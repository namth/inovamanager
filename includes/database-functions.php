<?php
/* Database Functions - Imported from functions.php */

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
        `gender` enum('MALE','FEMALE','OTHER') NULL,
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
        `status` enum('NEW','ACTIVE','DELETED','EXPIRED','SUSPENDED') NOT NULL DEFAULT 'NEW',
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
        `status` enum('NEW','ACTIVE','DELETED','EXPIRED','SUSPENDED') NOT NULL DEFAULT 'NEW',
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
        `status` enum('NEW','ACTIVE','DELETED','EXPIRED','SUSPENDED') NOT NULL DEFAULT 'NEW',
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

    # 10. partner_discount_rates table - Quản lý tỷ lệ chiết khấu cho từng loại dịch vụ
    $discountRatesTable = $wpdb->prefix . 'im_partner_discount_rates';
    $createTable = "CREATE TABLE `{$discountRatesTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `partner_id` bigint(20) UNSIGNED NOT NULL,
        `service_type` enum('hosting','maintenance','website_service') NOT NULL,
        `discount_rate` decimal(5, 2) NOT NULL COMMENT 'Tỷ lệ chiết khấu (%)',
        `effective_date` date NOT NULL COMMENT 'Ngày áp dụng',
        `end_date` date NULL COMMENT 'Ngày kết thúc (NULL = vẫn còn hiệu lực)',
        `notes` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_partner_service` (`partner_id`, `service_type`, `effective_date`),
        KEY `partner_id` (`partner_id`),
        KEY `service_type` (`service_type`),
        KEY `effective_date` (`effective_date`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 11. partner_commissions table
    $commissionsTable = $wpdb->prefix . 'im_partner_commissions';
    $createTable = "CREATE TABLE `{$commissionsTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `partner_id` bigint(20) UNSIGNED NOT NULL,
        `invoice_id` bigint(20) UNSIGNED NOT NULL,
        `invoice_item_id` bigint(20) UNSIGNED NULL,
        `service_type` varchar(255) NULL COMMENT 'Loại dịch vụ (hosting/maintenance/website_service)',
         `commission_amount` bigint(20) NOT NULL,
         `discount_rate` decimal(5, 2) NULL COMMENT 'Tỷ lệ chiết khấu áp dụng (%)',
        `service_amount` bigint(20) NULL COMMENT 'Giá trị ban đầu của dịch vụ',
        `calculation_date` date NOT NULL,
        `status` enum('PENDING','PAID','WITHDRAWN','CANCELLED') NOT NULL DEFAULT 'PENDING',
        `payout_date` date NULL COMMENT 'Ngày thanh toán',
        `payout_amount` bigint(20) NULL COMMENT 'Số tiền thực tế thanh toán',
        `withdrawal_date` date NULL COMMENT 'Ngày rút tiền (cuối cùng)',
        `notes` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `partner_id` (`partner_id`),
        KEY `invoice_id` (`invoice_id`),
        KEY `invoice_item_id` (`invoice_item_id`),
        KEY `status` (`status`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 12. recurring_expenses table - Quản lý chi tiêu định kỳ
    $recurring_expenses_table = $wpdb->prefix . 'im_recurring_expenses';
    $createTable = "CREATE TABLE `{$recurring_expenses_table}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL COMMENT 'Tên chi tiêu',
        `category` varchar(100) NOT NULL COMMENT 'Danh mục',
        `vendor` varchar(255) COMMENT 'Nhà cung cấp',
        `amount` bigint(20) NOT NULL COMMENT 'Số tiền (VNĐ)',
        `start_date` date NOT NULL COMMENT 'Ngày bắt đầu',
        `end_date` date COMMENT 'Ngày kết thúc',
        `status` enum('ACTIVE','INACTIVE','EXPIRED','SUSPENDED') DEFAULT 'ACTIVE',
        `note` text COMMENT 'Ghi chú',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `status` (`status`),
        KEY `category` (`category`),
        KEY `start_date` (`start_date`),
        KEY `end_date` (`end_date`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 13. Initialize default VAT rates in wp_options
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

    # 13. website_services table - Quản lý yêu cầu chỉnh sửa có tính phí
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

    # 14. cart table - Giỏ hàng cho các dịch vụ cần thanh toán
    $cartTable = $wpdb->prefix . 'im_cart';
    $createTable = "CREATE TABLE `{$cartTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) UNSIGNED NOT NULL,
        `service_type` enum('domain','hosting','maintenance','website_service') NOT NULL,
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

function get_partner_id_from_items($items)
{
    foreach ($items as $item) {
        $partner_id = get_partner_id_from_service($item['service_type'], $item['service_id']);
        if (!empty($partner_id)) {
            return $partner_id;
        }
    }
    return null;
}

function get_partner_id_from_service($service_type, $service_id)
{
    global $wpdb;

    if (!$service_id) {
        return null;
    }

    $normalized_type = normalize_service_type_for_commission($service_type);

    switch ($normalized_type) {
        case 'hosting':
            $table = $wpdb->prefix . 'im_hostings';
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT partner_id FROM `{$table}` WHERE id = %d",
                $service_id
            ));
            return $result ? intval($result) : null;

        case 'maintenance':
            $table = $wpdb->prefix . 'im_maintenance_packages';
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT partner_id FROM `{$table}` WHERE id = %d",
                $service_id
            ));
            return $result ? intval($result) : null;

        case 'website_service':
        default:
            return null;
    }
}

function get_service_partner_id($service_type, $service_id)
{
    global $wpdb;

    if (!$service_id) {
        return false;
    }

    $service_type = strtolower($service_type);

    switch ($service_type) {
        case 'domain':
            $table = $wpdb->prefix . 'im_domains';
            break;
        case 'hosting':
            $table = $wpdb->prefix . 'im_hostings';
            break;
        case 'maintenance':
            $table = $wpdb->prefix . 'im_maintenance_packages';
            break;
        case 'website_service':
        case 'website':
            $table = $wpdb->prefix . 'im_website_services';
            break;
        default:
            return false;
    }

    $partner_id = $wpdb->get_var($wpdb->prepare(
        "SELECT provider_id FROM $table WHERE id = %d LIMIT 1",
        $service_id
    ));

    return $partner_id ? intval($partner_id) : false;
}

function get_partner_discount_rate($partner_id, $service_type, $effective_date = null)
{
    global $wpdb;

    if (!$effective_date) {
        $effective_date = date('Y-m-d');
    }

    $rates_table = $wpdb->prefix . 'im_partner_discount_rates';

    // Get the most recent rate that is effective on the given date
    // and hasn't ended yet (end_date is NULL or in the future)
    $query = $wpdb->prepare(
        "SELECT `discount_rate` FROM `{$rates_table}`
         WHERE `partner_id` = %d
         AND `service_type` = %s
         AND `effective_date` <= %s
         AND (`end_date` IS NULL OR `end_date` > %s)
         ORDER BY `effective_date` DESC
         LIMIT 1",
        $partner_id,
        $service_type,
        $effective_date,
        $effective_date
    );

    $result = $wpdb->get_var($query);
    return $result ? floatval($result) : 0;
}

function calculate_commission_amount($service_amount, $discount_rate)
{
    return round(intval($service_amount) * floatval($discount_rate) / 100);
}

function create_commissions_for_invoice($invoice_id)
{
    global $wpdb;

    $invoices_table = $wpdb->prefix . 'im_invoices';
    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
    $commissions_table = $wpdb->prefix . 'im_partner_commissions';

    // Get invoice
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$invoices_table}` WHERE id = %d",
        $invoice_id
    ));

    if (!$invoice) {
        return false;
    }

    // Get invoice items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$invoice_items_table}` WHERE invoice_id = %d",
        $invoice_id
    ));

    if (empty($items)) {
        return false;
    }

    $calculation_date = current_time('Y-m-d');
    $success_count = 0;

    // Create commission for each item
    foreach ($items as $item) {
        // Normalize service type to standard format
        $normalized_service_type = normalize_service_type_for_commission($item->service_type);

        // Skip if service type is not applicable for commissions (e.g., domains)
        if (empty($normalized_service_type)) {
            continue;
        }

        // Get partner_id: from invoice if set, otherwise from service item
        $partner_id = $invoice->partner_id;
        if (empty($partner_id)) {
            $partner_id = get_partner_id_from_service($item->service_type, $item->service_id);
        }

        // Skip if no partner found
        if (empty($partner_id)) {
            continue;
        }

        // Get discount rate for this service type
        $discount_rate = get_partner_discount_rate(
            $partner_id,
            $normalized_service_type,
            $calculation_date
        );

        // Skip if no discount rate found
        if ($discount_rate <= 0) {
            continue;
        }

        // Calculate commission amount
        $commission_amount = calculate_commission_amount(
            $item->item_total,
            $discount_rate
        );

        // Insert into partner_commissions table
        $result = $wpdb->insert(
            $commissions_table,
            array(
                'partner_id' => $partner_id,
                'invoice_id' => $invoice_id,
                'invoice_item_id' => $item->id,
                'service_type' => $normalized_service_type,
                'commission_amount' => $commission_amount,
                'discount_rate' => $discount_rate,
                'service_amount' => $item->item_total,
                'calculation_date' => $calculation_date,
                'status' => 'PENDING',
            ),
            array('%d', '%d', '%d', '%s', '%d', '%f', '%d', '%s', '%s')
        );

        if ($result !== false) {
            $success_count++;
        }
    }

    return $success_count > 0;
}

