<?php
/* 
    Template Name: Maintenance Package List
*/

global $wpdb;
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
$users_table = $wpdb->prefix . 'im_users';
$websites_table = $wpdb->prefix . 'im_websites';

// Handle delete request
$message = '';
$message_type = '';

if (isset($_POST['delete_maintenance_id']) && !empty($_POST['delete_maintenance_id'])) {
    $maintenance_id = intval($_POST['delete_maintenance_id']);

    // Check if maintenance package exists
    $maintenance = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $maintenance_table WHERE id = %d
    ", $maintenance_id));

    if ($maintenance) {
        // Check if maintenance is being used by any websites
        $websites_using_maintenance = $wpdb->get_results($wpdb->prepare("
            SELECT id, name FROM $websites_table WHERE maintenance_package_id = %d
        ", $maintenance_id));

        $force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] == '1';

        if ($websites_using_maintenance && !$force_delete) {
            $message = "Không thể xóa gói bảo trì này vì đang được sử dụng bởi " . count($websites_using_maintenance) . " website(s). Sử dụng 'Xóa bắt buộc' nếu bạn muốn tiếp tục.";
            $message_type = 'warning';
        } else {
            // If force delete, update maintenance_package_id of websites to NULL
            if ($websites_using_maintenance && $force_delete) {
                $wpdb->update(
                    $websites_table,
                    array('maintenance_package_id' => null),
                    array('maintenance_package_id' => $maintenance_id),
                    array('%s'),
                    array('%d')
                );
            }

            // Delete the maintenance package
            $deleted = $wpdb->delete(
                $maintenance_table,
                array('id' => $maintenance_id),
                array('%d')
            );

            if ($deleted) {
                $order_code = !empty($maintenance->order_code) ? $maintenance->order_code : 'MAINT-' . $maintenance->id;
                $message = "Gói bảo trì '{$order_code}' đã được xóa thành công!";
                $message_type = 'success';
            } else {
                $message = "Có lỗi xảy ra khi xóa gói bảo trì.";
                $message_type = 'danger';
            }
        }
    } else {
        $message = "Gói bảo trì không tồn tại.";
        $message_type = 'danger';
    }
}

// Get search parameter
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Build WHERE clause
$where_conditions = array();
$permission_where = get_user_permission_where_clause('m', 'owner_user_id', 'partner_id');
if (!empty($permission_where)) {
    $where_conditions[] = ltrim($permission_where, ' AND');
}

if (!empty($search_query)) {
    $search_like = '%' . $wpdb->esc_like($search_query) . '%';
    $where_conditions[] = $wpdb->prepare("(w.name LIKE %s OR u.name LIKE %s OR u.user_code LIKE %s OR m.order_code LIKE %s)", $search_like, $search_like, $search_like, $search_like);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

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
    {$where_clause}
    GROUP BY
        m.id
    ORDER BY
        m.expiry_date ASC
";

// Pagination settings
$items_per_page = 10;
$current_page = max(1, intval(get_query_var('paged')));

// Get total count with same WHERE clause
$count_query = "
    SELECT COUNT(DISTINCT m.id)
    FROM $maintenance_table m
    LEFT JOIN $users_table u ON m.owner_user_id = u.id
    LEFT JOIN $websites_table w ON w.maintenance_package_id = m.id
    {$where_clause}
";
$total_items = $wpdb->get_var($count_query);
$total_pages = ceil($total_items / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// Add LIMIT to query
$query .= " LIMIT $items_per_page OFFSET $offset";

$maintenance_packages = $wpdb->get_results($query);

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
                         <h4 class="card-title">Danh sách Gói Bảo Trì</h4>
                         <div class="d-flex gap-2 align-items-center">
                             <!-- Search functionality -->
                             <div class="d-flex align-items-center me-3">
                                 <i class="ph ph-magnifying-glass text-muted me-2 fa-150p"></i>
                                 <form method="GET" class="d-flex">
                                     <input type="text" name="search" class="form-control form-control-sm"
                                            placeholder="Tìm kiếm gói bảo trì, website, chủ sở hữu..."
                                            value="<?php echo esc_attr($search_query); ?>" style="width: 300px;">
                                     <?php if (!empty($search_query)): ?>
                                     <a href="<?php echo home_url('/danh-sach-bao-tri/'); ?>" class="btn btn-sm btn-danger ms-1 d-flex align-items-center" title="Xóa bộ lọc">
                                         <i class="ph ph-x"></i>
                                     </a>
                                     <?php endif; ?>
                                 </form>
                             </div>
                             
                             <?php if (is_inova_admin()): ?>
                             <button id="bulk-renew-btn" class="btn btn-secondary btn-icon-text" style="display: none;" onclick="handleBulkRenewal('maintenances', 'gói bảo trì', '<?php echo home_url('/them-moi-hoa-don/'); ?>')">
                                 <i class="ph ph-arrow-clockwise btn-icon-prepend"></i>
                                 <span>Gia hạn nhiều gói bảo trì</span>
                             </button>
                             <?php endif; ?>
                             
                             <?php if (is_inova_admin()): ?>
                             <a href="<?php echo home_url('/them-goi-bao-tri/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới gói bảo trì">
                                 <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
                             </a>
                             <?php endif; ?>
                         </div>
                     </div>
                    
                    <?php if (empty($maintenance_packages)): ?>
                     <div class="text-center py-5">
                         <?php if (!empty($search_query)): ?>
                             <i class="ph ph-magnifying-glass icon-lg text-muted mb-3" style="font-size: 48px;"></i>
                             <h4>Không tìm thấy gói bảo trì nào</h4>
                             <p class="text-muted">
                                 Không có kết quả cho từ khóa "<strong><?php echo esc_html($search_query); ?></strong>"
                             </p>
                             <a href="<?php echo home_url('/danh-sach-bao-tri/'); ?>" class="btn btn-secondary me-2 d-flex align-items-center">
                                 <i class="ph ph-arrow-clockwise me-2"></i>Xóa bộ lọc
                             </a>
                         <?php else: ?>
                             <i class="ph ph-wrench icon-lg text-muted mb-3" style="font-size: 48px;"></i>
                             <h4>Chưa có gói bảo trì nào</h4>
                             <p class="text-muted">Bắt đầu bằng cách thêm gói bảo trì đầu tiên của bạn</p>
                             <?php if (is_inova_admin()): ?>
                             <a href="<?php echo home_url('/them-goi-bao-tri/'); ?>" class="btn btn-primary">
                                 <i class="ph ph-plus-circle me-2"></i> Thêm mới Gói Bảo Trì
                             </a>
                             <?php endif; ?>
                         <?php endif; ?>
                     </div>
                     <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <?php if (is_inova_admin()): ?>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all-maintenances" class="form-check form-check-danger">
                                    </th>
                                    <?php endif; ?>
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
                                    <?php if (is_inova_admin()): ?>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check form-check-danger maintenance-checkbox"
                                               value="<?php echo $package->id; ?>"
                                               data-owner-id="<?php echo $package->owner_user_id; ?>">
                                    </td>
                                    <?php endif; ?>
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
                                                <a href="<?php echo home_url('/danh-sach-bao-tri/?search=' . $package->owner_name); ?>" class="text-decoration-none">
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
                                        $status_text = 'Không xác định';
                                        switch ($package->status) {
                                            case 'NEW':
                                                $status_class = 'bg-light-warning text-warning border-warning';
                                                $status_text = 'Chờ thanh toán';
                                                break;
                                            case 'ACTIVE':
                                                $status_class = 'bg-success';
                                                $status_text = 'Đang hoạt động';
                                                break;
                                            case 'EXPIRED':
                                                $status_class = 'bg-danger';
                                                $status_text = 'Hết hạn';
                                                break;
                                            case 'SUSPENDED':
                                                $status_class = 'bg-danger';
                                                $status_text = 'Bị tạm ngưng';
                                                break;
                                            case 'DELETED':
                                                $status_class = 'bg-secondary';
                                                $status_text = 'Đã xóa';
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
                                        <?php if (is_inova_admin()): ?>
                                        <div class="d-flex align-items-center">
                                            <a href="<?php echo home_url('/sua-goi-bao-tri/?maintenance_id=' . $package->id); ?>" class="nav-link text-warning me-2" title="Sửa gói bảo trì">
                                                <i class="ph ph-pencil-simple btn-icon-prepend fa-150p"></i>
                                            </a>

                                            <?php if (empty($package->websites_names)): ?>
                                            <a href="<?php echo home_url('/attach-product-to-website/?maintenance_id=' . $package->id); ?>" class="nav-link text-warning me-2" title="Tạo website mới">
                                                <i class="ph ph-globe btn-icon-prepend fa-150p"></i>
                                            </a>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-sm btn-icon p-0 me-2 add-to-cart-btn"
                                                data-service-type="maintenance"
                                                data-service-id="<?php echo $package->id; ?>"
                                                title="Thêm vào giỏ hàng">
                                                <i class="ph ph-shopping-cart text-info fa-150p"></i>
                                            </button>

                                            <button type="button" class="nav-link text-danger border-0 bg-transparent p-0"
                                                    title="Xóa gói bảo trì"
                                                    onclick="confirmDeleteMaintenance(<?php echo $package->id; ?>, '<?php echo esc_js(!empty($package->order_code) ? $package->order_code : 'MAINT-' . $package->id); ?>', <?php echo (!empty($package->websites_names)) ? 'true' : 'false'; ?>)">
                                                <i class="ph ph-trash btn-icon-prepend fa-150p"></i>
                                            </button>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">--</span>
                                        <?php endif; ?>
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

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                       <div class="text-muted">
                           Hiển thị <?php echo (($current_page - 1) * $items_per_page) + 1; ?>
                           đến <?php echo min($current_page * $items_per_page, $total_items); ?>
                           trong tổng số <?php echo $total_items; ?> gói bảo trì
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
                    
                    <!-- Hidden form for deleting maintenance -->
<form id="deleteMaintenanceForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_maintenance_id" id="delete_maintenance_id" value="">
    <input type="hidden" name="force_delete" id="force_delete" value="0">
</form>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteMaintenanceModal" tabindex="-1" aria-labelledby="deleteMaintenanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteMaintenanceModalLabel">
                    <i class="ph ph-warning-diamond me-2"></i>
                    Xác nhận xóa gói bảo trì
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="ph ph-warning me-2"></i>
                    <strong>Cảnh báo:</strong> Hành động này không thể hoàn tác!
                </div>

                <p>Bạn có chắc chắn muốn xóa gói bảo trì <strong id="maintenanceCodeToDelete"></strong>?</p>

                <div id="websiteWarning" style="display: none;">
                    <div class="alert alert-danger" role="alert">
                        <i class="ph ph-exclamation-triangle me-2"></i>
                        <strong>Gói bảo trì này đang được sử dụng bởi website!</strong>
                        <br>Nếu bạn tiếp tục, website sẽ không còn liên kết với gói bảo trì nào.
                    </div>

                    <div class="form-check d-flex align-items-center">
                        <input class="form-check-input" type="checkbox" id="confirmForceDelete">
                        <label class="form-check-label text-danger fw-bold" for="confirmForceDelete">
                            Tôi hiểu và muốn xóa gói bảo trì kể cả khi đang được sử dụng
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

<script>
function confirmDeleteMaintenance(maintenanceId, maintenanceCode, hasWebsite) {
    document.getElementById('delete_maintenance_id').value = maintenanceId;
    document.getElementById('maintenanceCodeToDelete').textContent = maintenanceCode;

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

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteMaintenanceModal'));
    deleteModal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    const hasWebsite = document.getElementById('websiteWarning').style.display !== 'none';
    const forceDeleteChecked = document.getElementById('confirmForceDelete').checked;

    if (hasWebsite && !forceDeleteChecked) {
        alert('Vui lòng xác nhận để xóa gói bảo trì đang được sử dụng bởi website.');
        document.getElementById('confirmForceDelete').focus();
        document.getElementById('confirmForceDelete').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    // If maintenance has website and user confirmed, or maintenance doesn't have website
    if (hasWebsite && forceDeleteChecked) {
        document.getElementById('force_delete').value = '1';
    }

    // Proceed with deletion
    document.getElementById('deleteMaintenanceForm').submit();
});
</script>

<!-- Bulk renewal functionality is now handled by custom.js -->

<?php
get_footer();
?>