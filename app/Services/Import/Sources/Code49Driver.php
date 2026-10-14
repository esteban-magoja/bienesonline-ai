<?php

namespace App\Services\Import\Sources;

/**
 * Feed XML de Code49 en formato Trovit (https://tudominio.com/exportacao/bienesonline.xml).
 *
 * Las imágenes vienen como <picture_url> directo dentro de <pictures> (sin <picture>).
 */
class Code49Driver extends TrovitFeedDriver
{
    public function key(): string
    {
        return 'code49';
    }

    public function label(): string
    {
        return 'Code49';
    }

    public function description(): string
    {
        return __('import.drivers.code49.description');
    }

    protected function feedUrlPlaceholder(): string
    {
        return 'https://tusitio.com/exportacao/bienesonline.xml';
    }
}
