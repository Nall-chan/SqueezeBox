<?

/*
 * @addtogroup squeezebox
 * @{
 *
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.1
 *
 */


if (!defined("IPS_BASE"))
{
// --- BASE MESSAGE
    define('IPS_BASE', 10000);                             //Base Message
    define('IPS_KERNELSTARTED', IPS_BASE + 1);             //Post Ready Message
    define('IPS_KERNELSHUTDOWN', IPS_BASE + 2);            //Pre Shutdown Message, Runlevel UNINIT Follows
}
if (!defined("IPS_KERNELMESSAGE"))
{
// --- KERNEL
    define('IPS_KERNELMESSAGE', IPS_BASE + 100);           //Kernel Message
    define('KR_CREATE', IPS_KERNELMESSAGE + 1);            //Kernel is beeing created
    define('KR_INIT', IPS_KERNELMESSAGE + 2);              //Kernel Components are beeing initialised, Modules loaded, Settings read
    define('KR_READY', IPS_KERNELMESSAGE + 3);             //Kernel is ready and running
    define('KR_UNINIT', IPS_KERNELMESSAGE + 4);            //Got Shutdown Message, unloading all stuff
    define('KR_SHUTDOWN', IPS_KERNELMESSAGE + 5);          //Uninit Complete, Destroying Kernel Inteface
}
if (!defined("IPS_LOGMESSAGE"))
{
// --- KERNEL LOGMESSAGE
    define('IPS_LOGMESSAGE', IPS_BASE + 200);              //Logmessage Message
    define('KL_MESSAGE', IPS_LOGMESSAGE + 1);              //Normal Message                      | FG: Black | BG: White  | STLYE : NONE
    define('KL_SUCCESS', IPS_LOGMESSAGE + 2);              //Success Message                     | FG: Black | BG: Green  | STYLE : NONE
    define('KL_NOTIFY', IPS_LOGMESSAGE + 3);               //Notiy about Changes                 | FG: Black | BG: Blue   | STLYE : NONE
    define('KL_WARNING', IPS_LOGMESSAGE + 4);              //Warnings                            | FG: Black | BG: Yellow | STLYE : NONE
    define('KL_ERROR', IPS_LOGMESSAGE + 5);                //Error Message                       | FG: Black | BG: Red    | STLYE : BOLD
    define('KL_DEBUG', IPS_LOGMESSAGE + 6);                //Debug Informations + Script Results | FG: Grey  | BG: White  | STLYE : NONE
    define('KL_CUSTOM', IPS_LOGMESSAGE + 7);               //User Message                        | FG: Black | BG: White  | STLYE : NONE
}
if (!defined("IPS_MODULEMESSAGE"))
{
// --- MODULE LOADER
    define('IPS_MODULEMESSAGE', IPS_BASE + 300);           //ModuleLoader Message
    define('ML_LOAD', IPS_MODULEMESSAGE + 1);              //Module loaded
    define('ML_UNLOAD', IPS_MODULEMESSAGE + 2);            //Module unloaded
}
if (!defined("IPS_OBJECTMESSAGE"))
{
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
}
if (!defined("IPS_INSTANCEMESSAGE"))
{
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
}
if (!defined("IPS_VARIABLEMESSAGE"))
{
// --- VARIABLE MANAGER
    define('IPS_VARIABLEMESSAGE', IPS_BASE + 600);              //Variable Manager Message
    define('VM_CREATE', IPS_VARIABLEMESSAGE + 1);               //Variable Created
    define('VM_DELETE', IPS_VARIABLEMESSAGE + 2);               //Variable Deleted
    define('VM_UPDATE', IPS_VARIABLEMESSAGE + 3);               //On Variable Update
    define('VM_CHANGEPROFILENAME', IPS_VARIABLEMESSAGE + 4);    //On Profile Name Change
    define('VM_CHANGEPROFILEACTION', IPS_VARIABLEMESSAGE + 5);  //On Profile Action Change
}
if (!defined("IPS_SCRIPTMESSAGE"))
{
// --- SCRIPT MANAGER
    define('IPS_SCRIPTMESSAGE', IPS_BASE + 700);           //Script Manager Message
    define('SM_CREATE', IPS_SCRIPTMESSAGE + 1);            //On Script Create
    define('SM_DELETE', IPS_SCRIPTMESSAGE + 2);            //On Script Delete
    define('SM_CHANGEFILE', IPS_SCRIPTMESSAGE + 3);        //On Script File changed
    define('SM_BROKEN', IPS_SCRIPTMESSAGE + 4);            //Script Broken Status changed
}
if (!defined("IPS_EVENTMESSAGE"))
{
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
}
if (!defined("IPS_MEDIAMESSAGE"))
{
// --- MEDIA MANAGER
    define('IPS_MEDIAMESSAGE', IPS_BASE + 900);           //Media Manager Message
    define('MM_CREATE', IPS_MEDIAMESSAGE + 1);             //On Media Create
    define('MM_DELETE', IPS_MEDIAMESSAGE + 2);             //On Media Delete
    define('MM_CHANGEFILE', IPS_MEDIAMESSAGE + 3);         //On Media File changed
    define('MM_AVAILABLE', IPS_MEDIAMESSAGE + 4);          //Media Available Status changed
    define('MM_UPDATE', IPS_MEDIAMESSAGE + 5);
}
if (!defined("IPS_LINKMESSAGE"))
{
// --- LINK MANAGER
    define('IPS_LINKMESSAGE', IPS_BASE + 1000);           //Link Manager Message
    define('LM_CREATE', IPS_LINKMESSAGE + 1);             //On Link Create
    define('LM_DELETE', IPS_LINKMESSAGE + 2);             //On Link Delete
    define('LM_CHANGETARGET', IPS_LINKMESSAGE + 3);       //On Link TargetID change
}
if (!defined("IPS_FLOWMESSAGE"))
{
// --- DATA HANDLER
    define('IPS_FLOWMESSAGE', IPS_BASE + 1100);             //Data Handler Message
    define('FM_CONNECT', IPS_FLOWMESSAGE + 1);             //On Instance Connect
    define('FM_DISCONNECT', IPS_FLOWMESSAGE + 2);          //On Instance Disconnect
}
if (!defined("IPS_ENGINEMESSAGE"))
{
// --- SCRIPT ENGINE
    define('IPS_ENGINEMESSAGE', IPS_BASE + 1200);           //Script Engine Message
    define('SE_UPDATE', IPS_ENGINEMESSAGE + 1);             //On Library Refresh
    define('SE_EXECUTE', IPS_ENGINEMESSAGE + 2);            //On Script Finished execution
    define('SE_RUNNING', IPS_ENGINEMESSAGE + 3);            //On Script Started execution
}
if (!defined("IPS_PROFILEMESSAGE"))
{
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
}
if (!defined("IPS_TIMERMESSAGE"))
{
// --- TIMER POOL
    define('IPS_TIMERMESSAGE', IPS_BASE + 1400);            //Timer Pool Message
    define('TM_REGISTER', IPS_TIMERMESSAGE + 1);
    define('TM_UNREGISTER', IPS_TIMERMESSAGE + 2);
    define('TM_SETINTERVAL', IPS_TIMERMESSAGE + 3);
    define('TM_UPDATE', IPS_TIMERMESSAGE + 4);
    define('TM_RUNNING', IPS_TIMERMESSAGE + 5);
}

