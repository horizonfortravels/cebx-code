<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>دعوة للانضمام</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.7; color: #1f2937;">
    <p>مرحباً،</p>

    <p>
        تمت دعوتك للانضمام إلى <strong>{{ $organizationName }}</strong>
        بدور <strong>{{ $roleName }}</strong>.
    </p>

    <p>
        الجهة الداعية: <strong>{{ $inviterName }}</strong><br>
        البريد المدعو: <strong>{{ $email }}</strong>
    </p>

    <p>لمراجعة الدعوة ومتابعة القبول، استخدم الرابط التالي:</p>

    <p>
        <a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a>
    </p>

    <p>تنتهي صلاحية هذه الدعوة في: <strong>{{ $expiresAt }}</strong></p>

    <p>فريق CBEX</p>
</body>
</html>
