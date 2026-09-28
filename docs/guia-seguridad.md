# Guía de Seguridad — Plataforma de Perfiles

### Grupo de Desarrollo · Trabajo práctico de ciberseguridad

---
## 1. Contexto

Trabajo práctico para el curso de ciberseguridad: desarrollar y desplegar una aplicación web de registro/login con gestión de perfil de usuario y un panel administrador (listado y eliminación de cuentas). El equipo se dividió en tres frentes:

- **Infraestructura** — despliegue en el VPS asignado.
- **Base de datos** — administración del motor de base de datos.
- **Desarrollo** (este documento) — lógica de aplicación, autenticación, autorización y modelo de datos.

El objetivo del ejercicio no es solo entregar la funcionalidad pedida, sino demostrar capacidad de análisis de seguridad: identificar activos, amenazas, vulnerabilidades, riesgo/impacto, y aplicar controles proporcionales a la sensibilidad real de cada dato.

---
## 2. Clasificación de la información

|Dato|Clasificación|Justificación|
|---|---|---|
|Correo, contraseña|Confidencial|Llave de acceso a la cuenta|
|Nombre, cédula, teléfono, dirección|Confidencial|Datos personales identificables (PII)|
|Ocupación, ingresos mensuales, entidad bancaria|Confidencial|Datos financieros sensibles|
|Número de tarjeta, vencimiento, titular|**Restringida**|Mayor riesgo del sistema: filtración = daño directo e irreversible al usuario|

No hay datos "públicos" ni "internos" en este sistema — todo lo que maneja la aplicación requiere algún nivel de protección. Esta clasificación es la que define, más adelante, qué se cifra y qué se protege solo con control de acceso.

---
## 3. Activos (lo que le corresponde proteger al grupo de desarrollo)

- Credenciales de acceso (hash de contraseñas, sesión activa)
- Lógica de autorización (quién puede hacer qué, sobre qué recurso)
- Endpoints de registro, login, perfil y administración
- Modelo de datos y su lógica de cifrado
- Código fuente de la aplicación
- `APP_KEY` de Laravel (clave que cifra/descifra el número de tarjeta)

---
## 4. Amenazas identificadas

- **Suplantación de identidad**: fuerza bruta de login, robo/fijación de sesión, credential stuffing.
- **Escalación de privilegios**: usuario normal accediendo a funciones de admin (vertical) o al perfil de otro usuario (horizontal) — el riesgo más típico de este tipo de ejercicio.
- **Inyección SQL**: en formularios de registro/actualización de perfil.
- **XSS**: campos de texto (nombre, dirección) ejecutando scripts al mostrarse en el listado del admin.
- **CSRF**: actualización de perfil o tarjeta sin validar el origen real de la petición.
- **Exposición de datos sensibles**: tarjeta o contraseña en texto plano, o filtradas en logs/mensajes de error.
- **Fuga de información por manejo de errores**: stack traces exponiendo estructura de BD o rutas internas.

---
## 5. Vulnerabilidades comunes a evitar

- Contraseñas sin hash o con algoritmos débiles (MD5/SHA1).
- Autorización controlada solo en el frontend (ocultar un botón no es seguridad).
- Falta de validación de que el `id` editado pertenezca al usuario autenticado (IDOR).
- Número de tarjeta almacenado sin cifrar.
- Consultas SQL armadas por concatenación de strings.
- Sesiones sin expiración, o cookies sin `HttpOnly` / `Secure` / `SameSite`.
- Ausencia de rate limiting en el login.

---
## 6. Riesgo e impacto (priorización)

|Nivel|Ejemplos|
|---|---|
|**Alto / Alto**|Exposición del número de tarjeta, escalación a rol admin, SQL injection|
|**Medio**|XSS, CSRF|
|**Bajo pero visible**|Falta de rate limiting, mensajes de error verbosos|

Los controles se implementan en ese orden de prioridad.

---
## 7. Controles a implementar en desarrollo

