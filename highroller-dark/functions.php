<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Theme supports
 */
add_theme_support( 'custom-logo', array(
    'height'      => 64,
    'width'       => 240,
    'flex-height' => true,
    'flex-width'  => true,
) );

add_action('after_setup_theme', function () {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails'); // enables thumbnails for all CPTs that declare support
  register_nav_menus(array('primary' => 'Primary Menu'));
});

/**
 * Widget area (SEO footer)
 */
add_action('widgets_init', function () {
  register_sidebar(array(
    'name'          => 'SEO Footer',
    'id'            => 'seo-footer',
    'description'   => 'Content shown beneath main content on all pages (for SEO). Add a Paragraph/HTML block here.',
    'before_widget' => '<div id="%1$s" class="widget %2$s">',
    'after_widget'  => '</div>',
    'before_title'  => '<h3 class="widget-title">',
    'after_title'   => '</h3>',
  ));
});
// Widget area (Below Casino List)
add_action('widgets_init', function () {
  register_sidebar(array(
    'name'          => 'Below Casino List',
    'id'            => 'below-casinos',
    'description'   => 'Shown directly under the Latest Casinos list on the homepage.',
    'before_widget' => '<section id="%1$s" class="seo-card %2$s">',
    'after_widget'  => '</section>',
    'before_title'  => '<h2>',
    'after_title'   => '</h2>',
  ));
});

/**
 * Front-end assets
 */
add_action('wp_enqueue_scripts', function () {
  wp_enqueue_style( 'highroller-dark',   get_template_directory_uri() . '/assets/theme.css',  array(), '1.0.3' );
  wp_enqueue_script('highroller-hl',     get_template_directory_uri() . '/assets/hl.js',      array(), '1.0.1', true );
  wp_enqueue_script('highroller-mobile', get_template_directory_uri() . '/assets/mobile.js',  array(), '1.0.2', true );
});

/**
 * Fallback menu
 */
function highroller_menu_fallback() {
  echo '<ul class="menu"><li><a href="' . esc_url(home_url('/')) . '">Home</a></li></ul>';
}

/**
 * -------- ADMIN FIX FOR WC_CASINO EDITOR --------
 * Dequeue front-end plugin CSS/JS that sometimes bleeds into wp-admin,
 * and pin the metabox layout so it can't overlay the editor.
 */
add_action('admin_enqueue_scripts', function () {
    if ( ! function_exists('get_current_screen') ) return;
    $s = get_current_screen();
    if ( ! $s || $s->base !== 'post' || $s->post_type !== 'wc_casino' ) return;

    // 1) Prevent front-end assets from loading on this editor screen
    $style_handles  = array('wcc-tokens-css','wcc-core-css','zip-ai-sidebar-css');
    $script_handles = array('wcc-core-js','zip-ai-sidebar-js');

    foreach ($style_handles as $h) {
        if ( wp_style_is($h,  'enqueued') ) { wp_dequeue_style($h);  wp_deregister_style($h); }
    }
    foreach ($script_handles as $h) {
        if ( wp_script_is($h, 'enqueued') ) { wp_dequeue_script($h); wp_deregister_script($h); }
    }

    // 2) Safe admin-only layout for our metabox grid + ensure editor layer stays interactive
    wp_register_style('wcc-casino-admin-meta', false, array(), null);
    wp_enqueue_style('wcc-casino-admin-meta');

    $css = <<<CSS
/* Keep the WC Casino metabox behaving like a normal grid in admin */
#wcc_casino_box .inside       { padding-top: 12px; }
#wcc_casino_box .wcc-grid     {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 10px 14px;
  position: static;
  inset: auto;
  width: auto;
  height: auto;
  z-index: auto;
}
#wcc_casino_box .wcc-grid .full { grid-column: 1 / -1; }

/* Make sure the writing flow sits above any metabox */
.block-editor-writing-flow,
.edit-post-layout__content { position: relative; z-index: 2; }

/* If the Zip AI sidebar DOM is present, hide it on this edit screen */
#zip-ai-sidebar,
#zip-ai-sidebar-admin-trigger { display: none; }
CSS;

    wp_add_inline_style('wcc-casino-admin-meta', $css);
}, 99);


