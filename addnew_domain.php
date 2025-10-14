<?php
/* 
    Template Name: Add New Domain
*/

global $wpdb;
$domains_table = $wpdb->prefix . 'im_domains';
$current_user_id = get_current_user_id();

// Get all domain products for auto-detection
$catalog_table = $wpdb->prefix . 'im_product_catalog';
$domain_products = $wpdb->get_results("SELECT * FROM $catalog_table WHERE service_type = 'Domain' AND is_active = 1 ORDER BY name");

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_domain_field']) && wp_verify_nonce($_POST['post_domain_field'], 'post_domain')) {
        $domain_name = sanitize_text_field($_POST['domain_name']);
        $owner_user_id = sanitize_text_field($_POST['owner_user_id']);
        $provider_id = sanitize_text_field($_POST['provider_id']);
        $product_catalog_id = sanitize_text_field($_POST['product_catalog_id']);
        $price = sanitize_text_field($_POST['price']);
        $status = sanitize_text_field($_POST['status']);
        $managed_by_inova = isset($_POST['managed_by_inova']) ? 1 : 0;
        $dns_management = sanitize_text_field($_POST['dns_management']);
        $management_url = sanitize_text_field($_POST['management_url']);
        $management_username = sanitize_text_field($_POST['management_username']);
        $management_password = sanitize_text_field($_POST['management_password']);
        $notes = sanitize_text_field($_POST['notes']);
        
        // Registration date handling
        if (isset($_POST['registration_date']) && !empty($_POST['registration_date'])) {
            // Convert from dd/mm/yyyy to yyyy-mm-dd
            $registration_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['registration_date'])));
            $registration_period_years = intval($_POST['registration_period_years']);
            
            // Handle expiry date - use manual date if provided, otherwise calculate automatically
            if (isset($_POST['expiry_date']) && !empty($_POST['expiry_date'])) {
                // Convert from dd/mm/yyyy to yyyy-mm-dd
                $expiry_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['expiry_date'])));
            } else {
                // Calculate expiry date by adding registration period years
                $expiry_date = date('Y-m-d', strtotime($registration_date . " + {$registration_period_years} years"));
            }

            $data = array(
                'domain_name' => $domain_name,
                'owner_user_id' => $owner_user_id,
                'provider_id' => $provider_id,
                'product_catalog_id' => $product_catalog_id,
                'registration_date' => $registration_date,
                'registration_period_years' => $registration_period_years,
                'expiry_date' => $expiry_date,
                'price' => $price,
                'status' => $status,
                'managed_by_inova' => $managed_by_inova,
                'dns_management' => $dns_management,
                'management_url' => $management_url,
                'management_username' => $management_username,
                'management_password' => $management_password,
                'notes' => $notes
            );
            
            $insert = $wpdb->insert(
                $domains_table,
                $data
            );
            
            if ($insert) {
                $new_domain_id = $wpdb->insert_id;
                $notification = '<div class="alert alert-success" role="alert">Thêm domain mới thành công!</div>';
            } else {
                $notification = '<div class="alert alert-danger" role="alert">Đã xảy ra lỗi khi thêm domain. Vui lòng thử lại sau.</div>';
            }
        } else {
            $notification = '<div class="alert alert-warning" role="alert">Bạn chưa chọn ngày đăng ký</div>';
        }
    }
}

get_header();
?>

