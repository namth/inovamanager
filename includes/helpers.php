<?php
/* Helper Functions - Imported from functions.php */

function im_encrypt_password($data)
{
    if (empty($data)) {
        return $data;
    }

    // Fixed encryption key (base64 encoded 32-byte key for AES-256)
    $key = base64_decode('WDJzUWxSM3d1L3VkZmhzajlrMjI3M2lvcXc4ZWZnMmM=');
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

    // Combine IV + encrypted data and encode in base64 for storage
    return base64_encode($iv . $encrypted);
}

function im_decrypt_password($data)
{
    if (empty($data)) {
        return $data;
    }

    // Fixed encryption key (same as im_encrypt_password)
    $key = base64_decode('WDJzUWxSM3d1L3VkZmhzajlrMjI3M2lvcXc4ZWZnMmM=');

    // Decode the base64 data
    $data = base64_decode($data);

    // Extract IV and encrypted data
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);

    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}

function enqueue_js_css()
{
    /* 
     * Enqueue style css file
     */
    // Bootstrap CSS from CDN (required for Bootstrap components)
    wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0', 'all');
    wp_enqueue_style('feather', get_template_directory_uri() . '/assets/vendors/feather/feather.css');
    wp_enqueue_style('mdi', get_template_directory_uri() . '/assets/vendors/mdi/css/materialdesignicons.min.css');
    wp_enqueue_style('themify-icons', get_template_directory_uri() . '/assets/vendors/ti-icons/css/themify-icons.css');
    wp_enqueue_style('font-awesome', get_template_directory_uri() . '/assets/vendors/font-awesome/css/font-awesome.min.css');
    wp_enqueue_style('typicons', get_template_directory_uri() . '/assets/vendors/typicons/typicons.css');
    wp_enqueue_style('simple-line-icons', get_template_directory_uri() . '/assets/vendors/simple-line-icons/css/simple-line-icons.css');
    wp_enqueue_style('vendor.bundle.base', get_template_directory_uri() . '/assets/vendors/css/vendor.bundle.base.css');
    wp_enqueue_style('bootstrap-datepicker', get_template_directory_uri() . '/assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css');
    /* Plugin css for page have select2 element */
    wp_enqueue_style('select2', get_template_directory_uri() . '/assets/vendors/select2/select2.min.css');
    wp_enqueue_style('bootstrap-select2', get_template_directory_uri() . '/assets/vendors/select2-bootstrap-theme/select2-bootstrap.min.css');
    wp_enqueue_style('main-style', get_template_directory_uri() . '/assets/css/style.css');
    wp_enqueue_style('style', get_stylesheet_uri(), array(), '1.4', 'all');

    /*
     * Enqueue js file
     */
    wp_enqueue_script('jquery');
    // Bootstrap Bundle (includes Popper.js for modals) - must load after jquery
    wp_enqueue_script('bootstrap-bundle', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
    // Note: vendor.bundle.base.js removed - Bootstrap Bundle CDN provides all needed functionality
    wp_enqueue_script('bootstrap-datepicker', get_template_directory_uri() . '/assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js', array('jquery'), '1.0', true);
    wp_enqueue_script('off-canvas', get_template_directory_uri() . '/assets/js/off-canvas.js', array('jquery'), '1.0', true);
    wp_enqueue_script('template', get_template_directory_uri() . '/assets/js/template.js', array('jquery'), '1.0', true);
    wp_enqueue_script('settings', get_template_directory_uri() . '/assets/js/settings.js', array('jquery'), '1.0', true);
    wp_enqueue_script('hoverable-collapse', get_template_directory_uri() . '/assets/js/hoverable-collapse.js', array('jquery'), '1.0', true);
    wp_enqueue_script('todolist', get_template_directory_uri() . '/assets/js/todolist.js', array('jquery'), '1.0', true);
    wp_enqueue_script('phosphor-icon', 'https://unpkg.com/@phosphor-icons/web', array(), '1.0', true);
    wp_enqueue_script('select2.base', get_template_directory_uri() . '/assets/vendors/select2/select2.min.js', array('jquery'), '1.0', true);
    wp_enqueue_script('select2', get_template_directory_uri() . '/assets/js/select2.js', array('jquery', 'select2.base'), '1.0', true);
    wp_enqueue_script('datepicker', get_template_directory_uri() . '/assets/js/datepicker.js', array('jquery', 'bootstrap-datepicker'), '1.0', true);
    // Chart.js for charts and graphs
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true);
    wp_enqueue_script('custom', get_template_directory_uri() . '/assets/js/custom.js', array('jquery', 'bootstrap-bundle'), '1.0', true);
    wp_enqueue_script('revenue-stats', get_template_directory_uri() . '/assets/js/revenue-stats.js', array('jquery', 'bootstrap-bundle'), '1.0', true);
    wp_localize_script('custom', 'AJAX', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'vat_rates' => get_option('inova_vat_rates', array(
            'Hosting' => 10,
            'Domain' => 10,
            'Website' => 0,
            'Maintenance' => 0
        ))
    ));
}