/**
 * ================================
 * UNIVERSAL FEATURED-IMAGE FALLBACK
 * ================================
 *
 * Works for:
 *  - wc_casino: uses the Logo (square) if no thumbnail is set
 *  - wc_slot:   tries slot image/logo/banner metas, then any meta that is an image ID, then first content image
 *  - post:      first content image (Media Library) or cover block background
 *
 * It returns an attachment ID so anything calling get_the_post_thumbnail_*() just works.
 */

/** Try to resolve an image attachment ID from post content (image tag or cover background). */
function wcc_first_image_id_from_content( WP_Post $post ): int {
    $html = (string) $post->post_content;
    if ($html === '') return 0;

    // 1) Fast path: Gutenberg/Classic often add wp-image-### on library images.
    if (preg_match('/wp-image-(\d+)/', $html, $m)) {
        $maybe = (int) $m[1];
        if ($maybe && strpos((string) get_post_mime_type($maybe), 'image/') === 0) {
            return $maybe;
        }
    }

    // 2) Generic <img ... src="..."> scan -> map URL back to attachment ID.
    if (preg_match('/<img[^>]+src=["\']([^"\']+\.(?:jpe?g|png|gif|webp))["\']/i', $html, $m)) {
        $id = attachment_url_to_postid( $m[1] );
        if ($id && strpos((string) get_post_mime_type($id), 'image/') === 0) return $id;
    }

    // 3) Cover block / inline style background-image: url("...").
    if (preg_match('/url\(["\']?([^"\')]+\.(?:jpe?g|png|gif|webp))["\']?\)/i', $html, $m)) {
        $id = attachment_url_to_postid( $m[1] );
        if ($id && strpos((string) get_post_mime_type($id), 'image/') === 0) return $id;
    }

    return 0;
}

/** Search post meta for likely image attachment IDs. */
function wcc_guess_image_id_from_meta( int $post_id ): int {
    $all = get_post_meta( $post_id );

    if (empty($all) || !is_array($all)) return 0;

    // 1) Priority keys we expect across installs.
    $priority_keys = array(
        '_wcc_slot_image_id',
        '_wcc_logo_id',
        '_wcc_banner_id',
        'slot_logo_id',
        'logo_id',
        'image_id',
        'thumbnail_id',
        'banner_id',
        'cover_id',
    );

    foreach ($priority_keys as $key) {
        if (!isset($all[$key])) continue;
        $vals = (array) $all[$key];
        foreach ($vals as $v) {
            $id = (int) $v;
            if ($id && strpos((string) get_post_mime_type($id), 'image/') === 0) return $id;
        }
    }

    // 2) Fallback: scan any meta whose key name suggests an image and value is an image attachment ID.
    foreach ($all as $k => $vals) {
        if (!is_array($vals)) $vals = array($vals);
        $k_lc = strtolower((string) $k);
        $looks_like_image_key = (strpos($k_lc, 'logo') !== false) || (strpos($k_lc, 'image') !== false) ||
                                (strpos($k_lc, 'thumb') !== false) || (strpos($k_lc, 'banner') !== false) ||
                                (strpos($k_lc, 'cover') !== false);

        if (!$looks_like_image_key) continue;

        foreach ($vals as $v) {
            $id = (int) $v;
            if ($id && strpos((string) get_post_mime_type($id), 'image/') === 0) return $id;
        }
    }

    return 0;
}

