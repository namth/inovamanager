<?php
/* 
    Template Name: Maintenance Package List
*/

global $wpdb;
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
$users_table = $wpdb->prefix . 'im_users';
$websites_table = $wpdb->prefix . 'im_websites';

// Get all maintenance packages with related data
$query = "
    SELECT 
        m.*,
        u.name AS owner_name,
        u.user_code AS owner_code,
        GROUP_CONCAT(DISTINCT w.name SEPARATOR ', ') AS websites_names,
        GROUP_CONCAT(DISTINCT w.id SEPARATOR ',') AS websites_ids
    FROM 
        $maintenance_table m
    LEFT JOIN 
        $users_table u ON m.owner_user_id = u.id
    LEFT JOIN 
        $websites_table w ON w.maintenance_package_id = m.id
    GROUP BY 
        m.id
    ORDER BY 
        m.expiry_date ASC
";

$maintenance_packages = $wpdb->get_results($query);

// Filter maintenance packages by status if provided
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
if (!empty($status_filter)) {
    $filtered_packages = array_filter($maintenance_packages, function($package) use ($status_filter) {
        return $package->status === $status_filter;
    });
    $maintenance_packages = $filtered_packages;
}

get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">Danh sách Gói Bảo Trì</h4>
                        <div class="d-flex gap-2 align-items-center">
                            <button id="bulk-renew-btn" class="btn btn-secondary btn-icon-text" style="display: none;" onclick="handleBulkRenewal('maintenances', 'gói bảo trì', '<?php echo home_url('/them-moi-hoa-don/'); ?>')">
                                <i class="ph ph-arrow-clockwise btn-icon-prepend"></i>
                                <span>Gia hạn nhiều gói bảo trì</span>
                            </button>
                            <div class="d-flex align-items-center">
                                <i class="ph ph-funnel text-muted me-2 fa-150p"></i>
                                <select class="form-select form-select-sm w180" onchange="window.location.href=this.value">
                                    <option value="<?php echo home_url('/danh-sach-bao-tri/'); ?>" <?php echo empty($status_filter) ? 'selected' : ''; ?>>
                                        Tất cả trạng thái
                                    </option>
                                    <option value="<?php echo home_url('/danh-sach-bao-tri/?status=ACTIVE'); ?>" <?php echo $status_filter === 'ACTIVE' ? 'selected' : ''; ?>>
                                        Đang hoạt động
                                    </option>
                                    <option value="<?php echo home_url('/danh-sach-bao-tri/?status=PENDING'); ?>" <?php echo $status_filter === 'PENDING' ? 'selected' : ''; ?>>
                                        Chờ xử lý
                                    </option>
                                    <option value="<?php echo home_url('/danh-sach-bao-tri/?status=EXPIRED'); ?>" <?php echo $status_filter === 'EXPIRED' ? 'selected' : ''; ?>>
                                        Hết hạn
                                    </option>
                                    <option value="<?php echo home_url('/danh-sach-bao-tri/?status=CANCELLED'); ?>" <?php echo $status_filter === 'CANCELLED' ? 'selected' : ''; ?>>
                                        Đã hủy
                                    </option>
                                </select>
                            </div>
                            <a href="<?php echo home_url('/them-goi-bao-tri/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới gói bảo trì">
                                <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($maintenance_packages)): ?>
                    <div class="text-center py-5">
                        <i class="ph ph-wrench icon-lg text-muted mb-3" style="font-size: 48px;"></i>
                        <h4>Chưa có gói bảo trì nào</h4>
                        <p class="text-muted">Bắt đầu bằng cách thêm gói bảo trì đầu tiên của bạn</p>
                        <a href="<?php echo home_url('/them-goi-bao-tri/'); ?>" class="btn btn-primary">
                            <i class="ph ph-plus-circle me-2"></i> Thêm mới Gói Bảo Trì
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all-maintenances" class="form-check form-check-danger">
                                    </th>
                                    <th style="width: 50px;">STT</th>
                                    <th>Mã gói bảo trì</th>
                                    <th>Website đang bảo trì</th>
                                    <th>Chủ sở hữu</th>
                                    <th>Chu kỳ thanh toán</th>
                                    <th>Ngày hết hạn</th>
                                    <th>Chi phí</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (!empty($maintenance_packages)) {
                                    $stt = 1;
                                    foreach ($maintenance_packages as $package): 
                                ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check form-check-danger maintenance-checkbox" 
                                               value="<?php echo $package->id; ?>" 
                                               data-owner-id="<?php echo $package->owner_user_id; ?>">
                                    </td>
                                    <td class="text-center fw-bold text-muted"><?php echo $stt++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="ph ph-wrench text-warning me-2" style="font-size: 24px;"></i>
                                            <a href="<?php echo home_url('/maintenance/?maintenance_id=' . $package->id); ?>" class="nav-link text-decoration-none">
                                                <h6 class="mb-0"><?php echo !empty($package->order_code) ? esc_html($package->order_code) : 'MAINT-' . $package->id; ?></h6>
                                                <small class="text-muted">Gói bảo trì website</small>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($package->websites_names)): 
                                            $website_names = explode(', ', $package->websites_names);
                                            $website_ids = explode(',', $package->websites_ids);
                                            foreach ($website_names as $index => $website_name): ?>
                                                <a href="<?php echo home_url('/edit-website/?website_id=' . $website_ids[$index]); ?>" 
                                                   class="text-decoration-none fw-bold text-primary d-block">
                                                    <?php echo esc_html($website_name); ?>
                                                    <i class="fas fa-external-link-alt ms-1" style="font-size: 0.8em;"></i>
                                                </a>
                                            <?php endforeach;
                                        else: ?>
                                            <span class="text-muted">Chưa có website</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold">
                                            <?php if (!empty($package->owner_name)): ?>
                                                <a href="<?php echo home_url('/user-detail/?user_id=' . $package->owner_user_id); ?>" class="text-decoration-none">
                                                    <?php echo esc_html($package->owner_code . ' - ' . $package->owner_name); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">--</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-info">
                                            <?php 
                                            // Convert billing cycle months to Vietnamese text
                                            $billing_cycle_text = '';
                                            switch ($package->billing_cycle_months) {
                                                case 1:
                                                    $billing_cycle_text = '1 tháng';
                                                    break;
                                                case 3:
                                                    $billing_cycle_text = '3 tháng';
                                                    break;
                                                case 6:
                                                    $billing_cycle_text = '6 tháng';
                                                    break;
                                                case 12:
                                                    $billing_cycle_text = '12 tháng';
                                                    break;
                                                default:
                                                    $billing_cycle_text = $package->billing_cycle_months . ' tháng';
                                            }
                                            echo esc_html($billing_cycle_text);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge border-radius-9 <?php echo strtotime($package->expiry_date) < time() ? 'bg-danger' : 'bg-success'; ?> text-white">
                                            <?php echo date('d/m/Y', strtotime($package->expiry_date)); ?>
                                        </span>
                                        <?php
                                        // Calculate and display date range (from-to)
                                        $expiry_date_obj = new DateTime($package->expiry_date);
                                        $start_date_obj = new DateTime($package->renew_date);
                                        $start_date_formatted = $start_date_obj->format('d/m/Y');
                                        $end_date_formatted = $expiry_date_obj->format('d/m/Y');
                                        
                                        // Calculate days remaining until expiry
                                        $now = new DateTime();
                                        $expiry = new DateTime($package->expiry_date);
                                        $days_remaining = $now->diff($expiry)->days;
                                        $is_expired = $now > $expiry;
                                        
                                        if ($is_expired): ?>
                                            <span class="d-block mt-1 text-danger">Quá hạn <?php echo $days_remaining; ?> ngày</span>
                                        <?php elseif ($days_remaining <= 30): ?>
                                            <span class="d-block mt-1 text-warning">Còn <?php echo $days_remaining; ?> ngày</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">
                                            <?php echo number_format($package->actual_revenue, 0, ',', '.'); ?> VND
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'bg-secondary';
                                        switch ($package->status) {
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
                                                $status_text = $package->status;
                                        }
                                        ?>
                                        <span class="badge border-radius-9 <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <a href="<?php echo home_url('/sua-goi-bao-tri/?maintenance_id=' . $package->id); ?>" class="nav-link text-warning me-2" title="Sửa gói bảo trì">
                                                <i class="ph ph-pencil-simple btn-icon-prepend fa-150p"></i>
                                            </a>
                                            
                                            <?php if (empty($package->websites_names)): ?>
                                            <a href="<?php echo home_url('/attach-product-to-website/?maintenance_id=' . $package->id); ?>" class="nav-link text-warning me-2" title="Tạo website mới">
                                                <i class="ph ph-globe btn-icon-prepend fa-150p"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <a href="<?php echo home_url('/them-moi-hoa-don/?maintenance_id=' . $package->id); ?>" class="nav-link text-warning me-2" title="Tạo hóa đơn gia hạn">
                                                <i class="ph ph-receipt btn-icon-prepend fa-150p"></i>
                                            </a>
                                            
                                            <a href="<?php echo home_url('/xoa-goi-bao-tri/?id=' . $package->id); ?>" class="nav-link text-danger" title="Xóa gói bảo trì" onclick="return confirm('Bạn có chắc chắn muốn xóa gói bảo trì này?');">
                                                <i class="ph ph-trash btn-icon-prepend fa-150p"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach; 
                                } else {
                                ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2"></i>
                                            <div>Không có gói bảo trì nào được tìm thấy</div>
                                        </div>
                                    </td>
                                </tr>
                                <?php } ?>
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