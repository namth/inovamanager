<?php
/*
    Template Name: Cart
*/

global $wpdb;
$cart_table = $wpdb->prefix . 'im_cart';
$users_table = $wpdb->prefix . 'im_users';
$domains_table = $wpdb->prefix . 'im_domains';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
$website_services_table = $wpdb->prefix . 'im_website_services';
$product_catalog_table = $wpdb->prefix . 'im_product_catalog';

// Get all cart items grouped by user
$cart_items_query = "
    SELECT
        c.*,
        u.name AS customer_name,
        u.user_code AS customer_code,
        u.email AS customer_email
    FROM $cart_table c
    LEFT JOIN $users_table u ON c.user_id = u.id
    ORDER BY u.name, c.added_at DESC
";

$cart_items = $wpdb->get_results($cart_items_query);

// Group cart items by customer
$cart_by_customer = [];
foreach ($cart_items as $item) {
    $user_id = $item->user_id;
    if (!isset($cart_by_customer[$user_id])) {
        $cart_by_customer[$user_id] = [
            'customer_name' => $item->customer_name,
            'customer_code' => $item->customer_code,
            'customer_email' => $item->customer_email,
            'items' => []
        ];
    }
    $cart_by_customer[$user_id]['items'][] = $item;
}

get_header();
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title">
                            <i class="ph ph-shopping-cart me-2"></i>Giỏ hàng
                        </h4>
                    </div>

                    <?php if (empty($cart_by_customer)): ?>
                    <div class="text-center py-5">
                        <i class="ph ph-shopping-cart-simple icon-lg text-muted mb-3" style="font-size: 64px;"></i>
                        <h4>Giỏ hàng trống</h4>
                        <p class="text-muted">Chưa có dịch vụ nào trong giỏ hàng</p>
                        <a href="<?php echo home_url('/danh-sach-ten-mien/'); ?>" class="btn btn-primary me-2">
                            <i class="ph ph-globe me-2"></i>Xem danh sách Domain
                        </a>
                        <a href="<?php echo home_url('/danh-sach-hosting/'); ?>" class="btn btn-secondary me-2">
                            <i class="ph ph-database me-2"></i>Xem danh sách Hosting
                        </a>
                        <a href="<?php echo home_url('/danh-sach-goi-bao-tri/'); ?>" class="btn btn-info">
                            <i class="ph ph-wrench me-2"></i>Xem danh sách Bảo trì
                        </a>
                    </div>
                    <?php else: ?>

                        <?php foreach ($cart_by_customer as $user_id => $customer_cart): ?>
                        <div class="card mb-4 border">
                            <div class="card-header bg-primary text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="ph ph-user-circle me-2"></i>
                                        <?php echo esc_html($customer_cart['customer_name']); ?>
                                        <span class="badge bg-light text-dark ms-2"><?php echo esc_html($customer_cart['customer_code']); ?></span>
                                    </h5>
                                    <span class="badge bg-light text-dark"><?php echo count($customer_cart['items']); ?> dịch vụ</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 50px;">STT</th>
                                                <th>Loại dịch vụ</th>
                                                <th>Tên dịch vụ</th>
                                                <th>Giá gia hạn</th>
                                                <th>Ngày hết hạn</th>
                                                <th>Số lượng</th>
                                                <th style="width: 60px;">Xóa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $total = 0;
                                            foreach ($customer_cart['items'] as $index => $cart_item):
                                                // Get service details based on type
                                                $service_name = '';
                                                $service_price = 0;
                                                $expiry_date = '';
                                                $service_icon = '';
                                                $service_type_text = '';

                                                switch ($cart_item->service_type) {
                                                    case 'domain':
                                                        $service = $wpdb->get_row($wpdb->prepare("
                                                            SELECT d.domain_name, d.expiry_date, COALESCE(pc.renew_price, 0) AS price
                                                            FROM $domains_table d
                                                            LEFT JOIN $product_catalog_table pc ON d.product_catalog_id = pc.id
                                                            WHERE d.id = %d
                                                        ", $cart_item->service_id));
                                                        if ($service) {
                                                            $service_name = $service->domain_name;
                                                            $service_price = $service->price;
                                                            $expiry_date = $service->expiry_date;
                                                        }
                                                        $service_icon = 'ph-globe';
                                                        $service_type_text = 'Tên miền';
                                                        break;

                                                    case 'hosting':
                                                        $service = $wpdb->get_row($wpdb->prepare("
                                                            SELECT h.hosting_code, h.expiry_date, COALESCE(pc.renew_price, h.price, 0) AS price, pc.name AS product_name
                                                            FROM $hostings_table h
                                                            LEFT JOIN $product_catalog_table pc ON h.product_catalog_id = pc.id
                                                            WHERE h.id = %d
                                                        ", $cart_item->service_id));
                                                        if ($service) {
                                                            $service_name = $service->hosting_code . ' (' . $service->product_name . ')';
                                                            $service_price = $service->price;
                                                            $expiry_date = $service->expiry_date;
                                                        }
                                                        $service_icon = 'ph-database';
                                                        $service_type_text = 'Hosting';
                                                        break;

                                                    case 'maintenance':
                                                        $service = $wpdb->get_row($wpdb->prepare("
                                                            SELECT m.order_code, m.expiry_date, m.price_per_cycle AS price, pc.name AS product_name
                                                            FROM $maintenance_table m
                                                            LEFT JOIN $product_catalog_table pc ON m.product_catalog_id = pc.id
                                                            WHERE m.id = %d
                                                        ", $cart_item->service_id));
                                                        if ($service) {
                                                            $service_name = $service->order_code . ' (' . $service->product_name . ')';
                                                            $service_price = $service->price;
                                                            $expiry_date = $service->expiry_date;
                                                        }
                                                        $service_icon = 'ph-wrench';
                                                        $service_type_text = 'Bảo trì';
                                                        break;

                                                    case 'website_service':
                                                        $service = $wpdb->get_row($wpdb->prepare("
                                                            SELECT ws.title, ws.fixed_price, ws.daily_rate, ws.estimated_manday, ws.pricing_type, w.name AS website_name
                                                            FROM {$website_services_table} ws
                                                            LEFT JOIN {$wpdb->prefix}im_websites w ON ws.website_id = w.id
                                                            WHERE ws.id = %d
                                                        ", $cart_item->service_id));
                                                        if ($service) {
                                                            $service_name = $service->title;
                                                            if ($service->pricing_type === 'FIXED' && $service->fixed_price) {
                                                                $service_price = $service->fixed_price;
                                                            } elseif ($service->pricing_type === 'DAILY' && $service->daily_rate && $service->estimated_manday) {
                                                                $service_price = $service->daily_rate * $service->estimated_manday;
                                                            } else {
                                                                $service_price = 0;
                                                            }
                                                            $expiry_date = '';
                                                        }
                                                        $service_icon = 'ph-gear';
                                                        $service_type_text = 'Dịch vụ Website';
                                                        break;
                                                    }

                                                $item_total = $service_price * $cart_item->quantity;
                                                $total += $item_total;
                                            ?>
                                            <tr>
                                                <td class="text-center fw-bold text-muted"><?php echo $index + 1; ?></td>
                                                <td>
                                                    <i class="ph <?php echo $service_icon; ?> text-primary me-2"></i>
                                                    <?php echo $service_type_text; ?>
                                                </td>
                                                <td class="fw-bold"><?php echo esc_html($service_name); ?></td>
                                                <td><?php echo number_format($service_price, 0, ',', '.'); ?> VNĐ</td>
                                                <td>
                                                    <?php if ($expiry_date): ?>
                                                    <span class="badge <?php echo strtotime($expiry_date) < time() ? 'bg-danger' : 'bg-success'; ?>">
                                                        <?php echo date('d/m/Y', strtotime($expiry_date)); ?>
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="text-muted">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $cart_item->quantity; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger remove-cart-item" data-cart-id="<?php echo $cart_item->id; ?>">
                                                        <i class="ph ph-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-light">
                                                <td colspan="5" class="text-end fw-bold">Tổng cộng:</td>
                                                <td colspan="2" class="fw-bold text-primary"><?php echo number_format($total, 0, ',', '.'); ?> VNĐ</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <a href="<?php echo home_url('/them-moi-hoa-don/?user_id=' . $user_id . '&from_cart=1'); ?>" class="btn btn-primary">
                                    <i class="ph ph-receipt me-2"></i>Tạo hóa đơn cho khách hàng này
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    /**
     * Remove item from cart
     */
    $(document).on('click', '.remove-cart-item', function() {
        if (!confirm('Bạn có chắc muốn xóa dịch vụ này khỏi giỏ hàng?')) {
            return;
        }

        var $btn = $(this);
        var cartId = $btn.data('cart-id');
        var $row = $btn.closest('tr');

        $btn.prop('disabled', true);
        $btn.html('<i class="ph ph-circle-notch ph-spin"></i>');

        $.ajax({
            url: AJAX.ajaxurl,
            type: 'POST',
            data: {
                action: 'remove_from_cart',
                cart_id: cartId
            },
            success: function(response) {
                var data = JSON.parse(response);
                if (data.status) {
                    $row.fadeOut(function() {
                        $(this).remove();
                        // Reload page if no items left
                        if ($('.remove-cart-item').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(data.message);
                    $btn.prop('disabled', false);
                    $btn.html('<i class="ph ph-trash"></i>');
                }
            },
            error: function() {
                alert('Có lỗi xảy ra');
                $btn.prop('disabled', false);
                $btn.html('<i class="ph ph-trash"></i>');
            }
        });
    });
});
</script>

<?php get_footer(); ?>
