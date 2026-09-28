# Guía de estudio — Plataforma de Perfiles

### Documento para estudiar el código real del proyecto

---

## Cómo usar esta guía

Esto no es un manual de Laravel. Es un recorrido por **este** proyecto: cada vez que se explica un concepto, se dice en qué archivo está y en qué línea, para que puedas abrir el archivo al lado y leerlo tú mismo.

Los otros dos documentos del proyecto son:

- `docs/guia-seguridad.md` — el análisis de seguridad (activos, amenazas, clasificación de datos). Es la fuente de verdad del diseño.
- `docs/documentacion-tecnica.md` — las decisiones tomadas y cómo probarlas manualmente.

Las rutas de archivo son relativas a la raíz del proyecto.

---

# 1. RESUMEN DEL STACK

## 1.1 Lenguaje y framework

| Pieza | Versión | Dónde se declara |
|---|---|---|
| PHP | `^8.3` (en la máquina: 8.4.21; en el contenedor: 8.5) | `composer.json`, clave `require.php` |
| Laravel | 13.33.0 | `composer.lock` (`laravel/framework`) |
| Laravel Breeze | 2.4.2 | andamiaje de login/registro |
| Laravel Sail | 1.57.0 | entorno Docker |
| PHPUnit | 12.5.36 | pruebas automatizadas |
| Tailwind CSS | 3.4.19 | estilos (los trae Breeze) |
| Vite | 8.3.1 | empaquetado de assets |

Laravel es un framework MVC para PHP. La aplicación es **un monolito con renderizado en servidor**: no hay una API separada ni una SPA. El servidor genera el HTML completo en cada petición.

## 1.2 Laravel Sail: qué es y qué papel cumple aquí

**Sail no es una librería de la aplicación.** Es un conjunto de archivos de Docker que crean un entorno de desarrollo reproducible: en lugar de instalar PHP, sus extensiones y Node a mano, se levanta un contenedor con todo eso ya configurado.

El archivo que lo define es **`compose.yaml`** (raíz del proyecto):

- `compose.yaml:10-11` — declara **un solo servicio**, `laravel.test`. Se levanta solo el contenedor de la aplicación.
- `compose.yaml:13` — la imagen se construye desde `./vendor/laravel/sail/runtimes/8.5`. Es decir, el Dockerfile vive dentro del paquete `laravel/sail` instalado por Composer.
- `compose.yaml:17` — la imagen resultante se llama `sail-8.5/app`.
- `compose.yaml:21` — publica el puerto **80** de tu máquina contra el 80 del contenedor. Por eso la aplicación queda en <http://localhost>. (La línea 22 hace lo mismo con el 5173, que es el servidor de desarrollo de Vite para los assets.)

**Qué servicios levanta: solo uno.** Esto es una decisión deliberada. Sail, cuando lo instalas en modo no interactivo, añade por defecto MySQL, Redis, Mailpit y Selenium. Aquí se instaló con `php artisan sail:install --with=none`, porque la base de datos es SQLite (un archivo dentro del proyecto) y no hace falta ningún contenedor de base de datos. Menos piezas móviles.

El comando habitual para trabajar es `./vendor/bin/sail up -d` (arranca en segundo plano). Ese script es de macOS/Linux/WSL2; en Windows nativo funciona `docker compose up -d` y el resto de comandos como `docker compose exec laravel.test php artisan ...`.

## 1.3 Motor de base de datos: SQLite

**Motor actual: SQLite.** No es un servidor de base de datos como MySQL: es una biblioteca que guarda toda la base en **un solo archivo**, `database/database.sqlite`, dentro del proyecto. La conexión se llama a través de la extensión `pdo_sqlite` de PHP.

Dónde está configurado:

- `.env`, línea `DB_CONNECTION=sqlite`
- `.env.example:41` — el mismo valor, para que cualquiera que clone el proyecto parta de SQLite.
- **`DB_DATABASE` está deliberadamente comentado** (`.env.example:45`). Si no se define, Laravel usa por defecto `database/database.sqlite` (lo dice `config/database.php`, en la conexión `sqlite`). Esa ruta se resuelve igual en tu máquina y dentro del contenedor, donde el proyecto se monta en `/var/www/html`. Por eso la misma configuración funciona en los dos entornos sin tocar nada.

**Por qué SQLite:** la guía de seguridad lo define como motor *temporal* de desarrollo. No requiere instalar ni administrar ningún servidor, y para un trabajo de este alcance es suficiente.

**Cómo se cambiaría a MySQL o PostgreSQL:** editando **solo** el `.env`. `.env.example:48-59` ya trae los dos bloques listos, comentados:

```ini
# DB_CONNECTION=mysql
# DB_HOST=mysql
# DB_PORT=3306
# DB_DATABASE=plataforma_perfiles
# DB_USERNAME=sail
# DB_PASSWORD=password
```

Se descomentan, se comenta `DB_CONNECTION=sqlite`, y ya está. Que esto funcione sin tocar código se apoya en tres reglas que el proyecto respeta:

1. **Todo el acceso a datos es Eloquent o el query builder**, nunca SQL crudo. Comprueba: no hay ni una llamada a `DB::raw()` ni `whereRaw()` en `app/`.
2. **Las migraciones no usan nada específico de un motor.** Ojo al detalle del `enum`: `$table->enum('role', [...])` en `database/migrations/0001_01_01_000000_create_usuarios_table.php:39` genera un `ENUM` nativo en MySQL/PostgreSQL y un `VARCHAR` con restricción `CHECK` en SQLite. El comportamiento desde PHP es idéntico.
3. **No hay lógica de conexión en el código.** Todo sale del `.env`.

---

# 2. CÓMO FUNCIONA LARAVEL EN ESTE PROYECTO

## 2.1 El patrón MVC, aplicado aquí

Laravel organiza el código en **Modelo – Vista – Controlador**. La idea: la petición entra por una ruta, un controlador decide qué hacer, consulta o modifica datos a través de un modelo, y devuelve una vista (HTML).

### Dónde están las rutas

**`routes/web.php`** — las rutas de la aplicación. Es lo primero que deberías leer para entender el proyecto, porque es el mapa.

| Línea | Ruta | Qué hace |
|---|---|---|
| `web.php:8-10` | `GET /` | Página de bienvenida |
| `web.php:12-14` | `GET /dashboard` | Panel tras iniciar sesión |
| `web.php:29-38` | Grupo `/perfil` y `/perfil/tarjeta` | Perfil del usuario autenticado |
| `web.php:50-59` | Grupo `/admin/...` | Panel de administración |
| `web.php:61` | `require auth.php` | Carga las rutas de login/registro |

