<?php
/* 
    Template Name: Service Quotation - Stage 2: Admin Quotation & Approval
    Purpose: Admin/PM provides quotation for pending service requests
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_website_services';

// Get service ID from URL
$service_id = $_GET['service_id'] ?? 0;

// Get service request data
if ($service_id) {
    $service = $wpdb->get_row($wpdb->prepare("
        SELECT s.*, w.name as website_name, d.domain_name,
               u1.name as requester_name, u1.user_code as requester_code,
               u2.name as assignee_name, u2.user_code as assignee_code
        FROM $table_name s
        LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
        LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
        LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
        LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
        WHERE s.id = %d
    ", $service_id));
    
    if (!$service) {
        wp_redirect(home_url('/danh-sach-dich-vu/'));
        exit;
    }
}

/* 
 * Process quotation form submission
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_quotation']) && wp_verify_nonce($_POST['post_quotation'], 'post_quotation')) {
        $pricing_type = sanitize_text_field($_POST['pricing_type']);
        $estimated_manday = !empty($_POST['estimated_manday']) ? floatval($_POST['estimated_manday']) : null;
        $daily_rate = !empty($_POST['daily_rate']) ? intval($_POST['daily_rate']) : null;
        $fixed_price = !empty($_POST['fixed_price']) ? intval($_POST['fixed_price']) : null;
        $priority = sanitize_text_field($_POST['priority']);
        $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        $estimated_completion_date = !empty($_POST['estimated_completion_date']) ? 
            $_POST['estimated_completion_date'] : null;
        $quotation_notes = sanitize_textarea_field($_POST['quotation_notes']);

        // Validation
        if (!empty($pricing_type)) {
            if ($pricing_type === 'DAILY') {
                if (empty($estimated_manday) || empty($daily_rate)) {
                    $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Vui lòng nhập đầy đủ thông tin cho loại định giá theo ngày công.</div>';
                } else {
                    $valid = true;
                }
            } elseif ($pricing_type === 'FIXED') {
                if (empty($fixed_price)) {
                    $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Vui lòng nhập giá cố định.</div>';
                } else {
                    $valid = true;
                }
            }

            if (isset($valid) && $valid) {
                $update_data = array(
                    'pricing_type' => $pricing_type,
                    'estimated_manday' => $estimated_manday,
                    'daily_rate' => $daily_rate,
                    'fixed_price' => $fixed_price,
                    'priority' => $priority,
                    'assigned_to' => $assigned_to,
                    'estimated_completion_date' => $estimated_completion_date,
                    'notes' => $quotation_notes,
                    'updated_at' => current_time('mysql')
                );

                $result = $wpdb->update($table_name, $update_data, array('id' => $service_id));

                if ($result !== false) {
                    $message = '<div class="alert alert-success"><strong>Thành công!</strong> Báo giá đã được cập nhật.</div>';
                    // Refresh service data
                    $service = $wpdb->get_row($wpdb->prepare("
                        SELECT s.*, w.name as website_name, d.domain_name,
                               u1.name as requester_name, u1.user_code as requester_code,
                               u2.name as assignee_name, u2.user_code as assignee_code
                        FROM $table_name s
                        LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
                        LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
                        LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
                        LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
                        WHERE s.id = %d
                    ", $service_id));
                } else {
                    $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Không thể cập nhật báo giá.</div>';
                }
            }
        } else {
            $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Vui lòng chọn loại định giá.</div>';
        }
    }
    
    // Handle approval/rejection
    if (isset($_POST['update_status']) && wp_verify_nonce($_POST['update_status'], 'update_status')) {
        $new_status = sanitize_text_field($_POST['status']);
        $approval_notes = sanitize_textarea_field($_POST['approval_notes']);
        
        $update_data = array(
            'status' => $new_status,
            'notes' => $approval_notes,
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->update($table_name, $update_data, array('id' => $service_id));
        
        if ($result !== false) {
            if ($new_status === 'APPROVED') {
                $message = '<div class="alert alert-success"><strong>Thành công!</strong> Yêu cầu đã được duyệt. Có thể tiến hành tạo hóa đơn.</div>';
            } else {
                $message = '<div class="alert alert-info"><strong>Thành công!</strong> Trạng thái đã được cập nhật.</div>';
            }
            // Refresh service data
            $service = $wpdb->get_row($wpdb->prepare("
                SELECT s.*, w.name as website_name, d.domain_name,
                       u1.name as requester_name, u1.user_code as requester_code,
                       u2.name as assignee_name, u2.user_code as assignee_code
                FROM $table_name s
                LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
                LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
                LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
                LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
                WHERE s.id = %d
            ", $service_id));
        } else {
            $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Không thể cập nhật trạng thái.</div>';
        }
    }
}

// Get all users for assignment
$users_table = $wpdb->prefix . 'im_users';
$users = $wpdb->get_results("SELECT id, name, user_code FROM $users_table WHERE status = 'ACTIVE' ORDER BY name");

get_header(); 
?>

<div class="main-panel">
    <div class="content-wrapper">
        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="ph ph-currency-circle-dollar me-2"></i>
                            Báo giá dịch vụ website
                        </h4>
                        <p class="card-description">Giai đoạn 2: Báo giá & duyệt yêu cầu</p>
                        
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
                                            Thông tin yêu cầu
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Mã yêu cầu:</strong> <?php echo esc_html($service->service_code); ?></p>
                                                <p><strong>Website:</strong> <?php echo esc_html($service->website_name); ?></p>
                                                <p><strong>Domain:</strong> <?php echo esc_html($service->domain_name ?: 'Chưa có'); ?></p>
                                                <p><strong>Tiêu đề:</strong> <?php echo esc_html($service->title); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Người yêu cầu:</strong> <?php echo esc_html($service->requester_name . ' (' . $service->requester_code . ')'); ?></p>
                                                <p><strong>Trạng thái:</strong> 
                                                    <span class="badge badge-<?php 
                                                        echo $service->status === 'PENDING' ? 'warning' : 
                                                            ($service->status === 'APPROVED' ? 'success' : 'secondary'); 
                                                    ?>">
                                                        <?php echo esc_html($service->status); ?>
                                                    </span>
                                                </p>
                                                <p><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($service->created_at)); ?></p>
                                                <p><strong>Người phụ trách:</strong> <?php echo $service->assignee_name ? esc_html($service->assignee_name . ' (' . $service->assignee_code . ')') : 'Chưa phân công'; ?></p>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12">
                                                <p><strong>Mô tả:</strong></p>
                                                <div class="border p-3 bg-light">
                                                    <?php echo nl2br(esc_html($service->description)); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Left Column: Quotation Form -->
                            <div class="col-md-8">
                                <div class="card mb-4">
                                    <div class="card-header btn-secondary">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-calculator me-2"></i>
                                            Thông tin báo giá
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form class="forms-sample" method="post" action="">
                                            <?php wp_nonce_field('post_quotation', 'post_quotation'); ?>
                                            
                                            <div class="form-group mb-3">
                                                <label class="fw-bold">Loại định giá <span class="text-danger">*</span></label>
                                                <div class="d-flex gap-5 mt-2">
                                                    <div class="form-check mb-2">
                                                        <label class="form-check-label" for="pricing_type_daily">
                                                            <input class="form-check-input" type="radio" name="pricing_type" id="pricing_type_daily" value="DAILY" 
                                                                   <?php echo ($service->pricing_type === 'DAILY') ? 'checked' : ''; ?> required>
                                                            Theo ngày công
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <label class="form-check-label" for="pricing_type_fixed">
                                                            <input class="form-check-input" type="radio" name="pricing_type" id="pricing_type_fixed" value="FIXED" 
                                                                   <?php echo ($service->pricing_type === 'FIXED') ? 'checked' : ''; ?> required>
                                                            Giá cố định
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Daily Pricing Fields -->
                                            <div id="daily_pricing" class="pricing-section" style="display: none;">
                                                <div class="alert alert-info">
                                                    <small><i class="ph ph-info me-2"></i>Định giá theo ngày công</small>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group mb-3">
                                                            <label for="estimated_manday" class="fw-bold">Số ngày công ước tính</label>
                                                            <input type="number" class="form-control" id="estimated_manday" name="estimated_manday" 
                                                                   step="0.1" min="0.1" placeholder="Ví dụ: 2.5" 
                                                                   value="<?php echo esc_attr($service->estimated_manday); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group mb-3">
                                                            <label for="daily_rate" class="fw-bold">Đơn giá (VNĐ/ngày)</label>
                                                            <input type="number" class="form-control" id="daily_rate" name="daily_rate" 
                                                                   min="1" placeholder="Ví dụ: 200000" 
                                                                   value="<?php echo esc_attr($service->daily_rate); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Fixed Pricing Fields -->
                                            <div id="fixed_pricing" class="pricing-section" style="display: none;">
                                                <div class="alert alert-info">
                                                    <small><i class="ph ph-info me-2"></i>Định giá cố định</small>
                                                </div>
                                                <div class="form-group mb-3">
                                                    <label for="fixed_price" class="fw-bold">Giá cố định (VNĐ)</label>
                                                    <input type="number" class="form-control" id="fixed_price" name="fixed_price" 
                                                           min="1" placeholder="Ví dụ: 3000000" 
                                                           value="<?php echo esc_attr($service->fixed_price); ?>">
                                                </div>
                                            </div>

                                            <!-- Pricing Summary -->
                                            <div id="pricing_summary" class="alert alert-light border" style="display: none;">
                                                <h6 class="fw-bold">Tóm tắt báo giá</h6>
                                                <div id="summary_content"></div>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label class="fw-bold">Độ ưu tiên</label>
                                                <div class="d-flex gap-5 mt-2">
                                                    <div class="form-check mb-2">
                                                        <label class="form-check-label" for="priority_low">
                                                            <input class="form-check-input" type="radio" name="priority" id="priority_low" value="LOW" 
                                                                   <?php echo ($service->priority === 'LOW') ? 'checked' : ''; ?>>
                                                            Thấp
                                                        </label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <label class="form-check-label" for="priority_medium">
                                                            <input class="form-check-input" type="radio" name="priority" id="priority_medium" value="MEDIUM" 
                                                                   <?php echo ($service->priority === 'MEDIUM') ? 'checked' : ''; ?>>
                                                            Trung bình
                                                        </label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <label class="form-check-label" for="priority_high">
                                                            <input class="form-check-input" type="radio" name="priority" id="priority_high" value="HIGH" 
                                                                   <?php echo ($service->priority === 'HIGH') ? 'checked' : ''; ?>>
                                                            Cao
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <label class="form-check-label" for="priority_urgent">
                                                            <input class="form-check-input" type="radio" name="priority" id="priority_urgent" value="URGENT" 
                                                                   <?php echo ($service->priority === 'URGENT') ? 'checked' : ''; ?>>
                                                            Khẩn cấp
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="assigned_to" class="fw-bold">Phân công thực hiện</label>
                                                <select class="form-control" id="assigned_to" name="assigned_to">
                                                    <option value="">-- Chọn người thực hiện --</option>
                                                    <?php foreach ($users as $user): ?>
                                                        <option value="<?php echo $user->id; ?>" 
                                                                <?php echo ($service->assigned_to == $user->id) ? 'selected' : ''; ?>>
                                                            <?php echo esc_html($user->name . ' (' . $user->user_code . ')'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="estimated_completion_date" class="fw-bold">Ngày dự kiến hoàn thành</label>
                                                <input type="date" class="form-control" id="estimated_completion_date" name="estimated_completion_date" 
                                                       value="<?php echo $service->estimated_completion_date ? date('Y-m-d', strtotime($service->estimated_completion_date)) : ''; ?>">
                                                <small class="form-text text-muted">Chọn ngày dự kiến hoàn thành công việc</small>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="quotation_notes" class="fw-bold">Ghi chú báo giá</label>
                                                <textarea class="form-control" id="quotation_notes" name="quotation_notes" rows="3" 
                                                          placeholder="Ghi chú về báo giá, yêu cầu kỹ thuật..."><?php echo esc_textarea($service->notes); ?></textarea>
                                            </div>

                                            <button type="submit" class="btn btn-secondary me-2">
                                                <i class="ph ph-calculator me-2"></i>Cập nhật báo giá
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Status Update -->
                            <div class="col-md-4">
                                <div class="card mb-4">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-check-circle me-2"></i>
                                            Duyệt yêu cầu
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <?php wp_nonce_field('update_status', 'update_status'); ?>
                                            
                                            <div class="form-group mb-3">
                                                <label for="status" class="fw-bold">Trạng thái</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="PENDING" <?php echo ($service->status === 'PENDING') ? 'selected' : ''; ?>>Chờ xử lý</option>
                                                    <option value="APPROVED" <?php echo ($service->status === 'APPROVED') ? 'selected' : ''; ?>>Đã duyệt</option>
                                                    <option value="CANCELLED" <?php echo ($service->status === 'CANCELLED') ? 'selected' : ''; ?>>Đã hủy</option>
                                                </select>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="approval_notes" class="fw-bold">Ghi chú duyệt</label>
                                                <textarea class="form-control" id="approval_notes" name="approval_notes" rows="3" 
                                                          placeholder="Ghi chú về quyết định duyệt/từ chối..."></textarea>
                                            </div>

                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="ph ph-check me-2"></i>Cập nhật trạng thái
                                            </button>
                                        </form>

                                        <?php if ($service->status === 'APPROVED'): ?>
                                        <div class="mt-3">
                                            <a href="<?php echo home_url('/tao-hoa-don/?service_id=' . $service->id); ?>" class="btn btn-primary btn-sm w-100">
                                                <i class="ph ph-receipt me-2"></i>Tạo hóa đơn
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Guidelines -->
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="fw-bold text-muted">Hướng dẫn báo giá</h6>
                                        <small class="text-muted">
                                            • <strong>Theo ngày công:</strong> Phù hợp với công việc có thể ước tính thời gian<br>
                                            • <strong>Giá cố định:</strong> Phù hợp với gói dịch vụ hoặc yêu cầu đặc biệt<br>
                                            • Cần phân công người thực hiện trước khi duyệt<br>
                                            • Sau khi duyệt có thể tạo hóa đơn
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <a href="<?php echo home_url('/danh-sach-dich-vu/'); ?>" class="btn btn-light">
                                    <i class="ph ph-arrow-left me-2"></i>Quay lại danh sách
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pricingTypeRadios = document.querySelectorAll('input[name="pricing_type"]');
    const dailyPricing = document.getElementById('daily_pricing');
    const fixedPricing = document.getElementById('fixed_pricing');
    const pricingSummary = document.getElementById('pricing_summary');
    const summaryContent = document.getElementById('summary_content');
    
    const estimatedManday = document.getElementById('estimated_manday');
    const dailyRate = document.getElementById('daily_rate');
    const fixedPrice = document.getElementById('fixed_price');
    
    // Handle pricing type change
    pricingTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const value = this.value;
            
            // Hide all pricing sections
            dailyPricing.style.display = 'none';
            fixedPricing.style.display = 'none';
            pricingSummary.style.display = 'none';
            
            // Clear required attributes
            document.querySelectorAll('.pricing-section input').forEach(input => {
                input.removeAttribute('required');
            });
            
            if (value === 'DAILY') {
                dailyPricing.style.display = 'block';
                estimatedManday.setAttribute('required', 'required');
                dailyRate.setAttribute('required', 'required');
            } else if (value === 'FIXED') {
                fixedPricing.style.display = 'block';
                fixedPrice.setAttribute('required', 'required');
            }
            
            updatePricingSummary();
        });
    });

    // Initialize on page load
    const checkedRadio = document.querySelector('input[name="pricing_type"]:checked');
    if (checkedRadio) {
        checkedRadio.dispatchEvent(new Event('change'));
    }

    // Update pricing summary
    function updatePricingSummary() {
        const selectedPricingType = document.querySelector('input[name="pricing_type"]:checked');
        const pricingTypeValue = selectedPricingType ? selectedPricingType.value : '';
        let content = '';
        
        if (pricingTypeValue === 'DAILY') {
            const mandays = parseFloat(estimatedManday.value) || 0;
            const rate = parseFloat(dailyRate.value) || 0;
            const total = mandays * rate;
            
            if (mandays > 0 && rate > 0) {
                content = `
                    <small><strong>Loại:</strong> Theo ngày công</small><br>
                    <small><strong>Ngày công:</strong> ${mandays} ngày</small><br>
                    <small><strong>Đơn giá:</strong> ${rate.toLocaleString('vi-VN')} VNĐ/ngày</small><br>
                    <small><strong>Tổng cộng:</strong> <span class="text-success fw-bold">${total.toLocaleString('vi-VN')} VNĐ</span></small>
                `;
                pricingSummary.style.display = 'block';
            }
        } else if (pricingTypeValue === 'FIXED') {
            const price = parseFloat(fixedPrice.value) || 0;
            
            if (price > 0) {
                content = `
                    <small><strong>Loại:</strong> Giá cố định</small><br>
                    <small><strong>Tổng cộng:</strong> <span class="text-success fw-bold">${price.toLocaleString('vi-VN')} VNĐ</span></small>
                `;
                pricingSummary.style.display = 'block';
            }
        }
        
        summaryContent.innerHTML = content;
    }

    // Add event listeners for price calculation
    if (estimatedManday) estimatedManday.addEventListener('input', updatePricingSummary);
    if (dailyRate) dailyRate.addEventListener('input', updatePricingSummary);
    if (fixedPrice) fixedPrice.addEventListener('input', updatePricingSummary);
});
</script>

<?php get_footer(); ?>
