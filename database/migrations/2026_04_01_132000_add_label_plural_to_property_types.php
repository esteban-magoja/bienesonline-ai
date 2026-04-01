<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Activar extensión unaccent para búsquedas sin distinción de acentos
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        Schema::table('property_types', function (Blueprint $table) {
            $table->string('label_plural', 100)->nullable()->after('label');
        });

        // Plurales por value (aplica a todos los países que tengan ese value)
        $plurals = [
            'casa'             => 'Casas',
            'departamento'     => 'Departamentos',
            'local'            => 'Locales',
            'oficina'          => 'Oficinas',
            'terreno'          => 'Terrenos',
            'campo'            => 'Campos',
            'galpon'           => 'Galpones',
            'ph'               => 'PH',
            'cochera'          => 'Cocheras',
            'condominio'       => 'Condominios',
            'rancho'           => 'Ranchos',
            'bodega'           => 'Bodegas',
            'parcela'          => 'Parcelas',
            'estacionamiento'  => 'Estacionamientos',
            'piso'             => 'Pisos',
            'chalet'           => 'Chalets',
            'atico'            => 'Áticos',
            'finca'            => 'Fincas',
            'nave'             => 'Naves Industriales',
            'garaje'           => 'Garajes',
            'apartamento'      => 'Apartamentos',
            'lote'             => 'Lotes',
            'parqueadero'      => 'Parqueaderos',
            // Tipos adicionales (EC, GT, UY, etc.)
            'hacienda'         => 'Haciendas',
            'hotel'            => 'Hoteles',
            'edificio'         => 'Edificios',
            'proyecto'         => 'Proyectos',
            'villa'            => 'Villas',
            'townhouse'        => 'Townhouses',
            'quinta'           => 'Quintas',
        ];

        foreach ($plurals as $value => $plural) {
            DB::table('property_types')
                ->where('value', $value)
                ->update(['label_plural' => $plural]);
        }
    }

    public function down(): void
    {
        Schema::table('property_types', function (Blueprint $table) {
            $table->dropColumn('label_plural');
        });
    }
};
