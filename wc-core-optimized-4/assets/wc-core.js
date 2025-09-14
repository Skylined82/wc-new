/* =========================================================
   WC-Core front-end JS
   - Slot demo overlay + mobile full-screen
   - Casinos: load more
   - Slots: load more + mobile-friendly search
   ========================================================= */

/* ===== Slot demo: center-overlay lazy load + mobile full-screen ===== */
(function ($) {
  'use strict';

$(document).on('click', '.wcc-demo .demo-overlay', function (e) {
  e.preventDefault();

    var wrap = $(this).closest('.framewrap');
    var ifr  = wrap.find('iframe');
    var src  = ifr.attr('data-src');
    if (!src) return;

    // On phones/tablets open vendor demo full-screen
    if (window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
      window.location.assign(src);
      return;
    }

    // Desktop: lazy-load into the iframe
    if (!ifr.attr('src') || ifr.attr('src') === 'about:blank') {
      ifr.attr('src', src);
    }

    $(this).fadeOut(150);
  });

})(jQuery);


/* ===== Casinos: load more ===== */
(function ($) {
  'use strict';

  var $list = $('#wcc-casino-list');
  var $btn  = $('#wcc-load-more');
  if (!$list.length || !$btn.length || typeof WCC_Ajax === 'undefined') return;

  var isLoading = false;

  function getQuery() {
    try { return JSON.parse($list.attr('data-query') || '{}'); }
    catch (e) { return {}; }
  }

  function setPage(p) {
    $list.attr('data-page', String(p));
  }

  $btn.on('click', function () {
    if (isLoading) return;
    isLoading = true;
    $list.attr('aria-busy', 'true');
    $btn.attr('aria-busy', 'true');

    var original = $btn.text();
    $btn.text('Loading…').prop('disabled', true);

    var page  = parseInt($list.attr('data-page') || '1', 10);
    var query = getQuery();

    $.ajax({
      url:  WCC_Ajax.ajaxurl,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'wcc_load_more_casinos',
        nonce:  WCC_Ajax.nonce,
        page:   page,                    // server treats this as "current"; fetches next
        query:  JSON.stringify(query)
      }
    })
    .done(function (resp) {
      if (!resp || !resp.success || !resp.data) {
        $btn.text('Try again').prop('disabled', false);
        return;
      }

      if (resp.data.html) {
        $list.append(resp.data.html);
      }

      if (resp.data.hasMore) {
        setPage(resp.data.nextPage || (page + 1));
        $btn.text(original).prop('disabled', false);
      } else {
        $btn.remove();
      }
    })
    .fail(function () {
      $btn.text('Try again').prop('disabled', false);
    })
    .always(function () {
      isLoading = false;
      $list.removeAttr('aria-busy');
      $btn.removeAttr('aria-busy');
    });
  });

})(jQuery);


/* ===== Slots: load more + mobile-friendly search ===== */
(function ($) {
  'use strict';

  if (typeof WCC_Ajax === 'undefined') return;

  var $grid = $('#wcc-slots-grid');
  if (!$grid.length) return;

  var $s = $('#wcc-slots-search');

  function dataQuery() {
    try { return JSON.parse($grid.attr('data-query') || '{}'); }
    catch (e) { return {}; }
  }

  /* -- Load more (delegated so it survives replacement) -- */
  $(document).on('click', '#wcc-slots-load-more', function () {
    var $self = $(this);
    var page  = parseInt($grid.attr('data-page') || '1', 10);
    var q     = dataQuery();
    var term  = ($s.val() || '').trim();

    $self.prop('disabled', true).addClass('is-loading');

    $.post(WCC_Ajax.ajaxurl, {
      action: 'wcc_load_more_slots',
      nonce:  WCC_Ajax.nonce,
      page:   page,                    // server will return .next
      query:  JSON.stringify(q),
      s:      term
    }).done(function (r) {
      if (r && r.success) {
        if (r.data && r.data.html) $grid.append(r.data.html);
        // Prefer server's next page index if provided
        var next = (r.data && typeof r.data.next !== 'undefined') ? r.data.next : (page + 1);
        $grid.attr('data-page', String(next));

        if (!r.data.hasMore) {
          $self.remove();
        } else {
          $self.prop('disabled', false).removeClass('is-loading');
        }
      } else {
        $self.prop('disabled', false).removeClass('is-loading');
      }
    }).fail(function () {
      $self.prop('disabled', false).removeClass('is-loading');
    });
  });

  /* -- Search (debounced, resets list) — mobile friendly -- */
if ($s.length) {
  var debounceTimer, lastTerm = '';
  var reqSeq = 0; // increments per search

  function runSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      var q    = dataQuery();
      var term = ($s.val() || '').trim();
      var thisSeq = ++reqSeq;

      $.post(WCC_Ajax.ajaxurl, {
        action: 'wcc_load_more_slots',
        nonce:  WCC_Ajax.nonce,
        page:   0,                     // fresh query
        query:  JSON.stringify(q),
        s:      term
      }).done(function (r) {
        // Ignore stale responses
        if (thisSeq !== reqSeq) return;

        if (r && r.success && r.data) {
          var next = (typeof r.data.next !== 'undefined') ? r.data.next : 1;
          $grid.html(r.data.html).attr('data-page', String(next));

          if (r.data.hasMore) {
            // Ensure a button exists and is enabled
            var $btn = $('#wcc-slots-load-more');
            if (!$btn.length) {
              $('<button id="wcc-slots-load-more" class="wcc-load-more" type="button">Load more</button>')
                .insertAfter($grid);
            } else {
              $btn.prop('disabled', false).removeClass('is-loading');
            }
          } else {
            $('#wcc-slots-load-more').remove();
          }
        }
      });
    }, 250);
  }


    // Listen to input + search + change (iOS "×" fires `search`)
    $s.on('input.wcc search.wcc change.wcc', function () {
      var v = ($s.val() || '').trim();
      if (v !== lastTerm) {
        lastTerm = v;
        runSearch();
      }
    });

    // Prevent full-page submit on Enter; trigger search
    $s.on('keydown.wcc', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        runSearch();
      }
    });
  }

})(jQuery);
