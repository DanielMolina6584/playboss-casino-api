-- =============================================================================
-- PLAYBOSS — SCRIPT DE BASE DE DATOS (MySQL 8+)
-- Modelo en 3FN — 33 tablas
-- Orden de creación respeta las dependencias de llaves foráneas.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1. EMPRESA (tabla de una sola fila — reducida a lo esencial)
-- =============================================================================
CREATE TABLE empresa (
    nit                 VARCHAR(20)     NOT NULL,
    razon_social         VARCHAR(150)    NOT NULL,
    nombre_comercial     VARCHAR(100)    NOT NULL,
    direccion             VARCHAR(200)    NULL,
    telefono               VARCHAR(20)     NULL,
    correo_contacto       VARCHAR(120)    NULL,
    licencia_operacion    VARCHAR(50)     NULL,
    fecha_registro         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_empresa PRIMARY KEY (nit)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================================================
-- 2. CATÁLOGOS GENERALES
-- =============================================================================

CREATE TABLE pais (
    codigo_iso   CHAR(2)      NOT NULL,
    nombre        VARCHAR(80)  NOT NULL,
    CONSTRAINT pk_pais PRIMARY KEY (codigo_iso)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE ciudad (
    id_ciudad      INT             NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(80)     NOT NULL,
    pais_codigo    CHAR(2)         NOT NULL,
    CONSTRAINT pk_ciudad PRIMARY KEY (id_ciudad),
    CONSTRAINT fk_ciudad_pais FOREIGN KEY (pais_codigo)
        REFERENCES pais (codigo_iso) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_ciudad_pais (pais_codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE tipo_documento (
    codigo   VARCHAR(5)    NOT NULL,
    nombre    VARCHAR(60)   NOT NULL,
    CONSTRAINT pk_tipo_documento PRIMARY KEY (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE estado_usuario (
    codigo        VARCHAR(5)    NOT NULL,
    nombre         VARCHAR(40)   NOT NULL,
    descripcion    VARCHAR(150)  NULL,
    CONSTRAINT pk_estado_usuario PRIMARY KEY (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE rol (
    codigo        VARCHAR(15)   NOT NULL,
    nombre         VARCHAR(50)   NOT NULL,
    descripcion    VARCHAR(150)  NULL,
    CONSTRAINT pk_rol PRIMARY KEY (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE servicio (
    codigo   VARCHAR(10)   NOT NULL,
    nombre    VARCHAR(50)   NOT NULL,
    activo    BOOLEAN       NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_servicio PRIMARY KEY (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================================================
-- 3. DEPORTES: LIGAS, TEMPORADAS, EQUIPOS, ESTADIOS
-- =============================================================================

CREATE TABLE liga (
    id_liga           INT             NOT NULL AUTO_INCREMENT,
    servicio_codigo   VARCHAR(10)     NOT NULL,
    pais_codigo       CHAR(2)         NOT NULL,
    nombre             VARCHAR(80)     NOT NULL,
    logo_url           VARCHAR(255)    NULL,
    activa             BOOLEAN         NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_liga PRIMARY KEY (id_liga),
    CONSTRAINT fk_liga_servicio FOREIGN KEY (servicio_codigo)
        REFERENCES servicio (codigo) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_liga_pais FOREIGN KEY (pais_codigo)
        REFERENCES pais (codigo_iso) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_liga_servicio (servicio_codigo),
    INDEX idx_liga_pais (pais_codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE temporada (
    id_temporada   INT             NOT NULL AUTO_INCREMENT,
    liga_id         INT             NOT NULL,
    nombre           VARCHAR(20)     NOT NULL,
    fecha_inicio     DATE            NOT NULL,
    fecha_fin        DATE            NOT NULL,
    CONSTRAINT pk_temporada PRIMARY KEY (id_temporada),
    CONSTRAINT fk_temporada_liga FOREIGN KEY (liga_id)
        REFERENCES liga (id_liga) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT uq_temporada_liga_nombre UNIQUE (liga_id, nombre),
    INDEX idx_temporada_liga (liga_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE estadio (
    id_estadio   INT             NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(100)    NOT NULL,
    ciudad_id    INT             NOT NULL,
    capacidad     INT             NULL,
    direccion     VARCHAR(200)    NULL,
    CONSTRAINT pk_estadio PRIMARY KEY (id_estadio),
    CONSTRAINT fk_estadio_ciudad FOREIGN KEY (ciudad_id)
        REFERENCES ciudad (id_ciudad) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_estadio_ciudad (ciudad_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE equipo (
    id_equipo         INT             NOT NULL AUTO_INCREMENT,
    nombre              VARCHAR(80)     NOT NULL,
    escudo_url          VARCHAR(255)    NULL,
    ciudad_id          INT             NOT NULL,
    estadio_id         INT             NULL,
    fecha_fundacion     DATE            NULL,
    CONSTRAINT pk_equipo PRIMARY KEY (id_equipo),
    CONSTRAINT fk_equipo_ciudad FOREIGN KEY (ciudad_id)
        REFERENCES ciudad (id_ciudad) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_equipo_estadio FOREIGN KEY (estadio_id)
        REFERENCES estadio (id_estadio) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_equipo_ciudad (ciudad_id),
    INDEX idx_equipo_estadio (estadio_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE equipo_liga_temporada (
    equipo_id           INT             NOT NULL,
    temporada_id        INT             NOT NULL,
    fecha_inscripcion    DATE            NOT NULL,
    puntos                INT             NOT NULL DEFAULT 0,
    CONSTRAINT pk_equipo_liga_temporada PRIMARY KEY (equipo_id, temporada_id),
    CONSTRAINT fk_elt_equipo FOREIGN KEY (equipo_id)
        REFERENCES equipo (id_equipo) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_elt_temporada FOREIGN KEY (temporada_id)
        REFERENCES temporada (id_temporada) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================================================
-- 4. JUGADORES
-- =============================================================================

CREATE TABLE posicion (
    codigo   VARCHAR(3)    NOT NULL,
    nombre    VARCHAR(30)   NOT NULL,
    CONSTRAINT pk_posicion PRIMARY KEY (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE jugador (
    id_jugador           INT             NOT NULL AUTO_INCREMENT,
    primer_nombre         VARCHAR(50)     NOT NULL,
    segundo_nombre        VARCHAR(50)     NULL,
    primer_apellido       VARCHAR(50)     NULL,
    segundo_apellido      VARCHAR(50)     NULL,
    fecha_nacimiento       DATE            NULL,
    pais_codigo           CHAR(2)         NULL,
    posicion_codigo       VARCHAR(3)      NULL,
    equipo_actual_id      INT             NULL,
    dorsal                  SMALLINT        NULL,
    foto_url                VARCHAR(255)    NULL,
    CONSTRAINT pk_jugador PRIMARY KEY (id_jugador),
    CONSTRAINT fk_jugador_pais FOREIGN KEY (pais_codigo)
        REFERENCES pais (codigo_iso) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_jugador_posicion FOREIGN KEY (posicion_codigo)
        REFERENCES posicion (codigo) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_jugador_equipo FOREIGN KEY (equipo_actual_id)
        REFERENCES equipo (id_equipo) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_jugador_equipo (equipo_actual_id),
    INDEX idx_jugador_pais (pais_codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================================================
-- 5. USUARIOS Y SEGURIDAD
-- =============================================================================

CREATE TABLE usuario (
    id_usuario      BIGINT          NOT NULL AUTO_INCREMENT,
    rol_codigo      VARCHAR(15)     NOT NULL,
    estado_codigo   VARCHAR(5)      NOT NULL,
    fecha_registro  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_usuario PRIMARY KEY (id_usuario),
    CONSTRAINT fk_usuario_rol FOREIGN KEY (rol_codigo)
        REFERENCES rol (codigo) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_usuario_estado FOREIGN KEY (estado_codigo)
        REFERENCES estado_usuario (codigo) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_usuario_rol (rol_codigo),
    INDEX idx_usuario_estado (estado_codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE usuario_credencial (
    usuario_id       BIGINT          NOT NULL,
    correo           VARCHAR(120)    NOT NULL,
    contrasena_hash  VARCHAR(255)    NOT NULL,
    CONSTRAINT pk_usuario_credencial PRIMARY KEY (usuario_id),
    CONSTRAINT uq_usuario_credencial_correo UNIQUE (correo),
    CONSTRAINT fk_usuario_credencial_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE usuario_perfil (
    usuario_id             BIGINT          NOT NULL,
    primer_nombre          VARCHAR(50)     NOT NULL,
    segundo_nombre         VARCHAR(50)     NULL,
    primer_apellido        VARCHAR(50)     NOT NULL,
    segundo_apellido       VARCHAR(50)     NULL,
    tipo_documento_codigo  VARCHAR(5)      NOT NULL,
    numero_documento       VARCHAR(20)     NOT NULL,
    celular                VARCHAR(20)     NULL,
    fecha_nacimiento       DATE            NOT NULL,
    CONSTRAINT pk_usuario_perfil PRIMARY KEY (usuario_id),
    CONSTRAINT uq_usuario_perfil_documento UNIQUE (tipo_documento_codigo, numero_documento),
    CONSTRAINT fk_usuario_perfil_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_usuario_perfil_tipo_documento FOREIGN KEY (tipo_documento_codigo)
        REFERENCES tipo_documento (codigo) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE logs (
    id_login       BIGINT          NOT NULL AUTO_INCREMENT,
    usuario_id      BIGINT          NOT NULL,
    fecha_hora       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_origen        VARCHAR(45)     NULL,
    dispositivo      VARCHAR(150)    NULL,
    exitoso           BOOLEAN         NOT NULL,
    motivo_fallo     VARCHAR(100)    NULL,
    CONSTRAINT pk_logs PRIMARY KEY (id_login),
    CONSTRAINT fk_logs_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_logs_usuario_fecha (usuario_id, fecha_hora)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE codigo_recuperacion (
    id_codigo           BIGINT          NOT NULL AUTO_INCREMENT,
    usuario_id            BIGINT          NOT NULL,
    codigo                 CHAR(6)         NOT NULL,
    fecha_generacion       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion       TIMESTAMP       NOT NULL,
    usado                   BOOLEAN         NOT NULL DEFAULT FALSE,
    CONSTRAINT pk_codigo_recuperacion PRIMARY KEY (id_codigo),
    CONSTRAINT fk_codigo_recuperacion_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_codigo_recuperacion_usuario (usuario_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================================================
-- 6. CALENDARIO Y PARTIDOS
-- =============================================================================

CREATE TABLE jornada (
    id_jornada     INT             NOT NULL AUTO_INCREMENT,
    temporada_id    INT             NOT NULL,
    numero           SMALLINT        NOT NULL,
    fecha_inicio     DATE            NOT NULL,
    fecha_fin        DATE            NOT NULL,
    CONSTRAINT pk_jornada PRIMARY KEY (id_jornada),
    CONSTRAINT fk_jornada_temporada FOREIGN KEY (temporada_id)
        REFERENCES temporada (id_temporada) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT uq_jornada_temporada_numero UNIQUE (temporada_id, numero),
    INDEX idx_jornada_temporada (temporada_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE estado_partido (
    codigo   VARCHAR(4)    NOT NULL,
    nombre    VARCHAR(30)   NOT NULL,
    CONSTRAINT pk_estado_partido PRIMARY KEY (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE partido (
    id_partido             BIGINT          NOT NULL AUTO_INCREMENT,
    jornada_id               INT             NOT NULL,
    equipo_local_id          INT             NOT NULL,
    equipo_visitante_id      INT             NOT NULL,
    estadio_id               INT             NULL,
    fecha_hora                TIMESTAMP       NOT NULL,
    estado_codigo            VARCHAR(4)      NOT NULL,
    goles_local               SMALLINT        NULL,
    goles_visitante           SMALLINT        NULL,
    CONSTRAINT pk_partido PRIMARY KEY (id_partido),
    CONSTRAINT fk_partido_jornada FOREIGN KEY (jornada_id)
        REFERENCES jornada (id_jornada) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_partido_equipo_local FOREIGN KEY (equipo_local_id)
        REFERENCES equipo (id_equipo) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_partido_equipo_visitante FOREIGN KEY (equipo_visitante_id)
        REFERENCES equipo (id_equipo) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_partido_estadio FOREIGN KEY (estadio_id)
        REFERENCES estadio (id_estadio) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_partido_estado FOREIGN KEY (estado_codigo)
        REFERENCES estado_partido (codigo) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_partido_jornada (jornada_id),
    INDEX idx_partido_fecha (fecha_hora),
    INDEX idx_partido_estado (estado_codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE alineacion (
    partido_id       BIGINT          NOT NULL,
    jugador_id        INT             NOT NULL,
    equipo_id          INT             NOT NULL,
    titular             BOOLEAN         NOT NULL DEFAULT TRUE,
    minuto_entra       SMALLINT        NULL,
    minuto_sale        SMALLINT        NULL,
    CONSTRAINT pk_alineacion PRIMARY KEY (partido_id, jugador_id),
    CONSTRAINT fk_alineacion_partido FOREIGN KEY (partido_id)
        REFERENCES partido (id_partido) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_alineacion_jugador FOREIGN KEY (jugador_id)
        REFERENCES jugador (id_jugador) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_alineacion_equipo FOREIGN KEY (equipo_id)
        REFERENCES equipo (id_equipo) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_alineacion_equipo (equipo_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================================================
-- 7. APUESTAS, MERCADOS Y REGLAS
-- =============================================================================

CREATE TABLE mercado (
    codigo             VARCHAR(10)   NOT NULL,
    nombre              VARCHAR(60)   NOT NULL,
    servicio_codigo    VARCHAR(10)   NOT NULL,
    CONSTRAINT pk_mercado PRIMARY KEY (codigo),
    CONSTRAINT fk_mercado_servicio FOREIGN KEY (servicio_codigo)
        REFERENCES servicio (codigo) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE regla_apuesta (
    id_regla                 INT             NOT NULL AUTO_INCREMENT,
    mercado_codigo            VARCHAR(10)     NOT NULL,
    monto_minimo               DECIMAL(12,2)   NOT NULL,
    monto_maximo               DECIMAL(12,2)   NOT NULL,
    ganancia_maxima            DECIMAL(12,2)   NULL,
    edad_minima                 SMALLINT        NOT NULL DEFAULT 18,
    activa                       BOOLEAN         NOT NULL DEFAULT TRUE,
    fecha_vigencia_desde        DATE            NOT NULL,
    CONSTRAINT pk_regla_apuesta PRIMARY KEY (id_regla),
    CONSTRAINT fk_regla_mercado FOREIGN KEY (mercado_codigo)
        REFERENCES mercado (codigo) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_regla_mercado (mercado_codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE cuota (
    id_cuota               BIGINT          NOT NULL AUTO_INCREMENT,
    partido_id               BIGINT          NOT NULL,
    mercado_codigo           VARCHAR(10)     NOT NULL,
    seleccion                 VARCHAR(20)     NOT NULL,
    valor                      DECIMAL(6,2)    NOT NULL,
    fecha_actualizacion       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_cuota PRIMARY KEY (id_cuota),
    CONSTRAINT fk_cuota_partido FOREIGN KEY (partido_id)
        REFERENCES partido (id_partido) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_cuota_mercado FOREIGN KEY (mercado_codigo)
        REFERENCES mercado (codigo) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_cuota_partido_mercado (partido_id, mercado_codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE estado_apuesta (
    codigo   VARCHAR(4)    NOT NULL,
    nombre    VARCHAR(30)   NOT NULL,
    CONSTRAINT pk_estado_apuesta PRIMARY KEY (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE apuesta (
    id_apuesta               BIGINT          NOT NULL AUTO_INCREMENT,
    usuario_id                 BIGINT          NOT NULL,
    fecha_hora                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto                        DECIMAL(12,2)   NOT NULL,
    cuota_total                 DECIMAL(8,2)    NOT NULL,
    ganancia_potencial          DECIMAL(12,2)   NOT NULL,
    estado_codigo               VARCHAR(4)      NOT NULL,
    CONSTRAINT pk_apuesta PRIMARY KEY (id_apuesta),
    CONSTRAINT fk_apuesta_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_apuesta_estado FOREIGN KEY (estado_codigo)
        REFERENCES estado_apuesta (codigo) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_apuesta_monto_positivo CHECK (monto > 0),
    INDEX idx_apuesta_usuario (usuario_id),
    INDEX idx_apuesta_estado (estado_codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE apuesta_detalle (
    apuesta_id                 BIGINT          NOT NULL,
    partido_id                  BIGINT          NOT NULL,
    cuota_id                     BIGINT          NOT NULL,
    valor_cuota_congelado       DECIMAL(6,2)    NOT NULL,
    CONSTRAINT pk_apuesta_detalle PRIMARY KEY (apuesta_id, partido_id),
    CONSTRAINT fk_apuesta_detalle_apuesta FOREIGN KEY (apuesta_id)
        REFERENCES apuesta (id_apuesta) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_apuesta_detalle_partido FOREIGN KEY (partido_id)
        REFERENCES partido (id_partido) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_apuesta_detalle_cuota FOREIGN KEY (cuota_id)
        REFERENCES cuota (id_cuota) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_apuesta_detalle_cuota (cuota_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================================================
-- 8. CUENTAS Y PAGOS
-- =============================================================================

CREATE TABLE cuenta (
    usuario_id                BIGINT          NOT NULL,
    saldo_disponible            DECIMAL(12,2)   NOT NULL DEFAULT 0,
    saldo_retenido               DECIMAL(12,2)   NOT NULL DEFAULT 0,
    fecha_actualizacion          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_cuenta PRIMARY KEY (usuario_id),
    CONSTRAINT fk_cuenta_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_cuenta_saldos_no_negativos CHECK (saldo_disponible >= 0 AND saldo_retenido >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE metodo_pago (
    codigo   VARCHAR(10)   NOT NULL,
    nombre    VARCHAR(50)   NOT NULL,
    activo    BOOLEAN       NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_metodo_pago PRIMARY KEY (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE transaccion (
    id_transaccion             BIGINT          NOT NULL AUTO_INCREMENT,
    usuario_id                    BIGINT          NOT NULL,
    metodo_pago_codigo            VARCHAR(10)     NOT NULL,
    tipo                            VARCHAR(10)     NOT NULL,
    monto                            DECIMAL(12,2)   NOT NULL,
    estado                           VARCHAR(15)     NOT NULL DEFAULT 'PENDIENTE',
    fecha_hora                       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    referencia_externa             VARCHAR(100)    NULL,
    CONSTRAINT pk_transaccion PRIMARY KEY (id_transaccion),
    CONSTRAINT fk_transaccion_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_transaccion_metodo_pago FOREIGN KEY (metodo_pago_codigo)
        REFERENCES metodo_pago (codigo) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_transaccion_tipo CHECK (tipo IN ('DEPOSITO', 'RETIRO')),
    CONSTRAINT chk_transaccion_monto_positivo CHECK (monto > 0),
    INDEX idx_transaccion_usuario (usuario_id),
    INDEX idx_transaccion_estado (estado)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- log_pago: bitácora técnica de solo-inserción, sin llave primaria a propósito
-- (ver justificación en el documento de modelo de datos).
CREATE TABLE log_pago (
    transaccion_id     BIGINT          NOT NULL,
    fecha_evento          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo_evento           VARCHAR(40)     NOT NULL,
    payload                 JSON            NULL,
    codigo_respuesta      VARCHAR(10)     NULL,
    CONSTRAINT fk_log_pago_transaccion FOREIGN KEY (transaccion_id)
        REFERENCES transaccion (id_transaccion) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_log_pago_transaccion_fecha (transaccion_id, fecha_evento)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- FIN DEL SCRIPT — 33 tablas
-- =============================================================================
