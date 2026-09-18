<?php
/*
    Template Name: Service Revenue Report
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

// Get filter parameters
$year_param = get_query_var('year') ?: (isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '');
$current_year = intval(date('Y'));
$selected_year = !empty($year_param) ? intval($year_param) : $current_year;

// Validate year (allow last 5 years to current + 1)
$min_year = $current_year - 5;
$max_year = $current_year + 1;
if ($selected_year < $min_year || $selected_year > $max_year) {
    $selected_year = $current_year;
}

// Permission where clause
$permission_where = get_user_permission_where_clause('u', 'id');

// 1. Query paid invoice items grouped by month and service type
$service_items_query = "
    SELECT 
        MONTH(COALESCE(i.payment_date, i.invoice_date)) AS month_num,
        CASE 
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%domain%' OR LOWER(TRIM(ii.service_type)) LIKE '%tên miền%' OR LOWER(TRIM(ii.service_type)) LIKE '%ten mien%' THEN 'domain'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%host%' THEN 'hosting'
            WHEN LOWER(TRIM(ii.service_type)) LIKE '%maint%' OR LOWER(TRIM(ii.service_type)) LIKE '%bảo trì%' OR LOWER(TRIM(ii.service_type)) LIKE '%bao tri%' THEN 'maintenance'
            ELSE 'website_service'
        END AS norm_service,
        SUM(ii.item_total) AS total_item_rev
    FROM {$invoices_table} i
    JOIN {$invoice_items_table} ii ON i.id = ii.invoice_id
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    WHERE YEAR(COALESCE(i.payment_date, i.invoice_date)) = {$selected_year}
    AND i.status IN ('PAID', 'paid')
    {$permission_where}
    GROUP BY month_num, norm_service
";
$service_items_results = $wpdb->get_results($service_items_query);

$service_matrix = array();
foreach ($service_items_results as $row) {
    $m = intval($row->month_num);
    $st = $row->norm_service;
    if (!isset($service_matrix[$m])) {
        $service_matrix[$m] = array();
    }
    $service_matrix[$m][$st] = floatval($row->total_item_rev);
}

// 2. Query partner commissions grouped by month
$commission_query = "
    SELECT 
        MONTH(c.created_at) AS month_num,
        SUM(c.commission_amount) AS total_commission
    FROM {$commissions_table} c
    LEFT JOIN {$invoices_table} i ON c.invoice_id = i.id
    LEFT JOIN {$users_table} u ON i.user_id = u.id
    WHERE YEAR(c.created_at) = {$selected_year}
    {$permission_where}
    GROUP BY month_num
";
$commission_results = $wpdb->get_results($commission_query);

$commission_matrix = array();
foreach ($commission_results as $row) {
    $m = intval($row->month_num);
    $commission_matrix[$m] = floatval($row->total_commission);
}

// 3. Compile 12-month report data
$report_data = array();
$totals = array(
    'domain' => 0,
    'hosting' => 0,
    'maintenance' => 0,
    'website' => 0,
    'total_revenue' => 0,
    'commission' => 0,
    'labor_cost' => 0,
    'net_profit' => 0,
);

for ($m = 1; $m <= 12; $m++) {
    $domain = isset($service_matrix[$m]['domain']) ? $service_matrix[$m]['domain'] : 0;
    $hosting = isset($service_matrix[$m]['hosting']) ? $service_matrix[$m]['hosting'] : 0;
    $maintenance = isset($service_matrix[$m]['maintenance']) ? $service_matrix[$m]['maintenance'] : 0;
    $website = isset($service_matrix[$m]['website_service']) ? $service_matrix[$m]['website_service'] : 0;

    // Total service revenue (Pre-VAT)
    $month_rev = $domain + $hosting + $maintenance + $website;

    // Commission paid to partners
    $month_comm = isset($commission_matrix[$m]) ? $commission_matrix[$m] : 0;

    // Labor cost = 70% Website + 30% Maintenance + 15% Hosting (Domain excluded)
    $month_labor = (0.70 * $website) + (0.30 * $maintenance) + (0.15 * $hosting);

    // Net profit = Total Revenue - Commission - Labor Cost
    $month_net = $month_rev - $month_comm - $month_labor;

    $report_data[$m] = array(
        'domain' => $domain,
        'hosting' => $hosting,
        'maintenance' => $maintenance,
        'website' => $website,
        'total_revenue' => $month_rev,
        'commission' => $month_comm,
        'labor_cost' => $month_labor,
        'net_profit' => $month_net,
    );

    $totals['domain'] += $domain;
    $totals['hosting'] += $hosting;
    $totals['maintenance'] += $maintenance;
    $totals['website'] += $website;
    $totals['total_revenue'] += $month_rev;
    $totals['commission'] += $month_comm;
    $totals['labor_cost'] += $month_labor;
    $totals['net_profit'] += $month_net;
}

// Handle server-side direct CSV export if requested
if (isset($_GET['export']) && in_array($_GET['export'], array('csv', 'excel'))) {
    $filename = 'Bao-cao-doanh-thu-dich-vu-' . $selected_year . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    fputcsv($output, array('BÁO CÁO DOANH THU DỊCH VỤ THEO THÁNG - NĂM ' . $selected_year));
    fputcsv($output, array('Đơn vị tính: VNĐ (Doanh thu trước thuế VAT)'));
    fputcsv($output, array());
    fputcsv($output, array(
        'Tháng',
        'Tên Miền',
        'Hosting',
        'Bảo Trì',
        'Dịch Vụ Website',
        'Tổng Doanh Thu',
        'Hoa Hồng Đối Tác',
        'CP Nhân Công',
        'Lợi Nhuận Còn Lại'
    ));

    for ($m = 1; $m <= 12; $m++) {
        $r = $report_data[$m];
        fputcsv($output, array(
            'Tháng ' . $m,
            $r['domain'],
            $r['hosting'],
            $r['maintenance'],
            $r['website'],
            $r['total_revenue'],
            $r['commission'] ? -$r['commission'] : 0,
            $r['labor_cost'] ? -$r['labor_cost'] : 0,
            $r['net_profit']
        ));
    }

    fputcsv($output, array(
        'TỔNG CỘNG',
        $totals['domain'],
        $totals['hosting'],
        $totals['maintenance'],
        $totals['website'],
        $totals['total_revenue'],
        $totals['commission'] ? -$totals['commission'] : 0,
        $totals['labor_cost'] ? -$totals['labor_cost'] : 0,
        $totals['net_profit']
    ));

    fclose($output);
    exit;
}

get_header();
?>

<div class="main-panel">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="page-header d-print-none mb-3">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title d-flex align-items-center">
                        <i class="ph ph-table me-2 text-primary" style="font-size: 1.5rem;"></i>
                        <span>Báo Cáo Doanh Thu Dịch Vụ Theo Tháng</span>
                    </h2>
                    <p class="text-muted mb-0 small">
                        Chi tiết doanh thu thuần (trừ VAT) theo từng loại sản phẩm/dịch vụ, hoa hồng đối tác và chi phí nhân công (70% Website + 30% Bảo trì + 15% Hosting).
                    </p>
                </div>
                <div class="col-auto d-flex align-items-center gap-2">
                    <label for="year-select" class="form-label mb-0 fw-semibold text-nowrap">Năm:</label>
                    <select id="year-select" class="form-select form-select-sm" style="min-width: 110px;">
                        <?php
                        for ($y = $min_year; $y <= $max_year; $y++) {
                            $selected = ($y == $selected_year) ? 'selected' : '';
                            echo "<option value=\"{$y}\" {$selected}>{$y}</option>";
                        }
                        ?>
                    </select>
                    <button type="button" id="btn-export-excel" class="btn btn-sm btn-success d-inline-flex align-items-center shadow-sm">
                        <i class="ph ph-file-xls me-1" style="font-size: 1.15rem;"></i> Xuất Excel
                    </button>
                </div>
            </div>
        </div>

        <div class="page-wrapper">
            <!-- 4 Top KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm shadow-sm border-0" style="border-radius: 12px; background: #fff;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-primary-subtle text-primary p-2 rounded-3 me-3" style="font-size: 1.25rem;">
                                    <i class="ph ph-money"></i>
                                </span>
                                <div>
                                    <div class="text-muted small">Tổng Doanh Thu (Trừ VAT)</div>
                                    <div class="fs-5 fw-bold text-dark">
                                        <?php echo number_format($totals['total_revenue'], 0, ',', '.'); ?> ₫
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm shadow-sm border-0" style="border-radius: 12px; background: #fff;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-danger-subtle text-danger p-2 rounded-3 me-3" style="font-size: 1.25rem;">
                                    <i class="ph ph-users-three"></i>
                                </span>
                                <div>
                                    <div class="text-muted small">Hoa Hồng Đối Tác</div>
                                    <div class="fs-5 fw-bold text-danger">
                                        <?php echo number_format($totals['commission'], 0, ',', '.'); ?> ₫
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm shadow-sm border-0" style="border-radius: 12px; background: #fff;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-warning-subtle text-warning p-2 rounded-3 me-3" style="font-size: 1.25rem;">
                                    <i class="ph ph-briefcase"></i>
                                </span>
                                <div>
                                    <div class="text-muted small" title="70% Website + 30% Bảo trì + 15% Hosting">Chi Phí Nhân Công</div>
                                    <div class="fs-5 fw-bold text-warning">
                                        <?php echo number_format($totals['labor_cost'], 0, ',', '.'); ?> ₫
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm shadow-sm border-0" style="border-radius: 12px; background: #fff;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-success-subtle text-success p-2 rounded-3 me-3" style="font-size: 1.25rem;">
                                    <i class="ph ph-trend-up"></i>
                                </span>
                                <div>
                                    <div class="text-muted small">Lợi Nhuận Còn Lại</div>
                                    <div class="fs-5 fw-bold text-success">
                                        <?php echo number_format($totals['net_profit'], 0, ',', '.'); ?> ₫
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Single Consolidated Table -->
            <div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        Chi Tiết Doanh Thu & Chi Phí 12 Tháng - Năm <?php echo esc_html($selected_year); ?>
                    </h5>
                    <span class="text-muted small">Đơn vị: VNĐ</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover card-table mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="text-uppercase text-secondary" style="font-size: 0.78rem; letter-spacing: 0.5px;">
                                <th class="text-center py-3" style="width: 100px;">Tháng</th>
                                <th class="text-end py-3">Tên Miền</th>
                                <th class="text-end py-3">Hosting</th>
                                <th class="text-end py-3">Bảo Trì</th>
                                <th class="text-end py-3">Dịch Vụ Website</th>
                                <th class="text-end py-3 bg-light-subtle fw-bold text-primary">Tổng Doanh Thu</th>
                                <th class="text-end py-3 text-danger">Hoa Hồng Đối Tác</th>
                                <th class="text-end py-3 text-warning" title="70% Website + 30% Bảo Trì + 15% Hosting">CP Nhân Công</th>
                                <th class="text-end py-3 text-success fw-bold">Lợi Nhuận Còn Lại</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($m = 1; $m <= 12; $m++): 
                                $row = $report_data[$m];
                                $has_activity = ($row['total_revenue'] > 0 || $row['commission'] > 0);
                            ?>
                            <tr class="<?php echo ($m % 2 == 0) ? 'bg-light-subtle' : ''; ?>">
                                <td class="text-center fw-semibold text-dark">
                                    Tháng <?php echo $m; ?>
                                </td>
                                <td class="text-end text-muted">
                                    <?php echo $row['domain'] > 0 ? number_format($row['domain'], 0, ',', '.') . ' ₫' : '<span class="text-black-50">-</span>'; ?>
                                </td>
                                <td class="text-end text-muted">
                                    <?php echo $row['hosting'] > 0 ? number_format($row['hosting'], 0, ',', '.') . ' ₫' : '<span class="text-black-50">-</span>'; ?>
                                </td>
                                <td class="text-end text-muted">
                                    <?php echo $row['maintenance'] > 0 ? number_format($row['maintenance'], 0, ',', '.') . ' ₫' : '<span class="text-black-50">-</span>'; ?>
                                </td>
                                <td class="text-end text-muted">
                                    <?php echo $row['website'] > 0 ? number_format($row['website'], 0, ',', '.') . ' ₫' : '<span class="text-black-50">-</span>'; ?>
                                </td>
                                <td class="text-end fw-bold text-primary bg-light-subtle">
                                    <?php echo $row['total_revenue'] > 0 ? number_format($row['total_revenue'], 0, ',', '.') . ' ₫' : '<span class="text-black-50">-</span>'; ?>
                                </td>
                                <td class="text-end text-danger">
                                    <?php echo $row['commission'] > 0 ? '-' . number_format($row['commission'], 0, ',', '.') . ' ₫' : '<span class="text-black-50">-</span>'; ?>
                                </td>
                                <td class="text-end text-warning">
                                    <?php echo $row['labor_cost'] > 0 ? '-' . number_format($row['labor_cost'], 0, ',', '.') . ' ₫' : '<span class="text-black-50">-</span>'; ?>
                                </td>
                                <td class="text-end fw-bold <?php echo ($row['net_profit'] < 0) ? 'text-danger' : 'text-success'; ?>">
                                    <?php 
                                    if ($row['net_profit'] != 0) {
                                        echo number_format($row['net_profit'], 0, ',', '.') . ' ₫';
                                    } else {
                                        echo '<span class="text-black-50">-</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                        <tfoot class="table-light border-top border-2">
                            <tr class="fw-bold" style="font-size: 0.95rem;">
                                <td class="text-center text-dark py-3">TỔNG CỘNG</td>
                                <td class="text-end text-dark py-3">
                                    <?php echo number_format($totals['domain'], 0, ',', '.'); ?> ₫
                                </td>
                                <td class="text-end text-dark py-3">
                                    <?php echo number_format($totals['hosting'], 0, ',', '.'); ?> ₫
                                </td>
                                <td class="text-end text-dark py-3">
                                    <?php echo number_format($totals['maintenance'], 0, ',', '.'); ?> ₫
                                </td>
                                <td class="text-end text-dark py-3">
                                    <?php echo number_format($totals['website'], 0, ',', '.'); ?> ₫
                                </td>
                                <td class="text-end text-primary py-3 bg-light-subtle">
                                    <?php echo number_format($totals['total_revenue'], 0, ',', '.'); ?> ₫
                                </td>
                                <td class="text-end text-danger py-3">
                                    -<?php echo number_format($totals['commission'], 0, ',', '.'); ?> ₫
                                </td>
                                <td class="text-end text-warning py-3">
                                    -<?php echo number_format($totals['labor_cost'], 0, ',', '.'); ?> ₫
                                </td>
                                <td class="text-end text-success py-3" style="font-size: 1.05rem;">
                                    <?php echo number_format($totals['net_profit'], 0, ',', '.'); ?> ₫
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer bg-white text-muted small py-3 px-4 border-top">
                    <div class="row">
                        <div class="col-md-8">
                            <i class="ph ph-info me-1"></i>
                            <strong>Ghi chú công thức tính:</strong>
                            <ul class="mb-0 ps-3 mt-1">
                                <li><strong>Doanh thu từng dịch vụ:</strong> Tính theo số tiền trước thuế VAT từ các hóa đơn đã thanh toán (PAID).</li>
                                <li><strong>Chi phí nhân công:</strong> = <code>70% × Dịch Vụ Website + 30% × Bảo Trì + 15% × Hosting</code> (loại trừ dịch vụ Tên Miền).</li>
                                <li><strong>Lợi nhuận còn lại:</strong> = <code>Tổng Doanh Thu - Hoa Hồng Đối Tác - Chi Phí Nhân Công</code>.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo get_template_directory_uri(); ?>/assets/vendors/js/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var yearSelect = document.getElementById('year-select');
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            var selectedYear = this.value;
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('year', selectedYear);
            window.location.href = currentUrl.toString();
        });
    }

    var reportData = <?php echo json_encode(array_values($report_data)); ?>;
    var reportTotals = <?php echo json_encode($totals); ?>;
    var selectedYear = <?php echo json_encode($selected_year); ?>;

    var btnExport = document.getElementById('btn-export-excel');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            if (typeof XLSX === 'undefined') {
                // Fallback to backend export if SheetJS hasn't loaded
                window.location.href = window.location.pathname + '?export=csv&year=' + selectedYear;
                return;
            }

            var headers = [
                ["BÁO CÁO DOANH THU DỊCH VỤ THEO THÁNG - NĂM " + selectedYear],
                ["Đơn vị tính: VNĐ (Doanh thu trước thuế VAT)"],
                [],
                [
                    "Tháng",
                    "Tên Miền",
                    "Hosting",
                    "Bảo Trì",
                    "Dịch Vụ Website",
                    "Tổng Doanh Thu",
                    "Hoa Hồng Đối Tác",
                    "CP Nhân Công",
                    "Lợi Nhuận Còn Lại"
                ]
            ];

            var rows = [];
            for (var m = 1; m <= 12; m++) {
                var item = reportData[m - 1] || {};
                rows.push([
                    "Tháng " + m,
                    Number(item.domain || 0),
                    Number(item.hosting || 0),
                    Number(item.maintenance || 0),
                    Number(item.website || 0),
                    Number(item.total_revenue || 0),
                    item.commission ? -Number(item.commission) : 0,
                    item.labor_cost ? -Number(item.labor_cost) : 0,
                    Number(item.net_profit || 0)
                ]);
            }

            var totalRow = [
                "TỔNG CỘNG",
                Number(reportTotals.domain || 0),
                Number(reportTotals.hosting || 0),
                Number(reportTotals.maintenance || 0),
                Number(reportTotals.website || 0),
                Number(reportTotals.total_revenue || 0),
                reportTotals.commission ? -Number(reportTotals.commission) : 0,
                reportTotals.labor_cost ? -Number(reportTotals.labor_cost) : 0,
                Number(reportTotals.net_profit || 0)
            ];

            var notes = [
                [],
                ["* Ghi chú công thức tính:"],
                ["- Doanh thu từng dịch vụ: Tính theo số tiền trước thuế VAT từ các hóa đơn đã thanh toán (PAID)."],
                ["- Chi phí nhân công: = 70% × Dịch Vụ Website + 30% × Bảo Trì + 15% × Hosting (loại trừ dịch vụ Tên Miền)."],
                ["- Lợi nhuận còn lại: = Tổng Doanh Thu - Hoa Hồng Đối Tác - Chi Phí Nhân Công."]
            ];

            var worksheetData = headers.concat(rows, [totalRow], notes);
            var ws = XLSX.utils.aoa_to_sheet(worksheetData);

            // Set column widths
            ws['!cols'] = [
                { wch: 14 },
                { wch: 18 },
                { wch: 18 },
                { wch: 18 },
                { wch: 20 },
                { wch: 22 },
                { wch: 20 },
                { wch: 22 },
                { wch: 22 }
            ];

            // Apply number format to data and total rows
            for (var R = 3; R <= 16; ++R) {
                for (var C = 1; C <= 8; ++C) {
                    var cellAddress = XLSX.utils.encode_cell({ r: R, c: C });
                    if (ws[cellAddress] && typeof ws[cellAddress].v === 'number') {
                        ws[cellAddress].z = '#,##0';
                    }
                }
            }

            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Doanh Thu " + selectedYear);

            var fileName = "Bao-cao-doanh-thu-dich-vu-" + selectedYear + ".xlsx";
            XLSX.writeFile(wb, fileName);
        });
    }
});
</script>

<?php
get_footer();
