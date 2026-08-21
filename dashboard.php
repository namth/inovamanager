<?php
/* 
    Template Name: Dashboard
*/

global $wpdb;
$websites_table = $wpdb->prefix . 'im_websites';
$domains_table = $wpdb->prefix . 'im_domains';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
$users_table = $wpdb->prefix . 'im_users';
$invoice_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';

// Get permission WHERE clauses
$website_permission = get_website_permission_where_clause('w');
$domain_permission = get_user_permission_where_clause('d', 'owner_user_id');
$hosting_permission = get_user_permission_where_clause('h', 'owner_user_id', 'partner_id');
$maintenance_permission = get_user_permission_where_clause('m', 'owner_user_id', 'partner_id');

// Count total items with permission filtering
$total_websites = $wpdb->get_var("SELECT COUNT(*) FROM $websites_table w WHERE 1=1 {$website_permission}");

$total_domains = $wpdb->get_var("
    SELECT COUNT(*)
    FROM $domains_table d
    WHERE 1=1 {$domain_permission}
");

$total_hostings = $wpdb->get_var("
    SELECT COUNT(*)
    FROM $hostings_table h
    WHERE 1=1 {$hosting_permission}
");

$total_maintenance = $wpdb->get_var("
    SELECT COUNT(*)
    FROM $maintenance_table m
    WHERE 1=1 {$maintenance_permission}
");

// Current date
$current_date = date('Y-m-d');
// Date 1 month ago from now
$one_month_ago = date('Y-m-d', strtotime('-1 month'));
// Date 1 month from now
$one_month_later = date('Y-m-d', strtotime('+1 month'));

// Get domains expiring within 1 month (9 closest to expiry) - chỉ lấy trạng thái ACTIVE (Đang hoạt động) và NEW (Chờ thanh toán)
$soon_expiring_domains = $wpdb->get_results("
    SELECT d.*, u.name AS owner_name, u.user_code
    FROM $domains_table d
    LEFT JOIN $users_table u ON d.owner_user_id = u.id
    WHERE d.expiry_date BETWEEN '$one_month_ago' AND '$one_month_later'
    AND d.status IN ('ACTIVE', 'NEW')
    {$domain_permission}
    ORDER BY d.expiry_date ASC
    LIMIT 9
");

// Get hostings expiring within 1 month (9 closest to expiry) - chỉ lấy trạng thái ACTIVE (Đang hoạt động) và NEW (Chờ thanh toán)
$soon_expiring_hostings = $wpdb->get_results("
    SELECT h.*, u.name AS owner_name, u.user_code, p.name AS product_name
    FROM $hostings_table h
    LEFT JOIN $users_table u ON h.owner_user_id = u.id
    LEFT JOIN {$wpdb->prefix}im_product_catalog p ON h.product_catalog_id = p.id
    WHERE h.expiry_date BETWEEN '$one_month_ago' AND '$one_month_later'
    AND h.status IN ('ACTIVE', 'NEW')
    {$hosting_permission}
    ORDER BY h.expiry_date ASC
    LIMIT 9
");

// Get maintenance packages expiring within 1 month (9 closest to expiry) - chỉ lấy trạng thái ACTIVE (Đang hoạt động) và NEW (Chờ thanh toán)
$soon_expiring_maintenance = $wpdb->get_results("
    SELECT m.*, u.name AS owner_name, u.user_code, p.name AS package_name
    FROM $maintenance_table m
    LEFT JOIN $users_table u ON m.owner_user_id = u.id
    LEFT JOIN {$wpdb->prefix}im_product_catalog p ON m.product_catalog_id = p.id
    WHERE m.expiry_date BETWEEN '$one_month_ago' AND '$one_month_later'
    AND m.status IN ('ACTIVE', 'NEW')
    {$maintenance_permission}
    ORDER BY m.expiry_date ASC
    LIMIT 9
");

// Get pending invoices (chờ thanh toán) with permission filtering
$pending_invoices = $wpdb->get_results("
    SELECT i.*, u.name AS customer_name, u.user_code
    FROM $invoice_table i
    LEFT JOIN $users_table u ON i.user_id = u.id
    WHERE i.status = 'pending'
    {$domain_permission}
    ORDER BY i.due_date ASC
    LIMIT 10
");

// Get invoice items for pending invoices
$invoice_items_map = array();
if (!empty($pending_invoices)) {
    $invoice_ids = wp_list_pluck($pending_invoices, 'id');
    $invoice_ids_placeholders = implode(',', array_fill(0, count($invoice_ids), '%d'));
    
    $items_query = $wpdb->prepare("
        SELECT id, invoice_id, service_type, service_id, description, quantity, unit_price, item_total
        FROM $invoice_items_table
        WHERE invoice_id IN ($invoice_ids_placeholders)
        ORDER BY invoice_id ASC, id ASC
    ", $invoice_ids);
    
    $all_items = $wpdb->get_results($items_query);
    
    // Map items to invoices and get website information
    foreach ($all_items as $item) {
        // Get website names using common function
        $item->website_names = get_invoice_item_website_names($item);
        
        if (!isset($invoice_items_map[$item->invoice_id])) {
            $invoice_items_map[$item->invoice_id] = array();
        }
        $invoice_items_map[$item->invoice_id][] = $item;
    }
}

// 1. Get failed website status logs (latest 8)
$failed_website_logs = array();
$status_logs_table = $wpdb->prefix . 'im_website_status_logs';
if ($wpdb->get_var("SHOW TABLES LIKE '{$status_logs_table}'") === $status_logs_table) {
    $failed_website_logs = $wpdb->get_results("
        SELECT *
        FROM {$status_logs_table}
        WHERE status = 'FAILED'
        ORDER BY id DESC
        LIMIT 8
    ");
}

// 2. Get recent plugin activity logs (latest 8)
$recent_plugin_activity_logs = array();
$plugin_logs_table = $wpdb->prefix . 'im_plugin_activity_logs';
if ($wpdb->get_var("SHOW TABLES LIKE '{$plugin_logs_table}'") === $plugin_logs_table) {
    $recent_plugin_activity_logs = $wpdb->get_results("
        SELECT *
        FROM {$plugin_logs_table}
        ORDER BY id DESC
        LIMIT 8
    ");
}

get_header();
?>

<div class="content-wrapper">
    <!-- Page Title -->
    <div class="row mb-3">
        <div class="col-12">
            <h1 class="page-title mb-0">Dashboard</h1>
            <p class="text-muted">Tổng quan dịch vụ</p>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <!-- Websites count -->
        <div class="col-sm-6 col-md-6 col-xl-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <h6 class="card-title mb-0">Tổng số website</h6>
                        <div class="dash-icon rounded-circle bg-light-primary text-primary">
                            <i class="ph ph-globe"></i>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h2 class="mb-1"><?php echo $total_websites; ?></h2>
                            <a href="<?php echo home_url('/list-website/'); ?>" class="text-decoration-none">
                                <span class="text-muted">Xem danh sách</span>
                                <i class="ph ph-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Domains count -->
        <div class="col-sm-6 col-md-6 col-xl-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <h6 class="card-title mb-0">Tổng số tên miền</h6>
                        <div class="dash-icon rounded-circle bg-light-info text-info">
                            <i class="ph ph-globe-hemisphere-west"></i>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h2 class="mb-1"><?php echo $total_domains; ?></h2>
                            <a href="<?php echo home_url('/domains/'); ?>" class="text-decoration-none">
                                <span class="text-muted">Xem danh sách</span>
                                <i class="ph ph-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hosting count -->
        <div class="col-sm-6 col-md-6 col-xl-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <h6 class="card-title mb-0">Tổng số hosting</h6>
                        <div class="dash-icon rounded-circle bg-light-success text-success">
                            <i class="ph ph-cloud"></i>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h2 class="mb-1"><?php echo $total_hostings; ?></h2>
                            <a href="<?php echo home_url('/hostings/'); ?>" class="text-decoration-none">
                                <span class="text-muted">Xem danh sách</span>
                                <i class="ph ph-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Maintenance count -->
        <div class="col-sm-6 col-md-6 col-xl-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <h6 class="card-title mb-0">Tổng số gói bảo trì</h6>
                        <div class="dash-icon rounded-circle bg-light-warning text-warning">
                            <i class="ph ph-wrench"></i>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h2 class="mb-1"><?php echo $total_maintenance; ?></h2>
                            <a href="<?php echo home_url('/maintenance/'); ?>" class="text-decoration-none">
                                <span class="text-muted">Xem danh sách</span>
                                <i class="ph ph-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Expiring Items Section -->
    <div class="row">
        <!-- Domains expiring soon -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100 border-info">
                <div class="card-header bg-light-info d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 text-info">
                        <i class="ph ph-globe-hemisphere-west me-1"></i>
                        Tên miền sắp hết hạn
                    </h5>
                    <a href="<?php echo home_url('/domains/'); ?>" class="btn btn-sm btn-info">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($soon_expiring_domains)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr class="bg-light">
                                    <th>Tên miền</th>
                                    <th>Chủ sở hữu</th>
                                    <th>Hết hạn</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($soon_expiring_domains as $domain): 
                                    $now = new DateTime();
                                    $expiry = new DateTime($domain->expiry_date);
                                    $is_expired = $now > $expiry;
                                    $badge_class = $is_expired ? 'bg-danger' : 'bg-success';
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo home_url('/domain/?domain_id=' . $domain->id); ?>" class="text-decoration-none">
                                            <?php echo esc_html($domain->domain_name); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo esc_html($domain->user_code); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge border-radius-9 <?php echo $badge_class; ?>">
                                            <?php echo date('d/m/Y', strtotime($domain->expiry_date)); ?>
                                        </span>
                                        <?php
                                        $now = new DateTime();
                                        $expiry = new DateTime($domain->expiry_date);
                                        $days_remaining = $now->diff($expiry)->days;
                                        $is_expired = $now > $expiry;
                                        
                                        if ($is_expired): ?>
                                            <span class="d-block mt-1 text-danger">Quá hạn <?php echo $days_remaining; ?> ngày</span>
                                        <?php elseif ($days_remaining <= 30): ?>
                                            <span class="d-block mt-1 text-warning">Còn <?php echo $days_remaining; ?> ngày</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center">
                        <i class="ph ph-check-circle text-success" style="font-size: 32px;"></i>
                        <p class="mt-2 text-muted">Không có tên miền sắp hết hạn</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Hostings expiring soon -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100 border-success">
                <div class="card-header bg-light-success d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 text-success">
                        <i class="ph ph-cloud me-1"></i>
                        Hosting sắp hết hạn
                    </h5>
                    <a href="<?php echo home_url('/hostings/'); ?>" class="btn btn-sm btn-success">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($soon_expiring_hostings)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr class="bg-light">
                                    <th>Hosting</th>
                                    <th>Chủ sở hữu</th>
                                    <th>Hết hạn</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($soon_expiring_hostings as $hosting): 
                                    $now = new DateTime();
                                    $expiry = new DateTime($hosting->expiry_date);
                                    $is_expired = $now > $expiry;
                                    $badge_class = $is_expired ? 'bg-danger' : 'bg-success';
                                    
                                    $hosting_name = !empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id;
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo home_url('/hosting/?hosting_id=' . $hosting->id); ?>" class="text-decoration-none">
                                            <?php echo esc_html($hosting_name); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo esc_html($hosting->user_code); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge border-radius-9 <?php echo $badge_class; ?>">
                                            <?php echo date('d/m/Y', strtotime($hosting->expiry_date)); ?>
                                        </span>
                                        <?php
                                        $now = new DateTime();
                                        $expiry = new DateTime($hosting->expiry_date);
                                        $days_remaining = $now->diff($expiry)->days;
                                        $is_expired = $now > $expiry;
                                        
                                        if ($is_expired): ?>
                                            <span class="d-block mt-1 text-danger">Quá hạn <?php echo $days_remaining; ?> ngày</span>
                                        <?php elseif ($days_remaining <= 30): ?>
                                            <span class="d-block mt-1 text-warning">Còn <?php echo $days_remaining; ?> ngày</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center">
                        <i class="ph ph-check-circle text-success" style="font-size: 32px;"></i>
                        <p class="mt-2 text-muted">Không có hosting sắp hết hạn</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Maintenance packages expiring soon -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100 border-warning">
                <div class="card-header bg-light-warning d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 text-warning">
                        <i class="ph ph-wrench me-1"></i>
                        Gói bảo trì sắp hết hạn
                    </h5>
                    <a href="<?php echo home_url('/maintenance/'); ?>" class="btn btn-sm btn-warning">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($soon_expiring_maintenance)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr class="bg-light">
                                    <th>Gói bảo trì</th>
                                    <th>Chủ sở hữu</th>
                                    <th>Hết hạn</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($soon_expiring_maintenance as $maintenance): 
                                    $now = new DateTime();
                                    $expiry = new DateTime($maintenance->expiry_date);
                                    $is_expired = $now > $expiry;
                                    $badge_class = $is_expired ? 'bg-danger' : 'bg-success';
                                    
                                    $maintenance_name = !empty($maintenance->order_code) ? $maintenance->order_code : 'MAINT-' . $maintenance->id;
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo home_url('/maintenance/?maintenance_id=' . $maintenance->id); ?>" class="text-decoration-none">
                                            <?php echo esc_html($maintenance_name); ?>
                                        </a>
                                        <div class="small text-muted"><?php echo esc_html($maintenance->package_name); ?></div>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo esc_html($maintenance->user_code); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge border-radius-9 <?php echo $badge_class; ?>">
                                            <?php echo date('d/m/Y', strtotime($maintenance->expiry_date)); ?>
                                        </span>
                                        <?php
                                        $now = new DateTime();
                                        $expiry = new DateTime($maintenance->expiry_date);
                                        $days_remaining = $now->diff($expiry)->days;
                                        $is_expired = $now > $expiry;
                                        
                                        if ($is_expired): ?>
                                            <span class="d-block mt-1 text-danger">Quá hạn <?php echo $days_remaining; ?> ngày</span>
                                        <?php elseif ($days_remaining <= 30): ?>
                                            <span class="d-block mt-1 text-warning">Còn <?php echo $days_remaining; ?> ngày</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center">
                        <i class="ph ph-check-circle text-success" style="font-size: 32px;"></i>
                        <p class="mt-2 text-muted">Không có gói bảo trì sắp hết hạn</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Invoices Section -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card border-danger">
                <div class="card-header bg-light-danger d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 text-danger">
                        <i class="ph ph-file-text me-1"></i>
                        Hóa đơn chờ thanh toán
                    </h5>
                    <a href="<?php echo home_url('/danh-sach-hoa-don/?status=pending'); ?>" class="btn btn-sm btn-danger">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($pending_invoices)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr class="bg-light">
                                    <th>Mã hóa đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Sản phẩm/Dịch vụ</th>
                                    <th>Hạn thanh toán</th>
                                    <th>Tổng tiền</th>
                                    <th>Đã thanh toán</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_invoices as $invoice):
                                    $now = new DateTime();
                                    $due = new DateTime($invoice->due_date);
                                    $is_overdue = $now > $due;
                                    $due_badge_class = $is_overdue ? 'bg-danger' : 'bg-success';
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $invoice->id); ?>" class="text-decoration-none">
                                            <?php echo esc_html($invoice->invoice_code); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo esc_html($invoice->user_code); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <?php
                                            if (isset($invoice_items_map[$invoice->id]) && !empty($invoice_items_map[$invoice->id])) {
                                                foreach ($invoice_items_map[$invoice->id] as $item) {
                                                    echo '<span class="mb-2">' . esc_html($item->description);
                                                    if (!empty($item->website_names)) {
                                                        $websites_string = implode(', ', array_map('esc_html', $item->website_names));
                                                        echo '<br><small class="text-muted"><i class="ph ph-globe-hemisphere-west"></i> (' . $websites_string . ')</small>';
                                                    }
                                                    echo '</span>';
                                                }
                                            } else {
                                                echo '<span class="text-muted">Không có sản phẩm</span>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge border-radius-9 <?php echo $due_badge_class; ?>">
                                            <?php echo date('d/m/Y', strtotime($invoice->due_date)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-danger">
                                            <?php echo number_format($invoice->total_amount, 0, ',', '.'); ?> VNĐ
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">
                                            <?php echo number_format($invoice->paid_amount, 0, ',', '.'); ?> VNĐ
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center">
                        <i class="ph ph-check-circle text-success" style="font-size: 32px;"></i>
                        <p class="mt-2 text-muted">Không có hóa đơn chờ thanh toán</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Website Status & Plugin Activity Logs Row -->
    <div class="row">
        <!-- Box 1: Website Status Logs (Failed) -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-danger">
                <div class="card-header bg-light-danger d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 text-danger">
                        <i class="ph ph-warning-circle me-1"></i>
                        Website mất kết nối / Lỗi (Failed)
                    </h5>
                    <a href="<?php echo admin_url('admin.php?page=nhat-ky-website-admin&status=FAILED'); ?>" class="btn btn-sm btn-danger">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($failed_website_logs)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr class="bg-light">
                                    <th>Website</th>
                                    <th>Chi tiết Lỗi / Mã HTTP</th>
                                    <th style="width: 140px;">Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($failed_website_logs as $flog): 
                                    $err_text = !empty($flog->error_message) ? $flog->error_message : (!empty($flog->http_code) ? 'HTTP ' . $flog->http_code : 'Lỗi không xác định');
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-danger"><?php echo esc_html($flog->website_name); ?></strong>
                                        <?php if (!empty($flog->website_id)): ?>
                                            <br><small class="text-muted">ID: <?php echo esc_html($flog->website_id); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code style="font-size: 11px; color: #dc3545; word-break: break-all; display: block; max-width: 250px;">
                                            <?php echo esc_html(mb_strimwidth($err_text, 0, 80, '...')); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="small text-muted d-block"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($flog->created_at))); ?></span>
                                        <small class="text-danger"><?php echo esc_html(human_time_diff(strtotime($flog->created_at), current_time('timestamp')) . ' trước'); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center">
                        <i class="ph ph-check-circle text-success" style="font-size: 32px;"></i>
                        <p class="mt-2 text-muted">Tất cả website đều hoạt động ổn định, không có lỗi</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Box 2: Plugin Activity Logs -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-primary">
                <div class="card-header bg-light-primary d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 text-primary">
                        <i class="ph ph-plugs-connected me-1"></i>
                        Nhật ký thay đổi Plugin gần đây
                    </h5>
                    <a href="<?php echo admin_url('admin.php?page=nhat-ky-plugin-admin'); ?>" class="btn btn-sm btn-primary">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recent_plugin_activity_logs)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr class="bg-light">
                                    <th>Website</th>
                                    <th style="width: 110px;">Hành động</th>
                                    <th>Plugin & Phiên bản</th>
                                    <th style="width: 140px;">Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_plugin_activity_logs as $plog): 
                                    $badge_class = 'bg-secondary';
                                    $label = $plog->action;
                                    switch (strtoupper($plog->action)) {
                                        case 'ACTIVATED':
                                            $badge_class = 'bg-success';
                                            $label = '🟢 Bật';
                                            break;
                                        case 'DEACTIVATED':
                                            $badge_class = 'bg-danger';
                                            $label = '🔴 Tắt';
                                            break;
                                        case 'INSTALLED':
                                            $badge_class = 'bg-info text-dark';
                                            $label = '📦 Cài mới';
                                            break;
                                        case 'UPDATED':
                                            $badge_class = 'bg-warning text-dark';
                                            $label = '🔄 Update';
                                            break;
                                        case 'DELETED':
                                            $badge_class = 'bg-dark';
                                            $label = '🗑️ Xóa';
                                            break;
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($plog->website_name); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>" style="font-size: 11px; padding: 4px 6px;">
                                            <?php echo esc_html($label); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo esc_html($plog->plugin_name); ?></div>
                                        <?php if (strtoupper($plog->action) === 'UPDATED' && !empty($plog->old_version)): ?>
                                            <small class="text-muted"><?php echo esc_html($plog->old_version); ?> ➔ <strong class="text-success"><?php echo esc_html($plog->plugin_version); ?></strong></small>
                                        <?php elseif (!empty($plog->plugin_version)): ?>
                                            <small class="text-muted">v<?php echo esc_html($plog->plugin_version); ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($plog->performed_by)): ?>
                                            <div class="small text-muted" style="font-size: 11px;"><i class="ph ph-user"></i> <?php echo esc_html($plog->performed_by); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="small text-muted d-block"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($plog->created_at))); ?></span>
                                        <small class="text-muted"><?php echo esc_html(human_time_diff(strtotime($plog->created_at), current_time('timestamp')) . ' trước'); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center">
                        <i class="ph ph-check-circle text-success" style="font-size: 32px;"></i>
                        <p class="mt-2 text-muted">Chưa có thay đổi plugin nào được ghi nhận</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
?>