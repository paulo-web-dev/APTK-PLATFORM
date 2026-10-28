{{--
    Pagamento confirmado (leva 17, App\Mail\PagamentoConfirmado). Mesmo
    esquema do pedido-novo: tabela + estilo inline, hex da paleta e do
    --color-success escritos à mão (e-mail não lê variável CSS).
--}}
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagamento confirmado {{ $numero }}</title>
</head>
<body style="margin:0;padding:0;background:#E5DBC9;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#E5DBC9;">
<tr><td align="center" style="padding:24px 12px;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;">
    <tr><td style="padding:24px;background:#020203;border-radius:8px 8px 0 0;">
        <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#E5DBC9;">APTK Spirits · pagamento confirmado</div>
        <div style="padding-top:6px;font-size:24px;font-weight:bold;color:#ffffff;">{{ $numero }}</div>
    </td></tr>

    <tr><td style="padding:20px 24px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#E8F3EC;border-left:4px solid #3F8F63;border-radius:4px;">
            <tr><td style="padding:14px 16px;">
                <div style="font-size:20px;font-weight:bold;color:#3F8F63;">Pago</div>
                <div style="padding-top:4px;font-size:15px;line-height:1.5;color:#020203;">
                    {{ $cliente }}<br>
                    {{ $forma }} · <strong>{{ $total }}</strong><br>
                    Confirmado em {{ $pago_em }} (horário de Brasília)
                </div>
            </td></tr>
        </table>
    </td></tr>

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
