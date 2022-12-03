<?php

declare(strict_types=1);

/*
 * @addtogroup squeezebox
 * @{
 *
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.70
 *
 */
require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen
require_once __DIR__ . '/../libs/SqueezeBoxClass.php';  // diverse Klassen

eval('declare(strict_types=1);namespace SqueezeboxDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');

/**
 * SqueezeboxDevice Klasse für eine SqueezeBox-Instanz in IPS.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2021 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.70
 *
 * @example <b>Ohne</b>
 *
 * @property int $ParentID
 * @property array $Multi_Playlist Alle Datensätze der Playlisten
 * @property int $PlayerMode
 * @property int $PlayerTrackIndex
 * @property int $PlayerShuffle
 * @property int $PlayerTracks
 * @property int $PositionRAW
 * @property bool $isSeekable
 * @property string $SyncMaster
 * @property string $SyncMembers
 */
class SqueezeboxDevice extends IPSModule
{
    use LMSHTMLTable,
        LMSSongURL,
        LMSCover,
        LSQProfile,
        \SqueezeboxDevice\VariableHelper,
        \SqueezeboxDevice\VariableProfileHelper,
        \squeezebox\DebugHelper,
        \SqueezeboxDevice\BufferHelper,
        \SqueezeboxDevice\InstanceStatus,
        \SqueezeboxDevice\WebhookHelper {
        \SqueezeboxDevice\InstanceStatus::MessageSink as IOMessageSink;
        \SqueezeboxDevice\InstanceStatus::RegisterParent as IORegisterParent;
        \SqueezeboxDevice\InstanceStatus::RequestAction as IORequestAction;
    }
    private $Socket = false;
    private $Error = false;

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{96A9AB3A-2538-42C5-A130-FC34205A706A}');
        $this->SetReceiveDataFilter('.*"Address":"".*');
        $this->RegisterPropertyString('Address', '');
        $this->RegisterPropertyInteger('Interval', 2);
        $this->RegisterPropertyString('CoverSize', 'cover');
        $this->RegisterPropertyBoolean('enableBass', true);
        $this->RegisterPropertyBoolean('enableTreble', true);
        $this->RegisterPropertyBoolean('enablePitch', true);
        $this->RegisterPropertyBoolean('enableRandomplay', true);
        $this->RegisterPropertyBoolean('enableRawDuration', false);
        $this->RegisterPropertyBoolean('enableRawPosition', false);
        $this->RegisterPropertyBoolean('showSyncMaster', true);
        $this->RegisterPropertyBoolean('showSyncControl', true);
        $this->RegisterPropertyBoolean('showPlaylist', true);
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
     * Interne Funktion des SDK.
     */
    public function Destroy()
    {
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return parent::Destroy();
        }
        if (!IPS_InstanceExists($this->InstanceID)) {
            $CoverID = @IPS_GetObjectIDByIdent('CoverIMG', $this->InstanceID);
            if ($CoverID > 0) {
                @IPS_DeleteMedia($CoverID, true);
            }
            $this->UnregisterHook('/hook/SqueezeBoxPlaylist' . $this->InstanceID);
            $this->DeleteProfile();
        }
        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
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
        $vid = @$this->GetIDForIdent('Interpret');
        if ($vid > 0) {
            @IPS_SetIdent($vid, 'Artist');
        }

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
            $this->RegisterVariableInteger('Preset', 'Preset', 'LSQ.Preset', 2);
            $this->EnableAction('Preset');
            $this->RegisterVariableBoolean('Mute', 'Mute', '~Switch', 4);
            $this->EnableAction('Mute');
            $this->RegisterVariableInteger('Volume', 'Volume', 'LSQ.Intensity', 5);
            $this->EnableAction('Volume');
            if ($this->ReadPropertyBoolean('enableBass')) {
                $this->RegisterVariableInteger('Bass', 'Bass', 'LSQ.Intensity', 6);
                $this->EnableAction('Bass');
            } else {
                $this->UnregisterVariable('Bass');
            }
            if ($this->ReadPropertyBoolean('enableTreble')) {
                $this->RegisterVariableInteger('Treble', $this->Translate('Treble'), 'LSQ.Intensity', 7);
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

            $this->RegisterVariableInteger('Signalstrength', $this->Translate('Signalstrength'), 'LSQ.Intensity', 31);
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
        $this->PlayerMode = $this->RegisterVariableInteger('Status', $this->Translate('State'), 'LSQ.Status', 3);
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
        $this->PlayerShuffle = $this->RegisterVariableInteger('Shuffle', $this->Translate('Shuffle'), 'LSQ.Shuffle', 9);
        $this->EnableAction('Shuffle');
        $this->RegisterMessage($this->PlayerShuffle, VM_UPDATE);
        $this->RegisterVariableInteger('Repeat', $this->Translate('Repeat'), 'LSQ.Repeat', 10);
        $this->EnableAction('Repeat');
        $this->PlayerTracks = $this->RegisterVariableInteger('Tracks', $this->Translate('Tracks in Playlist'), '', 11);
        $this->RegisterMessage($this->PlayerTracks, VM_UPDATE);
        $this->RegisterProfileInteger('LSQ.Tracklist.' . $this->InstanceID, '', '', '', (($this->GetValue('Tracks') == 0) ? 0 : 1), $this->GetValue('Tracks'), 1);
        $this->PlayerTrackIndex = $this->RegisterVariableInteger('Index', 'Playlist Position', 'LSQ.Tracklist.' . $this->InstanceID, 12);
        $this->EnableAction('Index');
        $this->RegisterMessage($this->PlayerTrackIndex, VM_UPDATE);
        $this->RegisterVariableString('Playlistname', 'Playlist', '', 19);
        $this->RegisterVariableString('Album', 'Album', '', 20);
        $this->RegisterVariableString('Title', $this->Translate('Title'), '', 21);
        $this->RegisterVariableString('Artist', $this->Translate('Artist'), '', 22);
        $this->RegisterVariableString('Genre', $this->Translate('Genre'), '', 23);
        $this->RegisterVariableString('Duration', $this->Translate('Duration'), '', 24);
        $this->RegisterVariableString('Position', $this->Translate('Position'), '', 25);
        $this->RegisterVariableInteger('Position2', 'Position', 'LSQ.Intensity', 26);
        $this->DisableAction('Position2');
        $this->RegisterVariableInteger('SleepTimer', $this->Translate('Sleeptimer'), 'LSQ.SleepTimer', 32);
        $this->EnableAction('SleepTimer');
        $this->RegisterVariableString('SleepTimeout', $this->Translate('Switch off in'), '', 33);

        // Playlist
        if ($this->ReadPropertyBoolean('showPlaylist')) {
            $this->RegisterVariableString('Playlist', 'Playlist', '~HTMLBox', 30);
        } else {
            $this->UnregisterVariable('Playlist');
        }

        // Wenn Kernel nicht bereit, dann warten... wenn unser IO Aktiv wird, holen wir unsere Daten :)
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Playlist
        if ($this->ReadPropertyBoolean('showPlaylist')) {
            $this->RegisterHook('/hook/SqueezeBoxPlaylist' . $this->InstanceID);
        } else {
            $this->UnregisterHook('/hook/SqueezeBoxPlaylist' . $this->InstanceID);
        }
        //$this->SetStatus(IS_ACTIVE);
        // Wenn Parent aktiv, dann Anmeldung an der Hardware bzw. Datenabgleich starten
        $this->RegisterParent();
        if ($this->HasActiveParent()) {
            $this->IOChangeState(IS_ACTIVE);
        }
        $this->_SetNewSyncProfil();
    }

