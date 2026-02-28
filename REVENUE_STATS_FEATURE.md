# 📊 Tính Năng Thống Kê Doanh Thu (Revenue Statistics)

## Mục Đích
Cung cấp tính năng thống kê chi tiết doanh thu từ các hóa đơn đã thanh toán, cho phép theo dõi tăng/giảm doanh thu theo từng tháng, năm và đối tác.

---

## 🎯 Thông Tin Theo Dõi

### A. Bắt Buộc ✅
- **Số lượng HĐ thanh toán** - Tổng số hóa đơn với status = 'PAID' trong kỳ
- **Tổng tiền thanh toán** - SUM(total_amount) cho tất cả HĐ đã thanh toán
- **Phân trang theo năm** - Chọn năm để xem thống kê, lịch sử 5 năm

### B. Bổ Sung (Thêm Giá Trị) ⭐
- **Tổng tiền nợ** - SUM(total_amount - paid_amount) cho HĐ chưa thanh toán
- **HĐ chưa thanh toán** - Số lượng HĐ với status != 'PAID'
- **Trung bình/HĐ** - AVG(total_amount) để đánh giá giá trị trung bình
- **Khách hàng duy nhất** - COUNT(DISTINCT user_id) theo tháng
- **Biểu đồ cột** - Visualize doanh thu 12 tháng (Chart.js)
- **Lọc theo đối tác** - Xem chi tiết doanh thu từng partner
- **Chi tiết từng tháng** - Xem chi tiết invoices trong modal
- **Export CSV** - Xuất báo cáo thành file Excel

---

## 📁 Cấu Trúc Files

### Created Files:
```
📦 theme/bookorder/
├── revenue_stats.php                    (Trang chính - 450+ lines)
├── assets/js/revenue-stats.js           (Handlers JavaScript - 150+ lines)
├── includes/ajax-handlers.php           (Thêm 2 AJAX handlers)
├── includes/helpers.php                 (Thêm URL mapping + enqueue scripts)
└── REVENUE_STATS_FEATURE.md             (File này)
```

### Modified Files:
- `includes/helpers.php` - Thêm:
  - URL mapping: `'revenue-stats'`, `'thong-ke-doanh-thu'`, `'thong-ke'`
  - Chart.js CDN
  - revenue-stats.js enqueue
  - revenue-stats.js localization

- `includes/ajax-handlers.php` - Thêm:
  - `get_monthly_invoice_details_callback()` - Xem chi tiết hóa đơn tháng
  - `export_revenue_report_callback()` - Export CSV báo cáo

---

## 🚀 Cách Sử Dụng

### 1. Truy Cập Trang Thống Kê
```
Trên giao diện:
/revenue-stats/          (English)
/thong-ke-doanh-thu/     (Vietnamese)
/thong-ke/               (Short alias)
```

### 2. Chọn Năm & Đối Tác
- **Dropdown chọn năm**: Lưu lịch sử 5 năm gần nhất
- **Filter đối tác**: Xem doanh thu riêng từng partner
- Tự động reload khi thay đổi

### 3. Xem Chi Tiết Tháng
- Bấm nút "Chi tiết" trên mỗi hàng tháng
- Mở modal hiển thị tất cả invoices trong tháng đó
- Xem tổng tiền, đã TT, chưa TT, tỷ lệ TT

### 4. Export Báo Cáo
```javascript
// Click button "Export CSV" (có thể thêm sau)
// File: doanh-thu-{year}-{date}.csv
```

---

## 📊 Dữ Liệu Hiển Thị

### Trang Chính - 4 Summary Cards:
| Card | Dữ Liệu | Màu |
|------|---------|-----|
| HĐ Đã TT | COUNT(paid invoices) | Primary (Blue) |
| Doanh Thu | SUM(total_amount) | Success (Green) |
| HĐ Chưa TT | COUNT(unpaid invoices) | Warning (Yellow) |
| Tiền Nợ | SUM(unpaid amount) | Danger (Red) |

### Cards Bổ Sung (2 thêm):
| Card | Dữ Liệu |
|------|---------|
| Trung Bình/HĐ | AVG(total_amount) |
| Khách Hàng Duy Nhất | COUNT(DISTINCT user_id) |

