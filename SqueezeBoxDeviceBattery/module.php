<?

require_once(__DIR__ . "/../SSH2.php");  // diverse Klassen

class SqueezeboxBattery extends IPSModule
{

    private $Address, $Interval;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyInteger("Interval", 5);
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
                if (ReadPropertyInteger("Interval")<5)
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

        $this->RegisterProfileIntegerEx("Charge.Squeezebox", "Information", "", "", Array(
            Array(1, "Keine installiert", "", -1),
            Array(2, "Ruhezustand", "", -1),
            Array(3, "wird entladen", "", -1),
            Array(8, "wird geladen", "", -1),
            Array(24, "Ladezyklus pausiert", "", -1),
            Array(35, "Warnung", "", -1)
        ));
        $this->RegisterProfileInteger("Intensity.Squeezebox", "Intensity", "", " %", 0, 100, 1);

        //Status-Variablen anlegen
        $this->RegisterVariableInteger("State", "Status", "Power.Squeezebox", 1);
        $this->RegisterVariableFloat("WallVoltage", "Netzspannung", "~Voltage", 2);
        $this->RegisterVariableInteger("ChargeState", "Ladestatus", "Charge.Squeezebox", 2);
        $this->RegisterVariableInteger("BatteryLevel", "Akkukapazität", "Intensity.Squeezebox", 3); // float?
        $this->RegisterVariableFloat("BatteryTemperature", "Akkutemperatur", "~Temperature", 4);
        $this->RegisterVariableFloat("BatteryVoltage", "Akkuspannung", "~Voltage", 5);
        $this->RegisterVariableFloat("BatteryVMon1", "Akku vmon1", "~Voltage", 6);
        $this->RegisterVariableFloat("BatteryVMon2", "Akku vmon2", "~Voltage", 7);        
        if ($this->Init(false))
        {
            if ($this->ReadPropertyInteger("Interval") >= 5)
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
//SSH Login
//include('Net/SSH2.php');
        $ssh = new Net_SSH2($this->ReadPropertyString("Address"));
        if (!$ssh->login('root', $this->ReadPropertyString("Password")))
        {
            return false;
        }
//SSH Ende
        $ssh->exec("cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_charge"); //Befehl der auf dem Mac ausgeführt //werden soll.
        IPS_LogMessage("Batterie",$ssh->read());
        $ssh->disconnect();

//        return true;
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

}

?>