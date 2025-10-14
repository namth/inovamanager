<?php
/* 
    Template Name: Edit Contact
*/

global $wpdb;
$contacts_table = $wpdb->prefix . 'im_contacts';
$contact_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no contact_id, redirect back
if ($contact_id === 0) {
    wp_redirect(home_url('/danh-sach-nguoi-dung/'));
    exit;
}

// Get contact information
$contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM $contacts_table WHERE id = %d", $contact_id));

// If contact doesn't exist, redirect back
if (!$contact) {
    wp_redirect(home_url('/danh-sach-nguoi-dung/'));
    exit;
}

// Get user information
$users_table = $wpdb->prefix . 'im_users';
$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $contact->user_id));

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit_contact_field']) && wp_verify_nonce($_POST['edit_contact_field'], 'edit_contact')) {
        $full_name = sanitize_text_field($_POST['full_name']);
        $email = sanitize_email($_POST['email']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $position = sanitize_text_field($_POST['position']);
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        // If this contact is marked as primary, update all other contacts to not be primary
        if ($is_primary) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $contacts_table SET is_primary = 0 WHERE user_id = %d AND id != %d",
                $contact->user_id,
                $contact_id
            ));
        }
        
        // Update the contact
        $wpdb->update(
            $contacts_table,
            array(
                'full_name' => $full_name,
                'email' => $email,
                'phone_number' => $phone_number,
                'position' => $position,
                'is_primary' => $is_primary
            ),
            array('id' => $contact_id)
        );
        
        $notification = '<div class="alert alert-success" role="alert">Cập nhật liên hệ thành công</div>';
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
                        <a href="<?php echo home_url('/chi-tiet-nguoi-dung/?id=') . $contact->user_id; ?>" class="abs-top-left nav-link">
                            <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                        </a>
                        <div>
                            <h3 class="display-3">Chỉnh sửa liên hệ</h3>
                            <p class="text-center">của <?php echo $user->company_name ?: $user->name; ?></p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                            <?php
                            if (isset($notification)) {
                                echo $notification;
                                // add more button to back to user detail page
                                echo '<a href="' . home_url('/chi-tiet-nguoi-dung/?id=' . $contact->user_id) . '" class="btn btn-dark btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                        <i class="ph ph-user btn-icon-prepend fa-150p"></i>
                                        <span class="fw-bold">Quay lại thông tin người dùng</span>
                                    </a>';
                            } else {
                                ?>
                                <form class="forms-sample col-md-6 col-lg-4 d-flex flex-column w-100 max-w395" action="" method="post" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="full_name" class="fw-bold">Họ và tên <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo esc_attr($contact->full_name); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="position" class="fw-bold">Chức vụ</label>
                                        <input type="text" class="form-control" id="position" name="position" value="<?php echo esc_attr($contact->position); ?>" placeholder="Giám đốc, Kế toán, v.v...">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email" class="fw-bold">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo esc_attr($contact->email); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="phone_number" class="fw-bold">Số điện thoại</label>
                                        <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo esc_attr($contact->phone_number); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="form-check form-check-primary">
                                            <label class="form-check-label">
                                                <input type="checkbox" class="form-check-input" name="is_primary" <?php checked($contact->is_primary); ?>>
                                                Đây là người liên hệ chính
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">Nếu đánh dấu, người này sẽ trở thành liên hệ chính thay thế liên hệ chính hiện tại (nếu có).</small>
                                    </div>
                                    
                                    <?php
                                    wp_nonce_field('edit_contact', 'edit_contact_field');
                                    ?>
                                    <div class="form-group d-flex justify-content-center">
                                        <button type="submit"
                                            class="btn btn-dark btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                            <i class="ph ph-user-focus btn-icon-prepend fa-150p"></i>
                                            <span class="fw-bold">Cập nhật liên hệ</span>
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