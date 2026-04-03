<?php
/* AJAX Handlers - Imported from functions.php */

function add_to_cart_ajax()
{
    if (!isset($_POST['service_type']) || !isset($_POST['service_id'])) {
        echo json_encode(array(
            'status' => false,
            'message' => 'Thiếu thông tin dịch vụ'
        ));
        exit;
    }

    $service_type = sanitize_text_field($_POST['service_type']);
    $service_id = intval($_POST['service_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    if (!in_array($service_type, ['domain', 'hosting', 'maintenance'])) {
        echo json_encode(array(
            'status' => false,
            'message' => 'Loại dịch vụ không hợp lệ'
        ));
        exit;
    }

    global $wpdb;
    $cart_table = $wpdb->prefix . 'im_cart';

    // Get user_id from service
    $user_id = 0;
    switch ($service_type) {
        case 'domain':
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT owner_user_id FROM {$wpdb->prefix}im_domains WHERE id = %d",
                $service_id
            ));
            break;
        case 'hosting':
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT owner_user_id FROM {$wpdb->prefix}im_hostings WHERE id = %d",
                $service_id
            ));
            break;
        case 'maintenance':
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT owner_user_id FROM {$wpdb->prefix}im_maintenance_packages WHERE id = %d",
                $service_id
            ));
            break;
    }

    if (!$user_id) {
        echo json_encode(array(
            'status' => false,
            'message' => 'Không tìm thấy thông tin khách hàng'
        ));
        exit;
    }

    // Check if item already in cart
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $cart_table WHERE user_id = %d AND service_type = %s AND service_id = %d",
        $user_id,
        $service_type,
        $service_id
    ));

    if ($existing) {
        // Update quantity if already exists
        $wpdb->update(
            $cart_table,
            array('quantity' => $existing->quantity + $quantity),
            array('id' => $existing->id)
        );
        $message = 'Đã cập nhật số lượng trong giỏ hàng';
    } else {
        // Insert new cart item
        $result = $wpdb->insert($cart_table, array(
            'user_id' => $user_id,
            'service_type' => $service_type,
            'service_id' => $service_id,
            'quantity' => $quantity
        ));

        if (!$result) {
            echo json_encode(array(
                'status' => false,
                'message' => 'Không thể thêm vào giỏ hàng'
            ));
            exit;
        }
        $message = 'Đã thêm vào giỏ hàng';
    }

    // Get cart count
    $cart_count = $wpdb->get_var("SELECT COUNT(*) FROM $cart_table");

    echo json_encode(array(
        'status' => true,
        'message' => $message,
        'cart_count' => $cart_count
    ));
    exit;
}

function add_website_to_cart_ajax()
{
    if (!isset($_POST['website_id'])) {
        echo json_encode(array(
            'status' => false,
            'message' => 'Thiếu ID website'
        ));
        exit;
    }

    $website_id = intval($_POST['website_id']);
    global $wpdb;

    $websites_table = $wpdb->prefix . 'im_websites';
    $domains_table = $wpdb->prefix . 'im_domains';
    $cart_table = $wpdb->prefix . 'im_cart';

    // Get website info with its linked service IDs
    $website = $wpdb->get_row($wpdb->prepare(
        "SELECT id, owner_user_id, domain_id, hosting_id, maintenance_package_id 
         FROM $websites_table WHERE id = %d",
        $website_id
    ));

    if (!$website) {
        echo json_encode(array(
            'status' => false,
            'message' => 'Website không tồn tại'
        ));
        exit;
    }

    $items_to_add = [];

    // 1. Domain - Only if managed by INOVA
    if ($website->domain_id) {
        $managed_by_inova = $wpdb->get_var($wpdb->prepare(
            "SELECT managed_by_inova FROM $domains_table WHERE id = %d",
            $website->domain_id
        ));
        if ($managed_by_inova) {
            $items_to_add[] = [
                'type' => 'domain',
                'id' => $website->domain_id,
                'user_id' => $website->owner_user_id
            ];
        }
    }

    // 2. Hosting
    if ($website->hosting_id) {
        $items_to_add[] = [
            'type' => 'hosting',
            'id' => $website->hosting_id,
            'user_id' => $website->owner_user_id
        ];
    }

    // 3. Maintenance
    if ($website->maintenance_package_id) {
        $items_to_add[] = [
            'type' => 'maintenance',
            'id' => $website->maintenance_package_id,
            'user_id' => $website->owner_user_id
        ];
    }

    if (empty($items_to_add)) {
        echo json_encode(array(
            'status' => false,
            'message' => 'Website này không có dịch vụ nào cần gia hạn hoặc domain không do INOVA quản lý.'
        ));
        exit;
    }

    $services_added = 0;
    foreach ($items_to_add as $item) {
        // Check if item already in cart
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $cart_table WHERE user_id = %d AND service_type = %s AND service_id = %d",
            $item['user_id'],
            $item['type'],
            $item['id']
        ));

        if (!$existing) {
            $result = $wpdb->insert($cart_table, array(
                'user_id' => $item['user_id'],
                'service_type' => $item['type'],
                'service_id' => $item['id'],
                'quantity' => 1
            ));
            if ($result) {
                $services_added++;
            }
        }
    }

    // Get total cart count
    $cart_count = $wpdb->get_var("SELECT COUNT(*) FROM $cart_table");

    if ($services_added > 0) {
        echo json_encode(array(
            'status' => true,
            'message' => "Đã thêm $services_added dịch vụ vào giỏ hàng",
            'cart_count' => $cart_count
        ));
    } else {
        echo json_encode(array(
            'status' => false,
            'message' => 'Các dịch vụ của website này đã có sẵn trong giỏ hàng',
            'cart_count' => $cart_count
        ));
    }
    exit;
}

function add_website_service_to_cart_ajax()
{
    global $wpdb;

    $service_id = !empty($_POST['service_id']) ? intval($_POST['service_id']) : 0;

    if (!$service_id) {
        wp_send_json_error(array('message' => 'Thiếu ID dịch vụ'));
        return;
    }

    // Get service details from im_website_services table
    $service_table = $wpdb->prefix . 'im_website_services';
    $service = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$service_table} WHERE id = %d",
        $service_id
    ));

    if (!$service) {
        wp_send_json_error(array('message' => 'Không tìm thấy dịch vụ'));
        return;
    }

    // Check if service is APPROVED
    if ($service->status !== 'APPROVED') {
        wp_send_json_error(array('message' => 'Dịch vụ chưa được duyệt'));
        return;
    }

    // Get user ID from service's requested_by field
    $user_id = $service->requested_by;
    if (!$user_id) {
        wp_send_json_error(array('message' => 'Không tìm thấy chủ sở hữu dịch vụ'));
        return;
    }

    // Add to cart table (im_cart)
    $cart_table = $wpdb->prefix . 'im_cart';

    // Check if already in cart
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$cart_table} WHERE user_id = %d AND service_type = %s AND service_id = %d",
        $user_id,
        'website_service',
        $service_id
    ));

    if ($existing) {
        // Update quantity
        $wpdb->update(
            $cart_table,
            array('quantity' => intval($existing->quantity) + 1),
            array('id' => $existing->id),
            array('%d'),
            array('%d')
        );
    } else {
        // Insert new cart item
        $wpdb->insert(
            $cart_table,
            array(
                'user_id' => $user_id,
                'service_type' => 'website_service',
                'service_id' => $service_id,
                'quantity' => 1
            ),
            array('%d', '%s', '%d', '%d')
        );
    }

    wp_send_json_success(array('message' => 'Dịch vụ đã được thêm vào giỏ hàng'));
}

function delete_website_service_ajax()
{
    global $wpdb;

    $service_id = !empty($_POST['service_id']) ? intval($_POST['service_id']) : 0;

    if (!$service_id) {
        wp_send_json_error(array('message' => 'Thiếu ID dịch vụ'));
        exit;
    }

    // Get service details
    $service_table = $wpdb->prefix . 'im_website_services';
    $service = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$service_table} WHERE id = %d",
        $service_id
    ));

    if (!$service) {
        wp_send_json_error(array('message' => 'Không tìm thấy dịch vụ'));
        exit;
    }

    // Hard delete the service - completely remove from database
    $result = $wpdb->delete(
        $service_table,
        array('id' => $service_id),
        array('%d')
    );

    if ($result) {
        wp_send_json_success(array('message' => 'Dịch vụ đã được xóa'));
        exit;
    } else {
        wp_send_json_error(array('message' => 'Không thể xóa dịch vụ: ' . $wpdb->last_error));
        exit;
    }
}

function remove_from_cart_ajax()
{
    if (!isset($_POST['cart_id'])) {
        echo json_encode(array(
            'status' => false,
            'message' => 'Thiếu thông tin giỏ hàng'
        ));
        exit;
    }

    $cart_id = intval($_POST['cart_id']);

    global $wpdb;
    $cart_table = $wpdb->prefix . 'im_cart';

    $result = $wpdb->delete($cart_table, array('id' => $cart_id));

    if ($result) {
        // Get cart count
        $cart_count = $wpdb->get_var("SELECT COUNT(*) FROM $cart_table");

        echo json_encode(array(
            'status' => true,
            'message' => 'Đã xóa khỏi giỏ hàng',
            'cart_count' => $cart_count
        ));
    } else {
        echo json_encode(array(
            'status' => false,
            'message' => 'Không thể xóa khỏi giỏ hàng'
        ));
    }
    exit;
}

function get_cart_count_ajax()
{
    global $wpdb;
    $cart_table = $wpdb->prefix . 'im_cart';
    $cart_count = $wpdb->get_var("SELECT COUNT(*) FROM $cart_table");

    echo json_encode(array(
        'status' => true,
        'cart_count' => $cart_count
    ));
    exit;
}

