<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>{{ $replySubject }}</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937">
  <p>Xin chào {{ $customerName }},</p>
  <div style="white-space:pre-wrap">{{ $replyMessage }}</div>
  <p>Trân trọng,<br>{{ config('mail.from.name') }}</p>
</body>
</html>