**`routes/auth.php`** — las rutas de autenticación, que genera Breeze. Están separadas por comodidad y agrupadas por middleware:

- `auth.php:14` — grupo `guest` (solo para quien **no** ha iniciado sesión): registro, login, recuperar contraseña.
- `auth.php:41` — grupo `auth` (solo para quien **sí** la ha iniciado): verificación de correo, cambio de contraseña, logout.

### Los controladores y sus vistas

Un **controlador** es una clase con métodos. Cada método atiende una ruta y devuelve algo (una vista o una redirección).

**`app/Http/Controllers/Auth/RegisteredUserController.php`** — el registro.

- `store()` (línea 31) es el método interesante. Recibe un `RegisterRequest` (línea 31) que ya validó todo, y dentro de una transacción (`DB::transaction`, línea 38) crea el usuario y su tarjeta.
- Vista que sirve: `resources/views/auth/register.blade.php` (la devuelve `create()`, línea 22).

**`app/Http/Controllers/PerfilController.php`** — ver y editar el perfil propio.

- `edit()` (línea 26) → vista `resources/views/perfil/edit.blade.php`
- `update()` (línea 41) → modifica los datos personales y financieros
- `destroy()` (línea 67) → baja voluntaria de la propia cuenta
- La vista `perfil/edit.blade.php` incluye cuatro parciales de `resources/views/perfil/partials/`: `datos.blade.php`, `tarjeta-resumen.blade.php`, `password.blade.php` y `eliminar-cuenta.blade.php`.

**`app/Http/Controllers/TarjetaController.php`** — la tarjeta, que vive en su propio endpoint.

- `edit()` (línea 20) → vista `resources/views/perfil/tarjeta.blade.php`
- `update()` (línea 27) → guarda el número cifrado

**`app/Http/Controllers/Admin/UsuarioController.php`** — el panel de administración.

- `index()` (línea 34) → vista `resources/views/admin/usuarios/index.blade.php`
- `destroy()` (línea 61) → elimina (con borrado lógico)

### Qué es Blade

Blade es el motor de plantillas de Laravel. Dos sintaxis que verás por todo el proyecto:

- `{{ $variable }}` — **imprime escapando el HTML**. Si el valor es `<script>alert(1)</script>`, el navegador muestra ese texto y no ejecuta nada. Es la razón por la que el proyecto es resistente a XSS sin hacer nada especial.
- `{!! $variable !!}` — imprime **sin escapar**. Es peligroso y **en este proyecto no se usa ni una sola vez** (puedes comprobarlo: `grep -rn "{!!" resources/views/` no devuelve nada).

Además, Blade permite **componentes**: etiquetas reutilizables. Los de Breeze están en `resources/views/components/` y se usan así:

```blade
<x-input-label for="email" value="Correo electrónico" />
<x-text-input id="email" name="email" type="email" />
<x-input-error :messages="$errors->get('email')" />
```

## 2.2 Middleware: qué es y cuáles se usan

Un **middleware** es código que se ejecuta *alrededor* de una petición, antes o después del controlador. Piensa en una cadena de porteros: la petición los atraviesa en orden, y cualquiera puede dejar pasar o cortar el paso.

Se aplican por ruta. Por ejemplo, `routes/web.php:50`:

```php
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
```

Eso significa: para todo lo que esté dentro de este grupo, primero pasa `auth` y luego `admin`.

### Los middleware de este proyecto

**`auth`** — es de Laravel, no lo escribimos nosotros. Comprueba que haya sesión iniciada; si no, redirige a `/login`.

**`guest`** — el contrario. Solo deja pasar a quien **no** ha iniciado sesión. Por eso el registro no es accesible estando dentro (`routes/auth.php:14`).

**`admin`** — nuestro. Definido en **`app/Http/Middleware/EnsureUserIsAdmin.php`**:

```php
// EnsureUserIsAdmin.php:21-25
$usuario = $request->user();

if ($usuario === null || ! $usuario->esAdministrador()) {
    abort(403);
}
```

Se aplica a todo el grupo `/admin` en `routes/web.php:50`. **Devuelve 403** a quien no sea administrador, aunque escriba la URL a mano.

**`no-admin`** — nuestro. Definido en **`app/Http/Middleware/EnsureUserIsNotAdmin.php`**:

```php
// EnsureUserIsNotAdmin.php:20-24
$usuario = $request->user();

if ($usuario !== null && $usuario->esAdministrador()) {
    return redirect()->route('admin.usuarios.index');
}
```

Hace lo contrario que el anterior: si quien entra es administrador, lo devuelve al panel. Es lo que implementa la regla de que **el administrador no tiene perfil propio**. Se aplica al grupo de perfil en `routes/web.php:29`.

Fíjate en que son **dos middlewares separados, no uno con un parámetro**. Cada uno expresa una regla distinta y se lee mejor así.

### Cómo se registran los alias

Para poder escribir `->middleware('admin')` en vez del nombre completo de la clase, hay que registrar un alias en **`bootstrap/app.php:23-26`**:

```php
$middleware->alias([
    'admin' => EnsureUserIsAdmin::class,
    'no-admin' => EnsureUserIsNotAdmin::class,
]);
```

`bootstrap/app.php` es el archivo de arranque de la aplicación en Laravel 11 y posteriores. Antes esto se hacía en un `Kernel.php` que ya no existe.

### El grupo `web`

Hay un conjunto de middleware que se aplica a **todas** las rutas de `routes/web.php` sin que las declares. Es el grupo `web`, definido por Laravel. En este proyecto contiene, en este orden:

1. `EncryptCookies` — cifra el valor de las cookies. Por eso la cookie de sesión se ve como una cadena base64 ilegible.
2. `AddQueuedCookiesToResponse`
3. `StartSession` — arranca la sesión.
4. `ShareErrorsFromSession` — hace que `$errors` esté disponible en las vistas.
5. `PreventRequestForgery` — **la protección CSRF** (ver sección 4.2).
6. `SubstituteBindings` — resuelve los parámetros de ruta a modelos (por ejemplo `{usuario}` → un objeto `User`).

## 2.3 Migración, modelo y seeder

Estos tres conceptos son la base de datos en Laravel. Se explican mejor con los archivos reales.

### Migración = la receta del esquema

Una **migración** es un archivo PHP que describe un cambio en la estructura de la base de datos. En vez de escribir `CREATE TABLE` a mano, escribes PHP y Laravel lo traduce al dialecto del motor que uses. Ventaja: el mismo archivo crea la tabla en SQLite, MySQL o PostgreSQL.

Ejemplo real, `database/migrations/2026_09_27_000001_create_tarjetas_table.php:17-38`:

