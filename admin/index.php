<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

// ── Auth ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['user'] === UPDATE_ADMIN_USER && password_verify($_POST['pass'], UPDATE_ADMIN_PASS)) {
        $_SESSION['update_admin'] = true;
        header('Location: index.php'); exit;
    }
    $loginError = 'Credenciales incorrectas.';
}
if (isset($_POST['logout'])) { session_destroy(); header('Location: index.php'); exit; }
$auth = !empty($_SESSION['update_admin']);

// ── Acciones ─────────────────────────────────────────────────────────────────
$msg = '';

if ($auth && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Publicar nueva versión
    if (isset($_POST['publish_version'])) {
        $v  = trim($_POST['version'] ?? '');
        $cl = trim($_POST['changelog'] ?? '');
        if (preg_match('/^\d+\.\d+\.\d+$/', $v) && $cl) {
            $info = json_decode(file_get_contents(VERSION_FILE), true);

            // Subir ZIP si se adjunta
            if (!empty($_FILES['zipfile']['name'])) {
                $dest = RELEASES_DIR . "v{$v}.zip";
                move_uploaded_file($_FILES['zipfile']['tmp_name'], $dest);
                $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
                $base = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI'], 2);
                $info['download_url'] = $base . "/releases/v{$v}.zip";
            }

            $info['version']     = $v;
            $info['released_at'] = date('Y-m-d');
            $info['changelog']   = $cl;
            file_put_contents(VERSION_FILE, json_encode($info, JSON_PRETTY_PRINT));
            $msg = "✓ Versión {$v} publicada correctamente.";
        } else {
            $msg = '✗ Formato de versión inválido (usa X.Y.Z) o changelog vacío.';
        }
    }

    // Eliminar cliente
    if (isset($_POST['delete_client'])) {
        $domain  = $_POST['delete_client'];
        $clients = json_decode(file_get_contents(CLIENTS_FILE), true) ?: [];
        $clients = array_values(array_filter($clients, fn($c) => $c['domain'] !== $domain));
        file_put_contents(CLIENTS_FILE, json_encode($clients, JSON_PRETTY_PRINT));
        $msg = "✓ Cliente {$domain} eliminado.";
    }
}

// ── Datos ─────────────────────────────────────────────────────────────────────
$version = $auth ? json_decode(file_get_contents(VERSION_FILE), true) : [];
$clients = $auth ? (json_decode(file_get_contents(CLIENTS_FILE), true) ?: []) : [];
usort($clients, fn($a, $b) => strcmp($b['last_seen'] ?? '', $a['last_seen'] ?? ''));

