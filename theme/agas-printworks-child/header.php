<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="pw-skip" href="#site-content"><?php esc_html_e('Skip to content', 'agas-printworks'); ?></a>
<div class="pw-announcement" role="note"><span class="pw-announcement-dot"></span> Design online. Make it yours. Print with purpose. <span class="pw-announcement-star">✳</span> Your ideas start here.</div>
<header class="pw-header" id="site-header">
  <div class="pw-container pw-header-inner">
    <a class="pw-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="AGAS Print Studio home">
      <span class="pw-brand-symbol" aria-hidden="true"><i></i><i></i><i></i></span>
      <span class="pw-brand-text">AGAS<span>PRINT STUDIO</span></span>
    </a>
    <button type="button" class="pw-mobile-toggle" aria-expanded="false" aria-controls="pw-mobile-nav" aria-label="Open navigation" data-pw-nav-toggle>
      <span class="pw-toggle-open"><?php echo agas_pw_icon('menu', 25); ?></span>
      <span class="pw-toggle-close"><?php echo agas_pw_icon('close', 25); ?></span>
    </button>
    <nav class="pw-nav" id="pw-mobile-nav" aria-label="Primary navigation">
      <a href="<?php echo esc_url(agas_pw_shop_url()); ?>" <?php if (function_exists('is_shop') && (is_shop() || is_product())) echo 'aria-current="page"'; ?>>Shop products</a>
      <a href="<?php echo esc_url(agas_pw_design_url()); ?>">Design templates</a>
      <a href="<?php echo esc_url(home_url('/#how-it-works')); ?>">How it works</a>
      <a href="<?php echo esc_url(home_url('/#faq')); ?>">FAQ</a>
    </nav>
    <div class="pw-header-actions">
      <a href="<?php echo esc_url(agas_pw_account_url()); ?>" class="pw-header-account" aria-label="My account" title="My account"><?php echo agas_pw_icon('user', 21); ?><span>Account</span></a>
      <a href="<?php echo esc_url(agas_pw_cart_url()); ?>" class="pw-cart-button" aria-label="View shopping cart"><?php echo agas_pw_icon('bag', 20); ?><span>Cart</span>
        <?php $count = function_exists('WC') && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0; ?>
        <span class="pw-cart-count" aria-label="<?php echo esc_attr($count . ' items'); ?>"><?php echo esc_html((string) $count); ?></span>
      </a>
    </div>
  </div>
</header>
