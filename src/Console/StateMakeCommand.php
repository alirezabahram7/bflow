<?php


namespace BFlow\Console;


use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class StateMakeCommand extends GeneratorCommand
{
    protected $signature = 'make:state {name} {--type=process}';
    protected $description = 'Create a new state class';
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
        return $rootNamespace . '\Http\BFlow\States';
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the state class.'],
        ];
    }

    protected function getOptions(){
        return[
            ['type', 'tp', InputOption::VALUE_REQUIRED, 'The Type of the state in the user-flow'],
        ];
    }


}
