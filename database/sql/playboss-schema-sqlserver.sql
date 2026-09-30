-- PLAYBOSS schema for Microsoft SQL Server 2016+.
-- Run in an empty database. Identity columns replace MySQL AUTO_INCREMENT;
-- BIT, DATETIME2, NVARCHAR and CREATE INDEX replace MySQL-specific syntax.

CREATE TABLE empresa (
    nit NVARCHAR(20) NOT NULL CONSTRAINT pk_empresa PRIMARY KEY,
    razon_social NVARCHAR(150) NOT NULL,
    nombre_comercial NVARCHAR(100) NOT NULL,
    direccion NVARCHAR(200) NULL,
    telefono NVARCHAR(20) NULL,
    correo_contacto NVARCHAR(120) NULL,
    licencia_operacion NVARCHAR(50) NULL,
    fecha_registro DATETIME2 NOT NULL CONSTRAINT df_empresa_fecha DEFAULT SYSDATETIME()
);

CREATE TABLE pais (
    codigo_iso NCHAR(2) NOT NULL CONSTRAINT pk_pais PRIMARY KEY,
    nombre NVARCHAR(80) NOT NULL
);

CREATE TABLE ciudad (
    id_ciudad INT IDENTITY(1,1) NOT NULL CONSTRAINT pk_ciudad PRIMARY KEY,
    nombre NVARCHAR(80) NOT NULL,
    pais_codigo NCHAR(2) NOT NULL,
    CONSTRAINT fk_ciudad_pais FOREIGN KEY (pais_codigo) REFERENCES pais(codigo_iso) ON UPDATE CASCADE ON DELETE NO ACTION
);
CREATE INDEX idx_ciudad_pais ON ciudad(pais_codigo);

CREATE TABLE tipo_documento (
    codigo NVARCHAR(5) NOT NULL CONSTRAINT pk_tipo_documento PRIMARY KEY,
    nombre NVARCHAR(60) NOT NULL
);

CREATE TABLE estado_usuario (
    codigo NVARCHAR(5) NOT NULL CONSTRAINT pk_estado_usuario PRIMARY KEY,
    nombre NVARCHAR(40) NOT NULL,
    descripcion NVARCHAR(150) NULL
);

CREATE TABLE rol (
    codigo NVARCHAR(15) NOT NULL CONSTRAINT pk_rol PRIMARY KEY,
    nombre NVARCHAR(50) NOT NULL,
    descripcion NVARCHAR(150) NULL
);

CREATE TABLE servicio (
    codigo NVARCHAR(10) NOT NULL CONSTRAINT pk_servicio PRIMARY KEY,
    nombre NVARCHAR(50) NOT NULL,
    activo BIT NOT NULL CONSTRAINT df_servicio_activo DEFAULT 1
);

CREATE TABLE liga (
    id_liga INT IDENTITY(1,1) NOT NULL CONSTRAINT pk_liga PRIMARY KEY,
    servicio_codigo NVARCHAR(10) NOT NULL,
    pais_codigo NCHAR(2) NOT NULL,
    nombre NVARCHAR(80) NOT NULL,
    logo_url NVARCHAR(255) NULL,
    activa BIT NOT NULL CONSTRAINT df_liga_activa DEFAULT 1,
    CONSTRAINT fk_liga_servicio FOREIGN KEY (servicio_codigo) REFERENCES servicio(codigo) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT fk_liga_pais FOREIGN KEY (pais_codigo) REFERENCES pais(codigo_iso) ON UPDATE CASCADE ON DELETE NO ACTION
);
CREATE INDEX idx_liga_servicio ON liga(servicio_codigo);
CREATE INDEX idx_liga_pais ON liga(pais_codigo);

CREATE TABLE temporada (
    id_temporada INT IDENTITY(1,1) NOT NULL CONSTRAINT pk_temporada PRIMARY KEY,
    liga_id INT NOT NULL,
    nombre NVARCHAR(20) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    CONSTRAINT fk_temporada_liga FOREIGN KEY (liga_id) REFERENCES liga(id_liga) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT uq_temporada_liga_nombre UNIQUE (liga_id, nombre)
);
CREATE INDEX idx_temporada_liga ON temporada(liga_id);

