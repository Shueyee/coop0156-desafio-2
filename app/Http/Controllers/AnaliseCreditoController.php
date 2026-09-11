<?php

namespace App\Http\Controllers;

use App\Enums\StatusAnalise;
use App\Http\Requests\SolicitarAnaliseCreditoRequest;
use App\Models\AnaliseCredito;
use App\Models\Cliente;
use App\Services\AnaliseCreditoService;
use App\Services\ConsultaBureauService;
use Illuminate\Http\Response;

class AnaliseCreditoController extends Controller
{
    /**
     * Solicita uma nova análise de crédito.
     *
     * POST /api/analise-credito
     *
     * Campos esperados no body (JSON):
     *  - nome: string, obrigatório
     *  - cpf: string, obrigatório (11 dígitos)
     *  - renda_mensal: numeric, obrigatório
     *  - tipo_credito: string, obrigatório (pessoal | imobiliario | automotivo)
     *  - valor_solicitado: numeric, obrigatório
     *
     * Fluxo esperado:
     *  1. Validar os dados de entrada.
     *  2. Persistir a análise no banco com status 'pendente'.
     *  3. Consultar a API do Bureau de Crédito (GET /api/mock/bureau/{cpf}) via Http::.
     *  4. Tratar falhas de comunicação com o Bureau (timeout, HTTP 500, resposta malformada).
     *  5. Aplicar as regras de negócio (renda mínima, faixas de score, comprometimento de renda).
     *  6. Atualizar e retornar a análise persistida com o resultado final.
     *
     * @param  \App\Http\Requests\SolicitarAnaliseCreditoRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function solicitar(
        SolicitarAnaliseCreditoRequest $request,
        ConsultaBureauService $consultaBureauService,
        AnaliseCreditoService $analiseCreditoService,
    ) {
        $dados = $request->validated();

        $cliente = Cliente::firstOrCreate(
            ['cpf' => $dados['cpf']],
            [
                'nome' => $dados['nome'],
                'renda_mensal' => $dados['renda_mensal'],
                // A solicitação de análise não coleta e-mail, mas a tabela clientes exige um
                // valor único. Gera um placeholder determinístico baseado no CPF.
                'email' => $dados['cpf'].'@sememail.coop0156.local',
            ]
        );

        $analise = AnaliseCredito::create([
            'cliente_id' => $cliente->id,
            'cpf' => $dados['cpf'],
            'nome' => $dados['nome'],
            'renda_mensal' => $dados['renda_mensal'],
            'tipo_credito' => $dados['tipo_credito'],
            'valor_solicitado' => $dados['valor_solicitado'],
            'status' => StatusAnalise::PENDENTE,
        ]);

        $resultadoBureau = $consultaBureauService->consultar($dados['cpf']);

        if (! $resultadoBureau->sucesso) {
            $analise->update(['motivo_rejeicao' => $resultadoBureau->motivoFalha]);

            return response()->json([
                'message' => 'Não foi possível concluir a análise no momento. Tente novamente mais tarde.',
                'analise_id' => $analise->id,
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $resultado = $analiseCreditoService->avaliar(
            rendaMensal: (float) $dados['renda_mensal'],
            score: $resultadoBureau->score,
            valorSolicitado: (float) $dados['valor_solicitado'],
        );

        $analise->update([
            'status' => $resultado->status,
            'score' => $resultadoBureau->score,
            'taxa_juros' => $resultado->taxaJuros,
            'valor_parcela' => $resultado->valorParcela,
            'motivo_rejeicao' => $resultado->motivoRejeicao,
        ]);

        return response()->json($analise->fresh(), Response::HTTP_CREATED);
    }

    /**
     * Confirma a contratação de uma análise de crédito aprovada.
     *
     * POST /api/analise-credito/{id}/contratar
     *
     * Fluxo esperado:
     *  1. Buscar a análise pelo ID (retornar 404 se não encontrada).
     *  2. Verificar se o status é 'aprovado' (retornar 422 se não for).
     *  3. Atualizar o status para 'contratado'.
     *  4. Retornar confirmação de sucesso.
     *
     * ⭐ DIFERENCIAL OPCIONAL: Em vez de atualizar diretamente para 'contratado',
     *    atualize para 'processando_contratacao' e dispare o Job ProcessarContratacaoJob
     *    para a fila. O Job ficará responsável por finalizar e atualizar para 'contratado'.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function contratar($id)
    {
        $analise = AnaliseCredito::find($id);

        if (! $analise) {
            return response()->json(['message' => 'Análise de crédito não encontrada.'], Response::HTTP_NOT_FOUND);
        }

        if ($analise->status !== StatusAnalise::APROVADO) {
            return response()->json([
                'message' => 'Somente análises aprovadas podem ser contratadas.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $analise->update(['status' => StatusAnalise::CONTRATADO]);

        return response()->json($analise, Response::HTTP_OK);
    }
}
