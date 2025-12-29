<?php

declare(strict_types=1);

/**
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.10
 *
 */

require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen
require_once __DIR__ . '/../libs/SqueezeBoxClass.php';  // diverse Klassen
eval('declare(strict_types=1);namespace LyrionMusicServer {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace LyrionMusicServer {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace LyrionMusicServer {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace LyrionMusicServer {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace LyrionMusicServer {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');

/**
 * LyrionMusicServer Klasse für die Kommunikation mit dem Lyrion Media-Server (LMS).
 * Erweitert IPSModule.
 *
 * @todo          Favoriten als Tabelle oder Baum ?! für das WF
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.10
 *
 * @property array $ReplyLMSData \SqueezeBox\LMSData Enthält die versendeten Befehle und speichert die Antworten.
 * @property string $Buffer EmpfangsBuffer
 * @property string $Host Adresse des LMS (aus IO-Parent ausgelesen)
 * @property int $ParentID Die InstanzeID des IO-Parent
 * @property array $Multi_Playlists Alle Datensätze der Playlisten
 * @property int $ScannerID VariablenID des Scanner State
 * @property string $WebHookSecretPlaylist
 * @property array $SelectedPlayers
 * @property resource|bool $Socket
 *
 * @method bool lock(string $ident)
 * @method void unlock(string $ident)
 * @method bool RegisterHook(string $WebHook)
 * @method void SetValueBoolean(string $Ident, bool $value)
 * @method void SetValueFloat(string $Ident, float $value)
 * @method void SetValueInteger(string $Ident, int $value)
 * @method void SetValueString(string $Ident, string $value)
 * @method void UnregisterProfile(string $Name)
 * @method int FindIDForIdent(string $Ident)
 * @method bool IORequestAction(string $Ident, mixed $Value)
 * @method void IOMessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data)
 * @method int IORegisterParent()
 */
class LyrionMusicServer extends IPSModuleStrict
{
    use \SqueezeBox\LMSSocket,
        \SqueezeBox\LMSHTMLTable,
        \SqueezeBox\LMSSongURL,
        \SqueezeBox\DebugHelper,
        \LyrionMusicServer\VariableProfileHelper,
        \LyrionMusicServer\VariableHelper,
        \LyrionMusicServer\BufferHelper,
        \LyrionMusicServer\InstanceStatus,
        \LyrionMusicServer\Semaphore{
            \LyrionMusicServer\InstanceStatus::MessageSink as IOMessageSink; // MessageSink gibt es sowohl hier in der Klasse, als auch im Trait InstanceStatus. Hier wird für die Methode im Trait ein Alias benannt.
            \LyrionMusicServer\InstanceStatus::RegisterParent as IORegisterParent;
            \LyrionMusicServer\InstanceStatus::RequestAction as IORequestAction;
        }

    /**
     * Socket
     *
     * @var resource|bool
     */
    private $Socket = false;

