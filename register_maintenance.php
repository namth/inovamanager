<?php
/*
    Template Name: Register Maintenance Package
*/

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

// Only allow regular users, not admins
if (is_inova_admin()) {
    wp_redirect(home_url('/dashboard/'));
    exit;
}

global $wpdb;
$users_table = $wpdb->prefix . 'im_users';
$websites_table = $wpdb->prefix . 'im_websites';

// Get current user info
$current_wp_user_id = get_current_user_id();
$current_user_obj = wp_get_current_user();
$current_inova_user = get_inova_user($current_wp_user_id);
$current_inova_user_id = $current_inova_user->id ?? null;

// Get user's websites
$user_websites = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name FROM $websites_table WHERE owner_user_id = %d AND (status IS NULL OR status != 'DELETED') ORDER BY name ASC",
    $current_inova_user_id
));

// Get selected website ID from URL parameter (required)
$selected_website_id = isset($_GET['website_id']) ? intval($_GET['website_id']) : 0;

if (empty($selected_website_id)) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

// Get the selected website details
$selected_website = $wpdb->get_row($wpdb->prepare(
    "SELECT id, name FROM $websites_table WHERE id = %d AND owner_user_id = %d",
    $selected_website_id,
    $current_inova_user_id
));

// Verify website exists and belongs to current user
if (empty($selected_website)) {
    wp_redirect(home_url('/list-website/'));
    exit;
}