add_filter('get_post_metadata', function ($value, $object_id, $meta_key, $single) {
    // Only when WordPress is asking for the thumbnail id AND no real value exists.
    if ($meta_key !== '_thumbnail_id') return $value;
    if ($value !== null)            return $value;

    $post = get_post($object_id);
    if (!$post) return $value;

    // --- CASINO: prefer the uploaded logo if present ---
    if ($post->post_type === 'wc_casino') {
        $logo_id = (int) get_post_meta($object_id, '_wcc_logo_id', true);
        if ($logo_id) return $single ? $logo_id : array($logo_id);
        // last resort for casino as well: hunt through meta
        $guessed = wcc_guess_image_id_from_meta($object_id);
        if ($guessed) return $single ? $guessed : array($guessed);
        return $value;
    }

    // --- SLOTS & NEWS ---
    if (in_array($post->post_type, array('wc_slot', 'post'), true)) {
        // 1) Try known metas / any meta that looks like an image first
        $meta_img_id = wcc_guess_image_id_from_meta( $object_id );
        if ($meta_img_id) return $single ? $meta_img_id : array($meta_img_id);

        // 2) Try first image in content (works without wp-image class)
        $content_img_id = wcc_first_image_id_from_content( $post );
        if ($content_img_id) return $single ? $content_img_id : array($content_img_id);
    }

    // No fallback found; keep WP's "no thumbnail" behaviour.
    return $value;
}, 10, 4);
/**
 * WC Casino: Minimum deposit (optional)
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'wcc_min_deposit',
        __('Minimum deposit', 'highroller-dark'),
        function ($post) {
            $val = get_post_meta($post->ID, '_wcc_min_deposit', true);
            wp_nonce_field('wcc_min_deposit_save', 'wcc_min_deposit_nonce');
            ?>
            <p class="howto" style="margin-top:0">
                <?php esc_html_e('Optional. Examples: "$10", "€20", "0.001 BTC"', 'highroller-dark'); ?>
            </p>
            <input type="text"
                   id="wcc_min_deposit_input"
                   name="wcc_min_deposit_input"
                   class="widefat"
                   value="<?php echo esc_attr($val); ?>"
                   placeholder="$10 / €10 / 0.001 BTC">
            <?php
        },
        'wc_casino',
        'side',
        'default'
    );
});

add_action('save_post_wc_casino', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['wcc_min_deposit_nonce']) || !wp_verify_nonce($_POST['wcc_min_deposit_nonce'], 'wcc_min_deposit_save')) return;

    $raw = isset($_POST['wcc_min_deposit_input']) ? wp_unslash($_POST['wcc_min_deposit_input']) : '';
    $val = trim($raw);

    if ($val === '') {
        delete_post_meta($post_id, '_wcc_min_deposit');
    } else {
        update_post_meta($post_id, '_wcc_min_deposit', sanitize_text_field($val));
    }
});

/**
 * ==================================
 * WC Casino logo images – strip fixed width/height
 * ==================================
 *
 * Prevents WordPress (or the plugin) from forcing 150×150 on <img class="logoimg">
 * so CSS can control scaling instead.
 */
function highroller_logoimg_no_dims( $attr, $attachment, $size ) {
    if ( isset( $attr['class'] ) && strpos( $attr['class'], 'logoimg' ) !== false ) {
        unset( $attr['width'], $attr['height'] );
    }
    return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'highroller_logoimg_no_dims', 10, 3 );
/* === Rank Math: %wc_auto_desc% — compact, fast, cached (Goldex fixes) === */

if ( ! defined('WC_AUTO_DESC_VERSION') ) {
    define('WC_AUTO_DESC_VERSION', '2025-09-11d'); // bump to invalidate caches
}

/* -------- small helpers -------- */

if ( ! function_exists('wcd_summarize_text') ) {
    function wcd_summarize_text( $text, $limit = 160 ) {
        $text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
        if ( mb_strlen( $text ) <= $limit ) return $text;
        $cut = mb_substr( $text, 0, $limit );
        if ( preg_match('/^(.+?)(?:[.!?])(?=\s+[A-Z(])(?!.*[.!?](?=\s+[A-Z(]))/u', $cut, $m) ) {
            $out = $m[1];
        } else {
            $out = preg_replace('/\s+\S*$/u', '', $cut);
            $out = preg_replace('/\b(?:Min|Mr|Ms|Dr|vs|etc)\.?$/u', '', $out);
        }
        return rtrim( $out, " .,-–—" ) . '…';
    }
}

if ( ! function_exists('wcd_join_bullets') ) {
    function wcd_join_bullets( array $bits, $limit = 158 ) {
        $bits  = array_values( array_filter( array_map('trim', $bits) ) );
        $out   = implode(' • ', $bits);
        if ( mb_strlen($out) <= $limit ) return $out;
        while ( count($bits) > 1 && mb_strlen(implode(' • ', $bits)) > $limit ) array_pop($bits);
        $out = implode(' • ', $bits);
        return ( mb_strlen($out) > $limit ) ? wcd_summarize_text($out, $limit) : $out;
    }
}

