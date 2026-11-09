<?php

use App\Http\Controllers\Api\ZKLibraryController;
use App\ZKService\ZKLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return app()->version();
});

Route::group(['prefix' => 'app'],function() {
    Route::get('metodos',[ZKLibraryController::class,'obtenerMetodos']);
});



Route::group(['prefix' => 'biometrico'],function() {
    Route::get('users',[ZKLibraryController::class,'getUsers'])->name('biometrico.users');
    Route::get('marcaciones',[ZKLibraryController::class,'getAttendances'])->name('biometrico.marcaciones');
    Route::get('version',[ZKLibraryController::class,'getversion'])->name('version');
    Route::get('api-all',[ZKLibraryController::class,'obtenerMarcacionesApi'])->name('biometrico.api-all');
    Route::get('herramientas',[ZKLibraryController::class,'herramientas'])->name('biometrico.herramientas');
    Route::post('usuarios/actualizar',[ZKLibraryController::class,'cambiarIdUsuario'])->name('biometrico.usuarios.actualizar');
    Route::get('exportar-attlog',[ZKLibraryController::class,'exportarAttlogDat'])->name('biometrico.exportar-attlog');
});
