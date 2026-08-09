<?php
/* 
    Template Name: Restore Website
*/

global $wpdb;
$websites_table = $wpdb->prefix . 'im_websites';
$domains_table = $wpdb->prefix . 'im_domains';
$services_table = $wpdb->prefix . 'im_website_services';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';

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
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // 1. Restore website to ACTIVE
            $wpdb->update(
                $websites_table,
                array('status' => 'ACTIVE'),
                array('id' => $website_id),
                array('%s'),
                array('%d')
            );
            
            // 2. Restore domain to ACTIVE if linked and currently DELETED
            if ($website->domain_id) {
                $wpdb->update(
                    $domains_table,
                    array('status' => 'ACTIVE'),
                    array('id' => $website->domain_id, 'status' => 'DELETED'),
                    array('%s'),
                    array('%d', '%s')
                );
            }
            
            // 3. Restore hosting to ACTIVE if linked and currently DELETED
            if ($website->hosting_id) {
                $wpdb->update(
                    $hostings_table,
                    array('status' => 'ACTIVE'),
                    array('id' => $website->hosting_id, 'status' => 'DELETED'),
                    array('%s'),
                    array('%d', '%s')
                );
            }
            
            // 4. Restore maintenance package to ACTIVE if linked and currently DELETED
            if ($website->maintenance_package_id) {
                $wpdb->update(
                    $maintenance_table,
                    array('status' => 'ACTIVE'),
                    array('id' => $website->maintenance_package_id, 'status' => 'DELETED'),
                    array('%s'),
                    array('%d', '%s')
                );
            }
            
            // 5. Restore all website services to ACTIVE if currently DELETED
            $wpdb->update(
                $services_table,
                array('status' => 'ACTIVE'),
                array('website_id' => $website_id, 'status' => 'DELETED'),
                array('%s'),
                array('%d', '%s')
            );
            
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
                                        Khôi phục Website và Dịch vụ liên quan
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-success d-flex align-items-center mb-4">
                                        <i class="ph ph-check-circle me-3 fa-2x"></i>
                                        <div>
                                            <strong>Khôi phục tự động:</strong> Khi bạn thực hiện khôi phục website <strong>"<?php echo esc_html($website->name); ?>"</strong>, hệ thống sẽ tự động khôi phục toàn bộ các dịch vụ đi kèm đang bị ẩn (Domain, Hosting, Bảo trì, Website Services) về trạng thái <strong>ACTIVE</strong>.
                                        </div>
                                    </div>
                                    
                                    <form method="post" action="" id="restoreForm">
                                        <?php wp_nonce_field('restore_website_' . $website_id, 'restore_website_nonce'); ?>
                                        <input type="hidden" name="confirm_restore" value="1">
                                        
                                        <!-- List of services that will be restored -->
                                        <div class="mb-4">
                                            <h6 class="mb-3 text-primary">
                                                <i class="ph ph-list-checks me-2"></i>
                                                Danh sách dịch vụ sẽ được khôi phục đồng thời:
                                            </h6>
                                            
                                            <div class="border rounded p-3 bg-light" style="max-height: 400px; overflow-y: auto;">
                                                <!-- Domain -->
                                                <?php if ($deleted_domain): ?>
                                                    <div class="d-flex justify-content-between align-items-center p-2 mb-2 bg-white rounded border">
                                                        <div>
                                                            <i class="ph ph-globe text-primary me-2"></i>
                                                            <strong>Tên miền:</strong> <?php echo esc_html($deleted_domain->domain_name); ?>
                                                        </div>
                                                        <span class="badge bg-warning text-dark">DELETED &rarr; ACTIVE</span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Hosting -->
                                                <?php if ($deleted_hosting): ?>
                                                    <div class="d-flex justify-content-between align-items-center p-2 mb-2 bg-white rounded border">
                                                        <div>
                                                            <i class="ph ph-cloud text-info me-2"></i>
                                                            <strong>Hosting:</strong> <?php echo esc_html($deleted_hosting->hosting_code ?: 'HOST-' . $deleted_hosting->id); ?>
                                                        </div>
                                                        <span class="badge bg-warning text-dark">DELETED &rarr; ACTIVE</span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Maintenance -->
                                                <?php if ($deleted_maintenance): ?>
                                                    <div class="d-flex justify-content-between align-items-center p-2 mb-2 bg-white rounded border">
                                                        <div>
                                                            <i class="ph ph-wrench text-warning me-2"></i>
                                                            <strong>Bảo trì:</strong> <?php echo esc_html($deleted_maintenance->order_code ?: 'MAINT-' . $deleted_maintenance->id); ?>
                                                        </div>
                                                        <span class="badge bg-warning text-dark">DELETED &rarr; ACTIVE</span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Website Services -->
                                                <?php if (!empty($deleted_services)): ?>
                                                    <?php foreach ($deleted_services as $service): ?>
                                                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 bg-white rounded border">
                                                            <div>
                                                                <i class="ph ph-code text-success me-2"></i>
                                                                <strong><?php echo esc_html($service->title); ?></strong> (<?php echo esc_html($service->service_code); ?>)
                                                            </div>
                                                            <span class="badge bg-warning text-dark">DELETED &rarr; ACTIVE</span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                
                                                <?php if (!$deleted_domain && !$deleted_hosting && !$deleted_maintenance && empty($deleted_services)): ?>
                                                    <div class="text-muted p-2">
                                                        <i class="ph ph-info me-2"></i>Không có dịch vụ đính kèm nào bị xóa mềm. Chỉ khôi phục bản ghi Website.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Action buttons -->
                                        <div class="d-flex justify-content-center gap-3">
                                            <button type="submit" class="btn btn-success btn-lg">
                                                <i class="ph ph-arrow-clockwise me-2"></i>
                                                Xác nhận Khôi phục Website & Dịch vụ
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

<?php
get_footer();
?>
