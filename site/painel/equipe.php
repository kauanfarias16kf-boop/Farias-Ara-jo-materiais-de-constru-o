<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $voltar = ($_POST['voltar'] ?? '') === 'fechamento' ? 'fechamento.php?ini=' . urlencode((string)($_POST['ini'] ?? '')) : null;

    if ($acao === 'salvar') {
        $v = [
            'nome' => trim((string)($_POST['nome'] ?? '')),
            'funcao' => trim((string)($_POST['funcao'] ?? '')),
            'whatsapp' => trim((string)($_POST['whatsapp'] ?? '')),
            'pix' => trim((string)($_POST['pix'] ?? '')),
            'diaria' => num_br((string)($_POST['diaria'] ?? '')),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
            'notas' => trim((string)($_POST['notas'] ?? '')),
        ];
        if ($v['nome'] === '') { avisar('Escreva o nome do funcionário.', 'erro'); ir('equipe.php?id=' . ($id ?: 'novo')); }
        if ($id) {
            $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($v)));
            $pdo->prepare("UPDATE funcionarios SET $sets WHERE id = ?")->execute([...array_values($v), $id]);
        } else {
            $pdo->prepare('INSERT INTO funcionarios(' . implode(',', array_keys($v)) . ') VALUES(' . rtrim(str_repeat('?,', count($v)), ',') . ')')->execute(array_values($v));
            $id = (int)$pdo->lastInsertId();
        }
        avisar('Funcionário salvo.');
        ir("equipe.php?id=$id");
    }
    if ($acao === 'vale') {
        $data = (string)($_POST['data'] ?? date('Y-m-d'));
        $valor = num_br((string)($_POST['valor'] ?? ''));
        if ($f = fechamento_da_data($data)) { avisar('A quinzena de ' . quinzena_rotulo($f['inicio'], $f['fim']) . ' já foi fechada. Lance o vale em outra data ou reabra a quinzena.', 'erro'); }
        elseif ($valor <= 0) { avisar('Informe o valor do vale.', 'erro'); }
        else {
            $pdo->prepare('INSERT INTO vales(funcionario_id, data, valor, obs) VALUES(?,?,?,?)')->execute([$id, $data, $valor, trim((string)($_POST['obs'] ?? ''))]);
            avisar('Vale de ' . brl($valor) . ' lançado. Ele é descontado no fechamento.');
        }
        ir($voltar ?? "equipe.php?id=$id#vales");
    }
    if ($acao === 'rm_vale') {
        $st = $pdo->prepare('SELECT data FROM vales WHERE id = ? AND funcionario_id = ?'); $st->execute([(int)$_POST['vale_id'], $id]);
        $d = $st->fetchColumn();
        if ($d && fechamento_da_data($d)) avisar('Esse vale está numa quinzena fechada. Reabra a quinzena para mudar.', 'erro');
        elseif ($d) { $pdo->prepare('DELETE FROM vales WHERE id = ?')->execute([(int)$_POST['vale_id']]); avisar('Vale removido.'); }
        ir("equipe.php?id=$id#vales");
    }
    if ($acao === 'parcelamento') {
        $desc = mb_substr(trim((string)($_POST['descricao'] ?? '')), 0, 80);
        $total = num_br((string)($_POST['total'] ?? ''));
        $parc = num_br((string)($_POST['parcela'] ?? ''));
        $pagas = max(0, (int)($_POST['pagas'] ?? 0));
        if ($desc === '' || $total <= 0 || $parc <= 0 || $parc > $total) { avisar('Preencha o que é, o valor total e o valor de cada parcela (que não pode passar do total).', 'erro'); ir("equipe.php?id=$id#parcelas"); }
        $n = (int)ceil(round($total / $parc, 6));
        if ($n > 60) { avisar('São parcelas demais. Confira os valores.', 'erro'); ir("equipe.php?id=$id#parcelas"); }
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO parcelamentos(funcionario_id, descricao, total, obs) VALUES(?,?,?,?)')->execute([$id, $desc, $total, trim((string)($_POST['obs'] ?? ''))]);
        $pl = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO parcelas(parcelamento_id, numero, valor, status) VALUES(?,?,?,?)');
        $resto = $total;
        for ($i = 1; $i <= $n; $i++) {
            $v = $i < $n ? $parc : round($resto, 2);
            $resto -= $v;
            $ins->execute([$pl, $i, $v, $i <= $pagas ? 'descontada' : 'pendente']);
        }
        $pdo->commit();
        avisar("Parcelamento criado: $n parcelas. A próxima pendente entra sozinha no próximo fechamento.");
        ir("equipe.php?id=$id#parcelas");
    }
    if ($acao === 'rm_parcelamento') {
        $pl = (int)($_POST['plano'] ?? 0);
        $usadas = (int)$pdo->query("SELECT COUNT(*) FROM parcelas WHERE parcelamento_id = $pl AND fechamento_id IS NOT NULL")->fetchColumn();
        if ($usadas) avisar('Esse parcelamento já foi descontado em uma folha fechada, então não dá para apagar. As parcelas pendentes foram canceladas.', 'ok');
        if ($usadas) $pdo->prepare("DELETE FROM parcelas WHERE parcelamento_id = ? AND status = 'pendente'")->execute([$pl]);
        else { $pdo->prepare('DELETE FROM parcelamentos WHERE id = ? AND funcionario_id = ?')->execute([$pl, $id]); avisar('Parcelamento apagado.'); }
        ir("equipe.php?id=$id#parcelas");
    }
    if ($acao === 'apagar' && $id) {
        $tem = (int)$pdo->query("SELECT COUNT(*) FROM diarias WHERE funcionario_id = $id")->fetchColumn();
        if ($tem) {
            $pdo->prepare('UPDATE funcionarios SET ativo = 0 WHERE id = ?')->execute([$id]);
            avisar('Esse funcionário tem diárias registradas, então ele foi marcado como inativo para manter o histórico.');
            ir("equipe.php?id=$id");
        }
        $pdo->prepare('DELETE FROM funcionarios WHERE id = ?')->execute([$id]);
        avisar('Funcionário apagado.');
        ir('equipe.php');
    }
    ir('equipe.php');
}

