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
