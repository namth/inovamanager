<?php
/* 
    Template Name: Add New Domain Simple
*/

global $wpdb;
$domains_table = $wpdb->prefix . 'im_domains';
$current_user_id = get_current_user_id();
$inova_user_id = get_user_inova_id($current_user_id);

// Check if user is logged in
if (!$current_user_id) {
    wp_redirect(home_url('/login/'));
    exit;
}

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_domain_simple_field']) && wp_verify_nonce($_POST['post_domain_simple_field'], 'post_domain_simple')) {
        $domain_name = sanitize_text_field($_POST['domain_name']);
        
        // Check if domain name is empty
        if (empty($domain_name)) {
            $notification = '<div class="alert alert-warning" role="alert">Tên miền là trường bắt buộc</div>';
        } else {
            // Optional fields
            $management_option = !empty($_POST['management_option']) ? sanitize_text_field($_POST['management_option']) : 'enter_info';
            $management_url = null;
            $management_username = null;
            $management_password = null;
            $notes = !empty($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : null;

            // If using management info from another domain
            if ($management_option === 'same_as_other' && !empty($_POST['copy_from_domain_id'])) {
                $copy_from_domain_id = intval($_POST['copy_from_domain_id']);
                $source_domain = $wpdb->get_row($wpdb->prepare(
                    "SELECT management_url, management_username, management_password FROM $domains_table WHERE id = %d AND owner_user_id = %d",
                    $copy_from_domain_id,
                    $inova_user_id
                ));
                
                if ($source_domain) {
                    $management_url = $source_domain->management_url;
                    $management_username = $source_domain->management_username;
                    $management_password = $source_domain->management_password;
                }
            } else {
                // Use entered information
                $management_url = !empty($_POST['management_url']) ? sanitize_text_field($_POST['management_url']) : null;
                $management_username = !empty($_POST['management_username']) ? sanitize_text_field($_POST['management_username']) : null;
                $management_password = !empty($_POST['management_password']) ? sanitize_text_field($_POST['management_password']) : null;
            }
            
            // Handle registration date
            $registration_date = null;
            $registration_period_years = 1;
            $expiry_date = null;
            
            if (!empty($_POST['registration_date'])) {
                $registration_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['registration_date'])));
                $registration_period_years = !empty($_POST['registration_period_years']) ? intval($_POST['registration_period_years']) : 1;
                
                // Handle expiry date
                if (!empty($_POST['expiry_date'])) {
                    $expiry_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['expiry_date'])));
                } else {
                    // Calculate automatically
                    $expiry_date = date('Y-m-d', strtotime($registration_date . " + {$registration_period_years} years"));
                }
            }
            
            // Encrypt management_password before inserting
            $encrypted_password = !empty($management_password) ? im_encrypt_password($management_password) : '';
            
            // Build data array
             $data = array(
                 'domain_name' => $domain_name,
                 'owner_user_id' => $inova_user_id,
                 'create_by' => $current_user_id,
                 'registration_date' => $registration_date,
                 'registration_period_years' => $registration_period_years,
                 'expiry_date' => $expiry_date,
                 'managed_by_inova' => 0,
                 'management_url' => $management_url,
                 'management_username' => $management_username,
                 'management_password' => $encrypted_password,
                 'notes' => $notes,
                 'status' => 'ACTIVE'
             );
            
            // Insert into database
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
                    <form class="forms-sample col-md-8 col-lg-6 d-flex flex-column" action="" method="post">
                        <div class="card mb-4">
                            <div class="card-header btn-primary">
                                <h5 class="mb-1 mt-1">
                                    <i class="ph ph-globe-hemisphere-west me-2"></i>
                                    Thông tin tên miền
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Domain Name (Required) -->
                                <div class="form-group mb-3">
                                    <label for="domain_name" class="fw-bold">Tên miền <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="domain_name" name="domain_name" placeholder="example.com" required>
                                        <button type="button" class="d-flex align-items-center btn btn-secondary" id="lookup-whois-btn">
                                            <i class="ph ph-magnifying-glass me-1"></i> Looking up
                                        </button>
                                    </div>
                                    <small class="form-text text-muted"><i class="ph ph-info me-1"></i>Nhập tên miền (bắt buộc). Nhấn "Looking up" để tra cứu ngày đăng ký và hết hạn tự động (chỉ áp dụng với tên miền quốc tế)</small>
                                    <div id="whois-lookup-status" class="mt-2"></div>
                                </div>

                                <!-- Registration Date -->
                                <div class="form-group mb-3">
                                    <label for="registration_date" class="fw-bold">Ngày đăng ký</label>
                                    <div class="input-group date datepicker">
                                        <input type="text" class="form-control" id="registration_date" name="registration_date" placeholder="dd/mm/yyyy">
                                        <span class="input-group-text btn-secondary">
                                            <i class="ph ph-calendar"></i>
                                        </span>
                                    </div>
                                    <small class="form-text text-muted">Ngày đăng ký tên miền</small>
                                </div>

                                <!-- Registration Period -->
                                <div class="form-group mb-3">
                                    <label for="registration_period_years" class="fw-bold">Thời hạn (năm)</label>
                                    <select class="form-control" id="registration_period_years" name="registration_period_years">
                                        <?php for ($i = 1; $i <= 10; $i++) : ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i == 1 ? 'selected' : ''; ?>><?php echo $i; ?> năm</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <!-- Expiry Date -->
                                <div class="form-group mb-3">
                                    <label for="expiry_date" class="fw-bold">Ngày hết hạn</label>
                                    <div class="input-group date datepicker">
                                        <input type="text" class="form-control" id="expiry_date" name="expiry_date" placeholder="dd/mm/yyyy">
                                        <span class="input-group-text btn-secondary">
                                            <i class="ph ph-calendar"></i>
                                        </span>
                                    </div>
                                    <small class="form-text text-muted">Tự động tính từ ngày đăng ký + thời hạn</small>
                                </div>

                                <!-- Management Information -->
                                <hr class="my-4">
                                <h6 class="mb-3 text-primary">Thông tin quản lý (tùy chọn)</h6>

                                <!-- Management Option Radio -->
                                <div class="form-group d-flex gap-5 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input management-option-radio" type="radio" name="management_option" id="management_option_enter" value="enter_info" checked>
                                        <label class="form-check-label" for="management_option_enter">
                                            Nhập thông tin quản lý
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input management-option-radio" type="radio" name="management_option" id="management_option_same" value="same_as_other">
                                        <label class="form-check-label" for="management_option_same">
                                            Cùng chung tài khoản với domain khác
                                        </label>
                                    </div>
                                </div>

                                <!-- Management Information Fields (shown by default) -->
                                <div id="management_info_section" class="management-info-section">
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
                                            <input type="text" class="form-control" id="management_username" name="management_username" placeholder="Username">
                                        </div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="management_password" class="fw-bold">Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="management_password" name="management_password" placeholder="Password">
                                            <button class="btn btn-secondary toggle-password" type="button">
                                                <i class="ph ph-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Copy from Domain Selection (hidden by default) -->
                                <div id="copy_domain_section" class="copy-domain-section d-none">
                                    <div class="form-group mb-3">
                                        <label for="copy_from_domain_id" class="fw-bold">Chọn tên miền để sao chép thông tin quản lý</label>
                                        <select class="form-control" id="copy_from_domain_id" name="copy_from_domain_id">
                                            <option value="">-- Chọn tên miền --</option>
                                            <?php
                                            // Get all domains of current user
                                            $user_domains = $wpdb->get_results($wpdb->prepare(
                                                "SELECT id, domain_name FROM $domains_table WHERE owner_user_id = %d AND status = 'ACTIVE' ORDER BY domain_name ASC",
                                                $inova_user_id
                                            ));
                                            
                                            if ($user_domains) {
                                                foreach ($user_domains as $domain) {
                                                    echo '<option value="' . esc_attr($domain->id) . '">' . esc_html($domain->domain_name) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <small class="form-text text-muted">Thông tin quản lý sẽ được sao chép từ tên miền được chọn</small>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="form-group mb-3">
                                    <label for="notes" class="fw-bold">Ghi chú</label>
                                    <textarea class="form-control height-auto" id="notes" rows="3" placeholder="Ghi chú thêm về tên miền" name="notes"></textarea>
                                </div>
                            </div>
                        </div>

                        <?php
                        wp_nonce_field('post_domain_simple', 'post_domain_simple_field');
                        ?>
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-globe btn-icon-prepend fa-150p"></i>
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
// Auto-calculate expiry date based on registration date and registration period
function calculateExpiryDate() {
    const registrationDate = document.getElementById('registration_date').value;
    const registrationPeriodYears = parseInt(document.getElementById('registration_period_years').value);
    
    if (registrationDate && registrationPeriodYears) {
        // Parse date in dd/mm/yyyy format
        const dateParts = registrationDate.split('/');
        if (dateParts.length === 3) {
            const regDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
            
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
        $('#registration_date').on('change changeDate input blur', calculateExpiryDate);
    }

    if (registrationPeriodField) {
        registrationPeriodField.addEventListener('change', calculateExpiryDate);
    }

    // Auto-calculate when page loads if registration date is filled
    calculateExpiryDate();

    /**
     * Management Option Toggle
     * Toggle between entering management info and copying from another domain
     */
    $('.management-option-radio').on('change', function() {
        const selectedOption = $('input[name="management_option"]:checked').val();
        
        if (selectedOption === 'same_as_other') {
            // Hide management info fields and show domain selection
            $('#management_info_section').addClass('d-none');
            $('#copy_domain_section').removeClass('d-none');
        } else {
            // Show management info fields and hide domain selection
            $('#management_info_section').removeClass('d-none');
            $('#copy_domain_section').addClass('d-none');
        }
    });

    /**
     * WHOIS lookup when clicking the button
     * Fetch domain registration and expiry dates from WHOIS API
     */
    $('#lookup-whois-btn').on('click', function() {
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
