<?

require_once(__DIR__ . "/../SqueezeBoxClass.php");  // diverse Klassen

class LMSSplitter extends IPSModule
{

    public function Create()
    {
        //Never delete this line!
        parent::Create();
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
        // Eigene Profile
        $this->RegisterProfileIntegerEx("Scanner.SqueezeboxServer", "Gear", "", "", Array(
            Array(0, "Standby", "", -1),
            Array(1, "Abbruch", "", -1),
            Array(2, "Scan", "", -1),
            Array(3, "Nur Playlists", "", -1),
            Array(4, "Vollständig", "", -1)
        ));
        $this->RegisterProfileInteger("PlayerSelect" . $this->InstanceID . ".SqueezeboxServer", "Speaker", "", "", 0, 0, 0);
        // Eigene Variablen
        $this->RegisterVariableInteger("RescanState", "Scanner", "Scanner.SqueezeboxServer", 1);
        $this->RegisterVariableString("RescanInfo", "Rescan Status", "", 2);
        $this->RegisterVariableString("RescanProgress", "Rescan Fortschritt", "", 3);
        $this->EnableAction("RescanState");
        $this->RegisterVariableInteger("PlayerSelect", "Player wählen", "PlayerSelect" . $this->InstanceID . ".SqueezeboxServer", 4);
        $this->EnableAction("PlayerSelect");
        $this->RegisterVariableString("Playlists", "Playlisten", "~HTMLBox", 5);

        // Eigene Scripte
        $ID = $this->RegisterScript("WebHookPlaylist", "WebHookPlaylist", $this->CreateWebHookScript(), -8);
        IPS_SetHidden($ID, true);
        $this->RegisterHook('/hook/LMSPlaylist' . $this->InstanceID, $ID);

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
        if (($this->ReadPropertyBoolean('Open'))
                and ( $this->HasActiveParent($ParentID)))
        {
            $Data = new LMSData("listen", "1");
            $this->SendLMSData($Data);
            $Data = new LMSData("rescan", "?", false);
            $this->SendLMSData($Data);
            $this->RefreshPlaylists();
            $this->RefreshPlayerList();
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
        $players = $this->GetNumberOfPlayers();
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
        $DevicesIDs = IPS_GetInstanceListByModuleID("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
        $FoundId = 0;
        foreach ($DevicesIDs as $Device)
        {
            if (IPS_GetProperty($Device, 'Address') == $LSQEvent['Playerid']) $FoundId = $Device;
        }
        $LSQEvent['Instanceid'] = $FoundId;       
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
        $ret = $this->SendLMSData(new LMSData('playlists', array(0, 10000, 'tags:u')));
        $SongInfo = new LSMSongInfo($ret);
        $Playlists = $SongInfo->GetAllSongs();
        foreach ($Playlists as $Key => $Playlist)
        {
            $raw = LMS_SendRaw(15277 /* [Logitech Media Server] */, array('playlists', 'tracks'), array(0, 10000, 'playlist_id:' . $Playlist['Id'], 'tags:d'), true);
            $SongInfo = new LSMSongInfo($raw);
            $Playlists[$Key]['Tracks'] = $SongInfo->CountAllSongs();
            $Playlists[$Key]['Duration'] = $SongInfo->GetTotalDuration();
        }
        return $Playlists;
    }

################## Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident)
        {
            case "PlayerSelect":
                $this->SetValueInteger('PlayerSelect', $Value);

                break;
            case "RescanState":
                if ($Value == 1)
                    $ret = $this->SendLMSData(new LMSData('abortscan', ''));
                elseif ($Value == 2)
                    $ret = $this->SendLMSData(new LMSData('rescan', ''));
                elseif ($Value == 3)
                    $ret = $this->SendLMSData(new LMSData('rescan playlists', ''));
                elseif ($Value == 4)
                    $ret = $this->SendLMSData(new LMSData('wipecache', '', false));
                if (($Value <> 0) and ( !$ret))
                    throw new Exception('Error on send Scan Command');
                $this->SetValueInteger('RescanState', $Value);
                break;
            default:

                break;
        }
    }

################## Privat

    public function RefreshPlayerList()
    {
        $players = $this->GetNumberOfPlayers();
        $Assosiation = array();
        $Assosiation[] = array(-2, 'Keiner', "", 0x00ff00);
        $Assosiation[] = array(-1, 'Alle', "", 0xff0000);
        for ($i = 0; $i < $players; $i++)
        {
            $PlayerName = rawurldecode($this->SendLMSData(new LMSData(array('player', 'name', $i), '?')));
            $Assosiation[] = array($i, $PlayerName, "", -1);
        }
//        IPS_LogMessage('Data', print_r($Assosiation, 1));
        $this->RegisterProfileIntegerEx("PlayerSelect" . $this->InstanceID . ".SqueezeboxServer", "Speaker", "", "", $Assosiation);
        $this->SetValueInteger('PlayerSelect', -2);
    }

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
        $HTMLData = $this->GetTableHeader($Config);
        $pos = 0;
//          $CurrentTrack = GetValueInteger($this->GetIDForIdent('Index'));
        if (isset($Data))
        {
            foreach ($Data as $Position => $Line)
            {
                $Line['Position'] = $Position;
                if ($Line['Duration'] > 3600)
                    $Line['Duration'] = @date("H:i:s", $Line['Duration'] - 3600);
                else
                    $Line['Duration'] = @date("i:s", $Line['Duration']);

//          $Line['Play'] = $Line['Position'] == $CurrentTrack ? '<div class="ipsIconArrowRight" is="null"></div>' : '';

                $HTMLData .='<tr style="' . $Config['Style']['BR' . ($pos % 2 ? 'U' : 'G')] . '"
          onclick="window.xhrGet=function xhrGet(o) {var HTTP = new XMLHttpRequest();HTTP.open(\'GET\',o.url,true);HTTP.send();};window.xhrGet({ url: \'hook/LMSPlaylist' . $this->InstanceID . '?Playlistid=' . $Line['Id'] . '\' })">';
                foreach ($Config['Spalten'] as $feldIndex => $value)
                {
                    $HTMLData .= '<td style="' . $Config['Style']['DF' . ($Line['Position'] == $pos % 2 ? 'U' : 'G') . $feldIndex] . '">' . (string) $Line[$feldIndex] . '</td>';
                }
                $HTMLData .= '</tr>' . PHP_EOL;
                $pos++;
            }
        }
        $HTMLData .= $this->GetTableFooter();
        $this->SetValueString('Playlists', $HTMLData);
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
            $html .= '<th style="color:ffff00; ' . $Config['Style']['HF' . $Index] . '">' . $Value . '</th>';
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

    private function CreateWebHookScript()
    {
        $Script = '<?
            var_dump($_GET);
            $PlayerSelect = IPS_GetObjectIDByIdent("PlayerSelect",IPS_GetParent($_IPS["SELF"]));
            $PlayerID = GetValueInteger($PlayerSelect);
            if ($PlayerID == -1)
            {
            // Alle
            }
            elseif($PlayerID >= 0)
            {
                $Player = LMS_GetPlayerInfo(IPS_GetParent($_IPS["SELF"]),$PlayerID);
                if ($Player["Instanceid"] > 0)
                {
                    LSQ_LoadPlaylistByPlaylistID($Player["Instanceid"],(integer)$_GET["Playlistid"]);
                }
            }
            SetValueInteger($PlayerSelect,-2);
';
        return $Script;
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
"Id" =>"",
"Playlist"=>"Playlist-Name",
"Tracks"=>"Tracks",
"Duration"=>"Dauer"
);
#### Mögliche Index-Felder
/*
| Index            | Typ     | Beschreibung                        |
| :--------------: | :-----: | :---------------------------------: |
| Id               | integer | UID der Playlist in der LMS-Datenbank  |
| Playlist         | string  | Name der Playlist                   |
| Duration         | integer | Länge der Playlist in Klartext      |
| Url              | string  | Pfad der Playlist                   |
| Tracks           | integer | Anzahl der enthaltenen Tracks       |
*/
// Breite der Spalten (Reihenfolge ist egal)
$Config["Breite"] = array(
"Id" =>"100em",
    "Playlist" => "400em",
    "Tracks" => "50em",
    "Duration" => "75em"
);
// Style Informationen der Tabelle
$Config["Style"] = array(
    // <table>-Tag:
    "T"    => "margin:0 auto; font-size:0.8em;",
    // <thead>-Tag:
    "H"    => "",
    // <tr>-Tag im thead-Bereich:
    "HR"   => "",
    // <th>-Tag Feld Id:
    "HFId"  => "width:35px; align:left;",
    // <th>-Tag Feld Playlist:
    "HFPlaylist"  => "width:35px; align:left;",
    // <th>-Tag Feld Tracks:
    "HFTracks"  => "width:35px; align:left;",
    // <th>-Tag Feld Duration:
    "HFDuration"  => "width:35px; align:left;",
    // <tbody>-Tag:
    "B"    => "",
    // <tr>-Tag:
    "BRG"  => "background-color:#000000; color:ffff00;",
    "BRU"  => "background-color:#080808; color:ffff00;",
    // <td>-Tag Feld Id:
    "DFGId" => "text-align:center;",
    "DFUId" => "text-align:center;",
    // <td>-Tag Feld Playlist:
    "DFGPlaylist" => "text-align:left;",
    "DFUPlaylist" => "text-align:left;",
    // <td>-Tag Feld Tracks:
    "DFGTracks" => "text-align:right;",
    "DFUTracks" => "text-align:right;",
    // <td>-Tag Feld Duration:
    "DFGDuration" => "text-align:right;",
    "DFUDuration" => "text-align:right;",
    // ^- Der Buchstabe "G" steht für gerade, "U" für ungerade.
 );
### Konfig ENDE !!!
LMS_DisplayPlaylist($_IPS["TARGET"],$Config);
?>';
        return $Script;
    }

################## Decode Data

