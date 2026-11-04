<?php
// Relatório geral do período + folha individual de cada colaborador (modelo da Farias Araújo).
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
$pdo = db();
$ok = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
$ini = $ok($_GET['ini'] ?? '') ? $_GET['ini'] : quinzena(date('Y-m-d'))[0];
$fim = $ok($_GET['fim'] ?? '') ? $_GET['fim'] : quinzena($ini)[1];
$so = (string)($_GET['so'] ?? '');

$st = $pdo->prepare('SELECT * FROM fechamentos WHERE inicio = ? AND fim = ?');
$st->execute([$ini, $fim]);
$fech = $st->fetch() ?: null;

// linhas: snapshot do fechamento, ou prévia
$linhas = [];
if ($fech) {
    $st = $pdo->prepare('SELECT * FROM fechamento_itens WHERE fechamento_id = ? ORDER BY bruto DESC, nome');
    $st->execute([$fech['id']]);
    foreach ($st->fetchAll() as $i) {
        $q = $pdo->prepare('SELECT p.*, pl.descricao FROM parcelas p JOIN parcelamentos pl ON pl.id = p.parcelamento_id WHERE p.fechamento_id = ? AND pl.funcionario_id = ? ORDER BY pl.id');
        $q->execute([$fech['id'], (int)$i['funcionario_id']]);
        $i['parcelas_aqui'] = $q->fetchAll();
        $i['chave'] = 'i' . $i['id'];
        $linhas[] = $i;
    }
} else {
    foreach (folha($ini, $fim) as $l) {
        $l['parcelas_aqui'] = parcelas_propostas((int)$l['id']);
        $l['descontos'] = array_sum(array_map(fn($p) => (float)$p['valor'], $l['parcelas_aqui']));
        $l['liquido'] = round($l['bruto'] + $l['extras'] - $l['vales'] - $l['descontos'], 2);
        $l['funcionario_id'] = $l['id'];
        $l['chave'] = 'f' . $l['id'];
        if ($l['dias'] > 0 || $l['vales'] > 0 || $l['descontos'] > 0) $linhas[] = $l;
    }
    usort($linhas, fn($a, $b) => $b['bruto'] <=> $a['bruto']);
}
if ($so !== '') $linhas = array_values(array_filter($linhas, fn($l) => $l['chave'] === $so));

$tot = ['bruto' => 0, 'desc' => 0, 'liq' => 0];
foreach ($linhas as $l) { $tot['bruto'] += $l['bruto'] + $l['extras']; $tot['desc'] += $l['vales'] + $l['descontos']; $tot['liq'] += $l['liquido']; }
$st = $pdo->prepare('SELECT categoria, SUM(valor) AS v FROM despesas WHERE data BETWEEN ? AND ? GROUP BY categoria ORDER BY v DESC');
$st->execute([$ini, $fim]);
$desp = $st->fetchAll();
$totDesp = array_sum(array_map(fn($d) => (float)$d['v'], $desp));

