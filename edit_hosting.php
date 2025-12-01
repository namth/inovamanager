<?php
/* 
    Template Name: Edit Hosting
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_hostings';
$current_user_id = get_current_user_id();

// Get hosting ID from URL
$hosting_id = isset($_GET['hosting_id']) ? intval($_GET['hosting_id']) : 0;

// Redirect if no hosting ID provided
if (!$hosting_id) {
    wp_redirect(home_url('/danh-sach-hosting/'));
    exit;
}

// Get hosting data
$hosting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $hosting_id));

// Redirect if hosting not found
if (!$hosting) {
    wp_redirect(home_url('/danh-sach-hosting/'));
    exit;
}

// Get user data
$users_table = $wpdb->prefix . 'im_users';
$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $hosting->owner_user_id));

// Get website using this hosting
$websites_table = $wpdb->prefix . 'im_websites';
$website = $wpdb->get_row($wpdb->prepare("SELECT * FROM $websites_table WHERE hosting_id = %d", $hosting_id));

// Get all hosting products for the dropdown
$catalog_table = $wpdb->prefix . 'im_product_catalog';
$hosting_products = $wpdb->get_results("SELECT * FROM $catalog_table WHERE service_type = 'Hosting' AND is_active = 1 ORDER BY name");

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_hosting_field']) && wp_verify_nonce($_POST['post_hosting_field'], 'post_hosting')) {
        // Registration date handling
        if (isset($_POST['registration_date']) && !empty($_POST['registration_date'])) {
            // Convert from dd/mm/yyyy to yyyy-mm-dd
            $registration_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['registration_date'])));
            $billing_cycle_months = intval($_POST['billing_cycle_months']);
            
            // Handle expiry date - use manual date if provided, otherwise calculate automatically
            if (isset($_POST['expiry_date']) && !empty($_POST['expiry_date'])) {
                // Convert from dd/mm/yyyy to yyyy-mm-dd
                $expiry_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['expiry_date'])));
            } else {
                // Calculate expiry date based on registration date and billing cycle
                $expiry_date = date('Y-m-d', strtotime($registration_date . " + {$billing_cycle_months} months"));
            }
            
            $owner_user_id = intval($_POST['owner_user_id']);
            $hosting_code = sanitize_text_field($_POST['hosting_code']);
            $product_catalog_id = intval($_POST['product_catalog_id']);
            $provider_id = !empty($_POST['provider_id']) ? intval($_POST['provider_id']) : null;
            $partner_id = !empty($_POST['partner_id']) ? intval($_POST['partner_id']) : null;
            $status = sanitize_text_field($_POST['status']);
            $ip_address = sanitize_text_field($_POST['ip_address']);
            $management_url = sanitize_text_field($_POST['management_url']);
            $management_username = sanitize_text_field($_POST['management_username']);
            $management_password = sanitize_text_field($_POST['management_password']);
            $notes = sanitize_textarea_field($_POST['notes']);
            
            // Validate required fields
            if (!empty($owner_user_id) && !empty($product_catalog_id) && !empty($registration_date)) {
                // Auto-generate hosting_code if it's empty
                if (empty($hosting_code)) {
                    // Get product code from catalog
                    $catalog_table = $wpdb->prefix . 'im_product_catalog';
                    $product = $wpdb->get_row($wpdb->prepare("SELECT code FROM $catalog_table WHERE id = %d", $product_catalog_id));
                    // Get user code
                    $users_table = $wpdb->prefix . 'im_users';
                    $user_data = $wpdb->get_row($wpdb->prepare("SELECT user_code FROM $users_table WHERE id = %d", $owner_user_id));
                    
                    if ($product && $user_data) {
                        // Count total hostings for this user (excluding current hosting) + 1
                        $hosting_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $table_name WHERE owner_user_id = %d AND id != %d", 
                            $owner_user_id, $hosting_id
                        ));
                        $next_number = $hosting_count + 1;
                        
                        // Generate hosting code: [Product Code][Count with padding][User Code]
                        $hosting_code = $product->code . str_pad($next_number, 2, '0', STR_PAD_LEFT) . $user_data->user_code;
                    }
                }
                
                // Encrypt management_password before updating if provided
                $encrypted_password = !empty($management_password) ? im_encrypt_password($management_password) : $hosting->management_password;
                
                $update_result = $wpdb->update(
                    $table_name,
                    array(
                        'owner_user_id' => $owner_user_id,
                        'hosting_code' => $hosting_code,
                        'product_catalog_id' => $product_catalog_id,
                        'provider_id' => $provider_id,
                        'registration_date' => $registration_date,
                        'billing_cycle_months' => $billing_cycle_months,
                        'expiry_date' => $expiry_date,
                        'partner_id' => $partner_id,
                        'status' => $status,
                        'ip_address' => $ip_address,
                        'management_url' => $management_url,
                        'management_username' => $management_username,
                        'management_password' => $encrypted_password,
                        'notes' => $notes
                    ),
                    array('id' => $hosting_id),
                    array(
                        '%d', '%s', '%d', '%d', '%s', '%d', '%s', 
                        '%d', '%s', '%s', '%s', '%s', '%s', '%s'
                    ),
                    array('%d')
                );

                if ($update_result !== false) {
                    $notification = '<div class="alert alert-success" role="alert">Cập nhật hosting thành công!</div>';
                    
                    // Reload hosting data after update
                    $hosting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $hosting_id));
                    
                    // Auto-redirect to previous page or hosting list after 1.5 seconds
                    $redirect_url = isset($_POST['redirect_url']) && !empty($_POST['redirect_url']) 
                        ? esc_url($_POST['redirect_url']) 
                        : home_url('/danh-sach-hosting/');
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "' . $redirect_url . '";
                        }, 1500);
                    </script>';
                } else {
                    $notification = '<div class="alert alert-danger" role="alert">Không thể cập nhật hosting. Vui lòng thử lại.</div>';
                }
            } else {
                $notification = '<div class="alert alert-warning" role="alert">Vui lòng điền đầy đủ thông tin bắt buộc.</div>';
            }
        } else {
            $notification = '<div class="alert alert-warning" role="alert">Vui lòng chọn ngày đăng ký.</div>';
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
                <a href="<?php echo home_url('/danh-sach-hosting/'); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Chỉnh sửa hosting</h3>
                </div>
            </div>
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php
                    if (isset($notification)) {
                        echo $notification;
                        // add more button to back to home page
                        echo '<div class="d-flex mt-3">';
                        echo '<a href="' . home_url('/danh-sach-hosting/') . '" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-list btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Xem danh sách hosting</span>
                              </a>';
                        echo '<a href="' . home_url('/hosting/?hosting_id=' . $hosting_id) . '" class="btn btn-info btn-icon-text ms-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-eye btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Xem chi tiết hosting</span>
                              </a>';
                        echo '</div>';
                    } else {
                    ?>
                    
                    <?php if (isset($user)): ?>
                    <div class="card card-rounded p-3 min-w395 mb-4">
                        <div class="d-flex justify-content-center flex-column text-center nav-link">
                            <i class="ph ph-user-circle icon-lg p-4"></i>
                            <span class="fw-bold p-2">
                                Thông tin khách hàng
                            </span>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <tr>
                                        <td class="fw-bold p-2">Mã khách hàng</td>
                                        <td><?php echo $user->user_code; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold p-2">Tên</td>
                                        <td><?php echo $user->name; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold p-2">Email</td>
                                        <td><?php echo $user->email; ?></td>
                                    </tr>
                                    <?php if (!empty($user->company_name)): ?>
                                    <tr>
                                        <td class="fw-bold p-2">Công ty</td>
                                        <td><?php echo $user->company_name; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($user->phone_number)): ?>
                                    <tr>
                                        <td class="fw-bold p-2">Số điện thoại</td>
                                        <td><?php echo $user->phone_number; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($website)): ?>
                    <div class="card card-rounded p-3 min-w395 mb-4">
                        <div class="d-flex justify-content-center flex-column text-center">
                            <i class="ph ph-globe icon-lg p-4"></i>
                            <span class="fw-bold p-2">
                                Website liên kết
                            </span>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <tr>
                                        <td class="fw-bold p-2">Tên website</td>
                                        <td>
                                            <a href="<?php echo home_url('/edit-website/?website_id=' . $website->id); ?>" class="nav-link">
                                                <?php echo $website->name; ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form class="forms-sample col-md-8 col-lg-10 d-flex flex-column" action="" method="post">
                        <input type="hidden" name="owner_user_id" value="<?php echo $hosting->owner_user_id; ?>">
                        <!-- Store previous page URL -->
                        <input type="hidden" name="redirect_url" id="redirect_url" value="">
                        
                        <div class="row">
                            <!-- Hosting Information Section -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header btn-primary">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-server me-2"></i>
                                            Thông tin hosting
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="hosting_code" class="fw-bold">Mã hosting</label>
                                            <input type="text" class="form-control" id="hosting_code" name="hosting_code" 
                                                   placeholder="HOST001" value="<?php echo esc_attr($hosting->hosting_code); ?>">
                                            <small class="form-text text-muted">Để trống để tự động tạo mã</small>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold" for="product_catalog_id">Gói hosting <span class="text-danger">*</span></label>
                                            <select class="js-example-basic-single w-100" name="product_catalog_id" id="product_catalog_id" required>
                                                <option value="">-- Chọn gói hosting --</option>
                                                <?php
                                                foreach ($hosting_products as $product) {
                                                    $selected = ($product->id == $hosting->product_catalog_id) ? 'selected' : '';
                                                    $product_display = $product->name;
                                                    if (!empty($product->code)) {
                                                        $product_display = '[' . $product->code . '] ' . $product->name;
                                                    }
                                                    echo '<option value="' . $product->id . '" data-cycle="' . $product->billing_cycle . '" data-code="' . (isset($product->code) ? $product->code : '') . '" ' . $selected . '>' . 
                                                        $product_display . '</option>';
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
                                                    $selected = ($provider->id == $hosting->provider_id) ? 'selected' : '';
                                                    echo '<option value="' . $provider->id . '" ' . $selected . '>' . $provider->name . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <small class="form-text text-muted">Chọn đơn vị cung cấp hosting</small>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group mb-3">
                                                    <label for="registration_date" class="fw-bold">Ngày đăng ký <span class="text-danger">*</span></label>
                                                    <div class="input-group date datepicker">
                                                        <input type="text" class="form-control" id="registration_date" name="registration_date" 
                                                               placeholder="<?php echo date('d/m/Y'); ?>" required
                                                               value="<?php echo date('d/m/Y', strtotime($hosting->registration_date)); ?>">
                                                        <span class="input-group-text bg-secondary text-white">
                                                            <i class="ph ph-calendar"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group mb-3">
                                                    <label for="billing_cycle_months" class="fw-bold">Chu kỳ thanh toán <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="billing_cycle_months" name="billing_cycle_months" required>
                                                        <option value="1" <?php selected($hosting->billing_cycle_months, 1); ?>>1 tháng</option>
                                                        <option value="3" <?php selected($hosting->billing_cycle_months, 3); ?>>3 tháng</option>
                                                        <option value="6" <?php selected($hosting->billing_cycle_months, 6); ?>>6 tháng</option>
                                                        <option value="12" <?php selected($hosting->billing_cycle_months, 12); ?>>12 tháng</option>
                                                        <option value="24" <?php selected($hosting->billing_cycle_months, 24); ?>>24 tháng</option>
                                                        <option value="36" <?php selected($hosting->billing_cycle_months, 36); ?>>36 tháng</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group mb-3">
                                                    <label for="expiry_date" class="fw-bold">Ngày hết hạn <span class="text-danger">*</span></label>
                                                    <div class="input-group date datepicker">
                                                        <input type="text" class="form-control" id="expiry_date" name="expiry_date" 
                                                               placeholder="<?php echo date('d/m/Y', strtotime('+1 year')); ?>" required
                                                               value="<?php echo date('d/m/Y', strtotime($hosting->expiry_date)); ?>">
                                                        <span class="input-group-text bg-warning text-white">
                                                            <i class="ph ph-calendar-x"></i>
                                                        </span>
                                                    </div>
                                                    <small class="form-text text-muted">Tự động tính từ ngày đăng ký + chu kỳ</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label class="fw-bold" for="partner_id">Đối tác</label>
                                            <select class="js-example-basic-single w-100" name="partner_id" id="partner_id">
                                                <option value="">-- Chọn đối tác --</option>
                                                <?php
                                                $users_table = $wpdb->prefix . 'im_users';
                                                $partners = $wpdb->get_results("SELECT * FROM $users_table WHERE user_type = 'PARTNER' ORDER BY name");
                                                foreach ($partners as $partner) {
                                                    $selected = ($partner->id == $hosting->partner_id) ? 'selected' : '';
                                                    echo '<option value="' . $partner->id . '" ' . $selected . '>' . $partner->user_code . ' - ' . $partner->name . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <small class="form-text text-muted">Chọn đối tác nếu có</small>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="notes" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control height-auto" id="notes" rows="3" placeholder="Ghi chú" name="notes"><?php echo esc_textarea($hosting->notes); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Management Information Section -->
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
                                                 <option value="NEW" <?php selected($hosting->status, 'NEW'); ?>>Chờ thanh toán</option>
                                                 <option value="ACTIVE" <?php selected($hosting->status, 'ACTIVE'); ?>>Đang hoạt động</option>
                                                 <option value="EXPIRED" <?php selected($hosting->status, 'EXPIRED'); ?>>Hết hạn</option>
                                                 <option value="SUSPENDED" <?php selected($hosting->status, 'SUSPENDED'); ?>>Bị tạm ngưng</option>
                                                 <option value="DELETED" <?php selected($hosting->status, 'DELETED'); ?>>Đã xóa</option>
                                             </select>
                                         </div>

                                        <div class="form-group mb-3">
                                            <label for="ip_address" class="fw-bold">Địa chỉ IP</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ph ph-globe"></i></span>
                                                <input type="text" class="form-control" id="ip_address" name="ip_address" 
                                                       placeholder="192.168.1.1" value="<?php echo esc_attr($hosting->ip_address); ?>">
                                            </div>
                                        </div>

                                        <hr class="my-4">
                                        <h6 class="mb-3 text-primary">Thông tin đăng nhập</h6>
                                        
                                        <div class="form-group mb-3">
                                            <label for="management_url" class="fw-bold">URL quản lý</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ph ph-link"></i></span>
                                                <input type="text" class="form-control" id="management_url" name="management_url" 
                                                       placeholder="https://cpanel.example.com" value="<?php echo esc_url($hosting->management_url); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="management_username" class="fw-bold">Username</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ph ph-user"></i></span>
                                                <input type="text" class="form-control" id="management_username" name="management_username" 
                                                       value="<?php echo esc_attr($hosting->management_username); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                             <label for="management_password" class="fw-bold">Password</label>
                                             <div class="input-group">
                                                 <input type="password" class="form-control" id="management_password" name="management_password" 
                                                        value="<?php echo esc_attr(im_decrypt_password($hosting->management_password)); ?>">
                                                 <button class="btn btn-secondary toggle-password" type="button">
                                                     <i class="ph ph-eye"></i>
                                                 </button>
                                             </div>
                                         </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php wp_nonce_field('post_hosting', 'post_hosting_field'); ?>
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Cập nhật hosting</span>
                            </button>
                            <a href="<?php echo home_url('/danh-sach-hosting/'); ?>" class="btn btn-light btn-icon-text ms-2 d-flex align-items-center border-radius-9">
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
// Make hosting products available to the centralized JavaScript
window.hostingProducts = <?php echo json_encode($hosting_products); ?>;

// Password toggle and billing cycle functionality is now handled by custom.js
</script>

<script>
// Auto-calculate expiry date based on registration date and billing cycle
function calculateExpiryDate() {
    const registrationDate = document.getElementById('registration_date').value;
    const billingCycle = parseInt(document.getElementById('billing_cycle_months').value);
    
    if (registrationDate && billingCycle) {
        // Parse date in dd/mm/yyyy format
        const dateParts = registrationDate.split('/');
        if (dateParts.length === 3) {
            const regDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]); // yyyy, mm-1, dd
            
            // Add billing cycle months
            const expiryDate = new Date(regDate);
            expiryDate.setMonth(expiryDate.getMonth() + billingCycle);
            
            // Format back to dd/mm/yyyy
            const day = String(expiryDate.getDate()).padStart(2, '0');
            const month = String(expiryDate.getMonth() + 1).padStart(2, '0');
            const year = expiryDate.getFullYear();
            
            document.getElementById('expiry_date').value = `${day}/${month}/${year}`;
        }
    }
}

// Attach event listeners
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
    
    const registrationDateField = document.getElementById('registration_date');
    const billingCycleField = document.getElementById('billing_cycle_months');
    
    if (registrationDateField) {
        registrationDateField.addEventListener('change', calculateExpiryDate);
        registrationDateField.addEventListener('blur', calculateExpiryDate);
    }
    
    if (billingCycleField) {
        billingCycleField.addEventListener('change', calculateExpiryDate);
    }
});
</script>

<?php
get_footer();
?>