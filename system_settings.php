<?php
/*
    Template Name: System Settings
*/

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_vat'])) {
    if (isset($_POST['system_settings_nonce']) && wp_verify_nonce($_POST['system_settings_nonce'], 'system_settings_action')) {
        $action = sanitize_text_field($_POST['update_vat']);

        if ($action === 'save_vat_settings') {
            $vat_rates = array();
            $service_types = array('Hosting', 'Domain', 'Website', 'Maintenance');
            foreach ($service_types as $service_type) {
                if (isset($_POST['vat_rate_' . $service_type])) {
                    $vat_rates[$service_type] = floatval($_POST['vat_rate_' . $service_type]);
                }
            }
            if (update_option('inova_vat_rates', $vat_rates)) {
                $message = 'Cập nhật cấu hình VAT thành công!';
                $message_type = 'success';
            } else {
                $message = 'Không có thay đổi nào được cập nhật cho VAT.';
                $message_type = 'info';
            }
        } elseif ($action === 'save_cloudflare_settings') {
            update_option('cloudflare_api_token', sanitize_text_field($_POST['cloudflare_api_token']));
            update_option('cloudflare_account_id', sanitize_text_field($_POST['cloudflare_account_id']));
            $message = 'Cài đặt Cloudflare đã được lưu thành công!';
            $message_type = 'success';
        } elseif ($action === 'save_apilayer_settings') {
            update_option('apilayer_whois_api_key', sanitize_text_field($_POST['apilayer_whois_api_key']));
            $message = 'Cài đặt APILayer WHOIS đã được lưu thành công!';
            $message_type = 'success';
        } elseif ($action === 'save_payment_settings') {
            // Save payment settings for both account types
            update_option('payment_bank_code_no_vat', sanitize_text_field($_POST['payment_bank_code_no_vat']));
            update_option('payment_account_number_no_vat', sanitize_text_field($_POST['payment_account_number_no_vat']));
            update_option('payment_bank_code_with_vat', sanitize_text_field($_POST['payment_bank_code_with_vat']));
            update_option('payment_account_number_with_vat', sanitize_text_field($_POST['payment_account_number_with_vat']));

            // Also update old keys for backward compatibility (use no_vat as default)
            update_option('payment_bank_code', sanitize_text_field($_POST['payment_bank_code_no_vat']));
            update_option('payment_account_number', sanitize_text_field($_POST['payment_account_number_no_vat']));

            $message = 'Cài đặt thanh toán QR đã được lưu thành công!';
            $message_type = 'success';
        }
    } else {
        $message = 'Lỗi bảo mật!';
        $message_type = 'error';
    }
}

// Get current settings
$vat_rates = get_current_vat_rates();
$cloudflare_api_token = get_option('cloudflare_api_token', '');
$cloudflare_account_id = get_option('cloudflare_account_id', '');
$apilayer_whois_api_key = get_option('apilayer_whois_api_key', '');

// Get payment settings for both account types
$payment_bank_code_no_vat = get_option('payment_bank_code_no_vat', '');
$payment_account_number_no_vat = get_option('payment_account_number_no_vat', '');
$payment_bank_code_with_vat = get_option('payment_bank_code_with_vat', '');
$payment_account_number_with_vat = get_option('payment_account_number_with_vat', '');

