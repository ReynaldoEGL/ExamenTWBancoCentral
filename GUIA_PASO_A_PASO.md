# Guía paso a paso — Panel administrativo del Banco Central (Persona 2)

Esta guía corresponde únicamente a la responsabilidad acordada como **Persona 2: panel administrativo del Banco Central y despliegue en Coolify**. La Persona 1 conserva el esquema SQL, las funciones atómicas y la API core; la Persona 3 hace la Sucursal; la Persona 4 hace el ATM.

El kit no es un Laravel completo: contiene los archivos propios del panel para copiar dentro de una instalación Laravel nueva. Laravel y sus dependencias se generan con Composer.

---

## 0. Qué tiene que estar listo y qué debes pedir

Antes de empezar, verifica con la Persona 1:

1. Que el **script SQL 01** se haya ejecutado una sola vez en el proyecto Supabase principal y que existan estas tablas: `nodes`, `profiles`, `api_keys`, `users_accounts`, `transactions` y `node_cash_movements`.
2. Que exista una cuenta de administrador en Supabase Auth y un registro en `public.profiles` con el mismo UUID y `rol = 'admin'`.
3. El Project URL, la clave pública/anon, el host/puerto/usuario del **Session pooler** y la contraseña de PostgreSQL. Pide que la contraseña te la compartan por un canal seguro, no en el repositorio.
4. Que tu panel usará las tablas centrales directamente a través de una conexión PostgreSQL del servidor. La API core se debe integrar en el mismo repo y la Persona 1 debe conservar `routes/api.php`, sus controladores, middleware y funciones SQL.
5. Que las API Keys se generan como cadena aleatoria, el panel almacena `hash('sha256', $key)` en hexadecimal y la API core valida con exactamente el mismo algoritmo. El kit genera claves con prefijo `atm_` para cajeros y `suc_` para sucursales.
6. Que la Persona 1 arreglará los tipos del OpenAPI de ATM: `nodes.id` y `transactions.node_id` son UUID, por tanto en OpenAPI se documentan como `type: string`, `format: uuid`, no como enteros.
7. Que el contrato devuelve el `estado` de los nodos como `activo`/`inactivo` (la columna SQL real se llama `activo` y es booleana) y que la API central actualiza saldo, efectivo del nodo y transacción de forma atómica en retiros y depósitos.

**No crees ni ejecutes de nuevo el SQL 01 si la Persona 1 ya lo ejecutó. No uses `php artisan migrate:fresh` contra la base central.** Este panel consume las tablas ya creadas y no debe reconstruirlas.

### Responsabilidades de las otras personas

- **Persona 1:** Base principal Supabase, restricciones/RLS/triggers, funciones SQL atómicas, API core y contrato definitivo.
- **Tú, Persona 2:** Panel administrativo, creación/gestión de nodos, generación/revocación de API Keys, asignación administrativa de efectivo, historial/reportes y despliegue del servicio Laravel en Coolify.
- **Persona 3:** Base local de Sucursal y app administrativa; alta de clientes llamando la API del Central, historial y reportes.
- **Persona 4:** Base local del ATM, app Express, efectivo físico local, saldo/retiros/depósitos llamando la API del Central.

---

## 1. Preparar Windows

Abre **PowerShell**. Ejecuta:

```powershell
php -v
composer --version
git --version
```

Para crear el proyecto con Laravel 13, usa PHP 8.3 o superior y Composer 2. Si no tienes Laravel instalado globalmente, no importa: Composer creará el proyecto.

Comprueba que PHP tenga habilitado `pdo_pgsql`, porque Laravel se conectará a PostgreSQL:

```powershell
php -m | Select-String "pdo_pgsql"
```

Si no aparece, habilita `extension=pdo_pgsql` en el `php.ini` que utiliza la terminal, reinicia PowerShell y repite la comprobación. En Docker, la imagen del kit instala esa extensión durante la construcción.

## 2. Crear la aplicación Laravel

En PowerShell, navega a la carpeta donde guardas las prácticas y crea el proyecto:

