<?php
/**
 * Plugin Name: WC-Core
 * Description: Casinos & Slots tools — Highlights, Best/Worst, Reviews (publish toggle), Slot demo lazy iframe with mobile full-screen, /review endpoint for casinos, logo uploads, and uniform dark color tokens.
 * Version: 1.7.3
 * Author: You
 */

if ( ! defined('ABSPATH') ) exit;
define('WCC_VER','2.0.7');

/* Activation */
register_activation_hook(__FILE__, function(){
  wcc_register_types();
  flush_rewrite_rules();
});

/* Utils */
function wcc_get_meta( $id, $key, $default = '' ){
  $v = get_post_meta( $id, $key, true );
  return ( $v !== '' ) ? $v : $default;
}

function wcc_initials( $text ){
  $text = trim( wp_strip_all_tags( $text ) );
  if ( $text === '' ) return 'WC';
  $parts = preg_split('/\s+/', $text);
  $a = isset($parts[0][0]) ? strtoupper($parts[0][0]) : '';
  $b = isset($parts[1][0]) ? strtoupper($parts[1][0]) : ( isset($parts[0][1]) ? strtoupper($parts[0][1]) : '' );
  return $a.$b;
}
function wcc_casino_review_url( $post_id ){
  $pto  = get_post_type_object('wc_casino');
  $base = ($pto && !empty($pto->rewrite['slug'])) ? trim($pto->rewrite['slug'],'/') : 'casinos';
  $slug = get_post_field('post_name', $post_id);
  // strip any "-review" or "-casino-review" suffix from the slug
  $slug = preg_replace('~(?:-casino)?-review$~i', '', $slug);
  return home_url( trailingslashit("$base/$slug") );
}

// Clean "Brand – Casino Review" → "Brand" and remove lingering dashes/spaces.
function wcc_brand_name( $title ){
  // Normalize entities & weird spaces
  $t = wp_strip_all_tags( $title );
  $t = html_entity_decode( $t, ENT_QUOTES, 'UTF-8' );
  // Convert NBSP & other space separators to regular spaces
  $t = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}]/u', ' ', $t);

  // Remove optional separator + "(casino )?review" suffix
  $t = preg_replace('~(?:\s*[\p{Pd}:|]\s*)?(?:casino\s+review|review)\s*$~iu', '', $t);

  // Strip any trailing separators (dash/em/en/colon/pipe) + trailing spaces
  $t = preg_replace('~[\s\p{Z}]*(?:[\p{Pd}:|])+[\s\p{Z}]*$~u', '', $t);

  // Collapse doubles and trim
  $t = preg_replace('~\s{2,}~', ' ', $t);
  return trim($t);
}

function wcc_get_settings(){
  $defaults = array(
    'highlights_posts' => 1,
    'highlights_casinos' => 1,
    'highlights_slots' => 1,
    'highlights_count' => 12,
  );
  $opts = get_option('wcc_settings', array());
  return wp_parse_args($opts, $defaults);
}

/* CPTs */
function wcc_register_types(){
  register_post_type('wc_casino', array(
    'labels' => array('name'=>'Casinos','singular_name'=>'Casino','add_new_item'=>'Add New Casino','edit_item'=>'Edit Casino'),
    'public' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-star-filled',
    'supports' => array('title','editor','thumbnail','excerpt','page-attributes'),
    'has_archive' => 'casinos', // <— explicit archive path
    'rewrite' => array(
      'slug'       => 'casinos',
      'with_front' => false,
      'feeds'      => false,
      'pages'      => true,
    ),
  ));

  register_post_type('wc_slot', array(
    'labels' => array('name'=>'Slots','singular_name'=>'Slot','add_new_item'=>'Add New Slot','edit_item'=>'Edit Slot'),
    'public' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-image-filter',
    'supports' => array('title','editor','thumbnail','page-attributes'),
    'has_archive' => 'slots',   // <— explicit archive path
    'rewrite' => array(
      'slug'       => 'slots',  // singles use /slots/%postname%/
      'with_front' => false,
      'feeds'      => false,
      'pages'      => true,
    ),
  ));
}


add_action('init','wcc_register_types');
// Ensure /slots/ hits the wc_slot archive even if something else meddles with rules.
add_action('init', function () {
  add_rewrite_rule('^slots/?$', 'index.php?post_type=wc_slot', 'top');
  add_rewrite_rule('^casinos/?$', 'index.php?post_type=wc_casino', 'top');
}, 20);

