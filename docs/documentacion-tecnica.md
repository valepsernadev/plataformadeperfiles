# Documentación técnica — Plataforma de Perfiles

### Grupo de Desarrollo · Trabajo práctico de ciberseguridad

---

## 0. Qué es este documento y cómo leerlo

Este documento describe **qué se construyó, con qué herramientas y por qué**, y sobre todo **qué decisiones de seguridad se tomaron y contra qué amenaza concreta de la guía responden**.

El documento compañero, [`guia-seguridad.md`](./guia-seguridad.md), es la fuente de verdad del proyecto: define la clasificación de la información, los activos, las amenazas y el modelo de entidades. Aquí no se repite ese análisis; se explica cómo el código lo implementa y dónde verificarlo.

La sección **6 (pruebas manuales)** está pensada para que cualquiera del grupo pueda reproducir la demostración frente al docente sin leer el código: cada prueba dice qué hacer y qué debería pasar si el control funciona.

> **Convención de este documento:** cada decisión de seguridad se presenta como *Activo → Amenaza → Control → Dónde está → Cómo se comprueba*. Si algo no se pudo verificar, se dice explícitamente en la sección 8.

---

## 1. Stack: herramientas, versiones y por qué cada una

| Pieza | Versión | Por qué se eligió |
|---|---|---|
| **PHP** | 8.4 (local) / 8.5 (contenedor) | Requisito de Laravel 13 (`^8.3`). |
| **Laravel** | 13.33.0 | Monolito con renderizado en servidor. Ver justificación abajo. |
| **Laravel Sail** | 1.57.0 | Entorno Docker reproducible, sin configuración manual. |
| **Laravel Breeze** | 2.4.2 (stack Blade) | Andamiaje de registro/login con hash, CSRF y validación ya integrados. |
| **SQLite** | vía `pdo_sqlite` | Motor temporal de desarrollo; cambiar a MySQL/PostgreSQL es editar el `.env`. |
| **Tailwind CSS** | 3.4.19 | Estilos que trae Breeze. No se añadió nada de diseño propio. |
| **Vite** | 8.3.1 | Empaquetado de assets, integrado con Laravel. |
| **PHPUnit** | 12.5.36 | Pruebas automatizadas de los controles de seguridad. |

### 1.1 Por qué Laravel como monolito

La guía lo pide explícitamente en su sección 8: *"backend + frontend renderizado por el servidor, sin separar en API + SPA — decisión tomada para evitar sobre-ingeniería en un ejercicio de este alcance"*.

La ventaja de seguridad, más allá del alcance, es que **no hay una segunda superficie que proteger**. Una API + SPA obliga a resolver dos veces la autenticación (token en el cliente + sesión en el servidor), añade CORS, y mueve parte de la lógica de autorización al navegador, donde el usuario la controla. Con Blade, la autorización vive únicamente en el servidor y el cliente nunca recibe datos que no deba ver.

### 1.2 Por qué Sail

La guía asigna la infraestructura a otro equipo y pide que desarrollo no se ocupe de ella. Sail da un contenedor con PHP, las extensiones necesarias y Node ya resueltos: `docker compose up` y el entorno existe.

**Decisión: un único servicio.** El `compose.yaml` tiene solo el contenedor `laravel.test`. Como la base de datos es SQLite (un archivo dentro del proyecto), no hace falta ningún contenedor de base de datos. Se evitó a propósito arrastrar MySQL, Redis, Mailpit o Selenium, que Sail instala por defecto en modo no interactivo y que este trabajo no necesita.

### 1.3 Por qué Breeze y no Fortify/Jetstream

La guía ofrece "Breeze/Fortify". Se eligió **Breeze con el stack Blade** porque:

- Fortify es *headless*: da las rutas, no las vistas. Habría que escribir todo el frontend de autenticación a mano.
- Jetstream trae equipos, roles, API tokens y verificación en dos pasos: funcionalidad que el enunciado no pide y que contradice la restricción de no implementar nada no descrito.
- Breeze es el mínimo que ya trae resuelto lo que **sí** se pide: hash de contraseñas, token CSRF en los formularios, validación y el rate limiting básico del login.

### 1.4 Por qué SQLite, y cómo se cambia de motor

SQLite no requiere servidor, lo que elimina una pieza móvil en desarrollo. La portabilidad se consiguió con tres reglas:

1. **Nada de SQL crudo.** Todo el acceso a datos pasa por Eloquent, que genera SQL parametrizado y portable.
2. **Nada de SQL específico de un motor** en migraciones, seeders ni consultas.
3. **Ninguna lógica de conexión en el código.** La conexión sale entera del `.env`.

El bloque de base de datos del `.env` incluye los ejemplos para MySQL y PostgreSQL comentados y listos para descomentar. Adicionalmente, `DB_DATABASE` se deja **sin definir** a propósito: Laravel usa por defecto `database/database.sqlite`, que resuelve a la misma ruta relativa tanto en local como dentro del contenedor (donde el proyecto se monta en `/var/www/html`), así que la misma configuración vale en ambos entornos sin tocar nada.

> Nota sobre el tipo `enum`: `$table->enum('role', [...])` genera `ENUM` nativo en MySQL/PostgreSQL y un `VARCHAR` con restricción `CHECK` en SQLite. El comportamiento desde la aplicación es idéntico en los tres motores.

---

## 2. Modelo de datos implementado

Se implementó exactamente el modelo de la sección 9 de la guía.

### 2.1 Tabla `usuarios`

`database/migrations/0001_01_01_000000_create_usuarios_table.php`

