<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();
$pdo = db();
$data_ok = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $volta = 'despesas.php' . (!empty($_POST['volta']) ? '?' . preg_replace('/[^a-z0-9=&_-]/i', '', (string)$_POST['volta']) : '');
    if ($acao === 'apagar' && $id) {
        $pdo->prepare('DELETE FROM despesas WHERE id = ?')->execute([$id]);
        avisar('Despesa apagada.');
        ir($volta);
    }
    $v = [
        'data' => $data_ok($_POST['data'] ?? '') ? $_POST['data'] : date('Y-m-d'),
        'obra_id' => ((int)($_POST['obra_id'] ?? 0)) ?: null,
        'categoria' => (in_array($_POST['categoria'] ?? '', FA_CATEGORIAS, true) || ($id && ($_POST['categoria'] ?? '') === ($_POST['cat_antiga'] ?? null))) ? $_POST['categoria'] : 'Outros',
        'descricao' => mb_substr(trim((string)($_POST['descricao'] ?? '')), 0, 200),
        'valor' => num_br((string)($_POST['valor'] ?? '')),
        'forma' => in_array($_POST['forma'] ?? '', FA_FORMAS, true) ? $_POST['forma'] : null,
    ];
    if ($v['valor'] <= 0) { avisar('Informe o valor da despesa.', 'erro'); ir($volta); }
    if ($id) {
        $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($v)));
        $pdo->prepare("UPDATE despesas SET $sets WHERE id = ?")->execute([...array_values($v), $id]);
        avisar('Despesa atualizada.');
    } else {
        $pdo->prepare('INSERT INTO despesas(' . implode(',', array_keys($v)) . ') VALUES(' . rtrim(str_repeat('?,', count($v)), ',') . ')')->execute(array_values($v));
        avisar('Despesa de ' . brl($v['valor']) . ' lançada' . ($v['obra_id'] ? ' na obra.' : '.'));
    }
    ir($volta);
}

// período: quinzena atual por padrão
[$qi, $qf] = quinzena(date('Y-m-d'));
$per = $_GET['p'] ?? 'quinzena';
if ($per === 'mes') { $ini = date('Y-m-01'); $fim = date('Y-m-t'); }
elseif ($per === 'livre' && $data_ok($_GET['ini'] ?? '') && $data_ok($_GET['fim'] ?? '')) { $ini = $_GET['ini']; $fim = $_GET['fim']; }
elseif ($per === 'hoje') { $ini = $fim = date('Y-m-d'); }
else { $per = 'quinzena'; $ini = $qi; $fim = $qf; }
$obraF = (int)($_GET['obra'] ?? 0);
$query = http_build_query(array_filter(['p' => $per, 'ini' => $per === 'livre' ? $ini : null, 'fim' => $per === 'livre' ? $fim : null, 'obra' => $obraF ?: null]));

$where = 'd.data BETWEEN ? AND ?' . ($obraF ? ' AND d.obra_id = ?' : '');
$args = $obraF ? [$ini, $fim, $obraF] : [$ini, $fim];
$st = $pdo->prepare("SELECT d.*, o.titulo FROM despesas d LEFT JOIN obras o ON o.id = d.obra_id WHERE $where ORDER BY d.data DESC, d.id DESC");
$st->execute($args);
$lista = $st->fetchAll();
$total = array_sum(array_column($lista, 'valor'));
$porCat = [];
$porObra = [];
foreach ($lista as $d) {
    $porCat[$d['categoria']] = ($porCat[$d['categoria']] ?? 0) + $d['valor'];
    $k = $d['titulo'] ?: 'Sem obra (geral)';
    $porObra[$k] = ($porObra[$k] ?? 0) + $d['valor'];
}
arsort($porCat); arsort($porObra);

$obrasTodas = $pdo->query('SELECT id, titulo FROM obras ORDER BY status = \'concluida\', id DESC')->fetchAll(PDO::FETCH_KEY_PAIR);
$obrasAtivas = $pdo->query("SELECT id, titulo FROM obras WHERE status != 'concluida' ORDER BY status = 'em_andamento' DESC, id DESC")->fetchAll(PDO::FETCH_KEY_PAIR);

$ed = ['id' => 0, 'data' => date('Y-m-d'), 'obra_id' => array_key_first($obrasAtivas), 'categoria' => 'Combustível', 'descricao' => '', 'valor' => '', 'forma' => 'Pix'];
if ($eid = (int)($_GET['editar'] ?? 0)) {
    $s = $pdo->prepare('SELECT * FROM despesas WHERE id = ?'); $s->execute([$eid]);
    $ed = $s->fetch() ?: $ed;
}
topo('Despesas', 'despesas', $u);
?>
<div class="cab"><div><h1>Despesas</h1><p class="sub">Tudo que sai do caixa no dia a dia: material, combustível, frete, almoço da equipe.</p></div></div>

