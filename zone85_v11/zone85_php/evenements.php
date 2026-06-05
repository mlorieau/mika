<?php
// evenements.php — V11 : le "Bonus du Moment" est desormais sur l'accueil
// Redirection vers index.php#bonus
header('Location: index.php#bonus', true, 302);
exit;