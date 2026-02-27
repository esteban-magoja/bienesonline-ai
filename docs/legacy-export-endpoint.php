<?php
/**
 * Endpoint de exportación de anuncios para BienesOnline Legacy
 * 
 * INSTALACIÓN:
 *   Copiar este archivo al proyecto viejo como: /api/export-listings.php
 *   O configurar el router del proyecto viejo para que /api/export-listings apunte a este script.
 * 
 * USO:
 *   GET /api/export-listings?email=usuario@example.com
 * 
 * RESPUESTA:
 *   JSON con los anuncios del usuario y URLs absolutas de imágenes
 * 
 * CONFIGURACIÓN:
 *   Ajustar las constantes de conexión a BD y los nombres de tablas/columnas
 *   según la estructura real del proyecto viejo.
 */

// ============================================================
// CONFIGURACIÓN - Ajustar según el proyecto viejo
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'bienesonline_legacy');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// URL base para construir las URLs absolutas de imágenes
// Ejemplo: 'https://viejo.bienesonline.com'
define('BASE_URL', 'https://TU_DOMINIO_VIEJO.com');

// Tabla de anuncios y sus columnas
define('TABLE_LISTINGS', 'property_listings');
define('TABLE_USERS', 'users');
define('TABLE_IMAGES', 'property_images');

// Columna que almacena la carpeta de imágenes relativa al public/storage
// Ejemplo: 'property_images/foto.jpg' → BASE_URL/storage/property_images/foto.jpg
define('IMAGES_STORAGE_PATH', '/storage/');

// ============================================================
// LÓGICA PRINCIPAL
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Ajustar en producción

// Validar parámetro email
$email = trim($_GET['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email inválido o no proporcionado']);
    exit;
}

// Conexión a la base de datos
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit;
}

// Buscar usuario por email
$stmt = $pdo->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Devolver array vacío (no revelar si el email existe o no)
    echo json_encode(['listings' => []]);
    exit;
}

$userId = $user['id'];

// Obtener anuncios del usuario
// AJUSTAR las columnas según la BD del proyecto viejo
$stmt = $pdo->prepare('
    SELECT 
        id,
        title,
        description,
        property_type,
        transaction_type,
        price,
        currency,
        bedrooms,
        bathrooms,
        parking_spaces,
        area,
        lotsize,
        address,
        city,
        state,
        country,
        postal_code,
        latitude,
        longitude,
        conditions,
        is_active
    FROM ' . TABLE_LISTINGS . '
    WHERE user_id = ?
    ORDER BY created_at DESC
');
$stmt->execute([$userId]);
$listings = $stmt->fetchAll();

// Para cada anuncio, obtener sus imágenes
$result = [];
foreach ($listings as $listing) {
    $stmtImages = $pdo->prepare('
        SELECT image_path, is_primary, sort_order
        FROM ' . TABLE_IMAGES . '
        WHERE property_listing_id = ?
        ORDER BY sort_order ASC, is_primary DESC
    ');
    $stmtImages->execute([$listing['id']]);
    $images = $stmtImages->fetchAll();

    // Construir URLs absolutas para cada imagen
    $imagesFormatted = array_map(function ($img) {
        return [
            'url'        => BASE_URL . IMAGES_STORAGE_PATH . $img['image_path'],
            'is_primary' => (bool) $img['is_primary'],
            'sort_order' => (int) $img['sort_order'],
        ];
    }, $images);

    $result[] = [
        'id'               => (int) $listing['id'],
        'title'            => $listing['title'] ?? '',
        'description'      => $listing['description'] ?? '',
        'property_type'    => $listing['property_type'] ?? '',
        'transaction_type' => $listing['transaction_type'] ?? '',
        'price'            => (float) ($listing['price'] ?? 0),
        'currency'         => $listing['currency'] ?? 'USD',
        'bedrooms'         => (int) ($listing['bedrooms'] ?? 0),
        'bathrooms'        => (int) ($listing['bathrooms'] ?? 0),
        'parking_spaces'   => (int) ($listing['parking_spaces'] ?? 0),
        'area'             => (int) ($listing['area'] ?? 0),
        'lotsize'          => $listing['lotsize'] ? (int) $listing['lotsize'] : null,
        'address'          => $listing['address'] ?? null,
        'city'             => $listing['city'] ?? '',
        'state'            => $listing['state'] ?? '',
        'country'          => $listing['country'] ?? '',
        'postal_code'      => $listing['postal_code'] ?? null,
        'latitude'         => $listing['latitude'] ? (float) $listing['latitude'] : null,
        'longitude'        => $listing['longitude'] ? (float) $listing['longitude'] : null,
        'conditions'       => $listing['conditions'] ?? null,
        'is_active'        => (bool) $listing['is_active'],
        'images'           => $imagesFormatted,
    ];
}

echo json_encode(['listings' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
