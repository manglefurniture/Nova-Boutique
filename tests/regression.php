<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$must=['public/index.php'=>['meta name="robots"','ProductRepository::publicList'],'public/admin/productos.php'=>['Eliminar','Editar'],'public/admin/producto.php'=>['name="precio"','name="stock"','name="imagen_url"'],'database/002_seed_demo.sql'=>['Vestido Aurora','699.00'],'src/PaymentCredentialCipher.php'=>['GCM_TAG_LENGTH = 16','credential_ref']];
foreach($must as $file=>$needles){$content=file_get_contents($root.'/'.$file);if($content===false)throw new RuntimeException("No se pudo leer $file");foreach($needles as $needle){if(!str_contains($content,$needle))throw new RuntimeException("$file no contiene: $needle");}}
require_once $root.'/src/PaymentCredentialCipher.php';$cipher=new PaymentCredentialCipher('test-master-key');$payload=$cipher->encrypt('secret','MERCADO_PAGO','cred_test','access_token');if($cipher->decrypt($payload,'MERCADO_PAGO','cred_test','access_token')!=='secret')throw new RuntimeException('Falló round-trip.');
$rejected=false;try{$cipher->decrypt($payload,'MERCADO_PAGO','cred_test','webhook_secret');}catch(RuntimeException $e){$rejected=true;}if(!$rejected)throw new RuntimeException('El AAD cruzado no fue rechazado.');echo "Nova regressions: OK\n";