```php
Schema::create('tarjetas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('usuario_id')->unique()->constrained('usuarios')->cascadeOnDelete();
    $table->text('numero_tarjeta');
    $table->string('ultimos_4_digitos', 4);
    $table->string('nombre_titular', 150);
    $table->string('fecha_vencimiento', 5);
    $table->timestamps();
    $table->softDeletes();
});
```

Cada migración tiene un método `up()` (aplicar el cambio) y un `down()` (revertirlo, línea 44). El nombre del archivo empieza por fecha (`2026_09_27_000001_`) porque Laravel las ejecuta **en orden alfabético**: así se garantiza que `usuarios` exista antes que `tarjetas`, que la referencia.

Comandos: `php artisan migrate` aplica las pendientes. La tabla `migrations` (que Laravel crea solo) lleva la cuenta de cuáles ya se ejecutaron.

### Modelo Eloquent = la tabla vista como objeto

Un **modelo** es una clase PHP que representa una tabla. Cada fila es un objeto, cada columna una propiedad. Así no escribes SQL: escribes `$usuario->email` y Laravel genera la consulta.

`app/Models/User.php`:

```php
class User extends Authenticatable      // línea 12
{
    use HasFactory, Notifiable, SoftDeletes;   // línea 15
    protected $table = 'usuarios';             // línea 17
```

Fíjate en `protected $table = 'usuarios'` (línea 17): Laravel, por convención, buscaría una tabla llamada `users` (plural en inglés de la clase). Como aquí la tabla se llama `usuarios`, hay que decírselo explícitamente.

Tres cosas más del modelo que conviene entender:

**`$fillable` (línea 31-41)** — la *lista blanca* de campos que se pueden asignar en masa. Explicado en detalle en la sección 4.4.

**`$hidden` (línea 48-51)** — campos que nunca salen al convertir el modelo a array o JSON (`$usuario->toArray()`).

**`casts()` (línea 56-65)** — convierte columnas a tipos de PHP automáticamente al leerlas y al escribirlas:

```php
'email_verified_at' => 'datetime',
'password' => 'hashed',
'ingresos_mensuales' => 'decimal:2',
```

**Relaciones.** `app/Models/User.php:72-75`:

```php
public function tarjeta(): HasOne
{
    return $this->hasOne(Tarjeta::class, 'usuario_id');
}
```

Esto dice: "un usuario tiene una tarjeta". Gracias a eso puedes escribir `$usuario->tarjeta` y Laravel hace el `JOIN` por ti. La inversa está en `app/Models/Tarjeta.php:54-57` (`belongsTo`).

### Seeder = datos de prueba

Un **seeder** es una clase que llena la base de datos con datos iniciales. Sirve para que cualquiera del grupo tenga las mismas cuentas de prueba.

`database/seeders/UsuarioSeeder.php` crea 5 cuentas: 1 administradora (línea 30-47) y 4 usuarios. El método `crear()` (línea 126) es el que hace el trabajo:

```php
private function crear(array $datos, array $tarjeta, string $role = 'usuario'): User
{
    $usuario = User::create([...$datos, 'password' => Hash::make(self::PASSWORD)]);  // 128-132

    $usuario->role = $role;   // 136 — asignación directa, NO masiva
    $usuario->save();         // 137

    $usuario->tarjeta()->create([...]);   // 141-145
```

`database/seeders/DatabaseSeeder.php:11-13` es el seeder principal: llama a `UsuarioSeeder`. Se ejecuta con `php artisan db:seed` o junto a las migraciones con `php artisan migrate --seed`.

> Ojo: `UsuarioSeeder` usa `User::create`, así que **ejecutarlo dos veces duplica las cuentas**. No es idempotente. Para volver al estado limpio se usa `php artisan migrate:fresh --seed`, que borra todas las tablas y las reconstruye.

---

# 3. MODELO DE BASE DE DATOS

El proyecto tiene **dos tablas propias** (`usuarios` y `tarjetas`) más las que crea Laravel para su funcionamiento (`sessions`, `cache`, `jobs`, `migrations`).

## 3.1 Tabla `usuarios`

Definida en `database/migrations/0001_01_01_000000_create_usuarios_table.php:22-48`.

| Columna | Tipo | Línea | Para qué |
|---|---|---|---|
| `id` | bigint, PK, autoincremental | 23 | Identificador interno. **Nunca se usa la cédula como id en una URL** |
| `nombre_completo` | varchar(150) | 26 | |
| `numero_identificacion` | varchar(20), **unique** | 27 | No se cifra (ver 3.4) |
| `email` | varchar(150), **unique** | 28 | Credencial de acceso |
| `password` | varchar(255) | 29 | Guarda el **hash**, no la contraseña |
| `telefono` | varchar(20) | 30 | |
| `direccion` | varchar(255) | 31 | |
| `ocupacion` | varchar(100) | 34 | |
| `ingresos_mensuales` | **decimal(12,2)** | 35 | `decimal`, nunca `float` |
| `entidad_bancaria` | varchar(100) | 36 | |
| `role` | **enum('usuario','administrador')** | 39 | Por defecto `'usuario'` |
| `email_verified_at` | timestamp nullable | 43 | Plomería de Laravel |
| `remember_token` | varchar(100) nullable | 44 | Plomería de Laravel |
| `created_at` / `updated_at` | timestamps | 46 | Auditoría |
| `deleted_at` | timestamp nullable | 47 | **Borrado lógico** (ver 3.3) |

Dos detalles que suelen preguntarse:

**¿Por qué `decimal` y no `float`?** Los `float` son binarios y no representan exactamente los decimales: `0.1 + 0.2` no da `0.3` exacto. Con dinero eso produce céntimos que aparecen y desaparecen. `decimal(12,2)` guarda 12 dígitos con 2 decimales exactos.

**¿Por qué `enum` y no una tabla `roles`?** Porque solo hay dos roles. Una tabla aparte, con su clave foránea y su consulta extra, sería sobre-ingeniería para este caso. Está razonado en `docs/guia-seguridad.md`, sección 10.

## 3.2 Tabla `tarjetas`

Definida en `database/migrations/2026_09_27_000001_create_tarjetas_table.php:17-38`.

| Columna | Tipo | Línea | Para qué |
|---|---|---|---|
| `id` | bigint, PK | 18 | |
| `usuario_id` | bigint, **FK unique** → `usuarios.id` | 21 | La relación 1 a 1 |
| `numero_tarjeta` | **text** | 26 | **Único campo cifrado** del sistema |
| `ultimos_4_digitos` | varchar(4) | 31 | En claro, para enmascarar |
| `nombre_titular` | varchar(150) | 33 | Puede diferir del dueño de la cuenta |
| `fecha_vencimiento` | varchar(5) | 34 | Formato `MM/YY` |
| `created_at` / `updated_at` | timestamps | 36 | |
| `deleted_at` | timestamp nullable | 37 | Borrado lógico |

