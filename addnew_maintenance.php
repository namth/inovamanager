<?php
/* 
    Template Name: Add New Maintenance Package
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_maintenance_packages';
$current_user_id = get_current_user_id();

// Get user ID from URL if provided
$user_id = $_GET['user_id'] ?? 0;
// Get website ID from URL if provided
$website_id = $_GET['website_id'] ?? 0;

// If user ID is provided, get user data
if ($user_id) {
    $users_table = $wpdb->prefix . 'im_users';
    $user = $wpdb->get_row("SELECT * FROM $users_table WHERE id = $user_id");
    if (!$user) {
        wp_redirect(home_url('/danh-sach-bao-tri/')); // Redirect if user not found
        exit;
    }
}

// If website ID is provided, get website data
if ($website_id) {
    $websites_table = $wpdb->prefix . 'im_websites';
    $website = $wpdb->get_row("SELECT * FROM $websites_table WHERE id = $website_id");
    if (!$website) {
        wp_redirect(home_url('/danh-sach-bao-tri/')); // Redirect if website not found
        exit;
    }
    
    // If website exists, get its owner's user data
    if (!$user_id && $website) {
        $users_table = $wpdb->prefix . 'im_users';
        $user = $wpdb->get_row("SELECT * FROM $users_table WHERE id = $website->owner_user_id");
        $user_id = $website->owner_user_id;
    }
}

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
        $total_months_registered = $billing_cycle_months; // Set equal to billing cycle for now
        
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
                    // Count total maintenance packages for this user + 1
                    $maintenance_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE owner_user_id = %d", 
                        $owner_user_id
                    ));
                    $next_number = $maintenance_count + 1;
                    
                    // Generate maintenance code: BT + [Count] + [User Code]
                    $order_code = 'BT' . str_pad($next_number, 2, '0', STR_PAD_LEFT) . $user_data->user_code;
                }
            }
            $wpdb->insert(
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
                )
            );

            $maintenance_id = $wpdb->insert_id;
            
            if ($maintenance_id) {
                // Update website's maintenance_package_id if website_id was provided
                if ($website_id > 0) {
                    $websites_table = $wpdb->prefix . 'im_websites';
                    $wpdb->update(
                        $websites_table,
                        array('maintenance_package_id' => $maintenance_id),
                        array('id' => $website_id),
                        array('%d'),
                        array('%d')
                    );
                    
                    // After updating, redirect to the website detail page if it exists
                    if (isset($website)) {
                        wp_redirect(home_url('/edit-website/?website_id=') . $website_id);
                        exit;
                    }
                }
                
                // If not redirected above, redirect to maintenance list or user detail page
                if ($user_id) {
                    wp_redirect(home_url('/user-detail/?user_id=') . $user_id);
                } else {
                    wp_redirect(home_url('/danh-sach-bao-tri/'));
                }
                exit;
            } else {
                $notification = '<div class="alert alert-danger" role="alert">Không thể thêm mới gói bảo trì. Vui lòng thử lại.</div>';
            }
        } else {
            $notification = '<div class="alert alert-warning" role="alert">Vui lòng điền đầy đủ thông tin bắt buộc.</div>';
        }
    }
}


get_header();

print_r($wpdb->last_error); // For debugging, remove in production
?>
<div class="content-wrapper mt-5">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-column">
                <!-- Add back button in the left side -->
                <a href="<?php echo $user_id ? home_url('/user-detail/?user_id=') . $user_id : home_url('/danh-sach-bao-tri/'); ?>" class="abs-top-left nav-link">
                    <i class="ph ph-arrow-bend-up-left btn-icon-prepend fa-150p"></i>
                </a>
                <div class="justify-content-center">
                    <h3 class="display-3">Thêm mới gói bảo trì</h3>
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
                    
                    <?php
                    if (isset($notification)) {
                        echo $notification;
                        // add more button to back to home page
                        echo '<div class="d-flex mt-3">';
                        echo '<a href="' . ($user_id ? home_url('/user-detail/?user_id=') . $user_id : home_url('/danh-sach-bao-tri/')) . '" class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-list btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Xem danh sách gói bảo trì</span>
                              </a>';
                        if (isset($maintenance_id)) {
                            echo '<a href="' . home_url('/maintenance/?maintenance_id=' . $maintenance_id) . '" class="btn btn-info btn-icon-text ms-2 d-flex align-items-center border-radius-9">
                                    <i class="ph ph-eye btn-icon-prepend fa-150p"></i>
                                    <span class="fw-bold">Xem chi tiết gói bảo trì</span>
                                  </a>';
                        }
                        echo '</div>';
                    } else {
                    ?>
                    <h3 class="card-title p-2 mb-3">Thông tin gói bảo trì</h3>
                    <form class="forms-sample col-md-8 col-lg-10 d-flex flex-column" action="" method="post">
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
                                        <?php if (!isset($user)): ?>
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Khách hàng <span class="text-danger">*</span></label>
                                            <select class="js-example-basic-single w-100" name="owner_user_id" required>
                                                <option value="">-- Chọn khách hàng --</option>
                                                <?php
                                                $users_table = $wpdb->prefix . 'im_users';
                                                $users = $wpdb->get_results("SELECT * FROM $users_table WHERE status = 'ACTIVE'");
                                                foreach ($users as $user_option) {
                                                    $user_label = $user_option->name;
                                                    if (!empty($user_option->company_name)) {
                                                        $user_label .= ' (' . $user_option->company_name . ')';
                                                    }
                                                    $selected = ($user_id && $user_option->id == $user_id) ? 'selected' : '';
                                                    echo '<option value="' . $user_option->id . '" ' . $selected . '>' . $user_option->user_code . ' - ' . $user_label . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <?php else: ?>
                                        <input type="hidden" name="owner_user_id" value="<?php echo $user->id; ?>">
                                        <?php endif; ?>
                                        
                                        <div class="form-group mb-3">
                                            <label for="order_code" class="fw-bold">Mã gói bảo trì</label>
                                            <input type="text" class="form-control" id="order_code" name="order_code" 
                                                   placeholder="Mã gói bảo trì (tự động nếu để trống)">
                                            <small class="form-text text-muted">Để trống để tự động tạo mã</small>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label for="renew_date" class="fw-bold">Ngày gia hạn <span class="text-danger">*</span></label>
                                            <div class="input-group date datepicker">
                                                <input type="text" class="form-control" id="renew_date" name="renew_date" 
                                                       placeholder="<?php echo date('d/m/Y'); ?>" required>
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
                                                    echo '<option value="' . $partner->id . '">' . $partner->user_code . ' - ' . $partner->name . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <small class="form-text text-muted">Chọn đối tác nếu có</small>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="status" class="fw-bold">Trạng thái <span class="text-danger">*</span></label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="ACTIVE">Đang hoạt động</option>
                                                <option value="PENDING">Chờ xử lý</option>
                                                <option value="EXPIRED">Hết hạn</option>
                                                <option value="CANCELLED">Đã hủy</option>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="notes" class="fw-bold">Ghi chú</label>
                                            <textarea class="form-control height-auto" id="notes" rows="3" placeholder="Ghi chú" name="notes"></textarea>
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
                                            <input type="number" step="1000" class="form-control" id="monthly_fee" name="monthly_fee" value="100000" required>
                                            <small class="form-text text-muted">Chi phí bảo trì hàng tháng</small>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="price_per_cycle_display" class="fw-bold">Giá mỗi chu kỳ (VNĐ)</label>
                                                    <input type="text" class="form-control" id="price_per_cycle_display" readonly>
                                                    <small class="form-text text-muted">Tự động tính: Phí hàng tháng × Chu kỳ thanh toán</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="billing_cycle_months" class="fw-bold">Chu kỳ thanh toán <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="billing_cycle_months" name="billing_cycle_months" required>
                                                        <option value="1" selected>1 tháng</option>
                                                        <option value="3">3 tháng</option>
                                                        <option value="6">6 tháng</option>
                                                        <option value="12">12 tháng</option>
                                                        <option value="24">24 tháng</option>
                                                        <option value="36">36 tháng</option>
                                                    </select>
                                                    <small class="form-text text-muted">Số tháng được tính tiền mỗi lần thanh toán</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="discount_amount" class="fw-bold">Chiết khấu (VNĐ)</label>
                                            <input type="number" step="1000" class="form-control" id="discount_amount" name="discount_amount" value="0">
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="actual_revenue_display" class="fw-bold">Doanh thu thực tế (VNĐ)</label>
                                            <input type="text" class="form-control" id="actual_revenue_display" readonly>
                                            <small class="form-text text-muted">Tự động tính: Giá chu kỳ - Chiết khấu</small>
                                        </div>

                                        <div class="alert alert-info">
                                            <h6><i class="ph ph-info me-2"></i>Thông tin tự động</h6>
                                            <div class="row">
                                                <div class="col-6">
                                                    <small><strong>Ngày hết hạn:</strong></small><br>
                                                    <small id="expiry_date_display">Chưa tính</small>
                                                </div>
                                                <div class="col-6">
                                                    <small><strong>Tình trạng:</strong></small><br>
                                                    <small id="status_display">Chưa xác định</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hidden field for total_months_registered, will be set equal to billing_cycle_months -->
                        <input type="hidden" id="total_months_registered" name="total_months_registered" value="1">
                        
                        <?php
                        wp_nonce_field('post_maintenance', 'post_maintenance_field');
                        ?>
                        <div class="form-group d-flex justify-content-center mt-3">
                            <button type="submit"
                                class="btn btn-primary btn-icon-text me-2 d-flex align-items-center border-radius-9">
                                <i class="ph ph-floppy-disk btn-icon-prepend fa-150p"></i>
                                <span class="fw-bold">Lưu gói bảo trì</span>
                            </button>
                            <a href="<?php echo $user_id ? home_url('/user-detail/?user_id=') . $user_id : home_url('/danh-sach-bao-tri/'); ?>" class="btn btn-light btn-icon-text ms-2 d-flex align-items-center border-radius-9">
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
// Maintenance package calculations
document.addEventListener('DOMContentLoaded', function() {
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
                
                const formattedExpiry = expiryDate.toLocaleDateString('vi-VN');
                expiryDateDisplay.textContent = formattedExpiry;

                // Determine status
                const today = new Date();
                const daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                
                if (daysUntilExpiry > 30) {
                    statusDisplay.textContent = 'Còn hiệu lực';
                    statusDisplay.className = 'text-success';
                } else if (daysUntilExpiry > 0) {
                    statusDisplay.textContent = 'Sắp hết hạn';
                    statusDisplay.className = 'text-warning';
                } else {
                    statusDisplay.textContent = 'Đã hết hạn';
                    statusDisplay.className = 'text-danger';
                }
            }
        } else {
            expiryDateDisplay.textContent = 'Chưa tính';
            statusDisplay.textContent = 'Chưa xác định';
            statusDisplay.className = '';
        }
    }

    // Add event listeners
    monthlyFeeInput.addEventListener('input', calculateValues);
    billingCycleSelect.addEventListener('change', calculateValues);
    discountInput.addEventListener('input', calculateValues);
    renewDateInput.addEventListener('input', calculateValues);

    // Initial calculation
    calculateValues();
    
    // Maintenance Code Preview Functionality
    const ownerSelect = document.querySelector('[name="owner_user_id"]');
    const orderCodeInput = document.getElementById('order_code');
    
    if (ownerSelect && orderCodeInput) {
        ownerSelect.addEventListener('change', function() {
            const selectedOwner = this.options[this.selectedIndex];
            const ownerText = selectedOwner.text;
            
            // Extract user code from owner option text (format: "CODE - Name")
            let userCode = '';
            if (ownerText && ownerText.includes(' - ')) {
                userCode = ownerText.split(' - ')[0];
            }
            
            // Remove existing preview
            const existingPreview = document.querySelector('.maintenance-code-preview');
            if (existingPreview) {
                existingPreview.remove();
            }
            
            if (userCode && orderCodeInput.value === '') {
                // Create preview for new maintenance package (number will be auto-generated)
                const previewCode = 'BT[N]' + userCode;
                const preview = document.createElement('div');
                preview.className = 'maintenance-code-preview text-info mt-1';
                preview.innerHTML = '<small>Mã sẽ được tạo: ' + previewCode + ' (N sẽ được tự động thay thế)</small>';
                orderCodeInput.parentNode.appendChild(preview);
            }
        });
    }
});
</script>

<?php
get_footer();
?>