<?

require_once(__DIR__ . "/../SqueezeBoxClass.php");  // diverse Klassen

class SqueezeboxDevice extends IPSModule
{

    const isMAC = 1;
    const isIP = 2;

    protected $Address, $Interval, $Connected;

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
                        $this->ApplyChanges();
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
                        $this->ApplyChanges();
                    }
                }
                else
                { // OK : nun Länge prüfen
                    //Länge muss 17 sein, sonst löschen                
                    if (strlen($Address) <> 17)
                    {                    //Länge muss 17 sein, sonst löschen
                        $Address = '';
                        // STATUS config falsch
                        $this->ApplyChanges();
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
        $this->RegisterVariableInteger("Index", "Playlist Position", "", 12);


        $this->RegisterVariableString("Album", "Album", "", 20);
        $this->RegisterVariableString("Title", "Title", "", 21);
        $this->RegisterVariableString("Interpret", "Interpret", "", 22);
        $this->RegisterVariableString("Genre", "Stilrichtung", "", 23);
        $this->RegisterVariableString("Duration", "Dauer", "", 24);
        $this->RegisterVariableString("Position", "Spielzeit", "", 25);
        $this->RegisterVariableInteger("Position2", "Position", "Intensity.Squeezebox", 26);
        $this->EnableAction("Position2");
        $this->RegisterVariableString("Cover", "Cover", "~HTMLBox", 27);
        $this->RegisterVariableInteger("Signal", "Signalstärke", "Intensity.Squeezebox", 30);

        // Workaround für persistente Daten der Instanz.
        $this->RegisterVariableString("BufferOUT", "BufferOUT", "", -1);
        $this->RegisterVariableBoolean("WaitForResponse", "WaitForResponse", "", -1);
        $this->RegisterVariableBoolean("Connected", "Connected", "", -1);

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
                    {
                        $this->Connected = true;
                        $this->SyncClient();
                    }
                    // nicht connected
                    else
                        $this->Connected = false;
                    $this->SetValueBoolean('Connected', $this->Connected);
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

    public function RawSend($Text)
    {
//        return $this->SendDataToParent($Text);
    }

    public function Previous()
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
//$this->SendLSQData(new LSQData('button', 'play'));        
        //Play sendet keine direkte Antwort
        //Umbauen auf 'button play' -> schlecht da sonst versehentlich andere Aktionen ausgelöst werden können.
        return $this->SendLSQData(new LSQData('play', ''));
        /*        {
          $this->SetValueInteger($this->GetIDForIdent('Status'), 2);
          return true;
          }
          else
          {
          $this->SetValueInteger($this->GetIDForIdent('Status'), 1);
          return false;
          } */
//        return $this->SendLSQData(new LSQData('button','play'));        
    }

    public function Pause()
    {
//        $this->SendLSQData(new LSQData('button', 'pause'));        
        return boolval($this->SendLSQData(new LSQData('pause', '1')));
        /*        if ($ret == 1)
          return true;
          else
          return false; */
    }

    public function Next()
    {
        return $this->SendLSQData(new LSQData(array('button', 'jump_fwd'), ''));
    }

    public function SetVolume($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'volume'), $Value));
        return ($ret == $Value);
        /*
          if ($ret == $Value)
          return true;
          else
          return false; */
    }

    public function SetBass($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'bass'), $Value));
        return ($ret == $Value);
        /*
          if ($ret == $Value)
          return true;
          else
          return false; */
    }

    public function SetTreble($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'treble'), $Value));
        return ($ret == $Value);
        /*
          if ($ret == $Value)
          return true;
          else
          return false; */
    }

    public function SetPitch($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'pitch'), $Value));
        return ($ret == $Value);
        /*
          if ($ret == $Value)
          return true;
          else
          return false; */
    }

    public function SelectPreset($Value)
    {
        return $this->SendLSQData(new LSQData(array('button', 'preset_' . (int) $Value . '.single'), ''));
    }

    public function Mute($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('mixer', 'muting'), intval($Value)));
        return ($ret == $Value);
        /*
          if ($ret == $Value)
          return true;
          else
          return false; */
    }

    public function Power($Value)
    {
        $ret = $this->SendLSQData(new LSQData('power', intval($Value)));
        return ($ret == $Value);
        /*
          if ($ret == $Value)
          return true;
          else
          return false; */
    }

    public function Repeat($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'repeat'), intval($Value)));
        return ($ret == $Value);
        /*
          if ($ret == $Value)
          return true;
          else
          return false; */
    }

    public function Shuffle($Value)
    {
        $ret = $this->SendLSQData(new LSQData(array('playlist', 'shuffle'), intval($Value)));
        return ($ret == $Value);
        /*
          if ($ret == $Value)
          return true;
          else
          return false; */
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
                        $this->Previous();
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
                        $this->Next();
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
                break;
            default:
                throw new Exception("Invalid ident");
        }
    }

