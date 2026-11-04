<?php
// Importa a folha de pagamento de 08/09/2026 a 22/09/2026 (enviada em PDF) para o sistema. Roda uma vez só.
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();
$pdo = db();

const FOLHA_SET = [
    'ini' => '2026-09-08', 'fim' => '2026-09-22',
    'colaboradores' => [
        ['Egmar', 'Pedreiro', 200, [8, 9, 10, 11, 12, 14, 15, 16, 17, 18, 19, 21, 22]],
        ['Erisvaldo', 'Pedreiro', 200, [8, 9, 10, 11, 15, 16, 17, 18, 19, 21, 22]],
        ['Warley', 'Pedreiro', 200, [8, 9, 11, 14, 15, 16, 17, 18, 19, 21, 22]],
        ['Rafael', 'Ajudante', 100, [14, 15, 16, 17, 18, 19, 21]],
        ['Carlos', 'Ajudante', 100, [17]],
        ['Anastácio', 'Ajudante', 100, [14, 15, 16, 17, 18, 19, 21, 22]],
        ['Davi', 'Mestre de obra', 600, [8, 9, 10, 11, 12, 14, 15, 16, 17, 18, 19, 21, 22]],
    ],
    // nome, descrição, total, valor da parcela, parcelas pagas antes deste período
    'parcelamentos' => [
        ['Egmar', 'Chevrolet Prisma', 8000, 1500, 2],
        ['Erisvaldo', 'Chevrolet Prisma', 7000, 1000, 2],
    ],
    'vales' => [['Carlos', '2026-09-17', 50, 'Valor já pago']],
    'despesas' => [['Combustível', 634], ['Café da manhã', 390], ['Almoço', 1080], ['Cartões e divulgação', 150, 'Cartões de visita']],
];

$ja = $pdo->prepare('SELECT 1 FROM fechamentos WHERE inicio = ? AND fim = ?');
$ja->execute([FOLHA_SET['ini'], FOLHA_SET['fim']]);
$jaImportado = (bool)$ja->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$jaImportado) {
    $ids = [];
    $pdo->beginTransaction();
    $busca = $pdo->prepare('SELECT id FROM funcionarios WHERE nome = ?');
    foreach (FOLHA_SET['colaboradores'] as [$nome, $funcao, $diaria, $dias]) {
        $busca->execute([$nome]);
        $id = (int)$busca->fetchColumn();
        if (!$id) {
            $pdo->prepare('INSERT INTO funcionarios(nome, funcao, diaria, ativo) VALUES(?,?,?,1)')->execute([$nome, $funcao, $diaria]);
            $id = (int)$pdo->lastInsertId();
        }
        $ids[$nome] = $id;
        $d = $pdo->prepare('INSERT OR IGNORE INTO diarias(funcionario_id, data, fracao, valor, extra) VALUES(?,?,1,?,0)');
        foreach ($dias as $dia) $d->execute([$id, sprintf('2026-09-%02d', $dia), $diaria]);
    }
    foreach (FOLHA_SET['parcelamentos'] as [$nome, $desc, $total, $parc, $pagas]) {
        $pdo->prepare('INSERT INTO parcelamentos(funcionario_id, descricao, total) VALUES(?,?,?)')->execute([$ids[$nome], $desc, $total]);
        $pl = (int)$pdo->lastInsertId();
        $n = (int)ceil($total / $parc); $resto = $total;
        for ($i = 1; $i <= $n; $i++) {
            $v = $i < $n ? $parc : $resto; $resto -= $v;
            $pdo->prepare('INSERT INTO parcelas(parcelamento_id, numero, valor, status) VALUES(?,?,?,?)')->execute([$pl, $i, $v, $i <= $pagas ? 'descontada' : 'pendente']);
        }
    }
    foreach (FOLHA_SET['vales'] as [$nome, $data, $valor, $obs]) $pdo->prepare('INSERT INTO vales(funcionario_id, data, valor, obs) VALUES(?,?,?,?)')->execute([$ids[$nome], $data, $valor, $obs]);
    foreach (FOLHA_SET['despesas'] as $dsp) $pdo->prepare('INSERT INTO despesas(data, categoria, descricao, valor) VALUES(?,?,?,?)')->execute([FOLHA_SET['fim'], $dsp[0], $dsp[2] ?? 'Importado da folha de 08/09 a 22/09', $dsp[1]]);
    $pdo->commit();

    // fecha a folha descontando a 3ª parcela de cada um, como no PDF
    $escolhidas = [];
    foreach ($ids as $id) foreach (parcelas_propostas($id) as $p) $escolhidas[] = (int)$p['id'];
    $fid = fechar_periodo(FOLHA_SET['ini'], FOLHA_SET['fim'], $escolhidas);
    if (!empty($_POST['pago'])) $pdo->prepare("UPDATE fechamento_itens SET pago_em = '2026-09-22 18:00:00' WHERE fechamento_id = ?")->execute([$fid]);
    avisar('Folha de 08/09 a 22/09/2026 importada e fechada. Confira se os totais batem com o seu PDF.');
    ir('fechamento.php?ini=' . FOLHA_SET['ini'] . '&fim=' . FOLHA_SET['fim']);
}

