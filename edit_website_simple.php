<?php
/* 
    Template Name: Edit Website (User)
*/

global $wpdb;
$websites_table = $wpdb->prefix . 'im_websites';

$current_user_id = get_current_user_id();
$website_id = isset($_GET['website_id']) ? intval($_GET['website_id']) : 0;

$inova_user_id = get_user_inova_id($current_user_id);
// Get website data
$website = $wpdb->get_row($wpdb->prepare("SELECT * FROM $websites_table WHERE id = %d", $website_id));

// Check if website exists
if (!$website) {
    wp_redirect(home_url('/list-website/'));
    exit;
}
// print_r($website);
// Permission check - user/partner can edit their own websites or managed services
$permission_where = get_website_permission_where_clause();
$user_has_access = false;

if (is_inova_admin()) {
    $user_has_access = true;
} else {
    // Check if user owns the website
    if ($website->owner_user_id == $inova_user_id) {
        $user_has_access = true;
    } else {
        // Check if partner manages hosting or maintenance for this website
        $hosting_check = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}im_hostings WHERE id = %d AND partner_id = %d",
            $website->hosting_id,
            $inova_user_id
        ));
        
        $maintenance_check = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}im_maintenance_packages WHERE id = %d AND partner_id = %d",
            $website->maintenance_package_id,
            $inova_user_id
        ));
        
        if ($hosting_check || $maintenance_check) {
            $user_has_access = true;
        }
    }
}

if (!$user_has_access) {
    wp_die('Bạn không có quyền sửa website này.');
}

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit_website_simple_field']) && wp_verify_nonce($_POST['edit_website_simple_field'], 'edit_website_simple')) {
        $name = sanitize_text_field($_POST['website_name']);
        $admin_url = esc_url_raw($_POST['admin_url']);
        $admin_username = sanitize_text_field($_POST['admin_username']);
        $admin_password = sanitize_text_field($_POST['admin_password']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        // Validate required fields (only website name is required)
         if (!empty($name)) {
             // Encrypt admin_password before updating if provided
             $encrypted_password = !empty($admin_password) ? im_encrypt_password($admin_password) : $website->admin_password;
             
             $result = $wpdb->update(
                 $websites_table,
                 array(
                     'name' => $name,
                     'admin_url' => $admin_url,
                     'admin_username' => $admin_username,
                     'admin_password' => $encrypted_password,
                     'notes' => $notes,
                     'updated_at' => current_time('mysql')
                 ),
                 array('id' => $website_id),
                 array(
                     '%s', '%s', '%s', '%s', '%s', '%s'
                 ),
                 array('%d')
             );
            
            if ($result !== false) {
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
            $notification = '<div class="alert alert-warning" role="alert">Vui lòng nhập tên website (*).</div>';
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
                    <form class="forms-sample col-md-6 col-lg-7 d-flex flex-column" action="" method="post">
                        <!-- Store previous page URL -->
                        <input type="hidden" name="redirect_url" id="redirect_url" value="">
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
                                          placeholder="Nhập tên website" value="<?php echo esc_attr($website->name); ?>" required>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="admin_url" class="fw-bold">URL trang quản trị</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ph ph-link"></i></span>
                                        <input type="url" class="form-control" id="admin_url" name="admin_url" 
                                              placeholder="https://example.com/wp-admin/" value="<?php echo esc_attr($website->admin_url); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="admin_username" class="fw-bold">Tên đăng nhập</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ph ph-user"></i></span>
                                        <input type="text" class="form-control" id="admin_username" name="admin_username" 
                                              placeholder="Nhập tên đăng nhập" value="<?php echo esc_attr($website->admin_username); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="admin_password" class="fw-bold">Mật khẩu</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="admin_password" name="admin_password" 
                                              placeholder="Nhập mật khẩu quản trị" value="<?php echo esc_attr(im_decrypt_password($website->admin_password)); ?>">
                                        <button class="btn btn-secondary toggle-password" type="button">
                                            <i class="ph ph-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="notes" class="fw-bold">Ghi chú</label>
                                    <textarea class="form-control height-auto" id="notes" rows="3" placeholder="Nhập ghi chú (không bắt buộc)" name="notes"><?php echo esc_textarea($website->notes); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        wp_nonce_field('edit_website_simple', 'edit_website_simple_field');
                        ?>
                        
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


