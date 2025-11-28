<?php
/* 
    Template Name: Thêm người dùng mới
*/

global $wpdb;
$users_table = $wpdb->prefix . 'im_users';
$current_user_id = get_current_user_id();
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
        $tax_code = isset($_POST['tax_code']) ? sanitize_text_field($_POST['tax_code']) : '';
        $address = sanitize_text_field($_POST['address']);
        $notes = sanitize_text_field($_POST['notes']);
        $requires_vat_invoice = isset($_POST['requires_vat_invoice']) ? 1 : 0;
         
         // Get current date in server timezone
         $date = date('Y-m-d H:i:s');

         $wpdb->insert(
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
                 'status' => 'ACTIVE',
                 'requires_vat_invoice' => $requires_vat_invoice
             )
         );

        $notification = 'Thêm người dùng mới thành công.';
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
                        <a href="<?php echo home_url('/danh-sach-doi-tac/'); ?>" class="abs-top-left nav-link">
                            <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                        </a>
                        <div>
                            <h3 class="display-3">Thêm người dùng mới</h3>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                            <?php 
                            if (isset($notification)) {
                                echo '<div class="alert alert-success" role="alert">' . $notification . '</div>';
                                // add more button to back to home page
                                echo '<a href="' . home_url('/danh-sach-doi-tac/') . '" class="btn btn-dark btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                        <i class="ph ph-users-three btn-icon-prepend fa-150p"></i>
                                        <span class="fw-bold">Xem danh sách người dùng</span>
                                    </a>';
                            } else {
                            ?>
                            <form class="forms-sample col-md-8 col-lg-6 d-flex flex-column" action="" method="post" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="user_name" class="fw-bold">Tên người dùng <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="user_name" name="user_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="user_code" class="fw-bold">Mã người dùng <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="user_code" name="user_code" required>
                                    <small class="form-text text-muted">Mã người dùng là duy nhất trong hệ thống</small>
                                </div>
                                <div class="form-group">
                                    <label for="user_type" class="fw-bold">Loại người dùng <span class="text-danger">*</span></label>
                                    <select class="form-control" id="user_type" name="user_type" required>
                                        <option value="">-- Chọn loại người dùng --</option>
                                        <option value="INDIVIDUAL">Khách hàng cá nhân</option>
                                        <option value="BUSINESS">Khách hàng doanh nghiệp</option>
                                        <option value="PARTNER">Đối tác</option>
                                        <option value="SUPPLIER">Nhà cung cấp dịch vụ</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="email" class="fw-bold">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                <div class="form-group">
                                    <label for="phone_number" class="fw-bold">Điện thoại</label>
                                    <input type="text" class="form-control" id="phone_number" name="phone_number">
                                </div>
                                <div class="form-group tax-code-field" style="display: none;">
                                    <label for="tax_code" class="fw-bold">Mã số thuế</label>
                                    <input type="text" class="form-control" id="tax_code" name="tax_code">
                                </div>
                                <div class="form-group">
                                    <label for="address" class="fw-bold">Địa chỉ</label>
                                    <input type="text" class="form-control" id="address" name="address">
                                </div>
                                <div class="form-group">
                                    <label for="notes" class="fw-bold">Ghi chú</label>
                                    <textarea class="form-control height-auto" id="notes" rows="4" placeholder="Ghi chú" name="notes"></textarea>
                                </div>
                                <div class="form-group">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="requires_vat_invoice" 
                                               name="requires_vat_invoice" 
                                               value="1">
                                        <label class="form-check-label fw-bold" for="requires_vat_invoice">
                                            Nhận hóa đơn đỏ
                                        </label>
                                    </div>
                                </div>
                                <?php
                                wp_nonce_field('post_partner', 'post_partner_field');
                                ?>
                                <div class="form-group d-flex justify-content-center">
                                    <button type="submit" class="btn btn-dark btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                        <i class="ph ph-users-three btn-icon-prepend fa-150p"></i>
                                        <span class="fw-bold">Thêm người dùng mới</span>
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