<?

require_once(__DIR__ . "/../SqueezeBoxClass.php");  // diverse Klassen

class SqueezeboxDevice extends IPSModule
{

    const isMAC = 1;
    const isIP = 2;

    private $Address, $Interval, $Connected = 'noInit', $tempData;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // 1. Verfügbarer LMS-Splitter wird verbunden oder neu erzeugt, wenn nicht vorhanden.
        $this->ConnectParent("{96A9AB3A-2538-42C5-A130-FC34205A706A}");

        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyInteger("Interval", 2);
        $this->RegisterPropertyString("CoverSize", "cover");

        $ID = $this->RegisterScript('PlaylistDesign', 'Playlist Config', $this->CreatePlaylistConfigScript(), -7);
        IPS_SetHidden($ID, true);
        $this->RegisterPropertyInteger("Playlistconfig", $ID);
    }

    public function Destroy()
    {
        parent::Destroy();

        $CoverID = @IPS_GetObjectIDByIdent('CoverIMG', $this->InstanceID);
        if ($CoverID !== false)
            @IPS_DeleteMedia($CoverID, true);
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
            $this->SetStatus(104);
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
                            $this->SetStatus(102);
                            // STATUS config OK
                        }
                        else
                        {
                            $Address = '';
                            $this->SetStatus(202);
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
                            $this->SetStatus(102);
                            // STATUS config OK
                        }
                        else
                        {                    //Länge muss 17 sein, sonst löschen
                            $this->SetStatus(202);

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
                        $this->SetStatus(202);

                        $Address = '';
                        // STATUS config falsch
                        IPS_SetProperty($this->InstanceID, 'Address', $Address);
                        IPS_ApplyChanges($this->InstanceID);
                        return;
                    }
                }
            }
            // TODO IP-Adresse prüfen fehlt !!!!
        }


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
            Array(2, "Playlist", "", -1)
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
        $this->RegisterProfileIntegerEx("SleepTimer.Squeezebox", "Gear", "", "", Array(
            Array(0, "0", "", -1),
            Array(900, "900", "", -1),
            Array(1800, "1800", "", -1),
            Array(2700, "2700", "", -1),
            Array(3600, "3600", "", -1),
            Array(5400, "5400", "", -1)
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
        $this->RegisterVariableInteger("Index", "Playlist Position", "Tracklist.Squeezebox." . $this->InstanceID, 12);
        $this->EnableAction("Index");

        $this->RegisterVariableString("Playlistname", "Playlist", "", 19);
        $this->RegisterVariableString("Album", "Album", "", 20);
        $this->RegisterVariableString("Title", "Titel", "", 21);
        $this->RegisterVariableString("Interpret", "Interpret", "", 22);
        $this->RegisterVariableString("Genre", "Stilrichtung", "", 23);
        $this->RegisterVariableString("Duration", "Dauer", "", 24);
        $this->RegisterVariableString("Position", "Spielzeit", "", 25);
        $this->RegisterVariableInteger("Position2", "Position", "Intensity.Squeezebox", 26);
        $this->EnableAction("Position2");
        $this->RegisterVariableString("Playlist", "Playlist", "~HTMLBox", 29);

        $this->RegisterVariableInteger("Signalstrength", utf8_encode("Signalstärke"), "Intensity.Squeezebox", 30);
        $this->RegisterVariableInteger("SleepTimer", "Einschlaftimer", "SleepTimer.Squeezebox", 31);
        $this->EnableAction("SleepTimer");
        $this->RegisterVariableString("SleepTimeout", "Ausschalten in ", "", 32);

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

        // Eigene Scripte
        $sid = $this->RegisterScript("WebHookPlaylist", "WebHookPlaylist", '<? //Do not delete or modify.
if (isset($_GET["Index"]))
    LSQ_PlayTrack(' . $this->InstanceID . ',$_GET["Index"]);
', -8);
        IPS_SetHidden($sid, true);
        $this->RegisterHook('/hook/SqueezeBoxPlaylist' . $this->InstanceID, $sid);

        $ID = $this->RegisterScript('PlaylistDesign', 'Playlist Config', $this->CreatePlaylistConfigScript(), -7);
        IPS_SetHidden($ID, true);

        // Adresse nicht leer ?
        // Parent vorhanden und nicht in Fehlerstatus ?
        if ($this->Init(false))
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
        $this->_SetSeekable((bool) GetValueBoolean($this->GetIDForIdent('can_seek')));
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
    public function GenerateTTSFile(string $Text, string $File = null)
    {
        // File erzeugen
    }

    /**
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen..
     *
     * @return boolean
     */
    public function RequestAllState()
    {
        $this->Init();
        /*        $this->init();

          if ($this->Connected)
          { */

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

        /*
          $this->SendLSQData(
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
                new LSQData(array('status', 0, '1'), 'tags:gladiqrRtueJINpsy')
        );
        SetValueInteger($this->GetIDForIdent('Status'), 1);
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
        /*        }
          else
          {
          $this->SetValueBoolean('Power', false);
          } */
        return true;
    }

    /**
     * Setzten den Namen in dem Device.
     *
     * @param string $Name 
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function RequestState(string $Ident)
    {
        switch ($Ident)
        {
            case "Power":
                $Data = new LSQData(LSQResponse::power, '?', false);
                break;

            case "Status":
                $Data = new LSQData(LSQResponse::status, '?', false);
                break;
            case "Mute":
                break;
            case "Volume":
                break;
            case "Bass":
                break;
            case "Treble":
                break;
            case "Pitch":
                break;
            case "Shuffle":
                break;
            case "Repeat":
                break;
            case "Tracks":
            case "Index":
            case "Album":
            case "Title":
            case "Interpret":
            case "Genre":
            case "Duration":
            case "Position":
                break;
            case "Signalstrength":
                break;

            case "SleepTimeout":
            default:
                break;
        }
    }

    public function RawSend($Command, $Value, $needResponse)
    {
        $this->Init();

        $LSQData = new LSQData($Command, $Value, $needResponse);
        return $this->SendLSQData($LSQData);
        //return $this->SendDataToParent($LSQData);
    }

    /**
     * Setzten den Namen in dem Device.
     *
     * @param string $Name 
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SetName(string $Name)
    {
        $this->Init();

        $ret = rawurldecode($this->SendLSQData(new LSQData(LSQResponse::name, rawurlencode((string) $Name))));
        if ($ret == $Name)
        {
            $this->_NewName($Name);
            return true;
        }
        return false;
    }

    /**
     * Liefert den Namen von dem Device.
     *
     * @return string
     * @exception 
     */
    public function GetName()
    {
        $this->Init();

        $Name = rawurldecode($this->SendLSQData(new LSQData(LSQResponse::name, '?')));
        $this->_NewName($Name);
        return trim($Name);
    }

    public function SetSync(integer $SlaveInstanceID)
    {
        $id = @IPS_GetInstance($SlaveInstanceID);
        if ($id === FALSE)
            throw new Exception('Unknown LSQ_PlayerInstanz');
        if ($id['ModuleInfo']['ModuleID'] <> '{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}')
            throw new Exception('SlaveInstance in not a LSQ_PlayerInstanz');
        $ClientMac = IPS_GetProperty($SlaveInstanceID, 'Address');
        if (($ClientMac === '') or ( $ClientMac === false))
        {
            throw new Exception('SlaveInstance Address is not set.');
        }
        $ret = $this->SendLSQData(new LSQData(LSQResponse::sync, $ClientMac));
        return (rawurldecode($ret) == $ClientMac);
    }

    /**
     * Gibt alle mit diesem Gerät syncronisierte Instanzen zurück
     *
     * @return string|array
     * @exception 
     */
    public function GetSync()
    {
        $Addresses = array();
        $FoundInstanzIDs = array();
        $AllPlayerIDs = IPS_GetInstanceListByModuleID('{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}');

        foreach ($AllPlayerIDs as $DeviceID)
        {
            $Addresses[$DeviceID] = IPS_GetProperty($DeviceID, 'Address');
        }

        $ret = $this->SendLSQData(new LSQData(LSQResponse::sync, '?'));
        if ($ret == '-')
            return false;
        if (strpos($ret, ',') === false)
        {
            $FoundInstanzIDs[0] = array_search(rawurldecode($ret), $Addresses);
        }
        else
        {
            $Search = explode(',', $ret);
            foreach ($Search as $Value)
            {
                $FoundInstanzIDs[] = array_search(rawurldecode($Value), $Addresses);
            }
        }
        if (count($FoundInstanzIDs) > 0)
        {
            return $FoundInstanzIDs;
        }
        else
        {
            return false;
        }
    }

    /**
     * Sync dieses Gerätes aufheben
     *
     * @return boolean true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SetUnSync()
    {
        $ret = $this->SendLSQData(new LSQData(LSQResponse::sync, '-'));
        return ($ret == '-');
    }

    /**
     * Restzeit bis zum Sleep setzen.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SetSleep(integer $Seconds)
    {
        $ret = $this->SendLSQData(new LSQData(LSQResponse::sleep, $Seconds));
        $this->_SetSleep($Seconds);
        return ($ret == $Seconds);
    }

    /**
     * Restzeit bis zum Sleep lesen.
     *
     * @return integer
     * @exception 
     */
    public function GetSleep()
    {
        $ret = intval($this->SendLSQData(new LSQData(LSQResponse::sleep, '?')));
        $this->_SetSleep($ret);
        return $ret;
    }

    /**
     * Simuliert einen Tastendruck.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function PreviousButton()
    {
        $this->Init();

        if ($this->SendLSQData(new LSQData(array('button', 'jump_rew'), '')))
        {
            $this->_SetPlay();
            return true;
        }
        return false;
    }

    /**
     * Simuliert einen Tastendruck.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function NextButton()
    {
        $this->Init();

        if ($this->SendLSQData(new LSQData(array('button', 'jump_fwd'), '')))
        {
            $this->_SetPlay();
            return true;
        }
        return false;
    }

    /**
     * Startet die Wiedergabe
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function Play()
    {
        $this->Init();

        if ($this->SendLSQData(new LSQData('play', '')))
        {
            $this->_SetPlay();
            return true;
        }
        return false;
    }

    /**
     * Stoppt die Wiedergabe
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function Stop()
    {
        $this->Init();

        if ($this->SendLSQData(new LSQData('stop', '')))
        {
            $this->_SetStop();
            return true;
        }
        return false;
    }

    /**
     * Pausiert die Wiedergabe
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function Pause()
    {
        $this->Init();

        if ((bool) $this->SendLSQData(new LSQData('pause', '1')))
        {
            $this->_SetPause();
            return true;
        }
        return false;
    }

    /**
     * Setzten der Lautstärke.
     *
     * @param integer $Value 
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SetVolume(integer $Value)
    {
        $this->Init();

        if (($Value < 0) or ( $Value > 100))
            throw new Exception("Value invalid.");
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'volume'), $Value)));
        $this->_NewVolume($ret);
        return ($ret == $Value);
    }

    /**
     * Liefert die aktuelle Lautstärke von dem Device.
     *
     * @return integer
     * @exception 
     */
    public function GetVolume()
    {
        $this->Init();

        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'volume'), '?')));
        $this->_NewVolume($ret);
        return $ret;
    }

    /**
     * Setzt den Bass-Wert.
     *
     * @param integer $Value 
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SetBass(integer $Value)
    {
        $this->Init();

        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'bass'), $Value)));
        $this->SetValueInteger('Bass', $ret);
        return ($ret == $Value);
    }

    /**
     * Liefert den aktuellen Bass-Wert.
     *
     * @return integer
     * @exception 
     */
    public function GetBass()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'bass'), '?')));
        $this->SetValueInteger('Bass', $ret);
        return $ret;
    }

    /**
     * Setzt den Treble-Wert.
     *
     * @param integer $Value 
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SetTreble(integer $Value)
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'treble'), $Value)));
        $this->SetValueInteger('Treble', $ret);
        return ($ret == $Value);
    }

    /**
     * Liefert den aktuellen Treble-Wert.
     *
     * @return integer
     * @exception 
     */
    public function GetTreble()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'treble'), '?')));
        $this->SetValueInteger('Treble', $ret);
        return $ret;
    }

    /**
     * Setzt den Pitch-Wert.
     *
     * @param integer $Value 
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SetPitch(integer $Value)
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'pitch'), $Value)));
        $this->SetValueInteger('Pitch', $ret);
        return ($ret == $Value);
    }

    /**
     * Liefert den aktuellen Pitch-Wert.
     *
     * @return integer
     * @exception 
     */
    public function GetPitch()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array('mixer', 'pitch'), '?')));
        $this->SetValueInteger('Pitch', $ret);
        return $ret;
    }

    /**
     * Setzten der Stummschaltung.
     *
     * @param bolean $Value 
     * true = Stumm an
     * false = Stumm aus
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SetMute(boolean $Value)
    {
        $this->Init();
        if (!is_bool($Value))
            throw new Exception("Value must boolean.");
        $ret = (bool) $this->SendLSQData(new LSQData(array(LSQResponse::mixer, LSQResponse::muting), intval($Value)));
        $this->SetValueBoolean('Mute', $ret);
        return ($ret == $Value);
    }

    /**
     * Liefert den Status der Stummschaltung.
     *
     * @return boolean
     * true = Stumm an
     * false = Stumm aus
     * @exception 
     */
    public function GetMute()
    {
        $this->Init();
        $ret = (bool) $this->SendLSQData(new LSQData(array(LSQResponse::mixer, LSQResponse::muting), '?'));
        $this->SetValueBoolean('Mute', $ret);
        return $ret;
    }

    /**
     * Setzen des Wiederholungsmodus.
     *
     * @param integer $Value 
     * 0 = aus
     * 1 = Titel
     * 2 = Album
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SetRepeat(integer $Value)
    {
        $this->Init();
        if (($Value < 0) or ( $Value > 2))
            throw new Exception("Value must be 0, 1 or 2.");
        $ret = intval($this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::repeat), intval($Value))));
        $this->SetValueInteger('Repeat', $ret);
        return ($ret == $Value);
    }

    /**
     * Liefert den Wiederholungsmodus.
     *
     * @return integer
     * 0 = aus
     * 1 = Titel
     * 2 = Album
     * @exception 
     */
    public function GetRepeat()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::repeat), '?')));
        $this->SetValueInteger('Repeat', $ret);
        return $ret;
    }

    /**
     * Setzen des Zufallsmodus.
     *
     * @param integer $Value 
     * 0 = aus
     * 1 = Titel
     * 2 = Album
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SetShuffle(integer $Value)
    {
        $this->Init();
        if (($Value < 0) or ( $Value > 2))
            throw new Exception("Value must be 0, 1 or 2.");
        $ret = intval($this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::shuffle), intval($Value))));
        $this->SetValueInteger('Shuffle', $ret);
        $this->RefreshPlaylist();
        return ($ret == $Value);
    }

    /**
     * Liefert den Zufallsmodus.
     *
     * @return integer
     * 0 = aus
     * 1 = Titel
     * 2 = Album
     * @exception 
     */
    public function GetShuffle()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::shuffle), '?')));
        $this->SetValueInteger('Shuffle', $ret);
        return $ret;
    }

    /**
     * Simuliert einen Tastendruck auf einen der Preset-Tasten.
     *
     * @param integer $Value 
     * 1 - 6 = Taste 1 bis 6
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SelectPreset(integer $Value)
    {
        $this->Init();
        if (($Value < 1) or ( $Value > 6))
            throw new Exception("Value invalid.");
        return (bool) $this->SendLSQData(new LSQData(array(LSQResponse::button, 'preset_' . intval($Value) . '.single'), ''));
    }

    /**
     * Schaltet das Gerät ein oder aus.
     *
     * @access public
     * @param boolean $Value 
     * false  = ausschalten
     * true = einschalten
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function Power(boolean $Value)
    {
        $this->Init();
        if (!is_bool($Value))
            throw new Exception("Value must boolean.");
        $ret = (bool) $this->SendLSQData(new LSQData(LSQResponse::power, intval($Value)));
        return ($ret == $Value);
    }

    /**
     * Springt in der aktuellen Wiedergabeliste auf einen Titel.
     *
     * @param integer $Value 
     * Track in der Wiedergabeliste auf welchen gesprungen werden soll.
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function PlayTrack(integer $Index)
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::index), intval($Index) - 1))) + 1;
        return ($ret == $Index);
    }

    /**
     * Springt in der aktuellen Wiedergabeliste auf den nächsten Titel.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function NextTrack()
    {
        $this->Init();
        $ret = trim(rawurldecode($this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::index), '+1'))));
        return ($ret == "+1");
    }

    /**
     * Springt in der aktuellen Wiedergabeliste auf den vorherigen Titel.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function PreviousTrack()
    {
        $this->Init();
        $ret = trim(rawurldecode($this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::index), '-1'))));
        return ($ret == "-1");
    }

    /**
     * Setzt eine absolute Zeit-Position des aktuellen Titels.
     *
     * @param integer $Value Zeit in Sekunden.
     * @return boolean true bei erfolgreicher Ausführung und Rückmeldung.
     * @exception Wenn Befehl nicht ausgeführt werden konnte.
     */
    public function SetPosition(integer $Value)
    {
        $this->Init();
        if (!is_int($Value))
            throw new Exception("Value must be integer.");
        $ret = intval($this->SendLSQData(new LSQData(LSQResponse::time, $Value)));
        return ($ret == $Value);
    }

    /**
     * Liest die aktuelle Zeit-Position des aktuellen Titels.
     *
     * @return integer
     * Zeit in Sekunden.
     * @exception 
     */
    public function GetPosition()
    {
        $this->Init();
        $ret = intval($this->SendLSQData(new LSQData(LSQResponse::time, '?')));
        return $ret;
    }

    /**
     * Speichert die aktuelle Wiedergabeliste vom Gerät in einer unter $Name angegebenen Wiedergabelisten-Datei auf dem LMS-Server.
     *
     * @param string $Name
     * Der Name der Wiedergabeliste. Ist diese Liste auf dem Server schon vorhanden, wird sie überschrieben.
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SavePlaylist(string $Name)
    {
        $this->Init();
        $raw = $this->SendLSQData(new LSQData(array(LSQResponse::playlist, 'save'), array($Name, 'silent:1')));
        $ret = explode(' ', $raw);
        return (rawurldecode($ret[0]) == $Name);
    }

    /**
     * Speichert die aktuelle Wiedergabeliste vom Gerät in einer festen Wiedergabelisten-Datei auf dem LMS-Server.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function SaveTempPlaylist()
    {
        $this->Init();
        $raw = $this->SendLSQData(new LSQData(array(LSQResponse::playlist, 'save'), array((string) $this->InstanceID, 'silent:1')));
        $ret = explode(' ', $raw);
        return (rawurldecode($ret[0]) == (string) $this->InstanceID);
    }

    /**
     * Lädt eine Wiedergabelisten-Datei aus dem LMS-Server und startet die Wiedergabe derselben auf dem Gerät.
     *
     * @param string $Name
     * Der Name der Wiedergabeliste.
     * @return string
     * Kompletter Pfad der Wiedergabeliste.
     * @exception 
     */
    public function LoadPlaylist(string $Name)
    {
        $this->Init();
        $raw = $this->SendLSQData(new LSQData(array(LSQResponse::playlist, 'load'), array($Name, 'noplay:1')));
        $ret = rawurldecode(explode(' ', $raw)[0]);
        if (($ret == '/' . $Name) or ( $ret == '\\' . $Name))
            throw new Exception("Playlist not found.");
        return rawurldecode($ret);
    }

    /**
     * Lädt eine Wiedergabelisten-Datei aus dem LMS-Server und spring an die zuletzt abgespielten Track.
     *
     * @param string $Name
     * Der Name der Wiedergabeliste.
     * @return string
     * Kompletter Pfad der Wiedergabeliste.
     * @exception 
     */
    public function ResumePlaylist(string $Name)
    {
        $this->Init();
        $raw = $this->SendLSQData(new LSQData(array(LSQResponse::playlist, 'resume'), array($Name, 'noplay:1')));
        $ret = rawurldecode(explode(' ', $raw)[0]);
        if (($ret == '/' . $Name) or ( $ret == '\\' . $Name))
            throw new Exception("Playlist not found.");
        return rawurldecode($ret);
    }

    /**
     * Lädt eine zuvor gespeicherte Wiedergabelisten-Datei und setzt die Wiedergabe fort.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception 
     */
    public function LoadTempPlaylist()
    {
        $this->Init();
        $raw = $this->SendLSQData(new LSQData(array(LSQResponse::playlist, 'resume'), array((string) $this->InstanceID, 'wipePlaylist:1', 'noplay:1')));
        $ret = explode(' ', $raw);
        return (rawurldecode($ret[0]) == (string) $this->InstanceID);
    }

    public function LoadPlaylistByAlbumID(integer $AlbumID)
    {
        $this->Init();
        if (!is_int($AlbumID))
            throw new Exception("AlbumID must be integer.");

        $raw = $this->SendLSQData(new LSQData(LSQResponse::playlistcontrol, array('cmd:load', 'album_id:' . $AlbumID)));
        $Data = new LMSTaggingData($raw);
        if (property_exists($Data, 'count'))
        {
            $this->SetValueInteger('Tracks', (int) $Data->count);
            return true;
        }
        throw new Exception("AlbumID not found.");
    }

    public function LoadPlaylistByGenreID(integer $GenreID)
    {
        $this->Init();
        if (!is_int($GenreID))
            throw new Exception("GenreID must be integer.");
        $raw = $this->SendLSQData(new LSQData(LSQResponse::playlistcontrol, array('cmd:load', 'genre_id:' . $GenreID)));
        $Data = new LMSTaggingData($raw);
        if (property_exists($Data, 'count'))
        {
            $this->SetValueInteger('Tracks', (int) $Data->count);
            return true;
        }
        throw new Exception("GenreID not found.");
    }

    public function LoadPlaylistByArtistID(integer $ArtistID)
    {
        $this->Init();
        if (!is_int($ArtistID))
            throw new Exception("ArtistID must be integer.");

        $raw = $this->SendLSQData(new LSQData(LSQResponse::playlistcontrol, array('cmd:load', 'artist_id:' . $ArtistID)));
        $Data = new LMSTaggingData($raw);
        if (property_exists($Data, 'count'))
        {
            $this->SetValueInteger('Tracks', (int) $Data->count);
            return true;
        }
        throw new Exception("ArtistID not found.");
    }

    public function LoadPlaylistByPlaylistID(integer $PlaylistID)
    {
        $this->Init();
        if (!is_int($PlaylistID))
            throw new Exception("PlaylistID must be integer.");

        $raw = $this->SendLSQData(new LSQData(LSQResponse::playlistcontrol, array('cmd:load', 'playlist_id:' . $PlaylistID)));
        $Data = new LMSTaggingData($raw);
        if (property_exists($Data, 'count'))
        {
            $this->SetValueInteger('Tracks', (int) $Data->count);
            return true;
        }
        throw new Exception("PlaylistID not found.");
    }

    public function GetPlaylistInfo()
    {
        $this->Init();
        $raw = $this->SendLSQData(new LSQData(array(LSQResponse::playlist, 'playlistsinfo'), ''));
        if ($raw === true)
            return false;
        $SongInfo = new LSMSongInfo($raw);
        return $SongInfo->GetSong();
    }

    /**
     * Liefert Informationen über einen Song aus der aktuelle Wiedergabeliste.
     *
     * @param integer $Index
     * $Index für die absolute Position des Titels in der Wiedergabeliste.
     * 0 für den aktuellen Titel
     *  
     * @return array
     *  ["duration"]=>string
     *  ["id"]=>string
     *  ["title"]=>string
     *  ["genre"]=>string
     *  ["album"]=>string
     *  ["artist"]=>string
     *  ["disc"]=> string
     *  ["disccount"]=>string
     *  ["bitrate"]=>string
     *  ["tracknum"]=>string
     * @exception 
     */
    public function GetSongInfoByTrackIndex(integer $Index)
    {
        $this->Init();
        if (is_int($Index))
            $Index--;
        else
            throw new Exception("Index must be integer.");
        if ($Index == -1)
            $Index = '-';
        $Data = $this->SendLSQData(new LSQData(array('status', (string) $Index, '1'), 'tags:gladiqrRtueJINpsy'));
        $SongInfo = new LSMSongInfo($Data);
        $SongArray = $SongInfo->GetSong();
        if (count($SongArray) == 1)
            throw new Exception("Index not valid.");
        return $SongArray;
    }

    /**
     * Liefert Informationen über alle Songs aus der aktuelle Wiedergabeliste.
     *
     * @return array[index]
     *      ["duration"]=>string
     *      ["id"]=>string
     *      ["title"]=>string
     *      ["genre"]=>string
     *      ["album"]=>string
     *      ["artist"]=>string
     *      ["disc"]=> string
     *      ["disccount"]=>string
     *      ["bitrate"]=>string
     *      ["tracknum"]=>string
     * @exception 
     */
    public function GetSongInfoOfCurrentPlaylist()
    {
        $max = GetValueInteger($this->GetIDForIdent('Tracks'));
        $Data = $this->SendLSQData(new LSQData(array('status', '0', (string) $max), 'tags:gladiqrRtueJINpsy'));
        $SongInfo = new LSMSongInfo($Data);
        return $SongInfo->GetAllSongs();
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
                        $result = $this->PreviousTrack();
                        break;
                    case 1: //Stop
                        $result = $this->Stop();
                        break;
                    case 2: //Play
                        $result = $this->Play();
                        break;
                    case 3: //Pause
                        $result = $this->Pause();
                        break;
                    case 4: //Next
                        //$this->NextButton();
                        $result = $this->NextTrack();
                        break;
                }
                break;
            case "Volume":
                $result = $this->SetVolume($Value);
                break;
            case "Bass":
                $result = $this->SetBass($Value);
                break;
            case "Treble":
                $result = $this->SetTreble($Value);
                break;
            case "Pitch":
                $result = $this->SetPitch($Value);
                break;
            case "Preset":
                $result = $this->SelectPreset($Value);
                break;
            case "Power":
                $result = $this->Power($Value);
                break;
            case "Mute":
                $result = $this->SetMute($Value);
                break;
            case "Repeat":
                $result = $this->SetRepeat($Value);
                break;
            case "Shuffle":
                $result = $this->SetShuffle($Value);
                break;
            case "Position2":
                $this->tempData['Duration'] = GetValueInteger($this->GetIDForIdent('DurationRAW'));
