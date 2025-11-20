<?php
/*
    Template Name: VAT Invoice Settings
*/

global $wpdb;

// Get WordPress user
$wp_user = wp_get_current_user();
$wp_user_id = $wp_user->ID;

// Check if current user is WordPress admin
$is_admin = is_inova_admin();

if ($is_admin) {
    // WordPress Admin: Không cần tìm inova_user
    $is_admin_or_partner = true;
    $current_user = null;
    $current_user_id = null;
} else {
    // User or Partner: Tìm inova_user
    $current_user = get_inova_user();

    // Fallback: Nếu không tìm thấy inova_user qua get_inova_user(), thử query trực tiếp bằng WordPress user ID
    if (!$current_user) {
        $users_table = "{$wpdb->prefix}im_users";
        // Thử tìm user có id = WordPress user ID (fallback)
        $current_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$users_table` WHERE id = %d AND status = 'ACTIVE' LIMIT 1",
            $wp_user_id
        ));
    }

    if (!$current_user) {
        wp_die('Không tìm thấy thông tin người dùng. Vui lòng liên hệ administrator.');
    }

    $current_user_id = $current_user->id;
    $is_admin_or_partner = in_array($current_user->user_type, ['ADMIN', 'PARTNER']);
}

get_header();
// print_r($wp_user);
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">
                            <i class="ph ph-file-text me-2"></i>Cấu hình Hóa đơn đỏ (VAT)
                        </h4>
                    </div>

                    <div class="alert alert-info">
                        <i class="ph ph-info me-2"></i>
                        <strong>Hóa đơn đỏ là gì?</strong><br>
                        Nếu bạn là doanh nghiệp và cần hóa đơn VAT (hóa đơn đỏ) cho mục đích kế toán, vui lòng bật tính năng này và điền đầy đủ thông tin công ty.
                    </div>

                    <?php if ($is_admin_or_partner): ?>
                        <!-- ADMIN/PARTNER VIEW: User Management -->
                        <div class="mb-4">
                            <div class="input-group">
                                <input type="text" class="form-control" id="search-user-vat" placeholder="Tìm kiếm theo tên, mã, email hoặc tên công ty...">
                                <button class="btn btn-outline-secondary" type="button" id="btn-search-vat">
                                    <i class="ph ph-magnifying-glass"></i>
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="vat-invoice-table">
                                <thead>
                                    <tr class="bg-light">
                                        <th style="width: 80px;">Mã người dùng</th>
                                        <th>Tên người dùng</th>
                                        <th>Email</th>
                                        <th>Tên công ty</th>
                                        <th>Mã số thuế</th>
                                        <th style="width: 120px;">Trạng thái</th>
                                        <th style="width: 150px;">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody id="vat-invoice-list">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="ph ph-hourglass"></i> Đang tải...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav aria-label="Page navigation" id="vat-pagination" style="display: none;">
                            <ul class="pagination justify-content-center">
                                <li class="page-item">
                                    <button class="page-link" id="prev-page" type="button">Trước</button>
                                </li>
                                <li class="page-item active">
                                    <span class="page-link" id="current-page">1</span>
                                </li>
                                <li class="page-item">
                                    <button class="page-link" id="next-page" type="button">Sau</button>
                                </li>
                            </ul>
                        </nav>

                    <?php else: ?>
                        <!-- USER VIEW: Personal Settings -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="toggle-vat-invoice"
                                           <?php echo ($current_user->requires_vat_invoice) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="toggle-vat-invoice">
                                        Tôi muốn nhận hóa đơn đỏ (VAT)
                                    </label>
                                </div>

                                <div id="vat-invoice-form" style="display: <?php echo ($current_user->requires_vat_invoice) ? 'block' : 'none'; ?>;">
                                    <div class="card border-warning">
                                        <div class="card-body">
                                            <p class="text-muted mb-4">
                                                <i class="ph ph-warning me-2"></i>
                                                Vui lòng điền đầy đủ thông tin công ty để chúng tôi xuất hóa đơn VAT chính xác.
                                            </p>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="company_name" class="form-label">Tên công ty</label>
                                                    <input type="text"
                                                           class="form-control vat-invoice-field"
                                                           id="company_name"
                                                           name="company_name"
                                                           value="<?php echo esc_attr($current_user->company_name); ?>"
                                                           placeholder="Nhập tên công ty">
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="tax_code" class="form-label">Mã số thuế</label>
                                                    <input type="text"
                                                           class="form-control vat-invoice-field"
                                                           id="tax_code"
                                                           name="tax_code"
                                                           value="<?php echo esc_attr($current_user->tax_code); ?>"
                                                           placeholder="Nhập mã số thuế">
                                                </div>

                                                <div class="col-md-12 mb-3">
                                                    <label for="company_address" class="form-label">Địa chỉ công ty</label>
                                                    <textarea class="form-control vat-invoice-field"
                                                              id="company_address"
                                                              name="company_address"
                                                              rows="2"
                                                              placeholder="Nhập địa chỉ công ty"><?php echo esc_textarea($current_user->company_address); ?></textarea>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="invoice_email" class="form-label">Email nhận hóa đơn</label>
                                                    <input type="email"
                                                           class="form-control vat-invoice-field"
                                                           id="invoice_email"
                                                           name="invoice_email"
                                                           value="<?php echo esc_attr($current_user->invoice_email); ?>"
                                                           placeholder="Nhập email nhận hóa đơn">
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="invoice_phone" class="form-label">Số điện thoại liên hệ</label>
                                                    <input type="text"
                                                           class="form-control vat-invoice-field"
                                                           id="invoice_phone"
                                                           name="invoice_phone"
                                                           value="<?php echo esc_attr($current_user->invoice_phone); ?>"
                                                           placeholder="Nhập số điện thoại">
                                                </div>
                                            </div>

                                            <button type="button" class="btn btn-primary mt-3" id="save-vat-invoice-btn">
                                                <i class="ph ph-floppy-disk me-2"></i>Lưu thông tin hóa đơn
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

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
    </div>
</div>

<?php if ($is_admin_or_partner): ?>
<!-- Modal for editing VAT invoice -->
<div class="modal fade" id="edit-vat-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-modal-title">Sửa thông tin hóa đơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-user-id">

                <div class="mb-3">
                    <label for="modal-company-name" class="form-label">Tên công ty</label>
                    <input type="text" class="form-control" id="modal-company-name" placeholder="Tên công ty">
                </div>

                <div class="mb-3">
                    <label for="modal-tax-code" class="form-label">Mã số thuế</label>
                    <input type="text" class="form-control" id="modal-tax-code" placeholder="Mã số thuế">
                </div>

                <div class="mb-3">
                    <label for="modal-company-address" class="form-label">Địa chỉ công ty</label>
                    <textarea class="form-control" id="modal-company-address" rows="2" placeholder="Địa chỉ công ty"></textarea>
                </div>

                <div class="mb-3">
                    <label for="modal-invoice-email" class="form-label">Email nhận hóa đơn</label>
                    <input type="email" class="form-control" id="modal-invoice-email" placeholder="Email">
                </div>

                <div class="mb-3">
                    <label for="modal-invoice-phone" class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control" id="modal-invoice-phone" placeholder="Số điện thoại">
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="modal-requires-vat" checked>
                    <label class="form-check-label" for="modal-requires-vat">
                        Yêu cầu hóa đơn VAT
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="save-modal-vat-btn">
                    <i class="ph ph-floppy-disk me-2"></i>Lưu
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
    <?php if ($is_admin_or_partner): ?>
        /**
         * Admin/Partner View: Load VAT invoice list
         * Files using this: vat_invoice_settings.php (admin/partner only)
         */
        let isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        let currentPage = 1;
        let currentSearch = '';

        function loadVatInvoiceList(page = 1, search = '') {
            currentPage = page;
            currentSearch = search;

            $.ajax({
                url: AJAX.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'get_vat_invoice_list',
                    page: page,
                    per_page: 10,
                    search: search,
                    is_admin: isAdmin ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        var users = response.data.data;
                        var pagination = response.data.pagination;

                        // Build table rows
                        var html = '';
                        if (users.length === 0) {
                            html = '<tr><td colspan="7" class="text-center text-muted py-4">Không có dữ liệu</td></tr>';
                        } else {
                            users.forEach(function(user) {
                                var status = user.requires_vat_invoice ?
                                    '<span class="badge bg-success">Đang dùng</span>' :
                                    '<span class="badge bg-secondary">Chưa kích hoạt</span>';

                                html += '<tr>';
                                html += '<td><strong>' + escapeHtml(user.user_code) + '</strong></td>';
                                html += '<td>' + escapeHtml(user.name) + '<br><small class="text-muted">' + escapeHtml(user.email) + '</small></td>';
                                html += '<td>' + escapeHtml(user.email) + '</td>';
                                html += '<td>' + escapeHtml(user.company_name || '-') + '</td>';
                                html += '<td>' + escapeHtml(user.tax_code || '-') + '</td>';
                                html += '<td>' + status + '</td>';
                                html += '<td>';
                                html += '<button class="btn btn-sm btn-warning edit-user-vat-btn me-2" data-user-id="' + user.id + '" data-user-name="' + escapeHtml(user.name) + '">';
                                html += '<i class="ph ph-pencil-simple"></i>';
                                html += '</button>';
                                html += '<button class="btn btn-sm btn-danger delete-user-vat-btn" data-user-id="' + user.id + '" data-user-name="' + escapeHtml(user.name) + '">';
                                html += '<i class="ph ph-trash"></i>';
                                html += '</button>';
                                html += '</td>';
                                html += '</tr>';
                            });
                        }

                        $('#vat-invoice-list').html(html);

                        // Update pagination
                        updatePagination(pagination);
                    } else {
                        showError(response.data.message || 'Có lỗi xảy ra');
                    }
                },
                error: function() {
                    showError('Lỗi kết nối với server');
                }
            });
        }

        function updatePagination(pagination) {
            if (pagination.total_pages <= 1) {
                $('#vat-pagination').hide();
                return;
            }

            $('#current-page').text(pagination.page);
            $('#prev-page').prop('disabled', pagination.page === 1);
            $('#next-page').prop('disabled', pagination.page >= pagination.total_pages);
            $('#vat-pagination').show();
        }

        function escapeHtml(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function showError(message) {
            $('#error-text').text(message);
            $('#error-message').fadeIn().delay(4000).fadeOut();
        }

        function showSuccess(message) {
            $('#success-text').text(message);
            $('#success-message').fadeIn().delay(3000).fadeOut();
        }

        // Load list on page load
        loadVatInvoiceList(1, '');

        // Search button
        $('#btn-search-vat').on('click', function() {
            var search = $('#search-user-vat').val();
            loadVatInvoiceList(1, search);
        });

        // Search on Enter key
        $('#search-user-vat').on('keypress', function(e) {
            if (e.which === 13) {
                $('#btn-search-vat').click();
                return false;
            }
        });

        // Pagination
        $('#prev-page').on('click', function() {
            if (currentPage > 1) {
                loadVatInvoiceList(currentPage - 1, currentSearch);
            }
        });

        $('#next-page').on('click', function() {
            loadVatInvoiceList(currentPage + 1, currentSearch);
        });

        // Edit user VAT
        $(document).on('click', '.edit-user-vat-btn', function() {
            var userId = $(this).data('user-id');
            var userName = $(this).data('user-name');

            // You would fetch user data here, for now we'll just open the modal
            $('#modal-user-id').val(userId);
            $('#edit-modal-title').text('Sửa thông tin hóa đơn: ' + userName);

            // Fetch user data and populate form
            // This would require another AJAX call to get full user details
            var $row = $(this).closest('tr');
            var cells = $row.find('td');

            // Pre-fill basic info from table
            // You might want to fetch full details from server

            var editModal = new bootstrap.Modal(document.getElementById('edit-vat-modal'));
            editModal.show();
        });

        // Save modal VAT
        $('#save-modal-vat-btn').on('click', function() {
            var $btn = $(this);
            var userId = $('#modal-user-id').val();
            var companyName = $('#modal-company-name').val().trim();
            var taxCode = $('#modal-tax-code').val().trim();
            var companyAddress = $('#modal-company-address').val().trim();
            var invoiceEmail = $('#modal-invoice-email').val().trim();
            var invoicePhone = $('#modal-invoice-phone').val().trim();
            var requiresVat = $('#modal-requires-vat').is(':checked') ? 1 : 0;

            if (!companyName) {
                showError('Vui lòng nhập tên công ty');
                return;
            }

            $btn.prop('disabled', true);
            $btn.find('i').removeClass('ph-floppy-disk').addClass('ph-spinner ph-spin');

            $.ajax({
                url: AJAX.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'save_vat_invoice_for_user',
                    user_id: userId,
                    requires_vat_invoice: requiresVat,
                    company_name: companyName,
                    tax_code: taxCode,
                    company_address: companyAddress,
                    invoice_email: invoiceEmail,
                    invoice_phone: invoicePhone
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.data.message);
                        bootstrap.Modal.getInstance(document.getElementById('edit-vat-modal')).hide();
                        loadVatInvoiceList(currentPage, currentSearch);
                    } else {
                        showError(response.data.message || 'Có lỗi xảy ra');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.find('i').removeClass('ph-spinner ph-spin').addClass('ph-floppy-disk');
                }
            });
        });

        // Delete user VAT
        $(document).on('click', '.delete-user-vat-btn', function() {
            var userId = $(this).data('user-id');
            var userName = $(this).data('user-name');

            if (confirm('Bạn có chắc muốn xóa thông tin hóa đơn của ' + userName + '?')) {
                var $btn = $(this);
                var $icon = $btn.find('i');

                $btn.prop('disabled', true);
                $icon.removeClass('ph-trash').addClass('ph-spinner ph-spin');

                $.ajax({
                    url: AJAX.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_vat_invoice',
                        user_id: userId
                    },
                    success: function(response) {
                        if (response.success) {
                            showSuccess(response.data.message);
                            loadVatInvoiceList(currentPage, currentSearch);
                        } else {
                            showError(response.data.message || 'Có lỗi xảy ra');
                            $btn.prop('disabled', false);
                            $icon.removeClass('ph-spinner ph-spin').addClass('ph-trash');
                        }
                    },
                    error: function() {
                        showError('Lỗi kết nối');
                        $btn.prop('disabled', false);
                        $icon.removeClass('ph-spinner ph-spin').addClass('ph-trash');
                    }
                });
            }
        });

    <?php else: ?>
        /**
         * User View: Personal VAT invoice settings
         * Files using this: vat_invoice_settings.php (user only)
         */
        function showError(message) {
            $('#error-text').text(message);
            $('#error-message').fadeIn().delay(4000).fadeOut();
        }

        function showSuccess(message) {
            $('#success-text').text(message);
            $('#success-message').fadeIn().delay(3000).fadeOut();
        }

        // Toggle VAT invoice form visibility
        $('#toggle-vat-invoice').on('change', function() {
            var isChecked = $(this).is(':checked');
            if (isChecked) {
                $('#vat-invoice-form').slideDown();
            } else {
                $('#vat-invoice-form').slideUp();
            }
        });

        // Save VAT invoice settings
        $('#save-vat-invoice-btn').on('click', function() {
            var $btn = $(this);
            var $icon = $btn.find('i');

            // Get form values
            var requiresVatInvoice = $('#toggle-vat-invoice').is(':checked') ? 1 : 0;
            var companyName = $('#company_name').val().trim();
            var taxCode = $('#tax_code').val().trim();
            var companyAddress = $('#company_address').val().trim();
            var invoiceEmail = $('#invoice_email').val().trim();
            var invoicePhone = $('#invoice_phone').val().trim();

            // Validation
            if (requiresVatInvoice && !companyName) {
                showError('Vui lòng nhập tên công ty');
                return;
            }

            if (requiresVatInvoice && !taxCode) {
                showError('Vui lòng nhập mã số thuế');
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
                    action: 'update_vat_invoice_setting',
                    requires_vat_invoice: requiresVatInvoice,
                    company_name: companyName,
                    tax_code: taxCode,
                    company_address: companyAddress,
                    invoice_email: invoiceEmail,
                    invoice_phone: invoicePhone
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess('Đã lưu cấu hình hóa đơn đỏ thành công!');
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
    <?php endif; ?>
});
</script>

<?php
get_footer();
?>
