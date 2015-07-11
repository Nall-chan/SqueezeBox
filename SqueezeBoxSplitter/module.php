<?
class LMSSplitter extends IPSModule {

    public function __construct($InstanceID) {

        //Never delete this line!
        parent::__construct($InstanceID);
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
	$this->RegisterPropertyString("Host", "");
        
    }

    public function ApplyChanges() {
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
            if ($change) @IPS_ApplyChanges ($ParentID);
          
        }
    }

################## PRIVATE     

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

################## DataPoints
    public function ForwardData($JSONString)
    {
        //EDD ankommend von Device
            $data = json_decode($JSONString);
            IPS_LogMessage("IOSplitter FRWD", utf8_decode($data->Buffer));
            //We would package our payload here before sending it further...
            //$this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $data->Buffer)));
        //weiter zu IO per ClientSocket
            
            $this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $data->Buffer)));
            
    }

    public function ReceiveData($JSONString)
    {
        // 018EF6B5-AB94-40C6-AA53-46943E824ACF ankommend von IO
            $data = json_decode($JSONString);
            IPS_LogMessage("IOSplitter RECV", utf8_decode($data->Buffer));
            //We would parse our payload here before sending it further...
            //Lets just forward to our children
            $this->SendDataToChildren(json_encode(Array("DataID" => "{CB5950B3-593C-4126-9F0F-8655A3944419}", "Buffer" => $data->Buffer)));
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
            if ($parent['InstanceStatus'] == IS_ACTIVE)
                return true;
        }
        return false;
    }

    protected function SetStatus($data) {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function RegisterTimer($data, $cata) {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function SetTimerInterval($data, $cata) {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
    }

    protected function LogMessage($data, $cata) {
        
    }

    protected function SetSummary($data) {
        IPS_LogMessage(__CLASS__, __FUNCTION__ . "Data:" . $data); //                   
    }

}

?>