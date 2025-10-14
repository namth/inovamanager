/**
 * Custom JavaScript functionality for Inova Manager
 */
jQuery(document).ready(function($) {
    /**
     * Update price field when a product is selected from dropdown
     * Works for any select with id="product_catalog_id" that has data-price attributes
     */
    $(document).on('change', '#product_catalog_id', function() {
        var selectedOption = $(this).find('option:selected');
        var price = selectedOption.data('price');
        
        if (price) {
            // Update the price field with the selected product's price
            $('#price').val(price);
        }
    });

    /**
     * Enhanced password visibility toggle functionality
     * Supports multiple toggle button styles and password field configurations
     * Works with .toggle-password class and specific ID-based toggles
     */
    $(document).on('click', '.toggle-password, #togglePassword', function(e) {
        // Prevent default behavior and stop event propagation
        e.preventDefault();
        e.stopPropagation();
        
        var passwordInput, icon;
        
        // Handle specific ID-based toggle (for edit_hosting.php compatibility)
        if ($(this).attr('id') === 'togglePassword') {
            passwordInput = $('#management_password');
            icon = $('#toggleIcon');
        } else {
            // Handle generic .toggle-password class
            passwordInput = $(this).closest('.input-group').find('input');
            if (passwordInput.length === 0) {
                // Alternative: look for sibling input
                passwordInput = $(this).siblings('input');
            }
            icon = $(this).find('i');
        }
        
        if (passwordInput.length && icon.length) {
            // Toggle between password and text type
            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                icon.removeClass('ph-eye').addClass('ph-eye-slash');
            } else {
                passwordInput.attr('type', 'password');
                icon.removeClass('ph-eye-slash').addClass('ph-eye');
            }
        }
    });

    /**
     * Original toggle password functionality (kept for backwards compatibility)
     * Note: This is now handled by the enhanced password toggle above
     */
    // Removed duplicate code - now handled by enhanced toggle above

    /**
     * Update billing cycle based on selected product
     * Works for hosting and other services that have billing cycles
     */
    $(document).on('change', '#product_catalog_id', function() {
        var selectedOption = $(this).find('option:selected');
        var cycle = selectedOption.data('cycle');
        
        // Update billing cycle based on product cycle
        if (cycle && $('#billing_cycle_months').length) {
            var cycleMonths = 1; // Default to 1 month
            if (cycle.toLowerCase().includes('năm') || cycle.toLowerCase().includes('year')) {
                cycleMonths = 12;
            } else if (cycle.toLowerCase().includes('tháng') || cycle.toLowerCase().includes('month')) {
                var match = cycle.match(/\d+/);
                if (match) {
                    cycleMonths = parseInt(match[0]);
                }
            }
            $('#billing_cycle_months').val(cycleMonths);
        }
    });

    /**
     * Password reveal functionality for website credentials
     * This implementation ensures the modal is shown only once and properly cleaned up
     */
    $(document).on('click', '.show-password-btn', function(e) {
        // Prevent any default behavior or event bubbling
        e.preventDefault();
        e.stopPropagation();
        
        // Check if modal already exists and remove it
        const existingModal = document.querySelector('#passwordModal');
        if (existingModal) {
            const modalInstance = bootstrap.Modal.getInstance(existingModal);
            if (modalInstance) modalInstance.dispose();
            existingModal.remove();
        }
        
        const password = $(this).attr('data-password');
        
        // Create a modal for showing the password with a unique ID
        const modal = document.createElement('div');
        modal.classList.add('modal', 'fade');
        modal.id = 'passwordModal';
        modal.setAttribute('tabindex', '-1');
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Thông tin đăng nhập</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="${password}" readonly>
                                <button class="btn btn-secondary copy-btn" type="button">
                                    <i class="ph ph-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        // Copy to clipboard functionality
        const copyBtn = modal.querySelector('.copy-btn');
        copyBtn.addEventListener('click', function() {
            const input = this.previousElementSibling;
            input.select();
            document.execCommand('copy');
            
            this.innerHTML = '<i class="ph ph-check"></i> Đã copy';
            setTimeout(() => {
                this.innerHTML = '<i class="ph ph-copy"></i> Copy';
            }, 2000);
        });
        
        // Remove modal from DOM when hidden
        modal.addEventListener('hidden.bs.modal', function() {
            document.body.removeChild(modal);
        });
    });

    /**
     * Format prices with thousand separators as user types
     */
    $(document).on('input', 'input[name="price"]', function() {
        // Get the raw value without formatting
        var value = $(this).val().replace(/,/g, '');
        
        // Only process if it's a valid number
        if (!isNaN(value) && value !== '') {
            // Add thousand separators when not in focus
            // $(this).val(parseFloat(value).toLocaleString('vi-VN'));
        }
    });

    /**
     * Calculate expiry date based on registration date and billing period
     * Works for both domain registration (years) and hosting registration (months)
     */
    function updateExpiryDate() {
        var registrationDate = $('#registration_date').val();
        var billingCycleMonths = parseInt($('#billing_cycle_months').val()) || 0;
        
        if (registrationDate && billingCycleMonths > 0) {
            // Parse date (format DD/MM/YYYY)
            var dateParts = registrationDate.split('/');
            if (dateParts.length === 3) {
                var day = parseInt(dateParts[0]);
                var month = parseInt(dateParts[1]) - 1; // JS months start from 0
                var year = parseInt(dateParts[2]);
                
                var date = new Date(year, month, day);
                date.setMonth(date.getMonth() + billingCycleMonths);
                
                // Format expiry date
                var expiryDay = ('0' + date.getDate()).slice(-2);
                var expiryMonth = ('0' + (date.getMonth() + 1)).slice(-2);
                var expiryYear = date.getFullYear();
                
                var expiryDate = expiryDay + '/' + expiryMonth + '/' + expiryYear;
                
                // Update expiry date preview if it exists
                if ($('.expiry-date-preview').length) {
                    $('.expiry-date-preview').text('Ngày hết hạn: ' + expiryDate);
                } else {
                    $('#billing_cycle_months').after('<div class="expiry-date-preview text-info mt-1">Ngày hết hạn: ' + expiryDate + '</div>');
                }
            }
        }
    }
    
    // Update expiry date when registration date or billing period changes
    $(document).on('change', '#registration_date, #billing_cycle_months', updateExpiryDate);

    /**
     * Calculate domain expiry date based on registration date and registration period in years
     */
    function updateExpiryDatePreview() {
        var registrationDate = $('#registration_date').val();
        var years = $('#registration_period_years').val();
        
        if (registrationDate) {
            // Parse date (expects DD/MM/YYYY format)
            var parts = registrationDate.split('/');
            var regDate = new Date(parts[2], parts[1] - 1, parts[0]);
            
            // Add years
            regDate.setFullYear(regDate.getFullYear() + parseInt(years));
            
            // Format date
            var day = ('0' + regDate.getDate()).slice(-2);
            var month = ('0' + (regDate.getMonth() + 1)).slice(-2);
            var year = regDate.getFullYear();
            
            // Display preview
            $('.expiry-date-preview').remove();
            $('#registration_period_years').after('<div class="expiry-date-preview text-info mt-1">Ngày hết hạn: ' + day + '/' + month + '/' + year + '</div>');
        }
    }
    
    // Add event listeners for domain registration
    $(document).on('change', '#registration_date, #registration_period_years', updateExpiryDatePreview);
    
    // Initial calculation for domain registration
    if ($('#registration_date').val() && $('#registration_period_years').length) {
        updateExpiryDatePreview();
    }
    
    /**
     * Filter domains, hostings, and maintenance packages based on selected owner
     */
    $(document).on('change', '#owner_user_id', function() {
        var ownerId = $(this).val();
        if (ownerId) {
            // You can implement AJAX calls here to filter domains, hostings and maintenance
            // packages based on the selected owner if needed
            console.log('Owner changed to: ' + ownerId);
        }
    });

    /**
     * Check website status functionality for website forms
     */
    $(document).on('click', '#checkWebsiteStatus', function() {
        var websiteUrl = '';
        
        // Try to get domain name from select
        var domainId = $('#domain_id').val();
        if (domainId) {
            var domainText = $('#domain_id option:selected').text();
            if (domainText && domainText !== '-- Chọn tên miền --') {
                websiteUrl = domainText;
            }
        }
        
        // If no domain selected, try to use the website name
        if (!websiteUrl) {
            websiteUrl = $('#website_name').val();
        }
        
        if (websiteUrl) {
            $('#statusResult').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang kiểm tra...');
            
            $.ajax({
                url: AJAX.ajaxurl,
                type: 'POST',
                data: {
                    action: 'checkWebsiteStatus',
                    url: websiteUrl,
                    security: $('#check_website_status_nonce').val()
                },
                success: function(response) {
                    // Parse the response
                    var obj = JSON.parse(response);
                    $('#statusResult').html('<span class="website-status-dot bg-' + obj.class + '"></span> <span class="badge border-radius-9 btn-inverse-' + obj.class + '">' + obj.message + '</span>');
                },
                error: function() {
                    $('#statusResult').html('<span class="badge bg-danger">Lỗi kết nối</span>');
                }
            });
        } else {
            $('#statusResult').html('<span class="badge border-radius-9 btn-danger">Vui lòng chọn tên miền hoặc nhập tên website</span>');
        }
    });

    /**
     * Auto-detect domain type and populate hidden field for domain forms
     */
    $(document).on('input', '#domain_name', function() {
        let domainName = $(this).val().trim().toLowerCase();
        let domainInfo = '';
        let productId = '';
        
        // Get domain products from data attribute if available
        const domainProducts = window.domainProducts || [];
        
        if (domainName && domainProducts.length > 0) {
            let foundMatch = false;
            
            // Loop through each domain product to find a match
            for (let i = 0; i < domainProducts.length; i++) {
                const product = domainProducts[i];
                const tldName = product.name.toLowerCase();
                
                // Skip if product name doesn't start with dot (it should be like ".com", ".vn", etc.)
                if (!tldName.startsWith('.')) continue;
                
                // Check if domain name ends with this TLD
                if (domainName.endsWith(tldName)) {
                    foundMatch = true;
                    productId = product.id;
                    
                    // Update price field if it exists
                    const price = product.base_price;
                    if ($('#price').length) {
                        $('#price').val(price);
                    }
                    
                    domainInfo = `<span class="text-success">Loại tên miền: <strong>${product.name}</strong> - Giá: ${new Intl.NumberFormat('vi-VN').format(price)} VNĐ</span>`;
                    break;
                }
            }
            
            // Update hidden field and info text
            $('#product_catalog_id').val(productId);
            
            if (!foundMatch) {
                domainInfo = '<span class="text-warning">Không tìm thấy loại tên miền phù hợp. Vui lòng kiểm tra lại.</span>';
            }
        }
        
        $('#domain_type_info').html(domainInfo);
    });

    /**
     * Invoice list page functionality
     */
    
    // Mark as paid modal functionality
    $(document).on('click', '.mark-as-paid', function(e) {
        e.preventDefault();
        var invoiceId = $(this).data('invoice-id');
        var invoiceCode = $(this).data('invoice-code');
        
        $('#paid-invoice-id').val(invoiceId);
        $('#paid-invoice-code').text(invoiceCode);
        
        // Get invoice amount from the row (5th column)
        var amount = $(this).closest('tr').find('td:nth-child(5)').text().trim();
        $('#paid-amount').val(amount.replace(/[^\d]/g, ''));
        
        $('#markAsPaidModal').modal('show');
    });
    
    // Delete invoice modal functionality
    $(document).on('click', '.delete-invoice', function(e) {
        e.preventDefault();
        var invoiceId = $(this).data('invoice-id');
        var invoiceCode = $(this).data('invoice-code');
        
        $('#delete-invoice-id').val(invoiceId);
        $('#delete-invoice-code').text(invoiceCode);
        
        $('#deleteInvoiceModal').modal('show');
    });
    
    // Format paid amount with thousand separators
    $(document).on('blur', '#paid-amount', function() {
        var value = $(this).val().replace(/,/g, '');
        if (!isNaN(value) && value !== '') {
            $(this).val(parseInt(value).toLocaleString('vi-VN').replace(/\./g, ''));
        }
    });

    /**
     * Initialize components when document is ready
     */
    function initComponents() {
        // Any initialization code that should run when the page loads
        
        // Initialize datepickers if they exist
        if($.fn.datepicker) {
            $('.datepicker input').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true
            });
        }
        
        // Initialize Select2 if it exists
        if($.fn.select2) {
            $('.js-example-basic-single').select2();
            $('.js-example-basic-multiple').select2();
        }
    }
    
    // Run initialization
    initComponents();

    /**
     * Universal Bulk Renewal System
     * Handles bulk renewal functionality for all service types (domains, hostings, maintenances, websites)
     */
    
    /**
     * Initialize bulk renewal functionality for a specific service type
     * @param {string} serviceType - The service type (domains, hostings, maintenances, websites)
     * @param {string} serviceName - Display name for the service (tên miền, hosting, gói bảo trì, website)
     * @param {string} invoiceUrl - Base URL for invoice creation
     */
    function initBulkRenewal(serviceType, serviceName, invoiceUrl) {
        var selectAllId = '#select-all-' + serviceType;
        var checkboxClass = '.' + serviceType.slice(0, -1) + '-checkbox'; // Remove 's' and add '-checkbox'
        var bulkButtonId = '#bulk-renew-btn';
        
        // Handle "select all" checkbox
        $(document).on('change', selectAllId, function() {
            $(checkboxClass).prop('checked', $(this).prop('checked'));
            toggleBulkRenewButton(checkboxClass, bulkButtonId);
        });
        
        // Handle individual checkboxes - using event delegation
        $(document).on('change', checkboxClass, function() {
            var totalCheckboxes = $(checkboxClass).length;
            var checkedCheckboxes = $(checkboxClass + ':checked').length;
            
            $(selectAllId).prop('checked', totalCheckboxes === checkedCheckboxes);
            toggleBulkRenewButton(checkboxClass, bulkButtonId);
        });
    }
    
    /**
     * Show/hide bulk renew button based on selected checkboxes
     * @param {string} checkboxClass - CSS class selector for checkboxes
     * @param {string} bulkButtonId - ID selector for bulk button
     */
    function toggleBulkRenewButton(checkboxClass, bulkButtonId) {
        if ($(checkboxClass + ':checked').length > 0) {
            $(bulkButtonId).show();
        } else {
            $(bulkButtonId).hide();
        }
    }
    
    /**
     * Handle bulk renewal for any service type
     * @param {string} serviceType - The service type (domains, hostings, maintenances, websites)
     * @param {string} serviceName - Display name for the service
     * @param {string} invoiceUrl - Base URL for invoice creation
     */
    window.handleBulkRenewal = function(serviceType, serviceName, invoiceUrl) {
        var checkboxClass = '.' + serviceType.slice(0, -1) + '-checkbox'; // Remove 's' and add '-checkbox'
        var checkedBoxes = $(checkboxClass + ':checked');
        
        if (checkedBoxes.length === 0) {
            alert('Vui lòng chọn ít nhất một ' + serviceName + ' để gia hạn.');
            return;
        }
        
        // Check if all selected items belong to the same owner
        var ownerIds = checkedBoxes.map(function() {
            return $(this).data('owner-id');
        }).get();
        
        var uniqueOwners = [...new Set(ownerIds)];
        if (uniqueOwners.length > 1) {
            alert('Vui lòng chỉ chọn ' + serviceName + ' của cùng một khách hàng để gia hạn.');
            return;
        }
        
        // Get item IDs
        var itemIds = checkedBoxes.map(function() {
            return $(this).val();
        }).get().join(',');
        
        // Redirect to add invoice page with bulk parameter
        var paramName = 'bulk_' + serviceType;
        window.location.href = invoiceUrl + '?' + paramName + '=' + itemIds;
    };
    
    // Auto-initialize bulk renewal for common service types when page loads
    $(document).ready(function() {
        var invoiceUrl = window.location.origin + '/them-moi-hoa-don/';
        
        // Check which service list page we're on and initialize accordingly
        if ($('#select-all-domains').length) {
            initBulkRenewal('domains', 'tên miền', invoiceUrl);
        }
        if ($('#select-all-hostings').length) {
            initBulkRenewal('hostings', 'hosting', invoiceUrl);
        }
        if ($('#select-all-maintenances').length) {
            initBulkRenewal('maintenances', 'gói bảo trì', invoiceUrl);
        }
        if ($('#select-all-websites').length) {
            initBulkRenewal('websites', 'website', invoiceUrl);
        }
    });
    
    /**
     * Maintenance Package Specific Functionality
     */
    
    // Calculate actual revenue when price or discount changes for maintenance packages
    $(document).on('change', '#price, #discount_amount', function() {
        var price = parseFloat($('#price').val()) || 0;
        var discount = parseFloat($('#discount_amount').val()) || 0;
        var actual = price - discount;
        
        // You could display this in a read-only field if needed
        console.log('Actual revenue: ' + actual);
    });
    
    // Preview expiry date when registration date or total months changes for maintenance packages
    $(document).on('change', '#registration_date, #total_months_registered', function() {
        var registrationDate = $('#registration_date').val();
        var totalMonths = parseInt($('#total_months_registered').val()) || 0;
        
        if (registrationDate && totalMonths) {
            // Parse date (format DD/MM/YYYY)
            var dateParts = registrationDate.split('/');
            if (dateParts.length === 3) {
                var day = parseInt(dateParts[0]);
                var month = parseInt(dateParts[1]) - 1; // JS months start from 0
                var year = parseInt(dateParts[2]);
                
                var date = new Date(year, month, day);
                date.setMonth(date.getMonth() + totalMonths);
                
                // Format expiry date
                var expiryDay = ('0' + date.getDate()).slice(-2);
                var expiryMonth = ('0' + (date.getMonth() + 1)).slice(-2);
                var expiryYear = date.getFullYear();
                var formattedDate = expiryDay + '/' + expiryMonth + '/' + expiryYear;
                
                // Show expiry date preview
                $('.expiry-date-preview').remove();
                $('#total_months_registered').after('<div class="expiry-date-preview text-info mt-1">Ngày hết hạn dự kiến: ' + formattedDate + '</div>');
            }
        }
    });
    
    /**
     * Hosting Code Preview Functionality
     * Shows preview of auto-generated hosting code when product and owner are selected
     */
    $(document).on('change', '#product_catalog_id, [name="owner_user_id"]', function() {
        var productSelect = $('#product_catalog_id');
        var ownerSelect = $('[name="owner_user_id"]');
        var hostingCodeInput = $('#hosting_code');
        
        if (productSelect.length && ownerSelect.length && hostingCodeInput.length) {
            var selectedProduct = productSelect.find('option:selected');
            var selectedOwner = ownerSelect.find('option:selected');
            var productCode = selectedProduct.data('code');
            var ownerText = selectedOwner.text();
            
            // Extract user code from owner option text (format: "CODE - Name")
            var userCode = '';
            if (ownerText && ownerText.includes(' - ')) {
                userCode = ownerText.split(' - ')[0];
            }
            
            // Remove existing preview
            $('.hosting-code-preview').remove();
            
            if (productCode && userCode && hostingCodeInput.val() === '') {
                // Create preview for new hosting (ID will be auto-generated)
                var previewCode = productCode + '[ID]' + userCode;
                hostingCodeInput.after('<div class="hosting-code-preview text-info mt-1"><small>Mã sẽ được tạo: ' + previewCode + ' (ID sẽ được tự động thay thế)</small></div>');
            }
        }
    });
    
    /**
     * Auto-submit search forms on Enter key press
     * Works for any search input with name="search"
     */
    $(document).on('keypress', 'input[name="search"]', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            $(this).closest('form').submit();
        }
    });
});