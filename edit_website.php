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
                'notes' => $notes,
                'updated_at' => current_time('mysql')
            );
            
            // Only update password if a new one was provided
            if (!empty($admin_password)) {
                $data['admin_password'] = $admin_password;
            }
            
            $format = array(
                '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s'
            );
            
            $result = $wpdb->update(
                $websites_table,
                $data,
                array('id' => $website_id),
                $format,
                array('%d')
            );
            
            if ($result !== false) {
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
                
                // Redirect back to website list after successful update
                wp_redirect(home_url('/list-website/'));
                exit;
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
                <a href="<?php echo home_url('/list-website/'); ?>" class="abs-top-left nav-link">
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
                                                // Get domains that are either unassigned or assigned to this website
                                                $domains = $wpdb->get_results("SELECT * FROM $domains_table ORDER BY domain_name ASC");
                                                
                                                foreach ($domains as $domain) {
                                                    $selected = ($domain->id == $website->domain_id) ? 'selected' : '';
                                                    $domain_code = !empty($domain->order_code) ? $domain->order_code : 'DOM-' . $domain->id;
                                                    echo '<option value="' . $domain->id . '" ' . $selected . '>' . esc_html($domain_code . ' - ' . $domain->domain_name) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="hosting_id" class="fw-bold">Hosting liên kết</label>
                                            <select class="js-example-basic-single w-100" name="hosting_id" id="hosting_id">
                                                <option value="">-- Chọn hosting --</option>
                                                <?php
                                                // Get hostings that are either unassigned or assigned to this website
                                                $hostings = $wpdb->get_results($wpdb->prepare(
                                                    "SELECT * FROM $hostings_table WHERE id NOT IN 
                                                    (SELECT hosting_id FROM $websites_table WHERE hosting_id IS NOT NULL AND id != %d) OR id = %d",
                                                    $website_id, $website->hosting_id
                                                ));
                                                
                                                foreach ($hostings as $hosting) {
                                                    // get product_catalog_id from hosting, then get product name
                                                    $product_catalog = $wpdb->get_row($wpdb->prepare(
                                                        "SELECT * FROM $products_table WHERE id = %d", $hosting->product_catalog_id
                                                    ));
                                                    
                                                    $product_name = $product_catalog ? $product_catalog->name : 'Unknown Product';
                                                    $hosting_code = !empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id;
                                                    
                                                    $selected = ($hosting->id == $website->hosting_id) ? 'selected' : '';
                                                    echo '<option value="' . $hosting->id . '" ' . $selected . '>' . 
                                                         esc_html($hosting_code . ' - ' . $product_name) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="maintenance_package_id" class="fw-bold">Gói bảo trì</label>
                                            <select class="js-example-basic-single w-100" name="maintenance_package_id" id="maintenance_package_id">
                                                <option value="">-- Chọn gói bảo trì --</option>
                                                <?php
                                                // Get maintenance packages that are either unassigned or assigned to this website
                                                $maintenance_packages = $wpdb->get_results($wpdb->prepare(
                                                    "SELECT * FROM $maintenance_table WHERE id NOT IN 
                                                    (SELECT maintenance_package_id FROM $websites_table WHERE maintenance_package_id IS NOT NULL AND id != %d) OR id = %d",
                                                    $website_id, $website->maintenance_package_id
                                                ));

                                                foreach ($maintenance_packages as $package) {
                                                    // get product_catalog_id from maintenance_package, then get product name
                                                    $product_catalog = $wpdb->get_row($wpdb->prepare(
                                                        "SELECT * FROM $products_table WHERE id = %d", $package->product_catalog_id
                                                    ));

                                                    $product_name = $product_catalog ? $product_catalog->name : 'Unknown Product';
                                                    $display_name = !empty($package->order_code) ? $package->order_code : 'MAINT-' . $package->id;

                                                    $selected = ($package->id == $website->maintenance_package_id) ? 'selected' : '';
                                                    echo '<option value="' . $package->id . '" ' . $selected . '>' . esc_html($display_name . ' - Gói bảo trì website') . '</option>';
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

                                        <hr class="my-4">

                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Kiểm tra trạng thái website</label>
                                            <div class="d-flex align-items-center mt-2">
                                                <button type="button" id="checkWebsiteStatus" class="btn btn-info btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                                    <i class="ph ph-activity btn-icon-prepend fa-150p"></i>
                                                    <span>Kiểm tra hoạt động</span>
                                                </button>
                                                <span id="statusResult" class="ms-3"></span>
                                            </div>
                                            <div id="statusDetails" class="mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        wp_nonce_field('edit_website', 'edit_website_field');
                        ?>
                        <input type="hidden" id="check_website_status_nonce" value="<?php echo wp_create_nonce('check_website_status_nonce'); ?>">
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit"
                                class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Cập nhật website</span>
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