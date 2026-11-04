<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
$pdo = db();
$hoje = date('Y-m-d');
[$qi, $qf] = quinzena($hoje);

// período atual
$folhaQ = folha($qi, $qf);
$liqQ = 0.0;
foreach ($folhaQ as $l) {
    $desc = $l['dias'] > 0 ? array_sum(array_map(fn($p) => (float)$p['valor'], parcelas_propostas((int)$l['id']))) : 0;
    $liqQ += $l['bruto'] + $l['extras'] - $l['vales'] - $desc;
}
$st = $pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM despesas WHERE data BETWEEN ? AND ?'); $st->execute([$qi, $qf]);
$despQ = (float)$st->fetchColumn();
$st = $pdo->prepare('SELECT categoria, SUM(valor) v FROM despesas WHERE data BETWEEN ? AND ? GROUP BY categoria ORDER BY v DESC'); $st->execute([$qi, $qf]);
$porCat = $st->fetchAll();
$st = $pdo->prepare('SELECT data, SUM(fracao) n, SUM(fracao * valor + extra) v FROM diarias WHERE data BETWEEN ? AND ? GROUP BY data'); $st->execute([$qi, $qf]);
$porDia = [];
foreach ($st->fetchAll() as $r) $porDia[$r['data']] = $r;
$hojeN = (float)($porDia[$hoje]['n'] ?? 0);
$ativos = (int)$pdo->query('SELECT COUNT(*) FROM funcionarios WHERE ativo = 1')->fetchColumn();
$diaDoPeriodo = (int)((strtotime($hoje) - strtotime($qi)) / 86400) + 1;

