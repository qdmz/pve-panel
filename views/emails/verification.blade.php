<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verify Your Email</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px;">
        <h2 style="color: #333;">Verify Your Email Address</h2>
        <p>Thank you for registering! Please click the button below to verify your email address:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ $url ?? '#' }}" style="background: #4F46E5; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block;">
                Verify Email
            </a>
        </p>
        <p style="color: #666; font-size: 14px;">If you did not create an account, no further action is required.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #999; font-size: 12px;">This is an automated email, please do not reply.</p>
    </div>
</body>
</html>
