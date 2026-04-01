<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إعادة تعيين كلمة المرور</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.8; color: #1f2937;">
    <p>مرحبًا،</p>

    <p>
        تلقينا طلبًا لإعادة تعيين كلمة المرور المرتبطة بالحساب:
        <strong>{{ $email }}</strong>
    </p>

    <p>لإنشاء كلمة مرور جديدة بشكل آمن، استخدم الرابط التالي:</p>

    <p>
        <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
    </p>

    <p>
        تنتهي صلاحية هذا الرابط في:
        <strong>{{ $expiresAt }}</strong>
    </p>

    <p>
        إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذه الرسالة بأمان.
    </p>

    <p>فريق CBEX</p>
</body>
</html>
