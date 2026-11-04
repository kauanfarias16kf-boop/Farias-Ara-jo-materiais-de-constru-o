<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
$pdo = db();

$data = (string)($_GET['d'] ?? $_POST['d'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !strtotime($data)) $data = date('Y-m-d');

// ---------- toque numa célula da grade (salva na hora) ----------
if (($_POST['acao'] ?? '') === 'celula') {
    header('Content-Type: application/json; charset=utf-8');
    iniciar_sessao();
    $responde = function (int $c, array $j) { http_response_code($c); echo json_encode($j, JSON_UNESCAPED_UNICODE); exit; };
    if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) $responde(400, ['ok' => false, 'erro' => 'A página ficou aberta muito tempo. Recarregue.']);
    $fid = (int)($_POST['f'] ?? 0);
    $dia = (string)($_POST['dia'] ?? '');
    $v = in_array($_POST['v'] ?? '', ['0', '0.5', '1'], true) ? (float)$_POST['v'] : -1;
    if (!$fid || $v < 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) $responde(422, ['ok' => false, 'erro' => 'Dados inválidos.']);
    if ($fe = fechamento_da_data($dia)) $responde(409, ['ok' => false, 'erro' => 'Folha de ' . quinzena_rotulo($fe['inicio'], $fe['fim']) . ' já fechada.']);
    $st = $pdo->prepare('SELECT diaria FROM funcionarios WHERE id = ?'); $st->execute([$fid]);
    $diaria = $st->fetchColumn();
    if ($diaria === false) $responde(404, ['ok' => false, 'erro' => 'Colaborador não encontrado.']);
    if ($v <= 0) {
        $pdo->prepare('DELETE FROM diarias WHERE funcionario_id = ? AND data = ?')->execute([$fid, $dia]);
    } else {
        $obra = ((int)($_POST['o'] ?? 0)) ?: null;
        $pdo->prepare('INSERT INTO diarias(funcionario_id, data, obra_id, fracao, valor, extra) VALUES(?,?,?,?,?,0)
            ON CONFLICT(funcionario_id, data) DO UPDATE SET fracao = excluded.fracao, obra_id = COALESCE(diarias.obra_id, excluded.obra_id)')
            ->execute([$fid, $dia, $obra, $v, (float)$diaria]);
    }
    $responde(200, ['ok' => true]);
}

checar_csrf();
$fech = fechamento_da_data($data);

// ---------- salvar o dia inteiro (aba "Marcar o dia") ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($fech) { avisar('Essa folha já foi fechada. Para mudar, reabra no Fechamento.', 'erro'); ir("diarias.php?d=$data&v=dia"); }
    $diarias = $pdo->query('SELECT id, diaria FROM funcionarios')->fetchAll(PDO::FETCH_KEY_PAIR);
    $up = $pdo->prepare('INSERT INTO diarias(funcionario_id, data, obra_id, fracao, valor, extra, obs) VALUES(?,?,?,?,?,?,?)
        ON CONFLICT(funcionario_id, data) DO UPDATE SET obra_id = excluded.obra_id, fracao = excluded.fracao, extra = excluded.extra, obs = excluded.obs');
    $rm = $pdo->prepare('DELETE FROM diarias WHERE funcionario_id = ? AND data = ?');
    $n = 0.0;
    $pdo->beginTransaction();
    foreach ((array)($_POST['f'] ?? []) as $fid => $fr) {
        $fid = (int)$fid;
        if (!isset($diarias[$fid])) continue;
        $fr = in_array($fr, ['0.5', '1'], true) ? (float)$fr : 0.0;
        if ($fr <= 0) { $rm->execute([$fid, $data]); continue; }
        $up->execute([$fid, $data, ((int)($_POST['o'][$fid] ?? 0)) ?: null, $fr, (float)$diarias[$fid], num_br((string)($_POST['x'][$fid] ?? '')), mb_substr(trim((string)($_POST['n'][$fid] ?? '')), 0, 200)]);
        $n += $fr;
    }
    $pdo->commit();
    avisar('Dia ' . data_br($data) . ' salvo: ' . dias_br($n) . ($n == 1 ? ' diária.' : ' diárias.'));
    ir("diarias.php?d=$data&v=dia");
}

// ---------- dados ----------
$obras = $pdo->query("SELECT id, titulo FROM obras WHERE status IN ('em_andamento','planejada','pausada') ORDER BY status = 'em_andamento' DESC, id DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
$obraPadrao = array_key_first($obras);
[$qi, $qf] = quinzena($data);
$fechQ = fechamento_da_data($qi);
$diasQ = [];
for ($t = strtotime($qi); $t <= strtotime($qf); $t = strtotime('+1 day', $t)) $diasQ[] = date('Y-m-d', $t);

$st = $pdo->prepare('SELECT f.* FROM funcionarios f WHERE f.ativo = 1 OR EXISTS (SELECT 1 FROM diarias d WHERE d.funcionario_id = f.id AND d.data BETWEEN ? AND ?) ORDER BY f.nome');
$st->execute([$qi, $qf]);
$equipe = $st->fetchAll();
$marcas = [];
$st = $pdo->prepare('SELECT funcionario_id, data, fracao, valor, extra FROM diarias WHERE data BETWEEN ? AND ?');
$st->execute([$qi, $qf]);
foreach ($st->fetchAll() as $m) $marcas[$m['funcionario_id']][$m['data']] = $m;

$totDias = 0.0; $totValor = 0.0;
foreach ($marcas as $porDia) foreach ($porDia as $m) { $totDias += $m['fracao']; $totValor += $m['fracao'] * $m['valor'] + $m['extra']; }
$diasComMarca = count(array_unique(array_merge(...array_map('array_keys', array_values($marcas ?: [[]])))));

// dia selecionado (aba "Marcar o dia")
$st = $pdo->prepare('SELECT f.*, d.fracao, d.obra_id, d.extra, d.obs, d.valor AS valor_dia,
   (SELECT obra_id FROM diarias x WHERE x.funcionario_id = f.id AND x.obra_id IS NOT NULL ORDER BY x.data DESC LIMIT 1) AS ultima_obra
   FROM funcionarios f LEFT JOIN diarias d ON d.funcionario_id = f.id AND d.data = ?
   WHERE f.ativo = 1 OR d.id IS NOT NULL ORDER BY f.nome');
$st->execute([$data]);
$pessoas = $st->fetchAll();
$hojeN = 0.0; foreach ($pessoas as $p) $hojeN += (float)($p['fracao'] ?? 0);
$ativos = count(array_filter($equipe, fn($e) => $e['ativo']));

$ant = date('Y-m-d', strtotime("$data -1 day"));
$prox = date('Y-m-d', strtotime("$data +1 day"));
$qAnt = date('Y-m-d', strtotime("$qi -1 day"));
$qProx = date('Y-m-d', strtotime("$qf +1 day"));
$hoje = date('Y-m-d');
$ini_sem = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
$vista = in_array($_GET['v'] ?? '', ['grade', 'dia'], true) ? $_GET['v'] : '';

topo('Diárias', 'diarias', $u);
?>
<div class="cab">
  <div><span class="kicker">Período <?= quinzena_rotulo($qi, $qf) ?></span><h1>Diárias</h1></div>
  <div class="abas" role="tablist" aria-label="Como marcar" data-vista="<?= $vista ?>">
    <button type="button" role="tab" id="aba-grade" aria-controls="painel-grade" aria-selected="true">Grade do período</button>
    <button type="button" role="tab" id="aba-dia" aria-controls="painel-dia" aria-selected="false">Marcar o dia</button>
  </div>
  <a class="btn btn-pq" href="planilha.php">Colar da planilha</a>
</div>

<div class="resumo-dia">
  <div class="cartao cartao-num"><span class="ico"><?= icone('M4 5h16v16H4zM4 10h16M9 3v4M15 3v4', 20) ?></span><span class="num-rot">Diárias no período</span><span class="num" id="r-dias"><?= dias_br($totDias) ?></span><span class="pq">em <?= $diasComMarca ?> <?= $diasComMarca == 1 ? 'dia trabalhado' : 'dias trabalhados' ?></span></div>
  <div class="cartao cartao-num"><span class="ico"><?= icone('M3 6h18v12H3zM12 15a3 3 0 100-6 3 3 0 000 6z', 20) ?></span><span class="num-rot">Mão de obra</span><span class="num dinheiro" id="r-valor"><?= brl($totValor) ?></span><span class="pq">diárias + extras</span></div>
  <div class="cartao cartao-num"><span class="ico"><?= icone('M16 19v-1a4 4 0 00-4-4H7a4 4 0 00-4 4v1M9.5 10a3.5 3.5 0 100-7 3.5 3.5 0 000 7z', 20) ?></span><span class="num-rot"><?= $data === $hoje ? 'Hoje' : data_br($data) ?></span><span class="num" id="r-hoje"><?= dias_br($hojeN) ?> <span class="pq" style="font-size:15px;font-weight:600">de <?= $ativos ?></span></span><span class="pq"><?= dia_semana($data) ?></span></div>
  <div class="cartao destaque"><span class="num-rot">Folha</span><span class="num" style="font-size:20px"><?= $fechQ ? 'Fechada' : 'Aberta' ?></span><span class="pq"><?= $fechQ ? 'Só consulta. Reabra no Fechamento para mudar.' : 'Fecha em ' . data_br($qf) ?></span></div>
</div>

<?php if (!$equipe): ?>
  <div class="cartao vazio"><strong>Ninguém cadastrado ainda</strong>Cadastre os colaboradores com o valor da diária e volte aqui para marcar os dias.<br><a class="btn btn-ouro" href="equipe.php?id=novo">Cadastrar colaborador</a></div>
<?php else: ?>

<!-- ===== GRADE DO PERÍODO ===== -->
<section id="painel-grade" role="tabpanel" aria-labelledby="aba-grade">
  <div class="cab" style="margin-bottom:12px;align-items:center">
    <div class="acoes">
      <a class="btn btn-pq" href="?d=<?= $qAnt ?>&v=grade">← Período anterior</a>
      <a class="btn btn-pq" href="?d=<?= $qProx ?>&v=grade">Próximo →</a>
      <?php if ($hoje < $qi || $hoje > $qf): ?><a class="btn btn-pq" href="?v=grade">Período atual</a><?php endif ?>
    </div>
    <?php if (!$fechQ): ?>
    <div class="acoes"><label for="obra-grade" style="margin:0" class="pq">Obra das novas marcações</label>
      <select id="obra-grade" style="width:auto;max-width:280px"><option value="">Sem obra definida</option><?= opcoes($obras, (string)$obraPadrao, true) ?></select>
      <span class="salvo" id="salvo" aria-live="polite">✓ Salvo</span></div>
    <?php endif ?>
  </div>
  <?php if ($fechQ): ?><div class="travado"><?= icone('M6 11V8a6 6 0 0112 0v3M5 11h14v10H5z', 20) ?>Folha de <?= quinzena_rotulo($fechQ['inicio'], $fechQ['fim']) ?> fechada. <a href="fechamento.php?ini=<?= $fechQ['inicio'] ?>&fim=<?= $fechQ['fim'] ?>">Ver fechamento</a></div><?php endif ?>
  <div class="planilha" id="planilha" data-csrf="<?= csrf() ?>" data-travada="<?= $fechQ ? '1' : '0' ?>">
    <table>
      <thead><tr>
        <th class="quem-col" scope="col">Colaborador</th>
        <?php foreach ($diasQ as $d): $w = (int)date('w', strtotime($d)); ?>
          <th scope="col" class="<?= $w === 0 ? 'dom' : '' ?> <?= $d === $hoje ? 'hoje' : '' ?>"><small><?= $ini_sem[$w] ?></small><?= date('d', strtotime($d)) ?></th>
        <?php endforeach ?>
        <th class="tot" scope="col">Total</th>
      </tr></thead>
      <tbody>
      <?php foreach ($equipe as $p): $rDias = 0; $rVal = 0; ?>
        <tr data-f="<?= $p['id'] ?>">
          <td class="quem-col"><div class="pessoa-linha"><?= avatar($p['nome'], 'p') ?><div><b><?= h($p['nome']) ?></b><span><?= h($p['funcao']) ?> · <?= brl((float)$p['diaria']) ?></span></div></div></td>
          <?php foreach ($diasQ as $d):
            $m = $marcas[$p['id']][$d] ?? null; $v = $m ? (float)$m['fracao'] : 0; $val = $m ? (float)$m['valor'] : (float)$p['diaria'];
            $rDias += $v; $rVal += $m ? $v * $m['valor'] + $m['extra'] : 0;
            $w = (int)date('w', strtotime($d)); $vs = $v >= 1 ? '1' : ($v > 0 ? '0.5' : '0'); ?>
            <td class="<?= $w === 0 ? 'dom' : '' ?> <?= $d === $hoje ? 'hoje' : '' ?>"><button type="button" class="cel" data-dia="<?= $d ?>" data-v="<?= $vs ?>" data-valor="<?= $val ?>" data-extra="<?= $m ? (float)$m['extra'] : 0 ?>" aria-label="<?= h($p['nome']) ?>, <?= data_br($d) ?>: <?= $vs === '1' ? 'diária' : ($vs === '0.5' ? 'meia diária' : 'não trabalhou') ?>" <?= $fechQ ? 'disabled' : '' ?>><i><?= $vs === '1' ? 'D' : ($vs === '0.5' ? '½' : '') ?></i></button></td>
          <?php endforeach ?>
          <td class="tot"><b class="t-dias"><?= dias_br($rDias) ?></b><span class="pq t-val"><?= brl($rVal) ?></span></td>
        </tr>
      <?php endforeach ?>
      </tbody>
      <tfoot><tr>
        <td class="quem-col">Por dia</td>
        <?php foreach ($diasQ as $i => $d): $c = 0; foreach ($equipe as $p) $c += (float)($marcas[$p['id']][$d]['fracao'] ?? 0); ?>
          <td class="c-tot" data-i="<?= $i ?>"><?= $c ? dias_br($c) : '' ?></td>
        <?php endforeach ?>
        <td class="tot"><b id="g-total"><?= brl($totValor) ?></b></td>
      </tr></tfoot>
    </table>
  </div>
  <div class="legenda-cel"><span><i class="l1"></i>Diária</span><span><i class="lm"></i>Meia diária</span><span><i></i>Não trabalhou</span><span>Toque na célula para trocar. Salva sozinho.</span></div>
</section>

<!-- ===== MARCAR O DIA ===== -->
<section id="painel-dia" role="tabpanel" aria-labelledby="aba-dia" hidden>
  <div class="cab" style="margin-bottom:14px;align-items:center">
    <div><span class="kicker"><?= $data === $hoje ? 'Hoje · ' : '' ?><?= dia_semana($data) ?></span><h2 style="margin:4px 0 0;font-size:22px"><?= data_br($data) ?></h2></div>
    <form class="dia-nav" method="get"><input type="hidden" name="v" value="dia">
      <a class="btn btn-pq" href="?d=<?= $ant ?>&v=dia" aria-label="Dia anterior">←</a>
      <label class="sr" for="d">Dia</label><input id="d" type="date" name="d" value="<?= h($data) ?>" onchange="this.form.submit()">
      <a class="btn btn-pq" href="?d=<?= $prox ?>&v=dia" aria-label="Próximo dia">→</a>
      <?php if ($data !== $hoje): ?><a class="btn btn-pq" href="?v=dia">Hoje</a><?php endif ?>
    </form>
  </div>
  <?php if ($fech): ?><div class="travado"><?= icone('M6 11V8a6 6 0 0112 0v3M5 11h14v10H5z', 20) ?>Folha fechada. Só consulta.</div><?php endif ?>
  <form method="post">
    <?= campo_csrf() ?><input type="hidden" name="d" value="<?= h($data) ?>">
    <?php if (!$fech): ?><div class="acoes" style="margin-bottom:14px"><button type="button" class="btn btn-pq" data-todos>Todos com diária</button></div><?php endif ?>
    <fieldset class="ponto" style="border:0;padding:0;margin:0" <?= $fech ? 'disabled' : '' ?>>
      <legend class="sr">Quem trabalhou em <?= data_br($data) ?></legend>
      <?php foreach ($pessoas as $p): $fr = (string)($p['fracao'] ?? '0'); $fr = $fr === '0.5' ? '0.5' : ((float)$fr >= 1 ? '1' : '0'); $obraSel = $p['obra_id'] ?? $p['ultima_obra'] ?? $obraPadrao; ?>
      <div class="pessoa" data-diaria="<?= (float)($p['valor_dia'] ?? $p['diaria']) ?>">
        <div class="quem"><?= avatar($p['nome'], 'g') ?><div><b><?= h($p['nome']) ?></b><span><?= h($p['funcao']) ?> · diária <?= brl((float)($p['valor_dia'] ?? $p['diaria'])) ?></span></div></div>
        <div class="escolha" role="radiogroup" aria-label="<?= h($p['nome']) ?>">
          <?php foreach (['0' => 'Não veio', '0.5' => 'Meia', '1' => 'Diária'] as $val => $rot): ?>
            <label><input type="radio" name="f[<?= $p['id'] ?>]" value="<?= $val ?>" <?= $fr === (string)$val ? 'checked' : '' ?>><span><?= $rot ?></span></label>
          <?php endforeach ?>
        </div>
        <div class="linha2">
          <div><label class="sr" for="o<?= $p['id'] ?>">Obra de <?= h($p['nome']) ?></label><select id="o<?= $p['id'] ?>" name="o[<?= $p['id'] ?>]"><option value="">Sem obra definida</option><?= opcoes($obras, (string)$obraSel, true) ?></select></div>
          <div><label class="sr" for="x<?= $p['id'] ?>">Extra de <?= h($p['nome']) ?></label><input id="x<?= $p['id'] ?>" name="x[<?= $p['id'] ?>]" inputmode="decimal" placeholder="Extra R$" value="<?= !empty($p['extra']) ? number_format((float)$p['extra'], 2, ',', '.') : '' ?>" title="Hora extra ou bônus do dia"></div>
        </div>
        <input type="hidden" name="n[<?= $p['id'] ?>]" value="<?= h($p['obs'] ?? '') ?>">
      </div>
      <?php endforeach ?>
    </fieldset>
    <?php if (!$fech): ?>
    <div class="salvar-fixo"><span><b id="total-dia">0 diárias</b><span class="pq"> neste dia</span></span><button class="btn btn-ouro">Salvar o dia <?= data_br($data) ?></button></div>
    <?php endif ?>
  </form>
</section>
<?php endif;
rodape();
