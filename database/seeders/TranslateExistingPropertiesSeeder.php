<?php

namespace Database\Seeders;

use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TranslateExistingPropertiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Migrando propiedades existentes a formato bilingüe...');

        // Migrar PropertyListings
        $this->migratePropertyListings();

        // Migrar PropertyRequests
        $this->migratePropertyRequests();

        $this->command->info('Migración completada!');
    }

    /**
     * Migrate existing PropertyListings to i18n format.
     */
    private function migratePropertyListings(): void
    {
        $listings = PropertyListing::whereNull('title_i18n')->get();

        $this->command->info("Migrando {$listings->count()} property listings...");

        foreach ($listings as $listing) {
            // Migrar título
            if ($listing->title && empty($listing->title_i18n)) {
                $listing->title_i18n = [
                    'es' => $listing->title,
                    'en' => $this->translateToEnglish($listing->title)
                ];
            }

            // Migrar descripción
            if ($listing->description && empty($listing->description_i18n)) {
                $listing->description_i18n = [
                    'es' => $listing->description,
                    'en' => $this->translateToEnglish($listing->description)
                ];
            }

            // Migrar features (si existe el campo)
            if (isset($listing->features) && $listing->features && empty($listing->features_i18n)) {
                $listing->features_i18n = [
                    'es' => $listing->features,
                    'en' => $this->translateToEnglish($listing->features)
                ];
            }

            // Migrar location_details (si existe el campo)
            if (isset($listing->location_details) && $listing->location_details && empty($listing->location_details_i18n)) {
                $listing->location_details_i18n = [
                    'es' => $listing->location_details,
                    'en' => $this->translateToEnglish($listing->location_details)
                ];
            }

            $listing->save();
        }

        $this->command->info("✓ {$listings->count()} property listings migrados");
    }

    /**
     * Migrate existing PropertyRequests to i18n format.
     */
    private function migratePropertyRequests(): void
    {
        $requests = PropertyRequest::whereNull('title_i18n')->get();

        $this->command->info("Migrando {$requests->count()} property requests...");

        foreach ($requests as $request) {
            // Migrar título
            if ($request->title && empty($request->title_i18n)) {
                $request->title_i18n = [
                    'es' => $request->title,
                    'en' => $this->translateToEnglish($request->title)
                ];
            }

            // Migrar descripción
            if ($request->description && empty($request->description_i18n)) {
                $request->description_i18n = [
                    'es' => $request->description,
                    'en' => $this->translateToEnglish($request->description)
                ];
            }

            // Migrar requirements (si existe el campo)
            if (isset($request->requirements) && $request->requirements && empty($request->requirements_i18n)) {
                $request->requirements_i18n = [
                    'es' => $request->requirements,
                    'en' => $this->translateToEnglish($request->requirements)
                ];
            }

            $request->save();
        }

        $this->command->info("✓ {$requests->count()} property requests migrados");
    }

    /**
     * Simple translation helper (placeholder for now).
     * In production, you might want to use a translation API.
     *
     * @param string $text
     * @return string
     */
    private function translateToEnglish(string $text): string
    {
        // Traducciones básicas para términos comunes
        $translations = [
            'Casa' => 'House',
            'Departamento' => 'Apartment',
            'Terreno' => 'Land',
            'Local Comercial' => 'Commercial Space',
            'Oficina' => 'Office',
            'Moderna' => 'Modern',
            'Familiar' => 'Family',
            'Lujo' => 'Luxury',
            'Espaciosa' => 'Spacious',
            'Acogedora' => 'Cozy',
            'Luminoso' => 'Bright',
            'Céntrico' => 'Downtown',
            'Amplio' => 'Spacious',
            'en Venta' => 'for Sale',
            'Residencial' => 'Residential',
            'Comercial' => 'Commercial',
            'Esquinero' => 'Corner',
            'Ejecutiva' => 'Executive',
            'Excelente propiedad' => 'Excellent property',
            'ubicada en' => 'located in',
            'zona privilegiada' => 'prime area',
            'Cuenta con' => 'It has',
            'todas las comodidades' => 'all the amenities',
            'necesarias para' => 'necessary for',
            'una vida confortable' => 'a comfortable life',
            'Ideal para' => 'Ideal for',
            'familias' => 'families',
            'inversores' => 'investors',
            'Cerca de' => 'Close to',
            'escuelas' => 'schools',
            'supermercados' => 'supermarkets',
            'transporte público' => 'public transport',
            'Zona tranquila' => 'Quiet area',
            'segura' => 'safe'
        ];

        $translated = $text;
        foreach ($translations as $es => $en) {
            $translated = str_replace($es, $en, $translated);
        }

        // Si no se tradujo nada, agregar un prefijo indicando que es placeholder
        if ($translated === $text) {
            $translated = "[EN] " . $text;
        }

        return $translated;
    }
}
