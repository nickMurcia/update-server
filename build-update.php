<?php
// ── Generador de paquete de actualización ────────────────────────────────────
// Uso: php build-update.php [ruta-del-template]
// Ejemplo: php build-update.php ../cms-admin
// Genera: releases/vX.Y.Z.zip listo para subir al panel

$templateDir = isset($argv[1]) ? realpath($argv[1]) : realpath(__DIR__ . '/../cms-admin');
if (!$templateDir || !is_dir($templateDir)) {
    die("ERROR: Directorio template no encontrado: " . ($argv[1] ?? '../bernalabogados') . "\n");
}

require_once __DIR__ . '/config.php';

$version = json_decode(file_get_contents(VERSION_FILE), true);
$ver     = $version['version'] ?? '1.0.0';
$outFile = RELEASES_DIR . "v{$ver}.zip";

// Ficheros y directorios excluidos del paquete
$exclude = [
    'admin/config.php',       // credenciales de BD únicas por cliente
    'uploads',                // contenido del cliente
    '.git',                   // control de versiones
    '.env',                   // variables de entorno
    'node_modules',           // dependencias de build
    'articulos_seed.sql',     // datos de ejemplo
    'migration.sql',          // migraciones pendientes
    'SISTEMA.md',             // documentación interna
    'WORKFLOW-NUEVO-SITIO.md',
    '~',                      // ficheros temporales de Windows
];

$zip = new ZipArchive();
if ($zip->open($outFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("ERROR: No se puede crear el ZIP en {$outFile}\n");
}

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templateDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$count = 0;
foreach ($it as $file) {
    $real     = $file->getRealPath();
    $relative = ltrim(str_replace($templateDir, '', $real), DIRECTORY_SEPARATOR . '/');
    $relative = str_replace('\\', '/', $relative);

    // Saltar excluidos
    $skip = false;
    foreach ($exclude as $ex) {
        if (str_starts_with($relative, $ex)) { $skip = true; break; }
    }
    if ($skip) continue;

    $zip->addFile($real, $relative);
    $count++;
}

$zip->close();

$sizeMb = round(filesize($outFile) / 1024 / 1024, 2);
echo "✓ Generado: {$outFile}\n";
echo "  Versión:  {$ver}\n";
echo "  Ficheros: {$count}\n";
echo "  Tamaño:   {$sizeMb} MB\n";
echo "\nSube este archivo en el Panel → Publicar versión.\n";
