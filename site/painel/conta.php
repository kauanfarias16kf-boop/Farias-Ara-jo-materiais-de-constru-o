<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
$u = exigir_login();
checar_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
    $st->execute([$u['id']]);
    $hash = (string)$st->fetchColumn();
    $atual = (string)($_POST['atual'] ?? '');
    $nova = (string)($_POST['nova'] ?? '');
    if (!password_verify($atual, $hash)) avisar('A senha atual não confere.', 'erro');
    elseif (strlen($nova) < 8) avisar('A senha nova precisa ter pelo menos 8 caracteres.', 'erro');
    elseif ($nova !== ($_POST['conf'] ?? '')) avisar('As duas senhas novas não são iguais.', 'erro');
    else {
        db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
        avisar('Senha trocada.');
    }
    ir('conta.php');
}
topo('Minha conta', '', $u);
?>
<h1>Minha conta</h1>
<p class="sub">Você entrou como <?= h($u['nome']) ?> (usuário <?= h($u['login']) ?>).</p>
<form class="cartao" method="post" style="max-width:460px">
  <h2>Trocar senha</h2>
  <?= campo_csrf() ?>
  <div class="campo"><label for="atual">Senha atual</label><input id="atual" type="password" name="atual" required autocomplete="current-password"></div>
  <div class="campo"><label for="nova">Senha nova</label><input id="nova" type="password" name="nova" minlength="8" required autocomplete="new-password"></div>
  <div class="campo"><label for="conf">Repita a senha nova</label><input id="conf" type="password" name="conf" minlength="8" required autocomplete="new-password"></div>
  <button class="btn btn-ouro">Salvar senha nova</button>
</form>
<?php rodape();
