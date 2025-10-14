<?php
/* 
    Template Name: Invoice Success
    Purpose: Display success message after creating invoices
*/

global $wpdb;
$invoice_table = $wpdb->prefix . 'im_invoices';

// Get invoice ID from URL parameter
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
$message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

// Get invoice details if ID provided
$invoice = null;
if ($invoice_id) {
    $invoice = $wpdb->get_row($wpdb->prepare("
        SELECT i.*, u.name AS customer_name
        FROM $invoice_table i
        LEFT JOIN {$wpdb->prefix}im_users u ON i.user_id = u.id
        WHERE i.id = %d
    ", $invoice_id));
}

get_header(); 
?>

<div class="main-panel">
    <div class="content-wrapper">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <!-- Success Icon -->
                        <div class="mb-4">
                            <i class="ph ph-check-circle text-success" style="font-size: 5rem;"></i>
                        </div>
                        
                        <!-- Success Message -->
                        <h2 class="text-success mb-3">Tạo hóa đơn thành công!</h2>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success mb-4">
                                <?php echo esc_html($message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($invoice): ?>
                            <div class="row justify-content-center mb-4">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">Thông tin hóa đơn</h5>
                                            <table class="table table-sm table-borderless">
                                                <tr>
                                                    <td class="text-muted">Mã hóa đơn:</td>
                                                    <td><strong><?php echo esc_html($invoice->invoice_code); ?></strong></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Khách hàng:</td>
                                                    <td><?php echo esc_html($invoice->customer_name); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Tổng tiền:</td>
                                                    <td><strong><?php echo number_format($invoice->total_amount); ?> VNĐ</strong></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Trạng thái:</td>
                                                    <td>
                                                        <?php
                                                        $status_classes = [
                                                            'draft' => 'bg-secondary',
                                                            'pending' => 'bg-warning',
                                                            'paid' => 'bg-success',
                                                            'canceled' => 'bg-danger',
                                                            'pending_completion' => 'bg-info'
                                                        ];
                                                        $status_labels = [
                                                            'draft' => 'Nháp',
                                                            'pending' => 'Chờ thanh toán',
                                                            'paid' => 'Đã thanh toán',
                                                            'canceled' => 'Đã hủy',
                                                            'pending_completion' => 'Chờ hoàn thành'
                                                        ];
                                                        $status_class = $status_classes[$invoice->status] ?? 'bg-secondary';
                                                        $status_label = $status_labels[$invoice->status] ?? $invoice->status;
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>">
                                                            <?php echo $status_label; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-center gap-3">
                            <?php if ($invoice): ?>
                                <a href="<?php echo home_url('/chi-tiet-hoa-don/?invoice_id=' . $invoice->id); ?>" 
                                   class="btn btn-primary">
                                    <i class="ph ph-eye me-2"></i>Xem chi tiết hóa đơn
                                </a>
                            <?php endif; ?>
                            
                            <a href="<?php echo home_url('/danh-sach-hoa-don/'); ?>" 
                               class="btn btn-outline-primary">
                                <i class="ph ph-list me-2"></i>Danh sách hóa đơn
                            </a>
                            
                            <a href="<?php echo home_url('/danh-sach-dich-vu/'); ?>" 
                               class="btn btn-outline-secondary">
                                <i class="ph ph-arrow-left me-2"></i>Quay lại dịch vụ
                            </a>
                        </div>
                        
                        <!-- Additional Info -->
                        <div class="mt-5 text-muted">
                            <small>
                                <i class="ph ph-info me-1"></i>
                                Bạn có thể quản lý hóa đơn này từ trang danh sách hóa đơn hoặc xem chi tiết để thực hiện các thao tác khác.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
