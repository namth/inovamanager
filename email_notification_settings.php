<?php
/*
    Template Name: Account Settings
*/

// Get current user ID
$current_user_id = get_current_user_id();

if (!$current_user_id) {
    wp_redirect(home_url('/login'));
    exit;
}

// Get user's email notification settings (or defaults)
$user_settings = get_user_meta($current_user_id, 'inova_email_notifications', true);

// If user hasn't set preferences, use global defaults
if (empty($user_settings)) {
    $user_settings = get_option('inova_email_notification_defaults', [
        'domain' => 1,
        'hosting' => 1,
        'maintenance' => 1
    ]);
}

// Create settings map for easy access
$settings_map = [
    'domain' => isset($user_settings['domain']) ? $user_settings['domain'] : 1,
    'hosting' => isset($user_settings['hosting']) ? $user_settings['hosting'] : 1,
    'maintenance' => isset($user_settings['maintenance']) ? $user_settings['maintenance'] : 1
];


get_header();
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">
                            <i class="ph ph-user-gear me-2"></i>Cài đặt tài khoản
                        </h4>
                    </div>

                    <div class="alert alert-primary">
                        <i class="ph ph-user-circle me-2"></i>
                        <strong>Cài đặt cá nhân</strong><br>
                        Quản lý các cài đặt cá nhân cho tài khoản của bạn bao gồm thông báo email và thông tin hóa đơn.
                    </div>

                    <!-- Section 1: Email Notification Settings -->
                    <h5 class="mb-3 mt-4">
                        <i class="ph ph-bell me-2"></i>Cấu hình thông báo Email
                    </h5>

                    <div class="alert alert-info">
                        <i class="ph ph-info me-2"></i>
                        <strong>Lưu ý:</strong> Email sẽ được gửi tự động tại các mốc thời gian sau:
                        <ul class="mb-0 mt-2">
                            <li>30 ngày trước hết hạn</li>
                            <li>7 ngày trước hết hạn</li>
                            <li>3 ngày trước hết hạn</li>
                            <li>Ngày hết hạn</li>
                            <li>1 ngày quá hạn</li>
                            <li>2 tuần quá hạn</li>
                        </ul>
                    </div>

                    <div class="table-responsive mt-4">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <th style="width: 60px;">
                                        <i class="ph ph-list-checks"></i>
                                    </th>
                                    <th>Loại dịch vụ</th>
                                    <th style="width: 200px;">Gửi thông báo</th>
                                    <th>Mô tả</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center">
                                        <i class="ph ph-globe text-primary" style="font-size: 24px;"></i>
                                    </td>
                                    <td>
                                        <h6 class="mb-0">Tên miền (Domain)</h6>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input email-notification-toggle"
                                                   type="checkbox"
                                                   id="toggle-domain"
                                                   data-service-type="domain"
                                                   <?php echo (isset($settings_map['domain']) && $settings_map['domain']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="toggle-domain">
                                                <span class="status-text">
                                                    <?php echo (isset($settings_map['domain']) && $settings_map['domain']) ? 'Bật' : 'Tắt'; ?>
                                                </span>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="text-muted">
                                        Gửi email thông báo khi tên miền sắp hết hạn
                                    </td>
                                </tr>

                                <tr>
                                    <td class="text-center">
                                        <i class="ph ph-database text-success" style="font-size: 24px;"></i>
                                    </td>
                                    <td>
                                        <h6 class="mb-0">Hosting</h6>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input email-notification-toggle"
                                                   type="checkbox"
                                                   id="toggle-hosting"
                                                   data-service-type="hosting"
                                                   <?php echo (isset($settings_map['hosting']) && $settings_map['hosting']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="toggle-hosting">
                                                <span class="status-text">
                                                    <?php echo (isset($settings_map['hosting']) && $settings_map['hosting']) ? 'Bật' : 'Tắt'; ?>
                                                </span>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="text-muted">
                                        Gửi email thông báo khi hosting sắp hết hạn
                                    </td>
                                </tr>

                                <tr>
                                    <td class="text-center">
                                        <i class="ph ph-wrench text-warning" style="font-size: 24px;"></i>
                                    </td>
                                    <td>
                                        <h6 class="mb-0">Gói bảo trì (Maintenance)</h6>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input email-notification-toggle"
                                                   type="checkbox"
                                                   id="toggle-maintenance"
                                                   data-service-type="maintenance"
                                                   <?php echo (isset($settings_map['maintenance']) && $settings_map['maintenance']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="toggle-maintenance">
                                                <span class="status-text">
                                                    <?php echo (isset($settings_map['maintenance']) && $settings_map['maintenance']) ? 'Bật' : 'Tắt'; ?>
                                                </span>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="text-muted">
                                        Gửi email thông báo khi gói bảo trì sắp hết hạn
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>


                    <div class="alert alert-success mt-4" id="success-message" style="display: none;">
                        <i class="ph ph-check-circle me-2"></i>
                        <span id="success-text"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    /**
     * Handle email notification toggle
     * Files using this: email_notification_settings.php (only this page)
     */
    $('.email-notification-toggle').on('change', function() {
        var $toggle = $(this);
        var serviceType = $toggle.data('service-type');
        var isEnabled = $toggle.is(':checked') ? 1 : 0;
        var $statusText = $toggle.closest('.form-switch').find('.status-text');

        // Disable toggle during AJAX
        $toggle.prop('disabled', true);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'update_email_notification_setting',
                service_type: serviceType,
                is_enabled: isEnabled
            },
            success: function(response) {
                if (response.success) {
                    // Update status text
                    $statusText.text(isEnabled ? 'Bật' : 'Tắt');

                    // Show success message
                    var message = 'Đã ' + (isEnabled ? 'bật' : 'tắt') + ' thông báo email cho ' + getServiceTypeName(serviceType);
                    $('#success-text').text(message);
                    $('#success-message').fadeIn().delay(3000).fadeOut();
                } else {
                    alert('Có lỗi xảy ra: ' + (response.data?.message || 'Unknown error'));
                    // Revert toggle
                    $toggle.prop('checked', !isEnabled);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Có lỗi xảy ra khi cập nhật cài đặt.');
                // Revert toggle
                $toggle.prop('checked', !isEnabled);
            },
            complete: function() {
                // Re-enable toggle
                $toggle.prop('disabled', false);
            }
        });
    });

    function getServiceTypeName(serviceType) {
        var names = {
            'domain': 'Tên miền',
            'hosting': 'Hosting',
            'maintenance': 'Gói bảo trì'
        };
        return names[serviceType] || serviceType;
    }

});
</script>

<?php
get_footer();
?>