<form class="cartao" method="post" style="margin-bottom:22px<?= $ed['id'] ? ';border-color:var(--accent)' : '' ?>" id="lancar">
  <h2><?= $ed['id'] ? 'Editar despesa' : 'Lançar despesa' ?></h2>
  <?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>"><input type="hidden" name="volta" value="<?= h($query) ?>">
  <div class="grade g4">
    <div class="campo"><label for="valor">Valor (R$)</label><input id="valor" name="valor" inputmode="decimal" required placeholder="0,00" value="<?= $ed['valor'] !== '' ? number_format((float)$ed['valor'], 2, ',', '.') : '' ?>" style="font-size:20px;font-weight:700" <?= $ed['id'] ? '' : 'autofocus' ?>></div>
    <div class="campo"><label for="categoria">Categoria</label><select id="categoria" name="categoria"><?= opcoes(in_array($ed['categoria'], FA_CATEGORIAS, true) ? FA_CATEGORIAS : [...FA_CATEGORIAS, $ed['categoria']], $ed['categoria']) ?></select><input type="hidden" name="cat_antiga" value="<?= h($ed['categoria']) ?>"></div>
    <div class="campo"><label for="obra_id">Obra</label><select id="obra_id" name="obra_id"><option value="">Geral, sem obra</option><?= opcoes($ed['id'] ? $obrasTodas : $obrasAtivas, (string)$ed['obra_id'], true) ?></select></div>
    <div class="campo"><label for="data">Data</label><input id="data" type="date" name="data" value="<?= h($ed['data']) ?>" required></div>
    <div class="campo" style="grid-column:span 2"><label for="descricao">O que foi</label><input id="descricao" name="descricao" value="<?= h($ed['descricao']) ?>" placeholder="Ex.: 20 sacos de cimento, loja do Zé"></div>
    <div class="campo"><label for="forma">Pagou com</label><select id="forma" name="forma"><?= opcoes(FA_FORMAS, $ed['forma']) ?></select></div>
    <div class="campo" style="align-self:end"><button class="btn btn-ouro" style="width:100%"><?= $ed['id'] ? 'Salvar mudança' : 'Lançar despesa' ?></button></div>
  </div>
  <?php if ($ed['id']): ?><a href="despesas.php" class="pq">Cancelar edição</a><?php endif ?>
</form>

<nav class="filtros" aria-label="Período">
  <?php foreach (['hoje' => 'Hoje', 'quinzena' => 'Esta quinzena', 'mes' => 'Este mês'] as $k => $r): ?><a href="?p=<?= $k ?><?= $obraF ? "&obra=$obraF" : '' ?>" class="<?= $per === $k ? 'ativo' : '' ?>"><?= $r ?></a><?php endforeach ?>
</nav>
<form class="acoes" method="get" style="margin-bottom:20px"><input type="hidden" name="p" value="livre">
  <label for="ini" style="margin:0">De</label><input id="ini" type="date" name="ini" value="<?= h($ini) ?>" style="width:auto">
  <label for="fim" style="margin:0">até</label><input id="fim" type="date" name="fim" value="<?= h($fim) ?>" style="width:auto">
  <label for="obra" class="esconde-cel" style="margin:0">Obra</label><select id="obra" name="obra" style="width:auto;max-width:260px"><option value="">Todas as obras</option><?= opcoes($obrasTodas, (string)$obraF, true) ?></select>
  <button class="btn btn-pq">Filtrar</button>
</form>

<div class="grade g3" style="margin-bottom:20px">
  <div class="cartao destaque cartao-num" style="border-color:var(--accent)"><span class="num-rot">Total gasto</span><span class="num dinheiro"><?= brl($total) ?></span><span class="pq"><?= data_br($ini) ?><?= $ini !== $fim ? ' a ' . data_br($fim) : '' ?> · <?= count($lista) ?> lançamentos</span></div>
  <div class="cartao"><span class="num-rot">Por categoria</span>
    <?php if (!$porCat): ?><p class="pq">Nada ainda.</p><?php endif ?>
    <ul class="lista-ativ"><?php foreach (array_slice($porCat, 0, 5, true) as $c => $v): ?><li><span><?= h($c) ?></span><b class="dinheiro"><?= brl($v) ?></b></li><?php endforeach ?></ul></div>
  <div class="cartao"><span class="num-rot">Por obra</span>
    <?php if (!$porObra): ?><p class="pq">Nada ainda.</p><?php endif ?>
    <ul class="lista-ativ"><?php foreach (array_slice($porObra, 0, 5, true) as $c => $v): ?><li><span><?= h($c) ?></span><b class="dinheiro"><?= brl($v) ?></b></li><?php endforeach ?></ul></div>
</div>

<?php if (!$lista): ?>
  <div class="cartao vazio"><strong>Nenhuma despesa neste período</strong>Lance acima assim que pagar. Leva dez segundos e o custo de cada obra fica certo.</div>
<?php else: ?>
<div class="tabela-wrap"><table>
  <thead><tr><th>Data</th><th>O que foi</th><th class="esconde-cel">Obra</th><th class="esconde-cel">Pagou com</th><th class="dir">Valor</th><th><span class="sr">Ações</span></th></tr></thead>
  <tbody>
  <?php foreach ($lista as $d): ?>
    <tr><td class="pq" style="white-space:nowrap"><?= data_br($d['data']) ?></td>
      <td><b><?= h($d['categoria']) ?></b><?= $d['descricao'] ? '<div class="pq">' . h($d['descricao']) . '</div>' : '' ?></td>
      <td class="esconde-cel"><?= h($d['titulo'] ?: 'Geral') ?></td><td class="esconde-cel"><?= h($d['forma']) ?></td>
      <td class="dir forte"><?= brl((float)$d['valor']) ?></td>
      <td style="white-space:nowrap"><a class="btn btn-pq" href="?<?= h($query) ?>&editar=<?= $d['id'] ?>#lancar">Editar</a>
        <form method="post" style="display:inline" data-confirma="Apagar a despesa de <?= brl((float)$d['valor']) ?> (<?= h($d['categoria']) ?>)?"><?= campo_csrf() ?><input type="hidden" name="acao" value="apagar"><input type="hidden" name="id" value="<?= $d['id'] ?>"><input type="hidden" name="volta" value="<?= h($query) ?>"><button class="btn btn-perigo btn-pq">Apagar</button></form></td></tr>
  <?php endforeach ?>
  </tbody>
  <tfoot><tr><td colspan="2">Total</td><td class="esconde-cel"></td><td class="esconde-cel"></td><td class="dir"><?= brl($total) ?></td><td></td></tr></tfoot>
</table></div>
<?php endif;
rodape();
