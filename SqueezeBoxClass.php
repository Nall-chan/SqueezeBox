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

// Klasse mit Daten zum SENDEN an den LMS
class LMSData extends stdClass
{

//    const SendCommand = 0;
//    const GetData = 1;

    public $Data;
    public $Typ;
    public $needResponse;

    public function __construct($Command, $Data = '', /* $Typ = LMSData::SendCommand, */ $needResponse = true)
    {
        $this->Command = $Command;
        $this->Data = $Data;
//        $this->Typ = $Typ;
        $this->needResponse = $needResponse;
    }

}

// Klasse mit Daten zum SENDEN an ein Device ÜBER den LMS-Splitter
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

class LMSTaggingData extends stdClass
{

    public function __construct($TaggedDataLine)
    {
        foreach (explode(' ', $TaggedDataLine) as $Line)
        {
            $Data = new LSQTaggingData($Line, false);
            $this->{$Data->Command} = rawurldecode($Data->Value);
        }
    }

}

// Klasse mit den Empfangenen Daten vom LMS
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
        if (strpos($array[0], '%3A') == 2) //isMAC
        {
            $this->Device = LMSResponse::isMAC;
            $this->MAC = rawurldecode(array_shift($array));
        } elseif (strpos($array[0], '.')) //isIP
        {
            $this->Device = LMSResponse::isIP;
            $this->IP = array_shift($array);
        } else // isServer
        {
            $this->Device = LMSResponse::isServer;
        }
        foreach ($array as $Part)
        {
            $this->Data[] = utf8_encode($Part);
        }
    }

}

class LSQButton extends stdClass
{

    const power = 'power';
    const voldown = 'voldown';
    const volup = 'volup';
    const preset_1 = 'preset_1.single';
    const preset_2 = 'preset_2.single';
    const preset_3 = 'preset_3.single';
    const preset_4 = 'preset_4.single';
    const preset_5 = 'preset_5.single';
    const preset_6 = 'preset_6.single';
    const jump_rew = 'jump_rew';
    const jump_fwd = 'jump_fwd';

}

// Klasse mit einem Teil der Empfangenen Daten von einem LSQResponse
class LSQEvent extends stdClass
{

    public $Command;
    public $Value;
    public $isResponse = false;

    public function __construct($Command, $Value, $isResponse)
    {
        $this->Command = $Command;
        $this->Value = $Value;
        $this->isResponse = $isResponse;
    }

}

class LSQTaggingData extends LSQEvent
{

    public function __construct($Data, $isResponse)
    {
        $Part = explode('%3A', $Data); //        
        $Command = rawurldecode(array_shift($Part));
        if (!(strpos($Command, chr(0x20)) === false))
        {
            $Command = explode(chr(0x20), $Command);
        }
        if (count($Part) > 1)
        {
            $Value = implode('%3A', $Part);
        } else
        {
            $Value = array_shift($Part);
        }
        parent::__construct($Command, $Value, $isResponse);
        //return new LSQEvent($Command, $Value, $isResponse);
    }

}

class LSMSongInfo extends stdClass
{

    private $SongArray;
    private $Duration;

    public function __construct($TaggedDataLine)
    {
        $id = -1;
        $Songs = array();
        $Duration = 0;
        $SongFields = array(
            'Id' => 1,
            'Title' => 3,
            'Genre' => 3, // g
            'Album' => 3, // l
            'Artist' => 3, // a
            'Duration' => 1, // d
            'Disc' => 1, // i
            'Disccount' => 1, // q
            'Bitrate' => 3, // r
            'Tracknum' => 1, // t
            'Url' => 3, // u
            'Remote' => 0,
            'Rating' => 1, // R
            'Album_id' => 1, // e
            'Artwork_track_id' => 3, // J
            'Samplesize' => 3, // I
            'Remote_title' => 3, //N 
            'Genre_id' => 1, //p
            'Artist_id' => 1, //s
            'Year' => 1, // Y
            'Name' => 3,
            'Modified' => 0,
            'Playlist' => 3
        );
        foreach (explode(' ', $TaggedDataLine) as $Line)
        {

//            $LSQPart = $this->decodeLSQTaggingData($Line, false);
            $LSQPart = new LSQTaggingData($Line, false);

            if (is_array($LSQPart->Command) and ( $LSQPart->Command[0] == LSQResponse::playlist) and ( $LSQPart->Command[1] == LSQResponse::index))
            {
                $id = (int) $LSQPart->Value;
//                IPS_LogMessage('LMSSongInfo','ROW: '.$Key.' ID: '.$id);                
                continue;
            }
            if (is_array($LSQPart->Command))
                continue;

            if ($LSQPart->Command == LSQResponse::id)
            {
                $id++;
            }

            if ($LSQPart->Command == LSQResponse::duration)
                $Duration = +intval($LSQPart->Value);
            $Index = ucfirst($LSQPart->Command);
            if (array_key_exists($Index, $SongFields))
            {
                if ($SongFields[$Index] == 0)
                    $Songs[$id][$Index] = (bool) ($LSQPart->Value);
                elseif ($SongFields[$Index] == 1)
                    $Songs[$id][$Index] = intval($LSQPart->Value);
                else
                    $Songs[$id][$Index] = /* utf8_decode( */rawurldecode(rawurldecode($LSQPart->Value))/* ) */;
            }
        }
        if ((count($Songs) <> 1 ) and isset($Songs[-1]))
        {
            if (isset($Songs[-1]))
                unset($Songs[-1]);
        }
        $this->SongArray = $Songs;
        $this->Duration = $Duration;
    }