function get_customer_services_ajax()
{
    if (!isset($_POST['user_id'])) {
        echo json_encode(array(
            'status' => false,
            'message' => 'User ID not provided'
        ));
        exit;
    }

    $user_id = intval($_POST['user_id']);

    if ($user_id <= 0) {
        echo json_encode(array(
            'status' => false,
            'message' => 'Invalid User ID'
        ));
        exit;
    }

    global $wpdb;

    // Get domains
    $domains = $wpdb->get_results($wpdb->prepare("
        SELECT
            d.id,
            d.domain_name,
            d.registration_period_years,
            d.expiry_date,
            COALESCE(pc.renew_price, 0) AS price
        FROM {$wpdb->prefix}im_domains d
        LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON d.product_catalog_id = pc.id
        WHERE d.owner_user_id = %d AND d.status = 'ACTIVE'
        ORDER BY d.domain_name
    ", $user_id), ARRAY_A);

    // Get hostings
    $hostings = $wpdb->get_results($wpdb->prepare("
        SELECT
            h.id,
            h.hosting_code,
            COALESCE(pc.renew_price, h.price, 0) AS price,
            h.billing_cycle_months,
            h.expiry_date,
            pc.name AS product_name
        FROM {$wpdb->prefix}im_hostings h
        LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON h.product_catalog_id = pc.id
        WHERE h.owner_user_id = %d AND h.status = 'ACTIVE'
        ORDER BY h.hosting_code
    ", $user_id), ARRAY_A);

    // Get maintenance packages
    $maintenances = $wpdb->get_results($wpdb->prepare("
        SELECT
            m.id,
            m.order_code,
            m.price_per_cycle,
            m.billing_cycle_months,
            m.expiry_date,
            pc.name AS product_name
        FROM {$wpdb->prefix}im_maintenance_packages m
        LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON m.product_catalog_id = pc.id
        WHERE m.owner_user_id = %d AND m.status = 'ACTIVE'
        ORDER BY m.order_code
    ", $user_id), ARRAY_A);

    // Get websites
    $websites = $wpdb->get_results($wpdb->prepare("
        SELECT
            w.id,
            w.name,
            w.domain_id,
            w.hosting_id,
            w.maintenance_package_id
        FROM {$wpdb->prefix}im_websites w
        WHERE w.owner_user_id = %d AND w.status = 'ACTIVE'
        ORDER BY w.name
    ", $user_id), ARRAY_A);

    echo json_encode(array(
        'status' => true,
        'domains' => $domains,
        'hostings' => $hostings,
        'maintenances' => $maintenances,
        'websites' => $websites
    ));

    exit;
}

function fetch_domain_info_from_apilayer()
{
    $domain = sanitize_text_field($_POST['domain']);

    if (empty($domain)) {
        wp_send_json_error(array('message' => 'Domain name is required'));
        return;
    }

    // Get APILayer WHOIS API Key from options
    $api_key = get_option('apilayer_whois_api_key');

    if (empty($api_key)) {
        wp_send_json_error(array('message' => 'APILayer WHOIS API Key chưa được cấu hình trong Cài đặt hệ thống.'));
        return;
    }

    $api_url = 'https://api.apilayer.com/whois/query?domain=' . urlencode($domain);

    $response = wp_remote_get($api_url, array(
        'headers' => array(
            'apikey' => $api_key
        ),
        'timeout' => 15
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Failed to fetch domain information: ' . $response->get_error_message()));
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data)) {
        wp_send_json_error(array('message' => 'Invalid response from API'));
        return;
    }

    // Extract registration and expiry dates
    $registration_date = null;
    $expiry_date = null;

    // APILayer WHOIS returns dates in 'result' object
    if (isset($data['result'])) {
        $result = $data['result'];

        // Try different possible field names for registration date
        if (isset($result['created_date'])) {
            $registration_date = date('Y-m-d', strtotime($result['created_date']));
        } elseif (isset($result['creation_date'])) {
            $registration_date = date('Y-m-d', strtotime($result['creation_date']));
        }

        // Try different possible field names for expiry date
        if (isset($result['expiry_date'])) {
            $expiry_date = date('Y-m-d', strtotime($result['expiry_date']));
        } elseif (isset($result['expiration_date'])) {
            $expiry_date = date('Y-m-d', strtotime($result['expiration_date']));
        } elseif (isset($result['registry_expiry_date'])) {
            $expiry_date = date('Y-m-d', strtotime($result['registry_expiry_date']));
        }
    }

    wp_send_json_success(array(
        'registration_date' => $registration_date,
        'expiry_date' => $expiry_date,
        'raw_data' => $data // For debugging
    ));
}

function update_email_notification_setting_callback()
{
    $user_id = get_current_user_id();

    if (!$user_id) {
        wp_send_json_error(['message' => 'User not logged in']);
        return;
    }

    $service_type = sanitize_text_field($_POST['service_type']);
    $is_enabled = intval($_POST['is_enabled']);

    // Validation
    $valid_types = ['domain', 'hosting', 'maintenance'];
    if (!in_array($service_type, $valid_types)) {
        wp_send_json_error(['message' => 'Invalid service type']);
        return;
    }

    // Get current user settings (or defaults)
    $current_settings = get_user_meta($user_id, 'inova_email_notifications', true);

    if (empty($current_settings)) {
        // Initialize with global defaults
        $current_settings = get_option('inova_email_notification_defaults', [
            'domain' => 1,
            'hosting' => 1,
            'maintenance' => 1
        ]);
    }

    // Update specific service type
    $current_settings[$service_type] = $is_enabled;

    // Save to user meta
    $result = update_user_meta($user_id, 'inova_email_notifications', $current_settings);

    if ($result !== false) {
        wp_send_json_success([
            'message' => 'Cập nhật thành công',
            'service_type' => $service_type,
            'is_enabled' => $is_enabled
        ]);
    } else {
        wp_send_json_error(['message' => 'Không thể cập nhật cài đặt']);
    }
}

function delete_contact_callback()
{
    global $wpdb;

    // Get current Inova user
    $current_user = get_inova_user();

    if (!$current_user) {
        wp_send_json_error(['message' => 'Không tìm thấy người dùng']);
        return;
    }

    $current_user_id = $current_user->id;
    $contact_id = intval($_POST['contact_id']);

    if ($contact_id <= 0) {
        wp_send_json_error(['message' => 'ID liên hệ không hợp lệ']);
        return;
    }

    // Verify contact belongs to current user before deleting
    $contacts_table = $wpdb->prefix . 'im_contacts';
    $contact = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $contacts_table WHERE id = %d AND user_id = %d",
        $contact_id,
        $current_user_id
    ));

    if (!$contact) {
        wp_send_json_error(['message' => 'Liên hệ không tồn tại hoặc bạn không có quyền xóa']);
        return;
    }

    // Delete contact
    $deleted = $wpdb->delete($contacts_table, ['id' => $contact_id], ['%d']);

    if ($deleted) {
        wp_send_json_success(['message' => 'Đã xóa liên hệ thành công']);
    } else {
        wp_send_json_error(['message' => 'Có lỗi xảy ra khi xóa liên hệ']);
    }
}

function update_my_vat_invoice_setting_callback()
{
    global $wpdb;

    // Get current Inova user
    $current_user = get_inova_user();

    if (!$current_user) {
        wp_send_json_error(['message' => 'Không tìm thấy người dùng']);
        return;
    }

    $current_user_id = $current_user->id;

    // Sanitize inputs
    $company_name = sanitize_text_field($_POST['company_name']);
    $tax_code = sanitize_text_field($_POST['tax_code']);
    $invoice_email = $_POST['invoice_email'];
    $invoice_phone = sanitize_text_field($_POST['invoice_phone']);
    $notes = sanitize_textarea_field($_POST['notes']);

    // Update im_users table
    $users_table = "{$wpdb->prefix}im_users";
    $update_result = $wpdb->update(
        $users_table,
        [
            'company_name' => $company_name,
            'tax_code' => $tax_code,
            'invoice_email' => $invoice_email,
            'invoice_phone' => $invoice_phone,
            'notes' => $notes
        ],
        ['id' => $current_user_id],
        ['%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($update_result !== false) {
        wp_send_json_success([
            'message' => 'Đã lưu cấu hình hóa đơn đỏ thành công',
            'data' => [
                'company_name' => $company_name,
                'tax_code' => $tax_code,
                'invoice_email' => $invoice_email,
                'invoice_phone' => $invoice_phone,
                'notes' => $notes
            ]
        ]);
    } else {
        wp_send_json_error(['message' => 'Không thể cập nhật cài đặt']);
    }
}

function update_vat_invoice_setting_callback()
{
    global $wpdb;

    // Get current Inova user
    $current_user = get_inova_user();

    if (!$current_user) {
        wp_send_json_error(['message' => 'Không tìm thấy người dùng']);
        return;
    }

    $current_user_id = $current_user->id;

    // Sanitize inputs
    $requires_vat_invoice = intval($_POST['requires_vat_invoice']);
    $company_name = sanitize_text_field($_POST['company_name']);
    $tax_code = sanitize_text_field($_POST['tax_code']);
    $company_address = sanitize_textarea_field($_POST['company_address']);
    $invoice_email = sanitize_email($_POST['invoice_email']);
    $invoice_phone = sanitize_text_field($_POST['invoice_phone']);

    // Update im_users table
    $users_table = "{$wpdb->prefix}im_users";
    $update_result = $wpdb->update(
        $users_table,
        [
            'requires_vat_invoice' => $requires_vat_invoice,
            'company_name' => $company_name,
            'tax_code' => $tax_code,
            'company_address' => $company_address,
            'invoice_email' => $invoice_email,
            'invoice_phone' => $invoice_phone
        ],
        ['id' => $current_user_id],
        ['%d', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($update_result !== false) {
        wp_send_json_success([
            'message' => 'Đã lưu cấu hình hóa đơn đỏ thành công',
            'data' => [
                'requires_vat_invoice' => $requires_vat_invoice,
                'company_name' => $company_name,
                'tax_code' => $tax_code,
                'company_address' => $company_address,
                'invoice_email' => $invoice_email,
                'invoice_phone' => $invoice_phone
            ]
        ]);
    } else {
        wp_send_json_error(['message' => 'Không thể cập nhật cài đặt']);
    }
}

function get_vat_invoice_list_callback()
{
    global $wpdb;

    // Get pagination parameters
    $page = intval($_POST['page'] ?? 1);
    $per_page = intval($_POST['per_page'] ?? 10);
    $search = sanitize_text_field($_POST['search'] ?? '');
    $is_admin = intval($_POST['is_admin'] ?? 0);

    // Get current Inova user
    $current_user = get_inova_user();
    $users_table = "{$wpdb->prefix}im_users";

    // Check permissions
    if ($is_admin) {
        // WordPress Admin: Có quyền xem tất cả
        $allowed_user_ids = null; // null = tất cả
    } elseif ($current_user && in_array($current_user->user_type, ['ADMIN', 'PARTNER'])) {
        // Inova Admin: Xem tất cả
        if ($current_user->user_type === 'ADMIN') {
            $allowed_user_ids = null; // null = tất cả
        } else {
            // Partner: Lấy users sở hữu services của partner
            $hostings_table = "{$wpdb->prefix}im_hostings";
            $maintenance_table = "{$wpdb->prefix}im_maintenance_packages";

            // Lấy owner_user_ids từ hosting
            $hosting_owners = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT owner_user_id FROM `$hostings_table`
                 WHERE partner_id = %d AND status = 'ACTIVE'",
                $current_user->id
            ));

            // Lấy owner_user_ids từ maintenance
            $maintenance_owners = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT owner_user_id FROM `$maintenance_table`
                 WHERE partner_id = %d AND status = 'ACTIVE'",
                $current_user->id
            ));

            // Merge 2 list
            $allowed_user_ids = array_unique(array_merge($hosting_owners, $maintenance_owners));

            if (empty($allowed_user_ids)) {
                // Partner không có services nào
                wp_send_json_success([
                    'data' => [],
                    'pagination' => [
                        'total' => 0,
                        'page' => $page,
                        'per_page' => $per_page,
                        'total_pages' => 0
                    ]
                ]);
                return;
            }
        }
    } else {
        wp_send_json_error(['message' => 'Bạn không có quyền truy cập']);
        return;
    }

    // Build WHERE clause
    $offset = ($page - 1) * $per_page;
    $where = "WHERE status = 'ACTIVE'";
    $params = [];

    // Filter by allowed_user_ids nếu là partner
    if ($allowed_user_ids !== null) {
        $placeholders = implode(',', array_fill(0, count($allowed_user_ids), '%d'));
        $where .= " AND id IN ($placeholders)";
        $params = array_merge($params, $allowed_user_ids);
    }

    // Filter by search
    if (!empty($search)) {
        $where .= " AND (name LIKE %s OR user_code LIKE %s OR email LIKE %s OR company_name LIKE %s)";
        $search_param = '%' . $wpdb->esc_like($search) . '%';
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }

    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM `$users_table` $where";
    if (!empty($params)) {
        $count_query = $wpdb->prepare($count_query, ...$params);
    }
    $total = $wpdb->get_var($count_query);

    // Determine per_page:
    // Nếu admin & không search: lấy tất cả (per_page = tổng số)
    // Ngược lại: per_page = 10 (hoặc value từ request)
    if ($is_admin && empty($search)) {
        $per_page = max($total, 1); // Lấy tất cả trong 1 trang
    }

    // Get users
    $query = "SELECT id, user_code, name, email, company_name, tax_code, requires_vat_invoice
              FROM `$users_table` $where
              ORDER BY name ASC
              LIMIT %d OFFSET %d";

    $get_params = $params;
    $get_params[] = $per_page;
    $get_params[] = $offset;

    $query = $wpdb->prepare($query, ...$get_params);
    $users = $wpdb->get_results($query);

    wp_send_json_success([
        'data' => $users,
        'pagination' => [
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil(intval($total) / $per_page)
        ]
    ]);
}

function save_vat_invoice_for_user_callback()
{
    global $wpdb;

    // Get current Inova user
    $current_user = get_inova_user();
    $is_admin = is_inova_admin();

    if (!$is_admin && (!$current_user || !in_array($current_user->user_type, ['ADMIN', 'PARTNER']))) {
        wp_send_json_error(['message' => 'Bạn không có quyền thực hiện']);
        return;
    }

    // Get target user ID
    $target_user_id = intval($_POST['user_id'] ?? 0);

    if ($target_user_id <= 0) {
        wp_send_json_error(['message' => 'ID người dùng không hợp lệ']);
        return;
    }

    // Verify target user exists
    $users_table = "{$wpdb->prefix}im_users";
    $target_user = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM `$users_table` WHERE id = %d LIMIT 1",
        $target_user_id
    ));

    if (!$target_user) {
        wp_send_json_error(['message' => 'Không tìm thấy người dùng']);
        return;
    }

    // For Partner: Kiểm tra xem target_user có phải là owner của services của partner không
    if (!$is_admin && $current_user && $current_user->user_type === 'PARTNER') {
        $hostings_table = "{$wpdb->prefix}im_hostings";
        $maintenance_table = "{$wpdb->prefix}im_maintenance_packages";

        // Check if target user owns any services of this partner
        $has_services = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$hostings_table`
             WHERE partner_id = %d AND owner_user_id = %d AND status = 'ACTIVE'
             UNION ALL
             SELECT COUNT(*) FROM `$maintenance_table`
             WHERE partner_id = %d AND owner_user_id = %d AND status = 'ACTIVE'",
            $current_user->id,
            $target_user_id,
            $current_user->id,
            $target_user_id
        ));

        if (!$has_services) {
            wp_send_json_error(['message' => 'Bạn không có quyền sửa thông tin user này']);
            return;
        }
    }

    // Sanitize inputs
    $requires_vat_invoice = intval($_POST['requires_vat_invoice'] ?? 0);
    $company_name = sanitize_text_field($_POST['company_name'] ?? '');
    $tax_code = sanitize_text_field($_POST['tax_code'] ?? '');
    $company_address = sanitize_textarea_field($_POST['company_address'] ?? '');
    $invoice_email = sanitize_email($_POST['invoice_email'] ?? '');
    $invoice_phone = sanitize_text_field($_POST['invoice_phone'] ?? '');

    // Update user
    $update_result = $wpdb->update(
        $users_table,
        [
            'requires_vat_invoice' => $requires_vat_invoice,
            'company_name' => $company_name,
            'tax_code' => $tax_code,
            'company_address' => $company_address,
            'invoice_email' => $invoice_email,
            'invoice_phone' => $invoice_phone
        ],
        ['id' => $target_user_id],
        ['%d', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($update_result !== false) {
        wp_send_json_success([
            'message' => 'Đã lưu thông tin hóa đơn thành công'
        ]);
    } else {
        wp_send_json_error(['message' => 'Không thể cập nhật thông tin']);
    }
}

function delete_vat_invoice_callback()
{
    global $wpdb;

    // Get current Inova user
    $current_user = get_inova_user();
    $is_admin = is_inova_admin();

    $target_user_id = intval($_POST['user_id'] ?? 0);

    if ($target_user_id <= 0) {
        wp_send_json_error(['message' => 'ID người dùng không hợp lệ']);
        return;
    }

    // Only allow if: 1) WordPress admin, 2) User deleting their own info, 3) Inova Admin/Partner deleting their users
    if ($is_admin) {
        // WordPress admin: Có quyền xóa bất kỳ user nào
        $can_delete = true;
    } elseif ($current_user) {
        $current_user_id = $current_user->id;

        if ($current_user_id === $target_user_id) {
            // User deleting their own info
            $can_delete = true;
        } elseif (in_array($current_user->user_type, ['ADMIN', 'PARTNER'])) {
            // Inova Admin: Có thể xóa bất kỳ user nào
            if ($current_user->user_type === 'ADMIN') {
                $can_delete = true;
            } else {
                // Partner: Chỉ có thể xóa users sở hữu services của partner
                $hostings_table = "{$wpdb->prefix}im_hostings";
                $maintenance_table = "{$wpdb->prefix}im_maintenance_packages";

                $has_services = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$hostings_table`
                     WHERE partner_id = %d AND owner_user_id = %d AND status = 'ACTIVE'
                     UNION ALL
                     SELECT COUNT(*) FROM `$maintenance_table`
                     WHERE partner_id = %d AND owner_user_id = %d AND status = 'ACTIVE'",
                    $current_user->id,
                    $target_user_id,
                    $current_user->id,
                    $target_user_id
                ));

                $can_delete = !empty($has_services);
            }
        } else {
            $can_delete = false;
        }
    } else {
        $can_delete = false;
    }

    if (!$can_delete) {
        wp_send_json_error(['message' => 'Bạn không có quyền xóa']);
        return;
    }

    // Verify target user exists
    $users_table = "{$wpdb->prefix}im_users";
    $target_user = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM `$users_table` WHERE id = %d LIMIT 1",
        $target_user_id
    ));

    if (!$target_user) {
        wp_send_json_error(['message' => 'Không tìm thấy người dùng']);
        return;
    }

    // Reset VAT invoice fields
    $update_result = $wpdb->update(
        $users_table,
        [
            'requires_vat_invoice' => 0,
            'company_name' => '',
            'tax_code' => '',
            'company_address' => '',
            'invoice_email' => '',
            'invoice_phone' => ''
        ],
        ['id' => $target_user_id],
        ['%d', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($update_result !== false) {
        wp_send_json_success([
            'message' => 'Đã xóa thông tin hóa đơn thành công'
        ]);
    } else {
        wp_send_json_error(['message' => 'Không thể xóa thông tin']);
    }
}

function delete_website_user_callback()
{
    global $wpdb;

    // Get current user
    $current_user_id = get_current_user_id();
    $is_admin = is_inova_admin();
    $website_id = intval($_POST['website_id'] ?? 0);

    if ($website_id <= 0) {
        wp_send_json_error(['message' => 'ID website không hợp lệ']);
    }

    $websites_table = $wpdb->prefix . 'im_websites';

    // Get website
    $website = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $websites_table WHERE id = %d AND status = 'ACTIVE'",
        $website_id
    ));

    if (!$website) {
        wp_send_json_error(['message' => 'Không tìm thấy website']);
    }

    // Check permission
    $can_delete = false;

    if ($is_admin) {
        $can_delete = true;
    } elseif ($current_user_id) {
        // Check if user is creator
        if ($website->created_by == $current_user_id) {
            $can_delete = true;
        }
    }

    if (!$can_delete) {
        wp_send_json_error(['message' => 'Bạn không có quyền xóa website này ']);
    }

    // Check if website has related services
    if (!empty($website->domain_id) || !empty($website->hosting_id) || !empty($website->maintenance_package_id)) {
        $services = [];
        if (!empty($website->domain_id))
            $services[] = 'tên miền';
        if (!empty($website->hosting_id))
            $services[] = 'hosting';
        if (!empty($website->maintenance_package_id))
            $services[] = 'gói bảo trì';

        wp_send_json_error([
            'message' => 'Không thể xóa website vì đã liên kết với: ' . implode(', ', $services) . '. Vui lòng gỡ liên kết trước khi xóa.'
        ]);
    }

    // Soft delete website
    $result = $wpdb->update(
        $websites_table,
        ['status' => 'DELETED'],
        ['id' => $website_id],
        ['%s'],
        ['%d']
    );

    if ($result === false) {
        wp_send_json_error(['message' => 'Có lỗi xảy ra khi xóa website']);
    }

    wp_send_json_success([
        'message' => 'Đã xóa website thành công',
        'website_id' => $website_id
    ]);
}

