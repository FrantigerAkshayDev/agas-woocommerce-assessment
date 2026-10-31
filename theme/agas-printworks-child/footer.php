<?php defined('ABSPATH') || exit; ?>
<footer class="pw-footer">
  <div class="pw-container">
    <div class="pw-footer-top">
      <div class="pw-footer-lead">
        <a class="pw-brand pw-brand-light" href="<?php echo esc_url(home_url('/')); ?>"><span class="pw-brand-symbol" aria-hidden="true"><i></i><i></i><i></i></span><span class="pw-brand-text">AGAS<span>PRINT STUDIO</span></span></a>
        <p>Big ideas look better out in the world. Design something that feels like you.</p>
        <a class="pw-footer-cta" href="<?php echo esc_url(agas_pw_design_url()); ?>">Start creating <?php echo agas_pw_icon('arrow-up', 17); ?></a>
      </div>
      <div class="pw-footer-links"><h3>Explore</h3><a href="<?php echo esc_url(agas_pw_shop_url()); ?>">All products</a><a href="<?php echo esc_url(agas_pw_design_url()); ?>">Design templates</a><a href="<?php echo esc_url(home_url('/#how-it-works')); ?>">How it works</a><a href="<?php echo esc_url(home_url('/#faq')); ?>">FAQs</a></div>
      <div class="pw-footer-links"><h3>Your order</h3><a href="<?php echo esc_url(agas_pw_cart_url()); ?>">Shopping cart</a><a href="<?php echo esc_url(agas_pw_account_url()); ?>">My account</a><?php if (function_exists('wc_get_page_permalink')) : ?><a href="<?php echo esc_url(wc_get_page_permalink('checkout')); ?>">Checkout</a><?php endif; ?></div>
      <div class="pw-footer-links"><h3>Products</h3><?php foreach (agas_pw_catalog() as $key => $info) : $product = agas_pw_products()[$key] ?? null; ?><a href="<?php echo esc_url($product ? get_permalink($product->get_id()) : agas_pw_design_url()); ?>"><?php echo esc_html($info['name']); ?></a><?php endforeach; ?></div>
    </div>
    <div class="pw-footer-bottom"><span>© <?php echo esc_html(date_i18n('Y')); ?> AGAS Print Studio. Built on WordPress &amp; WooCommerce.</span><span>Design something worth noticing. <span aria-hidden="true">✳</span></span></div>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
