<!DOCTYPE html>
<html>
<head>
    <title>{{ __('auth.verify_email.subject') }}</title>
</head>

<body>
<h2>{{ __('auth.verify_email.greeting', ['name' => $user['name']]) }}</h2>
<br/>
{{ __('auth.verify_email.body', ['email' => $user['email']]) }}
<br/>
<a href="{{ url('user/verify/', $user['verification_code']) }}">{{ __('auth.verify_email.action') }}</a>
</body>

</html>