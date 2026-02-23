<?php

declare(strict_types=1);

namespace Xfa\Pdf;

use Illuminate\Support\ServiceProvider;
use Xfa\Pdf\Commands\XfaPdfCommand;
use Xfa\Pdf\Services\DatasetService;
use Xfa\Pdf\Services\NamespaceService;
use Xfa\Pdf\Services\PdfBinaryService;
use Xfa\Pdf\Services\PreviewService;
use Xfa\Pdf\Services\RepeatableService;
use Xfa\Pdf\Services\TemplateService;

class XfaPdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/xfa-pdf.php', 'xfa-pdf');

        $this->app->singleton(NamespaceService::class);
        $this->app->singleton(PdfBinaryService::class);
        $this->app->singleton(TemplateService::class);
        $this->app->singleton(PreviewService::class);

        $this->app->singleton(DatasetService::class, function ($app) {
            return new DatasetService($app->make(NamespaceService::class));
        });

        $this->app->singleton(RepeatableService::class, function ($app) {
            return new RepeatableService($app->make(NamespaceService::class));
        });

        $this->app->singleton('xfa-pdf', function ($app) {
            return new XfaPdfManager(
                $app->make(PdfBinaryService::class),
                $app->make(DatasetService::class),
                $app->make(TemplateService::class),
                $app->make(RepeatableService::class),
                $app->make(PreviewService::class),
                $app->make(NamespaceService::class),
            );
        });

        $this->app->alias('xfa-pdf', XfaPdfManager::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'xfa-pdf');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/xfa-pdf.php' => config_path('xfa-pdf.php'),
            ], 'xfa-pdf-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/xfa-pdf'),
            ], 'xfa-pdf-views');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'xfa-pdf-migrations');

            $this->commands([
                XfaPdfCommand::class,
            ]);
        }
    }
}
