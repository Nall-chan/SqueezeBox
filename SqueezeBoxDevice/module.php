<?

require_once(__DIR__ . "/../SqueezeBoxClass.php");  // diverse Klassen

class SqueezeboxDevice extends IPSModule
{

    const isMAC = 1;
    const isIP = 2;

    protected $Address, $Interval, $Connected, $tempData;

    public function __construct($InstanceID)
    {

        //Never delete this line!
        parent::__construct($InstanceID);
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyInteger("Interval", 2);
        $this->RegisterPropertyString("CoverSize", "cover");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Addresse prüfen
        $Address = $this->ReadPropertyString('Address');
        if ($Address == '')
        {
            // Status inaktiv
        }
        else
        {
            if (!strpos($Address, '.')) //keine IP ?
            {
                if (!strpos($Address, ':')) //keine MAC mit :
                {
                    if (!strpos($Address, '-')) //keine MAC mit -
                    {// : einfügen 
                        //Länge muss 12 sein, sonst löschen
                        if (strlen($Address) == 12)
                        {
                            $Address = implode(":", str_split($Address, 2));
                            // STATUS config OK
                        }
                        else
                        {
                            $Address = '';
                            // STATUS config falsch
                        }
                        IPS_SetProperty($this->InstanceID, 'Address', $Address);
                        IPS_ApplyChanges($this->InstanceID);
                        return;
                    }
                    else
                    {
                        if (strlen($Address) == 17)
                        {
                            //- gegen : ersetzen                    
                            $Address = str_replace('-', ':', $Address);
                            // STATUS config OK
                        }
                        else
                        {                    //Länge muss 17 sein, sonst löschen
                            $Address = '';
                            // STATUS config falsch
                        }
                        IPS_SetProperty($this->InstanceID, 'Address', $Address);
                        IPS_ApplyChanges($this->InstanceID);
                        return;
                    }
                }
                else
                { // OK : nun Länge prüfen
                    //Länge muss 17 sein, sonst löschen                
                    if (strlen($Address) <> 17)
                    {                    //Länge muss 17 sein, sonst löschen
                        $Address = '';
                        // STATUS config falsch
                        IPS_SetProperty($this->InstanceID, 'Address', $Address);
                        IPS_ApplyChanges($this->InstanceID);
                        return;
                    }
                }
            }
        }

        // LMS-Splitter wird benötigt
        $this->RequireParent("{61051B08-5B92-472B-AFB2-6D971D9B99EE}");

        // Profile anlegen
        $this->RegisterProfileIntegerEx("Status.Squeezebox", "Information", "", "", Array(
            Array(0, "Prev", "", -1),
            Array(1, "Stop", "", -1),
            Array(2, "Play", "", -1),
            Array(3, "Pause", "", -1),
            Array(4, "Next", "", -1)
        ));
        $this->RegisterProfileInteger("Intensity.Squeezebox", "Intensity", "", " %", 0, 100, 1);
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
        $this->RegisterProfileIntegerEx("Preset.Squeezebox", "Speaker", "", "", Array(
            Array(1, "1", "", -1),
            Array(2, "2", "", -1),
            Array(3, "3", "", -1),
            Array(4, "4", "", -1),
            Array(5, "5", "", -1),
            Array(6, "6", "", -1)
        ));
        $this->RegisterProfileInteger("Tracklist.Squeezebox." . $this->InstanceID, "", "", "", 1, 1, 1);

        //Status-Variablen anlegen
        $this->RegisterVariableBoolean("Power", "Power", "~Switch", 1);
        $this->EnableAction("Power");
        $this->RegisterVariableInteger("Status", "Status", "Status.Squeezebox", 3);
        $this->EnableAction("Status");
        $this->RegisterVariableInteger("Preset", "Preset", "Preset.Squeezebox", 2);
        $this->EnableAction("Preset");
        $this->RegisterVariableBoolean("Mute", "Mute", "~Switch", 4);
        $this->EnableAction("Mute");

        $this->RegisterVariableInteger("Volume", "Volume", "Intensity.Squeezebox", 5);
        $this->EnableAction("Volume");
        $this->RegisterVariableInteger("Bass", "Bass", "Intensity.Squeezebox", 6);
        $this->EnableAction("Bass");
        $this->RegisterVariableInteger("Treble", "Treble", "Intensity.Squeezebox", 7);
        $this->EnableAction("Treble");
        $this->RegisterVariableInteger("Pitch", "Pitch", "Intensity.Squeezebox", 8);
        $this->EnableAction("Pitch");

        $this->RegisterVariableInteger("Shuffle", "Shuffle", "Shuffle.Squeezebox", 9);
        $this->EnableAction("Shuffle");
        $this->RegisterVariableInteger("Repeat", "Repeat", "Repeat.Squeezebox", 10);
        $this->EnableAction("Repeat");
        $this->RegisterVariableInteger("Tracks", "Playlist Anzahl Tracks", "", 11);
        $this->RegisterVariableInteger("Index", "Playlist Position", "Tracklist.Squeezebox." . $this->InstanceID, 12);
        $this->EnableAction("Index");

        $this->RegisterVariableString("Album", "Album", "", 20);
        $this->RegisterVariableString("Title", "Title", "", 21);
        $this->RegisterVariableString("Interpret", "Interpret", "", 22);
        $this->RegisterVariableString("Genre", "Stilrichtung", "", 23);
        $this->RegisterVariableString("Duration", "Dauer", "", 24);
        $this->RegisterVariableString("Position", "Spielzeit", "", 25);
        $this->RegisterVariableInteger("Position2", "Position", "Intensity.Squeezebox", 26);
        $this->EnableAction("Position2");

        $this->RegisterVariableInteger("Signalstrength", utf8_encode("Signalstärke"), "Intensity.Squeezebox", 30);
        $this->RegisterVariableInteger("SleepTimeout", "SleepTimeout", "", 31);

        // Workaround für persistente Daten der Instanz.
        $this->RegisterVariableString("BufferOUT", "BufferOUT", "", -4);
        $this->RegisterVariableBoolean("WaitForResponse", "WaitForResponse", "", -5);
        $this->RegisterVariableBoolean("Connected", "Connected", "", -3);

        $this->RegisterVariableInteger("PositionRAW", "PositionRAW", "", -1);
        $this->RegisterVariableInteger("DurationRAW", "DurationRAW", "", -2);
        IPS_SetHidden($this->GetIDForIdent('BufferOUT'), true);
        IPS_SetHidden($this->GetIDForIdent('WaitForResponse'), true);
        IPS_SetHidden($this->GetIDForIdent('Connected'), true);
        IPS_SetHidden($this->GetIDForIdent('PositionRAW'), true);
        IPS_SetHidden($this->GetIDForIdent('DurationRAW'), true);
        // Adresse nicht leer ?
        if ($this->Init())
        {
            // Parent vorhanden ?
            $ParentID = $this->GetParent();
            if (!($ParentID === false))
            {
                // Parent aktiv ?
                if ($this->HasActiveParent($ParentID))
                {
                    // Ist Device (Player) connected ?
                    $Data = new LSQData(LSQResponse::connected, '?');

                    // Dann Status von LMS holen
                    if ($this->SendLSQData($Data) == 1)
                        $this->SetConnected(true);
                    // nicht connected
                    else
                        $this->SetConnected(false);
                }
                IPS_LogMessage('ApplyChanges', 'Instant:' . $this->InstanceID);
            }
        }
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function Sync()
    {
        $this->init();

        if ($this->Connected)
        {
            $this->SendLSQData(
                    new LSQData(LSQResponse::listen, '1')//, false)
            );
            $this->SendLSQData(
                    new LSQData(LSQResponse::power, '?')//, false)
            );
            $this->SendLSQData(
                    new LSQData(array(LSQResponse::mixer, LSQResponse::volume), '?')//, false)
            );
            $this->SendLSQData(
                    new LSQData(array(LSQResponse::mixer, LSQResponse::pitch), '?')//, false)
            );
            $this->SendLSQData(
                    new LSQData(array(LSQResponse::mixer, LSQResponse::bass), '?')//, false)
            );
            $this->SendLSQData(
                    new LSQData(array(LSQResponse::mixer, LSQResponse::treble), '?')//, false)
            );
            $this->SendLSQData(
                    new LSQData(array(LSQResponse::mixer, LSQResponse::muting), '?')//, false)
            );
            $this->SendLSQData(
                    new LSQData(LSQResponse::mode, '?')//, false)
            );
            $this->SendLSQData(
                    new LSQData(LSQResponse::signalstrength, '?')//, false)
            );
            $this->SendLSQData(
                    new LSQData(LSQResponse::name, '?')//, false)
            );
        }
        else
        {
            $this->SetValueBoolean('Power', false);
        }
    }

    public function RawSend($Command, $Value, $needResponse)
    {
        $LSQData = new LSQData($Command, $Value, $needResponse);
        return $this->SendDataToParent($LSQData);
    }

    public function SetName($Value)
    {
        return $this->SendLSQData(new LSQData(LSQResponse::name, (string) $Value));
    }

    public function SetSleepTimeout($Value)
    {
        return $this->SendLSQData(new LSQData(LSQResponse::sleep, (int) $Value));
    }

    public function PreviousButton()
    {
        return $this->SendLSQData(new LSQData(array('button', 'jump_rew'), ''));
    }

    public function Stop()
    {
//        $this->SendLSQData(new LSQData('button', 'stop'));        
        return $this->SendLSQData(new LSQData('stop', ''));
    }

    public function Play()
    {
        return $this->SendLSQData(new LSQData('play', ''));
    }

    public function Pause()
    {
        return boolval($this->SendLSQData(new LSQData('pause', '1')));
    }

    public function NextButton()
    {
        return $this->SendLSQData(new LSQData(array('button', 'jump_fwd'), ''));
    }

    public function SetVolume($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'volume'), $Value));
        return ($ret == $Value);
    }

    public function SetBass($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'bass'), $Value));
        return ($ret == $Value);
    }

    public function SetTreble($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'treble'), $Value));
        return ($ret == $Value);
    }

    public function SetPitch($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'pitch'), $Value));
        return ($ret == $Value);
    }

    public function SelectPreset($Value)
    {
        return $this->SendLSQData(new LSQData(array('button', 'preset_' . (int) $Value . '.single'), ''));
    }

    public function Mute($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'muting'), intval($Value)));
        return ($ret == $Value);
    }

    public function Power($Value)
    {
        $ret = $this->SendLSQData(new LSQData('power', intval($Value)));
        return ($ret == $Value);
    }

    public function Repeat($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'repeat'), intval($Value)));
        return ($ret == $Value);
    }

    public function Shuffle($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'shuffle'), intval($Value)));
        return ($ret == $Value);
    }

    public function PlayTrack($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'index'), intval($Value) - 1));
        return ($ret == $Value);
    }

    public function NextTrack()
    {
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'index'), '+1'));
        return ($ret == '%2B1');
    }

    public function PreviousTrack()
    {
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'index'), '-1'));
        return ($ret == '-1');
    }

    public function SetTime($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'time'), $Value));
        return ($ret == $Value);
    }

