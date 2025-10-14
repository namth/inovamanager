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
$websites_table = $wpdb->prefix . 'im_websites'; // Added websites table

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input data
    $action = sanitize_text_field($_POST['action']);
    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    $user_id = intval($_POST['user_id']);
    $invoice_date = sanitize_text_field($_POST['invoice_date']);
    $due_date = sanitize_text_field($_POST['due_date']);
    $notes = sanitize_textarea_field($_POST['notes']);
    $sub_total = floatval($_POST['sub_total']);
    $discount_total = floatval($_POST['discount_total']);
    $tax_amount = floatval($_POST['tax_amount']);
    $total_amount = floatval($_POST['total_amount']);

    // Generate invoice code if not provided
    $invoice_code = isset($_POST['invoice_code']) ? sanitize_text_field($_POST['invoice_code']) : 'INV-' . date('Ymd') . '-' . rand(1000, 9999);

    // Convert date format from DD/MM/YYYY to YYYY-MM-DD
    $invoice_date_parts = explode('/', $invoice_date);
    $formatted_invoice_date = $invoice_date_parts[2] . '-' . $invoice_date_parts[1] . '-' . $invoice_date_parts[0];
    
    $due_date_parts = explode('/', $due_date);
    $formatted_due_date = $due_date_parts[2] . '-' . $due_date_parts[1] . '-' . $due_date_parts[0];

    // Process invoice items
    $items = [];
    if (isset($_POST['service_type'])) {
        foreach ($_POST['service_type'] as $index => $service_type) {
            $item = [
                'service_type' => sanitize_text_field($service_type),
                'service_id' => intval($_POST['service_id'][$index]),
                'description' => sanitize_text_field($_POST['description'][$index]),
                'unit_price' => floatval($_POST['unit_price'][$index]),
                'quantity' => intval($_POST['quantity'][$index]),
                'item_total' => floatval($_POST['item_total'][$index]),
            ];
            
            // Process start and end dates if provided
            if (isset($_POST['start_date'][$index]) && !empty($_POST['start_date'][$index])) {
                $item['start_date'] = sanitize_text_field($_POST['start_date'][$index]);
            }
            
            if (isset($_POST['end_date'][$index]) && !empty($_POST['end_date'][$index])) {
                $item['end_date'] = sanitize_text_field($_POST['end_date'][$index]);
            }
            
            $items[] = $item;
        }
    }

    global $wpdb;
    $invoices_table = $wpdb->prefix . 'im_invoices';
    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';

    if ($action === 'add_invoice') {
        // Insert new invoice
        $wpdb->insert($invoices_table, [
            'invoice_code' => $invoice_code,
            'user_id' => $user_id,
            'invoice_date' => $formatted_invoice_date,
            'due_date' => $formatted_due_date,
            'sub_total' => $sub_total,
            'discount_total' => $discount_total,
            'tax_amount' => $tax_amount,
            'total_amount' => $total_amount,
            'notes' => $notes,
            'status' => isset($_POST['finalize']) && $_POST['finalize'] == 1 ? 'pending' : 'draft',
            'created_by_type' => 'admin',
            'created_by_id' => get_current_user_id(),
        ]);

        $new_invoice_id = $wpdb->insert_id;

        // Insert invoice items
        foreach ($items as $item) {
            $insert_data = [
                'invoice_id' => $new_invoice_id,
                'service_type' => $item['service_type'],
                'service_id' => $item['service_id'],
                'description' => $item['description'],
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'item_total' => $item['item_total'],
            ];
            
            // Add start and end dates if available
            if (isset($item['start_date'])) {
                $insert_data['start_date'] = $item['start_date'];
            }
            
            if (isset($item['end_date'])) {
                $insert_data['end_date'] = $item['end_date'];
            }
            
            $wpdb->insert($invoice_items_table, $insert_data);
        }

        echo '<div class="alert alert-success">Hóa đơn đã được tạo thành công!</div>';
        
        // Redirect to invoice detail page if finalized
        if (isset($_POST['finalize']) && $_POST['finalize'] == 1) {
            wp_redirect(home_url('/chi-tiet-hoa-don/?invoice_id=' . $new_invoice_id));
            exit;
        } elseif (isset($_POST['save_finalized']) && $_POST['save_finalized'] == 1) {
            // Update invoice status
            $wpdb->update($invoices_table, ['status' => 'pending'], ['id' => $new_invoice_id]);
            wp_redirect(home_url('/chi-tiet-hoa-don/?invoice_id=' . $new_invoice_id));
            exit;
        }
    } elseif ($action === 'edit_invoice' && $invoice_id > 0) {
        // Update existing invoice
        $wpdb->update($invoices_table, [
            'user_id' => $user_id,
            'invoice_date' => $formatted_invoice_date,
            'due_date' => $formatted_due_date,
            'sub_total' => $sub_total,
            'discount_total' => $discount_total,
            'tax_amount' => $tax_amount,
            'total_amount' => $total_amount,
            'notes' => $notes,
            'status' => isset($_POST['finalize']) && $_POST['finalize'] == 1 ? 'pending' : 'draft',
        ], ['id' => $invoice_id]);

        // Update invoice items
        $wpdb->delete($invoice_items_table, ['invoice_id' => $invoice_id]);
        foreach ($items as $item) {
            $insert_data = [
                'invoice_id' => $invoice_id,
                'service_type' => $item['service_type'],
                'service_id' => $item['service_id'],
                'description' => $item['description'],
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'item_total' => $item['item_total'],
            ];
            
            // Add start and end dates if available
            if (isset($item['start_date'])) {
                $insert_data['start_date'] = $item['start_date'];
            }
            
            if (isset($item['end_date'])) {
                $insert_data['end_date'] = $item['end_date'];
            }
            
            $wpdb->insert($invoice_items_table, $insert_data);
        }

        echo '<div class="alert alert-success">Hóa đơn đã được cập nhật thành công!</div>';
        
        // Redirect to invoice detail page if finalized
        if (isset($_POST['finalize']) && $_POST['finalize'] == 1) {
            wp_redirect(home_url('/chi-tiet-hoa-don/?invoice_id=' . $invoice_id));
            exit;
        } elseif (isset($_POST['save_finalized']) && $_POST['save_finalized'] == 1) {
            // Update invoice status
            $wpdb->update($invoices_table, ['status' => 'pending'], ['id' => $invoice_id]);
            wp_redirect(home_url('/chi-tiet-hoa-don/?invoice_id=' . $invoice_id));
            exit;
        }
    }
}

