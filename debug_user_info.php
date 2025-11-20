<?php
/*
    Template Name: Debug User Info
*/

global $wpdb;

$wp_user = wp_get_current_user();

echo "<h1>Debug User Information</h1>";

echo "<h2>WordPress User:</h2>";
echo "<pre>";
echo "ID: " . $wp_user->ID . "\n";
echo "Email: " . $wp_user->user_email . "\n";
echo "Display Name: " . $wp_user->display_name . "\n";
echo "Is Admin: " . (is_inova_admin() ? 'Yes' : 'No') . "\n";
echo "</pre>";

echo "<h2>Inova User (via get_inova_user):</h2>";
$inova_user = get_inova_user($wp_user->ID);
if ($inova_user) {
    echo "<pre>";
    echo "ID: " . $inova_user->id . "\n";
    echo "Name: " . $inova_user->name . "\n";
    echo "Email: " . $inova_user->email . "\n";
    echo "User Type: " . $inova_user->user_type . "\n";
    echo "Company: " . $inova_user->company_name . "\n";
    echo "</pre>";
} else {
    echo "❌ Not found via get_inova_user()\n";
}

echo "<h2>Try Email Lookup:</h2>";
$users_table = "{$wpdb->prefix}im_users";
$user_by_email = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM `$users_table` WHERE email = %s AND status = 'ACTIVE' LIMIT 1",
    $wp_user->user_email
));
if ($user_by_email) {
    echo "✅ Found by email: ID " . $user_by_email->id . " - " . $user_by_email->name . "\n";
} else {
    echo "❌ Not found by email\n";
}

echo "<h2>Try ID Lookup (WP ID as Inova ID):</h2>";
$user_by_id = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM `$users_table` WHERE id = %d AND status = 'ACTIVE' LIMIT 1",
    $wp_user->ID
));
if ($user_by_id) {
    echo "✅ Found by ID: " . $user_by_id->name . "\n";
} else {
    echo "❌ Not found by ID\n";
}

echo "<h2>User Meta - inova_id:</h2>";
$inova_id = get_user_meta($wp_user->ID, 'inova_id', true);
if ($inova_id) {
    echo "✅ inova_id meta exists: " . $inova_id . "\n";
} else {
    echo "❌ No inova_id meta set\n";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>← Go Back</a></p>";

get_footer();
?>
