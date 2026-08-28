<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/bootstrap.php';
require_once __DIR__.'/_layout.php';
$db=Database::connection();
$product=ProductRepository::findPublicBySlug($db,trim((string)($_GET['slug']??'')));
if(!$product){http_response_code(404);pageStart('Producto no encontrado');echo '<main class="wrap"><div class="panel"><h1>Esta pieza ya no está disponible.</h1><a class="btn" href="/">Volver a la colección</a></div></main>';pageEnd();exit;}
$images=ProductRepository::images($db,(int)$product['id']);
if($images===[]){$images=[['url'=>$product['imagen_url']?:'https://placehold.co/800x1000?text=Nova','alt_text'=>$product['nombre']]];}
$mainImage=$images[0];
pageStart((string)$product['nombre']);?>
<main class="wrap product-layout">
  <div class="product-gallery">
    <div class="product-photo"><img id="productGalleryMain" src="<?=Security::e((string)$mainImage['url'])?>" alt="<?=Security::e((string)($mainImage['alt_text']?:$product['nombre']))?>"></div>
    <?php if(count($images)>1): ?>
      <div class="product-thumbs" aria-label="Fotos del producto">
        <?php foreach($images as $index=>$image): ?>
          <button type="button" class="product-thumb<?=$index===0?' active':''?>" data-gallery-src="<?=Security::e((string)$image['url'])?>" data-gallery-alt="<?=Security::e((string)($image['alt_text']?:$product['nombre']))?>" aria-label="Ver foto <?=($index+1)?>">
            <img src="<?=Security::e((string)$image['url'])?>" alt="">
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="product-info"><span class="eyebrow">Pieza seleccionada</span><h1><?=Security::e($product['nombre'])?></h1><div class="price">$<?=number_format((float)$product['precio'],0)?> MXN</div><p class="muted"><?=nl2br(Security::e($product['descripcion']))?></p><p><strong><?=(int)$product['stock']?> disponible<?=(int)$product['stock']===1?'':'s'?></strong></p><form action="/carrito.php" method="post"><input type="hidden" name="csrf" value="<?=Security::e(Security::csrfToken())?>"><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?=(int)$product['id']?>"><label>Cantidad <input class="quantity" type="number" name="quantity" min="1" max="<?=(int)$product['stock']?>" value="1"></label> <button class="btn" type="submit" <?=(int)$product['stock']<=0?'disabled':''?>>Agregar a la bolsa</button></form></div>
</main>
<?php if(count($images)>1): ?>
<script>
document.querySelectorAll('[data-gallery-src]').forEach(function(button){button.addEventListener('click',function(){var main=document.getElementById('productGalleryMain');main.src=button.dataset.gallerySrc;main.alt=button.dataset.galleryAlt||'';document.querySelectorAll('.product-thumb').forEach(function(item){item.classList.remove('active')});button.classList.add('active')})});
</script>
<?php endif; pageEnd();?>
