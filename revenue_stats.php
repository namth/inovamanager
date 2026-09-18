<?php
/*
    Template Name: Revenue Statistics
*/

global $wpdb;
$invoices_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';
$users_table = $wpdb->prefix . 'im_users';
$commissions_table = $wpdb->prefix . 'im_partner_commissions';

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
$service_param = get_query_var('service_type') ?: (isset($_GET['service_type']) ? sanitize_text_field($_GET['service_type']) : 'all');

$selected_year = !empty($year_param) ? intval($year_param) : date('Y');
$partner_filter = !empty($partner_param) ? intval($partner_param) : 0;
$selected_service = in_array($service_param, array('all', 'domain', 'hosting', 'maintenance', 'website_service')) ? $service_param : 'all';

// Validate year (allow only last 5 years to current + 1)
$current_year = date('Y');
$min_year = $current_year - 5;
$max_year = $current_year + 1;

if ($selected_year < $min_year || $selected_year > $max_year) {
    $selected_year = $current_year;
}

// Get permission where clause
$permission_where = get_user_permission_where_clause('u', 'id');

// Build WHERE clause - Use payment_date for accurate revenue statistics
$where_clause = "WHERE YEAR(i.payment_date) = {$selected_year} 
                 AND i.status IN ('PAID', 'paid')
                 AND i.payment_date IS NOT NULL
                 {$permission_where}";

// Add partner filter if specified
if ($partner_filter > 0) {
    $where_clause .= $wpdb->prepare(" AND i.partner_id = %d", $partner_filter);
}

