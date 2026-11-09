@extends('layouts.app')

@php
    $seccion = $data['seccion'] ?? 'version';
    $titulo = $data['titulo'] ?? 'Información del Dispositivo Biométrico';
    $conectado = isset($data['ok']) && $data['ok'] == 1;
@endphp

@section('title', $titulo)

@section('content')
<div class="container py-3">
    {{-- Pestañas de navegación rápida de los endpoints del biométrico --}}
    <ul class="nav nav-pills mb-3 gap-2">
        <li class="nav-item">
            <a class="nav-link {{ $seccion === 'version' ? 'active' : 'bg-white border' }}" href="{{ route('version') }}">
                <i class="fa-solid fa-microchip me-1"></i> Versión / Info
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $seccion === 'users' ? 'active' : 'bg-white border' }}" href="{{ route('biometrico.users') }}">
                <i class="fa-solid fa-users me-1"></i> Usuarios
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $seccion === 'marcaciones' ? 'active' : 'bg-white border' }}" href="{{ route('biometrico.marcaciones') }}">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Marcaciones
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $seccion === 'herramientas' ? 'active' : 'bg-white border' }}" href="{{ route('biometrico.herramientas') }}">
                <i class="fa-solid fa-screwdriver-wrench me-1"></i> Editar Usuario / Exportar .DAT
            </a>
        </li>
    </ul>

    <div class="card shadow-lg border-0">
        <div class="card-header {{ $conectado ? 'bg-primary' : 'bg-danger' }} text-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0">
                    <i class="fas fa-fingerprint me-2"></i> {{ $titulo }}
                </h5>
                @if($seccion !== 'herramientas')
                    @if($conectado)
                        <span class="badge bg-success border border-light">
                            <i class="fa-solid fa-plug-circle-check me-1"></i> Conectado
                        </span>
                    @else
                        <span class="badge bg-dark border border-light">
                            <i class="fa-solid fa-plug-circle-xmark me-1"></i> Sin Conexión
                        </span>
                    @endif
                @endif
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($seccion !== 'herramientas')
                    <a href="{{ request()->fullUrlWithQuery(['json' => 1]) }}" target="_blank" class="btn btn-outline-light btn-sm" title="Ver respuesta en formato JSON">
                        <i class="fa-solid fa-code me-1"></i> JSON
                    </a>
                @endif
                <button type="button" onclick="window.location.reload()" class="btn btn-light btn-sm">
                    <i class="fa-solid fa-rotate-right me-1"></i> Actualizar
                </button>
                <a href="{{ url('/') }}" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Inicio
                </a>
            </div>
        </div>

        <div class="card-body p-4">
            @if($conectado)
                @if($seccion !== 'herramientas')
                    <div class="alert alert-success d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>{{ $data['mensaje'] ?? 'Conectado correctamente.' }}</strong>
                        </div>
                        <small class="text-muted">
                            IP: {{ config('zkteco.ip') }}:{{ config('zkteco.port', 4370) }}
                        </small>
                    </div>
                @endif

                {{-- SECCIÓN: VERSIÓN / INFO DEL DISPOSITIVO --}}
                @if($seccion === 'version' && isset($data['info']))
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <tbody>
                                @foreach($data['info'] as $key => $value)
                                    <tr>
                                        <th class="text-capitalize" style="width: 30%;">
                                            {{ str_replace('_', ' ', $key) }}
                                        </th>
                                        <td>
                                            @if($value === '' || $value === null)
                                                <span class="text-muted fst-italic">No disponible</span>
                                            @else
                                                <span class="fw-medium">{{ $value }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                {{-- SECCIÓN: USUARIOS DEL BIOMÉTRICO --}}
                @elseif($seccion === 'users')
                    @php $usuarios = $data['usuarios'] ?? []; @endphp
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <span class="badge bg-primary fs-6">
                            Total de usuarios: {{ count($usuarios) }}
                        </span>
                        <div class="input-group input-group-sm" style="max-width: 320px;">
                            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" id="buscadorTabla" class="form-control" placeholder="Buscar por DNI / ID o nombre...">
                        </div>
                    </div>

                    @if(count($usuarios) > 0)
                        <div class="table-responsive" style="max-height: 520px;">
                            <table class="table table-striped table-hover align-middle" id="tablaDatos">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>UID</th>
                                        <th>ID / Documento</th>
                                        <th>Nombre</th>
                                        <th>Rol / Nivel</th>
                                        <th class="text-end">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($usuarios as $u)
                                        <tr>
                                            <td>{{ $u['uid'] ?? '-' }}</td>
                                            <td class="fw-semibold">{{ $u['id'] ?? '-' }}</td>
                                            <td>{{ $u['nombre'] ?? '-' }}</td>
                                            <td>
                                                @if(($u['rol'] ?? 0) == 14)
                                                    <span class="badge bg-warning text-dark">Administrador ({{ $u['rol'] }})</span>
                                                @else
                                                    <span class="badge bg-secondary">Usuario ({{ $u['rol'] ?? 0 }})</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('biometrico.herramientas', ['id' => $u['id'] ?? '', 'nombre' => $u['nombre'] ?? '', 'rol' => $u['rol'] ?? 0]) }}"
                                                   class="btn btn-outline-primary btn-sm"
                                                   title="Modificar ID o Nombre de este usuario">
                                                    <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info mb-0">
                            <i class="fa-solid fa-circle-info me-1"></i> El dispositivo está conectado pero no devolvió usuarios registrados.
                        </div>
                    @endif

                {{-- SECCIÓN: MARCACIONES DEL BIOMÉTRICO --}}
                @elseif($seccion === 'marcaciones')
                    @php $marcaciones = $data['marcaciones'] ?? []; @endphp
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary fs-6">
                                Total de marcaciones en memoria: {{ count($marcaciones) }}
                            </span>
                            <a href="{{ route('biometrico.herramientas') }}" class="btn btn-success btn-sm">
                                <i class="fa-solid fa-file-arrow-down me-1"></i> Exportar a _attlog.dat
                            </a>
                        </div>
                        <div class="input-group input-group-sm" style="max-width: 320px;">
                            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" id="buscadorTabla" class="form-control" placeholder="Buscar por documento o fecha...">
                        </div>
                    </div>

                    @if(count($marcaciones) > 0)
                        <div class="table-responsive" style="max-height: 520px;">
                            <table class="table table-striped table-hover align-middle" id="tablaDatos">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>UID</th>
                                        <th>ID / Documento</th>
                                        <th>Fecha y Hora</th>
                                        <th>Estado</th>
                                        <th>Tipo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_slice($marcaciones, 0, 500) as $m)
                                        <tr>
                                            <td>{{ $m['uid'] ?? '-' }}</td>
                                            <td class="fw-semibold">{{ $m['id'] ?? '-' }}</td>
                                            <td>{{ $m['fecha'] ?? '-' }}</td>
                                            <td>{{ $m['estado'] ?? '-' }}</td>
                                            <td>{{ $m['tipo'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if(count($marcaciones) > 500)
                            <div class="text-muted small mt-2">
                                Mostrando las 500 marcaciones más recientes de {{ count($marcaciones) }} (use el botón JSON o Exportar .DAT para obtener la totalidad).
                            </div>
                        @endif
                    @else
                        <div class="alert alert-info mb-0">
                            <i class="fa-solid fa-circle-info me-1"></i> No hay marcaciones almacenadas actualmente en el dispositivo biométrico.
                        </div>
                    @endif

                {{-- SECCIÓN: HERRAMIENTAS (MODIFICAR USUARIO Y EXPORTAR ATTLOG.DAT) --}}
                @elseif($seccion === 'herramientas')
                    <div class="row g-4">
                        {{-- MÓDULO 1: CAMBIAR USERID / NOMBRE EN EL BIOMÉTRICO --}}
                        <div class="col-lg-6">
                            <div class="card h-100 border shadow-sm">
                                <div class="card-header bg-light fw-bold d-flex align-items-center justify-content-between">
                                    <span>
                                        <i class="fa-solid fa-user-pen text-primary me-2"></i> Modificar Usuario del Biométrico
                                    </span>
                                    <span class="badge bg-info text-dark">Conserva Huellas y Rostro</span>
                                </div>
                                <div class="card-body">
                                    <p class="small text-muted mb-3">
                                        Actualiza el <strong>UserID (DNI)</strong> y/o el <strong>Nombre</strong> directamente sobre el UID interno del reloj sin perder sus huellas dactilares ni plantillas faciales.
                                    </p>

                                    <form id="formActualizarUsuario">
                                        <div class="mb-3">
                                            <label for="id_actual" class="form-label fw-semibold">ID / DNI Actual en el Biométrico <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="id_actual" name="id_actual"
                                                   maxlength="9" required
                                                   value="{{ $data['id_precargado'] ?? '' }}"
                                                   placeholder="Ej: 5532962">
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label for="id_nuevo" class="form-label fw-semibold mb-0">Nuevo ID / DNI <span class="text-danger">*</span></label>
                                                <button type="button" id="btnCopiarId" class="btn btn-link btn-sm p-0 text-decoration-none">
                                                    <i class="fa-regular fa-copy me-1"></i>Mantener el mismo ID
                                                </button>
                                            </div>
                                            <input type="text" class="form-control" id="id_nuevo" name="id_nuevo"
                                                   maxlength="9" required
                                                   value="{{ $data['id_precargado'] ?? '' }}"
                                                   placeholder="Ej: 45532962">
                                            <div class="form-text">Si solo desea cambiar el nombre, deje el mismo ID actual.</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="nombre_nuevo" class="form-label fw-semibold">Nuevo Nombre (Opcional)</label>
                                            <input type="text" class="form-control" id="nombre_nuevo" name="nombre_nuevo"
                                                   maxlength="24"
                                                   value="{{ $data['nombre_precargado'] ?? '' }}"
                                                   placeholder="Dejar vacío para conservar el nombre actual (máx. 24 caract.)">
                                        </div>

                                        <div class="mb-3">
                                            <label for="rol" class="form-label fw-semibold">Nivel / Rol en el Biométrico</label>
                                            <select class="form-select" id="rol" name="rol">
                                                <option value="">Conservar rol actual</option>
                                                <option value="0" {{ (isset($data['id_precargado']) && $data['id_precargado'] !== '' && ($data['rol_precargado'] ?? 0) == 0) ? 'selected' : '' }}>Usuario Normal (0)</option>
                                                <option value="14" {{ (isset($data['id_precargado']) && $data['id_precargado'] !== '' && ($data['rol_precargado'] ?? 0) == 14) ? 'selected' : '' }}>Administrador (14)</option>
                                            </select>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="actualizar_bd_local" name="actualizar_bd_local" checked>
                                            <label class="form-check-label small" for="actualizar_bd_local">
                                                Actualizar también el DNI en las marcaciones ya guardadas en la base de datos local (<code>biometrico_local</code>)
                                            </label>
                                        </div>

                                        <div class="mb-4 p-3 bg-warning bg-opacity-10 border border-warning rounded">
                                            <label for="clave_acceso" class="form-label fw-semibold mb-1">
                                                <i class="fa-solid fa-lock text-warning me-1"></i> Clave de Acceso de Seguridad <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="clave_acceso" name="clave_acceso"
                                                       required autocomplete="off"
                                                       placeholder="Ingrese la clave global de autorización">
                                                <button class="btn btn-outline-secondary" type="button" id="btnToggleClave" title="Mostrar / Ocultar clave">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <button type="submit" id="btnGuardarUsuario" class="btn btn-primary w-100">
                                            <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios en el Biométrico
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- MÓDULO 2: EXPORTAR ARCHIVO _attlog.dat --}}
                        <div class="col-lg-6">
                            <div class="card h-100 border shadow-sm">
                                <div class="card-header bg-light fw-bold d-flex align-items-center justify-content-between">
                                    <span>
                                        <i class="fa-solid fa-file-export text-success me-2"></i> Exportar Archivo <code>_attlog.dat</code>
                                    </span>
                                    <span class="badge bg-success">Formato Nativo USB</span>
                                </div>
                                <div class="card-body">
                                    <p class="small text-muted mb-3">
                                        Genera y descarga el archivo <code>{SERIAL}_attlog.dat</code> con la misma estructura separada por tabulaciones que exporta el reloj biométrico físico por USB.
                                    </p>

                                    <div class="bg-light border rounded p-2 mb-3 small font-monospace text-secondary">
                                        &nbsp;43584972&nbsp;&nbsp;&nbsp;&nbsp;2026-05-29 12:30:56&nbsp;&nbsp;&nbsp;&nbsp;1&nbsp;&nbsp;&nbsp;&nbsp;255&nbsp;&nbsp;&nbsp;&nbsp;1&nbsp;&nbsp;&nbsp;&nbsp;0
                                    </div>

                                    <form id="formExportarDat">
                                        <div class="mb-3">
                                            <label for="origen" class="form-label fw-semibold">Origen de las Marcaciones</label>
                                            <select class="form-select" id="origen" name="origen">
                                                <option value="biometrico">Directo del Biométrico Físico ({{ config('zkteco.ip') }})</option>
                                                <option value="local">Base de Datos Local ({{ $data['total_locales'] ?? 0 }} registros guardados)</option>
                                            </select>
                                            <div class="form-text">
                                                Si el reloj no está conectado, puede elegir "Base de Datos Local" para exportar las marcaciones ya guardadas en su PC.
                                            </div>
                                        </div>

                                        <div class="row g-2 mb-3">
                                            <div class="col-sm-6">
                                                <label for="dat_desde" class="form-label fw-semibold">Fecha Inicio (Opcional)</label>
                                                <input type="date" class="form-control" id="dat_desde" name="desde">
                                            </div>
                                            <div class="col-sm-6">
                                                <label for="dat_hasta" class="form-label fw-semibold">Fecha Fin (Opcional)</label>
                                                <input type="date" class="form-control" id="dat_hasta" name="hasta">
                                            </div>
                                            <div class="col-12">
                                                <div class="form-text">Deje ambas fechas en blanco para exportar <strong>todas</strong> las marcaciones completas.</div>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="serial_dat" class="form-label fw-semibold">Prefijo de Serie para el Archivo (Opcional)</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="serial_dat" name="serial"
                                                       placeholder="Autodetectar del equipo (ej: {{ $data['serial_local'] ?? 'AF4C172960193' }})">
                                                <span class="input-group-text">_attlog.dat</span>
                                            </div>
                                        </div>

                                        <button type="submit" id="btnExportarDat" class="btn btn-success w-100">
                                            <i class="fa-solid fa-download me-1"></i> Generar y Descargar <code>_attlog.dat</code>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

            @else
                {{-- INTERFAZ CUANDO NO HAY CONEXIÓN --}}
                <div class="text-center py-4">
                    <div class="mb-3 text-danger">
                        <i class="fa-solid fa-plug-circle-xmark fa-4x"></i>
                    </div>
                    <h4 class="fw-bold text-danger mb-2">
                        No hay conexión con el dispositivo biométrico
                    </h4>
                    <p class="text-muted mb-4">
                        {{ $data['mensaje'] ?? 'No se pudo establecer comunicación en el tiempo límite configurado.' }}
                    </p>

                    <div class="row justify-content-center g-3 mb-4" style="max-width: 680px; margin: 0 auto;">
                        <div class="col-sm-4">
                            <div class="p-3 bg-light rounded border">
                                <div class="small text-muted">IP / Destino</div>
                                <div class="fw-bold text-truncate" title="{{ $data['ip'] ?? '' }}">
                                    {{ $data['ip'] ?? config('zkteco.ip', 'No definida') }}
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 bg-light rounded border">
                                <div class="small text-muted">Tipo / Protocolo</div>
                                <div class="fw-bold">
                                    {{ $data['port'] ?? config('zkteco.port', 4370) }} ({{ config('zkteco.establecimiento_master') ? 'UDP Master' : config('zkteco.protocol', 'UDP') }})
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 bg-light rounded border">
                                <div class="small text-muted">Tiempo de espera</div>
                                <div class="fw-bold">
                                    {{ config('zkteco.timeout', 2) }} seg. (máx.)
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <button type="button" onclick="window.location.reload()" class="btn btn-danger px-4">
                            <i class="fa-solid fa-rotate-right me-1"></i> Reintentar conexión
                        </button>
                        <a href="{{ route('biometrico.herramientas') }}" class="btn btn-outline-primary px-4">
                            <i class="fa-solid fa-screwdriver-wrench me-1"></i> Ir a Herramientas / Exportar .DAT Local
                        </a>
                        <a href="{{ url('/') }}" class="btn btn-outline-secondary px-4">
                            <i class="fa-solid fa-house me-1"></i> Ir al Inicio
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtro rápido en tablas de usuarios y marcaciones
    const buscador = document.getElementById('buscadorTabla');
    const tabla = document.getElementById('tablaDatos');
    if (buscador && tabla) {
        buscador.addEventListener('input', function () {
            const filtro = this.value.toLowerCase();
            const filas = tabla.querySelectorAll('tbody tr');
            filas.forEach(fila => {
                fila.style.display = fila.textContent.toLowerCase().includes(filtro) ? '' : 'none';
            });
        });
    }

    // Botón para copiar ID actual a ID nuevo
    const btnCopiarId = document.getElementById('btnCopiarId');
    if (btnCopiarId) {
        btnCopiarId.addEventListener('click', function () {
            const idActual = document.getElementById('id_actual').value;
            document.getElementById('id_nuevo').value = idActual;
        });
    }

    // Mostrar / ocultar clave de acceso
    const btnToggleClave = document.getElementById('btnToggleClave');
    const inputClave = document.getElementById('clave_acceso');
    if (btnToggleClave && inputClave) {
        btnToggleClave.addEventListener('click', function () {
            const esPassword = inputClave.type === 'password';
            inputClave.type = esPassword ? 'text' : 'password';
            this.innerHTML = esPassword ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
        });
    }

    // Envío del formulario de actualización de usuario en el biométrico
    const formActualizarUsuario = document.getElementById('formActualizarUsuario');
    if (formActualizarUsuario) {
        formActualizarUsuario.addEventListener('submit', function (e) {
            e.preventDefault();

            const btn = document.getElementById('btnGuardarUsuario');
            const textoOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Conectando con el biométrico...';

            const payload = {
                clave_acceso: document.getElementById('clave_acceso').value,
                id_actual: document.getElementById('id_actual').value.trim(),
                id_nuevo: document.getElementById('id_nuevo').value.trim(),
                nombre_nuevo: document.getElementById('nombre_nuevo').value.trim(),
                rol: document.getElementById('rol').value,
                actualizar_bd_local: document.getElementById('actualizar_bd_local').checked
            };

            fetch("{{ route('biometrico.usuarios.actualizar') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(async response => {
                const data = await response.json();
                return { status: response.status, data };
            })
            .then(({ status, data }) => {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;

                if (data.ok === 1 || data.success) {
                    const d = data.datos || {};
                    Swal.fire({
                        icon: 'success',
                        title: '¡Usuario Actualizado!',
                        html: `
                            <div class="text-start small">
                                <p class="mb-2">${data.mensaje}</p>
                                <ul class="list-group">
                                    <li class="list-group-item"><strong>UID Interno:</strong> ${d.uid ?? '-'}</li>
                                    <li class="list-group-item"><strong>ID / DNI:</strong> ${d.id_anterior ?? '-'} &rarr; <span class="text-primary fw-bold">${d.id_nuevo ?? '-'}</span></li>
                                    <li class="list-group-item"><strong>Nombre:</strong> ${d.nombre_anterior ?? '-'} &rarr; <span class="text-primary fw-bold">${d.nombre_nuevo ?? '-'}</span></li>
                                    <li class="list-group-item"><strong>Marcaciones locales actualizadas:</strong> ${d.marcaciones_locales_actualizadas ?? 0}</li>
                                </ul>
                            </div>
                        `,
                        confirmButtonText: 'Aceptar'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: status === 503 ? 'Sin conexión al biométrico' : 'No se pudo actualizar',
                        text: data.mensaje || 'Verifique los datos e intente nuevamente.',
                        confirmButtonText: 'Entendido'
                    });
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
                Swal.fire({
                    icon: 'error',
                    title: 'Error de red',
                    text: 'Ocurrió un problema al procesar la solicitud.',
                });
                console.error(err);
            });
        });
    }

    // Exportación de archivo _attlog.dat
    const formExportarDat = document.getElementById('formExportarDat');
    if (formExportarDat) {
        formExportarDat.addEventListener('submit', function (e) {
            e.preventDefault();

            const btn = document.getElementById('btnExportarDat');
            const textoOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Preparando archivo .DAT...';

            const params = new URLSearchParams();
            params.set('origen', document.getElementById('origen').value);

            const desde = document.getElementById('dat_desde').value;
            const hasta = document.getElementById('dat_hasta').value;
            const serial = document.getElementById('serial_dat').value.trim();

            if (desde) params.set('desde', desde);
            if (hasta) params.set('hasta', hasta);
            if (serial) params.set('serial', serial);

            // Primero verificamos vía JSON para comprobar conexión y cantidad de registros sin recargar la página
            const verifyParams = new URLSearchParams(params);
            verifyParams.set('verificar', '1');

            fetch("{{ route('biometrico.exportar-attlog') }}?" + verifyParams.toString(), {
                headers: { 'Accept': 'application/json' }
            })
            .then(async response => {
                const data = await response.json();
                return { status: response.status, data };
            })
            .then(({ status, data }) => {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;

                if (data.ok === 1) {
                    // Iniciar descarga directa del archivo .dat
                    window.location.href = "{{ route('biometrico.exportar-attlog') }}?" + params.toString();

                    Swal.fire({
                        icon: 'success',
                        title: 'Descarga Iniciada',
                        text: `Archivo ${data.filename} generado con ${data.total} marcaciones.`,
                        timer: 3500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: status === 503 ? 'Sin conexión al biométrico' : 'No se pudo exportar',
                        text: data.mensaje || 'Error al generar el archivo .dat.',
                        confirmButtonText: 'Entendido'
                    });
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo generar la exportación .dat.',
                });
                console.error(err);
            });
        });
    }
});
</script>
@endpush
@endsection
