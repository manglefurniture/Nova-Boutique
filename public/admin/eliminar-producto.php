<?php
declare(strict_types=1);require_once __DIR__.'/_bootstrap.php';if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}Security::verifyCsrf($_POST['csrf']??null);ProductRepository::softDelete($db,(int)($_POST['id']??0));header('Location: /admin/productos.php');exit;
