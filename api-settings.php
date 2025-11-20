<?php
/**
 * Template Name: API Settings
 * 
 * Admin page for managing API keys
 */

// Ensure only administrators can access this page
if (!current_user_can('administrator')) {
    wp_redirect(home_url());
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (isset($_POST['api_settings_nonce']) && wp_verify_nonce($_POST['api_settings_nonce'], 'api_settings_action')) {
        if ($_POST['action'] === 'generate_key') {
            // Generate new API key
            $api_key = generate_bookorder_api_key();
            $notification = 'API key generated successfully!';
            $notification_type = 'success';
        } elseif ($_POST['action'] === 'revoke_key') {
            update_option('bookorder_api_key', '');
            $notification = 'API key has been revoked!';
            $notification_type = 'success';
        } elseif ($_POST['action'] === 'save_cloudflare_settings') {
            // Save Cloudflare settings
            update_option('cloudflare_api_token', sanitize_text_field($_POST['cloudflare_api_token']));
            update_option('cloudflare_account_id', sanitize_text_field($_POST['cloudflare_account_id']));
            $notification = 'Cloudflare settings saved successfully!';
            $notification_type = 'success';
        }
    } else {
        $notification = 'Security check failed!';
        $notification_type = 'error';
    }
}

// Get current API key
$current_api_key = get_option('bookorder_api_key', '');
// Get current Cloudflare settings
$cloudflare_api_token = get_option('cloudflare_api_token', '');
$cloudflare_account_id = get_option('cloudflare_account_id', '');

get_header();
?>

<div class="content-wrapper mt-5">
    <div class="card card-rounded">
        <div class="card-body">
            <div class="row">
                <div class="col-lg-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="display-3">API Settings</h3>
                    </div>

                    <?php if (isset($notification)): ?>
                    <div class="alert alert-<?php echo $notification_type === 'error' ? 'danger' : $notification_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo esc_html($notification); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-8 col-lg-6">
                            <div class="card">
                                <div class="card-header btn-primary">
                                    <h4 class="mb-1 mt-1"><i class="ph ph-gear me-2"></i> API Key Management</h4>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">The API key is required for authentication when using the REST API endpoints. Keep this key secure.</p>
                                    
                                    <?php if (!empty($current_api_key)): ?>
                                        <div class="mb-4">
                                            <label class="form-label fw-bold">Current API Key:</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="api-key-display" value="<?php echo esc_attr($current_api_key); ?>" readonly>
                                                <button class="btn btn-secondary d-flex align-items-center" type="button" onclick="copyApiKey()">
                                                    <i class="ph ph-copy"></i>
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">This key should be included in the X-API-KEY header with each API request.</small>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-4">
                                            No API key is currently active. Generate a new key to enable API access.
                                        </div>
                                    <?php endif; ?>

                                    <form method="post" class="d-inline-block me-2">
                                        <?php wp_nonce_field('api_settings_action', 'api_settings_nonce'); ?>
                                        <input type="hidden" name="action" value="generate_key">
                                        <button type="submit" class="btn btn-success">
                                            <i class="ph ph-key"></i> Generate New API Key
                                        </button>
                                    </form>

                                    <?php if (!empty($current_api_key)): ?>
                                    <form method="post" class="d-inline-block">
                                        <?php wp_nonce_field('api_settings_action', 'api_settings_nonce'); ?>
                                        <input type="hidden" name="action" value="revoke_key">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to revoke this API key? Any services using it will no longer work.');">
                                            <i class="ph ph-prohibit"></i> Revoke API Key
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Cloudflare API Settings -->
                        <div class="col-md-8 col-lg-6 mt-4">
                            <div class="card">
                                <div class="card-header btn-info">
                                    <h4 class="mb-1 mt-1"><i class="ph ph-cloud-arrow-up me-2"></i> Cloudflare API Settings</h4>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Enter your Cloudflare API Token and Account ID to enable website import functionality.</p>
                                    <form method="post">
                                        <?php wp_nonce_field('api_settings_action', 'api_settings_nonce'); ?>
                                        <input type="hidden" name="action" value="save_cloudflare_settings">

                                        <div class="mb-3">
                                            <label for="cloudflare_api_token" class="form-label fw-bold">API Token</label>
                                            <input type="text" class="form-control" id="cloudflare_api_token" name="cloudflare_api_token" value="<?php echo esc_attr($cloudflare_api_token); ?>" placeholder="Enter your Cloudflare API Token">
                                            <small class="form-text text-muted">Create a token with "Zone:Read" and "DNS:Read" permissions.</small>
                                        </div>

                                        <div class="mb-3">
                                            <label for="cloudflare_account_id" class="form-label fw-bold">Account ID</label>
                                            <input type="text" class="form-control" id="cloudflare_account_id" name="cloudflare_account_id" value="<?php echo esc_attr($cloudflare_account_id); ?>" placeholder="Enter your Cloudflare Account ID">
                                            <small class="form-text text-muted">You can find this on the main page of your Cloudflare dashboard.</small>
                                        </div>

                                        <button type="submit" class="btn btn-info">
                                            <i class="ph ph-floppy-disk"></i> Save Cloudflare Settings
                                        </button>
                                        <button type="button" class="btn btn-secondary ms-2" id="test-cloudflare-connection">
                                            <i class="ph ph-plugs-connected"></i> Test Connection
                                        </button>
                                    </form>
                                    <div id="cloudflare-test-result" class="mt-3"></div>
                                </div>
                            </div>
                        </div>

                        <!-- New endpoints documentation -->
                        <div class="col-md-8 col-lg-6 mt-4">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="mb-4">Additional API Endpoints</h4>
                                    
                                    <!-- Update Partner API -->
                                    <div class="mb-4">
                                        <h5 class="fw-bold">Update Partner API</h5>
                                        <p>Update an existing partner's information.</p>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Endpoint:</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" value="<?php echo esc_url(rest_url('bookorder/v1/update-partner/{id}')); ?>" readonly>
                                                <button class="btn btn-secondary d-flex align-items-center" type="button" onclick="copyEndpoint(this)">
                                                    <i class="ph ph-copy"></i>
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">Replace {id} with the actual user ID</small>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Method:</label>
                                            <p><code>PUT</code></p>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Headers:</label>
                                            <pre class="bg-light p-3 border rounded"><code>X-API-KEY: your_api_key