| Campo | Tipo | Nota |
|---|---|---|
| `id` | bigint, PK | Identificador interno. **Nunca se usa la cédula como id de recurso en rutas.** |
| `nombre_completo` | varchar(150) | |
| `numero_identificacion` | varchar(20), unique | **No se cifra**, por la razón que da la guía: no es el dato de mayor riesgo y cifrarlo impediría validar unicidad. |
| `email` | varchar(150), unique | Credencial de acceso, debe ser buscable. |
| `password` | varchar(255) | Hash bcrypt. |
| `telefono` | varchar(20) | |
| `direccion` | varchar(255) | |
| `ocupacion` | varchar(100) | |
| `ingresos_mensuales` | decimal(12,2) | `decimal`, nunca `float`, para evitar errores de redondeo en dinero. |
| `entidad_bancaria` | varchar(100) | |
| `role` | enum('usuario','administrador') | Por defecto `usuario`. |
| `created_at` / `updated_at` | timestamps | Auditoría. |
| `deleted_at` | timestamp nullable | Soft delete. |

**Dos columnas añadidas respecto a la tabla de la guía**, documentadas aquí por transparencia:

- `email_verified_at` y `remember_token`.

Son **plomería estándar del andamiaje de autenticación de Laravel**, no datos de negocio: no aparecen en la tabla de clasificación de la sección 2 porque no son información personal ni financiera, sino marcas de estado del framework (`remember_token` sostiene la casilla "Recordarme" del login; `email_verified_at` es el estado de verificación del correo). Se mantuvieron porque eliminarlas obligaba a amputar funciones que Breeze ya trae funcionando —y que la guía cita como base— sin ninguna ganancia de seguridad. Ninguna de las dos se muestra ni se acepta desde un formulario.

### 2.2 Tabla `tarjetas`

`database/migrations/2026_09_27_000001_create_tarjetas_table.php`

| Campo | Tipo | Nota |
|---|---|---|
| `id` | bigint, PK | |
| `usuario_id` | bigint, FK unique | `unique` porque es una relación 1 a 1. |
| `numero_tarjeta` | text, cast `encrypted` | **Único campo cifrado a nivel de aplicación.** Es `text` porque el criptograma es más largo que el número y su longitud varía. |
| `ultimos_4_digitos` | varchar(4) | En claro, para mostrar `**** **** **** 1234` sin descifrar en cada render. |
| `nombre_titular` | varchar(150) | Puede diferir del dueño de la cuenta. |
| `fecha_vencimiento` | varchar(5) | Formato `MM/YY`, sin cifrar. |
| `created_at` / `updated_at` / `deleted_at` | | Soft delete, igual que en `usuarios`. |

**Por qué está separada:** la guía lo justifica en su sección 9 y el código lo aprovecha de forma concreta. Al vivir en otra tabla, el listado del panel de administración puede consultar `usuarios` sin que exista la posibilidad de arrastrar un dato de tarjeta por un `SELECT *` descuidado. La lógica de cifrado queda concentrada en un único modelo (`app/Models/Tarjeta.php`).

### 2.3 Lo que NO se implementó, a propósito

Siguiendo la sección 10 de la guía y la restricción de no sobre-ingeniería:

- **No hay tabla `roles`.** Con dos roles, el enum basta.
- **No se cifra ningún campo salvo `numero_tarjeta`.** El resto se protege con autorización. Cifrar datos que necesitan ser buscados u ordenados (correo, cédula) obligaría a renunciar a índices y validaciones de unicidad a cambio de poco.
- **No hay soporte multi-tarjeta.** El formulario pide una, y la relación es 1 a 1 con `unique` en la clave foránea.
- **No se guarda el CVV** en ninguna forma (ver 3.16).
- **No se normaliza `nombre_titular`** contra `usuarios.nombre_completo`.

---

## 3. Decisiones de seguridad

Cada punto indica el activo de la sección 3 de la guía que protege, la amenaza de la sección 4 contra la que responde, dónde está en el código y cómo se comprueba.

### 3.1 Hash de contraseñas con bcrypt

- **Activo:** credenciales de acceso (hash de contraseñas).
- **Amenaza:** suplantación de identidad. Si la base de datos se filtra y las contraseñas están en texto plano o con un hash débil (MD5/SHA1), el atacante las usa directamente.
- **Control:** `Hash::make()` de Laravel, que usa **bcrypt** con coste 12. bcrypt es una función de derivación lenta y con sal aleatoria por hash: es **irreversible** por diseño, a diferencia de un cifrado, que se deshace con la clave.
- **Dónde:** `app/Http/Controllers/Auth/RegisteredUserController.php` (registro), `app/Http/Controllers/Auth/PasswordController.php` (cambio), y el cast `'password' => 'hashed'` en `app/Models/User.php` como red de seguridad para cualquier otra ruta de escritura.
- **Comprobación:** `tests/Feature/Seguridad/HashPasswordTest.php`. También a mano: `SELECT password FROM usuarios` debe empezar por `$2y$`.

### 3.2 Cifrado del número de tarjeta

- **Activo:** el número de tarjeta, el único dato clasificado como **Restringido**.
- **Amenaza:** exposición de datos sensibles. Es el riesgo "Alto/Alto" de la sección 6: una filtración es daño directo e irreversible al usuario.
- **Control:** cast `encrypted` de Eloquent (`app/Models/Tarjeta.php`), que cifra con AES-256-CBC usando la `APP_KEY`. El campo `ultimos_4_digitos` se guarda en claro a propósito para poder pintar la tarjeta sin descifrar.
- **Dónde:** `app/Models/Tarjeta.php`, `app/Http/Controllers/TarjetaController.php`.
- **Comprobación:** `tests/Feature/Seguridad/CifradoTarjetaTest.php`, incluida una prueba que demuestra que **sin la `APP_KEY` el criptograma es inservible**: se intenta descifrar con otra clave y falla. Esto es lo que protege el dato aunque un atacante consiga un volcado de la base de datos.

### 3.3 Autorización por rol verificada en el backend

