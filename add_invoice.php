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
                        <h4 class="card-title"><?php echo $invoice_id > 0 ? 'Chỉnh sửa hóa đơn' : 'Tạo mới hóa đơn'; ?>
                        </h4>
                        <a href="<?php echo home_url('/danh-sach-hoa-don/'); ?>"
                            class="btn btn-secondary btn-icon-text">
                            <i class="ph ph-arrow-left btn-icon-prepend"></i>
                            <span>Quay lại danh sách hóa đơn</span>
                        </a>
                    </div>

                    <form id="invoice-form" method="post" action="">
                        <input type="hidden" name="action"
                            value="<?php echo $invoice_id > 0 ? 'edit_invoice' : 'add_invoice'; ?>">
                        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                        <?php if ($from_cart): ?>
                            <input type="hidden" name="from_cart" value="1">
                        <?php endif; ?>

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

<?php get_footer(); ?>

<script>
    jQuery(document).ready(function ($) {
        // Recalculate totals when item values change
        $(document).on('input', '.unit-price, .quantity, #discount-amount, #tax-amount', function () {
            calculateInvoiceTotals();
        });

        // Remove invoice item
        $(document).on('click', '.remove-item', function (e) {
            e.preventDefault();
            if (confirm('Bạn chắc chắn muốn xóa item này?')) {
                $(this).closest('tr').remove();
                calculateInvoiceTotals();
            }
        });

        // Initialize on page load
        calculateInvoiceTotals();
    });

    function calculateInvoiceTotals() {
        var subTotal = 0;
        var taxAmount = 0;

        $('#invoice-items-table tbody tr.invoice-item').each(function () {
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
</script>