/* ----------- Meta boxes ----------- */
/* Casinos */
function wcc_add_metabox_casino(){ add_meta_box('wcc_casino_box','Casino Details','wcc_render_metabox_casino','wc_casino','normal','high'); }
add_action('add_meta_boxes','wcc_add_metabox_casino');
function wcc_render_metabox_casino( $post ){
  $fields = array(
    '_wcc_affiliate_url' => 'Affiliate URL',
    '_wcc_bonus' => 'Welcome Bonus (text)',
    '_wcc_bonus_code'    => 'Bonus Code (optional)',
    '_wcc_min_deposit'   => 'Minimum Deposit (optional)',
    '_wcc_free_spins' => 'Free Spins (number or "-")',
    '_wcc_highlights' => 'Highlights (one per line)',
    '_wcc_rating' => 'Positioning (1-10)',
    '_wcc_terms' => 'T&C (small print under row)',
    '_wcc_terms_url' => 'T&C URL (optional)',
  );
  $is_new  = get_post_meta($post->ID, '_wcc_is_new', true);
  $quality  = get_post_meta($post->ID, '_wcc_quality', true);
  $featured = get_post_meta($post->ID, '_wcc_featured', true);
  $rev_on   = get_post_meta($post->ID, '_wcc_review_enabled', true);
  $rev      = get_post_meta($post->ID, '_wcc_review_content', true);
  $logo_id  = get_post_meta($post->ID, '_wcc_logo_id', true);
  wp_nonce_field('wcc_save_casino','wcc_nonce');
  echo '<style>.wcc-grid{display:grid;grid-template-columns:1fr 2fr;gap:10px}.wcc-grid label{font-weight:600;margin-top:6px;display:block}.wcc-inline{display:flex;gap:14px;align-items:center;margin-top:6px}.wcc-editor{grid-column:1/-1;margin-top:6px}.wcc-media .thumb{width:64px;height:64px;border-radius:10px;object-fit:cover;border:1px solid #ccd}</style>';
  echo '<div class="wcc-grid">';
  foreach( $fields as $k => $label ){
    $val = get_post_meta($post->ID, $k, true);
    echo '<div><label for="'.$k.'">'.esc_html($label).'</label></div><div>';
    if ( $k === '_wcc_highlights' || $k === '_wcc_terms' )
      echo '<textarea name="'.$k.'" id="'.$k.'" rows="3" style="width:100%;">'.esc_textarea($val).'</textarea>';
    else{
      echo '<input type="text" name="'.$k.'" id="'.$k.'" style="width:100%" value="'.esc_attr($val).'" />';
if ( $k === '_wcc_affiliate_url' ) {
  // Build a clean "/go/<key>/" value for the field
  $key   = get_post_meta($post->ID, '_wcc_go_key', true);
  if ($key === '') {
    // Try to derive key from existing full URL if present
    $existing = function_exists('wcc_go_url') ? wcc_go_url($post->ID) : '';
    $path = $existing ? parse_url($existing, PHP_URL_PATH) : '';
    if ($path && preg_match('~^/go/([^/]+)/?~', $path, $m)) { $key = $m[1]; }
    if ($key === '') { $key = $post->post_name; }
  }
  $path_value = '/go/' . $key . '/';

  echo '</div><div><label for="_wcc_redirector_url">'.esc_html__('Redirector URL','wc-core').'</label></div><div>';
  echo '<input type="text" name="_wcc_redirector_url" id="_wcc_redirector_url" value="'.esc_attr($path_value).'" style="width:100%" />';
  echo '<p class="description">'.esc_html__('Starts with /go/. You can type just the last part (e.g. casino-orca) or paste a full /go/... path — it will be normalized on save.','wc-core').'</p>';
}


    }
    echo '</div>';
  }
echo '<div><label>Quality</label></div><div class="wcc-inline">';
foreach(array('good'=>'Good','bad'=>'Bad') as $val=>$lbl){
  $checked = ($quality===$val || ($val==='good' && $quality==='')) ? 'checked' : '';
  echo '<label><input type="radio" name="_wcc_quality" value="'.$val.'" '.$checked.'> '.$lbl.'</label>';
}
echo '</div>';

  echo '<div><label>Featured (Highlights)</label></div><div class="wcc-inline"><label><input type="checkbox" name="_wcc_featured" value="1" '.checked($featured,'1',false).'> Show in Highlights</label></div>';
  echo '<div><label>Mark as New</label></div><div class="wcc-inline"><label><input type="checkbox" name="_wcc_is_new" value="1" '.checked($is_new,'1',false).'> Show on Home (New)</label></div>';
  // Logo upload
  $logo_img = $logo_id ? wp_get_attachment_image($logo_id,'thumbnail',false,array('class'=>'thumb')) : '';
  echo '<div><label>Logo (square)</label></div><div class="wcc-media">';
  echo '<input type="hidden" name="_wcc_logo_id" id="_wcc_logo_id" value="'.esc_attr($logo_id).'">';
  echo '<div class="preview">'.$logo_img.'</div>';
  echo '<p><button type="button" class="button wcc-upload" data-target="_wcc_logo_id">Select Logo</button> ';
  echo '<button type="button" class="button wcc-remove" data-target="_wcc_logo_id">Remove</button></p></div>';
  // Review editor
  echo '<div class="wcc-editor"><label style="font-weight:600">Review (optional)</label><p><label><input type="checkbox" name="_wcc_review_enabled" value="1" '.checked($rev_on,'1',false).'> Publish review</label></p>';
// In Casino metabox:
wp_editor( $rev, 'wcc_casino_review_content', array(
  'textarea_name' => 'wcc_casino_review_content',
  'textarea_rows' => 12,
  'media_buttons' => true,
  'teeny'         => false,
  'quicktags'     => true,
  'tinymce'       => array(
    'wp_autoresize_on' => true,
    'toolbar1'         => 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo',
    'toolbar2'         => '',
  ),
));
echo '</div></div>';
}
function wcc_save_casino_meta( $post_id ){
  if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
  if ( ! isset($_POST['wcc_nonce']) || ! wp_verify_nonce($_POST['wcc_nonce'], 'wcc_save_casino') ) return;
  if ( ! current_user_can('edit_post', $post_id) ) return;
  foreach( array('_wcc_affiliate_url','_wcc_bonus','_wcc_bonus_code','_wcc_min_deposit','_wcc_free_spins','_wcc_highlights','_wcc_rating','_wcc_terms','_wcc_terms_url') as $k ){
    if ( isset($_POST[$k]) ){
      $val = ($k==='_wcc_highlights'||$k==='_wcc_terms') ? wp_kses_post($_POST[$k]) : sanitize_text_field($_POST[$k]);
      update_post_meta($post_id, $k, $val);
    }
  }
  if ( isset($_POST['_wcc_quality']) ) update_post_meta($post_id,'_wcc_quality', sanitize_text_field($_POST['_wcc_quality']) );
  update_post_meta($post_id,'_wcc_featured', isset($_POST['_wcc_featured']) ? '1' : '' );
  update_post_meta($post_id,'_wcc_is_new', isset($_POST['_wcc_is_new']) ? '1' : '' );
  update_post_meta($post_id,'_wcc_review_enabled', isset($_POST['_wcc_review_enabled']) ? '1' : '' );
  if ( isset($_POST['wcc_casino_review_content']) ) {
    update_post_meta($post_id, '_wcc_review_content', wp_kses_post($_POST['wcc_casino_review_content']) );
    
}

wcc_sync_seo_mirror($post_id);
  if ( isset($_POST['_wcc_logo_id']) ) update_post_meta($post_id,'_wcc_logo_id', intval($_POST['_wcc_logo_id']) );

// Redirector URL -> _wcc_go_key (accepts "casino-orca", "/go/casino-orca/", or full URL)
if ( isset($_POST['_wcc_redirector_url']) ) {
  $inp = trim( wp_unslash( $_POST['_wcc_redirector_url'] ) );
  $key = '';
  if ( $inp !== '' ) {
    // Remove home URL if present
    $home = trailingslashit( home_url() );
    if ( stripos($inp, $home) === 0 ) { $inp = substr($inp, strlen($home)); }
    $inp = ltrim($inp, '/');
    // If it's "go/xxx", keep only last segment; if it's just "xxx", use that
    $parts = explode('/', $inp);
    $key = end($parts);
  }
  $key = sanitize_title( $key );
  if ( $key === '' ) { $key = get_post_field('post_name', $post_id); }
  update_post_meta($post_id, '_wcc_go_key', $key);
} else {
  // Ensure a default exists
  if ( '' === get_post_meta($post_id, '_wcc_go_key', true) ) {
    update_post_meta($post_id, '_wcc_go_key', get_post_field('post_name', $post_id));
  }
}


}
add_action('save_post','wcc_save_casino_meta');

/* Slots */
function wcc_add_metabox_slot(){ add_meta_box('wcc_slot_box','Slot Details & Review','wcc_render_metabox_slot','wc_slot','normal','high'); }
add_action('add_meta_boxes','wcc_add_metabox_slot');
function wcc_render_metabox_slot( $post ){
  $url = get_post_meta($post->ID,'_wcc_slot_url', true);
  $prov = get_post_meta($post->ID,'_wcc_slot_provider', true);
  $featured = get_post_meta($post->ID,'_wcc_featured', true);
  $rev_on   = get_post_meta($post->ID,'_wcc_review_enabled', true);
  $rev      = get_post_meta($post->ID,'_wcc_review_content', true);
  $logo_id  = get_post_meta($post->ID,'_wcc_slot_logo_id', true);
  wp_nonce_field('wcc_save_slot','wcc_slot_nonce');
  echo '<style>.wcc-inline{display:flex;gap:14px;align-items:center;margin-top:6px}.wcc-editor{margin-top:10px}.wcc-media .thumb{width:64px;height:64px;border-radius:10px;object-fit:cover;border:1px solid #ccd}</style>';
  echo '<p><label style="font-weight:600">Demo URL (iframe when loaded)</label><input type="text" name="_wcc_slot_url" style="width:100%" value="'.esc_attr($url).'"></p>';
  echo '<p><label style="font-weight:600">Provider</label><input type="text" name="_wcc_slot_provider" style="width:100%" value="'.esc_attr($prov).'"></p>';
  echo '<p class="wcc-inline"><label><input type="checkbox" name="_wcc_featured" value="1" '.checked($featured,'1',false).'> Featured (Highlights)</label></p>';
  // Logo upload
  $logo_img = $logo_id ? wp_get_attachment_image($logo_id,'thumbnail',false,array('class'=>'thumb')) : '';
  echo '<div class="wcc-media"><label style="font-weight:600;display:block;margin-bottom:4px">Logo (square, optional)</label>';
  echo '<input type="hidden" name="_wcc_slot_logo_id" id="_wcc_slot_logo_id" value="'.esc_attr($logo_id).'">';
  echo '<div class="preview">'.$logo_img.'</div>';
  echo '<p><button type="button" class="button wcc-upload" data-target="_wcc_slot_logo_id">Select Logo</button> ';
  echo '<button type="button" class="button wcc-remove" data-target="_wcc_slot_logo_id">Remove</button></p></div>';
  // Review
  echo '<div class="wcc-editor"><label style="font-weight:600">Review (optional)</label><p><label><input type="checkbox" name="_wcc_review_enabled" value="1" '.checked($rev_on,'1',false).'> Publish review</label></p>';
// In Slot metabox:
wp_editor( $rev, 'wcc_slot_review_content', array(
  'textarea_name' => 'wcc_slot_review_content',
  'textarea_rows' => 12,
  'media_buttons' => true,
  'teeny'         => false,
  'quicktags'     => true,
  'tinymce'       => array(
    'wp_autoresize_on' => true,
    'toolbar1'         => 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo',
    'toolbar2'         => '',
  ),
));
  echo '</div>';
}
function wcc_save_slot_meta( $post_id ){
  if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
  if ( ! isset($_POST['wcc_slot_nonce']) || ! wp_verify_nonce($_POST['wcc_slot_nonce'], 'wcc_save_slot') ) return;
  if ( ! current_user_can('edit_post', $post_id) ) return;
  if ( isset($_POST['_wcc_slot_url']) ) update_post_meta($post_id,'_wcc_slot_url', esc_url_raw($_POST['_wcc_slot_url']) );
  if ( isset($_POST['_wcc_slot_provider']) ) update_post_meta($post_id,'_wcc_slot_provider', sanitize_text_field($_POST['_wcc_slot_provider']) );
  update_post_meta($post_id,'_wcc_featured', isset($_POST['_wcc_featured']) ? '1' : '' );
  update_post_meta($post_id,'_wcc_review_enabled', isset($_POST['_wcc_review_enabled']) ? '1' : '' );
  if ( isset($_POST['wcc_slot_review_content']) ) {
    update_post_meta($post_id, '_wcc_review_content', wp_kses_post($_POST['wcc_slot_review_content']) );
    
}
wcc_sync_seo_mirror($post_id);

  if ( isset($_POST['_wcc_slot_logo_id']) ) update_post_meta($post_id,'_wcc_slot_logo_id', intval($_POST['_wcc_slot_logo_id']) );
}
add_action('save_post','wcc_save_slot_meta');