    /**
     * Create
     *
     * @return void
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\SqueezeBox\Splitter\Property::Username, '');
        $this->RegisterPropertyString(\SqueezeBox\Splitter\Property::Password, '');
        $this->RegisterPropertyInteger(\SqueezeBox\Splitter\Property::Port, 9090);
        $this->RegisterPropertyInteger(\SqueezeBox\Splitter\Property::Webport, 9000);
        $this->RegisterPropertyBoolean(\SqueezeBox\Splitter\Property::ShowHTMLPlaylist, false);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString(\SqueezeBox\Splitter\Property::Table, json_encode($Style['Table']));
        $this->RegisterPropertyString(\SqueezeBox\Splitter\Property::Columns, json_encode($Style['Columns']));
        $this->RegisterPropertyString(\SqueezeBox\Splitter\Property::Rows, json_encode($Style['Rows']));
        $this->RegisterTimer(\SqueezeBox\Splitter\Timer::KeepAlive, 0, 'LMS_KeepAlive($_IPS["TARGET"]);');
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
        }
        $this->ReplyLMSData = [];
        $this->Buffer = '';
        $this->Multi_Playlists = [];
        $this->Host = '';
        $this->ParentID = 0;
        $this->ScannerID = 0;
        $this->SelectedPlayers = [];
    }

    /**
     * Migrate
     * @param  string $JSONData
     * @return string
     */
    public function Migrate(string $JSONData): string
    {
        $Data = json_decode($JSONData);
        if (property_exists($Data->configuration, 'showPlaylist')) {
            $Data->configuration->showHTMLPlaylist = $Data->configuration->showPlaylist;
            $vid = $this->FindIDForIdent('Playlists');
            if ($vid > 0) { //Migrate StatusVariable Playlist to HTMLPlaylist
                @IPS_SetIdent($vid, 'HTMLPlaylists');
            }
            $this->SendDebug('Migrate', json_encode($Data), 0);
            $this->LogMessage('Migrated settings:' . json_encode($Data), KL_MESSAGE);
        }
        return json_encode($Data);
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
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
        // Alte Profile aufräumen
        $this->UnregisterProfile('LMS.PlayerSelect.' . $this->InstanceID);
        $this->UnregisterProfile('LMS.Scanner');
        // Eigene Variablen
        $this->RegisterVariableString('Version', 'Version', [], 0);
        $this->RegisterVariableInteger(
            'RescanState',
            'Scanner',
            [
                \SqueezeBox\Presentation::Icon         => 'magnifying-glass-music',
                \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_ENUMERATION,
                \SqueezeBox\Presentation\Enum::Options => json_encode(
                    [
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 0,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('standby'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'magnifying-glass-music',
                            \SqueezeBox\Presentation\Enum::Color      => -1,
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 1,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('abort'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'xmark',
                            \SqueezeBox\Presentation\Enum::Color      => -1,
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 2,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('scan'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'magnifying-glass',
                            \SqueezeBox\Presentation\Enum::Color      => -1,
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 3,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('only playlists'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'file-magnifying-glass',
                            \SqueezeBox\Presentation\Enum::Color      => -1,
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 4,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('completely'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'folder-magnifying-glass',
                            \SqueezeBox\Presentation\Enum::Color      => -1,
                        ]
                    ]
                )

            ],
            1
        );
        $this->ScannerID = $this->FindIDForIdent('RescanState');
        $this->RegisterMessage($this->ScannerID, VM_UPDATE);
        $this->EnableAction('RescanState');

        $this->RegisterVariableString(
            'RescanInfo',
            $this->Translate('Rescan state'),
            [
                \SqueezeBox\Presentation::Icon          => 'database',
                \SqueezeBox\Presentation::Type          => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Options => json_encode([
                    [
                        \SqueezeBox\Presentation\Value::Value       => '',
                        \SqueezeBox\Presentation\Value::Caption     => $this->Translate('standby'),
                        \SqueezeBox\Presentation\Value::IconActive  => true,
                        \SqueezeBox\Presentation\Value::Icon        => 'database',
                        \SqueezeBox\Presentation\Value::ColorActive => false,
                        \SqueezeBox\Presentation\Value::Color       => -1,
                    ],
                    [
                        \SqueezeBox\Presentation\Value::Value       => 'discovering_directory',
                        \SqueezeBox\Presentation\Value::Caption     => $this->Translate('discovering directory'),
                        \SqueezeBox\Presentation\Value::IconActive  => true,
                        \SqueezeBox\Presentation\Value::Icon        => 'file-magnifying-glass',
                        \SqueezeBox\Presentation\Value::ColorActive => false,
                        \SqueezeBox\Presentation\Value::Color       => -1,
                    ],
                    [
                        \SqueezeBox\Presentation\Value::Value       => 'directory_deleted',
                        \SqueezeBox\Presentation\Value::Caption     => $this->Translate('deleted directory'),
                        \SqueezeBox\Presentation\Value::IconActive  => true,
                        \SqueezeBox\Presentation\Value::Icon        => 'file-magnifying-glass',
                        \SqueezeBox\Presentation\Value::ColorActive => false,
                        \SqueezeBox\Presentation\Value::Color       => -1,
                    ],
                    [
                        \SqueezeBox\Presentation\Value::Value       => 'discovering_playlist',
                        \SqueezeBox\Presentation\Value::Caption     => $this->Translate('discovering playlist'),
                        \SqueezeBox\Presentation\Value::IconActive  => true,
                        \SqueezeBox\Presentation\Value::Icon        => 'list-music',
                        \SqueezeBox\Presentation\Value::ColorActive => false,
                        \SqueezeBox\Presentation\Value::Color       => -1,
                    ],
                    [
                        \SqueezeBox\Presentation\Value::Value       => 'playlist_new',
                        \SqueezeBox\Presentation\Value::Caption     => $this->Translate('discovering playlist'),
                        \SqueezeBox\Presentation\Value::IconActive  => true,
                        \SqueezeBox\Presentation\Value::Icon        => 'list-music',
                        \SqueezeBox\Presentation\Value::ColorActive => false,
                        \SqueezeBox\Presentation\Value::Color       => -1,
                    ],
                    [
                        \SqueezeBox\Presentation\Value::Value       => 'plugin_fulltext',
                        \SqueezeBox\Presentation\Value::Caption     => $this->Translate('plugin'),
                        \SqueezeBox\Presentation\Value::IconActive  => true,
                        \SqueezeBox\Presentation\Value::Icon        => 'folder-gear',
                        \SqueezeBox\Presentation\Value::ColorActive => false,
                        \SqueezeBox\Presentation\Value::Color       => -1,
                    ],
                    [
                        \SqueezeBox\Presentation\Value::Value       => 'updateStandaloneArtwork',
                        \SqueezeBox\Presentation\Value::Caption     => $this->Translate('update artwork'),
                        \SqueezeBox\Presentation\Value::IconActive  => true,
                        \SqueezeBox\Presentation\Value::Icon        => 'images',
                        \SqueezeBox\Presentation\Value::ColorActive => false,
                        \SqueezeBox\Presentation\Value::Color       => -1,
                    ],
                    [
                        \SqueezeBox\Presentation\Value::Value       => 'dboptimize',
                        \SqueezeBox\Presentation\Value::Caption     => $this->Translate('optimize database'),
                        \SqueezeBox\Presentation\Value::IconActive  => true,
                        \SqueezeBox\Presentation\Value::Icon        => 'images',
                        \SqueezeBox\Presentation\Value::ColorActive => false,
                        \SqueezeBox\Presentation\Value::Color       => -1,
                    ]
                ])
            ],
            2
        );
        $this->RegisterVariableString('RescanProgress', $this->Translate('Rescan progress'), [], 3);
        $this->RegisterVariableInteger('Players', 'Number of players', [], 4);

        // ServerPlaylisten
        if ($this->ReadPropertyBoolean(\SqueezeBox\Splitter\Property::ShowHTMLPlaylist)) {
            if ($this->RegisterVariableString(
                'HTMLPlaylists',
                $this->Translate('Playlists'),
                [
                    \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_WEB_CONTENT,
                    \SqueezeBox\Presentation\HTML::Type    => 0,
                    \SqueezeBox\Presentation\HTML::Padding => true
                ],
                6
            )) {
                IPS_SetIcon($this->FindIDForIdent('HTMLPlaylists'), 'list-music');
            }
            $this->RegisterVariableInteger(
                'PlayerSelect',
                $this->Translate('select player'),
                [
                    \SqueezeBox\Presentation::Icon         => 'Speaker',
                    \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_ENUMERATION,
                    \SqueezeBox\Presentation\Enum::Options => json_encode([
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 0,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('None'),
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => '',
                            \SqueezeBox\Presentation\Enum::Color      => 0x00ff00
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 100,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('All'),
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => '',
                            \SqueezeBox\Presentation\Enum::Color      => 0xff0000
                        ]
                    ])
                ],
                5
            );
            $this->EnableAction('PlayerSelect');
            $this->RegisterMessage($this->InstanceID, FM_CHILDADDED);
            $this->RegisterMessage($this->InstanceID, FM_CHILDREMOVED);
        } else {
            $this->UnregisterVariable('HTMLPlaylists');
            $this->UnregisterVariable('PlayerSelect');
            $this->UnregisterMessage($this->InstanceID, FM_CHILDADDED);
            $this->UnregisterMessage($this->InstanceID, FM_CHILDREMOVED);
        }

        // Wenn Kernel nicht bereit, dann warten... KR_READY kommt ja gleich
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // ServerPlaylisten
        if ($this->ReadPropertyBoolean(\SqueezeBox\Splitter\Property::ShowHTMLPlaylist)) {
            $this->RegisterHook('LMSPlaylist' . $this->InstanceID);
        }

        // Config prüfen
        $this->RegisterParent();

        // Wenn Parent aktiv, dann Anmeldung an der Hardware bzw. Datenabgleich starten
        if ($this->ParentID > 0) {
            $this->IOChangeState(IS_ACTIVE);
        }
    }

    /**
     * GetCompatibleParents
     *
     * @return string
     */
    public function GetCompatibleParents(): string
    {
        return '{"type": "require", "moduleIDs": ["{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}"]}';
    }

    /**
     * MessageSink
     *
     * @param  int $TimeStamp
     * @param  int $SenderID
     * @param  int $Message
     * @param  array $Data
     * @return void
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {

        if (!IPS_InstanceExists($this->InstanceID)) {
            return;
        }
        if (IPS_InstanceExists($SenderID)) {
            $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        }

        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            case VM_UPDATE:
                if (($SenderID == $this->ScannerID) && ($Data[0] == 0)) {
                    $this->RefreshPlaylistBuffer();
                }
                break;
            case FM_CHILDADDED:
            case FM_CHILDREMOVED:
                // In neuen Script-Thread kurz warten, sonst hat eine neue Instanz noch keinen Namen :(
                IPS_RunScriptText('IPS_Sleep(5000);IPS_RequestAction(' . $this->InstanceID . ',\'RefreshPlayerList\',0);');
                break;
        }
    }

    /**
     * GetConfigurationForParent
     *
     * @return string
     */
    public function GetConfigurationForParent(): string
    {
        $Config[\SqueezeBox\IO\Property::Port] = $this->ReadPropertyInteger(\SqueezeBox\Splitter\Property::Port);
        $Config[\SqueezeBox\IO\Property::UseSSL] = false;
        return json_encode($Config);
    }

    /**
     * GetConfigurationForm
     *
     * @return string
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        $Form['elements'][1]['objectID'] = $this->ParentID;
        $Form['elements'][1]['enabled'] = ($this->ParentID > 1);
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    /**
     * RequestAction
     * Actionhandler der Statusvariablen. Interne SDK-Funktion.
     *
     * @param string                $Ident Der Ident der Statusvariable.
     * @param bool|float|int|string $Value Der angeforderte neue Wert.
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return;
        }
        switch ($Ident) {
            case 'PlayerSelect':
                switch ($Value) {
                    case 0: //keiner
                        $this->SelectedPlayers = [];
                        break;
                    case 100: //alle
                        $this->SelectedPlayers = $this->GetAllPlayers();
                        break;
                    default:
                        $SelectedPlayers = $this->SelectedPlayers;
                        $Index = array_search((int) $Value, $SelectedPlayers);
                        if ($Index === false) {
                            array_push($SelectedPlayers, (int) $Value);
                        } else {
                            unset($SelectedPlayers[$Index]);
                        }
                        $this->SelectedPlayers = $SelectedPlayers;
                        break;
                }
                $this->RefreshPlayerList();
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
                if (($Value != 0) && (($ret === null) || ($ret === false))) {
                    echo $this->Translate('Error on send scanner-command');
                    return;
                }
                if ($this->GetValue('RescanState') != $Value) {
                    $this->SetValueInteger('RescanState', $Value);
                }
                break;
            case 'RefreshPlayerList':
                $this->RefreshPlayerList();
                break;
            case 'showHTMLPlaylist':
                $this->UpdateFormField('Table', 'enabled', (bool) $Value);
                $this->UpdateFormField('Columns', 'enabled', (bool) $Value);
                $this->UpdateFormField('Rows', 'enabled', (bool) $Value);
                $this->UpdateFormField('HTMLExpansionPanel', 'expanded', (bool) $Value);
                return;
            default:
                echo $this->Translate('Invalid Ident');
                break;
        }
    }

    /**
     * KeepAlive
     * IPS-Instanz-Funktion 'LMS_KeepAlive'.
     * Sendet einen listen Abfrage an den LMS um die Kommunikation zu erhalten.
     *
     * @return bool true wenn LMS erreichbar, sonst false.
     */
    public function KeepAlive(): bool
    {
        $Data = new \SqueezeBox\LMSData('listen', '1');
        $ret = @$this->Send($Data);
        if ($ret === null) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Error on keepalive to LMS.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if ($ret->Data[0] == '1') {
            return true;
        }
        set_error_handler([$this, 'ModulErrorHandler']);
        trigger_error($this->Translate('Error on keepalive to LMS.'), E_USER_NOTICE);
        restore_error_handler();
        return false;
    }

    /**
     * SendSpecial
     * IPS-Instanz-Funktion 'LMS_SendSpecial'.
     * Sendet einen Anfrage an den LMS.
     *
     * @param string $Command Das zu sendende Kommando.
     * @param string $Value   Die zu sendenden Werte als JSON String.
     * @return array|bool Antwort des LMS als Array, false im Fehlerfall.
     */
    public function SendSpecial(string $Command, string $Value): bool|array
    {
        $Data = json_decode($Value, true);
        if ($Data === null) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value ist not valid JSON.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = new \SqueezeBox\LMSData($Command, $Data);
        $ret = $this->SendDirect($LMSData);
        return $ret->Data;
    }

    /**
     * RestartServer
     * IPS-Instanz-Funktion 'LMS_RestartServer'.
     *
     * @return bool
     */
    public function RestartServer(): bool
    {
        return $this->Send(new \SqueezeBox\LMSData('restartserver')) != null;
    }

    /**
     * RequestState
     * IPS-Instanz-Funktion 'LMS_RequestState'.
     * Fragt einen Wert des LMS ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @param string $Ident Der Ident der abzufragenden Statusvariable.
     * @return bool True wenn erfolgreich.
     */
    public function RequestState(string $Ident): bool
    {
        if ($Ident == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Invalid Ident'));
            restore_error_handler();
            return false;
        }
        switch ($Ident) {
            case 'Players':
                $LMSResponse = new \SqueezeBox\LMSData(['player', 'count'], '?');
                break;
            case 'Version':
                $LMSResponse = new \SqueezeBox\LMSData('version', '?');
                break;
            case 'Playlists':
                $this->RefreshPlaylistBuffer();
                return true;
            default:
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Invalid Ident'));
                restore_error_handler();
                return false;
        }
        $LMSResponse = $this->SendDirect($LMSResponse);
        if ($LMSResponse === null) {
            return false;
        }
        return $this->DecodeLMSResponse($LMSResponse);
    }

    /**
     * GetAudioDirs
     * IPS-Instanz-Funktion 'LMS_GetAudioDirs'.
     *
     * @return bool|array
     */
    public function GetAudioDirs(): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['pref', 'mediadirs'], '?'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data;
    }

    /**
     * GetPlaylistDir
     * IPS-Instanz-Funktion 'LMS_GetPlaylistDir'.
     *
     * @return array
     */
    public function GetPlaylistDir(): bool|string
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['pref', 'playlistdir'], '?'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0];
    }

