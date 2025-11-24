<?php
/* 
    Template Name: Domain Details
*/

global $wpdb;
$domains_table = $wpdb->prefix . 'im_domains';
$current_user_id = get_current_user_id();

// Get domain ID from URL
$domain_id = isset($_GET['domain_id']) ? intval($_GET['domain_id']) : 0;

// Redirect if no domain ID provided
if (!$domain_id) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

// Get domain data
$domain = $wpdb->get_row($wpdb->prepare("SELECT * FROM $domains_table WHERE id = %d", $domain_id));

// Redirect if domain not found
if (!$domain) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

// Check if user has permission to view this domain
if (!can_user_view_item($domain->owner_user_id, null)) {
    wp_redirect(home_url('/danh-sach-ten-mien/'));
    exit;
}

// Get user data
$users_table = $wpdb->prefix . 'im_users';
$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $domain->owner_user_id));

// Get service provider data
$users_table = $wpdb->prefix . 'im_users';
$provider = null;
if ($domain->provider_id) {
    $provider = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d AND user_type = 'SUPPLIER'", $domain->provider_id));
}

// Get product catalog data
$catalog_table = $wpdb->prefix . 'im_product_catalog';
$product = null;
if ($domain->product_catalog_id) {
    $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $catalog_table WHERE id = %d", $domain->product_catalog_id));
}

// Get websites using this domain
$websites_table = $wpdb->prefix . 'im_websites';
$websites = $wpdb->get_results($wpdb->prepare("SELECT * FROM $websites_table WHERE domain_id = %d", $domain_id));

// Get related invoices
$invoices_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';

$query = "
    SELECT 
        i.*, 
        ii.start_date, 
        ii.end_date, 
        ii.unit_price
    FROM 
        $invoices_table i
    JOIN 
        $invoice_items_table ii ON i.id = ii.invoice_id
    WHERE 
        ii.service_type = 'Domain' 
    AND 
        ii.service_id = %d
    ORDER BY 
        i.invoice_date DESC
";

$invoices = $wpdb->get_results($wpdb->prepare($query, $domain_id));

