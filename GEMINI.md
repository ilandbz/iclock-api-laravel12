# GEMINI.md — Guía de Contexto y Arquitectura para Google Gemini / Antigravity

Este documento define la arquitectura, reglas de negocio y detalles de bajo nivel del proyecto **ICLOCK API** para asistir en futuras tareas de desarrollo y mantenimiento.

---

## 1. Propósito del Proyecto

`iclock-api` es una aplicación cliente desarrollada en **Laravel 13 (PHP 8.3+)** que corre localmente en las sedes donde se encuentran los relojes biométricos **ZKTeco**. Su objetivo es:
1. Extraer marcaciones del reloj biométrico físico por red local (`UDP` o `TCP`, puerto `4370`).
2. Guardar las marcaciones en una base de datos MySQL local (`biometrico_local`, tabla `marcaciones`) y respaldar archivos `.dat` en `storage/app/marcaciones`.
3. Sincronizar las marcaciones hacia el servidor central en la nube (`ASISTENCIA_API_URL/api/guardar-marcaciones-lote`).
4. Ofrecer una interfaz web rápida para consultar versión/estado del equipo, listar usuarios, listar marcaciones en memoria, modificar el `userid` (DNI) o nombre de un usuario sin perder sus huellas, y exportar archivos `{SERIAL}_attlog.dat`.

---

## 2. Mapa de Archivos Clave

- **`config/zkteco.php`**: Configuración central del biométrico (`ip`, `port`, `protocol`, `establecimiento_master`, `timeout`, `admin_key`).
- **`app/Models/Marcacion.php`**: Modelo Eloquent de la tabla `marcaciones` que consume `MarcacionTrait`.
- **`app/Http/Traits/MarcacionTrait.php`**: Núcleo de la lógica de negocio y comunicación con el reloj biométrico y sincronización a la nube.
- **`app/Http/Controllers/Api/ZKLibraryController.php`**: Controlador principal que atiende tanto las rutas web como los endpoints bajo `/api/biometrico/*`.
- **`app/ZKService/ZKLibrary.php`**: Librería propia para comunicación ZKTeco (usada cuando `ESTABLECIMIENTO_MASTER=false`).
- **`vendor/rats/zkteco/src/Lib/ZKTeco.php`**: Librería de terceros usada cuando `ESTABLECIMIENTO_MASTER=true`.
- **`resources/views/layouts/app.blade.php`**: Layout principal con Bootstrap 5.3, FontAwesome 6.5, SweetAlert2 y selector de tema claro/oscuro.
- **`resources/views/info.blade.php`**: Vista unificada para las pestañas `Versión / Info`, `Usuarios`, `Marcaciones` y `Editar Usuario / Exportar .DAT`, además de la pantalla de diagnóstico rápido cuando no hay conexión.
- **`resources/views/welcome.blade.php`**: Vista de inicio para guardar y sincronizar marcaciones por rango de fechas.
- **`resources/views/eliminardata.blade.php`**: Vista para limpiar las marcaciones almacenadas en el reloj biométrico.

---

## 3. Reglas Técnicas Críticas (No Romper)

### A. Manejo de Timeouts de Socket (`conectarBiometrico`)
- **Problema histórico:** `Rats\Zkteco\Lib\ZKTeco` configura por defecto un `SO_RCVTIMEO` de **60.5 segundos** en su constructor, y `ZKLibrary` ejecutaba `socket_connect` bloqueante en el constructor cuando usaba TCP.
- **Solución implementada:** Siempre conectar a través de `$this->conectarBiometrico()` en `MarcacionTrait.php`.
  - Durante el handshake (`connect()`), el timeout (`SO_RCVTIMEO` y `SO_SNDTIMEO`) se fija en **`config('zkteco.timeout', 2)` segundos**. Si el biométrico está apagado o desconectado, falla en **2 segundos** devolviendo HTTP `503`.
  - Una vez que `connect()` devuelve `true`, el timeout se amplía automáticamente a **15 segundos** (`$this->configurarSocketTimeout(15, 0)`) para evitar cortes durante la descarga de miles de registros en `getAttendance()` o `getUser()`.

### B. Modificación de Usuarios en ZKTeco (`actualizarUsuarioBiometrico`)
- En el protocolo ZKTeco, las plantillas de huella dactilar y rostro están vinculadas al **`uid` interno** (entero de 2 bytes `1..65535`), no al `userid` (DNI).
- **NUNCA** usar `removeUser()` / `deleteUser()` seguido de `setUser()` para cambiar el DNI o nombre, ya que eso borraría las huellas del usuario.
- **Siempre** sobrescribir in-place el registro enviando `CMD_SET_USER` (`8`) con el **mismo `uid`**, conservando su `password`, `role` y `cardno` originales.
- En modo `master`, construir directamente el paquete binario de 72 bytes en `MarcacionTrait::actualizarUsuarioBiometrico()` usando `pack('V', $cardnoActual)` para los 4 bytes de tarjeta. **No llamar a `Rats\Zkteco\Lib\Helper\User::set()`** porque tiene un bug en PHP 8+ cuando `$cardno == 0` (`hex2bin(Util::reverseHex(dechex(0)))` lanza excepción por longitud hexadecimal impar).
- Toda modificación de usuario requiere validar `clave_acceso` contra `config('zkteco.admin_key')` usando `hash_equals`.

### C. Formato Estricto de Exportación `{SERIAL}_attlog.dat`
- El método `MarcacionTrait::formatearLineaAttlog()` genera cada fila con el formato idéntico al archivo USB nativo de ZKTeco:
  - Columna 1: `userid` relleno con espacios a la izquierda hasta 9 caracteres (`str_pad($id, 9, ' ', STR_PAD_LEFT)`).
  - Separador: Tabulación (`\t`).
  - Columna 2: Fecha y hora `Y-m-d H:i:s`.
  - Columna 3: `1`.
  - Columna 4: `255` (o `type` del registro).
  - Columna 5: Modo de verificación (`state`: `1` = huella, `3` = clave, `15` = rostro).
  - Columna 6: `0`.
  - Fin de línea: `\r\n`.

---

## 4. Comandos Útiles de Verificación

```powershell
# Verificar sintaxis PHP
php -l app/Http/Traits/MarcacionTrait.php
php -l app/Http/Controllers/Api/ZKLibraryController.php

# Limpiar cachés de configuración, rutas y vistas
php artisan optimize:clear

# Listar rutas del módulo biométrico
php artisan route:list --path=biometrico
```

