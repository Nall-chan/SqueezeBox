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
require_once __DIR__ . '/../libs/ActionErrorHandler.php';  // Meldungen bei Bedienaktionen

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
 * @version       4.10
 *
 * @property int $ParentID
 * @property array $Multi_Playlist Alle Datensätze der Playlisten
 * @property int $PlayerMode
 * @property int $PlayerTrackIndex
 * @property int $PlayerShuffle
 * @property int $PlayerTracks
 * @property int $PositionRAW
 * @property bool $isSeekable
 * @property bool $isSubscribed
 * @property int $DurationRAW
 * @property string $SyncMaster
 * @property string $SyncMembers
 * @property string $WebHookSecretTrack
 * @property resource|bool $Socket
 * @method bool RegisterHook(string $WebHook)
 * @method bool UnregisterHook(string $WebHook)
 * @method void SetValueBoolean(string $Ident, bool $value)
 * @method void SetValueFloat(string $Ident, float $value)
 * @method void SetValueInteger(string $Ident, int $value)
 * @method void SetValueString(string $Ident, string $value)
 * @method void UnregisterProfile(string $Name)
 * @method int FindIDForIdent(string $Ident)
 * @method void RegisterParent()
 * @method bool IORequestAction(string $Ident, mixed $Value)
 * @method void IOMessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data)
 * @method int IORegisterParent()
 */
