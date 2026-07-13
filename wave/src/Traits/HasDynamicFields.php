<?php

namespace Wave\Traits;

use Illuminate\Support\Str;

trait HasDynamicFields
{
    private function dynamicFields($fields)
    {
        $dynamicFields = [];
        foreach ($fields as $field) {
            $key = $field['key'] ?? Str::slug($field['label']);

            if (! class_exists($field['type'])) {
                $fieldType = '\Filament\Forms\Components\\'.$field['type'];
            } else {
                $fieldType = $field['type'];
            }

            $newField = $fieldType::make($key);

            if (isset($field['label'])) {
                $label = $field['label'];
                if (is_string($label) && str_contains($label, '.') && ! str_contains($label, ' ')) {
                    $label = __($label);
                }
                $newField->label($label);
            }

            if (isset($field['options'])) {
                $newField->options($field['options']);
            }

            if (isset($field['suggestions'])) {
                $newField->suggestions($field['suggestions']);
            }

            if (isset($field['rules'])) {
                $rules = explode('|', $field['rules']);
                $newField->rules($rules);

                if (in_array('required', $rules)) {
                    $newField->required();
                }
            }

            if (isset($field['rows']) && method_exists($newField, 'rows')) {
                $newField->rows($field['rows']);
            }

            if (isset($field['cols']) && method_exists($newField, 'cols')) {
                $newField->cols($field['cols']);
            }

            $keyValue = auth()->user()->profileKeyValues->where('key', $key)->first();

            $value = $keyValue->value ?? '';
            if (! empty($value)) {
                if (json_decode($value, true) !== null) {
                    $value = json_decode($value, true);
                }
            }

            $newField->default($value);
            // add validation

            $dynamicFields[] = $newField;
        }

        return $dynamicFields;
    }

    private function saveDynamicFields($fields)
    {
        $state = $this->form->getState();
        foreach ($fields as $field) {
            $key = $field['key'] ?? Str::slug($field['label']);

            if (isset($state[$key])) {
                $value = $state[$key];
                if (is_array($state[$key])) {
                    $value = json_encode($state[$key]);
                }
                auth()->user()->setProfileKeyValue($key, $value, $field['type']);
            }
        }
    }
}
