<?php
// Página pública: o cliente digita o código e vê o andamento da obra.
declare(strict_types=1);
require_once __DIR__ . '/inc/db.php';
header('X-Robots-Tag: noindex');

$codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['c'] ?? '')));
$obra = null; $erro = ''; $etapas = []; $ats = [];
if ($codigo !== '') {
    if (!limite_ok('acomp:' . ip_cliente(), 40, 600)) {
        $erro = 'Muitas consultas seguidas. Espere alguns minutos e tente de novo.';
    } else {
        $st = db()->prepare('SELECT o.id, o.titulo, o.servico, o.status, o.inicio, o.previsao, o.codigo, c.nome AS cliente FROM obras o LEFT JOIN clientes c ON c.id = o.cliente_id WHERE o.codigo = ?');
        $st->execute([$codigo]);
        $obra = $st->fetch() ?: null;
        if (!$obra) $erro = 'Não achamos esse código. Confira as letras e números que a equipe mandou.';
        else {
            $st = db()->prepare('SELECT nome, status, previsao, concluida_em FROM etapas WHERE obra_id = ? ORDER BY ordem, id'); $st->execute([$obra['id']]); $etapas = $st->fetchAll();
            $st = db()->prepare('SELECT texto, foto, criado_em FROM atualizacoes WHERE obra_id = ? ORDER BY id DESC LIMIT 30'); $st->execute([$obra['id']]); $ats = $st->fetchAll();
        }
    }
}
$feitas = count(array_filter($etapas, fn($e) => $e['status'] === 'concluida'));
$pct = $etapas ? (int)round($feitas / count($etapas) * 100) : 0;
$atual = null;
foreach ($etapas as $e) if ($e['status'] !== 'concluida') { $atual = $e; break; }
$STATUS = ['planejada' => 'Planejada', 'em_andamento' => 'Em andamento', 'pausada' => 'Pausada', 'concluida' => 'Entregue'];
$primeiro = $obra && $obra['cliente'] ? explode(' ', trim($obra['cliente']))[0] : '';
$circ = 2 * M_PI * 52;
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#121316">
<title><?= $obra ? h($obra['titulo']) . ' · ' : '' ?>Acompanhe sua obra · Farias Araújo</title>
<link rel="icon" href="/assets/img/logo-marca.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600&family=IBM+Plex+Mono:wght@400;500&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/painel.css?v=1">
<style>
  .acomp{max-width:880px;margin:0 auto;padding:22px 18px 70px}
  .acomp header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:28px}
  .acomp header img{width:120px;height:auto}
  .resumo{display:grid;grid-template-columns:150px minmax(0,1fr);gap:24px;align-items:center}
  .anel{position:relative;width:150px;height:150px}
  .anel svg{transform:rotate(-90deg)}
  .anel circle{fill:none;stroke-width:7}
  .anel .fundo{stroke:var(--panel-2)}
  .anel .frente{stroke:var(--accent);stroke-linecap:round;transition:stroke-dashoffset 1.4s var(--ease)}
  .anel b{position:absolute;inset:0;display:grid;place-items:center;font:600 30px var(--serif)}
  .linha-tempo{position:relative;margin:0;padding:0;list-style:none}
  .linha-tempo li{position:relative;padding:0 0 22px 38px}
  .linha-tempo li:before{content:"";position:absolute;left:11px;top:24px;bottom:0;width:1.5px;background:var(--line-forte)}
  .linha-tempo li:last-child:before{display:none}
  .linha-tempo .bola{position:absolute;left:0;top:0;width:24px;height:24px;border-radius:50%;border:1.5px solid var(--line-forte);display:grid;place-items:center;font:500 11px var(--mono);color:var(--t2);background:var(--canvas)}
  .linha-tempo .concluida .bola{background:var(--accent);border-color:var(--accent);color:#16130c}
  .linha-tempo .concluida:before{background:var(--accent)}
  .linha-tempo .agora .bola{border-color:var(--accent);color:var(--accent);box-shadow:0 0 0 5px var(--accent-muted)}
  .linha-tempo strong{display:block}
  .novidade{display:grid;grid-template-columns:minmax(0,1fr);gap:0;overflow:hidden;padding:0}
  .novidade img{width:100%;max-height:420px;object-fit:cover;display:block}
  .novidade div{padding:14px 16px}
  @media (max-width:600px){.resumo{grid-template-columns:1fr;justify-items:center;text-align:center}}
</style>
</head>
<body>
<main class="acomp" id="main">
  <header><a href="/"><img src="/assets/img/logo.webp" alt="Farias Araújo Construções Pré Moldadas" width="120" height="78"></a><span class="kicker">Acompanhe sua obra</span></header>

  <?php if (!$obra): ?>
    <form class="cartao" method="get" style="max-width:460px;margin:30px auto">
      <h1>Acompanhe sua obra</h1>
      <p class="sub">Digite o código que a equipe mandou no WhatsApp.</p>
      <?php if ($erro): ?><div class="aviso aviso-erro" role="alert"><?= h($erro) ?></div><?php endif ?>
      <div class="campo"><label for="c">Código da obra</label><input id="c" name="c" value="<?= h($codigo) ?>" placeholder="Ex.: FA7K2Q" autocapitalize="characters" autocomplete="off" required style="font:500 18px var(--mono);letter-spacing:.12em;text-transform:uppercase"></div>
      <button class="btn btn-ouro" style="width:100%">Ver minha obra</button>
      <p class="pq" style="text-align:center;margin:16px 0 0">Perdeu o código? <a href="<?= h(whats_link(FA_WHATS_PRINCIPAL, 'Olá! Quero o código para acompanhar minha obra.')) ?>">Peça no WhatsApp</a></p>
    </form>
  <?php else: ?>
    <section class="cartao resumo">
      <div class="anel" role="img" aria-label="<?= $pct ?> por cento da obra concluída">
        <svg width="150" height="150" viewBox="0 0 120 120"><circle class="fundo" cx="60" cy="60" r="52"/><circle class="frente" cx="60" cy="60" r="52" stroke-dasharray="<?= $circ ?>" stroke-dashoffset="<?= $circ * (1 - $pct / 100) ?>"/></svg>
        <b><?= $pct ?>%</b>
      </div>
      <div>
        <span class="kicker">Código <?= h($obra['codigo']) ?> · <?= h($STATUS[$obra['status']] ?? $obra['status']) ?></span>
        <h1 style="margin-top:6px"><?= $primeiro ? 'Olá, ' . h($primeiro) . '.' : h($obra['titulo']) ?></h1>
        <p class="sub" style="margin:4px 0 0"><?= $primeiro ? h($obra['titulo']) . '. ' : '' ?><?= $atual ? 'Agora: <b style="color:var(--t1)">' . h($atual['nome']) . '</b>.' : ($etapas ? 'Todas as etapas concluídas.' : 'As etapas aparecem aqui assim que a obra começar.') ?></p>
        <?php if ($obra['previsao']): ?><p class="pq" style="margin:10px 0 0">Previsão de entrega: <b style="color:var(--t1)"><?= data_br($obra['previsao']) ?></b></p><?php endif ?>
      </div>
    </section>

    <div class="grade g2" style="margin-top:20px;align-items:start">
      <section class="cartao">
        <h2>Etapas</h2>
        <?php if (!$etapas): ?><p class="pq">Ainda sem etapas cadastradas.</p><?php endif ?>
        <ol class="linha-tempo">
          <?php foreach ($etapas as $i => $e): $cls = $e['status'] === 'concluida' ? 'concluida' : ($atual && $e === $atual ? 'agora' : ''); ?>
            <li class="<?= $cls ?>"><span class="bola"><?= $e['status'] === 'concluida' ? '✓' : $i + 1 ?></span><strong><?= h($e['nome']) ?></strong>
              <span class="pq"><?= $e['status'] === 'concluida' ? 'Concluída' . ($e['concluida_em'] ? ' em ' . data_br($e['concluida_em']) : '') : ($e['status'] === 'andamento' ? 'Em andamento' : 'A fazer') ?><?= $e['previsao'] && $e['status'] !== 'concluida' ? ' · previsão ' . data_br($e['previsao']) : '' ?></span></li>
          <?php endforeach ?>
        </ol>
      </section>
      <section class="grade">
        <h2 style="margin:0">Novidades da obra</h2>
        <?php if (!$ats): ?><div class="cartao vazio" style="padding:28px 16px"><strong>Nada publicado ainda</strong>As fotos e avisos da equipe aparecem aqui.</div><?php endif ?>
        <?php foreach ($ats as $a): ?>
          <article class="cartao novidade">
            <?php if ($a['foto']): ?><a href="<?= FA_UPLOADS_URL . '/' . h($a['foto']) ?>" target="_blank" rel="noopener"><img src="<?= FA_UPLOADS_URL . '/' . h(str_replace('.webp', '-p.webp', $a['foto'])) ?>" alt="Foto da obra em <?= data_br($a['criado_em']) ?>" loading="lazy"></a><?php endif ?>
            <div><span class="kicker"><?= data_br($a['criado_em']) ?></span><?php if ($a['texto']): ?><p style="margin:6px 0 0;white-space:pre-line"><?= h($a['texto']) ?></p><?php endif ?></div>
          </article>
        <?php endforeach ?>
      </section>
    </div>

    <section class="cartao" style="margin-top:20px;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
      <div><h2 style="margin:0 0 2px">Dúvida sobre a obra?</h2><p class="pq" style="margin:0">Fale direto com quem está construindo.</p></div>
      <div class="acoes"><?php foreach (FA_CONTATOS as $c): ?><a class="btn btn-zap" href="<?= h(whats_link($c['whats'], 'Olá, ' . $c['nome'] . '! Sobre a obra ' . $obra['codigo'] . ': ')) ?>" target="_blank" rel="noopener">Falar com <?= $c['nome'] ?></a><?php endforeach ?></div>
    </section>
  <?php endif ?>
</main>
</body>
</html>
