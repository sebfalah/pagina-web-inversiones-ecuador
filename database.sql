CREATE TABLE IF NOT EXISTS prospectos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  correo VARCHAR(190) NOT NULL,
  edad TINYINT UNSIGNED NOT NULL,
  riesgo VARCHAR(20) NOT NULL,
  objetivos JSON NOT NULL,
  capital DECIMAL(12,2) NOT NULL,
  cuenta_usa TINYINT(1) NOT NULL,
  mensaje TEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_correo (correo),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
