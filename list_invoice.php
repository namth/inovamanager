<?php
/* 
    Template Name: Invoice List
*/

global $wpdb;
$invoice_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';
$users_table = $wpdb->prefix . 'im_users';

// Process invoice deletion if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_invoice') {
    // Verify nonce for security (optional, can be added later)
    
    // Get invoice ID
    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    
    if ($invoice_id > 0) {
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // First delete related invoice items
            $wpdb->delete($invoice_items_table, array('invoice_id' => $invoice_id));
            
            // Then delete the invoice
            $result = $wpdb->delete($invoice_table, array('id' => $invoice_id));
            
            if ($result !== false) {
                $wpdb->query('COMMIT');
                
                // Show success message
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ph ph-check-circle me-2"></i> Hóa đơn đã được xóa thành công.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            } else {
                throw new Exception("Error deleting invoice");
            }
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            
            // Show error message
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="ph ph-warning me-2"></i> Đã xảy ra lỗi khi xóa hóa đơn. Vui lòng thử lại.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
    }
}

// Initialize variables
$current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$per_page = 20; // Records per page
$offset = ($current_page - 1) * $per_page;

// Search filters
$search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Build permission filtering
$permission_where = get_user_permission_where_clause('u', 'id');

// Build WHERE clause separately for reuse in count query
$where_clause = "WHERE 1=1 {$permission_where}";

// Add search conditions
if (!empty($search_term)) {
    $where_clause .= $wpdb->prepare(" AND (i.invoice_code LIKE %s OR u.name LIKE %s OR u.user_code LIKE %s)", 
        '%' . $wpdb->esc_like($search_term) . '%',
        '%' . $wpdb->esc_like($search_term) . '%',
        '%' . $wpdb->esc_like($search_term) . '%'
    );
}

if (!empty($status_filter)) {
    $where_clause .= $wpdb->prepare(" AND i.status = %s", $status_filter);
}

if (!empty($date_from)) {
    // Convert from DD/MM/YYYY to YYYY-MM-DD for database query
    $date_parts = explode('/', $date_from);
    if (count($date_parts) === 3) {
        $db_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
        $where_clause .= $wpdb->prepare(" AND i.invoice_date >= %s", $db_date);
    }
}

if (!empty($date_to)) {
    // Convert from DD/MM/YYYY to YYYY-MM-DD for database query
    $date_parts = explode('/', $date_to);
    if (count($date_parts) === 3) {
        $db_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
        $where_clause .= $wpdb->prepare(" AND i.invoice_date <= %s", $db_date);
    }
}

// Get total records for pagination
$count_query = "
    SELECT COUNT(*) 
    FROM $invoice_table i
    LEFT JOIN $users_table u ON i.user_id = u.id
    {$where_clause}
";
$total_items = $wpdb->get_var($count_query);
$total_pages = ceil($total_items / $per_page);

// Build main query
$query = "
    SELECT
        i.*,
        u.name AS customer_name,
        u.user_code
    FROM
        $invoice_table i
    LEFT JOIN
        $users_table u ON i.user_id = u.id
    {$where_clause}
    ORDER BY i.created_at DESC 
    LIMIT $offset, $per_page
";

// Execute query
$invoices = $wpdb->get_results($query);

