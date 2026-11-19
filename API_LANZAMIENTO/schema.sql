CREATE DATABASE IF NOT EXISTS api_boletas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE api_boletas;

-- es_admin = 1: el único rol con acceso a TODAS las subredes y a ingresos, egresos,
-- inventario, usuarios, roles y balance. Los demás roles solo acceden a registros de
-- la subred del usuario (usuarios.subred).
CREATE TABLE IF NOT EXISTS roles (
  idrol INT AUTO_INCREMENT PRIMARY KEY,
  nombrerol VARCHAR(50) NOT NULL UNIQUE,
  es_admin TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1
);

INSERT IGNORE INTO roles (nombrerol, es_admin) VALUES ('admin', 1), ('lider_norte', 0);

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  idrol INT NULL,
  subred VARCHAR(100) NULL,  -- subred a la que pertenece; obligatoria si el rol no es admin
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (idrol) REFERENCES roles (idrol)
);

CREATE TABLE IF NOT EXISTS inventario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  descripcion VARCHAR(255) NOT NULL,
  valor DECIMAL(12,2) NOT NULL,                 -- valor TOTAL de la existencia
  existencia INT UNSIGNED NOT NULL DEFAULT 0,
  -- se calcula solo: valor / existencia (NULL si la existencia es 0)
  preciounitario DECIMAL(12,2) GENERATED ALWAYS AS (IF(existencia > 0, valor / existencia, NULL)) STORED,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- nombre_lanzado puede quedar vacío: en ese caso quien paga es el líder
CREATE TABLE IF NOT EXISTS registros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre_lanzado VARCHAR(150) NOT NULL DEFAULT '',
  nombre_lider VARCHAR(150) NOT NULL,
  subred VARCHAR(100) NOT NULL DEFAULT '',
  numero_boleta VARCHAR(50) NOT NULL UNIQUE,
  monto_boleta DECIMAL(12,2) NOT NULL,
  imagen_boleta LONGTEXT NULL,  -- imagen de la boleta en base64 (opcional)
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Egresos: vvalor es el total pagado; cantidad e idinventario enlazan el egreso con el inventario;
--          preciounitario se calcula solo (vvalor / cantidad).
-- Ingresos: tipopersona ('lider'|'lanzado') y nombrepersona salen de la tabla registros.
--           idregistro enlaza el ingreso generado automáticamente al crear un registro.
CREATE TABLE IF NOT EXISTS ingresosegresos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipoingresoegreso ENUM('ingreso','egreso') NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  vvalor DECIMAL(12,2) NOT NULL,
  observaciones TEXT NULL,
  idinventario INT NULL,
  cantidad INT UNSIGNED NULL,
  preciounitario DECIMAL(12,2) GENERATED ALWAYS AS (IF(cantidad > 0, vvalor / cantidad, NULL)) STORED,
  tipopersona ENUM('lider','lanzado') NULL,
  nombrepersona VARCHAR(150) NULL,
  idregistro INT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ie_inventario FOREIGN KEY (idinventario) REFERENCES inventario (id),
  CONSTRAINT fk_ie_registro FOREIGN KEY (idregistro) REFERENCES registros (id)
);

CREATE TABLE IF NOT EXISTS salidas_inventario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  idinventario INT NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  motivo VARCHAR(255) NULL,
  observaciones TEXT NULL,
  idusuario INT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_salidas_inventario FOREIGN KEY (idinventario) REFERENCES inventario (id)
);
