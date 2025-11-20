<?php
/*
    Template Name: User Mapping Admin
    Description: Map WordPress users with Inova users (im_users table)
*/

// Only administrators can access this page
if (!current_user_can('administrator')) {
    wp_redirect(home_url());
    exit;
}

global $wpdb;
$users_table = $wpdb->prefix . 'im_users';

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (wp_verify_nonce($_POST['user_mapping_nonce'], 'user_mapping_action')) {
        if ($_POST['action'] === 'update_mapping') {
            $wp_user_id = intval($_POST['wp_user_id']);
            $inova_id = intval($_POST['inova_id']);

            if ($wp_user_id > 0) {
                if ($inova_id > 0) {
                    // Update user meta
                    update_user_meta($wp_user_id, 'inova_id', $inova_id);
                    $message = 'Đã cập nhật mapping cho user thành công!';
                    $message_type = 'success';
                } else {
                    // Remove mapping
                    delete_user_meta($wp_user_id, 'inova_id');
                    $message = 'Đã xóa mapping cho user!';
                    $message_type = 'success';
                }
            }
        }
    } else {
        $message = 'Lỗi bảo mật!';
        $message_type = 'error';
    }
}

// Get all WordPress users (excluding admin for now)
$wp_users = get_users(array(
    'orderby' => 'display_name',
    'order' => 'ASC'
));

// Get all Inova users
$inova_users = $wpdb->get_results("
    SELECT id, user_code, name, user_type, email
    FROM $users_table
    WHERE status = 'ACTIVE'
    ORDER BY name ASC
");

get_header();
?>

<div class="main-panel">
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">
                                <i class="ph ph-link me-2"></i>
                                Quản lý User Mapping
                            </h4>
                        </div>

                        <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : $message_type; ?> alert-dismissible fade show" role="alert">
                            <i class="ph ph-<?php echo $message_type === 'success' ? 'check-circle' : 'warning'; ?> me-2"></i>
                            <?php echo esc_html($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <i class="ph ph-info me-2"></i>
                            <strong>Hướng dẫn:</strong> Map mỗi WordPress user với một Inova user (Customer/Partner) từ bảng im_users để phân quyền truy cập.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="25%">WordPress User</th>
                                        <th width="20%">Email</th>
                                        <th width="20%">Role</th>
                                        <th width="25%">Inova User Mapping</th>
                                        <th width="10%">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wp_users as $wp_user):
                                        $current_inova_id = get_user_meta($wp_user->ID, 'inova_id', true);
                                        $user_roles = implode(', ', $wp_user->roles);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($wp_user->display_name); ?></strong>
                                            <br>
                                            <small class="text-muted">ID: <?php echo $wp_user->ID; ?></small>
                                        </td>
                                        <td><?php echo esc_html($wp_user->user_email); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $wp_user->has_cap('administrator') ? 'danger' : 'primary'; ?>">
                                                <?php echo esc_html($user_roles); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline-block w-100">
                                                <?php wp_nonce_field('user_mapping_action', 'user_mapping_nonce'); ?>
                                                <input type="hidden" name="action" value="update_mapping">
                                                <input type="hidden" name="wp_user_id" value="<?php echo $wp_user->ID; ?>">

                                                <select name="inova_id" class="form-select form-select-sm">
                                                    <option value="0">-- Chọn Inova User --</option>
                                                    <?php foreach ($inova_users as $inova_user): ?>
                                                        <option value="<?php echo $inova_user->id; ?>"
                                                                <?php selected($current_inova_id, $inova_user->id); ?>>
                                                            [<?php echo esc_html($inova_user->user_code); ?>]
                                                            <?php echo esc_html($inova_user->name); ?>
                                                            (<?php echo esc_html($inova_user->user_type); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <?php if ($current_inova_id):
                                                    $mapped_user = $wpdb->get_row($wpdb->prepare(
                                                        "SELECT * FROM $users_table WHERE id = %d",
                                                        $current_inova_id
                                                    ));
                                                    if ($mapped_user):
                                                ?>
                                                <small class="text-success d-block mt-1">
                                                    <i class="ph ph-check-circle"></i>
                                                    Đã map với: <?php echo esc_html($mapped_user->name); ?> (<?php echo esc_html($mapped_user->user_type); ?>)
                                                </small>
                                                <?php
                                                    endif;
                                                endif;
                                                ?>
                                            </form>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary btn-update-mapping"
                                                    data-user-id="<?php echo $wp_user->ID; ?>">
                                                <i class="ph ph-check"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle update mapping button click
    $('.btn-update-mapping').on('click', function() {
        var $row = $(this).closest('tr');
        var $form = $row.find('form');

        // Submit the form
        $form.submit();
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert-dismissible').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
});
</script>

<?php get_footer(); ?>
