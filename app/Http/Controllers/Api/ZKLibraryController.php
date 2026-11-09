<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarSubirMarcaciones;
use App\Models\Marcacion;
use Illuminate\Http\Request;
use App\ZKService\ZKLib;
use App\ZKService\ZKLibrary;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Rats\Zkteco\Lib\ZKTeco;

class ZKLibraryController extends Controller
{
    private $zklib_main;

    private $marcacion_model;

    public function __construct()
    {
        $this->marcacion_model = new Marcacion();
    }
    public function obtenerMetodos()
    {
        return $this->marcacion_model->getMethods();
    }
    public function herramientas(Request $request)
    {
        $totalLocales = Marcacion::count();
        $ultimaMarcacionLocal = Marcacion::orderBy('fecha', 'desc')->first();
        $serialLocal = Marcacion::whereNotNull('serial')->where('serial', '!=', '')->value('serial') ?? 'BIOMETRICO';

        $data = [
            'ok' => 1,
            'conectado' => true,
            'seccion' => 'herramientas',
            'titulo' => 'Gestión de Usuario y Exportación .DAT',
            'mensaje' => 'Módulo de actualización de usuarios en el reloj biométrico y generación de archivo _attlog.dat.',
            'total_locales' => $totalLocales,
            'ultima_fecha_local' => $ultimaMarcacionLocal->fecha ?? null,
            'serial_local' => $serialLocal,
            'id_precargado' => $request->query('id', ''),
            'nombre_precargado' => $request->query('nombre', ''),
            'rol_precargado' => $request->query('rol', '0'),
        ];

        return view('info', compact('data'));
    }