- Hash de contraseñas con **bcrypt** (`Hash::make` de Laravel) — nunca texto plano ni cifrado reversible.
- Autorización verificada en **cada endpoint** del backend (rol + pertenencia del recurso), no solo en la interfaz.
- **Eloquent** con consultas parametrizadas — nunca SQL crudo concatenado.
- Cifrado a nivel de aplicación (cast `encrypted` de Laravel) **únicamente** para el número de tarjeta, por ser el único dato clasificado como restringido.
- Validación de entrada y escape de salida para prevenir XSS.
- Protección CSRF (activa por defecto en formularios de Laravel).
- Cookies de sesión con `HttpOnly`, `Secure`, `SameSite=Strict`.
- Manejo de errores genérico hacia el usuario, sin detalles internos ni stack traces.
- Vista de administrador con **datos mínimos** (nombre, correo, cédula) — nunca ingresos, entidad bancaria ni tarjeta.
- Validación de formato de tarjeta (algoritmo de Luhn) antes de guardar.
- Protección del archivo `.env` (contiene `APP_KEY`) — nunca subirlo al repositorio.

---
## 8. Stack tecnológico

- **Laravel (PHP)** como monolito: backend + frontend renderizado por el servidor, sin separar en API + SPA — decisión tomada para evitar sobre-ingeniería en un ejercicio de este alcance.
- **Laravel Breeze/Fortify** para scaffolding de registro/login con hash, CSRF y validación ya integrados.
- **Laravel Sail** (Docker) como entorno de trabajo del grupo de desarrollo — no se requiere configuración de infraestructura ni de base de datos, eso lo gestionan los otros equipos.

---
## 9. Modelo de entidades

### Tabla `usuarios`

|Campo|Tipo|Nota|
|---|---|---|
|`id`|bigint, PK|Identificador interno — nunca usar la cédula como ID de recurso en rutas|
|`nombre_completo`|varchar(150)|Confidencial, protegido por autorización|
|`numero_identificacion`|varchar(20), unique|No se cifra: no es el dato de mayor riesgo, y cifrarlo impediría validar unicidad directamente|
|`email`|varchar(150), unique|Credencial de acceso, debe ser buscable|
|`password`|varchar(255)|Hash bcrypt|
|`telefono`|varchar(20)|Confidencial|
|`direccion`|varchar(255)|Confidencial|
|`ocupacion`|varchar(100)|Confidencial|
|`ingresos_mensuales`|decimal(12,2)|`decimal`, nunca `float`, para evitar errores de redondeo en dinero|
|`entidad_bancaria`|varchar(100)|Confidencial|
|`role`|enum('usuario','administrador')|Solo dos roles — una tabla `roles` separada sería sobre-ingeniería aquí|
|`created_at` / `updated_at`|timestamps|Auditoría|
|`deleted_at`|timestamp nullable|Soft delete — al "eliminar" una cuenta desde el panel admin, no se borra físicamente|

### Tabla `tarjetas` (separada intencionalmente)

|Campo|Tipo|Nota|
|---|---|---|
|`id`|bigint, PK|—|
|`usuario_id`|bigint, FK unique|`unique` porque el formulario solo pide una tarjeta por perfil (1 a 1)|
|`numero_tarjeta`|text, cast `encrypted`|Único campo cifrado a nivel de aplicación — dato clasificado como restringido|
|`ultimos_4_digitos`|varchar(4)|Plano, para mostrar `**** **** **** 1234` sin desencriptar en cada render|
|`nombre_titular`|varchar(150)|Puede diferir del dueño de la cuenta|
|`fecha_vencimiento`|varchar(5), formato `MM/YY`|Sin cifrar — sola, sin el número completo, no es explotable|
|`created_at` / `updated_at` / `deleted_at`|—|Igual que en `usuarios`, con soft delete|

**Por qué separar `tarjetas` de `usuarios`:** aísla el único dato restringido del sistema en un modelo pequeño y auditable, reduce lo que se expone en cualquier consulta sobre `usuarios`, y concentra toda la lógica de cifrado en un solo lugar del código.

---
## 10. Decisiones tomadas a propósito para evitar sobre-ingeniería

- No se separan roles en una tabla aparte (con dos roles, un enum basta).
- No se cifra cada campo confidencial — solo el dato clasificado como restringido.
- No se normaliza `nombre_titular` contra `usuarios.nombre_completo`.
- No se diseña soporte multi-tarjeta (no fue pedido en el enunciado).
- No se solicita ni almacena el código de seguridad (CVV) de la tarjeta.