// Initialize variables
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0; // Added user_id from URL

// Unified bulk renewal parameters - support for all service types
$bulk_domains = isset($_GET['bulk_domains']) ? sanitize_text_field($_GET['bulk_domains']) : '';
$bulk_hostings = isset($_GET['bulk_hostings']) ? sanitize_text_field($_GET['bulk_hostings']) : '';
$bulk_maintenances = isset($_GET['bulk_maintenances']) ? sanitize_text_field($_GET['bulk_maintenances']) : '';
$bulk_websites = isset($_GET['bulk_websites']) ? sanitize_text_field($_GET['bulk_websites']) : '';

// Legacy single item parameters (for backward compatibility)
$domain_id = isset($_GET['domain_id']) ? intval($_GET['domain_id']) : 0;
$hosting_id = isset($_GET['hosting_id']) ? intval($_GET['hosting_id']) : 0;
$maintenance_id = isset($_GET['maintenance_id']) ? intval($_GET['maintenance_id']) : 0;
$website_id = isset($_GET['website_id']) ? intval($_GET['website_id']) : 0;

// Check if we're adding to an existing invoice
$existing_invoice = null;
if ($invoice_id > 0) {
    $existing_invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $invoice_table WHERE id = %d", $invoice_id));
    if ($existing_invoice) {
        $user_id = $existing_invoice->user_id;
    }
}

// Initialize variables for service data
$service_type = '';
$renewal_products = []; // Unified array to hold all renewal products

// Convert single items to bulk format for unified processing
if ($domain_id > 0) {
    $bulk_domains = (string)$domain_id;
}
if ($hosting_id > 0) {
    $bulk_hostings = (string)$hosting_id;
}
if ($maintenance_id > 0) {
    $bulk_maintenances = (string)$maintenance_id;
}
if ($website_id > 0) {
    $bulk_websites = (string)$website_id;
}

// Function to process bulk items for any service type
function processBulkItems($ids_string, $service_type, $wpdb, $table_name, $additional_tables = []) {
    if (empty($ids_string)) {
        return [];
    }
    
    $item_ids = array_map('intval', explode(',', $ids_string));
    $item_ids = array_filter($item_ids); // Remove any invalid IDs
    
    if (empty($item_ids)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($item_ids), '%d'));
    
    // Build query based on service type
    $base_query = "
        SELECT 
            s.*,
            u.id AS owner_id,
            u.name AS owner_name,
            u.email AS owner_email";
    
    $joins = "
        FROM $table_name s
        LEFT JOIN {$wpdb->prefix}im_users u ON s.owner_user_id = u.id";
    
    // Add additional joins based on service type
    foreach ($additional_tables as $alias => $join) {
        $base_query .= ", " . $join['select'];
        $joins .= " LEFT JOIN " . $join['table'] . " " . $alias . " ON " . $join['condition'];
    }
    
    $order_field = '';
    switch ($service_type) {
        case 'domain':
            $order_field = 's.domain_name';
            break;
        case 'hosting':
            $order_field = 's.hosting_code';
            break;
        case 'maintenance':
            $order_field = 's.order_code';
            break;
        case 'website':
            $order_field = 's.name';
            break;
    }
    
    $query = $base_query . $joins . " WHERE s.id IN ($placeholders) ORDER BY " . $order_field;
    
    return $wpdb->get_results($wpdb->prepare($query, ...$item_ids));
}

// Process bulk domains
if (!empty($bulk_domains)) {
    $domains_data = processBulkItems($bulk_domains, 'domain', $wpdb, $domains_table, [
        'pc' => [
            'select' => 'pc.name AS product_name, pc.renew_price AS product_price',
            'table' => "{$wpdb->prefix}im_product_catalog",
            'condition' => 's.product_catalog_id = pc.id'
        ]
    ]);

    if (!empty($domains_data)) {
        $service_type = count(explode(',', $bulk_domains)) > 1 ? 'bulk_domains' : 'domain';
        if (empty($user_id)) {
            $user_id = $domains_data[0]->owner_user_id;
        }

        foreach ($domains_data as $domain_data) {
            // Ensure we have required fields
            $domain_name = $domain_data->domain_name ?? 'Domain-' . $domain_data->id;
            // Get price from product catalog
            $price = $domain_data->product_price ?? 0;
            $period = $domain_data->registration_period_years ?? 1;
            $expiry_date = $domain_data->expiry_date ?? date('Y-m-d');

            $renewal_products[] = array(
                'type' => 'domain',
                'id' => $domain_data->id,
                'name' => $domain_name,
                'price' => $price,
                'period' => $period,
                'period_type' => 'years',
                'expiry_date' => $expiry_date,
                'description' => 'Gia hạn tên miền ' . $domain_name . ' - ' . $period . ' năm',
                'start_date' => $expiry_date,
                'end_date' => date('Y-m-d', strtotime($expiry_date . ' + ' . $period . ' years'))
            );
        }
    }
}

// Process bulk hostings
if (!empty($bulk_hostings)) {
    $hostings_data = processBulkItems($bulk_hostings, 'hosting', $wpdb, $hostings_table, [
        'pc' => [
            'select' => 'pc.name AS product_name, pc.renew_price AS product_price',
            'table' => "{$wpdb->prefix}im_product_catalog",
            'condition' => 's.product_catalog_id = pc.id'
        ]
    ]);
    
    if (!empty($hostings_data)) {
        $service_type = count(explode(',', $bulk_hostings)) > 1 ? 'bulk_hostings' : 'hosting';
        if (empty($user_id)) {
            $user_id = $hostings_data[0]->owner_user_id;
        }
        
        foreach ($hostings_data as $hosting_data) {
            // Use product_price from product catalog, fallback to price field if exists
            $hosting_price = $hosting_data->product_price ?? $hosting_data->price ?? 0;

            $renewal_products[] = array(
                'type' => 'hosting',
                'id' => $hosting_data->id,
                'name' => $hosting_data->hosting_code,
                'product_name' => $hosting_data->product_name,
                'price' => $hosting_price,
                'period' => $hosting_data->billing_cycle_months,
                'period_type' => 'months',
                'expiry_date' => $hosting_data->expiry_date,
                'description' => 'Gia hạn hosting ' . $hosting_data->product_name . ' - ' . ($hosting_data->billing_cycle_months / 12) . ' năm',
                'start_date' => $hosting_data->expiry_date,
                'end_date' => date('Y-m-d', strtotime($hosting_data->expiry_date . ' + ' . $hosting_data->billing_cycle_months . ' months'))
            );
        }
    }
}

