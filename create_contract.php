<?php
/* 
    Template Name: Create Contract
*/
get_header();

?>
<div class="content-wrapper">
    <div class="card card-rounded">
        <div class="card-body">
            <div class="row">
                <div class="col-lg-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="display-2">Tạo tài liệu mới</h2>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="wrapper d-flex justify-content-center align-items-center flex-column py-2">
                            <?php 
                            if (isset($notification)) {
                                echo '<div class="alert alert-success" role="alert">' . $notification . '</div>';
                            } else {
                            ?>
                            <div class="d-flex justify-content-center mb-3">
                                <i class="fa fa-file-text-o fa-150p"></i>
                                <div class="wrapper ms-3">
                                    <p class="ms-1 mb-1 fw-bold">Mẫu hợp đồng lao động 2024</p>
                                </div>
                            </div>
                            <form
                                class="forms-sample col-md-6 col-lg-4 d-flex justify-content-center flex-column text-center"
                                action="" method="post" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="exampleInputUsername1">Tên hợp đồng mới</label>
                                    <input type="text" class="form-control text-center" id="exampleInputUsername1" name="contract_name"
                                        placeholder="Tên file tài liệu mới" value="Hợp đồng lao động - {your_name}">
                                </div>
                                <div class="d-flex mb-3 justify-content-center">
                                    <i class="fa fa-folder-open-o fa-150p"></i>
                                    <div class="wrapper ms-3">
                                        <p class="ms-1 mb-1 fw-bold">Thư mục đích:
                                            https://drive.google.com/drive/u/3/folders/1J7DMIPwy4YGyAN6sI6gYetd-UK-8vnoV
                                        </p>
                                    </div>
                                </div>
                                <div class="form-group mt-5 mb-4">
                                    <h4 class="card-title">Dữ liệu thay thế</h4>
                                </div>
                                <div class="form-group d-flex flex-column">
                                    <label>Chọn nhân sự</label>
                                    <select class="js-example-basic-single" name="employee_id">
                                        <option value="1">Trần Hải Nam</option>
                                        <option value="2">Nguyễn Duy Sơn</option>
                                    </select>
                                </div>
                                <?php
                                wp_nonce_field('post_contract', 'post_contract_field');
                                ?>
                                <input type="hidden" name="sourceFileId" value="1a4l5i3RiMBkxwMWj20bCR3drrfM8IRUDyBAZ_EVYGOo">
                                <input type="hidden" name="folderId" value="1J7DMIPwy4YGyAN6sI6gYetd-UK-8vnoV">
                                <div class="form-group d-flex justify-content-center">
                                    <button type="submit"
                                        class="btn btn-primary btn-icon-text me-2 d-flex align-items-center"><span
                                            class="mdi mdi-creation-outline btn-icon-prepend fa-150p"></span> Tạo tài
                                        liệu</button>
                                    <!-- <button class="btn btn-light btn-icon-text"><span class="mdi mdi-close"></span> Quay lại</button> -->
                                </div>
                            </form>
                            <?php 
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
get_footer();