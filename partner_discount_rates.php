<?php
/*
    Template Name: Partner Discount Rates Management
    Purpose: Manage partner commission discount rates by service type
*/

global $wpdb;

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('login'));
    exit;
}

// Only admin can access this page
if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

$rates_table = $wpdb->prefix . 'im_partner_discount_rates';
$users_table = $wpdb->prefix . 'im_users';

// Service types for commission
$service_types = array(
    'hosting' => 'Hosting',
    'maintenance' => 'Maintenance Package',
    'website_service' => 'Website Service'
);

get_header();
?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="ph ph-percent me-2"></i>Quản lý Tỷ lệ Chiết khấu Đối tác
                    </h4>
                    <button class="btn btn-light btn-sm" id="add-new-rate-btn">
                        <i class="ph ph-plus me-1"></i>Thêm mới
                    </button>
                </div>
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="partner-filter" class="form-label">Lọc theo đối tác</label>
                            <select id="partner-filter" class="form-select">
                                <option value="">-- Tất cả đối tác --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="service-type-filter" class="form-label">Loại dịch vụ</label>
                            <select id="service-type-filter" class="form-select">
                                <option value="">-- Tất cả loại dịch vụ --</option>
                                <?php foreach ($service_types as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Rates Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="discount-rates-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20%">Đối tác</th>
                                    <th style="width: 15%">Loại DV</th>
                                    <th style="width: 12%">Tỷ lệ (%)</th>
                                    <th style="width: 13%">Ngày áp dụng</th>
                                    <th style="width: 12%">Ngày kết thúc</th>
                                    <th style="width: 15%">Ghi chú</th>
                                    <th style="width: 13%">Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="rates-tbody">
                                <!-- Data loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>

                    <!-- No data message -->
                    <div id="no-rates-message" class="alert alert-info d-none">
                        <i class="ph ph-info me-2"></i>Không có dữ liệu tỷ lệ chiết khấu. Hãy thêm mới.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="rateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rateModalTitle">Thêm tỷ lệ chiết khấu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rate-form">
                    <input type="hidden" id="rate-id" name="rate_id" value="">

                    <div class="mb-3">
                        <label for="rate-partner-id" class="form-label">Đối tác <span class="text-danger">*</span></label>
                        <select id="rate-partner-id" name="partner_id" class="form-select" required>
                            <option value="">-- Chọn đối tác --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="rate-service-type" class="form-label">Loại dịch vụ <span class="text-danger">*</span></label>
                        <select id="rate-service-type" name="service_type" class="form-select" required>
                            <option value="">-- Chọn loại dịch vụ --</option>
                            <?php foreach ($service_types as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="rate-discount-rate" class="form-label">Tỷ lệ (%) <span class="text-danger">*</span></label>
                        <input type="number" id="rate-discount-rate" name="discount_rate" class="form-control" 
                               step="0.01" min="0" max="100" placeholder="0.00" required>
                        <small class="text-muted">Nhập từ 0 đến 100</small>
                    </div>

                    <div class="mb-3">
                        <label for="rate-effective-date" class="form-label">Ngày áp dụng <span class="text-danger">*</span></label>
                        <input type="date" id="rate-effective-date" name="effective_date" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="rate-end-date" class="form-label">Ngày kết thúc</label>
                        <input type="date" id="rate-end-date" name="end_date" class="form-control">
                        <small class="text-muted">Để trống nếu vẫn còn hiệu lực</small>
                    </div>

                    <div class="mb-3">
                        <label for="rate-notes" class="form-label">Ghi chú</label>
                        <textarea id="rate-notes" name="notes" class="form-control" rows="2" placeholder="Ghi chú về tỷ lệ này"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="save-rate-btn">
                    <i class="ph ph-save me-1"></i>Lưu
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteRateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Xóa tỷ lệ chiết khấu</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Bạn có chắc chắn muốn xóa tỷ lệ chiết khấu này không?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn">
                    <i class="ph ph-trash me-1"></i>Xóa
                </button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const AJAX_URL = '<?php echo admin_url('admin-ajax.php'); ?>';
    let rateToDelete = null;

    /**
     * Load partner discount rates
     */
    function loadDiscountRates(filters = {}) {
        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_partner_discount_rates',
                partner_id: filters.partner_id || $('#partner-filter').val() || '',
                service_type: filters.service_type || $('#service-type-filter').val() || ''
            },
            success: function(response) {
                if (response.success) {
                    displayRates(response.data);
                } else {
                    $('#rates-tbody').html('<tr><td colspan="7" class="text-center text-danger">Lỗi: ' + response.data.message + '</td></tr>');
                }
            },
            error: function() {
                $('#rates-tbody').html('<tr><td colspan="7" class="text-center text-danger">Lỗi kết nối!</td></tr>');
            }
        });
    }

    /**
     * Display rates in table
     */
    function displayRates(rates) {
        const tbody = $('#rates-tbody');
        tbody.empty();

        if (rates.length === 0) {
            $('#no-rates-message').removeClass('d-none');
            return;
        }

        $('#no-rates-message').addClass('d-none');

        rates.forEach(function(rate) {
            const endDate = rate.end_date && rate.end_date !== '0000-00-00' ? rate.end_date : '(Vô thời hạn)';
            const notes = rate.notes ? rate.notes.substring(0, 30) + '...' : '—';

            const row = $('<tr>').html(`
                <td><strong>${escapeHtml(rate.partner_name)}</strong></td>
                <td>${escapeHtml(getServiceTypeLabel('<?php echo json_encode($service_types); ?>', rate.service_type))}</td>
                <td><strong>${parseFloat(rate.discount_rate).toFixed(2)}%</strong></td>
                <td>${rate.effective_date}</td>
                <td>${endDate}</td>
                <td><small>${notes}</small></td>
                <td>
                    <button class="btn btn-sm btn-info edit-rate-btn" data-id="${rate.id}" title="Sửa">
                        <i class="ph ph-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-rate-btn" data-id="${rate.id}" title="Xóa">
                        <i class="ph ph-trash"></i>
                    </button>
                </td>
            `);

            tbody.append(row);
        });
    }

    /**
     * Load partners for select dropdown
     */
    function loadPartners() {
        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_all_partners'
            },
            success: function(response) {
                if (response.success) {
                    const partnerSelect = $('#rate-partner-id');
                    const filterSelect = $('#partner-filter');

                    partnerSelect.find('option:not(:first)').remove();
                    filterSelect.find('option:not(:first)').remove();

                    response.data.forEach(function(partner) {
                        partnerSelect.append(`<option value="${partner.id}">${escapeHtml(partner.name)} (${escapeHtml(partner.user_code)})</option>`);
                        filterSelect.append(`<option value="${partner.id}">${escapeHtml(partner.name)}</option>`);
                    });
                }
            }
        });
    }

    /**
     * Get service type label
     */
    function getServiceTypeLabel(serviceTypesJson, typeKey) {
        try {
            const types = JSON.parse(serviceTypesJson.replace(/&quot;/g, '"'));
            return types[typeKey] || typeKey;
        } catch (e) {
            return typeKey;
        }
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Open add/edit modal
     */
    $('#add-new-rate-btn').on('click', function() {
        $('#rate-form')[0].reset();
        $('#rate-id').val('');
        $('#rateModalTitle').text('Thêm tỷ lệ chiết khấu');
        // Use vanilla Bootstrap modal
        const modal = new bootstrap.Modal(document.getElementById('rateModal'));
        modal.show();
    });

    /**
     * Edit rate
     */
    $(document).on('click', '.edit-rate-btn', function() {
        const rateId = $(this).data('id');

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_partner_discount_rate',
                rate_id: rateId
            },
            success: function(response) {
                if (response.success) {
                    const rate = response.data;
                    $('#rate-id').val(rate.id);
                    $('#rate-partner-id').val(rate.partner_id);
                    $('#rate-service-type').val(rate.service_type);
                    $('#rate-discount-rate').val(parseFloat(rate.discount_rate).toFixed(2));
                    $('#rate-effective-date').val(rate.effective_date);
                    $('#rate-end-date').val(rate.end_date && rate.end_date !== '0000-00-00' ? rate.end_date : '');
                    $('#rate-notes').val(rate.notes || '');

                    $('#rateModalTitle').text('Sửa tỷ lệ chiết khấu');
                    // Use vanilla Bootstrap modal
                    const editModal = new bootstrap.Modal(document.getElementById('rateModal'));
                    editModal.show();
                } else {
                    alert('Lỗi: ' + response.data.message);
                }
            }
        });
    });

    /**
     * Save rate
     */
    $('#save-rate-btn').on('click', function() {
        const rateId = $('#rate-id').val();
        const action = rateId ? 'update_partner_discount_rate' : 'save_partner_discount_rate';

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: $.extend({}, $('#rate-form').serializeArray().reduce(function(obj, item) {
                obj[item.name] = item.value;
                return obj;
            }, {}), {
                action: action
            }),
            success: function(response) {
                if (response.success) {
                    // Hide modal using vanilla Bootstrap
                    const modal = bootstrap.Modal.getInstance(document.getElementById('rateModal'));
                    if (modal) modal.hide();
                    loadDiscountRates();
                    alert(response.data.message);
                } else {
                    alert('Lỗi: ' + response.data.message);
                }
            },
            error: function() {
                alert('Lỗi kết nối!');
            }
        });
    });

    /**
     * Delete rate
     */
    $(document).on('click', '.delete-rate-btn', function() {
        rateToDelete = $(this).data('id');
        // Use vanilla Bootstrap modal
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteRateModal'));
        deleteModal.show();
    });

    $('#confirm-delete-btn').on('click', function() {
        if (rateToDelete) {
            $.ajax({
                url: AJAX_URL,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'delete_partner_discount_rate',
                    rate_id: rateToDelete
                },
                success: function(response) {
                    if (response.success) {
                        // Hide delete modal
                        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteRateModal'));
                        if (deleteModal) deleteModal.hide();
                        loadDiscountRates();
                        alert(response.data.message);
                    } else {
                        alert('Lỗi: ' + response.data.message);
                    }
                }
            });
        }
    });

    /**
     * Filter change
     */
    $('#partner-filter, #service-type-filter').on('change', function() {
        loadDiscountRates();
    });

    // Initial load
    loadPartners();
    loadDiscountRates();
});
</script>

<?php get_footer(); ?>
