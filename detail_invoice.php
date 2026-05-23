<?php
/* 
    Template Name: Invoice Detail
    Purpose: Display detailed invoice information with management capabilities
*/

global $wpdb;
$invoice_table = $wpdb->prefix . 'im_invoices';
$invoice_items_table = $wpdb->prefix . 'im_invoice_items';
$users_table = $wpdb->prefix . 'im_users';
$service_table = $wpdb->prefix . 'im_website_services';

// Get invoice ID from URL parameter
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
$auto_open_payment = isset($_GET['action']) && $_GET['action'] === 'payment';

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['action_nonce'], 'invoice_action')) {
    // Debug logging
    error_log('POST request received with action: ' . ($_POST['action'] ?? 'none'));
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_item':
                $item_id = intval($_POST['item_id']);
                
                try {
                    // Start transaction
                    $wpdb->query('START TRANSACTION');
                    
                    // Get invoice requires_vat_invoice setting
                    $invoice_record = $wpdb->get_row($wpdb->prepare(
                        "SELECT requires_vat_invoice FROM $invoice_table WHERE id = %d",
                        $invoice_id
                    ));
                    
                    if (!$invoice_record) {
                        throw new Exception('Không tìm thấy hóa đơn');
                    }
                    
                    // Get the item to delete
                    $item_to_delete = $wpdb->get_row($wpdb->prepare("
                        SELECT * FROM $invoice_items_table WHERE id = %d AND invoice_id = %d
                    ", $item_id, $invoice_id));
                    
                    if (!$item_to_delete) {
                        throw new Exception('Không tìm thấy item cần xóa');
                    }
                    
                    // Delete the item
                    $delete_result = $wpdb->delete($invoice_items_table, array('id' => $item_id));
                    
                    if ($delete_result === false) {
                        throw new Exception('Lỗi khi xóa item khỏi hóa đơn');
                    }
                    
                    // Recalculate invoice totals from remaining items
                    $remaining_items = $wpdb->get_results($wpdb->prepare("
                        SELECT ii.item_total, ii.vat_rate, ii.service_type, ii.service_id
                        FROM $invoice_items_table ii
                        WHERE ii.invoice_id = %d
                        ORDER BY ii.id
                    ", $invoice_id));
                    
                    // Calculate new totals
                    $new_sub_total = 0;
                    $new_tax_amount = 0;
                    $new_discount_total = 0;
                    $has_vat = $invoice_record->requires_vat_invoice;
                    
                    // Sum up all item totals and calculate VAT per item (some items may have VAT, others may not)
                    $hostings_table = $wpdb->prefix . 'im_hostings';
                    $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
                    
                    // First pass: calculate subtotal and tax
                     foreach ($remaining_items as $item) {
                         $item_total = floatval($item->item_total);
                         $new_sub_total += $item_total;
                         
                         // Calculate VAT for this item using VAT rate from item
                         if ($has_vat) {
                             $item_vat_rate = floatval($item->vat_rate ?? 0);
                             $item_tax = ($item_total * $item_vat_rate) / 100;
                             $new_tax_amount += $item_tax;
                         }
                     }
                    
                    // Second pass: calculate discount from services
                    foreach ($remaining_items as $item) {
                        $discount_amount = 0;
                        if ($item->service_type === 'hosting') {
                            $service = $wpdb->get_row($wpdb->prepare(
                                "SELECT discount_amount FROM $hostings_table WHERE id = %d",
                                $item->service_id
                            ));
                            if ($service) {
                                $discount_amount = floatval($service->discount_amount ?? 0);
                            }
                        } elseif ($item->service_type === 'maintenance') {
                            $service = $wpdb->get_row($wpdb->prepare(
                                "SELECT discount_amount FROM $maintenance_table WHERE id = %d",
                                $item->service_id
                            ));
                            if ($service) {
                                $discount_amount = floatval($service->discount_amount ?? 0);
                            }
                        }
                        
                        $new_discount_total += $discount_amount;
                    }
                    
                    // Calculate final total: subtotal + tax - discount
                    $new_total_amount = $new_sub_total + $new_tax_amount - $new_discount_total;
                    
                    // Update invoice with new totals
                    $update_result = $wpdb->update(
                        $invoice_table,
                        array(
                            'sub_total' => $new_sub_total,
                            'tax_amount' => $new_tax_amount,
                            'total_amount' => $new_total_amount,
                            'discount_total' => $new_discount_total,
                            'updated_at' => current_time('mysql')
                        ),
                        array('id' => $invoice_id)
                    );
                    
                    if ($update_result === false) {
                        throw new Exception('Lỗi khi cập nhật tổng tiền hóa đơn');
                    }
                    
                    // Commit transaction
                    $wpdb->query('COMMIT');
                    
                    // Reload invoice to get updated values for display
                    $invoice = $wpdb->get_row($wpdb->prepare("
                        SELECT 
                            i.*,
                            u.name AS customer_name,
                            u.user_code AS customer_code,
                            u.email AS customer_email,
                            u.tax_code,
                            u.address AS customer_address,
                            u.phone_number AS customer_phone,
                            u.requires_vat_invoice
                        FROM 
                            $invoice_table i
                        LEFT JOIN 
                            $users_table u ON i.user_id = u.id
                        WHERE 
                            i.id = %d
                    ", $invoice_id));
                    
                    $success_message = 'Đã xóa item khỏi hóa đơn. Tổng tiền đã được tính lại.';
                    
                } catch (Exception $e) {
                    $wpdb->query('ROLLBACK');
                    $error_message = 'Lỗi: ' . $e->getMessage();
                }
                break;
                
            case 'edit_invoice_settings':
                $requires_vat = isset($_POST['requires_vat_invoice']) ? 1 : 0;
                $payment_method = sanitize_text_field($_POST['payment_method']);
                
                // Update basic settings
                $wpdb->update($invoice_table, array(
                    'requires_vat_invoice' => $requires_vat,
                    'payment_method' => $payment_method,
                    'updated_at' => current_time('mysql')
                ), array('id' => $invoice_id));
                
                // Recalculate totals because VAT setting might have changed
                try {
                    $remaining_items = $wpdb->get_results($wpdb->prepare("
                        SELECT ii.item_total, ii.vat_rate, ii.service_type, ii.service_id
                        FROM $invoice_items_table ii
                        WHERE ii.invoice_id = %d
                    ", $invoice_id));
                    
                    $new_sub_total = 0;
                    $new_tax_amount = 0;
                    $new_discount_total = 0;
                    
                    $hostings_table = $wpdb->prefix . 'im_hostings';
                    $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
                    
                    foreach ($remaining_items as $item) {
                        $item_total = floatval($item->item_total);
                        $new_sub_total += $item_total;
                        
                        if ($requires_vat) {
                            $item_vat_rate = floatval($item->vat_rate ?? 0);
                            $new_tax_amount += ($item_total * $item_vat_rate) / 100;
                        }
                        
                        // Recalculate discount
                        $discount_amount = 0;
                        if ($item->service_type === 'hosting') {
                            $discount_amount = floatval($wpdb->get_var($wpdb->prepare("SELECT discount_amount FROM $hostings_table WHERE id = %d", $item->service_id)) ?? 0);
                        } elseif ($item->service_type === 'maintenance') {
                            $discount_amount = floatval($wpdb->get_var($wpdb->prepare("SELECT discount_amount FROM $maintenance_table WHERE id = %d", $item->service_id)) ?? 0);
                        }
                        $new_discount_total += $discount_amount;
                    }
                    
                    $new_total_amount = $new_sub_total + $new_tax_amount - $new_discount_total;
                    
                    $wpdb->update($invoice_table, array(
                        'sub_total' => $new_sub_total,
                        'tax_amount' => $new_tax_amount,
                        'total_amount' => $new_total_amount,
                        'discount_total' => $new_discount_total
                    ), array('id' => $invoice_id));
                    
                    $success_message = 'Đã cập nhật cấu hình hóa đơn thành công.';
                } catch (Exception $e) {
                    $error_message = 'Lỗi khi tính toán lại: ' . $e->getMessage();
                }
                break;
                
            case 'mark_as_paid':
                error_log('Processing mark_as_paid for invoice ID: ' . $invoice_id);
                $paid_amount = floatval($_POST['paid_amount']);
                $payment_date = sanitize_text_field($_POST['payment_date']);
                $payment_notes = sanitize_textarea_field($_POST['payment_notes']);
                $payment_method = sanitize_text_field($_POST['payment_method']);

                // Start transaction to ensure data consistency
                $wpdb->query('START TRANSACTION');

                $payment_notes = $payment_notes?"\n\nGhi chú thanh toán: " . $payment_notes:"";

                try {
                    // Check if invoice requires VAT
                    $requires_vat = intval($wpdb->get_var($wpdb->prepare(
                        "SELECT requires_vat_invoice FROM $invoice_table WHERE id = %d",
                        $invoice_id
                    )));
                    $new_status = ($requires_vat === 1) ? 'pending_vat' : 'paid';

                    // Update invoice status
                    $invoice_result = $wpdb->update(
                        $invoice_table,
                        array(
                            'paid_amount' => $paid_amount,
                            'payment_date' => $payment_date,
                            'payment_method' => $payment_method,
                            'notes' => $wpdb->get_var($wpdb->prepare("SELECT notes FROM $invoice_table WHERE id = %d", $invoice_id)) . $payment_notes,
                            'status' => $new_status,
                            'updated_at' => current_time('mysql')
                        ),
                        array('id' => $invoice_id)
                    );

                    if ($invoice_result === false) {
                        throw new Exception('Failed to update invoice status');
                    }

                    // Define table names for different service types
                    $domains_table = $wpdb->prefix . 'im_domains';
                    $hostings_table = $wpdb->prefix . 'im_hostings';
                    $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';

                    // Get all invoice items with renewal information
                     $invoice_items = $wpdb->get_results($wpdb->prepare("
                         SELECT
                             ii.service_type,
                             ii.service_id,
                             ii.end_date
                         FROM $invoice_items_table ii
                         WHERE ii.invoice_id = %d
                         AND ii.service_id IS NOT NULL
                         AND ii.end_date IS NOT NULL
                     ", $invoice_id));

                     // Update expiry_date for each service/product based on service_type
                     foreach ($invoice_items as $item) {
                         $update_result = false;

                         switch ($item->service_type) {
                             case 'domain':
                                 // Check current status - if NEW, only change to ACTIVE, don't add time
                                 $current_domain = $wpdb->get_row($wpdb->prepare(
                                     "SELECT status FROM $domains_table WHERE id = %d",
                                     $item->service_id
                                 ));
                                 
                                 if ($current_domain && $current_domain->status === 'NEW') {
                                     // For NEW status: just activate without changing expiry date
                                     $update_result = $wpdb->update(
                                         $domains_table,
                                         array(
                                             'status' => 'ACTIVE',
                                             'updated_at' => current_time('mysql')
                                         ),
                                         array('id' => $item->service_id)
                                     );
                                     error_log("Domain ID {$item->service_id} status changed from NEW to ACTIVE (no time added)");
                                 } else {
                                     // For other statuses: update expiry_date (add time)
                                     $update_result = $wpdb->update(
                                         $domains_table,
                                         array(
                                             'expiry_date' => $item->end_date,
                                             'updated_at' => current_time('mysql')
                                         ),
                                         array('id' => $item->service_id)
                                     );
                                     error_log("Updated domain ID {$item->service_id} expiry_date to {$item->end_date}");
                                 }
                                 break;

                             case 'hosting':
                                 // Check current status - if NEW, only change to ACTIVE, don't add time
                                 $current_hosting = $wpdb->get_row($wpdb->prepare(
                                     "SELECT status FROM $hostings_table WHERE id = %d",
                                     $item->service_id
                                 ));
                                 
                                 if ($current_hosting && $current_hosting->status === 'NEW') {
                                     // For NEW status: just activate without changing expiry date
                                     $update_result = $wpdb->update(
                                         $hostings_table,
                                         array(
                                             'status' => 'ACTIVE',
                                             'updated_at' => current_time('mysql')
                                         ),
                                         array('id' => $item->service_id)
                                     );
                                     error_log("Hosting ID {$item->service_id} status changed from NEW to ACTIVE (no time added)");
                                 } else {
                                     // For other statuses: update expiry_date (add time)
                                     $update_result = $wpdb->update(
                                         $hostings_table,
                                         array(
                                             'expiry_date' => $item->end_date,
                                             'updated_at' => current_time('mysql')
                                         ),
                                         array('id' => $item->service_id)
                                     );
                                     error_log("Updated hosting ID {$item->service_id} expiry_date to {$item->end_date}");
                                 }
                                 break;

                             case 'maintenance':
                                 // Check current status - if NEW, only change to ACTIVE, don't add time
                                 $current_maintenance = $wpdb->get_row($wpdb->prepare(
                                     "SELECT status FROM $maintenance_table WHERE id = %d",
                                     $item->service_id
                                 ));
                                 
                                 if ($current_maintenance && $current_maintenance->status === 'NEW') {
                                     // For NEW status: just activate without changing expiry date
                                     $update_result = $wpdb->update(
                                         $maintenance_table,
                                         array(
                                             'status' => 'ACTIVE',
                                             'updated_at' => current_time('mysql')
                                         ),
                                         array('id' => $item->service_id)
                                     );
                                     error_log("Maintenance package ID {$item->service_id} status changed from NEW to ACTIVE (no time added)");
                                 } else {
                                     // For other statuses: update expiry_date (add time)
                                     $update_result = $wpdb->update(
                                         $maintenance_table,
                                         array(
                                             'expiry_date' => $item->end_date,
                                             'updated_at' => current_time('mysql')
                                         ),
                                         array('id' => $item->service_id)
                                     );
                                     error_log("Updated maintenance package ID {$item->service_id} expiry_date to {$item->end_date}");
                                 }
                                 break;

                             case 'website_service':
                                 // Website services don't have expiry_date, skip
                                 $update_result = true;
                                 break;
                         }

                         if ($update_result === false) {
                             throw new Exception("Failed to update expiry_date for {$item->service_type} ID: {$item->service_id}");
                         }
                     }

                    // Get related website services from invoice items
                    $related_services = $wpdb->get_results($wpdb->prepare("
                        SELECT DISTINCT ii.service_id
                        FROM $invoice_items_table ii
                        WHERE ii.invoice_id = %d
                        AND ii.service_type = 'website_service'
                        AND ii.service_id IS NOT NULL
                    ", $invoice_id));

                    // Update service status to in_progress for each related service
                    foreach ($related_services as $service_item) {
                        // Check if service is currently approved before updating
                        $current_service = $wpdb->get_row($wpdb->prepare("
                            SELECT status FROM $service_table WHERE id = %d
                        ", $service_item->service_id));

                        if ($current_service && $current_service->status === 'APPROVED') {
                            $service_result = $wpdb->update(
                                $service_table,
                                array(
                                    'status' => 'IN_PROGRESS',
                                    'start_date' => date('Y-m-d', strtotime($payment_date)),
                                    'updated_at' => current_time('mysql')
                                ),
                                array('id' => $service_item->service_id)
                            );

                            if ($service_result === false) {
                                throw new Exception('Failed to update service status for service ID: ' . $service_item->service_id);
                            }
                        }
                    }

                    // Update commission status when invoice is paid (Phase 2 integration)
                    update_commissions_on_invoice_paid($invoice_id);

                    $wpdb->query('COMMIT');
                    if ($new_status === 'pending_vat') {
                        $success_message = 'Hóa đơn VAT đã được ghi nhận thanh toán và chuyển sang trạng thái "Chờ xuất hóa đơn". Dịch vụ đã được gia hạn.';
                    } else {
                        $success_message = 'Hóa đơn đã được đánh dấu là đã thanh toán. Ngày hết hạn của các sản phẩm/dịch vụ đã được cập nhật.';
                    }

                } catch (Exception $e) {
                    $wpdb->query('ROLLBACK');
                    $error_message = 'Có lỗi xảy ra khi cập nhật: ' . $e->getMessage();
                }
                break;

            case 'mark_invoice_exported':
                error_log('Processing mark_invoice_exported for invoice ID: ' . $invoice_id);
                $wpdb->query('START TRANSACTION');
                try {
                    $invoice_result = $wpdb->update(
                        $invoice_table,
                        array(
                            'status' => 'paid',
                            'updated_at' => current_time('mysql')
                        ),
                        array('id' => $invoice_id)
                    );

                    if ($invoice_result === false) {
                        throw new Exception('Failed to update invoice status to paid');
                    }

                    $wpdb->query('COMMIT');
                    $success_message = 'Đã cập nhật trạng thái hóa đơn thành công sang Đã thanh toán (Đã xuất hóa đơn VAT).';
                } catch (Exception $e) {
                    $wpdb->query('ROLLBACK');
                    $error_message = 'Có lỗi xảy ra: ' . $e->getMessage();
                }
                break;
                
            case 'update_status':
                error_log('Processing update_status for invoice ID: ' . $invoice_id);
                $new_status = sanitize_text_field($_POST['new_status']);
                $status_notes = sanitize_textarea_field($_POST['status_notes']);
                
                $update_data = array(
                    'status' => $new_status,
                    'updated_at' => current_time('mysql')
                );
                
                if (!empty($status_notes)) {
                    $current_notes = $wpdb->get_var($wpdb->prepare("SELECT notes FROM $invoice_table WHERE id = %d", $invoice_id));
                    $update_data['notes'] = $current_notes . "\n\nCập nhật trạng thái: " . $status_notes;
                }
                
                $result = $wpdb->update($invoice_table, $update_data, array('id' => $invoice_id));
                
                if ($result !== false) {
                    // If status is cancelled, update commissions (Phase 2 integration)
                    if ($new_status === 'cancelled' || $new_status === 'canceled') {
                        cancel_commissions_for_invoice($invoice_id);
                        
                        // Set "cancelled" status to all services contained in this invoice
                        $domains_table = $wpdb->prefix . 'im_domains';
                        $hostings_table = $wpdb->prefix . 'im_hostings';
                        $maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
                        $service_table = $wpdb->prefix . 'im_services';
                        $invoice_items_table = $wpdb->prefix . 'im_invoice_items';
                        $websites_table = $wpdb->prefix . 'im_websites';
                        
                        $invoice_items_to_cancel = $wpdb->get_results($wpdb->prepare("
                            SELECT service_type, service_id
                            FROM $invoice_items_table
                            WHERE invoice_id = %d AND service_id IS NOT NULL
                        ", $invoice_id));
                        
                        $affected_website_ids = array();
                        
                        foreach ($invoice_items_to_cancel as $item) {
                            switch ($item->service_type) {
                                case 'domain':
                                    $wpdb->update($domains_table, array('status' => 'DELETED', 'updated_at' => current_time('mysql')), array('id' => $item->service_id));
                                    $affected_website_ids = array_merge($affected_website_ids, $wpdb->get_col($wpdb->prepare("SELECT id FROM $websites_table WHERE domain_id = %d", $item->service_id)));
                                    break;
                                case 'hosting':
                                    $wpdb->update($hostings_table, array('status' => 'DELETED', 'updated_at' => current_time('mysql')), array('id' => $item->service_id));
                                    $affected_website_ids = array_merge($affected_website_ids, $wpdb->get_col($wpdb->prepare("SELECT id FROM $websites_table WHERE hosting_id = %d", $item->service_id)));
                                    break;
                                case 'maintenance':
                                    $wpdb->update($maintenance_table, array('status' => 'DELETED', 'updated_at' => current_time('mysql')), array('id' => $item->service_id));
                                    $affected_website_ids = array_merge($affected_website_ids, $wpdb->get_col($wpdb->prepare("SELECT id FROM $websites_table WHERE maintenance_package_id = %d", $item->service_id)));
                                    break;
                                case 'website_service':
                                    $wpdb->update($service_table, array('status' => 'CANCELLED', 'updated_at' => current_time('mysql')), array('id' => $item->service_id));
                                    break;
                            }
                        }
                        
                        // Check and soft delete affected websites
                        if (!empty($affected_website_ids)) {
                            $affected_website_ids = array_unique($affected_website_ids);
                            foreach ($affected_website_ids as $website_id) {
                                $website = $wpdb->get_row($wpdb->prepare("SELECT domain_id, hosting_id, maintenance_package_id, status FROM $websites_table WHERE id = %d", $website_id));
                                if ($website && $website->status !== 'DELETED') {
                                    $has_active_service = false;
                                    
                                    // Check domain (counts as active if managed by Inova and not DELETED)
                                    if ($website->domain_id) {
                                        $domain = $wpdb->get_row($wpdb->prepare("SELECT managed_by_inova, status FROM $domains_table WHERE id = %d", $website->domain_id));
                                        if ($domain && $domain->managed_by_inova == 1 && $domain->status !== 'DELETED') {
                                            $has_active_service = true;
                                        }
                                    }
                                    
                                    // Check hosting (counts as active if not DELETED)
                                    if (!$has_active_service && $website->hosting_id) {
                                        $hosting = $wpdb->get_row($wpdb->prepare("SELECT status FROM $hostings_table WHERE id = %d", $website->hosting_id));
                                        if ($hosting && $hosting->status !== 'DELETED') {
                                            $has_active_service = true;
                                        }
                                    }
                                    
                                    // Check maintenance (counts as active if not DELETED)
                                    if (!$has_active_service && $website->maintenance_package_id) {
                                        $maintenance = $wpdb->get_row($wpdb->prepare("SELECT status FROM $maintenance_table WHERE id = %d", $website->maintenance_package_id));
                                        if ($maintenance && $maintenance->status !== 'DELETED') {
                                            $has_active_service = true;
                                        }
                                    }
                                    
                                    if (!$has_active_service) {
                                        $wpdb->update($websites_table, array('status' => 'DELETED'), array('id' => $website_id));
                                    }
                                }
                            }
                        }
                    }
                    
                    $success_message = 'Trạng thái hóa đơn đã được cập nhật.';
                } else {
                    $error_message = 'Có lỗi xảy ra khi cập nhật trạng thái hóa đơn.';
                }
                break;
        }
    }
}

// Get invoice details with service information
$invoice = $wpdb->get_row($wpdb->prepare("
    SELECT 
        i.*,
        u.name AS customer_name,
        u.user_code AS customer_code,
        u.email AS customer_email,
        u.tax_code,
        u.address AS customer_address,
        u.phone_number AS customer_phone,
        u.requires_vat_invoice AS user_requires_vat
    FROM 
        $invoice_table i
    LEFT JOIN 
        $users_table u ON i.user_id = u.id
    WHERE 
        i.id = %d
", $invoice_id));

// If invoice not found, redirect to invoice list
if (!$invoice) {
    wp_redirect(home_url('/danh-sach-hoa-don/'));
    exit;
}

// Check user permission - only admin/manager can edit invoice
// Note: $invoice->user_id is INOVA user ID (from im_users table), not WordPress ID
$can_edit_invoice = current_user_can('manage_options') || 
                    current_user_can('edit_users') || 
                    (is_user_logged_in() && get_user_inova_id() == $invoice->user_id && current_user_can('manage_own_invoice'));


// Get invoice items with service details and website names
$websites_table = $wpdb->prefix . 'im_websites';
$hostings_table = $wpdb->prefix . 'im_hostings';
$maintenance_table = $wpdb->prefix . 'im_maintenance_packages';
$domains_table = $wpdb->prefix . 'im_domains';
$commissions_table = $wpdb->prefix . 'im_partner_commissions';

$invoice_items = $wpdb->get_results($wpdb->prepare("
    SELECT
        ii.*,
        CASE
            WHEN ii.service_type = 'website_service' THEN ws.title
            ELSE ii.description
        END AS service_title,
        CASE
            WHEN ii.service_type = 'website_service' THEN ws.description
            ELSE ''
        END AS service_reference,
        COALESCE((SELECT SUM(commission_amount) FROM $commissions_table WHERE invoice_item_id = ii.id AND status = 'DIRECT_DISCOUNT'), 0) AS withdrawn_commission,
        CASE
            WHEN ii.service_type = 'hosting' THEN (SELECT discount_amount FROM $hostings_table WHERE id = ii.service_id)
            WHEN ii.service_type = 'maintenance' THEN (SELECT discount_amount FROM $maintenance_table WHERE id = ii.service_id)
            ELSE 0
        END AS item_discount_amount,
        CASE
            WHEN ii.service_type = 'domain' THEN (SELECT expiry_date FROM $domains_table WHERE id = ii.service_id)
            WHEN ii.service_type = 'hosting' THEN (SELECT expiry_date FROM $hostings_table WHERE id = ii.service_id)
            WHEN ii.service_type = 'maintenance' THEN (SELECT expiry_date FROM $maintenance_table WHERE id = ii.service_id)
            ELSE NULL
        END AS expiry_date,
        CASE
            WHEN ii.service_type = 'hosting' THEN (SELECT billing_cycle_months FROM $hostings_table WHERE id = ii.service_id)
            WHEN ii.service_type = 'maintenance' THEN (SELECT billing_cycle_months FROM $maintenance_table WHERE id = ii.service_id)
            ELSE NULL
        END AS billing_cycle_months
    FROM
        $invoice_items_table ii
    LEFT JOIN
        $service_table ws ON ii.service_type = 'website_service' AND ii.service_id = ws.id
    WHERE
        ii.invoice_id = %d
    ORDER BY ii.id
", $invoice_id));

// Get website names for each invoice item using common function
foreach ($invoice_items as $item) {
    $item->website_names = get_invoice_item_website_names($item);
}

// Calculate totals and check if has discounts/commissions to show
$has_item_discount = false;
$total_commission_deduction = 0;

foreach ($invoice_items as $item) {
    $item_commission = isset($item->withdrawn_commission) ? floatval($item->withdrawn_commission) : 0;
    $item_discount = isset($item->item_discount_amount) ? floatval($item->item_discount_amount) : 0;
    
    // Sum of commission for the bottom summary
    if ($item_commission > 0) {
        $total_commission_deduction += $item_commission;
    }
    
    // Flag to show "Giảm giá" and "Sau giảm" columns in items table
    if ($item_commission > 0 || $item_discount > 0) {
        $has_item_discount = true;
    }
}
$has_commission_deduction = $has_item_discount; // Rename for consistency in table header

// Check for related invoices (for 50% payment system)
$related_invoices = $wpdb->get_results($wpdb->prepare("
    SELECT DISTINCT 
        i2.*
    FROM 
        $invoice_table i2
    INNER JOIN 
        $invoice_items_table ii1 ON i2.id = ii1.invoice_id
    INNER JOIN 
        $invoice_items_table ii2 ON ii1.service_id = ii2.service_id 
        AND ii1.service_type = ii2.service_type
    WHERE 
        ii2.invoice_id = %d 
        AND i2.id != %d
    ORDER BY i2.created_at
", $invoice_id, $invoice_id));

// Calculate final total once (used in both totals display and QR code)
if ($invoice->requires_vat_invoice) {
    // With VAT: total_amount already includes tax
    $final_total = $invoice->total_amount;
} else {
    // Without VAT: total should be sub_total - discount
    $final_total = $invoice->sub_total - $invoice->discount_total;
}

// Apply commission deduction (always apply regardless of status)
$final_total -= $total_commission_deduction;

// Get status options and colors
$status_options = array(
    'draft' => 'Nháp',
    'pending' => 'Chờ thanh toán',
    'pending_vat' => 'Chờ xuất hóa đơn',
    'paid' => 'Đã thanh toán',
    'canceled' => 'Đã hủy',
    'pending_completion' => 'Chờ hoàn thành dịch vụ'
);

$status_classes = array(
    'draft' => 'bg-secondary',
    'pending' => 'bg-warning',
    'pending_vat' => 'bg-info',
    'paid' => 'bg-success',
    'canceled' => 'bg-danger',
    'pending_completion' => 'bg-info'
);

get_header(); 
?>

<div class="main-panel">
    <div class="content-wrapper">
        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="ph ph-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="ph ph-x-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Invoice Details Card -->
            <div class="col-lg-8 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h4 class="card-title mb-2">
                                    <i class="ph ph-receipt me-2"></i>
                                    Hóa đơn <?php echo esc_html($invoice->invoice_code); ?>
                                </h4>
                                <div class="d-flex align-items-center gap-3">
                                    <?php
                                    $status_classes = [
                                        'draft' => 'bg-secondary',
                                        'pending' => 'bg-warning',
                                        'paid' => 'bg-success',
                                        'canceled' => 'bg-danger',
                                        'pending_completion' => 'bg-info'
                                    ];
                                    $status_class = $status_classes[$invoice->status] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo $status_options[$invoice->status] ?? $invoice->status; ?>
                                    </span>
                                    <span class="text-muted">
                                        <i class="ph ph-calendar me-1"></i>
                                        Ngày tạo: <?php echo date('d/m/Y', strtotime($invoice->created_at)); ?>
                                    </span>
                                </div>
                            </div>

                        </div>

                        <!-- Invoice Info -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Thông tin hóa đơn</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" width="40%">Mã hóa đơn:</td>
                                        <td><strong><?php echo esc_html($invoice->invoice_code); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Ngày hóa đơn:</td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice->invoice_date)); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Hạn thanh toán:</td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice->due_date)); ?></td>
                                    </tr>
                                    <?php if ($invoice->payment_date): ?>
                                    <tr>
                                        <td class="text-muted">Ngày thanh toán:</td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice->payment_date)); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Thông tin khách hàng</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" width="40%">Tên khách hàng:</td>
                                        <td><strong><?php echo esc_html($invoice->customer_name); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Mã khách hàng:</td>
                                        <td><?php echo esc_html($invoice->customer_code); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Email:</td>
                                        <td><?php echo esc_html($invoice->customer_email); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Invoice Items -->
                         <h6 class="text-muted mb-3">Chi tiết dịch vụ</h6>
                         <?php
                         // Always group items by product_type
                         $grouped_items = array();
                         $product_type_labels = array(
                             'domain' => 'Tên miền',
                             'hosting' => 'Hosting',
                             'maintenance' => 'Gói bảo trì',
                             'website_service' => 'Dịch vụ website'
                         );
                         
                         foreach ($invoice_items as $item) {
                             $type = $item->service_type;
                             if (!isset($grouped_items[$type])) {
                                 $grouped_items[$type] = array();
                             }
                             $grouped_items[$type][] = $item;
                         }
                         
                         // Display each group with header
                         foreach ($grouped_items as $product_type => $items):
                             // Check if this group has any discount/commission
                             $group_has_discount = false;
                             foreach ($items as $item) {
                                 if ((isset($item->withdrawn_commission) && $item->withdrawn_commission > 0) || 
                                     (isset($item->item_discount_amount) && $item->item_discount_amount > 0)) {
                                     $group_has_discount = true;
                                     break;
                                 }
                             }

                             // Get VAT rate from first item (all items in group should have same rate)
                             $group_vat_rate = 0;
                             if ($invoice->requires_vat_invoice && !empty($items)) {
                                 $group_vat_rate = isset($items[0]->vat_rate) ? floatval($items[0]->vat_rate) : 0;
                             }
                         ?>
                         <div class="mb-4">
                             <h6 class="text-dark mb-3">
                                 <i class="ph ph-list me-2"></i>Danh sách dịch vụ <?php echo $product_type_labels[$product_type] ?? $product_type; ?>
                             </h6>
                             <div class="table-responsive">
                                 <table class="table table-striped">
                                     <thead class="table-light">
                                         <tr>
                                             <th>Dịch vụ</th>
                                             <?php if ($product_type !== 'website_service'): ?>
                                             <th class="text-center">Ngày hết hạn</th>
                                             <th class="text-center">Gia hạn đến</th>
                                             <?php endif; ?>
                                             <th class="text-end">Thành tiền</th>
                                             <?php if ($group_has_discount): ?>
                                             <th class="text-end">Giảm giá</th>
                                             <th class="text-end">Sau giảm</th>
                                             <?php endif; ?>
                                             <?php if ($invoice->requires_vat_invoice): ?>
                                             <th class="text-end">VAT <?php echo $group_vat_rate > 0 ? '(' . intval($group_vat_rate) . '%)' : ''; ?></th>
                                             <th class="text-end">Tổng</th>
                                             <?php endif; ?>
                                             <?php if ($can_edit_invoice && !in_array($invoice->status, ['paid', 'pending_vat'])): ?>
                                             <th class="text-center">Thao tác</th>
                                             <?php endif; ?>
                                         </tr>
                                     </thead>
                                     <tbody>
                                         <?php foreach ($items as $item): ?>
                                         <tr>
                                             <td>
                                                 <div class="fw-bold"><?php echo esc_html($item->service_title ?: $item->description); ?></div>
                                                 <?php if ($item->service_reference): ?>
                                                     <small class="text-muted"><?php echo esc_html($item->service_reference); ?></small>
                                                 <?php endif; ?>
                                                 <?php if (!empty($item->website_names)): ?>
                                                     <div><small class="text-muted">
                                                         <i class="ph ph-globe-hemisphere-west"></i>
                                                         (<?php echo implode(', ', array_map('esc_html', $item->website_names)); ?>)
                                                     </small></div>
                                                 <?php endif; ?>
                                             </td>
                                             <?php if ($product_type !== 'website_service'): ?>
                                             <td class="text-center">
                                                 <?php 
                                                 if ($item->expiry_date):
                                                     echo date('d/m/Y', strtotime($item->expiry_date));
                                                 else:
                                                     echo '<span class="text-muted">-</span>';
                                                 endif;
                                                 ?>
                                             </td>
                                             <td class="text-center">
                                                 <?php 
                                                 if ($item->expiry_date && $item->billing_cycle_months):
                                                     // Calculate renewal date
                                                     $renewal_date = date('d/m/Y', strtotime($item->expiry_date . ' + ' . $item->billing_cycle_months . ' months'));
                                                     echo $renewal_date;
                                                 elseif ($item->expiry_date && $product_type === 'domain'):
                                                     // For domain, add 1 year
                                                     $renewal_date = date('d/m/Y', strtotime($item->expiry_date . ' + 1 year'));
                                                     echo $renewal_date;
                                                 else:
                                                     echo '<span class="text-muted">-</span>';
                                                 endif;
                                                 ?>
                                             </td>
                                             <?php endif; ?>
                                             <?php 
                                                 $display_data = get_invoice_item_display_data($item, $invoice->requires_vat_invoice);
                                             ?>
                                             <td class="text-end"><?php echo number_format($display_data['subtotal']); ?>đ</td>
                                             
                                             <?php if ($group_has_discount): ?>
                                             <td class="text-end">
                                                 <?php if ($display_data['total_discount'] > 0): ?>
                                                     <span class="text-danger">-<?php echo number_format($display_data['total_discount']); ?>đ</span>
                                                 <?php else: ?>
                                                     <span class="text-muted">-</span>
                                                 <?php endif; ?>
                                             </td>
                                             <td class="text-end">
                                                 <?php echo number_format($display_data['total_after_discount']); ?>đ
                                             </td>
                                             <?php endif; ?>

                                             <?php if ($invoice->requires_vat_invoice): ?>
                                             <td class="text-end">
                                                 <?php if ($display_data['vat_amount'] > 0): ?>
                                                     <?php echo number_format($display_data['vat_amount']); ?>đ
                                                 <?php else: ?>
                                                     <span class="text-muted">-</span>
                                                 <?php endif; ?>
                                             </td>
                                             <td class="text-end"><strong><?php echo number_format($display_data['final_total']); ?>đ</strong></td>
                                             <?php endif; ?>
                                             
                                             <?php if ($can_edit_invoice && !in_array($invoice->status, ['paid', 'pending_vat'])): ?>
                                             <td class="text-center">
                                                 <button type="button" class="btn btn-sm btn-danger delete-invoice-item" 
                                                         data-item-id="<?php echo $item->id; ?>"
                                                         data-item-name="<?php echo esc_attr($item->service_title ?: $item->description); ?>"
                                                         title="Xóa item">
                                                     <i class="ph ph-trash"></i>
                                                 </button>
                                             </td>
                                             <?php endif; ?>
                                             </tr>
                                             <?php endforeach; ?>
                                     </tbody>
                                 </table>
                             </div>
                         </div>
                         <?php endforeach; ?>

                                     <!-- Invoice Totals -->
                         <div class="row justify-content-end">
                             <div class="col-md-6">
                                 <table class="table table-sm">
                                     <?php if ($invoice->requires_vat_invoice): ?>
                                     <tr>
                                         <td class="text-muted">Tạm tính:</td>
                                         <td class="text-end"><?php echo number_format($invoice->sub_total); ?> VNĐ</td>
                                     </tr>
                                     <?php endif; ?>
                                     <?php if ($invoice->discount_total > 0): ?>
                                     <tr>
                                         <td class="text-muted">Giảm giá:</td>
                                         <td class="text-end text-danger">-<?php echo number_format($invoice->discount_total); ?> VNĐ</td>
                                     </tr>
                                     <?php endif; ?>
                                     <?php if ($invoice->requires_vat_invoice && $invoice->tax_amount > 0): ?>
                                     <tr>
                                         <td class="text-muted">Thuế:</td>
                                         <td class="text-end"><?php echo number_format($invoice->tax_amount); ?> VNĐ</td>
                                     </tr>
                                     <?php endif; ?>
                                     
                                     <!-- Commission deduction (always show if exists) -->
                                     <?php if ($total_commission_deduction > 0): ?>
                                     <tr class="table-light">
                                         <td class="text-muted">Chiết khấu:</td>
                                         <td class="text-end text-danger">-<?php echo number_format($total_commission_deduction); ?> VNĐ</td>
                                     </tr>
                                     <?php endif; ?>
                                     
                                     <tr class="table-primary">
                                     <td><strong>Tổng cộng:</strong></td>
                                     <td class="text-end"><strong><?php 
                                     echo number_format($final_total); 
                                     ?> VNĐ</strong></td>
                                     </tr>
                                     <?php if ($invoice->paid_amount > 0): ?>
                                     <tr class="table-success">
                                         <td><strong>Đã thanh toán:</strong></td>
                                         <td class="text-end text-success"><strong><?php echo number_format($invoice->paid_amount); ?> VNĐ</strong></td>
                                     </tr>
                                     <tr>
                                         <td><strong>Còn lại:</strong></td>
                                         <td class="text-end"><strong><?php 
                                             $remaining = $final_total - $invoice->paid_amount;
                                             echo number_format(max(0, $remaining)); 
                                         ?> VNĐ</strong></td>
                                     </tr>
                                     <?php endif; ?>
                                 </table>
                             </div>
                         </div>

                        <!-- Notes - only visible to admins -->
                         <?php if ($can_edit_invoice && $invoice->notes): ?>
                         <div class="mt-4">
                             <h6 class="text-muted mb-2">Ghi chú</h6>
                             <div class="alert alert-light">
                                 <?php echo nl2br(esc_html($invoice->notes)); ?>
                             </div>
                         </div>
                         <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Related Invoices Sidebar -->
            <div class="col-lg-4 grid-margin stretch-card d-flex flex-column">
                <?php
                // Generate Payment QR Code if settings are configured and invoice is not paid
                // Check if QR code settings exist (either new format with VAT split or old format)
                $has_qr_settings = (
                    (get_option('payment_bank_code_no_vat') && get_option('payment_account_number_no_vat')) ||
                    (get_option('payment_bank_code_with_vat') && get_option('payment_account_number_with_vat')) ||
                    (get_option('payment_bank_code') && get_option('payment_account_number'))
                );

                if ($has_qr_settings && !in_array($invoice->status, ['paid', 'pending_vat', 'canceled'])):
                     // Calculate remaining amount after paid amount
                     $remaining_amount = $final_total - $invoice->paid_amount;

                     // Generate QR code with invoice code as reference
                     // Pass requires_vat_invoice to select appropriate bank account
                     $qr_add_info = 'HD ' . $invoice->invoice_code;
                     $requires_vat_invoice = isset($invoice->requires_vat_invoice) ? $invoice->requires_vat_invoice : 0;
                     $qr_code_url = generate_payment_qr_code($remaining_amount, $qr_add_info, $requires_vat_invoice, $invoice->payment_method);

                    if ($qr_code_url):
                ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="ph ph-qr-code me-2"></i>Thanh toán qua QR Code
                        </h6>
                        <p class="text-muted small mb-3">Quét mã QR để thanh toán nhanh</p>

                        <!-- QR Code Image -->
                        <div class="text-center mb-3">
                            <img src="<?php echo esc_url($qr_code_url); ?>"
                                 alt="Payment QR Code"
                                 class="img-fluid rounded"
                                 style="max-width: 280px; border: 1px solid #ddd; padding: 10px;">
                        </div>

                        <!-- Payment Information -->
                        <div class="alert alert-danger mb-0">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted"><small>Nội dung CK:</small></td>
                                    <td><strong><small><?php echo esc_html($qr_add_info); ?></small></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><small>Số tiền:</small></td>
                                    <td><strong><small><?php 
                                        echo number_format(max(0, $remaining_amount)); 
                                    ?> VNĐ</small></strong></td>
                                </tr>
                            </table>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="ph ph-info me-1"></i>
                                Vui lòng giữ nguyên nội dung chuyển khoản để hệ thống tự động xác nhận thanh toán.
                            </small>
                        </div>
                    </div>
                </div>

                <?php
                    endif;
                endif;
                ?>

                <!-- Copy Invoice Link Box - Always visible regardless of status -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="ph ph-link me-2"></i>Chia sẻ hóa đơn
                        </h6>
                        <p class="text-muted small mb-3">Copy link để gửi cho khách hàng</p>

                        <?php
                        // Generate public invoice link (token only - invoice_id is encoded in token)
                        $public_invoice_link = home_url('/public-invoice/?token=' . urlencode(base64_encode($invoice->id . '|' . $invoice->created_at)));
                        ?>

                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="invoiceLinkInput" 
                                   value="<?php echo esc_attr($public_invoice_link); ?>" readonly>
                            <a href="<?php echo esc_attr($public_invoice_link); ?>" target="_blank" class="btn bg-light-info border-info text-info"><i class="ph ph-arrow-square-out"></i></a>
                            <button class="btn btn-info" type="button" id="copyInvoiceLinkBtn" 
                                    data-link="<?php echo esc_attr($public_invoice_link); ?>">
                                <i class="ph ph-copy me-1"></i>Copy
                            </button>
                        </div>

                        <small class="text-muted d-block">
                            <i class="ph ph-info me-1"></i>
                            Khách hàng có thể xem hóa đơn chi tiết qua link này.
                        </small>
                    </div>
                </div>

                <?php if (!empty($related_invoices)): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="ph ph-link me-2"></i>Hóa đơn liên quan
                        </h6>
                        <p class="text-muted small mb-3">Các hóa đơn khác cho cùng dịch vụ</p>
                        
                        <?php foreach ($related_invoices as $related): ?>
                        <div class="d-flex justify-content-between align-items-center p-3 border rounded mb-2">
                            <div>
                                <div class="fw-bold"><?php echo esc_html($related->invoice_code); ?></div>
                                <small class="text-muted">
                                    <?php 
                                    $related_status_class = $status_classes[$related->status] ?? 'bg-secondary';
                                    echo '<span class="badge ' . $related_status_class . ' badge-sm">' . 
                                         ($status_options[$related->status] ?? $related->status) . '</span>';
                                    ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?php echo number_format($related->total_amount); ?> VNĐ</div>
                                <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $related->id); ?>" 
                                   class="btn btn-sm btn-primary">Xem</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="ph ph-lightning me-2"></i>Thao tác nhanh
                        </h6>
                        
                        <div class="d-flex flex-column">
                             <?php if ($can_edit_invoice): ?>
                             <?php if ($invoice->status === 'pending_vat'): ?>
                             <button type="button" class="btn btn-success mb-2" data-bs-toggle="modal" data-bs-target="#markExportedModalDetail">
                                 <i class="ph ph-file-text me-2"></i>Đã xuất hóa đơn
                             </button>
                             <?php elseif ($invoice->status !== 'paid'): ?>
                             <button type="button" class="btn btn-success mb-2" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                 <i class="ph ph-credit-card me-2"></i>Đánh dấu đã thanh toán
                             </button>
                             <?php endif; ?>
                             
                             <button type="button" class="btn btn-danger mb-2" data-bs-toggle="modal" data-bs-target="#statusModal">
                                 <i class="ph ph-arrow-clockwise me-2"></i>Cập nhật trạng thái
                             </button>
                             <?php endif; ?>
                             
                             <?php if ($can_edit_invoice && !in_array($invoice->status, ['paid', 'pending_vat'])): ?>
                             <button type="button" class="btn btn-info mb-2" data-bs-toggle="modal" data-bs-target="#mergeInvoiceModal">
                                 <i class="ph ph-plus me-2"></i>Gộp hóa đơn
                             </button>
                             <button type="button" class="btn btn-warning mb-2" data-bs-toggle="modal" data-bs-target="#editInvoiceModal">
                                 <i class="ph ph-note-pencil me-2"></i>Tùy chọn thanh toán
                             </button>
                             <button type="button" class="btn btn-primary mb-2 recalculateInvoiceBtn">
                                 <i class="ph ph-calculator me-2"></i>Tính lại tổng tiền
                             </button>
                             <button type="button" class="btn btn-secondary mb-2" data-bs-toggle="modal" data-bs-target="#changeCustomerModal">
                                 <i class="ph ph-user-circle-plus me-2"></i>Thay đổi khách hàng
                             </button>
                             <?php endif; ?>
                             
                             </div>
                             </div>
                             </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal - only visible to admins -->
<?php if ($can_edit_invoice): ?>
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php wp_nonce_field('invoice_action', 'action_nonce'); ?>
                <input type="hidden" name="action" value="mark_as_paid">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ph ph-credit-card me-2"></i>Đánh dấu đã thanh toán
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Số tiền thanh toán <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="paid_amount" 
                               value="<?php echo $final_total - $invoice->paid_amount; ?>" 
                               min="0" max="<?php echo $final_total - $invoice->paid_amount; ?>" 
                               step="1000" required>
                        <div class="form-text">Còn lại: <?php echo number_format($final_total - $invoice->paid_amount); ?> VNĐ</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ngày thanh toán <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phương thức thanh toán</label>
                        <select class="form-select" name="payment_method">
                            <option value="bank_transfer" <?php echo ($invoice->payment_method === 'bank_transfer') ? 'selected' : ''; ?>>Chuyển khoản ngân hàng</option>
                            <option value="cash" <?php echo ($invoice->payment_method === 'cash') ? 'selected' : ''; ?>>Tiền mặt</option>
                            <option value="credit_card" <?php echo ($invoice->payment_method === 'credit_card') ? 'selected' : ''; ?>>Thẻ tín dụng</option>
                            <option value="e_wallet" <?php echo ($invoice->payment_method === 'e_wallet') ? 'selected' : ''; ?>>Ví điện tử</option>
                            <option value="other" <?php echo ($invoice->payment_method === 'other') ? 'selected' : ''; ?>>Khác</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ghi chú thanh toán</label>
                        <textarea class="form-control" name="payment_notes" rows="3" 
                                  placeholder="Ghi chú về việc thanh toán (tùy chọn)"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success">
                        <i class="ph ph-check me-2"></i>Xác nhận thanh toán
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Update Modal - only visible to admins -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php wp_nonce_field('invoice_action', 'action_nonce'); ?>
                <input type="hidden" name="action" value="update_status">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ph ph-arrow-clockwise me-2"></i>Cập nhật trạng thái hóa đơn
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label">Trạng thái hiện tại</label>
                        <div>
                            <?php
                            $current_status_class = $status_classes[$invoice->status] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?php echo $current_status_class; ?> p-2">
                                <i class="ph ph-circle-fill me-2" style="font-size: 0.6rem;"></i>
                                <?php echo $status_options[$invoice->status] ?? $invoice->status; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label d-block mb-3">Trạng thái mới <span class="text-danger">*</span></label>
                        <div class="row">
                            <?php foreach ($status_options as $status_key => $status_label): ?>
                                <?php if ($status_key !== $invoice->status): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="new_status" 
                                               id="status_<?php echo $status_key; ?>" 
                                               value="<?php echo $status_key; ?>" required>
                                        <label class="form-check-label" for="status_<?php echo $status_key; ?>">
                                            <?php echo $status_label; ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ghi chú thay đổi</label>
                        <textarea class="form-control" name="status_notes" rows="3" 
                                  placeholder="Lý do thay đổi trạng thái (tùy chọn)"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-check me-2"></i>Cập nhật trạng thái
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Merge Invoice Modal -->
<?php if ($can_edit_invoice): ?>
<div class="modal fade" id="mergeInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ph ph-plus me-2"></i>Gộp hóa đơn
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                <p class="text-muted mb-3">Chọn một hoặc nhiều hóa đơn chờ thanh toán của cùng khách hàng để gộp:</p>
                
                <!-- Loading indicator -->
                <div id="mergeLoadingSpinner" class="text-center d-none mb-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Đang tải...</span>
                    </div>
                </div>
                
                <!-- Invoice list -->
                <div id="mergeInvoiceList" class="d-none">
                    <div class="list-group" style="max-height: 400px; overflow-y: auto;">
                        <!-- Invoices will be loaded here -->
                    </div>
                </div>
                
                <!-- Empty state -->
                <div id="mergeNoInvoices" class="alert alert-info d-none">
                    <i class="ph ph-info me-2"></i>
                    Không có hóa đơn khác của khách hàng này để gộp.
                </div>
                
                <!-- Error message -->
                <div id="mergeErrorMsg" class="alert alert-danger d-none" role="alert"></div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="mergeSubmitBtn" disabled>
                    <i class="ph ph-check me-2"></i>Gộp hóa đơn
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($can_edit_invoice): ?>
<div class="modal fade" id="editInvoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php wp_nonce_field('invoice_action', 'action_nonce'); ?>
                <input type="hidden" name="action" value="edit_invoice_settings">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ph ph-note-pencil me-2"></i>Chỉnh sửa cấu hình hóa đơn
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <label class="form-check-label fw-bold" for="requiresVatSwitch">Lấy hóa đơn VAT (10%)
                                <input class="form-check-input" type="checkbox" name="requires_vat_invoice" id="requiresVatSwitch" 
                                       <?php echo $invoice->requires_vat_invoice ? 'checked' : ''; ?>>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phương thức thanh toán</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="methodNone" value="" 
                                       <?php echo empty($invoice->payment_method) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="methodNone">Mặc định</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="methodCash" value="cash" 
                                       <?php echo $invoice->payment_method === 'cash' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="methodCash">Tiền mặt</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="methodTransfer" value="bank_transfer" 
                                       <?php echo $invoice->payment_method === 'bank_transfer' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="methodTransfer">Chuyển khoản</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-check me-2"></i>Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Change Customer Modal -->
<?php if ($can_edit_invoice): ?>
<div class="modal fade" id="changeCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ph ph-user-circle-plus me-2"></i>Thay đổi khách hàng
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                <p class="text-muted mb-3">Chọn khách hàng mới cho hóa đơn này.</p>
                
                <div class="mb-3">
                    <label class="form-label">Tìm khách hàng <span class="text-danger">*</span></label>
                    <select id="new_customer_id" class="form-select select2-customer" style="width: 100%">
                        <option value="">-- Tìm theo tên hoặc mã --</option>
                    </select>
                </div>

                <div class="form-check mb-3">
                    <label class="form-check-label text-warning fw-bold" for="change_services_owner">
                        <input class="form-check-input" type="checkbox" id="change_services_owner" value="1">
                        Thay đổi chủ sở hữu của dịch vụ
                    </label>
                    <small class="form-text text-muted d-block mt-1">
                        Nếu chọn, các dịch vụ liên kết trong hóa đơn này cũng sẽ tự động đổi sang chủ sở hữu mới.
                    </small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="changeCustomerSubmitBtn" disabled>
                    <i class="ph ph-check me-2"></i>Xác nhận thay đổi
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Mark Exported Modal -->
<?php if ($can_edit_invoice): ?>
<div class="modal fade" id="markExportedModalDetail" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <?php wp_nonce_field('invoice_action', 'action_nonce'); ?>
                <input type="hidden" name="action" value="mark_invoice_exported">
                
                <div class="modal-header bg-success text-white border-0 py-3">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="ph ph-file-text me-2 fs-4"></i> Xác nhận Đã xuất hóa đơn
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-success">
                        <i class="ph ph-check-circle display-4"></i>
                    </div>
                    <h5 class="mb-3 text-dark fw-bold">Hoàn tất quy trình xuất hóa đơn VAT?</h5>
                    <p class="text-muted mb-0">Bạn có chắc chắn muốn xác nhận hóa đơn mã <strong class="text-primary"><?php echo esc_html($invoice->invoice_code); ?></strong> đã được xuất VAT và chuyển trạng thái sang <strong class="text-success">Đã thanh toán</strong>?</p>
                    <p class="text-danger small mt-2"><i class="ph ph-warning me-1"></i> Lưu ý: Thao tác này sẽ chỉ cập nhật trạng thái hóa đơn và KHÔNG gia hạn lại dịch vụ hay tính lại hoa hồng.</p>
                </div>
                
                <div class="modal-footer border-0 bg-light py-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary px-4 border-radius-9" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success px-4 border-radius-9">
                        <i class="ph ph-check me-2"></i> Xác nhận
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($auto_open_payment && !in_array($invoice->status, ['paid', 'pending_vat'])): ?>
<script>
jQuery(document).ready(function($) {
    console.log('Auto-opening payment modal...');
    // Use Bootstrap 5 modal API
    try {
        var paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        paymentModal.show();
        console.log('Payment modal opened successfully');
    } catch (error) {
        console.error('Error opening payment modal:', error);
        // Fallback to jQuery if Bootstrap 5 is not available
        $('#paymentModal').modal('show');
    }
});
</script>
<?php endif; ?>

<script>
// Form validation for payment modal, copy invoice link, and delete invoice item
jQuery(document).ready(function($) {
    /**
     * Delete invoice item handler
     * Files using this: detail_invoice.php
     */
    $(document).on('click', '.delete-invoice-item', function(e) {
        e.preventDefault();
        
        var itemId = $(this).data('item-id');
        var itemName = $(this).data('item-name');
        var invoiceId = <?php echo $invoice_id; ?>;
        
        // Confirm deletion

        
        var btn = $(this);
        var originalHtml = btn.html();
        
        // Show loading state
        btn.prop('disabled', true);
        btn.html('<i class="ph ph-spinner ph-spin"></i>');
        
        // Submit delete form via AJAX
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'delete_item',
                item_id: itemId,
                action_nonce: '<?php echo wp_create_nonce('invoice_action'); ?>'
            },
            success: function(response) {
                // Reload page to show updated invoice
                window.location.reload();
            },
            error: function(xhr, status, error) {
                console.error('Error deleting item:', error);
                alert('Lỗi khi xóa item. Vui lòng thử lại.');
                
                // Restore button
                btn.prop('disabled', false);
                btn.html(originalHtml);
            }
        });
    });
    
    /**
     * Copy invoice link handler
     */
    $(document).on('click', '#copyInvoiceLinkBtn', function(e) {
        e.preventDefault();
        const link = $(this).data('link');
        const inputField = $('#invoiceLinkInput');
        
        // Select the input field and copy
        inputField.select();
        
        // Use modern clipboard API if available, fallback to exec command
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(function() {
                // Show success feedback
                const btn = $('#copyInvoiceLinkBtn');
                const originalHtml = btn.html();
                btn.html('<i class="ph ph-check me-1"></i>Đã copy');
                btn.addClass('btn-success').removeClass('btn-outline-primary');
                
                setTimeout(function() {
                    btn.html(originalHtml);
                    btn.removeClass('btn-success').addClass('btn-outline-primary');
                }, 2000);
            }).catch(function() {
                // Fallback to exec command if clipboard API fails
                document.execCommand('copy');
                showCopyFeedback();
            });
        } else {
            // Fallback for older browsers
            document.execCommand('copy');
            showCopyFeedback();
        }
        
        function showCopyFeedback() {
            const btn = $('#copyInvoiceLinkBtn');
            const originalHtml = btn.html();
            btn.html('<i class="ph ph-check me-1"></i>Đã copy');
            btn.addClass('btn-success').removeClass('btn-outline-primary');
            
            setTimeout(function() {
                btn.html(originalHtml);
                btn.removeClass('btn-success').addClass('btn-outline-primary');
            }, 2000);
        }
    });

    const paymentForm = document.querySelector('#paymentModal form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            
            const paidAmount = parseFloat(document.querySelector('input[name="paid_amount"]').value);
            const maxAmount = parseFloat(document.querySelector('input[name="paid_amount"]').getAttribute('max'));
            
            console.log('Paid amount:', paidAmount, 'Max amount:', maxAmount);
            
            if (paidAmount <= 0) {
                e.preventDefault();
                alert('Số tiền thanh toán phải lớn hơn 0');
                return false;
            }
            
            if (paidAmount > maxAmount) {
                e.preventDefault();
                alert('Số tiền thanh toán không được vượt quá số tiền còn lại');
                return false;
            }
            
            console.log('Form validation passed, submitting...');
            // Show loading indicator
            const submitBtn = paymentForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin me-2"></i>Đang xử lý...';
                submitBtn.disabled = true;
            }
        });
    } else {
        console.error('Payment form not found');
    }
    
    /**
     * Merge Invoice Modal Handler
     * Loads list of pending invoices for same customer
     */
    $('#mergeInvoiceModal').on('show.bs.modal', function(e) {
        var invoiceId = <?php echo $invoice_id; ?>;
        var customerId = <?php echo $invoice->user_id; ?>;
        
        // Show loading spinner
        $('#mergeLoadingSpinner').removeClass('d-none');
        $('#mergeInvoiceList').addClass('d-none');
        $('#mergeNoInvoices').addClass('d-none');
        $('#mergeErrorMsg').addClass('d-none');
        $('#mergeSubmitBtn').prop('disabled', true);
        
        // Load invoices via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'load_merge_invoices',
                invoice_id: invoiceId,
                customer_id: customerId
            },
            success: function(response) {
                $('#mergeLoadingSpinner').addClass('d-none');
                console.log('Merge invoices response:', response);
                console.log('Invoice count:', response.data ? response.data.invoices.length : 0);
                
                if (response.success && response.data.invoices.length > 0) {
                    var listHtml = '';
                    $.each(response.data.invoices, function(index, invoice) {
                        var invoiceAmount = parseInt(invoice.total_amount).toLocaleString('vi-VN');
                        listHtml += '<label class="list-group-item d-flex">' +
                            '<input class="form-check-input merge-invoice-checkbox" type="checkbox" value="' + invoice.id + '" data-amount="' + invoice.total_amount + '">' +
                            '<div class="d-flex flex-column justify-content-between w-100 ms-2">' +
                            '<div>' +
                            '<strong>' + invoice.invoice_code + '</strong>' +
                            '<br><small class="text-muted">Ngày tạo: ' + invoice.created_at + '</small>' +
                            '</div>' +
                            '<div>' +
                            '<strong>' + invoiceAmount + ' VNĐ</strong>' +
                            '</div>' +
                            '</div>' +
                            '</label>';
                    });
                    
                    $('#mergeInvoiceList .list-group').html(listHtml);
                    $('#mergeInvoiceList').removeClass('d-none');
                    
                    // Checkbox change handler
                    $(document).on('change', '.merge-invoice-checkbox', function() {
                        var hasChecked = $('.merge-invoice-checkbox:checked').length > 0;
                        $('#mergeSubmitBtn').prop('disabled', !hasChecked);
                    });
                } else {
                    $('#mergeNoInvoices').removeClass('d-none');
                }
            },
            error: function(xhr, status, error) {
                $('#mergeLoadingSpinner').addClass('d-none');
                $('#mergeErrorMsg').html('Có lỗi xảy ra khi tải danh sách hóa đơn: ' + error).removeClass('d-none');
            }
        });
    });
    
    /**
     * Merge submit handler
     */
    $(document).on('click', '#mergeSubmitBtn', function(e) {
        e.preventDefault();
        
        var selectedIds = [];
        $('.merge-invoice-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            alert('Vui lòng chọn ít nhất một hóa đơn để gộp');
            return;
        }
        
        // Confirm action
        if (!confirm('Xác nhận gộp ' + selectedIds.length + ' hóa đơn? Các chi tiết sẽ được chuyển sang hóa đơn hiện tại và hóa đơn cũ sẽ bị xóa.')) {
            return;
        }
        
        var btn = $(this);
        var originalHtml = btn.html();
        btn.html('<i class="ph ph-spinner ph-spin me-2"></i>Đang xử lý...').prop('disabled', true);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'merge_invoices',
                target_invoice_id: <?php echo $invoice_id; ?>,
                source_invoice_ids: selectedIds
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    var successMsg = '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                        '<i class="ph ph-check-circle me-2"></i>' +
                        'Gộp hóa đơn thành công! ' + response.data.message +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                        '</div>';
                    $('.content-wrapper').prepend(successMsg);
                    
                    // Close modal and reload page
                    var modal = bootstrap.Modal.getInstance(document.getElementById('mergeInvoiceModal'));
                    modal.hide();
                    
                    // Reload page immediately
                    location.reload();
                } else {
                    alert('Lỗi: ' + response.data.message);
                    btn.html(originalHtml).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Có lỗi xảy ra: ' + error);
                btn.html(originalHtml).prop('disabled', false);
            }
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            // Use Bootstrap 5 Alert API
            var alertInstance = bootstrap.Alert.getOrCreateInstance(alert);
            alertInstance.close();
        }, 5000);
    });
    


    /**
     * Recalculate Invoice handler
     */
    $(document).on('click', '.recalculateInvoiceBtn', function(e) {
        e.preventDefault();
        

        
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.html('<i class="ph ph-spinner ph-spin me-2"></i>Đang xử lý...').addClass('disabled');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'recalculate_invoice_totals',
                invoice_id: <?php echo $invoice_id; ?>
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Lỗi: ' + (response.data ? response.data.message : 'Không xác định'));
                    $btn.html(originalHtml).removeClass('disabled');
                }
            },
            error: function() {
                alert('Có lỗi hệ thống xảy ra.');
                $btn.html(originalHtml).removeClass('disabled');
            }
        });
    });

    /**
     * Change Customer Modal Handler
     */
    $('#changeCustomerModal').on('shown.bs.modal', function() {
        $('.select2-customer').select2({
            dropdownParent: $('#changeCustomerModal'),
            ajax: {
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term,
                        action: 'search_customers_select2'
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            },
            placeholder: '-- Tìm theo tên hoặc mã --',
            minimumInputLength: 2
        });
    });

    $('.select2-customer').on('change', function() {
        $('#changeCustomerSubmitBtn').prop('disabled', !$(this).val());
    });

    $('#changeCustomerSubmitBtn').on('click', function() {
        var newUserId = $('#new_customer_id').val();
        var invoiceId = <?php echo $invoice_id; ?>;
        var changeServicesOwner = $('#change_services_owner').is(':checked') ? 1 : 0;
        
        if (!newUserId) return;
        
        
        var btn = $(this);
        var originalHtml = btn.html();
        btn.html('<i class="ph ph-spinner ph-spin me-2"></i>Đang xử lý...').prop('disabled', true);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'change_invoice_customer',
                invoice_id: invoiceId,
                new_user_id: newUserId,
                change_services_owner: changeServicesOwner
            },
            success: function(response) {
                if (response.success) {
                    // Success! Now recalculate to ensure everything is correct
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'recalculate_invoice_totals',
                            invoice_id: invoiceId
                        },
                        success: function() {
                            location.reload();
                        },
                        error: function() {
                            location.reload();
                        }
                    });
                } else {
                    alert('Lỗi: ' + response.data.message);
                    btn.html(originalHtml).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Có lỗi xảy ra: ' + error);
                btn.html(originalHtml).prop('disabled', false);
            }
        });
    });
});
</script>

<?php get_footer(); ?>