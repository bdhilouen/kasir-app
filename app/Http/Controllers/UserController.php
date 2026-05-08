<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * List kasir milik admin yang sedang login.
     * GET /api/users
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('role', 'cashier')
            ->where('created_by', $request->user()->id);

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Search by name atau email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->select('id', 'name', 'email', 'role', 'created_by', 'created_at')
            ->orderBy('role')
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Tambah kasir baru. Akun admin hanya dibuat dari registrasi.
     * POST /api/users
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => 'sometimes|in:cashier',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'cashier',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Kasir '{$user->name}' berhasil ditambahkan.",
            'data' => $user->only('id', 'name', 'email', 'role', 'created_by', 'created_at'),
        ], 201);
    }

    /**
     * Detail satu user.
     * GET /api/users/{id}
     */
    public function show(Request $request, User $user): JsonResponse
    {
        if (! $this->isManagedCashier($request, $user)) {
            return $this->cashierNotFoundResponse();
        }

        return response()->json([
            'success' => true,
            'data' => $user->only('id', 'name', 'email', 'role', 'created_by', 'created_at'),
        ]);
    }

    /**
     * Edit nama atau email kasir milik admin.
     * PUT/PATCH /api/users/{id}
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if (! $this->isManagedCashier($request, $user)) {
            return $this->cashierNotFoundResponse();
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => "sometimes|required|email|unique:users,email,{$user->id}",
            'role' => 'sometimes|required|in:cashier',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data user berhasil diperbarui.',
            'data' => $user->fresh()->only('id', 'name', 'email', 'role', 'created_by', 'created_at'),
        ]);
    }

    /**
     * Hapus user.
     * DELETE /api/users/{id}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (! $this->isManagedCashier($request, $user)) {
            return $this->cashierNotFoundResponse();
        }

        // Hapus semua token user yang dihapus (force logout)
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "User '{$user->name}' berhasil dihapus.",
        ]);
    }

    /**
     * Reset password oleh admin.
     * PATCH /api/users/{id}/reset-password
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if (! $this->isManagedCashier($request, $user)) {
            return $this->cashierNotFoundResponse();
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Force logout semua sesi user tersebut
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => "Password '{$user->name}' berhasil direset. User harus login ulang.",
        ]);
    }

    /**
     * Ganti password sendiri (untuk admin maupun kasir).
     * PATCH /api/users/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Verifikasi password lama
        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai.',
            ], 422);
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Hapus semua token lain kecuali yang sedang dipakai
        $request->user()->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah.',
        ]);
    }

    private function isManagedCashier(Request $request, User $user): bool
    {
        return $user->role === 'cashier'
            && $user->created_by === $request->user()->id;
    }

    private function cashierNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Akun kasir tidak ditemukan.',
        ], 404);
    }
}
