<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
iniciar_sessao();
$_SESSION = [];
session_destroy();
header('Location: login.php');
