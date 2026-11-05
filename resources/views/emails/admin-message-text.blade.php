{!! $bodyText !!}

--
bienesonline.ai - {{ config('app.url') }}
@if ($unsubscribeUrl)
{{ __('admin_email.footer.unsubscribe_text', [], $footerLocale) }} {{ __('admin_email.footer.unsubscribe_link', [], $footerLocale) }}: {!! $unsubscribeUrl !!}
@endif
