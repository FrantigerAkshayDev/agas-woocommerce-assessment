<?php
/**
 * Plugin Name: AGAS Design Studio
 * Description: Custom WooCommerce template gallery, online canvas designer, and order integration.
 * Version: 0.1.5
 * Requires Plugins: woocommerce
 * Text Domain: agas-design-studio
 */

defined('ABSPATH') || exit;

final class AGAS_Design_Studio {
    const VERSION = '0.1.5';
    const REST_NS = 'agas-design/v1';

    public static function boot() {
        add_action('init', array(__CLASS__, 'register_types'), 5);
        add_action('init', array(__CLASS__, 'seed_once'), 99);
        add_action('init', array(__CLASS__, 'upgrade_logo_slots'), 100);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_page_assets'));
        add_filter('template_include', array(__CLASS__, 'standalone_page_template'), 99);
        add_shortcode('agas_template_gallery', array(__CLASS__, 'gallery_shortcode'));
        add_shortcode('agas_designer', array(__CLASS__, 'designer_shortcode'));
        add_action('woocommerce_single_product_summary', array(__CLASS__, 'product_cta'), 35);
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('admin_post_agas_design_add_cart', array(__CLASS__, 'add_to_cart'));
        add_action('admin_post_nopriv_agas_design_add_cart', array(__CLASS__, 'add_to_cart'));
        add_action('woocommerce_before_calculate_totals', array(__CLASS__, 'apply_cart_prices'), 20);
        add_filter('woocommerce_get_item_data', array(__CLASS__, 'cart_item_details'), 10, 2);
        add_filter('woocommerce_cart_item_thumbnail', array(__CLASS__, 'cart_thumbnail'), 10, 3);
        add_filter('woocommerce_store_api_cart_item_images', array(__CLASS__, 'cart_block_images'), 10, 3);
        add_action('woocommerce_order_item_meta_end', array(__CLASS__, 'order_item_preview'), 10, 4);
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'order_item_meta'), 10, 4);
        add_action('woocommerce_after_order_itemmeta', array(__CLASS__, 'admin_order_links'), 10, 3);
        add_action('add_meta_boxes_agas_template', array(__CLASS__, 'add_template_box'));
        add_action('save_post_agas_template', array(__CLASS__, 'save_template_box'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_post_agas_duplicate_template', array(__CLASS__, 'duplicate_template'));
        add_filter('post_row_actions', array(__CLASS__, 'template_row_actions'), 10, 2);
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
            'front' => json_decode((string) get_post_meta($post->ID, '_agas_canvas', true), true),
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
    }

    private static function enqueue_assets($include_editor = false) {
        wp_enqueue_style('agas-design-studio', plugins_url('assets/studio.css', __FILE__), array(), self::VERSION);
        if ($include_editor) {
            wp_enqueue_script('agas-fabric', 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js', array(), '5.3.1', true);
            wp_enqueue_script('agas-studio', plugins_url('assets/studio.js', __FILE__), array('agas-fabric'), self::VERSION, true);
        } else {
            wp_enqueue_script('agas-gallery', plugins_url('assets/gallery.js', __FILE__), array(), self::VERSION, true);
        }
    }

    public static function product_cta() {
        global $product;
        if (!$product || !self::is_designable($product->get_id())) return;
        echo '<div class="agas-product-cta"><a class="button alt" href="' . esc_url(self::gallery_url($product->get_id())) . '">Choose a Design or Start Blank</a></div>';
    }

    public static function gallery_shortcode() {
        if (!function_exists('wc_get_product')) return '<p>WooCommerce is required.</p>';
        self::enqueue_assets();
        $products = self::designable_products();
        $product_id = isset($_GET['agas_product']) ? absint($_GET['agas_product']) : 0;
        if (!$product_id || !isset($products[$product_id])) $product_id = (int) array_key_first($products);
        if (!$product_id) return '<p>Create the three assessment products first.</p>';
        $posts = get_posts(array('post_type' => 'agas_template', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC'));
        ob_start();
        echo '<section class="agas-gallery"><div class="agas-gallery-header"><div><span class="agas-eyebrow">AGAS DESIGN STUDIO</span><h1>Choose your starting design</h1><p>Pick a template or begin with a blank canvas. Every element can be customized.</p></div><a class="agas-action" href="' . esc_url(self::editor_url($product_id)) . '">Start from blank</a></div>';
        $gallery_categories = array();
        foreach ($posts as $candidate) {
            if (!self::template_belongs($candidate->ID, $product_id) || get_post_meta($candidate->ID, '_agas_legacy_hidden', true)) continue;
            $entry = self::template_data($candidate);
            $gallery_categories[$entry['category']] = true;
        }
        uksort($gallery_categories, 'strnatcasecmp');
        echo '<div class="agas-gallery-controls"><label>Product <select id="agas-product-select">';
        foreach ($products as $pid => $name) echo '<option value="' . esc_url(self::gallery_url($pid)) . '"' . selected($pid, $product_id, false) . '>' . esc_html($name) . '</option>';
        echo '</select></label><label>Category <select id="agas-category-filter"><option value="">All categories</option>';
        foreach (array_keys($gallery_categories) as $cat) echo '<option>' . esc_html($cat) . '</option>';
        echo '</select></label><label>Find a design <input id="agas-gallery-search" type="search" placeholder="Search by name..."></label></div>';
        echo '<div id="agas-template-cards" class="agas-gallery-grid">';
        $shown = 0;
        foreach ($posts as $post) {
            if (!self::template_belongs($post->ID, $product_id) || get_post_meta($post->ID, '_agas_legacy_hidden', true)) continue;
            $tpl = self::template_data($post);
            $bg = sanitize_hex_color(get_post_meta($post->ID, '_agas_thumb_color', true)) ?: '#294B72';
            $front = is_array($tpl['front']) ? $tpl['front'] : array();
            $title_display = $post->post_title;
            $parts = explode(' - ', $title_display, 2);
            if (count($parts) === 2) $title_display = $parts[1];
            echo '<article class="agas-template-card" data-category="' . esc_attr(strtolower($tpl['category'])) . '" data-name="' . esc_attr(strtolower($title_display)) . '">';
            if ($tpl['thumbnail']) {
                echo '<div class="agas-card-image"><img src="' . esc_url($tpl['thumbnail']) . '" alt="' . esc_attr($title_display) . '"></div>';
            } else {
                echo '<div class="agas-card-image agas-card-generated" style="background:' . esc_attr($bg) . '"><div class="agas-card-frame"><strong>' . esc_html(strtoupper($title_display)) . '</strong><span>CUSTOMIZE YOUR MESSAGE</span></div></div>';
            }
            echo '<div class="agas-card-foot"><div><strong>' . esc_html($title_display) . '</strong><small>' . esc_html($tpl['category']) . '</small></div><a href="' . esc_url(self::editor_url($product_id, $post->ID)) . '" class="agas-card-button">Customize</a></div></article>';
            $shown++;
        }
        echo '</div><p id="agas-gallery-empty" hidden>No matching templates. Try another search.</p></section>';
        if (!$shown) echo '<p>Templates are being prepared. You can still start blank.</p>';
        return ob_get_clean();
    }

    public static function designer_shortcode() {
        if (!function_exists('wc_get_product')) return '<p>WooCommerce is required.</p>';
        $products = self::designable_products();
        $product_id = isset($_GET['agas_product']) ? absint($_GET['agas_product']) : 0;
        $template_id = isset($_GET['agas_template']) ? absint($_GET['agas_template']) : 0;
        $editing_template = isset($_GET['agas_edit_template']) ? absint($_GET['agas_edit_template']) : 0;
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
            'cartEndpoint' => esc_url_raw(admin_url('admin-post.php')),
            'cartNonce' => wp_create_nonce('agas_design_cart'),
            'productId' => $product_id,
            'productName' => $products[$product_id],
            'template' => $template,
            'editingTemplate' => $editing_template,
            'loadedDesign' => $loaded_design,
            'templateGalleryUrl' => esc_url_raw(self::gallery_url($product_id)),
            'rules' => self::pricing_rules(),
            'products' => $products,
            'saveTemplateAllowed' => current_user_can('manage_woocommerce'),
            'readonly' => !empty($_GET['agas_readonly']),
        );
        wp_add_inline_script('agas-studio', 'window.AGAS_DS = ' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
        ob_start();
        ?>
        <div id="agas-designer-root" class="agas-designer">
          <header class="agas-topbar">
            <a class="agas-logo" href="<?php echo esc_url(self::gallery_url($product_id)); ?>">AGAS<span> DESIGN STUDIO</span></a>
            <div class="agas-design-context"><strong id="agas-product-name"><?php echo esc_html($products[$product_id]); ?></strong><span class="agas-template-name">Template: <?php echo esc_html($template ? preg_replace('/^.*? - /', '', $template['name']) : 'Blank design'); ?></span></div>
            <nav class="agas-top-actions"><button type="button" id="agas-undo" title="Undo">Undo</button><button type="button" id="agas-redo" title="Redo">Redo</button><button type="button" id="agas-save">Save</button><button type="button" id="agas-preview">Preview</button><a href="<?php echo esc_url(self::gallery_url($product_id)); ?>" id="agas-change-template">Change Template</a></nav>
          </header>
          <div class="agas-main">
            <aside class="agas-tools"><h3>Add to design</h3><div class="agas-panel-section"><label>Text</label><button type="button" id="agas-add-heading">Add Heading</button><button type="button" id="agas-add-body">Add Body</button></div><div class="agas-panel-section"><label>Graphics</label><div class="agas-shape-buttons"><button type="button" id="agas-add-rect">Rectangle</button><button type="button" id="agas-add-circle">Circle</button><button type="button" id="agas-add-star">Star</button></div></div><div class="agas-panel-section agas-object-actions"><label>Arrange selected object</label><div class="agas-command-grid"><button type="button" id="agas-rotate-left" title="Rotate 15 degrees counterclockwise">Rotate -15°</button><button type="button" id="agas-rotate-right" title="Rotate 15 degrees clockwise">Rotate +15°</button><button type="button" id="agas-flip-h">Flip H</button><button type="button" id="agas-flip-v">Flip V</button><button type="button" id="agas-lock">Lock object</button><button type="button" id="agas-center">Center object</button></div><label class="agas-opacity-control">Opacity <input type="range" id="agas-opacity" min="0" max="100" step="5" value="100"></label><p class="agas-shortcut-tip">⌘/Ctrl+C copy · V paste · D duplicate · X cut · Delete remove. Hold Shift to select multiple items.</p></div><div class="agas-panel-section"><label for="agas-bg">Background color</label><input id="agas-bg" type="color" value="#ffffff"></div><div class="agas-panel-section agas-logo-upload-actions"><label>Images &amp; logo slots</label><button type="button" id="agas-add-logo-slot">Add logo placeholder</button><button type="button" id="agas-replace-image" disabled>Upload / replace selected logo</button><p class="agas-hint">Select the dashed logo placeholder to upload. Replacing keeps the slot position and fits your image without stretching it.</p><input id="agas-logo-file" type="file" accept="image/png,image/jpeg" hidden><label for="agas-file">Add another PNG/JPG image</label><input id="agas-file" type="file" accept="image/png,image/jpeg"></div><p class="agas-hint">Drag objects to position them. Double-click text to edit. Select an object to use Copy, Paste, Duplicate, Rotate, Flip, Opacity and Lock.</p></aside>
            <section class="agas-workspace"><div class="agas-object-toolbar" id="agas-object-toolbar"><label>Font <select id="agas-font"><option>Arial</option><option>Georgia</option><option>Verdana</option><option>Times New Roman</option><option>Courier New</option></select></label><label>Size <input type="number" id="agas-font-size" min="8" max="160" value="32"></label><label>Color <input type="color" id="agas-color" value="#ffffff"></label><button type="button" id="agas-bold">Bold</button><button type="button" id="agas-copy" title="Copy selected object (Cmd/Ctrl+C)">Copy</button><button type="button" id="agas-paste" title="Paste copied object (Cmd/Ctrl+V)">Paste</button><button type="button" id="agas-duplicate" title="Duplicate selected object (Cmd/Ctrl+D)">Duplicate</button><button type="button" id="agas-cut" title="Cut selected object (Cmd/Ctrl+X)">Cut</button><button type="button" id="agas-delete">Delete</button><button type="button" id="agas-front">Bring forward</button><button type="button" id="agas-backward">Send backward</button></div><div class="agas-canvas-area"><div class="agas-ruler" id="agas-ruler">0 &nbsp; | &nbsp; 1 &nbsp; | &nbsp; 2 &nbsp; | &nbsp; 3 ft</div><div class="agas-canvas-viewport" id="agas-canvas-viewport"><div class="agas-canvas-shell" id="agas-canvas-shell"><canvas id="agas-canvas" width="900" height="600"></canvas><div class="agas-safety" id="agas-safety"></div></div></div><div id="agas-status" role="status" aria-live="polite">Ready to design</div></div></section>
            <aside class="agas-config"><h3>Product setup</h3><label>Size <select id="agas-size"><option value="3x2">3 x 2 ft</option><option value="4x2">4 x 2 ft</option><option value="6x3">6 x 3 ft</option><option value="8x4">8 x 4 ft</option><option value="custom">Custom size</option></select></label><div class="agas-two"><label>Width <input type="number" id="agas-width" min="0.25" max="20" step="0.25" value="3"></label><label>Height <input type="number" id="agas-height" min="0.25" max="20" step="0.25" value="2"></label></div><label>Unit <select id="agas-unit"><option value="ft">Feet</option><option value="in">Inches</option><option value="cm">Centimeters</option></select></label><label class="agas-check"><input id="agas-two-sided" type="checkbox"> Add back side</label><label class="agas-check"><input id="agas-grommets" type="checkbox"> Grommets</label><label class="agas-check"><input id="agas-pocket" type="checkbox"> Pole pocket</label><label>Design side <div class="agas-side-switch"><button type="button" class="active" id="agas-side-front">Front</button><button type="button" id="agas-side-back" disabled>Back</button></div></label><p class="agas-hint">Pricing is recalculated on the server at checkout.</p></aside>
          </div>
          <footer class="agas-bottombar"><label>Zoom <input id="agas-zoom" type="range" min="50" max="125" value="100"><span id="agas-zoom-out">100%</span></label><label class="agas-check"><input type="checkbox" id="agas-guides" checked> Safety guides</label><a href="mailto:support@example.com?subject=AGAS%20Design%20Studio%20Help" title="Demo help address">Need Help?</a><span class="agas-price">Price <strong id="agas-price">Calculating...</strong></span><button type="button" class="agas-primary" id="agas-continue"><?php echo $editing_template ? 'Save Template' : 'Continue to Cart'; ?></button></footer>
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
        $id = wp_insert_post(array('ID' => $existing_id, 'post_type' => 'agas_template', 'post_title' => $title, 'post_status' => 'publish'), true);
        if (is_wp_error($id)) return $id;
        update_post_meta($id, '_agas_canvas', wp_json_encode($data['front']));
        delete_post_meta($id, '_agas_legacy_hidden');
        $products = isset($data['product_ids']) && is_array($data['product_ids']) ? array_map('absint', $data['product_ids']) : array($product_id);
        $products = array_values(array_intersect(array_unique($products), array_keys(self::designable_products())));
        if (!in_array($product_id, $products, true)) $products[] = $product_id;
        update_post_meta($id, '_agas_products', $products);
        $category = sanitize_text_field($data['category'] ?? 'Business');
        wp_set_object_terms($id, $category, 'agas_template_category');
        $thumbnail = self::save_preview($data['preview'] ?? '', $id);
        if ($thumbnail) update_post_meta($id, '_agas_thumb_url', esc_url_raw($thumbnail));
        return rest_ensure_response(array('id' => $id, 'template_url' => self::editor_url($product_id, $id, array('agas_edit_template' => $id))));
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
        if (!WC()->cart) wc_load_cart();
        $key = WC()->cart->add_to_cart($product_id, 1, 0, array(), array('agas_design_id' => $id, 'agas_design_token' => $token, 'agas_options' => $opts, 'agas_unique' => wp_generate_uuid4()));
        if (!$key) wp_die('Could not add the customized product to the cart.', '', array('response' => 400));
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }

    public static function apply_cart_prices($cart) {
        if (!is_a($cart, 'WC_Cart')) return;
        foreach ($cart->get_cart() as $item) {
            if (empty($item['agas_design_id']) || !isset($item['agas_options'])) continue;
            $opts = self::clean_options($item['agas_options']);
            if (is_wp_error($opts)) continue;
            $item['data']->set_price(self::calculate_price($opts));
        }
    }

    public static function cart_item_details($data, $item) {
        if (empty($item['agas_design_id']) || empty($item['agas_options'])) return $data;
        $opts = $item['agas_options'];
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
        if (empty($values['agas_design_id']) || empty($values['agas_options'])) return;
        $id = absint($values['agas_design_id']);
        $opts = self::clean_options($values['agas_options']);
        if (is_wp_error($opts)) return;
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
        foreach (array('_agas_products','_agas_canvas','_agas_thumb_color','_agas_thumb_url','_agas_seed_preview') as $key) update_post_meta($copy, $key, get_post_meta($id, $key, true));
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
