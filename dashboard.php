<?php
/* 
    Template Name: Dashboard
*/

global $wpdb;
$websites_table = $wpdb->prefix . 'im_websites';
$domains_table = $wpdb->prefix . 'im_domains';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';

// Count total items
$total_websites = $wpdb->get_var("SELECT COUNT(*) FROM $websites_table");
$total_domains = $wpdb->get_var("SELECT COUNT(*) FROM $domains_table");
$total_hostings = $wpdb->get_var("SELECT COUNT(*) FROM $hostings_table");
$total_maintenance = $wpdb->get_var("SELECT COUNT(*) FROM $maintenance_table");

// Current date
$current_date = date('Y-m-d');
// Date 1 month ago from now
$one_month_ago = date('Y-m-d', strtotime('-1 month'));
// Date 1 month from now
$one_month_later = date('Y-m-d', strtotime('+1 month'));

// Get domains expiring within 1 month (9 closest to expiry)
$soon_expiring_domains = $wpdb->get_results("
    SELECT d.*, u.name AS owner_name, u.user_code 
    FROM $domains_table d
    LEFT JOIN {$wpdb->prefix}im_users u ON d.owner_user_id = u.id
    WHERE d.expiry_date BETWEEN '$one_month_ago' AND '$one_month_later'
    ORDER BY d.expiry_date ASC 
    LIMIT 9
");

// Get hostings expiring within 1 month (9 closest to expiry)
$soon_expiring_hostings = $wpdb->get_results("
    SELECT h.*, u.name AS owner_name, u.user_code, p.name AS product_name
    FROM $hostings_table h
    LEFT JOIN {$wpdb->prefix}im_users u ON h.owner_user_id = u.id
    LEFT JOIN {$wpdb->prefix}im_product_catalog p ON h.product_catalog_id = p.id
    WHERE h.expiry_date BETWEEN '$one_month_ago' AND '$one_month_later'
    ORDER BY h.expiry_date ASC 
    LIMIT 9
");

// Get maintenance packages expiring within 1 month (9 closest to expiry)
$soon_expiring_maintenance = $wpdb->get_results("
    SELECT m.*, u.name AS owner_name, u.user_code, p.name AS package_name
    FROM $maintenance_table m
    LEFT JOIN {$wpdb->prefix}im_users u ON m.owner_user_id = u.id
    LEFT JOIN {$wpdb->prefix}im_product_catalog p ON m.product_catalog_id = p.id
    WHERE m.expiry_date BETWEEN '$one_month_ago' AND '$one_month_later'
    ORDER BY m.expiry_date ASC 
    LIMIT 9
");

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
        <div class="col-md-6 col-xl-3 mb-3">
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
        <div class="col-md-6 col-xl-3 mb-3">
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
        <div class="col-md-6 col-xl-3 mb-3">
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
        <div class="col-md-6 col-xl-3 mb-3">
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
                                    $days_remaining = (strtotime($domain->expiry_date) - time()) / 86400;
                                    $badge_class = 'bg-success';
                                    if ($days_remaining < 30) {
                                        $badge_class = 'bg-warning text-dark';
                                    }
                                    if ($days_remaining < 7) {
                                        $badge_class = 'bg-danger';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo home_url('/detail-domain/?domain_id=' . $domain->id); ?>" class="text-decoration-none">
                                            <?php echo esc_html($domain->domain_name); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo esc_html($domain->user_code); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo date('d/m/Y', strtotime($domain->expiry_date)); ?>
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
                                    $days_remaining = (strtotime($hosting->expiry_date) - time()) / 86400;
                                    $badge_class = 'bg-success';
                                    if ($days_remaining < 30) {
                                        $badge_class = 'bg-warning text-dark';
                                    }
                                    if ($days_remaining < 7) {
                                        $badge_class = 'bg-danger';
                                    }
                                    
                                    $hosting_name = !empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id;
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo home_url('/detail-hosting/?hosting_id=' . $hosting->id); ?>" class="text-decoration-none">
                                            <?php echo esc_html($hosting_name); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo esc_html($hosting->user_code); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo date('d/m/Y', strtotime($hosting->expiry_date)); ?>
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
                                    $days_remaining = (strtotime($maintenance->expiry_date) - time()) / 86400;
                                    $badge_class = 'bg-success';
                                    if ($days_remaining < 30) {
                                        $badge_class = 'bg-warning text-dark';
                                    }
                                    if ($days_remaining < 7) {
                                        $badge_class = 'bg-danger';
                                    }
                                    
                                    $maintenance_name = !empty($maintenance->order_code) ? $maintenance->order_code : 'MAINT-' . $maintenance->id;
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo home_url('/detail-maintenance/?maintenance_id=' . $maintenance->id); ?>" class="text-decoration-none">
                                            <?php echo esc_html($maintenance_name); ?>
                                        </a>
                                        <div class="small text-muted"><?php echo esc_html($maintenance->package_name); ?></div>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo esc_html($maintenance->user_code); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo date('d/m/Y', strtotime($maintenance->expiry_date)); ?>
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
                        <p class="mt-2 text-muted">Không có gói bảo trì sắp hết hạn</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional dashboard widgets can be added here -->
</div>

<?php
get_footer();
?>