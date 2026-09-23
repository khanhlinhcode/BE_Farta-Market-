# Prompt triển khai chống trùng đơn, xác minh email, giỏ theo phiên và loading UI

```text
Bạn là Senior Laravel 12 + React 19 Engineer, chuyên gia nghiệp vụ thương mại
điện tử, bảo mật ứng dụng và UI performance.

Mục tiêu:
1. Một thao tác đặt hàng chỉ tạo đúng một đơn, kể cả double click, retry mạng
   hoặc hai request đồng thời.
2. Customer chưa xác minh email có thể giữ session để gửi lại/xác minh email,
   nhưng tuyệt đối không được hiển thị như tài khoản đã đăng nhập trên header,
   không được checkout, dùng coupon hoặc review.
3. Giỏ khách chỉ tồn tại trong tab hiện tại, hết hạn sau 60 phút không hoạt
   động và bị xóa khi logout; không phục hồi giỏ cũ từ localStorage.
4. Trang đầu không co nội dung làm header sát footer. Trong khi chờ API phải
   giữ đúng khung category, product card và banner bằng skeleton blur nhẹ.
5. Ảnh product bên dưới màn hình đầu dùng native lazy loading và decoding bất
   đồng bộ. Hero đầu trang giữ kích thước cố định, không hiện banner fallback
   trước khi CMS trả dữ liệu.

Repository:
- Backend: /Users/linhto/Documents/Programming/sivicode/SVC01072023BE/SVC01072023BE
- Storefront: /Users/linhto/Documents/Programming/sivicode/websivi

Nguyên tắc bắt buộc:
- Đọc AGENTS.md, DESIGN.md và skill UI/accessibility trước khi sửa giao diện.
- Áp dụng ponytail: dùng Laravel transaction, database unique index,
  Cache::lock, React state và Web Storage có sẵn; không thêm package mới.
- Không đọc, ghi hoặc in secret trong .env.
- Không thay API field hiện có; chỉ được bổ sung migration/index và trạng thái
  nội bộ cần thiết.
- Không dùng localStorage để lưu order, PII hoặc giỏ hàng lâu dài.
- Không đánh dấu PASS nếu chưa chạy test tương ứng.

Triển khai backend:
- Thêm cột idempotency scope không-null vào idempotency_keys.
- Backfill `guest` hoặc `user:{id}` và tạo unique index trên
  `(idempotency_key, scope)`. Không dùng unique nullable user_id làm lớp bảo vệ
  duy nhất vì MySQL cho phép nhiều NULL.
- Trước khi tạo unique index, hợp nhất các idempotency record cũ bị trùng và
  giữ record đầu tiên; tuyệt đối không xóa order liên quan.
- Cả COD và VNPay phải query/create idempotency record theo scope.
- Giữ Cache::lock và DB transaction hiện tại.
- Cùng key + cùng payload trả lại đúng order cũ; cùng key + payload khác trả
  409; tồn kho và email xác nhận chỉ xử lý một lần.

Triển khai Storefront:
- Thêm ref đồng bộ trước mutation để lần submit thứ hai bị bỏ qua ngay cả trước
  khi React kịp render `isPending=true`.
- Chỉ đưa user vào Redux auth khi role=customer và email đã xác minh.
- Sau xác minh thành công, gọi /api/me và cập nhật lại Redux; trước đó header
  vẫn hiển thị nút đăng nhập.
- Chuyển cart sang sessionStorage với envelope `{value, expiresAt}`, TTL 60
  phút; xóa key localStorage cũ và xóa cart khi logout.
- Giữ guest cart trong đúng phiên hiện tại vì backend hỗ trợ guest checkout;
  không để nó tồn tại qua phiên trình duyệt cũ.
- Home loading phải giữ chiều cao và cấu trúc category/product/banner. Dùng
  `aria-busy`, tôn trọng reduced motion và không gây layout shift.
- Product card image dùng `<img loading="lazy" decoding="async">`, blur nhẹ
  trong lúc tải rồi chuyển về ảnh rõ. Không lazy-load hero LCP.

Kiểm thử bắt buộc:
- Backend OrderTest và PaymentTest, sau đó toàn bộ `php artisan test`.
- Test database unique guest scope và idempotent replay.
- Test auth reducer từ chối customer chưa xác minh và nhận customer đã xác minh.
- Test double-submit chỉ gọi API một lần.
- Test cart không còn trong localStorage và tự xóa sau TTL.
- Test skeleton giữ category/product/banner trong lúc API pending.
- Chạy Pint, Composer validate/audit, frontend unit, build, npm audit và
  Playwright E2E.
- Kiểm tra desktop/mobile 320, 768, 1024 và 1440 px.

Tiêu chí hoàn tất:
- Không còn đơn trùng trong test retry/concurrency cùng idempotency key.
- Header không hiện tên trước khi xác minh email.
- Giỏ cũ localStorage không được phục hồi.
- Khi tải chậm, header/footer không dính nhau và banner không nhảy hiện trước.
- Không có migration pending, test/build đều pass và không có High/Critical
  dependency advisory.
```
