<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();
$pdo = db();
$STATUS = ['rascunho' => 'Rascunho', 'enviado' => 'Enviado', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado'];
$CONDICOES_PADRAO = "Pagamento por etapa: cada parte é paga quando a etapa anterior estiver concluída.\nMateriais e mão de obra inclusos conforme os itens acima.\nPrevisão de cada etapa combinada antes do início.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['acao'] ?? '') === 'apagar' && $id) {
        $pdo->prepare('DELETE FROM orcamentos WHERE id = ?')->execute([$id]);
        avisar('Orçamento apagado.');
        ir('orcamentos.php');
    }
    $v = [
        'cliente_id' => ((int)($_POST['cliente_id'] ?? 0)) ?: null,
        'pedido_id' => ((int)($_POST['pedido_id'] ?? 0)) ?: null,
        'cliente_nome' => trim((string)($_POST['cliente_nome'] ?? '')),
        'cliente_whatsapp' => trim((string)($_POST['cliente_whatsapp'] ?? '')),
        'cidade' => trim((string)($_POST['cidade'] ?? '')),
        'titulo' => trim((string)($_POST['titulo'] ?? '')),
        'validade_dias' => max(1, (int)($_POST['validade_dias'] ?? 15)),
        'prazo_execucao' => trim((string)($_POST['prazo_execucao'] ?? '')),
        'condicoes' => trim((string)($_POST['condicoes'] ?? '')),
        'observacoes' => trim((string)($_POST['observacoes'] ?? '')),
        'desconto' => num_br((string)($_POST['desconto'] ?? '')),
        'status' => array_key_exists($_POST['status'] ?? '', $STATUS) ? $_POST['status'] : 'rascunho',
    ];
    if ($v['cliente_id'] && $v['cliente_nome'] === '') {
        $st = $pdo->prepare('SELECT nome, whatsapp, cidade FROM clientes WHERE id = ?'); $st->execute([$v['cliente_id']]);
        if ($c = $st->fetch()) { $v['cliente_nome'] = $c['nome']; $v['cliente_whatsapp'] = $v['cliente_whatsapp'] ?: (string)$c['whatsapp']; $v['cidade'] = $v['cidade'] ?: (string)$c['cidade']; }
    }
    if ($v['cliente_nome'] === '' || $v['titulo'] === '') {
        avisar('Preencha o nome do cliente e o título do orçamento.', 'erro');
        ir($id ? "orcamento.php?id=$id" : 'orcamento.php?novo=1');
    }
    $pdo->beginTransaction();
    if ($id) {
        $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($v)));
        $pdo->prepare("UPDATE orcamentos SET $sets WHERE id = ?")->execute([...array_values($v), $id]);
        $pdo->prepare('DELETE FROM orcamento_itens WHERE orcamento_id = ?')->execute([$id]);
    } else {
        $v['numero'] = numero_orcamento();
        $pdo->prepare('INSERT INTO orcamentos(' . implode(',', array_keys($v)) . ') VALUES(' . rtrim(str_repeat('?,', count($v)), ',') . ')')->execute(array_values($v));
        $id = (int)$pdo->lastInsertId();
        if ($v['pedido_id']) $pdo->prepare("UPDATE pedidos SET status = 'orcado' WHERE id = ? AND status IN ('novo','contatado')")->execute([$v['pedido_id']]);
    }
    $ins = $pdo->prepare('INSERT INTO orcamento_itens(orcamento_id, descricao, unidade, quantidade, valor_unit, ordem) VALUES(?,?,?,?,?,?)');
    $d = (array)($_POST['d'] ?? []);
    foreach ($d as $i => $desc) {
        $desc = trim((string)$desc);
        if ($desc === '') continue;
        $ins->execute([$id, $desc, trim((string)($_POST['u'][$i] ?? '')), num_br((string)($_POST['q'][$i] ?? '1')) ?: 1, num_br((string)($_POST['v'][$i] ?? '')), $i]);
    }
    $pdo->commit();
    avisar('Orçamento salvo.');
    ir(isset($_POST['e_imprimir']) ? "orcamento-pdf.php?id=$id" : "orcamento.php?id=$id");
}

