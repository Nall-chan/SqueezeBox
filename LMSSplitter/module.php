<?php

declare(strict_types=1);

/*
 * @addtogroup squeezebox
 * @{
 *
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.0
 *
 */

require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen
require_once __DIR__ . '/../libs/SqueezeBoxClass.php';  // diverse Klassen
eval('declare(strict_types=1);namespace squeezebox {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace squeezebox {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace squeezebox {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace squeezebox {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace squeezebox {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace squeezebox {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');

/**
 * LMSSplitter Klasse für die Kommunikation mit dem Logitech Media-Server (LMS).
 * Erweitert IPSModule.
 *
 * @todo          Favoriten als Tabelle oder Baum ?! für das WF
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.0
 * @example <b>Ohne</b>
 * @property array $ReplyLMSData Enthält die versendeten Befehle und buffert die Antworten.
 * @property string $Buffer Empfangsbuffer
 * @property string $Host Adresse des LMS (aus IO-Parent ausgelesen)
 * @property int $ParentID Die InstanzeID des IO-Parent
 * @property array $Multi_Playlists Alle Datensätze der Playlisten
 * @property int $ScannerID VariablenID des Scanner State
 */
class LMSSplitter extends IPSModule
{

    use LMSHTMLTable,
        LMSSongURL,
        LMSProfile,
        \squeezebox\VariableProfileHelper,
        \squeezebox\VariableHelper,
        \squeezebox\DebugHelper,
        \squeezebox\BufferHelper,
        \squeezebox\InstanceStatus,
        \squeezebox\Semaphore,
        \squeezebox\WebhookHelper {
        \squeezebox\InstanceStatus::MessageSink as IOMessageSink; // MessageSink gibt es sowohl hier in der Klasse, als auch im Trait InstanceStatus. Hier wird für die Methode im Trait ein Alias benannt.
        \squeezebox\InstanceStatus::RegisterParent as IORegisterParent;
        \squeezebox\InstanceStatus::RequestAction as IORequestAction;
    }
    private $Socket = false;