[$qi, $qf] = quinzena(date('Y-m-d'));
$id = $_GET['id'] ?? '';
if ($id !== '') {
    $f = ['id' => 0, 'nome' => '', 'funcao' => 'Pedreiro', 'whatsapp' => '', 'pix' => '', 'diaria' => 0, 'ativo' => 1, 'notas' => ''];
    if ($id !== 'novo') {
        $st = $pdo->prepare('SELECT * FROM funcionarios WHERE id = ?'); $st->execute([(int)$id]);
        $f = $st->fetch() ?: ir('equipe.php');
    }
    topo($f['id'] ? $f['nome'] : 'Novo funcionário', 'equipe', $u);
    ?>
    <a href="equipe.php" class="voltar">← Todos os funcionários</a>
    <div class="cab" style="margin-top:12px"><div><?php if ($f['id']): ?><span class="kicker"><?= h($f['funcao']) ?></span><?php endif ?><h1><?= $f['id'] ? h($f['nome']) : 'Novo funcionário' ?></h1></div>
      <?php if ($f['id'] && $f['whatsapp']): ?><a class="btn btn-zap" href="<?= h(whats_link($f['whatsapp'])) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a><?php endif ?></div>
    <?php if ($f['id']):
        $q = array_values(array_filter(folha($qi, $qf), fn($l) => (int)$l['id'] === (int)$f['id']))[0] ?? ['dias' => 0, 'bruto' => 0, 'extras' => 0, 'vales' => 0, 'liquido' => 0];
    ?>
    <div class="grade g4 metricas" style="margin-bottom:20px">
      <div class="cartao"><span class="num-rot">Dias nesta quinzena</span><span class="num"><?= dias_br((float)$q['dias']) ?></span><span class="pq"><?= quinzena_rotulo($qi, $qf) ?></span></div>
      <div class="cartao"><span class="num-rot">Diárias + extras</span><span class="num dinheiro"><?= brl($q['bruto'] + $q['extras']) ?></span></div>
      <div class="cartao"><span class="num-rot">Vales</span><span class="num dinheiro"><?= brl((float)$q['vales']) ?></span></div>
      <div class="cartao destaque"><span class="num-rot">A receber até agora</span><span class="num dinheiro"><?= brl((float)$q['liquido']) ?></span></div>
    </div>
    <?php endif ?>
    <div class="lado">
      <form class="cartao" method="post">
        <h2>Dados</h2>
        <?= campo_csrf() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
        <div class="grade g2">
          <div class="campo"><label for="nome">Nome</label><input id="nome" name="nome" required value="<?= h($f['nome']) ?>"></div>
          <div class="campo"><label for="funcao">Função</label><select id="funcao" name="funcao"><?= opcoes(FA_FUNCOES, $f['funcao']) ?></select></div>
          <div class="campo"><label for="diaria">Valor da diária (R$)</label><input id="diaria" name="diaria" inputmode="decimal" required value="<?= (float)$f['diaria'] ? number_format((float)$f['diaria'], 2, ',', '.') : '' ?>" placeholder="Ex.: 150,00"><div class="dica">Meia diária conta a metade.</div></div>
          <div class="campo"><label for="whatsapp">WhatsApp</label><input id="whatsapp" name="whatsapp" inputmode="tel" value="<?= h($f['whatsapp']) ?>" placeholder="61 9 0000-0000"></div>
          <div class="campo"><label for="pix">Chave Pix</label><input id="pix" name="pix" value="<?= h($f['pix']) ?>" placeholder="CPF, telefone ou e-mail"></div>
          <div class="campo" style="align-self:center"><label><input type="checkbox" name="ativo" value="1" <?= $f['ativo'] ? 'checked' : '' ?>> Trabalhando com a gente</label><div class="dica">Desmarque quando sair. O histórico fica guardado.</div></div>
        </div>
        <div class="campo"><label for="notas">Anotações</label><textarea id="notas" name="notas"><?= h($f['notas']) ?></textarea></div>
        <button class="btn btn-ouro">Salvar funcionário</button>
      </form>
      <?php if ($f['id']):
        $vales = $pdo->query("SELECT * FROM vales WHERE funcionario_id = {$f['id']} ORDER BY data DESC, id DESC LIMIT 20")->fetchAll();
        $ult = $pdo->query("SELECT d.*, o.titulo FROM diarias d LEFT JOIN obras o ON o.id = d.obra_id WHERE d.funcionario_id = {$f['id']} ORDER BY d.data DESC LIMIT 12")->fetchAll();
      ?>
      <div class="grade">
        <form class="cartao" method="post" id="vales">
          <h2>Lançar vale (já pago)</h2>
          <p class="pq" style="margin-top:-8px">Adiantamento pago antes do fechamento. É descontado na quinzena da data.</p>
          <?= campo_csrf() ?><input type="hidden" name="acao" value="vale"><input type="hidden" name="id" value="<?= $f['id'] ?>">
          <div class="grade g2">
            <div class="campo"><label for="v-valor">Valor (R$)</label><input id="v-valor" name="valor" inputmode="decimal" required placeholder="0,00"></div>
            <div class="campo"><label for="v-data">Data</label><input id="v-data" type="date" name="data" value="<?= date('Y-m-d') ?>" required></div>
          </div>
          <div class="campo"><label for="v-obs">Motivo <span class="pq">(opcional)</span></label><input id="v-obs" name="obs" placeholder="Ex.: adiantamento para remédio"></div>
          <button class="btn btn-ouro">Lançar vale</button>
          <?php if ($vales): ?>
          <ul class="lista-ativ" style="margin-top:16px">
            <?php foreach ($vales as $v): ?>
              <li><span><b class="dinheiro"><?= brl((float)$v['valor']) ?></b> · <?= data_br($v['data']) ?><?= $v['obs'] ? '<br><span class="pq">' . h($v['obs']) . '</span>' : '' ?></span>
              <button class="btn btn-perigo btn-pq" form="rm-vale-<?= $v['id'] ?>">Tirar</button></li>
            <?php endforeach ?>
          </ul>
          <?php endif ?>
        </form>
        <?php foreach ($vales as $v): ?><form id="rm-vale-<?= $v['id'] ?>" method="post" data-confirma="Tirar o vale de <?= brl((float)$v['valor']) ?> do dia <?= data_br($v['data']) ?>?" hidden><?= campo_csrf() ?><input type="hidden" name="acao" value="rm_vale"><input type="hidden" name="id" value="<?= $f['id'] ?>"><input type="hidden" name="vale_id" value="<?= $v['id'] ?>"></form><?php endforeach ?>
        <section class="cartao" id="parcelas">
          <h2>Descontos parcelados</h2>
          <p class="pq" style="margin-top:-8px">Para um bem ou empréstimo descontado aos poucos, como o carro. A próxima parcela é descontada sozinha em cada fechamento.</p>
          <?php foreach ($pdo->query("SELECT * FROM parcelamentos WHERE funcionario_id = {$f['id']} ORDER BY id")->fetchAll() as $pl):
            $ps = $pdo->query("SELECT * FROM parcelas WHERE parcelamento_id = {$pl['id']} ORDER BY numero")->fetchAll(); $r = resumo_parcelamento((int)$pl['id']); ?>
            <div style="border:1.5px solid var(--line);border-radius:12px;padding:14px;margin-bottom:12px">
              <div class="cab" style="margin-bottom:8px"><b style="font-size:16px"><?= h($pl['descricao']) ?></b><span class="pq">Total <?= brl($r['total']) ?> · pago <?= brl($r['pago']) ?> · <b class="forte">saldo <?= brl($r['saldo']) ?></b></span></div>
              <div class="barra-prog" style="margin-bottom:10px"><i style="width:<?= $r['total'] ? round($r['pago'] / $r['total'] * 100) : 0 ?>%"></i></div>
              <div class="acoes"><?php foreach ($ps as $p): ?><span class="st <?= $p['status'] === 'descontada' ? 'st-pago' : '' ?>" title="<?= $p['status'] === 'descontada' ? 'Paga, descontada' : 'Pendente' ?>"><?= ordinal((int)$p['numero']) ?> · <?= brl((float)$p['valor']) ?></span><?php endforeach ?></div>
              <form method="post" style="margin-top:10px" data-confirma="Apagar o parcelamento <?= h($pl['descricao']) ?>?"><?= campo_csrf() ?><input type="hidden" name="acao" value="rm_parcelamento"><input type="hidden" name="id" value="<?= $f['id'] ?>"><input type="hidden" name="plano" value="<?= $pl['id'] ?>"><button class="btn btn-perigo btn-pq">Apagar parcelamento</button></form>
            </div>
          <?php endforeach ?>
          <form method="post">
            <?= campo_csrf() ?><input type="hidden" name="acao" value="parcelamento"><input type="hidden" name="id" value="<?= $f['id'] ?>">
            <div class="grade g2">
              <div class="campo"><label for="p-desc">O que é</label><input id="p-desc" name="descricao" required placeholder="Ex.: Chevrolet Prisma"></div>
              <div class="campo"><label for="p-total">Valor total (R$)</label><input id="p-total" name="total" inputmode="decimal" required placeholder="Ex.: 8.000,00"></div>
              <div class="campo"><label for="p-parc">Valor de cada parcela (R$)</label><input id="p-parc" name="parcela" inputmode="decimal" required placeholder="Ex.: 1.500,00"><div class="dica">A última fica com o que sobrar.</div></div>
              <div class="campo"><label for="p-pagas">Parcelas já pagas</label><input id="p-pagas" name="pagas" type="number" min="0" value="0"></div>
            </div>
            <button class="btn btn-ouro">Criar parcelamento</button>
          </form>
        </section>
        <section class="cartao">
          <div class="cab" style="margin-bottom:6px"><h2 style="margin:0">Últimos dias trabalhados</h2><a class="pq" href="diarias.php">Marcar diárias</a></div>
          <?php if (!$ult): ?><p class="pq">Nenhum dia marcado ainda.</p><?php endif ?>
          <ul class="lista-ativ"><?php foreach ($ult as $d): ?><li><span><b><?= data_br($d['data']) ?></b> <span class="pq"><?= dia_semana($d['data']) ?><?= $d['titulo'] ? ' · ' . h($d['titulo']) : '' ?></span></span><span class="st <?= $d['fracao'] < 1 ? '' : 'st-pago' ?>"><?= $d['fracao'] < 1 ? 'Meia' : 'Diária' ?><?= $d['extra'] > 0 ? ' + extra' : '' ?></span></li><?php endforeach ?></ul>
        </section>
      </div>
      <?php endif ?>
    </div>
    <?php if ($f['id']): ?>
    <form method="post" style="margin-top:28px" data-confirma="Apagar <?= h($f['nome']) ?>? Se ele tiver diárias, fica só como inativo."><?= campo_csrf() ?><input type="hidden" name="acao" value="apagar"><input type="hidden" name="id" value="<?= $f['id'] ?>"><button class="btn btn-perigo btn-pq">Apagar <?= h($f['nome']) ?></button></form>
    <?php endif;
    rodape();
    exit;
}

