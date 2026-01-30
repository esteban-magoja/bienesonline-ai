# Usuario de Testing i18n

**Propósito:** Usuario para testing de traducciones y vistas protegidas

## Credenciales

- **Email:** `i18n@test.local`
- **Password:** `testing123`
- **Username:** `i18n-tester`
- **Nombre:** i18n Tester

## Uso

Este usuario puede ser utilizado para:
- ✅ Verificar traducciones en vistas protegidas
- ✅ Testing de formularios bilingües
- ✅ Validación de permisos y roles
- ✅ Automatización de tests con curl/Playwright

## Login con curl

```bash
# Autenticarse
curl -c cookies.txt -X POST http://127.0.0.1:8000/login \
  -d "email=i18n@test.local" \
  -d "password=testing123"

# Acceder a páginas protegidas
curl -b cookies.txt http://127.0.0.1:8000/dashboard
curl -b cookies.txt http://127.0.0.1:8000/settings/profile
```

## Notas

- **NO usar en producción**
- Usuario creado: 2025-11-21
- ID en BD: 7
- Email NO es real (dominio .local)

---

_Para eliminar:_
```bash
php artisan tinker --execute="User::where('email', 'i18n@test.local')->delete();"
```
