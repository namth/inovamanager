<?php
$is_admin = is_inova_admin();
$user_type = get_inova_user_type();
?>

<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <!-- Dashboard - ALL USERS -->
        <li class="nav-item">
            <a class="nav-link" href="<?php echo get_bloginfo('url'); ?>">
                <i class="menu-icon ph ph-grid-four"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>

        <!-- Service Management - ALL USERS (data will be filtered) -->
        <li class="nav-item nav-category">Quản lý dịch vụ</li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/list-website/'); ?>">
                <i class="menu-icon ph ph-globe"></i>
                <span class="menu-title">Website</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-ten-mien/'); ?>">
                <i class="menu-icon ph ph-globe-stand"></i>
                <span class="menu-title">Tên miền</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-hosting'); ?>">
                <i class="menu-icon ph ph-cloud"></i>
                <span class="menu-title">Hosting</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-bao-tri/'); ?>">
                <i class="menu-icon ph ph-wrench"></i>
                <span class="menu-title">Bảo trì</span>
            </a>
        </li>

        <?php if ($is_admin): ?>
        <!-- Service List & Cloudflare Import - ADMIN ONLY -->
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/service-list/'); ?>">
                <i class="menu-icon ph ph-desktop-tower"></i>
                <span class="menu-title">Danh sách dịch vụ</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/cloudflare-import/'); ?>">
                <i class="menu-icon ph ph-cloud-arrow-up"></i>
                <span class="menu-title">Import từ Cloudflare</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Invoice - ALL USERS (data will be filtered) -->
         <li class="nav-item nav-category">Quản lý thanh toán</li>
         <li class="nav-item">
             <a class="nav-link" href="<?php echo home_url('/danh-sach-hoa-don/'); ?>">
                 <i class="menu-icon ph ph-file-text"></i>
                 <span class="menu-title">Hóa đơn</span>
             </a>
         </li>
         <?php if ($is_admin): ?>
         <li class="nav-item">
             <a class="nav-link" href="<?php echo home_url('/cau-hinh-hoa-don-do/'); ?>">
                 <i class="menu-icon ph ph-gear"></i>
                 <span class="menu-title">Cấu hình hóa đơn đỏ</span>
             </a>
         </li>
         <?php else: ?>
         <li class="nav-item">
             <a class="nav-link" href="<?php echo home_url('/cau-hinh-hoa-don-do-ca-nhan/'); ?>">
                 <i class="menu-icon ph ph-gear"></i>
                 <span class="menu-title">Cấu hình hóa đơn đỏ</span>
             </a>
         </li>
         <?php endif; ?>
        
        <!-- Commission View - PARTNER ONLY -->
        <?php if (!$is_admin && ($user_type === 'PARTNER')): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/partner-commissions/'); ?>">
                <i class="menu-icon ph ph-wallet"></i>
                <span class="menu-title">Hoa hồng của tôi</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($is_admin): ?>
        <!-- Commission Management - ADMIN ONLY -->
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/partner-commissions/'); ?>">
                <i class="menu-icon ph ph-wallet"></i>
                <span class="menu-title">Hoa hồng Đối tác</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/partner-discount-rates/'); ?>">
                <i class="menu-icon ph ph-percent"></i>
                <span class="menu-title">Tỷ lệ Chiết khấu</span>
            </a>
        </li>
        <!-- Admin Section - ADMIN ONLY -->
        <li class="nav-item nav-category">Dành cho quản trị viên</li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-doi-tac/'); ?>">
                <i class="menu-icon ph ph-users-three"></i>
                <span class="menu-title">Đối tác</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/quan-ly-user/'); ?>">
                <i class="menu-icon ph ph-users"></i>
                <span class="menu-title">Quản lý User</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-san-pham/'); ?>">
                <i class="menu-icon ph ph-package"></i>
                <span class="menu-title">Danh mục sản phẩm</span>
            </a>
        </li>
        <?php endif; ?>


        <!-- Account & Logout - ALL USERS -->
        <li class="nav-item nav-category">Cài đặt</li>
        <?php if ($is_admin): ?>
        <!-- Settings - ADMIN ONLY -->
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/api-settings/'); ?>">
                <i class="menu-icon ph ph-key"></i>
                <span class="menu-title">API Settings</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/system-settings'); ?>">
                <i class="menu-icon ph ph-gear"></i>
                <span class="menu-title">Kết nối hệ thống khác</span>
            </a>
        </li>
        <?php endif; ?>

         <li class="nav-item">
             <a class="nav-link" href="<?php echo $is_admin ? admin_url() : home_url('/partner/'); ?>">
                 <i class="menu-icon ph ph-user-gear"></i>
                 <span class="menu-title">Tài khoản</span>
             </a>
         </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/cau-hinh-email-thong-bao/'); ?>">
                <i class="menu-icon ph ph-envelope-simple-open"></i>
                <span class="menu-title">Email thông báo</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo wp_logout_url(home_url()); ?>">
                <i class="menu-icon ph ph-sign-out"></i>
                <span class="menu-title">Log out</span>
            </a>
        </li>
    </ul>
</nav>