function getLastId($table_name, $field_name)
{
    global $wpdb;
    $sql = "SELECT MAX($field_name) FROM $table_name";
    $result = $wpdb->get_var($sql);
    return $result;
}

function normalize_service_type_for_commission($service_type)
{
    $service_type = strtolower(trim($service_type));

    // Map various service type names to standard types
    switch ($service_type) {
        case 'hosting':
        case 'bulk_hostings':
            return 'hosting';
        case 'maintenance':
        case 'bulk_maintenances':
            return 'maintenance';
        case 'website_service':
        case 'website':
        case 'bulk_websites':
            return 'website_service';
        case 'domain':
        case 'bulk_domains':
            // Domains typically don't have commission rates, skip
            return '';
        default:
            return '';
    }
}

function format_service_type($service_type)
{
    $service_type = strtolower(trim($service_type));

    switch ($service_type) {
        case 'domain':
            return 'Domain';
        case 'hosting':
            return 'Hosting';
        case 'maintenance':
            return 'Maintenance';
        case 'website_service':
            return 'Website Service';
        default:
            return ucfirst($service_type);
    }
}

function get_vat_rate_for_service($service_type)
{
    $vat_rates = get_option('inova_vat_rates', array());

    if (isset($vat_rates[$service_type])) {
        return floatval($vat_rates[$service_type]);
    }

    return 0.00;
}

function get_current_vat_rates()
{
    $vat_rates = get_option('inova_vat_rates', array());

    // Convert to float
    foreach ($vat_rates as $key => $value) {
        $vat_rates[$key] = floatval($value);
    }

    return $vat_rates;
}

function update_vat_rate($service_type, $vat_rate)
{
    $vat_rates = get_option('inova_vat_rates', array());
    $vat_rates[$service_type] = floatval($vat_rate);

    return update_option('inova_vat_rates', $vat_rates);
}

function calculate_vat_amount($item_total, $vat_rate)
{
    return round($item_total * $vat_rate / 100);
}

function get_relationship($partnerID, $type = "full")
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'bopartner_relationship';
    $partner_table = $wpdb->prefix . 'bopartner';
    $relationships = $wpdb->get_results("SELECT * FROM $table_name WHERE partnerID_1 = $partnerID OR partnerID_2 = $partnerID");
    $partner1 = array_column($relationships, 'partnerID_1');
    $partner2 = array_column($relationships, 'partnerID_2');
    $partners = array_merge($partner1, $partner2);

    // remove partner id of current partner
    $partners = array_diff(array_unique($partners), array($partnerID));

    switch ($type) {
        case 'full':
            return $wpdb->get_results("SELECT * FROM $partner_table WHERE partnerID IN (" . implode(',', $partners) . ")");
        case 'array_id':
            return $partners;
        case 'string_id':
            return implode(',', $partners);
    }
}

function make_cloudflare_request($endpoint)
{
    $api_token = get_option('cloudflare_api_token');
    $account_id = get_option('cloudflare_account_id');

    if (empty($api_token) || empty($account_id)) {
        return new WP_Error('missing_credentials', 'Cloudflare API Token hoặc Account ID chưa được cấu hình.');
    }

    $url = "https://api.cloudflare.com/client/v4/{$endpoint}";

    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type' => 'application/json',
        ),
        'timeout' => 20,
    ));

    if (is_wp_error($response)) {
        // Log the WP_Error for debugging
        error_log('Cloudflare API WP_Error: ' . $response->get_error_message());
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    // Log response for debugging
    error_log('Cloudflare API Response Code: ' . $response_code);
    error_log('Cloudflare API Response Body: ' . substr($body, 0, 500));

    $data = json_decode($body, true);

    // Check for HTTP errors
    if ($response_code !== 200) {
        $error_message = 'HTTP Error ' . $response_code;
        if (isset($data['errors'][0]['message'])) {
            $error_message .= ': ' . $data['errors'][0]['message'];
        }
        return new WP_Error('cloudflare_http_error', $error_message);
    }

    // Check for JSON decode error
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_error', 'Không thể parse JSON response: ' . json_last_error_msg());
    }

    // Check Cloudflare API success flag
    if (!isset($data['success']) || !$data['success']) {
        $error_message = isset($data['errors'][0]['message']) ? $data['errors'][0]['message'] : 'Lỗi không xác định từ Cloudflare.';

        // Include all error details if available
        if (isset($data['errors']) && is_array($data['errors'])) {
            $all_errors = array_map(function ($err) {
                return isset($err['message']) ? $err['message'] : 'Unknown error';
            }, $data['errors']);
            $error_message = implode('; ', $all_errors);
        }

        return new WP_Error('cloudflare_error', $error_message);
    }

    return $data['result'];
}

