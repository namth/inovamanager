<?php
/* 
    Template Name: Add Invoice
*/

global $wpdb;
$invoice_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';
$users_table = $wpdb->prefix . 'im_users';
$domains_table = $wpdb->prefix . 'im_domains';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
$websites_table = $wpdb->prefix . 'im_websites';

# 1. Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_invoice_form_submission();
}

# 2. Initialize variables
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$bulk_domains = isset($_GET['bulk_domains']) ? sanitize_text_field($_GET['bulk_domains']) : '';
$bulk_hostings = isset($_GET['bulk_hostings']) ? sanitize_text_field($_GET['bulk_hostings']) : '';
$bulk_maintenances = isset($_GET['bulk_maintenances']) ? sanitize_text_field($_GET['bulk_maintenances']) : '';
$bulk_websites = isset($_GET['bulk_websites']) ? sanitize_text_field($_GET['bulk_websites']) : '';
$domain_id = isset($_GET['domain_id']) ? intval($_GET['domain_id']) : 0;
$hosting_id = isset($_GET['hosting_id']) ? intval($_GET['hosting_id']) : 0;
$maintenance_id = isset($_GET['maintenance_id']) ? intval($_GET['maintenance_id']) : 0;
$website_id = isset($_GET['website_id']) ? intval($_GET['website_id']) : 0;
$from_cart = isset($_GET['from_cart']) && $_GET['from_cart'] == 1;

# 3. Load existing invoice or cart items
$existing_invoice = null;
$renewal_products = [];
$invoice_items = [];
$product_data = null;
$website_data = null;
$partner_info = null;
$users = [];
$service_type = '';

load_invoice_data($invoice_id, $user_id, $from_cart, $bulk_domains, $bulk_hostings, $bulk_maintenances, $bulk_websites, $domain_id, $hosting_id, $maintenance_id, $website_id);

get_header();
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title"><?php echo $invoice_id > 0 ? 'Chỉnh sửa hóa đơn' : 'Tạo mới hóa đơn'; ?></h4>
                        <a href="<?php echo home_url('/danh-sach-hoa-don/'); ?>" class="btn btn-secondary btn-icon-text">
                            <i class="ph ph-arrow-left btn-icon-prepend"></i>
                            <span>Quay lại danh sách hóa đơn</span>
                        </a>
                    </div>
                    
                    <form id="invoice-form" method="post" action="">
                        <input type="hidden" name="action" value="<?php echo $invoice_id > 0 ? 'edit_invoice' : 'add_invoice'; ?>">
                        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                        <?php if ($from_cart): ?>
                        <input type="hidden" name="from_cart" value="1">
                        <?php endif; ?>
                        
                        <?php display_invoice_header(); ?>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <?php display_invoice_info_card(); ?>
                                <?php display_invoice_items_card(); ?>
                            </div>
                            
                            <div class="col-md-4">
                                <?php display_invoice_summary_card(); ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal thêm dịch vụ vào hóa đơn -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm dịch vụ vào hóa đơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <label class="form-label">Loại dịch vụ</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="service_type_select" id="type-domain" value="domain" autocomplete="off" checked>
                        <label class="btn btn-primary d-flex align-items-center" for="type-domain">
                            <i class="ph ph-globe me-1"></i> Tên miền
                        </label>
                        
                        <input type="radio" class="btn-check" name="service_type_select" id="type-hosting" value="hosting" autocomplete="off">
                        <label class="btn btn-primary d-flex align-items-center" for="type-hosting">
                            <i class="ph ph-database me-1"></i> Hosting
                        </label>
                        
                        <input type="radio" class="btn-check" name="service_type_select" id="type-maintenance" value="maintenance" autocomplete="off">
                        <label class="btn btn-primary d-flex align-items-center" for="type-maintenance">
                            <i class="ph ph-wrench me-1"></i> Bảo trì
                        </label>
                    </div>
                </div>
                
                <!-- Domain Selection -->
                <div id="domain-select" class="service-select-container">
                    <div class="mb-3">
                        <label class="form-label">Chọn tên miền</label>
                        <select class="form-select js-example-basic-single" id="domain-dropdown" data-service-type="domain">
                            <option value="">-- Chọn tên miền --</option>
                            <?php echo get_service_options('domain', $user_id); ?>
                        </select>
                    </div>
                </div>
                
                <!-- Hosting Selection -->
                <div id="hosting-select" class="service-select-container d-none">
                    <div class="mb-3">
                        <label class="form-label">Chọn hosting</label>
                        <select class="form-select js-example-basic-single" id="hosting-dropdown" data-service-type="hosting">
                            <option value="">-- Chọn hosting --</option>
                            <?php echo get_service_options('hosting', $user_id); ?>
                        </select>
                    </div>
                </div>
                
                <!-- Maintenance Selection -->
                <div id="maintenance-select" class="service-select-container d-none">
                    <div class="mb-3">
                        <label class="form-label">Chọn gói bảo trì</label>
                        <select class="form-select js-example-basic-single" id="maintenance-dropdown" data-service-type="maintenance">
                            <option value="">-- Chọn gói bảo trì --</option>
                            <?php echo get_service_options('maintenance', $user_id); ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="add-service-btn">Thêm vào hóa đơn</button>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>

