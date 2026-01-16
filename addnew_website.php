<?php
/* 
    Template Name: Add New Website
*/

global $wpdb;
$websites_table = $wpdb->prefix . 'im_websites';
$users_table = $wpdb->prefix . 'im_users';
$domains_table = $wpdb->prefix . 'im_domains';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
$products_table = $wpdb->prefix . 'im_product_catalog';

$current_user_id = get_current_user_id();
$website_id = isset($_GET['website_id']) ? intval($_GET['website_id']) : 0;

// Get pre-selected values from URL parameters
$pre_selected_domain_id = isset($_GET['domain_id']) ? intval($_GET['domain_id']) : 0;
$pre_selected_hosting_id = isset($_GET['hosting_id']) ? intval($_GET['hosting_id']) : 0;
$pre_selected_maintenance_id = isset($_GET['maintenance_id']) ? intval($_GET['maintenance_id']) : 0;

// Get redirect URL from HTTP_REFERER
$redirect_url = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer = esc_url($_SERVER['HTTP_REFERER']);
    // Validate that referer is from same domain
    if (strpos($referer, home_url()) === 0) {
        $redirect_url = $referer;
    }
}

// Get owner_user_id from the pre-selected items
$pre_selected_owner_id = 0;
if ($pre_selected_domain_id) {
    $domain = $wpdb->get_row($wpdb->prepare("SELECT owner_user_id FROM $domains_table WHERE id = %d", $pre_selected_domain_id));
    if ($domain) $pre_selected_owner_id = $domain->owner_user_id;
}
if (!$pre_selected_owner_id && $pre_selected_hosting_id) {
    $hosting = $wpdb->get_row($wpdb->prepare("SELECT owner_user_id FROM $hostings_table WHERE id = %d", $pre_selected_hosting_id));
    if ($hosting) $pre_selected_owner_id = $hosting->owner_user_id;
}
if (!$pre_selected_owner_id && $pre_selected_maintenance_id) {
    $maintenance = $wpdb->get_row($wpdb->prepare("SELECT owner_user_id FROM $maintenance_table WHERE id = %d", $pre_selected_maintenance_id));
    if ($maintenance) $pre_selected_owner_id = $maintenance->owner_user_id;
}

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_website_field']) && wp_verify_nonce($_POST['add_website_field'], 'add_website')) {
        $name = sanitize_text_field($_POST['website_name']);
        $owner_user_id = intval($_POST['owner_user_id']);
        $domain_id = !empty($_POST['domain_id']) ? intval($_POST['domain_id']) : null;
        $hosting_id = !empty($_POST['hosting_id']) ? intval($_POST['hosting_id']) : null;
        $maintenance_package_id = !empty($_POST['maintenance_package_id']) ? intval($_POST['maintenance_package_id']) : null;
        $admin_url = esc_url_raw($_POST['admin_url']);
        $admin_username = sanitize_text_field($_POST['admin_username']);
        $admin_password = sanitize_text_field($_POST['admin_password']);
        $ip_address = sanitize_text_field($_POST['ip_address']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        // Validate required fields
         if (!empty($name) && !empty($owner_user_id)) {
             // Get current admin user's inova_user_id for created_by field
             $admin_inova_user = get_inova_user($current_user_id);
             $created_by = $admin_inova_user ? $admin_inova_user->id : null;
             
             // Encrypt admin_password before inserting
             $encrypted_password = !empty($admin_password) ? im_encrypt_password($admin_password) : '';
             
             $result = $wpdb->insert(
                 $websites_table,
                 array(
                     'name' => $name,
                     'owner_user_id' => $owner_user_id,
                     'created_by' => $created_by,
                     'domain_id' => $domain_id,
                     'hosting_id' => $hosting_id,
                     'maintenance_package_id' => $maintenance_package_id,
                     'admin_url' => $admin_url,
                     'admin_username' => $admin_username,
                     'admin_password' => $encrypted_password,
                     'ip_address' => $ip_address,
                     'notes' => $notes,
                     'created_at' => current_time('mysql'),
                     'updated_at' => current_time('mysql')
                 ),
                 array(
                     '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                 )
             );
            
            if ($result) {
                $new_website_id = $wpdb->insert_id;
                
                // Update domain if provided
                if (!empty($domain_id)) {
                    $wpdb->update(
                        $domains_table,
                        array('website_id' => $new_website_id),
                        array('id' => $domain_id),
                        array('%d'),
                        array('%d')
                    );
                }
                
                // Redirect to previous page or website list immediately
                $redirect_url = isset($_POST['redirect_url']) && !empty($_POST['redirect_url']) 
                    ? esc_url($_POST['redirect_url']) 
                    : home_url('/list-website/');
                wp_redirect($redirect_url);
                exit;
            } else {
                $notification = '<div class="alert alert-danger" role="alert">Đã có lỗi xảy ra khi thêm mới website. Vui lòng thử lại.</div>';
            }
        } else {
            $notification = '<div class="alert alert-warning" role="alert">Vui lòng điền đầy đủ thông tin bắt buộc.</div>';
        }
    }
}

