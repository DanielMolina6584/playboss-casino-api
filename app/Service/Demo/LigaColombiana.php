<?php

namespace App\Service\Demo;

/**
 * Datos de referencia de la Liga BetPlay para la carga demo: ciudades, estadios
 * y los 20 equipos con su fuerza relativa (ataque/defensa) y colores del escudo.
 * Los jugadores de la demo son ficticios.
 */
class LigaColombiana
{
    /** @return array<string,string> clave => nombre de la ciudad */
    public static function ciudades(): array
    {
        return [
            'bogota' => 'Bogotá', 'medellin' => 'Medellín', 'envigado' => 'Envigado', 'rionegro' => 'Rionegro',
            'cali' => 'Cali', 'palmira' => 'Palmira', 'barranquilla' => 'Barranquilla', 'santa_marta' => 'Santa Marta',
            'bucaramanga' => 'Bucaramanga', 'ibague' => 'Ibagué', 'manizales' => 'Manizales', 'pereira' => 'Pereira',
            'pasto' => 'Pasto', 'valledupar' => 'Valledupar', 'tunja' => 'Tunja', 'villavicencio' => 'Villavicencio',
        ];
    }

    /** @return array<string,array{nombre:string,ciudad:string,capacidad:int}> */
    public static function estadios(): array
    {
        return [
            'campin' => ['nombre' => 'Estadio Nemesio Camacho El Campín', 'ciudad' => 'bogota', 'capacidad' => 36343],
            'techo' => ['nombre' => 'Estadio Metropolitano de Techo', 'ciudad' => 'bogota', 'capacidad' => 8000],
            'atanasio' => ['nombre' => 'Estadio Atanasio Girardot', 'ciudad' => 'medellin', 'capacidad' => 40943],
            'polideportivo_sur' => ['nombre' => 'Estadio Polideportivo Sur', 'ciudad' => 'envigado', 'capacidad' => 11000],
            'alberto_grisales' => ['nombre' => 'Estadio Alberto Grisales', 'ciudad' => 'rionegro', 'capacidad' => 14000],
            'pascual_guerrero' => ['nombre' => 'Estadio Olímpico Pascual Guerrero', 'ciudad' => 'cali', 'capacidad' => 33130],
            'deportivo_cali' => ['nombre' => 'Estadio Deportivo Cali', 'ciudad' => 'palmira', 'capacidad' => 42000],
            'metropolitano' => ['nombre' => 'Estadio Metropolitano Roberto Meléndez', 'ciudad' => 'barranquilla', 'capacidad' => 46692],
            'sierra_nevada' => ['nombre' => 'Estadio Sierra Nevada', 'ciudad' => 'santa_marta', 'capacidad' => 16000],
            'montanini' => ['nombre' => 'Estadio Américo Montanini', 'ciudad' => 'bucaramanga', 'capacidad' => 25000],
            'murillo_toro' => ['nombre' => 'Estadio Manuel Murillo Toro', 'ciudad' => 'ibague', 'capacidad' => 28100],
            'palogrande' => ['nombre' => 'Estadio Palogrande', 'ciudad' => 'manizales', 'capacidad' => 31611],
            'hernan_ramirez' => ['nombre' => 'Estadio Hernán Ramírez Villegas', 'ciudad' => 'pereira', 'capacidad' => 30297],
            'libertad' => ['nombre' => 'Estadio Departamental Libertad', 'ciudad' => 'pasto', 'capacidad' => 20665],
            'pavajeau' => ['nombre' => 'Estadio Armando Maestre Pavajeau', 'ciudad' => 'valledupar', 'capacidad' => 11000],
            'independencia' => ['nombre' => 'Estadio La Independencia', 'ciudad' => 'tunja', 'capacidad' => 20630],
            'bello_horizonte' => ['nombre' => 'Estadio Bello Horizonte', 'ciudad' => 'villavicencio', 'capacidad' => 15000],
        ];
    }

