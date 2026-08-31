<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        // ATENÇÃO: este middleware está DESATIVADO (comentado no Kernel).
        // Se for ativado, os hosts de marca de config/marcas.php precisam
        // entrar aqui — senão os domínios próprios (ice4pros.com.br etc.)
        // passam a ser rejeitados e o MarcaHost nunca vê o Host verdadeiro.
        return [
            $this->allSubdomainsOfApplicationUrl(),
        ];
    }
}
