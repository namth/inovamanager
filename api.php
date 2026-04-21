<?php
/**
 * API functions for the BookOrder Theme
 * 
 * @package BookOrder
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Register REST API endpoints
 */
function register_bookorder_api_routes()
{
    // Register route for importing a single partner
    register_rest_route('bookorder/v1', '/import-partner', array(
        'methods' => 'POST',
        'callback' => 'import_partner_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for updating a partner
    register_rest_route('bookorder/v1', '/update-partner/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'update_partner_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for adding a contact for business customers
    register_rest_route('bookorder/v1', '/add-contact', array(
        'methods' => 'POST',
        'callback' => 'add_contact_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for searching partners
    register_rest_route('bookorder/v1', '/search-partners', array(
        'methods' => 'GET',
        'callback' => 'search_partners_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for getting website ID from domain name
    register_rest_route('bookorder/v1', '/get-website-id', array(
        'methods' => 'POST',
        'callback' => 'get_website_id_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for updating website active status
    register_rest_route('bookorder/v1', '/update-status', array(
        'methods' => 'POST',
        'callback' => 'update_website_status_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for getting services expiring soon or expired
    register_rest_route('bookorder/v1', '/services-expiring', array(
        'methods' => 'GET',
        'callback' => 'get_services_expiring_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for getting pending invoices
    register_rest_route('bookorder/v1', '/invoices-pending', array(
        'methods' => 'GET',
        'callback' => 'get_pending_invoices_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for updating invoice status
    register_rest_route('bookorder/v1', '/update-invoice-status', array(
        'methods' => 'POST',
        'callback' => 'update_invoice_status_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for getting domain management info
    register_rest_route('bookorder/v1', '/domain-management-info', array(
        'methods' => 'GET',
        'callback' => 'get_domain_management_info_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for getting website management info
    register_rest_route('bookorder/v1', '/website-management-info', array(
        'methods' => 'GET',
        'callback' => 'get_website_management_info_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for getting users managed by a partner
    register_rest_route('bookorder/v1', '/partner-managed-users', array(
        'methods' => 'GET',
        'callback' => 'get_partner_managed_users_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for checking remote website status
    register_rest_route('bookorder/v1', '/check-status', array(
        'methods' => 'POST',
        'callback' => 'check_website_status_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for updating domain management info
    register_rest_route('bookorder/v1', '/update-domain-management-info', array(
        'methods' => 'POST',
        'callback' => 'update_domain_management_info_api',
        'permission_callback' => 'validate_api_key'
    ));

    // Register route for updating website management info
    register_rest_route('bookorder/v1', '/update-website-management-info', array(
        'methods' => 'POST',
        'callback' => 'update_website_management_info_api',
        'permission_callback' => 'validate_api_key'
    ));
}
add_action('rest_api_init', 'register_bookorder_api_routes');

/**
 * API key validation function
 * 
 * @param WP_REST_Request $request The request object
 * @return bool True if valid API key, false otherwise
 */
function validate_api_key($request)
{
    // Get API key from request headers
    $api_key = $request->get_header('X-API-KEY');

    if (!$api_key) {
        return false;
    }

    // Define your API key or retrieve it from options
    $valid_api_key = get_option('bookorder_api_key', 'your-secure-api-key-here');

    return $api_key === $valid_api_key;
}

/**
 * API callback function for importing a single partner
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function import_partner_api($request)
{
    // Get parameters from request
    $params = $request->get_params();

    // Validate required fields
    $required_fields = ['user_code', 'user_type', 'name'];
    foreach ($required_fields as $field) {
        if (empty($params[$field])) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => "Missing required field: {$field}"
                ),
                400
            );
        }
    }

    // Validate user_type
    $valid_user_types = ['INDIVIDUAL', 'BUSINESS', 'PARTNER', 'SUPPLIER'];
    if (!in_array($params['user_type'], $valid_user_types)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "Invalid user_type - must be one of: " . implode(', ', $valid_user_types)
            ),
            400
        );
    }

    // Validate email if provided
    if (!empty($params['email']) && !is_email($params['email'])) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "Invalid email format"
            ),
            400
        );
    }

    global $wpdb;
    $users_table = $wpdb->prefix . 'im_users';

    // Check if user_code already exists
    $existing = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM $users_table WHERE user_code = %s", $params['user_code'])
    );

    if ($existing > 0) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "User code '{$params['user_code']}' already exists"
            ),
            400
        );
    }

    // Prepare data for insertion
    $insert_data = array(
        'user_code' => sanitize_text_field($params['user_code']),
        'user_type' => sanitize_text_field($params['user_type']),
        'name' => sanitize_text_field($params['name']),
        'email' => !empty($params['email']) ? sanitize_email($params['email']) : '',
        'phone_number' => !empty($params['phone_number']) ? sanitize_text_field($params['phone_number']) : '',
        'tax_code' => !empty($params['tax_code']) ? sanitize_text_field($params['tax_code']) : '',
        'address' => !empty($params['address']) ? sanitize_text_field($params['address']) : '',
        'notes' => !empty($params['notes']) ? sanitize_text_field($params['notes']) : '',
        'status' => 'ACTIVE'
    );

    // Insert into database
    $result = $wpdb->insert($users_table, $insert_data);

    if ($result) {
        $user_id = $wpdb->insert_id;
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => "Partner imported successfully",
                'user_id' => $user_id,
                'user_data' => $insert_data
            ),
            201
        );
    } else {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "Database error while inserting partner"
            ),
            500
        );
    }
}

/**
 * API callback function for updating partner information
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function update_partner_api($request)
{
    // Get parameters from request
    $params = $request->get_params();
    $user_id = $request->get_param('id');

    if (!$user_id) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "Missing user ID"
            ),
            400
        );
    }

    global $wpdb;
    $users_table = $wpdb->prefix . 'im_users';

    // Check if user exists
    $user = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $user_id)
    );

    if (!$user) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "User with ID {$user_id} not found"
            ),
            404
        );
    }

    // Validate user_type if provided
    if (!empty($params['user_type'])) {
        $valid_user_types = ['INDIVIDUAL', 'BUSINESS', 'PARTNER', 'SUPPLIER'];
        if (!in_array($params['user_type'], $valid_user_types)) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => "Invalid user_type - must be one of: " . implode(', ', $valid_user_types)
                ),
                400
            );
        }
    }

    // Validate email if provided
    if (!empty($params['email']) && !is_email($params['email'])) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "Invalid email format"
            ),
            400
        );
    }

    // Check if user_code is being updated and if it already exists
    if (!empty($params['user_code']) && $params['user_code'] !== $user->user_code) {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $users_table WHERE user_code = %s AND id != %d",
                $params['user_code'],
                $user_id
            )
        );

        if ($existing > 0) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => "User code '{$params['user_code']}' already exists"
                ),
                400
            );
        }
    }

    // Prepare data for update
    $update_data = array();

    // Only update fields that are provided
    if (!empty($params['user_code']))
        $update_data['user_code'] = sanitize_text_field($params['user_code']);
    if (!empty($params['user_type']))
        $update_data['user_type'] = sanitize_text_field($params['user_type']);
    if (!empty($params['name']))
        $update_data['name'] = sanitize_text_field($params['name']);
    if (isset($params['email']))
        $update_data['email'] = sanitize_email($params['email']);
    if (isset($params['phone_number']))
        $update_data['phone_number'] = sanitize_text_field($params['phone_number']);
    if (isset($params['tax_code']))
        $update_data['tax_code'] = sanitize_text_field($params['tax_code']);
    if (isset($params['address']))
        $update_data['address'] = sanitize_text_field($params['address']);
    if (isset($params['notes']))
        $update_data['notes'] = sanitize_text_field($params['notes']);
    if (isset($params['status']))
        $update_data['status'] = sanitize_text_field($params['status']);

    // If no data was provided to update
    if (empty($update_data)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "No data provided to update"
            ),
            400
        );
    }

    // Update the database
    $result = $wpdb->update(
        $users_table,
        $update_data,
        array('id' => $user_id)
    );

    if ($result !== false) {
        // Get the updated user data
        $updated_user = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $user_id),
            ARRAY_A
        );

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => "Partner updated successfully",
                'user_id' => $user_id,
                'user_data' => $updated_user
            ),
            200
        );
    } else {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "Database error while updating partner"
            ),
            500
        );
    }
}

/**
 * API callback function for adding a contact for business customers
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function add_contact_api($request)
{
    // Get parameters from request
    $params = $request->get_params();

    // Validate required fields
    $required_fields = ['user_id', 'full_name'];
    foreach ($required_fields as $field) {
        if (empty($params[$field])) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => "Missing required field: {$field}"
                ),
                400
            );
        }
    }

    // Validate email if provided
    if (!empty($params['email']) && !is_email($params['email'])) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "Invalid email format"
            ),
            400
        );
    }

    global $wpdb;
    $users_table = $wpdb->prefix . 'im_users';
    $contacts_table = $wpdb->prefix . 'im_contacts';

    // Check if user exists and is a business
    $user = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $params['user_id'])
    );

    if (!$user) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "User with ID {$params['user_id']} not found"
            ),
            404
        );
    }

    if ($user->user_type !== 'BUSINESS') {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "Contacts can only be added to BUSINESS user types"
            ),
            400
        );
    }

    // Handle is_primary flag
    $is_primary = isset($params['is_primary']) && $params['is_primary'] ? 1 : 0;

    // If this contact is marked as primary, update all other contacts to not be primary
    if ($is_primary) {
        $wpdb->query($wpdb->prepare(
            "UPDATE $contacts_table SET is_primary = 0 WHERE user_id = %d",
            $params['user_id']
        ));
    }

    // Prepare data for insertion
    $insert_data = array(
        'user_id' => intval($params['user_id']),
        'full_name' => sanitize_text_field($params['full_name']),
        'email' => !empty($params['email']) ? sanitize_email($params['email']) : '',
        'phone_number' => !empty($params['phone_number']) ? sanitize_text_field($params['phone_number']) : '',
        'position' => !empty($params['position']) ? sanitize_text_field($params['position']) : '',
        'is_primary' => $is_primary
    );

    // Insert into database
    $result = $wpdb->insert($contacts_table, $insert_data);

    if ($result) {
        $contact_id = $wpdb->insert_id;
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => "Contact added successfully",
                'contact_id' => $contact_id,
                'contact_data' => $insert_data
            ),
            201
        );
    } else {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "Database error while adding contact"
            ),
            500
        );
    }
}

/**
 * API callback function for searching partners
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function search_partners_api($request)
{
    // Get search parameters
    $keyword = $request->get_param('keyword');
    $user_type = $request->get_param('user_type');
    $status = $request->get_param('status');
    $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
    $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 10;

    // Calculate offset for pagination
    $offset = ($page - 1) * $per_page;

    global $wpdb;
    $users_table = $wpdb->prefix . 'im_users';

    // Start building the query
    $search_query = "SELECT * FROM $users_table WHERE 1=1";
    $count_query = "SELECT COUNT(*) FROM $users_table WHERE 1=1";
    $query_params = array();

    // Add keyword search - search across multiple columns
    if (!empty($keyword)) {
        $search_term = '%' . $wpdb->esc_like($keyword) . '%';
        $search_query .= " AND (name LIKE %s OR user_code LIKE %s OR email LIKE %s OR phone_number LIKE %s OR tax_code LIKE %s OR address LIKE %s)";
        $count_query .= " AND (name LIKE %s OR user_code LIKE %s OR email LIKE %s OR phone_number LIKE %s OR tax_code LIKE %s OR address LIKE %s)";
        array_push($query_params, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term);
    }

    // Filter by user_type if provided
    if (!empty($user_type)) {
        $valid_user_types = ['INDIVIDUAL', 'BUSINESS', 'PARTNER', 'SUPPLIER'];
        if (!in_array($user_type, $valid_user_types)) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => "Invalid user_type - must be one of: " . implode(', ', $valid_user_types)
                ),
                400
            );
        }

        $search_query .= " AND user_type = %s";
        $count_query .= " AND user_type = %s";
        $query_params[] = $user_type;
    }

    // Filter by status if provided
    if (!empty($status)) {
        $valid_statuses = ['ACTIVE', 'INACTIVE', 'DELETED'];
        if (!in_array($status, $valid_statuses)) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => "Invalid status - must be one of: " . implode(', ', $valid_statuses)
                ),
                400
            );
        }

        $search_query .= " AND status = %s";
        $count_query .= " AND status = %s";
        $query_params[] = $status;
    }

    // Get total results count
    $total_results = 0;
    if (!empty($query_params)) {
        $total_results = $wpdb->get_var($wpdb->prepare($count_query, $query_params));
    } else {
        $total_results = $wpdb->get_var($count_query);
    }

    // Add ordering and pagination
    $search_query .= " ORDER BY name ASC LIMIT %d OFFSET %d";
    $query_params[] = $per_page;
    $query_params[] = $offset;

    // Execute search
    $results = array();
    if (!empty($query_params)) {
        $results = $wpdb->get_results($wpdb->prepare($search_query, $query_params), ARRAY_A);
    } else {
        $results = $wpdb->get_results($search_query, ARRAY_A);
    }

    // Calculate pagination metadata
    $total_pages = ceil($total_results / $per_page);

    // Check if we have business users and need to include their contacts
    $include_contacts = $request->get_param('include_contacts');
    if ($include_contacts && !empty($results)) {
        $contacts_table = $wpdb->prefix . 'im_contacts';

        // Get business user IDs
        $business_ids = array();
        foreach ($results as $user) {
            if ($user['user_type'] === 'BUSINESS') {
                $business_ids[] = $user['id'];
            }
        }

        // Fetch contacts for business users
        if (!empty($business_ids)) {
            $placeholders = implode(',', array_fill(0, count($business_ids), '%d'));
            $contacts_query = "SELECT * FROM $contacts_table WHERE user_id IN ($placeholders)";
            $contacts = $wpdb->get_results($wpdb->prepare($contacts_query, $business_ids), ARRAY_A);

            // Organize contacts by user_id
            $contacts_by_user = array();
            foreach ($contacts as $contact) {
                $user_id = $contact['user_id'];
                if (!isset($contacts_by_user[$user_id])) {
                    $contacts_by_user[$user_id] = array();
                }
                $contacts_by_user[$user_id][] = $contact;
            }

            // Add contacts to results
            foreach ($results as &$user) {
                if ($user['user_type'] === 'BUSINESS' && isset($contacts_by_user[$user['id']])) {
                    $user['contacts'] = $contacts_by_user[$user['id']];
                }
            }
        }
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'total_results' => intval($total_results),
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page,
            'results' => $results
        ),
        200
    );
}

/**
 * Function to generate a new API key
 * 
 * @return string The generated API key
 */
function generate_bookorder_api_key()
{
    $api_key = wp_generate_password(32, false);
    update_option('bookorder_api_key', $api_key);
    return $api_key;
}

// OPTIONAL: Add this line to generate an initial API key when the file is first loaded
// If you want to generate a key through an admin interface instead, remove this line
// if (!get_option('bookorder_api_key')) {
//     generate_bookorder_api_key();
// }

/**
 * API callback function for getting website ID from domain name
 * Used by satellite websites to identify themselves during plugin setup
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function get_website_id_api($request)
{
    global $wpdb;

    // Get domain parameter
    $domain = isset($request['domain']) ? sanitize_text_field($request['domain']) : '';

    if (empty($domain)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Domain name is required'
            ),
            400
        );
    }

    // Clean domain (remove http://, https://, www., trailing slash)
    $domain = preg_replace('#^https?://(www\.)?#', '', $domain);
    $domain = rtrim($domain, '/');

    // Find website by matching domain/subdomain in name
    $websites_table = $wpdb->prefix . 'im_websites';

    // Search for website with name matching the domain/subdomain
    $query = $wpdb->prepare("
        SELECT w.id, w.name
        FROM {$websites_table} w
        WHERE w.name = %s
        AND (w.status IS NULL OR w.status != 'DELETED')
        LIMIT 1
    ", $domain);

    $website = $wpdb->get_row($query);

    if (!$website) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Website not found for domain: ' . $domain
            ),
            404
        );
    }

    // Log the setup request
    error_log("Website ID request: Domain {$domain} -> Website ID {$website->id} ({$website->name})");

    return new WP_REST_Response(
        array(
            'success' => true,
            'website_id' => intval($website->id),
            'website_name' => $website->name
        ),
        200
    );
}

/**
 * API callback function for updating website active_time
 * Called by satellite websites every 5 minutes via WP-Cron
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function update_website_status_api($request)
{
    global $wpdb;

    // Get website_id parameter
    $website_id = isset($request['website_id']) ? intval($request['website_id']) : 0;

    if ($website_id <= 0) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Valid website_id is required'
            ),
            400
        );
    }

    // Check if website exists
    $websites_table = $wpdb->prefix . 'im_websites';
    $website = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name FROM {$websites_table} WHERE id = %d",
        $website_id
    ));

    if (!$website) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Website not found'
            ),
            404
        );
    }

    // Update active_time to current timestamp
    $update_result = $wpdb->update(
        $websites_table,
        array('active_time' => current_time('mysql')),
        array('id' => $website_id),
        array('%s'),
        array('%d')
    );

    if ($update_result === false) {
        error_log("Failed to update active_time for website ID {$website_id}: " . $wpdb->last_error);
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Failed to update status'
            ),
            500
        );
    }

    // Get updated active_time
    $updated_website = $wpdb->get_row($wpdb->prepare(
        "SELECT active_time FROM {$websites_table} WHERE id = %d",
        $website_id
    ));

    return new WP_REST_Response(
        array(
            'success' => true,
            'active_time' => $updated_website->active_time,
            'message' => 'Status updated successfully'
        ),
        200
    );
}

/**
 * API callback function for checking a remote website's status
 * Call the remote website's API to see if it's active and update active_time if so
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function check_website_status_api($request)
{
    global $wpdb;

    // Get website parameter (domain or name)
    $website_input = isset($request['website']) ? sanitize_text_field($request['website']) : '';

    if (empty($website_input)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Website name (domain) is required'
            ),
            400
        );
    }

    // Clean domain (remove protocol, www., trailing slash)
    $domain = preg_replace('#^https?://(www\.)?#', '', $website_input);
    $domain = rtrim($domain, '/');

    // Find website in database by name/domain
    $websites_table = $wpdb->prefix . 'im_websites';
    $website = $wpdb->get_row($wpdb->prepare("
        SELECT id, name
        FROM {$websites_table}
        WHERE name = %s
        AND (status IS NULL OR status != 'DELETED')
        LIMIT 1
    ", $domain));

    if (!$website) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Website not found for: ' . $domain
            ),
            404
        );
    }

    // Call the satellite API (https preferred)
    $api_url = "https://" . $domain . "/wp-json/inova/v1/status";
    
    // Timeout set to 15s to allow for Cloudflare-protected sites or slow responses
    $response = wp_remote_get($api_url, array('timeout' => 15));

    // If HTTPS fails, try HTTP (optional but safer for older systems)
    if (is_wp_error($response)) {
        $api_url = "http://" . $domain . "/wp-json/inova/v1/status";
        $response = wp_remote_get($api_url, array('timeout' => 15));
    }

    if (is_wp_error($response)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Could not connect to website: ' . $response->get_error_message()
            ),
            500
        );
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($http_code === 200 && !empty($data['status']) && $data['status'] === true) {
        // Website is active! Update active_time using the same logic as update_website_status_api
        $current_time = current_time('mysql');
        $update_result = $wpdb->update(
            $websites_table,
            array('active_time' => $current_time),
            array('id' => $website->id),
            array('%s'),
            array('%d')
        );

        if ($update_result === false) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Website is active but failed to update database status'
                ),
                500
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'active_time' => $current_time,
                'message' => 'Website is active and status has been updated'
            ),
            200
        );
    } else {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Website responded negatively or returned an error',
                'http_code' => $http_code,
                'response_data' => $data
            ),
            200 // Success call, but status false
        );
    }
}

/**
 * API callback function for getting services expiring soon or expired
 * Lấy danh sách các dịch vụ (domain, hosting, maintenance) sắp hết hạn hoặc quá hạn
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function get_services_expiring_api($request)
{
    global $wpdb;

    // Get parameters
    $days_offset = isset($request['days']) ? intval($request['days']) : 0;  // +3 (3 days left), -30 (30 days overdue)
    $owner_user_ids_raw = isset($request['owner_user_id']) ? $request['owner_user_id'] : '';
    $service_type = isset($request['service_type']) ? sanitize_text_field($request['service_type']) : '';  // 'domain', 'hosting', 'maintenance', or '' for all
    $page = isset($request['page']) ? max(1, intval($request['page'])) : 1;
    $per_page = isset($request['per_page']) ? intval($request['per_page']) : 20;

    if ($per_page < 1 || $per_page > 100) {
        $per_page = 20;
    }

    // Parse owner_user_id - hỗ trợ single ID, comma-separated string, hoặc JSON array
    // Ví dụ: owner_user_id=5 | owner_user_id=5,19,22 | owner_user_id=[5,19,22]
    $owner_user_id_array = array();
    if (!empty($owner_user_ids_raw)) {
        if (is_array($owner_user_ids_raw)) {
            // Already an array (e.g. owner_user_id[]=5&owner_user_id[]=19)
            $owner_user_id_array = array_map('intval', $owner_user_ids_raw);
        } else {
            // Try to parse as JSON array first (e.g. owner_user_id=[19,22])
            $parsed = json_decode($owner_user_ids_raw, true);
            if (is_array($parsed)) {
                $owner_user_id_array = array_map('intval', $parsed);
            } else {
                // Parse as comma-separated string or single ID (e.g. owner_user_id=5,19,22)
                $owner_user_id_array = array_map('intval', array_filter(array_map('trim', explode(',', $owner_user_ids_raw))));
            }
        }
        // Filter out zeros/invalid
        $owner_user_id_array = array_values(array_filter($owner_user_id_array));
    }

    $offset = ($page - 1) * $per_page;
    $today = date('Y-m-d');
    $target_date = date('Y-m-d', strtotime("{$days_offset} days"));

    // Determine date range for filtering
    // days >= 0: Expiring soon (today to future)
    // days < 0: Already expired within N days (past to today)
    if ($days_offset >= 0) {
        $start_date = $today;
        $end_date = $target_date;
    } else {
        $start_date = $target_date;
        $end_date = $today;
    }

    $results = array();
    $websites_table = $wpdb->prefix . 'im_websites';
    $users_table = $wpdb->prefix . 'im_users';

    // Query domains
    if (empty($service_type) || $service_type === 'domain') {
        $domains_table = $wpdb->prefix . 'im_domains';

        $domain_query = "
            SELECT
                d.id,
                d.domain_name,
                d.owner_user_id,
                d.expiry_date,
                u.name AS owner_name,
                d.status,
                'domain' AS service_type,
                w.name AS website_name
            FROM {$domains_table} d
            LEFT JOIN {$users_table} u ON d.owner_user_id = u.id
            LEFT JOIN {$websites_table} w ON w.domain_id = d.id
            WHERE d.status NOT IN ('DELETED')
        ";

        $query_params = array();

        if (!empty($owner_user_id_array)) {
            $placeholders = implode(',', array_fill(0, count($owner_user_id_array), '%d'));
            $domain_query .= " AND d.owner_user_id IN ({$placeholders})";
            $query_params = array_merge($query_params, $owner_user_id_array);
        }

        // Filter by date range (start_date to end_date)
        $domain_query .= " AND d.expiry_date >= %s AND d.expiry_date <= %s";
        $query_params[] = $start_date;
        $query_params[] = $end_date;

        $domain_results = $wpdb->get_results($wpdb->prepare($domain_query, $query_params), ARRAY_A);

        $results = array_merge($results, $domain_results ? $domain_results : array());
    }

    // Query hostings
    if (empty($service_type) || $service_type === 'hosting') {
        $hostings_table = $wpdb->prefix . 'im_hostings';

        $hosting_query = "
            SELECT
                h.id,
                h.hosting_code AS name,
                h.owner_user_id,
                h.expiry_date,
                u.name AS owner_name,
                h.status,
                'hosting' AS service_type,
                w.name AS website_name
            FROM {$hostings_table} h
            LEFT JOIN {$users_table} u ON h.owner_user_id = u.id
            LEFT JOIN {$websites_table} w ON w.hosting_id = h.id
            WHERE h.status NOT IN ('DELETED')
        ";

        $query_params = array();

        if (!empty($owner_user_id_array)) {
            $placeholders = implode(',', array_fill(0, count($owner_user_id_array), '%d'));
            $hosting_query .= " AND h.owner_user_id IN ({$placeholders})";
            $query_params = array_merge($query_params, $owner_user_id_array);
        }

        // Filter by date range (start_date to end_date)
        $hosting_query .= " AND h.expiry_date >= %s AND h.expiry_date <= %s";
        $query_params[] = $start_date;
        $query_params[] = $end_date;

        $hosting_results = $wpdb->get_results($wpdb->prepare($hosting_query, $query_params), ARRAY_A);

        $results = array_merge($results, $hosting_results ? $hosting_results : array());
    }

    // Query maintenance packages
    if (empty($service_type) || $service_type === 'maintenance') {
        $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';

        $maintenance_query = "
            SELECT
                m.id,
                m.order_code AS name,
                m.owner_user_id,
                m.expiry_date,
                u.name AS owner_name,
                m.status,
                'maintenance' AS service_type,
                w.name AS website_name
            FROM {$maintenance_table} m
            LEFT JOIN {$users_table} u ON m.owner_user_id = u.id
            LEFT JOIN {$websites_table} w ON w.maintenance_package_id = m.id
            WHERE m.status NOT IN ('DELETED')
        ";

        $query_params = array();

        if (!empty($owner_user_id_array)) {
            $placeholders = implode(',', array_fill(0, count($owner_user_id_array), '%d'));
            $maintenance_query .= " AND m.owner_user_id IN ({$placeholders})";
            $query_params = array_merge($query_params, $owner_user_id_array);
        }

        // Filter by date range (start_date to end_date)
        $maintenance_query .= " AND m.expiry_date >= %s AND m.expiry_date <= %s";
        $query_params[] = $start_date;
        $query_params[] = $end_date;

        $maintenance_results = $wpdb->get_results($wpdb->prepare($maintenance_query, $query_params), ARRAY_A);

        $results = array_merge($results, $maintenance_results ? $maintenance_results : array());
    }

    // Process and Group results by website name
    $grouped_results = array();
    foreach ($results as $item) {
        $website_name = !empty($item['website_name']) ? $item['website_name'] : '';
        
        // If no website linked, use domain name or service name as identifier
        if (empty($website_name)) {
            if ($item['service_type'] === 'domain') {
                $website_name = $item['domain_name'];
            } else {
                $website_name = $item['name'] . ' (Unlinked)';
            }
        }

        if (!isset($grouped_results[$website_name])) {
            $grouped_results[$website_name] = array(
                'website_name' => $website_name,
                'owner_name' => !empty($item['owner_name']) ? $item['owner_name'] : 'N/A',
                'services_list' => array(),
                'expiry_dates' => array()
            );
        }

        // Add service to list for markdown formatting
        $service_label = '';
        switch ($item['service_type']) {
            case 'domain':
                $service_label = 'Dịch vụ tên miền ' . $website_name;
                break;
            case 'hosting':
                $service_label = 'Dịch vụ hosting ' . $website_name;
                break;
            case 'maintenance':
                $service_label = 'Dịch vụ bảo trì ' . $website_name;
                break;
        }
        $grouped_results[$website_name]['services_list'][] = $service_label;
        $grouped_results[$website_name]['expiry_dates'][] = $item['expiry_date'];
    }

    // Finalize grouped objects
    $final_results = array();
    foreach ($grouped_results as $group) {
        // Format services as markdown
        $services_md = '';
        if (!empty($group['services_list'])) {
            $services_md = implode("\n", array_map(function($s) { return "- " . $s; }, $group['services_list']));
        }

        // Handle expiry_date (single or array)
        $unique_dates = array_unique($group['expiry_dates']);
        sort($unique_dates);
        $expiry_date_val = count($unique_dates) === 1 ? reset($unique_dates) : array_values($unique_dates);

        $final_results[] = array(
            'website_name' => $group['website_name'],
            'owner_name' => $group['owner_name'],
            'services' => $services_md,
            'expiry_date' => $expiry_date_val
        );
    }

    $total = count($final_results);

    // Apply pagination to grouped results
    $paginated_results = array_slice($final_results, $offset, $per_page);
    $total_pages = ceil($total / $per_page);

    return new WP_REST_Response(
        array(
            'success' => true,
            'total_results' => $total,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page,
            'days_offset' => $days_offset,
            'target_date' => $target_date,
            'results' => $paginated_results
        ),
        200
    );
}

/**
 * API callback function for getting pending invoices
 * Lấy danh sách các hóa đơn đang chờ thanh toán
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function get_pending_invoices_api($request)
{
    global $wpdb;

    $invoices_table = $wpdb->prefix . 'im_invoices';
    $users_table = $wpdb->prefix . 'im_users';

    $page = isset($request['page']) ? max(1, intval($request['page'])) : 1;
    $per_page = isset($request['per_page']) ? intval($request['per_page']) : 20;
    $user_ids = isset($request['user_id']) ? $request['user_id'] : '';
    $overdue_days = isset($request['overdue_days']) ? intval($request['overdue_days']) : 0;

    if ($per_page < 1 || $per_page > 100) {
        $per_page = 20;
    }

    $offset = ($page - 1) * $per_page;
    $today = date('Y-m-d');

    // Parse user_ids - can be single ID, comma-separated string, or JSON array
    $user_id_array = array();
    if (!empty($user_ids)) {
        if (is_array($user_ids)) {
            // Already an array
            $user_id_array = array_map('intval', $user_ids);
        } else {
            // Try to parse as JSON array first
            $parsed = json_decode($user_ids, true);
            if (is_array($parsed)) {
                $user_id_array = array_map('intval', $parsed);
            } else {
                // Parse as comma-separated string
                $user_id_array = array_map('intval', array_filter(array_map('trim', explode(',', $user_ids))));
            }
        }
        // Filter out zeros
        $user_id_array = array_filter($user_id_array);
    }

    // Build query
    $query = "
        SELECT
            i.id,
            i.invoice_code,
            i.user_id,
            i.invoice_date,
            i.due_date,
            i.sub_total,
            i.discount_total,
            i.tax_amount,
            i.total_amount,
            i.paid_amount,
            i.payment_date,
            i.payment_method,
            i.status,
            i.notes,
            i.created_at,
            u.name AS customer_name,
            u.email,
            IF(i.due_date >= %s, 0, DATEDIFF(%s, i.due_date)) AS days_overdue
        FROM {$invoices_table} i
        LEFT JOIN {$users_table} u ON i.user_id = u.id
        WHERE i.status IN ('pending', 'pending_completion')
    ";

    $query_params = array($today, $today);

    // Filter by user_ids if provided
    if (!empty($user_id_array)) {
        $placeholders = implode(',', array_fill(0, count($user_id_array), '%d'));
        $query .= " AND i.user_id IN ({$placeholders})";
        $query_params = array_merge($query_params, $user_id_array);
    }

    // Filter by overdue if specified
    if ($overdue_days > 0) {
        $query .= " AND i.due_date <= DATE_SUB(%s, INTERVAL %d DAY)";
        $query_params[] = $today;
        $query_params[] = $overdue_days;
    }

    // Add ordering and pagination
    $query .= " ORDER BY i.due_date ASC LIMIT %d OFFSET %d";
    $query_params[] = $per_page;
    $query_params[] = $offset;

    // Execute query
    $invoices = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);

    // Get invoice items for each invoice
    $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
    $final_results = array();

    foreach ($invoices as $invoice) {
        $items_query = "
            SELECT description, service_type, service_id
            FROM {$invoice_items_table}
            WHERE invoice_id = %d
            ORDER BY id
        ";

        $items = $wpdb->get_results($wpdb->prepare($items_query, $invoice['id']), ARRAY_A);
        
        // Summarize items into markdown
        $items_md = "";
        if (!empty($items)) {
            $items_md = implode("\n", array_map(function($item) {
                // Get linked website names
                $website_names = get_invoice_item_website_names((object)$item);
                $description = $item['description'];
                
                if (!empty($website_names)) {
                    $description .= " cho " . implode(', ', $website_names);
                }
                
                return "- " . $description;
            }, $items));
        }

        $final_results[] = array(
            'invoice_code' => $invoice['invoice_code'],
            'total_amount' => floatval($invoice['total_amount']),
            'due_date' => $invoice['due_date'],
            'customer_name' => $invoice['customer_name'],
            'days_overdue' => intval($invoice['days_overdue']),
            'items' => $items_md,
            'link_invoice' => home_url('/public-invoice/?token=' . urlencode(base64_encode($invoice['id'] . '|' . $invoice['created_at'])))
        );
    }

    // Get total count
    $count_query = "
        SELECT COUNT(*) FROM {$invoices_table} i
        WHERE i.status IN ('pending', 'pending_completion')
    ";

    $count_params = array();

    if (!empty($user_id_array)) {
        $placeholders = implode(',', array_fill(0, count($user_id_array), '%d'));
        $count_query .= " AND i.user_id IN ({$placeholders})";
        $count_params = $user_id_array;
    }

    if ($overdue_days > 0) {
        $count_query .= " AND i.due_date <= DATE_SUB(%s, INTERVAL %d DAY)";
        $count_params[] = $today;
        $count_params[] = $overdue_days;
    }

    $total = $wpdb->get_var($wpdb->prepare($count_query, $count_params));
    $total_pages = ceil($total / $per_page);

    return new WP_REST_Response(
        array(
            'success' => true,
            'total_results' => intval($total),
            'total_pages' => intval($total_pages),
            'current_page' => $page,
            'per_page' => $per_page,
            'results' => $final_results
        ),
        200
    );
}

/**
 * API callback function for updating invoice status
 * Cập nhật trạng thái hóa đơn, nếu là PAID thì cập nhật payment_date, paid_amount, payment_method
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function update_invoice_status_api($request)
{
    global $wpdb;

    $invoices_table = $wpdb->prefix . 'im_invoices';

    $invoice_id = isset($request['invoice_id']) ? intval($request['invoice_id']) : 0;
    $status = isset($request['status']) ? sanitize_text_field($request['status']) : '';
    $payment_date = isset($request['payment_date']) ? sanitize_text_field($request['payment_date']) : null;
    $paid_amount = isset($request['paid_amount']) ? intval($request['paid_amount']) : null;
    $payment_method = isset($request['payment_method']) ? sanitize_text_field($request['payment_method']) : null;

    // Validate required fields
    if ($invoice_id <= 0) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Invoice ID is required'
            ),
            400
        );
    }

    if (empty($status)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Status is required'
            ),
            400
        );
    }

    // Validate status value - convert to lowercase for consistency
    $status = strtolower($status);
    $valid_statuses = array('draft', 'pending', 'pending_completion', 'paid', 'canceled');
    if (!in_array($status, $valid_statuses)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => "Invalid status - must be one of: " . implode(', ', $valid_statuses)
            ),
            400
        );
    }

    // Check if invoice exists
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$invoices_table} WHERE id = %d",
        $invoice_id
    ));

    if (!$invoice) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Invoice not found'
            ),
            404
        );
    }

    // Prepare update data
    $update_data = array('status' => $status);
    $update_formats = array('%s');

    // If status is paid, update payment information
    if ($status === 'paid') {
        if (!$payment_date) {
            $payment_date = current_time('mysql');
        }
        if ($paid_amount === null) {
            $paid_amount = $invoice->total_amount;
        }
        // Default payment method is bank transfer if not provided but amount is specified
        if (!$payment_method && $paid_amount > 0) {
            $payment_method = 'chuyển khoản ngân hàng';
        }

        $update_data['payment_date'] = $payment_date;
        $update_data['paid_amount'] = $paid_amount;
        if ($payment_method) {
            $update_data['payment_method'] = $payment_method;
            $update_formats[] = '%s';
        }

        $update_formats[] = '%s';
        $update_formats[] = '%d';
    }

    // Update invoice
    $result = $wpdb->update(
        $invoices_table,
        $update_data,
        array('id' => $invoice_id),
        $update_formats,
        array('%d')
    );

    if ($result === false) {
        error_log("Failed to update invoice ID {$invoice_id}: " . $wpdb->last_error);
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Failed to update invoice status'
            ),
            500
        );
    }

    // Get updated invoice
    $updated_invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$invoices_table} WHERE id = %d",
        $invoice_id
    ), ARRAY_A);

    return new WP_REST_Response(
        array(
            'success' => true,
            'message' => 'Invoice status updated successfully',
            'invoice' => $updated_invoice
        ),
        200
    );
}

/**
 * API callback function for getting domain management info
 * Lấy thông tin quản lý domain (management_url, management_username, management_password)
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function get_domain_management_info_api($request)
{
    global $wpdb;

    $domains_table = $wpdb->prefix . 'im_domains';

    $domain_id = isset($request['domain_id']) ? intval($request['domain_id']) : 0;
    $domain_name = isset($request['domain_name']) ? sanitize_text_field($request['domain_name']) : '';

    // Validate - at least one parameter required
    if ($domain_id <= 0 && empty($domain_name)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Either domain_id or domain_name is required'
            ),
            400
        );
    }

    // Build query
    if ($domain_id > 0) {
        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT id, domain_name, management_url, management_username, management_password FROM {$domains_table} WHERE id = %d",
            $domain_id
        ), ARRAY_A);
    } else {
        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT id, domain_name, management_url, management_username, management_password FROM {$domains_table} WHERE domain_name = %s",
            $domain_name
        ), ARRAY_A);
    }

    if (!$domain) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Domain not found'
            ),
            404
        );
    }

    // Decrypt password if exists
    if (!empty($domain['management_password'])) {
        $domain['management_password'] = im_decrypt_password($domain['management_password']);
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'domain' => $domain
        ),
        200
    );
}

/**
 * API callback function for getting website management info
 * Lấy thông tin quản lý website (admin_url, admin_username, admin_password)
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function get_website_management_info_api($request)
{
    global $wpdb;

    $websites_table = $wpdb->prefix . 'im_websites';

    $website_id = isset($request['website_id']) ? intval($request['website_id']) : 0;

    // Validate
    if ($website_id <= 0) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'website_id is required'
            ),
            400
        );
    }

    // Get website
    $website = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, admin_url, admin_username, admin_password FROM {$websites_table} WHERE id = %d",
        $website_id
    ), ARRAY_A);

    if (!$website) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Website not found'
            ),
            404
        );
    }

    // Decrypt password if exists
    if (!empty($website['admin_password'])) {
        $website['admin_password'] = im_decrypt_password($website['admin_password']);
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'website' => $website
        ),
        200
    );
}

/**
 * API callback function for getting users managed by a partner
 * Lấy danh sách user_id mà partner đó quản lý
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function get_partner_managed_users_api($request)
{
    global $wpdb;

    $users_table = $wpdb->prefix . 'im_users';
    $partner_id = isset($request['partner_id']) ? intval($request['partner_id']) : 0;

    // Validate required parameter
    if ($partner_id <= 0) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Missing or invalid required parameter: partner_id'
            ),
            400
        );
    }

    // Get all users managed by this partner
    $query = "
        SELECT id, name, email, user_code, user_type, status
        FROM {$users_table}
        WHERE partner_id = %d
        ORDER BY name ASC
    ";

    $users = $wpdb->get_results($wpdb->prepare($query, $partner_id), ARRAY_A);

    // Extract user IDs and prepend partner_id at the beginning (convert all to integers)
    $managed_user_ids = array_map('intval', array_column($users, 'id'));
    $user_ids = array_merge([$partner_id], $managed_user_ids);

    // Get total count
    $total = count($users);

    return new WP_REST_Response(
        array(
            'success' => true,
            'partner_id' => $partner_id,
            'total_users' => $total,
            'user_ids' => $user_ids,
            'users' => $users
        ),
        200
    );
}

/**
 * API callback function for updating domain management info
 * Cập nhật thông tin quản lý domain (management_url, management_username, management_password)
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function update_domain_management_info_api($request)
{
    global $wpdb;

    $domains_table = $wpdb->prefix . 'im_domains';

    $domain_id = isset($request['domain_id']) ? intval($request['domain_id']) : 0;
    $domain_name = isset($request['domain_name']) ? sanitize_text_field($request['domain_name']) : '';

    // Validate - at least one parameter required to identify the domain
    if ($domain_id <= 0 && empty($domain_name)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Either domain_id or domain_name is required'
            ),
            400
        );
    }

    // Prepare update data
    $update_data = array();
    $update_formats = array();

    if (isset($request['management_url'])) {
        $update_data['management_url'] = sanitize_text_field($request['management_url']);
        $update_formats[] = '%s';
    }

    if (isset($request['management_username'])) {
        $update_data['management_username'] = sanitize_text_field($request['management_username']);
        $update_formats[] = '%s';
    }

    if (isset($request['management_password'])) {
        $update_data['management_password'] = im_encrypt_password($request['management_password']);
        $update_formats[] = '%s';
    }

    // Check if there's anything to update
    if (empty($update_data)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'No data provided to update'
            ),
            400
        );
    }

    // Build WHERE clause
    $where = array();
    $where_format = array();

    if ($domain_id > 0) {
        $where['id'] = $domain_id;
        $where_format[] = '%d';
    } else {
        $where['domain_name'] = $domain_name;
        $where_format[] = '%s';
    }

    // Update database
    $result = $wpdb->update($domains_table, $update_data, $where, $update_formats, $where_format);

    if ($result === false) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Database error while updating domain info'
            ),
            500
        );
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'message' => 'Domain management info updated successfully'
        ),
        200
    );
}

/**
 * API callback function for updating website management info
 * Cập nhật thông tin quản lý website (admin_url, admin_username, admin_password)
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
function update_website_management_info_api($request)
{
    global $wpdb;

    $websites_table = $wpdb->prefix . 'im_websites';

    $website_id = isset($request['website_id']) ? intval($request['website_id']) : 0;

    // Validate
    if ($website_id <= 0) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'website_id is required'
            ),
            400
        );
    }

    // Prepare update data
    $update_data = array();
    $update_formats = array();

    if (isset($request['admin_url'])) {
        $update_data['admin_url'] = sanitize_text_field($request['admin_url']);
        $update_formats[] = '%s';
    }

    if (isset($request['admin_username'])) {
        $update_data['admin_username'] = sanitize_text_field($request['admin_username']);
        $update_formats[] = '%s';
    }

    if (isset($request['admin_password'])) {
        $update_data['admin_password'] = im_encrypt_password($request['admin_password']);
        $update_formats[] = '%s';
    }

    // Check if there's anything to update
    if (empty($update_data)) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'No data provided to update'
            ),
            400
        );
    }

    // Update database
    $result = $wpdb->update(
        $websites_table,
        $update_data,
        array('id' => $website_id),
        $update_formats,
        array('%d')
    );

    if ($result === false) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Database error while updating website info'
            ),
            500
        );
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'message' => 'Website management info updated successfully'
        ),
        200
    );
}