    /**
     * Equipos de la Liga BetPlay 2026-II.
     * ataque/defensa: multiplicadores de goles a favor / en contra (1 = promedio).
     * @return array<string,array{nombre:string,siglas:string,ciudad:string,estadio:string,fundacion:?string,colores:array{0:string,1:string},ataque:float,defensa:float}>
     */
    public static function equipos(): array
    {
        return [
            'millonarios' => ['nombre' => 'Millonarios', 'siglas' => 'MIL', 'ciudad' => 'bogota', 'estadio' => 'campin', 'fundacion' => '1946-06-18', 'colores' => ['#0A3D91', '#FFFFFF'], 'ataque' => 1.25, 'defensa' => 0.85],
            'santa_fe' => ['nombre' => 'Independiente Santa Fe', 'siglas' => 'SFE', 'ciudad' => 'bogota', 'estadio' => 'campin', 'fundacion' => '1941-02-28', 'colores' => ['#C8102E', '#FFFFFF'], 'ataque' => 1.10, 'defensa' => 0.90],
            'internacional' => ['nombre' => 'Internacional de Bogotá', 'siglas' => 'INT', 'ciudad' => 'bogota', 'estadio' => 'techo', 'fundacion' => null, 'colores' => ['#5B2C83', '#F2C230'], 'ataque' => 0.90, 'defensa' => 1.05],
            'fortaleza' => ['nombre' => 'Fortaleza CEIF', 'siglas' => 'FOR', 'ciudad' => 'bogota', 'estadio' => 'techo', 'fundacion' => null, 'colores' => ['#F28C00', '#1B1B1B'], 'ataque' => 0.95, 'defensa' => 1.05],
            'nacional' => ['nombre' => 'Atlético Nacional', 'siglas' => 'NAL', 'ciudad' => 'medellin', 'estadio' => 'atanasio', 'fundacion' => '1947-03-07', 'colores' => ['#00843D', '#FFFFFF'], 'ataque' => 1.30, 'defensa' => 0.80],
            'medellin' => ['nombre' => 'Independiente Medellín', 'siglas' => 'DIM', 'ciudad' => 'medellin', 'estadio' => 'atanasio', 'fundacion' => '1913-11-14', 'colores' => ['#D50032', '#003DA5'], 'ataque' => 1.15, 'defensa' => 0.90],
            'envigado' => ['nombre' => 'Envigado FC', 'siglas' => 'ENV', 'ciudad' => 'envigado', 'estadio' => 'polideportivo_sur', 'fundacion' => null, 'colores' => ['#F47920', '#00843D'], 'ataque' => 0.85, 'defensa' => 1.15],
            'aguilas' => ['nombre' => 'Águilas Doradas', 'siglas' => 'AGU', 'ciudad' => 'rionegro', 'estadio' => 'alberto_grisales', 'fundacion' => null, 'colores' => ['#C9A227', '#1B1B1B'], 'ataque' => 0.95, 'defensa' => 1.00],
            'america' => ['nombre' => 'América de Cali', 'siglas' => 'AME', 'ciudad' => 'cali', 'estadio' => 'pascual_guerrero', 'fundacion' => '1927-02-13', 'colores' => ['#E10600', '#FFFFFF'], 'ataque' => 1.15, 'defensa' => 0.90],
            'cali' => ['nombre' => 'Deportivo Cali', 'siglas' => 'CAL', 'ciudad' => 'palmira', 'estadio' => 'deportivo_cali', 'fundacion' => '1912-11-23', 'colores' => ['#007A33', '#FFFFFF'], 'ataque' => 1.00, 'defensa' => 1.00],
            'junior' => ['nombre' => 'Junior FC', 'siglas' => 'JUN', 'ciudad' => 'barranquilla', 'estadio' => 'metropolitano', 'fundacion' => '1924-08-07', 'colores' => ['#D7192D', '#1D3C8C'], 'ataque' => 1.25, 'defensa' => 0.85],
            'union' => ['nombre' => 'Unión Magdalena', 'siglas' => 'UNI', 'ciudad' => 'santa_marta', 'estadio' => 'sierra_nevada', 'fundacion' => null, 'colores' => ['#0033A0', '#E4002B'], 'ataque' => 0.80, 'defensa' => 1.20],
            'bucaramanga' => ['nombre' => 'Atlético Bucaramanga', 'siglas' => 'BUC', 'ciudad' => 'bucaramanga', 'estadio' => 'montanini', 'fundacion' => null, 'colores' => ['#FFD100', '#00843D'], 'ataque' => 1.00, 'defensa' => 0.90],
            'tolima' => ['nombre' => 'Deportes Tolima', 'siglas' => 'TOL', 'ciudad' => 'ibague', 'estadio' => 'murillo_toro', 'fundacion' => '1954-12-18', 'colores' => ['#7A1631', '#F2C230'], 'ataque' => 1.15, 'defensa' => 0.85],
            'once_caldas' => ['nombre' => 'Once Caldas', 'siglas' => 'ONC', 'ciudad' => 'manizales', 'estadio' => 'palogrande', 'fundacion' => null, 'colores' => ['#FFFFFF', '#1B1B1B'], 'ataque' => 1.05, 'defensa' => 0.95],
            'pereira' => ['nombre' => 'Deportivo Pereira', 'siglas' => 'PER', 'ciudad' => 'pereira', 'estadio' => 'hernan_ramirez', 'fundacion' => null, 'colores' => ['#FFD100', '#D50032'], 'ataque' => 0.95, 'defensa' => 1.05],
            'pasto' => ['nombre' => 'Deportivo Pasto', 'siglas' => 'PAS', 'ciudad' => 'pasto', 'estadio' => 'libertad', 'fundacion' => null, 'colores' => ['#D50032', '#0033A0'], 'ataque' => 0.90, 'defensa' => 1.00],
            'alianza' => ['nombre' => 'Alianza FC', 'siglas' => 'ALI', 'ciudad' => 'valledupar', 'estadio' => 'pavajeau', 'fundacion' => null, 'colores' => ['#0057B8', '#FFD100'], 'ataque' => 0.90, 'defensa' => 1.10],
            'chico' => ['nombre' => 'Boyacá Chicó', 'siglas' => 'CHI', 'ciudad' => 'tunja', 'estadio' => 'independencia', 'fundacion' => null, 'colores' => ['#003DA5', '#FFFFFF'], 'ataque' => 0.80, 'defensa' => 1.20],
            'llaneros' => ['nombre' => 'Llaneros FC', 'siglas' => 'LLA', 'ciudad' => 'villavicencio', 'estadio' => 'bello_horizonte', 'fundacion' => null, 'colores' => ['#FFFFFF', '#E4002B'], 'ataque' => 0.85, 'defensa' => 1.15],
        ];
    }

