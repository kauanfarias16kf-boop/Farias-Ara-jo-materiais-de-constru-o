<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(FA_DATA)) {
        @mkdir(FA_DATA, 0750, true);
    }
    $pdo = new PDO('sqlite:' . FA_DB, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 4000;');
    migrar($pdo);
    return $pdo;
}

function migrar(PDO $pdo): void
{
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v < 1) migrar_v1($pdo);
    if ($v < 2) migrar_v2($pdo);
    if ($v < 3) migrar_v3($pdo);
}

function migrar_v3(PDO $pdo): void
{
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS parcelamentos(
      id INTEGER PRIMARY KEY, funcionario_id INTEGER NOT NULL REFERENCES funcionarios(id) ON DELETE CASCADE,
      descricao TEXT NOT NULL, total REAL NOT NULL, obs TEXT,
      criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE TABLE IF NOT EXISTS parcelas(
      id INTEGER PRIMARY KEY, parcelamento_id INTEGER NOT NULL REFERENCES parcelamentos(id) ON DELETE CASCADE,
      numero INTEGER NOT NULL, valor REAL NOT NULL, status TEXT NOT NULL DEFAULT 'pendente',
      fechamento_id INTEGER REFERENCES fechamentos(id) ON DELETE SET NULL, descontada_em TEXT);
    ALTER TABLE fechamento_itens ADD COLUMN funcao TEXT;
    ALTER TABLE fechamento_itens ADD COLUMN descontos REAL NOT NULL DEFAULT 0;
    ALTER TABLE fechamento_itens ADD COLUMN detalhe TEXT;
    ALTER TABLE fechamentos ADD COLUMN obs TEXT;
    PRAGMA user_version = 3;
    SQL);
}