    private function DecodeLMSEvent(LMSResponse $LMSData)
    {
        switch ($LMSData->Data[0])
        {
            case "listen":
                return true;
                break;
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
            case"wipecache":
                $this->SetValueInteger("RescanState", 4); // Vollständig
                return true;

            case "rescan":
                if (!isset($LMSData->Data[1]))
                {
                    $this->SetValueInteger("RescanState", 2); // einfacher
                    return true;
                }
                else
                {
                    if (($LMSData->Data[1] == 'done') or ( $LMSData->Data[1] == '0'))
                    {
                        $this->SetValueInteger("RescanState", 0); // fertig
                        $this->RefreshPlaylists();
                        return true;
                    }
                    elseif ($LMSData->Data[1] == 'playlists')
                    {
                        $this->SetValueInteger("RescanState", 3); // Playlists
                        return true;
                    }
                    elseif ((int) $LMSData->Data[1] == 1)
                    {
                        //start   
                        $this->SetValueInteger("RescanState", 2); // einfacher
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
            $part = trim($part);
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
                if ($Data->Data[0] == LSQResponse::client) // Client änderungen auch hier verarbeiten!
                {
                    IPS_RunScriptText("<?\nLMS_RefreshPlayerList(" . $this->InstanceID . ");");
                }
            }
        }
// Ist ein Fehler aufgetreten ?
        if (isset($ret))
            throw $ret; // dann erst jetzt werfen
        return $ReceiveOK;
    }

// Sende-Routine an den Parent
    protected function SendDataToParent($LMSData)
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
// Auf Antwort warten....
            $ret = $this->WaitForResponse();
// Lock der Sende-Routine aufheben.
            $this->unlock("LMSData");



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

    protected function SetStatus($InstanceStatus)
    {
        if ($InstanceStatus <> IPS_GetInstance($this->InstanceID)['InstanceStatus'])
            parent::SetStatus($InstanceStatus);
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
        $old = IPS_GetVariableProfile($Name)["Associations"];
        $OldValues = array_column($old,'Value');
        foreach ($Associations as $Association)
        {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
            $OldKey = array_search($Association[0],$OldValues);
            if (!($OldKey === false ))
            {
                unset($OldValues[$OldKey]);
            }
        }
        foreach ($OldValues as $OldKey => $OldValue)
        {
            IPS_SetVariableProfileAssociation($Name, $OldValue, '', '', 0);
        }
    }

}

?>