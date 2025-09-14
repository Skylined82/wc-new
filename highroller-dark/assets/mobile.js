(function(){
  function ready(fn){ if(document.readyState!=='loading'){fn()} else document.addEventListener('DOMContentLoaded',fn); }
  ready(function(){
    var drawer = document.querySelector('.mobile-drawer');
    var btn    = document.querySelector('.hambtn');
    var closeX = drawer ? drawer.querySelector('.closebtn') : null;
    var backdrop = drawer ? drawer.querySelector('.backdrop') : null;
    if(!drawer || !btn) return;

    function open(){
      drawer.classList.add('open');
      drawer.setAttribute('aria-hidden','false');
      btn.setAttribute('aria-expanded','true');
      document.body.style.overflow='hidden';
    }
    function close(){
      drawer.classList.remove('open');
      drawer.setAttribute('aria-hidden','true');
      btn.setAttribute('aria-expanded','false');
      document.body.style.overflow='';
    }
    btn.addEventListener('click', function(e){
      e.preventDefault();
      (drawer.classList.contains('open') ? close : open)();
    });
    if (closeX) closeX.addEventListener('click', function(e){ e.preventDefault(); close(); });
    if (backdrop) backdrop.addEventListener('click', function(){ close(); });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape') close(); });
  });
})();