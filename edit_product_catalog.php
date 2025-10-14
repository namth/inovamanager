<?php
/* 
    Template Name: Edit Product Catalog
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_product_catalog';
$current_user_id = get_current_user_id();

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no product ID is provided or product doesn't exist, redirect to product list
if (!$product_id) {
    wp_redirect(home_url('/danh-sach-san-pham/'));
    exit;
}

// Get product data
$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $product_id));

if (!$product) {
    wp_redirect(home_url('/danh-sach-san-pham/'));
    exit;
}

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit_product_field']) && wp_verify_nonce($_POST['edit_product_field'], 'edit_product')) {
        $service_type = sanitize_text_field($_POST['service_type']);
        $name = sanitize_text_field($_POST['product_name']);
        $description = sanitize_textarea_field($_POST['description']);
        $base_price = floatval($_POST['base_price']);
        $billing_cycle = sanitize_text_field($_POST['billing_cycle']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate required fields
        if (!empty($service_type) && !empty($name) && !empty($base_price) && !empty($billing_cycle)) {
            $wpdb->update(
                $table_name,
                array(
                    'service_type' => $service_type,
                    'name' => $name,
                    'description' => $description,
                    'base_price' => $base_price,
                    'billing_cycle' => $billing_cycle,
                    'is_active' => $is_active,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $product_id)
            );
            
            // Redirect back to product catalog list after successful update
            wp_redirect(home_url('/danh-sach-san-pham/'));
            exit;
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
                    <h3 class="display-3">Chỉnh sửa sản phẩm dịch vụ</h3>
                </div>
            </div>
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php
                    if (isset($notification)) {
                        echo $notification;
                    }
                    ?>
                    <h3 class="card-title p-2 mb-3">Thông tin sản phẩm dịch vụ</h3>
                    <form class="forms-sample col-sm-12 col-md-6 col-lg-4 d-flex flex-column w-100 max-w500" action="" method="post">
                        <div class="form-group">
                            <label for="service_type" class="fw-bold">Loại dịch vụ <span class="text-danger">*</span></label>
                            <select class="form-control" id="service_type" name="service_type" required>
                                <option value="">-- Chọn loại dịch vụ --</option>
                                <option value="Hosting" <?php selected($product->service_type, 'Hosting'); ?>>Hosting</option>
                                <option value="Domain" <?php selected($product->service_type, 'Domain'); ?>>Tên miền</option>
                                <option value="Maintenance" <?php selected($product->service_type, 'Maintenance'); ?>>Bảo trì</option>
                                <option value="Website" <?php selected($product->service_type, 'Website'); ?>>Website</option>
                                <option value="Other" <?php selected($product->service_type, 'Other'); ?>>Khác</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="product_name" class="fw-bold">Tên sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="product_name" name="product_name" value="<?php echo esc_attr($product->name); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="fw-bold">Mô tả</label>
                            <textarea class="form-control height-auto" id="description" name="description" rows="3"><?php echo esc_textarea($product->description); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="base_price" class="fw-bold">Giá cơ bản (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="base_price" name="base_price" value="<?php echo esc_attr($product->base_price); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="billing_cycle" class="fw-bold">Chu kỳ thanh toán <span class="text-danger">*</span></label>
                            <select class="form-control" id="billing_cycle" name="billing_cycle" required>
                                <option value="">-- Chọn chu kỳ --</option>
                                <option value="Monthly" <?php selected($product->billing_cycle, 'Monthly'); ?>>Tháng</option>
                                <option value="Quarterly" <?php selected($product->billing_cycle, 'Quarterly'); ?>>Quý</option>
                                <option value="Biannual" <?php selected($product->billing_cycle, 'Biannual'); ?>>6 tháng</option>
                                <option value="Annual" <?php selected($product->billing_cycle, 'Annual'); ?>>Năm</option>
                                <option value="Biennial" <?php selected($product->billing_cycle, 'Biennial'); ?>>2 năm</option>
                                <option value="Triennial" <?php selected($product->billing_cycle, 'Triennial'); ?>>3 năm</option>
                                <option value="One-time" <?php selected($product->billing_cycle, 'One-time'); ?>>Một lần</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check form-check-flat form-check-primary">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" name="is_active" <?php checked($product->is_active, 1); ?>>
                                    Đang hoạt động
                                </label>
                            </div>
                        </div>
                        
                        <?php
                        wp_nonce_field('edit_product', 'edit_product_field');
                        ?>
                        <div class="form-group d-flex justify-content-center">
                            <button type="submit"
                                class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Cập nhật sản phẩm</span>
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