if (!defined("IS_ACTIVE")) //Nur wenn Konstanten noch nicht bekannt sind.
{
// --- STATUS CODES
    define('IS_SBASE', 100);
    define('IS_CREATING', IS_SBASE + 1); //module is being created
    define('IS_ACTIVE', IS_SBASE + 2); //module created and running
    define('IS_DELETING', IS_SBASE + 3); //module us being deleted
    define('IS_INACTIVE', IS_SBASE + 4); //module is not beeing used
// --- ERROR CODES
    define('IS_EBASE', 200);          //default errorcode
    define('IS_NOTCREATED', IS_EBASE + 1); //instance could not be created
}

if (!defined("vtBoolean")) //Nur wenn Konstanten noch nicht bekannt sind.
{
    define('vtBoolean', 0);
    define('vtInteger', 1);
    define('vtFloat', 2);
    define('vtString', 3);
}

/**
 * SqueezeboxBattery Klasse für die Stromversorgung einer SqueezeBox als Instanz in IPS.
 * Erweitert IPSModule.
 *
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2017 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.0
 * @example <b>Ohne</b>
 */
class SqueezeboxBattery extends IPSModule
{

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyInteger("Interval", 30);
        $this->RegisterPropertyString("Password", "1234");
        $this->RegisterTimer("RequestState", 0, 'LSQB_RequestState($_IPS[\'TARGET\']);');
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Profile anlegen
        $this->RegisterProfileIntegerEx("LSQB.Power", "Information", "", "", Array(
            Array(0, $this->Translate("offline"), "", -1),
            Array(3, $this->Translate("on main"), "", -1),
            Array(5, $this->Translate("on battery"), "", -1),
            Array(7, $this->Translate("on main and battery"), "", -1)
        ));

