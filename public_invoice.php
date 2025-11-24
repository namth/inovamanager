<?php
/* 
    Template Name: Public Invoice View
    Purpose: Display invoice for public/non-authenticated users with minimal layout
*/

global $wpdb;
$invoice_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';
$users_table = $wpdb->prefix . 'im_users';
$service_table = $wpdb->prefix . 'im_website_services';

// Get invoice ID and token from URL parameters
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

// Get invoice
$invoice = $wpdb->get_row($wpdb->prepare("
    SELECT 
        i.*,
        u.name AS customer_name,
        u.user_code AS customer_code,
        u.email AS customer_email,
        u.tax_code,
        u.address AS customer_address,
        u.phone_number AS customer_phone,
        u.requires_vat_invoice
    FROM 
        $invoice_table i
    LEFT JOIN 
        $users_table u ON i.user_id = u.id
    WHERE 
        i.id = %d
", $invoice_id));

// If invoice not found, redirect
if (!$invoice) {
    wp_die('Hóa đơn không tồn tại.');
}

// Verify token - generate a simple hash from invoice ID and creation date
// Token format: base64(invoice_id + invoice_date)
$expected_token = base64_encode($invoice->id . '|' . $invoice->created_at);

// Allow either: authenticated user who owns invoice, or valid token provided
$is_authorized = false;

if (!empty($token) && $token === $expected_token) {
    $is_authorized = true;
} elseif (is_user_logged_in() && (
    current_user_can('manage_options') || 
    get_current_user_id() == $invoice->user_id
)) {
    $is_authorized = true;
}

// If not authorized, show error
if (!$is_authorized) {
    wp_die('Bạn không có quyền xem hóa đơn này.');
}

// Get invoice items with service details
$websites_table = $wpdb->prefix . 'im_websites';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
$commissions_table = $wpdb->prefix . 'im_partner_commissions';

$invoice_items = $wpdb->get_results($wpdb->prepare("
    SELECT
        ii.*,
        CASE
            WHEN ii.service_type = 'website_service' THEN ws.title
            ELSE ii.description
        END AS service_title,
        CASE
            WHEN ii.service_type = 'website_service' THEN ws.description
            ELSE ''
        END AS service_reference,
        COALESCE((SELECT SUM(commission_amount) FROM $commissions_table WHERE invoice_item_id = ii.id AND status IN ('WITHDRAWN', 'PAID')), 0) AS withdrawn_commission
    FROM
        $invoice_items_table ii
    LEFT JOIN
        $service_table ws ON ii.service_type = 'website_service' AND ii.service_id = ws.id
    WHERE
        ii.invoice_id = %d
    ORDER BY ii.id
", $invoice_id));

// Get website names for each invoice item
foreach ($invoice_items as $item) {
    $website_names = array();
    if (in_array($item->service_type, ['hosting', 'maintenance'])) {
        $website_infos = $wpdb->get_results($wpdb->prepare("
            SELECT name FROM $websites_table 
            WHERE (hosting_id = %d OR maintenance_package_id = %d)
            ORDER BY name ASC
        ", $item->service_id, $item->service_id));
        
        if (!empty($website_infos)) {
            foreach ($website_infos as $website_info) {
                $website_names[] = $website_info->name;
            }
        }
    } elseif ($item->service_type === 'website_service') {
        // Get website name from website_services table
        $service_table = $wpdb->prefix . 'im_website_services';
        $ws_info = $wpdb->get_row($wpdb->prepare("
            SELECT w.name FROM {$service_table} ws
            LEFT JOIN $websites_table w ON ws.website_id = w.id
            WHERE ws.id = %d
        ", $item->service_id));
        
        if ($ws_info && !empty($ws_info->name)) {
            $website_names[] = $ws_info->name;
        }
    }
    $item->website_names = $website_names;
}

// Calculate totals and check if has commissions
$has_commission_deduction = false;
$total_commission_deduction = 0;

foreach ($invoice_items as $item) {
    if (isset($item->withdrawn_commission) && $item->withdrawn_commission > 0) {
        $has_commission_deduction = true;
        $total_commission_deduction += $item->withdrawn_commission;
    }
}

// Get status options and colors
$status_options = array(
    'draft' => 'Nháp',
    'pending' => 'Chờ thanh toán',
    'paid' => 'Đã thanh toán',
    'canceled' => 'Đã hủy',
    'pending_completion' => 'Chờ hoàn thành dịch vụ'
);

$status_classes = array(
    'draft' => 'bg-secondary',
    'pending' => 'bg-warning',
    'paid' => 'bg-success',
    'canceled' => 'bg-danger',
    'pending_completion' => 'bg-info'
);

// Get site logo
$logo_url = get_template_directory_uri() . '/img/inova_logo.png';
$site_name = get_bloginfo('name');
$public_invoice_url = home_url('/public-invoice/?invoice_id=' . $invoice->id . '&token=' . urlencode(base64_encode($invoice->id . '|' . $invoice->created_at)));

get_header('nologin');
?>
<style>
    .content-wrapper {
        flex-direction: column;
        gap: 30px;
        padding: 20px;
    }

    .logo-section {
        text-align: center;
        width: 100%;
    }

    .logo-section img {
        max-width: 150px;
        height: auto;
    }
    
    .public-invoice-container {
        width: 100%;
        max-width: 820px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }
        
    .invoice-header {
        padding: 40px 30px;
        text-align: center;
        border-bottom: 1px solid #eee;
    }
    
    .invoice-header img {
        max-width: 120px;
        margin-bottom: 20px;
    }
    
    .invoice-header h1 {
        font-size: 28px;
        font-weight: 600;
        margin: 15px 0 5px;
    }
    
    .invoice-header p {
        margin: 5px 0;
        font-size: 14px;
    }
    
    .invoice-content {
        padding: 40px 30px;
    }
    
    .invoice-info-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 40px;
    }
    
    @media (max-width: 768px) {
        .invoice-info-row {
            grid-template-columns: 1fr;
        }
    }
    
    .info-section h3 {
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
    }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table td {
            padding: 6px 0;
            border: none;
        }
        
        .info-table td:first-child {
            width: 40%;
            font-size: 13px;
        }
        
        .info-table td:last-child {
            font-weight: 500;
        }
    
    .items-section {
        margin-bottom: 40px;
    }
    
    .items-section h3 {
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
    }
    
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }
    
    .items-table thead {
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }
        
        .items-table th {
            padding: 12px 10px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
    }
    
    .items-table th.text-end {
        text-align: right;
    }
    
    .items-table tbody tr {
        border-bottom: 1px solid #eee;
    }
    
    .items-table td {
            padding: 12px 10px;
            font-size: 13px;
    }
    
    .items-table td.text-end {
        text-align: right;
    }
    
    .items-table td.text-center {
        text-align: center;
    }
    
    .service-name {
        font-weight: 500;
    }
    
    .service-desc {
            font-size: 12px;
            margin-top: 3px;
    }
    
    .totals-section {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 30px;
        margin-bottom: 40px;
        align-items: start;
    }
    
    @media (max-width: 768px) {
        .totals-section {
            grid-template-columns: 1fr;
        }
    }
    
    .totals-table {
        width: 100%;
        border-collapse: collapse;
        background-color: #f8f9fa;
        border-radius: 6px;
        overflow: hidden;
    }
    
    .totals-table tr:last-child {
        border-bottom: none;
    }
    
    .totals-table td {
            padding: 10px 15px;
            font-size: 13px;
    }
        
        .totals-table td:first-child {
            width: 60%;
    }
        
        .totals-table td:last-child {
            text-align: right;
            font-weight: 500;
    }

        
    .totals-table tr.total-row td {
        padding: 12px 15px;
        font-size: 14px;
        font-weight: 600;
    }
        
    .totals-table tr.paid-row {
        background-color: #d4edda;
    }
    
    .qr-section {
        text-align: center;
    }
    
    .qr-section h4 {
        font-size: 13px;
        text-transform: uppercase;
        margin-bottom: 12px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
        
    .qr-code-img {
        max-width: 200px;
        width: 100%;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 8px;
        background: white;
        margin-bottom: 15px;
    }
        
    .qr-info {
        width: fit-content;
        margin: 0 auto;
        background-color: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 6px;
        padding: 12px;
        font-size: 12px;
    }
        
    .qr-info-row {
        display: flex;
        gap: 20px;
        justify-content: space-between;
        margin-bottom: 6px;
    }
        
    .qr-info-row:last-child {
        margin-bottom: 0;
    }
        
    .qr-info-label {
        font-weight: 500;
    }
        
    .qr-info-value {
        font-weight: bold;
        font-size: 12px;
    }
    
    @media print {
        .content-wrapper {
            background: white;
            padding: 0;
        }
        
        .public-invoice-container {
            max-width: 100%;
            box-shadow: none;
            border-radius: 0;
        }
        
        .logo-section {
            display: none;
        }
        
        .print-button {
            display: none;
        }
    }
</style>

<div class="content-wrapper d-flex align-items-center justify-content-center loginbg">
    <!-- Logo Section -->
    <div class="logo-section">
        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>">
    </div>

    <!-- Invoice Container -->
    <div class="public-invoice-container">
        <!-- Header -->
         <div class="invoice-header">
             <h1 class="text-dark">HÓA ĐƠN DỊCH VỤ</h1>
             <p class="text-muted"><?php echo 'Hạn thanh toán: ' . date('d/m/Y', strtotime($invoice->due_date)); ?></p>
             <p>
                 <?php
                 $badge_class = 'bg-secondary';
                 if ($invoice->status === 'paid') {
                     $badge_class = 'bg-light-success text-success';
                 } elseif ($invoice->status === 'pending') {
                     $badge_class = 'bg-light-warning text-warning';
                 } elseif ($invoice->status === 'canceled') {
                     $badge_class = 'bg-light-danger text-danger';
                 }
                 ?>
                 <span class="btn btn-sm <?php echo $badge_class; ?>" style="font-weight: bold; text-transform: uppercase;"><?php echo $status_options[$invoice->status] ?? $invoice->status; ?></span>
             </p>
         </div>

        <!-- Content -->
        <div class="invoice-content">
            <!-- Invoice and Customer Info -->
             <div class="invoice-info-row">
                 <div class="info-section">
                     <h3 class="text-muted">Thông tin nhà cung cấp</h3>
                    <table class="info-table">
                        <tr>
                            <td>Tên công ty:</td>
                            <td>CÔNG TY TNHH CÔNG NGHỆ INOVA</td>
                        </tr>
                        <tr>
                            <td>Mã số thuế:</td>
                            <td>0109882536</td>
                        </tr>
                        <tr>
                            <td>Email:</td>
                            <td>inovavietnam@gmail.com</td>
                        </tr>
                        <?php if ($invoice->payment_date): ?>
                        <tr>
                            <td>Ngày thanh toán:</td>
                            <td><?php echo date('d/m/Y', strtotime($invoice->payment_date)); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="info-section">
                     <h3 class="text-muted">Thông tin khách hàng</h3>
                    <table class="info-table">
                        <tr>
                            <td>Tên khách hàng:</td>
                            <td><?php echo esc_html($invoice->customer_name); ?></td>
                        </tr>
                        <tr>
                            <td>Mã khách hàng:</td>
                            <td><?php echo esc_html($invoice->customer_code); ?></td>
                        </tr>
                        <tr>
                            <td>Email:</td>
                            <td><?php echo esc_html($invoice->customer_email); ?></td>
                        </tr>
                        <?php if ($invoice->customer_phone): ?>
                        <tr>
                            <td>Điện thoại:</td>
                            <td><?php echo esc_html($invoice->customer_phone); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Invoice Items -->
             <div class="items-section">
                 <h3 class="text-muted">Chi tiết dịch vụ</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Dịch vụ</th>
                            <th class="text-center">Số lượng</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-end">Thành tiền</th>
                            <th class="text-end">VAT</th>
                            <th class="text-end">Tổng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoice_items as $item): ?>
                         <tr>
                             <td>
                                 <div class="service-name text-dark"><?php echo esc_html($item->service_title ?: $item->description); ?></div>
                                 <?php if ($item->service_reference): ?>
                                     <div class="service-desc text-muted"><?php echo esc_html($item->service_reference); ?></div>
                                 <?php endif; ?>
                                 <?php if (!empty($item->website_names)): ?>
                                     <div class="service-desc text-muted">
                                         <i class="ph ph-globe-hemisphere-west"></i>
                                         (<?php echo implode(', ', array_map('esc_html', $item->website_names)); ?>)
                                     </div>
                                 <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo number_format($item->quantity); ?></td>
                            <td class="text-end"><?php echo number_format($item->unit_price); ?> VNĐ</td>
                            <td class="text-end"><?php echo number_format($item->item_total); ?> VNĐ</td>
                            <td class="text-end">
                                <?php
                                $item_vat_rate = isset($item->vat_rate) ? floatval($item->vat_rate) : 0;
                                $item_vat_calculated = ($item->item_total * $item_vat_rate) / 100;
                                
                                if ($item_vat_calculated > 0):
                                    echo number_format($item_vat_calculated) . ' VNĐ<br><span class="text-muted" style="font-size: 11px;">(' . number_format($item_vat_rate, 1) . '%)</span>';
                                else:
                                    echo '<span class="text-muted">-</span>';
                                endif;
                                ?>
                            </td>
                            <td class="text-end">
                                <?php 
                                $item_vat = ($item->item_total * floatval($item->vat_rate ?? 0)) / 100;
                                $item_commission = (isset($item->withdrawn_commission) ? $item->withdrawn_commission : 0);
                                $item_total_final = $item->item_total + $item_vat - $item_commission;
                                echo '<strong>' . number_format($item_total_final) . ' VNĐ</strong>';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals and QR Code -->
            <div class="totals-section">
                <div></div>
                <div>
                    <table class="totals-table">
                        <tr>
                            <td>Tạm tính:</td>
                            <td><?php echo number_format($invoice->sub_total); ?> VNĐ</td>
                        </tr>
                        <?php if ($invoice->discount_total > 0): ?>
                         <tr>
                             <td>Giảm giá:</td>
                             <td><span class="text-danger">-<?php echo number_format($invoice->discount_total); ?> VNĐ</span></td>
                         </tr>
                         <?php endif; ?>
                        <?php if ($invoice->tax_amount > 0): ?>
                        <tr>
                            <td>Thuế:</td>
                            <td><?php echo number_format($invoice->tax_amount); ?> VNĐ</td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row bg-light-success">
                            <td>Tổng cộng:</td>
                            <td><?php echo number_format($invoice->total_amount); ?> VNĐ</td>
                        </tr>
                        <?php if ($invoice->paid_amount > 0): ?>
                        <tr class="paid-row bg-success">
                            <td>Đã thanh toán:</td>
                            <td><?php echo number_format($invoice->paid_amount); ?> VNĐ</td>
                        </tr>
                        <tr>
                            <td>Còn lại:</td>
                            <td><?php echo number_format(max(0, $invoice->total_amount - $invoice->paid_amount)); ?> VNĐ</td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- QR Code Section -->
            <?php
            $has_qr_settings = (
                (get_option('payment_bank_code_no_vat') && get_option('payment_account_number_no_vat')) ||
                (get_option('payment_bank_code_with_vat') && get_option('payment_account_number_with_vat')) ||
                (get_option('payment_bank_code') && get_option('payment_account_number'))
            );

            if ($has_qr_settings && $invoice->status !== 'paid' && $invoice->status !== 'canceled'):
                $remaining_amount = $invoice->total_amount - $invoice->paid_amount;
                $qr_add_info = 'HD ' . $invoice->invoice_code;
                $requires_vat_invoice = isset($invoice->requires_vat_invoice) ? $invoice->requires_vat_invoice : 0;
                $qr_code_url = generate_payment_qr_code($remaining_amount, $qr_add_info, $requires_vat_invoice);

                if ($qr_code_url):
            ?>
             <div class="qr-section">
                <h4 class="text-muted">Thông tin thanh toán</h4>
                <img src="<?php echo esc_url($qr_code_url); ?>" alt="Payment QR Code" class="qr-code-img">
                <div class="qr-info">
                    <div class="qr-info-row">
                        <span class="qr-info-label">Số tài khoản:</span>
                        <span class="qr-info-value"><?php echo get_option('payment_account_number'); ?></span>
                    </div>
                    <div class="qr-info-row">
                        <span class="qr-info-label">Nội dung CK:</span>
                        <span class="qr-info-value"><?php echo esc_html($qr_add_info); ?></span>
                    </div>
                    <div class="qr-info-row">
                        <span class="qr-info-label">Số tiền:</span>
                        <span class="qr-info-value"><?php echo number_format($remaining_amount); ?> VNĐ</span>
                    </div>
                </div>
                </div>
                <?php
                endif;
                endif;
                ?>

                <!-- Print Button -->
            <div class="d-flex align-items-center justify-content-center mt-4 print-button">
                <button class="btn btn-danger" onclick="window.print()">
                    <i class="ph ph-printer me-2"></i>In hóa đơn
                </button>
            </div>
        </div>
    </div>
</div><!-- content-wrapper end -->


