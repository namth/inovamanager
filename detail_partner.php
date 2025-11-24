<?php
/* 
Template Name: Detail Partner
*/
global $wpdb;
$users_table = $wpdb->prefix . 'im_users';
$contacts_table = $wpdb->prefix . 'im_contacts';

// get action from url if exists
$action = isset($_GET['action']) ? $_GET['action'] : '';

// if action is delete, then delete user by user id
if ($action == 'delete') {
    $user_id = $_GET['id'];
    $wpdb->delete($users_table, array('id' => $user_id));
    wp_redirect(home_url('/danh-sach-doi-tac/'));
    exit;
}

// if action is delete contact, then delete contact by contact id
if ($action == 'delete_contact') {
    $contact_id = $_GET['contact_id'];
    $user_id = $_GET['id'];
    $wpdb->delete($contacts_table, array('id' => $contact_id));
    wp_redirect(home_url('/partner?id=' . $user_id));
    exit;
}

// get user id from url, or use current user's inova_id as fallback
$current_user_id = get_current_user_id();
$user_id = isset($_GET['id']) ? intval($_GET['id']) : get_user_inova_id($current_user_id);

// if have user id, then get user data to show in form
// if not, go to list user page
if ($user_id) {
    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $user_id));
    if (!$user) {
        wp_redirect(home_url('/danh-sach-doi-tac/'));
        exit;
    }
} else {
    wp_redirect(home_url('/danh-sach-doi-tac/'));
    exit;
}

// Check if current user is administrator
$is_admin = is_inova_admin();

get_header();

