<?

require_once(__DIR__ . "/../SqueezeBoxClass.php");  // diverse Klassen

class SqueezeboxDevice extends IPSModule
{

    use LMSHTMLTable,
        LMSCover,
        VariableHelper,
        DebugHelper,
        InstanceStatus,
        Profile,
        Semaphore,
        Webhook;

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent("{96A9AB3A-2538-42C5-A130-FC34205A706A}");
        $this->SetReceiveDataFilter('.*"Address":"".*');
        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyInteger("Interval", 2);
        $this->RegisterPropertyString("CoverSize", "cover");
        $this->RegisterPropertyBoolean("enableBass", true);
        $this->RegisterPropertyBoolean("enableTreble", true);
        $this->RegisterPropertyBoolean("enablePitch", true);

        $this->RegisterPropertyBoolean("showPlaylist", true);
        $ID = @$this->GetIDForIdent('PlaylistDesign');
        if ($ID == false)
            $ID = $this->RegisterScript('PlaylistDesign', 'Playlist Config', $this->CreatePlaylistConfigScript(), -7);
        IPS_SetHidden($ID, true);
        $this->RegisterPropertyInteger("Playlistconfig", $ID);
        $this->RegisterPropertyBoolean('changeName', true);
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Destroy()
    {
        $CoverID = @IPS_GetObjectIDByIdent('CoverIMG', $this->InstanceID);
        if ($CoverID > 0)
            @IPS_DeleteMedia($CoverID, true);
        $this->UnregisterHook('/hook/SqueezeBoxPlaylist' . $this->InstanceID);
        $this->UnregisterProfile("Tracklist.Squeezebox." . $this->InstanceID);

        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message)
        {

            case DM_CONNECT:
            case DM_DISCONNECT:
                $this->ForceRefresh();
                break;
        }
    }

    /**
     * Wird durch das Verbinden/Trennen eines Parent ausgelöst.
     *
     * @access public
     */
    protected function ForceRefresh()
    {
        $this->ApplyChanges();
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        $this->SetReceiveDataFilter('.*"Address":"".*');
        $this->RegisterMessage($this->InstanceID, DM_CONNECT);
        $this->RegisterMessage($this->InstanceID, DM_DISCONNECT);
        // Wenn Kernel nicht bereit, dann warten... KR_READY über Splitter kommt ja gleich

        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() <> KR_READY)
            return;

        // Addresse prüfen
        $Address = $this->ReadPropertyString('Address');
        $NewState = IS_EBASE + 2;
        $changeAddress = false;
        if ($Address == '')
            $NewState = IS_INACTIVE;
        else
        {
            //ip Adresse:
            if (preg_match("/\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b/", $Address) === 1)
                $NewState = IS_ACTIVE;
            else // Keine IP Adresse
            {
                if (strlen($Address) == 12)
                {
                    $Address = strtolower(implode(":", str_split($Address, 2)));
                    $changeAddress = true;
                }
                if (preg_match("/^([0-9A-Fa-f]{2}[-]){5}([0-9A-Fa-f]{2})$/", $Address) === 1)
                {
                    $Address = strtolower(str_replace('-', ':', $Address));
                    $changeAddress = true;
                }
                if ($Address <> strtolower($Address))
                {
                    $Address = strtolower($Address);
                    $changeAddress = true;
                }
                if (preg_match("/^([0-9a-f]{2}[:]){5}([0-9a-f]{2})$/", $Address) === 1)
                {
                    $NewState = IS_ACTIVE;
                }
            }
            if ($changeAddress and ( $NewState == IS_ACTIVE))
            {
                IPS_SetProperty($this->InstanceID, 'Address', $Address);
                IPS_ApplyChanges($this->InstanceID);
                return;
            }
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
        $this->RegisterProfileInteger("Pitch.Squeezebox", "Intensity", "", " %", 80, 120, 1);
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

        $this->RegisterVariableBoolean("Connected", "Player verbunden", "", 0);

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
        if ($this->ReadPropertyBoolean('enableBass'))
        {
            $this->RegisterVariableInteger("Bass", "Bass", "Intensity.Squeezebox", 6);
            $this->EnableAction("Bass");
        }
        else
            $this->UnregisterVariable("Bass");
        if ($this->ReadPropertyBoolean('enableTreble'))
        {
            $this->RegisterVariableInteger("Treble", "Treble", "Intensity.Squeezebox", 7);
            $this->EnableAction("Treble");
        }
        else
            $this->UnregisterVariable("Treble");

        if ($this->ReadPropertyBoolean('enablePitch'))
        {
            $this->RegisterVariableInteger("Pitch", "Pitch", "Pitch.Squeezebox", 8);
            $this->EnableAction("Pitch");
        }
        else
            $this->UnregisterVariable("Pitch");

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
        $this->RegisterVariableString("Artist", "Interpret", "", 22);
        $this->RegisterVariableString("Genre", "Stilrichtung", "", 23);
        $this->RegisterVariableString("Duration", "Dauer", "", 24);
        $this->RegisterVariableString("Position", "Spielzeit", "", 25);
        $this->RegisterVariableInteger("Position2", "Position", "Intensity.Squeezebox", 26);
        $this->DisableAction('Position2');

        $this->RegisterVariableInteger("Signalstrength", "Signalstärke", "Intensity.Squeezebox", 30);
        $this->RegisterVariableInteger("SleepTimer", "Einschlaftimer", "SleepTimer.Squeezebox", 31);
        $this->EnableAction("SleepTimer");
        $this->RegisterVariableString("SleepTimeout", "Ausschalten in ", "", 32);


        $this->UnregisterVariable("can_seek");
        $this->UnregisterVariable("BufferOUT");
        $this->UnregisterVariable("WaitForResponse");
        //$this->UnregisterVariable("Connected");
        $this->UnregisterVariable("PositionRAW");
        $this->UnregisterVariable("DurationRAW");

        // Playlist
        if ($this->ReadPropertyBoolean('showPlaylist'))
        {
            $this->RegisterVariableString("Playlist", "Playlist", "~HTMLBox", 29);
            $sid = $this->RegisterScript("WebHookPlaylist", "WebHookPlaylist", '<? //Do not delete or modify.
if (isset($_GET["Index"]))
    if (LSQ_PlayTrack(' . $this->InstanceID . ',(int)$_GET["Index"])===true) echo "OK";
', -8);
            IPS_SetHidden($sid, true);
            $this->RegisterHook('/hook/SqueezeBoxPlaylist' . $this->InstanceID, $sid);
            $ID = @$this->GetIDForIdent('PlaylistDesign');
            if ($ID == false)
                $ID = $this->RegisterScript('PlaylistDesign', 'Playlist Config', $this->CreatePlaylistConfigScript(), -7);
            IPS_SetHidden($ID, true);
        }
        else
        {
            $this->UnregisterVariable("Playlist");
            $this->UnregisterScript("WebHookPlaylist");
            $this->UnregisterHook('/hook/SqueezeBoxPlaylist' . $this->InstanceID);
        }
        $this->SetStatus($NewState);

        switch ($NewState)
        {
            case IS_ACTIVE:
                // Addresse als Filter setzen
                $this->SetReceiveDataFilter('.*"Address":"' . $Address . '".*');
                $this->SetSummary($Address);
                $this->RequestState('Connected');
                // Ist Device (Player) connected ?
//                $ret = $this->Send(new LMSData('connected', '?'));
                // Dann Status von LMS holen
//                $this->_SetConnected((int) $ret->Data[0] == 1);
                break;
            case IS_INACTIVE:
                $this->SetSummary($Address);
                $this->_SetConnected(false);
                break;
            case IS_EBASE + 2: //misconfig
                trigger_error('Invalid Address', E_USER_NOTICE);
                break;
        }
    }

################## PUBLIC

    /**
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen..
     *
     * @return boolean
     */
    public function RequestAllState()
    {
        //connected und Power nicht erlaubt sonst schleife        
        if (!$this->RequestState('Signalstrength'))
            return false;
        $this->RequestState('Name');
        $this->RequestState('SleepTimeout');
        $this->RequestState('Sync');
        $this->RequestState('Volume');
        $this->RequestState('Mute');
        $this->RequestState('Bass');
        $this->RequestState('Treble');
        $this->RequestState('Pitch');
        $this->RequestState('Status');
        $this->RequestState('Position');
        $this->RequestState('Genre');
        $this->RequestState('Artist');
        $this->RequestState('Album');
        $this->RequestState('Title');
        $this->RequestState('Duration');
        $this->RequestState('Remote');
        $this->RequestState('Tracks');
        $this->RequestState('Shuffle');
        $this->RequestState('Repeat');
        $this->RequestState('Playlistname');
        $this->RequestState('Index');
        $LMSData = $this->SendDirect(new LMSData(array('status', 0, '1'), 'tags:gladiqrRtueJINpsy'));
        if ($LMSData === NULL)
            return false;
        $this->DecodeLMSResponse($LMSData);
        $this->SetCover();
        $this->_RefreshPlaylist();
        return true;
    }

    /**
     * IPS-Instanz-Funktion 'LSQ_RequestState'.
     * Fragt einen Wert des Players ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @access public
     * @param string $Ident Der Ident der abzufragenden Statusvariable.
     * @result bool True wenn erfolgreich.
     */
    public function RequestState(string $Ident)
    {
        switch ($Ident)
        {
            case 'Signalstrength':
                $LMSResponse = new LMSData('signalstrength', '?');
                break;
            case 'Name':
                $LMSResponse = new LMSData('name', '?');
                break;
            case 'Connected':
                $LMSResponse = new LMSData('connected', '?');
                break;
            case 'SleepTimeout':
                $LMSResponse = new LMSData('sleep', '?');
                break;
            case 'Sync':
                $LMSResponse = new LMSData('sync', '?');
                break;
            case 'Power':
                $LMSResponse = new LMSData('power', '?');
                break;
            case 'Volume':
                $LMSResponse = new LMSData(array('mixer', 'power'), '?');
                break;
            case 'Mute':
                $LMSResponse = new LMSData(array('mixer', 'muting'), '?');
                break;
            case 'Bass':
                $LMSResponse = new LMSData(array('mixer', 'bass'), '?');
                break;
            case 'Treble':
                $LMSResponse = new LMSData(array('mixer', 'treble'), '?');
                break;
            case 'Pitch':
                $LMSResponse = new LMSData(array('mixer', 'pitch'), '?');
                break;
            case 'Status':
                $LMSResponse = new LMSData('mode', '?');
                break;
            case 'Position2':
            case 'Position':
                $LMSResponse = new LMSData('time', '?');
                break;
            case 'Genre':
                $LMSResponse = new LMSData('genre', '?');
                break;
            case 'Artist':
                $LMSResponse = new LMSData('artist', '?');
                break;
            case 'Album':
                $LMSResponse = new LMSData('album', '?');
                break;
            case 'Title':
                $LMSResponse = new LMSData('title', '?');
                break;
            case 'Duration':
                $LMSResponse = new LMSData('duration', '?');
                break;
            case 'Remote':
                $LMSResponse = new LMSData('remote', '?');
                break;
            /*
              <playerid> current_title ?
              <playerid> path ?
             */
            case 'Tracks':
                $LMSResponse = new LMSData(array('playlist', 'tracks'), '?');
                break;
            case 'Shuffle':
                $LMSResponse = new LMSData(array('playlist', 'shuffle'), '?');
                break;
            case 'Repeat':
                $LMSResponse = new LMSData(array('playlist', 'repeat'), '?');
                break;
            case 'Playlistname':
                $LMSResponse = new LMSData(array('playlist', 'name'), '?');
                break;
            case 'Index':
                $LMSResponse = new LMSData(array('playlist', 'index'), '?');
                break;
//            case "SleepTimeout":
            case 'SleepTimer':
            case 'Preset':
                return true;
            default:
                trigger_error('Ident not valid');
                return false;
        }
        $LMSResponse = $this->SendDirect($LMSResponse);
        if ($LMSResponse == null)
            return false;
        if ($LMSResponse->Data[0] == '?')
            return false;
        return $this->DecodeLMSResponse($LMSResponse);
    }

    public function RawSend($Command, $Value, $needResponse)
    {
        $LMSData = new LMSData($Command, $Value, $needResponse);
        return $this->SendDirect($LMSData);
    }

    //fertig
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
        if (!is_string($Name))
        {
            trigger_error("Name must be string", E_USER_NOTICE);
            return false;
        }
        if ($Name == "")
        {
            trigger_error("Name cannot be empty", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('name', rawurlencode($Name)));
        //$ret = rawurldecode();
        if ($LMSData === NULL)
            return false;
        $this->_SetNewName(rawurldecode($LMSData->Data[0]));
        return (rawurldecode($LMSData->Data[0]) == $Name);
    }

    //fertig
    /**
     * Liefert den Namen von dem Device.
     *
     * @return string
     * @exception
     */
    public function GetName()
    {
        $LMSData = $this->SendDirect(new LMSData('name', '?'));
        //$ret = rawurldecode();
        if ($LMSData === NULL)
            return false;
        $this->_SetNewName(rawurldecode($LMSData->Data[0]));
        return rawurldecode($LMSData->Data[0]);
    }

    //////////////////////////////////////////Todo
    public function SetSync(int $SlaveInstanceID)
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

    //////////////////////////////////////////Todo
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

        $ret = $this->SendLSQData(new LSQData(LSQResponse::sync, '?'))->Data[0];
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

    //////////////////////////////////////////Todo
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

    //fertig
    /**
     * Restzeit bis zum Sleep setzen.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetSleep(int $Seconds)
    {
        if (!is_int($Seconds))
        {
            trigger_error("Seconds must be integer", E_USER_NOTICE);
            return false;
        }
        if ($Seconds < 0)
        {
            trigger_error("Seconds invalid.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('sleep', $Seconds));
        if ($LMSData === NULL)
            return false;
//        $this->_SetSleep($Seconds);
        return ((int) $LMSData->Data[0] == $Seconds);
    }

    //fertig
    /**
     * Restzeit bis zum Sleep lesen.
     *
     * @return integer
     * @exception
     */
    public function GetSleep()
    {
        $this->RequestState("SleepTimeout");
        trigger_error("This function is deprecated, use RequestState an GetValue.", E_USER_DEPRECATED);
        return GetValueInteger($this->GetIDForIdent("SleepTimeout"));
    }

    //fertig
    /**
     * Simuliert einen Tastendruck.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function PreviousButton()
    {
        $LMSData = $this->SendDirect(new LMSData('button', 'jump_rew'));
        if ($LMSData === NULL)
            return false;
//            $this->_SetPlay();
        return ($LMSData->Data[0] == 'jump_rew');
    }

    //fertig
    /**
     * Simuliert einen Tastendruck.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function NextButton()
    {
        $LMSData = $this->SendDirect(new LMSData('button', 'jump_fwd'));
        if ($LMSData === NULL)
            return false;
//            $this->_SetPlay();
        return ($LMSData->Data[0] == 'jump_fwd');
    }

    //fertig
    /**
     * Startet die Wiedergabe
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function Play()
    {
        $LMSData = $this->SendDirect(new LMSData('play', ''));
        if ($LMSData === NULL)
            return false;
//            $this->_SetPlay();
        return true;
    }

    //fertig
    /**
     * Stoppt die Wiedergabe
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function Stop()
    {
        $LMSData = $this->SendDirect(new LMSData('stop', ''));
        if ($LMSData === NULL)
            return false;
//            $this->_SetStop();
        return true;
    }

    //ferig
    /**
     * Pausiert die Wiedergabe
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function Pause()
    {
        $LMSData = $this->SendDirect(new LMSData('pause', '1'));
        if ($LMSData === NULL)
            return false;
//            $this->_SetPause();
        return ($LMSData->Data[0] == '1');
    }

    //fertig
    /**
     * Setzten der Lautstärke.
     *
     * @param integer $Value
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetVolume(int $Value)
    {
        if (!is_int($Value))
        {
            trigger_error("Value must be integer", E_USER_NOTICE);
            return false;
        }
        if (($Value < 0) or ( $Value > 100))
        {
            trigger_error("Value invalid.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('mixer', 'volume'), $Value));
//        $this->_NewVolume((int)$LMSData->Data[0]);
        if ($LMSData === NULL)
            return false;
        return ((int) $LMSData->Data[0] == $Value);
    }

    //fertig
    /**
     * Liefert die aktuelle Lautstärke von dem Device.
     *
     * @return integer
     * @exception
     */
    public function GetVolume()
    {
        $this->RequestState("Volume");
        trigger_error("This function is deprecated, use RequestState an GetValue.", E_USER_DEPRECATED);
        return GetValueInteger($this->GetIDForIdent("Volume"));
    }

    //fertig
    /**
     * Setzt den Bass-Wert.
     *
     * @param integer $Value
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetBass(int $Value)
    {
        if (!$this->ReadPropertyBoolean('enableBass'))
        {
            trigger_error("Bass-Control not enabled", E_USER_NOTICE);
            return false;
        }
        if (!is_int($Value))
        {
            trigger_error("Value must be integer", E_USER_NOTICE);
            return false;
        }
        if (($Value < 0) or ( $Value > 100))
        {
            trigger_error("Value invalid.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('mixer', 'bass'), $Value));
        if ($LMSData === NULL)
            return false;
        return ((int) $LMSData->Data[0] == $Value);
    }

    //fertig
    /**
     * Liefert den aktuellen Bass-Wert.
     *
     * @return integer
     * @exception
     */
    public function GetBass()
    {
        $this->RequestState("Bass");
        trigger_error("This function is deprecated, use RequestState an GetValue.", E_USER_DEPRECATED);
        return GetValueInteger($this->GetIDForIdent("Bass"));
    }

    //fertig
    /**
     * Setzt den Treble-Wert.
     *
     * @param integer $Value
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetTreble(int $Value)
    {
        if (!$this->ReadPropertyBoolean('enableTreble'))
        {
            trigger_error("Treble-Control not enabled", E_USER_NOTICE);
            return false;
        }

        if (!is_int($Value))
        {
            trigger_error("Value must be integer", E_USER_NOTICE);
            return false;
        }
        if (($Value < 0) or ( $Value > 100))
        {
            trigger_error("Value invalid.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('mixer', 'treble'), $Value));
        if ($LMSData === NULL)
            return false;
        return ((int) $LMSData->Data[0] == $Value);
    }

    //fertig
    /**
     * Liefert den aktuellen Treble-Wert.
     *
     * @return integer
     * @exception
     */
    public function GetTreble()
    {
        $this->RequestState("Treble");
        trigger_error("This function is deprecated, use RequestState an GetValue.", E_USER_DEPRECATED);
        return GetValueInteger($this->GetIDForIdent("Treble"));
    }

    //fertig
    /**
     * Setzt den Pitch-Wert.
     *
     * @param integer $Value
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetPitch(int $Value)
    {
        if (!$this->ReadPropertyBoolean('enablePitch'))
        {
            trigger_error("Pitch-Control not enabled", E_USER_NOTICE);
            return false;
        }

        if (!is_int($Value))
        {
            trigger_error("Value must be integer", E_USER_NOTICE);
            return false;
        }
        if (($Value < 80) or ( $Value > 120))
        {
            trigger_error("Value invalid.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('mixer', 'pitch'), $Value));
        if ($LMSData === NULL)
            return false;
        return ((int) $LMSData->Data[0] == $Value);
    }

    //fertig
    /**
     * Liefert den aktuellen Pitch-Wert.
     *
     * @return integer
     * @exception
     */
    public function GetPitch()
    {
        $this->RequestState("Pitch");
        trigger_error("This function is deprecated, use RequestState an GetValue.", E_USER_DEPRECATED);
        return GetValueInteger($this->GetIDForIdent("Pitch"));
    }

    //fertig
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
    public function SetMute(bool $Value)
    {
        if (!is_bool($Value))
        {
            trigger_error("Value must boolean.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('mixer', 'muting'), (int) $Value));
        if ($LMSData === NULL)
            return false;
        return ((int) $LMSData->Data[0] == (int) $Value);
    }

    //fertig
    public function GetMute()
    {
        $this->RequestState("Mute");
        trigger_error("This function is deprecated, use RequestState an GetValue.", E_USER_DEPRECATED);
        return GetValueBoolean($this->GetIDForIdent("Mute"));
    }

    //fertig
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
    public function SetRepeat(int $Value)
    {
        if (!is_int($Value))
        {
            trigger_error("Value must be integer", E_USER_NOTICE);
            return false;
        }
        if (($Value < 0) or ( $Value > 2))
        {
            trigger_error("Value must be 0, 1 or 2.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'repeat'), $Value));
        if ($LMSData === NULL)
            return false;

        return ((int) $LMSData->Data[0] == $Value);
    }

    //fertig
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
        $this->RequestState("Repeat");
        trigger_error("This function is deprecated, use RequestState an GetValue.", E_USER_DEPRECATED);
        return GetValueInteger($this->GetIDForIdent("Repeat"));
    }

    //fertig
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
    public function SetShuffle(int $Value)
    {
        if (!is_int($Value))
        {
            trigger_error("Value must be integer", E_USER_NOTICE);
            return false;
        }
        if (($Value < 0) or ( $Value > 2))
        {
            trigger_error("Value must be 0, 1 or 2.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'shuffle'), $Value));
        if ($LMSData === NULL)
            return false;
        return ((int) $LMSData->Data[0] == $Value);
    }

    //fertig
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
        $this->RequestState("Shuffle");
        trigger_error("This function is deprecated, use RequestState an GetValue.", E_USER_DEPRECATED);
        return GetValueInteger($this->GetIDForIdent("Shuffle"));
    }

    //fertig
    /**
     * Simuliert einen Tastendruck auf einen der Preset-Tasten.
     *
     * @param integer $Value
     * 1 - 6 = Taste 1 bis 6
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SelectPreset(int $Value)
    {
        if (!is_int($Value))
        {
            trigger_error("Value must be integer", E_USER_NOTICE);
            return false;
        }
        if (($Value < 1) or ( $Value > 6))
        {
            trigger_error("Value out of Range.", E_USER_NOTICE);
            return false;
        }
        $Value = 'preset_' . $Value . '.single';
        $LMSData = $this->SendDirect(new LMSData('button', $Value));
        if ($LMSData === NULL)
            return false;
        return ( $LMSData->Data[0] == $Value);
    }

    //fertig
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
    public function Power(bool $Value)
    {
        if (!is_bool($Value))
        {
            trigger_error("Value must boolean.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('power', (int) $Value));
        if ($LMSData === NULL)
            return false;
        return ((int) $LMSData->Data[0] == (int) $Value);
    }

    //fertig
    /**
     * Springt in der aktuellen Wiedergabeliste auf einen Titel.
     *
     * @param integer $Value
     * Track in der Wiedergabeliste auf welchen gesprungen werden soll.
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function PlayTrack(int $Index)
    {
        if (!is_int($Index))
        {
            trigger_error("Index must be integer", E_USER_NOTICE);
            return false;
        }
        if (($Index < 1) or ( $Index > GetValueInteger($this->GetIDForIdent('Tracks'))))
        {
            trigger_error("Index out of Range.", E_USER_NOTICE);
            return false;
        }

        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'index'), $Index - 1));
        if ($LMSData === NULL)
            return false;
        return ($LMSData->Data[0] == $Index - 1);
    }

    //fertig
    /**
     * Springt in der aktuellen Wiedergabeliste auf den nächsten Titel.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function NextTrack()
    {
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'index'), '+1'));
        if ($LMSData === NULL)
            return false;
        return ($LMSData->Data[0] == "+1");
    }

    //fertig
    /**
     * Springt in der aktuellen Wiedergabeliste auf den vorherigen Titel.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function PreviousTrack()
    {
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'index'), '-1'));
        if ($LMSData === NULL)
            return false;
        return ($LMSData->Data[0] == "-1");
    }

    /**
     * Setzt eine absolute Zeit-Position des aktuellen Titels.
     *
     * @param integer $Value Zeit in Sekunden.
     * @return boolean true bei erfolgreicher Ausführung und Rückmeldung.
     * @exception Wenn Befehl nicht ausgeführt werden konnte.
     */
    public function SetPosition(int $Value)
    {
        if (!is_int($Value))
        {
            trigger_error("Value must be integer", E_USER_NOTICE);
            return false;
        }
        if ($Value > $this->GetBuffer('DurationRAW'))
        {
            trigger_error("Value greater as duration", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('time', $Value));
        if ($LMSData === NULL)
            return false;
        return ($Value == $LMSData->Data[0]);
    }

    //fertig
    /**
     * Liest die aktuelle Zeit-Position des aktuellen Titels.
     *
     * @return integer
     * Zeit in Sekunden.
     * @exception
     */
    public function GetPosition()
    {
        $this->RequestState("Position");
        trigger_error("This function is deprecated, use RequestState an GetValue.", E_USER_DEPRECATED);
        return GetValueInteger($this->GetIDForIdent("Position"));
    }

    //fertig
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
        if (!is_string($Name))
        {
            trigger_error("Name must be string", E_USER_NOTICE);
            return false;
        }
        if ($Name == "")
        {
            trigger_error("Name cannot be empty", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'save'), array(rawurlencode($Name), 'silent:1')));
        if ($LMSData === NULL)
            return false;
        return (rawurldecode($LMSData->Data[0]) == $Name);
    }

    //fertig
    /**
     * Speichert die aktuelle Wiedergabeliste vom Gerät in einer festen Wiedergabelisten-Datei auf dem LMS-Server.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SaveTempPlaylist()
    {
        return ($this->SavePlaylist((string) $this->InstanceID));
    }

    //fertig
    /**
     * Lädt eine Wiedergabelisten-Datei aus dem LMS-Server und startet die Wiedergabe derselben auf dem Gerät.
     *
     * @param string $Name
     * Der Name der Wiedergabeliste. Eine URL zu einem Stream, einem Verzeichniss oder einer Datei
     * @return string
     * Kompletter Pfad der Wiedergabeliste.
     * @exception
     */
    public function LoadPlaylist(string $Name)
    {
        if (!is_string($Name))
        {
            trigger_error("Name must be string", E_USER_NOTICE);
            return false;
        }
        if ($Name == "")
        {
            trigger_error("Name cannot be empty", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'load'), array(rawurlencode($Name), 'noplay:1')));
        if ($LMSData === NULL)
            return false;
        $ret = rawurldecode($LMSData->Data[0]);
        if (($ret == '/' . $Name) or ( $ret == '\\' . $Name))
        {
            trigger_error("Playlist not found.", E_USER_NOTICE);
            return false;
        }
        return rawurldecode($ret);
    }

    //fertig
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
        if (!is_string($Name))
        {
            trigger_error("Name must be string", E_USER_NOTICE);
            return false;
        }
        if ($Name == "")
        {
            trigger_error("Name cannot be empty", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'resume'), array(rawurlencode($Name), 'noplay:1')));
        if ($LMSData === NULL)
            return false;
        $ret = rawurldecode($LMSData->Data[0]);
        if (($ret == '/' . $Name) or ( $ret == '\\' . $Name))
        {
            trigger_error("Playlist not found.", E_USER_NOTICE);
            return false;
        }
        return rawurldecode($ret);
    }

    //fertig
    /**
     * Lädt eine zuvor gespeicherte Wiedergabelisten-Datei und setzt die Wiedergabe fort.
     *
     * @return boolean
     * true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function LoadTempPlaylist()
    {
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'load'), array((string) $this->InstanceID, 'wipePlaylist:1', 'noplay:1')));
        if ($LMSData === NULL)
            return false;
        if (rawurldecode($LMSData->Data[0]) != (string) $this->InstanceID)
        {
            trigger_error("TempPlaylist not found.", E_USER_NOTICE);
            return false;
        }
        return true;
    }

    //fertig
    private function _PlaylistControl(string $cmd, string $item, string $errormsg)
    {
        $LMSData = $this->SendDirect(new LMSData('playlistcontrol', array($cmd, $item)));
        if ($LMSData === NULL)
            return false;
        $LMSData->SliceData();
        $LMSTaggedData = (new LMSTaggingArray($LMSData->Data))->DataArray();
        if (!array_key_exists('Count', $LMSTaggedData))
        {
            trigger_error($errormsg, E_USER_NOTICE);
            return false;
        }
        return true;
    }

    //fertig
    public function LoadPlaylistByAlbumID(int $AlbumID)
    {
        if (!is_int($AlbumID))
        {
            trigger_error("AlbumID must be integer.", E_USER_NOTICE);
            return false;
        }
        return $this->_PlaylistControl('cmd:load', 'album_id:' . $AlbumID, "AlbumID not found.");
    }

    //fertig
    public function LoadPlaylistByGenreID(int $GenreID)
    {
        if (!is_int($GenreID))
        {
            trigger_error("GenreID must be integer.", E_USER_NOTICE);
            return false;
        }
        return $this->_PlaylistControl('cmd:load', 'genre_id:' . $GenreID, "GenreID not found.");
    }

    //fertig
    public function LoadPlaylistByArtistID(int $ArtistID)
    {
        if (!is_int($ArtistID))
        {
            trigger_error("ArtistID must be integer.", E_USER_NOTICE);
            return false;
        }
        return $this->_PlaylistControl('cmd:load', 'artist_id:' . $ArtistID, "ArtistID not found.");
    }

    //fertig
    public function LoadPlaylistByPlaylistID(int $PlaylistID)
    {
        if (!is_int($PlaylistID))
        {
            trigger_error("PlaylistID must be integer.", E_USER_NOTICE);
            return false;
        }
        return $this->_PlaylistControl('cmd:load', 'playlist_id:' . $PlaylistID, "PlaylistID not found.");
    }

    //todo folder_id (int)
    //todo track_id (string)
    //todo alles mit Load auch mit add und add mit move
    //fertig
//The "playlist clear" command removes any song that is on the playlist. The player is stopped.
    public function ClearPlaylist()
    {
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'clear'), ''));
        if ($LMSData === NULL)
            return false;
        return true;
    }

    //fertig
    /*
      The "playlist delete" command deletes the song at the specified index from the current playlist.
     */
    public function DeleteSongFromPlaylist(int $Position)
    {
        if (!is_int($Position))
        {
            trigger_error("Position must be integer.", E_USER_NOTICE);
            return false;
        }
        $Position--;
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'delete'), $Position));
        if ($LMSData == NULL)
            return false;
        if (count($LMSData->Data) > 0)
        {
            trigger_error("Error delete song from playlist.", E_USER_NOTICE);
            return false;
        }
        return ($LMSData->Data[0] == $Position + 1);
    }

    /*
      The "playlist add" command adds the specified song URL, playlist or directory contents to the end of the current playlist. Songs currently playing or already on the playlist are not affected.

      Examples:

      Request: "04:20:00:12:23:45 playlist add /music/abba/01_Voulez_Vous.mp3<LF>"
      Response: "04:20:00:12:23:45 playlist add /music/abba/01_Voulez_Vous.mp3<LF>"

      Request: "04:20:00:12:23:45 playlist add /playlists/abba.m3u<LF>"
      Response: "04:20:00:12:23:45 playlist add /playlists/abba.m3u<LF>"
     */

    public function AddSongToPlaylist(string $SongURL)
    {
        return $this->AddSongToPlaylistEx($SongURL, -1);
    }

    public function AddSongToPlaylistEx(string $SongURL, int $Position)
    {
        if ($this->GetValidSongURL($SongURL) == false)
            return false;
        if (!is_int($Position))
        {
            trigger_error("Position must be integer.", E_USER_NOTICE);
            return false;
        }
        // alte Tracks speichern
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'add'), $SongURL));
        if ($LMSData === NULL)
            return FALSE;
        //neue Tracks holen
        // alt = 5
        // neu = 10
        // tomove = neu - alt = 5 hinzufügt.
        // Postition = 3
        // move alt(5, = erster neuer) to Postition (3)
        // move alt+1 to position+1
        //etc..
    }

    /*
     *    <playerid> playlist move <fromindex> <toindex>
      The "playlist move" command moves the song at the specified index to a new index in the playlist. An offset of zero is the first song in the playlist.
      Examples
      Request: "04:20:00:12:23:45 playlist move 0 5<LF>"
      Response: "04:20:00:12:23:45 playlist move 0 5<LF>"
     */

    public function MoveSongInPlaylist(int $Position, int $NewPosition)
    {
        if (!is_int($Position))
        {
            trigger_error("Position must be integer.", E_USER_NOTICE);
            return false;
        }
        $Position--;
        if (!is_int($NewPosition))
        {
            trigger_error("Position must be integer.", E_USER_NOTICE);
            return false;
        }
        $NewPosition--;
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'move'), array($Position, $NewPosition)));
        if ($LMSData === NULL)
            return FALSE;
        if (($LMSData->Data[0] <> $Position) or ( $LMSData->Data[1] <> $NewPosition))
        {
            trigger_error("Error on move Song in playlist.", E_USER_NOTICE);
            return false;
        }
        return true;
    }

    public function LoadPreviewPlaylist()
    {
        
    }

    /*
     * 
      <playerid> playlist preview <taggedParameters>

      When called without a cmd param of stop, replace the current playlist with the playlist specified by url, but save the current playlist to tempplaylist_<playerid>.m3u for later retrieval. When called with the cmd param of stop, stops the currently playing playlist and loads (if possible) the previous playlist. Restored playlist jumps to beginning of CURTRACK when present in m3u file, and does not autoplay restored playlist.

      Examples:

      Request: "04:20:00:12:23:45 playlist preview url:db:album.titlesearch=A%20FEAST%20OF%20WIRE title:A%20Feast%20Of%20Wire<LF>"
      Response: "04:20:00:12:23:45 playlist preview url:db:album.titlesearch=A%20FEAST%20OF%20WIRE title:A%20Feast%20Of%20Wire<LF>"

      Request: "04:20:00:12:23:45 playlist preview cmd:stop<LF>"
      Response: "04:20:00:12:23:45 playlist preview cmd:stop<LF>"

     */

    //TESTEN TODO
    public function GetPlaylistInfo()
    {
        $LMSData = $this->SendDirect(new LMSData(array('playlist', 'playlistsinfo'), ''));
        if ($LMSData === NULL)
            return false;
        if (count($LMSData->Data) == 1)
            return array();
        $SongInfo = new LMSSongInfo($LMSData->Data);
        return $SongInfo->GetSong();
    }

    //fertig
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
    public function GetSongInfoByTrackIndex(int $Index)
    {
        if (is_int($Index))
            $Index--;
        else
        {
            trigger_error("Index must be integer.", E_USER_NOTICE);
        }
        if ($Index == -1)
            $Index = '-';
        $LMSData = $this->SendDirect(new LMSData(array('status', (string) $Index, '1'), 'tags:gladiqrRtueJINpsy'));
        if ($LMSData === NULL)
            return false;
        $SongInfo = new LMSSongInfo($LMSData->Data);
        $SongArray = $SongInfo->GetSong();
        if (count($SongArray) == 1)
            throw new Exception("Index not valid.");
        return $SongArray;
    }

    //fertig
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
        $LMSData = $this->SendDirect(new LMSData(array('status', '0', (string) $max), 'tags:gladiqrRtueJINpsy'));
        if ($LMSData === NULL)
            return false;
        $LMSData->SliceData();
        $SongInfo = new LMSSongInfo($LMSData->Data);
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
                $Time = ($this->GetBuffer("DurationRAW") / 100) * $Value;
                $result = $this->SetPosition(intval($Time));
                break;
            case "Index":
                $result = $this->PlayTrack($Value);
                break;
            case "SleepTimer":
                $result = $this->SetSleep($Value);
                break;
            default:
                trigger_error("Invalid ident", E_USER_NOTICE);
                return;
        }
        if ($result == false)
        {
            trigger_error("Error on Execute Action", E_USER_NOTICE);
        }
    }

