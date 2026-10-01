# Importación de anuncios desde fuentes externas (Wasi y futuras)

Sistema **independiente** de la importación legacy de Bienesonline (`ImportController` / `ProcessImportChunkJob`, que desaparecerá en unas semanas). Permite que un usuario **premium** conecte una fuente externa, importe sus anuncios y los mantenga **sincronizados automáticamente**.

Fuente disponible hoy: **Wasi** (feed XML formato Trovit). Se agregarán más, cada una con sus propios datos de configuración y su propio formato (XML, JSON, etc.).

## Uso (usuario)

1. `/dashboard/imports` ("Importar anuncios" en el sidebar). Usuarios sin premium ven el aviso de suscripción + checkout (mismo patrón que `property-listings/create`).
2. Elige la fuente. El formulario se arma con los campos que pide esa fuente (Wasi: país + URL del feed).
3. Al conectar se descarga el feed para validarlo (si no responde o no es válido, no se guarda nada) y arranca la importación inicial en segundo plano.
4. Cada fuente conectada muestra: anuncios importados, última sincronización, frecuencia, resultado del último proceso y botones **Sincronizar ahora / Pausar / Desconectar**.
5. Desconectar **conserva** los anuncios ya importados (quedan desvinculados y dejan de sincronizarse).

Límites: 5 fuentes por usuario (`IMPORT_MAX_SOURCES_PER_USER`), 2000 anuncios por feed.

## Arquitectura

```
app/Services/Import/
  Contracts/ImportDriver.php      ← contrato que implementa cada fuente
  ImportSourceRegistry.php        ← lista de drivers (config/import.php → 'drivers')
  Sources/WasiTrovitDriver.php    ← fuente Wasi
  FeedFetcher.php                 ← descarga segura (SSRF, reintentos, cache-buster)
  ImportTypeResolver.php          ← tipo de inmueble / operación → valores regionales
  ImportLocationResolver.php      ← estado/región → nombre oficial (tabla states)
  ImportCurrencyNormalizer.php    ← UF→CLF, U$D→USD, vacío→moneda del país
  ListingImporter.php             ← crea/actualiza un anuncio (merge de 3 vías) + imágenes
  ImportSourceRunner.php          ← start / complete / markFailed / desactivar ausentes
  ImportSourceException.php       ← errores controlados (mensaje apto para el usuario)
app/Jobs/FetchSourceFeedJob.php            ← descarga + guarda items pendientes
app/Jobs/ProcessSourceImportChunkJob.php   ← procesa lotes (se encadena a sí mismo)
app/Console/Commands/SyncImportSources.php ← `imports:sync`
app/Http/Controllers/ImportSourceController.php
app/Models/ImportSource.php
app/Helpers/ChileRegions.php, PlaceName.php
resources/themes/anchor/pages/dashboard/imports/index.blade.php
```

Reutiliza `import_jobs` (progreso) e `import_listing_items` (items pendientes) del sistema legacy, sin modificar su lógica.

### Flujo

```
conectar / "Sincronizar ahora" / imports:sync
   └─ ImportSourceRunner::start()  → crea ImportJob (pending) [null si ya hay uno en curso o el dueño no es premium]
        └─ FetchSourceFeedJob      → driver->fetch() → inserta items pendientes → total_listings
             └─ ProcessSourceImportChunkJob (lotes de IMPORT_CHUNK_SIZE, cada chunk despacha el siguiente)
                  └─ ListingImporter::import() por item (created | updated | unchanged)
                  └─ sin pendientes → ImportSourceRunner::complete()
                       └─ desactiva ausentes, actualiza la fuente (last_synced_at, estado)
```

La UI hace polling a `GET /dashboard/import/status/{jobId}` (ruta existente del legacy, filtrada por usuario).

## Reglas de sincronización

- **Identidad del anuncio:** `import_source_id` + `external_id` (el `<id>` del feed). `property_listings.source` = clave del driver (`wasi`).
- **Merge de tres vías por campo** (`ListingImporter::FIELDS`): el valor nuevo de la fuente se aplica solo si la fuente lo cambió respecto de la última importación (`import_data`) **y** el usuario no editó ese campo a mano. Campos editados por el usuario no se pisan.
- **Embeddings:** los regenera el `PropertyListingObserver` solo si cambian título, descripción, dirección, ciudad o estado. Anuncios nuevos disparan matching/IndexNow como cualquier anuncio creado.
- **Anuncio ausente del feed:** suma 1 a `import_missing_count`; al llegar a `IMPORT_MISSING_TOLERANCE` (2) se desactiva (`is_active=false`). Si reaparece y lo había desactivado la importación, se reactiva. Si el usuario lo desactivó a mano **no** se reactiva.
- **Feed vacío o roto:** la corrida falla y **no se desactiva nada**.
- **Imágenes:** se comparan por `property_images.source_url`; se descargan solo las nuevas y se borran las que salieron del feed. Las subidas manuales (`source_url` NULL) no se tocan. `image_url` se guarda como ruta relativa (`/storage/...`), sin depender de `APP_URL`.
- **Fallos consecutivos:** tras `IMPORT_MAX_CONSECUTIVE_FAILURES` (5) la fuente pasa a `status=error` y el scheduler deja de sincronizarla; "Sincronizar ahora" la reactiva.
- **Premium:** `ImportSourceRunner::start()` no encola nada si el dueño ya no es premium (frena también los syncs automáticos). Conexiones y anuncios se conservan.

