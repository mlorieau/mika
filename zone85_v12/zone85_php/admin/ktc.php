<?php
// admin/ktc.php — Redirige vers ktc-episodes.php (point d'entrée V12)
require_once '../includes/config.php';
$base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
header('Location: ' . $base . '/admin/ktc-episodes.php');
exit;