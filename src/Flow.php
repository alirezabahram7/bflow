<?php


namespace Behamin\BFlow;


use Behamin\BFlow\Traits\FlowTrait;

abstract class Flow
{
    use FlowTrait;

    protected $flow;
    protected $isMain;
    protected static $arguments;
    protected $checkpoints;

    /**
     * @return $this
     */
    public function getThis() : Flow
    {
        return $this;
    }


    /**
     * @return array
     */
    public function getFlow() : array
    {
        return $this->flow;
    }


    /**
     * @return array
     */
    public function getCheckpoints() : array
    {
        return $this->checkpoints;
    }


    /**
     * @return bool
     */
    public function getIsMain() : bool
    {
        return $this->isMain;
    }


    /**
     * @param string $accessoryClass
     * @param string|null $after
     */
    public function addAccessory(string $accessoryClass, string $after = null) : void
    {
        $accessoryFlow = self::callMethod($accessoryClass , 'getFlow');

        if( ! $after) {
            $this->flow = array_merge($this->flow, $accessoryFlow);
        }
        else {
            foreach ($accessoryFlow as $state) {
                $afterIndex = self::getIndexOfState($after, $this->flow);
                $firstSlice = array_slice($this->flow, 0, $afterIndex + 1);
                $secondSlice = array_slice($this->flow, $afterIndex + 1, count($this->flow));
                $this->flow = $firstSlice;
                $this->flow[] = $state;
                $this->flow = array_merge($this->flow, $secondSlice);
                $after = $state;
            }
        }
    }
}