    public function __destruct()
    {
        if ($this->Socket) {
            fclose($this->Socket);
        }
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RequireParent('{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}');
        $this->RegisterPropertyString('User', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyInteger('Port', 9090);
        $this->RegisterPropertyInteger('Webport', 9000);
        $this->RegisterPropertyBoolean('showPlaylist', true);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString('Table', json_encode($Style['Table']));
        $this->RegisterPropertyString('Columns', json_encode($Style['Columns']));
        $this->RegisterPropertyString('Rows', json_encode($Style['Rows']));
        $this->RegisterTimer('KeepAlive', 0, 'LMS_KeepAlive($_IPS["TARGET"]);');

        $this->ReplyLMSData = [];
        $this->Buffer = '';
        $this->Multi_Playlists = [];
        $this->Host = '';
        $this->ParentID = 0;
        $this->ScannerID = 0;
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook('/hook/LMSPlaylist' . $this->InstanceID);
            $this->DeleteProfile();
        }
        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        $this->ReplyLMSData = [];
        $this->Buffer = '';
        $this->Multi_Playlists = [];
        $this->Host = '';
        $this->ParentID = 0;
        $this->ScannerID = 0;
        parent::ApplyChanges();

        // Buffer leeren
        $this->ReplyLMSData = [];
        $this->Buffer = '';

        // Eigene Profile
        $this->CreateProfile();
        // Eigene Variablen
        $this->RegisterVariableString('Version', 'Version', '', 0);
        $this->ScannerID = $this->RegisterVariableInteger('RescanState', 'Scanner', 'LMS.Scanner', 1);
        $this->RegisterMessage($this->ScannerID, VM_UPDATE);
        $this->EnableAction('RescanState');

        $this->RegisterVariableString('RescanInfo', $this->Translate('Rescan state'), '', 2);
        $this->RegisterVariableString('RescanProgress', $this->Translate('Rescan progess'), '', 3);
        $this->RegisterVariableInteger('Players', 'Number of players', '', 4);

        // ServerPlaylisten
        if ($this->ReadPropertyBoolean('showPlaylist')) {
            $this->RegisterProfileIntegerEx('LMS.PlayerSelect' . $this->InstanceID, 'Speaker', '', '', []);
            $this->RegisterVariableInteger('PlayerSelect', $this->Translate('select player'), 'LMS.PlayerSelect' . $this->InstanceID, 5);
            $this->EnableAction('PlayerSelect');
            $this->RegisterVariableString('Playlists', $this->Translate('Playlists'), '~HTMLBox', 6);
        } else {
            $this->UnregisterVariable('PlayerSelect');
            $this->UnregisterVariable('Playlists');
            $this->UnregisterProfile('LMS.PlayerSelect' . $this->InstanceID);
        }

        // Wenn Kernel nicht bereit, dann warten... KR_READY kommt ja gleich
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // ServerPlaylisten
        if ($this->ReadPropertyBoolean('showPlaylist')) {
            $this->RegisterHook('/hook/LMSPlaylist' . $this->InstanceID);
        } else {
            $this->UnregisterHook('/hook/LMSPlaylist' . $this->InstanceID);
        }

        // Config prüfen
        $this->RegisterParent();


        // Wenn Parent aktiv, dann Anmeldung an der Hardware bzw. Datenabgleich starten
        if ($this->ParentID > 0) {
            IPS_ApplyChanges($this->ParentID);
        }
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);

        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            case VM_UPDATE:
                if (($SenderID == $this->ScannerID) and ( $Data[0] == 0)) {
                    $this->RefreshAllPlaylists();
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

    protected function RegisterParent()
    {
        $IOId = $this->IORegisterParent();
        if ($IOId > 0) {
            $this->Host = gethostbyname(IPS_GetProperty($this->ParentID, 'Host'));
            $this->SetSummary(IPS_GetProperty($IOId, 'Host'));
            return;
        }
        $this->Host = '';
        $this->SetSummary(('none'));
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     * @access protected
     */
    protected function IOChangeState($State)
    {
        if ($State == IS_ACTIVE) {
            if ($this->HasActiveParent()) {
                if ($this->CheckLogin() !== true) {
                    $this->SetStatus(IS_EBASE + 4);
                    $this->SetTimerInterval('KeepAlive', 0);
                    return;
                }
                $this->SetStatus(IS_ACTIVE);
                $User = $this->ReadPropertyString('User');
                $Pass = $this->ReadPropertyString('Password');

                $LoginData = new LMSData('login', [$User, $Pass]);
                $this->Send($LoginData);
                $this->KeepAlive();
                $this->LogMessage($this->Translate('Connected to LMS'), KL_NOTIFY);
                $this->RequestState('Version');
                $this->LogMessage($this->Translate('Version of LMS:') . $this->GetValue('Version'), KL_NOTIFY);
                $this->RequestState('Players');
                $this->LogMessage($this->Translate('Connected Players to LMS:') . $this->GetValue('Players'), KL_NOTIFY);
                $this->RefreshPlayerList();
                $ret = $this->Send(new LMSData('rescan', '?'));
                if ($ret !== null) {
                    $this->DecodeLMSResponse($ret);
                }
                $this->SetTimerInterval('KeepAlive', 3600 * 1000);
                return;
            }
        }
        $this->SetStatus(IS_INACTIVE); // Setzen wir uns auf active, weil wir vorher eventuell im Fehlerzustand waren.
        $this->SetTimerInterval('KeepAlive', 0);
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function GetConfigurationForParent()
    {
        $Config['Port'] = $this->ReadPropertyInteger('Port');
        return json_encode($Config);
    }

    private function GenerateHTMLStyleProperty()
    {
        $NewTableConfig = [
            [
                'tag'   => '<table>',
                'style' => 'margin:0 auto; font-size:0.8em;'],
            [
                'tag'   => '<thead>',
                'style' => ''],
            [
                'tag'   => '<tbody>',
                'style' => '']
        ];
        $NewColumnsConfig = [
            [
                'index' => 0,
                'key'   => 'Id',
                'name'  => 'PlaylistID',
                'show'  => false,
                'width' => 100,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''],
            [
                'index' => 1,
                'key'   => 'Playlist',
                'name'  => 'Playlist-Name',
                'show'  => true,
                'width' => 400,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''],
            [
                'index' => 2,
                'key'   => 'Tracks',
                'name'  => 'Tracks',
                'show'  => true,
                'width' => 50,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''],
            [
                'index' => 3,
                'key'   => 'Duration',
                'name'  => $this->Translate('Duration'),
                'show'  => true,
                'width' => 75,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => '']
        ];
        $NewRowsConfig = [
            [
                'row'     => 'odd',
                'name'    => $this->Translate('odd'),
                'bgcolor' => 0x000000,
                'color'   => 0xffffff,
                'style'   => ''],
            [
                'row'     => 'even',
                'name'    => $this->Translate('even'),
                'bgcolor' => 0x080808,
                'color'   => 0xffffff,
                'style'   => '']
        ];
        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig];
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
        $LMSData = $this->SendDirect(new LMSData(['player', 'count'], '?'));
        if ($LMSData == null) {
            return false;
        }
        $players = $LMSData->Data[0];
        $this->SetValueInteger('Players', $players);
        $Assosiation = [];
        $Assosiation[] = [-2, 'Keiner', '', 0x00ff00];
        $Assosiation[] = [-1, 'Alle', '', 0xff0000];
        for ($i = 0; $i < $players; $i++) {
            $LMSPlayerData = $this->SendDirect(new LMSData(['player', 'name'], [$i, '?']));
            if ($LMSPlayerData == null) {
                continue;
            }
            $PlayerName = $LMSPlayerData->Data[1];
            $Assosiation[] = [$i, $PlayerName, '', -1];
        }
        $this->RegisterProfileIntegerEx('LMS.PlayerSelect' . $this->InstanceID, 'Speaker', '', '', $Assosiation);
        $this->SetValueInteger('PlayerSelect', -2);
        return true;
    }

    private function RefreshAllPlaylists()
    {
        $Data = [0, 100000, 'tags:u'];
        $LMSData = $this->SendDirect(new LMSData('playlists', $Data));
        if ($LMSData === null) {
            return;
        }
        $LMSData->SliceData();
        $Playlists = (new LMSTaggingArray($LMSData->Data))->DataArray();
        foreach ($Playlists as $Key => $Playlist) {
            $Playlists[$Key]['Id'] = $Key;
            $Playlists[$Key]['Tracks'] = 0;
            $Playlists[$Key]['Duration'] = 0;
            $LMSSongData = $this->SendDirect(new LMSData(['playlists', 'tracks'], [0, 100000, 'playlist_id:' . $Key, 'tags:d'], true));
            if ($LMSSongData === null) {
                trigger_error(sprintf($this->Translate('Error read Playlist %d .'), $Key), E_USER_NOTICE);
                $Playlists[$Key]['Playlist'] = $Playlists[$Key]['Playlist'];
                continue;
            }
            $LMSSongData->SliceData();
            //$this->SendDebug('EXTRA', $LMSSongData, 0);
            if (sizeof($LMSSongData->Data) > 0) { // Count sollte eigentlich immer kommen und somit
                $SongInfo = new LMSSongInfo($LMSSongData->Data);
                $Playlists[$Key]['Tracks'] = $SongInfo->CountAllSongs();
                $Playlists[$Key]['Duration'] = $SongInfo->GetTotalDuration();
            }
        }

        uasort($Playlists, [$this, 'SortPlaylistData']);

        $this->Multi_Playlists = $Playlists;
        $this->RefreshPlaylist();
    }

    private function SortPlaylistData($a, $b)
    {
        return strcmp($a['Playlist'], $b['Playlist']);
    }

    /**
     * Erzeugt eine HTML-Tabelle mit allen Playlisten für eine ~HTMLBox-Variable.
     *
     * @access private
     */
    private function RefreshPlaylist()
    {
        if (!$this->ReadPropertyBoolean('showPlaylist')) {
            return;
        }
        $Data = $this->Multi_Playlists;
        if (!is_array($Data)) {
            $Data = [];
        }
        $HTML = $this->GetTable($Data, 'LMSPlaylist', 'Playlist', 'Id');
        $this->SetValueString('Playlists', $HTML);
    }

    ################## Action
    /**
     * Actionhandler der Statusvariablen. Interne SDK-Funktion.
     * @access public
     * @param string $Ident Der Ident der Statusvariable.
     * @param bool|float|int|string $Value Der angeforderte neue Wert.
     */
    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'PlayerSelect':
                $ProfilName = 'LMS.PlayerSelect' . $this->InstanceID;
                $Profil = IPS_GetVariableProfile($ProfilName)['Associations'];
                switch ($Value) {
                    case -2: //keiner
                    case -1: //alle
                        $this->SetValueInteger('PlayerSelect', $Value);

                        for ($i = 2; $i < count($Profil); $i++) {
                            IPS_SetVariableProfileAssociation($ProfilName, $Profil[$i]['Value'], $Profil[$i]['Name'], $Profil[$i]['Icon'], -1);
                        }

                        break;
                    default:
                        $Value = $Value + 2;
                        $Profil[$Value]['Color'] = ($Profil[$Value]['Color'] == -1) ? 0x00ffff : -1;
                        IPS_SetVariableProfileAssociation($ProfilName, $Value - 2, $Profil[$Value]['Name'], $Profil[$Value]['Icon'], $Profil[$Value]['Color']);
                        $this->SetValueInteger('PlayerSelect', -3);
                        break;
                }

                break;
            case 'RescanState':
                if ($Value == 1) {
                    $ret = $this->AbortScan();
                } elseif ($Value == 2) {
                    $ret = $this->Rescan();
                } elseif ($Value == 3) {
                    $ret = $this->RescanPlaylists();
                } elseif ($Value == 4) {
                    $ret = $this->WipeCache();
                }
                if (($Value <> 0) and ( ($ret === null) or ( $ret === false))) {
                    echo $this->Translate('Error on send scanner-command');
                    return false;
                }
                if ($this->GetValue('RescanState') <> $Value) {
                    $this->SetValueInteger('RescanState', $Value);
                }
                break;
            default:
                echo $this->Translate('Invalid Ident');
                break;
        }
    }