    /** Nombres para jugadores y usuarios ficticios. */
    public const NOMBRES = [
        'Juan', 'Carlos', 'Andrés', 'Santiago', 'Sebastián', 'Mateo', 'Daniel', 'Felipe', 'Camilo', 'Julián',
        'Nicolás', 'Diego', 'Alejandro', 'Jhon', 'Brayan', 'Kevin', 'Luis', 'Miguel', 'Óscar', 'Wílmar',
        'Yerson', 'Duván', 'Edwin', 'Fredy', 'Hárold', 'Iván', 'Jorge', 'Leonardo', 'Marlon', 'Stiven',
    ];

    public const NOMBRES_MUJER = [
        'Laura', 'Valentina', 'Daniela', 'Camila', 'María', 'Juliana', 'Natalia', 'Sofía', 'Paula', 'Andrea',
    ];

    public const APELLIDOS = [
        'Rodríguez', 'Gómez', 'González', 'Martínez', 'García', 'López', 'Hernández', 'Sánchez', 'Ramírez', 'Pérez',
        'Torres', 'Díaz', 'Moreno', 'Muñoz', 'Rojas', 'Vargas', 'Castro', 'Jiménez', 'Ortiz', 'Valencia',
        'Mosquera', 'Cuesta', 'Palacios', 'Arboleda', 'Quintero', 'Restrepo', 'Ospina', 'Cardona', 'Mejía', 'Zapata',
        'Murillo', 'Córdoba', 'Rentería', 'Hurtado', 'Banguero', 'Caicedo', 'Ibarguen', 'Londoño', 'Salazar', 'Tabares',
    ];
}
