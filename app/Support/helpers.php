<?php

if (! function_exists('aptk_route')) {
    /**
     * route() com o domínio da APTK (config app.url) forçado.
     *
     * Nos domínios próprios de marca (config/marcas.php) o route() normal
     * geraria URL com o host da requisição — ice4pros.com.br/loja não
     * existe. CTAs de LP de marca usam este helper.
     */
    function aptk_route(string $name, mixed $params = []): string
    {
        return rtrim(config('app.url'), '/').route($name, $params, false);
    }
}

if (! function_exists('data_br')) {
    /**
     * Data para EXIBIÇÃO: horário de Brasília, "dd/mm/aaaa hh:mm".
     *
     * O banco e o app.timezone ficam em UTC (trocar o fuso do app faria o
     * Laravel gravar horário local nos registros novos e deslocaria em 3h
     * todos os antigos). Converter só na exibição, sempre por aqui.
     *
     * $formato: true = com hora (padrão), false = só data, string = formato
     * próprio (ex. com segundos nos logs técnicos).
     *
     * Só para timestamps gravados com now() (created_at, paid_at...). Data
     * digitada no admin (published_at, validade de cupom) e coluna DATE
     * (vencimento de boleto, renovação) já estão em horário local: formatar direto.
     */
    function data_br(mixed $data, bool|string $formato = true): string
    {
        if ($data === null || $data === '') {
            return '—';
        }

        $carbon = $data instanceof \DateTimeInterface
            ? \Illuminate\Support\Carbon::instance($data)
            : \Illuminate\Support\Carbon::parse($data, 'UTC');

        return $carbon->copy()
            ->setTimezone('America/Sao_Paulo')
            ->translatedFormat(is_string($formato) ? $formato : ($formato ? 'd/m/Y H:i' : 'd/m/Y'));
    }
}

if (! function_exists('moeda_br')) {
    /** Valor em reais no padrão brasileiro: "R$ 1.524,00". */
    function moeda_br(mixed $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }
}

if (! function_exists('cpf_formatado')) {
    /** "12345678909" → "123.456.789-09". Vazio = null; fora de 11 dígitos volta como veio. */
    function cpf_formatado(?string $cpf): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $cpf);

        if ($digitos === '') {
            return null;
        }

        return strlen($digitos) === 11
            ? substr($digitos, 0, 3).'.'.substr($digitos, 3, 3).'.'.substr($digitos, 6, 3).'-'.substr($digitos, 9, 2)
            : (string) $cpf;
    }
}

if (! function_exists('whatsapp_link')) {
    /**
     * Link do WhatsApp com mensagem pré-preenchida opcional. Todo link de
     * WhatsApp do site passa por aqui. Sem $marca = número da APTK (config
     * marcas.whatsapp); com $marca = número próprio da marca (config
     * marcas.marcas.{slug}.whatsapp, ex. 'ice4pros').
     */
    function whatsapp_link(?string $mensagem = null, ?string $marca = null): string
    {
        $link = 'https://wa.me/'.whatsapp_numero($marca);

        return filled($mensagem) ? $link.'?text='.rawurlencode($mensagem) : $link;
    }
}

if (! function_exists('whatsapp_numero')) {
    /** Só dígitos, com DDI: o da marca (se tiver número próprio) ou o da APTK. */
    function whatsapp_numero(?string $marca = null): string
    {
        $numero = $marca !== null ? config("marcas.marcas.{$marca}.whatsapp") : null;

        return (string) ($numero ?: config('marcas.whatsapp'));
    }
}

if (! function_exists('marca_url')) {
    /**
     * Página de uma marca com domínio próprio (config marcas.marcas.{slug}):
     * no domínio da marca, o caminho relativo ('/', '/produtos'); no domínio
     * da APTK, a rota equivalente (/ice4pros, /ice4pros/produtos).
     */
    function marca_url(string $marca, string $pagina = ''): string
    {
        $pagina = trim($pagina, '/');
        $hosts = config("marcas.marcas.{$marca}.hosts", []);

        if (app()->bound('request') && in_array(request()->getHost(), $hosts, true)) {
            return '/'.$pagina;
        }

        return route($pagina === '' ? $marca : "{$marca}.{$pagina}");
    }
}

if (! function_exists('whatsapp_formatado')) {
    /** Número para exibição: "(11) 93429-8435" (sem o DDI 55). */
    function whatsapp_formatado(?string $marca = null): string
    {
        $n = preg_replace('/^55(?=\d{10,11}$)/', '', whatsapp_numero($marca));

        return preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $n, $m) ? "({$m[1]}) {$m[2]}-{$m[3]}" : $n;
    }
}
