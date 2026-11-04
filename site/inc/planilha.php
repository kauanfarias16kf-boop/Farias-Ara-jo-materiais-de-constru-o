<?php
// Leitura das linhas coladas do Excel (diárias e despesas do dia) e gravação no sistema.
declare(strict_types=1);
require_once __DIR__ . '/painel.php';

// nomes escritos de outro jeito na planilha => nome no cadastro
const FA_APELIDOS = ['edmar' => 'Egmar'];
// colunas de despesa reconhecidas => categoria do sistema
const FA_COLUNAS_DESPESA = ['combustivel' => 'Combustível', 'cafe da manha' => 'Café da manhã', 'cafe' => 'Café da manhã', 'almoco' => 'Almoço', 'janta' => 'Outros', 'frete' => 'Transporte e frete'];
const FA_MARCA_PLANILHA = 'Lançado da planilha';

function sem_acento(string $s): string
{
    $s = mb_strtolower(trim($s));
    return strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
}

function numero_planilha(string $s): float
{
    $s = trim(str_replace(['R$', ' ', "\u{a0}"], '', $s));
    if ($s === '' || $s === '-') return 0.0;
    if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s) || str_contains($s, ',')) $s = str_replace(['.', ','], ['', '.'], $s);
    return is_numeric($s) ? (float)$s : NAN;
}