$notas = [];
foreach ($linhas as $l) {
    $partes = [];
    foreach ($l['parcelas_aqui'] as $p) $partes[] = brl((float)$p['valor']) . ' · ' . ordinal((int)$p['numero']) . ' parcela ' . $p['descricao'];
    if ($l['vales'] > 0) $partes[] = brl((float)$l['vales']) . ' já pago, restando ' . brl((float)$l['liquido']);
    if ($partes) $notas[] = '<b>' . h($l['nome']) . ':</b> ' . h(implode(', ', $partes));
}
$periodo = data_br($ini) . ' a ' . data_br($fim);
$dias_semana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $so ? 'Folha ' . h($linhas[0]['nome'] ?? '') : 'Folha de pagamento' ?> · <?= $periodo ?> · Farias Araújo</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  @page{size:A4;margin:12mm}
  *{box-sizing:border-box}
  body{margin:0;background:#2a2b30;font:13px/1.45 'Manrope',sans-serif;color:#17181b;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .ferramentas{position:sticky;top:0;z-index:2;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;padding:12px;background:#121316}
  .ferramentas button,.ferramentas a{font:700 15px 'Manrope',sans-serif;padding:11px 20px;border-radius:10px;border:1px solid #d1a74e;background:#d1a74e;color:#16130c;cursor:pointer;text-decoration:none}
  .ferramentas a{background:transparent;color:#f2eee6;border-color:rgba(242,238,230,.4)}
  .ferramentas span{color:#f0c46a;font-weight:700;align-self:center}
  .pagina{width:186mm;min-height:270mm;margin:20px auto;background:#fff;padding:12mm;break-after:page;page-break-after:always}
  .pagina:last-child{break-after:auto;page-break-after:auto}
  .topo{display:flex;align-items:center;justify-content:space-between;gap:10mm;border-bottom:3px solid #b8862b;padding-bottom:5mm;margin-bottom:6mm}
  .topo img{width:40mm;height:auto;filter:brightness(.22) sepia(.5)}
  .topo h1{font:600 19px/1.2 'Cinzel',serif;letter-spacing:.05em;margin:0;text-align:right}
  .topo p{margin:3px 0 0;text-align:right;font-weight:600;color:#55524c}
  .rascunho{display:inline-block;margin-top:4px;padding:2px 8px;border:1.5px solid #b23a24;color:#b23a24;border-radius:4px;font-weight:800;font-size:11px;letter-spacing:.08em}
  .quadro{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #c9c3b7;border-radius:6px;overflow:hidden;margin-bottom:7mm}
  .quadro div{padding:8px 10px;text-align:center;border-right:1px solid #c9c3b7}
  .quadro div:last-child{border-right:0;background:#fbf3df}
  .quadro small{display:block;font-weight:800;font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#fff;background:#1d1e22;margin:-8px -10px 8px;padding:7px 4px}
  .quadro b{font-size:15px}
  h2{font:800 13px 'Manrope',sans-serif;letter-spacing:.08em;text-transform:uppercase;margin:6mm 0 2.5mm;color:#1d1e22}
  table{width:100%;border-collapse:collapse;margin-bottom:3mm}
  th{background:#1d1e22;color:#fff;font-weight:700;font-size:11.5px;letter-spacing:.03em;padding:7px 8px;text-align:left}
  td{padding:6px 8px;border-bottom:1px solid #e3ded4}
  .n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
  tr.total td{background:#eef3e6;font-weight:800;border-top:1.5px solid #1d1e22}
  tr.liquido td{background:#fbf3df;font-weight:800;font-size:14px;border-top:1.5px solid #1d1e22}
  tr.paga td{background:#f1f6ea}
  tr.agora td{background:#fff4d6;font-weight:700}
  .duas{display:grid;grid-template-columns:1.1fr 1fr;gap:6mm;align-items:start}
  .notas{margin-top:5mm;font-size:12px;line-height:1.6;padding:8px 10px;border-left:3px solid #b8862b;background:#faf8f3}
  .resumo-parc{font-size:12px;margin-top:2mm}
  .assina{margin:16mm auto 0;width:80mm;text-align:center;border-top:1px solid #17181b;padding-top:4px;font-size:12px}
  .assina p{margin:6px 0 0;color:#55524c}
  .rodape{margin-top:8mm;font-size:10.5px;color:#7a766e;text-align:center}
  @media print{body{background:#fff}.ferramentas{display:none}.pagina{margin:0;width:auto;min-height:0;padding:0}}
  @media screen and (max-width:820px){.pagina{width:auto;min-height:0;margin:0 0 10px;padding:18px 14px}.quadro{grid-template-columns:1fr 1fr}.duas{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="ferramentas"><button onclick="print()">Imprimir ou salvar PDF</button><a href="fechamento.php?ini=<?= $ini ?>&fim=<?= $fim ?>">Voltar ao fechamento</a><?php if (!$fech): ?><span>Prévia: a folha ainda não foi fechada</span><?php endif ?></div>

<?php if ($so === '' && $linhas): ?>
<section class="pagina">
  <div class="topo"><img src="/assets/img/logo.webp" alt="Farias Araújo Construções Pré Moldadas"><div><h1>Relatório geral de despesas da obra</h1><p><?= $periodo ?></p><?php if (!$fech): ?><span class="rascunho">PRÉVIA</span><?php endif ?></div></div>
  <div class="quadro">
    <div><small>Mão de obra bruta</small><b><?= brl($tot['bruto']) ?></b></div>
    <div><small>Descontos / já pago</small><b><?= brl($tot['desc']) ?></b></div>
    <div><small>Mão de obra líquida</small><b><?= brl($tot['liq']) ?></b></div>
    <div><small>Total a desembolsar</small><b><?= brl($tot['liq'] + $totDesp) ?></b></div>
  </div>
  <h2>Resumo da mão de obra</h2>
  <table>
    <tr><th>Colaborador</th><th>Função</th><th class="n">Dias</th><th class="n">Bruto</th><th class="n">Desconto/Pago</th><th class="n">Líquido</th></tr>
    <?php foreach ($linhas as $l): ?><tr><td><?= h($l['nome']) ?></td><td><?= h($l['funcao']) ?></td><td class="n"><?= dias_br((float)$l['dias']) ?></td><td class="n"><?= brl($l['bruto'] + $l['extras']) ?></td><td class="n"><?= brl($l['vales'] + $l['descontos']) ?></td><td class="n"><?= brl((float)$l['liquido']) ?></td></tr><?php endforeach ?>
    <tr class="total"><td colspan="3">TOTAL</td><td class="n"><?= brl($tot['bruto']) ?></td><td class="n"><?= brl($tot['desc']) ?></td><td class="n"><?= brl($tot['liq']) ?></td></tr>
  </table>
  <div class="duas">
    <div><h2>Despesas operacionais</h2>
      <table><tr><th>Despesa</th><th class="n">Valor</th></tr>
        <?php foreach ($desp as $d): ?><tr><td><?= h($d['categoria']) ?></td><td class="n"><?= brl((float)$d['v']) ?></td></tr><?php endforeach ?>
        <?php if (!$desp): ?><tr><td colspan="2">Nenhuma despesa lançada no período.</td></tr><?php endif ?>
        <tr class="total"><td>TOTAL</td><td class="n"><?= brl($totDesp) ?></td></tr></table></div>
    <div><h2>Fechamento</h2>
      <table><tr><th>Item</th><th class="n">Valor</th></tr>
        <tr><td>Mão de obra líquida</td><td class="n"><?= brl($tot['liq']) ?></td></tr>
        <tr><td>Despesas operacionais</td><td class="n"><?= brl($totDesp) ?></td></tr>
        <tr class="liquido"><td>TOTAL GERAL</td><td class="n"><?= brl($tot['liq'] + $totDesp) ?></td></tr></table></div>
  </div>
  <?php if ($notas): ?><div class="notas"><?= implode(' &nbsp;|&nbsp; ', $notas) ?></div><?php endif ?>
  <div class="assina">Responsável pela obra</div>
</section>
<?php endif ?>

<?php foreach ($linhas as $l):
  $st = $pdo->prepare('SELECT data, fracao, valor, extra FROM diarias WHERE funcionario_id = ? AND data BETWEEN ? AND ? ORDER BY data');
  $st->execute([(int)$l['funcionario_id'], $ini, $fim]);
  $dias = $st->fetchAll();
  $st = $pdo->prepare('SELECT * FROM parcelamentos WHERE funcionario_id = ? ORDER BY id');
  $st->execute([(int)$l['funcionario_id']]);
  $planos = $st->fetchAll();
  $idsAqui = array_map(fn($p) => (int)$p['id'], $l['parcelas_aqui']);
?>
<section class="pagina">
  <div class="topo"><img src="/assets/img/logo.webp" alt="Farias Araújo Construções Pré Moldadas"><div><h1>Folha de pagamento individual</h1><p><?= h($l['nome']) ?><?= $l['funcao'] ? ' · ' . h($l['funcao']) : '' ?></p><p>Período: <?= $periodo ?></p><?php if (!$fech): ?><span class="rascunho">PRÉVIA</span><?php endif ?></div></div>
  <table>
    <tr><th>Data</th><th>Dia da semana</th><th class="n">Valor</th></tr>
    <?php foreach ($dias as $d): $v = $d['fracao'] * $d['valor'] + $d['extra']; ?>
      <tr><td><?= data_br($d['data']) ?></td><td><?= $dias_semana[(int)date('w', strtotime($d['data']))] ?><?= $d['fracao'] < 1 ? ' (meia diária)' : '' ?><?= $d['extra'] > 0 ? ' + extra ' . brl((float)$d['extra']) : '' ?></td><td class="n"><?= brl($v) ?></td></tr>
    <?php endforeach ?>
    <?php if (!$dias): ?><tr><td colspan="3">Nenhum dia trabalhado no período.</td></tr><?php endif ?>
    <tr class="total"><td colspan="2">TOTAL · <?= dias_br((float)$l['dias']) ?> <?= (float)$l['dias'] == 1 ? 'diária' : 'diárias' ?></td><td class="n"><?= brl($l['bruto'] + $l['extras']) ?></td></tr>
  </table>
  <h2>Resumo</h2>
  <table>
    <tr><th>Item</th><th class="n">Valor</th></tr>
    <tr><td>Diárias</td><td class="n"><?= brl((float)$l['bruto']) ?></td></tr>
    <?php if ($l['extras'] > 0): ?><tr><td>Extras</td><td class="n"><?= brl((float)$l['extras']) ?></td></tr><?php endif ?>
    <?php foreach ($l['parcelas_aqui'] as $p): ?><tr><td>(−) <?= ordinal((int)$p['numero']) ?> parcela <?= h($p['descricao']) ?></td><td class="n"><?= brl((float)$p['valor']) ?></td></tr><?php endforeach ?>
    <?php if ($l['vales'] > 0): ?><tr><td>(−) Valor já pago</td><td class="n"><?= brl((float)$l['vales']) ?></td></tr><?php endif ?>
    <tr class="liquido"><td>LÍQUIDO A RECEBER</td><td class="n"><?= brl((float)$l['liquido']) ?></td></tr>
  </table>
  <?php foreach ($planos as $pl):
    $st = $pdo->prepare('SELECT * FROM parcelas WHERE parcelamento_id = ? ORDER BY numero'); $st->execute([$pl['id']]); $ps = $st->fetchAll();
    $r = resumo_parcelamento((int)$pl['id']);
    $pagoAgora = $fech ? 0 : array_sum(array_map(fn($p) => in_array((int)$p['id'], $idsAqui, true) ? (float)$p['valor'] : 0, $ps));
  ?>
    <h2>Controle de parcelas · <?= h($pl['descricao']) ?></h2>
    <table>
      <tr><th>Parcela</th><th class="n">Valor</th><th>Situação</th></tr>
      <?php foreach ($ps as $p): $aqui = in_array((int)$p['id'], $idsAqui, true); ?>
        <tr class="<?= $aqui ? 'agora' : ($p['status'] === 'descontada' ? 'paga' : '') ?>"><td><?= ordinal((int)$p['numero']) ?></td><td class="n"><?= brl((float)$p['valor']) ?></td><td><?= $aqui ? 'DESCONTADA NESTA FOLHA' : ($p['status'] === 'descontada' ? 'PAGA · DESCONTADA' : 'PENDENTE') ?></td></tr>
      <?php endforeach ?>
      <tr class="total"><td>TOTAL</td><td class="n"><?= brl($r['total']) ?></td><td></td></tr>
    </table>
    <p class="resumo-parc"><b>Total:</b> <?= brl($r['total']) ?> · <b>Pago:</b> <?= brl($r['pago'] + $pagoAgora) ?> · <b>Saldo:</b> <?= brl($r['saldo'] - $pagoAgora) ?></p>
  <?php endforeach ?>
  <div class="assina">Assinatura · <?= h($l['nome']) ?><p>Data: ______/______/<?= date('Y', strtotime($fim)) ?></p></div>
  <p class="rodape">Farias Araújo Construções Pré Moldadas · <?= FA_EMAIL ?></p>
</section>
<?php endforeach ?>
<?php if (!$linhas): ?><section class="pagina"><p>Nada a mostrar neste período.</p></section><?php endif ?>
</body>
</html>
