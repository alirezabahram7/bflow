<?php


namespace Behamin\BFlow;

use Behamin\BFlow\State;
use Behamin\BFlow\Traits\FlowTrait;
use Exception;
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



    /**
     * @param $currentState
     * @param $arguments
     * @return string
     */
    public static function getNextState($previousState, $arguments) : string
    {
        self::$arguments = $arguments;
        self::$userFlow = self::detectUserFlow(self::$arguments['user_id'] ?? null, $previousState);

        $nextPlus1State = null;
        do {
            $currentStateIndex = self::getIndexOfState(self::$userFlow->state_address, self::$userFlow->flow);
            if ($currentStateIndex === false) {
                abort(404);
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

            $nextState = ( ! $nextPlus1State) ? self::$userFlow->flow[$nextStateIndex] : $nextPlus1State;
            $nextStateAddress = $nextState;
            $nextState = self::callMethod($nextState, 'getThis');

            $nextPlus1State = null;

            if (empty($nextState)) {
                abort(404);
            }

            if ($nextState->allowedCheckpoints and ! in_array(self::$userFlow->checkpoint, $nextState->allowedCheckpoints)) {
//                $responseMessage = 'You do not have access rights to the content!'."\n".
//                    'your checkpoint = ' . self::$userFlow->checkpoint."\n".
//                    'requested flow name = '. self::$userFlow->flow_name."\n".
//                    'requested state = '. self::getClassName($nextStateAddress);
//                return Response($responseMessage, 403);
                $nextStateAddress = self::detectStateByCheckpoint(self::callMethod(self::$userFlow->flow_address, 'getThis'), self::$userFlow->checkpoint);
                $nextState = self::callMethod($nextStateAddress, 'getThis');
            }

            // assign next state values into $userFlow
            $nextStateName = self::getClassName($nextStateAddress);
            self::$userFlow->state = $nextStateName;
            self::$userFlow->state_type = strtolower($nextState->type);
            self::$userFlow->state_address = $nextStateAddress;

            $currentCheckpoint = self::$userFlow->checkpoint;
            $currentStateType = self::$userFlow->state_type;

            if (in_array($currentStateType,[self::DECISION, self::ACTION])) {

                // exchange properties value between $this and next state
                State::$arguments = self::$arguments;
                State::$currentFlowAddress = self::$userFlow->flow_address;
                if ( ! class_exists($nextStateAddress)) {
                    $responseMessage = 'Error: There is not class of ' .$nextStateAddress. '!';
                    throw new \Exception($responseMessage, 500);
                }
                $stateObj = new $nextStateAddress();
                $result = call_user_func(array($stateObj, $nextStateName));

                self::$arguments = State::$arguments;

                // get the result of the state function
                if ($currentStateType == self::DECISION) {
                    if(class_exists($result)) {
                        $nextPlus1State = $result;
                    }
                    elseif ( ! empty($result) and ! empty($nextState->$result) and class_exists($nextState->$result)) {
                        $nextPlus1State = $nextState->$result;
                    }
                    elseif($result == null or $result == '') {
                        $nextStateName = null;
                    }
                    else {
                        $responseMessage = "TypeError: Return value of $nextStateAddress must be of the type class address, incorrect value returned!";
                        throw new \TypeError($responseMessage, 500);
                    }
                } elseif ($currentStateType == self::ACTION) {

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
            if ($nextState->type == self::TERMINAL or empty($nextStateName)) { $next=''; }
            else { $next = $flowName . '/' . ($nextState->prefix ?? '') . self::toUrlFormat($nextStateName); }

            $nextCheckpoint = $nextStateCheckpoint ?? self::$userFlow->checkpoint;
            if ($currentCheckpoint != $nextCheckpoint) {
                self::$userFlow->previous_checkpoint = self::$userFlow->checkpoint;
                self::$userFlow->checkpoint = $nextCheckpoint;
            }

//            print_r(self::$userFlow);
//            echo '<br>'.'<br>';
//            print_r ($nextPlus1State);
        } while (in_array($nextState->type,[self::DECISION, self::ACTION]) and ! empty($nextStateName));

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
            $userMainFlow = self::getMainUserFlow($userId);
            if (empty($userMainFlow)) {
                $source = 'default';
                $userDefaultFlow = self::getDefaultUserFlow();
            }
        }
        $userFlow = $userDBFlow ?? $userMainFlow ?? $userDefaultFlow;

        $flowAddress = self::FLOW_NAMESPACE .'\\'. $userFlow->flow_name;
        $flowClass = self::callMethod($flowAddress, 'getThis');
        $flow = $flowClass->getFlow();
        if ( ! empty($userDBFlow) or ( ! empty($userDefaultFlow) and (strtolower($flowTitle) == strtolower($userFlow->flow_name)))) {
            $stateAddress = empty($state) ? $flow[0] : self::STATE_NAMESPACE .'\\'. self::toPascalCase($state);
        } else {
            $stateAddress = self::detectStateByCheckpoint($flowClass, $userFlow->checkpoint);
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
            'flow_name' => $userFlow->flow_name,
            'flow_address' => $flowAddress,
            'flow' => $flow,
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
    private static function getMainUserFlow($userId)
    {
        if (empty($userId)) return null;
        return DB::table('user_checkpoint')->where('user_id', $userId)->where('is_main_flow', 1)->get()->first();
    }

    public static function getDefaultFlow()
    {
        return config('bflow.default_flow') ?? null;
    }

    public static function getDefaultCheckpoint()
    {
        return config('bflow.default_checkpoint') ?? null;
    }

    public static function detectStateByCheckpoint($flowClass, $checkpoint = null)
    {
        $defaultCheckpoint = BFlow::getDefaultFlow();
        $checkpoint = ($checkpoint ?? $defaultCheckpoint) ?? null;
        if (empty($checkpoint)) {
            $responseMessage = 'Argument 2 and Default checkpoint in bflow.php are null!';
            throw new \Exception($responseMessage, 500);
        }
        $checkpointArray = $flowClass->getCheckpoints()[strtoupper($checkpoint)] ?? null;
        if(empty($checkpointArray)) {
            if ($checkpoint) {
                $responseMessage = $checkpoint .' not defined in '.get_class($flowClass).'->checkpoints array or \'next\' item not defined for this checkpoint!';
            } else {
                $responseMessage = $defaultCheckpoint .' not defined in '.get_class($flowClass).'->checkpoints array or \'next\' item not defined for this checkpoint!';
            }
            throw new \Exception($responseMessage, 500);
        }
        return ($checkpointArray['next'] ?? $flowClass->getFlow()[0]) ?? null;
    }

    /**
     * @return object
     */
    private static function getDefaultUserFlow() : object
    {
        $defaultFlow = self::getDefaultFlow();
        if( empty($defaultFlow)) {
            $responseMessage = 'Default flow in bflow.php in config folder is not set!';
            throw new \Exception($responseMessage, 500);
        }
        $flowName = substr($defaultFlow, strripos($defaultFlow,'\\'));
        return (object)[
            'flow_name' => $flowName,
            'previous_checkpoint'=>null,
            'checkpoint' => self::getDefaultCheckpoint()
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
        self::$userFlow->flow_address = $flow;
        self::$userFlow->flow_name = $flowName;
        self::$userFlow->state_address = ( ! empty($state)) ? $state : $flowClass->getFlow()[0];
        self::$userFlow->state = self::getClassName(self::$userFlow->state_address);
        $stateClass = self::callMethod(self::$userFlow->state_address, 'getThis');
        self::$userFlow->state_type = $stateClass->type;
    }


    /**
     * @param $userId
     * @param $flowName
     * @return mixed|null
     */
    public static function getUserCheckpoint($userId, $flowName = null)
    {
        if (empty($userId)) return null;
        if(empty($flowName)) { $fieldName = 'is_main_flow'; $fieldValue = 1; }
        else { $fieldName = 'flow_name'; $fieldValue = $flowName; }
        return DB::table('user_checkpoint')->where('user_id', $userId)->where($fieldName, $fieldValue)->get()->first();
    }


    /**
     * @param null $userId
     * @param null $checkpoint
     * @param null $flowName
     */
    public static function setUserCheckpoint($userId = null, $checkpoint = null, string $flowName = null) : void
    {
        if( ! $userId) {
            $userId = self::$arguments['user_id'] ?? null;
            $isMain = self::$userFlow->is_main;
            $flowName = self::$userFlow->flow_name;
            $previousCheckpoint = self::$userFlow->previous_checkpoint;
            $checkpoint = self::$userFlow->checkpoint;
            $values = [
                'flow_name' => $flowName,
                'previous_checkpoint' => $previousCheckpoint,
                'checkpoint' => $checkpoint,
            ];
        }
        elseif($userId and $checkpoint) {
            if (empty($flowName)) {
                $isMain = true;
            }
            else {
                $isMain = self::callMethod(self::FLOW_NAMESPACE . '\\' . $flowName, 'getIsMain');
            }

            $CurrentCheckpointInDB = self::getUserCheckpoint($userId, ( ! $isMain) ? $flowName : null)->checkpoint;
            $values = [
                'checkpoint' => $checkpoint,
            ];
            if ($CurrentCheckpointInDB != $checkpoint) {
                $values = array_merge($values, [
                    'previous_checkpoint' => $CurrentCheckpointInDB,
                ]);
            }
            if ( ! empty($flowName)) {
                $values = array_merge($values, [
                    'flow_name' => $flowName,
                ]);
            }
        }

        if ($isMain) { $conditions = ['user_id' => $userId, 'is_main_flow' => true]; }
        else { $conditions = ['user_id' => $userId, 'is_main_flow' => false, 'flow_name' => $flowName]; }

        DB::table('user_checkpoint')->updateOrInsert($conditions, $values);
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