$ver = ($_GET['ver'] ?? '') === 'todos';
$folha = [];
foreach (folha($qi, $qf) as $l) $folha[$l['id']] = $l;
$lista = $pdo->query('SELECT * FROM funcionarios' . ($ver ? '' : ' WHERE ativo = 1') . ' ORDER BY nome')->fetchAll();
topo('Funcionários', 'equipe', $u);
?>
<div class="cab"><div><h1>Funcionários</h1><p class="sub">Quem trabalha com a gente, o valor da diária e quanto cada um tem a receber na quinzena.</p></div>
  <div class="acoes"><a class="btn" href="diarias.php">Marcar diárias</a><a class="btn btn-ouro" href="?id=novo">Novo funcionário</a></div></div>
<nav class="filtros" aria-label="Filtrar"><a href="equipe.php" class="<?= $ver ? '' : 'ativo' ?>">Trabalhando</a><a href="?ver=todos" class="<?= $ver ? 'ativo' : '' ?>">Todos, inclusive quem saiu</a></nav>
<?php if (!$lista): ?>
  <div class="cartao vazio"><strong>Nenhum funcionário cadastrado</strong>Cadastre cada pessoa com o valor da diária. Depois é só marcar os dias trabalhados.<br><a class="btn btn-ouro" href="?id=novo">Cadastrar funcionário</a></div>
<?php else: ?>
<div class="tabela-wrap"><table>
  <thead><tr><th>Nome</th><th class="esconde-cel">Função</th><th class="dir">Diária</th><th class="dir">Dias na quinzena</th><th class="dir">A receber</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $f): $q = $folha[$f['id']] ?? ['dias' => 0, 'liquido' => 0]; ?>
    <tr><td><a class="linha" href="?id=<?= $f['id'] ?>"><?= h($f['nome']) ?></a><?= $f['ativo'] ? '' : ' <span class="st">Saiu</span>' ?><div class="pq"><?= h($f['whatsapp']) ?></div></td><td class="esconde-cel"><?= h($f['funcao']) ?></td><td class="dir"><?= brl((float)$f['diaria']) ?></td><td class="dir forte"><?= dias_br((float)$q['dias']) ?></td><td class="dir forte"><?= brl((float)$q['liquido']) ?></td></tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<p class="pq" style="margin-top:12px">Quinzena atual: <?= quinzena_rotulo($qi, $qf) ?>. <a href="fechamento.php">Ver o fechamento</a></p>
<?php endif;
rodape();
