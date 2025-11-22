<?php 
# if not login, redirect to login page
if (!is_user_logged_in()) {
    wp_redirect(home_url('login'));
    exit;
}

$current_user = wp_get_current_user();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?php wp_title(); ?></title>
  <link rel="shortcut icon" href="<?php echo get_template_directory_uri(); ?>/assets/images/favicon.png" />
  <?php wp_head(); ?>
</head>

<body>
  <div class="container-scroller">
    <!-- partial:../../partials/_navbar.html -->
    <nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
        <div class="me-3">
          <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-bs-toggle="minimize">
            <span class="icon-menu"></span>
          </button>
        </div>
        <div>
          <a class="navbar-brand brand-logo" href="<?php echo home_url(); ?>">
            <img src="<?php echo get_template_directory_uri(); ?>/img/inova_logo.png" alt="logo" />
          </a>
          <a class="navbar-brand brand-logo-mini" href="<?php echo home_url(); ?>">
            <img src="<?php echo get_template_directory_uri(); ?>/img/inova_logo_icon.png" alt="logo" />
          </a>
        </div>
      </div>
      <div class="navbar-menu-wrapper d-flex align-items-top">
        <!-- <ul class="navbar-nav">
          <li class="nav-item fw-semibold d-none d-lg-block ms-0">
            <h1 class="welcome-text" style="display: block;">Good Morning, <span class="text-black fw-bold">John
                Doe</span></h1>
            <h3 class="welcome-sub-text" style="display: block;">Your performance summary this week </h3>
          </li>
        </ul> -->
        <ul class="navbar-nav ms-auto">
          <!-- <li class="nav-item">
            <form class="search-form" action="#">
              <i class="icon-search"></i>
              <input type="search" class="form-control" placeholder="Search Here" title="Search here">
            </form>
          </li> -->

          <!-- Cart Icon -->
          <li class="nav-item me-3">
            <a class="nav-link position-relative" id="cart-icon" href="<?php echo home_url('/gio-hang/'); ?>" title="Giỏ hàng">
              <i class="ph ph-shopping-cart" style="font-size: 24px;"></i>
              <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="cart-count-badge" style="display: none; font-size: 10px;">0</span>
            </a>
          </li>

          <li class="nav-item">
            Xin chào, <span class="fw-semibold"><?php echo $current_user->display_name; ?></span>
          </li>

          <li class="nav-item dropdown d-none d-lg-block user-dropdown">
            <a class="nav-link" id="UserDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
              <img class="img-xs rounded-circle"
                src="<?php 
                        # get user avatar and display url of avatar
                        echo get_avatar_url($current_user->ID);
                      ?>" alt="Profile image">
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
              <div class="dropdown-header text-center" style="cursor: pointer;" onclick="window.location.href='<?php echo esc_url(admin_url()); ?>'">
                <img class="img-md rounded-circle"
                  src="<?php 
                        # get user avatar and display url of avatar
                        echo get_avatar_url($current_user->ID);
                      ?>" alt="Profile image">
                <p class="mb-1 mt-3 fw-semibold"><?php echo $current_user->display_name; ?></p>
                <p class="fw-light text-muted mb-0"><?php echo $current_user->user_email; ?></p>
              </div>
              <!-- <a class="dropdown-item"><i class="dropdown-item-icon mdi mdi-account-outline text-primary me-2"></i> My
                Profile <span class="badge badge-pill badge-danger">1</span></a> -->
              <a class="dropdown-item" href="<?php echo wp_logout_url(home_url()); ?>"><i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>Sign Out</a>
            </div>
          </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button"
          data-bs-toggle="offcanvas">
          <span class="mdi mdi-menu"></span>
        </button>
      </div>
    </nav>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:../../partials/_sidebar.html -->
      <?php 
        get_sidebar();
      ?>
      <!-- partial -->
      <div class="main-panel">