<div class="content-wrapper mt-5">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-column">
                <!-- add back button in the left side -->
                <a href="<?php echo home_url('/danh-sach-ten-mien/'); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Thêm mới tên miền</h3>
                </div>
            </div>
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php
                    // print_r($data);
                    // print_r($insert);
                    if (isset($notification)) {
                        echo $notification;
                        // add more button to back to home page
                        echo '<div class="d-flex mt-3">';
                        echo '<a href="' . home_url('/danh-sach-ten-mien/') . '" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-list btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Xem danh sách tên miền</span>
                              </a>';
                        if (isset($new_domain_id)) {
                            echo '<a href="' . home_url('/detail-domain/?domain_id=' . $new_domain_id) . '" class="btn btn-info btn-icon-text ms-2 d-flex align-items-center border-radius-9">
                                    <i class="ph ph-eye btn-icon-prepend fa-150p"></i>
                                    <span class="fw-bold">Xem chi tiết tên miền</span>
                                  </a>';
                        }
                        echo '</div>';
                    } else {
                    ?>
                    <form class="forms-sample col-md-8 col-lg-10 d-flex flex-column" action="" method="post">
                        <div class="row">
                            <!-- Domain Information Section -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header btn-primary">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-globe-hemisphere-west me-2"></i>
                                            Thông tin tên miền
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="domain_name" class="fw-bold">Tên miền <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="domain_name" name="domain_name" placeholder="example.com" required>
                                            <small id="domain_type_info" class="form-text text-muted"></small>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Chủ sở hữu <span class="text-danger">*</span></label>
                                            <select class="js-example-basic-single w-100" name="owner_user_id" required>
                                                <option value="">-- Chọn chủ sở hữu --</option>
                                                <?php
                                                $users_table = $wpdb->prefix . 'im_users';
                                                $users = $wpdb->get_results("SELECT * FROM $users_table ORDER BY name");
                                                foreach ($users as $user) {
                                                    $user_label = $user->name;
                                                    if (!empty($user->company_name)) {
                                                        $user_label .= ' (' . $user->company_name . ')';
                                                    }
                                                    echo '<option value="' . $user->id . '">' . $user->user_code . ' - ' . $user_label . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold" for="provider_id">Nhà cung cấp</label>
                                            <select class="js-example-basic-single w-100" name="provider_id" id="provider_id">
                                                <option value="">-- Chọn nhà cung cấp --</option>
                                                <?php
                                                $users_table = $wpdb->prefix . 'im_users';
                                                $providers = $wpdb->get_results("SELECT * FROM $users_table WHERE user_type = 'SUPPLIER' ORDER BY name");
                                                foreach ($providers as $provider) {
                                                    echo '<option value="' . $provider->id . '">' . $provider->name . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <small class="form-text text-muted">Chọn đơn vị cung cấp tên miền</small>
                                        </div>
                                        
                                        <!-- Hidden input for product_catalog_id that will be set automatically -->
                                        <input type="hidden" name="product_catalog_id" id="product_catalog_id" value="">

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group mb-3">
                                                    <label for="registration_date" class="fw-bold">Ngày đăng ký <span class="text-danger">*</span></label>
                                                    <div class="input-group date datepicker">
                                                        <input type="text" class="form-control" id="registration_date" name="registration_date" placeholder="<?php echo date('d/m/Y'); ?>" required>
                                                        <span class="input-group-text bg-secondary text-white">
                                                            <i class="ph ph-calendar"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group mb-3">
                                                    <label for="registration_period_years" class="fw-bold">Thời hạn (năm) <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="registration_period_years" name="registration_period_years" required>
                                                        <?php for ($i = 1; $i <= 10; $i++) : ?>
                                                            <option value="<?php echo $i; ?>" <?php echo $i == 1 ? 'selected' : ''; ?>><?php echo $i; ?> năm</option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group mb-3">
                                                    <label for="expiry_date" class="fw-bold">Ngày hết hạn <span class="text-danger">*</span></label>
                                                    <div class="input-group date datepicker">
                                                        <input type="text" class="form-control" id="expiry_date" name="expiry_date" placeholder="Tự động tính toán" required>
                                                        <span class="input-group-text bg-secondary text-white">
                                                            <i class="ph ph-calendar"></i>
                                                        </span>
                                                    </div>
                                                    <small class="form-text text-muted">Tự động tính từ ngày đăng ký + thời hạn</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="notes" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control height-auto" id="notes" rows="3" placeholder="Ghi chú" name="notes"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Domain Management Section -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="d-flex card-header btn-secondary">
                                        <h5 class="mt-1 mb-1">
                                            <i class="ph ph-gear me-2"></i>
                                            Thông tin quản lý
                                        </h5>
                                        <span class="badge border-radius-9 bg-inverse-info text-info ms-2">Chỉ admin có thể thấy</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="status" class="fw-bold">Trạng thái <span class="text-danger">*</span></label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="ACTIVE">Đang hoạt động</option>
                                                <option value="EXPIRED">Hết hạn</option>
                                                <option value="PENDING_RENEWAL">Chờ gia hạn</option>
                                                <option value="CANCELLED">Đã hủy</option>
                                                <option value="TRANSFERRING">Đang chuyển</option>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <div class="form-switch d-flex gap-2">
                                                <input class="form-check-input" type="checkbox" id="managed_by_inova" name="managed_by_inova" checked>
                                                <label class="form-check-label fw-bold" for="managed_by_inova">
                                                    INOVA quản lý
                                                </label>
                                            </div>
                                            <i class="form-text"><small>Đánh dấu nếu INOVA đang quản lý tên miền này</small></i>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="dns_management" class="fw-bold">Quản lý DNS</label>
                                            <select class="form-control" id="dns_management" name="dns_management">
                                                <option value="PROVIDER">Nhà cung cấp</option>
                                                <option value="CLOUDFLARE">Cloudflare</option>
                                                <option value="OTHER">Khác</option>
                                            </select>
                                        </div>

                                        <hr class="my-4">
                                        <h6 class="mb-3 text-primary">Thông tin đăng nhập</h6>
                                        
                                        <div class="form-group mb-3">
                                            <label for="management_url" class="fw-bold">URL quản lý</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ph ph-link"></i></span>
                                                <input type="text" class="form-control" id="management_url" name="management_url" placeholder="https://...">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="management_username" class="fw-bold">Username</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ph ph-user"></i></span>
                                                <input type="text" class="form-control" id="management_username" name="management_username">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="management_password" class="fw-bold">Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="management_password" name="management_password">
                                                <button class="btn btn-secondary toggle-password" type="button">
                                                    <i class="ph ph-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        wp_nonce_field('post_domain', 'post_domain_field');
                        ?>
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit"
                                class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Lưu tên miền</span>
                            </button>
                            <a href="<?php echo home_url('/danh-sach-ten-mien/'); ?>" class="btn btn-light btn-icon-text ms-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-x btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Hủy</span>
                            </a>
                        </div>
                    </form>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Make domain products available to the centralized JavaScript
window.domainProducts = <?php echo json_encode($domain_products); ?>;

// Auto-calculate expiry date based on registration date and registration period
function calculateExpiryDate() {
    const registrationDate = document.getElementById('registration_date').value;
    const registrationPeriodYears = parseInt(document.getElementById('registration_period_years').value);
    
    if (registrationDate && registrationPeriodYears) {
        // Parse date in dd/mm/yyyy format
        const dateParts = registrationDate.split('/');
        if (dateParts.length === 3) {
            const regDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]); // yyyy, mm-1, dd
            
            // Add registration period years
            const expiryDate = new Date(regDate);
            expiryDate.setFullYear(expiryDate.getFullYear() + registrationPeriodYears);
            
            // Format back to dd/mm/yyyy
            const day = String(expiryDate.getDate()).padStart(2, '0');
            const month = String(expiryDate.getMonth() + 1).padStart(2, '0');
            const year = expiryDate.getFullYear();
            
            document.getElementById('expiry_date').value = `${day}/${month}/${year}`;
        }
    }
}

// Attach event listeners
document.addEventListener('DOMContentLoaded', function() {
    const registrationDateField = document.getElementById('registration_date');
    const registrationPeriodField = document.getElementById('registration_period_years');
    
    if (registrationDateField) {
        registrationDateField.addEventListener('change', calculateExpiryDate);
        registrationDateField.addEventListener('blur', calculateExpiryDate);
    }
    
    if (registrationPeriodField) {
        registrationPeriodField.addEventListener('change', calculateExpiryDate);
    }
    
    // Auto-calculate when page loads if registration date is filled
    calculateExpiryDate();
});
</script>

<?php
get_footer();
?>