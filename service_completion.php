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
        WHERE s.id = %d
    ", $service_id));
    
    if (!$service || !in_array($service->status, ['IN_PROGRESS', 'COMPLETED'])) {
        wp_redirect(home_url('/danh-sach-dich-vu/'));
        exit;
    }

    // Calculate total service value
    $total_service_value = ($service->pricing_type === 'DAILY') ? ((float)$service->estimated_manday * (float)$service->daily_rate) : (float)$service->fixed_price;

    // Get all related invoices with their service item amount
    $invoices = $wpdb->get_results($wpdb->prepare("
        SELECT i.*, 
               COALESCE((
                   SELECT SUM(ii.amount) 
                   FROM $invoice_items_table ii 
                   WHERE ii.invoice_id = i.id 
                     AND ii.service_type = 'website_service' 
                     AND ii.service_id = %d
               ), i.total_amount) as service_charged_amount,
               (
                   SELECT ii.description 
                   FROM $invoice_items_table ii 
                   WHERE ii.invoice_id = i.id 
                     AND ii.service_type = 'website_service' 
                     AND ii.service_id = %d
                   LIMIT 1
               ) as service_item_desc
        FROM $invoice_table i
        INNER JOIN $invoice_items_table ii_join ON i.id = ii_join.invoice_id
        WHERE ii_join.service_type = 'website_service' AND ii_join.service_id = %d
        GROUP BY i.id
        ORDER BY i.created_at ASC
    ", $service_id, $service_id, $service_id));
    
    $total_invoiced = 0;
    $total_paid = 0;
    $second_invoice = null;
    foreach ($invoices as $inv) {
        $total_invoiced += (float)$inv->service_charged_amount;
        if (in_array($inv->status, ['paid', 'pending_vat'])) {
            $total_paid += (float)$inv->service_charged_amount;
        }
        if ($inv->status === 'pending_completion') {
            $second_invoice = $inv;
        }
    }
    $remaining_uninvoiced = max(0, $total_service_value - $total_invoiced);

    // Get all tasks for this service
    $tasks_table = $wpdb->prefix . 'im_service_tasks';
    $tasks = $wpdb->get_results($wpdb->prepare("
        SELECT t.*, u.name as creator_name
        FROM $tasks_table t
        LEFT JOIN {$wpdb->prefix}im_users u ON t.created_by = u.id
        WHERE t.service_id = %d
        ORDER BY t.sort_order ASC, t.created_at ASC
    ", $service_id));
}

/* 
 * Process form submissions
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process payment confirmation for any milestone invoice
    if (isset($_POST['mark_invoice_paid']) && wp_verify_nonce($_POST['mark_invoice_paid'], 'mark_invoice_paid')) {
        $pay_invoice_id = intval($_POST['invoice_id'] ?? 0);
        if ($pay_invoice_id) {
            $wpdb->update(
                $invoice_table,
                array(
                    'status' => 'paid',
                    'payment_date' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $pay_invoice_id)
            );
            $message = '<div class="alert alert-success"><strong>Thành công!</strong> Đã xác nhận thanh toán hóa đơn.</div>';

            // Refresh invoices
            $invoices = $wpdb->get_results($wpdb->prepare("
                SELECT i.*, 
                       COALESCE((
                           SELECT SUM(ii.amount) 
                           FROM $invoice_items_table ii 
                           WHERE ii.invoice_id = i.id 
                             AND ii.service_type = 'website_service' 
                             AND ii.service_id = %d
                       ), i.total_amount) as service_charged_amount,
                       (
                           SELECT ii.description 
                           FROM $invoice_items_table ii 
                           WHERE ii.invoice_id = i.id 
                             AND ii.service_type = 'website_service' 
                             AND ii.service_id = %d
                           LIMIT 1
                       ) as service_item_desc
                FROM $invoice_table i
                INNER JOIN $invoice_items_table ii_join ON i.id = ii_join.invoice_id
                WHERE ii_join.service_type = 'website_service' AND ii_join.service_id = %d
                GROUP BY i.id
                ORDER BY i.created_at ASC
            ", $service_id, $service_id, $service_id));
            $total_paid = 0;
            foreach ($invoices as $inv) {
                if (in_array($inv->status, ['paid', 'pending_vat'])) {
                    $total_paid += (float)$inv->service_charged_amount;
                }
            }
        }
    }

    // Process completion form submission
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

                // Activate any pending_completion invoices
                $wpdb->query($wpdb->prepare("
                    UPDATE $invoice_table i
                    INNER JOIN $invoice_items_table ii ON i.id = ii.invoice_id
                    SET i.status = 'draft', i.due_date = DATE_ADD(NOW(), INTERVAL 30 DAY), i.updated_at = NOW()
                    WHERE ii.service_type = 'website_service' AND ii.service_id = %d AND i.status = 'pending_completion'
                ", $service_id));
                
                $wpdb->query('COMMIT');
                
                $message = '<div class="alert alert-success"><strong>Thành công!</strong> Dịch vụ đã được hoàn thành và bàn giao.</div>';
                
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
                    SELECT i.*, 
                           COALESCE((
                               SELECT SUM(ii.amount) 
                               FROM $invoice_items_table ii 
                               WHERE ii.invoice_id = i.id 
                                 AND ii.service_type = 'website_service' 
                                 AND ii.service_id = %d
                           ), i.total_amount) as service_charged_amount,
                           (
                               SELECT ii.description 
                               FROM $invoice_items_table ii 
                               WHERE ii.invoice_id = i.id 
                                 AND ii.service_type = 'website_service' 
                                 AND ii.service_id = %d
                               LIMIT 1
                           ) as service_item_desc
                    FROM $invoice_table i
                    INNER JOIN $invoice_items_table ii_join ON i.id = ii_join.invoice_id
                    WHERE ii_join.service_type = 'website_service' AND ii_join.service_id = %d
                    GROUP BY i.id
                    ORDER BY i.created_at ASC
                ", $service_id, $service_id, $service_id));
                
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

                        <!-- URL Message Alert -->
                        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'invoice_created'): ?>
                            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                <i class="ph ph-check-circle me-2"></i><strong>Thành công!</strong> Hóa đơn đợt thanh toán đã được tạo thành công.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Financial Overview & Invoices -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h5 class="mb-0 text-white">
                                            <i class="ph ph-receipt me-2"></i>
                                            Tài chính & Các đợt hóa đơn thanh toán
                                        </h5>
                                        <div class="d-flex gap-2">
                                            <?php if ($remaining_uninvoiced > 0): ?>
                                                <a href="<?php echo home_url('/tao-hoa-don/?service_id=' . $service->id); ?>" class="btn btn-sm btn-warning">
                                                    <i class="ph ph-plus-circle me-1"></i>Lập hóa đơn quyết toán (<?php echo number_format($remaining_uninvoiced); ?> VNĐ)
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo home_url('/tao-hoa-don/?service_id=' . $service->id); ?>" class="btn btn-sm btn-light">
                                                    <i class="ph ph-plus-circle me-1"></i>Lập thêm hóa đơn phát sinh
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Financial Summary Badges -->
                                        <div class="row mb-3 text-center g-2">
                                            <div class="col-md-3 col-6">
                                                <div class="p-2 border rounded bg-light">
                                                    <small class="text-muted d-block">Giá trị dịch vụ</small>
                                                    <strong class="fs-6"><?php echo number_format($total_service_value); ?> VNĐ</strong>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-6">
                                                <div class="p-2 border rounded bg-light">
                                                    <small class="text-muted d-block">Đã xuất hóa đơn</small>
                                                    <strong class="fs-6 text-primary"><?php echo number_format($total_invoiced); ?> VNĐ</strong>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-6">
                                                <div class="p-2 border rounded bg-light">
                                                    <small class="text-muted d-block">Đã thanh toán thực tế</small>
                                                    <strong class="fs-6 text-success"><?php echo number_format($total_paid); ?> VNĐ</strong>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-6">
                                                <div class="p-2 border rounded bg-light">
                                                    <small class="text-muted d-block">Chưa lập hóa đơn</small>
                                                    <strong class="fs-6 <?php echo $remaining_uninvoiced > 0 ? 'text-danger' : 'text-success'; ?>">
                                                        <?php echo number_format($remaining_uninvoiced); ?> VNĐ
                                                    </strong>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($remaining_uninvoiced > 0): ?>
                                            <div class="alert alert-warning d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <i class="ph ph-warning-circle me-2 fs-5"></i>
                                                    Dịch vụ còn <strong><?php echo number_format($remaining_uninvoiced); ?> VNĐ</strong> chưa lập hóa đơn quyết toán. Bạn nên lập hóa đơn để hoàn tất tài chính trước khi kết thúc hợp đồng.
                                                </div>
                                                <a href="<?php echo home_url('/tao-hoa-don/?service_id=' . $service->id); ?>" class="btn btn-sm btn-dark text-nowrap ms-2">
                                                    <i class="ph ph-receipt me-1"></i>Lập HĐ quyết toán
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($invoices)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover align-middle mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Đợt</th>
                                                            <th>Mã HĐ</th>
                                                            <th>Nội dung thanh toán</th>
                                                            <th>Số tiền HĐ</th>
                                                            <th>Trạng thái</th>
                                                            <th>Ngày tạo / Hạn</th>
                                                            <th>Thao tác</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($invoices as $idx => $inv): ?>
                                                            <tr>
                                                                <td><strong>Đợt <?php echo $idx + 1; ?></strong></td>
                                                                <td>
                                                                    <strong><?php echo esc_html($inv->invoice_code); ?></strong>
                                                                </td>
                                                                <td>
                                                                    <small class="text-muted"><?php echo esc_html($inv->service_item_desc ?: $service->title); ?></small>
                                                                </td>
                                                                <td>
                                                                    <strong class="text-primary"><?php echo number_format($inv->total_amount); ?> VNĐ</strong>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-<?php echo in_array($inv->status, ['paid', 'pending_vat']) ? 'success' : ($inv->status === 'draft' ? 'warning' : 'secondary'); ?> text-<?php echo in_array($inv->status, ['paid', 'pending_vat']) ? 'white' : 'dark'; ?>">
                                                                        <?php 
                                                                        if (in_array($inv->status, ['paid', 'pending_vat'])) echo 'Đã thanh toán';
                                                                        elseif ($inv->status === 'draft') echo 'Chờ thanh toán';
                                                                        elseif ($inv->status === 'pending_completion') echo 'Chờ hoàn thành';
                                                                        else echo esc_html($inv->status);
                                                                        ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <small class="text-muted">
                                                                        <?php echo date('d/m/Y', strtotime($inv->created_at)); ?>
                                                                        <?php if ($inv->due_date): ?>
                                                                            <br><span class="text-secondary">Hạn: <?php echo date('d/m/Y', strtotime($inv->due_date)); ?></span>
                                                                        <?php endif; ?>
                                                                    </small>
                                                                </td>
                                                                <td>
                                                                    <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $inv->id); ?>" class="btn btn-sm btn-outline-primary me-1" title="Xem hóa đơn">
                                                                        <i class="ph ph-eye"></i> Xem
                                                                    </a>
                                                                    <?php if (!in_array($inv->status, ['paid', 'pending_vat'])): ?>
                                                                        <form method="post" class="d-inline" onsubmit="return confirm('Xác nhận khách hàng đã thanh toán hóa đơn <?php echo esc_js($inv->invoice_code); ?>?');">
                                                                            <?php wp_nonce_field('mark_invoice_paid', 'mark_invoice_paid'); ?>
                                                                            <input type="hidden" name="invoice_id" value="<?php echo esc_attr($inv->id); ?>">
                                                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                                                <i class="ph ph-check"></i> Đã thu tiền
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-3 text-muted">
                                                <i class="ph ph-receipt fs-2 d-block mb-2"></i>
                                                Chưa có hóa đơn nào được tạo cho dịch vụ này.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

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

                                <!-- Task List -->
                                <div class="card mt-3">
                                    <div class="card-header bg-dark text-white py-2">
                                        <h6 class="mb-0 fw-bold small"><i class="ph ph-list-checks me-2"></i>Nhiệm vụ thực hiện</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <?php if (!empty($tasks)): ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($tasks as $task): ?>
                                                    <?php
                                                    $status_badge = 'bg-secondary';
                                                    $status_text = 'Chờ làm';
                                                    if ($task->status === 'IN_PROGRESS') {
                                                        $status_badge = 'bg-primary';
                                                        $status_text = 'Đang làm';
                                                    } elseif ($task->status === 'DONE') {
                                                        $status_badge = 'bg-success';
                                                        $status_text = 'Đã xong';
                                                    } elseif ($task->status === 'CANCELLED') {
                                                        $status_badge = 'bg-danger';
                                                        $status_text = 'Đã hủy';
                                                    }
                                                    ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-start py-2">
                                                        <div class="ms-2 me-auto text-wrap">
                                                            <div class="fw-bold small <?php echo $task->status === 'DONE' ? 'text-decoration-line-through text-muted' : ''; ?> <?php echo $task->status === 'CANCELLED' ? 'text-decoration-line-through text-danger' : ''; ?>">
                                                                <?php echo esc_html($task->title); ?>
                                                            </div>
                                                            <?php if ($task->description): ?>
                                                                <small class="text-muted text-wrap d-block mt-1" style="font-size: 0.75rem;"><?php echo esc_html($task->description); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="badge <?php echo $status_badge; ?> rounded-pill ms-2" style="font-size: 0.7rem;"><?php echo $status_text; ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <div class="p-3 text-center text-muted small">Không có nhiệm vụ nào</div>
                                        <?php endif; ?>
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

                                        <h6 class="fw-bold mt-4 mb-2"><i class="ph ph-list-checks me-2"></i>Nhiệm vụ đã thực hiện:</h6>
                                        <div class="table-responsive mb-4 border rounded">
                                            <table class="table table-hover mb-0">
                                                <thead>
                                                    <tr class="table-light">
                                                        <th>Tên nhiệm vụ</th>
                                                        <th>Mô tả</th>
                                                        <th>Người tạo</th>
                                                        <th>Trạng thái</th>
                                                        <th>Hoàn thành lúc</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($tasks)): ?>
                                                        <?php foreach ($tasks as $task): ?>
                                                            <?php
                                                            $status_badge = 'bg-secondary';
                                                            $status_text = 'Chờ làm';
                                                            if ($task->status === 'IN_PROGRESS') {
                                                                $status_badge = 'bg-primary';
                                                                $status_text = 'Đang làm';
                                                            } elseif ($task->status === 'DONE') {
                                                                $status_badge = 'bg-success';
                                                                $status_text = 'Đã xong';
                                                            } elseif ($task->status === 'CANCELLED') {
                                                                $status_badge = 'bg-danger';
                                                                $status_text = 'Đã hủy';
                                                            }
                                                            ?>
                                                            <tr>
                                                                <td><strong class="<?php echo $task->status === 'DONE' ? 'text-decoration-line-through text-muted' : ''; ?> <?php echo $task->status === 'CANCELLED' ? 'text-decoration-line-through text-danger' : ''; ?>"><?php echo esc_html($task->title); ?></strong></td>
                                                                <td><small class="text-muted"><?php echo esc_html($task->description ?: 'Không có'); ?></small></td>
                                                                <td><small><?php echo esc_html($task->creator_name ?: 'N/A'); ?></small></td>
                                                                <td><span class="badge <?php echo $status_badge; ?> text-white"><?php echo $status_text; ?></span></td>
                                                                <td><small><?php echo $task->completed_at ? date('d/m/Y H:i', strtotime($task->completed_at)) : 'N/A'; ?></small></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="5" class="text-center text-muted">Không có nhiệm vụ nào</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
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
                                <a href="<?php echo home_url('/thuc-hien-dich-vu/?service_id=' . $service->id); ?>" class="btn btn-primary ms-2 d-flex align-items-center">
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