// Get monthly statistics for the year - Based on payment_date for paid
$monthly_stats = $wpdb->get_results("
    SELECT 
        MONTH(i.payment_date) AS month_num,
        COUNT(i.id) AS invoice_count,
        SUM(i.total_amount) AS total_revenue,
        SUM(i.paid_amount) AS paid_amount,
        0 AS unpaid_amount
    FROM {$invoices_table} i
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    {$where_clause}
    GROUP BY MONTH(i.payment_date)
    ORDER BY month_num ASC
");

// Map paid statistics by month
$stats_by_month = array();
foreach ($monthly_stats as $stat) {
    $stats_by_month[$stat->month_num] = $stat;
}

// Get monthly unpaid statistics - Based on invoice_date (excluding PAID and CANCELED)
$unpaid_stats_query = "
    SELECT 
        MONTH(i.invoice_date) AS month_num,
        COUNT(i.id) AS unpaid_count,
        SUM(i.total_amount - COALESCE(i.paid_amount, 0)) AS unpaid_amount
    FROM {$invoices_table} i
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    WHERE YEAR(i.invoice_date) = {$selected_year}
    AND i.status NOT IN ('PAID', 'paid', 'CANCELED', 'canceled')
    {$permission_where}";

if ($partner_filter > 0) {
    $unpaid_stats_query .= $wpdb->prepare(" AND i.partner_id = %d", $partner_filter);
}

$unpaid_stats_query .= " GROUP BY MONTH(i.invoice_date)
    ORDER BY month_num ASC";

$unpaid_monthly = $wpdb->get_results($unpaid_stats_query);

// Map unpaid statistics by month
$unpaid_by_month = array();
$unpaid_count_by_month = array();
foreach ($unpaid_monthly as $stat) {
    $unpaid_by_month[$stat->month_num] = $stat->unpaid_amount;
    $unpaid_count_by_month[$stat->month_num] = $stat->unpaid_count;
}

// Get monthly commission statistics - Based on created_at, no status filter
$commission_stats_query = "
    SELECT 
        MONTH(c.created_at) AS month_num,
        SUM(c.commission_amount) AS commission_amount
    FROM {$commissions_table} c
    WHERE YEAR(c.created_at) = {$selected_year}
    {$permission_where}";

if ($partner_filter > 0) {
    $commission_stats_query .= $wpdb->prepare(" AND c.partner_id = %d", $partner_filter);
}

$commission_stats_query .= " GROUP BY MONTH(c.created_at)
    ORDER BY month_num ASC";

$commission_monthly = $wpdb->get_results($commission_stats_query);

// Map commission statistics by month
$commission_by_month = array();
foreach ($commission_monthly as $stat) {
    $commission_by_month[$stat->month_num] = $stat->commission_amount;
}

// Service items partner condition
$partner_item_condition = ($partner_filter > 0) ? $wpdb->prepare(" AND i.partner_id = %d", $partner_filter) : "";

if (!function_exists('im_normalize_service_key')) {
    function im_normalize_service_key($type) {
        $t = strtolower(trim((string)$type));
        if (strpos($t, 'domain') !== false) return 'domain';
        if (strpos($t, 'host') !== false) return 'hosting';
        if (strpos($t, 'maint') !== false || strpos($t, 'bảo trì') !== false || strpos($t, 'bao tri') !== false) return 'maintenance';
        if (strpos($t, 'web') !== false) return 'website_service';
        return $t;
    }
}

// Paid amount & count by service type and month from invoice_items
$paid_service_monthly_query = "
    SELECT 
        MONTH(COALESCE(i.payment_date, i.invoice_date)) AS month_num,
        CASE 
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%domain%' THEN 'domain'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%host%' THEN 'hosting'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%maint%' OR LOWER(TRIM(ii.service_type)) LIKE '%bảo trì%' OR LOWER(TRIM(ii.service_type)) LIKE '%bao tri%' THEN 'maintenance'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%web%' THEN 'website_service'
            ELSE 'website_service'
        END AS service_type,
        COUNT(DISTINCT i.id) AS invoice_count,
        SUM(ii.item_total) AS paid_amount
    FROM {$invoices_table} i
    JOIN {$invoice_items_table} ii ON i.id = ii.invoice_id
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    WHERE YEAR(COALESCE(i.payment_date, i.invoice_date)) = {$selected_year}
    AND i.status IN ('PAID', 'paid')
    {$permission_where}
    {$partner_item_condition}
    GROUP BY month_num, service_type
    ORDER BY month_num ASC, service_type ASC
";
$paid_service_monthly = $wpdb->get_results($paid_service_monthly_query);

// Total distinct paid invoices by month (for 'all') from invoice_items
$paid_all_monthly_query = "
    SELECT 
        MONTH(COALESCE(i.payment_date, i.invoice_date)) AS month_num,
        COUNT(DISTINCT i.id) AS invoice_count
    FROM {$invoices_table} i
    JOIN {$invoice_items_table} ii ON i.id = ii.invoice_id
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    WHERE YEAR(COALESCE(i.payment_date, i.invoice_date)) = {$selected_year}
    AND i.status IN ('PAID', 'paid')
    {$permission_where}
    {$partner_item_condition}
    GROUP BY month_num
    ORDER BY month_num ASC
";
$paid_all_monthly = $wpdb->get_results($paid_all_monthly_query);
$paid_all_count_map = array();
foreach ($paid_all_monthly as $row) {
    $paid_all_count_map[intval($row->month_num)] = intval($row->invoice_count);
}

// Unpaid amount & count by service type and month from invoice_items
$unpaid_service_monthly_query = "
    SELECT 
        MONTH(i.invoice_date) AS month_num,
        CASE 
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%domain%' THEN 'domain'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%host%' THEN 'hosting'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%maint%' OR LOWER(TRIM(ii.service_type)) LIKE '%bảo trì%' OR LOWER(TRIM(ii.service_type)) LIKE '%bao tri%' THEN 'maintenance'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%web%' THEN 'website_service'
            ELSE 'website_service'
        END AS service_type,
        COUNT(DISTINCT i.id) AS unpaid_count,
        SUM(ii.item_total) AS unpaid_amount
    FROM {$invoices_table} i
    JOIN {$invoice_items_table} ii ON i.id = ii.invoice_id
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    WHERE YEAR(i.invoice_date) = {$selected_year}
    AND i.status NOT IN ('PAID', 'paid', 'CANCELED', 'canceled')
    {$permission_where}
    {$partner_item_condition}
    GROUP BY month_num, service_type
    ORDER BY month_num ASC, service_type ASC
";
$unpaid_service_monthly = $wpdb->get_results($unpaid_service_monthly_query);

// Total distinct unpaid invoices by month (for 'all') from invoice_items
$unpaid_all_monthly_query = "
    SELECT 
        MONTH(i.invoice_date) AS month_num,
        COUNT(DISTINCT i.id) AS unpaid_count
    FROM {$invoices_table} i
    JOIN {$invoice_items_table} ii ON i.id = ii.invoice_id
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    WHERE YEAR(i.invoice_date) = {$selected_year}
    AND i.status NOT IN ('PAID', 'paid', 'CANCELED', 'canceled')
    {$permission_where}
    {$partner_item_condition}
    GROUP BY month_num
    ORDER BY month_num ASC
";
$unpaid_all_monthly = $wpdb->get_results($unpaid_all_monthly_query);
$unpaid_all_count_map = array();
foreach ($unpaid_all_monthly as $row) {
    $unpaid_all_count_map[intval($row->month_num)] = intval($row->unpaid_count);
}

// Commission by service type and month
$comm_partner_condition = ($partner_filter > 0) ? $wpdb->prepare(" AND c.partner_id = %d", $partner_filter) : "";
$comm_service_monthly_query = "
    SELECT 
        MONTH(c.created_at) AS month_num,
        CASE 
            WHEN LOWER(TRIM(c.service_type)) LIKE '%domain%' THEN 'domain'
            WHEN LOWER(TRIM(c.service_type)) LIKE '%host%' THEN 'hosting'
            WHEN LOWER(TRIM(c.service_type)) LIKE '%maint%' OR LOWER(TRIM(c.service_type)) LIKE '%bảo trì%' OR LOWER(TRIM(c.service_type)) LIKE '%bao tri%' THEN 'maintenance'
            WHEN LOWER(TRIM(c.service_type)) LIKE '%web%' THEN 'website_service'
            ELSE 'website_service'
        END AS service_type,
        SUM(c.commission_amount) AS commission_amount
    FROM {$commissions_table} c
    LEFT JOIN {$invoices_table} i ON c.invoice_id = i.id
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    WHERE YEAR(c.created_at) = {$selected_year}
    {$permission_where}
    {$comm_partner_condition}
    GROUP BY MONTH(c.created_at), service_type
    ORDER BY month_num ASC, service_type ASC
";
$comm_service_monthly = $wpdb->get_results($comm_service_monthly_query);

// Build service monthly matrix - Initialized with all 12 months for each service
$service_keys = array('all', 'domain', 'hosting', 'maintenance', 'website_service');
$service_monthly_matrix = array();
foreach ($service_keys as $stk) {
    $service_monthly_matrix[$stk] = array();
    for ($m = 1; $m <= 12; $m++) {
        $service_monthly_matrix[$stk][$m] = array(
            'paid_count' => 0,
            'unpaid_count' => 0,
            'paid_amount' => 0,
            'unpaid_amount' => 0,
            'total_amount' => 0,
            'commission' => 0
        );
    }
}

// Fill specific services from invoice_items
foreach ($paid_service_monthly as $row) {
    $norm_key = im_normalize_service_key($row->service_type);
    $m = intval($row->month_num);
    if (isset($service_monthly_matrix[$norm_key][$m])) {
        $service_monthly_matrix[$norm_key][$m]['paid_count'] += intval($row->invoice_count);
        $service_monthly_matrix[$norm_key][$m]['paid_amount'] += intval($row->paid_amount);
    }
}

foreach ($unpaid_service_monthly as $row) {
    $norm_key = im_normalize_service_key($row->service_type);
    $m = intval($row->month_num);
    if (isset($service_monthly_matrix[$norm_key][$m])) {
        $service_monthly_matrix[$norm_key][$m]['unpaid_count'] += intval($row->unpaid_count);
        $service_monthly_matrix[$norm_key][$m]['unpaid_amount'] += intval($row->unpaid_amount);
    }
}

foreach ($comm_service_monthly as $row) {
    $norm_key = im_normalize_service_key($row->service_type);
    $m = intval($row->month_num);
    if (isset($service_monthly_matrix[$norm_key][$m])) {
        $service_monthly_matrix[$norm_key][$m]['commission'] += intval($row->commission_amount);
    }
}

// Compute total_amount for specific services
foreach (array('domain', 'hosting', 'maintenance', 'website_service') as $stk) {
    for ($m = 1; $m <= 12; $m++) {
        $p = $service_monthly_matrix[$stk][$m]['paid_amount'];
        $u = $service_monthly_matrix[$stk][$m]['unpaid_amount'];
        $service_monthly_matrix[$stk][$m]['total_amount'] = $p + $u;
    }
}

// Fill 'all' by aggregating across all service items for each month
for ($m = 1; $m <= 12; $m++) {
    $all_paid_amount = 0;
    $all_unpaid_amount = 0;
    $all_commission = 0;

    foreach (array('domain', 'hosting', 'maintenance', 'website_service') as $stk) {
        $all_paid_amount += $service_monthly_matrix[$stk][$m]['paid_amount'];
        $all_unpaid_amount += $service_monthly_matrix[$stk][$m]['unpaid_amount'];
        $all_commission += $service_monthly_matrix[$stk][$m]['commission'];
    }

    $service_monthly_matrix['all'][$m] = array(
        'paid_count' => isset($paid_all_count_map[$m]) ? $paid_all_count_map[$m] : 0,
        'unpaid_count' => isset($unpaid_all_count_map[$m]) ? $unpaid_all_count_map[$m] : 0,
        'paid_amount' => $all_paid_amount,
        'unpaid_amount' => $all_unpaid_amount,
        'total_amount' => $all_paid_amount + $all_unpaid_amount,
        'commission' => $all_commission
    );
}

// Get paid amount by service type for the year
$service_type_stats = $wpdb->get_results("
    SELECT 
        CASE 
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%domain%' THEN 'domain'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%host%' THEN 'hosting'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%maint%' OR LOWER(TRIM(ii.service_type)) LIKE '%bảo trì%' OR LOWER(TRIM(ii.service_type)) LIKE '%bao tri%' THEN 'maintenance'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%web%' THEN 'website_service'
            ELSE LOWER(TRIM(COALESCE(ii.service_type, '')))
        END AS service_type,
        SUM(ii.item_total) AS service_paid_amount
    FROM {$invoices_table} i
    JOIN {$invoice_items_table} ii ON i.id = ii.invoice_id
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    WHERE YEAR(i.payment_date) = {$selected_year}
    AND i.status IN ('PAID', 'paid')
    AND i.payment_date IS NOT NULL
    {$permission_where}
    " . ($partner_filter > 0 ? $wpdb->prepare("AND i.partner_id = %d", $partner_filter) : "") . "
    GROUP BY service_type
    ORDER BY service_type ASC
");

// Get total statistics for the year
$total_stats = $wpdb->get_row("
    SELECT 
        COUNT(i.id) AS total_invoices,
        SUM(i.paid_amount) AS total_year_revenue,
        SUM(i.discount_total) AS total_discount,
        AVG(i.total_amount) AS avg_invoice_amount,
        COUNT(DISTINCT i.user_id) AS unique_customers
    FROM {$invoices_table} i
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    {$where_clause}
");

// Get outstanding invoices (excluding PAID and CANCELED, and applying partner filter)
$outstanding_where = "WHERE YEAR(i.invoice_date) = {$selected_year}
                      AND i.status NOT IN ('PAID', 'paid', 'CANCELED', 'canceled')
                      {$permission_where}";
if ($partner_filter > 0) {
    $outstanding_where .= $wpdb->prepare(" AND i.partner_id = %d", $partner_filter);
}

$outstanding_stats = $wpdb->get_row("
    SELECT 
        COUNT(i.id) AS outstanding_invoices,
        SUM(i.total_amount - COALESCE(i.paid_amount, 0)) AS outstanding_amount
    FROM {$invoices_table} i
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    {$outstanding_where}
");

// Get canceled invoices statistics for the year (Sụt giảm doanh thu)
$canceled_where = "WHERE YEAR(i.invoice_date) = {$selected_year}
                   AND i.status IN ('CANCELED', 'canceled')
                   {$permission_where}";
if ($partner_filter > 0) {
    $canceled_where .= $wpdb->prepare(" AND i.partner_id = %d", $partner_filter);
}

$canceled_stats = $wpdb->get_row("
    SELECT 
        COUNT(i.id) AS canceled_invoices,
        SUM(i.total_amount) AS total_canceled_amount
    FROM {$invoices_table} i
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    {$canceled_where}
");

// Calculate average revenue per month
$current_year_real = date('Y');
$current_month_real = date('n');

if ($selected_year < $current_year_real) {
    $num_months = 12;
} elseif ($selected_year == $current_year_real) {
    $num_months = $current_month_real;
} else {
    $num_months = 1;
}
$avg_monthly_revenue = ($total_stats->total_year_revenue ?? 0) / $num_months;

// Get unique service types and prepare service type map
$service_type_map = array(
    'domain' => 'Tên Miền',
    'hosting' => 'Hosting',
    'maintenance' => 'Bảo Trì',
    'website_service' => 'Dịch Vụ Website'
);

$service_types = array();
foreach ($service_type_stats as $stat) {
    if ($stat->service_type && $stat->service_paid_amount > 0) {
        $service_types[$stat->service_type] = true;
    }
}
$service_types = array_keys($service_types);

// Get partners list for filter - Based on payment_date
$partners = $wpdb->get_results("
    SELECT DISTINCT i.partner_id, u.name, u.user_code
    FROM {$invoices_table} i
    LEFT JOIN {$users_table} u ON i.partner_id = u.id
    WHERE i.partner_id IS NOT NULL
    AND YEAR(i.payment_date) = {$selected_year}
    AND i.status = 'PAID'
    AND i.payment_date IS NOT NULL
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
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted">Trung Bình/Tháng</div>
                            <div class="fs-5 fw-bold">
                                <?php echo number_format(intval($avg_monthly_revenue)); ?> ₫
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted">Số Lượng Khách Hàng</div>
                            <div class="fs-5 fw-bold">
                                <?php echo intval($total_stats->unique_customers ?? 0); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted">Tổng Chiết Khấu</div>
                            <div class="fs-5 fw-bold text-danger">
                                <?php echo number_format(intval($total_stats->total_discount ?? 0)); ?> ₫
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted d-flex align-items-center justify-content-between" title="Tổng giá trị các hóa đơn đã bị hủy trong năm <?php echo esc_html($selected_year); ?>">
                                <span>Sụt Giảm Doanh Thu Năm Nay</span>
                            </div>
                            <div class="fs-5 fw-bold text-danger">
                                <?php echo number_format(intval($canceled_stats->total_canceled_amount ?? 0)); ?> ₫
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

            <!-- Service Type Summary Cards -->
            <div class="row mt-4">
                <?php 
                $service_colors = array(
                    'website_service' => array('color' => 'text-info', 'bg' => 'border-info'),
                    'domain' => array('color' => 'text-primary', 'bg' => 'border-primary'),
                    'hosting' => array('color' => 'text-success', 'bg' => 'border-success'),
                    'maintenance' => array('color' => 'text-warning', 'bg' => 'border-warning')
                );
                
                foreach ($service_types as $st):
                    $stat = null;
                    foreach ($service_type_stats as $s) {
                        if ($s->service_type == $st) {
                            $stat = $s;
                            break;
                        }
                    }
                    $amount = $stat ? intval($stat->service_paid_amount) : 0;
                    $colors = isset($service_colors[$st]) ? $service_colors[$st] : array('color' => 'text-secondary', 'bg' => 'border-secondary');
                ?>
                <div class="col-md-3">
                    <div class="card <?php echo $colors['bg']; ?>">
                        <div class="card-body">
                            <div class="text-muted"><?php echo isset($service_type_map[$st]) ? $service_type_map[$st] : $st; ?></div>
                            <div class="fs-4 fw-bold <?php echo $colors['color']; ?>">
                                <?php echo number_format($amount); ?> ₫
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <style>
            .service-filter-menu {
                display: inline-flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 4px;
            }
            .service-filter-btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 6px !important;
                vertical-align: middle !important;
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                outline: none !important;
                color: #6c757d !important;
                padding: 6px 12px !important;
                font-size: 0.875rem !important;
                font-weight: 500 !important;
                line-height: 1 !important;
                border-radius: 9px !important;
                cursor: pointer !important;
                transition: all 0.2s ease-in-out !important;
                text-decoration: none !important;
            }
            .service-filter-btn i {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 1.05rem !important;
                line-height: 1 !important;
                vertical-align: middle !important;
            }
            .service-filter-btn span {
                display: inline-flex !important;
                align-items: center !important;
                line-height: 1 !important;
                vertical-align: middle !important;
            }
            .service-filter-btn:hover {
                color: #333333 !important;
                background-color: rgba(108, 117, 125, 0.12) !important;
            }
            .service-filter-btn.active {
                background-color: #6c757d !important;
                color: #ffffff !important;
                font-weight: 600 !important;
            }
            .service-filter-btn.active:hover {
                background-color: #5c636a !important;
                color: #ffffff !important;
            }
            .service-filter-btn.active i,
            .service-filter-btn.active span {
                color: #ffffff !important;
            }
            </style>

            <!-- Monthly Table -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="card-title mb-0 d-flex align-items-center">
                        <i class="ph ph-calendar-blank me-2"></i>Chi Tiết Theo Tháng
                    </h4>
                    <!-- Horizontal Service Filter Menu -->
                    <div class="service-filter-menu" role="tablist" aria-label="Lọc theo loại dịch vụ">
                        <button type="button" class="service-filter-btn border-radius-9 <?php echo ($selected_service === 'all') ? 'active' : ''; ?>" data-service="all">
                            <i class="ph ph-squares-four"></i><span>Tất cả</span>
                        </button>
                        <button type="button" class="service-filter-btn border-radius-9 <?php echo ($selected_service === 'domain') ? 'active' : ''; ?>" data-service="domain">
                            <i class="ph ph-globe"></i><span>Tên Miền</span>
                        </button>
                        <button type="button" class="service-filter-btn border-radius-9 <?php echo ($selected_service === 'hosting') ? 'active' : ''; ?>" data-service="hosting">
                            <i class="ph ph-hard-drives"></i><span>Hosting</span>
                        </button>
                        <button type="button" class="service-filter-btn border-radius-9 <?php echo ($selected_service === 'maintenance') ? 'active' : ''; ?>" data-service="maintenance">
                            <i class="ph ph-wrench"></i><span>Bảo Trì</span>
                        </button>
                        <button type="button" class="service-filter-btn border-radius-9 <?php echo ($selected_service === 'website_service') ? 'active' : ''; ?>" data-service="website_service">
                            <i class="ph ph-browsers"></i><span>Dịch Vụ Website</span>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="monthly-stats-table">
                        <thead>
                            <tr class="table-light">
                                <th>Tháng</th>
                                <th class="text-center">Số HĐ</th>
                                <th class="text-end">Tổng Tiền</th>
                                <th class="text-end">Đã TT</th>
                                <th class="text-end">Chưa TT</th>
                                <th class="text-end">Hoa Hồng</th>
                                <th class="text-center">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody id="monthly-stats-tbody">
                            <?php 
                            $curr_matrix = isset($service_monthly_matrix[$selected_service]) ? $service_monthly_matrix[$selected_service] : $service_monthly_matrix['all'];
                            $grand_paid_count = 0;
                            $grand_total = 0;
                            $grand_paid = 0;
                            $grand_unpaid = 0;
                            $grand_commission = 0;

                            for ($m = 1; $m <= 12; $m++):
                                $row = $curr_matrix[$m];
                                $p_cnt = $row['paid_count'];
                                $p_amt = $row['paid_amount'];
                                $u_amt = $row['unpaid_amount'];
                                $tot_amt = $row['total_amount'];
                                $comm_amt = $row['commission'];

                                $grand_paid_count += $p_cnt;
                                $grand_total += $tot_amt;
                                $grand_paid += $p_amt;
                                $grand_unpaid += $u_amt;
                                $grand_commission += $comm_amt;
                            ?>
                            <tr>
                                <td class="fw-bold"><?php echo esc_html($month_names[$m]); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-danger border-radius-9 px-2 py-1"><?php echo $p_cnt; ?></span>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php echo number_format($tot_amt); ?> ₫
                                </td>
                                <td class="text-end text-success fw-bold">
                                    <?php echo number_format($p_amt); ?> ₫
                                </td>
                                <td class="text-end text-warning fw-bold">
                                    <?php echo number_format($u_amt); ?> ₫
                                </td>
                                <td class="text-end text-danger fw-bold">
                                    <?php echo number_format($comm_amt); ?> ₫
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn bg-success text-white border-success view-month-details d-flex align-items-center gap-1" data-month="<?php echo $m; ?>" data-year="<?php echo $selected_year; ?>" title="Xem hóa đơn đã thanh toán">
                                            <i class="ph ph-eye"></i> Đã TT
                                        </button>
                                        <button class="btn bg-light-warning text-dark border-dark view-month-unpaid d-flex align-items-center gap-1" data-month="<?php echo $m; ?>" data-year="<?php echo $selected_year; ?>" title="Xem hóa đơn chưa thanh toán">
                                            <i class="ph ph-eye"></i> Chưa TT
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                        <tfoot id="monthly-stats-tfoot" class="table-light fw-bold border-top">
                            <tr>
                                <td>Tổng cộng cả năm</td>
                                <td class="text-center"><span class="badge bg-danger border-radius-9 px-2 py-1"><?php echo $grand_paid_count; ?></span></td>
                                <td class="text-end text-primary fs-5"><?php echo number_format($grand_total); ?> ₫</td>
                                <td class="text-end text-success fs-5"><?php echo number_format($grand_paid); ?> ₫</td>
                                <td class="text-end text-warning fs-5"><?php echo number_format($grand_unpaid); ?> ₫</td>
                                <td class="text-end text-danger fs-5"><?php echo number_format($grand_commission); ?> ₫</td>
                                <td class="text-center text-muted">-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Data for JavaScript - Chart shows: Đã TT (tổng), Chưa TT, Hoa Hồng (Đồng bộ trực tiếp từ ma trận tổng hợp invoice_items)
$chart_data_paid = array();
$chart_data_unpaid = array();
$chart_data_commission = array();
$labels = array();

for ($m = 1; $m <= 12; $m++) {
    $labels[] = 'T' . $m;
    $chart_data_paid[] = $service_monthly_matrix['all'][$m]['paid_amount'];
    $chart_data_unpaid[] = $service_monthly_matrix['all'][$m]['unpaid_amount'];
    $chart_data_commission[] = -$service_monthly_matrix['all'][$m]['commission'];
}
?>

<script>
// Data for chart
const chartLabels = <?php echo json_encode($labels); ?>;
const chartDataPaid = <?php echo json_encode($chart_data_paid); ?>;
const chartDataUnpaid = <?php echo json_encode($chart_data_unpaid); ?>;
const chartDataCommission = <?php echo json_encode($chart_data_commission); ?>;

// Data for service monthly breakdown
const serviceMonthlyMatrix = <?php echo json_encode($service_monthly_matrix); ?>;
const monthNames = <?php echo json_encode($month_names); ?>;
const selectedYear = <?php echo json_encode($selected_year); ?>;

function formatCurrency(val) {
    return new Intl.NumberFormat('vi-VN').format(val || 0);
}

function renderMonthlyTable(serviceKey) {
    const $ = window.jQuery || window.$;
    const data = (serviceMonthlyMatrix && serviceMonthlyMatrix[serviceKey]) ? serviceMonthlyMatrix[serviceKey] : serviceMonthlyMatrix['all'];
    let tbodyHtml = '';
    let grandPaidCount = 0;
    let grandTotal = 0;
    let grandPaid = 0;
    let grandUnpaid = 0;
    let grandCommission = 0;

    for (let m = 1; m <= 12; m++) {
        const row = (data && data[m]) ? data[m] : { paid_count: 0, unpaid_count: 0, paid_amount: 0, unpaid_amount: 0, total_amount: 0, commission: 0 };
        grandPaidCount += row.paid_count;
        grandTotal += row.total_amount;
        grandPaid += row.paid_amount;
        grandUnpaid += row.unpaid_amount;
        grandCommission += row.commission;

        const mName = (monthNames && monthNames[m]) ? monthNames[m] : ('Tháng ' + m);

        tbodyHtml += `
            <tr>
                <td class="fw-bold">${mName}</td>
                <td class="text-center">
                    <span class="badge bg-danger border-radius-9 px-2 py-1">${row.paid_count}</span>
                </td>
                <td class="text-end fw-bold">${formatCurrency(row.total_amount)} ₫</td>
                <td class="text-end text-success fw-bold">${formatCurrency(row.paid_amount)} ₫</td>
                <td class="text-end text-warning fw-bold">${formatCurrency(row.unpaid_amount)} ₫</td>
                <td class="text-end text-danger fw-bold">${formatCurrency(row.commission)} ₫</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        <button class="btn bg-success text-white border-success view-month-details d-flex align-items-center gap-1" data-month="${m}" data-year="${selectedYear}" title="Xem hóa đơn đã thanh toán">
                            <i class="ph ph-eye"></i> Đã TT
                        </button>
                        <button class="btn bg-light-warning text-dark border-dark view-month-unpaid d-flex align-items-center gap-1" data-month="${m}" data-year="${selectedYear}" title="Xem hóa đơn chưa thanh toán">
                            <i class="ph ph-eye"></i> Chưa TT
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    if (window.jQuery) {
        window.jQuery('#monthly-stats-tbody').html(tbodyHtml);
    } else {
        const tbodyEl = document.getElementById('monthly-stats-tbody');
        if (tbodyEl) tbodyEl.innerHTML = tbodyHtml;
    }

    const tfootHtml = `
        <tr>
            <td>Tổng cộng cả năm</td>
            <td class="text-center"><span class="badge bg-danger border-radius-9 px-2 py-1">${grandPaidCount}</span></td>
            <td class="text-end text-primary fs-5">${formatCurrency(grandTotal)} ₫</td>
            <td class="text-end text-success fs-5">${formatCurrency(grandPaid)} ₫</td>
            <td class="text-end text-warning fs-5">${formatCurrency(grandUnpaid)} ₫</td>
            <td class="text-end text-danger fs-5">${formatCurrency(grandCommission)} ₫</td>
            <td class="text-center text-muted">-</td>
        </tr>
    `;

    if (window.jQuery) {
        window.jQuery('#monthly-stats-tfoot').html(tfootHtml);
    } else {
        const tfootEl = document.getElementById('monthly-stats-tfoot');
        if (tfootEl) tfootEl.innerHTML = tfootHtml;
    }
}

jQuery(document).ready(function($) {
    // Horizontal service filter button click
    $(document).on('click', '.service-filter-btn', function(e) {
        e.preventDefault();
        const service = $(this).data('service') || 'all';
        
        // Update active button state
        $('.service-filter-btn').removeClass('active');
        $(this).addClass('active');

        // Re-render table data immediately
        renderMonthlyTable(service);

        // Update URL query parameter without page reload
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            if (service === 'all') {
                url.searchParams.delete('service_type');
            } else {
                url.searchParams.set('service_type', service);
            }
            window.history.replaceState({}, '', url.toString());
        }
    });

    // Initialize Chart.js if available
    if (typeof Chart !== 'undefined') {
        const ctx = document.getElementById('revenue-chart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [
                        {
                            label: 'Đã Thanh Toán (VNĐ)',
                            data: chartDataPaid,
                            backgroundColor: 'rgba(75, 192, 75, 0.6)',
                            borderColor: 'rgba(75, 192, 75, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Chưa Thanh Toán (VNĐ)',
                            data: chartDataUnpaid,
                            backgroundColor: 'rgba(255, 159, 64, 0.6)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Hoa Hồng - Chi Phí (VNĐ)',
                            data: chartDataCommission,
                            backgroundColor: 'rgba(220, 53, 69, 0.6)',
                            borderColor: 'rgba(220, 53, 69, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            stacked: true
                        },
                        y: {
                            stacked: true,
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
        const service = $('.service-filter-btn.active').data('service') || 'all';
        let url = '<?php echo home_url('/revenue-stats/'); ?>?year=' + year + '&partner=' + partner;
        if (service && service !== 'all') {
            url += '&service_type=' + service;
        }
        window.location.href = url;
    });

    // Partner filter change
    $('#partner-filter').on('change', function() {
        const partner = $(this).val();
        const year = $('#year-select').val();
        const service = $('.service-filter-btn.active').data('service') || 'all';
        let url = '<?php echo home_url('/revenue-stats/'); ?>?year=' + year + '&partner=' + partner;
        if (service && service !== 'all') {
            url += '&service_type=' + service;
        }
        window.location.href = url;
    });

    // View month details - Format: d/m/Y with correct last day of month
    $(document).on('click', '.view-month-details', function() {
        const month = $(this).data('month');
        const year = $(this).data('year');
        // Get last day of month (handles Feb 28/29, 30-day months, etc.)
        const lastDay = new Date(year, month, 0).getDate();
        // Pad month with leading zero if needed
        const paddedMonth = String(month).padStart(2, '0');
        window.location.href = '<?php echo home_url('/list-invoice/'); ?>?date_from=01/' + paddedMonth + '/' + year + 
                               '&date_to=' + lastDay + '/' + paddedMonth + '/' + year + '&status=PAID';
    });

    // View unpaid invoices - Format: d/m/Y with correct last day of month
    $(document).on('click', '.view-month-unpaid', function() {
        const month = $(this).data('month');
        const year = $(this).data('year');
        // Get last day of month (handles Feb 28/29, 30-day months, etc.)
        const lastDay = new Date(year, month, 0).getDate();
        // Pad month with leading zero if needed
        const paddedMonth = String(month).padStart(2, '0');
        window.location.href = '<?php echo home_url('/list-invoice/'); ?>?date_from=01/' + paddedMonth + '/' + year + 
                               '&date_to=' + lastDay + '/' + paddedMonth + '/' + year + '&status_type=unpaid';
    });
});
</script>

<?php
get_footer();
?>
