<?

require_once(__DIR__ . "/../SqueezeBoxClass.php");  // diverse Klassen
/*
 * @addtogroup squeezebox
 * @{
 *
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 *
 */

/**
 * LMSSplitter Klasse für die Kommunikation mit dem Logitech Media-Server (LMS).
 * Erweitert IPSModule.
 * 
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0 
 * @example <b>Ohne</b>
 */
class LMSSplitter extends IPSModule
{

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
        $this->RegisterPropertyString("Host", "");
        $this->RegisterPropertyBoolean("Open", false);
        $this->RegisterPropertyInteger("Port", 9090);
        $this->RegisterPropertyInteger("Webport", 9000);
        $ID = @$this->GetIDForIdent('PlaylistDesign');
        if ($ID == false)
            $ID = $this->RegisterScript('PlaylistDesign', 'Playlist Config', $this->CreatePlaylistConfigScript(), -7);
        IPS_SetHidden($ID, true);
        $this->RegisterPropertyInteger("Playlistconfig", $ID);
        $this->RegisterTimer('KeepAlive', 0, 'LMS_KeepAlive($_IPS["TARGET"]);');
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
            case IPS_KERNELMESSAGE:
                if ($Data[0] == KR_READY)
                {
                    try
                    {
                        $this->KernelReady();
                    }
                    catch (Exception $exc)
                    {
                        return;
                    }
                }
                break;
            case DM_CONNECT:
            case DM_DISCONNECT:
                $this->ForceRefresh();
                break;
            case IM_CHANGESTATUS:
                if (($SenderID == @IPS_GetInstance($this->InstanceID)['ConnectionID']) and ( $Data[0] == IS_ACTIVE))
                    try
                    {
                        $this->ForceRefresh();
                    }
                    catch (Exception $exc)
                    {
                        return;
                    }
                break;
        }
    }

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Parent ändert.
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
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        $this->RegisterMessage($this->InstanceID, DM_CONNECT);
        $this->RegisterMessage($this->InstanceID, DM_DISCONNECT);
        // Wenn Kernel nicht bereit, dann warten... KR_READY kommt ja gleich

        if (IPS_GetKernelRunlevel() <> KR_READY)
            return;

        parent::ApplyChanges();
        // Kurzinfo setzen
        $this->SetSummary($this->ReadPropertyString('Host'));
        // Buffer leeren
        $this->SetBuffer('ReplyLMSData', serialize(array())); // 
        // Config prüfen
        $Open = $this->ReadPropertyBoolean('Open');
        $NewState = IS_ACTIVE;
        if (!$Open)
            $NewState = IS_INACTIVE;
        else
        {
            if ($this->ReadPropertyString('Host') == '')
            {
                $NewState = IS_EBASE + 2;
                $Open = false;
                trigger_error('Host is empty', E_USER_NOTICE);
            }
            if ($this->ReadPropertyInteger('Port') == 0)
            {
                $NewState = IS_EBASE + 3;
                $Open = false;
                trigger_error('Port is empty', E_USER_NOTICE);
            }
        }
        $ParentID = $this->GetParent();

        // Zwangskonfiguration des ClientSocket
        if ($ParentID > 0)
        {
            // Dup Applychange vermeiden
            $this->UnregisterMessage($ParentID, IM_CHANGESTATUS);

            if (IPS_GetProperty($ParentID, 'Host') <> $this->ReadPropertyString('Host'))
                IPS_SetProperty($ParentID, 'Host', $this->ReadPropertyString('Host'));
            if (IPS_GetProperty($ParentID, 'Port') <> $this->ReadPropertyInteger('Port'))
                IPS_SetProperty($ParentID, 'Port', $this->ReadPropertyInteger('Port'));

            // Keine Verbindung erzwingen wenn Host leer ist, sonst folgt später Exception.
            if ($Open)
            {
                $Open = @Sys_Ping($this->ReadPropertyString('Host'), 500);
                if (!$Open)
                    $NewState = IS_EBASE + 4;
            }
            $this->OpenIOParent($ParentID, $Open);
        }
        else
        {
            if ($Open)
            {
                $NewState = IS_INACTIVE;
                $Open = false;
            }
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

        // ServerPlaylisten
        $this->RegisterVariableInteger("PlayerSelect", "Player wählen", "PlayerSelect" . $this->InstanceID . ".SqueezeboxServer", 4);
        $this->EnableAction("PlayerSelect");
        $this->RegisterVariableString("Playlists", "Playlisten", "~HTMLBox", 5);
        $sid = $this->RegisterScript("WebHookPlaylist", "WebHookPlaylist", $this->CreateWebHookScript(), -8);
        IPS_SetHidden($sid, true);
        if (IPS_GetKernelRunlevel() == KR_READY)
            $this->RegisterHook('/hook/LMSPlaylist' . $this->InstanceID, $sid);
        $ID = @$this->GetIDForIdent('PlaylistDesign');
        if ($ID == false)
            $ID = $this->RegisterScript('PlaylistDesign', 'Playlist Config', $this->CreatePlaylistConfigScript(), -7);
        IPS_SetHidden($ID, true);

        //remove Workaround für persistente Daten der Instanz
        $this->UnregisterVariable("BufferIN");
        $this->UnregisterVariable("BufferOUT");
        $this->UnregisterVariable("WaitForResponse");

        // Wenn wir verbunden sind, am LMS mit listen anmelden für Events
        if ($Open)
        {
            if ($this->HasActiveParent($ParentID))
            {
                try
                {
                    $Data = new LMSData("listen", "1");
                    $this->SendLMSData($Data);
                    $this->RefreshPlayerList();
                    $Data = new LMSData("rescan", "?", false);
                    $this->SendLMSData($Data);
                }
                catch (Exception $exc)
                {
                    $NewState = IS_EBASE + 4;
                    trigger_error($exc->getMessage(), $exc->getCode());
                }
            }
            else
            {
                $NewState = IS_EBASE + 4;
                trigger_error('Could not connect to LMS.', E_USER_NOTICE);
            }
        }

        $this->GetParentData();

        $this->SetStatus($NewState);

        if ($NewState == IS_ACTIVE)
        {
            $DevicesIDs = IPS_GetInstanceListByModuleID("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
            foreach ($DevicesIDs as $Device)
            {
                if (IPS_GetInstance($Device)['ConnectionID'] == $this->InstanceID)
                {
                    @IPS_ApplyChanges($Device);
                }
            }
        }
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    /**
     * IPS-Instanz-Funktion 'LMS_KeepAlive'.
     * Sendet einen listen und rescan Abfrage an den LMS um die Kommunikation zu erhalten.
     * 
     * @access public
     * @result bool true wenn LMS erreichbar, sonst false.
     */
    public function KeepAlive()
    {
        try
        {
            $Data = new LMSData("listen", "1");
            $this->SendLMSData($Data);
            $this->RefreshPlayerList();
            $Data = new LMSData("rescan", "?", false);
            $this->SendLMSData($Data);
            return true;
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }
    }

    public function SendRaw($Command, $Value, $needResponse)
    {
        $LMSData = new LMSData($Command, $Value, $needResponse);
        $ret = $this->SendLMSData($LMSData);
        return $ret;
    }

    public function Rescan()
    {
        return $this->SendLMSData(new LMSData('rescan'));
    }

    public function GetRescanProgress()
    {
        $ret = $this->SendLMSData(new LMSData('rescanprogress'));
        if ($ret === false)
            return false;
        $LSQEvent = new LSQTaggingData($ret, true);
        return (bool) $LSQEvent->Value;
    }

    public function GetNumberOfPlayers()
    {
        $players = $this->SendLMSData(new LMSData(array('player', 'count'), '?'));
        if ($players === false)
            return false;
        return (int) $players;
    }

    public function CreateAllPlayer()
    {
        $players = $this->GetNumberOfPlayers();
        if ($players === false)
            return false;
        $DevicesIDs = IPS_GetInstanceListByModuleID("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
        $CreatedPlayers = array();
        foreach ($DevicesIDs as $Device)
        {
            $KnownDevices[] = IPS_GetProperty($Device, 'Address');
        }
        for ($i = 0; $i < $players; $i++)
        {
            $player = $this->SendLMSData(new LMSData(array('player', 'id', $i), '?'));
            if ($player === false)
                continue;
            $playermac = rawurldecode($player);

            if (in_array($playermac, $KnownDevices))
                continue;
            $NewDevice = IPS_CreateInstance("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
            $playerName = $this->SendLMSData(new LMSData(array('player', 'name', $i), '?'));
            IPS_SetName($NewDevice, $playerName);
            if (IPS_GetInstance($NewDevice)['ConnectionID'] <> $this->InstanceID)
            {
                @IPS_DisconnectInstance($NewDevice);
                IPS_ConnectInstance($NewDevice, $this->InstanceID);
            }
            IPS_SetProperty($NewDevice, 'Address', $playermac);
            IPS_ApplyChanges($NewDevice);
            $CreatedPlayers[] = $NewDevice;
        }
        return $CreatedPlayers;
    }

    public function GetPlayerInfo(int $Index)
    {
        if (!is_int($Index))
        {
            trigger_error("Index must be integer.", E_USER_NOTICE);
            return false;
        }
        $ret = $this->SendLMSData(new LMSData(array('players', (string) $Index, '1')));
        if ($ret === false)
        {
            trigger_error("Error read player info.", E_USER_NOTICE);
            return false;
        }
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
        if (!isset($LSQEvent['Playerid']))
        {
            trigger_error('Invalid Index', E_USER_NOTICE);
            return false;
        }
        $DevicesIDs = IPS_GetInstanceListByModuleID("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
        $FoundId = 0;
        foreach ($DevicesIDs as $Device)
        {
            if (IPS_GetProperty($Device, 'Address') == $LSQEvent['Playerid'])
                $FoundId = $Device;
        }
        $LSQEvent['Instanceid'] = $FoundId;
        return $LSQEvent;
    }

    public function GetLibaryInfo()
    {
        $genres = $this->SendLMSData(new LMSData(array('info', 'total', 'genres'), '?'));
        if ($genres === false)
            return false;
        $artists = $this->SendLMSData(new LMSData(array('info', 'total', 'artists'), '?'));
        if ($artists === false)
            return false;
        $albums = $this->SendLMSData(new LMSData(array('info', 'total', 'albums'), '?'));
        if ($albums === false)
            return false;
        $songs = $this->SendLMSData(new LMSData(array('info', 'total', 'songs'), '?'));
        if ($songs === false)
            return false;
        $ret = array('Genres' => intval($genres), 'Artists' => intval($artists), 'Albums' => intval($albums), 'Songs' => intval($songs));
        return $ret;
    }

    public function GetVersion()
    {
        $ret = $this->SendLMSData(new LMSData('version', '?'));
        return $ret;
    }

    public function GetSongInfoByFileID(int $FileID)
    {
        if (!is_int($FileID))
        {
            trigger_error("FileID must be integer.", E_USER_NOTICE);
            return false;
        }
        $Data = $this->SendLMSData(new LMSData(array('songinfo', '0', '20'), array('track_id:' . $FileID, 'tags:gladiqrRtueJINpsy')));
        if ($Data === false)
            return FALSE;
        $SongInfo = new LSMSongInfo($Data);
        $Song = $SongInfo->GetSong();
        if (is_null($Song))
        {
            trigger_error("FileID not valid.", E_USER_NOTICE);
            return false;
        }
        return $Song;
    }

    public function GetSongInfoByFileURL(string $FileURL)
    {
        if (!is_string($FileURL))
        {
            trigger_error("FileURL must be string.", E_USER_NOTICE);
            return false;
        }
        $Data = $this->SendLMSData(new LMSData(array('songinfo', '0', '20'), array('url:' . rawurlencode($FileURL), 'tags:gladiqrRtueJINpsy')));
        if ($Data === false)
            return FALSE;
        $SongInfo = new LSMSongInfo($Data);
        $Song = $SongInfo->GetSong();
        if (count($Song) == 1)
        {
            trigger_error("FileURL not valid.", E_USER_NOTICE);
            return false;
        }
        return $Song;
    }

    public function GetSyncGroups()
    {
        $ret = $this->SendLMSData(new LMSData('syncgroups', '?'));
        if (is_bool($ret))
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
        if (!is_string($Name))
        {
            trigger_error("Name must be string.", E_USER_NOTICE);
            return false;
        }
        $raw = $this->SendLMSData(new LMSData(array('playlists', 'new'), 'name:' . rawurlencode($Name)));
        if ($raw === false)
            return false;
        $Data = new LMSTaggingData($raw);
        if (property_exists($Data, 'playlist_id'))
        {
            return (int) $Data->playlist_id;
        }
        else
        {
            trigger_error("Playlist already exists.", E_USER_NOTICE);
            return false;
        }
    }

//
    public function DeletePlaylist(int $PlayListId) // ToDo antwort zerlegen
    {
        if (!is_int($PlayListId))
        {
            trigger_error("PlayListId must be integer.", E_USER_NOTICE);
            return false;
        }
        $raw = $this->SendLMSData(new LMSData(array('playlists', 'delete'), 'playlist_id:' . $PlayListId));
        if ($raw === false)
            return false;
        $Data = new LMSTaggingData($raw);
        if (property_exists($Data, 'playlist_id'))
        {
            return ($PlayListId == (int) $Data->playlist_id);
        }
        else
        {
            trigger_error("Error deleting Playlist.", E_USER_NOTICE);
            return false;
        }
    }

//
    public function AddFileToPlaylist(int $PlayListId, string $SongUrl, int $Track = null)
    {
        $raw = $this->SendLMSData(new LMSData(array('playlists', 'edit'), array('cmd:add', 'playlist_id:' . $PlayListId, 'url:' . rawurlencode($SongUrl))));
        $Data = new LMSTaggingData($raw);
        if (property_exists($Data, 'url'))
        {
            if ($SongUrl <> (string) $Data->url)
                return false;
        }
        else
        {
            trigger_error("Error add File to Playlist.", E_USER_NOTICE);
            return false;
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
    public function DeleteFileFromPlaylist(int $PlayListId, int $Track)
    {
        $ret = $this->SendLMSData(new LMSData(array('playlists', 'edit'), array('cmd:delete', 'playlist_id:' . $PlayListId, 'index:' . $Track)));
        return $ret;
    }

    public function GetPlaylists()
    {
        $ret = $this->SendLMSData(new LMSData('playlists', array(0, 10000, 'tags:u')));
        if ($ret === false)
            return false;
        $SongInfo = new LSMSongInfo($ret);
        $Playlists = $SongInfo->GetAllSongs();
        foreach ($Playlists as $Key => $Playlist)
        {
            $raw = @$this->SendLMSData(new LMSData(array('playlists', 'tracks'), array(0, 10000, 'playlist_id:' . $Playlist['Id'], 'tags:d'), true));
            if ($raw === false)
            {
                trigger_error("Error read Playlist " . $Playlist['Id'] . ".", E_USER_NOTICE);
                $Playlists[$Key]['Playlist'] = $Playlists[$Key]['Playlist'] . " (ERROR ON READ DATA)";
                $Playlists[$Key]['Tracks'] = "";
                $Playlists[$Key]['Duration'] = "";
                continue;
            }
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
                {
                    echo 'Error on send Scan Command';
                    return false;
                }
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
            trigger_error('Error on read Playlistconfig-Script', E_USER_NOTICE);

        $Data = $this->GetPlaylists();
        if ($Data === false)
            return false;
        $HTMLData = $this->GetTableHeader($Config);
        $pos = 0;
//          $CurrentTrack = GetValueInteger($this->GetIDForIdent('Index'));
        if (isset($Data))
        {
            foreach ($Data as $Position => $Line)
            {
                $Line['Position'] = $Position;
//                if (array_key_exists('Duration', $Line))
//                {
                if ($Line['Duration'] > 3600)
                    $Line['Duration'] = @date("H:i:s", $Line['Duration'] - 3600);
                else
                    $Line['Duration'] = @date("i:s", $Line['Duration']);
                /*                } else
                  {
                  $Line['Duration'] = '';
                  }
                  if (!array_key_exists('Tracks', $Line))
                  $Line['Tracks'] = '';
                 */
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
        // Kopf der Tabelle erzeugen
        $html = '<table style="' . $Config['Style']['T'] . '">' . PHP_EOL;
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
            $html .= '<th style="' . $Config['Style']['HF' . $Index] . '">' . $Value . '</th>';
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
    // <table>-Tag:;
    "T"    => "margin:0 auto; font-size:0.8em;",
    // <thead>-Tag:
    "H"    => "",
    // <tr>-Tag im thead-Bereich:
    "HR"   => "",
    // <th>-Tag Feld Id:
    "HFId"  => "color:#ffffff; width:35px; align:left;",
    // <th>-Tag Feld Playlist:
    "HFPlaylist"  => "color:#ffffff; width:35px; align:left;",
    // <th>-Tag Feld Tracks:
    "HFTracks"  => "color:#ffffff; width:35px; align:left;",
    // <th>-Tag Feld Duration:
    "HFDuration"  => "color:#ffffff; width:35px; align:left;",
    // <tbody>-Tag:
    "B"    => "",
    // <tr>-Tag:
    "BRG"  => "background-color:#000000; color:#ffffff;",
    "BRU"  => "background-color:#080808; color:#ffffff;",
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
//                        IPS_LogMessage("scanner progress", print_r($Data, 1));
//                        IPS_LogMessage("scanner progress2", print_r($Data->{0}, 1));
                        switch (array_keys(get_object_vars($Data))[0])
                        {
                            case "end":
                            case "exit":
                                $this->SetValueString("RescanInfo", "");
                                $this->SetValueString("RescanProgress", "");
                                return true;
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
                        if ($this->SetValueInteger("RescanState", 0))   // fertig
                            $this->RefreshPlaylists();
                        return true;
                    }
                    elseif ($LMSData->Data[1] == 'playlists')
                    {
                        $this->SetValueInteger("RescanState", 3); // Playlists
                        return true;
                    }
                    elseif ($LMSData->Data[1] == '1')
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

################## DATAPOINTS DEVICE

    /**
     * Interne Funktion des SDK. Nimmt Daten von Childs entgegen und sendet Diese weiter.
     * 
     * @access public
     * @param string $JSONString Ein LSQData-Objekt welches als JSONString kodiert ist.
     * @result bool true wenn Daten gesendet werden konnten, sonst false.
     */
    public function ForwardData($JSONString)
    {
        $Data = json_decode($JSONString);

// Daten annehmen und Command zusammenfügen wenn Array
//        if (is_array($Data->LSQ->Command))
//            $Data->LSQ->Command[0] = $Data->LSQ->Address . ' ' . $Data->LSQ->Command[0];
//        else
//            $Data->LSQ->Command = $Data->LSQ->Address . ' ' . $Data->LSQ->Command;
// LMS-Objekt erzeugen und Daten mit Adresse ergänzen.
//        $LMSData = new LMSData($Data->LSQ->Command, $Data->LSQ->Value, false);
// Senden über die Warteschlange
        $LMSData = new LMSData();        
        $LMSData->CreateFromGenericObject($Data);
        $ret = $this->Send($LMSData);
        if (!is_null($ret))
            return serialize($ret);
        
        return false;
    }

    /**
     * Sendet LSQData an die Childs.
     * 
     * @access private
     * @param LMSResponse $LMSData Ein LMSResponse-Objekt.
     */
    private function SendDataToDevice(LMSResponse $LMSData)
    {
        $Data = $LMSData->ToJSONStringForDevice("{CB5950B3-593C-4126-9F0F-8655A3944419}");
        $this->SendDebug('IPS_SendDataToChildren', $Data, 0);
        $this->SendDataToChildren($Data);
    }

################## DATAPOINTS PARENT    

    /**
     * Empfängt Daten vom Parent.
     * 
     * @access public
     * @param string $JSONString Das empfangene JSON-kodierte Objekt vom Parent.
     * @result bool True wenn Daten verarbeitet wurden, sonst false.
     */
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);

        // Datenstream zusammenfügen
        $head = $this->GetBuffer('BufferIN');
//        $this->SetBuffer('BufferIN', '');
        $Data = $head . utf8_decode($data->Buffer);

        // Stream in einzelne Pakete schneiden
        $packet = explode(chr(0x0d), $Data);

        // Rest vom Stream wieder in den Empfangsbuffer schieben
        $tail = trim(array_pop($packet));
        if ($tail == "")
            $this->SetBuffer('BufferIN', '');
        else
            $this->SetBuffer('BufferIN', $tail);

        // Pakete verarbeiten
        foreach ($packet as $part)
        {
            $part = trim($part);
            $Data = new LMSResponse($part);
            try
            {
                $isResponse = $this->SendQueueUpdate($Data);
            }
            catch (Exception $exc)
            {
                $buffer = $this->GetBuffer('BufferIN');
                $this->SetBuffer('BufferIN', $part . chr(0x0d) . $buffer);
                trigger_error($exc->getMessage(), E_USER_NOTICE);
                continue;
            }

            if ($isResponse === true) //War eine Antwort auf eine Anfrage... nix machen
            {
                // TODO LMS-Statusvariablen nachführen....????
                $this->SendDebug('LMS_Response', $Data, 0);
                if ($Data->Data[0] == LSQResponse::client) // Client änderungen auch hier verarbeiten!
                {
                    IPS_RunScriptText("<?\nLMS_RefreshPlayerList(" . $this->InstanceID . ");");
                }
            }
            else //War keine Antwort also ein Event
            {
                if ($Data->Device != LMSResponse::isServer)
                {
                    $this->SendDebug('LMS_Event', $Data, 0);
                    $this->SendDataToDevice($Data);
                    if ($Data->Data[0] == LSQResponse::client) // Client änderungen auch hier verarbeiten!
                    {
                        IPS_RunScriptText("<?\nLMS_RefreshPlayerList(" . $this->InstanceID . ");");
                    }
                }
                else
                    $this->DecodeLMSEvent($Data);
            }
        }
    }

    /**
     * Versendet ein LMSData-Objekt und empfängt die Antwort.
     * 
     * @access protected
     * @param LMSData $LMSData Das Objekt welches versendet werden soll.
     * @result mixed Enthält die Antwort auf das Versendete Objekt oder NULL im Fehlerfall.
     */
    protected function Send(LMSData $LMSData)
    {
        try
        {
            if ($this->ReadPropertyBoolean('Open') === false)
                throw new Exception('Instance inactiv.', E_USER_NOTICE);

            if (!$this->HasActiveParent())
                throw new Exception('Intance has no active parent.', E_USER_NOTICE);

            if ($LMSData->needResponse)
            {

                $this->SendDebug('Send', $LMSData, 0);
                $this->SendQueuePush($LMSData);
                $this->SendDataToParent($LMSData);
                $ReplyDataArray = $this->WaitForResponse($LMSData);

                if ($ReplyDataArray === false)
                {
                    $this->SetStatus(IS_EBASE + 3);
                    throw new Exception('No anwser from LMS', E_USER_NOTICE);
                }
                $this->SendDebug('Receive', $ReplyDataArray, 0);
                return $ReplyDataArray;

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
                  $ret = str_replace($WaitData, "", $ret);l
                  return $ret;
                  } */
            }
            else // ohne Response, also ohne warten raussenden, 
            {
                $this->SendDebug('SendFaF', $LMSData, 0);
                $this->SendDataToParent($LMSData);
            }
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }
    }

    /**
     * Sendet ein LMSData-Objekt an den Parent.
     * 
     * @access protected
     * @param LMSData $LMSData Das Objekt welches versendet werden soll.
     * @result bool true
     */
    protected function SendDataToParent($LMSData)
    {
        $JsonString = $LMSData->ToJSONStringForLMS('{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}');
        parent::SendDataToParent($JsonString);
        return true;
    }

    /**
     * Wartet auf eine Antwort einer Anfrage an den LMS.
     * 
     * @access private
     * @param LMSData $LMSData Das Objekt welches an den LMS versendet wurde.
     * @result array|boolean Enthält ein Array mit den Daten der Antwort. False bei einem Timeout
     */
    private function WaitForResponse(LMSData $LMSData)
    {
        for ($i = 0; $i < 1000; $i++)
        {
            if ($this->GetBuffer('ReplyLMSData') === 'a:0:{}') // wenn wenig los, gleich warten            
                IPS_Sleep(5);
            else
            {
                $key = $this->SendQueueSearch($LMSData->Address, $LMSData->Command);
                if ($key !== false)
                    return $this->SendQueuePop($key);
                IPS_Sleep(5);
            }
        }
        return false;
    }

################## SENDQUEUE

    /**
     * Fügt eine Anfrage in die SendQueue ein.
     * 
     * @access private
     * @param LMSData $LMSData Das versendete LMSData Objekt.
     */
    private function SendQueuePush(LMSData $LMSData)
    {
        if (!$this->lock('ReplyLMSData'))
            throw new Exception('ReplyLMSData is locked', E_USER_NOTICE);
        $data = unserialize($this->GetBuffer('ReplyLMSData'));
        $data[] = $LMSData->GetSearchPatter();
        $this->SetBuffer('ReplyLMSData', serialize($data));
        $this->unlock('ReplyLMSData');
    }

    /**
     * Durchsucht die SendQueue nach einem Datensatz
     * 
     * @access private
     * @param LMSData $LMSData Das zu suchene LMSData Objekt.
     */
    private function SendQueueSearch(string $Address, string $Command)
    {
        $SearchPatter = array("Address" => $Address, "Command" => $Command);
        $Buffer = unserialize($this->GetBuffer('ReplyLMSData'));
        $key = array_search($SearchPatter, array_merge_recursive(array_column($Buffer, 'Address'), array_column($Buffer, 'Command')));
        return $key;
    }

    /**
     * Fügt eine Antwort in die SendQueue ein.
     * 
     * @access private
     * @param LMSResponse $LMSResponse Das empfangene LMSData Objekt.
     * @return bool True wenn Anfrage zur Antwort gefunden wurde, sonst false.
     */
    private function SendQueueUpdate(LMSResponse $LMSResponse)
    {
        if (!$this->lock('ReplyLMSData'))
            throw new Exception('ReplyLMSData is locked', E_USER_NOTICE);
        $key = $this->SendQueueSearch($LMSResponse->Address, $LMSResponse->Command);
        if ($key === false)
        {
            $this->unlock('ReplyLMSData');
            return false;
        }
        $data = unserialize($this->GetBuffer('ReplyLMSData'));
        $data[$key]['Data'] = $LMSResponse->Data;
        $this->SetBuffer('ReplyLMSData', serialize($data));
        $this->unlock('ReplyLMSData');
        return true;
    }

    /**
     * Holt eine Antwort aus der SendQueue.
     * 
     * @access private
     * @param int $Index Der Index des zu holenden Elementes.
     * @return Array Die dazugehörige Antwort des LMS.
     */
    private function SendQueuePop(int $Index)
    {
        $data = unserialize($this->GetBuffer('ReplyLMSData'));
        $Result = $data[$Index]['Data'];
        $this->SendQueueRemove($Index);
        return $Result;
    }

    /**
     * Löscht einen Eintrag aus der SendQueue.
     * 
     * @access private
     * @param int $Index Der Index des zu löschenden Eintrags.
     */
    private function SendQueueRemove(int $Index)
    {
        if (!$this->lock('ReplyLMSData'))
            throw new Exception('ReplyLMSData is locked', E_USER_NOTICE);
        $data = unserialize($this->GetBuffer('ReplyLMSData'));
        unset($data[$Index]);
        $this->SetBuffer('ReplyLMSData', serialize($data));
        $this->unlock('ReplyLMSData');
    }

//    private function SetWaitForResponse($Data)
//    {
//        if (is_array($Data))
//            $Data = implode(' ', $Data);
//        if ($this->lock('BufferOut'))
//        {
//            $buffer = $this->GetIDForIdent('BufferOUT');
//            $WaitForResponse = $this->GetIDForIdent('WaitForResponse');
//            SetValueString($buffer, $Data);
//            SetValueBoolean($WaitForResponse, true);
//            $this->unlock('BufferOut');
//            return true;
//        }
//        return false;
//    }
//
//    private function ResetWaitForResponse()
//    {
//        if ($this->lock('BufferOut'))
//        {
//            $buffer = $this->GetIDForIdent('BufferOUT');
//            $WaitForResponse = $this->GetIDForIdent('WaitForResponse');
//            SetValueString($buffer, '');
//            SetValueBoolean($WaitForResponse, false);
//            $this->unlock('BufferOut');
//            return true;
//        }
//        return false;
//    }
//
//    private function WriteResponse($Array)
//    {
//        if (is_array($Array))
//            $Array = implode(' ', $Array);
//
//        $Event = $this->GetIDForIdent('WaitForResponse');
//        if (!GetValueBoolean($Event))
//            return false;
//        $BufferID = $this->GetIDForIdent('BufferOUT');
//        $BufferOut = GetValueString($BufferID);
//        $Data = utf8_decode($Array /* implode(" ", $Array) */);
//        $DataPos = strpos($Data, $BufferOut);
//        if (!($DataPos === false))
//        {
//            if ($this->lock('BufferOut'))
//            {
////                $Event = $this->GetIDForIdent('WaitForResponse');
//                SetValueString($BufferID, trim(substr($Data, $DataPos + strlen($BufferOut))));
//                SetValueBoolean($Event, false);
//                $this->unlock('BufferOut');
//                return true;
//            }
//            return 'Error on write ResponseBuffer';
//        }
//        return false;
//    }
################## SEMAPHOREN Helper  - private  

    private function lock($ident)
    {
        for ($i = 0; $i < 100; $i++)
        {
            if (IPS_SemaphoreEnter("LMS_" . (string) $this->InstanceID . (string) $ident, 1))
                return true;
            else
                IPS_Sleep(mt_rand(1, 5));
        }
        return false;
    }

    private function unlock($ident)
    {
        IPS_SemaphoreLeave("LMS_" . (string) $this->InstanceID . (string) $ident);
    }

    /**
     * Ermittelt den Parent und verwaltet die Einträge des Parent im MessageSink
     * Ermöglicht es das Statusänderungen des Parent empfangen werden können.
     * 
     * @access private
     */
    private function GetParentData()
    {
        $OldParentId = $this->GetBuffer('Parent');
        $ParentId = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($OldParentId > 0)
            $this->UnregisterMessage($OldParentId, IM_CHANGESTATUS);
        if ($ParentId > 0)
        {
            $this->RegisterMessage($ParentId, IM_CHANGESTATUS);
            $this->SetBuffer('Parent', $ParentId);
        }
        else
            $this->SetBuffer('Parent', 0);
    }

    /**
     * Öffnet oder schließt den übergeordneten IO-Parent
     * @param int $ParentID
     * @param bool $Open True für öffnen, false für schließen.
     */
    private function OpenIOParent(int $ParentID, bool $Open)
    {
        if ($ParentID == 0)
            return;
        IPS_SetProperty($ParentID, 'Open', $Open);
        if (IPS_HasChanges($ParentID))
            @IPS_ApplyChanges($ParentID);
    }

 
################## DUMMYS / WOARKAROUNDS - protected
    /**
     * Formatiert eine DebugAusgabe und gibt sie an IPS weiter.
     *
     * @access protected
     * @param string $Message Nachrichten-Feld.
     * @param string|array|Kodi_RPC_Data $Data Daten-Feld.
     * @param int $Format Ausgabe in Klartext(0) oder Hex(1)
     */

    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_a($Data, 'LMSData'))
        {
            $this->SendDebug($Message . " LMSSendData:Address", $Data->Address(), 0);
            $this->SendDebug($Message . " LMSSendData:Command", $Data->Command(), 0);
            $this->SendDebug($Message . " LMSSendData:Data", $Data->Data(), 0);
            $this->SendDebug($Message . " LMSSendData:needResponse", ($Data->needResponse ? 'true' : 'false'), 0);
        }
        else if (is_a($Data, 'LMSResponse'))
        {
            $this->SendDebug($Message . " LMSResponse:Address", $Data->Address(), 0);
            $this->SendDebug($Message . " LMSResponse:Command", $Data->Command(), 0);
            $this->SendDebug($Message . " LMSResponse:Data", $Data->Data(), 0);
        }
        elseif (is_array($Data))
        {
            foreach ($Data as $Key => $DebugData)
            {
                $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
            }
        }
        else if (is_object($Data))
        {
            foreach ($Data as $Key => $DebugData)
            {
                $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
            }
        }
        else
        {
            parent::SendDebug($Message, $Data, $Format);
        }
    }

    /**
     * Liefert den Parent der Instanz.
     * 
     * @return int|bool InstanzID des Parent, false wenn kein Parent vorhanden.
     */
    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    /**
     * Prüft den Parent auf vorhandensein und Status.
     * 
     * @return bool True wenn Parent vorhanden und in Status 102, sonst false.
     */
    protected function HasActiveParent()
    {
        $ParentID = $this->GetParent();
        if ($ParentID !== false)
        {
            if (IPS_GetInstance($ParentID)['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }

    /**
     * Erzeugt einen neuen Parent, wenn keiner vorhanden ist.
     * 
     * @param string $ModuleID Die GUID des benötigten Parent.
     */
    protected function RequireParent($ModuleID)
    {

        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] == 0)
        {

            $parentID = IPS_CreateInstance($ModuleID);
            $instance = IPS_GetInstance($parentID);
            IPS_SetName($parentID, "Client-Socket Logitech Media Server");
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
//
//        if (IPS_GetKernelRunlevel() == KR_READY)
//            $OldStatus = IPS_GetInstance($this->InstanceID)['InstanceStatus'];
//        else
//            $OldStatus = -1;
//
//        if ($InstanceStatus <> $OldStatus)
//        {
//            parent::SetStatus($InstanceStatus);
//            if ($InstanceStatus == IS_ACTIVE)
//                $this->SetTimerInterval('KeepAlive', 3600000);
//            else
//                $this->SetTimerInterval('KeepAlive', 0);
//            return true;
//        }
//        else
//            return false;
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
                throw new Exception("Variable profile type does not match for profile " . $Name, E_USER_NOTICE);
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
        $OldValues = array_column($old, 'Value');
        foreach ($Associations as $Association)
        {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
            $OldKey = array_search($Association[0], $OldValues);
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