/**
 * Revenue Statistics - JavaScript Handlers
 * Files using this: revenue_stats.php
 */

jQuery(document).ready(function($) {
    /**
     * Load monthly revenue details via AJAX
     * Shows invoices for selected month in a modal
     */
    $(document).on('click', '.view-month-details', function(e) {
        e.preventDefault();
        
        const month = $(this).data('month');
        const year = $(this).data('year');
        const button = $(this);
        
        // Show loading state
        const originalText = button.html();
        button.prop('disabled', true).html('<i class="ph ph-spinner ph-spin"></i>');
        
        $.ajax({
            url: AJAX.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_monthly_invoice_details',
                month: month,
                year: year,
                security: $('input[name="revenue_stats_nonce"]').val() || ''
            },
            success: function(response) {
                if (response.success) {
                    // Create modal with monthly data
                    const monthNames = ['', 'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 
                                      'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 
                                      'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
                    
                    const data = response.data;
                    let invoiceRows = '';
                    
                    if (data.invoices && data.invoices.length > 0) {
                        data.invoices.forEach(function(invoice) {
                            invoiceRows += `
                                <tr>
                                    <td>${escapeHtml(invoice.invoice_code)}</td>
                                    <td>${escapeHtml(invoice.customer_name)}</td>
                                    <td class="text-right">${formatNumber(invoice.total_amount)} ₫</td>
                                    <td class="text-right">${formatNumber(invoice.paid_amount)} ₫</td>
                                    <td class="text-center">
                                        <a href="/chi-tiet-hoa-don/?id=${invoice.id}" class="btn btn-sm btn-info">
                                            <i class="ph ph-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        invoiceRows = '<tr><td colspan="5" class="text-center text-muted">Không có hóa đơn nào</td></tr>';
                    }
                    
                    const modal = `
                        <div class="modal fade" id="monthDetailsModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">${monthNames[month]} ${year}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <div class="card border-primary">
                                                    <div class="card-body">
                                                        <div class="text-muted">Tổng HĐ</div>
                                                        <div class="fs-5 fw-bold">${data.total_invoices}</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card border-success">
                                                    <div class="card-body">
                                                        <div class="text-muted">Tổng Tiền</div>
                                                        <div class="fs-5 fw-bold">${formatNumber(data.total_amount)} ₫</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card border-info">
                                                    <div class="card-body">
                                                        <div class="text-muted">Tỷ Lệ TT</div>
                                                        <div class="fs-5 fw-bold">${data.payment_percentage}%</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Mã HĐ</th>
                                                        <th>Khách Hàng</th>
                                                        <th class="text-right">Tổng Tiền</th>
                                                        <th class="text-right">Đã TT</th>
                                                        <th class="text-center">Thao Tác</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${invoiceRows}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Remove old modal if exists
                    $('#monthDetailsModal').remove();
                    
                    // Add and show new modal
                    $('body').append(modal);
                    const modalInstance = new bootstrap.Modal(document.getElementById('monthDetailsModal'));
                    modalInstance.show();
                    
                    // Cleanup on hide
                    document.getElementById('monthDetailsModal').addEventListener('hidden.bs.modal', function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message || 'Đã có lỗi xảy ra');
                }
            },
            error: function() {
                alert('Đã có lỗi xảy ra khi tải dữ liệu');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });

    /**
     * Export revenue report to CSV
     */
    $(document).on('click', '#export-report-btn', function(e) {
        e.preventDefault();
        
        const year = $('#year-select').val();
        const partner = $('#partner-filter').val();
        
        window.location.href = AJAX.ajaxurl + '?action=export_revenue_report&year=' + year + '&partner=' + partner;
    });
});

/**
 * Helper function to escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

/**
 * Helper function to format numbers with thousand separator
 */
function formatNumber(num) {
    if (!num) return '0';
    return parseInt(num).toLocaleString('vi-VN');
}