- **Activo:** lógica de autorización; endpoints de administración.
- **Amenaza:** escalada de privilegios vertical.
- **Control:** middleware `EnsureUserIsAdmin`, registrado con el alias `admin` en `bootstrap/app.php` y aplicado a **todas** las rutas de `/admin`. Devuelve 403.
- **Dónde:** `app/Http/Middleware/EnsureUserIsAdmin.php`, `routes/web.php`, `bootstrap/app.php`.
- **Por qué así:** la guía es explícita en que *"ocultar un botón no es seguridad"*. La navegación solo pinta el enlace de administración si el usuario es administrador, pero eso es cosmético: **la barrera es el middleware**. Un usuario con rol `usuario` que escriba la URL a mano recibe 403 igual.
- **Comprobación:** `tests/Feature/Seguridad/AccesoPanelAdminTest.php`.

### 3.4 Protección contra IDOR (escalada horizontal)

- **Activo:** lógica de autorización; endpoints de perfil.
- **Amenaza:** escalada de privilegios horizontal: acceder al perfil o a la tarjeta de otra persona.
- **Control, en dos capas:**

  1. **Estructural — no existe el identificador.** Ninguna ruta de perfil lleva `{id}`: el recurso es siempre `$request->user()`. No hay nada que manipular. Las rutas son `/perfil` y `/perfil/tarjeta`.
  2. **Explícita — `UserPolicy`.** Cada acción pasa además por `Gate::authorize()`, que compara el dueño del recurso con quien lo pide (`$autenticado->id === $recurso->id`).

- **Dónde:** `routes/web.php`, `app/Policies/UserPolicy.php`, `app/Http/Controllers/PerfilController.php`, `app/Http/Controllers/TarjetaController.php`.
- **Además:** `usuario_id` **no** está en `Tarjeta::$fillable`. La tarjeta se crea y se lee siempre a través de la relación `$usuario->tarjeta()`, que fija la clave foránea en código. Enviar `usuario_id` en el POST no tiene ningún efecto.
- **Comprobación:** `tests/Feature/Seguridad/IdorTest.php`. La sección 6.2 tiene el procedimiento manual.

### 3.5 Escalada de privilegios por asignación masiva

- **Activo:** lógica de autorización.
- **Amenaza:** escalada de privilegios vertical por un camino menos obvio que el anterior: `role` **no está en `User::$fillable`**.

  Si lo estuviera, bastaría con añadir `role=administrador` al POST del registro o al PATCH del perfil para convertirse en administrador. Es un fallo clásico de Laravel y merece un control propio.
- **Control:** lista blanca de campos asignables en el modelo + los controladores escriben **solo** `$request->validated()`, nunca `$request->all()`.
- **Dónde:** `app/Models/User.php` (con un comentario que advierte de no añadirlo nunca), `app/Http/Requests/Auth/RegisterRequest.php`, `app/Http/Requests/PerfilUpdateRequest.php`.
- **Consecuencia:** el rol solo se puede asignar con código explícito y de confianza (seeders), lo cual es visible y auditable.
- **Comprobación:** `tests/Feature/Seguridad/EscaladaPrivilegiosTest.php`.

### 3.6 Protección CSRF

- **Activo:** endpoints de registro, perfil y administración.
- **Amenaza:** CSRF — que un sitio ajeno haga que el navegador de la víctima envíe una petición aprovechando su sesión abierta.
- **Control:** middleware `PreventRequestForgery` de Laravel (activo en el grupo `web`) + `@csrf` en **todos** los formularios. Los formularios que modifican usan además `@method`.
- **Dónde:** `resources/views/**` (todos los `<form method="POST">`), activo por defecto.
- **Comprobación:** `tests/Feature/Seguridad/CsrfTest.php`. Un POST sin token recibe **419** y no modifica nada.
- **Nota de implementación:** Laravel desactiva la verificación CSRF cuando detecta que corre en tests. Esa comodidad haría que la prueba no probara nada, así que en las pruebas correspondientes se cambia el entorno de la aplicación a uno distinto de `testing` y se lanza la petición sin token, comprobando que efectivamente se rechaza.

### 3.7 Cookies de sesión con HttpOnly, Secure y SameSite=Strict

- **Activo:** sesión activa.
- **Amenaza:** robo o fijación de sesión; CSRF.
- **Control:** las tres directivas exigidas por la guía.
  - `HttpOnly`: JavaScript no puede leer la cookie, así que un XSS no puede robarla con `document.cookie`.
  - `Secure`: el navegador no la envía por HTTP plano.
  - `SameSite=Strict`: no viaja en peticiones iniciadas desde otro sitio (en vez del `Lax` que trae Laravel por defecto).
- **Dónde:** `config/session.php` y el bloque de sesión del `.env`.
- **Detalle importante:** `'secure' => env('SESSION_SECURE_COOKIE', true)` — **el valor por defecto es `true`**, de modo que si alguien despliega sin definir la variable, la cookie sale segura igualmente. Ver la limitación documentada en 8.1 sobre HTTP local.
- **Comprobación:** `tests/Feature/Seguridad/CookiesSesionTest.php`, que inspecciona los atributos reales de la cabecera `Set-Cookie`, no solo la configuración.

### 3.8 Rate limiting del login, en dos capas

- **Activo:** endpoint de login.
- **Amenaza:** la guía nombra dos ataques distintos, y cada uno necesita su propio límite:
  - **Fuerza bruta** contra una cuenta concreta → lo corta el límite que ya trae Breeze, con clave `email|ip` y umbral de 5 intentos.
  - **Credential stuffing** (probar un diccionario de contraseñas contra muchísimos correos desde una misma máquina) → **el límite de Breeze no lo ve**, porque cada correo abre su propio contador. Por eso se añadió un segundo límite con clave **solo por IP**, de 20 por minuto, en `app/Providers/AppServiceProvider.php`.
- **Dónde:** `app/Http/Requests/Auth/LoginRequest.php` (Breeze), `app/Providers/AppServiceProvider.php` y `routes/auth.php` (`->middleware('throttle:login')`).
- **Comprobación:** `tests/Feature/Seguridad/RateLimitLoginTest.php`. El umbral por IP es holgado a propósito: no estorba a una persona que se equivoca al escribir, pero frena un barrido automatizado.

