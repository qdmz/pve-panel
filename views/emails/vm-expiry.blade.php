<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>VM Expiry Notice</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px;">
        <h2 style="color: #E53E3E;">VM Expiry Notice</h2>
        <p>Your virtual machine <strong>{{ $vm_name ?? 'N/A' }}</strong> will expire in <strong>{{ $days_left ?? 0 }} day(s)</strong>.</p>
        <p>Please renew your VM to avoid service interruption. Once expired, your VM will be automatically suspended and may be deleted after a grace period.</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ config('app.frontend_url') }}/billing" style="background: #4F46E5; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block;">
                Renew Now
            </a>
        </p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #999; font-size: 12px;">This is an automated email, please do not reply.</p>
    </div>
</body>
</html>
