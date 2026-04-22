<?php
/* REST API Endpoints - Imported from functions.php */

function api_get_expiring_services($request)
{
    $days = $request->get_param('days');
    $days = $days ? intval($days) : 30;

    $service_types_param = $request->get_param('service_types');
    $service_types = ['domain', 'hosting', 'maintenance'];

    if ($service_types_param) {
        $service_types = array_map('trim', explode(',', $service_types_param));
    }

    $services = get_expiring_services($days, $service_types);

    // Calculate total count
    $total = 0;
    foreach ($services as $type => $items) {
        $total += count($items);
    }

    return new WP_REST_Response([
        'success' => true,
        'data' => $services,
        'total' => $total,
        'days_threshold' => $days,
        'service_types' => $service_types
    ], 200);
}

function api_create_bulk_invoice($request)
{
    global $wpdb;

    $user_id = intval($request->get_param('owner_user_id') ?: $request->get_param('user_id'));
    $services = $request->get_param('services');
    $invoice_date = sanitize_text_field($request->get_param('invoice_date'));
    $due_date = sanitize_text_field($request->get_param('due_date'));
    $notes = sanitize_textarea_field($request->get_param('notes'));

    // Validation
    if (!$user_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'owner_user_id is required'
        ], 400);
    }

    if (empty($services) || !is_array($services)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'services array is required'
        ], 400);
    }

    // Verify user exists
    $users_table = $wpdb->prefix . 'im_users';
    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$users_table} WHERE id = %d", $user_id));

    if (!$user) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'User not found'
        ], 404);
    }

    // Tables
    $invoices_table = $wpdb->prefix . 'im_invoices';
    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
    $domains_table = $wpdb->prefix . 'im_domains';
    $hostings_table = $wpdb->prefix . 'im_hostings';
    $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
    $product_catalog_table = $wpdb->prefix . 'im_product_catalog';

    // Generate invoice code
    $invoice_code = generate_invoice_code($user_id);

    // Calculate totals
    $sub_total = 0;
    $invoice_items = [];

    foreach ($services as $service) {
        $service_type = sanitize_text_field($service['service_type']);
        $service_id = intval($service['service_id']);
        $quantity = isset($service['quantity']) ? intval($service['quantity']) : 1;

        $item_data = null;

        switch ($service_type) {
            case 'domain':
                $domain = $wpdb->get_row($wpdb->prepare("
                    SELECT d.*, pc.renew_price, pc.name AS product_name
                    FROM {$domains_table} d
                    LEFT JOIN {$product_catalog_table} pc ON d.product_catalog_id = pc.id
                    WHERE d.id = %d
                ", $service_id));

                if ($domain) {
                    $price = $domain->renew_price ? $domain->renew_price : 0;

                    // Determine start_date and end_date based on status
                    if ($domain->status === 'NEW') {
                        $start_date = $domain->registration_date;
                        $end_date = $domain->expiry_date;
                    } else {
                        $start_date = $domain->expiry_date;
                        $end_date = date('Y-m-d', strtotime($domain->expiry_date . ' +1 year'));
                    }

                    $item_data = [
                        'service_type' => 'domain',
                        'service_id' => $service_id,
                        'description' => 'Gia hạn tên miền ' . $domain->domain_name . ' - 1 năm',
                        'unit_price' => $price,
                        'quantity' => $quantity,
                        'item_total' => $price * $quantity,
                        'start_date' => $start_date,
                        'end_date' => $end_date
                    ];
                }
                break;

            case 'hosting':
                $hosting = $wpdb->get_row($wpdb->prepare("
                    SELECT h.*, pc.renew_price, pc.name AS product_name
                    FROM {$hostings_table} h
                    LEFT JOIN {$product_catalog_table} pc ON h.product_catalog_id = pc.id
                    WHERE h.id = %d
                ", $service_id));

                if ($hosting) {
                    $price = $hosting->renew_price ? $hosting->renew_price : $hosting->price;
                    $months = $hosting->billing_cycle_months;

                    // Determine start_date and end_date based on status
                    if ($hosting->status === 'NEW') {
                        $start_date = $hosting->registration_date;
                        $end_date = $hosting->expiry_date;
                    } else {
                        $start_date = $hosting->expiry_date;
                        $end_date = date('Y-m-d', strtotime($hosting->expiry_date . " +{$months} months"));
                    }

                    $item_data = [
                        'service_type' => 'hosting',
                        'service_id' => $service_id,
                        'description' => 'Gia hạn hosting ' . $hosting->hosting_code . ' - ' . $months . ' tháng',
                        'unit_price' => $price,
                        'quantity' => $quantity,
                        'item_total' => $price * $quantity,
                        'start_date' => $start_date,
                        'end_date' => $end_date
                    ];
                }
                break;

            case 'maintenance':
                $maintenance = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM {$maintenance_table} WHERE id = %d
                ", $service_id));

                if ($maintenance) {
                    $price = $maintenance->price_per_cycle;
                    $months = $maintenance->billing_cycle_months;

                    // Determine start_date and end_date based on status
                    if ($maintenance->status === 'NEW') {
                        $start_date = $maintenance->registration_date;
                        $end_date = $maintenance->expiry_date;
                    } else {
                        $start_date = $maintenance->expiry_date;
                        $end_date = date('Y-m-d', strtotime($maintenance->expiry_date . " +{$months} months"));
                    }

                    $item_data = [
                        'service_type' => 'maintenance',
                        'service_id' => $service_id,
                        'description' => 'Gia hạn gói bảo trì ' . $maintenance->order_code . ' - ' . $months . ' tháng',
                        'unit_price' => $price,
                        'quantity' => $quantity,
                        'item_total' => $price * $quantity,
                        'start_date' => $start_date,
                        'end_date' => $end_date
                    ];
                }
                break;
        }

        if ($item_data) {
            $invoice_items[] = $item_data;
            $sub_total += $item_data['item_total'];
        }
    }

    if (empty($invoice_items)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'No valid services found'
        ], 404);
    }

    // Get user's VAT invoice preference
    $user_vat_settings = get_user_meta($user_id, 'inova_vat_invoice_settings', true);
    $requires_vat_invoice = 0;
    if (!empty($user_vat_settings) && isset($user_vat_settings['requires_vat_invoice'])) {
        $requires_vat_invoice = intval($user_vat_settings['requires_vat_invoice']);
    }

    // Create invoice
    $wpdb->insert($invoices_table, [
        'invoice_code' => $invoice_code,
        'user_id' => $user_id,
        'invoice_date' => $invoice_date,
        'due_date' => $due_date,
        'sub_total' => $sub_total,
        'discount_total' => 0,
        'tax_amount' => 0,
        'total_amount' => $sub_total,
        'notes' => $notes,
        'status' => 'pending',
        'requires_vat_invoice' => $requires_vat_invoice,
        'created_by_type' => 'api',
        'created_by_id' => get_current_user_id()
    ]);

    $invoice_id = $wpdb->insert_id;

    // Insert invoice items
    foreach ($invoice_items as $item) {
        $item['invoice_id'] = $invoice_id;
        $wpdb->insert($invoice_items_table, $item);
    }

    return new WP_REST_Response([
        'success' => true,
        'data' => [
            'invoice_id' => $invoice_id,
            'invoice_code' => $invoice_code,
            'total_amount' => $sub_total,
            'items_count' => count($invoice_items)
        ],
        'message' => 'Invoice created successfully'
    ], 201);
}


/* ===== REGISTER REST API ROUTES ===== */
add_action('rest_api_init', function () {
    register_rest_route('inova/v1', '/expiring-services', [
        'methods' => 'GET',
        'callback' => 'api_get_expiring_services',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('inova/v1', '/bulk-invoice', [
        'methods' => 'POST',
        'callback' => 'api_create_bulk_invoice',
        'permission_callback' => '__return_true'
    ]);
});