//                $this->tempData['Position'] = GetValueInteger($this->GetIDForIdent('PositionRAW'));
                $Time = ($this->tempData['Duration'] / 100) * $Value;
                $result = $this->SetPosition(intval($Time));
                break;
            case "Index":
                $result = $this->PlayTrack($Value);
                break;
            case "SleepTimer":
                $result = $this->SetSleep($Value);
                break;
            default:
                throw new Exception("Invalid ident");
        }
        if ($result == false)
        {
            throw new Exception("Error on RequestAction for ident " . $Ident);
        }
    }

################## PRIVATE

    private function _NewName($Name)
    {

        if (IPS_GetName($this->InstanceID) <> trim($Name))
        {
            IPS_SetName($this->InstanceID, trim($Name));
        }
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
        $this->SetValueBoolean('can_seek', (bool) $Value);
        if ((bool) $Value)
            $this->EnableAction("Position2");
        else
            $this->DisableAction('Position2');
    }

    private function _SetSleep($Value)
    {
        $this->SetValueInteger('SleepTimer', $Value);
        if ($Value == 0)
            $this->SetValueString('SleepTimeout', '');
    }

    private function decodeLSQEvent($LSQEvent)
    {
        if (is_array($LSQEvent->Command))
        {
            $MainCommand = array_shift($LSQEvent->Command);
        }
        else
        {
            $MainCommand = $LSQEvent->Command;
        }

        switch ($MainCommand)
        {
            case LSQResponse::player_connected:
                if (GetValueBoolean($this->GetIDForIdent('Connected')) <> (bool) $LSQEvent->Value)
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
                $this->_NewName(rawurldecode((string) $LSQEvent->Value));
                break;
            case LSQResponse::signalstrength:
                $this->SetValueInteger('Signalstrength', (int) $LSQEvent->Value);
                break;
            case LSQResponse::player_ip:
                //wegwerfen, solange es keinen SetSummary gibt
                break;
            case LSQResponse::power:
                $this->SetValueBoolean('Power', (bool) $LSQEvent->Value);
                if ((bool) $LSQEvent->Value == false)
                    $this->_SetSleep(0);
                break;

            case LSQResponse::play:
                $this->_SetPlay();
                break;
            case LSQResponse::stop:
                $this->_SetStop();
                break;
            case LSQResponse::pause:
                if ($LSQEvent->Value == '')
                {
                    $this->_SetPause();
                }
                elseif ((bool) $LSQEvent->Value)
                {
                    $this->_SetPause();
                }
                else
                {
                    $this->_SetPlay();
                }
                break;

            case LSQResponse::mode:
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
                $this->SetValueBoolean('Mute', (bool) $LSQEvent->Value);
                break;
            case LSQResponse::repeat:
                $this->SetValueInteger('Repeat', (int) ($LSQEvent->Value));
                break;
            case LSQResponse::shuffle:
                if ($this->SetValueInteger('Shuffle', (int) ($LSQEvent->Value)))
                {
                    $this->RefreshPlaylist();
                }
                break;
            case LSQResponse::sleep:
                $this->_SetSleep((int) $LSQEvent->Value);
                break;
            case LSQResponse::will_sleep_in:
                if ((int) $LSQEvent->Value > 3600)
                    $this->SetValueString('SleepTimeout', @date("H:i:s", (int) $LSQEvent->Value - 3600));
                else
                    $this->SetValueString('SleepTimeout', @date("i:s", (int) $LSQEvent->Value));
                break;
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
            case LSQResponse::displaynotify:
            case LSQResponse::remoteMeta:
            case LSQResponse::id:
            case LSQResponse::playlist_modified:
            case LSQResponse::playlist_id:
            case LSQResponse::currentSong:
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
                    $currentTrack = 1;
                }
                $this->SetValueInteger('Status', 2);
                $this->SetValueString('Title', trim(rawurldecode($title)));
                //  $this->SendLSQData(new LSQData(LSQResponse::artist, '?', false));
                //  $this->SendLSQData(new LSQData(LSQResponse::album, '?', false));
                $this->SendLSQData(new LSQData(LSQResponse::genre, '?', false));
                $this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::name), '?', false));
                //  $this->SendLSQData(new LSQData(LSQResponse::duration, '?', false));
                //  $this->SendLSQData(new LSQData(array(LSQResponse::playlist, LSQResponse::tracks), '?', false));
                $this->SetCover();
                if ($this->SetValueInteger('Index', $currentTrack))
                    $this->RefreshPlaylist();
                break;
            case LSQResponse::newmetadata:
                $this->SetCover();
                break;
            case LSQResponse::playlist:
                if (!($LSQEvent->Command[0] == LSQResponse::stop)  //Playlist stop kommt auch bei fwd ?
                        and ! ($LSQEvent->Command[0] == LSQResponse::mode))
                {
                    if ($LSQEvent->Command[0] == LSQResponse::name)
                        $this->decodeLSQEvent(new LSQEvent(LSQResponse::playlist_name, $LSQEvent->Value, $LSQEvent->isResponse));
                    else
                        $this->decodeLSQEvent(new LSQEvent($LSQEvent->Command[0], $LSQEvent->Value, $LSQEvent->isResponse));
                }
                break;
            case LSQResponse::loadtracks:
                $this->RefreshPlaylist();

                break;
            case LSQResponse::load_done:
                $this->RefreshPlaylist();
        
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
            case LSQResponse::title:
                $this->SetValueString('Title', trim(rawurldecode($LSQEvent->Value)));
                break;
            case LSQResponse::artist:
                $this->SetValueString('Interpret', trim(rawurldecode($LSQEvent->Value)));
                break;
            case LSQResponse::current_title:
            case LSQResponse::album:

                if (is_array($LSQEvent->Value))
                {
                    $this->SetValueString('Album', trim(rawurldecode($LSQEvent->Value[0])));
                }
                else
                {
                    $this->SetValueString('Album', trim(rawurldecode($LSQEvent->Value)));
                }
                break;
            case LSQResponse::genre:
                $this->SetValueString('Genre', trim(rawurldecode($LSQEvent->Value)));
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
                    if ((int) $LSQEvent->Value > 3600)
                        $this->SetValueString('Duration', @date("H:i:s", (int) $LSQEvent->Value - 3600));
                    else
                        $this->SetValueString('Duration', @date("i:s", (int) $LSQEvent->Value));
                }
                break;
            case LSQResponse::playlist_name:
                $this->SetValueString('Playlistname', trim(rawurldecode($LSQEvent->Value)));

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
                    if ($this->SetValueInteger('Index', 0))
                        $this->RefreshPlaylist();
                    $this->SetCover();
