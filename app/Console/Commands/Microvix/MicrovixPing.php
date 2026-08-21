<?php

namespace App\Console\Commands\Microvix;

use App\Exceptions\MicrovixException;
use App\Services\Microvix\MicrovixClient;

class MicrovixPing extends MicrovixCommand
{
    protected $signature = 'microvix:ping';

    protected $description = 'Primeiro teste de fogo: B2CConsultaEmpresas com timestamp 0';

    public function handle(MicrovixClient $client): int
    {
        $this->info('Consultando B2CConsultaEmpresas em '.$client->urlSaida().' ...');

        try {
            $rows = $this->consultaPrimeiraCarga($client, 'B2CConsultaEmpresas');
        } catch (MicrovixException $e) {
            $this->error("Falhou: {$e->getMessage()}");
            $this->line('Resposta crua (se houver) está em microvix_logs.');

            return self::FAILURE;
        }

        $this->tabela($rows, [
            'empresa', 'nome_emp', 'cnpjEmp', 'portal',
            'centro_distribuicao', 'data_criacao', 'timestamp',
        ]);

        $this->info(count($rows ?? []).' empresa(s). Comunicação OK.');

        return self::SUCCESS;
    }
}