$id = (int)($_GET['id'] ?? 0);
$itens = [];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM orcamentos WHERE id = ?'); $st->execute([$id]);
    $o = $st->fetch() ?: ir('orcamentos.php');
    $st = $pdo->prepare('SELECT * FROM orcamento_itens WHERE orcamento_id = ? ORDER BY ordem, id'); $st->execute([$id]);
    $itens = $st->fetchAll();
} else {
    $o = ['id' => 0, 'numero' => '', 'cliente_id' => null, 'pedido_id' => null, 'cliente_nome' => '', 'cliente_whatsapp' => '', 'cidade' => '', 'titulo' => '', 'validade_dias' => 15, 'prazo_execucao' => '', 'condicoes' => $CONDICOES_PADRAO, 'observacoes' => '', 'desconto' => 0, 'status' => 'rascunho'];
    if ($pid = (int)($_GET['pedido'] ?? 0)) {
        $st = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?'); $st->execute([$pid]);
        if ($p = $st->fetch()) {
            $o = array_merge($o, ['pedido_id' => $pid, 'cliente_id' => $p['cliente_id'], 'cliente_nome' => $p['nome'], 'cliente_whatsapp' => $p['whatsapp'], 'cidade' => $p['cidade'], 'titulo' => $p['servico'] . ($p['metragem'] ? ' · ' . $p['metragem'] . ' m²' : '')]);
        }
    }
    if ($cid = (int)($_GET['cliente'] ?? 0)) {
        $st = $pdo->prepare('SELECT * FROM clientes WHERE id = ?'); $st->execute([$cid]);
        if ($c = $st->fetch()) $o = array_merge($o, ['cliente_id' => $cid, 'cliente_nome' => $c['nome'], 'cliente_whatsapp' => $c['whatsapp'], 'cidade' => $c['cidade']]);
    }
}
$clientes = $pdo->query('SELECT id, nome FROM clientes ORDER BY nome')->fetchAll(PDO::FETCH_KEY_PAIR);
$fmt = fn($n) => $n === null || $n === '' ? '' : rtrim(rtrim(number_format((float)$n, 2, ',', '.'), '0'), ',');
$fmt2 = fn($n) => (float)$n ? number_format((float)$n, 2, ',', '.') : '';
$SUGESTOES = [['Fundação e base', 'm²'], ['Pilares pré moldados', 'un'], ['Vigas pré moldadas', 'un'], ['Placas de fechamento', 'm²'], ['Cobertura', 'm²'], ['Instalação elétrica', 'vb'], ['Instalação hidráulica', 'vb'], ['Revestimento cerâmico', 'm²'], ['Piso polido', 'm²'], ['Pintura', 'm²'], ['Mão de obra', 'vb']];

topo($id ? 'Orçamento ' . $o['numero'] : 'Novo orçamento', 'orcamentos', $u);
?>
<a href="orcamentos.php" class="pq">← Todos os orçamentos</a>
<div class="cab" style="margin-top:10px">
  <div><span class="kicker"><?= $id ? 'Nº ' . h($o['numero']) : 'Novo' ?></span><h1><?= $id ? h($o['titulo']) : 'Novo orçamento' ?></h1></div>
  <?php if ($id): $total = max(0, total_orcamento($id) - (float)$o['desconto']); $primeiro = explode(' ', trim($o['cliente_nome']))[0]; ?>
  <div class="acoes">
    <a class="btn" href="orcamento-pdf.php?id=<?= $id ?>" target="_blank">Ver e baixar PDF</a>
    <?php if ($o['cliente_whatsapp']): ?><a class="btn btn-zap" target="_blank" rel="noopener" href="<?= h(whats_link($o['cliente_whatsapp'], "Olá, $primeiro! Segue o orçamento nº {$o['numero']} da Farias Araújo para {$o['titulo']}, no valor de " . brl($total) . ". Vou te mandar o PDF aqui. Qualquer dúvida é só chamar.")) ?>">Avisar no WhatsApp</a><?php endif ?>
  </div>
  <?php endif ?>
</div>

