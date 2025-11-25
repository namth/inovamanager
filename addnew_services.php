<?php
/* 
    Template Name: Add New Website Service
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_website_services';
$current_user_id = get_current_user_id();

// Get website ID from URL if provided
$website_id = $_GET['website_id'] ?? 0;

// If website ID is provided, get website data and owner info
if ($website_id) {
    $websites_table = $wpdb->prefix . 'im_websites';
    $website = $wpdb->get_row($wpdb->prepare("
        SELECT w.*, u.name as owner_name, u.user_code as owner_code, u.email as owner_email, 
               u.phone_number as owner_phone, u.name as owner_company,
               d.domain_name
        FROM $websites_table w 
        LEFT JOIN {$wpdb->prefix}im_users u ON w.owner_user_id = u.id
        LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
        WHERE w.id = %d
    ", $website_id));
    
    if (!$website) {
        // wp_redirect(home_url('/list-website/')); // Redirect if website not found
        exit;
    }
}

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_service_field']) && wp_verify_nonce($_POST['post_service_field'], 'post_service')) {
        $website_id = intval($_POST['website_id']);
        $service_code = sanitize_text_field($_POST['service_code']);
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);
        $pricing_type = sanitize_text_field($_POST['pricing_type']);
        $estimated_manday = !empty($_POST['estimated_manday']) ? floatval($_POST['estimated_manday']) : null;
        $daily_rate = !empty($_POST['daily_rate']) ? intval($_POST['daily_rate']) : null;
        $fixed_price = !empty($_POST['fixed_price']) ? intval($_POST['fixed_price']) : null;
        $priority = sanitize_text_field($_POST['priority']);
        $status = sanitize_text_field($_POST['status']);
        $requested_by = intval($_POST['requested_by']);
        $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        $estimated_completion_date = !empty($_POST['estimated_completion_date']) ? 
            date('Y-m-d', strtotime(str_replace('/', '-', $_POST['estimated_completion_date']))) : null;
        $notes = sanitize_textarea_field($_POST['notes']);
        
        // Auto-generate service_code if empty
        if (empty($service_code)) {
            // Count existing services for this website + 1
            $service_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE website_id = %d", 
                $website_id
            ));
            $next_number = $service_count + 1;
            
            // Generate service code: SV + [Website ID] + [Count]
            $service_code = 'SV' . str_pad($website_id, 3, '0', STR_PAD_LEFT) . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        }
        
        // Validate required fields
        if (!empty($website_id) && !empty($title) && !empty($description) && !empty($pricing_type) && !empty($requested_by)) {
            // Validate pricing logic
            $pricing_valid = false;
            if ($pricing_type === 'DAILY') {
                $pricing_valid = !empty($estimated_manday) && !empty($daily_rate);
                $fixed_price = null; // Clear fixed_price for daily pricing
            } elseif ($pricing_type === 'FIXED') {
                $pricing_valid = !empty($fixed_price);
                $estimated_manday = null; // Clear daily fields for fixed pricing
                $daily_rate = null;
            }
            
            if ($pricing_valid) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'website_id' => $website_id,
                        'service_code' => $service_code,
                        'title' => $title,
                        'description' => $description,
                        'estimated_manday' => $estimated_manday,
                        'daily_rate' => $daily_rate,
                        'fixed_price' => $fixed_price,
                        'pricing_type' => $pricing_type,
                        'priority' => $priority,
                        'status' => $status,
                        'requested_by' => $requested_by,
                        'assigned_to' => $assigned_to,
                        'estimated_completion_date' => $estimated_completion_date,
                        'notes' => $notes
                    )
                );

                $service_id = $wpdb->insert_id;
                
                if ($service_id) {
                    // Redirect to website detail page
                    wp_redirect(home_url('/website/?website_id=') . $website_id);
                    exit;
                } else {
                    $notification = '<div class="alert alert-danger" role="alert">Không thể thêm mới dịch vụ. Vui lòng thử lại.</div>';
                }
            } else {
                $notification = '<div class="alert alert-warning" role="alert">Vui lòng điền đầy đủ thông tin giá theo loại định giá đã chọn.</div>';
            }
        } else {
            $notification = '<div class="alert alert-warning" role="alert">Vui lòng điền đầy đủ thông tin bắt buộc.</div>';
        }
    }
}

get_header();

// Debug (remove in production)
if ($wpdb->last_error) {
    echo '<div class="alert alert-danger">Database Error: ' . $wpdb->last_error . '</div>';
}
?>
<div class="content-wrapper mt-5">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-column">
                <!-- Back button -->
                <a href="<?php echo home_url('/website/?website_id=') . $website_id; ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Thêm mới dịch vụ website</h3>
                </div>
            </div>
            
            <?php if (isset($notification)) echo $notification; ?>
            
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php if (isset($website)): ?>
                    <!-- Website Information Card -->
                    <div class="card card-rounded p-3 min-w395 mb-4">
                        <div class="d-flex justify-content-center flex-column text-center nav-link">
                            <i class="ph ph-globe icon-lg p-4"></i>
                            <span class="fw-bold p-2">
                                Thông tin website
                            </span>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <tr>
                                        <td class="fw-bold p-2">Tên website</td>
                                        <td><?php echo esc_html($website->name); ?></td>
                                    </tr>
                                    <?php if (!empty($website->domain_name)): ?>
                                    <tr>
                                        <td class="fw-bold p-2">Domain</td>
                                        <td>
                                            <a href="https://<?php echo esc_html($website->domain_name); ?>" target="_blank" class="text-decoration-none">
                                                <?php echo esc_html($website->domain_name); ?>
                                                <i class="ph ph-arrow-square-out ms-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="fw-bold p-2">Chủ sở hữu</td>
                                        <td><?php echo esc_html($website->owner_name); ?> (<?php echo esc_html($website->owner_code); ?>)</td>
                                    </tr>
                                    <?php if (!empty($website->owner_email)): ?>
                                    <tr>
                                        <td class="fw-bold p-2">Email</td>
                                        <td><?php echo esc_html($website->owner_email); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($website->owner_company)): ?>
                                    <tr>
                                        <td class="fw-bold p-2">Công ty</td>
                                        <td><?php echo esc_html($website->owner_company); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Service Form -->
                    <form class="forms-sample col-md-8 col-lg-10 d-flex flex-column" action="" method="post">
                        <input type="hidden" name="website_id" value="<?php echo $website_id; ?>">
                        <input type="hidden" name="requested_by" value="<?php echo $website->owner_user_id; ?>">
                        
                        <div class="row">
                            <!-- Service Information Section -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header btn-primary">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-wrench me-2"></i>
                                            Thông tin dịch vụ
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="service_code" class="fw-bold">Mã dịch vụ</label>
                                            <input type="text" class="form-control" id="service_code" name="service_code" 
                                                   placeholder="Mã dịch vụ (tự động nếu để trống)">
                                            <small class="form-text text-muted">Để trống để tự động tạo mã</small>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="title" class="fw-bold">Tiêu đề dịch vụ <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="title" name="title" 
                                                   placeholder="Ví dụ: Cập nhật nội dung trang chủ" required>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="description" class="fw-bold">Mô tả chi tiết <span class="text-danger">*</span></label>
                                            <textarea class="form-control height-auto" id="description" name="description" rows="4" 
                                                     placeholder="Mô tả chi tiết về công việc cần thực hiện..." required></textarea>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="status" class="fw-bold">Trạng thái <span class="text-danger">*</span></label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="PENDING" selected>Chờ duyệt</option>
                                                <option value="APPROVED">Đã duyệt</option>
                                                <option value="IN_PROGRESS">Đang thực hiện</option>
                                                <option value="COMPLETED">Hoàn thành</option>
                                                <option value="CANCELLED">Đã hủy</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="assigned_to" class="fw-bold">Phân công cho</label>
                                            <select class="js-example-basic-single w-100" name="assigned_to" id="assigned_to">
                                                <option value="">-- Chọn nhân viên thực hiện --</option>
                                                <?php
                                                $users_table = $wpdb->prefix . 'im_users';
                                                $staff = $wpdb->get_results("SELECT * FROM $users_table WHERE user_type = 'ADMIN' AND status = 'ACTIVE' ORDER BY name");
                                                foreach ($staff as $member) {
                                                    echo '<option value="' . $member->id . '">' . esc_html($member->name) . ' (' . esc_html($member->user_code) . ')</option>';
                                                }
                                                ?>
                                            </select>
                                            <small class="form-text text-muted">Chọn nhân viên sẽ thực hiện dịch vụ này</small>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="priority" class="fw-bold">Mức độ ưu tiên <span class="text-danger">*</span></label>
                                            <select class="form-control" id="priority" name="priority" required>
                                                <option value="LOW">Thấp</option>
                                                <option value="MEDIUM" selected>Trung bình</option>
                                                <option value="HIGH">Cao</option>
                                                <option value="URGENT">Khẩn cấp</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Time and Cost Section -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header btn-secondary">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-currency-circle-dollar me-2"></i>
                                            Thời gian và chi phí
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="estimated_completion_date" class="fw-bold">Thời gian dự kiến hoàn thành</label>
                                            <div class="input-group date datepicker">
                                                <input type="text" class="form-control" id="estimated_completion_date" name="estimated_completion_date" 
                                                       placeholder="<?php echo date('d/m/Y H:i'); ?>">
                                                <span class="input-group-text bg-secondary text-white">
                                                    <i class="ph ph-calendar"></i>
                                                </span>
                                            </div>
                                            <small class="form-text text-muted">Định dạng: dd/mm/yyyy hh:mm (ví dụ: 15/06/2025 14:30)</small>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Loại định giá <span class="text-danger">*</span></label>
                                            <div class="d-flex gap-5 mt-2">
                                                <div class="form-check mb-2">
                                                    <label class="form-check-label" for="pricing_type_fixed">
                                                        <input class="form-check-input" type="radio" name="pricing_type" id="pricing_type_fixed" value="FIXED" required checked>
                                                        Giá cố định
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <label class="form-check-label" for="pricing_type_daily">
                                                        <input class="form-check-input" type="radio" name="pricing_type" id="pricing_type_daily" value="DAILY" required>
                                                        Theo ngày công
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Daily Pricing Fields -->
                                        <div id="daily_pricing" class="pricing-section" style="display: none;">
                                            <div class="alert alert-info">
                                                <small><i class="ph ph-info me-2"></i>Định giá theo ngày công</small>
                                            </div>
                                            
                                            <div class="form-group mb-3">
                                                <label for="estimated_manday" class="fw-bold">Ước tính ngày công <span class="text-danger">*</span></label>
                                                <input type="number" step="0.5" class="form-control" id="estimated_manday" name="estimated_manday" 
                                                       placeholder="Ví dụ: 2.5">
                                                <small class="form-text text-muted">Số ngày công ước tính để hoàn thành</small>
                                            </div>
                                            
                                            <div class="form-group mb-3">
                                                <label for="daily_rate" class="fw-bold">Đơn giá theo ngày (VNĐ) <span class="text-danger">*</span></label>
                                                <input type="number" step="1000" class="form-control" id="daily_rate" name="daily_rate" 
                                                       placeholder="500000">
                                                <small class="form-text text-muted">Giá tiền cho 1 ngày công</small>
                                            </div>
                                            
                                            <div class="form-group mb-3">
                                                <label for="estimated_total" class="fw-bold">Tổng ước tính (VNĐ)</label>
                                                <input type="text" class="form-control" id="estimated_total" readonly>
                                                <small class="form-text text-muted">Tự động tính: Ngày công × Đơn giá</small>
                                            </div>
                                        </div>
                                        
                                        <!-- Fixed Pricing Fields -->
                                        <div id="fixed_pricing" class="pricing-section" style="display: none;">
                                            <div class="alert alert-success">
                                                <small><i class="ph ph-info me-2"></i>Định giá cố định</small>
                                            </div>
                                            
                                            <div class="form-group mb-3">
                                                <label for="fixed_price" class="fw-bold">Giá cố định (VNĐ) <span class="text-danger">*</span></label>
                                                <input type="number" step="1000" class="form-control" id="fixed_price" name="fixed_price" 
                                                       placeholder="1000000">
                                                <small class="form-text text-muted">Tổng giá cố định cho toàn bộ dịch vụ</small>
                                            </div>
                                        </div>
                                        
                                        <!-- Notes Section -->
                                        <div class="form-group mb-3">
                                            <label for="notes" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control height-auto" id="notes" name="notes" rows="3" 
                                                     placeholder="Ghi chú thêm về dịch vụ..."></textarea>
                                        </div>
                                        
                                        <!-- Pricing Summary -->
                                        <div class="alert alert-warning" id="pricing_summary" style="display: none;">
                                            <h6><i class="ph ph-calculator me-2"></i>Tóm tắt giá</h6>
                                            <div id="summary_content">
                                                <!-- Will be filled by JavaScript -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php wp_nonce_field('post_service', 'post_service_field'); ?>
                        
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Lưu dịch vụ</span>
                            </button>
                            <a href="<?php echo home_url('/website/?website_id=') . $website_id; ?>" 
                               class="btn btn-light btn-icon-text ms-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-x btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Hủy</span>
                            </a>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        <h4>Lỗi</h4>
                        <p>Không tìm thấy thông tin website. Vui lòng kiểm tra lại.</p>
                        <a href="<?php echo home_url('/list-website/'); ?>" class="btn btn-secondary">
                            <i class="ph ph-arrow-left me-2"></i>Quay lại danh sách
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const pricingTypeRadios = document.querySelectorAll('input[name="pricing_type"]');
    const dailyPricing = document.getElementById('daily_pricing');
    const fixedPricing = document.getElementById('fixed_pricing');
    const pricingSummary = document.getElementById('pricing_summary');
    const summaryContent = document.getElementById('summary_content');
    
    const estimatedManday = document.getElementById('estimated_manday');
    const dailyRate = document.getElementById('daily_rate');
    const estimatedTotal = document.getElementById('estimated_total');
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
    
    // Calculate daily pricing total
    function calculateDailyTotal() {
        const mandays = parseFloat(estimatedManday.value) || 0;
        const rate = parseFloat(dailyRate.value) || 0;
        const total = mandays * rate;
        
        estimatedTotal.value = total.toLocaleString('vi-VN') + ' VNĐ';
        return total;
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
    
    // Add event listeners for calculations
    estimatedManday.addEventListener('input', function() {
        calculateDailyTotal();
        updatePricingSummary();
    });
    
    dailyRate.addEventListener('input', function() {
        calculateDailyTotal();
        updatePricingSummary();
    });
    
    fixedPrice.addEventListener('input', updatePricingSummary);
    
    // Enhanced datetime input for estimated completion date
    const estimatedCompletionInput = document.getElementById('estimated_completion_date');
    if (estimatedCompletionInput) {
        // Add support for datetime input format
        estimatedCompletionInput.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value) {
                // Try to parse and format the date
                const dateFormats = [
                    /^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})$/,  // dd/mm/yyyy hh:mm
                    /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/  // dd/mm/yyyy (will add default time)
                ];
                
                let parsedDate = null;
                
                for (const format of dateFormats) {
                    const match = value.match(format);
                    if (match) {
                        if (match.length === 6) {
                            // Full datetime format
                            parsedDate = new Date(match[3], match[2] - 1, match[1], match[4], match[5]);
                        } else if (match.length === 4) {
                            // Date only format - add default time (09:00)
                            parsedDate = new Date(match[3], match[2] - 1, match[1], 9, 0);
                        }
                        break;
                    }
                }
                
                if (parsedDate && !isNaN(parsedDate.getTime())) {
                    // Format back to dd/mm/yyyy hh:mm
                    const day = String(parsedDate.getDate()).padStart(2, '0');
                    const month = String(parsedDate.getMonth() + 1).padStart(2, '0');
                    const year = parsedDate.getFullYear();
                    const hours = String(parsedDate.getHours()).padStart(2, '0');
                    const minutes = String(parsedDate.getMinutes()).padStart(2, '0');
                    
                    this.value = `${day}/${month}/${year} ${hours}:${minutes}`;
                } else if (value) {
                    // Invalid format - show warning
                    this.style.borderColor = '#dc3545';
                    setTimeout(() => {
                        this.style.borderColor = '';
                    }, 2000);
                }
            }
        });
        
        // Add placeholder with current date + 7 days as default
        const defaultDate = new Date();
        defaultDate.setDate(defaultDate.getDate() + 7);
        const day = String(defaultDate.getDate()).padStart(2, '0');
        const month = String(defaultDate.getMonth() + 1).padStart(2, '0');
        const year = defaultDate.getFullYear();
        estimatedCompletionInput.placeholder = `${day}/${month}/${year} 09:00`;
    }
});
</script>

<?php get_footer(); ?>