Content-Type: application/json</code></pre>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Parameters:</label>
                                            <pre class="bg-light p-3 border rounded"><code>{
  "name": "Updated Partner Name",
  "email": "updated@example.com",
  "phone_number": "0987654321",
  "address": "New Address",
  "notes": "Updated notes"
}</code></pre>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Notes:</label>
                                            <p>Only include the fields you want to update. All fields are optional.</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Add Contact API -->
                                    <div>
                                        <h5 class="fw-bold">Add Contact API</h5>
                                        <p>Add a new contact for a business customer.</p>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Endpoint:</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" value="<?php echo esc_url(rest_url('bookorder/v1/add-contact')); ?>" readonly>
                                                <button class="btn btn-secondary d-flex align-items-center" type="button" onclick="copyEndpoint(this)">
                                                    <i class="ph ph-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Method:</label>
                                            <p><code>POST</code></p>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Headers:</label>
                                            <pre class="bg-light p-3 border rounded"><code>X-API-KEY: your_api_key
Content-Type: application/json</code></pre>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Parameters:</label>
                                            <pre class="bg-light p-3 border rounded"><code>{
  "user_id": 123,
  "full_name": "Contact Name",
  "email": "contact@example.com",
  "phone_number": "1234567890",
  "position": "CEO",
  "is_primary": true
}</code></pre>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="fw-bold">Required Fields:</label>
                                            <ul class="list-group">
                                                <li class="list-group-item"><code>user_id</code> - ID of the business user</li>
                                                <li class="list-group-item"><code>full_name</code> - Name of the contact person</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="form-group mt-3">
                                            <label class="fw-bold">Notes:</label>
                                            <p>This API only works for users with <code>user_type</code> set to <code>BUSINESS</code>.</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Search Partners API -->
                                    <div class="mt-4">
                                        <h5 class="fw-bold">Search Partners API</h5>
                                        <p>Search for partners in the database using keywords and filters.</p>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Endpoint:</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" value="<?php echo esc_url(rest_url('bookorder/v1/search-partners')); ?>" readonly>
                                                <button class="btn btn-secondary d-flex align-items-center" type="button" onclick="copyEndpoint(this)">
                                                    <i class="ph ph-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Method:</label>
                                            <p><code>GET</code></p>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Headers:</label>
                                            <pre class="bg-light p-3 border rounded"><code>X-API-KEY: your_api_key</code></pre>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Parameters:</label>
                                            <ul class="list-group">
                                                <li class="list-group-item"><code>keyword</code> - Search term to match against name, user_code, email, phone_number, tax_code, address</li>
                                                <li class="list-group-item"><code>user_type</code> - Filter by user type (INDIVIDUAL, BUSINESS, PARTNER, SUPPLIER)</li>
                                                <li class="list-group-item"><code>status</code> - Filter by status (ACTIVE, INACTIVE, DELETED)</li>
                                                <li class="list-group-item"><code>page</code> - Page number for pagination (default: 1)</li>
                                                <li class="list-group-item"><code>per_page</code> - Results per page (default: 10)</li>
                                                <li class="list-group-item"><code>include_contacts</code> - Set to true to include contact details for business users</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Example URL:</label>
                                            <pre class="bg-light p-3 border rounded"><code><?php echo esc_url(rest_url('bookorder/v1/search-partners')); ?>?keyword=test&user_type=BUSINESS&page=1&per_page=10&include_contacts=true</code></pre>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="fw-bold">Notes:</label>
                                            <p>All parameters are optional. If no parameters are provided, all partners will be returned (paginated).</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyApiKey() {
    var apiKeyInput = document.getElementById("api-key-display");
    apiKeyInput.select();
    apiKeyInput.setSelectionRange(0, 99999); // For mobile devices
    document.execCommand("copy");
    
    // Show feedback
    var originalBtnContent = event.target.innerHTML;
    event.target.innerHTML = '<i class="ph ph-check"></i>';
    setTimeout(function() {
        event.target.innerHTML = originalBtnContent;
    }, 2000);
}