### La relación entre las dos tablas

`tarjetas.usuario_id` es una **clave foránea**: apunta a `usuarios.id`. Se declara así:

```php
// create_tarjetas_table.php:21
$table->foreignId('usuario_id')->unique()->constrained('usuarios')->cascadeOnDelete();
```

Traducido: `foreignId` crea la columna; `constrained('usuarios')` añade la restricción de que el valor debe existir en `usuarios.id` (la base de datos lo verifica); `cascadeOnDelete()` borra la tarjeta si se borra el usuario; y **`unique()`** es lo que hace que la relación sea **1 a 1** — un usuario no puede tener dos filas en `tarjetas`.

> Matiz: como el borrado de usuarios es lógico (se marca `deleted_at` y la fila sigue existiendo), el `cascadeOnDelete` casi nunca se dispara. Está ahí como red de seguridad por si algún día se borra una fila de verdad.

**Por qué las tarjetas están en una tabla aparte.** Es una decisión de seguridad, explicada en `docs/guia-seguridad.md` sección 9: aísla el único dato "Restringido" en un modelo pequeño, y sobre todo hace que cualquier consulta sobre `usuarios` **no pueda** arrastrar por accidente un número de tarjeta.

## 3.3 Soft delete (borrado lógico)

**Qué es.** Borrar de verdad una fila (`DELETE FROM usuarios WHERE id = 3`) la elimina para siempre. El *borrado lógico* no borra: marca la fila como eliminada y hace que el resto de la aplicación la ignore. Se puede recuperar, y queda rastro para auditoría.

**Cómo está implementado aquí.**

1. **La columna**: `deleted_at` (`create_usuarios_table.php:47` y `create_tarjetas_table.php:37`). Es un timestamp que vale `NULL` mientras la fila está viva.

2. **El trait en el modelo**: `app/Models/User.php:15` y `app/Models/Tarjeta.php:17`:

   ```php
   use SoftDeletes;
   ```

   El trait viene de `Illuminate\Database\Eloquent\SoftDeletes`. Al usarlo, Eloquent activa un *global scope*: **añade automáticamente `WHERE deleted_at IS NULL` a todas las consultas** de ese modelo.

3. **El método de borrado**: `$usuario->delete()` (`Admin/UsuarioController.php:91` y `PerfilController.php:79`) no ejecuta un `DELETE`, sino un `UPDATE ... SET deleted_at = <ahora>`.

**Qué cambia en las consultas.** Esta es la parte que hay que entender bien, porque el proyecto juega con ello de forma deliberada:

| Consulta | Resultado |
|---|---|
| `User::all()` | Solo los **vivos**. El scope excluye los eliminados |
| `User::withTrashed()->get()` | **Todos**, vivos y eliminados |
| `User::onlyTrashed()->get()` | Solo los **eliminados** |
| `User::find(3)` (si el 3 está eliminado) | `null` |
| `User::withTrashed()->find(3)` | El objeto, con `deleted_at` puesto |
| `$usuario->trashed()` | `true` si esa fila está eliminada |

Se ve muy claro en el panel de administración, `app/Http/Controllers/Admin/UsuarioController.php:38-45`:

```php
$usuarios = User::query()
    ->withTrashed()                          // 39 — incluye los eliminados
    ->where('role', 'usuario')               // 42 — la cuenta admin no se lista
    ->select([...User::columnasListadoAdmin(), 'deleted_at'])   // 43
    ->orderBy('nombre_completo')
    ->paginate(15);
```

Y en la vista, `resources/views/admin/usuarios/index.blade.php:64-67` decide si pinta la etiqueta "Eliminado" consultando **`$usuario->trashed()`**.

**Un efecto bonito y gratis:** como el scope excluye las filas borradas, **una cuenta eliminada no puede iniciar sesión**. No hay ningún `if` que lo impida: el proveedor de autenticación busca el usuario por correo, la consulta lleva el `WHERE deleted_at IS NULL`, no lo encuentra, y el login falla. Es un ejemplo de cómo una decisión de modelado sustituye a código defensivo.

**Y otro que hay que conocer:** el borrado lógico **no libera** el correo ni la cédula, porque la fila sigue existiendo y las columnas son `unique`. No se puede registrar una cuenta nueva con el correo de una cuenta eliminada. Es un efecto secundario discutible, documentado como limitación en `docs/documentacion-tecnica.md`.

## 3.4 El cast `encrypted`

**Qué es un cast.** Ya lo viste en 2.3: una instrucción en el modelo que dice "esta columna no es lo que parece, conviértela". `'ingresos_mensuales' => 'decimal:2'` hace que al leer te devuelva un decimal en vez de un string.

**El cast `encrypted`** hace algo mucho más interesante: **cifra al escribir y descifra al leer, de forma transparente**.

Está declarado en **`app/Models/Tarjeta.php:47`**:

```php
protected function casts(): array
{
    return [
        'numero_tarjeta' => 'encrypted',
    ];
}
```

**Cómo funciona por dentro.** El cast engancha dos momentos del ciclo de vida del atributo, en `Illuminate\Database\Eloquent\Concerns\HasAttributes`:

- **Al asignar** (`set`): antes de guardar el valor en el array interno del modelo, llama a `castAttributeAsEncryptedString()`, que cifra el valor con la clase `Crypt`.
- **Al leer** (`get`): hace lo contrario, lo descifra.

`Crypt` usa **AES-256-CBC** con la `APP_KEY` del `.env`. Lo que se guarda en la base de datos no es el número, sino un sobre cifrado que incluye el vector de inicialización, el valor cifrado y un **MAC** (un código de autenticación). Eso es lo que se ve si consultas la tabla en crudo:

```
eyJpdiI6InMvSFBoYTZUbk81bHdXSVpVNU5IckE9PSIsInZhbHVlIjoiRUYwdHY2UFJLUC..
```

Ese texto es base64 de un JSON con forma `{"iv": "...", "value": "...", "mac": "...", "tag": ""}`. El `mac` es importante: permite detectar si alguien manipuló el criptograma. Si se altera, el descifrado lanza una excepción `DecryptException` en vez de devolver basura.

**Qué implica, en la práctica:**

