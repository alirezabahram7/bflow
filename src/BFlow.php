<?php


namespace BFlow;


use App\Flows\AbstractFlow;
use App\Flows\States\State;
use Illuminate\Support\Facades\DB;

class BFlow
{
    private const INOUT = 'inout';
    private const PROCESS = 'process';
    private const DECISION = 'decision';
    private const TERMINAL = 'terminal';

    protected static $flow = [];
    protected static $arguments = [];
    protected static $defaultFlow;
    protected static $userFlow;

    public static function getStatesName(array $states) : array
    {
        return collect($states)->pluck('name')->toArray();
    }

    public static function getNextState($currentState, $arguments) : string
    {
        self::$arguments = $arguments;
        self::$userFlow = self::detectUserFlow(self::$arguments['user_id'] ?? null, $currentState);

        $nextPlus1State = null;
        do {
            $currentStateIndex = self::getIndexOfState(self::$userFlow->state_address, self::$userFlow->flow);
            if ($currentStateIndex === false) {
                return null;
            }

            $currentState = AbstractFlow::callMethod(self::$userFlow->flow[$currentStateIndex],'getThis'); ///
            if($currentState->next) {
                $nextStateIndex = self::getIndexOfState($currentState->next, self::$userFlow->flow);
            } else {
                $nextStateIndex = $currentStateIndex;
                if (strtolower(self::$userFlow->source) == 'db') { ///
                    $nextStateIndex = $currentStateIndex + 1;
                } elseif (strtolower(self::$userFlow->source) == 'previous_flow') {
                    self::$userFlow->source = 'db';
                }
            }

            $nextState = AbstractFlow::callMethod(self::$userFlow->flow[$nextStateIndex], 'getThis');
            $nextStateAddress = self::$userFlow->flow[$nextStateIndex];
            if ($nextPlus1State) {
                $nextState = AbstractFlow::callMethod($nextPlus1State, 'getThis');
                $nextStateAddress = $nextPlus1State;
                $nextPlus1State = null;
            }

            self::$userFlow->state = $nextState->name;
            self::$userFlow->state_type = $nextState->type;
            self::$userFlow->state_address = __NAMESPACE__ . '\\States\\' . self::toPascalCase($nextState->name);

            if (empty($nextState)) {
                return 'not found!';
                // stop or abort(404)
            }

            if ($nextState->allowedCheckpoints and ! in_array(self::$userFlow->checkpoint, $nextState->allowedCheckpoints)) {
                return 'not allowed!';
                // stop or move to a state by checkpoint
            }
            $currentCheckpoint = self::$userFlow->checkpoint;
            if (in_array(strtolower($nextState->type),[self::DECISION, self::PROCESS])) {
                State::$arguments = self::$arguments;
                $stateObj = new $nextStateAddress();
                $result = call_user_func(array($stateObj, $nextState->name));
                self::$arguments = State::$arguments;
                if (strtolower($nextState->type) == self::DECISION) {
                    if ( ! empty($result) and ! empty($nextState->$result)) {
                        $nextPlus1State = $nextState->$result;
                    }
                    else {
                        self::$userFlow->state = $nextState->name;
                    }
                } elseif (strtolower($nextState->type) == self::PROCESS) {
                    if(self::$userFlow->source != 'previous_flow') {
                        self::$userFlow->state = $nextState->name;
                    }
                }
                $nextStateCheckpoint = $stateObj->getCheckpoint() ?? $nextState->getCheckpoint();
            }
            else {
                $nextStateCheckpoint = $nextState->getCheckpoint();
            }

            $flowName = self::$userFlow->flow_name;
            if (str_ends_with(self::$userFlow->flow_name, 'Flow')) { $flowName = strtolower(str_ireplace(array('Flow'), '', $flowName)); }
            $next = $flowName . '/' . $nextState->name;
            if (strtolower($nextState->type) == self::TERMINAL) { $next=''; }
            $checkpoint = $nextStateCheckpoint ?? self::$userFlow->checkpoint;
            if ($currentCheckpoint != $checkpoint) {
                self::$userFlow->previous_checkpoint = self::$userFlow->checkpoint;
                self::$userFlow->checkpoint = $checkpoint;
            }
            print_r(self::$userFlow); echo '<br/><br/>';
        } while (in_array(strtolower($nextState->type),[self::DECISION, self::PROCESS]));

        if(self::$userFlow->checkpoint and self::$arguments['user_id']) {
            self::setUserCheckpoint();
        }
        return $next;
    }

