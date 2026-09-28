<?php

namespace Condoedge\Projects;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ProjectsServiceProvider extends ServiceProvider
{
    use \Kompo\Routing\Mixins\ExtendsRoutingTrait;

    public function boot()
    {
        $this->extendRouting(); // so Route::layout() works in the package routes

        $this->loadJSONTranslationsFrom(__DIR__.'/../resources/lang');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'projects');
        $this->loadConfig();

        $this->setCommands();
        $this->setCronJobs();
    }

    public function register()
    {
        // Load routes at the very end (after fortify/auth), like the other condoedge packages.
        $this->booted(function () {
            \Route::middleware('web')->group(__DIR__.'/../routes/web.php');
            \Route::middleware('api')->prefix('api')->group(__DIR__.'/../routes/api.php');
        });
    }

    protected function loadConfig()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/projects.php', 'projects');
    }

    protected function setCommands()
    {
        $this->commands([
            \Condoedge\Projects\Console\Commands\ReconcileGithubCommand::class,
            \Condoedge\Projects\Console\Commands\ImportSuggestionsCommand::class,
            \Condoedge\Projects\Console\Commands\SeedPmDataCommand::class,
        ]);
    }

    protected function setCronJobs()
    {
        $schedule = $this->app->make(Schedule::class);

        // Safety net for missed GitHub webhooks. onOneServer() only picks which server runs a tick;
        // withoutOverlapping() is what stops a run still calling GitHub from meeting the next one.
        $schedule->command('projects:reconcile-github')->hourly()->onOneServer()->withoutOverlapping();
    }
}
