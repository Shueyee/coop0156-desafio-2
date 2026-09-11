<?php

namespace Tests\Feature;

use App\Enums\StatusAnalise;
use App\Models\AnaliseCredito;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnaliseCreditoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nome' => 'Maria Oliveira',
            'cpf' => '12345678903',
            'renda_mensal' => 5000.00,
            'tipo_credito' => 'pessoal',
            'valor_solicitado' => 10000.00,
        ], $overrides);
    }

    private function fakeBureauSucesso(int $score): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['score' => $score, 'situacao' => 'ativo']),
        ]);
    }

    // --- Fluxo de sucesso ---

    public function test_cria_cliente_automaticamente_ao_solicitar_com_cpf_novo(): void
    {
        $this->fakeBureauSucesso(850);

        $this->assertDatabaseMissing('clientes', ['cpf' => '12345678903']);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(201);

        $cliente = Cliente::where('cpf', '12345678903')->first();
        $this->assertNotNull($cliente, 'O cliente deveria ter sido criado automaticamente.');

        $this->assertDatabaseHas('analises_credito', [
            'cpf' => '12345678903',
            'cliente_id' => $cliente->id,
        ]);
    }

    public function test_aprova_com_taxa_reduzida_para_score_alto(): void
    {
        $this->fakeBureauSucesso(850);

        $response = $this->postJson('/api/analise-credito', $this->payload([
            'renda_mensal' => 5000.00,
            'valor_solicitado' => 10000.00,
        ]));

        $response->assertStatus(201);

        $analise = AnaliseCredito::find($response->json('id'));

        $this->assertSame(StatusAnalise::APROVADO, $analise->status);
        $this->assertSame(850, $analise->score);
        $this->assertEqualsWithDelta(2.9, (float) $analise->taxa_juros, 0.001);
        // Exemplo do README: 10.000 a 2,9% => parcela de 1.123,33
        $this->assertEqualsWithDelta(1123.33, (float) $analise->valor_parcela, 0.01);
        $this->assertNull($analise->motivo_rejeicao);
    }

    public function test_aprova_com_taxa_padrao_para_score_medio(): void
    {
        $this->fakeBureauSucesso(550);

        $response = $this->postJson('/api/analise-credito', $this->payload([
            'renda_mensal' => 5000.00,
            'valor_solicitado' => 5000.00,
        ]));

        $response->assertStatus(201);

        $analise = AnaliseCredito::find($response->json('id'));

        $this->assertSame(StatusAnalise::APROVADO, $analise->status);
        $this->assertEqualsWithDelta(4.5, (float) $analise->taxa_juros, 0.001);
    }

    // --- Reprovações ---

    public function test_reprova_por_renda_mensal_insuficiente(): void
    {
        $this->fakeBureauSucesso(600); // score não importa, renda barra antes

        $response = $this->postJson('/api/analise-credito', $this->payload([
            'renda_mensal' => 1000.00,
        ]));

        $response->assertStatus(201);

        $analise = AnaliseCredito::find($response->json('id'));

        $this->assertSame(StatusAnalise::REPROVADO, $analise->status);
        $this->assertSame('Renda mínima insuficiente', $analise->motivo_rejeicao);
    }

    public function test_reprova_por_score_baixo(): void
    {
        $this->fakeBureauSucesso(150);

        $response = $this->postJson('/api/analise-credito', $this->payload([
            'renda_mensal' => 3000.00,
        ]));

        $response->assertStatus(201);

        $analise = AnaliseCredito::find($response->json('id'));

        $this->assertSame(StatusAnalise::REPROVADO, $analise->status);
        $this->assertSame('Score de crédito muito baixo', $analise->motivo_rejeicao);
    }

    public function test_reprova_por_comprometimento_de_renda(): void
    {
        $this->fakeBureauSucesso(850);

        $response = $this->postJson('/api/analise-credito', $this->payload([
            'renda_mensal' => 1600.00,
            'valor_solicitado' => 20000.00,
        ]));

        $response->assertStatus(201);

        $analise = AnaliseCredito::find($response->json('id'));

        $this->assertSame(StatusAnalise::REPROVADO, $analise->status);
        $this->assertSame('Comprometimento de renda superior a 30%', $analise->motivo_rejeicao);
    }

    // --- Resiliência (não pode virar 500) ---

    public function test_falha_do_bureau_com_erro_500_retorna_resposta_limpa(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['error' => 'Erro interno'], 500),
        ]);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(503);
        $response->assertJsonStructure(['message', 'analise_id']);

        $analise = AnaliseCredito::find($response->json('analise_id'));
        $this->assertSame(StatusAnalise::PENDENTE, $analise->status);
        $this->assertNotNull($analise->motivo_rejeicao);
    }

    public function test_falha_do_bureau_por_timeout_retorna_resposta_limpa(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(503);
    }

    public function test_falha_do_bureau_por_resposta_malformada_retorna_resposta_limpa(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['cpf' => '12345678903', 'status_bureau' => 'ok']),
        ]);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(503);
    }

    // --- Validação ---

    public function test_nao_solicita_analise_com_dados_invalidos(): void
    {
        $response = $this->postJson('/api/analise-credito', [
            'nome' => '',
            'cpf' => '123',
            'renda_mensal' => -1,
            'tipo_credito' => 'inexistente',
            'valor_solicitado' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nome', 'cpf', 'renda_mensal', 'tipo_credito', 'valor_solicitado']);
    }

    // --- Contratação ---

    public function test_contrata_analise_aprovada_com_sucesso(): void
    {
        $analise = AnaliseCredito::factory()->aprovada()->create();

        $response = $this->postJson("/api/analise-credito/{$analise->id}/contratar");

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'contratado']);

        $this->assertDatabaseHas('analises_credito', [
            'id' => $analise->id,
            'status' => 'contratado',
        ]);
    }

    public function test_nao_contrata_analise_que_nao_esta_aprovada(): void
    {
        $analise = AnaliseCredito::factory()->create(); // status pendente por padrão

        $response = $this->postJson("/api/analise-credito/{$analise->id}/contratar");

        $response->assertStatus(422);
    }

    public function test_retorna_404_ao_contratar_analise_inexistente(): void
    {
        $response = $this->postJson('/api/analise-credito/999999/contratar');

        $response->assertStatus(404);
    }
}