```powershell
cd C:\Users\$env:USERNAME\Desktop\PracticasWeb
composer create-project laravel/laravel banco-central "^13.0"
cd banco-central
php artisan --version
```

Verifica que el comando muestre la versión de Laravel y que dentro de `banco-central` existan `app`, `routes`, `resources`, `config`, `public` y `artisan`.

Si Composer informa que tu versión de PHP no satisface el proyecto, actualiza PHP o crea el proyecto con una versión Laravel compatible que el profesor autorice. Si usas otra versión de Laravel, valida los requisitos de PHP y la compatibilidad del Dockerfile antes de desplegar.

## 3. Copiar los archivos del kit

1. Descarga y descomprime `banco-central-panel-kit.zip` en una carpeta temporal.
2. Desde PowerShell, copia **el contenido** de la carpeta del kit hacia la raíz de `banco-central`. Ajusta las rutas si lo extrajiste en otra ubicación:

```powershell
$kit = "$env:USERPROFILE\Downloads\banco-central-panel-kit"
$proyecto = "$env:USERPROFILE\Desktop\PracticasWeb\banco-central"
Get-ChildItem $kit -Force | Copy-Item -Destination $proyecto -Recurse -Force
```

3. Si Windows solicita combinar carpetas, permite la combinación. El `routes/web.php` del kit reemplaza las rutas web de la instalación nueva.
4. **No reemplaces `routes/api.php` que después añadirá la Persona 1.** Este kit no implementa el API core.
5. Copia también `README.md`, `.env.central.example`, `Dockerfile`, `.dockerignore`, la carpeta `docker` y los fragmentos de configuración. `.env.central.example` es una plantilla adicional; no debe renombrarse ni confundirse con tu `.env` real.

La estructura que debes observar al final incluye:

```text
banco-central/
├── app/Http/Controllers/Admin/
├── app/Http/Middleware/EnsureCentralAdmin.php
├── app/Support/Money.php
├── config/
│   ├── database.php.snippet
│   └── services.php.snippet
├── docker/000-default.conf
├── public/css/central-admin.css
├── resources/views/admin/
├── resources/views/auth/central-login.blade.php
├── resources/views/layouts/central.blade.php
├── routes/web.php
├── Dockerfile
├── .dockerignore
└── .env.central.example
```

## 4. Configurar las variables de entorno

Abre el archivo `.env` que se generó en la raíz de Laravel. **No lo publiques ni lo subas a GitHub.** Conserva el resto de variables que Laravel necesita y ajusta, como mínimo, estas:

```dotenv
APP_NAME="Banco Central"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

SUPABASE_URL=https://PROJECT_REF.supabase.co
SUPABASE_PUBLIC_KEY=sb_publishable_REEMPLAZAR_POR_LA_CLAVE_PUBLICA

DB_CONNECTION=pgsql
DB_HOST=HOST_EXACTO_DEL_SESSION_POOLER
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.PROJECT_REF
DB_PASSWORD=CONTRASENA_POSTGRES
DB_SSLMODE=require

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stack
```

Reemplaza los ejemplos por los valores reales. `SUPABASE_PUBLIC_KEY` debe ser la clave pública/anon del proyecto; nunca uses `service_role` ni una clave secreta en el navegador. `DB_PASSWORD` es la contraseña de PostgreSQL y no es la misma que la clave pública.

### Dónde obtener los datos de Supabase

1. Abre el proyecto Supabase del **Banco Central** (no la base local de la Sucursal ni la del ATM).
2. Da clic en **Connect**.
3. Selecciona **Session pooler** y copia el host, usuario, puerto y base de datos que muestre. El puerto suele ser `5432`; no inventes el host ni el sufijo del usuario.
4. En el dashboard del proyecto busca la clave pública/anon en la sección **API Keys** o equivalente y copia el Project URL.
5. Si no recuerdan la contraseña de PostgreSQL, la Persona 1 puede restablecerla desde los ajustes de Database y compartirla de manera segura.

