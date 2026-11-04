<?php
// Lista pública das fotos visíveis da galeria.
declare(strict_types=1);
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');
try {
    $fotos = db()->query('SELECT titulo, servico, cidade, arquivo, miniatura FROM galeria WHERE publicado = 1 ORDER BY ordem, id LIMIT 60')->fetchAll();
    foreach ($fotos as &$f) {
        $f['arquivo'] = FA_UPLOADS_URL . '/' . $f['arquivo'];
        $f['miniatura'] = FA_UPLOADS_URL . '/' . $f['miniatura'];
    }
    echo json_encode(['fotos' => $fotos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo '{"fotos":[]}';
}