function generate_payment_qr_code($amount, $add_info = '', $requires_vat_invoice = 0)
{
    // Select appropriate bank account based on VAT invoice requirement
    if ($requires_vat_invoice) {
        // Use account for customers WITH VAT invoice
        $bank_code = get_option('payment_bank_code_with_vat');
        $account_number = get_option('payment_account_number_with_vat');
    } else {
        // Use account for customers WITHOUT VAT invoice
        $bank_code = get_option('payment_bank_code_no_vat');
        $account_number = get_option('payment_account_number_no_vat');
    }

    // Fallback to old keys if new keys are not set (backward compatibility)
    if (empty($bank_code) || empty($account_number)) {
        $bank_code = get_option('payment_bank_code');
        $account_number = get_option('payment_account_number');
    }

    if (empty($bank_code) || empty($account_number)) {
        return false;
    }

    // Build QR code URL using VietQR API
    // Format: https://img.vietqr.io/image/{BANK_CODE}-{ACCOUNT_NUMBER}-{TEMPLATE}.jpg
    // Template options: compact, compact2, print, qr_only
    $template = 'compact2';

    $url = sprintf(
        'https://img.vietqr.io/image/%s-%s-%s.jpg',
        $bank_code,
        $account_number,
        $template
    );

    // Add query parameters
    $params = array();

    if (!empty($amount) && $amount > 0) {
        $params['amount'] = number_format($amount, 0, '', '');
    }

    if (!empty($add_info)) {
        $params['addInfo'] = $add_info;
    }

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    return $url;
}

function get_public_invoice_url($invoice_id)
{
    global $wpdb;
    $invoice_table = $wpdb->prefix . 'im_invoices';

    // Get invoice to extract creation date
    $invoice = $wpdb->get_row($wpdb->prepare("
        SELECT id, created_at FROM $invoice_table WHERE id = %d
    ", $invoice_id));

    if (!$invoice) {
        return '';
    }

    // Generate token based on invoice ID and creation date
    $token = base64_encode($invoice->id . '|' . $invoice->created_at);

    // Build URL with token
    $url = home_url('/public-invoice/?invoice_id=' . $invoice_id . '&token=' . urlencode($token));

    return $url;
}

function get_user_inova_id($user_id = null)
{
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $inova_id = get_user_meta($user_id, 'inova_id', true);
    return $inova_id ? intval($inova_id) : false;
}

function get_inova_user($user_id = null)
{
    global $wpdb;

    $inova_id = get_user_inova_id($user_id);
    if (!$inova_id) {
        return null;
    }

    $users_table = $wpdb->prefix . 'im_users';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $users_table WHERE id = %d AND status = 'ACTIVE'",
        $inova_id
    ));
}

function get_inova_user_by_id($inova_user_id = null)
{
    global $wpdb;

    if (!$inova_user_id) {
        return null;
    }

    $users_table = $wpdb->prefix . 'im_users';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $users_table WHERE id = %d AND status = 'ACTIVE'",
        $inova_user_id
    ));
}

function is_inova_admin()
{
    return current_user_can('administrator');
}

function get_inova_user_type()
{
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return null;
    }

    $inova_user = get_inova_user($current_user_id);
    return $inova_user ? $inova_user->user_type : null;
}

function can_user_view_item($owner_user_id, $partner_id = null)
{
    // Admin can view everything
    if (is_inova_admin()) {
        return true;
    }

    // Get current WordPress user ID
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return false; // Not logged in
    }

    // Get Inova user record
    $inova_user = get_inova_user($current_user_id);
    if (!$inova_user || !isset($inova_user->id)) {
        return false; // No inova_id mapping
    }

    // Simple logic: User can view if inova_id = owner_user_id OR inova_id = partner_id
    if ($inova_user->id == $owner_user_id) {
        return true; // Owner can view
    }

    if ($partner_id && $inova_user->id == $partner_id) {
        return true; // Partner can view
    }

    // Check if current user manages the owner via partner relationship
    $owner_record = get_inova_user_by_id($owner_user_id);
    if ($owner_record && !empty($owner_record->partner_id) && intval($owner_record->partner_id) === intval($inova_user->id)) {
        return true;
    }

    return false;
}