No uses el **Transaction pooler, puerto 6543**, para la conexión principal de Laravel/PDO: el ORM puede usar funciones de sesión y prepared statements que ese modo no soporta. Supabase recomienda Session pooler para clientes persistentes o cuando la conectividad directa IPv6 no funciona. La configuración de Laravel debe exigir SSL. <https://supabase.com/docs/guides/database/connecting-to-postgres> <https://supabase.com/docs/guides/getting-started/quickstarts/laravel>

### Configurar `config/services.php`

Abre `config/services.php`. Dentro del array que retorna el archivo, agrega el bloque del archivo `config/services.php.snippet`:

```php
'supabase' => [
    'url' => env('SUPABASE_URL'),
    'key' => env('SUPABASE_PUBLIC_KEY'),
],
```

No reemplaces el contenido completo de `services.php`; agrega solo esa entrada junto a los otros servicios.

### Configurar SSL en `config/database.php`

Abre `config/database.php`, localiza la entrada `pgsql` y confirma que tenga esta opción dentro de su array:

```php
'sslmode' => env('DB_SSLMODE', 'require'),
```

Si existe con valor predeterminado `prefer`, cámbialo a `require`. No dupliques la misma clave dentro del array.

### Generar la clave de Laravel y limpiar caché

Ejecuta:

```powershell
php artisan key:generate
php artisan optimize:clear
```

La primera instrucción debe escribir una `APP_KEY` en `.env`. Verifica que ya no esté vacía. Si Laravel no puede conectarse a Supabase, no continúes con la lógica del panel: revisa host/usuario/contraseña, SSL y `pdo_pgsql` primero.

## 5. Crear el administrador en Supabase Auth

El formulario `/login` no crea usuarios. Se autentica contra Supabase Auth y después comprueba que el UUID de ese usuario tenga rol `admin` en `public.profiles`.

1. Abre Supabase del Banco Central y entra a **Authentication → Users**.
2. Crea el usuario para la persona que administrará el banco. Para las pruebas, usa una cuenta cuya confirmación de correo esté resuelta.
3. Copia el UUID del usuario creado.
4. Abre **SQL Editor**, crea una consulta y ejecuta el siguiente SQL cambiando el UUID y el nombre:

```sql
insert into public.profiles (id, nombre, rol, node_id)
values ('UUID_DEL_USUARIO_AUTH', 'Administrador Central', 'admin', null)
on conflict (id) do update
set nombre = excluded.nombre,
    rol = 'admin',
    node_id = null;
```

5. Verifica el perfil:

```sql
select id, nombre, rol, node_id
from public.profiles
where id = 'UUID_DEL_USUARIO_AUTH';
```

El ID del perfil debe ser exactamente el ID generado por Supabase Auth y `rol` debe ser `admin`. Si la Persona 1 ya hizo este paso, no lo repitas; solo pide que confirme el perfil.

La autenticación del panel usa el endpoint Auth de Supabase desde el servidor y comprueba el rol por medio de la conexión protegida a PostgreSQL. El navegador nunca recibe la contraseña de PostgreSQL.

## 6. Primera ejecución local

Desde la raíz del proyecto, abre PowerShell y ejecuta:

```powershell
php artisan optimize:clear
php artisan route:list
php artisan serve
```

Abre en Chrome:

```text
http://127.0.0.1:8000/login
```

Inicia sesión con la cuenta creada en Supabase Auth. Debe abrirse el panel del Banco Central. Si la aplicación lanza un error 500, revisa la terminal de `artisan serve` y los archivos de `storage/logs`.

**No ejecutes `php artisan migrate` ni `php artisan migrate:fresh` contra la base central como parte de esta guía.** El script SQL de la Persona 1 es el contrato de tablas. El kit usa sesiones y caché locales por archivo para no depender de tablas Laravel adicionales.

## 7. Qué puedes gestionar en el panel

### Tablero `/admin`

Muestra recuento de sucursales/cajeros, nodos activos, cantidad de cuentas, suma de saldos, efectivo registrado en nodos, operaciones del día y últimas transacciones. No modifica los saldos.

### Nodos `/admin/nodes`