function create_commissions_from_discount_data($invoice_id, $commission_data)
{
    global $wpdb;

    if (empty($commission_data) || !is_array($commission_data)) {
        return 0;
    }

    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
    $commissions_table = $wpdb->prefix . 'im_partner_commissions';
    $calculation_date = current_time('Y-m-d');
    $success_count = 0;

    foreach ($commission_data as $commission_info) {
        // Get invoice item to link commission
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$invoice_items_table}` 
             WHERE invoice_id = %d AND service_type = %s AND service_id = %d
             LIMIT 1",
            $invoice_id,
            $commission_info['service_type'],
            $commission_info['service_id']
        ));

        if (!$item) {
            continue;
        }

        // Insert commission record
        $result = $wpdb->insert(
            $commissions_table,
            array(
                'partner_id' => intval($commission_info['partner_id']),
                'invoice_id' => $invoice_id,
                'invoice_item_id' => $item->id,
                'service_type' => sanitize_text_field($commission_info['service_type']),
                'commission_amount' => floatval($commission_info['amount']),
                'discount_rate' => 0, // Not using discount rate since commission_amount is from discount_amount
                'service_amount' => floatval($item->item_total),
                'calculation_date' => $calculation_date,
                'status' => 'PENDING',
            ),
            array('%d', '%d', '%d', '%s', '%d', '%f', '%d', '%s', '%s')
        );

        if ($result !== false) {
            $success_count++;
        }
    }

    return $success_count;
}

function update_commissions_on_invoice_paid($invoice_id)
{
    global $wpdb;

    $invoices_table = $wpdb->prefix . 'im_invoices';
    $commissions_table = $wpdb->prefix . 'im_partner_commissions';

    // Get invoice to get payment date
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$invoices_table}` WHERE id = %d",
        $invoice_id
    ));

    if (!$invoice || !$invoice->payment_date) {
        return false;
    }

    // Update all commissions for this invoice to PAID status
    $result = $wpdb->update(
        $commissions_table,
        array(
            'status' => 'PAID',
            'payout_date' => date('Y-m-d', strtotime($invoice->payment_date))
        ),
        array('invoice_id' => $invoice_id),
        array('%s', '%s'),
        array('%d')
    );

    return $result !== false;
}

function cancel_commissions_for_invoice($invoice_id)
{
    global $wpdb;

    $commissions_table = $wpdb->prefix . 'im_partner_commissions';

    // Delete all commissions associated with this invoice completely
    $result = $wpdb->delete(
        $commissions_table,
        array('invoice_id' => $invoice_id),
        array('%d')
    );

    return $result !== false;
}

function schedule_auto_renewal_invoices()
{
    if (!wp_next_scheduled('auto_create_renewal_invoices')) {
        wp_schedule_event(time(), 'daily', 'auto_create_renewal_invoices');
    }
}

