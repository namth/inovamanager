<?php
/*
    Template Name: Edit Profile
*/

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
        <div class="col-md-6 mx-auto">
            <div class="card mt-4">
                <div class="card-header bg-gradient-danger text-center py-4">
                    <img class="img-lg rounded-circle border border-white" style="width: 90px; height: 90px; object-fit: cover;"
                        src="<?php echo get_avatar_url($current_user->ID); ?>" alt="Profile image">
                </div>
                <div class="card-body">
                    <h4 class="card-title text-center mb-4">Thông Tin Cá Nhân</h4>
                    <form id="edit-profile-form">
                        <div class="mb-3">
                            <label for="display_name" class="form-label">Tên hiển thị <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="display_name" name="display_name" 
                                value="<?php echo esc_attr($current_user->display_name); ?>" required>
                            <small class="text-muted">Tên này sẽ hiển thị trên giao diện</small>
                        </div>

                        <div class="mb-3">
                            <label for="user_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="user_email" name="user_email" 
                                value="<?php echo esc_attr($current_user->user_email); ?>" required>
                            <small class="text-muted">Email sẽ được sử dụng để đăng nhập</small>
                        </div>

                        <div class="d-flex justify-content-center gap-4 mt-4">
                            <button type="submit" class="btn btn-dark">
                                <i class="ph ph-check me-2"></i>Cập Nhật
                            </button>
                            <a href="javascript:history.back();" class="btn btn-danger">
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
     * Edit profile form handler
     * Files using this: edit_profile.php
     */
    $('#edit-profile-form').on('submit', function(e) {
        e.preventDefault();
        
        var displayName = $('#display_name').val().trim();
        var userEmail = $('#user_email').val().trim();
        
        // Clear previous error messages
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');
        
        // Validation
        var hasError = false;
        
        if (!displayName) {
            $('#display_name').addClass('is-invalid');
            hasError = true;
        }
        
        if (!userEmail) {
            $('#user_email').addClass('is-invalid');
            hasError = true;
        }
        
        // Basic email validation
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(userEmail)) {
            $('#user_email').addClass('is-invalid');
            hasError = true;
        }
        
        if (hasError) {
            alert('Vui lòng điền đầy đủ và chính xác thông tin');
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
                action: 'update_user_profile',
                display_name: displayName,
                user_email: userEmail,
                user_nicename: '',
                security: $('input[name="nonce"]').val() || ''
            },
            success: function(response) {
                if (response.success) {
                    // Show success message and redirect back
                    alert('Thông tin cá nhân đã được cập nhật thành công.');
                    window.history.back();
                } else {
                    // Show error message
                    alert(response.data.message || 'Đã có lỗi xảy ra. Vui lòng thử lại.');
                    console.error('Error:', response.data);
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
