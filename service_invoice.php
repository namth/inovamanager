<?php
/* 
    Template Name: Service Invoice Creation - Stage 3: Create Invoice
    Purpose: Create invoice for approved service requests
*/

global $wpdb;
$service_table = $wpdb->prefix . 'im_website_services';
$invoice_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';

// Get service ID from URL
$service_id = $_GET['service_id'] ?? 0;

// Get approved service request data
if ($service_id) {
    $service = $wpdb->get_row($wpdb->prepare("
        SELECT s.*, w.name as website_name, d.domain_name,
               u1.name as requester_name, u1.user_code as requester_code, u1.email as requester_email,
               u2.name as assignee_name, u2.user_code as assignee_code,
               w.owner_user_id
        FROM $service_table s
        LEFT JOIN {$wpdb->prefix}im_websites w ON s.website_id = w.id
        LEFT JOIN {$wpdb->prefix}im_domains d ON w.domain_id = d.id
        LEFT JOIN {$wpdb->prefix}im_users u1 ON s.requested_by = u1.id
        LEFT JOIN {$wpdb->prefix}im_users u2 ON s.assigned_to = u2.id
        WHERE s.id = %d
    ", $service_id));

    if (!$service || !in_array($service->status, ['APPROVED', 'IN_PROGRESS', 'COMPLETED'])) {
        wp_redirect(home_url('/danh-sach-dich-vu/'));
        exit;
    }

    // Check all existing invoices for this service
    $existing_invoices = $wpdb->get_results($wpdb->prepare("
        SELECT i.*, ii.item_total as service_item_amount, ii.description as service_item_desc
        FROM $invoice_table i
        INNER JOIN $invoice_items_table ii ON i.id = ii.invoice_id
        WHERE ii.service_type = 'website_service' AND ii.service_id = %d
        ORDER BY i.created_at ASC
    ", $service_id));

    $existing_invoice = !empty($existing_invoices) ? $existing_invoices[0] : null;

    // Calculate total service value from quotation
    $total_service_value = 0;
    if ($service->pricing_type === 'DAILY') {
        $total_service_value = floatval($service->estimated_manday) * floatval($service->daily_rate);
    } elseif ($service->pricing_type === 'FIXED') {
        $total_service_value = floatval($service->fixed_price);
    }

    $total_invoiced_service_amount = 0;
    foreach ($existing_invoices as $inv) {
        $total_invoiced_service_amount += floatval($inv->service_item_amount);
    }
    $remaining_service_amount = max(0, $total_service_value - $total_invoiced_service_amount);
    $installment_count = count($existing_invoices);
    $next_installment_num = $installment_count + 1;

    // Get pending/new hostings for this customer with correct pricing from product_catalog
    $available_hostings = $wpdb->get_results($wpdb->prepare("
        SELECT 
            h.*,
            COALESCE(pc.renew_price, h.price, 0) AS price
        FROM {$wpdb->prefix}im_hostings h
        LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON h.product_catalog_id = pc.id
        WHERE h.owner_user_id = %d AND h.status = 'NEW'
        ORDER BY h.created_at DESC
    ", $service->owner_user_id));

    // Get pending/new domains for this customer with correct pricing from product_catalog
    $available_domains = $wpdb->get_results($wpdb->prepare("
        SELECT 
            d.*,
            COALESCE(pc.renew_price, d.price, 0) AS price
        FROM {$wpdb->prefix}im_domains d
        LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON d.product_catalog_id = pc.id
        WHERE d.owner_user_id = %d AND d.status = 'NEW'
        ORDER BY d.created_at DESC
    ", $service->owner_user_id));
}

/* 
 * Process invoice creation and deletion
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_invoice']) && wp_verify_nonce($_POST['delete_invoice'], 'delete_invoice')) {
        // Delete existing invoice
        $wpdb->query('START TRANSACTION');

        try {
            // Delete invoice items first
            $wpdb->delete($invoice_items_table, array('invoice_id' => $existing_invoice->id));

            // Cancel commissions associated with this invoice (Phase 2 integration)
            cancel_commissions_for_invoice($existing_invoice->id);

            // Delete the invoice
            $result = $wpdb->delete($invoice_table, array('id' => $existing_invoice->id));

            if ($result === false) {
                throw new Exception('Failed to delete invoice');
            }

            $wpdb->query('COMMIT');
            $message = '<div class="alert alert-success"><strong>Thành công!</strong> Hóa đơn đã được xóa. Bạn có thể tạo hóa đơn mới.</div>';

            // Reset existing invoice to allow creating new one
            $existing_invoice = null;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Không thể xóa hóa đơn: ' . $e->getMessage() . '</div>';
        }
    } elseif (isset($_POST['create_invoice']) && wp_verify_nonce($_POST['create_invoice'], 'create_invoice')) {
        $invoice_date = sanitize_text_field($_POST['invoice_date']);
        $due_date = sanitize_text_field($_POST['due_date']);
        $tax_rate = floatval($_POST['tax_rate']);
        $discount_amount = intval($_POST['discount_amount']);
        $payment_terms = sanitize_textarea_field($_POST['payment_terms']);
        $invoice_notes = sanitize_textarea_field($_POST['invoice_notes']);
        $payment_type = sanitize_text_field($_POST['payment_type']); // NEW: payment type option

        // Calculate pricing for website service
        $unit_price = 0;
        if ($service->pricing_type === 'DAILY') {
            $unit_price = $service->estimated_manday * $service->daily_rate;
        } elseif ($service->pricing_type === 'FIXED') {
            $unit_price = $service->fixed_price;
        }

        // Get selected hosting and domain IDs
        $selected_hostings = isset($_POST['selected_hostings']) ? array_filter(array_map('intval', (array) $_POST['selected_hostings'])) : array();
        $selected_domains = isset($_POST['selected_domains']) ? array_filter(array_map('intval', (array) $_POST['selected_domains'])) : array();

        // Get product details and calculate their total
        $products_total = 0;
        $selected_hosting_items = array();
        $selected_domain_items = array();

        // Get hosting details - use proper query for each item with product_catalog pricing
         if (!empty($selected_hostings)) {
             foreach ($selected_hostings as $hosting_id) {
                 $hosting = $wpdb->get_row($wpdb->prepare("
                     SELECT 
                         h.*,
                         COALESCE(pc.renew_price, h.price, 0) AS price
                     FROM {$wpdb->prefix}im_hostings h
                     LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON h.product_catalog_id = pc.id
                     WHERE h.id = %d
                 ", $hosting_id));
                 if ($hosting) {
                     $selected_hosting_items[] = $hosting;
                     $products_total += floatval($hosting->price);
                 }
             }
         }

        // Get domain details - use proper query for each item with product_catalog pricing
         if (!empty($selected_domains)) {
             foreach ($selected_domains as $domain_id) {
                 $domain = $wpdb->get_row($wpdb->prepare("
                     SELECT 
                         d.*,
                         COALESCE(pc.renew_price, d.price, 0) AS price
                     FROM {$wpdb->prefix}im_domains d
                     LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON d.product_catalog_id = pc.id
                     WHERE d.id = %d
                 ", $domain_id));
                 if ($domain) {
                     $selected_domain_items[] = $domain;
                     $products_total += floatval($domain->price);
                 }
             }
         }

        $milestone_title = !empty($_POST['milestone_title']) ? sanitize_text_field($_POST['milestone_title']) : '';

        // Calculate service portion for this invoice
        $service_charged = 0;
        if ($payment_type === 'percentage') {
            $pct = max(1, min(100, floatval($_POST['installment_percentage'] ?? 30)));
            $service_charged = round($unit_price * ($pct / 100));
            if (empty($milestone_title)) {
                $milestone_title = 'Đợt ' . $next_installment_num . ': Tạm ứng ' . $pct . '%';
            }
        } elseif ($payment_type === 'amount') {
            $service_charged = floatval($_POST['custom_installment_amount'] ?? 0);
            if (empty($milestone_title)) {
                $milestone_title = 'Đợt ' . $next_installment_num . ': Thanh toán theo tiến độ';
            }
        } elseif ($payment_type === 'remaining') {
            $service_charged = $remaining_service_amount;
            if (empty($milestone_title)) {
                $milestone_title = 'Đợt ' . $next_installment_num . ': Quyết toán phần còn lại';
            }
        } elseif ($payment_type === 'full') {
            if (!empty($existing_invoices)) {
                $service_charged = $remaining_service_amount;
                if (empty($milestone_title)) {
                    $milestone_title = 'Đợt ' . $next_installment_num . ': Quyết toán toàn bộ phần còn lại';
                }
            } else {
                $service_charged = $unit_price;
                if (empty($milestone_title)) {
                    $milestone_title = 'Thanh toán trọn gói 100%';
                }
            }
        } elseif ($payment_type === 'partial_50') {
            $service_charged = $unit_price * 0.5;
        }

        $sub_total = $service_charged + $products_total;
        $discount_total = $discount_amount;
        $tax_amount = ($sub_total - $discount_total) * ($tax_rate / 100);
        $total_amount = $sub_total - $discount_total + $tax_amount;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Get user's VAT invoice preference
            $user_vat_settings = get_user_meta($service->owner_user_id, 'inova_vat_invoice_settings', true);
            $requires_vat_invoice = 0;
            if (!empty($user_vat_settings) && isset($user_vat_settings['requires_vat_invoice'])) {
                $requires_vat_invoice = intval($user_vat_settings['requires_vat_invoice']);
            }

            if ($payment_type === 'partial_50' && empty($existing_invoices)) {
                // Generate invoice codes
                $first_invoice_code = generate_invoice_code($service->owner_user_id);

                // Calculate invoice totals matching JavaScript preview logic
                // Invoice 1: 50% service + 100% products
                $invoice1_subtotal = ($unit_price * 0.5) + $products_total;
                $invoice1_discount = $discount_total * 0.5; // Split discount proportionally
                $invoice1_taxable = $invoice1_subtotal - $invoice1_discount;
                $invoice1_tax = round($invoice1_taxable * ($tax_rate / 100));
                $invoice1_total = $invoice1_subtotal - $invoice1_discount + $invoice1_tax;

                // Invoice 2: 50% service only
                $invoice2_subtotal = $unit_price * 0.5;
                $invoice2_discount = $discount_total * 0.5;
                $invoice2_taxable = $invoice2_subtotal - $invoice2_discount;
                $invoice2_tax = round($invoice2_taxable * ($tax_rate / 100));
                $invoice2_total = $invoice2_subtotal - $invoice2_discount + $invoice2_tax;

                // Get partner_id from service (if hosting/maintenance)
                $partner_id = get_partner_id_from_service('website_service', $service_id);

                $invoice_data = array(
                    'invoice_code' => $first_invoice_code,
                    'user_id' => $service->owner_user_id,
                    'invoice_date' => date('Y-m-d', strtotime($invoice_date)),
                    'due_date' => date('Y-m-d', strtotime($due_date)),
                    'sub_total' => $invoice1_subtotal,
                    'discount_total' => $invoice1_discount,
                    'tax_amount' => $invoice1_tax,
                    'total_amount' => $invoice1_total,
                    'paid_amount' => 0,
                    'status' => 'pending',
                    'requires_vat_invoice' => $requires_vat_invoice,
                    'notes' => $invoice_notes . "\n\nThanh toán 50% trước khi bắt đầu thực hiện dịch vụ.",
                    'created_by_type' => 'system',
                    'created_by_id' => get_current_user_id(),
                    'partner_id' => $partner_id,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $invoice_result = $wpdb->insert($invoice_table, $invoice_data);
                if ($invoice_result === false) {
                    throw new Exception('Failed to create first invoice');
                }

                $first_invoice_id = $wpdb->insert_id;

                // Insert invoice item for first payment - 50% of service
                $description = $service->title . " (Thanh toán trước - 50%)";
                if ($service->pricing_type === 'DAILY') {
                    $description .= " (Theo ngày công: {$service->estimated_manday} ngày × " . number_format($service->daily_rate) . " VNĐ)";
                }

                $invoice_item_data = array(
                    'invoice_id' => $first_invoice_id,
                    'service_type' => 'website_service',
                    'service_id' => $service_id,
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $unit_price * 0.5,
                    'item_total' => $unit_price * 0.5,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $item_result = $wpdb->insert($invoice_items_table, $invoice_item_data);
                if ($item_result === false) {
                    throw new Exception('Failed to create first invoice item');
                }

                // Create second invoice for remaining 50%
                $second_invoice_code = generate_invoice_code($service->owner_user_id); // This will be called after the first one is in DB, so count is correct

                $second_invoice_data = array(
                    'invoice_code' => $second_invoice_code,
                    'user_id' => $service->owner_user_id,
                    'invoice_date' => date('Y-m-d', strtotime($invoice_date)),
                    'due_date' => date('Y-m-d', strtotime($due_date . ' +30 days')), // Due after completion
                    'sub_total' => $invoice2_subtotal,
                    'discount_total' => $invoice2_discount,
                    'tax_amount' => $invoice2_tax,
                    'total_amount' => $invoice2_total,
                    'paid_amount' => 0,
                    'status' => 'pending_completion', // Special status for completion payment
                    'requires_vat_invoice' => $requires_vat_invoice,
                    'notes' => $invoice_notes . "\n\nThanh toán 50% còn lại sau khi hoàn thành dịch vụ.",
                    'created_by_type' => 'system',
                    'created_by_id' => get_current_user_id(),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $second_invoice_result = $wpdb->insert($invoice_table, $second_invoice_data);
                if ($second_invoice_result === false) {
                    throw new Exception('Failed to create second invoice');
                }

                $second_invoice_id = $wpdb->insert_id;

                // Insert invoice item for second payment - 50% of service
                $second_description = $service->title . " (Thanh toán sau - 50%)";
                if ($service->pricing_type === 'DAILY') {
                    $second_description .= " (Theo ngày công: {$service->estimated_manday} ngày × " . number_format($service->daily_rate) . " VNĐ)";
                }

                $second_item_data = array(
                    'invoice_id' => $second_invoice_id,
                    'service_type' => 'website_service',
                    'service_id' => $service_id,
                    'description' => $second_description,
                    'quantity' => 1,
                    'unit_price' => $unit_price * 0.5,
                    'item_total' => $unit_price * 0.5,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $second_item_result = $wpdb->insert($invoice_items_table, $second_item_data);
                if ($second_item_result === false) {
                    throw new Exception('Failed to create second invoice item');
                }

                // Add selected hosting items to FIRST invoice (all included in first payment)
                error_log('DEBUG: Adding ' . count($selected_hosting_items) . ' hosting items to first invoice');
                foreach ($selected_hosting_items as $hosting) {
                    error_log('DEBUG: Adding hosting item ID=' . $hosting->id . ', price=' . $hosting->price);
                    $hosting_item_data = array(
                        'invoice_id' => $first_invoice_id,
                        'service_type' => 'hosting',
                        'service_id' => $hosting->id,
                        'description' => 'Gói hosting cho ' . ($service->website_name ?: $hosting->hosting_code),
                        'quantity' => 1,
                        'unit_price' => floatval($hosting->price),
                        'item_total' => floatval($hosting->price),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    );
                    
                    $hosting_item_result = $wpdb->insert($invoice_items_table, $hosting_item_data);
                    if ($hosting_item_result === false) {
                        error_log('Hosting invoice item insert failed for first invoice ' . $first_invoice_id . ': ' . $wpdb->last_error);
                        throw new Exception('Failed to create hosting invoice item: ' . $wpdb->last_error);
                    }
                    error_log('DEBUG: Hosting item inserted successfully');
                }

                // Add selected domain items to FIRST invoice (all included in first payment)
                error_log('DEBUG: Adding ' . count($selected_domain_items) . ' domain items to first invoice');
                foreach ($selected_domain_items as $domain) {
                    error_log('DEBUG: Adding domain item ID=' . $domain->id . ', price=' . $domain->price);
                    $domain_item_data = array(
                        'invoice_id' => $first_invoice_id,
                        'service_type' => 'domain',
                        'service_id' => $domain->id,
                        'description' => 'Gia hạn tên miền ' . $domain->domain_name,
                        'quantity' => 1,
                        'unit_price' => floatval($domain->price),
                        'item_total' => floatval($domain->price),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    );
                    
                    $domain_item_result = $wpdb->insert($invoice_items_table, $domain_item_data);
                    if ($domain_item_result === false) {
                        error_log('Domain invoice item insert failed for first invoice ' . $first_invoice_id . ': ' . $wpdb->last_error);
                        throw new Exception('Failed to create domain invoice item: ' . $wpdb->last_error);
                    }
                    error_log('DEBUG: Domain item inserted successfully');
                }

                // Create commissions for invoices (Phase 2 integration)
                create_commissions_for_invoice($first_invoice_id);
                create_commissions_for_invoice($second_invoice_id);

                $message = '<div class="alert alert-success"><strong>Thành công!</strong> Đã tạo 2 hóa đơn: ' . $first_invoice_code . ' (50% trước) và ' . $second_invoice_code . ' (50% sau khi hoàn thành).</div>';
                $created_invoice_id = $first_invoice_id; // Store for redirect
            } else {
                // Generate single invoice code
                $invoice_code = generate_invoice_code($service->owner_user_id);

                // Get partner_id from service (if hosting/maintenance)
                $partner_id = get_partner_id_from_service('website_service', $service_id);

                // Create single full payment invoice
                $invoice_data = array(
                    'invoice_code' => $invoice_code,
                    'user_id' => $service->owner_user_id,
                    'invoice_date' => date('Y-m-d', strtotime($invoice_date)),
                    'due_date' => date('Y-m-d', strtotime($due_date)),
                    'sub_total' => $sub_total,
                    'discount_total' => $discount_total,
                    'tax_amount' => $tax_amount,
                    'total_amount' => $total_amount,
                    'paid_amount' => 0,
                    'status' => 'draft',
                    'requires_vat_invoice' => $requires_vat_invoice,
                    'notes' => $invoice_notes . (!empty($milestone_title) ? "\n\n[" . $milestone_title . "]" : ""),
                    'created_by_type' => 'system',
                    'created_by_id' => get_current_user_id(),
                    'partner_id' => $partner_id,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $invoice_result = $wpdb->insert($invoice_table, $invoice_data);
                if ($invoice_result === false) {
                    throw new Exception('Failed to create invoice');
                }

                $invoice_id = $wpdb->insert_id;

                // Insert invoice item with milestone title
                $description = $service->title . (!empty($milestone_title) ? ' (' . $milestone_title . ')' : '');
                if ($service->pricing_type === 'DAILY') {
                    $description .= " (Theo ngày công: {$service->estimated_manday} ngày × " . number_format($service->daily_rate) . " VNĐ)";
                }

                $invoice_item_data = array(
                    'invoice_id' => $invoice_id,
                    'service_type' => 'website_service',
                    'service_id' => $service_id,
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $service_charged,
                    'item_total' => $service_charged,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $item_result = $wpdb->insert($invoice_items_table, $invoice_item_data);
                if ($item_result === false) {
                    throw new Exception('Failed to create invoice item');
                }

                // Add selected hosting items to invoice
                error_log('DEBUG: Adding ' . count($selected_hosting_items) . ' hosting items to invoice');
                foreach ($selected_hosting_items as $hosting) {
                    error_log('DEBUG: Adding hosting item ID=' . $hosting->id . ', price=' . $hosting->price);
                    $hosting_item_data = array(
                        'invoice_id' => $invoice_id,
                        'service_type' => 'hosting',
                        'service_id' => $hosting->id,
                        'description' => 'Gói hosting cho ' . ($service->website_name ?: $hosting->hosting_code),
                        'quantity' => 1,
                        'unit_price' => floatval($hosting->price),
                        'item_total' => floatval($hosting->price),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    );
                    
                    $hosting_item_result = $wpdb->insert($invoice_items_table, $hosting_item_data);
                    if ($hosting_item_result === false) {
                        error_log('Hosting invoice item insert failed for invoice ' . $invoice_id . ': ' . $wpdb->last_error);
                        throw new Exception('Failed to create hosting invoice item: ' . $wpdb->last_error);
                    }
                    error_log('DEBUG: Hosting item inserted successfully');
                }

                // Add selected domain items to invoice
                error_log('DEBUG: Adding ' . count($selected_domain_items) . ' domain items to invoice');
                foreach ($selected_domain_items as $domain) {
                    error_log('DEBUG: Adding domain item ID=' . $domain->id . ', price=' . $domain->price);
                    $domain_item_data = array(
                        'invoice_id' => $invoice_id,
                        'service_type' => 'domain',
                        'service_id' => $domain->id,
                        'description' => 'Gia hạn tên miền ' . $domain->domain_name,
                        'quantity' => 1,
                        'unit_price' => floatval($domain->price),
                        'item_total' => floatval($domain->price),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    );
                    
                    $domain_item_result = $wpdb->insert($invoice_items_table, $domain_item_data);
                    if ($domain_item_result === false) {
                        error_log('Domain invoice item insert failed for invoice ' . $invoice_id . ': ' . $wpdb->last_error);
                        throw new Exception('Failed to create domain invoice item: ' . $wpdb->last_error);
                    }
                    error_log('DEBUG: Domain item inserted successfully');
                }

                // Create commissions for invoice (Phase 2 integration)
                create_commissions_for_invoice($invoice_id);

                $message = '<div class="alert alert-success"><strong>Thành công!</strong> Hóa đơn ' . $invoice_code . ' đã được tạo.</div>';
                $created_invoice_id = $invoice_id; // Store for redirect
            }

            $wpdb->query('COMMIT');

            // Redirect back to processing or completion
            $redirect_url = ($service->status === 'COMPLETED') 
                ? home_url('/completion/?service_id=' . $service_id . '&msg=invoice_created') 
                : home_url('/processing/?service_id=' . $service_id . '&msg=invoice_created');
            wp_redirect($redirect_url);
            exit;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $error_message = '<div class="alert alert-danger"><strong>Lỗi!</strong> Không thể tạo hóa đơn: ' . $e->getMessage() . '</div>';
        }
    }
}

get_header();

?>

<div class="main-panel">
    <div class="content-wrapper">
        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="ph ph-receipt me-2"></i>
                            Tạo hóa đơn cho dịch vụ website
                        </h4>
                        <p class="card-description">Giai đoạn 3: Tạo hóa đơn cho yêu cầu đã được duyệt</p>

                        <!-- Display messages -->
                        <?php if (isset($message))
                            echo $message; ?>
                        <?php if (isset($error_message))
                            echo $error_message; ?>

                        <?php if (!empty($existing_invoices)): ?>
                            <div class="card border-info mb-4">
                                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-white">
                                        <i class="ph ph-receipt me-2"></i>
                                        Danh sách các đợt hóa đơn đã tạo cho dịch vụ này
                                    </h5>
                                    <div>
                                        <span class="badge bg-light text-dark">
                                            Đã lập: <?php echo number_format($total_invoiced_service_amount); ?> / <?php echo number_format($total_service_value); ?> VNĐ
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Đợt</th>
                                                    <th>Mã hóa đơn</th>
                                                    <th>Nội dung thanh toán</th>
                                                    <th>Ngày HĐ</th>
                                                    <th>Hạn TT</th>
                                                    <th>Số tiền</th>
                                                    <th>Trạng thái</th>
                                                    <th>Thao tác</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($existing_invoices as $idx => $inv): ?>
                                                    <tr>
                                                        <td><strong>Đợt <?php echo $idx + 1; ?></strong></td>
                                                        <td><strong><?php echo esc_html($inv->invoice_code); ?></strong></td>
                                                        <td><small class="text-muted"><?php echo esc_html($inv->service_item_desc ?: $service->title); ?></small></td>
                                                        <td><?php echo date('d/m/Y', strtotime($inv->invoice_date)); ?></td>
                                                        <td><?php echo date('d/m/Y', strtotime($inv->due_date)); ?></td>
                                                        <td><strong class="text-primary"><?php echo number_format($inv->total_amount); ?> VNĐ</strong></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo in_array($inv->status, ['paid', 'pending_vat']) ? 'success' : 'warning'; ?> text-<?php echo in_array($inv->status, ['paid', 'pending_vat']) ? 'white' : 'dark'; ?>">
                                                                <?php echo in_array($inv->status, ['paid', 'pending_vat']) ? 'Đã thanh toán' : 'Chờ thanh toán'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $inv->id); ?>" class="btn btn-sm btn-outline-primary me-1" title="Xem hóa đơn">
                                                                <i class="ph ph-eye"></i>
                                                            </a>
                                                            <?php if ($inv->status === 'draft'): ?>
                                                                <form method="post" style="display: inline-block;">
                                                                    <?php wp_nonce_field('delete_invoice', 'delete_invoice'); ?>
                                                                    <input type="hidden" name="invoice_id" value="<?php echo esc_attr($inv->id); ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa hóa đơn"
                                                                        onclick="return confirm('Bạn có chắc chắn muốn xóa hóa đơn <?php echo esc_js($inv->invoice_code); ?>?')">
                                                                        <i class="ph ph-trash"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="row mt-3 text-center border-top pt-2">
                                        <div class="col-md-4">
                                            <small class="text-muted">Tổng dịch vụ:</small> <strong><?php echo number_format($total_service_value); ?> VNĐ</strong>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Đã xuất HĐ:</small> <strong class="text-primary"><?php echo number_format($total_invoiced_service_amount); ?> VNĐ</strong>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Còn lại chưa xuất:</small> <strong class="<?php echo $remaining_service_amount > 0 ? 'text-warning' : 'text-success'; ?>"><?php echo number_format($remaining_service_amount); ?> VNĐ</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Invoice Creation Form -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-1 mt-1">
                                            <i class="ph ph-file-text me-2"></i>
                                            <?php echo !empty($existing_invoices) ? ('Lập hóa đơn đợt ' . $next_installment_num) : 'Thông tin hóa đơn'; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form class="forms-sample" method="post" action="">
                                            <?php wp_nonce_field('create_invoice', 'create_invoice'); ?>

                                            <div class="form-group mb-3">
                                                <label for="milestone_title" class="fw-bold">Tên đợt thanh toán / Hạng mục <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="milestone_title" name="milestone_title"
                                                    value="<?php echo esc_attr('Đợt ' . $next_installment_num . ': ' . ($installment_count == 0 ? 'Tạm ứng đợt 1' : ($remaining_service_amount > 0 ? 'Thanh toán theo tiến độ' : 'Phát sinh bổ sung'))); ?>" required>
                                                <small class="form-text text-muted">Ví dụ: Tạm ứng đợt 1 (30%), Bàn giao Staging (40%), Quyết toán nghiệm thu...</small>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="invoice_date" class="fw-bold">Ngày hóa đơn <span class="text-danger">*</span></label>
                                                        <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="due_date" class="fw-bold">Hạn thanh toán <span class="text-danger">*</span></label>
                                                        <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="tax_rate" class="fw-bold">Thuế VAT (%)</label>
                                                        <input type="number" class="form-control" id="tax_rate" name="tax_rate" value="0" min="0" max="100" step="0.1">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="discount_amount" class="fw-bold">Giảm giá (VNĐ)</label>
                                                        <input type="number" class="form-control" id="discount_amount" name="discount_amount" value="0" min="0">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group mb-3">
                                                <label for="payment_type" class="fw-bold">Phương thức tính tiền đợt này <span class="text-danger">*</span></label>
                                                <select class="form-control" id="payment_type" name="payment_type" required>
                                                    <?php if ($installment_count > 0): ?>
                                                        <option value="remaining" selected>Quyết toán toàn bộ phần còn lại (<?php echo number_format($remaining_service_amount); ?> VNĐ)</option>
                                                        <option value="percentage">Theo tỷ lệ % dịch vụ (ví dụ: 30%, 40%, 50%)</option>
                                                        <option value="amount">Theo số tiền cụ thể (VNĐ)</option>
                                                        <option value="full">Toàn bộ giá trị dịch vụ (<?php echo number_format($total_service_value); ?> VNĐ)</option>
                                                    <?php else: ?>
                                                        <option value="full">Thanh toán toàn bộ 100% (<?php echo number_format($total_service_value); ?> VNĐ)</option>
                                                        <option value="percentage">Tạm ứng theo tỷ lệ % (ví dụ: 30%, 50%)</option>
                                                        <option value="amount">Tạm ứng số tiền cụ thể (VNĐ)</option>
                                                        <option value="partial_50">Chia đôi 50% trước + 50% sau (Tạo 2 hóa đơn cùng lúc)</option>
                                                    <?php endif; ?>
                                                </select>
                                                <small class="form-text text-muted" id="payment_type_help">
                                                    <?php echo $installment_count > 0 ? 'Mặc định tính nốt số tiền còn lại chưa xuất hóa đơn.' : 'Chọn thanh toán toàn bộ hoặc tạm ứng theo tỷ lệ.'; ?>
                                                </small>
                                            </div>

                                            <!-- Percentage Row -->
                                            <div id="percentage_row" class="form-group mb-3" style="display: none;">
                                                <label for="installment_percentage" class="fw-bold">Tỷ lệ thanh toán (%)</label>
                                                <div class="input-group mb-2">
                                                    <input type="number" class="form-control" id="installment_percentage" name="installment_percentage" min="1" max="100" value="30">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-secondary quick-pct" data-pct="30">30%</button>
                                                    <button type="button" class="btn btn-outline-secondary quick-pct" data-pct="40">40%</button>
                                                    <button type="button" class="btn btn-outline-secondary quick-pct" data-pct="50">50%</button>
                                                    <button type="button" class="btn btn-outline-secondary quick-pct" data-pct="70">70%</button>
                                                </div>
                                            </div>

                                            <!-- Custom Amount Row -->
                                            <div id="amount_row" class="form-group mb-3" style="display: none;">
                                                <label for="custom_installment_amount" class="fw-bold">Số tiền thanh toán đợt này (VNĐ)</label>
                                                <input type="number" class="form-control" id="custom_installment_amount" name="custom_installment_amount" min="1000" value="<?php echo esc_attr($remaining_service_amount > 0 ? $remaining_service_amount : 1000000); ?>">
                                            </div>

                                                <div class="form-group mb-3">
                                                    <label for="payment_terms" class="fw-bold">Điều khoản thanh toán</label>
                                                    <textarea class="form-control" id="payment_terms" name="payment_terms"
                                                        rows="3"
                                                        placeholder="Ví dụ: Thanh toán trong vòng 30 ngày kể từ ngày xuất hóa đơn...">Thanh toán trong vòng 30 ngày kể từ ngày xuất hóa đơn.</textarea>
                                                </div>

                                                <div class="form-group mb-3">
                                                    <label for="invoice_notes" class="fw-bold">Ghi chú hóa đơn</label>
                                                    <textarea class="form-control" id="invoice_notes" name="invoice_notes"
                                                        rows="3" placeholder="Ghi chú bổ sung cho hóa đơn..."></textarea>
                                                </div>

                                                <!-- Available Products Section inside form -->
                                                <?php if ($available_hostings || $available_domains): ?>
                                                    <div class="mb-4 p-3 bg-light-warning border rounded">
                                                        <h6 class="fw-bold mb-3">
                                                            <i class="ph ph-package me-2"></i>
                                                            Thêm sản phẩm chờ thanh toán
                                                        </h6>
                                                        <p class="text-muted mb-3 small">
                                                            <i class="ph ph-info me-2"></i>
                                                            Chọn hosting và domain chờ thanh toán để thêm vào hóa đơn:
                                                        </p>

                                                        <!-- Hostings -->
                                                         <?php if ($available_hostings): ?>
                                                             <div class="mb-3">
                                                                 <h6 class="fw-bold mb-3" style="font-size: 0.95rem;">
                                                                     <i class="ph ph-cloud me-2"></i>Hosting chờ thanh toán
                                                                 </h6>
                                                                 <div class="row">
                                                                     <?php foreach ($available_hostings as $hosting): ?>
                                                                         <div class="col-md-6 mb-3">
                                                                             <div class="card border-light h-100">
                                                                                 <div class="card-body p-2">
                                                                                     <div class="form-check d-flex align-items-start">
                                                                                         <input class="form-check hosting-checkbox"
                                                                                             type="checkbox" name="selected_hostings[]"
                                                                                             value="<?php echo esc_attr($hosting->id); ?>"
                                                                                             id="hosting_<?php echo esc_attr($hosting->id); ?>"
                                                                                             data-price="<?php echo floatval($hosting->price); ?>">
                                                                                         <label class="form-check-label w-100 ms-2" style="cursor: pointer;"
                                                                                             for="hosting_<?php echo esc_attr($hosting->id); ?>">
                                                                                             <div class="fw-bold text-dark"><?php echo esc_html($hosting->hosting_code); ?></div>
                                                                                             <small class="text-muted d-block">
                                                                                                 Kỳ hạn: <?php echo date('d/m/Y', strtotime($hosting->registration_date)); ?> - <?php echo date('d/m/Y', strtotime($hosting->expiry_date)); ?>
                                                                                             </small>
                                                                                             <div class="mt-2">
                                                                                                 <span class="badge bg-light-primary text-primary">
                                                                                                     <?php echo number_format($hosting->price); ?> VNĐ
                                                                                                 </span>
                                                                                             </div>
                                                                                         </label>
                                                                                     </div>
                                                                                 </div>
                                                                             </div>
                                                                         </div>
                                                                     <?php endforeach; ?>
                                                                 </div>
                                                             </div>
                                                         <?php endif; ?>

                                                        <!-- Domains -->
                                                         <?php if ($available_domains): ?>
                                                             <div class="mt-3">
                                                                 <h6 class="fw-bold mb-3" style="font-size: 0.95rem;">
                                                                     <i class="ph ph-globe me-2"></i>Domain chờ thanh toán
                                                                 </h6>
                                                                 <div class="row">
                                                                     <?php foreach ($available_domains as $domain): ?>
                                                                         <div class="col-md-6 mb-3">
                                                                             <div class="card border-light h-100">
                                                                                 <div class="card-body p-2">
                                                                                     <div class="form-check d-flex align-items-start">
                                                                                         <input class="form-check domain-checkbox"
                                                                                             type="checkbox" name="selected_domains[]"
                                                                                             value="<?php echo esc_attr($domain->id); ?>"
                                                                                             id="domain_<?php echo esc_attr($domain->id); ?>"
                                                                                             data-price="<?php echo floatval($domain->price); ?>">
                                                                                         <label class="form-check-label w-100 ms-2" style="cursor: pointer;"
                                                                                             for="domain_<?php echo esc_attr($domain->id); ?>">
                                                                                             <div class="fw-bold text-dark"><?php echo esc_html($domain->domain_name); ?></div>
                                                                                             <small class="text-muted d-block">
                                                                                                 Kỳ hạn: <?php echo date('d/m/Y', strtotime($domain->registration_date)); ?> - <?php echo date('d/m/Y', strtotime($domain->expiry_date)); ?>
                                                                                             </small>
                                                                                             <div class="mt-2">
                                                                                                 <span class="badge bg-light-success text-success">
                                                                                                     <?php echo number_format($domain->price); ?> VNĐ
                                                                                                 </span>
                                                                                             </div>
                                                                                         </label>
                                                                                     </div>
                                                                                 </div>
                                                                             </div>
                                                                         </div>
                                                                     <?php endforeach; ?>
                                                                 </div>
                                                             </div>
                                                         <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <button type="submit" class="btn btn-primary me-2">
                                                    <i class="ph ph-receipt me-2"></i>Tạo hóa đơn
                                                </button>
                                                <a href="<?php echo home_url('/danh-sach-dich-vu/'); ?>"
                                                    class="btn btn-light">
                                                    <i class="ph ph-arrow-left me-2"></i>Quay lại
                                                </a>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Invoice Preview -->
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            <h5 class="mb-1 mt-1">
                                                <i class="ph ph-eye me-2"></i>
                                                Xem trước hóa đơn
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div id="invoice_preview">
                                                <h6 class="fw-bold mb-3">
                                                    <i class="ph ph-calculator me-2"></i>Chi tiết tính toán
                                                </h6>
                                                <table class="table table-sm">
                                                    <!-- Service price -->
                                                    <tr>
                                                        <td class="fw-bold">Dịch vụ website:</td>
                                                        <td class="text-end fw-bold" id="service_price">
                                                            <?php
                                                            $price = $service->pricing_type === 'DAILY' ?
                                                                ($service->estimated_manday * $service->daily_rate) :
                                                                $service->fixed_price;
                                                            echo number_format($price);
                                                            ?> VNĐ
                                                        </td>
                                                    </tr>
                                                    
                                                    <!-- Products price -->
                                                    <tr id="products_row" style="display: none;">
                                                        <td class="fw-bold">Sản phẩm bổ sung:</td>
                                                        <td class="text-end fw-bold text-primary" id="products_display">0 VNĐ</td>
                                                    </tr>
                                                    
                                                    <!-- Subtotal -->
                                                    <tr id="subtotal_row" class="border-top" style="display: none;">
                                                        <td class="fw-bold">Tổng cộng (trước giảm & VAT):</td>
                                                        <td class="text-end fw-bold text-primary" id="subtotal_display">0 VNĐ</td>
                                                    </tr>
                                                    
                                                    <!-- Discount -->
                                                    <tr>
                                                        <td>Giảm giá:</td>
                                                        <td class="text-end" id="discount_display">0 VNĐ</td>
                                                    </tr>
                                                    
                                                    <!-- Tax -->
                                                    <tr>
                                                        <td>Thuế VAT (<span id="tax_rate_display">0</span>%):</td>
                                                        <td class="text-end" id="tax_display">0 VNĐ</td>
                                                    </tr>
                                                    
                                                    <!-- Total -->
                                                    <tr class="fw-bold border-top border-bottom">
                                                        <td>Thành tiền:</td>
                                                        <td class="text-end text-success fs-5" id="total_display">
                                                            <?php echo number_format($price); ?> VNĐ
                                                        </td>
                                                    </tr>
                                                </table>

                                                <div id="payment_breakdown" style="display: none;">
                                                    <h6 class="fw-bold text-info mt-3">Thanh toán theo giai đoạn</h6>
                                                    <table class="table table-sm table-bordered">
                                                        <tr class="table-light">
                                                            <td><strong>Hóa đơn 1 (50%):</strong></td>
                                                            <td class="text-end text-primary" id="first_payment_display">
                                                                <?php echo number_format(($price + ($price * 0.1)) * 0.5); ?>
                                                                VNĐ
                                                            </td>
                                                        </tr>
                                                        <tr class="table-light">
                                                            <td><strong>Hóa đơn 2 (50%):</strong></td>
                                                            <td class="text-end text-warning" id="second_payment_display">
                                                                <?php echo number_format(($price + ($price * 0.1)) * 0.5); ?>
                                                                VNĐ
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <small class="text-muted">
                                                        • Hóa đơn 1: Thanh toán trước khi bắt đầu thực hiện<br>
                                                        • Hóa đơn 2: Thanh toán sau khi hoàn thành dịch vụ
                                                    </small>
                                                </div>
                                            </div>

                                            <div class="mt-3">
                                                <h6 class="fw-bold">Thông tin bổ sung</h6>
                                                <small class="text-muted" id="additional_info">
                                                    • Hóa đơn sẽ có trạng thái "Nháp"<br>
                                                    • Cần thanh toán để chuyển sang "Đã thanh toán"<br>
                                                    • Sau khi thanh toán, dịch vụ sẽ chuyển sang "Đang thực hiện"
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const taxRate = document.getElementById('tax_rate');
        const discountAmount = document.getElementById('discount_amount');
        const paymentType = document.getElementById('payment_type');
        const pctInput = document.getElementById('installment_percentage');
        const customAmountInput = document.getElementById('custom_installment_amount');
        const pctRow = document.getElementById('percentage_row');
        const amountRow = document.getElementById('amount_row');

        const totalServiceValue = <?php echo (float)$total_service_value; ?>;
        const remainingServiceAmount = <?php echo (float)$remaining_service_amount; ?>;
        const installmentCount = <?php echo (int)$installment_count; ?>;

        function calculateProductsTotal() {
            let total = 0;

            // Sum hosting prices
            const hostingCheckboxes = document.querySelectorAll('.hosting-checkbox:checked');
            hostingCheckboxes.forEach(checkbox => {
                const price = parseFloat(checkbox.dataset.price) || 0;
                total += price;
            });

            // Sum domain prices
            const domainCheckboxes = document.querySelectorAll('.domain-checkbox:checked');
            domainCheckboxes.forEach(checkbox => {
                const price = parseFloat(checkbox.dataset.price) || 0;
                total += price;
            });

            return total;
        }

        function getActiveServicePrice() {
            const method = paymentType ? paymentType.value : 'full';
            if (method === 'percentage') {
                const pct = parseFloat(pctInput ? pctInput.value : 0) || 0;
                return Math.round(totalServiceValue * (pct / 100));
            } else if (method === 'amount') {
                return parseFloat(customAmountInput ? customAmountInput.value : 0) || 0;
            } else if (method === 'remaining') {
                return remainingServiceAmount;
            } else {
                return totalServiceValue;
            }
        }

        function updatePreview() {
            const tax = parseFloat(taxRate ? taxRate.value : 0) || 0;
            const discount = parseFloat(discountAmount ? discountAmount.value : 0) || 0;
            const paymentMethod = paymentType ? paymentType.value : 'full';

            // Toggle rows
            if (pctRow) pctRow.style.display = (paymentMethod === 'percentage') ? 'block' : 'none';
            if (amountRow) amountRow.style.display = (paymentMethod === 'amount') ? 'block' : 'none';

            const activeServicePrice = getActiveServicePrice();
            const productsTotal = calculateProductsTotal();
            const subtotal = activeServicePrice + productsTotal;
            const discountTotal = discount;
            
            // Calculate VAT: (subtotal - discount) * (tax_rate / 100)
            const taxableAmount = Math.max(0, subtotal - discountTotal);
            const taxAmount = Math.round(taxableAmount * (tax / 100));
            const total = taxableAmount + taxAmount;

            // Update service price display
            const servicePriceEl = document.getElementById('service_price');
            if (servicePriceEl) {
                servicePriceEl.textContent = activeServicePrice.toLocaleString('vi-VN') + ' VNĐ';
            }

            // Update products display
            const productsRow = document.getElementById('products_row');
            const subtotalRow = document.getElementById('subtotal_row');
            const productsDisplay = document.getElementById('products_display');
            const subtotalDisplay = document.getElementById('subtotal_display');

            if (productsTotal > 0) {
                if (productsRow) productsRow.style.display = 'table-row';
                if (subtotalRow) subtotalRow.style.display = 'table-row';
                if (productsDisplay) productsDisplay.textContent = productsTotal.toLocaleString('vi-VN') + ' VNĐ';
                if (subtotalDisplay) subtotalDisplay.textContent = subtotal.toLocaleString('vi-VN') + ' VNĐ';
            } else {
                if (productsRow) productsRow.style.display = 'none';
                if (subtotalRow) subtotalRow.style.display = 'none';
            }

            // Update tax rate display
            const taxRateDisplay = document.getElementById('tax_rate_display');
            if (taxRateDisplay) taxRateDisplay.textContent = tax.toFixed(1);
            const discountDisplay = document.getElementById('discount_display');
            if (discountDisplay) discountDisplay.textContent = discountTotal.toLocaleString('vi-VN') + ' VNĐ';
            const taxDisplay = document.getElementById('tax_display');
            if (taxDisplay) taxDisplay.textContent = taxAmount.toLocaleString('vi-VN') + ' VNĐ';
            const totalDisplay = document.getElementById('total_display');
            if (totalDisplay) totalDisplay.textContent = total.toLocaleString('vi-VN') + ' VNĐ';

            // Show/hide payment breakdown based on payment type
            const paymentBreakdown = document.getElementById('payment_breakdown');
            const additionalInfo = document.getElementById('additional_info');

            if (paymentMethod === 'partial_50') {
                if (paymentBreakdown) paymentBreakdown.style.display = 'block';

                const invoice1Subtotal = (totalServiceValue * 0.5) + productsTotal;
                const invoice1Discount = discount * 0.5;
                const invoice1TaxableAmount = Math.max(0, invoice1Subtotal - invoice1Discount);
                const invoice1Tax = Math.round(invoice1TaxableAmount * (tax / 100));
                const invoice1Total = invoice1TaxableAmount + invoice1Tax;
                
                const invoice2Subtotal = totalServiceValue * 0.5;
                const invoice2Discount = discount * 0.5;
                const invoice2TaxableAmount = Math.max(0, invoice2Subtotal - invoice2Discount);
                const invoice2Tax = Math.round(invoice2TaxableAmount * (tax / 100));
                const invoice2Total = invoice2TaxableAmount + invoice2Tax;

                const firstPaymentEl = document.getElementById('first_payment_display');
                if (firstPaymentEl) firstPaymentEl.textContent = invoice1Total.toLocaleString('vi-VN') + ' VNĐ';
                const secondPaymentEl = document.getElementById('second_payment_display');
                if (secondPaymentEl) secondPaymentEl.textContent = invoice2Total.toLocaleString('vi-VN') + ' VNĐ';

                let productNote = '';
                if (productsTotal > 0) {
                    productNote = '<br>• Hosting & Domain được thêm vào hóa đơn 1 (100%, thanh toán trước)';
                }

                if (additionalInfo) {
                    additionalInfo.innerHTML = `
                    • Sẽ tạo 2 hóa đơn riêng biệt<br>
                    • Hóa đơn 1: 50% dịch vụ + 100% hosting/domain (thanh toán trước)<br>
                    • Hóa đơn 2: 50% dịch vụ còn lại (thanh toán sau khi hoàn thành)<br>
                    • Dịch vụ chỉ bắt đầu sau khi thanh toán hóa đơn đầu tiên${productNote}
                    `;
                }
            } else {
                if (paymentBreakdown) paymentBreakdown.style.display = 'none';
                let productNote = '';
                if (productsTotal > 0) {
                    productNote = '<br>• Hosting & Domain được thêm vào cùng hóa đơn này';
                }
                if (additionalInfo) {
                    if (installmentCount > 0) {
                        additionalInfo.innerHTML = `
                        • Hóa đơn đợt thanh toán tiếp theo cho hợp đồng<br>
                        • Trạng thái sau khi tạo: "Nháp"<br>
                        • Bạn có thể xác nhận thanh toán trực tiếp ở trang Triển khai hoặc Bàn giao${productNote}
                        `;
                    } else {
                        additionalInfo.innerHTML = `
                        • Hóa đơn sẽ có trạng thái "Nháp"<br>
                        • Cần thanh toán hoặc kích hoạt bảo lãnh để chuyển sang "Đang thực hiện"${productNote}
                        `;
                    }
                }
            }
        }

        // Event listeners for form fields
        if (taxRate) taxRate.addEventListener('input', updatePreview);
        if (discountAmount) discountAmount.addEventListener('input', updatePreview);
        if (paymentType) paymentType.addEventListener('change', updatePreview);
        if (pctInput) pctInput.addEventListener('input', updatePreview);
        if (customAmountInput) customAmountInput.addEventListener('input', updatePreview);

        // Quick percentage buttons
        document.querySelectorAll('.quick-pct').forEach(btn => {
            btn.addEventListener('click', function () {
                const pct = this.getAttribute('data-pct');
                if (pctInput) {
                    pctInput.value = pct;
                    updatePreview();
                }
            });
        });

        // Product checkboxes
        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('hosting-checkbox') || e.target.classList.contains('domain-checkbox')) {
                updatePreview();
            }
        });

        // Initialize preview
        updatePreview();
    });
</script>

<?php get_footer(); ?>