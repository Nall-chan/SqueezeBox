<?

// Klasse mit Daten zum SENDEN an den LMS
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
        if (strpos($array[0], '%')) //isMAC
        {
            $this->Device = LMSResponse::isMAC;
            $this->MAC = rawurldecode(array_shift($array));
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
        if (isset($Part[1]))
        {
            $Value = implode('%3A', $Part);
        }
        else
        {
            $Value = $Part[0];
        }
        parent::__construct($Command, $Value, $isResponse);
        //return new LSQEvent($Command, $Value, $isResponse);
    }
    
}

class LSMSongInfo extends stdClass
{
    private $SongArray;
        public function __construct($TaggedDataLine)
    {
        $id = 0;
        $Songs = array();
        $SongFields = array(
            'Id' => 0,
            'Title' => 1,
            'Genre' => 1,
            'Album' => 1,
            'Artist' => 1,
            'Duration' => 0,
            'Disc' => 0,
            'Disccount' => 0,
            'Bitrate' => 1,
            'Tracknum' => 0
        );
        foreach (explode(' ', $TaggedDataLine) as $Key => $Line)
        {

//            $LSQPart = $this->decodeLSQTaggingData($Line, false);
            $LSQPart = new LSQTaggingData($Line, false);                        
//o%20Ninjago duration%3A213.471 disc%3A11 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A14 playlist%20index%3A162 id%3A32144 title%3AEpisode%2031%3A%20Die%20Computerwelt%203 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A245.914 disc%3A11 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A15 playlist%20index%3A163 id%3A32145 title%3AEpisode%2031%3A%20Die%20Computerwelt%204 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A260.112 disc%3A11 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A16 playlist%20index%3A164 id%3A32146 title%3AEpisode%2031%3A%20Die%20Computerwelt%205 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A241.947 disc%3A11 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A17 playlist%20index%3A165 id%3A32147 title%3AEpisode%2031%3A%20Die%20Computerwelt%206 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A208.617 disc%3A11 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A18 playlist%20index%3A166 id%3A32148 title%3AThe%20Fold%20-%20The%20Weekend%20Whip%20(Lego%20Ninjago%20Theme%20Song)%20-%20Michael%20Am%20Remix genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A259.146 disc%3A11 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A19 playlist%20index%3A167 id%3A32149 title%3AEpisode%2032%3A%20Projekt%20Arcturus%201 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A281.384 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A1 playlist%20index%3A168 id%3A32150 title%3AEpisode%2032%3A%20Projekt%20Arcturus%202 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A258.885 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A2 playlist%20index%3A169 id%3A32151 title%3AEpisode%2032%3A%20Projekt%20Arcturus%203 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A401.339 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A3 playlist%20index%3A170 id%3A32152 title%3AEpisode%2032%3A%20Projekt%20Arcturus%204 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A191.495 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A4 playlist%20index%3A171 id%3A32153 title%3AEpisode%2032%3A%20Projekt%20Arcturus%205 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A235.291 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A5 playlist%20index%3A172 id%3A32154 title%3AEpisode%2033%3A%20Die%20Ninja%20im%20Weltall%201 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A290.597 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A6 playlist%20index%3A173 id%3A32155 title%3AEpisode%2033%3A%20Die%20Ninja%20im%20Weltall%202 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A230.88 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A7 playlist%20index%3A174 id%3A32156 title%3AEpisode%2033%3A%20Die%20Ninja%20im%20Weltall%203 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A261.287 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A8 playlist%20index%3A175 id%3A32157 title%3AEpisode%2033%3A%20Die%20Ninja%20im%20Weltall%204 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A246.279 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A9 playlist%20index%3A176 id%3A32158 title%3AEpisode%2033%3A%20Die%20Ninja%20im%20Weltall%205 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A229.288 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A10 playlist%20index%3A177 id%3A32159 title%3AEpisode%2034%3A%20Der%20Goldene%20Meister%201 genre%3AH%C3%B6rspiel album%3ANinjago%20Staffel%203 artist%3ALego%20Ninjago duration%3A290.049 disc%3A12 disccount%3A12 bitrate%3A320kb%2Fs%20CBR tracknum%3A11 playlist%20index%3A
     
                        IPS_LogMessage('LMSSongInfo','ROW: '.$Key.' Data:'.print_r($LSQPart->Command,1));                
            if (is_array($LSQPart->Command) and ( $LSQPart->Command[0] == LSQResponse::playlist) and ( $LSQPart->Command[0] == LSQResponse::index))
            {
                $id = (int) $LSQPart->Value;
                IPS_LogMessage('LMSSongInfo','ROW: '.$Key.' ID: '.$id);                
                continue;
            }
            $Index = ucfirst($LSQPart->Command);
            if (array_key_exists($Index, $SongFields))
            {
                if ($SongFields[$Index] == 0)
                    $Songs[$id][$Index] = intval($LSQPart->Value);
                else
                    $Songs[$id][$Index] = utf8_decode(rawurldecode($LSQPart->Value));
            }
        }
        $this->SongArray= $Songs;
    }
    
