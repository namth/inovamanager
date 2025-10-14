<?php
/* 
    Template Name: Service Execution - Stage 4: Work Execution & Progress Tracking
    Purpose: Track service execution progress and manage work status
*/

global $wpdb;
$service_table = $wpdb->prefix . 'im_website_services';
$invoice_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';

// Get service ID from URL
$service_id = $_GET['service_id'] ?? 0;

// Get service data
if ($service_id) {
    $service = $wpdb->get_row($wpdb->prepare("
        SELECT s.*, w.name as website_name, d.domain_name,
               u1.name as requester_name, u1.user_code as requester_code,
               u2.name as assignee_name, u2.user_code as assignee_code
        FROM $service_table s
        LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
        LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
        LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
        LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
        WHERE s.id = %d
    ", $service_id));
    
    if (!$service || !in_array($service->status, ['APPROVED', 'IN_PROGRESS'])) {
        wp_redirect(home_url('/danh-sach-dich-vu/'));
        exit;
    }

    // Get related invoices (could be multiple for partial payments)
    $invoices = $wpdb->get_results($wpdb->prepare("
        SELECT i.* FROM $invoice_table i
        INNER JOIN $invoice_items_table ii ON i.id = ii.invoice_id
        WHERE ii.service_type = 'website_service' AND ii.service_id = %d
        ORDER BY i.created_at ASC
    ", $service_id));
    
    // Get the first invoice for payment (main invoice or first 50%)
    $invoice = !empty($invoices) ? $invoices[0] : null;
}

/* 
 * Process payment confirmation
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['confirm_payment']) && wp_verify_nonce($_POST['confirm_payment'], 'confirm_payment')) {
        $payment_date = sanitize_text_field($_POST['payment_date']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        $payment_amount = intval($_POST['payment_amount']);
        $payment_notes = sanitize_textarea_field($_POST['payment_notes']);

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Update invoice status to PAID
            $invoice_update = $wpdb->update(
                $invoice_table,
                array(
                    'status' => 'paid',
                    'paid_amount' => $payment_amount,
                    'payment_date' => date('Y-m-d H:i:s', strtotime($payment_date)),
                    'payment_method' => $payment_method,
                    'notes' => $payment_notes,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $invoice->id)
            );

            // Update service status to IN_PROGRESS and set start_date
            $service_update = $wpdb->update(
                $service_table,
                array(
                    'status' => 'IN_PROGRESS',
                    'start_date' => date('Y-m-d'),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $service_id)
            );

            if ($invoice_update !== false && $service_update !== false) {
                $wpdb->query('COMMIT');
                $message = '<div class="alert alert-success"><strong>Thành công!</strong> Thanh toán đã được xác nhận và công việc bắt đầu thực hiện.</div>';
                
                // Refresh data
                $service = $wpdb->get_row($wpdb->prepare("
                    SELECT s.*, w.name as website_name, d.domain_name,
                           u1.name as requester_name, u1.user_code as requester_code,
                           u2.name as assignee_name, u2.user_code as assignee_code
                    FROM $service_table s
                    LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
                    LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
                    LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
                    LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
                    WHERE s.id = %d
                ", $service_id));

                $invoice = $wpdb->get_row($wpdb->prepare("
                    SELECT i.* FROM $invoice_table i
                    INNER JOIN $invoice_items_table ii ON i.id = ii.invoice_id
                    WHERE ii.service_type = 'website_service' AND ii.service_id = %d
                ", $service_id));
            } else {
                throw new Exception('Failed to update payment status');
            }

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Không thể xác nhận thanh toán: ' . $e->getMessage() . '</div>';
        }
    }

    // Process progress update
    if (isset($_POST['update_progress']) && wp_verify_nonce($_POST['update_progress'], 'update_progress')) {
        $progress_notes = sanitize_textarea_field($_POST['progress_notes']);
        $estimated_completion_date = !empty($_POST['estimated_completion_date']) ? 
            date('Y-m-d', strtotime(str_replace('/', '-', $_POST['estimated_completion_date']))) : null;

        $update_data = array(
            'notes' => $progress_notes,
            'updated_at' => current_time('mysql')
        );

        if ($estimated_completion_date) {
            $update_data['estimated_completion_date'] = $estimated_completion_date;
        }

        $result = $wpdb->update($service_table, $update_data, array('id' => $service_id));

        if ($result !== false) {
            $message = '<div class="alert alert-success"><strong>Thành công!</strong> Tiến độ công việc đã được cập nhật.</div>';
            // Refresh service data
            $service = $wpdb->get_row($wpdb->prepare("
                SELECT s.*, w.name as website_name, d.domain_name,
                       u1.name as requester_name, u1.user_code as requester_code,
                       u2.name as assignee_name, u2.user_code as assignee_code
                FROM $service_table s
                LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
                LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
                LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
                LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
                WHERE s.id = %d
            ", $service_id));
        } else {
            $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Không thể cập nhật tiến độ.</div>';
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
                            <i class="ph ph-gear me-2"></i>
                            Thực hiện công việc dịch vụ
                        </h4>
                        <p class="card-description">Giai đoạn 4: Thực hiện công việc & theo dõi tiến độ</p>
                        
                        <!-- Display messages -->
                        <?php if (isset($message)) echo $message; ?>
                        <?php if (isset($error_message)) echo $error_message; ?>

                        <!-- Service Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">
                                            <i class="ph ph-info me-2"></i>
                                            Thông tin dịch vụ
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
                                                <p><strong>Trạng thái:</strong> 
                                                    <span class="badge badge-<?php 
                                                        echo $service->status === 'APPROVED' ? 'success' : 
                                                            ($service->status === 'IN_PROGRESS' ? 'primary' : 'secondary'); 
                                                    ?>">
                                                        <?php 
                                                        $status_text = array(
                                                            'APPROVED' => 'Đã duyệt',
                                                            'IN_PROGRESS' => 'Đang thực hiện'
                                                        );
                                                        echo $status_text[$service->status] ?? $service->status; 
                                                        ?>
                                                    </span>
                                                </p>
                                                <p><strong>Người thực hiện:</strong> <?php echo esc_html($service->assignee_name . ' (' . $service->assignee_code . ')'); ?></p>
                                                <p><strong>Ngày bắt đầu:</strong> <?php echo $service->start_date ? date('d/m/Y', strtotime($service->start_date)) : 'Chưa bắt đầu'; ?></p>
                                                <p><strong>Dự kiến hoàn thành:</strong> <?php echo $service->estimated_completion_date ? date('d/m/Y', strtotime($service->estimated_completion_date)) : 'Chưa xác định'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Invoice Status -->
                        <?php if (!empty($invoices)): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <?php if (count($invoices) > 1): ?>
                                    <!-- Multiple invoices (partial payment) -->
                                    <div class="card border-info">
                                        <div class="card-header bg-info text-white">
                                            <h5 class="mb-0">
                                                <i class="ph ph-receipt me-2"></i>
                                                Thông tin hóa đơn (Thanh toán theo giai đoạn)
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <?php foreach ($invoices as $index => $inv): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card border-<?php echo $inv->status === 'paid' ? 'success' : ($inv->status === 'pending_completion' ? 'secondary' : 'warning'); ?>">
                                                        <div class="card-header bg-<?php echo $inv->status === 'paid' ? 'success' : ($inv->status === 'pending_completion' ? 'secondary' : 'warning'); ?> text-white">
                                                            <h6 class="mb-0">Hóa đơn <?php echo $index + 1; ?> (<?php echo $index == 0 ? '50% trước' : '50% sau'; ?>)</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <p><strong>Mã:</strong> <?php echo esc_html($inv->invoice_code); ?></p>
                                                            <p><strong>Tổng tiền:</strong> <?php echo number_format($inv->total_amount); ?> VNĐ</p>
                                                            <p><strong>Trạng thái:</strong> 
                                                                <span class="badge badge-<?php echo $inv->status === 'paid' ? 'success' : ($inv->status === 'pending_completion' ? 'secondary' : 'warning'); ?>">
                                                                    <?php 
                                                                    if ($inv->status === 'paid') echo 'Đã thanh toán';
                                                                    elseif ($inv->status === 'pending_completion') echo 'Chờ hoàn thành';
                                                                    else echo 'Chưa thanh toán';
                                                                    ?>
                                                                </span>
                                                            </p>
                                                            <?php if ($inv->payment_date): ?>
                                                            <p><strong>Ngày thanh toán:</strong> <?php echo date('d/m/Y H:i', strtotime($inv->payment_date)); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Single invoice (full payment) -->
                                    <?php $invoice = $invoices[0]; ?>
                                    <div class="card border-<?php echo $invoice->status === 'paid' ? 'success' : 'warning'; ?>">
                                        <div class="card-header bg-<?php echo $invoice->status === 'paid' ? 'success' : 'warning'; ?> text-white">
                                            <h5 class="mb-0">
                                                <i class="ph ph-receipt me-2"></i>
                                                Thông tin hóa đơn
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Mã hóa đơn:</strong> <?php echo esc_html($invoice->invoice_code); ?></p>
                                                    <p><strong>Ngày hóa đơn:</strong> <?php echo date('d/m/Y', strtotime($invoice->invoice_date)); ?></p>
                                                    <p><strong>Hạn thanh toán:</strong> <?php echo date('d/m/Y', strtotime($invoice->due_date)); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Tổng tiền:</strong> <?php echo number_format($invoice->total_amount); ?> VNĐ</p>
                                                    <p><strong>Đã thanh toán:</strong> <?php echo number_format($invoice->paid_amount); ?> VNĐ</p>
                                                    <p><strong>Trạng thái:</strong> 
                                                        <span class="badge badge-<?php echo $invoice->status === 'paid' ? 'success' : 'warning'; ?>">
                                                            <?php echo $invoice->status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán'; ?>
                                                        </span>
                                                    </p>
                                                    <?php if ($invoice->payment_date): ?>
                                                    <p><strong>Ngày thanh toán:</strong> <?php echo date('d/m/Y H:i', strtotime($invoice->payment_date)); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row">
                            <!-- Payment Confirmation (if not paid) -->
                            <?php if ($invoice && $invoice->status !== 'paid'): ?>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-warning text-dark">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-credit-card me-2"></i>
                                            Xác nhận thanh toán
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-warning">
                                            <i class="ph ph-warning me-2"></i>
                                            <strong>Chưa thanh toán!</strong><br>
                                            Cần xác nhận thanh toán để bắt đầu thực hiện công việc.
                                        </div>

                                        <form method="post" action="">
                                            <?php wp_nonce_field('confirm_payment', 'confirm_payment'); ?>
                                            
                                            <div class="form-group mb-3">
                                                <label for="payment_date" class="fw-bold">Ngày thanh toán <span class="text-danger">*</span></label>
                                                <input type="datetime-local" class="form-control" id="payment_date" name="payment_date" 
                                                       value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="payment_method" class="fw-bold">Phương thức thanh toán</label>
                                                <select class="form-control" id="payment_method" name="payment_method">
                                                    <option value="bank_transfer">Chuyển khoản ngân hàng</option>
                                                    <option value="cash">Tiền mặt</option>
                                                    <option value="credit_card">Thẻ tín dụng</option>
                                                    <option value="e_wallet">Ví điện tử</option>
                                                    <option value="other">Khác</option>
                                                </select>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="payment_amount" class="fw-bold">Số tiền thanh toán <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                                                       value="<?php echo $invoice->total_amount; ?>" min="1" required>
                                                <small class="form-text text-muted">Tổng hóa đơn: <?php echo number_format($invoice->total_amount); ?> VNĐ</small>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="payment_notes" class="fw-bold">Ghi chú thanh toán</label>
                                                <textarea class="form-control" id="payment_notes" name="payment_notes" rows="3" 
                                                          placeholder="Ghi chú về thanh toán..."></textarea>
                                            </div>

                                            <button type="submit" class="btn btn-warning">
                                                <i class="ph ph-check me-2"></i>Xác nhận thanh toán
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Progress Tracking -->
                            <?php if ($service->status === 'IN_PROGRESS'): ?>
                            <div class="col-md-<?php echo ($invoice && $invoice->status !== 'PAID') ? '6' : '8'; ?>">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-chart-line me-2"></i>
                                            Cập nhật tiến độ
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <?php wp_nonce_field('update_progress', 'update_progress'); ?>
                                            
                                            <div class="form-group mb-3">
                                                <label for="estimated_completion_date" class="fw-bold">Cập nhật ngày hoàn thành</label>
                                                <input type="date" class="form-control" id="estimated_completion_date" name="estimated_completion_date" 
                                                       value="<?php echo $service->estimated_completion_date ? date('Y-m-d', strtotime($service->estimated_completion_date)) : ''; ?>">
                                                <small class="form-text text-muted">Chọn ngày dự kiến hoàn thành</small>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="progress_notes" class="fw-bold">Tiến độ công việc</label>
                                                <textarea class="form-control" id="progress_notes" name="progress_notes" rows="6" 
                                                          placeholder="Mô tả tiến độ công việc, khó khăn gặp phải, kế hoạch tiếp theo..."><?php echo esc_textarea($service->notes); ?></textarea>
                                            </div>

                                            <button type="submit" class="btn btn-primary">
                                                <i class="ph ph-upload me-2"></i>Cập nhật tiến độ
                                            </button>
                                            <a href="<?php echo home_url('/completion/?service_id=' . $service->id); ?>" class="btn btn-success ms-2">
                                                <i class="ph ph-check-circle me-2"></i>Hoàn thành công việc
                                            </a>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Service Details -->
                            <div class="col-md-<?php echo ($service->status === 'IN_PROGRESS') ? '4' : '12'; ?>">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-note me-2"></i>
                                            Chi tiết yêu cầu
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="fw-bold">Mô tả chi tiết:</h6>
                                        <div class="border p-3 bg-light mb-3">
                                            <?php echo nl2br(esc_html($service->description)); ?>
                                        </div>

                                        <?php if ($service->notes): ?>
                                        <h6 class="fw-bold">Ghi chú hiện tại:</h6>
                                        <div class="border p-3 bg-light">
                                            <?php echo nl2br(esc_html($service->notes)); ?>
                                        </div>
                                        <?php endif; ?>

                                        <div class="mt-3">
                                            <h6 class="fw-bold text-muted">Thông tin thêm:</h6>
                                            <small class="text-muted">
                                                • <strong>Loại định giá:</strong> <?php echo $service->pricing_type === 'DAILY' ? 'Theo ngày công' : 'Giá cố định'; ?><br>
                                                <?php if ($service->pricing_type === 'DAILY'): ?>
                                                • <strong>Ước tính:</strong> <?php echo $service->estimated_manday; ?> ngày × <?php echo number_format($service->daily_rate); ?> VNĐ<br>
                                                <?php else: ?>
                                                • <strong>Giá:</strong> <?php echo number_format($service->fixed_price); ?> VNĐ<br>
                                                <?php endif; ?>
                                                • <strong>Độ ưu tiên:</strong> <?php echo $service->priority; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <a href="<?php echo home_url('/danh-sach-dich-vu/'); ?>" class="btn btn-light">
                                    <i class="ph ph-arrow-left me-2"></i>Quay lại danh sách
                                </a>
                                <?php if ($invoice): ?>
                                <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $invoice->id); ?>" class="btn btn-outline-primary ms-2">
                                    <i class="ph ph-receipt me-2"></i>Xem hóa đơn
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
