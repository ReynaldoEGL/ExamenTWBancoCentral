# Kit del panel administrativo — Banco Central (Persona 2)

Este kit añade el **panel web administrativo** a una aplicación Laravel nueva. Está alineado con el SQL compartido para `public.nodes`, `public.profiles`, `public.api_keys`, `public.users_accounts`, `public.transactions` y `public.node_cash_movements`.

## Alcance

Incluye login por Supabase Auth (solo perfiles con rol `admin`), tablero, administración de nodos, generación/revocación de API Keys, gestión auditada del efectivo asignado, historial de transacciones, reportes y configuración Docker para Coolify.

**No incluye la API core del Banco Central** (`routes/api.php`, middleware para `X-API-Key`, creación de cuentas, retiros ni depósitos). Esos componentes corresponden a la Persona 1. No sobrescriba `routes/api.php` ni el SQL de la Persona 1.

## Requisitos de desarrollo

- PHP 8.3 o superior para Laravel 13.
- Composer 2.
- Git.
- Proyecto Supabase del Banco Central con el script SQL ya ejecutado.
- Usuario creado mediante Supabase Auth y registro correspondiente en `public.profiles` con `rol = 'admin'`.

## Instalación rápida

1. Cree el proyecto: `composer create-project laravel/laravel:^13.0 banco-central`.
2. Entre: `cd banco-central`.
3. Descomprima y copie el contenido de este kit dentro de la raíz del proyecto, conservando las carpetas. Cuando Windows pregunte, combine carpetas; reemplace `routes/web.php` si es el proyecto nuevo. **No reemplace `routes/api.php`**.
4. Copie las variables de `.env.central.example` a `.env`, complete los valores reales de Supabase y ejecute `php artisan key:generate`.
5. Agregue el bloque de `config/services.php.snippet` dentro del array de `config/services.php`.
6. Verifique que la conexión `pgsql` de `config/database.php` tenga `'sslmode' => env('DB_SSLMODE', 'require')`.
7. Configure `SESSION_DRIVER=file`, `CACHE_STORE=file` y `QUEUE_CONNECTION=sync`.
8. No ejecute `php artisan migrate:fresh` contra Supabase. Este panel usa las tablas creadas por el SQL acordado.
9. Ejecute `php artisan optimize:clear`, luego `php artisan serve` y visite `http://127.0.0.1:8000/login`.

## Crear el usuario administrador

1. En Supabase, abra **Authentication → Users** y agregue/cree el usuario de prueba con correo y contraseña.
2. Copie el UUID del usuario.
3. En **SQL Editor**, inserte el perfil (sustituya los valores):

```sql
insert into public.profiles (id, nombre, rol, node_id)
values ('UUID_DEL_USUARIO_AUTH', 'Administrador Central', 'admin', null)
on conflict (id) do update
set nombre = excluded.nombre,
    rol = 'admin',
    node_id = null;
```

No inserte manualmente filas en `auth.users`; use Supabase Auth Dashboard.

## Conexión a PostgreSQL

Desde Supabase abra **Connect → Session pooler** y copie host, usuario, base y puerto. Para el pooler compartido normalmente el puerto es `5432` y el usuario tiene forma `postgres.PROJECT_REF`; use los valores exactos que muestra el panel. Active SSL (`DB_SSLMODE=require`). No use Transaction pooler (6543) para esta conexión Laravel/ORM.

La contraseña de PostgreSQL y la clave pública de Supabase son distintas. El panel necesita la clave pública para el endpoint Auth y la conexión PostgreSQL para consultar las tablas del núcleo. Nunca ponga una clave `service_role`/secret en el navegador o en Git. Las credenciales de PostgreSQL solo van en el servidor.

## Pruebas locales

```powershell
php artisan optimize:clear
php artisan route:list
php artisan serve
```

Pruebe:

- Login con usuario Auth que tiene `rol=admin`.
- Login con usuario Auth sin perfil admin: debe denegarse.
- Crear una sucursal y un cajero con responsable y efectivo inicial.
- Generar la API Key y copiarla una sola vez.
- Revocar la clave y verificar que su prefijo aparezca revocado.
- Añadir ingreso/egreso de efectivo; un egreso mayor al efectivo disponible debe ser rechazado.
- Ver el historial y reportes tras ejecutar operaciones de prueba desde la API de la Persona 1.

## Despliegue con Coolify

1. Envíe el repositorio a GitHub sin `.env`.
2. En Coolify cree una Application ligada al repositorio y rama principal.
3. Seleccione build por Dockerfile; exponga el puerto `80`.
4. Configure variables de entorno de producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://SU-DOMINIO`, `APP_KEY`, `SUPABASE_URL`, `SUPABASE_PUBLIC_KEY`, `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE=postgres`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE=require`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`, `LOG_CHANNEL=stderr`.
5. Genere la `APP_KEY` localmente con `php artisan key:generate --show` (o use la salida de `php artisan key:generate` en un entorno temporal) y cópiela como variable secreta a Coolify.
6. Guarde variables, despliegue y revise los logs. Asigne dominio y HTTPS.
7. Conecte el despliegue automático del proveedor Git si está disponible; después de cada push, confirme el deployment y visite `/login`.

El `Dockerfile` usa Apache con `DocumentRoot` apuntando a `public/`; no expone la raíz del proyecto. Mantenga `APP_DEBUG=false` en producción.

## Avisos de integración para la Persona 1 y la Persona 4

- El SQL define `nodes.id` y `node_id` como UUID. El OpenAPI del ATM adjunto declara algunos como `integer`; cambie esos esquemas a `type: string`, `format: uuid` y actualice ejemplos.
- El generador de claves de este panel usa `atm_` para cajeros, `suc_` para sucursales y `hash('sha256', $key)` en hexadecimal. La API core debe usar exactamente el mismo hash para buscar `api_keys.key_hash`.
- En `GET /api/node/me`, la API debe transformar el booleano `nodes.activo` a `estado: "activo"` o `"inactivo"` y devolver el ID UUID como cadena.
- La API core debe crear el historial inicial al abrir cuenta y ejecutar retiros/depósitos en transacciones atómicas. El panel no implementa esas operaciones.
- Las asignaciones administrativas se registran en `node_cash_movements`. La Persona 1 debe decidir si también registrará ahí los movimientos físicos de retiros y depósitos; debe mantener coherencia con la interpretación de auditoría que use el equipo.

## Limitaciones del kit

- Está diseñado como punto de partida de práctica; debe probarse contra la versión exacta de Laravel y el proyecto Supabase del equipo.
- El saldo agregado del tablero es informativo; el panel no permite editar saldos de clientes.
- No reemplaza pruebas de autorización, HTTPS, copias de seguridad, control de acceso al servidor ni revisión del núcleo bancario.
