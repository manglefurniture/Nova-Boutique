<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$product = null;
$images = [];
if (!empty($_GET['id'])) {
    $product = ProductRepository::findById($db, (int) $_GET['id']);
    if (!$product) {
        http_response_code(404);
        exit('Producto no encontrado');
    }
    $images = ProductRepository::images($db, (int) $product['id']);
}
adminStart($product ? 'Editar producto' : 'Nuevo producto');
?>
<form class="panel form-grid" method="post" action="/admin/guardar-producto.php" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= Security::e(Security::csrfToken()) ?>">
<input type="hidden" name="id" value="<?= (int) ($product['id'] ?? 0) ?>">
<input type="hidden" name="version" value="<?= (int) ($product['version'] ?? 0) ?>">
<div class="field"><label>Nombre</label><input name="nombre" required value="<?= Security::e($product['nombre'] ?? '') ?>"></div>
<div class="field"><label>Slug</label><input name="slug" value="<?= Security::e($product['slug'] ?? '') ?>" placeholder="se genera del nombre"></div>
<div class="field"><label>Precio MXN</label><input type="number" step="0.01" min="0" name="precio" required value="<?= Security::e((string) ($product['precio'] ?? '')) ?>"></div>
<div class="field"><label>Stock</label><input type="number" min="0" name="stock" required value="<?= Security::e((string) ($product['stock'] ?? '0')) ?>"></div>
<div class="field full"><label>Descripción</label><textarea name="descripcion"><?= Security::e($product['descripcion'] ?? '') ?></textarea></div>
<div class="field"><label><input type="checkbox" name="activo" value="1" <?= !isset($product['activo']) || (int) $product['activo'] ? 'checked' : '' ?>> Publicado</label></div>
<div class="field"><label><input type="checkbox" name="destacado" value="1" <?= !empty($product['destacado']) ? 'checked' : '' ?>> Destacado</label></div>

<div class="field full product-gallery-admin">
  <div>
    <strong>Fotos del producto</strong>
    <p class="muted">Puedes guardar hasta 8 fotos. La marcada como principal es la que aparece en el catálogo.</p>
  </div>
  <?php if ($images !== []): ?>
    <div class="admin-image-grid">
      <?php foreach ($images as $index => $image): ?>
        <article class="admin-image-card">
          <img src="<?= Security::e((string) $image['url']) ?>" alt="<?= Security::e((string) ($image['alt_text'] ?? $product['nombre'] ?? 'Producto')) ?>">
          <div class="admin-image-controls">
            <label><input type="radio" name="imagen_principal" value="<?= (int) $image['id'] ?>" <?= $index === 0 ? 'checked' : '' ?>> Principal</label>
            <label><input type="checkbox" name="eliminar_imagen[]" value="<?= (int) $image['id'] ?>"> Eliminar</label>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="notice">Este producto todavía no tiene fotos guardadas.</div>
  <?php endif; ?>

  <label class="upload-box">
    <strong>Agregar fotos</strong>
    <span>JPG, PNG o WebP · máximo 8 MB por imagen · hasta 6 por carga</span>
    <input type="file" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple>
  </label>
</div>

<div class="field full"><p class="muted">Si cambia el inventario mientras tienes esta ficha abierta, Nova rechazará el guardado y te pedirá recargar para evitar sobrescribir una reserva o venta.</p></div>
<div class="field full"><button class="btn" type="submit">Guardar producto</button></div>
</form>
<?php adminEnd(); ?>
