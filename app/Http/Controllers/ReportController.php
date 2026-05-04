<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Debt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Laporan penjualan harian.
     * GET /api/reports/daily?date=2025-03-21
     */
    public function daily(Request $request): JsonResponse
    {
        $date       = $request->input('date', now()->toDateString());
        $categoryId = $request->input('category_id');

        // Ambil summary langsung dari DB — tidak load transaksi ke memory
        $summary = $this->buildSummaryFromDB(
            startDate:  $date,
            endDate:    $date,
            categoryId: $categoryId,
        );

        // Transaksi hanya diload untuk ditampilkan di list, bukan untuk kalkulasi
        $query = Transaction::with([
                'transactionDetails:id,transaction_id,product_id,product_name,price,quantity,subtotal',
            ])
            ->whereDate('transaction_date', $date)
            ->where('is_voided', false)
            ->orderBy('transaction_date', 'desc');

        if ($categoryId) {
            $query->whereHas('transactionDetails.product', fn($q) =>
                $q->where('category_id', $categoryId)
            );
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'date'         => $date,
                'category_id'  => $categoryId,
                'summary'      => $summary,
                'transactions' => $query->get(),
            ],
        ]);
    }

    /**
     * Laporan penjualan mingguan.
     * GET /api/reports/weekly?date=2025-03-21
     */
    public function weekly(Request $request): JsonResponse
    {
        $date       = $request->input('date', now()->toDateString());
        $categoryId = $request->input('category_id');
        $startDate  = now()->parse($date)->startOfWeek()->toDateString();
        $endDate    = now()->parse($date)->endOfWeek()->toDateString();

        return response()->json([
            'success' => true,
            'data'    => [
                'start_date'      => $startDate,
                'end_date'        => $endDate,
                'category_id'     => $categoryId,
                'summary'         => $this->buildSummaryFromDB($startDate, $endDate, $categoryId),
                'daily_breakdown' => $this->buildDailyBreakdownFromDB($startDate, $endDate, $categoryId),
            ],
        ]);
    }

    /**
     * Laporan penjualan bulanan.
     * GET /api/reports/monthly?month=2025-03
     */
    public function monthly(Request $request): JsonResponse
    {
        $month      = $request->input('month', now()->format('Y-m'));
        $categoryId = $request->input('category_id');
        $startDate  = now()->parse($month . '-01')->startOfMonth()->toDateString();
        $endDate    = now()->parse($month . '-01')->endOfMonth()->toDateString();

        return response()->json([
            'success' => true,
            'data'    => [
                'month'           => $month,
                'start_date'      => $startDate,
                'end_date'        => $endDate,
                'category_id'     => $categoryId,
                'summary'         => $this->buildSummaryFromDB($startDate, $endDate, $categoryId),
                'daily_breakdown' => $this->buildDailyBreakdownFromDB($startDate, $endDate, $categoryId),
                'top_products'    => $this->buildTopProductsFromDB($startDate, $endDate, $categoryId),
            ],
        ]);
    }

    /**
     * Laporan custom range tanggal.
     * GET /api/reports/range?start_date=2025-03-01&end_date=2025-03-21
     */
    public function range(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $categoryId = $request->input('category_id');

        return response()->json([
            'success' => true,
            'data'    => [
                'start_date'   => $request->start_date,
                'end_date'     => $request->end_date,
                'category_id'  => $categoryId,
                'summary'      => $this->buildSummaryFromDB($request->start_date, $request->end_date, $categoryId),
                'top_products' => $this->buildTopProductsFromDB($request->start_date, $request->end_date, $categoryId),
            ],
        ]);
    }

    /**
     * Data untuk grafik — revenue per hari dalam range.
     * GET /api/reports/chart?start_date=2025-03-01&end_date=2025-03-31&category_id=1
     */
    public function chartData(Request $request): JsonResponse
    {
        $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $startDate  = $request->start_date;
        $endDate    = $request->end_date;
        $categoryId = $request->input('category_id');

        // Semua kalkulasi dilakukan di DB — tidak ada loop di PHP
        $chartData = $this->buildDailyBreakdownFromDB($startDate, $endDate, $categoryId);

        return response()->json([
            'success' => true,
            'data'    => [
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'category_id' => $categoryId,
                'chart'       => $chartData,
            ],
        ]);
    }

    /**
     * Breakdown revenue per kategori dalam range tanggal.
     * GET /api/reports/category-breakdown?start_date=2025-03-01&end_date=2025-03-31
     */
    public function categoryBreakdown(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        // Semua grouping dilakukan di DB — tidak ada loop PHP
        $breakdown = TransactionDetail::select(
                'products.category_id',
                DB::raw("COALESCE(categories.name, 'Tidak berkategori') as category_name"),
                DB::raw('SUM(transaction_details.subtotal) as total_revenue'),
                DB::raw('SUM(transaction_details.quantity) as total_quantity'),
                DB::raw('COUNT(transaction_details.id) as total_items'),
            )
            ->join('products', 'products.id', '=', 'transaction_details.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereHas('transaction', fn($q) =>
                $q->whereBetween('transaction_date', [
                        $request->start_date . ' 00:00:00',
                        $request->end_date   . ' 23:59:59',
                    ])
                    ->where('is_voided', false)
                    ->where('status', '!=', 'debt')
            )
            ->groupBy('products.category_id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'start_date' => $request->start_date,
                'end_date'   => $request->end_date,
                'breakdown'  => $breakdown,
            ],
        ]);
    }

    /**
     * Rekap hutang.
     * GET /api/reports/debts
     */
    public function debtSummary(): JsonResponse
    {
        // 1 query untuk total unpaid dan partial sekaligus
        $totals = Debt::whereIn('status', ['unpaid', 'partial'])
            ->select(
                'status',
                DB::raw('SUM(remaining_debt) as total'),
            )
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalUnpaid  = $totals['unpaid']  ?? 0;
        $totalPartial = $totals['partial'] ?? 0;

        $overdueDebts = Debt::with('transaction:id,invoice_number,transaction_date')
            ->whereIn('status', ['unpaid', 'partial'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->select('id', 'transaction_id', 'customer_name', 'remaining_debt', 'due_date', 'status')
            ->get();

        $debtsByCustomer = Debt::whereIn('status', ['unpaid', 'partial'])
            ->select(
                'customer_name',
                DB::raw('SUM(remaining_debt) as total_remaining'),
                DB::raw('COUNT(*) as total_hutang'),
            )
            ->groupBy('customer_name')
            ->orderByDesc('total_remaining')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_unpaid'      => $totalUnpaid,
                'total_partial'     => $totalPartial,
                'total_outstanding' => $totalUnpaid + $totalPartial,
                'overdue_debts'     => $overdueDebts,
                'debts_by_customer' => $debtsByCustomer,
            ],
        ]);
    }

    /**
     * Produk terlaris.
     * GET /api/reports/top-products?start_date=2025-03-01&end_date=2025-03-21&limit=10
     */
    public function topProducts(Request $request): JsonResponse
    {
        $request->validate([
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:categories,id',
            'limit'       => 'nullable|integer|min:1|max:50',
        ]);

        $topProducts = $this->buildTopProductsFromDB(
            startDate:  $request->start_date,
            endDate:    $request->end_date,
            categoryId: $request->input('category_id'),
            limit:      $request->input('limit', 10),
        );

        return response()->json([
            'success' => true,
            'data'    => $topProducts,
        ]);
    }

    // ============================================================
    // Private Helper Methods — semua kalkulasi dilakukan di DB
    // ============================================================

    /**
     * Build summary langsung dari DB tanpa load transaksi ke memory.
     */
    private function buildSummaryFromDB(
        string  $startDate,
        string  $endDate,
        ?int    $categoryId = null,
    ): array {
        $baseQuery = Transaction::whereBetween('transaction_date', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ])
            ->where('is_voided', false);

        if ($categoryId) {
            $baseQuery->whereHas('transactionDetails.product', fn($q) =>
                $q->where('category_id', $categoryId)
            );
        }

        // Kalau ada filter kategori, revenue dihitung dari detail produk
        if ($categoryId) {
            $totalRevenue = TransactionDetail::whereHas('transaction', fn($q) =>
                    $q->whereBetween('transaction_date', [
                            $startDate . ' 00:00:00',
                            $endDate   . ' 23:59:59',
                        ])
                        ->where('is_voided', false)
                )
                ->whereHas('product', fn($q) => $q->where('category_id', $categoryId))
                ->sum('subtotal');
        } else {
            $totalRevenue = (clone $baseQuery)->sum('total_amount');
        }

        // Aggregasi per status — 1 query
        $byStatus = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Aggregasi per payment method — 1 query
        $byPayment = (clone $baseQuery)
            ->select('payment_method', DB::raw('SUM(paid_amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $totalTransactions = (clone $baseQuery)->count();
        $totalCollected    = (clone $baseQuery)->sum('paid_amount');

        return [
            'total_transactions' => $totalTransactions,
            'total_revenue'      => $totalRevenue,
            'total_collected'    => $totalCollected,
            'by_status'          => [
                'paid'    => ['count' => $byStatus['paid']->count    ?? 0, 'amount' => $byStatus['paid']->amount    ?? 0],
                'partial' => ['count' => $byStatus['partial']->count ?? 0, 'amount' => $byStatus['partial']->amount ?? 0],
                'debt'    => ['count' => $byStatus['debt']->count    ?? 0, 'amount' => $byStatus['debt']->amount    ?? 0],
            ],
            'by_payment_method'  => [
                'cash'     => $byPayment['cash']->total     ?? 0,
                'transfer' => $byPayment['transfer']->total ?? 0,
                'qris'     => $byPayment['qris']->total     ?? 0,
            ],
        ];
    }

    /**
     * Build daily breakdown langsung dari DB — tidak ada loop PHP per hari.
     * PostgreSQL generate_series dipakai untuk mengisi hari yang kosong.
     */
    private function buildDailyBreakdownFromDB(
        string $startDate,
        string $endDate,
        ?int   $categoryId = null,
    ): array {
        // Kalau ada filter kategori, revenue dari detail; kalau tidak, dari transaksi
        if ($categoryId) {
            $rows = DB::select("
                SELECT
                    d::date AS date,
                    COALESCE(SUM(td.subtotal), 0)        AS revenue,
                    COALESCE(COUNT(DISTINCT t.id), 0)    AS total_transactions,
                    COALESCE(SUM(t.paid_amount), 0)      AS total_collected
                FROM generate_series(
                    :start::date,
                    :end::date,
                    '1 day'::interval
                ) AS d
                LEFT JOIN transactions t
                    ON t.transaction_date::date = d::date
                    AND t.is_voided = false
                    AND t.status != 'debt'
                LEFT JOIN transaction_details td
                    ON td.transaction_id = t.id
                LEFT JOIN products p
                    ON p.id = td.product_id
                    AND p.category_id = :category_id
                GROUP BY d
                ORDER BY d
            ", [
                'start'       => $startDate,
                'end'         => $endDate,
                'category_id' => $categoryId,
            ]);
        } else {
            $rows = DB::select("
                SELECT
                    d::date AS date,
                    COALESCE(SUM(t.total_amount), 0)  AS revenue,
                    COALESCE(COUNT(t.id), 0)          AS total_transactions,
                    COALESCE(SUM(t.paid_amount), 0)   AS total_collected
                FROM generate_series(
                    :start::date,
                    :end::date,
                    '1 day'::interval
                ) AS d
                LEFT JOIN transactions t
                    ON t.transaction_date::date = d::date
                    AND t.is_voided = false
                GROUP BY d
                ORDER BY d
            ", [
                'start' => $startDate,
                'end'   => $endDate,
            ]);
        }

        return array_map(fn($row) => [
            'date'               => $row->date,
            'revenue'            => (float) $row->revenue,
            'total_transactions' => (int)   $row->total_transactions,
            'total_collected'    => (float) $row->total_collected,
        ], $rows);
    }

    /**
     * Build top products langsung dari DB.
     */
    private function buildTopProductsFromDB(
        ?string $startDate  = null,
        ?string $endDate    = null,
        ?int    $categoryId = null,
        int     $limit      = 10,
    ): array {
        $query = TransactionDetail::select(
                'transaction_details.product_id',
                'transaction_details.product_name',
                DB::raw('SUM(transaction_details.quantity) as total_quantity'),
                DB::raw('SUM(transaction_details.subtotal) as total_revenue'),
            )
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transactions.is_voided', false)
            ->groupBy('transaction_details.product_id', 'transaction_details.product_name')
            ->orderByDesc('total_quantity')
            ->limit($limit);

        if ($startDate && $endDate) {
            $query->whereBetween('transactions.transaction_date', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ]);
        }

        if ($categoryId) {
            $query->join('products', 'products.id', '=', 'transaction_details.product_id')
                  ->where('products.category_id', $categoryId);
        }

        return $query->get()->toArray();
    }
}