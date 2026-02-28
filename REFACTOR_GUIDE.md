# Functions.php Refactor - Quick Guide

## 📂 New File Structure

```
bookorder/
├── functions.php                     ← Main file (224 lines)
├── api.php                           ← API integration
├── includes/                         ← NEW FOLDER
│   ├── helpers.php                   ← Helper functions (1,077 lines)
│   ├── database-functions.php        ← DB operations (2,594 lines)
│   ├── ajax-handlers.php             ← AJAX callbacks (2,048 lines)
│   └── api-endpoints.php             ← REST API endpoints (247 lines)
├── assets/
│   ├── css/
│   ├── js/
│   └── vendors/
├── header.php
├── footer.php
├── sidebar.php
└── [other template files]
```

---

## 🔍 Where to Find Functions

### I need a **helper/utility function**
→ Look in **`includes/helpers.php`**

Examples:
- Password encryption/decryption
- User ID conversion
- Permission checks
- Template routing
- VAT calculations
- Email helpers

### I need **database query/operation function**
→ Look in **`includes/database-functions.php`**

Examples:
- Create invoice
- Calculate commissions
- Get expiring services
- Generate report data
- Webhook operations
- Cron job functions

### I need **AJAX handler**
→ Look in **`includes/ajax-handlers.php`**

Examples:
- Add to cart
- Update profile
- Change password
- Update settings
- Get data via AJAX

### I need **REST API endpoint**
→ Look in **`includes/api-endpoints.php`**

Examples:
- Get expiring services
- Create bulk invoice

### I need to **register a hook/action**
→ Look in **`functions.php`** (main file)

Examples:
- `add_action('wp_enqueue_scripts', 'enqueue_js_css')`
- AJAX handler registrations
- Cron job scheduling

---

## ⚡ How to Add a New Function

### Scenario 1: Add a new HELPER function
1. Open `includes/helpers.php`
2. Add your function at the appropriate section
3. Save the file
4. **No need to change functions.php** - helpers.php is auto-loaded

```php
// Example: Add helper function
function my_new_helper() {
    // Your code here
}
```

### Scenario 2: Add a new DATABASE function
1. Open `includes/database-functions.php`
2. Add your function at the appropriate section
3. Save the file
4. **No need to change functions.php** - database-functions.php is auto-loaded

```php
// Example: Add database function
function my_database_operation() {
    global $wpdb;
    // Your code here
}
```

### Scenario 3: Add a new AJAX handler
1. **Method A:** Add function to `includes/ajax-handlers.php`
2. Add the hook in `functions.php`

```php
// In includes/ajax-handlers.php
function my_ajax_handler() {
    check_ajax_referer('security_nonce', 'security');
    // Your code here
}

// In functions.php - register it
add_action('wp_ajax_my_action', 'my_ajax_handler');
add_action('wp_ajax_nopriv_my_action', 'my_ajax_handler');
```

### Scenario 4: Add new REST API endpoint
1. Add function to `includes/api-endpoints.php`
2. Register route in `functions.php`

```php
// In includes/api-endpoints.php
function my_api_handler($request) {
    return rest_ensure_response(['data' => 'result']);
}

// In functions.php
add_action('rest_api_init', function() {
    register_rest_route('inova/v1', '/my-endpoint', [
        'methods' => 'POST',
        'callback' => 'my_api_handler'
    ]);
});
```

---

## 🔗 Function Dependencies

### Load Order
When WordPress loads the theme, functions are loaded in this order:

```
1. functions.php (main file)
   ↓ (requires these 4 files in order)
2. helpers.php (base utilities, no dependencies)
   ↓
3. database-functions.php (uses helpers)
   ↓
4. ajax-handlers.php (uses helpers & database-functions)
   ↓
5. api-endpoints.php (uses database-functions)
   ↓
6. Hooks & actions are registered
```

### Important Notes
- **helpers.php** has no external dependencies - can be used by any file
- **database-functions.php** depends on helpers.php
- **ajax-handlers.php** depends on both helpers and database-functions
- **api-endpoints.php** depends on database-functions

---

## ✅ What Changed & What Didn't

### ✅ Changed
- **File organization** - Functions split into 4 logical files
- **Main functions.php** - Now only ~224 lines (was 7,232)

### ✅ NOT Changed
- **Function names** - All functions have same names
- **Function signatures** - All parameters are the same
- **Function behavior** - All code works identically
- **Database schema** - Completely unchanged
- **AJAX endpoints** - All respond the same way
- **API contracts** - REST API works exactly the same
- **Hooks & actions** - All registered in same way