// Process bulk maintenances
if (!empty($bulk_maintenances)) {
    $maintenances_data = processBulkItems($bulk_maintenances, 'maintenance', $wpdb, $maintenance_table, [
        'pc' => [
            'select' => 'pc.name AS product_name',
            'table' => "{$wpdb->prefix}im_product_catalog",
            'condition' => 's.product_catalog_id = pc.id'
        ]
    ]);
    
    if (!empty($maintenances_data)) {
        $service_type = count(explode(',', $bulk_maintenances)) > 1 ? 'bulk_maintenances' : 'maintenance';
        if (empty($user_id)) {
            $user_id = $maintenances_data[0]->owner_user_id;
        }
        
        foreach ($maintenances_data as $maintenance_data) {
            $renewal_products[] = array(
                'type' => 'maintenance',
                'id' => $maintenance_data->id,
                'name' => $maintenance_data->order_code,
                'product_name' => $maintenance_data->product_name,
                'price' => $maintenance_data->price_per_cycle,
                'period' => $maintenance_data->billing_cycle_months,
                'period_type' => 'months',
                'expiry_date' => $maintenance_data->expiry_date,
                'description' => 'Gia hạn gói bảo trì ' . $maintenance_data->product_name . ' - ' . ($maintenance_data->billing_cycle_months / 12) . ' năm',
                'start_date' => $maintenance_data->expiry_date,
                'end_date' => date('Y-m-d', strtotime($maintenance_data->expiry_date . ' + ' . $maintenance_data->billing_cycle_months . ' months'))
            );
        }
    }
}

