<?php
/**
 * AGAS Printworks - responsive customer storefront.
 * This Storefront child theme deliberately leaves the AGAS Design Studio plugin,
 * WooCommerce prices, orders, and checkout logic unchanged.
 */
if (!defined('ABSPATH')) { exit; }

function agas_pw_setup() {
    load_theme_textdomain('agas-printworks', get_stylesheet_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('custom-logo', array('height' => 100, 'width' => 280, 'flex-height' => true, 'flex-width' => true));
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
    add_theme_support('responsive-embeds');
    add_theme_support('woocommerce', array('thumbnail_image_width' => 600, 'single_image_width' => 900, 'product_grid' => array('default_rows' => 2, 'min_rows' => 1, 'max_rows' => 4, 'default_columns' => 3, 'min_columns' => 1, 'max_columns' => 4)));
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    register_nav_menus(array('header' => __('Main navigation', 'agas-printworks'), 'footer' => __('Footer navigation', 'agas-printworks')));
}
add_action('after_setup_theme', 'agas_pw_setup');

function agas_pw_assets() {
    $version = wp_get_theme()->get('Version') ?: '1.1.0';
    // Storefront enqueues the child stylesheet itself; don't output a duplicate.
    // Storefront may enqueue theme-specific skins. Our stylesheet takes precedence.
    wp_enqueue_script('agas-printworks', get_stylesheet_directory_uri() . '/assets/js/storefront.js', array(), $version, true);
}
add_action('wp_enqueue_scripts', 'agas_pw_assets', 70);

/** Our woocommerce.php provides its own <main>; avoid Storefront's extra content wrappers. */
function agas_pw_storefront_wrapper_compat() {
    if (!function_exists('is_woocommerce') || !is_woocommerce()) return;
    remove_action('woocommerce_before_main_content', 'storefront_before_content', 10);
    remove_action('woocommerce_after_main_content', 'storefront_after_content', 10);
    remove_action('woocommerce_sidebar', 'storefront_get_sidebar', 10);
}
add_action('wp', 'agas_pw_storefront_wrapper_compat', 50);

/** Marketing descriptions only; do not use these values to alter actual price. */
function agas_pw_catalog() {
    return array(
        'outdoor vinyl banner' => array(
            'name' => 'Outdoor Vinyl Banner', 'eyebrow' => 'MAKE A BIG STATEMENT',
            'description' => 'Create a large-format banner for an event, opening, promotion, or message that deserves attention.',
            'detail' => 'Design your banner with custom text, graphics, sizes and available finishing options.',
            'image' => 'banner.svg', 'num' => '01', 'tone' => 'sun',
        ),
        'custom flag' => array(
            'name' => 'Custom Flag', 'eyebrow' => 'LET IT FLY',
            'description' => 'Build a distinctive flag for events, teams, displays or your own creative ideas.',
            'detail' => 'Personalize the flag artwork, experiment with layouts and choose a print size in the editor.',
            'image' => 'flag.svg', 'num' => '02', 'tone' => 'blue',
        ),
        'yard sign' => array(
            'name' => 'Yard Sign', 'eyebrow' => 'SAY IT OUT LOUD',
            'description' => 'Make your message visible with a personalized sign for homes, listings, businesses or celebrations.',
            'detail' => 'Start from a sign template, edit the wording, and choose single- or double-sided artwork.',
            'image' => 'yard-sign.svg', 'num' => '03', 'tone' => 'lilac',
        ),
    );
}

function agas_pw_products() {
    static $found = null;
    if ($found !== null) return $found;
    $found = array();
    if (!function_exists('wc_get_products')) return $found;
    // Only three demo products; keep product records as the source of truth.
    $products = wc_get_products(array('limit' => 100, 'status' => 'publish', 'type' => 'simple'));
    foreach ($products as $product) {
        $key = strtolower(trim($product->get_name()));
        if (isset(agas_pw_catalog()[$key])) $found[$key] = $product;
    }
    return $found;
}
function agas_pw_info($product_or_name) {
    if (is_object($product_or_name) && method_exists($product_or_name, 'get_name')) $name = $product_or_name->get_name();
    else $name = (string) $product_or_name;
    $catalog = agas_pw_catalog();
    return $catalog[strtolower(trim($name))] ?? null;
}
function agas_pw_design_url($product = null) {
    $pid = (is_object($product) && method_exists($product, 'get_id')) ? (int) $product->get_id() : absint($product);
    if (class_exists('AGAS_Design_Studio') && is_callable(array('AGAS_Design_Studio', 'gallery_url'))) {
        return AGAS_Design_Studio::gallery_url($pid);
    }
    $fallback = home_url('/agas-templates/');
    return $pid ? add_query_arg('agas_product', $pid, $fallback) : $fallback;
}
function agas_pw_shop_url() {
    $url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '';
    return $url ? $url : home_url('/#products');
}
function agas_pw_cart_url() {
    return function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
}
function agas_pw_account_url() {
    $url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '';
    return $url ? $url : home_url('/my-account/');
}
function agas_pw_theme_image($name) {
    return get_stylesheet_directory_uri() . '/assets/images/' . basename($name);
}
function agas_pw_icon($name, $size = 22) {
    $icons = array(
        'arrow' => '<path d="M4 12h16m-7-7 7 7-7 7"/>',
        'arrow-up' => '<path d="M5 17 19 3m-11 0h11v11"/>',
        'bag' => '<path d="M4 8h16l-1 13H5L4 8Zm4 0V6a4 4 0 0 1 8 0v2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="M5 5l14 14M19 5 5 19"/>',
        'check' => '<path d="m4 12 5 5L20 6"/>',
        'spark' => '<path d="m12 2 2.7 6.6L21 11l-6.3 2.5L12 20l-2.7-6.5L3 11l6.3-2.4L12 2Z"/>',
        'edit' => '<path d="m4 17 1 3 3-1L20 7a2 2 0 0 0-3-3L5 16"/><path d="m14 6 4 4"/>',
        'layers' => '<path d="m12 2 9 5-9 5-9-5 9-5Zm-9 10 9 5 9-5M3 17l9 5 9-5"/>',
        'shield' => '<path d="m12 2 8 4v6c0 6-4 9-8 10-4-1-8-4-8-10V6l8-4Z"/><path d="m9 12 2 2 4-4"/>',
    );
    if (!isset($icons[$name])) return '';
    return '<svg class="pw-icon" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';
}

/** Every designable item should enter through the design gallery, not a direct zero-priced Woo add-to-cart. */
add_filter('woocommerce_loop_add_to_cart_link', function($html, $product) {
    if (!agas_pw_info($product)) return $html;
    return '<a class="button pw-loop-design" href="' . esc_url(agas_pw_design_url($product)) . '" aria-label="' . esc_attr(sprintf(__('Customize %s', 'agas-printworks'), $product->get_name())) . '">Customize ' . agas_pw_icon('arrow-up', 18) . '</a>';
}, 20, 2);

add_filter('woocommerce_get_price_html', function($price, $product) {
    return agas_pw_info($product) ? '<span class="pw-price-caption">Price calculated in designer</span>' : $price;
}, 20, 2);

add_filter('woocommerce_placeholder_img_src', function($url) {
    global $product;
    $info = $product && is_object($product) ? agas_pw_info($product) : null;
    return $info ? agas_pw_theme_image($info['image']) : $url;
}, 20);

add_filter('woocommerce_short_description', function($description) {
    if (!function_exists('is_product') || !is_product()) return $description;
    global $product;
    $info = $product && is_object($product) ? agas_pw_info($product) : null;
    return $info ? '<p>' . esc_html($info['description']) . '</p>' : $description;
}, 20);

function agas_pw_prepare_product_page() {
    if (!function_exists('is_product') || !is_product()) return;
    global $post;
    $product = $post ? wc_get_product($post->ID) : null;
    if (!agas_pw_info($product)) return;
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    add_action('woocommerce_single_product_summary', 'agas_pw_product_helper', 12);
    add_action('woocommerce_single_product_summary', 'agas_pw_product_steps', 32);
}
add_action('wp', 'agas_pw_prepare_product_page');
function agas_pw_product_helper() {
    echo '<div class="pw-custom-tag">' . agas_pw_icon('spark', 16) . '<span>' . esc_html__('CUSTOMIZE ONLINE', 'agas-printworks') . '</span></div>';
    echo '<p class="pw-detail-note">' . esc_html__('Choose a design, personalize it, and get the final price based on your dimensions and options.', 'agas-printworks') . '</p>';
}
function agas_pw_product_steps() {
    echo '<div class="pw-pdp-steps"><span>' . agas_pw_icon('check', 17) . ' Select a template or start blank</span><span>' . agas_pw_icon('check', 17) . ' Edit both sides if needed</span><span>' . agas_pw_icon('check', 17) . ' Review price before checkout</span></div>';
}

/** The default Woo shop listing uses three columns, with no stale Woo price/sale badges for customized items. */
add_filter('loop_shop_columns', function() { return 3; }, 25);
add_filter('loop_shop_per_page', function() { return 12; }, 25);

/** Static gallery/editor pages are rendered by the companion plugin, and remain untouched by template changes. */
function agas_pw_body_class($classes) {
    $classes[] = 'agas-pw-theme';
    return $classes;
}
add_filter('body_class', 'agas_pw_body_class');