    /**
     * Interne Funktion des SDK.
     *
     *
     * @param type $TimeStamp
     * @param type $SenderID
     * @param type $Message
     * @param type $Data
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
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
//                    if ($Data[1] === true)
                    $this->_RefreshPlaylist();
                }
                if ($SenderID == $this->PlayerTrackIndex) {
//                    if ($Data[1] == true)
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
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen..
     *
     * @return bool
     */
    public function RequestAllState()
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
        $this->RequestState('Signalstrength');
        $this->RequestState('SleepTimeout');
        $this->RequestState('Randomplay');
        $LMSData = $this->SendDirect(new LMSData(['status', '-', 1], ['tags:gladiqrRtueJINpsy', 'subscribe:0']));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $this->DecodeLMSResponse($LMSData);
        //$this->SetCover();
        if ($this->GetValue('Status') == 2) {
            $this->_StartSubscribe();
        }
        return true;
    }

    /**
     * IPS-Instanz-Funktion 'LSQ_RequestState'.
     * Fragt einen Wert des Players ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @param string $Ident Der Ident der abzufragenden Statusvariable.
     * @result bool True wenn erfolgreich.
     */
    public function RequestState(string $Ident)
    {
        switch ($Ident) {
            case 'Name':
                $LMSResponse = new LMSData('name', '?');
                break;
            case 'Power':
                $LMSResponse = new LMSData('power', '?');
                break;
            case 'Status':
                if (!$this->_isPlayerOn()) {
                    $this->_SetModeToStop();
                    return true;
                }
                $LMSResponse = new LMSData('mode', '?');
                break;
            case 'Mute':
                $LMSResponse = new LMSData(['mixer', 'muting'], '?');
                break;
            case 'Volume':
                $LMSResponse = new LMSData(['mixer', 'power'], '?');
                break;
            case 'Bass':
                if (!$this->ReadPropertyBoolean('enableBass')) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Invalid ident'));
                    restore_error_handler();
                    return false;
                }
                $LMSResponse = new LMSData(['mixer', 'bass'], '?');
                break;
            case 'Treble':
                if (!$this->ReadPropertyBoolean('enableTreble')) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Invalid ident'));
                    restore_error_handler();
                    return false;
                }
                $LMSResponse = new LMSData(['mixer', 'treble'], '?');
                break;
            case 'Pitch':
                if (!$this->ReadPropertyBoolean('enablePitch')) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Invalid ident'));
                    restore_error_handler();
                    return false;
                }
                $LMSResponse = new LMSData(['mixer', 'pitch'], '?');
                break;
            case 'Shuffle':
                $LMSResponse = new LMSData(['playlist', 'shuffle'], '?');
                break;
            case 'Repeat':
                $LMSResponse = new LMSData(['playlist', 'repeat'], '?');
                break;
            case 'Tracks':
                $LMSResponse = new LMSData(['playlist', 'tracks'], '?');
                break;
            case 'Index':
                $LMSResponse = new LMSData(['playlist', 'index'], '?');
                break;
            case 'Playlistname':
                $LMSResponse = new LMSData(['playlist', 'name'], '?');
                break;
            case 'Album':
                $LMSResponse = new LMSData('album', '?');
                break;
            case 'Title':
                $LMSResponse = new LMSData('title', '?');
                break;
            case 'Artist':
                $LMSResponse = new LMSData('artist', '?');
                break;
            case 'Genre':
                $LMSResponse = new LMSData('genre', '?');
                break;
            case 'Duration':
                $LMSResponse = new LMSData('duration', '?');
                break;
            case 'Position2':
            case 'Position':
                $LMSResponse = new LMSData('time', '?');
                break;
            case 'Signalstrength':
                $LMSResponse = new LMSData('signalstrength', '?');
                break;
            case 'SleepTimeout':
                $LMSResponse = new LMSData('sleep', '?');
                break;
            case 'Connected':
                $LMSResponse = new LMSData('connected', '?');
                break;
            case 'Sync':
                $LMSResponse = new LMSData('sync', '?');
                break;
            case 'Remote':
                $LMSResponse = new LMSData('remote', '?');
                break;
            case 'Randomplay':
                $LMSResponse = new LMSData('randomplayisactive', '');
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

    public function SetSync(int $SlaveInstanceID)
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
        $ret = $this->SendDirect(new LMSData('sync', $ClientMac));
        return $ret->Data[0] == $ClientMac;
    }

    /**
     * Gibt alle mit diesem Gerät synchronisierte Instanzen zurück.
     *
     * @return array
     * @exception
     */
    public function GetSync()
    {
        $FoundInstanzIDs = [];
        $ret = $this->SendDirect(new LMSData('sync', '?'))->Data[0];
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
     * Sync dieses Gerätes aufheben.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetUnSync()
    {
        $ret = $this->SendDirect(new LMSData('sync', '-'))->Data[0];
        return $ret == '-';
    }
    ///////////////////////////////////////////////////////////////
    // START PLAYER
    ///////////////////////////////////////////////////////////////

    /**
     * Setzten den Namen in dem Device.
     *
     * @param string $Name
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetName(string $Name)
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('name', $Name));
        if ($LMSData === null) {
            return false;
        }
        $this->_SetNewName($LMSData->Data[0]);
        return $LMSData->Data[0] == $Name;
    }

    /**
     * Liefert den Namen von dem Device.
     *
     * @return string
     * @exception
     */
    public function GetName()
    {
        $LMSData = $this->SendDirect(new LMSData('name', '?'));
        if ($LMSData === null) {
            return false;
        }
        $this->_SetNewName($LMSData->Data[0]);
        return $LMSData->Data[0];
    }

    /**
     * Schaltet das Gerät ein oder aus.
     *
     * @param bool $Value
     *                    false  = ausschalten
     *                    true = einschalten
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function Power(bool $Value)
    {
        $LMSData = $this->SendDirect(new LMSData('power', (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == (int) $Value;
    }

    /**
     * Restzeit bis zum Sleep setzen.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetSleep(int $Seconds)
    {
        if ($Seconds < 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Seconds invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('sleep', $Seconds));
        if ($LMSData === null) {
            return false;
        }
        $this->_SetNewSleepTimeout((int) $LMSData->Data[0]);
        return (int) $LMSData->Data[0] == $Seconds;
    }

    /**
     * Setzten der Stummschaltung.
     *
     * @param boolean $Value
     *                      true = Stumm an
     *                      false = Stumm aus
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetMute(bool $Value)
    {
        $LMSData = $this->SendDirect(new LMSData(['mixer', 'muting'], (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == (int) $Value;
    }

    /**
     * Setzten der Lautstärke.
     *
     * @param int $Value
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetVolume(int $Value)
    {
        if (($Value < 0) || ($Value > 100)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['mixer', 'volume'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * Setzten der Lautstärke.
     *
     * @param string $Value
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetVolumeEx(string $Value)
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
        $LMSData = $this->SendDirect(new LMSData(['mixer', 'volume'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * Setzt den Bass-Wert.
     *
     * @param int $Value
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetBass(int $Value)
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
        $LMSData = $this->SendDirect(new LMSData(['mixer', 'bass'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * Setzt den Bass-Wert.
     *
     * @param string $Value
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetBassEx(string $Value)
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
        $LMSData = $this->SendDirect(new LMSData(['mixer', 'bass'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * Setzt den Treble-Wert.
     *
     * @param int $Value
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetTreble(int $Value)
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
        $LMSData = $this->SendDirect(new LMSData(['mixer', 'treble'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * Setzt den Treble-Wert.
     *
     * @param string $Value
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetTrebleEx(string $Value)
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
        $LMSData = $this->SendDirect(new LMSData(['mixer', 'treble'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * Setzt den Pitch-Wert.
     *
     * @param int $Value
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetPitch(int $Value)
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
        $LMSData = $this->SendDirect(new LMSData(['mixer', 'pitch'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * Setzt den Pitch-Wert.
     *
     * @param string $Value
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetPitchEx(string $Value)
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
        $LMSData = $this->SendDirect(new LMSData(['mixer', 'pitch'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * Simuliert einen Tastendruck.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function PreviousButton()
    {
        $LMSData = $this->SendDirect(new LMSData('button', 'jump_rew'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == 'jump_rew';
    }

    /**
     * Simuliert einen Tastendruck.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function NextButton()
    {
        $LMSData = $this->SendDirect(new LMSData('button', 'jump_fwd'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == 'jump_fwd';
    }

    /**
     * Simuliert einen Tastendruck auf einen der Preset-Tasten.
     *
     * @param int $Value
     *                   1 - 6 = Taste 1 bis 6
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SelectPreset(int $Value)
    {
        if (($Value < 1) || ($Value > 6)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $Value = 'preset_' . $Value . '.single';
        $LMSData = $this->SendDirect(new LMSData('button', $Value));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == $Value;
    }

    /**
     * Startet die Wiedergabe.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function Play()
    {
        $LMSData = $this->SendDirect(new LMSData('play', ''));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * Startet die Wiedergabe.
     *
     * @param FadeIn Einblendezeit
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function PlayEx(int $FadeIn)
    {
        $LMSData = $this->SendDirect(new LMSData('play', (int) $FadeIn));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * Stoppt die Wiedergabe.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function Stop()
    {
        $LMSData = $this->SendDirect(new LMSData('stop', ''));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    //fertig

    /**
     * Pausiert die Wiedergabe.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function Pause()
    {
        $LMSData = $this->SendDirect(new LMSData('pause', '1'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == '1';
    }

    /**
     * Setzt eine absolute Zeit-Position des aktuellen Titels.
     *
     * @param int $Value Zeit in Sekunden.
     *
     * @return bool true bei erfolgreicher Ausführung und Rückmeldung.
     * @exception Wenn Befehl nicht ausgeführt werden konnte.
     */
    public function SetPosition(int $Value)
    {
        if ($Value > $this->DurationRAW) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value greater as duration'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData('time', $Value));
        if ($LMSData === null) {
            return false;
        }
        return $Value == $LMSData->Data[0];
    }

    public function DisplayLine(string $Text, int $Duration)
    {
        return $this->DisplayLines('', $Text, true, $Duration);
    }

    public function DisplayLineEx(string $Text, int $Duration, bool $Centered, int $Brightness)
    {
        return $this->DisplayLines('', $Text, true, $Duration, $Centered, $Brightness);
    }

    public function Display2Lines(string $Text1, string $Text2, int $Duration)
    {
        return $this->DisplayLines($Text1, $Text2, false, $Duration);
    }

    public function Display2LinesEx(string $Text1, string $Text2, int $Duration, bool $Centered, int $Brightness)
    {
        return $this->DisplayLines($Text1, $Text2, false, $Duration, $Centered, $Brightness);
    }

    public function DisplayText(string $Text1, string $Text2, int $Duration)
    {
        $Duration = ($Duration < 3 ? 3 : $Duration);
        if ($Text1 == '') {
            $Values[] = ' ';
        } else {
            $Values[] = utf8_decode($Text1);
        }

        if ($Text2 == '') {
            $Values[] = ' ';
        } else {
            $Values[] = utf8_decode($Text2);
        }
        $LMSData = $this->SendDirect(new LMSData('display', $Values));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function GetLinesPerScreen()
    {
        $LMSData = $this->SendDirect(new LMSData('linesperscreen', '?'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0];
    }

    public function GetDisplayedText()
    {
        $LMSData = $this->SendDirect(new LMSData('display', ['?', '?']));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data;
    }

    public function GetDisplayedNow()
    {
        $LMSData = $this->SendDirect(new LMSData('displaynow', ['?', '?']));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data;
    }

    public function PressButton(string $ButtonCode)
    {
        $LMSData = $this->SendDirect(new LMSData('button', $ButtonCode));
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
     * Lädt eine neue Playlist.
     *
     * @param string $URL SongURL, Verzeichnis oder Remote-Stream
     *
     * @return type
     */
    public function PlayUrl(string $URL)
    {
        return $this->PlayUrlEx($URL, '');
    }

    public function PlayUrlEx(string $URL, string $DisplayTitle)
    {
        if ($this->GetValidSongURL($URL) == false) {
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'play'], [$URL, $DisplayTitle]));
        if ($LMSData === null) {
            return false;
        }
        //TODO
        return $LMSData;
//
//        $ret = $LMSData->Data[0];
//        if (($ret == '/' . $Name) or ( $ret == '\\' . $Name))
//        {
//            trigger_error($this->Translate('Playlist not found.'), E_USER_NOTICE);
//            return false;
//        }
//        return $ret;
    }
    public function PlayUrlSpecial(string $URL)
    {
        return $this->PlayUrlSpecialEx($URL, '');
    }

    public function PlayUrlSpecialEx(string $URL, string $DisplayTitle)
    {
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'play'], [$URL, $DisplayTitle]));
        if ($LMSData === null) {
            return false;
        }
        //TODO
        return $LMSData;
