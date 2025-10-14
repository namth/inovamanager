<?php
/* 
Template Name: Login
*/

// process login
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']) ? true : false;

    $user = wp_signon([
        'user_login' => $username,
        'user_password' => $password,
        'remember' => $remember_me
    ]);

    if (is_wp_error($user)) {
        echo '<script>alert("Tên đăng nhập hoặc mật khẩu không đúng")</script>';
    } else {
        wp_redirect(home_url());
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- Required meta tags -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Star Admin2 </title>
<!-- plugins:css -->
<?php
wp_head();
?>
</head>

<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth px-0 flex-column justify-content-center loginbg">
                <div class="w200 mb-4">
                    <img src="<?php echo get_template_directory_uri(); ?>/img/inova_logo.png" alt="logo" width="200px">
                </div>
                <div class="row w-100 mx-0">
                    <div class="col-lg-4 mx-auto mb-5">
                        <div class="auth-form-light text-left py-5 px-4 px-sm-5">
                            <form class="pt-3" action="" method="post" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="exampleInputEmail1">Tên đăng nhập</label>
                                    <input type="text" class="form-control form-control-lg" id="exampleInputEmail1"
                                        name="username" placeholder="Username">
                                </div>
                                <div class="form-group">
                                    <label for="exampleInputPassword1">Mật khẩu</label>
                                    <input type="password" class="form-control form-control-lg"
                                        id="exampleInputPassword1" name="password" placeholder="Password">
                                </div>
                                <div class="my-2 d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <label class="form-check-label text-muted">
                                            <input type="checkbox" class="form-check-input" name="remember_me"> Nhớ
                                            mật khẩu</label>
                                    </div>
                                </div>
                                <div class="mt-3 d-grid gap-2">
                                    <input type="submit" class="btn btn-block btn-dark btn-lg fw-medium auth-form-btn"
                                        value="Đăng nhập">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- content-wrapper ends -->
    </div>
    <!-- container-scroller -->
    <?php
    wp_footer();
    ?>
</body>

</html>