<?php

namespace App\Providers;

use App\Repositories\DashboardRepository;
use App\Repositories\MedicoRepository;
use App\Repositories\UciRepository;
use App\Services\ExcelParserService;
use App\Services\ReporteExcelService;
use App\Services\ReportePdfService;
use App\Services\TurnoCalculatorService;
use App\Services\TurnoService;
use App\Services\AlertService;
use App\Services\PlantillaService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExcelParserService::class);
        $this->app->singleton(TurnoCalculatorService::class);
        $this->app->singleton(ReporteExcelService::class);
        $this->app->singleton(ReportePdfService::class);
        $this->app->singleton(DashboardRepository::class);
        $this->app->singleton(MedicoRepository::class);
        $this->app->singleton(UciRepository::class);
        $this->app->singleton(TurnoService::class);
        $this->app->singleton(AlertService::class);
        $this->app->singleton(PlantillaService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
