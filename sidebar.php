<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <li class="nav-item">
            <a class="nav-link" href="<?php /* echo homepage url */ echo get_bloginfo('url'); ?>">
                <i class="menu-icon ph ph-grid-four"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>
        <li class="nav-item nav-category">Quản lý dịch vụ</li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/list-website/'); ?>">
                <i class="menu-icon ph ph-globe"></i>
                <span class="menu-title">Website</span>
                <!-- <i class="menu-arrow"></i> -->
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-ten-mien/'); ?>">
                <i class="menu-icon ph ph-globe-stand"></i>
                <span class="menu-title">Tên miền</span>
                <!-- <i class="menu-arrow"></i> -->
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-hosting'); ?>">
                <i class="menu-icon ph ph-cloud"></i>
                <span class="menu-title">Hosting</span>
                <!-- <i class="menu-arrow"></i> -->
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-bao-tri/'); ?>">
                <i class="menu-icon ph ph-wrench"></i>
                <span class="menu-title">Bảo trì</span>
                <!-- <i class="menu-arrow"></i> -->
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/service-list/'); ?>">
                <i class="menu-icon ph ph-desktop-tower"></i>
                <span class="menu-title">Danh sách dịch vụ</span>
                <!-- <i class="menu-arrow"></i> -->
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-doi-tac/'); ?>">
                <i class="menu-icon ph ph-users-three"></i>
                <span class="menu-title">Đối tác</span>
                <!-- <i class="menu-arrow"></i> -->
            </a>
        </li>
        <li class="nav-item nav-category">Dành cho quản trị viên</li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-san-pham/'); ?>">
                <i class="menu-icon ph ph-package"></i>
                <span class="menu-title">Danh mục sản phẩm</span>
                <!-- <i class="menu-arrow"></i> -->
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/lich-su/'); ?>">
                <i class="menu-icon ph ph-stack-overflow-logo"></i>
                <span class="menu-title">Lịch sử</span>
                <!-- <i class="menu-arrow"></i> -->
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/danh-sach-hoa-don/'); ?>">
                <i class="menu-icon ph ph-file-text"></i>
                <span class="menu-title">Hóa đơn</span>
            </a>
        </li>
        <li class="nav-item nav-category">Cài đặt</li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo home_url('/api-settings/'); ?>">
                <i class="menu-icon ph ph-key"></i>
                <span class="menu-title">API Settings</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo admin_url(); ?>">
                <i class="menu-icon ph ph-user-circle"></i>
                <span class="menu-title">Tài khoản</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#">
                <i class="menu-icon ph ph-sign-out"></i>
                <span class="menu-title">Log out</span>
            </a>
        </li>
    </ul>
</nav>