get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-body p-4">
                    <!-- Header Section -->
                    <div class="mb-4">
                        <h2 class="card-title mb-2">
                            <i class="ph ph-wrench me-2 text-warning"></i>
                            Đăng ký Gói Bảo trì Website
                        </h2>
                        <p class="text-muted mb-0">Yêu cầu bảo trì và bảo dưỡng website của bạn</p>
                    </div>

                    <!-- Introduction Section -->
                    <div class="alert bg-light-info text-info p-3 mb-4">
                        <h5 class="mb-2">
                            <i class="ph ph-info me-2"></i>
                            Giới thiệu về Gói Bảo trì
                        </h5>
                        <p class="mb-2">
                            Dịch vụ bảo trì toàn diện của chúng tôi giúp đảm bảo website của bạn luôn hoạt động ổn định, an toàn và được cập nhật.
                        </p>
                        <p class="mb-2">
                            <strong>Quy trình đăng ký:</strong>
                        </p>
                        <ul class="mb-0">
                            <li>Bạn gửi yêu cầu bảo trì với thông tin chi tiết về website</li>
                            <li>Chúng tôi sẽ kiểm tra website và đánh giá tình trạng hiện tại</li>
                            <li>Dựa vào kích thước dữ liệu (GB), chúng tôi sẽ báo giá cụ thể cho bạn</li>
                            <li>Bạn xác nhận và ký kết hợp đồng dịch vụ</li>
                        </ul>
                    </div>

                    <!-- Service Details Section -->
                    <div class="alert bg-light-primary text-primary p-3 mb-4">
                        <h5 class="mb-3">
                            <i class="ph ph-list-checks me-2"></i>
                            Nội dung bảo trì bao gồm
                        </h5>
                        
                        <div class="mb-3">
                            <h6 class="mb-2 text-primary"><strong>🔒 Công việc thực hiện ngay:</strong></h6>
                            <ul class="mb-0 ps-3 small">
                                <li>Sao lưu toàn bộ mã nguồn và dữ liệu database</li>
                                <li>Quét và khắc phục các lỗi bảo mật: SQL Injection, XSS, Clickjacking, CSRF, Session Fixation</li>
                                <li>Cấu hình SSL/HTTPS an toàn, ẩn đường dẫn admin, cường hóa bảo mật đăng nhập</li>
                                <li>Phát hiện và xóa mã độc, virus trên website</li>
                                <li>Cấu hình phân quyền file và thư mục, kích hoạt bảo vệ chống tấn công DDOS</li>
                            </ul>
                        </div>

                        <div class="mb-3">
                            <h6 class="mb-2 text-primary"><strong>📅 Công việc định kỳ hàng tháng:</strong></h6>
                            <ul class="mb-0 ps-3 small">
                                <li>Sao lưu database hàng tuần, sao lưu source code hàng tháng</li>
                                <li>Cập nhật bảo mật hệ thống, nâng cấp WordPress core</li>
                                <li>Cập nhật và nâng cấp các plugin, theme, PHP & MySQL</li>
                                <li>Kiểm tra error_log, phát hiện file lạ và các vấn đề bảo mật</li>
                            </ul>
                        </div>

                        <div>
                            <h6 class="mb-2 text-primary"><strong>🚨 Hỗ trợ xử lý sự cố:</strong></h6>
                            <ul class="mb-0 ps-3 small">
                                <li>Khôi phục website khi gặp lỗi hoặc hỏng, hỗ trợ tức thời khi bị tấn công</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Pricing Section -->
                    <div class="alert bg-light-warning text-dark p-3 mb-4">
                        <h5 class="mb-2">
                            <i class="ph ph-tag me-2 text-warning"></i>
                            Cách tính chi phí bảo trì
                        </h5>
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="fs-5 fw-bold text-danger">100.000 VNĐ</span>
                            <span class="text-muted">/tháng/1GB dữ liệu</span>
                        </div>
                        <p class="mt-2 mb-0 small text-muted">
                            Ví dụ: Website 5GB = 500.000 VNĐ/tháng
                        </p>
                    </div>

                    <!-- Registration Form -->
                    <form id="maintenance-registration-form">
                        <!-- User Info Section -->
                        <div class="mb-4">
                            <h5 class="mb-3">
                                <i class="ph ph-user me-2"></i>
                                Thông tin người đăng ký
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Họ và tên</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo esc_attr($current_user_obj->display_name); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" 
                                           value="<?php echo esc_attr($current_user_obj->user_email); ?>" readonly>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                           placeholder="Nhập số điện thoại" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zalo (nếu có)</label>
                                    <input type="text" class="form-control" id="zalo_number" name="zalo_number" 
                                           placeholder="Nhập số Zalo">
                                </div>
                            </div>
                        </div>

                        <!-- Website Display -->
                        <div class="mb-4">
                            <label class="form-label small text-muted">Website cần bảo trì</label>
                            <div class="d-flex align-items-baseline gap-2 mb-4">
                                <i class="ph ph-globe text-primary" style="font-size: 24px;"></i>
                                <span class="fs-5 fw-bold text-primary"><?php echo esc_html($selected_website->name); ?></span>
                            </div>
                            <input type="hidden" id="website_id" name="website_id" value="<?php echo $selected_website->id; ?>">
                        </div>

                        <!-- Additional Information -->
                        <div class="mb-4">
                            <h5 class="mb-3">
                                <i class="ph ph-note-pencil me-2"></i>
                                Yêu cầu bổ sung
                            </h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Nội dung bổ sung (tuỳ chọn)</label>
                                <textarea class="form-control" id="maintenance_notes" name="maintenance_notes" 
                                          placeholder="" style="resize: vertical; min-height: 150px;"></textarea>
                                <small class="text-muted">Mô tả thêm các nhu cầu hoặc vấn đề cụ thể cần chúng tôi hỗ trợ</small>
                            </div>
                        </div>

                        <!-- Contact Info Section -->
                        <div class="alert bg-light-primary text-primary p-3 mb-4">
                            <h5 class="mb-2">
                                <i class="ph ph-phone me-2"></i>
                                Thông tin liên hệ của chúng tôi
                            </h5>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <strong>Email:</strong><br>
                                    <a href="mailto:namth.pass@gmail.com" class="text-decoration-none">
                                        namth.pass@gmail.com
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <strong>Điện thoại:</strong><br>
                                    <a href="tel:0986896800" class="text-decoration-none">
                                        0986 896 800
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <strong>Zalo:</strong><br>
                                    <a href="https://zalo.me/0986896800" class="text-decoration-none" target="_blank">
                                        0986 896 800
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success" id="submit-btn">
                                <i class="ph ph-check-circle me-2"></i>
                                Gửi Yêu cầu Đăng ký
                            </button>
                            <a href="<?php echo home_url('/list-website/'); ?>" class="btn btn-secondary">
                                <i class="ph ph-arrow-left me-2"></i>
                                Quay lại
                            </a>
                        </div>
                        <?php wp_nonce_field('maintenance_registration', 'nonce'); ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="ph ph-check-circle text-success" style="font-size: 48px;"></i>
                </div>
                <h5 class="modal-title mb-2">Đăng ký thành công!</h5>
                <p class="text-muted mb-4">
                    Yêu cầu của bạn đã được gửi thành công. Chúng tôi sẽ liên hệ lại trong thời gian sớm nhất.
                </p>
                <a href="<?php echo home_url('/list-website/'); ?>" class="btn btn-success">
                    Quay lại danh sách website
                </a>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#maintenance-registration-form').on('submit', function(e) {
        e.preventDefault();

        var submitBtn = $('#submit-btn');
        var originalBtnText = submitBtn.html();

        // Show loading state
        submitBtn.prop('disabled', true).html('<i class="ph ph-spinner ph-spin me-2"></i>Đang gửi...');

        var formData = {
            action: 'register_maintenance_package',
            website_id: $('#website_id').val(),
            phone_number: $('#phone_number').val(),
            zalo_number: $('#zalo_number').val(),
            maintenance_notes: $('#maintenance_notes').val(),
            nonce: $('input[name="nonce"]').val()
        };

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success modal
                    var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                    
                    // Reset form
                    $('#maintenance-registration-form')[0].reset();
                    
                    // Redirect immediately
                    window.location.href = '<?php echo home_url('/list-website/'); ?>';
                } else {
                    alert('Lỗi: ' + (response.data.message || 'Có lỗi xảy ra'));
                    submitBtn.prop('disabled', false).html(originalBtnText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Đã có lỗi xảy ra. Vui lòng thử lại.');
                submitBtn.prop('disabled', false).html(originalBtnText);
            }
        });
    });
});
</script>

<?php
get_footer();
?>