- Si haces `SELECT numero_tarjeta FROM tarjetas` en un cliente de base de datos, **no ves el número**. Aunque alguien robe un volcado completo de la base de datos, sin la `APP_KEY` no obtiene nada. Lo comprobamos en las pruebas: intentar descifrar con otra clave lanza excepción (`tests/Feature/Seguridad/CifradoTarjetaTest.php`).
- Dentro de la aplicación, en cambio, se trabaja con normalidad: `$tarjeta->numero_tarjeta` devuelve `4111111111111111`.
- **La `APP_KEY` es, por tanto, tan sensible como la base de datos.** Si se cambia, todo lo cifrado con la anterior deja de poder descifrarse. Por eso vive solo en `.env`, que está en `.gitignore`, y `.env.example` no la lleva.
- **Solo este campo está cifrado.** La guía es explícita: se cifra únicamente el dato clasificado como "Restringido". El resto (correo, cédula, ingresos) se protege con autorización, no con cifrado.
- **El cifrado es aleatorio:** cifrar dos veces el mismo número da resultados distintos (por el vector de inicialización aleatorio). Eso impide que, mirando la base de datos, se deduzca qué cuentas comparten tarjeta.

**El campo compañero.** `tarjetas.ultimos_4_digitos` (`create_tarjetas_table.php:31`) se guarda **en claro a propósito**. Es lo que permite mostrar `**** **** **** 1234` sin descifrar en cada render. El accesor que lo formatea está en `app/Models/Tarjeta.php:66-71`:

```php
protected function numeroEnmascarado(): Attribute
{
    return Attribute::get(fn (): string => '**** **** **** '.$this->ultimos_4_digitos);
}
```

Convención de Laravel: un método `numeroEnmascarado()` se expone como la propiedad `$tarjeta->numero_enmascarado`. Cuatro dígitos sueltos no son explotables sin el resto del número, y la fecha de vencimiento sola tampoco.

---

# 4. SEGURIDAD: QUÉ SE IMPLEMENTÓ Y DÓNDE

Cada control, con el archivo y el mecanismo exacto.

## 4.1 Hash de contraseñas

**Qué es.** Una contraseña no se guarda ni en texto plano ni cifrada. Se guarda un **hash**: el resultado de una función de un solo sentido. No se puede deshacer. Para verificar un login, se hashea lo que escribe el usuario y se comparan los hashes.

La diferencia con el cifrado importa: un cifrado se revierte con la clave; un hash, no. Si alguien roba la base de datos, no obtiene contraseñas.

Se usa **bcrypt**, que además es *lento* a propósito (a diferencia de MD5 o SHA1, que son rapidísimos y por eso sirven para probar millones de combinaciones por segundo). bcrypt incluye una **sal aleatoria** por hash, así que dos personas con la misma contraseña tienen hashes distintos.

**Dónde está:**

| Sitio | Archivo:línea | Qué hace |
|---|---|---|
| Registro | `Auth/RegisteredUserController.php:45` | `Hash::make($datos['password'])` |
| Cambio de contraseña | `Auth/PasswordController.php` (Breeze) | `Hash::make($validated['password'])` |
| Restablecer contraseña | `Auth/NewPasswordController.php` (Breeze) | `forceFill(['password' => Hash::make(...)])` |
| Red de seguridad | `app/Models/User.php:61` | cast `'password' => 'hashed'` |

El cast `'hashed'` es una segunda barrera: si algún día se escribe la contraseña por otra ruta del código, el cast la hashea automáticamente al asignarla. Y no la hashea dos veces: comprueba antes si el valor ya es un hash (`Hash::isHashed()`).

El coste de bcrypt se configura con `BCRYPT_ROUNDS=12` en `.env.example:28` (12 es el valor por defecto de Laravel). Más rondas = más lento de calcular = más caro de atacar por fuerza bruta. En las pruebas se baja a 4 (`phpunit.xml:23`) para que la suite no tarde una eternidad.

**Cómo comprobarlo:** `SELECT password FROM usuarios` debe devolver algo como `$2y$12$...`. El prefijo `$2y$` identifica bcrypt.

## 4.2 Protección CSRF

**Qué es.** CSRF (*Cross-Site Request Forgery*) es un ataque en el que un sitio malicioso hace que **tu navegador** envíe una petición a la aplicación aprovechando que tienes la sesión abierta. Los navegadores adjuntan las cookies automáticamente a cualquier petición hacia un dominio, venga de donde venga la petición. Sin protección, bastaría con que visitaras una página con un formulario oculto apuntando a `/perfil`.

**Cómo se defiende Laravel.** Un **token** secreto, distinto por sesión, incrustado en cada formulario. El servidor lo compara: si no coincide, rechaza con **419**.

**Dónde está:**

1. **El middleware**: es parte del grupo `web` y se llama `Illuminate\Foundation\Http\Middleware\PreventRequestForgery`. Verás ese nombre en Laravel 13; en versiones anteriores se llamaba `ValidateCsrfToken` y antes `VerifyCsrfToken`. Se aplica a todas las rutas de `routes/web.php` sin que haya que declararlo.

2. **El token en el formulario**: directiva `@csrf` en cada formulario. Ejemplo, `resources/views/auth/register.blade.php:10`.

3. **Los formularios que no son POST**: HTML solo soporta GET y POST. Para `PATCH`, `PUT` y `DELETE` se añade un campo oculto: `@method('patch')`, `@method('delete')`. Ver `resources/views/admin/usuarios/index.blade.php:84`.

**Comprobación rápida:**

```bash
curl -i -X POST http://localhost/login -d "email=x@y.com&password=loquesea"
```

Responde **419**, aunque las credenciales fueran correctas. Y no modifica nada.

**Matiz importante para entender las pruebas:** Laravel **desactiva** esta verificación cuando detecta que corre en un entorno de tests, para no obligar a enviar el token en cada petición de prueba. Por eso en `tests/Feature/Seguridad/CsrfTest.php` se cambia el entorno de la aplicación a uno distinto de `testing` antes de lanzar la petición sin token: sin ese truco, la prueba no probaría nada.

## 4.3 Autorización por rol

**Qué es.** No basta con que el usuario esté autenticado; hay que comprobar *qué puede hacer*. Aquí hay dos roles: `usuario` y `administrador`.

**La regla de oro** (está en la guía de seguridad): *ocultar un botón no es seguridad*. Que la vista no pinte el enlace de administración es comodidad visual; la barrera real tiene que estar en el servidor.

### Cómo se comprueba el rol

Todo pasa por un único método, `app/Models/User.php:81-84`:

```php
public function esAdministrador(): bool
{
    return $this->role === 'administrador';
}
```

Tener una sola fuente de verdad evita que se compare el rol de formas distintas en sitios distintos.

### Las dos barreras del panel de administración