function delete_domain_user_callback()
{
    global $wpdb;

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'delete_domain_nonce')) {
        wp_send_json_error(['message' => 'Lỗi bảo mật: nonce không hợp lệ']);
        return;
    }

    // Get current user
    $current_user_id = get_current_user_id();
    $domain_id = intval($_POST['domain_id'] ?? 0);
    $force_delete = intval($_POST['force_delete'] ?? 0);

    if ($domain_id <= 0) {
        wp_send_json_error(['message' => 'ID domain không hợp lệ']);
        return;
    }

    if (!$current_user_id) {
        wp_send_json_error(['message' => 'Bạn cần đăng nhập để thực hiện hành động này']);
        return;
    }

    $domains_table = $wpdb->prefix . 'im_domains';
    $websites_table = $wpdb->prefix . 'im_websites';

    // Get domain
    $domain = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $domains_table WHERE id = %d",
        $domain_id
    ));

    if (!$domain) {
        wp_send_json_error(['message' => 'Không tìm thấy domain']);
        return;
    }

    // Check permission: Only creator (created_by) can delete
    if ($domain->create_by != $current_user_id) {
        wp_send_json_error(['message' => 'Bạn không có quyền xóa domain này']);
        return;
    }

    // Check if domain is used by any websites
    $websites_using_domain = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM $websites_table WHERE domain_id = %d",
        $domain_id
    ));

    // If domain has websites and force_delete is not 1, return error
    if (!empty($websites_using_domain) && $force_delete != 1) {
        wp_send_json_error([
            'message' => "Không thể xóa domain này vì đang được sử dụng bởi " . count($websites_using_domain) . " website(s). Vui lòng xác nhận để xóa."
        ]);
        return;
    }

    // If domain has websites and force_delete is 1, gỡ liên kết
    if (!empty($websites_using_domain) && $force_delete == 1) {
        $wpdb->update(
            $websites_table,
            array('domain_id' => null),
            array('domain_id' => $domain_id),
            array('%s'),
            array('%d')
        );
    }

    // Delete domain
    $deleted = $wpdb->delete(
        $domains_table,
        array('id' => $domain_id),
        array('%d')
    );

    if ($deleted === false) {
        wp_send_json_error(['message' => 'Có lỗi xảy ra khi xóa domain']);
        return;
    }

    wp_send_json_success([
        'message' => "Domain '{$domain->domain_name}' đã được xóa thành công!",
        'domain_id' => $domain_id
    ]);
}

