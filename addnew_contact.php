<?php
/* 
    Template Name: Add New Contact
*/

global $wpdb;
$contacts_table = $wpdb->prefix . 'im_contacts';
$current_user_id = get_current_user_id();

// Get user_id from GET parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// If no user_id, redirect back to the user list
if ($user_id === 0) {
    wp_redirect(home_url('/danh-sach-nguoi-dung/'));
    exit;
}

// Get user information to display
$users_table = $wpdb->prefix . 'im_users';
$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $user_id));

// If user doesn't exist, redirect back
if (!$user) {
    wp_redirect(home_url('/danh-sach-nguoi-dung/'));
    exit;
}

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_contact_field']) && wp_verify_nonce($_POST['post_contact_field'], 'post_contact')) {
        $full_name = sanitize_text_field($_POST['full_name']);
        $email = sanitize_email($_POST['email']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $position = sanitize_text_field($_POST['position']);
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        // If this contact is marked as primary, update all other contacts to not be primary
        if ($is_primary) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $contacts_table SET is_primary = 0 WHERE user_id = %d",
                $user_id
            ));
        }
        
        // Insert the new contact
        $wpdb->insert(
            $contacts_table,
            array(
                'user_id' => $user_id,
                'full_name' => $full_name,
                'email' => $email,
                'phone_number' => $phone_number,
                'position' => $position,
                'is_primary' => $is_primary
            )
        );
        
        $notification = '<div class="alert alert-success" role="alert">Thêm liên hệ mới thành công</div>';
    }
}

get_header();
?>

<div class="content-wrapper mt-5">
    <div class="card card-rounded">
        <div class="card-body">
            <div class="row">
                <div class="col-lg-12">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-column">
                         <!-- add back button in the left side -->
                         <a href="javascript:history.back()" class="abs-top-left nav-link">
                             <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                         </a>
                        <div>
                            <h3 class="display-4">Thêm liên hệ mới</h3>
                            <h4 class="text-center">cho <?php echo $user->name; ?></h4>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                            <?php
                            if (isset($notification)) {
                                echo $notification;
                                // add more button to back to user detail page
                                echo '<a href="javascript:history.back()" class="btn btn-dark btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                        <i class="ph ph-user btn-icon-prepend fa-150p"></i>
                                        <span class="fw-bold">Quay lại thông tin người dùng</span>
                                    </a>';
                            } else {
                                ?>
                                <form class="forms-sample col-md-6 col-lg-4 d-flex flex-column w-100 max-w395" action="" method="post" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="full_name" class="fw-bold">Họ và tên <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="position" class="fw-bold">Chức vụ</label>
                                        <input type="text" class="form-control" id="position" name="position" placeholder="Giám đốc, Kế toán, v.v...">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email" class="fw-bold">Email</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="phone_number" class="fw-bold">Số điện thoại</label>
                                        <input type="text" class="form-control" id="phone_number" name="phone_number">
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="form-check form-check-primary">
                                            <label class="form-check-label">
                                                <input type="checkbox" class="form-check-input" name="is_primary">
                                                Đây là người liên hệ chính
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">Nếu đánh dấu, người này sẽ trở thành liên hệ chính thay thế liên hệ chính hiện tại (nếu có).</small>
                                    </div>
                                    
                                    <?php
                                    wp_nonce_field('post_contact', 'post_contact_field');
                                    ?>
                                    <div class="form-group d-flex justify-content-center">
                                        <button type="submit"
                                            class="btn btn-dark btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                            <i class="ph ph-user-plus btn-icon-prepend fa-150p"></i>
                                            <span class="fw-bold">Thêm liên hệ</span>
                                        </button>
                                    </div>
                                </form>
                            <?php
                            }
                            ?>
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