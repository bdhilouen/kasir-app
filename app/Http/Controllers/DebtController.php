<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DebtController extends Controller
{
    /**
     * Display a listing of debts.
     * GET /api/debts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Debt::with('transaction');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by customer name
        if ($request->has('search')) {
            $query->where('customer_name', 'ilike', "%{$request->search}%");
        }

        // Filter by due date (hutang yang sudah jatuh tempo)
        if ($request->boolean('overdue')) {
            $query->where('due_date', '<', now()->toDateString())
                  ->whereIn('status', ['unpaid', 'partial']);
        }

        $debts = $query->orderBy('created_at', 'desc')
                       ->paginate($request->input('per_page', 15));

        // Hitung total sisa hutang keseluruhan
        $totalRemaining = Debt::whereIn('status', ['unpaid', 'partial'])
                              ->sum('remaining_debt');

        return response()->json([
            'success' => true,
            'data'    => $debts,
            'meta'    => [
                'total_remaining_debt' => $totalRemaining,
            ],
        ]);
    }

    /**
     * Display the specified debt.
     * GET /api/debts/{id}
     */
    public function show(Debt $debt): JsonResponse
    {
        $debt->load('transaction.transactionDetails.product');

        return response()->json([
            'success' => true,
            'data'    => $debt,
        ]);
    }

    /**
     * Proses pembayaran hutang (cicilan atau lunas).
     * POST /api/debts/{id}/pay
     */
    public function pay(Request $request, Debt $debt): JsonResponse
    {
        if ($debt->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Hutang ini sudah lunas.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => "required|numeric|min:1|max:{$debt->remaining_debt}",
            'notes'  => 'nullable|string|max:255',
        ]);

        $result = DB::transaction(function () use ($debt, $validated) {
            $newPaidAmount    = $debt->paid_amount + $validated['amount'];
            $newRemainingDebt = $debt->total_debt - $newPaidAmount;

            // Tentukan status baru
            $newStatus = $newRemainingDebt <= 0 ? 'paid' : 'partial';

            // Update debt
            $debt->update([
                'paid_amount'    => $newPaidAmount,
                'remaining_debt' => max(0, $newRemainingDebt),
                'status'         => $newStatus,
                'notes'          => $validated['notes'] ?? $debt->notes,
            ]);

            // Kalau hutang lunas, update juga status transaksinya
            if ($newStatus === 'paid') {
                $debt->transaction->update([
                    'status'      => 'paid',
                    'paid_amount' => $debt->total_debt,
                ]);
            }

            return $debt->fresh('transaction');
        });

        $message = $result->status === 'paid'
            ? 'Hutang berhasil dilunasi.'
            : 'Pembayaran cicilan berhasil dicatat.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $result,
        ]);
    }

    /**
     * Update due date atau notes hutang.
     * PATCH /api/debts/{id}
     */
    public function update(Request $request, Debt $debt): JsonResponse
    {
        if ($debt->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Hutang yang sudah lunas tidak bisa diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'due_date' => 'nullable|date|after_or_equal:today',
            'notes'    => 'nullable|string|max:255',
        ]);

        $debt->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data hutang berhasil diperbarui.',
            'data'    => $debt->fresh(),
        ]);
    }
}