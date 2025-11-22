<?php
/* 
    Template Name: Sửa người dùng
*/

get_header();

$user_id = $_GET['id'];
// if have user id, then get user data to show in form
// if not, go to list user page
if (isset($user_id)) {
    global $wpdb;
    $users_table = $wpdb->prefix . 'im_users';
    $user = $wpdb->get_row("SELECT * FROM $users_table WHERE id = $user_id");
    if (!$user) {
        wp_redirect(home_url('/danh-sach-doi-tac/'));
        exit;
    }
} else {
    wp_redirect(home_url('/danh-sach-doi-tac/'));
    exit;
}

/* 
* Process data when form is submitted
*/
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_partner_field']) && wp_verify_nonce($_POST['post_partner_field'], 'post_partner')) {
        $name = sanitize_text_field($_POST['user_name']);
        $user_code = sanitize_text_field($_POST['user_code']);
        $user_type = sanitize_text_field($_POST['user_type']);
        $email = sanitize_email($_POST['email']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $tax_code = sanitize_text_field($_POST['tax_code']);
        $address = sanitize_text_field($_POST['address']);
        $notes = sanitize_text_field($_POST['notes']);
        $status = sanitize_text_field($_POST['status']);
        $partner_id = isset($_POST['partner_id']) ? sanitize_text_field($_POST['partner_id']) : null;

        // Update user data
        $wpdb->update(
            $users_table,
            array(
                'user_code' => $user_code,
                'user_type' => $user_type,
                'name' => $name,
                'email' => $email,
                'phone_number' => $phone_number,
                'tax_code' => $tax_code,
                'address' => $address,
                'notes' => $notes,
                'partner_id' => $partner_id,
                'status' => $status
            ),
            array('id' => $user_id)
        );

        $notification = 'Cập nhật thông tin người dùng thành công';
    }
}
?>
<div class="content-wrapper mt-5">
    <div class="card card-rounded">
        <div class="card-body">
            <div class="row">
                <div class="col-lg-12" id="relative">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-column">
                        <!-- add back button in the left side -->
                        <a href="<?php echo home_url('/partner?id=') . $user->id; ?>" class="abs-top-left nav-link">
                            <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                        </a>
                        <div>
                            <h3 class="display-3">Sửa thông tin người dùng</h3>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                            <?php 
                            if (isset($notification)) {
                                echo '<div class="alert alert-success" role="alert">' . $notification . '</div>';
                                // add more button to back to home page
                                echo '<a href="' . home_url('/partner?id=' . $user->id) . '" class="btn btn-dark btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                        <i class="ph ph-eye btn-icon-prepend fa-150p"></i>
                                        <span class="fw-bold">Xem lại thông tin</span>
                                    </a>';
                            } else {
                            ?>
                            <form class="forms-sample col-md-8 col-lg-6 d-flex flex-column" action="" method="post" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="user_name" class="fw-bold">Tên người dùng <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="user_name" name="user_name" value="<?php echo esc_attr($user->name); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="user_code" class="fw-bold">Mã người dùng <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="user_code" name="user_code" value="<?php echo esc_attr($user->user_code); ?>" required>
                                    <small class="form-text text-muted">Mã người dùng là duy nhất trong hệ thống</small>
                                </div>
                                <div class="form-group">
                                    <label for="user_type" class="fw-bold">Loại người dùng <span class="text-danger">*</span></label>
                                    <select class="form-control" id="user_type" name="user_type" required>
                                        <option value="">-- Chọn loại người dùng --</option>
                                        <option value="INDIVIDUAL" <?php selected($user->user_type, 'INDIVIDUAL'); ?>>Khách hàng cá nhân</option>
                                        <option value="BUSINESS" <?php selected($user->user_type, 'BUSINESS'); ?>>Khách hàng doanh nghiệp</option>
                                        <option value="PARTNER" <?php selected($user->user_type, 'PARTNER'); ?>>Đối tác</option>
                                        <option value="ADMIN" <?php selected($user->user_type, 'ADMIN'); ?>>Quản trị viên</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="email" class="fw-bold">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo esc_attr($user->email); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="phone_number" class="fw-bold">Điện thoại</label>
                                    <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo esc_attr($user->phone_number); ?>">
                                </div>
                                <div class="form-group tax-code-field" <?php echo $user->user_type !== 'BUSINESS' ? 'style="display: none;"' : ''; ?>>
                                    <label for="tax_code" class="fw-bold">Mã số thuế</label>
                                    <input type="text" class="form-control" id="tax_code" name="tax_code" value="<?php echo esc_attr($user->tax_code); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="address" class="fw-bold">Địa chỉ</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?php echo esc_attr($user->address); ?>">
                                </div>
                                
                                <?php if ($user->user_type != 'PARTNER'): ?>
                                <div class="form-group">
                                    <label class="fw-bold">Đối tác quản lý</label>
                                    <select class="js-example-basic-single w-100" name="partner_id">
                                        <option value="">-- Không có đối tác quản lý --</option>
                                        <?php
                                        $partners = $wpdb->get_results("SELECT * FROM $users_table ORDER BY name");
                                        foreach ($partners as $partner) {
                                            $selected = ($user->partner_id == $partner->id) ? 'selected' : '';
                                            echo '<option value="' . $partner->id . '" ' . $selected . '>' . $partner->user_code . ' - ' . $partner->name . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <div class="form-group">
                                    <label for="status" class="fw-bold">Trạng thái</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="ACTIVE" <?php selected($user->status, 'ACTIVE'); ?>>Hoạt động</option>
                                        <option value="INACTIVE" <?php selected($user->status, 'INACTIVE'); ?>>Không hoạt động</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="notes" class="fw-bold">Ghi chú</label>
                                    <textarea class="form-control height-auto" id="notes" rows="4" name="notes"><?php echo esc_textarea($user->notes); ?></textarea>
                                </div>
                                
                                <?php
                                wp_nonce_field('post_partner', 'post_partner_field');
                                ?>

                                <div class="form-group d-flex justify-content-center">
                                    <button type="submit" class="btn btn-dark btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                        <i class="ph ph-user-focus btn-icon-prepend fa-150p"></i>
                                        <span class="fw-bold">Cập nhật thông tin</span>
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Add JavaScript to show/hide tax_code field based on user_type -->
                            <script>
                                jQuery(document).ready(function($) {
                                    $('#user_type').change(function() {
                                        if ($(this).val() === 'BUSINESS') {
                                            $('.tax-code-field').show();
                                        } else {
                                            $('.tax-code-field').hide();
                                        }
                                    });
                                });
                            </script>
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