        $this->RegisterProfileIntegerEx("LSQB.Charge", "Battery", "", "", Array(
            Array(1, $this->Translate("not installed"), "", -1),
            Array(2, $this->Translate("standby"), "", -1),
            Array(3, $this->Translate("discharging"), "", -1),
            Array(8, $this->Translate("charging"), "", -1),
            Array(24, $this->Translate("load cycle wait state"), "", -1),
            Array(35, $this->Translate("warning"), "", -1)
        ));

        $this->RegisterProfileInteger("LSQB.mAh", "Intensity", "", " mAh", 0, 0, 0);

        //Status-Variablen anlegen
        $vid = $this->RegisterVariableInteger("State", $this->Translate("State"), "LSQB.Power", 1);
        if (IPS_GetVariable($vid)['VariableCustomProfile'] == "Power.Squeezebox")
            IPS_SetVariableCustomProfile($vid, '');

        $this->RegisterVariableFloat("SysVoltage", $this->Translate("Device voltage"), "~Volt", 2);
        $this->RegisterVariableFloat("WallVoltage", $this->Translate("Line voltage"), "~Volt", 3);
        $vid = $this->RegisterVariableInteger("ChargeState", $this->Translate("Charge state"), "LSQB.Charge", 4);
        if (IPS_GetVariable($vid)['VariableCustomProfile'] == "Charge.Squeezebox")
            IPS_SetVariableCustomProfile($vid, '');
        $this->RegisterVariableFloat("BatteryLevel", $this->Translate("Battery level"), "~Intensity.1", 5);
        $this->RegisterVariableFloat("BatteryTemperature", $this->Translate("Battery temperature"), "~Temperature", 6);
        $this->RegisterVariableFloat("BatteryVoltage", $this->Translate("Battery voltage total"), "~Volt", 7);
        $this->RegisterVariableFloat("BatteryVMon1", $this->Translate("Battery voltage 1"), "~Volt", 8);
        $this->RegisterVariableFloat("BatteryVMon2", $this->Translate("Battery voltage 2"), "~Volt", 9);
        $vid = $this->RegisterVariableInteger("BatteryCapacity", $this->Translate("Battery capacity"), "LSQB.mAh", 10);

        if (IPS_GetVariable($vid)['VariableCustomProfile'] == "mAh.Squeezebox")
            IPS_SetVariableCustomProfile($vid, '');

        if (IPS_VariableProfileExists('Power.Squeezebox'))
            IPS_DeleteVariableProfile('Power.Squeezebox');

        if (IPS_VariableProfileExists('Charge.Squeezebox'))
            IPS_DeleteVariableProfile('Charge.Squeezebox');

        if (IPS_VariableProfileExists('mAh.Squeezebox'))
            IPS_DeleteVariableProfile('mAh.Squeezebox');

        // Addresse prüfen
        $Address = $this->ReadPropertyString('Address');
        if (trim($Address) == '')
        {
            $this->SetStatus(IS_INACTIVE);
            $this->SetTimerInterval("RequestState", 0);
            return;
        }


        if ($this->ReadPropertyInteger("Interval") >= 30)
        {
            $this->SetStatus(IS_ACTIVE);
            $this->SetTimerInterval("RequestState", $this->ReadPropertyInteger("Interval") * 1000);
        }
        else
        {
            if ($this->ReadPropertyInteger("Interval") == 0)
                $this->SetStatus(IS_ACTIVE);
            else
                $this->SetStatus(203);

            $this->SetTimerInterval("RequestState", 0);
        }
        $this->RequestState();
    }

