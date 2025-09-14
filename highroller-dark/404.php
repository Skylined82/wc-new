<?php
// /wp-content/themes/highroller-dark/404.php  (child theme if you use one)
if ( ! defined('ABSPATH') ) exit;
get_header();
?>

<main id="primary" class="site-main readable">
  <article class="card">
    <h1 class="page-title">Page not found</h1>
    <p>We couldn’t find that page. It may have moved or never existed.</p>
  </article>

  <section class="card">
    <h2 class="page-title" style="margin-top:0">Latest news</h2>
    <?php echo do_shortcode('[wc_news_list count="4"]'); ?>
  </section>

  <section class="card">
    <h2 class="page-title" style="margin-top:0">Popular picks</h2>
    <?php echo do_shortcode('[wc_best_casinos count="5" loadmore="0"]'); ?>
  </section>
</main>

<?php get_footer(); ?>
