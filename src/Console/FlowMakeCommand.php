<?php


namespace Behamin\BFlow\Console;


use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class FlowMakeCommand extends GeneratorCommand
{
    protected $name = 'make:flow';
    protected $description = 'Create a new flow class';
    protected $type = 'Flow';
    /**
     * @inheritDoc
     */
    protected function getStub()
    {
        return __DIR__ . '/stubs/flow.stub';
    }

    /**
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Http\BFlow\Flows';
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the Flow class.'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['main', 'mn', InputOption::VALUE_NONE, 'A property for definition a main flow.'],
            ['dependent', 'dp', InputOption::VALUE_REQUIRED, 'A boolean property that Specifies this is a dependent flow as a auxiliary.'],
        ];
    }

    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);
        if($stub) {
            $isMain = (bool)$this->option('main') ? 'true' : 'false';
            $isDependent = $this->option('dependent');
            $isDependent = ($isDependent == 'true' or $isDependent>0) ? 'true' : 'false';
            $isDependent = $isMain=='true' ? 'false' : $isDependent;
            $stub = str_replace(['{{ isMain }}', '{{isMain}}'], $isMain, $stub);
            return str_replace(['{{ isDependent }}', '{{isDependent}}'], $isDependent, $stub);
        }
    }
}
