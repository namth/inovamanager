<nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-bottom d-flex align-items-bottom flex-row headerLight">
  <div class="col-md-12 d-flex justify-content-center w-100">
        <div class="p-3 d-flex justify-content-between flex-row w-100 max-w600">
          <a href="<?php echo home_url('/website-list/'); ?>" class="d-flex justify-content-center flex-column text-center nav-link"><i class="ph ph-globe icon-lg"></i><span>Website</span></a>
          <a href="<?php echo home_url('/hosting-list/'); ?>" class="d-flex justify-content-center flex-column text-center nav-link"><i class="ph ph-cloud icon-lg"></i><span>Hosting</span></a>
          <a href="<?php echo home_url('/domain-list/'); ?>" class="d-flex justify-content-center flex-column text-center nav-link"><i class="ph ph-globe-stand icon-lg"></i><span>Domain</span></a>
          <a href="<?php echo home_url('/maintenance-list/'); ?>" class="d-flex justify-content-center flex-column text-center nav-link"><i class="ph ph-wrench icon-lg"></i><span>Bảo trì</span></a>
          <a href="<?php echo home_url('/invoice-list/'); ?>" class="d-flex justify-content-center flex-column text-center nav-link"><i class="ph ph-file-text icon-lg"></i><span>Hóa đơn</span></a>
        </div>
  </div>
</nav>
  
<?php wp_footer(); ?>
</body>

</html>