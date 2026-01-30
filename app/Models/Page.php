<?php

namespace App\Models;

use Wave\Page as WavePage;

class Page extends WavePage
{
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'title_i18n' => 'array',
        'excerpt_i18n' => 'array',
        'body_i18n' => 'array',
        'meta_description_i18n' => 'array',
        'meta_keywords_i18n' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Antes de guardar, sincronizar campos legacy con versión en español
        static::saving(function ($page) {
            if (isset($page->title_i18n['es'])) {
                $page->attributes['title'] = $page->title_i18n['es'];
            }
            if (isset($page->excerpt_i18n['es'])) {
                $page->attributes['excerpt'] = $page->excerpt_i18n['es'];
            }
            if (isset($page->body_i18n['es'])) {
                $page->attributes['body'] = $page->body_i18n['es'];
            }
            if (isset($page->meta_description_i18n['es'])) {
                $page->attributes['meta_description'] = $page->meta_description_i18n['es'];
            }
            if (isset($page->meta_keywords_i18n['es'])) {
                $page->attributes['meta_keywords'] = $page->meta_keywords_i18n['es'];
            }
        });
    }

    /**
     * Get the translated title for the current locale.
     */
    public function getTitleAttribute($value)
    {
        $locale = app()->getLocale();
        
        // Si existe traducción en el idioma actual, usarla
        if (isset($this->title_i18n[$locale])) {
            return $this->title_i18n[$locale];
        }
        
        // Fallback a español
        if (isset($this->title_i18n['es'])) {
            return $this->title_i18n['es'];
        }
        
        // Fallback al valor original
        return $value;
    }

    /**
     * Get the translated excerpt for the current locale.
     */
    public function getExcerptAttribute($value)
    {
        $locale = app()->getLocale();
        
        if (isset($this->excerpt_i18n[$locale])) {
            return $this->excerpt_i18n[$locale];
        }
        
        if (isset($this->excerpt_i18n['es'])) {
            return $this->excerpt_i18n['es'];
        }
        
        return $value;
    }

    /**
     * Get the translated body for the current locale.
     */
    public function getBodyAttribute($value)
    {
        $locale = app()->getLocale();
        
        if (isset($this->body_i18n[$locale])) {
            return $this->body_i18n[$locale];
        }
        
        if (isset($this->body_i18n['es'])) {
            return $this->body_i18n['es'];
        }
        
        return $value;
    }

    /**
     * Get the translated meta description for the current locale.
     */
    public function getMetaDescriptionAttribute($value)
    {
        $locale = app()->getLocale();
        
        if (isset($this->meta_description_i18n[$locale])) {
            return $this->meta_description_i18n[$locale];
        }
        
        if (isset($this->meta_description_i18n['es'])) {
            return $this->meta_description_i18n['es'];
        }
        
        return $value;
    }

    /**
     * Get the translated meta keywords for the current locale.
     */
    public function getMetaKeywordsAttribute($value)
    {
        $locale = app()->getLocale();
        
        if (isset($this->meta_keywords_i18n[$locale])) {
            return $this->meta_keywords_i18n[$locale];
        }
        
        if (isset($this->meta_keywords_i18n['es'])) {
            return $this->meta_keywords_i18n['es'];
        }
        
        return $value;
    }
}
