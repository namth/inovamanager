<?php
/* 
    Template Name: Service List - All Stages Management
    Purpose: List and manage all website services across all stages
*/

global $wpdb;
$service_table = $wpdb->prefix . 'im_website_services';

// Handle status filter
$status_filter = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

// Build SQL query
$where_conditions = array();
$where_values = array();

if (!empty($status_filter)) {
    $where_conditions[] = "s.status = %s";
    $where_values[] = $status_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(s.service_code LIKE %s OR s.title LIKE %s OR w.name LIKE %s OR u1.name LIKE %s)";
    $where_values[] = '%' . $search_query . '%';
    $where_values[] = '%' . $search_query . '%';
    $where_values[] = '%' . $search_query . '%';
    $where_values[] = '%' . $search_query . '%';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
}

// Get services with pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$sql = "
    SELECT s.*, w.name as website_name, d.domain_name,
           u1.name as requester_name, u1.user_code as requester_code,
           u2.name as assignee_name, u2.user_code as assignee_code
    FROM $service_table s
    LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
    LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
    LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
    LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
    $where_clause
    ORDER BY s.created_at DESC
    LIMIT $per_page OFFSET $offset
";

if (!empty($where_values)) {
    $services = $wpdb->get_results($wpdb->prepare($sql, ...$where_values));
    $total_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $service_table s
        LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
        LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
        $where_clause
    ", ...$where_values));
} else {
    $services = $wpdb->get_results($sql);
    $total_count = $wpdb->get_var("
        SELECT COUNT(*) FROM $service_table s
        LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
        LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
        $where_clause
    ");
}

// Get statistics
$stats = $wpdb->get_results("
    SELECT status, COUNT(*) as count 
    FROM $service_table 
    GROUP BY status
");

$status_counts = array();
foreach ($stats as $stat) {
    $status_counts[$stat->status] = $stat->count;
}

get_header(); 
?>

<div class="main-panel">
    <div class="content-wrapper">
        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="card-title mb-0">
                                    <i class="ph ph-list me-2"></i>
                                    Quản lý dịch vụ website
                                </h4>
                                <p class="card-description mb-0">Tất cả các yêu cầu dịch vụ qua 5 giai đoạn</p>
                            </div>
                            <div>
                                <a href="<?php echo home_url('/service-request/'); ?>" class="btn btn-primary">
                                    <i class="ph ph-plus me-2"></i>Thêm yêu cầu
                                </a>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <div class="card text-center">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold text-warning">Chờ xử lý</h6>
                                        <h4 class="text-warning"><?php echo $status_counts['PENDING'] ?? 0; ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card text-center">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold text-success">Đã duyệt</h6>
                                        <h4 class="text-success"><?php echo $status_counts['APPROVED'] ?? 0; ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card text-center">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold text-primary">Đang thực hiện</h6>
                                        <h4 class="text-primary"><?php echo $status_counts['IN_PROGRESS'] ?? 0; ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card text-center">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold text-info">Hoàn thành</h6>
                                        <h4 class="text-info"><?php echo $status_counts['COMPLETED'] ?? 0; ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card text-center">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold text-danger">Đã hủy</h6>
                                        <h4 class="text-danger"><?php echo $status_counts['CANCELLED'] ?? 0; ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card text-center">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold text-dark">Tổng cộng</h6>
                                        <h4 class="text-dark"><?php echo array_sum($status_counts); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <form method="get" class="d-flex gap-3 align-items-end">
                                    <div class="flex-grow-1">
                                        <label class="form-label">Tìm kiếm</label>
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Mã dịch vụ, tiêu đề, website, khách hàng..." 
                                               value="<?php echo esc_attr($search_query); ?>">
                                    </div>
                                    <div>
                                        <label class="form-label">Trạng thái</label>
                                        <select class="form-control" name="status">
                                            <option value="">Tất cả</option>
                                            <option value="PENDING" <?php echo $status_filter === 'PENDING' ? 'selected' : ''; ?>>Chờ xử lý</option>
                                            <option value="APPROVED" <?php echo $status_filter === 'APPROVED' ? 'selected' : ''; ?>>Đã duyệt</option>
                                            <option value="IN_PROGRESS" <?php echo $status_filter === 'IN_PROGRESS' ? 'selected' : ''; ?>>Đang thực hiện</option>
                                            <option value="COMPLETED" <?php echo $status_filter === 'COMPLETED' ? 'selected' : ''; ?>>Hoàn thành</option>
                                            <option value="CANCELLED" <?php echo $status_filter === 'CANCELLED' ? 'selected' : ''; ?>>Đã hủy</option>
                                        </select>
                                    </div>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ph ph-magnifying-glass me-2"></i>Tìm kiếm
                                        </button>
                                        <a href="<?php echo home_url('/danh-sach-dich-vu/'); ?>" class="btn btn-light">
                                            <i class="ph ph-x me-2"></i>Xóa bộ lọc
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Services Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mã dịch vụ</th>
                                        <th>Website</th>
                                        <th>Tiêu đề</th>
                                        <th>Khách hàng</th>
                                        <th>Người thực hiện</th>
                                        <th>Trạng thái</th>
                                        <th>Giá trị</th>
                                        <th>Ngày tạo</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($services)): ?>
                                        <?php foreach ($services as $service): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html($service->service_code); ?></strong>
                                                <?php if ($service->priority === 'URGENT'): ?>
                                                    <span class="badge badge-danger ms-1">Khẩn cấp</span>
                                                <?php elseif ($service->priority === 'HIGH'): ?>
                                                    <span class="badge badge-warning ms-1">Cao</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo esc_html($service->website_name); ?></strong>
                                                    <?php if ($service->domain_name): ?>
                                                        <br><small class="text-muted"><?php echo esc_html($service->domain_name); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 200px;" title="<?php echo esc_attr($service->title); ?>">
                                                    <?php echo esc_html($service->title); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo esc_html($service->requester_name); ?>
                                            </td>
                                            <td>
                                                <?php if ($service->assignee_name): ?>
                                                    <?php echo esc_html($service->assignee_name); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa phân công</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_info = array(
                                                    'PENDING' => array('class' => 'warning', 'text' => 'Chờ xử lý'),
                                                    'APPROVED' => array('class' => 'success', 'text' => 'Đã duyệt'),
                                                    'IN_PROGRESS' => array('class' => 'primary', 'text' => 'Đang thực hiện'),
                                                    'COMPLETED' => array('class' => 'info', 'text' => 'Hoàn thành'),
                                                    'CANCELLED' => array('class' => 'danger', 'text' => 'Đã hủy')
                                                );
                                                $status = $status_info[$service->status] ?? array('class' => 'secondary', 'text' => $service->status);
                                                ?>
                                                <span class="badge badge-<?php echo $status['class']; ?>">
                                                    <?php echo $status['text']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($service->pricing_type === 'DAILY' && $service->estimated_manday && $service->daily_rate): ?>
                                                    <?php echo number_format($service->estimated_manday * $service->daily_rate); ?> VNĐ
                                                    <br><small class="text-muted"><?php echo $service->estimated_manday; ?> ngày</small>
                                                <?php elseif ($service->pricing_type === 'FIXED' && $service->fixed_price): ?>
                                                    <?php echo number_format($service->fixed_price); ?> VNĐ
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa báo giá</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($service->created_at)); ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                
                                                    <!-- Stage 1: Customer Request -->
                                                    <a class="nav-link text-warning" href="<?php echo home_url('/service-request/?service_id=' . $service->id); ?>">
                                                        <i class="ph ph-eye fa-150p"></i>
                                                    </a>
                                                    
                                                    <!-- Stage 2: Quotation (if PENDING) -->
                                                    <?php if ($service->status === 'PENDING'): ?>
                                                    <a class="nav-link text-warning" href="<?php echo home_url('/bao-gia/?service_id=' . $service->id); ?>">
                                                        <i class="ph ph-calculator fa-150p"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Stage 3: Invoice (if APPROVED) -->
                                                    <?php if ($service->status === 'APPROVED'): ?>
                                                    <a class="nav-link text-warning" href="<?php echo home_url('/tao-hoa-don/?service_id=' . $service->id); ?>">
                                                        <i class="ph ph-receipt fa-150p"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Stage 4: Execution (if APPROVED or IN_PROGRESS) -->
                                                    <?php if (in_array($service->status, ['APPROVED', 'IN_PROGRESS'])): ?>
                                                    <a class="nav-link text-warning" href="<?php echo home_url('/processing/?service_id=' . $service->id); ?>">
                                                        <i class="ph ph-gear fa-150p"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Stage 5: Completion (if IN_PROGRESS) -->
                                                    <?php if ($service->status === 'IN_PROGRESS'): ?>
                                                    <a class="nav-link text-warning" href="<?php echo home_url('/completion/?service_id=' . $service->id); ?>">
                                                        <i class="ph ph-check-circle fa-150p"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <!-- View completed (if COMPLETED) -->
                                                    <?php if ($service->status === 'COMPLETED'): ?>
                                                    <a class="nav-link text-warning" href="<?php echo home_url('/completion/?service_id=' . $service->id); ?>">
                                                        <i class="ph ph-file-text fa-150p"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <a class="nav-link text-danger" href="#" onclick="confirmDelete(<?php echo $service->id; ?>)">
                                                        <i class="ph ph-trash fa-150p"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="ph ph-empty ph-2x mb-3"></i>
                                                <br>Không có dịch vụ nào được tìm thấy
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_count > $per_page): ?>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                Hiển thị <?php echo (($page - 1) * $per_page) + 1; ?> - <?php echo min($page * $per_page, $total_count); ?> 
                                trong tổng số <?php echo $total_count; ?> dịch vụ
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm">
                                    <?php
                                    $total_pages = ceil($total_count / $per_page);
                                    $current_url = remove_query_arg('page');
                                    
                                    // Previous
                                    if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo add_query_arg('page', $page - 1, $current_url); ?>">‹</a>
                                        </li>
                                    <?php endif;
                                    
                                    // Page numbers
                                    for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo add_query_arg('page', $i, $current_url); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor;
                                    
                                    // Next
                                    if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo add_query_arg('page', $page + 1, $current_url); ?>">›</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(serviceId) {
    if (confirm('Bạn có chắc chắn muốn xóa dịch vụ này? Thao tác này không thể hoàn tác.')) {
        // Implement delete functionality
        window.location.href = `<?php echo home_url('/xoa-dich-vu/'); ?>?service_id=${serviceId}`;
    }
}

// Auto-refresh status every 30 seconds for active services
setInterval(function() {
    const activeStatuses = ['PENDING', 'APPROVED', 'IN_PROGRESS'];
    const currentStatus = '<?php echo $status_filter; ?>';
    
    if (!currentStatus || activeStatuses.includes(currentStatus)) {
        // Only refresh if we're viewing active services
        // You can implement AJAX refresh here if needed
    }
}, 30000);
</script>

<?php get_footer(); ?>
