<?php
/*
    Template Name: Revenue Statistics
*/

global $wpdb;
$invoices_table = $wpdb->prefix . 'im_invoices';
$users_table = $wpdb->prefix . 'im_users';

// Check authentication
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

// Get current user's Inova ID for permission filtering
$current_user_id = get_current_user_id();
$inova_user_id = get_user_inova_id($current_user_id);

// Get filter parameters using proper WordPress query vars
$year_param = get_query_var('year');
$partner_param = get_query_var('partner');

$selected_year = !empty($year_param) ? intval($year_param) : date('Y');
$partner_filter = !empty($partner_param) ? intval($partner_param) : 0;

// Validate year (allow only last 5 years to current + 1)
$current_year = date('Y');
$min_year = $current_year - 5;
$max_year = $current_year + 1;

if ($selected_year < $min_year || $selected_year > $max_year) {
    $selected_year = $current_year;
}

// Get permission where clause
$permission_where = get_user_permission_where_clause('u', 'id');

// Build WHERE clause
$where_clause = "WHERE YEAR(i.invoice_date) = {$selected_year} 
                 AND i.status = 'PAID' 
                 {$permission_where}";

// Add partner filter if specified
if ($partner_filter > 0) {
    $where_clause .= $wpdb->prepare(" AND i.partner_id = %d", $partner_filter);
}

