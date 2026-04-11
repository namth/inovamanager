<?php
/*
    Template Name: Add New Expense
*/

global $wpdb;
$table = $wpdb->prefix . 'im_recurring_expenses';

// Admin-only check
if (!is_inova_admin()) {
    wp_redirect(home_url('/'));
    exit;
}

// Get redirect URL from HTTP_REFERER
$redirect_url = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer = esc_url($_SERVER['HTTP_REFERER']);
    if (strpos($referer, home_url()) === 0) {
        $redirect_url = $referer;
    }
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

/*
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_expense_field']) && wp_verify_nonce($_POST['post_expense_field'], 'post_expense')) {
        $name          = sanitize_text_field($_POST['name']);
        $category      = sanitize_text_field($_POST['category']);
        $vendor        = sanitize_text_field($_POST['vendor']);
        $amount        = intval($_POST['amount']);
        $billing_cycle = sanitize_text_field($_POST['billing_cycle'] ?? 'MONTHLY');
        $status        = sanitize_text_field($_POST['status']);
        $note          = sanitize_textarea_field($_POST['note']);

        // End date handling (optional)
        $end_date = null;
        if (isset($_POST['end_date']) && !empty($_POST['end_date'])) {
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['end_date'])));
        }

        $valid_cycles = array('MONTHLY', 'YEARLY', 'QUARTERLY', 'OTHER');
        if (!in_array($billing_cycle, $valid_cycles)) {
            $billing_cycle = 'MONTHLY';
        }

        // Validation
        if (empty($name)) {
            $notification = '<div class="alert alert-warning" role="alert">Vui lòng nhập tên chi tiêu</div>';
        } elseif ($amount <= 0) {
            $notification = '<div class="alert alert-warning" role="alert">Số tiền phải lớn hơn 0</div>';
        } else {
            $data = array(
                'name'          => $name,
                'category'      => $category,
                'vendor'        => $vendor,
                'amount'        => $amount,
                'billing_cycle' => $billing_cycle,
                'end_date'      => $end_date,
                'status'        => $status,
                'note'          => $note
            );

            $insert = $wpdb->insert(
                $table,
                $data,
                array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );

            if ($insert) {
                $notification = '<div class="alert alert-success" role="alert">Thêm chi tiêu mới thành công!</div>';

                $redirect_url = isset($_POST['redirect_url']) && !empty($_POST['redirect_url'])
                    ? esc_url($_POST['redirect_url'])
                    : home_url('/danh-sach-chi-tieu/');
                wp_redirect($redirect_url);
                exit;
            } else {
                $notification = '<div class="alert alert-danger" role="alert">Đã xảy ra lỗi khi thêm chi tiêu. Vui lòng thử lại sau.</div>';
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
                <a href="<?php echo home_url('/danh-sach-chi-tieu/'); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Thêm mới Chi tiêu</h3>
                </div>
            </div>
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php
                    if (isset($notification)) {
                        echo $notification;
                        echo '<div class="d-flex mt-3">';
                        echo '<a href="' . home_url('/danh-sach-chi-tieu/') . '" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-list btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Xem danh sách chi tiêu</span>
                              </a>';
                        echo '</div>';
                    } else {
                    ?>
                    <form class="forms-sample col-md-8 col-lg-10 d-flex flex-column" action="" method="post">
                        <!-- Store previous page URL -->
                        <input type="hidden" name="redirect_url" id="redirect_url" value="<?php echo $redirect_url; ?>">
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
                                            <input type="text" class="form-control" id="name" name="name" placeholder="Ví dụ: Tiền điện tháng 1" required>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="category" class="fw-bold">Danh mục <span class="text-danger">*</span></label>
                                            <select class="form-control" id="category" name="category" required>
                                                <option value="">-- Chọn danh mục --</option>
                                                <?php foreach ($categories as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="vendor" class="fw-bold">Nhà cung cấp</label>
                                            <input type="text" class="form-control" id="vendor" name="vendor" placeholder="Ví dụ: Công ty điện lực EVN">
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="amount" class="fw-bold">Số tiền <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="amount" name="amount" placeholder="0" min="0" required>
                                                <span class="input-group-text bg-secondary text-white">VNĐ</span>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="billing_cycle" class="fw-bold">Chu kỳ <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="billing_cycle" name="billing_cycle" required>
                                                        <?php foreach ($billing_cycles as $key => $label): ?>
                                                            <option value="<?php echo $key; ?>" <?php echo ($key === 'MONTHLY') ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="end_date" class="fw-bold">Ngày kết thúc</label>
                                                    <div class="input-group date datepicker">
                                                        <input type="text" class="form-control" id="end_date" name="end_date" placeholder="dd/mm/yyyy (để trống = vô hạn)">
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
                                                <option value="ACTIVE" selected>Hoạt động</option>
                                                <option value="INACTIVE">Không hoạt động</option>
                                                <option value="SUSPENDED">Tạm ngưng</option>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="note" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control" id="note" name="note" rows="5" placeholder="Ghi chú thêm về chi tiêu này..."></textarea>
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
                                <span class="fw-bold">Lưu chi tiêu</span>
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
