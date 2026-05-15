<?php

namespace App\Console\Commands;

use App\Events\PropertyRequestCreated;
use App\Models\PropertyRequest;
use App\Models\PropertyType;
use App\Models\TransactionType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Nnjeim\World\Models\Country;

class ImportLegacyRequests extends Command
{
    protected $signature = 'import:legacy-requests
                            {file : Ruta al archivo JSON. El nombre debe ser el código ISO2 del país (ej: GT.json)}
                            {--user=1 : ID del usuario al que se asociarán las solicitudes}
                            {--limit= : Importar solo los primeros N registros (útil para pruebas)}
                            {--skip-embeddings : Omitir la generación de embeddings (Fase 2)}
                            {--only-embeddings : Solo generar embeddings para registros pendientes (omite Fase 1)}
                            {--chunk=50 : Registros por lote al generar embeddings}
                            {--skip-whatsapp : No enviar notificaciones de WhatsApp a dueños de anuncios compatibles}
                            {--dry-run : Simula la importación sin guardar datos}';

    protected $description = 'Importa solicitudes (pedidos) desde un archivo JSON del proyecto legacy';

    /** Tipos de propiedad cargados desde la BD para el país del archivo. */
    protected \Illuminate\Database\Eloquent\Collection $propertyTypes;

    /** Tipos de transacción cargados desde la BD para el país del archivo. */
    protected \Illuminate\Database\Eloquent\Collection $transactionTypes;

    /** Valores no reconocidos durante la importación (para reporte final). */
    protected array $unmappedPropertyTypes    = [];
    protected array $unmappedTransactionTypes = [];

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $userId   = (int) $this->option('user');
        $dryRun   = $this->option('dry-run');
        $limit    = $this->option('limit') ? (int) $this->option('limit') : null;

        if (!str_starts_with($filePath, '/')) {
            $filePath = base_path($filePath);
        }

        if (!file_exists($filePath)) {
            $this->error("Archivo no encontrado: {$filePath}");
            return self::FAILURE;
        }

        // El nombre del archivo ES el código ISO2 (ej: GT.json → 'GT')
        $countryCode = strtoupper(pathinfo($filePath, PATHINFO_FILENAME));
        $countryName = $this->resolveCountryName($countryCode);

        // Cargar tipos desde la BD (con fallback automático a INTL)
        $this->propertyTypes    = PropertyType::getByCountry($countryCode);
        $this->transactionTypes = TransactionType::getByCountry($countryCode);

        $this->info("País       : {$countryName} ({$countryCode})");
        $this->info("Usuario ID : {$userId}");
        $this->line("Tipos de inmueble   : " . $this->propertyTypes->pluck('label')->implode(', '));
        $this->line("Tipos de operación  : " . $this->transactionTypes->pluck('label')->implode(', '));
        $this->newLine();

        // --- Fase 1: Importar registros ---
        if (!$this->option('only-embeddings')) {
            $result = $this->runImport($filePath, $userId, $countryName, $dryRun, $limit);
            if ($result !== self::SUCCESS) {
                return $result;
            }
        }

        // --- Fase 2: Generar embeddings ---
        if (!$dryRun && !$this->option('skip-embeddings')) {
            $this->runEmbeddings($countryName, (int) $this->option('chunk'));
        }

