<?php
/**
 * Plugin Name: AGAS Design Studio
 * Description: Custom WooCommerce template gallery, online canvas designer, and order integration.
 * Version: 0.1.19
 * Requires Plugins: woocommerce
 * Text Domain: agas-design-studio
 */

defined('ABSPATH') || exit;

final class AGAS_Design_Studio {
    const VERSION = '0.1.19';
    const REST_NS = 'agas-design/v1';

    public static function boot() {
        add_action('init', array(__CLASS__, 'register_types'), 5);
        add_action('init', array(__CLASS__, 'seed_once'), 99);
        add_action('init', array(__CLASS__, 'upgrade_logo_slots'), 100);
        add_filter('show_admin_bar', array(__CLASS__, 'editor_admin_bar_visibility'), 999);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_page_assets'));
        add_filter('template_include', array(__CLASS__, 'standalone_page_template'), 99);
        add_shortcode('agas_template_gallery', array(__CLASS__, 'gallery_shortcode'));
        add_shortcode('agas_designer', array(__CLASS__, 'designer_shortcode'));
        add_action('woocommerce_single_product_summary', array(__CLASS__, 'product_cta'), 35);
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('template_redirect', array(__CLASS__, 'maybe_frontend_add_to_cart'), 1);
        add_action('admin_post_agas_design_add_cart', array(__CLASS__, 'add_to_cart'));
        add_action('admin_post_nopriv_agas_design_add_cart', array(__CLASS__, 'add_to_cart'));
        add_filter('woocommerce_get_cart_item_from_session', array(__CLASS__, 'sync_session_cart_item'), 20, 3);
        add_action('woocommerce_before_calculate_totals', array(__CLASS__, 'apply_cart_prices'), 20);
        add_filter('woocommerce_get_item_data', array(__CLASS__, 'cart_item_details'), 10, 2);
        add_filter('the_content', array(__CLASS__, 'cart_design_actions_content'), 11);
        add_action('woocommerce_before_cart', array(__CLASS__, 'classic_cart_design_actions'), 8);
        add_filter('woocommerce_cart_item_thumbnail', array(__CLASS__, 'cart_thumbnail'), 10, 3);
        add_filter('woocommerce_store_api_cart_item_images', array(__CLASS__, 'cart_block_images'), 10, 3);
        add_action('woocommerce_order_item_meta_end', array(__CLASS__, 'order_item_preview'), 10, 4);
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'order_item_meta'), 10, 4);
        add_action('woocommerce_after_order_itemmeta', array(__CLASS__, 'admin_order_links'), 10, 3);
        add_action('woocommerce_admin_order_data_after_order_details', array(__CLASS__, 'admin_order_print_panel'), 10, 1);
        add_action('admin_post_agas_design_print_center', array(__CLASS__, 'print_center'));
        add_action('admin_post_agas_design_original', array(__CLASS__, 'download_original'));
        add_action('add_meta_boxes_agas_template', array(__CLASS__, 'add_template_box'));
        add_action('save_post_agas_template', array(__CLASS__, 'save_template_box'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_post_agas_duplicate_template', array(__CLASS__, 'duplicate_template'));
        add_filter('post_row_actions', array(__CLASS__, 'template_row_actions'), 10, 2);
        add_filter('manage_agas_template_posts_columns', array(__CLASS__, 'template_list_columns'));
        add_action('manage_agas_template_posts_custom_column', array(__CLASS__, 'template_list_cell'), 10, 2);
        add_filter('the_content', array(__CLASS__, 'account_designs_content'));
    }

    public static function activate() {
        self::register_types();
        self::create_page('agas-templates', 'Design Templates', '[agas_template_gallery]', 'agas_ds_gallery_page');
        self::create_page('design-studio', 'Online Design Studio', '[agas_designer]', 'agas_ds_editor_page');
        update_option('agas_ds_seed_needed', 'yes');
        flush_rewrite_rules();
    }

    private static function create_page($slug, $title, $content, $option) {
        $existing = get_page_by_path($slug);
        if ($existing) {
            update_option($option, $existing->ID);
            return;
        }
        $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content));
        if ($id && !is_wp_error($id)) update_option($option, $id);
    }

    public static function register_types() {
        register_post_type('agas_template', array(
            'labels' => array('name' => 'Design Templates', 'singular_name' => 'Design Template', 'add_new_item' => 'Add New Design Template'),
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'show_in_rest' => true, 'supports' => array('title'),
            'capability_type' => 'post', 'menu_icon' => 'dashicons-art',
        ));
        register_taxonomy('agas_template_category', 'agas_template', array(
            'label' => 'Template Categories', 'public' => false, 'show_ui' => true,
            'show_in_rest' => true, 'hierarchical' => false,
        ));
        register_post_type('agas_design', array(
            'labels' => array('name' => 'Customer Designs'),
            'public' => false, 'show_ui' => false, 'show_in_rest' => false,
            'supports' => array('title'),
        ));
    }

    private static function designable_products() {
        if (!function_exists('wc_get_products')) return array();
        $products = wc_get_products(array('limit' => 100, 'status' => 'publish', 'type' => 'simple'));
        $names = array('outdoor vinyl banner', 'custom flag', 'yard sign');
        $found = array();
        foreach ($products as $product) {
            if (in_array(strtolower(trim($product->get_name())), $names, true)) {
                $found[(int) $product->get_id()] = $product->get_name();
            }
        }
        return $found;
    }

    private static function is_designable($product_id) {
        return array_key_exists((int) $product_id, self::designable_products());
    }

    private static function url_for_page($option, $fallback) {
        $id = absint(get_option($option));
        return $id && get_post_status($id) === 'publish' ? get_permalink($id) : home_url('/' . $fallback . '/');
    }

    public static function gallery_url($product_id = 0) {
        $url = self::url_for_page('agas_ds_gallery_page', 'agas-templates');
        return $product_id ? add_query_arg('agas_product', absint($product_id), $url) : $url;
    }

    public static function editor_url($product_id, $template_id = 0, $more = array()) {
        return add_query_arg(array_merge(array('agas_product' => absint($product_id), 'agas_template' => absint($template_id)), $more), self::url_for_page('agas_ds_editor_page', 'design-studio'));
    }

    private static function template_belongs($template_id, $product_id) {
        if (!$template_id) return true;
        $post = get_post($template_id);
        if (!$post || $post->post_type !== 'agas_template') return false;
        if ($post->post_status !== 'publish' && !current_user_can('edit_post', $template_id)) return false;
        return in_array((int) $product_id, array_map('intval', (array) get_post_meta($template_id, '_agas_products', true)), true);
    }

    private static function template_data($post) {
        $products = array_map('intval', (array) get_post_meta($post->ID, '_agas_products', true));
        $terms = wp_get_post_terms($post->ID, 'agas_template_category', array('fields' => 'names'));
        return array(
            'id' => $post->ID,
            'name' => $post->post_title,
            'product_ids' => $products,
            'category' => !is_wp_error($terms) && $terms ? $terms[0] : 'Other',
            'mockup_profile' => (string) get_post_meta($post->ID, '_agas_mockup_profile', true),
            'front' => json_decode((string) get_post_meta($post->ID, '_agas_canvas', true), true),
            'back' => json_decode((string) get_post_meta($post->ID, '_agas_canvas_back', true), true),
            'thumbnail' => self::template_thumbnail($post->ID),
            'status' => $post->post_status,
        );
    }

    /**
     * The demo originally shipped with the same six text layouts for every
     * product. v0.1.4 introduces independent, editable product-specific seeds.
     *
     * Non-destructive migration: previously saved template posts and customer
     * design references are never deleted or overwritten. Only untouched
     * legacy starter templates are hidden from the gallery; admins still see
     * them, and their existing edit URLs remain valid.
     */
    private static function seed_manifest() {
        $path = plugin_dir_path(__FILE__) . 'assets/seed-presets.json';
        $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        return is_array($data) ? $data : array();
    }

    private static function seed_canvas_file($key) {
        if (!is_string($key) || !preg_match('/^[a-z0-9-]+$/D', $key)) return null;
        $path = plugin_dir_path(__FILE__) . 'assets/seeds/' . $key . '.json';
        $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        return is_array($data) && isset($data['objects']) && is_array($data['objects']) ? $data : null;
    }


