<?php
/* 
    Template Name: Restore Website
*/

global $wpdb;
$websites_table = $wpdb->prefix . 'im_websites';
$services_table = $wpdb->prefix . 'im_website_services';

// Get website ID from URL
$website_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Redirect if no website ID provided
if (!$website_id) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

// Get website data
$website = $wpdb->get_row($wpdb->prepare("SELECT * FROM $websites_table WHERE id = %d AND status = 'DELETED'", $website_id));

// Redirect if website not found or not deleted
if (!$website) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

// Get deleted services for this website
$deleted_services = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $services_table 
    WHERE website_id = %d AND status = 'DELETED'
    ORDER BY created_at DESC
", $website_id));

// Get deleted domain if exists
$deleted_domain = null;
if ($website->domain_id) {
    $deleted_domain = $wpdb->get_row($wpdb->prepare("SELECT * FROM $domains_table WHERE id = %d AND status = 'DELETED'", $website->domain_id));
}

// Get deleted hosting if exists  
$deleted_hosting = null;
if ($website->hosting_id) {
    $deleted_hosting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $hostings_table WHERE id = %d AND status = 'DELETED'", $website->hosting_id));
}

// Get deleted maintenance if exists
$deleted_maintenance = null;
if ($website->maintenance_package_id) {
    $deleted_maintenance = $wpdb->get_row($wpdb->prepare("SELECT * FROM $maintenance_table WHERE id = %d AND status = 'DELETED'", $website->maintenance_package_id));
}

// Process restoration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_restore'])) {
    if (wp_verify_nonce($_POST['restore_website_nonce'], 'restore_website_' . $website_id)) {
        
        $selected_services = isset($_POST['selected_services']) ? array_map('intval', $_POST['selected_services']) : [];
        $restore_domain = isset($_POST['restore_domain']) ? true : false;
        $restore_hosting = isset($_POST['restore_hosting']) ? true : false;
        $restore_maintenance = isset($_POST['restore_maintenance']) ? true : false;
        $restore_website = isset($_POST['restore_website']) ? true : false;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Restore website if requested
            if ($restore_website) {
                $wpdb->update(
                    $websites_table,
                    array('status' => 'ACTIVE'),
                    array('id' => $website_id),
                    array('%s'),
                    array('%d')
                );
            }
            
            // Restore domain if requested
            if ($restore_domain && $deleted_domain) {
                $wpdb->update(
                    $domains_table,
                    array('status' => 'ACTIVE'),
                    array('id' => $deleted_domain->id),
                    array('%s'),
                    array('%d')
                );
            }
            
            // Restore hosting if requested
            if ($restore_hosting && $deleted_hosting) {
                $wpdb->update(
                    $hostings_table,
                    array('status' => 'ACTIVE'),
                    array('id' => $deleted_hosting->id),
                    array('%s'),
                    array('%d')
                );
            }
            
            // Restore maintenance if requested
            if ($restore_maintenance && $deleted_maintenance) {
                $wpdb->update(
                    $maintenance_table,
                    array('status' => 'ACTIVE'),
                    array('id' => $deleted_maintenance->id),
                    array('%s'),
                    array('%d')
                );
            }
            
            // Restore selected website services
            if (!empty($selected_services)) {
                foreach ($selected_services as $service_id) {
                    $wpdb->update(
                        $services_table,
                        array('status' => 'ACTIVE'),
                        array('id' => $service_id),
                        array('%s'),
                        array('%d')
                    );
                }
            }
            
            $wpdb->query('COMMIT');
            
            // Redirect with success message
            wp_redirect(home_url('/list-website/?restored=1'));
            exit;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $error_message = 'Đã xảy ra lỗi khi khôi phục: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Lỗi bảo mật. Vui lòng thử lại.';
    }
}

