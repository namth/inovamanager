<?php
/**
 * Inova Manager Theme Functions
 * 
 * Main theme functions file - imports from includes/ directory
 * Each include file contains:
 * - Related functions
 * - add_action/add_filter hooks for those functions
 * 
 * Structure:
 * - includes/helpers.php - Helper functions + their hooks
 * - includes/database-functions.php - Database operations + their hooks
 * - includes/ajax-handlers.php - AJAX handlers + their action registrations
 * - includes/api-endpoints.php - REST API endpoints + their hooks
 */

/* Include API functions */
require_once get_template_directory() . '/api.php';

/* ===== INCLUDE SEPARATED FUNCTION FILES ===== */
/* Each file contains functions + their corresponding hooks */
require_once get_template_directory() . '/includes/helpers.php';
require_once get_template_directory() . '/includes/database-functions.php';
require_once get_template_directory() . '/includes/ajax-handlers.php';
require_once get_template_directory() . '/includes/api-endpoints.php';

/* ===== FLUSH REWRITE RULES ON THEME ACTIVATION ===== */
/* Ensures virtual page URLs work correctly after adding new pages */
add_action('after_switch_theme', function() {
    flush_rewrite_rules();
});