//                $LSQEvent->Value                    
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
                array_shift($LSQEvent->Value);
                if ($LSQEvent->Command[0] == '-')// and ( $LSQEvent->Command[1] == '1') and ( strpos($Event, "subscribe%3A") > 0))
                {

                $remote = false;
                    foreach ($LSQEvent->Value as $Data)
                    {
//                        $LSQPart = $this->decodeLSQTaggingData($Data, $LSQEvent->isResponse);
                        $LSQPart = new LSQTaggingData($Data, $LSQEvent->isResponse);
                        if ($LSQPart->Command == LSQResponse::remote)
                            $remote = ($LSQPart->Value == 1);
//                        if (($LSQPart->Command == LSQResponse::title) and $remote) continue;
//                        if (($LSQPart->Command == LSQResponse::current_title) and $remote) continue;
                        $this->decodeLSQEvent($LSQPart);
                    }
                }
                break;
            case LSQResponse::can_seek:
                if (GetValueBoolean($this->GetIDForIdent('can_seek')) <> (bool) $LSQEvent->Value)
                {
                    $this->_SetSeekable((bool) $LSQEvent->Value);
                }
                break;
            case LSQResponse::remote:
                if (GetValueBoolean($this->GetIDForIdent('can_seek')) == (bool) $LSQEvent->Value)
                {
                    $this->_SetSeekable(!(bool) $LSQEvent->Value);
                }
                break;
            case LSQResponse::index:
            case LSQResponse::playlist_cur_index:
                if ($this->SetValueInteger('Index', intval($LSQEvent->Value) + 1))
                    $this->RefreshPlaylist();
                break;
            case LSQResponse::time:
                $this->tempData['Position'] = $LSQEvent->Value;
                $this->SetValueInteger('PositionRAW', $LSQEvent->Value);
                if ((int) $LSQEvent->Value > 3600)
                    $this->SetValueString('Position', @date("H:i:s", (int) $LSQEvent->Value - 3600));
                else
                    $this->SetValueString('Position', @date("i:s", (int) $LSQEvent->Value));

                break;
            default:
                if (is_array($LSQEvent->Value))
                    IPS_LogMessage('ToDoLSQEvent', 'LSQResponse-' . $MainCommand . '-' . print_r($LSQEvent->Value, 1));
                else
                    IPS_LogMessage('ToDoLSQEvent', 'LSQResponse-' . $MainCommand . '-' . $LSQEvent->Value);
                break;
        }
        if (isset($this->tempData['Duration']) and isset($this->tempData['Position']))
        {
            $Value = (100 / $this->tempData['Duration']) * $this->tempData['Position'];
            $this->SetValueInteger('Position2', round($Value));
        }
    }

    private function RefreshPlaylist()
    {
        $ScriptID = $this->ReadPropertyInteger('Playlistconfig');
        if ($ScriptID == 0)
            return;
        IPS_RunScriptEx($ScriptID, array('SENDER' => 'SqueezeBox', 'TARGET' => $this->InstanceID));
    }

    public function DisplayPlaylist($Config)
    {
        if (($Config === false) or ( !is_array($Config)))
            throw new Exception('Error on read Playlistconfig-Script');

        try
        {
            $Data = $this->GetSongInfoOfCurrentPlaylist();
        }
        catch (Exception $exc)
        {
            throw new Exception('Error on read Playlist');
        }
        $HTMLData = $this->GetTableHeader($Config);
        $pos = 0;
        $CurrentTrack = GetValueInteger($this->GetIDForIdent('Index'));

        if (isset($Data))
        {
            foreach ($Data as $Position => $Line)
            {
                $Line['Position'] = $Position;
                if (array_key_exists('Duration', $Line))
                {
                    if ($Line['Duration'] > 3600)
                        $Line['Duration'] = @date("H:i:s", $Line['Duration'] - 3600);
                    else
                        $Line['Duration'] = @date("i:s", $Line['Duration']);
                } else
                {
                    $Line['Duration'] = '---';
                }
                
                $Line['Play'] = $Line['Position'] == $CurrentTrack ? '<div class="ipsIconArrowRight" is="null"></div>' : '';

                $HTMLData .='<tr style="' . $Config['Style']['BR' . ($Line['Position'] == $CurrentTrack ? 'A' : ($pos % 2 ? 'U' : 'G'))] . '"
                        onclick="window.xhrGet=function xhrGet(o) {var HTTP = new XMLHttpRequest();HTTP.open(\'GET\',o.url,true);HTTP.send();};window.xhrGet({ url: \'hook/SqueezeBoxPlaylist' . $this->InstanceID . '?Index=' . $Line['Position'] . '\' })">';
                foreach ($Config['Spalten'] as $feldIndex => $value)
                {
                    if (!array_key_exists($feldIndex, $Line))
                        $Line[$feldIndex] = '';
                    $HTMLData .= '<td style="' . $Config['Style']['DF' . ($Line['Position'] == $CurrentTrack ? 'A' : ($pos % 2 ? 'U' : 'G')) . $feldIndex] . '">' . (string) $Line[$feldIndex] . '</td>';
                }
                $HTMLData .= '</tr>' . PHP_EOL;
                $pos++;
            }
        }
        $HTMLData .= $this->GetTableFooter();
        $this->SetValueString('Playlist', $HTMLData);
    }

    private function RegisterHook($WebHook, $TargetID)
    {
        $ids = IPS_GetInstanceListByModuleID("{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}");
        if (sizeof($ids) > 0)
        {
            $hooks = json_decode(IPS_GetProperty($ids[0], "Hooks"), true);
            $found = false;
            foreach ($hooks as $index => $hook)
            {
                if ($hook['Hook'] == $WebHook)
                {
                    if ($hook['TargetID'] == $TargetID)
                        return;
                    $hooks[$index]['TargetID'] = $TargetID;
                    $found = true;
                }
            }
            if (!$found)
            {
                $hooks[] = Array("Hook" => $WebHook, "TargetID" => $TargetID);
            }
            IPS_SetProperty($ids[0], "Hooks", json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }

    private function GetTableHeader($Config)
    {
//	$felder = array('Icon'=>'Typ', 'Date'=>'Datum', 'Name'=>'Name', 'Caller'=>'Rufnummer', 'Device'=>'Nebenstelle', 'Called'=>'Eigene Rufnummer', 'Duration'=>'Dauer','AB'=>'Nachricht');
        // Kopf der Tabelle erzeugen
        $html = "<table bgcolor='#000000'><body scroll=no><body bgcolor='#000000'><table style=" . $Config['Style']['T'] . '">' . PHP_EOL;
        $html .= '<colgroup>' . PHP_EOL;
        foreach ($Config['Spalten'] as $Index => $Value)
        {
            $html .= '<col width="' . $Config['Breite'][$Index] . '" />' . PHP_EOL;
        }
        $html .= '</colgroup>' . PHP_EOL;
        $html .= '<thead style="' . $Config['Style']['H'] . '">' . PHP_EOL;
        $html .= '<tr style="' . $Config['Style']['HR'] . '">';
        foreach ($Config['Spalten'] as $Index => $Value)
        {
            $html .= '<th style="color:#ffffff; ' . $Config['Style']['HF' . $Index] . '">' . $Value . '</th>';
        }
        $html .= '</tr>' . PHP_EOL;
        $html .= '</thead>' . PHP_EOL;
        $html .= '<tbody style="' . $Config['Style']['B'] . '">' . PHP_EOL;
        return $html;
    }

    private function GetTableFooter()
    {
        $html = '</tbody>' . PHP_EOL;
        $html .= '</table>' . PHP_EOL;
        return $html;
    }

    private function CreatePlaylistConfigScript()
    {
        $Script = '<?
### Konfig ab Zeile 10 !!!

if ($_IPS["SENDER"] <> "SqueezeBox")
{
	echo "Dieses Script kann nicht direkt ausgeführt werden!";
	return;
}
##########   KONFIGURATION
#### Tabellarische Ansicht
# Folgende Parameter bestimmen das Aussehen der HTML-Tabelle in der die WLAN-Geräte aufgelistet werden.

// Reihenfolge und Überschriften der Tabelle. Der vordere Wert darf nicht verändert werden.
// Die Reihenfolge, der hintere Wert (Anzeigetext) und die Reihenfolge sind beliebig änderbar.
$Config["Spalten"] = array(
"Play" =>"",
"Position"=>"Pos",
"Title"=>"Titel",
"Artist"=>"Interpret",
"Bitrate"=>"Bitrate",
"Duration"=>"Dauer"
);
#### Mögliche Index-Felder
/*
| Index            | Typ     | Beschreibung                        |
| :--------------: | :-----: | :---------------------------------: |
| Play             |  kein   | Play-Icon                           |
| Position         | integer | Position in der Playlist            |
| Id               | integer | UID der Datei in der LMS-Datenbank  |
| Title            | string  | Titel                               |
| Genre            | string  | Genre                               |
| Album            | string  | Album                               |
| Artist           | string  | Interpret                           |
| Duration         | integer | Länge in Sekunden                   |
| Disc             | integer | Aktuelles Medium                    |
| Disccount        | integer | Anzahl aller Medien dieses Albums   |
| Bitrate          | string  | Bitrate in Klartext                 |
| Tracknum         | integer | Tracknummer im Album                |
| Url              | string  | Pfad der Playlist                   |
| Album_id         | integer | UID des Album in der LMS-Datenbank  |
| Artwork_track_id | string  | UID des Cover in der LMS-Datenbank  |
| Genre_id         | integer | UID des Genre in der LMS-Datenbank  |
| Artist_id        | integer | UID des Artist in der LMS-Datenbank |
| Year             | integer | Jahr des Song, soweit hinterlegt    |
*/
// Breite der Spalten (Reihenfolge ist egal)
$Config["Breite"] = array(
"Play" =>"50em",
"Position" => "50em",
    "Title" => "200em",
    "Artist" => "200em",
    "Bitrate" => "200em",
    "Duration" => "100em"
);
// Style Informationen der Tabelle
$Config["Style"] = array(
    // <table>-Tag:
    "T"    => "margin:0 auto; font-size:0.8em;",
    // <thead>-Tag:
    "H"    => "",
    // <tr>-Tag im thead-Bereich:
    "HR"   => "",
    // <th>-Tag Feld Play:
    "HFPlay"  => "width:35px; align:left;",
    // <th>-Tag Feld Position:
    "HFPosition"  => "width:35px; align:left;",
    // <th>-Tag Feld Title:
    "HFTitle"  => "width:35px; align:left;",
    // <th>-Tag Feld Artist:
    "HFArtist"  => "width:35px; align:left;",
    // <th>-Tag Feld Bitrate:
    "HFBitrate"  => "width:35px; align:left;",
    // <th>-Tag Feld Duration:
    "HFDuration"  => "width:35px; align:left;",
    // <tbody>-Tag:
    "B"    => "",
    // <tr>-Tag:
    "BRG"  => "background-color:#000000; color:#ffffff;",
    "BRU"  => "background-color:#080808; color:#ffffff;",
    "BRA"  => "background-color:#808000; color:#ffffff;",
    // <td>-Tag Feld Play:
    "DFGPlay" => "text-align:center;",
    "DFUPlay" => "text-align:center;",
    "DFAPlay" => "text-align:center;",
    // <td>-Tag Feld Position:
    "DFGPosition" => "text-align:center;",
    "DFUPosition" => "text-align:center;",
    "DFAPosition" => "text-align:center;",
    // <td>-Tag Feld Title:
    "DFGTitle" => "text-align:center;",
    "DFUTitle" => "text-align:center;",
    "DFATitle" => "text-align:center;",
    // <td>-Tag Feld Artist:
	 "DFGArtist" => "text-align:center;",
    "DFUArtist" => "text-align:center;",
    "DFAArtist" => "text-align:center;",
    // <td>-Tag Feld Bitrate:
    "DFGBitrate" => "text-align:center;",
    "DFUBitrate" => "text-align:center;",
    "DFABitrate" => "text-align:center;",
    // <td>-Tag Feld Duration:
    "DFGDuration" => "text-align:center;",
    "DFUDuration" => "text-align:center;",
    "DFADuration" => "text-align:center;"
    // ^- Der Buchstabe "G" steht für gerade, "U" für ungerade., "A" für Aktiv
 );
### Konfig ENDE !!!
LSQ_DisplayPlaylist($_IPS["TARGET"],$Config);
?>';
        return $Script;
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
            IPS_SetMediaCached($CoverID, true);
            IPS_SetMediaFile($CoverID, "media" . DIRECTORY_SEPARATOR . "Cover_" . $this->InstanceID . ".png", False);
        }
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            $Host = IPS_GetProperty($ParentID, 'Host') . ":" . IPS_GetProperty($ParentID, 'Webport');
            $Size = $this->ReadPropertyString("CoverSize");
            $PlayerID = rawurlencode($this->Address);
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
        $this->Connected = $Status;
        $this->Init(false);
        if ($Status === true)
            $this->RequestAllState();
    }

    private function Init($throwException = true)
    {
        if ($this->Connected <> 'noInit')
            return true;

        $this->Address = $this->ReadPropertyString("Address");
        $this->Interval = $this->ReadPropertyInteger("Interval");
        $this->Connected = GetValueBoolean($this->GetIDForIdent('Connected'));
        if ($this->Address == '')
        {
            $this->SetStatus(202);
            if ($throwException)
                throw new Exception('Address not set.');
            else
                return false;
        }
        $ParentID = $this->GetParent();
        if ($ParentID === false)
        {
            $this->SetStatus(104);
            if ($throwException)
                throw new Exception('Instance has no parent.');
            else
                return false;
        }
        else
        if (!$this->HasActiveParent($ParentID))
        {
            $this->SetStatus(203);
            if ($throwException)
                throw new Exception('Instance has no active parent.');
            else
                return false;
        }
        $this->SetStatus(102);

        return true;
    }

    private function SetValueBoolean($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueBoolean($id) <> $value)
        {
            SetValueBoolean($id, $value);
            return true;
        }
        return false;
    }

    private function SetValueInteger($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueInteger($id) <> $value)
        {
            SetValueInteger($id, $value);
            return true;
        }
        return false;
    }

    private function SetValueString($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueString($id) <> $value)
        {
            SetValueString($id, $value);
            return true;
        }
        return false;
    }