################## ActionHandler

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident)
        {
            case "Status":
                switch ($Value)
                {
                    case 0: //Prev
                        //$this->PreviousButton();
                        $this->PreviousTrack();
                        break;
                    case 1: //Stop
                        $this->Stop();
                        break;
                    case 2: //Play
                        $this->Play();
                        break;
                    case 3: //Pause
                        $this->Pause();
                        break;
                    case 4: //Next
                        //$this->NextButton();
                        $this->NextTrack();
                        break;
                }
                break;
            case "Volume":
                $this->SetVolume($Value);
                break;
            case "Bass":
                $this->SetBass($Value);
                break;
            case "Treble":
                $this->SetTreble($Value);
                break;
            case "Pitch":
                $this->SetPitch($Value);
                break;
            case "Preset":
                $this->SelectPreset($Value);
                break;
            case "Power":
                $this->Power($Value);
                break;
            case "Mute":
                $this->Mute($Value);
                break;
            case "Repeat":
                $this->Repeat($Value);
                break;
            case "Shuffle":
                $this->Shuffle($Value);
                break;
            case "Position2":
                $this->tempData['Duration'] = GetValueInteger($this->GetIDForIdent('DurationRAW'));
                $this->tempData['Position'] = GetValueInteger($this->GetIDForIdent('PositionRAW'));
                $Time = ($this->tempData['Duration'] / 100) * $Value;
                $this->SetTime($Time);
                break;
            case "Index":
                $this->PlayTrack($Value);
                break;
            default:
                throw new Exception("Invalid ident");
        }
    }

