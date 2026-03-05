<?php
/**
 * Test Copy: InfoSystems IWMS (Minimal Test)
 * Description: Minimal test version
 * Version: 1.0
 */

if (!defined('ABSPATH'))
    exit;

define('IWMS_PATH', plugin_dir_path(__FILE__));
define('IWMS_URL', plugin_dir_url(__FILE__));

// Minimal activation hook
function iwms_activate_plugin() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Just create one simple table
    $table = $wpdb->prefix . 'iwms_test';
    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        test varchar(50) DEFAULT '',
        PRIMARY KEY (id)
    ) $charset_collate;";

    dbDelta($sql);
}
register_activation_hook(__FILE__, 'iwms_activate_plugin');

// Minimal shortcode
add_shortcode('iwms_dashboard', function() {
    return '<h2>IWMS Dashboard - Test Version</h2><p>Plugin activated successfully!</p>';
});