function get_user_permission_where_clause($table_alias = '', $owner_field = 'owner_user_id', $partner_field = null)
{
    global $wpdb;

    // Admin sees everything
    if (is_inova_admin()) {
        return '';
    }

    // Get current WordPress user ID
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return ' AND 1=0'; // Not logged in
    }

    // Get Inova user record
    $inova_user = get_inova_user($current_user_id);
    if (!$inova_user || !isset($inova_user->id)) {
        return ' AND 1=0'; // No inova_id mapping
    }

    $prefix = $table_alias ? $table_alias . '.' : '';
    $conditions = array();

    // Owner access
    $conditions[] = $wpdb->prepare(
        "{$prefix}{$owner_field} = %d",
        $inova_user->id
    );

    // Direct partner field access if available
    if ($partner_field) {
        $conditions[] = $wpdb->prepare(
            "{$prefix}{$partner_field} = %d",
            $inova_user->id
        );
    }

    // Managed owner access
    $conditions[] = $wpdb->prepare(
        "{$prefix}{$owner_field} IN (
            SELECT managed_users.id
            FROM {$wpdb->prefix}im_users managed_users
            WHERE managed_users.partner_id = %d
        )",
        $inova_user->id
    );

    return ' AND (' . implode(' OR ', $conditions) . ')';
}

function get_website_permission_where_clause($table_alias = 'w')
{
    global $wpdb;

    if (is_inova_admin()) {
        return '';
    }

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return ' AND 1=0';
    }

    $inova_user = get_inova_user($current_user_id);
    if (!$inova_user || !isset($inova_user->id)) {
        return ' AND 1=0';
    }

    $base_clause = get_user_permission_where_clause($table_alias, 'owner_user_id');

    $prefix = $table_alias ? $table_alias . '.' : '';

    $hosting_condition = $wpdb->prepare(
        "EXISTS (SELECT 1 FROM {$wpdb->prefix}im_hostings website_hostings WHERE website_hostings.id = {$prefix}hosting_id AND website_hostings.partner_id = %d)",
        $inova_user->id
    );

    $maintenance_condition = $wpdb->prepare(
        "EXISTS (SELECT 1 FROM {$wpdb->prefix}im_maintenance_packages website_maintenance WHERE website_maintenance.id = {$prefix}maintenance_package_id AND website_maintenance.partner_id = %d)",
        $inova_user->id
    );

    if ($base_clause === '') {
        return ' AND (' . $hosting_condition . ' OR ' . $maintenance_condition . ')';
    }

    if ($base_clause === ' AND 1=0') {
        return $base_clause;
    }

    $trimmed_clause = rtrim($base_clause);
    if (substr($trimmed_clause, -1) === ')') {
        $trimmed_clause = substr($trimmed_clause, 0, -1);
        $trimmed_clause .= ' OR ' . $hosting_condition . ' OR ' . $maintenance_condition . ')';
        return $trimmed_clause;
    }

    return $base_clause . ' OR ' . $hosting_condition . ' OR ' . $maintenance_condition;
}

