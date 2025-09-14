<?php get_header(); ?>
<h1 class="page-title"><?php the_archive_title(); ?></h1>
<?php if(have_posts()): ?>
<div class="card">
  <?php while(have_posts()): the_post(); ?>
    <p><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></p>
  <?php endwhile; ?>
</div>
<?php else: ?>
<p class="card">No items.</p>
<?php endif; ?>
<?php get_footer(); ?>
