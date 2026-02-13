<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PropertyType;
use App\Models\TransactionType;

class RegionalTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tablas
        PropertyType::truncate();
        TransactionType::truncate();

        $this->seedPropertyTypes();
        $this->seedTransactionTypes();
    }

    private function seedPropertyTypes(): void
    {
        $data = [
            // INTL - Internacional (Fallback)
            ['INTL', 'casa', 'Casa', 'house', 1],
            ['INTL', 'departamento', 'Departamento', 'apartment', 2],
            ['INTL', 'local', 'Local Comercial', 'commercial', 3],
            ['INTL', 'oficina', 'Oficina', 'office', 4],
            ['INTL', 'terreno', 'Terreno', 'land', 5],
            ['INTL', 'campo', 'Campo', 'farm', 6],
            ['INTL', 'galpon', 'Galpón', 'warehouse', 7],

            // AR - Argentina
            ['AR', 'casa', 'Casa', 'house', 1],
            ['AR', 'departamento', 'Departamento', 'apartment', 2],
            ['AR', 'ph', 'PH (Propiedad Horizontal)', 'townhouse', 3],
            ['AR', 'local', 'Local Comercial', 'commercial', 4],
            ['AR', 'oficina', 'Oficina', 'office', 5],
            ['AR', 'terreno', 'Terreno', 'land', 6],
            ['AR', 'campo', 'Campo', 'farm', 7],
            ['AR', 'galpon', 'Galpón', 'warehouse', 8],
            ['AR', 'cochera', 'Cochera', 'parking', 9],

            // MX - México
            ['MX', 'casa', 'Casa', 'house', 1],
            ['MX', 'departamento', 'Departamento', 'apartment', 2],
            ['MX', 'condominio', 'Condominio', 'condo', 3],
            ['MX', 'local', 'Local Comercial', 'commercial', 4],
            ['MX', 'oficina', 'Oficina', 'office', 5],
            ['MX', 'terreno', 'Terreno', 'land', 6],
            ['MX', 'rancho', 'Rancho', 'farm', 7],
            ['MX', 'bodega', 'Bodega', 'warehouse', 8],

            // CL - Chile
            ['CL', 'casa', 'Casa', 'house', 1],
            ['CL', 'departamento', 'Departamento', 'apartment', 2],
            ['CL', 'local', 'Local Comercial', 'commercial', 3],
            ['CL', 'oficina', 'Oficina', 'office', 4],
            ['CL', 'terreno', 'Terreno', 'land', 5],
            ['CL', 'parcela', 'Parcela', 'farm', 6],
            ['CL', 'bodega', 'Bodega', 'warehouse', 7],
            ['CL', 'estacionamiento', 'Estacionamiento', 'parking', 8],

            // ES - España
            ['ES', 'piso', 'Piso', 'apartment', 1],
            ['ES', 'casa', 'Casa', 'house', 2],
            ['ES', 'chalet', 'Chalet', 'villa', 3],
            ['ES', 'atico', 'Ático', 'penthouse', 4],
            ['ES', 'local', 'Local Comercial', 'commercial', 5],
            ['ES', 'oficina', 'Oficina', 'office', 6],
            ['ES', 'terreno', 'Terreno', 'land', 7],
            ['ES', 'finca', 'Finca', 'farm', 8],
            ['ES', 'nave', 'Nave Industrial', 'warehouse', 9],
            ['ES', 'garaje', 'Garaje', 'parking', 10],

            // CO - Colombia
            ['CO', 'apartamento', 'Apartamento', 'apartment', 1],
            ['CO', 'casa', 'Casa', 'house', 2],
            ['CO', 'local', 'Local Comercial', 'commercial', 3],
            ['CO', 'oficina', 'Oficina', 'office', 4],
            ['CO', 'lote', 'Lote', 'land', 5],
            ['CO', 'finca', 'Finca', 'farm', 6],
            ['CO', 'bodega', 'Bodega', 'warehouse', 7],
            ['CO', 'parqueadero', 'Parqueadero', 'parking', 8],
        ];

        foreach ($data as $item) {
            PropertyType::create([
                'country_code' => $item[0],
                'value' => $item[1],
                'label' => $item[2],
                'value_en' => $item[3],
                'order' => $item[4],
                'is_active' => true,
            ]);
        }
    }

    private function seedTransactionTypes(): void
    {
        $data = [
            // INTL - Internacional (Fallback)
            ['INTL', 'venta', 'Venta', 'sale', 1],
            ['INTL', 'alquiler', 'Alquiler / Renta', 'rent', 2],
            ['INTL', 'alquiler_temporal', 'Alquiler Temporal', 'temporary_rent', 3],

            // AR - Argentina
            ['AR', 'venta', 'Venta', 'sale', 1],
            ['AR', 'alquiler', 'Alquiler', 'rent', 2],
            ['AR', 'alquiler_temporal', 'Alquiler Temporal', 'temporary_rent', 3],

            // MX - México
            ['MX', 'venta', 'Venta', 'sale', 1],
            ['MX', 'renta', 'Renta', 'rent', 2],
            ['MX', 'renta_vacacional', 'Renta Vacacional', 'temporary_rent', 3],

            // CL - Chile
            ['CL', 'venta', 'Venta', 'sale', 1],
            ['CL', 'arriendo', 'Arriendo', 'rent', 2],
            ['CL', 'arriendo_temporal', 'Arriendo Temporal', 'temporary_rent', 3],

            // ES - España
            ['ES', 'venta', 'Venta', 'sale', 1],
            ['ES', 'alquiler', 'Alquiler', 'rent', 2],
            ['ES', 'alquiler_vacacional', 'Alquiler Vacacional', 'temporary_rent', 3],

            // CO - Colombia
            ['CO', 'venta', 'Venta', 'sale', 1],
            ['CO', 'arriendo', 'Arriendo', 'rent', 2],
            ['CO', 'arriendo_temporal', 'Arriendo Temporal', 'temporary_rent', 3],
        ];

        foreach ($data as $item) {
            TransactionType::create([
                'country_code' => $item[0],
                'value' => $item[1],
                'label' => $item[2],
                'value_en' => $item[3],
                'order' => $item[4],
                'is_active' => true,
            ]);
        }
    }
}
