<?php get_header(); ?>


<div class="card hl-shell">


  <div class="hl-head">


    <h2>Highlights</h2>


    <div class="hl-nav">


      <button class="hl-prev" aria-label="Previous">‹</button>


      <button class="hl-next" aria-label="Next">›</button>


    </div>


  </div>


  <div class="hl-track">


    <?php echo do_shortcode('[wc_highlights]'); ?>


  </div>


</div>


<article class="card">

<div class="entry"><?php the_content(); ?></div>


</article>
<?php if ( is_active_sidebar('below-casinos') ) : ?>
  <section class="seo-card">
    <?php dynamic_sidebar('below-casinos'); ?>
  </section>
<?php endif; ?>

<?php get_footer(); ?>


