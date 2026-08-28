-- Nova Boutique - esquema inicial
-- MariaDB 11.x

CREATE TABLE IF NOT EXISTS productos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(180) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    precio DECIMAL(10,2) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    imagen_url VARCHAR(700) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    destacado TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    eliminado_en DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_productos_slug (slug),
    KEY idx_productos_publicos (activo, eliminado_en, destacado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_gateway_credentials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(40) NOT NULL,
    credential_ref VARCHAR(64) NOT NULL,
    environment VARCHAR(30) NOT NULL DEFAULT 'PRODUCTION',
    public_key VARCHAR(255) NULL,
    access_token_enc TEXT NOT NULL,
    webhook_secret_enc TEXT NOT NULL,
    account_id VARCHAR(120) NULL,
    account_label VARCHAR(190) NULL,
    created_by VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_gateway_credentials_provider_id (provider, id),
    UNIQUE KEY uq_payment_gateway_credentials_provider_ref (provider, credential_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TRIGGER IF NOT EXISTS trg_payment_gateway_credentials_immutable
BEFORE UPDATE ON payment_gateway_credentials
FOR EACH ROW
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Las versiones historicas de credenciales de pago son inmutables';

CREATE TABLE IF NOT EXISTS payment_gateway_config (
    provider VARCHAR(40) NOT NULL,
    configured TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 0,
    current_credential_id BIGINT UNSIGNED NULL,
    updated_by VARCHAR(120) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (provider),
    KEY idx_payment_gateway_config_current (provider, current_credential_id),
    CONSTRAINT fk_payment_gateway_config_credential
      FOREIGN KEY (provider, current_credential_id)
      REFERENCES payment_gateway_credentials(provider, id)
      ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payment_gateway_config (provider, configured, active, current_credential_id)
VALUES ('MERCADO_PAGO', 0, 0, NULL)
ON DUPLICATE KEY UPDATE provider = VALUES(provider);

CREATE TABLE IF NOT EXISTS pedidos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero_pedido VARCHAR(40) NOT NULL,
    cliente_nombre VARCHAR(180) NOT NULL,
    cliente_email VARCHAR(190) NOT NULL,
    cliente_telefono VARCHAR(40) NOT NULL,
    cliente_direccion VARCHAR(500) NULL,
    total DECIMAL(10,2) NOT NULL,
    estado ENUM('pending_payment','paid','payment_review','cancelled','completed') NOT NULL DEFAULT 'pending_payment',
    estado_pago VARCHAR(40) NOT NULL DEFAULT 'pending',
    payment_credential_id BIGINT UNSIGNED NULL,
    mp_preference_id VARCHAR(120) NULL,
    mp_payment_id VARCHAR(120) NULL,
    reserva_expira_en DATETIME NULL,
    reserva_liberada TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pedidos_numero (numero_pedido),
    UNIQUE KEY uq_pedidos_mp_payment_id (mp_payment_id),
    KEY idx_pedidos_email (cliente_email),
    KEY idx_pedidos_estado (estado, creado_en),
    KEY idx_pedidos_reserva (estado, reserva_liberada, reserva_expira_en),
    KEY idx_pedidos_credencial (payment_credential_id),
    CONSTRAINT fk_pedidos_payment_credential
      FOREIGN KEY (payment_credential_id)
      REFERENCES payment_gateway_credentials(id)
      ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    producto_id BIGINT UNSIGNED NULL,
    nombre_snapshot VARCHAR(180) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_items_pedido (pedido_id),
    CONSTRAINT fk_items_pedido
      FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
      ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_items_producto
      FOREIGN KEY (producto_id) REFERENCES productos(id)
      ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor VARCHAR(190) NOT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id VARCHAR(120) NULL,
    details_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
