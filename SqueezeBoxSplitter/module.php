<?

require_once(__DIR__ . "/../SqueezeBoxClass.php");  // diverse Klassen

class LMSSplitter extends IPSModule
{

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        //These lines are parsed on Instance creation
        // ClientSocket benötigt
        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}", "Logitech Media Server");
        $this->RegisterPropertyString("Host", "");
        $this->RegisterPropertyBoolean("Open", true);
        $this->RegisterPropertyInteger("Port", 9090);
        $this->RegisterPropertyInteger("Webport", 9000);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $change = false;

        // Zwangskonfiguration des ClientSocket
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            if (IPS_GetProperty($ParentID, 'Host') <> $this->ReadPropertyString('Host'))
            {
                IPS_SetProperty($ParentID, 'Host', $this->ReadPropertyString('Host'));
                $change = true;
            }
            if (IPS_GetProperty($ParentID, 'Port') <> $this->ReadPropertyInteger('Port'))
            {
                IPS_SetProperty($ParentID, 'Port', $this->ReadPropertyInteger('Port'));
                $change = true;
            }
            $ParentOpen = $this->ReadPropertyBoolean('Open');
            // Keine Verbindung erzwingen wenn Host leer ist, sonst folgt später Exception.
            if ($this->ReadPropertyString('Host') == '')
            {
                $this->SetStatus(202);
                $ParentOpen = false;
            }
            if (IPS_GetProperty($ParentID, 'Open') <> $ParentOpen)
            {
                IPS_SetProperty($ParentID, 'Open', $ParentOpen);
                $change = true;
            }
            if ($change)
                @IPS_ApplyChanges($ParentID);
        }
        //Workaround für persistente Daten der Instanz
        $this->RegisterVariableString("BufferIN", "BufferIN");
        $this->RegisterVariableString("BufferOUT", "BufferOUT");
        $this->RegisterVariableBoolean("WaitForResponse", "WaitForResponse");

        // Wenn wir verbunden sind, am LMS mit listen anmelden für Events
        if ($this->ReadPropertyBoolean('Open') and $this->HasActiveParent($ParentID))
        {
            $Data = new LMSData("listen 1");
            $this->SendLMSData($Data);
        }

        // Debug-Ausgabe
//        IPS_LogMessage('ApplyChanges', 'Instant:' . $this->InstanceID);
//        IPS_LogMessage('ApplyChanges', 'Host:' . $this->ReadPropertyString('Host'));
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function SendEx($Text)
    {
        return $this->SendDataToParent($Text);
    }

    public function Rescan()
    {
        $ret = $this->SendLMSData(new LMSData('rescan', LMSData::SendCommand));
        return $ret;
    }

    public function GetRescanProgress()
    {
        $ret = $this->SendLMSData(new LMSData('rescanprogress', LMSData::SendCommand));
        return $ret;
    }

    public function GetSongInfoByFileID(integer $ID)
    {
        
    }

    public function GetSongInfoByFileURL(string $File)
    {
        
    }

    public function CreateAllPlayer()
    {
        $players = $this->SendDataToParent('player count ?');

        for ($i = 0; $i < $players; $i++)
        {
            $player = $this->SendDataToParent('player id ' . $i . ' ?');
            $playerName = $this->SendDataToParent('player name ' . $i . ' ?');
            // Daten zerlegen und Childs anlegen/prüfen
            IPS_LogMessage('PLAYER ID' . $i, print_r($player, 1));
            IPS_LogMessage('PLAYER NAME' . $i, print_r($playerName, 1));
        }
    }

    public function GetPlayerInfo(integer $Index)
    {
        if (!is_int($Index))
            throw new Exception("Index must be integer.");
        $ret = $this->SendLMSData(new LMSData('players ' . (string) $Index . ' 1', LMSData::GetData));
        return $ret;
    }

    public function GetLibaryInfo()
    {
        $gernes = $this->SendLMSData(new LMSData('info total genres ?', LMSData::GetData));
        $artists = $this->SendLMSData(new LMSData('info total artists ?', LMSData::GetData));
        $albums = $this->SendLMSData(new LMSData('info total albums ?', LMSData::GetData));
        $songs = $this->SendLMSData(new LMSData('info total songs ?', LMSData::GetData));
        $ret = array('Geners' => $gernes, 'Artists' => $artists, 'Albums' => $albums, 'Songs' => $songs);
        return $ret;
    }

    public function GetVersion()
    {
        $ret = $this->SendLMSData(new LMSData('version ?', LMSData::GetData));
        return $ret;
    }

    public function GetSyncGroups()
    {
        $ret = $this->SendLMSData(new LMSData('syncgroups ?', LMSData::GetData));
        return $ret;
    }

    public function CreatePlaylist(string $Name) // ToDo antwort zerlegen
    {
        $ret = $this->SendLMSData(new LMSData('playlists new name%3A' . $Name, LMSData::GetData));
        return $ret;
    }

    public function DeletePlaylist(integer $PlayListId) // ToDo antwort zerlegen
    {
        $ret = $this->SendLMSData(new LMSData('playlists delete playlist_id%3A' . $PlayListId, LMSData::GetData));
        return $ret;
    }

    public function AddFileToPlaylist(integer $PlayListId, string $SongUrl, integer $Track = -1)
    {
        $ret = $this->SendLMSData(new LMSData('playlists edit cmd%3Aadd playlist_id%3A' . $PlayListId . ' url%3A' . $SongUrl, LMSData::GetData));
        if ($Track >= 0)
        {
//            $ret = $this->SendLMSData(new LMSData('playlists edit cmd%3Amove playlist_id%3A'.$PlayListId.' url%3A'.$SongUrl, LMSData::GetData));
            // index  toindex
        }
        return $ret;
    }

    public function DeleteFileFromPlaylist(integer $PlayListId, integer $SongId)
    {
        $ret = $this->SendLMSData(new LMSData('playlists edit cmd%3Adelete playlist_id%3A' . $PlayListId . ' index%3A' . $SongId, LMSData::GetData));
        return $ret;
    }

