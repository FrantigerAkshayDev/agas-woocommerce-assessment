<?php
/** WooCommerce-native catalog and product content, with a consistent AGAS shell. */
get_header();
$agas_single = function_exists('is_product') && is_product();
?>
<main id="site-content" class="pw-store-main <?php echo $agas_single ? 'pw-store-single' : 'pw-store-archive'; ?>">
  <?php if (!$agas_single) : ?>
  <section class="pw-store-heading"><div class="pw-container"><div class="pw-eyebrow">THE AGAS COLLECTION</div><h1>Pick your <em>canvas.</em></h1><p>Three customizable print products. One place to make them yours.</p></div></section>
  <?php endif; ?>
  <div class="pw-container pw-woocommerce-shell">
    <?php woocommerce_content(); ?>
  </div>
  <?php if ($agas_single) : ?>
  <aside class="pw-pdp-explainer"><div class="pw-container pw-pdp-explainer-inner"><div class="pw-eyebrow">THE PROCESS</div><h2>Make it yours<br><em>in three steps.</em></h2><div><span>01 / Choose a template</span><span>02 / Personalize your artwork</span><span>03 / Review &amp; checkout</span></div><a class="pw-text-link" href="<?php echo esc_url(agas_pw_design_url()); ?>">Explore all design templates <?php echo agas_pw_icon('arrow-up', 18); ?></a></div></aside>
  <?php endif; ?>
</main>
<?php get_footer(); ?>