<form method="post">
  <?= campo_csrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="pedido_id" value="<?= (int)$o['pedido_id'] ?>">
  <div class="lado">
    <section class="cartao">
      <h2>Para quem</h2>
      <div class="grade g2">
        <div class="campo"><label for="cliente_id">Cliente cadastrado</label><select id="cliente_id" name="cliente_id"><option value="">Nenhum (preencher ao lado)</option><?= opcoes($clientes, (string)$o['cliente_id'], true) ?></select></div>
        <div class="campo"><label for="cliente_nome">Nome no orçamento</label><input id="cliente_nome" name="cliente_nome" value="<?= h($o['cliente_nome']) ?>" placeholder="Fica vazio para usar o do cadastro"></div>
        <div class="campo"><label for="cliente_whatsapp">WhatsApp</label><input id="cliente_whatsapp" name="cliente_whatsapp" inputmode="tel" value="<?= h($o['cliente_whatsapp']) ?>"></div>
        <div class="campo"><label for="cidade">Cidade da obra</label><input id="cidade" name="cidade" value="<?= h($o['cidade']) ?>"></div>
      </div>
      <div class="campo"><label for="titulo">Título</label><input id="titulo" name="titulo" required value="<?= h($o['titulo']) ?>" placeholder="Ex.: Casa pré moldada 2 quartos, 62 m²"></div>
    </section>
    <section class="cartao">
      <h2>Condições</h2>
      <div class="grade g2">
        <div class="campo"><label for="validade_dias">Validade (dias)</label><input id="validade_dias" name="validade_dias" type="number" min="1" value="<?= (int)$o['validade_dias'] ?>"></div>
        <div class="campo"><label for="status">Situação</label><select id="status" name="status"><?= opcoes($STATUS, $o['status'], true) ?></select></div>
      </div>
      <div class="campo"><label for="prazo_execucao">Prazo de execução</label><input id="prazo_execucao" name="prazo_execucao" value="<?= h($o['prazo_execucao']) ?>" placeholder="Ex.: 75 dias corridos após a base"></div>
      <div class="campo"><label for="condicoes">Pagamento e condições</label><textarea id="condicoes" name="condicoes"><?= h($o['condicoes']) ?></textarea></div>
    </section>
  </div>

  <section class="cartao" style="margin-top:20px">
    <h2>Itens</h2>
    <div class="acoes" style="margin-bottom:12px"><span class="pq">Adicionar rápido:</span><?php foreach ($SUGESTOES as [$s, $un]): ?><button type="button" class="btn btn-pq" data-sugestao="<?= h($s) ?>" data-un="<?= $un ?>"><?= h($s) ?></button><?php endforeach ?></div>
    <div class="tabela-wrap" style="background:transparent">
      <table id="itens" class="itens">
        <thead><tr><th>Descrição</th><th>Un.</th><th>Qtd.</th><th>Valor unit.</th><th class="dir">Total</th><th><span class="esconde-cel">Tirar</span></th></tr></thead>
        <tbody>
        <?php foreach ($itens as $it): ?>
          <tr><td class="c-desc"><input name="d[]" value="<?= h($it['descricao']) ?>" aria-label="Descrição"></td><td class="c-un"><input name="u[]" value="<?= h($it['unidade']) ?>" aria-label="Unidade"></td><td class="c-q"><input name="q[]" value="<?= $fmt($it['quantidade']) ?>" inputmode="decimal" aria-label="Quantidade"></td><td class="c-v"><input name="v[]" value="<?= $fmt2($it['valor_unit']) ?>" inputmode="decimal" aria-label="Valor unitário" placeholder="0,00"></td><td class="c-t dir"></td><td><button type="button" class="btn btn-perigo btn-pq rm" aria-label="Tirar item">×</button></td></tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <template id="modelo-item"><tr><td class="c-desc"><input name="d[]" aria-label="Descrição" placeholder="Ex.: Pilares pré moldados 15x15"></td><td class="c-un"><input name="u[]" aria-label="Unidade"></td><td class="c-q"><input name="q[]" inputmode="decimal" aria-label="Quantidade"></td><td class="c-v"><input name="v[]" inputmode="decimal" aria-label="Valor unitário" placeholder="0,00"></td><td class="c-t dir"></td><td><button type="button" class="btn btn-perigo btn-pq rm" aria-label="Tirar item">×</button></td></tr></template>
    <button type="button" class="btn" id="add-item" style="margin-top:12px">Adicionar item</button>
    <div class="totais">
      <div>Subtotal <b id="subtotal">R$ 0,00</b></div>
      <div style="display:flex;gap:10px;align-items:center;justify-content:end"><label for="desconto" style="margin:0;font-weight:500">Desconto</label><input id="desconto" name="desconto" inputmode="decimal" value="<?= $fmt2($o['desconto']) ?>" placeholder="0,00" style="width:130px"><span class="pq" id="desc-v" hidden></span></div>
      <div class="final">Total <span id="total">R$ 0,00</span></div>
    </div>
    <div class="campo" style="margin-top:14px"><label for="observacoes">Observações para o cliente</label><textarea id="observacoes" name="observacoes" placeholder="Ex.: Não inclui projeto arquitetônico nem taxas da prefeitura."><?= h($o['observacoes']) ?></textarea></div>
    <div class="acoes"><button class="btn btn-ouro">Salvar orçamento</button><button class="btn" name="e_imprimir" value="1">Salvar e gerar PDF</button></div>
  </section>
</form>
<?php if ($id): ?>
<form method="post" style="margin-top:28px" data-confirma="Apagar o orçamento <?= h($o['numero']) ?>? Não dá para desfazer."><?= campo_csrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="acao" value="apagar"><button class="btn btn-perigo btn-pq">Apagar orçamento <?= h($o['numero']) ?></button></form>
<?php endif;
rodape();
