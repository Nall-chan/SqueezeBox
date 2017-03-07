<?

require_once(__DIR__ . "/../SqueezeBoxClass.php");  // diverse Klassen
/*
 * @addtogroup squeezebox
 * @{
 *
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2017 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.1
 *
 */

/**
 * LMSSplitter Klasse für die Kommunikation mit dem Logitech Media-Server (LMS).
 * Erweitert IPSModule.
 *
 * @todo          Favoriten als Tabelle oder Baum ?! für das WF
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2017 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.1
 * @example <b>Ohne</b>
 * @property array $ReplyLMSData Description
 * @property string $Buffer
 */
class LMSSplitter extends IPSModule
{

    use LMSHTMLTable,
        LMSSongURL,
        VariableHelper,
        DebugHelper,
        InstanceStatus,
        Profile,
        Semaphore,
        Webhook;

//        LMSCover;

    /**
     * Wert einer Eigenschaft aus den InstanceBuffer lesen.
     * 
     * @access public
     * @param string $name Propertyname
     * @return mixed Value of Name
     */
    public function __get($name)
    {
        //$this->SendDebug('GET_' . $name, unserialize($this->GetBuffer($name)), 0);
        return unserialize($this->GetBuffer($name));
    }

    /**
     * Wert einer Eigenschaft in den InstanceBuffer schreiben.
     * 
     * @access public
     * @param string $name Propertyname
     * @param mixed Value of Name
     */
    public function __set($name, $value)
    {
        $this->SetBuffer($name, serialize($value));
        //$this->SendDebug('SET_' . $name, serialize($value), 0);
    }

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
        $this->RegisterPropertyBoolean("showPlaylist", true);

