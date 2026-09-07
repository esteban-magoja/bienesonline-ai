# PayPal Sandbox en Local

Guía operativa para probar suscripciones de PayPal desde el entorno local.

## Configuración `.env`

Para probar Stripe y PayPal simultáneamente:

```env
BILLING_PROVIDER=stripe
BILLING_PROVIDERS=stripe,paypal

PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=CLIENT_ID_SANDBOX
PAYPAL_CLIENT_SECRET=CLIENT_SECRET_SANDBOX
PAYPAL_WEBHOOK_ID=WEBHOOK_ID_SANDBOX
PAYPAL_CURRENCY=USD

APP_URL=https://TU-DOMINIO.trycloudflare.com
ALLOWED_DOMAINS=localhost,127.0.0.1,TU-DOMINIO.trycloudflare.com

QUEUE_CONNECTION=database
```

`BILLING_PROVIDER` es el proveedor principal de Wave. `BILLING_PROVIDERS` define los proveedores disponibles en el checkout.

No confundir `PAYPAL_WEBHOOK_ID` con la URL del webhook. `PAYPAL_WEBHOOK_ID` es el identificador que PayPal genera al registrar el webhook.

No guardar credenciales reales, tokens OAuth ni secretos en esta guía o en el repositorio.

## Servidor Y Túnel

Iniciar Laravel en el puerto `8000`:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

En otra terminal iniciar un Quick Tunnel:

```bash
cloudflared tunnel --url http://127.0.0.1:8000
```

Cloudflare mostrará una URL temporal similar a:

```text
https://random-name.trycloudflare.com
```

La URL del webhook es:

```text
https://random-name.trycloudflare.com/webhook/paypal
```

El túnel debe permanecer abierto durante las pruebas. La URL puede cambiar al reiniciar `cloudflared`.

Apache no necesita detenerse. El túnel debe apuntar explícitamente a `127.0.0.1:8000`, no al puerto `80`.

Después de cambiar el `.env`:

```bash
php artisan optimize:clear
```

## Webhook De PayPal

En PayPal Developer, registra el webhook desde la aplicación REST Sandbox y usa la URL pública completa:

```text
https://TU-DOMINIO.trycloudflare.com/webhook/paypal
```

La ruta local es `POST /webhook/paypal`. El proyecto excluye esta ruta de CSRF y verifica la firma mediante el endpoint de PayPal usando `PAYPAL_WEBHOOK_ID`.

Para suscripciones, habilitar como mínimo estos eventos:

```text
BILLING.SUBSCRIPTION.CREATED
BILLING.SUBSCRIPTION.ACTIVATED
BILLING.SUBSCRIPTION.UPDATED
BILLING.SUBSCRIPTION.SUSPENDED
BILLING.SUBSCRIPTION.CANCELLED
BILLING.SUBSCRIPTION.EXPIRED
BILLING.SUBSCRIPTION.PAYMENT.FAILED
PAYMENT.SALE.COMPLETED
PAYMENT.SALE.DENIED
PAYMENT.SALE.REVERSED
```

Una prueba manual sin firma:

```bash
curl -i -X POST \
  "https://TU-DOMINIO.trycloudflare.com/webhook/paypal" \
  -H "Content-Type: application/json" \
  -d '{}'
```

La respuesta esperada es `400 Invalid webhook signature`. Esto confirma que la solicitud llegó a Laravel; no simula un webhook válido de PayPal.

## Productos Y Planes

Los productos y planes se crean en el entorno Sandbox. Para cada modalidad de cobro se necesita un plan separado:

- Mensual: intervalo de un mes.
- Anual: intervalo de un año.

Si la aplicación ofrece Basic, Premium y Pro, se necesitan normalmente seis planes: uno mensual y uno anual por cada nivel.

Cada plan de PayPal genera un ID con formato `P-...`. Ese es el ID que se guarda en Filament, en `Plans > Edit > Provider Prices`.

Configurar cada precio con:

```text
Provider: PayPal
Cycle: Monthly o Yearly
External Price / Plan ID: P-...
Amount: importe del plan
Currency: USD
Active: true
```

El ID `PROD-...` es el producto y no debe utilizarse como `plan_id` en el checkout. Tampoco deben utilizarse IDs de Stripe, webhooks o cuentas Sandbox.

No se crean suscripciones manuales para cada usuario. La aplicación crea la suscripción cuando el comprador aprueba el botón PayPal.

## Cuentas Sandbox

