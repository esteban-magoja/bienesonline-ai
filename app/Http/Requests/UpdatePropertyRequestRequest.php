<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $propertyRequest = $this->route('propertyRequest');
        return auth()->check() && $propertyRequest->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'required|string|max:20',
            
            // Campos bilingües
            'title.es' => 'required|string|max:255',
            'title.en' => 'required|string|max:255',
            'description.es' => 'required|string|min:20',
            'description.en' => 'required|string|min:20',
            
            'property_type' => 'required|string|in:house,apartment,commercial,office,land,farm,warehouse',
            'transaction_type' => 'required|string|in:sale,rent',
            'max_budget' => 'required|numeric|min:0',
            'min_budget' => 'nullable|numeric|min:0|lt:max_budget',
            'currency' => 'required|string|in:USD,ARS,EUR,BRL,MXN,CLP',
            'min_bedrooms' => 'nullable|integer|min:0',
            'min_bathrooms' => 'nullable|integer|min:0',
            'min_parking_spaces' => 'nullable|integer|min:0',
            'min_area' => 'nullable|integer|min:0',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'expires_at' => 'nullable|date|after:today',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.es.required' => __('validation.required', ['attribute' => __('attributes.title') . ' (ES)']),
            'title.en.required' => __('validation.required', ['attribute' => __('attributes.title') . ' (EN)']),
            'description.es.required' => __('validation.required', ['attribute' => __('attributes.description') . ' (ES)']),
            'description.en.required' => __('validation.required', ['attribute' => __('attributes.description') . ' (EN)']),
            'description.es.min' => __('validation.min.string', ['attribute' => __('attributes.description') . ' (ES)', 'min' => 20]),
            'description.en.min' => __('validation.min.string', ['attribute' => __('attributes.description') . ' (EN)', 'min' => 20]),
        ];
    }

    public function attributes(): array
    {
        return [
            'client_name' => __('attributes.client_name'),
            'client_email' => __('attributes.client_email'),
            'client_phone' => __('attributes.client_phone'),
            'title.es' => __('attributes.title') . ' (ES)',
            'title.en' => __('attributes.title') . ' (EN)',
            'description.es' => __('attributes.description') . ' (ES)',
            'description.en' => __('attributes.description') . ' (EN)',
            'property_type' => __('attributes.property_type'),
            'transaction_type' => __('attributes.transaction_type'),
            'max_budget' => __('attributes.max_budget'),
            'min_budget' => __('attributes.min_budget'),
            'currency' => __('attributes.currency'),
            'min_bedrooms' => __('attributes.bedrooms'),
            'min_bathrooms' => __('attributes.bathrooms'),
            'min_parking_spaces' => __('attributes.parking_spaces'),
            'min_area' => __('attributes.area'),
            'city' => __('attributes.city'),
            'state' => __('attributes.state'),
            'country' => __('attributes.country'),
            'expires_at' => __('attributes.expires_at'),
            'is_active' => __('attributes.is_active'),
        ];
    }
}