**Barrera 1 — el middleware.** `EnsureUserIsAdmin.php:21-25` devuelve 403 si no es administrador. Se aplica al grupo en `routes/web.php:50`. Un usuario normal que escriba `http://localhost/admin/usuarios` a mano recibe **403 Acceso denegado**.

**Barrera 2 — la policy.** Cada acción del controlador revalida con `Gate::authorize`:

- `Admin/UsuarioController.php:36` → `Gate::authorize('viewAny', User::class)`
- `Admin/UsuarioController.php:63` → `Gate::authorize('delete', $usuario)`

`viewAny` está definido en `app/Policies/UserPolicy.php:41-44` y también exige ser administrador. Dos barreras en vez de una: si alguien añadiera mañana una ruta nueva y olvidara el middleware, la policy seguiría protegiendo.

> **Cómo se conecta la policy con el modelo sin registrarla:** Laravel la descubre por convención de nombres. `App\Models\User` → `App\Policies\UserPolicy`. No hay que declararlo en ningún sitio.

### La restricción del administrador sobre las rutas de perfil

El administrador **no tiene perfil propio**: el panel sirve solo para administrar usuarios. Esto se implementa con el middleware `no-admin` (`EnsureUserIsNotAdmin.php`), aplicado al grupo de perfil en `routes/web.php:29`.

Resultado: un administrador que intente entrar a `/perfil`, `/perfil/tarjeta` o enviar un `PATCH /perfil` es **redirigido a `/admin/usuarios`** en vez de ver la página.

Y en la navegación, los enlaces a perfil se ocultan con `@unless (auth()->user()->esAdministrador())` — `resources/views/layouts/navigation.blade.php:21`, `54`, `91` y `112`. Insistimos: eso es cosmético, la barrera es el middleware.

### El límite del propio panel

`Admin/UsuarioController.php:75-79` impide que un administrador se elimine a sí mismo desde el panel (evita quedarse fuera del sistema por error), y `:85-89` rechaza eliminar **cualquier** cuenta administradora aunque se envíe su id a mano.

## 4.4 Que un usuario no pueda tocar el perfil de otro

Esta es la vulnerabilidad **IDOR** (*Insecure Direct Object Reference*): que un usuario consiga leer o modificar el recurso de otro cambiando un identificador. Aquí se ataca con tres mecanismos.

### a) Rutas sin identificador (el más fuerte)

Mira las rutas de perfil, `routes/web.php:30-37`:

```php
Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
Route::patch('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
Route::delete('/perfil', [PerfilController::class, 'destroy'])->name('perfil.destroy');
Route::get('/perfil/tarjeta', [TarjetaController::class, 'edit'])->name('perfil.tarjeta.edit');
Route::put('/perfil/tarjeta', [TarjetaController::class, 'update'])->name('perfil.tarjeta.update');
```

**Ninguna lleva `{id}`.** No existe `/perfil/3`. El recurso es siempre el usuario autenticado, resuelto en el controlador con `$request->user()` (`PerfilController.php:28`, `:43`, `:73`).

Es la forma más contundente de cortar un IDOR: **no hay identificador que manipular**. Si escribes `/perfil/3` recibes un 404, porque esa ruta no existe.

### b) La policy como segunda capa

Aun así, cada acción revalida la pertenencia con `UserPolicy`:

- `PerfilController.php:30` → `Gate::authorize('view', $usuario)`
- `PerfilController.php:45` → `Gate::authorize('update', $usuario)`
- `PerfilController.php:75` → `Gate::authorize('delete', $usuario)`

Y la comprobación de propiedad es `app/Policies/UserPolicy.php:55-58`:

```php
private function esElMismo(User $autenticado, User $recurso): bool
{
    return $autenticado->id === $recurso->id;
}
```

Compara el **id**, no la cédula ni el correo. Es la comprobación literal que pide la guía: "que el `id` editado pertenezca al usuario autenticado".

### c) Lista blanca contra asignación masiva (escalada vertical)

Hay otra escalada, esta **vertical**: convertirse en administrador.

El peligro se llama **asignación masiva**. Cuando escribes `User::create($request->all())` o `$usuario->fill($datos)`, Laravel mete en el modelo todo lo que venga en el array. Si `role` fuese asignable, bastaría con añadir `role=administrador` al POST del registro.

La defensa está en **`app/Models/User.php:31-41`**:

```php
protected $fillable = [
    'nombre_completo',
    'numero_identificacion',
    'email',
    'password',
    'telefono',
    'direccion',
    'ocupacion',
    'ingresos_mensuales',
    'entidad_bancaria',
];
```

**`role` no está en la lista, y el comentario de las líneas 19-27 advierte de que no debe estarlo nunca.** Cualquier `role` que llegue en una petición se descarta silenciosamente.

Tres piezas más cierran el círculo:

1. **El registro fija el rol en el servidor** (`RegisteredUserController.php:57`): `$usuario->role = 'usuario';` — asignación **directa**, no masiva, con un valor literal. Y no hay ninguna regla de validación para `role` en `RegisterRequest.php` (fíjate en las líneas 49-70: no aparece).
2. **El perfil tampoco lo acepta**: `PerfilUpdateRequest.php:30-45` no tiene regla para `role`, y el controlador escribe solo `$request->validated()` (`PerfilController.php:49`), que devuelve **únicamente** los campos con regla.
3. **La tarjeta igual**: `Tarjeta::$fillable` (`app/Models/Tarjeta.php:29-34`) **no incluye `usuario_id`**. La tarjeta se crea y se lee siempre a través de la relación `$usuario->tarjeta()`, que fija la clave foránea en código (`TarjetaController.php:33`). Enviar `usuario_id` en el POST no hace nada.

**En resumen:** para cambiar un rol hace falta escribir código explícito. Así se hace en el seeder (`UsuarioSeeder.php:136`), que es código de confianza, y no hay ninguna otra vía.

## 4.5 Cifrado del número de tarjeta

Ya explicado en detalle en **3.4**. Resumen del mecanismo:

| Aspecto | Detalle |
|---|---|
| Dónde | `app/Models/Tarjeta.php:47` → `'numero_tarjeta' => 'encrypted'` |
| Algoritmo | AES-256-CBC |
| Clave | `APP_KEY` del `.env` (generada con `php artisan key:generate`) |
| Alcance | **Solo este campo.** Ningún otro dato se cifra |
| Integridad | El criptograma lleva un MAC: si se manipula, el descifrado falla |

## 4.6 Validación del número de tarjeta (Luhn)

**Qué es.** El algoritmo de Luhn calcula un dígito de control: el último dígito de una tarjeta se deriva matemáticamente de los anteriores. Sirve para detectar números mal tecleados o inventados **antes** de guardarlos. No comprueba que la tarjeta exista ni que tenga fondos — eso es cosa del emisor.

