<?php
/* 
    Template Name: Product Catalog List
*/

global $wpdb;
$table_name = $wpdb->prefix . 'im_product_catalog';

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    $wpdb->delete($table_name, array('id' => $product_id));
    // Redirect to remove the action from URL
    wp_redirect(home_url('/danh-sach-san-pham/'));
    exit;
}

// Get the filter value
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';

// Get all products with optional filter
if (!empty($filter_type) && $filter_type != 'all') {
    $products = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE service_type = %s ORDER BY service_type, name", $filter_type));
} else {
    $products = $wpdb->get_results("SELECT * FROM $table_name ORDER BY service_type, name");
}

get_header();
?>
<div class="content-wrapper mt-5">
    <div class="row">
        <div class="col-lg-12" id="relative">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h3 class="page-title">Danh sách sản phẩm dịch vụ</h3>
                </div>
                <div>
                    <a href="<?php echo home_url('/them-moi-san-pham/'); ?>" class="btn btn-primary btn-icon-text d-flex align-items-center">
                        <i class="ph ph-plus-circle btn-icon-prepend"></i>
                        <span>Thêm mới sản phẩm</span>
                    </a>
                </div>
            </div>

            <div class="mb-3">
                <div class="btn-group bg-white" role="group" aria-label="Loại dịch vụ">
                    <a href="<?php echo home_url('/danh-sach-san-pham/'); ?>" class="btn border-primary <?php echo empty($filter_type) || $filter_type === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Tất cả
                    </a>
                    <a href="<?php echo home_url('/danh-sach-san-pham/?filter_type=Domain'); ?>" class="btn border-primary <?php echo $filter_type === 'Domain' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Domain
                    </a>
                    <a href="<?php echo home_url('/danh-sach-san-pham/?filter_type=Hosting'); ?>" class="btn border-primary <?php echo $filter_type === 'Hosting' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Hosting
                    </a>
                    <a href="<?php echo home_url('/danh-sach-san-pham/?filter_type=Maintenance'); ?>" class="btn border-primary <?php echo $filter_type === 'Maintenance' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Maintenance
                    </a>
                    <a href="<?php echo home_url('/danh-sach-san-pham/?filter_type=Website'); ?>" class="btn border-primary <?php echo $filter_type === 'Website' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Website
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Loại dịch vụ</th>
                                    <th>Tên sản phẩm</th>
                                    <th>Mô tả</th>
                                    <th>Giá cơ bản</th>
                                    <th>Chu kỳ</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">Chưa có sản phẩm nào</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo $product->id; ?></td>
                                        <td>
                                            <?php 
                                            // Display service type with icon
                                            $type_icon = '';
                                            $type_class = '';
                                            
                                            switch ($product->service_type) {
                                                case 'Hosting':
                                                    $type_icon = 'ph-cloud';
                                                    $type_class = 'd-flex fit-content badge border-radius-9 btn-inverse-danger';
                                                    break;
                                                case 'Domain':
                                                    $type_icon = 'ph-globe';
                                                    $type_class = 'd-flex fit-content badge border-radius-9 btn-inverse-primary';
                                                    break;
                                                case 'Maintenance':
                                                    $type_icon = 'ph-wrench';
                                                    $type_class = 'd-flex fit-content badge border-radius-9 btn-inverse-warning';
                                                    break;
                                                case 'Website':
                                                    $type_icon = 'ph-browser';
                                                    $type_class = 'd-flex fit-content badge border-radius-9 btn-inverse-info';
                                                    break;
                                                default:
                                                    $type_icon = 'ph-package';
                                                    $type_class = 'd-flex fit-content badge bg-secondary border-radius-9';
                                            }
                                            
                                            echo '<span class="' . $type_class . '"><i class="ph ' . $type_icon . ' me-1"></i>' . $product->service_type . '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo $product->name; ?></td>
                                        <td><?php echo !empty($product->description) ? substr($product->description, 0, 50) . '...' : ''; ?></td>
                                        <td><?php echo number_format($product->base_price, 0, ',', '.') . ' VNĐ'; ?></td>
                                        <td><?php echo $product->billing_cycle; ?></td>
                                        <td>
                                            <?php
                                            if ($product->is_active) {
                                                echo '<span class="d-flex fit-content badge border-radius-9 bg-success">Đang hoạt động</span>';
                                            } else {
                                                echo '<span class="d-flex fit-content badge border-radius-9 bg-danger">Không hoạt động</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="d-flex">
                                                <a href="<?php echo home_url('/sua-san-pham/?id=' . $product->id); ?>" class="nav-link text-warning me-2">
                                                    <i class="ph ph-pencil-simple btn-icon-prepend fa-150p"></i>
                                                </a>
                                                <a href="?action=delete&id=<?php echo $product->id; ?>" class="nav-link text-danger"
                                                   onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này?');">
                                                    <i class="ph ph-trash btn-icon-prepend fa-150p"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
get_footer();
?>