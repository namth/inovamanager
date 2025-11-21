<?php
/*
    Template Name: Partner Commissions Management
    Purpose: Manage partner commissions from services - Admin and Partner view
*/

global $wpdb;

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('login'));
    exit;
}

$commissions_table = $wpdb->prefix . 'im_partner_commissions';
$users_table = $wpdb->prefix . 'im_users';
$invoices_table = $wpdb->prefix . 'im_invoices';

// Service types for commission
$service_types = array(
    'hosting' => 'Hosting',
    'maintenance' => 'Maintenance Package',
    'website_service' => 'Website Service'
);

// Commission statuses
$statuses = array(
    'PENDING' => 'Chưa thanh toán',
    'PAID' => 'Đã thanh toán',
    'WITHDRAWN' => 'Đã rút tiền',
    'CANCELLED' => 'Bị hủy'
);

// Check user role
$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_options');
$is_partner = false;
$partner_id = null;

if (!$is_admin) {
    // Check if user is a partner - get_inova_user returns object with id and user_type
    $inova_user = get_inova_user($current_user->ID);
    
    if ($inova_user && isset($inova_user->id) && $inova_user->user_type === 'PARTNER' && $inova_user->status === 'ACTIVE') {
        $partner_id = $inova_user->id;
        $is_partner = true;
    } else {
        wp_die('Access denied - You are not authorized to view this page');
    }
}

