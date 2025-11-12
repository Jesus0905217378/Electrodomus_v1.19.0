-- =========================================================
-- MIGRATIONS SQL – Sistema Web Electricidad (PHP/MySQL)
-- Requisitos: MySQL 8+, UTF8MB4
-- =========================================================

-- (Opcional) Crear BD y seleccionarla:
-- CREATE DATABASE IF NOT EXISTS electrica_db
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- USE electrica_db;

-- Ajuste de sesión
SET NAMES utf8mb4;
SET time_zone = "+00:00";

-- Desactivar claves para recrear tablas sin errores
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- DROP TABLES (si existen, para reimportar cómodamente)
-- =========================================================
DROP TABLE IF EXISTS reportes;
DROP TABLE IF EXISTS intentos;
DROP TABLE IF EXISTS preguntas;
DROP TABLE IF EXISTS evaluaciones;
DROP TABLE IF EXISTS simulaciones_uso;
DROP TABLE IF EXISTS simulaciones;
DROP TABLE IF EXISTS contenidos;
DROP TABLE IF EXISTS usuarios;

-- =========================================================
-- USUARIOS
-- =========================================================
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(100)              NOT NULL,
  email         VARCHAR(120)              NOT NULL UNIQUE,
  password_hash VARCHAR(255)              NOT NULL,
  role          ENUM('admin','user')      NOT NULL DEFAULT 'user',
  created_at    TIMESTAMP                 NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_usuarios_email ON usuarios(email);

-- =========================================================
-- CONTENIDOS (material teórico / HTML / enlaces)
-- =========================================================
CREATE TABLE contenidos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  titulo      VARCHAR(200)   NOT NULL,
  descripcion TEXT           NULL,
  cuerpo      LONGTEXT       NULL,           -- se permite HTML
  autor_id    INT            NULL,
  created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP      NULL DEFAULT NULL,
  CONSTRAINT fk_contenidos_autor
    FOREIGN KEY (autor_id) REFERENCES usuarios(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_contenidos_created_at ON contenidos(created_at);

-- =========================================================
-- SIMULACIONES (catálogo)
-- =========================================================
CREATE TABLE simulaciones (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  clave       VARCHAR(50)  NOT NULL,      -- ej: 'ohm', 'serie', 'paralelo'
  titulo      VARCHAR(150) NOT NULL,
  descripcion TEXT         NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE UNIQUE INDEX uq_simulaciones_clave ON simulaciones(clave);

-- =========================================================
-- SIMULACIONES_USO (registro de ejecuciones del usuario)
-- =========================================================
CREATE TABLE simulaciones_uso (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT       NOT NULL,
  simulacion_id INT       NOT NULL,
  parametros    JSON      NULL,            -- valores de sliders/inputs
  resultado     JSON      NULL,            -- cálculos/resultados
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_simu_uso_user
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_simu_uso_sim
    FOREIGN KEY (simulacion_id) REFERENCES simulaciones(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_simu_uso_user ON simulaciones_uso(usuario_id);
CREATE INDEX idx_simu_uso_sim ON simulaciones_uso(simulacion_id);
CREATE INDEX idx_simu_uso_created ON simulaciones_uso(created_at);

-- =========================================================
-- EVALUACIONES (quizzes)
-- =========================================================
CREATE TABLE evaluaciones (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  titulo      VARCHAR(150) NOT NULL,
  descripcion TEXT         NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- PREGUNTAS
--  tipo = 'opcion_multiple' => usar campo opciones (JSON array de strings)
--  tipo = 'verdadero_falso' => opciones NULL; respuesta_correcta = [true] o [false]
--  respuesta_correcta: JSON (índices correctos o boolean)
-- =========================================================
CREATE TABLE preguntas (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  evaluacion_id      INT                      NOT NULL,
  enunciado          TEXT                     NOT NULL,
  tipo               ENUM('opcion_multiple','verdadero_falso') NOT NULL,
  opciones           JSON                     NULL,
  respuesta_correcta JSON                     NOT NULL,
  CONSTRAINT fk_preg_eval
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_preguntas_eval ON preguntas(evaluacion_id);

-- =========================================================
-- INTENTOS (resultado por usuario)
-- =========================================================
CREATE TABLE intentos (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  evaluacion_id INT           NOT NULL,
  usuario_id    INT           NOT NULL,
  puntaje       DECIMAL(5,2)  NOT NULL,
  detalle       JSON          NULL,     -- respuestas del usuario
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_int_eval
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_int_user
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_intentos_user ON intentos(usuario_id);
CREATE INDEX idx_intentos_eval ON intentos(evaluacion_id);

-- =========================================================
-- REPORTES (resúmenes agregados o exportables)
-- =========================================================
CREATE TABLE reportes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT       NULL,
  tipo       VARCHAR(50) NULL,     -- ej: 'uso', 'notas'
  payload    JSON       NULL,
  created_at TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rep_user
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_reportes_user ON reportes(usuario_id);

-- Reactivar claves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- SEEDS (datos iniciales)
-- =========================================================

-- Simulaciones básicas
INSERT INTO simulaciones (clave, titulo, descripcion) VALUES
 ('ohm',    'Ley de Ohm',          'Simulación de V, I, R con sliders y lectura inmediata.'),
 ('serie',  'Circuito en Serie',    'Resistencias en serie: Req e intensidad común.'),
 ('paralelo','Circuito en Paralelo','Resistencias en paralelo: Req y corrientes por rama.');

-- Evaluaciones
INSERT INTO evaluaciones (titulo, descripcion) VALUES
 ('Evaluación 1: Fundamentos', 'Voltaje, corriente, resistencia, Ley de Ohm.'),
 ('Evaluación 2: Serie y Paralelo', 'Cálculos básicos de circuitos.');

-- Preguntas (Eval 1)
INSERT INTO preguntas (evaluacion_id, enunciado, tipo, opciones, respuesta_correcta) VALUES
 (1, 'Si V=10V y R=5Ω, ¿cuánta corriente circula?', 'opcion_multiple',
     JSON_ARRAY('1 A','2 A','0.5 A','5 A'), JSON_ARRAY(1)),
 (1, 'La resistencia se mide en ohmios (Ω).', 'verdadero_falso',
     NULL, JSON_ARRAY(true));

-- Preguntas (Eval 2)
INSERT INTO preguntas (evaluacion_id, enunciado, tipo, opciones, respuesta_correcta) VALUES
 (2, 'En serie, la resistencia equivalente es la suma de resistencias.', 'verdadero_falso',
     NULL, JSON_ARRAY(true)),
 (2, 'En paralelo, 1/Req = 1/R1 + 1/R2 + ...', 'verdadero_falso',
     NULL, JSON_ARRAY(true));

-- (Opcional) Contenido inicial
INSERT INTO contenidos (titulo, descripcion, cuerpo, autor_id)
VALUES
 ('Introducción a la Electricidad',
  'Conceptos básicos: voltaje, corriente, resistencia y potencia.',
  '<p>La electricidad estudia el movimiento de cargas eléctricas. Conceptos clave: <strong>V</strong> (voltaje), <strong>I</strong> (corriente), <strong>R</strong> (resistencia).</p><p>Ley de Ohm: V = I · R.</p>',
  NULL);

-- =========================================================
-- NOTAS:
-- 1) No se crea usuario admin por defecto: usa la pantalla de Registro
--    y luego cambia el campo role a 'admin' desde phpMyAdmin si lo necesitas.
-- 2) Los tipos JSON requieren MySQL 5.7+ (ideal MySQL 8).
-- 3) Todas las tablas usan utf8mb4.
-- =========================================================
