# Sistema CMS — Guía completa de despliegue y uso

## Los tres repos

| Repo | Propósito |
|------|-----------|
| `nickMurcia/update-server` | Servidor de actualizaciones — se despliega una vez en `updates.tuconsultor.digital` |
| `nickMurcia/cms-admin` | Panel CMS genérico — se instala en cada cliente nuevo |
| `nickMurcia/bernalberberena` | Ejemplo de proyecto cliente completo |

---

## 1. Desplegar el servidor de actualizaciones

**Solo se hace una vez.** URL final: `https://updates.tuconsultor.digital`

### Pasos

1. Crear el subdominio `updates.tuconsultor.digital` en el panel del hosting
2. Subir todos los archivos del repo `update-server` al hosting (FTP o Git)
3. Crear `config.php` a partir de `config.example.php`:

```php
<?php
define('UPDATE_ADMIN_USER', 'admin');
define('UPDATE_ADMIN_PASS', password_hash('TU-CONTRASEÑA-SEGURA', PASSWORD_BCRYPT));
define('RELEASES_DIR',  __DIR__ . '/releases/');
define('CLIENTS_FILE',  __DIR__ . '/clients.json');
define('VERSION_FILE',  __DIR__ . '/version.json');
```

4. Crear `clients.json` vacío:
```json
[]
```

5. Verificar que la carpeta `releases/` tiene permisos de escritura (755)

### Verificar que funciona

```
https://updates.tuconsultor.digital/check.php
→ Debe devolver JSON con la versión 1.0.0

https://updates.tuconsultor.digital/admin/
→ Debe mostrar el login del panel
```

---

## 2. Instalar el CMS en un cliente nuevo

1. **Clonar cms-admin** en Laragon local:
```bash
git clone https://github.com/nickMurcia/cms-admin.git nombre-cliente
```

2. **Crear la BD** en el hosting del cliente (cPanel / Plesk):
   - Nueva base de datos MySQL
   - Usuario con permisos completos sobre esa BD

3. **Subir archivos al hosting** del cliente (sin `node_modules/`, sin `src/`)

4. **Ejecutar el instalador** desde el navegador:
```
https://dominio-cliente.com/admin/install.php
```
El wizard pide en 2 pasos: credenciales de BD → usuario admin. Escribe `config.php` automáticamente.

5. **Eliminar `install.php`** del servidor tras la instalación

6. **Activar las actualizaciones** — añadir en `admin/config.php` del cliente:
```php
define('UPDATE_SERVER_URL', 'https://updates.tuconsultor.digital');
```

7. **Crear repo del cliente** en GitHub y hacer push

---

## 3. Desarrollar una nueva funcionalidad

**Regla:** siempre se desarrolla en `cms-admin`, nunca directamente en el repo de un cliente.

1. Desarrollar y probar en `cms-admin` en Laragon local
2. Subir la versión en `cms-admin/version.php`:
```php
return '1.1.0'; // incrementar según cambio: mayor.menor.parche
```
3. Commit y push a GitHub:
```bash
git commit -m "feat: nueva funcionalidad"
git push
```

---

## 4. Publicar la actualización para todos los clientes

1. **Generar el paquete ZIP** desde la carpeta `update-server`:
```bash
php build-update.php ../cms-admin
# Genera: releases/v1.1.0.zip
# Excluye automáticamente: admin/config.php, uploads/, .git, node_modules/
```

2. **Publicar en el panel** — entrar en `https://updates.tuconsultor.digital/admin/`:
   - Número de versión: `1.1.0`
   - Subir el ZIP generado
   - Escribir el changelog
   - Clic en "Publicar versión"

3. **Los clientes ven la actualización** automáticamente en su panel:
   - Admin del cliente → Actualizaciones → "Instalar v1.1.0"
   - Se descarga el ZIP, se extrae y se copian los archivos
   - `admin/config.php` y `uploads/` nunca se tocan

---

## 5. Estructura de archivos por cliente

```
hosting-cliente/
  admin/
    src/          ← Modelos PHP (Post, Lead, Setting, TeamMember...)
    views/        ← Vistas del panel
    api/          ← Endpoints JSON públicos (GET)
    config.php    ← Credenciales BD del cliente (NO va a git)
    index.php
    setup.sql
  vendor/         ← PHPMailer (incluido)
  uploads/
    posts/        ← Imágenes subidas por el cliente
    .htaccess
  [plantillas PHP del cliente]
    index.php, contacto.php, areas/, equipo.php...
  css/style.css   ← CSS compilado del cliente
  .htaccess
```

---

## 6. API JSON pública (para frontends Vue o PHP)

Cada cliente expone estos endpoints de solo lectura:

| Endpoint | Descripción |
|----------|-------------|
| `GET /admin/api/posts` | Artículos publicados (paginado con `?page=1&per_page=10`) |
| `GET /admin/api/posts/{slug}` | Artículo individual |
| `GET /admin/api/settings` | Config pública del sitio (teléfono, redes, etc.) |
| `GET /admin/api/team` | Miembros del equipo |

---

## 7. Vue progresivo en el frontend

Vue se usa como componentes aislados en páginas PHP — no como SPA (para mantener el SEO perfecto).

- El frontend PHP renderiza el HTML completo (SEO ✅)
- Vue se carga vía CDN solo en las páginas que lo necesiten
- El formulario de contacto ya está convertido a Vue 3 (Composition API) en `bernalberberena` como ejemplo

Para añadir Vue a una página:
```php
$headExtra = '<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>';
```

---

## 8. Versioning (cuándo subir cada número)

| Tipo de cambio | Ejemplo | Versión |
|----------------|---------|---------|
| Parche — fix de bug | Corregir un error de visualización | `1.0.0 → 1.0.1` |
| Menor — nueva función | Añadir CRM de leads | `1.0.0 → 1.1.0` |
| Mayor — cambio estructural | Rediseño del panel | `1.0.0 → 2.0.0` |

---

## Pendiente

- [ ] Crear subdominio `updates.tuconsultor.digital`
- [ ] Desplegar `update-server` en ese subdominio
- [ ] Añadir `UPDATE_SERVER_URL` en `admin/config.php` de bernalberberena
- [ ] Activar el sistema de actualizaciones con la versión 1.0.0
