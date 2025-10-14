<?php
/* 
    Template Name: Website List
*/

global $wpdb;
$websites_table = $wpdb->prefix . 'im_websites';
$users_table = $wpdb->prefix . 'im_users';
$domains_table = $wpdb->prefix . 'im_domains';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';

// Add status column to websites table if it doesn't exist
$wpdb->query("ALTER TABLE $websites_table ADD COLUMN IF NOT EXISTS status ENUM('ACTIVE', 'DELETED') DEFAULT 'ACTIVE'");

// Get all websites with related data (exclude soft deleted)
$query = "
    SELECT 
        w.id,
        w.name,
        w.owner_user_id,
        w.domain_id,
        w.hosting_id,
        w.maintenance_package_id,
        w.admin_url,
        w.admin_username,
        w.admin_password,
        w.status,
        u.name AS owner_name,
        u.user_code AS owner_code,
        u.user_type AS owner_type,
        d.domain_name,
        h.hosting_code,
        m.order_code AS maintenance_code
    FROM 
        $websites_table w
    LEFT JOIN 
        $users_table u ON w.owner_user_id = u.id
    LEFT JOIN 
        $domains_table d ON w.domain_id = d.id
    LEFT JOIN 
        $hostings_table h ON w.hosting_id = h.id
    LEFT JOIN 
        $maintenance_table m ON w.maintenance_package_id = m.id
    WHERE 
        (w.status IS NULL OR w.status != 'DELETED')
    ORDER BY 
        w.name ASC
";

// Check if showing deleted websites
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';

// Update query based on show_deleted parameter
if ($show_deleted) {
    $query = str_replace(
        "WHERE \n        (w.status IS NULL OR w.status != 'DELETED')",
        "WHERE \n        w.status = 'DELETED'",
        $query
    );
}

$websites = $wpdb->get_results($query);

// Get search parameter
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Apply search filter
if (!empty($search_query)) {
    $websites = array_filter($websites, function($website) use ($search_query) {
        $search_lower = strtolower($search_query);
        return strpos(strtolower($website->name), $search_lower) !== false ||
               strpos(strtolower($website->owner_name), $search_lower) !== false ||
               strpos(strtolower($website->domain_name), $search_lower) !== false ||
               strpos(strtolower($website->hosting_code), $search_lower) !== false ||
               strpos(strtolower($website->maintenance_code), $search_lower) !== false;
    });
}

// Debug: Check if query executed successfully and data exists
if ($wpdb->last_error) {
    echo '<div class="alert alert-danger">Database Error: ' . $wpdb->last_error . '</div>';
}

