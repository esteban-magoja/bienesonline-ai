<?php

namespace App\Services\Import;

use RuntimeException;

/**
 * Error controlado al obtener o interpretar una fuente. El mensaje es apto para mostrar al usuario.
 */
class ImportSourceException extends RuntimeException
{
}