    protected static function toPascalCase($string)
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '/', '\\'], ' ', $string)));
    }

    public static function getIndexOfState(string $state,array $flow)
    {
        return array_search($state, $flow);
    }

    private static function separateFlowAndState($flowAndState) : array
    {
        $slashPosition = strpos($flowAndState,'/');
        if ($slashPosition === false) { $slashPosition = strlen($flowAndState); }
        $flow = substr($flowAndState, 0, $slashPosition);
        $state = substr($flowAndState, $slashPosition + 1);
        return [$flow, $state];
    }

    public static function loadStatesOfFlow(string $flowName) : array
    {
        $flowClassName = __NAMESPACE__ .'\\' . $flowName;
        return AbstractFlow::callMethod($flowClassName, 'getFlow');
    }

    private static function detectUserFlow($userId, $FlowAndState) : object
    {
        [$flowTitle, $state] = self::separateFlowAndState(strtolower($FlowAndState));
        $flowName = ucfirst(strtolower($flowTitle)) . 'Flow';

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

        $flowClassName = __NAMESPACE__ .'\\' . $userFlow->flow_name;
        $flowClass = AbstractFlow::callMethod($flowClassName, 'getThis');
        $flow = $flowClass->getFlow();
        if ( ! empty($userDBFlow)) {
            $stateAddress = empty($state) ? $flow[0] : __NAMESPACE__ .'\\States\\'. self::toPascalCase($state);
        } else {
            $stateAddress = $flowClass->getCheckpoints()[strtoupper($userFlow->checkpoint)]['next'] ?? $flow[0];
        }
        $stateClass = AbstractFlow::callMethod($stateAddress, 'getThis');
        if ($stateClass === false) {
            $state = '';
            $stateType = '';
        } else {
            $state = $stateClass->name;
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

    private static function getUserMainFlow($userId)
    {
        if (empty($userId)) return null;
        return DB::table('user_checkpoint')->where('user_id', $userId)->where('is_main_flow', 1)->get()->first();
    }

    private static function getDefaultFlow() : object
    {
        $flowName = substr(static::$defaultFlow, strripos(static::$defaultFlow,'\\') + 1);
        return (object)[
            'flow_name' => $flowName,
            'previous_checkpoint'=>null,
            'checkpoint' => static::$defaultCheckpoint
        ];
    }

    public static function jumpTo(string $flow, string $state = null)
    {
        $flowName = substr($flow, strripos($flow,'\\') + 1);
        $flowClass = AbstractFlow::callMethod($flow, 'getThis');
        self::$userFlow->source = 'previous_flow';
        self::$userFlow->flow = $flowClass->getFlow();
        self::$userFlow->flow_name = $flowName;
        self::$userFlow->state_address = (array_search($state, $flowClass->getFlow()) !== false) ? $state : self::$userFlow->state = $flowClass->getFlow()[0];
        self::$userFlow->state = AbstractFlow::callMethod(self::$userFlow->state_address, 'getThis')->name;
    }

    public static function getUserCheckpoint($userId, $flowName)
    {
        if (empty($userId)) return null;
        return DB::table('user_checkpoint')->where('user_id', $userId)->where('flow_name', $flowName)->get()->first();
    }

    public static function setUserCheckpoint($userId = null, $isMain = null, $flowName = null, $previousCheckpoint = null, $checkpoint = null)
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

    public static function getCheckpoint() : ? string
    {
        return self::$userFlow->checkpoint;
    }

    public static function setCheckpoint(string $checkpoint)
    {
        self::$userFlow->checkpoint = $checkpoint;
    }

    public static function getPreviousCheckpoint() : ? string
    {
//        $userCheckpoint = self::getUserCheckpoint(self::$userFlow->user_id, self::$userFlow->flow_name);
//        return $userCheckpoint->previous_checkpoint ?? null;
        return self::$userFlow->previous_checkpoint;
    }
}