/* Enqueue tokens + assets */
function wcc_enqueue_tokens(){
  wp_register_style('wcc-tokens', plugins_url('assets/wcc-tokens.css', __FILE__), array(), WCC_VER);
  wp_enqueue_style('wcc-tokens');
}
add_action('wp_enqueue_scripts','wcc_enqueue_tokens', 5);

add_action('admin_enqueue_scripts', function($hook){
  if ( in_array($hook, array('post.php','post-new.php'), true) ){
    wp_enqueue_media();
    wp_enqueue_editor(); // ✅ ensures TinyMCE + Quicktags always load
    wp_enqueue_script('wcc-admin-media', plugin_dir_url(__FILE__).'assets/wc-admin-media.js', array('jquery'), WCC_VER, true);
  }
});
add_action('wp_enqueue_scripts', function(){
  wp_enqueue_style('wcc-core', plugin_dir_url(__FILE__).'assets/wc-core.css', array(), WCC_VER);
  wp_enqueue_script('wcc-core', plugin_dir_url(__FILE__).'assets/wc-core.js', array('jquery'), WCC_VER, true);
  wp_localize_script('wcc-core', 'WCC_Ajax', array(
  'ajaxurl' => admin_url('admin-ajax.php'),
  'nonce'   => wp_create_nonce('wcc-loadmore'),
));
});
/* === Hide page H1 on “Bonuses” and “Best Casinos” (robust, keeps menu label) === */

/* Block themes (FSE): remove the Post Title block entirely */
add_filter('render_block', function ($content, $block) {
  if ( is_admin() ) return $content;
  if ( isset($block['blockName']) && $block['blockName'] === 'core/post-title' && is_page(array('bonuses','best-casinos')) ) {
    return '';
  }
  return $content;
}, 10, 2);

/* Classic themes: blank only the queried page title (the H1) */
add_filter('the_title', function ($title, $post_id) {
  if ( is_admin() ) return $title;
  if ( is_page(array('bonuses','best-casinos')) && get_queried_object_id() === (int) $post_id ) {
    return '';
  }
  return $title;
}, 10, 2);

/* Ensure nav menus still show the correct label for the current page tab */
add_filter('nav_menu_item_title', function ($title, $item, $args, $depth) {
  if ( is_admin() ) return $title;
  if ( is_page(array('bonuses','best-casinos')) && (int) $item->object_id === get_queried_object_id() ) {
    $raw = get_post_field('post_title', $item->object_id); // raw DB title, unaffected by the_title filter
    return $raw !== '' ? $raw : $title;
  }
  return $title;
}, 10, 4);

/* === /end hide page H1 === */



/* ---------- Shortcodes & Rendering ---------- */
function wcc_render_casino_rows($query_args, $opts = array()){
  $defaults=array('post_type'=>'wc_casino'); $args=wp_parse_args($query_args,$defaults);
  // Allow turning the outer wrapper on/off (used by AJAX)
$wrap = true;
if (isset($opts['wrap'])) {
  $wrap = (bool) $opts['wrap'];
}
$q=new WP_Query($args); ob_start(); if ( $wrap ) echo '<div class="wcc-table">';
  if($q->have_posts()){
    while($q->have_posts()){ $q->the_post(); $id=get_the_ID();
      $aff=esc_url(wcc_get_meta($id,'_wcc_affiliate_url',''));
      $bonus=esc_html(wcc_get_meta($id,'_wcc_bonus','-'));
      $min_dep = trim( (string) wcc_get_meta($id, '_wcc_min_deposit', '') );
      $spins=wcc_get_meta($id,'_wcc_free_spins','-'); $spins=$spins===''?'-':esc_html($spins);
      $highs=wcc_get_meta($id,'_wcc_highlights',''); $quality=wcc_get_meta($id,'_wcc_quality','neutral'); $terms=wcc_get_meta($id,'_wcc_terms','');
      $rev_on = wcc_get_meta($id,'_wcc_review_enabled','');
      $logo_id = wcc_get_meta($id,'_wcc_logo_id','');
      echo '<div class="wcc-row'.($quality==='bad'?' is-bad':'').'">';
      $cta = $aff ? wcc_go_url($id) : get_permalink($id);
$rel = $aff ? ' rel="nofollow sponsored noopener" target="_blank"' : '';
echo '<div class="cell brand"><a class="brand-link" href="'.esc_url($cta).'"'.$rel.'>';

// Use the original (non-cropped) image for logos
$img_args = array(
  'class'    => 'logoimg',
  'loading'  => 'lazy',
  'decoding' => 'async',
  // Optional: override WP’s auto “sizes” so it doesn’t prefer tiny variants
  'sizes'    => '(min-width: 900px) 540px, 80vw'
);

if ( $logo_id ) {
  echo wp_get_attachment_image( (int) $logo_id, 'full', false, $img_args );
} elseif ( has_post_thumbnail() ) {
  the_post_thumbnail( 'full', $img_args );
} else {
  echo '<div class="logo">'.esc_html( wcc_initials( get_the_title() ) ).'</div>';
}

echo '<div class="title">'.esc_html( wcc_brand_name( get_the_title() ) ).'</div></a></div>';

$code = trim( (string) wcc_get_meta($id, '_wcc_bonus_code', '') );

echo '<div class="cell bonus">';
echo   '<div class="caption">WELCOME BONUS</div>';
echo   '<div class="value">'.wp_kses_post($bonus).'</div>';
if ( $code !== '' ){
  echo '<div class="sub"><span class="sub-label">Code</span> <span class="wcc-code">'.esc_html($code).'</span></div>';
}
if ( $min_dep !== '' ){
  echo '<div class="sub"><span class="sub-label">Min. deposit</span> <span class="wcc-min-deposit">'.esc_html($min_dep).'</span></div>';
}
echo '</div>';


$spins_out = ($spins === '-' ? '<span class="muted">-</span>' : esc_html($spins));
echo '<div class="cell spins"><div class="caption">FREE SPINS</div><div class="value">'.$spins_out.'</div></div>';



      if($highs!==''){ $c=0; echo '<div class="cell features">'; foreach(preg_split('/\r\n|\r|\n/',$highs) as $ln){ $ln=trim($ln); if($ln!==''){ echo '<div class="dot">•</div><div>'.esc_html($ln).'</div>'; $c++; if($c>=2) break; } } echo '</div>'; } else { echo '<div class="cell"></div>'; }
      echo '<div class="cell actions">';
      if($quality==='bad'){ echo '<a class="btn bad" href="'.esc_url(get_permalink()).'">Read Review</a>'; }
      else if($aff){ echo '<a class="btn cta" rel="nofollow sponsored noopener" target="_blank" href="'.esc_url( function_exists('wcc_go_url') ? wcc_go_url($id) : get_permalink($id) ).'">Get Bonus ›</a>'; }
      else { echo '<a class="btn" href="'.esc_url(get_permalink()).'">Read more</a>'; }
      if($rev_on){ echo '<a class="review-link" href="'.esc_url( wcc_casino_review_url($id) ).'">Read review</a>'; }
      echo '</div>';
      $terms_url = esc_url( wcc_get_meta($id,'_wcc_terms_url','') );
if($terms){
  if($terms_url){
    echo '<div class="terms"><a href="'.$terms_url.'" target="_blank" rel="nofollow noopener">'.$terms.'</a></div>';
  } else {
    echo '<div class="terms">'.wp_kses_post($terms).'</div>';
  }
}

      echo '</div>';
    } wp_reset_postdata();
  } else { echo '<p>No casinos found.</p>'; }
  if ( $wrap ) echo '</div>';
return ob_get_clean();
}

