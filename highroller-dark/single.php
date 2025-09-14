<?php get_header(); ?>
<article class="card">
  <h1 class="page-title"><?php the_title(); ?></h1>
  <?php if(has_post_thumbnail()) the_post_thumbnail('large', array('style'=>'border-radius:10px;margin-bottom:12px')); ?>
  <div class="entry"><?php the_content(); ?></div>
</article>
<?php get_footer(); ?>
