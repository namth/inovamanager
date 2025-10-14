<?php
/* 
    Template Name: Service Completion - Stage 5: Completion & Handover
    Purpose: Complete service and handover to customer
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
               u1.name as requester_name, u1.user_code as requester_code, u1.email as requester_email,
               u2.name as assignee_name, u2.user_code as assignee_code
        FROM $service_table s
        LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
        LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
        LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
        LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
        WHERE s.id = %d AND s.status = 'IN_PROGRESS'
    ", $service_id));
    
    if (!$service) {
        wp_redirect(home_url('/danh-sach-dich-vu/'));
        exit;
    }

    // Get related invoices to check for second payment
    $invoices = $wpdb->get_results($wpdb->prepare("
        SELECT i.* FROM $invoice_table i
        INNER JOIN $invoice_items_table ii ON i.id = ii.invoice_id
        WHERE ii.service_type = 'website_service' AND ii.service_id = %d
        ORDER BY i.created_at ASC
    ", $service_id));
    
    // Check if there's a second invoice waiting for completion payment
    $second_invoice = null;
    if (count($invoices) > 1) {
        $second_invoice = $invoices[1]; // Second invoice (50% completion payment)
    }
}

/* 
 * Process completion form submission
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['complete_service']) && wp_verify_nonce($_POST['complete_service'], 'complete_service')) {
        $completion_notes = sanitize_textarea_field($_POST['completion_notes']);
        $customer_feedback = sanitize_textarea_field($_POST['customer_feedback']);
        $handover_details = sanitize_textarea_field($_POST['handover_details']);
        $completion_date = sanitize_text_field($_POST['completion_date']);

        // Validate required fields
        if (!empty($completion_notes) && !empty($completion_date)) {
            // Start transaction
            $wpdb->query('START TRANSACTION');
            
            try {
                $update_data = array(
                    'status' => 'COMPLETED',
                    'completion_date' => $completion_date,
                    'notes' => $completion_notes . "\n\n--- BÀN GIAO ---\n" . $handover_details . 
                              (!empty($customer_feedback) ? "\n\n--- PHẢN HỒI KHÁCH HÀNG ---\n" . $customer_feedback : ''),
                    'updated_at' => current_time('mysql')
                );

                $result = $wpdb->update($service_table, $update_data, array('id' => $service_id));
                
                if ($result === false) {
                    throw new Exception('Failed to update service status');
                }

                // If there's a second invoice (completion payment), activate it
                if ($second_invoice && $second_invoice->status === 'pending_completion') {
                    $invoice_update = $wpdb->update(
                        $invoice_table,
                        array(
                            'status' => 'draft', // Change from pending_completion to draft so it can be paid
                            'due_date' => date('Y-m-d', strtotime('+30 days')), // Set new due date
                            'updated_at' => current_time('mysql')
                        ),
                        array('id' => $second_invoice->id)
                    );
                    
                    if ($invoice_update === false) {
                        throw new Exception('Failed to activate completion payment invoice');
                    }
                }
                
                $wpdb->query('COMMIT');
                
                if ($second_invoice) {
                    $message = '<div class="alert alert-success"><strong>Thành công!</strong> Dịch vụ đã được hoàn thành và bàn giao. Hóa đơn thanh toán 50% còn lại đã được kích hoạt.</div>';
                } else {
                    $message = '<div class="alert alert-success"><strong>Thành công!</strong> Dịch vụ đã được hoàn thành và bàn giao.</div>';
                }
                
                // Refresh service data
                $service = $wpdb->get_row($wpdb->prepare("
                    SELECT s.*, w.name as website_name, d.domain_name,
                           u1.name as requester_name, u1.user_code as requester_code, u1.email as requester_email,
                           u2.name as assignee_name, u2.user_code as assignee_code
                    FROM $service_table s
                    LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
                    LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
                    LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
                    LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
                    WHERE s.id = %d
                ", $service_id));
                
                // Refresh invoice data
                $invoices = $wpdb->get_results($wpdb->prepare("
                    SELECT i.* FROM $invoice_table i
                    INNER JOIN $invoice_items_table ii ON i.id = ii.invoice_id
                    WHERE ii.service_type = 'website_service' AND ii.service_id = %d
                    ORDER BY i.created_at ASC
                ", $service_id));
                
                if (count($invoices) > 1) {
                    $second_invoice = $invoices[1];
                }
                
            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Không thể hoàn thành dịch vụ: ' . $e->getMessage() . '</div>';
            }
        } else {
            $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Vui lòng điền đầy đủ thông tin bắt buộc.</div>';
        }
    }
}

// Calculate service duration and metrics
$start_date = $service->start_date ? new DateTime($service->start_date) : null;
$completion_date = $service->completion_date ? new DateTime($service->completion_date) : new DateTime();
$duration_days = $start_date ? $start_date->diff($completion_date)->days : 0;

// Calculate estimated vs actual
$estimated_date = $service->estimated_completion_date ? new DateTime($service->estimated_completion_date) : null;
$is_ontime = $estimated_date ? ($completion_date <= $estimated_date) : null;

get_header(); 
?>

<div class="main-panel">
    <div class="content-wrapper">
        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="ph ph-check-circle me-2"></i>
                            Hoàn thành & bàn giao dịch vụ
                        </h4>
                        <p class="card-description">Giai đoạn 5: Hoàn thành công việc và bàn giao cho khách hàng</p>
                        
                        <!-- Display messages -->
                        <?php if (isset($message)) echo $message; ?>
                        <?php if (isset($error_message)) echo $error_message; ?>

                        <!-- Service Summary -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">
                                            <i class="ph ph-info me-2"></i>
                                            Tóm tắt dịch vụ
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>Mã yêu cầu:</strong> <?php echo esc_html($service->service_code); ?></p>
                                                        <p><strong>Website:</strong> <?php echo esc_html($service->website_name); ?></p>
                                                        <p><strong>Tiêu đề:</strong> <?php echo esc_html($service->title); ?></p>
                                                        <p><strong>Khách hàng:</strong> <?php echo esc_html($service->requester_name . ' (' . $service->requester_code . ')'); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Người thực hiện:</strong> <?php echo esc_html($service->assignee_name . ' (' . $service->assignee_code . ')'); ?></p>
                                                        <p><strong>Ngày bắt đầu:</strong> <?php echo $service->start_date ? date('d/m/Y H:i', strtotime($service->start_date)) : 'N/A'; ?></p>
                                                        <p><strong>Thời gian thực hiện:</strong> 
                                                            <?php if ($duration_days > 0): ?>
                                                                <?php echo $duration_days; ?> ngày
                                                            <?php else: ?>
                                                                < 1 ngày
                                                            <?php endif; ?>
                                                        </p>
                                                        <p><strong>Trạng thái:</strong> 
                                                            <span class="badge badge-<?php echo $service->status === 'COMPLETED' ? 'success' : 'primary'; ?>">
                                                                <?php echo $service->status === 'COMPLETED' ? 'Đã hoàn thành' : 'Đang thực hiện'; ?>
                                                            </span>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h6 class="fw-bold text-center">Thống kê thực hiện</h6>
                                                        <?php if ($estimated_date): ?>
                                                        <div class="text-center mb-2">
                                                            <span class="badge badge-<?php echo $is_ontime ? 'success' : 'warning'; ?> p-2">
                                                                <i class="ph ph-<?php echo $is_ontime ? 'check' : 'clock'; ?> me-1"></i>
                                                                <?php echo $is_ontime ? 'Đúng hạn' : 'Trễ hạn'; ?>
                                                            </span>
                                                        </div>
                                                        <small class="text-muted">
                                                            <strong>Dự kiến:</strong> <?php echo date('d/m/Y', strtotime($service->estimated_completion_date)); ?><br>
                                                            <strong>Thực tế:</strong> <?php echo $service->completion_date ? date('d/m/Y', strtotime($service->completion_date)) : date('d/m/Y'); ?>
                                                        </small>
                                                        <?php else: ?>
                                                        <div class="text-center">
                                                            <small class="text-muted">Không có ước tính</small>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Second Invoice Notification -->
                        <?php if ($second_invoice): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-<?php echo $second_invoice->status === 'draft' ? 'warning' : 'info'; ?> border-<?php echo $second_invoice->status === 'draft' ? 'warning' : 'info'; ?>">
                                    <div class="d-flex align-items-center">
                                        <i class="ph ph-receipt me-3 fs-4"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?php if ($second_invoice->status === 'draft'): ?>
                                                    <strong>Hóa đơn thanh toán 50% còn lại đã sẵn sàng!</strong>
                                                <?php else: ?>
                                                    <strong>Hóa đơn thanh toán 50% còn lại</strong>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-2">
                                                Mã hóa đơn: <strong><?php echo esc_html($second_invoice->invoice_code); ?></strong> | 
                                                Số tiền: <strong><?php echo number_format($second_invoice->total_amount); ?> VNĐ</strong> |
                                                Trạng thái: 
                                                <span class="badge badge-<?php echo $second_invoice->status === 'draft' ? 'warning' : 'secondary'; ?>">
                                                    <?php echo $second_invoice->status === 'draft' ? 'Sẵn sàng thanh toán' : 'Chờ hoàn thành dịch vụ'; ?>
                                                </span>
                                            </p>
                                            <?php if ($second_invoice->status === 'draft'): ?>
                                            <p class="mb-0 text-muted">
                                                <small>Khách hàng có thể thanh toán hóa đơn này ngay sau khi dịch vụ được hoàn thành.</small>
                                            </p>
                                            <?php else: ?>
                                            <p class="mb-0 text-muted">
                                                <small>Hóa đơn này sẽ được kích hoạt tự động sau khi bạn hoàn thành dịch vụ.</small>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($second_invoice->status === 'draft'): ?>
                                        <div>
                                            <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $second_invoice->id); ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="ph ph-eye me-1"></i>Xem hóa đơn
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($service->status !== 'COMPLETED'): ?>
                        <!-- Completion Form -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-file-text me-2"></i>
                                            Thông tin hoàn thành
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form class="forms-sample" method="post" action="">
                                            <?php wp_nonce_field('complete_service', 'complete_service'); ?>
                                            
                                            <div class="form-group mb-3">
                                                <label for="completion_date" class="fw-bold">Ngày hoàn thành <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="completion_date" name="completion_date" 
                                                       value="<?php echo date('Y-m-d'); ?>" required>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="completion_notes" class="fw-bold">Báo cáo hoàn thành <span class="text-danger">*</span></label>
                                                <textarea class="form-control" id="completion_notes" name="completion_notes" rows="6" 
                                                          placeholder="Mô tả chi tiết công việc đã hoàn thành, kết quả đạt được..." required><?php echo esc_textarea($service->notes); ?></textarea>
                                                <small class="form-text text-muted">Mô tả chi tiết những gì đã hoàn thành</small>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="handover_details" class="fw-bold">Chi tiết bàn giao</label>
                                                <textarea class="form-control" id="handover_details" name="handover_details" rows="4" 
                                                          placeholder="Thông tin đăng nhập, hướng dẫn sử dụng, tài liệu kỹ thuật..."></textarea>
                                                <small class="form-text text-muted">Thông tin cần thiết để khách hàng tiếp quản</small>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="customer_feedback" class="fw-bold">Phản hồi khách hàng</label>
                                                <textarea class="form-control" id="customer_feedback" name="customer_feedback" rows="3" 
                                                          placeholder="Phản hồi, đánh giá từ khách hàng về dịch vụ..."></textarea>
                                                <small class="form-text text-muted">Ghi nhận phản hồi từ khách hàng (nếu có)</small>
                                            </div>

                                            <div class="alert alert-info">
                                                <i class="ph ph-info me-2"></i>
                                                <strong>Lưu ý:</strong> Sau khi hoàn thành, trạng thái sẽ chuyển thành "Đã hoàn thành" và không thể chỉnh sửa.
                                            </div>

                                            <button type="submit" class="btn btn-success me-2">
                                                <i class="ph ph-check-circle me-2"></i>Hoàn thành dịch vụ
                                            </button>
                                            <a href="<?php echo home_url('/thuc-hien-dich-vu/?service_id=' . $service->id); ?>" class="btn btn-light">
                                                <i class="ph ph-arrow-left me-2"></i>Quay lại thực hiện
                                            </a>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Guidelines -->
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-lightbulb me-2"></i>
                                            Hướng dẫn bàn giao
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="fw-bold">Checklist bàn giao:</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="check1">
                                            <label class="form-check-label" for="check1">
                                                <small>Kiểm tra kỹ năng hoạt động</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="check2">
                                            <label class="form-check-label" for="check2">
                                                <small>Chuẩn bị tài liệu hướng dẫn</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="check3">
                                            <label class="form-check-label" for="check3">
                                                <small>Cung cấp thông tin đăng nhập</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="check4">
                                            <label class="form-check-label" for="check4">
                                                <small>Demo cho khách hàng</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="check5">
                                            <label class="form-check-label" for="check5">
                                                <small>Thu thập phản hồi</small>
                                            </label>
                                        </div>

                                        <div class="mt-3">
                                            <h6 class="fw-bold">Thông tin cần bàn giao:</h6>
                                            <small class="text-muted">
                                                • Tài khoản đăng nhập (nếu có)<br>
                                                • Link truy cập các tính năng mới<br>
                                                • Hướng dẫn sử dụng<br>
                                                • Tài liệu kỹ thuật<br>
                                                • Thông tin liên hệ hỗ trợ
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Service Details -->
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <h6 class="fw-bold">Yêu cầu ban đầu:</h6>
                                        <div class="border p-2 bg-light">
                                            <small><?php echo nl2br(esc_html($service->description)); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Completed Service Display -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">
                                            <i class="ph ph-check-circle me-2"></i>
                                            Dịch vụ đã hoàn thành
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-success">
                                            <i class="ph ph-check me-2"></i>
                                            <strong>Hoàn thành!</strong> Dịch vụ đã được hoàn thành vào ngày <?php echo date('d/m/Y H:i', strtotime($service->completion_date)); ?>
                                        </div>

                                        <h6 class="fw-bold">Báo cáo hoàn thành:</h6>
                                        <div class="border p-3 bg-light mb-3">
                                            <?php echo nl2br(esc_html($service->notes)); ?>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="fw-bold">Thống kê:</h6>
                                                <ul class="list-unstyled">
                                                    <li><strong>Thời gian thực hiện:</strong> <?php echo $duration_days; ?> ngày</li>
                                                    <li><strong>Trạng thái tiến độ:</strong> 
                                                        <span class="badge badge-<?php echo $is_ontime ? 'success' : 'warning'; ?>">
                                                            <?php echo $is_ontime ? 'Đúng hạn' : 'Trễ hạn'; ?>
                                                        </span>
                                                    </li>
                                                    <li><strong>Độ ưu tiên:</strong> <?php echo $service->priority; ?></li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-bold">Thông tin liên hệ:</h6>
                                                <ul class="list-unstyled">
                                                    <li><strong>Khách hàng:</strong> <?php echo esc_html($service->requester_name); ?></li>
                                                    <li><strong>Email:</strong> <?php echo esc_html($service->requester_email); ?></li>
                                                    <li><strong>Người thực hiện:</strong> <?php echo esc_html($service->assignee_name); ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row mt-4">
                            <div class="col-12">
                                <a href="<?php echo home_url('/danh-sach-dich-vu/'); ?>" class="btn btn-light">
                                    <i class="ph ph-arrow-left me-2"></i>Quay lại danh sách
                                </a>
                                <a href="<?php echo home_url('/thuc-hien-dich-vu/?service_id=' . $service->id); ?>" class="btn btn-outline-primary ms-2">
                                    <i class="ph ph-gear me-2"></i>Xem thực hiện
                                </a>
                                <button type="button" class="btn btn-outline-success ms-2" onclick="window.print()">
                                    <i class="ph ph-printer me-2"></i>In báo cáo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style media="print">
@media print {
    .btn, .card-header, .form-group, form, .alert-info { display: none !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    .card-body { padding: 15px !important; }
}
</style>

<?php get_footer(); ?>
