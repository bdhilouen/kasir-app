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

class TransactionController extends Controller
{
    /**
     * Display a listing of transactions — admin melihat semua termasuk yang void.
     * GET /api/transactions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with([
            'transactionDetails.product',
            'voidedBy:id,name',
        ]);

        // Filter tanggal
        if ($request->has('date')) {
            $query->whereDate('transaction_date', $request->date);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->start_date . ' 00:00:00',
                $request->end_date   . ' 23:59:59',
            ]);
        }

        // Filter status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter void — default tampilkan semua, bisa filter hanya void atau hanya aktif
        if ($request->has('is_voided')) {
            $query->where('is_voided', $request->boolean('is_voided'));
        }

        // Filter payment method
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
            'paid_amount'        => 'required|numeric|min:0',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        $transaction = DB::transaction(function () use ($validated) {
            $totalAmount = 0;
            $itemsToSave = [];

            foreach ($validated['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok {$product->name} tidak cukup. Stok tersedia: {$product->stock}");
                }

                $subtotal     = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                $itemsToSave[] = [
                    'product'      => $product,
                    'quantity'     => $item['quantity'],
                    'price'        => $product->price,
                    'subtotal'     => $subtotal,
                    'product_name' => $product->name,
                ];
            }

            $paidAmount   = $validated['paid_amount'];
            $changeAmount = max(0, $paidAmount - $totalAmount);
            $remaining    = $totalAmount - $paidAmount;

            if ($paidAmount <= 0) {
                $status = 'debt';
            } elseif ($paidAmount >= $totalAmount) {
                $status = 'paid';
            } else {
                $status = 'partial';
            }

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

            foreach ($itemsToSave as $item) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id'     => $item['product']->id,
                    'product_name'   => $item['product_name'],
                    'price'          => $item['price'],
                    'quantity'       => $item['quantity'],
                    'subtotal'       => $item['subtotal'],
                ]);

                $item['product']->decrement('stock', $item['quantity']);
            }

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

            return $transaction->load('transactionDetails');
        });

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil disimpan.',
            'data'    => $transaction,
        ], 201);
    }

    /**
     * Display the specified transaction.
     * GET /api/transactions/{id}
     */
    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load([
            'transactionDetails.product',
            'voidedBy:id,name',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $transaction,
        ]);
    }

    /**
     * Void transaksi — stok dikembalikan, data tetap tersimpan.
     * POST /api/transactions/{id}/void
     */
    public function void(Request $request, Transaction $transaction): JsonResponse
    {
        // Cek apakah transaksi sudah di-void sebelumnya
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

        // Verifikasi password kasir yang sedang login
        if (!Hash::check($validated['password'], $request->user()->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah. Void transaksi dibatalkan.',
            ], 403);
        }

        DB::transaction(function () use ($transaction, $validated, $request) {

            // Kembalikan stok semua produk
            foreach ($transaction->transactionDetails as $detail) {
                Product::where('id', $detail->product_id)
                       ->increment('stock', $detail->quantity);
            }

            // Void debt juga kalau ada
            if ($transaction->debt) {
                $transaction->debt->update([
                    'status' => 'paid', // tutup hutangnya karena transaksi dibatalkan
                    'notes'  => 'Transaksi di-void: ' . ($validated['void_reason'] ?? 'Tidak ada alasan'),
                ]);
            }

            // Update transaksi jadi void — data tetap ada
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
                'transactionDetails.product',
                'voidedBy:id,name',
            ]),
        ]);
    }

    /**
     * Riwayat transaksi untuk kasir — maksimal 7 hari ke belakang, tidak tampilkan void.
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

        $transactions = Transaction::with('transactionDetails.product')
            ->whereDate('transaction_date', $date)
            ->where('is_voided', false) // kasir tidak perlu lihat yang void
            ->orderBy('transaction_date', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $transactions,
        ]);
    }

    private function generateInvoiceNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "INV-{$date}-";

        $last = Transaction::where('invoice_number', 'like', "{$prefix}%")
                           ->orderBy('invoice_number', 'desc')
                           ->value('invoice_number');

        $nextNumber = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}