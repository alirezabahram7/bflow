<?php

namespace BFlow;


use BFlow\Facades\BFlow;
use Illuminate\Support\ServiceProvider;

class BFlowServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('bflow', function ($app) {
            return new BFlow();
        });
    }
}