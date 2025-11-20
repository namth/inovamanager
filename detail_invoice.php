<?php
/* 
    Template Name: Invoice Detail
    Purpose: Display detailed invoice information with management capabilities
*/

global $wpdb;
$invoice_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';
$users_table = $wpdb->prefix . 'im_users';
$service_table = $wpdb->prefix . 'im_website_services';

// Get invoice ID from URL parameter
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
$auto_open_payment = isset($_GET['action']) && $_GET['action'] === 'payment';

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['action_nonce'], 'invoice_action')) {
    // Debug logging
    error_log('POST request received with action: ' . ($_POST['action'] ?? 'none'));
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_as_paid':
                error_log('Processing mark_as_paid for invoice ID: ' . $invoice_id);
                $paid_amount = floatval($_POST['paid_amount']);
                $payment_date = sanitize_text_field($_POST['payment_date']);
                $payment_notes = sanitize_textarea_field($_POST['payment_notes']);
                $payment_method = sanitize_text_field($_POST['payment_method']);

                // Start transaction to ensure data consistency
                $wpdb->query('START TRANSACTION');

                try {
                    // Update invoice status
                    $invoice_result = $wpdb->update(
                        $invoice_table,
                        array(
                            'paid_amount' => $paid_amount,
                            'payment_date' => $payment_date,
                            'payment_method' => $payment_method,
                            'notes' => $wpdb->get_var($wpdb->prepare("SELECT notes FROM $invoice_table WHERE id = %d", $invoice_id)) . "\n\nGhi chú thanh toán: " . $payment_notes,
                            'status' => 'paid',
                            'updated_at' => current_time('mysql')
                        ),
                        array('id' => $invoice_id)
                    );

                    if ($invoice_result === false) {
                        throw new Exception('Failed to update invoice status');
                    }

                    // Define table names for different service types
                    $domains_table = $wpdb->prefix . 'im_domains';
                    $hostings_table = $wpdb->prefix . 'im_hostings';
                    $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';

                    // Get all invoice items with renewal information
                    $invoice_items = $wpdb->get_results($wpdb->prepare("
                        SELECT
                            ii.service_type,
                            ii.service_id,
                            ii.end_date
                        FROM $invoice_items_table ii
                        WHERE ii.invoice_id = %d
                        AND ii.service_id IS NOT NULL
                        AND ii.end_date IS NOT NULL
                    ", $invoice_id));

                    // Update expiry_date for each service/product based on service_type
                    foreach ($invoice_items as $item) {
                        $update_result = false;

                        switch ($item->service_type) {
                            case 'domain':
                                $update_result = $wpdb->update(
                                    $domains_table,
                                    array(
                                        'expiry_date' => $item->end_date,
                                        'updated_at' => current_time('mysql')
                                    ),
                                    array('id' => $item->service_id)
                                );
                                error_log("Updated domain ID {$item->service_id} expiry_date to {$item->end_date}");
                                break;

                            case 'hosting':
                                $update_result = $wpdb->update(
                                    $hostings_table,
                                    array(
                                        'expiry_date' => $item->end_date,
                                        'updated_at' => current_time('mysql')
                                    ),
                                    array('id' => $item->service_id)
                                );
                                error_log("Updated hosting ID {$item->service_id} expiry_date to {$item->end_date}");
                                break;

                            case 'maintenance':
                                $update_result = $wpdb->update(
                                    $maintenance_table,
                                    array(
                                        'expiry_date' => $item->end_date,
                                        'updated_at' => current_time('mysql')
                                    ),
                                    array('id' => $item->service_id)
                                );
                                error_log("Updated maintenance package ID {$item->service_id} expiry_date to {$item->end_date}");
                                break;

                            case 'website_service':
                                // Website services don't have expiry_date, skip
                                $update_result = true;
                                break;
                        }

                        if ($update_result === false) {
                            throw new Exception("Failed to update expiry_date for {$item->service_type} ID: {$item->service_id}");
                        }
                    }

                    // Get related website services from invoice items
                    $related_services = $wpdb->get_results($wpdb->prepare("
                        SELECT DISTINCT ii.service_id
                        FROM $invoice_items_table ii
                        WHERE ii.invoice_id = %d
                        AND ii.service_type = 'website_service'
                        AND ii.service_id IS NOT NULL
                    ", $invoice_id));

                    // Update service status to in_progress for each related service
                    foreach ($related_services as $service_item) {
                        // Check if service is currently approved before updating
                        $current_service = $wpdb->get_row($wpdb->prepare("
                            SELECT status FROM $service_table WHERE id = %d
                        ", $service_item->service_id));

                        if ($current_service && $current_service->status === 'APPROVED') {
                            $service_result = $wpdb->update(
                                $service_table,
                                array(
                                    'status' => 'IN_PROGRESS',
                                    'start_date' => date('Y-m-d', strtotime($payment_date)),
                                    'updated_at' => current_time('mysql')
                                ),
                                array('id' => $service_item->service_id)
                            );

                            if ($service_result === false) {
                                throw new Exception('Failed to update service status for service ID: ' . $service_item->service_id);
                            }
                        }
                    }

                    $wpdb->query('COMMIT');
                    $success_message = 'Hóa đơn đã được đánh dấu là đã thanh toán. Ngày hết hạn của các sản phẩm/dịch vụ đã được cập nhật.';

                } catch (Exception $e) {
                    $wpdb->query('ROLLBACK');
                    $error_message = 'Có lỗi xảy ra khi cập nhật: ' . $e->getMessage();
                }
                break;
                
            case 'update_status':
                error_log('Processing update_status for invoice ID: ' . $invoice_id);
                $new_status = sanitize_text_field($_POST['new_status']);
                $status_notes = sanitize_textarea_field($_POST['status_notes']);
                
                $update_data = array(
                    'status' => $new_status,
                    'updated_at' => current_time('mysql')
                );
                
                if (!empty($status_notes)) {
                    $current_notes = $wpdb->get_var($wpdb->prepare("SELECT notes FROM $invoice_table WHERE id = %d", $invoice_id));
                    $update_data['notes'] = $current_notes . "\n\nCập nhật trạng thái: " . $status_notes;
                }
                
                $result = $wpdb->update($invoice_table, $update_data, array('id' => $invoice_id));
                
                if ($result !== false) {
                    $success_message = 'Trạng thái hóa đơn đã được cập nhật.';
                } else {
                    $error_message = 'Có lỗi xảy ra khi cập nhật trạng thái hóa đơn.';
                }
                break;
        }
    }
}

// Get invoice details with service information
$invoice = $wpdb->get_row($wpdb->prepare("
    SELECT 
        i.*,
        u.name AS customer_name,
        u.user_code AS customer_code,
        u.email AS customer_email,
        u.tax_code,
        u.address AS customer_address,
        u.phone_number AS customer_phone
    FROM 
        $invoice_table i
    LEFT JOIN 
        $users_table u ON i.user_id = u.id
    WHERE 
        i.id = %d
", $invoice_id));

// If invoice not found, redirect to invoice list
if (!$invoice) {
    wp_redirect(home_url('/danh-sach-hoa-don/'));
    exit;
}

// Get invoice items with service details and website names
$websites_table = $wpdb->prefix . 'im_websites';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';

$invoice_items = $wpdb->get_results($wpdb->prepare("
    SELECT
        ii.*,
        CASE
            WHEN ii.service_type = 'website_service' THEN ws.title
            ELSE ii.description
        END AS service_title,
        CASE
            WHEN ii.service_type = 'website_service' THEN CONCAT('Yêu cầu dịch vụ #', ws.id)
            ELSE ''
        END AS service_reference,
        CASE
            WHEN ii.service_type = 'hosting' THEN (SELECT w2.name FROM $websites_table w2 WHERE w2.hosting_id = ii.service_id LIMIT 1)
            WHEN ii.service_type = 'maintenance' THEN (SELECT w3.name FROM $websites_table w3 WHERE w3.maintenance_package_id = ii.service_id LIMIT 1)
            ELSE NULL
        END AS website_name
    FROM
        $invoice_items_table ii
    LEFT JOIN
        $service_table ws ON ii.service_type = 'website_service' AND ii.service_id = ws.id
    WHERE
        ii.invoice_id = %d
    ORDER BY ii.id
", $invoice_id));

// Check for related invoices (for 50% payment system)
$related_invoices = $wpdb->get_results($wpdb->prepare("
    SELECT DISTINCT 
        i2.*
    FROM 
        $invoice_table i2
    INNER JOIN 
        $invoice_items_table ii1 ON i2.id = ii1.invoice_id
    INNER JOIN 
        $invoice_items_table ii2 ON ii1.service_id = ii2.service_id 
        AND ii1.service_type = ii2.service_type
    WHERE 
        ii2.invoice_id = %d 
        AND i2.id != %d
    ORDER BY i2.created_at
", $invoice_id, $invoice_id));

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

get_header(); 
?>

<div class="main-panel">
    <div class="content-wrapper">
        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="ph ph-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="ph ph-x-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Invoice Details Card -->
            <div class="col-lg-8 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h4 class="card-title mb-2">
                                    <i class="ph ph-receipt me-2"></i>
                                    Hóa đơn <?php echo esc_html($invoice->invoice_code); ?>
                                </h4>
                                <div class="d-flex align-items-center gap-3">
                                    <?php
                                    $status_classes = [
                                        'draft' => 'bg-secondary',
                                        'pending' => 'bg-warning',
                                        'paid' => 'bg-success',
                                        'canceled' => 'bg-danger',
                                        'pending_completion' => 'bg-info'
                                    ];
                                    $status_class = $status_classes[$invoice->status] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo $status_options[$invoice->status] ?? $invoice->status; ?>
                                    </span>
                                    <span class="text-muted">
                                        <i class="ph ph-calendar me-1"></i>
                                        Ngày tạo: <?php echo date('d/m/Y', strtotime($invoice->created_at)); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="ph ph-gear me-2"></i>Thao tác
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if ($invoice->status !== 'paid'): ?>
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                            <i class="ph ph-credit-card me-2"></i>Đánh dấu đã thanh toán
                                        </a></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#statusModal">
                                        <i class="ph ph-arrow-clockwise me-2"></i>Cập nhật trạng thái
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo home_url('/print-invoice/?invoice_id=' . $invoice->id); ?>" target="_blank">
                                        <i class="ph ph-printer me-2"></i>In hóa đơn
                                    </a></li>
                                    <li><a class="dropdown-item" href="<?php echo home_url('/danh-sach-hoa-don/'); ?>">
                                        <i class="ph ph-arrow-left me-2"></i>Quay lại danh sách
                                    </a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Invoice Info -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Thông tin hóa đơn</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" width="40%">Mã hóa đơn:</td>
                                        <td><strong><?php echo esc_html($invoice->invoice_code); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Ngày hóa đơn:</td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice->invoice_date)); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Hạn thanh toán:</td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice->due_date)); ?></td>
                                    </tr>
                                    <?php if ($invoice->payment_date): ?>
                                    <tr>
                                        <td class="text-muted">Ngày thanh toán:</td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice->payment_date)); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Thông tin khách hàng</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" width="40%">Tên khách hàng:</td>
                                        <td><strong><?php echo esc_html($invoice->customer_name); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Mã khách hàng:</td>
                                        <td><?php echo esc_html($invoice->customer_code); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Email:</td>
                                        <td><?php echo esc_html($invoice->customer_email); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Invoice Items -->
                        <h6 class="text-muted mb-3">Chi tiết dịch vụ</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-light">
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
                                            <div>
                                                <strong><?php echo esc_html($item->service_title ?: $item->description); ?></strong>
                                                <?php if ($item->service_reference): ?>
                                                    <br><small class="text-muted"><?php echo esc_html($item->service_reference); ?></small>
                                                <?php endif; ?>
                                                <?php if (isset($item->website_name) && !empty($item->website_name) && in_array($item->service_type, ['hosting', 'maintenance'])): ?>
                                                    <br><small class="text-muted"><i class="ph ph-globe-hemisphere-west"></i> Website: <?php echo esc_html($item->website_name); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo number_format($item->quantity); ?></td>
                                        <td class="text-end"><?php echo number_format($item->unit_price); ?> VNĐ</td>
                                        <td class="text-end"><?php echo number_format($item->item_total); ?> VNĐ</td>
                                        <td class="text-end">
                                            <?php if (isset($item->vat_amount) && $item->vat_amount > 0): ?>
                                                <?php echo number_format($item->vat_amount); ?> VNĐ
                                                <br><small class="text-muted">(<?php echo number_format($item->vat_rate, 1); ?>%)</small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><strong><?php echo number_format($item->item_total + (isset($item->vat_amount) ? $item->vat_amount : 0)); ?> VNĐ</strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Invoice Totals -->
                        <div class="row justify-content-end">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td class="text-muted">Tạm tính:</td>
                                        <td class="text-end"><?php echo number_format($invoice->sub_total); ?> VNĐ</td>
                                    </tr>
                                    <?php if ($invoice->discount_total > 0): ?>
                                    <tr>
                                        <td class="text-muted">Giảm giá:</td>
                                        <td class="text-end text-danger">-<?php echo number_format($invoice->discount_total); ?> VNĐ</td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($invoice->tax_amount > 0): ?>
                                    <tr>
                                        <td class="text-muted">Thuế:</td>
                                        <td class="text-end"><?php echo number_format($invoice->tax_amount); ?> VNĐ</td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr class="table-primary">
                                        <td><strong>Tổng cộng:</strong></td>
                                        <td class="text-end"><strong><?php echo number_format($invoice->total_amount); ?> VNĐ</strong></td>
                                    </tr>
                                    <?php if ($invoice->paid_amount > 0): ?>
                                    <tr class="table-success">
                                        <td><strong>Đã thanh toán:</strong></td>
                                        <td class="text-end text-success"><strong><?php echo number_format($invoice->paid_amount); ?> VNĐ</strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Còn lại:</strong></td>
                                        <td class="text-end"><strong><?php echo number_format($invoice->total_amount - $invoice->paid_amount); ?> VNĐ</strong></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <!-- Notes -->
                        <?php if ($invoice->notes): ?>
                        <div class="mt-4">
                            <h6 class="text-muted mb-2">Ghi chú</h6>
                            <div class="alert alert-light">
                                <?php echo nl2br(esc_html($invoice->notes)); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Related Invoices Sidebar -->
            <div class="col-lg-4 grid-margin stretch-card d-flex flex-column">
                <?php
                // Generate Payment QR Code if settings are configured and invoice is not paid
                // Check if QR code settings exist (either new format with VAT split or old format)
                $has_qr_settings = (
                    (get_option('payment_bank_code_no_vat') && get_option('payment_account_number_no_vat')) ||
                    (get_option('payment_bank_code_with_vat') && get_option('payment_account_number_with_vat')) ||
                    (get_option('payment_bank_code') && get_option('payment_account_number'))
                );

                if ($has_qr_settings && $invoice->status !== 'paid' && $invoice->status !== 'canceled'):
                    // Calculate remaining amount
                    $remaining_amount = $invoice->total_amount - $invoice->paid_amount;

                    // Generate QR code with invoice code as reference
                    // Pass requires_vat_invoice to select appropriate bank account
                    $qr_add_info = 'HD ' . $invoice->invoice_code;
                    $requires_vat_invoice = isset($invoice->requires_vat_invoice) ? $invoice->requires_vat_invoice : 0;
                    $qr_code_url = generate_payment_qr_code($remaining_amount, $qr_add_info, $requires_vat_invoice);

                    if ($qr_code_url):
                ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="ph ph-qr-code me-2"></i>Thanh toán qua QR Code
                        </h6>
                        <p class="text-muted small mb-3">Quét mã QR để thanh toán nhanh</p>

                        <!-- QR Code Image -->
                        <div class="text-center mb-3">
                            <img src="<?php echo esc_url($qr_code_url); ?>"
                                 alt="Payment QR Code"
                                 class="img-fluid rounded"
                                 style="max-width: 280px; border: 1px solid #ddd; padding: 10px;">
                        </div>

                        <!-- Payment Information -->
                        <div class="alert alert-danger mb-0">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted"><small>Nội dung CK:</small></td>
                                    <td><strong><small><?php echo esc_html($qr_add_info); ?></small></strong></td>
                                </tr>
                            </table>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="ph ph-info me-1"></i>
                                Vui lòng giữ nguyên nội dung chuyển khoản để hệ thống tự động xác nhận thanh toán.
                            </small>
                        </div>
                    </div>
                </div>
                <?php
                    endif;
                endif;
                ?>

                <?php if (!empty($related_invoices)): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="ph ph-link me-2"></i>Hóa đơn liên quan
                        </h6>
                        <p class="text-muted small mb-3">Các hóa đơn khác cho cùng dịch vụ</p>
                        
                        <?php foreach ($related_invoices as $related): ?>
                        <div class="d-flex justify-content-between align-items-center p-3 border rounded mb-2">
                            <div>
                                <div class="fw-bold"><?php echo esc_html($related->invoice_code); ?></div>
                                <small class="text-muted">
                                    <?php 
                                    $related_status_class = $status_classes[$related->status] ?? 'bg-secondary';
                                    echo '<span class="badge ' . $related_status_class . ' badge-sm">' . 
                                         ($status_options[$related->status] ?? $related->status) . '</span>';
                                    ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?php echo number_format($related->total_amount); ?> VNĐ</div>
                                <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $related->id); ?>" 
                                   class="btn btn-sm btn-primary">Xem</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="ph ph-lightning me-2"></i>Thao tác nhanh
                        </h6>
                        
                        <div class="d-flex flex-column">
                            <?php if ($invoice->status !== 'paid'): ?>
                            <button type="button" class="btn btn-success mb-2" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                <i class="ph ph-credit-card me-2"></i>Đánh dấu đã thanh toán
                            </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-danger mb-2" data-bs-toggle="modal" data-bs-target="#statusModal">
                                <i class="ph ph-arrow-clockwise me-2"></i>Cập nhật trạng thái
                            </button>
                            
                            <a href="<?php echo home_url('/danh-sach-hoa-don/'); ?>" class="btn btn-secondary mb-2">
                                <i class="ph ph-arrow-left me-2"></i>Quay lại danh sách
                            </a>

                            <a href="<?php echo admin_url('admin-ajax.php?action=generate_invoice_pdf&invoice_id=' . $invoice->id); ?>"
                               class="btn btn-primary mb-2" target="_blank">
                                <i class="ph ph-file-pdf me-2"></i>Xuất PDF
                            </a>

                            <a href="<?php echo home_url('/print-invoice/?invoice_id=' . $invoice->id); ?>"
                               class="btn btn-info" target="_blank">
                                <i class="ph ph-printer me-2"></i>In hóa đơn
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php wp_nonce_field('invoice_action', 'action_nonce'); ?>
                <input type="hidden" name="action" value="mark_as_paid">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ph ph-credit-card me-2"></i>Đánh dấu đã thanh toán
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Số tiền thanh toán <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="paid_amount" 
                               value="<?php echo $invoice->total_amount - $invoice->paid_amount; ?>" 
                               min="0" max="<?php echo $invoice->total_amount - $invoice->paid_amount; ?>" 
                               step="1000" required>
                        <div class="form-text">Còn lại: <?php echo number_format($invoice->total_amount - $invoice->paid_amount); ?> VNĐ</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ngày thanh toán <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phương thức thanh toán</label>
                        <select class="form-select" name="payment_method">
                            <option value="bank_transfer">Chuyển khoản ngân hàng</option>
                            <option value="cash">Tiền mặt</option>
                            <option value="credit_card">Thẻ tín dụng</option>
                            <option value="e_wallet">Ví điện tử</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ghi chú thanh toán</label>
                        <textarea class="form-control" name="payment_notes" rows="3" 
                                  placeholder="Ghi chú về việc thanh toán (tùy chọn)"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success">
                        <i class="ph ph-check me-2"></i>Xác nhận thanh toán
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php wp_nonce_field('invoice_action', 'action_nonce'); ?>
                <input type="hidden" name="action" value="update_status">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ph ph-arrow-clockwise me-2"></i>Cập nhật trạng thái hóa đơn
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Trạng thái hiện tại</label>
                        <input type="text" class="form-control" 
                               value="<?php echo $status_options[$invoice->status] ?? $invoice->status; ?>" 
                               readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Trạng thái mới <span class="text-danger">*</span></label>
                        <select class="form-select" name="new_status" required>
                            <?php foreach ($status_options as $status_key => $status_label): ?>
                                <?php if ($status_key !== $invoice->status): ?>
                                    <option value="<?php echo $status_key; ?>"><?php echo $status_label; ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ghi chú thay đổi</label>
                        <textarea class="form-control" name="status_notes" rows="3" 
                                  placeholder="Lý do thay đổi trạng thái (tùy chọn)"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-check me-2"></i>Cập nhật trạng thái
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($auto_open_payment && $invoice->status !== 'paid'): ?>
<script>
jQuery(document).ready(function($) {
    console.log('Auto-opening payment modal...');
    // Use Bootstrap 5 modal API
    try {
        var paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        paymentModal.show();
        console.log('Payment modal opened successfully');
    } catch (error) {
        console.error('Error opening payment modal:', error);
        // Fallback to jQuery if Bootstrap 5 is not available
        $('#paymentModal').modal('show');
    }
});
</script>
<?php endif; ?>

<script>
// Form validation for payment modal
jQuery(document).ready(function($) {
    const paymentForm = document.querySelector('#paymentModal form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            
            const paidAmount = parseFloat(document.querySelector('input[name="paid_amount"]').value);
            const maxAmount = parseFloat(document.querySelector('input[name="paid_amount"]').getAttribute('max'));
            
            console.log('Paid amount:', paidAmount, 'Max amount:', maxAmount);
            
            if (paidAmount <= 0) {
                e.preventDefault();
                alert('Số tiền thanh toán phải lớn hơn 0');
                return false;
            }
            
            if (paidAmount > maxAmount) {
                e.preventDefault();
                alert('Số tiền thanh toán không được vượt quá số tiền còn lại');
                return false;
            }
            
            console.log('Form validation passed, submitting...');
            // Show loading indicator
            const submitBtn = paymentForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin me-2"></i>Đang xử lý...';
                submitBtn.disabled = true;
            }
        });
    } else {
        console.error('Payment form not found');
    }
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            // Use Bootstrap 5 Alert API
            var alertInstance = bootstrap.Alert.getOrCreateInstance(alert);
            alertInstance.close();
        }, 5000);
    });
});
</script>

<?php get_footer(); ?>