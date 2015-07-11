<?

class SqueezeboxDevice extends IPSModule
{

    protected $MAC;

    public function __construct($InstanceID)
    {

        //Never delete this line!
        parent::__construct($InstanceID);
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString("MACAddress", "");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->RequireParent("{61051B08-5B92-472B-AFB2-6D971D9B99EE}");
        $this->RegisterProfileIntegerEx("Status.Squeezebox", "Information", "", "", Array(
            Array(0, "Prev", "", -1),
            Array(1, "Stop", "", -1),
            Array(2, "Play", "", -1),
            Array(3, "Pause", "", -1),
            Array(4, "Next", "", -1)
        ));
        $this->RegisterProfileIntegerEx("Shuffle.Squeezebox", "Shuffle", "", "", Array(
            Array(0, "off", "", -1),
            Array(1, "Title", "", -1),
            Array(2, "Album", "", -1)
        ));
        $this->RegisterProfileIntegerEx("Repeat.Squeezebox", "Repeat", "", "", Array(
            Array(0, "off", "", -1),
            Array(1, "Title", "", -1),
            Array(2, "Album", "", -1)
        ));
        $this->RegisterProfileInteger("Volume.Squeezebox", "Intensity", "", " %", 0, 100, 1);
        $this->RegisterVariableBoolean("Power", "Power", "~Switch");
        $this->EnableAction("Power");
        $this->RegisterVariableInteger("Status", "Status", "Status.Squeezebox");
        $this->EnableAction("Status");
        $this->RegisterVariableInteger("Volume", "Volume", "Volume.Squeezebox");
        $this->EnableAction("Volume");
        $this->RegisterVariableInteger("Shuffle", "Shuffle", "Shuffle.Squeezebox");
        $this->EnableAction("Shuffle");
        $this->RegisterVariableInteger("Repeat", "Shuffle", "Repeat.Squeezebox");
        $this->EnableAction("Repeat");
        $this->MAC = $this->GetMAC($this->ReadPropertyString('MACAddress'));
        if ($this->MAC === false)
        {
            IPS_LogMessage("IODevice ApplyChanges", "Invalid MAC".$this->ReadPropertyString('MACAddress'));                        
        }
    }

################## PRIVATE     

    public function Send($Text)
    {
        return IPS_SendDataToParent($this->InstanceID, json_encode(Array("DataID" => "{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}", "Buffer" => $Text)));
    }
    private function GetMAC($mac)
    {
        return $this->MAC = @hex2bin(str_replace(array("-",":"), "", $mac)); 
    }
################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function RequestState()
    {
        if ($this->HasActiveParent())
        {
            $ret = $this->Send();
            IPS_LogMessage("RetSend", $ret);
        }
    }

################## DataPoints

    public function ReceiveData($JSONString)
    {
        // CB5950B3-593C-4126-9F0F-8655A3944419 ankommend von Splitter
        $data = json_decode($JSONString);
        $this->MAC = $this->GetMAC($this->ReadPropertyString('MACAddress'));
        if ($this->MAC === false) return false;
        IPS_LogMessage("IODevice MAC", $data->MAC);
        IPS_LogMessage("IODevice MAC", $this->MAC);        
        if ($this->MAC == $data->MAC)
        {
            IPS_LogMessage("IODevice DATA", print_r($data->Payload, 1));            
            return true;
        } else
            return false;
        //We would parse our payload here before sending it further...
    }

################## DUMMYS / WOARKAROUNDS - protected

    protected function HasActiveParent()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //          
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            if ($parent['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }

    protected function SetStatus($data)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function RegisterTimer($data, $cata)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function SetTimerInterval($data, $cata)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function LogMessage($data, $cata)
    {
        
    }

    protected function SetSummary($data)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__ . "Data:" . $data); //                   
    }

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