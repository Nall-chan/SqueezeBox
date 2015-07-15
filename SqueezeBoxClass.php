<?

class LMSData extends stdClass
{

    const SendCommand = 0;
    const GetData = 1;

    public $Data;
    public $Typ;
    public $needResponse;

    public function __construct($Data, $Typ = LMSCommand::SendCommand, $needResponse = true)
    {
        $this->Data = $Data;
        $this->Typ = $Typ;
        $this->needResponse = $needResponse;
    }

}

class LMSResponse extends stdClass
{

    const isServer = 0;
    const isMAC = 1;
    const isIP = 2;

    public $Device;
    public $MAC;
    public $IP;
    public $Data;

    public function __construct($Data)
    {
        $array = explode(' ', $Data); // Antwortstring in Array umwandeln
        if (strpos($array[0], '%')) //isMAC
        {
            $this->Device = LMSResponse::isMAC;
            $this->MAC = urldecode(array_shift($array));
        }
        elseif (strpos($array[0], '.')) //isIP
        {
            $this->Device = LMSResponse::isIP;
            $this->IP = array_shift($array);
        }
        else // isServer
        {
            $this->Device = LMSResponse::isServer;
        }
        $this->Data = $array;
    }

}

class LSQResponse extends stdClass
{
    public function __construct($Data) // LMS->Data
    {
        $Commands = array('signalstrength','name','connected','sleep','sync','syncgroups','power','mixer','show','display','linesperscreen','displaynow',
            'playerpref','button','irenable','connect');
        $Mixer = array ('volume','muting','bass',' treble','pitch');
        $Playerpref = array();
    /*
     * [LMS] => stdClass Object
        (
            [Device] => 1
            [MAC] => 00:04:20:2b:9d:ae
            [IP] => 
     * AB HIER
            [Data] => Array
                (
                    [0] => mixer
                    [1] => muting
                    [2] => toggle
                    [3] => seq_no%3A1332
                )
        )*/        
      // $Data[0] prüfen
    }
}
?>