/** Lê o texto colado. Devolve colunas, dias e avisos, sem gravar nada. */
function ler_planilha(string $texto): array
{
    $linhas = preg_split('/\r\n|\r|\n/', trim($texto));
    $avisos = [];
    if (!$linhas || count($linhas) < 2) return ['erro' => 'Cole o cabeçalho (Data, nomes, despesas) e pelo menos uma linha de dia.'];
    $cab = explode("\t", array_shift($linhas));
    if (count($cab) < 2) return ['erro' => 'Não achei as colunas. Copie direto do Excel, com o cabeçalho, para as colunas virem separadas.'];

    $pdo = db();
    $cadastro = [];
    foreach ($pdo->query('SELECT id, nome, funcao, diaria FROM funcionarios') as $f) $cadastro[sem_acento($f['nome'])] = $f;

    $cols = [];
    foreach ($cab as $i => $nome) {
        $n = sem_acento($nome);
        if ($i === 0 || $n === '') continue;
        if (in_array($n, ['valor', 'total', 'valor do dia'], true)) { $cols[$i] = ['tipo' => 'valor']; continue; }
        if (isset(FA_COLUNAS_DESPESA[$n])) { $cols[$i] = ['tipo' => 'despesa', 'categoria' => FA_COLUNAS_DESPESA[$n], 'rotulo' => trim($nome)]; continue; }
        $certo = FA_APELIDOS[$n] ?? trim($nome);
        $f = $cadastro[sem_acento($certo)] ?? null;
        $cols[$i] = ['tipo' => 'pessoa', 'nome' => $f['nome'] ?? $certo, 'rotulo' => trim($nome), 'id' => $f ? (int)$f['id'] : 0,
            'funcao' => $f['funcao'] ?? null, 'diaria' => $f ? (float)$f['diaria'] : 0.0, 'mestre' => ($f['funcao'] ?? '') === 'Mestre de obra'];
        if ($certo !== trim($nome)) $avisos[] = 'A coluna "' . trim($nome) . '" foi lançada em ' . $certo . '.';
    }

    $dias = [];
    foreach ($linhas as $k => $l) {
        if (trim($l) === '') continue;
        $c = explode("\t", $l);
        if (!preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', trim($c[0]), $m)) { $avisos[] = 'Linha ' . ($k + 2) . ' ignorada: a data "' . trim($c[0]) . '" não está no formato dia/mês/ano.'; continue; }
        $ano = strlen($m[3]) === 2 ? 2000 + (int)$m[3] : (int)$m[3];
        if (!checkdate((int)$m[2], (int)$m[1], $ano)) { $avisos[] = 'Data inválida: ' . trim($c[0]) . '.'; continue; }
        $data = sprintf('%04d-%02d-%02d', $ano, $m[2], $m[1]);
        $dia = ['pessoas' => [], 'despesas' => [], 'valor' => null];
        foreach ($cols as $i => $col) {
            $v = numero_planilha($c[$i] ?? '');
            if (is_nan($v)) { $avisos[] = data_br($data) . ': valor "' . trim($c[$i]) . '" não é um número e ficou de fora.'; continue; }
            if ($col['tipo'] === 'valor') { $dia['valor'] = ($c[$i] ?? '') === '' ? null : $v; continue; }
            if ($v <= 0) continue;
            if ($col['tipo'] === 'despesa') $dia['despesas'][$col['categoria']] = ($dia['despesas'][$col['categoria']] ?? 0) + $v;
            else $dia['pessoas'][$i] = $v;
        }
        $dias[$data] = $dia;
    }
    ksort($dias);

    // diária de quem ainda não tem cadastro: o valor mais comum na planilha
    foreach ($cols as $i => &$col) {
        if ($col['tipo'] !== 'pessoa' || $col['id']) continue;
        $vals = array_count_values(array_map('strval', array_filter(array_map(fn($d) => $d['pessoas'][$i] ?? null, $dias))));
        arsort($vals);
        $col['diaria'] = $vals ? (float)array_key_first($vals) : 0;
        $col['funcao'] = $col['diaria'] >= 500 ? 'Mestre de obra' : ($col['diaria'] >= 150 ? 'Pedreiro' : 'Ajudante');
        $col['novo'] = true;
    }
    unset($col);

    // conferência de cada linha com a coluna Valor (com e sem o mestre de obra, como a planilha costuma somar)
    foreach ($dias as $data => &$d) {
        $tudo = array_sum($d['pessoas']) + array_sum($d['despesas']);
        $mestre = 0;
        foreach ($d['pessoas'] as $i => $v) if ($cols[$i]['mestre'] || ($cols[$i]['funcao'] ?? '') === 'Mestre de obra') $mestre += $v;
        $d['confere'] = $d['valor'] === null || abs($d['valor'] - $tudo) < .01 || abs($d['valor'] - ($tudo - $mestre)) < .01;
        $d['soma'] = $mestre ? $tudo - $mestre : $tudo;
        if (!$d['confere']) $avisos[] = data_br($data) . ': a linha soma ' . brl($d['soma']) . ', mas a coluna Valor diz ' . brl($d['valor']) . '. Lancei as diárias como estão marcadas.';
        $f = fechamento_da_data($data);
        $d['fechada'] = $f ? quinzena_rotulo($f['inicio'], $f['fim']) : null;
    }
    unset($d);

    return ['cols' => $cols, 'dias' => $dias, 'avisos' => $avisos];
}

/** Resumo por quinzena para a prévia. */
function resumo_planilha(array $p): array
{
    $q = [];
    foreach ($p['dias'] as $data => $d) {
        [$i, $f] = quinzena($data);
        $r = &$q[$i];
        $r ??= ['inicio' => $i, 'fim' => $f, 'pessoas' => [], 'despesas' => [], 'dias' => 0, 'fechada' => $d['fechada']];
        $r['dias']++;
        foreach ($d['pessoas'] as $c => $v) {
            $col = $p['cols'][$c];
            $x = &$r['pessoas'][$col['nome']];
            $x ??= ['funcao' => $col['funcao'], 'diaria' => $col['diaria'], 'novo' => !empty($col['novo']), 'dias' => 0.0, 'total' => 0.0];
            $x['dias'] += $col['diaria'] > 0 ? min(1, $v / $col['diaria']) : 1;
            $x['total'] += $v;
            unset($x);
        }
        foreach ($d['despesas'] as $cat => $v) $r['despesas'][$cat] = ($r['despesas'][$cat] ?? 0) + $v;
        unset($r);
    }
    return $q;
}

/** Grava: cada dia colado substitui as diárias e as despesas de planilha daquele dia. Dias de folha já fechada não mudam. */
function gravar_planilha(array $p): array
{
    $pdo = db();
    $pdo->beginTransaction();
    $ids = [];
    $n = ['dias' => 0, 'diarias' => 0, 'despesas' => 0, 'novos' => [], 'pulados' => 0];
    foreach ($p['cols'] as $i => $c) {
        if ($c['tipo'] !== 'pessoa') continue;
        if (!$c['id']) {
            $pdo->prepare('INSERT INTO funcionarios(nome, funcao, diaria, ativo) VALUES(?,?,?,1)')->execute([$c['nome'], $c['funcao'], $c['diaria']]);
            $c['id'] = (int)$pdo->lastInsertId();
            $n['novos'][] = $c['nome'];
        }
        $ids[$i] = $c;
    }
    $apagaD = $pdo->prepare('DELETE FROM diarias WHERE data = ?');
    $poeD = $pdo->prepare('INSERT INTO diarias(funcionario_id, data, fracao, valor, extra, obs) VALUES(?,?,?,?,?,?)');
    $apagaX = $pdo->prepare('DELETE FROM despesas WHERE data = ? AND descricao = ?');
    $poeX = $pdo->prepare('INSERT INTO despesas(data, categoria, descricao, valor) VALUES(?,?,?,?)');
    foreach ($p['dias'] as $data => $d) {
        if ($d['fechada']) { $n['pulados']++; continue; }
        $n['dias']++;
        $apagaD->execute([$data]);
        foreach ($d['pessoas'] as $i => $v) {
            $c = $ids[$i];
            $diaria = $c['diaria'] > 0 ? $c['diaria'] : $v;
            $fracao = min(1, round($v / $diaria, 4));
            $extra = max(0, round($v - $diaria, 2));
            $poeD->execute([$c['id'], $data, $fracao, $diaria, $extra, null]);
            $n['diarias']++;
        }
        $apagaX->execute([$data, FA_MARCA_PLANILHA]);
        foreach ($d['despesas'] as $cat => $v) { $poeX->execute([$data, $cat, FA_MARCA_PLANILHA, $v]); $n['despesas']++; }
    }
    $pdo->commit();
    return $n;
}
