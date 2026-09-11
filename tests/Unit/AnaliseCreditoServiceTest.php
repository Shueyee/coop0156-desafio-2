<?php

namespace Tests\Unit;

use App\Enums\StatusAnalise;
use App\Services\AnaliseCreditoService;
use Tests\TestCase;

class AnaliseCreditoServiceTest extends TestCase
{
    public function test_reprova_por_renda_minima_insuficiente(): void
    {
        $resultado = (new AnaliseCreditoService())->avaliar(
            rendaMensal: 1000,
            score: 850,
            valorSolicitado: 5000,
        );

        $this->assertSame(StatusAnalise::REPROVADO, $resultado->status);
        $this->assertSame('Renda mínima insuficiente', $resultado->motivoRejeicao);
        $this->assertNull($resultado->taxaJuros);
        $this->assertNull($resultado->valorParcela);
    }

    public function test_reprova_por_score_baixo(): void
    {
        $resultado = (new AnaliseCreditoService())->avaliar(
            rendaMensal: 3000,
            score: 150,
            valorSolicitado: 5000,
        );

        $this->assertSame(StatusAnalise::REPROVADO, $resultado->status);
        $this->assertSame('Score de crédito muito baixo', $resultado->motivoRejeicao);
        $this->assertNull($resultado->taxaJuros);
    }

    public function test_aprova_com_taxa_reduzida_para_score_alto(): void
    {
        $resultado = (new AnaliseCreditoService())->avaliar(
            rendaMensal: 5000,
            score: 850,
            valorSolicitado: 10000,
        );

        $this->assertSame(StatusAnalise::APROVADO, $resultado->status);
        $this->assertSame(2.9, $resultado->taxaJuros);
        $this->assertSame(1123.33, $resultado->valorParcela);
        $this->assertNull($resultado->motivoRejeicao);
    }

    public function test_aprova_com_taxa_padrao_para_score_medio(): void
    {
        $resultado = (new AnaliseCreditoService())->avaliar(
            rendaMensal: 5000,
            score: 550,
            valorSolicitado: 5000,
        );

        $this->assertSame(StatusAnalise::APROVADO, $resultado->status);
        $this->assertSame(4.5, $resultado->taxaJuros);
        $this->assertSame(641.67, $resultado->valorParcela);
    }

    public function test_reprova_por_comprometimento_de_renda(): void
    {
        $resultado = (new AnaliseCreditoService())->avaliar(
            rendaMensal: 1600,
            score: 850,
            valorSolicitado: 20000,
        );

        $this->assertSame(StatusAnalise::REPROVADO, $resultado->status);
        $this->assertSame('Comprometimento de renda superior a 30%', $resultado->motivoRejeicao);
        $this->assertSame(2.9, $resultado->taxaJuros);
        $this->assertNotNull($resultado->valorParcela);
    }

    public function test_score_limite_400_e_aprovado_com_taxa_padrao(): void
    {
        $resultado = (new AnaliseCreditoService())->avaliar(
            rendaMensal: 5000,
            score: 400,
            valorSolicitado: 1000,
        );

        $this->assertSame(StatusAnalise::APROVADO, $resultado->status);
        $this->assertSame(4.5, $resultado->taxaJuros);
    }

    public function test_score_limite_700_e_aprovado_com_taxa_reduzida(): void
    {
        $resultado = (new AnaliseCreditoService())->avaliar(
            rendaMensal: 5000,
            score: 700,
            valorSolicitado: 1000,
        );

        $this->assertSame(StatusAnalise::APROVADO, $resultado->status);
        $this->assertSame(2.9, $resultado->taxaJuros);
    }
}
