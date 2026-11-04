<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();
$pdo = db();
$ok_data = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);

// período: o ciclo da data pedida, ou datas escolhidas à mão
$gi = $_GET['ini'] ?? $_POST['ini'] ?? '';
$gf = $_GET['fim'] ?? $_POST['fim'] ?? '';
if ($ok_data($gi) && $ok_data($gf) && $gf >= $gi && (strtotime($gf) - strtotime($gi)) / 86400 <= 40) { $ini = $gi; $fim = $gf; }
else [$ini, $fim] = quinzena($ok_data($gi) ? $gi : date('Y-m-d'));
$qs = 'ini=' . $ini . '&fim=' . $fim;

$st = $pdo->prepare('SELECT * FROM fechamentos WHERE inicio = ? AND fim = ?');
$st->execute([$ini, $fim]);
$fech = $st->fetch() ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'fechar' && !$fech) {
        $sobrepoe = $pdo->prepare('SELECT * FROM fechamentos WHERE inicio <= ? AND fim >= ?');
        $sobrepoe->execute([$fim, $ini]);
        if ($o = $sobrepoe->fetch()) { avisar('Já existe um fechamento que cobre parte dessas datas (' . quinzena_rotulo($o['inicio'], $o['fim']) . ').', 'erro'); ir("fechamento.php?$qs"); }
        $escolhidas = array_map('intval', (array)($_POST['parc'] ?? []));
        if (!fechar_periodo($ini, $fim, $escolhidas)) { avisar('Não há diárias, vales nem parcelas nesse período para fechar.', 'erro'); ir("fechamento.php?$qs"); }
        avisar('Fechamento feito. Os valores ficaram travados. Agora é pagar e marcar quem já recebeu.');
        ir("fechamento.php?$qs");
    }
    if ($fech && $acao === 'pago') {
        $iid = (int)($_POST['item'] ?? 0);
        $pdo->prepare("UPDATE fechamento_itens SET pago_em = CASE WHEN pago_em IS NULL THEN datetime('now','-3 hours') ELSE NULL END WHERE id = ? AND fechamento_id = ?")->execute([$iid, $fech['id']]);
        ir("fechamento.php?$qs#i$iid");
    }
    if ($fech && $acao === 'todos_pagos') {
        $pdo->prepare("UPDATE fechamento_itens SET pago_em = datetime('now','-3 hours') WHERE fechamento_id = ? AND pago_em IS NULL")->execute([$fech['id']]);
        avisar('Todos marcados como pagos.');
        ir("fechamento.php?$qs");
    }
    if ($fech && $acao === 'reabrir') {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE parcelas SET status = 'pendente', fechamento_id = NULL, descontada_em = NULL WHERE fechamento_id = ?")->execute([$fech['id']]);
        $pdo->prepare('DELETE FROM fechamentos WHERE id = ?')->execute([$fech['id']]);
        $pdo->commit();
        avisar('Fechamento reaberto. As parcelas descontadas nele voltaram a ficar pendentes.');
        ir("fechamento.php?$qs");
    }
    ir("fechamento.php?$qs");
}

[$antI] = quinzena(date('Y-m-d', strtotime("$ini -1 day")));
[$proxI] = quinzena(date('Y-m-d', strtotime("$fim +1 day")));
$terminou = $fim < date('Y-m-d');