        $this->ReplyLMSData = array();
        $this->Buffer = "";


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
    public function Destroy()
    {
        $this->UnregisterHook('/hook/LMSPlaylist' . $this->InstanceID);
        $this->UnregisterProfile("PlayerSelect" . $this->InstanceID . ".SqueezeboxServer");
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
        $this->ReplyLMSData = array();
        $this->Buffer = "";
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

        $this->RegisterVariableString("Version", "Version", "", 0);
        $this->RegisterVariableInteger("RescanState", "Scanner", "Scanner.SqueezeboxServer", 1);
        $this->RegisterVariableString("RescanInfo", "Rescan Status", "", 2);
        $this->RegisterVariableString("RescanProgress", "Rescan Fortschritt", "", 3);
        $this->EnableAction("RescanState");
        $this->RegisterVariableInteger("Players", "Anzahl Player", "", 4);

        // ServerPlaylisten
        if ($this->ReadPropertyBoolean('showPlaylist'))
        {
            $this->RegisterVariableInteger("PlayerSelect", "Player wählen", "PlayerSelect" . $this->InstanceID . ".SqueezeboxServer", 5);
            $this->EnableAction("PlayerSelect");
            $this->RegisterVariableString("Playlists", "Playlisten", "~HTMLBox", 6);
//            $sid = $this->RegisterScript("WebHookPlaylist", "WebHookPlaylist", $this->CreateWebHookScript(), -8);
//            IPS_SetHidden($sid, true);
            $this->RegisterHook('/hook/LMSPlaylist' . $this->InstanceID);
            $ID = @$this->GetIDForIdent('PlaylistDesign');
            if ($ID == false)
                $ID = $this->RegisterScript('PlaylistDesign', 'Playlist Config', $this->CreatePlaylistConfigScript(), -7);
            IPS_SetHidden($ID, true);
        }
        else
        {
            $this->UnregisterVariable("PlayerSelect");
            $this->UnregisterVariable("Playlists");
            
            $this->UnregisterHook('/hook/LMSPlaylist' . $this->InstanceID);
            $this->UnregisterProfile("PlayerSelect" . $this->InstanceID . ".SqueezeboxServer");
        }

        //remove old Workarounds 
        $this->UnregisterScript("WebHookPlaylist");        
        $this->UnregisterVariable("BufferIN");
        $this->UnregisterVariable("BufferOUT");
        $this->UnregisterVariable("WaitForResponse");


        // Wenn wir verbunden sind, am LMS mit listen anmelden für Events
        if ($Open)
        {
            if ($this->HasActiveParent($ParentID))
            {
                if (@$this->KeepAlive() === false)
                {
                    $NewState = IS_EBASE + 4;
                    trigger_error('Could not connect to LMS.', E_USER_NOTICE);
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
            $this->RefreshPlayerList();
            $ret = $this->Send(new LMSData("rescan", "?"));
            if ($ret != NULL)
                if ($ret->Data[0] == 0)
                    $this->RefreshPlaylists();
            $DevicesIDs = IPS_GetInstanceListByModuleID("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
            foreach ($DevicesIDs as $Device)
            {
                if (IPS_GetInstance($Device)['ConnectionID'] == $this->InstanceID)
                {
                    //@IPS_ApplyChanges($Device);
                }
            }
            $this->SetTimerInterval('KeepAlive', 3600 * 1000);
        }
        else
            $this->SetTimerInterval('KeepAlive', 0);
    }

################## Privat

    /**
     * Ändert das Variablenprofil PlayerSelect anhand der bekannten Player.
     *
     * @access private
     * @return bool TRUE bei Erfolg, sonst FALSE.
     */
    private function RefreshPlayerList()
    {
        $LMSData = $this->SendDirect(new LMSData(array('player', 'count'), '?'));
        if ($LMSData == null)
            return false;
        $players = $LMSData->Data[0];
        $this->SetValueInteger("Players", $players);
        $Assosiation = array();
        $Assosiation[] = array(-2, 'Keiner', "", 0x00ff00);
        $Assosiation[] = array(-1, 'Alle', "", 0xff0000);
        for ($i = 0; $i < $players; $i++)
        {
            $LMSPlayerData = $this->SendDirect(new LMSData(array('player', 'name', $i), '?'));
            if ($LMSPlayerData == null)
                continue;
            $PlayerName = $LMSPlayerData->Data[0]; //rawurldecode($LMSPlayerData->Data[0]);
            $Assosiation[] = array($i, $PlayerName, "", -1);
        }
        @$this->RegisterProfileIntegerEx("PlayerSelect" . $this->InstanceID . ".SqueezeboxServer", "Speaker", "", "", $Assosiation);
        $this->SetValueInteger('PlayerSelect', -2);
        return true;
    }

    /**
     * Erzeugt eine HTML-Tabelle mit allen Playlisten für eine ~HTMLBox-Variable.
     *
     * @access private
     */
    private function RefreshPlaylists()
    {
        if (!$this->ReadPropertyBoolean('showPlaylist'))
            return;
        $ScriptID = $this->ReadPropertyInteger('Playlistconfig');
        if ($ScriptID == 0)
            return;
        if (!IPS_ScriptExists($ScriptID))
            return;

        $result = IPS_RunScriptWaitEx($ScriptID, array('SENDER' => 'LMS'));
        $Config = unserialize($result);
        if (($Config === false) or ( !is_array($Config)))
        {
            trigger_error('Error on read Playlistconfig-Script', E_USER_NOTICE);
            return;
        }
        $Data = $this->GetPlaylists();
        if ($Data === false)
            return false;
        $HTMLData = $this->GetTableHeader($Config);
        $pos = 0;
        if (count($Data) > 0)
        {
            foreach ($Data as $Line)
            {
                if ($Line['Duration'] > 3600)
                    $Line['Duration'] = @date("H:i:s", $Line['Duration'] - 3600);
                else
                    $Line['Duration'] = @date("i:s", $Line['Duration']);

                $HTMLData .= '<tr style="' . $Config['Style']['BR' . ($pos % 2 ? 'U' : 'G')] . '"
          onclick="window.xhrGet' . $this->InstanceID . '({ url: \'hook/LMSPlaylist' . $this->InstanceID . '?Type=Playlist&ID=' . $Line['Id'] . '\' })">';

                foreach ($Config['Spalten'] as $feldIndex => $value)
                {
                    if (!array_key_exists($feldIndex, $Line))
                        $Line[$feldIndex] = '';

                    $HTMLData .= '<td style="' . $Config['Style']['DF' . ($pos % 2 ? 'U' : 'G') . $feldIndex] . '">' . (string) $Line[$feldIndex] . '</td>';
                }
                $HTMLData .= '</tr>' . PHP_EOL;
                $pos++;
            }
        }
        $HTMLData .= $this->GetTableFooter();
        $this->SetValueString('Playlists', $HTMLData);
    }

    /**
     * Gibt den Inhalt des PHP-Scriptes zurück, welche die Konfiguration und das Design der Playlist-Tabelle enthält.
     *
     * @access private
     * @return string Ein PHP-Script welche als Grundlage für die User dient.
     */
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
# Folgende Parameter bestimmen das Aussehen der HTML-Tabelle in der die Playlist dargestellt wird.

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
echo serialize($Config);
?>';
        return $Script;
    }

################## Action

    /**
     * Actionhandler der Statusvariablen. Interne SDK-Funktion.
     * @todo Playerselect auf muti umbauen
     * @access public
     * @param string $Ident Der Ident der Statusvariable.
     * @param bool|float|int|string $Value Der angeforderte neue Wert.
     */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident)
        {
            case "PlayerSelect":
                $ProfilName = "PlayerSelect" . $this->InstanceID . ".SqueezeboxServer";
                $Profil = IPS_GetVariableProfile($ProfilName)["Associations"];
                switch ($Value)
                {
                    case -2: //keiner
                    case -1: //alle
                        if ($this->SetValueInteger('PlayerSelect', $Value))
                        {
                            for ($i = 2; $i < count($Profil); $i++)
                            {
                                IPS_SetVariableProfileAssociation($ProfilName, $Profil[$i]['Value'], $Profil[$i]['Name'], $Profil[$i]['Icon'], -1);
                            }
                        }
                        break;
                    default:
                        $Value = $Value + 2;
                        $Profil[$Value]['Color'] = ($Profil[$Value]['Color'] == -1 ) ? 0x00ffff : -1;
                        IPS_SetVariableProfileAssociation($ProfilName, $Value - 2, $Profil[$Value]['Name'], $Profil[$Value]['Icon'], $Profil[$Value]['Color']);
                        $this->SetValueInteger('PlayerSelect', -3);
                        break;
                }

                break;
            case "RescanState":
                if ($Value == 1)
                    $ret = $this->Send(new LMSData('abortscan', ''));
                elseif ($Value == 2)
                    $ret = $this->Send(new LMSData('rescan', ''));
                elseif ($Value == 3)
                    $ret = $this->Send(new LMSData('rescan playlists', ''));
                elseif ($Value == 4)
                    $ret = $this->Send(new LMSData('wipecache', '', false));
                if (($Value <> 0) and ( ($ret === NULL) or ( $ret === false)))
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

    /**
     * IPS-Instanz-Funktion 'LMS_ProcessHookdata'. Verarbeitet Daten aus dem Webhook.
     *
     * @access protected
     * @param string $ID Die zu ladenen Playlist oder der zu ladene Favorit.
     */
    protected function ProcessHookdata()
    {
                    if (!isset($_GET["ID"]))
            return;
             echo LMS_ProcessHookdata(IPS_GetParent($_IPS["SELF"]),(string)$_GET["Type"],(string)$_GET["ID"]);

             //
        $Value = GetValueInteger($this->GetIDForIdent("PlayerSelect"));
        switch ($Value)
        {
            case -2: //keiner
                echo "Kein Player gewählt";
                return;
            case -1: //alle
                $Players = range(0, GetValueInteger($this->GetIDForIdent("Players")) - 1);

                break;
            case -3: // multi
                $ProfilName = "PlayerSelect" . $this->InstanceID . ".SqueezeboxServer";
                $Profil = IPS_GetVariableProfile($ProfilName)["Associations"];
                $Players = array();
                for ($i = 2; $i < count($Profil); $i++)
                {
                    if ($Profil[$i]['Color'] == -1)
                        $Players[] = $i - 2;
                }
                break;
            default:
                echo "Unbekannter Player gewählt";
                return;
        }
        //Todo auf Sync umbauen
        foreach ($Players as $PlayerId)
        {
            $Player = $this->GetPlayerInfo($PlayerId);
            if ($Player["Instanceid"] > 0)
            {
                if ($Type == 'Playlist')
                    LSQ_LoadPlaylistByPlaylistID($Player["Instanceid"], (int) $ID);
                if ($Type == 'Favorite')
                    LSQ_LoadPlaylistByFavoriteID($Player["Instanceid"], $ID);
            }
        }
        echo "OK";
    }

################## PUBLIC

    /**
     * IPS-Instanz-Funktion 'LMS_KeepAlive'.
     * Sendet einen listen Abfrage an den LMS um die Kommunikation zu erhalten.
     *
     * @access public
     * @result bool true wenn LMS erreichbar, sonst false.
     */
    public function KeepAlive()
    {

        $Data = new LMSData("listen", "1");
        $ret = @$this->Send($Data);
        if ($ret === NULL)
        {
            trigger_error('Error on keepalive to LMS.', E_USER_NOTICE);
            return false;
        }
        if ($ret->Data[0] == "1")
            return true;

        trigger_error('Error on keepalive to LMS.', E_USER_NOTICE);
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_RawSend'.
     * Sendet einen Anfrage an den LMS.
     *
     * @access public
     * @param string|array $Command Das/Die zu sendende/n Kommando/s.
     * @param string|array $Value Der/Die zu sendende/n Wert/e.
     * @param bool $needResponse True wenn Antwort erwartet.
     * @result array|bool Antwort des LMS als Array, false im Fehlerfall.
     */
    public function SendRaw($Command, $Value, $needResponse)
    {
        $LMSData = new LMSData($Command, $Value, $needResponse);
        $ret = $this->SendDirect($LMSData);
        $ret->SliceData();
        return $ret;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_Rescan'.
     * Startet einen rescan der Datenbank.
     *
     * @access public
     * @result bool True wenn erfolgreich.
     */
    public function Rescan()
    {
        return ($this->Send(new LMSData('rescan')) <> NULL);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_RequestState'.
     * Fragt einen Wert des LMS ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @access public
     * @param string $Ident Der Ident der abzufragenden Statusvariable.
     * @result bool True wenn erfolgreich.
     */
    public function RequestState(string $Ident)
    {
        if ($Ident == "")
        {
            trigger_error('Ident not valid');
            return false;
        }
        switch ($Ident)
        {
            case 'Players':
                $LMSResponse = new LMSData(array('player', 'count'), '?');
                break;
            case 'Version':
                $LMSResponse = new LMSData('version', '?');
                break;
            default:
                trigger_error('Ident not valid');
                return false;
        }
        $LMSResponse = $this->Send($LMSResponse);
        if ($LMSResponse === NULL)
            return false;
        return $this->DecodeLMSResponse($LMSResponse);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetNumberOfPlayers'.
     * @deprecated since version number
     * @access public
     * @result int Anzahl der bekannten Player.
     */
    public function GetNumberOfPlayers()
    {
        trigger_error('Function ist deprecated. Use RequestState and GetValue.', E_USER_DEPRECATED);
        $ret = $this->RequestState("Players");
        if ($ret)
            return GetValueInteger($this->GetIDForIdent('Players'));
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetPlayerInfo'.
     * @access public
     * @param int $Index Der Index des Player.
     * @example <code>
     * $ret = LMS_GetPlayerInfo(37340 \/*[LMSSplitter]*\/,6);
     * var_dump($ret);
     * </code>
     * @return array Ein assoziertes Array mit den Daten des Players.
     */
    public function GetPlayerInfo(int $Index)
    {
        if (!is_int($Index))
        {
            trigger_error("Index must be integer.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('players', array((string) $Index, '1')));
        if ($LMSData === NULL)
            return false;
        $LMSData->SliceData();
        $ret = (new LMSTaggingArray($LMSData->Data))->GetDataArray();
        if (!isset($ret['Playerid']))
        {
            trigger_error('Invalid index', E_USER_NOTICE);
            return false;
        }
        $DevicesIDs = IPS_GetInstanceListByModuleID("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
        $FoundId = 0;
        foreach ($DevicesIDs as $Device)
        {
            if (IPS_GetProperty($Device, 'Address') == $ret['Playerid'])
                $FoundId = $Device;
        }
        $ret['Instanceid'] = $FoundId;
        unset($ret['Count']);
        return $ret;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetVersion'.
     * @deprecated since version number
     * @access public
     * @result string
     */
    public function GetVersion()
    {
        trigger_error('Function ist deprecated. Use RequestState and GetValue.', E_USER_DEPRECATED);
        $ret = $this->RequestState("Version");
        if ($ret)
            return GetValueString($this->GetIDForIdent('Version'));
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetSyncGroups'.
     * Liefer ein Array welches die Gruppen mit ihren jeweiligen IPS-InstanzeIDs enthält.
     * @access public
     * @result array Array welches so viele Elemente wie Gruppen enthält.
     */
    public function GetSyncGroups()
    {
        $LMSData = $this->SendDirect(new LMSData('syncgroups', '?'));
        if ($LMSData == NULL)
            return false;

        if (count($LMSData->Data) == 0)
            return array();
        $AllPlayerIDs = IPS_GetInstanceListByModuleID('{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}');
        $Addresses = array();
        $ret = array();
        foreach ($AllPlayerIDs as $DeviceID)
        {
            $Addresses[$DeviceID] = IPS_GetProperty($DeviceID, 'Address');
        }
        $Data = array_chunk($LMSData->Data, 2);
        foreach ($Data as $Group)
        {
            $FoundInstanzIDs = array();
            $Search = explode(',', (new LMSTaggingData($Group[0]))->Value);
            foreach ($Search as $Value)
            {
                if (array_search($Value, $Addresses) !== false)
                    $FoundInstanzIDs[] = array_search($Value, $Addresses);
            }
            if (count($FoundInstanzIDs) > 0)
                $ret[] = $FoundInstanzIDs;
        }
        return $ret;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetLibaryInfo'.
     * @access public
     * @result array
     */
    public function GetLibaryInfo()
    {
        $genres = $this->Send(new LMSData(array('info', 'total', 'genres'), '?'));
        if ($genres === null)
            return false;
        $artists = $this->Send(new LMSData(array('info', 'total', 'artists'), '?'));
        if ($artists === null)
            return false;
        $albums = $this->Send(new LMSData(array('info', 'total', 'albums'), '?'));
        if ($albums === null)
            return false;
        $songs = $this->Send(new LMSData(array('info', 'total', 'songs'), '?'));
        if ($songs === null)
            return false;
        $duration = $this->Send(new LMSData(array('info', 'total', 'duration'), '?'));
        if ($duration === null)
            return false;
        $ret = array(
            'Genres' => (int) $genres->Data[0],
            'Artists' => (int) $artists->Data[0],
            'Albums' => (int) $albums->Data[0],
            'Songs' => (int) $songs->Data[0],
            'Duration' => (int) $duration->Data[0]
        );
        return $ret;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetGenres'.
     * @access public
     * @result array
     */
    public function GetGenres()
    {
        $LMSData = $this->SendDirect(new LMSData('genres', array(0, 100000)));
        if ($LMSData === null)
            return false;
        $LMSData->SliceData();
        $Genres = new LMSTaggingArray($LMSData->Data);
        $Genres->Compact('Genre');
        return $Genres->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetArtists'.
     * @access public
     * @result array
     */
    public function GetArtists()
    {
        $LMSData = $this->SendDirect(new LMSData('artists', array(0, 100000)));
        if ($LMSData === null)
            return false;
        $LMSData->SliceData();
        $Artists = new LMSTaggingArray($LMSData->Data);
        $Artists->Compact('Artist');
        return $Artists->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetAlbums'.
     * @access public
     * @result array
     */
    public function GetAlbums()
    {
        $LMSData = $this->SendDirect(new LMSData('albums', array(0, 100000)));
        if ($LMSData === null)
            return false;
        $LMSData->SliceData();
        $Albums = new LMSTaggingArray($LMSData->Data);
        $Albums->Compact('Album');
        return $Albums->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetYears'.
     * @access public
     * @result array
     */
    public function GetYears()
    {
        $LMSData = $this->SendDirect(new LMSData('years', array(0, 100000)));
        if ($LMSData === null)
            return false;
        $LMSData->SliceData();
        $Years = new LMSTaggingArray($LMSData->Data);
        $Years->Compact('Year');
        return $Years->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetAlarmPlaylists'.
     * Liefert alle Playlisten welche für den Wecker genutzt werden können.
     * @access public
     * @result array Array mit den Server-Playlists. FALSE im Fehlerfall.
     */
    public function GetAlarmPlaylists()
    {
        $LMSData = $this->SendDirect(new LMSData(array('alarm', 'playlists'), array(0, 100000)));
        if ($LMSData === null)
            return false;
        $LMSData->SliceData();
        return (new LMSTaggingArray($LMSData->Data, 'category'))->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetPlaylists'.
     * Liefert alle Server Playlisten.
     * @access public
     * @result array Array mit den Server-Playlists. FALSE im Fehlerfall.
     */
    public function GetPlaylists()
    {
        $LMSData = $this->SendDirect(new LMSData('playlists', array(0, 100000, 'tags:u')));
        if ($LMSData === null)
            return false;
        $LMSData->SliceData();
        $SongInfo = new LMSSongInfo($LMSData->Data);
        $Playlists = $SongInfo->GetAllSongs();
        foreach ($Playlists as $Key => $Playlist)
        {
            $LMSSongData = $this->SendDirect(new LMSData(array('playlists', 'tracks'), array(0, 100000, 'playlist_id:' . $Playlist['Id'], 'tags:d'), true));
            if ($LMSSongData === NULL)
            {
                trigger_error("Error read Playlist " . $Playlist['Id'] . ".", E_USER_NOTICE);
                $Playlists[$Key]['Playlist'] = $Playlists[$Key]['Playlist'] . " (ERROR ON READ DATA)";
                $Playlists[$Key]['Tracks'] = "";
                $Playlists[$Key]['Duration'] = "";
                continue;
            }
            $LMSSongData->SliceData();
            $SongInfo = new LMSSongInfo($LMSSongData->Data);
            $Playlists[$Key]['Tracks'] = $SongInfo->CountAllSongs();
            $Playlists[$Key]['Duration'] = $SongInfo->GetTotalDuration();
        }
        return $Playlists;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetFavorites'.
     * Liefert ein Array mit allen in $FavoriteID enthaltenen Favoriten.
     *
     * @access public
     * @param string $FavoriteID ID des Favortien welcher ausgelesen werden soll. '' für oberste Ebene.
     * @result array
     */
    public function GetFavorites(string $FavoriteID)
    {

        if (!is_string($FavoriteID))
        {
            trigger_error("FavoriteID must be string.", E_USER_NOTICE);
            return false;
        }
        if ($FavoriteID == '')
            $Data = array(0, 100000, 'want_url:1');
        else
            $Data = array(0, 100000, 'want_url:1', 'item_id:' . $FavoriteID);
        $LMSData = $this->SendDirect(new LMSData(array('favorites', 'items'), $Data));
        if ($LMSData === null)
            return false;
        $LMSData->SliceData();
        array_shift($LMSData->Data);
        return (new LMSTaggingArray($LMSData->Data))->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetFavorites'.
     * @access public
     * @result array
     */
    public function AddFavorite(string $FavoriteID, string $Title, string $URL)
    {
        if (!is_string($FavoriteID))
        {
            trigger_error("FavoriteID must be string.", E_USER_NOTICE);
            return false;
        }
        if (!is_string($Title))
        {
            trigger_error("Title must be string.", E_USER_NOTICE);
            return false;
        }
        if ($this->GetValidSongURL($URL) == false)
            return false;
        $LMSData = $this->SendDirect(new LMSData(array('favorites', 'add'), array('item_id:' . $FavoriteID, 'title:' . $Title, 'url:' . $URL)));
        if ($LMSData === null)
            return false;
        return $LMSData;
        $LMSData->SliceData();
//        array_shift($LMSData->Data);
//        return (new LMSTaggingArray($LMSData->Data))->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_AddFavoriteLevel'.
     * Erzeugt eine neue Ebene unterhalb $ParentFavoriteID mit dem Namen von $Title.
     *
     * @access public
     * @param string $ParentFavoriteID Die ID des Favoriten unter dem die neue Ebene erzeugt wird.
     * @param string $Title Der Name der neuen Ebene.
     * @result bool TRUE bei Erfolg, sonst FALSE.
     */
    public function AddFavoriteLevel(string $ParentFavoriteID, string $Title)
    {
        if (!is_string($ParentFavoriteID))
        {
            trigger_error("ParentFavoriteID must be string.", E_USER_NOTICE);
            return false;
        }
        if (!is_string($Title))
        {
            trigger_error("Title must be string.", E_USER_NOTICE);
            return false;
        }
        if ($ParentFavoriteID == '')
            $Data = 'title:' . $Title;
        else
            $Data = array('item_id:' . $ParentFavoriteID, 'title:' . $Title);
        $LMSData = $this->SendDirect(new LMSData(array('favorites', 'addlevel'), $Data));
        if ($LMSData === null)
            return false;

        $LMSData->SliceData();

        return (rawurldecode($LMSData->Data[0]) == "count:1");
    }

    /**
     * IPS-Instanz-Funktion 'LMS_DeleteFavorite'.
     * Löscht einen Eintrag aus den Favoriten.
     *
     * @access public
     * @param string $FavoriteID Die ID des Favoriten welcher gelöscht werden soll.
     * @result bool TRUE bei Erfolg, sonst FALSE.
     */
    public function DeleteFavorite(string $FavoriteID)
    {
        if (!is_string($FavoriteID))
        {
            trigger_error("FavoriteID must be string.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('favorites', 'delete'), 'item_id:' . $FavoriteID));
        if ($LMSData === null)
            return false;
        $LMSData->SliceData();

        return (count($LMSData->Data) == 0);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_RenameFavorite'.
     * Benennt eine neue Favoriten um.
     *
     * @access public
     * @param string $FavoriteID Die ID des Favoriten welcher umbenannt werden soll.
     * @param string $Title Der neue Name des Favoriten.
     * @result bool TRUE bei Erfolg, sonst FALSE.
     */
    public function RenameFavorite(string $FavoriteID, string $Title)
    {
        if (!is_string($FavoriteID))
        {
            trigger_error("FavoriteID must be string.", E_USER_NOTICE);
            return false;
        }
        if (!is_string($Title))
        {
            trigger_error("Title must be string.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('favorites', 'rename'), array('item_id:' . $FavoriteID, 'title:' . $Title)));
        if ($LMSData === null)
            return false;
        $LMSData->SliceData();

        return (count($LMSData->Data) == 0);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_MoveFavorite'.
     * Verschiebt einen Favoriten.
     *
     * @access public
     * @param string $FavoriteID Die ID des Favoriten welcher verschoben werden soll.
     * @param string $NewParentFavoriteID Das Ziel des zu verschiebenen Favoriten.
     * @result bool TRUE bei Erfolg, sonst FALSE.
     */
    public function MoveFavorite(string $FavoriteID, string $NewParentFavoriteID)
    {
        if (!is_string($FavoriteID))
        {
            trigger_error("Favorite must be string.", E_USER_NOTICE);
            return false;
        }
        if (!is_string($NewParentFavoriteID))
        {
            trigger_error("NewParentFavoriteID must be string.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('favorites', 'move'), array('from_id:' . $FavoriteID, 'to_id:' . $NewParentFavoriteID)));
        if ($LMSData === null)
            return false;
        $LMSData->SliceData();

        return (count($LMSData->Data) == 0);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetDirectoryByID'. Liefert Informationen zu einem Verzeichnis.
     *
     * @access public
     * @param int $FolderID ID des Verzeichnis welches durchsucht werden soll. 0= root
     * @return array|bool Array mit den Quellen oder false bei Fehler.
     */
    public function GetDirectoryByID(int $FolderID)
    {
        if (!is_int($FolderID))
        {
            trigger_error('FolderID must be integer', E_USER_NOTICE);
            return false;
        }
        if ($FolderID == 0)
            $Data = array('0', 100000, 'tags:uc');
        else
            $Data = array('0', 100000, 'tags:uc', 'folder_id:' . $FolderID);
        $LMSData = $this->SendDirect(new LMSData('mediafolder', $Data));
        if ($LMSData == null)
            return false;
        $LMSData->SliceData();

        return (new LMSTaggingArray($LMSData->Data))->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetDirectoryByIDEx'. Liefert rekursiv Informationen zu einem Verzeichnis.
     *
     * @access public
     * @param int $FolderID ID des Verzeichnis welches durchsucht werden soll.
     * @return array|bool Array mit den Quellen oder false bei Fehler.
     */
    public function GetDirectoryByIDRecursiv(int $FolderID)
    {
        if (!is_int($FolderID))
        {
            trigger_error('FolderID must be integer', E_USER_NOTICE);
            return false;
        }
        if ($FolderID == 0)
        {
            trigger_error('Search root recursive is not supported', E_USER_NOTICE);
            return false;
        }
        else
            $Data = array('0', 100000, 'tags:uc', 'folder_id:' . $FolderID);
        $LMSData = $this->SendDirect(new LMSData('mediafolder', $Data));
        if ($LMSData == null)
            return false;
        $LMSData->SliceData();

        return (new LMSTaggingArray($LMSData->Data))->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetDirectoryByURL'. Liefert rekursiv Informationen zu einem Verzeichnis.
     *
     * @access public
     * @param string $Directory URL des Verzeichnis welches durchsucht werden soll.
     * @return array|bool Array mit den Quellen oder false bei Fehler.
     */
    public function GetDirectoryByURL(string $Directory)
    {
        if (!is_string($Directory))
        {
            trigger_error('Directory must be string', E_USER_NOTICE);
            return false;
        }
        if ($Directory == '')
            $Data = array('0', 100000, 'tags:uc');
        else
            $Data = array('0', 100000, 'tags:uc', 'url:' . $Directory);
        $LMSData = $this->SendDirect(new LMSData('mediafolder', $Data));
        if ($LMSData == null)
            return false;
        $LMSData->SliceData();

        return (new LMSTaggingArray($LMSData->Data))->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetDirectoryByURLRecursiv'. Liefert rekursiv Informationen zu einem Verzeichnis.
     *
     * @access public
     * @param string $Directory URL des Verzeichnis welches durchsucht werden soll.
     * @return array|bool Array mit den Quellen oder false bei Fehler.
     */
    public function GetDirectoryByURLRecursiv(string $Directory)
    {
        if (!is_string($Directory))
        {
            trigger_error('Directory must be string', E_USER_NOTICE);
            return false;
        }
        if ($Directory == '')
            $Data = array('0', 100000, 'recursive:1', 'tags:uc');
        else
            $Data = array('0', 100000, 'recursive:1', 'tags:uc', 'url:' . $Directory);
        $LMSData = $this->SendDirect(new LMSData('mediafolder', $Data));
        if ($LMSData == null)
            return false;
        $LMSData->SliceData();

        return (new LMSTaggingArray($LMSData->Data))->GetDataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetSongInfoByFileID'.
     * Liefert Details zu einem Song anhand der ID.
     * @access public
     * @param int $SongID Die ID des Song
     * @result array Array mit den Daten des Song. FALSE wenn SongID unbekannt.
     */
    public function GetSongInfoByFileID(int $SongID)
    {
        if (!is_int($SongID))
        {
            trigger_error("SongID must be integer.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('songinfo', '0', '20'), array('track_id:' . $SongID, 'tags:gladiqrRtueJINpsy')));
        if ($LMSData === NULL)
            return FALSE;
        $LMSData->SliceData();
        $SongInfo = new LSMSongInfo($LMSData->Data);
        $Song = $SongInfo->GetSong();
        if ($Song == NULL)
        {
            trigger_error("SongID not valid.", E_USER_NOTICE);
            return false;
        }
        return $Song;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetSongInfoByFileURL'.
     * Liefert Details zu einem Song anhand der URL.
     * @access public
     * @param int $SongURL Die URL des Song
     * @result array Array mit den Daten des Song. FALSE wenn Song unbekannt.
     */
    public function GetSongInfoByFileURL(string $SongURL)
    {
        if ($this->GetValidSongURL($SongURL) == false)
            return false;
        $LMSData = $this->SendDirect(new LMSData(array('songinfo', '0', '20'), array('url:' . $SongURL, 'tags:gladiqrRtueJINpsy')));
        if ($LMSData === NULL)
            return false;
        $SongInfo = new LSMSongInfo($LMSData->Data);
        $Song = $SongInfo->GetSong();
        if (count($Song) == 1)
        {
            trigger_error("SongURL not found.", E_USER_NOTICE);
            return false;
        }
        return $Song;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_CreatePlaylist'.
     * Erzeugt eine Playlist.
     * @access public
     * @param string $Name Der Name für die neue Playlist.
     * @result int Die PlaylistId der neu erzeugten Playlist. FALSE im Fehlerfall.
     */
    public function CreatePlaylist(string $Name)
    {
        if (!is_string($Name))
        {
            trigger_error("Name must be string.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playlists', 'new'), 'name:' . $Name));
        if ($LMSData === NULL)
            return false;
        $LMSData->SliceData();
        if (strpos($LMSData->Data[0], 'playlist_id') === 0)
        {
            return (int) (new LMSTaggingData($LMSData->Data[0]))->Value;
        }
        trigger_error("Playlist already exists.", E_USER_NOTICE);
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_DeletePlaylist'.
     * Löscht eine Playlist.
     * @access public
     * @param int $PlaylistId Die ID der zu löschenden Playlist.
     * @result bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function DeletePlaylist(int $PlaylistId)
    {
        if (!is_int($PlaylistId))
        {
            trigger_error("PlayListId must be integer.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playlists', 'delete'), 'playlist_id:' . $PlaylistId));
        if ($LMSData === NULL)
            return false;
        if (strpos($LMSData->Data[0], 'playlist_id') === 0)
        {
            return $PlaylistId === (int) (new LMSTaggingData($LMSData->Data[0]))->Value;
        }
        trigger_error("Error deleting Playlist.", E_USER_NOTICE);
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_RenamePlaylist'.
     * Benennt eine Playlist um.
     * @access public
     * @param int $PlaylistId Die ID der Playlist welche umbenannt werden soll.
     * @param string $Name Der neue Name der Playlist.
     * @result bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function RenamePlaylist(int $PlaylistId, string $Name)
    {
        if (!is_int($PlaylistId))
        {
            trigger_error("PlayListId must be integer.", E_USER_NOTICE);
            return false;
        }
        if (!is_string($Name))
        {
            trigger_error("Name must be string.", E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playlists', 'rename'), array('playlist_id:' . $PlaylistId, 'newname:' . $Name, 'dry_run:1')));
        if ($LMSData === NULL)
            return false;
        $LMSData->SliceData();
        if (count($LMSData->Data) > 0)
        {
            if ((new LMSTaggingData($LMSData->Data[0]))->Value == $PlaylistId)
                return true;
            trigger_error("Error rename Playlist. Name already used by Playlist " . (new LMSTaggingData($LMSData->Data[0]))->Value . '.', E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playlists', 'rename'), array('playlist_id:' . $PlaylistId, 'newname:' . $Name, 'dry_run:0')));
        if ($LMSData === NULL)
            return false;
        $LMSData->SliceData();
        if (count($LMSData->Data) > 0)
        {
            trigger_error("Error rename Playlist. Name already used by Playlist " . (new LMSTaggingData($LMSData->Data[0]))->Value . '.', E_USER_NOTICE);
            return false;
        }
        return true;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetPlaylist'.
     * Liefert alle Songs einer Playlist.
     * @access public
     * @param int $PlaylistId Die Playlist welche gelesen werden soll.
     * @result array Array mit Songs der Playlist.
     */
    public function GetPlaylist(int $PlaylistId)
    {
        if (!is_int($PlaylistId))
        {
            trigger_error("PlaylistId must be integer.", E_USER_NOTICE);
            return false;
        }
        $LMSSongData = $this->SendDirect(new LMSData(array('playlists', 'tracks'), array(0, 10000, 'playlist_id:' . $PlaylistId, 'tags:gladiqrRtueJINpsy'), true));
        if ($LMSSongData === NULL)
        {
            trigger_error("Error read Playlist " . $PlaylistId . ".", E_USER_NOTICE);
            return false;
        }
        $LMSSongData->SliceData();
        $SongInfo = new LMSSongInfo($LMSSongData->Data);
        return $SongInfo->GetAllSongs();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_AddSongToPlaylist'.
     * Fügt einen Song einer Playlist hinzu.
     * @access public
     * @param int $PlaylistId Die ID der Playlist zu welcher ein Song hinzugefügt wird.
     * @param string $SongURL Die URL des Song, Verzeichnisses oder Streams.
     * @result bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function AddSongToPlaylist(int $PlaylistId, string $SongURL)
    {
        return $this->AddSongToPlaylistEx($PlaylistId, $SongURL, -1);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_AddSongToPlaylistEx'.
     * Fügt einen Song einer Playlist an einer bestimmten Position hinzu.
     * @todo GetValidSongURL prüfen, da auch Playlisten, Verzeichnisse und Streams erlaubt sind ?
     * @access public
     * @param int $PlaylistId Die ID der Playlist zu welcher ein Song hinzugefügt wird.
     * @param string $SongURL Die URL des Song.
     * @param int $Position Die Position (1 = 1.Eintrag) an welcher der Song eingefügt wird.
     * @result bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function AddSongToPlaylistEx(int $PlaylistId, string $SongURL, int $Position)
    {
        if (!is_int($PlaylistId))
        {
            trigger_error("PlaylistId must be integer.", E_USER_NOTICE);
            return false;
        }
        if ($this->GetValidSongURL($SongURL) == false)
            return false;
        if (!is_int($Position))
        {
            trigger_error("Position must be integer.", E_USER_NOTICE);
            return false;
        }

        $LMSData = $this->SendDirect(new LMSData(array('playlists', 'edit'), array('cmd:add', 'playlist_id:' . $PlaylistId, 'url:' . $SongURL)));
        if ($LMSData === NULL)
            return FALSE;

        if (strpos($LMSData->Data[2], 'url') === 0)
        {
            if ($SongURL != (new LMSTaggingData($LMSData->Data[2]))->Value)
            {
                trigger_error("Error on add SongURL to playlist.", E_USER_NOTICE);
                return false;
            }
        }

        if ($Position == -1)
            return true;

        $LMSSongData = $this->SendDirect(new LMSData(array('playlists', 'tracks'), array(0, 10000, 'playlist_id:' . $PlaylistId, 'tags:'), true));
        if ($LMSSongData === NULL)
        {
            trigger_error("Error on move Song after adding to playlist.", E_USER_NOTICE);
            return false;
        }

        $OldPosition = new LMSTaggingData(array_pop($LMSSongData->Data));
        if ($OldPosition->Name <> 'count')
        {
            trigger_error("Error on move Song after adding to playlist.", E_USER_NOTICE);
            return false;
        }
        return $this->MoveSongInPlaylist($PlaylistId, (int) $OldPosition->Value - 1, $Position);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_DeleteSongFromPlaylist'.
     * Entfernt einen Song aus einer Playlist.
     * @access public
     * @param int $PlaylistId Die ID der Playlist aus welcher ein Song entfernt wird.
     * @param int $Position Die Position (1 = 1.Eintrag) des Song welcher entfernt wird.
     * @result bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function DeleteSongFromPlaylist(int $PlaylistId, int $Position)
    {
        if (!is_int($PlaylistId))
        {
            trigger_error("PlaylistId must be integer.", E_USER_NOTICE);
            return false;
        }
        if (!is_int($Position))
        {
            trigger_error("Position must be integer.", E_USER_NOTICE);
            return false;
        }
        $Position--;
        $LMSData = $this->SendDirect(new LMSData(array('playlists', 'edit'), array('cmd:delete', 'playlist_id:' . $PlaylistId, 'index:' . $Position)));
        if ($LMSData == NULL)
            return false;
        $LMSData->SliceData();
        if (count($LMSData->Data) > 0)
        {
            trigger_error("Error delete song from playlist.", E_USER_NOTICE);
            return false;
        }
        return true;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_MoveSongInPlaylist'.
     * Verschiebt die Position eines Song innerhalb einer Playlist.
     * @access public
     * @param int $PlaylistId Die ID der Playlist.
     * @param int $Position Die Position (1 = 1.Eintrag) des Song welcher verschoben wird.
     * @param int $NewPosition Die neue Position (1 = 1.Eintrag) des zu verschiebenen Song.
     * @result bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function MoveSongInPlaylist(int $PlaylistId, int $Position, int $NewPosition)
    {
        if (!is_int($PlaylistId))
        {
            trigger_error("PlaylistId must be integer.", E_USER_NOTICE);
            return false;
        }
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
        $LMSData = $this->SendDirect(new LMSData(array('playlists', 'edit'), array('cmd:move', 'playlist_id:' . $PlaylistId, 'index:' . $Position, 'toindex:' . $NewPosition)));
        if ($LMSData === NULL)
            return FALSE;
        $LMSData->SliceData();
        if (count($LMSData->Data) > 0)
        {
            trigger_error("Error on move Song in playlist.", E_USER_NOTICE);
            return false;
        }
        return true;
    }

################## Decode Data

    private function DecodeLMSResponse(LMSData $LMSData)
    {
        if ($LMSData == NULL)
            return false;
        $this->SendDebug('Decode', $LMSData, 0);
        switch ($LMSData->Command[0])
        {
            case "listen":
                return true;
                break;
            case "scanner":
                switch ($LMSData->Data[0])
                {
                    case "notify":
                        $Data = new LMSTaggingData($LMSData->Data[1]);
//                        $this->SendDebug('scanner', $Data, 0);
                        switch ($Data->Name)
                        {
                            case "end":
                            case "exit":
                                $this->SetValueString("RescanInfo", "");
                                $this->SetValueString("RescanProgress", "");
                                return true;
                            case "progress":
//                                $this->SendDebug('progress', $Data->Value, 0);
//                                $Info = explode("||", rawurldecode($Data->value));
                                $Info = explode("||", $Data->value);
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
            case "wipecache":
                $this->SetValueInteger("RescanState", 4); // Vollständig
                return true;
            case "player":
                if ($LMSData->Command[1] == "count")
                {
                    $this->SetValueInteger('Players', (int) $LMSData->Data[0]);
                    return true;
                }
                break;
            case "version":
                $this->SetValueString('Version', $LMSData->Data[0]);
                return true;
            case "rescan":
                if (!isset($LMSData->Data[0]))
                {
                    $this->SetValueInteger("RescanState", 2); // einfacher
                    return true;
                }
                else
                {
                    if (($LMSData->Data[0] == 'done') or ( $LMSData->Data[0] == '0'))
                    {
                        if ($this->SetValueInteger("RescanState", 0))   // fertig
                            $this->RefreshPlaylists();
                        return true;
                    }
                    elseif ($LMSData->Data[0] == 'playlists')
                    {
                        $this->SetValueInteger("RescanState", 3); // Playlists
                        return true;
                    }
                    elseif ($LMSData->Data[0] == '1')
                    {
                        //start
                        $this->SetValueInteger("RescanState", 2); // einfacher
                        return true;
                    }
                }
                break;
            case "playlists":
                if (count($LMSData->Command) > 1)
                    if (in_array($LMSData->Command[1], array('rename', 'delete', 'new', 'edit')))
                    //$this->RefreshPlaylists(); //todo Playlists im Buffer vorhalten und mit passender Aktion bearbeiten
                        break;
            case "favorites":
                if (count($LMSData->Command) > 1)
                    if (in_array($LMSData->Command[1], array('addlevel', 'rename', 'delete', 'move', 'changed')))
//mediafolder
                    //$this->RefreshFavoriteslist();
                    /*
                     *
                      favorites items
                      favorites exists
                      favorites add
                      favorites addlevel
                      favorites delete
                      favorites rename
                      favorites move
                      favorites playlist

                     */
                        break;
            case "songinfo":
                break;
            default:
                IPS_LogMessage('unhandled Decode LMS', print_r($LMSData, true));
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
     * @result LMSData|bool 
     */
    public function ForwardData($JSONString)
    {
        $Data = json_decode($JSONString);
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
    private function SendDataToDevice(LMSResponse $LMSResponse)
    {
        $Data = $LMSResponse->ToJSONStringForDevice("{CB5950B3-593C-4126-9F0F-8655A3944419}");
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
        $head = $this->Buffer;
        $Data = $head . utf8_decode($data->Buffer);

        // Stream in einzelne Pakete schneiden
        $packet = explode(chr(0x0d), $Data);

        // Rest vom Stream wieder in den Empfangsbuffer schieben
        $tail = trim(array_pop($packet));
        $this->Buffer = $tail;

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
                $buffer = $this->Buffer;
                $this->Buffer = $part . chr(0x0d) . $buffer;
                trigger_error($exc->getMessage(), E_USER_NOTICE);
                continue;
            }
            if ($Data->Data[0] == LSQResponse::client) // Client änderungen auch hier verarbeiten!
                $this->RefreshPlayerList();
            //IPS_RunScriptText("<?\nLMS_RefreshPlayerList(" . $this->InstanceID . ");");

            if ($isResponse === false) //War keine Antwort also ein Event
            {
                $this->SendDebug('LMS_Event', $Data, 0);
                if ($Data->Device != LMSResponse::isServer)
                    $this->SendDataToDevice($Data);
                else
                    $this->DecodeLMSResponse($Data);
            }
        }
    }

    /**
     * Versendet ein LMSData-Objekt und empfängt die Antwort.
     *
     * @access protected
     * @param LMSData $LMSData Das Objekt welches versendet werden soll.
     * @return LMSData Enthält die Antwort auf das Versendete Objekt oder NULL im Fehlerfall.
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
//                    $this->SetStatus(IS_EBASE + 3);
                    throw new Exception('No anwser from LMS', E_USER_NOTICE);
                }

//                $this->SendDebug('ResponseRAWData', $ReplyDataArray, 0);
                //$ret = new LMSData($LMSData->Command, array_slice($ReplyDataArray, count($LMSData->Command) - 1), true);
                $LMSData->Data = $ReplyDataArray;
                $this->SendDebug('Response', $LMSData, 0);
                return $LMSData;
            }
            else // ohne Response, also ohne warten raussenden,
            {
                $this->SendDebug('SendFaF', $LMSData, 0);
                return $this->SendDataToParent($LMSData);
            }
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return NULL;
        }
    }

    /**
     * Konvertiert $Data zu einem String und versendet diesen direkt an den LMS.
     *
     * @access protected
     * @param LMSData $LMSData Zu versendende Daten.
     * @return LMSData LMSData mit der Antwort. NULL im Fehlerfall.
     */
    protected function SendDirect(LMSData $LMSData)
    {

        try
        {
            if ($this->ReadPropertyBoolean("Open") === false)
                throw new Exception('Instance inactiv.', E_USER_NOTICE);

            if (!$this->HasActiveParent())
                throw new Exception('Intance has no active parent.', E_USER_NOTICE);

            $Host = $this->ReadPropertyString("Host");
            if ($Host === "")
                return NULL;

            $Port = $this->ReadPropertyInteger('Port');
//            $User = IPS_GetProperty($instance['ConnectionID'], 'Username');
//            $Pass = IPS_GetProperty($instance['ConnectionID'], 'Password');

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
            //          $this->SendDebug('Receive', $ReplyData, 0);
//            $ret = array_slice($ReplyData->Data, count($LMSData->Command) - 1);
            //$LMSData->Data = array_slice($ReplyData->Data, count($LMSData->Command) - 1);
            $LMSData->Data = $ReplyData->Data;
            //$ret = new LMSData($LMSData->Command, , true);
            //$this->SendDebug('Response', $LMSData, 0);
            //          if (count($ret) == 1)
//                $ret = $ret[0];

            $this->SendDebug('Response Direct', $LMSData, 0);
            return $LMSData;
        }
        catch (Exception $ex)
        {
            $this->SendDebug("Response Direct", $ex->getMessage(), 0);
            trigger_error($ex->getMessage(), $ex->getCode());
        }
        return NULL;
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
        $SearchPatter = $LMSData->GetSearchPatter();
        for ($i = 0; $i < 1000; $i++)
        {

            $Buffer = $this->ReplyLMSData;
            if (!array_key_exists($SearchPatter, $Buffer))
                return false;
            if (array_key_exists('Data', $Buffer[$SearchPatter]))
            {
                $this->SendQueueRemove($SearchPatter);
                return $Buffer[$SearchPatter]['Data'];
            }
            IPS_Sleep(5);
        }
        $this->SendQueueRemove($SearchPatter);
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
        $data = $this->ReplyLMSData;
        $data[$LMSData->GetSearchPatter()] = array();
        $this->ReplyLMSData = $data;
        $this->unlock('ReplyLMSData');
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
//        if (is_array($LMSResponse->Command))
        $key = $LMSResponse->GetSearchPatter(); //Address . implode('', $LMSResponse->Command);
        //$this->SendDebug('SendQueueUpdate', $key, 0);
//        else
//            $key = $LMSResponse->Address . $LMSResponse->Command;
        $data = $this->ReplyLMSData;
        if (array_key_exists($key, $data))
        {
            $data[$key]['Data'] = $LMSResponse->Data;
            $this->ReplyLMSData = $data;
            $this->unlock('ReplyLMSData');
            return true;
        }
        $this->unlock('ReplyLMSData');
        return false;
    }

    /**
     * Löscht einen Eintrag aus der SendQueue.
     *
     * @access private
     * @param int $Index Der Index des zu löschenden Eintrags.
     */
    private function SendQueueRemove(string $Index)
    {
        if (!$this->lock('ReplyLMSData'))
            throw new Exception('ReplyLMSData is locked', E_USER_NOTICE);
        $data = $this->ReplyLMSData;
        unset($data[$Index]);
        $this->ReplyLMSData = $data;
        $this->unlock('ReplyLMSData');
    }

################## DUMMYS / WOARKAROUNDS - protected

    /**
     * Öffnet oder schließt den übergeordneten IO-Parent
     *
     * @access private
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

    /**
     * Erzeugt einen neuen Parent, wenn keiner vorhanden ist.
     *
     * @access protected
     * @param string $ModuleID Die GUID des benötigten Parent.
     */
    protected function RequireParent($ModuleID)
    {

        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] == 0)
        {

            $parentID = IPS_CreateInstance($ModuleID);
            $instance = IPS_GetInstance($parentID);
            IPS_SetName($parentID, "LMS CLI-Socket");
            IPS_ConnectInstance($this->InstanceID, $parentID);
        }
    }

}

/** @} */
?>