// Process bulk websites (get all associated products)
if (!empty($bulk_websites)) {
    $websites_data = processBulkItems($bulk_websites, 'website', $wpdb, $websites_table);
    
    if (!empty($websites_data)) {
        $service_type = count(explode(',', $bulk_websites)) > 1 ? 'bulk_websites' : 'website';
        if (empty($user_id)) {
            $user_id = $websites_data[0]->owner_user_id;
        }
        
        foreach ($websites_data as $website_data) {
            // Get all products associated with this website
            // 1. Get domain
            if ($website_data->domain_id > 0) {
                $domain_data = $wpdb->get_row($wpdb->prepare("
                    SELECT d.*, pc.name AS product_name, pc.renew_price AS product_price
                    FROM $domains_table d
                    LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON d.product_catalog_id = pc.id
                    WHERE d.id = %d
                ", $website_data->domain_id));

                if ($domain_data) {
                    // Get price from product catalog
                    $domain_price = $domain_data->product_price ?? 0;

                    $renewal_products[] = array(
                        'type' => 'domain',
                        'id' => $domain_data->id,
                        'name' => $domain_data->domain_name,
                        'price' => $domain_price,
                        'period' => $domain_data->registration_period_years,
                        'period_type' => 'years',
                        'expiry_date' => $domain_data->expiry_date,
                        'description' => 'Gia hạn tên miền ' . $domain_data->domain_name . ' - ' . $domain_data->registration_period_years . ' năm',
                        'website_name' => $website_data->name
                    );
                }
            }
            
            // 2. Get hosting
            if ($website_data->hosting_id > 0) {
                $hosting_data = $wpdb->get_row($wpdb->prepare("
                    SELECT h.*, pc.name AS product_name, pc.renew_price AS product_price
                    FROM $hostings_table h
                    LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON h.product_catalog_id = pc.id
                    WHERE h.id = %d
                ", $website_data->hosting_id));
                
                if ($hosting_data) {
                    // Use product_price from product catalog, fallback to price field if exists
                    $hosting_price = $hosting_data->product_price ?? $hosting_data->price ?? 0;

                    $renewal_products[] = array(
                        'type' => 'hosting',
                        'id' => $hosting_data->id,
                        'name' => $hosting_data->hosting_code,
                        'product_name' => $hosting_data->product_name,
                        'price' => $hosting_price,
                        'period' => $hosting_data->billing_cycle_months,
                        'period_type' => 'months',
                        'expiry_date' => $hosting_data->expiry_date,
                        'description' => 'Gia hạn hosting ' . $hosting_data->product_name . ' - ' . ($hosting_data->billing_cycle_months / 12) . ' năm',
                        'website_name' => $website_data->name
                    );
                }
            }
            
            // 3. Get maintenance package
            if ($website_data->maintenance_package_id > 0) {
                $maintenance_data = $wpdb->get_row($wpdb->prepare("
                    SELECT m.*, pc.name AS product_name
                    FROM $maintenance_table m
                    LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON m.product_catalog_id = pc.id
                    WHERE m.id = %d
                ", $website_data->maintenance_package_id));
                
                if ($maintenance_data) {
                    $renewal_products[] = array(
                        'type' => 'maintenance',
                        'id' => $maintenance_data->id,
                        'name' => $maintenance_data->order_code,
                        'product_name' => $maintenance_data->product_name,
                        'price' => $maintenance_data->price_per_cycle,
                        'period' => $maintenance_data->billing_cycle_months,
                        'period_type' => 'months',
                        'expiry_date' => $maintenance_data->expiry_date,
                        'description' => 'Gia hạn gói bảo trì ' . $maintenance_data->product_name . ' - ' . ($maintenance_data->billing_cycle_months / 12) . ' năm',
                        'website_name' => $website_data->name
                    );
                }
            }
        }
    }
}

// Legacy variable definitions for backward compatibility
$product_data = null;
$website_data = null;

// Get all users for dropdown first (needed for legacy variables)
$users = $wpdb->get_results("SELECT id, name, user_code, email FROM $users_table ORDER BY name");

// If we have a single item renewal, populate the legacy variables
if (!empty($renewal_products) && count($renewal_products) == 1 && 
    in_array($service_type, ['domain', 'hosting', 'maintenance'])) {
    
    $single_product = $renewal_products[0];
    
    // Get owner name from users
    $owner_name = '';
    foreach ($users as $user) {
        if ($user->id == $user_id) {
            $owner_name = $user->name;
            break;
        }
    }
    
    // Create a mock object that matches the expected structure
    $product_data = (object) [
        'id' => $single_product['id'],
        'owner_name' => $owner_name,
        'owner_user_id' => $user_id,
        'price' => $single_product['price'],
        'expiry_date' => $single_product['expiry_date']
    ];
    
    // Add service-specific fields
    if ($service_type == 'domain') {
        $product_data->domain_name = $single_product['name'];
        $product_data->registration_period_years = $single_product['period'];
    } elseif ($service_type == 'hosting') {
        $product_data->hosting_code = $single_product['name'];
        $product_data->product_name = $single_product['product_name'] ?? '';
        $product_data->billing_cycle_months = $single_product['period'];
    } elseif ($service_type == 'maintenance') {
        $product_data->order_code = $single_product['name'];
        $product_data->product_name = $single_product['product_name'] ?? '';
        $product_data->billing_cycle_months = $single_product['period'];
    }
}

// If we have a single website renewal, populate website_data
if ($service_type == 'website' && !empty($bulk_websites)) {
    $website_ids = explode(',', $bulk_websites);
    if (count($website_ids) == 1) {
        $website_data = $wpdb->get_row($wpdb->prepare("
            SELECT w.*, u.name AS owner_name
            FROM $websites_table w
            LEFT JOIN {$wpdb->prefix}im_users u ON w.owner_user_id = u.id
            WHERE w.id = %d
        ", intval($website_ids[0])));
    }
}

// Get existing invoice items if we're editing an existing invoice
$invoice_items = array();
if ($invoice_id > 0) {
    $invoice_items = $wpdb->get_results($wpdb->prepare("
        SELECT 
            ii.*,
            CASE 
                WHEN ii.service_type = 'domain' THEN (SELECT domain_name FROM {$wpdb->prefix}im_domains WHERE id = ii.service_id)
                WHEN ii.service_type = 'hosting' THEN (SELECT hosting_code FROM {$wpdb->prefix}im_hostings WHERE id = ii.service_id)
                WHEN ii.service_type = 'maintenance' THEN (SELECT order_code FROM {$wpdb->prefix}im_maintenance_packages WHERE id = ii.service_id)
                ELSE ''
            END AS service_name
        FROM 
            $invoice_items_table ii
        WHERE 
            ii.invoice_id = %d
    ", $invoice_id));
}

// Calculate total
$sub_total = 0;
foreach ($invoice_items as $item) {
    $sub_total += $item->item_total;
}

// Check if the invoice is being finalized
$is_finalizing = isset($_GET['finalize']) && $_GET['finalize'] == 1;

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
                        <input type="hidden" name="invoice_code" value="<?php echo $existing_invoice ? esc_attr($existing_invoice->invoice_code) : 'INV-' . date('Ymd') . '-' . rand(1000, 9999); ?>">
                        
                        <?php if (($product_data || $website_id > 0 || !empty($renewal_products)) && !$existing_invoice): ?>
                        <div class="alert alert-info">
                            <i class="ph ph-info me-2"></i>
                            Đang tạo hóa đơn cho <?php 
                            if ($service_type == 'domain'):
                                echo 'tên miền <strong>' . esc_html($product_data->domain_name) . '</strong>';
                            elseif ($service_type == 'hosting'):
                                echo 'hosting <strong>' . esc_html($product_data->hosting_code) . '</strong>';
                            elseif ($service_type == 'maintenance'):
                                echo 'gói bảo trì <strong>' . esc_html($product_data->order_code) . '</strong>';
                            elseif ($service_type == 'website'):
                                echo 'website <strong>' . esc_html($website_data->name) . '</strong>';
                            elseif ($service_type == 'bulk_domains'):
                                $domain_count = count(array_filter($renewal_products, function($p) { return $p['type'] == 'domain'; }));
                                echo 'gia hạn <strong>' . $domain_count . ' tên miền</strong>';
                            elseif ($service_type == 'bulk_hostings'):
                                $hosting_count = count(array_filter($renewal_products, function($p) { return $p['type'] == 'hosting'; }));
                                echo 'gia hạn <strong>' . $hosting_count . ' hosting</strong>';
                            elseif ($service_type == 'bulk_maintenances'):
                                $maintenance_count = count(array_filter($renewal_products, function($p) { return $p['type'] == 'maintenance'; }));
                                echo 'gia hạn <strong>' . $maintenance_count . ' gói bảo trì</strong>';
                            elseif ($service_type == 'bulk_websites'):
                                $website_count = count(explode(',', $bulk_websites));
                                echo 'gia hạn <strong>' . $website_count . ' website</strong>';
                            endif;
                            ?> 
                            <?php if (!in_array($service_type, ['bulk_domains', 'bulk_hostings', 'bulk_maintenances', 'bulk_websites'])): ?>
                            của khách hàng <strong><?php echo $service_type == 'website' ? esc_html($website_data->owner_name) : esc_html($product_data->owner_name); ?></strong>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card mb-4">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">Thông tin hóa đơn</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Khách hàng <span class="text-danger">*</span></label>
                                                <select name="user_id" id="user_id" class="form-select js-example-basic-single" required <?php echo ($existing_invoice || $product_data || $website_id > 0 || in_array($service_type, ['bulk_domains', 'bulk_hostings', 'bulk_maintenances', 'bulk_websites'])) ? 'disabled' : ''; ?>>
                                                    <option value="">-- Chọn khách hàng --</option>
                                                    <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user->id; ?>" <?php selected($user_id, $user->id); ?>>
                                                        <?php echo esc_html($user->user_code . ' - ' . $user->name); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if ($existing_invoice || $product_data || $website_id > 0 || in_array($service_type, ['bulk_domains', 'bulk_hostings', 'bulk_maintenances', 'bulk_websites'])): ?>
                                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Mã hóa đơn</label>
                                                <input type="text" class="form-control" name="invoice_code_display" value="<?php echo $existing_invoice ? esc_attr($existing_invoice->invoice_code) : 'INV-' . date('Ymd') . '-' . rand(1000, 9999); ?>" readonly>
                                                <small class="text-muted">Mã hóa đơn được tạo tự động</small>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Ngày xuất hóa đơn <span class="text-danger">*</span></label>
                                                <div class="input-group datepicker">
                                                    <input type="text" class="form-control" name="invoice_date" value="<?php echo $existing_invoice ? date('d/m/Y', strtotime($existing_invoice->invoice_date)) : date('d/m/Y'); ?>" required>
                                                    <span class="input-group-text bg-primary text-white">
                                                        <i class="ph ph-calendar-blank"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Hạn thanh toán <span class="text-danger">*</span></label>
                                                <div class="input-group datepicker">
                                                    <input type="text" class="form-control" name="due_date" value="<?php echo $existing_invoice ? date('d/m/Y', strtotime($existing_invoice->due_date)) : date('d/m/Y', strtotime('+7 days')); ?>" required>
                                                    <span class="input-group-text bg-primary text-white">
                                                        <i class="ph ph-calendar-blank"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Ghi chú</label>
                                            <textarea class="form-control" name="notes" rows="3"><?php echo $existing_invoice ? esc_textarea($existing_invoice->notes) : ''; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Chi tiết hóa đơn</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table" id="invoice-items-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 35%">Dịch vụ</th>
                                                        <th style="width: 30%">Mô tả</th>
                                                        <th style="width: 15%">Đơn giá</th>
                                                        <th style="width: 10%">SL</th>
                                                        <th style="width: 15%">Thành tiền</th>
                                                        <th style="width: 5%">Xóa</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    // Display existing items
                                                    if (!empty($invoice_items)): 
                                                        foreach ($invoice_items as $index => $item):
                                                    ?>
                                                    <tr class="invoice-item">
                                                        <td>
                                                            <input type="hidden" name="item_id[]" value="<?php echo $item->id; ?>">
                                                            <input type="hidden" name="service_type[]" value="<?php echo $item->service_type; ?>">
                                                            <input type="hidden" name="service_id[]" value="<?php echo $item->service_id; ?>">
                                                            <strong><?php echo ucfirst($item->service_type); ?>:</strong> <?php echo esc_html($item->service_name); ?>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control" name="description[]" value="<?php echo esc_attr($item->description); ?>" required>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control unit-price" name="unit_price[]" value="<?php echo number_format($item->unit_price, 0, '', ''); ?>" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control quantity" name="quantity[]" value="<?php echo $item->quantity; ?>" min="1" required>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control item-total" name="item_total[]" value="<?php echo number_format($item->item_total, 0, '', ''); ?>" readonly>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                                                <i class="ph ph-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                        endforeach;
                                                    endif;
                                                    
                                                    // Add new item from URL if provided for single product (domain/hosting/maintenance)
                                                    if ($product_data && !$existing_invoice && $service_type != 'website'):
                                                        // Prepare description based on service type
                                                        if ($service_type == 'domain'):
                                                            $description = 'Gia hạn tên miền ' . $product_data->domain_name . ' - ' . $product_data->registration_period_years . ' năm';
                                                            $start_date = $product_data->expiry_date;
                                                            $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $product_data->registration_period_years . ' years'));
                                                            $unit_price = $product_data->price;
                                                        elseif ($service_type == 'hosting'):
                                                            $description = 'Gia hạn hosting ' . $product_data->product_name . ' - ' . ($product_data->billing_cycle_months / 12) . ' năm';
                                                            $start_date = $product_data->expiry_date;
                                                            $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $product_data->billing_cycle_months . ' months'));
                                                            $unit_price = $product_data->price;
                                                        elseif ($service_type == 'maintenance'):
                                                            $description = 'Gia hạn gói bảo trì ' . $product_data->product_name . ' - ' . ($product_data->billing_cycle_months / 12) . ' năm';
                                                            $start_date = $product_data->expiry_date;
                                                            $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $product_data->billing_cycle_months . ' months'));
                                                            $unit_price = $product_data->price;
                                                        endif;
                                                    ?>
                                                    <tr class="invoice-item">
                                                        <td>
                                                            <input type="hidden" name="item_id[]" value="0">
                                                            <input type="hidden" name="service_type[]" value="<?php echo $service_type; ?>">
                                                            <input type="hidden" name="service_id[]" value="<?php echo $service_type == 'domain' ? $domain_id : ($service_type == 'hosting' ? $hosting_id : $maintenance_id); ?>">
                                                            <input type="hidden" name="start_date[]" value="<?php echo $start_date; ?>">
                                                            <input type="hidden" name="end_date[]" value="<?php echo $end_date; ?>">
                                                            <strong><?php echo ucfirst($service_type); ?>:</strong> 
                                                            <?php 
                                                            if ($service_type == 'domain'):
                                                                echo esc_html($product_data->domain_name);
                                                            elseif ($service_type == 'hosting'):
                                                                echo esc_html($product_data->hosting_code);
                                                            elseif ($service_type == 'maintenance'):
                                                                echo esc_html($product_data->order_code);
                                                            endif;
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control" name="description[]" value="<?php echo esc_attr($description); ?>" required>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control unit-price" name="unit_price[]" value="<?php echo number_format($unit_price, 0, '', ''); ?>" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control quantity" name="quantity[]" value="1" min="1" required>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control item-total" name="item_total[]" value="<?php echo number_format($unit_price, 0, '', ''); ?>" readonly>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                                                <i class="ph ph-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                    elseif (!empty($renewal_products) && !$existing_invoice):
                                                        // Add all renewal products (unified system for all service types)
                                                        foreach ($renewal_products as $product):
                                                            // Calculate end date - use pre-calculated if available, otherwise calculate
                                                            if (isset($product['end_date'])) {
                                                                $end_date = $product['end_date'];
                                                            } else {
                                                                if ($product['period_type'] == 'years') {
                                                                    $end_date = date('Y-m-d', strtotime($product['expiry_date'] . ' + ' . $product['period'] . ' years'));
                                                                } else {
                                                                    $end_date = date('Y-m-d', strtotime($product['expiry_date'] . ' + ' . $product['period'] . ' months'));
                                                                }
                                                            }
                                                            
                                                            // Use pre-calculated start date if available
                                                            $start_date = isset($product['start_date']) ? $product['start_date'] : $product['expiry_date'];
                                                    ?>
                                                    <tr class="invoice-item">
                                                        <td>
                                                            <input type="hidden" name="item_id[]" value="0">
                                                            <input type="hidden" name="service_type[]" value="<?php echo $product['type']; ?>">
                                                            <input type="hidden" name="service_id[]" value="<?php echo $product['id']; ?>">
                                                            <input type="hidden" name="start_date[]" value="<?php echo $start_date; ?>">
                                                            <input type="hidden" name="end_date[]" value="<?php echo $end_date; ?>">
                                                            <strong><?php echo ucfirst($product['type']); ?>:</strong> 
                                                            <?php echo esc_html($product['name']); ?>
                                                            <?php if (isset($product['website_name'])): ?>
                                                                <br><small class="text-muted">Website: <?php echo esc_html($product['website_name']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control" name="description[]" value="<?php echo esc_attr($product['description']); ?>" required>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control unit-price" name="unit_price[]" value="<?php echo number_format($product['price'], 0, '', ''); ?>" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control quantity" name="quantity[]" value="1" min="1" required>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control item-total" name="item_total[]" value="<?php echo number_format($product['price'], 0, '', ''); ?>" readonly>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                                                <i class="ph ph-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                        endforeach;
                                                    endif; 
                                                    ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="6">
                                                            <div class="text-center">
                                                                <a href="#" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                                                    <i class="ph ph-plus-circle me-1"></i> Thêm dịch vụ
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card mb-4">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">Tóm tắt hóa đơn</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Tổng tiền dịch vụ:</span>
                                            <span id="summary-subtotal"><?php echo number_format($sub_total, 0, ',', '.'); ?> VNĐ</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Chiết khấu:</span>
                                            <div class="input-group" style="width: 140px;">
                                                <input type="number" class="form-control" name="discount_total" id="discount-amount" value="<?php echo $existing_invoice ? $existing_invoice->discount_total : 0; ?>" min="0">
                                                <span class="input-group-text">VNĐ</span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Thuế (VAT):</span>
                                            <div class="input-group" style="width: 140px;">
                                                <input type="number" class="form-control" name="tax_amount" id="tax-amount" value="<?php echo $existing_invoice ? $existing_invoice->tax_amount : 0; ?>" min="0">
                                                <span class="input-group-text">VNĐ</span>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between mb-3 fw-bold">
                                            <span>Thành tiền:</span>
                                            <span id="summary-total"><?php echo number_format($sub_total, 0, ',', '.'); ?> VNĐ</span>
                                            <input type="hidden" name="sub_total" id="sub-total-input" value="<?php echo $sub_total; ?>">
                                            <input type="hidden" name="total_amount" id="total-amount-input" value="<?php echo $sub_total; ?>">
                                        </div>
                                        
                                        <?php if ($is_finalizing): ?>
                                        <div class="mt-4">
                                            <label class="form-label">Phương thức thanh toán <span class="text-danger">*</span></label>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="payment_method" id="payment-bank" value="bank_transfer" checked>
                                                <label class="form-check-label" for="payment-bank">
                                                    <i class="ph ph-bank me-1"></i> Chuyển khoản ngân hàng
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" id="payment-qr" value="qr_code">
                                                <label class="form-check-label" for="payment-qr">
                                                    <i class="ph ph-qr-code me-1"></i> Quét mã QR
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div id="bank-info" class="mt-3 p-3 bg-light rounded">
                                            <h6><i class="ph ph-info me-1"></i> Thông tin chuyển khoản</h6>
                                            <p class="mb-1"><strong>Ngân hàng:</strong> VietcomBank</p>
                                            <p class="mb-1"><strong>Số tài khoản:</strong> 1234567890</p>
                                            <p class="mb-1"><strong>Chủ tài khoản:</strong> INOVA COMPANY</p>
                                            <p class="mb-0"><strong>Nội dung CK:</strong> <span class="text-danger"><?php echo $existing_invoice ? $existing_invoice->invoice_code : 'INV-' . date('Ymd') . '-' . rand(1000, 9999); ?></span></p>
                                        </div>
                                        
                                        <div id="qr-info" class="mt-3 p-3 bg-light rounded d-none">
                                            <h6><i class="ph ph-qr-code me-1"></i> Thanh toán bằng mã QR</h6>
                                            <div class="text-center">
                                                <img src="<?php echo get_template_directory_uri(); ?>/assets/images/sample-qr.png" alt="QR Code" class="img-fluid" style="max-width: 200px;">
                                                <p class="mt-2 mb-0">Quét mã QR để thanh toán</p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-white text-end pt-3">
                                    <?php if (!$is_finalizing): ?>
                                        <button type="submit" name="save_draft" class="btn btn-secondary me-2">
                                            <i class="ph ph-floppy-disk me-1"></i> Lưu nháp
                                        </button>
                                        <button type="submit" name="finalize" class="btn btn-primary" value="1">
                                            <i class="ph ph-check-circle me-1"></i> Hoàn tất hóa đơn
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="save_finalized" class="btn btn-success" value="1">
                                            <i class="ph ph-check-circle me-1"></i> Xác nhận và thanh toán
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Item -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Thêm dịch vụ vào hóa đơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <label class="form-label">Loại dịch vụ</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="service_type_select" id="type-domain" value="domain" autocomplete="off" checked>
                        <label class="btn btn-outline-primary" for="type-domain">
                            <i class="ph ph-globe me-1"></i> Tên miền
                        </label>
                        
                        <input type="radio" class="btn-check" name="service_type_select" id="type-hosting" value="hosting" autocomplete="off">
                        <label class="btn btn-outline-primary" for="type-hosting">
                            <i class="ph ph-database me-1"></i> Hosting
                        </label>
                        
                        <input type="radio" class="btn-check" name="service_type_select" id="type-maintenance" value="maintenance" autocomplete="off">
                        <label class="btn btn-outline-primary" for="type-maintenance">
                            <i class="ph ph-wrench me-1"></i> Bảo trì
                        </label>
                        
                        <input type="radio" class="btn-check" name="service_type_select" id="type-website" value="website" autocomplete="off">
                        <label class="btn btn-outline-primary" for="type-website">
                            <i class="ph ph-globe-hemisphere-west me-1"></i> Website
                        </label>
                    </div>
                </div>
                
                <!-- Domain Selection -->
                <div id="domain-select" class="service-select-container">
                    <div class="mb-3">
                        <label class="form-label">Chọn tên miền</label>
                        <select class="form-select js-example-basic-single" id="domain-dropdown">
                            <option value="">-- Chọn tên miền --</option>
                            <?php
                            // Get available domains for this customer with price from product catalog
                            if ($user_id > 0) {
                                $domains = $wpdb->get_results($wpdb->prepare("
                                    SELECT
                                        d.id,
                                        d.domain_name,
                                        d.registration_period_years,
                                        d.expiry_date,
                                        pc.renew_price AS price
                                    FROM {$wpdb->prefix}im_domains d
                                    LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON d.product_catalog_id = pc.id
                                    WHERE d.owner_user_id = %d
                                    ORDER BY d.domain_name
                                ", $user_id));

                                foreach ($domains as $domain) {
                                    echo '<option value="' . $domain->id . '"
                                        data-price="' . ($domain->price ?? 0) . '"
                                        data-expiry="' . $domain->expiry_date . '"
                                        data-period="' . $domain->registration_period_years . '">
                                        ' . esc_html($domain->domain_name) . '
                                    </option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <!-- Hosting Selection -->
                <div id="hosting-select" class="service-select-container d-none">
                    <div class="mb-3">
                        <label class="form-label">Chọn hosting</label>
                        <select class="form-select js-example-basic-single" id="hosting-dropdown">
                            <option value="">-- Chọn hosting --</option>
                            <?php
                            // Get available hostings for this customer
                            if ($user_id > 0) {
                                $hostings = $wpdb->get_results($wpdb->prepare("
                                    SELECT h.id, h.hosting_code, pc.renew_price as price, h.billing_cycle_months, h.expiry_date, pc.name as product_name
                                    FROM {$wpdb->prefix}im_hostings h
                                    LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON h.product_catalog_id = pc.id
                                    WHERE h.owner_user_id = %d
                                    ORDER BY h.hosting_code
                                ", $user_id));
                                
                                foreach ($hostings as $hosting) {
                                    echo '<option value="' . $hosting->id . '" 
                                        data-price="' . $hosting->price . '"
                                        data-expiry="' . $hosting->expiry_date . '"
                                        data-period="' . $hosting->billing_cycle_months . '"
                                        data-name="' . esc_html($hosting->product_name) . '">
                                        ' . esc_html($hosting->hosting_code) . ' - ' . esc_html($hosting->product_name) . '
                                    </option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <!-- Maintenance Selection -->
                <div id="maintenance-select" class="service-select-container d-none">
                    <div class="mb-3">
                        <label class="form-label">Chọn gói bảo trì</label>
                        <select class="form-select js-example-basic-single" id="maintenance-dropdown">
                            <option value="">-- Chọn gói bảo trì --</option>
                            <?php
                            // Get available maintenance packages for this customer
                            if ($user_id > 0) {
                                $maintenance_packages = $wpdb->get_results($wpdb->prepare("
                                    SELECT m.id, m.order_code, m.price_per_cycle, m.billing_cycle_months, m.expiry_date, pc.name as product_name
                                    FROM {$wpdb->prefix}im_maintenance_packages m
                                    LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON m.product_catalog_id = pc.id
                                    WHERE m.owner_user_id = %d
                                    ORDER BY m.order_code
                                ", $user_id));
                                
                                foreach ($maintenance_packages as $maintenance) {
                                    echo '<option value="' . $maintenance->id . '" 
                                        data-price="' . $maintenance->price_per_cycle . '"
                                        data-expiry="' . $maintenance->expiry_date . '"
                                        data-period="' . $maintenance->billing_cycle_months . '"
                                        data-name="' . esc_html($maintenance->product_name) . '">
                                        ' . esc_html($maintenance->order_code) . ' - ' . esc_html($maintenance->product_name) . '
                                    </option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <!-- Website Selection -->
                <div id="website-select" class="service-select-container d-none">
                    <div class="mb-3">
                        <label class="form-label">Chọn website</label>
                        <select class="form-select js-example-basic-single" id="website-dropdown">
                            <option value="">-- Chọn website --</option>
                            <?php
                            // Get available websites for this customer
                            if ($user_id > 0) {
                                $websites = $wpdb->get_results($wpdb->prepare("
                                    SELECT w.id, w.name, w.domain_id, w.hosting_id, w.maintenance_package_id
                                    FROM {$wpdb->prefix}im_websites w
                                    WHERE w.owner_user_id = %d
                                    ORDER BY w.name
                                ", $user_id));
                                
                                foreach ($websites as $website) {
                                    echo '<option value="' . $website->id . '" 
                                        data-domain-id="' . $website->domain_id . '"
                                        data-hosting-id="' . $website->hosting_id . '"
                                        data-maintenance-id="' . $website->maintenance_package_id . '">
                                        ' . esc_html($website->name) . '
                                    </option>';
                                }
                            }
                            ?>
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

<script>
jQuery(document).ready(function($) {
    // Format prices with thousand separators
    function formatPrice(price) {
        return parseFloat(price).toLocaleString('vi-VN').replace(/\./g, ',');
    }
    
    // Unformat price from thousand separators to number
    function unformatPrice(formattedPrice) {
        return parseInt(formattedPrice.replace(/\./g, '').replace(/,/g, ''));
    }
    
    // Calculate line total when unit price or quantity changes
    $(document).on('input', '.unit-price, .quantity', function() {
        var row = $(this).closest('.invoice-item');
        var unitPrice = row.find('.unit-price').val().replace(/,/g, '');
        var quantity = row.find('.quantity').val();
        
        var total = unitPrice * quantity;
        row.find('.item-total').val(total);
        
        updateTotals();
    });
    
    // Format unit price on input
    $(document).on('blur', '.unit-price', function() {
        var value = $(this).val().replace(/,/g, '');
        if (!isNaN(value) && value !== '') {
            $(this).val(parseInt(value).toLocaleString('vi-VN').replace(/\./g, ''));
        }
    });
    
    // Remove invoice item
    $(document).on('click', '.remove-item', function() {
        $(this).closest('.invoice-item').remove();
        updateTotals();
    });
    
    // Update totals when discount or tax changes
    $('#discount-amount, #tax-amount').on('input', function() {
        updateTotals();
    });
    
    // Toggle payment method info
    $('input[name="payment_method"]').on('change', function() {
        if ($(this).val() === 'bank_transfer') {
            $('#bank-info').removeClass('d-none');
            $('#qr-info').addClass('d-none');
        } else {
            $('#bank-info').addClass('d-none');
            $('#qr-info').removeClass('d-none');
        }
    });
    
    // Switch between service types in the modal
    $('input[name="service_type_select"]').on('change', function() {
        $('.service-select-container').addClass('d-none');
        $('#' + $(this).val() + '-select').removeClass('d-none');
    });
    
    // Add service from modal to invoice
    $('#add-service-btn').on('click', function() {
        var serviceType = $('input[name="service_type_select"]:checked').val();
        var serviceId, serviceName, price, description, startDate, endDate;
        
        if (serviceType === 'domain') {
            var selected = $('#domain-dropdown option:selected');
            if (selected.val() === '') {
                alert('Vui lòng chọn tên miền');
                return;
            }
            serviceId = selected.val();
            serviceName = selected.text();
            price = selected.data('price');
            var expiryDate = new Date(selected.data('expiry'));
            var period = selected.data('period');
            startDate = selected.data('expiry');
            
            // Calculate new expiry date by adding years
            var endDateObj = new Date(expiryDate);
            endDateObj.setFullYear(endDateObj.getFullYear() + parseInt(period));
            endDate = endDateObj.toISOString().split('T')[0];
            
            description = 'Gia hạn tên miền ' + serviceName + ' - ' + period + ' năm';
        } 
        else if (serviceType === 'hosting') {
            var selected = $('#hosting-dropdown option:selected');
            if (selected.val() === '') {
                alert('Vui lòng chọn hosting');
                return;
            }
            serviceId = selected.val();
            serviceName = selected.text().trim();
            price = selected.data('price');
            var expiryDate = new Date(selected.data('expiry'));
            var period = selected.data('period');
            startDate = selected.data('expiry');
            
            // Calculate new expiry date by adding months
            var endDateObj = new Date(expiryDate);
            endDateObj.setMonth(endDateObj.getMonth() + parseInt(period));
            endDate = endDateObj.toISOString().split('T')[0];
            
            description = 'Gia hạn hosting ' + selected.data('name') + ' - ' + (period/12) + ' năm';
        } 
        else if (serviceType === 'maintenance') {
            var selected = $('#maintenance-dropdown option:selected');
            if (selected.val() === '') {
                alert('Vui lòng chọn gói bảo trì');
                return;
            }
            serviceId = selected.val();
            serviceName = selected.text().trim();
            price = selected.data('price');
            var expiryDate = new Date(selected.data('expiry'));
            var period = selected.data('period');
            startDate = selected.data('expiry');
            
            // Calculate new expiry date by adding months
            var endDateObj = new Date(expiryDate);
            endDateObj.setMonth(endDateObj.getMonth() + parseInt(period));
            endDate = endDateObj.toISOString().split('T')[0];
            
            description = 'Gia hạn gói bảo trì ' + selected.data('name') + ' - ' + (period/12) + ' năm';
        }
        else if (serviceType === 'website') {
            var selected = $('#website-dropdown option:selected');
            if (selected.val() === '') {
                alert('Vui lòng chọn website');
                return;
            }
            
            // For websites, we'll redirect to the invoice page with the website ID
            var websiteId = selected.val();
            var currentUrl = window.location.href.split('?')[0];
            window.location.href = currentUrl + '?website_id=' + websiteId;
            return;
        }
        
        // Add new row to invoice items table
        var newRow = `
            <tr class="invoice-item">
                <td>
                    <input type="hidden" name="item_id[]" value="0">
                    <input type="hidden" name="service_type[]" value="${serviceType}">
                    <input type="hidden" name="service_id[]" value="${serviceId}">
                    <input type="hidden" name="start_date[]" value="${startDate}">
                    <input type="hidden" name="end_date[]" value="${endDate}">
                    <strong>${serviceType.charAt(0).toUpperCase() + serviceType.slice(1)}:</strong> ${serviceName}
                </td>
                <td>
                    <input type="text" class="form-control" name="description[]" value="${description}" required>
                </td>
                <td>
                    <input type="text" class="form-control unit-price" name="unit_price[]" value="${price}" required>
                </td>
                <td>
                    <input type="number" class="form-control quantity" name="quantity[]" value="1" min="1" required>
                </td>
                <td>
                    <input type="text" class="form-control item-total" name="item_total[]" value="${price}" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-item">
                        <i class="ph ph-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        
        $('#invoice-items-table tbody').append(newRow);
        updateTotals();
        
        // Close modal
        $('#addItemModal').modal('hide');
    });
    
    // Update invoice totals
    function updateTotals() {
        var subTotal = 0;
        
        // Sum all item totals
        $('.item-total').each(function() {
            var itemTotal = $(this).val().replace(/,/g, '');
            subTotal += parseInt(itemTotal) || 0;
        });
        
        // Get discount and tax
        var discount = parseInt($('#discount-amount').val()) || 0;
        var tax = parseInt($('#tax-amount').val()) || 0;
        
        // Calculate total
        var total = subTotal - discount + tax;
        
        // Update summary display
        $('#summary-subtotal').text(formatPrice(subTotal) + ' VNĐ');
        $('#summary-total').text(formatPrice(total) + ' VNĐ');
        
        // Update hidden inputs for form submission
        $('#sub-total-input').val(subTotal);
        $('#total-amount-input').val(total);
    }
    
    // Initialize Select2
    if($.fn.select2) {
        $('.js-example-basic-single').select2({
            dropdownParent: $('#addItemModal') // Fix for select2 in modal
        });
    }
    
    // Initialize datepickers
    if($.fn.datepicker) {
        $('.datepicker input').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true
        });
    }
    
    // Initial calculation
    updateTotals();
    
    // Customer change event
    $('#user_id').on('change', function() {
        // When changing customer, we would need to reload the page to get their services
        if ($(this).val() !== '') {
            var url = new URL(window.location.href);
            url.searchParams.set('user_id', $(this).val());
            window.location.href = url.toString();
        }
    });
});
</script>

<?php get_footer(); ?>