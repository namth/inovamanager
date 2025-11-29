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

// Get search parameter
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Check if showing deleted websites
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';

// Build WHERE clause
$where_conditions = array();

// Status filter
if ($show_deleted) {
    $where_conditions[] = "w.status = 'DELETED'";
} else {
    $where_conditions[] = "(w.status IS NULL OR w.status != 'DELETED')";
}

// Permission filter - Customer/Partner can see their own or managed items
$permission_where = get_website_permission_where_clause('w');
if (!empty($permission_where)) {
    $where_conditions[] = ltrim($permission_where, ' AND');
}

// Search filter
if (!empty($search_query)) {
    $search_like = '%' . $wpdb->esc_like($search_query) . '%';
    $where_conditions[] = $wpdb->prepare("(
        w.name LIKE %s OR
        u.name LIKE %s OR
        d.domain_name LIKE %s OR
        h.hosting_code LIKE %s OR
        m.order_code LIKE %s
    )", $search_like, $search_like, $search_like, $search_like, $search_like);
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get all websites with related data
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
        w.ip_address AS website_ip,
        w.active_time,
        w.status,
        w.created_by,
        u.name AS owner_name,
        u.user_code AS owner_code,
        u.user_type AS owner_type,
        d.domain_name,
        d.expiry_date AS domain_expiry_date,
        h.hosting_code,
        h.ip_address AS hosting_ip,
        h.expiry_date AS hosting_expiry_date,
        m.order_code AS maintenance_code,
        m.expiry_date AS maintenance_expiry_date
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
    {$where_clause}
    ORDER BY
        w.name ASC
";

// Pagination settings
$items_per_page = 20;
// $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$current_page = max(1, intval(get_query_var('paged')));

// Get total count with same WHERE clause
$count_query = "
    SELECT COUNT(*) as total
    FROM $websites_table w
    LEFT JOIN $users_table u ON w.owner_user_id = u.id
    LEFT JOIN $domains_table d ON w.domain_id = d.id
    LEFT JOIN $hostings_table h ON w.hosting_id = h.id
    LEFT JOIN $maintenance_table m ON w.maintenance_package_id = m.id
    {$where_clause}
";
$total_items = $wpdb->get_var($count_query);
$total_pages = ceil($total_items / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// Add LIMIT to query
$query .= " LIMIT $items_per_page OFFSET $offset";

$websites = $wpdb->get_results($query);

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
                            <?php if (is_inova_admin()): ?>
                            <!-- Toggle deleted view - ADMIN ONLY -->
                            <div class="me-3">
                                <?php if ($show_deleted): ?>
                                <a href="<?php echo home_url('/list-website/'); ?>" class="btn btn-sm btn-success d-flex align-items-center">
                                    <i class="ph ph-eye me-1"></i>
                                    Xem website hoạt động
                                </a>
                                <?php else: ?>
                                <a href="<?php echo home_url('/list-website/?show_deleted=1'); ?>" class="btn btn-sm btn-secondary d-flex align-items-center">
                                    <i class="ph ph-eye-slash me-1"></i>
                                    Xem website đã xóa
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

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
                                    <a href="<?php echo home_url('/list-website/' . ($show_deleted ? '?show_deleted=1' : '')); ?>" class="btn btn-sm btn-danger ms-1 d-flex align-items-center" title="Xóa bộ lọc">
                                        <i class="ph ph-x"></i>
                                    </a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <?php if (is_inova_admin()): ?>
                            <!-- Bulk actions - ADMIN ONLY -->
                            <button id="bulk-renew-btn" class="btn btn-secondary btn-icon-text" style="display: none;" onclick="handleBulkRenewal('websites', 'website', '<?php echo home_url('/them-moi-hoa-don/'); ?>')">
                                <i class="ph ph-arrow-clockwise btn-icon-prepend"></i>
                                <span>Gia hạn nhiều website</span>
                            </button>
                            <a href="<?php echo home_url('/addnew-website/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới website" data-bs-toggle="tooltip" data-bs-placement="left">
                                <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
                            </a>
                            <?php else: ?>
                            <!-- Add new website button - USER ONLY -->
                            <a href="<?php echo home_url('/addnew-website-simple/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới website" data-bs-toggle="tooltip" data-bs-placement="left">
                                <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
                            </a>
                            <?php endif; ?>
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
                        <a href="<?php echo home_url('/list-website/'); ?>" class="btn btn-sm btn-secondary d-flex align-items-center">
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
                            <a href="<?php echo home_url('/list-website/' . ($show_deleted ? '?show_deleted=1' : '')); ?>" class="btn btn-secondary me-2 d-flex align-items-center">
                                <i class="ph ph-arrow-clockwise me-2"></i>Xóa bộ lọc
                            </a>
                        <?php else: ?>
                            <h4><?php echo $show_deleted ? 'Không có website nào bị xóa' : 'Chưa có website nào'; ?></h4>
                            <p class="text-muted">
                                <?php echo $show_deleted ? 'Tất cả website đang hoạt động bình thường' : 'Bắt đầu bằng cách thêm website đầu tiên của bạn'; ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!$show_deleted && is_inova_admin()): ?>
                        <a href="<?php echo home_url('/addnew-website/'); ?>" class="btn btn-primary">
                            <i class="ph ph-plus-circle me-2"></i> Thêm mới Website
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <?php if (is_inova_admin()): ?>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="select-all-websites" class="form-check form-check-danger">
                                        </th>
                                    <?php endif; ?>

                                    <th style="width: 50px;">STT</th>
                                    <th>Tên website</th>

                                    <?php 
                                    $is_admin = is_inova_admin();
                                    $user_type = get_inova_user_type();
                                    if ($is_admin || $user_type === 'PARTNER'): ?>
                                        <th>Chủ sở hữu</th>
                                    <?php endif; ?>
                                    
                                    <th>Tên miền</th>
                                    <?php if (is_inova_admin()): ?>
                                        <th>Địa chỉ IP</th>
                                    <?php endif; ?>
                                    
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
                                    <?php if (is_inova_admin()): ?>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check form-check-danger website-checkbox"
                                               value="<?php echo $website->id; ?>"
                                               data-owner-id="<?php echo $website->owner_user_id; ?>">
                                    </td>
                                    <?php endif; ?>
                                    <td class="text-center">
                                        <span class="fw-bold text-muted"><?php echo $stt++; ?></span>
                                    </td>
                                    <td>
                                         <div class="d-flex align-items-center">
                                             <?php
                                             // Check website status based on active_time
                                             $status_color = 'danger'; // Default: red (down)
                                             $status_title = 'Website is down';
                                             $last_seen_text = '';

                                             if ($website->active_time) {
                                                 $active_time = strtotime($website->active_time);
                                                 $current_time = current_time('timestamp');
                                                 $diff_minutes = ($current_time - $active_time) / 60;

                                                 if ($diff_minutes <= 10) {
                                                     $status_color = 'success'; // Green (up)
                                                     $status_title = 'Website is up';
                                                 }

                                                 // Format last seen text
                                                 if ($diff_minutes < 1) {
                                                     $last_seen_text = 'Vừa xong';
                                                 } elseif ($diff_minutes < 60) {
                                                     $last_seen_text = round($diff_minutes) . ' phút trước';
                                                 } else if ($diff_minutes < 1440) { // Less than 24 hours
                                                     $hours = round($diff_minutes / 60);
                                                     $last_seen_text = $hours . ' giờ trước';
                                                 } else {
                                                     $days = round($diff_minutes / 1440);
                                                     $last_seen_text = $days . ' ngày trước';
                                                 }
                                             } else {
                                                 $last_seen_text = 'Chưa kết nối';
                                             }

                                             echo '<span class="website-status-dot me-2 bg-' . $status_color . '" title="' . $status_title . '"></span>';
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
                                                 <small class="text-muted fst-italic d-block mt-1" style="font-size: 11px;">
                                                     <?php echo esc_html($last_seen_text); ?>
                                                 </small>
                                             </div>
                                         </div>
                                     </td>
                                      <?php if ($is_admin || $user_type === 'PARTNER'): ?>
                                      <td>
                                          <?php if (!empty($website->owner_name)): ?>
                                          <a href="<?php echo home_url('/list-website/?search=' . $website->owner_name); ?>" class="d-flex align-items-center nav-link">
                                              <?php echo esc_html($website->owner_name); ?>
                                          </a>
                                          <?php else: ?>
                                          <span class="text-muted">--</span>
                                          <?php endif; ?>
                                      </td>
                                      <?php endif; ?>
                                    <td>
                                        <div class="d-flex justify-content-between gap-2 flex-column">
                                            <?php
                                                // Check if domain exists
                                                if (!empty($website->domain_name)):
                                            ?>
                                                <div>
                                                    <a href="<?php echo 'https://' . $website->domain_name; ?>" class="d-flex align-items-center nav-link" target="_blank">
                                                        <i class="ph ph-globe-hemisphere-east me-1 text-primary fa-120p"></i> <b><?php echo esc_html($website->domain_name); ?></b>
                                                        <i class="ph ph-arrow-square-out ms-1"></i>
                                                    </a>
                                                    <?php if (!empty($website->domain_expiry_date)): ?>
                                                        <small class="text-muted ms-4">HSD: <?php echo date('d/m/Y', strtotime($website->domain_expiry_date)); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Chưa có domain.</span>
                                                <?php if (is_inova_admin()): ?>
                                                <a href="<?php echo home_url('/them-moi-domain/?website_id=' . $website->id); ?>" class="d-flex align-items-center nav-link text-success">
                                                    <i class="ph ph-plus-circle me-1 fa-120p"></i> Thêm mới
                                                </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                     <?php if (is_inova_admin()): ?>
                                     <td>
                                         <?php
                                             // Prioritize website IP, fallback to hosting IP
                                             $display_ip = !empty($website->website_ip) ? $website->website_ip : $website->hosting_ip;
                                             if (!empty($display_ip)) {
                                                 echo '<span class="fw-bold text-danger">' . esc_html($display_ip) . '</span>';
                                             } else {
                                                 echo '<span class="text-muted">--</span>';
                                             }
                                         ?>
                                     </td>
                                     <?php endif; ?>
                                    <td>
                                        <?php if (!empty($website->hosting_id)): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="ph ph-cloud text-info me-2 fa-120p"></i>
                                                <a href="<?php echo home_url('/hosting/?hosting_id=' . $website->hosting_id); ?>" class="nav-link text-decoration-none">
                                                    <?php if (!empty($website->hosting_expiry_date)): ?>
                                                        <span class="badge border-radius-9 <?php echo strtotime($website->hosting_expiry_date) < time() ? 'bg-danger' : 'bg-success'; ?> text-white">
                                                            <?php echo date('d/m/Y', strtotime($website->hosting_expiry_date)); ?>
                                                        </span>
                                                        <?php
                                                        // Calculate days remaining until expiry
                                                        $now = new DateTime();
                                                        $expiry = new DateTime($website->hosting_expiry_date);
                                                        $days_remaining = $now->diff($expiry)->days;
                                                        $is_expired = $now > $expiry;

                                                        if ($is_expired): ?>
                                                            <span class="d-block mt-1 text-danger">Quá hạn <?php echo $days_remaining; ?> ngày</span>
                                                        <?php elseif ($days_remaining <= 30): ?>
                                                            <span class="d-block mt-1 text-warning">Còn <?php echo $days_remaining; ?> ngày</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php
                                                        $hosting_code = !empty($website->hosting_code) ? $website->hosting_code : 'HOST-' . $website->hosting_id;
                                                        echo '<h6 class="mb-0">' . esc_html($hosting_code) . '</h6>';
                                                        ?>
                                                    <?php endif; ?>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex justify-content-between gap-2 flex-column">
                                                <span class="text-muted">Chưa có gói hosting nào.</span>
                                                <?php if (is_inova_admin()): ?>
                                                <a href="<?php echo home_url('/them-moi-hosting/?website_id=' . $website->id); ?>" class="d-flex align-items-center nav-link text-success">
                                                    <i class="ph ph-plus-circle me-1 fa-120p"></i> Thêm mới
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($website->maintenance_package_id)): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="ph ph-wrench text-warning me-2 fa-120p"></i>
                                                <a href="<?php echo home_url('/maintenance/?maintenance_id=' . $website->maintenance_package_id); ?>" class="nav-link text-decoration-none">
                                                    <?php if (!empty($website->maintenance_expiry_date)): ?>
                                                        <span class="badge border-radius-9 <?php echo strtotime($website->maintenance_expiry_date) < time() ? 'bg-danger' : 'bg-success'; ?> text-white">
                                                            <?php echo date('d/m/Y', strtotime($website->maintenance_expiry_date)); ?>
                                                        </span>
                                                        <?php
                                                        // Calculate days remaining until expiry
                                                        $now = new DateTime();
                                                        $expiry = new DateTime($website->maintenance_expiry_date);
                                                        $days_remaining = $now->diff($expiry)->days;
                                                        $is_expired = $now > $expiry;

                                                        if ($is_expired): ?>
                                                            <span class="d-block mt-1 text-danger">Quá hạn <?php echo $days_remaining; ?> ngày</span>
                                                        <?php elseif ($days_remaining <= 30): ?>
                                                            <span class="d-block mt-1 text-warning">Còn <?php echo $days_remaining; ?> ngày</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php
                                                        $maintenance_code = !empty($website->maintenance_code) ? $website->maintenance_code : 'MAINT-' . $website->maintenance_package_id;
                                                        echo '<h6 class="mb-0">' . esc_html($maintenance_code) . '</h6>';
                                                        ?>
                                                    <?php endif; ?>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Website chưa được bảo trì</span>
                                            <?php if (is_inova_admin()): ?>
                                            <a href="<?php echo home_url('/them-goi-bao-tri/?website_id=' . $website->id); ?>" class="d-flex align-items-center nav-link text-success">
                                                <i class="ph ph-plus-circle me-1 fa-120p"></i> Thêm mới
                                            </a>
                                            <a href="<?php echo home_url(); ?>" class="d-flex align-items-center nav-link text-warning">
                                                <i class="ph ph-info me-1 fa-120p"></i> Tìm hiểu thêm
                                            </a>
                                            <?php else: ?>
                                            <a href="<?php echo home_url('/dang-ky-bao-tri/?website_id=' . $website->id); ?>" class="d-flex align-items-center nav-link text-success">
                                                <i class="ph ph-plus-circle me-1 fa-120p"></i> Đăng ký bảo trì
                                            </a>
                                            <a href="<?php echo home_url('/dang-ky-bao-tri/?website_id=' . $website->id); ?>" class="d-flex align-items-center nav-link text-warning">
                                                <i class="ph ph-info me-1 fa-120p"></i> Tìm hiểu thêm
                                            </a>
                                            <?php endif; ?>
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
                                            <button class="btn btn-sm btn-icon p-0 ms-1 show-password-btn" data-password="<?php echo esc_attr(im_decrypt_password($website->admin_password)); ?>">
                                                <i class="ph ph-eye"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                         <?php if (is_inova_admin()): ?>
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
                                         <?php else: ?>
                                              <!-- User actions - EDIT + DELETE -->
                                              <div class="d-flex align-items-center gap-2">
                                                  <a href="<?php echo home_url('/sua-website-simple/?website_id=' . $website->id); ?>" class="nav-link text-warning" title="Sửa website">
                                                      <i class="ph ph-pencil-simple btn-icon-prepend fa-150p"></i>
                                                  </a>
                                                  <?php
                                                  // Get current user ID from WordPress (converted to Inova user)
                                                  $current_wp_user_id = get_current_user_id();
                                                  $current_inova_user = get_inova_user($current_wp_user_id);
                                                  $can_user_delete = false;
                                                  
                                                  if ($current_wp_user_id && !empty($website->created_by)) {
                                                      // User can delete if they created the website
                                                      if ($current_wp_user_id == $website->created_by) {
                                                          $can_user_delete = true;
                                                      }
                                                  }
                                                  
                                                  if ($can_user_delete):
                                                  ?>
                                                  <button class="nav-link text-danger border-0 bg-transparent p-0 delete-website-btn" 
                                                          data-website-id="<?php echo $website->id; ?>"
                                                          data-website-name="<?php echo esc_attr($website->name); ?>"
                                                          title="Xóa website">
                                                      <i class="ph ph-trash btn-icon-prepend fa-150p"></i>
                                                  </button>
                                                  <?php endif; ?>
                                              </div>
                                          <?php endif; ?>
                                     </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Hiển thị <?php echo (($current_page - 1) * $items_per_page) + 1; ?>
                            đến <?php echo min($current_page * $items_per_page, $total_items); ?>
                            trong tổng số <?php echo $total_items; ?> website
                        </div>
                        <nav>
                            <ul class="pagination">
                                <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?paged=<?php echo $current_page - 1; ?><?php echo $show_deleted ? '&show_deleted=1' : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                        <i class="ph ph-caret-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);

                                if ($start_page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="?paged=1<?php echo $show_deleted ? '&show_deleted=1' : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">1</a></li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?paged=<?php echo $i; ?><?php echo $show_deleted ? '&show_deleted=1' : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="?paged=<?php echo $total_pages; ?><?php echo $show_deleted ? '&show_deleted=1' : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"><?php echo $total_pages; ?></a></li>
                                <?php endif; ?>

                                <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?paged=<?php echo $current_page + 1; ?><?php echo $show_deleted ? '&show_deleted=1' : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                        <i class="ph ph-caret-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>

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