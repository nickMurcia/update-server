<?php
// Copia este archivo a config.php y rellena los valores reales
define('UPDATE_ADMIN_USER', 'admin');
define('UPDATE_ADMIN_PASS', password_hash('cambia-esta-clave', PASSWORD_BCRYPT));
define('RELEASES_DIR',  __DIR__ . '/releases/');
define('CLIENTS_FILE',  __DIR__ . '/clients.json');
define('VERSION_FILE',  __DIR__ . '/version.json');