topo('Importar folha de setembro', 'fechamento', $u);
?>
<a href="fechamento.php" class="voltar">← Fechamento</a>
<div class="cab" style="margin-top:12px"><div><span class="kicker">Importação</span><h1>Folha de 08/09 a 22/09/2026</h1><p class="sub">Os dados do PDF que vocês mandaram, prontos para entrar no sistema.</p></div></div>
<?php if ($jaImportado): ?>
  <div class="cartao vazio"><strong>Essa folha já está no sistema</strong><a class="btn btn-ouro" href="fechamento.php?ini=<?= FOLHA_SET['ini'] ?>&fim=<?= FOLHA_SET['fim'] ?>">Ver o fechamento</a></div>
<?php else: ?>
<div class="grade g2" style="align-items:start">
  <section class="cartao"><h2>O que vai entrar</h2>
    <ul class="lista-ativ">
      <?php foreach (FOLHA_SET['colaboradores'] as [$n, $f, $d, $dias]): ?><li><span><b><?= $n ?></b> <span class="pq"><?= $f ?> · diária <?= brl((float)$d) ?></span></span><b><?= count($dias) ?> dias · <?= brl($d * count($dias)) ?></b></li><?php endforeach ?>
    </ul></section>
  <section class="grade">
    <div class="cartao"><h2>Descontos</h2><ul class="lista-ativ">
      <li><span>Egmar · Chevrolet Prisma</span><b>3ª de 6 · <?= brl(1500) ?></b></li>
      <li><span>Erisvaldo · Chevrolet Prisma</span><b>3ª de 7 · <?= brl(1000) ?></b></li>
      <li><span>Carlos · já pago</span><b><?= brl(50) ?></b></li></ul></div>
    <div class="cartao"><h2>Despesas operacionais</h2><ul class="lista-ativ"><?php foreach (FOLHA_SET['despesas'] as $d): ?><li><span><?= $d[2] ?? $d[0] ?></span><b><?= brl((float)$d[1]) ?></b></li><?php endforeach ?></ul></div>
  </section>
</div>
<form method="post" class="cartao" style="margin-top:20px">
  <?= campo_csrf() ?>
  <p style="margin-top:0">Esperado pelo PDF: bruto <b><?= brl(16400) ?></b>, descontos <b><?= brl(2550) ?></b>, líquido <b><?= brl(13850) ?></b>, total a desembolsar <b><?= brl(16104) ?></b>.</p>
  <label style="font-weight:600"><input type="checkbox" name="pago" value="1" checked> Essa folha já foi paga a todos</label>
  <p class="pq">Colaboradores com o mesmo nome que já existam no sistema são reaproveitados.</p>
  <button class="btn btn-ouro">Importar e fechar a folha</button>
</form>
<?php endif;
rodape();
