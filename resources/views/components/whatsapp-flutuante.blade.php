@props(['mensagem' => null, 'marca' => null])
@php
    /* Botão flutuante de WhatsApp (leva 20): canto inferior direito de todas
       as páginas públicas (layouts.public, layouts.marca, layouts.ice4pros).
       A página dá contexto com @section('whatsapp_mensagem'); o layout passa
       o yield aqui (vem escapado, por isso o decode). Cores pelos tokens
       --color-whatsapp / --color-on-whatsapp: verde oficial na APTK
       (aptk-tokens.css), turquesa + preto na Ice4Pros (ice4pros-tokens.css).
       CSS próprio aqui porque a Ice4Pros não carrega o aptk.css.
       marca = slug com número próprio (Ice4Pros, leva 21); sem = APTK. */
    $texto = trim(html_entity_decode((string) $mensagem, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $texto = $texto !== '' ? $texto : 'Olá! Vim pelo site da APTK.';
@endphp
<style>
  .wa-flutuante {
    position: fixed; z-index: 400;
    right: calc(20px + env(safe-area-inset-right, 0px));
    bottom: calc(20px + env(safe-area-inset-bottom, 0px));
    width: 56px; height: 56px; border-radius: 50%;
    display: grid; place-items: center;
    background: var(--color-whatsapp); color: var(--color-on-whatsapp);
    box-shadow: 0 6px 20px rgba(0, 0, 0, .28);
    transition: transform .18s ease;
  }
  .wa-flutuante:hover { transform: translateY(-2px); color: var(--color-on-whatsapp); }
  .wa-flutuante:focus-visible { outline: 2px solid var(--color-whatsapp); outline-offset: 3px; }
  .wa-flutuante svg { width: 30px; height: 30px; }
  @media (max-width: 768px) {
    .wa-flutuante {
      width: 48px; height: 48px;
      right: calc(14px + env(safe-area-inset-right, 0px));
      bottom: calc(14px + env(safe-area-inset-bottom, 0px));
    }
    .wa-flutuante svg { width: 26px; height: 26px; }
    /* Folga no fim da página: o último botão (Finalizar pedido, Adicionar
       ao carrinho, rodapé) sempre rola até ficar acima do flutuante. */
    body { padding-bottom: calc(76px + env(safe-area-inset-bottom, 0px)); }
  }
  @media (prefers-reduced-motion: reduce) { .wa-flutuante { transition: none; } }
</style>
<a href="{{ whatsapp_link($texto, $marca) }}" target="_blank" rel="noopener"
   class="wa-flutuante" aria-label="Falar no WhatsApp" data-whatsapp-flutuante>
  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor">
    <path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35Z"/>
    <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.84 9.84 0 0 0 12.04 2Zm0 18.15h-.01a8.23 8.23 0 0 1-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24Z"/>
  </svg>
</a>
