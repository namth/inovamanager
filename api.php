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
function register_bookorder_api_routes() {
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
}
add_action('rest_api_init', 'register_bookorder_api_routes');

/**
 * API key validation function
 * 
 * @param WP_REST_Request $request The request object
 * @return bool True if valid API key, false otherwise
 */
function validate_api_key($request) {
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
function import_partner_api($request) {
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
function update_partner_api($request) {
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
            $wpdb->prepare("SELECT COUNT(*) FROM $users_table WHERE user_code = %s AND id != %d", 
                $params['user_code'], $user_id)
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
    if (!empty($params['user_code'])) $update_data['user_code'] = sanitize_text_field($params['user_code']);
    if (!empty($params['user_type'])) $update_data['user_type'] = sanitize_text_field($params['user_type']);
    if (!empty($params['name'])) $update_data['name'] = sanitize_text_field($params['name']);
    if (isset($params['email'])) $update_data['email'] = sanitize_email($params['email']);
    if (isset($params['phone_number'])) $update_data['phone_number'] = sanitize_text_field($params['phone_number']);
    if (isset($params['tax_code'])) $update_data['tax_code'] = sanitize_text_field($params['tax_code']);
    if (isset($params['address'])) $update_data['address'] = sanitize_text_field($params['address']);
    if (isset($params['notes'])) $update_data['notes'] = sanitize_text_field($params['notes']);
    if (isset($params['status'])) $update_data['status'] = sanitize_text_field($params['status']);
    
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
function add_contact_api($request) {
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
function search_partners_api($request) {
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
function generate_bookorder_api_key() {
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
function get_website_id_api($request) {
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

    // Find website by domain name
    $domains_table = $wpdb->prefix . 'im_domains';
    $websites_table = $wpdb->prefix . 'im_websites';

    $query = $wpdb->prepare("
        SELECT w.id, w.name, d.domain_name
        FROM {$websites_table} w
        LEFT JOIN {$domains_table} d ON w.domain_id = d.id
        WHERE d.domain_name = %s
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
            'website_name' => $website->name,
            'domain' => $website->domain_name
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
function update_website_status_api($request) {
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