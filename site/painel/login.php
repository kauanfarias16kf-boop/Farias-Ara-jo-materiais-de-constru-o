<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/painel.php';
iniciar_sessao();

if ((int)db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 0) ir('instalar.php');
if (usuario()) ir('index.php');

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checar_csrf();
    $login = strtolower(trim((string)($_POST['login'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');
    if (!limite_ok('login:' . ip_cliente(), 8, 900)) {
        $erro = 'Muitas tentativas seguidas. Espere 15 minutos e tente de novo.';
    } else {
        $st = db()->prepare('SELECT * FROM usuarios WHERE login = ?');
        $st->execute([$login]);
        $u = $st->fetch();
        if ($u && password_verify($senha, $u['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'login' => $u['login']];
            $_SESSION['visto'] = time();
            ir('index.php');
        }
        $erro = 'Usuário ou senha não conferem. Confira e tente de novo.';
    }
}
topo('Entrar', '', null);
?>
<div class="login">
  <form class="cartao" method="post">
    <img src="/assets/img/logo-escuro.webp" alt="Farias Araújo Construções Pré Moldadas" width="170" height="111">
    <h1 style="text-align:center">Painel da equipe</h1>
    <p class="sub" style="text-align:center">Pedidos, obras, orçamentos e galeria.</p>
    <?php if ($erro): ?><div class="aviso aviso-erro" role="alert"><?= h($erro) ?></div><?php endif ?>
    <?= campo_csrf() ?>
    <div class="campo"><label for="login">Usuário</label><input id="login" name="login" autocomplete="username" autocapitalize="none" required value="<?= h($_POST['login'] ?? '') ?>"><div class="dica">kauan ou david</div></div>
    <div class="campo"><label for="senha">Senha</label><input id="senha" type="password" name="senha" autocomplete="current-password" required></div>
    <button class="btn btn-ouro" style="width:100%">Entrar no painel</button>
  </form>
</div>
<?php rodape();
