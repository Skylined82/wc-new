<?php
// If a page with slug 'seo-footer' exists, render its content here.
// Otherwise, render sample SEO content you can replace by creating a page named "SEO Footer".
$seo = get_page_by_path('seo-footer');
echo '<section class="card seo-footer">';
echo '<h2>About '.esc_html( get_bloginfo('name') ).'</h2>';
if ( $seo ) {
  echo apply_filters('the_content', $seo->post_content);
} else {
  $best   = get_page_by_path('best-casinos');
  $worst  = get_page_by_path('worst-casinos');
  $slots  = get_page_by_path('slots-list');
  $news   = get_page_by_path('news');
  $bonus  = get_page_by_path('bonuses');
  ?>
  <p>Welcome to <?php echo esc_html( get_bloginfo('name') ); ?> — your guide to bonuses, best casinos, and high‑RTP slots. We test brands for payouts, game selection, and support.</p>
  <ul>
    <li><a href="<?php echo $bonus?esc_url( get_permalink($bonus->ID) ): '#' ?>">Casino Bonuses</a> — current welcome offers and reloads</li>
    <li><a href="<?php echo $best?esc_url( get_permalink($best->ID) ): '#' ?>">Best Casinos</a> — trusted operators with fast cashouts</li>
    <li><a href="<?php echo $worst?esc_url( get_permalink($worst->ID) ): '#' ?>">Worst Casinos</a> — brands with complaints or slow KYC</li>
    <li><a href="<?php echo $slots?esc_url( get_permalink($slots->ID) ): '#' ?>">Slots</a> — trending games by provider</li>
    <li><a href="<?php echo $news?esc_url( get_permalink($news->ID) ): '#' ?>">News</a> — market updates and strategy</li>
  </ul>
  <p class="small">18+ only. Please play responsibly. Regional availability and T&amp;C apply. This site may use affiliate links; this never affects our impartial ratings.</p>
  <?php
}
echo '</section>';