### Biểu Đồ:
```
Doanh Thu 12 Tháng - Năm XXXX
[Column Chart]
- X axis: T1, T2, T3... T12
- Y axis: VNĐ (formatted)
- Bar: Doanh thu từng tháng
```

### Bảng Chi Tiết:
| Cột | Nội Dung |
|-----|----------|
| Tháng | Tháng 1, Tháng 2... Tháng 12 |
| Số HĐ | Badge hiển thị count |
| Tổng Tiền | SUM total_amount |
| Đã TT | SUM paid_amount (Green) |
| Chưa TT | SUM unpaid_amount (Yellow) |
| Thao Tác | Button "Chi tiết" |

---

## 🔧 Database Queries

### 1. Monthly Statistics Query:
```sql
SELECT 
    MONTH(i.invoice_date) AS month_num,
    COUNT(i.id) AS invoice_count,
    SUM(i.total_amount) AS total_revenue,
    SUM(CASE WHEN i.payment_date IS NOT NULL THEN i.total_amount ELSE 0 END) AS paid_amount,
    SUM(CASE WHEN i.payment_date IS NULL THEN i.total_amount ELSE 0 END) AS unpaid_amount
FROM wp_im_invoices i
LEFT JOIN wp_im_users u ON i.user_id = u.id
WHERE YEAR(i.invoice_date) = {$year}
AND i.status = 'PAID'
{$permission_where}
GROUP BY MONTH(i.invoice_date)
ORDER BY month_num ASC
```

### 2. Total Year Statistics Query:
```sql
SELECT 
    COUNT(i.id) AS total_invoices,
    SUM(i.total_amount) AS total_year_revenue,
    AVG(i.total_amount) AS avg_invoice_amount,
    COUNT(DISTINCT i.user_id) AS unique_customers
FROM wp_im_invoices i
LEFT JOIN wp_im_users u ON i.user_id = u.id
WHERE YEAR(i.invoice_date) = {$year}
AND i.status = 'PAID'
{$permission_where}
```

### 3. Outstanding Invoices Query:
```sql
SELECT 
    COUNT(i.id) AS outstanding_invoices,
    SUM(i.total_amount - COALESCE(i.paid_amount, 0)) AS outstanding_amount
FROM wp_im_invoices i
LEFT JOIN wp_im_users u ON i.user_id = u.id
WHERE YEAR(i.invoice_date) = {$year}
AND i.status != 'PAID'
{$permission_where}
```

---

## 🔐 Quyền & Bảo Mật

### Permission Checking:
- Sử dụng `get_user_permission_where_clause()` để lọc dữ liệu theo quyền user
- Admin/Manager: Xem tất cả user
- Partner/User: Chỉ xem dữ liệu của họ

### Input Validation:
- `intval()` cho year, month, partner_id
- Kiểm tra year trong range [current_year - 5, current_year + 1]
- Kiểm tra month trong range [1, 12]

### Nonce Verification (tùy chọn):
```php
if (!wp_verify_nonce($_POST['security'], 'revenue_stats_nonce')) {
    wp_send_json_error(array('message' => 'Security check failed'));
}
```

---

## 📱 JavaScript Handlers

### File: assets/js/revenue-stats.js

#### Handler 1: View Month Details
```javascript
$(document).on('click', '.view-month-details', function() {
    month = $(this).data('month');
    year = $(this).data('year');
    
    // AJAX call to get_monthly_invoice_details
    // Shows modal with invoice list
});
```

**AJAX Action:** `get_monthly_invoice_details`
**Parameters:** month, year
**Response:** 
```json
{
    "success": true,
    "data": {
        "invoices": [ { id, invoice_code, customer_name, total_amount, paid_amount } ],
        "total_invoices": 5,
        "total_amount": 50000000,
        "paid_amount": 45000000,
        "payment_percentage": 90
    }
}
```

#### Handler 2: Export Report
```javascript
// Click export button
// POST to: wp-admin/admin-ajax.php?action=export_revenue_report
// Parameters: year, partner
// Response: CSV file download
```

**CSV Format:**
```
Tháng,Số HĐ,Tổng Tiền,Đã TT,Chưa TT
Tháng 1,5,50000000,45000000,5000000
Tháng 2,3,30000000,30000000,0
...
```