function copyEndpoint(button) {
    var input = button.previousElementSibling;
    input.select();
    input.setSelectionRange(0, 99999); // For mobile devices
    document.execCommand("copy");

    // Show feedback
    var originalBtnContent = button.innerHTML;
    button.innerHTML = '<i class="ph ph-check"></i>';
    setTimeout(function() {
        button.innerHTML = originalBtnContent;
    }, 2000);
}

// Test Cloudflare Connection
jQuery(document).ready(function($) {
    $('#test-cloudflare-connection').on('click', function() {
        var button = $(this);
        var resultDiv = $('#cloudflare-test-result');
        var apiToken = $('#cloudflare_api_token').val();
        var accountId = $('#cloudflare_account_id').val();

        if (!apiToken || !accountId) {
            resultDiv.html('<div class="alert alert-warning">Vui lòng nhập API Token và Account ID trước.</div>');
            return;
        }

        button.prop('disabled', true).html('<i class="ph ph-spinner ph-spin"></i> Testing...');
        resultDiv.html('<div class="alert alert-info">Đang kiểm tra kết nối...</div>');

        // Save temporarily to options then test
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'test_cloudflare_connection',
                api_token: apiToken,
                account_id: accountId,
                nonce: '<?php echo wp_create_nonce('cloudflare_test_nonce'); ?>'
            },
            success: function(response) {
                button.prop('disabled', false).html('<i class="ph ph-plugs-connected"></i> Test Connection');

                if (response.success) {
                    resultDiv.html('<div class="alert alert-success"><strong>Kết nối thành công!</strong><br>' + response.data.message + '</div>');
                } else {
                    resultDiv.html('<div class="alert alert-danger"><strong>Lỗi kết nối:</strong><br>' + response.data.message + '</div>');
                }
            },
            error: function(jqXHR) {
                button.prop('disabled', false).html('<i class="ph ph-plugs-connected"></i> Test Connection');
                console.error('Test error:', jqXHR.responseText);
                resultDiv.html('<div class="alert alert-danger"><strong>Lỗi:</strong> Không thể kết nối đến server.</div>');
            }
        });
    });
});
</script>

<?php get_footer(); ?>