$outdated  = count(array_filter($clients, fn($c) => version_compare($version['version'], $c['version'] ?? '0', '>')));
$uptodate  = count($clients) - $outdated;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Panel de Actualizaciones</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f1f5f9;color:#1e293b;min-height:100vh}
    .sidebar{width:220px;background:#0f172a;position:fixed;top:0;bottom:0;left:0;display:flex;flex-direction:column;padding:1.5rem 1rem}
    .sidebar h2{color:white;font-size:.85rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid rgba(255,255,255,.1)}
    .sidebar nav a{display:block;color:rgba(255,255,255,.6);text-decoration:none;font-size:.8rem;padding:.45rem .75rem;border-radius:5px;margin-bottom:.15rem;transition:background .15s}
    .sidebar nav a:hover,.sidebar nav a.active{background:rgba(255,255,255,.12);color:white}
    .sidebar .version-badge{margin-top:auto;background:rgba(255,255,255,.08);border-radius:6px;padding:.6rem .75rem;font-size:.7rem;color:rgba(255,255,255,.5)}
    .main{margin-left:220px;padding:2rem}
    .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem}
    .topbar h1{font-size:1.1rem;font-weight:700;color:#0f172a}
    .btn{padding:.4rem 1rem;border:none;border-radius:5px;font-size:.78rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:opacity .15s}
    .btn:hover{opacity:.85}
    .btn-primary{background:#0ea5e9;color:white}
    .btn-danger{background:#ef4444;color:white}
    .btn-secondary{background:white;border:1px solid #e2e8f0;color:#475569}
    .card{background:white;border:1px solid #e2e8f0;border-radius:10px;padding:1.5rem;margin-bottom:1.25rem}
    .card h3{font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:.75rem;font-weight:600}
    .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
    .stat{background:white;border:1px solid #e2e8f0;border-radius:10px;padding:1.25rem}
    .stat-label{font-size:.65rem;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:.35rem;font-weight:600}
    .stat-value{font-size:1.8rem;font-weight:800;line-height:1}
    table{width:100%;border-collapse:collapse}
    th,td{padding:.65rem .85rem;text-align:left;font-size:.78rem}
    th{background:#f8fafc;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0}
    td{border-bottom:1px solid #f1f5f9;color:#1e293b}
    tr:last-child td{border:none}
    .badge{display:inline-block;font-size:.65rem;padding:.2rem .6rem;border-radius:999px;font-weight:600}
    .badge-ok{background:#dcfce7;color:#16a34a}
    .badge-outdated{background:#fef3c7;color:#d97706}
    .badge-unknown{background:#f1f5f9;color:#94a3b8}
    input,textarea,select{width:100%;border:1px solid #e2e8f0;border-radius:6px;padding:.55rem .75rem;font-size:.85rem;outline:none;transition:border-color .15s}
    input:focus,textarea:focus{border-color:#0ea5e9}
    label{display:block;font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    .msg-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;padding:.6rem 1rem;border-radius:6px;font-size:.82rem;margin-bottom:1rem}
    .msg-err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:.6rem 1rem;border-radius:6px;font-size:.82rem;margin-bottom:1rem}
    .login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;background:#0f172a}
    .login-box{background:white;border-radius:10px;padding:2.5rem;width:360px;box-shadow:0 20px 50px rgba(0,0,0,.3)}
    .login-box h1{font-size:1rem;font-weight:700;margin-bottom:1.5rem;color:#0f172a}
  </style>
</head>
<body>

<?php if (!$auth): ?>
<!-- LOGIN -->
<div class="login-wrap">
  <div class="login-box">
    <h1>🔄 Panel de Actualizaciones</h1>
    <?php if (!empty($loginError)): ?>
      <div class="msg-err"><?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div style="margin-bottom:1rem">
        <label>Usuario</label>
        <input type="text" name="user" required autofocus>
      </div>
      <div style="margin-bottom:1.5rem">
        <label>Contraseña</label>
        <input type="password" name="pass" required>
      </div>
      <button type="submit" name="login" class="btn btn-primary" style="width:100%;padding:.6rem">Entrar</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- SIDEBAR -->
<div class="sidebar">
  <h2>🔄 Updates Panel</h2>
  <nav>
    <a href="index.php" class="active">📊 Dashboard</a>
    <a href="index.php#publish">⬆️ Publicar versión</a>
    <a href="index.php#clients">👥 Clientes</a>
  </nav>
  <div class="version-badge">
    Versión actual<br>
    <strong style="color:white"><?= htmlspecialchars($version['version'] ?? '-') ?></strong>
    <span style="display:block;margin-top:.25rem"><?= htmlspecialchars($version['released_at'] ?? '') ?></span>
  </div>
</div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <h1>Panel de actualizaciones</h1>
    <form method="POST" style="display:inline">
      <button type="submit" name="logout" class="btn btn-secondary">Cerrar sesión</button>
    </form>
  </div>

  <?php if ($msg): ?>
    <div class="<?= str_starts_with($msg, '✓') ? 'msg-ok' : 'msg-err' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats">
    <div class="stat">
      <div class="stat-label">Versión publicada</div>
      <div class="stat-value" style="color:#0ea5e9;font-size:1.3rem"><?= htmlspecialchars($version['version']) ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Clientes registrados</div>
      <div class="stat-value" style="color:#0f172a"><?= count($clients) ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Al día</div>
      <div class="stat-value" style="color:#16a34a"><?= $uptodate ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Con actualización pendiente</div>
      <div class="stat-value" style="color:<?= $outdated > 0 ? '#d97706' : '#16a34a' ?>"><?= $outdated ?></div>
    </div>
  </div>

  <!-- Publicar nueva versión -->
  <div class="card" id="publish">
    <h3>⬆️ Publicar nueva versión</h3>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-grid" style="margin-bottom:1rem">
        <div>
          <label>Número de versión (X.Y.Z)</label>
          <input type="text" name="version" placeholder="1.1.0" pattern="\d+\.\d+\.\d+" required>
        </div>
        <div>
          <label>ZIP de la actualización</label>
          <input type="file" name="zipfile" accept=".zip">
          <span style="font-size:.68rem;color:#94a3b8;display:block;margin-top:.2rem">Sube el archivo latest.zip generado con build-update.php</span>
        </div>
      </div>
      <div style="margin-bottom:1rem">
        <label>Changelog (qué incluye esta versión)</label>
        <textarea name="changelog" rows="3" required placeholder="- Mejora X&#10;- Fix Y&#10;- Nueva función Z"><?= htmlspecialchars($version['changelog'] ?? '') ?></textarea>
      </div>
      <button type="submit" name="publish_version" class="btn btn-primary">Publicar versión</button>
    </form>
  </div>

  <!-- Tabla de clientes -->
  <div class="card" id="clients">
    <h3>👥 Clientes registrados (<?= count($clients) ?>)</h3>
    <?php if (empty($clients)): ?>
      <p style="font-size:.82rem;color:#94a3b8">Ningún cliente ha hecho check-in todavía. Los sitios aparecerán aquí automáticamente cuando comprueben actualizaciones.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Dominio</th>
            <th>Versión instalada</th>
            <th>Estado</th>
            <th>Último check-in</th>
            <th>Registrado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clients as $c):
            $cv     = $c['version'] ?? '0.0.0';
            $latest = $version['version'] ?? '0.0.0';
            $ok     = version_compare($cv, $latest, '>=');
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($c['domain']) ?></strong></td>
              <td style="font-family:monospace"><?= htmlspecialchars($cv) ?></td>
              <td>
                <span class="badge <?= $ok ? 'badge-ok' : 'badge-outdated' ?>">
                  <?= $ok ? 'Al día' : 'Pendiente ' . $latest ?>
                </span>
              </td>
              <td style="color:#64748b"><?= htmlspecialchars($c['last_seen'] ?? '-') ?></td>
              <td style="color:#64748b"><?= htmlspecialchars($c['registered'] ?? '-') ?></td>
              <td>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar cliente?')">
                  <input type="hidden" name="delete_client" value="<?= htmlspecialchars($c['domain']) ?>">
                  <button type="submit" class="btn btn-danger" style="padding:.25rem .6rem;font-size:.68rem">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>
<?php endif; ?>
</body>
</html>