################## PRIVATE
    //fertig

    private function _SetNewName(string $Name)
    {
        if (!$this->ReadPropertyBoolean('changeName'))
            return;
        if (IPS_GetName($this->InstanceID) <> trim($Name))
        {
            IPS_SetName($this->InstanceID, trim($Name));
        }
    }

    //fertig
    private function _isPlayerConnected()
    {
        return GetValueBoolean($this->GetIDForIdent('Connected'));
    }

    //fertig
    private function _isPlayerOn()
    {
        return GetValueBoolean($this->GetIDForIdent('Power'));
    }

    //fertig
    private function _SetConnected(bool $Status)
    {
        if (!$this->SetValueBoolean('Connected', $Status))
            return;
        if ($Status === true)
            $this->RequestState('Power');
        else
            $this->_SetNewPower(FALSE);
    }

    //fertig
    private function _SetNewSleepTimeout(int $Value)
    {
        $this->SetValueString('SleepTimeout', $this->ConvertSeconds($Value));
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //todo sync
    private function _SetNewSyncMembers(string $PlayerMACs)
    {
        if ($PlayerMACs == '-')
            $PlayerMACs = '';
        if ($this->GetBuffer('Sync') <> $PlayerMACs)
        {
            $this->SetBuffer('Sync', $PlayerMACs);
            $this->_SetNewSyncProfil();
        }
    }

    private function _SetNewSyncProfil()
    {
        $PlayerMACs = explode(',', $this->GetBuffer('Sync'));
    }

    //fertig
    private function _SetNewPower(bool $Power)
    {
        if (!$this->SetValueBoolean('Power', $Power))
            return;
        if ($Power === true)
            $this->RequestAllState();
        else
            $this->_SetModeToStop();
    }

    //fertig
    private function _SetNewVolume(int $Value)
    {
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

    //fertig
    private function _SetModeToPlay()
    {
//        $this->SetValueBoolean('Power', true);
        if ($this->SetValueInteger('Status', 2))
            $this->Send(new LMSData(array('status', '-', '1',), 'subscribe:' . $this->ReadPropertyInteger('Interval'), false));
    }

    //fertig
    private function _SetModeToStop()
    {
        if ($this->SetValueInteger('Status', 1))
        {
            if (GetValueInteger($this->GetIDForIdent('SleepTimer')) == 0)
                $this->Send(new LMSData(array('status', '-', '1',), 'subscribe:0', false));
        }
        //$this->_SetNewSleepTimer(0);
        //$this->_SetNewSleepTimeout(0);
    }

    //fertig
    private function _SetModeToPause()
    {
        if (!$this->_isPlayerOn())
            return;
        if ($this->SetValueInteger('Status', 3))
        {
            if (GetValueInteger($this->GetIDForIdent('SleepTimer')) == 0)
                $this->Send(new LMSData(array('status', '-', '1',), 'subscribe:0', false));
        }
    }

    //fertig
    private function _SetSeekable(bool $Value)
    {
        if ((bool) $Value <> (bool) $this->GetBuffer('can_seek'))
        {
            if ((bool) $Value)
                $this->EnableAction("Position2");
            else
                $this->DisableAction('Position2');
            $this->SetBuffer('can_seek', (int) $Value);
        }
    }

    //fertig
    private function _SetNewSleepTimer($Value)
    {
        if ($this->SetValueInteger('SleepTimer', $Value))
            if (GetValueInteger($this->GetIDForIdent('Status')) <> 2)
                $this->Send(new LMSData(array('status', '-', '1',), 'subscribe:0', false));
    }

    //fertig
    private function _SetNewDuration(int $Duration)
    {
        if ($Duration == 0)
        {
            $this->SetValueString('Duration', '');
            $this->SetBuffer('DurationRAW', 0);
            $this->SetValueInteger('Position2', 0);
            $this->DisableAction('Position2');
        }
        else
        {
            $this->SetBuffer('DurationRAW', $Duration);
            if ($this->SetValueString('Duration', $this->ConvertSeconds($Duration)))
            {
                if ((bool) $this->GetBuffer('can_seek'))
                    $this->EnableAction("Position2");
            }
        }
    }

    //fertig
    private function _SetNewTime(int $Time)
    {
        $this->SetBuffer('PositionRAW', $Time);
        $this->SetValueString('Position', $this->ConvertSeconds($Time));
        if ((bool) $this->GetBuffer('can_seek'))
        {
            $Value = (100 / $this->GetBuffer('DurationRAW') * $Time);
            $this->SetValueInteger('Position2', intval(round($Value)));
        }
    }

    //fertig
    private function _RefreshPlaylistIndex()
    {
        if (!$this->ReadPropertyBoolean('showPlaylist'))
            return;
        $ScriptID = $this->ReadPropertyInteger('Playlistconfig');
        if ($ScriptID == 0)
            return;
        if (!IPS_ScriptExists($ScriptID))
            return;
        $result = IPS_RunScriptWaitEx($ScriptID, array('SENDER' => 'SqueezeBox'));
        $Config = unserialize($result);
        if (($Config === false) or ( !is_array($Config)))
        {
            trigger_error('Error on read Playlistconfig-Script', E_USER_NOTICE);
            return;
        }
        // Daten aus Buffer holen und Tabelle neu bauen
        $OldBuffers = $this->_GetBufferList();
        $this->SendDebug('Bufferlist read', count($OldBuffers), 0);
        $Data = $this->_GetBuffer($OldBuffers);

        $HTMLData = $this->GetTableHeader($Config);
        $pos = 0;
        $CurrentTrack = GetValueInteger($this->GetIDForIdent('Index'));

        if (count($Data) > 0)
        {
            foreach ($Data as $Position => $Line)
            {
                $Line['Position'] = $Position + 1;
                if (array_key_exists('Duration', $Line))
                {
                    $Line['Duration'] = $this->ConvertSeconds($Line['Duration']);
                }
                else
                {
                    $Line['Duration'] = '---';
                }

                $Line['Play'] = $Line['Position'] == $CurrentTrack ? '<div class="iconMediumSpinner ipsIconArrowRight" style="width: 100%; background-position: center center;"></div>' : '';

                $HTMLData .='<tr style="' . $Config['Style']['BR' . ($Line['Position'] == $CurrentTrack ? 'A' : ($pos % 2 ? 'U' : 'G'))] . '"onclick="xhrGet' . $this->InstanceID . '({ url: \'hook/SqueezeBoxPlaylist' . $this->InstanceID . '?Index=' . $Line['Position'] . '\' });">';

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

    //fertig
    private function _RefreshPlaylist($Empty = false)
    {
        if (!$this->ReadPropertyBoolean('showPlaylist'))
            return;
        $ScriptID = $this->ReadPropertyInteger('Playlistconfig');
        if ($ScriptID == 0)
            return;
        if (!IPS_ScriptExists($ScriptID))
            return;
        if ($Empty)
        {
            $this->_ClearBuffer();
            $PlaylistDataArray = array();
        }
        else
        {
            $PlaylistDataArray = $this->GetSongInfoOfCurrentPlaylist();
            if ($PlaylistDataArray === false)
            {
                $this->_ClearBuffer();
                trigger_error('Error on read Playlist', E_USER_NOTICE);
                return false;
            }
            $this->_SetBuffer($PlaylistDataArray);
        }
        //Tabelle neu bauen lassen.
        $this->_RefreshPlaylistIndex();
        return;
    }

    //fertig
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
    "HFPlay"  => "color:#ffffff; width:35px; align:left;",
    // <th>-Tag Feld Position:
    "HFPosition"  => "color:#ffffff; width:35px; align:left;",
    // <th>-Tag Feld Title:
    "HFTitle"  => "color:#ffffff; width:35px; align:left;",
    // <th>-Tag Feld Artist:
    "HFArtist"  => "color:#ffffff; width:35px; align:left;",
    // <th>-Tag Feld Bitrate:
    "HFBitrate"  => "color:#ffffff; width:35px; align:left;",
    // <th>-Tag Feld Duration:
    "HFDuration"  => "color:#ffffff; width:35px; align:left;",
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
echo serialize($Config);
//LSQ_DisplayPlaylist($_IPS["TARGET"],$Config);
?>';
        return $Script;
    }

    /**
     * Liefert alle Items aus den Buffern.
     * 
     * @access private
     * @param array $Bufferlist Enthält die Liste der Buffer
     * @return array Array mit allen Items aus den Buffern
     */
    private function _GetBuffer(array $Bufferlist)
    {
        $Line = "";
        foreach ($Bufferlist as $Buffer)
        {
            $Line .= $this->GetBuffer('Playlist' . $Buffer);
        }
        $Liste = unserialize($Line);
        if ($Liste == false)
            return array();
        return $Liste;
    }

    /**
     * Leert alle Buffer.
     * 
     * @access private
     */
    private function _ClearBuffer()
    {
        $OldBuffers = $this->_GetBufferList();
        $this->SendDebug('Bufferlist old', count($OldBuffers), 0);
        foreach ($OldBuffers as $OldBuffer)
        {
            $this->SetBuffer('Playlist' . $OldBuffer, "");
        }
        $this->SetBuffer("Bufferlist", "");
    }

    /**
     * Füllt Buffer mit den Daten der Items
     * 
     * @access private
     * @param array $Items Items welche in die Buffer geschrieben werden sollen.
     * @return array Array welche die befüllten Buffer enthält.
     */
    private function _SetBuffer(array $Items)
    {

        $OldBuffers = $this->_GetBufferList();
        $this->SendDebug('Bufferlist old', count($OldBuffers), 0);

        $Lines = str_split(serialize($Items), 8000);
        foreach ($Lines as $BufferIndex => $BufferLine)
        {
            $this->SetBuffer('Playlist' . $BufferIndex, $BufferLine);
        }
        $NewBuffers = array_keys($Lines);
        $this->SendDebug('Bufferlist new', count($NewBuffers), 0);
        $this->_SetBufferList($NewBuffers);
        $DelBuffers = array_diff_key($OldBuffers, $NewBuffers);
        $this->SendDebug('Bufferlist del', count($DelBuffers), 0);
        foreach ($DelBuffers as $DelBuffer)
        {
            $this->SetBuffer('Playlist' . $DelBuffer, "");
            $this->SendDebug('Bufferlist' . $DelBuffer, 'DELETE', 0);
        }
    }

    /**
     * Schreibt eine Liste von genutzen Buffern.
     * 
     * @access private
     * @param array $List Array welches die genutzen Buffer enthält.
     */
    private function _SetBufferList(array $List)
    {
        $this->SetBuffer('Bufferlist', serialize($List));
    }

    /**
     * Liefert ein Array über alle aktiven Buffer.
     * 
     * @access private
     * @return array Array welches die Buffer enthält
     */
    private function _GetBufferList()
    {
        $Buffers = unserialize($this->GetBuffer('Bufferlist'));
        if ($Buffers == false)
            return array();
        return $Buffers;
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
            $Size = $this->ReadPropertyString("CoverSize");
            $Player = $this->ReadPropertyString("Address");
            $CoverRAW = $this->GetCover($ParentID, "", $Size, $Player);
            if (!($CoverRAW === false))
            {
                IPS_SetMediaContent($CoverID, base64_encode($CoverRAW));
            }
        }
        return;
    }

################## Decode Data

    private function DecodeLMSResponse(LMSData $LMSData)
    {
        if ($LMSData == NULL)
            return false;
        $this->SendDebug('Decode', $LMSData, 0);
        switch ($LMSData->Command[0])
        {
            case 'signalstrength':
                $this->SetValueInteger('Signalstrength', (int) $LMSData->Data[0]);
                break;
            case 'name':
                $this->_SetNewName((string) $LMSData->Data[0]);
                break;
            case 'connected':
                $this->_SetConnected((int) $LMSData->Data[0] === 1);
                break;
            case 'client':
                if (($LMSData->Data[0] == 'disconnect') or ( $LMSData->Data[0] == 'forget'))
                    $this->_SetConnected(false);
                elseif (($LMSData->Data[0] == 'new') or ( $LMSData->Data[0] == 'reconnect'))
                    $this->_SetConnected(true);
                break;
            case 'sleep':
                $this->_SetNewSleepTimeout((int) $LMSData->Data[0]);
                break;
            case 'sync':
                $this->_SetNewSyncMembers((string) $LMSData->Data[0]);
                break;
            case 'power':
                $this->_SetNewPower((int) $LMSData->Data[0] == 1);
                break;
            case 'mixer':
                switch ($LMSData->Command[1])
                {
                    case 'volume':
                        $this->_SetNewVolume((int) $LMSData->Data[0]);
                        break;
                    case 'muting':
                        $this->SetValueBoolean('Mute', (bool) $LMSData->Data[0]);
                        break;
                    case 'bass':
                        if ($this->ReadPropertyBoolean('enableBass'))
                            $this->SetValueInteger('Bass', (int) ($LMSData->Data[0]));
                        break;
                    case 'treble':
                        if ($this->ReadPropertyBoolean('enableTreble'))
                            $this->SetValueInteger('Treble', (int) ($LMSData->Data[0]));
                        break;
                    case 'pitch':
                        if ($this->ReadPropertyBoolean('enablePitch'))
                            $this->SetValueInteger('Pitch', (int) ($LMSData->Data[0]));
                        break;
                    default:
                        return false;
                }
                break;
            case 'play':
                $this->_SetModeToPlay();
                break;
            case 'stop':
                $this->_SetModeToStop();
                break;
            case 'pause':
                if ((bool) $LMSData->Data[0])
                    $this->_SetModeToPause();
                else
                    $this->_SetModeToPlay();
                break;
            case 'mode':
                switch ($LMSData->Data[0])
                {
                    case 'play':
                        $this->_SetModeToPlay();
                        break;
                    case 'stop':
                        $this->_SetModeToStop();
                        break;
                    case 'pause':
                        if ((bool) $LMSData->Data[0])
                            $this->_SetModeToPause();
                        else
                            $this->_SetModeToPlay();
                        break;
                    default:
                        return false;
                }
                break;
            case 'time':
//                $this->tempData['Position'] = $LMSData->Data[0];
                $this->SetBuffer('PositionRAW', (int) $LMSData->Data[0]);
                $this->SetValueString('Position', $this->ConvertSeconds($LMSData->Data[0]));
                break;
            case 'genre':
                $this->SetValueString('Genre', trim(rawurldecode((string) $LMSData->Data[0])));
                break;
            case 'artist':
                $this->SetValueString('Artist', trim(rawurldecode((string) $LMSData->Data[0])));
                break;
            case 'album':
                $this->SetValueString('Album', trim(rawurldecode((string) $LMSData->Data[0])));
                break;
            case 'title':
                $this->SetValueString('Title', trim(rawurldecode((string) $LMSData->Data[0])));
                break;
            case 'duration':
                $this->_SetNewDuration((int) $LMSData->Data[0]);
                break;
            case 'remote':
                $this->_SetSeekable(!(bool) $LMSData->Data[0]);
                break;
            /*
              <playerid> current_title ?
              <playerid> path ?
             */
            case 'playlist':
                switch ($LMSData->Command[1])
                {
                    case 'stop':
//                        $this->_SetModeToStop();
                        break;
                    case 'pause':
                        if ((bool) $LMSData->Data[0])
                            $this->_SetModeToPause();
                        else
                            $this->_SetModeToPlay();
                        break;
                    case 'tracks':
                        if ((int) $LMSData->Data[0] == 0)
                        { // alles leeren
                            $this->SetValueString('Title', '');
                            $this->SetValueString('Artist', '');
                            $this->SetValueString('Album', '');
                            $this->SetValueString('Genre', '');
                            $this->SetValueString('Duration', '0:00');
                            $this->SetBuffer('DurationRAW', 0);
                            $this->SetValueInteger('Position2', 0);
                            $this->SetBuffer('PositionRAW', 0);
                            $this->SetValueString('Position', '0:00');
                            $this->SetValueInteger('Index', 0);
                            $this->SetCover();
                            $this->_RefreshPlaylist(true);
                        }
                        $ProfileName = "Tracklist.Squeezebox." . $this->InstanceID;
                        if ($this->SetValueInteger('Tracks', (int) $LMSData->Data[0]))
                        {
                            if (!IPS_VariableProfileExists($ProfileName))
                            {
                                IPS_CreateVariableProfile($ProfileName, 1);
                                IPS_SetVariableProfileValues($ProfileName, 1, (int) $LMSData->Data[0], 1);
                            }
                            else
                            {
                                if (IPS_GetVariableProfile($ProfileName)['MaxValue'] <> (int) $LMSData->Data[0])
                                    IPS_SetVariableProfileValues($ProfileName, 1, (int) $LMSData->Data[0], 1);
                            }
                            if ((int) $LMSData->Data[0] > 0)
                                $this->_RefreshPlaylist();
                        }
                        break;
                    case 'open':
                    case 'load_done':
                        $this->_RefreshPlaylist();
                        break;
                    case 'shuffle':
                        if ($this->SetValueInteger('Shuffle', (int) $LMSData->Data[0]))
                            $this->_RefreshPlaylist();
                        break;
                    case 'repeat':
                        $this->SetValueInteger('Repeat', (int) $LMSData->Data[0]);
                        break;
                    case 'name':
                        $this->SetValueString('Playlistname', trim(rawurldecode((string) $LMSData->Data[0])));
                        break;
                    case 'index':
                    case 'jump':
                        if ($LMSData->Data[0] == "")
                            break;
                        if (((string) $LMSData->Data[0][0] === '+') or ( (string) $LMSData->Data[0][0] === '-'))
                        {
                            if ($this->SetValueInteger('Index', GetValueInteger($this->GetIDForIdent('Index')) + (int) $LMSData->Data[0]))
                                $this->_RefreshPlaylistIndex();
                        }
                        else
                        {
                            if ($this->SetValueInteger('Index', (int) $LMSData->Data[0] + 1))
                                $this->_RefreshPlaylistIndex();
                        }
                        break;
                    case 'newsong':
                        $this->SetValueString('Title', trim(rawurldecode((string) $LMSData->Data[0])));
                        if (isset($LMSData->Data[1]))
                        {
                            $this->SetValueInteger('Index', (int) $LMSData->Data[1] + 1);
                            $this->_RefreshPlaylistIndex();
                        }
                        else
                            $this->SetValueInteger('Index', 0);

                        $this->Send(new LMSData('artist', '?', false));
                        $this->Send(new LMSData('album', '?', false));
                        $this->Send(new LMSData('genre', '?', false));
                        $this->Send(new LMSData('duration', '?', false));
                        $this->Send(new LMSData(array('playlist', 'name'), '?', false));
                        $this->Send(new LMSData(array('playlist', 'tracks'), '?', false));
                        $this->SetCover();
                        break;
                    default:
                        return false;
                }
                break;
            case 'newmetadata':
                $this->SetCover();
                break;

            case 'status':
                foreach ($LMSData->Data as $TaggedDataLine)
                {
                    $Data = new LMSTaggingData($TaggedDataLine);
                    switch ($Data->Name)
                    {
                        //subscribe:0
                        case 'player_name':
                            $this->_SetNewName((string) $Data->Value);
                            break;
                        //player_connected:1
                        //player_ip:192.168.201.83:38630
                        case 'power':
                            $this->_SetNewPower((int) $Data->Value == 1);
                            break;
                        case 'signalstrength':
                            $this->SetValueInteger('Signalstrength', (int) $Data->Value);
                            break;
                        case 'mode':
                            switch ($Data->Value)
                            {
                                case 'play':
                                    $this->_SetModeToPlay();
                                    break;
                                case 'stop':
                                    $this->_SetModeToStop();
                                    break;
                                case 'pause':
                                    $this->_SetModeToPause();
                                    break;
                            }
                            break;
                        case 'time':
                            $this->_SetNewTime((int) $Data->Value);
                            break;
                        case 'duration':
                            $this->_SetNewDuration((int) $Data->Value);
                            break;
                        case 'can_seek':
                            $this->_SetSeekable((int) $Data->Value == 1);
                            break;
                        case 'sleep':
                            $this->_SetNewSleepTimer((int) $Data->Value);
                            break;
                        case 'will_sleep_in':
                            $this->_SetNewSleepTimeout((int) $Data->Value);
                            break;
                        case 'mixer volume':
                            $this->_SetNewVolume((int) $Data->Value);
                            break;
                        case 'playlist repeat':
                            $this->SetValueInteger('Repeat', (int) $Data->Value);
                            break;
                        case 'playlist shuffle':
                            if ($this->SetValueInteger('Shuffle', (int) $Data->Value))
                                $this->_RefreshPlaylist();
                            break;
                        case 'playlist mode':
                            //TODO
                            break;
                        //seq_no:16                        
                        case 'playlist_cur_index':
                        case 'playlist index':
                            $this->SetValueInteger('Index', (int) $Data->Value + 1);
                            break;
                        //playlist_timestamp:1474744498.14079
                        //playlist_tracks:8
                        //digital_volume_control:1
                        //id:141676
                        case 'title':
                            $this->SetValueString('Title', trim(rawurldecode((string) $Data->Value)));
                            break;
                        case 'genre':
                            $this->SetValueString('Genre', trim(rawurldecode((string) $Data->Value)));
                            break;
                        case 'artist':
                            $this->SetValueString('Artist', trim(rawurldecode((string) $Data->Value)));
                            break;
                        case 'album':
                            $this->SetValueString('Album', trim(rawurldecode((string) $Data->Value)));
                            break;
                    }
                }
                break;
            default:
                return false;

////////////////////////////////////////////7

            /*


              case LSQResponse::displaynotify:
              case LSQResponse::remoteMeta:
              case LSQResponse::playlist_modified:
              case LSQResponse::currentSong:
              //ignore
              break;
              case LSQResponse::loadtracks:
              $this->RefreshPlaylist();
              break;
              case LSQResponse::prefset:
              break;
              case LSQResponse::current_title:
              case LSQResponse::album:
              if (is_array($LMSData->Data[0]))
              {
              $this->SetValueString('Album', trim(rawurldecode($LMSData->Data[0][0])));
              }
              else
              {
              $this->SetValueString('Album', trim(rawurldecode($LMSData->Data[0])));
              }
              break;
              case LSQResponse::playlist_name:
              $this->SetValueString('Playlistname', trim(rawurldecode($LMSData->Data[0])));

              break;
              case LSQResponse::playlist_tracks:
              case LSQResponse::status:
              $remote = false;
              foreach ($LMSData->Data[0] as $Data)
              {
              if ($LSQPart->Command == LSQResponse::remote)
              $remote = ($LSQPart->Value == 1);
              //                        if (($LSQPart->Command == LSQResponse::title) and $remote) continue;
              //                        if (($LSQPart->Command == LSQResponse::current_title) and $remote) continue;
              $this->decodeLSQEvent($LSQPart);
              }
              }
              break;

             */
        }

        return true;
    }

################## DataPoints Ankommend von Parent-LMS-Splitter

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('Receive Event', $JSONString, 0);
        $Data = json_decode($JSONString);
        // Objekt erzeugen welches die Commands und die Values enthält.
        $LMSData = new LMSData();
        $LMSData->CreateFromGenericObject($Data);
//        $this->SendDebug('Receive Event ', $LMSData, 0);
        // Ist das Command schon bekannt ?
        if ($LMSData->Command[0] <> false)
        {
            if ($LMSData->Command[0] == 'ignore')
                return;
            if ($this->_isPlayerConnected())
                $this->DecodeLMSResponse($LMSData);
            else
            {
                if ($LMSData->Command[0] == 'client')
                    $this->DecodeLMSResponse($LMSData);
            }
        } else
        {
            $this->SendDebug('UNKNOW', $LMSData, 0);
        }
    }

    ################## Datenaustausch

    /** Sende-Routine an den Parent
     *
     * @param LMSData $LMSData Description
     * @return LMSData Description
     */
    protected function SendDataToParent($LMSData)
    {
        $JSONData = $LMSData->ToJSONString("{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}");
        return parent::SendDataToParent($JSONData);
    }

    /**
     * Konvertiert $Data zu einem JSONString und versendet diese an den Splitter.
     *
     * @access protected
     * @param LMSData $LMSData Zu versendende Daten.
     * @return LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function Send(LMSData $LMSData)
    {
        try
        {
            if (!$this->_isPlayerConnected() and ( $LMSData->Command[0] != 'connected'))
                throw new Exception('Player not connected', E_USER_NOTICE);
            if (!$this->HasActiveParent())
                throw new Exception('Intance has no active parent.', E_USER_NOTICE);
            $LMSData->Address = $this->ReadPropertyString('Address');
            $this->SendDebug('Send', $LMSData, 0);

            $anwser = $this->SendDataToParent($LMSData);
            if ($anwser === false)
            {
                $this->SendDebug('Receive', 'No valid answer', 0);
                return NULL;
            }
            $result = unserialize($anwser);
            if ($LMSData->needResponse === false)
                return $result;
            $LMSData->Data = $result->Data;
            $this->SendDebug('Response', $LMSData, 0);
            return $LMSData;
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return NULL;
        }
    }

    /**
     * Konvertiert $Data zu einem String und versendet diesen direkt an den LMS.
     *
     * @access protected
     * @param LMSData $LMSData Zu versendende Daten.
     * @return LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    protected function SendDirect(LMSData $LMSData)
    {

        try
        {
            if (!$this->_isPlayerConnected() and ( $LMSData->Command[0] != 'connected'))
                throw new Exception('Player not connected', E_USER_NOTICE);
            if (!$this->HasActiveParent())
                throw new Exception('Intance has no active parent.', E_USER_NOTICE);

            $SplitterID = $this->GetParent();
            if (@IPS_GetProperty($SplitterID, "Open") === false)
                throw new Exception('Instance inactiv.', E_USER_NOTICE);

            $Host = @IPS_GetProperty($SplitterID, "Host");
            if ($Host === "")
                return NULL;

            $Port = IPS_GetProperty($SplitterID, 'Port');
//            $User = IPS_GetProperty($instance['ConnectionID'], 'Username');
//            $Pass = IPS_GetProperty($instance['ConnectionID'], 'Password');

            $LMSData->Address = $this->ReadPropertyString('Address');
            $Data = $LMSData->ToRawStringForLMS();
            $this->SendDebug('Send Direct', $LMSData, 0);
            $this->SendDebug('Send Direct', $Data, 0);

            $fp = @stream_socket_client("tcp://" . $Host . ":" . $Port, $errno, $errstr, 1);
            if (!$fp)
                throw new Exception('No anwser from LMS', E_USER_NOTICE);
            else
            {
                stream_set_timeout($fp, 5);
                fwrite($fp, $Data);
                $anwser = stream_get_line($fp, 1024 * 1024 * 2, chr(0x0d));
                fclose($fp);
            }

            if ($anwser === false)
                throw new Exception('No anwser from LMS', E_USER_NOTICE);
            $this->SendDebug('Receive', $anwser, 0);
            $ReplyData = new LMSResponse($anwser);
            $LMSData->Data = $ReplyData->Data;
            $this->SendDebug('Receive Direct', $LMSData, 0);
            return $LMSData;
        }
        catch (Exception $ex)
        {
            $this->SendDebug("Receive Direct", $ex->getMessage(), 0);
            trigger_error($ex->getMessage(), $ex->getCode());
        }
        return NULL;
    }

    /**
     * Prüft den Parent auf vorhandensein und Status.
     * 
     * @return bool True wenn Parent vorhanden und in Status 102, sonst false.
     */
    protected function HasActiveParent()
    {
        $SplitterID = $this->GetParent();
        if ($SplitterID !== false)
        {
            $ParentID = IPS_GetInstance($SplitterID)['ConnectionID'];
            if ($ParentID > 0)
                if (IPS_GetInstance($ParentID)['InstanceStatus'] == 102)
                    return true;
        }
        return false;
    }

}

?>