<?php
/*
    Template Name: Edit Expense Declaration
*/

global $wpdb;
$declarations_table = $wpdb->prefix . 'im_expense_declarations';
$recurring_table    = $wpdb->prefix . 'im_recurring_expenses';

// Admin-only check
if (!is_inova_admin()) {
    wp_redirect(home_url('/'));
    exit;
}

$declaration_id = isset($_GET['declaration_id']) ? intval($_GET['declaration_id']) : 0;

if (!$declaration_id) {
    wp_redirect(home_url('/ke-khai-chi-phi/'));
    exit;
}

$decl = $wpdb->get_row($wpdb->prepare("SELECT * FROM $declarations_table WHERE id = %d", $declaration_id));

if (!$decl) {
    wp_redirect(home_url('/ke-khai-chi-phi/'));
    exit;
}

// Error messages
$error_messages = array(
    'date'   => 'Vui lòng chọn ngày phát sinh',
    'name'   => 'Vui lòng nhập tên khoản chi',
    'amount' => 'Số tiền phải lớn hơn 0',
    'update' => 'Cập nhật thất bại hoặc không có thay đổi',
);

$notification = '';
if (isset($_GET['error'])) {
    $code = sanitize_text_field($_GET['error']);
    $notification = '<div class="alert alert-warning">' . ($error_messages[$code] ?? 'Có lỗi xảy ra') . '</div>';
}

// Redirect URL
$redirect_url = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer = esc_url($_SERVER['HTTP_REFERER']);
    if (strpos($referer, home_url()) === 0) {
        $redirect_url = $referer;
    }
}

// Get all active recurring expenses for dropdown
$recurring_expenses = $wpdb->get_results(
    "SELECT id, name, category, amount FROM $recurring_table WHERE status = 'ACTIVE' ORDER BY name ASC"
);

// Categories
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

get_header();
?>

<div class="content-wrapper mt-5">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-column">
                <a href="<?php echo home_url('/ke-khai-chi-phi/'); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Sửa Kê khai Chi phí</h3>
                </div>
            </div>

            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php if (!empty($notification)): ?>
                    <div class="col-md-8 col-lg-10 mb-3"><?php echo $notification; ?></div>
                    <?php endif; ?>

                    <form class="forms-sample col-md-8 col-lg-10 d-flex flex-column"
                          action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                        <input type="hidden" name="action" value="edit_expense_declaration_post">
                        <input type="hidden" name="declaration_id" value="<?php echo $declaration_id; ?>">
                        <input type="hidden" name="redirect_url" value="<?php echo esc_attr($redirect_url ?: home_url('/ke-khai-chi-phi/')); ?>">

                        <div class="row">
                            <!-- LEFT CARD -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header btn-primary">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-currency-circle-dollar me-2"></i>
                                            Thông tin khoản chi
                                        </h5>
                                    </div>
                                    <div class="card-body">

                                        <!-- Date -->
                                        <div class="form-group mb-3">
                                            <label for="expense_date" class="fw-bold">Ngày phát sinh <span class="text-danger">*</span></label>
                                            <div class="input-group date datepicker">
                                                <input type="text" class="form-control" id="expense_date" name="expense_date"
                                                       value="<?php echo date('d/m/Y', strtotime($decl->expense_date)); ?>"
                                                       placeholder="dd/mm/yyyy" required>
                                                <span class="input-group-text bg-secondary text-white">
                                                    <i class="ph ph-calendar"></i>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Category -->
                                        <div class="form-group mb-3">
                                            <label for="category" class="fw-bold">Danh mục <span class="text-danger">*</span></label>
                                            <select class="form-control" id="category" name="category" required>
                                                <option value="">-- Chọn danh mục --</option>
                                                <?php foreach ($categories as $k => $v): ?>
                                                <option value="<?php echo $k; ?>" <?php echo ($decl->category === $k) ? 'selected' : ''; ?>>
                                                    <?php echo $v; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Recurring suggestion -->
                                        <div class="form-group mb-3">
                                            <label for="recurring_expense_id" class="fw-bold">Chi tiêu định kỳ liên kết
                                                <small class="text-muted fw-normal">(không bắt buộc)</small>
                                            </label>
                                            <select class="form-control" id="recurring_expense_id" name="recurring_expense_id">
                                                <option value="0">-- Không liên kết --</option>
                                                <?php foreach ($recurring_expenses as $re): ?>
                                                <option value="<?php echo $re->id; ?>"
                                                        data-name="<?php echo esc_attr($re->name); ?>"
                                                        data-category="<?php echo esc_attr($re->category); ?>"
                                                        data-amount="<?php echo esc_attr($re->amount); ?>"
                                                    <?php echo ($decl->recurring_expense_id == $re->id) ? 'selected' : ''; ?>>
                                                    <?php echo esc_html($re->name); ?>
                                                    (<?php echo number_format($re->amount, 0, '.', ','); ?> VNĐ)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Name -->
                                        <div class="form-group mb-3">
                                            <label for="name" class="fw-bold">Tên khoản chi <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   value="<?php echo esc_attr($decl->name); ?>" required>
                                        </div>

                                        <!-- Amount -->
                                        <div class="form-group mb-3">
                                            <label for="amount" class="fw-bold">Số tiền <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="amount" name="amount"
                                                       value="<?php echo esc_attr($decl->amount); ?>" min="1" required>
                                                <span class="input-group-text bg-secondary text-white">VNĐ</span>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT CARD -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header btn-secondary">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-note me-2"></i>
                                            Ghi chú
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="note" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control" id="note" name="note" rows="8"><?php echo esc_textarea($decl->note); ?></textarea>
                                        </div>

                                        <hr class="my-3">
                                        <div class="text-muted">
                                            <small>
                                                <strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($decl->created_at)); ?><br>
                                                <strong>Cập nhật:</strong> <?php echo date('d/m/Y H:i', strtotime($decl->updated_at)); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php wp_nonce_field('post_declaration', 'post_declaration_field'); ?>

                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Lưu thay đổi</span>
                            </button>
                            <a href="<?php echo home_url('/ke-khai-chi-phi/'); ?>" class="btn btn-light btn-icon-text ms-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-x btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Hủy</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const categorySelect = document.getElementById('category');
const recurringSelect = document.getElementById('recurring_expense_id');
const recurringOptions = Array.from(recurringSelect.options);

function filterRecurringExpenses() {
    const selectedCategory = categorySelect.value;
    
    // Current selected option
    const currentVal = recurringSelect.value;
    let foundMatch = false;

    recurringOptions.forEach(opt => {
        if (opt.value === "0") {
            opt.hidden = false;
            return;
        }
        
        if (!selectedCategory || opt.dataset.category === selectedCategory) {
            opt.hidden = false;
            if (opt.value === currentVal) foundMatch = true;
        } else {
            opt.hidden = true;
            opt.disabled = true;
        }
        
        if (!opt.hidden) opt.disabled = false;
    });

    if (currentVal !== "0" && !foundMatch) {
        recurringSelect.value = "0";
    }
}

categorySelect.addEventListener('change', filterRecurringExpenses);

recurringSelect.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const name     = opt.dataset.name     || '';
    const amount   = opt.dataset.amount   || '';
    const category = opt.dataset.category || '';

    if (name)     document.getElementById('name').value = name;
    if (amount)   document.getElementById('amount').value = amount;
    if (category) categorySelect.value = category;
});

filterRecurringExpenses();
</script>

<?php
get_footer();
?>