//
//        $ret = $LMSData->Data[0];
//        if (($ret == '/' . $Name) or ( $ret == '\\' . $Name))
//        {
//            trigger_error($this->Translate('Playlist not found.'), E_USER_NOTICE);
//            return false;
//        }
//        return $ret;
    }
    public function PlayFavorite(string $FavoriteID)
    {
        if ($FavoriteID == '') {
            $Data = ['play', 'item_id:.'];
        } else {
            $Data = ['play', 'item_id:' . rawurlencode($FavoriteID)];
        }

        $LMSData = $this->SendDirect(new LMSData(['favorites', 'playlist'], $Data));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * Am Ende hinzufügen.
     *
     * @param string $URL
     *
     * @return type
     */
    public function AddToPlaylistByUrl(string $URL)
    {
        return $this->AddUrlToPlaylistEx($URL, -1);
    }

    public function AddToPlaylistByUrlEx(string $URL, string $DisplayTitle)
    {
        if ($this->GetValidSongURL($URL) == false) {
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'add'], [$URL, $DisplayTitle]));
        if ($LMSData === null) {
            return false;
        }
        //TODO
        return $LMSData;
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

    public function DeleteFromPlaylistByUrl(string $URL)
    {
        if ($this->GetValidSongURL($URL) == false) {
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'deleteitem'], $URL));
        if ($LMSData === null) {
            return false;
        }
        //TODO
        return $LMSData;
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

    public function MoveSongInPlaylist(int $Position, int $NewPosition)
    {
        $Position--;
        $NewPosition--;
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'move'], [$Position, $NewPosition]));
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

    /*
      The "playlist delete" command deletes the song at the specified index from the current playlist.
     */
    public function DeleteFromPlaylistByIndex(int $Position)
    {
        $Position--;
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'delete'], $Position));
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

    public function PreviewPlaylistStart(string $Name)
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'preview'], 'url:' . $Name));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == 'url:' . $Name;
    }

    public function PreviewPlaylistStop()
    {
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'preview'], 'cmd:stop'));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * Speichert die aktuelle Wiedergabeliste vom Gerät in einer unter $Name angegebenen Wiedergabelisten-Datei auf dem LMS-Server.
     *
     * @param string $Name
     *                     Der Name der Wiedergabeliste. Ist diese Liste auf dem Server schon vorhanden, wird sie überschrieben.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SavePlaylist(string $Name)
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'save'], [$Name, 'silent:1']));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == $Name;
    }

    /**
     * Speichert die aktuelle Wiedergabeliste vom Gerät in einer festen Wiedergabelisten-Datei auf dem LMS-Server.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SaveTempPlaylist()
    {
        return $this->SavePlaylist('tempplaylist_' . str_replace(':', '', $this->ReadPropertyString('Address')));
    }

    /**
     * Lädt eine Wiedergabelisten-Datei aus dem LMS-Server und spring an die zuletzt abgespielten Track.
     *
     * @param string $Name
     *                     Der Name der Wiedergabeliste.
     *
     * @return string
     *                Kompletter Pfad der Wiedergabeliste.
     * @exception
     */
    public function ResumePlaylist(string $Name)
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'resume'], [$Name, 'noplay:1']));
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
     * Lädt eine zuvor gespeicherte Wiedergabelisten-Datei und setzt die Wiedergabe fort.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function LoadTempPlaylist()
    {
        $Playlist = 'tempplaylist_' . str_replace(':', '', $this->ReadPropertyString('Address'));
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'resume'], [$Playlist, 'wipePlaylist:1', 'noplay:0']));
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
     * Lädt eine Wiedergabelisten-Datei aus dem LMS-Server und startet die Wiedergabe derselben auf dem Gerät.
     *
     * @param string $Name
     *                     Der Name der Wiedergabeliste. Eine URL zu einem Stream, einem Verzeichnis oder einer Datei
     *
     * @return string
     *                Kompletter Pfad der Wiedergabeliste.
     * @exception
     */
    public function LoadPlaylist(string $Name)
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'load'], [$Name, 'noplay:1']));
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

    public function LoadPlaylistBySearch(string $Genre, string $Artist, string $Album)
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
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'loadalbum'], [$Genre, $Artist, $Album]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function AddToPlaylistBySearch(string $Genre, string $Artist, string $Album)
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
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'addalbum'], [$Genre, $Artist, $Album]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function LoadPlaylistByTrackTitel(string $Titel)
    {
        if ($Titel == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'loadtracks'], 'track.titlesearch=' . $Titel));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function LoadPlaylistByAlbumTitel(string $Titel)
    {
        if ($Titel == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'loadtracks'], 'album.titlesearch=' . $Titel));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function LoadPlaylistByArtistName(string $Name)
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'loadtracks'], 'contributor.namesearch=' . $Name));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function LoadPlaylistByFavoriteID(string $FavoriteID)
    {
        if ($FavoriteID == '') {
            $Data = ['load', 'item_id:.'];
        } else {
            $Data = ['load', 'item_id:' . rawurlencode($FavoriteID)];
        }

        $LMSData = $this->SendDirect(new LMSData(['favorites', 'playlist'], $Data));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function AddToPlaylistByTrackTitel(string $Titel)
    {
        if ($Titel == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'addtracks'], 'track.titlesearch=' . $Titel));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function AddToPlaylistByAlbumTitel(string $Titel)
    {
        if ($Titel == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'addtracks'], 'album.titlesearch=' . $Titel));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function AddToPlaylistByArtistName(string $Name)
    {
        if ($Name == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'addtracks'], 'contributor.namesearch=' . $Name));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function AddToPlaylistByFavoriteID(string $FavoriteID)
    {
        if ($FavoriteID == '') {
            $Data = ['add', 'item_id:.'];
        } else {
            $Data = ['add', 'item_id:' . rawurlencode($FavoriteID)];
        }
        $LMSData = $this->SendDirect(new LMSData(['favorites', 'playlist'], $Data));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function InsertInPlaylistBySearch(string $Genre, string $Artist, string $Album)
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
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'insertalbum'], [$Genre, $Artist, $Album]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function DeleteFromPlaylistBySearch(string $Genre, string $Artist, string $Album)
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

        $LMSData = $this->SendDirect(new LMSData(['playlist', 'deletealbum'], [$Genre, $Artist, $Album]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    //The "playlist clear" command removes any song that is on the playlist. The player is stopped.
    public function ClearPlaylist()
    {
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'clear'], ''));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function AddPlaylistIndexToZappedList(int $Position)
    {
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'zap'], $Position));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function GetPlaylistURL()
    {
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'url'], '?'));
        if ($LMSData === null) {
            return false;
        }
        //TODO
        return $LMSData;
    }

    public function IsPlaylistModified()
    {
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'modified'], '?'));
        if ($LMSData === null) {
            return false;
        }
        //TODO
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
    public function GetPlaylistInfo()
    {
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'playlistsinfo'], ''));
        if ($LMSData === null) {
            return false;
        }
        if (count($LMSData->Data) == 1) {
            return [];
        }
        $SongInfo = new LMSSongInfo($LMSData->Data);
        return $SongInfo->GetSong();
    }

    /**
     * Springt in der aktuellen Wiedergabeliste auf einen Titel.
     *
     * @param int $Index
     *                   Track in der Wiedergabeliste auf welchen gesprungen werden soll.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function GoToTrack(int $Index)
    {
        if (($Index < 1) || ($Index > $this->GetValue('Tracks'))) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Index'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'index'], $Index - 1));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == $Index - 1;
    }

    /**
     * Springt in der aktuellen Wiedergabeliste auf den nächsten Titel.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function NextTrack()
    {
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'index'], '+1'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == '+1';
    }

    /**
     * Springt in der aktuellen Wiedergabeliste auf den vorherigen Titel.
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function PreviousTrack()
    {
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'index'], '-1'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == '-1';
    }

    /**
     * Setzen des Zufallsmodus.
     *
     * @param int $Value
     *                   0 = aus
     *                   1 = Titel
     *                   2 = Album
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetShuffle(int $Value)
    {
        if (($Value < 0) || ($Value > 2)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value must be 0, 1 or 2.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'shuffle'], $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) $LMSData->Data[0] == $Value;
    }

    /**
     * Setzen des Wiederholungsmodus.
     *
     * @param int $Value
     *                   0 = aus
     *                   1 = Titel
     *                   2 = Album
     *
     * @return bool
     *              true bei erfolgreicher Ausführung und Rückmeldung
     * @exception
     */
    public function SetRepeat(int $Value)
    {
        if (($Value < 0) || ($Value > 2)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Value must be 0, 1 or 2.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['playlist', 'repeat'], $Value));
        if ($LMSData === null) {
            return false;
        }

        return (int) $LMSData->Data[0] == $Value;
    }

    public function LoadPlaylistByAlbumID(int $AlbumID)
    {
        return $this->_PlaylistControl('cmd:load', 'album_id:' . $AlbumID, sprintf($this->Translate('%s not found.'), 'AlbumID'));
    }

    public function LoadPlaylistByGenreID(int $GenreID)
    {
        return $this->_PlaylistControl('cmd:load', 'genre_id:' . $GenreID, sprintf($this->Translate('%s not found.'), 'GenreID'));
    }

    public function LoadPlaylistByArtistID(int $ArtistID)
    {
        return $this->_PlaylistControl('cmd:load', 'artist_id:' . $ArtistID, sprintf($this->Translate('%s not found.'), 'ArtistID'));
    }

    public function LoadPlaylistByPlaylistID(int $PlaylistID)
    {
        return $this->_PlaylistControl('cmd:load', 'playlist_id:' . $PlaylistID, sprintf($this->Translate('%s not found.'), 'PlaylistID'));
    }

    public function LoadPlaylistBySongIDs(string $SongIDs)
    {
        return $this->_PlaylistControl('cmd:load', 'track_id:' . $SongIDs, sprintf($this->Translate('%s not found.'), 'SongIDs'));
    }

    public function LoadPlaylistByFolderID(int $FolderID)
    {
        return $this->_PlaylistControl('cmd:load', 'folder_id:' . $FolderID, sprintf($this->Translate('%s not found.'), 'FolderID'));
    }

    public function AddToPlaylistByAlbumID(int $AlbumID)
    {
        return $this->_PlaylistControl('cmd:add', 'album_id:' . $AlbumID, sprintf($this->Translate('%s not found.'), 'AlbumID'));
    }

    public function AddToPlaylistByGenreID(int $GenreID)
    {
        return $this->_PlaylistControl('cmd:add', 'genre_id:' . $GenreID, sprintf($this->Translate('%s not found.'), 'GenreID'));
    }

    public function AddToPlaylistByArtistID(int $ArtistID)
    {
        return $this->_PlaylistControl('cmd:add', 'artist_id:' . $ArtistID, sprintf($this->Translate('%s not found.'), 'ArtistID'));
    }

    public function AddToPlaylistByPlaylistID(int $PlaylistID)
    {
        return $this->_PlaylistControl('cmd:add', 'playlist_id:' . $PlaylistID, sprintf($this->Translate('%s not found.'), 'PlaylistID'));
    }

    public function AddToPlaylistBySongIDs(string $SongIDs)
    {
        return $this->_PlaylistControl('cmd:add', 'track_id:' . $SongIDs, sprintf($this->Translate('%s not found.'), 'SongIDs'));
    }

    public function AddToPlaylistByFolderID(int $FolderID)
    {
        return $this->_PlaylistControl('cmd:add', 'folder_id:' . $FolderID, sprintf($this->Translate('%s not found.'), 'FolderID'));
    }

    //todo alles auch mit add + move
    //todo insert testen !?!?
    public function InsertInPlaylistByAlbumID(int $AlbumID)
    {
        return $this->_PlaylistControl('cmd:insert', 'album_id:' . $AlbumID, sprintf($this->Translate('%s not found.'), 'AlbumID'));
    }

    public function InsertInPlaylistByGenreID(int $GenreID)
    {
        return $this->_PlaylistControl('cmd:insert', 'genre_id:' . $GenreID, sprintf($this->Translate('%s not found.'), 'GenreID'));
    }

    public function InsertInPlaylistByArtistID(int $ArtistID)
    {
        return $this->_PlaylistControl('cmd:insert', 'artist_id:' . $ArtistID, sprintf($this->Translate('%s not found.'), 'ArtistID'));
    }

    public function InsertInPlaylistByPlaylistID(int $PlaylistID)
    {
        return $this->_PlaylistControl('cmd:insert', 'playlist_id:' . $PlaylistID, sprintf($this->Translate('%s not found.'), 'PlaylistID'));
    }

    public function InsertInPlaylistBySongIDs(string $SongIDs)
    {
        return $this->_PlaylistControl('cmd:insert', 'track_id:' . $SongIDs, sprintf($this->Translate('%s not found.'), 'SongIDs'));
    }

    public function InsertInPlaylistByFolderID(int $FolderID)
    {
        return $this->_PlaylistControl('cmd:insert', 'folder_id:' . $FolderID, sprintf($this->Translate('%s not found.'), 'FolderID'));
    }

    public function InsertInPlaylistByFavoriteID(string $FavoriteID)
    {
        if ($FavoriteID == '') {
            $Data = ['insert', 'item_id:.'];
        } else {
            $Data = ['insert', 'item_id:' . rawurlencode($FavoriteID)];
        }
        $LMSData = $this->SendDirect(new LMSData(['favorites', 'playlist'], $Data));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function DeleteFromPlaylistByAlbumID(int $AlbumID)
    {
        return $this->_PlaylistControl('cmd:delete', 'album_id:' . $AlbumID, sprintf($this->Translate('%s not found.'), 'AlbumID'));
    }

    public function DeleteFromPlaylistByGenreID(int $GenreID)
    {
        return $this->_PlaylistControl('cmd:delete', 'genre_id:' . $GenreID, sprintf($this->Translate('%s not found.'), 'GenreID'));
    }

    public function DeleteFromPlaylistByArtistID(int $ArtistID)
    {
        return $this->_PlaylistControl('cmd:delete', 'artist_id:' . $ArtistID, sprintf($this->Translate('%s not found.'), 'ArtistID'));
    }

    public function DeleteFromPlaylistByPlaylistID(int $PlaylistID)
    {
        return $this->_PlaylistControl('cmd:delete', 'playlist_id:' . $PlaylistID, sprintf($this->Translate('%s not found.'), 'PlaylistID'));
    }

    public function DeleteFromPlaylistBySongIDs(string $SongIDs)
    {
        return $this->_PlaylistControl('cmd:delete', 'track_id:' . $SongIDs, sprintf($this->Translate('%s not found.'), 'SongIDs'));
    }

    /**
     * Liefert Informationen über einen Song aus der aktuelle Wiedergabeliste.
     *
     * @param int $Index
     *                   $Index für die absolute Position des Titels in der Wiedergabeliste.
     *                   0 für den aktuellen Titel
     *
     * @return array
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
     * @exception
     */
    public function GetSongInfoByTrackIndex(int $Index)
    {
        $Index--;
        if ($Index == -1) {
            $Index = '-';
        }
        $LMSData = $this->SendDirect(new LMSData(['status', (string) $Index, '1'], 'tags:gladiqrRtueJINpsy'));
        if ($LMSData === null) {
            return false;
        }
        $SongInfo = new LMSSongInfo($LMSData->Data);
        $SongArray = $SongInfo->GetSong();
        if (count($SongArray) == 1) {
            throw new Exception($this->Translate('Index not valid.'));
        }
        return $SongArray;
    }

    /**
     * Liefert Informationen über alle Songs aus der aktuelle Wiedergabeliste.
     *
     * @return array[index]
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
     * @exception
     */
    public function GetSongInfoOfCurrentPlaylist()
    {
        $max = $this->GetValue('Tracks');
        $LMSData = $this->SendDirect(new LMSData(['status', '0', (string) $max], 'tags:gladiqrRtueJINpsy'));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = new LMSSongInfo($LMSData->Data);
        return $SongInfo->GetAllSongs();
    }

    ///////////////////////////////////////////////////////////////
    // ENDE PLAYERLIST
    ///////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////
    // START RANDOMPLAY
    ///////////////////////////////////////////////////////////////

    public function StartRandomplayOfTracks()
    {
        $LMSData = $this->SendDirect(new LMSData(['randomplay'], ['tracks']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function StartRandomplayOfAlbums()
    {
        $LMSData = $this->SendDirect(new LMSData(['randomplay'], ['albums']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function StartRandomplayOfArtist()
    {
        $LMSData = $this->SendDirect(new LMSData(['randomplay'], ['contributors']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function StartRandomplayOfYear()
    {
        $LMSData = $this->SendDirect(new LMSData(['randomplay'], ['year']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function StopRandomplay()
    {
        $LMSData = $this->SendDirect(new LMSData(['randomplay'], ['disable']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function RandomplaySelectAllGenre(bool $Active)
    {
        $LMSData = $this->SendDirect(new LMSData(['randomplaygenreselectall'], [(int) $Active]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    public function RandomplaySelectGenre(string $Genre, bool $Active)
    {
        if ($Genre == '') {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Genre'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(['randomplaychoosegenre'], [$Genre, (int) $Active]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    ///////////////////////////////////////////////////////////////
    // ENDE RANDOMPLAY
    ///////////////////////////////////////////////////////////////
    //################# ActionHandler

    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return;
        }
        switch ($Ident) {
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
    public function ReceiveData($JSONString)
    {
        $this->SendDebug('Receive Event', $JSONString, 0);
        $Data = json_decode($JSONString);
        // Objekt erzeugen welches die Commands und die Values enthält.
        $LMSData = new LMSData();
        $LMSData->CreateFromGenericObject($Data);
        // Ist das Command schon bekannt ?

        if ($LMSData->Command[0] != false) {
            if ($LMSData->Command[0] == 'ignore') {
                return;
            }
            $this->DecodeLMSResponse($LMSData);
        } else {
            $this->SendDebug('UNKNOWN', $LMSData, 0);
        }
    }

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->ApplyChanges();
    }

    protected function RegisterParent()
    {
        $SplitterId = $this->IORegisterParent();
        if ($SplitterId > 0) {
            $IOId = @IPS_GetInstance($SplitterId)['ConnectionID'];
            if ($IOId > 0) {
                $this->SetSummary(IPS_GetProperty($IOId, 'Host'));

                return;
            }
        }
        $this->SetSummary(('none'));
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState($State)
    {
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $Value = IS_INACTIVE;
        if ($State == IS_ACTIVE) {
            $LMSResponse = $this->SendDirect(new LMSData('connected', '?'));
            if ($LMSResponse != null) {
                $Value = ($LMSResponse->Data[0] == '1') ? IS_ACTIVE : IS_INACTIVE;
            }
        }
        $this->SetStatus($Value);
    }

    /**
     * Verarbeitet Daten aus dem Webhook.
     *
     * @global array $_GET
     */
    protected function ProcessHookdata()
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
     * Konvertiert $Data zu einem String und versendet diesen direkt an den LMS.
     *
     * @param LMSData $LMSData Zu versendende Daten.
     *
     * @return LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    protected function SendDirect(LMSData $LMSData)
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

                $LoginData = (new LMSData('login', [$User, $Pass]))->ToRawStringForLMS();
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

            $ReplyData = new LMSResponse($answer);
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

    protected function SetStatus($State)
    {
        $this->SendDebug('OldState', $this->GetStatus(), 0);
        $this->SendDebug('NewState', $State, 0);
        if ($State != $this->GetStatus()) {
            parent::SetStatus($State);
        }
    }
    protected function ModulErrorHandler($errno, $errstr)
    {
        if (!(error_reporting() & $errno)) {
            // Dieser Fehlercode ist nicht in error_reporting enthalten
            return true;
        }
        $this->SendDebug('ERROR', utf8_decode($errstr), 0);
        echo $errstr . "\r\n";
    }

    private function GenerateHTMLStyleProperty()
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

    private function DisplayLines($Line1 = '', $Line2 = '', $Huge = false, $Duration = 3, $Centered = false, $Brightness = 4)
    {
        $Duration = ($Duration < 3 ? 3 : $Duration);
        $Brightness = ($Brightness < 0 ? 0 : $Brightness);
        $Brightness = ($Brightness > 4 ? 4 : $Brightness);
        $Values = [];
        if ($Line1 != '') {
            $Values[] = 'line1:' . utf8_decode($Line1);
        }
        if ($Line2 != '') {
            $Values[] = 'line2:' . utf8_decode($Line2);
        }
        $Values[] = 'duration:' . $Duration;
        $Values[] = 'brightness:' . $Brightness;
        if ($Huge) {
            $Values[] = 'font:huge';
        }
        if ($Centered) {
            $Values[] = 'centered:1';
        }
        $LMSData = $this->Send(new LMSData('show', $Values));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    private function _PlaylistControl(string $cmd, string $item, string $errormsg)
    {
        $LMSData = $this->SendDirect(new LMSData('playlistcontrol', [$cmd, $item]));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        /** @var array $LMSTaggedData */
        $LMSTaggedData = (new LMSTaggingArray($LMSData->Data))->DataArray();
        if (!array_key_exists('Count', $LMSTaggedData)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($errormsg, E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return true;
    }

    //################# PRIVATE
    private function _isPlayerConnected()
    {
        return $this->GetStatus() == IS_ACTIVE;
    }

    private function _isPlayerOn()
    {
        return (bool) $this->GetValue('Power');
    }

    private function _StartSubscribe()
    {
        if ($this->_isPlayerConnected()) {
            $this->Send(new LMSData(['status', '-', '1'], 'subscribe:' . $this->ReadPropertyInteger('Interval')), false);
        }
    }

    private function _StopSubscribe()
    {
        if ($this->_isPlayerConnected()) {
            if (($this->GetValue('SleepTimer')) == 0) {
                @$this->Send(new LMSData(['status', '-', '1'], 'subscribe:0'), false);
            }
        }
    }

    private function _SetNewName(string $Name)
    {
        if (!$this->ReadPropertyBoolean('changeName')) {
            return;
        }
        if (IPS_GetName($this->InstanceID) != trim($Name)) {
            IPS_SetName($this->InstanceID, trim($Name));
        }
    }

    private function _SetNewPower(bool $Power)
    {
        $this->SetValueBoolean('Power', $Power);
        if (!$Power) {
            $this->_SetModeToStop();
            $this->_SetNewSyncMaster(false);
            $this->_SetNewSyncMembers('-');
            $this->SetValueString('SleepTimeout', 0);
            $this->SetValueInteger('SleepTimer', 0);
        }
    }

    private function _SetModeToPlay()
    {
        if ($this->GetValue('Status') != 2) {
            $this->SetValueInteger('Status', 2);
        }
    }

    private function _SetModeToPause()
    {
        if (!$this->_isPlayerOn()) {
            return;
        }
        if ($this->GetValue('Status') != 3) {
            $this->SetValueInteger('Status', 3);
        }
    }

    private function _SetModeToStop()
    {
        if ($this->GetValue('Status') != 1) {
            $this->SetValueInteger('Status', 1);
        }
    }

    private function _SetNewVolume($Value)
    {
        if (is_string($Value) && (($Value[0] == '+') || $Value[0] == '-')) {
            $Value = $this->GetValue('Volume') + (int) $Value;
            if ($Value < 0) {
                $Value = 0;
            }
            if ($Value > 100) {
                $Value = 100;
            }
        }
        if ($Value < 0) {
            $Value = $Value - (2 * $Value);
            $this->SetValueBoolean('Mute', true);
        } else {
            $this->SetValueBoolean('Mute', false);
        }
        $this->SetValueInteger('Volume', $Value);
    }

    private function _SetNewBass($Value)
    {
        if ($Value == '') {
            return;
        }
        if (is_string($Value) && (($Value[0] == '+') || $Value[0] == '-')) {
            $Value = $this->GetValue('Bass') + (int) $Value;
            if ($Value < 0) {
                $Value = 0;
            }
            if ($Value > 100) {
                $Value = 100;
            }
        }
        $this->SetValueInteger('Bass', $Value);
    }

    private function _SetNewTreble($Value)
    {
        if ($Value == '') {
            return;
        }
        if (is_string($Value) && (($Value[0] == '+') || $Value[0] == '-')) {
            $Value = $this->GetValue('Treble') + (int) $Value;
            if ($Value < 0) {
                $Value = 0;
            }
            if ($Value > 100) {
                $Value = 100;
            }
        }
        $this->SetValueInteger('Treble', $Value);
    }

    private function _SetNewPitch($Value)
    {
        if ($Value == '') {
            return;
        }
        if (is_string($Value) && (($Value[0] == '+') || $Value[0] == '-')) {
            $Value = $this->GetValue('Pitch') + (int) $Value;
            if ($Value < 80) {
                $Value = 80;
            }
            if ($Value > 120) {
                $Value = 120;
            }
        }
        $this->SetValueInteger('Pitch', $Value);
    }

    private function _SetNewTime(int $Time)
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
            $this->SetValueInteger('Position2', intval(round($Value)));
        }
    }

    private function _SetNewDuration(int $Duration)
    {
        $this->DurationRAW = $Duration;
        if ($this->ReadPropertyBoolean('enableRawDuration')) {
            $this->SetValueInteger('DurationRaw', $Duration);
        }
        if ($Duration == 0) {
            $this->SetValueString('Duration', '');
            $this->SetValueInteger('Position2', 0);
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

    private function _SetNewSleepTimeout(int $Value)
    {
        $this->SetValueString('SleepTimeout', $this->ConvertSeconds($Value));
        if ($Value == 0) {
            $this->SetValueInteger('SleepTimer', 0);
        }
    }

    private function _SetNewSyncMaster(bool $isMaster)
    {
        if ($this->ReadPropertyBoolean('showSyncMaster')) {
            $this->SetValueBoolean('Master', $isMaster);
        }
    }

    private function _SetNewSyncMembers(string $PlayerMACs)
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

    private function _GetAllPlayers()
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

    private function _SetNewSyncProfil()
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

    private function _SetSeekable(bool $Value)
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

    private function _RefreshPlaylistIndex()
    {
        $this->SetCover();
        if (!$this->ReadPropertyBoolean('showPlaylist')) {
            return;
        }
        $Data = $this->Multi_Playlist;
        if (!is_array($Data)) {
            $Data = [];
        }
        $CurrentTrack = $this->GetValue('Index');
        $HTML = $this->GetTable($Data, 'SqueezeBoxPlaylist', 'Track', 'Position', $CurrentTrack);
        $this->SetValueString('Playlist', $HTML);
    }

    private function _RefreshPlaylist($Empty = false)
    {
        if (!$this->ReadPropertyBoolean('showPlaylist')) {
            $this->SetCover();
            return;
        }
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
                return false;
            }
            $this->Multi_Playlist = $PlaylistDataArray;
        }
        $this->_RefreshPlaylistIndex();
        return;
    }

    private function SetCover()
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
    private function DecodeLMSResponse(LMSData $LMSData)
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
                            $this->SetValueInteger('Position2', 0);
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
                        $this->SetValueInteger('Repeat', (int) $LMSData->Data[0]);
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
                            $this->SetValueInteger('Index', $this->GetValue('Index') + (int) $LMSData->Data[0]);
                        } else {
                            $this->SetValueInteger('Index', (int) $LMSData->Data[0] + 1);
                        }
                        break;
                    case 'newsong':
                        $this->SetValueString('Title', trim((string) $LMSData->Data[0]));
                        if (isset($LMSData->Data[1])) {
                            $this->SetValueInteger('Index', (int) $LMSData->Data[1] + 1);
                        } else {
                            $this->_RefreshPlaylist();
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
                $this->SetValueInteger('Signalstrength', (int) $LMSData->Data[0]);
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
                    $Data = new LMSTaggingData($TaggedDataLine);
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
                            $this->SetValueInteger('Signalstrength', (int) $Data->Value);
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
                            $this->SetValueInteger('SleepTimer', (int) $Data->Value);
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
                            $this->_SetNewVolume($Data->Value);
                            break;
                        case 'mixer bass':
                            if ($this->ReadPropertyBoolean('enableBass')) {
                                $this->_SetNewBass($Data->Value);
                            }
                            break;
                        case 'mixer treble':
                            if ($this->ReadPropertyBoolean('enableTreble')) {
                                $this->_SetNewTreble($Data->Value);
                            }
                            break;
                        case 'mixer pitch':
                            if ($this->ReadPropertyBoolean('enablePitch')) {
                                $this->_SetNewPitch($Data->Value);
                            }
                            break;
                        case 'playlist repeat':
                            $this->SetValueInteger('Repeat', (int) $Data->Value);
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
     * Konvertiert $Data zu einem JSONString und versendet diese an den Splitter.
     *
     * @param LMSData $LMSData Zu versendende Daten.
     *
     * @return LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function Send(LMSData $LMSData)
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

/* @} */
