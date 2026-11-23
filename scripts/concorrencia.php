<?php

/**
 * Dispara entradas e depois saídas simultâneas no mesmo compartimento e confere se o lock
 * pessimista (DECISOES.md, item 11) manteve o estoque consistente.
 *
 * Precisa de um servidor com vários workers, senão as requisições são atendidas em fila:
 *   PHP_CLI_SERVER_WORKERS=20 php artisan serve --no-reload
 *
 * Uso:
 *   php scripts/concorrencia.php [--base=http://127.0.0.1:8000/api] [--compartimento=1]
 *                                [--requisicoes=20] [--quantidade=50]
 *
 * Sai com código 0 se tudo bateu e 1 se encontrou inconsistência.
 */

$opts = getopt('', ['base::', 'compartimento::', 'requisicoes::', 'quantidade::']) + [
    'base' => 'http://127.0.0.1:8000/api',
    'compartimento' => '1',
    'requisicoes' => '20',
    'quantidade' => '50',
];

$base = rtrim($opts['base'], '/');
$compartimentoId = (int) $opts['compartimento'];
$n = (int) $opts['requisicoes'];
$qtd = (string) $opts['quantidade'];
$capacidade = '600'; // SECO
$falhas = [];

// ---------------------------------------------------------------------------
// Pré-condição: compartimento vazio e capaz de receber SECO hoje.
// ---------------------------------------------------------------------------

[$status, $body] = requisicao('GET', "{$base}/compartimentos");
if ($status !== 200) {
    sair("Não consegui consultar {$base}/compartimentos (HTTP {$status}). O servidor está rodando?");
}
$comp = array_values(array_filter($body['data'], fn ($c) => $c['id'] === $compartimentoId))[0] ?? null;
if ($comp === null) {
    sair("Compartimento {$compartimentoId} não existe.");
}
if ($comp['quantidadeAtual'] != 0 || ! in_array($comp['tipoAtual'], [null, 'SECO'], true)) {
    sair("{$comp['codigo']} precisa estar vazio e sem outro tipo (está com {$comp['quantidadeAtual']} de {$comp['tipoAtual']}).\n"
        .'Use outro --compartimento ou recrie o banco: php artisan migrate:fresh --seed');
}

$nome = 'Concorrencia '.date('His').'-'.random_int(1000, 9999);
$aceitasEsperadas = min($n, intdiv((int) $capacidade, (int) $qtd));

printf("%s: %d entradas simultâneas de %s kg de SECO (capacidade %s kg).\n\n", $comp['codigo'], $n, $qtd, $capacidade);

// ---------------------------------------------------------------------------
// Fase 1: entradas simultâneas.
// ---------------------------------------------------------------------------

$respostas = disparar(array_fill(0, $n, ['POST', "{$base}/ingredientes", [
    'nome' => $nome, 'tipo' => 'SECO', 'quantidade' => (float) $qtd,
    'compartimentoId' => $compartimentoId, 'responsavel' => 'concorrencia',
]]));
$resumo = resumir($respostas);
imprimir('Entradas', $resumo);

$ingredienteId = null;
foreach ($respostas as $r) {
    $ingredienteId ??= $r['status'] === 201 ? $r['body']['data']['id'] : null;
}
$saldo = saldoDoCompartimento($base, $compartimentoId);
$saldoEsperado = bcmul((string) $resumo['201'], $qtd, 3);

checar($resumo['5xx'] === 0, 'nenhuma resposta 5xx', $falhas);
checar($resumo['201'] === $aceitasEsperadas, "{$aceitasEsperadas} entradas aceitas (aceitas: {$resumo['201']})", $falhas);
checar(($resumo['erros']['CAPACIDADE_EXCEDIDA'] ?? 0) === $n - $aceitasEsperadas, ($n - $aceitasEsperadas).' rejeitadas com CAPACIDADE_EXCEDIDA', $falhas);
checar(bccomp($saldo, $saldoEsperado, 3) === 0, "saldo do compartimento = {$saldoEsperado} (lido: {$saldo})", $falhas);
checar(bccomp($saldo, $capacidade, 3) <= 0, "saldo não passa da capacidade de {$capacidade}", $falhas);
if ($ingredienteId !== null) {
    $qtdIngrediente = quantidadeDoIngrediente($base, $ingredienteId);
    checar(bccomp($qtdIngrediente, $saldo, 3) === 0, "ingrediente soma o mesmo que o compartimento ({$qtdIngrediente})", $falhas);
    checar(contarMovimentacoes($base, $ingredienteId, 'ENTRADA') === $resumo['201'], 'uma movimentação ENTRADA por entrada aceita', $falhas);
}

// ---------------------------------------------------------------------------
// Fase 2: saídas simultâneas do mesmo ingrediente.
// ---------------------------------------------------------------------------

