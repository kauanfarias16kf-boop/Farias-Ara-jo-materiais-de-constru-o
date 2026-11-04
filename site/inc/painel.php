<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

const FA_MENU = [
    '' => [
        'index' => ['Início', 'M3 11l9-7 9 7v9a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1z'],
    ],
    'Comercial' => [
        'pedidos' => ['Pedidos', 'M4 4h16v12H7l-3 3zM8 9h8M8 12h5'],
        'clientes' => ['Clientes', 'M16 19v-1a4 4 0 00-4-4H7a4 4 0 00-4 4v1M9.5 10a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM21 19v-1a4 4 0 00-3-3.9M16 3.1a3.5 3.5 0 010 6.8'],
        'orcamentos' => ['Orçamentos', 'M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6'],
    ],
    'Obra' => [
        'obras' => ['Obras', 'M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6'],
        'diarias' => ['Diárias', 'M4 5h16v16H4zM4 10h16M9 3v4M15 3v4M8.5 15l2.5 2.5 4.5-5'],
        'despesas' => ['Despesas', 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h3'],
        'galeria' => ['Galeria', 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M15 9.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z'],
    ],
    'Equipe' => [
        'equipe' => ['Funcionários', 'M4 15a8 8 0 0116 0v2H4zM12 7V4M8 8V6M16 8V6M2 17h20'],
        'fechamento' => ['Fechamento', 'M3 6h18v12H3zM12 15a3 3 0 100-6 3 3 0 000 6zM6 9v.01M18 15v.01'],
    ],
];
const FA_ATALHOS = ['index', 'diarias', 'despesas', 'obras'];

function icone(string $d, int $s = 20): string
{
    return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
}

function item_menu(string $k): array
{
    foreach (FA_MENU as $g) if (isset($g[$k])) return $g[$k];
    return ['', ''];
}

function topo(string $titulo, string $ativo = '', ?array $u = null): void
{
    $u ??= usuario();
    $aviso = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);
    $novos = 0;
    if ($u) $novos = (int)db()->query("SELECT COUNT(*) FROM pedidos WHERE status = 'novo'")->fetchColumn();
    $selo = fn($k) => ($k === 'pedidos' && $novos) ? '<b class="selo" aria-label="' . $novos . ' novos">' . $novos . '</b>' : '';
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#17181b">
<title><?= h($titulo) ?> · Painel Farias Araújo</title>
<script>try{var t=localStorage.getItem('fa-tema');if(t)document.documentElement.dataset.tema=t}catch(e){}</script>
<link rel="icon" href="/assets/img/logo-marca.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600&family=IBM+Plex+Mono:wght@400;500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/painel.css?v=3">
</head>
<body class="painel<?= $u ? ' com-menu' : '' ?>">
<a class="pular" href="#main">Pular para o conteúdo</a>
<?php if ($u): ?>
<aside class="lateral" id="lateral" aria-label="Menu do painel">
  <a class="marca" href="index.php"><img src="/assets/img/logo.webp" alt="Farias Araújo" width="118" height="77"></a>
  <nav class="menu">
    <?php foreach (FA_MENU as $grupo => $itens): ?>
      <?php if ($grupo): ?><span class="grupo"><?= $grupo ?></span><?php endif ?>
      <?php foreach ($itens as $k => [$nome, $ic]): ?>
        <a href="<?= $k ?>.php" class="<?= $ativo === $k ? 'ativo' : '' ?>" <?= $ativo === $k ? 'aria-current="page"' : '' ?>><?= icone($ic) ?><span><?= $nome ?></span><?= $selo($k) ?></a>
      <?php endforeach ?>
    <?php endforeach ?>
  </nav>
  <div class="conta">
    <button type="button" class="btn btn-pq tema" data-tema-botao><span class="so-escuro">Modo claro</span><span class="so-claro">Modo escuro</span></button>
    <a href="conta.php"><?= icone('M12 12a4 4 0 100-8 4 4 0 000 8zM4 21a8 8 0 0116 0', 18) ?><?= h($u['nome']) ?></a>
    <a href="/" target="_blank" rel="noopener"><?= icone('M14 4h6v6M20 4l-9 9M18 14v6H4V6h6', 18) ?>Ver o site</a>
    <a href="sair.php"><?= icone('M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10', 18) ?>Sair</a>
  </div>
</aside>
<nav class="atalhos" aria-label="Atalhos">
  <?php foreach (FA_ATALHOS as $k): [$nome, $ic] = item_menu($k); ?>
    <a href="<?= $k ?>.php" class="<?= $ativo === $k ? 'ativo' : '' ?>"><?= icone($ic, 22) ?><span><?= $nome ?></span></a>
  <?php endforeach ?>
  <button type="button" data-abre-menu aria-controls="lateral" aria-expanded="false"><?= icone('M4 7h16M4 12h16M4 17h16', 22) ?><span>Menu</span><?= $novos ? '<b class="selo">' . $novos . '</b>' : '' ?></button>
</nav>
<div class="veu" data-fecha-menu hidden></div>
<?php endif ?>
<main id="main" tabindex="-1" class="conteudo">
<?php if ($aviso): ?><div class="aviso aviso-<?= h($aviso[1]) ?>" role="status"><?= h($aviso[0]) ?></div><?php endif ?>
<?php
}

function rodape(): void
{
    ?>
</main>
<script src="/assets/js/painel.js?v=3" defer></script>
</body>
</html>
<?php
}

function rotulo_status(string $s): string
{
    $mapa = [
        'novo' => 'Novo', 'contatado' => 'Contatado', 'orcado' => 'Orçado', 'fechado' => 'Fechado', 'perdido' => 'Perdido',
        'planejada' => 'Planejada', 'em_andamento' => 'Em andamento', 'pausada' => 'Pausada', 'concluida' => 'Concluída',
        'pendente' => 'Pendente', 'andamento' => 'Em andamento',
        'rascunho' => 'Rascunho', 'enviado' => 'Enviado', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado',
    ];
    return '<span class="st st-' . h($s) . '">' . h($mapa[$s] ?? $s) . '</span>';
}

function opcoes(array $lista, ?string $atual, bool $chaves = false): string
{
    $o = '';
    foreach ($lista as $k => $v) {
        $val = $chaves ? (string)$k : (string)$v;
        $o .= '<option value="' . h($val) . '"' . ($val === (string)$atual ? ' selected' : '') . '>' . h((string)$v) . '</option>';
    }
    return $o;
}

function total_orcamento(int $id): float
{
    $st = db()->prepare('SELECT COALESCE(SUM(quantidade * valor_unit), 0) FROM orcamento_itens WHERE orcamento_id = ?');
    $st->execute([$id]);
    return (float)$st->fetchColumn();
}

/** Período de pagamento (ciclo de 15 dias corridos) que contém a data. */
function quinzena(string $data): array
{
    $t = strtotime($data) ?: time();
    $base = strtotime(FA_CICLO_INICIO);
    $n = (int)floor((($t - $base) / 86400) / FA_CICLO_DIAS);
    $ini = strtotime('+' . ($n * FA_CICLO_DIAS) . ' days', $base);
    $fim = strtotime('+' . (FA_CICLO_DIAS - 1) . ' days', $ini);
    return [date('Y-m-d', $ini), date('Y-m-d', $fim)];
}

function quinzena_rotulo(string $inicio, string $fim): string
{
    $a = strtotime($inicio); $b = strtotime($fim);
    return date('d/m', $a) . (date('Y', $a) !== date('Y', $b) ? '/' . date('Y', $a) : '') . ' a ' . date('d/m/Y', $b);
}

/** Próxima parcela pendente de cada parcelamento do funcionário (o que entra como desconto no fechamento). */
function parcelas_propostas(int $funcionario_id): array
{
    $st = db()->prepare("SELECT p.id, p.numero, p.valor, pl.descricao, pl.id AS plano,
        (SELECT COUNT(*) FROM parcelas x WHERE x.parcelamento_id = pl.id) AS total_parcelas
        FROM parcelamentos pl JOIN parcelas p ON p.parcelamento_id = pl.id
        WHERE pl.funcionario_id = ? AND p.status = 'pendente'
          AND p.numero = (SELECT MIN(numero) FROM parcelas y WHERE y.parcelamento_id = pl.id AND y.status = 'pendente')
        ORDER BY pl.id");
    $st->execute([$funcionario_id]);
    return $st->fetchAll();
}

/** Resumo de um parcelamento: total, pago e saldo. */
function resumo_parcelamento(int $id): array
{
    $st = db()->prepare("SELECT COALESCE(SUM(valor),0) AS total, COALESCE(SUM(CASE WHEN status = 'descontada' THEN valor END),0) AS pago FROM parcelas WHERE parcelamento_id = ?");
    $st->execute([$id]);
    $r = $st->fetch();
    return ['total' => (float)$r['total'], 'pago' => (float)$r['pago'], 'saldo' => (float)$r['total'] - (float)$r['pago']];
}

function ordinal(int $n): string { return $n . 'ª'; }

/** Fechamento que já cobre a data, se existir. */
function fechamento_da_data(string $data): ?array
{
    $st = db()->prepare('SELECT * FROM fechamentos WHERE ? BETWEEN inicio AND fim');
    $st->execute([$data]);
    return $st->fetch() ?: null;
}

/** Prévia da folha: dias, valores e vales de cada funcionário no período. */
function folha(string $inicio, string $fim): array
{
    $st = db()->prepare("SELECT f.id, f.nome, f.funcao, f.pix, f.whatsapp, f.diaria, f.ativo,
        COALESCE(SUM(d.fracao),0) AS dias, COALESCE(SUM(d.fracao * d.valor),0) AS bruto, COALESCE(SUM(d.extra),0) AS extras,
        (SELECT COALESCE(SUM(v.valor),0) FROM vales v WHERE v.funcionario_id = f.id AND v.data BETWEEN ? AND ?) AS vales
        FROM funcionarios f LEFT JOIN diarias d ON d.funcionario_id = f.id AND d.data BETWEEN ? AND ?
        GROUP BY f.id HAVING f.ativo = 1 OR dias > 0 OR vales > 0 ORDER BY f.nome");
    $st->execute([$inicio, $fim, $inicio, $fim]);
    $linhas = $st->fetchAll();
    foreach ($linhas as &$l) $l['liquido'] = round($l['bruto'] + $l['extras'] - $l['vales'], 2);
    return $linhas;
}

function dias_br(float $d): string
{
    return rtrim(rtrim(number_format($d, 1, ',', ''), '0'), ',');
}

function dia_semana(string $data): string
{
    return ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'][(int)date('w', strtotime($data))];
}

/** Valor em reais por extenso, para recibos. */
function por_extenso(float $valor): string
{
    $u = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
    $d = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
    $c = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
    $ate999 = function (int $n) use ($u, $d, $c): string {
        if ($n === 100) return 'cem';
        $p = [];
        if ($n >= 100) { $p[] = $c[intdiv($n, 100)]; $n %= 100; }
        if ($n >= 20) { $p[] = $d[intdiv($n, 10)]; $n %= 10; }
        if ($n > 0) $p[] = $u[$n];
        return implode(' e ', $p);
    };
    $reais = (int)floor($valor + 0.0001);
    $cent = (int)round(($valor - $reais) * 100);
    $partes = [];
    $mi = intdiv($reais, 1000000); $mil = intdiv($reais % 1000000, 1000); $resto = $reais % 1000;
    if ($mi) $partes[] = $ate999($mi) . ($mi === 1 ? ' milhão' : ' milhões');
    if ($mil) $partes[] = ($mil === 1 ? 'mil' : $ate999($mil) . ' mil');
    if ($resto) $partes[] = $ate999($resto);
    $txt = '';
    foreach ($partes as $i => $p) {
        $ultimo = $i === count($partes) - 1;
        $txt .= ($i === 0 ? '' : ($ultimo && ($resto < 100 || $resto % 100 === 0) ? ' e ' : ' ')) . $p;
    }
    if ($reais) $txt .= ($mi && !$mil && !$resto ? ' de' : '') . ($reais === 1 ? ' real' : ' reais');
    if ($cent) $txt .= ($reais ? ' e ' : '') . $ate999($cent) . ($cent === 1 ? ' centavo' : ' centavos');
    return $txt ?: 'zero real';
}

/** Fecha a folha do período: grava os valores travados e desconta as parcelas escolhidas. Retorna o id do fechamento, ou 0 se não havia nada. */
function fechar_periodo(string $ini, string $fim, array $escolhidas): int
{
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO fechamentos(inicio, fim) VALUES(?,?)')->execute([$ini, $fim]);
    $fid = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO fechamento_itens(fechamento_id, funcionario_id, nome, funcao, pix, dias, bruto, extras, vales, descontos, detalhe, liquido) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
    $marca = $pdo->prepare("UPDATE parcelas SET status = 'descontada', fechamento_id = ?, descontada_em = ? WHERE id = ? AND status = 'pendente'");
    $n = 0;
    foreach (folha($ini, $fim) as $l) {
        $desc = 0.0; $det = [];
        // sem dia trabalhado no período, não há folha nem desconto de parcela
        if ($l['dias'] <= 0 && $l['vales'] <= 0 && $l['extras'] <= 0) continue;
        foreach (($l['dias'] > 0 ? parcelas_propostas((int)$l['id']) : []) as $p) {
            if (!in_array((int)$p['id'], $escolhidas, true)) continue;
            $desc += (float)$p['valor'];
            $det[] = brl((float)$p['valor']) . ' · ' . ordinal((int)$p['numero']) . ' parcela ' . $p['descricao'];
            $marca->execute([$fid, $fim, $p['id']]);
        }
        if ($l['vales'] > 0) $det[] = brl((float)$l['vales']) . ' já pago';
        if ($l['dias'] <= 0 && $l['vales'] <= 0 && $l['extras'] <= 0 && $desc <= 0) continue;
        $liq = round($l['bruto'] + $l['extras'] - $l['vales'] - $desc, 2);
        $ins->execute([$fid, $l['id'], $l['nome'], $l['funcao'], $l['pix'], $l['dias'], $l['bruto'], $l['extras'], $l['vales'], $desc, implode(' | ', $det), $liq]);
        $n++;
    }
    if (!$n) { $pdo->rollBack(); return 0; }
    $pdo->commit();
    return $fid;
}

/** Iniciais coloridas de uma pessoa (a cor é sempre a mesma para o mesmo nome). */
function avatar(string $nome, string $tam = ''): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [''];
    $ini = mb_strtoupper(mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : mb_substr($partes[0], 1, 1)));
    $cor = (crc32(mb_strtolower(trim($nome))) % 6) + 1;
    return '<span class="avatar ' . $tam . ' av' . $cor . '" aria-hidden="true">' . h($ini) . '</span>';
}