/* Best/Worst/Lists */
add_shortcode('wc_best_casinos', function($a){
  $a = shortcode_atts(array(
    'count'    => 10,  // legacy: if loadmore not used, show up to this many
    'loadmore' => 0,   // set to 1 to enable the button
    'per_page' => 10,  // how many per click when loadmore=1
    'title'    => '',  // NEW: optional H2 above the list
    'intro'    => '',  // NEW: optional intro paragraph under the H2
  ), $a, 'wc_best_casinos');

  $base_args = array(
    'post_type'           => 'wc_casino',
    'post_status'         => 'publish',
    'meta_key'            => '_wcc_rating',
    // STABLE ORDER to prevent duplicates across pages:
    // 1) rating desc, 2) date desc, 3) ID desc
    'orderby'             => array(
      'meta_value_num' => 'DESC',
      'date'           => 'DESC',
      'ID'             => 'DESC',
    ),
    'ignore_sticky_posts' => true,
    // HARD-exclude "bad"
    'meta_query'          => array(
      array('key' => '_wcc_quality', 'value' => 'bad', 'compare' => '!=')
    ),
  );

  // Load-more mode
  if ( intval($a['loadmore']) === 1 ) {
    $args = $base_args;
    $args['paged']          = 1;
    $args['posts_per_page'] = max(1, intval($a['per_page']));

    $q = new WP_Query($args);
    $rows_html = wcc_render_casino_rows($args, array('wrap' => false));

    ob_start(); ?>
      <section class="wcc-section">
        <?php if (trim($a['title']) !== '') : ?>
          <h2 class="wcc-heading" style="margin:0 0 .5rem;"><?php echo esc_html($a['title']); ?></h2>
        <?php endif; ?>
        <?php if (trim($a['intro']) !== '') : ?>
          <p class="wcc-intro" style="margin:0 0 .9rem;opacity:.9;"><?php echo wp_kses_post($a['intro']); ?></p>
        <?php endif; ?>

        <div id="wcc-casino-list"
             class="wcc-table"
             data-query='<?php echo esc_attr( wp_json_encode( $args ) ); ?>'
             data-page="1">
          <?php echo $rows_html; ?>
        </div>

        <?php if ( $q->max_num_pages > 1 ) : ?>
          <button id="wcc-load-more"
                  class="wcc-load-more"
                  type="button"
                  aria-label="Load more casinos">Load more</button>
        <?php endif; ?>
      </section>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
  }

  // Legacy (no load more)
  $args = $base_args;
  $args['posts_per_page'] = intval($a['count']);
  ob_start(); ?>
    <section class="wcc-section">
      <?php if (trim($a['title']) !== '') : ?>
        <h2 class="wcc-heading" style="margin:0 0 .5rem;"><?php echo esc_html($a['title']); ?></h2>
      <?php endif; ?>
      <?php if (trim($a['intro']) !== '') : ?>
        <p class="wcc-intro" style="margin:0 0 .9rem;opacity:.9;"><?php echo wp_kses_post($a['intro']); ?></p>
      <?php endif; ?>
      <?php echo wcc_render_casino_rows($args); ?>
    </section>
  <?php
  return ob_get_clean();
});

add_shortcode('wc_new_casinos', function($a, $content = null){
  $a = shortcode_atts(array(
    'loadmore' => 1,
    'per_page' => 12,
    'title'    => 'Latest Casinos', // H2 above the list
    // Fallback micro-intro if no enclosed content is provided
    'intro'    => 'These are the newest casinos added to our list. Compare welcome bonuses, free spins and key terms at a glance, then read a review before you play.',
  ), $a, 'wc_new_casinos');

  // Prefer enclosed content ([wc_new_casinos]…intro…[/wc_new_casinos]) over the intro attribute
  $intro_html = trim( (string) $content ) !== '' ? $content : $a['intro'];

  $base_args = array(
    'post_type'           => 'wc_casino',
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'meta_query'          => array(
      array('key' => '_wcc_is_new', 'value' => '1'),
    ),
    'orderby'             => array('date'=>'DESC','ID'=>'DESC'),
  );

  // -- Load-more version
  if ( intval($a['loadmore']) === 1 ) {
    $args = $base_args;
    $args['paged']          = 1;
    $args['posts_per_page'] = max(1, intval($a['per_page']));
    $q = new WP_Query($args);
    $rows_html = wcc_render_casino_rows($args, array('wrap' => false));

    ob_start(); ?>
      <section class="wcc-section">
        <?php if (trim($a['title']) !== '') : ?>
          <h2 class="wcc-heading" style="margin:0 0 .5rem;"><?php echo esc_html($a['title']); ?></h2>
        <?php endif; ?>

        <?php if (trim($intro_html) !== '') : ?>
          <p class="wcc-intro" style="margin:0 0 .9rem;opacity:.9;"><?php echo wp_kses_post($intro_html); ?></p>
        <?php endif; ?>

        <div id="wcc-casino-list"
             class="wcc-table"
             data-query='<?php echo esc_attr( wp_json_encode( $args ) ); ?>'
             data-page="1">
          <?php echo $rows_html; ?>
        </div>

        <?php if ( $q->max_num_pages > 1 ) : ?>
          <button id="wcc-load-more" class="wcc-load-more" type="button" aria-label="Load more casinos">Load more</button>
        <?php endif; ?>
      </section>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
  }

  // -- Legacy (no JS)
  $args = $base_args;
  $args['posts_per_page'] = intval($a['per_page']);
  ob_start(); ?>
    <section class="wcc-section">
      <?php if (trim($a['title']) !== '') : ?>
        <h2 class="wcc-heading" style="margin:0 0 .5rem;"><?php echo esc_html($a['title']); ?></h2>
      <?php endif; ?>

      <?php if (trim($intro_html) !== '') : ?>
        <p class="wcc-intro" style="margin:0 0 .9rem;opacity:.9;"><?php echo wp_kses_post($intro_html); ?></p>
      <?php endif; ?>

      <?php echo wcc_render_casino_rows($args); ?>
    </section>
  <?php
  return ob_get_clean();
});

