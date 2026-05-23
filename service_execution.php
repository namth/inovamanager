<?php
/* 
    Template Name: Service Execution - Stage 4: Work Execution & Progress Tracking
    Purpose: Track service execution progress and manage work status with sub-tasks
*/

global $wpdb;
$service_table = $wpdb->prefix . 'im_website_services';
$tasks_table = $wpdb->prefix . 'im_service_tasks';
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

    // Allow access for PENDING, APPROVED, IN_PROGRESS, COMPLETED statuses
    if (!$service || !in_array($service->status, ['PENDING', 'APPROVED', 'IN_PROGRESS', 'COMPLETED'])) {
        wp_redirect(home_url('/danh-sach-dich-vu/'));
        exit;
    }

    // Get related invoices
    $invoices = $wpdb->get_results($wpdb->prepare("
        SELECT i.* FROM $invoice_table i
        INNER JOIN $invoice_items_table ii ON i.id = ii.invoice_id
        WHERE ii.service_type = 'website_service' AND ii.service_id = %d
        ORDER BY i.created_at ASC
    ", $service_id));

    $invoice = !empty($invoices) ? $invoices[0] : null;

    // Get all tasks for this service
    $tasks = $wpdb->get_results($wpdb->prepare("
        SELECT t.*, u.name as creator_name
        FROM $tasks_table t
        LEFT JOIN {$wpdb->prefix}im_users u ON t.created_by = u.id
        WHERE t.service_id = %d
        ORDER BY t.sort_order ASC, t.created_at ASC
    ", $service_id));

    // Calculate task stats
    $task_stats = array(
        'total' => 0,
        'done' => 0,
        'in_progress' => 0,
        'todo' => 0,
        'cancelled' => 0
    );
    foreach ($tasks as $task) {
        if ($task->status !== 'CANCELLED')
            $task_stats['total']++;
        $task_stats[strtolower($task->status)]++;
    }
    $task_stats['percentage'] = $task_stats['total'] > 0 ? round(($task_stats['done'] / $task_stats['total']) * 100) : 0;
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
            // Determine dynamic status based on VAT requirement
            $new_status = (intval($invoice->requires_vat_invoice) === 1) ? 'pending_vat' : 'paid';

            // Update invoice status
            $invoice_update = $wpdb->update(
                $invoice_table,
                array(
                    'status' => $new_status,
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
                if ($new_status === 'pending_vat') {
                    $message = '<div class="alert alert-success"><strong>Thành công!</strong> Thanh toán đã được xác nhận (Chờ xuất hóa đơn VAT) và công việc bắt đầu thực hiện.</div>';
                } else {
                    $message = '<div class="alert alert-success"><strong>Thành công!</strong> Thanh toán đã được xác nhận và công việc bắt đầu thực hiện.</div>';
                }

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
                        <p class="card-description">Quản lý công việc & theo dõi tiến độ</p>

                        <!-- Display messages -->
                        <?php if (isset($message))
                            echo $message; ?>
                        <?php if (isset($error_message))
                            echo $error_message; ?>

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
                                                <p><strong>Mã yêu cầu:</strong>
                                                    <?php echo esc_html($service->service_code); ?></p>
                                                <p><strong>Website:</strong>
                                                    <?php echo esc_html($service->website_name); ?></p>
                                                <p><strong>Tiêu đề:</strong> <?php echo esc_html($service->title); ?>
                                                </p>
                                                <p><strong>Khách hàng:</strong>
                                                    <?php echo esc_html($service->requester_name . ' (' . $service->requester_code . ')'); ?>
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Trạng thái:</strong>
                                                    <span id="service-status-badge" class="badge badge-<?php
                                                    echo $service->status === 'PENDING' ? 'warning' :
                                                        ($service->status === 'APPROVED' ? 'success' :
                                                            ($service->status === 'IN_PROGRESS' ? 'primary' : 'secondary'));
                                                    ?>">
                                                        <?php
                                                        $status_text = array(
                                                            'PENDING' => 'Chờ xử lý',
                                                            'APPROVED' => 'Đã duyệt',
                                                            'IN_PROGRESS' => 'Đang thực hiện',
                                                            'COMPLETED' => 'Hoàn thành'
                                                        );
                                                        echo $status_text[$service->status] ?? $service->status;
                                                        ?>
                                                    </span>
                                                </p>
                                                <p><strong>Người thực hiện:</strong>
                                                    <?php echo $service->assignee_name ? esc_html($service->assignee_name . ' (' . $service->assignee_code . ')') : '<span class="text-muted">Chưa phân công</span>'; ?>
                                                </p>
                                                <p><strong>Ngày bắt đầu:</strong>
                                                    <?php echo $service->start_date ? date('d/m/Y', strtotime($service->start_date)) : 'Chưa bắt đầu'; ?>
                                                </p>
                                                <p><strong>Dự kiến hoàn thành:</strong>
                                                    <?php echo $service->estimated_completion_date ? date('d/m/Y', strtotime($service->estimated_completion_date)) : 'Chưa xác định'; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ========== TASK MANAGEMENT SECTION ========== -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div
                                        class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="ph ph-list-checks me-2"></i>
                                            Danh sách công việc
                                        </h5>
                                        <div class="d-flex align-items-center gap-3">
                                            <!-- Progress Summary -->
                                            <span class="badge bg-light text-dark" id="task-counter">
                                                <?php echo $task_stats['done']; ?>/<?php echo $task_stats['total']; ?>
                                                hoàn thành
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Progress Bar -->
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="text-muted">Tiến độ tổng thể</small>
                                                <small class="fw-bold"
                                                    id="progress-percentage"><?php echo $task_stats['percentage']; ?>%</small>
                                            </div>
                                            <div class="progress" style="height: 10px; border-radius: 5px;">
                                                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated"
                                                    id="progress-bar" role="progressbar"
                                                    style="width: <?php echo $task_stats['percentage']; ?>%"
                                                    aria-valuenow="<?php echo $task_stats['percentage']; ?>"
                                                    aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                            <div class="d-flex gap-3 mt-2">
                                                <small class="text-muted"><span
                                                        class="badge bg-secondary me-1">&nbsp;</span> Chờ làm: <span
                                                        id="stat-todo"><?php echo $task_stats['todo']; ?></span></small>
                                                <small class="text-muted"><span
                                                        class="badge bg-primary me-1">&nbsp;</span> Đang làm: <span
                                                        id="stat-in-progress"><?php echo $task_stats['in_progress']; ?></span></small>
                                                <small class="text-muted"><span
                                                        class="badge bg-success me-1">&nbsp;</span> Xong: <span
                                                        id="stat-done"><?php echo $task_stats['done']; ?></span></small>
                                                <?php if ($task_stats['cancelled'] > 0): ?>
                                                    <small class="text-muted"><span
                                                            class="badge bg-danger me-1">&nbsp;</span> Hủy: <span
                                                            id="stat-cancelled"><?php echo $task_stats['cancelled']; ?></span></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Add Task Form -->
                                        <?php if (is_inova_admin()): ?>
                                            <div class="mb-4 p-3 bg-light-warning rounded border-warning">
                                                <div class="d-flex gap-2 align-items-start">
                                                    <div class="flex-grow-1">
                                                        <input type="text" class="form-control" id="new-task-title"
                                                            placeholder="Nhập tên công việc mới..." maxlength="255">
                                                        <textarea class="form-control mt-2" id="new-task-description"
                                                            rows="2" placeholder="Mô tả chi tiết (tùy chọn)..."
                                                            style="display: none;"></textarea>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-danger" id="btn-add-task"
                                                            title="Thêm task">
                                                            <i class="ph ph-plus"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-secondary" id="btn-toggle-desc"
                                                            title="Thêm mô tả">
                                                            <i class="ph ph-text-aa"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Tasks List -->
                                        <div id="tasks-list">
                                            <?php if (empty($tasks)): ?>
                                                <div class="text-center text-muted py-4" id="no-tasks-msg">
                                                    <i class="ph ph-clipboard-text" style="font-size: 3rem;"></i>
                                                    <p class="mt-2">Chưa có công việc nào. Thêm công việc đầu tiên!</p>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($tasks as $task): ?>
                                                    <div class="task-item d-flex align-items-start gap-3 p-3 border-bottom task-status-<?php echo strtolower($task->status); ?>"
                                                        data-task-id="<?php echo $task->id; ?>"
                                                        data-status="<?php echo $task->status; ?>">
                                                        <!-- Status Toggle Button -->
                                                        <div class="task-status-toggle" style="min-width: 36px;">
                                                            <?php
                                                            $icon_class = 'ph-circle';
                                                            $btn_class = 'btn-outline-secondary';
                                                            if ($task->status === 'IN_PROGRESS') {
                                                                $icon_class = 'ph-circle-half';
                                                                $btn_class = 'btn-outline-primary';
                                                            } elseif ($task->status === 'DONE') {
                                                                $icon_class = 'ph-check-circle';
                                                                $btn_class = 'btn-success';
                                                            } elseif ($task->status === 'CANCELLED') {
                                                                $icon_class = 'ph-x-circle';
                                                                $btn_class = 'btn-outline-danger';
                                                            }
                                                            ?>
                                                            <button type="button"
                                                                class="btn btn-sm <?php echo $btn_class; ?> btn-task-toggle rounded-circle p-0 border-white"
                                                                title="Chuyển trạng thái">
                                                                <i class="ph <?php echo $icon_class; ?>"
                                                                    style="font-size: 1.2rem;"></i>
                                                            </button>
                                                        </div>

                                                        <!-- Task Content -->
                                                        <div class="flex-grow-1">
                                                            <div
                                                                class="task-title fw-bold <?php echo $task->status === 'DONE' ? 'text-decoration-line-through text-muted' : ''; ?> <?php echo $task->status === 'CANCELLED' ? 'text-decoration-line-through text-danger' : ''; ?>">
                                                                <?php echo esc_html($task->title); ?>
                                                            </div>
                                                            <?php if ($task->description): ?>
                                                                <div class="task-desc text-muted small mt-1">
                                                                    <?php echo nl2br(esc_html($task->description)); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="task-meta mt-1">
                                                                <small class="text-muted">
                                                                    <?php if ($task->creator_name): ?>
                                                                        <i
                                                                            class="ph ph-user me-1"></i><?php echo esc_html($task->creator_name); ?>
                                                                        ·
                                                                    <?php endif; ?>
                                                                    <i
                                                                        class="ph ph-calendar me-1"></i><?php echo date('d/m/Y H:i', strtotime($task->created_at)); ?>
                                                                    <?php if ($task->completed_at): ?>
                                                                        · <i class="ph ph-check me-1 text-success"></i>Xong:
                                                                        <?php echo date('d/m/Y H:i', strtotime($task->completed_at)); ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <!-- Task Actions -->
                                                        <div class="task-actions d-flex gap-1">
                                                            <div class="dropdown">
                                                                <button
                                                                    class="btn btn-sm dropdown-toggle border-0 d-flex align-items-center"
                                                                    type="button" data-bs-toggle="dropdown"
                                                                    aria-expanded="false">
                                                                    <i class="ph ph-dots-three-vertical"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li><a class="dropdown-item task-action-status" href="#"
                                                                            data-status="TODO"><i
                                                                                class="ph ph-circle me-2"></i>Chờ làm</a></li>
                                                                    <li><a class="dropdown-item task-action-status" href="#"
                                                                            data-status="IN_PROGRESS"><i
                                                                                class="ph ph-circle-half me-2"></i>Đang làm</a>
                                                                    </li>
                                                                    <li><a class="dropdown-item task-action-status" href="#"
                                                                            data-status="DONE"><i
                                                                                class="ph ph-check-circle me-2"></i>Hoàn
                                                                            thành</a></li>
                                                                    <li><a class="dropdown-item task-action-status" href="#"
                                                                            data-status="CANCELLED"><i
                                                                                class="ph ph-x-circle me-2"></i>Hủy</a></li>
                                                                    <?php if (is_inova_admin()): ?>
                                                                        <li>
                                                                            <hr class="dropdown-divider">
                                                                        </li>
                                                                        <li><a class="dropdown-item text-danger task-action-delete"
                                                                                href="#"><i class="ph ph-trash me-2"></i>Xóa</a>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
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
                                                            <?php
                                                            $card_class = 'warning';
                                                            if ($inv->status === 'paid') {
                                                                $card_class = 'success';
                                                            } elseif ($inv->status === 'pending_vat') {
                                                                $card_class = 'info';
                                                            } elseif ($inv->status === 'pending_completion') {
                                                                $card_class = 'secondary';
                                                            }
                                                            ?>
                                                            <div class="card border-<?php echo $card_class; ?>">
                                                                <div class="card-header bg-<?php echo $card_class; ?> text-white">
                                                                    <h6 class="mb-0">Hóa đơn <?php echo $index + 1; ?>
                                                                        (<?php echo $index == 0 ? '50% trước' : '50% sau'; ?>)</h6>
                                                                </div>
                                                                <div class="card-body">
                                                                    <p><strong>Mã:</strong>
                                                                        <?php echo esc_html($inv->invoice_code); ?></p>
                                                                    <p><strong>Tổng tiền:</strong>
                                                                        <?php echo number_format($inv->total_amount); ?> VNĐ</p>
                                                                    <p><strong>Trạng thái:</strong>
                                                                        <span
                                                                            class="badge bg-<?php echo $card_class; ?> border-radius-9 text-<?php echo $card_class === 'warning' ? 'dark' : 'white'; ?>">
                                                                            <?php
                                                                            if ($inv->status === 'paid')
                                                                                echo 'Đã thanh toán';
                                                                            elseif ($inv->status === 'pending_vat')
                                                                                echo 'Chờ xuất hóa đơn';
                                                                            elseif ($inv->status === 'pending_completion')
                                                                                echo 'Chờ hoàn thành';
                                                                            else
                                                                                echo 'Chưa thanh toán';
                                                                            ?>
                                                                        </span>
                                                                    </p>
                                                                    <?php if ($inv->payment_date): ?>
                                                                        <p><strong>Ngày thanh toán:</strong>
                                                                            <?php echo date('d/m/Y H:i', strtotime($inv->payment_date)); ?>
                                                                        </p>
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
                                        <?php
                                        $invoice_card_class = 'warning';
                                        $invoice_status_text = 'Chưa thanh toán';
                                        if ($invoice->status === 'paid') {
                                            $invoice_card_class = 'success';
                                            $invoice_status_text = 'Đã thanh toán';
                                        } elseif ($invoice->status === 'pending_vat') {
                                            $invoice_card_class = 'info';
                                            $invoice_status_text = 'Chờ xuất hóa đơn';
                                        }
                                        ?>
                                        <div class="card border-<?php echo $invoice_card_class; ?>">
                                            <div class="card-header bg-<?php echo $invoice_card_class; ?> text-white">
                                                <h5 class="mb-0">
                                                    <i class="ph ph-receipt me-2"></i>
                                                    Thông tin hóa đơn
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>Mã hóa đơn:</strong>
                                                            <?php echo esc_html($invoice->invoice_code); ?></p>
                                                        <p><strong>Ngày hóa đơn:</strong>
                                                            <?php echo date('d/m/Y', strtotime($invoice->invoice_date)); ?></p>
                                                        <p><strong>Hạn thanh toán:</strong>
                                                            <?php echo date('d/m/Y', strtotime($invoice->due_date)); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Tổng tiền:</strong>
                                                            <?php echo number_format($invoice->total_amount); ?> VNĐ</p>
                                                        <p><strong>Đã thanh toán:</strong>
                                                            <?php echo number_format($invoice->paid_amount); ?> VNĐ</p>
                                                        <p><strong>Trạng thái:</strong>
                                                            <span
                                                                class="badge bg-<?php echo $invoice_card_class; ?> border-radius-9 text-<?php echo $invoice_card_class === 'warning' ? 'dark' : 'white'; ?>">
                                                                <?php echo $invoice_status_text; ?>
                                                            </span>
                                                        </p>
                                                        <?php if ($invoice->payment_date): ?>
                                                            <p><strong>Ngày thanh toán:</strong>
                                                                <?php echo date('d/m/Y H:i', strtotime($invoice->payment_date)); ?>
                                                            </p>
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
                            <?php if ($invoice && !in_array(strtolower($invoice->status), ['paid', 'pending_vat'])): ?>
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
                                                    <label for="payment_date" class="fw-bold">Ngày thanh toán <span
                                                            class="text-danger">*</span></label>
                                                    <input type="datetime-local" class="form-control" id="payment_date"
                                                        name="payment_date" value="<?php echo date('Y-m-d\TH:i'); ?>"
                                                        required>
                                                </div>

                                                <div class="form-group mb-3">
                                                    <label for="payment_method" class="fw-bold">Phương thức thanh
                                                        toán</label>
                                                    <select class="form-control" id="payment_method" name="payment_method">
                                                        <option value="bank_transfer">Chuyển khoản ngân hàng</option>
                                                        <option value="cash">Tiền mặt</option>
                                                        <option value="credit_card">Thẻ tín dụng</option>
                                                        <option value="e_wallet">Ví điện tử</option>
                                                        <option value="other">Khác</option>
                                                    </select>
                                                </div>

                                                <div class="form-group mb-3">
                                                    <label for="payment_amount" class="fw-bold">Số tiền thanh toán <span
                                                            class="text-danger">*</span></label>
                                                    <input type="number" class="form-control" id="payment_amount"
                                                        name="payment_amount" value="<?php echo $invoice->total_amount; ?>"
                                                        min="1" required>
                                                    <small class="form-text text-muted">Tổng hóa đơn:
                                                        <?php echo number_format($invoice->total_amount); ?> VNĐ</small>
                                                </div>

                                                <div class="form-group mb-3">
                                                    <label for="payment_notes" class="fw-bold">Ghi chú thanh toán</label>
                                                    <textarea class="form-control" id="payment_notes" name="payment_notes"
                                                        rows="3" placeholder="Ghi chú về thanh toán..."></textarea>
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
                            <?php if (in_array($service->status, ['IN_PROGRESS', 'PENDING'])): ?>
                                <div
                                    class="col-md-<?php echo ($invoice && !in_array(strtolower($invoice->status), ['paid', 'pending_vat'])) ? '6' : '8'; ?>">
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
                                                    <label for="estimated_completion_date" class="fw-bold">Cập nhật ngày
                                                        hoàn thành</label>
                                                    <input type="date" class="form-control" id="estimated_completion_date"
                                                        name="estimated_completion_date"
                                                        value="<?php echo $service->estimated_completion_date ? date('Y-m-d', strtotime($service->estimated_completion_date)) : ''; ?>">
                                                    <small class="form-text text-muted">Chọn ngày dự kiến hoàn thành</small>
                                                </div>

                                                <div class="form-group mb-3">
                                                    <label for="progress_notes" class="fw-bold">Tiến độ công việc</label>
                                                    <textarea class="form-control" id="progress_notes" name="progress_notes"
                                                        rows="6"
                                                        placeholder="Mô tả tiến độ công việc, khó khăn gặp phải, kế hoạch tiếp theo..."><?php echo esc_textarea($service->notes); ?></textarea>
                                                </div>

                                                <button type="submit" class="btn btn-primary">
                                                    <i class="ph ph-upload me-2"></i>Cập nhật tiến độ
                                                </button>
                                                <?php if ($service->status === 'IN_PROGRESS'): ?>
                                                    <a href="<?php echo home_url('/completion/?service_id=' . $service->id); ?>"
                                                        class="btn btn-success ms-2">
                                                        <i class="ph ph-check-circle me-2"></i>Hoàn thành công việc
                                                    </a>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Service Details -->
                            <div
                                class="col-md-<?php echo (in_array($service->status, ['IN_PROGRESS', 'PENDING'])) ? '4' : '12'; ?>">
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
                                                <?php if ($service->pricing_type): ?>
                                                    • <strong>Loại định giá:</strong>
                                                    <?php echo $service->pricing_type === 'DAILY' ? 'Theo ngày công' : 'Giá cố định'; ?><br>
                                                    <?php if ($service->pricing_type === 'DAILY'): ?>
                                                        • <strong>Ước tính:</strong> <?php echo $service->estimated_manday; ?>
                                                        ngày × <?php echo number_format($service->daily_rate); ?> VNĐ<br>
                                                    <?php else: ?>
                                                        • <strong>Giá:</strong>
                                                        <?php echo number_format($service->fixed_price); ?> VNĐ<br>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    • <strong>Báo giá:</strong> <span class="text-warning">Chưa báo
                                                        giá</span><br>
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
                                    <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $invoice->id); ?>"
                                        class="btn btn-outline-primary ms-2">
                                        <i class="ph ph-receipt me-2"></i>Xem hóa đơn
                                    </a>
                                <?php endif; ?>
                                <?php if ($service->status === 'PENDING' || (!$service->pricing_type)): ?>
                                    <a href="<?php echo home_url('/bao-gia/?service_id=' . $service->id); ?>"
                                        class="btn btn-outline-secondary ms-2">
                                        <i class="ph ph-calculator me-2"></i>Báo giá
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

<script>
    jQuery(document).ready(function ($) {
        const serviceId = <?php echo intval($service_id); ?>;
        const ajaxUrl = AJAX.ajaxurl;

        // Toggle description field
        $('#btn-toggle-desc').on('click', function () {
            $('#new-task-description').slideToggle(200);
        });

        // Add new task
        $('#btn-add-task').on('click', addTask);
        $('#new-task-title').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                addTask();
            }
        });

        function addTask() {
            const title = $('#new-task-title').val().trim();
            const description = $('#new-task-description').val().trim();

            if (!title) {
                $('#new-task-title').focus().addClass('is-invalid');
                setTimeout(() => $('#new-task-title').removeClass('is-invalid'), 2000);
                return;
            }

            const $btn = $('#btn-add-task');
            $btn.prop('disabled', true).html('<i class="ph ph-spinner ph-spin me-1"></i>...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'add_service_task',
                    service_id: serviceId,
                    title: title,
                    description: description || ''
                },
                success: function (response) {
                    if (response.success) {
                        // Remove "no tasks" message
                        $('#no-tasks-msg').remove();

                        // Build task HTML
                        const task = response.data.task;
                        const taskHtml = buildTaskHtml(task);

                        // Append to list with animation
                        const $taskEl = $(taskHtml);
                        $taskEl.addClass('task-adding');
                        $('#tasks-list').append($taskEl);

                        // Clear inputs
                        $('#new-task-title').val('');
                        $('#new-task-description').val('');

                        // Update stats
                        updateStats(response.data.stats);

                        // Handle service status auto-change
                        if (response.data.service_status_changed) {
                            const statusMap = {
                                'PENDING': { class: 'badge-warning', text: 'Chờ xử lý' },
                                'IN_PROGRESS': { class: 'badge-primary', text: 'Đang thực hiện' },
                                'COMPLETED': { class: 'badge-info', text: 'Hoàn thành' }
                            };
                            const newServiceStatus = statusMap[response.data.new_service_status];
                            if (newServiceStatus) {
                                $('#service-status-badge')
                                    .removeClass('badge-warning badge-success badge-primary badge-secondary badge-info')
                                    .addClass(newServiceStatus.class)
                                    .text(newServiceStatus.text);
                            }
                        }
                    } else {
                        alert(response.data.message || 'Lỗi khi thêm task');
                    }
                },
                error: function () {
                    alert('Đã có lỗi xảy ra');
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="ph ph-plus me-1"></i>Thêm');
                    $('#new-task-title').focus();
                }
            });
        }

        // Task status toggle (click the circle button) - cycle: TODO → IN_PROGRESS → DONE
        $(document).on('click', '.btn-task-toggle', function () {
            const $item = $(this).closest('.task-item');
            const taskId = $item.data('task-id');
            const currentStatus = $item.data('status');

            let nextStatus;
            switch (currentStatus) {
                case 'TODO': nextStatus = 'IN_PROGRESS'; break;
                case 'IN_PROGRESS': nextStatus = 'DONE'; break;
                case 'DONE': nextStatus = 'TODO'; break;
                case 'CANCELLED': nextStatus = 'TODO'; break;
                default: nextStatus = 'TODO';
            }

            updateTaskStatus(taskId, nextStatus, $item);
        });

        // Task status change from dropdown
        $(document).on('click', '.task-action-status', function (e) {
            e.preventDefault();
            const $item = $(this).closest('.task-item');
            const taskId = $item.data('task-id');
            const newStatus = $(this).data('status');

            updateTaskStatus(taskId, newStatus, $item);
        });

        // Delete task
        $(document).on('click', '.task-action-delete', function (e) {
            e.preventDefault();
            const $item = $(this).closest('.task-item');
            const taskId = $item.data('task-id');

            if (!confirm('Bạn có chắc muốn xóa task này?')) return;

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'delete_service_task',
                    task_id: taskId
                },
                success: function (response) {
                    if (response.success) {
                        $item.addClass('task-removing');
                        setTimeout(() => $item.remove(), 300);
                        updateStats(response.data.stats);
                    } else {
                        alert(response.data.message || 'Lỗi khi xóa task');
                    }
                }
            });
        });

        function updateTaskStatus(taskId, newStatus, $item) {
            const $toggleBtn = $item.find('.btn-task-toggle');
            $toggleBtn.prop('disabled', true);

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'update_service_task',
                    task_id: taskId,
                    status: newStatus
                },
                success: function (response) {
                    if (response.success) {
                        // Update task item UI
                        $item.data('status', newStatus);
                        $item.removeClass('task-status-todo task-status-in_progress task-status-done task-status-cancelled');
                        $item.addClass('task-status-' + newStatus.toLowerCase());

                        // Update toggle button
                        let iconClass, btnClass;
                        switch (newStatus) {
                            case 'TODO': iconClass = 'ph-circle'; btnClass = 'btn-outline-secondary'; break;
                            case 'IN_PROGRESS': iconClass = 'ph-circle-half'; btnClass = 'btn-outline-primary'; break;
                            case 'DONE': iconClass = 'ph-check-circle'; btnClass = 'btn-success'; break;
                            case 'CANCELLED': iconClass = 'ph-x-circle'; btnClass = 'btn-outline-danger'; break;
                        }
                        $toggleBtn.removeClass('btn-outline-secondary btn-outline-primary btn-success btn-outline-danger').addClass(btnClass);
                        $toggleBtn.find('i').attr('class', 'ph ' + iconClass).css('font-size', '1.2rem');

                        // Update title styling
                        const $title = $item.find('.task-title');
                        $title.removeClass('text-decoration-line-through text-muted text-danger');
                        if (newStatus === 'DONE') $title.addClass('text-decoration-line-through text-muted');
                        if (newStatus === 'CANCELLED') $title.addClass('text-decoration-line-through text-danger');

                        // Update stats
                        updateStats(response.data.stats);

                        // Handle service status auto-change
                        if (response.data.service_status_changed) {
                            const statusMap = {
                                'PENDING': { class: 'badge-warning', text: 'Chờ xử lý' },
                                'IN_PROGRESS': { class: 'badge-primary', text: 'Đang thực hiện' },
                                'COMPLETED': { class: 'badge-info', text: 'Hoàn thành' }
                            };
                            const newServiceStatus = statusMap[response.data.new_service_status];
                            if (newServiceStatus) {
                                $('#service-status-badge')
                                    .removeClass('badge-warning badge-success badge-primary badge-secondary badge-info')
                                    .addClass(newServiceStatus.class)
                                    .text(newServiceStatus.text);
                            }

                            if (response.data.new_service_status === 'COMPLETED') {
                                // Show completion notification
                                $('<div class="alert alert-success mt-3"><strong>🎉 Tất cả công việc đã hoàn thành!</strong> Dịch vụ đã tự động chuyển sang trạng thái hoàn thành.</div>')
                                    .insertAfter('.card-description').delay(5000).fadeOut();
                            }
                        }
                    } else {
                        alert(response.data.message || 'Lỗi khi cập nhật task');
                    }
                },
                error: function () {
                    alert('Đã có lỗi xảy ra');
                },
                complete: function () {
                    $toggleBtn.prop('disabled', false);
                }
            });
        }

        function updateStats(stats) {
            $('#task-counter').text(stats.done + '/' + stats.total + ' hoàn thành');
            $('#progress-percentage').text(stats.percentage + '%');
            $('#progress-bar').css('width', stats.percentage + '%').attr('aria-valuenow', stats.percentage);
            $('#stat-todo').text(stats.todo);
            $('#stat-in-progress').text(stats.in_progress);
            $('#stat-done').text(stats.done);
            if ($('#stat-cancelled').length) {
                $('#stat-cancelled').text(stats.cancelled);
            }
        }

        function buildTaskHtml(task) {
            const creatorInfo = task.creator_name ? '<i class="ph ph-user me-1"></i>' + escHtml(task.creator_name) + ' · ' : '';
            const createdAt = new Date(task.created_at).toLocaleDateString('vi-VN') + ' ' +
                new Date(task.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            const descHtml = task.description ? '<div class="task-desc text-muted small mt-1">' + escHtml(task.description).replace(/\n/g, '<br>') + '</div>' : '';

            return `
        <div class="task-item d-flex align-items-start gap-3 p-3 border-bottom task-status-todo" 
             data-task-id="${task.id}" data-status="TODO">
            <div class="task-status-toggle" style="min-width: 36px;">
                <button type="button" class="btn btn-sm btn-outline-secondary btn-task-toggle rounded-circle p-0 border-white" 
                        title="Chuyển trạng thái">
                    <i class="ph ph-circle" style="font-size: 1.2rem;"></i>
                </button>
            </div>
            <div class="flex-grow-1">
                <div class="task-title fw-bold">${escHtml(task.title)}</div>
                ${descHtml}
                <div class="task-meta mt-1">
                    <small class="text-muted">
                        ${creatorInfo}
                        <i class="ph ph-calendar me-1"></i>${createdAt}
                    </small>
                </div>
            </div>
            <div class="task-actions d-flex gap-1">
                <div class="dropdown">
                    <button class="btn btn-sm dropdown-toggle border-0 d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ph ph-dots-three-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item task-action-status" href="#" data-status="TODO"><i class="ph ph-circle me-2"></i>Chờ làm</a></li>
                        <li><a class="dropdown-item task-action-status" href="#" data-status="IN_PROGRESS"><i class="ph ph-circle-half me-2"></i>Đang làm</a></li>
                        <li><a class="dropdown-item task-action-status" href="#" data-status="DONE"><i class="ph ph-check-circle me-2"></i>Hoàn thành</a></li>
                        <li><a class="dropdown-item task-action-status" href="#" data-status="CANCELLED"><i class="ph ph-x-circle me-2"></i>Hủy</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger task-action-delete" href="#"><i class="ph ph-trash me-2"></i>Xóa</a></li>
                    </ul>
                </div>
            </div>
        </div>`;
        }

        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    });
</script>

<?php get_footer(); ?>