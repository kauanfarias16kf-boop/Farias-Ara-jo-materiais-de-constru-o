<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();
$pdo = db();
$STATUS = ['planejada' => 'Planejada', 'em_andamento' => 'Em andamento', 'pausada' => 'Pausada', 'concluida' => 'Concluída'];
$ESTADOS = ['pendente' => 'Pendente', 'andamento' => 'Em andamento', 'concluida' => 'Concluída'];

function carregar_obra(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM obras WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($acao === 'salvar') {
        $v = [
            'titulo' => trim((string)($_POST['titulo'] ?? '')),
            'cliente_id' => ((int)($_POST['cliente_id'] ?? 0)) ?: null,
            'endereco' => trim((string)($_POST['endereco'] ?? '')),
            'servico' => trim((string)($_POST['servico'] ?? '')),
            'status' => array_key_exists($_POST['status'] ?? '', $STATUS) ? $_POST['status'] : 'planejada',
            'inicio' => ($_POST['inicio'] ?? '') ?: null,
            'previsao' => ($_POST['previsao'] ?? '') ?: null,
            'valor' => num_br((string)($_POST['valor'] ?? '')),
            'notas' => trim((string)($_POST['notas'] ?? '')),
        ];
        if ($v['titulo'] === '') { avisar('Dê um nome para a obra.', 'erro'); ir($id ? "obra.php?id=$id" : 'obra.php?novo=1'); }
        if ($id) {
            $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($v)));
            $pdo->prepare("UPDATE obras SET $sets WHERE id = ?")->execute([...array_values($v), $id]);
        } else {
            $v['codigo'] = codigo_obra();
            $pdo->prepare('INSERT INTO obras(' . implode(',', array_keys($v)) . ') VALUES(' . rtrim(str_repeat('?,', count($v)), ',') . ')')->execute(array_values($v));
            $id = (int)$pdo->lastInsertId();
            if (!empty($_POST['etapas_padrao'])) {
                $ins = $pdo->prepare('INSERT INTO etapas(obra_id, nome, ordem) VALUES(?,?,?)');
                foreach (FA_ETAPAS_PADRAO as $i => $nome) $ins->execute([$id, $nome, $i + 1]);
            }
        }
        avisar('Obra salva.');
        ir("obra.php?id=$id");
    }

    $obra = carregar_obra($id);
    if (!$obra) ir('obras.php');

    if ($acao === 'etapas') {
        $up = $pdo->prepare('UPDATE etapas SET nome = ?, status = ?, previsao = ?, valor = ?, concluida_em = CASE WHEN ? = \'concluida\' THEN COALESCE(concluida_em, date(\'now\',\'-3 hours\')) ELSE NULL END WHERE id = ? AND obra_id = ?');
        foreach ((array)($_POST['etapa'] ?? []) as $eid => $e) {
            if (($e['status'] ?? '') === 'remover') {
                $pdo->prepare('DELETE FROM etapas WHERE id = ? AND obra_id = ?')->execute([(int)$eid, $id]);
                continue;
            }
            $s = array_key_exists($e['status'] ?? '', $ESTADOS) ? $e['status'] : 'pendente';
            $up->execute([trim((string)$e['nome']) ?: 'Etapa', $s, ($e['previsao'] ?? '') ?: null, num_br((string)($e['valor'] ?? '')), $s, (int)$eid, $id]);
        }
        $nova = trim((string)($_POST['nova_etapa'] ?? ''));
        if ($nova !== '') {
            $ordem = (int)$pdo->query("SELECT COALESCE(MAX(ordem),0)+1 FROM etapas WHERE obra_id = $id")->fetchColumn();
            $pdo->prepare('INSERT INTO etapas(obra_id, nome, ordem) VALUES(?,?,?)')->execute([$id, $nova, $ordem]);
        }
        // se alguma etapa andou, a obra está em andamento
        $temAndando = (int)$pdo->query("SELECT COUNT(*) FROM etapas WHERE obra_id = $id AND status != 'pendente'")->fetchColumn();
        if ($temAndando && $obra['status'] === 'planejada') $pdo->prepare("UPDATE obras SET status = 'em_andamento' WHERE id = ?")->execute([$id]);
        avisar('Etapas atualizadas. O cliente já vê pelo link.');
        ir("obra.php?id=$id#etapas");
    }
    if ($acao === 'atualizacao') {
        $texto = trim((string)($_POST['texto'] ?? ''));
        $foto = null;
        try {
            if (!empty($_FILES['foto']['name'])) $foto = salvar_imagem($_FILES['foto'], 'obras')[0];
        } catch (RuntimeException $e) { avisar($e->getMessage(), 'erro'); ir("obra.php?id=$id#diario"); }
        if ($texto === '' && !$foto) { avisar('Escreva algo ou escolha uma foto.', 'erro'); ir("obra.php?id=$id#diario"); }
        $pdo->prepare('INSERT INTO atualizacoes(obra_id, texto, foto) VALUES(?,?,?)')->execute([$id, $texto, $foto]);
        avisar('Novidade publicada no acompanhamento.');
        ir("obra.php?id=$id#diario");
    }
    if ($acao === 'rm_atualizacao') {
        $st = $pdo->prepare('SELECT foto FROM atualizacoes WHERE id = ? AND obra_id = ?');
        $st->execute([(int)$_POST['at_id'], $id]);
        if (($f = $st->fetchColumn()) !== false) {
            if ($f) { apagar_upload($f); apagar_upload(str_replace('.webp', '-p.webp', $f)); }
            $pdo->prepare('DELETE FROM atualizacoes WHERE id = ?')->execute([(int)$_POST['at_id']]);
        }
        avisar('Novidade removida.');
        ir("obra.php?id=$id#diario");
    }
    if ($acao === 'apagar') {
        foreach ($pdo->query("SELECT foto FROM atualizacoes WHERE obra_id = $id AND foto IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $f) {
            apagar_upload($f); apagar_upload(str_replace('.webp', '-p.webp', $f));
        }
        $pdo->prepare('DELETE FROM obras WHERE id = ?')->execute([$id]);
        avisar('Obra apagada.');
        ir('obras.php');
    }
    ir("obra.php?id=$id");
}