## Normalización de datos

| Dato | Cómo se normaliza |
|---|---|
| Operación (`For Rent`, `For Sale`) | sinónimos → `value_en` (`rent`/`sale`/`temporary_rent`) → valor regional del país en `transaction_types` (`alquiler` AR, `arriendo` CL). **Si no se reconoce, el anuncio falla** (no se adivina). |
| Tipo de inmueble | 1) coincidencia directa con `property_types` del país; 2) sinónimo → `value_en` → tipo regional (prefiere terreno/departamento/casa); 3) si no hay equivalente, texto en minúsculas (`oficina`, `dúplex`, `bodega`…). |
| Estado/región | compara contra la tabla `states` del país sin acentos ni prefijo "Región de". En Chile, si viene una provincia, la convierte a región (`ChileRegions`). Devuelve el nombre **oficial de `states`** (p. ej. `Región Metropolitana de Santiago`). Si no reconoce, guarda el texto original. |
| Moneda | `UF`→`CLF`, `U$D`/`US$`→`USD`; vacío, `$` o `pesos` → moneda del país. |
| Área / lote | se redondean a entero (las columnas son `integer`; los feeds traen decimales). |
| Descripción | decodifica entidades HTML, quita etiquetas y conserva saltos de línea (`\n`, `<br>`, `</p>`, `</li>`). **Los feeds de Wasi vienen sin saltos de línea**: es del feed, no de la importación. |

## Seguridad

La URL la da el usuario → `FeedFetcher`: solo http/https, bloquea hosts que resuelven a IPs privadas/reservadas (valida también cada redirect, máx. 3), timeout, tamaño máximo (20 MB), `User-Agent` propio. Agrega `?v=<aleatorio>` en cada descarga y reintenta hasta 3 veces si la respuesta no es un XML válido (los feeds de Wasi fallan a veces por caché). Todas las rutas de `dashboard/imports` exigen auth y validan que la fuente pertenezca al usuario.

## Configuración (`config/import.php`, `.env`)

| Variable | Default | Descripción |
|---|---|---|
| `IMPORT_SYNC_INTERVAL_HOURS` | 24 | frecuencia de sync por fuente (se guarda en `import_sources.sync_interval_hours`) |
| `IMPORT_MISSING_TOLERANCE` | 2 | corridas sin aparecer antes de desactivar |
| `IMPORT_MAX_CONSECUTIVE_FAILURES` | 5 | fallos seguidos antes de pausar la fuente |
| `IMPORT_MAX_SOURCES_PER_USER` | 5 | conexiones por usuario |
| `IMPORT_MAX_LISTINGS_PER_FEED` | 2000 | tope de anuncios por feed |
| `IMPORT_FEED_TIMEOUT` / `_ATTEMPTS` / `_RETRY_DELAY_MS` / `_MAX_BYTES` | 30 / 3 / 1500 / 20 MB | descarga del feed |
| `IMPORT_BLOCK_PRIVATE_HOSTS` | true | protección SSRF (solo desactivar en tests) |
| `IMPORT_CHUNK_SIZE`, `IMPORT_IMAGE_TIMEOUT`, `IMPORT_MAX_IMAGE_SIZE` | 20 / 60 / 10 MB | compartidas con el legacy |

## Base de datos

Una migración: `2026_09_30_100000_create_import_sources_table.php` (todo aditivo, sin datos que migrar).

- **Nueva `import_sources`:** `user_id`, `driver`, `name`, `config` (JSON), `status` (active/paused/error), `sync_enabled`, `sync_interval_hours`, `last_synced_at`, `last_sync_status`, `last_error`, `consecutive_failures`. Índices `(user_id, driver)` y `(status, sync_enabled, last_synced_at)`.
- **`import_jobs`:** `import_source_id` (nullable, cascade), `type` (initial/sync), `updated_listings`, `deactivated_listings`.
- **`property_listings`:** `import_source_id` (nullable, nullOnDelete), `import_data` (JSON), `import_missing_count`; índice `(import_source_id, external_id)`. Se reutilizan `external_id` y `source`.
- **`property_images`:** `source_url` (nullable).

Cambios mínimos en código existente: el import legacy y el recuadro del dashboard filtran `whereNull('import_source_id')` para no mezclarse con los jobs de fuentes nuevas; `CountrySetting::getEnabledCountries()` devuelve `Eloquent\Collection` vacía (arreglo del bug conocido).

## Cómo agregar una fuente nueva

