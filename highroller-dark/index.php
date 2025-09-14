<?php get_header(); ?>
<?php if(have_posts()): while(have_posts()): the_post(); ?>
<article class="card">
  <?php if(is_singular()){ echo '<h1 class="page-title">'.get_the_title().'</h1>'; } else { echo '<h2><a href="'.esc_url(get_permalink()).'">'.get_the_title().'</a></h2>'; } ?>
  <div class="entry"><?php the_content(); ?></div>
</article>
<?php endwhile; endif; ?>
<?php get_footer(); ?>
