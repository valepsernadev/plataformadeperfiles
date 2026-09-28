{{--
    Layout de las páginas de error.

    A propósito NO usa @vite ni depende de la sesión, del usuario autenticado ni
    de la base de datos: tiene que poder renderizarse aunque el fallo que
    provocó el error sea justamente uno de esos subsistemas. El CSS va en línea
    por el mismo motivo.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            color: #111827;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            padding: 24px;
        }
        main { max-width: 32rem; text-align: center; }
        .codigo {
            font-size: 3rem;
            font-weight: 700;
            margin: 0;
            color: #9ca3af;
            letter-spacing: 0.05em;
        }
        h1 { font-size: 1.25rem; margin: 0.5rem 0 0; }
        .mensaje { margin: 0.75rem 0 0; color: #4b5563; line-height: 1.5; }
        .acciones { margin-top: 1.5rem; }
        .acciones a {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #1f2937;
            color: #fff;
            text-decoration: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #111827; color: #f9fafb; }
            .mensaje { color: #9ca3af; }
            .acciones a { background: #f9fafb; color: #111827; }
        }
    </style>
</head>
<body>
    <main>
        <p class="codigo">@yield('codigo')</p>
        <h1>@yield('titulo')</h1>
        <p class="mensaje">@yield('mensaje')</p>

        <div class="acciones">
            <a href="{{ url('/') }}">Volver al inicio</a>
        </div>
    </main>
</body>
</html>