################## DataPoints
    // Ankommend von Parent-LMS-Splitter

    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString);

        $this->Init(false);
        if ($this->Address === '') //Keine Adresse Daten nicht verarbeiten
            return false;

        // Adressen stimmen überein, die Daten sind für uns.
        if (($this->Address == $Data->LMS->MAC) or ( $this->Address == $Data->LMS->IP))
        {
            // Objekt erzeugen welches die Commands und die Values enthält.
            $Response = new LSQResponse($Data->LMS);

            // Ist das Command schon bekannt ?
            if ($Response->Command <> false)
            {
                // Daten prüfen ob Antwort
                $isResponse = $this->WriteResponse($Response->Command, $Response->Value);
                if (is_bool($isResponse))
                {
                    $Response->isResponse = $isResponse;
                    if (!$isResponse)
                    {
                        // Daten dekodieren
                        $this->decodeLSQEvent($Response);
                    }
                    return true;
                }
                else
                {
                    throw new Exception($isResponse);
                }
            }
            // Ignorierte Commands loggen
            /*
              else
              {
              IPS_LogMessage("ToDoLSQDevice: Unbekannter Datensatz:", print_r($Response->Value, 1));
              return true;
              } */
        }
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
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            if (!$this->HasActiveParent($ParentID))
                return;
        }
        else
            return;

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
//        throw new Exception("No answer from LMS789");

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
//                throw new Exception("No answer from LMS123");

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
//        throw new Exception("No answer from LMS456");

        return false;
    }

    private function WriteResponse($Command, $Value)
    {
        if (is_array($Command))
            $Command = implode(' ', $Command);
        if (is_array($Value))
            $Value = implode(' ', $Value);

        $EventID = $this->GetIDForIdent('WaitForResponse');
        if (!GetValueBoolean($EventID))
            return false;
        $BufferID = $this->GetIDForIdent('BufferOUT');
        if ($Command == GetValueString($BufferID))
        {
            if ($this->lock('BufferOut'))
            {
                SetValueString($BufferID, trim($Value));
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
                IPS_Sleep(mt_rand(1, 5));
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

    protected function SetStatus($InstanceStatus)
    {
        if ($InstanceStatus <> IPS_GetInstance($this->InstanceID)['InstanceStatus'])
            parent::SetStatus($InstanceStatus);
    }

    protected function RegisterTimer($Name, $Interval, $Script)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            $id = 0;


        if ($id > 0)
        {
            if (!IPS_EventExists($id))
                throw new Exception("Ident with name " . $Name . " is used for wrong object type");

            if (IPS_GetEvent($id)['EventType'] <> 1)
            {
                IPS_DeleteEvent($id);
                $id = 0;
            }
        }

        if ($id == 0)
        {
            $id = IPS_CreateEvent(1);
            IPS_SetParent($id, $this->InstanceID);
            IPS_SetIdent($id, $Name);
        }
        IPS_SetName($id, $Name);
        IPS_SetHidden($id, true);
        IPS_SetEventScript($id, $Script);
        if ($Interval > 0)
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);

            IPS_SetEventActive($id, true);
        }
        else
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);

            IPS_SetEventActive($id, false);
        }
    }

    protected function UnregisterTimer($Name)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id > 0)
        {
            if (!IPS_EventExists($id))
                throw new Exception('Timer not present');
            IPS_DeleteEvent($id);
        }
    }

    protected function SetTimerInterval($Name, $Interval)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            throw new Exception('Timer not present');
        if (!IPS_EventExists($id))
            throw new Exception('Timer not present');

        $Event = IPS_GetEvent($id);

        if ($Interval < 1)
        {
            if ($Event['EventActive'])
                IPS_SetEventActive($id, false);
        }
        else
        {
            if ($Event['CyclicTimeValue'] <> $Interval)
                IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);
            if (!$Event['EventActive'])
                IPS_SetEventActive($id, true);
        }
    }

}

?>