function convert_date_format($date_string)
{
    $parts = explode('/', $date_string);
    if (count($parts) === 3) {
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return $date_string;
}

function get_virtual_page_template_mapping()
{
    return array(
        // Core Pages
        'dashboard' => 'dashboard.php',
        'homepage' => 'homepage.php',
        'home' => 'homepage.php',
        'history' => 'history.php',
        'login' => 'login.php',

        // Website Management
        'website-list' => 'website_list.php',
        'websites' => 'website_list.php',
        'list-website' => 'website_list.php',
        'addnew-website' => 'addnew_website.php',
        'add-website' => 'addnew_website.php',
        'addnew-website-simple' => 'addnew_website_simple.php',
        'edit-website' => 'edit_website.php',
        'sua-website' => 'edit_website.php',
        'edit-website-simple' => 'edit_website_simple.php',
        'sua-website-simple' => 'edit_website_simple.php',
        'detail-website' => 'detail_website.php',
        'website' => 'detail_website.php',
        'delete-website' => 'xoa-website.php',
        'xoa-website' => 'xoa-website.php',
        'restore-website' => 'khoi-phuc-website.php',
        'khoi-phuc-website' => 'khoi-phuc-website.php',
        'attach-product-to-website' => 'attach_product_to_website.php',

        // Domain Management
        'domain-list' => 'domain_list.php',
        'danh-sach-ten-mien' => 'domain_list.php',
        'addnew-domain' => 'addnew_domain.php',
        'add-domain' => 'addnew_domain.php',
        'addnew-domain-simple' => 'addnew_domain_simple.php',
        'add-domain-simple' => 'addnew_domain_simple.php',
        'them-ten-mien' => 'addnew_domain_simple.php',
        'edit-domain' => 'edit_domain.php',
        'edit-domain-simple' => 'edit_domain_simple.php',
        'sua-domain-simple' => 'edit_domain_simple.php',
        'detail-domain' => 'detail_domain.php',

        // Hosting Management
        'hosting-list' => 'hosting_list.php',
        'hostings' => 'hosting_list.php',
        'addnew-hosting' => 'addnew_hosting.php',
        'add-hosting' => 'addnew_hosting.php',
        'edit-hosting' => 'edit_hosting.php',
        'detail-hosting' => 'detail_hosting.php',

        // Maintenance Management
        'maintenance-list' => 'maintenance_list.php',
        'maintenances' => 'maintenance_list.php',
        'addnew-maintenance' => 'addnew_maintenance.php',
        'add-maintenance' => 'addnew_maintenance.php',
        'edit-maintenance' => 'edit_maintenance.php',
        'detail-maintenance' => 'detail_maintenance.php',
        'register-maintenance' => 'register_maintenance.php',
        'dang-ky-bao-tri' => 'register_maintenance.php',

        // Service Management
        'service-list' => 'service_list.php',
        'services' => 'service_list.php',
        'addnew-service' => 'addnew_services.php',
        'add-service' => 'addnew_services.php',
        'service-request' => 'service_request.php',
        'service-quotation' => 'service_quotation.php',
        'service-execution' => 'service_execution.php',
        'service-completion' => 'service_completion.php',
        'service-invoice' => 'service_invoice.php',

        // Product Catalog Management
        'product-catalog-list' => 'product_catalog_list.php',
        'product-list' => 'product_catalog_list.php',
        'products' => 'product_catalog_list.php',
        'addnew-product-catalog' => 'addnew_product_catalog.php',
        'add-product-catalog' => 'addnew_product_catalog.php',
        'add-product' => 'addnew_product_catalog.php',
        'edit-product-catalog' => 'edit_product_catalog.php',
        'edit-product' => 'edit_product_catalog.php',

        // VAT Settings
        'vat-settings' => 'vat_invoice_settings.php',
        'cau-hinh-vat' => 'vat_invoice_settings.php',

        // VAT Invoice Settings
        'cau-hinh-hoa-don-do' => 'vat_invoice_settings.php',
        'cau-hinh-hoa-don-do-ca-nhan' => 'my_vat_invoice_settings.php',

        // Test Pages
        'test-page' => 'test_page.php',
        'debug-user-info' => 'debug_user_info.php',

        // Email Notification Settings
        'email-notification-settings' => 'email_notification_settings.php',
        'cau-hinh-email-thong-bao' => 'email_notification_settings.php',

        // Contact Management
        'addnew-contact' => 'addnew_contact.php',
        'add-contact' => 'addnew_contact.php',
        'edit-contact' => 'edit_contact.php',

        // Partner Management
        'partner-list' => 'list_partner.php',
        'list-partner' => 'list_partner.php',
        'partners' => 'list_partner.php',
        'addnew-partner' => 'addnew_partner.php',
        'add-partner' => 'addnew_partner.php',
        'edit-partner' => 'edit_partner.php',
        'detail-partner' => 'detail_partner.php',

        // Partner Commission Management
        'partner-discount-rates' => 'partner_discount_rates.php',
        'quan-ly-chiet-khau' => 'partner_discount_rates.php',
        'partner-commissions' => 'partner_commissions.php',
        'quan-ly-hoa-hong' => 'partner_commissions.php',


        // Invoice Management
        'invoice-list' => 'list_invoice.php',
        'list-invoice' => 'list_invoice.php',
        'invoices' => 'list_invoice.php',
        'danh-sach-hoa-don' => 'list_invoice.php',
        'add-invoice' => 'add_invoice.php',
        'detail-invoice' => 'detail_invoice.php',
        'chi-tiet-hoa-don' => 'detail_invoice.php',
        'invoice-success' => 'invoice_success.php',
        'public-invoice' => 'public_invoice.php',
        'xem-hoa-don' => 'public_invoice.php',

        // Revenue Statistics
        'revenue-stats' => 'revenue_stats.php',
        'thong-ke-doanh-thu' => 'revenue_stats.php',
        'revenue-statistics' => 'revenue_stats.php',
        'thong-ke' => 'revenue_stats.php',

        // Cart Management
        'gio-hang' => 'cart.php',
        'cart' => 'cart.php',

        // Contract Management
        'create-contract' => 'create_contract.php',
        'contract' => 'create_contract.php',

        // API & Settings
        'api' => 'api.php',
        'api-settings' => 'api-settings.php',
        'api-examples' => 'api-examples.php',
        'system-settings' => 'system_settings.php',
        'cai-dat-he-thong' => 'system_settings.php',
        'user-mapping' => 'user_mapping_admin.php',
        'quan-ly-user' => 'user_mapping_admin.php',

        // Cloudflare
        'cloudflare-import' => 'cloudflare_import.php',
        'them-nhanh-tu-cloudflare' => 'cloudflare_import.php',

        // Recurring Expenses Management
        'danh-sach-chi-tieu' => 'expense_list.php',
        'expense-list' => 'expense_list.php',
        'them-chi-tieu' => 'addnew_expense.php',
        'them-moi-chi-tieu' => 'addnew_expense.php',
        'add-expense' => 'addnew_expense.php',
        'sua-chi-tieu' => 'edit_expense.php',
        'chinh-sua-chi-tieu' => 'edit_expense.php',
        'edit-expense' => 'edit_expense.php',

        // User Account
        'edit-profile' => 'edit_profile.php',
        'sua-thong-tin' => 'edit_profile.php',
        'change-password' => 'change_password.php',
        'doi-mat-khau' => 'change_password.php',
    );
}

function get_available_templates()
{
    $theme_dir = get_template_directory();
    $templates = array();

    // Lấy tất cả file .php trong thư mục theme
    $files = glob($theme_dir . '/*.php');

    foreach ($files as $file) {
        $filename = basename($file);

        // Bỏ qua các file hệ thống
        $system_files = array('functions.php', 'index.php', 'header.php', 'footer.php', 'sidebar.php', 'style.css');
        if (!in_array($filename, $system_files)) {
            $templates[] = $filename;
        }
    }

    return $templates;
}

function debug_page_template_info()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Get current request URI
    $request_uri = trim($_SERVER['REQUEST_URI'], '/');
    $request_uri = explode('?', $request_uri)[0]; // Remove query params

    // Remove site path if WordPress is in subdirectory
    $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path && strpos($request_uri, $home_path) === 0) {
        $request_uri = substr($request_uri, strlen($home_path) + 1);
    }

    $template_mapping = array(
        'dashboard' => 'page-dashboard.php',
        'website-list' => 'page-website-list.php',
        'website' => 'page-website.php',
        'hosting-list' => 'page-hosting-list.php',
        'hosting' => 'page-hosting.php',
        'domain-list' => 'page-domain-list.php',
        'domain' => 'page-domain.php',
        'provider-list' => 'page-provider-list.php',
        'provider' => 'page-provider.php'
    );
    $custom_mappings = get_option('inova_template_mappings', array());
    $all_mappings = array_merge($template_mapping, $custom_mappings);

    $suggested_template = isset($all_mappings[$request_uri]) ? $all_mappings[$request_uri] : 'Default/Not Found';

    echo "<!-- DEBUG: Request URI: $request_uri | Suggested Template: $suggested_template -->";
}