function get_all_partners_callback()
{
    global $wpdb;

    $users_table = $wpdb->prefix . 'im_users';

    // Get only PARTNER type users
    $partners = $wpdb->get_results(
        "SELECT id, name, user_code FROM {$users_table} 
         WHERE user_type = 'PARTNER' AND status = 'ACTIVE'
         ORDER BY name ASC"
    );

    if (empty($partners)) {
        wp_send_json_error(['message' => 'Không có đối tác nào']);
        return;
    }

    wp_send_json_success($partners);
}

function get_partner_discount_rates_callback()
{
    global $wpdb;

    $rates_table = $wpdb->prefix . 'im_partner_discount_rates';
    $users_table = $wpdb->prefix . 'im_users';

    $partner_id = !empty($_POST['partner_id']) ? intval($_POST['partner_id']) : null;
    $service_type = !empty($_POST['service_type']) ? sanitize_text_field($_POST['service_type']) : null;

    $query = "SELECT 
        dr.*,
        u.name as partner_name,
        u.user_code
    FROM {$rates_table} dr
    JOIN {$users_table} u ON dr.partner_id = u.id
    WHERE 1=1";

    $params = [];

    if ($partner_id) {
        $query .= " AND dr.partner_id = %d";
        $params[] = $partner_id;
    }

    if ($service_type) {
        $query .= " AND dr.service_type = %s";
        $params[] = $service_type;
    }

    $query .= " ORDER BY dr.partner_id, dr.service_type, dr.effective_date DESC";

    if (!empty($params)) {
        $rates = $wpdb->get_results($wpdb->prepare($query, $params));
    } else {
        $rates = $wpdb->get_results($query);
    }

    if (empty($rates)) {
        wp_send_json_success([]);
        return;
    }

    wp_send_json_success($rates);
}

function get_partner_discount_rate_callback()
{
    global $wpdb;

    $rate_id = !empty($_POST['rate_id']) ? intval($_POST['rate_id']) : 0;

    if (!$rate_id) {
        wp_send_json_error(['message' => 'Thiếu ID tỷ lệ']);
        return;
    }

    $rates_table = $wpdb->prefix . 'im_partner_discount_rates';
    $rate = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$rates_table} WHERE id = %d",
        $rate_id
    ));

    if (!$rate) {
        wp_send_json_error(['message' => 'Không tìm thấy tỷ lệ']);
        return;
    }

    wp_send_json_success($rate);
}

