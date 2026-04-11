<?php
/*
    Template Name: Expense Declarations List
*/

global $wpdb;
$declarations_table = $wpdb->prefix . 'im_expense_declarations';
$recurring_table    = $wpdb->prefix . 'im_recurring_expenses';

// Admin-only check
if (!is_inova_admin()) {
    wp_redirect(home_url('/'));
    exit;
}

// Handle delete request
$message      = '';
$message_type = '';

if (isset($_POST['delete_declaration_id']) && !empty($_POST['delete_declaration_id'])) {
    $del_id = intval($_POST['delete_declaration_id']);
    $decl   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $declarations_table WHERE id = %d", $del_id));

    if ($decl) {
        $deleted = $wpdb->delete($declarations_table, array('id' => $del_id), array('%d'));
        if ($deleted) {
            $message      = "Kê khai '{$decl->name}' đã được xóa thành công!";
            $message_type = 'success';
        } else {
            $message      = 'Có lỗi xảy ra khi xóa.';
            $message_type = 'danger';
        }
    } else {
        $message      = 'Kê khai không tồn tại.';
        $message_type = 'danger';
    }
}

// Filters
$filter_month    = isset($_GET['month'])    ? intval($_GET['month'])                             : intval(date('m'));
$filter_year     = isset($_GET['year'])     ? intval($_GET['year'])                              : intval(date('Y'));
$filter_category = isset($_GET['category']) ? sanitize_text_field($_GET['category'])             : '';
$search_query    = isset($_GET['search'])   ? sanitize_text_field($_GET['search'])               : '';

