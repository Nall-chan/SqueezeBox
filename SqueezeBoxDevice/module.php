<?php

declare(strict_types=1);

/**
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.00
 *
 */
require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen
require_once __DIR__ . '/../libs/SqueezeBoxClass.php';  // diverse Klassen

eval('declare(strict_types=1);namespace SqueezeboxDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');

/**
 * SqueezeboxDevice Klasse für eine SqueezeBox-Instanz in IPS.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.00
 *
 * @property int $ParentID
 * @property array $Multi_Playlist Alle Datensätze der Playlisten
 * @property int $PlayerMode
 * @property int $PlayerTrackIndex
 * @property int $PlayerShuffle
 * @property int $PlayerTracks
 * @property int $PositionRAW
 * @property bool $isSeekable
 * @property int $DurationRAW
 * @property string $SyncMaster
 * @property string $SyncMembers
 * @property string $WebHookSecretTrack
 * @property resource|false $Socket
 * @method bool RegisterHook(string $WebHook)
 * @method void SetValueBoolean(string $Ident, bool $value)
 * @method void SetValueFloat(string $Ident, float $value)
 * @method void SetValueInteger(string $Ident, int $value)
 * @method void SetValueString(string $Ident, string $value)
 * @method void UnregisterProfile(string $Name)
 * @method int FindIDForIdent(string $Ident)
 * @method void RegisterParent()
 */
class SqueezeboxDevice extends IPSModuleStrict
{
    use \SqueezeBox\LMSHTMLTable,
        \SqueezeBox\LMSSongUrl,
        \SqueezeBox\LMSCover,
        \SqueezeBox\LSQProfile,
        \SqueezeBox\DebugHelper,
        \SqueezeboxDevice\VariableHelper,
        \SqueezeboxDevice\VariableProfileHelper,
        \SqueezeboxDevice\BufferHelper,
        \SqueezeboxDevice\InstanceStatus {
            \SqueezeboxDevice\InstanceStatus::MessageSink as IOMessageSink;
            \SqueezeboxDevice\InstanceStatus::RequestAction as IORequestAction;
        }
    private $Socket = false;

    /**
     * __destruct
     * schließt bei Bedarf den noch offenen TCP-Socket.
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->Socket) {
            fclose($this->Socket);
        }
    }

    /**
     * Create
     *
     * @return void
     */
    public function Create(): void
    {
        parent::Create();
        $this->ConnectParent('{96A9AB3A-2538-42C5-A130-FC34205A706A}');
        $this->SetReceiveDataFilter('.*"Address":"".*');
        $this->RegisterPropertyString('Address', '');
        $this->RegisterPropertyInteger('Interval', 2);
        $this->RegisterPropertyString('CoverSize', 'cover');
        $this->RegisterPropertyBoolean('enableBass', false);
        $this->RegisterPropertyBoolean('enableTreble', false);
        $this->RegisterPropertyBoolean('enablePitch', false);
        $this->RegisterPropertyBoolean('enableRandomplay', false);
        $this->RegisterPropertyBoolean('enableRawDuration', false);
        $this->RegisterPropertyBoolean('enableRawPosition', false);
        $this->RegisterPropertyBoolean('enablePreset', false);
        $this->RegisterPropertyBoolean('enableSleepTimer', false);
        $this->RegisterPropertyBoolean('showSleepTimeout', false);
        $this->RegisterPropertyBoolean('showSyncMaster', false);
        $this->RegisterPropertyBoolean('showSyncControl', false);
        $this->RegisterPropertyBoolean('showSignalstrength', false);
        $this->RegisterPropertyBoolean('showTilePlaylist', true);
        $this->RegisterPropertyBoolean('showHTMLPlaylist', false);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString('Table', json_encode($Style['Table']));
        $this->RegisterPropertyString('Columns', json_encode($Style['Columns']));
        $this->RegisterPropertyString('Rows', json_encode($Style['Rows']));
        $this->RegisterPropertyBoolean('changeName', false);

        $this->Multi_Playlist = [];
        $this->ParentID = 0;
        $this->PlayerMode = 0;
        $this->PlayerShuffle = 0;
        $this->PlayerTrackIndex = 0;
        $this->DurationRAW = 0;
        $this->isSeekable = false;
        $this->SyncMaster = '';
        $this->SyncMembers = '';
    }

    /**
     * Migrate
     *
     * @param  string $JSONData
     * @return string
     */
    public function Migrate(string $JSONData): string
    {
        $Data = json_decode($JSONData);
        if (property_exists($Data->configuration, 'showPlaylist')) {
            $Data->configuration->showHTMLPlaylist = $Data->configuration->showPlaylist;
            /**
             * @todo Migrate Statusvariables Types an Profiles?
             */
            $vid = $this->FindIDForIdent('Interpret');
            if ($vid > 0) { //Migrate Statusvariable Interpret to Artist
                @IPS_SetIdent($vid, 'Artist');
            }
            $vid = $this->FindIDForIdent('Playlist');
            if ($vid > 0) { //Migrate Statusvariable Playlist to HTMLPlaylist
                @IPS_SetIdent($vid, 'HTMLPlaylist');
            }
            $this->SendDebug('Migrate', json_encode($Data), 0);
            $this->LogMessage('Migrated settings:' . json_encode($Data), KL_MESSAGE);
        }
        return json_encode($Data);
    }

