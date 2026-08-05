<?php
/* 
    Template Name: Website Status Logs
*/

if (!is_inova_admin() && !current_user_can('manage_options')) {
    wp_die(__('Bạn không có quyền truy cập trang này.', 'inovamanager'));
}

global $wpdb;
$logs_table = $wpdb->prefix . 'im_website_status_logs';

// Get search and status parameters
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Build WHERE clause
$where_conditions = array();

if (!empty($status_filter) && in_array(strtoupper($status_filter), array('SUCCESS', 'FAILED'))) {
    $where_conditions[] = $wpdb->prepare("status = %s", strtoupper($status_filter));
}

if (!empty($search_query)) {
    $search_like = '%' . $wpdb->esc_like($search_query) . '%';
    $where_conditions[] = $wpdb->prepare("(website_name LIKE %s OR ping_url LIKE %s OR error_message LIKE %s)", $search_like, $search_like, $search_like);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination settings
$items_per_page = 20;
$current_page = max(1, intval(get_query_var('paged') ? get_query_var('paged') : (isset($_GET['paged']) ? $_GET['paged'] : 1)));

$count_query = "SELECT COUNT(*) FROM {$logs_table} {$where_clause}";
$total_items = (int) $wpdb->get_var($count_query);
$total_pages = ceil($total_items / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// Fetch logs
$logs_query = "SELECT * FROM {$logs_table} {$where_clause} ORDER BY id DESC LIMIT {$items_per_page} OFFSET {$offset}";
$logs = $wpdb->get_results($logs_query);

get_header();
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="card-title mb-1">
                                <i class="ph ph-list-checks me-2 text-primary"></i>Nhật ký kiểm tra trạng thái Website
                            </h4>
                            <p class="text-muted mb-0 font-size-13">Lưu vết kết quả kiểm tra tự động định kỳ (Cronjob) gửi request tới website vệ tinh</p>
                        </div>
                        <div>
                            <button type="button" id="clear-status-logs-btn" class="btn btn-danger btn-sm">
                                <i class="ph ph-trash me-1"></i>Xóa toàn bộ nhật ký
                            </button>
                        </div>
                    </div>

                    <!-- Filter & Search Form -->
                    <form method="GET" action="" class="row g-3 mb-4">
                        <div class="col-md-5">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Tìm theo tên website, URL ping..." value="<?php echo esc_attr($search_query); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ph ph-magnifying-glass"></i> Tìm kiếm
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Tất cả trạng thái --</option>
                                <option value="SUCCESS" <?php selected($status_filter, 'SUCCESS'); ?>>Thành công (SUCCESS)</option>
                                <option value="FAILED" <?php selected($status_filter, 'FAILED'); ?>>Thất bại (FAILED)</option>
                            </select>
                        </div>
                        <?php if (!empty($search_query) || !empty($status_filter)): ?>
                        <div class="col-md-2">
                            <a href="<?php echo home_url('/nhat-ky-website/'); ?>" class="btn btn-outline-secondary w-100">
                                <i class="ph ph-x me-1"></i>Đặt lại
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>

                    <!-- Logs Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">ID</th>
                                    <th style="width: 160px;">Thời gian</th>
                                    <th>Tên Website</th>
                                    <th>Endpoint Ping</th>
                                    <th style="width: 100px;">Trạng thái</th>
                                    <th style="width: 100px;">Mã HTTP</th>
                                    <th>Chi tiết Phản hồi / Lỗi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="ph ph-tray fs-2 d-block mb-2 text-secondary"></i>
                                        Không tìm thấy bản ghi nhật ký kiểm tra website nào.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): 
                                        $badge_class = ($log->status === 'SUCCESS') ? 'bg-success' : 'bg-danger';
                                    ?>
                                    <tr>
                                        <td><code>#<?php echo esc_html($log->id); ?></code></td>
                                        <td class="text-nowrap text-muted"><?php echo esc_html($log->created_at); ?></td>
                                        <td><strong><?php echo esc_html($log->website_name); ?></strong></td>
                                        <td><code style="font-size: 11px;"><?php echo esc_html($log->ping_url); ?></code></td>
                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo esc_html($log->status); ?></span></td>
                                        <td>
                                            <?php if ($log->http_code == 200): ?>
                                                <span class="badge bg-light-success text-success border border-success">200 OK</span>
                                            <?php elseif ($log->http_code): ?>
                                                <span class="badge bg-light-danger text-danger border border-danger">HTTP <?php echo esc_html($log->http_code); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <details>
                                                <summary class="text-primary cursor-pointer fw-semibold" style="font-size: 12px;">Xem chi tiết</summary>
                                                <div class="mt-2 p-2 bg-light rounded border">
                                                    <?php if (!empty($log->error_message)): ?>
                                                        <strong class="text-danger">Lỗi:</strong>
                                                        <div class="text-danger mb-2 p-1 bg-white border border-danger rounded" style="font-size: 11px;"><?php echo esc_html($log->error_message); ?></div>
                                                    <?php endif; ?>
                                                    <strong>Response Body:</strong>
                                                    <pre class="mb-0 p-2 bg-white border rounded" style="max-height: 150px; font-size: 11px; white-space: pre-wrap; word-break: break-all;"><?php echo esc_html($log->response_body ? $log->response_body : 'Không có nội dung phản hồi'); ?></pre>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-middle mt-4">
                        <div class="text-muted font-size-13">
                            Hiển thị <?php echo count($logs); ?> / tổng số <?php echo $total_items; ?> bản ghi log
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $base_url = home_url('/nhat-ky-website/');
                                $query_args = array();
                                if (!empty($search_query)) $query_args['search'] = $search_query;
                                if (!empty($status_filter)) $query_args['status'] = $status_filter;

                                for ($i = 1; $i <= $total_pages; $i++) {
                                    $query_args['paged'] = $i;
                                    $page_link = add_query_arg($query_args, $base_url);
                                    $active_class = ($i == $current_page) ? 'active' : '';
                                    echo '<li class="page-item ' . $active_class . '"><a class="page-link" href="' . esc_url($page_link) . '">' . $i . '</a></li>';
                                }
                                ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clearBtn = document.getElementById('clear-status-logs-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (confirm('Bạn có chắc chắn muốn xóa TOÀN BỘ nhật ký kiểm tra trạng thái Website?')) {
                const formData = new FormData();
                formData.append('action', 'clear_website_status_logs');

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.data.message);
                        window.location.reload();
                    } else {
                        alert('Lỗi: ' + data.data.message);
                    }
                })
                .catch(err => {
                    alert('Có lỗi xảy ra khi kết nối tới hệ thống!');
                });
            }
        });
    }
});
</script>

<?php
get_footer();
?>