################## PUBLIC

    /**
     * IPS-Instanz-Funktion 'LSQB_RequestState'.
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen.
     *
     * @return bool True wenn erfolgreich.
     */
    public function RequestState()
    {
        $Address = trim($this->ReadPropertyString("Address"));
        if ($Address == '')
            return false;
        set_include_path(__DIR__ . '/libs');
        require_once (__DIR__ . '/libs/Net/SSH2.php');

        $ssh = new Net_SSH2($this->ReadPropertyString("Address"));
        $login = @$ssh->login('root', $this->ReadPropertyString("Password"));
        if ($login == false)
        {
            echo $this->Translate('Login failed.');
            $this->SendDebug('Login', 'ERROR', 0);
            return false;
        }
        $this->SendDebug('Login', $login, 0);

        $PowerMode = (int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/power_mode");
        $this->SendDebug('PowerMode', $PowerMode, 0);
        $this->SetValueInteger('State', $PowerMode);
        if ($PowerMode == 5)
            $this->SetValueFloat("WallVoltage", 0);
        else
        {
            $WallVoltage = round((int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/wall_voltage") / 1000, 1);
            $this->SetValueFloat("WallVoltage", $WallVoltage);
        }

        $SysVoltage = round((float) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/sys_voltage") / 1000, 1);
        $this->SendDebug('SysVoltage', $SysVoltage, 0);
        $this->SetValueFloat('SysVoltage', $SysVoltage);
        $ChargeState = (int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/charger_state");
        $this->SendDebug('ChargeState', $ChargeState, 0);
        $this->SetValueInteger('ChargeState', $ChargeState);
        if ($ChargeState <> 1)
        {
            $BatteryLevel = (int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_charge") / 2000;
            $this->SendDebug('BatteryLevel', $BatteryLevel, 0);
            $this->SetValueFloat('BatteryLevel', $BatteryLevel);
            $BatteryCapacity = (int) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_capacity");
            $this->SendDebug('BatteryCapacity', $BatteryCapacity, 0);
            $this->SetValueInteger('BatteryCapacity', $BatteryCapacity);
            $BatteryTemperature = round((float) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_temperature") / 32, 1);
            $this->SendDebug('BatteryTemperature', $BatteryTemperature, 0);
            $this->SetValueFloat('BatteryTemperature', $BatteryTemperature);
            $BatteryVoltage = round((float) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_voltage") / 1000, 1);
            $this->SendDebug('BatteryVoltage', $BatteryVoltage, 0);
            $this->SetValueFloat('BatteryVoltage', $BatteryVoltage);
            $BatteryVMon1 = round((float) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_vmon1_voltage") / 1000, 1);
            $this->SendDebug('BatteryVMon1', $BatteryVMon1, 0);
            $this->SetValueFloat('BatteryVMon1', $BatteryVMon1);
            $BatteryVMon2 = round((float) $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_vmon2_voltage") / 1000, 1);
            $this->SendDebug('BatteryVMon2', $BatteryVMon2, 0);
            $this->SetValueFloat('BatteryVMon2', $BatteryVMon2);
        }
        else
        {
            $this->SetValueFloat('BatteryLevel', 0.0);
            $this->SetValueInteger('BatteryCapacity', 0);
            $this->SetValueFloat('BatteryTemperature', 0.0);
            $this->SetValueFloat('BatteryVoltage', 0.0);
            $this->SetValueFloat('BatteryVMon1', 0.0);
            $this->SetValueFloat('BatteryVMon2', 0.0);
        }

        $ssh->disconnect();
        $this->SendDebug('Disconnect', '', 0);
        return true;
    }

################## PRIVATE

    /**
     * Setzte eine IPS-Variable vom Typ float auf den Wert von $value.
     *
     * @access private
     * @param string $Ident Ident der Statusvariable.
     * @param float $value Neuer Wert der Statusvariable.
     */
    private function SetValueFloat($Ident, $value)
    {
        $id = @$this->GetIDForIdent($Ident);
        if ($id > 0)
            SetValueFloat($id, $value);
    }

    /**
     * Setzte eine IPS-Variable vom Typ integer auf den Wert von $value.
     *
     * @access private
     * @param string $Ident Ident der Statusvariable.
     * @param int $value Neuer Wert der Statusvariable.
     */
    private function SetValueInteger($Ident, $value)
    {
        $id = @$this->GetIDForIdent($Ident);
        if ($id > 0)
            SetValueInteger($id, $value);
    }

################## DUMMYS / WOARKAROUNDS - protected

    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ integer
     *
     * @access protected
     * @param string $Name Name des Profils.
     * @param string $Icon Name des Icon.
     * @param string $Prefix Prefix für die Darstellung.
     * @param string $Suffix Suffix für die Darstellung.
     * @param int $MinValue Minimaler Wert.
     * @param int $MaxValue Maximaler wert.
     * @param int $StepSize Schrittweite
     */
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {

        if (!IPS_VariableProfileExists($Name))
        {
            IPS_CreateVariableProfile($Name, 1);
        }
        else
        {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1)
                throw new Exception("Variable profile type does not match for profile " . $Name, E_USER_WARNING);
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ integer mit Assoziationen
     *
     * @access protected
     * @param string $Name Name des Profils.
     * @param string $Icon Name des Icon.
     * @param string $Prefix Prefix für die Darstellung.
     * @param string $Suffix Suffix für die Darstellung.
     * @param array $Associations Assoziationen der Werte als Array.
     */
    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (sizeof($Associations) === 0)
        {
            $MinValue = 0;
            $MaxValue = 0;
        }
        else
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

}

/** @} */