get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-row">
                <!-- Add back button in the left side -->
                <a href="<?php echo home_url('/list-website/'); ?>" class="nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="mb-1">Chi tiết tên miền <?php echo esc_html($domain->domain_name); ?></h3>
                </div>
                <div>
                    <div class="d-flex flex-row justify-content-center gap-3">
                        <?php if (is_inova_admin()): ?>
                        <a href="<?php echo home_url('/edit-domain/?domain_id=' . $domain_id); ?>" class="btn btn-info btn-icon-text d-flex align-items-center">
                            <i class="ph ph-pencil me-1"></i> Chỉnh sửa
                        </a>
                        <a href="<?php echo home_url('/them-moi-hoa-don/?domain_id=' . $domain_id); ?>" class="btn btn-dark btn-icon-text d-flex align-items-center">
                            <i class="ph ph-receipt me-1"></i> Tạo hóa đơn
                        </a>
                        <?php endif; ?>

                        <?php if (!is_inova_admin() && $domain->managed_by_inova == 0): ?>
                        <!-- Customers can manually renew domains NOT managed by INOVA -->
                        <button type="button" class="btn btn-success btn-icon-text d-flex align-items-center renew-domain-manual-btn"
                            data-domain-id="<?php echo $domain->id; ?>"
                            data-domain-name="<?php echo esc_attr($domain->domain_name); ?>"
                            data-expiry-date="<?php echo esc_attr($domain->expiry_date); ?>">
                            <i class="ph ph-clock-clockwise me-1"></i> Gia hạn thêm 1 năm
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php 
                    // Calculate status information for use in owner section
                    $status_class = 'bg-secondary';
                    $status_text = 'Không xác định';
                    
                    switch ($domain->status) {
                        case 'ACTIVE':
                            $status_class = 'bg-success';
                            $status_text = 'Đang hoạt động';
                            break;
                        case 'PENDING_RENEWAL':
                            $status_class = 'bg-warning text-dark';
                            $status_text = 'Chờ gia hạn';
                            break;
                        case 'EXPIRED':
                            $status_class = 'bg-danger';
                            $status_text = 'Hết hạn';
                            break;
                        case 'CANCELLED':
                            $status_class = 'bg-secondary';
                            $status_text = 'Đã hủy';
                            break;
                        case 'TRANSFERRING':
                            $status_class = 'bg-info';
                            $status_text = 'Đang chuyển';
                            break;
                    }
                    ?>
                    
                    <div class="row mb-4 w-100">
                        <!-- Owner Information -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h3 class="card-title d-flex align-items-center mb-3 text-primary">
                                        <i class="ph ph-user-circle me-2 fa-150p"></i>
                                        Thông tin chủ sở hữu
                                    </h3>
                                    
                                    <?php if (isset($user)): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <?php if (current_user_can('administrator')): ?>
                                            <tr>
                                                <th class="pe-3">Mã khách hàng</th>
                                                <td><?php echo $user->user_code; ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Tên</th>
                                                <td>
                                                    <a href="<?php echo home_url('/user-detail/?user_id=' . $user->id); ?>" class="text-decoration-none">
                                                        <?php echo $user->name; ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td><?php echo $user->email; ?></td>
                                            </tr>
                                            <?php if (!empty($user->company_name)): ?>
                                            <tr>
                                                <th>Công ty</th>
                                                <td><?php echo $user->company_name; ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($user->phone_number)): ?>
                                            <tr>
                                                <th>Số điện thoại</th>
                                                <td><?php echo $user->phone_number; ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Trạng thái</th>
                                                <td>
                                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                    <?php if ($domain->status === 'ACTIVE'): 
                                                    // Calculate days remaining until expiry
                                                    $now = new DateTime();
                                                    $expiry = new DateTime($domain->expiry_date);
                                                    $days_remaining = $now->diff($expiry)->days;
                                                    $is_expired = $now > $expiry;
                                                    
                                                    if ($is_expired): ?>
                                                        <span class="badge bg-danger ms-1">Quá hạn <?php echo $days_remaining; ?> ngày</span>
                                                    <?php elseif ($days_remaining <= 30): ?>
                                                        <span class="badge bg-warning text-dark ms-1">Còn <?php echo $days_remaining; ?> ngày</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success ms-1">Còn <?php echo $days_remaining; ?> ngày</span>
                                                    <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Giá dịch vụ</th>
                                                <td class="bb-1"><strong><?php echo number_format($domain->price, 0, ',', '.'); ?> VNĐ</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted">Không tìm thấy thông tin chủ sở hữu</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Domain Management Details -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title d-flex align-items-center mb-3 text-info">
                                        <i class="ph ph-globe me-2 fa-150p"></i>
                                        Thông tin quản trị tên miền
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <?php if (!empty($domain->management_url)): ?>
                                            <tr>
                                                <th class="pe-3">URL Quản trị</th>
                                                <td>
                                                    <a href="<?php echo esc_url($domain->management_url); ?>" target="_blank" class="text-decoration-none">
                                                        <?php echo esc_url($domain->management_url); ?>
                                                        <i class="ph ph-arrow-square-out ms-1"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($domain->management_username)): ?>
                                            <tr>
                                                <th>Tài khoản</th>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span><?php echo esc_html($domain->management_username); ?></span>
                                                        <button class="btn btn-sm text-primary ms-2" onclick="navigator.clipboard.writeText('<?php echo esc_js($domain->management_username); ?>')">
                                                            <i class="ph ph-copy"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($domain->management_password)): ?>
                                            <tr>
                                                <th>Mật khẩu</th>
                                                <td>
                                                    <div class="input-group">
                                                        <?php $decrypted_password = im_decrypt_password($domain->management_password); ?>
                                                        <input type="password" class="form-control" id="domain-password" value="<?php echo esc_attr($decrypted_password); ?>" readonly>
                                                        <button class="btn btn-inverse-secondary border-secondary toggle-password" type="button">
                                                            <i class="ph ph-eye"></i>
                                                        </button>
                                                        <button class="btn btn-secondary" type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($decrypted_password); ?>')">
                                                            <i class="ph ph-copy"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Ngày đăng ký</th>
                                                <td><?php echo date('d/m/Y', strtotime($domain->registration_date)); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Cập nhật lần cuối</th>
                                                <td class="bb-1"><?php echo date('d/m/Y', strtotime($domain->updated_at)); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4 w-100">
                        <div class="col-md-12">
                            <!-- Domain Details Card -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title d-flex align-items-center mb-4 text-success">
                                        <i class="ph ph-info me-2 fa-150p"></i>
                                        Chi tiết tên miền
                                    </h5>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-primary">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle d-flex align-items-center text-primary mb-3">
                                                        <i class="ph ph-calendar me-2" style="font-size: 20px;"></i>
                                                        Thông tin thời hạn
                                                    </h6>
                                                    <div class="mb-2">
                                                        <span class="text-muted">Ngày hết hạn:</span>
                                                        <span class="badge border-radius-9 <?php echo strtotime($domain->expiry_date) < time() ? 'btn-inverse-danger' : 'btn-inverse-success'; ?>">
                                                            <?php echo date('d/m/Y', strtotime($domain->expiry_date)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small mb-2">
                                                        <span class="text-muted">Thời hạn đăng ký:</span>
                                                        <?php echo $domain->registration_period_years; ?> năm
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-info">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle d-flex align-items-center text-info mb-3">
                                                        <i class="ph ph-buildings me-2" style="font-size: 20px;"></i>
                                                        Nhà cung cấp
                                                    </h6>
                                                    <div class="mb-2">
                                                        <span class="fw-bold fs-5">
                                                            <?php echo $provider ? esc_html($provider->name) : 'Không xác định'; ?>
                                                        </span>
                                                    </div>
                                                    <div class="small">
                                                        <span class="text-muted">INOVA quản lý:</span>
                                                        <?php if ($domain->managed_by_inova): ?>
                                                            <span class="badge bg-success">Có</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Không</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-warning">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle d-flex align-items-center text-warning mb-3">
                                                        <i class="ph ph-globe-hemisphere-west me-2" style="font-size: 20px;"></i>
                                                        Quản lý DNS
                                                    </h6>
                                                    <div class="mb-2">
                                                        <span class="fw-bold fs-5">
                                                            <?php 
                                                            // print_r($domain);
                                                            $dns_management = 'Không xác định';
                                                            switch ($domain->dns_management) {
                                                                case 'PROVIDER':
                                                                    $dns_management = 'Nhà cung cấp';
                                                                    break;
                                                                case 'CLOUDFLARE':
                                                                    $dns_management = 'Cloudflare';
                                                                    break;
                                                                case 'OTHER':
                                                                    $dns_management = 'Khác';
                                                                    break;
                                                            }
                                                            echo $dns_management;
                                                            ?>
                                                        </span>
                                                    </div>
                                                    <div class="small">
                                                        <span class="text-muted">Trạng thái:</span>
                                                        <span class="badge border-radius-9 <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                    
                    
                    <!-- Notes Section -->
                    <?php if (!empty($domain->notes)): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title d-flex align-items-center mb-3">
                                <i class="ph ph-notepad me-2 text-secondary" style="font-size: 24px;"></i>
                                Ghi chú
                            </h5>
                            <div class="card bg-light p-3">
                                <pre class="mb-0"><?php echo esc_html($domain->notes); ?></pre>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Invoices Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title d-flex align-items-center mb-0">
                                    <i class="ph ph-receipt me-2 text-success" style="font-size: 24px;"></i>
                                    Lịch sử hóa đơn
                                </h5>
                                <a href="<?php echo home_url('/them-moi-hoa-don/?domain_id=' . $domain_id); ?>" class="d-flex align-items-center btn btn-sm btn-success">
                                    <i class="ph ph-plus-circle me-1"></i> Tạo hóa đơn
                                </a>
                            </div>
                            
                            <?php if (!empty($invoices)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Mã hóa đơn</th>
                                            <th>Ngày lập</th>
                                            <th>Dịch vụ</th>
                                            <th>Kỳ thanh toán</th>
                                            <th>Số tiền</th>
                                            <th>Trạng thái</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo home_url('/invoice-detail/?invoice_id=' . $invoice->id); ?>" class="text-decoration-none">
                                                    <?php echo $invoice->invoice_code; ?>
                                                </a>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($invoice->invoice_date)); ?></td>
                                            <td>
                                                <i class="ph ph-globe me-1"></i>
                                                Tên miền
                                            </td>
                                            <td>
                                                <?php
                                                if (!empty($invoice->start_date) && !empty($invoice->end_date)) {
                                                    echo date('d/m/Y', strtotime($invoice->start_date)) . ' - ' . 
                                                         date('d/m/Y', strtotime($invoice->end_date));
                                                } else {
                                                    echo '<span class="text-muted">--</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo number_format($invoice->unit_price, 0, ',', '.'); ?> VNĐ</td>
                                            <td>
                                                <?php 
                                                $invoice_status_class = 'bg-secondary';
                                                $invoice_status_text = 'Không xác định';
                                                $invoice_status = strtoupper($invoice->status);
                                                
                                                switch ($invoice_status) {
                                                    case 'PAID':
                                                        $invoice_status_class = 'bg-success';
                                                        $invoice_status_text = 'Đã thanh toán';
                                                        break;
                                                    case 'PENDING':
                                                    case 'UNPAID':
                                                        $invoice_status_class = 'btn-inverse-warning';
                                                        $invoice_status_text = 'Chưa thanh toán';
                                                        break;
                                                    case 'CANCELLED':
                                                        $invoice_status_class = 'bg-secondary';
                                                        $invoice_status_text = 'Đã hủy';
                                                        break;
                                                    case 'DRAFT':
                                                        $invoice_status_class = 'btn-inverse-info';
                                                        $invoice_status_text = 'Bản nháp';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge border-radius-9 <?php echo $invoice_status_class; ?>">
                                                    <?php echo $invoice_status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo home_url('/invoice-detail/?invoice_id=' . $invoice->id); ?>" class="btn btn-sm btn-secondary">
                                                    Xem chi tiết
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-light text-center py-4">
                                <i class="ph ph-receipt mb-2" style="font-size: 32px;"></i>
                                <p class="mb-2">Chưa có hóa đơn nào cho tên miền này</p>
                                <a href="<?php echo home_url('/them-moi-hoa-don/?domain_id=' . $domain_id); ?>" class="btn btn-success">
                                    <i class="ph ph-plus-circle me-1"></i> Tạo hóa đơn mới
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password toggle functionality is now handled by custom.js
</script>

<?php
get_footer();
?>