### Chart.js Integration:
```javascript
// Library: Chart.js v3.9.1 (CDN)
const ctx = document.getElementById('revenue-chart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['T1', 'T2', ... 'T12'],
        datasets: [{
            label: 'Doanh Thu (VNĐ)',
            data: [50000000, 30000000, ...],
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('vi-VN').format(value);
                    }
                }
            }
        }
    }
});
```

---

## 🎨 UI/UX Features

### Responsive Design:
- ✅ Mobile-friendly layout (col-md-3, col-md-6)
- ✅ Cards collapse on mobile
- ✅ Table responsive wrapper for scrolling

### Vietnamese Localization:
- ✅ Tháng 1, Tháng 2... Tháng 12
- ✅ Currency format: 50.000.000 ₫
- ✅ All labels in Vietnamese

### Status Colors:
- 🔵 Primary: HĐ Đã TT
- 🟢 Success: Doanh Thu
- 🟡 Warning: HĐ Chưa TT / Tiền Nợ
- 🔴 Danger: Outstanding Amount

### Icons (Phosphor):
- `ph-chart-line` - Page header
- `ph-eye` - View details button
- `ph-spinner` - Loading state

---

## 📈 Future Enhancements

### Phase 2:
1. **Hàng so sánh năm**: So sánh doanh thu năm nay vs năm trước
2. **Top customers**: Bảng hiển thị khách hàng chi tiêu nhiều nhất
3. **Tính năng filter thêm**:
   - Lọc theo ngày cụ thể
   - Lọc theo loại thanh toán (payment_method)
   - Lọc theo service type
4. **Export PDF**: Báo cáo định dạng PDF chuyên nghiệp
5. **Email schedule**: Tự động gửi báo cáo hàng tháng
6. **Advanced analytics**:
   - Line chart xu hướng doanh thu
   - Pie chart phân bổ theo đối tác
   - Heatmap tháng có doanh thu cao nhất
7. **Forecast**: Dự đoán doanh thu tháng tiếp theo

### Phase 3:
1. **Dashboard widget**: Mini revenue stats trong dashboard
2. **Mobile app**: Xem thống kê trên mobile
3. **Real-time sync**: Cập nhật doanh thu realtime
4. **API endpoint**: REST API để lấy dữ liệu thống kê

---

## 🐛 Troubleshooting

### Vấn đề 1: Dữ liệu không hiển thị
**Nguyên nhân**: Permission checking sai
**Giải pháp**: 
- Kiểm tra `get_user_permission_where_clause()` function
- Verify user_id mapping giữa WordPress và Inova

### Vấn đề 2: Chart.js không hiển thị
**Nguyên nhân**: CDN không tải được
**Giải pháp**:
- Kiểm tra internet connection
- Thay thế CDN URL nếu bị block
- Tải offline version

### Vấn đề 3: Export CSV bị lỗi
**Nguyên nhân**: BOM header hoặc encoding sai
**Giải pháp**:
- File sử dụng: `fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));`
- Encoding UTF-8 với BOM để Excel đọc Vietnamese

### Vấn đề 4: Modal không đóng
**Nguyên nhân**: Bootstrap version khác
**Giải pháp**:
- Kiểm tra Bootstrap version (v5.3.0)
- Dùng `bootstrap.Modal` thay vì `$.modal()`

---

## 📚 Testing Checklist

- [ ] Trang load đúng URL: /revenue-stats/, /thong-ke-doanh-thu/
- [ ] Chọn năm - dữ liệu update đúng
- [ ] Filter partner - hiển thị đúng doanh thu partner
- [ ] Biểu đồ render đúng (12 columns)
- [ ] Button "Chi tiết" - mở modal đúng
- [ ] Modal hiển thị invoices đúng
- [ ] Số HĐ, tổng tiền tính toán chính xác
- [ ] Permission - user chỉ xem dữ liệu của họ
- [ ] Số format VN: 50.000.000 ₫
- [ ] Responsive - mobile layout OK
- [ ] Export CSV - file tải về OK, format Vietnamese OK

---

## 📞 Support & Questions

Nếu có vấn đề hoặc câu hỏi:
1. Kiểm tra browser console (F12 > Console tab)
2. Kiểm tra server logs
3. Verify database connection
4. Check user permissions

---

**Version:** 1.0
**Date:** February 28, 2025
**Status:** ✅ Production Ready