function save_partner_discount_rate_callback()
{
    global $wpdb;

    // Verify nonce if needed
    if (!check_ajax_referer('nonce_name', 'security', false)) {
        // Note: nonce check is optional here since this is for admin only
    }

    $partner_id = !empty($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;
    $service_type = !empty($_POST['service_type']) ? sanitize_text_field($_POST['service_type']) : '';
    $discount_rate = !empty($_POST['discount_rate']) ? floatval($_POST['discount_rate']) : 0;
    $effective_date = !empty($_POST['effective_date']) ? sanitize_text_field($_POST['effective_date']) : '';
    $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
    $notes = !empty($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

    // Validation
    if (!$partner_id || !$service_type || $discount_rate < 0 || $discount_rate > 100 || !$effective_date) {
        wp_send_json_error(['message' => 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.']);
        return;
    }

    $rates_table = $wpdb->prefix . 'im_partner_discount_rates';

    $insert_data = [
        'partner_id' => $partner_id,
        'service_type' => $service_type,
        'discount_rate' => $discount_rate,
        'effective_date' => $effective_date,
        'end_date' => $end_date ?: null,
        'notes' => $notes
    ];

    $result = $wpdb->insert(
        $rates_table,
        $insert_data,
        ['%d', '%s', '%f', '%s', '%s', '%s']
    );

    if ($result === false) {
        wp_send_json_error(['message' => 'Lỗi cơ sở dữ liệu: ' . $wpdb->last_error]);
        return;
    }

    wp_send_json_success(['message' => 'Tỷ lệ chiết khấu đã được thêm thành công']);
}

function update_partner_discount_rate_callback()
{
    global $wpdb;

    $rate_id = !empty($_POST['rate_id']) ? intval($_POST['rate_id']) : 0;
    $partner_id = !empty($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;
    $service_type = !empty($_POST['service_type']) ? sanitize_text_field($_POST['service_type']) : '';
    $discount_rate = !empty($_POST['discount_rate']) ? floatval($_POST['discount_rate']) : 0;
    $effective_date = !empty($_POST['effective_date']) ? sanitize_text_field($_POST['effective_date']) : '';
    $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
    $notes = !empty($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

    // Validation
    if (!$rate_id || !$partner_id || !$service_type || $discount_rate < 0 || $discount_rate > 100 || !$effective_date) {
        wp_send_json_error(['message' => 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.']);
        return;
    }

    $rates_table = $wpdb->prefix . 'im_partner_discount_rates';

    $update_data = [
        'partner_id' => $partner_id,
        'service_type' => $service_type,
        'discount_rate' => $discount_rate,
        'effective_date' => $effective_date,
        'end_date' => $end_date ?: null,
        'notes' => $notes
    ];

    $result = $wpdb->update(
        $rates_table,
        $update_data,
        ['id' => $rate_id],
        ['%d', '%s', '%f', '%s', '%s', '%s'],
        ['%d']
    );

    if ($result === false) {
        wp_send_json_error(['message' => 'Lỗi cơ sở dữ liệu']);
        return;
    }

    wp_send_json_success(['message' => 'Tỷ lệ chiết khấu đã được cập nhật thành công']);
}

function delete_partner_discount_rate_callback()
{
    global $wpdb;

    $rate_id = !empty($_POST['rate_id']) ? intval($_POST['rate_id']) : 0;

    if (!$rate_id) {
        wp_send_json_error(['message' => 'Thiếu ID tỷ lệ']);
        return;
    }

    $rates_table = $wpdb->prefix . 'im_partner_discount_rates';

    // Get rate info before deleting
    $rate = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$rates_table} WHERE id = %d",
        $rate_id
    ));

    if (!$rate) {
        wp_send_json_error(['message' => 'Không tìm thấy tỷ lệ']);
        return;
    }

    $result = $wpdb->delete(
        $rates_table,
        ['id' => $rate_id],
        ['%d']
    );

    if ($result === false) {
        wp_send_json_error(['message' => 'Lỗi cơ sở dữ liệu']);
        return;
    }

    wp_send_json_success(['message' => 'Tỷ lệ chiết khấu đã được xóa thành công']);
}

function get_partner_commissions_callback()
{
    global $wpdb;

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    // Check if user is admin or partner
    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');
    $is_partner = false;
    $user_partner_id = null;

    if (!$is_admin) {
        // Use get_inova_user to get inova user record (returns object with id, user_type, status)
        $inova_user = get_inova_user($current_user->ID);

        if ($inova_user && in_array($inova_user->user_type, array('PARTNER', 'MANAGER', 'EMPLOYEE'))) {
            $is_partner = true;
            $user_partner_id = $inova_user->id;
        } else {
            wp_send_json_error(['message' => 'Access denied']);
            return;
        }
    }

    $partner_id = !empty($_POST['partner_id']) ? intval($_POST['partner_id']) : '';
    $service_type = !empty($_POST['service_type']) ? sanitize_text_field($_POST['service_type']) : '';
    $status = !empty($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $from_date = !empty($_POST['from_date']) ? sanitize_text_field($_POST['from_date']) : '';
    $to_date = !empty($_POST['to_date']) ? sanitize_text_field($_POST['to_date']) : '';

    // Partner can only see their own commissions
    if ($is_partner && !$partner_id) {
        $partner_id = $user_partner_id;
    }

    $commissions_table = $wpdb->prefix . 'im_partner_commissions';
    $users_table = $wpdb->prefix . 'im_users';

    $query = "
        SELECT 
            c.*,
            u.name as partner_name
        FROM {$commissions_table} c
        LEFT JOIN {$users_table} u ON c.partner_id = u.id
        WHERE 1=1
    ";

    if ($partner_id) {
        $query .= $wpdb->prepare(" AND c.partner_id = %d", $partner_id);
    }

    if ($service_type) {
        $query .= $wpdb->prepare(" AND c.service_type = %s", $service_type);
    }

    if ($status) {
        $query .= $wpdb->prepare(" AND c.status = %s", $status);
    }

    if ($from_date) {
        $query .= $wpdb->prepare(" AND c.calculation_date >= %s", $from_date);
    }

    if ($to_date) {
        $query .= $wpdb->prepare(" AND c.calculation_date <= %s", $to_date);
    }

    $query .= " ORDER BY c.calculation_date DESC, c.id DESC";

    $commissions = $wpdb->get_results($query);

    if ($commissions === null) {
        wp_send_json_error(['message' => 'Lỗi truy vấn dữ liệu']);
        return;
    }

    wp_send_json_success($commissions);
}

function get_commission_detail_callback()
{
    global $wpdb;

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Truy cập bị từ chối']);
        return;
    }

    $commission_id = !empty($_POST['commission_id']) ? intval($_POST['commission_id']) : 0;

    if (!$commission_id) {
        wp_send_json_error(['message' => 'Thiếu ID hoa hồng']);
        return;
    }

    // Check authorization - admin can view all, partner/other only own
    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');
    $user_partner_id = null;

    if (!$is_admin) {
        $inova_user = get_inova_user($current_user->ID);
        if (!$inova_user) {
            wp_send_json_error(['message' => 'Truy cập bị từ chối']);
            return;
        }
        $user_partner_id = $inova_user->id;
    }

    $commissions_table = $wpdb->prefix . 'im_partner_commissions';
    $users_table = $wpdb->prefix . 'im_users';

    $query = $wpdb->prepare("
        SELECT 
            c.*,
            u.name as partner_name
        FROM {$commissions_table} c
        LEFT JOIN {$users_table} u ON c.partner_id = u.id
        WHERE c.id = %d
    ", $commission_id);

    // Non-admin can only view their own commissions
    if (!$is_admin && $user_partner_id) {
        $query .= $wpdb->prepare(" AND c.partner_id = %d", $user_partner_id);
    }

    $commission = $wpdb->get_row($query);

    if (!$commission) {
        wp_send_json_error(['message' => 'Không tìm thấy hoa hồng']);
        return;
    }

    wp_send_json_success($commission);
}

function update_commission_status_callback()
{
    global $wpdb;

    // Check authorization - only admin can update commission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Truy cập bị từ chối']);
        return;
    }

    $commission_id = !empty($_POST['commission_id']) ? intval($_POST['commission_id']) : 0;
    $new_status = !empty($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $new_discount_rate = isset($_POST['discount_rate']) && $_POST['discount_rate'] !== null ? floatval($_POST['discount_rate']) : null;

    if (!$commission_id || !$new_status) {
        wp_send_json_error(['message' => 'Thiếu dữ liệu']);
        return;
    }

    // Validate status
    $valid_statuses = ['PENDING', 'PAID', 'WITHDRAWN', 'CANCELLED'];
    if (!in_array($new_status, $valid_statuses)) {
        wp_send_json_error(['message' => 'Trạng thái không hợp lệ']);
        return;
    }

    $commissions_table = $wpdb->prefix . 'im_partner_commissions';

    // Check if commission exists
    $commission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$commissions_table} WHERE id = %d",
        $commission_id
    ));

    if (!$commission) {
        wp_send_json_error(['message' => 'Không tìm thấy hoa hồng']);
        return;
    }

    // Prepare update data
    $update_data = ['status' => $new_status];
    $format = ['%s'];

    // Update discount rate and recalculate commission amount only if currently PENDING
    if ($commission->status === 'PENDING' && $new_discount_rate !== null) {
        $update_data['discount_rate'] = $new_discount_rate;
        $format[] = '%f';
        
        // Recalculate commission amount
        $service_amount = floatval($commission->service_amount);
        $new_commission_amount = ($service_amount * $new_discount_rate) / 100;
        $update_data['commission_amount'] = $new_commission_amount;
        $format[] = '%f';
    }

    // Set withdrawal_date when status is WITHDRAWN
    if ($new_status === 'WITHDRAWN') {
        $update_data['withdrawal_date'] = current_time('Y-m-d');
        $format[] = '%s';
    }

    $result = $wpdb->update(
        $commissions_table,
        $update_data,
        ['id' => $commission_id],
        $format,
        ['%d']
    );

    if ($result === false) {
        wp_send_json_error(['message' => 'Lỗi cơ sở dữ liệu']);
        return;
    }

    wp_send_json_success(['message' => 'Hoa hồng đã được cập nhật thành công']);
}

function bulk_update_commission_status_callback()
{
    global $wpdb;

    // Check authorization - only admin can bulk update commissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Truy cập bị từ chối']);
        return;
    }

    $commission_ids = !empty($_POST['commission_ids']) ? array_map('intval', (array) $_POST['commission_ids']) : [];
    $new_status = !empty($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

    if (empty($commission_ids) || !$new_status) {
        wp_send_json_error(['message' => 'Thiếu dữ liệu']);
        return;
    }

    // Validate status
    $valid_statuses = ['PENDING', 'PAID', 'WITHDRAWN', 'CANCELLED'];
    if (!in_array($new_status, $valid_statuses)) {
        wp_send_json_error(['message' => 'Trạng thái không hợp lệ']);
        return;
    }

    $commissions_table = $wpdb->prefix . 'im_partner_commissions';

    // Build query based on status
    if ($new_status === 'WITHDRAWN') {
        $update_query = "UPDATE {$commissions_table} SET status = %s, withdrawal_date = %s WHERE id IN (" .
            implode(',', array_fill(0, count($commission_ids), '%d')) . ")";
        $query_params = array_merge([$new_status, current_time('Y-m-d')], $commission_ids);
    } else {
        $update_query = "UPDATE {$commissions_table} SET status = %s WHERE id IN (" .
            implode(',', array_fill(0, count($commission_ids), '%d')) . ")";
        $query_params = array_merge([$new_status], $commission_ids);
    }

    // Update
    $result = $wpdb->query($wpdb->prepare($update_query, $query_params));

    if ($result === false) {
        wp_send_json_error(['message' => 'Lỗi cơ sở dữ liệu']);
        return;
    }

    wp_send_json_success(['message' => 'Đã cập nhật ' . count($commission_ids) . ' hoa hồng thành công']);
}

function change_user_password_callback()
{
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Bạn cần đăng nhập để thực hiện hành động này']);
        return;
    }

    $current_user_id = get_current_user_id();
    $current_password = !empty($_POST['current_password']) ? sanitize_text_field($_POST['current_password']) : '';
    $new_password = !empty($_POST['new_password']) ? sanitize_text_field($_POST['new_password']) : '';

    // Validate inputs
    if (empty($current_password) || empty($new_password)) {
        wp_send_json_error(['message' => 'Vui lòng điền đầy đủ thông tin']);
        return;
    }

    // Validate new password length
    if (strlen($new_password) < 8) {
        wp_send_json_error(['message' => 'Mật khẩu mới phải có ít nhất 8 ký tự']);
        return;
    }

    // Get current user
    $user = get_user_by('id', $current_user_id);

    if (!$user) {
        wp_send_json_error(['message' => 'Không tìm thấy người dùng']);
        return;
    }

    // Verify current password
    if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
        wp_send_json_error(['message' => 'Mật khẩu hiện tại không chính xác']);
        return;
    }

    // Update password
    wp_set_password($new_password, $current_user_id);

    // Send success response
    wp_send_json_success([
        'message' => 'Mật khẩu đã được cập nhật thành công'
    ]);
}

function update_user_profile_callback()
{
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Bạn cần đăng nhập để thực hiện hành động này']);
        return;
    }

    $current_user_id = get_current_user_id();
    $display_name = !empty($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
    $user_email = !empty($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
    $user_nicename = !empty($_POST['user_nicename']) ? sanitize_text_field($_POST['user_nicename']) : '';

    // Validate inputs
    if (empty($display_name) || empty($user_email)) {
        wp_send_json_error(['message' => 'Tên hiển thị và Email không được để trống']);
        return;
    }

    // Validate email format
    if (!is_email($user_email)) {
        wp_send_json_error(['message' => 'Email không hợp lệ']);
        return;
    }

    // Get current user
    $user = get_user_by('id', $current_user_id);

    if (!$user) {
        wp_send_json_error(['message' => 'Không tìm thấy người dùng']);
        return;
    }

    // Check if new email is already used by another user
    if ($user_email !== $user->user_email) {
        $existing_user = get_user_by('email', $user_email);
        if ($existing_user && $existing_user->ID !== $current_user_id) {
            wp_send_json_error(['message' => 'Email này đã được sử dụng bởi một tài khoản khác']);
            return;
        }
    }

    // Prepare update data
    $user_data = array(
        'ID' => $current_user_id,
        'display_name' => $display_name,
        'user_email' => $user_email,
    );

    // Add user_nicename if provided
    if (!empty($user_nicename)) {
        $user_data['user_nicename'] = $user_nicename;
    }

    // Update user
    $result = wp_update_user($user_data);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => 'Lỗi khi cập nhật thông tin: ' . $result->get_error_message()]);
        return;
    }

    // Send success response
    wp_send_json_success([
        'message' => 'Thông tin cá nhân đã được cập nhật thành công'
    ]);
}

