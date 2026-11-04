<?php
// Primeiro acesso: cria as contas de Kauan e David. Some sozinho depois que existe alguém.
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
iniciar_sessao();

if ((int)db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) ir('login.php');

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checar_csrf();
    $contas = [];
    foreach (['kauan' => 'Kauan', 'david' => 'David'] as $login => $nome) {
        $s1 = (string)($_POST["senha_$login"] ?? '');
        $s2 = (string)($_POST["conf_$login"] ?? '');
        if (strlen($s1) < 8) { $erro = "A senha de $nome precisa ter pelo menos 8 caracteres."; break; }
        if ($s1 !== $s2) { $erro = "As duas senhas de $nome não são iguais."; break; }
        $contas[] = [$nome, $login, password_hash($s1, PASSWORD_DEFAULT)];
    }
    if (!$erro) {
        $st = db()->prepare('INSERT INTO usuarios(nome, login, senha_hash) VALUES(?,?,?)');
        foreach ($contas as $c) $st->execute($c);
        avisar('Contas criadas. Entre com seu usuário e senha.');
        ir('login.php');
    }
}
topo('Primeiro acesso', '', null);
?>
<div class="login">
  <form class="cartao" method="post">
    <img src="/assets/img/logo-escuro.webp" alt="Farias Araújo Construções Pré Moldadas" width="170" height="111">
    <h1 style="text-align:center">Primeiro acesso</h1>
    <p class="sub" style="text-align:center">Crie a senha de cada sócio. Isso aparece uma vez só.</p>
    <?php if ($erro): ?><div class="aviso aviso-erro" role="alert"><?= h($erro) ?></div><?php endif ?>
    <?= campo_csrf() ?>
    <?php foreach (['kauan' => 'Kauan', 'david' => 'David'] as $login => $nome): ?>
      <fieldset style="border:1px solid var(--line);border-radius:12px;padding:14px 14px 2px;margin:0 0 14px">
        <legend class="kicker" style="padding:0 6px"><?= $nome ?> · usuário <?= $login ?></legend>
        <div class="campo"><label for="s_<?= $login ?>">Senha</label><input id="s_<?= $login ?>" type="password" name="senha_<?= $login ?>" minlength="8" required autocomplete="new-password"></div>
        <div class="campo"><label for="c_<?= $login ?>">Repita a senha</label><input id="c_<?= $login ?>" type="password" name="conf_<?= $login ?>" minlength="8" required autocomplete="new-password"></div>
      </fieldset>
    <?php endforeach ?>
    <button class="btn btn-ouro" style="width:100%">Criar as contas</button>
  </form>
</div>
<?php rodape();
