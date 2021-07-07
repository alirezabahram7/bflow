<?php


namespace BFlow;

use BFlow;

abstract class Flow
{
    protected $flow;
    protected $isMain;
    protected static $arguments;
    protected $checkpoints;
    //protected static $defaultFlow = RegFlow::class;
    protected static $defaultCheckpoint = 'REG';
    /**
     * @return $this
     */
    public function getThis()
    {
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFlow()
    {
        return $this->flow;
    }

    /**
     * @return mixed
     */
    public function getCheckpoints()
    {
        return $this->checkpoints;
    }

    /**
     * @return mixed
     */
    public function getIsMain()
    {
        return $this->isMain;
    }

    /**
     * @param string $accessoryClass
     * @param string $after
     */
    public function addAccessory(string $accessoryClass, string $after = null)
    {
        $accessoryFlow = self::callMethod($accessoryClass , 'getFlow');

        if( ! $after) {
            $this->flow = array_merge($this->flow, $accessoryFlow);
        }
        else {
            foreach ($accessoryFlow as $state) {
                $afterIndex = BFlow::getIndexOfState($after, $this->flow);
                $firstSlice = array_slice($this->flow, 0, $afterIndex + 1);
                $secondSlice = array_slice($this->flow, $afterIndex + 1, count($this->flow));
                $this->flow = $firstSlice;
                $this->flow[] = $state;
                $this->flow = array_merge($this->flow, $secondSlice);
                $after = $state;
            }
        }
    }

    /**
     * @param string $pathAndClassName
     * @param string $functionName
     * @param bool $statically
     * @return bool|mixed
     */
    public static function callMethod(string $pathAndClassName, string $functionName, $statically = false)
    {
        if ( ! class_exists($pathAndClassName)) return false;
        if ( ! $statically) {
            $obj = new $pathAndClassName;
        }
        return call_user_func(array($obj ?? $pathAndClassName, $functionName));
    }
}
