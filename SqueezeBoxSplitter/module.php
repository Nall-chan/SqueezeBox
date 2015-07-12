<?

class LMSSplitter extends IPSModule
{

    public function __construct($InstanceID)
    {

        //Never delete this line!
        parent::__construct($InstanceID);
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString("Host", "");
        $this->RegisterPropertyBoolean("Open", true);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $change = false;
        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            if (IPS_GetProperty($ParentID, 'Host') <> $this->ReadPropertyString('Host'))
            {
                IPS_SetProperty($ParentID, 'Host', $this->ReadPropertyString('Host'));
                $change = true;
            }
            if (IPS_GetProperty($ParentID, 'Port') <> 9090)
            {
                IPS_SetProperty($ParentID, 'Port', 9090);
                $change = true;
            }
            if (IPS_GetProperty($ParentID, 'Open') <> $this->ReadPropertyBoolean('Open'))
            {
                IPS_SetProperty($ParentID, 'Open', $this->ReadPropertyBoolean('Open'));
                $change = true;
            }

            if ($change)
                @IPS_ApplyChanges($ParentID);
        }
    }

################## PRIVATE     

    private function encode($raw)
    {
        $array = explode(' ', $raw); // Antwortstring in Array umwandeln
        $Data = new stdClass();
        $Data->MAC = $this->GetMAC(urldecode($array[0])); // MAC in lesbares Format umwandeln und als BINAY speichern
        $Data->Payload = $array;
        return $Data;
    }

    private function GetMAC($mac)
    {
        return $this->MAC = strtoupper(str_replace(array("-", ":"), "", $mac));
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function Send($Text)
    {
        $this->SendDataToParent($Text);
    }

################## DataPoints

    public function ForwardData($JSONString)
    {
        //EDD ankommend von Device
        $data = json_decode($JSONString);
        IPS_LogMessage("IOSplitter FRWD MAC", $data->MAC);        
        IPS_LogMessage("IOSplitter FRWD Payload", $data->Payload);
        
        $sendData = urlencode(implode(":",$mac = str_split($data->MAC, 2)))." ".$data->Payload.chr(0x0d);
        // Daten annehmen und mit MAC codieren. Senden an Parent
        
        // 
        //We would package our payload here before sending it further...
        //weiter zu IO per ClientSocket        
        return $this->SendDataToParent($sendData);
    }

    public function ReceiveData($JSONString)
    {
        // 018EF6B5-AB94-40C6-AA53-46943E824ACF ankommend von IO
        $data = json_decode($JSONString);
        IPS_LogMessage("IOSplitter RECV", utf8_decode($data->Buffer));
        //We would parse our payload here before sending it further...
        //Lets just forward to our children
        $packet = explode(chr(0x0d), $data->Buffer);
        foreach ($packet as $part)
        {
            if ($part == '')
                continue;
            $encoded = $this->encode($part);
            IPS_LogMessage("IOSplitter encoded", utf8_decode(print_r($encoded, 1)));
            if ($encoded->MAC <> "listen")
            {
                $ret = $this->SendDataToChildren(json_encode(Array("DataID" => "{CB5950B3-593C-4126-9F0F-8655A3944419}", "MAC" => $encoded->MAC, "Payload" => $encoded->Payload)));
                IPS_LogMessage("IOSplitter ReturnValue", print_r($ret, 1));
            }
        }
    }

    protected function SendDataToParent($Data)
    {
        //Semaphore püfen
        // setzen
        // senden
        return IPS_SendDataToParent($this->InstanceID, json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $Data)));
        
        // Rückgabe speichern 
        // Semaphore velassen
        // Rückgabe auswerten auf Fehler ?
        // Rückgabe zurückgeben.        
    }

    protected function SendDataToChildren($Data)
    {
        return IPS_SendDataToChildren($this->InstanceID, $Data);
    }

################## DUMMYS / WOARKAROUNDS - protected

    protected function GetParent()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //          
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    protected function HasActiveParent($ParentID)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //          
        if ($ParentID > 0)
        {
            $parent = IPS_GetInstance($ParentID);
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

}

?>