function auto_create_renewal_invoices_callback()
{
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
    // Get websites table for joining
    $websites_table = $wpdb->prefix . 'im_websites';

    // 1. Get INOVA-managed domains
    $expiring_domains = $wpdb->get_results($wpdb->prepare("
        SELECT d.*, u.user_code, u.name as owner_name, COALESCE(pc.renew_price, 0) AS renew_price
        FROM {$domains_table} d
        LEFT JOIN {$users_table} u ON d.owner_user_id = u.id
        LEFT JOIN {$product_catalog_table} pc ON d.product_catalog_id = pc.id
        WHERE d.managed_by_inova = 1
        AND d.status = 'ACTIVE'
        AND d.expiry_date BETWEEN %s AND %s
    ", $date_30_days, $date_51_days));

    // 2. Get all hostings with website names
    $expiring_hostings = $wpdb->get_results($wpdb->prepare("
        SELECT h.*, u.user_code, u.name as owner_name, COALESCE(pc.renew_price, 0) AS renew_price,
               GROUP_CONCAT(w.name SEPARATOR ', ') as website_names
        FROM {$hostings_table} h
        LEFT JOIN {$users_table} u ON h.owner_user_id = u.id
        LEFT JOIN {$product_catalog_table} pc ON h.product_catalog_id = pc.id
        LEFT JOIN {$websites_table} w ON w.hosting_id = h.id
        WHERE h.status = 'ACTIVE'
        AND h.expiry_date BETWEEN %s AND %s
        GROUP BY h.id
    ", $date_30_days, $date_51_days));

    // 3. Get all maintenance packages with website names
    $expiring_maintenance = $wpdb->get_results($wpdb->prepare("
        SELECT m.*, u.user_code, u.name as owner_name,
               GROUP_CONCAT(w.name SEPARATOR ', ') as website_names
        FROM {$maintenance_table} m
        LEFT JOIN {$users_table} u ON m.owner_user_id = u.id
        LEFT JOIN {$websites_table} w ON w.maintenance_package_id = m.id
        WHERE m.status = 'ACTIVE'
        AND m.expiry_date BETWEEN %s AND %s
        GROUP BY m.id
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
        foreach ($user_services['domains'] as $d)
            $service_ids[] = 'D' . $d->id;
        foreach ($user_services['hostings'] as $h)
            $service_ids[] = 'H' . $h->id;
        foreach ($user_services['maintenances'] as $m)
            $service_ids[] = 'M' . $m->id;

        // Skip if invoice already created
        $existing_check = implode(',', array_map(function ($id) {
            return "'" . esc_sql($id) . "'";
        }, $service_ids));
        $existing_invoice = $wpdb->get_var("
            SELECT COUNT(*) FROM {$invoice_items_table}
            WHERE CONCAT(LEFT(service_type, 1), service_id) IN ({$existing_check})
            AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");

        if ($existing_invoice > 0) {
            continue; // Already has invoice
        }

        // Prepare invoice items array
        $items = array();

        // Add domain items
        foreach ($user_services['domains'] as $domain) {
            $unit_price = floatval($domain->renew_price);
            $quantity = 1;
            $item_total = $unit_price * $quantity;

            // Get VAT rate for Domain service
            $vat_rate = get_vat_rate_for_service('Domain');
            $vat_amount = calculate_vat_amount($item_total, $vat_rate);

            // Determine start_date and end_date based on status
            if ($domain->status === 'NEW') {
                $start_date = $domain->registration_date;
                $end_date = $domain->expiry_date;
            } else {
                $start_date = $domain->expiry_date;
                $end_date = date('Y-m-d', strtotime($domain->expiry_date . ' +1 year'));
            }

            $items[] = array(
                'service_type' => 'domain',
                'service_id' => $domain->id,
                'description' => 'Gia hạn tên miền: ' . $domain->domain_name,
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                'item_total' => $item_total,
                'vat_rate' => $vat_rate,
                'vat_amount' => $vat_amount,
                'start_date' => $start_date,
                'end_date' => $end_date
            );
        }

        // Add hosting items
        foreach ($user_services['hostings'] as $hosting) {
            $hosting_code = !empty($hosting->hosting_code) ? $hosting->hosting_code : 'HOST-' . $hosting->id;
            $unit_price = floatval($hosting->renew_price);
            $quantity = 1;
            $item_total = $unit_price * $quantity;

            // Get VAT rate for Hosting service
            $vat_rate = get_vat_rate_for_service('Hosting');
            $vat_amount = calculate_vat_amount($item_total, $vat_rate);

            // Determine start_date and end_date based on status
            if ($hosting->status === 'NEW') {
                $start_date = $hosting->registration_date;
                $end_date = $hosting->expiry_date;
            } else {
                $start_date = $hosting->expiry_date;
                $end_date = date('Y-m-d', strtotime($hosting->expiry_date . ' +1 year'));
            }

            $items[] = array(
                'service_type' => 'hosting',
                'service_id' => $hosting->id,
                'description' => 'Gia hạn hosting: ' . $hosting_code,
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                'item_total' => $item_total,
                'vat_rate' => $vat_rate,
                'vat_amount' => $vat_amount,
                'start_date' => $start_date,
                'end_date' => $end_date
            );
        }

        // Add maintenance items
        foreach ($user_services['maintenances'] as $maintenance) {
            $maintenance_code = !empty($maintenance->order_code) ? $maintenance->order_code : 'MAINT-' . $maintenance->id;
            $months = $maintenance->billing_cycle_months;
            $unit_price = floatval($maintenance->actual_revenue);
            $quantity = 1;
            $item_total = $unit_price * $quantity;

            // Get VAT rate for Maintenance service
            $vat_rate = get_vat_rate_for_service('Maintenance');
            $vat_amount = calculate_vat_amount($item_total, $vat_rate);

            // Determine start_date and end_date based on status
            if ($maintenance->status === 'NEW') {
                $start_date = $maintenance->registration_date;
                $end_date = $maintenance->expiry_date;
            } else {
                $start_date = $maintenance->expiry_date;
                $end_date = date('Y-m-d', strtotime($maintenance->expiry_date . ' +' . $months . ' months'));
            }

            $items[] = array(
                'service_type' => 'maintenance',
                'service_id' => $maintenance->id,
                'description' => 'Gia hạn bảo trì: ' . $maintenance_code,
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                'item_total' => $item_total,
                'vat_rate' => $vat_rate,
                'vat_amount' => $vat_amount,
                'start_date' => $start_date,
                'end_date' => $end_date
            );
        }

        // Create invoice using unified function
        $invoice_id = create_invoice_with_items(
            $user_id,
            $items,
            'Hóa đơn gia hạn dịch vụ tự động',
            'pending',
            0,
            'system'
        );

        // Send webhook notification if invoice created successfully
        if ($invoice_id) {
            trigger_invoice_creation_webhook($invoice_id, $user_id);
        }
    }
}

function create_invoice_with_items($user_id, $items, $notes = '', $status = 'pending', $created_by_id = 0, $created_by_type = 'system', $invoice_date = '', $due_date = '', $discount_total = 0)
{
    global $wpdb;

    if (empty($items) || !is_array($items)) {
        return false;
    }

    $invoices_table = $wpdb->prefix . 'im_invoices';
    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
    $users_table = $wpdb->prefix . 'im_users';

    // Get user's requires_vat_invoice setting
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT requires_vat_invoice FROM $users_table WHERE id = %d",
        $user_id
    ));
    $requires_vat_invoice = ($user && isset($user->requires_vat_invoice)) ? intval($user->requires_vat_invoice) : 0;

    // Calculate totals from items
    $sub_total = 0;
    $tax_amount = 0;

    foreach ($items as $item) {
        $item_total = floatval($item['item_total'] ?? 0);
        $vat_amount = floatval($item['vat_amount'] ?? 0);

        $sub_total += $item_total;
        // Only add VAT if requires_vat_invoice = 1
        if ($requires_vat_invoice) {
            $tax_amount += $vat_amount;
        }
    }

    $total_amount = $sub_total + $tax_amount - $discount_total;

    // Get partner_id from items
    $partner_id = get_partner_id_from_items($items);

    // Generate invoice code
    $invoice_code = generate_invoice_code($user_id);

    // Set default dates if not provided
    if (empty($invoice_date)) {
        $invoice_date = date('Y-m-d');
    }
    if (empty($due_date)) {
        $due_date = date('Y-m-d', strtotime('+15 days'));
    }

    // Create invoice record
    $invoice_data = array(
        'invoice_code' => $invoice_code,
        'user_id' => $user_id,
        'invoice_date' => $invoice_date,
        'due_date' => $due_date,
        'sub_total' => $sub_total,
        'discount_total' => $discount_total,
        'tax_amount' => $tax_amount,
        'total_amount' => $total_amount,
        'notes' => $notes,
        'status' => $status,
        'created_by_type' => $created_by_type,
        'created_by_id' => $created_by_id,
        'partner_id' => $partner_id,
        'requires_vat_invoice' => $requires_vat_invoice,
    );

    // Insert invoice
    $wpdb->insert($invoices_table, $invoice_data);
    $invoice_id = $wpdb->insert_id;

    if (!$invoice_id) {
        return false;
    }

    // Insert all invoice items
    foreach ($items as $item) {
        $insert_data = array(
            'invoice_id' => $invoice_id,
            'service_type' => sanitize_text_field($item['service_type']),
            'service_id' => intval($item['service_id']),
            'description' => sanitize_text_field($item['description']),
            'unit_price' => floatval($item['unit_price']),
            'quantity' => intval($item['quantity']),
            'item_total' => floatval($item['item_total']),
            'vat_rate' => floatval($item['vat_rate'] ?? 0),
            'vat_amount' => floatval($item['vat_amount'] ?? 0),
        );

        // Add optional start_date and end_date
        if (isset($item['start_date']) && !empty($item['start_date'])) {
            $insert_data['start_date'] = $item['start_date'];
        }

        if (isset($item['end_date']) && !empty($item['end_date'])) {
            $insert_data['end_date'] = $item['end_date'];
        }

        $wpdb->insert($invoice_items_table, $insert_data);
    }

    // Create commissions for invoice
    create_commissions_for_invoice($invoice_id);

    return $invoice_id;
}

function generate_invoice_code($user_id)
{
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
    
    $next = $count + 1;
    $sequence_part = str_pad($next, 3, '0', STR_PAD_LEFT);
    $invoice_code = strtoupper($user_code) . $date_part . $sequence_part;

    // 4. Ensure the code is unique, especially if an invoice has been deleted before
    while ($wpdb->get_var($wpdb->prepare("SELECT invoice_code FROM $invoices_table WHERE invoice_code = %s", $invoice_code))) {
        $next++;
        $sequence_part = str_pad($next, 3, '0', STR_PAD_LEFT);
        $invoice_code = strtoupper($user_code) . $date_part . $sequence_part;
    }

    return $invoice_code;
}

function get_expiring_services($days_threshold = 30, $service_types = ['domain', 'hosting', 'maintenance'])
{
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

function get_services_at_expiry_milestone($milestone)
{
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
    $websites_table = $wpdb->prefix . 'im_websites';
    $hostings = $wpdb->get_results($wpdb->prepare("
        SELECT
            h.id,
            h.hosting_code AS name,
            h.owner_user_id,
            u.name AS owner_name,
            u.email AS owner_email,
            u.user_code AS owner_code,
            h.expiry_date,
            COALESCE(pc.renew_price, 0) AS price,
            pc.name AS product_name,
            h.status,
            GROUP_CONCAT(w.name SEPARATOR ', ') AS website_names
        FROM {$hostings_table} h
        LEFT JOIN {$users_table} u ON h.owner_user_id = u.id
        LEFT JOIN {$product_catalog_table} pc ON h.product_catalog_id = pc.id
        LEFT JOIN {$websites_table} w ON w.hosting_id = h.id
        WHERE DATE(h.expiry_date) = %s
            AND h.status = 'ACTIVE'
        GROUP BY h.id
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
            m.status,
            GROUP_CONCAT(w.name SEPARATOR ', ') AS website_names
        FROM {$maintenance_table} m
        LEFT JOIN {$users_table} u ON m.owner_user_id = u.id
        LEFT JOIN {$websites_table} w ON w.maintenance_package_id = m.id
        WHERE DATE(m.expiry_date) = %s
            AND m.status = 'ACTIVE'
        GROUP BY m.id
        ORDER BY m.order_code ASC
    ", $target_date));

    $result['maintenance_packages'] = $maintenances;

    return $result;
}

function send_expiry_notification_emails($milestone)
{
    // Get services at this milestone
    $services = get_services_at_expiry_milestone($milestone);

    // Group services by user
    $services_by_user = [];

    foreach ($services as $type => $items) {
        foreach ($items as $item) {
            $user_id = $item->owner_user_id;

            if (!isset($services_by_user[$user_id])) {
                $services_by_user[$user_id] = [
                    'user' => (object) [
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

function check_and_send_expiry_emails()
{
    // Define milestones (hard-coded, not configurable)
    $milestones = [-30, -7, -3, 0, 1, 14];

    // Collect all expiring services for webhook
    $all_expiring_domains = [];
    $all_expiring_hostings = [];
    $all_expiring_maintenance = [];

    foreach ($milestones as $milestone) {
        send_expiry_notification_emails($milestone);

        // Collect services for webhook (only collect once, avoiding duplicates)
        $services = get_services_at_expiry_milestone($milestone);
        $all_expiring_domains = array_merge($all_expiring_domains, $services['domains']);
        $all_expiring_hostings = array_merge($all_expiring_hostings, $services['hostings']);
        $all_expiring_maintenance = array_merge($all_expiring_maintenance, $services['maintenance_packages']);
    }

    // Remove duplicates by ID
    $all_expiring_domains = array_unique($all_expiring_domains, SORT_REGULAR);
    $all_expiring_hostings = array_unique($all_expiring_hostings, SORT_REGULAR);
    $all_expiring_maintenance = array_unique($all_expiring_maintenance, SORT_REGULAR);

    // Send webhook notification if there are any expiring services
    if (!empty($all_expiring_domains) || !empty($all_expiring_hostings) || !empty($all_expiring_maintenance)) {
        trigger_expiry_check_webhook($all_expiring_domains, $all_expiring_hostings, $all_expiring_maintenance);
    }

    error_log('Expiry notification emails check completed at ' . current_time('Y-m-d H:i:s'));
}

function schedule_expiry_check_cron()
{
    if (!wp_next_scheduled('inovamanager_check_expiry_daily')) {
        wp_schedule_event(strtotime('08:00:00'), 'daily_8am', 'inovamanager_check_expiry_daily');
    }
}

function handle_invoice_form_submission()
{
    global $wpdb;

    $action = sanitize_text_field($_POST['action']);
    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    $user_id = intval($_POST['user_id']);
    $invoice_date = sanitize_text_field($_POST['invoice_date']);
    $due_date = sanitize_text_field($_POST['due_date']);
    $notes = sanitize_textarea_field($_POST['notes']);
    $discount_total = floatval($_POST['discount_total']);
    $tax_amount = floatval($_POST['tax_amount']);

    // Convert date format from DD/MM/YYYY to YYYY-MM-DD
    $formatted_invoice_date = convert_date_format($invoice_date);
    $formatted_due_date = convert_date_format($due_date);

    // Process invoice items
    $items = process_invoice_items();

    if (empty($items)) {
        echo '<div class="alert alert-danger">Lỗi: Hóa đơn phải có ít nhất một item!</div>';
        return;
    }

    // Process discount logic: separate invoice discount and commission data
    $discount_result = process_discount_and_commission($items);
    $items = $discount_result['items'];
    $auto_discount = $discount_result['invoice_discount']; // Discount from items without partner_id
    $commission_data = $discount_result['commission_data']; // Commission to create for items with partner_id

    // Add auto discount to total discount from form
    $final_discount_total = $discount_total + $auto_discount;

    $invoices_table = $wpdb->prefix . 'im_invoices';
    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';

    if ($action === 'add_invoice') {
        $invoice_status = isset($_POST['finalize']) && $_POST['finalize'] == 1 ? 'pending' : 'draft';
        $new_invoice_id = create_invoice_with_items(
            $user_id,
            $items,
            $notes,
            $invoice_status,
            get_current_user_id(),
            'admin',
            $formatted_invoice_date,
            $formatted_due_date,
            $final_discount_total
        );

        if (!$new_invoice_id) {
            echo '<div class="alert alert-danger">Lỗi: Không thể tạo hóa đơn!</div>';
        } else {
            echo '<div class="alert alert-success">Hóa đơn đã được tạo thành công!</div>';

            // Create commissions for items with partner_id
            if (!empty($commission_data)) {
                create_commissions_from_discount_data($new_invoice_id, $commission_data);
            }

            // Clear cart items if invoice was created from cart
            if (isset($_POST['from_cart']) && $_POST['from_cart'] == 1) {
                $cart_table = $wpdb->prefix . 'im_cart';
                $wpdb->delete($cart_table, ['user_id' => $user_id]);
            }

            // Redirect to invoice detail page if finalized
            if (isset($_POST['finalize']) && $_POST['finalize'] == 1) {
                wp_redirect(home_url('/chi-tiet-hoa-don/?invoice_id=' . $new_invoice_id));
                exit;
            } elseif (isset($_POST['save_finalized']) && $_POST['save_finalized'] == 1) {
                $wpdb->update($invoices_table, ['status' => 'pending'], ['id' => $new_invoice_id]);
                wp_redirect(home_url('/chi-tiet-hoa-don/?invoice_id=' . $new_invoice_id));
                exit;
            }
        }
    } elseif ($action === 'edit_invoice' && $invoice_id > 0) {
        // Get user's requires_vat_invoice setting
        $users_table = $wpdb->prefix . 'im_users';
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT requires_vat_invoice FROM $users_table WHERE id = %d",
            $user_id
        ));
        $requires_vat_invoice = ($user && isset($user->requires_vat_invoice)) ? intval($user->requires_vat_invoice) : 0;

        // Calculate totals for update
        $sub_total = 0;
        $tax_amount = 0;

        foreach ($items as $item) {
            $item_total = floatval($item['item_total']);
            $vat_amount = floatval($item['vat_amount'] ?? 0);

            $sub_total += $item_total;
            // Only add VAT if requires_vat_invoice = 1
            if ($requires_vat_invoice) {
                $tax_amount += $vat_amount;
            }
        }

        // Use final_discount_total (with auto discount) for edit as well
        $total_amount = $sub_total + $tax_amount - $final_discount_total;

        // Update existing invoice
        $wpdb->update($invoices_table, [
            'user_id' => $user_id,
            'invoice_date' => $formatted_invoice_date,
            'due_date' => $formatted_due_date,
            'sub_total' => $sub_total,
            'discount_total' => $final_discount_total,
            'tax_amount' => $tax_amount,
            'total_amount' => $total_amount,
            'notes' => $notes,
            'status' => isset($_POST['finalize']) && $_POST['finalize'] == 1 ? 'pending' : 'draft',
            'requires_vat_invoice' => $requires_vat_invoice,
        ], ['id' => $invoice_id]);

        // Update invoice items
        $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
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
                'vat_rate' => $item['vat_rate'],
                'vat_amount' => $item['vat_amount'],
            ];

            if (isset($item['start_date']) && !empty($item['start_date'])) {
                $insert_data['start_date'] = $item['start_date'];
            }

            if (isset($item['end_date']) && !empty($item['end_date'])) {
                $insert_data['end_date'] = $item['end_date'];
            }

            $wpdb->insert($invoice_items_table, $insert_data);
        }

        // Delete existing commissions and recreate from discount data
        $commissions_table = $wpdb->prefix . 'im_partner_commissions';
        $wpdb->delete($commissions_table, ['invoice_id' => $invoice_id]);

        // Create new commissions for items with partner_id
        if (!empty($commission_data)) {
            create_commissions_from_discount_data($invoice_id, $commission_data);
        }

        echo '<div class="alert alert-success">Hóa đơn đã được cập nhật thành công!</div>';

        if (isset($_POST['finalize']) && $_POST['finalize'] == 1) {
            wp_redirect(home_url('/chi-tiet-hoa-don/?invoice_id=' . $invoice_id));
            exit;
        } elseif (isset($_POST['save_finalized']) && $_POST['save_finalized'] == 1) {
            $wpdb->update($invoices_table, ['status' => 'pending'], ['id' => $invoice_id]);
            wp_redirect(home_url('/chi-tiet-hoa-don/?invoice_id=' . $invoice_id));
            exit;
        }
    }
}

function process_invoice_items()
{
    $items = [];

    if (!isset($_POST['service_type'])) {
        return $items;
    }

    foreach ($_POST['service_type'] as $index => $service_type) {
        $service_type = sanitize_text_field($service_type);
        $service_id = intval($_POST['service_id'][$index]);

        // Validate service_id is set
        if (empty($service_id)) {
            continue;
        }

        $item_total = floatval($_POST['item_total'][$index]);

        // Get VAT rate from form submission if available, otherwise from service type
        if (isset($_POST['vat_rate'][$index]) && !empty($_POST['vat_rate'][$index])) {
            $vat_rate = floatval($_POST['vat_rate'][$index]);
        } else {
            $vat_rate = get_vat_rate_for_service($service_type);
        }

        $vat_amount = calculate_vat_amount($item_total, $vat_rate);

        $item = [
            'service_type' => $service_type,
            'service_id' => $service_id,
            'description' => sanitize_text_field($_POST['description'][$index]),
            'unit_price' => floatval($_POST['unit_price'][$index]),
            'quantity' => intval($_POST['quantity'][$index]),
            'item_total' => $item_total,
            'vat_rate' => $vat_rate,
            'vat_amount' => $vat_amount,
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

    return $items;
}

function process_discount_and_commission($items)
{
    global $wpdb;

    $invoice_discount = 0;
    $commission_data = []; // For creating commissions later

    $hostings_table = $wpdb->prefix . 'im_hostings';
    $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';

    foreach ($items as &$item) {
        $service_type = $item['service_type'];
        $service_id = $item['service_id'];
        $discount_amount = 0;
        $partner_id = 0;

        // Get discount_amount and partner_id from database
        if ($service_type === 'hosting') {
            $service = $wpdb->get_row($wpdb->prepare(
                "SELECT discount_amount, partner_id FROM $hostings_table WHERE id = %d",
                $service_id
            ));
        } elseif ($service_type === 'maintenance') {
            $service = $wpdb->get_row($wpdb->prepare(
                "SELECT discount_amount, partner_id FROM $maintenance_table WHERE id = %d",
                $service_id
            ));
        } else {
            $service = null;
        }

        if ($service) {
            $discount_amount = floatval($service->discount_amount ?? 0);
            $partner_id = intval($service->partner_id ?? 0);

            if ($discount_amount > 0) {
                if ($partner_id > 0) {
                    // Has partner_id: Record for commission (don't subtract from invoice)
                    $commission_data[] = [
                        'service_type' => $service_type,
                        'service_id' => $service_id,
                        'partner_id' => $partner_id,
                        'amount' => $discount_amount,
                        'item_index' => array_key_last($items) // Track which item this relates to
                    ];
                } else {
                    // No partner_id: Subtract from invoice discount
                    $invoice_discount += $discount_amount;
                }
            }
        }

        // Store discount info in item for reference
        $item['discount_amount'] = $discount_amount;
        $item['partner_id'] = $partner_id;
    }

    return [
        'items' => $items,
        'invoice_discount' => $invoice_discount,
        'commission_data' => $commission_data
    ];
}

function load_invoice_data(&$invoice_id, &$user_id, $from_cart, &$bulk_domains, &$bulk_hostings, &$bulk_maintenances, &$bulk_websites, $domain_id, $hosting_id, $maintenance_id, $website_id)
{
    global $wpdb, $renewal_products, $invoice_items, $product_data, $website_data, $existing_invoice, $partner_info, $users, $service_type;

    $users_table = $wpdb->prefix . 'im_users';

    // Load existing invoice if editing
    if ($invoice_id > 0) {
        $existing_invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}im_invoices WHERE id = %d",
            $invoice_id
        ));

        if ($existing_invoice) {
            $user_id = $existing_invoice->user_id;
            load_existing_invoice_items($invoice_id);
        }
    }

    // Load cart items if from_cart
    if ($from_cart && $user_id > 0) {
        load_cart_items($user_id, $bulk_domains, $bulk_hostings, $bulk_maintenances, $bulk_websites);
    }

    // Convert single items to bulk format for unified processing
    if ($domain_id > 0) {
        $bulk_domains = (string) $domain_id;
    }
    if ($hosting_id > 0) {
        $bulk_hostings = (string) $hosting_id;
    }
    if ($maintenance_id > 0) {
        $bulk_maintenances = (string) $maintenance_id;
    }
    if ($website_id > 0) {
        $bulk_websites = (string) $website_id;
    }

    // Process bulk items
    load_bulk_items($bulk_domains, $bulk_hostings, $bulk_maintenances, $bulk_websites, $user_id, $service_type);

    // Load users list
    $users = $wpdb->get_results("SELECT id, name, user_code, email FROM $users_table ORDER BY name");

    // Get partner info
    if (!empty($renewal_products)) {
        $partner_id = get_partner_id_from_items(array_map(function ($p) {
            return [
                'service_type' => $p['type'],
                'service_id' => $p['id']
            ];
        }, $renewal_products));

        if ($partner_id) {
            $partner_info = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, user_code, email FROM $users_table WHERE id = %d",
                $partner_id
            ));
        }
    }

    // Calculate totals
    calculate_invoice_totals($invoice_items, $renewal_products);
}

function load_existing_invoice_items($invoice_id)
{
    global $wpdb, $invoice_items;

    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';

    $invoice_items = $wpdb->get_results($wpdb->prepare("
        SELECT
            ii.*,
            CASE
                WHEN ii.service_type = 'domain' THEN (SELECT domain_name FROM {$wpdb->prefix}im_domains WHERE id = ii.service_id)
                WHEN ii.service_type = 'hosting' THEN (SELECT hosting_code FROM {$wpdb->prefix}im_hostings WHERE id = ii.service_id)
                WHEN ii.service_type = 'maintenance' THEN (SELECT order_code FROM {$wpdb->prefix}im_maintenance_packages WHERE id = ii.service_id)
                WHEN ii.service_type = 'website_service' THEN (SELECT title FROM {$wpdb->prefix}im_website_services WHERE id = ii.service_id)
                ELSE ''
            END AS service_name
        FROM {$invoice_items_table} ii
        WHERE ii.invoice_id = %d
    ", $invoice_id));
}

function load_cart_items($user_id, &$bulk_domains, &$bulk_hostings, &$bulk_maintenances, &$bulk_websites)
{
    global $wpdb;

    $cart_table = $wpdb->prefix . 'im_cart';
    $cart_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $cart_table WHERE user_id = %d ORDER BY added_at",
        $user_id
    ));

    foreach ($cart_items as $cart_item) {
        switch ($cart_item->service_type) {
            case 'domain':
                $bulk_domains .= ($bulk_domains ? ',' : '') . $cart_item->service_id;
                break;
            case 'hosting':
                $bulk_hostings .= ($bulk_hostings ? ',' : '') . $cart_item->service_id;
                break;
            case 'maintenance':
                $bulk_maintenances .= ($bulk_maintenances ? ',' : '') . $cart_item->service_id;
                break;
            case 'website_service':
                $bulk_websites .= ($bulk_websites ? ',' : '') . $cart_item->service_id;
                break;
        }
    }
}

function load_bulk_items(&$bulk_domains, &$bulk_hostings, &$bulk_maintenances, &$bulk_websites, &$user_id, &$service_type)
{
    global $wpdb, $renewal_products;

    $domains_table = $wpdb->prefix . 'im_domains';
    $hostings_table = $wpdb->prefix . 'im_hostings';
    $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
    $websites_table = $wpdb->prefix . 'im_websites';

    // Process bulk domains
    if (!empty($bulk_domains)) {
        $domains_data = get_bulk_items_data('domain', $bulk_domains, $domains_table);
        if (!empty($domains_data)) {
            $service_type = count(explode(',', $bulk_domains)) > 1 ? 'bulk_domains' : 'domain';
            if (empty($user_id)) {
                $user_id = $domains_data[0]->owner_user_id;
            }
            build_renewal_products($domains_data, 'domain');
        }
    }

    // Process bulk hostings
    if (!empty($bulk_hostings)) {
        $hostings_data = get_bulk_items_data('hosting', $bulk_hostings, $hostings_table);
        if (!empty($hostings_data)) {
            $service_type = count(explode(',', $bulk_hostings)) > 1 ? 'bulk_hostings' : 'hosting';
            if (empty($user_id)) {
                $user_id = $hostings_data[0]->owner_user_id;
            }
            build_renewal_products($hostings_data, 'hosting');
        }
    }

    // Process bulk maintenances
    if (!empty($bulk_maintenances)) {
        $maintenances_data = get_bulk_items_data('maintenance', $bulk_maintenances, $maintenance_table);
        if (!empty($maintenances_data)) {
            $service_type = count(explode(',', $bulk_maintenances)) > 1 ? 'bulk_maintenances' : 'maintenance';
            if (empty($user_id)) {
                $user_id = $maintenances_data[0]->owner_user_id;
            }
            build_renewal_products($maintenances_data, 'maintenance');
        }
    }

    // Process bulk website services
    if (!empty($bulk_websites)) {
        $website_services_table = $wpdb->prefix . 'im_website_services';
        $website_services_data = get_bulk_items_data('website_service', $bulk_websites, $website_services_table);
        if (!empty($website_services_data)) {
            $service_type = count(explode(',', $bulk_websites)) > 1 ? 'bulk_websites' : 'website_service';
            // Get user_id from website owner if not set
            if (empty($user_id) && !empty($website_services_data)) {
                // Get website owner from first service
                $first_service = $website_services_data[0];
                if (isset($first_service->website_id)) {
                    $website_owner = $wpdb->get_row($wpdb->prepare(
                        "SELECT owner_user_id FROM $websites_table WHERE id = %d",
                        $first_service->website_id
                    ));
                    if ($website_owner) {
                        $user_id = $website_owner->owner_user_id;
                    }
                }
            }
            build_renewal_products($website_services_data, 'website_service');
        }
    }

}

function get_bulk_items_data($type, $ids_string, $table_name)
{
    global $wpdb;

    if (empty($ids_string)) {
        return [];
    }

    $item_ids = array_map('intval', explode(',', $ids_string));
    $item_ids = array_filter($item_ids);

    if (empty($item_ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($item_ids), '%d'));

    // Special handling for website_service
    if ($type === 'website_service') {
        $website_services_table = $wpdb->prefix . 'im_website_services';
        $websites_table = $wpdb->prefix . 'im_websites';
        $query = "
            SELECT 
                ws.*,
                w.name AS website_name,
                w.owner_user_id,
                u.id AS owner_id,
                u.name AS owner_name,
                u.email AS owner_email
            FROM {$website_services_table} ws
            LEFT JOIN {$websites_table} w ON ws.website_id = w.id
            LEFT JOIN {$wpdb->prefix}im_users u ON w.owner_user_id = u.id
            WHERE ws.id IN ($placeholders)
        ";
    } else {
        $query = "
            SELECT s.*, u.id AS owner_id, u.name AS owner_name, u.email AS owner_email
            FROM $table_name s
            LEFT JOIN {$wpdb->prefix}im_users u ON s.owner_user_id = u.id
            WHERE s.id IN ($placeholders)
        ";
    }

    return $wpdb->get_results($wpdb->prepare($query, ...$item_ids));
}

function build_renewal_products($items_data, $type)
{
    global $wpdb, $renewal_products;

    $products_table = $wpdb->prefix . 'im_product_catalog';
    $websites_table = $wpdb->prefix . 'im_websites';

    foreach ($items_data as $item) {
        // Get product name and renew price from product catalog
        $product_info = null;
        $product_name = '';
        $price = 0;

        if (!empty($item->product_catalog_id)) {
             $product_info = $wpdb->get_row($wpdb->prepare(
                 "SELECT name, renew_price, register_price FROM $products_table WHERE id = %d",
                 $item->product_catalog_id
             ));

             if ($product_info) {
                 $product_name = $product_info->name;
                 // For domains: use renew_price if ACTIVE, register_price if PENDING
                 if ($type === 'domain' && isset($item->status)) {
                     $price = floatval(($item->status === 'ACTIVE') ? $product_info->renew_price : $product_info->register_price);
                 } else {
                     $price = floatval($product_info->renew_price);
                 }
             }
         }

        // Fallback to item price if product_catalog price not available
        if (empty($price)) {
            if ($type === 'domain' && isset($item->price)) {
                $price = floatval($item->price);
            } elseif ($type === 'hosting' && isset($item->price)) {
                $price = floatval($item->price);
            } elseif ($type === 'maintenance' && isset($item->price_per_cycle)) {
                $price = floatval($item->price_per_cycle);
            } elseif ($type === 'website_service') {
                // For website services, use fixed_price if available
                if (isset($item->fixed_price) && $item->fixed_price > 0) {
                    $price = floatval($item->fixed_price);
                } elseif (isset($item->estimated_manday) && isset($item->daily_rate) && $item->daily_rate > 0) {
                    // Calculate price from mandays * daily rate
                    $price = floatval($item->estimated_manday) * floatval($item->daily_rate);
                }
            }
        }

        $website_name = null;
        if (in_array($type, ['hosting', 'maintenance'])) {
            $website_name = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM $websites_table WHERE " . ($type === 'hosting' ? 'hosting_id' : 'maintenance_package_id') . " = %d LIMIT 1",
                $item->id
            ));
        } elseif ($type === 'website_service' && isset($item->website_name)) {
            // Website name is already loaded from the query join
            $website_name = $item->website_name;
        }

        $period = $type === 'domain' ? $item->registration_period_years : ($type === 'hosting' || $type === 'maintenance' ? $item->billing_cycle_months : 0);
        $expiry_date = $item->expiry_date ?? date('Y-m-d');

        // Get service name based on type
        if ($type === 'domain') {
            $service_name = $item->domain_name;
        } elseif ($type === 'hosting') {
            $service_name = $item->hosting_code;
        } elseif ($type === 'maintenance') {
            $service_name = $item->order_code;
        } else { // website_service
            $service_name = $item->title ?? $item->service_code;
        }

        // Determine start_date and end_date based on status
        if (isset($item->status) && $item->status === 'NEW') {
            $start_date = $item->registration_date ?? $expiry_date;
            $end_date = $expiry_date;
        } else {
            $start_date = $expiry_date;
            $end_date = calculate_end_date($expiry_date, $period, $type === 'domain' ? 'years' : 'months');
        }

        $renewal_products[] = [
            'type' => $type,
            'id' => $item->id,
            'name' => $service_name,
            'product_name' => $product_name,
            'price' => $price,
            'quantity' => 1,
            'period' => $period,
            'period_type' => $type === 'domain' ? 'years' : 'months',
            'expiry_date' => $expiry_date,
            'website_name' => $website_name,
            'description' => build_service_description($type, $service_name, $product_name, $period),
            'start_date' => $start_date,
            'end_date' => $end_date,
            'discount_amount' => floatval($item->discount_amount ?? 0),
            'partner_id' => intval($item->partner_id ?? 0)
        ];
    }
}

function build_service_description($type, $nameOrItem, $product_name = '', $period = 1)
{
    $service_name = is_string($nameOrItem) ? $nameOrItem : (isset($nameOrItem->domain_name) ? $nameOrItem->domain_name : '');

    switch ($type) {
        case 'domain':
            return 'Gia hạn tên miền ' . $service_name . ' - ' . intval($period) . ' năm';
        case 'hosting':
            return 'Gói hosting ' . $product_name . ' - ' . round($period / 12, 2) . ' năm';
        case 'maintenance':
            return 'Bảo trì, bảo dưỡng, chăm sóc website ' . $product_name . ' - ' . round($period / 12, 2) . ' năm';
        case 'website_service':
            return 'Dịch vụ website: ' . $service_name;
        default:
            return '';
    }
}

function calculate_end_date($expiry_date, $period, $unit)
{
    if ($unit === 'years') {
        return date('Y-m-d', strtotime($expiry_date . ' + ' . $period . ' years'));
    } else {
        return date('Y-m-d', strtotime($expiry_date . ' + ' . $period . ' months'));
    }
}

function calculate_invoice_totals(&$invoice_items, &$renewal_products)
{
    global $sub_total, $discount_total, $existing_invoice;

    $sub_total = 0;
    $discount_total = 0;

    if (!empty($invoice_items)) {
        foreach ($invoice_items as $item) {
            $sub_total += $item->item_total;
        }
        if ($existing_invoice) {
            $discount_total = floatval($existing_invoice->discount_total ?? 0);
        }
    } else {
        foreach ($renewal_products as $product) {
            $quantity = isset($product['quantity']) ? $product['quantity'] : 1;
            $item_total = $product['price'] * $quantity;
            $sub_total += $item_total;
        }
    }
}

function display_invoice_info_card()
{
    global $invoice_id, $existing_invoice, $product_data, $website_id, $user_id, $users, $partner_info, $service_type, $bulk_domains, $bulk_hostings, $bulk_maintenances, $bulk_websites;
    ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Thông tin hóa đơn</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Khách hàng <span class="text-danger">*</span></label>
                            <select name="user_id" id="user_id" class="form-select" required <?php echo ($existing_invoice || $product_data || $website_id > 0 || in_array($service_type, ['bulk_domains', 'bulk_hostings', 'bulk_maintenances', 'bulk_websites'])) ? 'disabled' : ''; ?>>
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
                            <input type="text" class="form-control" name="invoice_code_display" value="<?php echo $existing_invoice ? esc_attr($existing_invoice->invoice_code) : '(sẽ được tạo tự động)'; ?>" readonly>
                        </div>
                    </div>
            
                    <?php if ($partner_info): ?>
                            <div class="alert alert-info">
                                <strong>Đối tác liên quan:</strong> <?php echo esc_html($partner_info->user_code . ' - ' . $partner_info->name); ?>
                            </div>
                    <?php endif; ?>
            
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ngày xuất hóa đơn <span class="text-danger">*</span></label>
                            <input type="text" class="form-control datepicker" name="invoice_date" value="<?php echo $existing_invoice ? date('d/m/Y', strtotime($existing_invoice->invoice_date)) : date('d/m/Y'); ?>" required>
                        </div>
                
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hạn thanh toán <span class="text-danger">*</span></label>
                            <input type="text" class="form-control datepicker" name="due_date" value="<?php echo $existing_invoice ? date('d/m/Y', strtotime($existing_invoice->due_date)) : date('d/m/Y', strtotime('+7 days')); ?>" required>
                        </div>
                    </div>
            
                    <div class="mb-3">
                        <label class="form-label">Ghi chú</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo $existing_invoice ? esc_textarea($existing_invoice->notes) : ''; ?></textarea>
                    </div>
                </div>
            </div>
            <?php
}

function display_invoice_items_card()
{
    global $invoice_items, $renewal_products;
    ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Chi tiết hóa đơn</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="invoice-items-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%">Dịch vụ</th>
                                    <th style="width: 25%">Mô tả</th>
                                    <th style="width: 12%">Đơn giá</th>
                                    <th style="width: 8%">SL</th>
                                    <th style="width: 12%">Thành tiền</th>
                                    <th style="width: 12%">VAT</th>
                                    <th style="width: 6%">Xóa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php render_invoice_items_rows($invoice_items, $renewal_products); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
}

function render_invoice_items_rows($invoice_items, $renewal_products)
{
    // Render existing items
    if (!empty($invoice_items)) {
        foreach ($invoice_items as $item) {
            render_invoice_item_row($item);
        }
    }

    // Render renewal products
    if (!empty($renewal_products)) {
        foreach ($renewal_products as $product) {
            render_renewal_product_row($product);
        }
    }
}

function render_invoice_item_row($item)
{
    $vat_rate = isset($item->vat_rate) ? $item->vat_rate : get_vat_rate_for_service($item->service_type);
    $vat_amount = isset($item->vat_amount) ? $item->vat_amount : calculate_vat_amount($item->item_total, $vat_rate);
    $service_type_label = format_service_type($item->service_type);
    ?>
            <tr class="invoice-item">
                <td>
                    <input type="hidden" name="item_id[]" value="<?php echo $item->id; ?>">
                    <input type="hidden" name="service_type[]" value="<?php echo $item->service_type; ?>">
                    <input type="hidden" name="service_id[]" value="<?php echo $item->service_id; ?>">
                    <input type="hidden" name="vat_rate[]" value="<?php echo $vat_rate; ?>">
                    <strong><?php echo $service_type_label; ?>:</strong> <?php echo esc_html($item->service_name); ?>
                </td>
                <td><input type="text" class="form-control" name="description[]" value="<?php echo esc_attr($item->description); ?>" required></td>
                <td><input type="text" class="form-control unit-price" name="unit_price[]" value="<?php echo number_format($item->unit_price, 0, '', ''); ?>" required></td>
                <td><input type="number" class="form-control quantity" name="quantity[]" value="<?php echo $item->quantity; ?>" min="1" required></td>
                <td><input type="text" class="form-control item-total" name="item_total[]" value="<?php echo number_format($item->item_total, 0, '', ''); ?>" readonly></td>
                <td>
                    <div class="item-vat-display">
                        <?php if ($vat_amount > 0): ?>
                                <span class="vat-amount"><?php echo number_format($vat_amount, 0, ',', '.'); ?> VNĐ</span>
                                <br><small class="text-muted vat-rate">(<?php echo number_format($vat_rate, 1); ?>%)</small>
                        <?php else: ?>
                                <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td><button type="button" class="btn btn-danger btn-sm remove-item"><i class="ph ph-trash"></i></button></td>
            </tr>
            <?php
}

function render_renewal_product_row($product)
{
    $item_quantity = $product['quantity'] ?? 1;
    $item_total = $product['price'] * $item_quantity;
    $product_type_capitalized = ucfirst($product['type']);
    $product_vat_rate = get_vat_rate_for_service($product_type_capitalized);
    $product_vat_amount = calculate_vat_amount($item_total, $product_vat_rate);
    $start_date = $product['start_date'] ?? $product['expiry_date'];
    $end_date = $product['end_date'] ?? calculate_end_date($product['expiry_date'], $product['period'], $product['period_type'] === 'years' ? 'years' : 'months');
    $service_type_label = format_service_type($product['type']);
    ?>
            <tr class="invoice-item">
                <td>
                    <input type="hidden" name="item_id[]" value="0">
                    <input type="hidden" name="service_type[]" value="<?php echo $product['type']; ?>">
                    <input type="hidden" name="service_id[]" value="<?php echo $product['id']; ?>">
                    <input type="hidden" name="start_date[]" value="<?php echo $start_date; ?>">
                    <input type="hidden" name="end_date[]" value="<?php echo $end_date; ?>">
                    <input type="hidden" name="vat_rate[]" value="<?php echo $product_vat_rate; ?>">
                    <strong><?php echo $service_type_label; ?>:</strong> <?php echo esc_html($product['name']); ?>
                    <?php if (isset($product['website_name']) && !empty($product['website_name'])): ?>
                            <br><small class="text-muted"><i class="ph ph-globe-hemisphere-west"></i> <?php echo esc_html($product['website_name']); ?></small>
                    <?php endif; ?>
                </td>
                <td><input type="text" class="form-control" name="description[]" value="<?php echo esc_attr($product['description']); ?>" required></td>
                <td><input type="text" class="form-control unit-price" name="unit_price[]" value="<?php echo number_format($product['price'], 0, '', ''); ?>" required></td>
                <td><input type="number" class="form-control quantity" name="quantity[]" value="<?php echo $item_quantity; ?>" min="1" required></td>
                <td><input type="text" class="form-control item-total" name="item_total[]" value="<?php echo number_format($item_total, 0, '', ''); ?>" readonly></td>
                <td>
                    <div class="item-vat-display">
                        <?php if ($product_vat_amount > 0): ?>
                                <span class="vat-amount"><?php echo number_format($product_vat_amount, 0, ',', '.'); ?> VNĐ</span>
                                <br><small class="text-muted vat-rate">(<?php echo number_format($product_vat_rate, 1); ?>%)</small>
                        <?php else: ?>
                                <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td><button type="button" class="btn btn-danger btn-sm remove-item"><i class="ph ph-trash"></i></button></td>
            </tr>
            <?php
}

function display_invoice_summary_card()
{
    global $sub_total, $existing_invoice;
    ?>
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
                        <div>
                            <span>Chiết khấu:</span>
                            <small class="text-muted d-block" style="font-size: 11px;">
                                <i>Tự động: từ sản phẩm không có đối tác<br/>
                                + Nhập thêm chiết khấu khác</i>
                            </small>
                        </div>
                        <input type="number" class="form-control" style="width: 140px;" name="discount_total" id="discount-amount" value="0" min="0">
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Thuế (VAT):</span>
                        <input type="number" class="form-control" style="width: 140px;" name="tax_amount" id="tax-amount" value="0" min="0">
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3 fw-bold">
                        <span>Thành tiền:</span>
                        <span id="summary-total"><?php echo number_format($sub_total, 0, ',', '.'); ?> VNĐ</span>
                    </div>
                    <input type="hidden" name="sub_total" id="sub-total-input" value="<?php echo $sub_total; ?>">
                    <input type="hidden" name="total_amount" id="total-amount-input" value="<?php echo $sub_total; ?>">
                </div>
            </div>
    
            <div class="card-footer bg-white text-end pt-3">
                <button type="submit" name="save_draft" class="btn btn-secondary me-2">
                    <i class="ph ph-floppy-disk me-1"></i> Lưu nháp
                </button>
                <button type="submit" name="finalize" class="btn btn-primary" value="1">
                    <i class="ph ph-check-circle me-1"></i> Hoàn tất hóa đơn
                </button>
            </div>
            <?php
}

function get_invoice_item_website_names($item)
{
    global $wpdb;

    $websites_table = $wpdb->prefix . 'im_websites';
    $service_table = $wpdb->prefix . 'im_website_services';
    $website_names = array();

    if ($item->service_type === 'hosting') {
        // Get websites linked to this hosting
        $website_infos = $wpdb->get_results($wpdb->prepare("
            SELECT name FROM $websites_table 
            WHERE hosting_id = %d
            ORDER BY name ASC
        ", $item->service_id));

        if (!empty($website_infos)) {
            foreach ($website_infos as $website_info) {
                $website_names[] = $website_info->name;
            }
        }
    } elseif ($item->service_type === 'maintenance') {
        // Get websites linked to this maintenance package
        $website_infos = $wpdb->get_results($wpdb->prepare("
            SELECT name FROM $websites_table 
            WHERE maintenance_package_id = %d
            ORDER BY name ASC
        ", $item->service_id));

        if (!empty($website_infos)) {
            foreach ($website_infos as $website_info) {
                $website_names[] = $website_info->name;
            }
        }
    } elseif ($item->service_type === 'website_service') {
        // Get website name from website_services table
        $ws_info = $wpdb->get_row($wpdb->prepare("
            SELECT w.name FROM {$service_table} ws
            LEFT JOIN $websites_table w ON ws.website_id = w.id
            WHERE ws.id = %d
        ", $item->service_id));

        if ($ws_info && !empty($ws_info->name)) {
            $website_names[] = $ws_info->name;
        }
    }

    return $website_names;
}

function get_service_options($type, $user_id)
{
    global $wpdb;

    if (!$user_id) {
        return '';
    }

    $html = '';

    switch ($type) {
        case 'domain':
            $domains = $wpdb->get_results($wpdb->prepare("
                SELECT d.id, d.domain_name, d.registration_period_years, d.expiry_date, d.price as base_price, pc.renew_price AS price
                FROM {$wpdb->prefix}im_domains d
                LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON d.product_catalog_id = pc.id
                WHERE d.owner_user_id = %d
                ORDER BY d.domain_name
            ", $user_id));

            foreach ($domains as $domain) {
                // Use renew_price from product_catalog, fallback to base_price from domain
                $display_price = !empty($domain->price) ? $domain->price : ($domain->base_price ?? 0);
                $html .= '<option value="' . $domain->id . '"
                    data-price="' . floatval($display_price) . '"
                    data-expiry="' . $domain->expiry_date . '"
                    data-period="' . $domain->registration_period_years . '">
                    ' . esc_html($domain->domain_name) . ' - ' . number_format($display_price, 0, ',', '.') . ' VNĐ
                </option>';
            }
            break;

        case 'hosting':
            $hostings = $wpdb->get_results($wpdb->prepare("
                SELECT h.id, h.hosting_code, h.price as base_price, pc.renew_price as price, h.billing_cycle_months, h.expiry_date, pc.name as product_name
                FROM {$wpdb->prefix}im_hostings h
                LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON h.product_catalog_id = pc.id
                WHERE h.owner_user_id = %d
                ORDER BY h.hosting_code
            ", $user_id));

            foreach ($hostings as $hosting) {
                // Use renew_price from product_catalog, fallback to base_price from hosting
                $display_price = !empty($hosting->price) ? $hosting->price : ($hosting->base_price ?? 0);
                $html .= '<option value="' . $hosting->id . '" 
                    data-price="' . floatval($display_price) . '"
                    data-expiry="' . $hosting->expiry_date . '"
                    data-period="' . $hosting->billing_cycle_months . '"
                    data-name="' . esc_attr($hosting->product_name) . '">
                    ' . esc_html($hosting->hosting_code . ' - ' . ($hosting->product_name ?? '')) . ' - ' . number_format($display_price, 0, ',', '.') . ' VNĐ
                </option>';
            }
            break;

        case 'maintenance':
            $maintenances = $wpdb->get_results($wpdb->prepare("
                SELECT m.id, m.order_code, m.price_per_cycle, m.billing_cycle_months, m.expiry_date, pc.renew_price as pc_price, pc.name as product_name
                FROM {$wpdb->prefix}im_maintenance_packages m
                LEFT JOIN {$wpdb->prefix}im_product_catalog pc ON m.product_catalog_id = pc.id
                WHERE m.owner_user_id = %d
                ORDER BY m.order_code
            ", $user_id));

            foreach ($maintenances as $maintenance) {
                // Use renew_price from product_catalog, fallback to price_per_cycle from maintenance
                $display_price = !empty($maintenance->pc_price) ? $maintenance->pc_price : ($maintenance->price_per_cycle ?? 0);
                $html .= '<option value="' . $maintenance->id . '" 
                    data-price="' . floatval($display_price) . '"
                    data-expiry="' . $maintenance->expiry_date . '"
                    data-period="' . $maintenance->billing_cycle_months . '"
                    data-name="' . esc_attr($maintenance->product_name) . '">
                    ' . esc_html($maintenance->order_code . ' - ' . ($maintenance->product_name ?? '')) . ' - ' . number_format($display_price, 0, ',', '.') . ' VNĐ
                </option>';
            }
            break;
    }

    return $html;
}

function send_webhook_data($data, $event_type = 'generic')
{
    $webhook_url = get_option('inova_webhook_url', '');

    if (empty($webhook_url)) {
        error_log('Webhook URL not configured');
        return false;
    }

    // Prepare webhook payload
    $payload = array(
        'event_type' => $event_type,
        'timestamp' => current_time('c'),
        'data' => $data
    );

    // Send POST request to webhook
    $response = wp_remote_post($webhook_url, array(
        'method' => 'POST',
        'timeout' => 30,
        'httpversion' => '1.1',
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($payload),
    ));

    if (is_wp_error($response)) {
        error_log('Webhook send error: ' . $response->get_error_message());
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code >= 200 && $status_code < 300) {
        error_log('Webhook sent successfully. Event: ' . $event_type);
        return true;
    } else {
        error_log('Webhook error - Status: ' . $status_code . ', Response: ' . wp_remote_retrieve_body($response));
        return false;
    }
}

function prepare_invoice_webhook_data($invoice_id, $customer_id)
{
    global $wpdb;

    $invoices_table = $wpdb->prefix . 'im_invoices';
    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
    $users_table = $wpdb->prefix . 'im_users';

    // Get invoice details
    $invoice = $wpdb->get_row($wpdb->prepare("
        SELECT *
        FROM {$invoices_table}
        WHERE id = %d
    ", $invoice_id));

    if (!$invoice) {
        return array();
    }

    // Get customer info
    $customer = $wpdb->get_row($wpdb->prepare("
        SELECT id, name, user_code
        FROM {$users_table}
        WHERE id = %d
    ", $customer_id));

    // Get invoice items
    $items = $wpdb->get_results($wpdb->prepare("
        SELECT service_type, description, unit_price, quantity, item_total, vat_amount
        FROM {$invoice_items_table}
        WHERE invoice_id = %d
    ", $invoice_id));

    // Generate public invoice link (token only - invoice_id is encoded in token)
    $public_invoice_link = home_url('/public-invoice/?token=' . urlencode(base64_encode($invoice->id . '|' . $invoice->created_at)));

    return array(
        'invoice_id' => intval($invoice->id),
        'invoice_link' => $public_invoice_link,
        'customer_id' => intval($customer->id),
        'customer_name' => $customer->name,
        'customer_code' => $customer->user_code,
        'total_amount' => floatval($invoice->total_amount),
        'tax_amount' => floatval($invoice->tax_amount),
        'amount_due' => floatval($invoice->amount_due),
        'status' => $invoice->status,
        'invoice_date' => $invoice->created_at,
        'items' => array_map(function ($item) {
            return array(
                'service_type' => $item->service_type,
                'description' => $item->description,
                'unit_price' => floatval($item->unit_price),
                'quantity' => intval($item->quantity),
                'item_total' => floatval($item->item_total),
                'vat_amount' => floatval($item->vat_amount)
            );
        }, $items)
    );
}

function trigger_invoice_creation_webhook($invoice_id, $customer_id)
{
    // Check if webhook is enabled for renewal invoices
    if (!get_option('inova_webhook_enabled_renewal', 1)) {
        return;
    }

    $invoice_data = prepare_invoice_webhook_data($invoice_id, $customer_id);

    if (!empty($invoice_data)) {
        send_webhook_data($invoice_data, 'renewal_invoice');
    }
}

function prepare_expiry_webhook_data($expiring_domains, $expiring_hostings, $expiring_maintenance)
{
    $services_expiring = array(
        'domains' => array_map(function ($domain) {
            return array(
                'id' => intval($domain->id),
                'domain_name' => $domain->name,
                'owner_id' => intval($domain->owner_user_id),
                'owner_name' => $domain->owner_name,
                'expiry_date' => $domain->expiry_date,
                'days_until_expiry' => intval((strtotime($domain->expiry_date) - time()) / 86400),
                'renewal_price' => floatval($domain->price ?? 0),
                'status' => $domain->status
            );
        }, $expiring_domains),
        'hostings' => array_map(function ($hosting) {
            return array(
                'id' => intval($hosting->id),
                'hosting_code' => $hosting->hosting_code,
                'owner_id' => intval($hosting->owner_user_id),
                'owner_name' => $hosting->owner_name,
                'expiry_date' => $hosting->expiry_date,
                'days_until_expiry' => intval((strtotime($hosting->expiry_date) - time()) / 86400),
                'renewal_price' => floatval($hosting->price ?? 0),
                'websites' => !empty($hosting->website_names) ? explode(', ', $hosting->website_names) : [],
                'status' => $hosting->status
            );
        }, $expiring_hostings),
        'maintenances' => array_map(function ($maintenance) {
            return array(
                'id' => intval($maintenance->id),
                'order_code' => $maintenance->order_code,
                'owner_id' => intval($maintenance->owner_user_id),
                'owner_name' => $maintenance->owner_name,
                'expiry_date' => $maintenance->expiry_date,
                'days_until_expiry' => intval((strtotime($maintenance->expiry_date) - time()) / 86400),
                'price_per_cycle' => floatval($maintenance->price ?? 0),
                'websites' => !empty($maintenance->website_names) ? explode(', ', $maintenance->website_names) : [],
                'status' => $maintenance->status
            );
        }, $expiring_maintenance)
    );

    return $services_expiring;
}

function trigger_expiry_check_webhook($expiring_domains, $expiring_hostings, $expiring_maintenance)
{
    // Check if webhook is enabled for expiry check
    if (!get_option('inova_webhook_enabled_expiry', 1)) {
        return;
    }

    $expiry_data = prepare_expiry_webhook_data($expiring_domains, $expiring_hostings, $expiring_maintenance);

    if (!empty(array_filter($expiry_data))) {
        send_webhook_data($expiry_data, 'expiry_check');
    }
}


/* ===== REGISTER HOOKS FOR DATABASE FUNCTIONS ===== */

// Cron job scheduling
add_filter('cron_schedules', function ($schedules) {
    $schedules['daily'] = [
        'interval' => 86400,
        'display' => 'Once Daily'
    ];
    return $schedules;
});

add_action('after_switch_theme', 'schedule_expiry_check_cron');
add_action('init', 'schedule_expiry_check_cron');

// Scheduled email notifications
add_action('inovamanager_check_expiry_daily', 'check_and_send_expiry_emails');

// Auto renewal scheduling
add_action('wp', 'schedule_auto_renewal_invoices');
add_action('auto_create_renewal_invoices', 'auto_create_renewal_invoices_callback');