################## DataPoints
    //Ankommend von Child-Device

    public function ForwardData($JSONString)
    {
        $Data = json_decode($JSONString);

        // Daten annehmen und Command zusammenfügen wenn Array
        if (is_array($Data->LSQ->Command))
            $Data->LSQ->Command = implode(' ', $Data->LSQ->Command);

        // LMS-Objekt erzeugen und Daten mit Adresse ergänzen.
        $LMSData = new LMSData($Data->LSQ->Address . ' ' . $Data->LSQ->Command . ' ' . $Data->LSQ->Value, LMSData::SendCommand, false);

        // Senden über die Warteschlange
        $ret = $this->SendLMSData($LMSData);
        return $ret;
    }

    // Ankommend von Parent-ClientSocket
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $bufferID = $this->GetIDForIdent("BufferIN");

        // Empfangs Lock setzen
        if (!$this->lock("bufferin"))
        {
            throw new Exception("ReceiveBuffer is locked");
        }

        // Datenstream zusammenfügen
        $head = GetValueString($bufferID);
        SetValueString($bufferID, '');

        // Stream in einzelne Pakete schneiden
        $packet = explode(chr(0x0d), $head . utf8_decode($data->Buffer));

        // Rest vom Stream wieder in den Empfangsbuffer schieben
        $tail = array_pop($packet);
        SetValueString($bufferID, $tail);

        // Empfangs Lock aufheben
        $this->unlock("bufferin");

        // Pakete verarbeiten
        $ReceiveOK = true;
        foreach ($packet as $part)
        {
            $Data = new LMSResponse($part);
            // Server Antworten hier verarbeiten
            if ($Data->Device == LMSResponse::isServer)
            {
                $isResponse = $this->WriteResponse($Data->Data);
                if ($isResponse === true)
                {
                    // TODO LMS-Statusvariablen nachführen....
                    // 
                    continue; // später unnötig
                }
                elseif ($isResponse === false)
                { //Info Daten von Server verarbeiten
                    // TODO
                }
                else
                {
                    $ret = new Exception($isResponse);
                }
            }
            // Nicht Server antworten zu den Devices weiter senden.
            else
            {
                try
                {
                    if (!$this->SendDataToChildren(json_encode(Array("DataID" => "{CB5950B3-593C-4126-9F0F-8655A3944419}", "LMS" => $Data))))
                        $ReceiveOK = false;
                }
                catch (Exception $exc)
                {
                    $ret = new Exception($exc);
                }
            }
        }
        // Ist ein Fehler aufgetreten ?
        if (isset($ret))
            throw $ret; // dann erst jetzt werfen
        return $ReceiveOK;
    }

    // Sende-Routine an den Parent
    protected function SendDataToParent($Data)
    {
        //Semaphore setzen
        if (!$this->lock("ToParent"))
        {
            throw new Exception("Can not send to LMS");
        }
        // Daten senden
        try
        {
            $ret = IPS_SendDataToParent($this->InstanceID, json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => utf8_encode($Data . chr(0x0d)))));
        }
        catch (Exception $exc)
        {
            // Senden fehlgeschlagen
            $this->unlock("ToParent");
            throw new Exception("LMS not reachable");
        }
        $this->unlock("ToParent");
        return $ret;
    }

    // Sende-Routine an den Child
    protected function SendDataToChildren($Data)
    {
        return IPS_SendDataToChildren($this->InstanceID, $Data);
    }

    ################## Datenaustausch      
    // Sende-Routine des LMSData-Objektes an den Parent

    private function SendLMSData($LMSData)
    {
        $ParentID = $this->GetParent();
        if ($ParentID === false)
            throw new Exception('Instance has no parent.');
        else
        if (!$this->HasActiveParent($ParentID))
            throw new Exception('Instance has no active parent.');
        if ($LMSData->needResponse)
        {
            //Semaphore setzen für Sende-Routine
            if (!$this->lock("LMSData"))
            {
                throw new Exception("Can not send to LMS");
            }

            // Noch Umbauen wie das Device ?!?!
            if ($LMSData->Typ == LMSData::GetData)
            {
                $WaitData = substr($LMSData->Data, 0, -2);
            }
            else
            {
                $WaitData = $LMSData->Data;
            }

            // Anfrage für die Warteschleife schreiben
            if (!$this->SetWaitForResponse($WaitData))
            {
                // Konnte Daten nicht in den ResponseBuffer schreiben
                // Lock der Sende-Routine aufheben.
                $this->unlock("LMSData");
                throw new Exception("Can not send to LMS");
            }

            try
            {
                // Senden an Parent
                $this->SendDataToParent($LMSData->Data);
            }
            catch (Exception $exc)
            {
                // Konnte nicht senden
                //Daten in ResponseBuffer löschen
                $this->ResetWaitForResponse();
                // Lock der Sende-Routine aufheben.
                $this->unlock("LMSData");
                throw $exc;
            }
            // Lock der Sende-Routine aufheben.
            $this->unlock("LMSData");
            // Auf Antwort warten....
            $ret = $this->WaitForResponse();



            if ($ret === false) // Response-Warteschleife lief in Timeout
            {
                //  Daten in ResponseBuffer löschen                
                $this->ResetWaitForResponse();
                // Fehler
                throw new Exception("No answer from LMS");
            }

            // Rückgabe ist eine Bestätigung von einem Befehl
            if ($LMSData->Typ == LMSData::SendCommand)
            {
                if (trim($LMSData->Data) == trim($ret))
                    return true;
                else
                    return false;
            }
            // Rückgabe ist ein Wert auf eine Anfrage
            else
            {
                // Abschneiden der Anfrage.
                $ret = str_replace($WaitData, "", $ret);
                return $ret;
            }
        }
        // ohne Response, also ohne warten raussenden, 
        else
        {
            try
            {
                $this->SendDataToParent($LMSData->Data);
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
                    return $ret;
                }
                return false;
            }
        }
        return false;
    }

    private function WriteResponse($Array)
    {
        $Event = $this->GetIDForIdent('WaitForResponse');
        if (!GetValueBoolean($Event))
            return false;
        $buffer = $this->GetIDForIdent('BufferOUT');
        $Data = utf8_decode(implode(" ", $Array));
        if (!(strpos($Data, GetValueString($buffer)) === false))
        {
            if ($this->lock('BufferOut'))
            {
//                $Event = $this->GetIDForIdent('WaitForResponse');
                SetValueString($buffer, $Data);
                SetValueBoolean($Event, false);
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

    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    protected function HasActiveParent($ParentID)
    {
        if ($ParentID > 0)
        {
            $parent = IPS_GetInstance($ParentID);
            if ($parent['InstanceStatus'] == 102)
            {
                $this->SetStatus(102);
                return true;
            }
        }
        $this->SetStatus(203);
        return false;
    }

    protected function RequireParent($ModuleID, $Name = '')
    {

        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] == 0)
        {

            $parentID = IPS_CreateInstance($ModuleID);
            $instance = IPS_GetInstance($parentID);
            if ($Name == '')
                IPS_SetName($parentID, $instance['ModuleInfo']['ModuleName']);
            else
                IPS_SetName($parentID, $Name);
            IPS_ConnectInstance($this->InstanceID, $parentID);
        }
    }

    /*
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
}

?>