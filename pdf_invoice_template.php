<?php
/**
 * PDF Invoice Template
 * Generate HTML template for PDF invoice using Dompdf
 */

/**
 * Get invoice PDF HTML
 *
 * @param int $invoice_id Invoice ID
 * @return string HTML content for PDF
 */
function get_invoice_pdf_html($invoice_id) {
    global $wpdb;

    $invoice_table = $wpdb->prefix . 'im_invoices';
    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
    $users_table = $wpdb->prefix . 'im_users';

    // Get invoice data
    $invoice = $wpdb->get_row($wpdb->prepare("
        SELECT
            i.*,
            u.name AS customer_name,
            u.email AS customer_email,
            u.phone_number AS customer_phone,
            u.address AS customer_address,
            u.tax_code AS customer_tax_code
        FROM {$invoice_table} i
        LEFT JOIN {$users_table} u ON i.user_id = u.id
        WHERE i.id = %d
    ", $invoice_id));

    if (!$invoice) {
        return '<h1>Invoice not found</h1>';
    }

    // Get invoice items
    $invoice_items = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$invoice_items_table}
        WHERE invoice_id = %d
        ORDER BY id
    ", $invoice_id));

    // Build HTML
    $html = '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Hóa đơn ' . esc_html($invoice->invoice_code) . '</title>
    <style>
        @page {
            margin: 20mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
        }
        .container {
            width: 100%;
        }
        .header {
            margin-bottom: 30px;
            border-bottom: 3px solid #007bff;
            padding-bottom: 15px;
        }
        .company-info {
            float: left;
            width: 50%;
        }
        .invoice-info {
            float: right;
            width: 45%;
            text-align: right;
        }
        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .invoice-title {
            font-size: 16pt;
            font-weight: bold;
            color: #007bff;
            margin-top: 10px;
        }
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
        .customer-info {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table thead tr {
            background-color: #007bff;
            color: white;
        }
        table th, table td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
        }
        table th {
            font-weight: bold;
        }
        table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #e9ecef !important;
        }
        .grand-total-row {
            font-weight: bold;
            font-size: 12pt;
            background-color: #007bff !important;
            color: white !important;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
            font-size: 9pt;
            color: #666;
        }
        .signature-section {
            margin-top: 40px;
        }
        .signature-box {
            float: left;
            width: 48%;
            text-align: center;
        }
        .signature-box.right {
            float: right;
        }
        .signature-title {
            font-weight: bold;
            margin-bottom: 60px;
        }
        .signature-name {
            font-style: italic;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 10pt;
        }
        .status-paid {
            background-color: #28a745;
            color: white;
        }
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-draft {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">';

    // Header
    $html .= '
        <div class="header clearfix">
            <div class="company-info">
                <div class="company-name">INOVA MANAGER</div>
                <div>Địa chỉ: 123 Đường ABC, Quận XYZ, TP.HCM</div>
                <div>Điện thoại: 1900-xxxx</div>
                <div>Email: info@inova.vn</div>
                <div>Mã số thuế: 0123456789</div>
            </div>
            <div class="invoice-info">
                <div class="invoice-title">HÓA ĐƠN</div>
                <div><strong>Số:</strong> ' . esc_html($invoice->invoice_code) . '</div>
                <div><strong>Ngày:</strong> ' . date('d/m/Y', strtotime($invoice->invoice_date)) . '</div>';

    // Status badge
    $status_class = 'status-draft';
    $status_text = 'Nháp';
    switch (strtolower($invoice->status)) {
        case 'paid':
            $status_class = 'status-paid';
            $status_text = 'Đã thanh toán';
            break;
        case 'pending':
            $status_class = 'status-pending';
            $status_text = 'Chờ thanh toán';
            break;
    }

    $html .= '
                <div style="margin-top: 10px;">
                    <span class="status-badge ' . $status_class . '">' . $status_text . '</span>
                </div>
            </div>
        </div>';

    // Customer info
    $html .= '
        <div class="section-title">THÔNG TIN KHÁCH HÀNG</div>
        <div class="customer-info">
            <div><strong>Tên khách hàng:</strong> ' . esc_html($invoice->customer_name) . '</div>';

    if (!empty($invoice->customer_tax_code)) {
        $html .= '<div><strong>Mã số thuế:</strong> ' . esc_html($invoice->customer_tax_code) . '</div>';
    }
    if (!empty($invoice->customer_address)) {
        $html .= '<div><strong>Địa chỉ:</strong> ' . esc_html($invoice->customer_address) . '</div>';
    }
    if (!empty($invoice->customer_phone)) {
        $html .= '<div><strong>Điện thoại:</strong> ' . esc_html($invoice->customer_phone) . '</div>';
    }
    if (!empty($invoice->customer_email)) {
        $html .= '<div><strong>Email:</strong> ' . esc_html($invoice->customer_email) . '</div>';
    }

    $html .= '
        </div>';

    // Invoice items table
    $html .= '
        <div class="section-title">CHI TIẾT HÓA ĐƠN</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 40px;">STT</th>
                    <th>Dịch vụ</th>
                    <th class="text-center" style="width: 60px;">SL</th>
                    <th class="text-right" style="width: 120px;">Đơn giá</th>
                    <th class="text-right" style="width: 120px;">Thành tiền</th>
                </tr>
            </thead>
            <tbody>';

    $index = 1;
    foreach ($invoice_items as $item) {
        $html .= '
                <tr>
                    <td class="text-center">' . $index++ . '</td>
                    <td>' . esc_html($item->description);

        if (!empty($item->start_date) && !empty($item->end_date)) {
            $html .= '<br><small style="color: #666;">Từ ' . date('d/m/Y', strtotime($item->start_date)) .
                     ' đến ' . date('d/m/Y', strtotime($item->end_date)) . '</small>';
        }

        $html .= '</td>
                    <td class="text-center">' . $item->quantity . '</td>
                    <td class="text-right">' . number_format($item->unit_price, 0, ',', '.') . '</td>
                    <td class="text-right">' . number_format($item->item_total, 0, ',', '.') . '</td>
                </tr>';
    }

    // Totals
    $html .= '
                <tr class="total-row">
                    <td colspan="4" class="text-right">Tổng cộng:</td>
                    <td class="text-right">' . number_format($invoice->sub_total, 0, ',', '.') . '</td>
                </tr>';

    if ($invoice->discount_total > 0) {
        $html .= '
                <tr class="total-row">
                    <td colspan="4" class="text-right">Giảm giá:</td>
                    <td class="text-right">-' . number_format($invoice->discount_total, 0, ',', '.') . '</td>
                </tr>';
    }

    if ($invoice->tax_amount > 0) {
        $html .= '
                <tr class="total-row">
                    <td colspan="4" class="text-right">Thuế VAT:</td>
                    <td class="text-right">' . number_format($invoice->tax_amount, 0, ',', '.') . '</td>
                </tr>';
    }

    $html .= '
                <tr class="grand-total-row">
                    <td colspan="4" class="text-right">TỔNG TIỀN:</td>
                    <td class="text-right">' . number_format($invoice->total_amount, 0, ',', '.') . ' VNĐ</td>
                </tr>
            </tbody>
        </table>';

    // Notes
    if (!empty($invoice->notes)) {
        $html .= '
        <div class="section-title">GHI CHÚ</div>
        <div style="background-color: #f8f9fa; padding: 10px; border-left: 4px solid #ffc107;">
            ' . nl2br(esc_html($invoice->notes)) . '
        </div>';
    }

    // Signature section
    $html .= '
        <div class="signature-section clearfix">
            <div class="signature-box">
                <div class="signature-title">Người mua hàng</div>
                <div class="signature-name">(Ký, ghi rõ họ tên)</div>
            </div>
            <div class="signature-box right">
                <div class="signature-title">Người bán hàng</div>
                <div class="signature-name">(Ký, ghi rõ họ tên)</div>
            </div>
        </div>';

    // Footer
    $html .= '
        <div class="footer">
            <p style="text-align: center; margin: 0;">
                Cảm ơn quý khách đã sử dụng dịch vụ của chúng tôi!<br>
                <em>Tài liệu được tạo tự động bởi hệ thống INOVA Manager</em>
            </p>
        </div>
    </div>
</body>
</html>';

    return $html;
}
