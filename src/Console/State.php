<?php


namespace BFlow\Console;


use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

class State extends GeneratorCommand
{
    protected $signature = 'make:state {name}'; //type ?
    protected $type = "State";

    /**
     * @inheritDoc
     */
    protected function getStub()
    {
        return __DIR__ . '/stubs/state.php.stub';
    }

    /**
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Http\States';
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the filter class.'],
        ];
    }

}
