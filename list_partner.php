<?php
/* 
Template Name: List Partner
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_users';

// Check if current user is administrator
if (!is_inova_admin()) {
    wp_redirect(home_url('/partner/'));
    exit;
}

// Get filter parameter
$type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

// Get all users
$users = $wpdb->get_results("SELECT * FROM $table_name ORDER BY user_type, name");

// Apply filter if provided
if (!empty($type_filter)) {
    $users = array_filter($users, function($user) use ($type_filter) {
        return $user->user_type === $type_filter;
    });
}

get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h3 class="display-3">Danh sách người dùng</h3>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <!-- User type filter -->
                    <div class="d-flex align-items-center">
                        <i class="ph ph-funnel text-muted me-2 fa-150p"></i>
                        <select class="form-select form-select-sm w180" onchange="window.location.href=this.value">
                            <option value="<?php echo home_url('/danh-sach-doi-tac/'); ?>" <?php echo empty($type_filter) ? 'selected' : ''; ?>>
                                Tất cả loại người dùng
                            </option>
                            <option value="<?php echo home_url('/danh-sach-doi-tac/?type=PARTNER'); ?>" <?php echo $type_filter === 'PARTNER' ? 'selected' : ''; ?>>
                                Đối tác
                            </option>
                            <option value="<?php echo home_url('/danh-sach-doi-tac/?type=INDIVIDUAL'); ?>" <?php echo $type_filter === 'INDIVIDUAL' ? 'selected' : ''; ?>>
                                Khách hàng cá nhân
                            </option>
                            <option value="<?php echo home_url('/danh-sach-doi-tac/?type=BUSINESS'); ?>" <?php echo $type_filter === 'BUSINESS' ? 'selected' : ''; ?>>
                                Khách hàng doanh nghiệp
                            </option>
                            <option value="<?php echo home_url('/danh-sach-doi-tac/?type=SUPPLIER'); ?>" <?php echo $type_filter === 'SUPPLIER' ? 'selected' : ''; ?>>
                                Nhà cung cấp
                            </option>
                            <option value="<?php echo home_url('/danh-sach-doi-tac/?type=ADMIN'); ?>" <?php echo $type_filter === 'ADMIN' ? 'selected' : ''; ?>>
                                Quản trị
                            </option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="statistics-details d-flex flex-row gap-3 flex-wrap">
                <?php
                foreach ($users as $user) {
                    // Each user type will have different icon and color
                    switch ($user->user_type) {
                        case 'PARTNER':
                            $icon = 'ph-users-three';
                            $color = 'btn-inverse-warning';
                            $type_label = 'Đối tác';
                            break;
                        case 'BUSINESS':
                            $icon = 'ph-buildings';
                            $color = 'btn-inverse-info';
                            $type_label = 'Doanh nghiệp';
                            break;
                        case 'SUPPLIER':
                            $icon = 'ph-buildings';
                            $color = 'btn-primary';
                            $type_label = 'Nhà cung cấp dịch vụ';
                            break;
                        case 'INDIVIDUAL':
                            $icon = 'ph-user';
                            $color = 'btn-inverse-success';
                            $type_label = 'Cá nhân';
                            break;
                        case 'ADMIN':
                            $icon = 'ph-user-focus';
                            $color = 'btn-inverse-danger';
                            $type_label = 'Quản trị';
                            break;
                        default:
                            $icon = 'ph-person-simple-run';
                            $color = 'btn-inverse-dark';
                            $type_label = 'Chưa xác định';
                            break;
                    }
                ?>
                    <div class="card card-rounded p-3 w235 user-card" data-type="<?php echo $user->user_type; ?>">
                        <a href="<?php echo home_url('/partner?id=' . $user->id) ?>" class="d-flex justify-content-center flex-column text-center nav-link">
                            <i class="d-flex fit-content badge border-radius-9 <?php echo $color; ?>"><?php echo $type_label; ?></i>
                            <i class="ph <?php echo $icon; ?> icon-lg p-4"></i>
                            <span class="fw-bold p-2"><?php echo $user->name; ?></span>
                            <span><?php echo $user->user_code; ?></span>
                            <span><?php echo $user->email; ?></span>
                            <span><?php echo $user->phone_number; ?></span>
                            <?php if (!empty($user->company_name)): ?>
                            <span class="text-primary"><?php echo $user->company_name; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Add a button fixed on bottom right corner, with plus icon, border dash, link to addnew_partner page -->
    <a href="<?php echo home_url('/them-doi-tac-moi/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới đối tác" data-bs-toggle="tooltip" data-bs-placement="left">
        <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
    </a>
</div>

<?php
get_footer();
?>