function register_maintenance_package_callback()
{
    // Verify nonce
    $nonce = !empty($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'maintenance_registration')) {
        wp_send_json_error(['message' => 'Xác thực bảo mật thất bại']);
        return;
    }

    // Check if user is logged in (for non-admin)
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Vui lòng đăng nhập trước']);
        return;
    }

    // Get and sanitize input
    $website_id = !empty($_POST['website_id']) ? intval($_POST['website_id']) : 0;
    $phone_number = !empty($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';
    $zalo_number = !empty($_POST['zalo_number']) ? sanitize_text_field($_POST['zalo_number']) : '';
    $maintenance_notes = !empty($_POST['maintenance_notes']) ? sanitize_textarea_field($_POST['maintenance_notes']) : '';

    // Validate required fields
    if (empty($website_id) || empty($phone_number)) {
        wp_send_json_error(['message' => 'Vui lòng điền đầy đủ thông tin bắt buộc']);
        return;
    }

    global $wpdb;
    $websites_table = $wpdb->prefix . 'im_websites';
    $users_table = $wpdb->prefix . 'im_users';

    // Get current user info
    $current_wp_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $current_inova_user = get_inova_user($current_wp_user_id);

    // Verify website exists and belongs to current user
    $website = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name FROM $websites_table WHERE id = %d AND owner_user_id = %d",
        $website_id,
        $current_inova_user->id
    ));

    if (empty($website)) {
        wp_send_json_error(['message' => 'Website không tồn tại hoặc bạn không có quyền truy cập']);
        return;
    }

    // Prepare email content
    $user_email = $current_user->user_email;
    $user_name = $current_user->display_name;
    $website_name = $website->name;

    // Build email body
    $email_subject = '[Yêu cầu Bảo trì] ' . $website_name . ' - từ ' . $user_name;

    $email_body = "YÊU CẦU ĐĂNG KÝ GÓI BẢO TRÌ WEBSITE\n";
    $email_body .= str_repeat("=", 55) . "\n\n";

    $email_body .= "📋 THÔNG TIN NGƯỜI ĐĂNG KÝ:\n";
    $email_body .= "─────────────────────────────────────────────\n";
    $email_body .= "Họ tên: " . $user_name . "\n";
    $email_body .= "Email: " . $user_email . "\n";
    $email_body .= "Số điện thoại: " . $phone_number . "\n";
    if (!empty($zalo_number)) {
        $email_body .= "Zalo: " . $zalo_number . "\n";
    }

    $email_body .= "\n🌐 WEBSITE CẦN BẢO TRÌ:\n";
    $email_body .= "─────────────────────────────────────────────\n";
    $email_body .= "Tên website: " . $website_name . "\n";

    if (!empty($maintenance_notes)) {
        $email_body .= "\n📝 YÊU CẦU BỔ SUNG:\n";
        $email_body .= "─────────────────────────────────────────────\n";
        $email_body .= $maintenance_notes . "\n";
    }

    $email_body .= "\n💰 GIÁ CỬA:\n";
    $email_body .= "─────────────────────────────────────────────\n";
    $email_body .= "100.000 VNĐ / tháng / 1GB dữ liệu\n";
    $email_body .= "Ví dụ: Website 5GB = 500.000 VNĐ/tháng\n";
    $email_body .= "\n📞 QUY TRÌNH TIẾP THEO:\n";
    $email_body .= "─────────────────────────────────────────────\n";
    $email_body .= "1. Chúng tôi sẽ kiểm tra chi tiết website của bạn\n";
    $email_body .= "2. Báo giá cụ thể dựa vào dung lượng và yêu cầu\n";
    $email_body .= "3. Liên hệ để xác nhận và ký kết hợp đồng\n";
    $email_body .= "\n" . str_repeat("=", 55) . "\n";

    // Send email
    $recipient_email = 'namth.pass@gmail.com';
    $headers = array('Content-Type: text/plain; charset=UTF-8');

    $email_sent = wp_mail($recipient_email, $email_subject, $email_body, $headers);

    if ($email_sent) {
        wp_send_json_success([
            'message' => 'Yêu cầu đăng ký của bạn đã được gửi thành công!',
            'website_id' => $website_id
        ]);
    } else {
        wp_send_json_error(['message' => 'Có lỗi xảy ra khi gửi email. Vui lòng thử lại.']);
    }
}