if ( ! function_exists('wcd_money_from_text') ) {
    // Normalize "€1,000" or "500 EUR" -> "€1,000"/"€500"
    function wcd_money_from_text( $s ) {
        if ( preg_match('~([\p{Sc}])\s*([0-9][0-9\.,]*)~u', $s, $m) ) return $m[1] . $m[2];
        if ( preg_match('~([0-9][0-9\.,]*)\s*(EUR|USD|CAD|AUD|GBP)~i', $s, $m) ) {
            $map = array('EUR'=>'€','USD'=>'$','CAD'=>'C$','AUD'=>'A$','GBP'=>'£');
            $sym = isset($map[strtoupper($m[2])]) ? $map[strtoupper($m[2])] : strtoupper($m[2]) . ' ';
            return $sym . $m[1];
        }
        return '';
    }
}
if ( ! function_exists('wcd_money_from_text_relaxed') ) {
    // Also handle number-first forms like "4,000 €/$" (keep original order)
    function wcd_money_from_text_relaxed( $s ) {
        $out = wcd_money_from_text($s);
        if ( $out !== '' ) return $out;
        if ( preg_match('~([0-9][0-9\.,]*)\s*(€\/\$|€|\$|EUR|USD|CAD|AUD|GBP)~iu', $s, $m) ) {
            return trim($m[1] . ' ' . $m[2]);
        }
        return '';
    }
}

if ( ! function_exists('wcd_get_meta_any') ) {
    function wcd_get_meta_any( $post_id, array $keys ) {
        foreach ( $keys as $k ) {
            $v = get_post_meta( $post_id, $k, true );
            if ( $v !== '' && $v !== null ) return is_string($v) ? trim($v) : $v;
        }
        return '';
    }
}

/* -------- primary extraction: backend → DOM → regex -------- */

