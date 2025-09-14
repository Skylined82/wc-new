(function(){
  function ready(fn){ if(document.readyState!='loading'){fn()} else document.addEventListener('DOMContentLoaded',fn)}
  ready(function(){
    document.querySelectorAll('.hl-shell').forEach(function(shell){
      var track = shell.querySelector('.wcc-highlights');
      if(!track) return;
      var prev = shell.querySelector('.hl-prev'), next = shell.querySelector('.hl-next');
      function move(dir){ var amt = Math.round(shell.clientWidth * 0.9); track.scrollBy({left: dir*amt, behavior:'smooth'}); }
      if(prev) prev.addEventListener('click', function(){ move(-1); });
      if(next) next.addEventListener('click', function(){ move(1); });
    });
  });
})();