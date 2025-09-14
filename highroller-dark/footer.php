<?php if ( ! defined('ABSPATH') ) exit; ?>


</main>





<?php // SEO footer block (widget with fallback) ?>


<?php if ( is_active_sidebar('seo-footer') ) : ?>
  <div class="container">
    <section class="seo-card">
      <?php dynamic_sidebar('seo-footer'); ?>
    </section>
  </div>
<?php endif; ?>






<footer>


  <div class="container inner">&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?></div>


</footer>


<?php wp_footer(); ?>


</body></html>


