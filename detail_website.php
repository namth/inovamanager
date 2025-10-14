<?php
/* 
    Template Name: Website Details
*/

global $wpdb;
$websites_table = $wpdb->prefix . 'im_websites';
$users_table = $wpdb->prefix . 'im_users';
$domains_table = $wpdb->prefix . 'im_domains';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';

$current_user_id = get_current_user_id();

// Get website ID from URL
$website_id = isset($_GET['website_id']) ? intval($_GET['website_id']) : 0;

// Redirect if no website ID provided
if (!$website_id) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

// Get website data
$website = $wpdb->get_row($wpdb->prepare("SELECT * FROM $websites_table WHERE id = %d", $website_id));

// Redirect if website not found
if (!$website) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

// Get owner data
$owner = null;
if ($website->owner_user_id) {
    $owner = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $website->owner_user_id));
}

// Get domain data
$domain = null;
if ($website->domain_id) {
    $domain = $wpdb->get_row($wpdb->prepare("SELECT * FROM $domains_table WHERE id = %d", $website->domain_id));
}

// Get hosting data
$hosting = null;
if ($website->hosting_id) {
    $hosting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $hostings_table WHERE id = %d", $website->hosting_id));
}

// Get maintenance package data
$maintenance = null;
if ($website->maintenance_package_id) {
    $maintenance = $wpdb->get_row($wpdb->prepare("SELECT * FROM $maintenance_table WHERE id = %d", $website->maintenance_package_id));
}

// Get related invoices
$invoices_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';

$query = "
    SELECT 
        i.*, 
        ii.service_type, 
        ii.start_date, 
        ii.end_date, 
        ii.unit_price,
        ii.description
    FROM 
        $invoices_table i
    JOIN 
        $invoice_items_table ii ON i.id = ii.invoice_id
    WHERE 
        (ii.service_type = 'Website' AND ii.service_id = %d)
        OR 
        (ii.service_type = 'Domain' AND ii.service_id = %d)
        OR 
        (ii.service_type = 'Hosting' AND ii.service_id = %d)
        OR 
        (ii.service_type = 'Maintenance' AND ii.service_id = %d)
    ORDER BY 
        i.invoice_date DESC
";

