<?php
get_header();
the_post();

$id      = get_the_ID();
$logo_id = (int) get_post_meta($id, '_wcc_logo_id', true);
$aff     = trim( (string) get_post_meta($id, '_wcc_affiliate_url', true) );
$bonus = trim( (string) get_post_meta($id, '_wcc_bonus', true) );
$code  = trim( (string) get_post_meta($id, '_wcc_bonus_code', true) );
$has_meta = ($bonus !== '' || $code !== '');

// Prefer /go/{key} if available, fall back to the raw affiliate URL.
$cta = '';
if ( $aff !== '' ) {
  $cta = function_exists('wcc_go_url') ? wcc_go_url($id) : $aff;
}
?>
<section class="casino-hero cardish">
  <div class="hero-inner container readable">
    <div class="hero-logo-wrap">
      <?php
      if ($logo_id) {
        echo wp_get_attachment_image($logo_id, 'medium', false, ['class' => 'hero-logo', 'alt' => get_the_title()]);
      } elseif (has_post_thumbnail()) {
        the_post_thumbnail('medium', ['class' => 'hero-logo']);
      } else {
        echo '<div class="hero-logo placeholder">'.esc_html( function_exists('wcc_initials') ? wcc_initials(get_the_title()) : substr(get_the_title(),0,2) ).'</div>';
      }
      ?>
    </div>

<div class="hero-copy<?php echo $has_meta ? ' has-meta' : ''; ?>">
  <h1 class="hero-title"><?php echo esc_html( wcc_brand_name(get_the_title()) ); ?></h1>

  <?php if ($has_meta): ?>
    <div class="hero-meta">
      <?php if ($bonus !== ''): ?><span class="bonus"><?php echo esc_html($bonus); ?></span><?php endif; ?>
      <?php if ($code !== ''): ?>
        <span class="code-label">CODE</span>
        <span class="coupon"><?php echo esc_html($code); ?></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>



    <?php if ($cta): ?>
      <div class="hero-cta-wrap">
        <a class="wcc-btn hero-cta"
           href="<?php echo esc_url($cta); ?>"
           target="_blank" rel="nofollow sponsored noopener">
          Get Bonus ›
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>

<main class="container readable">
  <?php the_content(); ?>
</main>

<?php get_footer(); ?>
