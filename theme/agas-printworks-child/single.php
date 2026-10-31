<?php get_header(); ?>
<main class="pw-internal-main" id="site-content"><div class="pw-container pw-page-content pw-article">
<?php while (have_posts()) : the_post(); ?><article <?php post_class(); ?>><div class="pw-eyebrow">AGAS PRINT STUDIO</div><h1><?php the_title(); ?></h1><div class="pw-article-content"><?php the_content(); ?></div></article><?php endwhile; ?></div></main>
<?php get_footer(); ?>
