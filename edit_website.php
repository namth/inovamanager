<?php
/* 
    Template Name: Edit Website
*/

global $wpdb;
$websites_table     = $wpdb->prefix . 'im_websites';
$users_table        = $wpdb->prefix . 'im_users';
$domains_table      = $wpdb->prefix . 'im_domains';
$hostings_table     = $wpdb->prefix . 'im_hostings';
$maintenance_table  = $wpdb->prefix . 'im_maintenance_packages';
$products_table     = $wpdb->prefix . 'im_product_catalog';

$current_user_id = get_current_user_id();
$website_id = isset($_GET['website_id']) ? intval($_GET['website_id']) : 0;

// Store referrer URL for redirect after submit
$referrer_url = isset($_GET['referrer']) ? esc_url_raw($_GET['referrer']) : '';
if (empty($referrer_url) && isset($_SERVER['HTTP_REFERER'])) {
    $referrer_url = esc_url_raw($_SERVER['HTTP_REFERER']);
}
// Default to list-website if no referrer
if (empty($referrer_url)) {
    $referrer_url = home_url('/list-website/');
}

// Redirect if website ID not provided
if (empty($website_id)) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

// Get the website data
$website = $wpdb->get_row($wpdb->prepare("
    SELECT 
        w.*,
        u.name AS owner_name,
        u.user_code AS owner_code,
        d.domain_name,
        h.hosting_code,
        m.order_code AS maintenance_code
    FROM 
        $websites_table w
    LEFT JOIN 
        $users_table u ON w.owner_user_id = u.id
    LEFT JOIN 
        $domains_table d ON w.domain_id = d.id
    LEFT JOIN 
        $hostings_table h ON w.hosting_id = h.id
    LEFT JOIN 
        $maintenance_table m ON w.maintenance_package_id = m.id
    WHERE
        w.id = %d
", $website_id));

// Redirect if website not found
if (!$website) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit_website_field']) && wp_verify_nonce($_POST['edit_website_field'], 'edit_website')) {
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
             $data = array(
                 'name' => $name,
                 'owner_user_id' => $owner_user_id,
                 'domain_id' => $domain_id,
                 'hosting_id' => $hosting_id,
                 'maintenance_package_id' => $maintenance_package_id,
                 'admin_url' => $admin_url,
                 'admin_username' => $admin_username,
                 'ip_address' => $ip_address,
                 'notes' => $notes,
                 'updated_at' => current_time('mysql')
             );

             // Only update password if a new one was provided
             if (!empty($admin_password)) {
                 $data['admin_password'] = im_encrypt_password($admin_password);
             }

            $format = array(
                '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'
            );
            
            $result = $wpdb->update(
                $websites_table,
                $data,
                array('id' => $website_id),
                $format,
                array('%d')
            );
            
            if ($result !== false) {
                // Check if owner changed
                $owner_changed = ($website->owner_user_id != $owner_user_id);
                
                // Update existing domain if applicable
                if (!empty($website->domain_id) && $domain_id != $website->domain_id) {
                    // Clear website_id from old domain
                    $wpdb->update(
                        $domains_table,
                        array('website_id' => null),
                        array('id' => $website->domain_id),
                        array('%d'),
                        array('%d')
                    );
                }

                // Link new domain if selected
                if (!empty($domain_id)) {
                    $wpdb->update(
                        $domains_table,
                        array('website_id' => $website_id),
                        array('id' => $domain_id),
                        array('%d'),
                        array('%d')
                    );
                }

                // Update owner of linked service packages if owner changed
                if ($owner_changed) {
                    // Update domain owner
                    if (!empty($domain_id)) {
                        $wpdb->update(
                            $domains_table,
                            array('owner_user_id' => $owner_user_id),
                            array('id' => $domain_id),
                            array('%d'),
                            array('%d')
                        );
                    }

                    // Update hosting owner
                    if (!empty($hosting_id)) {
                        $wpdb->update(
                            $hostings_table,
                            array('owner_user_id' => $owner_user_id),
                            array('id' => $hosting_id),
                            array('%d'),
                            array('%d')
                        );
                    }

                    // Update maintenance package owner
                    if (!empty($maintenance_package_id)) {
                        $wpdb->update(
                            $maintenance_table,
                            array('owner_user_id' => $owner_user_id),
                            array('id' => $maintenance_package_id),
                            array('%d'),
                            array('%d')
                        );
                    }
                }

                // Auto-redirect to previous page or website list after 1.5 seconds
                $redirect_url = isset($_POST['redirect_url']) && !empty($_POST['redirect_url']) 
                    ? esc_url($_POST['redirect_url']) 
                    : home_url('/list-website/');
                echo '<script>
                    setTimeout(function() {
                        window.location.href = "' . $redirect_url . '";
                    }, 1500);
                </script>';
            } else {
                $notification = '<div class="alert alert-danger" role="alert">Đã có lỗi xảy ra khi cập nhật website. Vui lòng thử lại.</div>';
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
                <a href="<?php echo esc_url($referrer_url); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Sửa website</h3>
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
                        <input type="hidden" name="redirect_url" id="redirect_url" value="">
                        <div class="row">
                            <!-- Website Information Section -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header btn-primary">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-globe me-2"></i>
                                            Thông tin Website
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="website_name" class="fw-bold">Tên website <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="website_name" name="website_name" 
                                                  placeholder="Tên website" value="<?php echo esc_attr($website->name); ?>" required>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="owner_user_id" class="fw-bold">Chủ sở hữu <span class="text-danger">*</span></label>
                                            <select class="js-example-basic-single w-100" name="owner_user_id" id="owner_user_id" required>
                                                <option value="">-- Chọn chủ sở hữu --</option>
                                                <?php
                                                $users = $wpdb->get_results("SELECT * FROM $users_table WHERE status = 'ACTIVE' ORDER BY user_code ASC");
                                                foreach ($users as $user) {
                                                    $selected = ($user->id == $website->owner_user_id) ? 'selected' : '';
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
                                                // Get unique domains with one linked website name (if any), filter by owner
                                                $domains = $wpdb->get_results($wpdb->prepare("
                                                    SELECT d.*,
                                                           (SELECT w2.name FROM $websites_table w2 WHERE w2.domain_id = d.id AND w2.id != %d LIMIT 1) AS linked_website_name
                                                    FROM $domains_table d
                                                    WHERE d.owner_user_id = %d
                                                    ORDER BY d.domain_name ASC
                                                ", $website_id, $website->owner_user_id));

                                                foreach ($domains as $domain) {
                                                    $selected = ($domain->id == $website->domain_id) ? 'selected' : '';

                                                    $display_text = $domain->domain_name;
                                                    if (!empty($domain->linked_website_name)) {
                                                        $display_text .= ' (Đã liên kết: ' . $domain->linked_website_name . ')';
                                                    }

                                                    echo '<option value="' . $domain->id . '" ' . $selected . '>' . esc_html($display_text) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="hosting_id" class="fw-bold">Hosting liên kết</label>
                                            <select class="js-example-basic-single w-100" name="hosting_id" id="hosting_id">
                                                <option value="">-- Chọn hosting --</option>
                                                <?php
                                                // Get unique hostings with one linked website name (if any), filter by owner
                                                $hostings = $wpdb->get_results($wpdb->prepare("
                                                    SELECT h.*,
                                                           pc.name AS product_name,
                                                           (SELECT w2.name FROM $websites_table w2 WHERE w2.hosting_id = h.id AND w2.id != %d LIMIT 1) AS linked_website_name
                                                    FROM $hostings_table h
                                                    LEFT JOIN $products_table pc ON h.product_catalog_id = pc.id
                                                    WHERE h.owner_user_id = %d
                                                    ORDER BY h.hosting_code
                                                ", $website_id, $website->owner_user_id));

                                                foreach ($hostings as $hosting) {
                                                    $product_name = $hosting->product_name ?: 'Unknown Product';
                                                    $hosting_code = !empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id;

                                                    $display_text = $hosting_code . ' - ' . $product_name;
                                                    if (!empty($hosting->linked_website_name)) {
                                                        $display_text .= ' (Đã liên kết: ' . $hosting->linked_website_name . ')';
                                                    }

                                                    $selected = ($hosting->id == $website->hosting_id) ? 'selected' : '';
                                                    echo '<option value="' . $hosting->id . '" ' . $selected . '>' .
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
                                                // Get unique maintenance packages with one linked website name (if any), filter by owner
                                                $maintenance_packages = $wpdb->get_results($wpdb->prepare("
                                                    SELECT m.*,
                                                           (SELECT w2.name FROM $websites_table w2 WHERE w2.maintenance_package_id = m.id AND w2.id != %d LIMIT 1) AS linked_website_name
                                                    FROM $maintenance_table m
                                                    WHERE m.owner_user_id = %d
                                                    ORDER BY m.order_code
                                                ", $website_id, $website->owner_user_id));

                                                foreach ($maintenance_packages as $package) {
                                                    $display_name = !empty($package->order_code) ? $package->order_code : 'MAINT-' . $package->id;

                                                    $display_text = $display_name . ' - Gói bảo trì website';
                                                    if (!empty($package->linked_website_name)) {
                                                        $display_text .= ' (Đã liên kết: ' . $package->linked_website_name . ')';
                                                    }

                                                    $selected = ($package->id == $website->maintenance_package_id) ? 'selected' : '';
                                                    echo '<option value="' . $package->id . '" ' . $selected . '>' . esc_html($display_text) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="notes" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control height-auto" id="notes" rows="3" placeholder="Ghi chú" name="notes"><?php echo esc_textarea($website->notes); ?></textarea>
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
                                                      placeholder="https://example.com/wp-admin/" value="<?php echo esc_url($website->admin_url); ?>">
                                            </div>
                                            <small class="form-text text-muted">Địa chỉ trang quản trị website</small>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="admin_username" class="fw-bold">Tên đăng nhập quản trị</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ph ph-user"></i></span>
                                                <input type="text" class="form-control" id="admin_username" name="admin_username" 
                                                      placeholder="admin" value="<?php echo esc_attr($website->admin_username); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="admin_password" class="fw-bold">Mật khẩu quản trị</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="admin_password" name="admin_password"
                                                      placeholder="Để trống nếu không muốn thay đổi">
                                                <button class="btn btn-secondary toggle-password" type="button">
                                                    <i class="ph ph-eye"></i>
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">Để trống nếu bạn không muốn thay đổi mật khẩu.</small>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="ip_address" class="fw-bold">Địa chỉ IP</label>
                                            <input type="text" class="form-control" id="ip_address" name="ip_address"
                                                  placeholder="Nhập địa chỉ IP" value="<?php echo esc_attr($website->ip_address); ?>">
                                            <small class="form-text text-muted">Địa chỉ IP gốc của website (không qua Cloudflare)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        wp_nonce_field('edit_website', 'edit_website_field');
                        ?>
                        <input type="hidden" name="referrer_url" value="<?php echo esc_attr($referrer_url); ?>">
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit"
                                class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Cập nhật website</span>
                            </button>
                            <a href="<?php echo esc_url($referrer_url); ?>" class="btn btn-light btn-icon-text ms-2 d-flex align-items-center border-radius-9">
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

<script>
jQuery(document).ready(function($) {
    // Set redirect URL from previous page stored in sessionStorage
    const previousUrl = sessionStorage.getItem('previousPageUrl') || document.referrer;
    if (previousUrl && previousUrl.includes(window.location.hostname)) {
        document.getElementById('redirect_url').value = previousUrl;
    }
    
    // Store current page in sessionStorage before leaving
    $(document).on('click', 'a', function() {
        sessionStorage.setItem('previousPageUrl', window.location.href);
    });
});
</script>

<?php
get_footer();
?>