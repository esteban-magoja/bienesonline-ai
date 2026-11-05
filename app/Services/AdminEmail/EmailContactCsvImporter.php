<?php

namespace App\Services\AdminEmail;

use App\Models\EmailContact;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nnjeim\World\Models\Country;

/**
 * Importa contactos (Comunicaciones → Contactos) desde un CSV.
 *
 * - Columnas reconocidas por el encabezado (es/en, sin importar acentos ni mayúsculas). Solo el
 *   email es obligatorio. Un archivo sin encabezado con un email por línea también sirve.
 * - Separador `,`, `;` o tabulación y codificación UTF-8 o Windows-1252 (Excel) detectados solos.
 * - No duplica: si el contacto existe solo completa sus campos vacíos. Los emails de usuarios
 *   registrados no se crean como contactos (ya están como usuarios).
 */
class EmailContactCsvImporter
{
    public const MAX_ROWS = 10000;

    private const CHUNK = 1000;

    /**
     * Nombres de columna aceptados (normalizados con Str::slug(..., '_')) por campo.
     *
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'email' => ['email', 'e_mail', 'mail', 'correo', 'correo_electronico', 'email_address', 'direccion_de_correo'],
        'name' => ['nombre', 'name', 'nombre_completo', 'full_name', 'contacto', 'nombre_y_apellido', 'first_name'],
        'agency' => ['inmobiliaria', 'agencia', 'empresa', 'agency', 'company', 'razon_social', 'organizacion', 'organization'],
        'country' => ['pais', 'country'],
        'whatsapp' => ['whatsapp', 'telefono', 'movil', 'celular', 'phone', 'mobile', 'tel', 'phone_number', 'numero'],
        'notes' => ['notas', 'notes', 'observaciones', 'comentarios', 'comments', 'nota'],
    ];

    /**
     * Nombres de país en español que no coinciden con el nombre en inglés de la tabla countries.
     *
     * @var array<string, string>
     */
    private const COUNTRY_ALIASES = [
        'espana' => 'Spain',
        'brasil' => 'Brazil',
        'estados-unidos' => 'United States',
        'eeuu' => 'United States',
        'ee-uu' => 'United States',
        'usa' => 'United States',
        'republica-dominicana' => 'Dominican Republic',
        'italia' => 'Italy',
        'francia' => 'France',
        'alemania' => 'Germany',
        'reino-unido' => 'United Kingdom',
        'belgica' => 'Belgium',
        'suiza' => 'Switzerland',
        'holanda' => 'Netherlands',
        'paises-bajos' => 'Netherlands',
    ];

    /**
     * @return array{created: int, updated: int, unchanged: int, users: int, invalid: int, duplicates: int, contact_ids: list<int>, user_ids: list<int>, invalid_lines: list<int>}
     *
     * @throws InvalidArgumentException si el archivo no tiene columna de email o supera MAX_ROWS
     */
    public function import(string $path, ?string $source = null): array
    {
        $rows = $this->readRows($path);

        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'users' => 0, 'invalid' => 0, 'duplicates' => 0, 'contact_ids' => [], 'user_ids' => [], 'invalid_lines' => []];

        /** @var array<string, array<string, ?string>> $byEmail */
        $byEmail = [];
        foreach ($rows as $line => $row) {
            $email = Str::lower(trim((string) ($row['email'] ?? '')));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $result['invalid']++;
                $result['invalid_lines'][] = $line;

                continue;
            }

            if (isset($byEmail[$email])) {
                $result['duplicates']++;

                continue;
            }