function load_merge_invoices_callback()
{
    global $wpdb;

    $invoice_id = intval($_POST['invoice_id']);
    $inova_customer_id = intval($_POST['customer_id']); // This is INOVA user ID from im_users table

    $invoice_table = $wpdb->prefix . 'im_invoices';

    if ($invoice_id <= 0 || $inova_customer_id <= 0) {
        error_log('Load merge invoices - Invalid params: invoice_id=' . $invoice_id . ', customer_id=' . $inova_customer_id);
        wp_send_json_error(['message' => 'Thông tin không hợp lệ']);
        return;
    }

    // Verify invoice exists and belongs to this customer
    $invoice = $wpdb->get_row($wpdb->prepare("
        SELECT id, user_id, status 
        FROM $invoice_table 
        WHERE id = %d
    ", $invoice_id));

    if (!$invoice) {
        error_log('Load merge invoices - Invoice not found: ' . $invoice_id);
        wp_send_json_error(['message' => 'Hóa đơn không tồn tại']);
        return;
    }

    if ($invoice->user_id != $inova_customer_id) {
        error_log('Load merge invoices - Customer mismatch: invoice customer=' . $invoice->user_id . ', requested=' . $inova_customer_id);
        wp_send_json_error(['message' => 'Hóa đơn không thuộc khách hàng này']);
        return;
    }

    // Get all pending invoices for the same customer, excluding current invoice
    // user_id in im_invoices is the INOVA user ID (from im_users table)
    $query = $wpdb->prepare("
        SELECT 
            id,
            invoice_code,
            total_amount,
            created_at,
            status
        FROM $invoice_table
        WHERE user_id = %d
        AND id != %d
        AND status = 'pending'
        ORDER BY created_at DESC
    ", $inova_customer_id, $invoice_id);

    error_log('Load merge invoices - Query for customer (Inova ID): ' . $inova_customer_id . ', excluding invoice: ' . $invoice_id);
    error_log('Load merge invoices - SQL Query: ' . $query);

    $invoices = $wpdb->get_results($query);

    // Check for SQL errors
    if ($invoices === null) {
        $sql_error = $wpdb->last_error;
        error_log('Load merge invoices - SQL Error: ' . $sql_error);
        wp_send_json_error(['message' => 'Lỗi truy vấn cơ sở dữ liệu: ' . $sql_error]);
        return;
    }

    error_log('Load merge invoices - Found: ' . count($invoices) . ' pending invoices');
    if (count($invoices) > 0) {
        foreach ($invoices as $inv) {
            error_log('  - Invoice: ' . $inv->invoice_code . ' (ID: ' . $inv->id . ', Amount: ' . $inv->total_amount . ')');
        }
    }

    if (!empty($invoices)) {
        wp_send_json_success([
            'invoices' => $invoices,
            'count' => count($invoices)
        ]);
    } else {
        wp_send_json_success([
            'invoices' => [],
            'count' => 0
        ]);
    }
}

function merge_invoices_callback()
{
    global $wpdb;

    $target_invoice_id = intval($_POST['target_invoice_id']);
    $source_invoice_ids = array_map('intval', (array) $_POST['source_invoice_ids']);

    if ($target_invoice_id <= 0 || empty($source_invoice_ids)) {
        wp_send_json_error(['message' => 'Dữ liệu không hợp lệ']);
        return;
    }

    // Define table names
    $invoice_table = $wpdb->prefix . 'im_invoices';
    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
    $commissions_table = $wpdb->prefix . 'im_partner_commissions';

    try {
        // Start transaction
        $wpdb->query('START TRANSACTION');

        // Get target invoice details
        $target_invoice = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $invoice_table WHERE id = %d
        ", $target_invoice_id));

        if (!$target_invoice) {
            throw new Exception('Hóa đơn đích không tồn tại');
        }

        // Initialize totals
        $total_amount = floatval($target_invoice->total_amount);
        $sub_total = floatval($target_invoice->sub_total);
        $tax_amount = floatval($target_invoice->tax_amount);
        $discount_total = floatval($target_invoice->discount_total);
        $merged_count = 0;

        // Process each source invoice
        foreach ($source_invoice_ids as $source_id) {
            $source_invoice = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $invoice_table WHERE id = %d
            ", $source_id));

            if (!$source_invoice) {
                continue;
            }

            // Add amounts from source invoice
            $total_amount += floatval($source_invoice->total_amount);
            $sub_total += floatval($source_invoice->sub_total);
            $tax_amount += floatval($source_invoice->tax_amount);
            $discount_total += floatval($source_invoice->discount_total);

            // Move all invoice items from source to target
            $items = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM $invoice_items_table WHERE invoice_id = %d
            ", $source_id));

            foreach ($items as $item) {
                // Update invoice_id for the item
                $wpdb->update(
                    $invoice_items_table,
                    ['invoice_id' => $target_invoice_id],
                    ['id' => $item->id]
                );
            }

            // Update commissions - change invoice_id to target invoice
            $wpdb->update(
                $commissions_table,
                ['invoice_id' => $target_invoice_id],
                ['invoice_id' => $source_id]
            );

            // Delete source invoice
            $wpdb->delete($invoice_table, ['id' => $source_id]);

            $merged_count++;
        }

        // Update target invoice with new totals
        $wpdb->update(
            $invoice_table,
            [
                'total_amount' => intval($total_amount),
                'sub_total' => intval($sub_total),
                'tax_amount' => intval($tax_amount),
                'discount_total' => intval($discount_total),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $target_invoice_id]
        );

        // Commit transaction
        $wpdb->query('COMMIT');

        wp_send_json_success([
            'message' => 'Đã gộp ' . $merged_count . ' hóa đơn. Tổng tiền cập nhật thành ' . number_format($total_amount) . ' VNĐ',
            'merged_count' => $merged_count
        ]);

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => 'Lỗi: ' . $e->getMessage()]);
    }
}

function delete_expense_callback() {
    check_ajax_referer('delete_expense_nonce', 'nonce');

    if (!is_inova_admin()) {
        wp_send_json_error(array('message' => 'Không có quyền truy cập.'));
    }

    $expense_id = intval($_POST['expense_id']);

    if (empty($expense_id)) {
        wp_send_json_error(array('message' => 'ID chi tiêu không hợp lệ.'));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'im_recurring_expenses';

    $deleted = $wpdb->delete(
        $table,
        array('id' => $expense_id),
        array('%d')
    );

    if ($deleted) {
        wp_send_json_success(array('message' => 'Xóa chi tiêu thành công.'));
    } else {
        wp_send_json_error(array('message' => 'Có lỗi xảy ra khi xóa chi tiêu.'));
    }
}

function update_expense_status_callback() {
    check_ajax_referer('update_expense_status_nonce', 'nonce');

    if (!is_inova_admin()) {
        wp_send_json_error(array('message' => 'Không có quyền truy cập.'));
    }

    $expense_id = intval($_POST['expense_id']);
    $status = sanitize_text_field($_POST['status']);

    if (empty($expense_id)) {
        wp_send_json_error(array('message' => 'ID chi tiêu không hợp lệ.'));
    }

    $valid_statuses = array('ACTIVE', 'INACTIVE', 'EXPIRED', 'SUSPENDED');
    if (!in_array($status, $valid_statuses)) {
        wp_send_json_error(array('message' => 'Trạng thái không hợp lệ.'));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'im_recurring_expenses';

    $updated = $wpdb->update(
        $table,
        array('status' => $status),
        array('id' => $expense_id),
        array('%s'),
        array('%d')
    );
    
    if ($updated !== false) {
        wp_send_json_success(array('message' => 'Cập nhật trạng thái thành công.'));
    } else {
        wp_send_json_error(array('message' => 'Có lỗi xảy ra khi cập nhật trạng thái.'));
    }
    }
    
    /**
    * Renew domain for 1 more year
    * AJAX handler for domain renewal
    */
    function renew_domain_one_year_ajax() {
    check_ajax_referer('renew_domain_nonce', 'nonce');
    
    $domain_id = intval($_POST['domain_id']);
    
    if (!$domain_id) {
        wp_send_json_error(array('message' => 'Domain ID không hợp lệ.'));
        return;
    }
    
    global $wpdb;
    $domains_table = $wpdb->prefix . 'im_domains';
    
    // Get current domain data
    $domain = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $domains_table WHERE id = %d",
        $domain_id
    ));
    
    if (!$domain) {
        wp_send_json_error(array('message' => 'Không tìm thấy tên miền.'));
        return;
    }
    
    // Calculate new expiry date (add 1 year)
    $current_expiry = $domain->expiry_date;
    $new_expiry = date('Y-m-d', strtotime($current_expiry . ' +1 year'));
    
    // Update expiry date
    $update_result = $wpdb->update(
        $domains_table,
        array('expiry_date' => $new_expiry),
        array('id' => $domain_id),
        array('%s'),
        array('%d')
    );
    
    if ($update_result === false) {
        wp_send_json_error(array(
            'message' => 'Không thể cập nhật ngày hết hạn. SQL Error: ' . $wpdb->last_error
        ));
        return;
    }
    
    // Log the renewal
    error_log("Domain Renewal: Domain ID {$domain_id} ({$domain->domain_name}) renewed from {$current_expiry} to {$new_expiry}");
    
    wp_send_json_success(array(
        'message' => 'Đã gia hạn thành công tên miền ' . $domain->domain_name,
        'old_expiry_date' => $current_expiry,
        'new_expiry_date' => $new_expiry,
        'domain_name' => $domain->domain_name
    ));
    }
    
    
    /* ===== REGISTER AJAX HANDLERS ===== */
/* All add_action calls for AJAX handlers defined in this file */

// Cart operations
add_action('wp_ajax_add_to_cart', 'add_to_cart_ajax');
add_action('wp_ajax_nopriv_add_to_cart', 'add_to_cart_ajax');

add_action('wp_ajax_add_website_service_to_cart', 'add_website_service_to_cart_ajax');
add_action('wp_ajax_nopriv_add_website_service_to_cart', 'add_website_service_to_cart_ajax');

add_action('wp_ajax_add_website_to_cart', 'add_website_to_cart_ajax');
add_action('wp_ajax_nopriv_add_website_to_cart', 'add_website_to_cart_ajax');

add_action('wp_ajax_delete_website_service', 'delete_website_service_ajax');
add_action('wp_ajax_nopriv_delete_website_service', 'delete_website_service_ajax');

add_action('wp_ajax_remove_from_cart', 'remove_from_cart_ajax');
add_action('wp_ajax_nopriv_remove_from_cart', 'remove_from_cart_ajax');

add_action('wp_ajax_get_cart_count', 'get_cart_count_ajax');
add_action('wp_ajax_nopriv_get_cart_count', 'get_cart_count_ajax');

add_action('wp_ajax_get_customer_services', 'get_customer_services_ajax');
add_action('wp_ajax_nopriv_get_customer_services', 'get_customer_services_ajax');

// Domain operations
add_action('wp_ajax_fetch_domain_info', 'fetch_domain_info_from_apilayer');
add_action('wp_ajax_nopriv_fetch_domain_info', 'fetch_domain_info_from_apilayer');

add_action('wp_ajax_renew_domain_one_year', 'renew_domain_one_year_ajax');
add_action('wp_ajax_nopriv_renew_domain_one_year', 'renew_domain_one_year_ajax');

// Email notifications
add_action('wp_ajax_update_email_notification_setting', 'update_email_notification_setting_callback');
add_action('wp_ajax_delete_contact', 'delete_contact_callback');
add_action('wp_ajax_update_my_vat_invoice_setting', 'update_my_vat_invoice_setting_callback');
add_action('wp_ajax_update_vat_invoice_setting', 'update_vat_invoice_setting_callback');
add_action('wp_ajax_get_vat_invoice_list', 'get_vat_invoice_list_callback');
add_action('wp_ajax_save_vat_invoice_for_user', 'save_vat_invoice_for_user_callback');
add_action('wp_ajax_delete_vat_invoice', 'delete_vat_invoice_callback');

// User management
add_action('wp_ajax_delete_website_user', 'delete_website_user_callback');
add_action('wp_ajax_delete_domain_user', 'delete_domain_user_callback');
add_action('wp_ajax_change_user_password', 'change_user_password_callback');
add_action('wp_ajax_nopriv_change_user_password', 'change_user_password_callback');
add_action('wp_ajax_update_user_profile', 'update_user_profile_callback');
add_action('wp_ajax_nopriv_update_user_profile', 'update_user_profile_callback');

// Partner management
add_action('wp_ajax_get_all_partners', 'get_all_partners_callback');
add_action('wp_ajax_get_partner_discount_rates', 'get_partner_discount_rates_callback');
add_action('wp_ajax_get_partner_discount_rate', 'get_partner_discount_rate_callback');
add_action('wp_ajax_save_partner_discount_rate', 'save_partner_discount_rate_callback');
add_action('wp_ajax_update_partner_discount_rate', 'update_partner_discount_rate_callback');
add_action('wp_ajax_delete_partner_discount_rate', 'delete_partner_discount_rate_callback');

// Commission management
add_action('wp_ajax_get_partner_commissions', 'get_partner_commissions_callback');
add_action('wp_ajax_nopriv_get_partner_commissions', 'get_partner_commissions_callback');
add_action('wp_ajax_get_commission_detail', 'get_commission_detail_callback');
add_action('wp_ajax_nopriv_get_commission_detail', 'get_commission_detail_callback');
add_action('wp_ajax_update_commission_status', 'update_commission_status_callback');
add_action('wp_ajax_bulk_update_commission_status', 'bulk_update_commission_status_callback');

// Invoice & maintenance
add_action('wp_ajax_register_maintenance_package', 'register_maintenance_package_callback');
add_action('wp_ajax_nopriv_register_maintenance_package', 'register_maintenance_package_callback');
add_action('wp_ajax_load_merge_invoices', 'load_merge_invoices_callback');
add_action('wp_ajax_nopriv_load_merge_invoices', 'load_merge_invoices_callback');
add_action('wp_ajax_merge_invoices', 'merge_invoices_callback');
add_action('wp_ajax_nopriv_merge_invoices', 'merge_invoices_callback');

/**
 * AJAX handler - Get monthly invoice details
 * Shows all invoices for a specific month/year
 */
function get_monthly_invoice_details_callback()
{
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'im_invoices';
    $users_table = $wpdb->prefix . 'im_users';

    // Get parameters
    $month = intval($_POST['month'] ?? 0);
    $year = intval($_POST['year'] ?? 0);

    if ($month < 1 || $month > 12 || $year < 2000 || $year > date('Y') + 1) {
        wp_send_json_error(array('message' => 'Tháng hoặc năm không hợp lệ'));
    }

    // Get current user's Inova ID for permission filtering
    $current_user_id = get_current_user_id();
    $inova_user_id = get_user_inova_id($current_user_id);

    // Get permission where clause
    $permission_where = get_user_permission_where_clause('u', 'id');

    // Get invoices for the month
    $invoices = $wpdb->get_results("
        SELECT 
            i.id,
            i.invoice_code,
            i.total_amount,
            i.paid_amount,
            u.name AS customer_name,
            u.user_code
        FROM {$invoices_table} i
        LEFT JOIN {$users_table} u ON i.user_id = u.id
        WHERE YEAR(i.invoice_date) = {$year}
        AND MONTH(i.invoice_date) = {$month}
        AND i.status = 'PAID'
        {$permission_where}
        ORDER BY i.invoice_date DESC
    ");

    // Get statistics for the month
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(i.id) AS total_invoices,
            SUM(i.total_amount) AS total_amount,
            SUM(i.paid_amount) AS paid_amount
        FROM {$invoices_table} i
        LEFT JOIN {$users_table} u ON i.user_id = u.id
        WHERE YEAR(i.invoice_date) = {$year}
        AND MONTH(i.invoice_date) = {$month}
        AND i.status = 'PAID'
        {$permission_where}
    ");

    // Calculate payment percentage
    $payment_percentage = 0;
    if ($stats && $stats->total_amount > 0) {
        $payment_percentage = round(($stats->paid_amount / $stats->total_amount) * 100);
    }

    wp_send_json_success(array(
        'invoices' => $invoices,
        'total_invoices' => intval($stats->total_invoices ?? 0),
        'total_amount' => intval($stats->total_amount ?? 0),
        'paid_amount' => intval($stats->paid_amount ?? 0),
        'payment_percentage' => $payment_percentage
    ));
}

/**
 * AJAX handler - Export revenue report
 * Exports monthly revenue data as CSV file
 */
function export_revenue_report_callback()
{
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'im_invoices';
    $users_table = $wpdb->prefix . 'im_users';

    $year = intval($_GET['year'] ?? date('Y'));
    $partner_id = intval($_GET['partner'] ?? 0);

    // Get current user's Inova ID for permission filtering
    $current_user_id = get_current_user_id();
    $inova_user_id = get_user_inova_id($current_user_id);

    // Get permission where clause
    $permission_where = get_user_permission_where_clause('u', 'id');

    // Build WHERE clause
    $where_clause = "WHERE YEAR(i.invoice_date) = {$year} 
                     AND i.status = 'PAID' 
                     {$permission_where}";

    if ($partner_id > 0) {
        $where_clause .= $wpdb->prepare(" AND i.partner_id = %d", $partner_id);
    }

    // Get monthly statistics
    $monthly_stats = $wpdb->get_results("
        SELECT 
            MONTH(i.invoice_date) AS month_num,
            COUNT(i.id) AS invoice_count,
            SUM(i.total_amount) AS total_revenue,
            SUM(CASE WHEN i.payment_date IS NOT NULL THEN i.total_amount ELSE 0 END) AS paid_amount,
            SUM(CASE WHEN i.payment_date IS NULL THEN i.total_amount ELSE 0 END) AS unpaid_amount
        FROM {$invoices_table} i
        LEFT JOIN {$users_table} u ON i.user_id = u.id
        {$where_clause}
        GROUP BY MONTH(i.invoice_date)
        ORDER BY month_num ASC
    ");

    // Create CSV
    $filename = 'doanh-thu-' . $year . '-' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header row
    fputcsv($output, array(
        'Tháng',
        'Số HĐ',
        'Tổng Tiền',
        'Đã TT',
        'Chưa TT'
    ), ',');

    // Month names
    $month_names = array(
        1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3', 4 => 'Tháng 4',
        5 => 'Tháng 5', 6 => 'Tháng 6', 7 => 'Tháng 7', 8 => 'Tháng 8',
        9 => 'Tháng 9', 10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
    );

    // Create array for all 12 months
    $stats_by_month = array();
    foreach ($monthly_stats as $stat) {
        $stats_by_month[$stat->month_num] = $stat;
    }

    // Data rows
    for ($m = 1; $m <= 12; $m++) {
        $stat = isset($stats_by_month[$m]) ? $stats_by_month[$m] : null;
        fputcsv($output, array(
            $month_names[$m],
            $stat ? intval($stat->invoice_count) : 0,
            $stat ? intval($stat->total_revenue) : 0,
            $stat ? intval($stat->paid_amount) : 0,
            $stat ? intval($stat->unpaid_amount) : 0
        ), ',');
    }

    fclose($output);
    exit;
}

/**
 * Edit recurring expense - POST handler
 */
function edit_expense_post_callback() {
    if (!is_inova_admin()) {
        wp_redirect(home_url('/'));
        exit;
    }

    if (isset($_POST['post_expense_field']) && wp_verify_nonce($_POST['post_expense_field'], 'post_expense')) {
        global $wpdb;
        $table = $wpdb->prefix . 'im_recurring_expenses';

        $expense_id = intval($_POST['expense_id']);
        $name = sanitize_text_field($_POST['name']);
        $category = sanitize_text_field($_POST['category']);
        $vendor = sanitize_text_field($_POST['vendor']);
        $amount = intval($_POST['amount']);
        $status = sanitize_text_field($_POST['status']);
        $note = sanitize_textarea_field($_POST['note']);

        // Validation
        if (empty($name)) {
            wp_redirect(home_url('/edit-expense/?expense_id=' . $expense_id . '&error=name'));
            exit;
        } elseif ($amount <= 0) {
            wp_redirect(home_url('/edit-expense/?expense_id=' . $expense_id . '&error=amount'));
            exit;
        }

        // Start date handling
        if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
            // Convert from dd/mm/yyyy to yyyy-mm-dd
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['start_date'])));
            
            // End date handling (optional)
            $end_date = null;
            if (isset($_POST['end_date']) && !empty($_POST['end_date'])) {
                $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['end_date'])));
            }

            if (!empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
                wp_redirect(home_url('/edit-expense/?expense_id=' . $expense_id . '&error=date'));
                exit;
            }

            $data = array(
                'name' => $name,
                'category' => $category,
                'vendor' => $vendor,
                'amount' => $amount,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => $status,
                'note' => $note
            );
            
            $update = $wpdb->update(
                $table,
                $data,
                array('id' => $expense_id),
                array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'),
                array('%d')
            );

            if ($update !== false) {
                // Redirect to previous page or expense list
                $redirect_url = isset($_POST['redirect_url']) && !empty($_POST['redirect_url']) 
                    ? esc_url($_POST['redirect_url']) 
                    : home_url('/danh-sach-chi-tieu/');
                wp_redirect($redirect_url);
                exit;
            } else {
                wp_redirect(home_url('/edit-expense/?expense_id=' . $expense_id . '&error=update'));
                exit;
            }
        } else {
            wp_redirect(home_url('/edit-expense/?expense_id=' . $expense_id . '&error=start_date'));
            exit;
        }
    }

    wp_redirect(home_url('/danh-sach-chi-tieu/'));
    exit;
}

// Expense management
add_action('wp_ajax_delete_expense', 'delete_expense_callback');
add_action('wp_ajax_update_expense_status', 'update_expense_status_callback');
add_action('admin_post_edit_expense_post', 'edit_expense_post_callback');

// Revenue Statistics
add_action('wp_ajax_get_monthly_invoice_details', 'get_monthly_invoice_details_callback');
add_action('wp_ajax_export_revenue_report', 'export_revenue_report_callback');
