<?php
/* 
    Template Name: Delete Website
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

// Get website data with related services
$website = $wpdb->get_row($wpdb->prepare("SELECT * FROM $websites_table WHERE id = %d", $website_id));

// Redirect if website not found
if (!$website) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

// Get all services related to this website
$services = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $services_table 
    WHERE website_id = %d 
    ORDER BY created_at DESC
", $website_id));

// Get domain information if exists
$domain = null;
if ($website->domain_id) {
    $domain = $wpdb->get_row($wpdb->prepare("SELECT * FROM $domains_table WHERE id = %d", $website->domain_id));
}

// Get hosting information if exists
$hosting = null;
if ($website->hosting_id) {
    $hosting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $hostings_table WHERE id = %d", $website->hosting_id));
}

// Get maintenance information if exists
$maintenance = null;
if ($website->maintenance_package_id) {
    $maintenance = $wpdb->get_row($wpdb->prepare("SELECT * FROM $maintenance_table WHERE id = %d", $website->maintenance_package_id));
}

// Add status column to websites table if it doesn't exist
$wpdb->query("ALTER TABLE $websites_table ADD COLUMN IF NOT EXISTS status ENUM('ACTIVE', 'DELETED') DEFAULT 'ACTIVE'");
$wpdb->query("ALTER TABLE $services_table ADD COLUMN IF NOT EXISTS status ENUM('ACTIVE', 'DELETED') DEFAULT 'ACTIVE'");

// Process deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
    if (wp_verify_nonce($_POST['delete_website_nonce'], 'delete_website_' . $website_id)) {
        
        $selected_services = isset($_POST['selected_services']) ? array_map('intval', $_POST['selected_services']) : [];
        $selected_domain = isset($_POST['selected_domain']) ? true : false;
        $selected_hosting = isset($_POST['selected_hosting']) ? true : false;
        $selected_maintenance = isset($_POST['selected_maintenance']) ? true : false;
        $delete_method = isset($_POST['delete_method']) ? $_POST['delete_method'] : 'soft';
        $delete_website = isset($_POST['delete_website']) ? true : false;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Handle service deletions (website services)
            if (!empty($selected_services)) {
                foreach ($selected_services as $service_id) {
                    if ($delete_method === 'hard') {
                        // Hard delete: Remove from database
                        $wpdb->delete($services_table, array('id' => $service_id), array('%d'));
                        
                        // Delete related invoice items
                        $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
                        $wpdb->delete($invoice_items_table, array('service_type' => 'Website Service', 'service_id' => $service_id), array('%s', '%d'));
                    } else {
                        // Soft delete: Update status
                        $wpdb->update(
                            $services_table,
                            array('status' => 'DELETED'),
                            array('id' => $service_id),
                            array('%s'),
                            array('%d')
                        );
                    }
                }
            }
            
            // Handle domain deletion/detachment
            if ($selected_domain && $website->domain_id) {
                if ($delete_method === 'hard') {
                    // Hard delete domain
                    $wpdb->delete($domains_table, array('id' => $website->domain_id), array('%d'));
                    
                    // Delete related invoice items
                    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
                    $wpdb->delete($invoice_items_table, array('service_type' => 'Domain', 'service_id' => $website->domain_id), array('%s', '%d'));
                } else {
                    // Soft delete domain and detach from website
                    $wpdb->update(
                        $domains_table,
                        array('status' => 'DELETED'),
                        array('id' => $website->domain_id),
                        array('%s'),
                        array('%d')
                    );
                }
                
                // Remove domain reference from website
                $wpdb->update(
                    $websites_table,
                    array('domain_id' => null),
                    array('id' => $website_id),
                    array('%d'),
                    array('%d')
                );
            }
            
            // Handle hosting deletion/detachment
            if ($selected_hosting && $website->hosting_id) {
                if ($delete_method === 'hard') {
                    // Hard delete hosting
                    $wpdb->delete($hostings_table, array('id' => $website->hosting_id), array('%d'));
                    
                    // Delete related invoice items
                    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
                    $wpdb->delete($invoice_items_table, array('service_type' => 'Hosting', 'service_id' => $website->hosting_id), array('%s', '%d'));
                } else {
                    // Soft delete hosting and detach from website
                    $wpdb->update(
                        $hostings_table,
                        array('status' => 'DELETED'),
                        array('id' => $website->hosting_id),
                        array('%s'),
                        array('%d')
                    );
                }
                
                // Remove hosting reference from website
                $wpdb->update(
                    $websites_table,
                    array('hosting_id' => null),
                    array('id' => $website_id),
                    array('%d'),
                    array('%d')
                );
            }
            
            // Handle maintenance deletion/detachment
            if ($selected_maintenance && $website->maintenance_package_id) {
                if ($delete_method === 'hard') {
                    // Hard delete maintenance
                    $wpdb->delete($maintenance_table, array('id' => $website->maintenance_package_id), array('%d'));
                    
                    // Delete related invoice items
                    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
                    $wpdb->delete($invoice_items_table, array('service_type' => 'Maintenance', 'service_id' => $website->maintenance_package_id), array('%s', '%d'));
                } else {
                    // Soft delete maintenance and detach from website
                    $wpdb->update(
                        $maintenance_table,
                        array('status' => 'DELETED'),
                        array('id' => $website->maintenance_package_id),
                        array('%s'),
                        array('%d')
                    );
                }
                
                // Remove maintenance reference from website
                $wpdb->update(
                    $websites_table,
                    array('maintenance_package_id' => null),
                    array('id' => $website_id),
                    array('%d'),
                    array('%d')
                );
            }
            
            // If deleting website (all services selected)
            if ($delete_website) {
                if ($delete_method === 'hard') {
                    // Hard delete website
                    
                    // Delete remaining services if any
                    $wpdb->delete($services_table, array('website_id' => $website_id), array('%d'));
                    
                    // Delete related invoice items
                    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
                    $wpdb->delete($invoice_items_table, array('service_type' => 'Website', 'service_id' => $website_id), array('%s', '%d'));
                    
                    // Finally delete the website record
                    $result = $wpdb->delete($websites_table, array('id' => $website_id), array('%d'));
                    
                } else {
                    // Soft delete website
                    $result = $wpdb->update(
                        $websites_table,
                        array('status' => 'DELETED'),
                        array('id' => $website_id),
                        array('%s'),
                        array('%d')
                    );
                }
            } else {
                $result = true; // Just deleting services, not website
            }
            
            if ($result !== false) {
                $wpdb->query('COMMIT');
                
                // Redirect with success message
                $success_type = $delete_website ? 'website' : 'services';
                $delete_type = $delete_method === 'hard' ? 'hard' : 'soft';
                wp_redirect(home_url('/list-website/?deleted=1&type=' . $success_type . '&method=' . $delete_type));
                exit;
            } else {
                $wpdb->query('ROLLBACK');
                $error_message = 'Không thể thực hiện thao tác xóa. Vui lòng thử lại.';
            }
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $error_message = 'Đã xảy ra lỗi: ' . $e->getMessage();
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
                        <h4 class="card-title text-danger">
                            <i class="ph ph-trash me-2"></i>
                            Xóa Website / Dịch vụ
                        </h4>
                        <a href="<?php echo home_url('/list-website/'); ?>" class="btn btn-secondary">
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
                            <div class="card border-info mb-4">
                                <div class="card-header bg-light-info text-info">
                                    <h5 class="mb-0">
                                        <i class="ph ph-info me-2"></i>
                                        Chọn dịch vụ cần xóa
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="mb-3">
                                        Website: <strong>"<?php echo esc_html($website->name); ?>"</strong>
                                    </p>
                                    
                                    <form method="post" action="" id="deleteForm">
                                        <?php wp_nonce_field('delete_website_' . $website_id, 'delete_website_nonce'); ?>
                                        <input type="hidden" name="confirm_delete" value="1">
                                        
                                        <!-- Services selection -->
                                        <div class="mb-4">
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <label class="form-check-label fw-bold" for="select_all_services">
                                                        <input class="form-check-input" type="checkbox" id="select_all_services">
                                                        Chọn tất cả dịch vụ
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="border rounded p-3 mb-3" style="max-height: 400px; overflow-y: auto;">
                                                <!-- Domain Service -->
                                                <?php if ($domain): ?>
                                                    <div class="mb-3">
                                                        <h6 class="text-primary mb-2">
                                                            <i class="ph ph-globe me-2"></i>
                                                            Dịch vụ Domain
                                                        </h6>
                                                        <div class="form-check mb-2 ms-3 d-flex gap-2">
                                                            <input class="form-check service-checkbox ps-3" type="checkbox" 
                                                                value="domain" 
                                                                id="domain_service" 
                                                                name="selected_domain">
                                                            <label class="" for="domain_service">
                                                                    <div class="d-flex justify-content-between align-items-start">
                                                                        <div>
                                                                            <strong><?php echo esc_html($domain->domain_name); ?></strong>
                                                                            <span class="badge bg-<?php echo ($domain->status === 'ACTIVE') ? 'success' : 'secondary'; ?> ms-2">
                                                                                <?php echo esc_html($domain->status); ?>
                                                                            </span>
                                                                            <br>
                                                                            <small class="text-muted">
                                                                                Đăng ký: <?php echo $domain->registration_date ? date('d/m/Y', strtotime($domain->registration_date)) : 'N/A'; ?> | 
                                                                                Hết hạn: <?php echo $domain->expiry_date ? date('d/m/Y', strtotime($domain->expiry_date)) : 'N/A'; ?>
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Hosting Service -->
                                                <?php if ($hosting): ?>
                                                    <div class="mb-3">
                                                        <h6 class="text-info mb-2">
                                                            <i class="ph ph-cloud me-2"></i>
                                                            Dịch vụ Hosting
                                                        </h6>
                                                        <div class=" mb-2 ms-3 d-flex gap-2">
                                                            <input class=" service-checkbox" type="checkbox" 
                                                                   value="hosting" 
                                                                   id="hosting_service" 
                                                                   name="selected_hosting">
                                                            <label class="form-check-label" for="hosting_service">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <div>
                                                                        <strong><?php echo esc_html($hosting->hosting_code ?: 'HOST-' . $hosting->id); ?></strong>
                                                                        <span class="badge bg-<?php echo ($hosting->status === 'ACTIVE') ? 'success' : 'secondary'; ?> ms-2">
                                                                            <?php echo esc_html($hosting->status); ?>
                                                                        </span>
                                                                        <br>
                                                                        <small class="text-muted">
                                                                            Đăng ký: <?php echo date('d/m/Y', strtotime($hosting->registration_date)); ?> | 
                                                                            Hết hạn: <?php echo date('d/m/Y', strtotime($hosting->expiry_date)); ?>
                                                                        </small>
                                                                        <?php if ($hosting->ip_address): ?>
                                                                        <br>
                                                                        <small class="text-muted">IP: <?php echo esc_html($hosting->ip_address); ?></small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Maintenance Service -->
                                                <?php if ($maintenance): ?>
                                                    <div class="mb-3">
                                                        <h6 class="text-warning mb-2">
                                                            <i class="ph ph-wrench me-2"></i>
                                                            Dịch vụ Bảo trì
                                                        </h6>
                                                        <div class=" mb-2 ms-3 d-flex gap-2">
                                                            <input class="service-checkbox" type="checkbox" value="maintenance" id="maintenance_service" name="selected_maintenance">
                                                            <label class="form-check-label" for="maintenance_service">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <div>
                                                                        <strong><?php echo esc_html($maintenance->order_code ?: 'MAINT-' . $maintenance->id); ?></strong>
                                                                        <span class="badge bg-<?php echo ($maintenance->status === 'ACTIVE') ? 'success' : 'secondary'; ?> ms-2">
                                                                            <?php echo esc_html($maintenance->status); ?>
                                                                        </span>
                                                                        <br>
                                                                        <small class="text-muted">
                                                                            Gia hạn: <?php echo date('d/m/Y', strtotime($maintenance->renew_date)); ?> | 
                                                                            Hết hạn: <?php echo date('d/m/Y', strtotime($maintenance->expiry_date)); ?>
                                                                        </small>
                                                                        <br>
                                                                        <small class="text-muted">
                                                                            Phí hàng tháng: <?php echo number_format($maintenance->monthly_fee, 0, ',', '.'); ?>đ
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Website Services -->
                                                <?php if (!empty($services)): ?>
                                                    <div class="mb-3">
                                                        <h6 class="text-success mb-2">
                                                            <i class="ph ph-code me-2"></i>
                                                            Dịch vụ Website
                                                        </h6>
                                                        <?php foreach ($services as $service): ?>
                                                            <div class="form-check mb-2 ms-3 d-flex gap-2">
                                                                <label class="form-check-label" for="service_<?php echo $service->id; ?>">
                                                                    <input class="form-check-input service-checkbox" type="checkbox" 
                                                                           value="<?php echo $service->id; ?>" 
                                                                           id="service_<?php echo $service->id; ?>" 
                                                                           name="selected_services[]">
                                                                </label>
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <div>
                                                                        <strong><?php echo esc_html($service->title); ?></strong>
                                                                        <span class="badge bg-<?php echo ($service->status === 'COMPLETED') ? 'success' : (($service->status === 'IN_PROGRESS') ? 'primary' : 'secondary'); ?> ms-2">
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
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!$domain && !$hosting && !$maintenance && empty($services)): ?>
                                                    <div class="alert alert-info">
                                                        <i class="ph ph-info me-2"></i>
                                                        Website này chưa có dịch vụ nào. Bạn có thể xóa trực tiếp website.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Delete method selection -->
                                        <div class="mb-4">
                                            <h6 class="mb-3">
                                                <i class="ph ph-gear me-2"></i>
                                                Phương thức xóa:
                                            </h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-check form-check-flat form-check-primary">
                                                        <label class="form-check-label">
                                                            <input type="radio" class="form-check-input" type="radio" name="delete_method" value="soft" id="soft_delete">
                                                            Xóa mềm <i class="input-helper"></i>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check form-check-flat form-check-primary">
                                                        <label class="form-check-label">
                                                            <input type="radio" class="form-check-input" type="radio" name="delete_method" value="hard" id="hard_delete">
                                                            Xóa vĩnh viễn <i class="input-helper"></i>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Warning message -->
                                        <div class="alert alert-warning" id="warning_message" style="display: none;">
                                            <i class="ph ph-warning-circle me-2"></i>
                                            <strong>Cảnh báo:</strong>
                                            <span id="warning_text"></span>
                                        </div>
                                        
                                        <!-- Action buttons -->
                                        <div class="d-flex justify-content-center gap-3">
                                            <button type="submit" class="btn btn-danger btn-lg" id="delete_btn" disabled>
                                                <i class="ph ph-trash me-2"></i>
                                                <span id="delete_btn_text">Chọn dịch vụ để xóa</span>
                                            </button>
                                            
                                            <a href="<?php echo home_url('/list-website/'); ?>" class="btn btn-secondary btn-lg">
                                                <i class="ph ph-x me-2"></i>
                                                Hủy bỏ
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add hidden field for website deletion -->
                    <input type="hidden" name="delete_website" value="0" id="delete_website_field" form="deleteForm">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select_all_services');
    const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
    const deleteBtn = document.getElementById('delete_btn');
    const deleteBtnText = document.getElementById('delete_btn_text');
    const warningMessage = document.getElementById('warning_message');
    const warningText = document.getElementById('warning_text');
    const deleteWebsiteField = document.getElementById('delete_website_field');
    const deleteForm = document.getElementById('deleteForm');
    
    // Count total services available
    const domainExists = <?php echo $domain ? 'true' : 'false'; ?>;
    const hostingExists = <?php echo $hosting ? 'true' : 'false'; ?>;
    const maintenanceExists = <?php echo $maintenance ? 'true' : 'false'; ?>;
    const websiteServicesCount = <?php echo count($services); ?>;
    const totalServices = (domainExists ? 1 : 0) + (hostingExists ? 1 : 0) + (maintenanceExists ? 1 : 0) + websiteServicesCount;
    
    // Select all functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            serviceCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateDeleteButton();
        });
    }
    
    // Individual service checkbox functionality
    serviceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllCheckbox();
            updateDeleteButton();
        });
    });
    
    function updateSelectAllCheckbox() {
        if (selectAllCheckbox) {
            const checkedCount = document.querySelectorAll('.service-checkbox:checked').length;
            selectAllCheckbox.checked = checkedCount === totalServices;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalServices;
        }
    }
    
    function updateDeleteButton() {
        const checkedCount = document.querySelectorAll('.service-checkbox:checked').length;
        const deleteMethod = document.querySelector('input[name="delete_method"]:checked').value;
        
        if (totalServices === 0) {
            // Website không có dịch vụ đính kèm - luôn cho phép xóa website
            deleteBtn.disabled = false;
            deleteWebsiteField.value = '1';
            deleteBtnText.textContent = deleteMethod === 'hard' ? 'Xóa website vĩnh viễn' : 'Xóa website (mềm)';
            warningText.textContent = deleteMethod === 'hard' ? 
                'Sẽ xóa website vĩnh viễn khỏi database!' : 
                'Sẽ ẩn website khỏi danh sách (có thể khôi phục).';
            warningMessage.style.display = 'block';
        } else if (checkedCount === 0) {
            // Có dịch vụ nhưng chưa chọn cái nào
            deleteBtn.disabled = true;
            deleteBtnText.textContent = 'Chọn dịch vụ để xóa';
            warningMessage.style.display = 'none';
            deleteWebsiteField.value = '0';
        } else if (checkedCount === totalServices) {
            // Đã chọn tất cả dịch vụ - sẽ xóa website
            deleteBtn.disabled = false;
            deleteWebsiteField.value = '1';
            deleteBtnText.textContent = deleteMethod === 'hard' ? 'Xóa website vĩnh viễn' : 'Xóa website (mềm)';
            warningText.textContent = deleteMethod === 'hard' ? 
                'Sẽ xóa toàn bộ website và tất cả dịch vụ vĩnh viễn khỏi database!' : 
                'Sẽ ẩn website và tất cả dịch vụ khỏi danh sách (có thể khôi phục).';
            warningMessage.style.display = 'block';
        } else {
            // Chỉ chọn một số dịch vụ - sẽ giữ website
            deleteBtn.disabled = false;
            deleteWebsiteField.value = '0';
            deleteBtnText.textContent = deleteMethod === 'hard' ? 
                `Xóa vĩnh viễn ${checkedCount} dịch vụ` : 
                `Xóa mềm ${checkedCount} dịch vụ`;
            warningText.textContent = deleteMethod === 'hard' ? 
                `Sẽ xóa vĩnh viễn ${checkedCount} dịch vụ đã chọn khỏi database!` : 
                `Sẽ ẩn ${checkedCount} dịch vụ đã chọn khỏi danh sách (có thể khôi phục).`;
            warningMessage.style.display = 'block';
        }
    }
    
    // Update button when delete method changes
    document.querySelectorAll('input[name="delete_method"]').forEach(radio => {
        radio.addEventListener('change', updateDeleteButton);
    });
    
    // Form submission confirmation
    deleteForm.addEventListener('submit', function(e) {
        const checkedCount = document.querySelectorAll('.service-checkbox:checked').length;
        const deleteMethod = document.querySelector('input[name="delete_method"]:checked').value;
        const isWebsiteDeletion = deleteWebsiteField.value === '1';
        
        let confirmMessage = '';
        if (isWebsiteDeletion) {
            confirmMessage = deleteMethod === 'hard' ? 
                'Bạn có chắc chắn muốn XÓA VĨNH VIỄN website và tất cả dịch vụ? Hành động này KHÔNG THỂ HOÀN TÁC!' : 
                'Bạn có chắc chắn muốn ẩn website và tất cả dịch vụ khỏi danh sách?';
        } else {
            confirmMessage = deleteMethod === 'hard' ? 
                `Bạn có chắc chắn muốn XÓA VĨNH VIỄN ${checkedCount} dịch vụ đã chọn? Hành động này KHÔNG THỂ HOÀN TÁC!` : 
                `Bạn có chắc chắn muốn ẩn ${checkedCount} dịch vụ đã chọn khỏi danh sách?`;
        }
        
        if (!confirm(confirmMessage)) {
            e.preventDefault();
        }
    });
    
    // Initialize button state
    updateDeleteButton();
});
</script>

<?php
get_footer();
?>
