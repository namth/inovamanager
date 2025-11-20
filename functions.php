<?php
/* Import vendor autoload for Dompdf */
require_once get_template_directory() . '/vendor/autoload.php';

/* Include API functions */
require_once get_template_directory() . '/api.php';

/* enqueue js css function */
function enqueue_js_css()
{
    /* 
     * Enqueue style css file
     */
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
    wp_enqueue_script('bundle.base', get_template_directory_uri() . '/assets/vendors/js/vendor.bundle.base.js', array(), '1.0', true);
    wp_enqueue_script('bootstrap-datepicker', get_template_directory_uri() . '/assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js', array(), '1.0', true);
    wp_enqueue_script('off-canvas', get_template_directory_uri() . '/assets/js/off-canvas.js', array(), '1.0', true);
    wp_enqueue_script('template', get_template_directory_uri() . '/assets/js/template.js', array(), '1.0', true);
    wp_enqueue_script('settings', get_template_directory_uri() . '/assets/js/settings.js', array(), '1.0', true);
    wp_enqueue_script('hoverable-collapse', get_template_directory_uri() . '/assets/js/hoverable-collapse.js', array(), '1.0', true);
    wp_enqueue_script('todolist', get_template_directory_uri() . '/assets/js/todolist.js', array(), '1.0', true);
    wp_enqueue_script('phosphor-icon', 'https://unpkg.com/@phosphor-icons/web', array(), '1.0', true);
    wp_enqueue_script('select2.base', get_template_directory_uri() . '/assets/vendors/select2/select2.min.js', array(), '1.0', true);
    wp_enqueue_script('select2', get_template_directory_uri() . '/assets/js/select2.js', array(), '1.0', true);
    wp_enqueue_script('datepicker', get_template_directory_uri() . '/assets/js/datepicker.js', array(), '1.0', true);
    wp_enqueue_script('custom', get_template_directory_uri() . '/assets/js/custom.js', array('jquery'), '1.0', true);
    wp_localize_script('custom', 'AJAX', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'vat_rates' => get_option('inova_vat_rates', array(
            'Hosting' => 10.00,
            'Domain' => 10.00,
            'Website' => 0.00,
            'Maintenance' => 0.00
        ))
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_js_css');

function CreateDatabaseBookOrder()
{
    global $wpdb;
    $charsetCollate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    # 1. users table - Quản lý người dùng (Khách hàng, Đối tác, Quản trị viên)
    $usersTable = $wpdb->prefix . 'im_users';
    $createTable = "CREATE TABLE `{$usersTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_code` varchar(10) UNIQUE NOT NULL,
        `user_type` varchar(255) NOT NULL,
        `name` varchar(255) NOT NULL,
        `email` varchar(255) NULL,
        `phone_number` varchar(20) NULL,
        `tax_code` varchar(50) NULL,
        `address` text NULL,
        `partner_id` bigint(20) UNSIGNED NULL,
        `notes` text NULL,
        `requires_vat_invoice` BOOLEAN DEFAULT 0,
        `company_name` varchar(255) NULL,
        `company_address` text NULL,
        `invoice_email` varchar(255) NULL,
        `invoice_phone` varchar(20) NULL,
        `status` varchar(255) DEFAULT 'ACTIVE',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `partner_id` (`partner_id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 2. contacts table - Liên hệ cho khách hàng doanh nghiệp
    $contactsTable = $wpdb->prefix . 'im_contacts';
    $createTable = "CREATE TABLE `{$contactsTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) UNSIGNED NOT NULL,
        `full_name` varchar(255) NOT NULL,
        `email` varchar(255) NULL,
        `phone_number` varchar(20) NULL,
        `position` varchar(100) NULL,
        `is_primary` BOOLEAN DEFAULT FALSE,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 3. product_catalog table - Quản lý giá và loại dịch vụ chuẩn
    $catalogTable = $wpdb->prefix . 'im_product_catalog';
    $createTable = "CREATE TABLE `{$catalogTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `service_type` varchar(255) NOT NULL,
        `name` varchar(255) NOT NULL,
        `code` varchar(5) NOT NULL,
        `description` text NULL,
        `register_price` bigint(20) NOT NULL,
        `renew_price` bigint(20) NOT NULL,
        `billing_cycle` varchar(255) NOT NULL,
        `is_active` BOOLEAN DEFAULT TRUE,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 4. domains table
    $domainsTable = $wpdb->prefix . 'im_domains';
    $createTable = "CREATE TABLE `{$domainsTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `domain_name` varchar(255) UNIQUE NOT NULL,
        `owner_user_id` bigint(20) UNSIGNED NOT NULL,
        `create_by` bigint(20) UNSIGNED NULL,
        `provider_id` bigint(20) UNSIGNED NULL,
        `product_catalog_id` bigint(20) UNSIGNED NULL,
        `registration_date` date NULL,
        `registration_period_years` int NULL DEFAULT 1,
        `expiry_date` date NULL,
        `status` enum('ACTIVE','DELETED','EXPIRED','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
        `managed_by_inova` BOOLEAN DEFAULT TRUE,
        `dns_management` varchar(255) NULL,
        `management_url` varchar(255) NULL,
        `management_username` varchar(255) NULL,
        `management_password` varchar(255) NULL,
        `notes` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `owner_user_id` (`owner_user_id`),
        KEY `create_by` (`create_by`),
        KEY `provider_id` (`provider_id`),
        KEY `product_catalog_id` (`product_catalog_id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 5. hostings table
    $hostingsTable = $wpdb->prefix . 'im_hostings';
    $createTable = "CREATE TABLE `{$hostingsTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `owner_user_id` bigint(20) UNSIGNED NOT NULL,
        `create_by` bigint(20) UNSIGNED NULL,
        `hosting_code` varchar(50) UNIQUE NULL,
        `product_catalog_id` bigint(20) UNSIGNED NOT NULL,
        `provider_id` bigint(20) UNSIGNED NULL,
        `registration_date` date NOT NULL,
        `billing_cycle_months` int NOT NULL DEFAULT 12,
        `expiry_date` date NOT NULL,
        `partner_id` bigint(20) UNSIGNED NULL,
        `status` enum('ACTIVE','DELETED','EXPIRED','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
        `ip_address` varchar(50) NULL,
        `management_url` varchar(255) NULL,
        `management_username` varchar(255) NULL,
        `management_password` varchar(255) NULL,
        `notes` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `owner_user_id` (`owner_user_id`),
        KEY `create_by` (`create_by`),
        KEY `product_catalog_id` (`product_catalog_id`),
        KEY `provider_id` (`provider_id`),
        KEY `partner_id` (`partner_id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 6. maintenance_packages table
    $maintenanceTable = $wpdb->prefix . 'im_maintenance_packages';
    $createTable = "CREATE TABLE `{$maintenanceTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `owner_user_id` bigint(20) UNSIGNED NOT NULL,
        `order_code` varchar(50) UNIQUE NULL,
        `billing_cycle_months` int NOT NULL,
        `renew_date` date NOT NULL,
        `total_months_registered` int NOT NULL,
        `expiry_date` date NOT NULL,
        `monthly_fee` bigint(20) NOT NULL,
        `price_per_cycle` bigint(20) NOT NULL,
        `partner_id` bigint(20) UNSIGNED NULL,
        `discount_amount` bigint(20) DEFAULT 0,
        `actual_revenue` bigint(20) NOT NULL,
        `status` enum('ACTIVE','DELETED','EXPIRED','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
        `notes` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `owner_user_id` (`owner_user_id`),
        KEY `partner_id` (`partner_id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 7. websites table
    $websitesTable = $wpdb->prefix . 'im_websites';
    $createTable = "CREATE TABLE `{$websitesTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `owner_user_id` bigint(20) UNSIGNED NOT NULL,
        `created_by` bigint(20) UNSIGNED NULL,
        `domain_id` bigint(20) UNSIGNED NULL,
        `hosting_id` bigint(20) UNSIGNED NULL,
        `maintenance_package_id` bigint(20) UNSIGNED NULL,
        `admin_url` varchar(255) NULL,
        `admin_username` varchar(255) NULL,
        `admin_password` varchar(255) NULL,
        `ip_address` varchar(255) NULL,
        `active_time` timestamp NULL DEFAULT NULL,
        `status` enum('ACTIVE','DELETED') DEFAULT 'ACTIVE',
        `notes` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `owner_user_id` (`owner_user_id`),
        KEY `created_by` (`created_by`),
        KEY `domain_id` (`domain_id`),
        KEY `hosting_id` (`hosting_id`),
        KEY `maintenance_package_id` (`maintenance_package_id`),
        KEY `active_time` (`active_time`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 8. invoices table
    $invoicesTable = $wpdb->prefix . 'im_invoices';
    $createTable = "CREATE TABLE `{$invoicesTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `invoice_code` varchar(50) UNIQUE NOT NULL,
        `user_id` bigint(20) UNSIGNED NOT NULL,
        `partner_id` bigint(20) UNSIGNED NULL,
        `invoice_date` date NOT NULL,
        `due_date` date NOT NULL,
        `sub_total` bigint(20) NOT NULL,
        `discount_total` bigint(20) DEFAULT 0,
        `tax_amount` bigint(20) DEFAULT 0,
        `total_amount` bigint(20) NOT NULL,
        `paid_amount` bigint(20) DEFAULT 0,
        `payment_date` datetime NULL,
        `payment_method` varchar(100) NULL,
        `status` varchar(255) NOT NULL DEFAULT 'DRAFT',
        `requires_vat_invoice` BOOLEAN DEFAULT 0,
        `notes` text NULL,
        `created_by_type` varchar(255) NOT NULL,
        `created_by_id` bigint(20) UNSIGNED NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `partner_id` (`partner_id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 9. invoice_items table
    $invoiceItemsTable = $wpdb->prefix . 'im_invoice_items';
    $createTable = "CREATE TABLE `{$invoiceItemsTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `invoice_id` bigint(20) UNSIGNED NOT NULL,
        `service_type` varchar(255) NOT NULL,
        `service_id` bigint(20) UNSIGNED NOT NULL,
        `description` varchar(255) NOT NULL,
        `renewal_period_description` varchar(100) NULL,
        `start_date` date NULL,
        `end_date` date NULL,
        `quantity` int NOT NULL DEFAULT 1,
        `unit_price` bigint(20) NOT NULL,
        `item_total` bigint(20) NOT NULL,
        `vat_rate` decimal(5, 2) DEFAULT 0.00 COMMENT 'VAT rate at time of invoice creation',
        `vat_amount` bigint(20) DEFAULT 0 COMMENT 'VAT amount = item_total * vat_rate / 100',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `invoice_id` (`invoice_id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 10. partner_commissions table
    $commissionsTable = $wpdb->prefix . 'im_partner_commissions';
    $createTable = "CREATE TABLE `{$commissionsTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `partner_id` bigint(20) UNSIGNED NOT NULL,
        `invoice_id` bigint(20) UNSIGNED NOT NULL,
        `invoice_item_id` bigint(20) UNSIGNED NULL,
        `commission_amount` bigint(20) NOT NULL,
        `commission_rate` decimal(5, 2) NULL,
        `calculation_date` date NOT NULL,
        `status` varchar(255) DEFAULT 'PENDING',
        `payout_date` date NULL,
        `notes` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `partner_id` (`partner_id`),
        KEY `invoice_id` (`invoice_id`),
        KEY `invoice_item_id` (`invoice_item_id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 11. Initialize default VAT rates in wp_options
    // Set default VAT rates if not exists
    if (!get_option('inova_vat_rates')) {
        $default_vat_rates = array(
            'Hosting' => 10.00,
            'Domain' => 10.00,
            'Website' => 0.00,
            'Maintenance' => 0.00
        );
        add_option('inova_vat_rates', $default_vat_rates);
    }

    # 12. website_services table - Quản lý yêu cầu chỉnh sửa có tính phí
    $websiteServicesTable = $wpdb->prefix . 'im_website_services';
    $createTable = "CREATE TABLE `{$websiteServicesTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `website_id` bigint(20) UNSIGNED NOT NULL,
        `service_code` varchar(20) UNIQUE NOT NULL,
        `title` varchar(255) NOT NULL,
        `description` text NULL,
        `estimated_manday` decimal(5,2) NULL,
        `daily_rate` bigint(20) NULL,
        `fixed_price` bigint(20) NULL,
        `pricing_type` enum('DAILY','FIXED') NULL,
        `priority` enum('LOW','MEDIUM','HIGH','URGENT') DEFAULT 'MEDIUM',
        `status` enum('PENDING','APPROVED','IN_PROGRESS','COMPLETED','CANCELLED','ACTIVE','DELETED') DEFAULT 'PENDING',
        `requested_by` bigint(20) UNSIGNED NOT NULL,
        `assigned_to` bigint(20) UNSIGNED NULL,
        `start_date` date NULL,
        `estimated_completion_date` date NULL,
        `completion_date` date NULL,
        `notes` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `website_id` (`website_id`),
        KEY `requested_by` (`requested_by`),
        KEY `assigned_to` (`assigned_to`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 11. cart table - Giỏ hàng cho các dịch vụ cần thanh toán
    $cartTable = $wpdb->prefix . 'im_cart';
    $createTable = "CREATE TABLE `{$cartTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) UNSIGNED NOT NULL,
        `service_type` enum('domain','hosting','maintenance') NOT NULL,
        `service_id` bigint(20) UNSIGNED NOT NULL,
        `quantity` int(11) DEFAULT 1,
        `notes` text NULL,
        `added_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `service_type` (`service_type`),
        KEY `service_id` (`service_id`),
        UNIQUE KEY `unique_cart_item` (`user_id`, `service_type`, `service_id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # Initialize default email notification settings in WordPress options
    // Set global default for email notifications (can be overridden per user via user meta)
    if (!get_option('inova_email_notification_defaults')) {
        $default_settings = array(
            'domain' => 1,
            'hosting' => 1,
            'maintenance' => 1
        );
        add_option('inova_email_notification_defaults', $default_settings);
    }
}
add_action('after_switch_theme', 'CreateDatabaseBookOrder');

# Function to get last id of table
function getLastId($table_name, $field_name)
{
    global $wpdb;
    $sql = "SELECT MAX($field_name) FROM $table_name";
    $result = $wpdb->get_var($sql);
    return $result;
}

/**
 * Get VAT rate for a service type
 *
 * @param string $service_type Service type (Hosting, Domain, Website, Maintenance)
 * @return float VAT rate in percentage (e.g., 10.00 for 10%)
 */
function get_vat_rate_for_service($service_type) {
    $vat_rates = get_option('inova_vat_rates', array());

    if (isset($vat_rates[$service_type])) {
        return floatval($vat_rates[$service_type]);
    }

    return 0.00;
}

/**
 * Get all current VAT rates
 *
 * @return array Associative array of service_type => vat_rate
 */
function get_current_vat_rates() {
    $vat_rates = get_option('inova_vat_rates', array());

    // Convert to float
    foreach ($vat_rates as $key => $value) {
        $vat_rates[$key] = floatval($value);
    }

    return $vat_rates;
}

/**
 * Update VAT rate for a service type
 *
 * @param string $service_type Service type
 * @param float $vat_rate VAT rate in percentage
 * @return bool Success status
 */
function update_vat_rate($service_type, $vat_rate) {
    $vat_rates = get_option('inova_vat_rates', array());
    $vat_rates[$service_type] = floatval($vat_rate);

    return update_option('inova_vat_rates', $vat_rates);
}

/**
 * Calculate VAT amount for an item
 *
 * @param float $item_total Item total amount
 * @param float $vat_rate VAT rate in percentage
 * @return int VAT amount (rounded)
 */
function calculate_vat_amount($item_total, $vat_rate) {
    return round($item_total * $vat_rate / 100);
}

/* get all relationship by partnerID from bopartner_relationship table
    * @param $partnerID: partnerID
    * @return array of partnerID
*/
function get_relationship($partnerID, $type="full")
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



/**
 * AJAX handler to add item to cart
 */
add_action('wp_ajax_add_to_cart', 'add_to_cart_ajax');
add_action('wp_ajax_nopriv_add_to_cart', 'add_to_cart_ajax');
function add_to_cart_ajax() {
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
        $user_id, $service_type, $service_id
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

/**
 * AJAX handler to remove item from cart
 */
add_action('wp_ajax_remove_from_cart', 'remove_from_cart_ajax');
add_action('wp_ajax_nopriv_remove_from_cart', 'remove_from_cart_ajax');
function remove_from_cart_ajax() {
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

/**
 * AJAX handler to get cart count
 */
add_action('wp_ajax_get_cart_count', 'get_cart_count_ajax');
add_action('wp_ajax_nopriv_get_cart_count', 'get_cart_count_ajax');
function get_cart_count_ajax() {
    global $wpdb;
    $cart_table = $wpdb->prefix . 'im_cart';
    $cart_count = $wpdb->get_var("SELECT COUNT(*) FROM $cart_table");

    echo json_encode(array(
        'status' => true,
        'cart_count' => $cart_count
    ));
    exit;
}

/**
 * AJAX handler to get customer services (domains, hostings, maintenances, websites)
 * Used in add_invoice.php to load services when customer is selected
 */
add_action('wp_ajax_get_customer_services', 'get_customer_services_ajax');
add_action('wp_ajax_nopriv_get_customer_services', 'get_customer_services_ajax');
function get_customer_services_ajax() {
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

/**
 * Function to automatically select page template based on slug
 * Tự động lựa chọn template page dựa theo slug (Virtual Pages)
 */
function auto_assign_page_template($template) {
    global $wp_query;
    
    // Get current request URI
    $request_uri = trim($_SERVER['REQUEST_URI'], '/');
    $request_uri_parts = explode('?', $request_uri);
    $request_uri = $request_uri_parts[0]; // Remove query params for matching
    
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
            
            // Set global $post
            global $post;
            $post = $virtual_post;
            setup_postdata($post);
            
            return $template_file;
        }
    }
    
    return $template;
}
add_filter('template_include', 'auto_assign_page_template');

/**
 * Handle virtual page requests
 * Xử lý các request đến virtual pages
 */
function handle_virtual_page_request() {
    global $wp_query;
    
    // Get current request URI
    $request_uri = trim($_SERVER['REQUEST_URI'], '/');
    $request_uri_parts = explode('?', $request_uri);
    $request_uri = $request_uri_parts[0]; // Remove query params for matching
    
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
add_action('wp', 'handle_virtual_page_request');

/**
 * Get the centralized template mapping array
 * Lấy mảng mapping template tập trung
 */
function get_virtual_page_template_mapping() {
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
        

        
        // Invoice Management
        'invoice-list' => 'list_invoice.php',
        'list-invoice' => 'list_invoice.php',
        'invoices' => 'list_invoice.php',
        'add-invoice' => 'add_invoice.php',
        'detail-invoice' => 'detail_invoice.php',
        'invoice-success' => 'invoice_success.php',

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
        
        // Alternative and shorter slugs
        'website' => 'website_list.php',
        'domain' => 'domain_list.php',
        'hosting' => 'hosting_list.php',
        'maintenance' => 'maintenance_list.php',
        'service' => 'service_list.php',
        'product' => 'product_catalog_list.php',
        'partner' => 'list_partner.php',
        'invoice' => 'list_invoice.php',
        'contact' => 'addnew_contact.php'
    );
}

/**
 * Get all available templates in theme
 * Lấy danh sách tất cả template có trong theme
 */
function get_available_templates() {
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

/**
 * Debug function to show current page slug and suggested template
 * Function debug để hiển thị slug hiện tại và template được đề xuất
 */
function debug_page_template_info() {
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
add_action('wp_head', 'debug_page_template_info');

/**
 * Add admin menu for template management
 * Thêm menu admin để quản lý template
 */
function add_template_management_menu() {
    add_theme_page(
        'Template Management',
        'Template Management', 
        'manage_options',
        'template-management',
        'template_management_page'
    );
}
add_action('admin_menu', 'add_template_management_menu');

/**
 * Add template info to admin bar
 * Thêm thông tin template vào admin bar
 */
function add_template_info_to_admin_bar($wp_admin_bar) {
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
add_action('admin_bar_menu', 'add_template_info_to_admin_bar', 100);

/**
 * Template management admin page
 * Trang admin quản lý template
 */
function template_management_page() {
    // Get template mapping array
    $template_mapping = get_virtual_page_template_mapping();
    
    // Handle form submissions for adding/removing mappings
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_mapping' && isset($_POST['slug']) && isset($_POST['template'])) {
            $slug = sanitize_text_field($_POST['slug']);
            $template = sanitize_text_field($_POST['template']);
            
            // Update template mapping in database or option
            $current_mappings = get_option('inova_template_mappings', array());
            $current_mappings[$slug] = $template;
            update_option('inova_template_mappings', $current_mappings);
            
            echo '<div class="notice notice-success"><p>Mapping added successfully! URL /' . esc_html($slug) . '/ will now load template: ' . esc_html($template) . '</p></div>';
        } elseif ($_POST['action'] === 'remove_mapping' && isset($_POST['slug'])) {
            $slug = sanitize_text_field($_POST['slug']);
            
            $current_mappings = get_option('inova_template_mappings', array());
            unset($current_mappings[$slug]);
            update_option('inova_template_mappings', $current_mappings);
            
            echo '<div class="notice notice-success"><p>Mapping for /' . esc_html($slug) . '/ removed successfully!</p></div>';
        }
    }
    
    // Get current mappings (combine default with saved ones)
    $saved_mappings = get_option('inova_template_mappings', array());
    $all_mappings = array_merge($template_mapping, $saved_mappings);
    
    ?>
    <div class="wrap">
        <h1>Virtual Page Template Management</h1>
        <p>Manage URL to template mappings for virtual pages. No WordPress pages need to be created - URLs are handled directly.</p>
        
        <h2>Add New Virtual Page Mapping</h2>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="slug">URL Slug</label>
                    </th>
                    <td>
                        <input type="text" id="slug" name="slug" class="regular-text" placeholder="dashboard" required />
                        <p class="description">Enter the URL slug (without slashes). Will create: <?php echo home_url('/'); ?><strong>[slug]</strong>/</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="template">Template File</label>
                    </th>
                    <td>
                        <select id="template" name="template" required>
                            <option value="">Select template...</option>
                            <?php
                            $theme_path = get_template_directory();
                            $template_files = glob($theme_path . '/page-*.php');
                            foreach ($template_files as $file) {
                                $filename = basename($file);
                                echo '<option value="' . esc_attr($filename) . '">' . esc_html($filename) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Select the template file to load for this URL</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="action" value="add_mapping" />
            <?php wp_nonce_field('template_management'); ?>
            <p class="submit">
                <input type="submit" class="button-primary" value="Add Virtual Page Mapping" />
            </p>
        </form>
        
        <h2>Current Virtual Page Mappings</h2>
        <?php if (!empty($all_mappings)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Template File</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_mappings as $slug => $template): ?>
                        <tr>
                            <td>
                                <strong><?php echo home_url('/' . esc_html($slug) . '/'); ?></strong>
                                <div class="row-actions">
                                    <span class="view"><a href="<?php echo home_url('/' . esc_html($slug) . '/'); ?>" target="_blank">View</a></span>
                                </div>
                            </td>
                            <td><code><?php echo esc_html($template); ?></code></td>
                            <td>
                                <?php if (isset($template_mapping[$slug])): ?>
                                    <span class="dashicons dashicons-admin-settings" title="Default mapping"></span> Default
                                <?php else: ?>
                                    <span class="dashicons dashicons-admin-customizer" title="Custom mapping"></span> Custom
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $template_path = get_template_directory() . '/' . $template;
                                if (file_exists($template_path)): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;" title="Template file exists"></span> Active
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss" style="color: red;" title="Template file not found"></span> Missing Template
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!isset($template_mapping[$slug])): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="remove_mapping" />
                                        <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>" />
                                        <?php wp_nonce_field('template_management'); ?>
                                        <input type="submit" class="button button-small" value="Remove" onclick="return confirm('Are you sure you want to remove this mapping?')" />
                                    </form>
                                <?php else: ?>
                                    <em>Default - cannot remove</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No virtual page mappings configured yet.</p>
        <?php endif; ?>
        
        <h2>System Information</h2>
        <div class="card">
            <h3>How Virtual Pages Work</h3>
            <ul>
                <li><strong>No WordPress Pages Required:</strong> URLs are handled directly without creating database entries</li>
                <li><strong>Direct Template Loading:</strong> Each URL slug maps directly to a template file</li>
                <li><strong>SEO Friendly:</strong> URLs are clean and can be indexed normally</li>
                <li><strong>Performance:</strong> No database queries needed for page lookup</li>
            </ul>
            
            <h3>Available Template Files</h3>
            <ul>
                <?php
                $theme_path = get_template_directory();
                $template_files = glob($theme_path . '/page-*.php');
                if (!empty($template_files)):
                    foreach ($template_files as $file):
                        $filename = basename($file);
                        ?>
                        <li><code><?php echo esc_html($filename); ?></code></li>
                        <?php
                    endforeach;
                else:
                    ?>
                    <li><em>No page-*.php templates found in theme</em></li>
                    <?php
                endif;
                ?>
            </ul>
        </div>
        
        <h2>Debug Information</h2>
        <div class="card">
            <p><strong>Current Request URI:</strong> <code><?php echo esc_html($_SERVER['REQUEST_URI'] ?? 'N/A'); ?></code></p>
            <p><strong>Theme Directory:</strong> <code><?php echo esc_html(get_template_directory()); ?></code></p>
            <p><strong>Home URL:</strong> <code><?php echo esc_html(home_url('/')); ?></code></p>
            <p><strong>Virtual Page System:</strong> 
                <?php if (function_exists('handle_virtual_page_request')): ?>
                    <span style="color: green;">✓ Active</span>
                <?php else: ?>
                    <span style="color: red;">✗ Not Active</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php
}

/**
 * AJAX handler to fetch domain information from APILayer
 */
add_action('wp_ajax_fetch_domain_info', 'fetch_domain_info_from_apilayer');
add_action('wp_ajax_nopriv_fetch_domain_info', 'fetch_domain_info_from_apilayer');

function fetch_domain_info_from_apilayer() {
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

/**
 * ===================================================================
 * Cloudflare Integration Functions
 * ===================================================================
 */

/**
 * Helper function to make requests to Cloudflare API
 */
function make_cloudflare_request($endpoint) {
    $api_token = get_option('cloudflare_api_token');
    $account_id = get_option('cloudflare_account_id');

    if (empty($api_token) || empty($account_id)) {
        return new WP_Error('missing_credentials', 'Cloudflare API Token hoặc Account ID chưa được cấu hình.');
    }

    $url = "https://api.cloudflare.com/client/v4/{$endpoint}";

    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type'  => 'application/json',
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
            $all_errors = array_map(function($err) {
                return isset($err['message']) ? $err['message'] : 'Unknown error';
            }, $data['errors']);
            $error_message = implode('; ', $all_errors);
        }

        return new WP_Error('cloudflare_error', $error_message);
    }

    return $data['result'];
}

/**
 * AJAX handler to fetch zones from Cloudflare
 */
add_action('wp_ajax_fetch_cloudflare_zones', function() {
    check_ajax_referer('cloudflare_ajax_nonce', 'nonce');

    $account_id = get_option('cloudflare_account_id');
    $zones_data = make_cloudflare_request("zones?account.id={$account_id}&per_page=100");

    if (is_wp_error($zones_data)) {
        wp_send_json_error(array('message' => $zones_data->get_error_message()));
        return;
    }

    global $wpdb;
    $domains_table = $wpdb->prefix . 'im_domains';
    $response_data = [];

    foreach ($zones_data as $zone) {
        // Check if domain exists in local DB
        $existing_domain = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $domains_table WHERE domain_name = %s",
            $zone['name']
        ));

        // Get origin IP from A record
        $origin_ip = null;
        $dns_records = make_cloudflare_request("zones/{$zone['id']}/dns_records?type=A&name={$zone['name']}");
        if (!is_wp_error($dns_records) && !empty($dns_records)) {
            $origin_ip = $dns_records[0]['content'];
        }

        $response_data[] = array(
            'id' => $zone['id'],
            'name' => $zone['name'],
            'status' => $zone['status'],
            'exists_in_db' => $existing_domain > 0,
            'origin_ip' => $origin_ip,
        );
    }

    wp_send_json_success($response_data);
});

/**
 * AJAX handler to import a single zone from Cloudflare
 */
add_action('wp_ajax_import_cloudflare_zone', function() {
    check_ajax_referer('cloudflare_ajax_nonce', 'nonce');

    $zone_name = sanitize_text_field($_POST['zone_name']);

    if (empty($zone_name)) {
        wp_send_json_error(array('message' => 'Tên miền không hợp lệ.'));
        return;
    }

    global $wpdb;
    $domains_table = $wpdb->prefix . 'im_domains';
    $websites_table = $wpdb->prefix . 'im_websites';

    // Log import attempt
    error_log("=== Starting Cloudflare Import for: {$zone_name} ===");
    error_log("Domains table: {$domains_table}");
    error_log("Websites table: {$websites_table}");

    // Check if default owner exists
    $users_table = $wpdb->prefix . 'im_users';
    $default_owner_id = 12;
    $owner_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $users_table WHERE id = %d",
        $default_owner_id
    ));

    if (!$owner_exists) {
        error_log("ERROR: Default owner user ID {$default_owner_id} does not exist!");
        wp_send_json_error(array(
            'message' => "Lỗi: User với ID {$default_owner_id} không tồn tại trong hệ thống. Vui lòng tạo user trước hoặc thay đổi default owner ID trong code."
        ));
        return;
    }

    // Start transaction
    $wpdb->query('START TRANSACTION');

    try {
        // 1. Check if domain exists
        $domain_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $domains_table WHERE domain_name = %s",
            $zone_name
        ));

        if ($domain_id) {
            error_log("Domain already exists with ID: {$domain_id}");
        } else {
            error_log("Domain does not exist, creating new one...");
        }

        // 2. If not, create new domain
        if (!$domain_id) {
            $domain_data = array(
                'domain_name'       => $zone_name,
                'owner_user_id'     => 12, // Default owner ID
                'registration_date' => '2025-01-01',
                'expiry_date'       => '2026-01-01',
                'status'            => 'ACTIVE',
                'dns_management'    => 'CLOUDFLARE',
            );

            $insert_result = $wpdb->insert($domains_table, $domain_data);

            if ($insert_result === false) {
                $error_msg = 'Không thể tạo bản ghi domain mới.';
                if ($wpdb->last_error) {
                    $error_msg .= ' SQL Error: ' . $wpdb->last_error;
                }
                throw new Exception($error_msg);
            }

            $domain_id = $wpdb->insert_id;

            if (!$domain_id) {
                throw new Exception('Domain được tạo nhưng không có ID. Kiểm tra AUTO_INCREMENT.');
            }

            error_log("Domain created successfully with ID: {$domain_id}");
        }

        // 3. Check if website exists
        error_log("Checking if website already exists...");
        $website_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $websites_table WHERE name = %s OR domain_id = %d",
            $zone_name, $domain_id
        ));

        if ($website_exists > 0) {
            error_log("Website already exists, cannot import.");
            throw new Exception('Website với tên hoặc domain này đã tồn tại trong hệ thống.');
        }

        error_log("Website does not exist, creating new one...");

        // 4. Create new website
        $website_data = array(
            'name'          => $zone_name,
            'owner_user_id' => 12, // Default owner ID
            'domain_id'     => $domain_id,
            'status'        => 'ACTIVE',
            'ip_address'    => null, // Leave IP address empty as requested
        );

        $insert_result = $wpdb->insert($websites_table, $website_data);

        if ($insert_result === false) {
            $error_msg = 'Không thể tạo bản ghi website mới.';
            if ($wpdb->last_error) {
                $error_msg .= ' SQL Error: ' . $wpdb->last_error;
            }
            error_log('Website Insert Error: ' . $error_msg);
            error_log('Website Data: ' . print_r($website_data, true));
            throw new Exception($error_msg);
        }

        $website_id = $wpdb->insert_id;

        if (!$website_id) {
            throw new Exception('Website được tạo nhưng không có ID. Kiểm tra AUTO_INCREMENT.');
        }

        error_log("Website created successfully with ID: {$website_id}");
        error_log("=== Import completed successfully ===");

        // Commit transaction
        $wpdb->query('COMMIT');

        wp_send_json_success(array(
            'message' => "Đã import thành công website '{$zone_name}'.",
            'website_id' => $website_id,
            'domain_id' => $domain_id,
        ));

    } catch (Exception $e) {
        // Rollback on error
        $wpdb->query('ROLLBACK');
        wp_send_json_error(array('message' => $e->getMessage()));
    }
});

/**
 * AJAX handler to test Cloudflare connection
 */
add_action('wp_ajax_test_cloudflare_connection', function() {
    check_ajax_referer('cloudflare_test_nonce', 'nonce');

    $api_token = sanitize_text_field($_POST['api_token']);
    $account_id = sanitize_text_field($_POST['account_id']);

    if (empty($api_token) || empty($account_id)) {
        wp_send_json_error(array('message' => 'API Token và Account ID không được để trống.'));
        return;
    }

    // Temporarily save to options for testing
    $old_token = get_option('cloudflare_api_token');
    $old_account = get_option('cloudflare_account_id');

    update_option('cloudflare_api_token', $api_token);
    update_option('cloudflare_account_id', $account_id);

    // Test the connection by trying to fetch user details or zones
    $test_result = make_cloudflare_request("user/tokens/verify");

    // Restore old values
    update_option('cloudflare_api_token', $old_token);
    update_option('cloudflare_account_id', $old_account);

    if (is_wp_error($test_result)) {
        wp_send_json_error(array('message' => $test_result->get_error_message()));
        return;
    }

    // If we got here, connection is successful
    $message = 'Kết nối thành công! Token hợp lệ.';
    if (isset($test_result['status']) && $test_result['status'] === 'active') {
        $message .= ' Token status: Active';
    }

    wp_send_json_success(array('message' => $message, 'data' => $test_result));
});

/**
 * AJAX handler to renew domain by 1 year
 */
add_action('wp_ajax_renew_domain_one_year', function() {
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
});

/**
 * Generate VietQR payment QR code URL
 *
 * @param float $amount Payment amount
 * @param string $add_info Payment description/reference
 * @return string|false QR code image URL or false if settings not configured
 */
function generate_payment_qr_code($amount, $add_info = '', $requires_vat_invoice = 0) {
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

/**
 * ============================================================================
 * USER PERMISSION & ROLE MANAGEMENT FUNCTIONS
 * ============================================================================
 */

/**
 * Get inova_id from WordPress user meta
 *
 * @param int|null $user_id WordPress user ID (null = current user)
 * @return int|false Inova user ID or false if not set
 */
function get_user_inova_id($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $inova_id = get_user_meta($user_id, 'inova_id', true);
    return $inova_id ? intval($inova_id) : false;
}

/**
 * Get inova user record from im_users table
 *
 * @param int|null $user_id WordPress user ID (null = current user)
 * @return object|null User object from im_users or null
 */
function get_inova_user($user_id = null) {
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

/**
 * Get inova user record directly by ID
 *
 * @param int|null $inova_user_id Inova user ID
 * @return object|null User object or null
 */
function get_inova_user_by_id($inova_user_id = null) {
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

/**
 * Check if current user is administrator
 *
 * @return bool True if admin, false otherwise
 */
function is_inova_admin() {
    return current_user_can('administrator');
}

/**
 * Get user type (Customer, Partner, etc.)
 *
 * @return string|null User type or null if not found
 */
function get_inova_user_type() {
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return null;
    }

    $inova_user = get_inova_user($current_user_id);
    return $inova_user ? $inova_user->user_type : null;
}

/**
 * Check if current user can view a specific item by owner_user_id
 *
 * @param int $owner_user_id The owner_user_id of the item
 * @param int|null $partner_id Optional partner_id for Partner access
 * @return bool True if user can view, false otherwise
 */
function can_user_view_item($owner_user_id, $partner_id = null) {
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

/**
 * Generate WHERE clause for filtering data by user permissions
 *
 * @param string $table_alias Table alias (e.g., 'w' for websites, 'd' for domains)
 * @param string $owner_field Field name for owner_user_id (default: 'owner_user_id')
 * @param string|null $partner_field Field name for partner_id (optional)
 * @return string SQL WHERE clause
 */
function get_user_permission_where_clause($table_alias = '', $owner_field = 'owner_user_id', $partner_field = null) {
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

/**
 * Generate WHERE clause for websites, allowing partners managing hosting/maintenance access
 *
 * @param string $table_alias Table alias for websites table
 * @return string SQL WHERE clause
 */
function get_website_permission_where_clause($table_alias = 'w') {
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

// ============================================================================
// AUTO RENEWAL INVOICE CRON JOB
// ============================================================================

/**
 * Schedule auto renewal invoice creation cron job
 */
function schedule_auto_renewal_invoices() {
    if (!wp_next_scheduled('auto_create_renewal_invoices')) {
        wp_schedule_event(time(), 'daily', 'auto_create_renewal_invoices');
    }
}
add_action('wp', 'schedule_auto_renewal_invoices');

/**
 * Auto create renewal invoices for expiring services
 * Creates invoices 30 days before expiry for:
 * - Domains managed by INOVA (managed_by_inova = 1)
 * - All hostings
 * - All maintenance packages
 * Groups services expiring within 51 days into single invoice per user
 */
function auto_create_renewal_invoices_callback() {
    global $wpdb;

    $domains_table = $wpdb->prefix . 'im_domains';
    $hostings_table = $wpdb->prefix . 'im_hostings';
    $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
    $invoices_table = $wpdb->prefix . 'im_invoices';
    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
    $users_table = $wpdb->prefix . 'im_users';
    $product_catalog_table = $wpdb->prefix . 'im_product_catalog';

    // Calculate date ranges
    $today = date('Y-m-d');
    $date_30_days = date('Y-m-d', strtotime('+30 days'));
    $date_51_days = date('Y-m-d', strtotime('+51 days'));

    // Find all services expiring between 30-51 days from now
    // 1. Get INOVA-managed domains
    $expiring_domains = $wpdb->get_results($wpdb->prepare("
        SELECT d.*, u.user_code, u.name as owner_name, pc.renew_price
        FROM {$domains_table} d
        LEFT JOIN {$users_table} u ON d.owner_user_id = u.id
        LEFT JOIN {$product_catalog_table} pc ON d.product_catalog_id = pc.id
        WHERE d.managed_by_inova = 1
        AND d.status = 'ACTIVE'
        AND d.expiry_date BETWEEN %s AND %s
    ", $date_30_days, $date_51_days));

    // 2. Get all hostings
    $expiring_hostings = $wpdb->get_results($wpdb->prepare("
        SELECT h.*, u.user_code, u.name as owner_name, pc.renew_price
        FROM {$hostings_table} h
        LEFT JOIN {$users_table} u ON h.owner_user_id = u.id
        LEFT JOIN {$product_catalog_table} pc ON h.product_catalog_id = pc.id
        WHERE h.status = 'ACTIVE'
        AND h.expiry_date BETWEEN %s AND %s
    ", $date_30_days, $date_51_days));

    // 3. Get all maintenance packages
    $expiring_maintenance = $wpdb->get_results($wpdb->prepare("
        SELECT m.*, u.user_code, u.name as owner_name
        FROM {$maintenance_table} m
        LEFT JOIN {$users_table} u ON m.owner_user_id = u.id
        WHERE m.status = 'ACTIVE'
        AND m.expiry_date BETWEEN %s AND %s
    ", $date_30_days, $date_51_days));

    // Group services by owner_user_id
    $services_by_user = array();

    foreach ($expiring_domains as $domain) {
        if (!isset($services_by_user[$domain->owner_user_id])) {
            $services_by_user[$domain->owner_user_id] = array(
                'user_code' => $domain->user_code,
                'owner_name' => $domain->owner_name,
                'domains' => array(),
                'hostings' => array(),
                'maintenances' => array()
            );
        }
        $services_by_user[$domain->owner_user_id]['domains'][] = $domain;
    }

    foreach ($expiring_hostings as $hosting) {
        if (!isset($services_by_user[$hosting->owner_user_id])) {
            $services_by_user[$hosting->owner_user_id] = array(
                'user_code' => $hosting->user_code,
                'owner_name' => $hosting->owner_name,
                'domains' => array(),
                'hostings' => array(),
                'maintenances' => array()
            );
        }
        $services_by_user[$hosting->owner_user_id]['hostings'][] = $hosting;
    }

    foreach ($expiring_maintenance as $maintenance) {
        if (!isset($services_by_user[$maintenance->owner_user_id])) {
            $services_by_user[$maintenance->owner_user_id] = array(
                'user_code' => $maintenance->user_code,
                'owner_name' => $maintenance->owner_name,
                'domains' => array(),
                'hostings' => array(),
                'maintenances' => array()
            );
        }
        $services_by_user[$maintenance->owner_user_id]['maintenances'][] = $maintenance;
    }

    // Create invoices for each user
    foreach ($services_by_user as $user_id => $user_services) {
        // Check if invoice already exists for any of these services
        $service_ids = array();
        foreach ($user_services['domains'] as $d) $service_ids[] = 'D' . $d->id;
        foreach ($user_services['hostings'] as $h) $service_ids[] = 'H' . $h->id;
        foreach ($user_services['maintenances'] as $m) $service_ids[] = 'M' . $m->id;

        // Skip if invoice already created
        $existing_check = implode(',', array_map(function($id) { return "'" . esc_sql($id) . "'"; }, $service_ids));
        $existing_invoice = $wpdb->get_var("
            SELECT COUNT(*) FROM {$invoice_items_table}
            WHERE CONCAT(LEFT(service_type, 1), service_id) IN ({$existing_check})
            AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");

        if ($existing_invoice > 0) {
            continue; // Already has invoice
        }

        // Generate invoice code
        $invoice_code = generate_invoice_code($user_id);

        // Create invoice
        $invoice_data = array(
            'invoice_code' => $invoice_code,
            'user_id' => $user_id,
            'invoice_date' => $today,
            'due_date' => date('Y-m-d', strtotime('+15 days')),
            'status' => 'pending',
            'total_amount' => 0,
            'paid_amount' => 0,
            'notes' => 'Hóa đơn gia hạn dịch vụ tự động'
        );

        $wpdb->insert($invoices_table, $invoice_data);
        $invoice_id = $wpdb->insert_id;

        $total_amount = 0;

        // Add domain items
        foreach ($user_services['domains'] as $domain) {
            $item_data = array(
                'invoice_id' => $invoice_id,
                'service_type' => 'Domain',
                'service_id' => $domain->id,
                'description' => 'Gia hạn tên miền: ' . $domain->domain_name,
                'unit_price' => $domain->renew_price,
                'quantity' => 1,
                'start_date' => $domain->expiry_date,
                'end_date' => date('Y-m-d', strtotime($domain->expiry_date . ' +1 year'))
            );
            $wpdb->insert($invoice_items_table, $item_data);
            $total_amount += $domain->renew_price;
        }

        // Add hosting items
        foreach ($user_services['hostings'] as $hosting) {
            $hosting_code = !empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id;
            $item_data = array(
                'invoice_id' => $invoice_id,
                'service_type' => 'Hosting',
                'service_id' => $hosting->id,
                'description' => 'Gia hạn hosting: ' . $hosting_code,
                'unit_price' => $hosting->renew_price,
                'quantity' => 1,
                'start_date' => $hosting->expiry_date,
                'end_date' => date('Y-m-d', strtotime($hosting->expiry_date . ' +1 year'))
            );
            $wpdb->insert($invoice_items_table, $item_data);
            $total_amount += $hosting->renew_price;
        }

        // Add maintenance items
        foreach ($user_services['maintenances'] as $maintenance) {
            $maintenance_code = !empty($maintenance->order_code) ? $maintenance->order_code : 'MAINT-' . $maintenance->id;
            $months = $maintenance->billing_cycle_months;
            $item_data = array(
                'invoice_id' => $invoice_id,
                'service_type' => 'Maintenance',
                'service_id' => $maintenance->id,
                'description' => 'Gia hạn bảo trì: ' . $maintenance_code,
                'unit_price' => $maintenance->actual_revenue,
                'quantity' => 1,
                'start_date' => $maintenance->expiry_date,
                'end_date' => date('Y-m-d', strtotime($maintenance->expiry_date . ' +' . $months . ' months'))
            );
            $wpdb->insert($invoice_items_table, $item_data);
            $total_amount += $maintenance->actual_revenue;
        }

        // Update invoice total
        $wpdb->update(
            $invoices_table,
            array('total_amount' => $total_amount),
            array('id' => $invoice_id),
            array('%f'),
            array('%d')
        );
    }
}
add_action('auto_create_renewal_invoices', 'auto_create_renewal_invoices_callback');

/**
 * Generate a unique invoice code based on customer and sequence.
 * Format: [Customer Code][YYMMDD][Sequential Number]
 * Example: KH001240525001
 *
 * @param int $user_id The ID of the customer.
 * @return string The generated invoice code.
 */
function generate_invoice_code($user_id) {
    global $wpdb;
    $users_table = $wpdb->prefix . 'im_users';
    $invoices_table = $wpdb->prefix . 'im_invoices';

    // 1. Get customer code
    $user_code = $wpdb->get_var($wpdb->prepare(
        "SELECT user_code FROM $users_table WHERE id = %d",
        $user_id
    ));

    if (empty($user_code)) {
        // Fallback if user code is not found
        return 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
    }

    // 2. Get current date part
    $date_part = date('ymd');

    // 3. Get the sequential number for this customer
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $invoices_table WHERE user_id = %d",
        $user_id
    ));
    $sequence_part = str_pad($count + 1, 3, '0', STR_PAD_LEFT); // Pad to 3 digits, e.g., 001, 002

    return strtoupper($user_code) . $date_part . $sequence_part;
}

// ====================================================================
// EXPIRY NOTIFICATION SYSTEM - Core Functions
// ====================================================================

/**
 * Get services expiring within a specific number of days
 *
 * @param int $days_threshold Number of days to check (default: 30)
 * @param array $service_types Array of service types to check ['domain', 'hosting', 'maintenance']
 * @return array Array of expiring services grouped by service type
 */
function get_expiring_services($days_threshold = 30, $service_types = ['domain', 'hosting', 'maintenance']) {
    global $wpdb;

    $domains_table = $wpdb->prefix . 'im_domains';
    $hostings_table = $wpdb->prefix . 'im_hostings';
    $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
    $users_table = $wpdb->prefix . 'im_users';
    $product_catalog_table = $wpdb->prefix . 'im_product_catalog';

    $result = [
        'domains' => [],
        'hostings' => [],
        'maintenance_packages' => []
    ];

    $current_date = current_time('Y-m-d');
    $threshold_date = date('Y-m-d', strtotime("+{$days_threshold} days"));

    // Get expiring domains
    if (in_array('domain', $service_types)) {
        $domains = $wpdb->get_results($wpdb->prepare("
            SELECT
                d.id,
                d.domain_name AS name,
                d.owner_user_id,
                u.name AS owner_name,
                u.email AS owner_email,
                u.user_code AS owner_code,
                d.expiry_date,
                DATEDIFF(d.expiry_date, %s) AS days_remaining,
                COALESCE(pc.renew_price, 0) AS price,
                pc.name AS product_name,
                d.status
            FROM {$domains_table} d
            LEFT JOIN {$users_table} u ON d.owner_user_id = u.id
            LEFT JOIN {$product_catalog_table} pc ON d.product_catalog_id = pc.id
            WHERE d.expiry_date BETWEEN %s AND %s
                AND d.status = 'ACTIVE'
            ORDER BY d.expiry_date ASC
        ", $current_date, $current_date, $threshold_date));

        $result['domains'] = $domains;
    }

    // Get expiring hostings
    if (in_array('hosting', $service_types)) {
        $hostings = $wpdb->get_results($wpdb->prepare("
            SELECT
                h.id,
                h.hosting_code AS name,
                h.owner_user_id,
                u.name AS owner_name,
                u.email AS owner_email,
                u.user_code AS owner_code,
                h.expiry_date,
                DATEDIFF(h.expiry_date, %s) AS days_remaining,
                h.price,
                pc.name AS product_name,
                h.status
            FROM {$hostings_table} h
            LEFT JOIN {$users_table} u ON h.owner_user_id = u.id
            LEFT JOIN {$product_catalog_table} pc ON h.product_catalog_id = pc.id
            WHERE h.expiry_date BETWEEN %s AND %s
                AND h.status = 'ACTIVE'
            ORDER BY h.expiry_date ASC
        ", $current_date, $current_date, $threshold_date));

        $result['hostings'] = $hostings;
    }

    // Get expiring maintenance packages
    if (in_array('maintenance', $service_types)) {
        $maintenances = $wpdb->get_results($wpdb->prepare("
            SELECT
                m.id,
                m.order_code AS name,
                m.owner_user_id,
                u.name AS owner_name,
                u.email AS owner_email,
                u.user_code AS owner_code,
                m.expiry_date,
                DATEDIFF(m.expiry_date, %s) AS days_remaining,
                m.price_per_cycle AS price,
                CONCAT('Gói bảo trì ', m.billing_cycle_months, ' tháng') AS product_name,
                m.status
            FROM {$maintenance_table} m
            LEFT JOIN {$users_table} u ON m.owner_user_id = u.id
            WHERE m.expiry_date BETWEEN %s AND %s
                AND m.status = 'ACTIVE'
            ORDER BY m.expiry_date ASC
        ", $current_date, $current_date, $threshold_date));

        $result['maintenance_packages'] = $maintenances;
    }

    return $result;
}

/**
 * Get services at a specific expiry milestone
 * Milestones: -30, -7, -3, 0, +1, +14 (days relative to expiry date)
 *
 * @param int $milestone Number of days from expiry (negative = before, positive = after, 0 = today)
 * @return array Array of services grouped by service type
 */
function get_services_at_expiry_milestone($milestone) {
    global $wpdb;

    $domains_table = $wpdb->prefix . 'im_domains';
    $hostings_table = $wpdb->prefix . 'im_hostings';
    $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
    $users_table = $wpdb->prefix . 'im_users';
    $product_catalog_table = $wpdb->prefix . 'im_product_catalog';

    $result = [
        'domains' => [],
        'hostings' => [],
        'maintenance_packages' => []
    ];

    // Calculate target date based on milestone
    $target_date = date('Y-m-d', strtotime("{$milestone} days"));

    // Get domains at this milestone
    $domains = $wpdb->get_results($wpdb->prepare("
        SELECT
            d.id,
            d.domain_name AS name,
            d.owner_user_id,
            u.name AS owner_name,
            u.email AS owner_email,
            u.user_code AS owner_code,
            d.expiry_date,
            COALESCE(pc.renew_price, 0) AS price,
            pc.name AS product_name,
            d.status
        FROM {$domains_table} d
        LEFT JOIN {$users_table} u ON d.owner_user_id = u.id
        LEFT JOIN {$product_catalog_table} pc ON d.product_catalog_id = pc.id
        WHERE DATE(d.expiry_date) = %s
            AND d.status = 'ACTIVE'
        ORDER BY d.domain_name ASC
    ", $target_date));

    $result['domains'] = $domains;

    // Get hostings at this milestone
    $hostings = $wpdb->get_results($wpdb->prepare("
        SELECT
            h.id,
            h.hosting_code AS name,
            h.owner_user_id,
            u.name AS owner_name,
            u.email AS owner_email,
            u.user_code AS owner_code,
            h.expiry_date,
            h.price,
            pc.name AS product_name,
            h.status
        FROM {$hostings_table} h
        LEFT JOIN {$users_table} u ON h.owner_user_id = u.id
        LEFT JOIN {$product_catalog_table} pc ON h.product_catalog_id = pc.id
        WHERE DATE(h.expiry_date) = %s
            AND h.status = 'ACTIVE'
        ORDER BY h.hosting_code ASC
    ", $target_date));

    $result['hostings'] = $hostings;

    // Get maintenance packages at this milestone
    $maintenances = $wpdb->get_results($wpdb->prepare("
        SELECT
            m.id,
            m.order_code AS name,
            m.owner_user_id,
            u.name AS owner_name,
            u.email AS owner_email,
            u.user_code AS owner_code,
            m.expiry_date,
            m.price_per_cycle AS price,
            CONCAT('Gói bảo trì ', m.billing_cycle_months, ' tháng') AS product_name,
            m.status
        FROM {$maintenance_table} m
        LEFT JOIN {$users_table} u ON m.owner_user_id = u.id
        WHERE DATE(m.expiry_date) = %s
            AND m.status = 'ACTIVE'
        ORDER BY m.order_code ASC
    ", $target_date));

    $result['maintenance_packages'] = $maintenances;

    return $result;
}

// ====================================================================
// REST API ENDPOINTS - Expiry Services
// ====================================================================

/**
 * Register REST API routes for expiry services
 */
add_action('rest_api_init', function () {
    // GET endpoint: Get expiring services
    register_rest_route('inovamanager/v1', '/expiring-services', array(
        'methods' => 'GET',
        'callback' => 'api_get_expiring_services',
        'permission_callback' => function() {
            return current_user_can('read');
        }
    ));

    // POST endpoint: Create bulk invoice
    register_rest_route('inovamanager/v1', '/create-bulk-invoice', array(
        'methods' => 'POST',
        'callback' => 'api_create_bulk_invoice',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
});

/**
 * API Callback: Get expiring services
 *
 * GET /wp-json/inovamanager/v1/expiring-services?days=30&service_types=domain,hosting
 */
function api_get_expiring_services($request) {
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

/**
 * API Callback: Create bulk invoice for a user
 *
 * POST /wp-json/inovamanager/v1/create-bulk-invoice
 * Body: {
 *   "user_id": 5,
 *   "services": [
 *     {"service_type": "domain", "service_id": 12, "quantity": 1},
 *     {"service_type": "hosting", "service_id": 8, "quantity": 1}
 *   ],
 *   "invoice_date": "2025-10-22",
 *   "due_date": "2025-11-01",
 *   "notes": ""
 * }
 */
function api_create_bulk_invoice($request) {
    global $wpdb;

    $user_id = intval($request->get_param('user_id'));
    $services = $request->get_param('services');
    $invoice_date = sanitize_text_field($request->get_param('invoice_date'));
    $due_date = sanitize_text_field($request->get_param('due_date'));
    $notes = sanitize_textarea_field($request->get_param('notes'));

    // Validation
    if (!$user_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'user_id is required'
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
                    $new_expiry = date('Y-m-d', strtotime($domain->expiry_date . ' +1 year'));

                    $item_data = [
                        'service_type' => 'domain',
                        'service_id' => $service_id,
                        'description' => 'Gia hạn tên miền ' . $domain->domain_name . ' - 1 năm',
                        'unit_price' => $price,
                        'quantity' => $quantity,
                        'item_total' => $price * $quantity,
                        'start_date' => $domain->expiry_date,
                        'end_date' => $new_expiry
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
                    $new_expiry = date('Y-m-d', strtotime($hosting->expiry_date . " +{$months} months"));

                    $item_data = [
                        'service_type' => 'hosting',
                        'service_id' => $service_id,
                        'description' => 'Gia hạn hosting ' . $hosting->hosting_code . ' - ' . $months . ' tháng',
                        'unit_price' => $price,
                        'quantity' => $quantity,
                        'item_total' => $price * $quantity,
                        'start_date' => $hosting->expiry_date,
                        'end_date' => $new_expiry
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
                    $new_expiry = date('Y-m-d', strtotime($maintenance->expiry_date . " +{$months} months"));

                    $item_data = [
                        'service_type' => 'maintenance',
                        'service_id' => $service_id,
                        'description' => 'Gia hạn gói bảo trì ' . $maintenance->order_code . ' - ' . $months . ' tháng',
                        'unit_price' => $price,
                        'quantity' => $quantity,
                        'item_total' => $price * $quantity,
                        'start_date' => $maintenance->expiry_date,
                        'end_date' => $new_expiry
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

// ====================================================================
// EMAIL NOTIFICATION SYSTEM
// ====================================================================

/**
 * Get email subject based on milestone
 *
 * @param int $milestone Days from expiry (-30, -7, -3, 0, +1, +14)
 * @return string Email subject
 */
function get_expiry_email_subject($milestone) {
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

/**
 * Generate email body for expiry notification
 *
 * @param int $milestone Days from expiry
 * @param array $services Services grouped by type
 * @param object $user User object
 * @return string HTML email body
 */
function get_expiry_email_body($milestone, $services, $user) {
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
        if (empty($items)) continue;

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

/**
 * Get email notification settings for a user
 * Falls back to global defaults if user hasn't set preferences
 *
 * @param int $user_id User ID
 * @return array Array of enabled service types
 */
function get_user_email_notification_settings($user_id) {
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

/**
 * Send expiry notification emails for a specific milestone
 *
 * @param int $milestone Days from expiry (-30, -7, -3, 0, +1, +14)
 */
function send_expiry_notification_emails($milestone) {
    // Get services at this milestone
    $services = get_services_at_expiry_milestone($milestone);

    // Group services by user
    $services_by_user = [];

    foreach ($services as $type => $items) {
        foreach ($items as $item) {
            $user_id = $item->owner_user_id;

            if (!isset($services_by_user[$user_id])) {
                $services_by_user[$user_id] = [
                    'user' => (object)[
                        'id' => $user_id,
                        'name' => $item->owner_name,
                        'email' => $item->owner_email
                    ],
                    'services' => [
                        'domains' => [],
                        'hostings' => [],
                        'maintenance_packages' => []
                    ]
                ];
            }

            $services_by_user[$user_id]['services'][$type][] = $item;
        }
    }

    // Send email to each user based on their preferences
    foreach ($services_by_user as $user_id => $data) {
        $user = $data['user'];
        $user_services = $data['services'];

        if (empty($user->email)) {
            continue; // Skip if no email
        }

        // Get user's email notification preferences
        $enabled_types = get_user_email_notification_settings($user_id);

        // Filter services based on user preferences
        $filtered_services = [];
        foreach ($user_services as $type => $items) {
            $service_type_key = rtrim($type, 's'); // Remove 's' from end (domains -> domain)

            if (in_array($service_type_key, $enabled_types) && !empty($items)) {
                $filtered_services[$type] = $items;
            }
        }

        // Skip if no services to notify about after filtering
        if (empty($filtered_services)) {
            continue;
        }

        $subject = get_expiry_email_subject($milestone);
        $body = get_expiry_email_body($milestone, $filtered_services, $user);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: INOVA Manager <no-reply@inova.vn>'
        ];

        wp_mail($user->email, $subject, $body, $headers);

        // Log for debugging
        error_log("Sent expiry notification email to {$user->email} for milestone {$milestone}");
    }
}

/**
 * Main cron function - Check all milestones and send emails
 */
function check_and_send_expiry_emails() {
    // Define milestones (hard-coded, not configurable)
    $milestones = [-30, -7, -3, 0, 1, 14];

    foreach ($milestones as $milestone) {
        send_expiry_notification_emails($milestone);
    }

    error_log('Expiry notification emails check completed at ' . current_time('Y-m-d H:i:s'));
}

/**
 * Add custom cron schedule (daily at 8 AM)
 */
add_filter('cron_schedules', function($schedules) {
    $schedules['daily_8am'] = [
        'interval' => 86400, // 24 hours
        'display'  => __('Daily at 8 AM')
    ];
    return $schedules;
});

/**
 * Schedule the cron job
 */
function schedule_expiry_check_cron() {
    if (!wp_next_scheduled('inovamanager_check_expiry_daily')) {
        wp_schedule_event(strtotime('08:00:00'), 'daily_8am', 'inovamanager_check_expiry_daily');
    }
}
add_action('after_switch_theme', 'schedule_expiry_check_cron');
add_action('init', 'schedule_expiry_check_cron');

/**
 * Hook the cron job to the actual function
 */
add_action('inovamanager_check_expiry_daily', 'check_and_send_expiry_emails');

// ====================================================================
// AJAX HANDLERS - Email Notification Settings
// ====================================================================

/**
 * AJAX handler: Update email notification setting for current user
 */
add_action('wp_ajax_update_email_notification_setting', 'update_email_notification_setting_callback');

function update_email_notification_setting_callback() {
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

/**
 * AJAX handler: Update my VAT invoice setting (user self-service)
 * User updates their own VAT invoice info
 */
add_action('wp_ajax_update_my_vat_invoice_setting', 'update_my_vat_invoice_setting_callback');
add_action('wp_ajax_delete_contact', 'delete_contact_callback');

function delete_contact_callback() {
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

function update_my_vat_invoice_setting_callback() {
    global $wpdb;

    // Get current Inova user
    $current_user = get_inova_user();

    if (!$current_user) {
        wp_send_json_error(['message' => 'Không tìm thấy người dùng']);
        return;
    }

    $current_user_id = $current_user->id;

    // Sanitize inputs
    $company_name   = sanitize_text_field($_POST['company_name']);
    $tax_code       = sanitize_text_field($_POST['tax_code']);
    $invoice_email  = $_POST['invoice_email'];
    $invoice_phone  = sanitize_text_field($_POST['invoice_phone']);
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

/**
 * AJAX handler: Update VAT invoice setting for current user
 * Saves to im_users table instead of user meta
 */
add_action('wp_ajax_update_vat_invoice_setting', 'update_vat_invoice_setting_callback');

function update_vat_invoice_setting_callback() {
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

/**
 * AJAX handler: Get VAT invoice list for admin/partner
 * Returns paginated list of users with VAT invoice settings
 * Admin: Lấy tất cả users
 * Partner: Lấy users sở hữu services của partner
 */
add_action('wp_ajax_get_vat_invoice_list', 'get_vat_invoice_list_callback');

function get_vat_invoice_list_callback() {
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

/**
 * AJAX handler: Save VAT invoice for specific user (admin/partner only)
 */
add_action('wp_ajax_save_vat_invoice_for_user', 'save_vat_invoice_for_user_callback');

function save_vat_invoice_for_user_callback() {
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
            $current_user->id, $target_user_id, $current_user->id, $target_user_id
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

/**
 * AJAX handler: Delete VAT invoice settings for user
 */
add_action('wp_ajax_delete_vat_invoice', 'delete_vat_invoice_callback');

function delete_vat_invoice_callback() {
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
                    $current_user->id, $target_user_id, $current_user->id, $target_user_id
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

// ====================================================================
// PDF INVOICE GENERATION - Dompdf
// ====================================================================

// Import Dompdf classes
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Include PDF template functions
 */
require_once get_template_directory() . '/pdf_invoice_template.php';

/**
 * AJAX handler: Generate and download invoice PDF
 */
add_action('wp_ajax_generate_invoice_pdf', 'generate_invoice_pdf_callback');
add_action('wp_ajax_nopriv_generate_invoice_pdf', 'generate_invoice_pdf_callback');

function generate_invoice_pdf_callback() {
    if (!isset($_GET['invoice_id'])) {
        wp_die('Invoice ID is required');
    }

    $invoice_id = intval($_GET['invoice_id']);

    // Get invoice HTML
    $html = get_invoice_pdf_html($invoice_id);

    // Configure Dompdf
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', false);

    // Create Dompdf instance
    $dompdf = new \Dompdf\Dompdf($options);

    // Load HTML
    $dompdf->loadHtml($html);

    // Set paper size and orientation
    $dompdf->setPaper('A4', 'portrait');

    // Render PDF (first pass)
    $dompdf->render();

    // Get invoice code for filename
    global $wpdb;
    $invoice_table = $wpdb->prefix . 'im_invoices';
    $invoice = $wpdb->get_row($wpdb->prepare("SELECT invoice_code FROM {$invoice_table} WHERE id = %d", $invoice_id));
    $filename = $invoice ? "invoice-{$invoice->invoice_code}.pdf" : "invoice-{$invoice_id}.pdf";

    // Output PDF (download)
    $dompdf->stream($filename, [
        "Attachment" => true  // true = download, false = preview in browser
    ]);

    exit;
}

/**
 * AJAX handler: Delete website for user/admin
 * Files using this: website_list.php
 * 
 * Permission rules:
 * - Only creator (created_by) or admin can delete
 * - If created_by is NULL, only admin can delete
 * - Cannot delete if has domain_id, hosting_id, or maintenance_package_id
 */
add_action('wp_ajax_delete_website_user', 'delete_website_user_callback');

function delete_website_user_callback() {
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
        if (!empty($website->domain_id)) $services[] = 'tên miền';
        if (!empty($website->hosting_id)) $services[] = 'hosting';
        if (!empty($website->maintenance_package_id)) $services[] = 'gói bảo trì';
        
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

/**
 * AJAX handler: Delete domain for users
 * Files using this: domain_list.php (inline AJAX call)
 * 
 * Permission rules:
 * - Only creator (created_by) can delete their own domain
 * - When deleting, gỡ liên kết domain from websites (set domain_id to NULL)
 */
add_action('wp_ajax_delete_domain_user', 'delete_domain_user_callback');

function delete_domain_user_callback() {
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