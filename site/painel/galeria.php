<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'enviar') {
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $servico = trim((string)($_POST['servico'] ?? ''));
        $cidade = trim((string)($_POST['cidade'] ?? ''));
        $files = $_FILES['fotos'] ?? null;
        $n = 0; $erros = [];
        if ($files && is_array($files['name'])) {
            $ordem = (int)$pdo->query('SELECT COALESCE(MAX(ordem),0) FROM galeria')->fetchColumn();
            foreach ($files['name'] as $i => $nome) {
                if ($nome === '') continue;
                $f = ['name' => $nome, 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                try {
                    [$arq, $mini] = salvar_imagem($f, 'galeria');
                    $pdo->prepare('INSERT INTO galeria(titulo, servico, cidade, arquivo, miniatura, ordem) VALUES(?,?,?,?,?,?)')
                        ->execute([$titulo ?: $servico ?: 'Obra Farias Araújo', $servico, $cidade, $arq, $mini, ++$ordem]);
                    $n++;
                } catch (RuntimeException $e) { $erros[] = "$nome: " . $e->getMessage(); }
            }
        }
        if ($n) avisar("$n " . ($n === 1 ? 'foto publicada' : 'fotos publicadas') . ' no site.' . ($erros ? ' Não entrou: ' . implode(' ', $erros) : ''));
        else avisar($erros ? implode(' ', $erros) : 'Escolha pelo menos uma foto.', 'erro');
        ir('galeria.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($acao === 'alternar') {
        $pdo->prepare('UPDATE galeria SET publicado = 1 - publicado WHERE id = ?')->execute([$id]);
        avisar('Visibilidade trocada.');
    }
    if ($acao === 'salvar') {
        $pdo->prepare('UPDATE galeria SET titulo = ?, servico = ?, cidade = ? WHERE id = ?')->execute([trim((string)$_POST['titulo']), trim((string)$_POST['servico']), trim((string)$_POST['cidade']), $id]);
        avisar('Foto atualizada.');
    }
    if ($acao === 'subir' || $acao === 'descer') {
        $lista = $pdo->query('SELECT id FROM galeria ORDER BY ordem, id')->fetchAll(PDO::FETCH_COLUMN);
        $pos = array_search($id, array_map('intval', $lista), true);
        $alvo = $acao === 'subir' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($lista[$alvo])) {
            [$lista[$pos], $lista[$alvo]] = [$lista[$alvo], $lista[$pos]];
            $up = $pdo->prepare('UPDATE galeria SET ordem = ? WHERE id = ?');
            foreach ($lista as $i => $gid) $up->execute([$i + 1, $gid]);
        }
    }
    if ($acao === 'apagar') {
        $st = $pdo->prepare('SELECT arquivo, miniatura FROM galeria WHERE id = ?'); $st->execute([$id]);
        if ($g = $st->fetch()) { apagar_upload($g['arquivo']); apagar_upload($g['miniatura']); }
        $pdo->prepare('DELETE FROM galeria WHERE id = ?')->execute([$id]);
        avisar('Foto apagada.');
    }
    ir('galeria.php');
}

$fotos = $pdo->query('SELECT * FROM galeria ORDER BY ordem, id')->fetchAll();
topo('Galeria', 'galeria', $u);
?>
<div class="cab"><div><h1>Galeria de obras</h1><p class="sub">As fotos marcadas como visíveis aparecem na seção "Obras" do site, na ordem daqui.</p></div><a class="btn" href="/#obras" target="_blank" rel="noopener">Ver no site</a></div>

<form class="cartao" method="post" enctype="multipart/form-data" style="margin-bottom:20px">
  <h2>Publicar fotos</h2>
  <?= campo_csrf() ?><input type="hidden" name="acao" value="enviar">
  <div class="grade g4">
    <div class="campo"><label for="fotos">Fotos</label><input id="fotos" type="file" name="fotos[]" multiple required accept="image/jpeg,image/png,image/webp"><div class="dica">Pode escolher várias de uma vez.</div></div>
    <div class="campo"><label for="titulo">Título</label><input id="titulo" name="titulo" placeholder="Ex.: Casa 2 quartos"></div>
    <div class="campo"><label for="servico">Serviço</label><select id="servico" name="servico"><?= opcoes(FA_SERVICOS, 'Casa pré moldada') ?></select></div>
    <div class="campo"><label for="cidade">Cidade</label><input id="cidade" name="cidade" placeholder="Ex.: Samambaia"></div>
  </div>
  <button class="btn btn-ouro">Publicar no site</button>
</form>

<?php if (!$fotos): ?>
  <div class="cartao vazio"><strong>Nenhuma foto ainda</strong>Enquanto a galeria estiver vazia, o site mostra um convite para o Instagram.</div>
<?php else: ?>
<div class="fotos" style="grid-template-columns:repeat(auto-fill,minmax(230px,1fr))">
  <?php foreach ($fotos as $i => $g): ?>
  <div class="foto <?= $g['publicado'] ? '' : 'oculta' ?>">
    <img src="<?= FA_UPLOADS_URL . '/' . h($g['miniatura']) ?>" alt="<?= h($g['titulo']) ?>" loading="lazy">
    <div class="info">
      <form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= $g['id'] ?>">
        <input name="titulo" value="<?= h($g['titulo']) ?>" aria-label="Título" style="padding:6px 9px;font-size:13px;margin-bottom:6px">
        <select name="servico" aria-label="Serviço" style="padding:6px 9px;font-size:13px;margin-bottom:6px"><?= opcoes(FA_SERVICOS, $g['servico']) ?></select>
        <input name="cidade" value="<?= h($g['cidade']) ?>" aria-label="Cidade" placeholder="Cidade" style="padding:6px 9px;font-size:13px;margin-bottom:8px">
        <button class="btn btn-pq">Salvar</button>
      </form>
      <div class="acoes" style="margin-top:8px">
        <?php foreach (['alternar' => $g['publicado'] ? 'Esconder' : 'Mostrar', 'subir' => '↑', 'descer' => '↓'] as $a => $rot): if (($a === 'subir' && $i === 0) || ($a === 'descer' && $i === count($fotos) - 1)) continue; ?>
          <form method="post"><?= campo_csrf() ?><input type="hidden" name="acao" value="<?= $a ?>"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn btn-pq" <?= $a !== 'alternar' ? 'aria-label="' . ($a === 'subir' ? 'Mover para antes' : 'Mover para depois') . '"' : '' ?>><?= $rot ?></button></form>
        <?php endforeach ?>
        <form method="post" data-confirma="Apagar esta foto do site?"><?= campo_csrf() ?><input type="hidden" name="acao" value="apagar"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn btn-perigo btn-pq">Apagar</button></form>
      </div>
      <?php if (!$g['publicado']): ?><p class="pq" style="margin:8px 0 0">Escondida do site</p><?php endif ?>
    </div>
  </div>
  <?php endforeach ?>
</div>
<?php endif;
rodape();
