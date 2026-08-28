<?php
declare(strict_types=1);require_once dirname(__DIR__,2).'/config/bootstrap.php';header('Content-Type: application/json');/* Endpoint reservado para Webhook firmado. La validación x-signature y conciliación histórica se completa antes de activar pagos reales. */http_response_code(200);echo '{"ok":true}';
