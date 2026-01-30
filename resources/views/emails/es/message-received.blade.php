@component('mail::message')
# {{ __('emails.message_received.greeting', ['name' => $property->user->name]) }}

{{ __('emails.message_received.intro') }}

## {{ __('emails.message_received.property') }}
**{{ $property->title }}**

📍 {{ $property->city }}, {{ $property->country }}  
💰 {{ $property->formatted_price }}

---

**{{ __('emails.message_received.from') }}:** {{ $message->name }}  
**Email:** {{ $message->email }}  
@if($message->phone)
**{{ __('properties.contact.phone') }}:** {{ $message->phone }}
@endif

**{{ __('emails.message_received.message') }}:**

{{ $message->message }}

---

@component('mail::button', ['url' => route('property.show', $property->id)])
{{ __('emails.message_received.view_property') }}
@endcomponent

{{ __('emails.message_received.footer') }}

{{ __('emails.common.regards') }},  
{{ __('emails.common.team') }} {{ config('app.name') }}
@endcomponent
