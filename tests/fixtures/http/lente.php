<?php

// Serveur de test (php -S) : répond 300 ms après la requête, comme un service qui calcule (ChronometreTest).
usleep(300000);
header('Content-Type: application/json');
echo '{"ok":true}';
