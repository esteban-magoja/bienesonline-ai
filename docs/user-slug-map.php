<?php
/**
 * Genera la lista email => slug (username) de todos los usuarios, con paginación.
 *
 * El slug es el segmento de URL del perfil público del usuario en BienesOnline:
 *   https://bienesonline.com/es/inmobiliaria/{username}
 *
 * En la BD, ese slug es el campo `users.username`. Este script se ejecuta dentro
 * del proyecto bienesonline-ai (usa Laravel DB facade, credenciales del .env).
 * Imprime por consola un array asociativo PHP listo para copiar al proyecto legacy.
 *
 * USO (paginación por argumento offset):
 *   php artisan tinker --execute 'require base_path("docs/user-slug-map.php");'
 *   php artisan tinker --execute 'require base_path("docs/user-slug-map.php"); $argv=["--offset=0","--limit=1000"];'
 *
 * Variables opcionales (setear antes del require):
 *   $usmOffset  int  Registro inicial (default 0)
 *   $usmLimit   int  Cantidad de registros por página (default 1000)
 *
 * El output incluye:
 *   - Cabecera con offset/limit/total y metadatos para continuar la siguiente página
 *   - Array PHP `['email' => 'slug', ...]` listo para copiar
 *
 * Ejemplo de flujo de paginación:
 *   Página 1: offset=0,    limit=1000  -> devuelve 1000 registros (id 1..1000 aprox)
 *   Página 2: offset=1000, limit=1000  -> devuelve hasta 1000 registros más
 *   ...
 *   Cuando devuelve menos de $usmLimit registros, llegó al final.
 *
 * @return void
 */

use Illuminate\Support\Facades\DB;

// ============================================================
// PAGINACIÓN
// ============================================================

$offset = isset($usmOffset) ? (int) $usmOffset : 0;
$limit  = isset($usmLimit)  ? (int) $usmLimit  : 1000;

if ($offset < 0) {
    $offset = 0;
}
if ($limit < 1 || $limit > 10000) {
    $limit = 1000;
}

// Total de registros disponibles (sin paginar)
$total = DB::table('users')
    ->whereNotNull('email')
    ->whereNotNull('username')
    ->count();

// Página actual
$map = DB::table('users')
    ->select('email', 'username')
    ->whereNotNull('email')
    ->whereNotNull('username')
    ->orderBy('id')
    ->offset($offset)
    ->limit($limit)
    ->get()
    ->mapWithKeys(fn ($row) => [trim((string) $row->email) => trim((string) $row->username)])
    ->all();

$count = count($map);
$hasMore = ($offset + $count) < $total;
$nextOffset = $offset + $count;

// ============================================================
// OUTPUT
// ============================================================

$output  = "<?php\n\n";
$output .= "// Mapa email => slug (users.username)\n";
$output .= "// Generado el " . now()->toDateTimeString() . "\n";
$output .= "// Pagina: offset=" . $offset . " limit=" . $limit . " count=" . $count . " total=" . $total . "\n";
$output .= "// Siguiente pagina (si hasMore=true): offset=" . $nextOffset . " limit=" . $limit . "\n";
$output .= "// hasMore=" . ($hasMore ? 'true' : 'false') . "\n\n";
$output .= "return [\n";
foreach ($map as $email => $slug) {
    $output .= "    '" . addslashes($email) . "' => '" . addslashes($slug) . "',\n";
}
$output .= "];\n";

fwrite(STDERR, sprintf(
    "[user-slug-map] offset=%d limit=%d count=%d total=%d hasMore=%s nextOffset=%d%s",
    $offset, $limit, $count, $total,
    $hasMore ? 'true' : 'false',
    $nextOffset,
    PHP_EOL
));
fwrite(STDOUT, $output);