$clientes = $pdo->query('SELECT id, nome FROM clientes ORDER BY nome')->fetchAll(PDO::FETCH_KEY_PAIR);
$id = (int)($_GET['id'] ?? 0);
$obra = $id ? carregar_obra($id) : null;
if ($id && !$obra) ir('obras.php');
$o = $obra ?? ['id' => 0, 'titulo' => '', 'cliente_id' => (int)($_GET['cliente'] ?? 0), 'endereco' => '', 'servico' => 'Casa pré moldada', 'status' => 'planejada', 'inicio' => '', 'previsao' => '', 'valor' => 0, 'notas' => '', 'codigo' => ''];
if (!$obra && $o['cliente_id'] && isset($clientes[$o['cliente_id']])) {
    $st = $pdo->prepare('SELECT endereco FROM clientes WHERE id = ?'); $st->execute([$o['cliente_id']]); $o['endereco'] = (string)$st->fetchColumn();
}

topo($obra ? $o['titulo'] : 'Nova obra', 'obras', $u);
?>
<a href="obras.php" class="pq">← Todas as obras</a>
<div class="cab" style="margin-top:10px">
  <div><?php if ($obra): ?><span class="kicker">Código <?= h($o['codigo']) ?></span><?php endif ?><h1><?= $obra ? h($o['titulo']) : 'Nova obra' ?></h1></div>
  <?php if ($obra): ?><?= rotulo_status($o['status']) ?><?php endif ?>
</div>

