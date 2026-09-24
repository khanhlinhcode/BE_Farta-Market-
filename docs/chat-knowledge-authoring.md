# Soạn và đồng bộ knowledge cho chatbot

Knowledge chỉ dành cho chính sách đã được Farta Market xác nhận: giao hàng,
đổi trả, thanh toán, bảo quản và hướng dẫn mua hàng. Không đưa giá, tồn kho,
trạng thái đơn/thanh toán, secret, `.env`, dữ liệu người dùng hoặc nội dung
quản trị riêng tư vào tài liệu. Các dữ liệu động đó vẫn phải đọc từ MySQL hoặc
`SiteSetting`.

## Quy trình xuất bản

1. Sao chép `resources/chat/knowledge/farta-market.sample.json` sang một tên
   file JSON mới trong cùng thư mục.
2. Chọn `source_id` chữ thường, ổn định và duy nhất; không đổi khi chỉ cập nhật
   nội dung. Tăng `version` khi chính sách thay đổi.
3. Viết mỗi chủ đề thành các `sections` có `heading` rõ ràng. Nội dung phải là
   câu hoàn chỉnh đã được chủ cửa hàng duyệt.
4. Giữ `status` là `draft` trong lúc rà soát. Draft không được index và không
   thể xuất hiện trong câu trả lời.
5. Đổi sang `published`, rồi kiểm tra và đồng bộ:

```bash
php artisan chat:knowledge:sync --dry-run
php artisan chat:knowledge:sync
```

Command từ chối placeholder (`TODO`, `CHANGEME`, `{{...}}`) và nội dung giống
chỉ thị hệ thống/prompt injection. Mỗi document/chunk có checksum nên chạy lại
không tạo bản sao. Khi Qdrant Cloud Inference được bật, command chỉ thay thế các
điểm có đúng `source_id` trong collection Farta chuyên dụng; point ID và payload
`chunk_id` đều bằng ID chunk trong MySQL. Nó không ghi hoặc xóa collection khác.

## Schema

Các trường bắt buộc: `source_id`, `title`, `locale` (`vi` hoặc `en`), `topic`,
`version`, `status`, `updated_at`, `owner`, và mảng `sections`. Mỗi section cần
`heading` và `content`. Chunking giữ ranh giới heading và câu, tối đa khoảng 900
ký tự mỗi chunk.

## Kiểm tra sau đồng bộ

- Hỏi cùng một ý bằng nhiều cách diễn đạt VI/EN.
- Xác nhận response có `answer_status=verified` và citation đúng tài liệu.
- Hỏi một điều chưa có nguồn; chatbot phải từ chối thay vì suy đoán.
- Chỉnh file đã từng xuất bản về `draft`, chạy lại, rồi xác nhận local chunks và
  các vector point của đúng `source_id` đã được thu hồi.
