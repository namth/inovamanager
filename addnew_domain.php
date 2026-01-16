<?php
/* 
    Template Name: Add New Domain
*/

global $wpdb;
$domains_table = $wpdb->prefix . 'im_domains';
$current_user_id = get_current_user_id();

// Get website ID from URL if provided
$website_id = $_GET['website_id'] ?? 0;

// Get redirect URL from HTTP_REFERER
$redirect_url = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer = esc_url($_SERVER['HTTP_REFERER']);
    // Validate that referer is from same domain
    if (strpos($referer, home_url()) === 0) {
        $redirect_url = $referer;
    }
}

// If website ID is provided, get website data
if ($website_id) {
    $websites_table = $wpdb->prefix . 'im_websites';
    $website = $wpdb->get_row("SELECT * FROM $websites_table WHERE id = $website_id");
    if ($website) {
        // Get owner's user data
        $users_table = $wpdb->prefix . 'im_users';
        $user = $wpdb->get_row("SELECT * FROM $users_table WHERE id = $website->owner_user_id");
        $user_id = $website->owner_user_id;
    }
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
        $price = sanitize_text_field($_POST['price']);
        $status = sanitize_text_field($_POST['status']);
        $managed_by_inova = isset($_POST['managed_by_inova']) ? 1 : 0;
        $dns_management = sanitize_text_field($_POST['dns_management']);
        $management_url = sanitize_text_field($_POST['management_url']);
        $management_username = sanitize_text_field($_POST['management_username']);
        $management_password = sanitize_text_field($_POST['management_password']);
        $notes = sanitize_text_field($_POST['notes']);
        $selected_website_id = !empty($_POST['selected_website_id']) ? intval($_POST['selected_website_id']) : 0;
        
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

            // Encrypt management_password before inserting
            $encrypted_password = !empty($management_password) ? im_encrypt_password($management_password) : '';
            
            $data = array(
                'domain_name' => $domain_name,
                'owner_user_id' => $owner_user_id,
                'create_by' => $current_user_id,
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
                'management_password' => $encrypted_password,
                'notes' => $notes
            );
            
            $insert = $wpdb->insert(
                $domains_table,
                $data
            );

            if ($insert) {
                $new_domain_id = $wpdb->insert_id;

                // Update website's domain_id if website_id was provided or selected
                $final_website_id = $website_id > 0 ? $website_id : $selected_website_id;
                if ($final_website_id > 0) {
                    $websites_table = $wpdb->prefix . 'im_websites';
                    $wpdb->update(
                        $websites_table,
                        array('domain_id' => $new_domain_id),
                        array('id' => $final_website_id),
                        array('%d'),
                        array('%d')
                    );
                }

                $notification = '<div class="alert alert-success" role="alert">Thêm domain mới thành công!</div>';
                
                // Redirect to previous page or domain list immediately
                $redirect_url = isset($_POST['redirect_url']) && !empty($_POST['redirect_url']) 
                    ? esc_url($_POST['redirect_url']) 
                    : home_url('/danh-sach-ten-mien/');
                wp_redirect($redirect_url);
                exit;
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
                        <!-- Store previous page URL -->
                        <input type="hidden" name="redirect_url" id="redirect_url" value="<?php echo $redirect_url; ?>">
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
                                        <?php if (!$website_id): ?>
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Website liên kết</label>
                                            <select class="js-example-basic-single w-100" name="selected_website_id" id="selected_website_id">
                                                <option value="">-- Chọn website (không bắt buộc) --</option>
                                                <?php
                                                $websites_table = $wpdb->prefix . 'im_websites';
                                                $users_table = $wpdb->prefix . 'im_users';
                                                $websites = $wpdb->get_results("
                                                    SELECT w.id, w.name, w.owner_user_id, u.user_code, u.name as owner_name
                                                    FROM $websites_table w
                                                    LEFT JOIN $users_table u ON w.owner_user_id = u.id
                                                    WHERE w.status = 'ACTIVE'
                                                    AND (w.domain_id IS NULL OR w.domain_id = 0)
                                                    ORDER BY w.name
                                                ");
                                                foreach ($websites as $ws) {
                                                    $website_label = $ws->name;
                                                    if (!empty($ws->owner_name)) {
                                                        $website_label .= ' (' . $ws->owner_name . ')';
                                                    }
                                                    echo '<option value="' . $ws->id . '" data-owner-id="' . $ws->owner_user_id . '">' . $website_label . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <small class="form-text text-muted">Chọn website để tự động điền chủ sở hữu và liên kết</small>
                                        </div>
                                        <?php endif; ?>

                                        <div class="form-group mb-3">
                                            <label for="domain_name" class="fw-bold">Tên miền <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="domain_name" name="domain_name" placeholder="example.com" required>
                                                <button type="button" class="btn btn-secondary" id="lookup-whois-btn">
                                                    <i class="ph ph-magnifying-glass me-1"></i> Looking up
                                                </button>
                                            </div>
                                            <small id="domain_type_info" class="form-text text-muted"></small>
                                            <div id="whois-lookup-status" class="mt-2"></div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Chủ sở hữu <span class="text-danger">*</span></label>
                                            <select class="js-example-basic-single w-100" name="owner_user_id" id="owner_user_id" required>
                                                <option value="">-- Chọn chủ sở hữu --</option>
                                                <?php
                                                $users_table = $wpdb->prefix . 'im_users';
                                                $users = $wpdb->get_results("SELECT * FROM $users_table ORDER BY name");
                                                foreach ($users as $user) {
                                                    $user_label = $user->name;
                                                    if (!empty($user->company_name)) {
                                                        $user_label .= ' (' . $user->company_name . ')';
                                                    }
                                                    $selected = (isset($user_id) && $user->id == $user_id) ? 'selected' : '';
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
                                                 <option value="NEW">Chờ thanh toán</option>
                                                 <option value="EXPIRED">Hết hạn</option>
                                                 <option value="SUSPENDED">Bị tạm ngưng</option>
                                                 <option value="DELETED">Đã xóa</option>
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

// Attach event listeners - Use jQuery after it's loaded
jQuery(document).ready(function($) {
    const registrationDateField = document.getElementById('registration_date');
    const registrationPeriodField = document.getElementById('registration_period_years');

    if (registrationDateField) {
        // Use jQuery to handle datepicker events properly
        $('#registration_date').on('change changeDate input blur', calculateExpiryDate);
    }

    if (registrationPeriodField) {
        registrationPeriodField.addEventListener('change', calculateExpiryDate);
    }

    // Auto-calculate when page loads if registration date is filled
    calculateExpiryDate();

    // Auto-fill owner when website is selected
    $('#selected_website_id').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const ownerId = selectedOption.attr('data-owner-id');

        if (ownerId) {
            // Set the owner select value using Select2
            $('[name="owner_user_id"]').val(ownerId).trigger('change');
        } else {
            // Clear owner selection if no website is selected
            $('[name="owner_user_id"]').val('').trigger('change');
        }
    });

    /**
     * Auto-fetch domain information from APILayer when domain name is entered
     */
    // WHOIS lookup when clicking the button
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