################## PRIVATE

    private function decodeLSQEvent($LSQEvent)
    {
        /*        $coverID = $this->GetIDForIdent("Cover");
          $albumID = $this->GetIDForIdent("Album");
          $interpretID = $this->GetIDForIdent("Interpret");
          $durationID = $this->GetIDForIdent("Duration");
          $positionID = $this->GetIDForIdent("Position");
          $position2ID = $this->GetIDForIdent("Position2");
          $titleID = $this->GetIDForIdent("Title");
          $indexID = $this->GetIDForIdent("Index");
          $signalID = $this->GetIDForIdent("Signal");
          $tracksID = $this->GetIDForIdent("Tracks");
          $genreID = $this->GetIDForIdent("Genre");
         */
        $MainCommand = is_array($LSQEvent->Command) ? $LSQEvent->Command[0] : $LSQEvent->Command;
        switch ($MainCommand)
        {
            case LSQResponse::connected:
                if ($LSQEvent->Value == 1)
                {
                    $this->Connected = true;
                    $this->SyncClient();
                }
                else
                    $this->Connected = false;
                $this->SetValueBoolean('Connected', $this->Connected);
                break;
            case LSQResponse::signalstrength:
            case LSQResponse::name:

            case LSQResponse::sleep:
            case LSQResponse::sync:
            case LSQResponse::linesperscreen:
            case LSQResponse::button: // sollte nie kommen ?
                IPS_LogMessage('decodeLSQEvent', 'LSQResponse::button:' . $LSQEvent->Value);
                break;
            case LSQResponse::irenable:
            case LSQResponse::connect:
                // fehlt noch
                break;
            case LSQResponse::playlist . ' ' . LSQResponse::pause:
            case LSQResponse::pause:
                if (boolval($LSQEvent->Value))
                    $this->SetValueInteger('Status', 3);
                else
                    $this->SetValueInteger('Status', 2);
                break;
            case LSQResponse::playlist . ' ' . LSQResponse::play:
            case LSQResponse::play:
                $this->SetValueInteger('Status', 2);
                break;
            //case LSQResponse::playlist . ' ' . LSQResponse::stop:
            case LSQResponse::stop:
                $this->SetValueInteger('Status', 1);
//                IPS_LogMessage('decodeLSQEvent', 'LSQResponse::' . $LSQEvent->Command . ':' . $LSQEvent->Value);
                break;

            case LSQResponse::mode:
                $this->SetValueInteger('Status', $LSQEvent->GetModus());
                IPS_LogMessage('decodeLSQEvent', 'LSQResponse::' . $LSQEvent->Command . ':' . $LSQEvent->Value . ':' . $LSQEvent->GetModus());
                break;
            case LSQResponse::client:
                if ($LSQEvent->Value == 'disconnect')
                {
                    $this->SetValueBoolean('Power', false);
                    $this->Connected = false;
                    $this->SetValueBoolean('Connected', false);
                }
                elseif ($LSQEvent->Value == 'new')
                {
                    $this->SetValueBoolean('Power', true);
                    $this->Connected = true;
                    $this->SetValueBoolean('Connected', true);
                }
                break;
            case LSQResponse::power:
                $this->SetValueBoolean('Power', boolval($LSQEvent->Value));
                break;
            case LSQResponse::mixer:
                switch ($LSQEvent->Command[1])
                {
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
                }
                break;
            case LSQResponse::playlist:
                switch ($LSQEvent->Command[1])
                {
                    case LSQResponse::repeat:
                        $this->SetValueInteger('Repeat', (int) ($LSQEvent->Value));
                        break;
                    case LSQResponse::shuffle:
                        $this->SetValueInteger('Shuffle', (int) ($LSQEvent->Value));
                        break;
                }
                break;
        }



        /* 14.07.2015 19:30:12? | IODevice DECODE? | Array
          (
          [0] => 00:04:20:2b:9d:ae
          [1] => status
          [2] => -
          [3] => 1
          [4] => subscribe%3A2
          [5] => player_name%3ASqueezebox%20Family
          [6] => player_connected%3A1
          [7] => player_ip%3A192.168.201.83%3A39937
          [8] => power%3A1
          [9] => signalstrength%3A100
          [10] => mode%3Aplay
          [11] => time%3A110.039440977097
          [12] => rate%3A1
          [13] => duration%3A275.85
          [14] => can_seek%3A1
          [15] => mixer%20volume%3A16
          [16] => playlist%20repeat%3A0
          [17] => playlist%20shuffle%3A0
          [18] => playlist%20mode%3Aoff
          [19] => seq_no%3A162
          [20] => playlist_cur_index%3A5
          [21] => playlist_timestamp%3A1436894784.2188
          [22] => playlist_tracks%3A9
          [23] => playlist%20index%3A5
          [24] => id%3A20877
          [25] => title%3A%E5%B0%91%E5%A5%B3%E3%81%9F%E3%81%A1%E3%82%88%20%5BNO%20NAME%20ver.%5D
          [26] => genre%3ASoundtrack%2FAnime
          [27] => artist%3ANO%20NAME
          [28] => album%3AAKB0048%20Complete%20Vocal%20Collection
          [29] => duration%3A275.85
          )
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

        /*
          //Titel-Tag aktualisieren
          if (($array[1] == 'playlist') and ( $array[2] == 'newsong'))
          {
          $this->SetValueString($titleID, utf8_decode(urldecode($array[3])));
          $this->SetValueInteger($modusID, 2); // Button auf play
          // Subscribe auf entsprechende Box f??r Anzeige der Laufzeit
          //            $this->SendDataToParent("status - 1 subscribe:".$this->Interval);
          //            $this->SendDataToParent("artist ?");
          //            $this->SendDataToParent("album ?");
          //            IPS_Sleep(10);
          $this->SetValueString($coverID, $this->GetCover($array[0]));
          }
          // Album aktualisieren
          if ($array[1] == 'album')
          {
          if (isset($array[2]))
          $this->SetValueString($albumID, utf8_decode(urldecode($array[2])));
          else
          $this->SetValueString($albumID, '');
          }
          // Artist aktualisieren
          if ($array[1] == 'artist')
          {
          if (isset($array[2]))
          $this->SetValueString($interpretID, utf8_decode(urldecode($array[2])));
          else
          $this->SetValueString($interpretID, '');
          }
          }
          if (($array[1] == 'status') and ( isset($array[4]) and ( $array[4] == 'subscribe%3A2')))
          {
          foreach ($array as $item)
          {
          $item = utf8_decode(urldecode($item));
          $chunks = explode(":", $item);
          if ($chunks[0] == "time")
          {
          $this->SetValueString($positionID, @date('i:s', $chunks[1]));
          $time = $chunks[1];
          //                IPS_LogMessage("Squeeze",date('i:s', $chunks[1]));
          }
          if ($chunks[0] == "duration")
          {
          $this->SetValueString($durationID, @date('i:s', $chunks[1]));
          $duration = $chunks[1];
          //                IPS_LogMessage("Squeeze",date('i:s', $chunks[1]));
          }
          if ($chunks[0] == "mode")
          {
          if ($chunks[1] == 'play')
          {
          $this->SetValueInteger($modusID, 2);
          }
          if ($chunks[1] == 'stop')
          {
          $this->SetValueInteger($modusID, 1);
          //                        $this->SendDataToParent("status - 1 subscribe:0");
          }
          if ($chunks[1] == 'pause')
          {
          $this->SetValueInteger($modusID, 3);
          //                        $this->SendDataToParent("status - 1 subscribe:0");
          }
          }
          //rate%3A1 can_seek%3A1  playlist%20mode%3Aoff seq_no%3A91 playlist_cur_index%3A39 playlist_timestamp%3A1415459866.17632 id%3A26977 duration%3A614.034
          if ($chunks[0] == 'album')
          {
          $this->SetValueString($albumID, utf8_decode(urldecode($chunks[1])));
          }
          if ($chunks[0] == 'genre')
          {
          $this->SetValueString($genreID, utf8_decode(urldecode($chunks[1])));
          }

          if ($chunks[0] == 'artist')
          {

          $this->SetValueString($interpretID, utf8_decode(urldecode($chunks[1])));
          }
          if ($chunks[0] == 'title')
          {
          $this->SetValueString($titleID, utf8_decode(urldecode($chunks[1])));
          }

          if ($chunks[0] == 'playlist_tracks')
          {
          $this->SetValueInteger($tracksID, (int) $chunks[1]);
          }
          if ($chunks[0] == 'playlist index')
          {
          $this->SetValueInteger($indexID, (int) $chunks[1]);
          }
          if ($chunks[0] == 'signalstrength')
          {
          $this->SetValueInteger($signalID, (int) $chunks[1]);
          }

          if ($chunks[0] == 'mixer volume')
          {
          $this->SetValueInteger($volumeID, (int) urldecode($chunks[1]));
          }
          if ($chunks[0] == 'playlist repeat')
          $this->SetValueInteger($repeatID, (int) $chunks[1]);
          if ($chunks[0] == 'playlist shuffle')
          $this->SetValueInteger($shuffleID, (int) $chunks[1]);
          if ($chunks[0] == 'power')
          {
          if ($chunks[1] == '1')
          {
          $this->SetValueBoolean($powerID, true);
          }
          else
          {
          $this->SetValueBoolean($powerID, false);
          }
          }
          }
          if (isset($duration) and isset($time))
          {
          $value = (100 / $duration) * $time;
          $this->SetValueInteger($position2ID, round($value));
          }
          }

         */
    }

    private function GetCover($player_id)
