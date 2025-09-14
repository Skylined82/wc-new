<?php
if ( ! defined('ABSPATH') ) exit;

// Global base (change via filter)
add_filter('wcc_go_base', function($base){ return 'go'; });

// Key helper (editable per casino via _wcc_go_key)
if ( ! function_exists('wcc_go_key') ) {
  function wcc_go_key( $post ) {
    $post = get_post( $post ); if ( ! $post ) return '';
    $key = get_post_meta($post->ID, '_wcc_go_key', true);
    if ( empty($key) ) $key = $post->post_name;
    return sanitize_title($key);
  }
}
if ( ! function_exists('wcc_go_url') ) {
  function wcc_go_url( $post ) {
    $base = apply_filters('wcc_go_base','go');
    return home_url( user_trailingslashit( $base . '/' . wcc_go_key($post) ) );
  }
}

// Rewrite + query var
add_action('init', function(){
  $base = apply_filters('wcc_go_base','go');
  add_rewrite_tag('%wcc_go%', '([^&]+)');
  add_rewrite_rule('^' . preg_quote($base,'/') . '/([^/]+)/?$', 'index.php?wcc_go=$matches[1]', 'top');
}, 9);
add_filter('query_vars', function($vars){ $vars[]='wcc_go'; return $vars; });

// Redirect
add_action('template_redirect', function(){
  $slug = get_query_var('wcc_go'); if ( ! $slug ) return;
  header('X-Robots-Tag: noindex, nofollow', true);

  // Resolve by custom key, fallback to slug
  $posts = get_posts([
    'post_type'=>'wc_casino','posts_per_page'=>1,'post_status'=>'publish',
    'meta_key'=>'_wcc_go_key','meta_value'=>sanitize_title_for_query($slug),'fields'=>'ids',
    'no_found_rows'=>true,'suppress_filters'=>true
  ]);
  $casino = $posts ? get_post($posts[0]) : get_page_by_path( sanitize_title_for_query($slug), OBJECT, 'wc_casino' );
  if ( ! $casino || 'publish' !== $casino->post_status ) { status_header(404); nocache_headers(); include get_query_template('404'); exit; }

  $target = trim( (string) get_post_meta($casino->ID,'_wcc_affiliate_url', true) );

  // If someone pasted /go/... as the affiliate, avoid loops: treat as unset
  $base = apply_filters('wcc_go_base','go');
  $own  = home_url('/');
  if ( $target && stripos($target, $own) === 0 && strpos($target, '/'.$base.'/') !== false ) { $target = ''; }

  if ( ! $target ) { $target = get_permalink($casino); } // fallback only when truly unset

  if ( ! empty($_GET) ) $target = add_query_arg( array_map('rawurlencode', $_GET), $target );

  $clicks = (int) get_post_meta($casino->ID,'_wcc_clicks', true);
  update_post_meta($casino->ID,'_wcc_clicks', $clicks + 1);
// Allow redirecting to the affiliate host
add_filter('allowed_redirect_hosts', function($hosts) use ($target){
  $h = parse_url($target, PHP_URL_HOST);
  if($h && !in_array($h, $hosts, true)) $hosts[] = $h;
  return $hosts;
});

  wp_safe_redirect( $target, (int) apply_filters('wcc_go_status_code', 302) );
  exit;
}, 0);
