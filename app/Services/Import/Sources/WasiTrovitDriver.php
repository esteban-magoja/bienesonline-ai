<?php

namespace App\Services\Import\Sources;

use SimpleXMLElement;

/**
 * Feed XML de Wasi en formato Trovit (https://tudominio.com/xml-trovit.htm).
 */
class WasiTrovitDriver extends TrovitFeedDriver
{
    public function key(): string
    {
        return 'wasi';
    }

    public function label(): string
    {
        return 'Wasi';
    }

    public function description(): string
    {
        return __('import.drivers.wasi.description');
    }

    protected function feedUrlPlaceholder(): string
    {
        return 'https://tusitio.com/xml-trovit.htm';
    }

    protected function address(SimpleXMLElement $ad): string
    {
        return implode(', ', array_filter([
            $this->text($ad->neighborhood),
            $this->text($ad->city_area),
        ]));
    }
}
