<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class RegisterController extends Controller
{
    /**
     * Step 1 — Kirim OTP ke email.
     * POST /api/register/send-otp
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
        ]);

        // Hapus OTP lama yang belum dipakai untuk email ini
        OtpCode::where('email', $request->email)->delete();

        // Generate OTP 6 digit
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'email'      => $request->email,
            'code'       => $otp,
            'expires_at' => now()->addMinutes(10),
            'is_used'    => false,
        ]);

        // Kirim email
        Mail::to($request->email)->send(new OtpMail($otp, $request->email));

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP telah dikirim ke email kamu.',
        ]);
    }

    /**
     * Step 2 — Verifikasi OTP saja (opsional, untuk validasi real-time).
     * POST /api/register/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        $otpRecord = OtpCode::where('email', $request->email)
            ->where('code', $request->otp)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP tidak valid atau sudah kadaluarsa.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP valid.',
        ]);
    }

    /**
     * Step 3 — Register akun admin setelah OTP diverifikasi.
     * POST /api/register
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:8',
            'otp'      => 'required|string|size:6',
        ]);

        // Verifikasi OTP
        $otpRecord = OtpCode::where('email', $request->email)
            ->where('code', $request->otp)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP tidak valid atau sudah kadaluarsa.',
            ], 422);
        }

        // Tandai OTP sudah dipakai
        $otpRecord->update(['is_used' => true]);

        // Buat akun admin
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'admin', // selalu admin
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun admin berhasil dibuat. Silakan login.',
            'data'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ], 201);
    }
}