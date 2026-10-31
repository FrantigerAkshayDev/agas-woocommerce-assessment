<?php
/** Isolated test: hiding the WordPress toolbar must be scoped to the editor. */
define('ABSPATH', '/');
$GLOBALS['mock_page_id'] = 42;
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_activation_hook() {}
function absint($value) { return abs((int) $value); }
function get_option($name) { return $name === 'agas_ds_editor_page' ? 42 : null; }
function is_page($id) { return (int) $id === $GLOBALS['mock_page_id']; }
require dirname(__DIR__) . '/agas-design-studio.php';
function expect_equal($expected, $actual, $message) {
    if ($expected !== $actual) throw new RuntimeException($message);
    echo "PASS $message\n";
}
expect_equal(false, AGAS_Design_Studio::editor_admin_bar_visibility(true), 'admin toolbar hidden on Design Studio page');
expect_equal(false, AGAS_Design_Studio::editor_admin_bar_visibility(false), 'false toolbar setting preserved');
$GLOBALS['mock_page_id'] = 43;
expect_equal(true, AGAS_Design_Studio::editor_admin_bar_visibility(true), 'toolbar remains available on other frontend pages');
echo "EDITOR_ADMIN_BAR_TESTS=PASS\n";
