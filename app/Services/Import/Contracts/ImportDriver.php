<?php

namespace App\Services\Import\Contracts;

/**
 * Una fuente externa de anuncios (Wasi, otros CRMs, etc.).
 *
 * Cada driver define qué datos pide al usuario y cómo obtiene/normaliza los anuncios.
 * El pipeline común (ProcessSourceImportChunkJob) solo conoce el formato normalizado
 * que devuelve fetch().
 */
interface ImportDriver
{
    /** Clave estable guardada en import_sources.driver y property_listings.source. */
    public function key(): string;

    /** Nombre mostrado al usuario. */
    public function label(): string;

    /** Texto breve de ayuda mostrado en el formulario. */
    public function description(): string;

    /**
     * Campos que se piden al usuario.
     *
     * @return array<string, array{type: string, label: string, placeholder?: string, help?: string}>
     *         type: 'country' | 'url' | 'text'
     */
    public function fields(): array;

    /**
     * Reglas de validación de Laravel para los campos de fields().
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /** Nombre corto para identificar la conexión en la UI (ej. host del feed). */
    public function suggestName(array $config): string;

    /**
     * Descarga y normaliza los anuncios.
     *
     * Cada elemento debe incluir: id (external_id), title, description, property_type,
     * transaction_type, price, currency, bedrooms, bathrooms, parking_spaces, area,
     * lotsize, address, city, state, country, postal_code, latitude, longitude,
     * images (lista de URLs, la primera es la principal).
     *
     * @param  array<string, mixed>  $config  valores del formulario (import_sources.config)
     * @return array<int, array<string, mixed>>
     *
     * @throws \App\Services\Import\ImportSourceException si la fuente no responde o es inválida
     */
    public function fetch(array $config): array;
}