// Build WHERE
$where_parts = array();
if ($filter_month > 0) {
    $where_parts[] = $wpdb->prepare("MONTH(d.expense_date) = %d", $filter_month);
}
if ($filter_year > 0) {
    $where_parts[] = $wpdb->prepare("YEAR(d.expense_date) = %d", $filter_year);
}
if (!empty($filter_category)) {
    $where_parts[] = $wpdb->prepare("d.category = %s", $filter_category);
}
if (!empty($search_query)) {
    $like = '%' . $wpdb->esc_like($search_query) . '%';
    $where_parts[] = $wpdb->prepare("(d.name LIKE %s OR d.note LIKE %s)", $like, $like);
}
$where_sql = !empty($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Pagination
$items_per_page = 15;
$current_page   = max(1, intval(get_query_var('paged')));
$total_items    = (int) $wpdb->get_var("SELECT COUNT(*) FROM $declarations_table d $where_sql");
$total_pages    = ceil($total_items / $items_per_page);
$offset         = ($current_page - 1) * $items_per_page;

// Get records
$declarations = $wpdb->get_results("
    SELECT d.*, r.name AS recurring_name, r.billing_cycle
    FROM $declarations_table d
    LEFT JOIN $recurring_table r ON d.recurring_expense_id = r.id
    $where_sql
    ORDER BY d.expense_date DESC
    LIMIT $items_per_page OFFSET $offset
");

// ---- Statistics ----
// Total for current filters (no pagination)
$stat_total = (int) $wpdb->get_var("SELECT SUM(d.amount) FROM $declarations_table d $where_sql");

// Monthly breakdown for selected year (for chart / summary)
$monthly_stats = $wpdb->get_results($wpdb->prepare("
    SELECT MONTH(expense_date) AS m, SUM(amount) AS total
    FROM $declarations_table
    WHERE YEAR(expense_date) = %d
    " . (!empty($filter_category) ? $wpdb->prepare("AND category = %s", $filter_category) : "") . "
    GROUP BY MONTH(expense_date)
    ORDER BY m ASC
", $filter_year));

$monthly_by_num = array();
foreach ($monthly_stats as $ms) {
    $monthly_by_num[$ms->m] = (int) $ms->total;
}

// Category breakdown for current filter period
$category_stats_raw = $wpdb->get_results("
    SELECT d.category, SUM(d.amount) AS total
    FROM $declarations_table d
    $where_sql
    GROUP BY d.category
    ORDER BY total DESC
");

// Year total
$year_total = (int) $wpdb->get_var($wpdb->prepare("
    SELECT SUM(amount) FROM $declarations_table WHERE YEAR(expense_date) = %d
", $filter_year));

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

$month_names = array(
    1=>'Tháng 1',2=>'Tháng 2',3=>'Tháng 3',4=>'Tháng 4',
    5=>'Tháng 5',6=>'Tháng 6',7=>'Tháng 7',8=>'Tháng 8',
    9=>'Tháng 9',10=>'Tháng 10',11=>'Tháng 11',12=>'Tháng 12'
);

// Build pagination URL helper
function declaration_page_url($page, $month, $year, $category, $search) {
    $params = array('paged' => $page, 'month' => $month, 'year' => $year);
    if ($category) $params['category'] = $category;
    if ($search)   $params['search']   = $search;
    return home_url('/ke-khai-chi-phi/?' . http_build_query($params));
}

$available_years = range(date('Y'), 2020);

get_header();
?>

<div class="content-wrapper">
    <div class="row">
        <!-- ===================== SUMMARY CARDS ===================== -->
        <div class="col-12 mb-3">
            <div class="row g-3">
                <!-- Card: Tháng hiện tại (filtered) -->
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #6c63ff !important;">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:52px;height:52px;background:rgba(108,99,255,.12);">
                                <i class="ph ph-calendar-check" style="font-size:24px;color:#6c63ff;"></i>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">
                                    <?php echo $month_names[$filter_month] ?? ''; ?> <?php echo $filter_year; ?>
                                    <?php if (!empty($filter_category)): ?>
                                    <br><span class="badge bg-light-primary text-primary border-radius-9"><?php echo $categories[$filter_category] ?? $filter_category; ?></span>
                                    <?php endif; ?>
                                </p>
                                <h5 class="mb-0 fw-bold"><?php echo number_format($stat_total, 0, '.', ','); ?> VNĐ</h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Năm (overall) -->
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #17c1e8 !important;">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:52px;height:52px;background:rgba(23,193,232,.12);">
                                <i class="ph ph-chart-bar" style="font-size:24px;color:#17c1e8;"></i>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Tổng năm <?php echo $filter_year; ?></p>
                                <h5 class="mb-0 fw-bold"><?php echo number_format($year_total, 0, '.', ','); ?> VNĐ</h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Số khoản -->
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #fb6340 !important;">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:52px;height:52px;background:rgba(251,99,64,.12);">
                                <i class="ph ph-list-numbers" style="font-size:24px;color:#fb6340;"></i>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Số khoản chi (bộ lọc)</p>
                                <h5 class="mb-0 fw-bold"><?php echo number_format($total_items); ?> khoản</h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Top category -->
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #2dce89 !important;">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:52px;height:52px;background:rgba(45,206,137,.12);">
                                <i class="ph ph-tag" style="font-size:24px;color:#2dce89;"></i>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Danh mục chi nhiều nhất</p>
                                <?php if (!empty($category_stats_raw)): ?>
                                <h5 class="mb-0 fw-bold" style="font-size:0.95rem;">
                                    <?php echo $categories[$category_stats_raw[0]->category] ?? $category_stats_raw[0]->category; ?>
                                    <br><small class="text-muted"><?php echo number_format($category_stats_raw[0]->total, 0, '.', ','); ?> VNĐ</small>
                                </h5>
                                <?php else: ?>
                                <h5 class="mb-0 fw-bold text-muted">—</h5>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== MONTHLY BAR CHART ===================== -->
        <div class="col-lg-8 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Chi phí theo tháng – năm <?php echo $filter_year; ?></h6>
                    <canvas id="monthlyChart" height="110"></canvas>
                </div>
            </div>
        </div>

        <!-- ===================== CATEGORY BREAKDOWN ===================== -->
        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Theo danh mục (bộ lọc hiện tại)</h6>
                    <?php if (!empty($category_stats_raw)): ?>
                    <?php
                        $cat_total = array_sum(array_column($category_stats_raw, 'total'));
                        $palette = ['#6c63ff','#17c1e8','#fb6340','#2dce89','#f53d6b','#ffd166','#06d6a0','#118ab2'];
                        $ci = 0;
                    ?>
                    <?php foreach ($category_stats_raw as $cs): ?>
                    <?php
                        $pct = $cat_total > 0 ? round($cs->total / $cat_total * 100, 1) : 0;
                        $color = $palette[$ci % count($palette)];
                        $ci++;
                    ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="fw-semibold"><?php echo $categories[$cs->category] ?? $cs->category; ?></small>
                            <small class="text-muted"><?php echo number_format($cs->total, 0, '.', ','); ?> (<?php echo $pct; ?>%)</small>
                        </div>
                        <div class="progress" style="height:7px;">
                            <div class="progress-bar" role="progressbar"
                                 style="width:<?php echo $pct; ?>%;background:<?php echo $color; ?>;"
                                 aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p class="text-muted text-center mt-4">Không có dữ liệu</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===================== TABLE ===================== -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Filter bar -->
                    <form method="GET" class="row g-2 mb-4 align-items-end">
                        <div class="col-auto">
                            <label class="form-label mb-1 fw-semibold small">Tháng</label>
                            <select name="month" class="form-select form-select-sm">
                                <option value="0">Tất cả tháng</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php selected($filter_month, $m); ?>>Tháng <?php echo $m; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="form-label mb-1 fw-semibold small">Năm</label>
                            <select name="year" class="form-select form-select-sm">
                                <?php foreach ($available_years as $y): ?>
                                <option value="<?php echo $y; ?>" <?php selected($filter_year, $y); ?>><?php echo $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="form-label mb-1 fw-semibold small">Danh mục</label>
                            <select name="category" class="form-select form-select-sm">
                                <option value="">Tất cả</option>
                                <?php foreach ($categories as $k => $v): ?>
                                <option value="<?php echo $k; ?>" <?php selected($filter_category, $k); ?>><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label mb-1 fw-semibold small">Tìm kiếm</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Tên khoản chi..." value="<?php echo esc_attr($search_query); ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="ph ph-funnel me-1"></i>Lọc
                            </button>
                            <a href="<?php echo home_url('/ke-khai-chi-phi/'); ?>" class="btn btn-light btn-sm ms-1">
                                <i class="ph ph-x me-1"></i>Xóa lọc
                            </a>
                        </div>
                    </form>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">
                            <i class="ph ph-currency-circle-dollar me-2 text-primary"></i>
                            Kê khai Chi phí
                        </h5>
                        <a href="<?php echo home_url('/them-ke-khai-chi-phi/'); ?>" class="btn btn-primary btn-sm">
                            <i class="ph ph-plus me-1"></i>Ghi khoản chi mới
                        </a>
                    </div>

                    <?php if (empty($declarations)): ?>
                    <div class="text-center py-5">
                        <i class="ph ph-receipt" style="font-size:48px;" class="text-muted"></i>
                        <h5 class="mt-3 text-muted">Không có khoản chi nào</h5>
                        <p class="text-muted">Thay đổi bộ lọc hoặc thêm khoản chi mới</p>
                        <a href="<?php echo home_url('/them-ke-khai-chi-phi/'); ?>" class="btn btn-primary mt-2">
                            <i class="ph ph-plus-circle me-1"></i>Ghi khoản chi mới
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>STT</th>
                                    <th>Ngày</th>
                                    <th>Tên khoản chi</th>
                                    <th>Danh mục</th>
                                    <th>Số tiền</th>
                                    <th>Liên kết</th>
                                    <th>Ghi chú</th>
                                    <th class="text-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($declarations as $i => $d): ?>
                                <tr>
                                    <td class="text-muted fw-bold"><?php echo $i + 1 + $offset; ?></td>
                                    <td class="text-nowrap">
                                        <strong><?php echo date('d/m/Y', strtotime($d->expense_date)); ?></strong>
                                    </td>
                                    <td><strong><?php echo esc_html($d->name); ?></strong></td>
                                    <td>
                                        <span class="badge border-radius-9 bg-light-primary text-primary">
                                            <?php echo $categories[$d->category] ?? esc_html($d->category); ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <strong class="text-danger"><?php echo number_format($d->amount, 0, '.', ','); ?> VNĐ</strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($d->recurring_name)): ?>
                                        <small class="text-muted">
                                            <i class="ph ph-link-simple me-1"></i><?php echo esc_html($d->recurring_name); ?>
                                        </small>
                                        <?php else: ?>
                                        <small class="text-muted">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($d->note)): ?>
                                        <small class="text-muted"><?php echo esc_html(mb_substr($d->note, 0, 50)); ?></small>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <a href="<?php echo home_url('/sua-ke-khai-chi-phi/?declaration_id=' . $d->id); ?>"
                                           class="btn btn-sm btn-info d-inline-flex align-items-center" title="Sửa">
                                            <i class="ph ph-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center ms-1"
                                                onclick="confirmDeleteDeclaration(<?php echo $d->id; ?>, '<?php echo esc_attr($d->name); ?>')"
                                                title="Xóa">
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
                    <div class="mt-3 d-flex justify-content-center">
                        <nav>
                            <ul class="pagination pagination-sm">
                                <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo declaration_page_url($current_page - 1, $filter_month, $filter_year, $filter_category, $search_query); ?>">
                                        <i class="ph ph-caret-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++): ?>
                                <li class="page-item <?php echo ($p === $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo declaration_page_url($p, $filter_month, $filter_year, $filter_category, $search_query); ?>"><?php echo $p; ?></a>
                                </li>
                                <?php endfor; ?>
                                <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo declaration_page_url($current_page + 1, $filter_month, $filter_year, $filter_category, $search_query); ?>">
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

<!-- Hidden delete form -->
<form id="deleteDeclarationForm" method="POST" style="display:none;">
    <input type="hidden" name="delete_declaration_id" id="delete_declaration_id" value="">
</form>

<!-- Delete modal -->
<div class="modal fade" id="deleteDeclarationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="ph ph-warning-diamond me-2"></i>Xác nhận xóa kê khai</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning"><i class="ph ph-warning me-2"></i><strong>Cảnh báo:</strong> Hành động này không thể hoàn tác!</div>
                <p>Bạn có chắc muốn xóa kê khai <strong id="declarationNameToDelete"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="ph ph-x me-1"></i>Hủy</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteDeclarationBtn"><i class="ph ph-trash me-1"></i>Xóa</button>
            </div>
        </div>
    </div>
</div>

<!-- Add FAB button -->
<a href="<?php echo home_url('/them-ke-khai-chi-phi/'); ?>" class="fixed-bottom-right nav-link" title="Ghi khoản chi mới" data-bs-toggle="tooltip" data-bs-placement="left">
    <i class="ph ph-plus btn-icon-prepend fa-150p"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Delete modal
function confirmDeleteDeclaration(id, name) {
    document.getElementById('delete_declaration_id').value = id;
    document.getElementById('declarationNameToDelete').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteDeclarationModal')).show();
}
document.getElementById('confirmDeleteDeclarationBtn').addEventListener('click', function() {
    document.getElementById('deleteDeclarationForm').submit();
});

// Monthly bar chart
const monthlyData = <?php
    $chart_data = array();
    for ($m = 1; $m <= 12; $m++) {
        $chart_data[] = $monthly_by_num[$m] ?? 0;
    }
    echo json_encode($chart_data);
?>;

const ctx = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'],
        datasets: [{
            label: 'Chi phí (VNĐ)',
            data: monthlyData,
            backgroundColor: 'rgba(108,99,255,0.75)',
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return new Intl.NumberFormat('vi-VN').format(ctx.parsed.y) + ' VNĐ';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(v) {
                        if (v >= 1000000) return (v/1000000).toFixed(1) + 'M';
                        if (v >= 1000) return (v/1000).toFixed(0) + 'K';
                        return v;
                    }
                }
            }
        }
    }
});
</script>

<?php
get_footer();
?>
