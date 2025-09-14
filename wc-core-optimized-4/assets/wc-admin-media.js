(function($){

  function bindBtn($btn){

    $btn.on('click', function(e){

      e.preventDefault();

      var target = $(this).data('target');

      var $input = $('#'+target);

      var $wrap = $(this).closest('.wcc-media');

      var frame = wp.media({ title: 'Select Logo', button: { text: 'Use this' }, library: { type: 'image' }, multiple: false });

      frame.on('select', function(){

        var att = frame.state().get('selection').first().toJSON();

        $input.val(att.id);

        $wrap.find('.preview').html('<img class="thumb" src="'+(att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url)+'" alt="">');

      });

      frame.open();

    });

  }

  $(document).on('click','.wcc-remove',function(e){

    e.preventDefault();

    var target = $(this).data('target');

    $('#'+target).val('');

    $(this).closest('.wcc-media').find('.preview').empty();

  });

  $(function(){ $('.wcc-upload').each(function(){ bindBtn($(this)); }); });

})(jQuery);