CREATE TABLE estadio (
    id_estadio INT IDENTITY(1,1) NOT NULL CONSTRAINT pk_estadio PRIMARY KEY,
    nombre NVARCHAR(100) NOT NULL,
    ciudad_id INT NOT NULL,
    capacidad INT NULL,
    direccion NVARCHAR(200) NULL,
    CONSTRAINT fk_estadio_ciudad FOREIGN KEY (ciudad_id) REFERENCES ciudad(id_ciudad) ON UPDATE CASCADE ON DELETE NO ACTION
);
CREATE INDEX idx_estadio_ciudad ON estadio(ciudad_id);

CREATE TABLE equipo (
    id_equipo INT IDENTITY(1,1) NOT NULL CONSTRAINT pk_equipo PRIMARY KEY,
    nombre NVARCHAR(80) NOT NULL,
    escudo_url NVARCHAR(255) NULL,
    ciudad_id INT NOT NULL,
    estadio_id INT NULL,
    fecha_fundacion DATE NULL,
    CONSTRAINT fk_equipo_ciudad FOREIGN KEY (ciudad_id) REFERENCES ciudad(id_ciudad) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT fk_equipo_estadio FOREIGN KEY (estadio_id) REFERENCES estadio(id_estadio) ON UPDATE CASCADE ON DELETE SET NULL
);
CREATE INDEX idx_equipo_ciudad ON equipo(ciudad_id);
CREATE INDEX idx_equipo_estadio ON equipo(estadio_id);

CREATE TABLE equipo_liga_temporada (
    equipo_id INT NOT NULL,
    temporada_id INT NOT NULL,
    fecha_inscripcion DATE NOT NULL,
    puntos INT NOT NULL CONSTRAINT df_elt_puntos DEFAULT 0,
    CONSTRAINT pk_equipo_liga_temporada PRIMARY KEY (equipo_id, temporada_id),
    CONSTRAINT fk_elt_equipo FOREIGN KEY (equipo_id) REFERENCES equipo(id_equipo) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_elt_temporada FOREIGN KEY (temporada_id) REFERENCES temporada(id_temporada) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE posicion (
    codigo NVARCHAR(3) NOT NULL CONSTRAINT pk_posicion PRIMARY KEY,
    nombre NVARCHAR(30) NOT NULL
);

CREATE TABLE jugador (
    id_jugador INT IDENTITY(1,1) NOT NULL CONSTRAINT pk_jugador PRIMARY KEY,
    primer_nombre NVARCHAR(50) NOT NULL,
    segundo_nombre NVARCHAR(50) NULL,
    primer_apellido NVARCHAR(50) NULL,
    segundo_apellido NVARCHAR(50) NULL,
    fecha_nacimiento DATE NULL,
    pais_codigo NCHAR(2) NULL,
    posicion_codigo NVARCHAR(3) NULL,
    equipo_actual_id INT NULL,
    dorsal SMALLINT NULL,
    foto_url NVARCHAR(255) NULL,
    CONSTRAINT fk_jugador_pais FOREIGN KEY (pais_codigo) REFERENCES pais(codigo_iso) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT fk_jugador_posicion FOREIGN KEY (posicion_codigo) REFERENCES posicion(codigo) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT fk_jugador_equipo FOREIGN KEY (equipo_actual_id) REFERENCES equipo(id_equipo) ON UPDATE CASCADE ON DELETE SET NULL
);
CREATE INDEX idx_jugador_equipo ON jugador(equipo_actual_id);
CREATE INDEX idx_jugador_pais ON jugador(pais_codigo);

CREATE TABLE usuario (
    id_usuario BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT pk_usuario PRIMARY KEY,
    rol_codigo NVARCHAR(15) NOT NULL,
    estado_codigo NVARCHAR(5) NOT NULL,
    fecha_registro DATETIME2 NOT NULL CONSTRAINT df_usuario_fecha DEFAULT SYSDATETIME(),
    CONSTRAINT fk_usuario_rol FOREIGN KEY (rol_codigo) REFERENCES rol(codigo) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT fk_usuario_estado FOREIGN KEY (estado_codigo) REFERENCES estado_usuario(codigo) ON UPDATE CASCADE ON DELETE NO ACTION
);
CREATE INDEX idx_usuario_rol ON usuario(rol_codigo);
CREATE INDEX idx_usuario_estado ON usuario(estado_codigo);

CREATE TABLE usuario_credencial (
    usuario_id BIGINT NOT NULL CONSTRAINT pk_usuario_credencial PRIMARY KEY,
    correo NVARCHAR(120) NOT NULL CONSTRAINT uq_usuario_credencial_correo UNIQUE,
    contrasena_hash NVARCHAR(255) NOT NULL,
    CONSTRAINT fk_usuario_credencial_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id_usuario) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE usuario_perfil (
    usuario_id BIGINT NOT NULL CONSTRAINT pk_usuario_perfil PRIMARY KEY,
    primer_nombre NVARCHAR(50) NOT NULL,
    segundo_nombre NVARCHAR(50) NULL,
    primer_apellido NVARCHAR(50) NOT NULL,
    segundo_apellido NVARCHAR(50) NULL,
    tipo_documento_codigo NVARCHAR(5) NOT NULL,
    numero_documento NVARCHAR(20) NOT NULL,
    celular NVARCHAR(20) NULL,
    fecha_nacimiento DATE NOT NULL,
    CONSTRAINT uq_usuario_perfil_documento UNIQUE (tipo_documento_codigo, numero_documento),
    CONSTRAINT fk_usuario_perfil_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id_usuario) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_usuario_perfil_tipo_documento FOREIGN KEY (tipo_documento_codigo) REFERENCES tipo_documento(codigo) ON UPDATE CASCADE ON DELETE NO ACTION
);

