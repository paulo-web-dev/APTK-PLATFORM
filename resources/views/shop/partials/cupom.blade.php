{{-- Campo de cupom (carrinho e checkout). $cupom = CupomAvaliacao|null da
     sessão; os botões usam os forms de shop.partials.cupom-forms. --}}
@once
@push('styles')
<style>
  .cupom-box { margin: 14px 0 4px; padding-top: 14px; border-top: 1px solid var(--color-border); }
  .cupom-box label { display: block; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: .06em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 7px; }
  .cupom-campo { display: flex; gap: 8px; }
  .cupom-campo input { flex: 1; min-width: 0; text-transform: uppercase; background: var(--color-bg); color: var(--color-text); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 9px 12px; font-family: var(--font-mono); font-size: var(--text-sm); }
  .cupom-campo button, .cupom-remover { background: transparent; color: var(--color-text); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 8px 14px; font-size: var(--text-sm); cursor: pointer; }
  .cupom-campo button:hover, .cupom-remover:hover { border-color: var(--color-primary); color: var(--color-primary); }
  .cupom-aplicado { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-success); }
  .cupom-remover { padding: 4px 10px; font-size: var(--text-xs); }
  .cupom-msg { font-size: var(--text-sm); margin: 8px 0 0; }
  .cupom-msg.is-ok { color: var(--color-success); }
  .cupom-msg.is-erro { color: var(--color-danger); }
</style>
@endpush
@endonce
<div class="cupom-box" id="cupom">
  @if ($cupom && $cupom->aplicado)
    <div class="cupom-aplicado">
      <span>Cupom {{ $cupom->codigo }} · {{ $cupom->coupon->rotuloValor() }} off</span>
      <button type="submit" form="cupomRemoverForm" class="cupom-remover">Remover</button>
    </div>
  @else
    @if ($cupom)
      {{-- Na sessão, mas não vale agora (inativado, expirado, mínimo...). --}}
      <p class="cupom-msg is-erro" style="margin:0 0 10px;">{{ $cupom->mensagem() }}
        <button type="submit" form="cupomRemoverForm" class="cupom-remover">Remover</button></p>
    @endif
    <label for="cupomCodigo">Cupom de desconto</label>
    <div class="cupom-campo">
      <input type="text" id="cupomCodigo" name="cupom" form="cupomAplicarForm" maxlength="50" autocomplete="off" placeholder="CÓDIGO">
      <button type="submit" form="cupomAplicarForm">Aplicar</button>
    </div>
  @endif
  @if (session('cupom_ok'))
    <p class="cupom-msg is-ok" role="status">{{ session('cupom_ok') }}</p>
  @endif
  @if (session('cupom_erro'))
    <p class="cupom-msg is-erro" role="alert">{{ session('cupom_erro') }}</p>
  @endif
</div>
