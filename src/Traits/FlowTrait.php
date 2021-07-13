<?php


namespace BFlow\Traits;


use BFlow\Flow;
use Illuminate\Support\Facades\DB;

trait FlowTrait
{

    /**
     * @param string $pathAndClassName
     * @param string $functionName
     * @param false $statically
     * @return false|mixed
     */
    public static function callMethod(string $pathAndClassName, string $functionName, $statically = false)
    {
        if ( ! class_exists($pathAndClassName)) return false;
        if ( ! $statically) {
            $obj = new $pathAndClassName;
        }
        return call_user_func(array($obj ?? $pathAndClassName, $functionName));
    }

    /**
     * @param $string
     * @return string
     */
    public static function toPascalCase($string) : string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '/', '\\'], ' ', $string)));
    }

    /**
     * @param string $string
     * @return string
     */
    public static function toUrlFormat(string $string) : string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '/$0', $string));
    }

    /**
     * @param string $stateClassAddress
     * @return string
     */
    public static function getClassName(string $stateClassAddress) : string
    {
        $pos = strrpos($stateClassAddress, '\\') + 1;
        return ucfirst($pos !==false ? substr($stateClassAddress, $pos) : $stateClassAddress);
    }

    /**
     * @param string $state
     * @param array $flow
     * @return false|int
     */
    public static function getIndexOfState(string $state, array $flow) : ? int
    {
        return array_search($state, $flow);
    }

    /**
     * @param $flowAndState
     * @return array
     */
    public static function separateFlowAndState($flowAndState) : array
    {
        $slashPosition = strpos($flowAndState,'/');
        if ($slashPosition === false) { $slashPosition = strlen($flowAndState); }
        $flow = substr($flowAndState, 0, $slashPosition);
        $state = substr($flowAndState, $slashPosition + 1);
        return [$flow, $state];
    }
}
