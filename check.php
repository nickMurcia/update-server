<?php
// Endpoint público — los clientes consultan aquí si hay actualización disponible
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

$version = json_decode(file_get_contents(VERSION_FILE), true);

// Registrar el check-in del cliente
$domain  = preg_replace('/[^a-z0-9\.\-]/i', '', $_GET['domain'] ?? '');
$current = preg_replace('/[^0-9\.]/', '', $_GET['version'] ?? '0.0.0');

if ($domain) {
    $clients = json_decode(file_get_contents(CLIENTS_FILE), true) ?: [];
    $found   = false;
    foreach ($clients as &$c) {
        if ($c['domain'] === $domain) {
            $c['version']   = $current;
            $c['last_seen'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    if (!$found) {
        $clients[] = [
            'domain'     => $domain,
            'version'    => $current,
            'registered' => date('Y-m-d H:i:s'),
            'last_seen'  => date('Y-m-d H:i:s'),
        ];
    }
    file_put_contents(CLIENTS_FILE, json_encode($clients, JSON_PRETTY_PRINT));
}

echo json_encode([
    'version'      => $version['version'],
    'released_at'  => $version['released_at'],
    'changelog'    => $version['changelog'],
    'download_url' => $version['download_url'],
    'has_update'   => version_compare($version['version'], $current, '>'),
]);