El vendedor y el comprador deben ser cuentas Sandbox:

1. En PayPal Developer abrir `Testing Tools > Sandbox Accounts`.
2. Usar la cuenta Business Sandbox para la aplicación, los productos y los planes.
3. Usar una cuenta Personal Sandbox como comprador.
4. Iniciar sesión en `sandbox.paypal.com` con la cuenta Personal Sandbox durante el checkout.

El correo de la cuenta Personal Sandbox no tiene que coincidir con el correo del usuario local. La vinculación de la suscripción se realiza mediante el `custom_id` generado por la aplicación, además de validar el plan y el `payer_id`.

## Cola De Trabajo

Los webhooks se reciben rápidamente, pero `ProcessPayPalWebhook` se ejecuta en cola. Con `QUEUE_CONNECTION=database`, mantener un worker activo:

```bash
php artisan queue:work --tries=5
```

Si se modificó el código de un worker de larga duración:

```bash
php artisan queue:restart
```

El webhook puede llegar después de que el navegador ejecute `onApprove`. El checkout reintenta brevemente la confirmación remota mientras PayPal cambia de `APPROVAL_PENDING` a `ACTIVE`.

## Flujo De Prueba

1. Iniciar Laravel, Cloudflare Tunnel y el worker.
2. Confirmar que `APP_URL` y `ALLOWED_DOMAINS` contienen el dominio actual del túnel.
3. Abrir `/settings/subscription` con un usuario regular sin suscripción activa.
4. Seleccionar el ciclo mensual o anual.
5. Aprobar el pago con una cuenta Personal Sandbox.
6. Esperar la redirección a la pantalla de bienvenida.
7. Comprobar la suscripción local y los eventos del webhook.
8. Probar cancelación y una nueva suscripción si es necesario.

## Diagnóstico

Consultar los registros locales:

```bash
php artisan pail
```

O revisar `storage/logs/laravel.log`.

Consultar estados en la base de datos con las herramientas de lectura del proyecto o un cliente SQL:

```sql
SELECT id, user_id, provider, cycle, status, external_id
FROM billing_checkout_attempts
WHERE provider = 'paypal'
ORDER BY id DESC;

SELECT id, event_type, status, error_message, processed_at
FROM billing_webhook_events
WHERE provider = 'paypal'
ORDER BY id DESC;

SELECT id, billable_id, vendor_slug, vendor_subscription_id, status, plan_id, cycle
FROM subscriptions
WHERE vendor_slug = 'paypal'
ORDER BY id DESC;
```

Estados esperados después de una activación:

```text
billing_checkout_attempts.status = completed
billing_checkout_attempts.external_id = I-...
billing_webhook_events.status = processed
subscriptions.vendor_slug = paypal
subscriptions.status = active
```

### Errores conocidos

`PendingRequest::retry(): Argument #2 ... array given`

En Laravel 12, los intervalos variables se pasan como primer argumento:

```php
->retry([200, 500, 1000], when: $this->shouldRetry(...), throw: false)
```

Después de corregir un worker, reiniciarlo con `php artisan queue:restart`.

`The PayPal subscriber does not match the checkout user.`

No comparar el correo del comprador Sandbox con el correo del usuario local. Son identidades distintas. Validar `custom_id`, plan e identidad `payer_id`.

`403` con `Cf-Mitigated: challenge`

Cloudflare está bloqueando el webhook con un desafío. PayPal no puede resolverlo. Excluir `POST /webhook/paypal` de los challenges, WAF rules, Bot Fight Mode y Cloudflare Access.

Redirección hacia `localhost` o Apache

Agregar el host del túnel a `ALLOWED_DOMAINS` y configurar el túnel con `http://127.0.0.1:8000`. Limpiar la configuración con `php artisan optimize:clear`.

`404` al probar el webhook con `curl -I`

`curl -I` utiliza `HEAD`, pero la ruta solo acepta `POST`. Usar el comando `curl -X POST` de esta guía.

Botón PayPal visible pero sin renderizar

Revisar la consola del navegador. No colocar directivas Blade como `@foreach` dentro de `@script`; recorrer los IDs de los planes desde JavaScript mediante `@js()`.

Pantalla blanca después de aprobar

Revisar primero la excepción del callback y el estado de la cola. La confirmación debe consultar PayPal hasta que la suscripción esté `ACTIVE` y usar URLs relativas para no perder la sesión entre `127.0.0.1` y el dominio del túnel.
