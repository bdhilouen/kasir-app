<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 480px; margin: 40px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1e293b; padding: 24px 32px; }
        .header h1 { color: white; margin: 0; font-size: 20px; }
        .body { padding: 32px; }
        .otp-box { background: #f1f5f9; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; }
        .otp-code { font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #1e293b; }
        .note { color: #94a3b8; font-size: 13px; margin-top: 8px; }
        .footer { padding: 16px 32px; background: #f8fafc; text-align: center; }
        .footer p { color: #94a3b8; font-size: 12px; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MaKasir</h1>
        </div>
        <div class="body">
            <p style="color: #374151; margin-top: 0;">Halo,</p>
            <p style="color: #374151;">Kamu menerima email ini karena ada permintaan registrasi akun admin untuk email <strong>{{ $email }}</strong>.</p>
            <p style="color: #374151;">Masukkan kode OTP berikut di halaman registrasi:</p>
            <div class="otp-box">
                <div class="otp-code">{{ $otp }}</div>
                <div class="note">Berlaku selama 10 menit</div>
            </div>
            <p style="color: #94a3b8; font-size: 13px;">Jika kamu tidak merasa melakukan registrasi, abaikan email ini.</p>
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} MaKasir. All rights reserved.</p>
        </div>
    </div>
</body>
</html>