// Static banks list from Sepay
$banks_list = array(
    array('name' => 'Ngân hàng TMCP Công thương Việt Nam', 'code' => 'ICB', 'bin' => '970415', 'short_name' => 'VietinBank'),
    array('name' => 'Ngân hàng TMCP Ngoại thương Việt Nam', 'code' => 'VCB', 'bin' => '970436', 'short_name' => 'Vietcombank'),
    array('name' => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam', 'code' => 'BIDV', 'bin' => '970418', 'short_name' => 'BIDV'),
    array('name' => 'Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam', 'code' => 'VBA', 'bin' => '970405', 'short_name' => 'Agribank'),
    array('name' => 'Ngân hàng TMCP Á Châu', 'code' => 'ACB', 'bin' => '970416', 'short_name' => 'ACB'),
    array('name' => 'Ngân hàng TMCP Đông Á', 'code' => 'DOB', 'bin' => '970406', 'short_name' => 'DongABank'),
    array('name' => 'Ngân hàng TMCP Phương Đông', 'code' => 'OCB', 'bin' => '970448', 'short_name' => 'OCB'),
    array('name' => 'Ngân hàng TMCP Quốc tế Việt Nam', 'code' => 'VIB', 'bin' => '970441', 'short_name' => 'VIB'),
    array('name' => 'Ngân hàng TMCP Sài Gòn Thương Tín', 'code' => 'STB', 'bin' => '970403', 'short_name' => 'Sacombank'),
    array('name' => 'Ngân hàng TMCP Sài Gòn - Hà Nội', 'code' => 'SHB', 'bin' => '970443', 'short_name' => 'SHB'),
    array('name' => 'Ngân hàng TMCP Kỹ thương Việt Nam', 'code' => 'TCB', 'bin' => '970407', 'short_name' => 'Techcombank'),
    array('name' => 'Ngân hàng TMCP Việt Nam Thịnh Vượng', 'code' => 'VPB', 'bin' => '970432', 'short_name' => 'VPBank'),
    array('name' => 'Ngân hàng TMCP Tiên Phong', 'code' => 'TPB', 'bin' => '970423', 'short_name' => 'TPBank'),
    array('name' => 'Ngân hàng TMCP Bản Việt', 'code' => 'VCCB', 'bin' => '970454', 'short_name' => 'VietCapitalBank'),
    array('name' => 'Ngân hàng TMCP Xuất Nhập khẩu Việt Nam', 'code' => 'EIB', 'bin' => '970431', 'short_name' => 'Eximbank'),
    array('name' => 'Ngân hàng TMCP Hàng Hải', 'code' => 'MSB', 'bin' => '970426', 'short_name' => 'MSB'),
    array('name' => 'Ngân hàng TMCP Quân đội', 'code' => 'MBB', 'bin' => '970422', 'short_name' => 'MBBank'),
    array('name' => 'Ngân hàng TMCP Việt Á', 'code' => 'VAB', 'bin' => '970427', 'short_name' => 'VietABank'),
    array('name' => 'Ngân hàng TMCP Nam Á', 'code' => 'NAB', 'bin' => '970428', 'short_name' => 'NamABank'),
    array('name' => 'Ngân hàng TMCP Bưu điện Liên Việt', 'code' => 'LPB', 'bin' => '970449', 'short_name' => 'LienVietPostBank'),
    array('name' => 'Ngân hàng TMCP Phát triển Thành phố Hồ Chí Minh', 'code' => 'HDB', 'bin' => '970437', 'short_name' => 'HDBank'),
    array('name' => 'Ngân hàng TMCP Bảo Việt', 'code' => 'BVB', 'bin' => '970438', 'short_name' => 'BaoVietBank'),
    array('name' => 'Ngân hàng TMCP Đại Chúng Việt Nam', 'code' => 'PVCB', 'bin' => '970412', 'short_name' => 'PVcomBank'),
    array('name' => 'Ngân hàng TMCP Sài Gòn Công Thương', 'code' => 'SGICB', 'bin' => '970400', 'short_name' => 'SaigonBank'),
    array('name' => 'Ngân hàng TMCP Bắc Á', 'code' => 'BAB', 'bin' => '970409', 'short_name' => 'BacABank'),
    array('name' => 'Ngân hàng TMCP Đại Dương', 'code' => 'Oceanbank', 'bin' => '970414', 'short_name' => 'Oceanbank'),
    array('name' => 'Ngân hàng TMCP Kiên Long', 'code' => 'KLB', 'bin' => '970452', 'short_name' => 'KienLongBank'),
    array('name' => 'Ngân hàng TMCP Phương Nam', 'code' => 'VNMART', 'bin' => '970430', 'short_name' => 'PGBank'),
    array('name' => 'Ngân hàng TMCP Xăng dầu Petrolimex', 'code' => 'PGB', 'bin' => '970430', 'short_name' => 'PGBank'),
    array('name' => 'Ngân hàng TMCP Việt Nam Thương Tín', 'code' => 'CTG', 'bin' => '970446', 'short_name' => 'VietBank'),
    array('name' => 'Ngân hàng TMCP Á Châu', 'code' => 'ABB', 'bin' => '970425', 'short_name' => 'ABBank'),
    array('name' => 'Ngân hàng TMCP Sài Gòn', 'code' => 'SCB', 'bin' => '970429', 'short_name' => 'SCB'),
    array('name' => 'Ngân hàng TMCP Quốc Dân', 'code' => 'NVB', 'bin' => '970419', 'short_name' => 'NCB'),
    array('name' => 'Ngân hàng TMCP An Bình', 'code' => 'ABBank', 'bin' => '970425', 'short_name' => 'ABBANK'),
    array('name' => 'Ngân hàng TMCP Đông Nam Á', 'code' => 'SEAB', 'bin' => '970440', 'short_name' => 'SeABank'),
    array('name' => 'Ngân hàng số CAKE by VPBank', 'code' => 'CAKE', 'bin' => '546034', 'short_name' => 'CAKE'),
    array('name' => 'Ngân hàng số Ubank by VPBank', 'code' => 'Ubank', 'bin' => '546035', 'short_name' => 'Ubank'),
    array('name' => 'Ngân hàng TNHH MTV CIMB Việt Nam', 'code' => 'CIMB', 'bin' => '422589', 'short_name' => 'CIMB'),
    array('name' => 'Ngân hàng TNHH MTV Standard Chartered Bank Việt Nam', 'code' => 'SCVN', 'bin' => '970410', 'short_name' => 'StandardChartered'),
    array('name' => 'Ngân hàng TNHH MTV Public Việt Nam', 'code' => 'PBVN', 'bin' => '970439', 'short_name' => 'PublicBank'),
    array('name' => 'Ngân hàng TNHH MTV HSBC Việt Nam', 'code' => 'HSBC', 'bin' => '458761', 'short_name' => 'HSBC'),
    array('name' => 'Ngân hàng TNHH MTV Woori Việt Nam', 'code' => 'WVN', 'bin' => '970457', 'short_name' => 'Woori'),
    array('name' => 'Ngân hàng Liên doanh Việt - Nga', 'code' => 'VRB', 'bin' => '970421', 'short_name' => 'VRB'),
    array('name' => 'Ngân hàng Nonghyup - Chi nhánh Hà Nội', 'code' => 'NHB HN', 'bin' => '801011', 'short_name' => 'Nonghyup'),
    array('name' => 'Ngân hàng KEB Hana – Chi nhánh TP.HCM', 'code' => 'KEBHANAHCM', 'bin' => '970467', 'short_name' => 'KEBHanaHCM'),
    array('name' => 'Ngân hàng KEB Hana – Chi nhánh Hà Nội', 'code' => 'KEBHANAHN', 'bin' => '970466', 'short_name' => 'KEBHanaHN'),
    array('name' => 'Ngân hàng Kookmin - Chi nhánh Hà Nội', 'code' => 'KBHN', 'bin' => '970462', 'short_name' => 'KookminHN'),
    array('name' => 'Ngân hàng Kookmin - Chi nhánh TP.HCM', 'code' => 'KBHCM', 'bin' => '970463', 'short_name' => 'KookminHCM'),
    array('name' => 'Ngân hàng Shinhan Việt Nam', 'code' => 'SHBVN', 'bin' => '970424', 'short_name' => 'ShinhanBank'),
    array('name' => 'Ngân hàng TNHH MTV Hong Leong Việt Nam', 'code' => 'HLBVN', 'bin' => '970442', 'short_name' => 'HongLeong'),
    array('name' => 'Ngân hàng United Overseas - Chi nhánh TP.HCM', 'code' => 'UOB', 'bin' => '970458', 'short_name' => 'UnitedOverseas'),
    array('name' => 'Ngân hàng Đại chúng TNHH Kasikornbank', 'code' => 'Kasikornbank', 'bin' => '668888', 'short_name' => 'Kasikornbank'),
    array('name' => 'Ngân hàng TNHH Indovina', 'code' => 'IVB', 'bin' => '970434', 'short_name' => 'Indovina')
);


get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">
                            <i class="ph ph-gear me-2"></i>
                            Cài đặt hệ thống
                        </h4>
                    </div>

                    <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php if ($message_type === 'success'): ?>
                            <i class="ph ph-check-circle me-2"></i>
                        <?php elseif ($message_type === 'info'): ?>
                            <i class="ph ph-info me-2"></i>
                        <?php else: ?>
                            <i class="ph ph-x-circle me-2"></i>
                        <?php endif; ?>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" id="systemSettingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="vat-tab" data-bs-toggle="tab" data-bs-target="#vat" type="button" role="tab" aria-controls="vat" aria-selected="true">
                                <i class="ph ph-percent me-2"></i>Cấu hình VAT
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cloudflare-tab" data-bs-toggle="tab" data-bs-target="#cloudflare" type="button" role="tab" aria-controls="cloudflare" aria-selected="false">
                                <i class="ph ph-cloud-arrow-up me-2"></i>Cloudflare API
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="apilayer-tab" data-bs-toggle="tab" data-bs-target="#apilayer" type="button" role="tab" aria-controls="apilayer" aria-selected="false">
                                <i class="ph ph-key me-2"></i>APILayer WHOIS API
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab" aria-controls="payment" aria-selected="false">
                                <i class="ph ph-qr-code me-2"></i>Thanh toán QR
                            </button>
                        </li>
                    </ul>

                    <!-- Tab panes -->
                    <div class="tab-content pt-4">
                        <!-- VAT Settings Tab -->
                        <div class="tab-pane fade show active" id="vat" role="tabpanel" aria-labelledby="vat-tab">
                            <form method="POST">
                                <?php wp_nonce_field('system_settings_action', 'system_settings_nonce'); ?>
                                <input type="hidden" name="update_vat" value="save_vat_settings">
                                <div class="alert alert-info">
                                    <i class="ph ph-info me-2"></i>
                                    <strong>Hướng dẫn:</strong> VAT được áp dụng tự động khi tạo hóa đơn.
                                </div>
                                <div class="row">
                                    <!-- Hosting VAT -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card border-danger h-100">
                                            <div class="card-header bg-danger text-white"><h5 class="mb-0"><i class="ph ph-cloud me-2"></i>Hosting</h5></div>
                                            <div class="card-body">
                                                <label class="form-label fw-bold">VAT Rate (%)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="vat_rate_Hosting" value="<?php echo isset($vat_rates['Hosting']) ? $vat_rates['Hosting'] : 10.00; ?>" step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Domain VAT -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card border-primary h-100">
                                            <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="ph ph-globe me-2"></i>Domain</h5></div>
                                            <div class="card-body">
                                                <label class="form-label fw-bold">VAT Rate (%)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="vat_rate_Domain" value="<?php echo isset($vat_rates['Domain']) ? $vat_rates['Domain'] : 10.00; ?>" step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Website VAT -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card border-info h-100">
                                            <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="ph ph-browser me-2"></i>Website</h5></div>
                                            <div class="card-body">
                                                <label class="form-label fw-bold">VAT Rate (%)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="vat_rate_Website" value="<?php echo isset($vat_rates['Website']) ? $vat_rates['Website'] : 0.00; ?>" step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Maintenance VAT -->
                                    <div class="col-md-6 mb-4">
                                        <div class="card border-warning h-100">
                                            <div class="card-header bg-warning text-dark"><h5 class="mb-0"><i class="ph ph-wrench me-2"></i>Maintenance</h5></div>
                                            <div class="card-body">
                                                <label class="form-label fw-bold">VAT Rate (%)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="vat_rate_Maintenance" value="<?php echo isset($vat_rates['Maintenance']) ? $vat_rates['Maintenance'] : 0.00; ?>" step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg"><i class="ph ph-check me-2"></i>Lưu cấu hình VAT</button>
                                </div>
                            </form>
                        </div>

                        <!-- Cloudflare Settings Tab -->
                        <div class="tab-pane fade" id="cloudflare" role="tabpanel" aria-labelledby="cloudflare-tab">
                            <form method="post">
                                <?php wp_nonce_field('system_settings_action', 'system_settings_nonce'); ?>
                                <input type="hidden" name="update_vat" value="save_cloudflare_settings">
                                <p class="card-text">Nhập Cloudflare API Token và Account ID để bật tính năng import website.</p>
                                <div class="mb-3">
                                    <label for="cloudflare_api_token" class="form-label fw-bold">API Token</label>
                                    <input type="password" class="form-control" id="cloudflare_api_token" name="cloudflare_api_token" value="<?php echo esc_attr($cloudflare_api_token); ?>" placeholder="Nhập Cloudflare API Token của bạn">
                                    <small class="form-text text-muted">Tạo token với quyền "Zone:Read" và "DNS:Read".</small>
                                </div>
                                <div class="mb-3">
                                    <label for="cloudflare_account_id" class="form-label fw-bold">Account ID</label>
                                    <input type="text" class="form-control" id="cloudflare_account_id" name="cloudflare_account_id" value="<?php echo esc_attr($cloudflare_account_id); ?>" placeholder="Nhập Cloudflare Account ID của bạn">
                                    <small class="form-text text-muted">Bạn có thể tìm thấy ID này trên trang chính của Cloudflare dashboard.</small>
                                </div>
                                <button type="submit" class="btn btn-info"><i class="ph ph-floppy-disk"></i> Lưu cài đặt Cloudflare</button>
                            </form>
                        </div>

                        <!-- APILayer WHOIS Settings Tab -->
                        <div class="tab-pane fade" id="apilayer" role="tabpanel" aria-labelledby="apilayer-tab">
                            <form method="post">
                                <?php wp_nonce_field('system_settings_action', 'system_settings_nonce'); ?>
                                <input type="hidden" name="update_vat" value="save_apilayer_settings">
                                <p class="card-text">Nhập API Key của APILayer WHOIS để tự động lấy thông tin domain.</p>
                                <div class="mb-3">
                                    <label for="apilayer_whois_api_key" class="form-label fw-bold">APILayer WHOIS API Key</label>
                                    <input type="password" class="form-control" id="apilayer_whois_api_key" name="apilayer_whois_api_key" value="<?php echo esc_attr($apilayer_whois_api_key); ?>" placeholder="Nhập API Key của bạn">
                                    <small class="form-text text-muted">API này được sử dụng ở trang "Thêm mới Domain" để lấy ngày đăng ký/hết hạn.</small>
                                </div>
                                <button type="submit" class="btn btn-info"><i class="ph ph-floppy-disk"></i> Lưu cài đặt APILayer</button>
                            </form>
                        </div>

                        <!-- Payment QR Settings Tab -->
                        <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
                            <form method="post">
                                <?php wp_nonce_field('system_settings_action', 'system_settings_nonce'); ?>
                                <input type="hidden" name="update_vat" value="save_payment_settings">

                                <div class="alert alert-info">
                                    <i class="ph ph-info me-2"></i>
                                    <strong>Hướng dẫn:</strong> Cấu hình 2 tài khoản ngân hàng riêng biệt để tự động tạo mã QR thanh toán trên hóa đơn dựa trên loại khách hàng (có/không nhận hóa đơn đỏ).
                                </div>

                                <!-- Section 1: Account for customers WITHOUT VAT invoice -->
                                <div class="card border-success mb-4">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0">
                                            <i class="ph ph-file me-2"></i>Tài khoản cho khách KHÔNG nhận hóa đơn đỏ
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="payment_bank_code_no_vat" class="form-label fw-bold">Ngân hàng <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="payment_bank_code_no_vat" name="payment_bank_code_no_vat">
                                                        <option value="">-- Chọn ngân hàng --</option>
                                                        <?php if (!empty($banks_list)): ?>
                                                            <?php foreach ($banks_list as $bank): ?>
                                                                <option value="<?php echo esc_attr($bank['short_name']); ?>"
                                                                        <?php selected($payment_bank_code_no_vat, $bank['short_name']); ?>>
                                                                    <?php echo esc_html($bank['name']); ?> (<?php echo esc_html($bank['short_name']); ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="payment_account_number_no_vat" class="form-label fw-bold">Số tài khoản <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="payment_account_number_no_vat" name="payment_account_number_no_vat" value="<?php echo esc_attr($payment_account_number_no_vat); ?>" placeholder="Nhập số tài khoản">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <?php if (!empty($payment_account_number_no_vat) && !empty($payment_bank_code_no_vat)): ?>
                                                <div class="text-center">
                                                    <p class="fw-bold mb-2">
                                                        <i class="ph ph-eye me-2"></i>Xem trước QR Code
                                                    </p>
                                                    <img src="https://img.vietqr.io/image/<?php echo esc_attr($payment_bank_code_no_vat); ?>-<?php echo esc_attr($payment_account_number_no_vat); ?>-compact2.jpg?amount=100000&addInfo=Test"
                                                         alt="QR Code Preview"
                                                         style="max-width: 200px;"
                                                         class="img-fluid rounded border p-2">
                                                    <p class="text-muted mt-2 mb-0 small">
                                                        <i class="ph ph-info me-1"></i>Ví dụ: 100,000 VNĐ
                                                    </p>
                                                </div>
                                                <?php else: ?>
                                                <div class="alert alert-warning mb-0">
                                                    <i class="ph ph-warning me-2"></i>
                                                    Chưa cấu hình tài khoản
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 2: Account for customers WITH VAT invoice -->
                                <div class="card border-warning mb-4">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0">
                                            <i class="ph ph-file-text me-2"></i>Tài khoản cho khách CÓ nhận hóa đơn đỏ
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="payment_bank_code_with_vat" class="form-label fw-bold">Ngân hàng <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="payment_bank_code_with_vat" name="payment_bank_code_with_vat">
                                                        <option value="">-- Chọn ngân hàng --</option>
                                                        <?php if (!empty($banks_list)): ?>
                                                            <?php foreach ($banks_list as $bank): ?>
                                                                <option value="<?php echo esc_attr($bank['short_name']); ?>"
                                                                        <?php selected($payment_bank_code_with_vat, $bank['short_name']); ?>>
                                                                    <?php echo esc_html($bank['name']); ?> (<?php echo esc_html($bank['short_name']); ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="payment_account_number_with_vat" class="form-label fw-bold">Số tài khoản <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="payment_account_number_with_vat" name="payment_account_number_with_vat" value="<?php echo esc_attr($payment_account_number_with_vat); ?>" placeholder="Nhập số tài khoản">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <?php if (!empty($payment_account_number_with_vat) && !empty($payment_bank_code_with_vat)): ?>
                                                <div class="text-center">
                                                    <p class="fw-bold mb-2">
                                                        <i class="ph ph-eye me-2"></i>Xem trước QR Code
                                                    </p>
                                                    <img src="https://img.vietqr.io/image/<?php echo esc_attr($payment_bank_code_with_vat); ?>-<?php echo esc_attr($payment_account_number_with_vat); ?>-compact2.jpg?amount=100000&addInfo=Test"
                                                         alt="QR Code Preview"
                                                         style="max-width: 200px;"
                                                         class="img-fluid rounded border p-2">
                                                    <p class="text-muted mt-2 mb-0 small">
                                                        <i class="ph ph-info me-1"></i>Ví dụ: 100,000 VNĐ
                                                    </p>
                                                </div>
                                                <?php else: ?>
                                                <div class="alert alert-warning mb-0">
                                                    <i class="ph ph-warning me-2"></i>
                                                    Chưa cấu hình tài khoản
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="ph ph-floppy-disk me-2"></i>Lưu cấu hình thanh toán
                                    </button>
                                </div>
                            </form>
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
