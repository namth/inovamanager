<?php
/* 
    Template Name: Add New Product Catalog
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_product_catalog';
$current_user_id = get_current_user_id();

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_product_field']) && wp_verify_nonce($_POST['post_product_field'], 'post_product')) {
        $service_type = sanitize_text_field($_POST['service_type']);
        $name = sanitize_text_field($_POST['product_name']);
        $code = sanitize_text_field($_POST['code']);
        $description = sanitize_textarea_field($_POST['description']);
        $register_price = intval($_POST['register_price']);
        $renew_price = intval($_POST['renew_price']);
        $billing_cycle = sanitize_text_field($_POST['billing_cycle']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validate required fields
        if (!empty($service_type) && !empty($name) && !empty($code) && !empty($register_price) && !empty($renew_price) && !empty($billing_cycle)) {
            $wpdb->insert(
                $table_name,
                array(
                    'service_type' => $service_type,
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'register_price' => $register_price,
                    'renew_price' => $renew_price,
                    'billing_cycle' => $billing_cycle,
                    'is_active' => $is_active
                )
            );

            $product_id = $wpdb->insert_id;
            
            if ($product_id) {
                // Redirect back to product catalog list
                wp_redirect(home_url('/danh-sach-san-pham/'));
                exit;
            } else {
                $notification = '<div class="alert alert-danger" role="alert">Không thể thêm mới sản phẩm. Vui lòng thử lại.</div>';
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
                <a href="<?php echo home_url('/danh-sach-san-pham/'); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Thêm mới sản phẩm dịch vụ</h3>
                </div>
            </div>
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php
                    if (isset($notification)) {
                        echo $notification;
                    }
                    ?>
                    <!-- <h3 class="card-title p-2 mb-3">Thông tin sản phẩm dịch vụ</h3> -->
                    <form class="forms-sample col-sm-12 col-md-6 col-lg-4 d-flex flex-column w-100 max-w500" action="" method="post">
                        <div class="form-group">
                            <label for="service_type" class="fw-bold">Loại dịch vụ <span class="text-danger">*</span></label>
                            <select class="form-control" id="service_type" name="service_type" required>
                                <option value="">-- Chọn loại dịch vụ --</option>
                                <option value="Hosting">Hosting</option>
                                <option value="Domain">Tên miền</option>
                                <option value="Maintenance">Bảo trì</option>
                                <option value="Website">Website</option>
                                <option value="Other">Khác</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="product_name" class="fw-bold">Tên sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="product_name" name="product_name" required>
                        </div>

                        <div class="form-group">
                            <label for="code" class="fw-bold">Mã sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="code" name="code" maxlength="5" placeholder="VD: HOST, DMN" required>
                            <small class="form-text text-muted">Mã sản phẩm (tối đa 5 ký tự, viết hoa)</small>
                        </div>

                        <div class="form-group">
                            <label for="description" class="fw-bold">Mô tả</label>
                            <textarea class="form-control height-auto" id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="register_price" class="fw-bold">Giá đăng ký lần đầu (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" step="1000" class="form-control" id="register_price" name="register_price" required>
                            <small class="form-text text-muted">Giá cho lần đăng ký đầu tiên</small>
                        </div>

                        <div class="form-group">
                            <label for="renew_price" class="fw-bold">Giá gia hạn (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" step="1000" class="form-control" id="renew_price" name="renew_price" required>
                            <small class="form-text text-muted">Giá cho các lần gia hạn tiếp theo</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="billing_cycle" class="fw-bold">Chu kỳ thanh toán <span class="text-danger">*</span></label>
                            <select class="form-control" id="billing_cycle" name="billing_cycle" required>
                                <option value="">-- Chọn chu kỳ --</option>
                                <option value="Monthly">Tháng</option>
                                <option value="Quarterly">Quý</option>
                                <option value="Biannual">6 tháng</option>
                                <option value="Annual">Năm</option>
                                <option value="Biennial">2 năm</option>
                                <option value="Triennial">3 năm</option>
                                <option value="One-time">Một lần</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check form-check-flat form-check-primary">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" name="is_active" checked>
                                    Đang hoạt động
                                </label>
                            </div>
                        </div>
                        
                        <?php
                        wp_nonce_field('post_product', 'post_product_field');
                        ?>
                        <div class="form-group d-flex justify-content-center">
                            <button type="submit"
                                class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Lưu sản phẩm</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();