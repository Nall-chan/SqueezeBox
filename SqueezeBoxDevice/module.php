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
        $this->RegisterVariableBoolean("can_seek", "can_seek", "", -5);
        $this->RegisterVariableString("BufferOUT", "BufferOUT", "", -4);
        $this->RegisterVariableBoolean("WaitForResponse", "WaitForResponse", "", -5);
        $this->RegisterVariableBoolean("Connected", "Connected", "", -3);

        $this->RegisterVariableInteger("PositionRAW", "PositionRAW", "", -1);
        $this->RegisterVariableInteger("DurationRAW", "DurationRAW", "", -2);
        IPS_SetHidden($this->GetIDForIdent('can_seek'), true);
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

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */
################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */
    public function RequestState()
    {
        $this->init();

        if ($this->Connected)
        {
            $this->SendLSQData(
                    new LSQData(LSQResponse::listen, '1')//, false)
            );
            $this->SendLSQData(
                    new LSQData(LSQResponse::power, '?', false)
            );
            $this->GetVolume();
            $this->GetPitch();
            $this->GetBass();
            $this->GetTreble();
            $this->GetMute();
            $this->GetRepeat();
            $this->GetShuffle();
            /*            $this->SendLSQData(
              new LSQData(array(LSQResponse::mixer, LSQResponse::volume), '?', false)
              );
              $this->SendLSQData(
              new LSQData(array(LSQResponse::mixer, LSQResponse::pitch), '?', false)
              );
              $this->SendLSQData(
              new LSQData(array(LSQResponse::mixer, LSQResponse::bass), '?', false)
              );
              $this->SendLSQData(
              new LSQData(array(LSQResponse::mixer, LSQResponse::treble), '?', false)
              );
              $this->SendLSQData(
              new LSQData(array(LSQResponse::mixer, LSQResponse::muting), '?', false)
              ); */
            $this->SendLSQData(
                    new LSQData(LSQResponse::mode, '?', false)
            );
            $this->SendLSQData(
                    new LSQData(LSQResponse::signalstrength, '?', false)
            );
            $this->SendLSQData(
                    new LSQData(LSQResponse::name, '?', false)
            );
            // Playlist holen
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

    public function SetName($Name)
    {
        $ret = $this->SendLSQData(new LSQData(LSQResponse::name, urlencode((string) $Name)));
        if ($ret == $Name)
        {
            $this->_NewName($Name);
            return true;
        }
        return false;
    }

    private function _NewName($Name)
    {

        if (IPS_GetName($this->InstanceID) <> trim($Name))
        {
            IPS_SetName($this->InstanceID, trim($Name));
        }
    }

    public function GetName()
    {
        $Name = urldecode($this->SendLSQData(new LSQData(LSQResponse::name, '?')));
        $this->_NewName($Name);
        return $Name;
    }

    /*
      public function SetSleep($Value)
      {
      $ret = $this->SendLSQData(new LSQData(LSQResponse::sleep, (int) $Value));
      return ($ret == $Value);
      }

      public function GetSleep()
      {
      return $this->SendLSQData(new LSQData(LSQResponse::sleep, '?'));
      } */

    public function PreviousButton()
    {
        if ($this->SendLSQData(new LSQData(array('button', 'jump_rew'), '')))
        {
            $this->_SetPlay();
            return true;
        }
        return false;
    }

    public function NextButton()
    {
        if ($this->SendLSQData(new LSQData(array('button', 'jump_fwd'), '')))
        {
            $this->_SetPlay();
            return true;
        }
        return false;
    }

    public function Play()
    {
        if ($this->SendLSQData(new LSQData('play', '')))
        {
            $this->_SetPlay();
            return true;
        }
        return false;
    }

    public function Stop()
    {
        if ($this->SendLSQData(new LSQData('stop', '')))
        {
            $this->_SetStop();
            return true;
        }
        return false;
    }

    public function Pause()
    {
        if (boolval($this->SendLSQData(new LSQData('pause', '1'))))
        {
            $this->_SetPause();
            return true;
        }
        return false;
    }

    public function SetVolume($Value)
    {
        if (($Value < 0) or ( $Value > 100))
            throw new Exception("Value invalid.");
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'volume'), $Value));
        $this->_NewVolume($ret);
        return ($ret == $Value);
    }

    public function GetVolume()
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'volume'), '?'));
        $this->_NewVolume($ret);
        return $ret;
    }

    public function SetBass($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'bass'), $Value));
        $this->SetValueInteger('Bass', ($ret));
        return ($ret == $Value);
    }

    public function GetBass()
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'bass'), '?'));
        $this->SetValueInteger('Bass', ($ret));
        return $ret;
    }

    public function SetTreble($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'treble'), $Value));
        $this->SetValueInteger('Treble', ($ret));
        return ($ret == $Value);
    }

    public function GetTreble()
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'treble'), '?'));
        $this->SetValueInteger('Treble', ($ret));
        return $ret;
    }

    public function SetPitch($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'pitch'), $Value));
        $this->SetValueInteger('Pitch', ($ret));
        return ($ret == $Value);
    }

    public function GetPitch()
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'pitch'), '?'));
        $this->SetValueInteger('Pitch', ($ret));
        return $ret;
    }

    public function SetMute($Value)
    {
        if (!is_bool($Value))
            throw new Exception("Value must boolean.");
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'muting'), intval($Value)));
        $this->SetValueBoolean('Mute', boolval($ret));
        return ($ret == $Value);
    }

    public function GetMute()
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'muting'), '?'));
        $this->SetValueBoolean('Mute', boolval($ret));
        return boolval($ret);
    }

    public function SetRepeat($Value)
    {
        if (($Value < 0) or ( $Value > 2))
            throw new Exception("Value must be 0, 1 or 2.");
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'repeat'), intval($Value)));
        $this->SetValueInteger('Repeat', intval($ret));
        return ($ret == $Value);
    }

    public function GetRepeat()
    {
        $ret = (int) $this->SendLSQData(new LSQData(array('playlist', 'repeat'), '?'));
        $this->SetValueInteger('Repeat', intval($ret));
        return $ret;
    }

    public function SetShuffle($Value)
    {
        if (($Value < 0) or ( $Value > 2))
            throw new Exception("Value must be 0, 1 or 2.");
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'shuffle'), intval($Value)));
        $this->SetValueInteger('Shuffle', intval($ret));
        return ($ret == $Value);
    }

    public function GetShuffle()
    {
        $ret = (int) $this->SendLSQData(new LSQData(array('playlist', 'shuffle'), '?'));
        $this->SetValueInteger('Shuffle', intval($ret));
        return $ret;
    }

    public function SelectPreset($Value)
    {
        if (($Value < 1) or ( $Value > 6))
            throw new Exception("Value invalid.");
        return $this->SendLSQData(new LSQData(array('button', 'preset_' . (int) $Value . '.single'), ''));
    }

    public function Power($Value)
    {
        if (!is_bool($Value))
            throw new Exception("Value must boolean.");
        $ret = $this->SendLSQData(new LSQData('power', intval($Value)));
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
        return ($ret == '%2D1');
    }

    public function SetPosition($Value)
    {
        if (!is_float($Value))
            throw new Exception("Value must be integer.");
        $ret = $this->SendLSQData(new LSQData('time', $Value));
        return ($ret == $Value);
    }

    public function SavePlaylist($Name)
    {
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'save'), urlencode($Name) . ' silent:1'));
        return ($ret == urldecode($Name));
    }

    public function LoadPlaylist($Name)
    {
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'load'), urlencode($Name) . ' silent:1'));
        return ($ret == urldecode($Name));
    }

    public function GetSongInfoByTrackIndex($Index)
    {
        if (is_int($Index))
            $Index--;
        $Data = $this->SendLSQData(new LSQData(array('status', (string) $Index, '1'), 'tags:gladiqrRt'));
        $Song = $this->DecodeSongInfo($Data)[0];
        IPS_LogMessage('SONGINFO', print_r($Song, 1));
        return $Song;
    }

    private function _SetPlay()
    {
        $this->SetValueBoolean('Power', true);
        if (GetValueInteger($this->GetIDForIdent('Status')) <> 2)
        {

            $this->SetValueInteger('Status', 2);
            $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
        }
    }

    private function _SetStop()
    {
        if (GetValueInteger($this->GetIDForIdent('Status')) <> 1)
        {
            $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:0', false));
            $this->SetValueInteger('Status', 1);
        }
    }

    private function _SetPause()
    {
        if (GetValueInteger($this->GetIDForIdent('Status')) <> 3)
        {

            $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:0', false));
            $this->SetValueInteger('Status', 3);
        }
    }

    private function _NewVolume($Value)
    {
        $Value = (int) ($Value);
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
    }

    private function _SetSeekable($Value)
    {
        $this->SetValueBoolean('can_seek', boolval($Value));
        if (boolval($Value))
            $this->EnableAction("Position2");
        else
            $this->DisableAction('Position2');
    }

    private function DecodeSongInfo($Data)
    {
        $id = 0;
        $Songs = array();
        $SongFields = array(
            'id',
            'title',
            'genre',
            'album',
            'artist',
            'duration',
            'disc',
            'disccount',
            'bitrate',
            'tracknum'
        );
        foreach (explode(' ', $Data) as $Line)
        {
            $LSQPart = $this->decodeLSQTaggingData($Line, false);


            if (is_array($LSQPart->Command) and ( $LSQPart->Command[0] == LSQResponse::playlist) and ( $LSQPart->Command[0] == LSQResponse::index))
            {
                $id = (int) $LSQPart->Value;
                continue;
            }
            if (in_array($LSQPart->Command, $SongFields))
                $Songs[$id][$LSQPart->Command] = urldecode($LSQPart->Value);
        }
        IPS_LogMessage('SONGINFO', print_r($Songs, 1));
        return $Songs;
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
                $this->SetMute($Value);
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
                $this->SetPosition($Time);
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
        {
            $MainCommand = array_shift($LSQEvent->Command);
//            IPS_LogMessage('CommandLSQEvent', print_r($LSQEvent->Command, 1));
        }
        else
        {
//            IPS_LogMessage('CommandLSQEvent', $LSQEvent->Command);

            $MainCommand = $LSQEvent->Command;
        }
//        IPS_LogMessage('MAINCommandLSQEvent', $MainCommand);

        switch ($MainCommand)
        {
            case LSQResponse::player_connected:
                if (GetValueBoolean($this->GetIDForIdent('Connected')) <> boolval($LSQEvent->Value))
                {
                    $this->SetConnected(true);
                }

                break;
            case LSQResponse::connected:
                if (!$LSQEvent->isResponse) //wenn Response, dann macht der Anfrager das selbst
                {
                    if ($LSQEvent->Value == 1)
                        $this->SetConnected(true);
                    else
                        $this->SetConnected(false);
                }
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
            case LSQResponse::player_name:
            case LSQResponse::name:
                $this->_NewName(urldecode((string) $LSQEvent->Value));
                break;
            case LSQResponse::signalstrength:
                $this->SetValueInteger('Signalstrength', (int) $LSQEvent->Value);
                break;
            case LSQResponse::player_ip:
                //wegwerfen, solange es keinen SetSummary gibt
                break;
            case LSQResponse::power:
                $this->SetValueBoolean('Power', boolval($LSQEvent->Value));
                break;

            case LSQResponse::play:
                $this->_SetPlay();
                /* if (GetValueInteger($this->GetIDForIdent('Status')) <> 2)
                  {
                  $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
                  $this->SetValueInteger('Status', 2);
                  } */
                break;
            case LSQResponse::stop:
                /* if (GetValueInteger($this->GetIDForIdent('Status')) <> 1)
                  {
                  $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:0', false));
                  $this->SetValueInteger('Status', 1);
                  } */
                $this->_SetStop();
                break;
            case LSQResponse::pause:
                if ($LSQEvent->Value == '')
                {
                    $this->_SetPause();
                }
                elseif (boolval($LSQEvent->Value))
                {
                    $this->_SetPause();
                    /* if (GetValueInteger($this->GetIDForIdent('Status')) <> 3)
                      {
                      $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:0', false));
                      $this->SetValueInteger('Status', 3);
                      } */
                }
                else
                {
                    $this->_SetPlay();
                    /* if (GetValueInteger($this->GetIDForIdent('Status')) <> 2)
                      {

                      $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
                      $this->SetValueInteger('Status', 2);
                      } */
                }
                break;

            case LSQResponse::mode:
//                IPS_LogMessage('MODE', print_r($LSQEvent, 1));
                if (is_array($LSQEvent->Command))
                    $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                else
                    $this->decodeLSQEvent(new LSQEvent($LSQEvent->Value, '', $LSQEvent->isResponse));
                break;
            case LSQResponse::mixer:
                $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                break;
            case LSQResponse::volume:
                if (is_array($LSQEvent->Value))
                    $this->_NewVolume((int) $LSQEvent->Value[0]);
                else
                    $this->_NewVolume((int) $LSQEvent->Value);
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
            case LSQResponse::muting:
                $this->SetValueBoolean('Mute', boolval($LSQEvent->Value));
                break;
            case LSQResponse::repeat:
                $this->SetValueInteger('Repeat', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::shuffle:
                $this->SetValueInteger('Shuffle', (int) ($LSQEvent->Value));
                break;
            /*            case LSQResponse::sleep:
              $this->SetValueInteger('SleepTimeout', (int) $LSQEvent->Value);
              break; */
            /*          case LSQResponse::button:
              $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
              break; */
            /*            case LSQButton::jump_fwd:
              case LSQButton::jump_rew:
              $this->SetPlay();
              break; */
            case LSQResponse::sync:
            case LSQResponse::rate:
            case LSQResponse::seq_no:
            case LSQResponse::playlist_timestamp:
            case LSQResponse::linesperscreen:
            case LSQResponse::irenable:
            case LSQResponse::connect:
            case LSQResponse::waitingToPlay:
            case LSQResponse::jump:
            case LSQResponse::open:
                //ignore
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
                $this->SendLSQData(new LSQData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
//                IPS_Sleep(500);
                $this->SetCover();
                break;
            case LSQResponse::newmetadata:
                $this->SetCover();
                break;
            case LSQResponse::playlist:
                if (($LSQEvent->Command[0] <> LSQResponse::stop)  //Playlist stop kommt auch bei fwd ?
                        and ( $LSQEvent->Command[0] <> LSQResponse::mode))
                    $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                break;
            case LSQResponse::prefset:
                /*                if ($LSQEvent->Command[0] == 'server')
                  {
                  $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[1], $LSQEvent->Value, $LSQEvent->isResponse));
                  }
                  else
                  {
                  IPS_LogMessage('prefsetLSQEvent', 'Namespace' . $LSQEvent->Command[0] . ':' . $LSQEvent->Value);
                  } */
                break;
            case LSQResponse::title:
                $this->SetValueString('Title', trim(urldecode($LSQEvent->Value)));
                break;
            case LSQResponse::artist:
                $this->SetValueString('Interpret', trim(urldecode($LSQEvent->Value)));
                break;
            case LSQResponse::current_title:
            case LSQResponse::album:
                /*
                00%3A04%3A20%3A2e%3A57%3Aee
                status
                -
                1
                subscribe%3A0
                player_name%3ASqueezebox%20Micha%20
                player_connected%3A1
                player_ip%3A192.168.201.81%3A40828
                power%3A1
                signalstrength%3A83
                mode%3Apause
                remote%3A1
                current_title%3AJapan-A-Radio%20-%20Japan's%20best%20music%20mix!
                time%3A399.420708084106
                rate%3A1
                mixer%20volume%3A19
                playlist%20repeat%3A0
                playlist%20shuffle%3A0
                playlist%20mode%3Aoff
                seq_no%3A81
                playlist_cur_index%3A0
                playlist_timestamp%3A1437755193.00634
                playlist_tracks%3A1
                remoteMeta%3AHASH(0xb64e9ac)
                playlist%20index%3A0
                id%3A-168804412
                title%3AGenius...!%3F
                artist%3Ahoukago%20ti%20taimu
                duration%3A0
                */
                
/*
                if (is_array($LSQEvent->Value))
                {
                    IPS_LogMessage('album/title',  $MainCommand . '-' . print_r($LSQEvent->Value, 1));
                $this->SetValueString('Album', trim(urldecode($LSQEvent->Value[0])));                    
                }
                else
                {
                    IPS_LogMessage('album/title', $MainCommand . '-' . $LSQEvent->Value);                        
                $this->SetValueString('Album', trim(urldecode($LSQEvent->Value)));                    
                }*/
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
                if ($LSQEvent->Value == 0)
                { // alles leeren
                    $this->SetValueString('Title', '');
                    $this->SetValueString('Interpret', '');
                    $this->SetValueString('Album', '');
                    $this->SetValueString('Genre', '');
                    $this->SetValueString('Duration', '0:00');
                    $this->SetValueInteger('DurationRAW', 0);
                    $this->SetValueInteger('Position2', 0);
                    $this->SetValueInteger('PositionRAW', 0);
                    $this->SetValueString('Position', '0:00');

                    $this->SetValueInteger('Index', 0);
                    $this->SetCover();
                }
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
                array_shift($LSQEvent->Value);
                if ($LSQEvent->Command[0] == '-')// and ( $LSQEvent->Command[1] == '1') and ( strpos($Event, "subscribe%3A") > 0))
                {
//                    IPS_LogMessage('subscribeLSQEvent', print_r($LSQEvent->Value, 1));
                    /*        $SongFields = array(
                      'id',
                      'title',
                      'genre',
                      'album',
                      'artist',
                      'duration',
                      'player_connected',
                      'power',
                      'signalstrength',
                      'mode',
                      'time',
                      'can_seek',
                      'mixer%20volume',

                      );
                      'playlist%20repeat'
                      'playlist%20shuffle'
                      'playlist_cur_index'
                      'playlist_tracks'
                      'playlist%20index'

                      'id'
                      'title'
                      'genre'
                      'artist'
                      'album'
                      'duration' */

                    foreach ($LSQEvent->Value as $Data)
                    {

                        $LSQPart = $this->decodeLSQTaggingData($Data, $LSQEvent->isResponse);
                        //                  IPS_LogMessage('ValueLSQEvent', print_r($Value, 1));
                        //                      if (in_array($LSQPart->Command, $SongFields))                        
                        $this->decodeLSQEvent($LSQPart);
                    }
                }

// ALT                 
                /*                if ($LSQEvent->Command[0] == '0') //and ( strpos($Event, "tags%3A") > 0))
                  { //Daten für Playlist dekodieren und zurückgeben
                  //                    IPS_LogMessage('statusLSQEvent', print_r($LSQEvent->Value, 1));
                  $Songs = $this->DecodeSongInfo($LSQEvent->Value);
                  IPS_LogMessage('statusLSQEvent', print_r($Songs, 1));
                  } */
                break;
            case LSQResponse::can_seek:
                if (GetValueBoolean($this->GetIDForIdent('can_seek')) <> boolval($LSQEvent->Value))
                {
                    $this->_SetSeekable(boolval($LSQEvent->Value));
//                    $this->SetValueBoolean('can_seek', boolval($LSQEvent->Value));
                }
                break;
            case LSQResponse::remote:
                if (GetValueBoolean($this->GetIDForIdent('can_seek')) == boolval($LSQEvent->Value))
                {
                    $this->_SetSeekable(!boolval($LSQEvent->Value));
//                    $this->SetValueBoolean('can_seek', boolval($LSQEvent->Value));
                }
                break;
            case LSQResponse::index:
            case LSQResponse::playlist_cur_index:
            case LSQResponse::currentSong:
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
//        $Part = explode(chr(0x3a), urldecode($Data));
        $Part = explode('%3A',$Data);//        
//        IPS_LogMessage('PartLSQEvent', print_r($Part, 1));
        $Command = urldecode(array_shift($Part));
        if (!(strpos($Command, chr(0x20)) === false))
        {
            $Command = explode(chr(0x20), $Command);
        }
        // alle playlist_xxx auch zerlegen ?
        if (isset($Part[1]))
        {
            $Value = $Part;
        }
        else
        {
            $Value = $Part[0];
        }
        IPS_LogMessage('decodeLSQTaggingData',print_r($Command, 1). print_r($Value, 1));
        
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
        $this->RequestState();
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
                IPS_LogMessage('Splitter',print_r($Data,1));
        
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
//                IPS_LogMessage('EMPFANG', print_r($Response, 1));

                $isResponse = $this->WriteResponse($Response->Command, $Response->Value);
                if (is_bool($isResponse))
                {
                    $Response->isResponse = $isResponse;
                    // Daten dekodieren
                    if (!$isResponse)
                    {
                                        IPS_LogMessage('Splitter',print_r($Response,1));

                        $this->decodeLSQEvent($Response);
                    }
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
//            IPS_LogMessage('SENDE', print_r($LSQData, 1));
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
            /*            if ($ret === true)
              IPS_LogMessage('FOUND RESPONSE', 'TRUE');
              else
              IPS_LogMessage('FOUND RESPONSE', print_r($ret, 1)); */
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