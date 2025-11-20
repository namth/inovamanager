<?php
/*
    Template Name: Cloudflare Import
*/

get_header();
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">
                            <i class="ph ph-cloud-arrow-up me-2"></i>
                            Thêm nhanh Website & Domain từ Cloudflare
                        </h4>
                        <button id="fetch-from-cloudflare" class="btn btn-primary btn-icon-text">
                            <i class="ph ph-cloud-arrow-down btn-icon-prepend"></i>
                            Tải danh sách từ Cloudflare
                        </button>
                    </div>

                    <div id="api-status" class="mb-4"></div>

                    <div class="table-responsive" id="cloudflare-sites-container" style="display:none;">
                        <table class="table table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <th>Tên miền</th>
                                    <th>Trạng thái Cloudflare</th>
                                    <th>Trạng thái hệ thống</th>
                                    <th>Địa chỉ IP Gốc (A Record)</th>
                                    <th class="text-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="cloudflare-sites-tbody">
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div id="loading-indicator" class="text-center py-5" style="display:none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Đang tải dữ liệu từ Cloudflare...</p>
                    </div>

                    <div id="no-data-message" class="text-center py-5" style="display:none;">
                        <i class="ph ph-info icon-lg text-muted mb-3"></i>
                        <h4>Chưa có dữ liệu</h4>
                        <p class="text-muted">Nhấn nút "Tải danh sách từ Cloudflare" để bắt đầu.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const fetchButton = $('#fetch-from-cloudflare');
    const sitesContainer = $('#cloudflare-sites-container');
    const sitesTbody = $('#cloudflare-sites-tbody');
    const loadingIndicator = $('#loading-indicator');
    const noDataMessage = $('#no-data-message');
    const apiStatus = $('#api-status');

    // Initially show the no-data message
    noDataMessage.show();

    fetchButton.on('click', function() {
        sitesContainer.hide();
        noDataMessage.hide();
        loadingIndicator.show();
        apiStatus.empty();
        sitesTbody.empty();

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'fetch_cloudflare_zones',
                nonce: '<?php echo wp_create_nonce('cloudflare_ajax_nonce'); ?>'
            },
            success: function(response) {
                loadingIndicator.hide();
                console.log('Response:', response);

                if (response.success) {
                    if (response.data.length > 0) {
                        sitesContainer.show();
                        response.data.forEach(function(site) {
                            const row = `
                                <tr id="zone-${site.id}">
                                    <td class="fw-bold">${site.name}</td>
                                    <td><span class="badge bg-${site.status === 'active' ? 'success' : 'warning'}">${site.status}</span></td>
                                    <td class="system-status">
                                        ${site.exists_in_db ? '<span class="badge bg-secondary">Đã tồn tại</span>' : '<span class="badge bg-info">Chưa có</span>'}
                                    </td>
                                    <td class="origin-ip">${site.origin_ip ? `<span class="badge bg-primary">${site.origin_ip}</span>` : '<span class="text-muted">Không tìm thấy</span>'}</td>
                                    <td class="text-center">
                                        ${!site.exists_in_db ? `<button class="btn btn-sm btn-success import-btn" data-zone-id="${site.id}" data-zone-name="${site.name}">Import</button>` : ''}
                                    </td>
                                </tr>
                            `;
                            sitesTbody.append(row);
                        });
                    } else {
                        noDataMessage.html('<i class="ph ph-info icon-lg text-muted mb-3"></i><h4>Không tìm thấy website nào trên Cloudflare.</h4>').show();
                    }
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : 'Lỗi không xác định';
                    apiStatus.html(`<div class="alert alert-danger"><strong>Lỗi:</strong> ${errorMsg}</div>`);
                    noDataMessage.show();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                loadingIndicator.hide();
                console.error('AJAX Error:', textStatus, errorThrown);
                console.error('Response:', jqXHR.responseText);

                var errorMsg = 'Có lỗi xảy ra khi kết nối đến server.';
                if (jqXHR.responseText) {
                    try {
                        var errorData = JSON.parse(jqXHR.responseText);
                        if (errorData.data && errorData.data.message) {
                            errorMsg = errorData.data.message;
                        }
                    } catch(e) {
                        errorMsg += ' Chi tiết: ' + jqXHR.responseText.substring(0, 100);
                    }
                }
                apiStatus.html('<div class="alert alert-danger"><strong>Lỗi kết nối:</strong> ' + errorMsg + '</div>');
                noDataMessage.show();
            }
        });
    });

    // Handle Import button click
    sitesTbody.on('click', '.import-btn', function() {
        const button = $(this);
        const zoneId = button.data('zone-id');
        const zoneName = button.data('zone-name');

        button.prop('disabled', true).html('<i class="ph ph-spinner ph-spin"></i> Đang import...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'import_cloudflare_zone',
                nonce: '<?php echo wp_create_nonce('cloudflare_ajax_nonce'); ?>',
                zone_id: zoneId,
                zone_name: zoneName
            },
            success: function(response) {
                const row = $(`#zone-${zoneId}`);
                if (response.success) {
                    button.remove();
                    row.find('.system-status').html('<span class="badge bg-success">Đã import</span>');
                    apiStatus.html(`<div class="alert alert-success alert-dismissible fade show" role="alert">${response.data.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                } else {
                    button.prop('disabled', false).html('Import');
                    apiStatus.html(`<div class="alert alert-danger alert-dismissible fade show" role="alert">Lỗi: ${response.data.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                }
            },
            error: function() {
                button.prop('disabled', false).html('Import');
                apiStatus.html('<div class="alert alert-danger alert-dismissible fade show" role="alert">Có lỗi xảy ra khi import.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            }
        });
    });
});
</script>

<?php
get_footer();
?>