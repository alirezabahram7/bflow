<?php


namespace BFlow\Console;


use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

class Flow extends GeneratorCommand
{
    protected $signature = 'make:flow {name}'; //type
    protected $type = 'Flow';
    /**
     * @inheritDoc
     */
    protected function getStub()
    {
        return __DIR__ . '/stubs/flow.php.stub';
    }

    /**
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Http\Flows';
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the Flow class.'],
        ];
    }
}