1. Abre **Nodos** y pulsa **Registrar nodo**.
2. Selecciona `Sucursal` o `Cajero automático`.
3. Escribe nombre y ubicación.
4. Selecciona un perfil existente de `public.profiles` como responsable.
5. Introduce efectivo inicial (puede ser `0.00`).
6. Pulsa **Crear nodo**.
7. El sistema genera un UUID para el nodo e inserta un movimiento `ingreso` en `node_cash_movements` si el efectivo inicial es mayor que cero.

Si no hay perfiles en la lista, pide a la Persona 1 que cree al responsable en Supabase Auth y `public.profiles`. Para cumplir RN12, registra siempre un responsable por nodo.

Al editar un nodo puedes cambiar nombre, ubicación, responsable y estado. Desactivar el nodo lo deja marcado como inactivo; la API core debe comprobar este estado y responder `403 NODO_INACTIVO`. No se permite cambiar el efectivo desde el formulario general, para que no se pierda la auditoría.

### API Keys

1. Entra al detalle de un nodo.
2. Pulsa **Generar / renovar API Key**.
3. Copia la clave completa inmediatamente y guárdala de manera segura para entregarla al destinatario.
4. La pantalla enseña solamente el prefijo en visitas posteriores. La base conserva `key_hash` SHA-256; no existe forma de recuperar la clave original.
5. Si una clave se compromete, pulsa **Revocar**. Al regenerar, el panel desactiva cualquier clave activa previa para cumplir con el índice de una clave activa por nodo.

**Pide a la Persona 1 que confirme el mismo algoritmo:** en Laravel/PHP, `hash('sha256', $key)` produce el texto hexadecimal que debe compararse con `api_keys.key_hash`. Pasa a la Persona 4 una API Key de prueba asociada específicamente al nodo tipo `cajero`, no tu contraseña de PostgreSQL ni una `service_role`.

### Efectivo asignado

En el detalle del nodo, utiliza Movimiento de efectivo para registrar un `ingreso` o `egreso` administrativo. La operación bloquea la fila del nodo en una transacción, verifica que un egreso no supere el efectivo disponible, actualiza `nodes.efectivo_disponible` e inserta el movimiento de auditoría.

Este formulario solo gestiona asignaciones administrativas. Los retiros y depósitos de clientes se deben ejecutar a través de la API core, que debe actualizar saldo, efectivo oficial y transacción atómicamente. Pide a la Persona 1 que acuerde si esos cambios operativos también deben quedar registrados en `node_cash_movements`; no los dupliques desde el panel.

### Transacciones `/admin/transactions`

Muestra el historial global, uniendo `transactions` con `nodes` y las cuentas de origen/destino para mostrar números de cuenta. Puedes filtrar por texto, tipo, nodo y fechas.

El panel no tiene botones para modificar ni borrar transacciones. El script central debe seguir siendo responsable del trigger de inmutabilidad.

### Reportes `/admin/reports`

Permite consultar totales de operaciones y montos por tipo, agrupación por nodo y efectivo registrado actual por sucursal/cajero. Los reportes son consultas de la base central, no de las bases locales.

---

## 8. Integración con la Persona 1 y la Persona 4

Antes de la demo, acuerden con la Persona 1 el `openapi.yaml` definitivo. El contrato que compartió la Persona 4 dice que todas las peticiones envían `X-API-Key`, que el Central valida el estado del nodo y que los retiros/depósitos modifican simultáneamente saldo, efectivo y transacción.

Comprueba estas correspondencias:

| Base central SQL | Contrato que debe entregar el API |
|---|---|
| `nodes.id` UUID | `Nodo.id` como `string` con `format: uuid` |
| `transactions.node_id` UUID | `Transaccion.node_id` como `string` con `format: uuid` |
| `nodes.activo` boolean | Respuesta `estado: activo` o `inactivo` |
| `api_keys.key_hash` SHA-256 hex | Middleware central calcula el mismo SHA-256 de `X-API-Key` |
| `users_accounts.id` UUID + `numero_cuenta` texto | Respuestas API exponen el número de cuenta; internamente enlazan por UUID |
| `transactions.saldo_resultante` | Respuesta de operación comunica el saldo posterior correctamente |

