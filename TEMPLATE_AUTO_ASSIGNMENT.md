# Template Auto-Assignment Documentation

## Tổng quan
Hệ thống Template Auto-Assignment tự động gán template PHP cho các page WordPress dựa trên slug của page, giúp đơn giản hóa việc quản lý template và tránh phải tạo page template thủ công.

## Cách hoạt động

### 1. Auto Template Assignment
- Khi user truy cập một page, hệ thống sẽ lấy slug của page đó
- So sánh slug với danh sách mapping đã định nghĩa
- Nếu tìm thấy mapping phù hợp, sẽ load template tương ứng
- Nếu không tìm thấy, sử dụng template mặc định của WordPress

### 2. Template Mapping
Hệ thống hỗ trợ 2 loại mapping:

#### Default Mapping (trong functions.php)
```php
'dashboard' => 'dashboard.php',
'website-list' => 'website_list.php',
'add-website' => 'addnew_website.php',
// ... và nhiều mapping khác
```

#### Custom Mapping (lưu trong WordPress Options)
- Có thể thêm thông qua admin panel
- Ưu tiên cao hơn default mapping
- Lưu trữ trong database, không cần sửa code

## Cách sử dụng

### 1. Tạo page mới
1. Vào WordPress Admin → Pages → Add New
2. Tạo page với slug mong muốn (ví dụ: "dashboard")
3. Hệ thống tự động áp dụng template tương ứng

### 2. Quản lý Template Mapping
1. Vào WordPress Admin → Appearance → Template Management
2. Xem danh sách mapping hiện tại
3. Thêm mapping mới nếu cần
4. Kiểm tra trạng thái file template

### 3. Debug và kiểm tra
- Khi đăng nhập với quyền admin, hệ thống tự động thêm debug info vào HTML source
- View source để xem: `<!-- DEBUG: Page Slug: [slug] | Suggested Template: [template] -->`

## Danh sách Template có sẵn

### Website Management
- `website_list.php` - Danh sách website
- `addnew_website.php` - Thêm website mới
- `edit_website.php` - Sửa website
- `detail_website.php` - Chi tiết website
- `xoa-website.php` - Xóa website
- `khoi-phuc-website.php` - Khôi phục website
- `attach_product_to_website.php` - Gán sản phẩm vào website

### Domain Management
- `domain_list.php` - Danh sách domain
- `addnew_domain.php` - Thêm domain mới
- `edit_domain.php` - Sửa domain
- `detail_domain.php` - Chi tiết domain

### Hosting Management
- `hosting_list.php` - Danh sách hosting
- `addnew_hosting.php` - Thêm hosting mới
- `edit_hosting.php` - Sửa hosting
- `detail_hosting.php` - Chi tiết hosting

### Maintenance Management
- `maintenance_list.php` - Danh sách bảo trì
- `addnew_maintenance.php` - Thêm gói bảo trì mới
- `edit_maintenance.php` - Sửa gói bảo trì
- `detail_maintenance.php` - Chi tiết gói bảo trì

### Service Management
- `service_list.php` - Danh sách dịch vụ
- `addnew_services.php` - Thêm dịch vụ mới
- `service_request.php` - Yêu cầu dịch vụ
- `service_quotation.php` - Báo giá dịch vụ
- `service_execution.php` - Thực hiện dịch vụ
- `service_completion.php` - Hoàn thành dịch vụ
- `service_invoice.php` - Hóa đơn dịch vụ

### Product & Catalog
- `product_catalog_list.php` - Danh sách sản phẩm
- `addnew_product_catalog.php` - Thêm sản phẩm mới
- `edit_product_catalog.php` - Sửa sản phẩm

### Others
- `dashboard.php` - Bảng điều khiển
- `homepage.php` - Trang chủ
- `history.php` - Lịch sử
- `login.php` - Đăng nhập
- `api.php` - API
- `api-settings.php` - Cài đặt API
- `api-examples.php` - Ví dụ API

## Mapping Slugs

### English Slugs
```
dashboard → dashboard.php
website-list → website_list.php
add-website → addnew_website.php
edit-website → edit_website.php
detail-website → detail_website.php
domain-list → domain_list.php
hosting-list → hosting_list.php
service-list → service_list.php
// ... và nhiều mapping khác
```

### Vietnamese Slugs
```
trang-chu → homepage.php
bang-dieu-khien → dashboard.php
danh-sach-website → website_list.php
them-website → addnew_website.php
sua-website → edit_website.php
chi-tiet-website → detail_website.php
// ... và nhiều mapping khác
```

### Short Slugs
```
website → website_list.php
domain → domain_list.php
hosting → hosting_list.php
service → service_list.php
// ... và nhiều mapping khác
```

## Ví dụ sử dụng

### Ví dụ 1: Tạo trang dashboard
1. Tạo page mới với slug "dashboard"
2. Hệ thống tự động sử dụng `dashboard.php`
3. URL: `https://yoursite.com/dashboard/`

### Ví dụ 2: Tạo trang danh sách website
1. Tạo page mới với slug "website-list" hoặc "websites"
2. Hệ thống tự động sử dụng `website_list.php`
3. URL: `https://yoursite.com/website-list/`

### Ví dụ 3: Tạo custom mapping
1. Vào Template Management trong admin
2. Thêm mapping: slug "my-custom-page" → template "custom_template.php"
3. Tạo page với slug "my-custom-page"
4. Hệ thống sử dụng `custom_template.php`

## Lợi ích

1. **Tự động hóa**: Không cần tạo page template thủ công
2. **Linh hoạt**: Hỗ trợ nhiều slug cho cùng một template
3. **Đa ngôn ngữ**: Hỗ trợ slug tiếng Việt và tiếng Anh
4. **Dễ quản lý**: Interface admin để quản lý mapping
5. **Debug friendly**: Thông tin debug tự động
6. **Extensible**: Có thể thêm custom mapping mà không cần sửa code

## Lưu ý

1. **Ưu tiên mapping**: Custom mapping > Default mapping
2. **File existence**: Template file phải tồn tại, nếu không sẽ fallback về template mặc định
3. **Performance**: Hệ thống cache mapping để tối ưu hiệu suất
4. **Security**: Sử dụng nonce và sanitization cho bảo mật
5. **Compatibility**: Tương thích với WordPress standard và theme switching

## Troubleshooting

### Template không được áp dụng
1. Kiểm tra slug của page có đúng không
2. Kiểm tra template file có tồn tại không
3. Kiểm tra debug info trong HTML source
4. Xem Template Management để verify mapping

### Page hiển thị 404
1. Kiểm tra page đã được tạo và publish chưa
2. Kiểm tra permalink settings
3. Flush rewrite rules (Settings → Permalinks → Save)

### Template bị conflict
1. Kiểm tra theme có template tùy chỉnh nào conflict không
2. Temporary disable plugin để test
3. Kiểm tra template hierarchy của WordPress