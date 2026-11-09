<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', config('app.name').' sitio'))">

    {{-- Bootstrap, FontAwesome & SweetAlert2 (CDN) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    {{-- @vite(['resources/css/app.css','resources/js/app.js']) --}}

    <style>
        :root{ --app-bg:#f7f8fa; }
        body{ background:var(--app-bg); }
        .container-narrow{ max-width:1100px; }
        .card.shadow-soft{ box-shadow:0 8px 24px rgba(0,0,0,.06); border:1px solid rgba(0,0,0,.04); }
        .navbar-brand{ font-weight:600; letter-spacing:.2px; }
    </style>

    @stack('head')
</head>
<body>
    {{-- Navbar pública --}}
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container container-narrow">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
                <i class="fa-solid fa-fingerprint"></i>
                <span>{{ config('app.name','Aplicación') }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="topNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('/') ? 'active' : '' }}" href="{{ url('/') }}">
                            <i class="fa-solid fa-house me-1"></i>Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('version') ? 'active' : '' }}" href="{{ route('version') }}">
                            <i class="fa-solid fa-microchip me-1"></i>Versión / Info
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('biometrico.users') ? 'active' : '' }}" href="{{ route('biometrico.users') }}">
                            <i class="fa-solid fa-users me-1"></i>Usuarios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('biometrico.marcaciones') ? 'active' : '' }}" href="{{ route('biometrico.marcaciones') }}">
                            <i class="fa-solid fa-clock-rotate-left me-1"></i>Marcaciones
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('biometrico.herramientas') ? 'active' : '' }}" href="{{ route('biometrico.herramientas') }}">
                            <i class="fa-solid fa-screwdriver-wrench me-1"></i>Editar Usuario / Exportar .DAT
                        </a>
                    </li>
                </ul>

                {{-- Acciones públicas (sin login) --}}
                <div class="d-flex align-items-center gap-2">
                    <button id="themeToggle" class="btn btn-light btn-sm" type="button" title="Cambiar tema">
                        <i class="fa-regular fa-moon"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    {{-- Cabecera opcional --}}
    @hasSection('page_header')
    <header class="bg-white border-bottom">
        <div class="container container-narrow py-3">
            @yield('page_header')
        </div>
    </header>
    @endif

    {{-- Contenido --}}
    <main class="container container-narrow my-4">
        @yield('content')
    </main>

    {{-- Footer público --}}
    <footer class="border-top py-4">
        <div class="container container-narrow d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="small">© {{ date('Y') }} {{ config('app.name') }} · Todos los derechos reservados</div>
            <div class="small">
                <a href="#" class="text-decoration-none me-3">Términos</a>
                <a href="#" class="text-decoration-none">Privacidad</a>
            </div>
        </div>
    </footer>

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Toggle tema claro/oscuro sin login --}}
    <script>
    (function () {
        function setTheme(t){ document.documentElement.setAttribute('data-bs-theme', t); localStorage.setItem('theme', t); }
        const saved = localStorage.getItem('theme');
        const prefer = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        setTheme(saved || prefer);
        window.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('themeToggle');
            if(!btn) return;
            btn.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                setTheme(current);
                btn.innerHTML = current === 'dark' ? '<i class="fa-regular fa-sun"></i>' : '<i class="fa-regular fa-moon"></i>';
            });
        });
    })();
    </script>

    @stack('scripts')
</body>
</html>