// setzt die $var_id mit dem Coverbild von $player_id
    {
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            $SQserverIP = IPS_GetProperty($ParentID, 'Host') . ":" . IPS_GetProperty($ParentID, 'Webport');
            $cover = $this->ReadPropertyString("CoverSize");
//            $player_id = urlencode(implode(":", str_split($this->MAC, 2)));
            return "<table width=\"100%\" cellspacing=\"0\"><tr><td align=\"right\">"
                    . "<img src=\"http://" . $SQserverIP . "/music/current/" . $cover . "png?player=" . $player_id . "\"></img>"
                    . "</td></tr></table>";
        }
        else
            return "";
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

    private function SyncClient()
    {
        $this->SendLSQData(
                new LSQData(LSQResponse::listen, '1')
        );
        $this->SendLSQData(
                new LSQData(array(LSQResponse::mixer, LSQResponse::volume), '?')
        );
        $this->SendLSQData(
                new LSQData(array(LSQResponse::mixer, LSQResponse::pitch), '?')
        );
        $this->SendLSQData(
                new LSQData(array(LSQResponse::mixer, LSQResponse::bass), '?')
        );
        $this->SendLSQData(
                new LSQData(array(LSQResponse::mixer, LSQResponse::treble), '?')
        );
        $this->SendLSQData(
                new LSQData(array(LSQResponse::mixer, LSQResponse::muting), '?')
        );
        $this->SendLSQData(
                new LSQData(LSQResponse::mode, '?')
        );
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
                $isResponse = $this->WriteResponse($Response->Command, $Response->Value);
                if (is_bool($isResponse))
                {
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
// Auf Antwort warten....
            $ret = $this->WaitForResponse();
// SendeLock  velassen
            $this->unlock("LSQData");
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