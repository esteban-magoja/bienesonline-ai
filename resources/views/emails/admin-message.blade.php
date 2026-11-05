<!DOCTYPE html>
<html lang="{{ $footerLocale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#ffffff;">
    <div style="max-width:600px;margin:0 auto;padding:24px 16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;">
        {!! $bodyHtml !!}

        <hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 0 16px;">
        <p style="margin:0;font-size:12px;line-height:1.5;color:#6b7280;">
            <a href="{{ config('app.url') }}" style="color:#6b7280;">bienesonline.ai</a>
            @if ($unsubscribeUrl)
                · {{ __('admin_email.footer.unsubscribe_text', [], $footerLocale) }}
                <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">{{ __('admin_email.footer.unsubscribe_link', [], $footerLocale) }}</a>
            @endif
        </p>
    </div>
</body>
</html>