<script>
jQuery(document).ready(function($) {
    // Service type selector - hiển thị/ẩn dropdown tương ứng
    $(document).on('change', 'input[name="service_type_select"]', function() {
        var selectedType = $(this).val();
        $('.service-select-container').addClass('d-none');
        $('#' + selectedType + '-select').removeClass('d-none');
    });
    
    // Thêm service vào hóa đơn
    $(document).on('click', '#add-service-btn', function() {
        var serviceType = $('input[name="service_type_select"]:checked').val();
        var dropdown = $('#' + serviceType + '-dropdown');
        var selectedId = dropdown.val();
        var selectedOption = dropdown.find('option:selected');
        
        if (!selectedId) {
            alert('Vui lòng chọn ' + (serviceType === 'domain' ? 'tên miền' : serviceType === 'hosting' ? 'hosting' : 'gói bảo trì'));
            return;
        }
        
        // Lấy thông tin từ data attributes
        var price = parseFloat(selectedOption.data('price')) || 0;
        var expiry = selectedOption.data('expiry') || '';
        var period = parseFloat(selectedOption.data('period')) || 1;
        var productName = selectedOption.data('name') || '';
        var websiteName = selectedOption.data('website-name') || '';
        
        // Extract service name from option text (remove price suffix)
        var fullText = selectedOption.text().trim();
        var name = fullText;
        // Remove the price part if it exists (format: "Name - 100,000 VNĐ")
        var priceMatch = fullText.match(/(.*?)\s*-\s*[\d,\.]+\s*VNĐ$/);
        if (priceMatch) {
            name = priceMatch[1].trim();
        }
        
        // Tính ngày kết thúc
        var startDate = expiry;
        var endDate = calculateEndDate(expiry, period, serviceType);
        
        // Build description
        var description = buildServiceDescription(serviceType, name, productName, period);
        
        // Add row to table
        addInvoiceItemRow(selectedId, serviceType, name, description, price, startDate, endDate, websiteName);
        
        // Close modal
        bootstrap.Modal.getInstance(document.getElementById('addServiceModal')).hide();
        
        // Reset form
        $('#domain-dropdown').val('').trigger('change');
        $('#hosting-dropdown').val('').trigger('change');
        $('#maintenance-dropdown').val('').trigger('change');
    });
    
    // Recalculate totals when item values change
    $(document).on('input', '.unit-price, .quantity, #discount-amount, #tax-amount', function() {
        calculateInvoiceTotals();
    });
    
    // Remove invoice item
    $(document).on('click', '.remove-item', function(e) {
        e.preventDefault();
        if (confirm('Bạn chắc chắn muốn xóa item này?')) {
            $(this).closest('tr').remove();
            calculateInvoiceTotals();
        }
    });
    
    // Initialize on page load
    calculateInvoiceTotals();
});

// Helper functions
function buildServiceDescription(type, name, productName, period) {
    var descriptions = {
        'domain': 'Gia hạn tên miền ' + name + ' - ' + period + ' năm',
        'hosting': 'Gia hạn hosting ' + productName + ' - ' + (period / 12) + ' năm',
        'maintenance': 'Gia hạn gói bảo trì ' + productName + ' - ' + (period / 12) + ' năm'
    };
    return descriptions[type] || '';
}

