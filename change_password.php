<?php
/*
    Template Name: Change Password
*/

global $wpdb;

# Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('login'));
    exit;
}

$current_user = wp_get_current_user();

get_header();
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-md-5 mx-auto">
            <div class="card mt-5">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="ph ph-lock me-2"></i>Đổi Mật Khẩu</h5>
                    <form id="change-password-form">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-secondary toggle-password" type="button">
                                    <i class="ph ph-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback d-block" id="current_password_error"></div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Mật khẩu mới</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" placeholder="Tối thiểu 8 ký tự">
                                <button class="btn btn-secondary toggle-password" type="button">
                                    <i class="ph ph-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                <button class="btn btn-secondary toggle-password" type="button">
                                    <i class="ph ph-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback d-block" id="confirm_password_error"></div>
                        </div>

                        <div class="d-flex justify-content-center gap-4">
                            <button type="submit" class="d-flex align-items-center btn btn-dark">
                                <i class="ph ph-check me-2"></i>Cập Nhật
                            </button>
                            <a href="javascript:history.back();" class="d-flex align-items-center btn btn-danger">
                                <i class="ph ph-x me-2"></i>Hủy
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    /**
     * Change password form handler
     * Files using this: change_password.php
     */
    $('#change-password-form').on('submit', function(e) {
        e.preventDefault();
        
        var currentPassword = $('#current_password').val();
        var newPassword = $('#new_password').val();
        var confirmPassword = $('#confirm_password').val();
        
        // Clear previous error messages
        $('#current_password_error').text('');
        $('#confirm_password_error').text('');
        
        // Validation
        var hasError = false;
        
        if (newPassword.length < 8) {
            $('#new_password').addClass('is-invalid');
            hasError = true;
        } else {
            $('#new_password').removeClass('is-invalid');
        }
        
        if (newPassword !== confirmPassword) {
            $('#confirm_password').addClass('is-invalid');
            $('#confirm_password_error').text('Mật khẩu xác nhận không khớp');
            hasError = true;
        } else {
            $('#confirm_password').removeClass('is-invalid');
        }
        
        if (hasError) {
            return;
        }
        
        // Disable submit button and show spinner
        var $submitBtn = $(this).find('button[type="submit"]');
        var originalIcon = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="ph ph-spinner ph-spin me-2"></i>Đang cập nhật...');
        
        $.ajax({
            url: AJAX.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'change_user_password',
                current_password: currentPassword,
                new_password: newPassword,
                security: $('input[name="nonce"]').val() || ''
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert('Mật khẩu đã được cập nhật thành công. Vui lòng đăng nhập lại.');
                    // Redirect to login
                    window.location.href = '<?php echo wp_logout_url(home_url('/login/')); ?>';
                } else {
                    // Show error message
                    alert(response.data.message || 'Đã có lỗi xảy ra. Vui lòng thử lại.');
                    $('#current_password').addClass('is-invalid');
                    $('#current_password_error').text(response.data.message || '');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Đã có lỗi xảy ra. Vui lòng thử lại.');
            },
            complete: function() {
                // Re-enable submit button
                $submitBtn.prop('disabled', false).html(originalIcon);
            }
        });
    });
});
</script>

<?php
get_footer();
?>