get_header();
?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="ph ph-wallet me-2"></i>Quản lý Hoa hồng Đối tác
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Summary Cards (Admin Only) -->
                    <?php if ($is_admin): ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light-warning border-0">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-2">Chưa thanh toán</h6>
                                    <h3 id="total-pending" class="text-warning mb-0">0 VNĐ</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light-success border-0">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-2">Đã thanh toán</h6>
                                    <h3 id="total-paid" class="text-success mb-0">0 VNĐ</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light-info border-0">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-2">Đã rút tiền</h6>
                                    <h3 id="total-withdrawn" class="text-info mb-0">0 VNĐ</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light-primary border-0">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-2">Tổng cộng</h6>
                                    <h3 id="total-all" class="text-primary mb-0">0 VNĐ</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Filter Section (Admin Only) -->
                    <?php if ($is_admin): ?>
                    <div class="row mb-4 g-3">
                        <div class="col-md-3">
                            <label for="partner-filter" class="form-label">Lọc theo đối tác</label>
                            <select id="partner-filter" class="form-select">
                                <option value="">-- Tất cả đối tác --</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="service-type-filter" class="form-label">Loại dịch vụ</label>
                            <select id="service-type-filter" class="form-select">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($service_types as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status-filter" class="form-label">Trạng thái</label>
                            <select id="status-filter" class="form-select">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($statuses as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="from-date" class="form-label">Từ ngày</label>
                            <input type="date" id="from-date" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label for="to-date" class="form-label">Đến ngày</label>
                            <input type="date" id="to-date" class="form-control">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button class="btn btn-secondary btn-sm w-100" id="reset-filters-btn">
                                <i class="ph ph-funnel-x"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Bulk Actions (Admin Only) -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-warning d-none" id="bulk-actions-container">
                                    <input type="checkbox" id="select-all-checkbox" class="form-check-input me-2" title="Chọn tất cả">
                                    <span id="selected-count">0 được chọn</span>
                                </button>
                                <select id="bulk-status-action" class="form-select form-select-sm" style="width: 200px;">
                                    <option value="">-- Cập nhật trạng thái --</option>
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-primary" id="bulk-update-btn">
                                    <i class="ph ph-arrow-circle-right me-1"></i>Cập nhật
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Commissions Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="commissions-table">
                            <thead class="table-light">
                                <tr>
                                    <?php if ($is_admin): ?>
                                    <th style="width: 3%">
                                        <input type="checkbox" class="form-check-input" id="header-checkbox">
                                    </th>
                                    <?php endif; ?>
                                    <th style="width: 10%">Ngày</th>
                                    <?php if ($is_admin): ?>
                                    <th style="width: 12%">Đối tác</th>
                                    <?php endif; ?>
                                    <th style="width: 10%">Loại DV</th>
                                    <th style="width: 10%">Tỷ lệ (%)</th>
                                    <th style="width: 12%">Số tiền DV</th>
                                    <th style="width: 12%">Hoa hồng</th>
                                    <th style="width: 10%">Trạng thái</th>
                                    <th style="width: 11%">Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="commissions-tbody">
                                <!-- Data loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>

                    <!-- No data message -->
                    <div id="no-commissions-message" class="alert alert-info d-none">
                        <i class="ph ph-info me-2"></i>Không có dữ liệu hoa hồng. Hãy tạo hóa đơn để tạo hoa hồng.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Commission Detail Modal -->
<div class="modal fade" id="commissionDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết Hoa hồng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="commission-detail-body">
                <!-- Detail content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal (Admin Only) -->
<?php if ($is_admin): ?>
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cập nhật Trạng thái Hoa hồng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="status-select" class="form-label">Trạng thái mới</label>
                    <select id="status-select" class="form-select">
                        <option value="">-- Chọn trạng thái --</option>
                        <?php foreach ($statuses as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="confirm-status-btn">
                    <i class="ph ph-check me-1"></i>Cập nhật
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
    const AJAX_URL = '<?php echo admin_url('admin-ajax.php'); ?>';
    const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const PARTNER_ID = <?php echo $partner_id ? $partner_id : 'null'; ?>;
    let commissionToUpdate = null;

    /**
     * Load commissions
     */
    function loadCommissions(filters = {}) {
        const data = {
            action: 'get_partner_commissions',
            partner_id: IS_ADMIN ? (filters.partner_id || $('#partner-filter').val() || '') : PARTNER_ID,
            service_type: filters.service_type || $('#service-type-filter').val() || '',
            status: filters.status || $('#status-filter').val() || '',
            from_date: filters.from_date || $('#from-date').val() || '',
            to_date: filters.to_date || $('#to-date').val() || ''
        };

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function(response) {
                if (response.success) {
                    displayCommissions(response.data);
                    if (IS_ADMIN) {
                        updateSummaryCards(response.data);
                    }
                } else {
                    $('#commissions-tbody').html('<tr><td colspan="9" class="text-center text-danger">Lỗi: ' + response.data.message + '</td></tr>');
                }
            },
            error: function() {
                $('#commissions-tbody').html('<tr><td colspan="9" class="text-center text-danger">Lỗi kết nối!</td></tr>');
            }
        });
    }

    /**
     * Display commissions in table
     */
    function displayCommissions(commissions) {
        const tbody = $('#commissions-tbody');
        tbody.empty();

        if (commissions.length === 0) {
            $('#no-commissions-message').removeClass('d-none');
            return;
        }

        $('#no-commissions-message').addClass('d-none');

        commissions.forEach(function(commission) {
            let row = '<tr>';

            if (IS_ADMIN) {
                row += `<td><input type="checkbox" class="form-check-input commission-checkbox" data-id="${commission.id}"></td>`;
            }

            row += `
                <td>${commission.calculation_date}</td>
            `;

            if (IS_ADMIN) {
                row += `<td><strong>${escapeHtml(commission.partner_name)}</strong></td>`;
            }

            const statusClass = getStatusClass(commission.status);
            row += `
                <td>${escapeHtml(getServiceTypeLabel(commission.service_type))}</td>
                <td>${parseFloat(commission.discount_rate).toFixed(2)}%</td>
                <td>${formatMoney(commission.service_amount)}</td>
                <td><strong>${formatMoney(commission.commission_amount)}</strong></td>
                <td><span class="badge bg-${statusClass}">${escapeHtml(getStatusLabel(commission.status))}</span></td>
                <td>
                    <button class="btn btn-sm btn-info view-commission-btn" data-id="${commission.id}" title="Xem chi tiết">
                        <i class="ph ph-eye"></i>
                    </button>
            `;

            if (IS_ADMIN) {
                row += `
                    <button class="btn btn-sm btn-warning update-status-btn" data-id="${commission.id}" title="Cập nhật trạng thái">
                        <i class="ph ph-pencil"></i>
                    </button>
                `;
            }

            row += `
                </td>
            </tr>
            `;

            tbody.append(row);
        });
    }

    /**
     * Update summary cards
     */
    function updateSummaryCards(commissions) {
        let pending = 0, paid = 0, withdrawn = 0, total = 0;

        commissions.forEach(function(c) {
            const amount = parseInt(c.commission_amount);
            if (c.status === 'PENDING') pending += amount;
            if (c.status === 'PAID') paid += amount;
            if (c.status === 'WITHDRAWN') withdrawn += amount;
            total += amount;
        });

        $('#total-pending').text(formatMoney(pending));
        $('#total-paid').text(formatMoney(paid));
        $('#total-withdrawn').text(formatMoney(withdrawn));
        $('#total-all').text(formatMoney(total));
    }

    /**
     * Load partners for select dropdown (Admin only)
     */
    function loadPartners() {
        if (!IS_ADMIN) return;

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_all_partners'
            },
            success: function(response) {
                if (response.success) {
                    const filterSelect = $('#partner-filter');
                    filterSelect.find('option:not(:first)').remove();

                    response.data.forEach(function(partner) {
                        filterSelect.append(`<option value="${partner.id}">${escapeHtml(partner.name)} (${escapeHtml(partner.user_code)})</option>`);
                    });
                }
            }
        });
    }

    /**
     * Format money
     */
    function formatMoney(value) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
    }

    /**
     * Get service type label
     */
    function getServiceTypeLabel(typeKey) {
        const types = <?php echo json_encode($service_types); ?>;
        return types[typeKey] || typeKey;
    }

    /**
     * Get status label
     */
    function getStatusLabel(status) {
        const statuses = <?php echo json_encode($statuses); ?>;
        return statuses[status] || status;
    }

    /**
     * Get status badge class
     */
    function getStatusClass(status) {
        switch(status) {
            case 'PENDING': return 'warning';
            case 'PAID': return 'success';
            case 'WITHDRAWN': return 'info';
            case 'CANCELLED': return 'danger';
            default: return 'secondary';
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
     * View commission detail
     */
    $(document).on('click', '.view-commission-btn', function() {
        const commissionId = $(this).data('id');

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_commission_detail',
                commission_id: commissionId
            },
            success: function(response) {
                if (response.success) {
                    const detail = response.data;
                    const statusLabel = getStatusLabel(detail.status);
                    const serviceLabel = getServiceTypeLabel(detail.service_type);

                    let html = `
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Đối tác:</strong> ${escapeHtml(detail.partner_name)}</p>
                                <p><strong>Loại dịch vụ:</strong> ${escapeHtml(serviceLabel)}</p>
                                <p><strong>Tỷ lệ chiết khấu:</strong> ${parseFloat(detail.discount_rate).toFixed(2)}%</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Ngày tính:</strong> ${detail.calculation_date}</p>
                                <p><strong>Trạng thái:</strong> <span class="badge bg-${getStatusClass(detail.status)}">${statusLabel}</span></p>
                                <p><strong>Hóa đơn:</strong> #${detail.invoice_id}</p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Số tiền dịch vụ:</strong> ${formatMoney(detail.service_amount)}</p>
                                <p><strong>Hoa hồng:</strong> <strong class="text-success">${formatMoney(detail.commission_amount)}</strong></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Ngày thanh toán:</strong> ${detail.payout_date || '—'}</p>
                                <p><strong>Ngày rút tiền:</strong> ${detail.withdrawal_date || '—'}</p>
                            </div>
                        </div>

                        ${detail.notes ? `<p><strong>Ghi chú:</strong> ${escapeHtml(detail.notes)}</p>` : ''}
                    `;

                    $('#commission-detail-body').html(html);
                    // Use vanilla Bootstrap modal
                    const detailModal = new bootstrap.Modal(document.getElementById('commissionDetailModal'));
                    detailModal.show();
                } else {
                    alert('Lỗi: ' + response.data.message);
                }
            }
        });
    });

    // Admin-only events
    <?php if ($is_admin): ?>

    /**
     * Update status - single
     */
    $(document).on('click', '.update-status-btn', function() {
        commissionToUpdate = $(this).data('id');
        $('#status-select').val('');
        // Use vanilla Bootstrap modal
        const statusModal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
        statusModal.show();
    });

    $('#confirm-status-btn').on('click', function() {
        const newStatus = $('#status-select').val();
        if (!newStatus) {
            alert('Vui lòng chọn trạng thái!');
            return;
        }

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'update_commission_status',
                commission_id: commissionToUpdate,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // Hide status modal
                    const statusModal = bootstrap.Modal.getInstance(document.getElementById('updateStatusModal'));
                    if (statusModal) statusModal.hide();
                    loadCommissions();
                    alert(response.data.message);
                } else {
                    alert('Lỗi: ' + response.data.message);
                }
            }
        });
    });

    /**
     * Checkbox handling
     */
    $('#header-checkbox').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.commission-checkbox').prop('checked', isChecked);
        updateSelectedCount();
    });

    $(document).on('change', '.commission-checkbox', function() {
        updateSelectedCount();
    });

    function updateSelectedCount() {
        const count = $('.commission-checkbox:checked').length;
        $('#selected-count').text(count + ' được chọn');
        
        if (count > 0) {
            $('#bulk-actions-container').removeClass('d-none');
        } else {
            $('#bulk-actions-container').addClass('d-none');
        }
    }

    /**
     * Bulk update status
     */
    $('#bulk-update-btn').on('click', function() {
        const newStatus = $('#bulk-status-action').val();
        if (!newStatus) {
            alert('Vui lòng chọn trạng thái!');
            return;
        }

        const commissionIds = [];
        $('.commission-checkbox:checked').each(function() {
            commissionIds.push($(this).data('id'));
        });

        if (commissionIds.length === 0) {
            alert('Vui lòng chọn ít nhất một hoa hồng!');
            return;
        }

        if (!confirm(`Bạn có chắc chắn muốn cập nhật ${commissionIds.length} hoa hồng không?`)) {
            return;
        }

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'bulk_update_commission_status',
                commission_ids: commissionIds,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    loadCommissions();
                    $('#bulk-status-action').val('');
                    $('#header-checkbox').prop('checked', false);
                    alert(response.data.message);
                } else {
                    alert('Lỗi: ' + response.data.message);
                }
            }
        });
    });

    /**
     * Filter and reset
     */
    $('#partner-filter, #service-type-filter, #status-filter, #from-date, #to-date').on('change', function() {
        loadCommissions();
    });

    $('#reset-filters-btn').on('click', function() {
        $('#partner-filter').val('');
        $('#service-type-filter').val('');
        $('#status-filter').val('');
        $('#from-date').val('');
        $('#to-date').val('');
        loadCommissions();
    });

    <?php endif; ?>

    // Initial load
    <?php if ($is_admin): ?>
    loadPartners();
    <?php endif; ?>
    loadCommissions();
});
</script>

<?php get_footer(); ?>
