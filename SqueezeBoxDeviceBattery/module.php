<?

if (@constant('IPS_BASE') == null) //Nur wenn Konstanten noch nicht bekannt sind.
{
// --- BASE MESSAGE
    define('IPS_BASE', 10000);                             //Base Message
    define('IPS_KERNELSHUTDOWN', IPS_BASE + 1);            //Pre Shutdown Message, Runlevel UNINIT Follows
    define('IPS_KERNELSTARTED', IPS_BASE + 2);             //Post Ready Message
// --- KERNEL
    define('IPS_KERNELMESSAGE', IPS_BASE + 100);           //Kernel Message
    define('KR_CREATE', IPS_KERNELMESSAGE + 1);            //Kernel is beeing created
    define('KR_INIT', IPS_KERNELMESSAGE + 2);              //Kernel Components are beeing initialised, Modules loaded, Settings read
    define('KR_READY', IPS_KERNELMESSAGE + 3);             //Kernel is ready and running
    define('KR_UNINIT', IPS_KERNELMESSAGE + 4);            //Got Shutdown Message, unloading all stuff
    define('KR_SHUTDOWN', IPS_KERNELMESSAGE + 5);          //Uninit Complete, Destroying Kernel Inteface
// --- KERNEL LOGMESSAGE
    define('IPS_LOGMESSAGE', IPS_BASE + 200);              //Logmessage Message
    define('KL_MESSAGE', IPS_LOGMESSAGE + 1);              //Normal Message                      | FG: Black | BG: White  | STLYE : NONE
    define('KL_SUCCESS', IPS_LOGMESSAGE + 2);              //Success Message                     | FG: Black | BG: Green  | STYLE : NONE
    define('KL_NOTIFY', IPS_LOGMESSAGE + 3);               //Notiy about Changes                 | FG: Black | BG: Blue   | STLYE : NONE
    define('KL_WARNING', IPS_LOGMESSAGE + 4);              //Warnings                            | FG: Black | BG: Yellow | STLYE : NONE
    define('KL_ERROR', IPS_LOGMESSAGE + 5);                //Error Message                       | FG: Black | BG: Red    | STLYE : BOLD
    define('KL_DEBUG', IPS_LOGMESSAGE + 6);                //Debug Informations + Script Results | FG: Grey  | BG: White  | STLYE : NONE
    define('KL_CUSTOM', IPS_LOGMESSAGE + 7);               //User Message                        | FG: Black | BG: White  | STLYE : NONE
// --- MODULE LOADER
    define('IPS_MODULEMESSAGE', IPS_BASE + 300);           //ModuleLoader Message
    define('ML_LOAD', IPS_MODULEMESSAGE + 1);              //Module loaded
    define('ML_UNLOAD', IPS_MODULEMESSAGE + 2);            //Module unloaded
// --- OBJECT MANAGER
    define('IPS_OBJECTMESSAGE', IPS_BASE + 400);
    define('OM_REGISTER', IPS_OBJECTMESSAGE + 1);          //Object was registered
    define('OM_UNREGISTER', IPS_OBJECTMESSAGE + 2);        //Object was unregistered
    define('OM_CHANGEPARENT', IPS_OBJECTMESSAGE + 3);      //Parent was Changed
    define('OM_CHANGENAME', IPS_OBJECTMESSAGE + 4);        //Name was Changed
    define('OM_CHANGEINFO', IPS_OBJECTMESSAGE + 5);        //Info was Changed
    define('OM_CHANGETYPE', IPS_OBJECTMESSAGE + 6);        //Type was Changed
    define('OM_CHANGESUMMARY', IPS_OBJECTMESSAGE + 7);     //Summary was Changed
    define('OM_CHANGEPOSITION', IPS_OBJECTMESSAGE + 8);    //Position was Changed
    define('OM_CHANGEREADONLY', IPS_OBJECTMESSAGE + 9);    //ReadOnly was Changed
    define('OM_CHANGEHIDDEN', IPS_OBJECTMESSAGE + 10);     //Hidden was Changed
    define('OM_CHANGEICON', IPS_OBJECTMESSAGE + 11);       //Icon was Changed
    define('OM_CHILDADDED', IPS_OBJECTMESSAGE + 12);       //Child for Object was added
    define('OM_CHILDREMOVED', IPS_OBJECTMESSAGE + 13);     //Child for Object was removed
    define('OM_CHANGEIDENT', IPS_OBJECTMESSAGE + 14);      //Ident was Changed
// --- INSTANCE MANAGER
    define('IPS_INSTANCEMESSAGE', IPS_BASE + 500);         //Instance Manager Message
    define('IM_CREATE', IPS_INSTANCEMESSAGE + 1);          //Instance created
    define('IM_DELETE', IPS_INSTANCEMESSAGE + 2);          //Instance deleted
    define('IM_CONNECT', IPS_INSTANCEMESSAGE + 3);         //Instance connectged
    define('IM_DISCONNECT', IPS_INSTANCEMESSAGE + 4);      //Instance disconncted
    define('IM_CHANGESTATUS', IPS_INSTANCEMESSAGE + 5);    //Status was Changed
    define('IM_CHANGESETTINGS', IPS_INSTANCEMESSAGE + 6);  //Settings were Changed
    define('IM_CHANGESEARCH', IPS_INSTANCEMESSAGE + 7);    //Searching was started/stopped
    define('IM_SEARCHUPDATE', IPS_INSTANCEMESSAGE + 8);    //Searching found new results
    define('IM_SEARCHPROGRESS', IPS_INSTANCEMESSAGE + 9);  //Searching progress in %
    define('IM_SEARCHCOMPLETE', IPS_INSTANCEMESSAGE + 10); //Searching is complete
// --- VARIABLE MANAGER
    define('IPS_VARIABLEMESSAGE', IPS_BASE + 600);              //Variable Manager Message
    define('VM_CREATE', IPS_VARIABLEMESSAGE + 1);               //Variable Created
    define('VM_DELETE', IPS_VARIABLEMESSAGE + 2);               //Variable Deleted
    define('VM_UPDATE', IPS_VARIABLEMESSAGE + 3);               //On Variable Update
    define('VM_CHANGEPROFILENAME', IPS_VARIABLEMESSAGE + 4);    //On Profile Name Change
    define('VM_CHANGEPROFILEACTION', IPS_VARIABLEMESSAGE + 5);  //On Profile Action Change
// --- SCRIPT MANAGER
    define('IPS_SCRIPTMESSAGE', IPS_BASE + 700);           //Script Manager Message
    define('SM_CREATE', IPS_SCRIPTMESSAGE + 1);            //On Script Create
    define('SM_DELETE', IPS_SCRIPTMESSAGE + 2);            //On Script Delete
    define('SM_CHANGEFILE', IPS_SCRIPTMESSAGE + 3);        //On Script File changed
    define('SM_BROKEN', IPS_SCRIPTMESSAGE + 4);            //Script Broken Status changed
// --- EVENT MANAGER
    define('IPS_EVENTMESSAGE', IPS_BASE + 800);             //Event Scripter Message
    define('EM_CREATE', IPS_EVENTMESSAGE + 1);             //On Event Create
    define('EM_DELETE', IPS_EVENTMESSAGE + 2);             //On Event Delete
    define('EM_UPDATE', IPS_EVENTMESSAGE + 3);
    define('EM_CHANGEACTIVE', IPS_EVENTMESSAGE + 4);
    define('EM_CHANGELIMIT', IPS_EVENTMESSAGE + 5);
    define('EM_CHANGESCRIPT', IPS_EVENTMESSAGE + 6);
    define('EM_CHANGETRIGGER', IPS_EVENTMESSAGE + 7);
    define('EM_CHANGETRIGGERVALUE', IPS_EVENTMESSAGE + 8);
    define('EM_CHANGETRIGGEREXECUTION', IPS_EVENTMESSAGE + 9);
    define('EM_CHANGECYCLIC', IPS_EVENTMESSAGE + 10);
    define('EM_CHANGECYCLICDATEFROM', IPS_EVENTMESSAGE + 11);
    define('EM_CHANGECYCLICDATETO', IPS_EVENTMESSAGE + 12);
    define('EM_CHANGECYCLICTIMEFROM', IPS_EVENTMESSAGE + 13);
    define('EM_CHANGECYCLICTIMETO', IPS_EVENTMESSAGE + 14);
// --- MEDIA MANAGER
    define('IPS_MEDIAMESSAGE', IPS_BASE + 900);           //Media Manager Message
    define('MM_CREATE', IPS_MEDIAMESSAGE + 1);             //On Media Create
    define('MM_DELETE', IPS_MEDIAMESSAGE + 2);             //On Media Delete
    define('MM_CHANGEFILE', IPS_MEDIAMESSAGE + 3);         //On Media File changed
    define('MM_AVAILABLE', IPS_MEDIAMESSAGE + 4);          //Media Available Status changed
    define('MM_UPDATE', IPS_MEDIAMESSAGE + 5);
// --- LINK MANAGER
    define('IPS_LINKMESSAGE', IPS_BASE + 1000);           //Link Manager Message
    define('LM_CREATE', IPS_LINKMESSAGE + 1);             //On Link Create
    define('LM_DELETE', IPS_LINKMESSAGE + 2);             //On Link Delete
    define('LM_CHANGETARGET', IPS_LINKMESSAGE + 3);       //On Link TargetID change
// --- DATA HANDLER
    define('IPS_DATAMESSAGE', IPS_BASE + 1100);             //Data Handler Message
    define('DM_CONNECT', IPS_DATAMESSAGE + 1);             //On Instance Connect
    define('DM_DISCONNECT', IPS_DATAMESSAGE + 2);          //On Instance Disconnect
// --- SCRIPT ENGINE
    define('IPS_ENGINEMESSAGE', IPS_BASE + 1200);           //Script Engine Message
    define('SE_UPDATE', IPS_ENGINEMESSAGE + 1);             //On Library Refresh
    define('SE_EXECUTE', IPS_ENGINEMESSAGE + 2);            //On Script Finished execution
    define('SE_RUNNING', IPS_ENGINEMESSAGE + 3);            //On Script Started execution
// --- PROFILE POOL
    define('IPS_PROFILEMESSAGE', IPS_BASE + 1300);
    define('PM_CREATE', IPS_PROFILEMESSAGE + 1);
    define('PM_DELETE', IPS_PROFILEMESSAGE + 2);
    define('PM_CHANGETEXT', IPS_PROFILEMESSAGE + 3);
    define('PM_CHANGEVALUES', IPS_PROFILEMESSAGE + 4);
    define('PM_CHANGEDIGITS', IPS_PROFILEMESSAGE + 5);
    define('PM_CHANGEICON', IPS_PROFILEMESSAGE + 6);
    define('PM_ASSOCIATIONADDED', IPS_PROFILEMESSAGE + 7);
    define('PM_ASSOCIATIONREMOVED', IPS_PROFILEMESSAGE + 8);
    define('PM_ASSOCIATIONCHANGED', IPS_PROFILEMESSAGE + 9);
// --- TIMER POOL
    define('IPS_TIMERMESSAGE', IPS_BASE + 1400);            //Timer Pool Message
    define('TM_REGISTER', IPS_TIMERMESSAGE + 1);
    define('TM_UNREGISTER', IPS_TIMERMESSAGE + 2);
    define('TM_SETINTERVAL', IPS_TIMERMESSAGE + 3);
    define('TM_UPDATE', IPS_TIMERMESSAGE + 4);
    define('TM_RUNNING', IPS_TIMERMESSAGE + 5);
// --- STATUS CODES
    define('IS_SBASE', 100);
    define('IS_CREATING', IS_SBASE + 1); //module is being created
    define('IS_ACTIVE', IS_SBASE + 2); //module created and running
    define('IS_DELETING', IS_SBASE + 3); //module us being deleted
    define('IS_INACTIVE', IS_SBASE + 4); //module is not beeing used
// --- ERROR CODES
    define('IS_EBASE', 200);          //default errorcode
    define('IS_NOTCREATED', IS_EBASE + 1); //instance could not be created
// --- Search Handling
    define('FOUND_UNKNOWN', 0);     //Undefined value
    define('FOUND_NEW', 1);         //Device is new and not configured yet
    define('FOUND_OLD', 2);         //Device is already configues (InstanceID should be set)
    define('FOUND_CURRENT', 3);     //Device is already configues (InstanceID is from the current/searching Instance)
    define('FOUND_UNSUPPORTED', 4); //Device is not supported by Module

    define('vtBoolean', 0);
    define('vtInteger', 1);
    define('vtFloat', 2);
    define('vtString', 3);
}

