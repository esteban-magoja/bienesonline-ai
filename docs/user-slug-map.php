<?php
/**
 * Genera la lista email => slug (username) de todos los usuarios.
 *
 * El slug es el segmento de URL del perfil público del usuario en BienesOnline:
 *   https://bienesonline.com/es/inmobiliaria/{username}
 *
 * En la BD, ese slug es el campo `users.username`. Este script se ejecuta dentro
 * del proyecto bienesonline-ai (usa Laravel DB facade, credenciales del .env).
 * Imprime por consola un array asociativo PHP listo para copiar al proyecto legacy.
 *
 * USO:
 *   php artisan tinker --execute 'require base_path("docs/user-slug-map.php");'
 *
 * @return void
 */

use Illuminate\Support\Facades\DB;

$map = DB::table('users')
    ->select('email', 'username')
    ->whereNotNull('email')
    ->whereNotNull('username')
    ->orderBy('id')
    ->get()
    ->mapWithKeys(fn ($row) => [trim((string) $row->email) => trim((string) $row->username)])
    ->all();

$output = "<?php\n\n// Mapa email => slug (users.username) generado el " . now()->toDateTimeString() . "\n";
$output .= "// Cantidad de usuarios: " . count($map) . "\n\n";
$output .= "return [\n";
foreach ($map as $email => $slug) {
    $output .= "    '" . addslashes($email) . "' => '" . addslashes($slug) . "',\n";
}
$output .= "];\n";

fwrite(STDOUT, $output);
