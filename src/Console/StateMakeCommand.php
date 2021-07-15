<?php


namespace Behamin\BFlow\Console;


use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class StateMakeCommand extends GeneratorCommand
{
    protected $name = 'make:state';
    protected $description = 'Create a new state class';
    protected $type = "State";

    /**
     * @inheritDoc
     */
    protected function getStub()
    {
        if (in_array(strtolower($this->option('type')), ['decision', 'action'])) {
            return __DIR__.'/stubs/functional.state.stub';
        }
        return __DIR__.'/stubs/state.stub';
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

    protected function getOptions()
    {
        return [
            ['type', 'tp', InputOption::VALUE_REQUIRED, 'The Type of the state in the user-flow'],
        ];
    }

    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);
        $className = $this->argument('name') ?? null;
        if($stub and $className){
            $stateType = strtoupper($this->option('type'));
            $stateType = in_array($stateType, ['DISPLAY', 'DECISION', 'ACTION', 'TERMINAL']) ? $stateType : 'DISPLAY';
            $stub = str_replace(['{{ function }}', '{{function}}'], lcfirst($className), $stub);
            return str_replace(['{{ type }}', '{{type}}'], 'State::' . $stateType, $stub);
        }
    }
}