### 3.9 Validación de entrada y validación de formato de tarjeta (Luhn)

- **Activo:** modelo de datos; integridad de los datos de tarjeta.
- **Amenaza:** inyección; almacenar datos basura o inventados.
- **Control:** reglas de validación estrictas en **FormRequests** dedicados (no en el controlador), y una regla propia `App\Rules\Luhn` que valida el dígito de control antes de cifrar y guardar. Se rechaza también un número con todos los dígitos iguales (`0000000000000000` pasa Luhn matemáticamente pero ningún emisor lo emite).
- **Dónde:** `app/Rules/Luhn.php`, `app/Http/Requests/`.
- **Sobre la inyección SQL:** el control real no es escapar la entrada sino no construir SQL por concatenación. Todo el acceso a datos es Eloquent/query builder, que **parametriza** las consultas. No existe ni una consulta cruda concatenada en el proyecto.
- **Comprobación:** `tests/Unit/LuhnTest.php` y las pruebas de registro en `tests/Feature/Auth/RegistrationTest.php`.

### 3.10 Escape de salida (XSS)

- **Activo:** código fuente de la aplicación; sesiones de los administradores.
- **Amenaza:** XSS almacenado. El escenario real de este sistema es concreto: alguien se registra con un `nombre_completo` como `<script>…</script>` y ese nombre se pinta después en el **listado del administrador**, que es justo la pantalla donde al atacante le gustaría ejecutar código con más privilegios que los suyos (la guía lo señala en la sección 4).
- **Control:** escape automático de Blade. Todas las plantillas usan `{{ }}`, que escapa HTML. **No se usa `{!! !!}` con datos de usuario en ninguna vista.**
- **Dónde:** todas las plantillas, en particular `resources/views/admin/usuarios/index.blade.php`.
- **Nota:** el escape es una decisión de la **capa de salida**. El dato se guarda tal cual se escribió y se escapa al mostrarlo; no se "mutila" la entrada.
- **Comprobación:** `tests/Feature/Seguridad/XssTest.php`.

### 3.11 Manejo de errores genérico

- **Activo:** modelo de datos y su lógica; rutas internas.
- **Amenaza:** fuga de información por manejo de errores. Un stack trace revela la versión del framework, rutas de archivos, estructura de la base de datos y a veces fragmentos de consultas.
- **Control:** `APP_DEBUG=false` y páginas de error propias para 401, 403, 404, 419, 429, 500 y 503 (`resources/views/errors/`). Los mensajes son genéricos y **no confirman** si un recurso existe ni por qué se denegó.
- **Detalle:** el layout de errores no usa `@vite` ni depende de la sesión, del usuario autenticado ni de la base de datos, y lleva el CSS en línea. Tiene que poder renderizarse aunque el fallo que provocó el error sea justamente uno de esos subsistemas.
- **Comprobación:** `tests/Feature/Seguridad/ErroresGenericosTest.php`, que provoca un error interno real y verifica que ni el mensaje de la excepción, ni su clase, ni rutas de archivos aparecen en la respuesta.

### 3.12 Datos mínimos en el panel de administración

- **Activo:** datos personales y financieros de los usuarios.
- **Amenaza:** exposición de datos sensibles; violación del principio de mínimo privilegio.
- **Control, doble:** la vista lista **solo** nombre, correo y cédula —nunca ingresos, entidad bancaria ni tarjeta—, y además el controlador hace un `select()` **explícito** de esas tres columnas más el `id`.
- **Por qué el `select()` importa:** esas columnas no se consultan siquiera, así que no pueden filtrarse por accidente en la vista, en un `toArray()` ni en el HTML, ni aunque alguien añadiera mañana un `{{ $usuario->ingresos_mensuales }}` a la plantilla.
- **Dónde:** `app/Http/Controllers/Admin/UsuarioController.php`, `resources/views/admin/usuarios/index.blade.php`.
- **Comprobación:** `tests/Feature/Seguridad/AccesoPanelAdminTest.php`.

### 3.13 Eliminación con soft delete

- **Activo:** datos de los usuarios; trazabilidad.
- **Amenaza:** pérdida de información y de rastro de auditoría por borrado físico.
- **Control:** `SoftDeletes` en ambos modelos. "Eliminar" desde el panel marca `deleted_at`; la fila se conserva. El efecto práctico es inmediato: la cuenta desaparece del listado **y no puede iniciar sesión**, porque el `SoftDeletingScope` la excluye de las consultas del proveedor de autenticación.
- **Dónde:** `app/Models/User.php`, `app/Models/Tarjeta.php`, `app/Http/Controllers/Admin/UsuarioController.php`.
- **Extra:** un administrador **no puede eliminarse a sí mismo** desde el panel, para no quedarse fuera del sistema por error.
- **Comprobación:** `tests/Feature/Seguridad/BorradoLogicoTest.php`.

### 3.14 Consultas parametrizadas

- **Activo:** base de datos.
- **Amenaza:** inyección SQL en los formularios de registro y actualización de perfil.
- **Control:** Eloquent y el query builder en el 100% del acceso a datos. Las consultas se construyen con métodos (`where('email', $valor)`), nunca concatenando cadenas. Laravel las envía como sentencias preparadas con parámetros separados, así que un valor como `' OR 1=1 --` se trata como texto, no como SQL.
- **Dónde:** todo `app/`.
- **Comprobación:** inspección del código — no hay ninguna llamada a `DB::raw()` con entrada de usuario ni concatenación en `whereRaw`.

### 3.15 Protección del `.env` y de la `APP_KEY`

