<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Debt;
use App\Models\Product;
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
        $date = $request->input('date', now()->toDateString());

        $query = Transaction::with('transactionDetails.product.category')
            ->whereDate('transaction_date', $date);

        // Tambahkan filter kategori
        if ($request->has('category_id')) {
            $query->whereHas('transactionDetails.product', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        $transactions = $query->get();
        $summary      = $this->buildSummary($transactions, $request->category_id ?? null);

        return response()->json([
            'success' => true,
            'data'    => [
                'date'        => $date,
                'category_id' => $request->category_id ?? null,
                'summary'     => $summary,
                'transactions' => $transactions,
            ],
        ]);
    }

    /**
     * Laporan penjualan mingguan.
     * GET /api/reports/weekly?date=2025-03-21 (date = hari apa saja dalam minggu itu)
     */
    public function weekly(Request $request): JsonResponse
    {
        $date      = $request->input('date', now()->toDateString());
        $startDate = now()->parse($date)->startOfWeek()->toDateString();
        $endDate   = now()->parse($date)->endOfWeek()->toDateString();

        $query = Transaction::with('transactionDetails.product.category')
            ->whereBetween('transaction_date', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ]);

        if ($request->has('category_id')) {
            $query->whereHas('transactionDetails.product', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        $transactions = $query->get();

        $summary = $this->buildSummary($transactions, $request->category_id ?? null);
        $dailyBreakdown = $this->buildDailyBreakdown($transactions, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data'    => [
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'category_id' => $request->category_id ?? null,
                'summary'     => $summary,
                'daily_breakdown' => $dailyBreakdown,
            ],
        ]);
    }

    /**
     * Laporan penjualan bulanan.
     * GET /api/reports/monthly?month=2025-03
     */
    public function monthly(Request $request): JsonResponse
    {
        $month     = $request->input('month', now()->format('Y-m'));
        $startDate = now()->parse($month . '-01')->startOfMonth()->toDateString();
        $endDate   = now()->parse($month . '-01')->endOfMonth()->toDateString();

        $query = Transaction::with('transactionDetails.product.category')
            ->whereBetween('transaction_date', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ]);

        if ($request->has('category_id')) {
            $query->whereHas('transactionDetails.product', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        $transactions = $query->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'month'           => $month,
                'start_date'      => $startDate,
                'end_date'        => $endDate,
                'category_id'     => $request->category_id ?? null,
                'summary'         => $this->buildSummary($transactions, $request->category_id ?? null),
                'daily_breakdown' => $this->buildDailyBreakdown($transactions, $startDate, $endDate),
                'top_products'    => $this->buildTopProducts($transactions),
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

        $query = Transaction::with('transactionDetails.product.category')
            ->whereBetween('transaction_date', [
                $request->start_date . ' 00:00:00',
                $request->end_date   . ' 23:59:59',
            ]);

        if ($request->has('category_id')) {
            $query->whereHas('transactionDetails.product', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        $transactions = $query->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'start_date'   => $request->start_date,
                'end_date'     => $request->end_date,
                'category_id'  => $request->category_id ?? null,
                'summary'      => $this->buildSummary($transactions, $request->category_id ?? null),
                'top_products' => $this->buildTopProducts($transactions),
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
        $categoryId = $request->category_id ?? null;

        // Ambil semua transaksi dalam range
        $query = Transaction::with('transactionDetails.product')
            ->whereBetween('transaction_date', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ])
            ->where('status', '!=', 'debt'); // hanya yang ada pembayarannya

        if ($categoryId) {
            $query->whereHas('transactionDetails.product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        $transactions = $query->get();

        // Build chart data per hari
        $chartData = [];
        $current   = now()->parse($startDate);
        $end       = now()->parse($endDate);

        while ($current <= $end) {
            $dateStr = $current->toDateString();

            $dayTransactions = $transactions->filter(function ($t) use ($dateStr) {
                return now()->parse($t->transaction_date)->toDateString() === $dateStr;
            });

            // Kalau ada filter kategori, hitung revenue hanya dari produk kategori itu
            if ($categoryId) {
                $revenue = 0;
                foreach ($dayTransactions as $transaction) {
                    foreach ($transaction->transactionDetails as $detail) {
                        if ($detail->product && $detail->product->category_id == $categoryId) {
                            $revenue += $detail->subtotal;
                        }
                    }
                }
            } else {
                $revenue = $dayTransactions->sum('total_amount');
            }

            $chartData[] = [
                'date'               => $dateStr,
                'revenue'            => $revenue,
                'total_transactions' => $dayTransactions->count(),
            ];

            $current->addDay();
        }

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

        $details = TransactionDetail::with('product.category')
            ->whereHas('transaction', function ($q) use ($request) {
                $q->whereBetween('transaction_date', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date   . ' 23:59:59',
                ])->where('status', '!=', 'debt');
            })
            ->get();

        // Group by kategori
        $breakdown = [];

        foreach ($details as $detail) {
            $categoryId   = $detail->product->category_id ?? null;
            $categoryName = $detail->product->category->name ?? 'Tidak berkategori';
            $key          = $categoryId ?? 'uncategorized';

            if (!isset($breakdown[$key])) {
                $breakdown[$key] = [
                    'category_id'    => $categoryId,
                    'category_name'  => $categoryName,
                    'total_revenue'  => 0,
                    'total_quantity' => 0,
                    'total_products' => 0,
                ];
            }

            $breakdown[$key]['total_revenue']  += $detail->subtotal;
            $breakdown[$key]['total_quantity'] += $detail->quantity;
            $breakdown[$key]['total_products']++;
        }

        // Sort by revenue descending
        usort($breakdown, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        return response()->json([
            'success' => true,
            'data'    => [
                'start_date' => $request->start_date,
                'end_date'   => $request->end_date,
                'breakdown'  => array_values($breakdown),
            ],
        ]);
    }

    /**
     * Rekap hutang.
     * GET /api/reports/debts
     */
    public function debtSummary(Request $request): JsonResponse
    {
        $totalUnpaid = Debt::where('status', 'unpaid')->sum('remaining_debt');
        $totalPartial = Debt::where('status', 'partial')->sum('remaining_debt');

        $overdueDebts = Debt::with('transaction')
            ->whereIn('status', ['unpaid', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->whereNotNull('due_date')
            ->get();

        $debtsByCustomer = Debt::whereIn('status', ['unpaid', 'partial'])
            ->select('customer_name', DB::raw('SUM(remaining_debt) as total_remaining'))
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
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'limit'      => 'nullable|integer|min:1|max:50',
        ]);

        $query = TransactionDetail::select(
            'product_id',
            'product_name',
            DB::raw('SUM(quantity) as total_quantity'),
            DB::raw('SUM(subtotal) as total_revenue')
        )
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_quantity')
            ->limit($request->input('limit', 10));

        // Filter by tanggal kalau ada
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereHas('transaction', function ($q) use ($request) {
                $q->whereBetween('transaction_date', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date   . ' 23:59:59',
                ]);
            });
        }

        $topProducts = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $topProducts,
        ]);
    }

    // ========================
    // Private Helper Methods
    // ========================

    /**
     * Build ringkasan dari koleksi transaksi.
     */
    private function buildSummary($transactions, ?int $categoryId = null): array
    {
        // Kalau ada filter kategori, total revenue dihitung dari detail produk saja
        if ($categoryId) {
            $totalRevenue = 0;
            foreach ($transactions as $transaction) {
                foreach ($transaction->transactionDetails as $detail) {
                    if ($detail->product && $detail->product->category_id == $categoryId) {
                        $totalRevenue += $detail->subtotal;
                    }
                }
            }
        } else {
            $totalRevenue = $transactions->sum('total_amount');
        }

        $paid    = $transactions->where('status', 'paid');
        $partial = $transactions->where('status', 'partial');
        $debt    = $transactions->where('status', 'debt');

        return [
            'total_transactions' => $transactions->count(),
            'total_revenue'      => $totalRevenue,
            'total_collected'    => $transactions->sum('paid_amount'),
            'by_status' => [
                'paid'    => ['count' => $paid->count(),    'amount' => $paid->sum('total_amount')],
                'partial' => ['count' => $partial->count(), 'amount' => $partial->sum('total_amount')],
                'debt'    => ['count' => $debt->count(),    'amount' => $debt->sum('total_amount')],
            ],
            'by_payment_method' => [
                'cash'     => $transactions->where('payment_method', 'cash')->sum('paid_amount'),
                'transfer' => $transactions->where('payment_method', 'transfer')->sum('paid_amount'),
                'qris'     => $transactions->where('payment_method', 'qris')->sum('paid_amount'),
            ],
        ];
    }

    /**
     * Build breakdown per hari dalam range tanggal.
     */
    private function buildDailyBreakdown($transactions, string $startDate, string $endDate): array
    {
        $breakdown = [];
        $current   = now()->parse($startDate);
        $end       = now()->parse($endDate);

        while ($current <= $end) {
            $dateStr       = $current->toDateString();
            $dayTransactions = $transactions->filter(function ($t) use ($dateStr) {
                return now()->parse($t->transaction_date)->toDateString() === $dateStr;
            });

            $breakdown[] = [
                'date'                => $dateStr,
                'total_transactions'  => $dayTransactions->count(),
                'total_revenue'       => $dayTransactions->sum('total_amount'),
                'total_collected'     => $dayTransactions->sum('paid_amount'),
            ];

            $current->addDay();
        }

        return $breakdown;
    }

    /**
     * Build top produk dari koleksi transaksi.
     */
    private function buildTopProducts($transactions): array
    {
        $productSales = [];

        foreach ($transactions as $transaction) {
            foreach ($transaction->transactionDetails as $detail) {
                $key = $detail->product_id;
                if (!isset($productSales[$key])) {
                    $productSales[$key] = [
                        'product_id'     => $detail->product_id,
                        'product_name'   => $detail->product_name,
                        'total_quantity' => 0,
                        'total_revenue'  => 0,
                    ];
                }
                $productSales[$key]['total_quantity'] += $detail->quantity;
                $productSales[$key]['total_revenue']  += $detail->subtotal;
            }
        }

        // Sort by total_quantity descending
        usort($productSales, fn($a, $b) => $b['total_quantity'] <=> $a['total_quantity']);

        return array_values($productSales);
    }
}
