(function() {
  // Toggleable debug helper
  var DBG = true;
  function log() {
    if (!DBG) return;
    try { console.log.apply(console, ['[WCC TOC]'].concat([].slice.call(arguments))); } catch(e){}
  }

  function slugify(txt) {
    return String(txt || '')
      .replace(/<[^>]+>/g, '')
      .replace(/&[^\s;]+;/g, '')
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }

  function buildTOCFromHtml(html) {
    log('buildTOCFromHtml: input length', html.length);
    var div = document.createElement('div'); div.innerHTML = html;
    var seen = {}, items = [];

    Array.prototype.forEach.call(div.querySelectorAll('h1,h2,h3'), function(h){
      var level = (h.tagName === 'H3') ? 3 : 2; // H1 & H2 => top level
      var text  = (h.textContent || '').trim(); if (!text) return;
      var id = (h.getAttribute('id') || '').trim();
      if (!id) {
        var base = slugify(text) || 'section', cand = base, n = 2;
        while (seen[cand]) { cand = base + '-' + n; n++; }
        id = cand; h.setAttribute('id', id);
      }
      seen[id] = true;
      items.push({ level: level, id: id, text: text });
    });

    log('buildTOCFromHtml: headings found', items.length);

    var toc = '';
    if (items.length) {
      toc += '<nav class="wcc-toc"><h2>Table of Contents</h2><ul>';
      var cur = 2;
      items.forEach(function(it){
        while (cur < it.level) { toc += '<ul>'; cur++; }
        while (cur > it.level) { toc += '</ul>'; cur--; }
        toc += '<li><a href="#' + it.id.replace(/"/g,'&quot;') + '">' +
               it.text.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</a></li>';
      });
      while (cur > 2) { toc += '</ul>'; cur--; }
      toc += '</ul></nav>';
    } else {
      toc = '<nav class="wcc-toc"><h2>Table of Contents</h2></nav>';
    }

    return { tocHtml: toc, updatedHtml: div.innerHTML };
  }

  tinymce.PluginManager.add('wcc_toc_plugin', function(editor) {
    log('plugin loaded for', editor && editor.id, 'TinyMCE', tinymce.majorVersion + '.' + tinymce.minorVersion);

    editor.on('init', function() {
      log('editor init', editor.id);
    });

    function insertOrReplaceTOC() {
      log('TOC button click', editor.id);
      var MARK_STR  = '%%WCC_INSERT%%';
      var MARK_NODE = '<span data-wcc-mark="1"></span>';

      try {
        editor.undoManager.transact(function(){
          var html = editor.getContent({ format: 'html' });
          log('current content length', html.length);

          // Replace existing [wcc_toc] shortcodes with a marker
          html = html.replace(/\[wcc_toc(?:[^\]]*)?\]/i, MARK_STR);
          var hadShortcode = html.indexOf(MARK_STR) !== -1;

          // If no marker yet, insert one at the caret
          if (!hadShortcode && html.indexOf(MARK_STR) === -1) {
            log('no [wcc_toc] found; inserting caret marker');
            editor.insertContent(MARK_STR);
            html = editor.getContent({ format: 'html' });
            log('after insert, content length', html.length);
          }

          // Replace the string marker with a DOM placeholder
          var htmlWithNode = html.replace(MARK_STR, MARK_NODE);
          var hasNode = htmlWithNode.indexOf('data-wcc-mark="1"') !== -1;
          log('marker node present?', hasNode);

          if (!hasNode) {
            log('ERROR: marker node not found after insertion/replacement');
          }
        htmlWithNode = htmlWithNode.replace(/<nav class="wcc-toc"[\s\S]*?<\/nav>/i, MARK_NODE);
          var processed = buildTOCFromHtml(htmlWithNode);

          // Swap placeholder node for TOC
          var finalHtml = processed.updatedHtml.replace(MARK_NODE, processed.tocHtml);
          log('final length', finalHtml.length);

          editor.setContent(finalHtml, { format: 'html' });
        });

        editor.focus();
        editor.nodeChanged();
        log('done');
      } catch (err) {
        log('EXCEPTION', err && (err.stack || err.message || err));
        console.error(err);
      }
    }

    editor.addButton('wcc_toc', {
      text: 'TOC',
      tooltip: 'Insert Table of Contents',
      onclick: insertOrReplaceTOC
    });

    // For manual testing from console:
    editor.addCommand('wccInsertTOC', insertOrReplaceTOC);
  });
})();
