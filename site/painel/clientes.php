<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();
$pdo = db();
$CAMPOS = ['nome', 'whatsapp', 'email', 'cidade', 'endereco', 'documento', 'notas'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['acao'] ?? '') === 'apagar' && $id) {
        $pdo->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
        avisar('Cliente apagado.');
        ir('clientes.php');
    }
    $v = [];
    foreach ($CAMPOS as $c) $v[$c] = trim((string)($_POST[$c] ?? ''));
    if ($v['nome'] === '') { avisar('O cliente precisa de um nome.', 'erro'); ir('clientes.php?id=' . ($id ?: 'novo')); }
    if ($id) {
        $sets = implode(', ', array_map(fn($c) => "$c = ?", $CAMPOS));
        $pdo->prepare("UPDATE clientes SET $sets WHERE id = ?")->execute([...array_values($v), $id]);
    } else {
        $pdo->prepare('INSERT INTO clientes(' . implode(',', $CAMPOS) . ') VALUES(' . rtrim(str_repeat('?,', count($CAMPOS)), ',') . ')')->execute(array_values($v));
        $id = (int)$pdo->lastInsertId();
    }
    avisar('Cliente salvo.');
    ir("clientes.php?id=$id");
}

$id = $_GET['id'] ?? '';
if ($id !== '') {
    $c = array_fill_keys($CAMPOS, '');
    $c['id'] = 0;
    if ($id !== 'novo') {
        $st = $pdo->prepare('SELECT * FROM clientes WHERE id = ?');
        $st->execute([(int)$id]);
        $c = $st->fetch() ?: ir('clientes.php');
    }
    $obras = $orcs = [];
    if ($c['id']) {
        $st = $pdo->prepare('SELECT * FROM obras WHERE cliente_id = ? ORDER BY id DESC'); $st->execute([$c['id']]); $obras = $st->fetchAll();
        $st = $pdo->prepare('SELECT * FROM orcamentos WHERE cliente_id = ? ORDER BY id DESC'); $st->execute([$c['id']]); $orcs = $st->fetchAll();
    }
    topo($c['id'] ? $c['nome'] : 'Novo cliente', 'clientes', $u);
    ?>
    <a href="clientes.php" class="pq">← Todos os clientes</a>
    <div class="cab" style="margin-top:10px"><h1><?= $c['id'] ? h($c['nome']) : 'Novo cliente' ?></h1>
      <?php if ($c['id'] && $c['whatsapp']): ?><a class="btn btn-zap" href="<?= h(whats_link($c['whatsapp'])) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a><?php endif ?></div>
    <div class="lado">
      <form class="cartao" method="post">
        <?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <div class="grade g2">
          <div class="campo"><label for="nome">Nome</label><input id="nome" name="nome" required value="<?= h($c['nome']) ?>"></div>
          <div class="campo"><label for="whatsapp">WhatsApp</label><input id="whatsapp" name="whatsapp" inputmode="tel" value="<?= h($c['whatsapp']) ?>" placeholder="61 9 0000-0000"></div>
          <div class="campo"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= h($c['email']) ?>"></div>
          <div class="campo"><label for="documento">CPF ou CNPJ</label><input id="documento" name="documento" value="<?= h($c['documento']) ?>"></div>
          <div class="campo"><label for="cidade">Cidade</label><input id="cidade" name="cidade" value="<?= h($c['cidade']) ?>"></div>
          <div class="campo"><label for="endereco">Endereço da obra</label><input id="endereco" name="endereco" value="<?= h($c['endereco']) ?>"></div>
        </div>
        <div class="campo"><label for="notas">Anotações</label><textarea id="notas" name="notas"><?= h($c['notas']) ?></textarea></div>
        <button class="btn btn-ouro">Salvar cliente</button>
      </form>
      <?php if ($c['id']): ?>
      <div class="grade">
        <section class="cartao"><div class="cab" style="margin-bottom:6px"><h2 style="margin:0">Obras</h2><a class="btn btn-pq" href="obra.php?novo=1&cliente=<?= $c['id'] ?>">Nova obra</a></div>
          <?php if (!$obras): ?><p class="pq">Nenhuma obra ainda.</p><?php endif ?>
          <ul class="lista-ativ"><?php foreach ($obras as $o): ?><li><a href="obra.php?id=<?= $o['id'] ?>"><?= h($o['titulo']) ?></a><?= rotulo_status($o['status']) ?></li><?php endforeach ?></ul></section>
        <section class="cartao"><div class="cab" style="margin-bottom:6px"><h2 style="margin:0">Orçamentos</h2><a class="btn btn-pq" href="orcamento.php?novo=1&cliente=<?= $c['id'] ?>">Novo orçamento</a></div>
          <?php if (!$orcs): ?><p class="pq">Nenhum orçamento ainda.</p><?php endif ?>
          <ul class="lista-ativ"><?php foreach ($orcs as $o): ?><li><a href="orcamento.php?id=<?= $o['id'] ?>"><?= h($o['numero']) ?> · <?= h($o['titulo']) ?></a><?= rotulo_status($o['status']) ?></li><?php endforeach ?></ul></section>
      </div>
      <?php endif ?>
    </div>
    <?php if ($c['id']): ?>
    <form method="post" style="margin-top:28px" data-confirma="Apagar <?= h($c['nome']) ?>? As obras e orçamentos continuam, mas ficam sem cliente."><?= campo_csrf() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="acao" value="apagar"><button class="btn btn-perigo btn-pq">Apagar cliente <?= h($c['nome']) ?></button></form>
    <?php endif;
    rodape();
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $st = $pdo->prepare("SELECT * FROM clientes WHERE nome LIKE ? OR whatsapp LIKE ? OR cidade LIKE ? ORDER BY nome");
    $like = "%$q%";
    $st->execute([$like, $like, $like]);
} else {
    $st = $pdo->query('SELECT * FROM clientes ORDER BY nome');
}
$lista = $st->fetchAll();
topo('Clientes', 'clientes', $u);
?>
<div class="cab"><div><h1>Clientes</h1><p class="sub"><?= count($lista) ?> cadastrados</p></div><a class="btn btn-ouro" href="?id=novo">Novo cliente</a></div>
<form class="acoes" style="margin-bottom:16px" role="search"><label for="q" class="esconde-cel" style="margin:0">Buscar</label><input id="q" name="q" value="<?= h($q) ?>" placeholder="Nome, WhatsApp ou cidade" style="max-width:340px"><button class="btn">Buscar</button></form>
<?php if (!$lista): ?>
  <div class="cartao vazio"><strong><?= $q ? 'Ninguém encontrado' : 'Nenhum cliente ainda' ?></strong><?= $q ? 'Tente outro nome ou número.' : 'Cadastre aqui ou transforme um pedido do site em cliente com um clique.' ?><br><a class="btn" href="?id=novo">Cadastrar cliente</a></div>
<?php else: ?>
<div class="tabela-wrap"><table>
  <thead><tr><th>Nome</th><th>WhatsApp</th><th class="esconde-cel">Cidade</th></tr></thead>
  <tbody><?php foreach ($lista as $c): ?><tr><td><a class="linha" href="?id=<?= $c['id'] ?>"><?= h($c['nome']) ?></a></td><td><?= h($c['whatsapp']) ?></td><td class="esconde-cel"><?= h($c['cidade']) ?></td></tr><?php endforeach ?></tbody>
</table></div>
<?php endif;
rodape();
