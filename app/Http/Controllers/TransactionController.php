<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Product;
use App\Models\Debt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Display a listing of transactions.
     * GET /api/transactions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with('transactionDetails.product');

        // Filter by tanggal
        if ($request->has('date')) {
            $query->whereDate('transaction_date', $request->date);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->start_date . ' 00:00:00',
                $request->end_date   . ' 23:59:59',
            ]);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment method
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
            'customer_name'  => 'nullable|string|max:150',
            'payment_method' => 'required|in:cash,transfer,qris',
            'paid_amount'    => 'required|numeric|min:0',
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        // Wrap dalam DB transaction supaya kalau error, semua rollback
        $transaction = DB::transaction(function () use ($validated) {

            $totalAmount = 0;
            $itemsToSave = [];

            // Kalkulasi total & validasi stok semua item dulu sebelum disimpan
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
                    'product_name' => $product->name, // snapshot nama produk
                ];
            }

            // Hitung kembalian & status
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

            // Buat transaksi
            $transaction = Transaction::create([
                'invoice_number'  => $this->generateInvoiceNumber(),
                'transaction_date'=> now(),
                'customer_name'   => $validated['customer_name'] ?? null,
                'total_amount'    => $totalAmount,
                'paid_amount'     => $paidAmount,
                'change_amount'   => $changeAmount,
                'payment_method'  => $validated['payment_method'],
                'status'          => $status,
            ]);

            // Simpan detail & kurangi stok
            foreach ($itemsToSave as $item) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id'     => $item['product']->id,
                    'product_name'   => $item['product_name'],
                    'price'          => $item['price'],
                    'quantity'       => $item['quantity'],
                    'subtotal'       => $item['subtotal'],
                ]);

                // Kurangi stok produk
                $item['product']->decrement('stock', $item['quantity']);
            }

            // Kalau hutang, buat record di tabel debts
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
        $transaction->load('transactionDetails.product');

        return response()->json([
            'success' => true,
            'data'    => $transaction,
        ]);
    }

    /**
     * Transaksi tidak boleh diedit, hanya bisa void/cancel.
     * DELETE /api/transactions/{id}
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        // Transaksi yang sudah lunas tidak bisa dibatalkan
        if ($transaction->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi yang sudah lunas tidak bisa dibatalkan.',
            ], 422);
        }

        DB::transaction(function () use ($transaction) {
            // Kembalikan stok produk
            foreach ($transaction->transactionDetails as $detail) {
                Product::where('id', $detail->product_id)
                       ->increment('stock', $detail->quantity);
            }

            // Hapus debt kalau ada
            $transaction->debt()->delete();

            // Hapus transaksi (detail ikut terhapus karena cascade)
            $transaction->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dibatalkan dan stok dikembalikan.',
        ]);
    }

    /**
     * Generate nomor invoice otomatis.
     * Format: INV-YYYYMMDD-XXXX (contoh: INV-20250321-0001)
     */
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