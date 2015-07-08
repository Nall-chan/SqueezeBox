<?
class XBZBDevice extends IPSModule {

    public function __construct($InstanceID) {

        //Never delete this line!
        parent::__construct($InstanceID);
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
	$this->RegisterPropertyString("MACAddress", "");        
    }

    public function ApplyChanges() {
        //Never delete this line!
        parent::ApplyChanges();
        $this->RequireParent("{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}");             
    }

################## PRIVATE     

		public function Send($Text)
		{
			return $this->SendDataToParent(json_encode(Array("DataID" => "{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}", "Buffer" => $Text)));
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
     $ret =   $this->Send();
         IPS_LogMessage("RetSend", $ret);
    }
}

################## DataPoints

    public function ReceiveData($JSONString)
    {
        // CB5950B3-593C-4126-9F0F-8655A3944419 ankommend von Splitter
            $data = json_decode($JSONString);
            IPS_LogMessage("IODevice RECV", utf8_decode($data->Buffer));
            //We would parse our payload here before sending it further...
            //Lets just forward to our children
            //$this->SendDataToChildren(json_encode(Array("DataID" => "{CB5950B3-593C-4126-9F0F-8655A3944419}", "Buffer" => $data->Buffer)));
    }

################## DUMMYS / WOARKAROUNDS - protected

    protected function HasActiveParent()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //          
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
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