<?php

namespace App\Services;

use App\Enums\StatusAnalise;

/**
 * Representa o resultado de aplicar as regras de crédito (renda mínima,
 * faixa de score, comprometimento de renda) - independente de como esse
 * resultado chegou até aqui (Controller decide o que persistir).
 */
final class ResultadoAnaliseCreditoDTO
{
    private function __construct(
        public readonly StatusAnalise $status,
        public readonly ?float $taxaJuros,
        public readonly ?float $valorParcela,
        public readonly ?string $motivoRejeicao,
    ) {
    }

    public static function aprovado(float $taxaJuros, float $valorParcela): self
    {
        return new self(
            status: StatusAnalise::APROVADO,
            taxaJuros: $taxaJuros,
            valorParcela: $valorParcela,
            motivoRejeicao: null,
        );
    }

    /**
     * @param  float|null  $taxaJuros  Informado quando a reprovação acontece depois de já ter
     *                                 calculado a taxa (ex.: comprometimento de renda).
     * @param  float|null  $valorParcela  Idem.
     */
    public static function reprovado(string $motivo, ?float $taxaJuros = null, ?float $valorParcela = null): self
    {
        return new self(
            status: StatusAnalise::REPROVADO,
            taxaJuros: $taxaJuros,
            valorParcela: $valorParcela,
            motivoRejeicao: $motivo,
        );
    }
}
