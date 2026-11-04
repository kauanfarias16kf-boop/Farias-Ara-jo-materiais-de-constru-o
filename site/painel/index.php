<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
$pdo = db();

$novos = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE status = 'novo'")->fetchColumn();
$andamento = (int)$pdo->query("SELECT COUNT(*) FROM obras WHERE status = 'em_andamento'")->fetchColumn();
$orcAbertos = $pdo->query("SELECT id, desconto FROM orcamentos WHERE status IN ('rascunho','enviado')")->fetchAll();
$valorAberto = 0.0;
foreach ($orcAbertos as $o) $valorAberto += max(0, total_orcamento((int)$o['id']) - (float)$o['desconto']);
$clientes = (int)$pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();

$ultPedidos = $pdo->query('SELECT * FROM pedidos ORDER BY id DESC LIMIT 6')->fetchAll();
$obras = $pdo->query("SELECT o.*, c.nome AS cliente,
   (SELECT COUNT(*) FROM etapas e WHERE e.obra_id = o.id) AS total_e,
   (SELECT COUNT(*) FROM etapas e WHERE e.obra_id = o.id AND e.status = 'concluida') AS feitas
   FROM obras o LEFT JOIN clientes c ON c.id = o.cliente_id
   WHERE o.status IN ('em_andamento','planejada','pausada') ORDER BY o.previsao IS NULL, o.previsao LIMIT 6")->fetchAll();

[$qi, $qf] = quinzena(date('Y-m-d'));
$hojeDias = (float)$pdo->query("SELECT COALESCE(SUM(fracao),0) FROM diarias WHERE data = date('now','-3 hours')")->fetchColumn();
$ativos = (int)$pdo->query('SELECT COUNT(*) FROM funcionarios WHERE ativo = 1')->fetchColumn();
$folhaQ = array_sum(array_column(folha($qi, $qf), 'liquido'));
$st = $pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM despesas WHERE data BETWEEN ? AND ?'); $st->execute([$qi, $qf]);
$despQ = (float)$st->fetchColumn();
$hora = (int)date('H');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
topo('Início', 'index', $u);
?>
<div class="cab">
  <div><span class="kicker"><?= date('d/m/Y') ?></span><h1><?= $saudacao ?>, <?= h($u['nome']) ?>.</h1></div>
  <div class="acoes"><a class="btn" href="despesas.php">Lançar despesa</a><a class="btn btn-ouro" href="diarias.php">Marcar diárias de hoje</a></div>
</div>

<span class="kicker">Obra e equipe</span>
<div class="grade g3" style="margin:10px 0 26px">
  <a class="cartao cartao-num <?= $ativos && !$hojeDias ? 'destaque' : '' ?>" href="diarias.php"><span class="num-rot">Diárias hoje</span><span class="num"><?= dias_br($hojeDias) ?> <span class="pq" style="font-size:16px">de <?= $ativos ?> na equipe</span></span><span class="pq"><?= $ativos && !$hojeDias ? 'Ainda não marcado hoje' : 'Toque para conferir' ?></span></a>
  <a class="cartao cartao-num" href="fechamento.php"><span class="num-rot">Folha da quinzena</span><span class="num dinheiro"><?= brl($folhaQ) ?></span><span class="pq"><?= quinzena_rotulo($qi, $qf) ?></span></a>
  <a class="cartao cartao-num" href="despesas.php"><span class="num-rot">Despesas da quinzena</span><span class="num dinheiro"><?= brl($despQ) ?></span><span class="pq">material, combustível e outros</span></a>
</div>

<span class="kicker">Comercial</span>
<div style="height:10px"></div>

<div class="grade g4 metricas" style="margin-bottom:20px">
  <a class="cartao cartao-num" href="pedidos.php?s=novo"><span class="num-rot">Pedidos novos</span><span class="num"><?= $novos ?></span><span class="pq">esperando resposta</span></a>
  <a class="cartao cartao-num" href="obras.php?s=em_andamento"><span class="num-rot">Obras em andamento</span><span class="num"><?= $andamento ?></span><span class="pq">no canteiro agora</span></a>
  <a class="cartao cartao-num" href="orcamentos.php"><span class="num-rot">Em orçamentos abertos</span><span class="num" style="font-size:22px"><?= brl($valorAberto) ?></span><span class="pq"><?= count($orcAbertos) ?> aguardando resposta</span></a>
  <a class="cartao cartao-num" href="clientes.php"><span class="num-rot">Clientes</span><span class="num"><?= $clientes ?></span><span class="pq">cadastrados</span></a>
</div>

<div class="grade g2">
  <section class="cartao">
    <div class="cab" style="margin-bottom:8px"><h2 style="margin:0">Últimos pedidos do site</h2><a href="pedidos.php" class="pq">Ver todos</a></div>
    <?php if (!$ultPedidos): ?>
      <div class="vazio"><strong>Nenhum pedido ainda</strong>Quando alguém pedir orçamento pelo site, aparece aqui na hora.</div>
    <?php else: ?>
      <ul class="lista-ativ">
      <?php foreach ($ultPedidos as $p): ?>
        <li><span><a class="linha" style="color:var(--t1);text-decoration:none;font-weight:600" href="pedidos.php?id=<?= $p['id'] ?>"><?= h($p['nome']) ?></a><br><span class="pq"><?= h($p['servico']) ?> · <?= h($p['cidade']) ?> · <?= data_br($p['criado_em']) ?></span></span><?= rotulo_status($p['status']) ?></li>
      <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>
  <section class="cartao">
    <div class="cab" style="margin-bottom:8px"><h2 style="margin:0">Obras ativas</h2><a href="obras.php" class="pq">Ver todas</a></div>
    <?php if (!$obras): ?>
      <div class="vazio"><strong>Nenhuma obra ativa</strong>Cadastre a obra para acompanhar etapas e mandar o link ao cliente.<br><a class="btn" href="obra.php?novo=1">Cadastrar obra</a></div>
    <?php else: ?>
      <ul class="lista-ativ">
      <?php foreach ($obras as $o): $pct = $o['total_e'] ? round($o['feitas'] / $o['total_e'] * 100) : 0; ?>
        <li style="display:block">
          <div style="display:flex;justify-content:space-between;gap:10px"><a style="color:var(--t1);text-decoration:none;font-weight:600" href="obra.php?id=<?= $o['id'] ?>"><?= h($o['titulo']) ?></a><span class="pq"><?= $pct ?>%</span></div>
          <div class="pq" style="margin:2px 0 8px"><?= h($o['cliente'] ?: 'Sem cliente') ?><?= $o['previsao'] ? ' · entrega ' . data_br($o['previsao']) : '' ?></div>
          <div class="barra-prog"><i style="width:<?= $pct ?>%"></i></div>
        </li>
      <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>
</div>
<?php rodape();
