<?php

namespace Behamin\BFlow;


use Behamin\BFlow\Console\FlowMakeCommand;
use Behamin\BFlow\Console\StateMakeCommand;
use Behamin\BFlow\Facades\BFlow;
use Illuminate\Support\ServiceProvider;

class BFlowServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                FlowMakeCommand::class,
                StateMakeCommand::class
            ]);

            $this->publishes([
                __DIR__ . '/../config/bflow.php' => config_path('bflow.php')
            ], 'config');
        }
    }

    public function register()
    {
        $this->app->bind(
            'bflow',
            function ($app) {
                return new BFlow();
            }
        );
    }
}