/** Upgrade only the completely untouched Company Brand Flag seed; preserve user edits. */
public static function upgrade_logo_slots() {
    if (get_option('agas_ds_logo_slot_revision') === 'v0.1.5') return;
    $posts = get_posts(array('post_type' => 'agas_template', 'post_status' => 'any',
        'meta_key' => '_agas_seed_key', 'meta_value' => 'flag-company', 'numberposts' => 1));
    if (!$posts) return; // Wait until product-specific templates have been seeded.
    $id = (int) $posts[0]->ID;
    $legacy = self::seed_canvas_file('flag-company-legacy');
    $new = self::seed_canvas_file('flag-company');
    $saved = json_decode((string) get_post_meta($id, '_agas_canvas', true), true);
    if ($legacy && $new && $saved == $legacy && !get_post_meta($id, '_agas_thumb_url', true)) {
        update_post_meta($id, '_agas_canvas', wp_json_encode($new));
    }
    update_option('agas_ds_logo_slot_revision', 'v0.1.5');
}

    private static function legacy_specs() {
        return array(
            array('Grand Opening', 'Events', '#173A64', '#FFD166', 'CELEBRATE WITH US'),
            array('Now Hiring', 'Business', '#1B4332', '#95D5B2', 'JOIN OUR TEAM'),
            array('Big Summer Sale', 'Sale', '#7B2CBF', '#FFB4A2', 'LIMITED TIME OFFER'),
            array('Community Event', 'Events', '#003049', '#F77F00', 'EVERYONE IS WELCOME'),
            array('Stay Safe', 'Safety', '#183153', '#FFDF6C', 'SAFETY STARTS HERE'),
            array('New Business', 'Business', '#30343F', '#00C2A8', 'YOUR BRAND. YOUR WAY.'),
        );
    }

    private static function legacy_seed_canvas($spec, $product_name) {
        $base = $spec[2]; $accent = $spec[3];
        return array('version' => '5.3.1', 'background' => $base, 'objects' => array(
            array('type' => 'rect', 'left' => 55, 'top' => 54, 'width' => 790, 'height' => 492, 'fill' => 'transparent', 'stroke' => $accent, 'strokeWidth' => 12, 'rx' => 10, 'ry' => 10),
            array('type' => 'rect', 'left' => 335, 'top' => 120, 'width' => 230, 'height' => 14, 'fill' => $accent),
            array('type' => 'textbox', 'text' => strtoupper($spec[0]), 'left' => 95, 'top' => 175, 'width' => 710, 'fontSize' => 70, 'fontWeight' => 'bold', 'fill' => '#FFFFFF', 'fontFamily' => 'Arial', 'textAlign' => 'center'),
            array('type' => 'textbox', 'text' => $spec[4], 'left' => 140, 'top' => 340, 'width' => 620, 'fontSize' => 30, 'fill' => $accent, 'fontFamily' => 'Arial', 'textAlign' => 'center'),
            array('type' => 'textbox', 'text' => strtoupper($product_name), 'left' => 150, 'top' => 460, 'width' => 600, 'fontSize' => 17, 'fill' => '#FFFFFF', 'fontFamily' => 'Arial', 'textAlign' => 'center'),
        ));
    }

    private static function template_thumbnail($id) {
        $custom = (string) get_post_meta($id, '_agas_thumb_url', true);
        if ($custom) return $custom;
        $seed = (string) get_post_meta($id, '_agas_seed_preview', true);
        if ($seed && preg_match('/^[a-z0-9-]+\.png$/D', $seed)) {
            return plugins_url('assets/thumbs/' . $seed, __FILE__);
        }
        return '';
    }

    public static function seed_once() {
        if (get_option('agas_ds_seed_revision') === 'v0.1.4' || !function_exists('wc_get_products')) return;
        $products = self::designable_products();
        $manifest = self::seed_manifest();
        if (count($products) < 3 || count($manifest) < 3) return;
        $legacy = self::legacy_specs();

        foreach ($products as $product_id => $product_name) {
            $presets = $manifest[strtolower(trim($product_name))] ?? array();
            if (count($presets) < 6) return;
            foreach ($presets as $preset) {
                if (empty($preset['key']) || empty($preset['name']) || empty($preset['category'])) return;
                if (!self::seed_canvas_file($preset['key'])) return;
            }

            // Retain old posts and their IDs. Hide only untouched demo starters.
            foreach ($legacy as $old_spec) {
                $legacy_title = $product_name . ' - ' . $old_spec[0];
                $old = get_posts(array('post_type' => 'agas_template', 'post_status' => 'any',
                    'name' => sanitize_title($legacy_title), 'numberposts' => 1));
                if (!$old) continue;
                $old_id = (int) $old[0]->ID;
                $assigned = array_map('intval', (array) get_post_meta($old_id, '_agas_products', true));
                if (!in_array((int) $product_id, $assigned, true)) continue;
                $legacy_json = wp_json_encode(self::legacy_seed_canvas($old_spec, $product_name));
                if ((string) get_post_meta($old_id, '_agas_canvas', true) === $legacy_json
                    && !get_post_meta($old_id, '_agas_thumb_url', true)) {
                    update_post_meta($old_id, '_agas_legacy_hidden', '1');
                }
            }

            foreach ($presets as $preset) {
                $key = $preset['key'];
                $already = get_posts(array('post_type' => 'agas_template', 'post_status' => 'any',
                    'meta_key' => '_agas_seed_key', 'meta_value' => $key, 'numberposts' => 1));
                if ($already) continue;
                $id = wp_insert_post(array(
                    'post_type' => 'agas_template',
                    'post_title' => $product_name . ' - ' . $preset['name'],
                    'post_status' => 'publish',
                ));
                if (!$id || is_wp_error($id)) return;
                update_post_meta($id, '_agas_products', array((int) $product_id));
                update_post_meta($id, '_agas_canvas', wp_json_encode(self::seed_canvas_file($key)));
                update_post_meta($id, '_agas_seed_key', $key);
                update_post_meta($id, '_agas_seed_preview', $key . '.png');
                $seed_profile = sanitize_key($preset['mockup_profile'] ?? '');
                if (self::valid_mockup_profile(self::mockup_kind($product_name), $seed_profile)) {
                    update_post_meta($id, '_agas_mockup_profile', $seed_profile);
                }
                wp_set_object_terms($id, $preset['category'], 'agas_template_category');
            }
        }
        update_option('agas_ds_seed_revision', 'v0.1.4');
        delete_option('agas_ds_seed_needed');
    }

    /**
     * The design experience is a full-screen application, not a blog post.
     * Use a plugin-owned template to bypass block-theme constrained widths.
     * This affects only the two plugin-created pages.
     */
    /**
     * The logged-in WordPress toolbar is not part of the fullscreen editor.
     * Hide it ONLY on the Design Studio page; retain it on the storefront,
     * gallery, and all wp-admin screens.
     */
    public static function editor_admin_bar_visibility($show) {
        $editor_id = absint(get_option('agas_ds_editor_page'));
        return ($editor_id && is_page($editor_id)) ? false : $show;
    }

    public static function standalone_page_template($theme_template) {
        $gallery_id = absint(get_option('agas_ds_gallery_page'));
        $editor_id = absint(get_option('agas_ds_editor_page'));
        if (($gallery_id && is_page($gallery_id)) || ($editor_id && is_page($editor_id))) {
            $standalone = plugin_dir_path(__FILE__) . 'templates/standalone.php';
            if (is_readable($standalone)) return $standalone;
        }
        return $theme_template;
    }

    public static function enqueue_page_assets() {
        $gallery_id = absint(get_option('agas_ds_gallery_page'));
        $editor_id = absint(get_option('agas_ds_editor_page'));
        if ($editor_id && is_page($editor_id)) self::enqueue_assets(true);
        elseif ($gallery_id && is_page($gallery_id)) self::enqueue_assets(false);
        elseif (function_exists('is_cart') && is_cart()) {
            wp_enqueue_style('agas-design-studio', plugins_url('assets/studio.css', __FILE__), array(), self::VERSION);
        }
    }

    private static function enqueue_assets($include_editor = false) {
        wp_enqueue_style('agas-design-studio', plugins_url('assets/studio.css', __FILE__), array(), self::VERSION);
        wp_enqueue_script('agas-realistic-mockups', plugins_url('assets/realistic-mockups.js', __FILE__), array(), self::VERSION, true);
        if ($include_editor) {
            wp_enqueue_script('agas-fabric', 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js', array(), '5.3.1', true);
            wp_enqueue_style('agas-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Lato:wght@400;700&family=Montserrat:wght@400;700&family=Poppins:wght@400;700&family=Oswald:wght@400;700&family=Merriweather:wght@400;700&family=Raleway:wght@400;700&family=Nunito:wght@400;700&family=Playfair+Display:wght@400;700&family=Source+Sans+3:wght@400;700&display=swap', array(), null);
            wp_enqueue_script('agas-qr', plugins_url('assets/vendor/agas-qrcode.js', __FILE__), array(), self::VERSION, true);
            wp_enqueue_script('agas-template-sidebar', plugins_url('assets/template-sidebar.js', __FILE__), array(), self::VERSION, true);
            wp_enqueue_script('agas-studio', plugins_url('assets/studio.js', __FILE__), array('agas-fabric', 'agas-qr', 'agas-realistic-mockups', 'agas-template-sidebar'), self::VERSION, true);
            wp_enqueue_script('agas-editor-tour', plugins_url('assets/editor-tour.js', __FILE__), array('agas-studio'), self::VERSION, true);
        } else {
            wp_enqueue_script('agas-gallery', plugins_url('assets/gallery.js', __FILE__), array('agas-realistic-mockups'), self::VERSION, true);
        }
    }

    public static function product_cta() {
        global $product;
        if (!$product || !self::is_designable($product->get_id())) return;
        echo '<div class="agas-product-cta"><a class="button alt" href="' . esc_url(self::gallery_url($product->get_id())) . '">Choose a Design or Start Blank</a></div>';
    }

    /** Mockups are illustrative displays of each existing design, never print artwork. */
    private static function mockup_kind($product_name) {
        if (stripos((string) $product_name, 'flag') !== false) return 'flag';
        if (stripos((string) $product_name, 'sign') !== false) return 'sign';
        return 'banner';
    }

    /** Profile choices are scoped to the physical product, not global categories. */
    private static function mockup_choices($kind) {
        $choices = array(
            'banner' => array(
                'banner-storefront' => 'Storefront vinyl banner',
                'banner-event' => 'Event / walkway banner',
            ),
            'flag' => array(
                'flag-feather' => 'Feather / promotional flag',
                'flag-rectangular' => 'Rectangular flag on pole',
            ),
            'sign' => array(
                'sign-lawn' => 'Real-estate lawn sign',
                'sign-parking' => 'Parking / metal-post sign',
                'sign-event' => 'Event directional sign',
            ),
        );
        return $choices[$kind] ?? array();
    }

    private static function valid_mockup_profile($kind, $profile) {
        return $profile !== '' && array_key_exists($profile, self::mockup_choices($kind));
    }

    private static function resolved_mockup_profile($kind, $category, $name, $override = '') {
        if (self::valid_mockup_profile($kind, $override)) return $override;
        $cat = strtolower((string) $category);
        $name = strtolower((string) $name);
        if ($kind === 'flag') {
            return preg_match('/country|nation|national|heritage|world|state flags?/', $cat)
                || preg_match('/\b(france|japan|italy|ukraine|india|germany|country)\b/', $name)
                ? 'flag-rectangular' : 'flag-feather';
        }
        if ($kind === 'banner') {
            if (preg_match('/grand opening|new opening/', $name)) return 'banner-storefront';
            return preg_match('/events?|community|festival|fundraiser|safety/', $cat)
                || preg_match('/festival|fundraiser|concert|community event|safety/', $name)
                ? 'banner-event' : 'banner-storefront';
        }
        if (preg_match('/parking|directions?|safety|construction/', $cat) || preg_match('/reserved|parking|caution/', $name)) return 'sign-parking';
        if (preg_match('/events?|wayfinding/', $cat) || preg_match('/event entrance|event parking|festival/', $name)) return 'sign-event';
        return 'sign-lawn';
    }

    private static function mockup_photo($profile) {
        $files = array(
            'banner-storefront' => 'banner.jpg', 'banner-event' => 'banner-event.jpg',
            'flag-feather' => 'flag.jpg', 'flag-rectangular' => 'flag-rectangular.jpg',
            'sign-lawn' => 'sign.jpg', 'sign-parking' => 'sign-parking.jpg', 'sign-event' => 'sign-event.jpg',
        );
        return plugins_url('assets/mockups/photos/' . ($files[$profile] ?? 'banner.jpg'), __FILE__);
    }

    public static function gallery_shortcode() {
        if (!function_exists('wc_get_product')) return '<p>WooCommerce is required.</p>';
        self::enqueue_assets();
        $products = self::designable_products();
        $product_id = isset($_GET['agas_product']) ? absint($_GET['agas_product']) : 0;
        if (!$product_id || !isset($products[$product_id])) $product_id = (int) array_key_first($products);
        if (!$product_id) return '<p>Create the three assessment products first.</p>';

        $posts = get_posts(array('post_type' => 'agas_template', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC'));
        $templates = array();
        $categories = array();
        foreach ($posts as $post) {
            if (!self::template_belongs($post->ID, $product_id) || get_post_meta($post->ID, '_agas_legacy_hidden', true)) continue;
            $tpl = self::template_data($post);
            $parts = explode(' - ', $post->post_title, 2);
            $title = count($parts) === 2 ? $parts[1] : $post->post_title;
            $tpl['display_name'] = $title;
            $tpl['url'] = self::editor_url($product_id, $post->ID);
            $tpl['background'] = sanitize_hex_color(get_post_meta($post->ID, '_agas_thumb_color', true)) ?: '#294B72';
            $templates[] = $tpl;
            $categories[$tpl['category']] = true;
        }
        uksort($categories, 'strnatcasecmp');
        $kind = self::mockup_kind($products[$product_id]);
        $first = $templates ? $templates[0] : null;
        $first_profile = $first ? self::resolved_mockup_profile($kind, $first['category'], $first['name'], $first['mockup_profile']) : self::resolved_mockup_profile($kind, '', '');
        ob_start();
        ?>
        <section class="agas-gallery" id="agas-gallery" data-product-kind="<?php echo esc_attr($kind); ?>">
          <header class="agas-gallery-header">
            <div>
              <span class="agas-eyebrow">AGAS DESIGN STUDIO / TEMPLATES</span>
              <h1>Find a design worth printing.</h1>
              <p>Choose a starting design, preview it on your product, then make it yours.</p>
            </div>
            <a class="agas-action" href="<?php echo esc_url(self::editor_url($product_id)); ?>">Start from blank <span aria-hidden="true">↗</span></a>
          </header>
          <div class="agas-gallery-controls" role="search" aria-label="Find a template">
            <div class="agas-gallery-searchbox">
              <label class="agas-visually-hidden" for="agas-gallery-search">Search design templates</label>
              <svg viewBox="0 0 24 24" width="29" height="29" aria-hidden="true" focusable="false"><circle cx="10.8" cy="10.8" r="6.9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 5.2 5.2" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
              <input id="agas-gallery-search" type="search" placeholder="Search designs, occasions, or categories" autocomplete="off" spellcheck="false">
              <button type="button" id="agas-gallery-clear" class="agas-gallery-clear" hidden aria-label="Clear search">Clear ×</button>
            </div>
            <div class="agas-gallery-filters">
              <label class="agas-filter-pill" for="agas-product-select"><span>Product</span>
                <select id="agas-product-select" aria-label="Choose product">
                  <?php foreach ($products as $pid => $name) : ?>
                    <option value="<?php echo esc_url(self::gallery_url($pid)); ?>" <?php selected((int) $pid, $product_id); ?>><?php echo esc_html($name); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="agas-filter-pill" for="agas-category-filter"><span>Category</span>
                <select id="agas-category-filter" aria-label="Filter designs by category">
                  <option value="">All categories</option>
                  <?php foreach (array_keys($categories) as $category) : ?>
                    <option value="<?php echo esc_attr(strtolower($category)); ?>"><?php echo esc_html($category); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <p id="agas-gallery-results" class="agas-gallery-results" role="status" aria-live="polite"><?php echo esc_html(count($templates)); ?> designs available. Select a card to see its product mockup.</p>
          </div>
          <div class="agas-gallery-body">
            <aside class="agas-gallery-preview" aria-label="Selected template mockup">
              <div class="agas-preview-sidebar-top"><span class="agas-eyebrow">ON YOUR PRODUCT</span><span class="agas-preview-live-dot">Live mockup</span></div>
              <div id="agas-gallery-mockup" class="agas-mockup-stage agas-mockup-stage--<?php echo esc_attr($kind); ?>" data-mockup-kind="<?php echo esc_attr($kind); ?>" data-mockup-profile="<?php echo esc_attr($first_profile); ?>" data-mockup-base="<?php echo esc_url(plugins_url('assets/mockups/photos/', __FILE__)); ?>" data-mockup-photo="<?php echo esc_url(self::mockup_photo($first_profile)); ?>">
                <div class="agas-mockup-art">
                  <img id="agas-gallery-mockup-image" alt="Template shown on product mockup" <?php if ($first && $first['thumbnail']) : ?>src="<?php echo esc_url($first['thumbnail']); ?>"<?php else : ?>hidden<?php endif; ?>>
                  <div id="agas-gallery-mockup-fallback" class="agas-mockup-art-fallback" style="background:<?php echo esc_attr($first ? $first['background'] : '#294B72'); ?>" <?php if ($first && $first['thumbnail']) echo 'hidden'; ?>><?php echo esc_html($first ? $first['display_name'] : 'Select a design'); ?></div>
                </div>
              </div>
              <div class="agas-gallery-preview-description">
                <span class="agas-gallery-preview-kicker">SELECTED TEMPLATE</span>
                <h2 id="agas-selected-name"><?php echo esc_html($first ? $first['display_name'] : 'No templates available'); ?></h2>
                <p id="agas-selected-category"><?php echo esc_html($first ? $first['category'] : 'Start from blank to create a design.'); ?></p>
                <p class="agas-selected-mockup-label" id="agas-selected-mockup-type" aria-live="polite">Physical format: <?php echo esc_html(self::mockup_choices($kind)[$first_profile] ?? $first_profile); ?></p>
                <a class="agas-action agas-selected-action" id="agas-selected-customize" <?php if ($first) : ?>href="<?php echo esc_url($first['url']); ?>"<?php else : ?>href="<?php echo esc_url(self::editor_url($product_id)); ?>"<?php endif; ?>>Customize this design <span aria-hidden="true">↗</span></a>
                <p class="agas-mockup-disclaimer">Mockup is illustrative. Your flat artwork remains the source for print exports.</p>
              </div>
            </aside>
            <div class="agas-gallery-collection">
              <div class="agas-gallery-collection-top"><h2>Explore templates</h2><span>Click to preview · Customize when ready</span></div>
              <div id="agas-template-cards" class="agas-gallery-grid">
                <?php foreach ($templates as $i => $tpl) : ?>
                  <article class="agas-template-card<?php echo $i === 0 ? ' is-selected' : ''; ?>"
                    data-category="<?php echo esc_attr(strtolower($tpl['category'])); ?>"
                    data-mockup-profile="<?php echo esc_attr(self::resolved_mockup_profile($kind, $tpl['category'], $tpl['name'], $tpl['mockup_profile'])); ?>"
                    data-search="<?php echo esc_attr(strtolower($tpl['display_name'] . ' ' . $tpl['category'])); ?>"
                    data-name="<?php echo esc_attr($tpl['display_name']); ?>"
                    data-preview="<?php echo esc_url($tpl['thumbnail']); ?>"
                    data-background="<?php echo esc_attr($tpl['background']); ?>"
                    data-editor-url="<?php echo esc_url($tpl['url']); ?>">
                    <button type="button" class="agas-card-pick" aria-label="Preview <?php echo esc_attr($tpl['display_name']); ?>" aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                      <?php if ($tpl['thumbnail']) : ?>
                        <span class="agas-card-image"><img loading="lazy" src="<?php echo esc_url($tpl['thumbnail']); ?>" alt="<?php echo esc_attr($tpl['display_name']); ?> template artwork"></span>
                      <?php else : ?>
                        <span class="agas-card-image agas-card-generated" style="background:<?php echo esc_attr($tpl['background']); ?>"><span class="agas-card-frame"><strong><?php echo esc_html(strtoupper($tpl['display_name'])); ?></strong><span>CUSTOMIZE YOUR MESSAGE</span></span></span>
                      <?php endif; ?>
                      <span class="agas-card-title-row"><strong><?php echo esc_html($tpl['display_name']); ?></strong><small><?php echo esc_html($tpl['category']); ?></small></span>
                    </button>
                    <div class="agas-card-foot"><span>View on product</span><a class="agas-card-button" href="<?php echo esc_url($tpl['url']); ?>">Customize <span aria-hidden="true">↗</span></a></div>
                  </article>
                <?php endforeach; ?>
              </div>
              <p id="agas-gallery-empty" hidden>No matching templates. Clear your search or try another category.</p>
            </div>
          </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function designer_shortcode() {
        if (!function_exists('wc_get_product')) return '<p>WooCommerce is required.</p>';
        $products = self::designable_products();
        $product_id = isset($_GET['agas_product']) ? absint($_GET['agas_product']) : 0;
        $template_id = isset($_GET['agas_template']) ? absint($_GET['agas_template']) : 0;
        $editing_template = isset($_GET['agas_edit_template']) ? absint($_GET['agas_edit_template']) : 0;
        $new_template = !empty($_GET['agas_new_template']) && current_user_can('manage_woocommerce');
        $admin_template_mode = $new_template || ($editing_template && current_user_can('edit_post', $editing_template));
        $design_id = isset($_GET['agas_design']) ? absint($_GET['agas_design']) : 0;
        $token = isset($_GET['agas_token']) ? sanitize_text_field(wp_unslash($_GET['agas_token'])) : '';
        if ($editing_template) {
            if (!current_user_can('edit_post', $editing_template)) return '<p>You are not allowed to edit this template.</p>';
            $tpl_products = (array) get_post_meta($editing_template, '_agas_products', true);
            if (!$product_id && $tpl_products) $product_id = absint($tpl_products[0]);
            $template_id = $editing_template;
        }
        if (!$product_id && $products) $product_id = (int) array_key_first($products);
        if (!self::is_designable($product_id)) return '<p>Choose one of the three assessment products.</p>';
        if ($template_id && !self::template_belongs($template_id, $product_id)) return '<p>This template is not available for this product.</p>';
        $template = $template_id ? self::template_data(get_post($template_id)) : null;
        // The editor sidebar loads metadata only; canvas JSON is fetched on selection.
        // Do not expose drafts or templates assigned to a different product.
        $available_templates = array();
        $published = get_posts(array('post_type' => 'agas_template', 'post_status' => 'publish',
            'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC'));
        foreach ($published as $candidate) {
            if (!self::template_belongs($candidate->ID, $product_id)
                || get_post_meta($candidate->ID, '_agas_legacy_hidden', true)) continue;
            $terms = wp_get_post_terms($candidate->ID, 'agas_template_category', array('fields' => 'names'));
            $parts = explode(' - ', $candidate->post_title, 2);
            $available_templates[] = array(
                'id' => (int) $candidate->ID,
                'name' => count($parts) === 2 ? $parts[1] : $candidate->post_title,
                'category' => !is_wp_error($terms) && $terms ? $terms[0] : 'Other',
                'thumbnail' => self::template_thumbnail($candidate->ID),
            );
        }
        $product_kind = self::mockup_kind($products[$product_id]);
        $mockup_profile = self::resolved_mockup_profile($product_kind, $template['category'] ?? '', $template['name'] ?? '', $template['mockup_profile'] ?? '');
        $loaded_design = null;
        if ($design_id) {
            $design = get_post($design_id);
            if (!$design || $design->post_type !== 'agas_design' || !self::can_read_design($design, $token)) return '<p>Design link invalid or expired.</p>';
            if ((int) get_post_meta($design_id, '_agas_product_id', true) !== $product_id) return '<p>Incorrect product for this design.</p>';
            $loaded_design = array(
                'id' => $design_id,
                'token' => $token,
                'front' => json_decode(get_post_meta($design_id, '_agas_front', true), true),
                'back' => json_decode(get_post_meta($design_id, '_agas_back', true), true),
                'options' => (array) get_post_meta($design_id, '_agas_options', true),
            );
        }
        self::enqueue_assets(true);
        $config = array(
            'rest' => esc_url_raw(rest_url(self::REST_NS . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'cartEndpoint' => esc_url_raw(add_query_arg('agas_design_add_to_cart', '1', home_url('/'))),
            'cartNonce' => wp_create_nonce('agas_design_cart'),
            'productId' => $product_id,
            'productName' => $products[$product_id],
            'productKind' => $product_kind,
            'mockupCategory' => $template['category'] ?? '',
            'mockupProfile' => $mockup_profile,
            'mockupBase' => plugins_url('assets/mockups/photos/', __FILE__),
            'template' => $template,
            'availableTemplates' => $available_templates,
            'editingTemplate' => $editing_template,
            'adminTemplateMode' => (bool) $admin_template_mode,
            'loadedDesign' => $loaded_design,
            'templateGalleryUrl' => esc_url_raw(self::gallery_url($product_id)),
            'rules' => self::pricing_rules(),
            'products' => $products,
            'saveTemplateAllowed' => current_user_can('manage_woocommerce'),
            'readonly' => !empty($_GET['agas_readonly']),
            'returnToCart' => !empty($_GET['agas_from_cart']) && $design_id > 0,
            'cartEditKey' => isset($_GET['agas_cart_key']) ? sanitize_text_field(wp_unslash($_GET['agas_cart_key'])) : '',
        );
        wp_add_inline_script('agas-studio', 'window.AGAS_DS = ' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
        ob_start();
        ?>
        <div id="agas-designer-root" class="agas-designer">
          <header class="agas-topbar">
            <a class="agas-logo" href="<?php echo esc_url(self::gallery_url($product_id)); ?>">AGAS<span> DESIGN STUDIO</span></a>
            <div class="agas-design-context"><strong id="agas-product-name"><?php echo esc_html($products[$product_id]); ?></strong><span class="agas-template-name">Template: <?php echo esc_html($template ? preg_replace('/^.*? - /', '', $template['name']) : 'Blank design'); ?></span></div>
<nav class="agas-top-actions" aria-label="Editor commands"><?php if (!empty($_GET['agas_from_cart'])) : ?><a class="agas-back-to-cart" href="<?php echo esc_url(wc_get_cart_url()); ?>">Back to cart</a><?php endif; ?>
              <button type="button" id="agas-undo" class="agas-icon-button" title="Undo (Ctrl/Command+Z)" aria-label="Undo (Ctrl/Command+Z)" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5M4 9h10a6 6 0 0 1 0 12h-2"/></svg></button><button type="button" id="agas-redo" class="agas-icon-button" title="Redo (Ctrl/Command+Y)" aria-label="Redo (Ctrl/Command+Y)" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 14 5-5-5-5m5 5H10a6 6 0 0 0 0 12h2"/></svg></button>
              <button type="button" class="agas-header-action" id="agas-save"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3h14l3 3v15H3V3zM7 3v7h10V3M7 21v-8h10v8"/></svg><span>Save</span></button>
              <button type="button" class="agas-header-action agas-header-preview" id="agas-preview"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><span>Preview</span></button>
              <button type="button" class="agas-icon-button agas-settings-toggle" id="agas-settings-toggle" aria-controls="agas-product-settings" aria-expanded="false" title="Product settings" aria-label="Product settings"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19 13.5a7.5 7.5 0 0 0 0-3l2-1.5-2-3.5-2.5 1a8 8 0 0 0-2.6-1.5L13.5 2h-4l-.4 3a8 8 0 0 0-2.6 1.5L4 5.5 2 9l2 1.5a7.5 7.5 0 0 0 0 3L2 15l2 3.5 2.5-1a8 8 0 0 0 2.6 1.5l.4 3h4l.4-3a8 8 0 0 0 2.6-1.5l2.5 1 2-3.5z"/></svg></button></nav>
          </header>
          <div class="agas-main">

            <div class="agas-insert-sidebar" aria-label="Add and manage canvas elements">
              <nav class="agas-tool-rail" role="tablist" aria-label="Design tools">
                <button type="button" class="agas-rail-tab" data-agas-tab="templates" role="tab" aria-controls="agas-panel-templates" aria-selected="false" aria-label="Templates" title="Templates"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/></svg><span class="agas-visually-hidden">Templates</span></button><button type="button" class="agas-rail-tab is-active" data-agas-tab="text" role="tab" aria-controls="agas-panel-text" aria-selected="true" aria-label="Text" title="Text"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7V4h16v3M12 4v16M8 20h8"/></svg><span class="agas-visually-hidden">Text</span></button><button type="button" class="agas-rail-tab" data-agas-tab="elements" role="tab" aria-controls="agas-panel-elements" aria-selected="false" aria-label="Elements" title="Elements"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="9" height="9" rx="1"/><circle cx="17" cy="17" r="4"/><path d="M4 20l6-6"/></svg><span class="agas-visually-hidden">Elements</span></button><button type="button" class="agas-rail-tab" data-agas-tab="uploads" role="tab" aria-controls="agas-panel-uploads" aria-selected="false" aria-label="Uploads" title="Uploads"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V3m0 0-5 5m5-5 5 5M4 16v4h16v-4"/></svg><span class="agas-visually-hidden">Uploads</span></button><button type="button" class="agas-rail-tab" data-agas-tab="background" role="tab" aria-controls="agas-panel-background" aria-selected="false" aria-label="Background" title="Background"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="7.5" cy="12" r="1"/><circle cx="11" cy="7.5" r="1"/><circle cx="16" cy="8.5" r="1"/><path d="M13 21c-2 0-2-3 0-4 2-1 6 1 7-3"/></svg><span class="agas-visually-hidden">Background</span></button><button type="button" class="agas-rail-tab" data-agas-tab="qr" role="tab" aria-controls="agas-panel-qr" aria-selected="false" aria-label="QR Code" title="QR Code"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM15 15v2h2M20 14v3h-2M14 20h3M20 20h1"/></svg><span class="agas-visually-hidden">QR Code</span></button><button type="button" class="agas-rail-tab" data-agas-tab="layers" role="tab" aria-controls="agas-panel-layers" aria-selected="false" aria-label="Layers" title="Layers"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5"/></svg><span class="agas-visually-hidden">Layers</span></button>
              </nav>
              <aside class="agas-tools" aria-label="Selected insertion tools">
                <section class="agas-tool-panel" id="agas-panel-templates" role="tabpanel" aria-label="Design templates" hidden>
                  <div class="agas-panel-top"><h3>Design templates</h3><button type="button" class="agas-drawer-dismiss agas-icon-button" aria-label="Close tool panel" title="Close tool panel"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
                  <p class="agas-drawer-description">Choose a new design without leaving the editor. Switching replaces the canvas artwork; you will be asked first.</p>
                  <button type="button" class="agas-drawer-cta" id="agas-change-template" aria-controls="agas-template-sidebar-grid"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/></svg> Browse templates</button>
                  <label class="agas-sidebar-search-label" for="agas-template-sidebar-search">Search designs<input type="search" id="agas-template-sidebar-search" autocomplete="off" placeholder="Search templates..." aria-controls="agas-template-sidebar-grid"></label>
                  <label class="agas-sidebar-category-label" for="agas-template-sidebar-category">Category<select id="agas-template-sidebar-category" aria-label="Filter sidebar templates by category"><option value="">All categories</option></select></label>
                  <p class="agas-sidebar-template-count" id="agas-template-sidebar-count" role="status" aria-live="polite"></p>
                  <div id="agas-template-sidebar-grid" class="agas-template-sidebar-grid" aria-label="Available design templates"></div>
                  <p class="agas-sidebar-template-empty" id="agas-template-sidebar-empty" hidden>No matching templates. Try a different search or category.</p>
                  <a class="agas-sidebar-gallery-link" href="<?php echo esc_url(self::gallery_url($product_id)); ?>">Open full gallery</a>
                </section>
                <section class="agas-tool-panel" id="agas-panel-text" role="tabpanel" aria-label="Add text" ><div class="agas-panel-top"><h3>Add text</h3><button type="button" class="agas-drawer-dismiss agas-icon-button" aria-label="Close tool panel" title="Close tool panel"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><p class="agas-drawer-description">Add a text element, then select it on the canvas to adjust its properties above.</p>
                    <div class="agas-insert-list"><button type="button" id="agas-add-heading" class="agas-add-heading"><span class="agas-add-type">Aa</span><span>Heading</span></button><button type="button" id="agas-add-subheading"><span class="agas-add-type">T</span><span>Subheading</span></button><button type="button" id="agas-add-body"><span class="agas-add-type">Tt</span><span>Body text</span></button></div></section>
                <section class="agas-tool-panel" id="agas-panel-elements" role="tabpanel" aria-label="Elements &amp; graphics" hidden><div class="agas-panel-top"><h3>Elements &amp; graphics</h3><button type="button" class="agas-drawer-dismiss agas-icon-button" aria-label="Close tool panel" title="Close tool panel"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><div class="agas-panel-section"><label>Basic shapes</label><div class="agas-shape-buttons"><button type="button" id="agas-add-rect">▭ <span>Rectangle</span></button><button type="button" id="agas-add-circle">◯ <span>Circle</span></button><button type="button" id="agas-add-star">☆ <span>Star</span></button></div></div>
                    <div class="agas-panel-section agas-graphics-library"><label for="agas-clipart-category">Graphics library (24 original cliparts)</label><select id="agas-clipart-category" aria-label="Filter cliparts by category"><option value="all">All graphics</option><option value="business">Business</option><option value="events">Events</option><option value="nature">Nature</option><option value="symbols">Symbols &amp; safety</option></select><div id="agas-clipart-grid" class="agas-clipart-grid" aria-label="Insert graphics"></div></div></section>
                <section class="agas-tool-panel" id="agas-panel-uploads" role="tabpanel" aria-label="Uploads &amp; logos" hidden><div class="agas-panel-top"><h3>Uploads &amp; logos</h3><button type="button" class="agas-drawer-dismiss agas-icon-button" aria-label="Close tool panel" title="Close tool panel"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><div class="agas-panel-section agas-logo-upload-actions"><label for="agas-file">Add PNG, JPG or SVG artwork</label><input id="agas-file" type="file" accept="image/png,image/jpeg,image/svg+xml"><button type="button" id="agas-add-logo-slot"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Add logo placeholder</button><button type="button" id="agas-replace-image" disabled>Replace selected image</button><input id="agas-logo-file" type="file" accept="image/png,image/jpeg,image/svg+xml" hidden><p class="agas-hint">Drag an image onto the canvas, or select a placeholder to replace it without changing its position.</p></div></section>
                <section class="agas-tool-panel" id="agas-panel-background" role="tabpanel" aria-label="Background" hidden><div class="agas-panel-top"><h3>Background</h3><button type="button" class="agas-drawer-dismiss agas-icon-button" aria-label="Close tool panel" title="Close tool panel"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><div class="agas-panel-section"><label for="agas-bg">Background color</label><input id="agas-bg" type="color" value="#ffffff"><label for="agas-bg-file">Background image</label><input id="agas-bg-file" type="file" accept="image/png,image/jpeg"><button type="button" id="agas-bg-remove">Remove background image</button></div></section>
                <section class="agas-tool-panel" id="agas-panel-qr" role="tabpanel" aria-label="QR code" hidden><div class="agas-panel-top"><h3>QR code</h3><button type="button" class="agas-drawer-dismiss agas-icon-button" aria-label="Close tool panel" title="Close tool panel"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><div class="agas-panel-section agas-qr-panel"><label for="agas-qr-url">Paste a website URL</label><input type="url" id="agas-qr-url" inputmode="url" maxlength="240" placeholder="https://example.com" autocomplete="off"><button type="button" id="agas-add-qr">Generate QR Code</button><p class="agas-hint">Creates a scannable vector QR with quiet zone. Test the link before printing.</p></div></section>
                <section class="agas-tool-panel" id="agas-panel-layers" role="tabpanel" aria-label="Design layers" hidden><div class="agas-panel-top"><h3>Design layers</h3><button type="button" class="agas-drawer-dismiss agas-icon-button" aria-label="Close tool panel" title="Close tool panel"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><p class="agas-drawer-description">Select an existing text layer to edit it using the toolbar above the canvas.</p><div class="agas-text-layer-group"><div id="agas-text-layers" aria-label="Selectable text elements in this design"></div></div><p class="agas-hint">Drag elements on the canvas. Shift-click to select multiple elements.</p></section>
              </aside>
            </div>
<section class="agas-workspace"><div class="agas-object-toolbar" id="agas-object-toolbar" aria-label="Selected element properties">
                <div class="agas-context-empty" id="agas-context-empty"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16 4l4 4-11 11-5 1 1-5L16 4z"/></svg><span>Select an element on the canvas to edit its properties</span></div>

                  <div class="agas-context-text" id="agas-context-text" hidden>
                    <label class="agas-context-field agas-selected-text-input" for="agas-text-content" title="Edit selected text">Text <span id="agas-text-edit-panel" hidden><textarea id="agas-text-content" rows="1" placeholder="Edit selected text"></textarea></span></label>
                    <button type="button" id="agas-edit-on-canvas" class="agas-icon-button" title="Edit text on canvas" aria-label="Edit text on canvas" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16 4l4 4-11 11-5 1 1-5L16 4z"/></svg></button>
                    <label class="agas-context-field agas-font-family" title="Font family"><span class="agas-visually-hidden">Font family</span><select id="agas-font" aria-label="Font family"><option>Arial</option><option>Georgia</option><option>Verdana</option><option>Times New Roman</option><option>Courier New</option><optgroup label="Google Fonts"><option>Inter</option><option>Roboto</option><option>Open Sans</option><option>Lato</option><option>Montserrat</option><option>Poppins</option><option>Oswald</option><option>Merriweather</option><option>Raleway</option><option>Nunito</option><option>Playfair Display</option><option>Source Sans 3</option></optgroup></select></label>
                    <label class="agas-context-field agas-font-size" title="Font size"><span class="agas-visually-hidden">Font size</span><input type="number" id="agas-font-size" min="8" max="160" value="32" aria-label="Font size"></label>
                    <label class="agas-context-field" title="Text color"><span class="agas-visually-hidden">Text color</span><input type="color" id="agas-color" value="#ffffff" aria-label="Text color"></label>
                    <div class="agas-toolbar-group agas-text-style"><button type="button" id="agas-bold" class="agas-icon-button agas-letter-control" title="Bold" aria-label="Bold" aria-pressed="false"><b>B</b></button><button type="button" id="agas-italic" class="agas-icon-button agas-letter-control" title="Italic" aria-label="Italic" aria-pressed="false"><i>I</i></button><button type="button" id="agas-underline" class="agas-icon-button agas-letter-control" title="Underline" aria-label="Underline" aria-pressed="false"><u>U</u></button></div>
                    <label class="agas-context-field" title="Text alignment"><span class="agas-visually-hidden">Text alignment</span><select id="agas-text-align" aria-label="Text alignment"><option value="left">Left</option><option value="center" selected>Center</option><option value="right">Right</option><option value="justify">Justify</option></select></label>
                    <label class="agas-context-field agas-spacing-control" title="Letter spacing"><span class="agas-visually-hidden">Letter spacing</span><input type="number" id="agas-char-spacing" min="-500" max="1500" step="10" value="0" aria-label="Letter spacing" title="Letter spacing"></label>
                    <label class="agas-context-field agas-spacing-control" title="Line spacing"><span class="agas-visually-hidden">Line spacing</span><input type="number" id="agas-line-height" min="0.7" max="3" step="0.1" value="1.16" aria-label="Line spacing" title="Line spacing"></label>
                  </div>

                  <div class="agas-context-actions" id="agas-context-actions" hidden>
                    <span class="agas-toolbar-separator" aria-hidden="true"></span>
                    <div class="agas-toolbar-group"><button type="button" id="agas-copy" class="agas-icon-button" title="Copy selected element" aria-label="Copy selected element" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg></button><button type="button" id="agas-paste" class="agas-icon-button" title="Paste element" aria-label="Paste element" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="5" width="14" height="17" rx="2"/><path d="M9 5V3h6v2M9 12h6M9 16h5"/></svg></button><button type="button" id="agas-duplicate" class="agas-icon-button" title="Duplicate selected element" aria-label="Duplicate selected element" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3M14 12v5m-2.5-2.5h5"/></svg></button><button type="button" id="agas-cut" class="agas-icon-button" title="Cut selected element" aria-label="Cut selected element" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="18" r="3"/><circle cx="18" cy="18" r="3"/><path d="M8 16 21 3M16 16 3 3"/></svg></button><button type="button" id="agas-delete" class="agas-icon-button" title="Delete selected element" aria-label="Delete selected element" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15M10 10v7M14 10v7"/></svg></button></div>
                    <span class="agas-toolbar-separator" aria-hidden="true"></span>
                    <div class="agas-toolbar-group"><button type="button" id="agas-front" class="agas-icon-button" title="Bring forward" aria-label="Bring forward" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 17h12v4H8zM4 3h12v10H4zM10 8h10m0 0-3-3m3 3-3 3"/></svg></button><button type="button" id="agas-backward" class="agas-icon-button" title="Send backward" aria-label="Send backward" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 17h12v4H4zM8 3h12v10H8zM14 8H4m0 0 3-3M4 8l3 3"/></svg></button><button type="button" id="agas-rotate-left" class="agas-icon-button" title="Rotate left 15 degrees" aria-label="Rotate left 15 degrees" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11a9 9 0 1 0 4-7M3 4v7h7"/></svg></button><button type="button" id="agas-rotate-right" class="agas-icon-button" title="Rotate right 15 degrees" aria-label="Rotate right 15 degrees" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11a9 9 0 1 1-4-7m4 0v7h-7"/></svg></button><button type="button" id="agas-flip-h" class="agas-icon-button" title="Flip horizontal" aria-label="Flip horizontal" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v20M3 6l6 6-6 6V6zm18 0-6 6 6 6V6z"/></svg></button><button type="button" id="agas-flip-v" class="agas-icon-button" title="Flip vertical" aria-label="Flip vertical" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12h20M6 3l6 6 6-6H6zm0 18 6-6 6 6H6z"/></svg></button></div>
                    <div class="agas-toolbar-group agas-advanced-position"><button type="button" id="agas-center" class="agas-icon-button" title="Center object" aria-label="Center object" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="6"/><path d="M12 1v5M12 18v5M1 12h5M18 12h5"/></svg></button><button type="button" id="agas-align-left" class="agas-icon-button" title="Align left" aria-label="Align left" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3v18M8 7h12M8 12h8M8 17h12"/></svg></button><button type="button" id="agas-align-right" class="agas-icon-button" title="Align right" aria-label="Align right" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 3v18M4 7h12M8 12h8M4 17h12"/></svg></button><button type="button" id="agas-align-top" class="agas-icon-button" title="Align top" aria-label="Align top" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h18M7 8v12M12 8v8M17 8v12"/></svg></button><button type="button" id="agas-align-bottom" class="agas-icon-button" title="Align bottom" aria-label="Align bottom" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 20h18M7 4v12M12 8v8M17 4v12"/></svg></button></div>
                    <div class="agas-toolbar-group"><button type="button" id="agas-lock" class="agas-icon-button" title="Lock selected element" aria-label="Lock selected element" ><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></button><button type="button" class="agas-icon-button" id="agas-protect-delete" title="Protect from deletion" aria-label="Protect from deletion" <?php if (!$admin_template_mode) echo 'hidden'; ?>><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2 20 6v6c0 6-4 9-8 10-4-1-8-4-8-10V6zM8 12l3 3 5-6"/></svg></button></div>
                    <label class="agas-context-opacity" title="Opacity"><span class="agas-visually-hidden">Opacity</span><span aria-hidden="true">◐</span><input type="range" id="agas-opacity" min="0" max="100" step="5" value="100" aria-label="Opacity"></label>
                  </div>
</div><div class="agas-canvas-area"><div class="agas-ruler" id="agas-ruler">0 &nbsp; | &nbsp; 1 &nbsp; | &nbsp; 2 &nbsp; | &nbsp; 3 ft</div><div class="agas-canvas-viewport" id="agas-canvas-viewport"><div class="agas-canvas-shell" id="agas-canvas-shell"><canvas id="agas-canvas" width="900" height="600"></canvas><div class="agas-safety" id="agas-safety"></div><div class="agas-bleed" id="agas-bleed" aria-hidden="true"></div><div class="agas-center-guide agas-guide-vertical" id="agas-guide-vertical" aria-hidden="true"></div><div class="agas-center-guide agas-guide-horizontal" id="agas-guide-horizontal" aria-hidden="true"></div><div class="agas-ruler-vertical" id="agas-ruler-vertical" aria-hidden="true"></div></div></div><div id="agas-status" role="status" aria-live="polite">Ready to design</div></div></section>
            <aside class="agas-config" id="agas-product-settings"><div class="agas-config-heading"><h3>Product setup</h3><button type="button" id="agas-settings-close" class="agas-icon-button" title="Close product settings" aria-label="Close product settings"><svg class="agas-svg-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
            <?php if ($admin_template_mode) : ?>
            <section class="agas-admin-template-controls" id="agas-admin-template-controls">
              <h4>Template management</h4>
              <label>Template title <input type="text" id="agas-template-title" maxlength="180" value="<?php echo esc_attr($template['name'] ?? 'New Design Template'); ?>"></label>
              <label>Category <input type="text" id="agas-template-category" maxlength="80" value="<?php echo esc_attr($template['category'] ?? 'Business'); ?>"></label>
              <label>Product mockup format
                <select id="agas-template-mockup" aria-label="Choose physical mockup format">
                  <option value="">Automatic — product and category</option>
                  <?php foreach (self::mockup_choices($product_kind) as $profile_key => $profile_label) : ?>
                    <option value="<?php echo esc_attr($profile_key); ?>" <?php selected(($template['mockup_profile'] ?? ''), $profile_key); ?>><?php echo esc_html($profile_label); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <p class="agas-hint">The visual mockup follows the printed format. It never changes the print canvas or saved artwork.</p>
              <label>Status <select id="agas-template-status"><option value="draft" <?php selected(($template['status'] ?? 'draft'), 'draft'); ?>>Draft</option><option value="publish" <?php selected(($template['status'] ?? ''), 'publish'); ?>>Published</option></select></label>
              <fieldset><legend>Assign to products</legend>
                <?php foreach ($products as $pid => $pname) : ?>
                  <label class="agas-check"><input type="checkbox" class="agas-template-product" value="<?php echo esc_attr($pid); ?>" <?php checked(in_array((int) $pid, (array) ($template['product_ids'] ?? array($product_id)), true)); ?>> <?php echo esc_html($pname); ?></label>
                <?php endforeach; ?>
              </fieldset>
              <p class="agas-hint">Save in Draft to hide from customers. Publish to make the template appear in assigned galleries.</p>
            </section>
            <?php endif; ?>
              <div class="agas-product-settings-fields">
                <label>Size <select id="agas-size"><option value="3x2">3 x 2 ft</option><option value="4x2">4 x 2 ft</option><option value="6x3">6 x 3 ft</option><option value="8x4">8 x 4 ft</option><option value="custom">Custom size</option></select></label>
                <div class="agas-two"><label>Width <input type="number" id="agas-width" min="0.25" max="20" step="0.25" value="3"></label><label>Height <input type="number" id="agas-height" min="0.25" max="20" step="0.25" value="2"></label></div>
                <label>Unit <select id="agas-unit"><option value="ft">Feet</option><option value="in">Inches</option><option value="cm">Centimeters</option></select></label>
                <fieldset class="agas-finishing-options" aria-label="Choose one finishing method"><legend>Finishing</legend><p class="agas-hint">Select one finishing option for this item.</p>
                  <label class="agas-check"><input id="agas-no-finishing" type="radio" name="agas-finishing" value="none" checked> None</label>
                  <label class="agas-check"><input id="agas-grommets" type="radio" name="agas-finishing" value="grommets"> Grommets</label>
                  <label class="agas-check"><input id="agas-pocket" type="radio" name="agas-finishing" value="pole_pocket"> Pole pocket</label>
                </fieldset>
                <section class="agas-sides-preview" id="agas-sides-preview" aria-label="Side previews and back-side options">
                  <div class="agas-sides-header"><strong>Side previews</strong><label class="agas-check agas-back-checkbox"><input id="agas-two-sided" type="checkbox"><span id="agas-back-price-hint">Add Back</span></label></div>
                  <div class="agas-side-switch"><button type="button" class="active" id="agas-side-front">Front</button><button type="button" id="agas-side-back" disabled>Back</button></div>
                  <div class="agas-side-thumbnails"><button type="button" id="agas-side-thumb-front" aria-label="Edit front design"><img id="agas-thumb-front" alt="Front side design thumbnail"><span>Front</span></button><button type="button" id="agas-side-thumb-back" aria-label="Edit back design" disabled><img id="agas-thumb-back" alt="Back side design thumbnail"><span>Back</span></button></div>
                </section><p class="agas-hint">Pricing is recalculated on the server at checkout.</p>
              </div>
            </aside>
          </div>
          <footer class="agas-bottombar"><label>Zoom <input id="agas-zoom" type="range" min="50" max="125" value="100"><span id="agas-zoom-out">100%</span><select id="agas-zoom-select" aria-label="Zoom percentage"><option value="50">50%</option><option value="75">75%</option><option value="100" selected>100%</option><option value="125">125%</option></select></label><label class="agas-check"><input type="checkbox" id="agas-guides" checked> Safety guides</label><button type="button" id="agas-help-open" title="Editor instructions">Need Help?</button><span class="agas-price">Price <strong id="agas-price">Calculating...</strong></span><button type="button" class="agas-primary" id="agas-continue"><?php echo $admin_template_mode ? 'Save Template' : 'Continue to Cart'; ?></button></footer>
          <section id="agas-dual-preview" class="agas-dual-preview" aria-label="Two ways to preview your design" hidden>
            <header class="agas-dual-preview-header"><div><span class="agas-eyebrow">PREVIEW YOUR PRINT</span><h2>See your design two ways.</h2><p>Review the artwork itself and how it may look in the real world.</p></div><div class="agas-dual-preview-side-switch" aria-label="Preview side"><button type="button" id="agas-preview-side-front" aria-pressed="true">Front</button><button type="button" id="agas-preview-side-back" aria-pressed="false" disabled>Back</button></div></header>
            <div class="agas-dual-preview-grid">
              <section class="agas-dual-preview-panel"><div class="agas-dual-preview-panel-header"><h3>Product mockup</h3><span>In context</span></div><div id="agas-editor-mockup" class="agas-mockup-stage agas-mockup-stage--<?php echo esc_attr(self::mockup_kind($products[$product_id])); ?>" data-mockup-kind="<?php echo esc_attr($product_kind); ?>" data-mockup-profile="<?php echo esc_attr($mockup_profile); ?>" data-mockup-base="<?php echo esc_url(plugins_url('assets/mockups/photos/', __FILE__)); ?>" data-mockup-photo="<?php echo esc_url(self::mockup_photo($mockup_profile)); ?>"><div class="agas-mockup-art"><img id="agas-preview-mockup-image" alt="Your current artwork shown on a product mockup"></div></div><p>Illustrative placement only. Use the flat artwork for exact content and print checks.</p></section>
              <section class="agas-dual-preview-panel"><div class="agas-dual-preview-panel-header"><h3>Flat artwork</h3><span>Design preview</span></div><div class="agas-flat-preview-stage"><img id="agas-preview-flat-image" alt="Your current design without editor guides or controls"></div><p>Shows the actual design layout without selection handles, safety guides or bleed overlays.</p></section>
            </div>
            <p id="agas-dual-preview-error" class="agas-dual-preview-error" role="alert" hidden></p>
          </section>
          <div class="agas-help-overlay" id="agas-help-overlay" hidden><section class="agas-help-dialog" role="dialog" aria-modal="true" aria-labelledby="agas-help-heading"><h2 id="agas-help-heading">Design Studio help</h2><p>Select an object to edit or drag it. Select text once to type in the left panel, or press Enter to edit on canvas. Use the side buttons to switch between front and back.</p><p>Shortcuts: Cmd/Ctrl+Z undo, Cmd/Ctrl+C copy, Cmd/Ctrl+V paste, Delete remove, arrow keys nudge. Save your work before changing templates.</p><div class="agas-help-actions"><button type="button" id="agas-help-tour" class="agas-help-tour-start">Start guided tour</button><button type="button" id="agas-help-close">Close help</button></div></section></div>

          <!-- Beginner tour is scoped to the editor and never changes/saves artwork. -->
          <div class="agas-tour-layer" id="agas-tour-layer" hidden>
            <div class="agas-tour-spotlight" id="agas-tour-spotlight" aria-hidden="true"></div>
            <section class="agas-tour-card" id="agas-tour-card" role="dialog" aria-modal="true" aria-labelledby="agas-tour-title" aria-describedby="agas-tour-description" tabindex="-1">
              <div class="agas-tour-card-heading"><span class="agas-tour-eyebrow">GETTING STARTED</span><button type="button" id="agas-tour-skip" class="agas-tour-skip" aria-label="Skip guided tour">Skip tour <span aria-hidden="true">×</span></button></div>
              <div class="agas-tour-progress" id="agas-tour-progress" aria-label="Guided tour progress"></div>
              <p class="agas-tour-step-number" id="agas-tour-step-number" aria-live="polite"></p>
              <h2 id="agas-tour-title">Welcome to AGAS Design Studio</h2>
              <p id="agas-tour-description">Learn the tools you need to customize your artwork.</p>
              <div class="agas-tour-actions"><button type="button" id="agas-tour-back" class="agas-tour-back">Back</button><button type="button" id="agas-tour-next" class="agas-tour-next">Next <span aria-hidden="true">→</span></button></div>
              <p class="agas-tour-shortcuts">Esc to skip · Arrow keys to navigate</p>
            </section>
          </div>

          <div class="agas-cart-choice-overlay" id="agas-cart-choice" hidden>
            <section class="agas-cart-choice-dialog" role="dialog" aria-modal="true" aria-labelledby="agas-cart-choice-title" aria-describedby="agas-cart-choice-help">
              <h2 id="agas-cart-choice-title">What would you like to do with your changes?</h2>
              <p id="agas-cart-choice-help">Choose whether to update your existing cart design or keep it and add a separate version.</p>
              <div class="agas-cart-choice-options">
                <button type="button" id="agas-choice-replace" class="agas-cart-choice-option">
                  <strong>Replace existing item</strong>
                  <span>Update the design already in your cart. Do not create another product.</span>
                </button>
                <button type="button" id="agas-choice-add" class="agas-cart-choice-option">
                  <strong>Add as a separate item</strong>
                  <span>Keep your original design unchanged and save a new independent copy.</span>
                </button>
              </div>
              <button type="button" id="agas-choice-cancel" class="agas-choice-cancel">Cancel — keep editing</button>
            </section>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function pricing_rules() {
        $defaults = array('sqft_rate' => 4, 'minimum' => 15, 'back' => 12, 'grommets' => 5, 'pole_pocket' => 8);
        $settings = get_option('agas_ds_pricing', array());
        return wp_parse_args(is_array($settings) ? $settings : array(), $defaults);
    }

    private static function clean_options($raw) {
        if (!is_array($raw)) $raw = array();
        $unit = isset($raw['unit']) && in_array($raw['unit'], array('ft', 'in', 'cm'), true) ? $raw['unit'] : 'ft';
        $w = isset($raw['width']) ? (float) $raw['width'] : 3;
        $h = isset($raw['height']) ? (float) $raw['height'] : 2;
        $factor = $unit === 'in' ? 12 : ($unit === 'cm' ? 30.48 : 1);
        if (!is_finite($w) || !is_finite($h) || $w / $factor < 0.25 || $h / $factor < 0.25 || $w / $factor > 20 || $h / $factor > 20) return new WP_Error('bad_size', 'Width and height must be between 0.25 and 20 feet.', array('status' => 400));
        // Finishing is mutually exclusive in both the customer UI and server pricing.
        if (!empty($raw['grommets']) && !empty($raw['pole_pocket'])) {
            return new WP_Error('conflicting_finishing', 'Choose either grommets or a pole pocket, not both.', array('status' => 400));
        }
        return array('width' => $w, 'height' => $h, 'unit' => $unit,
            'double_sided' => !empty($raw['double_sided']), 'grommets' => !empty($raw['grommets']), 'pole_pocket' => !empty($raw['pole_pocket']));
    }

    private static function calculate_price($options) {
        $rules = self::pricing_rules();
        $factor = $options['unit'] === 'in' ? 12 : ($options['unit'] === 'cm' ? 30.48 : 1);
        $area = ($options['width'] / $factor) * ($options['height'] / $factor);
        $base = max((float) $rules['minimum'], $area * (float) $rules['sqft_rate']);
        $addons = ($options['double_sided'] ? (float) $rules['back'] : 0) + ($options['grommets'] ? (float) $rules['grommets'] : 0) + ($options['pole_pocket'] ? (float) $rules['pole_pocket'] : 0);
        return round($base + $addons, wc_get_price_decimals());
    }

    public static function register_routes() {
        register_rest_route(self::REST_NS, '/price', array('methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => function($request) {
            $opts = self::clean_options($request->get_params());
            if (is_wp_error($opts)) return $opts;
            return rest_ensure_response(array('price' => self::calculate_price($opts), 'currency' => html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }));
        register_rest_route(self::REST_NS, '/designs', array('methods' => WP_REST_Server::CREATABLE, 'permission_callback' => '__return_true', 'callback' => array(__CLASS__, 'save_design')));
        register_rest_route(self::REST_NS, '/templates', array('methods' => WP_REST_Server::CREATABLE, 'permission_callback' => function() { return current_user_can('manage_woocommerce'); }, 'callback' => array(__CLASS__, 'save_template')));
        register_rest_route(self::REST_NS, '/templates/(?P<id>\d+)', array('methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true', 'callback' => array(__CLASS__, 'fetch_editor_template')));
    }

    /** Read only published, product-assigned template artwork for the in-editor sidebar. */
    public static function fetch_editor_template($request) {
        $id = absint($request['id']);
        $product_id = absint($request->get_param('product_id'));
        $post = get_post($id);
        if (!$post || $post->post_type !== 'agas_template' || $post->post_status !== 'publish'
            || !$product_id || !self::is_designable($product_id)
            || !self::template_belongs($id, $product_id)
            || get_post_meta($id, '_agas_legacy_hidden', true)) {
            return new WP_Error('template_unavailable', 'Template not available for this product.', array('status' => 404));
        }
        return rest_ensure_response(self::template_data($post));
    }

    private static function valid_canvas($canvas) {
        if (!is_array($canvas) || !isset($canvas['objects']) || !is_array($canvas['objects']) || count($canvas['objects']) > 150) return false;
        $json = wp_json_encode($canvas);
        return $json !== false && strlen($json) < 2500000;
    }

    private static function save_preview($data_uri, $post_id) {
        if (!is_string($data_uri) || !preg_match('#^data:image/png;base64,([a-zA-Z0-9+/=]+)$#', $data_uri, $m) || strlen($m[1]) > 3000000) return '';
        $bytes = base64_decode($m[1], true);
        if (!$bytes || strlen($bytes) > 2300000) return '';
        $size = @getimagesizefromstring($bytes);
        if (!$size || $size[2] !== IMAGETYPE_PNG || $size[0] > 2200 || $size[1] > 2200) return '';
        $dir = wp_upload_dir();
        if (!empty($dir['error'])) return '';
        $name = 'agas-design-' . absint($post_id) . '-' . wp_generate_password(8, false, false) . '.png';
        $uploaded = wp_upload_bits($name, null, $bytes);
        if (!empty($uploaded['error'])) return '';
        return $uploaded['url'];
    }

    public static function save_design($request) {
        $rate_key = 'agas_ds_rate_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $count = (int) get_transient($rate_key);
        if ($count >= 60) return new WP_Error('rate_limited', 'Too many saves from this connection. Try again later.', array('status' => 429));
        set_transient($rate_key, $count + 1, HOUR_IN_SECONDS);
        $params = $request->get_json_params();
        if (!is_array($params)) return new WP_Error('bad_input', 'Invalid request.', array('status' => 400));
        $product_id = absint($params['product_id'] ?? 0);
        if (!self::is_designable($product_id)) return new WP_Error('bad_product', 'Invalid assessment product.', array('status' => 400));
        $front = $params['front'] ?? null;
        $back = $params['back'] ?? null;
        $opts = self::clean_options($params['options'] ?? array());
        if (is_wp_error($opts)) return $opts;
        if (!self::valid_canvas($front) || ($opts['double_sided'] && !self::valid_canvas($back))) return new WP_Error('bad_canvas', 'Design could not be saved. Check object count and image size.', array('status' => 400));
        $template_id = absint($params['template_id'] ?? 0);
        if ($template_id && !self::template_belongs($template_id, $product_id)) return new WP_Error('bad_template', 'Invalid template for this product.', array('status' => 400));
        $existing_id = absint($params['design_id'] ?? 0);
        $existing_token = sanitize_text_field($params['token'] ?? '');
        if ($existing_id) {
            $existing = get_post($existing_id);
            if (!$existing || $existing->post_type !== 'agas_design' || !self::can_read_design($existing, $existing_token)) return new WP_Error('bad_access', 'Cannot modify this design.', array('status' => 403));
            if ((int) get_post_meta($existing_id, '_agas_product_id', true) !== $product_id) return new WP_Error('bad_product', 'Product cannot be changed.', array('status' => 400));
            $id = $existing_id;
            $token = (string) get_post_meta($id, '_agas_token', true);
        } else {
            $id = wp_insert_post(array('post_type' => 'agas_design', 'post_status' => 'private', 'post_title' => 'Customer design - ' . current_time('mysql'), 'post_author' => get_current_user_id()), true);
            if (is_wp_error($id)) return $id;
            $token = bin2hex(random_bytes(24));
            update_post_meta($id, '_agas_token', $token);
        }
        update_post_meta($id, '_agas_product_id', $product_id);
        update_post_meta($id, '_agas_template_id', $template_id);
        update_post_meta($id, '_agas_options', $opts);
        update_post_meta($id, '_agas_front', wp_json_encode($front));
        update_post_meta($id, '_agas_back', wp_json_encode($opts['double_sided'] ? $back : null));
        $preview = self::save_preview($params['preview'] ?? '', $id);
        if ($preview) update_post_meta($id, '_agas_preview', esc_url_raw($preview));
        return rest_ensure_response(array('id' => $id, 'token' => $token,
            'edit_url' => self::editor_url($product_id, $template_id, array('agas_design' => $id, 'agas_token' => $token)),
            'price' => self::calculate_price($opts), 'preview' => (string) get_post_meta($id, '_agas_preview', true)));
    }

    private static function can_read_design($post, $token) {
        if (current_user_can('manage_woocommerce') || (get_current_user_id() && (int) $post->post_author === get_current_user_id())) return true;
        $stored = (string) get_post_meta($post->ID, '_agas_token', true);
        return strlen($stored) > 24 && is_string($token) && hash_equals($stored, $token);
    }

    public static function save_template($request) {
        $data = $request->get_json_params();
        if (!is_array($data) || !self::valid_canvas($data['front'] ?? null)) return new WP_Error('invalid_template', 'Canvas not valid.', array('status' => 400));
        $product_id = absint($data['product_id'] ?? 0);
        if (!self::is_designable($product_id)) return new WP_Error('bad_product', 'Invalid product.', array('status' => 400));
        $existing_id = absint($data['template_id'] ?? 0);
        if ($existing_id && !current_user_can('edit_post', $existing_id)) return new WP_Error('forbidden', 'Not allowed.', array('status' => 403));
        $title = sanitize_text_field($data['name'] ?? 'New template');
        $requested_status = sanitize_key($data['status'] ?? ($existing_id ? get_post_status($existing_id) : 'draft'));
        $requested_status = in_array($requested_status, array('draft', 'publish'), true) ? $requested_status : 'draft';
        $id = wp_insert_post(array('ID' => $existing_id, 'post_type' => 'agas_template', 'post_title' => $title, 'post_status' => $requested_status), true);
        if (is_wp_error($id)) return $id;
        update_post_meta($id, '_agas_canvas', wp_json_encode($data['front']));
        if (!empty($data['back']) && self::valid_canvas($data['back'])) {
            update_post_meta($id, '_agas_canvas_back', wp_json_encode($data['back']));
        }
        delete_post_meta($id, '_agas_legacy_hidden');
        $products = isset($data['product_ids']) && is_array($data['product_ids']) ? array_map('absint', $data['product_ids']) : array($product_id);
        $products = array_values(array_intersect(array_unique($products), array_keys(self::designable_products())));
        if (!in_array($product_id, $products, true)) $products[] = $product_id;
        update_post_meta($id, '_agas_products', $products);
        $category = sanitize_text_field($data['category'] ?? 'Business');
        wp_set_object_terms($id, $category, 'agas_template_category');
        $requested_profile = sanitize_key($data['mockup_profile'] ?? '');
        $profile_kind = self::mockup_kind(self::designable_products()[$product_id] ?? '');
        if (self::valid_mockup_profile($profile_kind, $requested_profile)) {
            update_post_meta($id, '_agas_mockup_profile', $requested_profile);
        } else {
            delete_post_meta($id, '_agas_mockup_profile');
        }
        $thumbnail = self::save_preview($data['preview'] ?? '', $id);
        if ($thumbnail) update_post_meta($id, '_agas_thumb_url', esc_url_raw($thumbnail));
        return rest_ensure_response(array('id' => $id, 'template_url' => self::editor_url($product_id, $id, array('agas_edit_template' => $id))));
    }

    /**
     * Handle cart additions on a FRONTEND request, after WooCommerce has
     * restored the customer's cart from its session during wp_loaded.
     * admin-post.php is an admin request and does not reliably initialize
     * existing frontend cart contents (which can replace earlier items).
     */
    public static function maybe_frontend_add_to_cart() {
        if (empty($_GET['agas_design_add_to_cart'])) return;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            wp_die('Use the design editor to add a customized product.', '', array('response' => 405));
        }
        self::add_to_cart();
    }

    /**
     * Server-authoritative options always come from the saved design.
     * Cart item options are snapshots for display only and may be stale when
     * a customer edits a previously saved design before checkout.
     */
    private static function cart_design_options($item) {
        $design_id = absint($item['agas_design_id'] ?? 0);
        if (!$design_id) return null;
        $design = get_post($design_id);
        if (!$design || $design->post_type !== 'agas_design') return null;
        $product_id = absint($item['product_id'] ?? 0);
        if (!$product_id || (int) get_post_meta($design_id, '_agas_product_id', true) !== $product_id) return null;
        $stored_token = (string) get_post_meta($design_id, '_agas_token', true);
        $cart_token = (string) ($item['agas_design_token'] ?? '');
        if (strlen($stored_token) < 25 || !hash_equals($stored_token, $cart_token)) return null;
        $options = self::clean_options(get_post_meta($design_id, '_agas_options', true));
        return is_wp_error($options) ? null : $options;
    }

    /** Refresh snapshots as WooCommerce restores the cart on a new request. */
    public static function sync_session_cart_item($cart_item, $values, $cart_item_key) {
        $options = self::cart_design_options($cart_item);
        if ($options) $cart_item['agas_options'] = $options;
        return $cart_item;
    }

    /** Decide whether a save updates the existing cart line or creates a new one. */
    public static function cart_choice_action($mode, $existing) {
        if (!in_array($mode, array('auto', 'replace', 'add'), true)) {
            return new WP_Error('invalid_cart_mode', 'Invalid cart operation.');
        }
        if ($mode === 'replace' && !$existing) {
            return new WP_Error('cart_item_missing', 'The original item is no longer in your cart. Return to the cart and edit it again.');
        }
        if ($mode === 'add' && $existing) {
            return new WP_Error('design_already_in_cart', 'This saved design is already in the cart. Save a separate copy before adding it again.');
        }
        return ($mode === 'replace' || ($mode === 'auto' && $existing)) ? 'replace' : 'add';
    }

    public static function add_to_cart() {
        if (!function_exists('WC')) wp_die('WooCommerce is not available.', '', array('response' => 503));
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'agas_design_cart')) wp_die('Security check failed.', '', array('response' => 403));
        $id = isset($_POST['design_id']) ? absint($_POST['design_id']) : 0;
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $design = get_post($id);
        if (!$design || $design->post_type !== 'agas_design' || !self::can_read_design($design, $token)) wp_die('Saved design was not found or access was denied.', '', array('response' => 403));
        $product_id = (int) get_post_meta($id, '_agas_product_id', true);
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_purchasable() || !self::is_designable($product_id)) wp_die('Product is unavailable.', '', array('response' => 400));
        $opts = self::clean_options(get_post_meta($id, '_agas_options', true));
        if (is_wp_error($opts)) wp_die('Design options are invalid.', '', array('response' => 400));
        // Store the canonical server token even for authorized administrators
        // who may have opened a design without including its share token.
        $token = (string) get_post_meta($id, '_agas_token', true);
        if (strlen($token) < 25) wp_die('Saved design token is invalid.', '', array('response' => 400));
        if (!WC()->cart) wc_load_cart();
        if (!WC()->cart) wp_die('Could not initialize WooCommerce cart.', '', array('response' => 503));

        // Compatibility for a cached v0.1.7 editor using the old admin-post
        // endpoint. The new frontend endpoint does NOT need this extra load.
        if (is_admin() && isset(WC()->cart->session) && is_object(WC()->cart->session)
            && method_exists(WC()->cart->session, 'get_cart_from_session')) {
            WC()->cart->session->get_cart_from_session();
        }

        // Explicitly honor the customer's choice when returning from a cart edit.
        // A separate version MUST have a new saved design ID so the original
        // cart line, thumbnail, metadata and artwork cannot mutate later.
        $cart_mode = isset($_POST['cart_mode']) ? sanitize_key(wp_unslash($_POST['cart_mode'])) : 'auto';
        if (!in_array($cart_mode, array('auto', 'replace', 'add'), true)) {
            wp_die('Invalid cart operation.', '', array('response' => 400));
        }
        $source_id = isset($_POST['source_design_id']) ? absint($_POST['source_design_id']) : 0;
        if ($cart_mode === 'replace' && $source_id !== $id) {
            wp_die('A replacement must refer to the original saved design.', '', array('response' => 409));
        }
        $source_cart_key = isset($_POST['source_cart_key']) ? sanitize_text_field(wp_unslash($_POST['source_cart_key'])) : '';
        $existing_key = '';
        $cart_contents = WC()->cart->get_cart();
        if ($cart_mode === 'replace' && $source_cart_key !== '') {
            $selected = $cart_contents[$source_cart_key] ?? null;
            if (!is_array($selected) || (int) ($selected['agas_design_id'] ?? 0) !== $id
                || (int) ($selected['product_id'] ?? 0) !== $product_id
                || !hash_equals($token, (string) ($selected['agas_design_token'] ?? ''))) {
                wp_die('The cart item to replace is no longer available. Return to the cart and try again.', '', array('response' => 409));
            }
            $existing_key = $source_cart_key;
        } else {
            foreach ($cart_contents as $item_key => $cart_item) {
                if ((int) ($cart_item['agas_design_id'] ?? 0) === $id
                    && (int) ($cart_item['product_id'] ?? 0) === $product_id) {
                    $existing_key = $item_key;
                    break;
                }
            }
        }
        $decision = self::cart_choice_action($cart_mode, $existing_key !== '');
        if (is_wp_error($decision)) {
            wp_die($decision->get_error_message(), '', array('response' => 409));
        }
        if ($decision === 'replace') {
            WC()->cart->cart_contents[$existing_key]['agas_options'] = $opts;
            WC()->cart->cart_contents[$existing_key]['agas_design_token'] = $token;
            $key = $existing_key;
        } else {
            $key = WC()->cart->add_to_cart($product_id, 1, 0, array(), array(
                'agas_design_id' => $id,
                'agas_design_token' => $token,
                'agas_options' => $opts,
                'agas_unique' => wp_generate_uuid4(),
            ));
        }
        if (!$key) wp_die('Could not add the customized product to the cart.', '', array('response' => 400));
        WC()->cart->calculate_totals();
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }

    public static function apply_cart_prices($cart) {
        if (!is_a($cart, 'WC_Cart')) return;
        foreach ($cart->get_cart() as $key => $item) {
            if (empty($item['agas_design_id'])) continue;
            $opts = self::cart_design_options($item);
            if (!$opts) continue;
            $cart->cart_contents[$key]['agas_options'] = $opts;
            $cart->cart_contents[$key]['data']->set_price(self::calculate_price($opts));
        }
    }

    /**
     * WooCommerce's Cart block does not provide a supported per-line action slot.
     * Place a clear design-editing panel before the block, keeping product links unchanged.
     * We do not expose edit links for designs without matching cart session tokens.
     */
    private static function cart_design_actions_markup() {
        if (!function_exists('WC') || !WC()->cart) return '';
        $rows = array();
        foreach (WC()->cart->get_cart() as $cart_key => $item) {
            $id = absint($item['agas_design_id'] ?? 0);
            $product_id = absint($item['product_id'] ?? 0);
            if (!$id || !$product_id || get_post_type($id) !== 'agas_design') continue;
            if ((int) get_post_meta($id, '_agas_product_id', true) !== $product_id) continue;
            $canonical = (string) get_post_meta($id, '_agas_token', true);
            $session_token = (string) ($item['agas_design_token'] ?? '');
            if ($canonical === '' || $session_token === '' || !hash_equals($canonical, $session_token)) continue;
            $template_id = absint(get_post_meta($id, '_agas_template_id', true));
            if ($template_id && !self::template_belongs($template_id, $product_id)) $template_id = 0;
            $url = self::editor_url($product_id, $template_id, array(
                'agas_design' => $id,
                'agas_token' => $canonical,
                'agas_from_cart' => 1,
                'agas_cart_key' => $cart_key,
            ));
            $options = self::cart_design_options($item);
            $details = $options && !is_wp_error($options)
                ? $options['width'] . ' x ' . $options['height'] . ' ' . $options['unit'] .
                  (!empty($options['double_sided']) ? ' / front and back' : ' / front only')
                : '';
            $product = wc_get_product($product_id);
            $title = $product ? $product->get_name() : 'Customized product';
            $rows[] = '<div class="agas-cart-edit-row"><div><strong>' . esc_html($title) .
                ' - Design #' . esc_html((string) $id) . '</strong><small>' . esc_html($details) .
                '</small></div><a class="agas-cart-edit-link" rel="nofollow" href="' .
                esc_url($url) . '">Edit design</a></div>';
        }
        if (!$rows) return '';
        return '<section class="agas-cart-edit-panel" aria-label="Edit your customized designs"><div class="agas-cart-edit-header"><h2>Edit your designs</h2><p>Need to change text, artwork, size or printing sides? Open your saved design, make changes, and return to this cart. Other items will stay in your cart.</p></div>' . implode('', $rows) . '</section>';
    }

    public static function cart_design_actions_content($content) {
        if (!function_exists('is_cart') || !is_cart() || !in_the_loop() || !is_main_query()) return $content;
        if (function_exists('wc_get_page_id') && (int) get_the_ID() !== (int) wc_get_page_id('cart')) return $content;
        if (function_exists('has_block') && !has_block('woocommerce/cart', get_post())) return $content;
        return self::cart_design_actions_markup() . $content;
    }

    public static function classic_cart_design_actions() {
        if (function_exists('has_block') && has_block('woocommerce/cart', get_post())) return;
        echo self::cart_design_actions_markup(); // Escaping is handled during construction.
    }

    public static function cart_item_details($data, $item) {
        if (empty($item['agas_design_id'])) return $data;
        $opts = self::cart_design_options($item);
        if (!$opts) return $data;
        $data[] = array('key' => 'Design', 'value' => 'Custom design #' . absint($item['agas_design_id']));
        $data[] = array('key' => 'Size', 'value' => esc_html($opts['width'] . ' x ' . $opts['height'] . ' ' . $opts['unit']));
        $data[] = array('key' => 'Sides', 'value' => $opts['double_sided'] ? 'Front and Back' : 'Front only');
        $extras = array();
        if ($opts['grommets']) $extras[] = 'Grommets';
        if ($opts['pole_pocket']) $extras[] = 'Pole pocket';
        if ($extras) $data[] = array('key' => 'Extras', 'value' => esc_html(implode(', ', $extras)));
        return $data;
    }

    public static function cart_thumbnail($image, $item, $key) {
        if (empty($item['agas_design_id'])) return $image;
        $preview = get_post_meta(absint($item['agas_design_id']), '_agas_preview', true);
        return $preview ? '<img src="' . esc_url($preview) . '" alt="Saved custom design preview" style="max-width:100px;height:auto;">' : $image;
    }

    /** Only return a preview generated inside this WordPress uploads folder. */
    private static function saved_preview_url($design_id) {
        $preview = (string) get_post_meta(absint($design_id), '_agas_preview', true);
        $uploads = wp_upload_dir();
        if (!$preview || !empty($uploads['error']) || empty($uploads['baseurl'])) return '';
        $prefix = trailingslashit($uploads['baseurl']);
        if (strpos($preview, $prefix) !== 0) return '';
        return esc_url_raw($preview);
    }

    /**
     * WooCommerce Cart and Checkout BLOCKS read item images from the Store API,
     * not from woocommerce_cart_item_thumbnail (classic templates only).
     * Keep the original thumbnail if the design has no usable saved preview.
     */
    public static function cart_block_images($images, $cart_item, $cart_item_key) {
        $design_id = empty($cart_item['agas_design_id']) ? 0 : absint($cart_item['agas_design_id']);
        if (!$design_id) return $images;
        $preview = self::saved_preview_url($design_id);
        if (!$preview) return $images;
        return array((object) array(
            'id' => 0,
            'src' => $preview,
            'thumbnail' => $preview,
            'srcset' => '',
            'sizes' => '',
            'name' => 'Customer customized product design',
            'alt' => 'Preview of the customer customized design',
        ));
    }

    /** Add a preview to order details and HTML emails, with a URL in plain emails. */
    public static function order_item_preview($item_id, $item, $order, $plain_text = false) {
        if (!is_a($item, 'WC_Order_Item_Product')) return;
        $design_id = absint($item->get_meta('_agas_design_id', true));
        if (!$design_id) return;
        $preview = self::saved_preview_url($design_id);
        if (!$preview) return;
        if ($plain_text) {
            echo "\nDesign preview: " . esc_url_raw($preview) . "\n";
            return;
        }
        echo '<p class="agas-order-preview"><strong>Customized artwork preview</strong><br>'
            . '<img src="' . esc_url($preview) . '" alt="Customer customized product design" width="180" '
            . 'style="width:180px;max-width:100%;height:auto;border:1px solid #ddd"></p>';
    }

    public static function order_item_meta($order_item, $cart_item_key, $values, $order) {
        if (empty($values['agas_design_id'])) return;
        $id = absint($values['agas_design_id']);
        $opts = self::cart_design_options($values);
        if (!$opts) return;
        $order_item->add_meta_data('_agas_design_id', $id, true);
        $order_item->add_meta_data('_agas_design_token', sanitize_text_field($values['agas_design_token'] ?? ''), true);
        $order_item->add_meta_data('Print Size', $opts['width'] . ' x ' . $opts['height'] . ' ' . $opts['unit'], true);
        $order_item->add_meta_data('Sides', $opts['double_sided'] ? 'Front and Back' : 'Front only', true);
        $order_item->add_meta_data('Grommets', $opts['grommets'] ? 'Yes' : 'No', true);
        $order_item->add_meta_data('Pole Pocket', $opts['pole_pocket'] ? 'Yes' : 'No', true);
        $preview = (string) get_post_meta($id, '_agas_preview', true);
        if ($preview) $order_item->add_meta_data('Design Preview', esc_url_raw($preview), true);
    }

    public static function admin_order_links($item_id, $item, $product) {
        if (!current_user_can('manage_woocommerce') || !is_a($item, 'WC_Order_Item_Product')) return;
        $id = absint($item->get_meta('_agas_design_id', true));
        if (!$id) return;
        $product_id = (int) get_post_meta($id, '_agas_product_id', true);
        $template_id = (int) get_post_meta($id, '_agas_template_id', true);
        echo '<p class="agas-admin-link"><a target="_blank" rel="noopener" href="' . esc_url(self::editor_url($product_id, $template_id, array('agas_design' => $id, 'agas_readonly' => 1))) . '">Open saved design</a></p>';
        $order_id = absint($item->get_order_id());
        $line_id = absint($item->get_id());
        $export_url = wp_nonce_url(add_query_arg(array('action' => 'agas_design_print_center', 'order_id' => $order_id, 'line_id' => $line_id), admin_url('admin-post.php')), 'agas_print_' . $order_id . '_' . $line_id);
        echo '<p class="agas-admin-link"><a href="' . esc_url($export_url) . '" target="_blank" rel="noopener">Print export center (PDF / 150 DPI PNG / original uploads)</a></p>';
    }


    /** Secondary link location for newer WooCommerce order administration screens. */
    public static function admin_order_print_panel($order) {
        if (!current_user_can('manage_woocommerce') || !is_a($order, 'WC_Order')) return;
        $links = array();
        foreach ($order->get_items('line_item') as $item) {
            $line_id = absint($item->get_id());
            $design_id = absint($item->get_meta('_agas_design_id', true));
            if (!$design_id || get_post_type($design_id) !== 'agas_design') continue;
            $url = wp_nonce_url(add_query_arg(array('action' => 'agas_design_print_center', 'order_id' => $order->get_id(), 'line_id' => $line_id), admin_url('admin-post.php')), 'agas_print_' . $order->get_id() . '_' . $line_id);
            $links[] = '<li><a target="_blank" rel="noopener" href="' . esc_url($url) . '">' . esc_html($item->get_name()) . ' — Download PDF / 150-DPI PNG</a></li>';
        }
        if ($links) echo '<div class="agas-order-print-panel"><h3>AGAS Design Studio — Print Files</h3><ul>' . implode('', $links) . '</ul></div>';
    }

    /**
     * Exports are initiated by an authenticated store administrator. Nothing
     * is rendered from guest-supplied JSON on the order management screen.
     * This prototype generates PDFs/PNGs in the browser from the saved Fabric
     * canvas, avoiding a new server-side graphics dependency in the demo.
     */
    private static function print_order_item($order_id, $line_id) {
        if (!current_user_can('manage_woocommerce') || !function_exists('wc_get_order')) return null;
        $order = wc_get_order($order_id);
        if (!$order) return null;
        $item = $order->get_item($line_id);
        if (!$item || !is_a($item, 'WC_Order_Item_Product')) return null;
        $design_id = absint($item->get_meta('_agas_design_id', true));
        if (!$design_id || get_post_type($design_id) !== 'agas_design') return null;
        return array($order, $item, $design_id);
    }

    private static function print_canvas_data($design_id, $side) {
        $key = $side === 'back' ? '_agas_back' : '_agas_front';
        $canvas = json_decode((string) get_post_meta($design_id, $key, true), true);
        return self::valid_canvas($canvas) ? $canvas : null;
    }

    /** Enumerate user-uploaded image sources, preserving source bytes. */
    private static function original_images($canvas) {
        if (!is_array($canvas)) return array();
        $found = array();
        $stack = isset($canvas['objects']) && is_array($canvas['objects']) ? $canvas['objects'] : array();
        if (!empty($canvas['backgroundImage']) && is_array($canvas['backgroundImage'])) $stack[] = $canvas['backgroundImage'];
        while ($stack) {
            $obj = array_pop($stack);
            if (!is_array($obj)) continue;
            if (isset($obj['objects']) && is_array($obj['objects'])) {
                foreach ($obj['objects'] as $child) $stack[] = $child;
            }
            if (($obj['type'] ?? '') !== 'image') continue;
            $src = $obj['src'] ?? '';
            if (!is_string($src) || strlen($src) > 2500000) continue;
            if (!preg_match('#^data:image/(png|jpeg);base64,([a-zA-Z0-9+/=]+)$#D', $src, $m)) continue;
            $name = sanitize_file_name((string) ($obj['agasOriginalName'] ?? 'customer-artwork'));
            if (!$name) $name = 'customer-artwork';
            $ext = $m[1] === 'png' ? 'png' : 'jpg';
            $filename = preg_replace('/\.(png|jpg|jpeg)$/i', '', $name) . '.' . $ext;
            $found[] = array('name' => $filename, 'src' => $src, 'type' => $ext);
        }
        return array_reverse($found);
    }

    public static function download_original() {
        $order_id = absint($_GET['order_id'] ?? 0);
        $line_id = absint($_GET['line_id'] ?? 0);
        $side = sanitize_key($_GET['side'] ?? '');
        $index = isset($_GET['index']) ? absint($_GET['index']) : -1;
        if (!in_array($side, array('front', 'back'), true)) wp_die('Invalid print side.', '', array('response' => 400));
        check_admin_referer('agas_original_' . $order_id . '_' . $line_id . '_' . $side . '_' . $index);
        $info = self::print_order_item($order_id, $line_id);
        if (!$info) wp_die('Order artwork not available.', '', array('response' => 403));
        $canvas = self::print_canvas_data($info[2], $side);
        $files = self::original_images($canvas);
        if (!isset($files[$index])) wp_die('Original upload not found.', '', array('response' => 404));
        if (!preg_match('#^data:image/(png|jpeg);base64,([a-zA-Z0-9+/=]+)$#D', $files[$index]['src'], $m)) wp_die('Invalid image.', '', array('response' => 400));
        $bytes = base64_decode($m[2], true);
        if (!$bytes || strlen($bytes) > 1900000) wp_die('Invalid file content.', '', array('response' => 400));
        $image = @getimagesizefromstring($bytes);
        if (!$image || !in_array($image['mime'], array('image/png', 'image/jpeg'), true)) wp_die('Unsupported image data.', '', array('response' => 400));
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $image['mime']);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $files[$index]['name']) . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    public static function print_center() {
        $order_id = absint($_GET['order_id'] ?? 0);
        $line_id = absint($_GET['line_id'] ?? 0);
        check_admin_referer('agas_print_' . $order_id . '_' . $line_id);
        $info = self::print_order_item($order_id, $line_id);
        if (!$info) wp_die('Order or design not found, or access denied.', '', array('response' => 403));
        list($order, $item, $design_id) = $info;
        $options = self::clean_options((array) get_post_meta($design_id, '_agas_options', true));
        if (is_wp_error($options)) wp_die('Saved print dimensions are invalid.', '', array('response' => 400));
        $front = self::print_canvas_data($design_id, 'front');
        $back = !empty($options['double_sided']) ? self::print_canvas_data($design_id, 'back') : null;
        if (!$front || (!empty($options['double_sided']) && !$back)) wp_die('Saved canvas data is unavailable.', '', array('response' => 404));
        $line_index = 1;
        foreach ($order->get_items('line_item') as $order_line) {
            if ((int) $order_line->get_id() === $line_id) break;
            $line_index++;
        }
        $payload = array(
            'orderId' => (int) $order_id,
            'lineIndex' => (int) $line_index,
            'product' => $item->get_name(),
            'size' => $options,
            'front' => $front,
            'back' => $back,
            'bleedInches' => 0.125,
            'dpi' => 150,
        );
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Content-Type: text/html; charset=utf-8');
        $fabric_url = 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js';
        $pdf_url = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.2/jspdf.umd.min.js';
        $svg_url = 'https://cdn.jsdelivr.net/npm/svg2pdf.js@2.8.1/dist/svg2pdf.umd.min.js';
        $print_js = plugins_url('assets/print-export.js', __FILE__);
        $offline_pdf_js = plugins_url('assets/offline-pdf.js', __FILE__);
        $options_desc = $options['width'] . ' x ' . $options['height'] . ' ' . $options['unit'];
        $back_link = $order->get_edit_order_url();
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>AGAS Print Exports — Order #' . esc_html($order_id) . '</title>';
        echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&amp;family=Roboto:wght@400;700&amp;family=Open+Sans:wght@400;700&amp;family=Lato:wght@400;700&amp;family=Montserrat:wght@400;700&amp;family=Poppins:wght@400;700&amp;family=Oswald:wght@400;700&amp;family=Merriweather:wght@400;700&amp;family=Raleway:wght@400;700&amp;family=Nunito:wght@400;700&amp;family=Playfair+Display:wght@400;700&amp;family=Source+Sans+3:wght@400;700&amp;display=swap">';
        echo '<style>body{font-family:system-ui,-apple-system,Arial;background:#f4f7fb;color:#142b43;margin:0}header{background:#fff;border-bottom:1px solid #dde4ee;padding:20px max(24px,6vw)}main{max-width:960px;margin:28px auto;padding:0 20px}h1{margin:4px 0 10px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px}.card{background:#fff;border:1px solid #dde4ee;border-radius:12px;padding:22px}button{background:#1551c0;color:white;border:0;padding:12px 16px;margin:6px 8px 6px 0;border-radius:8px;font-weight:600;cursor:pointer}button:disabled{opacity:.5;cursor:wait}a{color:#1551c0}small,.muted{color:#62738b}#agas-print-status{font-weight:600;line-height:1.6}.warn{background:#fff9e9;padding:12px;border-left:4px solid #eab308}.bad{color:#bb1d28}.good{color:#187044}ul{padding-left:20px;line-height:1.8}</style></head><body>';
        echo '<header><strong>AGAS DESIGN STUDIO · PRINT EXPORT CENTER</strong> &nbsp; <a href="' . esc_url($back_link) . '">Back to order</a></header>';
        echo '<main><h1>Order #' . esc_html($order_id) . ' — ' . esc_html($item->get_name()) . '</h1>';
        echo '<p class="muted">Line ' . esc_html($line_index) . ' · Design #' . esc_html($design_id) . ' · Print size ' . esc_html($options_desc) . ' · 0.125-inch bleed on each edge · 150 DPI PNG</p>';
        echo '<p class="warn">Demo export: PDF attempts vector output via SVG; browser fallback may rasterize unsupported effects. Check artwork dimensions, font appearance, image quality, and bleed in a print preflight before manufacturing. Extremely large designs may exceed browser memory.</p>';
        echo '<div class="grid"><section class="card"><h2>Front side</h2><p><button data-agas-export="pdf" data-side="front">Download Front PDF</button><button data-agas-export="png" data-side="front">Download Front 150-DPI PNG</button></p></section>';
        if ($back) echo '<section class="card"><h2>Back side</h2><p><button data-agas-export="pdf" data-side="back">Download Back PDF</button><button data-agas-export="png" data-side="back">Download Back 150-DPI PNG</button></p></section>';
        echo '</div><section class="card" style="margin-top:18px"><h2>Customer-uploaded originals</h2><ul>';
        $total = 0;
        foreach (array('front' => $front, 'back' => $back) as $side => $json) {
            if (!$json) continue;
            foreach (self::original_images($json) as $index => $image) {
                $url = wp_nonce_url(add_query_arg(array('action' => 'agas_design_original', 'order_id' => $order_id, 'line_id' => $line_id, 'side' => $side, 'index' => $index), admin_url('admin-post.php')), 'agas_original_' . $order_id . '_' . $line_id . '_' . $side . '_' . $index);
                echo '<li>' . esc_html(ucfirst($side)) . ': <a href="' . esc_url($url) . '">' . esc_html($image['name']) . '</a></li>';
                $total++;
            }
        }
        if (!$total) echo '<li>No uploaded image originals are attached to this saved design.</li>';
        echo '</ul></section><section class="card" style="margin-top:18px"><h2>Export diagnostics</h2><p id="agas-print-status" aria-live="polite">Loading canvas and PDF libraries…</p><div id="agas-print-dimensions" class="muted"></div></section>';
        echo '<script type="application/json" id="agas-print-data">' . wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
        echo '<script src="' . esc_url($fabric_url) . '"></script><script src="' . esc_url($pdf_url) . '"></script><script src="' . esc_url($svg_url) . '"></script>';
        echo '<script src="' . esc_url($offline_pdf_js) . '?ver=' . esc_attr(self::VERSION) . '"></script>';
        echo '<script src="' . esc_url($print_js) . '?ver=' . esc_attr(self::VERSION) . '"></script>';
        echo '</main></body></html>';
        exit;
    }

    public static function add_template_box() {
        add_meta_box('agas_template_setup', 'Designer Setup', array(__CLASS__, 'template_box'), 'agas_template', 'normal', 'high');
    }

    public static function template_box($post) {
        wp_nonce_field('agas_template_meta', 'agas_template_meta_nonce');
        $assigned = array_map('intval', (array) get_post_meta($post->ID, '_agas_products', true));
        echo '<p>Assign this template to products. Publish it, then open the canvas editor.</p>';
        foreach (self::designable_products() as $id => $name) {
            echo '<label style="display:block;margin:8px 0"><input type="checkbox" name="agas_products[]" value="' . esc_attr($id) . '" ' . checked(in_array($id, $assigned, true), true, false) . '> ' . esc_html($name) . '</label>';
        }
        if ($assigned) {
            echo '<p><a class="button button-primary" href="' . esc_url(self::editor_url($assigned[0], $post->ID, array('agas_edit_template' => $post->ID))) . '">Edit Template in Design Studio</a></p>';
        }
        echo '<p>Category tags can be edited in the Template Categories panel.</p>';
    }

    public static function save_template_box($post_id) {
        if (!isset($_POST['agas_template_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['agas_template_meta_nonce'])), 'agas_template_meta') || !current_user_can('edit_post', $post_id)) return;
        $posted = isset($_POST['agas_products']) && is_array($_POST['agas_products']) ? array_map('absint', wp_unslash($_POST['agas_products'])) : array();
        $allowed = array_keys(self::designable_products());
        update_post_meta($post_id, '_agas_products', array_values(array_intersect($posted, $allowed)));
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=agas_template', 'Designer Pricing', 'Pricing Rules', 'manage_woocommerce', 'agas-designer-pricing', array(__CLASS__, 'pricing_page'));
        add_submenu_page('edit.php?post_type=agas_template', 'Create Template in Designer', 'Create in Designer', 'manage_woocommerce', 'agas-designer-new-template', array(__CLASS__, 'new_template_page'));
    }

    public static function new_template_page() {
        if (!current_user_can('manage_woocommerce')) return;
        echo '<div class="wrap"><h1>Create a Design Template</h1><p>Choose a product to open the same canvas editor customers use. You can then assign it to additional products, choose Draft/Published, and save its thumbnail.</p>';
        foreach (self::designable_products() as $id => $name) {
            $link = self::editor_url($id, 0, array('agas_new_template' => 1));
            echo '<p><a class="button button-primary" href="' . esc_url($link) . '">Create ' . esc_html($name) . ' Template</a></p>';
        }
        echo '</div>';
    }

    public static function template_list_columns($columns) {
        $out = array();
        foreach ($columns as $key => $value) {
            $out[$key] = $value;
            if ($key === 'cb') $out['agas_thumb'] = 'Preview';
            if ($key === 'title') { $out['agas_products'] = 'Products'; $out['agas_category'] = 'Category'; $out['agas_status'] = 'Status'; }
        }
        return $out;
    }

    public static function template_list_cell($column, $post_id) {
        if ($column === 'agas_thumb') {
            $image = self::template_thumbnail($post_id);
            if ($image) echo '<img src="' . esc_url($image) . '" alt="Template preview" style="max-width:88px;max-height:68px;border-radius:5px;object-fit:contain">';
            else echo '&mdash;';
        } elseif ($column === 'agas_products') {
            $names = self::designable_products();
            $list = array();
            foreach ((array) get_post_meta($post_id, '_agas_products', true) as $id) {
                if (isset($names[(int) $id])) $list[] = $names[(int) $id];
            }
            echo esc_html($list ? implode(', ', $list) : 'Not assigned');
        } elseif ($column === 'agas_category') {
            $terms = wp_get_post_terms($post_id, 'agas_template_category', array('fields' => 'names'));
            echo esc_html(!is_wp_error($terms) && $terms ? implode(', ', $terms) : '—');
        } elseif ($column === 'agas_status') {
            echo esc_html(ucfirst((string) get_post_status($post_id)));
        }
    }

    public static function register_settings() {
        register_setting('agas_design_pricing', 'agas_ds_pricing', array('sanitize_callback' => array(__CLASS__, 'sanitize_pricing')));
    }

    public static function sanitize_pricing($input) {
        $defaults = self::pricing_rules();
        $out = array();
        foreach ($defaults as $key => $value) {
            $n = isset($input[$key]) ? (float) $input[$key] : (float) $value;
            $out[$key] = is_finite($n) ? min(10000, max(0, $n)) : $value;
        }
        return $out;
    }

    public static function pricing_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $rules = self::pricing_rules();
        $labels = array('sqft_rate' => 'Price per square foot', 'minimum' => 'Minimum base price', 'back' => 'Back-side surcharge', 'grommets' => 'Grommets charge', 'pole_pocket' => 'Pole-pocket charge');
        echo '<div class="wrap"><h1>AGAS Designer Pricing</h1><p>These values are applied on the server to both quotes and WooCommerce cart totals.</p><form method="post" action="' . esc_url(admin_url('options.php')) . '">';
        settings_fields('agas_design_pricing');
        echo '<table class="form-table">';
        foreach ($labels as $key => $label) echo '<tr><th><label for="agas-' . esc_attr($key) . '">' . esc_html($label) . ' ($)</label></th><td><input id="agas-' . esc_attr($key) . '" type="number" min="0" max="10000" step="0.01" name="agas_ds_pricing[' . esc_attr($key) . ']" value="' . esc_attr($rules[$key]) . '"></td></tr>';
        echo '</table>';
        submit_button('Save Pricing Rules');
        echo '</form></div>';
    }

    public static function template_row_actions($actions, $post) {
        if ($post->post_type === 'agas_template' && current_user_can('edit_post', $post->ID)) {
            $url = wp_nonce_url(add_query_arg(array('action' => 'agas_duplicate_template', 'template_id' => $post->ID), admin_url('admin-post.php')), 'agas_duplicate_' . $post->ID);
            $actions['agas_duplicate'] = '<a href="' . esc_url($url) . '">Duplicate template</a>';
        }
        return $actions;
    }

    public static function duplicate_template() {
        $id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;
        check_admin_referer('agas_duplicate_' . $id);
        if (!$id || !current_user_can('edit_post', $id)) wp_die('Not authorized.', '', array('response' => 403));
        $original = get_post($id);
        if (!$original || $original->post_type !== 'agas_template') wp_die('Invalid template.');
        $copy = wp_insert_post(array('post_type' => 'agas_template', 'post_title' => $original->post_title . ' (Copy)', 'post_status' => 'draft'));
        foreach (array('_agas_products','_agas_canvas','_agas_thumb_color','_agas_thumb_url','_agas_seed_preview','_agas_mockup_profile') as $key) update_post_meta($copy, $key, get_post_meta($id, $key, true));
        wp_set_object_terms($copy, wp_get_post_terms($id, 'agas_template_category', array('fields' => 'ids')), 'agas_template_category');
        wp_safe_redirect(get_edit_post_link($copy, '')); exit;
    }

    public static function account_designs_content($content) {
        if (!function_exists('is_account_page') || !is_account_page() || !is_user_logged_in() || !in_the_loop() || !is_main_query()) return $content;
        $posts = get_posts(array('post_type' => 'agas_design', 'post_status' => 'private', 'author' => get_current_user_id(), 'numberposts' => 30));
        if (!$posts) return $content;
        $block = '<section class="agas-my-designs"><h3>My Designs</h3><ul>';
        foreach ($posts as $post) {
            $product_id = (int) get_post_meta($post->ID, '_agas_product_id', true);
            $template_id = (int) get_post_meta($post->ID, '_agas_template_id', true);
            $url = self::editor_url($product_id, $template_id, array('agas_design' => $post->ID));
            $block .= '<li><a href="' . esc_url($url) . '">Design #' . absint($post->ID) . ' - ' . esc_html(get_the_title($product_id)) . '</a></li>';
        }
        $block .= '</ul></section>';
        return $content . $block;
    }
}

AGAS_Design_Studio::boot();
register_activation_hook(__FILE__, array('AGAS_Design_Studio', 'activate'));
