<?php
// Redirigé vers le hub communautaire (onglet Zonautes)
$params = [];
$clan = $_GET['clan'] ?? '';
$sort = $_GET['sort'] ?? '';
$off  = (int)($_GET['offset'] ?? 0);
if (in_array($clan, ['bocage','littoral','marais'], true)) $params[] = 'clan='.$clan;
if ($sort === 'season') $params[] = 'sort=season';
if ($off > 0) $params[] = 'offset='.$off;
$qs = $params ? '&'.implode('&', $params) : '';
header('Location: communaute.php?tab=zonautes'.$qs, true, 301);
exit;
