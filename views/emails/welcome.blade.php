<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px;">
        <h2 style="color: #333;">Welcome, {{ $name ?? 'User' }}!</h2>
        <p>Your email has been verified successfully. You now have full access to our platform.</p>
        <p>You can now:</p>
        <ul>
            <li>Browse and order virtual machines</li>
            <li>Manage your instances</li>
            <li>Access billing and support</li>
        </ul>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #999; font-size: 12px;">This is an automated email, please do not reply.</p>
    </div>
</body>
</html>
