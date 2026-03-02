<?php
/*
    Template Name: Expense List
*/

global $wpdb;
$table = $wpdb->prefix . 'im_recurring_expenses';

// Admin-only check
if (!is_inova_admin()) {
    wp_redirect(home_url('/'));
    exit;
}

// Handle delete request
$message = '';
$message_type = '';

if (isset($_POST['delete_expense_id']) && !empty($_POST['delete_expense_id'])) {
    $expense_id = intval($_POST['delete_expense_id']);

    // Check if expense exists
    $expense = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $expense_id));

    if ($expense) {
        $deleted = $wpdb->delete(
            $table,
            array('id' => $expense_id),
            array('%d')
        );

        if ($deleted) {
            $message = "Chi tiêu '{$expense->name}' đã được xóa thành công!";
            $message_type = 'success';
        } else {
            $message = "Có lỗi xảy ra khi xóa chi tiêu.";
            $message_type = 'danger';
        }
    } else {
        $message = "Chi tiêu không tồn tại.";
        $message_type = 'danger';
    }
}

// Get search and filter parameters
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Build WHERE clause
$where_conditions = array();

if (!empty($search_query)) {
    $search_like = '%' . $wpdb->esc_like($search_query) . '%';
    $where_conditions[] = $wpdb->prepare("(name LIKE %s OR category LIKE %s OR vendor LIKE %s)", $search_like, $search_like, $search_like);
}

