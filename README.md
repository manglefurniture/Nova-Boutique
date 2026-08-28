# Nova Boutique

E-commerce de reventa de ropa desarrollado por Hache Interactive.

## Alcance inicial

- catálogo público responsive;
- carrito por sesión;
- checkout como invitado, sin cuentas de cliente;
- panel administrativo para productos, pedidos, inventario, clientes y ventas;
- alta, edición, activación/desactivación y eliminación lógica de productos;
- producto muestra incluido;
- base de Mercado Pago con credenciales versionadas y cifradas siguiendo Hache-Base;
- reservas de inventario con TTL, liberación programada y conciliación segura de pagos tardíos;
- auditoría de mutaciones administrativas de productos y credenciales de pago;
- sitio marcado `noindex, nofollow` y `robots.txt` con `Disallow: /`;
- aviso de privacidad configurable sin versionar datos legales personales;
- health check, preflight, backup y rollback;
- CI con PHP y MariaDB.

## Baseline Hache-Base seleccionado

Nova aplica los módulos de Hache-Base que corresponden a su alcance: arquitectura separada, seguridad web, CSRF, autenticación admin con rate limit, configuración/secretos, zona horaria, migraciones e integridad MariaDB, auditoría, pagos/webhooks con credenciales históricas inmutables, inventario reservable con TTL, pruebas, CI, noindex para app no pública, privacidad, backup/rollback y health check.

No se incorporan multisede, múltiples roles, operaciones recurrentes, n8n, correo transaccional ni SEO público porque no forman parte del alcance actual. Si el negocio los necesita después se añaden como módulos, no como código muerto.

## Requisitos

PHP 8.2+ con `pdo_mysql`, `mbstring`, `openssl`, `curl`; MariaDB 11.x; Nginx apuntando `root` a `public/`.

## Instalación

1. Copia `.env.example` a `.env`.
2. Crea la base `nova_boutique` y aplica `database/001_initial.sql` y `database/002_seed_demo.sql`.
3. Genera el hash del password admin con `php -r "echo password_hash('CAMBIA_ESTA_CLAVE', PASSWORD_DEFAULT), PHP_EOL;"` y colócalo en `ADMIN_PASSWORD_HASH`.
4. Define una clave maestra estable en `PAYMENT_GATEWAY_CONFIG_KEY` antes de guardar credenciales.
5. Completa `PRIVACY_CONTROLLER_NAME`, `PRIVACY_ADDRESS` y `PRIVACY_CONTACT_EMAIL` antes de aceptar pedidos reales.
6. Configura Nginx con `deploy/nginx-nova.conf` como referencia.
7. Instala el scheduler de reservas descrito abajo.

## Producto muestra

La migración `002_seed_demo.sql` crea **Vestido Aurora** a **$699 MXN** con stock inicial de 4 piezas y foto de moda. Desde `/admin/productos.php` se puede cambiar nombre, precio, stock, descripción, foto, estado o eliminarlo.

## Mercado Pago

El panel incluye `/admin/pasarelas.php`. Cada cambio de Access Token/Webhook Secret crea una versión nueva e inmutable. Los secretos se cifran con AES-256-GCM, tag de 16 bytes y AAD contextual `provider + credential_ref + purpose`.

Mientras la pasarela no esté configurada y activa, el checkout permite crear el pedido pero lo deja en `pending_payment`. Cuando Mercado Pago está activo, el pedido queda ligado a la versión exacta de credenciales y se genera Checkout Pro.

La conciliación valida referencia local, versión histórica de credenciales, moneda e importe. `mp_payment_id` es único. Si un pago aprobado llega después de liberar su reserva, Nova intenta readquirir el inventario dentro de una transacción. Si ya no existe stock suficiente, conserva `estado_pago=approved` pero coloca el pedido en `payment_review` para intervención humana, sin vender inventario duplicado.

## Scheduler de reservas

Producción debe ejecutar `bin/release-expired-reservations.php` automáticamente. Se incluyen unidades systemd listas para el layout `/var/www/nova.hacheinteractive.com/app`:

```bash
sudo cp deploy/nova-release-reservations.service /etc/systemd/system/
sudo cp deploy/nova-release-reservations.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now nova-release-reservations.timer
systemctl status nova-release-reservations.timer --no-pager
```

El timer corre cada minuto y es persistente tras reinicios.

## Backup, rollback y smoke

Antes de modificar una producción existente:

```bash
export APP_ROOT=/var/www/nova.hacheinteractive.com/app
export BACKUP_ROOT=/var/backups/nova-boutique
# Exportar también DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME y DB_PASSWORD desde el entorno seguro.
bash deploy/preflight.sh
bash deploy/backup.sh
```

El backup registra commit, estado Git, dump MariaDB, snapshot local de `.env` con permisos restrictivos y checksums. `deploy/rollback.sh <commit>` revierte solo código; restaurar base de datos es una decisión separada.

Después del deploy:

```bash
export APP_URL=https://nova.hacheinteractive.com
bash deploy/health-check.sh
```

`/health.php` solo comprueba que la aplicación y MariaDB respondan y nunca expone secretos.

## Privacidad

`/privacidad.php` usa exclusivamente valores de `.env` para responsable, domicilio y canal de privacidad. Esos datos no se versionan. El enlace aparece desde el storefront.

## No indexación

Todas las respuestas públicas envían `X-Robots-Tag: noindex, nofollow`, las páginas incluyen meta robots y `robots.txt` bloquea todo rastreo.