if ($fech) {
    $st = $pdo->prepare('SELECT i.*, f.whatsapp FROM fechamento_itens i LEFT JOIN funcionarios f ON f.id = i.funcionario_id WHERE i.fechamento_id = ? ORDER BY i.bruto DESC, i.nome');
    $st->execute([$fech['id']]);
    $linhas = $st->fetchAll();
} else {
    $linhas = [];
    foreach (folha($ini, $fim) as $l) {
        $l['propostas'] = parcelas_propostas((int)$l['id']);
        $l['descontos'] = array_sum(array_map(fn($p) => (float)$p['valor'], $l['propostas']));
        $l['liquido'] = round($l['bruto'] + $l['extras'] - $l['vales'] - $l['descontos'], 2);
        if ($l['dias'] > 0 || $l['vales'] > 0 || $l['descontos'] > 0) $linhas[] = $l;
    }
}
$tot = ['dias' => 0, 'bruto' => 0, 'extras' => 0, 'vales' => 0, 'descontos' => 0, 'liquido' => 0];
foreach ($linhas as $l) foreach ($tot as $k => $_) $tot[$k] += (float)$l[$k];
$st = $pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM despesas WHERE data BETWEEN ? AND ?');
$st->execute([$ini, $fim]);
$despesas = (float)$st->fetchColumn();
$pagos = $fech ? count(array_filter($linhas, fn($l) => $l['pago_em'])) : 0;
$funcs = $pdo->query('SELECT id, nome FROM funcionarios WHERE ativo = 1 ORDER BY nome')->fetchAll(PDO::FETCH_KEY_PAIR);
$historico = $pdo->query('SELECT f.*, (SELECT SUM(liquido) FROM fechamento_itens i WHERE i.fechamento_id = f.id) AS total,
  (SELECT COUNT(*) FROM fechamento_itens i WHERE i.fechamento_id = f.id AND i.pago_em IS NULL) AS faltam
  FROM fechamentos f ORDER BY f.inicio DESC LIMIT 12')->fetchAll();

topo('Fechamento ' . quinzena_rotulo($ini, $fim), 'fechamento', $u);
?>
<div class="cab">
  <div><span class="kicker">Folha de pagamento</span><h1><?= quinzena_rotulo($ini, $fim) ?></h1></div>
  <div class="acoes nao-imprime">
    <a class="btn btn-pq" href="?ini=<?= $antI ?>">← Anterior</a>
    <a class="btn btn-pq" href="?ini=<?= $proxI ?>">Próximo →</a>
    <?php if ($linhas): ?><a class="btn btn-pq btn-ouro" target="_blank" href="relatorio.php?<?= $qs ?>">Relatório completo (PDF)</a><?php endif ?>
  </div>
</div>
<details class="nao-imprime" style="margin:-10px 0 18px"><summary class="pq" style="cursor:pointer">Ajustar as datas deste fechamento</summary>
  <form class="acoes" method="get" style="margin-top:10px"><label for="ini" style="margin:0">De</label><input id="ini" type="date" name="ini" value="<?= $ini ?>" style="width:auto"><label for="fim" style="margin:0">até</label><input id="fim" type="date" name="fim" value="<?= $fim ?>" style="width:auto"><button class="btn btn-pq">Ver período</button></form></details>

<div class="grade g4 metricas" style="margin-bottom:20px">
  <div class="cartao"><span class="num-rot">Mão de obra bruta</span><span class="num dinheiro"><?= brl($tot['bruto'] + $tot['extras']) ?></span><span class="pq"><?= dias_br($tot['dias']) ?> diárias</span></div>
  <div class="cartao"><span class="num-rot">Descontos / já pago</span><span class="num dinheiro"><?= brl($tot['vales'] + $tot['descontos']) ?></span><span class="pq">parcelas <?= brl($tot['descontos']) ?> · vales <?= brl($tot['vales']) ?></span></div>
  <div class="cartao"><span class="num-rot">Mão de obra líquida</span><span class="num dinheiro"><?= brl($tot['liquido']) ?></span><span class="pq"><?= $fech ? "$pagos de " . count($linhas) . ' pagos' : ($terminou ? 'Período terminou, pronto para fechar' : 'Termina em ' . data_br($fim)) ?></span></div>
  <a class="cartao cartao-num destaque" href="despesas.php?p=livre&ini=<?= $ini ?>&fim=<?= $fim ?>"><span class="num-rot">Total a desembolsar</span><span class="num dinheiro"><?= brl($tot['liquido'] + $despesas) ?></span><span class="pq">líquido + <?= brl($despesas) ?> de despesas</span></a>
</div>

<?php if (!$linhas): ?>
  <div class="cartao vazio"><strong>Nada neste período</strong>Quando os dias forem marcados em Diárias, a folha aparece aqui sozinha.<br><a class="btn btn-ouro" href="diarias.php">Marcar diárias</a></div>
<?php else: ?>
<form method="post" id="form-fechar"><?= campo_csrf() ?><input type="hidden" name="acao" value="fechar"><input type="hidden" name="ini" value="<?= $ini ?>"><input type="hidden" name="fim" value="<?= $fim ?>"></form>
<div class="tabela-wrap"><table>
  <thead><tr><th>Colaborador</th><th class="esconde-cel">Função</th><th class="dir">Dias</th><th class="dir">Bruto</th><th>Desconto / já pago</th><th class="dir">Líquido</th><th class="nao-imprime"><?= $fech ? 'Pagamento' : '<span class="sr">Folha</span>' ?></th></tr></thead>
  <tbody>
  <?php foreach ($linhas as $l):
    $primeiro = explode(' ', trim($l['nome']))[0];
    $bruto = (float)$l['bruto'] + (float)$l['extras'];
  ?>
    <tr id="i<?= $l['id'] ?>"><td><b><?= h($l['nome']) ?></b><?= !empty($l['pix']) ? '<div class="pq">Pix: ' . h($l['pix']) . '</div>' : '' ?></td>
      <td class="esconde-cel"><?= h($l['funcao']) ?></td>
      <td class="dir"><?= dias_br((float)$l['dias']) ?></td>
      <td class="dir"><?= brl($bruto) ?><?= $l['extras'] > 0 ? '<div class="pq">inclui ' . brl((float)$l['extras']) . ' de extras</div>' : '' ?></td>
      <td><?php if ($fech): ?>
          <?= ($l['vales'] + $l['descontos']) > 0 ? '<b>' . brl($l['vales'] + $l['descontos']) . '</b><div class="pq">' . h(str_replace(' | ', ' · ', (string)$l['detalhe'])) . '</div>' : '<span class="pq">Nenhum</span>' ?>
        <?php else: ?>
          <?php foreach ($l['propostas'] as $p): ?><label style="font-weight:600;font-size:14px;margin:0 0 4px"><input type="checkbox" name="parc[]" value="<?= $p['id'] ?>" form="form-fechar" checked> <?= brl((float)$p['valor']) ?> · <?= ordinal((int)$p['numero']) ?> de <?= $p['total_parcelas'] ?> <?= h($p['descricao']) ?></label><?php endforeach ?>
          <?php if ($l['vales'] > 0): ?><div class="pq"><?= brl((float)$l['vales']) ?> já pago (vale)</div><?php endif ?>
          <?php if (!$l['propostas'] && $l['vales'] <= 0): ?><span class="pq">Nenhum</span><?php endif ?>
        <?php endif ?></td>
      <td class="dir forte" style="font-size:17px"><?= brl((float)$l['liquido']) ?></td>
      <td class="nao-imprime"><div class="acoes" style="flex-wrap:nowrap">
        <?php if ($fech):
          $msg = "Olá, $primeiro! Folha de " . quinzena_rotulo($ini, $fim) . ":\n" . dias_br((float)$l['dias']) . ' diárias: ' . brl($bruto) .
            ($l['descontos'] + $l['vales'] > 0 ? "\nDescontos: " . str_replace(' | ', ', ', (string)$l['detalhe']) : '') . "\nLíquido a receber: " . brl((float)$l['liquido']) . ($l['pix'] ? "\nPix: {$l['pix']}" : '') . "\nFarias Araújo Construções";
        ?>
          <form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="pago"><input type="hidden" name="ini" value="<?= $ini ?>"><input type="hidden" name="fim" value="<?= $fim ?>"><input type="hidden" name="item" value="<?= $l['id'] ?>">
            <button class="btn btn-pq <?= $l['pago_em'] ? '' : 'btn-ouro' ?>" title="<?= $l['pago_em'] ? 'Toque para desfazer' : 'Marcar como pago' ?>"><?= $l['pago_em'] ? '✓ Pago ' . date('d/m', strtotime($l['pago_em'])) : 'Marcar pago' ?></button></form>
          <?php if (!empty($l['whatsapp'])): ?><a class="btn btn-zap btn-pq" target="_blank" rel="noopener" href="<?= h(whats_link($l['whatsapp'], $msg)) ?>">Avisar</a><?php endif ?>
        <?php endif ?>
        <a class="btn btn-pq" target="_blank" href="relatorio.php?<?= $qs ?>&so=<?= $fech ? 'i' . $l['id'] : 'f' . $l['id'] ?>">Folha</a>
      </div></td></tr>
  <?php endforeach ?>
  </tbody>
  <tfoot><tr><td>Total</td><td class="esconde-cel"></td><td class="dir"><?= dias_br($tot['dias']) ?></td><td class="dir"><?= brl($tot['bruto'] + $tot['extras']) ?></td><td><?= brl($tot['vales'] + $tot['descontos']) ?></td><td class="dir"><?= brl($tot['liquido']) ?></td><td class="nao-imprime"></td></tr></tfoot>
</table></div>
<?php if (!$fech): ?><p class="pq" style="margin-top:10px">Desmarque uma parcela se combinou de não descontar desta vez. O líquido de cada um é recalculado ao fechar.</p><?php endif ?>

<div class="acoes nao-imprime" style="margin-top:18px">
  <?php if (!$fech): ?>
    <button class="btn btn-ouro" form="form-fechar" onclick="return confirm('Fechar a folha de <?= quinzena_rotulo($ini, $fim) ?>? As diárias, vales e parcelas do período ficam travados.<?= $terminou ? '' : ' Atenção: o período ainda não terminou.' ?>')">Fechar a folha</button>
    <a class="btn" href="diarias.php?d=<?= min($fim, date('Y-m-d')) ?>">Conferir diárias</a>
  <?php else: ?>
    <?php if ($pagos < count($linhas)): ?><form method="post" data-confirma="Marcar todos como pagos agora?"><?= campo_csrf() ?><input type="hidden" name="acao" value="todos_pagos"><input type="hidden" name="ini" value="<?= $ini ?>"><input type="hidden" name="fim" value="<?= $fim ?>"><button class="btn btn-ouro">Marcar todos como pagos</button></form><?php endif ?>
    <form method="post" data-confirma="Reabrir o fechamento? Os valores travados são apagados, as parcelas descontadas voltam a ficar pendentes e as marcas de pago somem."><?= campo_csrf() ?><input type="hidden" name="acao" value="reabrir"><input type="hidden" name="ini" value="<?= $ini ?>"><input type="hidden" name="fim" value="<?= $fim ?>"><button class="btn btn-perigo btn-pq">Reabrir fechamento</button></form>
  <?php endif ?>
</div>
<?php endif ?>

<div class="grade g2 nao-imprime" style="margin-top:30px;align-items:start">
  <?php if (!$fech && $funcs): ?>
  <form class="cartao" method="post" action="equipe.php">
    <h2>Lançar vale (já pago)</h2>
    <p class="pq" style="margin-top:-8px">Dinheiro adiantado antes do fechamento. Desconta neste período.</p>
    <?= campo_csrf() ?><input type="hidden" name="acao" value="vale"><input type="hidden" name="voltar" value="fechamento"><input type="hidden" name="ini" value="<?= $ini ?>">
    <div class="grade g2">
      <div class="campo"><label for="vf">Colaborador</label><select id="vf" name="id"><?= opcoes($funcs, null, true) ?></select></div>
      <div class="campo"><label for="vv">Valor (R$)</label><input id="vv" name="valor" inputmode="decimal" required placeholder="0,00"></div>
      <div class="campo"><label for="vd">Data</label><input id="vd" type="date" name="data" value="<?= min(max(date('Y-m-d'), $ini), $fim) ?>" min="<?= $ini ?>" max="<?= $fim ?>"></div>
      <div class="campo"><label for="vo">Motivo <span class="pq">(opcional)</span></label><input id="vo" name="obs"></div>
    </div>
    <button class="btn btn-ouro">Lançar vale</button>
  </form>
  <?php endif ?>
  <section class="cartao">
    <h2>Folhas fechadas</h2>
    <?php if (!$historico): ?><p class="pq">Nenhuma ainda. <a href="importar.php">Importar a folha de 08/09 a 22/09/2026</a></p><?php endif ?>
    <ul class="lista-ativ">
      <?php foreach ($historico as $hq): ?>
        <li><a href="?ini=<?= $hq['inicio'] ?>&fim=<?= $hq['fim'] ?>" style="font-weight:700"><?= quinzena_rotulo($hq['inicio'], $hq['fim']) ?></a><span class="acoes"><b class="dinheiro"><?= brl((float)$hq['total']) ?></b><span class="st <?= $hq['faltam'] ? 'st-aberto' : 'st-pago' ?>"><?= $hq['faltam'] ? $hq['faltam'] . ' a pagar' : 'Pago' ?></span></span></li>
      <?php endforeach ?>
    </ul>
  </section>
</div>
<?php rodape();
