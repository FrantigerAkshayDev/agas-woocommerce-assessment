<?php get_header(); ?>
<main class="pw-internal-main" id="site-content"><div class="pw-page-heading"><div class="pw-container"><div class="pw-eyebrow">AGAS PRINT STUDIO</div><h1><?php echo is_home() ? esc_html__('Updates & ideas', 'agas-printworks') : esc_html(get_the_archive_title()); ?></h1></div></div><div class="pw-container pw-page-content pw-posts">
<?php if (have_posts()) : while (have_posts()) : the_post(); ?><article <?php post_class('pw-post-card'); ?>><a href="<?php the_permalink(); ?>"><h2><?php the_title(); ?></h2></a><div><?php the_excerpt(); ?></div></article><?php endwhile; the_posts_pagination(); else : ?><p>No content here yet. <a href="<?php echo esc_url(agas_pw_shop_url()); ?>">Explore our products.</a></p><?php endif; ?></div></main>
<?php get_footer(); ?>