function migrar_v2(PDO $pdo): void
{
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS funcionarios(
      id INTEGER PRIMARY KEY, nome TEXT NOT NULL, funcao TEXT, whatsapp TEXT, pix TEXT,
      diaria REAL NOT NULL DEFAULT 0, ativo INTEGER NOT NULL DEFAULT 1, notas TEXT,
      criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE TABLE IF NOT EXISTS diarias(
      id INTEGER PRIMARY KEY, funcionario_id INTEGER NOT NULL REFERENCES funcionarios(id) ON DELETE CASCADE,
      data TEXT NOT NULL, obra_id INTEGER REFERENCES obras(id) ON DELETE SET NULL,
      fracao REAL NOT NULL DEFAULT 1, valor REAL NOT NULL DEFAULT 0, extra REAL NOT NULL DEFAULT 0, obs TEXT,
      UNIQUE(funcionario_id, data));
    CREATE INDEX IF NOT EXISTS idx_diarias_data ON diarias(data);
    CREATE TABLE IF NOT EXISTS vales(
      id INTEGER PRIMARY KEY, funcionario_id INTEGER NOT NULL REFERENCES funcionarios(id) ON DELETE CASCADE,
      data TEXT NOT NULL, valor REAL NOT NULL, obs TEXT);
    CREATE TABLE IF NOT EXISTS despesas(
      id INTEGER PRIMARY KEY, data TEXT NOT NULL, obra_id INTEGER REFERENCES obras(id) ON DELETE SET NULL,
      categoria TEXT NOT NULL, descricao TEXT, valor REAL NOT NULL, forma TEXT,
      criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE INDEX IF NOT EXISTS idx_despesas_data ON despesas(data);
    CREATE TABLE IF NOT EXISTS fechamentos(
      id INTEGER PRIMARY KEY, inicio TEXT NOT NULL, fim TEXT NOT NULL UNIQUE,
      criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE TABLE IF NOT EXISTS fechamento_itens(
      id INTEGER PRIMARY KEY, fechamento_id INTEGER NOT NULL REFERENCES fechamentos(id) ON DELETE CASCADE,
      funcionario_id INTEGER REFERENCES funcionarios(id) ON DELETE SET NULL, nome TEXT NOT NULL, pix TEXT,
      dias REAL NOT NULL, bruto REAL NOT NULL, extras REAL NOT NULL, vales REAL NOT NULL, liquido REAL NOT NULL,
      pago_em TEXT);
    PRAGMA user_version = 2;
    SQL);
}

function migrar_v1(PDO $pdo): void
{
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS usuarios(
      id INTEGER PRIMARY KEY, nome TEXT NOT NULL, login TEXT NOT NULL UNIQUE,
      senha_hash TEXT NOT NULL, criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE TABLE IF NOT EXISTS clientes(
      id INTEGER PRIMARY KEY, nome TEXT NOT NULL, whatsapp TEXT, email TEXT, cidade TEXT,
      endereco TEXT, documento TEXT, notas TEXT,
      criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE TABLE IF NOT EXISTS pedidos(
      id INTEGER PRIMARY KEY, nome TEXT NOT NULL, whatsapp TEXT NOT NULL, cidade TEXT, servico TEXT,
      metragem TEXT, prazo TEXT, mensagem TEXT, status TEXT NOT NULL DEFAULT 'novo', notas TEXT,
      cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL, ip TEXT,
      criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE TABLE IF NOT EXISTS obras(
      id INTEGER PRIMARY KEY, cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
      titulo TEXT NOT NULL, endereco TEXT, servico TEXT, status TEXT NOT NULL DEFAULT 'planejada',
      inicio TEXT, previsao TEXT, valor REAL DEFAULT 0, codigo TEXT NOT NULL UNIQUE, notas TEXT,
      criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE TABLE IF NOT EXISTS etapas(
      id INTEGER PRIMARY KEY, obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
      nome TEXT NOT NULL, ordem INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'pendente',
      previsao TEXT, valor REAL DEFAULT 0, concluida_em TEXT);
    CREATE TABLE IF NOT EXISTS atualizacoes(
      id INTEGER PRIMARY KEY, obra_id INTEGER NOT NULL REFERENCES obras(id) ON DELETE CASCADE,
      texto TEXT, foto TEXT, criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE TABLE IF NOT EXISTS orcamentos(
      id INTEGER PRIMARY KEY, numero TEXT NOT NULL UNIQUE,
      cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
      pedido_id INTEGER REFERENCES pedidos(id) ON DELETE SET NULL,
      cliente_nome TEXT NOT NULL, cliente_whatsapp TEXT, cidade TEXT, titulo TEXT NOT NULL,
      validade_dias INTEGER NOT NULL DEFAULT 15, prazo_execucao TEXT, condicoes TEXT, observacoes TEXT,
      desconto REAL NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'rascunho',
      criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE TABLE IF NOT EXISTS orcamento_itens(
      id INTEGER PRIMARY KEY, orcamento_id INTEGER NOT NULL REFERENCES orcamentos(id) ON DELETE CASCADE,
      descricao TEXT NOT NULL, unidade TEXT, quantidade REAL NOT NULL DEFAULT 1,
      valor_unit REAL NOT NULL DEFAULT 0, ordem INTEGER NOT NULL DEFAULT 0);
    CREATE TABLE IF NOT EXISTS galeria(
      id INTEGER PRIMARY KEY, titulo TEXT NOT NULL, servico TEXT, cidade TEXT, arquivo TEXT NOT NULL,
      miniatura TEXT NOT NULL, publicado INTEGER NOT NULL DEFAULT 1, ordem INTEGER NOT NULL DEFAULT 0,
      criado_em TEXT NOT NULL DEFAULT (datetime('now','-3 hours')));
    CREATE TABLE IF NOT EXISTS tentativas(chave TEXT NOT NULL, momento INTEGER NOT NULL);
    CREATE INDEX IF NOT EXISTS idx_tent ON tentativas(chave, momento);
    PRAGMA user_version = 1;
    SQL);
}

/** Limite simples de tentativas por chave (ip + ação). */
function limite_ok(string $chave, int $max, int $janela): bool
{
    $pdo = db();
    $agora = time();
    $pdo->prepare('DELETE FROM tentativas WHERE momento < ?')->execute([$agora - 86400]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM tentativas WHERE chave = ? AND momento > ?');
    $st->execute([$chave, $agora - $janela]);
    if ((int)$st->fetchColumn() >= $max) return false;
    $pdo->prepare('INSERT INTO tentativas(chave, momento) VALUES(?, ?)')->execute([$chave, $agora]);
    return true;
}

function ip_cliente(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0'), 0, 64);
}

function codigo_obra(): string
{
    $alfa = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $c = 'FA';
        for ($i = 0; $i < 4; $i++) $c .= $alfa[random_int(0, strlen($alfa) - 1)];
        $st = db()->prepare('SELECT 1 FROM obras WHERE codigo = ?');
        $st->execute([$c]);
    } while ($st->fetchColumn());
    return $c;
}

function numero_orcamento(): string
{
    $ano = date('Y');
    $st = db()->prepare("SELECT COUNT(*) FROM orcamentos WHERE numero LIKE ?");
    $st->execute([$ano . '-%']);
    $n = (int)$st->fetchColumn() + 1;
    do {
        $num = sprintf('%s-%03d', $ano, $n++);
        $chk = db()->prepare('SELECT 1 FROM orcamentos WHERE numero = ?');
        $chk->execute([$num]);
    } while ($chk->fetchColumn());
    return $num;
}

function so_digitos(?string $s): string
{
    return preg_replace('/\D+/', '', (string)$s);
}

function whats_link(?string $numero, string $texto = ''): string
{
    $n = so_digitos($numero);
    if ($n !== '' && strlen($n) <= 11) $n = '55' . $n;
    if ($n === '') $n = FA_WHATS_PRINCIPAL;
    return 'https://wa.me/' . $n . ($texto !== '' ? '?text=' . rawurlencode($texto) : '');
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function brl(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function num_br(string $s): float
{
    $s = trim($s);
    if ($s === '') return 0.0;
    if (str_contains($s, ',')) $s = str_replace(['.', ','], ['', '.'], $s);
    return (float)preg_replace('/[^0-9.\-]/', '', $s);
}

function data_br(?string $d): string
{
    if (!$d) return '';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : '';
}

/** Recebe uma imagem enviada, valida, redimensiona e salva em webp. Retorna [arquivo, miniatura] relativos a /uploads. */
function salvar_imagem(array $file, string $pasta): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('A foto não chegou. Tente de novo.');
    if ($file['size'] > 15 * 1024 * 1024) throw new RuntimeException('Foto grande demais. O limite é 15 MB.');
    $info = @getimagesize($file['tmp_name']);
    if (!$info) throw new RuntimeException('Esse arquivo não parece uma foto.');
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        default => false,
    };
    if (!$src) throw new RuntimeException('Use foto em JPG, PNG ou WEBP.');
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $o = $exif['Orientation'] ?? 1;
        if ($o == 3) $src = imagerotate($src, 180, 0);
        if ($o == 6) $src = imagerotate($src, -90, 0);
        if ($o == 8) $src = imagerotate($src, 90, 0);
    }
    $dir = FA_UPLOADS . '/' . $pasta;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $base = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $grande = redimensionar($src, 1920);
    $mini = redimensionar($src, 640);
    imagewebp($grande, "$dir/$base.webp", 82);
    imagewebp($mini, "$dir/$base-p.webp", 78);
    return ["$pasta/$base.webp", "$pasta/$base-p.webp"];
}

function redimensionar($src, int $max)
{
    $w = imagesx($src);
    $h = imagesy($src);
    $k = min(1, $max / max($w, $h));
    $nw = (int)round($w * $k);
    $nh = (int)round($h * $k);
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

function apagar_upload(?string $rel): void
{
    if (!$rel || str_contains($rel, '..')) return;
    $p = FA_UPLOADS . '/' . $rel;
    if (is_file($p)) @unlink($p);
}