        return self::SUCCESS;
    }

    protected function runImport(string $filePath, int $userId, string $countryName, bool $dryRun, ?int $limit): int
    {
        $records      = $this->parseJsonFile($filePath);
        $skipWhatsapp = $this->option('skip-whatsapp');

        if ($records === null) {
            $this->error('No se pudo parsear el archivo JSON o no contiene datos de tabla.');
            return self::FAILURE;
        }

        if ($limit) {
            $records = array_slice($records, 0, $limit);
        }

        $total   = count($records);
        $created = 0;
        $skipped = 0;
        $errors  = 0;

        $this->info("Fase 1 — Importando {$total} registros" . ($dryRun ? ' (dry-run)' : '') . '...');

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($records as $row) {
            try {
                $mapped = $this->mapRecord($row, $userId, $countryName, strtoupper(pathinfo($filePath, PATHINFO_FILENAME)));

                if (!$dryRun) {
                    // Detectar duplicados por combinación de campos únicos del registro.
                    // No se usa created_at porque se guarda con la fecha actual (not legacy).
                    $exists = DB::table('property_requests')
                        ->where('client_email',     $mapped['client_email'])
                        ->where('property_type',    $mapped['property_type'])
                        ->where('transaction_type', $mapped['transaction_type'])
                        ->where('city',             $mapped['city'])
                        ->where('state',            $mapped['state'])
                        ->where('country',          $mapped['country'])
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $insertedId      = DB::table('property_requests')->insertGetId($mapped);
                    $propertyRequest = PropertyRequest::find($insertedId);

                    if ($propertyRequest && !$skipWhatsapp) {
                        event(new PropertyRequestCreated($propertyRequest));
                    }
                }

                $created++;
            } catch (\Exception $e) {
                $errors++;
                Log::warning('ImportLegacyRequests: error al importar solicitud', [
                    'row'   => $row,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $suffix = $skipWhatsapp ? '' : ' (notificaciones WhatsApp encoladas)';
        $this->info("   ✅ Importados: {$created}  ⏭️  Saltados (duplicados): {$skipped}  ❌ Errores: {$errors}{$suffix}");

        if ($this->unmappedPropertyTypes) {
            $unique = array_unique($this->unmappedPropertyTypes);
            $this->warn("   ⚠️  Tipos de inmueble no reconocidos (fallback aplicado): " . implode(', ', $unique));
            $this->warn("      Considera agregarlos en admin/country-types.");
        }

        if ($this->unmappedTransactionTypes) {
            $unique = array_unique($this->unmappedTransactionTypes);
            $this->warn("   ⚠️  Tipos de operación no reconocidos (fallback aplicado): " . implode(', ', $unique));
        }

        $this->newLine();
        return self::SUCCESS;
    }

    protected function runEmbeddings(string $countryName, int $chunkSize): void
    {
        $pending = \App\Models\PropertyRequest::where('country', $countryName)
            ->whereNull('embedding')
            ->count();

        if ($pending === 0) {
            $this->info('Fase 2 — Sin embeddings pendientes.');
            return;
        }

        $this->info("Fase 2 — Generando embeddings para {$pending} solicitudes...");

        $bar      = $this->output->createProgressBar($pending);
        $bar->start();

        $generated = 0;
        $failed    = 0;
        $model     = config('openai.embeddings_model', 'text-embedding-3-small');
        $client    = \OpenAI::client(config('openai.api_key'));

        \App\Models\PropertyRequest::where('country', $countryName)
            ->whereNull('embedding')
            ->chunkById($chunkSize, function ($requests) use ($client, $model, $bar, &$generated, &$failed) {
                foreach ($requests as $request) {
                    try {
                        $text = implode(' ', array_filter([
                            $request->title,
                            $request->description,
                            $request->property_type,
                            $request->transaction_type,
                            $request->city,
                            $request->state,
                            $request->country,
                        ]));

                        $response  = $client->embeddings()->create(['model' => $model, 'input' => $text]);
                        $vector    = new \Pgvector\Laravel\Vector($response->embeddings[0]->embedding);

                        \App\Models\PropertyRequest::where('id', $request->id)
                            ->update(['embedding' => $vector]);

                        $generated++;
                    } catch (\Exception $e) {
                        $failed++;
                        Log::warning('ImportLegacyRequests: error generando embedding', [
                            'request_id' => $request->id,
                            'error'      => $e->getMessage(),
                        ]);
                    }

                    $bar->advance();
                }

                // Pausa entre chunks para respetar rate limits de OpenAI
                sleep(1);
            });

        $bar->finish();
        $this->newLine();
        $this->info("   ✅ Embeddings generados: {$generated}  ❌ Fallidos: {$failed}");

        if ($failed > 0) {
            $this->warn("   Los fallidos quedaron con embedding=NULL. Podés reintentar con --only-embeddings.");
        }
    }

    /**
     * Parsea el JSON exportado por PHPMyAdmin.
     * Formato: [{type:header,...}, {type:database,...}, {type:table, data:[...]}]
     */
    protected function parseJsonFile(string $path): ?array
    {
        $decoded = json_decode(file_get_contents($path), true);

        if (!is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $item) {
            if (isset($item['type']) && $item['type'] === 'table' && isset($item['data'])) {
                return $item['data'];
            }
        }

        return null;
    }

    /**
     * Obtiene el nombre del país a partir del código ISO2 usando la tabla del paquete world.
     */
    protected function resolveCountryName(string $iso2): string
    {
        $country = Country::where('iso2', $iso2)->first();

        if (!$country) {
            $this->warn("Código ISO2 '{$iso2}' no encontrado en la BD. Se usará el código como nombre.");
            return $iso2;
        }

        return $country->name;
    }

    /**
     * Mapea un registro legacy al formato de PropertyRequest.
     */
    protected function mapRecord(array $row, int $userId, string $countryName, string $countryCode): array
    {
        $tipoInmueble  = $row['tipo_inmueble'] ?? '';
        $tipoOperacion = $row['tipo_operacion'] ?? '';
        $provincia     = $row['provincia_inmueble'] ?? null;
        $localidad     = $row['localidad_inmueble'] ?? null;
        $fecha         = $row['fecha'] ?? null;

        $propertyType    = $this->resolvePropertyType($tipoInmueble, $countryCode);
        $transactionType = $this->resolveTransactionType($tipoOperacion, $countryCode);

        $record = [
            'user_id'          => $userId,
            'client_name'      => $row['nombre'] ?? null,
            'client_email'     => $row['email'] ?? null,
            'client_phone'     => $row['telefono'] ?? null,
            'title'            => $this->buildTitle($tipoInmueble, $tipoOperacion, $localidad, $provincia),
            'description'      => $this->buildDescription($tipoInmueble, $tipoOperacion, $localidad, $provincia, $countryName),
            'property_type'    => $propertyType,
            'transaction_type' => $transactionType,
            'min_budget'       => null,
            'max_budget'       => 0,
            'currency'         => 'USD',
            'city'             => $localidad,
            'state'            => $this->resolveState($provincia ?? '', $countryName),
            'country'          => $countryName,
            'is_active'        => true,
            'expires_at'       => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        return $record;
    }

    /**
     * Resuelve el property_type buscando el label legacy en los tipos cargados desde la BD.
     *
     * Estrategia:
     *  1. Busca por `label` exacto (case-insensitive) en los tipos del país
     *  2. Busca por `value` exacto (case-insensitive) en los tipos del país
     *  3. Busca globalmente por `label` en todas las filas para obtener `value_en`,
     *     luego localiza el `value` equivalente en los tipos del país
     *  4. Fallback: primer tipo del país (normalmente 'casa' u INTL equiv.)
     */
    protected function resolvePropertyType(string $legacyValue, string $countryCode): string
    {
        $normalized = strtolower(trim($legacyValue));

        // 1 y 2: buscar por label o value en los tipos del país/INTL ya cargados
        $match = $this->propertyTypes->first(function ($type) use ($normalized) {
            return strtolower($type->label) === $normalized
                || strtolower($type->value) === $normalized;
        });

        if ($match) {
            return $match->value;
        }

        // 3: buscar globalmente por label para obtener value_en y encontrar equivalente local
        $valueEn = PropertyType::whereRaw('LOWER(label) = ?', [$normalized])->value('value_en')
            ?? PropertyType::whereRaw('LOWER(value) = ?', [$normalized])->value('value_en');

        if ($valueEn) {
            $localMatch = $this->propertyTypes->first(fn($t) => $t->value_en === $valueEn);
            if ($localMatch) {
                return $localMatch->value;
            }
        }

        // 4: fallback al primer tipo disponible
        $this->unmappedPropertyTypes[] = $legacyValue;
        return $this->propertyTypes->first()?->value ?? 'casa';
    }

    /**
     * Resuelve el transaction_type con la misma estrategia que resolvePropertyType.
     */
    protected function resolveTransactionType(string $legacyValue, string $countryCode): string
    {
        $normalized = strtolower(trim($legacyValue));

        $match = $this->transactionTypes->first(function ($type) use ($normalized) {
            return strtolower($type->label) === $normalized
                || strtolower($type->value) === $normalized;
        });

        if ($match) {
            return $match->value;
        }

        $valueEn = TransactionType::whereRaw('LOWER(label) = ?', [$normalized])->value('value_en')
            ?? TransactionType::whereRaw('LOWER(value) = ?', [$normalized])->value('value_en');

        if ($valueEn) {
            $localMatch = $this->transactionTypes->first(fn($t) => $t->value_en === $valueEn);
            if ($localMatch) {
                return $localMatch->value;
            }
        }

        $this->unmappedTransactionTypes[] = $legacyValue;
        return $this->transactionTypes->first()?->value ?? 'venta';
    }

    protected function buildTitle(string $tipo, string $operacion, ?string $ciudad, ?string $provincia): string
    {
        $parts = [Str::ucfirst(strtolower($tipo)), 'en', Str::ucfirst(strtolower($operacion))];

        if ($ciudad) {
            $parts[] = 'en ' . $ciudad;
        } elseif ($provincia) {
            $parts[] = 'en ' . $provincia;
        }

        return implode(' ', $parts);
    }

    protected function buildDescription(string $tipo, string $operacion, ?string $ciudad, ?string $provincia, string $country): string
    {
        $ubicacion = implode(', ', array_filter([$ciudad, $provincia, $country]));
        return "Solicitud de {$tipo} en {$operacion} en {$ubicacion}.";
    }

    /**
     * Para Chile convierte la provincia (campo legacy) a la Región correspondiente.
     * Para el resto de países devuelve el valor sin modificar.
     */
    protected function resolveState(string $state, string $country): string
    {
        if (strtolower(trim($country)) !== 'chile') {
            return $state;
        }

        return $this->chileProvinceToRegion($state) ?? $state;
    }

    /**
     * Devuelve la Región chilena para una provincia dada, o null si no se reconoce.
     */
    protected function chileProvinceToRegion(string $province): ?string
    {
        $map = [
            // Arica y Parinacota
            'arica'                                => 'Arica y Parinacota',
            'parinacota'                           => 'Arica y Parinacota',
            // Tarapacá
            'el tamarugal'                         => 'Tarapacá',
            'iquique'                              => 'Tarapacá',
            // Antofagasta
            'antofagasta'                          => 'Antofagasta',
            'el loa'                               => 'Antofagasta',
            'tocopilla'                            => 'Antofagasta',
            // Atacama
            'chanaral'                             => 'Atacama',
            'copiapo'                              => 'Atacama',
            'huasco'                               => 'Atacama',
            // Coquimbo
            'elqui'                                => 'Coquimbo',
            'limari'                               => 'Coquimbo',
            'choapa'                               => 'Coquimbo',
            // Valparaíso
            'isla de pascua'                       => 'Valparaíso',
            'petorca'                              => 'Valparaíso',
            'valparaiso'                           => 'Valparaíso',
            'san felipe de aconcagua'              => 'Valparaíso',
            'los andes'                            => 'Valparaíso',
            'quillota'                             => 'Valparaíso',
            'san antonio'                          => 'Valparaíso',
            'marga marga'                          => 'Valparaíso',
            // Metropolitana de Santiago
            'santiago'                             => 'Metropolitana de Santiago',
            'cordillera'                           => 'Metropolitana de Santiago',
            'chacabuco'                            => 'Metropolitana de Santiago',
            'maipo'                                => 'Metropolitana de Santiago',
            'melipilla'                            => 'Metropolitana de Santiago',
            'talagante'                            => 'Metropolitana de Santiago',
            // Libertador General Bernardo O'Higgins
            'cachapoal'                            => "Libertador General Bernardo O'Higgins",
            'cardenal caro'                        => "Libertador General Bernardo O'Higgins",
            'colchagua'                            => "Libertador General Bernardo O'Higgins",
            // Maule
            'curico'                               => 'Maule',
            'talca'                                => 'Maule',
            'cauquenes'                            => 'Maule',
            'linares'                              => 'Maule',
            // Ñuble
            'itata'                                => 'Ñuble',
            'diguillin'                            => 'Ñuble',
            'punilla'                              => 'Ñuble',
            // Biobío
            'arauco'                               => 'Biobío',
            'biobio'                               => 'Biobío',
            'concepcion'                           => 'Biobío',
            'nuble'                                => 'Biobío',
            // La Araucanía
            'cautin'                               => 'La Araucanía',
            'malleco'                              => 'La Araucanía',
            // Los Ríos
            'valdivia'                             => 'Los Ríos',
            'ranco'                                => 'Los Ríos',
            // Los Lagos
            'llanquihue'                           => 'Los Lagos',
            'osorno'                               => 'Los Lagos',
            'palena'                               => 'Los Lagos',
            'chiloe'                               => 'Los Lagos',
            // Aysén del General Carlos Ibáñez del Campo
            'coihaique'                            => 'Aysén del General Carlos Ibáñez del Campo',
            'general carrera'                      => 'Aysén del General Carlos Ibáñez del Campo',
            'capitan prat'                         => 'Aysén del General Carlos Ibáñez del Campo',
            'aisen'                                => 'Aysén del General Carlos Ibáñez del Campo',
            'aysen'                                => 'Aysén del General Carlos Ibáñez del Campo',
            // Magallanes y de la Antártica Chilena
            'magallanes'                           => 'Magallanes y de la Antártica Chilena',
            'tierra del fuego'                     => 'Magallanes y de la Antártica Chilena',
            'ultima esperanza'                     => 'Magallanes y de la Antártica Chilena',
        ];

        $normalized = $this->normalizeString($province);

        foreach ($map as $key => $region) {
            if ($this->normalizeString($key) === $normalized) {
                return $region;
            }
        }

        Log::info('ImportLegacyRequests: provincia chilena no reconocida, se guarda el valor original', [
            'province' => $province,
        ]);

        return null;
    }

    protected function normalizeString(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return $value;
    }
}
