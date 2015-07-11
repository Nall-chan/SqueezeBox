<?

class SqueezeboxDevice extends IPSModule
{

    protected $MAC;

    public function __construct($InstanceID)
    {

        //Never delete this line!
        parent::__construct($InstanceID);
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString("MACAddress", "");
        $this->RegisterPropertyBoolean("EmulateStatus", false);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->RequireParent("{61051B08-5B92-472B-AFB2-6D971D9B99EE}");
        $this->RegisterProfileIntegerEx("Status.Squeezebox", "Information", "", "", Array(
            Array(0, "Prev", "", -1),
            Array(1, "Stop", "", -1),
            Array(2, "Play", "", -1),
            Array(3, "Pause", "", -1),
            Array(4, "Next", "", -1)
        ));
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
        $this->RegisterProfileInteger("Volume.Squeezebox", "Intensity", "", " %", 0, 100, 1);

        $this->RegisterVariableBoolean("Power", "Power", "~Switch", 1);
        $this->EnableAction("Power");
        $this->RegisterVariableInteger("Status", "Status", "Status.Squeezebox", 2);
        $this->EnableAction("Status");
        $this->RegisterVariableInteger("Volume", "Volume", "Volume.Squeezebox", 3);
        $this->EnableAction("Volume");
        $this->RegisterVariableInteger("Shuffle", "Shuffle", "Shuffle.Squeezebox", 4);
        $this->EnableAction("Shuffle");
        $this->RegisterVariableInteger("Repeat", "Repeat", "Repeat.Squeezebox", 5);
        $this->EnableAction("Repeat");

        $this->RegisterVariableString("Interpret", "Interpret", "", 6);
        $this->RegisterVariableString("Title", "Title", "", 7);
        $this->RegisterVariableString("Album", "Album", "", 8);
        $this->RegisterVariableString("Cover", "Cover", "", 9);
        $this->RegisterVariableString("Position", "Position", "", 10);
        $this->RegisterVariableString("Duration", "Dauer", "", 11);

        $this->MAC = $this->GetMAC($this->ReadPropertyString('MACAddress'));
    }

################## PRIVATE     

    public function Send($Text)
    {
        return IPS_SendDataToParent($this->InstanceID, json_encode(Array("DataID" => "{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}", "Buffer" => $Text)));
    }

    private function GetMAC($mac)
    {
        return $this->MAC = strtoupper(str_replace(array("-", ":"), "", $mac));
    }

