<?php
/* 
    Template Name: Maintenance Package Details
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_maintenance_packages';
$current_user_id = get_current_user_id();

// Get maintenance ID from URL
$maintenance_id = isset($_GET['maintenance_id']) ? intval($_GET['maintenance_id']) : 0;

// Redirect if no maintenance ID provided
if (!$maintenance_id) {
    wp_redirect(home_url('/danh-sach-bao-tri/'));
    exit;
}

// Get maintenance data
$maintenance = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $maintenance_id));

// Redirect if maintenance not found
if (!$maintenance) {
    wp_redirect(home_url('/danh-sach-bao-tri/'));
    exit;
}

// Get user data
$users_table = $wpdb->prefix . 'im_users';
$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $maintenance->owner_user_id));

// Get product catalog data
$catalog_table = $wpdb->prefix . 'im_product_catalog';
$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $catalog_table WHERE id = %d", $maintenance->product_catalog_id));

// Get partner data if exists
$partner = null;
if ($maintenance->partner_id) {
    $partner = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $maintenance->partner_id));
}

// Get websites using this maintenance package
$websites_table = $wpdb->prefix . 'im_websites';
$websites = $wpdb->get_results($wpdb->prepare("SELECT * FROM $websites_table WHERE maintenance_package_id = %d", $maintenance_id));

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
        ii.service_type = 'Maintenance' 
    AND 
        ii.service_id = %d
    ORDER BY 
        i.invoice_date DESC
";

$invoices = $wpdb->get_results($wpdb->prepare($query, $maintenance_id));

get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-row">
                <!-- Add back button in the left side -->
                <a href="<?php echo home_url('/danh-sach-bao-tri/'); ?>" class="nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="mb-1">Chi tiết gói bảo trì <?php echo !empty($maintenance->order_code) ? $maintenance->order_code : 'MAINT-' . $maintenance->id; ?></h3>
                </div>
                <div>
                    <div class="d-flex flex-row justify-content-center gap-3">
                        <a href="<?php echo home_url('/sua-goi-bao-tri/?maintenance_id=' . $maintenance_id); ?>" class="btn btn-info btn-icon-text d-flex align-items-center">
                            <i class="ph ph-pencil me-1"></i> Chỉnh sửa
                        </a>
                        <a href="<?php echo home_url('/them-moi-hoa-don/?maintenance_id=' . $maintenance_id); ?>" class="btn btn-dark btn-icon-text d-flex align-items-center">
                            <i class="ph ph-receipt me-1"></i> Tạo hóa đơn
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php 
                    // Calculate status information for use in owner section
                    $status_class = 'bg-secondary';
                    $status_text = 'Không xác định';
                    
                    switch ($maintenance->status) {
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
                                                    <?php if ($maintenance->status === 'ACTIVE'): 
                                                    // Calculate days remaining until expiry
                                                    $now = new DateTime();
                                                    $expiry = new DateTime($maintenance->expiry_date);
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
                                                <th>Giá mỗi chu kỳ</th>
                                                <td class="bb-1"><strong><?php echo number_format($maintenance->price_per_cycle, 0, ',', '.'); ?> VNĐ</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted">Không tìm thấy thông tin chủ sở hữu</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Package Information -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title d-flex align-items-center mb-3 text-info">
                                        <i class="ph ph-wrench me-2 fa-150p"></i>
                                        Thông tin gói bảo trì
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <tr>
                                                <th class="pe-3">Loại gói</th>
                                                <td><?php echo $product ? $product->name : 'Không xác định'; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Ngày đăng ký</th>
                                                <td><?php echo date('d/m/Y', strtotime($maintenance->registration_date)); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Ngày hết hạn</th>
                                                <td>
                                                    <span class="badge border-radius-9 <?php echo strtotime($maintenance->expiry_date) < time() ? 'btn-inverse-danger' : 'btn-inverse-success'; ?>">
                                                        <?php echo date('d/m/Y', strtotime($maintenance->expiry_date)); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Chu kỳ thanh toán</th>
                                                <td><?php echo $maintenance->billing_cycle_months; ?> tháng</td>
                                            </tr>
                                            <tr>
                                                <th>Tổng thời gian</th>
                                                <td><?php echo $maintenance->total_months_registered; ?> tháng</td>
                                            </tr>
                                            <?php if ($partner): ?>
                                            <tr>
                                                <th>Đối tác</th>
                                                <td>
                                                    <a href="<?php echo home_url('/user-detail/?user_id=' . $partner->id); ?>" class="text-decoration-none">
                                                        <?php echo $partner->user_code . ' - ' . $partner->name; ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Cập nhật lần cuối</th>
                                                <td class="bb-1"><?php echo date('d/m/Y', strtotime($maintenance->updated_at)); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row w-100">
                        <div class="col-md-12">
                            <!-- Maintenance Package Details Card -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title d-flex align-items-center mb-4 text-success">
                                        <i class="ph ph-gear me-2 fa-150p"></i>
                                        Chi tiết gói bảo trì
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
                                                        <span class="badge border-radius-9 <?php echo strtotime($maintenance->expiry_date) < time() ? 'btn-inverse-danger' : 'btn-inverse-success'; ?>">
                                                            <?php echo date('d/m/Y', strtotime($maintenance->expiry_date)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small mb-2">
                                                        <span class="text-muted">Chu kỳ thanh toán:</span>
                                                        <?php echo $maintenance->billing_cycle_months; ?> tháng
                                                    </div>
                                                    <div class="small">
                                                        <span class="text-muted">Tổng thời gian:</span>
                                                        <?php echo $maintenance->total_months_registered; ?> tháng
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-info">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle d-flex align-items-center text-info mb-3">
                                                        <i class="ph ph-package me-2" style="font-size: 20px;"></i>
                                                        Loại gói dịch vụ
                                                    </h6>
                                                    <div class="mb-2">
                                                        <span class="fw-bold fs-5">
                                                            <?php echo $product ? esc_html($product->name) : 'Không xác định'; ?>
                                                        </span>
                                                    </div>
                                                    <div class="small">
                                                        <span class="text-muted">Trạng thái:</span>
                                                        <span class="badge border-radius-9 <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                    </div>
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
                                                            <?php echo number_format($maintenance->price_per_cycle, 0, ',', '.'); ?> VNĐ
                                                        </span>
                                                    </div>
                                                    <?php if ($maintenance->discount_amount > 0): ?>
                                                    <div class="small mb-1">
                                                        <span class="text-muted">Chiết khấu:</span>
                                                        <span class="text-danger"><?php echo number_format($maintenance->discount_amount, 0, ',', '.'); ?> VNĐ</span>
                                                    </div>
                                                    <div class="small">
                                                        <span class="text-muted">Thực thu:</span>
                                                        <span class="fw-bold"><?php echo number_format($maintenance->actual_revenue, 0, ',', '.'); ?> VNĐ</span>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="small">
                                                        <span class="text-muted">Mỗi chu kỳ:</span>
                                                        <?php echo $maintenance->billing_cycle_months; ?> tháng
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                    
                        <!-- Notes Section -->
                        <?php if (!empty($maintenance->notes)): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title d-flex align-items-center mb-3">
                                    <i class="ph ph-notepad me-2 text-secondary" style="font-size: 24px;"></i>
                                    Ghi chú
                                </h5>
                                <div class="card bg-light p-3">
                                    <pre class="mb-0"><?php echo esc_html($maintenance->notes); ?></pre>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                    
                <!-- Combined Section: Websites and Invoices -->
                <div class="row mb-4 w-100">
                    <!-- Websites Section (1/4) -->
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title d-flex align-items-center mb-0">
                                        <i class="ph ph-globe me-2 text-success" style="font-size: 20px;"></i>
                                        <span class="small">Các Website đang được bảo trì</span>
                                    </h5>
                                    <a href="<?php echo home_url('/them-moi-website/?maintenance_id=' . $maintenance_id); ?>" class="btn btn-sm btn-success" title="Thêm website mới">
                                        <i class="ph ph-plus"></i>
                                    </a>
                                </div>
                                
                                <?php if (!empty($websites)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th class="small">Tên website</th>
                                                <th class="small" width="80">Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($websites as $website): ?>
                                            <tr>
                                                <td class="small">
                                                    <a href="<?php echo home_url('/detail-website/?website_id=' . $website->id); ?>" class="text-decoration-none">
                                                        <?php echo $website->name; ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="<?php echo home_url('/edit-website/?website_id=' . $website->id); ?>" class="btn btn-sm btn-secondary" title="Chỉnh sửa">
                                                        <i class="ph ph-pencil"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2 text-center">
                                    <span class="badge bg-info small">Có <?php echo count($websites); ?> website</span>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-light text-center py-3">
                                    <i class="ph ph-globe-slash mb-1" style="font-size: 24px;"></i>
                                    <p class="small mb-2">Chưa có website nào</p>
                                    <a href="<?php echo home_url('/them-moi-website/?maintenance_id=' . $maintenance_id); ?>" class="btn btn-sm btn-success">
                                        <i class="ph ph-plus-circle me-1"></i> Thêm mới
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invoices Section (3/4) -->
                    <div class="col-md-8">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title d-flex align-items-center mb-0">
                                        <i class="ph ph-receipt me-2 text-success" style="font-size: 24px;"></i>
                                        Lịch sử hóa đơn
                                    </h5>
                                    <a href="<?php echo home_url('/them-moi-hoa-don/?maintenance_id=' . $maintenance_id); ?>" class="d-flex align-items-center btn btn-sm btn-success">
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
                                    <p class="mb-2">Chưa có hóa đơn nào cho gói bảo trì này</p>
                                    <a href="<?php echo home_url('/them-moi-hoa-don/?maintenance_id=' . $maintenance_id); ?>" class="btn btn-success">
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

<?php
get_footer();
?>