get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
                    <?php 
                    $type = isset($_GET['type']) ? $_GET['type'] : 'website';
                    $method = isset($_GET['method']) ? $_GET['method'] : 'soft';
                    $type_text = ($type === 'website') ? 'website' : 'dịch vụ';
                    $method_text = ($method === 'hard') ? 'xóa vĩnh viễn' : 'xóa mềm';
                    ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="ph ph-check-circle me-2"></i>
                        <strong>Thành công!</strong> Đã <?php echo $method_text; ?> <?php echo $type_text; ?> thành công.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['restored']) && $_GET['restored'] == '1'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="ph ph-check-circle me-2"></i>
                        <strong>Thành công!</strong> Đã khôi phục website và dịch vụ thành công.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">
                            <?php echo $show_deleted ? 'Website đã xóa' : 'Danh sách Website'; ?>
                        </h4>
                        <div class="d-flex gap-2 align-items-center">
                            <!-- Toggle deleted view -->
                            <div class="me-3">
                                <?php if ($show_deleted): ?>
                                <a href="<?php echo home_url('/list-website/'); ?>" class="btn btn-sm btn-outline-success">
                                    <i class="ph ph-eye me-1"></i>
                                    Xem website hoạt động
                                </a>
                                <?php else: ?>
                                <a href="<?php echo home_url('/list-website/?show_deleted=1'); ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="ph ph-eye-slash me-1"></i>
                                    Xem website đã xóa
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Search functionality -->
                            <div class="d-flex align-items-center me-3">
                                <i class="ph ph-magnifying-glass text-muted me-2 fa-150p"></i>
                                <form method="GET" class="d-flex">
                                    <?php if ($show_deleted): ?>
                                    <input type="hidden" name="show_deleted" value="1">
                                    <?php endif; ?>
                                    <input type="text" name="search" class="form-control form-control-sm" 
                                           placeholder="Tìm kiếm website, chủ sở hữu, dịch vụ..." 
                                           value="<?php echo esc_attr($search_query); ?>" style="width: 300px;">
                                    <?php if (!empty($search_query)): ?>
                                    <a href="<?php echo home_url('/list-website/' . ($show_deleted ? '?show_deleted=1' : '')); ?>" class="btn btn-sm btn-outline-danger ms-1" title="Xóa bộ lọc">
                                        <i class="ph ph-x"></i>
                                    </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            
                            <button id="bulk-renew-btn" class="btn btn-secondary btn-icon-text" style="display: none;" onclick="handleBulkRenewal('websites', 'website', '<?php echo home_url('/them-moi-hoa-don/'); ?>')">
                                <i class="ph ph-arrow-clockwise btn-icon-prepend"></i>
                                <span>Gia hạn nhiều website</span>
                            </button>
                            <a href="<?php echo home_url('/them-moi-website/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới website" data-bs-toggle="tooltip" data-bs-placement="left">
                                <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Filter status indicator -->
                    <?php if (!empty($search_query)): ?>
                    <div class="alert alert-info d-flex align-items-center mb-3">
                        <i class="ph ph-info me-2"></i>
                        <div class="flex-grow-1">
                            <strong>Đang tìm kiếm:</strong>
                            <span class="badge bg-primary ms-1">"<?php echo esc_html($search_query); ?>"</span>
                            <span class="text-muted ms-2">(<?php echo count($websites); ?> kết quả)</span>
                        </div>
                        <a href="<?php echo home_url('/list-website/'); ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="ph ph-x me-1"></i>Xóa bộ lọc
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (empty($websites)): ?>
                    <div class="text-center py-5">
                        <i class="ph ph-globe icon-lg text-muted mb-3" style="font-size: 48px;"></i>
                        <?php if (!empty($search_query)): ?>
                            <h4>Không tìm thấy website nào</h4>
                            <p class="text-muted">
                                Không có kết quả cho từ khóa "<strong><?php echo esc_html($search_query); ?></strong>"
                            </p>
                            <a href="<?php echo home_url('/list-website/' . ($show_deleted ? '?show_deleted=1' : '')); ?>" class="btn btn-outline-secondary me-2">
                                <i class="ph ph-arrow-clockwise me-2"></i>Xóa bộ lọc
                            </a>
                        <?php else: ?>
                            <h4><?php echo $show_deleted ? 'Không có website nào bị xóa' : 'Chưa có website nào'; ?></h4>
                            <p class="text-muted">
                                <?php echo $show_deleted ? 'Tất cả website đang hoạt động bình thường' : 'Bắt đầu bằng cách thêm website đầu tiên của bạn'; ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!$show_deleted): ?>
                        <a href="<?php echo home_url('/them-moi-website/'); ?>" class="btn btn-primary">
                            <i class="ph ph-plus-circle me-2"></i> Thêm mới Website
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all-websites" class="form-check form-check-danger">
                                    </th>
                                    <th style="width: 50px;">STT</th>
                                    <th>Tên website</th>
                                    <th>Chủ sở hữu</th>
                                    <th>Tên miền</th>
                                    <th>Hosting</th>
                                    <th>Gói bảo trì</th>
                                    <th>Thông tin đăng nhập</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $stt = 1;
                                foreach ($websites as $website): 
                                ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check form-check-danger website-checkbox" 
                                               value="<?php echo $website->id; ?>" 
                                               data-owner-id="<?php echo $website->owner_user_id; ?>">
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-muted"><?php echo $stt++; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php 
                                            // Check if domain exists
                                            if (!empty($website->domain_name)){
                                                // Check website status
                                                $website_url = $website->domain_name;
                                                $is_up = check_website_status($website_url);
                                                $status_color = $is_up ? 'success' : 'danger';
                                                $status_title = $is_up ? 'Website is up' : 'Website is down';

                                                echo '<span class="website-status-dot me-2 bg-' . $status_color . '" title="' . $status_title . '"></span>';
                                            }
                                            ?>
                                            <div>
                                                <h6 class="mb-0">
                                                    <a href="<?php echo home_url('/website/?website_id=' . $website->id); ?>" class="nav-link">
                                                        <?php echo esc_html($website->name); ?>
                                                    </a>
                                                    <?php if ($show_deleted): ?>
                                                        <span class="badge bg-danger ms-2">Đã xóa</span>
                                                    <?php endif; ?>
                                                </h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($website->owner_name)): ?>
                                        <a href="<?php echo home_url('/partner/?id=' . $website->owner_user_id); ?>" class="d-flex align-items-center nav-link">
                                            <?php echo esc_html($website->owner_name); ?>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-between gap-2 flex-column">
                                            <?php 
                                                // Check if domain exists
                                                if (!empty($website->domain_name)): 
                                            ?>
                                                <a href="<?php echo 'https://' . $website->domain_name; ?>" class="d-flex align-items-center nav-link" target="_blank">
                                                    <i class="ph ph-globe-hemisphere-east me-1 text-primary fa-120p"></i> <b><?php echo esc_html($website->domain_name); ?></b>
                                                    <i class="ph ph-arrow-square-out ms-1"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Chưa có domain.</span>
                                                <a href="<?php echo home_url('/them-moi-domain/?website_id=' . $website->id); ?>" class="d-flex align-items-center nav-link text-success">
                                                    <i class="ph ph-plus-circle me-1 fa-120p"></i> Thêm mới
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($website->hosting_id)): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="ph ph-cloud text-info me-2 fa-120p"></i>
                                                <a href="<?php echo home_url('/hosting/?hosting_id=' . $website->hosting_id); ?>" class="nav-link text-decoration-none">
                                                    <?php 
                                                    $hosting_code = !empty($website->hosting_code) ? $website->hosting_code : 'HOST-' . $website->hosting_id;
                                                    echo '<h6 class="mb-0">' . esc_html($hosting_code) . '</h6>';
                                                    ?>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex justify-content-between gap-2 flex-column">
                                                <span class="text-muted">Chưa có gói hosting nào.</span>
                                                <a href="<?php echo home_url('/them-moi-hosting/?website_id=' . $website->id); ?>" class="d-flex align-items-center nav-link text-success">
                                                    <i class="ph ph-plus-circle me-1 fa-120p"></i> Thêm mới
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($website->maintenance_package_id)): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="ph ph-wrench text-warning me-2 fa-120p"></i>
                                                <a href="<?php echo home_url('/maintenance/?maintenance_id=' . $website->maintenance_package_id); ?>" class="nav-link text-decoration-none">
                                                    <?php 
                                                    $maintenance_code = !empty($website->maintenance_code) ? $website->maintenance_code : 'MAINT-' . $website->maintenance_package_id;
                                                    echo '<h6 class="mb-0">' . esc_html($maintenance_code) . '</h6>';
                                                    ?>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Website chưa được bảo trì</span>
                                            <a href="<?php echo home_url('/them-goi-bao-tri/?website_id=' . $website->id); ?>" class="d-flex align-items-center nav-link text-success">
                                                <i class="ph ph-plus-circle me-1 fa-120p"></i> Thêm mới
                                            </a>
                                            <a href="<?php echo home_url(); ?>" class="d-flex align-items-center nav-link text-warning">
                                                <i class="ph ph-info me-1 fa-120p"></i> Tìm hiểu thêm
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($website->admin_url)): ?>
                                        <div class="d-flex align-items-center mb-1">
                                            <small class="text-muted me-2">Admin URL:</small>
                                            <a href="<?php echo esc_url($website->admin_url); ?>" target="_blank" class="badge btn-danger border-radius-9 nav-link">
                                                <?php echo esc_html(parse_url($website->admin_url, PHP_URL_HOST)); ?>
                                                <i class="ph ph-arrow-square-out ms-1"></i>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($website->admin_username)): ?>
                                        <div class="d-flex align-items-center">
                                            <small class="text-muted me-2">Login:</small>
                                            <span class="badge btn-danger border-radius-9 text-truncate credential-badge" style="max-width: 150px;">
                                                <?php echo esc_html($website->admin_username); ?>
                                            </span>
                                            <?php if (!empty($website->admin_password)): ?>
                                            <button class="btn btn-sm btn-icon p-0 ms-1 show-password-btn" data-password="<?php echo esc_attr($website->admin_password); ?>">
                                                <i class="ph ph-eye"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($show_deleted): ?>
                                            <div class="d-flex align-items-center">
                                                <a href="<?php echo home_url('/khoi-phuc-website/?id=' . $website->id); ?>" class="nav-link text-success me-2" title="Khôi phục website">
                                                    <i class="ph ph-arrow-clockwise btn-icon-prepend fa-150p"></i>
                                                </a>
                                                
                                                <a href="<?php echo home_url('/xoa-website/?id=' . $website->id); ?>" class="nav-link text-danger" title="Xóa vĩnh viễn" onclick="return confirm('Bạn có chắc chắn muốn xóa vĩnh viễn website này?');">
                                                    <i class="ph ph-trash btn-icon-prepend fa-150p"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex align-items-center">
                                                <a href="<?php echo home_url('/sua-website/?website_id=' . $website->id); ?>" class="nav-link text-warning me-2" title="Sửa website">
                                                    <i class="ph ph-pencil-simple btn-icon-prepend fa-150p"></i>
                                                </a>
                                                
                                                <a href="<?php echo home_url('/them-dich-vu-moi/?website_id=' . $website->id); ?>" class="nav-link text-info me-2" title="Thêm dịch vụ mới">
                                                    <i class="ph ph-wrench btn-icon-prepend fa-150p"></i>
                                                </a>
                                                
                                                <a href="<?php echo home_url('/them-moi-hoa-don/?website_id=' . $website->id); ?>" class="nav-link text-warning me-2" title="Tạo hóa đơn">
                                                    <i class="ph ph-receipt btn-icon-prepend fa-150p"></i>
                                                </a>
                                                
                                                <a href="<?php echo home_url('/xoa-website/?id=' . $website->id); ?>" class="nav-link text-danger" title="Xóa website" onclick="return confirm('Bạn có chắc chắn muốn xóa website này?');">
                                                    <i class="ph ph-trash btn-icon-prepend fa-150p"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk renewal functionality is now handled by custom.js -->

<?php
get_footer();
?>