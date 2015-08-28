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
        $this->RegisterPropertyBoolean("Open", false);
        $this->RegisterPropertyInteger("Port", 9090);
        $this->RegisterPropertyInteger("Webport", 9000);
        $ID = $this->RegisterScript('PlaylistDesign', 'Playlist Config', $this->CreatePlaylistConfigScript(), -7);
        IPS_SetHidden($ID, true);
        $this->RegisterPropertyInteger("Playlistconfig", $ID);
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
            if (!$ParentOpen)
                $this->SetStatus(104);

            if ($this->ReadPropertyString('Host') == '')
            {
                if ($ParentOpen)
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

// Eigene Variablen
        $this->RegisterVariableInteger("RescanState", "Rescan läuft", "", 1);
        $this->RegisterVariableString("RescanInfo", "Rescan Status", "", 2);
        $this->RegisterVariableString("RescanProgress", "Rescan Fortschritt", "", 3);
        $this->EnableAction("RescanState");
        $this->RegisterVariableString("Playlists", "Playlisten", "", 4);

        // Eigene Scripte

        $ID = $this->RegisterScript('PlaylistDesign', 'Playlist Config', $this->CreatePlaylistConfigScript(), -4);
        IPS_SetHidden($ID, true);

//Workaround für persistente Daten der Instanz
        $this->RegisterVariableString("BufferIN", "BufferIN", "", -3);
        $this->RegisterVariableString("BufferOUT", "BufferOUT", "", -2);
        $this->RegisterVariableBoolean("WaitForResponse", "WaitForResponse", "", -1);
        IPS_SetHidden($this->GetIDForIdent('BufferIN'), true);
        IPS_SetHidden($this->GetIDForIdent('BufferOUT'), true);
        IPS_SetHidden($this->GetIDForIdent('WaitForResponse'), true);



// Wenn wir verbunden sind, am LMS mit listen anmelden für Events
        if ($this->ReadPropertyBoolean('Open') and $this->HasActiveParent($ParentID))
        {
            $Data = new LMSData("listen", "1");
            $this->SendLMSData($Data);
            $this->RefreshPlaylists();
        }
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function SendRaw($Command, $Value, $needResponse)
    {
        $LMSData = new LMSData($Command, $Value, $needResponse);
        $ret = $this->SendLMSData($LMSData);
        if (is_bool($ret))
            return $ret;
        return $ret;
//return new LMSTaggingData($ret);
//return $this->SendDataToParent($Text);
    }

    public function Rescan()
    {
        $ret = $this->SendLMSData(new LMSData('rescan'));
        return $ret;
    }

    public function GetRescanProgress()
    {
        $ret = $this->SendLMSData(new LMSData('rescanprogress'));
        $LSQEvent = new LSQTaggingData($ret, true);
        return (bool) $LSQEvent->Value;
    }

    public function GetNumberOfPlayers()
    {
        $players = $this->SendLMSData(new LMSData(array('player', 'count'), '?'));
        return (int) $players;
    }

    public function CreateAllPlayer()
    {
        $players = $this->SendLMSData(new LMSData(array('player', 'count'), '?'));
        $DevicesIDs = IPS_GetInstanceListByModuleID("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
        $CreatedPlayers = array();
        foreach ($DevicesIDs as $Device)
        {
            $KnownDevices[] = IPS_GetProperty($Device, 'Address');
        }
        for ($i = 0; $i < $players; $i++)
        {
            $player = rawurldecode($this->SendLMSData(new LMSData(array('player', 'id', $i), '?')));
            if (in_array($player, $KnownDevices))
                continue;
            $NewDevice = IPS_CreateInstance("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
            $playerName = $this->SendLMSData(new LMSData(array('player', 'name', $i), '?'));
            IPS_SetName($NewDevice, $playerName);
            if (IPS_GetInstance($NewDevice)['ConnectionID'] <> $this->InstanceID)
            {
                @IPS_DisconnectInstance($NewDevice);
                IPS_ConnectInstance($NewDevice, $this->InstanceID);
            }
            IPS_SetProperty($NewDevice, 'Address', $player);
            IPS_ApplyChanges($NewDevice);
            $CreatedPlayers[] = $NewDevice;
        }
        return $CreatedPlayers;
    }

    public function GetPlayerInfo(integer $Index)
    {
        if (!is_int($Index))
            throw new Exception("Index must be integer.");
        $ret = $this->SendLMSData(new LMSData(array('players', (string) $Index, '1')));
//        $LSQEvent = new LSQTaggingData($ret, true);
        $Data = new LMSResponse($ret);
        $LSQEvent = array();
        foreach ($Data->Data as $Part)
        {
            $Pair = new LSQTaggingData($Part, true);
            if (is_numeric($Pair->Value))
                $Pair->Value = (int) $Pair->Value;
            else
                $Pair->Value = rawurldecode($Pair->Value);
            $LSQEvent[ucfirst($Pair->Command)] = $Pair->Value;
        }
        return $LSQEvent;
    }

    public function GetLibaryInfo()
    {
        $genres = intval($this->SendLMSData(new LMSData(array('info', 'total', 'genres'), '?')));
        $artists = intval($this->SendLMSData(new LMSData(array('info', 'total', 'artists'), '?')));
        $albums = intval($this->SendLMSData(new LMSData(array('info', 'total', 'albums'), '?')));
        $songs = intval($this->SendLMSData(new LMSData(array('info', 'total', 'songs'), '?')));
        $ret = array('Genres' => $genres, 'Artists' => $artists, 'Albums' => $albums, 'Songs' => $songs);
        return $ret;
    }

    public function GetVersion()
    {
        $ret = $this->SendLMSData(new LMSData('version', '?'));
        return $ret;
    }

    public function GetSongInfoByFileID(integer $FileID)
    {
        $Data = $this->SendLMSData(new LMSData(array('songinfo', '0', '20'), array('track_id:' . $FileID, 'tags:gladiqrRtueJINpsy')));
        $SongInfo = new LSMSongInfo($Data);
        $Song = $SongInfo->GetSong();
        if (is_null($Song))
            throw new Exception("FileID not valid.");
        return $Song;
    }

    public function GetSongInfoByFileURL(string $FileURL)
    {
        $Data = $this->SendLMSData(new LMSData(array('songinfo', '0', '20'), array('url:' . rawurlencode($FileURL), 'tags:gladiqrRtueJINpsy')));
        $SongInfo = new LSMSongInfo($Data);
        $Song = $SongInfo->GetSong();
        if (count($Song) == 1)
            throw new Exception("FileURL not valid.");
        return $Song;
    }

    public function GetSyncGroups()
    {
        $ret = $this->SendLMSData(new LMSData('syncgroups', '?'));
        if ($ret === true)
            return false;

        $Data = new LMSTaggingData($ret);
        $AllPlayerIDs = IPS_GetInstanceListByModuleID('{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}');
        $Addresses = array();
        $FoundInstanzIDs = array();
        foreach ($AllPlayerIDs as $DeviceID)
        {
            $Addresses[$DeviceID] = IPS_GetProperty($DeviceID, 'Address');
        }
        $Search = explode(',', $Data->sync_members);
        foreach ($Search as $Value)
        {
            $FoundInstanzIDs[] = array_search($Value, $Addresses);
        }
        if (count($FoundInstanzIDs) > 0)
            return $FoundInstanzIDs;
        else
            return false;
    }

//
    public function CreatePlaylist(string $Name) // ToDo antwort zerlegen
    {
        $raw = $this->SendLMSData(new LMSData(array('playlists', 'new'), 'name:' . $Name));
        $Data = new LMSTaggingData($raw);
        if (property_exists($Data, 'playlist_id'))
        {
            return (int) $Data->playlist_id;
        }
        else
        {
            throw new Exception("Playlist already exists.");
        }
    }

//
    public function DeletePlaylist(integer $PlayListId) // ToDo antwort zerlegen
    {
        if (!is_int($PlayListId))
            throw new Exception("PlayListId must be integer.");

        $raw = $this->SendLMSData(new LMSData(array('playlists', 'delete'), 'playlist_id:' . $PlayListId));
        $Data = new LMSTaggingData($raw);
        if (property_exists($Data, 'playlist_id'))
        {
            return ($PlayListId == (int) $Data->playlist_id);
        }
        else
        {
            throw new Exception("Error deleting Playlist.");
        }
    }

//
    public function AddFileToPlaylist(integer $PlayListId, string $SongUrl, integer $Track = null)
    {
        $raw = $this->SendLMSData(new LMSData(array('playlists', 'edit'), array('cmd:add', 'playlist_id:' . $PlayListId, 'url:' . $SongUrl)));
        $Data = new LMSTaggingData($raw);
        if (property_exists($Data, 'url'))
        {
            if ($SongUrl <> (string) $Data->url)
                return false;
        }
        else
        {
            throw new Exception("Error add File to Playlist.");
        }
        if (!is_null($Track))
        {
//            $ret = $this->SendLMSData(new LMSData('playlists edit cmd%3Amove playlist_id%3A'.$PlayListId.' url%3A'.$SongUrl, LMSData::GetData));
// index  toindex
        }
//cmd%3Aadd playlist_id%3A52250 url%3Afile%3A%2F%2F%2FE%3A%2FServerFolders%2FMusik%2FAKB%2FAKB0048%20Complete%20Vocal%20Collection%2F1-03%20-%20AKB%20Sanjou!.mp3"        
        return true;
    }

//
    public function DeleteFileFromPlaylist(integer $PlayListId, integer $Track)
    {
        $ret = $this->SendLMSData(new LMSData(array('playlists', 'edit'), array('cmd:delete', 'playlist_id:' . $PlayListId, 'index:' . $Track)));
        return $ret;
    }

    public function GetPlaylists()
    {
        return "123456678";
    }

################## Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident)
        {
            case "RescanState":
                if ($Value == 0)
                    $ret = $this->SendLMSData(new LMSData('abortscan', ''));
                elseif ($Value == 1)
                    $ret = $this->SendLMSData(new LMSData('rescan', ''));
                elseif ($Value == 2)
                    $ret = $this->SendLMSData(new LMSData('rescan playlists', ''));
                elseif ($Value == 3)
                {
                    $ret = $this->SendLMSData(new LMSData('wipecache', ''));
                }
                if (!$ret)
                    throw new Exception('Error on send Scan Command');
                $this->SetValueInteger('RescanState', $Value);
                break;
            default:

                break;
        }
    }

################## Privat

    private function RefreshPlaylists()
    {
        $ScriptID = $this->ReadPropertyInteger('Playlistconfig');
        if ($ScriptID == 0)
            return;
        IPS_RunScriptEx($ScriptID, array('SENDER' => 'LMS', 'TARGET' => $this->InstanceID));
    }

    public function DisplayPlaylist($Config)
    {
        if (($Config === false) or ( !is_array($Config)))
            throw new Exception('Error on read Playlistconfig-Script');

        try
        {
            $Data = $this->GetPlaylists();
        }
        catch (Exception $exc)
        {
            unset($exc);
            throw new Exception('Error on read Playlist');
        }
        /*    $HTMLData = $this->GetTableHeader($Config);
          $pos = 0;
          $CurrentTrack = GetValueInteger($this->GetIDForIdent('Index'));

          if (isset($Data))
          {
          foreach ($Data as $Position => $Line)
          {
          $Line['Position'] = $Position + 1;
          $Line['Duration'] = @date('i:s', $Line['Duration']);
          $Line['Play'] = $Line['Position'] == $CurrentTrack ? '<div class="ipsIconArrowRight" is="null"></div>' : '';

          $HTMLData .='<tr style="' . $Config['Style']['BR' . ($Line['Position'] == $CurrentTrack ? 'A' : ($pos % 2 ? 'U' : 'G'))] . '"
          onclick="window.xhrGet=function xhrGet(o) {var HTTP = new XMLHttpRequest();HTTP.open(\'GET\',o.url,true);HTTP.send();};window.xhrGet({ url: \'hook/SqueezeBoxPlaylist' . $this->InstanceID . '?Index=' . $Line['Position'] . '\' })">';
          foreach ($Config['Spalten'] as $feldIndex => $value)
          {
          $HTMLData .= '<td style="' . $Config['Style']['DF' . ($Line['Position'] == $CurrentTrack ? 'A' : ($pos % 2 ? 'U' : 'G')) . $feldIndex] . '">' . (string) $Line[$feldIndex] . '</td>';
          }
          $HTMLData .= '</tr>' . PHP_EOL;
          $pos++;
          }
          }
          $HTMLData .= $this->GetTableFooter();
          $this->SetValueString('Playlist', $HTMLData); */
        $this->SetValueString('Playlists', serialize($Data));
    }

    private function CreatePlaylistConfigScript()
    {
        $Script = '<?
### Konfig ab Zeile 10 !!!

if ($_IPS["SENDER"] <> "LMS")
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
    "BRG"  => "background-color:#000000; color:ffff00;",
    "BRU"  => "background-color:#080808; color:ffff00;",
    "BRA"  => "background-color:#808000; color:ffff00;",
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

################## Decode Data

    private function DecodeLMSEvent(LMSResponse $LMSData)
    {
        switch ($LMSData->Data[0])
        {
            case "scanner":
                switch ($LMSData->Data[1])
                {
                    case "notify":
                        $Data = new LMSTaggingData($LMSData->Data[2]);
                        IPS_LogMessage("scanner progress", print_r($Data, 1));
                        IPS_LogMessage("scanner progress2", print_r($Data->{0}, 1));
                        switch (array_keys(get_object_vars($Data))[0])
                        {
                            case "end":
                            case "exit":
                                $this->SetValueString("RescanInfo", "");
                                $this->SetValueString("RescanProgress", "");
                                return true;
                                break;
                            case "progress":
                                $Info = explode("||", $Data->progress);
                                $StepInfo = $Info[2];
                                if (strpos($StepInfo, "|"))
                                {
                                    $StepInfo = explode("|", $StepInfo)[1];
                                }
                                $this->SetValueString("RescanInfo", $StepInfo);
                                $StepProgress = $Info[3] . " von " . $Info[4];
                                $this->SetValueString("RescanProgress", $StepProgress);
                                return true;
                                break;
                        }
                        break;
                }
                break;
            case "rescan":
                $this->SetValueInteger("RescanState", 3);
                break;
            case "rescan":
                if (!isset($LMSData->Data[1]))
                {
                    $this->SetValueInteger("RescanState", 1);
                    return true;

                    //start   
                }
                else
                {
                    if ($LMSData->Data[1] == 'done')
                    {
                        $this->SetValueInteger("RescanState", 0);
                        return true;
                        //done
                    }
                    else
                    {
                        //start   
                        $this->SetValueInteger("RescanState", 2);
                        return true;
                    }
                }
                break;
        }
        return false;
    }

################## DataPoints
//Ankommend von Child-Device

    public function ForwardData($JSONString)
    {
        $Data = json_decode($JSONString);

// Daten annehmen und Command zusammenfügen wenn Array
        if (is_array($Data->LSQ->Command))
//            $Data->LSQ->Command = implode(' ', $Data->LSQ->Command);
            $Data->LSQ->Command[0] = $Data->LSQ->Address . ' ' . $Data->LSQ->Command[0];
        else
            $Data->LSQ->Command = $Data->LSQ->Address . ' ' . $Data->LSQ->Command;
// LMS-Objekt erzeugen und Daten mit Adresse ergänzen.
//        $LMSData = new LMSData($Data->LSQ->Address . ' ' . $Data->LSQ->Command, $Data->LSQ->Value, false);
        $LMSData = new LMSData($Data->LSQ->Command, $Data->LSQ->Value, false);
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
                    if (!$this->DecodeLMSEvent($Data))
                        IPS_LogMessage('LMSEvent', print_r($Data, 1));
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
    protected function SendDataToParent(LMSData $LMSData)
    {
        if (is_array($LMSData->Command))
            $Commands = implode(' ', $LMSData->Command);
        else
            $Commands = $LMSData->Command;
        if (is_array($LMSData->Data))
            $Data = $Commands . ' ' . implode(' ', $LMSData->Data);
        else
            $Data = $Commands . ' ' . $LMSData->Data;
        $Data = trim($Data);
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

    private function SendLMSData(LMSData $LMSData)
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
            /*            if ($LMSData->Typ == LMSData::GetData)
              {
              $WaitData = substr($LMSData->Data, 0, -2);
              }
              else
              {
              $WaitData = $LMSData->Data;
              } */

// Anfrage für die Warteschleife schreiben
            if (!$this->SetWaitForResponse($LMSData->Command))
            {
// Konnte Daten nicht in den ResponseBuffer schreiben
// Lock der Sende-Routine aufheben.
                $this->unlock("LMSData");
                throw new Exception("Can not send to LMS");
            }

            try
            {
// Senden an Parent
                $this->SendDataToParent($LMSData);
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
            /*            if ($LMSData->Typ == LMSData::SendCommand)
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
              } */
            return $ret;
        }
// ohne Response, also ohne warten raussenden, 
        else
        {
            try
            {
                $this->SendDataToParent($LMSData);
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

    private function WriteResponse($Array)
    {
        if (is_array($Array))
            $Array = implode(' ', $Array);

        $Event = $this->GetIDForIdent('WaitForResponse');
        if (!GetValueBoolean($Event))
            return false;
        $BufferID = $this->GetIDForIdent('BufferOUT');
        $BufferOut = GetValueString($BufferID);
        $Data = utf8_decode($Array /* implode(" ", $Array) */);
        $DataPos = strpos($Data, $BufferOut);
        if (!($DataPos === false))
        {
            if ($this->lock('BufferOut'))
            {
//                $Event = $this->GetIDForIdent('WaitForResponse');
                SetValueString($BufferID, trim(substr($Data, $DataPos + strlen($BufferOut))));
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

}

?>