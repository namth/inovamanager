<?php 
# Header for pages that don't require login
# Usage: get_header('nologin')
# This header allows public access without login redirect
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?php wp_title(); ?></title>
  <link rel="shortcut icon" href="<?php echo get_template_directory_uri(); ?>/assets/images/favicon.png" />
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <?php wp_head(); ?>
</head>

<body>
  <div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