################## PRIVATE

    private function decodeLSQEvent($LSQEvent)
    {
        if (is_array($LSQEvent->Command))
            $MainCommand = array_shift($LSQEvent->Command);
        else
            $MainCommand = $LSQEvent->Command;
        switch ($MainCommand)
        {
            case LSQResponse::connected:
                if (!$LSQEvent->isResponse) //wenn Response, dann macht der Anfrager das selbst
                {
                    if ($LSQEvent->Value == 1)
                        $this->SetConnected(true);
                    else
                        $this->SetConnected(false);
                }
                break;
            case LSQResponse::signalstrength:
                $this->SetValueInteger('Signalstrength', (int) $LSQEvent->Value);
                break;
            case LSQResponse::player_ip:
                //wegwerfen, solange es keinen SetSummary gibt
                break;
            case LSQResponse::player_name:
            case LSQResponse::name:
                if (IPS_GetName($this->InstanceID) <> trim(urldecode((string) $LSQEvent->Value)))
                {
                    IPS_SetName($this->InstanceID, trim(urldecode((string) $LSQEvent->Value)));
                }
                break;
            case LSQResponse::sleep:
                $this->SetValueInteger('SleepTimeout', (int) $LSQEvent->Value);
                break;
            case LSQResponse::button:
                $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                break;
            case LSQButton::jump_fwd:
            case LSQButton::jump_rew:
                $this->SetValueBoolean('Power', true);
                $this->SetValueInteger('Status', 2);
                $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
                break;
            case LSQResponse::sync:
            case LSQResponse::player_connected:
            case LSQResponse::can_seek:
            case LSQResponse::rate:
            case LSQResponse::seq_no:
            case LSQResponse::playlist_timestamp:
            case LSQResponse::linesperscreen:
            case LSQResponse::irenable:
            case LSQResponse::connect:
                //ignore
                break;
            case LSQResponse::pause:
                if (boolval($LSQEvent->Value))
                {
                    $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:0', false));
                    $this->SetValueInteger('Status', 3);
                }
                else
                {
                    $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
                    $this->SetValueInteger('Status', 2);
                }
                break;
            case LSQResponse::play:
                $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
                $this->SetValueInteger('Status', 2);
                break;
            case LSQResponse::stop:
                $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:0', false));
                $this->SetValueInteger('Status', 1);
                break;
            case LSQResponse::mode:
                $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                break;
            case LSQResponse::client:
                if (!$LSQEvent->isResponse) //wenn Response, dann macht der Anfrager das selbst                
                {
                    if ($LSQEvent->Value == 'disconnect')
                        $this->SetConnected(false);
                    elseif (($LSQEvent->Value == 'new') or ( $LSQEvent->Value == 'reconnect'))
                        $this->SetConnected(true);
                }
                break;
            case LSQResponse::power:
                $this->SetValueBoolean('Power', boolval($LSQEvent->Value));
                break;
            case LSQResponse::mixer:
                $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                break;
            case LSQResponse::muting:
                $this->SetValueBoolean('Mute', boolval($LSQEvent->Value));
                break;
            case LSQResponse::volume:
                $Value = (int) ($LSQEvent->Value);
                if ($Value < 0)
                {
                    $Value = $Value - (2 * $Value);
                    $this->SetValueBoolean('Mute', true);
                }
                else
                {
                    $this->SetValueBoolean('Mute', false);
                }
                $this->SetValueInteger('Volume', $Value);
                break;
            case LSQResponse::treble:
                $this->SetValueInteger('Treble', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::bass:
                $this->SetValueInteger('Bass', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::pitch:
                $this->SetValueInteger('Pitch', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::repeat:
                $this->SetValueInteger('Repeat', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::shuffle:
                $this->SetValueInteger('Shuffle', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::newsong:
                if (is_array($LSQEvent->Value))
                {
                    $title = $LSQEvent->Value[0];
                    $currentTrack = intval($LSQEvent->Value[1]) + 1;
                }
                else
                {
                    $title = $LSQEvent->Value;
                    $currentTrack = 0;
                }
                $this->SetValueInteger('Status', 2);
                $this->SetValueString('Title', trim(urldecode($title)));
                $this->SetValueInteger('Index', $currentTrack);
                $this->SendLSQData(new LSQData(LSQResponse::artist, '?', false));
                $this->SendLSQData(new LSQData(LSQResponse::album, '?', false));
                $this->SendLSQData(new LSQData(LSQResponse::genre, '?', false));
                $this->SendLSQData(new LSQData(LSQResponse::duration, '?', false));
                $this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::tracks), '?', false));
                $this->SetCover();
                $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
                break;
            case LSQResponse::playlist:
                if (($LSQEvent->Command[0]<>LSQResponse::stop)  //Playlist stop kommt auch bei fwd ?
                        and ($LSQEvent->Command[0]<>LSQResponse::mode))
                $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                break;
            case LSQResponse::prefset:
                if ($LSQEvent->Command[0] == 'server')
                {
                    $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[1], $LSQEvent->Value, $LSQEvent->isResponse));
                }
                else
                {
                    IPS_LogMessage('prefsetLSQEvent', 'Namespace' . $LSQEvent->Command[0] . ':' . $LSQEvent->Value);
                }
                break;
            case LSQResponse::artist:
                $this->SetValueString('Interpret', trim(urldecode($LSQEvent->Value)));
                break;
            case LSQResponse::album:
                $this->SetValueString('Album', trim(urldecode($LSQEvent->Value)));
                break;
            case LSQResponse::genre:
                $this->SetValueString('Genre', trim(urldecode($LSQEvent->Value)));
                break;
            case LSQResponse::duration:
                if ($LSQEvent->Value == 0)
                {
                    $this->SetValueString('Duration', '');
                    $this->SetValueInteger('DurationRAW', 0);
                    $this->SetValueInteger('Position2', 0);
                }
                else
                {
                    $this->tempData['Duration'] = $LSQEvent->Value;
                    $this->SetValueInteger('DurationRAW', $LSQEvent->Value);
                    $this->SetValueString('Duration', @date('i:s', $LSQEvent->Value));
                }
                break;
            case LSQResponse::playlist_tracks:
            case LSQResponse::tracks:
                $this->SetValueInteger('Tracks', $LSQEvent->Value);
                $Name = "Tracklist.Squeezebox." . $this->InstanceID;
                if (!IPS_VariableProfileExists($Name))
                {
                    IPS_CreateVariableProfile($Name, 1);
                    IPS_SetVariableProfileValues($Name, 1, $LSQEvent->Value, 1);
                }
                else
                {
                    if (IPS_GetVariableProfile($Name)['MaxValue'] <> $LSQEvent->Value)
                        IPS_SetVariableProfileValues($Name, 1, $LSQEvent->Value, 1);
                }
                break;                
            case LSQResponse::status:
//                IPS_LogMessage('statusLSQEvent', print_r($LSQEvent, 1));
                $Event = array_shift($LSQEvent->Value);
                if ($LSQEvent->Command[0] == '-')// and ( $LSQEvent->Command[1] == '1') and ( strpos($Event, "subscribe%3A") > 0))
                       
                {
//                    IPS_LogMessage('subscribeLSQEvent', print_r($LSQEvent->Value, 1));
                    foreach ($LSQEvent->Value as $Data)
                    {
                        $LSQPart = $this->decodeLSQTaggingData($Data, $LSQEvent->isResponse);
                        //                  IPS_LogMessage('ValueLSQEvent', print_r($Value, 1));
                        $this->decodeLSQEvent($LSQPart);
                    }
                }
                if ($LSQEvent->Command[0] == '0') //and ( strpos($Event, "tags%3A") > 0))
                { //Daten für Playlist dekodieren und zurückgeben
                    IPS_LogMessage('statusLSQEvent', print_r($LSQEvent->Value, 1));
                }
                break;
            case LSQResponse::index:
            case LSQResponse::playlist_cur_index:
                $this->SetValueInteger('Index', intval($LSQEvent->Value) + 1);
                break;

            case LSQResponse::time:
                $this->tempData['Position'] = $LSQEvent->Value;
                $this->SetValueInteger('PositionRAW', $LSQEvent->Value);
                $this->SetValueString('Position', @date('i:s', $LSQEvent->Value));

                /*                $duration = GetValueString($this->GetIDForIdent('Duration'));
                  if ($duration <> '')
                  {
                  $duration= strtotime ( $duration , 0);
                  $Value = (100 / $duration) * $LSQEvent->Value;
                  $this->SetValueInteger('Position2', round($Value));
                  }
                  else
                  {
                  $this->SetValueInteger('Position2', 0);
                  } */
                break;
            default:
                if (is_array($LSQEvent->Value))
                    IPS_LogMessage('defaultLSQEvent', 'LSQResponse-' . $MainCommand . '-' . print_r($LSQEvent->Value, 1));
                else
                    IPS_LogMessage('defaultLSQEvent', 'LSQResponse-' . $MainCommand . '-' . $LSQEvent->Value);
                break;
        }
        if (isset($this->tempData['Duration']) and isset($this->tempData['Position']))
        {
            $Value = (100 / $this->tempData['Duration']) * $this->tempData['Position'];
            $this->SetValueInteger('Position2', round($Value));
        }

        /*
         * 0 = aus
         * 1 = Titel Normalisierung
         * 2 Album 
         * 3 'Intelligente
         * 
         * 14.07.2015 19:21:49? | IODevice DECODE? | Array
          (
          [0] => 00:04:20:2b:9d:ae
          [1] => prefset
          [2] => server
          [3] => replayGainMode
          [4] => 1
          )
          14.07.2015 19:22:06? | IODevice DECODE? | Array
          (
          [0] => 00:04:20:2b:9d:ae
          [1] => playerpref
          [2] => replayGainMode
          [3] => 0
          ) */
        /*
          14.07.2015 19:26:48? | IODevice DECODE? | Array
          (
          [0] => 00:04:20:2b:9d:ae
          [1] => playlist
          [2] => jump
          [3] => 5
          [4] =>
          [5] =>
          [6] =>
          ) */
    }

    private function decodeLSQTaggingData($Data, $isResponse)
    {
        $Part = explode(chr(0x3a), urldecode($Data));
//                  IPS_LogMessage('PartLSQEvent', print_r($Part, 1));                                    
        $Command = array_shift($Part);
        if (!(strpos($Command, chr(0x20)) === false))
        {
            $Command = explode(chr(0x20), $Command);
        }
//                    IPS_LogMessage('CommandLSQEvent', print_r($Command, 1));
        // alle playlist_xxx auch zerlegen ?
        if (isset($Part[1]))
        {
            $Value = $Part;
        }
        else
        {
            $Value = $Part[0];
        }
        return new LSQEvent($Command, $Value, $isResponse);
    }

    private function SetCover()
    {
        $CoverID = @IPS_GetObjectIDByIdent('CoverIMG', $this->InstanceID);
        if ($CoverID === false)
        {
            $CoverID = IPS_CreateMedia(1);
            IPS_SetParent($CoverID, $this->InstanceID);
            IPS_SetIdent($CoverID, 'CoverIMG');
            IPS_SetName($CoverID, 'Cover');
            IPS_SetPosition($CoverID, 27);
            IPS_SetMediaFile($CoverID, "Cover_" . $this->InstanceID . ".png", False);
        }
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            $Host = IPS_GetProperty($ParentID, 'Host') . ":" . IPS_GetProperty($ParentID, 'Webport');
            $Size = $this->ReadPropertyString("CoverSize");
            $PlayerID = urlencode($this->Address);
            $CoverRAW = @Sys_GetURLContent("http://" . $Host . "/music/current/" . $Size . ".png?player=" . $PlayerID);
            if (!($CoverRAW === false))
            {
                IPS_SetMediaContent($CoverID, base64_encode($CoverRAW));
            }
        }
        return;
    }

    private function SetConnected($Status)
    {
        $this->SetValueBoolean('Connected', $Status);
        if ($Status)
        {
            $this->Sync();
        }
    }

    private function Init()
    {
        $this->Address = $this->ReadPropertyString("Address");
        $this->Interval = $this->ReadPropertyInteger("Interval");
        $this->Connected = GetValueBoolean($this->GetIDForIdent('Connected'));
        if ($this->Address == '')
            return false;
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

################## DataPoints
    // Ankommend von Parent-LMS-Splitter

    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString);
        $this->Init();
        if ($this->Address === '') //Keine Adresse Daten nicht verarbeiten
            return false;

        // Adressen stimmen überein, die Daten sind für uns.
        if (($this->Address == $Data->LMS->MAC) or ( $this->Address == $Data->LMS->IP))
        {
            // Objekt erzeugen welches die Commands und die Values enthält.
            $Response = new LSQResponse($Data->LMS);

            // Ist das Command noch schon bekannt ?
            if ($Response->Command <> false)
            {
                // Daten prüfen ob Antwort
                IPS_LogMessage('EMPFANG', print_r($Response, 1));

                $isResponse = $this->WriteResponse($Response->Command, $Response->Value);
                if (is_bool($isResponse))
                {
                    $Response->isResponse = $isResponse;
                    // Daten dekodieren
                    $this->decodeLSQEvent($Response);
                    return true;
                }
                else
                {
                    throw new Exception($isResponse);
                }
            }
            // Unbekanntes Command loggen
            else
            {
                IPS_LogMessage("LSQDevice: ToDo Datensatz:", print_r($Response->Value, 1));
            }
        }
        else
        // Daten waren nicht für uns
            return false;
    }

    // Sende-Routine an den Parent
    protected function SendDataToParent($LSQData)
    {
        $LSQData->Address = $this->ReadPropertyString('Address');
        // Sende Lock setzen
        if (!$this->lock("ToParent"))
        {
            throw new Exception("Can not send to LMS-Splitter");
        }
        // Daten senden
        try
        {
            $ret = IPS_SendDataToParent($this->InstanceID, json_encode(Array("DataID" => "{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}", "LSQ" => $LSQData)));
        }
        catch (Exception $exc)
        {
            // Senden fehlgeschlagen
            // Sende Lock aufheben
            $this->unlock("ToParent");
            throw new Exception("LMS not reachable");
        }
        // Sende Lock aufheben
        $this->unlock("ToParent");
        return $ret;
    }

    ################## Datenaustausch

    private function SendLSQData($LSQData)
    {
        $this->init();
        // prüfen ob Player connected ?
        // nur senden wenn connected ODER wir eine connected anfrage senden wollen
        if ((!$this->Connected) and ( $LSQData->Command <> LSQResponse::connected))
        {
            throw new Exception("Device not connected");
        }

        if ($LSQData->needResponse)
        {
//Semaphore setzen
            if (!$this->lock("LSQData"))
            {
                throw new Exception("Can not send to LMS-Splitter");
            }
// Anfrage f??r die Warteschleife schreiben
            if (!$this->SetWaitForResponse($LSQData->Command))
            {
                $this->unlock("LSQData");
                throw new Exception("Can not send to LMS-Splitter");
            }
            try
            {
                $this->SendDataToParent($LSQData);
            }
            catch (Exception $exc)
            {
//  Daten in Warteschleife l?¶schen
                $this->ResetWaitForResponse();
                $this->unlock("LSQData");
                throw $exc;
            }
// SendeLock  velassen
            $this->unlock("LSQData");
            IPS_LogMessage('SENDE', print_r($LSQData, 1));
// Auf Antwort warten....
            $ret = $this->WaitForResponse();

            if ($ret === false) // Warteschleife lief in Timeout
            {
//  Daten in Warteschleife l?¶schen                
                $this->ResetWaitForResponse();
// Fehler
                throw new Exception("No answer from LMS");
            }
// R??ckgabe ist ein Wert auf eine Anfrage, abschneiden der Anfrage.
//FEHLT NOCH
//                        $ret = str_replace($WaitData, "", $ret);
            if ($ret === true)
                IPS_LogMessage('FOUND RESPONSE', 'TRUE');
            else
                IPS_LogMessage('FOUND RESPONSE', print_r($ret, 1));
            return $ret;
        }
        else
        {
            try
            {
                $this->SendDataToParent($LSQData);
            }
            catch (Exception $exc)
            {
                throw $exc;
            }
        }
    }

