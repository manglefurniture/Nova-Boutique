<?php
declare(strict_types=1);
function pageStart(string $title): void
{
    $cartCount = Cart::count(); ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title><?= Security::e($title) ?> · Nova Boutique</title><link rel="stylesheet" href="/assets/app.css"></head><body><div class="topbar">PIEZAS CON HISTORIA · SELECCIÓN CURADA</div><header class="wrap nav"><a class="brand" href="/">NOVA</a><nav class="navlinks"><a href="/#coleccion">Colección</a><a href="/carrito.php" class="pill">Bolsa (<?= $cartCount ?>)</a></nav></header><?php
}
function pageEnd(): void
{ ?>
<footer class="footer"><div class="wrap">Nova Boutique · Proyecto Hache Interactive · Compra responsable, estilo circular. · <a href="/privacidad.php">Privacidad</a></div></footer></body></html><?php
}