<?php if ($obra):
  $link = (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . '/acompanhar.php?c=' . $o['codigo'];
  $cli = $o['cliente_id'] ? $pdo->query('SELECT nome, whatsapp FROM clientes WHERE id = ' . (int)$o['cliente_id'])->fetch() : null;
  $primeiro = $cli ? explode(' ', trim($cli['nome']))[0] : '';
  $msgLink = "Olá" . ($primeiro ? ", $primeiro" : '') . "! Por este link você acompanha sua obra com a Farias Araújo: etapa atual, fotos e próximos passos. $link (código {$o['codigo']})";
?>
<section class="cartao" style="margin-bottom:20px;display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap">
  <div><span class="num-rot">Link de acompanhamento do cliente</span><p style="margin:4px 0 0;word-break:break-all"><a href="<?= h($link) ?>" target="_blank" rel="noopener"><?= h($link) ?></a></p></div>
  <div class="acoes"><button type="button" class="btn btn-pq" data-copiar="<?= h($link) ?>">Copiar link</button><?php if ($cli && $cli['whatsapp']): ?><a class="btn btn-zap btn-pq" target="_blank" rel="noopener" href="<?= h(whats_link($cli['whatsapp'], $msgLink)) ?>">Mandar para <?= h($primeiro) ?></a><?php endif ?></div>
</section>
<?php endif ?>

<?php if ($obra):
  $mo = (float)$pdo->query("SELECT COALESCE(SUM(fracao * valor + extra),0) FROM diarias WHERE obra_id = $id")->fetchColumn();
  $diasObra = (float)$pdo->query("SELECT COALESCE(SUM(fracao),0) FROM diarias WHERE obra_id = $id")->fetchColumn();
  $desp = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE obra_id = $id")->fetchColumn();
  $custo = $mo + $desp; $saldo = (float)$o['valor'] - $custo;
?>
<div class="grade g4 metricas" style="margin-bottom:20px">
  <a class="cartao cartao-num" href="diarias.php"><span class="num-rot">Mão de obra</span><span class="num dinheiro" style="font-size:24px"><?= brl($mo) ?></span><span class="pq"><?= dias_br($diasObra) ?> diárias nesta obra</span></a>
  <a class="cartao cartao-num" href="despesas.php?p=livre&ini=2000-01-01&fim=2100-12-31&obra=<?= $id ?>"><span class="num-rot">Despesas</span><span class="num dinheiro" style="font-size:24px"><?= brl($desp) ?></span><span class="pq">ver lançamentos</span></a>
  <div class="cartao"><span class="num-rot">Custo até agora</span><span class="num dinheiro" style="font-size:24px"><?= brl($custo) ?></span><span class="pq">mão de obra + despesas</span></div>
  <div class="cartao <?= $o['valor'] > 0 ? 'destaque' : '' ?>"><span class="num-rot">Sobra do contrato</span><span class="num dinheiro" style="font-size:24px;<?= $saldo < 0 ? 'color:var(--erro)' : '' ?>"><?= $o['valor'] > 0 ? brl($saldo) : 'Sem valor' ?></span><span class="pq"><?= $o['valor'] > 0 ? 'de ' . brl((float)$o['valor']) : 'Informe o valor do contrato' ?></span></div>
</div>
<?php endif ?>

<div class="lado">
  <form class="cartao" method="post">
    <h2>Dados da obra</h2>
    <?= campo_csrf() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
    <div class="campo"><label for="titulo">Nome da obra</label><input id="titulo" name="titulo" required value="<?= h($o['titulo']) ?>" placeholder="Ex.: Casa pré moldada 70 m², Recanto das Emas"></div>
    <div class="grade g2">
      <div class="campo"><label for="cliente_id">Cliente</label><select id="cliente_id" name="cliente_id"><option value="">Sem cliente</option><?= opcoes($clientes, (string)$o['cliente_id'], true) ?></select><div class="dica"><a href="clientes.php?id=novo">Cadastrar cliente novo</a></div></div>
      <div class="campo"><label for="servico">Serviço principal</label><select id="servico" name="servico"><?= opcoes(FA_SERVICOS, $o['servico']) ?></select></div>
      <div class="campo"><label for="status">Situação</label><select id="status" name="status"><?= opcoes($STATUS, $o['status'], true) ?></select></div>
      <div class="campo"><label for="valor">Valor do contrato</label><input id="valor" name="valor" inputmode="decimal" value="<?= $o['valor'] ? number_format((float)$o['valor'], 2, ',', '.') : '' ?>" placeholder="0,00"></div>
      <div class="campo"><label for="inicio">Início</label><input id="inicio" type="date" name="inicio" value="<?= h($o['inicio']) ?>"></div>
      <div class="campo"><label for="previsao">Previsão de entrega</label><input id="previsao" type="date" name="previsao" value="<?= h($o['previsao']) ?>"></div>
    </div>
    <div class="campo"><label for="endereco">Endereço</label><input id="endereco" name="endereco" value="<?= h($o['endereco']) ?>"></div>
    <div class="campo"><label for="notas">Anotações internas</label><textarea id="notas" name="notas" placeholder="O cliente não vê isto."><?= h($o['notas']) ?></textarea></div>
    <?php if (!$obra): ?><div class="campo"><label><input type="checkbox" name="etapas_padrao" value="1" checked> Criar as etapas padrão (base, pilares, vigas, cobertura, instalações, revestimento, pintura, entrega)</label></div><?php endif ?>
    <button class="btn btn-ouro"><?= $obra ? 'Salvar obra' : 'Cadastrar obra' ?></button>
  </form>

  <?php if ($obra):
    $etapas = $pdo->query("SELECT * FROM etapas WHERE obra_id = $id ORDER BY ordem, id")->fetchAll();
    $feitas = count(array_filter($etapas, fn($e) => $e['status'] === 'concluida'));
    $pct = $etapas ? round($feitas / count($etapas) * 100) : 0;
  ?>
  <form class="cartao" method="post" id="etapas">
    <div class="cab" style="margin-bottom:10px"><h2 style="margin:0">Etapas</h2><span class="pq"><?= $feitas ?> de <?= count($etapas) ?> · <?= $pct ?>%</span></div>
    <div class="barra-prog" style="margin-bottom:8px"><i style="width:<?= $pct ?>%"></i></div>
    <?= campo_csrf() ?><input type="hidden" name="acao" value="etapas"><input type="hidden" name="id" value="<?= $id ?>">
    <?php foreach ($etapas as $i => $e): ?>
      <div class="etapa <?= h($e['status']) ?>">
        <span class="bola"><?= $e['status'] === 'concluida' ? '✓' : $i + 1 ?></span>
        <div>
          <input name="etapa[<?= $e['id'] ?>][nome]" value="<?= h($e['nome']) ?>" aria-label="Nome da etapa <?= $i + 1 ?>" style="padding:6px 10px;font-weight:600">
          <div class="acoes" style="margin-top:6px">
            <input type="date" name="etapa[<?= $e['id'] ?>][previsao]" value="<?= h($e['previsao']) ?>" aria-label="Previsão" style="width:auto;padding:5px 8px;font-size:13px">
            <input name="etapa[<?= $e['id'] ?>][valor]" value="<?= $e['valor'] ? number_format((float)$e['valor'], 2, ',', '.') : '' ?>" placeholder="Valor da etapa" aria-label="Valor da etapa" inputmode="decimal" style="width:130px;padding:5px 8px;font-size:13px">
          </div>
        </div>
        <select name="etapa[<?= $e['id'] ?>][status]" aria-label="Situação da etapa"><?= opcoes($ESTADOS, $e['status'], true) ?><option value="remover">Remover etapa</option></select>
      </div>
    <?php endforeach ?>
    <div class="campo" style="margin-top:14px"><label for="nova_etapa">Adicionar etapa</label><input id="nova_etapa" name="nova_etapa" placeholder="Ex.: Muro e calçada"></div>
    <button class="btn btn-ouro">Salvar etapas</button>
  </form>
  <?php endif ?>
</div>

<?php if ($obra):
  $ats = $pdo->query("SELECT * FROM atualizacoes WHERE obra_id = $id ORDER BY id DESC")->fetchAll();
?>
<section class="grade" id="diario" style="margin-top:20px">
  <form class="cartao" method="post" enctype="multipart/form-data">
    <h2>Publicar novidade da obra</h2>
    <p class="pq" style="margin-top:-6px">Aparece na página de acompanhamento do cliente.</p>
    <?= campo_csrf() ?><input type="hidden" name="acao" value="atualizacao"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="grade g2">
      <div class="campo"><label for="texto">O que aconteceu</label><textarea id="texto" name="texto" placeholder="Ex.: Pilares do fundo montados. Semana que vem entram as vigas."></textarea></div>
      <div class="campo"><label for="foto">Foto (opcional)</label><input id="foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp" capture="environment"><div class="dica">Pode tirar direto pelo celular.</div></div>
    </div>
    <button class="btn btn-ouro">Publicar novidade</button>
  </form>
  <?php if ($ats): ?>
  <div class="fotos">
    <?php foreach ($ats as $a): ?>
      <div class="foto">
        <?php if ($a['foto']): ?><img src="<?= FA_UPLOADS_URL . '/' . h(str_replace('.webp', '-p.webp', $a['foto'])) ?>" alt="" loading="lazy"><?php endif ?>
        <div class="info"><b><?= data_br($a['criado_em']) ?></b><?= nl2br(h($a['texto'])) ?>
          <form method="post" data-confirma="Remover esta novidade?" style="margin-top:8px"><?= campo_csrf() ?><input type="hidden" name="acao" value="rm_atualizacao"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="at_id" value="<?= $a['id'] ?>"><button class="btn btn-perigo btn-pq">Remover</button></form>
        </div>
      </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>
</section>
<form method="post" style="margin-top:28px" data-confirma="Apagar a obra <?= h($o['titulo']) ?> com todas as etapas e fotos? Não dá para desfazer."><?= campo_csrf() ?><input type="hidden" name="acao" value="apagar"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-perigo btn-pq">Apagar obra <?= h($o['titulo']) ?></button></form>
<?php endif;
rodape();
