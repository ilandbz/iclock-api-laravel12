# CLAUDE.md — Guía de Desarrollo y Arquitectura del Proyecto (`iclock-api`)

Este archivo proporciona el contexto arquitectónico, convenciones y restricciones técnicas del repositorio `iclock-api` para Claude Code y otros asistentes de programación.

---

## Resumen del Sistema

`iclock-api` es una pasarela local y panel de administración para relojes biométricos **ZKTeco** desarrollada con **Laravel 13** y **PHP 8.3+**. Se ejecuta en el equipo cliente conectado a la misma red local que el reloj biométrico y cumple tres funciones principales:
1. **Extracción y almacenamiento local:** Conecta al reloj por sockets UDP/TCP (puerto `4370`), descarga asistencias y las guarda en MySQL (`biometrico_local.marcaciones`) y en archivos `.dat` dentro de `storage/app/marcaciones`.
2. **Sincronización con la nube:** Envía las marcaciones locales en lotes de 1,000 registros vía HTTP POST hacia `${ASISTENCIA_API_URL}/api/guardar-marcaciones-lote`.
3. **Administración del biométrico:** Consulta información del dispositivo, lista usuarios y marcaciones en tiempo real, modifica el `userid` (DNI) y `nombre` de usuarios conservando sus huellas dactilares, y exporta archivos `{SERIAL}_attlog.dat` idénticos a los generados por USB en el reloj físico.

---

## Comandos Frecuentes

```powershell
# Limpiar caché de Laravel tras cambios en .env, rutas o vistas
php artisan optimize:clear

# Inspeccionar rutas registradas del biométrico
php artisan route:list --path=biometrico

# Validar sintaxis de archivos modificados
php -l app/Http/Traits/MarcacionTrait.php
php -l app/Http/Controllers/Api/ZKLibraryController.php
```

---

## Arquitectura y Flujo de Datos

### 1. Inicialización y Conexión Dual (`app/Http/Traits/MarcacionTrait.php`)
El modelo `App\Models\Marcacion` utiliza el trait `MarcacionTrait`. Según la variable `ESTABLECIMIENTO_MASTER` (`config('zkteco.establecimiento_master')`):
- **`true` (Modo Master):** Instancia `Rats\Zkteco\Lib\ZKTeco` (comunicación UDP).
- **`false` (Modo Otros):** Instancia `App\ZKService\ZKLibrary` (comunicación TCP o UDP según `BIOMETRIC_PROTOCOL`).

### 2. Prevención de Bloqueos cuando no hay Conexión
- **Nunca llamar a `$this->zklib->connect()` directamente.** Usar siempre `$this->conectarBiometrico()`.
- `conectarBiometrico()` establece `SO_RCVTIMEO` y `SO_SNDTIMEO` en `config('zkteco.timeout', 2)` segundos antes de iniciar el handshake. Si el reloj no responde, aborta en **2 segundos** y los controladores devuelven HTTP `503` (o renderizan el estado *"Sin Conexión"* en `resources/views/info.blade.php`).
- Si la conexión es exitosa, `conectarBiometrico()` incrementa el timeout del socket a **15 segundos** para permitir la transferencia completa de paquetes grandes (`getAttendance()`, `getUser()`).

### 3. Actualización Segura de Usuarios en el Reloj (`actualizarUsuarioBiometrico`)
- Los relojes ZKTeco asocian las huellas dactilares y rostros al `uid` interno (2 bytes), no al `userid` (DNI).
- Para cambiar el `userid` o `name` de un usuario **sin perder sus huellas**, se envía el comando `CMD_SET_USER (8)` sobrescribiendo el mismo `uid` con la estructura binaria de 72 bytes:
  - `2 bytes` UID (little-endian)
  - `1 byte` Role (`0` = Usuario, `14` = Admin)
  - `8 bytes` Password (null-padded)
  - `24 bytes` Name (null-padded, `utf8_decode`)
  - `4 bytes` Card Number (`pack('V', $cardno)`)
  - `9 bytes` Group/Timezone (`str_pad(chr(1), 9, chr(0))`)
  - `9 bytes` UserID / DNI (null-padded)
  - `15 bytes` Padding (`chr(0)`)
- **Importante:** No usar `Rats\Zkteco\Lib\Helper\User::set()` directamente porque contiene un defecto con `hex2bin(dechex(0))` en PHP 8+.
- El endpoint `POST /api/biometrico/usuarios/actualizar` exige el campo `clave_acceso`, el cual se compara con `config('zkteco.admin_key')` (`BIOMETRIC_ADMIN_KEY` en `.env`).

### 4. Exportación `{SERIAL}_attlog.dat` (`exportarAttlogDat`)
- Soporta dos orígenes:
  - `origen=biometrico`: Lee en vivo las marcaciones y el número de serie del reloj físico.
  - `origen=local`: Genera el archivo a partir de la tabla local `marcaciones` (útil cuando el reloj no está conectado).
- Formato por línea (`formatearLineaAttlog`):
  ```text
   43584972\t2026-05-29 12:30:56\t1\t255\t1\t0\r\n
  ```
  Donde el DNI se alinea a la derecha a 9 caracteres con espacios (`str_pad($id, 9, ' ', STR_PAD_LEFT)`).

