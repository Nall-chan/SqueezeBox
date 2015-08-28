<?

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
            $this->SetStatus(104);
        }
        else
        {
            if (strpos($Address, '.')) // IP ?
            {
                $this->SetStatus(102);
                if ($this->ReadPropertyInteger("Interval") < 30)
                    $this->SetStatus(203);
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
            }
            else
            {
                $this->SetTimerInterval("RequestState", 0);
            }
            $this->RequestState();
        }
        else
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
        $this->Init();
        set_include_path(__DIR__);
        require_once (__DIR__ . '/Net/SSH2.php');

        $ssh = new Net_SSH2($this->ReadPropertyString("Address"));
        if (!$ssh->login('root', $this->ReadPropertyString("Password")))
        {
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
        }
        else
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
            $this->SetStatus(202);
            if ($throwException)
                throw new Exception('Address not set.');
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
        }
        else
        {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1)
                throw new Exception("Variable profile type does not match for profile " . $Name);
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

    protected function RegisterTimer($Name, $Interval, $Script)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
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
        }
        else
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);

            IPS_SetEventActive($id, false);
        }
    }

    protected function SetTimerInterval($Name, $Interval)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            throw new Exception('Timer not present');
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
        if ($InstanceStatus <> IPS_GetInstance($this->InstanceID)['InstanceStatus'])
            parent::SetStatus($InstanceStatus);
    }

}

?>