get_header();
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">Quản lý hóa đơn</h4>
                        <?php if (is_inova_admin()): ?>
                        <a href="<?php echo home_url('/tao-hoa-don/'); ?>" class="btn btn-primary btn-icon-text">
                            <i class="ph ph-plus btn-icon-prepend"></i>
                            <span>Tạo hóa đơn mới</span>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Search and Filters -->
                    <form method="get" action="" class="mb-4">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Tìm kiếm hóa đơn..." name="search" value="<?php echo esc_attr($search_term); ?>">
                                    <button class="btn btn-primary d-flex align-items-center" type="submit">
                                        <i class="ph ph-magnifying-glass"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <select class="form-select" name="status">
                                    <option value="">-- Trạng thái --</option>
                                    <option value="draft" <?php selected($status_filter, 'draft'); ?>>Nháp</option>
                                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>Chờ thanh toán</option>
                                    <option value="pending_completion" <?php selected($status_filter, 'pending_completion'); ?>>Chờ hoàn thành</option>
                                    <option value="paid" <?php selected($status_filter, 'paid'); ?>>Đã thanh toán</option>
                                    <option value="canceled" <?php selected($status_filter, 'canceled'); ?>>Đã hủy</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <div class="input-group datepicker">
                                    <input type="text" class="form-control" placeholder="Từ ngày" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="ph ph-calendar-blank"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <div class="input-group datepicker">
                                    <input type="text" class="form-control" placeholder="Đến ngày" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="ph ph-calendar-blank"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <div class="d-flex">
                                    <button type="submit" class="btn btn-primary me-2">Lọc</button>
                                    <a href="<?php echo home_url('/danh-sach-hoa-don/'); ?>" class="btn btn-secondary d-flex align-items-center">Đặt lại</a>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Invoice List Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mã hóa đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Ngày xuất</th>
                                    <th>Hạn thanh toán</th>
                                    <th>Tổng tiền</th>
                                    <th>Thanh toán</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">Không tìm thấy hóa đơn nào</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $invoice->id); ?>" class="text-decoration-none text-primary fw-medium">
                                                <?php echo esc_html($invoice->invoice_code); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span><?php echo esc_html($invoice->user_code . ' - ' . $invoice->customer_name); ?></span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice->invoice_date)); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice->due_date)); ?></td>
                                        <td><?php echo number_format($invoice->total_amount, 0, ',', '.'); ?> VNĐ</td>
                                        <td><?php echo number_format($invoice->paid_amount, 0, ',', '.'); ?> VNĐ</td>
                                        <td>
                                            <?php
                                            switch ($invoice->status) {
                                                case 'draft':
                                                    echo '<span class="badge bg-secondary border-radius-9">Nháp</span>';
                                                    break;
                                                case 'pending':
                                                    echo '<span class="badge btn-warning border-radius-9">Chờ thanh toán</span>';
                                                    break;
                                                case 'pending_completion':
                                                    echo '<span class="badge bg-info border-radius-9">Chờ hoàn thành</span>';
                                                    break;
                                                case 'paid':
                                                    echo '<span class="badge bg-success border-radius-9">Đã thanh toán</span>';
                                                    break;
                                                case 'canceled':
                                                    echo '<span class="badge bg-danger border-radius-9">Đã hủy</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary border-radius-9">Khác</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (is_inova_admin()): ?>
                                                    <?php if ($invoice->status == 'draft'): ?>
                                                    <a href="<?php echo home_url('/tao-hoa-don/?invoice_id=' . $invoice->id); ?>" class="nav-link text-warning me-2" title="Chỉnh sửa">
                                                        <i class="ph ph-pencil-simple fa-150p"></i>
                                                    </a>

                                                    <a href="<?php echo home_url('/tao-hoa-don/?invoice_id=' . $invoice->id . '&finalize=1'); ?>" class="nav-link text-success me-2" title="Hoàn tất hóa đơn">
                                                        <i class="ph ph-check-circle fa-150p"></i>
                                                    </a>
                                                    <?php endif; ?>

                                                    <?php if (in_array($invoice->status, ['pending', 'draft'])): ?>
                                                    <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $invoice->id . '&action=payment'); ?>" class="nav-link text-success me-2" title="Đánh dấu đã thanh toán">
                                                        <i class="ph ph-money fa-150p"></i>
                                                    </a>
                                                    <?php endif; ?>

                                                    <?php if ($invoice->status == 'pending_completion'): ?>
                                                    <span class="nav-link text-muted me-2" title="Đang chờ hoàn thành dịch vụ">
                                                        <i class="ph ph-clock fa-150p"></i>
                                                    </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <a href="<?php echo home_url('/print-invoice/?invoice_id=' . $invoice->id); ?>" class="nav-link text-info me-2" title="In hóa đơn" target="_blank">
                                                    <i class="ph ph-printer fa-150p"></i>
                                                </a>

                                                <?php if (is_inova_admin()): ?>
                                                <a href="#" class="nav-link text-danger delete-invoice" title="Xóa hóa đơn" data-invoice-id="<?php echo $invoice->id; ?>" data-invoice-code="<?php echo $invoice->invoice_code; ?>">
                                                    <i class="ph ph-trash fa-150p"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div>
                            Hiển thị <?php echo min($per_page, count($invoices)); ?> / <?php echo $total_items; ?> hóa đơn
                        </div>
                        <div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo add_query_arg('paged', $current_page - 1, $_SERVER['REQUEST_URI']); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo add_query_arg('paged', $i, $_SERVER['REQUEST_URI']); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo add_query_arg('paged', $current_page + 1, $_SERVER['REQUEST_URI']); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div class="modal fade" id="markAsPaidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="mark-as-paid-form" method="post" action="<?php echo home_url('/process-invoice/'); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Đánh dấu hóa đơn đã thanh toán</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="mark_as_paid">
                    <input type="hidden" name="invoice_id" id="paid-invoice-id">
                    
                    <p>Bạn đang đánh dấu hóa đơn <strong id="paid-invoice-code"></strong> là đã thanh toán.</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Số tiền đã thanh toán</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="paid_amount" id="paid-amount" required>
                            <span class="input-group-text">VNĐ</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ngày thanh toán</label>
                        <div class="input-group datepicker">
                            <input type="text" class="form-control" name="payment_date" value="<?php echo date('d/m/Y'); ?>" required>
                            <span class="input-group-text bg-primary text-white">
                                <i class="ph ph-calendar-blank"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ghi chú thanh toán</label>
                        <textarea class="form-control" name="payment_notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success">Xác nhận đã thanh toán</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Invoice Modal -->
<div class="modal fade" id="deleteInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="delete-invoice-form" method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Xác nhận xóa hóa đơn</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_invoice">
                    <input type="hidden" name="invoice_id" id="delete-invoice-id">
                    
                    <p>Bạn có chắc chắn muốn xóa hóa đơn <strong id="delete-invoice-code"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="ph ph-warning me-2"></i> Hành động này không thể hoàn tác.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xác nhận xóa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo get_template_directory_uri(); ?>/assets/js/custom.js"></script>

<?php get_footer(); ?>