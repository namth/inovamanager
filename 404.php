<?php
/**
 * The template for displaying 404 pages (not found)
 */

get_header();
?>

<div class="content-wrapper d-flex flex-column justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="text-center">
        <h1 class="display-1 mb-4">404</h1>
        <h2 class="mb-4">Không tìm thấy trang</h2>
        <p class="text-muted mb-5">Trang bạn đang tìm kiếm không tồn tại hoặc đã được di chuyển.</p>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-dark btn-icon-text d-inline-flex align-items-center border-radius-9">
            <i class="ph ph-house btn-icon-prepend fa-150p me-2"></i>
            <span class="fw-bold">Quay về trang chủ</span>
        </a>
    </div>
</div>

<?php
get_footer();
?>