add_shortcode('wc_all_casinos', function($a){
  $a = shortcode_atts(array(
    'loadmore' => 1,
    'per_page' => 10,
    'title'    => '',  // NEW: optional H2 above the list
    'intro'    => '',  // NEW: optional intro paragraph under the H2
  ), $a, 'wc_all_casinos');

$base_args = array(
  'post_type'           => 'wc_casino',
  'post_status'         => 'publish',
  'ignore_sticky_posts' => true,
  'orderby'             => array('date'=>'DESC','ID'=>'DESC'),
  'meta_query'          => array(
    array('key' => '_wcc_quality', 'value' => 'bad', 'compare' => '!=')
  ),
);

  if ( intval($a['loadmore']) === 1 ) {
    $args = $base_args;
    $args['paged']          = 1;
    $args['posts_per_page'] = max(1, intval($a['per_page']));

    $q = new WP_Query($args);
    $rows_html = wcc_render_casino_rows($args, array('wrap' => false));

    ob_start(); ?>
      <section class="wcc-section">
        <?php if (trim($a['title']) !== '') : ?>
          <h2 class="wcc-heading" style="margin:0 0 .5rem;"><?php echo esc_html($a['title']); ?></h2>
        <?php endif; ?>
        <?php if (trim($a['intro']) !== '') : ?>
          <p class="wcc-intro" style="margin:0 0 .9rem;opacity:.9;"><?php echo wp_kses_post($a['intro']); ?></p>
        <?php endif; ?>

        <div id="wcc-casino-list"
             class="wcc-table"
             data-query='<?php echo esc_attr( wp_json_encode( $args ) ); ?>'
             data-page="1">
          <?php echo $rows_html; ?>
        </div>

        <?php if ( $q->max_num_pages > 1 ) : ?>
          <button id="wcc-load-more" class="wcc-load-more" type="button" aria-label="Load more casinos">Load more</button>
        <?php endif; ?>
      </section>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
  }

  $args = $base_args;
  $args['posts_per_page'] = intval($a['per_page']);
  ob_start(); ?>
    <section class="wcc-section">
      <?php if (trim($a['title']) !== '') : ?>
        <h2 class="wcc-heading" style="margin:0 0 .5rem;"><?php echo esc_html($a['title']); ?></h2>
      <?php endif; ?>
      <?php if (trim($a['intro']) !== '') : ?>
        <p class="wcc-intro" style="margin:0 0 .9rem;opacity:.9;"><?php echo wp_kses_post($a['intro']); ?></p>
      <?php endif; ?>
      <?php echo wcc_render_casino_rows($args); ?>
    </section>
  <?php
  return ob_get_clean();
});

add_shortcode('wc_worst_casinos', function($a){
  $a = shortcode_atts(array(
    'loadmore' => 0,
    'per_page' => 20,
  ), $a, 'wc_worst_casinos');

  $base_args = array(
    'post_type'           => 'wc_casino',
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'meta_query'          => array(
      array('key'=>'_wcc_quality','value'=>'bad'),
    ),
    // Tell AJAX not to apply the global "!= bad"
    '__include_bad'       => 1,
    'orderby'             => array('date'=>'DESC','ID'=>'DESC'),
  );

  if ( intval($a['loadmore']) === 1 ) {
    $args = $base_args;
    $args['paged']          = 1;
    $args['posts_per_page'] = max(1, intval($a['per_page']));

    $q = new WP_Query($args);
    $rows_html = wcc_render_casino_rows($args, array('wrap' => false));

    ob_start(); ?>
      <div id="wcc-casino-list"
           class="wcc-table"
           data-query='<?php echo esc_attr( wp_json_encode( $args ) ); ?>'
           data-page="1">
        <?php echo $rows_html; ?>
      </div>
      <?php if ( $q->max_num_pages > 1 ) : ?>
        <button id="wcc-load-more" class="wcc-load-more" type="button" aria-label="Load more casinos">Load more</button>
      <?php endif; ?>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
  }

  $args = $base_args;
  $args['posts_per_page'] = intval($a['per_page']);
  return wcc_render_casino_rows($args);
});

/* Slots grid — minimal load-more + optional search */
add_shortcode('wc_slots_grid', function($a){
  $a = shortcode_atts(array(
    'count'    => 12,   // legacy (no loadmore)
    'columns'  => 3,
    'loadmore' => 0,    // 1 to enable
    'per_page' => 12,   // page size when loadmore=1
    'search'   => 0,    // 1 to show a search box
  ), $a, 'wc_slots_grid');

  $cols = max(1, min(6, intval($a['columns'])));
  $base = array(
    'post_type'           => 'wc_slot',
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    // stable ordering so no dupes between pages
    'orderby' => array('date'=>'DESC','ID'=>'DESC'),
  );

  // Load-more mode: render shell + first page
  if ( intval($a['loadmore']) === 1 ) {
    $args = $base;
    $args['paged']          = 1;
    $args['posts_per_page'] = max(1, intval($a['per_page']));
    $q = new WP_Query($args);

    ob_start(); ?>
      <?php if ( intval($a['search']) === 1 ) : ?>
        <div class="wcc-slots-toolbar">
          <input
  id="wcc-slots-search"
  class="wcc-input"
  type="search"
  placeholder="Search slots or providers…"
  autocomplete="off"
  autocapitalize="off"
  autocorrect="off"
  spellcheck="false"
/>

        </div>
      <?php endif; ?>

      <div id="wcc-slots-grid"
           class="wcc-slots cols-<?php echo esc_attr($cols); ?>"
           data-query='<?php echo esc_attr( wp_json_encode($args) ); ?>'
           data-page="1">
        <?php
        if($q->have_posts()){
          while($q->have_posts()){ $q->the_post(); ?>
            <a class="slot" href="<?php echo esc_url(get_permalink()); ?>">
              <?php if(has_post_thumbnail()) the_post_thumbnail('medium'); else echo '<div class="ph"></div>'; ?>
              <div class="slotname"><?php echo esc_html(get_the_title()); ?></div>
              <?php if($p = wcc_get_meta(get_the_ID(),'_wcc_slot_provider','')) echo '<div class="provider">'.esc_html($p).'</div>'; ?>
            </a>
          <?php }
          wp_reset_postdata();
        } else { echo '<p>No slots yet.</p>'; } ?>
      </div>

      <?php if ( $q->max_num_pages > 1 ) : ?>
        <button id="wcc-slots-load-more" class="wcc-load-more" type="button">Load more</button>
      <?php endif; ?>
    <?php
    return ob_get_clean();
  }

  // Legacy (no JS)
  $args = $base;
  $args['posts_per_page'] = intval($a['count']);
  $q = new WP_Query($args);
  ob_start(); echo '<div class="wcc-slots cols-'.esc_attr($cols).'">';
  if($q->have_posts()){ while($q->have_posts()){ $q->the_post(); ?>
    <a class="slot" href="<?php echo esc_url(get_permalink()); ?>">
      <?php if(has_post_thumbnail()) the_post_thumbnail('medium'); else echo '<div class="ph"></div>'; ?>
      <div class="slotname"><?php echo esc_html(get_the_title()); ?></div>
      <?php if($p = wcc_get_meta(get_the_ID(),'_wcc_slot_provider','')) echo '<div class="provider">'.esc_html($p).'</div>'; ?>
    </a>
  <?php } wp_reset_postdata(); } else { echo '<p>No slots yet.</p>'; }
  echo '</div>'; return ob_get_clean();
});