- **Activo:** `APP_KEY` de Laravel — la clave que cifra y descifra el número de tarjeta. Y el código fuente.
- **Amenaza:** exposición de datos sensibles; si la `APP_KEY` se filtra junto con la base de datos, el cifrado del punto 3.2 deja de proteger nada.
- **Control:** `.env` está en `.gitignore` (verificado) y **nunca** se versiona. El repositorio incluye `.env.example` **sin** `APP_KEY`: cada entorno la genera con `php artisan key:generate`. El `APP_KEY` real no se copia jamás a `.env.example`.

  > Este punto no es teórico: durante el desarrollo, un comando automático intentó copiar el `.env` completo —con su `APP_KEY`— sobre `.env.example`, que sí se versiona. El control lo detuvo. Es exactamente el accidente que la guía quiere evitar y quedó como evidencia de que el control funciona.

- **Dónde:** `.gitignore`, `.env.example`.
- **Comprobación:** `git check-ignore -v .env` debe responder que está ignorado.

### 3.16 El CVV no se solicita ni se almacena

- **Activo:** datos de tarjeta del usuario.
- **Amenaza:** exposición de datos sensibles. El CVV es el dato que más directamente habilita un fraude y **no se puede almacenar** sin incumplir PCI-DSS.
- **Control:** no existe el campo en el formulario, no existe la regla de validación, **no existe la columna en la base de datos**. Como los controladores escriben solo `$request->validated()`, enviarlo en el POST no tiene ningún efecto.
- **Dónde:** `app/Http/Requests/`, migración de `tarjetas`.
- **Comprobación:** hay una prueba que envía `cvv`, `cvc` y `codigo_seguridad` en el registro y verifica que esas columnas **no existen** en la tabla y que ninguno de esos valores quedó almacenado en ninguna parte de la fila creada.

---

## 4. Matriz de trazabilidad

| Activo (guía §3) | Amenaza (guía §4) | Control implementado | Prueba |
|---|---|---|---|
| Credenciales / hash | Suplantación, fuerza bruta | bcrypt coste 12 | `HashPasswordTest` |
| Endpoint de login | Fuerza bruta | Límite 5 por `email\|ip` (Breeze) | `RateLimitLoginTest` |
| Endpoint de login | Credential stuffing | Límite 20/min por IP | `RateLimitLoginTest` |
| Lógica de autorización | Escalada vertical | Middleware `admin` → 403 | `AccesoPanelAdminTest` |
| Lógica de autorización | Escalada vertical | `role` fuera de `$fillable` | `EscaladaPrivilegiosTest` |
| Endpoints de perfil | Escalada horizontal (IDOR) | Rutas sin `{id}` + `UserPolicy` | `IdorTest` |
| Modelo de datos | Inyección SQL | Eloquent parametrizado | inspección |
| Campos de texto | XSS almacenado | Escape `{{ }}` de Blade | `XssTest` |
| Formularios | CSRF | Token + `PreventRequestForgery` | `CsrfTest` |
| Sesión activa | Robo / fijación de sesión | HttpOnly + Secure + SameSite=Strict | `CookiesSesionTest` |
| Número de tarjeta | Exposición de datos | Cast `encrypted` (AES-256-CBC) | `CifradoTarjetaTest` |
| `APP_KEY` | Exposición de datos | `.env` ignorado; `.env.example` sin clave | `git check-ignore` |
| Estructura de BD | Fuga por errores | `APP_DEBUG=false` + vistas propias | `ErroresGenericosTest` |
| Datos financieros | Exposición de datos | Datos mínimos + `select()` explícito | `AccesoPanelAdminTest` |
| Datos de tarjeta | Exposición de datos | CVV no pedido, no validado, no almacenado | `RegistrationTest` |
| Registro de cuentas | Datos inválidos | Regla `Luhn` + FormRequests | `LuhnTest` |
| Datos de los usuarios | Pérdida de trazabilidad | Soft delete en ambos modelos | `BorradoLogicoTest` |

---

## 5. Puesta en marcha

### 5.1 Con Sail (Docker)

```bash
# 1. Dependencias de PHP y de Node
docker run --rm -v "$(pwd):/app" -w /app composer:latest install
npm install && npm run build

# 2. Clave de aplicación y base de datos
cp .env.example .env
docker compose run --rm laravel.test php artisan key:generate
docker compose up -d
docker compose exec laravel.test php artisan migrate --seed
```

La aplicación queda en <http://localhost>.

> **Nota sobre Windows:** el script `vendor/bin/sail` es de macOS/Linux/WSL2 y en Windows nativo falla con `Unsupported operating system`. Las dos alternativas son usar `docker compose` directamente (los comandos de arriba) o instalar WSL2 y ejecutar `./vendor/bin/sail`. En WSL2 los comandos son los habituales: `./vendor/bin/sail up -d`, `./vendor/bin/sail artisan migrate --seed`, etc.

> **Estado de verificación del entorno Sail (leer antes de la entrega):** el `compose.yaml` está escrito y **validado** (`docker compose config -q` lo resuelve sin errores y define un único servicio, `laravel.test`), y se comprobó que la imagen de Sail para PHP 8.5 incluye `sqlite3` y `php8.5-sqlite3`, así que la base de datos funciona dentro del contenedor. **La construcción de la imagen no se pudo completar en la máquina de desarrollo** por un problema del propio Docker Desktop (su almacén `containerd` está corrupto: `failed to calculate image disk usage: lstat …/snapshots/1564/fs/var/lib/dpkg/tmp.ci: no such file or directory`), no por el proyecto. Ver la limitación 8.6.

### 5.2 Sin Docker (PHP local)

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### 5.3 Cuentas de demostración

Las crea `UsuarioSeeder`. La contraseña de todas es `Password123!`.

