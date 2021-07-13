<?php

namespace BFlow;


use BFlow\Facades\BFlow;

class BFLowServiceProvider
{
    public function register()
    {
        $this->app->bind('bflow', function ($app) {
            return new BFlow();
        });
    }
}