?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12" id="relative">
            <div class="statistics-details d-flex flex-column gap-3 justify-content-center text-center align-items-center mt-5">
                <!-- add back button in the left side -->
                <?php if ($is_admin): ?>
                <a href="<?php echo home_url('/danh-sach-doi-tac'); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <?php endif; ?>
                <?php
                // each user_type will have different icon and color
                switch ($user->user_type) {
                    case 'INDIVIDUAL':
                        $icon = 'ph-user';
                        $color = 'btn-inverse-success';
                        $type_label = 'Khách hàng cá nhân';
                        break;
                    case 'BUSINESS':
                        $icon = 'ph-buildings';
                        $color = 'btn-inverse-info';
                        $type_label = 'Khách hàng doanh nghiệp';
                        break;
                    case 'SUPPLIER':
                        $icon = 'ph-buildings';
                        $color = 'btn-inverse-info';
                        $type_label = 'Nhà cung cấp dịch vụ';
                        break;
                    case 'PARTNER':
                        $icon = 'ph-users-three';
                        $color = 'btn-inverse-warning';
                        $type_label = 'Đối tác';
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
                <div class="card card-rounded p-3 max-w600">
                    <div class="d-flex justify-content-center flex-column text-center nav-link">
                        <i class="d-flex fit-content badge border-radius-9 <?php echo $color; ?>"><?php echo $type_label; ?></i>
                        <i class="ph <?php echo $icon; ?> icon-lg p-4"></i>
                        <span class="fw-bold p-2"><?php echo $user->name; ?></span>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <?php 
                                    // if have user_code, then show it in table row with label 'Mã người dùng'
                                    if (!empty($user->user_code)) {
                                        echo '<tr>
                                                <td class="fw-bold p-2">Mã người dùng</td>
                                                <td>' . $user->user_code . '</td>
                                            </tr>';
                                    }

                                    // if have email, phone_number, tax_code, address, notes then show it in table row
                                    if (!empty($user->email)) {
                                        echo '<tr>
                                                <td class="fw-bold p-2">Email</td>
                                                <td>' . $user->email . '</td>
                                            </tr>';
                                    }
                                    if (!empty($user->phone_number)) {
                                        echo '<tr>
                                                <td class="fw-bold p-2">Số điện thoại</td>
                                                <td>' . $user->phone_number . '</td>
                                            </tr>';
                                    }
                                    if (!empty($user->tax_code)) {
                                        echo '<tr>
                                                <td class="fw-bold p-2">Mã số thuế</td>
                                                <td>' . $user->tax_code . '</td>
                                            </tr>';
                                    }
                                    if (!empty($user->address)) {
                                        echo '<tr>
                                                <td class="fw-bold p-2">Địa chỉ</td>
                                                <td>' . $user->address . '</td>
                                            </tr>';
                                    }
                                    if (!empty($user->notes) && $is_admin) {
                                        echo '<tr>
                                                <td class="fw-bold p-2">Ghi chú</td>
                                                <td>' . $user->notes . '</td>
                                            </tr>';
                                    }
                                    if (!empty($user->created_at)) {
                                        echo '<tr>
                                                <td class="fw-bold p-2">Ngày tạo</td>
                                                <td>' . date('d/m/Y H:i', strtotime($user->created_at)) . '</td>
                                            </tr>';
                                    }
                                    if (!empty($user->status)) {
                                        $status_class = ($user->status == 'ACTIVE') ? 'text-success' : 'text-danger';
                                        $status_text = ($user->status == 'ACTIVE') ? 'Hoạt động' : 'Không hoạt động';
                                        echo '<tr>
                                                <td class="fw-bold p-2">Trạng thái</td>
                                                <td class="' . $status_class . '">' . $status_text . '</td>
                                            </tr>';
                                    }
                                ?>
                            </table>
                        </div>

                    </div>
                </div>
                <div class="d-flex flex-row justify-content-center gap-3">
                    <a href="<?php echo home_url('/sua-doi-tac/') . '?id=' . $user->id; ?>" class="btn btn-dark btn-icon-text d-flex align-items-center">
                        <i class="ph ph-pencil-simple-line btn-icon-prepend"></i>
                        Sửa thông tin
                    </a>
                    <a href="?action=delete&id=<?php echo $user->id; ?>" class="btn btn-danger btn-icon-text d-flex align-items-center" 
                       onclick="return confirm('Bạn có chắc chắn muốn xóa người dùng này?');">
                        <i class="ph ph-trash btn-icon-prepend"></i>
                        Xóa
                    </a>
                    
                    <a href="<?php echo home_url('/them-lien-he-moi/') . '?user_id=' . $user->id; ?>" class="btn btn-primary btn-icon-text d-flex align-items-center">
                        <i class="ph ph-user-plus btn-icon-prepend"></i>
                        Thêm liên hệ
                    </a>
                </div>
                
                <?php
                // If this user has a partner_id, show their partner info
                if (!empty($user->partner_id)) {
                    $partner = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $user->partner_id));
                    if ($partner) {
                        // Determine partner icon and color
                        switch ($partner->user_type) {
                            case 'PARTNER':
                                $icon = 'ph-users-three';
                                $color = 'btn-inverse-warning';
                                $type_label = 'Đối tác';
                                break;
                            default:
                                $icon = 'ph-person-simple-run';
                                $color = 'btn-inverse-dark';
                                $type_label = 'Chưa xác định';
                                break;
                        }
                        
                        echo '<h4 class="card-title mt-3 p-2">Đối tác quản lý</h4>';
                        echo '<div class="statistics-details d-flex flex-row gap-3 flex-wrap justify-content-center">';
                        ?>
                            <div class="card card-rounded p-3 w280">
                                <a href="<?php echo home_url('/danh-sach-doi-tac?id=' . $partner->id) ?>" class="d-flex justify-content-center flex-column text-center nav-link">
                                    <i class="d-flex fit-content badge border-radius-9 <?php echo $color; ?>"><?php echo $type_label; ?></i>
                                    <i class="ph <?php echo $icon; ?> icon-lg p-4"></i>
                                    <span class="fw-bold p-2"><?php echo $partner->name; ?></span>
                                    <span><?php echo $partner->user_code; ?></span>
                                    <span><?php echo $partner->email; ?></span>
                                    <span><?php echo $partner->phone_number; ?></span>
                                </a>
                            </div>
                        <?php
                        echo '</div>';
                    }
                }
                
                // Show their contacts
                $contacts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $contacts_table WHERE user_id = %d ORDER BY is_primary DESC, full_name", $user->id));
                
                if (!empty($contacts)) {
                    echo '<h4 class="card-title mt-3 p-2">Thông tin liên hệ</h4>';
                    echo '<div class="statistics-details d-flex flex-row gap-3 flex-wrap justify-content-center">';
                    
                    foreach ($contacts as $contact) {
                        ?>
                        <div class="card card-rounded p-3 w280">
                            <div class="d-flex justify-content-center flex-column text-center">
                                <?php if ($contact->is_primary): ?>
                                <i class="d-flex fit-content badge border-radius-9 btn-inverse-success">Liên hệ chính</i>
                                <?php endif; ?>
                                <i class="ph ph-user-circle icon-lg p-4"></i>
                                <span class="fw-bold p-2"><?php echo $contact->full_name; ?></span>
                                <?php if (!empty($contact->position)): ?>
                                <span class="text-primary"><?php echo $contact->position; ?></span>
                                <?php endif; ?>
                                <?php if (!empty($contact->email)): ?>
                                <span><?php echo $contact->email; ?></span>
                                <?php endif; ?>
                                <?php if (!empty($contact->phone_number)): ?>
                                <span><?php echo $contact->phone_number; ?></span>
                                <?php endif; ?>
                                
                                <div class="mt-3 d-flex justify-content-center gap-2">
                                    <a href="<?php echo home_url('/sua-lien-he/?id=' . $contact->id); ?>" class="btn btn-sm btn-dark btn-icon-text d-flex align-items-center">
                                        <i class="ph ph-pencil-simple btn-icon-prepend"></i>
                                        Sửa
                                    </a>
                                    <a href="?action=delete_contact&contact_id=<?php echo $contact->id; ?>&id=<?php echo $user->id; ?>" 
                                        class="btn btn-sm btn-danger btn-icon-text d-flex align-items-center"
                                        onclick="return confirm('Bạn có chắc chắn muốn xóa liên hệ này?');">
                                        <i class="ph ph-trash btn-icon-prepend"></i>
                                        Xóa
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    
                    echo '</div>';
                }
                
                // If this user is a PARTNER, show all users they manage
                if ($user->user_type == 'PARTNER') {
                    $managed_users = $wpdb->get_results($wpdb->prepare("SELECT * FROM $users_table WHERE partner_id = %d", $user->id));
                    
                    if (!empty($managed_users)) {
                        echo '<h4 class="card-title mt-3 p-2">Khách hàng được quản lý</h4>';
                        echo '<div class="statistics-details d-flex flex-row gap-3 flex-wrap justify-content-center">';
                        
                        foreach ($managed_users as $managed) {
                            // Determine icon and color based on user type
                            switch ($managed->user_type) {
                                case 'INDIVIDUAL':
                                    $icon = 'ph-user';
                                    $color = 'btn-inverse-success';
                                    $type_label = 'Cá nhân';
                                    break;
                                case 'BUSINESS':
                                    $icon = 'ph-buildings';
                                    $color = 'btn-inverse-info';
                                    $type_label = 'Doanh nghiệp';
                                    break;
                                default:
                                    $icon = 'ph-person-simple-run';
                                    $color = 'btn-inverse-dark';
                                    $type_label = 'Chưa xác định';
                                    break;
                            }
                            ?>
                            <div class="card card-rounded p-3 w280">
                                <a href="<?php echo home_url('/danh-sach-doi-tac?id=' . $managed->id) ?>" class="d-flex justify-content-center flex-column text-center nav-link">
                                    <i class="d-flex fit-content badge border-radius-9 <?php echo $color; ?>"><?php echo $type_label; ?></i>
                                    <i class="ph <?php echo $icon; ?> icon-lg p-4"></i>
                                    <span class="fw-bold p-2"><?php echo $managed->name; ?></span>
                                    <span><?php echo $managed->user_code; ?></span>
                                    <span><?php echo $managed->email; ?></span>
                                    <span><?php echo $managed->phone_number; ?></span>
                                </a>
                            </div>
                            <?php
                        }
                        
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Add a button fixed on bottom right corner, with plus icon, border dash, link to addnew_user page -->
    <?php if ($is_admin): ?>
    <a href="<?php echo home_url('/them-doi-tac-moi'); ?>" class="fixed-bottom-right nav-link">
        <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
    </a>
    <?php endif; ?>
</div>

<?php
get_footer();