| Nombre | Correo | Rol | Para qué sirve |
|---|---|---|---|
| Ana Restrepo | `admin@plataforma.test` | administrador | Entrar al panel de administración |
| Juan Pérez | `usuario1@plataforma.test` | usuario | Persona normal para probar IDOR y el 403 |
| María Gómez | `usuario2@plataforma.test` | usuario | Segunda cuenta, para intentar acceder a datos ajenos |
| Carlos Analítica | `analitica1@plataforma.test` | usuario | Equipo de Analítica |
| Laura Analítica | `analitica2@plataforma.test` | usuario | Equipo de Analítica |

Las tarjetas sembradas usan **números de prueba publicados por las pasarelas de pago** (cumplen Luhn, no corresponden a tarjetas reales).

Para volver al estado limpio en cualquier momento: `php artisan migrate:fresh --seed`.

---

## 6. Pruebas manuales de los controles

Procedimiento para demostrar cada control frente al docente. En todos los casos se asume la aplicación levantada y las cuentas sembradas.

### 6.1 Acceso no autorizado al panel de administración (escalada vertical)

1. Entrar con `usuario1@plataforma.test` / `Password123!`.
2. Comprobar que **no aparece** el enlace "Administración" en la navegación.
3. Escribir la URL a mano: `http://localhost/admin/usuarios`.

**Qué debe pasar:** página **403 — Acceso denegado**. Es el punto clave de la demostración: ocultar el enlace era cosmético; lo que impide el acceso es el middleware en el backend. Además, la página de error no revela nada del sistema.

4. Estando en el panel como administrador (`admin@plataforma.test`), inspeccionar el HTML del listado y buscar `ingresos`, `Bancolombia` o el número de tarjeta.

**Qué debe pasar:** no aparecen. El listado solo muestra nombre, correo y cédula. No es que se oculten con CSS: esas columnas no se consultan.

### 6.2 IDOR — intentar acceder al perfil de otra persona (escalada horizontal)

Estando autenticado como `usuario1` (id 2), intentar alcanzar el perfil de `usuario2` (id 3):

| Intento | Qué debe pasar |
|---|---|
| `GET /perfil/3` | **404** — esa ruta no existe; el perfil nunca se direcciona por id |
| `PATCH /perfil/3` con token | **404** |
| `GET /perfil?id=3` | **200**, pero muestra **tu** perfil, no el de la otra persona: el `id` se ignora |
| `GET /perfil/tarjeta` | Muestra **tu** tarjeta enmascarada, nunca la ajena |

**El intento que hay que enseñar** es la manipulación del formulario, porque es el IDOR clásico:

1. Estando como `usuario1`, abrir el perfil y usar las herramientas de desarrollo del navegador para añadir al `<form>` un campo oculto `usuario_id` con el id de otra cuenta (por ejemplo `3`).
2. Cambiar el nombre y enviar.

**Qué debe pasar:** se modifica **tu** cuenta y la de la otra persona queda intacta. El backend resuelve el recurso con `$request->user()` e ignora cualquier identificador que venga en el cuerpo.

Se puede comprobar en la base de datos:

```bash
php artisan tinker --execute="App\Models\User::find(3)->nombre_completo;"
```

### 6.3 XSS almacenado

1. Registrarse con un nombre malicioso:

   ```
   Nombre completo: <script>alert("xss")</script>
   ```

   (el resto de campos, con datos válidos; tarjeta `4111 1111 1111 1111`, vencimiento `08/29`).

2. Entrar como `admin@plataforma.test` y abrir el panel de administración.

**Qué debe pasar:** el nombre se ve **como texto** en la tabla, con las etiquetas a la vista (`<script>alert("xss")</script>`) y **no salta ningún diálogo de alerta**. Ver el código fuente de la página (Ctrl+U) confirma que la etiqueta viaja escapada como `&lt;script&gt;`.

Si el control fallara, el script se ejecutaría **en la sesión del administrador**, que es justo lo que busca un atacante.

### 6.4 CSRF

1. Abrir el perfil, y con las herramientas del navegador borrar el campo oculto `_token` del formulario.
2. Enviar.

**Qué debe pasar:** página **419 — Sesión expirada**, y **ningún dato modificado**. Sin token válido, Laravel rechaza cualquier POST/PATCH/PUT/DELETE.

Se puede demostrar de forma más cruda desde una terminal:

```bash
curl -i -X POST http://localhost/login -d "email=usuario1@plataforma.test&password=Password123!"
```

**Qué debe pasar:** `HTTP 419`, aunque las credenciales sean correctas.

### 6.5 Rate limiting del login

**Fuerza bruta contra una cuenta:**

1. Intentar entrar como `usuario1@plataforma.test` con una contraseña incorrecta **seis veces**.
2. En el sexto intento, usar **la contraseña correcta**.

**Qué debe pasar:** el sexto intento muestra *"Demasiados intentos de acceso. Vuelve a intentarlo en N segundos."* y **no deja entrar**, aunque la contraseña sea correcta. El bloqueo dura un minuto.

**Credential stuffing desde una IP:**

```bash
for i in $(seq 1 25); do
  curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost/login \
    -d "_token=TOKEN&email=victima$i@test.com&password=probando$i"
done
```

**Qué debe pasar:** pasadas 20 peticiones en el mismo minuto, el servidor responde **429**. Nótese que un límite solo por `email|ip` (el de Breeze) no detendría esto, porque cada correo abre su propio contador; por eso existe el segundo límite por IP.

### 6.6 Atributos de la cookie de sesión

```bash
curl -s -D - -o /dev/null http://localhost/login | grep -i "set-cookie"
```

**Qué debe pasar:** la cookie de sesión incluye `secure`, `httponly` y `samesite=strict`.

Para comprobar el efecto de `HttpOnly` en el navegador: abrir la consola y escribir `document.cookie`. La cookie de sesión **no aparece** (JavaScript no puede leerla).

### 6.7 Cifrado del número de tarjeta

```bash
php artisan tinker --execute="DB::table('tarjetas')->first()->numero_tarjeta;"
```

**Qué debe pasar:** una cadena cifrada tipo `eyJpdiI6...`, **nunca** `4111111111111111`.

