# Notas de Entrega

## O que foi implementado

Todos os pontos obrigatórios do desafio foram concluídos:

- [x] **CRUD de Clientes** (`ClienteController`) — as 5 operações, com `Form Request` de validação e retorno de erros claros (404 quando não encontrado, 422 nas validações).
- [x] **Análise de crédito** (`AnaliseCreditoController::solicitar`) — localiza/cadastra o cliente pelo CPF, persiste a análise, consulta o Bureau e aplica as regras de elegibilidade.
- [x] **Resiliência na integração com o Bureau** — timeout, erro 500 e resposta malformada são tratados sem devolver 500 pro cliente da API (retorna 503 com mensagem clara).
- [x] **Contratação** (`AnaliseCreditoController::contratar`) — só avança se a análise estiver `aprovado`.
- [x] **Tela de simulação e contratação** — JavaScript das duas telas (`analise.blade.php` e `simulacao.blade.php`) implementado.
- [x] **Testes automatizados** — 42 testes no total (`ClienteTest`, `AnaliseCreditoTest`, e testes unitários dos Services), todos passando.

## O que ficou de fora

- [ ] **Diferencial de filas** (`ProcessarContratacaoJob`) — não implementei. Sendo honesto, esse ponto avançado eu não teria conseguido desenvolver sozinho no meu nível atual — precisaria pedir bastante ajuda pra fazer direito.
- [ ] Feedback de erro no frontend é um banner simples — funcional, mas sem grandes refinamentos de UX.

## Decisões técnicas

- **Regra de negócio fora do Controller:** duas classes de Service — `ConsultaBureauService` (só a chamada HTTP e tratamento de falha) e `AnaliseCreditoService` (só as regras de elegibilidade, sem I/O). Cada uma tem seu próprio DTO de retorno (`ConsultaBureauResultadoDTO`, `ResultadoAnaliseCreditoDTO`) em vez de lançar exception pra um cenário que é esperado no domínio (Bureau fora do ar, análise reprovada).
- **Ordem do fluxo em `solicitar`:** segue o passo a passo literal do README — persiste a análise como `pendente` antes de consultar o Bureau, e só aplica as regras (incluindo renda mínima) depois de ter o score.
- **Cliente criado automaticamente:** como a solicitação de análise não coleta e-mail (só nome, CPF e renda), mas a tabela `clientes` exige e-mail único, uso um e-mail-placeholder determinístico baseado no CPF pra clientes criados por esse fluxo.
- **Bug de scaffold corrigido:** o `.env.example` original apontava o Bureau para `http://localhost:8000/...`, mas isso não funciona rodando via Sail (a aplicação serve na porta 80 dentro do container) — corrigido para `http://localhost/...`, consistente com a Opção A (recomendada) de execução.

## Transparência sobre o uso de IA no desenvolvimento

No meu dia a dia trabalho com Yii, então conceitos como MVC, ORM, validação e injeção de dependência já eram familiares pra mim — mas o jeito como o Laravel resolve cada um desses pontos é bem diferente do que estou acostumado. Por isso, em vários momentos do desenvolvimento precisei recorrer a uma IA (Claude) pra pesquisar qual seria a forma correta e idiomática de fazer algo em Laravel especificamente, já que a base conceitual eu já tinha, mas não o conhecimento específico do framework.

Usei o Claude como ferramenta de apoio ao longo de todo o desenvolvimento, e sou transparente que, com as habilidades que tenho hoje, não conseguiria ter desenvolvido este projeto sozinho, sem esse apoio.
