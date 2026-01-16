<?php
/* 
    Template Name: Add New Website (User)
*/

global $wpdb;
$websites_table = $wpdb->prefix . 'im_websites';

$current_user_id = get_current_user_id();

$inova_user_id = get_user_inova_id($current_user_id);

// Get redirect URL from HTTP_REFERER
$redirect_url = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer = esc_url($_SERVER['HTTP_REFERER']);
    // Validate that referer is from same domain
    if (strpos($referer, home_url()) === 0) {
        $redirect_url = $referer;
    }
}

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_website_simple_field']) && wp_verify_nonce($_POST['add_website_simple_field'], 'add_website_simple')) {
        $name = sanitize_text_field($_POST['website_name']);
        $admin_url = esc_url_raw($_POST['admin_url']);
        $admin_username = sanitize_text_field($_POST['admin_username']);
        $admin_password = sanitize_text_field($_POST['admin_password']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        // Validate required fields (only website name is required)
          if (!empty($name)) {
              // Encrypt admin_password before inserting
              $encrypted_password = !empty($admin_password) ? im_encrypt_password($admin_password) : '';
              
              $result = $wpdb->insert(
                  $websites_table,
                  array(
                      'name' => $name,
                      'owner_user_id' => $inova_user_id,
                      'created_by' => $current_user_id,
                      'admin_url' => $admin_url,
                      'admin_username' => $admin_username,
                      'admin_password' => $encrypted_password,
                      'notes' => $notes,
                      'created_at' => current_time('mysql'),
                      'updated_at' => current_time('mysql')
                  ),
                  array(
                      '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'
                  )
              );
            
            if ($result) {
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
                    <form class="forms-sample col-md-7 col-lg-8 col-12 d-flex flex-column" action="" method="post">
                        <!-- Store previous page URL -->
                        <input type="hidden" name="redirect_url" id="redirect_url" value="<?php echo $redirect_url; ?>">
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
                                          placeholder="Nhập tên website" required>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="admin_url" class="fw-bold">URL trang quản trị</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ph ph-link"></i></span>
                                        <input type="url" class="form-control" id="admin_url" name="admin_url" 
                                              placeholder="https://example.com/wp-admin/">
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="admin_username" class="fw-bold">Tên đăng nhập</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ph ph-user"></i></span>
                                        <input type="text" class="form-control" id="admin_username" name="admin_username" 
                                              placeholder="Nhập tên đăng nhập">
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="admin_password" class="fw-bold">Mật khẩu</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="admin_password" name="admin_password" 
                                              placeholder="Nhập mật khẩu quản trị">
                                        <button class="btn btn-secondary toggle-password" type="button">
                                            <i class="ph ph-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="notes" class="fw-bold">Ghi chú</label>
                                    <textarea class="form-control height-auto" id="notes" rows="3" placeholder="Nhập ghi chú (không bắt buộc)" name="notes"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        wp_nonce_field('add_website_simple', 'add_website_simple_field');
                        ?>
                        
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit"
                                class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-globe-hemisphere-west btn-icon-prepend fa-150p"></i>
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

<script>
jQuery(document).ready(function($) {
});
</script>

<?php
get_footer();
?>


