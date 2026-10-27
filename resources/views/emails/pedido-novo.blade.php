{{--
    Aviso de pedido pago para a equipe (leva 16, App\Mail\PedidoNovo).
    E-mail não lê CSS externo nem variável CSS: tabela + estilo inline, com os
    hex da paleta (aptk-tokens.css) escritos à mão — Espumante #E5DBC9,
    Cuba Libre #020203, Scotch #BF6300. Largura máxima 600 px, uma coluna:
    lê igual no Gmail e no celular.
--}}
@php
    $label = 'padding:0 0 4px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#7a6f63;';
    $secao = 'padding:22px 24px 0;';
    $txt   = 'font-size:15px;line-height:1.5;color:#020203;';
    $linha = 'padding:6px 0;font-size:15px;color:#020203;';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo pedido {{ $numero }}</title>
</head>
<body style="margin:0;padding:0;background:#E5DBC9;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#E5DBC9;">
<tr><td align="center" style="padding:24px 12px;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;">

    {{-- Cabeçalho --}}
    <tr><td style="padding:24px;background:#020203;border-radius:8px 8px 0 0;">
        <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#E5DBC9;">APTK Spirits · pedido pago</div>
        <div style="padding-top:6px;font-size:24px;font-weight:bold;color:#ffffff;">{{ $numero }}</div>
        <div style="padding-top:4px;font-size:14px;color:#E5DBC9;">{{ $data }} (horário de Brasília)</div>
    </td></tr>

    {{-- Cliente --}}
    <tr><td style="{{ $secao }}">
        <div style="{{ $label }}">Cliente</div>
        <div style="{{ $txt }}">
            <strong>{{ $cliente['nome'] }}</strong><br>
            CPF {{ $cliente['cpf'] }}<br>
            <a href="mailto:{{ $cliente['email'] }}" style="color:#BF6300;">{{ $cliente['email'] }}</a><br>
            {{ $cliente['telefone'] }}
        </div>
    </td></tr>

    {{-- Entrega --}}
    <tr><td style="{{ $secao }}">
        <div style="{{ $label }}">Entrega</div>
        <div style="{{ $txt }}">
            @if ($destinatario)<strong>{{ $destinatario }}</strong><br>@endif
            @forelse ($endereco as $parte){{ $parte }}<br>@empty — <br>@endforelse
            <span style="color:#7a6f63;">Frete:</span> {{ $frete['nome'] }} · {{ $frete['valor'] }}@if ($frete['prazo']) · {{ $frete['prazo'] }}@endif
        </div>
    </td></tr>

    {{-- Itens --}}
    <tr><td style="{{ $secao }}">
        <div style="{{ $label }}">Itens</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            @foreach ($itens as $it)
                <tr><td style="padding:10px 0;border-top:1px solid #E5DBC9;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size:15px;color:#020203;">
                                <strong>{{ $it['nome'] }}</strong>@if ($it['volume']) · {{ $it['volume'] }}@endif
                                <div style="padding-top:2px;font-size:13px;color:#7a6f63;">{{ $it['qtd'] }} × {{ $it['unitario'] }}</div>
                            </td>
                            <td align="right" valign="top" style="font-size:15px;color:#020203;white-space:nowrap;padding-left:12px;">{{ $it['subtotal'] }}</td>
                        </tr>
                    </table>
                    @if ($it['custom'])
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;background:#fbf3ea;border-left:4px solid #BF6300;">
                            <tr><td style="padding:12px 14px;font-size:14px;line-height:1.55;color:#020203;">
                                <div style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#BF6300;font-weight:bold;">Garrafa personalizada</div>
                                <div style="padding:6px 0;font-size:18px;">“{{ $it['custom']['texto'] }}”</div>
                                Bebida: <strong>{{ $it['custom']['bebida'] }}</strong><br>
                                Fonte: <strong>{{ $it['custom']['fonte'] }}</strong><br>
                                Cor: <strong>{{ $it['custom']['cor'] }}</strong>
                            </td></tr>
                        </table>
                    @endif
                </td></tr>
            @endforeach
        </table>
    </td></tr>

    {{-- Totais --}}
    <tr><td style="{{ $secao }}">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #E5DBC9;">
            <tr><td style="{{ $linha }}">Subtotal</td><td align="right" style="{{ $linha }}">{{ $subtotal }}</td></tr>
            <tr><td style="{{ $linha }}">Desconto</td><td align="right" style="{{ $linha }}">{{ $desconto }}</td></tr>
            <tr><td style="{{ $linha }}">Frete</td><td align="right" style="{{ $linha }}">{{ $frete_valor }}</td></tr>
            <tr><td style="padding:10px 0;font-size:18px;font-weight:bold;color:#020203;border-top:1px solid #E5DBC9;">Total</td>
                <td align="right" style="padding:10px 0;font-size:18px;font-weight:bold;color:#BF6300;border-top:1px solid #E5DBC9;">{{ $total }}</td></tr>
        </table>
    </td></tr>

    {{-- Pagamento --}}
    <tr><td style="{{ $secao }}">
        <div style="{{ $label }}">Pagamento</div>
        <div style="{{ $txt }}">
            {{ $pagamento['forma'] }}@if ($pagamento['parcelas']) · {{ $pagamento['parcelas'] }}@endif<br>
            Status: <strong>{{ $pagamento['status'] }}</strong>@if ($pago_em) · {{ $pago_em }}@endif
        </div>
    </td></tr>

    {{-- Botão --}}
    <tr><td align="center" style="padding:28px 24px 30px;">
        <table role="presentation" cellpadding="0" cellspacing="0">
            <tr><td bgcolor="#BF6300" style="border-radius:6px;">
                <a href="{{ $link_admin }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">Ver pedido no admin</a>
            </td></tr>
        </table>
    </td></tr>

</table>

<div style="padding-top:14px;font-size:12px;color:#7a6f63;">Aviso automático da loja APTK Spirits.</div>

</td></tr>
</table>
</body>
</html>