Pide a la Persona 1 que confirme los siguientes endpoints mínimos del contrato que ya recibió del ATM: `GET /api/node/me`, `GET /api/accounts/{numero}`, `POST /api/withdraw` y `POST /api/deposit`. El panel administrativo no implementa esos endpoints; la Persona 1 debe conservarlos y probarlos.

La Persona 4 necesita de ti/Persona 1 una key de cajero y la URL base de la API una vez desplegada. No entregues hasta que se confirme que la key pertenece a un nodo `cajero` activo con efectivo asignado.

## 9. Pruebas de aceptación para tu parte

Ejecuta cada prueba con datos de laboratorio y anota el resultado:

1. Acceder con perfil `admin`: debe abrir el panel.
2. Acceder con usuario Auth que no tiene perfil `admin`: debe rechazar el acceso.
3. Registrar un nodo con responsable y efectivo inicial de `10000.00`: debe aparecer en la lista y el efectivo debe coincidir.
4. Consultar `node_cash_movements`: debe existir una fila `ingreso` por los `$10,000.00` iniciales.
5. Generar una key: debe mostrarse completa solo al crearla; en tabla aparece prefijo, estado y fecha.
6. Renovar key: la anterior debe quedar revocada y solo debe existir una activa para ese nodo.
7. Registrar un egreso mayor que el efectivo: debe rechazarlo y dejar el saldo de efectivo intacto.
8. Desactivar un nodo y pedir a la Persona 1 que pruebe su API Key: debe obtener `403` de nodo inactivo.
9. Una vez creada la cuenta de `$1,000.00` desde la Sucursal, tu panel debe mostrarla en el recuento y en el historial tras la operación inicial acordada.
10. Una vez retirados `$300.00` en el ATM, el panel debe reflejar saldo central `$700.00` y la transacción del retiro.
11. Intentar modificar/borrar manualmente una transacción en pruebas controladas debe ser bloqueado por el trigger de la base central.

No hagas el retiro de demo modificando manualmente el saldo desde el panel ni el SQL Editor: eso no demuestra la integración.

## 10. Subir el código a GitHub

Antes de subir:

```powershell
git status
```

Confirma que `.env` no aparece como archivo que vaya a enviarse. Si aparece, añádelo a `.gitignore` y no lo incluyas. Un `.env.example` sin secretos sí puede versionarse.

Ejemplo de primeros commits:

```powershell
git add app routes resources public config/services.php config/database.php Dockerfile docker .dockerignore README.md GUIA_PASO_A_PASO.md .env.central.example
 git commit -m "feat: panel administrativo del banco central"
```

No copies literalmente el comando si alguna carpeta aún no existe; revisa `git status` antes de confirmar.

Como la Persona 1 trabaja en el mismo repositorio para la API core, acuerden ramas o commits separados. No reemplaces su `routes/api.php`, controladores, middleware ni scripts SQL cuando integres los cambios del panel.

## 11. Desplegar en Coolify

Este kit trae un `Dockerfile` y la configuración de Apache para servir Laravel desde `public/`, no desde la raíz del proyecto.