$invoices = $wpdb->get_results($wpdb->prepare($query, 
    $website_id, 
    $website->domain_id ?: 0, 
    $website->hosting_id ?: 0, 
    $website->maintenance_package_id ?: 0
));

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
                    <h3 class="mb-1">Chi tiết website <?php echo esc_html($website->name); ?></h3>
                </div>
                <div>
                    <div class="d-flex flex-row justify-content-center gap-3">
                        <a href="<?php echo home_url('/sua-website/?website_id=' . $website_id); ?>" class="btn btn-info btn-icon-text d-flex align-items-center">
                            <i class="ph ph-pencil me-1"></i> Chỉnh sửa
                        </a>
                        <a href="<?php echo home_url('/them-moi-hoa-don/?website_id=' . $website_id); ?>" class="btn btn-dark btn-icon-text d-flex align-items-center">
                            <i class="ph ph-receipt me-1"></i> Tạo hóa đơn
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <div class="row mb-4 w-100">
                        <!-- Owner Information -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h3 class="card-title d-flex align-items-center mb-3 text-primary">
                                        <i class="ph ph-user-circle me-2 fa-150p"></i>
                                        Thông tin chủ sở hữu
                                    </h3>
                                    <?php if (isset($owner)): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <?php if (current_user_can('administrator')): ?>
                                            <tr>
                                                <th class="pe-3">Mã khách hàng</th>
                                                <td><?php echo $owner->user_code; ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Tên</th>
                                                <td>
                                                    <a href="<?php echo home_url('/user-detail/?user_id=' . $owner->id); ?>" class="text-decoration-none">
                                                        <?php echo $owner->name; ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td><?php echo $owner->email; ?></td>
                                            </tr>
                                            <?php if (!empty($owner->company_name)): ?>
                                            <tr>
                                                <th>Công ty</th>
                                                <td><?php echo $owner->company_name; ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($owner->phone_number)): ?>
                                            <tr>
                                                <th>Số điện thoại</th>
                                                <td class="bb-1"><?php echo $owner->phone_number; ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted">Không tìm thấy thông tin chủ sở hữu</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Website Details -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title d-flex align-items-center mb-3 text-info">
                                        <i class="ph ph-globe me-2 fa-150p"></i>
                                        Thông tin quản trị website
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <?php if (!empty($website->admin_url)): ?>
                                            <tr>
                                                <th class="pe-3">URL Quản trị</th>
                                                <td>
                                                    <a href="<?php echo esc_url($website->admin_url); ?>" target="_blank" class="text-decoration-none">
                                                        <?php echo esc_url($website->admin_url); ?>
                                                        <i class="ph ph-arrow-square-out ms-1"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($website->admin_username)): ?>
                                            <tr>
                                                <th>Tài khoản</th>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span><?php echo esc_html($website->admin_username); ?></span>
                                                        <button class="btn btn-sm text-primary ms-2" onclick="navigator.clipboard.writeText('<?php echo esc_js($website->admin_username); ?>')">
                                                            <i class="ph ph-copy"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($website->admin_password)): ?>
                                            <tr>
                                                <th>Mật khẩu</th>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" id="admin-password" value="<?php echo esc_attr($website->admin_password); ?>" readonly>
                                                        <button class="btn btn-inverse-secondary border-secondary toggle-password" type="button">
                                                            <i class="ph ph-eye"></i>
                                                        </button>
                                                        <button class="btn btn-secondary" type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($website->admin_password); ?>')">
                                                            <i class="ph ph-copy"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Ngày tạo</th>
                                                <td><?php echo date('d/m/Y', strtotime($website->created_at)); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Cập nhật lần cuối</th>
                                                <td class="bb-1"><?php echo date('d/m/Y', strtotime($website->updated_at)); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4 w-100">
                        <div class="col-md-12">
                            <!-- Associated Services -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title d-flex align-items-center mb-4 text-success">
                                        <i class="ph ph-package me-2 fa-150p"></i>
                                        Dịch vụ liên kết
                                    </h5>
                                    
                                    <div class="row">
                                        <!-- Domain Service -->
                                        <div class="col-md-4 mb-3">
                                            <div class="card h-100 <?php echo $domain ? 'border-primary' : 'border-danger bg-inverse-danger'; ?>">
                                                <div class="card-body box">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="card-subtitle d-flex align-items-center <?php echo $domain ? 'text-primary' : 'text-muted'; ?>">
                                                            <i class="ph ph-globe me-2" style="font-size: 20px;"></i>
                                                            <span>Tên miền</span>
                                                        </h6>
                                                        <?php if ($domain): ?>
                                                        <div class="d-flex text-right">
                                                            <a href="<?php echo home_url('/domain/?domain_id=' . $domain->id); ?>" class="nav-link me-2">
                                                                <i class="ph ph-eye fa-150p"></i>
                                                            </a>
                                                            <span class="badge border-radius-9 bg-success">Đã liên kết</span>
                                                        </div>
                                                        <?php else: ?>
                                                        <span class="badge border-radius-9 bg-danger">Chưa liên kết</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if ($domain): ?>
                                                    <div class="mb-2">
                                                        <a href="http://<?php echo esc_html($domain->domain_name); ?>" target="_blank" class="text-decoration-none fw-bold fs-5">
                                                            <?php echo esc_html($domain->domain_name); ?>
                                                            <i class="ph ph-arrow-square-out"></i>
                                                        </a>
                                                    </div>
                                                    <div class="small mb-2">
                                                        <span class="text-muted">Hết hạn:</span>
                                                        <span class="badge border-radius-9 <?php echo strtotime($domain->expiry_date) < time() ? 'btn-inverse-danger' : 'btn-inverse-success'; ?>">
                                                            <?php echo date('d/m/Y', strtotime($domain->expiry_date)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small">
                                                        <span class="text-muted">Nhà cung cấp:</span>
                                                        <?php echo !empty($domain->provider_name) ? esc_html($domain->provider_name) : 'Không xác định'; ?>
                                                    </div>
                                                    <?php else: ?>
                                                    <p class="text-muted">Website này chưa có tên miền liên kết.</p>
                                                    <div class="mt-2">
                                                        <a href="<?php echo home_url('/them-moi-domain/?website_id=' . $website_id); ?>" class="btn btn-sm btn-danger d-flex align-items-center fit-content">
                                                            <i class="ph ph-plus-circle me-1"></i> Thêm tên miền
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Hosting Service -->
                                        <div class="col-md-4 mb-3">
                                            <div class="card h-100 <?php echo $hosting ? 'border-info' : 'border-danger bg-inverse-danger'; ?>">
                                                <div class="card-body box">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="card-subtitle d-flex align-items-center <?php echo $hosting ? 'text-info' : 'text-muted'; ?>">
                                                            <i class="ph ph-cloud me-2" style="font-size: 20px;"></i>
                                                            <span class="<?php echo $hosting ? '' : 'text-muted'; ?>">Hosting</span>
                                                        </h6>
                                                        <?php if ($hosting): ?>
                                                        <div class="d-flex text-right">
                                                            <a href="<?php echo home_url('/hosting/?hosting_id=' . $hosting->id); ?>" class="nav-link me-2">
                                                                <i class="ph ph-eye fa-150p"></i>
                                                            </a>
                                                            <span class="badge border-radius-9 bg-success">Đã liên kết</span>
                                                        </div>
                                                        <?php else: ?>
                                                        <span class="badge border-radius-9 bg-danger">Chưa liên kết</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if ($hosting): ?>
                                                    <div class="mb-2">
                                                        <span class="fw-bold fs-5">
                                                            <?php echo esc_html(!empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small mb-2">
                                                        <span class="text-muted">Hết hạn:</span>
                                                        <span class="badge border-radius-9 <?php echo strtotime($hosting->expiry_date) < time() ? 'btn-inverse-danger' : 'btn-inverse-success'; ?>">
                                                            <?php echo date('d/m/Y', strtotime($hosting->expiry_date)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small">
                                                        <span class="text-muted">Nhà cung cấp:</span>
                                                        <?php echo !empty($hosting->hosting_provider) ? esc_html($hosting->hosting_provider) : 'Không xác định'; ?>
                                                    </div>
                                                    <?php else: ?>
                                                    <p class="text-muted">Website này chưa có hosting liên kết.</p>
                                                    <div class="mt-2">
                                                        <a href="<?php echo home_url('/them-moi-hosting/?website_id=' . $website_id); ?>" class="btn btn-sm btn-danger d-flex align-items-center fit-content">
                                                            <i class="ph ph-plus-circle me-1"></i> Thêm hosting
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Maintenance Service -->
                                        <div class="col-md-4 mb-3">
                                            <div class="card h-100 <?php echo $maintenance ? 'border-warning' : 'border-danger bg-inverse-danger'; ?>">
                                                <div class="card-body box">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="card-subtitle d-flex align-items-center <?php echo $maintenance ? 'text-warning' : 'text-muted'; ?>">
                                                            <i class="ph ph-wrench me-2" style="font-size: 20px;"></i>
                                                            <span class="<?php echo $maintenance ? '' : 'text-muted'; ?>">Gói bảo trì</span>
                                                        </h6>
                                                        <?php if ($maintenance): ?>
                                                        <div class="d-flex text-right">
                                                            <a href="<?php echo home_url('/detail-maintenance/?maintenance_id=' . $maintenance->id); ?>" class="nav-link me-2">
                                                                <i class="ph ph-eye fa-150p"></i>
                                                            </a>
                                                            <span class="badge border-radius-9 bg-success">Đã liên kết</span>
                                                        </div>
                                                        <?php else: ?>
                                                        <span class="badge border-radius-9 bg-danger">Chưa liên kết</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if ($maintenance): ?>
                                                    <div class="mb-2">
                                                        <span class="fw-bold fs-5">
                                                            <?php echo esc_html(!empty($maintenance->order_code) ? $maintenance->order_code : 'MAINT-' . $maintenance->id); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small mb-2">
                                                        <span class="text-muted">Hết hạn:</span>
                                                        <span class="badge border-radius-9 <?php echo strtotime($maintenance->expiry_date) < time() ? 'btn-inverse-danger' : 'btn-inverse-success'; ?>">
                                                            <?php echo date('d/m/Y', strtotime($maintenance->expiry_date)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small">
                                                        <span class="text-muted">Trạng thái:</span>
                                                        <?php 
                                                        $status_class = 'bg-secondary';
                                                        $status_text = 'Không xác định';
                                                        
                                                        switch ($maintenance->status) {
                                                            case 'ACTIVE':
                                                                $status_class = 'btn-inverse-success';
                                                                $status_text = 'Đang hoạt động';
                                                                break;
                                                            case 'PENDING':
                                                                $status_class = 'btn-inverse-warning';
                                                                $status_text = 'Chờ xử lý';
                                                                break;
                                                            case 'EXPIRED':
                                                                $status_class = 'btn-danger';
                                                                $status_text = 'Hết hạn';
                                                                break;
                                                            case 'CANCELLED':
                                                                $status_class = 'btn-inverse-secondary';
                                                                $status_text = 'Đã hủy';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge border-radius-9 <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                    </div>
                                                    <?php else: ?>
                                                    <p class="text-muted">Website này chưa được bảo trì thường xuyên, có thể tồn tại rủi ro bảo mật.</p>
                                                    <div class="mt-2 d-flex gap-2">
                                                        <a href="<?php echo home_url('/them-goi-bao-tri/?website_id=' . $website_id); ?>" class="btn btn-sm btn-danger d-flex align-items-center fit-content">
                                                            <i class="ph ph-plus-circle me-1"></i> Thêm gói bảo trì
                                                        </a>
                                                        <a href="#" class="btn btn-sm btn-info d-flex align-items-center fit-content">
                                                            <i class="ph ph-info me-1"></i> Tìm hiểu thêm
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Notes Section -->
                            <?php if (!empty($website->notes)): ?>
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title d-flex align-items-center mb-3">
                                        <i class="ph ph-notepad me-2 text-secondary" style="font-size: 24px;"></i>
                                        Ghi chú
                                    </h5>
                                    <div class="card bg-light p-3">
                                        <pre class="mb-0"><?php echo esc_html($website->notes); ?></pre>
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
                                        <a href="<?php echo home_url('/them-moi-hoa-don/?website_id=' . $website_id); ?>" class="d-flex align-items-center btn btn-sm btn-success">
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
                                                        <?php 
                                                        $service_icon = 'ph-globe';
                                                        $service_text = 'Website';
                                                        
                                                        switch ($invoice->service_type) {
                                                            case 'Domain':
                                                                $service_icon = 'ph-globe';
                                                                $service_text = 'Tên miền';
                                                                break;
                                                            case 'Hosting':
                                                                $service_icon = 'ph-cloud';
                                                                $service_text = 'Hosting';
                                                                break;
                                                            case 'Maintenance':
                                                                $service_icon = 'ph-wrench';
                                                                $service_text = 'Bảo trì';
                                                                break;
                                                            case 'Website':
                                                                $service_icon = 'ph-globe';
                                                                $service_text = 'Website';
                                                                break;
                                                        }
                                                        ?>
                                                        <i class="ph <?php echo $service_icon; ?> me-1"></i>
                                                        <?php echo $service_text; ?>
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
                                        <p class="mb-2">Chưa có hóa đơn nào cho website này hoặc các dịch vụ liên kết</p>
                                        <a href="<?php echo home_url('/them-moi-hoa-don/?website_id=' . $website_id); ?>" class="btn btn-success">
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
    </div>
</div>

<script>
// Password toggle functionality is now handled by custom.js
</script>

<?php
get_footer();
?>