CREATE TABLE logs (
    id_login BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT pk_logs PRIMARY KEY,
    usuario_id BIGINT NOT NULL,
    fecha_hora DATETIME2 NOT NULL CONSTRAINT df_logs_fecha DEFAULT SYSDATETIME(),
    ip_origen NVARCHAR(45) NULL,
    dispositivo NVARCHAR(150) NULL,
    exitoso BIT NOT NULL,
    motivo_fallo NVARCHAR(100) NULL,
    CONSTRAINT fk_logs_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id_usuario) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE INDEX idx_logs_usuario_fecha ON logs(usuario_id, fecha_hora);

CREATE TABLE codigo_recuperacion (
    id_codigo BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT pk_codigo_recuperacion PRIMARY KEY,
    usuario_id BIGINT NOT NULL,
    codigo NCHAR(6) NOT NULL,
    fecha_generacion DATETIME2 NOT NULL CONSTRAINT df_codigo_fecha DEFAULT SYSDATETIME(),
    fecha_expiracion DATETIME2 NOT NULL,
    usado BIT NOT NULL CONSTRAINT df_codigo_usado DEFAULT 0,
    CONSTRAINT fk_codigo_recuperacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id_usuario) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE INDEX idx_codigo_recuperacion_usuario ON codigo_recuperacion(usuario_id);

CREATE TABLE jornada (
    id_jornada INT IDENTITY(1,1) NOT NULL CONSTRAINT pk_jornada PRIMARY KEY,
    temporada_id INT NOT NULL,
    numero SMALLINT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    CONSTRAINT fk_jornada_temporada FOREIGN KEY (temporada_id) REFERENCES temporada(id_temporada) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT uq_jornada_temporada_numero UNIQUE (temporada_id, numero)
);
CREATE INDEX idx_jornada_temporada ON jornada(temporada_id);

CREATE TABLE estado_partido (
    codigo NVARCHAR(4) NOT NULL CONSTRAINT pk_estado_partido PRIMARY KEY,
    nombre NVARCHAR(30) NOT NULL
);

CREATE TABLE partido (
    id_partido BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT pk_partido PRIMARY KEY,
    jornada_id INT NOT NULL,
    equipo_local_id INT NOT NULL,
    equipo_visitante_id INT NOT NULL,
    estadio_id INT NULL,
    fecha_hora DATETIME2 NOT NULL,
    estado_codigo NVARCHAR(4) NOT NULL,
    goles_local SMALLINT NULL,
    goles_visitante SMALLINT NULL,
    CONSTRAINT fk_partido_jornada FOREIGN KEY (jornada_id) REFERENCES jornada(id_jornada) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_partido_equipo_local FOREIGN KEY (equipo_local_id) REFERENCES equipo(id_equipo) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT fk_partido_equipo_visitante FOREIGN KEY (equipo_visitante_id) REFERENCES equipo(id_equipo) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT fk_partido_estadio FOREIGN KEY (estadio_id) REFERENCES estadio(id_estadio) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_partido_estado FOREIGN KEY (estado_codigo) REFERENCES estado_partido(codigo) ON UPDATE CASCADE ON DELETE NO ACTION
);
CREATE INDEX idx_partido_jornada ON partido(jornada_id);
CREATE INDEX idx_partido_fecha ON partido(fecha_hora);
CREATE INDEX idx_partido_estado ON partido(estado_codigo);

