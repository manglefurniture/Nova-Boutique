-- Galería de imágenes por producto para Nova Boutique
-- MariaDB 11.x

CREATE TABLE IF NOT EXISTS producto_imagenes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    producto_id BIGINT UNSIGNED NOT NULL,
    url VARCHAR(700) NOT NULL,
    alt_text VARCHAR(220) NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_producto_imagenes_orden (producto_id, orden),
    KEY idx_producto_imagenes_producto (producto_id),
    CONSTRAINT fk_producto_imagenes_producto
      FOREIGN KEY (producto_id) REFERENCES productos(id)
      ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conserva la imagen principal existente (incluido el producto demo) al migrar
-- instalaciones que nacieron antes de la galería.
INSERT INTO producto_imagenes (producto_id, url, alt_text, orden)
SELECT p.id, p.imagen_url, p.nombre, 1
FROM productos p
WHERE p.imagen_url IS NOT NULL
  AND p.imagen_url <> ''
  AND NOT EXISTS (
      SELECT 1 FROM producto_imagenes pi WHERE pi.producto_id = p.id
  );