class SqueezeboxBattery extends IPSModule
{

    private $Address;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyInteger("Interval", 30);
        $this->RegisterPropertyString("Password", "1234");
        $this->RegisterTimer("RequestState", 0, 'LSQB_RequestState($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Addresse prüfen
        $Address = $this->ReadPropertyString('Address');
        if ($Address == '')
        {
            $this->SetStatus(IS_ACTIVE);
        } else
        {
            if (strpos($Address, '.')) // IP ?
            {
                if ($this->ReadPropertyInteger("Interval") < 30)
                    $this->SetStatus(203);
                else
                    $this->SetStatus(IS_ACTIVE);
            }
        }

        // Profile anlegen
        $this->RegisterProfileIntegerEx("Power.Squeezebox", "Information", "", "", Array(
            Array(0, "offline", "", -1),
            Array(3, "Netzbetrieb", "", -1),
            Array(5, "Akkubetrieb", "", -1),
            Array(7, "Netz- und Akkubetrieb", "", -1)
        ));

        $this->RegisterProfileIntegerEx("Charge.Squeezebox", "Battery", "", "", Array(
            Array(1, "Keine installiert", "", -1),
            Array(2, "Ruhezustand", "", -1),
            Array(3, "wird entladen", "", -1),
            Array(8, "wird geladen", "", -1),
            Array(24, "Ladezyklus pausiert", "", -1),
            Array(35, "Warnung", "", -1)
        ));
        $this->RegisterProfileInteger("mAh.Squeezebox", "Intensity", "", " mAh", 0, 0, 0);


        //Status-Variablen anlegen
        $this->RegisterVariableInteger("State", "Status", "Power.Squeezebox", 1);
        $this->RegisterVariableFloat("SysVoltage", "Gerätespannung", "~Volt", 2);
        $this->RegisterVariableFloat("WallVoltage", "Netzspannung", "~Volt", 3);
        $this->RegisterVariableInteger("ChargeState", "Ladestatus", "Charge.Squeezebox", 4);
        $this->RegisterVariableFloat("BatteryLevel", "Akkuladekapazität", "~Intensity.1", 5);
        $this->RegisterVariableFloat("BatteryTemperature", "Akkutemperatur", "~Temperature", 6);
        $this->RegisterVariableFloat("BatteryVoltage", "Akkuspannung", "~Volt", 7);
        $this->RegisterVariableFloat("BatteryVMon1", "Akku vmon1", "~Volt", 8);
        $this->RegisterVariableFloat("BatteryVMon2", "Akku vmon2", "~Volt", 9);
        $this->RegisterVariableInteger("BatteryCapacity", "Akkukapazität", "mAh.Squeezebox", 10);
//        $this->RegisterVariableInteger("BatteryChargeRate", "Ladezyklen Akku", "", 11);
//        $this->RegisterVariableInteger("BatteryDischargeRate", "Entladezyklen Akku", "", 12);
        if ($this->Init(false))
        {
            if ($this->ReadPropertyInteger("Interval") >= 30)
            {
                $this->SetTimerInterval("RequestState", $this->ReadPropertyInteger("Interval"));
            } else
            {
                $this->SetTimerInterval("RequestState", 0);
            }
            $this->RequestState();
        } else
        {
            $this->SetTimerInterval("RequestState", 0);
        }
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */
################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    /**
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen..
     *
     * @return boolean
     */
    public function RequestState()
    {
        if($this->Init()=== false)
            return false;
        
        set_include_path(__DIR__);
        require_once (__DIR__ . '/Net/SSH2.php');

        $ssh = new Net_SSH2($this->ReadPropertyString("Address"));
        $login = @$ssh->login('root', $this->ReadPropertyString("Password"));
        if ($login == false)
        {
            trigger_error('Could not log in on SqueezeBox',E_USER_NOTICE);
            return false;
        }
        $PowerMode = (int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/power_mode");
        $this->SetValueInteger('State', $PowerMode);
        if ($PowerMode == 5)
            $this->SetValueFloat("WallVoltage", 0);
        else
        {
            $WallVoltage = round((int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/wall_voltage") / 1000, 1);
            $this->SetValueFloat("WallVoltage", $WallVoltage);
        }

        $SysVoltage = round((float) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/sys_voltage") / 1000, 1);
        $this->SetValueFloat('SysVoltage', $SysVoltage);
        $ChargeState = (int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/charger_state");
        $this->SetValueInteger('ChargeState', $ChargeState);
        if ($ChargeState <> 1)
        {
            $BatteryLevel = (int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_charge") / 2000;
            $this->SetValueFloat('BatteryLevel', $BatteryLevel);
            $BatteryCapacity = (int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_capacity");
            $this->SetValueInteger('BatteryCapacity', $BatteryCapacity);
            $BatteryTemperature = round((float) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_temperature") / 32, 1);
            $this->SetValueFloat('BatteryTemperature', $BatteryTemperature);
            $BatteryVoltage = round((float) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_voltage") / 1000, 1);
            $this->SetValueFloat('BatteryVoltage', $BatteryVoltage);
            $BatteryVMon1 = round((float) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_vmon1_voltage") / 1000, 1);
            $this->SetValueFloat('BatteryVMon1', $BatteryVMon1);
            $BatteryVMon2 = round((float) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_vmon2_voltage") / 1000, 1);
            $this->SetValueFloat('BatteryVMon2', $BatteryVMon2);
//var_dump($ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/charger_event"));
//            $BatteryChargeRate = (int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_charge_rate");
//            $this->SetValueInteger('BatteryChargeRate', $BatteryChargeRate);
//            $BatteryDischargeRate = (int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_discharge_rate");
//            $this->SetValueInteger('BatteryDischargeRate', $BatteryDischargeRate);
        } else
        {
            $this->SetValueFloat('BatteryLevel', 0.0);
            $this->SetValueInteger('BatteryCapacity', 0);
            $this->SetValueFloat('BatteryTemperature', 0.0);
            $this->SetValueFloat('BatteryVoltage', 0.0);
            $this->SetValueFloat('BatteryVMon1', 0.0);
            $this->SetValueFloat('BatteryVMon2', 0.0);
//            $this->SetValueInteger('BatteryChargeRate', 0);
//            $this->SetValueInteger('BatteryDischargeRate', 0);
        }




        $ssh->disconnect();

        return true;
    }

################## PRIVATE

    private function Init($throwException = true)
    {
        $this->Address = $this->ReadPropertyString("Address");
        if ($this->Address == '')
        {
            $this->SetStatus(IS_INACTIVE);
            if ($throwException)
            {
                trigger_error('Address not set.', E_USER_NOTICE);
                return false;
            }
            else
                return false;
        }
        return true;
    }

    private function SetValueBoolean($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueBoolean($id) <> $value)
            SetValueBoolean($id, $value);
    }

    private function SetValueFloat($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueFloat($id) <> $value)
            SetValueFloat($id, $value);
    }

    private function SetValueInteger($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueInteger($id) <> $value)
            SetValueInteger($id, $value);
    }

    private function SetValueString($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueString($id) <> $value)
            SetValueString($id, $value);
    }

################## DUMMYS / WOARKAROUNDS - protected
    //Remove on next Symcon update

    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {

        if (!IPS_VariableProfileExists($Name))
        {
            IPS_CreateVariableProfile($Name, 1);
        } else
        {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1)
                throw new Exception("Variable profile type does not match for profile " . $Name, E_USER_WARNING);
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (sizeof($Associations) === 0)
        {
            $MinValue = 0;
            $MaxValue = 0;
        } else
        {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations) - 1][0];
        }

        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association)
        {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    protected function RegisterTimer($Name, $Interval, $Script)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            $id = 0;


        if ($id > 0)
        {
            if (!IPS_EventExists($id))
                throw new Exception("Ident with name " . $Name . " is used for wrong object type", E_USER_WARNING);

            if (IPS_GetEvent($id)['EventType'] <> 1)
            {
                IPS_DeleteEvent($id);
                $id = 0;
            }
        }

        if ($id == 0)
        {
            $id = IPS_CreateEvent(1);
            IPS_SetParent($id, $this->InstanceID);
            IPS_SetIdent($id, $Name);
        }
        IPS_SetName($id, $Name);
        IPS_SetHidden($id, true);
        IPS_SetEventScript($id, $Script);
        if ($Interval > 0)
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);

            IPS_SetEventActive($id, true);
        } else
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);

            IPS_SetEventActive($id, false);
        }
    }

    protected function UnregisterTimer($Name)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id > 0)
        {
            if (!IPS_EventExists($id))
                throw new Exception('Timer not present', E_USER_WARNING);
            IPS_DeleteEvent($id);
        }
    }

    protected function SetTimerInterval($Name, $Interval)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            throw new Exception('Timer not present', E_USER_WARNING);
        if (!IPS_EventExists($id))
            throw new Exception('Timer not present', E_USER_WARNING);

        $Event = IPS_GetEvent($id);

        if ($Interval < 1)
        {
            if ($Event['EventActive'])
                IPS_SetEventActive($id, false);
        }
        else
        {
            if ($Event['CyclicTimeValue'] <> $Interval)
                IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);
            if (!$Event['EventActive'])
                IPS_SetEventActive($id, true);
        }
    }

    protected function SetStatus($InstanceStatus)
    {
        if (IPS_GetKernelRunlevel() == KR_READY)
            $OldStatus = IPS_GetInstance($this->InstanceID)['InstanceStatus'];
        else
            $OldStatus =-1;
        if ($InstanceStatus <> $OldStatus)
            parent::SetStatus($InstanceStatus);
    }

}

?>