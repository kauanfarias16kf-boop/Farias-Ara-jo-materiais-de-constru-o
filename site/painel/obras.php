<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
$pdo = db();
$STATUS = ['planejada' => 'Planejada', 'em_andamento' => 'Em andamento', 'pausada' => 'Pausada', 'concluida' => 'Concluída'];
$filtro = $_GET['s'] ?? '';
$where = array_key_exists($filtro, $STATUS) ? 'WHERE o.status = ?' : '';
$st = $pdo->prepare("SELECT o.*, c.nome AS cliente,
  (SELECT COUNT(*) FROM etapas e WHERE e.obra_id = o.id) AS total_e,
  (SELECT COUNT(*) FROM etapas e WHERE e.obra_id = o.id AND e.status = 'concluida') AS feitas,
  (SELECT nome FROM etapas e WHERE e.obra_id = o.id AND e.status != 'concluida' ORDER BY ordem LIMIT 1) AS atual
  FROM obras o LEFT JOIN clientes c ON c.id = o.cliente_id $where ORDER BY o.id DESC");
$st->execute($where ? [$filtro] : []);
$lista = $st->fetchAll();
topo('Obras', 'obras', $u);
?>
<div class="cab"><div><h1>Obras</h1><p class="sub">Etapas, fotos e o link que o cliente usa para acompanhar.</p></div><a class="btn btn-ouro" href="obra.php?novo=1">Nova obra</a></div>
<nav class="filtros" aria-label="Filtrar">
  <a href="obras.php" class="<?= $filtro === '' ? 'ativo' : '' ?>">Todas</a>
  <?php foreach ($STATUS as $k => $v): ?><a href="?s=<?= $k ?>" class="<?= $filtro === $k ? 'ativo' : '' ?>"><?= $v ?></a><?php endforeach ?>
</nav>
<?php if (!$lista): ?>
  <div class="cartao vazio"><strong>Nenhuma obra aqui</strong>Cadastre a obra com as etapas. O cliente recebe um código para ver o andamento pelo celular.<br><a class="btn" href="obra.php?novo=1">Cadastrar obra</a></div>
<?php else: ?>
<div class="grade g3">
  <?php foreach ($lista as $o): $pct = $o['total_e'] ? round($o['feitas'] / $o['total_e'] * 100) : 0; ?>
  <a class="cartao cartao-num" href="obra.php?id=<?= $o['id'] ?>">
    <div style="display:flex;justify-content:space-between;gap:8px;align-items:center"><span class="kicker"><?= h($o['codigo']) ?></span><?= rotulo_status($o['status']) ?></div>
    <h2 style="margin:12px 0 2px"><?= h($o['titulo']) ?></h2>
    <p class="pq" style="margin:0 0 14px"><?= h($o['cliente'] ?: 'Sem cliente') ?><?= $o['previsao'] ? ' · entrega ' . data_br($o['previsao']) : '' ?></p>
    <div class="barra-prog"><i style="width:<?= $pct ?>%"></i></div>
    <p class="pq" style="margin:8px 0 0"><?= $pct ?>% · <?= $o['atual'] ? 'agora: ' . h($o['atual']) : 'todas as etapas feitas' ?></p>
  </a>
  <?php endforeach ?>
</div>
<?php endif;
rodape();
