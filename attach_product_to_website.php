<?php
/* 
    Template Name: Attach Product to Website
*/

global $wpdb;
$websites_table = $wpdb->prefix . 'im_websites';
$domains_table = $wpdb->prefix . 'im_domains';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
$users_table = $wpdb->prefix . 'im_users';
$products_table = $wpdb->prefix . 'im_product_catalog';

$current_user_id = get_current_user_id();

// Get parameters from URL
$domain_id = isset($_GET['domain_id']) ? intval($_GET['domain_id']) : 0;
$hosting_id = isset($_GET['hosting_id']) ? intval($_GET['hosting_id']) : 0;
$maintenance_id = isset($_GET['maintenance_id']) ? intval($_GET['maintenance_id']) : 0;

// Determine which product type we're working with
$product_type = '';
$product_data = null;
$product_owner_id = 0;

if ($domain_id > 0) {
    $product_type = 'domain';
    $product_data = $wpdb->get_row($wpdb->prepare("
        SELECT d.*, u.name AS owner_name, u.user_code AS owner_code, pc.name AS product_name
        FROM $domains_table d
        LEFT JOIN $users_table u ON d.owner_user_id = u.id
        LEFT JOIN $products_table pc ON d.product_catalog_id = pc.id
        WHERE d.id = %d
    ", $domain_id));
    if ($product_data) $product_owner_id = $product_data->owner_user_id;
} elseif ($hosting_id > 0) {
    $product_type = 'hosting';
    $product_data = $wpdb->get_row($wpdb->prepare("
        SELECT h.*, u.name AS owner_name, u.user_code AS owner_code, pc.name AS product_name
        FROM $hostings_table h
        LEFT JOIN $users_table u ON h.owner_user_id = u.id
        LEFT JOIN $products_table pc ON h.product_catalog_id = pc.id
        WHERE h.id = %d
    ", $hosting_id));
    if ($product_data) $product_owner_id = $product_data->owner_user_id;
} elseif ($maintenance_id > 0) {
    $product_type = 'maintenance';
    $product_data = $wpdb->get_row($wpdb->prepare("
        SELECT m.*, u.name AS owner_name, u.user_code AS owner_code, pc.name AS product_name
        FROM $maintenance_table m
        LEFT JOIN $users_table u ON m.owner_user_id = u.id
        LEFT JOIN $products_table pc ON m.product_catalog_id = pc.id
        WHERE m.id = %d
    ", $maintenance_id));
    if ($product_data) $product_owner_id = $product_data->owner_user_id;
}

// Redirect if no valid product found
if (!$product_data || !$product_type) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

/* 
 * Process form submission
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['attach_product_field']) && wp_verify_nonce($_POST['attach_product_field'], 'attach_product')) {
        $website_id = intval($_POST['website_id']);
        
        if ($website_id > 0) {
            $data = array();
            $where = array('id' => $website_id);
            $format = array();
            $where_format = array('%d');
            
            // Determine which field to update based on product type
            if ($product_type === 'domain') {
                $data['domain_id'] = $domain_id;
                $format[] = '%d';
            } elseif ($product_type === 'hosting') {
                $data['hosting_id'] = $hosting_id;
                $format[] = '%d';
            } elseif ($product_type === 'maintenance') {
                $data['maintenance_package_id'] = $maintenance_id;
                $format[] = '%d';
            }
            
            $data['updated_at'] = current_time('mysql');
            $format[] = '%s';
            
            $result = $wpdb->update(
                $websites_table,
                $data,
                $where,
                $format,
                $where_format
            );
            
            if ($result !== false) {
                // Success message and redirect
                $success_message = '';
                switch ($product_type) {
                    case 'domain':
                        $success_message = 'Đã gắn tên miền vào website thành công!';
                        break;
                    case 'hosting':
                        $success_message = 'Đã gắn hosting vào website thành công!';
                        break;
                    case 'maintenance':
                        $success_message = 'Đã gắn gói bảo trì vào website thành công!';
                        break;
                }
                
                // Redirect to website detail page
                wp_redirect(home_url('/website/?website_id=' . $website_id . '&success=' . urlencode($success_message)));
                exit;
            } else {
                $error_message = 'Có lỗi xảy ra khi gắn sản phẩm vào website.';
            }
        } else {
            $error_message = 'Vui lòng chọn website.';
        }
    }
}

// Get available websites for this customer that don't already have this product type
$available_websites = array();
if ($product_owner_id > 0) {
    $where_condition = '';
    switch ($product_type) {
        case 'domain':
            $where_condition = 'AND (w.domain_id IS NULL OR w.domain_id = 0)';
            break;
        case 'hosting':
            $where_condition = 'AND (w.hosting_id IS NULL OR w.hosting_id = 0)';
            break;
        case 'maintenance':
            $where_condition = 'AND (w.maintenance_package_id IS NULL OR w.maintenance_package_id = 0)';
            break;
    }
    
    $available_websites = $wpdb->get_results($wpdb->prepare("
        SELECT w.id, w.name, w.domain_id, w.hosting_id, w.maintenance_package_id
        FROM $websites_table w
        WHERE w.owner_user_id = %d $where_condition
        ORDER BY w.name ASC
    ", $product_owner_id));
}

get_header();
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <!-- Back button -->
                        <a href="javascript:history.back()" class="nav-link">
                            <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                        </a>
                        
                        <h4 class="card-title mb-0">
                            <i class="ph ph-link me-2"></i>
                            Gắn <?php 
                            echo $product_type === 'domain' ? 'Tên miền' : 
                                ($product_type === 'hosting' ? 'Hosting' : 'Gói bảo trì'); 
                            ?> vào Website
                        </h4>
                        
                        <div></div> <!-- Empty div for spacing -->
                    </div>

                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Product Information Card -->
                    <div class="card mb-4 <?php 
                        echo $product_type === 'domain' ? 'border-primary' : 
                            ($product_type === 'hosting' ? 'border-info' : 'border-warning'); 
                    ?>">
                        <div class="card-body">
                            <h5 class="card-title d-flex align-items-center mb-3 <?php 
                                echo $product_type === 'domain' ? 'text-primary' : 
                                    ($product_type === 'hosting' ? 'text-info' : 'text-warning'); 
                            ?>">
                                <i class="ph <?php 
                                    echo $product_type === 'domain' ? 'ph-globe-hemisphere-east' : 
                                        ($product_type === 'hosting' ? 'ph-cloud' : 'ph-wrench'); 
                                ?> me-2" style="font-size: 24px;"></i>
                                Thông tin <?php 
                                    echo $product_type === 'domain' ? 'tên miền' : 
                                        ($product_type === 'hosting' ? 'hosting' : 'gói bảo trì'); 
                                ?>
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td class="fw-bold pe-3">Mã sản phẩm:</td>
                                            <td>
                                                <?php 
                                                if ($product_type === 'domain') {
                                                    echo !empty($product_data->order_code) ? $product_data->order_code : 'DOM-' . $product_data->id;
                                                } elseif ($product_type === 'hosting') {
                                                    echo !empty($product_data->hosting_code) ? $product_data->hosting_code : 'HOST-' . $product_data->id;
                                                } else {
                                                    echo !empty($product_data->order_code) ? $product_data->order_code : 'MAINT-' . $product_data->id;
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold pe-3">Tên sản phẩm:</td>
                                            <td>
                                                <?php 
                                                if ($product_type === 'domain') {
                                                    echo esc_html($product_data->domain_name);
                                                } else {
                                                    echo esc_html($product_data->product_name);
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold pe-3">Chủ sở hữu:</td>
                                            <td><?php echo esc_html($product_data->owner_code . ' - ' . $product_data->owner_name); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless table-sm">
                                        <?php if ($product_type === 'domain'): ?>
                                        <tr>
                                            <td class="fw-bold pe-3">Ngày hết hạn:</td>
                                            <td>
                                                <span class="badge border-radius-9 <?php echo strtotime($product_data->expiry_date) < time() ? 'bg-danger' : 'bg-success'; ?>">
                                                    <?php echo date('d/m/Y', strtotime($product_data->expiry_date)); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold pe-3">Trạng thái:</td>
                                            <td>
                                                <span class="badge border-radius-9 <?php 
                                                    echo $product_data->status === 'ACTIVE' ? 'bg-success' : 
                                                        ($product_data->status === 'PENDING' ? 'bg-warning' : 'bg-secondary'); 
                                                ?>">
                                                    <?php 
                                                    echo $product_data->status === 'ACTIVE' ? 'Hoạt động' : 
                                                        ($product_data->status === 'PENDING' ? 'Chờ xử lý' : $product_data->status); 
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php elseif ($product_type === 'hosting'): ?>
                                        <tr>
                                            <td class="fw-bold pe-3">Ngày hết hạn:</td>
                                            <td>
                                                <span class="badge border-radius-9 <?php echo strtotime($product_data->expiry_date) < time() ? 'bg-danger' : 'bg-success'; ?>">
                                                    <?php echo date('d/m/Y', strtotime($product_data->expiry_date)); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold pe-3">Giá/chu kỳ:</td>
                                            <td><?php echo number_format($product_data->price, 0, ',', '.'); ?> VNĐ</td>
                                        </tr>
                                        <?php else: // maintenance ?>
                                        <tr>
                                            <td class="fw-bold pe-3">Ngày hết hạn:</td>
                                            <td>
                                                <span class="badge border-radius-9 <?php echo strtotime($product_data->expiry_date) < time() ? 'bg-danger' : 'bg-success'; ?>">
                                                    <?php echo date('d/m/Y', strtotime($product_data->expiry_date)); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold pe-3">Giá/chu kỳ:</td>
                                            <td><?php echo number_format($product_data->price_per_cycle, 0, ',', '.'); ?> VNĐ</td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Website Selection Form -->
                    <?php if (!empty($available_websites)): ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('attach_product', 'attach_product_field'); ?>
                        
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">
                                    <i class="ph ph-globe me-2"></i>
                                    Chọn website để gắn
                                </h5>
                                
                                <div class="form-group mb-4">
                                    <label for="website_id" class="fw-bold">Website:</label>
                                    <select class="form-select js-example-basic-single" name="website_id" id="website_id" required>
                                        <option value="">-- Chọn website --</option>
                                        <?php foreach ($available_websites as $website): ?>
                                        <option value="<?php echo $website->id; ?>">
                                            <?php echo esc_html($website->name); ?>
                                            <?php 
                                            // Show current attachments for context
                                            $attachments = array();
                                            if ($website->domain_id) $attachments[] = 'Domain';
                                            if ($website->hosting_id) $attachments[] = 'Hosting';
                                            if ($website->maintenance_package_id) $attachments[] = 'Bảo trì';
                                            
                                            if (!empty($attachments)) {
                                                echo ' (' . implode(', ', $attachments) . ')';
                                            } else {
                                                echo ' (Chưa có dịch vụ nào)';
                                            }
                                            ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="d-flex justify-content-center gap-3">
                                    <a href="javascript:history.back()" class="btn btn-secondary">
                                        <i class="ph ph-x me-2"></i>Hủy
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ph ph-link me-2"></i>
                                        Gắn <?php 
                                            echo $product_type === 'domain' ? 'Tên miền' : 
                                                ($product_type === 'hosting' ? 'Hosting' : 'Gói bảo trì'); 
                                        ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <?php else: ?>
                    <!-- No available websites -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="ph ph-info text-muted mb-3" style="font-size: 48px;"></i>
                            <h5>Không có website phù hợp</h5>
                            <p class="text-muted mb-4">
                                Khách hàng <strong><?php echo esc_html($product_data->owner_name); ?></strong> 
                                không có website nào chưa gắn <?php 
                                    echo $product_type === 'domain' ? 'tên miền' : 
                                        ($product_type === 'hosting' ? 'hosting' : 'gói bảo trì'); 
                                ?>.
                            </p>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="javascript:history.back()" class="btn btn-secondary">
                                    <i class="ph ph-arrow-left me-2"></i>Quay lại
                                </a>
                                <a href="<?php echo home_url('/them-moi-website/?owner_user_id=' . $product_owner_id . '&' . $product_type . '_id=' . 
                                    ($product_type === 'domain' ? $domain_id : 
                                        ($product_type === 'hosting' ? $hosting_id : $maintenance_id))); ?>" 
                                   class="btn btn-success">
                                    <i class="ph ph-plus me-2"></i>Tạo website mới
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Select2 for better dropdown experience
jQuery(document).ready(function($) {
    if (typeof $.fn.select2 !== 'undefined') {
        $('.js-example-basic-single').select2({
            width: '100%'
        });
    }
});
</script>

<?php
get_footer();
?>