/* News */
add_shortcode('wc_news_list', function($a){
  $a=shortcode_atts(array('count'=>6),$a,'wc_news_list');
  $cat=get_category_by_slug('news'); $cat_id=$cat?$cat->term_id:0;
  $q=new WP_Query(array('post_type'=>'post','posts_per_page'=>intval($a['count']),'cat'=>$cat_id,'orderby'=>'date','order'=>'DESC'));
  ob_start(); echo '<div class="wcc-news">';
  if($q->have_posts()){
    while($q->have_posts()){ $q->the_post();
      echo '<article class="news-card"><a href="'.esc_url(get_permalink()).'">';
      if(has_post_thumbnail()) the_post_thumbnail('medium'); else echo '<div class="ph"></div>';
      echo '<h3>'.esc_html(get_the_title()).'</h3><div class="meta">'.esc_html(get_the_date()).'</div></a></article>';
    } wp_reset_postdata();
  } else { echo '<p>No news yet.</p>'; }
  echo '</div>'; return ob_get_clean();
});

/* Highlights */
add_shortcode('wc_highlights', function(){
  $s=wcc_get_settings(); $pts=array();
  if($s['highlights_posts']) $pts[]='post';
  if($s['highlights_casinos']) $pts[]='wc_casino';
  if($s['highlights_slots']) $pts[]='wc_slot';
  if(empty($pts)) $pts=array('post','wc_casino','wc_slot');
  $q=new WP_Query(array(
    'post_type'=>$pts,'posts_per_page'=>intval($s['highlights_count']),
    'meta_query'=>array('relation'=>'OR', array('key'=>'_wcc_featured','value'=>'1'), array('key'=>'_wcc_featured','compare'=>'NOT EXISTS')),
    'orderby'=>'date','order'=>'DESC'
  ));
  ob_start(); echo '<div class="wcc-highlights">';
  if($q->have_posts()){
    while($q->have_posts()){ $q->the_post(); $pt=get_post_type();
      echo '<a class="hi-card" href="'.esc_url(get_permalink()).'">';
      if(has_post_thumbnail()) the_post_thumbnail('medium'); else echo '<div class="ph"></div>';
      echo '<div class="k">'.($pt==='post'?'News':($pt==='wc_casino'?'Casino':'Slot')).'</div><div class="t">'.esc_html(get_the_title()).'</div></a>';
    } wp_reset_postdata();
  } else { echo '<p>No items.</p>'; }
  echo '</div>'; return ob_get_clean();
});

/* Settings page + utilities */
add_action('admin_menu', function(){ add_options_page('WC-Core','WC-Core','manage_options','wc-core','wcc_settings_page'); });
function wcc_settings_page(){ $opts=wcc_get_settings(); ?>
<div class="wrap"><h1>WC-Core</h1>
<form method="post"><?php wp_nonce_field('wcc_save_settings','wcc_settings_nonce'); ?>
<h2>Highlights</h2>
<p>Control what appears in the Highlights (and the <code>[wc_highlights]</code> shortcode).</p>
<label><input type="checkbox" name="wcc_highlights_posts" value="1" <?php checked($opts['highlights_posts'],1); ?>> News</label><br>
<label><input type="checkbox" name="wcc_highlights_casinos" value="1" <?php checked($opts['highlights_casinos'],1); ?>> Casinos</label><br>
<label><input type="checkbox" name="wcc_highlights_slots" value="1" <?php checked($opts['highlights_slots'],1); ?>> Slots</label><br>
<p><label>Max items: <input type="number" min="3" max="24" name="wcc_highlights_count" value="<?php echo esc_attr($opts['highlights_count']); ?>"></label></p>
<p><button class="button button-primary">Save Settings</button></p>
</form>
<hr><h2>Utilities</h2>
<form method="post" style="display:inline-block;margin-left:8px"><?php wp_nonce_field('wcc_build','wcc_build_nonce'); ?><input type="hidden" name="wcc_build_pages" value="1"><?php submit_button('Create Pages & Menu (incl. Home)','secondary',null,false); ?></form>
<form method="post" style="display:inline-block;margin-left:8px"><?php wp_nonce_field('wcc_front','wcc_front_nonce'); ?><input type="hidden" name="wcc_make_front" value="1"><?php submit_button('Set \"Home\" as Front Page','secondary',null,false); ?></form>
<p>Tip: Set featured images for big cards. Use the Logo upload fields for square logos (tables and badges).</p>
</div><?php }

add_filter('mce_external_plugins', function($plugins){
    // add your own cache-buster
    $ver = WCC_VER . '-' . filemtime( plugin_dir_path(__FILE__) . 'assets/wcc-toc-mce.js' );
    $plugins['wcc_toc_plugin'] = plugin_dir_url(__FILE__) . 'assets/wcc-toc-mce.js?ver=' . rawurlencode($ver);
    return $plugins;
}, 9999);


// Ensure the button appears on our review editors' toolbar
add_filter('tiny_mce_before_init', function($init, $editor_id){
  $targets = ['wcc_casino_review_content','wcc_slot_review_content'];
  if ( ! in_array($editor_id, $targets, true) ) return $init;

  $t1 = isset($init['toolbar1']) ? $init['toolbar1'] : '';
  if ($t1 === '') {
    $t1 = 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo';
  }
  if (strpos($t1, 'wcc_toc') === false) {
    $t1 = (strpos($t1, 'undo,redo') !== false)
      ? str_replace('undo,redo', 'wcc_toc,undo,redo', $t1)
      : ($t1 . ',wcc_toc');
  }
  $init['toolbar1'] = $t1;
  return $init;
}, 10, 2);


add_action('admin_init', function(){
  if( isset($_POST['wcc_settings_nonce']) && current_user_can('manage_options') && wp_verify_nonce($_POST['wcc_settings_nonce'],'wcc_save_settings') ){
    update_option('wcc_settings', array(
      'highlights_posts'=> isset($_POST['wcc_highlights_posts'])?1:0,
      'highlights_casinos'=> isset($_POST['wcc_highlights_casinos'])?1:0,
      'highlights_slots'=> isset($_POST['wcc_highlights_slots'])?1:0,
      'highlights_count'=> max(3, min(24, intval($_POST['wcc_highlights_count']))),
    ));
    add_action('admin_notices', function(){ echo '<div class="notice notice-success"><p>Settings saved.</p></div>'; });
  }
  if( isset($_POST['wcc_build_pages']) && current_user_can('manage_options') && wp_verify_nonce($_POST['wcc_build_nonce'],'wcc_build') ){
    wcc_build_pages_and_menu(); add_action('admin_notices', function(){ echo '<div class="notice notice-success"><p>Pages & menu created.</p></div>'; });
  }
  if( isset($_POST['wcc_make_front']) && current_user_can('manage_options') && wp_verify_nonce($_POST['wcc_front_nonce'],'wcc_front') ){
    wcc_set_home_as_front(); add_action('admin_notices', function(){ echo '<div class="notice notice-success"><p>Home set as front page.</p></div>'; });
  }
});

