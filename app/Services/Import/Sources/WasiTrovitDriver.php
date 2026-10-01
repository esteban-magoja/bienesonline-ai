<?php

namespace App\Services\Import\Sources;

use App\Services\Import\Contracts\ImportDriver;
use App\Services\Import\FeedFetcher;
use App\Services\Import\ImportCurrencyNormalizer;
use App\Services\Import\ImportLocationResolver;
use App\Services\Import\ImportSourceException;
use App\Services\Import\ImportTypeResolver;
use Nnjeim\World\Models\Country;
use SimpleXMLElement;

/**
 * Feed XML de Wasi en formato Trovit (https://tudominio.com/xml-trovit.htm).
 */
class WasiTrovitDriver implements ImportDriver
{
    public function __construct(
        private readonly FeedFetcher $fetcher,
        private readonly ImportTypeResolver $types,
        private readonly ImportLocationResolver $locations,
        private readonly ImportCurrencyNormalizer $currencies,
    ) {}

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

    public function fields(): array
    {
        return [
            'country'  => [
                'type'  => 'country',
                'label' => __('import.listings_country'),
            ],
            'feed_url' => [
                'type'        => 'url',
                'label'       => __('import.drivers.wasi.feed_url'),
                'placeholder' => 'https://tusitio.com/xml-trovit.htm',
                'help'        => __('import.drivers.wasi.feed_url_help'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'country'  => ['required', 'string', 'max:100'],
            'feed_url' => ['required', 'url:http,https', 'max:1000'],
        ];
    }

    public function suggestName(array $config): string
    {
        return parse_url($config['feed_url'] ?? '', PHP_URL_HOST) ?: 'Wasi';
    }

    public function fetch(array $config): array
    {
        $xml = $this->fetcher->fetch($config['feed_url'], fn (string $body) => $this->parse($body) !== null);

        $root = $this->parse($xml);
        if ($root === null) {
            throw new ImportSourceException(__('import.feed_invalid'));
        }

        $country = (string) ($config['country'] ?? '');
        $countryCode = $this->types->countryCode($country);
        $defaultCurrency = $this->defaultCurrency($country);

        $ads = [];
        foreach ($root->ad as $ad) {
            $normalized = $this->normalizeAd($ad, $country, $countryCode, $defaultCurrency);
            if ($normalized !== null) {
                $ads[$normalized['id']] = $normalized; // deduplica por id
            }
        }

        return array_values($ads);
    }

    private function parse(string $body): ?SimpleXMLElement
    {
        $body = trim($body);
        if ($body === '' || ! str_contains($body, '<trovit')) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $root = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $root instanceof SimpleXMLElement && $root->getName() === 'trovit' ? $root : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeAd(SimpleXMLElement $ad, string $country, ?string $countryCode, string $defaultCurrency): ?array
    {
        $id = trim((string) $ad->id);
        if ($id === '') {
            return null;
        }

        $currency = $this->currencies->normalize((string) ($ad->price['currency'] ?? ''), $defaultCurrency);

        $images = [];
        foreach ($ad->pictures->picture ?? [] as $picture) {
            $url = trim((string) $picture->picture_url);
            if ($url !== '') {
                $images[] = $url;
            }
        }

        $address = implode(', ', array_filter([
            $this->text($ad->neighborhood),
            $this->text($ad->city_area),
        ]));

        return [
            'id'               => $id,
            'title'            => $this->text($ad->title),
            'description'      => $this->multilineText($ad->content),
            'property_type'    => $this->types->propertyType((string) $ad->property_type, $countryCode),
            'transaction_type' => $this->types->transactionType((string) $ad->type, $countryCode),
            'price'            => $this->number($ad->price) ?? 0,
            'currency'         => $currency,
            'bedrooms'         => (int) ($this->number($ad->rooms) ?? 0),
            'bathrooms'        => (int) ($this->number($ad->bathrooms) ?? 0),
            'parking_spaces'   => (int) ($this->number($ad->parking) ?? 0),
            // Las columnas area/lotsize son enteras y el feed trae decimales (668.92)
            'area'             => (int) round($this->number($ad->floor_area) ?? 0),
            'lotsize'          => ($lot = $this->number($ad->plot_area)) !== null ? (int) round($lot) : null,
            'address'          => $address !== '' ? $address : null,
            'city'             => $this->text($ad->city),
            'state'            => $this->locations->state($this->text($ad->region), $country),
            'country'          => $country,
            'postal_code'      => $this->text($ad->postcode) ?: null,
            'latitude'         => $this->number($ad->latitude),
            'longitude'        => $this->number($ad->longitude),
            'images'           => $images,
        ];
    }

    private function text(mixed $node): string
    {
        $value = html_entity_decode((string) $node, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
    }

    /**
     * Como text(), pero conserva saltos de línea (\n y etiquetas br/p/li) para la descripción.
     */
    private function multilineText(mixed $node): string
    {
        $value = html_entity_decode((string) $node, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6])>#i', "\n", $value) ?? $value;
        $value = str_replace(["\r\n", "\r"], "\n", strip_tags($value));

        $lines = array_map(fn ($line) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line) ?? ''), explode("\n", $value));

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '');
    }

    private function number(mixed $node): int|float|null
    {
        $value = trim((string) $node);
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return $value + 0;
    }

    private function defaultCurrency(string $countryName): string
    {
        $country = Country::where('name', $countryName)->first();

        return $country?->currency['code'] ?? 'USD';
    }
}
