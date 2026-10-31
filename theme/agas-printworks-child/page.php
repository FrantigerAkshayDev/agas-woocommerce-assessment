<?php get_header(); ?>
<main id="site-content" class="pw-internal-main">
  <?php while (have_posts()) : the_post();
    $cart = function_exists('is_cart') && is_cart();
    $checkout = function_exists('is_checkout') && is_checkout();
    $account = function_exists('is_account_page') && is_account_page();
    $heading = $cart ? 'Your cart.' : ($checkout ? 'Almost there.' : ($account ? 'Your account.' : get_the_title()));
    $description = $cart ? 'Everything you created, all in one place.' : ($checkout ? 'Review your order details and finish checkout.' : ($account ? 'Your orders, details and saved designs.' : ''));
  ?>
    <section class="pw-page-heading"><div class="pw-container"><div class="pw-eyebrow"><?php echo esc_html($cart ? 'YOUR CREATIONS' : ($checkout ? 'CHECKOUT' : ($account ? 'MY AGAS' : 'AGAS PRINT STUDIO'))); ?></div><h1><?php echo esc_html($heading); ?></h1><?php if ($description) : ?><p><?php echo esc_html($description); ?></p><?php endif; ?></div></section>
    <div class="pw-container pw-page-content <?php echo $cart ? 'pw-cart-page' : ($checkout ? 'pw-checkout-page' : ''); ?>">
      <?php the_content(); ?>
      <?php wp_link_pages(); ?>
    </div>
  <?php endwhile; ?>
</main>
<?php get_footer(); ?>