```bash
php artisan tinker --execute="App\Models\Tarjeta::first()->numero_tarjeta;"
```

**Qué debe pasar:** ahora sí, el número en claro: es la aplicación descifrando con la `APP_KEY`. La diferencia entre las dos consultas es exactamente la demostración de que el cifrado es real.

Adicional: borrar temporalmente `APP_KEY` del `.env` y volver a leer con Eloquent → la aplicación lanza un error de descifrado. Sin la clave, el dato es inservible.

### 6.8 Manejo de errores genérico

1. Visitar `http://localhost/una-ruta-que-no-existe`.

**Qué debe pasar:** página **404** propia, con un mensaje genérico. **No** aparece la versión de Laravel, ni rutas de archivos, ni un stack trace.

2. Poner `APP_DEBUG=true` en el `.env`, provocar un error y comparar: ahora sí aparece el stack trace completo.

**Qué debe pasar:** la comparación demuestra por qué `APP_DEBUG=false` es un control y no una preferencia. Volver a dejarlo en `false` después de la demostración.

### 6.9 Escalada de privilegios por asignación masiva

```bash
php artisan tinker --execute="
\$u = App\Models\User::create([
  'nombre_completo' => 'Intruso', 'numero_identificacion' => '999',
  'email' => 'intruso@test.com', 'password' => 'Password123!',
  'telefono' => '300', 'direccion' => 'x', 'ocupacion' => 'x',
  'ingresos_mensuales' => 1, 'entidad_bancaria' => 'x',
  'role' => 'administrador',
]);
echo \$u->fresh()->role;
"
```

**Qué debe pasar:** imprime `usuario`. El `role` enviado se descarta silenciosamente porque no está en la lista blanca. (Esa cuenta de prueba se puede borrar después con `migrate:fresh --seed`.)

### 6.10 Eliminación con soft delete

1. Entrar como administrador y pulsar "Eliminar" sobre una cuenta.
2. Comprobar que desaparece del listado.
3. Verificar en la base de datos que **la fila sigue ahí**:

```bash
php artisan tinker --execute="
\$f = DB::table('usuarios')->where('id', 3)->first();
echo 'existe: '.(\$f ? 'si' : 'no').' | deleted_at: '.\$f->deleted_at;
echo ' | visible para el modelo: '.(App\Models\User::find(3) ? 'si' : 'no');
"
```

**Qué debe pasar:** `existe: si | deleted_at: 2026-… | visible para el modelo: no`. La fila se conserva para auditoría pero el sistema ya no la ve: la cuenta tampoco puede iniciar sesión.

---

## 7. Pruebas automatizadas

```bash
php artisan test
```

**Resultado actual: 111 pruebas, 287 aserciones, todas en verde.**

Las pruebas corren sobre **SQLite en memoria** (configurado en `phpunit.xml`), así que no tocan la base de datos de desarrollo.

| Archivo | Qué cubre |
|---|---|
| `tests/Unit/LuhnTest.php` | Algoritmo de Luhn: válidos, inválidos, con separadores, todos los dígitos iguales |
| `tests/Feature/Auth/RegistrationTest.php` | Registro completo, campos obligatorios, Luhn, normalización, unicidad, CVV no almacenado |
| `tests/Feature/ProfileTest.php` | Perfil, tarjeta enmascarada, actualización, baja voluntaria |
| `tests/Feature/Seguridad/AccesoPanelAdminTest.php` | 403 para no administradores, 200 para administradores, datos mínimos |
| `tests/Feature/Seguridad/IdorTest.php` | Policies, rutas sin id, inyección de identificadores ajenos |
| `tests/Feature/Seguridad/CifradoTarjetaTest.php` | Cifrado en reposo, descifrado, IV aleatorio, inservible sin la clave |
| `tests/Feature/Seguridad/BorradoLogicoTest.php` | Soft delete, login de cuenta eliminada, tarjeta conservada |
| `tests/Feature/Seguridad/RateLimitLoginTest.php` | Bloqueo por cuenta y por IP, reinicio del contador |
| `tests/Feature/Seguridad/EscaladaPrivilegiosTest.php` | `role` no asignable en masa por ninguna vía |
| `tests/Feature/Seguridad/XssTest.php` | Escape en el panel, en el perfil y en la navegación |
| `tests/Feature/Seguridad/HashPasswordTest.php` | bcrypt, sal aleatoria, no exposición al serializar |
| `tests/Feature/Seguridad/CsrfTest.php` | 419 sin token, token presente en los formularios |
| `tests/Feature/Seguridad/CookiesSesionTest.php` | Atributos reales de `Set-Cookie` |
| `tests/Feature/Seguridad/ErroresGenericosTest.php` | Sin stack traces ni detalles internos |

Las pruebas de seguridad están escritas de forma que **fallarían si el control desapareciera**. Por ejemplo, la de XSS comprueba que la etiqueta `<script>` no viaja sin escapar en el HTML crudo; la de cifrado comprueba que el valor almacenado no contiene el número; la de asignación masiva comprueba que `role` no está en `$fillable`. No son pruebas de "la página responde 200".

---

## 8. Limitaciones conocidas y decisiones discutibles

Se documentan a propósito: un análisis de seguridad que no reconoce sus límites no es un análisis.

### 8.1 `Secure` en las cookies y desarrollo sobre HTTP

La guía exige cookies `Secure`, lo cual significa que el navegador solo las devuelve por HTTPS. En desarrollo la aplicación se sirve por **HTTP plano** en `http://localhost`.

Chrome, Edge y Firefox tratan `http://localhost` como *origen seguro* y **aceptan** cookies `Secure` sobre él, así que en la práctica el login funciona sin cambiar nada. **Pero es una excepción del navegador, no una propiedad del sistema:** si se accede por un host que no sea `localhost` sobre HTTP (por ejemplo `http://192.168.1.50`), el navegador descartará la cookie y el login fallará sin ningún mensaje de error evidente.

