<?php

namespace App\Services\Microvix;

/**
 * Relógio injetável da serialização de envio (obs. 5 de B2CCadastraPedido:
 * duas requisições do mesmo método no mesmo segundo corrompem a reserva de
 * estoque). Nos testes entra um fake com segundo virtual — nada dorme de
 * verdade.
 */
class MicrovixRelogio
{
    /** Segundo atual (epoch). */
    public function agora(): int
    {
        return time();
    }

    /** Dorme até o início do próximo segundo do relógio de parede. */
    public function esperaProximoSegundo(): void
    {
        usleep((int) ((1 - fmod(microtime(true), 1)) * 1_000_000) + 1_000);
    }
}