################## ResponseBuffer    -   private

    private function SetWaitForResponse($Data)
    {
        if (is_array($Data))
            $Data = implode(' ', $Data);
        if ($this->lock('BufferOut'))
        {
            $buffer = $this->GetIDForIdent('BufferOUT');
            $WaitForResponse = $this->GetIDForIdent('WaitForResponse');
            SetValueString($buffer, $Data);
            SetValueBoolean($WaitForResponse, true);
            $this->unlock('BufferOut');
            return true;
        }
        return false;
    }

    private function WaitForResponse()
    {
        $Event = $this->GetIDForIdent('WaitForResponse');
        for ($i = 0; $i < 500; $i++)
        {
            if (GetValueBoolean($Event))
                IPS_Sleep(10);
            else
            {
                if ($this->lock('BufferOut'))
                {
                    $buffer = $this->GetIDForIdent('BufferOUT');
                    $ret = GetValueString($buffer);
                    SetValueString($buffer, "");
                    $this->unlock('BufferOut');
                    if ($ret == '')
                        return true;
                    else
                        return $ret;
                }
                return false;
            }
        }
        return false;
    }

    private function ResetWaitForResponse()
    {
        if ($this->lock('BufferOut'))
        {
            $buffer = $this->GetIDForIdent('BufferOUT');
            $WaitForResponse = $this->GetIDForIdent('WaitForResponse');
            SetValueString($buffer, '');
            SetValueBoolean($WaitForResponse, false);
            $this->unlock('BufferOut');
            return true;
        }
        return false;
    }

    private function WriteResponse($Command, $Value)
    {
        if (is_array($Command))
            $Command = implode(' ', $Command);
        if (is_array($Value))
            $Value = implode(' ', $Value);
//            $Value = $Value[0];

        $EventID = $this->GetIDForIdent('WaitForResponse');
        if (!GetValueBoolean($EventID))
            return false;
        $BufferID = $this->GetIDForIdent('BufferOUT');
//        $DataIn = json_encode($LSQDataIn);
//IPS_LogMessage('checkResponse',print_r($Command,1));
        if ($Command == GetValueString($BufferID))
        {
            if ($this->lock('BufferOut'))
            {
                SetValueString($BufferID, $Value);
                SetValueBoolean($EventID, false);
                $this->unlock('BufferOut');
                return true;
            }
            return 'Error on write ResponseBuffer';
        }
        return false;
    }

################## SEMAPHOREN Helper  - private  

    private function lock($ident)
    {
        for ($i = 0; $i < 100; $i++)
        {
            if (IPS_SemaphoreEnter("LMS_" . (string) $this->InstanceID . (string) $ident, 1))
            {
                //            IPS_LogMessage((string) $this->InstanceID, "Lock:LMS_" . (string) $this->InstanceID . (string) $ident);

                return true;
            }
            else
            {
                IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    private function unlock($ident)
    {
        //      IPS_LogMessage((string) $this->InstanceID, "Unlock:LMS_" . (string) $this->InstanceID . (string) $ident);

        IPS_SemaphoreLeave("LMS_" . (string) $this->InstanceID . (string) $ident);
    }

################## DUMMYS / WOARKAROUNDS - protected

    protected function HasActiveParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            if ($parent['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }

    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    /*
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
     */

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