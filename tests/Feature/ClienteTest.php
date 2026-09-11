<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_clientes_cadastrados(): void
    {
        Cliente::factory()->count(3)->create();

        $response = $this->getJson('/api/clientes');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_cria_cliente_com_sucesso(): void
    {
        $dados = [
            'nome' => 'joaozinho da silva',
            'cpf' => '12345678901',
            'email' => 'joaozinho@gmail.com',
            'telefone' => '51999999999',
            'renda_mensal' => 1500.00,
        ];

        $response = $this->postJson('/api/clientes', $dados);

        $response->assertStatus(201);
        $response->assertJsonFragment(['cpf' => '12345678901']);

        $this->assertDatabaseHas('clientes', [
            'cpf' => '12345678901',
            'email' => 'joaozinho@gmail.com',
        ]);
    }

    public function test_nao_cria_cliente_com_dados_invalidos(): void
    {
        $response = $this->postJson('/api/clientes', [
            'nome' => '',
            'cpf' => '123',
            'email' => 'nao-e-email',
            'renda_mensal' => -10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nome', 'cpf', 'email', 'renda_mensal']);
    }

    public function test_nao_cria_cliente_com_cpf_duplicado(): void
    {
        Cliente::factory()->create(['cpf' => '12345678901']);

        $response = $this->postJson('/api/clientes', [
            'nome' => 'jorge pinto',
            'cpf' => '12345678901',
            'email' => 'jorgepinto@gmail.com',
            'renda_mensal' => 2000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cpf']);
    }

    public function test_nao_cria_cliente_com_email_duplicado(): void
    {
        Cliente::factory()->create(['email' => 'existeeste@gmail.com']);

        $response = $this->postJson('/api/clientes', [
            'nome' => 'Outro Cliente',
            'cpf' => '98765432100',
            'email' => 'existeeste@gmail.com',
            'renda_mensal' => 2000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_exibe_cliente_existente(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->getJson("/api/clientes/{$cliente->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['cpf' => $cliente->cpf]);
    }

    public function test_retorna_404_ao_exibir_cliente_inexistente(): void
    {
        $response = $this->getJson('/api/clientes/999999');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Cliente não encontrado.']);
    }

    public function test_atualiza_cliente_com_sucesso(): void
    {
        $cliente = Cliente::factory()->create(['nome' => 'Nome Antigo']);

        $response = $this->putJson("/api/clientes/{$cliente->id}", [
            'nome' => 'Nome Novo',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'Nome Novo']);

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'nome' => 'Nome Novo',
        ]);
    }

    public function test_atualiza_cliente_sem_alterar_cpf_nao_falha_por_duplicidade(): void
    {
        $cliente = Cliente::factory()->create(['cpf' => '11122233344']);

        $response = $this->putJson("/api/clientes/{$cliente->id}", [
            'cpf' => '11122233344',
            'nome' => 'Nome Atualizado',
        ]);

        $response->assertStatus(200);
    }

    public function test_nao_atualiza_cliente_para_cpf_de_outro_cliente(): void
    {
        Cliente::factory()->create(['cpf' => '11122233344']);
        $cliente = Cliente::factory()->create(['cpf' => '55566677788']);

        $response = $this->putJson("/api/clientes/{$cliente->id}", [
            'cpf' => '11122233344',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cpf']);
    }

    public function test_retorna_404_ao_atualizar_cliente_inexistente(): void
    {
        $response = $this->putJson('/api/clientes/999999', [
            'nome' => 'Irineu',
        ]);

        $response->assertStatus(404);
    }

    public function test_remove_cliente_com_sucesso(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->deleteJson("/api/clientes/{$cliente->id}");

        $response->assertStatus(204);
        $response->assertNoContent();

        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
    }

    public function test_retorna_404_ao_remover_cliente_inexistente(): void
    {
        $response = $this->deleteJson('/api/clientes/999999');

        $response->assertStatus(404);
    }
}
