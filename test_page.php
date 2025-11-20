<?php
/*
    Template Name: Test Page
*/

echo "Test page loaded successfully!";
echo "<br>Current user: " . wp_get_current_user()->user_email;

get_footer();
?>