    public function GetSong($Index)
    {
        return $this->SongArray[$Index];
    }

    public function GetAllSongs()
    {
        return $this->SongArray;
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
    const open ='open';
    const time = 'time';
    const playlist_tracks = 'playlist_tracks';
    const playlist_cur_index = 'playlist_cur_index';
    const currentSong = 'currentSong';
    const waitingToPlay = 'waitingToPlay';
    const jump = 'jump';    
    const can_seek = 'can_seek';
    const remote = 'remote';   
    const newmetadata ='newmetadata';
    const remoteMeta='remoteMeta';
    const displaynotify='displaynotify';
    const id='id';
    const rate = 'rate';
    const seq_no = 'seq_no';
    const playlist_timestamp = 'playlist_timestamp';
    const title = 'title';
    const current_title ='current_title';
    const index = 'index';
    const connected = 'connected';
    const sleep = 'sleep';
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
                $this->Command = $Data->Data[0];
                if (isset($Data->Data[1]))
                    $this->Value = $Data->Data[1];
                break;
            // 0 = Command 1=multiValue
            case LSQResponse::status:

                $this->Command[0] = array_shift($Data->Data);
                $this->Command[1] = array_shift($Data->Data);
                $this->Command[2] = array_shift($Data->Data);
//                $this->Command[3] =array_shift($Data->Data);                
                $this->Value = array_values($Data->Data);
                break;
//        LSQResponse::show,
//        LSQResponse::display,
//        LSQResponse::displaynow,
//        LSQResponse::playerpref
            // 1 = Command 2 = Value             
            case LSQResponse::button:
            case LSQResponse::mixer:
            case LSQResponse::playlist:
                $this->Command[0] = $Data->Data[0];
                $this->Command[1] = $Data->Data[1];
                if (isset($Data->Data[3]))
                {
                    $this->Value[0] = $Data->Data[2];
                    $this->Value[1] = $Data->Data[3];
                }
                elseif (isset($Data->Data[2]))
                    $this->Value = $Data->Data[2];
                break;

            // 2 = Command 3 = Value             
            case LSQResponse::prefset:
                $this->Command[0] = $Data->Data[0];
                $this->Command[1] = $Data->Data[1];
                $this->Command[2] = $Data->Data[2];
                if (isset($Data->Data[3]))
                    $this->Value = $Data->Data[3];
                break;
            default:
                $this->Command = false;
                $this->Value = $Data->Data;
                break;
        }
    }

    // Liefert den Aktuellen Zustand (play,pause,stop) als integer für die Status-Variable
    /*    public function GetModus()
      {

      return (int) array_keys($this->Modus, $this->Value, true);
      } */
}

?>