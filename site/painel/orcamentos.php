<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
$pdo = db();
$STATUS = ['rascunho' => 'Rascunho', 'enviado' => 'Enviado', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado'];
$filtro = $_GET['s'] ?? '';
$st = $pdo->prepare('SELECT * FROM orcamentos' . (array_key_exists($filtro, $STATUS) ? ' WHERE status = ?' : '') . ' ORDER BY id DESC');
$st->execute(array_key_exists($filtro, $STATUS) ? [$filtro] : []);
$lista = $st->fetchAll();
topo('Orçamentos', 'orcamentos', $u);
?>
<div class="cab"><div><h1>Orçamentos</h1><p class="sub">Monte item por item e mande o PDF com a marca.</p></div><a class="btn btn-ouro" href="orcamento.php?novo=1">Novo orçamento</a></div>
<nav class="filtros" aria-label="Filtrar">
  <a href="orcamentos.php" class="<?= $filtro === '' ? 'ativo' : '' ?>">Todos</a>
  <?php foreach ($STATUS as $k => $v): ?><a href="?s=<?= $k ?>" class="<?= $filtro === $k ? 'ativo' : '' ?>"><?= $v ?></a><?php endforeach ?>
</nav>
<?php if (!$lista): ?>
  <div class="cartao vazio"><strong>Nenhum orçamento aqui</strong>Comece do zero ou a partir de um pedido do site, que já vem com nome, cidade e serviço.<br><a class="btn" href="orcamento.php?novo=1">Montar orçamento</a></div>
<?php else: ?>
<div class="tabela-wrap"><table>
  <thead><tr><th>Número</th><th>Cliente</th><th class="esconde-cel">Obra</th><th class="dir">Total</th><th>Situação</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $o): ?>
    <tr><td><a class="linha" href="orcamento.php?id=<?= $o['id'] ?>"><?= h($o['numero']) ?></a><div class="pq"><?= data_br($o['criado_em']) ?></div></td><td><?= h($o['cliente_nome']) ?></td><td class="esconde-cel"><?= h($o['titulo']) ?></td><td class="dir"><?= brl(max(0, total_orcamento((int)$o['id']) - (float)$o['desconto'])) ?></td><td><?= rotulo_status($o['status']) ?></td></tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif;
rodape();
