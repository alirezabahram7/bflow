<?php

namespace BFlow;


use BFilters\Console\Filter;
use BFlow\Console\FlowMakeCommand;
use BFlow\Console\StateMakeCommand;
use BFlow\Facades\BFlow;
use Illuminate\Support\ServiceProvider;

class BFlowServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands(
                [
                    FlowMakeCommand::class,
                    StateMakeCommand::class
                ]
            );
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
