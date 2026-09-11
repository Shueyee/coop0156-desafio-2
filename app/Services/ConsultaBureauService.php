<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Encapsula a chamada HTTP ao Bureau de Crédito externo (mockado).
 *
 * Nenhuma falha de comunicação (timeout, erro 500, resposta malformada)
 * deve escapar como exception pra fora daqui - tudo vira um
 * ConsultaBureauResultado de falha, e quem chamou decide o que fazer.
 */
class ConsultaBureauService
{
    public function consultar(string $cpf): ConsultaBureauResultadoDTO
    {
        $url = rtrim(config('services.score_bureau.url'), '/').'/'.$cpf;
        $timeout = config('services.score_bureau.timeout');

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (ConnectionException $e) {
            return ConsultaBureauResultadoDTO::falha(
                'Não foi possível conectar ao Bureau de Crédito. Tente novamente mais tarde.'
            );
        }

        if ($response->failed()) {
            return ConsultaBureauResultadoDTO::falha(
                'O Bureau de Crédito retornou um erro ao consultar o score.'
            );
        }

        $score = $response->json('score');

        if (! is_int($score)) {
            return ConsultaBureauResultadoDTO::falha(
                'O Bureau de Crédito retornou uma resposta em formato inesperado.'
            );
        }

        return ConsultaBureauResultadoDTO::sucesso($score);
    }
}