if ( ! function_exists('wcd_extract_facts') ) {
    function wcd_extract_facts( WP_Post $post ) {
        $bits  = array();
        $html  = apply_filters( 'the_content', $post->post_content );
        $plain = trim( preg_replace('~\s+~u', ' ', wp_strip_all_tags( $html ) ) );

        // --- 1) Backend metas ---
        $bonus_raw   = get_post_meta($post->ID, '_wcc_bonus', true);
        $bonus_code  = get_post_meta($post->ID, '_wcc_bonus_code', true);
        $min_deposit = get_post_meta($post->ID, '_wcc_min_deposit', true);
        $free_spins  = get_post_meta($post->ID, '_wcc_free_spins', true);
        $highlights  = get_post_meta($post->ID, '_wcc_highlights', true);

        // Helper: normalize money including number-first (e.g. "4,000 €/$")
        $money_relaxed = function($s){
            if ( preg_match('~([\p{Sc}])\s*([0-9][0-9\.,]*)~u', $s, $m) ) return $m[1].$m[2];
            if ( preg_match('~([0-9][0-9\.,]*)\s*(€\/\$|€|\$|EUR|USD|CAD|AUD|GBP)~iu', $s, $m) )
                return trim($m[1].' '.$m[2]);
            return '';
        };

        // Parse a flexible "Welcome bonus" string (percent optional)
        $make_bonus_bullet = function($src) use ($money_relaxed, $free_spins){
            if ($src==='') return '';
            $s = trim(preg_replace('~\s+~u',' ',$src));

            preg_match('~(\d{1,3})\s*%~', $s, $mPct);      // optional
            $cap = $money_relaxed($s);                     // optional
            preg_match('~(\d{1,4})\s*(?:free\s*)?spins?\b~iu', $s, $mFS);
            $fs  = !empty($mFS[1]) ? (int)$mFS[1] : (is_numeric($free_spins)? (int)$free_spins : 0);

            // Build a clean line with whatever we have
            $parts = array();
            if (!empty($mPct[1]) && $cap!=='') {
                $parts[] = (int)$mPct[1].'% up to '.$cap;
            } elseif (!empty($mPct[1]) && $cap==='') {
                $parts[] = (int)$mPct[1].'%';
            } elseif ($cap!=='') {
                // No percent → money-first style
                $parts[] = 'Up to '.$cap;
            }
            if ($fs) $parts[] = $fs.' FS';

            if (empty($parts)) return '';
            return implode(' + ', $parts).' Welcome bonus';
        };

        if ( $bonus_raw !== '' ) {
            $b = $make_bonus_bullet($bonus_raw);
            if ($b !== '') $bits['bonus'] = $b;
        }

        // Interpret highlights quickly
        if ( $highlights ) {
            $hl = strtolower($highlights);
            if ( strpos($hl,'crypto') !== false ) $bits['payments'] = 'Crypto-friendly';
            if ( strpos($hl,'vip') !== false || strpos($hl,'tournament') !== false ) $bits['vip'] = 'VIP & tournaments';
            if ( strpos($hl,'mobile') !== false || strpos($hl,'ios') !== false || strpos($hl,'android') !== false ) $bits['mobile'] = 'Mobile iOS/Android';
        }

        // --- 2) DOM scan (hero + overview) ---
        if ( trim($html) !== '' ) {
            $prev = libxml_use_internal_errors(true);
            $dom  = new DOMDocument();
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $xp = new DOMXPath($dom);

            // Hero bonus (percent OPTIONAL now)
            if ( empty($bits['bonus']) ) {
                $hero = $xp->query('//span[contains(@class,"bonus")]')->item(0);
                if ( $hero ) {
                    $b = $make_bonus_bullet(trim($hero->textContent));
                    if ($b !== '') $bits['bonus'] = $b;
                }
            }

           // Overview facts (works for casino & slot)
$reels = 0; $rows = 0; // for wc_slot grid like 5×4

foreach ( $xp->query('//ul[contains(@class,"tight")]//p[strong]') as $p ) {
    $text  = trim(preg_replace('~\s+~', ' ', $p->textContent));
    $parts = explode(':', $text, 2);
    if ( count($parts) < 2 ) continue;
    $label = strtolower(trim($parts[0]));
    $value = trim($parts[1]);

    // ===== CASINO bits you already had =====
    if ( $label === 'established' && preg_match('~\b(19|20)\d{2}\b~', $value, $m) ) {
        $bits['est'] = 'Est. '.$m[0];
        continue;
    }
    if ( $label === 'games' && preg_match('~([0-9][0-9\.,+]+)~', $value, $m) ) {
        $bits['games'] = trim($m[1]).' games';
        continue;
    }
    if ( $label === 'license' ) {
        $lic = trim(preg_split('~\(~', $value)[0]);
        $lic = preg_replace('~\s+~', ' ', $lic);
        if ($lic!=='') $bits['license'] = $lic.' license';
        continue;
    }
    if ( $label === 'currencies' && preg_match('~crypto~i', $value) ) {
        if ( empty($bits['payments']) ) $bits['payments'] = 'Crypto-friendly';
        continue;
    }

    // ===== SLOT facts =====
    if ( $label === 'provider' ) {
        $bits['provider'] = preg_replace('~\s*\(.*\)$~','', $value);
        continue;
    }
    if ( $label === 'reels' && preg_match('~\b(\d{1,2})\b~', $value, $m) ) {
        $reels = (int)$m[1];
        continue;
    }
    if ( $label === 'rows' && preg_match('~\b(\d{1,2})\b~', $value, $m) ) {
        $rows = (int)$m[1];
        continue;
    }
    if ( $label === 'paylines' && preg_match('~([0-9][0-9\.,]+)\s*(ways?|lines?)~i', $value, $m) ) {
        $bits['ways'] = trim($m[1]).' '.strtolower($m[2]); // e.g. "1,024 ways" or "25 lines"
        continue;
    }
    if ( $label === 'rtp' && preg_match('~\b([0-9]{2}(?:\.[0-9]{1,2})?)\s*%~', $value, $m) ) {
        $bits['rtp'] = $m[1].'% RTP';
        continue;
    }
    if ( $label === 'volatility' ) {
        if ( preg_match('~(very high|high|medium|med|low|extreme)~i', $value, $m) ) {
            $v = strtolower($m[1]); if ($v==='med') $v='medium';
            $bits['volatility'] = ucfirst($v).' volatility';
        } elseif ( preg_match('~\b([0-9]{1,2})\/10\b~', $value, $m) ) {
            // fallback if only a numeric score is present
            $bits['volatility'] = $m[1].'/10 volatility';
        }
        continue;
    }
    if ( $label === 'max win' && preg_match('~([0-9][0-9\.,]+)\s*x~i', $value, $m) ) {
        $bits['maxwin'] = 'Max win '.trim($m[1]).'x';
        continue;
    }
    if ( $label === 'bet range' ) {
        if ( preg_match('~(.+?)\s*[–-]\s*(.+)~u', $value, $m) ) {
            $min = $money_relaxed($m[1]);
            $max = $money_relaxed($m[2]);
            if ($min!=='' && $max!=='') $bits['bets'] = $min.'–'.$max.' bets';
        }
        continue;
    }
}
// Build 5×4 grid if both were captured
if ( $reels && $rows ) $bits['grid'] = $reels.'×'.$rows;
        }

        // --- 3) Prose fallbacks (license, games, wager, crypto, payouts, etc.) ---
        if ( empty($bits['games']) ) {
            if ( preg_match('~over\s+([0-9][0-9\.,]+)\s*\+?\s+games~iu', $plain, $m)
              || preg_match('~\b([0-9][0-9\.,]+)\+?\s+games\b~iu', $plain, $m) ) {
                $bits['games'] = trim($m[1]).' games';
            }
        }
        if ( empty($bits['license']) ) {
            if ( preg_match('~\b(Cura[cç]ao)\b[-\s]*licensed\b~iu', $plain, $m)
              || preg_match('~licensed\s+in\s+(Cura[cç]ao)\b~iu', $plain, $m)
              || preg_match('~\b(Cura[cç]ao)\s+license\b~iu', $plain, $m)
              || preg_match('~licensed\s+by\s+the\s+([A-Za-z ]+?)\b~iu', $plain, $m) ) {
                $lic = trim(str_ireplace('gaming control board','',$m[1]));
                if ($lic!=='') $bits['license'] = $lic.' license';
            }
        }
        if ( empty($bits['wager']) && preg_match('~\b(\d{1,3})x\b[^.]{0,120}?\bwager~i', $plain, $m) ) {
            $bits['wager'] = (int)$m[1].'x wagering';
        }
        if ( empty($bits['payments']) && preg_match('~\bcrypto(?:currency|currencies)?|bitcoin|ethereum|litecoin\b~iu', $plain) ) {
            $bits['payments'] = 'Crypto-friendly';
        }
        if ( preg_match('~\b(\d{1,3})\s*[-–to]\s*(\d{1,3})\s*hours?\b~i', $plain, $m) ) {
            $pay = (int)$m[1].'–'.(int)$m[2].'h payouts';
            $bits['payments'] = ( isset($bits['payments']) && strpos($bits['payments'],'Crypto')!==false )
                ? 'Crypto-friendly ('.$pay.')' : ($bits['payments'] ?? $pay);
        }
        if ( empty($bits['providers']) && preg_match('~\b([0-9]{1,4}\+?)\s*providers\b~i', $plain, $m) ) {
            $bits['providers'] = $m[1].' providers';
        }
        if ( empty($bits['vip']) && preg_match('~\bVIP\b|loyalty|tournament~i', $plain) ) $bits['vip'] = 'VIP & tournaments';
        if ( empty($bits['mobile']) && preg_match('~mobile[- ]optimized|iOS|Android~i', $plain) ) $bits['mobile'] = 'Mobile iOS/Android';
        if ( empty($bits['score']) && preg_match('~\b([0-9]{1,2}(?:\.[0-9])?)\/10\b~', $plain, $m) ) $bits['score'] = $m[1].'/10';

        return $bits;
    }
}
// Slot builder: compact, factual bullets for wc_slot pages.
if ( ! function_exists('wcd_build_desc_slot') ) {
    function wcd_build_desc_slot(array $bits, int $max = 6, int $limit = 158): string {
        // Order for slots
        $order = ['grid','ways','rtp','volatility','maxwin','bets','provider'];
        $out = [];
        foreach ($order as $k) {
            if (!empty($bits[$k])) {
                $out[] = trim($bits[$k]);
                if (count($out) >= $max) break;
            }
        }
        if (!$out) return '';
        return wcd_join_bullets(array_unique($out), $limit);
    }
}


