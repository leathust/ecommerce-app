<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>Liên hệ mới</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937">
  <h1 style="font-size:20px">Có yêu cầu liên hệ mới</h1>
  <p><strong>Người gửi:</strong> {{ $contact->full_name }}</p>
  <p><strong>Email:</strong> {{ $contact->email }}</p>
  @if($contact->phone)<p><strong>Điện thoại:</strong> {{ $contact->phone }}</p>@endif
  <p><strong>Chủ đề:</strong> {{ \App\Models\Contact::REASONS[$contact->reason] ?? $contact->reason }}</p>
  @if($contact->order_code)<p><strong>Mã đơn:</strong> {{ $contact->order_code }}</p>@endif
  <div><strong>Nội dung:</strong><div style="white-space:pre-wrap">{{ $contact->message }}</div></div>
  <p style="color:#64748b">Email này chỉ là thông báo nội bộ. Hãy phản hồi từ trang quản trị.</p>
</body>
</html>