class Squeezebox extends IPSModuleStrict
{
    use \SqueezeBox\LMSSocket,
        \SqueezeBox\ActionErrorHandler,
        \SqueezeBox\LMSHTMLTable,
        \SqueezeBox\LMSSongUrl,
        \SqueezeBox\LMSCover,
        \SqueezeBox\DebugHelper,
        \SqueezeboxDevice\VariableHelper,
        \SqueezeboxDevice\VariableProfileHelper,
        \SqueezeboxDevice\BufferHelper,
        \SqueezeboxDevice\InstanceStatus {
            \SqueezeboxDevice\InstanceStatus::MessageSink as IOMessageSink;
            \SqueezeboxDevice\InstanceStatus::RequestAction as IORequestAction;
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
        $this->SetReceiveDataFilter('.*"Address":"".*');
        $this->RegisterTimer(\SqueezeBox\Device\Timer::SyncPresentation, 0, 'IPS_RequestAction($_IPS["TARGET"], "_SetNewSyncPresentation", true);');
        $this->RegisterPropertyString(\SqueezeBox\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\SqueezeBox\Device\Property::Interval, 2);
        $this->RegisterPropertyString(\SqueezeBox\Device\Property::CoverSize, 'cover');
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::EnableBass, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::EnableTreble, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::EnablePitch, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::EnableRandomPlay, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::EnableDurationText, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::EnablePositionText, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::EnablePreset, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::EnableSleepTimer, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::ShowSleepTimeout, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::ShowSyncMaster, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::ShowSyncControl, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::ShowSignalStrength, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::ShowTilePlaylist, true);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::ShowHTMLPlaylist, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::ShowTotalPlaytime, true);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString(\SqueezeBox\Device\Property::Table, json_encode($Style['Table']));
        $this->RegisterPropertyString(\SqueezeBox\Device\Property::Columns, json_encode($Style['Columns']));
        $this->RegisterPropertyString(\SqueezeBox\Device\Property::Rows, json_encode($Style['Rows']));
        $this->RegisterPropertyBoolean(\SqueezeBox\Device\Property::ChangeName, false);

        $this->ParentID = 0;
        $this->Multi_Playlist = [];
        $this->PlayerMode = 0;
        $this->PlayerTrackIndex = 0;
        $this->PlayerShuffle = 0;
        $this->PlayerTracks = 0;
        $this->isSeekable = false;
        $this->isSubscribed = false;
        $this->DurationRAW = 0;
        $this->SyncMaster = '';
        $this->SyncMembers = '';
        $this->WebHookSecretTrack = '';
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
        $Migrated = false;
        if (property_exists($Data->configuration, 'showPlaylist')) {
            $Migrated = true;
            $Data->configuration->showHTMLPlaylist = $Data->configuration->showPlaylist;
            $vid = $this->FindIDForIdent('Interpret');
            if ($vid > 0) { //Migrate Statusvariable Interpret to Artist
                @IPS_SetIdent($vid, 'Artist');
            }
            $vid = $this->FindIDForIdent('Playlist');
            if ($vid > 0) { //Migrate Statusvariable Playlist to HTMLPlaylist
                @IPS_SetIdent($vid, 'HTMLPlaylist');
            }
        }
        if (!property_exists($Data->configuration, 'enableDurationText')) {
            $Migrated = true;
            $Data->configuration->enableDurationText = true;
            $vid = $this->FindIDForIdent('Duration');
            if ($vid > 0) { //Migrate Statusvariable Duration to DurationOld
                @IPS_SetIdent($vid, 'DurationText');
            }
            $vid = $this->FindIDForIdent('DurationRaw');
            if ($vid > 0) { //Migrate DurationRaw Playlist to Duration
                @IPS_SetIdent($vid, 'Duration');
            }
        }
        if (!property_exists($Data->configuration, 'enablePositionText')) {
            $Migrated = true;
            $Data->configuration->enablePositionText = true;
            $vid = $this->FindIDForIdent('Position');
            if ($vid > 0) { //Migrate Statusvariable Position to PositionText
                @IPS_SetIdent($vid, 'PositionText');
            }
            $vid = $this->FindIDForIdent('PositionRaw');
            if ($vid > 0) { //Migrate PositionRaw Playlist to Position
                @IPS_SetIdent($vid, 'Position');
            }
        }
        // Bis 4.05 war SleepTimeout ein String. Nur diese alte Variable wird beiseitegelegt (Changelog 4.10), die neue Integer-Variable bleibt unverändert.
        $vid = $this->FindIDForIdent('SleepTimeout');
        if (($vid > 0) && (IPS_GetVariable($vid)['VariableType'] != VARIABLETYPE_INTEGER)) {
            $Migrated = true;
            @IPS_SetIdent($vid, 'SleepTimeoutOld');
            @IPS_SetName($vid, 'SleepTimeout (old)');
        }
        if ($Migrated) {
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
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }
        $this->SetReceiveDataFilter('.*"Address":"".*');
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        $this->RegisterMessage($this->InstanceID, IM_CHANGESTATUS);
        $this->ParentID = 0;
        $this->Multi_Playlist = [];
        $this->PlayerMode = 0;
        $this->PlayerTrackIndex = 0;
        $this->PlayerShuffle = 0;
        $this->PlayerTracks = 0;
        $this->isSeekable = false;
        $this->isSubscribed = false;
        $this->DurationRAW = 0;
        $this->SyncMaster = '';
        $this->SyncMembers = '';
        $this->WebHookSecretTrack = '';
        parent::ApplyChanges();
        $Address = $this->ReadPropertyString(\SqueezeBox\Device\Property::Address);
        // Adresse als Filter setzen
        $this->SetReceiveDataFilter('.*"Address":"' . $Address . '".*');
        $this->SetSummary($Address);
        if (preg_match('/\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b/', $Address) !== 1) {
            $this->RegisterVariableBoolean(
                \SqueezeBox\Device\Variables::Power,
                'Power',
                [
                    \SqueezeBox\Presentation::Type            => VARIABLE_PRESENTATION_SWITCH,
                    \SqueezeBox\Presentation\Switchable::Type => 0
                ],
                1
            );
            $this->EnableAction(\SqueezeBox\Device\Variables::Power);
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePreset)) {
                $this->RegisterVariableInteger(
                    \SqueezeBox\Device\Variables::Preset,
                    'Preset',
                    [
                        \SqueezeBox\Presentation::Icon         => 'bookmark',
                        \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_ENUMERATION,
                        \SqueezeBox\Presentation\Enum::Layout  => 2,
                        \SqueezeBox\Presentation\Enum::Options => json_encode(
                            [
                                [
                                    \SqueezeBox\Presentation\Enum::Value      => 1,
                                    \SqueezeBox\Presentation\Enum::Caption    => '1',
                                    \SqueezeBox\Presentation\Enum::IconActive => false,
                                    \SqueezeBox\Presentation\Enum::Icon       => '',
                                    \SqueezeBox\Presentation\Enum::Color      => -1,
                                ],
                                [
                                    \SqueezeBox\Presentation\Enum::Value      => 2,
                                    \SqueezeBox\Presentation\Enum::Caption    => '2',
                                    \SqueezeBox\Presentation\Enum::IconActive => false,
                                    \SqueezeBox\Presentation\Enum::Icon       => '',
                                    \SqueezeBox\Presentation\Enum::Color      => -1,
                                ],                                [
                                    \SqueezeBox\Presentation\Enum::Value      => 3,
                                    \SqueezeBox\Presentation\Enum::Caption    => '3',
                                    \SqueezeBox\Presentation\Enum::IconActive => false,
                                    \SqueezeBox\Presentation\Enum::Icon       => '',
                                    \SqueezeBox\Presentation\Enum::Color      => -1,
                                ],                                [
                                    \SqueezeBox\Presentation\Enum::Value      => 4,
                                    \SqueezeBox\Presentation\Enum::Caption    => '4',
                                    \SqueezeBox\Presentation\Enum::IconActive => false,
                                    \SqueezeBox\Presentation\Enum::Icon       => '',
                                    \SqueezeBox\Presentation\Enum::Color      => -1,
                                ],                                [
                                    \SqueezeBox\Presentation\Enum::Value      => 5,
                                    \SqueezeBox\Presentation\Enum::Caption    => '5',
                                    \SqueezeBox\Presentation\Enum::IconActive => false,
                                    \SqueezeBox\Presentation\Enum::Icon       => '',
                                    \SqueezeBox\Presentation\Enum::Color      => -1,
                                ],                                [
                                    \SqueezeBox\Presentation\Enum::Value      => 6,
                                    \SqueezeBox\Presentation\Enum::Caption    => '6',
                                    \SqueezeBox\Presentation\Enum::IconActive => false,
                                    \SqueezeBox\Presentation\Enum::Icon       => '',
                                    \SqueezeBox\Presentation\Enum::Color      => -1,
                                ],                                [
                                    \SqueezeBox\Presentation\Enum::Value      => 7,
                                    \SqueezeBox\Presentation\Enum::Caption    => '7',
                                    \SqueezeBox\Presentation\Enum::IconActive => false,
                                    \SqueezeBox\Presentation\Enum::Icon       => '',
                                    \SqueezeBox\Presentation\Enum::Color      => -1,
                                ],                                [
                                    \SqueezeBox\Presentation\Enum::Value      => 8,
                                    \SqueezeBox\Presentation\Enum::Caption    => '8',
                                    \SqueezeBox\Presentation\Enum::IconActive => false,
                                    \SqueezeBox\Presentation\Enum::Icon       => '',
                                    \SqueezeBox\Presentation\Enum::Color      => -1,
                                ],                                [
                                    \SqueezeBox\Presentation\Enum::Value      => 9,
                                    \SqueezeBox\Presentation\Enum::Caption    => '9',
                                    \SqueezeBox\Presentation\Enum::IconActive => false,
                                    \SqueezeBox\Presentation\Enum::Icon       => '',
                                    \SqueezeBox\Presentation\Enum::Color      => -1,
                                ],                                [
                                    \SqueezeBox\Presentation\Enum::Value      => 10,
                                    \SqueezeBox\Presentation\Enum::Caption    => '10',
                                    \SqueezeBox\Presentation\Enum::IconActive => false,
                                    \SqueezeBox\Presentation\Enum::Icon       => '',
                                    \SqueezeBox\Presentation\Enum::Color      => -1,
                                ]

                            ]
                        )
                    ],
                    2
                );
                $this->EnableAction(\SqueezeBox\Device\Variables::Preset);
            } else {
                $this->UnregisterVariable(\SqueezeBox\Device\Variables::Preset);
            }
            $this->RegisterVariableBoolean(
                \SqueezeBox\Device\Variables::Mute,
                'Mute',
                [
                    \SqueezeBox\Presentation::Type                     => VARIABLE_PRESENTATION_SWITCH,
                    \SqueezeBox\Presentation\Switchable::Type          => 1,
                    \SqueezeBox\Presentation\Switchable::IconFalseUsed => false,
                    \SqueezeBox\Presentation\Switchable::IconFalse     => 'volume-xmark',
                    \SqueezeBox\Presentation\Switchable::IconTrue      => 'volume-xmark',
                ],
                4
            );
            $this->EnableAction(\SqueezeBox\Device\Variables::Mute);
            $this->RegisterVariableInteger(
                \SqueezeBox\Device\Variables::Volume,
                'Volume',
                [
                    \SqueezeBox\Presentation::Type                 => VARIABLE_PRESENTATION_SLIDER,
                    \SqueezeBox\Presentation::Icon                 => 'Speaker',
                    \SqueezeBox\Presentation\Slider::Min           => 0,
                    \SqueezeBox\Presentation\Slider::Max           => 100,
                    \SqueezeBox\Presentation\Slider::Step          => 1,
                    \SqueezeBox\Presentation\Slider::Digits        => 0,
                    \SqueezeBox\Presentation\Slider::Type          => 3,
                    \SqueezeBox\Presentation\Slider::Percentage    => true,
                    \SqueezeBox\Presentation\Slider::Prefix        => '',
                    \SqueezeBox\Presentation\Slider::Suffix        => ' %',
                    \SqueezeBox\Presentation\Slider::IntervalsUsed => false,
                    \SqueezeBox\Presentation\Slider::Intervals     => '[]',
                    \SqueezeBox\Presentation\Slider::GradientType  => 0,
                    \SqueezeBox\Presentation\Slider::Gradient      => '[]'
                ],
                5
            );
            $this->EnableAction(\SqueezeBox\Device\Variables::Volume);
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableBass)) {
                $this->RegisterVariableInteger(
                    \SqueezeBox\Device\Variables::Bass,
                    'Bass',
                    [
                        \SqueezeBox\Presentation::Type                 => VARIABLE_PRESENTATION_SLIDER,
                        \SqueezeBox\Presentation::Icon                 => 'signal-bars',
                        \SqueezeBox\Presentation\Slider::Min           => 0,
                        \SqueezeBox\Presentation\Slider::Max           => 100,
                        \SqueezeBox\Presentation\Slider::Step          => 1,
                        \SqueezeBox\Presentation\Slider::Digits        => 0,
                        \SqueezeBox\Presentation\Slider::Type          => 5,
                        \SqueezeBox\Presentation\Slider::Percentage    => true,
                        \SqueezeBox\Presentation\Slider::Prefix        => '',
                        \SqueezeBox\Presentation\Slider::Suffix        => ' %',
                        \SqueezeBox\Presentation\Slider::IntervalsUsed => false,
                        \SqueezeBox\Presentation\Slider::Intervals     => '[]',
                        \SqueezeBox\Presentation\Slider::GradientType  => 0,
                        \SqueezeBox\Presentation\Slider::Gradient      => '[]'
                    ],
                    6
                );
                $this->EnableAction(\SqueezeBox\Device\Variables::Bass);
            } else {
                $this->UnregisterVariable(\SqueezeBox\Device\Variables::Bass);
            }
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableTreble)) {
                $this->RegisterVariableInteger(
                    \SqueezeBox\Device\Variables::Treble,
                    $this->Translate('Treble'),
                    [
                        \SqueezeBox\Presentation::Type                 => VARIABLE_PRESENTATION_SLIDER,
                        \SqueezeBox\Presentation::Icon                 => 'signal-bars',
                        \SqueezeBox\Presentation\Slider::Min           => 0,
                        \SqueezeBox\Presentation\Slider::Max           => 100,
                        \SqueezeBox\Presentation\Slider::Step          => 1,
                        \SqueezeBox\Presentation\Slider::Digits        => 0,
                        \SqueezeBox\Presentation\Slider::Type          => 5,
                        \SqueezeBox\Presentation\Slider::Percentage    => true,
                        \SqueezeBox\Presentation\Slider::Prefix        => '',
                        \SqueezeBox\Presentation\Slider::Suffix        => ' %',
                        \SqueezeBox\Presentation\Slider::IntervalsUsed => false,
                        \SqueezeBox\Presentation\Slider::Intervals     => '[]',
                        \SqueezeBox\Presentation\Slider::GradientType  => 0,
                        \SqueezeBox\Presentation\Slider::Gradient      => '[]'
                    ],
                    7
                );
                $this->EnableAction(\SqueezeBox\Device\Variables::Treble);
            } else {
                $this->UnregisterVariable(\SqueezeBox\Device\Variables::Treble);
            }
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePitch)) {
                $this->RegisterVariableInteger(
                    \SqueezeBox\Device\Variables::Pitch,
                    $this->Translate('Pitch'),
                    [
                        \SqueezeBox\Presentation::Type                 => VARIABLE_PRESENTATION_SLIDER,
                        \SqueezeBox\Presentation::Icon                 => 'signal-bars',
                        \SqueezeBox\Presentation\Slider::Min           => 80,
                        \SqueezeBox\Presentation\Slider::Max           => 120,
                        \SqueezeBox\Presentation\Slider::Step          => 1,
                        \SqueezeBox\Presentation\Slider::Digits        => 0,
                        \SqueezeBox\Presentation\Slider::Type          => 5,
                        \SqueezeBox\Presentation\Slider::Percentage    => false,
                        \SqueezeBox\Presentation\Slider::Prefix        => '',
                        \SqueezeBox\Presentation\Slider::Suffix        => ' %',
                        \SqueezeBox\Presentation\Slider::IntervalsUsed => false,
                        \SqueezeBox\Presentation\Slider::Intervals     => '[]',
                        \SqueezeBox\Presentation\Slider::GradientType  => 0,
                        \SqueezeBox\Presentation\Slider::Gradient      => '[]'
                    ],
                    8
                );
                $this->EnableAction(\SqueezeBox\Device\Variables::Pitch);
            } else {
                $this->UnregisterVariable(\SqueezeBox\Device\Variables::Pitch);
            }
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSyncMaster)) {
                $this->RegisterVariableBoolean(
                    \SqueezeBox\Device\Variables::Master,
                    $this->Translate('Master'),
                    [],
                    14
                );
            } else {
                $this->UnregisterVariable(\SqueezeBox\Device\Variables::Master);
            }
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSyncControl)) {
                $this->_SetNewSyncPresentation();
                $this->EnableAction(\SqueezeBox\Device\Variables::Sync);
            } else {
                $this->UnregisterVariable(\SqueezeBox\Device\Variables::Sync);
            }
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSignalStrength)) {
                $this->RegisterVariableInteger(
                    \SqueezeBox\Device\Variables::Signalstrength,
                    $this->Translate('Signal strength'),
                    [
                        \SqueezeBox\Presentation::Icon                => 'signal-bars',
                        \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        \SqueezeBox\Presentation\Value::Min           => 0,
                        \SqueezeBox\Presentation\Value::Max           => 100,
                        \SqueezeBox\Presentation\Value::Digits        => 0,
                        \SqueezeBox\Presentation\Value::Prefix        => '',
                        \SqueezeBox\Presentation\Value::Suffix        => ' %',
                        \SqueezeBox\Presentation\Value::IntervalsUsed => false
                    ],
                    31
                );
            } else {
                $this->UnregisterVariable(\SqueezeBox\Device\Variables::Signalstrength);
            }
        } else {
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Power);
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Preset);
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Mute);
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Volume);
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Bass);
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Treble);
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Pitch);
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Signalstrength);
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Master);
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Sync);
        }
        // @todo @Symcon muss noch Profil bleiben bis Symcon eine Darstellung dafür hat
        $this->RegisterVariableInteger(
            \SqueezeBox\Device\Variables::Status,
            $this->Translate('State'),
            '~PlaybackPreviousNext',
            3
        );
        $this->PlayerMode = $this->FindIDForIdent(\SqueezeBox\Device\Variables::Status);
        $this->RegisterMessage($this->PlayerMode, VM_UPDATE);
        $this->EnableAction(\SqueezeBox\Device\Variables::Status);
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableRandomPlay)) {
            $this->RegisterVariableInteger(
                \SqueezeBox\Device\Variables::Randomplay,
                $this->Translate('Randomplay'),
                [
                    \SqueezeBox\Presentation::Icon         => 'Shuffle',
                    \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_ENUMERATION,
                    \SqueezeBox\Presentation\Enum::Options => json_encode([
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 0,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Off'),
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => '',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 1,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Track'),
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => '',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 2,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Album'),
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => '',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 3,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Artist'),
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => '',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 4,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Year'),
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => '',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ]
                    ])
                ],
                13
            );
            $this->EnableAction(\SqueezeBox\Device\Variables::Randomplay);
        } else {
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::Randomplay);
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableDurationText)) {
            $this->RegisterVariableString(
                \SqueezeBox\Device\Variables::DurationText,
                $this->Translate('Duration as text'),
                [],
                24
            );
        } else {
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::DurationText);
        }
        $this->RegisterVariableInteger(
            \SqueezeBox\Device\Variables::Duration,
            $this->Translate('Duration'),
            [
                \SqueezeBox\Presentation::Type => VARIABLE_PRESENTATION_DURATION
            ],
            28
        );
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePositionText)) {
            $this->RegisterVariableString(
                \SqueezeBox\Device\Variables::PositionText,
                $this->Translate('Position as Text'),
                [],
                25
            );
        } else {
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::PositionText);
        }
        $this->RegisterVariableInteger(
            \SqueezeBox\Device\Variables::Position,
            $this->Translate('Position'),
            [
                \SqueezeBox\Presentation::Type => VARIABLE_PRESENTATION_DURATION
            ],
            29
        );
        $this->RegisterVariableInteger(
            \SqueezeBox\Device\Variables::Shuffle,
            $this->Translate('Shuffle'),
            [
                \SqueezeBox\Presentation::Icon         => 'Shuffle',
                \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_ENUMERATION,
                \SqueezeBox\Presentation\Enum::Layout  => 0,
                \SqueezeBox\Presentation\Enum::Options => json_encode(
                    [
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 0,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Off'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'xmark',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 1,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Title'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'list-music',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 2,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Album'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'album',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ]
                    ]
                )
            ],
            9
        );
        $this->PlayerShuffle = $this->FindIDForIdent(\SqueezeBox\Device\Variables::Shuffle);
        $this->EnableAction(\SqueezeBox\Device\Variables::Shuffle);
        $this->RegisterMessage($this->PlayerShuffle, VM_UPDATE);
        // @todo @Symcon muss noch Profil bleiben bis Symcon eine Darstellung dafür hat
        $this->RegisterVariableInteger(
            \SqueezeBox\Device\Variables::Repeat,
            $this->Translate('Repeat'),
            '~Repeat',
            /*[
                \SqueezeBox\Presentation::Icon         => 'Repeat',
                \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_ENUMERATION,
                \SqueezeBox\Presentation\Enum::Layout  => 0,
                \SqueezeBox\Presentation\Enum::Options => json_encode(
                    [
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 0,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Off'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'xmark',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 1,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Playlist'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'list-music',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 2,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Title'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'file-music',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ]
                    ]
                )
            ],*/
            10
        );
        $this->EnableAction(\SqueezeBox\Device\Variables::Repeat);
        $this->RegisterVariableInteger(
            \SqueezeBox\Device\Variables::Tracks,
            $this->Translate('Tracks in Playlist'),
            [
                \SqueezeBox\Presentation::Icon                => 'list-music',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Digits        => 0,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => ' #',
                \SqueezeBox\Presentation\Value::IntervalsUsed => false
            ],
            11
        );
        $this->PlayerTracks = $this->FindIDForIdent(\SqueezeBox\Device\Variables::Tracks);
        $this->RegisterMessage($this->PlayerTracks, VM_UPDATE);
        $this->_SetNewTrackPresentation($this->GetValue(\SqueezeBox\Device\Variables::Tracks));
        $this->PlayerTrackIndex = $this->FindIDForIdent(\SqueezeBox\Device\Variables::Index);
        // Die Aktion von Index setzt _SetNewTrackPresentation (nur bei mehr als einem Titel).
        $this->RegisterMessage($this->PlayerTrackIndex, VM_UPDATE);
        $this->RegisterVariableString(
            \SqueezeBox\Device\Variables::StationName,
            $this->Translate('Station name'),
            [
                \SqueezeBox\Presentation::Type => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation::Icon => 'radio'
            ],
            18
        );
        $this->RegisterVariableString(
            \SqueezeBox\Device\Variables::Playlistname,
            'Name of Playlist',
            [],
            19
        );
        $this->RegisterVariableString(
            \SqueezeBox\Device\Variables::Album,
            'Album',
            [],
            20
        );
        // @todo @Symcon muss noch Profil bleiben bis Symcon eine Darstellung dafür hat
        $this->RegisterVariableString(
            \SqueezeBox\Device\Variables::Title,
            $this->Translate('Title'),
            '~Song',
            21
        );
        // @todo @Symcon muss noch Profil bleiben bis Symcon eine Darstellung dafür hat
        $this->RegisterVariableString(
            \SqueezeBox\Device\Variables::Artist,
            $this->Translate('Artist'),
            '~Artist',
            22
        );
        // @todo  @Symcon Kommt da noch was?
        $this->RegisterVariableString(
            \SqueezeBox\Device\Variables::Genre,
            $this->Translate('Genre'),
            [],
            23
        );
        $this->RegisterVariableFloat(
            \SqueezeBox\Device\Variables::Position2,
            'Position',
            // @todo @Symcon Bringt die Media-Kachel zum Absturz.
            // Und geht mit Dynamischen Variablen-Aktionen (wenn Stream nicht seekable ist) auch nicht.
            // Lösung?
            /*[
               \SqueezeBox\Presentation::Type                 => VARIABLE_PRESENTATION_SLIDER,
               \SqueezeBox\Presentation::Icon                 => 'bars-progress',
               \SqueezeBox\Presentation\Slider::Min           => 0,
               \SqueezeBox\Presentation\Slider::Max           => 100,
               \SqueezeBox\Presentation\Slider::Step          => 0.1,
               \SqueezeBox\Presentation\Slider::Digits        => 1,
               \SqueezeBox\Presentation\Slider::Type          => 5,
               \SqueezeBox\Presentation\Slider::Percentage    => true,
               \SqueezeBox\Presentation\Slider::Prefix        => '',
               \SqueezeBox\Presentation\Slider::Suffix        => $this->Translate(' %'),
               \SqueezeBox\Presentation\Slider::IntervalsUsed => false,
               \SqueezeBox\Presentation\Slider::Intervals     => '[]',
               \SqueezeBox\Presentation\Slider::GradientType  => 0,
               \SqueezeBox\Presentation\Slider::Gradient      => '[]',
        ]*/
            '~Progress',
            26
        );
        $this->DisableAction(\SqueezeBox\Device\Variables::Position2);
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableSleepTimer)) {
            $this->RegisterVariableInteger(
                \SqueezeBox\Device\Variables::SleepTimer,
                $this->Translate('Sleep timer'),
                [
                    \SqueezeBox\Presentation::Type                 => VARIABLE_PRESENTATION_SLIDER,
                    \SqueezeBox\Presentation::Icon                 => 'timer',
                    \SqueezeBox\Presentation\Slider::Min           => 0,
                    \SqueezeBox\Presentation\Slider::Max           => 5400,
                    \SqueezeBox\Presentation\Slider::Step          => 60,
                    \SqueezeBox\Presentation\Slider::Digits        => 0,
                    \SqueezeBox\Presentation\Slider::Type          => 5,
                    \SqueezeBox\Presentation\Slider::Percentage    => false,
                    \SqueezeBox\Presentation\Slider::Prefix        => '',
                    \SqueezeBox\Presentation\Slider::Suffix        => $this->Translate(' sec'),
                    \SqueezeBox\Presentation\Slider::IntervalsUsed => true,
                    \SqueezeBox\Presentation\Slider::Intervals     => json_encode(
                        [
                            [
                                \SqueezeBox\Presentation\Slider::IntervalMinValue => 0,
                                \SqueezeBox\Presentation\Slider::IntervalMaxValue => 5400,
                                \SqueezeBox\Presentation\Slider::ConstantActive   => false,
                                \SqueezeBox\Presentation\Slider::ConstantValue    => '',
                                \SqueezeBox\Presentation\Slider::ConversionFactor => 60,
                                \SqueezeBox\Presentation\Slider::PrefixActive     => false,
                                \SqueezeBox\Presentation\Slider::PrefixValue      => '',
                                \SqueezeBox\Presentation\Slider::SuffixActive     => true,
                                \SqueezeBox\Presentation\Slider::SuffixValue      => $this->Translate(' min'),
                                \SqueezeBox\Presentation\Slider::DigitsActive     => false,
                                \SqueezeBox\Presentation\Slider::DigitsValue      => 0,
                                \SqueezeBox\Presentation\Slider::IconActive       => false,
                                \SqueezeBox\Presentation\Slider::IconValue        => '',
                            ]
                        ]
                    ),
                    \SqueezeBox\Presentation\Slider::GradientType  => 0,
                    \SqueezeBox\Presentation\Slider::Gradient      => '[]'
                ],
                32
            );
            $this->EnableAction(\SqueezeBox\Device\Variables::SleepTimer);
        } else {
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::SleepTimer);
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSleepTimeout)) {
            $this->RegisterVariableInteger(
                \SqueezeBox\Device\Variables::SleepTimeout,
                $this->Translate('Switch off in'),
                [
                    \SqueezeBox\Presentation::Icon => 'face-sleeping', // Warum kein Icon bei Dauer?
                    \SqueezeBox\Presentation::Type => VARIABLE_PRESENTATION_DURATION,
                ],
                33
            );
        } else {
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::SleepTimeout);
        }

        // @todo @Symcon Tile-Playlist muss noch Profil bleiben bis Symcon eine Darstellung dafür hat
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowTilePlaylist)) {
            $this->RegisterVariableString(
                \SqueezeBox\Device\Variables::TilePlaylist,
                'Playlist',
                '~Playlist',
                34
            );
            $this->EnableAction(\SqueezeBox\Device\Variables::TilePlaylist);
        } else {
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::TilePlaylist);
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowHTMLPlaylist)) {
            $this->RegisterVariableString(
                \SqueezeBox\Device\Variables::HTMLPlaylist,
                'Playlist',
                [
                    \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_WEB_CONTENT,
                    \SqueezeBox\Presentation\HTML::Type    => 0,
                    \SqueezeBox\Presentation\HTML::Padding => true
                ],
                30
            );
        } else {
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::HTMLPlaylist);
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowTotalPlaytime)) {
            $this->RegisterVariableInteger(
                \SqueezeBox\Device\Variables::TotalRuntime,
                $this->Translate('Runtime'),
                [
                    \SqueezeBox\Presentation::Type            => VARIABLE_PRESENTATION_DURATION,
                    \SqueezeBox\Presentation\Duration::Format => 2
                ],
                35
            );
        } else {
            $this->UnregisterVariable(\SqueezeBox\Device\Variables::TotalRuntime);
        }

        // Alte Profile löschen
        $this->UnregisterProfile('LSQ.Status');
        $this->UnregisterProfile('LSQ.Volume');
        $this->UnregisterProfile('LSQ.Intensity');
        $this->UnregisterProfile('LSQ.Repeat');
        $this->UnregisterProfile('LSQ.Preset');
        $this->UnregisterProfile('LSQ.Pitch');
        $this->UnregisterProfile('LSQ.Sync.' . $this->InstanceID);
        $this->UnregisterProfile('LSQ.Tracklist.' . $this->InstanceID);
        $this->UnregisterProfile('LSQ.Shuffle');
        $this->UnregisterProfile('LSQ.SleepTimer');

        // Wenn Kernel nicht bereit, dann warten... wenn unser IO Aktiv wird, holen wir unsere Daten :)
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        // Playlist
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowHTMLPlaylist)) {
            $this->RegisterHook('SqueezeBoxPlaylist' . $this->InstanceID);
        } else {
            $this->UnregisterHook('SqueezeBoxPlaylist' . $this->InstanceID);
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
                if (($this->ReadPropertyString(\SqueezeBox\Device\Property::Address) == '') || ($this->GetStatus() != IS_ACTIVE)) {
                    return;
                }
                switch ($SenderID) {
                    case $this->PlayerMode:
                        if ($Data[0] == 2) {
                            $this->_StartSubscribe();
                        } else {
                            $this->_StopSubscribe();
                        }
                        break;
                    case $this->PlayerShuffle:
                        $this->_RefreshPlaylist();
                        break;
                    case $this->PlayerTrackIndex:
                        // VM_UPDATE kommt bei jedem Schreiben; Playlist-Markierung, Cover und Titeldaten nur bei neuem Index.
                        if (!$Data[1]) {
                            break;
                        }
                        $this->_RefreshPlaylistIndex();
                        $this->SetCover();
                        $this->RequestState(\SqueezeBox\Device\Variables::Album);
                        $this->RequestState(\SqueezeBox\Device\Variables::Title);
                        $this->RequestState(\SqueezeBox\Device\Variables::Artist);
                        $this->RequestState(\SqueezeBox\Device\Variables::Genre);
                        $this->RequestState(\SqueezeBox\Device\Variables::Duration);
                        break;
                    case $this->PlayerTracks:
                        $this->_SetNewTrackPresentation($Data[0]);
                        $this->_RefreshPlaylist($Data[0] == 0);
                        break;
                }
                break;
        }
    }

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
        $this->RequestState(\SqueezeBox\Device\Variables::Power);
        $this->RequestState(\SqueezeBox\Device\Variables::Status);
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSyncControl)) {
            $this->RequestState(\SqueezeBox\Device\Variables::Sync);
        }
        $this->RequestState('Remote');
        $this->RequestState(\SqueezeBox\Device\Variables::Mute);
        $this->RequestState(\SqueezeBox\Device\Variables::Volume);
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableBass)) {
            $this->RequestState(\SqueezeBox\Device\Variables::Bass);
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableTreble)) {
            $this->RequestState(\SqueezeBox\Device\Variables::Treble);
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePitch)) {
            $this->RequestState(\SqueezeBox\Device\Variables::Pitch);
        }
        $this->RequestState(\SqueezeBox\Device\Variables::Shuffle);
        $this->RequestState(\SqueezeBox\Device\Variables::Repeat);
        $this->RequestState(\SqueezeBox\Device\Variables::Tracks);
        $this->RequestState(\SqueezeBox\Device\Variables::Index);
        $this->RequestState(\SqueezeBox\Device\Variables::Playlistname);
        $this->RequestState(\SqueezeBox\Device\Variables::Album);
        $this->RequestState(\SqueezeBox\Device\Variables::Title);
        $this->RequestState(\SqueezeBox\Device\Variables::Artist);
        $this->RequestState(\SqueezeBox\Device\Variables::Genre);
        $this->RequestState(\SqueezeBox\Device\Variables::Duration);
        $this->RequestState(\SqueezeBox\Device\Variables::Position);
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSignalStrength)) {
            $this->RequestState(\SqueezeBox\Device\Variables::Signalstrength);
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSleepTimeout)) {
            $this->RequestState(\SqueezeBox\Device\Variables::SleepTimeout);
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableRandomPlay)) {
            $this->RequestState(\SqueezeBox\Device\Variables::Randomplay);
        }
        $this->SetCover();
        $this->_RefreshPlaylist();
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['status', '-', 1], ['tags:gladiqrRtueJINpsy', 'subscribe:0']));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $this->DecodeLMSResponse($LMSData);
        if ($this->GetValue(\SqueezeBox\Device\Variables::Status) == 2) {
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
            case \SqueezeBox\Device\Variables::Power:
                $LMSResponse = new \SqueezeBox\LMSData('power', '?');
                break;
            case \SqueezeBox\Device\Variables::Status:
                if (!$this->_isPlayerOn()) {
                    $this->_SetModeToStop();
                    return true;
                }
                $LMSResponse = new \SqueezeBox\LMSData('mode', '?');
                break;
            case \SqueezeBox\Device\Variables::Mute:
                $LMSResponse = new \SqueezeBox\LMSData(['mixer', 'muting'], '?');
                break;
            case \SqueezeBox\Device\Variables::Volume:
                $LMSResponse = new \SqueezeBox\LMSData(['mixer', 'volume'], '?');
                break;
            case \SqueezeBox\Device\Variables::Bass:
                if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableBass)) {
                    trigger_error($this->Translate('Invalid ident'), E_USER_NOTICE);
                    return false;
                }
                $LMSResponse = new \SqueezeBox\LMSData(['mixer', 'bass'], '?');
                break;
            case \SqueezeBox\Device\Variables::Treble:
                if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableTreble)) {
                    trigger_error($this->Translate('Invalid ident'), E_USER_NOTICE);
                    return false;
                }
                $LMSResponse = new \SqueezeBox\LMSData(['mixer', 'treble'], '?');
                break;
            case \SqueezeBox\Device\Variables::Pitch:
                if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePitch)) {
                    trigger_error($this->Translate('Invalid ident'), E_USER_NOTICE);
                    return false;
                }
                $LMSResponse = new \SqueezeBox\LMSData(['mixer', 'pitch'], '?');
                break;
            case \SqueezeBox\Device\Variables::Randomplay:
                if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableRandomPlay)) {
                    trigger_error($this->Translate('Invalid ident'), E_USER_NOTICE);
                    return false;
                }
                $LMSResponse = new \SqueezeBox\LMSData('randomplayisactive', '');
                break;
            case \SqueezeBox\Device\Variables::Sync:
                if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSyncControl)) {
                    trigger_error($this->Translate('Invalid ident'), E_USER_NOTICE);
                    return false;
                }
                $LMSResponse = new \SqueezeBox\LMSData('sync', '?');
                break;
            case \SqueezeBox\Device\Variables::Shuffle:
                $LMSResponse = new \SqueezeBox\LMSData(['playlist', 'shuffle'], '?');
                break;
            case \SqueezeBox\Device\Variables::Repeat:
                $LMSResponse = new \SqueezeBox\LMSData(['playlist', 'repeat'], '?');
                break;
            case \SqueezeBox\Device\Variables::Tracks:
                $LMSResponse = new \SqueezeBox\LMSData(['playlist', 'tracks'], '?');
                break;
            case \SqueezeBox\Device\Variables::Index:
                $LMSResponse = new \SqueezeBox\LMSData(['playlist', 'index'], '?');
                break;
            case \SqueezeBox\Device\Variables::Playlistname:
                $LMSResponse = new \SqueezeBox\LMSData(['playlist', 'name'], '?');
                break;
            case \SqueezeBox\Device\Variables::Album:
                $LMSResponse = new \SqueezeBox\LMSData('album', '?');
                break;
            case \SqueezeBox\Device\Variables::Title:
                $LMSResponse = new \SqueezeBox\LMSData('title', '?');
                break;
            case \SqueezeBox\Device\Variables::Artist:
                $LMSResponse = new \SqueezeBox\LMSData('artist', '?');
                break;
            case \SqueezeBox\Device\Variables::Genre:
                $LMSResponse = new \SqueezeBox\LMSData('genre', '?');
                break;
            case \SqueezeBox\Device\Variables::Duration:
                $LMSResponse = new \SqueezeBox\LMSData('duration', '?');
                break;
            case \SqueezeBox\Device\Variables::Position2:
            case \SqueezeBox\Device\Variables::Position:
                $LMSResponse = new \SqueezeBox\LMSData('time', '?');
                break;
            case \SqueezeBox\Device\Variables::Signalstrength:
                $LMSResponse = new \SqueezeBox\LMSData('signalstrength', '?');
                break;
            case \SqueezeBox\Device\Variables::SleepTimeout:
                $LMSResponse = new \SqueezeBox\LMSData('sleep', '?');
                break;
            case 'Connected':
                $LMSResponse = new \SqueezeBox\LMSData('connected', '?');
                break;
            case 'Remote':
                $LMSResponse = new \SqueezeBox\LMSData('remote', '?');
                break;
            default:
                trigger_error($this->Translate('Invalid ident'), E_USER_NOTICE);
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
            trigger_error($this->Translate('Unknown LSQ_PlayerInstanz'), E_USER_NOTICE);
            return false;
        }
        if ($id['ModuleInfo']['ModuleID'] != \SqueezeBox\GUID::Squeezebox) {
            trigger_error($this->Translate('SlaveInstance in not a LSQ_PlayerInstanz'), E_USER_NOTICE);
            return false;
        }
        if ($id['ConnectionID'] != $this->ParentID) {
            trigger_error($this->Translate('Player is not connected to this LMS.'), E_USER_NOTICE);
            return false;
        }
        $ClientMac = IPS_GetProperty($SlaveInstanceID, \SqueezeBox\Device\Property::Address);
        if (($ClientMac === '') || ($ClientMac === false)) {
            trigger_error($this->Translate('SlaveInstance Address is not set.'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('sync', $ClientMac));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == $ClientMac;
    }

    /**
     * GetSync
     *
     * IPS-Instanz-Funktion 'LSQ_GetSync'.
     * Gibt alle mit diesem Gerät synchronisierte Instanzen zurück.
     * Player ohne Instanz in Symcon werden nicht aufgeführt.
     * @return bool|array Array mit den InstanzIDs, false im Fehlerfall.
     */
    public function GetSync(): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('sync', '?'));
        if ($LMSData === null) {
            return false;
        }
        $FoundInstanzIDs = [];
        $ret = $LMSData->Data[0];
        if (($ret != '-') && ($ret != '')) {
            $Addresses = $this->_GetAllPlayers();
            foreach (explode(',', $ret) as $Value) {
                $InstanceID = array_search($Value, $Addresses);
                if ($InstanceID !== false) {
                    $FoundInstanzIDs[] = $InstanceID;
                }
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
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('sync', '-'));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Data[0] == '-';
    }

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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
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
     * @return bool|string Name vom Device
     */
    public function GetName(): bool|string
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
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('power', $Value ? '1' : '0'));
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
            trigger_error($this->Translate('Seconds invalid.'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('sleep', (string) $Seconds));
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
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'muting'], $Value ? '1' : '0'));
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
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'volume'], (string) $Value));
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
        if (!in_array(substr($Value, 0, 1), ['-', '+'])) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            return false;
        }
        if (((int) $Value < -100) || ((int) $Value > 100)) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
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
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableBass)) {
            trigger_error($this->Translate('bass control not enabled'), E_USER_NOTICE);
            return false;
        }
        if (($Value < 0) || ($Value > 100)) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'bass'], (string) $Value));
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
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableBass)) {
            trigger_error($this->Translate('bass control not enabled'), E_USER_NOTICE);
            return false;
        }
        if (!in_array(substr($Value, 0, 1), ['-', '+'])) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            return false;
        }
        if (((int) $Value < -100) || ((int) $Value > 100)) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
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
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableTreble)) {
            trigger_error($this->Translate('treble control not enabled'), E_USER_NOTICE);
            return false;
        }
        if (($Value < 0) || ($Value > 100)) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'treble'], (string) $Value));
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
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableTreble)) {
            trigger_error($this->Translate('treble control not enabled'), E_USER_NOTICE);
            return false;
        }
        if (!in_array(substr($Value, 0, 1), ['-', '+'])) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            return false;
        }
        if (((int) $Value < -100) || ((int) $Value > 100)) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
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
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePitch)) {
            trigger_error($this->Translate('pitch control not enabled'), E_USER_NOTICE);
            return false;
        }
        if (($Value < 80) || ($Value > 120)) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['mixer', 'pitch'], (string) $Value));
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
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePitch)) {
            trigger_error($this->Translate('pitch control not enabled'), E_USER_NOTICE);
            return false;
        }
        if (!in_array(substr($Value, 0, 1), ['-', '+'])) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
            return false;
        }
        if (((int) $Value < -40) || ((int) $Value > 40)) {
            trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
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
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_NOTICE);
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
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('play', (string) $FadeIn));
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
            trigger_error($this->Translate('Value greater as duration'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('time', (string) $Value));
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
    public function Display2LinesEx(string $Text1, string $Text2, int $Duration, bool $Centered, int $Brightness): bool
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
        $Values[] = (string) $Duration;
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData('display', $Values));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * GetLinesPerScreen
     *
     * @return bool|int
     */
    public function GetLinesPerScreen(): bool|int
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
     * @return bool|array
     */
    public function GetDisplayedText(): bool|array
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
     * @return bool|array
     */
    public function GetDisplayedNow(): bool|array
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
        return self::IsSameSongURL($URL, (string) $LMSData->Data[0]);
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
        return self::IsSameSongURL($URL, (string) $LMSData->Data[0]);
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
            $Data = ['play', 'item_id:' . $FavoriteID];
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

        return self::IsSameSongURL($URL, (string) $LMSData->Data[0]);
    }

    /**
     * InsertInPlaylistByUrlAt
     * IPS-Instanz-Funktion 'LSQ_InsertInPlaylistByUrlAt'.
     * Fügt eine URL an einer Position der Playlist ein. Der LMS hängt sie an, danach wird sie verschoben.
     * Enthält die URL mehrere Titel (z.B. eine Playlist-Datei), werden alle in ihrer Reihenfolge verschoben.
     *
     * @param  string $URL      Die URL (z.B. file:///…, http://…).
     * @param  int    $Position Position ab 1; Anzahl der Titel + 1 hängt an.
     * @return bool
     */
    public function InsertInPlaylistByUrlAt(string $URL, int $Position): bool
    {
        // Bei Zufallswiedergabe beziehen sich Positionen und 'playlist move' auf die gemischte Reihenfolge (live geprüft).
        if ($this->GetValue(\SqueezeBox\Device\Variables::Shuffle) != 0) {
            trigger_error($this->Translate('Not possible while shuffle is active.'), E_USER_NOTICE);
            return false;
        }
        $OldTracks = $this->_GetTrackCount();
        if ($OldTracks === false) {
            return false;
        }
        if (($Position < 1) || ($Position > $OldTracks + 1)) {
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Position'), E_USER_NOTICE);
            return false;
        }
        if (!$this->AddToPlaylistByUrlEx($URL, '')) {
            return false;
        }
        $NewTracks = $this->_GetTrackCount();
        if ($NewTracks === false) {
            return false;
        }
        for ($i = 0; $i < ($NewTracks - $OldTracks); $i++) {
            if (($OldTracks + 1 + $i) == ($Position + $i)) {
                continue;
            }
            if (!$this->MoveSongInPlaylist($OldTracks + 1 + $i, $Position + $i)) {
                return false;
            }
        }
        return true;
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
        return self::IsSameSongURL($URL, (string) $LMSData->Data[0]);
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
            trigger_error($this->Translate('Error on move song in playlist.'), E_USER_NOTICE);
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
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'delete'], (string) $Position));
        if ($LMSData === null) {
            return false;
        }
        // Der LMS bestätigt mit dem Echo des Index.
        if ($LMSData->Data[0] !== (string) $Position) {
            trigger_error($this->Translate('Error delete song from playlist.'), E_USER_NOTICE);
            return false;
        }
        return true;
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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
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
        return $this->SavePlaylist('tempplaylist_' . str_replace(':', '', $this->ReadPropertyString(\SqueezeBox\Device\Property::Address)));
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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'resume'], [$Name, 'noplay:1']));
        if ($LMSData === null) {
            return false;
        }
        $ret = $LMSData->Data[0];
        if (($ret == '/' . $Name) || ($ret == '\\' . $Name)) {
            trigger_error($this->Translate('Playlist not found.'), E_USER_NOTICE);
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
        $Playlist = 'tempplaylist_' . str_replace(':', '', $this->ReadPropertyString(\SqueezeBox\Device\Property::Address));
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'resume'], [$Playlist, 'wipePlaylist:1', 'noplay:0']));
        if ($LMSData === null) {
            return false;
        }
        if ($LMSData->Data[0] != $Playlist) {
            trigger_error($this->Translate('TempPlaylist not found.'), E_USER_NOTICE);
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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'load'], [$Name, 'noplay:1']));
        if ($LMSData === null) {
            return false;
        }
        $ret = $LMSData->Data[0];
        if (($ret == '/' . $Name) || ($ret == '\\' . $Name)) {
            trigger_error($this->Translate('Playlist not found.'), E_USER_NOTICE);
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
        if (!$this->_PrepareSearchPattern($Genre, $Artist, $Album)) {
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
        if (!$this->_PrepareSearchPattern($Genre, $Artist, $Album)) {
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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
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
            $Data = ['load', 'item_id:' . $FavoriteID];
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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Titel'), E_USER_NOTICE);
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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Name'), E_USER_NOTICE);
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
            $Data = ['add', 'item_id:' . $FavoriteID];
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
        if (!$this->_PrepareSearchPattern($Genre, $Artist, $Album)) {
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
        if (!$this->_PrepareSearchPattern($Genre, $Artist, $Album)) {
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
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'zap'], (string) $Position));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * GetPlaylistURL
     *
     * @return bool|string
     */
    public function GetPlaylistURL(): bool|string
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'playlistsinfo'], ''));
        if ($LMSData === null) {
            return false;
        }
        $Song = (new \SqueezeBox\LMSSongInfo($LMSData->Data))->GetSong();
        if (!isset($Song['Url'])) {
            // Der LMS antwortet leer, wenn die aktuelle Playlist nicht aus einer gespeicherten Playlist stammt.
            trigger_error($this->Translate('The current playlist is not a saved playlist.'), E_USER_NOTICE);
            return false;
        }
        return $Song['Url'];
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

    /**
     * GetPlaylistInfo
     * IPS-Instanz-Funktion 'LSQ_GetPlaylistInfo'.
     * Liefert Informationen zur zuletzt geladenen gespeicherten Playlist.
     *
     * @return bool|array Id, Name, Modified und Url; leeres Array, wenn keine gespeicherte Playlist geladen ist.
     */
    public function GetPlaylistInfo(): bool|array
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'playlistsinfo'], ''));
        if ($LMSData === null) {
            return false;
        }
        if (count($LMSData->Data) == 1) {
            return [];
        }
        $Song = (new \SqueezeBox\LMSSongInfo($LMSData->Data))->GetSong();
        // Die Antwort enthält nur id, name, modified und url; Songfelder ohne Wert entfallen.
        return array_intersect_key($Song, array_flip(['Id', 'Name', 'Modified', 'Url']));
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
        if (($Index < 1) || ($Index > $this->GetValue(\SqueezeBox\Device\Variables::Tracks))) {
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Index'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'index'], (string) ($Index - 1)));
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
            trigger_error($this->Translate('Value must be 0, 1 or 2.'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'shuffle'], (string) $Value));
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
            trigger_error($this->Translate('Value must be 0, 1 or 2.'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'repeat'], (string) $Value));
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
     * Fügt die Titel hinter dem aktuellen Titel ein.
     *
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
            $Data = ['insert', 'item_id:' . $FavoriteID];
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
     * @return bool|array
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
    public function GetSongInfoByTrackIndex(int $Index): bool|array
    {
        $Index--;
        if ($Index == -1) {
            $Index = '-';
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['status', (string) $Index, '1'], 'tags:gladiqrRtueJINpsy'));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = new \SqueezeBox\LMSSongInfo($LMSData->Data);
        $SongArray = $SongInfo->GetSong();
        if (count($SongArray) <= 1) {
            trigger_error($this->Translate('Index not valid.'), E_USER_NOTICE);
            return false;
        }
        return $SongArray;
    }

    /**
     * GetSongInfoOfTilePlaylist
     * Liefert Informationen über alle Songs aus der aktuelle Wiedergabeliste.
     *
     * @return bool|array[index]
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
    public function GetSongInfoOfCurrentPlaylist(): bool|array
    {
        $max = $this->GetValue(\SqueezeBox\Device\Variables::Tracks);
        if ($max == 0) {
            return [];
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['status', '0', (string) $max], 'tags:gladiqrRtueJINpsy'));
        if ($LMSData === null) {
            return false;
        }
        $LMSData->SliceData();
        $SongInfo = new \SqueezeBox\LMSSongInfo($LMSData->Data);
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowTotalPlaytime)) {
            $this->SetValueInteger(\SqueezeBox\Device\Variables::TotalRuntime, $SongInfo->GetTotalDuration());
        }
        return $SongInfo->GetAllSongs();
    }

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
            trigger_error(sprintf($this->Translate('%s can not be empty.'), 'Genre'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['randomplaychoosegenre'], [$Genre, (int) $Active]));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * RequestAction
     * Meldungen der Aktion werden dem Nutzer angezeigt.
     *
     * @param  string $Ident
     * @param  mixed $Value
     * @return void
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->ExecuteWithMessages(fn (): bool => $this->ExecuteAction($Ident, $Value));
    }

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
     * getTime
     * Zeitquelle für Timer. Die Symcon-Stubs der Unit-Tests verlangen diese Methode im Modul.
     *
     * @return int
     */
    protected function getTime(): int
    {
        return time();
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
        $Address = $this->ReadPropertyString(\SqueezeBox\Device\Property::Address);
        if (($Address !== '') && !\SqueezeBox\LMSData::IsValidAddress($Address)) {
            $this->SetStatus(IS_EBASE + 2);
            return;
        }
        $Value = IS_INACTIVE;
        if ($State == IS_ACTIVE) {
            $LMSResponse = $this->SendDirect(new \SqueezeBox\LMSData('connected', '?'));
            if ($LMSResponse != null) {
                $Value = ($LMSResponse->Data[0] == '1') ? IS_ACTIVE : IS_INACTIVE;
            }
        }
        $OldState = $this->GetStatus();
        $this->SetStatus($Value);
        if ($Value == IS_ACTIVE) {
            if ($OldState == $Value) { // Kein Trigger durch IM_CHANGESTATUS, also selber loslegen
                $this->RequestAllState();
            }
            // Erst nach 5 Sekunden, sonst sind beim ModuleReload InstanceInterface Fehler möglich
            $this->SetTimerInterval(\SqueezeBox\Device\Timer::SyncPresentation, 5000);
        } else {
            if ($OldState == $Value) { // Kein Trigger durch IM_CHANGESTATUS, also selber loslegen
                $this->_SetNewPower(false);
                $this->SetCover();
            }
        }
    }

    /**
     * ProcessHookData
     * Verarbeitet Daten aus dem Webhook. Meldungen werden als Antwort ausgegeben.
     *
     * @global array $_GET
     */
    protected function ProcessHookData(): void
    {
        $this->ExecuteWithMessages(fn () => $this->HandleHookData());
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
            return parent::SetStatus($State);
        }
        return true;
    }

    /**
     * ExecuteAction
     * Führt die Aktion einer Statusvariable bzw. des Formulars aus.
     *
     * @param  string $Ident
     * @param  mixed $Value
     * @return bool false im Fehlerfall.
     */
    private function ExecuteAction(string $Ident, mixed $Value): bool
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case '_SetNewSyncPresentation':
                $this->SetTimerInterval(\SqueezeBox\Device\Timer::SyncPresentation, 0);
                $this->_SetNewSyncPresentation();
                return true;
            case \SqueezeBox\Device\Variables::Status:
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
                    default:
                        $this->_InvalidActionValue();
                        return false;
                }
                break;
            case \SqueezeBox\Device\Variables::Volume:
                $result = $this->SetVolume((int) $Value);
                break;
            case \SqueezeBox\Device\Variables::Bass:
                $result = $this->SetBass((int) $Value);
                break;
            case \SqueezeBox\Device\Variables::Treble:
                $result = $this->SetTreble((int) $Value);
                break;
            case \SqueezeBox\Device\Variables::Pitch:
                $result = $this->SetPitch((int) $Value);
                break;
            case \SqueezeBox\Device\Variables::Preset:
                $result = $this->SelectPreset((int) $Value);
                break;
            case \SqueezeBox\Device\Variables::Power:
                $result = $this->Power((bool) $Value);
                break;
            case \SqueezeBox\Device\Variables::Mute:
                $result = $this->SetMute((bool) $Value);
                break;
            case \SqueezeBox\Device\Variables::Repeat:
                if ((int) $Value == 1) {
                    $Value = 2;
                } elseif ((int) $Value == 2) {
                    $Value = 1;
                }
                $result = $this->SetRepeat((int) $Value);
                break;
            case \SqueezeBox\Device\Variables::Shuffle:
                $result = $this->SetShuffle((int) $Value);
                break;
            case \SqueezeBox\Device\Variables::Position2:
                $Time = ($this->DurationRAW / 100) * (int) $Value;
                $result = $this->SetPosition(intval($Time));
                break;
            case \SqueezeBox\Device\Variables::Index:
                $result = $this->GoToTrack((int) $Value);
                break;
            case \SqueezeBox\Device\Variables::SleepTimer:
                $result = $this->SetSleep((int) $Value);
                break;
            case \SqueezeBox\Device\Variables::Sync:
                if ((int) $Value == 0) {
                    $result = $this->SetUnSync();
                } elseif ((int) $Value == 100) {
                    $result = true;
                } else {
                    $result = $this->SetSync((int) $Value);
                }
                break;
            case \SqueezeBox\Device\Variables::Randomplay:
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
                    default:
                        $this->_InvalidActionValue();
                        return false;
                }
                break;
            case \SqueezeBox\Device\Variables::TilePlaylist:
                $TilePlaylist = json_decode((string) $Value, true);
                if (!is_array($TilePlaylist) || !isset($TilePlaylist['current'])) {
                    $this->_InvalidActionValue();
                    return false;
                }
                $result = $this->GoToTrack((int) $TilePlaylist['current'] + 1);
                break;
            case \SqueezeBox\Device\Property::ShowHTMLPlaylist:
                $this->UpdateFormField('Table', 'enabled', (bool) $Value);
                $this->UpdateFormField('Columns', 'enabled', (bool) $Value);
                $this->UpdateFormField('Rows', 'enabled', (bool) $Value);
                $this->UpdateFormField('HTMLExpansionPanel', 'expanded', (bool) $Value);
                return true;
            default:
                trigger_error($this->Translate('Invalid ident') . ' ' . $Ident, E_USER_NOTICE);
                return false;
        }
        return $result;
    }

    /**
     * HandleHookData
     * Wählt den Titel aus der HTML-Playlist aus.
     *
     * @global array $_GET
     */
    private function HandleHookData(): void
    {
        if ((!isset($_GET['ID'])) || (!isset($_GET['Type'])) || (!isset($_GET['Secret']))) {
            echo $this->Translate('Bad Request');
            return;
        }
        // Ohne erzeugte HTML-Playlist gibt es kein Secret; der Hash wäre sonst ohne Geheimnis berechenbar.
        $MySecret = (string) $this->WebHookSecretTrack;
        $CalcSecret = base64_encode(sha1($MySecret . '0' . $_GET['ID'], true));
        if (($MySecret === '') || ($CalcSecret != rawurldecode($_GET['Secret']))) {
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
            trigger_error($errormsg, E_USER_NOTICE);
            return false;
        }
        return true;
    }

    /**
     * _InvalidActionValue
     * Meldet einen ungültigen Wert für eine Aktion.
     *
     * @return void
     */
    private function _InvalidActionValue(): void
    {
        trigger_error($this->Translate('Value invalid.'), E_USER_NOTICE);
    }

    /**
     * _PrepareSearchPattern
     * Ersetzt leere Suchmuster durch '*' (alle) und prüft, ob mindestens ein Suchmuster angegeben ist.
     *
     * @param  string $Genre
     * @param  string $Artist
     * @param  string $Album
     * @return bool True wenn mindestens ein Suchmuster vorhanden ist.
     */
    private function _PrepareSearchPattern(string &$Genre, string &$Artist, string &$Album): bool
    {
        foreach ([&$Genre, &$Artist, &$Album] as &$Pattern) {
            if ($Pattern == '') {
                $Pattern = '*';
            }
        }
        unset($Pattern);
        if (($Genre == '*') && ($Artist == '*') && ($Album == '*')) {
            trigger_error($this->Translate('One search pattern is required'), E_USER_NOTICE);
            return false;
        }
        return true;
    }

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
        // Player mit IP-Adresse haben keine Variable Power und lassen sich nicht ausschalten.
        if ($this->FindIDForIdent(\SqueezeBox\Device\Variables::Power) == 0) {
            return true;
        }
        return (bool) $this->GetValue(\SqueezeBox\Device\Variables::Power);
    }

    /**
     * _StartSubscribe
     *
     * @return void
     */
    private function _StartSubscribe(): void
    {
        if ($this->_isPlayerConnected()) {
            $this->isSubscribed = true;
            // tags:N liefert remote_title (Sendername bzw. Anzeigetitel von Streams); id und title sind immer enthalten.
            $this->Send(new \SqueezeBox\LMSData(['status', '-', '1'], ['subscribe:' . $this->ReadPropertyInteger(\SqueezeBox\Device\Property::Interval), 'tags:N'], false));
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
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableSleepTimer)) {
                if (($this->GetValue(\SqueezeBox\Device\Variables::SleepTimer)) != 0) {
                    return;
                }
            }
            @$this->Send(new \SqueezeBox\LMSData(['status', '-', '1'], 'subscribe:0', false));
        }
        $this->isSubscribed = false;
    }

    /**
     * _SetNewName
     *
     * @param  string $Name
     * @return void
     */
    private function _SetNewName(string $Name): void
    {
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ChangeName)) {
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
        // Player mit IP-Adresse haben keine Variable Power.
        if ($this->FindIDForIdent(\SqueezeBox\Device\Variables::Power) == 0) {
            return;
        }
        if ($this->GetValue(\SqueezeBox\Device\Variables::Power) != $Power) {
            $this->SetValueBoolean(\SqueezeBox\Device\Variables::Power, $Power);
            if (!$Power) {
                $this->_SetModeToStop();
                if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSleepTimeout)) {
                    $this->SetValueInteger(\SqueezeBox\Device\Variables::SleepTimeout, 0);
                }
                if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableSleepTimer)) {
                    $this->SetValueInteger(\SqueezeBox\Device\Variables::SleepTimer, 0);
                }
                if ($this->isSubscribed) {
                    $this->_StopSubscribe();
                }
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
        if ($this->GetValue(\SqueezeBox\Device\Variables::Status) != 2) {
            $this->SetValueInteger(\SqueezeBox\Device\Variables::Status, 2);
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
        if ($this->GetValue(\SqueezeBox\Device\Variables::Status) != 3) {
            $this->SetValueInteger(\SqueezeBox\Device\Variables::Status, 3);
        }
    }

    /**
     * _SetModeToStop
     *
     * @return void
     */
    private function _SetModeToStop(): void
    {
        if ($this->GetValue(\SqueezeBox\Device\Variables::Status) != 1) {
            $this->SetValueInteger(\SqueezeBox\Device\Variables::Status, 1);
            $this->_SetNewTime(0);
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSleepTimeout)) {
                $this->SetValueInteger(\SqueezeBox\Device\Variables::SleepTimeout, 0);
            }
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableSleepTimer)) {
                $this->SetValueInteger(\SqueezeBox\Device\Variables::SleepTimer, 0);
            }
        } else {
            if ($this->isSubscribed) {
                if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSleepTimeout)) {
                    $this->SetValueInteger(\SqueezeBox\Device\Variables::SleepTimeout, 0);
                }
                if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableSleepTimer)) {
                    $this->SetValueInteger(\SqueezeBox\Device\Variables::SleepTimer, 0);
                }
                $this->_StopSubscribe();
            }
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
        if (is_string($Value) && in_array(substr($Value, 0, 1), ['+', '-'])) {
            $Value = (int) $this->GetValue(\SqueezeBox\Device\Variables::Volume) + (int) $Value;
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
            if ($this->GetValue(\SqueezeBox\Device\Variables::Mute)) {
                $this->SetValueBoolean(\SqueezeBox\Device\Variables::Mute, false);
            }
        }
        if ($this->GetValue(\SqueezeBox\Device\Variables::Volume) != (int) $Value) {
            $this->SetValueInteger(\SqueezeBox\Device\Variables::Volume, (int) $Value);
        }
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
        if (is_string($Value) && in_array(substr($Value, 0, 1), ['+', '-'])) {
            $Value = (int) $this->GetValue(\SqueezeBox\Device\Variables::Bass) + (int) $Value;
            if ($Value < 0) {
                $Value = 0;
            }
            if ($Value > 100) {
                $Value = 100;
            }
        }
        $this->SetValueInteger(\SqueezeBox\Device\Variables::Bass, (int) $Value);
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
        if (is_string($Value) && in_array(substr($Value, 0, 1), ['+', '-'])) {
            $Value = (int) $this->GetValue(\SqueezeBox\Device\Variables::Treble) + (int) $Value;
            if ($Value < 0) {
                $Value = 0;
            }
            if ($Value > 100) {
                $Value = 100;
            }
        }
        $this->SetValueInteger(\SqueezeBox\Device\Variables::Treble, (int) $Value);
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
        if (is_string($Value) && in_array(substr($Value, 0, 1), ['+', '-'])) {
            $Value = (int) $this->GetValue(\SqueezeBox\Device\Variables::Pitch) + (int) $Value;
            if ($Value < 80) {
                $Value = 80;
            }
            if ($Value > 120) {
                $Value = 120;
            }
        }
        $this->SetValueInteger(\SqueezeBox\Device\Variables::Pitch, (int) $Value);
    }

    /**
     * _SetNewTime
     *
     * @param  int $Time
     * @return void
     */
    private function _SetNewTime(int $Time): void
    {
        // Streams haben keine Dauer und damit keine Position.
        if ($this->DurationRAW == 0) {
            $Time = 0;
        }

        $this->PositionRAW = $Time;
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePositionText)) {
            $this->SetValueString(\SqueezeBox\Device\Variables::PositionText, $this->ConvertSeconds($Time));
        }
        $this->SetValueInteger(\SqueezeBox\Device\Variables::Position, $Time);
        if ($this->DurationRAW == 0) {
            $this->SetValueFloat(\SqueezeBox\Device\Variables::Position2, 0);
        } elseif ($this->isSeekable) {
            $Value = (100 / $this->DurationRAW) * $Time;
            $this->SetValueFloat(\SqueezeBox\Device\Variables::Position2, $Value);
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
        if ($Duration == 0) {
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableDurationText)) {
                $this->SetValueString(\SqueezeBox\Device\Variables::DurationText, '');
            }
        } else {
            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableDurationText)) {
                $NewDuration = $this->ConvertSeconds($Duration);
                if ($this->GetValue(\SqueezeBox\Device\Variables::DurationText) != $NewDuration) {
                    $this->SetValueString(\SqueezeBox\Device\Variables::DurationText, $NewDuration);
                }
            }
        }
        $this->DurationRAW = $Duration;
        $this->SetValueInteger(\SqueezeBox\Device\Variables::Duration, $Duration);
        $this->_RefreshPositionAction();
        if ($Duration == 0) {
            // Position, PositionText und Position2 zurücksetzen.
            $this->_SetNewTime(0);
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
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSleepTimeout)) {
            $this->SetValueInteger(\SqueezeBox\Device\Variables::SleepTimeout, $Value);
            if ($Value == 0 && $this->GetValue(\SqueezeBox\Device\Variables::Status) != 2) {
                $this->_StopSubscribe();
            }
            if ($Value != 0 && !$this->isSubscribed) {
                $this->_StartSubscribe();
            }
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableSleepTimer)) {
            if ($Value == 0) {
                $this->SetValueInteger(\SqueezeBox\Device\Variables::SleepTimer, 0);
            }
        }
    }

    /**
     * _GetTrackCount
     * Liest die Anzahl der Titel der aktuellen Playlist direkt vom LMS.
     *
     * @return int|false
     */
    private function _GetTrackCount(): int|false
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playlist', 'tracks'], '?'));
        if ($LMSData === null) {
            return false;
        }
        return (int) ($LMSData->Data[0] ?? 0);
    }

    /**
     * _SetNewStationName
     * Setzt den Sendernamen aus einer Statusmeldung (live geprüft mit TuneIn und Icecast, 15.10.2026):
     * 1. remote_title (Name des Favoriten bzw. Anzeigetitel), wenn es keine URL ist; nur in der Antwort mit Tags.
     * 2. sonst den bisherigen Wert behalten (die Abo-Meldung enthält kein remote_title),
     * 3. ist der leer, current_title, wenn es keine URL und kein Songtitel ist. TuneIn liefert dort den Sendernamen,
     *    Icecast-Streams dagegen 'Interpret - Titel'.
     * Ohne Remote-Titel ist der Sendername leer. Im Zustand Stop fehlt die Dauer auch bei Titeln,
     * daher bleibt der Sendername bei einem angehaltenen Remote-Titel erhalten.
     *
     * @param  string $Mode         play, pause oder stop.
     * @param  bool   $Remote       remote:1 in der Statusmeldung.
     * @param  bool   $HasDuration  Dauer größer 0 in der Statusmeldung.
     * @param  string $CurrentTitle current_title der Statusmeldung.
     * @param  string $RemoteTitle  remote_title der Statusmeldung.
     * @param  string $Title        title der Statusmeldung.
     * @return void
     */
    private function _SetNewStationName(string $Mode, bool $Remote, bool $HasDuration, string $CurrentTitle, string $RemoteTitle, string $Title): void
    {
        if ($Remote && !in_array($Mode, ['play', 'pause'])) {
            return;
        }
        $Station = '';
        if ($Remote && !$HasDuration) {
            $Station = (string) $this->GetValue(\SqueezeBox\Device\Variables::StationName);
            $IsSongTitle = ($Title !== '') && (($CurrentTitle === $Title) || str_ends_with($CurrentTitle, ' - ' . $Title));
            if (($RemoteTitle !== '') && !self::IsUrl($RemoteTitle)) {
                $Station = $RemoteTitle;
            } elseif (($Station === '') && ($CurrentTitle !== '') && !self::IsUrl($CurrentTitle) && !$IsSongTitle) {
                $Station = $CurrentTitle;
            }
        }
        if ($this->GetValue(\SqueezeBox\Device\Variables::StationName) !== $Station) {
            $this->SetValueString(\SqueezeBox\Device\Variables::StationName, $Station);
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
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSyncMaster)) {
            if ($this->GetValue(\SqueezeBox\Device\Variables::Master) != $isMaster) {
                $this->SetValueBoolean(\SqueezeBox\Device\Variables::Master, $isMaster);
            }
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
            $this->_SetNewSyncPresentation();
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
        $AllPlayerIDs = IPS_GetInstanceListByModuleID(\SqueezeBox\GUID::Squeezebox);
        foreach ($AllPlayerIDs as $DeviceID) {
            if ($DeviceID == $this->InstanceID) {
                continue;
            }
            if (IPS_GetInstance($DeviceID)['ConnectionID'] == $this->ParentID) {
                // Wenn Sync aktiv ist und das Modul neu geladen wird, dann kann IPS_GetProperty fehlschlagen.
                $Addresses[$DeviceID] = @IPS_GetProperty($DeviceID, \SqueezeBox\Device\Property::Address);
            }
        }
        return $Addresses;
    }

    /**
     * _SetNewTrackPresentation
     *
     * @param  int $Tracks
     * @return void
     */
    private function _SetNewTrackPresentation(int $Tracks): void
    {
        $this->RegisterVariableInteger(
            \SqueezeBox\Device\Variables::Index,
            $this->Translate('Playlist current position'),
            [
                \SqueezeBox\Presentation::Type                 => VARIABLE_PRESENTATION_SLIDER,
                \SqueezeBox\Presentation::Icon                 => 'list-radio',
                // Symcon verlangt Max > Min; bei 0 oder 1 Titel daher 0 bis 1.
                \SqueezeBox\Presentation\Slider::Min           => ($Tracks > 1) ? 1 : 0,
                \SqueezeBox\Presentation\Slider::Max           => max($Tracks, 1),
                \SqueezeBox\Presentation\Slider::Step          => 1,
                \SqueezeBox\Presentation\Slider::Digits        => 0,
                \SqueezeBox\Presentation\Slider::Type          => 5,
                \SqueezeBox\Presentation\Slider::Percentage    => false,
                \SqueezeBox\Presentation\Slider::Prefix        => '',
                \SqueezeBox\Presentation\Slider::Suffix        => ' #',
                \SqueezeBox\Presentation\Slider::IntervalsUsed => false,
                \SqueezeBox\Presentation\Slider::Intervals     => '[]',
            ],
            12
        );
        // Bei höchstens einem Titel gibt es nichts auszuwählen; der Regler (0 bis 1) wäre sonst bedienbar.
        if ($Tracks > 1) {
            $this->EnableAction(\SqueezeBox\Device\Variables::Index);
        } else {
            $this->DisableAction(\SqueezeBox\Device\Variables::Index);
        }
    }

    /**
     * _SetNewSyncPresentation
     *
     * @return void
     */
    private function _SetNewSyncPresentation(): void
    {
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSyncControl)) {
            return;
        }
        $SyncMembers = [];
        if ($this->SyncMembers != '') {
            $SyncMembers = explode(',', $this->SyncMembers);
        }
        $Addresses = $this->_GetAllPlayers();
        $Options = [
            [
                \SqueezeBox\Presentation\Enum::Value           => 0,
                \SqueezeBox\Presentation\Enum::Caption         => $this->Translate('Off'),
                \SqueezeBox\Presentation\Enum::IconActive      => false,
                \SqueezeBox\Presentation\Enum::Icon            => '',
                \SqueezeBox\Presentation\Enum::Color           => -1
            ],
            [
                \SqueezeBox\Presentation\Enum::Value           => 100,
                \SqueezeBox\Presentation\Enum::Caption         => $this->Translate('On'),
                \SqueezeBox\Presentation\Enum::IconActive      => false,
                \SqueezeBox\Presentation\Enum::Icon            => '',
                \SqueezeBox\Presentation\Enum::Color           => 0xff0000
            ]
        ];
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
            $Options[] = [
                \SqueezeBox\Presentation\Enum::Value           => $InstanceID,
                \SqueezeBox\Presentation\Enum::Caption         => IPS_GetName($InstanceID),
                \SqueezeBox\Presentation\Enum::IconActive      => false,
                \SqueezeBox\Presentation\Enum::Icon            => '',
                \SqueezeBox\Presentation\Enum::Color           => $Color
            ];
        }
        $this->RegisterVariableInteger(
            \SqueezeBox\Device\Variables::Sync,
            $this->Translate('Synchronize'),
            [
                \SqueezeBox\Presentation::Icon              => 'Speaker',
                \SqueezeBox\Presentation::Type              => VARIABLE_PRESENTATION_ENUMERATION,
                \SqueezeBox\Presentation\Enum::Options      => json_encode($Options)
            ],
            15
        );
        $this->SetValueInteger(\SqueezeBox\Device\Variables::Sync, count($SyncMembers) == 0 ? 0 : 100);
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
            $this->isSeekable = $Value;
            $this->_RefreshPositionAction();
        }
    }

    /**
     * _RefreshPositionAction
     * Position2 ist nur bedienbar, wenn der Titel seekable ist und eine Dauer hat (keine Streams).
     *
     * @return void
     */
    private function _RefreshPositionAction(): void
    {
        if ($this->isSeekable && ($this->DurationRAW > 0)) {
            $this->EnableAction(\SqueezeBox\Device\Variables::Position2);
        } else {
            $this->DisableAction(\SqueezeBox\Device\Variables::Position2);
        }
    }

    /**
     * _RefreshPlaylistIndex
     *
     * @return void
     */
    private function _RefreshPlaylistIndex(): void
    {
        $Data = $this->Multi_Playlist;
        if (!is_array($Data)) {
            $Data = [];
        }
        $CurrentIndex = (int) $this->GetValue(\SqueezeBox\Device\Variables::Index);

        // TilePlaylist
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowTilePlaylist)) {
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
            $this->SetValueString(\SqueezeBox\Device\Variables::TilePlaylist, $TilePlaylistData);
        }
        // HTML-Playlist
        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowHTMLPlaylist)) {
            $HTML = $this->GetTable($Data, 'SqueezeBoxPlaylist', 'Track', 'Position', $CurrentIndex);
            $this->SetValueString(\SqueezeBox\Device\Variables::HTMLPlaylist, $HTML);
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
                trigger_error($this->Translate('Error on read playlist'), E_USER_NOTICE);
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
        $CoverID = @IPS_GetObjectIDByIdent(\SqueezeBox\Device\Variables::Cover, $this->InstanceID);
        if ($CoverID === false) {
            $CoverID = IPS_CreateMedia(1);
            IPS_SetParent($CoverID, $this->InstanceID);
            IPS_SetIdent($CoverID, \SqueezeBox\Device\Variables::Cover);
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
                $Size = $this->ReadPropertyString(\SqueezeBox\Device\Property::CoverSize);
                $Player = $this->ReadPropertyString(\SqueezeBox\Device\Property::Address);
                $CoverRAW = $this->GetCover('', $Size, $Player);
            }
        }
        if ($CoverRAW === false) {
            $CoverRAW = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'nocover.png');
        }
        IPS_SetMediaContent($CoverID, base64_encode($CoverRAW));
    }

    /**
     * DecodeLMSResponse
     *
     * @param  mixed $LMSData
     * @return bool
     */
    private function DecodeLMSResponse(\SqueezeBox\LMSData $LMSData): bool
    {
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
                        if ($this->GetValue(\SqueezeBox\Device\Variables::Mute) != (bool) $LMSData->Data[0]) {
                            $this->SetValueBoolean(\SqueezeBox\Device\Variables::Mute, (bool) $LMSData->Data[0]);
                        }
                        break;
                    case 'bass':
                        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableBass)) {
                            $this->_SetNewBass($LMSData->Data[0]);
                        }
                        break;
                    case 'treble':
                        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableTreble)) {
                            $this->_SetNewTreble($LMSData->Data[0]);
                        }
                        break;
                    case 'pitch':
                        if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePitch)) {
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
                            $this->SetValueString(\SqueezeBox\Device\Variables::Title, '');
                            $this->SetValueString(\SqueezeBox\Device\Variables::StationName, '');
                            $this->SetValueString(\SqueezeBox\Device\Variables::Playlistname, '');
                            $this->SetValueString(\SqueezeBox\Device\Variables::Artist, '');
                            $this->SetValueString(\SqueezeBox\Device\Variables::Album, '');
                            $this->SetValueString(\SqueezeBox\Device\Variables::Genre, '');
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Duration, 0);
                            $this->DurationRAW = 0;
                            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableDurationText)) {
                                $this->SetValueString(\SqueezeBox\Device\Variables::DurationText, '0:00');
                            }
                            $this->SetValueFloat(\SqueezeBox\Device\Variables::Position2, 0);
                            $this->PositionRAW = 0;
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Position, 0);
                            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePositionText)) {
                                $this->SetValueString(\SqueezeBox\Device\Variables::PositionText, '0:00');
                            }
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Index, 0);
                        }
                        if ($this->GetValue(\SqueezeBox\Device\Variables::Tracks) != (int) $LMSData->Data[0]) {
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Tracks, (int) $LMSData->Data[0]);
                        }
                        break;
                    case 'delete':
                    case 'addtracks':
                        // Die Anzahl ändert sich immer; VM_UPDATE von Tracks lädt die Playlist.
                        if (!$this->isSubscribed) {
                            $this->RequestState(\SqueezeBox\Device\Variables::Tracks);
                        }
                        break;
                    case 'play':
                    case 'load_done':
                    case 'resume':
                    case 'move':
                    case 'playtracks':
                    case 'loadtracks':
                        // Neue oder umsortierte Playlist. Ändert sich die Anzahl der Titel, lädt VM_UPDATE von Tracks die Playlist; sonst hier neu laden (auch bei laufendem Abo).
                        // Bei neuer Playlist den Sender leeren; läuft ein Stream, setzt ihn die nächste Statusmeldung wieder.
                        if (($LMSData->Command[1] != 'move') && ($this->GetValue(\SqueezeBox\Device\Variables::StationName) !== '')) {
                            $this->SetValueString(\SqueezeBox\Device\Variables::StationName, '');
                        }
                        $Tracks = $this->GetValue(\SqueezeBox\Device\Variables::Tracks);
                        $this->RequestState(\SqueezeBox\Device\Variables::Tracks);
                        if ($this->GetValue(\SqueezeBox\Device\Variables::Tracks) == $Tracks) {
                            $this->_RefreshPlaylist();
                        }
                        break;
                    case 'shuffle':
                        if ($this->GetValue(\SqueezeBox\Device\Variables::Shuffle) != (int) $LMSData->Data[0]) {
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Shuffle, (int) $LMSData->Data[0]);
                        }
                        break;
                    case 'repeat':
                        $Value = (int) $LMSData->Data[0];
                        if ($Value == 1) {
                            $Value = 2;
                        } elseif ($Value == 2) {
                            $Value = 1;
                        }
                        if ($this->GetValue(\SqueezeBox\Device\Variables::Repeat) != $Value) {
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Repeat, $Value);
                        }
                        break;
                    case 'name':
                        $this->SetValueString(\SqueezeBox\Device\Variables::Playlistname, trim((string) $LMSData->Data[0]));
                        break;
                    case 'index':
                    case 'jump':
                        if ($LMSData->Data[0] == '') {
                            break;
                        }
                        if (((string) $LMSData->Data[0][0] === '+') || ((string) $LMSData->Data[0][0] === '-')) {
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Index, (int) $this->GetValue(\SqueezeBox\Device\Variables::Index) + (int) $LMSData->Data[0]);
                        } else {
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Index, (int) $LMSData->Data[0] + 1);
                        }
                        break;
                    case 'newsong':
                        $Value = trim((string) $LMSData->Data[0]);
                        // Solange ein Stream keine Metadaten hat, sendet der LMS die URL als Titel; dann den letzten Titel behalten.
                        if (!self::IsUrl($Value)) {
                            $this->SetValueString(\SqueezeBox\Device\Variables::Title, $Value);
                        }
                        if (isset($LMSData->Data[1])) {
                            $NewIndex = (int) $LMSData->Data[1] + 1;
                            if ($this->GetValue(\SqueezeBox\Device\Variables::Index) != $NewIndex) {
                                // VM_UPDATE von Index aktualisiert Playlist-Markierung, Cover und Titeldaten.
                                $this->SetValueInteger(\SqueezeBox\Device\Variables::Index, $NewIndex);
                            } else {
                                // Gleicher Index, z.B. neue Playlist ab Titel 1.
                                $this->_RefreshPlaylistIndex();
                                $this->SetCover();
                            }
                        } else {
                            $this->RequestState(\SqueezeBox\Device\Variables::Playlistname);
                            $this->RequestState(\SqueezeBox\Device\Variables::Album);
                            $this->RequestState(\SqueezeBox\Device\Variables::Title);
                            $this->RequestState(\SqueezeBox\Device\Variables::Artist);
                            $this->RequestState(\SqueezeBox\Device\Variables::Genre);
                            $this->RequestState(\SqueezeBox\Device\Variables::Duration);
                            $this->SetCover();
                        }
                        break;
                    default:
                        return false;
                }
                break;
            case 'album':
                $this->SetValueString(\SqueezeBox\Device\Variables::Album, trim((string) $LMSData->Data[0]));
                break;
            case 'current_title':
                break;
            case 'title':
                $this->SetValueString(\SqueezeBox\Device\Variables::Title, trim((string) $LMSData->Data[0]));
                break;
            case 'artist':
                $this->SetValueString(\SqueezeBox\Device\Variables::Artist, trim((string) $LMSData->Data[0]));
                break;
            case 'genre':
                $this->SetValueString(\SqueezeBox\Device\Variables::Genre, trim((string) $LMSData->Data[0]));
                break;
            case 'duration':
                if ((int) $LMSData->Data[0] != $this->DurationRAW) {
                    $this->_SetNewDuration((int) $LMSData->Data[0]);
                }
                break;
            case 'time':
                $this->_SetNewTime((int) $LMSData->Data[0]);
                break;
            case 'signalstrength':
                if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSignalStrength)) {
                    $this->SetValueInteger(\SqueezeBox\Device\Variables::Signalstrength, (int) $LMSData->Data[0]);
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
            case 'prefset':
                // Tritt der Player einer Sync-Gruppe bei, erhält er als Slave weder 'sync' noch Statusmeldungen, nur die neue syncgroupid.
                // Daher den Zustand inkl. sync_master/sync_slaves und der übernommenen Playlist neu lesen.
                if (($LMSData->Command[1] ?? '') == 'server' && ($LMSData->Command[2] ?? '') == 'syncgroupid' && ((string) ($LMSData->Data[0] ?? '') !== '')) {
                    $this->RequestAllState();
                    break;
                }
                return false;
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
                if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableRandomPlay)) {
                    switch ($LMSData->Data[0]) {
                        case 'tracks':
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Randomplay, 1);
                            break;
                        case 'albums':
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Randomplay, 2);
                            break;
                        case 'contributors':
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Randomplay, 3);
                            break;
                        case 'year':
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Randomplay, 4);
                            break;
                        default:
                            $this->SetValueInteger(\SqueezeBox\Device\Variables::Randomplay, 0);
                            break;
                    }
                }
                break;
            case 'status':
                $isSyncActive = false;
                $HasDuration = false;
                $HasPlaylistName = false;
                $NewTime = null;
                $Mode = '';
                $Remote = false;
                $CurrentTitle = '';
                $RemoteTitle = '';
                $StatusTitle = '';
                $DurationValue = 0;
                $IndexChanged = false;
                $TitleChanged = false;
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
                            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::ShowSignalStrength)) {
                                $this->SetValueInteger(\SqueezeBox\Device\Variables::Signalstrength, (int) $Data->Value);
                            }
                            break;
                        case 'mode':
                            $Mode = $Data->Value;
                            switch ($Data->Value) {
                                case 'play':
                                    $this->_SetModeToPlay();
                                    break;
                                case 'stop':
                                    if ($this->GetValue(\SqueezeBox\Device\Variables::Status) != 1) {
                                        $this->SetValueInteger(\SqueezeBox\Device\Variables::Status, 1);
                                    }
                                    break;
                                case 'pause':
                                    $this->_SetModeToPause();
                                    break;
                            }
                            break;
                        case 'time':
                            // Erst nach der Dauer auswerten, die später in der Antwort folgt.
                            $NewTime = (int) $Data->Value;
                            break;
                        case 'duration':
                            $HasDuration = true;
                            $DurationValue = (int) $Data->Value;
                            if ((int) $Data->Value != $this->DurationRAW) {
                                $this->_SetNewDuration((int) $Data->Value);
                            }
                            break;
                        case 'can_seek':
                            $this->_SetSeekable((int) $Data->Value == 1);
                            break;
                        case 'remote':
                            $Remote = ((int) $Data->Value == 1);
                            break;
                        case 'remote_title':
                            $RemoteTitle = trim((string) $Data->Value);
                            break;
                        case 'sleep':
                            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableSleepTimer)) {
                                if ($this->GetValue(\SqueezeBox\Device\Variables::SleepTimer) != (int) $Data->Value) {
                                    $this->SetValueInteger(\SqueezeBox\Device\Variables::SleepTimer, (int) $Data->Value);
                                }
                            }
                            break;
                        case 'sync_master':
                            /*
                             * ID of the master player in the sync group this player belongs to.
                             * Only if synced.*/
                            $this->SyncMaster = $Data->Value;
                            $this->_SetNewSyncMaster($Data->Value == $this->ReadPropertyString(\SqueezeBox\Device\Property::Address));
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
                            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableBass)) {
                                $this->_SetNewBass((int) $Data->Value);
                            }
                            break;
                        case 'mixer treble':
                            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnableTreble)) {
                                $this->_SetNewTreble((int) $Data->Value);
                            }
                            break;
                        case 'mixer pitch':
                            if ($this->ReadPropertyBoolean(\SqueezeBox\Device\Property::EnablePitch)) {
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
                            if ($this->GetValue(\SqueezeBox\Device\Variables::Repeat) != $Value) {
                                $this->SetValueInteger(\SqueezeBox\Device\Variables::Repeat, $Value);
                            }
                            break;
                        case 'playlist shuffle':
                            if ($this->GetValue(\SqueezeBox\Device\Variables::Shuffle) != (int) $Data->Value) {
                                $this->SetValueInteger(\SqueezeBox\Device\Variables::Shuffle, (int) $Data->Value);
                            }
                            break;
                        case 'playlist_tracks':
                            if ($this->GetValue(\SqueezeBox\Device\Variables::Tracks) != (int) $Data->Value) {
                                $this->SetValueInteger(\SqueezeBox\Device\Variables::Tracks, (int) $Data->Value);
                                // Andere Playlist: Sender leeren. Slaves erhalten die Ereignisse zum Laden einer Playlist nicht.
                                // Läuft ein Stream, setzt ihn _SetNewStationName am Ende dieser Meldung wieder.
                                if ($this->GetValue(\SqueezeBox\Device\Variables::StationName) !== '') {
                                    $this->SetValueString(\SqueezeBox\Device\Variables::StationName, '');
                                }
                            }
                            break;
                        case 'playlist mode':
                            // off, on oder disabled (Party-Modus der LMS-Oberfläche); wird nicht ausgewertet.
                            break;
                        case 'playlist_cur_index':
                        case 'playlist index':
                            if ($this->GetValue(\SqueezeBox\Device\Variables::Index) != ((int) $Data->Value + 1)) {
                                $this->SetValueInteger(\SqueezeBox\Device\Variables::Index, (int) $Data->Value + 1);
                                $IndexChanged = true;
                            }
                            break;
                        case 'playlist_name':
                            $HasPlaylistName = true;
                            $this->SetValueString(\SqueezeBox\Device\Variables::Playlistname, trim((string) $Data->Value));
                            break;
                            //playlist_timestamp:1474744498.14079
                            // merken und auf änderung ein Refresh machen ?!
                        case 'current_title':
                            $CurrentTitle = trim((string) $Data->Value);
                            break;
                        case 'title':
                            $Value = trim((string) $Data->Value);
                            $StatusTitle = $Value;
                            if (!self::IsUrl($Value) && ($this->GetValue(\SqueezeBox\Device\Variables::Title) != $Value)) {
                                $this->SetValueString(\SqueezeBox\Device\Variables::Title, $Value);
                                $TitleChanged = true;
                            }
                            break;
                        case 'genre':
                            $this->SetValueString(\SqueezeBox\Device\Variables::Genre, trim((string) $Data->Value));
                            break;
                        case 'artist':
                            $this->SetValueString(\SqueezeBox\Device\Variables::Artist, trim((string) $Data->Value));
                            break;
                        case 'album':
                            $this->SetValueString(\SqueezeBox\Device\Variables::Album, trim((string) $Data->Value));
                            break;
                    }
                }
                // Streams liefern bei Wiedergabe und Pause keine Dauer. Im Zustand Stop fehlt sie auch bei Titeln.
                if (!$HasDuration && in_array($Mode, ['play', 'pause']) && ($this->DurationRAW != 0)) {
                    $this->_SetNewDuration(0);
                }
                if ($NewTime !== null) {
                    $this->_SetNewTime($NewTime);
                }
                // Streams haben keine Dauer: in der Abo-Meldung fehlt sie, in der Antwort mit Tags ist sie 0.
                $this->_SetNewStationName($Mode, $Remote, $HasDuration && ($DurationValue > 0), $CurrentTitle, $RemoteTitle, $StatusTitle);
                // Neuer Titel im Stream ohne neuen Index: Der LMS sendet 'playlist newsong' nur an den Master einer Sync-Gruppe,
                // Slaves erfahren den Wechsel nur aus der Statusmeldung (live geprüft 15.10.2026). Daher hier Titeldaten und Cover nachladen.
                // Beim Master hat newsong den Titel bereits gesetzt, dann ist TitleChanged false.
                if ($TitleChanged && !$IndexChanged && in_array($Mode, ['play', 'pause'])) {
                    $this->RequestState(\SqueezeBox\Device\Variables::Album);
                    $this->RequestState(\SqueezeBox\Device\Variables::Artist);
                    $this->RequestState(\SqueezeBox\Device\Variables::Genre);
                    $this->SetCover();
                }
                // playlist_name fehlt, wenn die aktuelle Playlist keine gespeicherte ist (z.B. nach PlayUrl).
                if (!$HasPlaylistName && ($this->GetValue(\SqueezeBox\Device\Variables::Playlistname) !== '')) {
                    $this->SetValueString(\SqueezeBox\Device\Variables::Playlistname, '');
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

    /**
     * Send
     * Konvertiert $Data zu einem JSONString und versendet diese an den Splitter.
     *
     * @param \SqueezeBox\LMSData $LMSData Zu versendende Daten.
     * @return \SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function Send(\SqueezeBox\LMSData $LMSData): ?\SqueezeBox\LMSData
    {
        if ($this->ReadPropertyString(\SqueezeBox\Device\Property::Address) == '') {
            return null;
        }
        try {
            if (!$this->_isPlayerConnected() && ($LMSData->Command[0] != 'connected')) {
                throw new Exception($this->Translate('Player not connected'), E_USER_NOTICE);
            }
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
        } catch (Exception $exc) {
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return null;
        }
        $LMSData->Address = $this->ReadPropertyString(\SqueezeBox\Device\Property::Address);
        return $this->SendToSplitter($LMSData);
    }

    /**
     * SendDirect
     * Konvertiert $Data zu einem String und versendet diesen direkt an den LMS.
     *
     * @param \SqueezeBox\LMSData $LMSData Zu versendende Daten.
     * @return ?\SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function SendDirect(\SqueezeBox\LMSData $LMSData): ?\SqueezeBox\LMSData
    {
        if ($this->ReadPropertyString(\SqueezeBox\Device\Property::Address) == '') {
            return null;
        }
        try {
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
            if (!$this->_isPlayerConnected() && ($LMSData->Command[0] != 'connected')) {
                throw new Exception($this->Translate('Player not connected'), E_USER_NOTICE);
            }
        } catch (Exception $exc) {
            $this->SendDebug('Receive Direct', $exc->getMessage(), 0);
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return null;
        }
        $LMSData->Address = $this->ReadPropertyString(\SqueezeBox\Device\Property::Address);
        return $this->SendDirectViaSplitter($LMSData);
    }
}
