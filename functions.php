<?php
/* import vendor autoload form root directory */

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
        'ajaxurl' => admin_url('admin-ajax.php')
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
        KEY `provider_id` (`provider_id`),
        KEY `product_catalog_id` (`product_catalog_id`)
    ) {$charsetCollate};";
    dbDelta($createTable);

    # 5. hostings table
    $hostingsTable = $wpdb->prefix . 'im_hostings';
    $createTable = "CREATE TABLE `{$hostingsTable}` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `owner_user_id` bigint(20) UNSIGNED NOT NULL,
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
        `domain_id` bigint(20) UNSIGNED NULL,
        `hosting_id` bigint(20) UNSIGNED NULL,
        `maintenance_package_id` bigint(20) UNSIGNED NULL,
        `admin_url` varchar(255) NULL,
        `admin_username` varchar(255) NULL,
        `admin_password` varchar(255) NULL,
        `status` enum('ACTIVE','DELETED') DEFAULT 'ACTIVE',
        `notes` text NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `owner_user_id` (`owner_user_id`),
        KEY `domain_id` (`domain_id`),
        KEY `hosting_id` (`hosting_id`),
        KEY `maintenance_package_id` (`maintenance_package_id`)
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

    # 11. website_services table - Quản lý yêu cầu chỉnh sửa có tính phí
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



/* Get website status - check if website is accessible
 * @param string $url: URL to check
 * @return bool: true if website is accessible, false otherwise
 */
function check_website_status($url) {
    // Add http:// if not present
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "http://" . $url;
    }

    // Initialize cURL
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 seconds timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 seconds connection timeout
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3); // Maximum number of redirects
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Don't verify SSL peer
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request, no body
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'); // User agent
    
    // Execute cURL request
    $response = curl_exec($ch);
    
    // Get HTTP status code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Close cURL
    curl_close($ch);
    
    // Check if request was successful (HTTP status codes 200-299)
    return ($response !== false && $http_code >= 200 && $http_code < 300);
}

add_action('wp_ajax_checkWebsiteStatus', 'checkWebsiteStatus');
add_action('wp_ajax_nopriv_checkWebsiteStatus', 'checkWebsiteStatus');
function checkWebsiteStatus() {
    if (isset($_POST['url'])) {
        $url = sanitize_text_field($_POST['url']);
        $status = check_website_status($url);
        echo json_encode(
            array(
                'status' => $status, 
                'url' => $url, 
                'message' => $status ? "Website $url đang hoạt động" : "Website $url không hoạt động",
                'class' => $status ? 'success' : 'danger'
            )
        );
    } else {
        echo json_encode(
            array(
                'status' => false, 
                'message' => 'URL not provided',
                'class' => 'danger'
            )
        );
    }
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
        'addnew-website' => 'addnew_website.php',
        'add-website' => 'addnew_website.php',
        'edit-website' => 'edit_website.php',
        'detail-website' => 'detail_website.php',
        'delete-website' => 'xoa-website.php',
        'xoa-website' => 'xoa-website.php',
        'restore-website' => 'khoi-phuc-website.php',
        'khoi-phuc-website' => 'khoi-phuc-website.php',
        'attach-product-to-website' => 'attach_product_to_website.php',
        
        // Domain Management
        'domain-list' => 'domain_list.php',
        'domains' => 'domain_list.php',
        'addnew-domain' => 'addnew_domain.php',
        'add-domain' => 'addnew_domain.php',
        'edit-domain' => 'edit_domain.php',
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
        
        // Contract Management
        'create-contract' => 'create_contract.php',
        'contract' => 'create_contract.php',
        
        // API & Settings
        'api' => 'api.php',
        'api-settings' => 'api-settings.php',
        'api-examples' => 'api-examples.php',
        
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