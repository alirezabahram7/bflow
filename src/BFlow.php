<?php


namespace Behamin\BFlow;

use Behamin\BFlow\State;
use Behamin\BFlow\Traits\FlowTrait;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class BFlow
{
    use FlowTrait;

    public const FLOW_NAMESPACE = 'App\Http\BFlow\Flows';
    public const STATE_NAMESPACE = 'App\Http\BFlow\States';
    
    public const DISPLAY = 'display';
    public const ACTION = 'action';
    public const DECISION = 'decision';
    public const TERMINAL = 'terminal';

    protected static $flow = [];
    protected static $arguments = [];
    protected static $defaultFlow;
    protected static $userFlow;


//    public static function getState($userId, $nextState, $arguments) : string
//    {
//
//    }

    /**
     * @param $currentState
     * @param $arguments
     * @return string
     */
    public static function getNextState($currentState, $arguments) : string
    {
        self::$arguments = $arguments;
        self::$userFlow = self::detectUserFlow(self::$arguments['user_id'] ?? null, $currentState);

        $nextPlus1State = null;
        do {
            $currentStateIndex = self::getIndexOfState(self::$userFlow->state_address, self::$userFlow->flow);
            if ($currentStateIndex === false) {
                abort(404);
//                return 'This page is not exist!';
                // stop or abort(404)
            }
            $currentState = self::callMethod(self::$userFlow->flow[$currentStateIndex],'getThis');
            if(strtolower(self::$userFlow->source) == 'main') {
                $nextStateIndex = $currentStateIndex;
            }
            if($currentState->next ?? false) {
                $nextStateIndex = self::getIndexOfState($currentState->next, self::$userFlow->flow);
            } else {
                $nextStateIndex = $currentStateIndex;
                if (in_array(strtolower(self::$userFlow->source), ['db', 'default'])) {
                    $nextStateIndex = $currentStateIndex + 1;
                } elseif (strtolower(self::$userFlow->source) == 'previous_flow') { // when developer use jump function
                    self::$userFlow->source = 'db';
                }
            }
            if ( ! $nextPlus1State) {
                $nextState = self::callMethod(self::$userFlow->flow[$nextStateIndex], 'getThis');
                $nextStateAddress = self::$userFlow->flow[$nextStateIndex];
            } else {
                $nextState = self::callMethod($nextPlus1State, 'getThis');
                $nextStateAddress = $nextPlus1State;
                $nextPlus1State = null;
            }

            if (empty($nextState)) {
                abort(404);
//                return 'This page is not exist!';
                // stop or abort(404)
            }

            if ($nextState->allowedCheckpoints and ! in_array(self::$userFlow->checkpoint, $nextState->allowedCheckpoints)) {
                return Response('You do not have access rights to the content!', 403);
                // stop or move to a state by checkpoint
            }

            // assign next state values into $userFlow
            $nextStateName = self::getClassName($nextStateAddress);
            self::$userFlow->state = $nextStateName;
            self::$userFlow->state_type = strtolower($nextState->type);
            self::$userFlow->state_address = $nextStateAddress;

            $currentCheckpoint = self::$userFlow->checkpoint;

            if (in_array(self::$userFlow->state_type,[self::DECISION, self::ACTION])) {

                // exchange properties value between $this and next state
                State::$arguments = self::$arguments;
                $stateObj = new $nextStateAddress();
                $result = call_user_func(array($stateObj, $nextStateName));
                self::$arguments = State::$arguments;

                // get the result of the state function
                if (self::$userFlow->state_type == self::DECISION) {
                    if(class_exists($result)) {
                        $nextPlus1State = $result;
                    }
                    elseif ( ! empty($result) and ! empty($nextState->$result) and class_exists($nextState->$result)) {
                        $nextPlus1State = $nextState->$result;
                    }
                    else {
                        $responseMessage = "TypeError: Return value of $nextStateAddress must be of the type class address, incorrect value returned!";
                        throw new \TypeError($responseMessage, 500);
                    }
                } elseif (self::$userFlow->state_type == self::ACTION) {
                    if(self::$userFlow->source != 'previous_flow') {
                        self::$userFlow->state = $nextStateName;
                        self::$userFlow->source = 'db';
                    }
                }
                $nextStateCheckpoint = $stateObj->getCheckpoint() ?? $nextState->getCheckpoint();
            }
            else {
                $nextStateCheckpoint = $nextState->getCheckpoint(); //  dangerous: don't set because checkpoint set without logic and checking
            }

            $flowName = lcfirst(self::$userFlow->flow_name);
            $next = $flowName . '/' . self::toUrlFormat($nextStateName);
            if ($nextState->type == self::TERMINAL) { $next=''; }

            $nextCheckpoint = $nextStateCheckpoint ?? self::$userFlow->checkpoint;
            if ($currentCheckpoint != $nextCheckpoint) {
                self::$userFlow->previous_checkpoint = self::$userFlow->checkpoint;
                self::$userFlow->checkpoint = $nextCheckpoint;
            }

//            print_r(self::$userFlow);
//            print_r ($nextPlus1State);
        } while (in_array($nextState->type,[self::DECISION, self::ACTION]));

        if(self::$userFlow->checkpoint and (self::$arguments['user_id'] ?? false)) {
            self::setUserCheckpoint();
        }
        return $next;
    }

    /**
     * @param $userId
     * @param $flowAndState
     * @return object
     */
    private static function detectUserFlow($userId, $flowAndState) : object
    {
        [$flowTitle, $state] = self::separateFlowAndState(strtolower($flowAndState));
        $flowName = ucfirst(strtolower($flowTitle));

        $source = 'db';
        $userDBFlow = self::getUserCheckpoint($userId, $flowName);
        if(empty($userDBFlow)) {
            $source = 'main';
            $userMainFlow = self::getUserMainFlow($userId);
            if (empty($userMainFlow)) {
                $source = 'default';
                $userDefaultFlow = self::getDefaultFlow();
            }
        }
        $userFlow = $userDBFlow ?? $userMainFlow ?? $userDefaultFlow;

        $flowClassName = self::FLOW_NAMESPACE .'\\'. $userFlow->flow_name;
        $flowClass = self::callMethod($flowClassName, 'getThis');
        $flow = $flowClass->getFlow();
        if ( ! empty($userDBFlow) or ! empty($userDefaultFlow)) {
            $stateAddress = empty($state) ? $flow[0] : self::STATE_NAMESPACE .'\\'. self::toPascalCase($state);
        } else {
            $stateAddress = $flowClass->getCheckpoints()[strtoupper($userFlow->checkpoint)]['next'] ?? $flow[0];
        }
        $stateClass = self::callMethod($stateAddress, 'getThis');
        if ($stateClass === false) {
            $state = '';
            $stateType = '';
        } else {
            $state = self::getClassName($stateAddress);
            $stateType = $stateClass->type;
        }
        return (object)[
            'source' => $source,
            'flow' => $flow,
            'flow_name' => $userFlow->flow_name,
            'is_main' => $flowClass->getIsMain(),
            'previous_checkpoint'=> $userFlow->previous_checkpoint,
            'checkpoint' => strtoupper($userFlow->checkpoint),
            'state' => $state,
            'state_type' => $stateType,
            'state_address' => $stateAddress
        ];
    }

    /**
     * @param $userId
     * @return mixed|null
     */
    private static function getUserMainFlow($userId)
    {
        if (empty($userId)) return null;
        return DB::table('user_checkpoint')->where('user_id', $userId)->where('is_main_flow', 1)->get()->first();
    }

    /**
     * @return object
     */
    private static function getDefaultFlow() : object
    {
        $defaultFlow = config('bflow.default_flow');
        $flowName = substr($defaultFlow, strripos($defaultFlow,'\\') + 1);
        return (object)[
            'flow_name' => $flowName,
            'previous_checkpoint'=>null,
            'checkpoint' => config('bflow.default_checkpoint')
        ];
    }


    /**
     * @param string $flow
     * @param string|null $state
     */
    public static function jumpTo(string $flow, string $state = null) : void
    {
        $flowName = substr($flow, strripos($flow,'\\') + 1);
        $flowClass = self::callMethod($flow, 'getThis');
        self::$userFlow->source = 'previous_flow';
        self::$userFlow->flow = $flowClass->getFlow();
        self::$userFlow->flow_name = $flowName;
        self::$userFlow->state_address = (array_search($state, $flowClass->getFlow()) !== false) ? $state : self::$userFlow->state = $flowClass->getFlow()[0];
        self::$userFlow->state = self::getClassName(self::$userFlow->state_address);
    }


    /**
     * @param $userId
     * @param $flowName
     * @return mixed|null
     */
    public static function getUserCheckpoint($userId, $flowName)
    {
        if (empty($userId)) return null;
        return DB::table('user_checkpoint')->where('user_id', $userId)->where('flow_name', $flowName)->get()->first();
    }


    /**
     * @param null $userId
     * @param null $isMain
     * @param null $flowName
     * @param null $previousCheckpoint
     * @param null $checkpoint
     */
    public static function setUserCheckpoint($userId = null, $isMain = null, $flowName = null, $previousCheckpoint = null, $checkpoint = null) : void
    {
        if( ! $userId and ! $isMain and ! $flowName and ! $previousCheckpoint and ! $checkpoint) {
            $userId = self::$arguments['user_id'] ?? null;
            $isMain = self::$userFlow->is_main;
            $flowName = self::$userFlow->flow_name;
            $previousCheckpoint = self::$userFlow->previous_checkpoint;
            $checkpoint = self::$userFlow->checkpoint;
        }

        if ($isMain) { $conditions = ['user_id' => $userId, 'is_main_flow' => $isMain]; }
        else { $conditions = ['user_id' => $userId, 'flow_name' => $flowName, 'is_main_flow' => $isMain]; }
        DB::table('user_checkpoint')->updateOrInsert(
            $conditions,
            [
                'flow_name' => $flowName,
                'previous_checkpoint' => $previousCheckpoint,
                'checkpoint' => $checkpoint,
                'is_main_flow' => $isMain
            ]
        );
    }

    /**
     * @return string|null
     */
    public static function getCheckpoint() : ? string
    {
        return self::$userFlow->checkpoint;
    }

    /**
     * @param string $checkpoint
     */
    public static function setCheckpoint(string $checkpoint) : void
    {
        self::$userFlow->checkpoint = $checkpoint;
    }

    /**
     * @return string|null
     */
    public static function getPreviousCheckpoint() : ? string
    {
        return self::$userFlow->previous_checkpoint;
    }
}