// comercial
$novos = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE status = 'novo'")->fetchColumn();
$orcAbertos = $pdo->query("SELECT id, desconto FROM orcamentos WHERE status IN ('rascunho','enviado')")->fetchAll();
$valorAberto = 0.0;
foreach ($orcAbertos as $o) $valorAberto += max(0, total_orcamento((int)$o['id']) - (float)$o['desconto']);
$ultPedidos = $pdo->query('SELECT * FROM pedidos ORDER BY id DESC LIMIT 5')->fetchAll();
$obras = $pdo->query("SELECT o.*, c.nome AS cliente,
   (SELECT COUNT(*) FROM etapas e WHERE e.obra_id = o.id) AS total_e,
   (SELECT COUNT(*) FROM etapas e WHERE e.obra_id = o.id AND e.status = 'concluida') AS feitas,
   (SELECT nome FROM etapas e WHERE e.obra_id = o.id AND e.status != 'concluida' ORDER BY ordem LIMIT 1) AS atual,
   (SELECT COALESCE(SUM(fracao * valor + extra),0) FROM diarias d WHERE d.obra_id = o.id) + (SELECT COALESCE(SUM(valor),0) FROM despesas x WHERE x.obra_id = o.id) AS custo
   FROM obras o LEFT JOIN clientes c ON c.id = o.cliente_id
   WHERE o.status IN ('em_andamento','planejada','pausada') ORDER BY o.status = 'em_andamento' DESC, o.id DESC LIMIT 4")->fetchAll();

$hora = (int)date('H');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
$dias_semana = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

// gráfico de barras: diárias por dia do período
$diasQ = [];
for ($t = strtotime($qi); $t <= strtotime($qf); $t = strtotime('+1 day', $t)) $diasQ[] = date('Y-m-d', $t);
$maxN = max(1, $ativos, ...array_map(fn($d) => (float)($porDia[$d]['n'] ?? 0), $diasQ));
$W = 600; $H = 190; $topo = 16; $base = 160; $passo = $W / count($diasQ); $larg = min(26, $passo - 8);
$escala = ($base - $topo) / $maxN;
$guias = array_unique([0, (int)ceil($maxN / 2), (int)ceil($maxN)]);
$maxCat = max(1, ...array_map(fn($c) => (float)$c['v'], $porCat ?: [['v' => 1]]));

topo('Início', 'index', $u);
?>
<div class="cab">
  <div><span class="kicker"><?= $dias_semana[(int)date('w')] ?>, <?= (int)date('d') ?> de <?= $meses[(int)date('m')] ?></span><h1><?= $saudacao ?>, <?= h($u['nome']) ?>.</h1></div>
  <div style="min-width:min(320px,100%)">
    <div class="pq" style="display:flex;justify-content:space-between;margin-bottom:6px"><b class="forte">Período <?= quinzena_rotulo($qi, $qf) ?></b><span>dia <?= $diaDoPeriodo ?> de <?= FA_CICLO_DIAS ?></span></div>
    <div class="barra-prog"><i style="width:<?= round($diaDoPeriodo / FA_CICLO_DIAS * 100) ?>%"></i></div>
  </div>
</div>

<nav class="grade g4" aria-label="Atalhos" style="margin-bottom:22px">
  <?php foreach ([
    ['diarias.php', 'Marcar diárias', 'Quem veio hoje', 'M4 5h16v16H4zM4 10h16M9 3v4M15 3v4M8.5 15l2.5 2.5 4.5-5', true],
    ['despesas.php', 'Lançar despesa', 'Combustível, almoço, material', 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h3', false],
    ['orcamento.php?novo=1', 'Novo orçamento', 'Monta e gera o PDF', 'M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6', false],
    ['fechamento.php', 'Fechamento', 'Prévia da folha', 'M3 6h18v12H3zM12 15a3 3 0 100-6 3 3 0 000 6z', false],
  ] as [$href, $tit, $sub, $ic, $prim]): ?>
    <a class="cartao cartao-num <?= $prim ? 'destaque' : '' ?>" href="<?= $href ?>" style="padding:18px 20px"><span class="ico"><?= icone($ic, 20) ?></span><b style="display:block;font-size:17px;padding-right:44px"><?= $tit ?></b><span class="pq"><?= $sub ?></span></a>
  <?php endforeach ?>
</nav>

<div class="grade g4 metricas" style="margin-bottom:22px">
  <a class="cartao cartao-num" href="diarias.php"><span class="ico"><?= icone('M16 19v-1a4 4 0 00-4-4H7a4 4 0 00-4 4v1M9.5 10a3.5 3.5 0 100-7 3.5 3.5 0 000 7z', 20) ?></span><span class="num-rot">Diárias hoje</span><span class="num"><?= dias_br($hojeN) ?> <span class="pq" style="font-size:15px;font-weight:600">de <?= $ativos ?></span></span><span class="pq"><?= $ativos && !$hojeN ? 'Ainda não marcado' : 'na obra hoje' ?></span></a>
  <a class="cartao cartao-num" href="fechamento.php"><span class="ico"><?= icone('M3 6h18v12H3zM12 15a3 3 0 100-6 3 3 0 000 6z', 20) ?></span><span class="num-rot">Folha do período</span><span class="num dinheiro"><?= brl($liqQ) ?></span><span class="pq">líquido até agora</span></a>
  <a class="cartao cartao-num" href="despesas.php"><span class="ico"><?= icone('M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6', 20) ?></span><span class="num-rot">Despesas do período</span><span class="num dinheiro"><?= brl($despQ) ?></span><span class="pq"><?= count($porCat) ?> categorias</span></a>
  <a class="cartao cartao-num destaque" href="fechamento.php"><span class="ico"><?= icone('M12 3v18M17 7H9.5a3 3 0 000 6h5a3 3 0 010 6H6', 20) ?></span><span class="num-rot">Total a desembolsar</span><span class="num dinheiro"><?= brl($liqQ + $despQ) ?></span><span class="pq">folha + despesas</span></a>
</div>

<div class="lado" style="margin-bottom:22px">
  <section class="cartao">
    <div class="cab" style="margin-bottom:10px"><div><h2 style="margin:0">Diárias por dia</h2><span class="pq">Pessoas trabalhando em cada dia do período</span></div><a class="pq" href="diarias.php?v=grade">Abrir a grade</a></div>
    <div class="grafico" id="graf-dias">
      <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Diárias por dia de <?= quinzena_rotulo($qi, $qf) ?>">
        <?php foreach ($guias as $g): $y = $base - $g * $escala; ?>
          <line class="guia" x1="24" x2="<?= $W ?>" y1="<?= $y ?>" y2="<?= $y ?>"/><text class="rot" x="0" y="<?= $y + 4 ?>"><?= $g ?></text>
        <?php endforeach ?>
        <line class="eixo" x1="24" x2="<?= $W ?>" y1="<?= $base ?>" y2="<?= $base ?>"/>
        <?php foreach ($diasQ as $i => $d):
          $n = (float)($porDia[$d]['n'] ?? 0); $v = (float)($porDia[$d]['v'] ?? 0);
          $x = 24 + $i * (($W - 24) / count($diasQ)) + ((($W - 24) / count($diasQ)) - $larg) / 2; $h2 = $n * $escala; $y = $base - $h2;
          $w = (int)date('w', strtotime($d)); ?>
          <?php if ($n > 0): ?><path class="barra <?= $d === $hoje ? 'hoje' : '' ?>" d="M<?= $x ?> <?= $base ?>V<?= $y + 4 ?>q0 -4 4 -4h<?= $larg - 8 ?>q4 0 4 4V<?= $base ?>Z"/><?php endif ?>
          <text class="rot" x="<?= $x + $larg / 2 ?>" y="<?= $base + 16 ?>" text-anchor="middle" style="<?= $d === $hoje ? 'font-weight:800;fill:var(--t1)' : '' ?><?= $w === 0 ? ';opacity:.55' : '' ?>"><?= (int)date('d', strtotime($d)) ?></text>
          <rect class="alvo" x="<?= $x - 4 ?>" y="<?= $topo ?>" width="<?= $larg + 8 ?>" height="<?= $base - $topo + 20 ?>" data-t="<?= data_br($d) ?> · <?= $dias_semana[$w] ?>" data-v="<?= dias_br($n) ?> <?= $n == 1 ? 'diária' : 'diárias' ?> · <?= brl($v) ?>"/>
        <?php endforeach ?>
      </svg>
    </div>
  </section>
  <section class="cartao">
    <div class="cab" style="margin-bottom:14px"><div><h2 style="margin:0">Despesas por categoria</h2><span class="pq">Período <?= quinzena_rotulo($qi, $qf) ?></span></div></div>
    <?php if (!$porCat): ?><div class="vazio" style="padding:20px 0"><strong>Nada lançado ainda</strong><a class="btn btn-pq" href="despesas.php">Lançar despesa</a></div><?php endif ?>
    <ul class="hbarras">
      <?php foreach (array_slice($porCat, 0, 6) as $c): ?>
        <li><span><?= h($c['categoria']) ?></span><b><?= brl((float)$c['v']) ?></b><span class="trilho"><i style="width:<?= round($c['v'] / $maxCat * 100) ?>%"></i></span></li>
      <?php endforeach ?>
    </ul>
  </section>
</div>

<div class="grade g2">
  <section class="cartao">
    <div class="cab" style="margin-bottom:6px"><h2 style="margin:0">Obras ativas</h2><a href="obras.php" class="pq">Ver todas</a></div>
    <?php if (!$obras): ?>
      <div class="vazio"><strong>Nenhuma obra ativa</strong>Cadastre a obra para acompanhar etapas, custos e mandar o link ao cliente.<br><a class="btn" href="obra.php?novo=1">Cadastrar obra</a></div>
    <?php endif ?>
    <ul class="lista-ativ">
    <?php foreach ($obras as $o): $pct = $o['total_e'] ? round($o['feitas'] / $o['total_e'] * 100) : 0; ?>
      <li style="display:block">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline"><a style="color:var(--t1);text-decoration:none;font-weight:800" href="obra.php?id=<?= $o['id'] ?>"><?= h($o['titulo']) ?></a><b class="pq forte"><?= $pct ?>%</b></div>
        <div class="pq" style="margin:2px 0 8px"><?= $o['atual'] ? 'Agora: ' . h($o['atual']) : 'Sem etapa pendente' ?> · custo <?= brl((float)$o['custo']) ?><?= $o['valor'] > 0 ? ' de ' . brl((float)$o['valor']) : '' ?></div>
        <div class="barra-prog"><i style="width:<?= $pct ?>%"></i></div>
      </li>
    <?php endforeach ?>
    </ul>
  </section>
  <section class="cartao">
    <div class="cab" style="margin-bottom:6px"><div><h2 style="margin:0">Pedidos do site</h2><span class="pq"><?= $novos ?> <?= $novos == 1 ? 'novo esperando' : 'novos esperando' ?> resposta · <?= brl($valorAberto) ?> em orçamentos abertos</span></div><a href="pedidos.php" class="pq">Ver todos</a></div>
    <?php if (!$ultPedidos): ?>
      <div class="vazio"><strong>Nenhum pedido ainda</strong>Quando alguém pedir orçamento pelo site, aparece aqui na hora.</div>
    <?php endif ?>
    <ul class="lista-ativ">
    <?php foreach ($ultPedidos as $p): ?>
      <li><a class="pessoa-linha" href="pedidos.php?id=<?= $p['id'] ?>" style="text-decoration:none;color:var(--t1)"><?= avatar($p['nome']) ?><div><b><?= h($p['nome']) ?></b><span><?= h($p['servico']) ?><?= $p['cidade'] ? ' · ' . h($p['cidade']) : '' ?> · <?= data_br($p['criado_em']) ?></span></div></a><?= rotulo_status($p['status']) ?></li>
    <?php endforeach ?>
    </ul>
  </section>
</div>
<?php rodape();
