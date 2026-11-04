<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();
$pdo = db();
$STATUS = ['novo' => 'Novo', 'contatado' => 'Contatado', 'orcado' => 'Orçado', 'fechado' => 'Fechado', 'perdido' => 'Perdido'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? '';
    $st = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) ir('pedidos.php');
    if ($acao === 'salvar') {
        $s = array_key_exists($_POST['status'] ?? '', $STATUS) ? $_POST['status'] : $p['status'];
        $pdo->prepare('UPDATE pedidos SET status = ?, notas = ? WHERE id = ?')->execute([$s, trim((string)($_POST['notas'] ?? '')), $id]);
        avisar('Pedido atualizado.');
        ir("pedidos.php?id=$id");
    }
    if ($acao === 'cliente') {
        $cid = $p['cliente_id'];
        if (!$cid) {
            $pdo->prepare('INSERT INTO clientes(nome, whatsapp, cidade, notas) VALUES(?,?,?,?)')
                ->execute([$p['nome'], $p['whatsapp'], $p['cidade'], 'Veio pelo site em ' . data_br($p['criado_em'])]);
            $cid = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE pedidos SET cliente_id = ? WHERE id = ?')->execute([$cid, $id]);
        }
        avisar('Cliente cadastrado a partir do pedido.');
        ir("clientes.php?id=$cid");
    }
    if ($acao === 'apagar') {
        $pdo->prepare('DELETE FROM pedidos WHERE id = ?')->execute([$id]);
        avisar('Pedido apagado.');
        ir('pedidos.php');
    }
}

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $st = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) ir('pedidos.php');
    if ($p['status'] === 'novo' && isset($_GET['marcar'])) {
        $pdo->prepare("UPDATE pedidos SET status = 'contatado' WHERE id = ?")->execute([$id]);
        $p['status'] = 'contatado';
    }
    $primeiro = explode(' ', trim($p['nome']))[0];
    $msg = "Olá, $primeiro! Aqui é o {$u['nome']}, da Farias Araújo Construções. Recebemos seu pedido de orçamento para " . mb_strtolower((string)$p['servico']) . ($p['cidade'] ? " em {$p['cidade']}" : '') . '. Podemos conversar?';
    topo('Pedido de ' . $p['nome'], 'pedidos', $u);
    ?>
    <a href="pedidos.php" class="pq">← Todos os pedidos</a>
    <div class="cab" style="margin-top:10px">
      <div><span class="kicker">Pedido #<?= $p['id'] ?> · <?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></span><h1><?= h($p['nome']) ?></h1></div>
      <?= rotulo_status($p['status']) ?>
    </div>
    <div class="lado">
      <section class="cartao">
        <div class="grade g2">
          <div><span class="num-rot">WhatsApp</span><p style="margin:4px 0 0"><?= h($p['whatsapp']) ?></p></div>
          <div><span class="num-rot">Cidade</span><p style="margin:4px 0 0"><?= h($p['cidade'] ?: 'Não informou') ?></p></div>
          <div><span class="num-rot">Serviço</span><p style="margin:4px 0 0"><?= h($p['servico']) ?></p></div>
          <div><span class="num-rot">Tamanho</span><p style="margin:4px 0 0"><?= h($p['metragem'] ? $p['metragem'] . ' m²' : 'Não informou') ?></p></div>
          <div><span class="num-rot">Quando quer começar</span><p style="margin:4px 0 0"><?= h($p['prazo'] ?: 'Não informou') ?></p></div>
        </div>
        <?php if ($p['mensagem']): ?><div style="margin-top:18px"><span class="num-rot">Mensagem</span><p style="white-space:pre-line;margin:6px 0 0"><?= h($p['mensagem']) ?></p></div><?php endif ?>
        <div class="acoes" style="margin-top:22px">
          <a class="btn btn-zap" href="<?= h(whats_link($p['whatsapp'], $msg)) ?>" target="_blank" rel="noopener" onclick="setTimeout(()=>location.href='pedidos.php?id=<?= $p['id'] ?>&marcar=1',400)">Responder no WhatsApp</a>
          <a class="btn btn-ouro" href="orcamento.php?novo=1&pedido=<?= $p['id'] ?>">Montar orçamento</a>
          <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="acao" value="cliente"><button class="btn"><?= $p['cliente_id'] ? 'Ver cliente' : 'Cadastrar como cliente' ?></button></form>
        </div>
      </section>
      <form class="cartao" method="post">
        <h2>Andamento</h2>
        <?= campo_csrf() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="acao" value="salvar">
        <div class="campo"><label for="status">Situação</label><select id="status" name="status"><?= opcoes($STATUS, $p['status'], true) ?></select></div>
        <div class="campo"><label for="notas">Anotações da equipe</label><textarea id="notas" name="notas" placeholder="Ex.: visita marcada sábado às 9h"><?= h($p['notas']) ?></textarea></div>
        <button class="btn btn-ouro">Salvar andamento</button>
      </form>
    </div>
    <form method="post" style="margin-top:28px" data-confirma="Apagar o pedido de <?= h($p['nome']) ?>? Não dá para desfazer."><?= campo_csrf() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="acao" value="apagar"><button class="btn btn-perigo btn-pq">Apagar pedido de <?= h($primeiro) ?></button></form>
    <?php
    rodape();
    exit;
}

$filtro = $_GET['s'] ?? '';
$sql = 'SELECT * FROM pedidos' . (array_key_exists($filtro, $STATUS) ? ' WHERE status = ?' : '') . ' ORDER BY id DESC LIMIT 300';
$st = $pdo->prepare($sql);
$st->execute(array_key_exists($filtro, $STATUS) ? [$filtro] : []);
$lista = $st->fetchAll();
topo('Pedidos', 'pedidos', $u);
?>
<div class="cab"><div><h1>Pedidos de orçamento</h1><p class="sub">Tudo que chega pelo formulário do site.</p></div></div>
<nav class="filtros" aria-label="Filtrar">
  <a href="pedidos.php" class="<?= $filtro === '' ? 'ativo' : '' ?>">Todos</a>
  <?php foreach ($STATUS as $k => $v): ?><a href="?s=<?= $k ?>" class="<?= $filtro === $k ? 'ativo' : '' ?>"><?= $v ?></a><?php endforeach ?>
</nav>
<?php if (!$lista): ?>
  <div class="cartao vazio"><strong><?= $filtro ? 'Nenhum pedido nessa situação' : 'Nenhum pedido ainda' ?></strong>Os pedidos feitos no site aparecem aqui na hora, com nome, WhatsApp e o que a pessoa precisa.</div>
<?php else: ?>
<div class="tabela-wrap"><table>
  <thead><tr><th>Nome</th><th>Serviço</th><th class="esconde-cel">Cidade</th><th class="esconde-cel">Data</th><th>Situação</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $p): ?>
    <tr><td><a class="linha" href="?id=<?= $p['id'] ?>"><?= h($p['nome']) ?></a><div class="pq"><?= h($p['whatsapp']) ?></div></td><td><?= h($p['servico']) ?></td><td class="esconde-cel"><?= h($p['cidade']) ?></td><td class="esconde-cel pq"><?= data_br($p['criado_em']) ?></td><td><?= rotulo_status($p['status']) ?></td></tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif;
rodape();