    /**
     * GetSyncGroups
     * IPS-Instanz-Funktion 'LMS_GetSyncGroups'.
     * Liefer ein Array welches die Gruppen mit ihren jeweiligen IPS-InstanzeIDs enthält.
     *
     * @return bool|array Array welches so viele Elemente wie Gruppen enthält.
     */
    public function GetSyncGroups(): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('syncgroups', '?'));
        if ($LMSData == null) {
            return false;
        }

        if (count($LMSData->Data) == 0) {
            return [];
        }
        $AllPlayerIDs = IPS_GetInstanceListByModuleID(\SqueezeBox\GUID::Squeezebox);
        $Addresses = [];
        $ret = [];
        foreach ($AllPlayerIDs as $DeviceID) {
            $Addresses[$DeviceID] = IPS_GetProperty($DeviceID, \SqueezeBox\Device\Property::Address);
        }
        $Data = array_chunk($LMSData->Data, 2);
        foreach ($Data as $Group) {
            $FoundInstanzIDs = [];
            $Search = explode(',', (new \SqueezeBox\LMSTaggingData($Group[0]))->Value);
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

    /**
     * GetPlayerInfo
     * IPS-Instanz-Funktion 'LMS_GetPlayerInfo'.
     *
     * @param int $Index Der Index des Player.
     * @example <code>
     * $ret = LMS_GetPlayerInfo(37340 \/*[LMSSplitter]*\/,6);
     * var_dump($ret);
     * </code>
     * @return bool|array Ein assoziiertes Array mit den Daten des Players.
     */
    public function GetPlayerInfo(int $Index): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('players', [(string) $Index, '1']));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $ret = (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
        if (!isset($ret['Playerid'])) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Invalid index'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $DevicesIDs = IPS_GetInstanceListByModuleID(\SqueezeBox\GUID::Squeezebox);
        $FoundId = 0;
        foreach ($DevicesIDs as $Device) {
            if (IPS_GetProperty($Device, \SqueezeBox\Device\Property::Address) == $ret['Playerid']) {
                $FoundId = $Device;
            }
        }
        $ret['Instanceid'] = $FoundId;
        unset($ret['Count']);
        return $ret;
    }

    /**
     * Rescan
     * IPS-Instanz-Funktion 'LMS_Rescan'.
     * Startet einen rescan der Datenbank.
     *
     * @return bool True wenn erfolgreich.
     */
    public function Rescan(): bool
    {
        return $this->Send(new \SqueezeBox\LMSData('rescan')) != null;
    }

    /**
     * RescanPlaylists
     * IPS-Instanz-Funktion 'LMS_RescanPlaylists'.
     * Startet einen rescan der Datenbank für die Playlists.
     *
     * @return bool True wenn erfolgreich.
     */
    public function RescanPlaylists(): bool
    {
        return $this->Send(new \SqueezeBox\LMSData('rescan', 'playlists')) != null;
    }

    /**
     * WipeCache
     * IPS-Instanz-Funktion 'LMS_WipeCache'.
     * Löscht den Cache der DB.
     *
     * @return bool True wenn erfolgreich.
     */
    public function WipeCache(): bool
    {
        return $this->Send(new \SqueezeBox\LMSData('wipecache')) != null;
    }

    /**
     * AbortScan
     * IPS-Instanz-Funktion 'LMS_AbortScan'.
     * Bricht einen Scan der Datenbank ab.
     *
     * @return bool True wenn erfolgreich.
     */
    public function AbortScan(): bool
    {
        return $this->Send(new \SqueezeBox\LMSData('abortscan')) != null;
    }

    /**
     * GetLibraryInfo
     * IPS-Instanz-Funktion 'LMS_GetLibraryInfo'.
     *
     * @return bool|array
     */
    public function GetLibraryInfo(): bool|array
    {
        $genres = $this->SendDirect(new \SqueezeBox\LMSData(['info', 'total', 'genres'], '?'));
        if ($genres === null) {
            return false;
        }
        $artists = $this->SendDirect(LMSData: new \SqueezeBox\LMSData(['info', 'total', 'artists'], '?'));
        if ($artists === null) {
            return false;
        }
        $albums = $this->SendDirect(new \SqueezeBox\LMSData(['info', 'total', 'albums'], '?'));
        if ($albums === null) {
            return false;
        }
        $songs = $this->SendDirect(new \SqueezeBox\LMSData(['info', 'total', 'songs'], '?'));
        if ($songs === null) {
            return false;
        }
        $duration = $this->SendDirect(new \SqueezeBox\LMSData(['info', 'total', 'duration'], '?'));
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
     * GetGenres
     * IPS-Instanz-Funktion 'LMS_GetGenres'.
     *
     * @return  bool|array
     */
    public function GetGenres(): bool|array
    {
        return $this->GetGenresEx('');
    }

    /**
     * GetGenresEx
     * IPS-Instanz-Funktion 'LMS_GetGenresEx'.
     *
     * @param string $Search Suchstring
     * @return bool|array
     */
    public function GetGenresEx(string $Search): bool|array
    {
        $Data = [0, 100000];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('genres', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Genres = new \SqueezeBox\LMSTaggingArray($LMSData->Data);
        $Genres->Compact('Genre');
        return $Genres->DataArray();
    }

    /**
     * GetArtists
     * IPS-Instanz-Funktion 'LMS_GetArtists'.
     *
     * @return bool|array
     */
    public function GetArtists(): bool|array
    {
        return $this->GetArtistsEx('');
    }

    /**
     * GetArtistsEx
     * IPS-Instanz-Funktion 'LMS_GetArtistsEx'.
     *
     * @param string $Search Suchstring
     * @return bool|array
     */
    public function GetArtistsEx(string $Search): bool|array
    {
        $Data = [0, 100000];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('artists', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Artists = new \SqueezeBox\LMSTaggingArray($LMSData->Data);
        $Artists->Compact('Artist');
        return $Artists->DataArray();
    }

    /**
     * GetAlbums
     * IPS-Instanz-Funktion 'LMS_GetAlbums'.
     *
     * @return bool|array
     */
    public function GetAlbums(): bool|array
    {
        return $this->GetAlbumsEx('');
    }

    /**
     * GetAlbumsEx
     * IPS-Instanz-Funktion 'LMS_GetAlbumsEx'.
     *
     * @param string $Search Suchstring
     * @return bool|array
     */
    public function GetAlbumsEx(string $Search): bool|array
    {
        $Data = [0, 100000];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('albums', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Albums = new \SqueezeBox\LMSTaggingArray($LMSData->Data);
        $Albums->Compact('Album');
        return $Albums->DataArray();
    }

    /**
     * GetDirectoryByID
     * IPS-Instanz-Funktion 'LMS_GetDirectoryByID'. Liefert Informationen zu einem Verzeichnis.
     *
     * @param int $FolderID ID des Verzeichnis welches durchsucht werden soll. 0= root
     * @return bool|array Array mit den Quellen oder false bei Fehler.
     */
    public function GetDirectoryByID(int $FolderID): bool|array
    {
        if ($FolderID == 0) {
            $Data = ['0', 100000, 'tags:uc'];
        } else {
            $Data = ['0', 100000, 'tags:uc', 'folder_id:' . $FolderID];
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('mediafolder', $Data));
        if ($LMSData == null) {
            return false;
        }
        $LMSData->SliceData();

        return (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
    }

    /**
     * GetDirectoryByIDRecursive
     * IPS-Instanz-Funktion 'LMS_GetDirectoryByIDRecursive'. Liefert rekursiv Informationen zu einem Verzeichnis.
     *
     * @param int $FolderID ID des Verzeichnis welches durchsucht werden soll.
     * @return bool|array Array mit den Quellen oder false bei Fehler.
     */
    public function GetDirectoryByIDRecursive(int $FolderID): bool|array
    {
        if ($FolderID == 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Search root recursive is not supported'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        } else {
            $Data = ['0', 100000, 'tags:uc', 'folder_id:' . $FolderID];
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('mediafolder', $Data));
        if ($LMSData == null) {
            return false;
        }
        $LMSData->SliceData();
        return (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
    }

    /**
     * GetDirectoryByURL
     * IPS-Instanz-Funktion 'LMS_GetDirectoryByURL'. Liefert rekursiv Informationen zu einem Verzeichnis.
     *
     * @param string $Directory URL des Verzeichnis welches durchsucht werden soll.
     * @return bool|array Array mit den Quellen oder false bei Fehler.
     */
    public function GetDirectoryByURL(string $Directory): bool|array
    {
        if ($Directory == '') {
            $Data = ['0', 100000, 'tags:uc'];
        } else {
            $Data = ['0', 100000, 'tags:uc', 'url:' . $Directory];
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('mediafolder', $Data));
        if ($LMSData == null) {
            return false;
        }
        $LMSData->SliceData();
        return (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
    }

    /**
     * GetDirectoryByURLRecursive
     * IPS-Instanz-Funktion 'LMS_GetDirectoryByURLRecursive'. Liefert rekursiv Informationen zu einem Verzeichnis.
     *
     * @param string $Directory URL des Verzeichnis welches durchsucht werden soll.
     * @return bool|array Array mit den Quellen oder false bei Fehler.
     */
    public function GetDirectoryByURLRecursive(string $Directory): bool|array
    {
        if ($Directory == '') {
            $Data = ['0', 100000, 'recursive:1', 'tags:uc'];
        } else {
            $Data = ['0', 100000, 'recursive:1', 'tags:uc', 'url:' . $Directory];
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('mediafolder', $Data));
        if ($LMSData == null) {
            return false;
        }
        $LMSData->SliceData();
        return (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
    }

    /**
     * GetPlaylists
     * IPS-Instanz-Funktion 'LMS_GetPlaylists'.
     * Liefert alle Server Playlisten.
     *
     * @return bool|array Array mit den Server-Playlists. FALSE im Fehlerfall.
     */
    public function GetPlaylists(): bool|array
    {
        return $this->GetPlaylistsEx('');
    }

    /**
     * GetPlaylistsEx
     * IPS-Instanz-Funktion 'LMS_GetPlaylistsEx'.
     * Liefert alle Server Playlisten.
     *
     * @param string $Search Such-String
     * @return bool|array Array mit den Server-Playlists. FALSE im Fehlerfall.
     */
    public function GetPlaylistsEx(string $Search): bool|array
    {
        if ($Search != '') {
            $Data = [0, 100000, 'search:' . urlencode($Search), 'tags:u'];
        } else {
            return $this->Multi_Playlists;
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('playlists', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Playlists = (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
        foreach ($Playlists as $Key => $Playlist) {
            $Playlists[$Key]['Id'] = $Key;
            $Playlists[$Key]['Tracks'] = 0;
            $Playlists[$Key]['Duration'] = 0;
            $LMSSongData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'tracks'], [0, 100000, 'playlist_id:' . $Key, 'tags:d']));
            if ($LMSSongData === null) {
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error(sprintf($this->Translate('Error read Playlist %d .'), $Key), E_USER_NOTICE);
                restore_error_handler();
                continue;
            }
            $LMSSongData->SliceData();
            $SongInfo = new \SqueezeBox\LMSSongInfo($LMSSongData->Data);
            $Playlists[$Key]['Tracks'] = $SongInfo->CountAllSongs();
            $Playlists[$Key]['Duration'] = $SongInfo->GetTotalDuration();
        }
        return $Playlists;
    }

    /**
     * GetPlaylist
     * IPS-Instanz-Funktion 'LMS_GetPlaylist'.
     * Liefert alle Songs einer Playlist.
     *
     * @param int $PlaylistId Die Playlist welche gelesen werden soll.
     * @return bool|array Array mit Songs der Playlist.
     */
    public function GetPlaylist(int $PlaylistId): bool|array
    {
        $LMSSongData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'tracks'], [0, 10000, 'playlist_id:' . $PlaylistId, 'tags:gladiqrRtueJINpsy']));
        if ($LMSSongData === null) {
            return false;
        }
        $LMSSongData->SliceData();
        $SongInfo = new \SqueezeBox\LMSSongInfo($LMSSongData->Data);
        return $SongInfo->GetAllSongs();
    }

    /**
     * RenamePlaylist
     * IPS-Instanz-Funktion 'LMS_RenamePlaylist'.
     * Benennt eine Playlist um.
     *
     * @param int    $PlaylistId Die ID der Playlist welche umbenannt werden soll.
     * @param string $Name       Der neue Name der Playlist.
     * @return bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function RenamePlaylist(int $PlaylistId, string $Name): bool
    {
        return $this->RenamePlaylistEx($PlaylistId, $Name, false);
    }

    /**
     * RenamePlaylistEx
     * IPS-Instanz-Funktion 'LMS_RenamePlaylistEx'.
     * Benennt eine Playlist um.
     *
     * @param int    $PlaylistId Die ID der Playlist welche umbenannt werden soll.
     * @param string $Name       Der neue Name der Playlist.
     * @param bool   $Overwrite  True wenn eine eventuell vorhandene Playlist überschrieben werden soll.
     * @return bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function RenamePlaylistEx(int $PlaylistId, string $Name, bool $Overwrite): bool
    {
        if (!$Overwrite) {
            $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'rename'], ['playlist_id:' . $PlaylistId, 'newname:' . $Name, 'dry_run:1']));
            if ($LMSData === null) {
                return false;
            }
            $LMSData->SliceData();
            if (count($LMSData->Data) > 0) {
                if ((new \SqueezeBox\LMSTaggingData($LMSData->Data[0]))->Value == $PlaylistId) {
                    return true;
                }
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error(sprintf($this->Translate('Error rename Playlist. Name already used by Playlist %s'), (new \SqueezeBox\LMSTaggingData($LMSData->Data[0]))->Value), E_USER_NOTICE);
                restore_error_handler();
                return false;
            }
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'rename'], ['playlist_id:' . $PlaylistId, 'newname:' . $Name, 'dry_run:0']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * CreatePlaylist
     * IPS-Instanz-Funktion 'LMS_CreatePlaylist'.
     * Erzeugt eine Playlist.
     *
     * @param string $Name Der Name für die neue Playlist.
     * @return int Die PlaylistId der neu erzeugten Playlist. FALSE im Fehlerfall.
     */
    public function CreatePlaylist(string $Name): bool|int
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'new'], 'name:' . $Name));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        if (strpos($LMSData->Data[0], 'playlist_id') === 0) {
            return (int) (new \SqueezeBox\LMSTaggingData($LMSData->Data[0]))->Value;
        }
        set_error_handler([$this, 'ModulErrorHandler']);
        trigger_error($this->Translate('Playlist already exists.'), E_USER_NOTICE);
        restore_error_handler();
        return false;
    }

    /**
     * DeletePlaylist
     * IPS-Instanz-Funktion 'LMS_DeletePlaylist'.
     * Löscht eine Playlist.
     *
     * @param int $PlaylistId Die ID der zu löschenden Playlist.
     * @return bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function DeletePlaylist(int $PlaylistId): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'delete'], 'playlist_id:' . $PlaylistId));
        if ($LMSData === null) {
            return false;
        }
        if (strpos($LMSData->Data[0], 'playlist_id') === 0) {
            return $PlaylistId === (int) (new \SqueezeBox\LMSTaggingData($LMSData->Data[0]))->Value;
        }
        set_error_handler([$this, 'ModulErrorHandler']);
        trigger_error($this->Translate('Error deleting Playlist.'), E_USER_NOTICE);
        restore_error_handler();
        return false;
    }

    /**
     * AddSongToPlaylist
     * IPS-Instanz-Funktion 'LMS_AddSongToPlaylist'.
     * Fügt einen Song einer Playlist hinzu.
     *
     * @param int    $PlaylistId Die ID der Playlist zu welcher ein Song hinzugefügt wird.
     * @param string $SongURL    Die URL des Song, Verzeichnisses oder Streams.
     * @return bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function AddSongToPlaylist(int $PlaylistId, string $SongURL): bool
    {
        return $this->AddSongToPlaylistEx($PlaylistId, $SongURL, -1);
    }

    /**
     * AddSongToPlaylistEx
     * IPS-Instanz-Funktion 'LMS_AddSongToPlaylistEx'.
     * Fügt einen Song einer Playlist an einer bestimmten Position hinzu.
     *
     * @param int    $PlaylistId Die ID der Playlist zu welcher ein Song hinzugefügt wird.
     * @param string $SongURL    Die URL des Song.
     * @param int    $Position   Die Position (1 = 1.Eintrag) an welcher der Song eingefügt wird.
     * @return bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function AddSongToPlaylistEx(int $PlaylistId, string $SongURL, int $Position): bool
    {
        if ($this->GetValidSongURL($SongURL) == false) {
            return false;
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'edit'], ['cmd:add', 'playlist_id:' . $PlaylistId, 'url:' . $SongURL]));
        if ($LMSData === null) {
            return false;
        }

        if (strpos($LMSData->Data[2], 'url') === 0) {
            if ($SongURL != (new \SqueezeBox\LMSTaggingData($LMSData->Data[2]))->Value) {
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Error on add SongURL to playlist.'), E_USER_NOTICE);
                restore_error_handler();
                return false;
            }
        }

        if ($Position == -1) {
            return true;
        }

        $LMSSongData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'tracks'], [0, 10000, 'playlist_id:' . $PlaylistId, 'tags:']));
        if ($LMSSongData === null) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Error on move Song after adding to playlist.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }

        $OldPosition = new \SqueezeBox\LMSTaggingData(array_pop($LMSSongData->Data));
        if ($OldPosition->Name != 'count') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Error on move Song after adding to playlist.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return $this->MoveSongInPlaylist($PlaylistId, (int) $OldPosition->Value - 1, $Position);
    }

    /**
     * DeleteSongFromPlaylist
     * IPS-Instanz-Funktion 'LMS_DeleteSongFromPlaylist'.
     * Entfernt einen Song aus einer Playlist.
     *
     * @param int $PlaylistId Die ID der Playlist aus welcher ein Song entfernt wird.
     * @param int $Position   Die Position (1 = 1.Eintrag) des Song welcher entfernt wird.
     * @return bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function DeleteSongFromPlaylist(int $PlaylistId, int $Position): bool
    {
        $Position--;
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'edit'], ['cmd:delete', 'playlist_id:' . $PlaylistId, 'index:' . $Position]));
        if ($LMSData == null) {
            return false;
        }
        $LMSData->SliceData();
        if (count($LMSData->Data) > 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Error delete song from playlist.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return true;
    }

    /**
     * MoveSongInPlaylist
     * IPS-Instanz-Funktion 'LMS_MoveSongInPlaylist'.
     * Verschiebt die Position eines Song innerhalb einer Playlist.
     *
     * @param int $PlaylistId  Die ID der Playlist.
     * @param int $Position    Die Position (1 = 1.Eintrag) des Song welcher verschoben wird.
     * @param int $NewPosition Die neue Position (1 = 1.Eintrag) des zu verschiebenen Song.
     * @return bool TRUE bei Erfolg. FALSE im Fehlerfall.
     */
    public function MoveSongInPlaylist(int $PlaylistId, int $Position, int $NewPosition): bool
    {
        $Position--;
        $NewPosition--;
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'edit'], ['cmd:move', 'playlist_id:' . $PlaylistId, 'index:' . $Position, 'toindex:' . $NewPosition]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        if (count($LMSData->Data) > 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Error on move Song in playlist.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return true;
    }

    /**
     * GetSongInfoByFileID
     * IPS-Instanz-Funktion 'LMS_GetSongInfoByFileID'.
     * Liefert Details zu einem Song anhand der ID.
     *
     * @param int $SongID Die ID des Song
     * @return bool|array Array mit den Daten des Song. FALSE wenn SongID unbekannt.
     */
    public function GetSongInfoByFileID(int $SongID): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('songinfo', ['0', '20', 'track_id:' . $SongID, 'tags:gladiqrRtueJINpsy']));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
        if (!array_key_exists($SongID, $SongInfo)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('SongID not valid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $SongInfo[$SongID]['Id'] = $SongID;
        return $SongInfo[$SongID];
    }

    /**
     * GetSongInfoByFileURL
     * IPS-Instanz-Funktion 'LMS_GetSongInfoByFileURL'.
     * Liefert Details zu einem Song anhand der URL.
     *
     * @param string $SongURL Die URL des Song
     * @return bool|array Array mit den Daten des Song. FALSE wenn Song unbekannt.
     */
    public function GetSongInfoByFileURL(string $SongURL): bool|array
    {
        if ($this->GetValidSongURL($SongURL) == false) {
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('songinfo', ['0', '20', 'url:' . $SongURL, 'tags:gladiqrRtueJINpsy']));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
        if (count($SongInfo) == 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('SongURL not found.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $SongInfo[key($SongInfo)]['Track_id'] = key($SongInfo);
        return array_shift($SongInfo);
    }

    //
    //////    DOKU TODO AFTER HERE
    //
    /**
     * GetSongsByGenre
     *
     * @param  int $GenreId
     * @return bool|array
     */
    public function GetSongsByGenre(int $GenreId): bool|array
    {
        return $this->GetSongsByGenreEx($GenreId, '');
    }

    /**
     * GetSongsByGenreEx
     *
     * @param  int $GenreId
     * @param  string $Search
     * @return bool|array
     */
    public function GetSongsByGenreEx(int $GenreId, string $Search): bool|array
    {
        $Data = [0, 100000, 'tags:gladiqrRtueJINpsy', 'genre_id:' . $GenreId];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('songs', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = new \SqueezeBox\LMSSongInfo($LMSData->Data);
        return $SongInfo->GetAllSongs();
    }

    /**
     * GetSongsByArtist
     *
     * @param  int $ArtistId
     * @return bool|array
     */
    public function GetSongsByArtist(int $ArtistId): bool|array
    {
        return $this->GetSongsByArtistEx($ArtistId, '');
    }

    /**
     * GetSongsByArtistEx
     *
     * @param  int $ArtistId
     * @param  string $Search
     * @return bool|array
     */
    public function GetSongsByArtistEx(int $ArtistId, string $Search): bool|array
    {
        $Data = [0, 100000, 'tags:gladiqrRtueJINpsy', 'artist_id:' . $ArtistId];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('songs', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = new \SqueezeBox\LMSSongInfo($LMSData->Data);
        return $SongInfo->GetAllSongs();
    }

    /**
     * GetSongsByAlbum
     *
     * @param  int $AlbumId
     * @return bool|array
     */
    public function GetSongsByAlbum(int $AlbumId): bool|array
    {
        return $this->GetSongsByAlbumEx($AlbumId, '');
    }

    /**
     * GetSongsByAlbumEx
     *
     * @param  int $AlbumId
     * @param  string $Search
     * @return bool|array
     */
    public function GetSongsByAlbumEx(int $AlbumId, string $Search): bool|array
    {
        $Data = [0, 100000, 'tags:gladiqrRtueJINpsy', 'artist_id:' . $AlbumId];
        if ($Search != '') {
            $Data[] = 'search:' . urlencode($Search);
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('songs', $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = new \SqueezeBox\LMSSongInfo($LMSData->Data);
        return $SongInfo->GetAllSongs();
    }

    /**
     * Search
     *
     * @param  string $Value
     * @return bool|array
     */
    public function Search(string $Value): bool|array
    {
        if ($Value == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('search', [0, 100000, 'term:' . rawurlencode($Value)]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Result = [];
        $Result['Contributors'] = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, 'contributor_id', 'contributor'))->DataArray();
        $Result['Tracks'] = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, 'track_id', 'track'))->DataArray();
        $Result['Albums'] = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, 'album_id', 'album'))->DataArray();
        return $Result;
    }

    /**
     * GetAlarmPlaylists
     * IPS-Instanz-Funktion 'LMS_GetAlarmPlaylists'.
     * Liefert alle Playlisten welche für den Wecker genutzt werden können.
     *
     * @return bool|array Array mit den Server-Playlists. FALSE im Fehlerfall.
     */
    public function GetAlarmPlaylists(): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['alarm', 'playlists'], [0, 100000]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        return (new \SqueezeBox\LMSTaggingArray($LMSData->Data, 'category'))->DataArray();
    }

    /**
     * GetFavorites
     * IPS-Instanz-Funktion 'LMS_GetFavorites'.
     * Liefert ein Array mit allen in $FavoriteID enthaltenen Favoriten.
     *
     * @param string $FavoriteID ID des Favoriten welcher ausgelesen werden soll. '' für oberste Ebene.
     * @return bool|array
     */
    public function GetFavorites(string $FavoriteID): bool|array
    {
        if ($FavoriteID == '') {
            $Data = [0, 100000, 'want_url:1', 'item_id:.'];
        } else {
            $Data = [0, 100000, 'want_url:1', 'item_id:' . rawurlencode($FavoriteID)];
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'items'], $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        array_shift($LMSData->Data);
        return (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
    }

    /**
     * AddFavorite
     * IPS-Instanz-Funktion 'LMS_AddFavorite'.
     *
     * @param string $ParentFavoriteID
     * @param string $Title
     * @param string $URL
     * @return bool
     */
    public function AddFavorite(string $ParentFavoriteID, string $Title, string $URL): bool
    {
        if ($this->GetValidSongURL($URL) == false) {
            return false;
        }

        if ($ParentFavoriteID == '') {
            $ParentFavoriteID = '10000';
        } else {
            $ParentFavoriteID .= '.10000';
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'add'], ['item_id:' . $ParentFavoriteID, 'title:' . $Title, 'url:' . rawurlencode($URL)]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        return $LMSData->Data[0] == 'count:1';
    }

    /**
     * AddFavoriteLevel
     * IPS-Instanz-Funktion 'LMS_AddFavoriteLevel'.
     * Erzeugt eine neue Ebene unterhalb $ParentFavoriteID mit dem Namen von $Title.
     *
     * @param string $ParentFavoriteID Die ID des Favoriten unter dem die neue Ebene erzeugt wird. '0' oder '' = root '3' = ID 3 '3.0' => ID 3.0
     * @param string $Title            Der Name der neuen Ebene.
     * @return bool TRUE bei Erfolg, sonst FALSE.
     */
    public function AddFavoriteLevel(string $ParentFavoriteID, string $Title): bool
    {
        if ($ParentFavoriteID == '') {
            $ParentFavoriteID = '10000';
        } else {
            $ParentFavoriteID .= '.10000';
        }

        $Data = ['item_id:' . $ParentFavoriteID, 'title:' . $Title];
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'addlevel'], $Data));
        if ($LMSData === null) {
            return false;
        }

        $LMSData->SliceData();

        return $LMSData->Data[0] == 'count:1';
    }

    /**
     * DeleteFavorite
     * IPS-Instanz-Funktion 'LMS_DeleteFavorite'.
     * Löscht einen Eintrag aus den Favoriten.
     *
     * @param string $FavoriteID Die ID des Favoriten welcher gelöscht werden soll.
     * @return bool TRUE bei Erfolg, sonst FALSE.
     */
    public function DeleteFavorite(string $FavoriteID): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'delete'], 'item_id:' . $FavoriteID));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();

        return count($LMSData->Data) == 0;
    }

    /**
     * RenameFavorite
     * IPS-Instanz-Funktion 'LMS_RenameFavorite'.
     * Benennt eine Favoriten um.
     *
     * @param string $FavoriteID Die ID des Favoriten welcher umbenannt werden soll.
     * @param string $Title      Der neue Name des Favoriten.
     * @return bool TRUE bei Erfolg, sonst FALSE.
     */
    public function RenameFavorite(string $FavoriteID, string $Title): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'rename'], ['item_id:' . $FavoriteID, 'title:' . $Title]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();

        return count($LMSData->Data) == 0;
    }

    /**
     * MoveFavorite
     * IPS-Instanz-Funktion 'LMS_MoveFavorite'.
     * Verschiebt einen Favoriten.
     *
     * @param string $FavoriteID          Die ID des Favoriten welcher verschoben werden soll.
     * @param string $NewParentFavoriteID Das Ziel des zu verschiebenen Favoriten.
     * @return bool TRUE bei Erfolg, sonst FALSE.
     */
    public function MoveFavorite(string $FavoriteID, string $NewParentFavoriteID): bool
    {
        if ($NewParentFavoriteID == '') {
            $NewParentFavoriteID = '10000';
        } else {
            $NewParentFavoriteID .= '.10000';
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'move'], ['from_id:' . $FavoriteID, 'to_id:' . $NewParentFavoriteID]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();

        return count($LMSData->Data) == 0;
    }

    // TODO ab Hier !

    /**
     * ExistsUrlInFavorite
     *
     * @param  string $URL
     * @return bool|array
     */
    public function ExistsUrlInFavorite(string $URL): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'exists'], $URL));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Result = (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
        return $Result;
    }

    /**
     * ExistsIdInFavorite
     *
     * @param  int $ID
     * @return bool|array
     */
    public function ExistsIdInFavorite(int $ID): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'exists'], [$ID]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $Result = (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
        return $Result;
    }

    /**
     * GetRadios
     *
     * @return bool|array
     */
    public function GetRadios(): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('radios', [0, 100000]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        return (new \SqueezeBox\LMSTaggingArray($LMSData->Data, 'icon'))->DataArray();
    }

    /**
     * GetApps
     *
     * @return bool|array
     */
    public function GetApps(): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('apps', [0, 100000]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        return (new \SqueezeBox\LMSTaggingArray($LMSData->Data, 'icon'))->DataArray();
    }

    /**
     * GetRadioOrAppData
     *
     * @param  string $Cmd
     * @param  string $FolderID
     * @return bool|array
     */
    public function GetRadioOrAppData(string $Cmd, string $FolderID): bool|array
    {
        return $this->GetRadioOrAppDataEx($Cmd, $FolderID, '');
    }

    /**
     * GetRadioOrAppDataEx
     *
     * @param  string $Cmd
     * @param  string $FolderID
     * @param  string $Search
     * @return bool|array
     */
    public function GetRadioOrAppDataEx(string $Cmd, string $FolderID, string $Search): bool|array
    {
        $Data = [0, 100000, 'want_url:1'];
        if ($FolderID == '') {
            $Data[] = 'item_id:.';
        } else {
            $Data[] = 'item_id:' . rawurlencode($FolderID);
        }

        if ($Search != '') {
            $Data[] = 'search:' . rawurlencode($Search);
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData([$Cmd, 'items'], $Data));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        array_shift($LMSData->Data);
        return (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
    }

    /**
     * ForwardData
     * Interne Funktion des SDK. Nimmt Daten von Children entgegen und sendet Diese weiter.
     *
     * @param string $JSONString Ein \SqueezeBox\LMSData-Objekt welches als JSONString kodiert ist.
     * @return string
     */
    public function ForwardData(string $JSONString): string
    {
        $Data = json_decode($JSONString);
        $LMSData = new \SqueezeBox\LMSData();
        $LMSData->CreateFromGenericObject($Data);
        $ret = $this->Send($LMSData);
        if (!is_null($ret)) {
            return serialize($ret);
        }
        return '';
    }

    /**
     * ReceiveData
     * Empfängt Daten vom Parent.
     *
     * @param string $JSONString Das empfangene JSON-kodierte Objekt vom Parent.
     * @return string True wenn Daten verarbeitet wurden, sonst false.
     */
    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString);

        // DatenStream zusammenfügen
        $head = $this->Buffer;
        $Data = $head . hex2bin($data->Buffer);

        // Stream in einzelne Pakete schneiden
        $packet = explode(chr(0x0d), $Data);

        // Rest vom Stream wieder in den EmpfangsBuffer schieben
        $tail = trim(array_pop($packet));
        $this->Buffer = $tail;

        // Pakete verarbeiten
        foreach ($packet as $part) {
            $part = trim($part);
            $Data = new \SqueezeBox\LMSResponse($part);

            try {
                $isResponse = $this->SendQueueUpdate($Data);
            } catch (Exception $exc) {
                $buffer = $this->Buffer;
                $this->Buffer = $part . chr(0x0d) . $buffer;
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($exc->getMessage(), E_USER_NOTICE);
                restore_error_handler();
                continue;
            }

            if ($isResponse === false) { //War keine Antwort also ein Event
                $this->SendDebug('LMS_Event', $Data, 0);
                if ($Data->Device != \SqueezeBox\DeviceType::isServer) {
                    if (in_array($Data->Command[0], ['playlist', 'client'])) {
                        $this->DecodeLMSResponse($Data);
                    }
                    $this->SendDataToDevice($Data);
                } else {
                    $this->DecodeLMSResponse($Data);
                }
            }
        }
        return '';
    }

    /**
     * KernelReady
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     *
     * @return void
     */
    protected function KernelReady(): void
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->ApplyChanges();
    }

    /**
     * RegisterParent
     *
     * @return void
     */
    protected function RegisterParent(): void
    {
        $IOId = $this->IORegisterParent();
        if ($IOId > 0) {
            $Host = IPS_GetProperty($this->ParentID, \SqueezeBox\IO\Property::Host);
            $this->Host = gethostbyname($Host);
            $this->SetSummary($Host);
            return;
        }
        $this->Host = '';
        $this->SetSummary(('none'));
    }

    /**
     * IOChangeState
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     *
     * @param  int $State
     * @return void
     */
    protected function IOChangeState(int $State): void
    {
        if ($State == IS_ACTIVE) {
            if ($this->HasActiveParent()) {
                if ($this->CheckLogin() !== true) {
                    $this->SetStatus(IS_EBASE + 4);
                    $this->SetTimerInterval(\SqueezeBox\Splitter\Timer::KeepAlive, 0);
                    $this->ReloadForm();
                    return;
                }
                $this->SetStatus(IS_ACTIVE);
                $this->ReloadForm();
                $User = $this->ReadPropertyString(\SqueezeBox\Splitter\Property::Username);
                $Pass = $this->ReadPropertyString(\SqueezeBox\Splitter\Property::Password);
                $LoginData = new \SqueezeBox\LMSData('login', [$User, $Pass]);
                $this->Send($LoginData);
                $this->KeepAlive();
                $this->LogMessage($this->Translate('Connected to LMS'), KL_NOTIFY);
                $this->RequestState('Version');
                $this->LogMessage($this->Translate('Version of LMS:') . $this->GetValue('Version'), KL_NOTIFY);
                $this->RequestState('Players');
                $this->LogMessage($this->Translate('Connected Players to LMS:') . $this->GetValue('Players'), KL_NOTIFY);
                $this->RefreshPlayerList();
                $ret = $this->Send(new \SqueezeBox\LMSData('rescan', '?'));
                if ($ret !== null) {
                    $this->DecodeLMSResponse($ret);
                }
                $this->SetTimerInterval(\SqueezeBox\Splitter\Timer::KeepAlive, 3600 * 1000);
                return;
            }
        }
        $this->SetStatus(IS_INACTIVE); // Setzen wir uns auf inactive, weil wir vorher eventuell im Fehlerzustand waren.
        $this->SetTimerInterval(\SqueezeBox\Splitter\Timer::KeepAlive, 0);
        $this->ReloadForm();
    }

    /**
     * ProcessHookData
     * Verarbeitet Daten aus dem Webhook.
     * @global array $_GET
     * @param string $ID Die zu ladenden Playlist oder der zu ladende Favorit.
     */
    protected function ProcessHookData(): void
    {
        if ((!isset($_GET['ID'])) || (!isset($_GET['Type'])) || (!isset($_GET['Secret']))) {
            echo $this->Translate('Bad Request');
            return;
        }
        $CalcSecret = base64_encode(sha1($this->WebHookSecretPlaylist . '0' . $_GET['ID'], true));
        if ($CalcSecret != rawurldecode($_GET['Secret'])) {
            echo $this->Translate('Access denied');
            return;
        }
        $this->LoadPlaylistforPlayers($_GET['Type'], $_GET['ID']);
        echo 'OK';
    }

    /**
     * LoadPlaylistforPlayers
     *
     * @param  string $Type
     * @param  int|string $PlaylistId
     * @return void
     */
    protected function LoadPlaylistforPlayers(string $Type, int|string $PlaylistId): void
    {
        $Value = $this->GetValue('PlayerSelect');
        if ($Value == 0) {
            echo $this->Translate('No Player selected');
            return;
        }
        $SelectedPlayers = $this->SelectedPlayers;
        $MasterId = 0;
        foreach ($SelectedPlayers as $PlayerInstanceId) {
            $OldActiveSync = LSQ_GetSync($PlayerInstanceId);
            $this->SendDebug('OldActiveSync:' . $PlayerInstanceId, $OldActiveSync, 0);
            foreach (array_diff($OldActiveSync, $SelectedPlayers) as $UnSyncId) {
                $this->SendDebug('SetUnSync', $UnSyncId, Format: 0);
                LSQ_SetUnSync($UnSyncId);
            }
            if ($MasterId == 0) {
                $MasterId = $PlayerInstanceId;
                $this->SendDebug('MasterId', $MasterId, 0);
                continue;
            }
            if (!in_array($MasterId, $OldActiveSync)) {
                $this->SendDebug('SetSync', $MasterId . ' with ' . $PlayerInstanceId, 0);
                LSQ_SetSync($MasterId, $PlayerInstanceId);
            }
        }
        if ($Type == 'Playlist') {
            LSQ_LoadPlaylistByPlaylistID($MasterId, (int) $PlaylistId);
        }
        if ($Type == 'Favorite') {
            LSQ_LoadPlaylistByFavoriteID($MasterId, (string) $PlaylistId);
        }
    }

    /**
     * ModulErrorHandler
     *
     * @param  int $errno
     * @param  string $errstr
     * @return bool
     */
    protected function ModulErrorHandler(int $errno, string $errstr): bool
    {
        if (!(error_reporting() & $errno)) {
            // Dieser Fehlercode ist nicht in error_reporting enthalten
            return true;
        }
        $this->SendDebug('ERROR', $errstr, 0);
        echo $errstr . "\r\n";
        return false;
    }

    /**
     * GenerateHTMLStyleProperty
     *
     * @return array
     */
    private function GenerateHTMLStyleProperty(): array
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

    /**
     * RefreshPlayerList
     * Ändert das Variablenprofil PlayerSelect anhand der bekannten Player.
     *
     * @return bool TRUE bei Erfolg, sonst FALSE.
     */
    private function RefreshPlayerList(): bool
    {
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Splitter\Property::ShowHTMLPlaylist)) {
            return false;
        }
        $Value = -1;
        $SelectedPlayers = $this->SelectedPlayers;
        $Players = $this->GetAllPlayers();
        if (count($SelectedPlayers) == 0) {
            $Value = 0;
        } else {
            if ($SelectedPlayers == $Players) {
                $Value = 100;
            }
        }
        $Options = [
            [
                \SqueezeBox\Presentation\Enum::Value           => 0,
                \SqueezeBox\Presentation\Enum::Caption         => $this->Translate('None'),
                \SqueezeBox\Presentation\Enum::IconActive      => false,
                \SqueezeBox\Presentation\Enum::Icon            => '',
                \SqueezeBox\Presentation\Enum::Color           => 0x00ff00
            ],
            [
                \SqueezeBox\Presentation\Enum::Value           => 100,
                \SqueezeBox\Presentation\Enum::Caption         => $this->Translate('All'),
                \SqueezeBox\Presentation\Enum::IconActive      => false,
                \SqueezeBox\Presentation\Enum::Icon            => '',
                \SqueezeBox\Presentation\Enum::Color           => 0xff0000
            ]
        ];
        foreach ($Players as $PlayerId) {
            $Options[] = [
                \SqueezeBox\Presentation\Enum::Value           => $PlayerId,
                \SqueezeBox\Presentation\Enum::Caption         => IPS_GetName($PlayerId),
                \SqueezeBox\Presentation\Enum::IconActive      => false,
                \SqueezeBox\Presentation\Enum::Icon            => '',
                \SqueezeBox\Presentation\Enum::Color           => (in_array($PlayerId, $SelectedPlayers) ? 0xff0000 : -1)
            ];
        }
        $this->RegisterVariableInteger(
            'PlayerSelect',
            $this->Translate('select player'),
            [
                \SqueezeBox\Presentation::Icon              => 'Speaker',
                \SqueezeBox\Presentation::Type              => VARIABLE_PRESENTATION_ENUMERATION,
                \SqueezeBox\Presentation\Enum::Options      => json_encode($Options)
            ],
            5
        );
        $this->SetValueInteger('PlayerSelect', $Value);
        return true;
    }

    /**
     * GetAllPlayers
     *
     * @return array
     */
    private function GetAllPlayers(): array
    {
        $Instances = [];
        $AllPlayerIDs = IPS_GetInstanceListByModuleID(\SqueezeBox\GUID::Squeezebox);
        foreach ($AllPlayerIDs as $DeviceID) {
            if (IPS_GetInstance($DeviceID)['ConnectionID'] == $this->InstanceID) {
                $Instances[] = $DeviceID;
            }
        }
        return $Instances;
    }

    /**
     * RefreshPlaylistBuffer
     *
     * @return void
     */
    private function RefreshPlaylistBuffer(): void
    {
        $Data = [0, 100000, 'tags:u'];
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('playlists', $Data));
        if ($LMSData === null) {
            return;
        }
        $LMSData->SliceData();
        $Playlists = (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
        foreach ($Playlists as $Key => &$Playlist) {
            $Playlist['Id'] = $Key;
            if (!isset($Playlist['Playlist'])) {
                $Playlist['Playlist'] = 'unknown playlist';
            }
            $Playlist['Tracks'] = 0;
            $Playlist['Duration'] = 0;
            $LMSSongData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'tracks'], [0, 100000, 'playlist_id:' . $Key, 'tags:d'], true));
            if ($LMSSongData === null) {
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error(sprintf($this->Translate('Error read Playlist %d .'), $Key), E_USER_NOTICE);
                restore_error_handler();
                $Playlist['Playlist'] = $Playlists['Playlist'];
                continue;
            }
            $LMSSongData->SliceData();
            if (count($LMSSongData->Data) > 0) {
                $SongInfo = new \SqueezeBox\LMSSongInfo($LMSSongData->Data);
                $Playlist['Tracks'] = $SongInfo->CountAllSongs();
                $Playlist['Duration'] = $SongInfo->GetTotalDuration();
            }
        }

        uasort($Playlists, [$this, 'SortPlaylistData']);

        $this->Multi_Playlists = $Playlists;
        $this->RefreshPlaylist();
    }

    /**
     * SortPlaylistData
     *
     * @param  array $a
     * @param  array $b
     * @return int
     */
    private function SortPlaylistData(array $a, array $b): int
    {
        return strcmp($a['Playlist'], $b['Playlist']);
    }

    /**
     * RefreshPlaylist
     * Erzeugt eine HTML-Tabelle mit allen Playlisten für eine ~HTMLBox-Variable.
     *
     * @return void
     */
    private function RefreshPlaylist(): void
    {

        $Data = $this->Multi_Playlists;
        if (!is_array($Data)) {
            $Data = [];
        }
        // @Todo TilePlaylist -> Visu-SDK Fehlt, oder HTML-SDK nutzen
        // HTML-Playlist
        if ($this->ReadPropertyBoolean(\SqueezeBox\Splitter\Property::ShowHTMLPlaylist)) {
            $HTML = $this->GetTable($Data, 'LMSPlaylist', 'Playlist', 'Id');
            $this->SetValueString('HTMLPlaylists', $HTML);
        }
    }

    /**
     * DecodeLMSResponse
     *
     * @param  mixed $LMSData
     * @return bool
     */
    private function DecodeLMSResponse(\SqueezeBox\LMSData $LMSData): bool
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
                        $Data = new \SqueezeBox\LMSTaggingData($LMSData->Data[1]);
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
                                $StepProgress = $Info[3] . $this->Translate(' from ') . ($Info[4] ? $Info[4] : $this->Translate('unknown'));
                                $this->SetValueString('RescanProgress', $StepProgress);
                                return true;
                        }
                        break;
                }
                break;
            case 'wipecache':
                if ($this->GetValue('RescanState') != 4) {
                    $this->SetValueInteger('RescanState', 4); // Vollständig
                }
                return true;
            case 'player':
                if ($LMSData->Command[1] == 'count') {
                    $this->SetValueInteger('Players', (int) $LMSData->Data[0]);
                    return true;
                }
                break;
            case 'client':
                $this->RequestState('Players');
                return true;
            case 'version':
                $this->SetValueString('Version', $LMSData->Data[0]);
                return true;
            case 'rescan':
                if (!isset($LMSData->Data[0])) {
                    if ($this->GetValue('RescanState') != 2) {
                        $this->SetValueInteger('RescanState', 2); // einfacher
                    }
                    return true;
                } else {
                    if ($LMSData->Data[0] == '') {
                        if ($this->GetValue('RescanState') != 2) {
                            $this->SetValueInteger('RescanState', 2); // einfacher
                        }
                        return true;
                    }
                    elseif (($LMSData->Data[0] == 'done') || ($LMSData->Data[0] == '0')) {
                        if ($this->GetValue('RescanState') != 0) {
                            $this->SetValueInteger('RescanState', 0);   // fertig
                        } else {
                            $this->RefreshPlaylistBuffer();
                        }
                        return true;
                    } elseif ($LMSData->Data[0] == 'playlists') {
                        if ($this->GetValue('RescanState') != 3) {
                            $this->SetValueInteger('RescanState', 3); // Playlists
                        }
                        return true;
                    } elseif ($LMSData->Data[0] == '1') {
                        //start
                        if ($this->GetValue('RescanState') != 2) {
                            $this->SetValueInteger('RescanState', 2); // einfacher
                        }
                        return true;
                    }
                }
                break;
            case 'playlists':
                if (count($LMSData->Command) > 1) {
                    $PlaylistData = (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
                    if (!array_key_exists('Playlist_id', $PlaylistData)) {
                        return false;
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
                            $LMSSongData = $this->SendDirect(new \SqueezeBox\LMSData('songinfo', ['0', '20', 'track_id:' . $PlaylistData['Playlist_id'], 'tags:u']));
                            if ($LMSSongData === null) {
                                set_error_handler([$this, 'ModulErrorHandler']);
                                trigger_error(sprintf($this->Translate('Error read Playlist %d .'), $PlaylistData['Playlist_id']), E_USER_NOTICE);
                                restore_error_handler();
                                $Playlists[$PlaylistData['Playlist_id']]['Url'] = '';
                            } else {
                                $LMSSongData->SliceData();
                                $SongInfo = (new \SqueezeBox\LMSTaggingArray($LMSSongData->Data))->DataArray();
                                if (!array_key_exists($PlaylistData['Playlist_id'], $SongInfo)) {
                                    $Playlists[$PlaylistData['Playlist_id']]['Url'] = '';
                                } else {
                                    $Playlists[$PlaylistData['Playlist_id']]['Url'] = $SongInfo[$PlaylistData['Playlist_id']]['Url'];
                                }
                            }
                            break; // Todo: Why was here no break?
                        case 'edit':
                            $LMSSongData = $this->SendDirect(new \SqueezeBox\LMSData(['playlists', 'tracks'], [0, 100000, 'playlist_id:' . $PlaylistData['Playlist_id'], 'tags:d']));
                            if ($LMSSongData === null) {
                                set_error_handler([$this, 'ModulErrorHandler']);
                                trigger_error(sprintf($this->Translate('Error read Playlist %d .'), $PlaylistData['Playlist_id']), E_USER_NOTICE);
                                restore_error_handler();
                                $Playlists[$PlaylistData['Playlist_id']]['Tracks'] = 0;
                                $Playlists[$PlaylistData['Playlist_id']]['Duration'] = 0;
                            } else {
                                $LMSSongData->SliceData();
                                $SongInfo = new \SqueezeBox\LMSSongInfo($LMSSongData->Data);
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
                    $this->RefreshPlaylistBuffer();
                    return true;
                }
                break;
            case 'favorites':
                // TODO
                if (count($LMSData->Command) > 1) {
                    if (in_array($LMSData->Command[1], ['addlevel', 'rename', 'delete', 'move', 'changed'])) {
                        //mediafolder
                        //$this->RefreshFavoritesList();
                        break;
                    }
                }
                break;
            case 'songinfo':
                //TODO ???
                break;
            default:
                break;
        }
        return false;
    }

    /**
     * SendDataToDevice
     * Sendet LMSResponse an die Children.
     *
     * @param \SqueezeBox\LMSResponse $LMSResponse Ein LMSResponse-Objekt.
     * @return void
     */
    private function SendDataToDevice(\SqueezeBox\LMSResponse $LMSResponse): void
    {
        $Data = $LMSResponse->ToJSONStringForDevice();
        $this->SendDebug('IPS_SendDataToChildren', $Data, 0);
        $this->SendDataToChildren($Data);
    }

    /**
     * CheckLogin
     *
     * @return bool
     */
    private function CheckLogin(): bool
    {
        if ($this->Host === '') {
            return false;
        }
        $Port = $this->ReadPropertyInteger(\SqueezeBox\Splitter\Property::Port);
        $User = $this->ReadPropertyString(\SqueezeBox\Splitter\Property::Username);
        $Pass = $this->ReadPropertyString(\SqueezeBox\Splitter\Property::Password);
        $LoginData = (new \SqueezeBox\LMSData('login', [$User, $Pass]))->ToRawStringForLMS();
        $CheckData = (new \SqueezeBox\LMSData('can', 'login'))->ToRawStringForLMS();
        try {
            $fp = @stream_socket_client('tcp://' . $this->Host . ':' . $Port, $errno, $errstr, 2);
            if (!$fp) {
                $this->SendDebug('no socket', $errstr, 0);

                throw new Exception($this->Translate('No answer from LMS') . ' ' . $errstr, E_USER_NOTICE);
            } else {
                stream_set_timeout($fp, 5);
                $this->SendDebug('Check login', $LoginData, 0);
                fwrite($fp, $LoginData);
                $answerlogin = stream_get_line($fp, 1024 * 1024 * 2, chr(0x0d));
                $this->SendDebug('Receive login', $answerlogin, 0);
                $this->SendDebug('Connection check', $CheckData, 0);
                fwrite($fp, $CheckData);
                $answer = stream_get_line($fp, 1024 * 1024 * 2, chr(0x0d));
                fclose($fp);
                $this->SendDebug('Receive check', $answer, 0);
            }
            if ($answerlogin === false) {
                throw new Exception($this->Translate('No answer from LMS'), E_USER_NOTICE);
            }
            if ($answer === false) {
                throw new Exception($this->Translate('No answer from LMS'), E_USER_NOTICE);
            }
        } catch (Exception $ex) {
            echo $ex->getMessage();
            return false;
        }
        return true;
    }

    /**
     * Send
     * Versendet ein \SqueezeBox\LMSData-Objekt und empfängt die Antwort.
     *
     * @param \SqueezeBox\LMSData $LMSData Das Objekt welches versendet werden soll.
     * @return ?\SqueezeBox\LMSData Enthält die Antwort auf das Versendete Objekt oder NULL im Fehlerfall.
     */
    private function Send(\SqueezeBox\LMSData $LMSData): ?\SqueezeBox\LMSData
    {
        try {
            if ($this->GetStatus() != IS_ACTIVE) {
                throw new Exception($this->Translate('Instance inactive.'), E_USER_NOTICE);
            }
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
            if ($LMSData->needResponse) {
                $this->SendDebug('Send CS', $LMSData, 0);
                $this->SendQueuePush($LMSData);
                $this->SendDataToParent($LMSData->ToJSONStringForLMS('{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}'));
                $ReplyDataArray = $this->WaitForResponse($LMSData);
                if ($ReplyDataArray === false) {
                    throw new Exception($this->Translate('No answer from LMS'), E_USER_NOTICE);
                }
                $LMSData->Data = $ReplyDataArray;
                $this->SendDebug('Response CS', $LMSData, 0);
                return $LMSData;
            } else { // ohne Response, also ohne warten raussenden,
                $this->SendDebug('SendFaF CS', $LMSData, 0);
                $this->SendDataToParent($LMSData->ToJSONStringForLMS('{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}'));
                return null;
            }
        } catch (Exception $exc) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($exc->getMessage(), $exc->getCode());
            restore_error_handler();
        }
        return null;
    }

    /**
     * WaitForResponse
     * Wartet auf eine Antwort einer Anfrage an den LMS.
     *
     * @param \SqueezeBox\LMSData $LMSData Das Objekt welches an den LMS versendet wurde.
     * @return bool|array Enthält ein Array mit den Daten der Antwort. False bei einem Timeout
     */
    private function WaitForResponse(\SqueezeBox\LMSData $LMSData): bool|array
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

    /**
     * SendQueuePush
     * Fügt eine Anfrage in die SendQueue ein.
     *
     * @param \SqueezeBox\LMSData $LMSData Das versendete \SqueezeBox\LMSData Objekt.
     * @return void
     */
    private function SendQueuePush(\SqueezeBox\LMSData $LMSData): void
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
     * SendQueueUpdate
     * Fügt eine Antwort in die SendQueue ein.
     *
     * @param \SqueezeBox\LMSResponse $LMSResponse Das empfangene \SqueezeBox\LMSData Objekt.
     * @return bool True wenn Anfrage zur Antwort gefunden wurde, sonst false.
     */
    private function SendQueueUpdate(\SqueezeBox\LMSResponse $LMSResponse): bool
    {
        if (!$this->lock('ReplyLMSData')) {
            throw new Exception($this->Translate('ReplyLMSData is locked'), E_USER_NOTICE);
        }
        $key = $LMSResponse->GetSearchPatter(); //Address . implode('', $LMSResponse->Command);
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
     * SendQueueRemove
     * Löscht einen Eintrag aus der SendQueue.
     *
     * @param int $Index Der Index des zu löschenden Eintrags.
     * @return void
     */
    private function SendQueueRemove(string $Index): void
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
     * SendDirect
     * Konvertiert $Data zu einem String und versendet diesen direkt an den LMS.
     *
     * @param \SqueezeBox\LMSData $LMSData Zu versendende Daten.
     * @return ?\SqueezeBox\LMSData \SqueezeBox\LMSData mit der Antwort. NULL im Fehlerfall.
     */
    private function SendDirect(\SqueezeBox\LMSData $LMSData): ?\SqueezeBox\LMSData
    {
        try {
            if ($this->GetStatus() != IS_ACTIVE) {
                throw new Exception($this->Translate('Instance inactive.'), E_USER_NOTICE);
            }
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
            if ($this->Host === '') {
                return null;
            }
        } catch (Exception $ex) {
            $this->SendDebug('Response Direct', $ex->getMessage(), 0);
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($ex->getMessage(), $ex->getCode());
            restore_error_handler();
            return null;
        }
        $Port = $this->ReadPropertyInteger(\SqueezeBox\Splitter\Property::Port);
        $User = $this->ReadPropertyString(\SqueezeBox\Splitter\Property::Username);
        $Pass = $this->ReadPropertyString(\SqueezeBox\Splitter\Property::Password);
        return $this->SendDirectToLMS($this->Host, $Port, $User, $Pass, $LMSData);
    }
}