    /**
     * Destroy
     *
     * @return void
     */
    public function Destroy(): void
    {
        if (IPS_GetKernelRunlevel() != KR_READY) {
            parent::Destroy();
            return;
        }
        if (!IPS_InstanceExists($this->InstanceID)) {
            $CoverID = @IPS_GetObjectIDByIdent('CoverIMG', $this->InstanceID);
            if ($CoverID > 0) {
                @IPS_DeleteMedia($CoverID, true);
            }
            $this->DeleteProfile();
        }
        parent::Destroy();
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        $this->SetReceiveDataFilter('.*"Address":"".*');
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        $this->RegisterMessage($this->InstanceID, IM_CHANGESTATUS);
        $this->Multi_Playlist = [];
        $this->ParentID = 0;
        $this->PlayerMode = 0;
        $this->PlayerShuffle = 0;
        $this->PlayerTrackIndex = 0;
        $this->DurationRAW = 0;
        $this->isSeekable = false;
        $this->SyncMaster = '';
        $this->SyncMembers = '';
        parent::ApplyChanges();
        $Address = $this->ReadPropertyString('Address');
        // Adresse als Filter setzen
        $this->SetReceiveDataFilter('.*"Address":"' . $Address . '".*');
        $this->SetSummary($Address);

        // Profile anlegen
        $this->CreateProfile();

        //Status-Variablen anlegen & Profile updaten
        $this->UnregisterVariable('Connected');

        if (preg_match('/\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b/', $Address) !== 1) {
            $this->RegisterVariableBoolean('Power', 'Power', '~Switch', 1);
            $this->EnableAction('Power');
            if ($this->ReadPropertyBoolean('enablePreset')) {
                $this->RegisterVariableInteger('Preset', 'Preset', 'LSQ.Preset', 2);
                $this->EnableAction('Preset');
            } else {
                $this->UnregisterVariable('Preset');
            }
            $this->RegisterVariableBoolean('Mute', 'Mute', '~Mute', 4);
            $this->EnableAction('Mute');
            $this->RegisterVariableInteger('Volume', 'Volume', '~Volume', 5);
            $this->EnableAction('Volume');
            if ($this->ReadPropertyBoolean('enableBass')) {
                $this->RegisterVariableInteger('Bass', 'Bass', '~Intensity.100', 6);
                $this->EnableAction('Bass');
            } else {
                $this->UnregisterVariable('Bass');
            }
            if ($this->ReadPropertyBoolean('enableTreble')) {
                $this->RegisterVariableInteger('Treble', $this->Translate('Treble'), '~Intensity.100', 7);
                $this->EnableAction('Treble');
            } else {
                $this->UnregisterVariable('Treble');
            }
            if ($this->ReadPropertyBoolean('enablePitch')) {
                $this->RegisterVariableInteger('Pitch', $this->Translate('Pitch'), 'LSQ.Pitch', 8);
                $this->EnableAction('Pitch');
            } else {
                $this->UnregisterVariable('Pitch');
            }
            if ($this->ReadPropertyBoolean('showSyncMaster')) {
                $this->RegisterVariableBoolean('Master', $this->Translate('Master'), '', 14);
            } else {
                $this->UnregisterVariable('Master');
            }
            if ($this->ReadPropertyBoolean('showSyncControl')) {
                $this->RegisterProfileIntegerEx('LSQ.Sync.' . $this->InstanceID, 'Speaker-100', '', '', [
                    [0, $this->Translate('Off'), '', -1],
                    [100, $this->Translate('On'), '', 0x00ff00]
                ]);
                $this->RegisterVariableInteger('Sync', $this->Translate('Synchronize'), 'LSQ.Sync.' . $this->InstanceID, 15);
                $this->EnableAction('Sync');
            } else {
                $this->UnregisterVariable('Sync');
            }
            if ($this->ReadPropertyBoolean('showSignalstrength')) {
                $this->RegisterVariableInteger('Signalstrength', $this->Translate('Signal strength'), '~Intensity.100', 31);
            } else {
                $this->UnregisterVariable('Signalstrength');
            }
        } else {
            $this->UnregisterVariable('Power');
            $this->UnregisterVariable('Preset');
            $this->UnregisterVariable('Mute');
            $this->UnregisterVariable('Volume');
            $this->UnregisterVariable('Bass');
            $this->UnregisterVariable('Treble');
            $this->UnregisterVariable('Pitch');
            $this->UnregisterVariable('Signalstrength');
            $this->UnregisterVariable('Master');
            $this->UnregisterVariable('Sync');
        }
        $this->RegisterVariableInteger('Status', $this->Translate('State'), '~PlaybackPreviousNext', 3);
        $this->PlayerMode = $this->FindIDForIdent('Status');
        $this->RegisterMessage($this->PlayerMode, VM_UPDATE);
        $this->EnableAction('Status');

        if ($this->ReadPropertyBoolean('enableRandomplay')) {
            $this->RegisterVariableInteger('Randomplay', $this->Translate('Randomplay'), 'LSQ.Randomplay', 13);
            $this->EnableAction('Randomplay');
        } else {
            $this->UnregisterVariable('Randomplay');
        }
        if ($this->ReadPropertyBoolean('enableRawDuration')) {
            $this->RegisterVariableInteger('DurationRaw', $this->Translate('Duration in seconds'), '', 28);
        } else {
            $this->UnregisterVariable('DurationRaw');
        }
        if ($this->ReadPropertyBoolean('enableRawPosition')) {
            $this->RegisterVariableInteger('PositionRaw', $this->Translate('Position in seconds'), '', 29);
            $this->EnableAction('PositionRaw');
        } else {
            $this->UnregisterVariable('PositionRaw');
        }
        $this->RegisterVariableInteger('Shuffle', $this->Translate('Shuffle'), 'LSQ.Shuffle', 9);
        $this->PlayerShuffle = $this->FindIDForIdent('Shuffle');
        $this->EnableAction('Shuffle');
        $this->RegisterMessage($this->PlayerShuffle, VM_UPDATE);
        $this->RegisterVariableInteger('Repeat', $this->Translate('Repeat'), '~Repeat', 10);
        $this->EnableAction('Repeat');
        $this->RegisterVariableInteger('Tracks', $this->Translate('Tracks in Playlist'), '', 11);
        $this->PlayerTracks = $this->FindIDForIdent('Tracks');
        $this->RegisterMessage($this->PlayerTracks, VM_UPDATE);
        $this->RegisterProfileInteger('LSQ.Tracklist.' . $this->InstanceID, '', '', '', (($this->GetValue('Tracks') == 0) ? 0 : 1), $this->GetValue('Tracks'), 1);
        $this->RegisterVariableInteger('Index', $this->Translate('Playlist current position'), 'LSQ.Tracklist.' . $this->InstanceID, 12);
        $this->PlayerTrackIndex = $this->FindIDForIdent('Index');
        $this->EnableAction('Index');
        $this->RegisterMessage($this->PlayerTrackIndex, VM_UPDATE);
        $this->RegisterVariableString('Playlistname', 'Name of Playlist', '', 19);
        $this->RegisterVariableString('Album', 'Album', '', 20);
        $this->RegisterVariableString('Title', $this->Translate('Title'), '~Song', 21);
        $this->RegisterVariableString('Artist', $this->Translate('Artist'), '~Artist', 22);
        $this->RegisterVariableString('Genre', $this->Translate('Genre'), '', 23);
        $this->RegisterVariableString('Duration', $this->Translate('Duration'), '', 24);
        $this->RegisterVariableString('Position', $this->Translate('Position'), '', 25);
        $this->RegisterVariableFloat('Position2', 'Position', '~Progress', 26);
        $this->DisableAction('Position2');
        if ($this->ReadPropertyBoolean('enableSleepTimer')) {
            $this->RegisterVariableInteger('SleepTimer', $this->Translate('Sleep timer'), 'LSQ.SleepTimer', 32);
            $this->EnableAction('SleepTimer');
        } else {
            $this->UnregisterVariable('SleepTimer');
        }
        if ($this->ReadPropertyBoolean('showSleepTimeout')) {
            $this->RegisterVariableString('SleepTimeout', $this->Translate('Switch off in'), '', 33);
        } else {
            $this->UnregisterVariable('SleepTimeout');
        }

        // Playlist
        if ($this->ReadPropertyBoolean('showTilePlaylist')) {
            $this->RegisterVariableString('TilePlaylist', 'Playlist', '~Playlist', 34);
            $this->EnableAction('TilePlaylist');
        } else {
            $this->UnregisterVariable('TilePlaylist');
        }
        if ($this->ReadPropertyBoolean('showHTMLPlaylist')) {
            $this->RegisterVariableString('HTMLPlaylist', 'Playlist', '~HTMLBox', 30);
        } else {
            $this->UnregisterVariable('HTMLPlaylist');
        }

        // Wenn Kernel nicht bereit, dann warten... wenn unser IO Aktiv wird, holen wir unsere Daten :)
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Playlist
        if ($this->ReadPropertyBoolean('showHTMLPlaylist')) {
            $this->RegisterHook('/hook/SqueezeBoxPlaylist' . $this->InstanceID);
        }
        // Wenn Parent aktiv, dann Anmeldung an der Hardware bzw. Datenabgleich starten
        $this->RegisterParent();
        if ($this->HasActiveParent() && (trim($Address) != '')) {
            $this->IOChangeState(IS_ACTIVE);
        } else {
            $this->IOChangeState(IS_INACTIVE);
        }
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
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            case IM_CHANGESTATUS:
                if ($SenderID == $this->InstanceID) {
                    if ($Data[0] == IS_ACTIVE) {
                        if ($this->HasActiveParent()) {
                            $this->RequestAllState();
                        }
                    } else {
                        $this->_SetNewPower(false);
                        $this->SetCover();
                    }
                }
                break;
            case VM_UPDATE:
                if ($SenderID == $this->PlayerMode) {
                    if ($Data[0] == 2) {
                        $this->_StartSubscribe();
                    } else {
                        $this->_StopSubscribe();
                    }
                }
                if ($SenderID == $this->PlayerShuffle) {
                    $this->_RefreshPlaylist();
                }
                if ($SenderID == $this->PlayerTrackIndex) {
                    $this->_RefreshPlaylistIndex();
                }
                if ($SenderID == $this->PlayerTracks) {
                    if ($Data[0] == 0) {
                        $this->RegisterProfileInteger('LSQ.Tracklist.' . $this->InstanceID, '', '', '', 0, 0, 1);
                        $this->_RefreshPlaylist(true);
                    } else {
                        $this->RegisterProfileInteger('LSQ.Tracklist.' . $this->InstanceID, '', '', '', 1, $Data[0], 1);
                        $this->_RefreshPlaylist();
                    }
                }
                break;
        }
    }

    //################# PUBLIC

    /**
     * RequestAllState
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen..
     *
     * @return bool
     */
    public function RequestAllState(): bool
    {
        //connected und Power nicht erlaubt sonst schleife
        if (!$this->RequestState('Name')) {
            return false;
        }
        $this->RequestState('Power');
        $this->RequestState('Status');
        $this->RequestState('Sync');
        $this->RequestState('Remote');
        $this->RequestState('Mute');
        $this->RequestState('Volume');
        if ($this->ReadPropertyBoolean('enableBass')) {
            $this->RequestState('Bass');
        }
        if ($this->ReadPropertyBoolean('enableTreble')) {
            $this->RequestState('Treble');
        }
        if ($this->ReadPropertyBoolean('enablePitch')) {
            $this->RequestState('Pitch');
        }
        $this->RequestState('Shuffle');
        $this->RequestState('Repeat');
        $this->RequestState('Tracks');
        $this->RequestState('Index');
        $this->RequestState('Playlistname');
        $this->RequestState('Album');
        $this->RequestState('Title');
        $this->RequestState('Artist');
        $this->RequestState('Genre');
        $this->RequestState('Duration');
        $this->RequestState('Position');
        if ($this->ReadPropertyBoolean('showSignalstrength')) {
            $this->RequestState('Signalstrength');
        }
        if ($this->ReadPropertyBoolean('showSleepTimeout')) {
            $this->RequestState('SleepTimeout');
        }
        $this->RequestState('Randomplay');
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['status', '-', 1], ['tags:gladiqrRtueJINpsy', 'subscribe:0']));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $this->DecodeLMSResponse($LMSData);
        if ($this->GetValue('Status') == 2) {
            $this->_StartSubscribe();
        }
        return true;
    }

    /**
     * RequestState
     * IPS-Instanz-Funktion 'LSQ_RequestState'.
     * Fragt einen Wert des Players ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @param string $Ident Der Ident der abzufragenden Statusvariable.
     * @return bool True wenn erfolgreich.
     */
    public function RequestState(string $Ident): bool
    {
        switch ($Ident) {
            case 'Name':
                $LMSResponse = new \SqueezeBox\LMSData('name', '?');
                break;
            case 'Power':
                $LMSResponse = new \SqueezeBox\LMSData('power', '?');
                break;
            case 'Status':
                if (!$this->_isPlayerOn()) {
                    $this->_SetModeToStop();
                    return true;
                }
                $LMSResponse = new \SqueezeBox\LMSData('mode', '?');
                break;
            case 'Mute':
                $LMSResponse = new \SqueezeBox\LMSData(['mixer', 'muting'], '?');
                break;
            case 'Volume':
                $LMSResponse = new \SqueezeBox\LMSData(['mixer', 'power'], '?');
                break;
            case 'Bass':
                if (!$this->ReadPropertyBoolean('enableBass')) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Invalid ident'));
                    restore_error_handler();
                    return false;
                }
                $LMSResponse = new \SqueezeBox\LMSData(['mixer', 'bass'], '?');
                break;
            case 'Treble':
                if (!$this->ReadPropertyBoolean('enableTreble')) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Invalid ident'));
                    restore_error_handler();
                    return false;
                }
                $LMSResponse = new \SqueezeBox\LMSData(['mixer', 'treble'], '?');
                break;
            case 'Pitch':
                if (!$this->ReadPropertyBoolean('enablePitch')) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Invalid ident'));
                    restore_error_handler();
                    return false;
                }
                $LMSResponse = new \SqueezeBox\LMSData(['mixer', 'pitch'], '?');
                break;
            case 'Shuffle':
                $LMSResponse = new \SqueezeBox\LMSData(['playlist', 'shuffle'], '?');
                break;
            case 'Repeat':
                $LMSResponse = new \SqueezeBox\LMSData(['playlist', 'repeat'], '?');
                break;
            case 'Tracks':
                $LMSResponse = new \SqueezeBox\LMSData(['playlist', 'tracks'], '?');
                break;
            case 'Index':
                $LMSResponse = new \SqueezeBox\LMSData(['playlist', 'index'], '?');
                break;
            case 'Playlistname':
                $LMSResponse = new \SqueezeBox\LMSData(['playlist', 'name'], '?');
                break;
            case 'Album':
                $LMSResponse = new \SqueezeBox\LMSData('album', '?');
                break;
            case 'Title':
                $LMSResponse = new \SqueezeBox\LMSData('title', '?');
                break;
            case 'Artist':
                $LMSResponse = new \SqueezeBox\LMSData('artist', '?');
                break;
            case 'Genre':
                $LMSResponse = new \SqueezeBox\LMSData('genre', '?');
                break;
            case 'Duration':
                $LMSResponse = new \SqueezeBox\LMSData('duration', '?');
                break;
            case 'Position2':
            case 'Position':
                $LMSResponse = new \SqueezeBox\LMSData('time', '?');
                break;
            case 'Signalstrength':
                $LMSResponse = new \SqueezeBox\LMSData('signalstrength', '?');
                break;
            case 'SleepTimeout':
                $LMSResponse = new \SqueezeBox\LMSData('sleep', '?');
                break;
            case 'Connected':
                $LMSResponse = new \SqueezeBox\LMSData('connected', '?');
                break;
            case 'Sync':
                $LMSResponse = new \SqueezeBox\LMSData('sync', '?');
                break;
            case 'Remote':
                $LMSResponse = new \SqueezeBox\LMSData('remote', '?');
                break;
            case 'Randomplay':
                $LMSResponse = new \SqueezeBox\LMSData('randomplayisactive', '');
                break;
            default:
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Invalid ident'));
                restore_error_handler();
                return false;
        }
        $LMSResponse = $this->SendDirect($LMSResponse);
        if ($LMSResponse == null) {
            return false;
        }
        if ($LMSResponse->Data[0] == '?') {
            return false;
        }
        return $this->DecodeLMSResponse($LMSResponse);
    }

    /**
     * SetSync
     *
     * IPS-Instanz-Funktion 'LSQ_SetSync'.
     * @param  int $SlaveInstanceID
     * @return bool
     */
    public function SetSync(int $SlaveInstanceID): bool
    {
        $id = @IPS_GetInstance($SlaveInstanceID);
        if ($id === false) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Unknown LSQ_PlayerInstanz'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if ($id['ModuleInfo']['ModuleID'] != '{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('SlaveInstance in not a LSQ_PlayerInstanz'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if ($id['ConnectionID'] != $this->ParentID) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Player is not connected to this LMS.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $ClientMac = IPS_GetProperty($SlaveInstanceID, 'Address');
        if (($ClientMac === '') || ($ClientMac === false)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('SlaveInstance Address is not set.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $ret = $this->SendDirect(new \SqueezeBox\LMSData('sync', $ClientMac));
        return $ret->Data[0] == $ClientMac;
    }

    /**
     * GetSync
     *
     * IPS-Instanz-Funktion 'LSQ_GetSync'.
     * Gibt alle mit diesem Gerät synchronisierte Instanzen zurück.
     * @return array
     */
    public function GetSync(): array
    {
        $FoundInstanzIDs = [];
        $ret = $this->SendDirect(new \SqueezeBox\LMSData('sync', '?'))->Data[0];
        if ($ret != '-') {
            $Search = explode(',', $ret);
            $Addresses = $this->_GetAllPlayers();
            foreach ($Search as $Value) {
                $FoundInstanzIDs[] = array_search($Value, $Addresses);
            }
        }
        return $FoundInstanzIDs;
    }

    /**
     * SetUnSync
     * IPS-Instanz-Funktion 'LSQ_SetUnSync'.
     * Sync dieses Gerätes aufheben.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetUnSync(): bool
    {
        $ret = $this->SendDirect(new \SqueezeBox\LMSData('sync', '-'))->Data[0];
        return $ret == '-';
    }
    ///////////////////////////////////////////////////////////////
    // START PLAYER
    ///////////////////////////////////////////////////////////////

    /**
     * SetName
     * Setzten den Namen in dem Device.
     * IPS-Instanz-Funktion 'LSQ_SetName'.
     *
     * @param string $Name Neuer Name
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetName(string $Name): bool
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('name', $Name));
        if ($LMSData === null) {
            return false;
        }
        $this->_SetNewName($LMSData->Data[0]);
        return $LMSData->Data[0] == $Name;
    }

    /**
     * GetName
     * IPS-Instanz-Funktion 'LSQ_GetName'.
     * Liefert den Namen von dem Device.
     *
     * @return false|string Name vom Device
     */
    public function GetName(): false|string
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('name', '?'));
        if ($LMSData === null) {
            return false;
        }
        $this->_SetNewName($LMSData->Data[0]);
        return $LMSData->Data[0];
    }

    /**
     * SetPower
     * IPS-Instanz-Funktion 'LSQ_SetPower'.
     * Schaltet das Gerät ein oder aus.
     *
     * @param bool $Value
     *                    false  = ausschalten
     *                    true = einschalten
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function Power(bool $Value): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('power', (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == (int) $Value;
    }

    /**
     * SetSleep
     * Restzeit bis zum Sleep setzen.
     *
     * @param int $Seconds Sekunden bis zum ausschalten
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetSleep(int $Seconds): bool
    {
        if ($Seconds < 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Seconds invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('sleep', $Seconds));
        if ($LMSData === null) {
            return false;
        }
        $this->_SetNewSleepTimeout((int) $LMSData->Data[0]);
        return (int) $LMSData->Data[0] == $Seconds;
    }

    /**
     * SetMute
     * Setzten der Stummschaltung.
     *
     * @param boolean $Value
     *                      true = Stumm an
     *                      false = Stumm aus
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetMute(bool $Value): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'muting'], (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == (int) $Value;
    }

    /**
     * SetVolume
     * Setzten der Lautstärke.
     *
     * @param int $Value
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetVolume(int $Value): bool
    {
        if (($Value < 0) || ($Value > 100)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'volume'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * SetVolumeEx
     * Setzten der Lautstärke.
     *
     * @param string $Value
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetVolumeEx(string $Value): bool
    {
        if (($Value[0] != '-') && ($Value[0] != '+')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (((int) $Value < -100) || ((int) $Value > 100)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'volume'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * SetBass
     * Setzt den Bass-Wert.
     *
     * @param int $Value
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetBass(int $Value): bool
    {
        if (!$this->ReadPropertyBoolean('enableBass')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('bass control not enabled'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (($Value < 0) || ($Value > 100)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'bass'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * SetBassEx
     * Setzt den Bass-Wert.
     *
     * @param string $Value
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetBassEx(string $Value): bool
    {
        if (!$this->ReadPropertyBoolean('enableBass')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('bass control not enabled'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (($Value[0] != '-') && ($Value[0] != '+')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (((int) $Value < -100) || ((int) $Value > 100)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'bass'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * SetTreble
     * Setzt den Treble-Wert.
     *
     * @param int $Value
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetTreble(int $Value): bool
    {
        if (!$this->ReadPropertyBoolean('enableTreble')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('treble control not enabled'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (($Value < 0) || ($Value > 100)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'treble'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * SetTrebleEx
     * Setzt den Treble-Wert.
     *
     * @param string $Value
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetTrebleEx(string $Value): bool
    {
        if (!$this->ReadPropertyBoolean('enableTreble')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('treble control not enabled'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (($Value[0] != '-') && ($Value[0] != '+')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (((int) $Value < -100) || ((int) $Value > 100)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'treble'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * SetPitch
     * Setzt den Pitch-Wert.
     *
     * @param int $Value
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetPitch(int $Value): bool
    {
        if (!$this->ReadPropertyBoolean('enablePitch')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('pitch control not enabled'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (($Value < 80) || ($Value > 120)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'pitch'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * SetPitchEx
     * Setzt den Pitch-Wert.
     *
     * @param string $Value
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetPitchEx(string $Value): bool
    {
        if (!$this->ReadPropertyBoolean('enablePitch')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('pitch control not enabled'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (($Value[0] != '-') && ($Value[0] != '+')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (((int) $Value < -40) || ((int) $Value > 40)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'pitch'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * PreviousButton
     * Simuliert einen Tastendruck.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function PreviousButton(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('button', 'jump_rew'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == 'jump_rew';
    }

    /**
     * NextButton
     * Simuliert einen Tastendruck.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function NextButton(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('button', 'jump_fwd'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == 'jump_fwd';
    }

    /**
     * SelectPreset
     * Simuliert einen Tastendruck auf einen der Preset-Tasten.
     *
     * @param int $Value 1 - 10
     * @return bool  true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SelectPreset(int $Value): bool
    {
        if (($Value < 1) || ($Value > 10)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $Value = 'preset_' . $Value . '.single';
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('button', $Value));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == $Value;
    }

    /**
     * Play
     * Startet die Wiedergabe.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function Play(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('play', ''));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * PlayEx
     * Startet die Wiedergabe.
     *
     * @param int $FadeIn Einblendezeit
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function PlayEx(int $FadeIn): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('play', (int) $FadeIn));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * Stop
     * Stoppt die Wiedergabe.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function Stop(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('stop', ''));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * Pause
     * Pausiert die Wiedergabe.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function Pause(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('pause', '1'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == '1';
    }

    /**
     * SetPosition
     * Setzt eine absolute Zeit-Position des aktuellen Titels.
     *
     * @param int $Value Zeit in Sekunden.
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung.
     */
    public function SetPosition(int $Value): bool
    {
        if ($Value > $this->DurationRAW) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value greater as duration'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('time', $Value));
        if ($LMSData === null) {
            return false;
        }
        return $Value == $LMSData->Data[0];
    }

    /**
     * DisplayLine
     *
     * @param  string $Text
     * @param  int $Duration
     * @return bool
     */
    public function DisplayLine(string $Text, int $Duration): bool
    {
        return $this->DisplayLines('', $Text, true, $Duration);
    }

    /**
     * DisplayLineEx
     *
     * @param  string $Text
     * @param  int $Duration
     * @param  bool $Centered
     * @param  int $Brightness
     * @return bool
     */
    public function DisplayLineEx(string $Text, int $Duration, bool $Centered, int $Brightness): bool
    {
        return $this->DisplayLines('', $Text, true, $Duration, $Centered, $Brightness);
    }

    /**
     * Display2Lines
     *
     * @param  string $Text1
     * @param  string $Text2
     * @param  int $Duration
     * @return bool
     */
    public function Display2Lines(string $Text1, string $Text2, int $Duration): bool
    {
        return $this->DisplayLines($Text1, $Text2, false, $Duration);
    }

    /**
     * Display2LinesEx
     *
     * @param  string $Text1
     * @param  string $Text2
     * @param  int $Duration
     * @param  bool $Centered
     * @param  int $Brightness
     * @return bool
     */
    public function Display2LinesEx(string $Text1, string $Text2, int $Duration, bool $Centered, int $Brightness)
    {
        return $this->DisplayLines($Text1, $Text2, false, $Duration, $Centered, $Brightness);
    }

    /**
     * DisplayText
     *
     * @param  string $Text1
     * @param  string $Text2
     * @param  int $Duration
     * @return bool
     */
    public function DisplayText(string $Text1, string $Text2, int $Duration): bool
    {
        $Duration = ($Duration < 3 ? 3 : $Duration);
        if ($Text1 == '') {
            $Values[] = ' ';
        } else {
            $Values[] = $Text1;
        }

        if ($Text2 == '') {
            $Values[] = ' ';
        } else {
            $Values[] = $Text2;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('display', $Values));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * GetLinesPerScreen
     *
     * @return int
     */
    public function GetLinesPerScreen(): int
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('linesperscreen', '?'));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0];
    }

    /**
     * GetDisplayedText
     *
     * @return string|array
     */
    public function GetDisplayedText(): string|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('display', ['?', '?']));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data;
    }

    /**
     * GetDisplayedNow
     *
     * @return string|array
     */
    public function GetDisplayedNow(): string|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('displaynow', ['?', '?']));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data;
    }

    /**
     * PressButton
     *
     * @param  string $ButtonCode
     * @return bool
     */
    public function PressButton(string $ButtonCode): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('button', $ButtonCode));
        if ($LMSData === null) {
            return false;
        }
        return $ButtonCode == $LMSData->Data[0];
    }

    ///////////////////////////////////////////////////////////////
    // ENDE PLAYER
    ///////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////
    // START PLAYLIST
    ///////////////////////////////////////////////////////////////

    /**
     * PlayUrl
     * Lädt eine neue Playlist.
     *
     * @param string $URL SongURL, Verzeichnis oder Remote-Stream
     * @return bool
     */
    public function PlayUrl(string $URL): bool
    {
        return $this->PlayUrlEx($URL, '');
    }

    /**
     * PlayUrlEx
     *
     * @param  string $URL
     * @param  string $DisplayTitle
     * @return bool
     */
    public function PlayUrlEx(string $URL, string $DisplayTitle): bool
    {
        if ($this->GetValidSongURL($URL) == false) {
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'play'], [$URL, $DisplayTitle]));
        if ($LMSData === null) {
            return false;
        }
        return strpos($URL, str_replace('\\', '/', $LMSData->Data[0])) !== false;
    }

    /**
     * PlayUrlSpecial
     *
     * @param  string $URL
     * @return bool
     */
    public function PlayUrlSpecial(string $URL): bool
    {
        return $this->PlayUrlSpecialEx($URL, '');
    }

    /**
     * PlayUrlSpecialEx
     *
     * @param  string $URL
     * @param  string $DisplayTitle
     * @return bool
     */
    public function PlayUrlSpecialEx(string $URL, string $DisplayTitle): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'play'], [$URL, $DisplayTitle]));
        if ($LMSData === null) {
            return false;
        }
        return strpos($URL, str_replace('\\', '/', $LMSData->Data[0])) !== false;
    }

    /**
     * PlayFavorite
     *
     * @param  string $FavoriteID
     * @return bool
     */
    public function PlayFavorite(string $FavoriteID): bool
    {
        if ($FavoriteID == '') {
            $Data = ['play', 'item_id:.'];
        } else {
            $Data = ['play', 'item_id:' . rawurlencode($FavoriteID)];
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'playlist'], $Data));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * AddToPlaylistByUrl
     * Am Ende hinzufügen.
     *
     * @param string $URL
     * @return bool
     */
    public function AddToPlaylistByUrl(string $URL): bool
    {
        return $this->AddToPlaylistByUrlEx($URL, '');
    }

    /**
     * AddToPlaylistByUrlEx
     *
     * @param  string $URL
     * @param  string $DisplayTitle
     * @return bool
     */
    public function AddToPlaylistByUrlEx(string $URL, string $DisplayTitle): bool
    {
        if ($this->GetValidSongURL($URL) == false) {
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'add'], [$URL, $DisplayTitle]));
        if ($LMSData === null) {
            return false;
        }

        return strpos($URL, str_replace('\\', '/', $LMSData->Data[0])) !== false;
        // todo für neue funktion InsertAt
        //neue Tracks holen
        // alt = 5
        // neu = 10
        // ToMove = neu - alt = 5 hinzufügt.
        // Position = 3
        // move alt(5, = erster neuer) to Position (3)
        // move alt+1 to position+1
        //etc..
    }

    /**
     * DeleteFromPlaylistByUrl
     *
     * @param  string $URL
     * @return bool
     */
    public function DeleteFromPlaylistByUrl(string $URL): bool
    {
        if ($this->GetValidSongURL($URL) == false) {
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'deleteitem'], $URL));
        if ($LMSData === null) {
            return false;
        }
        return strpos($URL, str_replace('\\', '/', $LMSData->Data[0])) !== false;
        // todo für neue funktion InsertAt
        //neue Tracks holen
        // alt = 5
        // neu = 10
        // ToMove = neu - alt = 5 hinzufügt.
        // Position = 3
        // move alt(5, = erster neuer) to Position (3)
        // move alt+1 to position+1
        //etc..
    }

    /**
     * MoveSongInPlaylist
     *
     * @param  int $Position
     * @param  int $NewPosition
     * @return bool
     */
    public function MoveSongInPlaylist(int $Position, int $NewPosition): bool
    {
        $Position--;
        $NewPosition--;
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'move'], [$Position, $NewPosition]));
        if ($LMSData === null) {
            return false;
        }
        if (($LMSData->Data[0] != $Position) || ($LMSData->Data[1] != $NewPosition)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Error on move song in playlist.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return true;
    }

    /**
     * DeleteFromPlaylistByIndex
     * The "playlist delete" command deletes the song at the specified index from the current playlist.
     *
     * @param  int $Position
     * @return bool
     */
    public function DeleteFromPlaylistByIndex(int $Position): bool
    {
        $Position--;
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'delete'], $Position));
        if ($LMSData == null) {
            return false;
        }
        if (count($LMSData->Data) > 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Error delete song from playlist.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return $LMSData->Data[0] == $Position + 1;
    }

    /**
     * PreviewPlaylistStart
     *
     * @param  string $Name
     * @return bool
     */
    public function PreviewPlaylistStart(string $Name): bool
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'preview'], 'url:' . $Name));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == 'url:' . $Name;
    }

    /**
     * PreviewPlaylistStop
     *
     * @return bool
     */
    public function PreviewPlaylistStop(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'preview'], 'cmd:stop'));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * SavePlaylist
     * Speichert die aktuelle Wiedergabeliste vom Gerät in einer unter $Name angegebenen Wiedergabelisten-Datei auf dem LMS-Server.
     *
     * @param string $Name Der Name der Wiedergabeliste. Ist diese Liste auf dem Server schon vorhanden, wird sie überschrieben.
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SavePlaylist(string $Name): bool
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'save'], [$Name, 'silent:1']));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == $Name;
    }

    /**
     * SaveTempPlaylist
     * Speichert die aktuelle Wiedergabeliste vom Gerät in einer festen Wiedergabelisten-Datei auf dem LMS-Server.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SaveTempPlaylist(): bool
    {
        return $this->SavePlaylist('tempplaylist_' . str_replace(':', '', $this->ReadPropertyString('Address')));
    }

    /**
     * ResumePlaylist
     * Lädt eine Wiedergabelisten-Datei aus dem LMS-Server und spring an die zuletzt abgespielten Track.
     *
     * @param string $Name  Der Name der Wiedergabeliste.
     * @return bool|string  Kompletter Pfad der Wiedergabeliste
     */
    public function ResumePlaylist(string $Name): bool|string
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'resume'], [$Name, 'noplay:1']));
        if ($LMSData === null) {
            return false;
        }
        $ret = $LMSData->Data[0];
        if (($ret == '/' . $Name) || ($ret == '\\' . $Name)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Playlist not found.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return $ret;
    }

    /**
     * LoadTempPlaylist
     * Lädt eine zuvor gespeicherte Wiedergabelisten-Datei und setzt die Wiedergabe fort.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function LoadTempPlaylist(): bool
    {
        $Playlist = 'tempplaylist_' . str_replace(':', '', $this->ReadPropertyString('Address'));
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'resume'], [$Playlist, 'wipePlaylist:1', 'noplay:0']));
        if ($LMSData === null) {
            return false;
        }
        if ($LMSData->Data[0] != $Playlist) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('TempPlaylist not found.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return true;
    }

    /**
     * LoadPlaylist
     * Lädt eine Wiedergabelisten-Datei aus dem LMS-Server und startet die Wiedergabe derselben auf dem Gerät.
     *
     * @param string $Name Der Name der Wiedergabeliste. Eine URL zu einem Stream, einem Verzeichnis oder einer Datei
     * @return bool|string Kompletter Pfad der Wiedergabeliste
     */
    public function LoadPlaylist(string $Name): bool|string
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'load'], [$Name, 'noplay:1']));
        if ($LMSData === null) {
            return false;
        }
        $ret = $LMSData->Data[0];
        if (($ret == '/' . $Name) || ($ret == '\\' . $Name)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Playlist not found.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return $ret;
    }

    /**
     * LoadPlaylistBySearch
     *
     * @param  string $Genre
     * @param  string $Artist
     * @param  string $Album
     * @return bool
     */
    public function LoadPlaylistBySearch(string $Genre, string $Artist, string $Album): bool
    {
        if ($Genre == '') {
            $Genre = '*';
        }
        if ($Artist == '') {
            $Artist = '*';
        }
        if ($Album == '') {
            $Album = '*';
        }
        if (($Genre == '*') && ($Genre == '*') && ($Genre == '*')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('One search patter is required'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'loadalbum'], [$Genre, $Artist, $Album]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * AddToPlaylistBySearch
     *
     * @param  string $Genre
     * @param  string $Artist
     * @param  string $Album
     * @return bool
     */
    public function AddToPlaylistBySearch(string $Genre, string $Artist, string $Album): bool
    {
        if ($Genre == '') {
            $Genre = '*';
        }
        if ($Artist == '') {
            $Artist = '*';
        }
        if ($Album == '') {
            $Album = '*';
        }
        if (($Genre == '*') && ($Genre == '*') && ($Genre == '*')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('One search patter is required'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'addalbum'], [$Genre, $Artist, $Album]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * LoadPlaylistByTrackTitel
     *
     * @param  string $Titel
     * @return bool
     */
    public function LoadPlaylistByTrackTitel(string $Titel): bool
    {
        if ($Titel == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'loadtracks'], 'track.titlesearch=' . $Titel));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * LoadPlaylistByAlbumTitel
     *
     * @param  string $Titel
     * @return bool
     */
    public function LoadPlaylistByAlbumTitel(string $Titel): bool
    {
        if ($Titel == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'loadtracks'], 'album.titlesearch=' . $Titel));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * LoadPlaylistByArtistName
     *
     * @param  string $Name
     * @return bool
     */
    public function LoadPlaylistByArtistName(string $Name): bool
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'loadtracks'], 'contributor.namesearch=' . $Name));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * LoadPlaylistByFavoriteID
     *
     * @param  string $FavoriteID
     * @return bool
     */
    public function LoadPlaylistByFavoriteID(string $FavoriteID): bool
    {
        if ($FavoriteID == '') {
            $Data = ['load', 'item_id:.'];
        } else {
            $Data = ['load', 'item_id:' . rawurlencode($FavoriteID)];
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'playlist'], $Data));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * AddToPlaylistByTrackTitel
     *
     * @param  string $Titel
     * @return bool
     */
    public function AddToPlaylistByTrackTitel(string $Titel): bool
    {
        if ($Titel == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'addtracks'], 'track.titlesearch=' . $Titel));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * AddToPlaylistByAlbumTitel
     *
     * @param  string $Titel
     * @return bool
     */
    public function AddToPlaylistByAlbumTitel(string $Titel): bool
    {
        if ($Titel == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'addtracks'], 'album.titlesearch=' . $Titel));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * AddToPlaylistByArtistName
     *
     * @param  string $Name
     * @return bool
     */
    public function AddToPlaylistByArtistName(string $Name): bool
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'addtracks'], 'contributor.namesearch=' . $Name));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * AddToPlaylistByFavoriteID
     *
     * @param  string $FavoriteID
     * @return bool
     */
    public function AddToPlaylistByFavoriteID(string $FavoriteID): bool
    {
        if ($FavoriteID == '') {
            $Data = ['add', 'item_id:.'];
        } else {
            $Data = ['add', 'item_id:' . rawurlencode($FavoriteID)];
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'playlist'], $Data));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * InsertInPlaylistBySearch
     *
     * @param  string $Genre
     * @param  string $Artist
     * @param  string $Album
     * @return bool
     */
    public function InsertInPlaylistBySearch(string $Genre, string $Artist, string $Album): bool
    {
        if ($Genre == '') {
            $Genre = '*';
        }
        if ($Artist == '') {
            $Artist = '*';
        }
        if ($Album == '') {
            $Album = '*';
        }
        if (($Genre == '*') && ($Genre == '*') && ($Genre == '*')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('One search patter is required'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'insertalbum'], [$Genre, $Artist, $Album]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * DeleteFromPlaylistBySearch
     *
     * @param  string $Genre
     * @param  string $Artist
     * @param  string $Album
     * @return bool
     */
    public function DeleteFromPlaylistBySearch(string $Genre, string $Artist, string $Album): bool
    {
        if ($Genre == '') {
            $Genre = '*';
        }
        if ($Artist == '') {
            $Artist = '*';
        }
        if ($Album == '') {
            $Album = '*';
        }
        if (($Genre == '*') && ($Genre == '*') && ($Genre == '*')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('One search patter is required'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'deletealbum'], [$Genre, $Artist, $Album]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * ClearPlaylist
     * The "playlist clear" command removes any song that is on the playlist. The player is stopped.
     *
     * @return bool
     */
    public function ClearPlaylist(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'clear'], ''));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * AddPlaylistIndexToZappedList
     *
     * @param  int $Position
     * @return bool
     */
    public function AddPlaylistIndexToZappedList(int $Position): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'zap'], $Position));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * GetPlaylistURL
     *
     * @return false
     */
    public function GetPlaylistURL(): false|string
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'playlistsinfo'], ''));
        if ($LMSData === null) {
            return false;
        }
        if (count($LMSData->Data) == 1) {
            return [];
        }
        $SongInfo = new \SqueezeBox\LMSSongInfo($LMSData->Data);
        return $SongInfo->GetSong()['Url'];
    }

    /**
     * IsPlaylistModified
     *
     * @return bool
     */
    public function IsPlaylistModified(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'modified'], '?'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == '1';
    }

    //TESTEN TODO
    /*            <br>
      <p id="playlist playlistsinfo">
      <strong>
      <code>
      &lt;playerid&gt;
      playlist
      playlistsinfo
      &lt;taggedParameters&gt;
      </code>
      </strong>
      </p>
      <p>
      The "playlist playlistsinfo" query returns information on the
      saved playlist last loaded into the Now Playing playlist, if any.
      </p>
      <p>
      Accepted tagged parameters:
      </p>
      <table border="0" spacing="50">
      <tbody><tr>
      <td width="100"><b>Tag</b></td>
      <td><b>Description</b></td>
      </tr>
      </tbody></table>
      <p>
      Returned tagged parameters:
      </p>
      <table border="0" spacing="50">
      <tbody><tr>
      <td width="100"><b>Tag</b></td>
      <td><b>Description</b></td>
      </tr>
      <tr>
      <td>id</td>
      <td>Playlist id.</td>
      </tr>
      <tr>
      <td>name</td>
      <td>Playlist name. Equivalent to
      "<a href="#playlist name">playlist name ?</a>".
      </td>
      </tr>
      <tr>
      <td>modified</td>
      <td>Modification state of the saved playlist. Equivalent to
      "<a href="#playlist modified">playlist modified ?</a>".
      </td>
      </tr><tr>
      <td>url</td>
      <td>Playlist url. Equivalent to
      "<a href="#playlist url">playlist url ?</a>".
      </td>
      </tr>
      </tbody></table>
      <p>
      Example:
      </p>
      <blockquote>
      <p>
      Request: "a5:41:d2:cd:cd:05 playlist playlistsinfo &lt;LF&gt;"
      <br>
      Response: "a5:41:d2:cd:cd:05 playlist playlistsinfo
      id:267 name:A98 modified:0 url:file://Volumes/... &lt;LF&gt;"
      </p>
      </blockquote> */

    /**
     * GetPlaylistInfo
     *
     * @return false|array
     */
    public function GetPlaylistInfo(): false|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'playlistsinfo'], ''));
        if ($LMSData === null) {
            return false;
        }
        if (count($LMSData->Data) == 1) {
            return [];
        }
        $SongInfo = new \SqueezeBox\LMSSongInfo($LMSData->Data);
        return $SongInfo->GetSong();
    }

    /**
     * GoToTrack
     * Springt in der aktuellen Wiedergabeliste auf einen Titel.
     *
     * @param int $Index Track in der Wiedergabeliste auf welchen gesprungen werden soll.
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function GoToTrack(int $Index): bool
    {
        if (($Index < 1) || ($Index > $this->GetValue('Tracks'))) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Index'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'index'], $Index - 1));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == $Index - 1;
    }

    /**
     * NextTrack
     * Springt in der aktuellen Wiedergabeliste auf den nächsten Titel.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function NextTrack(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'index'], '+1'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == '+1';
    }

    /**
     * PreviousTrack
     * Springt in der aktuellen Wiedergabeliste auf den vorherigen Titel.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function PreviousTrack(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'index'], '-1'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == '-1';
    }

    /**
     * SetShuffle
     * Setzen des Zufallsmodus.
     *
     * @param int $Value
     *                   0 = aus
     *                   1 = Titel
     *                   2 = Album
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetShuffle(int $Value): bool
    {
        if (($Value < 0) || ($Value > 2)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value must be 0, 1 or 2.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'shuffle'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * SetRepeat
     * Setzen des Wiederholungsmodus.
     *
     * @param int $Value
     *                   0 = aus
     *                   1 = Titel
     *                   2 = Album
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     */
    public function SetRepeat(int $Value): bool
    {
        if (($Value < 0) || ($Value > 2)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value must be 0, 1 or 2.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'repeat'], $Value));
        if ($LMSData === null) {
            return false;
        }

        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * LoadPlaylistByAlbumID
     *
     * @param  int $AlbumID
     * @return bool
     */
    public function LoadPlaylistByAlbumID(int $AlbumID): bool
    {
        return $this->_PlaylistControl('cmd:load', 'album_id:' . $AlbumID, sprintf($this->Translate('%s not found.'), 'AlbumID'));
    }

    /**
     * LoadPlaylistByGenreID
     *
     * @param  int $GenreID
     * @return bool
     */
    public function LoadPlaylistByGenreID(int $GenreID): bool
    {
        return $this->_PlaylistControl('cmd:load', 'genre_id:' . $GenreID, sprintf($this->Translate('%s not found.'), 'GenreID'));
    }

    /**
     * LoadPlaylistByArtistID
     *
     * @param  int $ArtistID
     * @return bool
     */
    public function LoadPlaylistByArtistID(int $ArtistID): bool
    {
        return $this->_PlaylistControl('cmd:load', 'artist_id:' . $ArtistID, sprintf($this->Translate('%s not found.'), 'ArtistID'));
    }

    /**
     * LoadPlaylistByPlaylistID
     *
     * @param  int $PlaylistID
     * @return bool
     */
    public function LoadPlaylistByPlaylistID(int $PlaylistID): bool
    {
        return $this->_PlaylistControl('cmd:load', 'playlist_id:' . $PlaylistID, sprintf($this->Translate('%s not found.'), 'PlaylistID'));
    }

    /**
     * LoadPlaylistBySongIDs
     *
     * @param  string $SongIDs
     * @return bool
     */
    public function LoadPlaylistBySongIDs(string $SongIDs): bool
    {
        return $this->_PlaylistControl('cmd:load', 'track_id:' . $SongIDs, sprintf($this->Translate('%s not found.'), 'SongIDs'));
    }

    /**
     * LoadPlaylistByFolderID
     *
     * @param  int $FolderID
     * @return bool
     */
    public function LoadPlaylistByFolderID(int $FolderID): bool
    {
        return $this->_PlaylistControl('cmd:load', 'folder_id:' . $FolderID, sprintf($this->Translate('%s not found.'), 'FolderID'));
    }

    /**
     * AddToPlaylistByAlbumID
     *
     * @param  int $AlbumID
     * @return bool
     */
    public function AddToPlaylistByAlbumID(int $AlbumID): bool
    {
        return $this->_PlaylistControl('cmd:add', 'album_id:' . $AlbumID, sprintf($this->Translate('%s not found.'), 'AlbumID'));
    }

    /**
     * AddToPlaylistByGenreID
     *
     * @param  int $GenreID
     * @return bool
     */
    public function AddToPlaylistByGenreID(int $GenreID): bool
    {
        return $this->_PlaylistControl('cmd:add', 'genre_id:' . $GenreID, sprintf($this->Translate('%s not found.'), 'GenreID'));
    }

    /**
     * AddToPlaylistByArtistID
     *
     * @param  int $ArtistID
     * @return bool
     */
    public function AddToPlaylistByArtistID(int $ArtistID): bool
    {
        return $this->_PlaylistControl('cmd:add', 'artist_id:' . $ArtistID, sprintf($this->Translate('%s not found.'), 'ArtistID'));
    }

    /**
     * AddToPlaylistByPlaylistID
     *
     * @param  int $PlaylistID
     * @return bool
     */
    public function AddToPlaylistByPlaylistID(int $PlaylistID): bool
    {
        return $this->_PlaylistControl('cmd:add', 'playlist_id:' . $PlaylistID, sprintf($this->Translate('%s not found.'), 'PlaylistID'));
    }

    /**
     * AddToPlaylistBySongIDs
     *
     * @param  string $SongIDs
     * @return bool
     */
    public function AddToPlaylistBySongIDs(string $SongIDs): bool
    {
        return $this->_PlaylistControl('cmd:add', 'track_id:' . $SongIDs, sprintf($this->Translate('%s not found.'), 'SongIDs'));
    }

    /**
     * AddToPlaylistByFolderID
     *
     * @param  int $FolderID
     * @return bool
     */
    public function AddToPlaylistByFolderID(int $FolderID): bool
    {
        return $this->_PlaylistControl('cmd:add', 'folder_id:' . $FolderID, sprintf($this->Translate('%s not found.'), 'FolderID'));
    }

    /**
     * InsertInPlaylistByAlbumID
     *
     * @todo alles auch mit add + move
     * @todo insert testen !?!?
     * @param  int $AlbumID
     * @return bool
     */
    public function InsertInPlaylistByAlbumID(int $AlbumID): bool
    {
        return $this->_PlaylistControl('cmd:insert', 'album_id:' . $AlbumID, sprintf($this->Translate('%s not found.'), 'AlbumID'));
    }

    /**
     * InsertInPlaylistByGenreID
     *
     * @param  int $GenreID
     * @return bool
     */
    public function InsertInPlaylistByGenreID(int $GenreID): bool
    {
        return $this->_PlaylistControl('cmd:insert', 'genre_id:' . $GenreID, sprintf($this->Translate('%s not found.'), 'GenreID'));
    }

    /**
     * InsertInPlaylistByArtistID
     *
     * @param  int $ArtistID
     * @return bool
     */
    public function InsertInPlaylistByArtistID(int $ArtistID): bool
    {
        return $this->_PlaylistControl('cmd:insert', 'artist_id:' . $ArtistID, sprintf($this->Translate('%s not found.'), 'ArtistID'));
    }

    /**
     * InsertInPlaylistByPlaylistID
     *
     * @param  int $PlaylistID
     * @return bool
     */
    public function InsertInPlaylistByPlaylistID(int $PlaylistID): bool
    {
        return $this->_PlaylistControl('cmd:insert', 'playlist_id:' . $PlaylistID, sprintf($this->Translate('%s not found.'), 'PlaylistID'));
    }

    /**
     * InsertInPlaylistBySongIDs
     *
     * @param  string $SongIDs
     * @return bool
     */
    public function InsertInPlaylistBySongIDs(string $SongIDs): bool
    {
        return $this->_PlaylistControl('cmd:insert', 'track_id:' . $SongIDs, sprintf($this->Translate('%s not found.'), 'SongIDs'));
    }

    /**
     * InsertInPlaylistByFolderID
     *
     * @param  int $FolderID
     * @return bool
     */
    public function InsertInPlaylistByFolderID(int $FolderID): bool
    {
        return $this->_PlaylistControl('cmd:insert', 'folder_id:' . $FolderID, sprintf($this->Translate('%s not found.'), 'FolderID'));
    }

    /**
     * InsertInPlaylistByFavoriteID
     *
     * @param  string $FavoriteID
     * @return bool
     */
    public function InsertInPlaylistByFavoriteID(string $FavoriteID): bool
    {
        if ($FavoriteID == '') {
            $Data = ['insert', 'item_id:.'];
        } else {
            $Data = ['insert', 'item_id:' . rawurlencode($FavoriteID)];
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['favorites', 'playlist'], $Data));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * DeleteFromPlaylistByAlbumID
     *
     * @param  int $AlbumID
     * @return bool
     */
    public function DeleteFromPlaylistByAlbumID(int $AlbumID): bool
    {
        return $this->_PlaylistControl('cmd:delete', 'album_id:' . $AlbumID, sprintf($this->Translate('%s not found.'), 'AlbumID'));
    }

    /**
     * DeleteFromPlaylistByGenreID
     *
     * @param  int $GenreID
     * @return bool
     */
    public function DeleteFromPlaylistByGenreID(int $GenreID): bool
    {
        return $this->_PlaylistControl('cmd:delete', 'genre_id:' . $GenreID, sprintf($this->Translate('%s not found.'), 'GenreID'));
    }

    /**
     * DeleteFromPlaylistByArtistID
     *
     * @param  int $ArtistID
     * @return bool
     */
    public function DeleteFromPlaylistByArtistID(int $ArtistID): bool
    {
        return $this->_PlaylistControl('cmd:delete', 'artist_id:' . $ArtistID, sprintf($this->Translate('%s not found.'), 'ArtistID'));
    }

    /**
     * DeleteFromPlaylistByPlaylistID
     *
     * @param  int $PlaylistID
     * @return bool
     */
    public function DeleteFromPlaylistByPlaylistID(int $PlaylistID): bool
    {
        return $this->_PlaylistControl('cmd:delete', 'playlist_id:' . $PlaylistID, sprintf($this->Translate('%s not found.'), 'PlaylistID'));
    }

    /**
     * DeleteFromPlaylistBySongIDs
     *
     * @param  string $SongIDs
     * @return bool
     */
    public function DeleteFromPlaylistBySongIDs(string $SongIDs): bool
    {
        return $this->_PlaylistControl('cmd:delete', 'track_id:' . $SongIDs, sprintf($this->Translate('%s not found.'), 'SongIDs'));
    }

    /**
     * GetSongInfoByTrackIndex
     * Liefert Informationen über einen Song aus der aktuelle Wiedergabeliste.
     *
     * @param int $Index
     *                   $Index für die absolute Position des Titels in der Wiedergabeliste.
     *                   0 für den aktuellen Titel
     * @return false|array
     *               ["duration"]=>string
     *               ["id"]=>string
     *               ["title"]=>string
     *               ["genre"]=>string
     *               ["album"]=>string
     *               ["artist"]=>string
     *               ["disc"]=> string
     *               ["disccount"]=>string
     *               ["bitrate"]=>string
     *               ["tracknum"]=>string
     */
    public function GetSongInfoByTrackIndex(int $Index): false|array
    {
        $Index--;
        if ($Index == -1) {
            $Index = '-';
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['status', (string) $Index, '1'], 'tags:gladiqrRtueJINpsy'));
        if ($LMSData === null) {
            return false;
        }
        $SongInfo = new \SqueezeBox\LMSSongInfo($LMSData->Data);
        $SongArray = $SongInfo->GetSong();
        if (count($SongArray) == 1) {
            throw new Exception($this->Translate('Index not valid.'));
        }
        return $SongArray;
    }

    /**
     * GetSongInfoOfTilePlaylist
     * Liefert Informationen über alle Songs aus der aktuelle Wiedergabeliste.
     *
     * @return false|array[index]
     *                      ["duration"]=>string
     *                      ["id"]=>string
     *                      ["title"]=>string
     *                      ["genre"]=>string
     *                      ["album"]=>string
     *                      ["artist"]=>string
     *                      ["disc"]=> string
     *                      ["disccount"]=>string
     *                      ["bitrate"]=>string
     *                      ["tracknum"]=>string
     */
    public function GetSongInfoOfCurrentPlaylist(): false|array
    {
        $max = $this->GetValue('Tracks');
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['status', '0', (string) $max], 'tags:gladiqrRtueJINpsy'));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = new \SqueezeBox\LMSSongInfo($LMSData->Data);
        return $SongInfo->GetAllSongs();
    }

    ///////////////////////////////////////////////////////////////
    // ENDE PLAYERLIST
    ///////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////
    // START RANDOMPLAY
    ///////////////////////////////////////////////////////////////

    /**
     * StartRandomplayOfTracks
     *
     * @return bool
     */
    public function StartRandomplayOfTracks(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['randomplay'], ['tracks']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * StartRandomplayOfAlbums
     *
     * @return bool
     */
    public function StartRandomplayOfAlbums(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['randomplay'], ['albums']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * StartRandomplayOfArtist
     *
     * @return bool
     */
    public function StartRandomplayOfArtist(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['randomplay'], ['contributors']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * StartRandomplayOfYear
     *
     * @return bool
     */
    public function StartRandomplayOfYear(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['randomplay'], ['year']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * StopRandomplay
     *
     * @return bool
     */
    public function StopRandomplay(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['randomplay'], ['disable']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * RandomplaySelectAllGenre
     *
     * @param  bool $Active
     * @return bool
     */
    public function RandomplaySelectAllGenre(bool $Active): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['randomplaygenreselectall'], [(int) $Active]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * RandomplaySelectGenre
     *
     * @param  string $Genre
     * @param  bool $Active
     * @return bool
     */
    public function RandomplaySelectGenre(string $Genre, bool $Active): bool
    {
        if ($Genre == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Genre'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['randomplaychoosegenre'], [$Genre, (int) $Active]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    ///////////////////////////////////////////////////////////////
    // ENDE RANDOMPLAY
    ///////////////////////////////////////////////////////////////
    //################# ActionHandler

    /**
     * RequestAction
     *
     * @param  string $Ident
     * @param  mixed $Value
     * @return void
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return;
        }
        switch ($Ident) {
            case '_SetNewSyncProfil':
                $this->_SetNewSyncProfil();
                return;
            case 'Status':
                switch ((int) $Value) {
                    case 0: //Prev
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
                        $result = $this->NextTrack();
                        break;
                }
                break;
            case 'Volume':
                $result = $this->SetVolume((int) $Value);
                break;
            case 'Bass':
                $result = $this->SetBass((int) $Value);
                break;
            case 'Treble':
                $result = $this->SetTreble((int) $Value);
                break;
            case 'Pitch':
                $result = $this->SetPitch((int) $Value);
                break;
            case 'Preset':
                $result = $this->SelectPreset((int) $Value);
                break;
            case 'Power':
                $result = $this->Power((bool) $Value);
                break;
            case 'Mute':
                $result = $this->SetMute((bool) $Value);
                break;
            case 'Repeat':
                if ((int) $Value == 1) {
                    $Value = 2;
                } elseif ((int) $Value == 2) {
                    $Value = 1;
                }
                $result = $this->SetRepeat((int) $Value);
                break;
            case 'Shuffle':
                $result = $this->SetShuffle((int) $Value);
                break;
            case 'Position2':
                $Time = ($this->DurationRAW / 100) * (int) $Value;
                $result = $this->SetPosition(intval($Time));
                break;
            case 'PositionRaw':
                $result = $this->SetPosition((int) $Value);
                break;
            case 'Index':
                $result = $this->GoToTrack((int) $Value);
                break;
            case 'SleepTimer':
                $result = $this->SetSleep((int) $Value);
                break;
            case 'Sync':
                if ((int) $Value == 0) {
                    $result = $this->SetUnSync();
                } elseif ((int) $Value == 100) {
                    $result = true;
                } else {
                    $result = $this->SetSync((int) $Value);
                }
                break;
            case 'Randomplay':
                switch ((int) $Value) {
                    case 0:
                        $result = $this->StopRandomPlay();
                        break;
                    case 1:
                        $result = $this->StartRandomplayOfTracks();
                        break;
                    case 2:
                        $result = $this->StartRandomplayOfAlbums();
                        break;
                    case 3:
                        $result = $this->StartRandomplayOfArtist();
                        break;
                    case 4:
                        $result = $this->StartRandomplayOfYear();
                        break;
                }
                break;
            case 'TilePlaylist':
                $result = $this->GoToTrack(json_decode($Value, true)['current'] + 1);
                break;
            case 'showHTMLPlaylist':
                $this->UpdateFormField('Table', 'enabled', (bool) $Value);
                $this->UpdateFormField('Columns', 'enabled', (bool) $Value);
                $this->UpdateFormField('Rows', 'enabled', (bool) $Value);
                $this->UpdateFormField('HTMLExpansionPanel', 'expanded', (bool) $Value);
                return;
            default:
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Invalid ident') . ' ' . $Ident, E_USER_NOTICE);
                restore_error_handler();
                return;
        }
        if ($result == false) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Error on Execute Action'), E_USER_NOTICE);
            restore_error_handler();
        }
    }

    //################# DataPoints Ankommend von Parent-LMS-Splitter
    /**
     * ReceiveData
     *
     * @param  string $JSONString
     * @return string
     */
    public function ReceiveData(string $JSONString): string
    {
        $this->SendDebug('Receive Event', $JSONString, 0);
        $Data = json_decode($JSONString);
        // Objekt erzeugen welches die Commands und die Values enthält.
        $LMSData = new \SqueezeBox\LMSData();
        $LMSData->CreateFromGenericObject($Data);
        // Ist das Command schon bekannt ?

        if ($LMSData->Command[0] != false) {
            if ($LMSData->Command[0] == 'ignore') {
                return '';
            }
            $this->DecodeLMSResponse($LMSData);
        } else {
            $this->SendDebug('UNKNOWN', $LMSData, 0);
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
     * IOChangeState
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     *
     * @param  int $State
     * @return void
     */
    protected function IOChangeState(int $State): void
    {
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $Value = IS_INACTIVE;
        if ($State == IS_ACTIVE) {
            $LMSResponse = $this->SendDirect(new \SqueezeBox\LMSData('connected', '?'));
            if ($LMSResponse != null) {
                $Value = ($LMSResponse->Data[0] == '1') ? IS_ACTIVE : IS_INACTIVE;
            }
        }
        $this->SetStatus($Value);
        if ($Value == IS_ACTIVE) {
            $this->_RefreshPlaylist();
            // Erst nach 5 Sekunden, sonst sind beim ModuleReload InstanceInterface Fehler möglich
            IPS_RunScriptText('IPS_Sleep(5000);IPS_RequestAction(' . $this->InstanceID . ', \'_SetNewSyncProfil\', true);');
        }
    }

    /**
     * ProcessHookData
     * Verarbeitet Daten aus dem Webhook.
     *
     * @global array $_GET
     */
    protected function ProcessHookData(): void
    {
        if ((!isset($_GET['ID'])) || (!isset($_GET['Type'])) || (!isset($_GET['Secret']))) {
            echo $this->Translate('Bad Request');
            return;
        }
        $CalcSecret = base64_encode(sha1($this->WebHookSecretTrack . '0' . $_GET['ID'], true));
        if ($CalcSecret != rawurldecode($_GET['Secret'])) {
            echo $this->Translate('Access denied');
            return;
        }
        if ($_GET['Type'] != 'Track') {
            echo $this->Translate('Bad Request');
            return;
        }

        if ($this->GoToTrack((int) $_GET['ID'])) {
            echo 'OK';
        }
    }

    /**
     * SendDirect
     * Konvertiert $Data zu einem String und versendet diesen direkt an den LMS.
     *
     * @param \SqueezeBox\LMSData $LMSData Zu versendende Daten.
     * @return null|\SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    protected function SendDirect(\SqueezeBox\LMSData $LMSData): null|\SqueezeBox\LMSData
    {
        if ($this->ReadPropertyString('Address') == '') {
            return null;
        }

        try {
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }

            if (!$this->_isPlayerConnected() && ($LMSData->Command[0] != 'connected')) {
                throw new Exception($this->Translate('Player not connected'), E_USER_NOTICE);
            }

            $LMSData->Address = $this->ReadPropertyString('Address');
            $this->SendDebug('Send Direct', $LMSData, 0);

            if (!$this->Socket) {
                $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
                $IoID = IPS_GetInstance($SplitterID)['ConnectionID'];
                $Host = IPS_GetProperty($IoID, 'Host');
                if ($Host === '') {
                    return null;
                }
                $Host = gethostbyname($Host);

                $Port = IPS_GetProperty($SplitterID, 'Port');
                $User = IPS_GetProperty($SplitterID, 'User');
                $Pass = IPS_GetProperty($SplitterID, 'Password');

                $LoginData = (new \SqueezeBox\LMSData('login', [$User, $Pass]))->ToRawStringForLMS();
                $this->SendDebug('Send Direct', $LoginData, 0);
                $this->Socket = @stream_socket_client('tcp://' . $Host . ':' . $Port, $errno, $errstr, 2);
                if (!$this->Socket) {
                    throw new Exception($this->Translate('No answer from LMS'), E_USER_NOTICE);
                }
                stream_set_timeout($this->Socket, 5);
                fwrite($this->Socket, $LoginData);
                $answerlogin = stream_get_line($this->Socket, 1024 * 1024 * 2, chr(0x0d));
                $this->SendDebug('Response Direct', $answerlogin, 0);
                if ($answerlogin === false) {
                    throw new Exception($this->Translate('No answer from LMS'), E_USER_NOTICE);
                }
            }

            $Data = $LMSData->ToRawStringForLMS();
            $this->SendDebug('Send Direct', $Data, 0);
            fwrite($this->Socket, $Data);
            $answer = stream_get_line($this->Socket, 1024 * 1024 * 2, chr(0x0d));
            $this->SendDebug('Response Direct', $answer, 0);
            if ($answer === false) {
                throw new Exception($this->Translate('No answer from LMS'), E_USER_NOTICE);
            }

            $ReplyData = new \SqueezeBox\LMSResponse($answer);
            $LMSData->Data = $ReplyData->Data;
            $this->SendDebug('Response Direct', $LMSData, 0);
            return $LMSData;
        } catch (Exception $exc) {
            $this->SendDebug('Receive Direct', $exc->getMessage(), 0);
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            restore_error_handler();
        }
        return null;
    }

    /**
     * SetStatus
     *
     * @param  int $State
     * @return bool
     */
    protected function SetStatus(int $State): bool
    {
        if ($State != $this->GetStatus()) {
            parent::SetStatus($State);
        }
        return false;
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
                'style' => 'margin:0 auto; font-size:0.8em;'
            ],
            [
                'tag'   => '<thead>',
                'style' => ''
            ],
            [
                'tag'   => '<tbody>',
                'style' => ''
            ]
        ];
        $NewColumnsConfig = [
            [
                'index' => 0,
                'key'   => 'Play',
                'name'  => '',
                'show'  => true,
                'width' => 50,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 1,
                'key'   => 'Position',
                'name'  => 'Pos',
                'show'  => true,
                'width' => 50,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 2,
                'key'   => 'Title',
                'name'  => $this->Translate('Title'),
                'show'  => true,
                'width' => 250,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 3,
                'key'   => 'Artist',
                'name'  => $this->Translate('Artist'),
                'show'  => true,
                'width' => 250,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 4,
                'key'   => 'Bitrate',
                'name'  => 'Bitrate',
                'show'  => false,
                'width' => 150,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 5,
                'key'   => 'Duration',
                'name'  => $this->Translate('Duration'),
                'show'  => true,
                'width' => 100,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 6,
                'key'   => 'Genre',
                'name'  => $this->Translate('Genre'),
                'show'  => false,
                'width' => 200,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 7,
                'key'   => 'Album',
                'name'  => 'Album',
                'show'  => false,
                'width' => 250,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 8,
                'key'   => 'Disc',
                'name'  => 'Disc',
                'show'  => false,
                'width' => 35,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 9,
                'key'   => 'Disccount',
                'name'  => 'Disccount',
                'show'  => false,
                'width' => 35,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 10,
                'key'   => 'Tracknum',
                'name'  => 'Track',
                'show'  => false,
                'width' => 35,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ],
            [
                'index' => 11,
                'key'   => 'Year',
                'name'  => $this->Translate('Year'),
                'show'  => false,
                'width' => 60,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''
            ]
        ];
        $NewRowsConfig = [
            [
                'row'     => 'odd',
                'name'    => $this->Translate('odd'),
                'bgcolor' => 0x000000,
                'color'   => 0xffffff,
                'style'   => ''
            ],
            [
                'row'     => 'even',
                'name'    => $this->Translate('even'),
                'bgcolor' => 0x080808,
                'color'   => 0xffffff,
                'style'   => ''
            ],
            [
                'row'     => 'active',
                'name'    => $this->Translate('active'),
                'bgcolor' => 0x808000,
                'color'   => 0xffffff,
                'style'   => ''
            ]
        ];
        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig];
    }

    /**
     * DisplayLines
     *
     * @param  mixed $Line1
     * @param  mixed $Line2
     * @param  mixed $Huge
     * @param  mixed $Duration
     * @param  mixed $Centered
     * @param  mixed $Brightness
     * @return bool
     */
    private function DisplayLines($Line1 = '', $Line2 = '', $Huge = false, $Duration = 3, $Centered = false, $Brightness = 4): bool
    {
        $Duration = ($Duration < 3 ? 3 : $Duration);
        $Brightness = ($Brightness < 0 ? 0 : $Brightness);
        $Brightness = ($Brightness > 4 ? 4 : $Brightness);
        $Values = [];
        if ($Line1 != '') {
            $Values[] = 'line1:' . $Line1;
        }
        if ($Line2 != '') {
            $Values[] = 'line2:' . $Line2;
        }
        $Values[] = 'duration:' . $Duration;
        $Values[] = 'brightness:' . $Brightness;
        if ($Huge) {
            $Values[] = 'font:huge';
        }
        if ($Centered) {
            $Values[] = 'centered:1';
        }
        $LMSData = $this->Send(new \SqueezeBox\LMSData('show', $Values));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * _PlaylistControl
     *
     * @param  string $cmd
     * @param  string $item
     * @param  string $errormsg
     * @return bool
     */
    private function _PlaylistControl(string $cmd, string $item, string $errormsg): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('playlistcontrol', [$cmd, $item]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        /** @var array $LMSTaggedData */
        $LMSTaggedData = (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
        if (!array_key_exists('Count', $LMSTaggedData)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($errormsg, E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return true;
    }

    //################# PRIVATE
    /**
     * _isPlayerConnected
     *
     * @return bool
     */
    private function _isPlayerConnected(): bool
    {
        return $this->GetStatus() == IS_ACTIVE;
    }

    /**
     * _isPlayerOn
     *
     * @return bool
     */
    private function _isPlayerOn(): bool
    {
        return (bool) $this->GetValue('Power');
    }

    /**
     * _StartSubscribe
     *
     * @return void
     */
    private function _StartSubscribe(): void
    {
        if ($this->_isPlayerConnected()) {
            $this->Send(new \SqueezeBox\LMSData(['status', '-', '1'], 'subscribe:' . $this->ReadPropertyInteger('Interval')), false);
        }
    }

    /**
     * _StopSubscribe
     *
     * @return void
     */
    private function _StopSubscribe(): void
    {
        if ($this->_isPlayerConnected()) {
            if ($this->ReadPropertyBoolean('enableSleepTimer')) {
                if (($this->GetValue('SleepTimer')) != 0) {
                    return;
                }
            }
            @$this->Send(new \SqueezeBox\LMSData(['status', '-', '1'], 'subscribe:0'), false);
        }
    }

    /**
     * _SetNewName
     *
     * @param  string $Name
     * @return void
     */
    private function _SetNewName(string $Name): void
    {
        if (!$this->ReadPropertyBoolean('changeName')) {
            return;
        }
        if (IPS_GetName($this->InstanceID) != trim($Name)) {
            IPS_SetName($this->InstanceID, trim($Name));
        }
    }

    /**
     * _SetNewPower
     *
     * @param  bool $Power
     * @return void
     */
    private function _SetNewPower(bool $Power): void
    {
        $this->SetValueBoolean('Power', $Power);
        if (!$Power) {
            $this->_SetModeToStop();
            $this->_SetNewSyncMaster(false);
            $this->_SetNewSyncMembers('-');
            if ($this->ReadPropertyBoolean('showSleepTimeout')) {
                $this->SetValueString('SleepTimeout', '');
            }
            if ($this->ReadPropertyBoolean('enableSleepTimer')) {
                $this->SetValueInteger('SleepTimer', 0);
            }
        }
    }

    /**
     * _SetModeToPlay
     *
     * @return void
     */
    private function _SetModeToPlay(): void
    {
        if ($this->GetValue('Status') != 2) {
            $this->SetValueInteger('Status', 2);
        }
    }

    /**
     * _SetModeToPause
     *
     * @return void
     */
    private function _SetModeToPause(): void
    {
        if (!$this->_isPlayerOn()) {
            return;
        }
        if ($this->GetValue('Status') != 3) {
            $this->SetValueInteger('Status', 3);
        }
    }

    /**
     * _SetModeToStop
     *
     * @return void
     */
    private function _SetModeToStop(): void
    {
        if ($this->GetValue('Status') != 1) {
            $this->SetValueInteger('Status', 1);
            $this->_SetNewTime(0);
        }
    }

    /**
     * _SetNewVolume
     *
     * @param  int|string $Value
     * @return void
     */
    private function _SetNewVolume(int|string $Value): void
    {
        if (is_string($Value) && (($Value[0] == '+') || $Value[0] == '-')) {
            $Value = (int) $this->GetValue('Volume') + (int) $Value;
            if ($Value < 0) {
                $Value = 0;
            }
            if ($Value > 100) {
                $Value = 100;
            }
        }
        if ($Value < 0) {
            $Value = $Value - (2 * $Value);
        } else {
            $this->SetValueBoolean('Mute', false);
        }
        $this->SetValueInteger('Volume', (int) $Value);
    }

    /**
     * _SetNewBass
     *
     * @param  int|string $Value
     * @return void
     */
    private function _SetNewBass(int|string $Value): void
    {
        if ($Value == '') {
            return;
        }
        if (is_string($Value) && (($Value[0] == '+') || $Value[0] == '-')) {
            $Value = (int) $this->GetValue('Bass') + (int) $Value;
            if ($Value < 0) {
                $Value = 0;
            }
            if ($Value > 100) {
                $Value = 100;
            }
        }
        $this->SetValueInteger('Bass', (int) $Value);
    }

    /**
     * _SetNewTreble
     *
     * @param  int|string $Value
     * @return void
     */
    private function _SetNewTreble(int|string $Value): void
    {
        if ($Value == '') {
            return;
        }
        if (is_string($Value) && (($Value[0] == '+') || $Value[0] == '-')) {
            $Value = (int) $this->GetValue('Treble') + (int) $Value;
            if ($Value < 0) {
                $Value = 0;
            }
            if ($Value > 100) {
                $Value = 100;
            }
        }
        $this->SetValueInteger('Treble', (int) $Value);
    }

    /**
     * _SetNewPitch
     *
     * @param  int|string $Value
     * @return void
     */
    private function _SetNewPitch(int|string $Value): void
    {
        if ($Value == '') {
            return;
        }
        if (is_string($Value) && (($Value[0] == '+') || $Value[0] == '-')) {
            $Value = (int) $this->GetValue('Pitch') + (int) $Value;
            if ($Value < 80) {
                $Value = 80;
            }
            if ($Value > 120) {
                $Value = 120;
            }
        }
        $this->SetValueInteger('Pitch', (int) $Value);
    }

    /**
     * _SetNewTime
     *
     * @param  int $Time
     * @return void
     */
    private function _SetNewTime(int $Time): void
    {
        if ($this->DurationRAW == 0) {
            return;
        }

        $this->PositionRAW = $Time;
        if ($this->ReadPropertyBoolean('enableRawPosition')) {
            $this->SetValueInteger('PositionRaw', $Time);
        }
        $this->SetValueString('Position', $this->ConvertSeconds($Time));
        if ($this->isSeekable) {
            $Value = (100 / $this->DurationRAW) * $Time;
            $this->SetValueFloat('Position2', $Value);
        }
    }

    /**
     * _SetNewDuration
     *
     * @param  int $Duration
     * @return void
     */
    private function _SetNewDuration(int $Duration): void
    {
        $this->DurationRAW = $Duration;
        if ($this->ReadPropertyBoolean('enableRawDuration')) {
            $this->SetValueInteger('DurationRaw', $Duration);
        }
        if ($Duration == 0) {
            $this->SetValueString('Duration', '');
            $this->SetValueFloat('Position2', 0);
            $this->DisableAction('Position2');
        } else {
            $OldDuration = $this->GetValue('Duration');
            $NewDuration = $this->ConvertSeconds($Duration);
            $this->SetValueString('Duration', $NewDuration);
            if (($OldDuration != $NewDuration) && ($this->isSeekable)) {
                $this->EnableAction('Position2');
            }
        }
    }

    /**
     * _SetNewSleepTimeout
     *
     * @param  int $Value
     * @return void
     */
    private function _SetNewSleepTimeout(int $Value): void
    {
        if ($this->ReadPropertyBoolean('showSleepTimeout')) {
            $this->SetValueString('SleepTimeout', $this->ConvertSeconds($Value));
        }
        if ($this->ReadPropertyBoolean('enableSleepTimer')) {
            if ($Value == 0) {
                $this->SetValueInteger('SleepTimer', 0);
            }
        }
    }

    /**
     * _SetNewSyncMaster
     *
     * @param  bool $isMaster
     * @return void
     */
    private function _SetNewSyncMaster(bool $isMaster): void
    {
        if ($this->ReadPropertyBoolean('showSyncMaster')) {
            $this->SetValueBoolean('Master', $isMaster);
        }
    }

    /**
     * _SetNewSyncMembers
     *
     * @param  string $PlayerMACs
     * @return void
     */
    private function _SetNewSyncMembers(string $PlayerMACs): void
    {
        if ($PlayerMACs == '-') {
            $PlayerMACs = '';
            $this->_SetNewSyncMaster(false);
            $this->SyncMaster = '';
        }
        if ($this->SyncMembers != $PlayerMACs) {
            $this->SyncMembers = $PlayerMACs;
            $this->_SetNewSyncProfil();
        }
    }

    /**
     * _GetAllPlayers
     *
     * @return array
     */
    private function _GetAllPlayers(): array
    {
        $Addresses = [];
        $AllPlayerIDs = IPS_GetInstanceListByModuleID('{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}');
        foreach ($AllPlayerIDs as $DeviceID) {
            if ($DeviceID == $this->InstanceID) {
                continue;
            }
            if (IPS_GetInstance($DeviceID)['ConnectionID'] == $this->ParentID) {
                $Addresses[$DeviceID] = IPS_GetProperty($DeviceID, 'Address');
            }
        }
        return $Addresses;
    }

    /**
     * _SetNewSyncProfil
     *
     * @return void
     */
    private function _SetNewSyncProfil(): void
    {
        if (!$this->ReadPropertyBoolean('showSyncControl')) {
            return;
        }
        $SyncMembers = [];
        if ($this->SyncMembers != '') {
            $SyncMembers = explode(',', $this->SyncMembers);
        }
        $Addresses = $this->_GetAllPlayers();
        $Assoziation = [
            [0, $this->Translate('Off'), '', -1],
            [100, $this->Translate('On'), '', 0x00ff00]
        ];
        foreach ($Addresses as $InstanceID => $MACAdresse) {
            if ((in_array($MACAdresse, $SyncMembers) || ($MACAdresse == $this->SyncMaster))) {
                $Color = 0xff0000;
            } else {
                $Color = -1;
            }
            $Assoziation[] = [
                $InstanceID,
                IPS_GetName($InstanceID),
                '',
                $Color];
        }
        $this->RegisterProfileIntegerEx('LSQ.Sync.' . $this->InstanceID, 'Speaker-100', '', '', $Assoziation);
        $this->SetValueInteger('Sync', count($SyncMembers) == 0 ? 0 : 100);
    }

    /**
     * _SetSeekable
     *
     * @param  bool $Value
     * @return void
     */
    private function _SetSeekable(bool $Value): void
    {
        if ($Value != $this->isSeekable) {
            if ($Value) {
                $this->EnableAction('Position2');
            } else {
                $this->DisableAction('Position2');
            }
            $this->isSeekable = $Value;
        }
    }

    /**
     * _RefreshPlaylistIndex
     *
     * @return void
     */
    private function _RefreshPlaylistIndex(): void
    {
        $this->SetCover();
        $Data = $this->Multi_Playlist;
        if (!is_array($Data)) {
            $Data = [];
        }
        $CurrentIndex = (int) $this->GetValue('Index');

        // TilePlaylist
        if ($this->ReadPropertyBoolean('showTilePlaylist')) {
            $TilePlaylistData = '';
            if (count($Data)) {
                $playlistEntries = [];
                foreach ($Data as $Index => ['Title' => $Title, 'Artist'=>$Artist, 'Duration'=>$Duration]) {
                    $playlistEntries[] = [
                        'artist'        => $Artist,
                        'song'          => $Title,
                        'duration'      => $Duration
                    ];
                }
                $TilePlaylistData = json_encode([
                    'current' => $CurrentIndex - 1,
                    'entries' => $playlistEntries
                ]);
            }
            $this->SetValueString('TilePlaylist', $TilePlaylistData);
        }
        // HTML-Playlist
        if ($this->ReadPropertyBoolean('showHTMLPlaylist')) {
            $HTML = $this->GetTable($Data, 'SqueezeBoxPlaylist', 'Track', 'Position', $CurrentIndex);
            $this->SetValueString('HTMLPlaylist', $HTML);
        }
    }

    /**
     * _RefreshPlaylist
     *
     * @param  bool $Empty
     * @return void
     */
    private function _RefreshPlaylist(bool $Empty = false): void
    {
        if ($Empty) {
            $this->Multi_Playlist = [];
        } else {
            $PlaylistDataArray = $this->GetSongInfoOfCurrentPlaylist();
            if ($PlaylistDataArray === false) {
                $this->Multi_Playlist = [];
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Error on read playlist'), E_USER_NOTICE);
                restore_error_handler();
                $this->SetCover();
                return;
            }
            $this->Multi_Playlist = $PlaylistDataArray;
        }
        $this->_RefreshPlaylistIndex();
        return;
    }

    /**
     * SetCover
     *
     * @return void
     */
    private function SetCover(): void
    {
        $this->SendDebug('Refresh Cover', '', 0);
        $CoverID = @IPS_GetObjectIDByIdent('CoverIMG', $this->InstanceID);
        if ($CoverID === false) {
            $CoverID = IPS_CreateMedia(1);
            IPS_SetParent($CoverID, $this->InstanceID);
            IPS_SetIdent($CoverID, 'CoverIMG');
            IPS_SetName($CoverID, 'Cover');
            IPS_SetPosition($CoverID, 27);
            IPS_SetMediaCached($CoverID, true);
            $filename = 'media' . DIRECTORY_SEPARATOR . 'Cover_' . $this->InstanceID . '.png';
            IPS_SetMediaFile($CoverID, $filename, false);
            $this->SendDebug('Create Media', $filename, 0);
        }
        $CoverRAW = false;
        if ($this->_isPlayerConnected()) {
            $ParentID = $this->ParentID;
            if ($ParentID > 0) {
                $Size = $this->ReadPropertyString('CoverSize');
                $Player = $this->ReadPropertyString('Address');
                $CoverRAW = $this->GetCover('', $Size, $Player);
            }
        }
        if ($CoverRAW === false) {
            $CoverRAW = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'nocover.png');
        }
        IPS_SetMediaContent($CoverID, base64_encode($CoverRAW));
    }

    //################# Decode Data
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
            case 'name':
                $this->_SetNewName((string) $LMSData->Data[0]);
                break;
            case 'power':
                $this->_SetNewPower((int) $LMSData->Data[0] == 1);
                $this->SetStatus(IS_ACTIVE);
                break;
            case 'mode':
                switch ($LMSData->Data[0]) {
                    case 'play':
                        $this->_SetModeToPlay();
                        break;
                    case 'stop':
                        $this->_SetModeToStop();
                        break;
                    case 'pause':
                        if ((bool) $LMSData->Data[0]) {
                            $this->_SetModeToPause();
                        } else {
                            $this->_SetModeToPlay();
                        }
                        break;
                    default:
                        return false;
                }
                break;
            case 'mixer':
                switch ($LMSData->Command[1]) {
                    case 'volume':
                        $this->_SetNewVolume($LMSData->Data[0]);
                        break;
                    case 'muting':
                        $this->SetValueBoolean('Mute', (bool) $LMSData->Data[0]);
                        break;
                    case 'bass':
                        if ($this->ReadPropertyBoolean('enableBass')) {
                            $this->_SetNewBass($LMSData->Data[0]);
                        }
                        break;
                    case 'treble':
                        if ($this->ReadPropertyBoolean('enableTreble')) {
                            $this->_SetNewTreble($LMSData->Data[0]);
                        }
                        break;
                    case 'pitch':
                        if ($this->ReadPropertyBoolean('enablePitch')) {
                            $this->_SetNewPitch($LMSData->Data[0]);
                        }
                        break;
                    default:
                        return false;
                }
                break;
            case 'playlist':
                switch ($LMSData->Command[1]) {
                    case 'stop':
                        $this->_SetModeToStop();
                        break;
                    case 'pause':
                        if ((bool) $LMSData->Data[0]) {
                            $this->_SetModeToPause();
                        } else {
                            $this->_SetModeToPlay();
                        }
                        break;
                    case 'tracks':
                    case 'clear':
                        if (!isset($LMSData->Data[0])) {
                            $LMSData->Data[0] = 0;
                        }
                        if ((int) $LMSData->Data[0] == 0) { // alles leeren
                            $this->SetValueString('Title', '');
                            $this->SetValueString('Playlistname', '');
                            $this->SetValueString('Artist', '');
                            $this->SetValueString('Album', '');
                            $this->SetValueString('Genre', '');
                            $this->SetValueString('Duration', '0:00');
                            $this->DurationRAW = 0;
                            if ($this->ReadPropertyBoolean('enableRawDuration')) {
                                $this->SetValueInteger('DurationRaw', 0);
                            }
                            $this->SetValueFloat('Position2', 0);
                            $this->SetValueString('Position', '0:00');
                            $this->SetValueInteger('Index', 0);
                        }
                        if ($this->GetValue('Tracks') != (int) $LMSData->Data[0]) {
                            $this->SetValueInteger('Tracks', (int) $LMSData->Data[0]);
                        }
                        break;
                    case 'addtracks':
                        $this->RequestState('Tracks');
                        break;
                    case 'load_done':
                    case 'resume':
                        $this->RequestState('Tracks');
                        break;
                    case 'shuffle':
                        if ($this->GetValue('Shuffle') != (int) $LMSData->Data[0]) {
                            $this->SetValueInteger('Shuffle', (int) $LMSData->Data[0]);
                        }
                        break;
                    case 'repeat':
                        $Value = (int) $LMSData->Data[0];
                        if ($Value == 1) {
                            $Value = 2;
                        } elseif ($Value == 2) {
                            $Value = 1;
                        }
                        $this->SetValueInteger('Repeat', $Value);
                        break;
                    case 'name':
                        $this->SetValueString('Playlistname', trim((string) $LMSData->Data[0]));
                        break;
                    case 'index':
                    case 'jump':
                        if ($LMSData->Data[0] == '') {
                            break;
                        }
                        if (((string) $LMSData->Data[0][0] === '+') || ((string) $LMSData->Data[0][0] === '-')) {
                            $this->SetValueInteger('Index', (int) $this->GetValue('Index') + (int) $LMSData->Data[0]);
                        } else {
                            $this->SetValueInteger('Index', (int) $LMSData->Data[0] + 1);
                        }
                        break;
                    case 'newsong':
                        $this->SetValueString('Title', trim((string) $LMSData->Data[0]));
                        if (isset($LMSData->Data[1])) {
                            $this->SetValueInteger('Index', (int) $LMSData->Data[1] + 1);
                        } else {
                            $this->_RefreshPlaylistIndex();
                        }

                        $this->RequestState('Playlistname');
                        $this->RequestState('Album');
                        $this->RequestState('Title');
                        $this->RequestState('Artist');
                        $this->RequestState('Genre');
                        $this->RequestState('Duration');
                        $this->SetCover();
                        break;
                    case 'clear':

                    default:
                        return false;
                }
                break;
            case 'album':
                $this->SetValueString('Album', trim((string) $LMSData->Data[0]));
                break;
            case 'current_title':
                break;
            case 'title':
                $this->SetValueString('Title', trim((string) $LMSData->Data[0]));
                break;
            case 'artist':
                $this->SetValueString('Artist', trim((string) $LMSData->Data[0]));
                break;
            case 'genre':
                $this->SetValueString('Genre', trim((string) $LMSData->Data[0]));
                break;
            case 'duration':
                $this->_SetNewDuration((int) $LMSData->Data[0]);
                break;
            case 'time':
                $this->PositionRAW = (int) $LMSData->Data[0];
                if ($this->ReadPropertyBoolean('enableRawPosition')) {
                    $this->SetValueInteger('PositionRaw', (int) $LMSData->Data[0]);
                }
                $this->SetValueString('Position', $this->ConvertSeconds((int) $LMSData->Data[0]));
                break;
            case 'signalstrength':
                if ($this->ReadPropertyBoolean('showSignalstrength')) {
                    $this->SetValueInteger('Signalstrength', (int) $LMSData->Data[0]);
                }
                break;
            case 'sleep':
                $this->_SetNewSleepTimeout((int) $LMSData->Data[0]);
                break;
            case 'connected':
                $this->SetStatus(($LMSData->Data[0] == '1') ? IS_ACTIVE : IS_INACTIVE);
                break;
            case 'sync':
                $this->_SetNewSyncMembers((string) $LMSData->Data[0]);
                break;
            case 'remote':
                $this->_SetSeekable(!(bool) $LMSData->Data[0]);
                break;
            case 'client':
                if (($LMSData->Data[0] == 'disconnect') || ($LMSData->Data[0] == 'forget')) {
                    $this->SetStatus(IS_INACTIVE);
                } elseif (($LMSData->Data[0] == 'new') || ($LMSData->Data[0] == 'reconnect')) {
                    $this->SetStatus(IS_ACTIVE);
                }
                break;
            case 'play':
                $this->_SetModeToPlay();
                break;
            case 'stop':
                $this->_SetModeToStop();
                break;
            case 'pause':
                if ((bool) $LMSData->Data[0]) {
                    $this->_SetModeToPause();
                } else {
                    $this->_SetModeToPlay();
                }
                break;
            case 'newmetadata':
                $this->SetCover();
                break;
            case 'randomplay':
                switch ($LMSData->Data[0]) {
                    case 'tracks':
                        $this->SetValueInteger('Randomplay', 1);
                        break;
                    case 'albums':
                        $this->SetValueInteger('Randomplay', 2);
                        break;
                    case 'contributors':
                        $this->SetValueInteger('Randomplay', 3);
                        break;
                    case 'year':
                        $this->SetValueInteger('Randomplay', 4);
                        break;
                    default:
                        $this->SetValueInteger('Randomplay', 0);
                        break;
                }

                break;
            case 'status':
                $isSyncActive = false;
                foreach ($LMSData->Data as $TaggedDataLine) {
                    $Data = new \SqueezeBox\LMSTaggingData($TaggedDataLine);
                    switch ($Data->Name) {
                        case 'player_name':
                            $this->_SetNewName((string) $Data->Value);
                            break;
                        case 'player_connected':
                            if ((bool) $Data->Value == false) {
                                $this->SetStatus(IS_INACTIVE);
                                //Abbruch, sonst wird der Rest falsch gesetzt.
                                return true;
                            }
                            break;
                        case 'power':
                            $this->_SetNewPower((int) $Data->Value == 1);
                            break;
                        case 'signalstrength':
                            if ($this->ReadPropertyBoolean('showSignalstrength')) {
                                $this->SetValueInteger('Signalstrength', (int) $Data->Value);
                            }
                            break;
                        case 'mode':
                            switch ($Data->Value) {
                                case 'play':
                                    $this->_SetModeToPlay();
                                    break;
                                case 'stop':
                                    if ($this->GetValue('Status') != 1) {
                                        $this->SetValueInteger('Status', 1);
                                    }
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
                        case 'remote':
                            //$this->_SetSeekable((int) $Data->Value != 1);
                            break;
                        case 'sleep':
                            if ($this->ReadPropertyBoolean('enableSleepTimer')) {
                                $this->SetValueInteger('SleepTimer', (int) $Data->Value);
                            }
                            break;
                        case 'sync_master':
                            /*
                             * ID of the master player in the sync group this player belongs to.
                             * Only if synced.*/
                            $this->SyncMaster = $Data->Value;
                            $this->_SetNewSyncMaster($Data->Value == $this->ReadPropertyString('Address'));
                            $isSyncActive = true;
                            break;
                        case 'sync_slaves':
                            /*
                             * Comma-separated list of player IDs, slaves to sync_master in the
                             * sync group this player belongs to. Only if synced.
                             */
                            $this->_SetNewSyncMembers((string) $Data->Value);
                            break;
                        case 'will_sleep_in':
                            $this->_SetNewSleepTimeout((int) $Data->Value);
                            break;
                        case 'mixer volume':
                            $this->_SetNewVolume((int) $Data->Value);
                            break;
                        case 'mixer bass':
                            if ($this->ReadPropertyBoolean('enableBass')) {
                                $this->_SetNewBass((int) $Data->Value);
                            }
                            break;
                        case 'mixer treble':
                            if ($this->ReadPropertyBoolean('enableTreble')) {
                                $this->_SetNewTreble((int) $Data->Value);
                            }
                            break;
                        case 'mixer pitch':
                            if ($this->ReadPropertyBoolean('enablePitch')) {
                                $this->_SetNewPitch((int) $Data->Value);
                            }
                            break;
                        case 'playlist repeat':
                            $Value = (int) $Data->Value;
                            if ($Value == 1) {
                                $Value = 2;
                            } elseif ($Value == 2) {
                                $Value = 1;
                            }
                            $this->SetValueInteger('Repeat', $Value);
                            break;
                        case 'playlist shuffle':
                            if ($this->GetValue('Shuffle') != (int) $Data->Value) {
                                $this->SetValueInteger('Shuffle', (int) $Data->Value);
                            }
                            break;
                        case 'playlist_tracks':
                            if ($this->GetValue('Tracks') != (int) $Data->Value) {
                                $this->SetValueInteger('Tracks', (int) $Data->Value);
                            }
                            break;
                        case 'playlist mode':
                            //TODO
                            break;
                        case 'playlist_cur_index':
                        case 'playlist index':
                            if ($this->GetValue('Index') != ((int) $Data->Value + 1)) {
                                $this->SetValueInteger('Index', (int) $Data->Value + 1);
                            }
                            break;
                        case 'playlist_name':
                            $this->SetValueString('Playlistname', trim((string) $Data->Value));
                            break;
                            //playlist_timestamp:1474744498.14079
                            // merken und auf änderung ein Refresh machen ?!
                        case 'current_title':
                            break;
                        case 'title':
                            $this->SetValueString('Title', trim((string) $Data->Value));
                            break;
                        case 'genre':
                            $this->SetValueString('Genre', trim((string) $Data->Value));
                            break;
                        case 'artist':
                            $this->SetValueString('Artist', trim((string) $Data->Value));
                            break;
                        case 'album':
                            $this->SetValueString('Album', trim((string) $Data->Value));
                            break;
                    }
                }
                if (!$isSyncActive) {
                    $this->_SetNewSyncMaster(false);
                    $this->SyncMaster = '';
                    $this->_SetNewSyncMembers('-');
                }
                break;
            default:
                return false;
        }

        return true;
    }

    //################# Datenaustausch

    /**
     * Send
     * Konvertiert $Data zu einem JSONString und versendet diese an den Splitter.
     *
     * @param \SqueezeBox\LMSData $LMSData Zu versendende Daten.
     * @return \SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function Send(\SqueezeBox\LMSData $LMSData): ?\SqueezeBox\LMSData
    {
        if ($this->ReadPropertyString('Address') == '') {
            return null;
        }

        try {
            if (!$this->_isPlayerConnected() && ($LMSData->Command[0] != 'connected')) {
                throw new Exception($this->Translate('Player not connected'), E_USER_NOTICE);
            }
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
            $LMSData->Address = $this->ReadPropertyString('Address');
            $this->SendDebug('Send', $LMSData, 0);

            $answer = $this->SendDataToParent($LMSData->ToJSONString('{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}'));
            if ($answer === false) {
                $this->SendDebug('Response', 'No valid answer', 0);
                return null;
            }
            $result = unserialize($answer);
            if ($LMSData->needResponse === false) {
                return $result;
            }
            $LMSData->Data = $result->Data;
            $this->SendDebug('Response', $LMSData, 0);
            return $LMSData;
        } catch (Exception $exc) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            restore_error_handler();
            return null;
        }
    }
}
