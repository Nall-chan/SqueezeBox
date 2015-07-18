<?

class LMSData extends stdClass
{

    const SendCommand = 0;
    const GetData = 1;

    public $Data;
    public $Typ;
    public $needResponse;

    public function __construct($Data, $Typ = LMSData::SendCommand, $needResponse = true)
    {
        $this->Data = $Data;
        $this->Typ = $Typ;
        $this->needResponse = $needResponse;
    }

}

class LSQData extends stdClass
{

    public $Address; //DeviceID
    public $Command;
    public $Value;
    public $needResponse;

    public function __construct($Command, $Value, $needResponse = true)
    {
        $this->Command = $Command;
        $this->Value = $Value;
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

    //commands
    const listen = 'listen';
    const signalstrength = 'signalstrength';
    const name = 'name';
    const connected = 'connected';
    const sleep = 'sleep';
    const sync = 'sync';
    const mode = 'mode';
    const power = 'power';
    const play = 'play';
    const stop = 'stop';
    const pause = 'pause';
    const mixer = 'mixer';
    const show = 'show';
    const display = 'display';
    const linesperscreen = 'linesperscreen';
    const displaynow = 'displaynow';
    const playerpref = 'playerpref';
    const button = 'button';
    const irenable = 'irenable';
    const connect = 'connect';
    const status = 'status';
    const prefset = 'prefset';
    const playlist = 'playlist';
    const client = 'client';
    //mixer
    const volume = 'volume';
    const muting = 'muting';
    const bass = 'bass';
    const treble = 'treble';
    const pitch = 'pitch';
    //playlist oder prefset
    const repeat = 'repeat';
    const shuffle = 'shuffle';

    /*
      play
      stop
      pause
      mode ?
      time
      genre ?
      artist ?
      album ?
      title ?
      duration ?
      remote ?
      current_title ?
      path ?
      playlist play
      playlist add
      playlist insert
      playlist deleteitem
      playlist move
      playlist delete
      playlist preview
      playlist resume
      playlist save
      playlist loadalbum
      playlist addalbum
      playlist loadtracks
      playlist addtracks
      playlist insertalbum
      playlist deletealbum
      playlist clear
      playlist zap
      playlist name ?
      playlist url ?
      playlist modified ?
      playlist playlistsinfo
      playlist index
      playlist genre ?
      playlist artist ?
      playlist album ?
      playlist title ?
      playlist path ?
      playlist remote ?
      playlist duration ?
      playlist tracks ?
      playlist shuffle
      playlist repeat
      playlistcontrol
     */
    /*    private $Commands = array(
      LSQResponse::signalstrength,
      LSQResponse::name,
      LSQResponse::connected,
      LSQResponse::sleep,
      LSQResponse::sync,
      LSQResponse::power,
      LSQResponse::mixer,
      LSQResponse::show,
      LSQResponse::display,
      LSQResponse::linesperscreen,
      LSQResponse::displaynow,
      LSQResponse::playerpref,
      LSQResponse::button,
      LSQResponse::irenable,
      LSQResponse::connect,
      LSQResponse::status);
      private $Mixer = array(
      LSQResponse::volume,
      LSQResponse::muting,
      LSQResponse::bass,
      LSQResponse::treble,
      LSQResponse::pitch);
      private $Playerpref = array(); */

    public $Address;
    public $Command;
    public $Value;
    private $Modus = Array(2 => LSQResponse::stop, 3 => LSQResponse::play, 4 => LSQResponse::pause);

    public function __construct($Data) // LMS->Data
    {
        if ($Data->Device == LMSResponse::isMAC)
            $this->Address = $Data->MAC;
        elseif ($Data->Device == LMSResponse::isIP)
            $this->Address = $Data->IP;
        switch ($Data->Data[0])
        {
            // 0 = Command 1 = Value
            case LSQResponse::signalstrength:
            case LSQResponse::name:
            case LSQResponse::connected:
            case LSQResponse::sleep:
            case LSQResponse::listen:
            case LSQResponse::sync:
            case LSQResponse::power:
            case LSQResponse::linesperscreen:
            case LSQResponse::irenable:
            case LSQResponse::connect:
            case LSQResponse::play:
            case LSQResponse::pause:
            case LSQResponse::stop:
            case LSQResponse::mode:
            case LSQResponse::client:
                $this->Command = $Data->Data[0];
                if (isset($Data->Data[1]))
                    $this->Value = $Data->Data[1];
                break;
            // 0 = Command 1=multiValue
            case LSQResponse::status:
                break;
//        LSQResponse::show,
//        LSQResponse::display,
//        LSQResponse::displaynow,
//        LSQResponse::playerpref
            // 1 = Command 2 = Value             
            case LSQResponse::button:
            case LSQResponse::mixer:
            case LSQResponse::playlist:
                $this->Command = $Data->Data[0] . ' ' . $Data->Data[1];
                if (isset($Data->Data[2]))
                    $this->Value = $Data->Data[2];
                break;
            // 2 = Command 3 = Value             
            case LSQResponse::prefset:
                $this->Command = $Data->Data[2];
                $this->Value = $Data->Data[3];

            default:



                break;
        }
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
          ) */
        // $Data[0] pr?fen
    }

    public function GetModus()
    {

        return (int) array_keys($this->Modus, $this->Value, true);
    }

}

?>