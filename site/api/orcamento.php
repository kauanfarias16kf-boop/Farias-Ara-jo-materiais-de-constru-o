<?php
// Recebe o pedido de orçamento do site e grava no painel.
declare(strict_types=1);
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $code, array $dados): never
{
    http_response_code($code);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok' => false, 'erro' => 'Use o formulário do site.']);

$in = $_POST;
if (!$in && str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'json')) {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
}
$campo = fn(string $k, int $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);

// armadilha para robôs: campo invisível que pessoas não preenchem
if ($campo('empresa', 50) !== '') responder(200, ['ok' => true]);

$nome = $campo('nome', 120);
$whats = $campo('whatsapp', 30);
$cidade = $campo('cidade', 120);
$servico = $campo('servico', 80);
$metragem = $campo('metragem', 20);
$prazo = $campo('prazo', 60);
$mensagem = $campo('mensagem', 2000);

$erros = [];
if (mb_strlen($nome) < 2) $erros['nome'] = 'Escreva seu nome.';
$dig = so_digitos($whats);
if (strlen($dig) < 10 || strlen($dig) > 13) $erros['whatsapp'] = 'Confira o número com DDD, por exemplo 61 9 0000-0000.';
if (!in_array($servico, FA_SERVICOS, true)) $servico = 'Outro';
if ($erros) responder(422, ['ok' => false, 'erros' => $erros]);

try {
    if (!limite_ok('pedido:' . ip_cliente(), 6, 3600)) {
        responder(429, ['ok' => false, 'erro' => 'Recebemos vários pedidos seguidos daqui. Chame direto no WhatsApp que a gente responde.']);
    }
    db()->prepare('INSERT INTO pedidos(nome, whatsapp, cidade, servico, metragem, prazo, mensagem, ip) VALUES(?,?,?,?,?,?,?,?)')
        ->execute([$nome, $whats, $cidade, $servico, $metragem, $prazo, $mensagem, ip_cliente()]);
} catch (Throwable $e) {
    error_log('FA pedido: ' . $e->getMessage());
    responder(500, ['ok' => false, 'erro' => 'O sistema não salvou agora. Mande pelo WhatsApp que chega do mesmo jeito.']);
}

responder(200, ['ok' => true]);