if ($ingredienteId !== null) {
    $saidasEsperadas = min($n, (int) bcdiv($saldo, $qtd, 0));

    printf("\n%s: %d saídas simultâneas de %s kg (saldo %s kg).\n\n", $comp['codigo'], $n, $qtd, $saldo);

    $respostas = disparar(array_fill(0, $n, ['POST', "{$base}/ingredientes/{$ingredienteId}/saida", [
        'quantidade' => (float) $qtd, 'responsavel' => 'concorrencia',
    ]]));
    $resumo = resumir($respostas);
    imprimir('Saídas', $resumo);

    $saldoFinal = saldoDoCompartimento($base, $compartimentoId);
    $saldoFinalEsperado = bcsub($saldo, bcmul((string) $resumo['200'], $qtd, 3), 3);

    checar($resumo['5xx'] === 0, 'nenhuma resposta 5xx', $falhas);
    checar($resumo['200'] === $saidasEsperadas, "{$saidasEsperadas} saídas aceitas (aceitas: {$resumo['200']})", $falhas);
    checar(($resumo['erros']['ESTOQUE_INSUFICIENTE'] ?? 0) === $n - $saidasEsperadas, ($n - $saidasEsperadas).' rejeitadas com ESTOQUE_INSUFICIENTE', $falhas);
    checar(bccomp($saldoFinal, $saldoFinalEsperado, 3) === 0, "saldo final = {$saldoFinalEsperado} (lido: {$saldoFinal})", $falhas);
    checar(bccomp($saldoFinal, '0', 3) >= 0, 'saldo nunca fica negativo', $falhas);
    checar(bccomp(quantidadeDoIngrediente($base, $ingredienteId), $saldoFinal, 3) === 0, 'ingrediente e compartimento continuam iguais', $falhas);
    checar(contarMovimentacoes($base, $ingredienteId, 'SAIDA') === $resumo['200'], 'uma movimentação SAIDA por saída aceita', $falhas);
}

echo "\n".($falhas ? 'FALHOU: '.count($falhas)." verificação(ões).\n" : "OK: estoque consistente.\n");
exit($falhas ? 1 : 0);

// ---------------------------------------------------------------------------
// Funções
// ---------------------------------------------------------------------------

/** Dispara todas as requisições ao mesmo tempo (curl_multi) e devolve status e corpo. */
function disparar(array $reqs): array
{
    $mh = curl_multi_init();
    $handles = [];
    foreach ($reqs as $i => [$metodo, $url, $payload]) {
        $handles[$i] = curl($metodo, $url, $payload);
        curl_multi_add_handle($mh, $handles[$i]);
    }

    do {
        $st = curl_multi_exec($mh, $ativos);
        if ($ativos) {
            curl_multi_select($mh);
        }
    } while ($ativos && $st === CURLM_OK);

    $out = [];
    foreach ($handles as $h) {
        $out[] = [
            'status' => curl_getinfo($h, CURLINFO_RESPONSE_CODE),
            'body' => json_decode(curl_multi_getcontent($h), true),
        ];
        curl_multi_remove_handle($mh, $h);
    }
    curl_multi_close($mh);

    return $out;
}

function resumir(array $respostas): array
{
    $r = ['200' => 0, '201' => 0, '5xx' => 0, 'erros' => []];
    foreach ($respostas as $resp) {
        if (isset($r[(string) $resp['status']])) {
            $r[(string) $resp['status']]++;
        }
        if ($resp['status'] >= 500 || $resp['status'] === 0) {
            $r['5xx']++;
        }
        if (isset($resp['body']['error'])) {
            $r['erros'][$resp['body']['error']] = ($r['erros'][$resp['body']['error']] ?? 0) + 1;
        }
    }

    return $r;
}

function imprimir(string $titulo, array $r): void
{
    $erros = implode(', ', array_map(fn ($k, $v) => "{$v} {$k}", array_keys($r['erros']), $r['erros'])) ?: 'nenhum';
    printf("  %s: %d x 200, %d x 201, %d x 5xx. Erros: %s\n", $titulo, $r['200'], $r['201'], $r['5xx'], $erros);
}

function checar(bool $ok, string $descricao, array &$falhas): void
{
    echo ($ok ? '  [ok]    ' : '  [FALHA] ').$descricao."\n";
    if (! $ok) {
        $falhas[] = $descricao;
    }
}

function saldoDoCompartimento(string $base, int $id): string
{
    [, $body] = requisicao('GET', "{$base}/compartimentos");
    foreach ($body['data'] as $c) {
        if ($c['id'] === $id) {
            return number_format($c['quantidadeAtual'], 3, '.', '');
        }
    }
    sair("Compartimento {$id} sumiu.");
}

function quantidadeDoIngrediente(string $base, int $id): string
{
    [, $body] = requisicao('GET', "{$base}/ingredientes/{$id}");

    return number_format($body['data']['quantidade'], 3, '.', '');
}

function contarMovimentacoes(string $base, int $ingredienteId, string $operacao): int
{
    $total = 0;
    for ($pagina = 1; ; $pagina++) {
        [, $body] = requisicao('GET', "{$base}/historico?perPage=100&page={$pagina}");
        foreach ($body['data'] as $m) {
            $total += (int) ($m['ingredienteId'] === $ingredienteId && $m['operacao'] === $operacao);
        }
        if ($pagina >= $body['meta']['last_page']) {
            return $total;
        }
    }
}

function requisicao(string $metodo, string $url, ?array $payload = null): array
{
    $h = curl($metodo, $url, $payload);
    $body = curl_exec($h);
    $status = curl_getinfo($h, CURLINFO_RESPONSE_CODE);
    curl_close($h);

    return [$status, json_decode((string) $body, true)];
}

function curl(string $metodo, string $url, ?array $payload): CurlHandle
{
    $h = curl_init($url);
    curl_setopt_array($h, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
    ]);
    if ($payload !== null) {
        curl_setopt($h, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    return $h;
}

function sair(string $mensagem): never
{
    fwrite(STDERR, $mensagem."\n");
    exit(1);
}
