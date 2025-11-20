<?php
global $wpdb;

// Get WordPress user
$wp_user = wp_get_current_user();
$wp_user_id = $wp_user->ID;

// Get Inova user - first try via meta link
$current_wp_user = get_inova_user($wp_user_id);

if (!$current_wp_user) {
    wp_die('Không tìm thấy thông tin người dùng Inova. Vui lòng liên hệ administrator để liên kết tài khoản.');
}

$current_wp_user_id = $current_wp_user->id;

// Handle delete contact action - BEFORE get_header()
$contact_action = isset($_GET['contact_action']) ? $_GET['contact_action'] : '';
$contacts_table = $wpdb->prefix . 'im_contacts';

if ($contact_action == 'delete_contact') {
    // Verify nonce
    check_admin_referer('delete_contact_' . $_GET['contact_id'], 'delete_contact_nonce');
    
    $contact_id = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : 0;
    if ($contact_id > 0) {
        // Verify contact belongs to current user before deleting
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $contacts_table WHERE id = %d AND user_id = %d",
            $contact_id,
            $current_wp_user_id
        ));
        
        if ($contact) {
            $wpdb->delete($contacts_table, array('id' => $contact_id));
            wp_redirect(home_url('/my-vat-invoice-settings/'));
            exit;
        }
    }
}

get_header();

// Get all contacts for this user
$all_contacts = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $contacts_table WHERE user_id = %d ORDER BY is_primary DESC, full_name ASC",
    $current_wp_user_id
));

