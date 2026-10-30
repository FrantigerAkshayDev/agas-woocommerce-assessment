<?php
/**
 * AGAS standalone design-studio/gallery frontend.
 * Rendered only for pages created by AGAS Design Studio.
 */
defined('ABSPATH') || exit;
$agas_is_editor = is_page(absint(get_option('agas_ds_editor_page')));
$agas_page_title = $agas_is_editor ? 'Online Design Studio' : 'Choose a Design';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?php echo esc_html($agas_page_title . ' | ' . get_bloginfo('name')); ?></title>
  <?php wp_head(); ?>
</head>
<body <?php body_class($agas_is_editor ? 'agas-ds-app-page' : 'agas-ds-gallery-page'); ?>>
<?php wp_body_open(); ?>
<?php if ($agas_is_editor) : ?>
  <main class="agas-standalone-shell" id="agas-main" aria-label="Online product designer">
    <?php echo do_shortcode('[agas_designer]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  </main>
<?php else : ?>
  <div class="agas-gallery-page-shell">
    <header class="agas-site-header">
      <a class="agas-site-mark" href="<?php echo esc_url(home_url('/')); ?>">AGAS <span>DESIGN STUDIO</span></a>
      <nav aria-label="Store links">
        <a href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/')); ?>">Shop</a>
        <a href="<?php echo esc_url(AGAS_Design_Studio::gallery_url()); ?>" aria-current="page">Templates</a>
        <a href="<?php echo esc_url(wc_get_cart_url()); ?>">Cart</a>
      </nav>
    </header>
    <main id="agas-main">
      <?php echo do_shortcode('[agas_template_gallery]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </main>
  </div>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
