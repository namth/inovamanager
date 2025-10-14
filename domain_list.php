<?php
/* 
    Template Name: Domain List
*/

global $wpdb;
$domains_table = $wpdb->prefix . 'im_domains';
$users_table = $wpdb->prefix . 'im_users';
$product_catalog_table = $wpdb->prefix . 'im_product_catalog';
$websites_table = $wpdb->prefix . 'im_websites';

// Get all domains with related data
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
    ORDER BY 
        d.expiry_date ASC
";

$domains = $wpdb->get_results($query);

// Filter domains by status if provided
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">Danh sách Tên miền</h4>
                        <div class="d-flex gap-2 align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="ph ph-funnel text-muted me-2 fa-150p"></i>
                                <select class="form-select form-select-sm w180" onchange="window.location.href=this.value">
                                    <option value="<?php echo home_url('/danh-sach-ten-mien/'); ?>" <?php echo empty($status_filter) ? 'selected' : ''; ?>>
                                        Tất cả trạng thái
                                    </option>
                                    <option value="<?php echo home_url('/danh-sach-ten-mien/?status=ACTIVE'); ?>" <?php echo $status_filter === 'ACTIVE' ? 'selected' : ''; ?>>
                                        Đang hoạt động
                                    </option>
                                    <option value="<?php echo home_url('/danh-sach-ten-mien/?status=EXPIRED'); ?>" <?php echo $status_filter === 'EXPIRED' ? 'selected' : ''; ?>>
                                        Hết hạn
                                    </option>
                                </select>
                            </div>
                            <button id="bulk-renew-btn" class="btn btn-secondary btn-icon-text" style="display: none;" onclick="handleBulkRenewal('domains', 'tên miền', '<?php echo home_url('/them-moi-hoa-don/'); ?>')">
                                <i class="ph ph-clock-clockwise btn-icon-prepend"></i>
                                <span>Gia hạn nhiều tên miền</span>
                            </button>
    
                            <!-- Add a button fixed on bottom right corner, with plus icon, border dash, link to addnew_partner page -->
                            <a href="<?php echo home_url('/them-moi-domain/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới tên miền" data-bs-toggle="tooltip" data-bs-placement="left">
                                <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($domains)): ?>
                    <div class="text-center py-5">
                        <i class="ph ph-globe-stand icon-lg text-muted mb-3" style="font-size: 48px;"></i>
                        <h4>Chưa có tên miền nào</h4>
                        <p class="text-muted">Bắt đầu bằng cách thêm tên miền đầu tiên của bạn</p>
                        <a href="<?php echo home_url('/them-moi-domain/'); ?>" class="btn btn-danger">
                            <i class="ph ph-plus-circle me-2"></i> Thêm mới Tên miền
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all-domains" class="form-check form-check-danger">
                                    </th>
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
                                    <td>
                                        <input type="checkbox" class="form-check form-check-danger domain-checkbox" value="<?php echo $domain->id; ?>" data-domain-name="<?php echo esc_attr($domain->domain_name); ?>">
                                    </td>
                                    <td class="text-center fw-bold text-muted"><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="ph ph-globe text-primary me-2" style="font-size: 24px;"></i>
                                            <div>
                                                <h6 class="mb-0">
                                                    <a href="<?php echo home_url('/domain/?domain_id=' . $domain->id); ?>" class="text-decoration-none">
                                                        <?php echo esc_html($domain->domain_name); ?>
                                                    </a>
                                                </h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($domain->owner_name)): ?>
                                        <a href="<?php echo home_url('/user-detail/?user_id=' . $domain->owner_user_id); ?>" class="text-decoration-none">
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
                                        <div class="d-flex align-items-center">
                                            <a href="<?php echo home_url('/edit-domain/?domain_id=' . $domain->id); ?>" class="nav-link text-warning me-2" title="Sửa tên miền">
                                                <i class="ph ph-pencil-simple btn-icon-prepend fa-150p"></i>
                                            </a>
                                            
                                            <?php if (empty($domain->website_name)): ?>
                                            <a href="<?php echo home_url('/attach-product-to-website/?domain_id=' . $domain->id); ?>" class="nav-link text-warning me-2" title="Tạo website mới">
                                                <i class="ph ph-globe btn-icon-prepend fa-150p"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <a href="<?php echo home_url('/them-moi-hoa-don/?domain_id=' . $domain->id); ?>" class="nav-link text-warning me-2" title="Tạo hóa đơn gia hạn">
                                                <i class="ph ph-receipt btn-icon-prepend fa-150p"></i>
                                            </a>
                                            
                                            <a href="<?php echo home_url('/xoa-domain/?id=' . $domain->id); ?>" class="nav-link text-danger" title="Xóa tên miền" onclick="return confirm('Bạn có chắc chắn muốn xóa tên miền này?');">
                                                <i class="ph ph-trash btn-icon-prepend fa-150p"></i>
                                            </a>
                                        </div>
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