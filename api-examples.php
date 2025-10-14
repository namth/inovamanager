<?php
/**
 * Example scripts for using the BookOrder API endpoints
 * 
 * This file contains example code snippets to demonstrate 
 * how to use the various API endpoints.
 */

// API base URL - replace with your actual WordPress site URL
$api_base_url = 'https://your-wordpress-site.com/wp-json/bookorder/v1';

// Your API key - replace with your actual API key
$api_key = 'your-api-key-here';

/**
 * Example 1: Add a new partner
 */
function example_add_partner() {
    global $api_base_url, $api_key;
    
    // Partner data
    $partner_data = array(
        'user_code' => 'P1234',
        'user_type' => 'PARTNER',
        'name' => 'Example Partner',
        'email' => 'partner@example.com',
        'phone_number' => '1234567890',
        'address' => '123 Partner Street, City',
        'tax_code' => '987654321',
        'notes' => 'This is a test partner imported via API'
    );
    
    // Initialize cURL session
    $ch = curl_init($api_base_url . '/import-partner');
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($partner_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-API-KEY: ' . $api_key
    ));
    
    // Execute the request and get the response
    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Process the response
    process_response($ch, $response, $http_status);
}

/**
 * Example 2: Update an existing partner
 * 
 * @param int $partner_id The ID of the partner to update
 */
function example_update_partner($partner_id) {
    global $api_base_url, $api_key;
    
    // Update data - only include fields you want to update
    $update_data = array(
        'name' => 'Updated Partner Name',
        'email' => 'updated@example.com',
        'phone_number' => '9876543210',
        'notes' => 'Updated via API on ' . date('Y-m-d H:i:s')
    );
    
    // Initialize cURL session
    $ch = curl_init($api_base_url . '/update-partner/' . $partner_id);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($update_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-API-KEY: ' . $api_key
    ));
    
    // Execute the request and get the response
    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Process the response
    process_response($ch, $response, $http_status);
}

/**
 * Example 3: Add a contact for a business user
 * 
 * @param int $business_user_id The ID of the business user to add the contact to
 */
function example_add_contact($business_user_id) {
    global $api_base_url, $api_key;
    
    // Contact data
    $contact_data = array(
        'user_id' => $business_user_id,
        'full_name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'phone_number' => '1234567890',
        'position' => 'CEO',
        'is_primary' => true
    );
    
    // Initialize cURL session
    $ch = curl_init($api_base_url . '/add-contact');
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($contact_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-API-KEY: ' . $api_key
    ));
    
    // Execute the request and get the response
    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Process the response
    process_response($ch, $response, $http_status);
}

/**
 * Helper function to process API responses
 */
function process_response($ch, $response, $http_status) {
    // Check for cURL errors
    if (curl_errno($ch)) {
        echo 'cURL Error: ' . curl_error($ch) . "\n";
    } else {
        echo 'HTTP Status: ' . $http_status . "\n";
        echo 'Response: ' . $response . "\n";
        
        // Parse response
        $result = json_decode($response, true);
        
        if ($http_status >= 200 && $http_status < 300) {
            echo "\nRequest completed successfully!\n";
            if (isset($result['message'])) {
                echo "Message: " . $result['message'] . "\n";
            }
            
            // Print additional details if available
            if (isset($result['user_id'])) {
                echo "User ID: " . $result['user_id'] . "\n";
            } elseif (isset($result['contact_id'])) {
                echo "Contact ID: " . $result['contact_id'] . "\n";
            }
        } else {
            echo "\nError in request: " . ($result['message'] ?? 'Unknown error') . "\n";
        }
    }
    
    // Close cURL session
    curl_close($ch);
}

// Usage examples - Uncomment to test

// Example 1: Add a new partner
// example_add_partner();

// Example 2: Update an existing partner (replace 123 with an actual partner ID)
// example_update_partner(123);

// Example 3: Add a contact for a business user (replace 456 with an actual business user ID)
// example_add_contact(456);