<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login to Your Account</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f9f9f9; padding:20px;">
    <div style="max-width:600px; margin:auto; background:#fff; border-radius:8px; padding:20px; border:1px solid #ddd;">
        <h2 style="color:#333;">Login to Your Account</h2>
        <p>Hello,</p>
        <p>You requested to login to your account. Click the button below to continue:</p>

        <p style="text-align:center; margin:30px 0;">
            <a href="{{ $link }}" style="background:#4CAF50; color:#fff; padding:12px 20px; text-decoration:none; border-radius:5px;">
                Login Now
            </a>
        </p>

        <p>If you didn’t request this, you can safely ignore this email.</p>
        <p style="color:#777;">This link will expire in 15 minutes.</p>
    </div>
</body>
</html>