1. Confirma que el repo central esté en GitHub y que `Dockerfile`, `docker/000-default.conf` y `.dockerignore` estén en la rama que se desplegará.
2. Abre el panel de Coolify y crea una **Application** desde el repositorio Git.
3. Selecciona la rama principal y el método de construcción **Dockerfile**.
4. Verifica que Coolify detecte el Dockerfile del repositorio. El contenedor escucha en el puerto `80`; configura el puerto expuesto como `80`.
5. En **Environment Variables**, define las variables de producción. Como mínimo:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMINIO_REAL
APP_KEY=base64:CLAVE_GENERADA_PARA_LARAVEL
SUPABASE_URL=https://PROJECT_REF.supabase.co
SUPABASE_PUBLIC_KEY=CLAVE_PUBLICA_DEL_PROYECTO
DB_CONNECTION=pgsql
DB_HOST=HOST_REAL_DEL_SESSION_POOLER
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=USUARIO_REAL_DEL_POOLER
DB_PASSWORD=CONTRASENA_POSTGRES
DB_SSLMODE=require
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
```

6. Para `APP_KEY`, ejecuta localmente `php artisan key:generate --show` y copia la salida completa a Coolify. Mantén la misma clave entre redespliegues; no publiques su valor.
7. Guarda las variables y despliega. Si modificas variables, vuelve a desplegar o reiniciar el recurso para aplicar el nuevo entorno.
8. Revisa los logs de construcción y ejecución. Asigna el dominio y activa HTTPS desde Coolify.
9. Abre `https://DOMINIO_REAL/login`, inicia sesión y prueba el panel.
10. Haz un cambio inocuo, por ejemplo corregir un texto de ayuda, envíalo a GitHub y verifica que se ejecute el despliegue continuo.

No configures `APP_DEBUG=true` en producción. Mantén el `.env`, contraseña de base y claves privadas fuera de GitHub. Las variables de Coolify se gestionan dentro del panel de la aplicación.

## 12. Problemas comunes

### `could not find driver` o `could not find driver (Connection: pgsql)`

La extensión `pdo_pgsql` no está instalada o habilitada. Comprueba `php -m`; en Docker, revisa la etapa de construcción del Dockerfile.

### `connection refused`, timeout o error de SSL

Vuelve a copiar el host/usuario del **Session pooler** desde **Connect**. Comprueba puerto `5432`, contraseña y `DB_SSLMODE=require`. No escribas un host `aws-0-...` supuesto: usa el valor exacto de tu proyecto.

### El login siempre dice que no hay acceso de administrador

Comprueba Authentication → Users, la contraseña/correo y que `public.profiles.id` sea el mismo UUID que `auth.users.id`; revisa que `rol` sea exactamente `admin` en minúsculas.

### `relation public.profiles does not exist` o faltan tablas

Estás conectado a un proyecto/base equivocados, o la Persona 1 aún no ejecutó el SQL 01. No intentes arreglarlo con `migrate:fresh`: confirma primero el proyecto Supabase y el script central.

### No aparecen responsables al crear un nodo

Faltan filas en `public.profiles`. Pide a la Persona 1 que registre el usuario mediante Supabase Auth y cree su perfil. La tabla `profiles` no es el mismo sistema que la tabla `users_accounts`.

### La API dice `API_KEY_INVALIDA` después de generar una key

Comprueba con la Persona 1 que la API core calcula SHA-256 hexadecimal del valor completo recibido por `X-API-Key`, compara con `api_keys.key_hash` y que el panel no esté consultando otro proyecto. Asegúrate de copiar la clave completa de la pantalla de generación, no solo el prefijo.

### El ATM muestra un ID incompatible

El SQL usa UUID. Pide que el contrato OpenAPI y la serialización de API usen cadenas UUID. No conviertas la columna ni fuerces `id` a entero en el panel.

### Coolify muestra 500, 403 o una página sin CSS

Revisa los logs primero. Verifica `APP_KEY`, las variables, permisos de `storage` y `bootstrap/cache`, el dominio/HTTPS y que el DocumentRoot de Apache sea `/var/www/html/public`. Para una clave incorrecta de administrador, confirma el perfil `admin`.

---

## 13. Límite de alcance y estado esperado

Al finalizar tu responsabilidad, desde el navegador deberás poder iniciar sesión como admin, registrar/editar/desactivar nodos, asignar responsables, emitir/revocar claves, registrar asignaciones de efectivo, consultar transacciones globales y ver reportes. La app debe estar desplegada en Coolify y leer la base central.

No es tu responsabilidad rehacer la API core de la Persona 1 ni la lógica de efectivo local del ATM de la Persona 4. Sí es tu responsabilidad comprobar, en la demostración conjunta, que tus vistas reflejen las operaciones que esos nodos procesan correctamente a través de la API.