    private function Cover($var_id,$player_id)
// setzt die $var_id mit dem Coverbild von $player_id
    {
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            $SQserverIP = IPS_GetProperty($ParentID, 'Host');
            $time = time();
//            $player_id = urlencode(implode(":", str_split($this->MAC, 2)));
            $str = "<table width='100%' cellspacing='0'><tr><td align=right>";
            $str = $str . "<img src='http://" . $SQserverIP . "/music/current/cover_150x150_$time.jpg?player=$player_id'></img>";
            $str = $str . "</td></tr></table>";
        }
        else
            $str = "";
        SetValueString($var_id, $str);
    }

    private function decode($Data)
    {
        $array = (array)$Data;
        IPS_LogMessage("IODevice DECODE", print_r($array,1));
        $powerID = $this->GetIDForIdent("Power");
        $volumeID = $this->GetIDForIdent("Volume");
        $modusID = $this->GetIDForIdent("Status");
        $repeatID = $this->GetIDForIdent("Repeat");
        $shuffleID = $this->GetIDForIdent("Shuffle");
        $coverID = $this->GetIDForIdent("Cover");
        $albumID = $this->GetIDForIdent("Album");
        $interpretID = $this->GetIDForIdent("Interpret");
        $durationID = $this->GetIDForIdent("Duration");
        $positionID = $this->GetIDForIdent("Position");
        $titleID = $this->GetIDForIdent("Title");

        if ($array[1] == 'power')
        {
            if ($array[2] == '1')
            {
                SetValueBoolean($powerID, true);
            }
            else
            {
                SetValueBoolean($powerID, false);
            }
        }
        //Lautstärke bei Änderung aktualisieren
        if (($array[1] == 'prefset') and ( $array[3] == 'volume'))
        {
            SetValueInteger($volumeID, (int)urldecode($array[4]));
        }
        // Repeat bei Änderung aktualisieren
        if (($array[1] == 'prefset') and ( $array[3] == 'repeat'))
        {
            SetValueInteger($repeatID, (int)$array[4]);
        }
        // Shuffle bei Änderung aktualisieren
        if (($array[1] == 'prefset') and ( $array[3] == 'shuffle'))
            SetValueInteger($shuffleID, (int)$array[4]);

        //Titel-Tag aktualisieren
        if (($array[1] == 'playlist') and ( $array[2] == 'newsong'))
        {
            SetValueString($titleID, utf8_decode(urldecode($array[3])));
            SetValueInteger($modusID, 2); // Button auf play
            // Subscripe auf entsprechende Box für Anzeige der Laufzeit
            /*            CSCK_SendText(SqueezeServer, $array[0] . " status - 1 subscribe:2" . chr(13));
              SqueezeSend($array[0], "artist ?");
              SqueezeSend($array[0], "album ?"); */
            IPS_Sleep(10);

            $this->Cover($coverID,$array[0]); // Cover anzeigen
        }
        // Album aktualisieren
        if ($array[1] == 'album')
        {
            if (isset($array[2]))
                SetValueString($albumID, utf8_decode(urldecode($array[2])));
            else
                SetValueString($albumID, '');
        }
        // Artist aktualisieren
        if ($array[1] == 'artist')
        {
            if (isset($array[2]))
                SetValueString($interpretID, utf8_decode(urldecode($array[2])));
            else
                SetValueString($interpretID, '');
        }
        // Steuerungstasten im Webfront aktualisieren
        if ($array[1] == 'play')
        {
            SetValueInteger($modusID, 2);
        }
        if ($array[1] == 'stop')
        {
            SetValueInteger($modusID, 1);
        }
        if (($array[1] == 'pause') and ( $array[2] == 1))
        {
            SetValueInteger($modusID, 3);
        }
        if (($array[1] == 'pause') and ( $array[2] == 0))
        {
            SetValueInteger($modusID, 2);
        }
        if (($array[1] == 'button') and ( $array[2] == 'jump_rew'))
        {
            SetValueInteger($modusID, 0);
        }
        if (($array[1] == 'button') and ( $array[2] == 'jump_fwd'))
        {
            SetValueInteger($modusID, 4);
        }
        if (($array[1] == 'status') and ( $array[4] == 'subscribe%3A2'))
        {
            foreach ($array as $item)
            {
                $item = utf8_decode(urldecode($item));
                $chunks = explode(":", $item);
                if ($chunks[0] == "time")
                {
                    SetValueString($positionID, @date('i:s', $chunks[1]));
//                IPS_LogMessage("Squeeze",date('i:s', $chunks[1]));
                }
                if ($chunks[0] == "duration")
                {
                    SetValueString($durationID, @date('i:s', $chunks[1]));
//                IPS_LogMessage("Squeeze",date('i:s', $chunks[1]));
                }
                if ($chunks[0] == "mode")
                {
                    if ($chunks[1] == 'play')
                    {
                        SetValueInteger($modusID, 2);
                    }
                    if ($chunks[1] == 'stop')
                    {
                        SetValueInteger($modusID, 1);
                    }
                    if ($chunks[1] == 'pause')
                    {
                        SetValueInteger($modusID, 3);
                    }
                }
//signalstrength%3A88 rate%3A1 can_seek%3A1  playlist%20mode%3Aoff seq_no%3A91 playlist_cur_index%3A39 playlist_timestamp%3A1415459866.17632 playlist_tracks%3A42 playlist%20index%3A39 id%3A26977 title%3AMonster%20High%20-%20Flederm%C3%A4use%20im%20Bauch%20-%20H%C3%B6rbuch%20-%20Abschnitt%2040 genre%3AH%C3%B6rbuch artist%3AMonster%20High album%3AMonster%20High%20-%20Flederm%C3%A4use%20im%20Bauch%20-%20H%C3%B6rbuch duration%3A614.034
                if ($chunks[0] == 'mixer volume')
                {
                    SetValueInteger($volumeID, (int)urldecode($chunks[1]));
                }
                if ($chunks[0] == 'playlist repeat')
                    SetValueInteger($repeatID, (int)$chunks[1]);
                if ($chunks[0] == 'playlist shuffle')
                    SetValueInteger($shuffleID, (int)$chunks[1]);
                if ($chunks[0] == 'power')
                {
                    if ($chunks[1] == '1')
                    {
                        SetValueBoolean($powerID, true);
                    }
                    else
                    {
                        SetValueBoolean($powerID, false);
                    }
                }
            }
        }
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
            $ret = $this->Send();
            IPS_LogMessage("RetSend", $ret);
        }
    }

################## DataPoints

    public function ReceiveData($JSONString)
    {
        // CB5950B3-593C-4126-9F0F-8655A3944419 ankommend von Splitter
        $data = json_decode($JSONString);
        $this->MAC = $this->GetMAC($this->ReadPropertyString('MACAddress'));
        if ($this->MAC === false)
            return false;
        IPS_LogMessage("IODevice MAC", $data->MAC);
        IPS_LogMessage("IODevice MAC", $this->MAC);
        if ($this->MAC == $data->MAC)
        {
            IPS_LogMessage("IODevice DATA", print_r($data->Payload, 1));
            $this->decode($data->Payload);
            return true;
        }
        else
            return false;
        //We would parse our payload here before sending it further...
    }

################## DUMMYS / WOARKAROUNDS - protected

    protected function HasActiveParent()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //          
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            if ($parent['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }

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