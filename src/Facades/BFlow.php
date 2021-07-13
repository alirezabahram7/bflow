<?php


namespace BFlow\Facades;

use Illuminate\Support\Facades\Facade;

class BFlow extends Facade
{

    /**
     * Class BFlow
     * @package BFlow
     * @method static string getNextState($currentState, $arguments)

     * @method static object detectUserFlow($userId, $flowAndState)
     * @method static mixed|null getUserMainFlow($userId)
     * @method static object getDefaultFlow()
     * @method static string|null jumpTo(string $flow, string $state = null)
     * @method static mixed|null getUserCheckpoint($userId, $flowName)
     * @method static null setUserCheckpoint($userId = null, $isMain = null, $flowName = null, $previousCheckpoint = null, $checkpoint = null)
     * @method static string|null getCheckpoint()
     * @method static string setCheckpoint(string $checkpoint)
     * @method static string|null getPreviousCheckpoint()
     * @method static mixed|false callMethod(string $pathAndClassName, string $functionName, $statically = false)
     * @method static string toPascalCase($string)
     * @method static string toUrlFormat(string $string)
     * @method static string getClassName(string $stateClassAddress)
     * @method static int|false getIndexOfState(string $state, array $flow)
     * @method static array separateFlowAndState($flowAndState)
     */

    protected static function getFacadeAccessor() : string
    {
        return 'bflow';
    }
}