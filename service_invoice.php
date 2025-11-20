<?php
/* 
    Template Name: Service Invoice Creation - Stage 3: Create Invoice
    Purpose: Create invoice for approved service requests
*/

global $wpdb;
$service_table = $wpdb->prefix . 'im_website_services';
$invoice_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';

// Get service ID from URL
$service_id = $_GET['service_id'] ?? 0;

// Get approved service request data
if ($service_id) {
    $service = $wpdb->get_row($wpdb->prepare("
        SELECT s.*, w.name as website_name, d.domain_name,
               u1.name as requester_name, u1.user_code as requester_code, u1.email as requester_email,
               u2.name as assignee_name, u2.user_code as assignee_code,
               w.owner_user_id
        FROM $service_table s
        LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
        LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
        LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
        LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
        WHERE s.id = %d AND s.status = 'APPROVED'
    ", $service_id));
    
    if (!$service) {
        wp_redirect(home_url('/danh-sach-dich-vu/'));
        exit;
    }

    // Check if invoice already exists for this service
    $existing_invoice = $wpdb->get_row($wpdb->prepare("
        SELECT i.* FROM $invoice_table i
        INNER JOIN $invoice_items_table ii ON i.id = ii.invoice_id
        WHERE ii.service_type = 'website_service' AND ii.service_id = %d
    ", $service_id));
}

/* 
 * Process invoice creation and deletion
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_invoice']) && wp_verify_nonce($_POST['delete_invoice'], 'delete_invoice')) {
        // Delete existing invoice
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete invoice items first
            $wpdb->delete($invoice_items_table, array('invoice_id' => $existing_invoice->id));
            
            // Delete the invoice
            $result = $wpdb->delete($invoice_table, array('id' => $existing_invoice->id));
            
            if ($result === false) {
                throw new Exception('Failed to delete invoice');
            }
            
            $wpdb->query('COMMIT');
            $message = '<div class="alert alert-success"><strong>Thành công!</strong> Hóa đơn đã được xóa. Bạn có thể tạo hóa đơn mới.</div>';
            
            // Reset existing invoice to allow creating new one
            $existing_invoice = null;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Không thể xóa hóa đơn: ' . $e->getMessage() . '</div>';
        }
    }
    elseif (isset($_POST['create_invoice']) && wp_verify_nonce($_POST['create_invoice'], 'create_invoice')) {
        $invoice_date = sanitize_text_field($_POST['invoice_date']);
        $due_date = sanitize_text_field($_POST['due_date']);
        $tax_rate = floatval($_POST['tax_rate']);
        $discount_amount = intval($_POST['discount_amount']);
        $payment_terms = sanitize_textarea_field($_POST['payment_terms']);
        $invoice_notes = sanitize_textarea_field($_POST['invoice_notes']);
        $payment_type = sanitize_text_field($_POST['payment_type']); // NEW: payment type option

        // Calculate pricing
        $unit_price = 0;
        if ($service->pricing_type === 'DAILY') {
            $unit_price = $service->estimated_manday * $service->daily_rate;
        } elseif ($service->pricing_type === 'FIXED') {
            $unit_price = $service->fixed_price;
        }

        $sub_total = $unit_price;
        $discount_total = $discount_amount;
        $tax_amount = ($sub_total - $discount_total) * ($tax_rate / 100);
        $total_amount = $sub_total - $discount_total + $tax_amount;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Get user's VAT invoice preference
            $user_vat_settings = get_user_meta($service->owner_user_id, 'inova_vat_invoice_settings', true);
            $requires_vat_invoice = 0;
            if (!empty($user_vat_settings) && isset($user_vat_settings['requires_vat_invoice'])) {
                $requires_vat_invoice = intval($user_vat_settings['requires_vat_invoice']);
            }

            if ($payment_type === 'partial_50') {
                // Generate invoice codes
                $first_invoice_code = generate_invoice_code($service->owner_user_id);

                // Create first invoice for 50%
                $first_amount = $total_amount * 0.5;
                $invoice_data = array(
                    'invoice_code' => $first_invoice_code,
                    'user_id' => $service->owner_user_id,
                    'invoice_date' => date('Y-m-d', strtotime($invoice_date)),
                    'due_date' => date('Y-m-d', strtotime($due_date)),
                    'sub_total' => $sub_total * 0.5,
                    'discount_total' => $discount_total * 0.5,
                    'tax_amount' => $tax_amount * 0.5,
                    'total_amount' => $first_amount,
                    'paid_amount' => 0,
                    'status' => 'pending',
                    'requires_vat_invoice' => $requires_vat_invoice,
                    'notes' => $invoice_notes . "\n\nThanh toán 50% trước khi bắt đầu thực hiện dịch vụ.",
                    'created_by_type' => 'system',
                    'created_by_id' => get_current_user_id(),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $invoice_result = $wpdb->insert($invoice_table, $invoice_data);
                if ($invoice_result === false) {
                    throw new Exception('Failed to create first invoice');
                }

                $first_invoice_id = $wpdb->insert_id;

                // Insert invoice item for first payment
                $description = $service->title . " (Thanh toán trước - 50%)";
                if ($service->pricing_type === 'DAILY') {
                    $description .= " (Theo ngày công: {$service->estimated_manday} ngày × " . number_format($service->daily_rate) . " VNĐ)";
                }

                $invoice_item_data = array(
                    'invoice_id' => $first_invoice_id,
                    'service_type' => 'website_service',
                    'service_id' => $service_id,
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $unit_price * 0.5,
                    'item_total' => $unit_price * 0.5,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $item_result = $wpdb->insert($invoice_items_table, $invoice_item_data);
                if ($item_result === false) {
                    throw new Exception('Failed to create first invoice item');
                }

                // Create second invoice for remaining 50%
                $second_invoice_code = generate_invoice_code($service->owner_user_id); // This will be called after the first one is in DB, so count is correct
                $second_amount = $total_amount - $first_amount;

                $second_invoice_data = array(
                    'invoice_code' => $second_invoice_code,
                    'user_id' => $service->owner_user_id,
                    'invoice_date' => date('Y-m-d', strtotime($invoice_date)),
                    'due_date' => date('Y-m-d', strtotime($due_date . ' +30 days')), // Due after completion
                    'sub_total' => $sub_total - ($sub_total * 0.5),
                    'discount_total' => $discount_total - ($discount_total * 0.5),
                    'tax_amount' => $tax_amount - ($tax_amount * 0.5),
                    'total_amount' => $second_amount,
                    'paid_amount' => 0,
                    'status' => 'pending_completion', // Special status for completion payment
                    'requires_vat_invoice' => $requires_vat_invoice,
                    'notes' => $invoice_notes . "\n\nThanh toán 50% còn lại sau khi hoàn thành dịch vụ.",
                    'created_by_type' => 'system',
                    'created_by_id' => get_current_user_id(),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $second_invoice_result = $wpdb->insert($invoice_table, $second_invoice_data);
                if ($second_invoice_result === false) {
                    throw new Exception('Failed to create second invoice');
                }

                $second_invoice_id = $wpdb->insert_id;

                // Insert invoice item for second payment
                $second_description = $service->title . " (Thanh toán sau - 50%)";
                if ($service->pricing_type === 'DAILY') {
                    $second_description .= " (Theo ngày công: {$service->estimated_manday} ngày × " . number_format($service->daily_rate) . " VNĐ)";
                }

                $second_item_data = array(
                    'invoice_id' => $second_invoice_id,
                    'service_type' => 'website_service',
                    'service_id' => $service_id,
                    'description' => $second_description,
                    'quantity' => 1,
                    'unit_price' => $unit_price - ($unit_price * 0.5),
                    'item_total' => $unit_price - ($unit_price * 0.5),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $second_item_result = $wpdb->insert($invoice_items_table, $second_item_data);
                if ($second_item_result === false) {
                    throw new Exception('Failed to create second invoice item');
                }

                $message = '<div class="alert alert-success"><strong>Thành công!</strong> Đã tạo 2 hóa đơn: ' . $first_invoice_code . ' (50% trước) và ' . $second_invoice_code . ' (50% sau khi hoàn thành).</div>';
                $created_invoice_id = $first_invoice_id; // Store for redirect
            } else {
                // Generate single invoice code
                $invoice_code = generate_invoice_code($service->owner_user_id);

                // Create single full payment invoice
                $invoice_data = array(
                    'invoice_code' => $invoice_code,
                    'user_id' => $service->owner_user_id,
                    'invoice_date' => date('Y-m-d', strtotime($invoice_date)),
                    'due_date' => date('Y-m-d', strtotime($due_date)),
                    'sub_total' => $sub_total,
                    'discount_total' => $discount_total,
                    'tax_amount' => $tax_amount,
                    'total_amount' => $total_amount,
                    'paid_amount' => 0,
                    'status' => 'draft',
                    'requires_vat_invoice' => $requires_vat_invoice,
                    'notes' => $invoice_notes,
                    'created_by_type' => 'system',
                    'created_by_id' => get_current_user_id(),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $invoice_result = $wpdb->insert($invoice_table, $invoice_data);
                if ($invoice_result === false) {
                    throw new Exception('Failed to create invoice');
                }

                $invoice_id = $wpdb->insert_id;

                // Insert invoice item
                $description = $service->title;
                if ($service->pricing_type === 'DAILY') {
                    $description .= " (Theo ngày công: {$service->estimated_manday} ngày × " . number_format($service->daily_rate) . " VNĐ)";
                }

                $invoice_item_data = array(
                    'invoice_id' => $invoice_id,
                    'service_type' => 'website_service',
                    'service_id' => $service_id,
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $unit_price,
                    'item_total' => $unit_price,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $item_result = $wpdb->insert($invoice_items_table, $invoice_item_data);
                if ($item_result === false) {
                    throw new Exception('Failed to create invoice item');
                }

                $message = '<div class="alert alert-success"><strong>Thành công!</strong> Hóa đơn ' . $invoice_code . ' đã được tạo.</div>';
                $created_invoice_id = $invoice_id; // Store for redirect
            }

            $wpdb->query('COMMIT');
            
            // Redirect to invoice detail page after successful creation
            if (isset($created_invoice_id)) {
                wp_redirect(home_url('/chi-tiet-hoa-don/?invoice_id=' . $created_invoice_id));
                exit;
            }

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Không thể tạo hóa đơn: ' . $e->getMessage() . '</div>';
        }
    }
}

get_header(); 
?>

<div class="main-panel">
    <div class="content-wrapper">
        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="ph ph-receipt me-2"></i>
                            Tạo hóa đơn cho dịch vụ website
                        </h4>
                        <p class="card-description">Giai đoạn 3: Tạo hóa đơn cho yêu cầu đã được duyệt</p>
                        
                        <!-- Display messages -->
                        <?php if (isset($message)) echo $message; ?>
                        <?php if (isset($error_message)) echo $error_message; ?>

                        <?php if ($existing_invoice): ?>
                        <div class="alert alert-warning">
                            <i class="ph ph-warning me-2"></i>
                            <strong>Hóa đơn đã tồn tại!</strong> 
                            Yêu cầu này đã có hóa đơn với mã: <strong><?php echo esc_html($existing_invoice->invoice_code); ?></strong>
                            <br>
                            <div class="mt-2">
                                <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $existing_invoice->id); ?>" class="btn btn-sm btn-primary me-2">
                                    <i class="ph ph-eye me-1"></i>Xem hóa đơn
                                </a>
                                <form method="post" style="display: inline-block;">
                                    <?php wp_nonce_field('delete_invoice', 'delete_invoice'); ?>
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa hóa đơn này? Hành động này không thể hoàn tác.')">
                                        <i class="ph ph-trash me-1"></i>Xóa hóa đơn
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Service Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">
                                            <i class="ph ph-check-circle me-2"></i>
                                            Thông tin dịch vụ đã duyệt
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Mã yêu cầu:</strong> <?php echo esc_html($service->service_code); ?></p>
                                                <p><strong>Website:</strong> <?php echo esc_html($service->website_name); ?></p>
                                                <p><strong>Tiêu đề:</strong> <?php echo esc_html($service->title); ?></p>
                                                <p><strong>Khách hàng:</strong> <?php echo esc_html($service->requester_name . ' (' . $service->requester_code . ')'); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Loại định giá:</strong> 
                                                    <span class="badge badge-info">
                                                        <?php echo $service->pricing_type === 'DAILY' ? 'Theo ngày công' : 'Giá cố định'; ?>
                                                    </span>
                                                </p>
                                                <?php if ($service->pricing_type === 'DAILY'): ?>
                                                    <p><strong>Ngày công:</strong> <?php echo esc_html($service->estimated_manday); ?> ngày</p>
                                                    <p><strong>Đơn giá:</strong> <?php echo number_format($service->daily_rate); ?> VNĐ/ngày</p>
                                                    <p><strong>Tổng giá trị:</strong> <?php echo number_format($service->estimated_manday * $service->daily_rate); ?> VNĐ</p>
                                                <?php else: ?>
                                                    <p><strong>Giá cố định:</strong> <?php echo number_format($service->fixed_price); ?> VNĐ</p>
                                                <?php endif; ?>
                                                <p><strong>Người thực hiện:</strong> <?php echo esc_html($service->assignee_name . ' (' . $service->assignee_code . ')'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!$existing_invoice): ?>
                        <!-- Invoice Creation Form -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-file-text me-2"></i>
                                            Thông tin hóa đơn
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form class="forms-sample" method="post" action="">
                                            <?php wp_nonce_field('create_invoice', 'create_invoice'); ?>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="invoice_date" class="fw-bold">Ngày hóa đơn <span class="text-danger">*</span></label>
                                                        <input type="date" class="form-control" id="invoice_date" name="invoice_date" 
                                                               value="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="due_date" class="fw-bold">Hạn thanh toán <span class="text-danger">*</span></label>
                                                        <input type="date" class="form-control" id="due_date" name="due_date" 
                                                               value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="tax_rate" class="fw-bold">Thuế VAT (%)</label>
                                                        <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                                                               value="10" min="0" max="100" step="0.1">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="discount_amount" class="fw-bold">Giảm giá (VNĐ)</label>
                                                        <input type="number" class="form-control" id="discount_amount" name="discount_amount" 
                                                               value="0" min="0">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="payment_type" class="fw-bold">Tùy chọn thanh toán <span class="text-danger">*</span></label>
                                                <select class="form-control" id="payment_type" name="payment_type" required>
                                                    <option value="full">Thanh toán toàn bộ (100%)</option>
                                                    <option value="partial_50">Thanh toán theo giai đoạn (50% trước + 50% sau khi hoàn thành)</option>
                                                </select>
                                                <small class="form-text text-muted">
                                                    • <strong>Thanh toán toàn bộ:</strong> Tạo 1 hóa đơn cho toàn bộ số tiền<br>
                                                    • <strong>Thanh toán theo giai đoạn:</strong> Tạo 2 hóa đơn - 50% thanh toán trước khi bắt đầu, 50% thanh toán sau khi hoàn thành
                                                </small>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="payment_terms" class="fw-bold">Điều khoản thanh toán</label>
                                                <textarea class="form-control" id="payment_terms" name="payment_terms" rows="3" 
                                                          placeholder="Ví dụ: Thanh toán trong vòng 30 ngày kể từ ngày xuất hóa đơn...">Thanh toán trong vòng 30 ngày kể từ ngày xuất hóa đơn.</textarea>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="invoice_notes" class="fw-bold">Ghi chú hóa đơn</label>
                                                <textarea class="form-control" id="invoice_notes" name="invoice_notes" rows="3" 
                                                          placeholder="Ghi chú bổ sung cho hóa đơn..."></textarea>
                                            </div>

                                            <button type="submit" class="btn btn-primary me-2">
                                                <i class="ph ph-receipt me-2"></i>Tạo hóa đơn
                                            </button>
                                            <a href="<?php echo home_url('/danh-sach-dich-vu/'); ?>" class="btn btn-light">
                                                <i class="ph ph-arrow-left me-2"></i>Quay lại
                                            </a>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Preview -->
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-eye me-2"></i>
                                            Xem trước hóa đơn
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="invoice_preview">
                                            <h6 class="fw-bold">Chi tiết tính toán</h6>
                                            <table class="table table-sm">
                                                <tr>
                                                    <td>Dịch vụ:</td>
                                                    <td class="text-end" id="service_price">
                                                        <?php 
                                                        $price = $service->pricing_type === 'DAILY' ? 
                                                                ($service->estimated_manday * $service->daily_rate) : 
                                                                $service->fixed_price;
                                                        echo number_format($price);
                                                        ?> VNĐ
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Giảm giá:</td>
                                                    <td class="text-end" id="discount_display">0 VNĐ</td>
                                                </tr>
                                                <tr>
                                                    <td>Thuế VAT:</td>
                                                    <td class="text-end" id="tax_display">
                                                        <?php echo number_format($price * 0.1); ?> VNĐ
                                                    </td>
                                                </tr>
                                                <tr class="fw-bold border-top">
                                                    <td>Tổng cộng:</td>
                                                    <td class="text-end text-success" id="total_display">
                                                        <?php echo number_format($price + ($price * 0.1)); ?> VNĐ
                                                    </td>
                                                </tr>
                                            </table>
                                            
                                            <div id="payment_breakdown" style="display: none;">
                                                <h6 class="fw-bold text-info mt-3">Thanh toán theo giai đoạn</h6>
                                                <table class="table table-sm table-bordered">
                                                    <tr class="table-light">
                                                        <td><strong>Hóa đơn 1 (50%):</strong></td>
                                                        <td class="text-end text-primary" id="first_payment_display">
                                                            <?php echo number_format(($price + ($price * 0.1)) * 0.5); ?> VNĐ
                                                        </td>
                                                    </tr>
                                                    <tr class="table-light">
                                                        <td><strong>Hóa đơn 2 (50%):</strong></td>
                                                        <td class="text-end text-warning" id="second_payment_display">
                                                            <?php echo number_format(($price + ($price * 0.1)) * 0.5); ?> VNĐ
                                                        </td>
                                                    </tr>
                                                </table>
                                                <small class="text-muted">
                                                    • Hóa đơn 1: Thanh toán trước khi bắt đầu thực hiện<br>
                                                    • Hóa đơn 2: Thanh toán sau khi hoàn thành dịch vụ
                                                </small>
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <h6 class="fw-bold">Thông tin bổ sung</h6>
                                            <small class="text-muted" id="additional_info">
                                                • Hóa đơn sẽ có trạng thái "Nháp"<br>
                                                • Cần thanh toán để chuyển sang "Đã thanh toán"<br>
                                                • Sau khi thanh toán, dịch vụ sẽ chuyển sang "Đang thực hiện"
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const taxRate = document.getElementById('tax_rate');
    const discountAmount = document.getElementById('discount_amount');
    const paymentType = document.getElementById('payment_type');
    const servicePrice = <?php echo $service->pricing_type === 'DAILY' ? ($service->estimated_manday * $service->daily_rate) : $service->fixed_price; ?>;

    function updatePreview() {
        const tax = parseFloat(taxRate.value) || 0;
        const discount = parseFloat(discountAmount.value) || 0;
        const paymentMethod = paymentType.value;
        
        const subtotal = servicePrice;
        const discountTotal = discount;
        const taxAmount = (subtotal - discountTotal) * (tax / 100);
        const total = subtotal - discountTotal + taxAmount;

        document.getElementById('discount_display').textContent = discountTotal.toLocaleString('vi-VN') + ' VNĐ';
        document.getElementById('tax_display').textContent = taxAmount.toLocaleString('vi-VN') + ' VNĐ';
        document.getElementById('total_display').textContent = total.toLocaleString('vi-VN') + ' VNĐ';

        // Show/hide payment breakdown based on payment type
        const paymentBreakdown = document.getElementById('payment_breakdown');
        const additionalInfo = document.getElementById('additional_info');
        
        if (paymentMethod === 'partial_50') {
            paymentBreakdown.style.display = 'block';
            
            const firstPayment = total * 0.5;
            const secondPayment = total - firstPayment;
            
            document.getElementById('first_payment_display').textContent = firstPayment.toLocaleString('vi-VN') + ' VNĐ';
            document.getElementById('second_payment_display').textContent = secondPayment.toLocaleString('vi-VN') + ' VNĐ';
            
            additionalInfo.innerHTML = `
                • Sẽ tạo 2 hóa đơn riêng biệt<br>
                • Hóa đơn 1: Thanh toán trước (50%) - trạng thái "Nháp"<br>
                • Hóa đơn 2: Thanh toán sau (50%) - trạng thái "Chờ hoàn thành"<br>
                • Dịch vụ chỉ bắt đầu sau khi thanh toán hóa đơn đầu tiên
            `;
        } else {
            paymentBreakdown.style.display = 'none';
            additionalInfo.innerHTML = `
                • Hóa đơn sẽ có trạng thái "Nháp"<br>
                • Cần thanh toán để chuyển sang "Đã thanh toán"<br>
                • Sau khi thanh toán, dịch vụ sẽ chuyển sang "Đang thực hiện"
            `;
        }
    }

    if (taxRate) taxRate.addEventListener('input', updatePreview);
    if (discountAmount) discountAmount.addEventListener('input', updatePreview);
    if (paymentType) paymentType.addEventListener('change', updatePreview);
    
    // Initialize preview
    updatePreview();
});
</script>

<?php get_footer(); ?>