/* -------- builder (bullets only; no casino name prefix) -------- */
// Build the final bullet string from extracted bits.
// Put 'bonus' first, then fill with the rest up to $max items.
if ( ! function_exists('wcd_build_desc') ) {
    function wcd_build_desc(array $bits, int $max = 5, int $limit = 158): string {
        // preferred order — bonus comes first
        $order = ['bonus','games','payments','vip','mobile','score','wager','license','providers','est'];

        $out = [];
        foreach ($order as $k) {
            if (!empty($bits[$k])) {
                $out[] = trim($bits[$k]);
                if (count($out) >= $max) break;
            }
        }
        // enforce the same length cap/ellipsis logic you’re using elsewhere
        return wcd_join_bullets(array_unique($out), $limit);
    }
}

if ( ! function_exists('wcd_build_auto_desc') ) {
    function wcd_build_auto_desc( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_type, array('wc_casino','wc_slot'), true ) ) return '';

        $bits = wcd_extract_facts( $post );

        if ( $post->post_type === 'wc_slot' ) {
            // New slot-specific summary (no plain score-only output)
            $desc = wcd_build_desc_slot($bits, 6, 158);
        } else {
            // Casino summary — use your existing generic builder if present
            if ( function_exists('wcd_build_desc') ) {
                $desc = wcd_build_desc($bits, 5, 158);
            } else {
                $ordered = array();
                foreach ( array('bonus','games','wager','license','payments','providers','est','vip','mobile','score') as $k ) {
                    if ( ! empty( $bits[$k] ) ) $ordered[] = $bits[$k];
                }
                $desc = wcd_join_bullets( $ordered, 158 );
            }
        }

        if ( $desc === '' ) {
            $desc = wcd_summarize_text( 'Read our full review.', 158 );
        }
        return $desc;
    }
}


