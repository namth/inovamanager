# Hướng dẫn sử dụng Template Auto Assignment

## 🚀 Cách truy cập Template Management

### Phương pháp 1: Thông qua WordPress Admin
1. **Đăng nhập WordPress Admin**: `http://yoursite.com/wp-admin/`
2. **Vào menu Appearance**: Sidebar trái → Appearance
3. **Chọn Template Management**: Appearance → Template Management

### Phương pháp 2: Truy cập trực tiếp
```
http://yoursite.com/wp-admin/themes.php?page=template-management
```

## 🎯 Demo & Test

### Tạo trang Demo để test
1. Vào **Pages → Add New**
2. Title: "Demo Template"
3. Slug: "demo" hoặc "test-template"  
4. **Publish** → Truy cập để xem kết quả

### Test System
Truy cập: `http://yoursite.com/wp-content/themes/bookorder/test-template-system.php`

## 🔧 Tính năng mới được thêm

### 1. **Link trực tiếp trong Template Management**
- ✅ **View Page**: Link đến trang đã tồn tại
- ➕ **Create Page**: Link tạo trang mới với slug được điền sẵn
- 📊 **Statistics**: Thống kê tổng quan

### 2. **Auto-fill khi tạo page**
- Slug được điền tự động khi click "Create Page"
- Title được gợi ý dựa trên slug
- Thông báo template sẽ được áp dụng

### 3. **Admin Bar Info**
- Hiển thị template đang sử dụng ở admin bar
- Click để đi đến Template Management

### 4. **Enhanced UI**
- Màu sắc phân biệt trạng thái
- Grid layout responsive
- Quick action buttons

## 🔧 Cách kiểm tra hệ thống hoạt động

### Bước 1: Kiểm tra functions
Truy cập file test: `http://yoursite.com/wp-content/themes/bookorder/test-template-system.php`

### Bước 2: Tạo page để test
1. Vào **Pages → Add New**
2. Tạo page với title: "Dashboard Test"
3. Slug sẽ tự động là: "dashboard-test" hoặc thay đổi thành "dashboard"
4. **Publish** page
5. Truy cập page để kiểm tra

### Bước 3: Kiểm tra debug info
1. Truy cập page vừa tạo
2. **View Page Source** (Ctrl+U)
3. Tìm dòng: `<!-- DEBUG: Page Slug: dashboard | Suggested Template: dashboard.php -->`

## 📝 Các template slug có sẵn

### Website Management
- `website-list` hoặc `websites` → `website_list.php`
- `add-website` → `addnew_website.php`
- `edit-website` → `edit_website.php`
- `detail-website` → `detail_website.php`

### Domain Management  
- `domain-list` hoặc `domains` → `domain_list.php`
- `add-domain` → `addnew_domain.php`
- `edit-domain` → `edit_domain.php`
- `detail-domain` → `detail_domain.php`

### Hosting Management
- `hosting-list` hoặc `hostings` → `hosting_list.php`
- `add-hosting` → `addnew_hosting.php`
- `edit-hosting` → `edit_hosting.php`
- `detail-hosting` → `detail_hosting.php`

### Dashboard & Admin
- `dashboard` → `dashboard.php`
- `homepage` hoặc `home` → `homepage.php`
- `api` → `api.php`
- `api-settings` → `api-settings.php`

## 🎯 Ví dụ thực tế

### Ví dụ 1: Tạo trang Dashboard
```
1. Pages → Add New
2. Title: "Bảng điều khiển"
3. Slug: "dashboard" 
4. Publish
5. Truy cập: http://yoursite.com/dashboard/
→ Sẽ sử dụng template dashboard.php
```

### Ví dụ 2: Tạo trang danh sách website
```
1. Pages → Add New
2. Title: "Danh sách Website"
3. Slug: "website-list"
4. Publish  
5. Truy cập: http://yoursite.com/website-list/
→ Sẽ sử dụng template website_list.php
```

### Ví dụ 3: Tạo trang tiếng Việt
```
1. Pages → Add New
2. Title: "Danh sách tên miền"
3. Slug: "danh-sach-domain"
4. Publish
5. Truy cập: http://yoursite.com/danh-sach-domain/
→ Sẽ sử dụng template domain_list.php
```

## 🛠️ Troubleshooting

### Vấn đề 1: Template Management không xuất hiện
**Nguyên nhân**: Functions chưa được load hoặc có lỗi syntax
**Giải pháp**:
1. Kiểm tra file `functions.php` có lỗi syntax không
2. Truy cập `test-template-system.php` để kiểm tra
3. Check error log: `/wp-content/debug.log`

### Vấn đề 2: Template không được áp dụng
**Nguyên nhân**: 
- Slug không khớp với mapping
- Template file không tồn tại
- Cache
**Giải pháp**:
1. Kiểm tra slug chính xác
2. Kiểm tra file template có tồn tại không
3. Clear cache nếu có

### Vấn đề 3: Debug info không hiển thị
**Nguyên nhân**: Chưa đăng nhập với quyền admin
**Giải pháp**: Đăng nhập với tài khoản admin

## 📋 Checklist kiểm tra

- [ ] ✅ Functions được load thành công
- [ ] ✅ Hooks được đăng ký 
- [ ] ✅ Template Management menu xuất hiện
- [ ] ✅ Có thể tạo page và xem debug info
- [ ] ✅ Template được áp dụng đúng
- [ ] ✅ Có thể thêm custom mapping

## 🔗 Links hữu ích

- **Test System**: `/wp-content/themes/bookorder/test-template-system.php`
- **Template Management**: `/wp-admin/themes.php?page=template-management`
- **Check Templates**: `/wp-content/themes/bookorder/check-templates.php`
- **WordPress Pages**: `/wp-admin/edit.php?post_type=page`

## 📞 Liên hệ hỗ trợ

Nếu vẫn gặp vấn đề, vui lòng:
1. Chạy file test và gửi kết quả
2. Kiểm tra error log
3. Cung cấp thông tin về lỗi cụ thể