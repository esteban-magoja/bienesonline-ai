<?php

namespace App\Services\Import\Sources;

/**
 * Feed XML estándar de Trovit, para portales/CRMs que exportan en ese formato
 * y no tienen una fuente propia en la lista.
 */
class TrovitDriver extends TrovitFeedDriver
{
    public function key(): string
    {
        return 'trovit';
    }

    public function label(): string
    {
        return 'Trovit';
    }

    public function description(): string
    {
        return __('import.drivers.trovit.description');
    }

    protected function feedUrlPlaceholder(): string
    {
        return 'https://tusitio.com/trovit.xml';
    }
}
