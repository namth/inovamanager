<?php
/* 
    Template Name: Hosting List
*/

global $wpdb;
$hostings_table = $wpdb->prefix . 'im_hostings';
$users_table = $wpdb->prefix . 'im_users';
$product_catalog_table = $wpdb->prefix . 'im_product_catalog';
$websites_table = $wpdb->prefix . 'im_websites';

// Handle delete request
$message = '';
$message_type = '';

if (isset($_POST['delete_hosting_id']) && !empty($_POST['delete_hosting_id'])) {
    $hosting_id = intval($_POST['delete_hosting_id']);
    
    // Check if hosting exists
    $hosting = $wpdb->get_row($wpdb->prepare("
        SELECT h.*, pc.name as product_name 
        FROM $hostings_table h
        LEFT JOIN $product_catalog_table pc ON h.product_catalog_id = pc.id
        WHERE h.id = %d
    ", $hosting_id));
    
    if ($hosting) {
        // Check if hosting is being used by any websites
        $websites_using_hosting = $wpdb->get_results($wpdb->prepare("
            SELECT id, name FROM $websites_table WHERE hosting_id = %d
        ", $hosting_id));
        
        $force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] == '1';
        
        if ($websites_using_hosting && !$force_delete) {
            $message = "Không thể xóa hosting này vì đang được sử dụng bởi " . count($websites_using_hosting) . " website(s). Sử dụng 'Xóa bắt buộc' nếu bạn muốn tiếp tục.";
            $message_type = 'warning';
        } else {
            // If force delete, update hosting_id of websites to NULL
            if ($websites_using_hosting && $force_delete) {
                $wpdb->update(
                    $websites_table,
                    array('hosting_id' => null),
                    array('hosting_id' => $hosting_id),
                    array('%s'),
                    array('%d')
                );
            }
            
            // Delete the hosting
            $deleted = $wpdb->delete(
                $hostings_table,
                array('id' => $hosting_id),
                array('%d')
            );
            
            if ($deleted) {
                $hosting_code = !empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id;
                $message = "Hosting '{$hosting_code}' đã được xóa thành công!";
                $message_type = 'success';
            } else {
                $message = "Có lỗi xảy ra khi xóa hosting.";
                $message_type = 'danger';
            }
        }
    } else {
        $message = "Hosting không tồn tại.";
        $message_type = 'danger';
    }
}

// Get all hostings with related data
$query = "
    SELECT 
        h.*,
        u.name AS owner_name,
        u.user_code AS owner_code,
        p.name AS provider_name,
        pc.name AS product_name,
        pc.service_type,
        pc.renew_price AS product_price,
        IFNULL(w.name, '') AS website_name,
        IFNULL(w.id, 0) AS website_id
    FROM 
        $hostings_table h
    LEFT JOIN 
        $users_table u ON h.owner_user_id = u.id
    LEFT JOIN 
        $users_table p ON h.provider_id = p.id AND p.user_type = 'SUPPLIER'
    LEFT JOIN 
        $product_catalog_table pc ON h.product_catalog_id = pc.id
    LEFT JOIN 
        $websites_table w ON w.hosting_id = h.id
    ORDER BY 
        h.expiry_date ASC
";

$hostings = $wpdb->get_results($query);

// Filter hostings by status if provided
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
if (!empty($status_filter)) {
    $filtered_hostings = array_filter($hostings, function($hosting) use ($status_filter) {
        return $hosting->status === $status_filter;
    });
    $hostings = $filtered_hostings;
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
                        <i class="ph ph-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'warning' ? 'warning' : 'x-circle'); ?> me-2"></i>
                        <?php echo esc_html($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="ph ph-check-circle me-2"></i>
                        Hosting đã được xóa thành công!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">Danh sách Hosting</h4>
                        <div class="d-flex gap-2 align-items-center">
                            <button id="bulk-renew-btn" class="btn btn-secondary btn-icon-text" style="display: none;" onclick="handleBulkRenewal('hostings', 'hosting', '<?php echo home_url('/them-moi-hoa-don/'); ?>')">
                                <i class="ph ph-arrow-clockwise btn-icon-prepend"></i>
                                <span>Gia hạn nhiều hosting</span>
                            </button>
                            <div class="d-flex align-items-center">
                                <i class="ph ph-funnel text-muted me-2 fa-150p"></i>
                                <select class="form-select form-select-sm w180" onchange="window.location.href=this.value">
                                    <option value="<?php echo home_url('/danh-sach-hosting/'); ?>" <?php echo empty($status_filter) ? 'selected' : ''; ?>>
                                        Tất cả trạng thái
                                    </option>
                                    <option value="<?php echo home_url('/danh-sach-hosting/?status=ACTIVE'); ?>" <?php echo $status_filter === 'ACTIVE' ? 'selected' : ''; ?>>
                                        Đang hoạt động
                                    </option>
                                    <option value="<?php echo home_url('/danh-sach-hosting/?status=EXPIRED'); ?>" <?php echo $status_filter === 'EXPIRED' ? 'selected' : ''; ?>>
                                        Hết hạn
                                    </option>
                                </select>
                            </div>
                            <a href="<?php echo home_url('/them-moi-hosting/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới hosting" data-bs-toggle="tooltip" data-bs-placement="left">
                                <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($hostings)): ?>
                    <div class="text-center py-5">
                        <i class="ph ph-cloud-slash icon-lg text-muted mb-3" style="font-size: 48px;"></i>
                        <h4>Chưa có hosting nào</h4>
                        <p class="text-muted">Bắt đầu bằng cách thêm hosting đầu tiên của bạn</p>
                        <a href="<?php echo home_url('/them-moi-hosting/'); ?>" class="btn btn-primary">
                            <i class="ph ph-plus-circle me-2"></i> Thêm mới Hosting
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all-hostings" class="form-check form-check-danger">
                                    </th>
                                    <th style="width: 50px;">STT</th>
                                    <th>Mã hosting</th>
                                    <th>Website</th>
                                    <th>Chủ sở hữu</th>
                                    <th>Ngày hết hạn</th>
                                    <th>Giá tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (!empty($hostings)) {
                                    $stt = 1;
                                    foreach ($hostings as $hosting): 
                                ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check form-check-danger hosting-checkbox" 
                                               value="<?php echo $hosting->id; ?>" 
                                               data-owner-id="<?php echo $hosting->owner_user_id; ?>">
                                    </td>
                                    <td class="text-center fw-bold text-muted"><?php echo $stt++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="ph ph-cloud text-info me-2" style="font-size: 24px;"></i>
                                            <a href="<?php echo home_url('/hosting/?hosting_id=' . $hosting->id); ?>" class="nav-link text-decoration-none">
                                                <h6 class="mb-0"><?php echo !empty($hosting->hosting_code) ? esc_html($hosting->hosting_code) : 'HOST-' . $hosting->id; ?></h6>
                                                <small class="text-muted"><?php echo esc_html($hosting->product_name); ?></small>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($hosting->website_name)): ?>
                                            <a href="<?php echo home_url('/edit-website/?website_id=' . $hosting->website_id); ?>" 
                                               class="text-decoration-none fw-bold text-primary">
                                                <?php echo esc_html($hosting->website_name); ?>
                                                <i class="fas fa-external-link-alt ms-1" style="font-size: 0.8em;"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Chưa có website</span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($hosting->ip_address)): ?>
                                            <div class="mt-1">
                                                <small class="text-danger fw-bold">
                                                    <i class="ph ph-globe me-1"></i>
                                                    <?php echo esc_html($hosting->ip_address); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold">
                                            <?php if (!empty($hosting->owner_name)): ?>
                                                <a href="<?php echo home_url('/user-detail/?user_id=' . $hosting->owner_user_id); ?>" class="text-decoration-none">
                                                    <?php echo esc_html($hosting->owner_name); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">--</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge border-radius-9 <?php echo strtotime($hosting->expiry_date) < time() ? 'bg-danger' : 'bg-success'; ?> text-white">
                                            <?php echo date('d/m/Y', strtotime($hosting->expiry_date)); ?>
                                        </span>
                                        <?php
                                        // Calculate days remaining until expiry
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
                                    <td>
                                        <span class="fw-bold text-success">
                                            <?php echo number_format($hosting->product_price, 0, ',', '.'); ?> VND
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'bg-secondary';
                                        switch ($hosting->status) {
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
                                                $status_text = $hosting->status;
                                        }
                                        ?>
                                        <span class="badge border-radius-9 <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <a href="<?php echo home_url('/sua-hosting/?hosting_id=' . $hosting->id); ?>" class="nav-link text-warning me-2" title="Sửa hosting">
                                                <i class="ph ph-pencil-simple btn-icon-prepend fa-150p"></i>
                                            </a>
                                            
                                            <?php if (empty($hosting->website_name)): ?>
                                            <a href="<?php echo home_url('/attach-product-to-website/?hosting_id=' . $hosting->id); ?>" class="nav-link text-warning me-2" title="Tạo website mới">
                                                <i class="ph ph-globe btn-icon-prepend fa-150p"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <a href="<?php echo home_url('/them-moi-hoa-don/?hosting_id=' . $hosting->id); ?>" class="nav-link text-warning me-2" title="Tạo hóa đơn gia hạn">
                                                <i class="ph ph-receipt btn-icon-prepend fa-150p"></i>
                                            </a>
                                            
                                            <button type="button" class="nav-link text-danger border-0 bg-transparent p-0" 
                                                    title="Xóa hosting" 
                                                    onclick="confirmDeleteHosting(<?php echo $hosting->id; ?>, '<?php echo esc_js(!empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id); ?>', <?php echo ($hosting->website_id > 0) ? 'true' : 'false'; ?>)">
                                                <i class="ph ph-trash btn-icon-prepend fa-150p"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach; 
                                } else {
                                ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2"></i>
                                            <div>Không có hosting nào được tìm thấy</div>
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

<!-- Hidden form for deleting hosting -->
<form id="deleteHostingForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_hosting_id" id="delete_hosting_id" value="">
    <input type="hidden" name="force_delete" id="force_delete" value="0">
</form>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteHostingModal" tabindex="-1" aria-labelledby="deleteHostingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteHostingModalLabel">
                    <i class="ph ph-warning-diamond me-2"></i>
                    Xác nhận xóa hosting
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="ph ph-warning me-2"></i>
                    <strong>Cảnh báo:</strong> Hành động này không thể hoàn tác!
                </div>
                
                <p>Bạn có chắc chắn muốn xóa hosting <strong id="hostingCodeToDelete"></strong>?</p>
                
                <div id="websiteWarning" style="display: none;">
                    <div class="alert alert-danger" role="alert">
                        <i class="ph ph-exclamation-triangle me-2"></i>
                        <strong>Hosting này đang được sử dụng bởi website!</strong>
                        <br>Nếu bạn tiếp tục, website sẽ không còn liên kết với hosting nào.
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmForceDelete">
                        <label class="form-check-label text-danger" for="confirmForceDelete">
                            Tôi hiểu và muốn xóa hosting kể cả khi đang được sử dụng
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ph ph-x me-2"></i>Hủy bỏ
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="ph ph-trash me-2"></i>Xóa hosting
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDeleteHosting(hostingId, hostingCode, hasWebsite) {
    console.log('Delete hosting:', hostingId, hostingCode, 'hasWebsite:', hasWebsite);
    
    document.getElementById('delete_hosting_id').value = hostingId;
    document.getElementById('hostingCodeToDelete').textContent = hostingCode;
    
    const websiteWarning = document.getElementById('websiteWarning');
    const confirmForceDelete = document.getElementById('confirmForceDelete');
    
    // Convert string 'true'/'false' to boolean
    const hasWebsiteBoolean = hasWebsite === true || hasWebsite === 'true';
    
    if (hasWebsiteBoolean) {
        websiteWarning.style.display = 'block';
        confirmForceDelete.checked = false;
        console.log('Showing website warning');
    } else {
        websiteWarning.style.display = 'none';
        console.log('No website warning needed');
    }
    
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteHostingModal'));
    deleteModal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    const hasWebsite = document.getElementById('websiteWarning').style.display !== 'none';
    const forceDeleteChecked = document.getElementById('confirmForceDelete').checked;
    
    console.log('Confirm delete - hasWebsite:', hasWebsite, 'forceDeleteChecked:', forceDeleteChecked);
    
    if (hasWebsite && !forceDeleteChecked) {
        alert('Vui lòng xác nhận để xóa hosting đang được sử dụng bởi website.');
        // Focus vào checkbox để user có thể check
        document.getElementById('confirmForceDelete').focus();
        // Highlight checkbox bằng cách scroll tới và thêm hiệu ứng
        document.getElementById('confirmForceDelete').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    
    // Nếu hosting có website và user đã xác nhận, hoặc hosting không có website
    if (hasWebsite && forceDeleteChecked) {
        document.getElementById('force_delete').value = '1';
        console.log('Setting force_delete to 1');
    }
    
    // Proceed with deletion
    document.getElementById('deleteHostingForm').submit();
});
</script>

<!-- Bulk renewal functionality is now handled by custom.js -->

<?php
get_footer();
?>