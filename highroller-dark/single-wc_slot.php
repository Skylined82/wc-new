<?php if ( ! defined('ABSPATH') ) exit; get_header(); ?>

<main id="primary" class="site-main">
<?php while ( have_posts() ) : the_post();
  $post_id  = get_the_ID();
  $thumb_id = get_post_thumbnail_id( $post_id ); // your fallback filter supplies this if none set
?>
  <article <?php post_class('slot-single'); ?>>

    <!-- HERO IMAGE + OVERLAID TITLE -->
    <section class="slot-hero card">
      <?php if ( $thumb_id ) : ?>
      <figure class="slot-hero-figure">
        <?php echo wp_get_attachment_image( $thumb_id, 'full', false, array(
          'class'    => 'slot-hero-img',
          'loading'  => 'eager',
          'decoding' => 'async'
        ) ); ?>
      </figure>
      <?php endif; ?>

      <div class="slot-hero-overlay">
        <h1 class="slot-title"><?php the_title(); ?></h1>
      </div>
    </section>

    <?php
    // Render content
    $raw       = get_post_field( 'post_content', $post_id );
    $rendered  = apply_filters( 'the_content', $raw );

    // 1) Remove the first "Read Review" button/link (covers Gutenberg button + generic link styles)
    $clean = preg_replace('/<div class="wp-block-button[^"]*">.*?Read\s*Review.*?<\/div>/is', '', $rendered, 1);
    $clean = preg_replace('/<a[^>]*class="[^"]*(?:btn|button|wp-block-button__link)[^"]*"[^>]*>\s*Read\s*Review\s*<\/a>/is', '', $clean, 1);

    // 2) Extract or synthesize "Where to Play"
    $where_html = '';
    if ( preg_match('/(<h2[^>]*>\s*Where\s*to\s*Play\s*<\/h2>.*?)(?=(<h2\b|<\/(div|section|article|main)>|$))/is', $clean, $m) ) {
      $where_html = $m[1];
      $clean      = str_replace($m[1], '', $clean);
    } else {
      ob_start(); ?>
      <section class="slot-recos">
        <h2><?php echo esc_html__('Where to Play', 'highroller-dark'); ?></h2>
        <?php
        // One random non-bad casino (no shortcode needed)
        echo wcc_render_casino_rows(array(
          'post_type'           => 'wc_casino',
          'post_status'         => 'publish',
          'posts_per_page'      => 1,
          'orderby'             => 'rand',
          'ignore_sticky_posts' => true,
          'meta_query'          => array(
            array('key' => '_wcc_quality', 'value' => 'bad', 'compare' => '!=')
          ),
        ));
        ?>
      </section>
      <?php $where_html = ob_get_clean();
    }

    // 3) Insert Where-to-Play right after the first .wcc-demo section; fallback: append
    if ( $where_html && preg_match('/(<section[^>]*class="[^"]*\bwcc-demo\b[^"]*"[^>]*>.*?<\/section>)/is', $clean) ) {
      $clean = preg_replace(
        '/(<section[^>]*class="[^"]*\bwcc-demo\b[^"]*"[^>]*>.*?<\/section>)/is',
        '$1' . "\n" . $where_html,
        $clean,
        1
      );
    } else {
      $clean .= $where_html;
    }
    ?>

    <div class="entry-content">
      <?php echo $clean; ?>
    </div>

    <!-- Mobile demo modal -->
    <div id="demo-modal" class="demo-modal" hidden aria-hidden="true">
      <div class="demo-modal-backdrop" data-close="1"></div>
      <div class="demo-modal-panel" role="dialog" aria-modal="true" aria-label="Game demo">
        <button type="button" class="demo-modal-close" aria-label="Close">✕</button>
        <div class="demo-modal-framewrap">
          <iframe id="demo-modal-iframe" src="about:blank" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-forms allow-pointer-lock" allow="autoplay; encrypted-media"></iframe>
        </div>
      </div>
    </div>

  </article>
<?php endwhile; ?>
</main>

<script>
(function(){
  const modal  = document.getElementById('demo-modal');
  if (!modal) return;

  const frame  = modal.querySelector('#demo-modal-iframe');
  const closeB = modal.querySelector('.demo-modal-close');
  const backdr = modal.querySelector('.demo-modal-backdrop');

  function getInlineIframe(btn){
    const wrap = btn.closest('.wcc-demo');
    return wrap ? wrap.querySelector('iframe') : null;
  }

  // Keep the inline iframe blank so it doesn't also load or try to break out
  function suppressInline(btn){
    const ifr = getInlineIframe(btn);
    if (!ifr) return { url: null };
    const url = ifr.getAttribute('data-src') || ifr.src || null;

    // Force it blank and mark as suppressed
    try { ifr.removeAttribute('src'); } catch(e){}
    ifr.setAttribute('data-src', url || '');
    ifr.setAttribute('data-suppressed', '1');
    ifr.removeAttribute('allowfullscreen');
    ifr.removeAttribute('allow');
    return { url };
  }

  function openModal(url){
    if (!url) return;
    frame.src = url;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('no-scroll', 'demo-open');
  }

  function closeModal(){
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    frame.src = 'about:blank';                     // unload game
    document.documentElement.classList.remove('no-scroll', 'demo-open');
  }

  // Intercept the click in the *capture* phase and stop other handlers
  function handleClick(e){
    const btn = e.target.closest('.wcc-demo .demo-overlay');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();

    const { url } = suppressInline(btn);
    openModal(url);
  }

  // Capture = true so we run before delegated/bubbling handlers
  document.addEventListener('click', handleClick, true);

  backdr.addEventListener('click', closeModal);
  closeB.addEventListener('click', closeModal);
  window.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
})();
</script>

<?php get_footer(); ?>