CREATE TABLE alineacion (
    partido_id BIGINT NOT NULL,
    jugador_id INT NOT NULL,
    equipo_id INT NOT NULL,
    titular BIT NOT NULL CONSTRAINT df_alineacion_titular DEFAULT 1,
    minuto_entra SMALLINT NULL,
    minuto_sale SMALLINT NULL,
    CONSTRAINT pk_alineacion PRIMARY KEY (partido_id, jugador_id),
    CONSTRAINT fk_alineacion_partido FOREIGN KEY (partido_id) REFERENCES partido(id_partido) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_alineacion_jugador FOREIGN KEY (jugador_id) REFERENCES jugador(id_jugador) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_alineacion_equipo FOREIGN KEY (equipo_id) REFERENCES equipo(id_equipo) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE INDEX idx_alineacion_equipo ON alineacion(equipo_id);

CREATE TABLE mercado (
    codigo NVARCHAR(10) NOT NULL CONSTRAINT pk_mercado PRIMARY KEY,
    nombre NVARCHAR(60) NOT NULL,
    servicio_codigo NVARCHAR(10) NOT NULL,
    CONSTRAINT fk_mercado_servicio FOREIGN KEY (servicio_codigo) REFERENCES servicio(codigo) ON UPDATE CASCADE ON DELETE NO ACTION
);

CREATE TABLE regla_apuesta (
    id_regla INT IDENTITY(1,1) NOT NULL CONSTRAINT pk_regla_apuesta PRIMARY KEY,
    mercado_codigo NVARCHAR(10) NOT NULL,
    monto_minimo DECIMAL(12,2) NOT NULL,
    monto_maximo DECIMAL(12,2) NOT NULL,
    ganancia_maxima DECIMAL(12,2) NULL,
    edad_minima SMALLINT NOT NULL CONSTRAINT df_regla_edad DEFAULT 18,
    activa BIT NOT NULL CONSTRAINT df_regla_activa DEFAULT 1,
    fecha_vigencia_desde DATE NOT NULL,
    CONSTRAINT fk_regla_mercado FOREIGN KEY (mercado_codigo) REFERENCES mercado(codigo) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE INDEX idx_regla_mercado ON regla_apuesta(mercado_codigo);

CREATE TABLE cuota (
    id_cuota BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT pk_cuota PRIMARY KEY,
    partido_id BIGINT NOT NULL,
    mercado_codigo NVARCHAR(10) NOT NULL,
    seleccion NVARCHAR(20) NOT NULL,
    valor DECIMAL(6,2) NOT NULL,
    fecha_actualizacion DATETIME2 NOT NULL CONSTRAINT df_cuota_fecha DEFAULT SYSDATETIME(),
    CONSTRAINT fk_cuota_partido FOREIGN KEY (partido_id) REFERENCES partido(id_partido) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_cuota_mercado FOREIGN KEY (mercado_codigo) REFERENCES mercado(codigo) ON UPDATE CASCADE ON DELETE NO ACTION
);
CREATE INDEX idx_cuota_partido_mercado ON cuota(partido_id, mercado_codigo);

CREATE TABLE estado_apuesta (
    codigo NVARCHAR(4) NOT NULL CONSTRAINT pk_estado_apuesta PRIMARY KEY,
    nombre NVARCHAR(30) NOT NULL
);

CREATE TABLE apuesta (
    id_apuesta BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT pk_apuesta PRIMARY KEY,
    usuario_id BIGINT NOT NULL,
    fecha_hora DATETIME2 NOT NULL CONSTRAINT df_apuesta_fecha DEFAULT SYSDATETIME(),
    monto DECIMAL(12,2) NOT NULL,
    cuota_total DECIMAL(8,2) NOT NULL,
    ganancia_potencial DECIMAL(12,2) NOT NULL,
    estado_codigo NVARCHAR(4) NOT NULL,
    CONSTRAINT fk_apuesta_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id_usuario) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT fk_apuesta_estado FOREIGN KEY (estado_codigo) REFERENCES estado_apuesta(codigo) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT chk_apuesta_monto_positivo CHECK (monto > 0)
);
CREATE INDEX idx_apuesta_usuario ON apuesta(usuario_id);
CREATE INDEX idx_apuesta_estado ON apuesta(estado_codigo);

CREATE TABLE apuesta_detalle (
    apuesta_id BIGINT NOT NULL,
    partido_id BIGINT NOT NULL,
    cuota_id BIGINT NOT NULL,
    valor_cuota_congelado DECIMAL(6,2) NOT NULL,
    CONSTRAINT pk_apuesta_detalle PRIMARY KEY (apuesta_id, partido_id),
    CONSTRAINT fk_apuesta_detalle_apuesta FOREIGN KEY (apuesta_id) REFERENCES apuesta(id_apuesta) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_apuesta_detalle_partido FOREIGN KEY (partido_id) REFERENCES partido(id_partido) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT fk_apuesta_detalle_cuota FOREIGN KEY (cuota_id) REFERENCES cuota(id_cuota) ON UPDATE CASCADE ON DELETE NO ACTION
);
CREATE INDEX idx_apuesta_detalle_cuota ON apuesta_detalle(cuota_id);

CREATE TABLE cuenta (
    usuario_id BIGINT NOT NULL CONSTRAINT pk_cuenta PRIMARY KEY,
    saldo_disponible DECIMAL(12,2) NOT NULL CONSTRAINT df_cuenta_disponible DEFAULT 0,
    saldo_retenido DECIMAL(12,2) NOT NULL CONSTRAINT df_cuenta_retenido DEFAULT 0,
    fecha_actualizacion DATETIME2 NOT NULL CONSTRAINT df_cuenta_fecha DEFAULT SYSDATETIME(),
    CONSTRAINT fk_cuenta_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id_usuario) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_cuenta_saldos_no_negativos CHECK (saldo_disponible >= 0 AND saldo_retenido >= 0)
);
EXEC(N'CREATE TRIGGER trg_cuenta_fecha_actualizacion ON cuenta
AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    IF UPDATE(saldo_disponible) OR UPDATE(saldo_retenido)
        UPDATE c SET fecha_actualizacion = SYSDATETIME()
        FROM cuenta AS c
        INNER JOIN inserted AS i ON i.usuario_id = c.usuario_id;
END');

CREATE TABLE metodo_pago (
    codigo NVARCHAR(10) NOT NULL CONSTRAINT pk_metodo_pago PRIMARY KEY,
    nombre NVARCHAR(50) NOT NULL,
    activo BIT NOT NULL CONSTRAINT df_metodo_pago_activo DEFAULT 1
);

CREATE TABLE transaccion (
    id_transaccion BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT pk_transaccion PRIMARY KEY,
    usuario_id BIGINT NOT NULL,
    metodo_pago_codigo NVARCHAR(10) NOT NULL,
    tipo NVARCHAR(10) NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    estado NVARCHAR(15) NOT NULL CONSTRAINT df_transaccion_estado DEFAULT 'PENDIENTE',
    fecha_hora DATETIME2 NOT NULL CONSTRAINT df_transaccion_fecha DEFAULT SYSDATETIME(),
    referencia_externa NVARCHAR(100) NULL,
    CONSTRAINT fk_transaccion_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id_usuario) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT fk_transaccion_metodo_pago FOREIGN KEY (metodo_pago_codigo) REFERENCES metodo_pago(codigo) ON UPDATE CASCADE ON DELETE NO ACTION,
    CONSTRAINT chk_transaccion_tipo CHECK (tipo IN ('DEPOSITO', 'RETIRO')),
    CONSTRAINT chk_transaccion_monto_positivo CHECK (monto > 0)
);
CREATE INDEX idx_transaccion_usuario ON transaccion(usuario_id);
CREATE INDEX idx_transaccion_estado ON transaccion(estado);

CREATE TABLE log_pago (
    transaccion_id BIGINT NOT NULL,
    fecha_evento DATETIME2 NOT NULL CONSTRAINT df_log_pago_fecha DEFAULT SYSDATETIME(),
    tipo_evento NVARCHAR(40) NOT NULL,
    payload NVARCHAR(MAX) NULL,
    codigo_respuesta NVARCHAR(10) NULL,
    CONSTRAINT fk_log_pago_transaccion FOREIGN KEY (transaccion_id) REFERENCES transaccion(id_transaccion) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE INDEX idx_log_pago_transaccion_fecha ON log_pago(transaccion_id, fecha_evento);
