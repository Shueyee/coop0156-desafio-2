<?php

namespace App\Services;

/**
 * Aplica as regras de elegibilidade de crédito da cooperativa.
 *
 * De propósito não sabe nada sobre HTTP, Bureau ou banco de dados - só
 * recebe os números já resolvidos (renda, score, valor) e devolve a
 * decisão. Isso deixa fácil de testar sem Http::fake() nem RefreshDatabase.
 */
class AnaliseCreditoService
{
    private const RENDA_MINIMA = 1500.00;

    private const SCORE_MINIMO = 400;

    private const SCORE_TAXA_REDUZIDA = 700;

    private const TAXA_PADRAO = 4.5;

    private const TAXA_REDUZIDA = 2.9;

    private const NUMERO_PARCELAS = 12;

    private const LIMITE_COMPROMETIMENTO_RENDA = 0.3;

    public function avaliar(float $rendaMensal, int $score, float $valorSolicitado): ResultadoAnaliseCreditoDTO
    {
        if ($rendaMensal < self::RENDA_MINIMA) {
            return ResultadoAnaliseCreditoDTO::reprovado('Renda mínima insuficiente');
        }

        if ($score < self::SCORE_MINIMO) {
            return ResultadoAnaliseCreditoDTO::reprovado('Score de crédito muito baixo');
        }

        $taxaJuros = $score >= self::SCORE_TAXA_REDUZIDA ? self::TAXA_REDUZIDA : self::TAXA_PADRAO;
        $valorParcela = $this->calcularValorParcela($valorSolicitado, $taxaJuros);

        if ($valorParcela > $rendaMensal * self::LIMITE_COMPROMETIMENTO_RENDA) {
            return ResultadoAnaliseCreditoDTO::reprovado(
                'Comprometimento de renda superior a 30%',
                $taxaJuros,
                $valorParcela,
            );
        }

        return ResultadoAnaliseCreditoDTO::aprovado($taxaJuros, $valorParcela);
    }

    private function calcularValorParcela(float $valorSolicitado, float $taxaJuros): float
    {
        $jurosTotais = $valorSolicitado * ($taxaJuros / 100) * self::NUMERO_PARCELAS;
        $valorTotal = $valorSolicitado + $jurosTotais;

        return round($valorTotal / self::NUMERO_PARCELAS, 2);
    }
}