    /**
     * Verarbeitet Daten aus dem Webhook.
     *
     * @access protected
     * @param string $ID Die zu ladenen Playlist oder der zu ladene Favorit.
     */
    protected function ProcessHookdata()
    {
        if ((!isset($_GET['ID'])) or ( !isset($_GET['Type'])) or ( !isset($_GET['Secret']))) {
            echo $this->Translate('Bad Request');
            return;
        }
        $CalcSecret = base64_encode(sha1($this->WebHookSecretPlaylist . '0' . $_GET['ID'], true));
        if ($CalcSecret != rawurldecode($_GET['Secret'])) {
            echo $this->Translate('Access denied');
            return;
        }
        $Value = $this->GetValue('PlayerSelect');
        switch ($Value) {
            case -2: //keiner
                echo $this->Translate('No Player selected');
                return;
            case -1: //alle
                $Players = range(0, $this->GetValue('Players') - 1);

                break;
            case -3: // multi
                $ProfilName = 'LMS.PlayerSelect' . $this->InstanceID;
                $Profil = IPS_GetVariableProfile($ProfilName)['Associations'];
                $Players = [];
                for ($i = 2; $i < count($Profil); $i++) {
                    if ($Profil[$i]['Color'] != -1) {
                        $Players[] = $i - 2;
                    }
                }
                break;
            default:
                echo $this->Translate('Unknown Player selected');
                return;
        }
        //Todo auf Sync umbauen
        foreach ($Players as $PlayerId) {
            $Player = $this->GetPlayerInfo($PlayerId);
            if ($Player['Instanceid'] > 0) {
                if ($_GET['Type'] == 'Playlist') {
                    LSQ_LoadPlaylistByPlaylistID($Player['Instanceid'], (int) $_GET['ID']);
                }
                if ($_GET['Type'] == 'Favorite') {
                    LSQ_LoadPlaylistByFavoriteID($Player['Instanceid'], (string) $_GET['ID']);
                }
            }
        }
        echo 'OK';
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
        $Data = new LMSData('listen', '1');
        $ret = @$this->Send($Data);
        if ($ret === null) {
            trigger_error($this->Translate('Error on keepalive to LMS.'), E_USER_NOTICE);
            return false;
        }
        if ($ret->Data[0] == '1') {
            return true;
        }

        trigger_error($this->Translate('Error on keepalive to LMS.'), E_USER_NOTICE);
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_SendSpecial'.
     * Sendet einen Anfrage an den LMS.
     *
     * @access public
     * @param string $Command Das zu sendene Kommando.
     * @param string $Value Die zu sendenden Werte als JSON String.
     * @result array|bool Antwort des LMS als Array, false im Fehlerfall.
     */
    public function SendSpecial(string $Command, string $Value)
    {
        $Data = json_decode($Value, true);
        if ($Data === null) {
            trigger_error($this->Translate('Value ist not valid JSON.'), E_USER_NOTICE);
            return false;
        }
        $LMSData = new LMSData($Command, $Data);
        $ret = $this->SendDirect($LMSData);
        return $ret->Data;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_RestartServer'.
     * @access public
     * @result bool
     */
    public function RestartServer()
    {
        return ($this->Send(new LMSData('restartserver')) <> null);
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
        if ($Ident == '') {
            trigger_error($this->Translate('Invalid Ident'));
            return false;
        }
        switch ($Ident) {
            case 'Players':
                $LMSResponse = new LMSData(['player', 'count'], '?');
                break;
            case 'Version':
                $LMSResponse = new LMSData('version', '?');
                break;
            case 'Playlists':
                return $this->RefreshAllPlaylists();
            default:
                trigger_error($this->Translate('Invalid Ident'));
                return false;
        }
        $LMSResponse = $this->Send($LMSResponse);
        if ($LMSResponse === null) {
            return false;
        }
        return $this->DecodeLMSResponse($LMSResponse);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetAudioDirs'.
     * @access public
     * @result array
     */
    public function GetAudioDirs()
    {
        $LMSData = $this->SendDirect(new LMSData(['pref', 'mediadirs'], '?'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetPlaylistDir'.
     * @access public
     * @result array
     */
    public function GetPlaylistDir()
    {
        $LMSData = $this->SendDirect(new LMSData(['pref', 'playlistdir'], '?'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0];
    }

    ///////////////////////////////////////////////////////////////
    // START TODO
    ///////////////////////////////////////////////////////////////
    /**
     * IPS-Instanz-Funktion 'LMS_GetSyncGroups'.
     * Liefer ein Array welches die Gruppen mit ihren jeweiligen IPS-InstanzeIDs enthält.
     * @access public
     * @result array Array welches so viele Elemente wie Gruppen enthält.
     */
    public function GetSyncGroups()
    {
        $LMSData = $this->SendDirect(new LMSData('syncgroups', '?'));
        if ($LMSData == null) {
            return false;
        }

        if (count($LMSData->Data) == 0) {
            return [];
        }
        $AllPlayerIDs = IPS_GetInstanceListByModuleID('{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}');
        $Addresses = [];
        $ret = [];
        foreach ($AllPlayerIDs as $DeviceID) {
            $Addresses[$DeviceID] = IPS_GetProperty($DeviceID, 'Address');
        }
        $Data = array_chunk($LMSData->Data, 2);
        foreach ($Data as $Group) {
            $FoundInstanzIDs = [];
            $Search = explode(',', (new LMSTaggingData($Group[0]))->Value);
            foreach ($Search as $Value) {
                if (array_search($Value, $Addresses) !== false) {
                    $FoundInstanzIDs[] = array_search($Value, $Addresses);
                }
            }
            if (count($FoundInstanzIDs) > 0) {
                $ret[] = $FoundInstanzIDs;
            }
        }
        return $ret;
    }

    ///////////////////////////////////////////////////////////////
    // ENDE TODO
    ///////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////
    // START PLAYERINFO
    ///////////////////////////////////////////////////////////////
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
        if (!is_int($Index)) {
            trigger_error(sprints($this->Translate('%s must be integer.'), 'Index'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('players', [(string) $Index, '1']));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $ret = (new LMSTaggingArray($LMSData->Data))->DataArray();
        if (!isset($ret['Playerid'])) {
            trigger_error($this->Translate('Invalid index'), E_USER_NOTICE);
            return false;
        }
        $DevicesIDs = IPS_GetInstanceListByModuleID('{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}');
        $FoundId = 0;
        foreach ($DevicesIDs as $Device) {
            if (IPS_GetProperty($Device, 'Address') == $ret['Playerid']) {
                $FoundId = $Device;
            }
        }
        $ret['Instanceid'] = $FoundId;
        unset($ret['Count']);
        return $ret;
    }

    ///////////////////////////////////////////////////////////////
    // START DATABASE
    ///////////////////////////////////////////////////////////////
    /**
     * IPS-Instanz-Funktion 'LMS_Rescan'.
     * Startet einen rescan der Datenbank.
     *
     * @access public
     * @result bool True wenn erfolgreich.
     */
    public function Rescan()
    {
        return ($this->Send(new LMSData('rescan')) <> null);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_RescanPlaylists'.
     * Startet einen rescan der Datenbank für die Playlists.
     *
     * @access public
     * @result bool True wenn erfolgreich.
     */
    public function RescanPlaylists()
    {
        return ($this->Send(new LMSData('rescan', 'playlists')) <> null);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_WipeCache'.
     * Löscht den Cache der DB.
     *
     * @access public
     * @result bool True wenn erfolgreich.
     */
    public function WipeCache()
    {
        return ($this->Send(new LMSData('wipecache')) <> null);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_AbortScan'.
     * Bricht einen Scan der Datenbank ab.
     *
     * @access public
     * @result bool True wenn erfolgreich.
     */
    public function AbortScan()
    {
        return ($this->Send(new LMSData('abortscan')) <> null);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetLibaryInfo'.
     * @access public
     * @result array
     */
    public function GetLibaryInfo()
    {
        $genres = $this->Send(new LMSData(['info', 'total', 'genres'], '?'));
        if ($genres === null) {
            return false;
        }
        $artists = $this->Send(new LMSData(['info', 'total', 'artists'], '?'));
        if ($artists === null) {
            return false;
        }
        $albums = $this->Send(new LMSData(['info', 'total', 'albums'], '?'));
        if ($albums === null) {
            return false;
        }
        $songs = $this->Send(new LMSData(['info', 'total', 'songs'], '?'));
        if ($songs === null) {
            return false;
        }
        $duration = $this->Send(new LMSData(['info', 'total', 'duration'], '?'));
        if ($duration === null) {
            return false;
        }
        $ret = [
            'Genres'   => (int) $genres->Data[0],
            'Artists'  => (int) $artists->Data[0],
            'Albums'   => (int) $albums->Data[0],
            'Songs'    => (int) $songs->Data[0],
            'Duration' => (int) $duration->Data[0]
        ];
        return $ret;
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetGenres'.
     * @access public
     * @result array
     */
    public function GetGenres()
    {
        return $this->GetGenresEx('');
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetGenresEx'.
     * @access public
     * @param $Search Suchstring
     * @result array
     */
    public function GetGenresEx(string $Search)
    {
        if (!is_string($Search)) {
            trigger_error(sprintf($this->Translate('%s must be string'), 'Search'), E_USER_NOTICE);
            return false;
        }
        $Data = [0, 100000];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }

        $LMSData = $this->SendDirect(new LMSData('genres', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Genres = new LMSTaggingArray($LMSData->Data);
        $Genres->Compact('Genre');
        return $Genres->DataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetArtists'.
     * @access public
     * @result array
     */
    public function GetArtists()
    {
        return $this->GetArtistsEx('');
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetArtistsEx'.
     * @access public
     * @param $Search Suchstring
     * @result array
     */
    public function GetArtistsEx(string $Search)
    {
        if (!is_string($Search)) {
            trigger_error(sprintf($this->Translate('%s must be string'), 'Search'), E_USER_NOTICE);
            return false;
        }

        $Data = [0, 100000];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }

        $LMSData = $this->SendDirect(new LMSData('artists', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Artists = new LMSTaggingArray($LMSData->Data);
        $Artists->Compact('Artist');
        return $Artists->DataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetAlbums'.
     * @access public
     * @result array
     */
    public function GetAlbums()
    {
        return $this->GetAlbumsEx('');
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetAlbumsEx'.
     * @access public
     * @param $Search Suchstring
     * @result array
     */
    public function GetAlbumsEx(string $Search)
    {
        if (!is_string($Search)) {
            trigger_error(sprintf($this->Translate('%s must be string'), 'Search'), E_USER_NOTICE);
            return false;
        }

        $Data = [0, 100000];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }
        $LMSData = $this->SendDirect(new LMSData('albums', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Albums = new LMSTaggingArray($LMSData->Data);
        $Albums->Compact('Album');
        return $Albums->DataArray();
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
        if (!is_int($FolderID)) {
            trigger_error(sprintf($this->Translate('%s must be integer'), 'FolderID'), E_USER_NOTICE);
            return false;
        }
        if ($FolderID == 0) {
            $Data = ['0', 100000, 'tags:uc'];
        } else {
            $Data = ['0', 100000, 'tags:uc', 'folder_id:' . $FolderID];
        }
        $LMSData = $this->SendDirect(new LMSData('mediafolder', $Data));
        if ($LMSData == null) {
            return false;
        }
        $LMSData->SliceData();

        return (new LMSTaggingArray($LMSData->Data))->DataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetDirectoryByIDRecursiv'. Liefert rekursiv Informationen zu einem Verzeichnis.
     *
     * @access public
     * @param int $FolderID ID des Verzeichnis welches durchsucht werden soll.
     * @return array|bool Array mit den Quellen oder false bei Fehler.
     */
    public function GetDirectoryByIDRecursiv(int $FolderID)
    {
        if (!is_int($FolderID)) {
            trigger_error(sprintf($this->Translate('%s must be integer'), 'FolderID'), E_USER_NOTICE);
            return false;
        }
        if ($FolderID == 0) {
            trigger_error($this->Translate('Search root recursive is not supported'), E_USER_NOTICE);
            return false;
        } else {
            $Data = ['0', 100000, 'tags:uc', 'folder_id:' . $FolderID];
        }
        $LMSData = $this->SendDirect(new LMSData('mediafolder', $Data));
        if ($LMSData == null) {
            return false;
        }
        $LMSData->SliceData();

        return (new LMSTaggingArray($LMSData->Data))->DataArray();
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
        if (!is_string($Directory)) {
            trigger_error(sprintf($this->Translate('%s must be string'), 'Directory'), E_USER_NOTICE);
            return false;
        }
        if ($Directory == '') {
            $Data = ['0', 100000, 'tags:uc'];
        } else {
            $Data = ['0', 100000, 'tags:uc', 'url:' . $Directory];
        }
        $LMSData = $this->SendDirect(new LMSData('mediafolder', $Data));
        if ($LMSData == null) {
            return false;
        }
        $LMSData->SliceData();

        return (new LMSTaggingArray($LMSData->Data))->DataArray();
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
        if (!is_string($Directory)) {
            trigger_error(sprintf($this->Translate('%s must be string'), 'Directory'), E_USER_NOTICE);
            return false;
        }
        if ($Directory == '') {
            $Data = ['0', 100000, 'recursive:1', 'tags:uc'];
        } else {
            $Data = ['0', 100000, 'recursive:1', 'tags:uc', 'url:' . $Directory];
        }
        $LMSData = $this->SendDirect(new LMSData('mediafolder', $Data));
        if ($LMSData == null) {
            return false;
        }
        $LMSData->SliceData();

        return (new LMSTaggingArray($LMSData->Data))->DataArray();
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetPlaylists'.
     * Liefert alle Server Playlisten.
     * @access public
     * @result array Array mit den Server-Playlists. FALSE im Fehlerfall.
     */
    public function GetPlaylists()
    {
        return $this->GetPlaylistsEx('');
    }

    /**
     * IPS-Instanz-Funktion 'LMS_GetPlaylistsEx'.
     * Liefert alle Server Playlisten.
     * @access public
     * @param string $Search Such-String
     * @result array Array mit den Server-Playlists. FALSE im Fehlerfall.
     */
    public function GetPlaylistsEx(string $Search)
    {
        if (!is_string($Search)) {
            trigger_error(sprintf($this->Translate('%s must be string'), 'Search'), E_USER_NOTICE);
            return false;
        }
        if ($Search != '') {
            $Data = [0, 100000, 'search:' . urlencode($Search), 'tags:u'];
        } else {
            return $this->Multi_Playlists;
        }

        $LMSData = $this->SendDirect(new LMSData('playlists', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Playlists = (new LMSTaggingArray($LMSData->Data))->DataArray();
        foreach ($Playlists as $Key => $Playlist) {
            $Playlists[$Key]['Id'] = $Key;
            $Playlists[$Key]['Tracks'] = 0;
            $Playlists[$Key]['Duration'] = 0;
            $LMSSongData = $this->SendDirect(new LMSData(['playlists', 'tracks'], [0, 100000, 'playlist_id:' . $Key, 'tags:d'], true));
            if ($LMSSongData === null) {
                trigger_error(sprintf($this->Translate('Error read Playlist %d .'), $Key), E_USER_NOTICE);
                $Playlists[$Key]['Playlist'] = $Playlists[$Key]['Playlist'];
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
     * IPS-Instanz-Funktion 'LMS_GetPlaylist'.
     * Liefert alle Songs einer Playlist.
     * @access public
     * @param int $PlaylistId Die Playlist welche gelesen werden soll.
     * @result array Array mit Songs der Playlist.
     */
    public function GetPlaylist(int $PlaylistId)
    {
        if (!is_int($PlaylistId)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'PlaylistId'), E_USER_NOTICE);
            return false;
        }
        $LMSSongData = $this->SendDirect(new LMSData(['playlists', 'tracks'], [0, 10000, 'playlist_id:' . $PlaylistId, 'tags:gladiqrRtueJINpsy'], true));
        if ($LMSSongData === null) {
            return false;
        }
        $LMSSongData->SliceData();
        $SongInfo = new LMSSongInfo($LMSSongData->Data);
        return $SongInfo->GetAllSongs();
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
        return $this->RenamePlaylistEx($PlaylistId, $Name, false);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_RenamePlaylistEx'.
     * Benennt eine Playlist um.
     * @access public
     * @param int $PlaylistId Die ID der Playlist welche umbenannt werden soll.
     * @param string $Name Der neue Name der Playlist.
     * @param bool $Overwrite True wenn eine eventuell vorhandene Playlist überschrieben werden soll.
     * @result bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function RenamePlaylistEx(int $PlaylistId, string $Name, bool $Overwrite)
    {
        if (!is_int($PlaylistId)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'PlaylistId'), E_USER_NOTICE);
            return false;
        }
        if (!is_string($Name)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'Name'), E_USER_NOTICE);
            return false;
        }
        if (!$Overwrite) {
            $LMSData = $this->SendDirect(new LMSData(['playlists', 'rename'], ['playlist_id:' . $PlaylistId, 'newname:' . $Name, 'dry_run:1']));
            if ($LMSData === null) {
                return false;
            }
            $LMSData->SliceData();
            if (count($LMSData->Data) > 0) {
                if ((new LMSTaggingData($LMSData->Data[0]))->Value == $PlaylistId) {
                    return true;
                }
                trigger_error(sprintf($this->Translate('Error rename Playlist. Name already used by Playlist %s'), (new LMSTaggingData($LMSData->Data[0]))->Value), E_USER_NOTICE);
                return false;
            }
        }
        $LMSData = $this->SendDirect(new LMSData(['playlists', 'rename'], ['playlist_id:' . $PlaylistId, 'newname:' . $Name, 'dry_run:0']));
        if ($LMSData === null) {
            return false;
        }
        return true;
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
        if (!is_string($Name)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'Name'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlists', 'new'], 'name:' . $Name));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        if (strpos($LMSData->Data[0], 'playlist_id') === 0) {
            return (int) (new LMSTaggingData($LMSData->Data[0]))->Value;
        }
        trigger_error($this->Translate('Playlist already exists.'), E_USER_NOTICE);
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
        if (!is_int($PlaylistId)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'PlaylistId'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlists', 'delete'], 'playlist_id:' . $PlaylistId));
        if ($LMSData === null) {
            return false;
        }
        if (strpos($LMSData->Data[0], 'playlist_id') === 0) {
            return $PlaylistId === (int) (new LMSTaggingData($LMSData->Data[0]))->Value;
        }
        trigger_error($this->Translate('Error deleting Playlist.'), E_USER_NOTICE);
        return false;
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
     * @access public
     * @param int $PlaylistId Die ID der Playlist zu welcher ein Song hinzugefügt wird.
     * @param string $SongURL Die URL des Song.
     * @param int $Position Die Position (1 = 1.Eintrag) an welcher der Song eingefügt wird.
     * @result bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function AddSongToPlaylistEx(int $PlaylistId, string $SongURL, int $Position)
    {
        if (!is_int($PlaylistId)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'PlaylistId'), E_USER_NOTICE);
            return false;
        }
        if ($this->GetValidSongURL($SongURL) == false) {
            return false;
        }
        if (!is_int($Position)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'Position'), E_USER_NOTICE);
            return false;
        }

        $LMSData = $this->SendDirect(new LMSData(['playlists', 'edit'], ['cmd:add', 'playlist_id:' . $PlaylistId, 'url:' . $SongURL]));
        if ($LMSData === null) {
            return false;
        }

        if (strpos($LMSData->Data[2], 'url') === 0) {
            if ($SongURL != (new LMSTaggingData($LMSData->Data[2]))->Value) {
                trigger_error($this->Translate('Error on add SongURL to playlist.'), E_USER_NOTICE);
                return false;
            }
        }

        if ($Position == -1) {
            return true;
        }

        $LMSSongData = $this->SendDirect(new LMSData(['playlists', 'tracks'], [0, 10000, 'playlist_id:' . $PlaylistId, 'tags:'], true));
        if ($LMSSongData === null) {
            trigger_error($this->Translate('Error on move Song after adding to playlist.'), E_USER_NOTICE);
            return false;
        }

        $OldPosition = new LMSTaggingData(array_pop($LMSSongData->Data));
        if ($OldPosition->Name <> 'count') {
            trigger_error($this->Translate('Error on move Song after adding to playlist.'), E_USER_NOTICE);
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
        if (!is_int($PlaylistId)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'PlaylistId'), E_USER_NOTICE);
            return false;
        }
        if (!is_int($Position)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'Position'), E_USER_NOTICE);
            return false;
        }
        $Position--;
        $LMSData = $this->SendDirect(new LMSData(['playlists', 'edit'], ['cmd:delete', 'playlist_id:' . $PlaylistId, 'index:' . $Position]));
        if ($LMSData == null) {
            return false;
        }
        $LMSData->SliceData();
        if (count($LMSData->Data) > 0) {
            trigger_error($this->Translate('Error delete song from playlist.'), E_USER_NOTICE);
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
        if (!is_int($PlaylistId)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'PlaylistId'), E_USER_NOTICE);
            return false;
        }
        if (!is_int($Position)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'Position'), E_USER_NOTICE);
            return false;
        }
        $Position--;
        if (!is_int($NewPosition)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'NewPosition'), E_USER_NOTICE);
            return false;
        }
        $NewPosition--;
        $LMSData = $this->SendDirect(new LMSData(['playlists', 'edit'], ['cmd:move', 'playlist_id:' . $PlaylistId, 'index:' . $Position, 'toindex:' . $NewPosition]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        if (count($LMSData->Data) > 0) {
            trigger_error($this->Translate('Error on move Song in playlist.'), E_USER_NOTICE);
            return false;
        }
        return true;
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
        if (!is_int($SongID)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'SongID'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('songinfo', ['0', '20', 'track_id:' . $SongID, 'tags:gladiqrRtueJINpsy']));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = (new LMSTaggingArray($LMSData->Data))->DataArray();
        if (!array_key_exists($SongID, $SongInfo)) {
            trigger_error($this->Translate('SongID not valid.'), E_USER_NOTICE);
            return false;
        }
        $SongInfo[$SongID]['Id'] = $SongID;
        return $SongInfo[$SongID];
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
        if ($this->GetValidSongURL($SongURL) == false) {
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('songinfo', ['0', '20', 'url:' . $SongURL, 'tags:gladiqrRtueJINpsy']));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = (new LMSTaggingArray($LMSData->Data))->DataArray();
        if (count($SongInfo) == 0) {
            trigger_error($this->Translate('SongURL not found.'), E_USER_NOTICE);
            return false;
        }
        $SongInfo[key($SongInfo)]['Track_id'] = key($SongInfo);
        return array_shift($SongInfo);
    }

    //
    //////    DOKU TODO AFTER HERE
    //
    public function GetSongsByGenre(int $GenreId)
    {
        return $this->GetSongsByGenreEx($GenreId, '');
    }

    public function GetSongsByGenreEx(int $GenreId, string $Search)
    {
        if (!is_int($GenreId)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'GenreId'), E_USER_NOTICE);
            return false;
        }
        if (!is_string($Search)) {
            trigger_error(sprintf($this->Translate('%s must be string'), 'Search'), E_USER_NOTICE);
            return false;
        }
        $Data = [0, 100000, 'tags:gladiqrRtueJINpsy', 'genre_id:' . $GenreId];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }

        $LMSData = $this->SendDirect(new LMSData('songs', $Data));
        //'tags:gladiqrRtueJINpsy'
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = new LMSSongInfo($LMSData->Data);
        return $SongInfo->GetAllSongs();
    }

    public function GetSongsByArtist(int $ArtistId)
    {
        return $this->GetSongsByArtistEx($ArtistId, '');
    }

    public function GetSongsByArtistEx(int $ArtistId, string $Search)
    {
        if (!is_int($ArtistId)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'ArtistId'), E_USER_NOTICE);
            return false;
        }
        if (!is_string($Search)) {
            trigger_error(sprintf($this->Translate('%s must be string'), 'Search'), E_USER_NOTICE);
            return false;
        }
        $Data = [0, 100000, 'tags:gladiqrRtueJINpsy', 'artist_id:' . $ArtistId];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }

        $LMSData = $this->SendDirect(new LMSData('songs', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = new LMSSongInfo($LMSData->Data);
        return $SongInfo->GetAllSongs();
    }

    public function GetSongsByAlbum(int $AlbumId)
    {
        return $this->GetSongsByAlbumEx($AlbumId, '');
    }

    public function GetSongsByAlbumEx(int $AlbumId, string $Search)
    {
        if (!is_int($AlbumId)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'AlbumId'), E_USER_NOTICE);
            return false;
        }
        if (!is_string($Search)) {
            trigger_error(sprintf($this->Translate('%s must be string'), 'Search'), E_USER_NOTICE);
            return false;
        }
        $Data = [0, 100000, 'tags:gladiqrRtueJINpsy', 'artist_id:' . $AlbumId];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }

        $LMSData = $this->SendDirect(new LMSData('songs', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = new LMSSongInfo($LMSData->Data);
        return $SongInfo->GetAllSongs();
    }

    public function Search(string $Value)
    {
        if (!is_string($Value)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'Value'), E_USER_NOTICE);
            return false;
        }
        if ($Value == '') {
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Value'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('search', [0, 100000, 'term:' . rawurlencode($Value)]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Result = [];
        $Result['Contributors'] = (new LMSTaggingArray($LMSData->Data, 'contributor_id', 'contributor'))->DataArray();
        $Result['Tracks'] = (new LMSTaggingArray($LMSData->Data, 'track_id', 'track'))->DataArray();
        $Result['Albums'] = (new LMSTaggingArray($LMSData->Data, 'album_id', 'album'))->DataArray();
        return $Result;
    }

    ///////////////////////////////////////////////////////////////
    // START ALARM PLAYLISTS COMMANDS
    ///////////////////////////////////////////////////////////////
    /**
     * IPS-Instanz-Funktion 'LMS_GetAlarmPlaylists'.
     * Liefert alle Playlisten welche für den Wecker genutzt werden können.
     * @access public
     * @result array Array mit den Server-Playlists. FALSE im Fehlerfall.
     */
    public function GetAlarmPlaylists()
    {
        $LMSData = $this->SendDirect(new LMSData(['alarm', 'playlists'], [0, 100000]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        return (new LMSTaggingArray($LMSData->Data, 'category'))->DataArray();
    }

    ///////////////////////////////////////////////////////////////
    // ENDE ALARM PLAYLISTS COMMANDS
    ///////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////
    // START FAVORITEN
    ///////////////////////////////////////////////////////////////
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
        if (!is_string($FavoriteID)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'FavoriteID'), E_USER_NOTICE);
            return false;
        }
        if ($FavoriteID == '') {
            $Data = [0, 100000, 'want_url:1', 'item_id:.'];
        } else {
            $Data = [0, 100000, 'want_url:1', 'item_id:' . rawurlencode($FavoriteID)];
        }
        $LMSData = $this->SendDirect(new LMSData(['favorites', 'items'], $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        array_shift($LMSData->Data);
        return (new LMSTaggingArray($LMSData->Data))->DataArray();
    }

    /**
     *  IPS-Instanz-Funktion 'LMS_AddFavorite'.
     * @access public
     * @param string $ParentFavoriteID
     * @param string $Title
     * @param string $URL
     * @return bool
     */
    public function AddFavorite(string $ParentFavoriteID, string $Title, string $URL)
    {
        if (!is_string($ParentFavoriteID)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'ParentFavoriteID'), E_USER_NOTICE);
            return false;
        }
        if (!is_string($Title)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'Title'), E_USER_NOTICE);
            return false;
        }
        if ($this->GetValidSongURL($URL) == false) {
            return false;
        }

        if ($ParentFavoriteID == '') {
            $ParentFavoriteID = '10000';
        } else {
            $ParentFavoriteID .= '.10000';
        }

        $LMSData = $this->SendDirect(new LMSData(['favorites', 'add'], ['item_id:' . $ParentFavoriteID, 'title:' . $Title, 'url:' . rawurlencode($URL)]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        return ($LMSData->Data[0] == 'count:1');
    }

    /**
     * IPS-Instanz-Funktion 'LMS_AddFavoriteLevel'.
     * Erzeugt eine neue Ebene unterhalb $ParentFavoriteID mit dem Namen von $Title.
     *
     * @access public
     * @param string $ParentFavoriteID Die ID des Favoriten unter dem die neue Ebene erzeugt wird. '0' oder '' = root '3' = ID 3 '3.0' => ID 3.0
     * @param string $Title Der Name der neuen Ebene.
     * @result bool TRUE bei Erfolg, sonst FALSE.
     */
    public function AddFavoriteLevel(string $ParentFavoriteID, string $Title)
    {
        if (!is_string($ParentFavoriteID)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'ParentFavoriteID'), E_USER_NOTICE);
            return false;
        }
        if (!is_string($Title)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'Title'), E_USER_NOTICE);
            return false;
        }
        if ($ParentFavoriteID == '') {
            $ParentFavoriteID = '10000';
        } else {
            $ParentFavoriteID .= '.10000';
        }

        $Data = ['item_id:' . $ParentFavoriteID, 'title:' . $Title];
        $LMSData = $this->SendDirect(new LMSData(['favorites', 'addlevel'], $Data));
        if ($LMSData === null) {
            return false;
        }

        $LMSData->SliceData();

        return ($LMSData->Data[0] == 'count:1');
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
        if (!is_string($FavoriteID)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'FavoriteID'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['favorites', 'delete'], 'item_id:' . $FavoriteID));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();

        return (count($LMSData->Data) == 0);
    }

    /**
     * IPS-Instanz-Funktion 'LMS_RenameFavorite'.
     * Benennt eine Favoriten um.
     *
     * @access public
     * @param string $FavoriteID Die ID des Favoriten welcher umbenannt werden soll.
     * @param string $Title Der neue Name des Favoriten.
     * @result bool TRUE bei Erfolg, sonst FALSE.
     */
    public function RenameFavorite(string $FavoriteID, string $Title)
    {
        if (!is_string($FavoriteID)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'FavoriteID'), E_USER_NOTICE);
            return false;
        }
        if (!is_string($Title)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'Title'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['favorites', 'rename'], ['item_id:' . $FavoriteID, 'title:' . $Title]));
        if ($LMSData === null) {
            return false;
        }
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
        if (!is_string($FavoriteID)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'FavoriteID'), E_USER_NOTICE);
            return false;
        }
        if (!is_string($NewParentFavoriteID)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'NewParentFavoriteID'), E_USER_NOTICE);
            return false;
        }
        if ($NewParentFavoriteID == '') {
            $NewParentFavoriteID = '10000';
        } else {
            $NewParentFavoriteID .= '.10000';
        }

        $LMSData = $this->SendDirect(new LMSData(['favorites', 'move'], ['from_id:' . $FavoriteID, 'to_id:' . $NewParentFavoriteID]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();

        return (count($LMSData->Data) == 0);
    }

    // TODO ab Hier !

    public function ExistsUrlInFavorite(string $URL)
    {
        if (!is_string($URL)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'URL'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['favorites', 'exists'], $URL));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Result = (new LMSTaggingArray($LMSData->Data))->DataArray();
        return $Result;
    }

    public function ExistsIdInFavorite(int $ID)
    {
        if (!is_int($ID)) {
            trigger_error(sprintf($this->Translate('%s must be integer.'), 'ID'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['favorites', 'exists'], [$ID]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Result = (new LMSTaggingArray($LMSData->Data))->DataArray();
        return $Result;
    }

    ///////////////////////////////////////////////////////////////
    // ENDE FAVORITEN
    ///////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////
    // START Plugins Radio & Apps
    ///////////////////////////////////////////////////////////////
    public function GetRadios()
    {
        $LMSData = $this->SendDirect(new LMSData('radios', [0, 100000]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        return (new LMSTaggingArray($LMSData->Data, 'icon'))->DataArray();
    }

    public function GetApps()
    {
        $LMSData = $this->SendDirect(new LMSData('apps', [0, 100000]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        return (new LMSTaggingArray($LMSData->Data, 'icon'))->DataArray();
    }

    public function GetRadioOrAppData(string $Cmd, string $FolderID)
    {
        return $this->GetRadioOrAppDataEx($Cmd, $FolderID, '');
    }

    public function GetRadioOrAppDataEx(string $Cmd, string $FolderID, string $Search)
    {
        if (!is_string($Cmd)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'DataID'), E_USER_NOTICE);
            return false;
        }
        if (!is_string($FolderID)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'FolderID'), E_USER_NOTICE);
            return false;
        }
        if (!is_string($Search)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'Search'), E_USER_NOTICE);
            return false;
        }
        $Data = [0, 100000, 'want_url:1'];
        if ($FolderID == '') {
            $Data[] = 'item_id:.';
        } else {
            $Data[] = 'item_id:' . rawurlencode($FolderID);
        }

        if ($Search != '') {
            $Data[] = 'search:' . rawurlencode($Search);
        }

        $LMSData = $this->SendDirect(new LMSData([$Cmd, 'items'], $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        array_shift($LMSData->Data);
        return (new LMSTaggingArray($LMSData->Data))->DataArray();
    }

    ///////////////////////////////////////////////////////////////
    // ENDE Plugins Radio & Apps
    ///////////////////////////////////////////////////////////////
    ################## Decode Data

    private function DecodeLMSResponse(LMSData $LMSData)
    {
        if ($LMSData == null) {
            return false;
        }
        $this->SendDebug('Decode', $LMSData, 0);
        switch ($LMSData->Command[0]) {
            case 'listen':
                return true;
            case 'scanner':
                switch ($LMSData->Data[0]) {
                    case 'notify':
                        $Data = new LMSTaggingData($LMSData->Data[1]);
//                        $this->SendDebug('scanner', $Data, 0);
                        switch ($Data->Name) {
                            case 'end':
                            case 'exit':
                                $this->SetValueString('RescanInfo', '');
                                $this->SetValueString('RescanProgress', '');
                                return true;
                            case 'progress':
                                $Info = explode('||', $Data->Value);
                                $StepInfo = $Info[2];
                                if (strpos($StepInfo, '|')) {
                                    $StepInfo = explode('|', $StepInfo)[1];
                                }
                                $this->SetValueString('RescanInfo', $StepInfo);
                                $StepProgress = $Info[3] . ' von ' . $Info[4];
                                $this->SetValueString('RescanProgress', $StepProgress);
                                return true;
                        }
                        break;
                }
                break;
            case 'wipecache':
                if ($this->GetValue('RescanState') <> 4) {
                    $this->SetValueInteger('RescanState', 4); // Vollständig
                }
                return true;
            case 'player':
                if ($LMSData->Command[1] == 'count') {
                    $this->SetValueInteger('Players', (int) $LMSData->Data[0]);
                    return true;
                }
                break;
            case 'version':
                $this->SetValueString('Version', $LMSData->Data[0]);
                return true;
            case 'rescan':
                if (!isset($LMSData->Data[0])) {
                    if ($this->GetValue('RescanState') <> 2) {
                        $this->SetValueInteger('RescanState', 2); // einfacher
                    }
                    return true;
                } else {
                    if (($LMSData->Data[0] == 'done') or ( $LMSData->Data[0] == '0')) {
                        if ($this->GetValue('RescanState') <> 0) {
                            $this->SetValueInteger('RescanState', 0);   // fertig
                        }
                        return true;
                    } elseif ($LMSData->Data[0] == 'playlists') {
                        if ($this->GetValue('RescanState') <> 3) {
                            $this->SetValueInteger('RescanState', 3); // Playlists
                        }
                        return true;
                    } elseif ($LMSData->Data[0] == '1') {
                        //start
                        if ($this->GetValue('RescanState') <> 2) {
                            $this->SetValueInteger('RescanState', 2); // einfacher
                        }
                        return true;
                    }
                }
                break;
            case 'playlists':
                if (count($LMSData->Command) > 1) {
                    $PlaylistData = (new LMSTaggingArray($LMSData->Data))->DataArray();
                    if (!array_key_exists('Playlist_id', $PlaylistData)) {
                        return;
                    }
                    $Playlists = $this->Multi_Playlists;

                    switch ($LMSData->Command[1]) {
                        case 'rename':
                            if (!array_key_exists('Overwritten_playlist_id', $PlaylistData)) {
                                $Playlists[$PlaylistData['Playlist_id']]['Playlist'] = $PlaylistData['Newname'];
                            }
                            break;
                        case 'delete':
                            unset($Playlists[$PlaylistData['Playlist_id']]);
                            break;
                        case 'new':
                            $Playlists[$PlaylistData['Playlist_id']]['Playlist'] = $PlaylistData['Name'];
                            $Playlists[$PlaylistData['Playlist_id']]['Id'] = $PlaylistData['Playlist_id'];
                            $LMSSongData = $this->SendDirect(new LMSData('songinfo', ['0', '20', 'track_id:' . $PlaylistData['Playlist_id'], 'tags:u']));
                            if ($LMSSongData === null) {
                                trigger_error(sprintf($this->Translate('Error read Playlist %d .'), $PlaylistData['Playlist_id']), E_USER_NOTICE);
                                $Playlists[$PlaylistData['Playlist_id']]['Url'] = '';
                            } else {
                                $LMSSongData->SliceData();
                                $SongInfo = (new LMSTaggingArray($LMSSongData->Data))->DataArray();
                                if (!array_key_exists($PlaylistData['Playlist_id'], $SongInfo)) {
                                    $Playlists[$PlaylistData['Playlist_id']]['Url'] = '';
                                } else {
                                    $Playlists[$PlaylistData['Playlist_id']]['Url'] = $SongInfo[$PlaylistData['Playlist_id']]['Url'];
                                }
                            }

                        case 'edit':
                            $LMSSongData = $this->SendDirect(new LMSData(['playlists', 'tracks'], [0, 100000, 'playlist_id:' . $PlaylistData['Playlist_id'], 'tags:d'], true));
                            if ($LMSSongData === null) {
                                trigger_error(sprintf($this->Translate('Error read Playlist %d .'), $PlaylistData['Playlist_id']), E_USER_NOTICE);
                                $Playlists[$PlaylistData['Playlist_id']]['Tracks'] = 0;
                                $Playlists[$PlaylistData['Playlist_id']]['Duration'] = 0;
                            } else {
                                $LMSSongData->SliceData();
                                $SongInfo = new LMSSongInfo($LMSSongData->Data);
                                $Playlists[$PlaylistData['Playlist_id']]['Tracks'] = $SongInfo->CountAllSongs();
                                $Playlists[$PlaylistData['Playlist_id']]['Duration'] = $SongInfo->GetTotalDuration();
                            }

                            break;
                    }
                    uasort($Playlists, [$this, 'SortPlaylistData']);
                    $this->Multi_Playlists = $Playlists;
                    $this->RefreshPlaylist();
                    return true;
                }
                break;
            case 'playlist': // Client Playlist info
                if ($LMSData->Command[1] == 'save') {
                    $this->RefreshAllPlaylists();
                    return true;
                }
                break;
            case 'favorites':
                // TODO
                if (count($LMSData->Command) > 1) {
                    if (in_array($LMSData->Command[1], ['addlevel', 'rename', 'delete', 'move', 'changed'])) {
                        //mediafolder
                        //$this->RefreshFavoriteslist();
                        /*
                         */
                        break;
                    }
                }
            case 'songinfo':
                //TODO ???
                break;
            default:
                //IPS_LogMessage('unhandled Decode LMS', print_r($LMSData, true));
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
        if (!is_null($ret)) {
            return serialize($ret);
        }

        return false;
    }

    /**
     * Sendet LSQData an die Childs.
     *
     * @access private
     * @param LMSResponse $LMSResponse Ein LMSResponse-Objekt.
     */
    private function SendDataToDevice(LMSResponse $LMSResponse)
    {
        $Data = $LMSResponse->ToJSONStringForDevice('{CB5950B3-593C-4126-9F0F-8655A3944419}');
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
        foreach ($packet as $part) {
            $part = trim($part);
            $Data = new LMSResponse($part);
            try {
                $isResponse = $this->SendQueueUpdate($Data);
            } catch (Exception $exc) {
                $buffer = $this->Buffer;
                $this->Buffer = $part . chr(0x0d) . $buffer;
                trigger_error($exc->getMessage(), E_USER_NOTICE);
                continue;
            }
            if ($Data->Command[0] == 'client') { // Client änderungen auch hier verarbeiten!
                $this->RefreshPlayerList();
            }

            if ($isResponse === false) { //War keine Antwort also ein Event
                $this->SendDebug('LMS_Event', $Data, 0);
                if ($Data->Device != LMSResponse::isServer) {
                    if ($Data->Command[0] == 'playlist') {
                        $this->DecodeLMSResponse($Data);
                    }
                    $this->SendDataToDevice($Data);
                } else {
                    $this->DecodeLMSResponse($Data);
                }
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
        try {
            if (IPS_GetInstance($this->InstanceID)['InstanceStatus'] != IS_ACTIVE) {
                throw new Exception($this->Translate('Instance inactiv.'), E_USER_NOTICE);
            }

            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }

            if ($LMSData->needResponse) {
                $this->SendDebug('Send', $LMSData, 0);
                $this->SendQueuePush($LMSData);
                $this->SendDataToParent($LMSData->ToJSONStringForLMS('{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}'));
                $ReplyDataArray = $this->WaitForResponse($LMSData);

                if ($ReplyDataArray === false) {
                    throw new Exception($this->Translate('No anwser from LMS'), E_USER_NOTICE);
                }

                $LMSData->Data = $ReplyDataArray;
                $this->SendDebug('Response', $LMSData, 0);
                return $LMSData;
            } else { // ohne Response, also ohne warten raussenden,
                $this->SendDebug('SendFaF', $LMSData, 0);
                return $this->SendDataToParent($LMSData->ToJSONStringForLMS('{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}'));
            }
        } catch (Exception $exc) {
            trigger_error($exc->getMessage(), $exc->getCode());
            return null;
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
        try {
            if (IPS_GetInstance($this->InstanceID)['InstanceStatus'] != IS_ACTIVE) {
                throw new Exception($this->Translate('Instance inactiv.'), E_USER_NOTICE);
            }

            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }

            if ($this->Host === '') {
                return null;
            }

            $this->SendDebug('Send Direct', $LMSData, 0);

            if (!$this->Socket) {
                $Port = $this->ReadPropertyInteger('Port');
                $User = $this->ReadPropertyString('User');
                $Pass = $this->ReadPropertyString('Password');

                $LoginData = (new LMSData('login', [$User, $Pass]))->ToRawStringForLMS();
                $this->SendDebug('Send Direct', $LoginData, 0);
                $this->Socket = @stream_socket_client('tcp://' . $this->Host . ':' . $Port, $errno, $errstr, 1);
                if (!$this->Socket) {
                    throw new Exception($this->Translate('No anwser from LMS'), E_USER_NOTICE);
                }
                stream_set_timeout($this->Socket, 5);
                fwrite($this->Socket, $LoginData);
                $anwserlogin = stream_get_line($this->Socket, 1024 * 1024 * 2, chr(0x0d));
                $this->SendDebug('Response Direct', $anwserlogin, 0);
                if ($anwserlogin === false) {
                    throw new Exception($this->Translate('No anwser from LMS'), E_USER_NOTICE);
                }
            }

            $Data = $LMSData->ToRawStringForLMS();
            $this->SendDebug('Send Direct', $Data, 0);
            fwrite($this->Socket, $Data);
            $anwser = stream_get_line($this->Socket, 1024 * 1024 * 2, chr(0x0d));
            $this->SendDebug('Response Direct', $anwser, 0);
            if ($anwser === false) {
                throw new Exception($this->Translate('No anwser from LMS'), E_USER_NOTICE);
            }

            $ReplyData = new LMSResponse($anwser);
            $LMSData->Data = $ReplyData->Data;
            $this->SendDebug('Response Direct', $LMSData, 0);
            return $LMSData;
        } catch (Exception $ex) {
            $this->SendDebug('Response Direct', $ex->getMessage(), 0);
            trigger_error($ex->getMessage(), $ex->getCode());
        }
        return null;
    }

    private function CheckLogin()
    {
        if ($this->Host === '') {
            return false;
        }

        $Port = $this->ReadPropertyInteger('Port');
        $User = $this->ReadPropertyString('User');
        $Pass = $this->ReadPropertyString('Password');

        $LoginData = (new LMSData('login', [$User, $Pass]))->ToRawStringForLMS();
        $CheckData = (new LMSData('can', 'login'))->ToRawStringForLMS();
        try {
            $fp = @stream_socket_client('tcp://' . $this->Host . ':' . $Port, $errno, $errstr, 2);
            if (!$fp) {
                $this->SendDebug('no socket', $errstr, 0);
                throw new Exception($this->Translate('No anwser from LMS') . ' ' . $errstr, E_USER_NOTICE);
            } else {
                stream_set_timeout($fp, 5);
                $this->SendDebug('Check login', $LoginData, 0);
                fwrite($fp, $LoginData);
                $anwserlogin = stream_get_line($fp, 1024 * 1024 * 2, chr(0x0d));
                $this->SendDebug('Receive login', $anwserlogin, 0);
                $this->SendDebug('Connection check', $CheckData, 0);
                fwrite($fp, $CheckData);
                $anwser = stream_get_line($fp, 1024 * 1024 * 2, chr(0x0d));
                fclose($fp);
                $this->SendDebug('Receive check', $anwser, 0);
            }
            if ($anwserlogin === false) {
                throw new Exception($this->Translate('No anwser from LMS'), E_USER_NOTICE);
            }
            if ($anwser === false) {
                throw new Exception($this->Translate('No anwser from LMS'), E_USER_NOTICE);
            }
        } catch (Exception $ex) {
            echo $ex->getMessage();
            return false;
        }
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
        for ($i = 0; $i < 1000; $i++) {
            $Buffer = $this->ReplyLMSData;
            if (!array_key_exists($SearchPatter, $Buffer)) {
                return false;
            }
            if (array_key_exists('Data', $Buffer[$SearchPatter])) {
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
        if (!$this->lock('ReplyLMSData')) {
            throw new Exception($this->Translate('ReplyLMSData is locked'), E_USER_NOTICE);
        }
        $data = $this->ReplyLMSData;
        $data[$LMSData->GetSearchPatter()] = [];
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
        if (!$this->lock('ReplyLMSData')) {
            throw new Exception($this->Translate('ReplyLMSData is locked'), E_USER_NOTICE);
        }
//        if (is_array($LMSResponse->Command))
        $key = $LMSResponse->GetSearchPatter(); //Address . implode('', $LMSResponse->Command);
        //$this->SendDebug('SendQueueUpdate', $key, 0);
//        else
//            $key = $LMSResponse->Address . $LMSResponse->Command;
        $data = $this->ReplyLMSData;
        if (array_key_exists($key, $data)) {
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
        if (!$this->lock('ReplyLMSData')) {
            throw new Exception($this->Translate('ReplyLMSData is locked'), E_USER_NOTICE);
        }
        $data = $this->ReplyLMSData;
        unset($data[$Index]);
        $this->ReplyLMSData = $data;
        $this->unlock('ReplyLMSData');
    }

    /**
     *  Konvertiert Sekunden in einen lesbare Zeit.
     * @param int $Time Zeit in Sekunden
     * @return string Zeit als String.
     */
    protected function ConvertSeconds(int $Time)
    {
        if ($Time > 3600) {
            return sprintf('%02d:%02d:%02d', ($Time / 3600), ($Time / 60 % 60), $Time % 60);
        } else {
            return sprintf('%02d:%02d', ($Time / 60 % 60), $Time % 60);
        }
    }

}

/** @} */
