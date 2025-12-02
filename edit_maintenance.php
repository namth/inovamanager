<?php
/* 
    Template Name: Edit Maintenance Package
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_maintenance_packages';
$current_user_id = get_current_user_id();

// Get maintenance ID from URL
$maintenance_id = isset($_GET['maintenance_id']) ? intval($_GET['maintenance_id']) : 0;

// Get redirect URL from HTTP_REFERER
$redirect_url = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer = esc_url($_SERVER['HTTP_REFERER']);
    // Validate that referer is from same domain
    if (strpos($referer, home_url()) === 0) {
        $redirect_url = $referer;
    }
}

// Redirect if no maintenance ID provided
if (!$maintenance_id) {
    wp_redirect(home_url('/danh-sach-goi-bao-tri/'));
    exit;
}

// Get maintenance data
$maintenance = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $maintenance_id));

// Redirect if maintenance not found
if (!$maintenance) {
    wp_redirect(home_url('/danh-sach-goi-bao-tri/'));
    exit;
}

// Get user data
$users_table = $wpdb->prefix . 'im_users';
$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $maintenance->owner_user_id));

// Get websites using this maintenance package
$websites_table = $wpdb->prefix . 'im_websites';
$websites = $wpdb->get_results($wpdb->prepare("SELECT * FROM $websites_table WHERE maintenance_package_id = %d", $maintenance_id));

/* 
 * Process data when form is submitted
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['post_maintenance_field']) && wp_verify_nonce($_POST['post_maintenance_field'], 'post_maintenance')) {
        $owner_user_id = intval($_POST['owner_user_id']);
        $order_code = sanitize_text_field($_POST['order_code']);
        $monthly_fee = intval($_POST['monthly_fee']);

        // Convert date formats from DD/MM/YYYY to YYYY-MM-DD
        $renew_date = !empty($_POST['renew_date']) ? 
            date('Y-m-d', strtotime(str_replace('/', '-', $_POST['renew_date']))) : '';
        
        $billing_cycle_months = intval($_POST['billing_cycle_months']);
        $total_months_registered = $billing_cycle_months; // Set equal to billing cycle
        
        // Calculate expiry date based on renew date and total months
        $expiry_date = !empty($renew_date) ? 
            date('Y-m-d', strtotime($renew_date . " + $total_months_registered months")) : '';
            
        // Calculate price_per_cycle = monthly_fee × billing_cycle_months
        $price_per_cycle = $monthly_fee * $billing_cycle_months;
        $partner_id = !empty($_POST['partner_id']) ? intval($_POST['partner_id']) : null;
        $discount_amount = intval($_POST['discount_amount'] ?? 0);
        $actual_revenue = $price_per_cycle - $discount_amount;
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        // Validate required fields
        if (!empty($owner_user_id) && !empty($monthly_fee) && !empty($renew_date)) {
            // Auto-generate order_code if it's empty
            if (empty($order_code)) {
                // Get user code
                $users_table = $wpdb->prefix . 'im_users';
                $user_data = $wpdb->get_row($wpdb->prepare("SELECT user_code FROM $users_table WHERE id = %d", $owner_user_id));
                
                if ($user_data) {
                    // Count total maintenance packages for this user (excluding current maintenance) + 1
                    $maintenance_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE owner_user_id = %d AND id != %d", 
                        $owner_user_id, $maintenance_id
                    ));
                    $next_number = $maintenance_count + 1;
                    
                    // Add random number (0-9) after $next_number to prevent code collision
                    $random_digit = rand(0, 9);
                    
                    // Generate maintenance code: BT + [Count] + [Random Digit] + [User Code]
                    $order_code = 'BT' . str_pad($next_number, 2, '0', STR_PAD_LEFT) . $random_digit . $user_data->user_code;
                }
            }
            
            $update_result = $wpdb->update(
                $table_name,
                array(
                    'owner_user_id' => $owner_user_id,
                    'order_code' => $order_code,
                    'monthly_fee' => $monthly_fee,
                    'renew_date' => $renew_date,
                    'billing_cycle_months' => $billing_cycle_months,
                    'total_months_registered' => $total_months_registered,
                    'expiry_date' => $expiry_date,
                    'price_per_cycle' => $price_per_cycle,
                    'partner_id' => $partner_id,
                    'discount_amount' => $discount_amount,
                    'actual_revenue' => $actual_revenue,
                    'status' => $status,
                    'notes' => $notes
                ),
                array('id' => $maintenance_id),
                array(
                    '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d', 
                    '%d', '%d', '%d', '%s', '%s'
                ),
                array('%d')
            );

            if ($update_result !== false) {
                $notification = '<div class="alert alert-success" role="alert">Cập nhật gói bảo trì thành công!</div>';
                
                // Reload maintenance data after update
                $maintenance = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $maintenance_id));
                
                // Auto-redirect to previous page or maintenance list after 1.5 seconds
                $redirect_url = isset($_POST['redirect_url']) && !empty($_POST['redirect_url']) 
                    ? esc_url($_POST['redirect_url']) 
                    : home_url('/danh-sach-goi-bao-tri/');
                echo '<script>
                    setTimeout(function() {
                        window.location.href = "' . $redirect_url . '";
                    }, 1500);
                </script>';
            } else {
                $notification = '<div class="alert alert-danger" role="alert">Không thể cập nhật gói bảo trì. Vui lòng thử lại.</div>';
            }
        } else {
            $notification = '<div class="alert alert-warning" role="alert">Vui lòng điền đầy đủ thông tin bắt buộc.</div>';
        }
    }
}


get_header();
?>
<div class="content-wrapper mt-5">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-column">
                <!-- Add back button in the left side -->
                <a href="<?php echo home_url('/danh-sach-goi-bao-tri/'); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Chỉnh sửa gói bảo trì</h3>
                </div>
            </div>
            <div class="mt-3">
                <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                    <?php if (isset($user)): ?>
                    <div class="card card-rounded p-3 min-w395 mb-4">
                        <div class="d-flex justify-content-center flex-column text-center nav-link">
                            <i class="ph ph-user-circle icon-lg p-4"></i>
                            <span class="fw-bold p-2">
                                Thông tin khách hàng
                            </span>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <tr>
                                        <td class="fw-bold p-2">Mã khách hàng</td>
                                        <td><?php echo $user->user_code; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold p-2">Tên</td>
                                        <td><?php echo $user->name; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold p-2">Email</td>
                                        <td><?php echo $user->email; ?></td>
                                    </tr>
                                    <?php if (!empty($user->company_name)): ?>
                                    <tr>
                                        <td class="fw-bold p-2">Công ty</td>
                                        <td><?php echo $user->company_name; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($user->phone_number)): ?>
                                    <tr>
                                        <td class="fw-bold p-2">Số điện thoại</td>
                                        <td><?php echo $user->phone_number; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($websites)): ?>
                    <div class="card card-rounded p-3 min-w395 mb-4">
                        <div class="d-flex justify-content-center flex-column text-center">
                            <i class="ph ph-globe icon-lg p-4"></i>
                            <span class="fw-bold p-2">
                                Websites đang bảo trì
                            </span>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tên website</th>
                                            <th>URL</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($websites as $website): ?>
                                        <tr>
                                            <td><?php echo $website->name; ?></td>
                                            <td>
                                                <?php if (!empty($website->admin_url)): ?>
                                                <a href="<?php echo esc_url($website->admin_url); ?>" target="_blank" class="nav-link">
                                                    <span class="text-truncate d-inline-block" style="max-width: 200px;"><?php echo $website->admin_url; ?></span>
                                                    <i class="ph ph-arrow-square-out ms-1"></i>
                                                </a>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo home_url('/edit-website/?website_id=' . $website->id); ?>" class="btn btn-sm btn-outline-primary">
                                                    Xem chi tiết
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    if (isset($notification)) {
                        echo $notification;
                        // add more button to back to home page
                        echo '<div class="d-flex mt-3">';
                        echo '<a href="' . home_url('/danh-sach-bao-tri/') . '" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-list btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Xem danh sách gói bảo trì</span>
                              </a>';
                        echo '<a href="' . home_url('/maintenance/?maintenance_id=' . $maintenance_id) . '" class="btn btn-info btn-icon-text ms-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-eye btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Xem chi tiết gói bảo trì</span>
                              </a>';
                        echo '</div>';
                    } else {
                    ?>
                    <h3 class="card-title p-2 mb-3">Thông tin gói bảo trì</h3>
                    <form class="forms-sample col-md-8 col-lg-10 d-flex flex-column" action="" method="post">
                        <!-- Store previous page URL -->
                        <input type="hidden" name="redirect_url" id="redirect_url" value="<?php echo $redirect_url; ?>">
                        
                        <div class="row">
                            <!-- Maintenance Package Information Section -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header btn-primary">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-wrench me-2"></i>
                                            Thông tin gói bảo trì
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Khách hàng <span class="text-danger">*</span></label>
                                            <select class="js-example-basic-single w-100" name="owner_user_id" id="owner_user_id" required>
                                                <option value="">-- Chọn khách hàng --</option>
                                                <?php
                                                $users_table = $wpdb->prefix . 'im_users';
                                                $users = $wpdb->get_results("SELECT * FROM $users_table WHERE status = 'ACTIVE'");
                                                foreach ($users as $user_option) {
                                                    $user_label = $user_option->name;
                                                    if (!empty($user_option->company_name)) {
                                                        $user_label .= ' (' . $user_option->company_name . ')';
                                                    }
                                                    $selected = ($user_option->id == $maintenance->owner_user_id) ? 'selected' : '';
                                                    echo '<option value="' . $user_option->id . '" ' . $selected . '>' . $user_option->user_code . ' - ' . $user_label . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="order_code" class="fw-bold">Mã gói bảo trì</label>
                                            <input type="text" class="form-control" id="order_code" name="order_code" 
                                                   placeholder="Mã gói bảo trì" value="<?php echo esc_attr($maintenance->order_code); ?>">
                                            <small class="form-text text-muted">Để trống để tự động tạo mã dựa trên khách hàng mới</small>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="renew_date" class="fw-bold">Ngày gia hạn <span class="text-danger">*</span></label>
                                            <div class="input-group date datepicker">
                                                <input type="text" class="form-control" id="renew_date" name="renew_date" 
                                                       placeholder="<?php echo date('d/m/Y'); ?>" required
                                                       value="<?php echo date('d/m/Y', strtotime($maintenance->renew_date)); ?>">
                                                <span class="input-group-text bg-secondary text-white">
                                                    <i class="ph ph-calendar"></i>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label class="fw-bold" for="partner_id">Đối tác</label>
                                            <select class="js-example-basic-single w-100" name="partner_id" id="partner_id">
                                                <option value="">-- Chọn đối tác --</option>
                                                <?php
                                                $users_table = $wpdb->prefix . 'im_users';
                                                $partners = $wpdb->get_results("SELECT * FROM $users_table WHERE user_type = 'Partner' AND status = 'ACTIVE'");
                                                foreach ($partners as $partner) {
                                                    $selected = ($partner->id == $maintenance->partner_id) ? 'selected' : '';
                                                    echo '<option value="' . $partner->id . '" ' . $selected . '>' . $partner->user_code . ' - ' . $partner->name . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <small class="form-text text-muted">Chọn đối tác nếu có</small>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="status" class="fw-bold">Trạng thái <span class="text-danger">*</span></label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="NEW" <?php selected($maintenance->status, 'NEW'); ?>>Chờ thanh toán</option>
                                                <option value="ACTIVE" <?php selected($maintenance->status, 'ACTIVE'); ?>>Đang hoạt động</option>
                                                <option value="EXPIRED" <?php selected($maintenance->status, 'EXPIRED'); ?>>Hết hạn</option>
                                                <option value="SUSPENDED" <?php selected($maintenance->status, 'SUSPENDED'); ?>>Bị tạm ngưng</option>
                                                <option value="DELETED" <?php selected($maintenance->status, 'DELETED'); ?>>Đã xóa</option>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="notes" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control height-auto" id="notes" rows="3" placeholder="Ghi chú" name="notes"><?php echo esc_textarea($maintenance->notes); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pricing and Management Section -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="d-flex card-header btn-secondary">
                                        <h5 class="mt-1 mb-1">
                                            <i class="ph ph-currency-circle-dollar me-2"></i>
                                            Chi phí & Quản lý
                                        </h5>
                                        <span class="badge border-radius-9 bg-inverse-info text-info ms-2">Tự động tính</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="monthly_fee" class="fw-bold">Phí hàng tháng (VNĐ) <span class="text-danger">*</span></label>
                                            <input type="number" step="1000" class="form-control" id="monthly_fee" name="monthly_fee" 
                                                   value="<?php echo $maintenance->monthly_fee; ?>" required>
                                            <small class="form-text text-muted">Chi phí bảo trì hàng tháng</small>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="price_per_cycle_display" class="fw-bold">Giá mỗi chu kỳ (VNĐ)</label>
                                                    <input type="text" class="form-control" id="price_per_cycle_display" readonly 
                                                           value="<?php echo number_format($maintenance->price_per_cycle, 0, ',', '.') . ' ₫'; ?>">
                                                    <small class="form-text text-muted">Tự động tính: Phí hàng tháng × Chu kỳ thanh toán</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="billing_cycle_months" class="fw-bold">Chu kỳ thanh toán <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="billing_cycle_months" name="billing_cycle_months" required>
                                                        <option value="1" <?php selected($maintenance->billing_cycle_months, 1); ?>>1 tháng</option>
                                                        <option value="3" <?php selected($maintenance->billing_cycle_months, 3); ?>>3 tháng</option>
                                                        <option value="6" <?php selected($maintenance->billing_cycle_months, 6); ?>>6 tháng</option>
                                                        <option value="12" <?php selected($maintenance->billing_cycle_months, 12); ?>>12 tháng</option>
                                                        <option value="24" <?php selected($maintenance->billing_cycle_months, 24); ?>>24 tháng</option>
                                                        <option value="36" <?php selected($maintenance->billing_cycle_months, 36); ?>>36 tháng</option>
                                                    </select>
                                                    <small class="form-text text-muted">Số tháng được tính tiền mỗi lần thanh toán</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="discount_amount" class="fw-bold">Chiết khấu (VNĐ)</label>
                                            <input type="number" step="1000" class="form-control" id="discount_amount" name="discount_amount" 
                                                   value="<?php echo $maintenance->discount_amount; ?>">
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="actual_revenue_display" class="fw-bold">Doanh thu thực tế (VNĐ)</label>
                                            <input type="text" class="form-control" id="actual_revenue_display" readonly 
                                                   value="<?php echo number_format($maintenance->actual_revenue, 0, ',', '.') . ' ₫'; ?>">
                                            <small class="form-text text-muted">Tự động tính: Giá chu kỳ - Chiết khấu</small>
                                        </div>

                                        <div class="alert alert-info">
                                            <h6><i class="ph ph-info me-2"></i>Thông tin tự động</h6>
                                            <div class="row">
                                                <div class="col-6">
                                                    <small><strong>Ngày hết hạn:</strong></small><br>
                                                    <small id="expiry_date_display"><?php echo date('d/m/Y', strtotime($maintenance->expiry_date)); ?></small>
                                                </div>
                                                <div class="col-6">
                                                    <small><strong>Tình trạng:</strong></small><br>
                                                    <small id="status_display">
                                                        <?php
                                                        $now = new DateTime();
                                                        $expiry = new DateTime($maintenance->expiry_date);
                                                        $days_remaining = $now->diff($expiry)->format("%r%a");
                                                        
                                                        if ($days_remaining < 0) {
                                                            echo '<span class="text-danger">Đã quá hạn ' . abs($days_remaining) . ' ngày</span>';
                                                        } elseif ($days_remaining <= 7) {
                                                            echo '<span class="text-warning">Sắp hết hạn (' . $days_remaining . ' ngày)</span>';
                                                        } else {
                                                            echo '<span class="text-success">Còn ' . $days_remaining . ' ngày</span>';
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hidden field for total_months_registered, will be set equal to billing_cycle_months -->
                        <input type="hidden" id="total_months_registered" name="total_months_registered" value="<?php echo $maintenance->total_months_registered; ?>">
                        
                        <?php
                        wp_nonce_field('post_maintenance', 'post_maintenance_field');
                        ?>
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit"
                                class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Lưu thay đổi</span>
                            </button>
                            <a href="<?php echo home_url('/danh-sach-bao-tri/'); ?>" class="btn btn-light btn-icon-text ms-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-x btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Hủy</span>
                            </a>
                        </div>
                    </form>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Maintenance package calculations
    const monthlyFeeInput = document.getElementById('monthly_fee');
    const billingCycleSelect = document.getElementById('billing_cycle_months');
    const discountInput = document.getElementById('discount_amount');
    const totalMonthsInput = document.getElementById('total_months_registered');
    const renewDateInput = document.getElementById('renew_date');
    
    const pricePerCycleDisplay = document.getElementById('price_per_cycle_display');
    const actualRevenueDisplay = document.getElementById('actual_revenue_display');
    const expiryDateDisplay = document.getElementById('expiry_date_display');
    const statusDisplay = document.getElementById('status_display');

    function formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
    }

    function calculateValues() {
        const monthlyFee = parseInt(monthlyFeeInput.value) || 0;
        const billingCycle = parseInt(billingCycleSelect.value) || 1;
        const discount = parseInt(discountInput.value) || 0;
        const renewDate = renewDateInput.value;

        // Set total months equal to billing cycle
        totalMonthsInput.value = billingCycle;
        const totalMonths = billingCycle;

        // Calculate price per cycle
        const pricePerCycle = monthlyFee * billingCycle;
        pricePerCycleDisplay.value = formatCurrency(pricePerCycle);

        // Calculate actual revenue
        const actualRevenue = pricePerCycle - discount;
        actualRevenueDisplay.value = formatCurrency(actualRevenue);

        // Calculate expiry date
        if (renewDate) {
            const dateParts = renewDate.split('/');
            if (dateParts.length === 3) {
                const renewDateObj = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
                const expiryDate = new Date(renewDateObj);
                expiryDate.setMonth(expiryDate.getMonth() + totalMonths);
                
                const formattedDate = expiryDate.toLocaleDateString('vi-VN');
                expiryDateDisplay.textContent = formattedDate;

                // Calculate status based on expiry date
                const today = new Date();
                const timeDiff = expiryDate.getTime() - today.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));

                if (daysDiff < 0) {
                    statusDisplay.innerHTML = '<span class="text-danger">Đã quá hạn ' + Math.abs(daysDiff) + ' ngày</span>';
                } else if (daysDiff <= 7) {
                    statusDisplay.innerHTML = '<span class="text-warning">Sắp hết hạn (' + daysDiff + ' ngày)</span>';
                } else {
                    statusDisplay.innerHTML = '<span class="text-success">Còn ' + daysDiff + ' ngày</span>';
                }
            }
        }
    }

    // Initialize calculations
    calculateValues();

    // Add event listeners
    if (monthlyFeeInput) monthlyFeeInput.addEventListener('input', calculateValues);
    if (billingCycleSelect) billingCycleSelect.addEventListener('change', calculateValues);
    if (discountInput) discountInput.addEventListener('input', calculateValues);
    if (renewDateInput) renewDateInput.addEventListener('change', calculateValues);
});
</script>

<?php
get_footer();
?>