function add_template_management_menu()
{
    add_theme_page(
        'Template Management',
        'Template Management',
        'manage_options',
        'template-management',
        'template_management_page'
    );
}

function add_template_info_to_admin_bar($wp_admin_bar)
{
    if (!current_user_can('manage_options') || !is_page()) {
        return;
    }

    global $wp_query;
    $page_slug = get_query_var('pagename');
    if (!$page_slug && isset($wp_query->queried_object->post_name)) {
        $page_slug = $wp_query->queried_object->post_name;
    }

    $template_mapping = array(
        'dashboard' => 'page-dashboard.php',
        'website-list' => 'page-website-list.php',
        'website' => 'page-website.php',
        'hosting-list' => 'page-hosting-list.php',
        'hosting' => 'page-hosting.php',
        'domain-list' => 'page-domain-list.php',
        'domain' => 'page-domain.php',
        'provider-list' => 'page-provider-list.php',
        'provider' => 'page-provider.php'
    );
    $custom_mappings = get_option('custom_template_mappings', array());
    $all_mappings = array_merge($template_mapping, $custom_mappings);

    $current_template = 'Default';
    if (isset($all_mappings[$page_slug])) {
        $current_template = $all_mappings[$page_slug];
    }

    $wp_admin_bar->add_node(array(
        'id' => 'template-info',
        'title' => '📄 Template: ' . $current_template,
        'href' => admin_url('themes.php?page=template-management'),
        'meta' => array(
            'title' => 'Current template: ' . $current_template . ' | Slug: ' . $page_slug
        )
    ));
}