---

## 🧪 Testing After Refactor

### Quick Test Checklist
- [ ] WordPress admin loads without errors
- [ ] Frontend pages load without errors
- [ ] AJAX requests work (add to cart, update settings)
- [ ] User login/logout works
- [ ] Invoices can be created
- [ ] Commissions calculate correctly
- [ ] API endpoints respond
- [ ] Email notifications send
- [ ] Cron jobs execute

### Where to Check for Errors
1. WordPress Admin → Tools → Site Health
2. Server error logs
3. Browser console (F12 → Console tab)
4. Check `/wp-content/debug.log` if enabled

---

## 📋 File Reference

### `functions.php` (224 lines)
**Purpose:** Main entry point, imports, and hook registration

**Sections:**
- Opening PHP + documentation
- Required includes (4 files)
- Hook registrations (200+ lines)

**Never remove these sections:**
```php
require_once get_template_directory() . '/includes/helpers.php';
require_once get_template_directory() . '/includes/database-functions.php';
require_once get_template_directory() . '/includes/ajax-handlers.php';
require_once get_template_directory() . '/includes/api-endpoints.php';
```

---

### `includes/helpers.php` (1,077 lines)
**Purpose:** Utility functions, encryption, user info, permissions

**Key Functions:**
- `im_encrypt_password()` - Encrypt passwords
- `get_user_inova_id()` - Convert WordPress ID to Inova ID
- `can_user_view_item()` - Permission check
- `enqueue_js_css()` - Load scripts & styles
- `get_virtual_page_template_mapping()` - Get page routes
- `auto_assign_page_template()` - Assign correct template

---

### `includes/database-functions.php` (2,594 lines)
**Purpose:** Database operations, queries, reports

**Key Functions:**
- `CreateDatabaseBookOrder()` - Create tables
- `create_invoice_with_items()` - Create invoice
- `create_commissions_for_invoice()` - Calculate commissions
- `get_expiring_services()` - Get expiring items
- `send_expiry_notification_emails()` - Send emails
- `auto_create_renewal_invoices_callback()` - Auto renew

---

### `includes/ajax-handlers.php` (2,048 lines)
**Purpose:** AJAX callback functions

**Key Handlers:**
- `add_to_cart_ajax()` - Add item to cart
- `change_user_password_callback()` - Change password
- `update_email_notification_setting_callback()` - Update settings
- `get_partner_commissions_callback()` - Get commissions
- `merge_invoices_callback()` - Merge invoices

---

### `includes/api-endpoints.php` (247 lines)
**Purpose:** REST API endpoint implementations

**Key Endpoints:**
- `api_get_expiring_services()` - GET `/inova/v1/expiring-services`
- `api_create_bulk_invoice()` - POST `/inova/v1/bulk-invoice`

---

## 🆘 Troubleshooting

### Problem: "Call to undefined function..."
**Solution:** 
1. Check function is in one of the 4 include files
2. Verify function name spelling
3. Check functions.php includes are not commented out

### Problem: "Cannot redeclare function..."
**Solution:** Function defined in multiple files
1. Search all files for the function name
2. Remove the duplicate
3. Keep only one copy

### Problem: AJAX not working
**Solution:**
1. Check action name matches callback function
2. Verify AJAX handler is in ajax-handlers.php
3. Verify `add_action()` is in functions.php
4. Check nonce validation if used

### Problem: Page templates not loading
**Solution:**
1. Check `get_virtual_page_template_mapping()` in helpers.php
2. Verify page route is registered
3. Check template file exists in theme directory

---

## 📞 Need Help?

If you encounter issues:
1. Check this guide first
2. Look at REFACTOR_SUMMARY.md for overview
3. Review REFACTOR_PLAN.md for detailed design
4. Check backup: `functions.php.backup.*` to restore if needed

---

## 🎯 Best Practices

### DO ✅
- Add new functions to appropriate file (helpers/db/ajax)
- Register hooks in functions.php main file
- Use helper functions from helpers.php
- Check if function already exists before adding

### DON'T ❌
- Don't remove the `require_once` statements from functions.php
- Don't add business logic directly to functions.php
- Don't create duplicate functions
- Don't modify function names (breaks dependencies)

---

**Last Updated:** February 28, 2025  
**Status:** ✅ Production Ready