    public function GetSong()
    {
        return array_shift($this->SongArray);
    }

    public function GetAllSongs()
    {
        return $this->SongArray;
    }

    public function GetTotalDuration()
    {
        /*       if ($this->Duration > 3600)
          return @date('Hi:s', $this->Duration - 3600);
          else
          return @date('i:s', $this->Duration); */
        return $this->Duration;
    }

    public function CountAllSongs()
    {
        return count($this->SongArray);
    }

}

// Klasse mit den Empfangenen Daten vom LMS-Splitter
class LSQResponse extends stdClass
{

    //commands
    const listen = 'listen';
    const signalstrength = 'signalstrength';
    const name = 'name';
    const player_name = 'player_name';
    const player_connected = 'player_connected';
    const player_ip = 'player_ip';
    const open = 'open';
    const time = 'time';
    const playlist_tracks = 'playlist_tracks';
    const playlist_cur_index = 'playlist_cur_index';
    const currentSong = 'currentSong';
    const waitingToPlay = 'waitingToPlay';
    const jump = 'jump';
    const can_seek = 'can_seek';
    const remote = 'remote';
    const newmetadata = 'newmetadata';
    const remoteMeta = 'remoteMeta';
    const displaynotify = 'displaynotify';
    const id = 'id';
    const rate = 'rate';
    const seq_no = 'seq_no';
    const playlist_timestamp = 'playlist_timestamp';
    const title = 'title';
    const current_title = 'current_title';
    const index = 'index';
    const connected = 'connected';
    const sleep = 'sleep';
    const will_sleep_in = 'will_sleep_in';
    const sync = 'sync';
    const mode = 'mode';
    const power = 'power';
    const album = 'album';
    const artist = 'artist';
    const duration = 'duration';
    const genre = 'genre';
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
    const playlists = 'playlists';
    const playlistcontrol = 'playlistcontrol';
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
    const newsong = 'newsong';
    const tracks = 'tracks';
    const loadtracks = 'loadtracks';
    const load_done = 'load_done';
    const playlist_name = 'playlist_name';
    const playlist_modified = 'playlist_modified';
    const playlist_id = 'playlist_id';

    /*
      connected ?
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

    public $Address;
    public $Command;
    public $Value;
    public $isResponse = false;

//    private $Modus = Array(2 => LSQResponse::stop, 3 => LSQResponse::play, 4 => LSQResponse::pause);

    public function __construct($Data) // LMS->Data
    {
        if ($Data->Device == LMSResponse::isMAC)
            $this->Address = $Data->MAC;
        elseif ($Data->Device == LMSResponse::isIP)
            $this->Address = $Data->IP;
        foreach ($Data->Data as $Key => $Value)
        {
            $Data->Data[$Key] = utf8_decode($Value);
        }
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
            case LSQResponse::album:
            case LSQResponse::artist:
            case LSQResponse::genre:
            case LSQResponse::duration:
            case LSQResponse::time:
            case LSQResponse::newmetadata:
            case LSQResponse::title:
                $this->Command = array_shift($Data->Data);
                if (isset($Data->Data[0]))
                    $this->Value = array_shift($Data->Data);
                break;
            // 0 = Command 1=multiValue
            case LSQResponse::status:
                $this->Command[0] = array_shift($Data->Data);
                $this->Command[1] = array_shift($Data->Data);
                $this->Command[2] = array_shift($Data->Data);
//                $this->Command[3] =array_shift($Data->Data);                
                $this->Value = array_values($Data->Data);
                break;
            case LSQResponse::playlistcontrol:
                $this->Command = array_shift($Data->Data);
                $this->Value = $Data->Data;
                break;
//        LSQResponse::show,
//        LSQResponse::display,
//        LSQResponse::displaynow,
//        LSQResponse::playerpref
            // 1 = Command 2 = Value             
            case LSQResponse::button:
            case LSQResponse::mixer:
            case LSQResponse::playlist:
            case LSQResponse::playlists:
                $this->Command[0] = array_shift($Data->Data);
                $this->Command[1] = array_shift($Data->Data);
                if (isset($Data->Data[1]))
                {
                    $this->Value = $Data->Data;

//                    $this->Value[0] =  array_shift($Data->Data);
//                    $this->Value[1] =  array_shift($Data->Data);
                } elseif (isset($Data->Data[0]))
                    $this->Value = array_shift($Data->Data);
                break;

            // 2 = Command 3 = Value             
            case LSQResponse::prefset:
                $this->Command[0] = array_shift($Data->Data);
                $this->Command[1] = array_shift($Data->Data);
                $this->Command[2] = array_shift($Data->Data);
                if (isset($Data->Data[0]))
                    $this->Value = array_shift($Data->Data);
                break;
            default:
                $this->Command = array_shift($Data->Data);
                if (isset($Data->Data[0]))
                    $this->Value = array_shift($Data->Data);
                break;
            case 'displaynotify': //ignorieren
            case 'menustatus': //ignorieren                
                $this->Command = false;
                break;
        }
    }

    // Liefert den Aktuellen Zustand (play,pause,stop) als integer für die Status-Variable
    /*    public function GetModus()
      {

      return (int) array_keys($this->Modus, $this->Value, true);
      } */
}

if (!function_exists("array_column"))
{

    function array_column($array, $column_name)
    {

        return array_map(function($element) use($column_name)
        {
            return $element[$column_name];
        }, $array);
    }

}
?>