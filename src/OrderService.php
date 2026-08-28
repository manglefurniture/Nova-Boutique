<?php
declare(strict_types=1);

final class OrderService
{
    public static function create(PDO $db,array $customer,array $cartItems):array
    {
        if($cartItems===[]) throw new InvalidArgumentException('El carrito está vacío.');
        $name=trim((string)($customer['nombre']??'')); $email=trim((string)($customer['email']??'')); $phone=trim((string)($customer['telefono']??'')); $address=trim((string)($customer['direccion']??''));
        if($name===''||$phone===''||$email===''||filter_var($email,FILTER_VALIDATE_EMAIL)===false) throw new InvalidArgumentException('Nombre, teléfono y correo válido son obligatorios.');
        $db->beginTransaction();
        try{
            $config=PaymentGatewayConfig::current($db,true); $number='NOVA-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3))); $total=0.0; $locked=[];
            $select=$db->prepare('SELECT id,nombre,precio,stock FROM productos WHERE id=? AND activo=1 AND eliminado_en IS NULL FOR UPDATE');
            foreach($cartItems as $cartItem){$select->execute([(int)$cartItem['id']]);$product=$select->fetch();if(!$product)throw new RuntimeException('Uno de los productos ya no está disponible.');$qty=(int)$cartItem['cantidad'];if($qty<=0||(int)$product['stock']<$qty)throw new RuntimeException('El stock cambió. Revisa tu carrito.');$product['cantidad']=$qty;$product['subtotal']=$qty*(float)$product['precio'];$total+=(float)$product['subtotal'];$locked[]=$product;}
            $insert=$db->prepare("INSERT INTO pedidos (numero_pedido,cliente_nombre,cliente_email,cliente_telefono,cliente_direccion,total,estado,estado_pago,payment_credential_id) VALUES (?,?,?,?,?,?,'pending_payment','pending',?)");
            $insert->execute([$number,$name,$email,$phone,$address!==''?$address:null,$total,$config&&$config['active']?(int)$config['credential_id']:null]); $orderId=(int)$db->lastInsertId();
            $itemStmt=$db->prepare('INSERT INTO pedido_items (pedido_id,producto_id,nombre_snapshot,precio_unitario,cantidad,subtotal) VALUES (?,?,?,?,?,?)'); $stockStmt=$db->prepare('UPDATE productos SET stock=stock-?,actualizado_en=CURRENT_TIMESTAMP WHERE id=?');
            foreach($locked as $item){$itemStmt->execute([$orderId,(int)$item['id'],(string)$item['nombre'],(float)$item['precio'],(int)$item['cantidad'],(float)$item['subtotal']]);$stockStmt->execute([(int)$item['cantidad'],(int)$item['id']]);}
            $db->commit(); return ['id'=>$orderId,'numero_pedido'=>$number,'cliente_nombre'=>$name,'cliente_email'=>$email,'total'=>$total,'payment_config'=>$config,'items'=>$locked];
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function attachPreference(PDO $db,int $orderId,string $preferenceId):void{$stmt=$db->prepare('UPDATE pedidos SET mp_preference_id=? WHERE id=?');$stmt->execute([$preferenceId,$orderId]);}
    public static function findByNumber(PDO $db,string $number):?array{$stmt=$db->prepare('SELECT * FROM pedidos WHERE numero_pedido=? LIMIT 1');$stmt->execute([$number]);return $stmt->fetch()?:null;}
    public static function markPayment(PDO $db,int $orderId,string $paymentId,string $status):void{$paid=$status==='approved';$stmt=$db->prepare("UPDATE pedidos SET mp_payment_id=?,estado_pago=?,estado=IF(?,'paid',estado),actualizado_en=CURRENT_TIMESTAMP WHERE id=?");$stmt->execute([$paymentId,$status,$paid?1:0,$orderId]);}
}
