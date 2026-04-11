<?php
/*
    Template Name: Edit Expense
*/

global $wpdb;
$table = $wpdb->prefix . 'im_recurring_expenses';

// Admin-only check
if (!is_inova_admin()) {
    wp_redirect(home_url('/'));
    exit;
}

// Get expense ID from URL
$expense_id = isset($_GET['expense_id']) ? intval($_GET['expense_id']) : 0;

// Get redirect URL from HTTP_REFERER
$redirect_url = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer = esc_url($_SERVER['HTTP_REFERER']);
    if (strpos($referer, home_url()) === 0) {
        $redirect_url = $referer;
    }
}

// Redirect if no expense ID provided
if (!$expense_id) {
    wp_redirect(home_url('/danh-sach-chi-tieu/'));
    exit;
}

// Get expense data
$expense = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $expense_id));

// Redirect if expense not found
if (!$expense) {
    wp_redirect(home_url('/danh-sach-chi-tieu/'));
    exit;
}

// Category mapping
$categories = array(
    'ELECTRICITY'  => 'Tiền điện',
    'WATER'        => 'Tiền nước',
    'SOFTWARE'     => 'Phần mềm',
    'HOSTING'      => 'VPS/Hosting',
    'DOMAIN'       => 'Tên miền',
    'AI'           => 'Chi phí AI',
    'CONTRACT'     => 'Hợp đồng dịch vụ',
    'RENTAL'       => 'Tiền thuê',
    'INSURANCE'    => 'Bảo hiểm',
    'MAINTENANCE'  => 'Bảo trì / Sửa chữa',
    'SUBSCRIPTION' => 'Đăng ký hàng tháng',
    'OTHER'        => 'Khác'
);

$billing_cycles = array(
    'MONTHLY'       => 'Hàng tháng',
    'SEMI_ANNUALLY' => '6 tháng / lần',
    'QUARTERLY'     => 'Hàng quý',
    'YEARLY'        => 'Hàng năm',
    'OTHER'         => 'Khác',
);

// Error messages mapping
$error_messages = array(
    'name'   => 'Vui lòng nhập tên chi tiêu',
    'amount' => 'Số tiền phải lớn hơn 0',
    'update' => 'Cập nhật thất bại hoặc không có thay đổi'
);

$notification = '';
$notification_type = '';

if (isset($_GET['error'])) {
    $error_code = sanitize_text_field($_GET['error']);
    $notification = '<div class="alert alert-warning" role="alert">' . ($error_messages[$error_code] ?? 'Có lỗi xảy ra') . '</div>';
    $notification_type = 'error';
}

get_header();
?>

<div class="content-wrapper mt-5">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-column">
                <!-- add back button in the left side -->
                <a href="<?php echo home_url('/danh-sach-chi-tieu/'); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Chỉnh sửa Chi tiêu</h3>
                </div>
            </div>
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php
                    if (!empty($notification) && $notification_type === 'success') {
                        echo $notification;
                        echo '<div class="d-flex mt-3">';
                        echo '<a href="' . home_url('/danh-sach-chi-tieu/') . '" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-list btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Xem danh sách chi tiêu</span>
                              </a>';
                        echo '</div>';
                    } else {
                    ?>
                    <?php if (!empty($notification)): ?>
                    <div class="col-md-8 col-lg-10 mb-3">
                        <?php echo $notification; ?>
                    </div>
                    <?php endif; ?>
                    <form class="forms-sample col-md-8 col-lg-10 d-flex flex-column" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                         <input type="hidden" name="action" value="edit_expense_post">
                         <!-- Store previous page URL -->
                         <input type="hidden" name="redirect_url" id="redirect_url" value="<?php echo $redirect_url; ?>">
                         <input type="hidden" name="expense_id" value="<?php echo $expense_id; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header btn-primary">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-receipt me-2"></i>
                                            Thông tin Chi tiêu
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="name" class="fw-bold">Tên chi tiêu <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?php echo esc_attr($expense->name); ?>" required>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="category" class="fw-bold">Danh mục <span class="text-danger">*</span></label>
                                            <select class="form-control" id="category" name="category" required>
                                                <option value="">-- Chọn danh mục --</option>
                                                <?php foreach ($categories as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo ($key === $expense->category) ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="vendor" class="fw-bold">Nhà cung cấp</label>
                                            <input type="text" class="form-control" id="vendor" name="vendor" value="<?php echo esc_attr($expense->vendor); ?>">
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="amount" class="fw-bold">Số tiền <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="amount" name="amount" value="<?php echo esc_attr($expense->amount); ?>" min="0" required>
                                                <span class="input-group-text bg-secondary text-white">VNĐ</span>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="billing_cycle" class="fw-bold">Chu kỳ <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="billing_cycle" name="billing_cycle" required>
                                                        <?php foreach ($billing_cycles as $key => $label): ?>
                                                            <option value="<?php echo $key; ?>" <?php echo (($expense->billing_cycle ?? 'MONTHLY') === $key) ? 'selected' : ''; ?>>
                                                                <?php echo $label; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="end_date" class="fw-bold">Ngày kết thúc</label>
                                                    <div class="input-group date datepicker">
                                                        <input type="text" class="form-control" id="end_date" name="end_date" value="<?php echo !empty($expense->end_date) ? date('d/m/Y', strtotime($expense->end_date)) : ''; ?>">
                                                        <span class="input-group-text bg-secondary text-white">
                                                            <i class="ph ph-calendar"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="d-flex card-header btn-secondary">
                                        <h5 class="mt-1 mb-1">
                                            <i class="ph ph-gear me-2"></i>
                                            Cấu hình khác
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="status" class="fw-bold">Trạng thái <span class="text-danger">*</span></label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="ACTIVE" <?php echo ($expense->status === 'ACTIVE') ? 'selected' : ''; ?>>Hoạt động</option>
                                                <option value="INACTIVE" <?php echo ($expense->status === 'INACTIVE') ? 'selected' : ''; ?>>Không hoạt động</option>
                                                <option value="EXPIRED" <?php echo ($expense->status === 'EXPIRED') ? 'selected' : ''; ?>>Hết hạn</option>
                                                <option value="SUSPENDED" <?php echo ($expense->status === 'SUSPENDED') ? 'selected' : ''; ?>>Tạm ngưng</option>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="note" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control" id="note" name="note" rows="5"><?php echo esc_textarea($expense->note); ?></textarea>
                                        </div>

                                        <hr class="my-3">
                                        <div class="text-muted">
                                            <small>
                                                <strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($expense->created_at)); ?><br>
                                                <strong>Ngày cập nhật:</strong> <?php echo date('d/m/Y H:i', strtotime($expense->updated_at)); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        wp_nonce_field('post_expense', 'post_expense_field');
                        ?>
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit"
                                class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Lưu thay đổi</span>
                            </button>
                            <a href="<?php echo home_url('/danh-sach-chi-tieu/'); ?>" class="btn btn-light btn-icon-text ms-2 d-flex align-items-center border-radius-9">
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

<?php
get_footer();
?>
