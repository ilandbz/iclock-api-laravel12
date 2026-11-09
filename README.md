# ICLOCK API — Cliente de Integración y Sincronización Biométrica ZKTeco

Aplicación web y API construida en **Laravel 13 (PHP 8.3+)** para la comunicación directa con relojes biométricos **ZKTeco**, almacenamiento local de marcaciones (`biometrico_local`), sincronización por lotes hacia el servidor central de asistencia en la nube y herramientas de administración del dispositivo.

---

## Características Principales

1. **Conexión Rápida con Control de Timeout (Fail-Fast 2s):**
   - Verificación de disponibilidad del reloj biométrico en **2 segundos** (`SO_RCVTIMEO` / `SO_SNDTIMEO`), evitando bloqueos de 60 segundos cuando el dispositivo está apagado o fuera de red.
   - Ampliación automática del timeout a **15 segundos** una vez establecida la sesión para permitir la descarga fluida de miles de marcaciones o usuarios.
2. **Doble Motor de Comunicación ZKTeco:**
   - **Modo Master (`ESTABLECIMIENTO_MASTER=true`):** Utiliza `Rats\Zkteco\Lib\ZKTeco` (UDP).
   - **Modo Otros (`ESTABLECIMIENTO_MASTER=false`):** Utiliza la librería propia `App\ZKService\ZKLibrary` con soporte `TCP` y `UDP`.
3. **Descarga y Sincronización a la Nube:**
   - Descarga marcaciones del reloj biométrico por rango de fechas, las almacena en la base de datos MySQL local (`marcaciones`), genera respaldo `.dat` en `storage/app/marcaciones` y las sincroniza en lotes de 1,000 registros hacia el servidor central (`/api/guardar-marcaciones-lote`).
4. **Módulo de Modificación de Usuarios en el Biométrico (`userid` y `Nombre`):**
   - Permite actualizar el **DNI / ID (`userid`)**, **Nombre** y **Rol** de un usuario directamente sobre su `UID` interno mediante el comando `CMD_SET_USER (8)`, **conservando intactas sus huellas dactilares y plantillas faciales**.
   - Protegido mediante **Clave de Acceso Global** (`BIOMETRIC_ADMIN_KEY`).
   - Opción para actualizar simultáneamente el DNI anterior por el nuevo en el historial local de marcaciones.
5. **Exportación Nativa a `{SERIAL}_attlog.dat`:**
   - Genera y descarga archivos `.dat` con el formato exacto de tabulaciones que exportan los equipos ZKTeco por USB (`" 43584972\t2026-05-29 12:30:56\t1\t255\t1\t0\r\n"`).
   - Permite exportar tanto **en vivo desde el biométrico físico** como **desde la base de datos local** (ideal cuando el reloj está desconectado).

---

## Requisitos del Sistema

- **PHP:** `>= 8.3` con la extensión **`ext-sockets`** habilitada en `php.ini`.
- **Base de Datos:** MySQL / MariaDB (`biometrico_local`).
- **Servidor Local:** Laravel Herd / Valet / Apache / Nginx.
- **Conectividad:** Acceso en red local (LAN/VPN) al puerto `4370` (UDP/TCP) del reloj biométrico ZKTeco.

---

## Configuración (`.env`)

Variables principales de configuración biométrica y sincronización en `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=biometrico_local
DB_USERNAME=root
DB_PASSWORD=tu_password

# Configuración del Reloj Biométrico ZKTeco
BIOMETRIC_IP=192.168.2.253
BIOMETRIC_PORT=4370
BIOMETRIC_PROTOCOL=TCP
ESTABLECIMIENTO_MASTER=true
BIOMETRIC_TIMEOUT=2

# Clave global para autorizar la modificación de usuarios del biométrico
BIOMETRIC_ADMIN_KEY=admin2026

# Servidor Central receptor de marcaciones
ASISTENCIA_API_URL=https://asistencia.macrocompany.net.pe
```

---

## Rutas y Endpoints Principales

### Vistas y Operaciones Principales (`routes/web.php`)
| Método | Ruta | Descripción |
| :--- | :--- | :--- |
| `GET` | `/` | Formulario principal para guardar marcaciones del biométrico y sincronizarlas a la nube. |
| `POST` | `/guardar-marcaciones` | Descarga marcaciones del biométrico en el rango `desde` - `hasta`, guarda en BD local y envía a la nube. |
| `POST` | `/sincronizar-marcaciones` | Reenvía en lotes de 1,000 las marcaciones de la BD local hacia `ASISTENCIA_API_URL`. |
| `POST` | `/eliminar-datos-biometrico` | Limpia la memoria de marcaciones del dispositivo biométrico físico. |

### Panel y Endpoints del Biométrico (`routes/api.php` — prefijo `/api/biometrico`)
> Todos los endpoints `GET` detectan automáticamente si se abren desde el navegador (renderizando la interfaz visual en `resources/views/info.blade.php`) o si se solicitan como JSON (`Accept: application/json` o `?json=1`).

| Método | Ruta | Nombre de Ruta | Descripción |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/biometrico/version` | `version` | Información técnica del dispositivo (versión de firmware, serie, MAC, hora actual, IP/puerto). |
| `GET` | `/api/biometrico/users` | `biometrico.users` | Listado de usuarios registrados en el biométrico con buscador y acceso directo a edición. |
| `GET` | `/api/biometrico/marcaciones` | `biometrico.marcaciones` | Listado de marcaciones almacenadas actualmente en la memoria del reloj. |
| `GET` | `/api/biometrico/herramientas` | `biometrico.herramientas` | Módulo para modificar `userid`/nombre de usuarios y exportar archivos `_attlog.dat`. |
| `POST` | `/api/biometrico/usuarios/actualizar` | `biometrico.usuarios.actualizar` | Actualiza `userid`, `nombre` y `rol` de un usuario en el biométrico (requiere `clave_acceso`). |
| `GET` | `/api/biometrico/exportar-attlog` | `biometrico.exportar-attlog` | Descarga el archivo `{SERIAL}_attlog.dat` desde el biométrico (`origen=biometrico`) o BD local (`origen=local`). |

---

## Formato del Archivo `{SERIAL}_attlog.dat`

Cada línea generada por el exportador respeta la estructura nativa de 6 columnas separadas por tabulación (`\t`) y salto de línea CRLF (`\r\n`):

```text
 43584972	2026-05-29 12:30:56	1	255	1	0
```

1. **Columna 1:** `userid` (DNI) alineado a la derecha a 9 caracteres con espacios iniciales (`str_pad($id, 9, ' ', STR_PAD_LEFT)`).
2. **Columna 2:** Fecha y hora en formato `YYYY-MM-DD HH:MM:SS`.
3. **Columna 3:** ID de dispositivo (`1`).
4. **Columna 4:** Estado de marcación (`255`).
5. **Columna 5:** Método de verificación (`1` = Huella, `3` = Contraseña, `15` = Rostro).
6. **Columna 6:** WorkCode (`0`).
