<?php
// Colar as linhas da planilha do Excel: prévia com conferência, depois grava diárias e despesas.
declare(strict_types=1);
require_once __DIR__ . '/../inc/planilha.php';
$u = exigir_login();
checar_csrf();

$texto = (string)($_POST['texto'] ?? '');
$acao = $_POST['acao'] ?? '';
$p = $texto !== '' ? ler_planilha($texto) : null;

if ($acao === 'gravar' && $p && empty($p['erro'])) {
    $n = gravar_planilha($p);
    avisar('Planilha lançada: ' . $n['dias'] . ' dias, ' . $n['diarias'] . ' diárias e ' . $n['despesas'] . ' despesas.'
        . ($n['novos'] ? ' Novos no cadastro: ' . implode(', ', $n['novos']) . '.' : '')
        . ($n['pulados'] ? ' ' . $n['pulados'] . ' dias de folha já fechada ficaram como estavam.' : ''));
    $ult = array_key_last($p['dias']);
    ir('diarias.php?v=grade&d=' . $ult);
}

topo('Colar da planilha', 'diarias', $u);
?>
<a href="diarias.php" class="voltar">← Diárias</a>
<div class="cab" style="margin-top:12px"><div><span class="kicker">Diárias e despesas</span><h1>Colar da planilha</h1>
  <p class="sub">No Excel, selecione o cabeçalho e as linhas dos dias, copie e cole aqui. Antes de gravar, o sistema mostra o resumo para você conferir.</p></div></div>

<?php if ($p && !empty($p['erro'])): ?><div class="aviso aviso-erro"><?= h($p['erro']) ?></div><?php endif ?>

<?php if (!$p || !empty($p['erro'])): ?>
<form method="post" class="cartao">
  <?= campo_csrf() ?><input type="hidden" name="acao" value="previa">
  <label for="texto" style="font-weight:700">Linhas da planilha</label>
  <p class="pq" style="margin:4px 0 10px">Primeira linha: Data, os nomes, Combustivel, Café da manhã, Almoço, Valor. Pode colar só os dias novos, sempre com o cabeçalho.</p>
  <textarea id="texto" name="texto" rows="12" required style="width:100%;font:14px/1.4 ui-monospace,Menlo,monospace;white-space:pre;overflow:auto" placeholder="Data&#9;Egmar&#9;Erisvaldo&#9;...&#9;Combustivel&#9;Café da manhã&#9;Almoço&#9;Valor"><?= h($texto) ?></textarea>
  <button class="btn btn-ouro" style="margin-top:12px">Ver o resumo</button>
</form>
<?php else:
  $resumo = resumo_planilha($p);
  $primeiro = array_key_first($p['dias']); $ultimo = array_key_last($p['dias']);
?>
<section class="cartao">
  <h2>O que vai entrar</h2>
  <p class="pq" style="margin-top:0"><?= count($p['dias']) ?> dias, de <?= data_br($primeiro) ?> a <?= data_br($ultimo) ?>. Cada dia colado substitui as diárias e as despesas da planilha daquele dia.</p>
  <?php if ($p['avisos']): ?><div class="aviso" style="margin:12px 0"><b>Confira</b><ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($p['avisos'] as $a): ?><li><?= h($a) ?></li><?php endforeach ?></ul></div><?php endif ?>
</section>

<?php foreach ($resumo as $q):
  $bruto = array_sum(array_column($q['pessoas'], 'total')); $desp = array_sum($q['despesas']); ?>
<section class="cartao" style="margin-top:16px">
  <div class="cab" style="margin:0 0 8px;align-items:center"><h2 style="margin:0">Quinzena <?= quinzena_rotulo($q['inicio'], $q['fim']) ?></h2>
    <?php if ($q['fechada']): ?><span class="selo">Folha já fechada, não muda</span><?php endif ?></div>
  <div class="tabela-wrap"><table>
    <thead><tr><th>Colaborador</th><th>Função</th><th style="text-align:right">Diária</th><th style="text-align:right">Dias</th><th style="text-align:right">Total</th></tr></thead>
    <tbody>
    <?php foreach ($q['pessoas'] as $nome => $x): ?>
      <tr><td><b><?= h($nome) ?></b><?= $x['novo'] ? ' <span class="selo">novo no cadastro</span>' : '' ?></td><td><?= h($x['funcao']) ?></td>
        <td style="text-align:right"><?= brl($x['diaria']) ?></td><td style="text-align:right"><?= dias_br($x['dias']) ?></td><td style="text-align:right"><?= brl($x['total']) ?></td></tr>
    <?php endforeach ?>
    </tbody>
    <tfoot><tr><th colspan="4">Diárias</th><th style="text-align:right"><?= brl($bruto) ?></th></tr>
    <?php foreach ($q['despesas'] as $cat => $v): ?><tr><td colspan="4"><?= h($cat) ?></td><td style="text-align:right"><?= brl($v) ?></td></tr><?php endforeach ?>
    <tr><th colspan="4">Diárias e despesas</th><th style="text-align:right"><?= brl($bruto + $desp) ?></th></tr></tfoot>
  </table></div>
</section>
<?php endforeach ?>

<form method="post" class="cartao" style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
  <?= campo_csrf() ?><input type="hidden" name="acao" value="gravar">
  <textarea name="texto" hidden><?= h($texto) ?></textarea>
  <button class="btn btn-ouro">Gravar no sistema</button>
  <a class="btn" href="planilha.php">Colar de novo</a>
  <span class="pq">Vales e parcelas não vêm da planilha. Lance no fechamento.</span>
</form>
<?php endif;
rodape();