**Cómo funciona.** Se recorren los dígitos de derecha a izquierda duplicando uno de cada dos; si el doble pasa de 9, se le resta 9. Si la suma total es múltiplo de 10, el número es válido.

**Dónde está:** `app/Rules/Luhn.php`. Es una **regla de validación propia**, una clase que implementa `ValidationRule`. El algoritmo está en `pasaLuhn()` (línea 54):

```php
private function pasaLuhn(string $numero): bool
{
    $suma = 0;
    $duplicar = false;

    for ($i = strlen($numero) - 1; $i >= 0; $i--) {   // 59 — de derecha a izquierda
        $digito = (int) $numero[$i];

        if ($duplicar) {
            $digito *= 2;              // 63
            if ($digito > 9) {
                $digito -= 9;          // 66
            }
        }

        $suma += $digito;
        $duplicar = ! $duplicar;       // 71 — alterna
    }

    return $suma % 10 === 0;           // 74
}
```

Antes de aplicar Luhn, `validate()` (línea 25) hace dos comprobaciones extra:

- **Longitud** entre 12 y 19 dígitos (línea 29), el rango de ISO/IEC 7812.
- **Dígitos todos iguales** (línea 38): `0000000000000000` pasa Luhn matemáticamente (la suma da 0, múltiplo de 10) pero ningún emisor real lo emite.

**Dónde se usa:**

- `RegisterRequest.php:67` → `'numero_tarjeta' => ['required', 'string', new Luhn]`
- `TarjetaUpdateRequest.php:41` → igual

**Detalle útil:** `prepareForValidation()` (`RegisterRequest.php:35-42` y `TarjetaUpdateRequest.php:26-33`) limpia el número antes de validarlo, quitando espacios y guiones. Así `4111 1111 1111 1111` y `4111-1111-1111-1111` se aceptan y se guardan como `4111111111111111`. El número se normaliza **antes** de validar, no después.

## 4.7 Manejo de sesiones (cookies)

**Qué es.** La sesión es cómo el servidor recuerda quién eres entre peticiones. HTTP no tiene memoria: sin sesión, cada petición sería anónima. El servidor guarda los datos de sesión y le da al navegador un identificador en una cookie.

**Dónde se configura:** `config/session.php` y el bloque de sesión del `.env`.

**Los tres atributos que exige la guía:**

| Atributo | Dónde | Qué protege |
|---|---|---|
| `HttpOnly` | `config/session.php:189` | JavaScript no puede leer la cookie. Si hubiera un XSS, no podría robar la sesión con `document.cookie` |
| `Secure` | `config/session.php:176` | El navegador solo la envía por HTTPS, nunca por HTTP plano |
| `SameSite=Strict` | `config/session.php:209` | La cookie no viaja en peticiones iniciadas desde otro sitio. Suma una capa frente a CSRF |

Fíjate en un detalle de diseño: el valor por defecto está puesto **del lado seguro**.

```php
// config/session.php:176
'secure' => env('SESSION_SECURE_COOKIE', true),
```

El segundo argumento de `env()` es el valor que se usa **si la variable no está definida**. Al ponerlo en `true`, si alguien despliega la aplicación y se olvida de esa variable, la cookie sale segura igualmente. El valor por defecto de Laravel aquí era *ninguno* (cookie no segura); este proyecto lo cambió a `true`. Lo mismo con `same_site`, que pasa de `lax` a `strict` (línea 209).

**Otros ajustes de sesión:**

- **Driver:** `SESSION_DRIVER=database` (`.env.example:66`). Las sesiones se guardan en la tabla `sessions` (`create_usuarios_table.php:56-63`), no en archivos ni en la cookie. El navegador solo lleva el identificador.
- **Caducidad:** `SESSION_LIFETIME=120` (`.env.example:69`) — minutos de inactividad. Una sesión sin caducidad es una vulnerabilidad listada en la guía.
- **Cookie cifrada:** el middleware `EncryptCookies` del grupo `web` cifra el valor de la cookie, por eso se ve como base64.

**Un matiz práctico que conviene conocer:** `Secure` significa que el navegador solo manda la cookie por HTTPS. En desarrollo servimos por HTTP plano en `http://localhost`. Chrome, Edge y Firefox tratan `localhost` como *origen seguro* y aceptan la cookie igualmente, así que funciona. Pero si accedes por otro host sobre HTTP (por ejemplo `http://192.168.1.50`), el navegador descarta la cookie y **el login falla sin mensaje de error claro**. Está documentado en `.env.example` junto a la variable.

## 4.8 Otros controles de seguridad

### Rate limiting del login (dos capas)

La guía nombra dos ataques distintos y cada uno necesita su límite.

**Capa 1 — fuerza bruta contra una cuenta.** Es de Breeze, `app/Http/Requests/Auth/LoginRequest.php`:

- `ensureIsNotRateLimited()` (línea 61) comprueba `RateLimiter::tooManyAttempts($key, 5)` — **5 intentos**.
- La clave es `email|ip` (`throttleKey()`, línea 82): el contador es por correo **y** por IP juntos.
- Al superarlo, lanza un error de validación con `trans('auth.throttle')` (línea 72), cuyo texto en español está en `lang/es/auth.php`.

**Capa 2 — credential stuffing.** Es nuestra, `app/Providers/AppServiceProvider.php:47-52`:

```php
private function configurarLimiteDeLogin(): void
{
    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(20)->by($request->ip());
    });
}
```

Con clave **solo por IP**, 20 por minuto. ¿Por qué hace falta si ya está la capa 1? Porque el credential stuffing consiste en probar un diccionario de contraseñas contra **muchísimos correos distintos** desde una misma máquina. Cada correo abre su propio contador en la capa 1, así que ese ataque no la activa nunca. El límite por IP sí lo ve.

Se activa en `routes/auth.php:25-26`, añadiendo `->middleware('throttle:login')` a la ruta POST del login.

### Manejo de errores genérico

Un mensaje de error detallado (un *stack trace*) revela la versión del framework, rutas de archivos internos, la estructura de la base de datos y a veces fragmentos de consultas. Es información gratis para un atacante.

- **`APP_DEBUG=false`** en `.env.example:10`. Con esto, Laravel no muestra el detalle técnico.
- **Páginas de error propias** en `resources/views/errors/`: `401`, `403`, `404`, `419`, `429`, `500`, `503`. Todas heredan de `errors/layout.blade.php`.
- Detalle de diseño: ese layout **no usa `@vite`, ni la sesión, ni el usuario autenticado, ni la base de datos**, y lleva el CSS en línea. Tiene que poder renderizarse aunque el fallo que provocó el error sea justamente uno de esos subsistemas.