/* -------- caching layer (no bulk re-save needed) -------- */

if ( ! function_exists('wcd_auto_desc_cache_key') ) {
    function wcd_auto_desc_cache_key( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) return false;

        // Include metas that affect description
        $meta_sig = array(
            '_wcc_bonus','_wcc_min_deposit','_wcc_bonus_code',
            '_wcc_free_spins','wcc_free_spins',
            '_wcc_highlights'
        );
        $buf = '';
        foreach ( $meta_sig as $k ) {
            $v = get_post_meta($post_id, $k, true);
            if ( $v !== '' && $v !== null ) $buf .= $k . '=' . (is_scalar($v)? $v : wp_json_encode($v)) . ';';
        }

        $sig = md5( (string) $post->post_modified_gmt . '|' . WC_AUTO_DESC_VERSION . '|' . $buf );
        return 'wcd_autodesc_' . $post_id . '_' . $sig;
    }
}

if ( ! function_exists('wcd_get_auto_desc_cached') ) {
    function wcd_get_auto_desc_cached( $post_id ) {
        $key = wcd_auto_desc_cache_key( $post_id );
        if ( ! $key ) return '';

        $val = wp_cache_get( $key, 'wcd' );
        if ( $val !== false ) return $val;

        $val = get_transient( $key );
        if ( $val !== false ) {
            wp_cache_set( $key, $val, 'wcd', DAY_IN_SECONDS * 7 );
            return $val;
        }

        $val = wcd_build_auto_desc( $post_id );
        set_transient( $key, $val, DAY_IN_SECONDS * 30 );
        wp_cache_set( $key, $val, 'wcd', DAY_IN_SECONDS * 7 );
        return $val;
    }
}

/* -------- Rank Math glue -------- */

add_filter( 'rank_math/vars/wc_auto_desc', function( $value, $context = null ) {
    $post_id = 0;
    if ( is_array($context) ) {
        if ( isset($context['post']) && $context['post'] instanceof WP_Post ) {
            $post_id = (int) $context['post']->ID;
        } elseif ( isset($context['id']) && is_numeric($context['id']) ) {
            $post_id = (int) $context['id'];
        }
    }
    if ( ! $post_id ) $post_id = get_the_ID();
    if ( ! $post_id ) return '';

    $desc = wcd_get_auto_desc_cached( $post_id );
    return $desc ?: '';
}, 10, 2 );

add_action( 'rank_math/vars/register_extra_replacements', function( $mgr = null ) {
    if ( ! $mgr && function_exists('rank_math') ) {
        $plugin = rank_math();
        if ( is_object($plugin) && isset($plugin->variables) ) $mgr = $plugin->variables;
    }
    if ( $mgr && is_object($mgr) && method_exists($mgr,'register_replacement') ) {
        $mgr->register_replacement( 'wc_auto_desc', array(
            'name'        => 'WC Auto Description',
            'description' => 'Auto summary from content (wc_slot & wc_casino).',
            'variable'    => 'wc_auto_desc',
            'example'     => '100% up to 4,000 €/$ Welcome bonus • 12,000 games • Crypto-friendly…',
            'callback'    => function( $context = null ) {
                return apply_filters( 'rank_math/vars/wc_auto_desc', '', $context );
            },
        ) );
    }
}, 10, 1 );

// If a post’s Rank Math description is empty, use ours on the front end.
add_filter( 'rank_math/frontend/description', function( $desc ) {
    if ( $desc ) return $desc;
    if ( is_singular( array( 'wc_slot', 'wc_casino' ) ) ) {
        $id  = get_the_ID();
        $val = apply_filters( 'rank_math/vars/wc_auto_desc', '', array( 'id' => $id ) );
        return $val ?: $desc;
    }
    return $desc;
}, 11 );





