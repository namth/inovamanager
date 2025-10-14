<?php
/* 
    Template Name: Service Request - Stage 1: Customer Request
    Purpose: Initial service request form for customers
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_website_services';

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
        wp_redirect(home_url('/danh-sach-website/')); 
        exit;
    }
} else {
    $website = null;
}

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_service_request']) && wp_verify_nonce($_POST['post_service_request'], 'post_service_request')) {
        $website_id = intval($_POST['website_id']);
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);
        $requested_by = intval($_POST['requested_by']);
        
        // Get customer code from requested_by user
        $customer = $wpdb->get_row($wpdb->prepare("
            SELECT user_code FROM {$wpdb->prefix}im_users WHERE id = %d
        ", $requested_by));
        
        $customer_code = $customer ? $customer->user_code : '';
        
        // Generate service code: SV + website_id + customer_code + count
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) + 1 FROM $table_name
        ", $website_id));
        $service_code = 'SV' . $website_id . $customer_code . $count;

        // Validation
        if (!empty($website_id) && !empty($title) && !empty($requested_by)) {
            $insert_data = array(
                'website_id' => $website_id,
                'service_code' => $service_code,
                'title' => $title,
                'description' => $description,
                'status' => 'PENDING',
                'requested_by' => $requested_by,
                // These fields will be filled in Stage 2 (Quotation)
                'estimated_manday' => NULL,
                'daily_rate' => NULL,
                'fixed_price' => NULL,
                'pricing_type' => NULL,
                'assigned_to' => NULL,
                'start_date' => NULL,
                'estimated_completion_date' => NULL,
                'completion_date' => NULL,
                'priority' => 'MEDIUM',
                'notes' => NULL,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );

            $result = $wpdb->insert($table_name, $insert_data);

            if ($result !== false) {
                $message = '<div class="alert alert-success"><strong>Thành công!</strong> Yêu cầu dịch vụ đã được gửi với mã số: ' . $service_code . '</div>';
            } else {
                $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Không thể gửi yêu cầu dịch vụ.</div>';
            }            } else {
                $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Vui lòng điền đầy đủ thông tin bắt buộc (Website, Tiêu đề yêu cầu, Người yêu cầu).</div>';
            }
    }
}

// Get all users for requester selection
$users_table = $wpdb->prefix . 'im_users';
$users = $wpdb->get_results("SELECT id, name, user_code, email FROM $users_table WHERE status = 'ACTIVE' ORDER BY name");

get_header(); 
?>

<div class="main-panel">
    <div class="content-wrapper">
        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body box-shadow">
                        <h4 class="card-title">
                            <i class="ph ph-plus-circle me-2"></i>
                            Gửi yêu cầu dịch vụ website
                        </h4>
                        <p class="card-description">Giai đoạn 1: Tiếp nhận yêu cầu từ khách hàng</p>
                        
                        <!-- Display messages -->
                        <?php if (isset($message)) echo $message; ?>
                        <?php if (isset($error_message)) echo $error_message; ?>

                        <form class="forms-sample" method="post" action="">
                            <?php wp_nonce_field('post_service_request', 'post_service_request'); ?>
                            
                            <div class="row">
                                <!-- Left Column: Service Information -->
                                <div class="col-md-8">
                                    <div class="card mb-4">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-1 mt-1">
                                                <i class="ph ph-info me-2"></i>
                                                Thông tin yêu cầu
                                            </h5>
                                        </div>
                                        <div class="card-body box-shadow">
                                            <!-- Website Context -->
                                            <?php if ($website): ?>
                                            <div class="alert alert-info">
                                                <h6><i class="ph ph-globe me-2"></i>Thông tin website</h6>
                                                <strong>Tên website:</strong> <?php echo esc_html($website->name); ?><br>
                                                <strong>Domain:</strong> <?php echo esc_html($website->domain_name ?: 'Chưa có domain'); ?><br>
                                                <strong>Chủ sở hữu:</strong> <?php echo esc_html($website->owner_name); ?> (<?php echo esc_html($website->owner_code); ?>)
                                            </div>
                                            <input type="hidden" name="website_id" value="<?php echo esc_attr($website_id); ?>">
                                            <?php else: ?>
                                            <div class="form-group mb-3">
                                                <label for="website_id" class="fw-bold">Website <span class="text-danger">*</span></label>
                                                <select class="form-control" id="website_id" name="website_id" required>
                                                    <option value="">-- Chọn website --</option>
                                                    <?php
                                                    $websites = $wpdb->get_results("
                                                        SELECT w.id, w.name, d.domain_name, u.name as owner_name 
                                                        FROM {$wpdb->prefix}im_websites w
                                                        LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
                                                        LEFT JOIN {$wpdb->prefix}im_users u ON w.owner_user_id = u.id
                                                        ORDER BY w.name
                                                    ");
                                                    foreach ($websites as $ws) {
                                                        echo '<option value="' . $ws->id . '">' . esc_html($ws->name) . ' - ' . esc_html($ws->domain_name) . ' (' . esc_html($ws->owner_name) . ')</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <?php endif; ?>

                                            <div class="form-group mb-3">
                                                <label for="title" class="fw-bold">Tiêu đề yêu cầu <span class="text-danger">*</span></label>
                                                <select class="form-control" id="title" name="title" required>
                                                    <option value="">-- Chọn loại yêu cầu --</option>
                                                    <option value="Thiết kế website mới">Thiết kế website mới</option>
                                                    <option value="Chỉnh sửa / thêm tính năng cho website">Chỉnh sửa / thêm tính năng cho website</option>
                                                    <option value="Yêu cầu khác">Yêu cầu khác</option>
                                                </select>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="description" class="fw-bold">Mô tả chi tiết</label>
                                                <textarea class="form-control" id="description" name="description" rows="10" 
                                                          placeholder="Mô tả chi tiết yêu cầu của bạn..."></textarea>
                                                <small class="form-text text-muted">Mô tả chi tiết giúp chúng tôi hiểu rõ hơn về yêu cầu và báo giá chính xác (không bắt buộc)</small>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="requested_by" class="fw-bold">Người yêu cầu <span class="text-danger">*</span></label>
                                                <select class="form-control" id="requested_by" name="requested_by" required>
                                                    <option value="">-- Chọn người yêu cầu --</option>
                                                    <?php foreach ($users as $user): ?>
                                                        <option value="<?php echo $user->id; ?>" 
                                                                <?php echo ($website && $user->id == $website->owner_user_id) ? 'selected' : ''; ?>>
                                                            <?php echo esc_html($user->name . ' (' . $user->user_code . ')'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column: Notes -->
                                <div class="col-md-4">
                                    <div class="card mb-4">
                                        <div class="card-header bg-secondary text-white">
                                            <h5 class="mb-1 mt-1">
                                                <i class="ph ph-note me-2"></i>
                                                Thông tin bổ sung
                                            </h5>
                                        </div>
                                        <div class="card-body box-shadow">
                                            <div class="alert alert-warning">
                                                <small>
                                                    <i class="ph ph-warning me-2"></i>
                                                    <strong>Lưu ý:</strong><br>
                                                    • Chọn loại yêu cầu từ danh sách<br>
                                                    • Yêu cầu sẽ có trạng thái "Chờ xử lý"<br>
                                                    • Chúng tôi sẽ liên hệ báo giá trong 24h<br>
                                                    • Mã yêu cầu sẽ được tạo tự động
                                                </small>
                                            </div>

                                            <div class="alert alert-info">
                                                <small>
                                                    <i class="ph ph-info me-2"></i>
                                                    <strong>Quy trình xử lý:</strong><br>
                                                    1. Tiếp nhận yêu cầu<br>
                                                    2. Báo giá & duyệt<br>
                                                    3. Tạo hóa đơn<br>
                                                    4. Thực hiện công việc<br>
                                                    5. Hoàn thành & bàn giao
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="ph ph-paper-plane me-2"></i>Gửi yêu cầu
                                    </button>
                                    <a href="<?php echo home_url('/service-list/'); ?>" class="btn btn-light">
                                        <i class="ph ph-arrow-left me-2"></i>Quay lại
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const titleSelect = document.getElementById('title');
    const description = document.getElementById('description');
    
    // Update placeholder cho description dựa trên title được chọn
    titleSelect.addEventListener('change', function() {
        const selectedValue = this.value;
        let placeholder = 'Mô tả chi tiết yêu cầu của bạn...';
        
        switch(selectedValue) {
            case 'Thiết kế website mới':
                placeholder = 'Mô tả chi tiết về website muốn thiết kế: mục đích sử dụng, đối tượng khách hàng, tính năng cần có, phong cách thiết kế...';
                break;
            case 'Chỉnh sửa / thêm tính năng cho website':
                placeholder = 'Mô tả chi tiết về tính năng cần thêm hoặc phần nào cần chỉnh sửa trên website hiện tại...';
                break;
            case 'Yêu cầu khác':
                placeholder = 'Mô tả chi tiết yêu cầu của bạn (bảo trì, tối ưu SEO, sửa lỗi, tư vấn...)';
                break;
        }
        
        description.placeholder = placeholder;
    });
    
    // Auto-focus vào description sau khi chọn title
    titleSelect.addEventListener('change', function() {
        if (this.value) {
            setTimeout(() => {
                description.focus();
            }, 100);
        }
    });
});
</script>

<?php get_footer(); ?>
