/**
 * Custom JavaScript functionality for Inova Manager
 * 
 * ============================================
 * Global Features
 * ============================================
 * 
 * 1. Password Toggle (.toggle-password)
 *    - Unified handler for all password field toggles
 *    - Works with all password input fields across the application
 *    - Button must be inside .input-group with password input
 *    - HTML Structure:
 *      <div class="input-group">
 *          <input type="password" class="form-control" ...>
 *          <button class="btn btn-secondary toggle-password" type="button">
 *              <i class="ph ph-eye"></i>
 *          </button>
 *      </div>
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
     * Universal password visibility toggle functionality
     * Handles all password field toggle buttons across the application
     * Files using this: addnew_website.php, edit_website.php, addnew_website_simple.php,
     *                   edit_website_simple.php, addnew_domain.php, edit_domain.php,
     *                   addnew_hosting.php, edit_hosting.php, detail_website.php,
     *                   detail_domain.php, detail_hosting.php
     */
    $(document).on('click', '.toggle-password', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Find password input - always inside .input-group with button
        var $inputGroup = $(this).closest('.input-group');
        var $passwordInput = $inputGroup.find('input[type="password"], input[type="text"]');
        var $icon = $(this).find('i');
        
        // Ensure we found the elements
        if ($passwordInput.length && $icon.length) {
            // Toggle password visibility
            if ($passwordInput.attr('type') === 'password') {
                $passwordInput.attr('type', 'text');
                $icon.removeClass('ph-eye').addClass('ph-eye-slash');
            } else {
                $passwordInput.attr('type', 'password');
                $icon.removeClass('ph-eye-slash').addClass('ph-eye');
            }
        }
        
        return false;
    });

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

                    // Update price field if it exists (use register_price for new domain registration)
                    const registerPrice = product.register_price || 0;
                    const renewPrice = product.renew_price || 0;
                    if ($('#price').length) {
                        $('#price').val(registerPrice);
                    }

                    domainInfo = `<span class="text-success">Loại tên miền: <strong>${product.name}</strong> - Giá đăng ký: ${new Intl.NumberFormat('vi-VN').format(registerPrice)} VNĐ | Giá gia hạn: ${new Intl.NumberFormat('vi-VN').format(renewPrice)} VNĐ</span>`;
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

    /**
     * Add to Cart functionality
     * Handle adding services (domain, hosting, maintenance) to cart
     */
    $(document).on('click', '.add-to-cart-btn', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var serviceType = $btn.data('service-type');
        var serviceId = $btn.data('service-id');
        var originalIcon = $btn.find('i').attr('class');

        // Disable button and show loading
        $btn.prop('disabled', true);
        $btn.find('i').attr('class', 'ph ph-circle-notch ph-spin text-info fa-150p');

        $.ajax({
            url: AJAX.ajaxurl,
            type: 'POST',
            data: {
                action: 'add_to_cart',
                service_type: serviceType,
                service_id: serviceId,
                quantity: 1
            },
            success: function(response) {
                var data = JSON.parse(response);

                if (data.status) {
                    // Show success message
                    showToast(data.message, 'success');

                    // Update cart count badge
                    updateCartCount(data.cart_count);

                    // Change icon to checkmark temporarily
                    $btn.find('i').attr('class', 'ph ph-check-circle text-success fa-150p');

                    // Restore original icon after 2 seconds
                    setTimeout(function() {
                        $btn.find('i').attr('class', originalIcon);
                        $btn.prop('disabled', false);
                    }, 2000);
                } else {
                    showToast(data.message, 'error');
                    $btn.find('i').attr('class', originalIcon);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                showToast('Có lỗi xảy ra khi thêm vào giỏ hàng', 'error');
                $btn.find('i').attr('class', originalIcon);
                $btn.prop('disabled', false);
            }
        });
    });

    /**
     * Update cart count badge in header
     */
    function updateCartCount(count) {
        var $cartBadge = $('#cart-count-badge');

        if (count > 0) {
            if ($cartBadge.length) {
                $cartBadge.text(count).show();
            } else {
                // Create badge if it doesn't exist
                $('#cart-icon').append('<span class="badge badge-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="cart-count-badge">' + count + '</span>');
            }
        } else {
            $cartBadge.hide();
        }
    }

    /**
     * Show toast notification
     */
    function showToast(message, type) {
        // Create toast element
        var bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
        var icon = type === 'success' ? 'ph-check-circle' : 'ph-warning-circle';

        var toast = $('<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">' +
            '<div class="toast align-items-center text-white ' + bgClass + ' border-0 show" role="alert">' +
                '<div class="d-flex">' +
                    '<div class="toast-body">' +
                        '<i class="ph ' + icon + ' me-2"></i>' + message +
                    '</div>' +
                    '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
                '</div>' +
            '</div>' +
        '</div>');

        $('body').append(toast);

        // Auto remove after 3 seconds
        setTimeout(function() {
            toast.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Load cart count on page load
     */
    $.ajax({
        url: AJAX.ajaxurl,
        type: 'POST',
        data: {
            action: 'get_cart_count'
        },
        success: function(response) {
            var data = JSON.parse(response);
            if (data.status && data.cart_count > 0) {
                updateCartCount(data.cart_count);
            }
        }
    });

    /**
     * ========================================
     * INVOICE PAGE FUNCTIONALITY
     * ========================================
     */

    // Check if we're on add_invoice page
    if ($('#invoice-form').length > 0) {

        /**
         * Format prices with thousand separators (Vietnamese format: 1.000.000)
         */
        function formatPrice(price) {
            return parseInt(price).toLocaleString('vi-VN');
        }

        /**
         * Calculate line total when unit price or quantity changes
         */
        $(document).on('input', '.unit-price, .quantity', function() {
            console.log('=== Invoice: Quantity/Price Changed ===');
            var $row = $(this).closest('.invoice-item');

            var unitPriceRaw = $row.find('.unit-price').val();
            var quantityRaw = $row.find('.quantity').val();

            console.log('Unit Price Raw:', unitPriceRaw);
            console.log('Quantity Raw:', quantityRaw);

            var unitPrice = parseInt(unitPriceRaw.replace(/\./g, '').replace(/,/g, '')) || 0;
            var quantity = parseInt(quantityRaw) || 0;

            console.log('Unit Price Parsed:', unitPrice);
            console.log('Quantity Parsed:', quantity);

            var total = unitPrice * quantity;
            console.log('Total Calculated:', total);

            // Store as plain number for calculation
            $row.find('.item-total').val(total.toString());
            console.log('Item Total Set To:', total.toString());

            // Update VAT display for this row
            var serviceType = $row.find('input[name="service_type[]"]').val();
            if (serviceType) {
                var vatRate = getVATRate(serviceType);
                var vatAmount = calculateVATAmount(total, vatRate);

                // Update vat_rate hidden input so it gets sent to server
                $row.find('input[name="vat_rate[]"]').val(vatRate);

                var $vatDisplay = $row.find('.item-vat-display');
                if ($vatDisplay.length > 0) {
                    if (vatAmount > 0) {
                        $vatDisplay.html(
                            '<span class="vat-amount">' + formatPrice(vatAmount) + ' VNĐ</span>' +
                            '<br><small class="text-muted vat-rate">(' + vatRate.toFixed(1) + '%)</small>'
                        );
                    } else {
                        $vatDisplay.html('<span class="text-muted">-</span>');
                    }
                }
                console.log('VAT Updated - Service Type:', serviceType, 'Rate:', vatRate + '%', 'Amount:', vatAmount);
            }

            updateInvoiceTotals();
        });

        /**
         * Format unit price on blur
         */
        $(document).on('blur', '.unit-price', function() {
            var value = $(this).val().replace(/\./g, '').replace(/,/g, '');
            if (!isNaN(value) && value !== '') {
                $(this).val(parseInt(value).toLocaleString('vi-VN'));
            }
        });

        /**
         * Remove invoice item
         */
        $(document).on('click', '.remove-item', function() {
            $(this).closest('.invoice-item').remove();
            updateInvoiceTotals();
        });

        /**
         * Get VAT rate for a service type
         */
        function getVATRate(serviceType) {
            if (typeof AJAX !== 'undefined' && typeof AJAX.vat_rates !== 'undefined') {
                // Capitalize first letter to match the key format
                var key = serviceType.charAt(0).toUpperCase() + serviceType.slice(1).toLowerCase();
                return parseFloat(AJAX.vat_rates[key]) || 0;
            }
            return 0;
        }

        /**
         * Calculate VAT amount based on item total and VAT rate
         */
        function calculateVATAmount(itemTotal, vatRate) {
            return Math.round(itemTotal * vatRate / 100);
        }

        /**
         * Update invoice totals
         */
        function updateInvoiceTotals() {
            console.log('=== Updating Invoice Totals ===');
            var subTotal = 0;
            var totalVAT = 0;

            // Sum all item totals and calculate VAT
            $('.invoice-item').each(function(index) {
                var $row = $(this);
                var itemTotal = $row.find('.item-total').val().replace(/\./g, '').replace(/,/g, '');
                var parsed = parseInt(itemTotal) || 0;
                console.log('Item ' + index + ' Total:', itemTotal, '-> Parsed:', parsed);
                subTotal += parsed;

                // Get service type and calculate VAT for this item
                var serviceType = $row.find('input[name="service_type[]"]').val();
                if (serviceType) {
                    var vatRate = getVATRate(serviceType);
                    var vatAmount = calculateVATAmount(parsed, vatRate);
                    console.log('Item ' + index + ' Service Type:', serviceType, 'VAT Rate:', vatRate + '%', 'VAT Amount:', vatAmount);
                    totalVAT += vatAmount;
                }
            });

            console.log('SubTotal:', subTotal, 'Total VAT:', totalVAT);

            // Get discount
            var discountRaw = $('#discount-amount').val() || '0';
            var discount = parseInt(discountRaw.replace(/\./g, '').replace(/,/g, '')) || 0;

            // Auto-calculate VAT and update the tax field
            $('#tax-amount').val(totalVAT);

            console.log('Discount:', discount, 'VAT:', totalVAT);

            // Calculate total
            var total = subTotal - discount + totalVAT;
            console.log('Total:', total);

            // Update summary display
            $('#summary-subtotal').text(formatPrice(subTotal) + ' VNĐ');
            $('#summary-total').text(formatPrice(total) + ' VNĐ');

            // Update hidden inputs for form submission
            $('#sub-total-input').val(subTotal);
            $('#total-amount-input').val(total);
        }

        /**
         * Update totals when discount changes
         * Note: VAT is auto-calculated, so we only listen to discount changes
         */
        $('#discount-amount').on('input', function() {
            updateInvoiceTotals();
        });

        /**
         * Toggle payment method info
         */
        $('input[name="payment_method"]').on('change', function() {
            if ($(this).val() === 'bank_transfer') {
                $('#bank-info').removeClass('d-none');
                $('#qr-info').addClass('d-none');
            } else {
                $('#bank-info').addClass('d-none');
                $('#qr-info').removeClass('d-none');
            }
        });

        /**
         * Initial calculation on page load
         */
        updateInvoiceTotals();
        console.log('Invoice page loaded, initial totals calculated');
    }

    /**
     * ===================================================================
     * DOMAIN RENEWAL - Single Domain (+1 Year)
     * ===================================================================
     * File: domain_list.php
     *
     * Handle quick renewal for individual domains
     * - Click renew icon in actions column
     * - Adds 1 year to current expiry date
     * - Shows confirmation with old/new dates
     * - Auto reloads page after success
     */
    $(document).on('click', '.renew-domain-btn', function() {
        const $button = $(this);
        const domainId = $button.data('domain-id');
        const domainName = $button.data('domain-name');
        const expiryDate = $button.data('expiry-date');

        // Confirm renewal
        if (!confirm(`Bạn có chắc muốn gia hạn tên miền "${domainName}" thêm 1 năm?\n\nNgày hết hạn hiện tại: ${formatDate(expiryDate)}\nNgày hết hạn mới: ${formatDate(addYears(expiryDate, 1))}`)) {
            return;
        }

        // Show loading state
        const originalIcon = $button.find('i').attr('class');
        $button.prop('disabled', true);
        $button.find('i').attr('class', 'ph ph-spinner ph-spin text-success btn-icon-prepend fa-150p');

        // Send AJAX request
        $.ajax({
            url: AJAX.ajaxurl,
            type: 'POST',
            data: {
                action: 'renew_domain_one_year',
                domain_id: domainId,
                nonce: $button.closest('table').data('renew-nonce') || ''
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert('✅ Đã gia hạn thành công!\n\n' +
                          'Tên miền: ' + domainName + '\n' +
                          'Ngày hết hạn mới: ' + formatDate(response.data.new_expiry_date));

                    // Reload page to show updated data
                    location.reload();
                } else {
                    // Show error message
                    alert('❌ Lỗi: ' + (response.data.message || 'Không thể gia hạn tên miền'));

                    // Reset button
                    $button.prop('disabled', false);
                    $button.find('i').attr('class', originalIcon);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', textStatus, errorThrown);
                alert('❌ Lỗi kết nối đến server. Vui lòng thử lại.');

                // Reset button
                $button.prop('disabled', false);
                $button.find('i').attr('class', originalIcon);
            }
        });
    });

    /**
     * Helper function to format date (DD/MM/YYYY)
     * Used by domain renewal functionality
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        const day = ('0' + date.getDate()).slice(-2);
        const month = ('0' + (date.getMonth() + 1)).slice(-2);
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    }

    /**
     * Helper function to add years to a date
     * Used by domain renewal functionality
     */
    function addYears(dateString, years) {
        const date = new Date(dateString);
        date.setFullYear(date.getFullYear() + years);
        return date.toISOString().split('T')[0];
    }

    /**
     * ===================================================================
     * DELETE WEBSITE - User/Admin
     * ===================================================================
     * File: website_list.php
     * 
     * Handle delete website for user who created it or admin
     * - Only creator or admin can delete
     * - Cannot delete if has linked services (domain, hosting, maintenance)
     */
    $(document).on('click', '.delete-website-btn', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const websiteId = $button.data('website-id');
        const websiteName = $button.data('website-name');
        
        // Confirm deletion
        if (!confirm(`Bạn có chắc muốn xóa website "${websiteName}"?\n\nNhư lưu ý: Không thể xóa website nếu nó có liên kết với tên miền, hosting hoặc gói bảo trì.`)) {
            return;
        }
        
        // Show loading state
        const originalIcon = $button.find('i').attr('class');
        $button.prop('disabled', true);
        $button.find('i').attr('class', 'ph ph-spinner ph-spin text-danger btn-icon-prepend fa-150p');
        
        // Send AJAX request
        $.ajax({
            url: AJAX.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_website_user',
                website_id: websiteId
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert('✅ Đã xóa website thành công!');
                    
                    // Reload page to show updated list
                    location.reload();
                } else {
                    // Show error message
                    alert('❌ Lỗi: ' + (response.data.message || 'Không thể xóa website'));
                    
                    // Reset button
                    $button.prop('disabled', false);
                    $button.find('i').attr('class', originalIcon);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', textStatus, errorThrown);
                alert('❌ Lỗi kết nối đến server. Vui lòng thử lại.');
                
                // Reset button
                $button.prop('disabled', false);
                $button.find('i').attr('class', originalIcon);
            }
        });
    });
    });