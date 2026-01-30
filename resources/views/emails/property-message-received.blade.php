<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Consulta</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2563eb;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .property-info {
            background-color: white;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
        }
        .message-box {
            background-color: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }
        .contact-info {
            background-color: #eff6ff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 0;
        }
        h1 {
            margin: 0;
            font-size: 24px;
        }
        h2 {
            color: #1f2937;
            font-size: 18px;
            margin-top: 0;
        }
        .label {
            font-weight: bold;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📧 Nueva Consulta sobre tu Propiedad</h1>
    </div>

    <div class="content">
        <p>Hola {{ $propertyMessage->propertyListing->user->name }},</p>
        
        <p>Has recibido una nueva consulta sobre tu propiedad:</p>

        <div class="property-info">
            <h2>{{ $propertyMessage->propertyListing->title }}</h2>
            <p>
                <span class="label">Precio:</span> {{ $propertyMessage->propertyListing->currency }} {{ number_format($propertyMessage->propertyListing->price) }}<br>
                <span class="label">Ubicación:</span> {{ $propertyMessage->propertyListing->city }}, {{ $propertyMessage->propertyListing->state }}
            </p>
        </div>

        <div class="contact-info">
            <h2>Datos del Interesado</h2>
            <p>
                <span class="label">Nombre:</span> {{ $propertyMessage->name }}<br>
                <span class="label">Email:</span> <a href="mailto:{{ $propertyMessage->email }}">{{ $propertyMessage->email }}</a><br>
                @if($propertyMessage->phone)
                    <span class="label">Teléfono:</span> <a href="tel:{{ $propertyMessage->phone }}">{{ $propertyMessage->phone }}</a><br>
                @endif
            </p>
        </div>

        <div class="message-box">
            <h2>Mensaje</h2>
            <p style="white-space: pre-line;">{{ $propertyMessage->message }}</p>
        </div>

        <p style="text-align: center;">
            <a href="mailto:{{ $propertyMessage->email }}" class="btn">Responder al Interesado</a>
        </p>

        <p style="margin-top: 30px; color: #6b7280; font-size: 14px;">
            Puedes responder directamente a este email o usar el email del interesado para contactarlo.
        </p>
    </div>

    <div class="footer">
        <p>Este mensaje fue enviado desde el formulario de contacto de tu anuncio.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
    </div>
</body>
</html>