/* --- Simple TOC builder for review editors --- */
if ( ! function_exists('wcc_render_review_html') ) {
  function wcc_render_review_html( $rev_raw ) {
    $marker = '%%WCC_TOC%%';

    // Keep our tag as a marker so other shortcodes/blocks can run first
    $rev_raw = preg_replace('/\[wcc_toc(?:\s[^\]]*)?\]/i', $marker, $rev_raw);

    // Usual WP processing
    $html = do_blocks( $rev_raw );
    $html = do_shortcode( $html );
    $html = wpautop( $html );

    // Scan H2/H3, add ids, collect items
    $seen  = array();
    $items = array();

$html = preg_replace_callback('#<h([1-6])([^>]*)>(.*?)</h\1>#is', function($m) use (&$seen, &$items){
    $tag   = max(1, min(6, intval($m[1])));  // keep original H1..H6
    $attrs = $m[2];
    $inner = $m[3];

    // Strip <strong>/<b> wrappers (visual only)
    $inner_clean = preg_replace('#</?(strong|b)\b[^>]*>#i', '', $inner);

    // Ensure a stable/unique id
    if ( preg_match('/\sid=("|\')(.*?)\1/i', $attrs, $idm) ) {
        $id = $idm[2];
        $seen[$id] = true;
    } else {
        $base = sanitize_title( wp_strip_all_tags($inner_clean) );
        if ($base === '') { $base = 'section'; }
        $id = $base; $n = 2;
        while ( isset($seen[$id]) ) { $id = $base . '-' . $n; $n++; }
        $seen[$id] = true;
        $attrs .= ' id="' . esc_attr($id) . '"';
    }

    // H1/H2 => level 2, H3+ => level 3 (for TOC nesting only)
    $tocLevel = ($tag >= 3) ? 3 : 2;
    $items[]  = array(
        'level' => $tocLevel,
        'id'    => $id,
        'text'  => wp_strip_all_tags($inner_clean),
    );

    // Re-emit heading without the bold wrapper
    return '<h' . $tag . $attrs . '>' . $inner_clean . '</h' . $tag . '>';
}, $html);



    // Build nested list (H2 > H3)
    $toc_html = '';
    if ( ! empty($items) ) {
      $toc_html .= '<nav class="wcc-toc"><h2>Table of Contents</h2><ul>';
      $level = 2;
      foreach ($items as $it) {
        $l = $it['level'];
        while ($level < $l) { $toc_html .= '<ul>'; $level++; }
        while ($level > $l) { $toc_html .= '</ul>'; $level--; }
        $toc_html .= '<li><a href="#' . esc_attr($it['id']) . '">' . esc_html($it['text']) . '</a></li>';
      }
      while ($level > 2) { $toc_html .= '</ul>'; $level--; }
      $toc_html .= '</ul></nav>';
    }

    // Swap marker for TOC (strip <p> wrappers WP may add)
    $html = str_replace(array('<p>'.$marker.'</p>', '<p> '.$marker.' </p>'), $marker, $html);
    $html = str_replace($marker, $toc_html, $html);

    return $html;
  }

  // If somehow processed early, keep the marker intact
  add_shortcode('wcc_toc', function(){ return '%%WCC_TOC%%'; });
}


/* Content injection */
add_filter('the_content','wcc_inject_content_blocks');
function wcc_inject_content_blocks($content){
  if (is_admin()) return $content;
  global $post; if (!$post) return $content;

  $type = get_post_type($post);

  // CASINO
  if ($type === 'wc_casino') {
    $rev_on = wcc_get_meta($post->ID,'_wcc_review_enabled','');
    $rev    = wcc_get_meta($post->ID,'_wcc_review_content','');

    if ($rev_on && trim(strip_tags($rev)) !== '') {
      $content .= '<section id="casino-review" class="wcc-review">'. wcc_render_review_html($rev) .'</section>';
    }
    return $content;
  }

  // SLOT
  if ($type === 'wc_slot') {
    $url    = wcc_get_meta($post->ID,'_wcc_slot_url','');
    $rev_on = wcc_get_meta($post->ID,'_wcc_review_enabled','');
    $rev    = wcc_get_meta($post->ID,'_wcc_review_content','');

    // Only "Read Review" anchor at top (no Play Demo anchor)
    $anchors = '<nav class="wcc-anchors">';
    if ($rev_on && trim(strip_tags($rev))!=='') $anchors .= '<a class="anchor-btn" href="#slot-review">Read Review</a>';
    $anchors .= '</nav>';

    $demo = '';
    if ($url) {
      $demo = '<section id="slot-demo" class="wcc-demo"><div class="framewrap">'
            . '<iframe data-src="'.esc_url($url).'" src="about:blank" loading="lazy" allowfullscreen></iframe>'
            . '<button type="button" class="demo-overlay" aria-controls="slot-demo"><span>Play Demo</span></button>'
            . '</div><div class="demo-note">Demo is provided by the game vendor and may be geo-restricted.</div></section>';
    }

    $review = '';
    if ($rev_on && trim(strip_tags($rev)) !== '') {
      $review = '<section id="slot-review" class="wcc-review">'. wcc_render_review_html($rev) .'</section>';
    }

    return $anchors.$content.$demo.$review;
  }

  return $content;
}

// === AJAX: Slots load more + search ===
// --- Internal: extend "s" search to also match the _wcc_slot_provider meta (AJAX only) ---

add_action('wp_ajax_wcc_load_more_slots', 'wcc_ajax_load_more_slots');
add_action('wp_ajax_nopriv_wcc_load_more_slots', 'wcc_ajax_load_more_slots');