// Get monthly statistics for the year
$monthly_stats = $wpdb->get_results("
    SELECT 
        MONTH(i.invoice_date) AS month_num,
        COUNT(i.id) AS invoice_count,
        SUM(i.total_amount) AS total_revenue,
        SUM(CASE WHEN i.payment_date IS NOT NULL THEN i.total_amount ELSE 0 END) AS paid_amount,
        SUM(CASE WHEN i.payment_date IS NULL THEN i.total_amount ELSE 0 END) AS unpaid_amount
    FROM {$invoices_table} i
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    {$where_clause}
    GROUP BY MONTH(i.invoice_date)
    ORDER BY month_num ASC
");

// Get total statistics for the year
$total_stats = $wpdb->get_row("
    SELECT 
        COUNT(i.id) AS total_invoices,
        SUM(i.total_amount) AS total_year_revenue,
        AVG(i.total_amount) AS avg_invoice_amount,
        COUNT(DISTINCT i.user_id) AS unique_customers
    FROM {$invoices_table} i
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    {$where_clause}
");

// Get outstanding invoices (not paid)
$outstanding_stats = $wpdb->get_row("
    SELECT 
        COUNT(i.id) AS outstanding_invoices,
        SUM(i.total_amount - COALESCE(i.paid_amount, 0)) AS outstanding_amount
    FROM {$invoices_table} i
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    WHERE YEAR(i.invoice_date) = {$selected_year}
    AND i.status != 'PAID'
    {$permission_where}
");

// Get partners list for filter
$partners = $wpdb->get_results("
    SELECT DISTINCT i.partner_id, u.name, u.user_code
    FROM {$invoices_table} i
    LEFT JOIN {$users_table} u ON i.partner_id = u.id
    WHERE i.partner_id IS NOT NULL
    AND YEAR(i.invoice_date) = {$selected_year}
    {$permission_where}
    ORDER BY u.name ASC
");

// Format month names in Vietnamese
$month_names = array(
    1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3', 4 => 'Tháng 4',
    5 => 'Tháng 5', 6 => 'Tháng 6', 7 => 'Tháng 7', 8 => 'Tháng 8',
    9 => 'Tháng 9', 10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
);

get_header();
?>

<div class="main-panel">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <i class="ph ph-chart-line me-2"></i>Thống Kê Doanh Thu
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <div class="page-wrapper">
        <div class="container-xl">
            <!-- Filters Row -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Chọn Năm:</label>
                    <select id="year-select" class="form-select">
                        <?php
                        for ($y = $min_year; $y <= $max_year; $y++) {
                            $selected = ($y == $selected_year) ? 'selected' : '';
                            echo "<option value=\"{$y}\" {$selected}>{$y}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lọc theo Đối Tác:</label>
                    <select id="partner-filter" class="form-select">
                        <option value="0" <?php echo ($partner_filter == 0) ? 'selected' : ''; ?>>Tất cả</option>
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?php echo esc_attr($partner->partner_id); ?>" 
                                    <?php echo ($partner_filter == $partner->partner_id) ? 'selected' : ''; ?>>
                                <?php echo esc_html($partner->name . ' (' . $partner->user_code . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="card border-primary">
                        <div class="card-body">
                            <div class="text-muted">HĐ Đã TT</div>
                            <div class="fs-4 fw-bold text-primary">
                                <?php echo intval($total_stats->total_invoices ?? 0); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body">
                            <div class="text-muted">Doanh Thu</div>
                            <div class="fs-4 fw-bold text-success">
                                <?php echo number_format(intval($total_stats->total_year_revenue ?? 0)); ?> ₫
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="text-muted">HĐ Chưa TT</div>
                            <div class="fs-4 fw-bold text-warning">
                                <?php echo intval($outstanding_stats->outstanding_invoices ?? 0); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-danger">
                        <div class="card-body">
                            <div class="text-muted">Tiền Nợ</div>
                            <div class="fs-4 fw-bold text-danger">
                                <?php echo number_format(intval($outstanding_stats->outstanding_amount ?? 0)); ?> ₫
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted">Trung Bình/HĐ</div>
                            <div class="fs-5 fw-bold">
                                <?php echo number_format(intval($total_stats->avg_invoice_amount ?? 0)); ?> ₫
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted">Khách Hàng Duy Nhất</div>
                            <div class="fs-5 fw-bold">
                                <?php echo intval($total_stats->unique_customers ?? 0); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="card mt-4">
                <div class="card-header">
                    <h4 class="card-title">Doanh Thu 12 Tháng - Năm <?php echo esc_html($selected_year); ?></h4>
                </div>
                <div class="card-body">
                    <canvas id="revenue-chart" height="80"></canvas>
                </div>
            </div>

            <!-- Monthly Table -->
            <div class="card mt-4">
                <div class="card-header">
                    <h4 class="card-title">Chi Tiết Theo Tháng</h4>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tháng</th>
                                <th class="text-right">Số HĐ</th>
                                <th class="text-right">Tổng Tiền</th>
                                <th class="text-right">Đã TT</th>
                                <th class="text-right">Chưa TT</th>
                                <th class="text-center">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Create array for all 12 months (fill missing months with 0)
                            $stats_by_month = array();
                            foreach ($monthly_stats as $stat) {
                                $stats_by_month[$stat->month_num] = $stat;
                            }

                            for ($m = 1; $m <= 12; $m++):
                                $stat = isset($stats_by_month[$m]) ? $stats_by_month[$m] : null;
                                $invoice_count = $stat ? intval($stat->invoice_count) : 0;
                                $total = $stat ? intval($stat->total_revenue) : 0;
                                $paid = $stat ? intval($stat->paid_amount) : 0;
                                $unpaid = $stat ? intval($stat->unpaid_amount) : 0;
                                $percentage = $total > 0 ? round(($paid / $total) * 100) : 0;
                                ?>
                                <tr>
                                    <td><?php echo esc_html($month_names[$m]); ?></td>
                                    <td class="text-right">
                                        <span class="badge bg-primary"><?php echo $invoice_count; ?></span>
                                    </td>
                                    <td class="text-right fw-bold">
                                        <?php echo number_format($total); ?> ₫
                                    </td>
                                    <td class="text-right">
                                        <span class="text-success">
                                            <?php echo number_format($paid); ?> ₫
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <span class="text-warning">
                                            <?php echo number_format($unpaid); ?> ₫
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info view-month-details" data-month="<?php echo $m; ?>" data-year="<?php echo $selected_year; ?>">
                                            <i class="ph ph-eye"></i> Chi tiết
                                        </button>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Data for JavaScript
$chart_data = array();
$labels = array();
for ($m = 1; $m <= 12; $m++) {
    $labels[] = 'T' . $m;
    $stat = isset($stats_by_month[$m]) ? $stats_by_month[$m] : null;
    $chart_data[] = $stat ? intval($stat->total_revenue) : 0;
}
?>

<script>
// Data for chart
const chartLabels = <?php echo json_encode($labels); ?>;
const chartData = <?php echo json_encode($chart_data); ?>;

jQuery(document).ready(function($) {
    // Initialize Chart.js if available
    if (typeof Chart !== 'undefined') {
        const ctx = document.getElementById('revenue-chart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Doanh Thu (VNĐ)',
                        data: chartData,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('vi-VN').format(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true
                        }
                    }
                }
            });
        }
    }

    // Year change
    $('#year-select').on('change', function() {
        const year = $(this).val();
        const partner = $('#partner-filter').val();
        window.location.href = '<?php echo home_url('/revenue-stats/'); ?>?year=' + year + '&partner=' + partner;
    });

    // Partner filter change
    $('#partner-filter').on('change', function() {
        const partner = $(this).val();
        const year = $('#year-select').val();
        window.location.href = '<?php echo home_url('/revenue-stats/'); ?>?year=' + year + '&partner=' + partner;
    });

    // View month details
    $(document).on('click', '.view-month-details', function() {
        const month = $(this).data('month');
        const year = $(this).data('year');
        window.location.href = '<?php echo home_url('/list-invoice/'); ?>?date_from=' + month + '/01/' + year + 
                               '&date_to=' + month + '/31/' + year + '&status=PAID';
    });
});
</script>

<?php
get_footer();
?>