if (!empty($status_filter)) {
    $where_conditions[] = $wpdb->prepare("status = %s", $status_filter);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT *
    FROM $table
    {$where_clause}
    ORDER BY start_date DESC
";

// Pagination settings
$items_per_page = 10;
$current_page = max(1, intval(get_query_var('paged')));

// Get total count
$count_query = "SELECT COUNT(*) FROM $table {$where_clause}";
$total_items = $wpdb->get_var($count_query);
$total_pages = ceil($total_items / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// Add LIMIT to query
$query .= " LIMIT $items_per_page OFFSET $offset";

$expenses = $wpdb->get_results($query);

// Category mapping
$categories = array(
    'ELECTRICITY' => 'Tiền điện',
    'WATER' => 'Tiền nước',
    'SOFTWARE' => 'Phần mềm / Dịch vụ',
    'CONTRACT' => 'Hợp đồng dịch vụ',
    'RENTAL' => 'Tiền thuê',
    'INSURANCE' => 'Bảo hiểm',
    'MAINTENANCE' => 'Bảo trì / Sửa chữa',
    'SUBSCRIPTION' => 'Đăng ký hàng tháng',
    'OTHER' => 'Khác'
);

// Status color mapping
$status_colors = array(
    'ACTIVE' => 'success',
    'INACTIVE' => 'warning',
    'EXPIRED' => 'danger',
    'SUSPENDED' => 'secondary'
);

$status_labels = array(
    'ACTIVE' => 'Hoạt động',
    'INACTIVE' => 'Không hoạt động',
    'EXPIRED' => 'Hết hạn',
    'SUSPENDED' => 'Tạm ngưng'
);

// Calculate monthly total
$monthly_total = 0;
if (!empty($expenses)) {
    foreach ($expenses as $expense) {
        if ($expense->status === 'ACTIVE' && strtotime($expense->start_date) <= time() && (empty($expense->end_date) || strtotime($expense->end_date) >= time())) {
            $monthly_total += $expense->amount;
        }
    }
}

get_header();
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php if ($message_type === 'success'): ?>
                            <i class="ph ph-check-circle me-2"></i>
                        <?php elseif ($message_type === 'warning'): ?>
                            <i class="ph ph-warning me-2"></i>
                        <?php else: ?>
                            <i class="ph ph-x-circle me-2"></i>
                        <?php endif; ?>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="card-title">Danh sách Chi tiêu Theo Chu kỳ</h4>
                            <?php if (!empty($expenses) && $monthly_total > 0): ?>
                            <p class="text-muted mb-0">
                                Tổng chi tiêu hiện tại: <strong class="text-primary">
                                    <?php echo number_format($monthly_total, 0, '.', ','); ?> VNĐ
                                </strong>
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <!-- Search functionality -->
                            <div class="d-flex align-items-center me-3">
                                <i class="ph ph-magnifying-glass text-muted me-2 fa-150p"></i>
                                <form method="GET" class="d-flex">
                                    <input type="text" name="search" class="form-control form-control-sm"
                                           placeholder="Tìm kiếm tên, danh mục..."
                                           value="<?php echo esc_attr($search_query); ?>" style="width: 300px;">
                                    <?php if (!empty($search_query)): ?>
                                    <a href="<?php echo home_url('/danh-sach-chi-tieu/'); ?>" class="btn btn-sm btn-danger ms-1 d-flex align-items-center" title="Xóa bộ lọc">
                                        <i class="ph ph-x"></i>
                                    </a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <!-- Add new expense button -->
                            <a href="<?php echo home_url('/them-chi-tieu/'); ?>" class="fixed-bottom-right nav-link" title="Thêm mới chi tiêu" data-bs-toggle="tooltip" data-bs-placement="left">
                                <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
                            </a>
                        </div>
                    </div>

                    <?php if (empty($expenses)): ?>
                    <div class="text-center py-5">
                        <?php if (!empty($search_query)): ?>
                            <i class="ph ph-magnifying-glass icon-lg text-muted mb-3" style="font-size: 48px;"></i>
                            <h4>Không tìm thấy chi tiêu nào</h4>
                            <p class="text-muted">
                                Không có kết quả cho từ khóa "<strong><?php echo esc_html($search_query); ?></strong>"
                            </p>
                            <a href="<?php echo home_url('/danh-sach-chi-tieu/'); ?>" class="btn btn-secondary me-2 d-flex align-items-center">
                                <i class="ph ph-arrow-clockwise me-2"></i>Xóa bộ lọc
                            </a>
                        <?php else: ?>
                            <i class="ph ph-receipt icon-lg text-muted mb-3" style="font-size: 48px;"></i>
                            <h4>Chưa có chi tiêu nào</h4>
                            <p class="text-muted">Bắt đầu bằng cách thêm chi tiêu đầu tiên</p>
                            <a href="<?php echo home_url('/them-chi-tieu/'); ?>" class="btn btn-primary">
                                <i class="ph ph-plus-circle me-2"></i> Thêm mới Chi tiêu
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <th>STT</th>
                                    <th>Tên chi tiêu</th>
                                    <th>Danh mục</th>
                                    <th>Nhà cung cấp</th>
                                    <th>Số tiền</th>
                                    <th>Ngày bắt đầu</th>
                                    <th>Ngày kết thúc</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expenses as $index => $expense): ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted"><?php echo $index + 1 + ($offset); ?></td>
                                    <td>
                                        <strong><?php echo esc_html($expense->name); ?></strong>
                                        <?php if (!empty($expense->note)): ?>
                                        <br><small class="text-muted"><?php echo esc_html(mb_substr($expense->note, 0, 50)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge border-radius-9 bg-light-primary text-primary">
                                            <?php echo esc_html($categories[$expense->category] ?? $expense->category); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($expense->vendor ?? '-'); ?></td>
                                    <td>
                                        <strong><?php echo number_format($expense->amount); ?> VNĐ</strong>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($expense->start_date)); ?></td>
                                    <td>
                                        <?php 
                                            if (empty($expense->end_date)) {
                                                echo '<span class="text-success">Vô hạn</span>';
                                            } else {
                                                echo date('d/m/Y', strtotime($expense->end_date));
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge border-radius-9 bg-<?php echo $status_colors[$expense->status] ?? 'secondary'; ?>">
                                            <?php echo $status_labels[$expense->status] ?? $expense->status; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo home_url('/sua-chi-tieu/?expense_id=' . $expense->id); ?>" class="btn btn-sm btn-info d-inline-flex align-items-center" title="Sửa">
                                            <i class="ph ph-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center delete-expense-btn" onclick="confirmDeleteExpense(<?php echo $expense->id; ?>, '<?php echo esc_attr($expense->name); ?>')" title="Xóa">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="mt-4 d-flex justify-content-center">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?paged=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                        <i class="ph ph-caret-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php for ($page = max(1, $current_page - 2); $page <= min($total_pages, $current_page + 2); $page++): ?>
                                <li class="page-item <?php echo ($page === $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?paged=<?php echo $page; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                        <?php echo $page; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?paged=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                        <i class="ph ph-caret-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for deleting expense -->
<form id="deleteExpenseForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_expense_id" id="delete_expense_id" value="">
</form>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-labelledby="deleteExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteExpenseModalLabel">
                    <i class="ph ph-warning-diamond me-2"></i>
                    Xác nhận xóa chi tiêu
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="ph ph-warning me-2"></i>
                    <strong>Cảnh báo:</strong> Hành động này không thể hoàn tác!
                </div>

                <p>Bạn có chắc chắn muốn xóa chi tiêu <strong id="expenseNameToDelete"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ph ph-x me-2"></i>Hủy bỏ
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="ph ph-trash me-2"></i>Xác nhận xóa
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDeleteExpense(expenseId, expenseName) {
    document.getElementById('delete_expense_id').value = expenseId;
    document.getElementById('expenseNameToDelete').textContent = expenseName;

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteExpenseModal'));
    deleteModal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    document.getElementById('deleteExpenseForm').submit();
});
</script>

<?php
get_footer();
?>
