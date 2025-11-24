<?php
/* 
    Template Name: Hosting Details
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_hostings';
$current_user_id = get_current_user_id();

// Get hosting ID from URL
$hosting_id = isset($_GET['hosting_id']) ? intval($_GET['hosting_id']) : 0;

// Redirect if no hosting ID provided
if (!$hosting_id) {
    wp_redirect(home_url('/hostings/'));
    exit;
}

// Get hosting data
$hosting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $hosting_id));

// Redirect if hosting not found
if (!$hosting) {
    wp_redirect(home_url('/hostings/'));
    exit;
}

// Check if user has permission to view this hosting
if (!can_user_view_item($hosting->owner_user_id, $hosting->partner_id)) {
    wp_redirect(home_url('/danh-sach-hosting/'));
    exit;
}

// Get user data
$users_table = $wpdb->prefix . 'im_users';
$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $hosting->owner_user_id));

// Get product catalog data
$catalog_table = $wpdb->prefix . 'im_product_catalog';
$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $catalog_table WHERE id = %d", $hosting->product_catalog_id));

// Get provider data if exists
$provider = null;
if ($hosting->provider_id) {
    $provider = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d AND user_type = 'SUPPLIER'", $hosting->provider_id));
}

// Get partner data if exists
$partner = null;
if ($hosting->partner_id) {
    $partner = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $hosting->partner_id));
}

// Get website using this hosting
$websites_table = $wpdb->prefix . 'im_websites';
$websites = $wpdb->get_results($wpdb->prepare("SELECT * FROM $websites_table WHERE hosting_id = %d", $hosting_id));

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
        ii.service_type = 'Hosting' 
    AND 
        ii.service_id = %d
    ORDER BY 
        i.invoice_date DESC
";

$invoices = $wpdb->get_results($wpdb->prepare($query, $hosting_id));

get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-row">
                <!-- Add back button in the left side -->
                <a href="<?php echo home_url('/danh-sach-hosting/'); ?>" class="nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="mb-1">Chi tiết hosting <?php echo !empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id; ?></h3>
                </div>
                <?php if (is_inova_admin()): ?>
                <div>
                    <div class="d-flex flex-row justify-content-center gap-3">
                        <a href="<?php echo home_url('/sua-hosting/?hosting_id=' . $hosting_id); ?>" class="btn btn-info btn-icon-text d-flex align-items-center">
                            <i class="ph ph-pencil me-1"></i> Chỉnh sửa
                        </a>
                        <a href="<?php echo home_url('/them-moi-hoa-don/?hosting_id=' . $hosting_id); ?>" class="btn btn-dark btn-icon-text d-flex align-items-center">
                            <i class="ph ph-receipt me-1"></i> Tạo hóa đơn
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php 
                    // Calculate status information for use in owner section
                    $status_class = 'bg-secondary';
                    $status_text = 'Không xác định';
                    
                    switch ($hosting->status) {
                        case 'ACTIVE':
                            $status_class = 'bg-success';
                            $status_text = 'Đang hoạt động';
                            break;
                        case 'PENDING':
                            $status_class = 'bg-warning text-dark';
                            $status_text = 'Chờ xử lý';
                            break;
                        case 'EXPIRED':
                            $status_class = 'bg-danger';
                            $status_text = 'Hết hạn';
                            break;
                        case 'CANCELLED':
                            $status_class = 'bg-secondary';
                            $status_text = 'Đã hủy';
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
                                                    <?php if ($hosting->status === 'ACTIVE'): 
                                                    // Calculate days remaining until expiry
                                                    $now = new DateTime();
                                                    $expiry = new DateTime($hosting->expiry_date);
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
                                                <td class="bb-1"><strong><?php echo number_format($hosting->price, 0, ',', '.'); ?> VNĐ</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted">Không tìm thấy thông tin chủ sở hữu</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hosting Management Details -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title d-flex align-items-center mb-3 text-info">
                                        <i class="ph ph-sliders me-2 fa-150p"></i>
                                        Thông tin quản trị hosting
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <?php if (!empty($hosting->management_url)): ?>
                                            <tr>
                                                <th class="pe-3">URL Quản trị</th>
                                                <td>
                                                    <a href="<?php echo esc_url($hosting->management_url); ?>" target="_blank" class="text-decoration-none">
                                                        <?php echo esc_url($hosting->management_url); ?>
                                                        <i class="ph ph-arrow-square-out ms-1"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($hosting->management_username)): ?>
                                            <tr>
                                                <th>Tài khoản</th>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span><?php echo esc_html($hosting->management_username); ?></span>
                                                        <button class="btn btn-sm text-primary ms-2" onclick="navigator.clipboard.writeText('<?php echo esc_js($hosting->management_username); ?>')">
                                                            <i class="ph ph-copy"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($hosting->management_password)): ?>
                                            <tr>
                                                <th>Mật khẩu</th>
                                                <td>
                                                    <div class="input-group">
                                                        <?php $decrypted_password = im_decrypt_password($hosting->management_password); ?>
                                                        <input type="password" class="form-control" id="hosting-password" value="<?php echo esc_attr($decrypted_password); ?>" readonly>
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
                                            <?php if (!empty($hosting->ip_address)): ?>
                                            <tr>
                                                <th>Địa chỉ IP</th>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span><?php echo esc_html($hosting->ip_address); ?></span>
                                                        <button class="btn btn-sm text-primary ms-2" onclick="navigator.clipboard.writeText('<?php echo esc_js($hosting->ip_address); ?>')">
                                                            <i class="ph ph-copy"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Ngày đăng ký</th>
                                                <td><?php echo date('d/m/Y', strtotime($hosting->registration_date)); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Cập nhật lần cuối</th>
                                                <td class="bb-1"><?php echo date('d/m/Y', strtotime($hosting->updated_at)); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4 w-100">
                        <div class="col-md-12">
                            <!-- Hosting Details Card -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title d-flex align-items-center mb-4 text-success">
                                        <i class="ph ph-server me-2 fa-150p"></i>
                                        Chi tiết hosting
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
                                                        <span class="badge border-radius-9 <?php echo strtotime($hosting->expiry_date) < time() ? 'btn-inverse-danger' : 'btn-inverse-success'; ?>">
                                                            <?php echo date('d/m/Y', strtotime($hosting->expiry_date)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small mb-2">
                                                        <span class="text-muted">Chu kỳ thanh toán:</span>
                                                        <?php echo $hosting->billing_cycle_months; ?> tháng
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
                                                        <span class="text-muted">Loại hosting:</span>
                                                        <?php echo $product ? esc_html($product->name) : 'Không xác định'; ?>
                                                    </div>
                                                    <?php if ($partner): ?>
                                                    <div class="small">
                                                        <span class="text-muted">Đối tác:</span>
                                                        <a href="<?php echo home_url('/user-detail/?user_id=' . $partner->id); ?>" class="text-decoration-none text-info">
                                                            <?php echo $partner->user_code . ' - ' . $partner->name; ?>
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-warning">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle d-flex align-items-center text-warning mb-3">
                                                        <i class="ph ph-money me-2" style="font-size: 20px;"></i>
                                                        Thông tin giá
                                                    </h6>
                                                    <div class="mb-2">
                                                        <span class="fw-bold fs-5">
                                                            <?php echo number_format($hosting->price, 0, ',', '.'); ?> VNĐ
                                                        </span>
                                                    </div>
                                                    <?php if ($hosting->discount_amount > 0): ?>
                                                    <div class="small mb-1">
                                                        <span class="text-muted">Chiết khấu:</span>
                                                        <span class="text-danger"><?php echo number_format($hosting->discount_amount, 0, ',', '.'); ?> VNĐ</span>
                                                    </div>
                                                    <div class="small">
                                                        <span class="text-muted">Thực thu:</span>
                                                        <span class="fw-bold"><?php echo number_format($hosting->actual_revenue, 0, ',', '.'); ?> VNĐ</span>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="small">
                                                        <span class="text-muted">Trạng thái:</span>
                                                        <span class="badge border-radius-9 <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    
                    <!-- Website Section -->
                    <div class="card w-100 mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title d-flex align-items-center mb-0">
                                    <i class="ph ph-globe me-2 text-success" style="font-size: 24px;"></i>
                                    Websites sử dụng hosting này
                                </h5>
                                <a href="<?php echo home_url('/addnew-website/?hosting_id=' . $hosting_id); ?>" class="d-flex align-items-center btn btn-sm btn-success">
                                    <i class="ph ph-plus-circle me-1"></i> Thêm website mới
                                </a>
                            </div>
                            
                            <?php if (!empty($websites)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tên website</th>
                                            <th>Tên miền</th>
                                            <th>URL</th>
                                            <th>Admin URL</th>
                                            <th>CMS</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($websites as $website): ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo home_url('/detail-website/?website_id=' . $website->id); ?>" class="text-decoration-none">
                                                    <?php echo $website->name; ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php 
                                                if (!empty($website->domain_id)) {
                                                    $domain_table = $wpdb->prefix . 'im_domains';
                                                    $domain = $wpdb->get_row($wpdb->prepare("SELECT * FROM $domain_table WHERE id = %d", $website->domain_id));
                                                    if ($domain) {
                                                        echo '<a href="' . home_url('/detail-domain/?domain_id=' . $domain->id) . '" class="text-decoration-none">' . $domain->domain_name . '</a>';
                                                    } else {
                                                        echo '<span class="text-muted">--</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted">--</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($website->url)): ?>
                                                <a href="<?php echo esc_url($website->url); ?>" target="_blank" class="nav-link d-inline-flex align-items-center">
                                                    <span class="text-truncate d-inline-block" style="max-width: 150px;"><?php echo parse_url($website->url, PHP_URL_HOST); ?></span>
                                                    <i class="ph ph-arrow-square-out ms-1"></i>
                                                </a>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($website->admin_url)): ?>
                                                <a href="<?php echo esc_url($website->admin_url); ?>" target="_blank" class="nav-link d-inline-flex align-items-center">
                                                    <span class="text-truncate d-inline-block" style="max-width: 150px;"><?php echo parse_url($website->admin_url, PHP_URL_HOST); ?></span>
                                                    <i class="ph ph-arrow-square-out ms-1"></i>
                                                </a>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo !empty($website->cms_info) ? $website->cms_info : '<span class="text-muted">--</span>'; ?></td>
                                            <td>
                                                <a href="<?php echo home_url('/edit-website/?website_id=' . $website->id); ?>" class="btn btn-sm btn-secondary">
                                                    <i class="ph ph-pencil me-1"></i> Chỉnh sửa
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 text-center">
                                <span class="badge bg-info">Có <?php echo count($websites); ?> website trên hosting này</span>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-light text-center py-4">
                                <i class="ph ph-globe-slash mb-2" style="font-size: 32px;"></i>
                                <p class="mb-2">Chưa có website nào được liên kết với hosting này</p>
                                <a href="<?php echo home_url('/addnew-website/?hosting_id=' . $hosting_id); ?>" class="btn btn-success">
                                    <i class="ph ph-plus-circle me-1"></i> Thêm website mới
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Notes Section -->
                    <?php if (!empty($hosting->notes)): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title d-flex align-items-center mb-3">
                                <i class="ph ph-notepad me-2 text-secondary" style="font-size: 24px;"></i>
                                Ghi chú
                            </h5>
                            <div class="card bg-light p-3">
                                <pre class="mb-0"><?php echo esc_html($hosting->notes); ?></pre>
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
                                <a href="<?php echo home_url('/them-moi-hoa-don/?hosting_id=' . $hosting_id); ?>" class="d-flex align-items-center btn btn-sm btn-success">
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
                                                <i class="ph ph-server me-1"></i>
                                                Hosting
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
                                <p class="mb-2">Chưa có hóa đơn nào cho hosting này</p>
                                <a href="<?php echo home_url('/them-moi-hoa-don/?hosting_id=' . $hosting_id); ?>" class="btn btn-success">
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