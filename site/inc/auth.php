<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function iniciar_sessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('fa_painel');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
    session_start();
}

function usuario(): ?array
{
    iniciar_sessao();
    return $_SESSION['usuario'] ?? null;
}

function exigir_login(): array
{
    $u = usuario();
    if (!$u) {
        $tem = (int)db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        header('Location: ' . ($tem ? 'login.php' : 'instalar.php'));
        exit;
    }
    // sessão expira depois de 12 horas parada
    if (time() - ($_SESSION['visto'] ?? 0) > 43200) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $_SESSION['visto'] = time();
    return $u;
}

function csrf(): string
{
    iniciar_sessao();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf() . '">';
}

function checar_csrf(): void
{
    iniciar_sessao();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
        if (!$ok) {
            http_response_code(400);
            exit('A página ficou aberta por muito tempo. Volte e tente de novo.');
        }
    }
}

function avisar(string $msg, string $tipo = 'ok'): void
{
    iniciar_sessao();
    $_SESSION['aviso'] = [$msg, $tipo];
}

function ir(string $url): never
{
    header('Location: ' . $url);
    exit;
}