            $byEmail[$email] = $this->cleanRow($row);
        }

        $emails = collect(array_keys($byEmail));
        $userIdsByEmail = $this->usersByEmail($emails);
        $contactsByEmail = $this->contactsByEmail($emails);

        DB::transaction(function () use ($byEmail, $userIdsByEmail, $contactsByEmail, $source, &$result): void {
            foreach ($byEmail as $email => $data) {
                if (isset($userIdsByEmail[$email])) {
                    $result['users']++;
                    $result['user_ids'][] = $userIdsByEmail[$email];

                    continue;
                }

                $contact = $contactsByEmail[$email] ?? null;

                if ($contact === null) {
                    $contact = EmailContact::create($data + ['email' => $email, 'source' => $source]);
                    $result['created']++;
                } else {
                    // Solo completa lo vacío: no pisa lo cargado a mano
                    $missing = array_filter($data, fn (?string $value, string $field): bool => $value !== null && blank($contact->{$field}), ARRAY_FILTER_USE_BOTH);

                    if ($missing !== []) {
                        $contact->update($missing);
                        $result['updated']++;
                    } else {
                        $result['unchanged']++;
                    }
                }

                $result['contact_ids'][] = $contact->id;
            }
        });

        $result['invalid_lines'] = array_slice($result['invalid_lines'], 0, 10);

        return $result;
    }

    /**
     * Lee el CSV y devuelve las filas indexadas por número de línea, con las claves de HEADER_ALIASES.
     *
     * @return array<int, array<string, string>>
     */
    private function readRows(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // BOM de UTF-8

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $content = str_replace(["\r\n", "\r"], "\n", (string) $content);
        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = $this->detectDelimiter($firstLine);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, null, $delimiter, '"', '') ?: [];
        $columns = $this->mapHeader($header);
        $line = 1;
        $rows = [];

        // Sin encabezado reconocible: si la primera fila trae un email, se toma un email por línea
        if (! in_array('email', $columns, true)) {
            if (! $this->firstEmailIn($header)) {
                fclose($stream);

                throw new InvalidArgumentException('No se encontró la columna de email. Encabezados aceptados: ' . implode(', ', self::HEADER_ALIASES['email']) . '.');
            }

            $rows[$line] = ['email' => (string) $this->firstEmailIn($header)];
            $columns = null;
        }

        while (($values = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;

            if ($values === [null] || trim(implode('', array_map('strval', $values))) === '') {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($stream);

                throw new InvalidArgumentException('El archivo supera el máximo de ' . number_format(self::MAX_ROWS, 0, ',', '.') . ' filas. Dividilo en partes.');
            }

            $rows[$line] = $columns === null
                ? ['email' => (string) ($this->firstEmailIn($values) ?? $values[0] ?? '')]
                : $this->combine($columns, $values);
        }

        fclose($stream);

        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @param  array<int, ?string>  $header
     * @return array<int, ?string> campo por posición de columna (null = columna ignorada)
     */
    private function mapHeader(array $header): array
    {
        $used = [];

        return array_map(function (?string $title) use (&$used): ?string {
            $normalized = Str::slug((string) $title, '_');

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (! isset($used[$field]) && in_array($normalized, $aliases, true)) {
                    $used[$field] = true;

                    return $field;
                }
            }

            return null;
        }, $header);
    }

    /**
     * @param  array<int, ?string>  $columns
     * @param  array<int, ?string>  $values
     * @return array<string, string>
     */
    private function combine(array $columns, array $values): array
    {
        $row = [];

        foreach ($columns as $index => $field) {
            if ($field !== null) {
                $row[$field] = trim((string) ($values[$index] ?? ''));
            }
        }

        return $row;
    }

    /**
     * @param  array<int, ?string>  $values
     */
    private function firstEmailIn(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, ?string> campos del contacto (sin email), vacíos como null
     */
    private function cleanRow(array $row): array
    {
        $data = [];

        foreach (['name' => 255, 'agency' => 255, 'country' => 100, 'whatsapp' => 50, 'notes' => 5000] as $field => $limit) {
            $value = Str::squish($row[$field] ?? '');
            $data[$field] = $value === '' ? null : Str::limit($value, $limit, '');
        }

        if ($data['country'] !== null) {
            $data['country'] = $this->normalizeCountry($data['country']);
        }

        return $data;
    }

    /**
     * Lleva el país al nombre de la tabla countries (el que usa el formulario): acepta nombre en
     * español o inglés, con o sin acentos, o código ISO2. Si no lo reconoce, deja el texto original.
     */
    private function normalizeCountry(string $country): string
    {
        $countries = Cache::remember('email_contact_import_countries', 3600, function (): array {
            $map = [];

            foreach (Country::query()->get(['name', 'iso2']) as $row) {
                $map[Str::slug($row->name)] = $row->name;
                $map[Str::lower($row->iso2)] = $row->name;
            }

            return $map;
        });

        $key = Str::slug($country);

        return $countries[$key] ?? (isset(self::COUNTRY_ALIASES[$key]) ? self::COUNTRY_ALIASES[$key] : $country);
    }

    /**
     * @param  Collection<int, string>  $emails
     * @return array<string, int>
     */
    private function usersByEmail(Collection $emails): array
    {
        return $emails->chunk(self::CHUNK)
            ->flatMap(fn (Collection $chunk) => User::query()
                ->whereIn(DB::raw('LOWER(email)'), $chunk->values()->all())
                ->get(['id', 'email'])
                ->mapWithKeys(fn (User $user): array => [Str::lower($user->email) => $user->id]))
            ->all();
    }

    /**
     * @param  Collection<int, string>  $emails
     * @return array<string, EmailContact>
     */
    private function contactsByEmail(Collection $emails): array
    {
        return $emails->chunk(self::CHUNK)
            ->flatMap(fn (Collection $chunk) => EmailContact::query()
                ->whereIn('email', $chunk->values()->all())
                ->get()
                ->keyBy('email'))
            ->all();
    }
}
