<?php

namespace App\Helpers;

/**
 * Provincias de Chile → región. Los feeds de inmobiliarias a veces informan la provincia
 * donde la plataforma usa la región.
 */
class ChileRegions
{
    /** Provincia (sin acentos, minúsculas) → nombre de región. */
    private const PROVINCE_TO_REGION = [
        // Arica y Parinacota
        'arica'                       => 'Arica y Parinacota',
        'parinacota'                  => 'Arica y Parinacota',
        // Tarapacá
        'el tamarugal'                => 'Tarapacá',
        'iquique'                     => 'Tarapacá',
        // Antofagasta
        'antofagasta'                 => 'Antofagasta',
        'el loa'                      => 'Antofagasta',
        'tocopilla'                   => 'Antofagasta',
        // Atacama
        'chanaral'                    => 'Atacama',
        'copiapo'                     => 'Atacama',
        'huasco'                      => 'Atacama',
        // Coquimbo
        'elqui'                       => 'Coquimbo',
        'limari'                      => 'Coquimbo',
        'choapa'                      => 'Coquimbo',
        // Valparaíso
        'isla de pascua'              => 'Valparaíso',
        'petorca'                     => 'Valparaíso',
        'valparaiso'                  => 'Valparaíso',
        'san felipe de aconcagua'     => 'Valparaíso',
        'los andes'                   => 'Valparaíso',
        'quillota'                    => 'Valparaíso',
        'san antonio'                 => 'Valparaíso',
        'marga marga'                 => 'Valparaíso',
        // Metropolitana de Santiago
        'santiago'                    => 'Metropolitana de Santiago',
        'cordillera'                  => 'Metropolitana de Santiago',
        'chacabuco'                   => 'Metropolitana de Santiago',
        'maipo'                       => 'Metropolitana de Santiago',
        'melipilla'                   => 'Metropolitana de Santiago',
        'talagante'                   => 'Metropolitana de Santiago',
        // Libertador General Bernardo O'Higgins
        'cachapoal'                   => "Libertador General Bernardo O'Higgins",
        'cardenal caro'               => "Libertador General Bernardo O'Higgins",
        'colchagua'                   => "Libertador General Bernardo O'Higgins",
        // Maule
        'curico'                      => 'Maule',
        'talca'                       => 'Maule',
        'cauquenes'                   => 'Maule',
        'linares'                     => 'Maule',
        // Ñuble
        'itata'                       => 'Ñuble',
        'diguillin'                   => 'Ñuble',
        'punilla'                     => 'Ñuble',
        // Biobío
        'arauco'                      => 'Biobío',
        'biobio'                      => 'Biobío',
        'concepcion'                  => 'Biobío',
        'nuble'                       => 'Biobío',
        // La Araucanía
        'cautin'                      => 'La Araucanía',
        'malleco'                     => 'La Araucanía',
        // Los Ríos
        'valdivia'                    => 'Los Ríos',
        'ranco'                       => 'Los Ríos',
        // Los Lagos
        'llanquihue'                  => 'Los Lagos',
        'osorno'                      => 'Los Lagos',
        'palena'                      => 'Los Lagos',
        'chiloe'                      => 'Los Lagos',
        // Aysén del General Carlos Ibáñez del Campo
        'coihaique'                   => 'Aysén del General Carlos Ibáñez del Campo',
        'general carrera'             => 'Aysén del General Carlos Ibáñez del Campo',
        'capitan prat'                => 'Aysén del General Carlos Ibáñez del Campo',
        'aisen'                       => 'Aysén del General Carlos Ibáñez del Campo',
        'aysen'                       => 'Aysén del General Carlos Ibáñez del Campo',
        // Magallanes y de la Antártica Chilena
        'magallanes'                  => 'Magallanes y de la Antártica Chilena',
        'tierra del fuego'            => 'Magallanes y de la Antártica Chilena',
        'ultima esperanza'            => 'Magallanes y de la Antártica Chilena',
    ];

    /**
     * Región correspondiente a una provincia, o null si no se reconoce.
     */
    public static function fromProvince(string $province): ?string
    {
        return self::PROVINCE_TO_REGION[PlaceName::normalize($province)] ?? null;
    }
}