function wcc_ajax_load_more_slots(){
  if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'wcc-loadmore') ){
    wp_send_json_error(array('message'=>'Bad nonce'));
  }

  // Client sends the CURRENT page (0 on fresh search). We fetch the NEXT one.
  $cur  = isset($_POST['page']) ? intval($_POST['page']) : 1;
  $next = ($cur < 1) ? 1 : ($cur + 1);

  $qraw = isset($_POST['query']) ? wp_unslash($_POST['query']) : '{}';
  $args = json_decode($qraw, true);
  if ( ! is_array($args) ) $args = array();

  // Normalise so paging is stable & consistent with shortcode
  $args['post_type']           = 'wc_slot';
  $args['post_status']         = 'publish';
  $args['ignore_sticky_posts'] = true;
  $args['orderby']             = array('menu_order'=>'ASC','date'=>'DESC','ID'=>'DESC');
  $args['paged']               = $next;

  // Search term (from the input)
  $s = isset($_POST['s']) ? sanitize_text_field( wp_unslash($_POST['s']) ) : '';
  if ( $s !== '' ) {
    $args['s'] = $s; // title/content search first
  }

  // First pass: normal search
  $q = new WP_Query($args);

  // Fallback: if no hits and a term exists, try provider meta LIKE "<term>"
  if ( $s !== '' && ! $q->have_posts() ) {
    unset($args['s']);
    $args['meta_query'] = array(
      array(
        'key'     => '_wcc_slot_provider',
        'value'   => $s,
        'compare' => 'LIKE',
      ),
    );
    $q = new WP_Query($args);
  }

  ob_start();
  if ($q->have_posts()){
    while($q->have_posts()){ $q->the_post(); ?>
      <a class="slot" href="<?php echo esc_url(get_permalink()); ?>">
        <?php if(has_post_thumbnail()) the_post_thumbnail('medium'); else echo '<div class="ph"></div>'; ?>
        <div class="slotname"><?php echo esc_html(get_the_title()); ?></div>
        <?php if($p = wcc_get_meta(get_the_ID(),'_wcc_slot_provider','')) echo '<div class="provider">'.esc_html($p).'</div>'; ?>
      </a>
    <?php }
    wp_reset_postdata();
  }
  $html = ob_get_clean();

  wp_send_json_success(array(
    'html'    => $html,
    'hasMore' => ($q->max_num_pages > $next),
    'next'    => $next,
  ));
}
// === Make Rank Math see TinyMCE review: persist a hidden mirror in post_content on save ===
add_filter('wp_insert_post_data', function ($data, $postarr) {
    $pt = isset($data['post_type']) ? $data['post_type'] : '';
    if ( ! in_array($pt, ['wc_casino','wc_slot'], true) ) return $data;

    // Only act when our metabox fields are actually posted in THIS request
    $has_meta_fields = array_key_exists('_wcc_review_enabled', $_POST)
        || array_key_exists('wcc_casino_review_content', $_POST)
        || array_key_exists('wcc_slot_review_content', $_POST);

    if ( ! $has_meta_fields ) {
        // This is the first Gutenberg pass (content-only). Leave content alone.
        return $data;
    }

    $rev_on = ! empty($_POST['_wcc_review_enabled']);

    $rev = '';
    if ($pt === 'wc_casino' && isset($_POST['wcc_casino_review_content'])) {
        $rev = wp_unslash($_POST['wcc_casino_review_content']);
    } elseif ($pt === 'wc_slot' && isset($_POST['wcc_slot_review_content'])) {
        $rev = wp_unslash($_POST['wcc_slot_review_content']);
    }

    // Remove any old mirror
    $data['post_content'] = preg_replace('~<!--WCC_SEO_MIRROR_START-->.*?<!--WCC_SEO_MIRROR_END-->~is', '', $data['post_content']);

    if ( $rev_on && trim( wp_strip_all_tags( $rev ) ) !== '' ) {
        $review_html = function_exists('wcc_render_review_html') ? wcc_render_review_html( $rev ) : wpautop( $rev );
        $snippet = "<!--WCC_SEO_MIRROR_START-->\n".
                   '<div class="wcc-seo-mirror" aria-hidden="true" style="display:none!important;visibility:hidden!important;opacity:0;max-height:0;overflow:hidden;">'."\n".
                   $review_html."\n".
                   "</div>\n".
                   "<!--WCC_SEO_MIRROR_END-->";
        if ( ! empty($data['post_content']) ) $data['post_content'] .= "\n\n";
        $data['post_content'] .= $snippet;
    }

    return $data;
}, 20, 2);
// === More RankMath Stuff ===
function wcc_sync_seo_mirror($post_id) {
    static $busy = array();
    if ( isset($busy[$post_id]) ) return; // prevent recursion
    $busy[$post_id] = true;

    if ( wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) ) { unset($busy[$post_id]); return; }

    $pt = get_post_type($post_id);
    if ( ! in_array($pt, ['wc_casino','wc_slot'], true) ) { unset($busy[$post_id]); return; }

    $rev_on = (string) get_post_meta($post_id, '_wcc_review_enabled', true ) === '1';
    $rev    = (string) get_post_meta($post_id, '_wcc_review_content', true );

    $content = get_post_field('post_content', $post_id);
    $content = preg_replace('~<!--WCC_SEO_MIRROR_START-->.*?<!--WCC_SEO_MIRROR_END-->~is', '', $content);

    if ( $rev_on && trim( wp_strip_all_tags( $rev ) ) !== '' ) {
        $review_html = function_exists('wcc_render_review_html') ? wcc_render_review_html( $rev ) : wpautop( $rev );
        $snippet = "<!--WCC_SEO_MIRROR_START-->\n".
                   '<div class="wcc-seo-mirror" aria-hidden="true" style="display:none!important;visibility:hidden!important;opacity:0;max-height:0;overflow:hidden;">'."\n".
                   $review_html."\n".
                   "</div>\n".
                   "<!--WCC_SEO_MIRROR_END-->";
        if ( $content !== '' ) $content .= "\n\n";
        $content .= $snippet;
    }

    wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );

    unset($busy[$post_id]);
}



// === AJAX: Load more casinos ===
add_action('wp_ajax_wcc_load_more_casinos', 'wcc_ajax_load_more_casinos');
add_action('wp_ajax_nopriv_wcc_load_more_casinos', 'wcc_ajax_load_more_casinos');

function wcc_ajax_load_more_casinos() {
  // Security
  if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'wcc-loadmore') ) {
    wp_send_json_error(array('message' => 'Bad nonce'));
  }

  $page  = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1; // current page on client
  $raw   = isset($_POST['query']) ? wp_unslash($_POST['query']) : '{}';
  $args  = json_decode($raw, true);
  if ( ! is_array($args) ) $args = array();

  // Always fetch the NEXT page
  $args['paged']       = $page + 1;

    // Normalise args so AJAX matches the shortcode exactly
  $args['post_type']           = 'wc_casino';
  $args['post_status']         = 'publish';
  $args['ignore_sticky_posts'] = true;

if ( empty($args['orderby']) || ! is_array($args['orderby']) ) {
  $args['orderby'] = array(
    'meta_value_num' => 'DESC',
    'date'           => 'DESC',
    'ID'             => 'DESC',
  );
}

// Only set meta_key when we're sorting by rating (meta_value_num)
if ( is_array($args['orderby']) && isset($args['orderby']['meta_value_num']) && empty($args['meta_key']) ){
  $args['meta_key'] = '_wcc_rating';
}

// By default exclude "bad", unless explicitly allowed (e.g. Worst Casinos)
if ( empty($args['__include_bad']) ) {
  if ( empty($args['meta_query']) || ! is_array($args['meta_query']) ) {
    $args['meta_query'] = array();
  }
  $args['meta_query'][] = array(
    'key'     => '_wcc_quality',
    'value'   => 'bad',
    'compare' => '!=',
  );
}

  // Query to know about max pages
  $q = new WP_Query($args);

  // Render rows WITHOUT outer wrapper
  $html = wcc_render_casino_rows($args, array('wrap' => false));

  wp_send_json_success(array(
    'html'     => $html,
    'nextPage' => $page + 1,
    'hasMore'  => ($q->max_num_pages > $args['paged']),
  ));
}

/* Builder helpers */
function wcc_page($title,$slug,$content){
  $ex=get_page_by_path($slug); if($ex) return $ex->ID;
  return wp_insert_post(array('post_title'=>$title,'post_name'=>$slug,'post_type'=>'page','post_status'=>'publish','post_content'=>$content));
}
function wcc_build_pages_and_menu(){
$home  = wcc_page('Home','home', '[wc_new_casinos loadmore="1" per_page="12"]');
$bon   = wcc_page('Bonuses','bonuses','[wc_all_casinos loadmore="1" per_page="10"]');
$best  = wcc_page('Best Casinos','best-casinos','[wc_best_casinos count="10" loadmore="0"]');
$worst = wcc_page('Worst Casinos','worst-casinos','[wc_worst_casinos]');
$slots = wcc_page('Slots','slots-list','[wc_slots_grid loadmore="1" per_page="12" columns="3" search="1"]');
$news  = wcc_page('News','news','[wc_news_list count="6"]');


  $menu = wp_get_nav_menu_object('Main'); $menu_id = $menu ? $menu->term_id : wp_create_nav_menu('Main');
  $items = wp_get_nav_menu_items($menu_id, array('post_status'=>'any')); if($items){ foreach($items as $it) wp_delete_post($it->ID, true); }
  foreach( array(
    array('Home',$home), array('Bonuses',$bon), array('Best Casinos',$best),
    array('Worst Casinos',$worst), array('Slots',$slots), array('News',$news)
  ) as $pair ){
    wp_update_nav_menu_item($menu_id, 0, array(
      'menu-item-title'=>$pair[0], 'menu-item-object'=>'page', 'menu-item-object-id'=>$pair[1],
      'menu-item-type'=>'post_type', 'menu-item-status'=>'publish'
    ));
  }
  set_theme_mod('nav_menu_locations', array('primary'=>$menu_id));
}
function wcc_set_home_as_front(){ $home=get_page_by_path('home'); if($home){ update_option('show_on_front','page'); update_option('page_on_front',$home->ID);} }

/* Always add body class for nav styling to avoid FOUC */
add_filter('body_class', function($classes){ $classes[] = 'wcc-nav'; return $classes; });
require_once __DIR__ . '/inc/go-redirector.php';

/* Rank Math MIRROR */
// Strip the editor-only SEO mirror from front-end output.
add_filter('the_content', function ($content) {
    return preg_replace('~<!--WCC_SEO_MIRROR_START-->.*?<!--WCC_SEO_MIRROR_END-->~is', '', $content);
}, 0);


