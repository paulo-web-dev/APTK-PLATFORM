{{-- Botao flutuante de tema (claro/escuro) — canto inferior esquerdo --}}
<button type="button" class="theme-toggle" id="aptkThemeToggle"
        aria-label="Alternar entre modo claro e escuro" aria-pressed="false" title="Alternar tema">
  <svg class="ic-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>
  </svg>
  <svg class="ic-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="4.2"/>
    <path d="M12 2v2.5M12 19.5V22M4.4 4.4l1.8 1.8M17.8 17.8l1.8 1.8M2 12h2.5M19.5 12H22M4.4 19.6 6.2 17.8M17.8 6.2l1.8-1.8"/>
  </svg>
</button>
<script>
(function(){
  var root=document.documentElement, btn=document.getElementById('aptkThemeToggle'), tmr;
  function sync(){ btn.setAttribute('aria-pressed', root.getAttribute('data-theme')==='dark' ? 'true':'false'); }
  sync();
  btn.addEventListener('click', function(){
    var dark = root.getAttribute('data-theme')==='dark';
    root.classList.add('theme-anim');
    if(dark){ root.removeAttribute('data-theme'); } else { root.setAttribute('data-theme','dark'); }
    try{ localStorage.setItem('aptk-theme', dark ? 'light':'dark'); }catch(e){}
    sync();
    clearTimeout(tmr); tmr=setTimeout(function(){ root.classList.remove('theme-anim'); }, 480);
  });
})();
</script>
