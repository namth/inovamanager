<?php
/* 
    Template Name: Domain List
*/

global $wpdb;
$domains_table = $wpdb->prefix . 'im_domains';
$users_table = $wpdb->prefix . 'im_users';
$product_catalog_table = $wpdb->prefix . 'im_product_catalog';
$websites_table = $wpdb->prefix . 'im_websites';

// Handle delete request
$message = '';
$message_type = '';

if (isset($_POST['delete_domain_id']) && !empty($_POST['delete_domain_id'])) {
    $domain_id = intval($_POST['delete_domain_id']);

    // Check if domain exists
    $domain = $wpdb->get_row($wpdb->prepare("
        SELECT d.*, pc.name as product_name
        FROM $domains_table d
        LEFT JOIN $product_catalog_table pc ON d.product_catalog_id = pc.id
        WHERE d.id = %d
    ", $domain_id));

    if ($domain) {
        // Check if domain is being used by any websites
        $websites_using_domain = $wpdb->get_results($wpdb->prepare("
            SELECT id, name FROM $websites_table WHERE domain_id = %d
        ", $domain_id));

        $force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] == '1';

        if ($websites_using_domain && !$force_delete) {
            $message = "Không thể xóa domain này vì đang được sử dụng bởi " . count($websites_using_domain) . " website(s). Sử dụng 'Xóa bắt buộc' nếu bạn muốn tiếp tục.";
            $message_type = 'warning';
        } else {
            // If force delete, update domain_id of websites to NULL
            if ($websites_using_domain && $force_delete) {
                $wpdb->update(
                    $websites_table,
                    array('domain_id' => null),
                    array('domain_id' => $domain_id),
                    array('%s'),
                    array('%d')
                );
            }

            // Delete the domain
            $deleted = $wpdb->delete(
                $domains_table,
                array('id' => $domain_id),
                array('%d')
            );

            if ($deleted) {
                $message = "Domain '{$domain->domain_name}' đã được xóa thành công!";
                $message_type = 'success';
            } else {
                $message = "Có lỗi xảy ra khi xóa domain.";
                $message_type = 'danger';
            }
        }
    } else {
        $message = "Domain không tồn tại.";
        $message_type = 'danger';
    }
}

// Get search and filter parameters
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Build WHERE clause
$where_conditions = array();
$permission_where = get_user_permission_where_clause('d', 'owner_user_id');
if (!empty($permission_where)) {
    $where_conditions[] = ltrim($permission_where, ' AND');
}

if (!empty($search_query)) {
    $search_like = '%' . $wpdb->esc_like($search_query) . '%';
    $where_conditions[] = $wpdb->prepare("(d.domain_name LIKE %s OR u.name LIKE %s OR u.user_code LIKE %s OR p.name LIKE %s)", $search_like, $search_like, $search_like, $search_like);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT
        d.*,
        u.name AS owner_name,
        u.user_code AS owner_code,
        p.name AS provider_name,
        pc.name AS product_name,
        pc.service_type,
        IFNULL(w.name, '') AS website_name,
        IFNULL(w.id, 0) AS website_id
    FROM
        $domains_table d
    LEFT JOIN
        $users_table u ON d.owner_user_id = u.id
    LEFT JOIN
        $users_table p ON d.provider_id = p.id AND p.user_type = 'SUPPLIER'
    LEFT JOIN
        $product_catalog_table pc ON d.product_catalog_id = pc.id
    LEFT JOIN
        $websites_table w ON w.domain_id = d.id
    {$where_clause}
    ORDER BY
        d.expiry_date ASC
";

// Pagination settings
$items_per_page = 10;
$current_page = max(1, intval(get_query_var('paged')));

// Get total count with same WHERE clause
$count_query = "
    SELECT COUNT(DISTINCT d.id)
    FROM $domains_table d
    LEFT JOIN $users_table u ON d.owner_user_id = u.id
    LEFT JOIN $users_table p ON d.provider_id = p.id AND p.user_type = 'SUPPLIER'
    {$where_clause}
";
$total_items = $wpdb->get_var($wpdb->prepare($count_query, ...$where_values ?? []));
$total_pages = ceil($total_items / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// Add LIMIT to query
$query .= " LIMIT $items_per_page OFFSET $offset";

$domains = $wpdb->get_results($wpdb->prepare($query, ...$where_values ?? []));

// Filter domains by status if provided
if (!empty($status_filter)) {
    $filtered_domains = array_filter($domains, function($domain) use ($status_filter) {
        return $domain->status === $status_filter;
    });
    $domains = $filtered_domains;
}

get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php if ($message_type === 'success'): ?>
                            <i class="ph ph-check-circle me-2"></i>
                        <?php elseif ($message_type === 'warning'): ?>
                            <i class="ph ph-warning me-2"></i>
                        <?php else: ?>
                            <i class="ph ph-x-circle me-2"></i>
                        <?php endif; ?>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">Danh sách Tên miền</h4>
                        <div class="d-flex gap-2 align-items-center">
                            <!-- Search functionality -->
                            <div class="d-flex align-items-center me-3">
                                <i class="ph ph-magnifying-glass text-muted me-2 fa-150p"></i>
                                <form method="GET" class="d-flex">
                                    <input type="text" name="search" class="form-control form-control-sm"
                                           placeholder="Tìm kiếm tên miền, chủ sở hữu..."
                                           value="<?php echo esc_attr($search_query); ?>" style="width: 300px;">
                                    <?php if (!empty($search_query)): ?>
                                    <a href="<?php echo home_url('/danh-sach-ten-mien/'); ?>" class="btn btn-sm btn-danger ms-1 d-flex align-items-center" title="Xóa bộ lọc">
                                        <i class="ph ph-x"></i>
                                    </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <?php if (is_inova_admin()): ?>
                            <button id="bulk-renew-btn" class="btn btn-secondary btn-icon-text" style="display: none;" onclick="handleBulkRenewal('domains', 'tên miền', '<?php echo home_url('/them-moi-hoa-don/'); ?>')">
                                <i class="ph ph-clock-clockwise btn-icon-prepend"></i>
                                <span>Gia hạn nhiều tên miền</span>
                            </button>

                            <!-- Add a button fixed on bottom right corner, with plus icon, border dash, link to addnew_partner page -->
                            <a href="<?php echo home_url('/them-moi-domain/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới tên miền" data-bs-toggle="tooltip" data-bs-placement="left">
                                <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
                            </a>
                            <?php else: ?>
                            <!-- Add new domain button - USER ONLY -->
                            <a href="<?php echo home_url('/them-ten-mien/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới tên miền" data-bs-toggle="tooltip" data-bs-placement="left">
                                <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (empty($domains)): ?>
                    <div class="text-center py-5">
                        <?php if (!empty($search_query)): ?>
                            <i class="ph ph-magnifying-glass icon-lg text-muted mb-3" style="font-size: 48px;"></i>
                            <h4>Không tìm thấy tên miền nào</h4>
                            <p class="text-muted">
                                Không có kết quả cho từ khóa "<strong><?php echo esc_html($search_query); ?></strong>"
                            </p>
                            <a href="<?php echo home_url('/danh-sach-ten-mien/'); ?>" class="btn btn-secondary me-2 d-flex align-items-center">
                                <i class="ph ph-arrow-clockwise me-2"></i>Xóa bộ lọc
                            </a>
                        <?php else: ?>
                            <i class="ph ph-globe-stand icon-lg text-muted mb-3" style="font-size: 48px;"></i>
                            <h4>Chưa có tên miền nào</h4>
                            <p class="text-muted">Bắt đầu bằng cách thêm tên miền đầu tiên của bạn</p>
                            <a href="<?php echo home_url('/them-moi-domain/'); ?>" class="btn btn-primary">
                                <i class="ph ph-plus-circle me-2"></i> Thêm mới Tên miền
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
                                        <input type="checkbox" id="select-all-domains" class="form-check form-check-danger">
                                    </th>
                                    <?php endif; ?>
                                    <th style="width: 50px;">STT</th>
                                    <th>Tên miền</th>
                                    <th>Chủ sở hữu</th>
                                    <th>Nhà cung cấp</th>
                                    <th>Ngày hết hạn</th>
                                    <th>Thông tin đăng nhập</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($domains as $index => $domain): ?>
                                <tr>
                                    <?php if (is_inova_admin()): ?>
                                    <td>
                                        <input type="checkbox" class="form-check form-check-danger domain-checkbox" value="<?php echo $domain->id; ?>" data-domain-name="<?php echo esc_attr($domain->domain_name); ?>">
                                    </td>
                                    <?php endif; ?>
                                    <td class="text-center fw-bold text-muted"><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php 
                                            $globe_icon = empty($domain->website_name)?"text-danger":"text-primary";
                                        ?>
                                        <div class="d-flex align-items-center">
                                            <i class="ph ph-globe <?php echo $globe_icon; ?> me-2" style="font-size: 24px;"></i>
                                            <div>
                                                <h6 class="mb-0">
                                                    <a href="<?php echo home_url('/domain/?domain_id=' . $domain->id); ?>" class="text-decoration-none">
                                                        <?php echo esc_html($domain->domain_name); ?>
                                                    </a>
                                                    <?php if ($domain->managed_by_inova == 1): ?>
                                                        <i class="ph ph-check text-success ms-1" title="Được quản lý bởi INOVA" data-bs-toggle="tooltip"></i>
                                                    <?php endif; ?>
                                                </h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($domain->owner_name)): ?>
                                        <a href="<?php echo home_url('/danh-sach-ten-mien/?search=' . $domain->owner_name); ?>" class="text-decoration-none">
                                            <?php echo esc_html($domain->owner_name); ?>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($domain->provider_name) ? esc_html($domain->provider_name) : '<span class="text-muted">--</span>'; ?>
                                    </td>
                                    <td>
                                        <span class="badge border-radius-9 <?php echo strtotime($domain->expiry_date) < time() ? 'bg-danger' : 'bg-success'; ?> text-white">
                                            <?php echo date('d/m/Y', strtotime($domain->expiry_date)); ?>
                                        </span>
                                        <?php
                                        // Calculate days remaining until expiry
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
                                    <td>
                                        <?php if (!empty($domain->management_url)): ?>
                                        <div class="d-flex align-items-center mb-1">
                                            <small class="text-muted me-2">URL:</small>
                                            <a href="<?php echo esc_url($domain->management_url); ?>" target="_blank" class="badge bg-light text-dark text-decoration-none">
                                                <?php echo esc_html(parse_url($domain->management_url, PHP_URL_HOST)); ?>
                                                <i class="ph ph-arrow-square-out ms-1"></i>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($domain->management_username)): ?>
                                        <div class="d-flex align-items-center">
                                            <small class="text-muted me-2">Login:</small>
                                            <span class="badge bg-light text-dark text-truncate credential-badge" style="max-width: 150px;">
                                                <?php echo esc_html($domain->management_username); ?>
                                            </span>
                                            <?php if (!empty($domain->management_password)): ?>
                                            <button class="btn btn-sm btn-icon p-0 ms-1 show-password-btn" data-password="<?php echo esc_attr($domain->management_password); ?>">
                                                <i class="ph ph-eye"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'bg-secondary';
                                        switch ($domain->status) {
                                            case 'ACTIVE':
                                                $status_class = 'bg-success';
                                                $status_text = 'Hoạt động';
                                                break;
                                            case 'PENDING':
                                                $status_class = 'bg-warning';
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
                                            default:
                                                $status_text = $domain->status;
                                        }
                                        ?>
                                        <span class="badge border-radius-9 <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                         <div class="d-flex align-items-center gap-2">
                                              <?php if (is_inova_admin()): ?>
                                              <a href="<?php echo home_url('/edit-domain/?domain_id=' . $domain->id); ?>" class="nav-link text-warning" title="Sửa tên miền">
                                                  <i class="ph ph-pencil-simple btn-icon-prepend fa-150p"></i>
                                              </a>
                                              <?php else: ?>
                                              <!-- Edit button for user - only if they created the domain -->
                                              <?php 
                                              $current_user_id = get_current_user_id();
                                              $can_edit_domain = ($domain->create_by == $current_user_id);
                                              if ($can_edit_domain): 
                                              ?>
                                              <a href="<?php echo home_url('/sua-domain-simple/?domain_id=' . $domain->id); ?>" class="nav-link text-warning" title="Sửa tên miền">
                                                  <i class="ph ph-pencil-simple btn-icon-prepend fa-150p"></i>
                                              </a>
                                              <?php endif; ?>
                                              <?php endif; ?>

                                              <?php if (is_inova_admin()): ?>
                                              <!-- Admin can renew all domains -->
                                              <button type="button" class="btn btn-sm btn-icon p-0 renew-domain-btn"
                                                  data-domain-id="<?php echo $domain->id; ?>"
                                                  data-domain-name="<?php echo esc_attr($domain->domain_name); ?>"
                                                  data-expiry-date="<?php echo esc_attr($domain->expiry_date); ?>"
                                                  title="Gia hạn thêm 1 năm">
                                                  <i class="ph ph-clock-clockwise text-success btn-icon-prepend fa-150p"></i>
                                              </button>
                                              <?php else: ?>
                                              <!-- User can renew if they created it and domain is not managed by INOVA -->
                                              <?php 
                                              $can_renew_domain = ($domain->create_by == $current_user_id && $domain->managed_by_inova == 0);
                                              if ($can_renew_domain): 
                                              ?>
                                              <button type="button" class="btn btn-sm btn-icon p-0 renew-domain-btn"
                                                  data-domain-id="<?php echo $domain->id; ?>"
                                                  data-domain-name="<?php echo esc_attr($domain->domain_name); ?>"
                                                  data-expiry-date="<?php echo esc_attr($domain->expiry_date); ?>"
                                                  title="Gia hạn thêm 1 năm">
                                                  <i class="ph ph-clock-clockwise text-success btn-icon-prepend fa-150p"></i>
                                              </button>
                                              <?php endif; ?>
                                              <?php endif; ?>

                                              <?php if (is_inova_admin()): ?>
                                              <?php if (empty($domain->website_name)): ?>
                                              <a href="<?php echo home_url('/attach-product-to-website/?domain_id=' . $domain->id); ?>" class="nav-link text-warning" title="Tạo website mới">
                                                  <i class="ph ph-globe btn-icon-prepend fa-150p"></i>
                                              </a>
                                              <?php endif; ?>

                                              <button type="button" class="btn btn-sm btn-icon p-0 add-to-cart-btn"
                                                  data-service-type="domain"
                                                  data-service-id="<?php echo $domain->id; ?>"
                                                  title="Thêm vào giỏ hàng">
                                                  <i class="ph ph-shopping-cart text-info fa-150p"></i>
                                              </button>

                                              <button type="button" class="nav-link text-danger border-0 bg-transparent p-0"
                                                      title="Xóa tên miền"
                                                      onclick="confirmDeleteDomain(<?php echo $domain->id; ?>, '<?php echo esc_js($domain->domain_name); ?>', <?php echo (!empty($domain->website_name)) ? 'true' : 'false'; ?>)">
                                                  <i class="ph ph-trash btn-icon-prepend fa-150p"></i>
                                              </button>
                                              <?php else: ?>
                                              <!-- Delete button for user - only if they created the domain -->
                                              <?php 
                                              $can_delete_domain = ($domain->create_by == $current_user_id);
                                              if ($can_delete_domain): 
                                              ?>
                                              <button type="button" class="nav-link text-danger border-0 bg-transparent p-0"
                                                      title="Xóa tên miền"
                                                      onclick="confirmDeleteDomainUser(<?php echo $domain->id; ?>, '<?php echo esc_js($domain->domain_name); ?>', <?php echo (!empty($domain->website_name)) ? 'true' : 'false'; ?>)">
                                                  <i class="ph ph-trash btn-icon-prepend fa-150p"></i>
                                              </button>
                                              <?php endif; ?>
                                              <?php endif; ?>
                                          </div>
                                      </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Hiển thị <?php echo (($current_page - 1) * $items_per_page) + 1; ?>
                            đến <?php echo min($current_page * $items_per_page, $total_items); ?>
                            trong tổng số <?php echo $total_items; ?> tên miền
                        </div>
                        <nav>
                            <ul class="pagination">
                                <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?paged=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                        <i class="ph ph-caret-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);

                                if ($start_page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="?paged=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">1</a></li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?paged=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="?paged=<?php echo $total_pages; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"><?php echo $total_pages; ?></a></li>
                                <?php endif; ?>

                                <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?paged=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                        <i class="ph ph-caret-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for deleting domain -->
<form id="deleteDomainForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_domain_id" id="delete_domain_id" value="">
    <input type="hidden" name="force_delete" id="force_delete" value="0">
</form>

<!-- Delete Confirmation Modal - Admin -->
<div class="modal fade" id="deleteDomainModal" tabindex="-1" aria-labelledby="deleteDomainModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteDomainModalLabel">
                    <i class="ph ph-warning-diamond me-2"></i>
                    Xác nhận xóa domain
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="ph ph-warning me-2"></i>
                    <strong>Cảnh báo:</strong> Hành động này không thể hoàn tác!
                </div>

                <p>Bạn có chắc chắn muốn xóa domain <strong id="domainNameToDelete"></strong>?</p>

                <div id="websiteWarning" style="display: none;">
                    <div class="alert alert-danger" role="alert">
                        <i class="ph ph-exclamation-triangle me-2"></i>
                        <strong>Domain này đang được sử dụng bởi website!</strong>
                        <br>Nếu bạn tiếp tục, website sẽ không còn liên kết với domain nào.
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmForceDelete">
                        <label class="form-check-label text-danger fw-bold" for="confirmForceDelete">
                            Tôi hiểu và muốn xóa domain kể cả khi đang được sử dụng
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ph ph-x me-2"></i>Hủy bỏ
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="ph ph-trash me-2"></i>Xác nhận xóa
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal - User -->
<div class="modal fade" id="deleteDomainUserModal" tabindex="-1" aria-labelledby="deleteDomainUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteDomainUserModalLabel">
                    <i class="ph ph-warning-diamond me-2"></i>
                    Xác nhận xóa domain
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="ph ph-warning me-2"></i>
                    <strong>Cảnh báo:</strong> Hành động này không thể hoàn tác!
                </div>

                <p>Bạn có chắc chắn muốn xóa domain <strong id="domainNameToDeleteUser"></strong>?</p>

                <div id="websiteWarningUser" style="display: none;">
                    <div class="alert alert-danger" role="alert">
                        <i class="ph ph-exclamation-triangle me-2"></i>
                        <strong>Domain này đang được sử dụng bởi website!</strong>
                        <br>Nếu bạn tiếp tục, website sẽ không còn liên kết với domain nào.
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmForceDeleteUser">
                        <label class="form-check-label text-danger fw-bold" for="confirmForceDeleteUser">
                            Tôi hiểu và muốn xóa domain kể cả khi đang được sử dụng
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ph ph-x me-2"></i>Hủy bỏ
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteUserBtn">
                    <i class="ph ph-spinner ph-spin me-2" style="display: none;"></i>
                    <i class="ph ph-trash me-2" id="trashIconUser"></i>Xác nhận xóa
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ===== ADMIN DELETE DOMAIN =====
function confirmDeleteDomain(domainId, domainName, hasWebsite) {
    document.getElementById('delete_domain_id').value = domainId;
    document.getElementById('domainNameToDelete').textContent = domainName;

    const websiteWarning = document.getElementById('websiteWarning');
    const confirmForceDelete = document.getElementById('confirmForceDelete');

    // Convert string 'true'/'false' to boolean
    const hasWebsiteBoolean = hasWebsite === true || hasWebsite === 'true';

    if (hasWebsiteBoolean) {
        websiteWarning.style.display = 'block';
        confirmForceDelete.checked = false;
    } else {
        websiteWarning.style.display = 'none';
    }

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteDomainModal'));
    deleteModal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    const hasWebsite = document.getElementById('websiteWarning').style.display !== 'none';
    const forceDeleteChecked = document.getElementById('confirmForceDelete').checked;

    if (hasWebsite && !forceDeleteChecked) {
        alert('Vui lòng xác nhận để xóa domain đang được sử dụng bởi website.');
        document.getElementById('confirmForceDelete').focus();
        document.getElementById('confirmForceDelete').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    // If domain has website and user confirmed, or domain doesn't have website
    if (hasWebsite && forceDeleteChecked) {
        document.getElementById('force_delete').value = '1';
    }

    // Proceed with deletion
    document.getElementById('deleteDomainForm').submit();
});

// ===== USER DELETE DOMAIN =====
function confirmDeleteDomainUser(domainId, domainName, hasWebsite) {
    document.getElementById('domainIdToDeleteUser').value = domainId;
    document.getElementById('domainNameToDeleteUser').textContent = domainName;

    const websiteWarning = document.getElementById('websiteWarningUser');
    const confirmForceDelete = document.getElementById('confirmForceDeleteUser');

    // Convert string 'true'/'false' to boolean
    const hasWebsiteBoolean = hasWebsite === true || hasWebsite === 'true';

    if (hasWebsiteBoolean) {
        websiteWarning.style.display = 'block';
        confirmForceDelete.checked = false;
    } else {
        websiteWarning.style.display = 'none';
    }

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteDomainUserModal'));
    deleteModal.show();
}

document.getElementById('confirmDeleteUserBtn').addEventListener('click', function() {
    const hasWebsite = document.getElementById('websiteWarningUser').style.display !== 'none';
    const forceDeleteChecked = document.getElementById('confirmForceDeleteUser').checked;

    if (hasWebsite && !forceDeleteChecked) {
        alert('Vui lòng xác nhận để xóa domain đang được sử dụng bởi website.');
        document.getElementById('confirmForceDeleteUser').focus();
        document.getElementById('confirmForceDeleteUser').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    // Get domain ID
    const domainId = document.getElementById('domainIdToDeleteUser').value;
    const forceDelete = (hasWebsite && forceDeleteChecked) ? 1 : 0;

    // Disable button and show spinner
    const btn = document.getElementById('confirmDeleteUserBtn');
    const spinnerIcon = btn.querySelector('.ph-spinner');
    const trashIcon = document.getElementById('trashIconUser');
    btn.disabled = true;
    spinnerIcon.style.display = 'inline-block';
    trashIcon.style.display = 'none';

    // Send AJAX request
    jQuery.ajax({
        url: AJAX.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'delete_domain_user',
            domain_id: domainId,
            force_delete: forceDelete,
            nonce: jQuery('input[name="delete_domain_nonce"]').val()
        },
        success: function(response) {
            if (response.success) {
                // Show success message
                // alert(response.data.message);
                // Reload page
                location.reload();
            } else {
                // Show error message
                alert(response.data.message || 'Có lỗi xảy ra');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            alert('Đã có lỗi xảy ra khi xóa domain.');
        },
        complete: function() {
            // Re-enable button
            btn.disabled = false;
            spinnerIcon.style.display = 'none';
            trashIcon.style.display = 'inline-block';
        }
    });
});
</script>

<!-- Hidden input for user delete domain ID -->
<input type="hidden" id="domainIdToDeleteUser" value="">

<!-- Hidden nonce field for AJAX -->
<input type="hidden" name="delete_domain_nonce" value="<?php echo wp_create_nonce('delete_domain_nonce'); ?>"

<!--
    ===================================================================
    DOMAIN RENEWAL FUNCTIONALITY
    ===================================================================
    All domain renewal JavaScript code has been moved to:
    assets/js/custom.js (starting at line ~892)

    Includes:
    - Single domain renewal (+1 year): Click renew icon in actions column
    - Bulk domain renewal: Select multiple domains and renew together
    - Helper functions: formatDate(), addYears()

    Backend AJAX handler:
    functions.php: renew_domain_one_year (line ~1709)
-->

<?php
get_footer();
?>