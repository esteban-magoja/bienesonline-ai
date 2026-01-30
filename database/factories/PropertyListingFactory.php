<?php

namespace Database\Factories;

use App\Models\PropertyListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyListingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PropertyListing::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $propertyTypes = ['house', 'apartment', 'land', 'commercial', 'office'];
        $transactionTypes = ['sale', 'rent'];
        $currencies = ['USD', 'ARS'];
        $conditions = ['new', 'excellent', 'good', 'to_refurbish'];

        // Títulos bilingües por tipo de propiedad
        $titleTemplates = [
            'house' => [
                'es' => ['Casa Moderna', 'Casa Familiar', 'Casa de Lujo', 'Casa Espaciosa', 'Casa Acogedora'],
                'en' => ['Modern House', 'Family House', 'Luxury House', 'Spacious House', 'Cozy House']
            ],
            'apartment' => [
                'es' => ['Departamento Luminoso', 'Departamento Céntrico', 'Departamento de Lujo', 'Departamento Amplio'],
                'en' => ['Bright Apartment', 'Downtown Apartment', 'Luxury Apartment', 'Spacious Apartment']
            ],
            'land' => [
                'es' => ['Terreno en Venta', 'Lote Residencial', 'Terreno Comercial', 'Lote Esquinero'],
                'en' => ['Land for Sale', 'Residential Lot', 'Commercial Land', 'Corner Lot']
            ],
            'commercial' => [
                'es' => ['Local Comercial', 'Oficina Comercial', 'Espacio Comercial', 'Local Céntrico'],
                'en' => ['Commercial Space', 'Commercial Office', 'Retail Space', 'Downtown Commercial']
            ],
            'office' => [
                'es' => ['Oficina Moderna', 'Oficina Ejecutiva', 'Oficina Amplia', 'Oficina Céntrica'],
                'en' => ['Modern Office', 'Executive Office', 'Spacious Office', 'Downtown Office']
            ]
        ];

        $propertyType = $this->faker->randomElement($propertyTypes);
        $transactionType = $this->faker->randomElement($transactionTypes);
        
        // Seleccionar título aleatorio del tipo de propiedad
        $titleIndex = array_rand($titleTemplates[$propertyType]['es']);
        $titleEs = $titleTemplates[$propertyType]['es'][$titleIndex];
        $titleEn = $titleTemplates[$propertyType]['en'][$titleIndex];

        // Generar descripciones bilingües
        $descriptionEs = "Excelente propiedad ubicada en una zona privilegiada. " .
                        "Cuenta con todas las comodidades necesarias para una vida confortable. " .
                        "Ideal para familias o inversores.";
        
        $descriptionEn = "Excellent property located in a prime area. " .
                        "It has all the necessary amenities for a comfortable life. " .
                        "Ideal for families or investors.";

        // Generar características bilingües
        $featuresEs = [
            'Cocina equipada',
            'Calefacción central',
            'Aire acondicionado',
            'Balcón',
            'Jardín',
            'Piscina'
        ];

        $featuresEn = [
            'Equipped kitchen',
            'Central heating',
            'Air conditioning',
            'Balcony',
            'Garden',
            'Swimming pool'
        ];

        $selectedFeaturesEs = $this->faker->randomElements($featuresEs, $this->faker->numberBetween(2, 4));
        $selectedFeaturesEn = array_map(function($feature) use ($featuresEs, $featuresEn) {
            $index = array_search($feature, $featuresEs);
            return $featuresEn[$index];
        }, $selectedFeaturesEs);

        // Detalles de ubicación bilingües
        $locationDetailsEs = "Cerca de escuelas, supermercados y transporte público. Zona tranquila y segura.";
        $locationDetailsEn = "Close to schools, supermarkets and public transport. Quiet and safe area.";

        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? 1,
            'title' => $titleEs, // Campo legacy
            'description' => $descriptionEs, // Campo legacy
            'property_type' => $propertyType,
            'transaction_type' => $transactionType,
            'price' => $this->faker->randomFloat(2, 50000, 1000000),
            'currency' => $this->faker->randomElement($currencies),
            'bedrooms' => $this->faker->numberBetween(1, 5),
            'bathrooms' => $this->faker->numberBetween(1, 4),
            'parking_spaces' => $this->faker->numberBetween(0, 3),
            'area' => $this->faker->numberBetween(50, 500),
            'lotsize' => $this->faker->numberBetween(100, 1000),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'country' => 'Argentina',
            'postal_code' => $this->faker->postcode(),
            'latitude' => $this->faker->latitude(-55, -21),
            'longitude' => $this->faker->longitude(-73, -53),
            'conditions' => $this->faker->randomElement($conditions),
            'is_featured' => $this->faker->boolean(20),
            'is_active' => $this->faker->boolean(90),
            // Campos i18n
            'title_i18n' => [
                'es' => $titleEs,
                'en' => $titleEn
            ],
            'description_i18n' => [
                'es' => $descriptionEs,
                'en' => $descriptionEn
            ],
            'features_i18n' => [
                'es' => implode(', ', $selectedFeaturesEs),
                'en' => implode(', ', $selectedFeaturesEn)
            ],
            'location_details_i18n' => [
                'es' => $locationDetailsEs,
                'en' => $locationDetailsEn
            ]
        ];
    }
}