get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title text-success">
                            <i class="ph ph-arrow-clockwise me-2"></i>
                            Khôi phục Website
                        </h4>
                        <a href="<?php echo home_url('/list-website/?show_deleted=1'); ?>" class="btn btn-secondary">
                            <i class="ph ph-arrow-left me-2"></i>Quay lại
                        </a>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="ph ph-warning me-2"></i>
                        <?php echo esc_html($error_message); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row justify-content-center">
                        <div class="col-md-10">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="ph ph-arrow-clockwise me-2"></i>
                                        Khôi phục Website và Dịch vụ
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="mb-3">
                                        Website: <strong>"<?php echo esc_html($website->name); ?>"</strong>
                                    </p>
                                    
                                    <form method="post" action="" id="restoreForm">
                                        <?php wp_nonce_field('restore_website_' . $website_id, 'restore_website_nonce'); ?>
                                        <input type="hidden" name="confirm_restore" value="1">
                                        
                                        <!-- Website restoration -->
                                        <div class="mb-4">
                                            <h6 class="mb-3">
                                                <i class="ph ph-globe me-2"></i>
                                                Khôi phục Website:
                                            </h6>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="restore_website" value="1" id="restore_website" checked>
                                                <label class="form-check-label fw-bold" for="restore_website">
                                                    Khôi phục website "<?php echo esc_html($website->name); ?>"
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <!-- Services restoration -->
                                        <?php if (!empty($deleted_services)): ?>
                                        <div class="mb-4">
                                            <h6 class="mb-3">
                                                <i class="ph ph-list me-2"></i>
                                                Khôi phục dịch vụ đã xóa:
                                            </h6>
                                            
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="select_all_services">
                                                    <label class="form-check-label fw-bold" for="select_all_services">
                                                        Chọn tất cả dịch vụ
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="border rounded p-3 mb-3" style="max-height: 300px; overflow-y: auto;">
                                                <?php foreach ($deleted_services as $service): ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input service-checkbox" type="checkbox" 
                                                               value="<?php echo $service->id; ?>" 
                                                               id="service_<?php echo $service->id; ?>" 
                                                               name="selected_services[]" checked>
                                                        <label class="form-check-label" for="service_<?php echo $service->id; ?>">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <strong><?php echo esc_html($service->title); ?></strong>
                                                                    <span class="badge bg-secondary ms-2">
                                                                        <?php echo esc_html($service->status); ?>
                                                                    </span>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        <?php echo esc_html($service->description); ?>
                                                                    </small>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        Mã: <?php echo esc_html($service->service_code); ?> | 
                                                                        Tạo: <?php echo date('d/m/Y', strtotime($service->created_at)); ?>
                                                                    </small>
                                                                </div>
                                                                <div class="text-end">
                                                                    <?php if ($service->fixed_price): ?>
                                                                        <span class="fw-bold text-success">
                                                                            <?php echo number_format($service->fixed_price, 0, ',', '.'); ?>đ
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Action buttons -->
                                        <div class="d-flex justify-content-center gap-3">
                                            <button type="submit" class="btn btn-success btn-lg">
                                                <i class="ph ph-arrow-clockwise me-2"></i>
                                                Khôi phục
                                            </button>
                                            
                                            <a href="<?php echo home_url('/list-website/?show_deleted=1'); ?>" class="btn btn-secondary btn-lg">
                                                <i class="ph ph-x me-2"></i>
                                                Hủy bỏ
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select_all_services');
    const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
    
    // Select all functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            serviceCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Individual service checkbox functionality
    serviceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllCheckbox();
        });
    });
    
    function updateSelectAllCheckbox() {
        if (selectAllCheckbox) {
            const totalServices = serviceCheckboxes.length;
            const checkedCount = document.querySelectorAll('.service-checkbox:checked').length;
            selectAllCheckbox.checked = checkedCount === totalServices;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalServices;
        }
    }
    
    // Initialize
    updateSelectAllCheckbox();
});
</script>

<?php
get_footer();
?>
