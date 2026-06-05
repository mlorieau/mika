<?php
// Redirigé vers le hub communautaire (onglet Classement)
$clan = in_array($_GET['clan'] ?? '', ['bocage','littoral','marais'], true) ? '&clan='.$_GET['clan'] : '';
header('Location: communaute.php?tab=classement'.$clan, true, 301);
exit;
