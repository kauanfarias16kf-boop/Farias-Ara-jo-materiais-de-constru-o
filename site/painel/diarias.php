<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();
$pdo = db();

$data = (string)($_GET['d'] ?? $_POST['d'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !strtotime($data)) $data = date('Y-m-d');
$fech = fechamento_da_data($data);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($fech) { avisar('Essa quinzena já foi fechada. Para mudar, reabra a quinzena no Fechamento.', 'erro'); ir("diarias.php?d=$data"); }
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
        $obra = ((int)($_POST['o'][$fid] ?? 0)) ?: null;
        $extra = num_br((string)($_POST['x'][$fid] ?? ''));
        $obs = mb_substr(trim((string)($_POST['n'][$fid] ?? '')), 0, 200);
        $up->execute([$fid, $data, $obra, $fr, (float)$diarias[$fid], $extra, $obs]);
        $n += $fr;
    }
    $pdo->commit();
    avisar('Dia ' . data_br($data) . ' salvo: ' . dias_br($n) . ($n == 1 ? ' diária.' : ' diárias.'));
    ir("diarias.php?d=$data");
}

$obras = $pdo->query("SELECT id, titulo FROM obras WHERE status IN ('em_andamento','planejada','pausada') ORDER BY status = 'em_andamento' DESC, id DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
$st = $pdo->prepare('SELECT f.*, d.fracao, d.obra_id, d.extra, d.obs, d.valor AS valor_dia,
   (SELECT obra_id FROM diarias x WHERE x.funcionario_id = f.id AND x.obra_id IS NOT NULL ORDER BY x.data DESC LIMIT 1) AS ultima_obra
   FROM funcionarios f LEFT JOIN diarias d ON d.funcionario_id = f.id AND d.data = ?
   WHERE f.ativo = 1 OR d.id IS NOT NULL ORDER BY f.nome');
$st->execute([$data]);
$pessoas = $st->fetchAll();
$obraPadrao = array_key_first($obras);

[$qi, $qf] = quinzena($data);
$folha = folha($qi, $qf);
$marcas = [];
$st = $pdo->prepare('SELECT funcionario_id, data, fracao FROM diarias WHERE data BETWEEN ? AND ?');
$st->execute([$qi, $qf]);
foreach ($st->fetchAll() as $m) $marcas[$m['funcionario_id']][$m['data']] = (float)$m['fracao'];
$diasQ = [];
for ($t = strtotime($qi); $t <= strtotime($qf); $t += 86400) $diasQ[] = date('Y-m-d', $t);

$ant = date('Y-m-d', strtotime("$data -1 day"));
$prox = date('Y-m-d', strtotime("$data +1 day"));
$hoje = $data === date('Y-m-d');
topo('Diárias de ' . data_br($data), 'diarias', $u);
?>
<div class="cab">
  <div><span class="kicker"><?= $hoje ? 'Hoje · ' : '' ?><?= dia_semana($data) ?></span><h1>Diárias de <?= data_br($data) ?></h1></div>
  <form class="dia-nav" method="get">
    <a class="btn btn-pq" href="?d=<?= $ant ?>" aria-label="Dia anterior">← Anterior</a>
    <label class="esconde-cel" for="d" style="margin:0">Dia</label>
    <input id="d" type="date" name="d" value="<?= h($data) ?>" onchange="this.form.submit()">
    <a class="btn btn-pq" href="?d=<?= $prox ?>" aria-label="Próximo dia">Próximo →</a>
    <?php if (!$hoje): ?><a class="btn btn-pq" href="diarias.php">Hoje</a><?php endif ?>
  </form>
</div>

<?php if ($fech): ?><div class="travado">A quinzena de <?= quinzena_rotulo($fech['inicio'], $fech['fim']) ?> já foi fechada e paga pela folha. Os dias aparecem só para consulta. <a href="fechamento.php?ini=<?= $fech['inicio'] ?>">Ver fechamento</a></div><?php endif ?>

<?php if (!$pessoas): ?>
  <div class="cartao vazio"><strong>Ninguém cadastrado ainda</strong>Cadastre os funcionários com o valor da diária e volte aqui para marcar os dias.<br><a class="btn btn-ouro" href="equipe.php?id=novo">Cadastrar funcionário</a></div>
<?php else: ?>
<form method="post">
  <?= campo_csrf() ?><input type="hidden" name="d" value="<?= h($data) ?>">
  <?php if (!$fech): ?><div class="acoes" style="margin-bottom:12px"><button type="button" class="btn btn-pq" data-todos>Marcar todos com diária</button><?php if (!$obras): ?><span class="pq">Dica: cadastre a obra para saber o custo de mão de obra de cada uma.</span><?php endif ?></div><?php endif ?>
  <fieldset class="ponto" style="border:0;padding:0;margin:0" <?= $fech ? 'disabled' : '' ?>>
    <legend class="sr">Quem trabalhou em <?= data_br($data) ?></legend>
    <?php foreach ($pessoas as $p): $fr = (string)($p['fracao'] ?? '0'); $fr = $fr === '0.5' ? '0.5' : ((float)$fr >= 1 ? '1' : '0'); $obraSel = $p['obra_id'] ?? $p['ultima_obra'] ?? $obraPadrao; ?>
    <div class="pessoa" data-diaria="<?= (float)($p['valor_dia'] ?? $p['diaria']) ?>">
      <div class="quem"><b><?= h($p['nome']) ?></b><span><?= h($p['funcao']) ?> · diária <?= brl((float)($p['valor_dia'] ?? $p['diaria'])) ?></span></div>
      <div class="escolha" role="radiogroup" aria-label="<?= h($p['nome']) ?>">
        <?php foreach (['0' => 'Não veio', '0.5' => 'Meia', '1' => 'Diária'] as $val => $rot): ?>
          <label><input type="radio" name="f[<?= $p['id'] ?>]" value="<?= $val ?>" <?= $fr === (string)$val ? 'checked' : '' ?>><span><?= $rot ?></span></label>
        <?php endforeach ?>
      </div>
      <div class="obra-campo"><label class="sr" for="o<?= $p['id'] ?>">Obra de <?= h($p['nome']) ?></label>
        <select id="o<?= $p['id'] ?>" name="o[<?= $p['id'] ?>]"><option value="">Sem obra definida</option><?= opcoes($obras, (string)$obraSel, true) ?></select></div>
      <div class="extra-campo"><label class="sr" for="x<?= $p['id'] ?>">Extra de <?= h($p['nome']) ?></label>
        <input id="x<?= $p['id'] ?>" name="x[<?= $p['id'] ?>]" inputmode="decimal" placeholder="Extra R$" value="<?= !empty($p['extra']) ? number_format((float)$p['extra'], 2, ',', '.') : '' ?>" title="Hora extra ou bônus do dia"></div>
      <input type="hidden" name="n[<?= $p['id'] ?>]" value="<?= h($p['obs'] ?? '') ?>">
    </div>
    <?php endforeach ?>
  </fieldset>
  <?php if (!$fech): ?>
  <div class="salvar-fixo"><span><b id="total-dia">0 diárias</b><span class="pq"> neste dia</span></span><button class="btn btn-ouro">Salvar o dia <?= data_br($data) ?></button></div>
  <?php endif ?>
</form>

<section style="margin-top:34px">
  <div class="cab" style="margin-bottom:14px"><div><span class="kicker">Quinzena</span><h2 style="margin:4px 0 0;font-size:20px"><?= quinzena_rotulo($qi, $qf) ?></h2></div><a class="btn" href="fechamento.php?ini=<?= $qi ?>">Ir para o fechamento</a></div>
  <div class="tabela-wrap"><table>
    <thead><tr><th>Funcionário</th><th class="esconde-cel">Dia a dia</th><th class="dir">Dias</th><th class="dir">A receber</th></tr></thead>
    <tbody>
    <?php foreach ($folha as $l): ?>
      <tr><td><a class="linha" href="equipe.php?id=<?= $l['id'] ?>"><?= h($l['nome']) ?></a></td>
        <td class="esconde-cel"><div class="tira" aria-hidden="true"><?php foreach ($diasQ as $d): $m = $marcas[$l['id']][$d] ?? 0; ?><i class="<?= $m >= 1 ? 't1' : ($m > 0 ? 'tm' : '') ?> <?= date('w', strtotime($d)) == 0 ? 'dom' : '' ?>" title="<?= data_br($d) ?>"><?= (int)date('d', strtotime($d)) ?></i><?php endforeach ?></div></td>
        <td class="dir forte"><?= dias_br((float)$l['dias']) ?></td><td class="dir forte"><?= brl((float)$l['liquido']) ?></td></tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
</section>
<?php endif;
rodape();
