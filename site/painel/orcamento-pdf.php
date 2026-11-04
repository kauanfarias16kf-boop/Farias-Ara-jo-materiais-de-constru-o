<?php
// Documento do orçamento com a marca. O botão usa "Salvar como PDF" do próprio navegador.
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM orcamentos WHERE id = ?'); $st->execute([$id]);
$o = $st->fetch() ?: ir('orcamentos.php');
$st = $pdo->prepare('SELECT * FROM orcamento_itens WHERE orcamento_id = ? ORDER BY ordem, id'); $st->execute([$id]);
$itens = $st->fetchAll();
$sub = 0.0;
foreach ($itens as $it) $sub += $it['quantidade'] * $it['valor_unit'];
$total = max(0, $sub - (float)$o['desconto']);
$emissao = strtotime($o['criado_em']);
$valido = date('d/m/Y', strtotime('+' . (int)$o['validade_dias'] . ' days', $emissao));
$qtd = fn($n) => rtrim(rtrim(number_format((float)$n, 2, ',', '.'), '0'), ',');
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Orçamento <?= h($o['numero']) ?> · <?= h($o['cliente_nome']) ?> · Farias Araújo</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600&family=IBM+Plex+Mono:wght@400;500&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  @page{size:A4;margin:0}
  *{box-sizing:border-box}
  body{margin:0;background:#2a2b30;font:13px/1.5 'Manrope',system-ui,sans-serif;color:#1d1e22;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .ferramentas{position:sticky;top:0;display:flex;gap:10px;justify-content:center;padding:12px;background:#121316;z-index:2}
  .ferramentas button,.ferramentas a{font:600 14px 'Manrope',sans-serif;padding:10px 18px;border-radius:10px;border:1px solid #c9a04a;background:#c9a04a;color:#16130c;cursor:pointer;text-decoration:none}
  .ferramentas a{background:transparent;color:#ece7dd;border-color:rgba(236,231,221,.35)}
  .folha{width:210mm;min-height:297mm;margin:20px auto;background:#f7f5f1;position:relative;overflow:hidden;display:flex;flex-direction:column}
  .topo{background:#141518;color:#ece7dd;padding:14mm 16mm 10mm;display:flex;justify-content:space-between;align-items:flex-end;gap:10mm;position:relative}
  .topo:after{content:"";position:absolute;left:0;right:0;bottom:0;height:3px;background:linear-gradient(90deg,#8a6a2a,#c9a04a 40%,#e7c97e 60%,#c9a04a)}
  .topo img{width:62mm;height:auto;display:block}
  .doc{text-align:right}
  .doc .k{font:500 9px 'IBM Plex Mono',monospace;letter-spacing:.2em;text-transform:uppercase;color:#c9a04a}
  .doc h1{font:600 22px/1.1 'Cinzel',serif;letter-spacing:.08em;margin:4px 0 6px}
  .doc p{margin:0;color:#b9b3a8;font-size:11px}
  .corpo{padding:10mm 16mm;flex:1}
  .blocos{display:grid;grid-template-columns:1.2fr 1fr;gap:8mm;margin-bottom:8mm}
  .rot{font:500 8.5px 'IBM Plex Mono',monospace;letter-spacing:.18em;text-transform:uppercase;color:#8a6a2a;margin-bottom:3px}
  .blocos p{margin:0}
  .titulo{font:600 15px 'Cinzel',serif;letter-spacing:.05em;margin:0 0 6mm;padding-bottom:4mm;border-bottom:1px solid #d9d3c8}
  table{width:100%;border-collapse:collapse}
  th{font:500 8.5px 'IBM Plex Mono',monospace;letter-spacing:.14em;text-transform:uppercase;color:#6b675f;text-align:left;padding:0 6px 6px;border-bottom:1.5px solid #1d1e22}
  td{padding:7px 6px;border-bottom:1px solid #e2ddd3;vertical-align:top}
  .n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
  .tot{margin-left:auto;width:78mm;margin-top:5mm}
  .tot div{display:flex;justify-content:space-between;padding:3px 6px}
  .tot .final{margin-top:4px;padding:9px 10px;background:#141518;color:#ece7dd;border-radius:6px;font:600 15px 'Cinzel',serif;letter-spacing:.04em}
  .tot .final b{color:#ddb65e}
  .cond{display:grid;grid-template-columns:1fr 1fr;gap:8mm;margin-top:8mm}
  .cond p{margin:0;white-space:pre-line}
  .assina{display:grid;grid-template-columns:1fr 1fr;gap:14mm;margin-top:16mm}
  .assina div{border-top:1px solid #1d1e22;padding-top:4px;font-size:11px;color:#55524c}
  .rodape{padding:6mm 16mm;background:#e9e5dd;display:flex;justify-content:space-between;gap:6mm;font-size:10.5px;color:#4a4740;flex-wrap:wrap}
  .rodape b{color:#1d1e22}
  @media print{body{background:#fff}.ferramentas{display:none}.folha{margin:0;width:auto;min-height:100vh}}
  @media screen and (max-width:820px){.folha{width:100%;min-height:auto;margin:0}.topo,.corpo,.rodape{padding-left:18px;padding-right:18px}.blocos,.cond{grid-template-columns:1fr}.tot{width:100%}}
</style>
</head>
<body>
<div class="ferramentas"><button onclick="print()">Baixar PDF</button><a href="orcamento.php?id=<?= $id ?>">Voltar e editar</a></div>
<article class="folha">
  <header class="topo">
    <img src="/assets/img/logo.webp" alt="Farias Araújo Construções Pré Moldadas">
    <div class="doc"><div class="k">Orçamento</div><h1>Nº <?= h($o['numero']) ?></h1><p>Emitido em <?= date('d/m/Y', $emissao) ?> · válido até <?= $valido ?></p></div>
  </header>
  <div class="corpo">
    <div class="blocos">
      <div><div class="rot">Cliente</div><p><b><?= h($o['cliente_nome']) ?></b></p><?php if ($o['cliente_whatsapp']): ?><p><?= h($o['cliente_whatsapp']) ?></p><?php endif ?><?php if ($o['cidade']): ?><p><?= h($o['cidade']) ?></p><?php endif ?></div>
      <div><div class="rot">Prazo de execução</div><p><?= h($o['prazo_execucao'] ?: 'A combinar') ?></p></div>
    </div>
    <h2 class="titulo"><?= h($o['titulo']) ?></h2>
    <table>
      <thead><tr><th style="width:38px">#</th><th>Descrição</th><th>Un.</th><th class="n">Qtd.</th><th class="n">Valor unit.</th><th class="n">Total</th></tr></thead>
      <tbody>
      <?php foreach ($itens as $i => $it): ?>
        <tr><td><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></td><td><?= h($it['descricao']) ?></td><td><?= h($it['unidade']) ?></td><td class="n"><?= $qtd($it['quantidade']) ?></td><td class="n"><?= brl((float)$it['valor_unit']) ?></td><td class="n"><?= brl($it['quantidade'] * $it['valor_unit']) ?></td></tr>
      <?php endforeach ?>
      <?php if (!$itens): ?><tr><td colspan="6" style="color:#8a857c">Nenhum item adicionado.</td></tr><?php endif ?>
      </tbody>
    </table>
    <div class="tot">
      <div><span>Subtotal</span><span class="n"><?= brl($sub) ?></span></div>
      <?php if ((float)$o['desconto'] > 0): ?><div><span>Desconto</span><span class="n">− <?= brl((float)$o['desconto']) ?></span></div><?php endif ?>
      <div class="final"><span>Total</span><b class="n"><?= brl($total) ?></b></div>
    </div>
    <div class="cond">
      <?php if ($o['condicoes']): ?><div><div class="rot">Pagamento e condições</div><p><?= h($o['condicoes']) ?></p></div><?php endif ?>
      <?php if ($o['observacoes']): ?><div><div class="rot">Observações</div><p><?= h($o['observacoes']) ?></p></div><?php endif ?>
    </div>
    <div class="assina"><div>Farias Araújo Construções Pré Moldadas</div><div>De acordo: <?= h($o['cliente_nome']) ?></div></div>
  </div>
  <footer class="rodape">
    <span><b>Kauan</b> 61 99440-8678 · <b>David</b> 61 99938-2439</span>
    <span><?= FA_EMAIL ?></span>
    <span>@obras_contrucoes.fariasaraujo</span>
  </footer>
</article>
</body>
</html>