1. Crear `app/Services/Import/Sources/MiFuenteDriver.php` implementando `ImportDriver`: `key()`, `label()`, `description()`, `fields()` (tipos `country` / `url` / `text`), `rules()`, `suggestName()` y `fetch(array $config)`, que devuelve la lista de anuncios normalizados (mismas claves que `WasiTrovitDriver::normalizeAd`, con `images` como lista de URLs; la primera es la principal).
2. Reutilizar `FeedFetcher`, `ImportTypeResolver`, `ImportLocationResolver` e `ImportCurrencyNormalizer`.
3. Registrarla en `config/import.php` → `drivers`.
4. Textos en `resources/lang/{es,en}/import.php` → `drivers.{key}`.
5. Test con un fixture recortado en `tests/Fixtures/`.

El formulario, la validación, el pipeline, el sync y la UI no requieren cambios.

## Producción

### Cómo corre hoy la cola
Cron cada minuto:
```
cd /home/bienesai/laravel-app && flock -n /tmp/laravel-queue.lock /usr/local/bin/ea-php84 -d memory_limit=256M artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```
- Un solo worker a la vez (`flock -n`). `--stop-when-empty` lo detiene cuando no hay jobs; el siguiente minuto lo retoma.
- La importación **encadena** sus jobs (cada chunk despacha el siguiente), así que la cola no se vacía a mitad y un mismo worker completa toda la importación. Si el worker sale por memoria (`--memory=128` por defecto), el cron lo retoma al minuto siguiente: la importación sigue sola.
- Los jobs declaran su propio `$timeout` (fetch 300 s, chunk 600 s), que prevalece sobre el `--timeout` del worker. **No** usar `queue:listen` para esto (su timeout de 60 s mata los chunks).
- Latencia esperada: la importación arranca dentro del minuto siguiente a conectar o sincronizar.

### Scheduler (necesario para el sync automático)
`imports:sync` está en `routes/console.php` (cada hora, `withoutOverlapping`). **Requiere un cron de `schedule:run` cada minuto** en el servidor:
```
* * * * * cd /home/bienesai/laravel-app && /usr/local/bin/ea-php84 artisan schedule:run >> /dev/null 2>&1
```
⚠️ Verificar en cPanel que exista (ya lo necesita `subscriptions:cancel-expired`). Sin él, la importación inicial y "Sincronizar ahora" funcionan (solo usan la cola), pero el sync automático no corre.

### Checklist de deploy
1. `git push` a `main` sube el código por FTP (`vendor/` excluido; **no hay cambios de Composer**).
2. En el servidor: `php artisan migrate` (agrega `import_sources` y columnas en `import_jobs`, `property_listings`, `property_images`; la de `property_listings` crea un índice que bloquea escrituras unos segundos).
3. `php -d memory_limit=-1 artisan optimize` (rutas nuevas `dashboard/imports/*`, comando `imports:sync`).
4. Confirmar el cron de `schedule:run` (ver arriba) y que `APP_URL` de producción sea correcto.
5. Si se notan estilos faltantes en `/dashboard/imports`: `npm run build` y commitear `public/build`.
6. Prueba: con un usuario premium conectar un feed, esperar ~1-2 min (cron de cola) y revisar la barra de progreso y los anuncios en `/property-listings`.

### Operación y diagnóstico
```bash
php artisan imports:sync                  # encola fuentes vencidas
php artisan imports:sync --source=ID      # fuerza una fuente
php artisan queue:failed                  # jobs fallidos
```
- `import_sources.last_error` / `status` muestran por qué una fuente falló o se pausó; `import_listing_items.error_message` indica por qué falló un anuncio (p. ej. tipo de operación no reconocido).
- Chunk muerto por timeout: `failed()` descarta el item culpable y continúa la cadena; el anuncio se reintenta en el siguiente sync.
- Imágenes: ocupan disco (`storage/app/public/property_images`); en cada sync solo se descargan las nuevas.

## Tests

`tests/Feature/Import/WasiImportTest.php` (31 tests, fixture `tests/Fixtures/wasi-trovit.xml`): driver y normalización, FeedFetcher/SSRF, resolvers, pipeline (alta, sin duplicados, merge con ediciones del usuario, desactivación/reactivación, imágenes, feed roto, fallos consecutivos), comando `imports:sync`, acceso premium y permisos.
```bash
php artisan test --compact --filter=WasiImportTest
```
(La BD de tests debe estar migrada: `DB_DATABASE=bienesonline_test php artisan migrate`.)

## Pendientes / limitaciones conocidas

- Tipos sin equivalente regional (`dúplex`, `lote comercial`, `bodega`, `oficina`) quedan como texto libre y no matchean entre países; se puede ampliar `ImportTypeResolver::PROPERTY_SYNONYMS`.
- El import legacy sigue guardando `image_url` absoluta (depende de `APP_URL`) y su propio mapeo provincia→región (nombres distintos a la tabla `states`); se descarta cuando el legacy desaparezca.
- Wasi no entrega saltos de línea en las descripciones.
- El feed se valida descargándolo dos veces al conectar (validación + importación); aceptable por el volumen.
- La frecuencia de sync es global (24 h por defecto); el diseño ya permite frecuencia por fuente (`sync_interval_hours`) pero la UI todavía no la expone.