function calculateEndDate(dateStr, period, serviceType) {
    var date = new Date(dateStr);
    
    if (serviceType === 'domain') {
        // Domain: period is in years
        date.setFullYear(date.getFullYear() + period);
    } else {
        // Hosting/Maintenance: period is in months
        date.setMonth(date.getMonth() + period);
    }
    
    return date.toISOString().split('T')[0];
}

function addInvoiceItemRow(serviceId, serviceType, serviceName, description, price, startDate, endDate, websiteName) {
    var itemTotal = price;
    var vat = calculateVAT(itemTotal);
    var vatRate = getVATRate(serviceType);
    var priceFormatted = parseInt(price).toLocaleString('vi-VN');
    var itemTotalFormatted = parseInt(itemTotal).toLocaleString('vi-VN');
    var vatFormatted = parseInt(vat).toLocaleString('vi-VN');
    
    var html = '<tr class="invoice-item">' +
        '<td>' +
            '<input type="hidden" name="item_id[]" value="0">' +
            '<input type="hidden" name="service_type[]" value="' + serviceType + '">' +
            '<input type="hidden" name="service_id[]" value="' + serviceId + '">' +
            '<input type="hidden" name="start_date[]" value="' + startDate + '">' +
            '<input type="hidden" name="end_date[]" value="' + endDate + '">' +
            '<input type="hidden" name="vat_rate[]" value="' + vatRate + '">' +
            '<strong>' + serviceType.charAt(0).toUpperCase() + serviceType.slice(1) + ':</strong> ' + serviceName +
            (websiteName ? '<br><small class="text-muted"><i class="ph ph-globe-hemisphere-west"></i> ' + websiteName + '</small>' : '') +
        '</td>' +
        '<td><input type="text" class="form-control" name="description[]" value="' + description + '" required></td>' +
        '<td><input type="text" class="form-control unit-price" name="unit_price[]" value="' + price + '" required></td>' +
        '<td><input type="number" class="form-control quantity" name="quantity[]" value="1" min="1" required></td>' +
        '<td><input type="text" class="form-control item-total" name="item_total[]" value="' + itemTotal + '" readonly></td>' +
        '<td><div class="item-vat-display"><span class="vat-amount">' + vatFormatted + ' VNĐ</span><br><small class="text-muted vat-rate">(' + vatRate + '%)</small></div></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm remove-item"><i class="ph ph-trash"></i></button></td>' +
        '</tr>';
    
    $('#invoice-items-table tbody').append(html);
}

function calculateInvoiceTotals() {
    var subTotal = 0;
    var taxAmount = 0;
    
    $('#invoice-items-table tbody tr.invoice-item').each(function() {
        var unitPrice = parseFloat($(this).find('.unit-price').val()) || 0;
        var quantity = parseFloat($(this).find('.quantity').val()) || 1;
        var itemTotal = unitPrice * quantity;
        
        $(this).find('.item-total').val(itemTotal);
        
        var vatRate = $(this).find('input[name="vat_rate[]"]').val() || 0;
        var vat = itemTotal * (vatRate / 100);
        
        subTotal += itemTotal;
        taxAmount += vat;
    });
    
    var discount = parseFloat($('#discount-amount').val()) || 0;
    var customTax = parseFloat($('#tax-amount').val()) || 0;
    var total = subTotal + customTax - discount;
    
    $('#summary-subtotal').text(formatCurrency(subTotal));
    $('#tax-amount').val(customTax);
    $('#summary-total').text(formatCurrency(total));
    $('#sub-total-input').val(subTotal);
    $('#total-amount-input').val(total);
}

function formatCurrency(amount) {
    return parseInt(amount).toLocaleString('vi-VN') + ' VNĐ';
}

function calculateVAT(amount) {
    var rate = 10; // mặc định 10%
    return parseInt(amount * (rate / 100));
}

function getVATRate(serviceType) {
    // Có thể gọi function PHP hoặc return hardcoded value
    return 10;
}
</script>