?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">
                            <i class="ph ph-file-text me-2"></i>Cấu hình Hóa đơn đỏ (VAT)
                        </h4>
                        <button type="button" class="d-flex align-items-center btn btn-warning btn-sm" id="edit-vat-btn">
                            <i class="ph ph-pencil-simple me-2"></i>Sửa thông tin
                        </button>
                    </div>

                    <div class="alert alert-info">
                        <i class="ph ph-info me-2"></i>
                        <strong>Hóa đơn đỏ là gì?</strong><br>
                        Đây là thông tin hóa đơn VAT của bạn. Thông tin này được sử dụng khi phát hành hóa đơn VAT cho các dịch vụ của bạn.
                    </div>

                    <div id="vat-info-display">
                        <table class="table table-borderless w-auto">
                            <tbody>
                                <tr>
                                    <td class="text-muted small" style="padding-right: 30px;">Tên công ty</td>
                                    <td class="fw-bold" id="display-company-name">
                                        <?php echo $current_wp_user->company_name ? esc_html($current_wp_user->company_name) : '<span class="text-muted">Chưa cập nhật</span>'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted small" style="padding-right: 30px;">Mã số thuế</td>
                                    <td class="fw-bold" id="display-tax-code">
                                        <?php echo $current_wp_user->tax_code ? esc_html($current_wp_user->tax_code) : '<span class="text-muted">Chưa cập nhật</span>'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted small" style="padding-right: 30px;">Email</td>
                                    <td class="fw-bold" id="display-invoice-email">
                                        <?php echo $current_wp_user->invoice_email ? esc_html($current_wp_user->invoice_email) : '<span class="text-muted">Chưa cập nhật</span>'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted small" style="padding-right: 30px;">Số điện thoại</td>
                                    <td class="fw-bold" id="display-invoice-phone">
                                        <?php echo $current_wp_user->invoice_phone ? esc_html($current_wp_user->invoice_phone) : '<span class="text-muted">Chưa cập nhật</span>'; ?>
                                    </td>
                                </tr>
                                <?php if ($current_wp_user->notes): ?>
                                <tr>
                                    <td class="text-muted small" style="padding-right: 30px;">Ghi chú (Email CC khác)</td>
                                    <td class="fw-bold" id="display-notes">
                                        <?php echo esc_html($current_wp_user->notes); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-success mt-4" id="success-message" style="display: none;">
                        <i class="ph ph-check-circle me-2"></i>
                        <span id="success-text"></span>
                    </div>

                    <div class="alert alert-danger mt-4" id="error-message" style="display: none;">
                        <i class="ph ph-warning me-2"></i>
                        <span id="error-text"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information Column -->
        <div class="col-lg-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">
                            <i class="ph ph-user-circle me-2"></i>Thông tin liên hệ
                        </h4>
                        <a href="<?php echo home_url('/them-lien-he-moi/?user_id=' . $current_wp_user_id); ?>" class="d-flex align-items-center btn btn-secondary btn-sm">
                            <i class="ph ph-user-plus me-2"></i>Thêm liên hệ
                        </a>
                    </div>

                    <?php if (!empty($all_contacts)): ?>
                    <div class="d-flex flex-wrap gap-3" style="justify-content: flex-start;">
                        <?php foreach ($all_contacts as $contact): ?>
                        <div class="card card-rounded p-3" style="border: 1px solid #e9ecef; flex: 0 0 calc(33.333% - 8px); min-width: 200px;">
                            <div class="d-flex justify-content-center flex-column text-center">
                                <?php if ($contact->is_primary): ?>
                                <i class="d-flex fit-content badge border-radius-9 btn-inverse-success justify-content-center mb-2" style="width: fit-content; position: absolute; top: 5px; left: 5px;">Liên hệ chính</i>
                                <?php endif; ?>
                                <i class="ph ph-user-circle icon-lg p-4 mt-4"></i>
                                <span class="fw-bold p-2"><?php echo esc_html($contact->full_name); ?></span>
                                <?php if (!empty($contact->position)): ?>
                                <span class="text-primary"><?php echo esc_html($contact->position); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($contact->email)): ?>
                                <span><a href="mailto:<?php echo esc_attr($contact->email); ?>"><?php echo esc_html($contact->email); ?></a></span>
                                <?php endif; ?>
                                <?php if (!empty($contact->phone_number)): ?>
                                <span><a href="tel:<?php echo esc_attr($contact->phone_number); ?>"><?php echo esc_html($contact->phone_number); ?></a></span>
                                <?php endif; ?>
                                
                                <div class="mt-3 d-flex justify-content-center gap-2">
                                    <a href="<?php echo home_url('/sua-lien-he/?id=' . $contact->id); ?>" class="btn btn-sm btn-dark btn-icon-text d-flex align-items-center">
                                        <i class="ph ph-pencil-simple btn-icon-prepend"></i>
                                        Sửa
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger btn-icon-text d-flex align-items-center delete-contact-btn" data-contact-id="<?php echo $contact->id; ?>" data-contact-name="<?php echo esc_attr($contact->full_name); ?>">
                                         <i class="ph ph-trash btn-icon-prepend"></i>
                                         Xóa
                                     </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="ph ph-info me-2"></i>
                        Chưa có liên hệ nào được thiết lập
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for editing VAT invoice -->
<div class="modal fade" id="edit-vat-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cập nhật thông tin hóa đơn đỏ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="modal-company-name" class="form-label">Tên công ty <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="modal-company-name" placeholder="Tên công ty">
                </div>

                <div class="mb-3">
                    <label for="modal-tax-code" class="form-label">Mã số thuế <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="modal-tax-code" placeholder="Mã số thuế">
                </div>

                <div class="mb-3">
                    <label for="modal-invoice-email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="modal-invoice-email" placeholder="Email">
                </div>

                <div class="mb-3">
                    <label for="modal-invoice-phone" class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control" id="modal-invoice-phone" placeholder="Số điện thoại">
                </div>

                <div class="mb-3">
                    <label for="modal-notes" class="form-label">Ghi chú (Email CC khác)</label>
                    <textarea class="form-control" id="modal-notes" rows="3" placeholder="Nhập email CC hoặc ghi chú khác"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="save-vat-btn">
                    <i class="ph ph-floppy-disk me-2"></i>Lưu thông tin
                </button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    /**
     * User VAT Invoice Settings - Simple View
     * Files using this: my_vat_invoice_settings.php (user only)
     */

    // Load current data and open modal for editing
    $('#edit-vat-btn').on('click', function() {
        // Pre-fill with current data
        $('#modal-company-name').val('<?php echo esc_attr($current_wp_user->company_name); ?>');
        $('#modal-tax-code').val('<?php echo esc_attr($current_wp_user->tax_code); ?>');
        $('#modal-invoice-email').val('<?php echo esc_attr($current_wp_user->invoice_email); ?>');
        $('#modal-invoice-phone').val('<?php echo esc_attr($current_wp_user->invoice_phone); ?>');
        $('#modal-notes').val('<?php echo esc_textarea($current_wp_user->notes); ?>');

        var editModal = new bootstrap.Modal(document.getElementById('edit-vat-modal'));
        editModal.show();
    });

    // Save VAT invoice settings
    $('#save-vat-btn').on('click', function() {
        var $btn = $(this);
        var $icon = $btn.find('i');

        // Get form values
        var companyName = $('#modal-company-name').val().trim();
        var taxCode = $('#modal-tax-code').val().trim();
        var invoiceEmail = $('#modal-invoice-email').val().trim();
        var invoicePhone = $('#modal-invoice-phone').val().trim();
        var notes = $('#modal-notes').val().trim();

        // Validation
        if (!companyName) {
            showError('Vui lòng nhập tên công ty');
            return;
        }

        if (!taxCode) {
            showError('Vui lòng nhập mã số thuế');
            return;
        }

        if (!invoiceEmail) {
            showError('Vui lòng nhập email');
            return;
        }

        // Disable button and show loading
        $btn.prop('disabled', true);
        $icon.removeClass('ph-floppy-disk').addClass('ph-spinner ph-spin');

        $.ajax({
            url: AJAX.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'update_my_vat_invoice_setting',
                company_name: companyName,
                tax_code: taxCode,
                invoice_email: invoiceEmail,
                invoice_phone: invoicePhone,
                notes: notes
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Đã lưu thông tin hóa đơn đỏ thành công!');
                    
                    // Update display
                    $('#display-company-name').text(companyName);
                    $('#display-tax-code').text(taxCode);
                    $('#display-invoice-email').text(invoiceEmail);
                    $('#display-invoice-phone').text(invoicePhone || '-');
                    $('#display-notes').text(notes || '-');
                    
                    // Close modal
                    setTimeout(function() {
                        bootstrap.Modal.getInstance(document.getElementById('edit-vat-modal')).hide();
                    }, 1500);
                } else {
                    showError(response.data?.message || 'Có lỗi xảy ra');
                }
            },
            error: function() {
                showError('Có lỗi xảy ra khi lưu cài đặt.');
            },
            complete: function() {
                // Re-enable button and restore icon
                $btn.prop('disabled', false);
                $icon.removeClass('ph-spinner ph-spin').addClass('ph-floppy-disk');
            }
        });
    });

    function showError(message) {
        $('#error-text').text(message);
        $('#error-message').fadeIn().delay(4000).fadeOut();
    }

    function showSuccess(message) {
        $('#success-text').text(message);
        $('#success-message').fadeIn().delay(3000).fadeOut();
    }

    /**
     * Delete contact via AJAX
     */
    $(document).on('click', '.delete-contact-btn', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var contactId = $btn.data('contact-id');
        var contactName = $btn.data('contact-name');

        if (!confirm('Bạn có chắc chắn muốn xóa liên hệ "' + contactName + '"?')) {
            return;
        }

        var $icon = $btn.find('i');

        // Disable button and show loading
        $btn.prop('disabled', true);
        $icon.removeClass('ph-trash').addClass('ph-spinner ph-spin');

        $.ajax({
            url: AJAX.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_contact',
                contact_id: contactId
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Đã xóa liên hệ thành công!');
                    
                    // Remove contact card after delay
                    setTimeout(function() {
                        $btn.closest('.card').fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 1000);
                } else {
                    showError(response.data?.message || 'Có lỗi xảy ra khi xóa liên hệ');
                }
            },
            error: function() {
                showError('Có lỗi xảy ra khi xóa liên hệ');
            },
            complete: function() {
                // Re-enable button and restore icon
                $btn.prop('disabled', false);
                $icon.removeClass('ph-spinner ph-spin').addClass('ph-trash');
            }
        });
    });
});
</script>

<?php
get_footer();
?>