get_header();
?>
<div class="content-wrapper mt-5">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-column">
                <!-- Add back button in the left side -->
                <a href="<?php echo home_url('/list-website/'); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Thêm mới website</h3>
                </div>
            </div>
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php
                    if (isset($notification)) {
                        echo $notification;
                    }
                    ?>
                    <form class="forms-sample col-md-8 col-lg-10 d-flex flex-column" action="" method="post">
                        <!-- Store previous page URL -->
                        <input type="hidden" name="redirect_url" id="redirect_url" value="<?php echo $redirect_url; ?>">
                        <div class="row">
                            <!-- Website Information Section -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-gradient-primary text-white">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-globe me-2"></i>
                                            Thông tin Website
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="website_name" class="fw-bold">Tên website <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="website_name" name="website_name" 
                                                  placeholder="Tên website" required>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="owner_user_id" class="fw-bold">Chủ sở hữu <span class="text-danger">*</span></label>
                                            <select class="js-example-basic-single w-100" name="owner_user_id" id="owner_user_id" required>
                                                <option value="">-- Chọn chủ sở hữu --</option>
                                                <?php
                                                $users = $wpdb->get_results("SELECT * FROM $users_table WHERE status = 'ACTIVE' ORDER BY user_code ASC");
                                                foreach ($users as $user) {
                                                    $selected = ($pre_selected_owner_id == $user->id) ? 'selected' : '';
                                                    echo '<option value="' . $user->id . '" ' . $selected . '>' . esc_html($user->user_code . ' - ' . $user->name) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="domain_id" class="fw-bold">Tên miền liên kết</label>
                                            <select class="js-example-basic-single w-100" name="domain_id" id="domain_id">
                                                <option value="">-- Chọn tên miền --</option>
                                                <?php
                                                // Get unique domains with one linked website name (if any), filter by owner if pre-selected
                                                $domain_query = "SELECT d.*,
                                                                        (SELECT w2.name FROM $websites_table w2 WHERE w2.domain_id = d.id LIMIT 1) AS linked_website_name
                                                                 FROM $domains_table d
                                                                 WHERE 1=1";
                                                if ($pre_selected_owner_id) {
                                                    $domain_query .= " AND d.owner_user_id = " . intval($pre_selected_owner_id);
                                                }
                                                $domain_query .= " ORDER BY d.domain_name ASC";

                                                $domains = $wpdb->get_results($domain_query);
                                                foreach ($domains as $domain) {
                                                    $selected = ($pre_selected_domain_id == $domain->id) ? 'selected' : '';

                                                    $display_text = $domain->domain_name;
                                                    if (!empty($domain->linked_website_name)) {
                                                        $display_text .= ' (Đã liên kết: ' . $domain->linked_website_name . ')';
                                                    }

                                                    echo '<option value="' . $domain->id . '" data-owner="' . $domain->owner_user_id . '" ' . $selected . '>' . esc_html($display_text) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="hosting_id" class="fw-bold">Hosting liên kết</label>
                                            <select class="js-example-basic-single w-100" name="hosting_id" id="hosting_id">
                                                <option value="">-- Chọn hosting --</option>
                                                <?php
                                                // Get unique hostings with one linked website name (if any), filter by owner if pre-selected
                                                $hosting_query = "SELECT h.*,
                                                                         pc.name AS product_name,
                                                                         (SELECT w2.name FROM $websites_table w2 WHERE w2.hosting_id = h.id LIMIT 1) AS linked_website_name
                                                                  FROM $hostings_table h
                                                                  LEFT JOIN $products_table pc ON h.product_catalog_id = pc.id
                                                                  WHERE 1=1";
                                                if ($pre_selected_owner_id) {
                                                    $hosting_query .= " AND h.owner_user_id = " . intval($pre_selected_owner_id);
                                                }
                                                $hosting_query .= " ORDER BY h.hosting_code";

                                                $hostings = $wpdb->get_results($hosting_query);

                                                foreach ($hostings as $hosting) {
                                                    $product_name = $hosting->product_name ?: 'Unknown Product';
                                                    $selected = ($pre_selected_hosting_id == $hosting->id) ? 'selected' : '';
                                                    $hosting_code = !empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id;

                                                    $display_text = $hosting_code . ' - ' . $product_name;
                                                    if (!empty($hosting->linked_website_name)) {
                                                        $display_text .= ' (Đã liên kết: ' . $hosting->linked_website_name . ')';
                                                    }

                                                    echo '<option value="' . $hosting->id . '" data-owner="' . $hosting->owner_user_id . '" ' . $selected . '>' .
                                                         esc_html($display_text) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="maintenance_package_id" class="fw-bold">Gói bảo trì</label>
                                            <select class="js-example-basic-single w-100" name="maintenance_package_id" id="maintenance_package_id">
                                                <option value="">-- Chọn gói bảo trì --</option>
                                                <?php
                                                // Get unique maintenance packages with one linked website name (if any), filter by owner if pre-selected
                                                $maintenance_query = "SELECT m.*,
                                                                             (SELECT w2.name FROM $websites_table w2 WHERE w2.maintenance_package_id = m.id LIMIT 1) AS linked_website_name
                                                                      FROM $maintenance_table m
                                                                      WHERE 1=1";
                                                if ($pre_selected_owner_id) {
                                                    $maintenance_query .= " AND m.owner_user_id = " . intval($pre_selected_owner_id);
                                                }
                                                $maintenance_query .= " ORDER BY m.order_code";

                                                $maintenance_packages = $wpdb->get_results($maintenance_query);

                                                foreach ($maintenance_packages as $package) {
                                                    $selected = ($pre_selected_maintenance_id == $package->id) ? 'selected' : '';
                                                    $display_name = !empty($package->order_code) ? $package->order_code : 'MAINT-' . $package->id;

                                                    $display_text = $display_name . ' - Gói bảo trì website';
                                                    if (!empty($package->linked_website_name)) {
                                                        $display_text .= ' (Đã liên kết: ' . $package->linked_website_name . ')';
                                                    }

                                                    echo '<option value="' . $package->id . '" data-owner="' . $package->owner_user_id . '" ' . $selected . '>' . esc_html($display_text) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="notes" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control height-auto" id="notes" rows="3" placeholder="Ghi chú" name="notes"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Website Administration Section -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="d-flex card-header btn-secondary">
                                        <h5 class="mt-1 mb-1">
                                            <i class="ph ph-gear me-2"></i>
                                            Thông tin quản trị
                                        </h5>
                                        <span class="badge border-radius-9 bg-inverse-info text-info ms-2">Chỉ admin có thể thấy</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="admin_url" class="fw-bold">URL trang quản trị</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ph ph-link"></i></span>
                                                <input type="url" class="form-control" id="admin_url" name="admin_url" 
                                                      placeholder="https://example.com/wp-admin/">
                                            </div>
                                            <small class="form-text text-muted">Địa chỉ trang quản trị website</small>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="admin_username" class="fw-bold">Tên đăng nhập quản trị</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ph ph-user"></i></span>
                                                <input type="text" class="form-control" id="admin_username" name="admin_username" 
                                                      placeholder="admin">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="admin_password" class="fw-bold">Mật khẩu quản trị</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="admin_password" name="admin_password" 
                                                      placeholder="Nhập mật khẩu quản trị">
                                                <button class="btn btn-secondary toggle-password" type="button">
                                                    <i class="ph ph-eye"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="ip_address" class="fw-bold">Địa chỉ IP</label>
                                            <input type="text" class="form-control" id="ip_address" name="ip_address" placeholder="Nhập địa chỉ IP">
                                            <small class="form-text text-muted">Địa chỉ IP gốc của website (không qua Cloudflare)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        wp_nonce_field('add_website', 'add_website_field');
                        ?>
                        
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit"
                                class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Lưu website</span>
                            </button>
                            <a href="<?php echo home_url('/list-website/'); ?>" class="btn btn-light btn-icon-text ms-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-x btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Hủy</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
?>

<script>
$(document).ready(function() {
    // Store all original options for filtering - Load all options regardless of pre-selection
    var allDomains = $('<select>');
    <?php
    // Load unique domains with one linked website name (if any) for JavaScript
    $all_domains = $wpdb->get_results("
        SELECT d.*,
               (SELECT w2.name FROM $websites_table w2 WHERE w2.domain_id = d.id LIMIT 1) AS linked_website_name
        FROM $domains_table d
        ORDER BY d.domain_name ASC
    ");
    foreach ($all_domains as $domain) {
        $display_text = $domain->domain_name;
        if (!empty($domain->linked_website_name)) {
            $display_text .= ' (Đã liên kết: ' . $domain->linked_website_name . ')';
        }
        $display_text = esc_js($display_text);
        echo "allDomains.append('<option value=\"{$domain->id}\" data-owner=\"{$domain->owner_user_id}\">{$display_text}</option>');";
    }
    ?>
    
    var allHostings = $('<select>');
    <?php
    // Load unique hostings with one linked website name (if any) for JavaScript
    $all_hostings = $wpdb->get_results("
        SELECT h.*,
               pc.name AS product_name,
               (SELECT w2.name FROM $websites_table w2 WHERE w2.hosting_id = h.id LIMIT 1) AS linked_website_name
        FROM $hostings_table h
        LEFT JOIN $products_table pc ON h.product_catalog_id = pc.id
        ORDER BY h.hosting_code
    ");
    foreach ($all_hostings as $hosting) {
        $product_name = $hosting->product_name ?: 'Unknown Product';
        $hosting_code = !empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id;
        $display_text = $hosting_code . ' - ' . $product_name;
        if (!empty($hosting->linked_website_name)) {
            $display_text .= ' (Đã liên kết: ' . $hosting->linked_website_name . ')';
        }
        $display_text = esc_js($display_text);
        echo "allHostings.append('<option value=\"{$hosting->id}\" data-owner=\"{$hosting->owner_user_id}\">{$display_text}</option>');";
    }
    ?>

    var allMaintenancePackages = $('<select>');
    <?php
    // Load unique maintenance packages with one linked website name (if any) for JavaScript
    $all_maintenance = $wpdb->get_results("
        SELECT m.*,
               (SELECT w2.name FROM $websites_table w2 WHERE w2.maintenance_package_id = m.id LIMIT 1) AS linked_website_name
        FROM $maintenance_table m
        ORDER BY m.order_code
    ");
    foreach ($all_maintenance as $package) {
        $display_name = !empty($package->order_code) ? $package->order_code : 'MAINT-' . $package->id;
        $display_text = $display_name . ' - Gói bảo trì website';
        if (!empty($package->linked_website_name)) {
            $display_text .= ' (Đã liên kết: ' . $package->linked_website_name . ')';
        }
        $display_text = esc_js($display_text);
        echo "allMaintenancePackages.append('<option value=\"{$package->id}\" data-owner=\"{$package->owner_user_id}\">{$display_text}</option>');";
    }
    ?>
    
    // Function to filter products by owner
    function filterProductsByOwner(ownerId) {
        // Filter domains
        $('#domain_id').empty().append('<option value="">-- Chọn tên miền --</option>');
        allDomains.find('option').each(function() {
            if ($(this).data('owner') == ownerId) {
                $('#domain_id').append($(this).clone());
            }
        });
        
        // Filter hostings
        $('#hosting_id').empty().append('<option value="">-- Chọn hosting --</option>');
        allHostings.find('option').each(function() {
            if ($(this).data('owner') == ownerId) {
                $('#hosting_id').append($(this).clone());
            }
        });
        
        // Filter maintenance packages
        $('#maintenance_package_id').empty().append('<option value="">-- Chọn gói bảo trì --</option>');
        allMaintenancePackages.find('option').each(function() {
            if ($(this).data('owner') == ownerId) {
                $('#maintenance_package_id').append($(this).clone());
            }
        });
        
        // Reinitialize Select2 if it's being used
        if (typeof $.fn.select2 !== 'undefined') {
            $('#domain_id, #hosting_id, #maintenance_package_id').trigger('change.select2');
        }
    }
    
    // Handle owner selection change
    $('#owner_user_id').on('change', function() {
        var selectedOwnerId = $(this).val();
        
        if (selectedOwnerId) {
            filterProductsByOwner(selectedOwnerId);
        } else {
            // If no owner selected, show all available products
            $('#domain_id').empty().append('<option value="">-- Chọn tên miền --</option>').append(allDomains.find('option').clone());
            $('#hosting_id').empty().append('<option value="">-- Chọn hosting --</option>').append(allHostings.find('option').clone());
            $('#maintenance_package_id').empty().append('<option value="">-- Chọn gói bảo trì --</option>').append(allMaintenancePackages.find('option').clone());
            
            if (typeof $.fn.select2 !== 'undefined') {
                $('#domain_id, #hosting_id, #maintenance_package_id').trigger('change.select2');
            }
        }
    });
    
    // If owner is pre-selected, filter products on page load and auto-select pre-selected items
    var preSelectedOwner = $('#owner_user_id').val();
    if (preSelectedOwner) {
        filterProductsByOwner(preSelectedOwner);
        
        // Auto-select pre-selected products after filtering
        <?php if ($pre_selected_domain_id): ?>
        $('#domain_id').val('<?php echo $pre_selected_domain_id; ?>');
        <?php endif; ?>
        
        <?php if ($pre_selected_hosting_id): ?>
        $('#hosting_id').val('<?php echo $pre_selected_hosting_id; ?>');
        <?php endif; ?>
        
        <?php if ($pre_selected_maintenance_id): ?>
        $('#maintenance_package_id').val('<?php echo $pre_selected_maintenance_id; ?>');
        <?php endif; ?>
        
        // Reinitialize Select2 after setting values
        if (typeof $.fn.select2 !== 'undefined') {
            $('#domain_id, #hosting_id, #maintenance_package_id').trigger('change.select2');
        }
    }
});
</script>