    public function cambiarIdUsuario(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clave_acceso'        => 'required|string',
            'id_actual'           => 'required|string|max:9',
            'id_nuevo'            => 'required|string|max:9',
            'nombre_nuevo'        => 'nullable|string|max:24',
            'rol'                 => 'nullable|integer|in:0,14',
            'actualizar_bd_local' => 'nullable|boolean',
        ], [
            'clave_acceso.required' => 'Debe ingresar la clave de acceso de seguridad.',
            'id_actual.required'    => 'El ID/DNI actual del usuario es obligatorio.',
            'id_actual.max'         => 'El ID actual no debe superar los 9 caracteres.',
            'id_nuevo.required'     => 'El ID/DNI nuevo es obligatorio (si solo cambia el nombre, deje el mismo ID).',
            'id_nuevo.max'          => 'El nuevo ID no debe superar los 9 caracteres en el biométrico.',
            'nombre_nuevo.max'      => 'El nombre en el biométrico soporta hasta 24 caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => 0,
                'success' => false,
                'mensaje' => $validator->errors()->first(),
                'errores' => $validator->errors(),
            ], 422);
        }

        $claveConfigurada = (string) config('zkteco.admin_key', 'admin2026');
        if (!hash_equals($claveConfigurada, (string) $request->input('clave_acceso'))) {
            return response()->json([
                'ok' => 0,
                'success' => false,
                'mensaje' => 'Clave de acceso incorrecta. No está autorizado para modificar datos de usuarios.',
            ], 403);
        }

        $idActual = trim($request->input('id_actual'));
        $idNuevo = trim($request->input('id_nuevo'));
        $nombreNuevo = $request->input('nombre_nuevo');
        $actualizarBdLocal = $request->boolean('actualizar_bd_local');
        $rol = $request->filled('rol') ? (int) $request->input('rol') : null;

        $resultado = $this->marcacion_model->actualizarUsuarioBiometrico(
            $idActual,
            $idNuevo,
            $nombreNuevo,
            $actualizarBdLocal,
            $rol
        );

        return response()->json([
            'ok' => $resultado['ok'],
            'success' => $resultado['ok'] === 1,
            'conectado' => $resultado['conectado'] ?? false,
            'mensaje' => $resultado['mensaje'],
            'datos' => $resultado['datos'] ?? null,
        ], $resultado['status'] ?? 200);
    }

    public function exportarAttlogDat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origen' => 'required|in:biometrico,local',
            'desde'  => 'nullable|date',
            'hasta'  => 'nullable|date|after_or_equal:desde',
            'serial' => 'nullable|string|max:30',
        ], [
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => 0,
                'success' => false,
                'mensaje' => $validator->errors()->first(),
            ], 422);
        }

        $origen = $request->input('origen', 'biometrico');
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');
        $serial = $request->input('serial');

        $resultado = $this->marcacion_model->exportarAttlogDat($origen, $desde, $hasta, $serial);

        if (($resultado['ok'] ?? 0) !== 1) {
            return response()->json([
                'ok' => 0,
                'success' => false,
                'conectado' => $resultado['conectado'] ?? false,
                'mensaje' => $resultado['mensaje'] ?? 'No se pudo exportar el archivo .dat.',
            ], $resultado['status'] ?? 503);
        }

        if ($request->boolean('verificar')) {
            return response()->json([
                'ok' => 1,
                'success' => true,
                'filename' => $resultado['filename'],
                'total' => $resultado['total'],
                'mensaje' => "Listo para descargar ({$resultado['total']} registros).",
            ]);
        }

        return response($resultado['contenido'], 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $resultado['filename'] . '"',
            'X-Total-Records' => $resultado['total'],
        ]);
    }
    public function getUsers(Request $request)
    {
        $users = $this->marcacion_model->getUsers();

        if ($users === false) {
            $data = [
                'ok' => 0,
                'conectado' => false,
                'seccion' => 'users',
                'titulo' => 'Usuarios del Dispositivo Biométrico',
                'ip' => config('zkteco.ip'),
                'port' => config('zkteco.port', 4370),
                'mensaje' => 'No hay conexión con el dispositivo biométrico (' . config('zkteco.ip') . ':' . config('zkteco.port', 4370) . ').',
            ];

            if ($request->wantsJson() || $request->boolean('json')) {
                return response()->json($data, 503);
            }

            return view('info', compact('data'));
        }

        $usuarios = [];
        if (is_array($users) && count($users)) {
            foreach ($users as $key => $user) {
                if (config('zkteco.establecimiento_master')) {
                    $usuarios[] = [
                        'uid' => $user['uid'] ?? $key,
                        'id' => $user['userid'] ?? '',
                        'nombre' => $user['name'] ?? '',
                        'rol' => $user['role'] ?? 0,
                        'cardno' => $user['cardno'] ?? '',
                    ];
                } else {
                    $usuarios[] = [
                        'uid' => $key,
                        'id' => $user[0] ?? '',
                        'nombre' => $user[1] ?? '',
                        'rol' => $user[2] ?? 0,
                        'pin' => $user[3] ?? '',
                    ];
                }
            }
        }

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json($usuarios, 200, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        $data = [
            'ok' => 1,
            'conectado' => true,
            'seccion' => 'users',
            'titulo' => 'Usuarios del Dispositivo Biométrico',
            'mensaje' => 'Usuarios obtenidos correctamente del dispositivo biométrico.',
            'usuarios' => $usuarios,
        ];

        return view('info', compact('data'));
    }
    public function getversion(Request $request)
    {
        $response = $this->marcacion_model->getversion();
        $data = $response->getData(true);
        $data['seccion'] = 'version';
        $data['titulo'] = 'Información del Dispositivo Biométrico';

        if ($request->wantsJson() || $request->boolean('json')) {
            return $response;
        }

        return view('info', compact('data'));
    }
    public function getAttendances(Request $request)
    {
        $attendances = $this->marcacion_model->getAttedances();
        if ($attendances === 404 || $attendances === false) {
            $data = [
                'ok' => 0,
                'conectado' => false,
                'seccion' => 'marcaciones',
                'titulo' => 'Marcaciones del Dispositivo Biométrico',
                'ip' => config('zkteco.ip'),
                'port' => config('zkteco.port', 4370),
                'mensaje' => 'No hay conexión con el dispositivo biométrico (' . config('zkteco.ip') . ':' . config('zkteco.port', 4370) . ').',
            ];

            if ($request->wantsJson() || $request->boolean('json')) {
                return response()->json($data, 503);
            }

            return view('info', compact('data'));
        }

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json($attendances, 200);
        }

        $marcaciones = [];
        if (is_array($attendances)) {
            foreach ($attendances as $att) {
                if (config('zkteco.establecimiento_master')) {
                    $marcaciones[] = [
                        'uid' => $att['uid'] ?? '',
                        'id' => $att['id'] ?? '',
                        'estado' => $att['state'] ?? '',
                        'fecha' => $att['timestamp'] ?? '',
                        'tipo' => $att['type'] ?? 1,
                    ];
                } else {
                    $marcaciones[] = [
                        'uid' => $att[0] ?? '',
                        'id' => $att[1] ?? '',
                        'estado' => $att[4] ?? ($att[2] ?? ''),
                        'fecha' => $att[3] ?? '',
                        'tipo' => $att[2] ?? 1,
                    ];
                }
            }
        }

        $data = [
            'ok' => 1,
            'conectado' => true,
            'seccion' => 'marcaciones',
            'titulo' => 'Marcaciones del Dispositivo Biométrico',
            'mensaje' => 'Marcaciones obtenidas correctamente del dispositivo biométrico.',
            'marcaciones' => $marcaciones,
        ];

        return view('info', compact('data'));
    }
    public function sincronizarLocalNube(Request $request)
    {
        $reglas = [
            'desde' => 'required|date|before_or_equal:' . now()->toDateString(),
            'hasta' => 'required|date|after_or_equal:desde|before_or_equal:' . now()->toDateString(),
        ];
        $mensajes = [
            'desde.required' => 'El campo de fecha de inicio es obligatorio.',
            'desde.date' => 'La fecha de inicio debe ser una fecha válida.',
            'desde.before_or_equal' => 'La fecha de inicio no puede ser posterior a hoy.',

            'hasta.required' => 'El campo de fecha de fin es obligatorio.',
            'hasta.date' => 'La fecha de fin debe ser una fecha válida.',
            'hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'hasta.before_or_equal' => 'La fecha de fin no puede ser posterior a hoy.',
        ];
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $validator = Validator::make($request->all(), $reglas, $mensajes);
        if ($validator->fails()) {
            return response()->json([
                'success' => 0,
                'mensaje' => 'Error en los datos enviados.',
                'errores' => $validator->errors(),
            ], 422);
        }
        $desde = $request->desde;
        $hasta = $request->hasta;

        $marcaciones = Marcacion::whereBetween('fecha', ["$desde 00:00:00", "$hasta 23:59:59"])
            ->whereNotNull('numero_documento')
            ->get(['numero_documento', 'uid', 'estado', 'fecha', 'tipo', 'serial', 'ip']);
        $rutaLote = config('app.api_url') . '/api/guardar-marcaciones-lote';
        $insertadasTotal = 0;
        // Enviar por lotes para no reventar el payload
        foreach ($marcaciones->chunk(1000) as $chunk) {
            $payload = [
                'marcaciones' => $chunk->map(function ($m) {
                    return [
                        'dni'    => $m->numero_documento,
                        'uid'    => $m->uid,
                        'estado' => $m->estado,
                        'fecha'  => $m->fecha,   // asegúrate del formato que espera el API
                        'tipo'   => $m->tipo,
                        'serial' => $m->serial,
                        'ip'     => $m->ip,
                    ];
                })->values()->all()
            ];

            $response = Http::timeout(120)->retry(3, 1000)->post($rutaLote, $payload);

            if ($response->successful()) {
                $insertadasTotal += (int) $response->json('insertadas', 0);
            } else {
                Log::error('Error sync marcaciones', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        }

        return response()->json([
            'success' => 1,
            'mensaje' => "Guardado satisfactoriamente, {$insertadasTotal} registros procesados"
        ], 200);
    }
    public function saveAttendandes(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $reglas = [
            'desde' => 'required|date|before_or_equal:' . now()->toDateString(),
            'hasta' => 'required|date|after_or_equal:desde|before_or_equal:' . now()->toDateString(),
        ];
        $mensajes = [
            'desde.required' => 'El campo de fecha de inicio es obligatorio.',
            'desde.date' => 'La fecha de inicio debe ser una fecha válida.',
            'desde.before_or_equal' => 'La fecha de inicio no puede ser posterior a hoy.',

            'hasta.required' => 'El campo de fecha de fin es obligatorio.',
            'hasta.date' => 'La fecha de fin debe ser una fecha válida.',
            'hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'hasta.before_or_equal' => 'La fecha de fin no puede ser posterior a hoy.',
        ];
        $validator = Validator::make($request->all(), $reglas, $mensajes);
        if ($validator->fails()) {
            return response()->json([
                'success' => 0,
                'mensaje' => 'Error en los datos enviados.',
                'errores' => $validator->errors(),
            ], 422);
        }
        $desde = $request->desde;
        $hasta = $request->hasta;

        $estado_save = $this->marcacion_model->saveAttendancesByAsc($desde, $hasta);

        if ($estado_save == -1) {
            $msg = 'No hay conexión con el dispositivo biométrico (' . config('zkteco.ip') . ':' . config('zkteco.port', 4370) . ').';
            return response()->json([
                'success' => 0,
                'conectado' => false,
                'mensaje' => $msg,
                'message' => $msg,
            ], 503);
        }
        if ($estado_save >= 0) {
            return response()->json([
                'success' => 1,
                'conectado' => true,
                'mensaje' => "Guardado satisfactoriamente, $estado_save registros procesados"
            ], 200);
        }
    }
    public function obtenerMarcacionesApi(Request $request)
    {
        $marcaciones_api = $this->marcacion_model->getAllAttendacesApi();

        if ($marcaciones_api === false) {
            $data = [
                'ok' => 0,
                'conectado' => false,
                'seccion' => 'api-all',
                'titulo' => 'Marcaciones en Servidor Remoto (API)',
                'ip' => config('app.api_url'),
                'port' => null,
                'mensaje' => 'No hay conexión con el servidor API externo (' . config('app.api_url') . ').',
            ];

            if ($request->wantsJson() || $request->boolean('json')) {
                return response()->json($data, 503);
            }

            return view('info', compact('data'));
        }

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json($marcaciones_api, 200);
        }

        $data = [
            'ok' => 1,
            'conectado' => true,
            'seccion' => 'api-all',
            'titulo' => 'Marcaciones en Servidor Remoto (API)',
            'mensaje' => 'Marcaciones del servidor remoto obtenidas correctamente.',
            'api_data' => is_array($marcaciones_api) ? $marcaciones_api : [],
        ];

        return view('info', compact('data'));
    }
    public function verificarDniPersonal(Request $request)
    {
        $marcaciones_api = $this->marcacion_model->verificarDniPersonal($request);

        return response()->json($marcaciones_api, 200);
    }
    public function eliminarDatosBiometrico()
    {
        $cantRegistos = $this->marcacion_model->deleteAttendances();
        if ($cantRegistos === -1) {
            $msg = 'No hay conexión con el dispositivo biométrico (' . config('zkteco.ip') . ':' . config('zkteco.port', 4370) . ').';
            return response()->json([
                'success' => 0,
                'conectado' => false,
                'mensaje' => $msg,
                'message' => $msg,
            ], 503);
        }
        return response()->json([
            'success' => 1,
            'conectado' => true,
            'mensaje' => "$cantRegistos Registros Eliminados",
        ], 200);
    }
}
