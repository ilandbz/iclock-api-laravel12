<?php

namespace App\Console\Commands;

use App\Models\Marcacion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AttendanceExport extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exportar Marcaciones del reloj';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // set_time_limit(0);
        // ini_set('memory_limit', '2048M');
        // $this->info('Iniciando exportación de marcaciones...');
        // Log::info("Iniciando exportación de marcaciones...");
        // $startTime = microtime(true);
        // $fechahasta = Carbon::today()->toDateString();
        // $fechadesde = Carbon::today()->subDays(6)->toDateString();
        // $hoy = Carbon::now();
        // $esLunes   = $hoy->isMonday();
        // $esViernes = $hoy->isFriday();
        // $marcacion_model = new Marcacion();
        // $estado_marcacion = null;
        // try {
        //     $estado_marcacion = $marcacion_model->saveAttendancesByAsc($fechadesde, $fechahasta);
        //     if (($esLunes || $esViernes) && $estado_marcacion > 30) {
        //         $marcacion_model->deleteAttendances();
        //     }
        //     Log::info('Mi comando se ejecutó');
        // } catch (\Exception $e) {
        //     Log::warning("La operación tomó más de 20 segundos y fue cancelada.");
        // }
    }
}
