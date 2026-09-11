<?php

namespace App\Services;

/**
 * Representa o resultado de uma consulta ao Bureau de Crédito externo.
 *
 * Existe pra evitar que o Controller/Service de regras precise saber os
 * detalhes de como a chamada HTTP falhou - ele só olha `sucesso` e decide
 * o que fazer.
 */
final class ConsultaBureauResultadoDTO
{
    private function __construct(
        public readonly bool $sucesso,
        public readonly ?int $score,
        public readonly ?string $motivoFalha,
    ) {
    }

    public static function sucesso(int $score): self
    {
        return new self(sucesso: true, score: $score, motivoFalha: null);
    }

    public static function falha(string $motivo): self
    {
        return new self(sucesso: false, score: null, motivoFalha: $motivo);
    }
}
