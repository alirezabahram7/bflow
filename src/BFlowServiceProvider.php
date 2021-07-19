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
        if ($this->app->runningInConsole()) {
            $this->commands([
                FlowMakeCommand::class,
                StateMakeCommand::class
            ]);

            $this->publishes([
                __DIR__ . '/../config/bflow.php' => config_path('bflow.php')
            ], 'config');

            if (! class_exists('CreateUserCheckpointTable')) {
                $this->publishes([
                    __DIR__ . '/../database/migrations/create_user_checkpoint_table.php.stub' =>
                        database_path('migrations/' . date('Y_m_d_His', time()) . '_create_user_checkpoint_table.php'),
                ], 'migrations');
            }
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
