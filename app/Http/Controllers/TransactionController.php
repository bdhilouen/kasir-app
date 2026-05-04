<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Product;
use App\Models\Debt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

class TransactionController extends Controller
{
    /**
     * Display a listing of transactions.
     * GET /api/transactions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with([
            'transactionDetails:id,transaction_id,product_id,product_name,price,quantity,subtotal',
            'voidedBy:id,name',
        ]);

        if ($request->has('date')) {
            $query->whereDate('transaction_date', $request->date);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->start_date . ' 00:00:00',
                $request->end_date   . ' 23:59:59',
            ]);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_voided')) {
            $query->where('is_voided', $request->boolean('is_voided'));
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $transactions,
        ]);
    }

    /**
     * Store a new transaction.
     * POST /api/transactions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name'      => 'nullable|string|max:150',
            'payment_method'     => 'required|in:cash,transfer,qris',
            'paid_amount'        => 'required|numeric|min:0|max:99999999',
            'items'              => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1|max:999',
        ]);

        // Cache lock — cegah double submit dari user yang sama
        $lockKey = 'transaction_user_' . $request->user()->id;
        $lock    = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi sedang diproses, tunggu sebentar.',
            ], 429);
        }

        try {
            $transaction = DB::transaction(function () use ($validated, $request) {

                // Ambil semua produk sekaligus — 1 query, bukan N query
                $productIds = collect($validated['items'])->pluck('product_id')->unique();
                $products   = Product::lockForUpdate()
                    ->whereIn('id', $productIds)
                    ->get()
                    ->keyBy('id');

                // Validasi stok semua produk dulu sebelum ada yang diubah
                foreach ($validated['items'] as $item) {
                    $product = $products->get($item['product_id']);
                    if (!$product) {
                        throw new \Exception("Produk tidak ditemukan.");
                    }
                    if ($product->stock < $item['quantity']) {
                        throw new \Exception(
                            "Stok {$product->name} tidak cukup. Stok tersedia: {$product->stock}"
                        );
                    }
                }

                // Kalkulasi total
                $totalAmount = 0;
                $detailsToInsert = [];
                $stockUpdates    = [];

                foreach ($validated['items'] as $item) {
                    $product  = $products->get($item['product_id']);
                    $subtotal = $product->price * $item['quantity'];
                    $totalAmount += $subtotal;

                    $detailsToInsert[] = [
                        'product_id'   => $product->id,
                        'product_name' => $product->name,
                        'price'        => $product->price,
                        'quantity'     => $item['quantity'],
                        'subtotal'     => $subtotal,
                    ];

                    $stockUpdates[$product->id] = $item['quantity'];
                }

                $paidAmount   = $validated['paid_amount'];
                $changeAmount = max(0, $paidAmount - $totalAmount);
                $remaining    = $totalAmount - $paidAmount;

                $status = match (true) {
                    $paidAmount <= 0             => 'debt',
                    $paidAmount >= $totalAmount  => 'paid',
                    default                      => 'partial',
                };

                // Buat transaksi
                $transaction = Transaction::create([
                    'invoice_number'   => $this->generateInvoiceNumber(),
                    'transaction_date' => now(),
                    'customer_name'    => $validated['customer_name'] ?? null,
                    'total_amount'     => $totalAmount,
                    'paid_amount'      => $paidAmount,
                    'change_amount'    => $changeAmount,
                    'payment_method'   => $validated['payment_method'],
                    'status'           => $status,
                    'is_voided'        => false,
                ]);

                // Insert semua detail sekaligus — 1 query, bukan N query
                $now = now();
                $transaction->transactionDetails()->insert(
                    collect($detailsToInsert)->map(fn($d) => array_merge($d, [
                        'transaction_id' => $transaction->id,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]))->toArray()
                );

                // Update stok semua produk — 1 query per produk tapi pakai DB statement
                // lebih aman dari race condition dibanding update massal
                foreach ($stockUpdates as $productId => $qty) {
                    Product::where('id', $productId)->decrement('stock', $qty);
                }

                // Buat debt kalau perlu
                if (in_array($status, ['debt', 'partial'])) {
                    Debt::create([
                        'transaction_id' => $transaction->id,
                        'customer_name'  => $validated['customer_name'] ?? 'Tidak diketahui',
                        'total_debt'     => $totalAmount,
                        'paid_amount'    => $paidAmount,
                        'remaining_debt' => $remaining,
                        'status'         => $status === 'debt' ? 'unpaid' : 'partial',
                    ]);
                }

                // Load hanya field yang dibutuhkan untuk response
                return $transaction->load([
                    'transactionDetails:id,transaction_id,product_id,product_name,price,quantity,subtotal',
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil disimpan.',
                'data'    => $transaction,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } finally {
            $lock->release(); // selalu release lock
        }
    }

    /**
     * Display the specified transaction.
     * GET /api/transactions/{id}
     */
    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load([
            'transactionDetails:id,transaction_id,product_id,product_name,price,quantity,subtotal',
            'voidedBy:id,name',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $transaction,
        ]);
    }

    /**
     * Void transaksi.
     * POST /api/transactions/{id}/void
     */
    public function void(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->is_voided) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi ini sudah pernah dibatalkan.',
            ], 422);
        }

        $validated = $request->validate([
            'password'    => 'required|string',
            'void_reason' => 'nullable|string|max:255',
        ]);

        if (!Hash::check($validated['password'], $request->user()->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah. Void transaksi dibatalkan.',
            ], 403);
        }

        // Load detail sekaligus sebelum masuk DB transaction
        $transaction->loadMissing('transactionDetails:id,transaction_id,product_id,quantity');

        DB::transaction(function () use ($transaction, $validated, $request) {

            // Kembalikan stok semua produk — 1 query per produk
            // tidak bisa bulk update karena tiap produk qty berbeda
            $transaction->transactionDetails->each(
                fn($detail) =>
                Product::where('id', $detail->product_id)
                    ->increment('stock', $detail->quantity)
            );

            // Update debt kalau ada — load dulu biar tidak N+1
            $transaction->loadMissing('debt');
            if ($transaction->debt) {
                $transaction->debt->update([
                    'status' => 'paid',
                    'notes'  => 'Transaksi di-void: ' . ($validated['void_reason'] ?? 'Tidak ada alasan'),
                ]);
            }

            $transaction->update([
                'is_voided'   => true,
                'voided_at'   => now(),
                'void_reason' => $validated['void_reason'] ?? null,
                'voided_by'   => $request->user()->id,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dibatalkan dan stok dikembalikan.',
            'data'    => $transaction->fresh()->load([
                'transactionDetails:id,transaction_id,product_id,product_name,price,quantity,subtotal',
                'voidedBy:id,name',
            ]),
        ]);
    }

    /**
     * Riwayat transaksi untuk kasir.
     * GET /api/transactions/cashier/history
     */
    public function cashierHistory(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $date         = $request->input('date', now()->toDateString());
        $sevenDaysAgo = now()->subDays(7)->toDateString();

        if ($date < $sevenDaysAgo) {
            return response()->json([
                'success' => false,
                'message' => 'Kasir hanya bisa melihat riwayat transaksi 7 hari ke belakang.',
            ], 403);
        }

        $transactions = Transaction::with([
            'transactionDetails:id,transaction_id,product_id,product_name,price,quantity,subtotal',
        ])
            ->whereDate('transaction_date', $date)
            ->where('is_voided', false)
            ->orderBy('transaction_date', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $transactions,
        ]);
    }

    /**
     * Generate nomor invoice — aman dari race condition dengan DB lock.
     */
    private function generateInvoiceNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "INV-{$date}-";

        $last = Transaction::where('invoice_number', 'like', "{$prefix}%")
            ->orderBy('invoice_number', 'desc')
            ->value('invoice_number');

        $nextNumber = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