El mensaje es deliberadamente genérico: no confirma si un recurso existe ni por qué se denegó.

### Datos mínimos en el panel de administración

El listado muestra solo **nombre, correo y cédula**. Nunca ingresos, entidad bancaria ni tarjeta.

La parte interesante es **cómo** se consigue, `Admin/UsuarioController.php:43`:

```php
->select([...User::columnasListadoAdmin(), 'deleted_at'])
```

`columnasListadoAdmin()` está en `app/Models/User.php:92-95` y devuelve `['id', 'nombre_completo', 'email', 'numero_identificacion']`. El `select()` explícito hace que las columnas sensibles **no se consulten siquiera**. No es que se oculten en la vista: no llegan a cargarse en memoria, así que no pueden filtrarse por accidente ni aunque alguien añadiera mañana un `{{ $usuario->ingresos_mensuales }}` a la plantilla.

### Escape de salida (XSS)

**Qué es.** XSS es inyectar código JavaScript que se ejecuta en el navegador de otra persona. El escenario concreto de este proyecto: alguien se registra con un `nombre_completo` como `<script>...</script>` y ese nombre se pinta después en el **listado del administrador**, que es justo donde al atacante le gustaría ejecutar código con más privilegios que los suyos.

**Cómo se defiende:** todas las plantillas usan `{{ }}`, que **escapa el HTML**. Un `<` se convierte en `&lt;`, y el navegador lo muestra como texto en vez de interpretarlo como etiqueta. El control es automático; lo que hay que vigilar es **no desactivarlo** con `{!! !!}`. En este proyecto no se usa ni una vez.

Fíjate también en este detalle de diseño: **el escape es cosa de la capa de salida**. El dato se guarda tal cual lo escribió el usuario; se escapa al mostrarlo. No se "mutila" la entrada.

### Consultas parametrizadas (inyección SQL)

Todo el acceso a datos usa Eloquent o el query builder, que construyen consultas con **parámetros separados** de la sentencia. Un valor como `' OR 1=1 --` se trata como texto, no como SQL.

La regla práctica: **nunca concatenar strings para formar SQL**. Puedes comprobar que el proyecto la respeta:

```bash
grep -rn "DB::raw\|whereRaw\|selectRaw\|DB::select(" app/    # no devuelve nada
```

### La `APP_KEY` y el archivo `.env`

`.env` contiene la `APP_KEY`, que es la clave que cifra y descifra el número de tarjeta. Si se filtra junto con un volcado de la base de datos, el cifrado deja de proteger nada.

- `.env` está en **`.gitignore`** (verificado) y nunca se versiona. Compruébalo con `git check-ignore -v .env`.
- **`.env.example` SÍ se versiona y NO lleva `APP_KEY`** (`APP_KEY=` vacío, línea 7, con un comentario explicando por qué). Cada entorno genera la suya con `php artisan key:generate`.

### El CVV no se solicita ni se almacena

El código de seguridad de la tarjeta es el dato que más directamente habilita un fraude, y no se puede almacenar sin incumplir PCI-DSS.

Aquí no existe en ninguna capa: **no hay campo en el formulario, no hay regla de validación y no hay columna en la base de datos**. Como los controladores escriben solo `$request->validated()`, enviarlo en el POST no tiene ningún efecto. El formulario de registro lo dice explícitamente al usuario en `resources/views/auth/register.blade.php:101`.

---

# 5. Dónde seguir estudiando

## Las pruebas como documentación viva

La carpeta **`tests/Feature/Seguridad/`** es probablemente el mejor sitio para entender los controles, porque cada archivo demuestra que uno funciona:

| Archivo | Qué demuestra |
|---|---|
| `AccesoPanelAdminTest.php` | Que un usuario normal recibe 403 y un administrador no |
| `IdorTest.php` | Que no se puede tocar el perfil ni la tarjeta de otro |
| `CifradoTarjetaTest.php` | Que el número está cifrado en la base y que sin la `APP_KEY` no se descifra |
| `BorradoLogicoTest.php` | Soft delete, y que una cuenta eliminada no puede iniciar sesión |
| `RateLimitLoginTest.php` | Los dos límites de intentos de login |
| `EscaladaPrivilegiosTest.php` | Que `role` no se puede enviar por POST |
| `XssTest.php` | Que un `<script>` en el nombre se escapa |
| `HashPasswordTest.php` | Que se guarda bcrypt y no la contraseña |
| `CsrfTest.php` | Que un POST sin token recibe 419 |
| `CookiesSesionTest.php` | Los atributos reales de la cookie |
| `ErroresGenericosTest.php` | Que un error interno no filtra detalles |
| `tests/Unit/LuhnTest.php` | El algoritmo de Luhn, con números válidos e inválidos |

Se ejecutan con **`php artisan test`**. Corren sobre **SQLite en memoria** (`phpunit.xml:31-32`), así que no tocan la base de datos de desarrollo.

## Un recorrido sugerido

Si quieres entender el proyecto de arriba a abajo, este orden funciona bien:

1. **`routes/web.php`** completo — es el mapa. Identifica qué controlador atiende cada ruta.
2. **`app/Models/User.php`** — el modelo central. Fíjate en `$fillable`, los casts y la relación `tarjeta()`.
3. **`app/Http/Middleware/`** — los dos middleware propios. Son cortos y muy expresivos.
4. **`app/Http/Controllers/`** — empieza por `RegisteredUserController::store()` y sigue por `PerfilController`, que es el más completo.
5. **`app/Http/Requests/`** — la validación. Aquí se ve qué campos acepta cada formulario y cuáles no.
6. **`database/migrations/`** — el esquema. Y `app/Models/Tarjeta.php` para el cast `encrypted`.
7. **`resources/views/`** — abre `auth/register.blade.php` y `admin/usuarios/index.blade.php` con el HTML ya en la cabeza.

## Comandos que te van a hacer falta

```bash
php artisan route:list --except-vendor     # todas las rutas, con el controlador que las atiende
php artisan test                           # la suite completa
php artisan test --filter=Seguridad        # solo las pruebas de seguridad
php artisan migrate:fresh --seed           # reinicia la base con los datos de demostración
php artisan tinker                         # consola interactiva: prueba Eloquent en vivo
```

Dentro de `tinker` puedes comprobar cosas como:

```php
DB::table('tarjetas')->first()->numero_tarjeta;   // el criptograma
App\Models\Tarjeta::first()->numero_tarjeta;      // el número descifrado
App\Models\User::withTrashed()->count();          // incluyendo eliminados
```

La diferencia entre las dos primeras líneas es, en una frase, todo lo que hace el cast `encrypted`.