function auto_assign_page_template($template)
{
    global $wp_query;

    // Get current request URI - MUST remove query string FIRST before trimming
    $full_request_uri = $_SERVER['REQUEST_URI'];
    $request_uri_parts = explode('?', $full_request_uri);
    $request_uri = $request_uri_parts[0]; // Remove query params first
    $request_uri = trim($request_uri, '/'); // Then trim slashes

    // Remove site path if WordPress is in subdirectory
    $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path && strpos($request_uri, $home_path) === 0) {
        $request_uri = substr($request_uri, strlen($home_path) + 1);
    }

    // Get template mapping using centralized function
    $all_mappings = get_virtual_page_template_mapping();
    $custom_mappings = get_option('inova_template_mappings', array());
    $all_mappings = array_merge($all_mappings, $custom_mappings);

    // Check if current request matches any slug
    if (isset($all_mappings[$request_uri])) {
        $template_file = get_template_directory() . '/' . $all_mappings[$request_uri];

        if (file_exists($template_file)) {
            // Set up virtual page query
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
            $wp_query->is_home = false;
            $wp_query->is_archive = false;
            $wp_query->is_category = false;
            $wp_query->is_404 = false;

            // Create virtual post object
            $virtual_post = new stdClass();
            $virtual_post->ID = 999999; // Use high number to avoid conflicts
            $virtual_post->post_author = 1;
            $virtual_post->post_date = current_time('mysql');
            $virtual_post->post_date_gmt = current_time('mysql', 1);
            $virtual_post->post_content = '';
            $virtual_post->post_title = ucwords(str_replace(['-', '_'], ' ', $request_uri));
            $virtual_post->post_excerpt = '';
            $virtual_post->post_status = 'publish';
            $virtual_post->comment_status = 'closed';
            $virtual_post->ping_status = 'closed';
            $virtual_post->post_password = '';
            $virtual_post->post_name = $request_uri;
            $virtual_post->to_ping = '';
            $virtual_post->pinged = '';
            $virtual_post->post_modified = current_time('mysql');
            $virtual_post->post_modified_gmt = current_time('mysql', 1);
            $virtual_post->post_content_filtered = '';
            $virtual_post->post_parent = 0;
            $virtual_post->guid = home_url('/' . $request_uri . '/');
            $virtual_post->menu_order = 0;
            $virtual_post->post_type = 'page';
            $virtual_post->post_mime_type = '';
            $virtual_post->comment_count = 0;
            $virtual_post->filter = 'raw';

            // Set the virtual post as the queried object
            $wp_query->queried_object = $virtual_post;
            $wp_query->queried_object_id = $virtual_post->ID;
            $wp_query->posts = array($virtual_post);
            $wp_query->found_posts = 1;
            $wp_query->post_count = 1;
            $wp_query->max_num_pages = 1;

            // Ensure query vars are properly populated for virtual pages
            // Parse query string and set query vars manually
            $full_request_uri = trim($_SERVER['REQUEST_URI'], '/');
            $query_string_parts = explode('?', $full_request_uri);
            if (isset($query_string_parts[1])) {
                parse_str($query_string_parts[1], $query_vars);
                foreach ($query_vars as $key => $value) {
                    $wp_query->set($key, $value);
                }
            }

            // Set global $post
            global $post;
            $post = $virtual_post;
            setup_postdata($post);

            return $template_file;
        }
    }

    return $template;
}

function handle_virtual_page_request()
{
    global $wp_query;

    // Get current request URI - MUST remove query string FIRST before trimming
    $full_request_uri = $_SERVER['REQUEST_URI'];
    $request_uri_parts = explode('?', $full_request_uri);
    $request_uri = $request_uri_parts[0]; // Remove query params first
    $request_uri = trim($request_uri, '/'); // Then trim slashes

    // Remove site path if WordPress is in subdirectory
    $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path && strpos($request_uri, $home_path) === 0) {
        $request_uri = substr($request_uri, strlen($home_path) + 1);
    }

    // Get template mapping using centralized function
    $all_mappings = get_virtual_page_template_mapping();
    $custom_mappings = get_option('inova_template_mappings', array());
    $all_mappings = array_merge($all_mappings, $custom_mappings);

    // If this is a virtual page request, prevent 404
    if (isset($all_mappings[$request_uri])) {
        $wp_query->is_404 = false;
        status_header(200);

        // Make sure WordPress knows this is a valid request
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_home = false;
        $wp_query->is_archive = false;
        $wp_query->is_category = false;
    }
}

function get_user_email_notification_settings($user_id)
{
    // Get user's custom settings
    $user_settings = get_user_meta($user_id, 'inova_email_notifications', true);

    // If user hasn't set preferences, use global defaults
    if (empty($user_settings)) {
        $user_settings = get_option('inova_email_notification_defaults', [
            'domain' => 1,
            'hosting' => 1,
            'maintenance' => 1
        ]);
    }

    // Return array of enabled service types
    $enabled_types = [];
    foreach ($user_settings as $service_type => $is_enabled) {
        if ($is_enabled) {
            $enabled_types[] = $service_type;
        }
    }

    return $enabled_types;
}