- **En producción esto no es un problema**, porque la aplicación debe servirse por HTTPS y entonces `Secure` es estrictamente correcto.
- Si hace falta probar desde otra máquina por HTTP, la variable `SESSION_SECURE_COOKIE=false` **solo para esa prueba**, entendiendo que se está desactivando un control. Está documentado en el propio `.env`.

### 8.2 No hay HTTPS en el entorno de desarrollo

Relacionado con lo anterior: Sail sirve por HTTP en el puerto 80. Añadir HTTPS local exigiría un proxy inverso con certificados (mkcert o similar), que la guía descarta por ser infraestructura de otro equipo y por la restricción de no sobre-ingeniería. En el VPS la terminación TLS corresponde al equipo de infraestructura.

### 8.3 SQLite no es el motor de producción

La guía lo define como motor **temporal** de desarrollo. SQLite no soporta concurrencia de escritura real, así que no es adecuado para producción. La portabilidad está resuelta (sección 1.4), pero **el cambio a MySQL o PostgreSQL no se ha probado todavía** en este entorno: sería el siguiente paso natural antes de desplegar.

### 8.4 La unicidad de correo y cédula incluye cuentas eliminadas

Como el borrado es lógico, una cuenta eliminada sigue ocupando su correo y su cédula: no se puede registrar una cuenta nueva con esos datos. Es un efecto secundario de usar soft delete y **es discutible**: puede ser deseable (evita que alguien reutilice el correo de una cuenta dada de baja) o un estorbo. Se deja documentado y sin resolver porque ninguna de las dos opciones es evidentemente correcta y resolverlo no estaba en el enunciado.

### 8.5 El correo se registra en el log

`MAIL_MAILER=log` hace que los correos (restablecimiento de contraseña, verificación) se escriban en `storage/logs/laravel.log` en vez de enviarse. Es lo adecuado para desarrollo —no se levanta un servidor de correo— pero significa que **los enlaces de restablecimiento quedan en disco**. En producción habría que configurar un servidor SMTP real.

### 8.6 La imagen de Sail no se pudo construir en el entorno de desarrollo

**Qué pasó.** Al construir la imagen (`docker compose up -d --build`), Docker Desktop falla al calcular el uso de disco de su almacén de capas:

```
failed to calculate image disk usage:
lstat /var/lib/desktop-containerd/daemon/io.containerd.snapshotter.v1.overlayfs/
snapshots/1564/fs/var/lib/dpkg/tmp.ci: no such file or directory
```

Es un **estado corrupto del almacén interno de Docker Desktop**, no un problema del `compose.yaml` ni del Dockerfile de Sail. Se verificó que no es del proyecto: `docker compose config -q` valida el archivo sin errores, `docker pull` funciona con normalidad, y el `Dockerfile` de Sail para PHP 8.5 instala `sqlite3` y `php8.5-sqlite3`.

**Qué implica y qué no.**

- Lo que **no** está verificado: que la imagen se construya y que la aplicación arranque *dentro del contenedor*. Es lo único pendiente.
- Lo que **sí** está verificado: toda la aplicación. Las 111 pruebas automatizadas y las 10 comprobaciones manuales por HTTP de la sección 6 se ejecutaron sobre **PHP 8.4 + SQLite**, que es el mismo motor de base de datos y la misma versión mayor de PHP que usa el contenedor. Nada de lo comprobado depende de que el proceso corra dentro de Docker.

**Cómo resolverlo** (decisión de quien administra la máquina, no del proyecto):

1. Reiniciar Docker Desktop. Suele bastar cuando el snapshotter se queda a medias.
2. Si persiste, en Docker Desktop: *Troubleshoot → Clean / Purge data*. **Atención: es destructivo.** Esta máquina tiene otros contenedores en marcha de proyectos distintos, así que hay que asegurarse de que se pueden perder antes de hacerlo.
3. Con WSL2 instalado, `./vendor/bin/sail up -d` es la vía habitual en Windows y evita el wrapper.

También conviene tener en cuenta que, durante el desarrollo, la red de esta máquina falló de forma intermitente al resolver el CDN del registro de Docker (`dial tcp: lookup production.cloudfront.docker.com: no such host`). Fue transitorio, pero si la construcción vuelve a fallar por DNS, ese es el primer sitio donde mirar.

### 8.7 Decisiones que se podrían cuestionar

- **`email_verified_at` y `remember_token`** no están en la tabla de la guía (ver 2.1). Se mantuvieron por ser plomería del framework; quien prefiera un ajuste literal al modelo puede eliminarlas y amputar las funciones asociadas.
- **Umbral del límite por IP (20/min)** es una elección de compromiso, no un número justificado empíricamente. Un entorno real lo ajustaría con datos de tráfico.
- **La política de contraseñas** usa los valores por defecto de Laravel (mínimo 8 caracteres). La guía no pide una política concreta y añadir requisitos de complejidad sin pedirlo habría sido sobre-ingeniería, pero es un control que se echa en falta frente a ataques de diccionario —mitigado en parte por el rate limiting y por la comprobación de contraseñas filtradas que Laravel ofrece si se activa.

---

## 9. Apéndice: comandos útiles

```bash
php artisan migrate:fresh --seed     # reinicia la base de datos con los datos de demostración
php artisan test                     # ejecuta toda la suite
php artisan test --filter=Seguridad  # solo las pruebas de seguridad
php artisan route:list --except-vendor
php artisan cache:clear              # reinicia también los contadores de rate limiting

php artisan tinker --execute="DB::table('tarjetas')->first()->numero_tarjeta;"        # cifrado
php artisan tinker --execute="App\Models\Tarjeta::first()->numero_tarjeta;"           # descifrado
```

Con Sail, anteponer `./vendor/bin/sail` (en WSL2) o usar `docker compose exec laravel.test php artisan …`.
