<?php

namespace App\Traits;

use Illuminate\Support\Facades\App;

trait Translatable
{
    /**
     * Get the list of translatable attributes.
     *
     * @return array
     */
    public function getTranslatableAttributes(): array
    {
        return $this->translatable ?? [];
    }

    /**
     * Get a translation for a specific field and locale.
     *
     * @param string $field
     * @param string|null $locale
     * @return string|null
     */
    public function getTranslation(string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?? App::getLocale();
        $i18nField = $field . '_i18n';

        // Si el campo i18n no existe, retornar null
        if (!isset($this->attributes[$i18nField])) {
            return null;
        }

        // Obtener las traducciones
        $translations = $this->getAttribute($i18nField);

        // Si no es un array, retornar null
        if (!is_array($translations)) {
            return null;
        }

        // Intentar retornar la traducción solicitada
        if (isset($translations[$locale])) {
            return $translations[$locale];
        }

        // Fallback al idioma por defecto (español)
        if (isset($translations['es'])) {
            return $translations['es'];
        }

        // Fallback a cualquier traducción disponible
        return !empty($translations) ? reset($translations) : null;
    }

    /**
     * Set a translation for a specific field and locale.
     *
     * @param string $field
     * @param string $locale
     * @param string|null $value
     * @return self
     */
    public function setTranslation(string $field, string $locale, ?string $value): self
    {
        $i18nField = $field . '_i18n';

        // Obtener traducciones actuales o inicializar array vacío
        $translations = $this->getAttribute($i18nField) ?? [];

        // Asegurar que sea un array
        if (!is_array($translations)) {
            $translations = [];
        }

        // Establecer la traducción
        $translations[$locale] = $value;

        // Guardar las traducciones
        $this->setAttribute($i18nField, $translations);

        return $this;
    }

    /**
     * Get all translations for a specific field.
     *
     * @param string $field
     * @return array
     */
    public function getAllTranslations(string $field): array
    {
        $i18nField = $field . '_i18n';

        $translations = $this->getAttribute($i18nField);

        return is_array($translations) ? $translations : [];
    }

    /**
     * Check if a translation exists for a specific field and locale.
     *
     * @param string $field
     * @param string $locale
     * @return bool
     */
    public function hasTranslation(string $field, string $locale): bool
    {
        $i18nField = $field . '_i18n';

        $translations = $this->getAttribute($i18nField);

        if (!is_array($translations)) {
            return false;
        }

        return isset($translations[$locale]) && !empty($translations[$locale]);
    }

    /**
     * Set multiple translations at once.
     *
     * @param string $field
     * @param array $translations
     * @return self
     */
    public function setTranslations(string $field, array $translations): self
    {
        $i18nField = $field . '_i18n';

        $this->setAttribute($i18nField, $translations);

        return $this;
    }

    /**
     * Get the localized value for a field (uses current locale).
     *
     * @param string $field
     * @return string|null
     */
    public function getLocalized(string $field): ?string
    {
        return $this->getTranslation($field, App::getLocale());
    }
}
