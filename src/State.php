<?php


namespace Behamin\BFlow;


abstract class State
{
    public const DISPLAY = 'display';
    public const ACTION = 'action';
    public const DECISION = 'decision';
    public const TERMINAL = 'terminal';

    public $type;

    public $allowedCheckpoints;
    protected $checkpoint;

    public $yes;
    public $no;
    const YES = 'yes';
    const NO = 'no';
    public static $arguments;
    public static $currentFlowAddress;

    public $next = null;
    public $prefix = '';

    public function getThis()
    {
        return $this;
    }

    public function getArguments()
    {
        return $this->getArguments();
    }

    public function setArguments(array $arguments)
    {
        self::$arguments = $arguments;
    }

    public function getCheckpoint()
    {
        return $this->checkpoint;
    }

    public function setCheckpoint(string $checkpoint)
    {
        $this->checkpoint = $checkpoint;
    }

}
