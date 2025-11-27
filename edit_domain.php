<?php
/* 
    Template Name: Edit Domain
*/

global $wpdb;
$domains_table = $wpdb->prefix . 'im_domains';
$current_user_id = get_current_user_id();

// Get domain ID from URL
$domain_id = isset($_GET['domain_id']) ? intval($_GET['domain_id']) : 0;

// Redirect if no domain ID provided
if (!$domain_id) {
    wp_redirect(home_url('/danh-sach-ten-mien/'));
    exit;
}

// Get domain data
$domain = $wpdb->get_row($wpdb->prepare("SELECT * FROM $domains_table WHERE id = %d", $domain_id));

// Redirect if domain not found
if (!$domain) {
    wp_redirect(home_url('/danh-sach-ten-mien/'));
    exit;
}

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

            // Encrypt management_password before updating if provided
            $encrypted_password = !empty($management_password) ? im_encrypt_password($management_password) : $domain->management_password;
            
            $data = array(
                'domain_name' => $domain_name,
                'owner_user_id' => $owner_user_id,
                'provider_id' => $provider_id,
                'product_catalog_id' => $product_catalog_id,
                'registration_date' => $registration_date,
                'registration_period_years' => $registration_period_years,
                'expiry_date' => $expiry_date,
                'status' => $status,
                'managed_by_inova' => $managed_by_inova,
                'dns_management' => $dns_management,
                'management_url' => $management_url,
                'management_username' => $management_username,
                'management_password' => $encrypted_password,
                'notes' => $notes
            );
            
            $update = $wpdb->update(
                $domains_table,
                $data,
                array('id' => $domain_id),
                array(
                    '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s', 
                    '%d', '%s', '%s', '%s', '%s', '%s'
                ),
                array('%d')
            );
            
            if ($update !== false) {
                $notification = '<div class="alert alert-success" role="alert">Cập nhật domain thành công</div>';
                
                // Refresh domain data
                $domain = $wpdb->get_row($wpdb->prepare("SELECT * FROM $domains_table WHERE id = %d", $domain_id));
            } else {
                $notification = '<div class="alert alert-danger" role="alert">Cập nhật thất bại hoặc không có thay đổi</div>';
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
                    <h3 class="display-3">Chỉnh sửa tên miền</h3>
                </div>
            </div>
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php
                    if (isset($notification)) {
                        echo $notification;
                        // add more button to back to home page
                        echo '<div class="d-flex mt-3">';
                        echo '<a href="' . home_url('/danh-sach-ten-mien/') . '" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-list btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Xem danh sách tên miền</span>
                              </a>';
                        echo '<a href="' . home_url('/domain/?domain_id=' . $domain_id) . '" class="btn btn-info btn-icon-text ms-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-eye btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Xem chi tiết tên miền</span>
                              </a>';
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
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="domain_name" name="domain_name" value="<?php echo esc_attr($domain->domain_name); ?>" required>
                                                <button type="button" class="btn btn-secondary" id="lookup-whois-btn">
                                                    <i class="ph ph-magnifying-glass me-1"></i> Looking up
                                                </button>
                                            </div>
                                            <small id="domain_type_info" class="form-text text-muted"></small>
                                            <div id="whois-lookup-status" class="mt-2"></div>
                                        </div>
                                        
                                        <!-- Hidden input for product_catalog_id that will be set automatically -->
                                        <input type="hidden" name="product_catalog_id" id="product_catalog_id" value="<?php echo esc_attr($domain->product_catalog_id); ?>">
                                        
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
                                                    $selected = ($user->id == $domain->owner_user_id) ? 'selected' : '';
                                                    echo '<option value="' . $user->id . '" ' . $selected . '>' . $user->user_code . ' - ' . $user_label . '</option>';
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
                                                    $selected = ($provider->id == $domain->provider_id) ? 'selected' : '';
                                                    echo '<option value="' . $provider->id . '" ' . $selected . '>' . $provider->user_code . ' - ' . $provider->name . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <small class="form-text text-muted">Chọn đơn vị cung cấp tên miền</small>
                                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="registration_date" class="fw-bold">Ngày đăng ký <span class="text-danger">*</span></label>
                                    <div class="input-group date datepicker">
                                        <input type="text" class="form-control" id="registration_date" name="registration_date" value="<?php echo date('d/m/Y', strtotime($domain->registration_date)); ?>" required>
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
                                            <option value="<?php echo $i; ?>" <?php echo ($i == $domain->registration_period_years) ? 'selected' : ''; ?>><?php echo $i; ?> năm</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="expiry_date" class="fw-bold">Ngày hết hạn <span class="text-danger">*</span></label>
                                    <div class="input-group date datepicker">
                                        <input type="text" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo date('d/m/Y', strtotime($domain->expiry_date)); ?>" required>
                                        <span class="input-group-text bg-secondary text-white">
                                            <i class="ph ph-calendar"></i>
                                        </span>
                                    </div>
                                    <small class="form-text text-muted">Tự động tính từ ngày đăng ký + thời hạn</small>
                                </div>
                            </div>
                        </div>                                        <div class="alert alert-info mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="ph ph-info me-2" style="font-size: 24px;"></i>
                                                <div>
                                                    <h6 class="mb-1">Thông tin hết hạn</h6>
                                                    <div>Ngày hết hạn: <strong><?php echo date('d/m/Y', strtotime($domain->expiry_date)); ?></strong></div>
                                                    <?php
                                                    $now = new DateTime();
                                                    $expiry = new DateTime($domain->expiry_date);
                                                    $days_remaining = $now->diff($expiry)->format("%r%a"); // Use %r to get the sign
                                                    
                                                    if ($days_remaining < 0) {
                                                        echo '<div class="text-danger">Đã quá hạn ' . abs($days_remaining) . ' ngày</div>';
                                                    } else {
                                                        echo '<div>Còn ' . $days_remaining . ' ngày</div>';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="notes" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control height-auto" id="notes" rows="3" placeholder="Ghi chú" name="notes"><?php echo esc_textarea($domain->notes); ?></textarea>
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
                                                 <option value="NEW" <?php selected($domain->status, 'NEW'); ?>>Chờ thanh toán</option>
                                                 <option value="ACTIVE" <?php selected($domain->status, 'ACTIVE'); ?>>Đang hoạt động</option>
                                                 <option value="EXPIRED" <?php selected($domain->status, 'EXPIRED'); ?>>Hết hạn</option>
                                                 <option value="SUSPENDED" <?php selected($domain->status, 'SUSPENDED'); ?>>Bị tạm ngưng</option>
                                                 <option value="DELETED" <?php selected($domain->status, 'DELETED'); ?>>Đã xóa</option>
                                             </select>
                                         </div>

                                        <div class="form-group mb-3">
                                            <div class="form-switch d-flex gap-2">
                                                <input class="form-check-input" type="checkbox" id="managed_by_inova" name="managed_by_inova" <?php checked($domain->managed_by_inova, 1); ?>>
                                                <label class="form-check-label fw-bold" for="managed_by_inova">
                                                    INOVA quản lý
                                                </label>
                                            </div>
                                            <i class="form-text"><small>Đánh dấu nếu INOVA đang quản lý tên miền này</small></i>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="dns_management" class="fw-bold">Quản lý DNS</label>
                                            <select class="form-control" id="dns_management" name="dns_management">
                                                <option value="PROVIDER" <?php selected($domain->dns_management, 'PROVIDER'); ?>>Nhà cung cấp</option>
                                                <option value="CLOUDFLARE" <?php selected($domain->dns_management, 'CLOUDFLARE'); ?>>Cloudflare</option>
                                                <option value="OTHER" <?php selected($domain->dns_management, 'OTHER'); ?>>Khác</option>
                                            </select>
                                        </div>

                                        <hr class="my-4">
                                        <h6 class="mb-3 text-primary">Thông tin đăng nhập</h6>
                                        
                                        <div class="form-group mb-3">
                                            <label for="management_url" class="fw-bold">URL quản lý</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ph ph-link"></i></span>
                                                <input type="text" class="form-control" id="management_url" name="management_url" placeholder="https://..." value="<?php echo esc_attr($domain->management_url); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="management_username" class="fw-bold">Username</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ph ph-user"></i></span>
                                                <input type="text" class="form-control" id="management_username" name="management_username" value="<?php echo esc_attr($domain->management_username); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                             <label for="management_password" class="fw-bold">Password</label>
                                             <div class="input-group">
                                                 <input type="password" class="form-control" id="management_password" name="management_password" value="<?php echo esc_attr(im_decrypt_password($domain->management_password)); ?>">
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
                                <span class="fw-bold">Lưu thay đổi</span>
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

// Attach event listeners - Use jQuery after it's loaded
jQuery(document).ready(function($) {
    const registrationDateField = document.getElementById('registration_date');
    const registrationPeriodField = document.getElementById('registration_period_years');
    
    if (registrationDateField) {
        registrationDateField.addEventListener('change', calculateExpiryDate);
        registrationDateField.addEventListener('blur', calculateExpiryDate);
    }
    
    if (registrationPeriodField) {
        registrationPeriodField.addEventListener('change', calculateExpiryDate);
    }

    /**
     * WHOIS lookup when clicking the button
     */
    $(document).on('click', '#lookup-whois-btn', function(e) {
        e.preventDefault();
        const domainName = $('#domain_name').val().trim();
        const $button = $(this);
        const $statusDiv = $('#whois-lookup-status');
        const $registrationDate = $('#registration_date');
        const $expiryDate = $('#expiry_date');

        if (!domainName) {
            $statusDiv.html('<div class="alert alert-warning"><i class="ph ph-warning me-1"></i>Vui lòng nhập tên miền trước.</div>');
            return;
        }

        if (!domainName.includes('.')) {
            $statusDiv.html('<div class="alert alert-warning"><i class="ph ph-warning me-1"></i>Tên miền không hợp lệ.</div>');
            return;
        }

        console.log('Manual WHOIS lookup for:', domainName);

        // Show loading state
        $button.prop('disabled', true).html('<i class="ph ph-spinner ph-spin me-1"></i> Đang tra cứu...');
        $statusDiv.html('<div class="alert alert-info"><i class="ph ph-info me-1"></i>Đang tra cứu thông tin WHOIS...</div>');
        $registrationDate.prop('disabled', true).val('Đang tải...');
        $expiryDate.prop('disabled', true).val('Đang tải...');

        $.ajax({
            url: AJAX.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'fetch_domain_info',
                domain: domainName
            },
            success: function(response) {
                console.log('WHOIS API Response:', response);

                // Reset button state
                $button.prop('disabled', false).html('<i class="ph ph-magnifying-glass me-1"></i> Looking up');

                if (response.success && response.data) {
                    let foundData = false;

                    // Fill in registration date if available
                    if (response.data.registration_date) {
                        const regDate = new Date(response.data.registration_date);
                        const regFormatted = ('0' + regDate.getDate()).slice(-2) + '/' +
                                           ('0' + (regDate.getMonth() + 1)).slice(-2) + '/' +
                                           regDate.getFullYear();
                        $registrationDate.val(regFormatted);
                        foundData = true;
                    }

                    // Fill in expiry date if available
                    if (response.data.expiry_date) {
                        const expDate = new Date(response.data.expiry_date);
                        const expFormatted = ('0' + expDate.getDate()).slice(-2) + '/' +
                                           ('0' + (expDate.getMonth() + 1)).slice(-2) + '/' +
                                           expDate.getFullYear();
                        $expiryDate.val(expFormatted);
                        foundData = true;
                    }

                    // Show success notification
                    if (foundData) {
                        $statusDiv.html('<div class="alert alert-success"><i class="ph ph-check-circle me-1"></i>Đã tra cứu thành công thông tin WHOIS!</div>');
                    } else {
                        $statusDiv.html('<div class="alert alert-warning"><i class="ph ph-warning me-1"></i>Không tìm thấy thông tin ngày đăng ký/hết hạn.</div>');
                    }
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : 'Không thể tra cứu thông tin WHOIS';
                    $statusDiv.html('<div class="alert alert-danger"><i class="ph ph-x-circle me-1"></i>' + errorMsg + '</div>');
                }

                // Re-enable fields
                $registrationDate.prop('disabled', false);
                $expiryDate.prop('disabled', false);

                // If no data was returned, clear the fields
                if (!response.success || !response.data || !response.data.registration_date) {
                    $registrationDate.val('');
                }
                if (!response.success || !response.data || !response.data.expiry_date) {
                    $expiryDate.val('');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error fetching domain information:', textStatus, errorThrown);

                // Reset button state
                $button.prop('disabled', false).html('<i class="ph ph-magnifying-glass me-1"></i> Looking up');

                $statusDiv.html('<div class="alert alert-danger"><i class="ph ph-x-circle me-1"></i>Lỗi kết nối đến server.</div>');
                $registrationDate.prop('disabled', false).val('');
                $expiryDate.prop('disabled', false).val('');
            }
        });
    });
});
</script>

<?php
get_footer();
?>