<?php

namespace App\Http\Traits;

use App\Models\Marcacion;
use App\ZKService\ZKLibrary; // propio
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Rats\Zkteco\Lib\ZKTeco;// vendor
trait MarcacionTrait
{
    private $zklib;
    private $tipo_marcacion;
    private $tipoestablecimiento;
    public function __construct(array $attributes = []){
        parent::__construct($attributes);
        if(config('zkteco.establecimiento_master')){
            $this->zklib = new ZKTeco(
                config('zkteco.ip'),
                (int) config('zkteco.port', 4370)
            );
            $this->tipoestablecimiento='master';
        }else{
            $this->tipoestablecimiento='otros';
            $this->zklib = new ZKLibrary(
                config('zkteco.ip'),
                config('zkteco.port', 4370),
                config('zkteco.protocol', 'UDP')
            );
        }
        $this->configurarSocketTimeout((int) config('zkteco.timeout', 2), 0);
        $this->tipo_marcacion = [
            0 => 'ENTRADA',
            1 => 'SALIDA',
            2 => 'ENTRADA TE',
            3 => 'ENTRADA TE',
            4 => 'ENTRADA TE',
            5 => 'SALIDA TE'
        ];
    }
    public function configurarSocketTimeout(int $segundos, int $microsegundos = 0): void
    {
        $timeout = ['sec' => $segundos, 'usec' => $microsegundos];
        if ($this->tipoestablecimiento === 'master' && isset($this->zklib->_zkclient)) {
            @socket_set_option($this->zklib->_zkclient, SOL_SOCKET, SO_RCVTIMEO, $timeout);
            @socket_set_option($this->zklib->_zkclient, SOL_SOCKET, SO_SNDTIMEO, $timeout);
        } elseif (isset($this->zklib->socket)) {
            $this->zklib->setTimeout($segundos, $microsegundos);
        }
    }
    public function conectarBiometrico(?int $timeoutSegundos = null): bool
    {
        $ip = config('zkteco.ip');
        if (empty($ip)) {
            return false;
        }
        $timeout = $timeoutSegundos ?? (int) config('zkteco.timeout', 2);
        $this->configurarSocketTimeout($timeout, 0);

        try {
            $conectado = (bool) @$this->zklib->connect();
            if ($conectado) {
                // Una vez conectado, damos más margen para la descarga de datos (usuarios/marcaciones)
                $this->configurarSocketTimeout(15, 0);
            }
            return $conectado;
        } catch (\Throwable $e) {
            return false;
        }
    }
    public function getMethods(){
        dd(get_class_methods($this->zklib));
        return 0;        
    }
    public function getVersionMaster(){
        $res = $this->conectarBiometrico();
        if (!$res) {
            return response()->json([
                'ok' => 0,
                'conectado' => false,
                'ip' => config('zkteco.ip'),
                'port' => config('zkteco.port', 4370),
                'mensaje' => 'No hay conexión con el dispositivo biométrico (' . config('zkteco.ip') . ':' . config('zkteco.port', 4370) . ').'
            ], 503);
        }
        $info = [
            'version'        => trim($this->zklib->version(), "\0"),
            'os_version'     => trim($this->zklib->osVersion(), "\0"),
            'platform'       => trim($this->zklib->platform(), "\0"),
            'fm_version'     => trim($this->zklib->fmVersion(), "\0"),
            'work_code'      => trim($this->zklib->workCode(), "\0"),
            'ssr'            => trim($this->zklib->ssr(), "\0"),
            'pin_width'      => trim($this->zklib->pinWidth(), "\0"),
            'face_function'  => trim($this->zklib->faceFunctionOn(), "\0"),
            'serial_number'  => trim($this->zklib->serialNumber(), "\0"),
            'device_name'    => trim($this->zklib->deviceName(), "\0"),
            'current_time'   => $this->zklib->getTime(),
            'ip_address'     => $this->zklib->_ip ?? 'No disponible',
            'port'           => $this->zklib->_port ?? null,
        ];

        try {
            if (method_exists($this->zklib, 'getMac')) {
                $info['mac_address'] = trim($this->zklib->getMac(), "\0");
            } else {
                $info['mac_address'] = 'No disponible (no soportado por SDK)';
            }
        } catch (\Throwable $e) {
            $info['mac_address'] = 'Error al obtener MAC: ' . $e->getMessage();
        }

        $this->zklib->disconnect();

        return response()->json([
            'ok' => 1,
            'conectado' => true,
            'mensaje' => 'Información general del dispositivo obtenida correctamente.',
            'info' => $info
        ]);
    }
    public function getVersionOtros(){
        $res = $this->conectarBiometrico();
        if (!$res) {
            return response()->json([
                'ok' => 0,
                'conectado' => false,
                'ip' => config('zkteco.ip'),
                'port' => config('zkteco.port', 4370),
                'mensaje' => 'No hay conexión con el dispositivo biométrico (' . config('zkteco.ip') . ':' . config('zkteco.port', 4370) . ').'
            ], 503);
        }
        try {
            $info = [
                'version'        => trim($this->zklib->getVersion(), "\0"),
                'os_version'     => '',
                'platform'       => '',
                'fm_version'     => '',
                'work_code'      => '',
                'ssr'            => '',
                'pin_width'      => '',
                'face_function'  => '',
                'serial_number'  => trim($this->zklib->getSerialNumber(), "\0"),
                'device_name'    => trim($this->zklib->getDeviceName(), "\0"),
                'current_time'   => $this->zklib->getTime(),
                'ip_address'     => $this->zklib->ip ?? config('zkteco.ip', 'No disponible'),
                'port'           => $this->zklib->port ?? config('zkteco.port'),
            ];

            if (method_exists($this->zklib, 'getMac')) {
                $info['mac_address'] = trim($this->zklib->getMac(), "\0");
            } else {
                $info['mac_address'] = 'No disponible (no soportado por SDK)';
            }

        } catch (\Throwable $e) {
            $this->zklib->disconnect();

            return response()->json([
                'ok' => 0,
                'conectado' => false,
                'mensaje' => 'Error al obtener información del dispositivo: ' . $e->getMessage()
            ], 500);
        }
        $this->zklib->disconnect();
        return response()->json([
            'ok' => 1,
            'conectado' => true,
            'mensaje' => 'Información general del dispositivo obtenida correctamente.',
            'info' => $info
        ]);
    }
    public function getVersion(){
        if($this->tipoestablecimiento=='master'){
            return $this->getVersionMaster();
        }else{
            return $this->getVersionOtros();
        }
    }
    public function getUsers(){
        $res = $this->conectarBiometrico();
        if($res)
        {
            $users = $this->zklib->getUser();
            $this->zklib->disconnect();
            return is_array($users) ? $users : [];
        }
        return false;        
    }
    public function getUser()
    {
        $res = $this->conectarBiometrico();
        if (!$res) {
            return [];
        }

        // Siempre trae todos
        $users = $this->zklib->getUser();
        $this->zklib->disconnect();

        // Filtrar por UID 4
        $uidBuscado = 4;
        foreach ($users as $user) {
            if ($user['uid'] == $uidBuscado) {
                return $user; // devuelves solo ese
            }
        }

        return []; // o null si prefieres
    }
    public function getAttedances(){
        set_time_limit(0);
        $res = $this->conectarBiometrico();
        if($res)
        {
            $attendances = array_reverse($this->zklib->getAttendance());
            $this->zklib->disconnect();
            return $attendances;
        }
        return 404;
    }
    public function cantidadAttendances(){
        $res = $this->conectarBiometrico();
        if($res)
        {
            $attendances = $this->zklib->getAttendance();
            $this->zklib->disconnect();
            return count($attendances);
        }
        return 0;
    }
    public function getAttedancesByAsc(){
        $res = $this->conectarBiometrico();
        if($res)
        {
            $attendances = $this->zklib->getAttendance();
            
            $this->zklib->disconnect();
            
            return $attendances;
        }

        return 404;
    }
    public function saveAttendances(){
        $res = $this->conectarBiometrico();
        if($res)
        {
            $attendances = array_reverse($this->zklib->getAttendance());
            $serialSub = substr($this->zklib->getSerialNumber(false), 14);
            $serial = substr($serialSub, 0, -1);

            if(count($attendances) > 0) 
            {
                foreach ($attendances as $attItem) {    

                    if($this->attendanceUserVerify($attItem[0],$attItem[4])===false)
                    {
                        $marcacion = new Marcacion();
                        $marcacion->uid = $attItem[0];
                        $marcacion->numero_documento = $attItem[1];
                        $marcacion->estado = $attItem[2];
                        $marcacion->fecha = $attItem[3];
                        $marcacion->tipo = $attItem[4];
                        $marcacion->serial = $serial;
                        $marcacion->ip = config('zkteco.ip');
                        $marcacion->save();
                    }
                }
            }
            $this->zklib->disconnect();
            return 1;         
        }
        return 404;
    }
    public function saveAttendancesMaster($desde, $hasta){
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $desde = Carbon::parse($desde)->startOfDay();
        $hasta = Carbon::parse($hasta)->endOfDay();
        $i = 0;
        $payload = [];
        $lineas = [];
        if ($this->conectarBiometrico()) {
            $attendances = array_reverse($this->zklib->getAttendance());
            $serialSub = substr($this->zklib->serialNumber(), 14);
            $serial = substr($serialSub, 0, -1);
            $ip = config('zkteco.ip');
            $this->zklib->disconnect();
            $cantidadsincronizados = $this->sincronizarLocalNube($desde, $hasta);
            if (!empty($attendances)) {
                foreach ($attendances as $attItem) {
                    $ts = Carbon::parse($attItem['timestamp']);
                    if ($ts->betweenIncluded($desde, $hasta)) {
                        $numeroDocumento = str_pad($attItem['id'], 8, '0', STR_PAD_LEFT);
                        $marcacion = Marcacion::firstOrCreate([
                            'numero_documento' => $numeroDocumento,
                            'fecha'            => $ts->toDateTimeString()
                        ], [
                            'uid'    => $attItem['uid'],
                            'estado' => $attItem['state'],
                            'tipo'   => $attItem['type'] ?? 1,
                            'serial' => $serial,
                            'ip'     => $ip
                        ]);
                        if ($marcacion->wasRecentlyCreated) {
                            $dt = Carbon::parse($marcacion->fecha);
                            $lineas[] = $this->formatearLineaAttlog(
                                $attItem['id'],
                                $dt->format('Y-m-d H:i:s'),
                                $marcacion->tipo,
                                $marcacion->estado
                            );
                            $payload[] = [
                                'dni'     => $numeroDocumento,
                                'uid'     => $marcacion->uid,
                                'estado'  => $marcacion->estado,
                                'fecha'   => $marcacion->fecha,
                                'tipo'    => $marcacion->tipo,
                                'serial'  => $marcacion->serial,
                                'ip'      => $marcacion->ip,
                            ];
                            $i++;
                        }
                    }
                }
                if (!empty($lineas)) {
                    $nombre = "{$serial}_attlog_" . now()->format('Ymd_His') . ".dat";
                    $contenido = implode("\r\n", $lineas) . "\r\n";
                    Storage::disk('marcaciones')->put($nombre, $contenido);
                }
                Log::info('Marcaciones procesadas', [
                    'total_leidas'     => count($attendances),
                    'nuevas_insertadas'=> count($lineas),
                    'archivo'          => $nombre ?? 'no generado'
                ]);
                if (!empty($payload)) {
                    $ruta = config('app.api_url') . '/api/guardar-marcaciones-lote';
                    try {
                        $response = Http::timeout(120)->post($ruta, [
                            'marcaciones' => $payload
                        ]);

                        if (!$response->successful()) {
                            Log::error('Error al enviar marcaciones', ['response' => $response->body()]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('Excepción al enviar marcaciones: ' . $e->getMessage());
                    }
                }
            }
            return $i;
        }
        return -1;
    }
    public function deleteAttendances(){
        $res = $this->conectarBiometrico();
        $registros = 0;
        if($res)
        {
            $registros = count($this->zklib->getAttendance());
            $this->zklib->clearAttendance();
            $this->zklib->disconnect();
        }else{
            return -1;
        }
        return $registros;
    }
    public function saveAttendancesByAsc($desde, $hasta){
        if($this->tipoestablecimiento=='master'){
            return $this->saveAttendancesMaster($desde, $hasta);
        }else{
            return $this->saveAttendancesOtros($desde, $hasta);
        }
    }
    public function saveAttendancesOtros($desde, $hasta){
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        // Fechas: parse una sola vez
        $desde = Carbon::parse($desde)->startOfDay();
        $hasta = Carbon::parse($hasta)->endOfDay();

        $i = 0;
        $payload = [];
        $lineas  = [];

        if ($this->conectarBiometrico()) {

            $attendances = array_reverse($this->zklib->getAttendance());

            // Serial de respaldo (si tu equipo no devuelve serialNumber)
            $serialSub = 'SERIAL GENERAL EN CASO NO FUNC';
            // Si más adelante activas serialNumber(), solo reemplaza la línea anterior por:
            // $serialSub = substr($this->zklib->serialNumber(), 14);
            // y mantén este rtrim para limpiar:
            $serial = rtrim(substr($serialSub, 0, -1));

            $ip = config('zkteco.ip');
            $this->zklib->disconnect();
            $cantidadsincronizados = $this->sincronizarLocalNube($desde, $hasta);
            if (!empty($attendances)) {
                foreach ($attendances as $attItem) {
                    // Mapeo de índices: [0]=uid, [1]=dni, [2]=tipo, [3]=timestamp, [4]=estado
                    $ts = Carbon::parse($attItem[3]);
                    if ($ts->betweenIncluded($desde, $hasta)) {
                        $numeroDocumento = str_pad($attItem[1], 8, '0', STR_PAD_LEFT);
                        $marcacion = Marcacion::firstOrCreate([
                            'numero_documento' => $numeroDocumento,
                            'fecha'            => $ts->toDateTimeString(),
                        ], [
                            'uid'    => $attItem[0],
                            'estado' => $attItem[4],
                            'tipo'   => $attItem[2] ?? 1,
                            'serial' => $serial,
                            'ip'     => $ip,
                        ]);
                        if ($marcacion->wasRecentlyCreated) {
                            $dt = Carbon::parse($marcacion->fecha);
                            $lineas[] = $this->formatearLineaAttlog(
                                $attItem[1],
                                $dt->format('Y-m-d H:i:s'),
                                $marcacion->tipo,
                                $marcacion->estado
                            );
                            $payload[] = [
                                'dni'    => $numeroDocumento,
                                'uid'    => $marcacion->uid,
                                'estado' => $marcacion->estado,
                                'fecha'  => $marcacion->fecha,
                                'tipo'   => $marcacion->tipo,
                                'serial' => $marcacion->serial,
                                'ip'     => $marcacion->ip,
                            ];
                            $i++;
                        }
                    }
                }
                // Guardar .dat solo si hay nuevas líneas
                if (!empty($lineas)) {
                    $nombre = "{$serial}_attlog_" . now()->format('Ymd_His') . ".dat";
                    $contenido = implode("\r\n", $lineas) . "\r\n";
                    Storage::disk('marcaciones')->put($nombre, $contenido);
                }
                Log::info('Otros - Marcaciones procesadas', [
                    'total_leidas'      => count($attendances),
                    'nuevas_insertadas' => count($lineas),
                    'archivo'           => $nombre ?? 'no generado'
                ]);
                // Envío remoto (si hubo nuevas)
                if (!empty($payload)) {
                    $ruta = config('app.api_url') . '/api/guardar-marcaciones-lote';
                    try {
                        $response = Http::timeout(120)->post($ruta, ['marcaciones' => $payload]);
                        if (!$response->successful()) {
                            Log::error('Otros - Error al enviar marcaciones', ['response' => $response->body()]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('Otros - Excepción al enviar marcaciones: ' . $e->getMessage());
                    }
                }
            }
            return $i;
        }
        return -1;
    }
    public function getAllAttendacesApi()
    {
        try {
            $ruta = config('app.api_url') . '/api/attendances';
            $response = Http::timeout(5)->get($ruta);
            if ($response->successful()) {
                return $response->json();
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
    public function getVerificarDniPersonalApp(string $dni){
        try {
            $ruta = config('app.api_url').'/api/attendances/verificar-dni';
            $response = Http::get($ruta,[
                'dni' => $dni
            ]);

            //return $ruta;
            // Verificar si la respuesta tiene un código 200 (éxito)
            if ($response->getStatusCode() == 200) {

               return $response->json();
                 //json_decode($response->getBody(), true);
                // Aquí puedes trabajar con los datos de respuesta
            } else {
                // Manejar el caso en que la respuesta no sea un código 200
            }
        } catch (\Exception $e) {
            // Manejar errores de excepción, como problemas de conexión
        }
    }
    public function sincronizarLocalNube($desde, $hasta){
        $marcaciones = Marcacion::whereBetween('fecha', ["$desde 00:00:00", "$hasta 23:59:59"])
            ->whereNotNull('numero_documento')
            ->get(['numero_documento','uid','estado','fecha','tipo','serial','ip']);
        $rutaLote = config('app.api_url').'/api/guardar-marcaciones-lote';
        $insertadasTotal = 0;
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
        return $insertadasTotal;
    }

    /**
     * Formatea una línea exactamente igual al archivo nativo {SERIAL}_attlog.dat de ZKTeco:
     * " 43584972\t2026-05-29 12:30:56\t1\t255\t1\t0"
     */
    public function formatearLineaAttlog($idUsuario, string $fechaHora, $colEstado = 255, $colVerificacion = 1): string
    {
        $idLimpio = trim((string) $idUsuario);
        $idPadded = str_pad($idLimpio, 9, ' ', STR_PAD_LEFT);
        $col4 = ($colEstado === null || $colEstado === '') ? 255 : $colEstado;
        $col5 = ($colVerificacion === null || $colVerificacion === '') ? 1 : $colVerificacion;

        return "{$idPadded}\t{$fechaHora}\t1\t{$col4}\t{$col5}\t0";
    }

    /**
     * Obtiene el número de serie limpio del dispositivo biométrico conectado.
     */
    public function obtenerSerialLimpio(): string
    {
        try {
            if ($this->tipoestablecimiento === 'master') {
                $raw = (string) $this->zklib->serialNumber();
            } else {
                $raw = (string) $this->zklib->getSerialNumber(false);
            }
            $serial = str_replace('~SerialNumber=', '', $raw);
            $serial = trim($serial, "\0 \t\n\r\x0B");
            return $serial !== '' ? $serial : 'BIOMETRICO';
        } catch (\Throwable $e) {
            return 'BIOMETRICO';
        }
    }

    /**
     * Modifica el userid (DNI) y/o el nombre de un usuario en el biométrico
     * conservando su UID interno, rol, contraseña, tarjeta y plantillas biométricas (huellas/rostro).
     */
    public function actualizarUsuarioBiometrico(
        string $idActual,
        string $idNuevo,
        ?string $nuevoNombre = null,
        bool $actualizarBdLocal = false,
        ?int $nuevoRol = null
    ): array {
        if (!$this->conectarBiometrico()) {
            return [
                'ok' => 0,
                'conectado' => false,
                'status' => 503,
                'mensaje' => 'No hay conexión con el dispositivo biométrico (' . config('zkteco.ip') . ':' . config('zkteco.port', 4370) . ').',
            ];
        }

        try {
            $usuarios = $this->zklib->getUser();
            if (!is_array($usuarios)) {
                $this->zklib->disconnect();
                return [
                    'ok' => 0,
                    'conectado' => true,
                    'status' => 500,
                    'mensaje' => 'No fue posible leer la lista de usuarios del dispositivo biométrico.',
                ];
            }

            $uidEncontrado = null;
            $usuarioOriginal = null;

            foreach ($usuarios as $key => $u) {
                $userId = $this->tipoestablecimiento === 'master'
                    ? ($u['userid'] ?? null)
                    : ($u[0] ?? null);

                if ((string) trim((string) $userId) === (string) trim($idActual)) {
                    $uidEncontrado = $this->tipoestablecimiento === 'master'
                        ? (int) ($u['uid'] ?? $key)
                        : (int) $key;
                    $usuarioOriginal = $u;
                    break;
                }
            }

            if (!$usuarioOriginal || !$uidEncontrado) {
                $this->zklib->disconnect();
                return [
                    'ok' => 0,
                    'conectado' => true,
                    'status' => 404,
                    'mensaje' => "El usuario con ID '{$idActual}' no existe en el biométrico.",
                ];
            }

            // Si está cambiando el ID (userid), verificar que el nuevo ID no esté ocupado por otro UID
            if ((string) trim($idNuevo) !== (string) trim($idActual)) {
                foreach ($usuarios as $key => $u) {
                    $userId = $this->tipoestablecimiento === 'master'
                        ? ($u['userid'] ?? null)
                        : ($u[0] ?? null);
                    $uidIter = $this->tipoestablecimiento === 'master'
                        ? (int) ($u['uid'] ?? $key)
                        : (int) $key;

                    if ((string) trim((string) $userId) === (string) trim($idNuevo) && $uidIter !== $uidEncontrado) {
                        $this->zklib->disconnect();
                        return [
                            'ok' => 0,
                            'conectado' => true,
                            'status' => 409,
                            'mensaje' => "El nuevo ID '{$idNuevo}' ya está ocupado por otro usuario en el biométrico (UID: {$uidIter}).",
                        ];
                    }
                }
            }

            if ($this->tipoestablecimiento === 'master') {
                $nombreActual = $usuarioOriginal['name'] ?? '';
                $rolActual = (int) ($usuarioOriginal['role'] ?? 0);
                $passwordActual = (string) ($usuarioOriginal['password'] ?? '');
                $cardnoActual = (int) ($usuarioOriginal['cardno'] ?? 0);
            } else {
                $nombreActual = $usuarioOriginal[1] ?? '';
                $rolActual = (int) ($usuarioOriginal[2] ?? 0);
                $passwordActual = (string) ($usuarioOriginal[3] ?? '');
                $cardnoActual = 0;
            }

            $nombreFinal = ($nuevoNombre !== null && trim($nuevoNombre) !== '')
                ? trim($nuevoNombre)
                : $nombreActual;
            $rolFinal = $nuevoRol !== null ? $nuevoRol : $rolActual;

            // Ejecutar actualización in-place sobre el mismo UID (CMD_SET_USER = 8)
            // Esto conserva intactas las huellas y rostro asociados al UID en la memoria del reloj.
            if ($this->tipoestablecimiento === 'master') {
                $byte1 = chr((int) ($uidEncontrado % 256));
                $byte2 = chr((int) ($uidEncontrado >> 8));
                $cardBytes = pack('V', $cardnoActual);

                $command_string = implode('', [
                    $byte1,
                    $byte2,
                    chr($rolFinal),
                    str_pad(substr($passwordActual, 0, 8), 8, chr(0)),
                    str_pad(substr(utf8_decode($nombreFinal), 0, 24), 24, chr(0)),
                    substr($cardBytes, 0, 4),
                    str_pad(chr(1), 9, chr(0)),
                    str_pad(substr(trim($idNuevo), 0, 9), 9, chr(0)),
                    str_repeat(chr(0), 15),
                ]);

                $resultadoSet = $this->zklib->_command(\Rats\Zkteco\Lib\Helper\Util::CMD_SET_USER, $command_string);
            } else {
                $resultadoSet = $this->zklib->setUser(
                    $uidEncontrado,
                    substr(trim($idNuevo), 0, 8),
                    substr(utf8_decode($nombreFinal), 0, 28),
                    $passwordActual,
                    $rolFinal
                );
            }

            $this->zklib->disconnect();

            if ($resultadoSet === false) {
                return [
                    'ok' => 0,
                    'conectado' => true,
                    'status' => 500,
                    'mensaje' => 'El biométrico rechazó el comando de actualización del usuario.',
                ];
            }

            $registrosLocalesActualizados = 0;
            if ($actualizarBdLocal && (string) trim($idNuevo) !== (string) trim($idActual)) {
                $docAnteriorPadded = str_pad(trim($idActual), 8, '0', STR_PAD_LEFT);
                $docNuevoPadded = str_pad(trim($idNuevo), 8, '0', STR_PAD_LEFT);

                $registrosLocalesActualizados = Marcacion::whereIn('numero_documento', [trim($idActual), $docAnteriorPadded])
                    ->update(['numero_documento' => $docNuevoPadded]);
            }

            return [
                'ok' => 1,
                'conectado' => true,
                'status' => 200,
                'mensaje' => 'Usuario actualizado correctamente en el dispositivo biométrico.',
                'datos' => [
                    'uid' => $uidEncontrado,
                    'id_anterior' => $idActual,
                    'id_nuevo' => $idNuevo,
                    'nombre_anterior' => $nombreActual,
                    'nombre_nuevo' => $nombreFinal,
                    'rol' => $rolFinal,
                    'marcaciones_locales_actualizadas' => $registrosLocalesActualizados,
                ],
            ];
        } catch (\Throwable $e) {
            try {
                $this->zklib->disconnect();
            } catch (\Throwable $inner) {
            }

            Log::error('Error en actualizarUsuarioBiometrico', [
                'id_actual' => $idActual,
                'id_nuevo' => $idNuevo,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => 0,
                'conectado' => true,
                'status' => 500,
                'mensaje' => 'Error al actualizar el usuario: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Genera el contenido y nombre de archivo {SERIAL}_attlog.dat en el formato exacto del biométrico.
     * $origen: 'biometrico' (directo del reloj físico) | 'local' (desde la BD local marcaciones)
     */
    public function exportarAttlogDat(
        string $origen = 'biometrico',
        ?string $desde = null,
        ?string $hasta = null,
        ?string $serialPersonalizado = null
    ): array {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        $fechaDesde = !empty($desde) ? Carbon::parse($desde)->startOfDay() : null;
        $fechaHasta = !empty($hasta) ? Carbon::parse($hasta)->endOfDay() : null;
        $lineas = [];

        if ($origen === 'biometrico') {
            if (!$this->conectarBiometrico()) {
                return [
                    'ok' => 0,
                    'conectado' => false,
                    'status' => 503,
                    'mensaje' => 'No hay conexión con el dispositivo biométrico (' . config('zkteco.ip') . ':' . config('zkteco.port', 4370) . '). Puede exportar usando el origen "Base de Datos Local".',
                ];
            }

            $serialDetectado = $this->obtenerSerialLimpio();
            $attendances = $this->zklib->getAttendance();
            $this->zklib->disconnect();

            $serial = !empty($serialPersonalizado) ? trim($serialPersonalizado) : $serialDetectado;

            if (is_array($attendances)) {
                foreach ($attendances as $att) {
                    if ($this->tipoestablecimiento === 'master') {
                        $idUser = $att['id'] ?? '';
                        $tsStr = $att['timestamp'] ?? '';
                        $estadoVerif = $att['state'] ?? 1;
                        $tipoCheck = $att['type'] ?? 255;
                    } else {
                        $idUser = $att[1] ?? '';
                        $tsStr = $att[3] ?? '';
                        $estadoVerif = $att[2] ?? 1;
                        $tipoCheck = $att[4] ?? 255;
                    }

                    if (empty($tsStr)) {
                        continue;
                    }

                    $ts = Carbon::parse($tsStr);
                    if ($fechaDesde && $ts->lt($fechaDesde)) {
                        continue;
                    }
                    if ($fechaHasta && $ts->gt($fechaHasta)) {
                        continue;
                    }

                    $lineas[] = $this->formatearLineaAttlog(
                        $idUser,
                        $ts->format('Y-m-d H:i:s'),
                        $tipoCheck,
                        $estadoVerif
                    );
                }
            }
        } else {
            // Origen: Base de datos local (tabla marcaciones)
            $query = Marcacion::query()
                ->whereNotNull('numero_documento')
                ->orderBy('fecha', 'asc');

            if ($fechaDesde) {
                $query->where('fecha', '>=', $fechaDesde->toDateTimeString());
            }
            if ($fechaHasta) {
                $query->where('fecha', '<=', $fechaHasta->toDateTimeString());
            }

            $registros = $query->toBase()->get(['numero_documento', 'fecha', 'estado', 'tipo', 'serial']);

            $serialPrimeraFila = $registros->first()->serial ?? null;
            $serial = !empty($serialPersonalizado)
                ? trim($serialPersonalizado)
                : (!empty($serialPrimeraFila) ? trim($serialPrimeraFila) : 'BIOMETRICO');

            foreach ($registros as $m) {
                $col4 = 255;
                $col5 = $m->estado ?? 1;

                $lineas[] = $this->formatearLineaAttlog(
                    $m->numero_documento,
                    substr((string) $m->fecha, 0, 19),
                    $col4,
                    $col5
                );
            }
        }

        $contenido = !empty($lineas) ? implode("\r\n", $lineas) . "\r\n" : '';
        $filename = "{$serial}_attlog.dat";

        // Guardar también respaldo en el disco marcaciones si tiene datos
        if (!empty($lineas)) {
            try {
                Storage::disk('marcaciones')->put($filename, $contenido);
            } catch (\Throwable $e) {
                Log::warning('No se pudo guardar copia en disco marcaciones: ' . $e->getMessage());
            }
        }

        return [
            'ok' => 1,
            'conectado' => true,
            'status' => 200,
            'filename' => $filename,
            'serial' => $serial,
            'total' => count($lineas),
            'contenido' => $contenido,
        ];
    }
}