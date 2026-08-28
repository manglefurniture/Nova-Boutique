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
- sitio marcado `noindex, nofollow` y `robots.txt` con `Disallow: /`;
- CI con PHP y MariaDB.

## Requisitos

PHP 8.2+ con `pdo_mysql`, `mbstring`, `openssl`, `curl`; MariaDB 11.x; Nginx apuntando `root` a `public/`.

## Instalación

1. Copia `.env.example` a `.env`.
2. Crea la base `nova_boutique` y aplica `database/001_initial.sql` y `database/002_seed_demo.sql`.
3. Genera el hash del password admin con `php -r "echo password_hash('CAMBIA_ESTA_CLAVE', PASSWORD_DEFAULT), PHP_EOL;"` y colócalo en `ADMIN_PASSWORD_HASH`.
4. Define una clave maestra estable en `PAYMENT_GATEWAY_CONFIG_KEY` antes de guardar credenciales.
5. Configura Nginx con `deploy/nginx-nova.conf` como referencia.

## Producto muestra

La migración `002_seed_demo.sql` crea **Vestido Aurora** a **$699 MXN** con stock inicial de 4 piezas y foto de moda. Desde `/admin/productos.php` se puede cambiar nombre, precio, stock, descripción, foto, estado o eliminarlo.

## Mercado Pago

El panel incluye `/admin/pasarelas.php`. Cada cambio de Access Token/Webhook Secret crea una versión nueva e inmutable. Los secretos se cifran con AES-256-GCM, tag de 16 bytes y AAD contextual `provider + credential_ref + purpose`.

Mientras la pasarela no esté configurada y activa, el checkout permite crear el pedido pero lo deja en `pending_payment`. Cuando Mercado Pago está activo, el pedido queda ligado a la versión exacta de credenciales y se genera Checkout Pro.

## No indexación

Todas las respuestas públicas envían `X-Robots-Tag: noindex, nofollow`, las páginas incluyen meta robots y `robots.txt` bloquea todo rastreo.