function get_expiry_email_subject($milestone)
{
    $subjects = [
        -30 => 'Thông báo: Dịch vụ của bạn sắp hết hạn trong 30 ngày',
        -7 => 'Cảnh báo: Dịch vụ của bạn sắp hết hạn trong 7 ngày',
        -3 => 'Khẩn cấp: Dịch vụ của bạn sắp hết hạn trong 3 ngày',
        0 => 'Dịch vụ của bạn đã hết hạn hôm nay',
        1 => 'Dịch vụ của bạn đã quá hạn 1 ngày',
        14 => 'Dịch vụ của bạn đã quá hạn 2 tuần'
    ];

    return isset($subjects[$milestone]) ? $subjects[$milestone] : 'Thông báo về dịch vụ của bạn';
}

function get_expiry_email_body($milestone, $services, $user)
{
    $greeting_text = [
        -30 => 'Chúng tôi xin thông báo rằng các dịch vụ sau của bạn sẽ hết hạn trong 30 ngày tới:',
        -7 => 'Chúng tôi xin nhắc nhở rằng các dịch vụ sau của bạn sẽ hết hạn trong 7 ngày tới:',
        -3 => 'Xin lưu ý: Các dịch vụ sau của bạn sẽ hết hạn trong 3 ngày tới:',
        0 => 'Các dịch vụ sau của bạn đã hết hạn hôm nay:',
        1 => 'Các dịch vụ sau của bạn đã quá hạn 1 ngày:',
        14 => 'Các dịch vụ sau của bạn đã quá hạn 2 tuần:'
    ];

    $greeting = isset($greeting_text[$milestone]) ? $greeting_text[$milestone] : 'Thông báo về dịch vụ của bạn:';

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .service-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .service-table th, .service-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .service-table th { background-color: #007bff; color: white; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .warning { background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
        .danger { background-color: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>INOVA Manager</h1>
            <h2>Thông báo hết hạn dịch vụ</h2>
        </div>
        <div class="content">
            <p>Kính gửi ' . esc_html($user->name) . ',</p>
            <p>' . $greeting . '</p>';

    $service_types = [
        'domains' => 'Tên miền',
        'hostings' => 'Hosting',
        'maintenance_packages' => 'Gói bảo trì'
    ];

    foreach ($services as $type => $items) {
        if (empty($items))
            continue;

        $html .= '<h3>' . $service_types[$type] . '</h3>';
        $html .= '<table class="service-table">
            <thead>
                <tr>
                    <th>Tên</th>
                    <th>Ngày hết hạn</th>
                    <th>Giá gia hạn</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($items as $item) {
            $html .= '<tr>
                <td>' . esc_html($item->name) . '</td>
                <td>' . date('d/m/Y', strtotime($item->expiry_date)) . '</td>
                <td>' . number_format($item->price, 0, ',', '.') . ' VNĐ</td>
            </tr>';
        }

        $html .= '</tbody></table>';
    }

    if ($milestone >= 0) {
        $html .= '<div class="danger">
            <strong>Lưu ý:</strong> Dịch vụ đã hết hạn hoặc sắp hết hạn. Vui lòng liên hệ với chúng tôi để gia hạn ngay.
        </div>';
    } else {
        $html .= '<div class="warning">
            <strong>Khuyến nghị:</strong> Vui lòng gia hạn dịch vụ trước ngày hết hạn để tránh gián đoạn.
        </div>';
    }

    $html .= '
            <p>Để gia hạn dịch vụ, vui lòng liên hệ với chúng tôi qua:</p>
            <ul>
                <li>Email: support@inova.vn</li>
                <li>Hotline: 1900-xxxx</li>
            </ul>
            <p>Trân trọng,<br><strong>INOVA Team</strong></p>
        </div>
        <div class="footer">
            <p>Email này được gửi tự động. Vui lòng không trả lời email này.</p>
            <p>&copy; 2025 INOVA Manager. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';

    return $html;
}


/* ===== REGISTER HOOKS FOR HELPERS ===== */

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'enqueue_js_css');

// Database setup
add_action('after_switch_theme', 'CreateDatabaseBookOrder');

// Register query vars for virtual pages
add_filter('query_vars', function($vars) {
    $vars[] = 'year';
    $vars[] = 'partner';
    $vars[] = 'search';
    $vars[] = 'status';
    $vars[] = 'date_from';
    $vars[] = 'date_to';
    $vars[] = 'paged';
    $vars[] = 'month';
    return $vars;
});

// Template routing
add_filter('template_include', 'auto_assign_page_template');
add_action('wp', 'handle_virtual_page_request');

// Admin UI
add_action('wp_head', 'debug_page_template_info');
add_action('admin_menu', 'add_template_management_menu');
add_action('admin_bar_menu', 'add_template_info_to_admin_bar', 100);
