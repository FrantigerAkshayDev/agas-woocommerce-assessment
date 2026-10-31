<?php get_header(); ?>
<main id="site-content">
  <section class="pw-hero" aria-labelledby="pw-hero-title">
    <div class="pw-container pw-hero-inner">
      <div class="pw-hero-copy">
        <div class="pw-kicker"><span class="pw-kicker-line"></span> YOUR IDEAS, PRINTED BIG</div>
        <h1 id="pw-hero-title">Make it.<br><span>Make it</span><br><em>unmissable.</em></h1>
        <p>Custom banners, flags and signs that speak your language. Choose a design, make it yours and see your price before checkout.</p>
        <div class="pw-hero-actions"><a class="pw-btn pw-btn-dark" href="<?php echo esc_url(agas_pw_shop_url()); ?>">Explore products <?php echo agas_pw_icon('arrow-up', 18); ?></a><a class="pw-btn pw-btn-quiet" href="<?php echo esc_url(agas_pw_design_url()); ?>">Browse templates <?php echo agas_pw_icon('arrow', 18); ?></a></div>
        <div class="pw-hero-proof"><span class="pw-proof-icon"><?php echo agas_pw_icon('spark', 17); ?></span><span>No design skills needed <b>·</b> Personalize online <b>·</b> See live pricing</span></div>
      </div>
      <div class="pw-hero-art" aria-label="Illustrations of a custom banner, flag and yard sign">
        <img src="<?php echo esc_url(agas_pw_theme_image('hero.svg')); ?>" alt="Custom printed banner, flag and yard sign arranged together" width="740" height="660" fetchpriority="high">
        <div class="pw-hero-art-caption"><span class="pw-caption-dot"></span> DESIGNED BY YOU. MADE TO STAND OUT.</div>
      </div>
    </div>
    <div class="pw-hero-footnote pw-container"><span>PERSONALIZE YOUR PRINT</span><span class="pw-hero-scroll">SCROLL TO EXPLORE <span aria-hidden="true">↓</span></span><span>01 — 03</span></div>
  </section>
  <div class="pw-benefit-band" aria-label="How the online design service works"><div class="pw-container"><span><?php echo agas_pw_icon('check', 19); ?> Start from ready-made templates</span><span><?php echo agas_pw_icon('check', 19); ?> Edit text, graphics &amp; colors</span><span><?php echo agas_pw_icon('check', 19); ?> Front &amp; back options</span><span><?php echo agas_pw_icon('check', 19); ?> Live price before checkout</span></div></div>
  <section class="pw-products-section pw-section" id="products" aria-labelledby="pw-products-title">
    <div class="pw-container">
      <div class="pw-section-heading"><div><div class="pw-eyebrow">THE GOOD STUFF <span aria-hidden="true">✳</span></div><h2 id="pw-products-title">Your message.<br><em>Your medium.</em></h2></div><div class="pw-section-side"><p>Three ways to turn your ideas into something people actually notice.</p><a class="pw-text-link" href="<?php echo esc_url(agas_pw_shop_url()); ?>">Shop all products <?php echo agas_pw_icon('arrow-up', 18); ?></a></div></div>
      <div class="pw-product-grid">
        <?php foreach (agas_pw_catalog() as $key => $info) : $product = agas_pw_products()[$key] ?? null; ?>
        <article class="pw-product-card pw-tone-<?php echo esc_attr($info['tone']); ?>">
          <a href="<?php echo esc_url($product ? get_permalink($product->get_id()) : agas_pw_design_url()); ?>" class="pw-product-visual" aria-label="<?php echo esc_attr('Learn more about ' . $info['name']); ?>">
            <span class="pw-product-number"><?php echo esc_html($info['num']); ?> / 03</span>
            <img src="<?php echo esc_url(agas_pw_theme_image($info['image'])); ?>" alt="<?php echo esc_attr($info['name'] . ' product illustration'); ?>" width="580" height="450" loading="lazy">
            <span class="pw-product-visual-arrow" aria-hidden="true"><?php echo agas_pw_icon('arrow-up', 25); ?></span>
          </a>
          <div class="pw-product-info"><div class="pw-product-eyebrow"><?php echo esc_html($info['eyebrow']); ?></div><h3><?php echo esc_html($info['name']); ?></h3><p><?php echo esc_html($info['description']); ?></p><a class="pw-product-design" href="<?php echo esc_url(agas_pw_design_url($product)); ?>">Customize this product <?php echo agas_pw_icon('arrow', 20); ?></a></div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <section class="pw-process pw-section" id="how-it-works" aria-labelledby="pw-process-title">
    <div class="pw-container">
      <div class="pw-process-intro"><div class="pw-eyebrow pw-eyebrow-light">EASY DOES IT</div><h2 id="pw-process-title">From blank canvas<br>to <em>bold statement.</em></h2><p>No complicated design software. Just a few simple steps from idea to cart.</p><a class="pw-btn pw-btn-light" href="<?php echo esc_url(agas_pw_design_url()); ?>">Explore templates <?php echo agas_pw_icon('arrow-up', 18); ?></a></div>
      <div class="pw-process-steps">
        <article><div class="pw-process-step-n">01 <span><?php echo agas_pw_icon('layers', 24); ?></span></div><h3>Pick a starting point</h3><p>Select your product, explore layouts or start with a blank design.</p></article>
        <article><div class="pw-process-step-n">02 <span><?php echo agas_pw_icon('edit', 24); ?></span></div><h3>Make it yours</h3><p>Change text, add your artwork, choose dimensions and customize both sides.</p></article>
        <article><div class="pw-process-step-n">03 <span><?php echo agas_pw_icon('bag', 24); ?></span></div><h3>Review &amp; order</h3><p>See your calculated price, review the cart and finish checkout.</p></article>
      </div>
    </div>
  </section>
  <section class="pw-inspiration pw-section" id="inspiration" aria-labelledby="pw-inspiration-title"><div class="pw-container">
    <div class="pw-section-heading"><div><div class="pw-eyebrow">A LITTLE INSPIRATION</div><h2 id="pw-inspiration-title">Your next idea<br><em>starts here.</em></h2></div><div class="pw-section-side"><p>Start with one of our editable designs. Make every color, word and detail your own.</p><a class="pw-text-link" href="<?php echo esc_url(agas_pw_design_url()); ?>">View all templates <?php echo agas_pw_icon('arrow-up', 18); ?></a></div></div>
    <div class="pw-inspiration-grid">
      <?php $examples = array(
        array('file' => 'banner-grand-opening.png', 'name' => 'Grand Opening', 'cat' => 'OUTDOOR BANNER', 'key' => 'outdoor vinyl banner'),
        array('file' => 'sign-open-house.png', 'name' => 'Open House', 'cat' => 'YARD SIGN', 'key' => 'yard sign'),
        array('file' => 'flag-event.png', 'name' => 'Event Flag', 'cat' => 'CUSTOM FLAG', 'key' => 'custom flag'),
      ); foreach ($examples as $example) : $product = agas_pw_products()[$example['key']] ?? null; ?>
      <a class="pw-inspiration-card" href="<?php echo esc_url(agas_pw_design_url($product)); ?>"><div class="pw-inspiration-image"><img src="<?php echo esc_url(agas_pw_theme_image($example['file'])); ?>" width="600" height="400" alt="<?php echo esc_attr($example['name'] . ' editable design template'); ?>" loading="lazy"></div><div class="pw-inspiration-meta"><span><?php echo esc_html($example['cat']); ?> <strong><?php echo esc_html($example['name']); ?></strong></span><?php echo agas_pw_icon('arrow-up', 21); ?></div></a>
      <?php endforeach; ?>
    </div>
  </div></section>
  <section class="pw-faq-section pw-section" id="faq" aria-labelledby="pw-faq-title"><div class="pw-container pw-faq-layout"><div><div class="pw-eyebrow">GOOD TO KNOW</div><h2 id="pw-faq-title">A few quick<br><em>answers.</em></h2><p>Questions before you start creating? Here are the basics.</p><a class="pw-text-link" href="<?php echo esc_url(agas_pw_design_url()); ?>">Explore designs <?php echo agas_pw_icon('arrow', 18); ?></a></div><div class="pw-faq-items">
    <details><summary>Can I start with a blank canvas? <span>+</span></summary><p>Yes. Choose a product, then select Start from blank instead of an existing template.</p></details>
    <details><summary>Can I edit text and upload my logo? <span>+</span></summary><p>Yes. Edit text and colors in the designer and add a PNG or JPG image where supported.</p></details>
    <details><summary>Can I design both sides? <span>+</span></summary><p>Yes. Enable Add back side inside the editor. The additional side is reflected in the live price.</p></details>
    <details><summary>When do I see the final price? <span>+</span></summary><p>The design studio calculates price based on dimensions, sides and selected options. WooCommerce validates it again in the cart.</p></details>
    <details><summary>Can I edit a design in my cart? <span>+</span></summary><p>Yes. Use Edit design in your cart to reopen your saved design and choose whether to replace it or add a separate version.</p></details>
  </div></div></section>
  <section class="pw-last-cta"><div class="pw-container pw-last-cta-inner"><div><span>YOUR IDEA LOOKS GOOD FROM HERE</span><h2>Ready to make<br><em>some noise?</em></h2></div><a href="<?php echo esc_url(agas_pw_design_url()); ?>" class="pw-btn pw-btn-white">Start designing <?php echo agas_pw_icon('arrow-up', 19); ?></a></div></section>
</main>
<?php get_footer(); ?>
