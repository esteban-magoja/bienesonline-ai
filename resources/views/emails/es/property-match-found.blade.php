@component('mail::message')
# {{ __('emails.property_match.greeting', ['name' => $user->name]) }}

{{ __('emails.property_match.intro') }}

## {{ __('emails.property_match.request_title') }}
**{{ $request->title }}**

---

## {{ __('emails.property_match.property_details') }}

**{{ $property->title }}**

📍 {{ $property->city }}, {{ $property->country }}  
💰 {{ $property->formatted_price }}  
🏠 {{ $property->property_type_formatted }}  
📏 {{ $property->area_cubierta }}m² {{ __('properties.stats.area_cubierta') }}

@if(isset($matchScore))
**{{ __('emails.property_match.match_score') }}:** {{ $matchScore }}%
@endif

@if(isset($matchReasons))
**{{ __('emails.property_match.match_reasons') }}:**
@foreach($matchReasons as $reason)
- {{ $reason }}
@endforeach
@endif

@component('mail::button', ['url' => route('property.show', $property->id)])
{{ __('emails.property_match.view_property') }}
@endcomponent

@component('mail::button', ['url' => route('dashboard.requests.show', $request->id), 'color' => 'success'])
{{ __('emails.property_match.view_all_matches') }}
@endcomponent

---

{{ __('emails.property_match.footer') }}

{{ __('emails.property_match.unsubscribe') }}

{{ __('emails.common.regards') }},  
{{ __('emails.common.team') }} {{ config('app.name') }}
@endcomponent
