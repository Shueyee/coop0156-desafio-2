<?php

namespace Tests\Unit;

use App\Services\ConsultaBureauService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsultaBureauServiceTest extends TestCase
{
    public function test_retorna_sucesso_com_score_baixo(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['cpf' => '11111111111', 'score' => 150, 'situacao' => 'ativo']),
        ]);

        $resultado = (new ConsultaBureauService())->consultar('11111111111');

        $this->assertTrue($resultado->sucesso);
        $this->assertSame(150, $resultado->score);
        $this->assertNull($resultado->motivoFalha);
    }

    public function test_retorna_sucesso_com_score_medio(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['score' => 550]),
        ]);

        $resultado = (new ConsultaBureauService())->consultar('22222222222');

        $this->assertTrue($resultado->sucesso);
        $this->assertSame(550, $resultado->score);
    }

    public function test_retorna_sucesso_com_score_alto(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['score' => 850]),
        ]);

        $resultado = (new ConsultaBureauService())->consultar('33333333333');

        $this->assertTrue($resultado->sucesso);
        $this->assertSame(850, $resultado->score);
    }

    public function test_retorna_falha_quando_bureau_da_erro_500(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['error' => 'Erro interno na comunicação com o provedor de score.'], 500),
        ]);

        $resultado = (new ConsultaBureauService())->consultar('44444444444');

        $this->assertFalse($resultado->sucesso);
        $this->assertNull($resultado->score);
        $this->assertNotNull($resultado->motivoFalha);
    }

    public function test_retorna_falha_quando_bureau_da_timeout(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $resultado = (new ConsultaBureauService())->consultar('55555555555');

        $this->assertFalse($resultado->sucesso);
        $this->assertNull($resultado->score);
        $this->assertNotNull($resultado->motivoFalha);
    }

    public function test_retorna_falha_quando_bureau_retorna_json_sem_score(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['cpf' => '66666666666', 'status_bureau' => 'ok']),
        ]);

        $resultado = (new ConsultaBureauService())->consultar('66666666666');

        $this->assertFalse($resultado->sucesso);
        $this->assertNull($resultado->score);
    }

    public function test_retorna_sucesso_com_score_padrao(): void
    {
        Http::fake([
            '*/api/mock/bureau/*' => Http::response(['score' => 600]),
        ]);

        $resultado = (new ConsultaBureauService())->consultar('99999999999');

        $this->assertTrue($resultado->sucesso);
        $this->assertSame(600, $resultado->score);
    }
}
