<?php if ( ! defined('ABSPATH') ) exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<?php wp_head(); ?>
<?php /* Inline clamp so it's last in cascade */ ?>
<style>
header.header .brand .custom-logo-link img.custom-logo,
header.header .custom-logo-link img.custom-logo,
header.header .brand img.custom-logo,
.header .brand img.custom-logo{
  height:48px !important;max-height:48px !important;width:auto !important;max-width:100% !important;display:block;
}
@media (max-width: 767px){
  header.header .brand .custom-logo-link img.custom-logo,
  header.header .custom-logo-link img.custom-logo,
  header.header .brand img.custom-logo,
  .header .brand img.custom-logo{height:40px !important;max-height:40px !important}
}
/* hide any content-inserted site logo on the homepage */
.home .entry .wp-block-site-logo,
.home .entry .custom-logo-link,
.home .entry img.custom-logo{display:none !important}
</style>

</head>
<body <?php body_class(); ?>>
<header class="header">
  <div class="container inner">
    <div class="brand">
  <?php if ( function_exists('the_custom_logo') && has_custom_logo() ) { the_custom_logo(); } else { ?>
    <a class="brand-fallback" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php bloginfo('name'); ?>">
      <span class="dot"></span><span class="site-title-text"><?php bloginfo('name'); ?></span>
    </a>
  <?php } ?>
</div>
    <nav class="nav">
      <?php wp_nav_menu(array('theme_location'=>'primary','menu_class'=>'menu','container'=>false,'fallback_cb'=>'highroller_menu_fallback')); ?>
    </nav>
    <button class="hambtn" aria-label="Menu"><span></span></button>
  </div>
</header>

<!-- Mobile drawer (right side) -->
<div class="mobile-drawer" aria-hidden="true">
  <div class="backdrop"></div>
  <div class="panel">
    <div class="drawer-head">
  <div class="brand">
    <?php if ( function_exists('the_custom_logo') && has_custom_logo() ) { the_custom_logo(); } else { ?>
      <a class="brand-fallback" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php bloginfo('name'); ?>">
        <span class="dot"></span><span class="site-title-text"><?php bloginfo('name'); ?></span>
      </a>
    <?php } ?>
  </div>
  <button class="closebtn" aria-label="Close">✕</button>
    </div>
    <?php wp_nav_menu(array('theme_location'=>'primary','menu_class'=>'menu','container'=>false,'fallback_cb'=>'highroller_menu_fallback')); ?>
  </div>
</div>

<main class="container">
