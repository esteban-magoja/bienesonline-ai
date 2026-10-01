<?php

namespace App\Services\Import;

use App\Services\Import\Contracts\ImportDriver;
use InvalidArgumentException;

class ImportSourceRegistry
{
    /**
     * @return array<string, ImportDriver> indexado por key()
     */
    public function all(): array
    {
        $drivers = [];

        foreach (config('import.drivers', []) as $class) {
            $driver = app($class);
            $drivers[$driver->key()] = $driver;
        }

        return $drivers;
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function get(string $key): ImportDriver
    {
        return $this->all()[$key]
            ?? throw new InvalidArgumentException("Fuente de importación desconocida: {$key}");
    }
}
