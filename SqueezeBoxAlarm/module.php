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
eval('declare(strict_types=1);namespace SqueezeboxAlarm {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxAlarm {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxAlarm {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxAlarm {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');

/**
 * Enthält die Daten eines Alarm.
 */
class LSA_Alarm
{
    /**
     * Id des Alarm.
     *
     * @var int|string
     */
    public int|string $Id;

    /**
     * Index des Alarm.
     *
     * @var ?int
     */
    public ?int $Index;

    /**
     * Tage des Alarm.
     *
     * @var string
     */
    public string $Dow = '0,1,2,3,4,5,6';

    /**
     * Status des Alarm.
     *
     * @var bool
     */
    public bool $Enabled = true;

    /**
     * Wiederholung des Alarm.
     *
     * @var bool
     */
    public bool $Repeat = true;

    /**
     * Shuffle des Alarm.
     *
     * @var int
     */
    public int $Shufflemode = 0;

    /**
     * Uhrzeit des Alarm.
     *
     * @var int
     */
    public int $Time;

    /**
     * Lautstärke des Alarm.
     *
     * @var int
     */
    public int $Volume;

    /**
     * Playlist des Alarm.
     *
     * @var string
     */
    public string $Url = 'CURRENT_PLAYLIST';

    /**
     * __construct
     * Erzeugt aus den übergeben Daten einen neuen LSA_Alarm.
     *
     * @param array $Alarm Das Array wie es von LMSTaggingArray erzeugt wird.
     * @param ?int   $Index Der fortlaufende Index dieses Weckers.
     * @return LSA_Alarm
     */
    public function __construct(array $Alarm, ?int $Index = null)
    {
        foreach ($Alarm as $Key => $Value) {
            $this->{$Key} = $Value;
        }
        $this->Index = $Index;
    }

    /**
     * __sleep
     * Liefert die Daten welche behalten werden müssen.
     *
     * @return array
     */
    public function __sleep(): array
    {
        return ['Id', 'Index', 'Dow', 'Enabled', 'Repeat', 'Time', 'Volume', 'Url', 'Shufflemode'];
    }

    /**
     * IpsToDow
     * Schreibt die Wochentag aus dem IPS Format in die Eigenschaft Dow.
     *
     * @param int $Days Die Wochentag im IPS-Format.
     */
    public function IpsToDow(int $Days): void
    {
        for ($i = 0; $i < 8; $i++) {
            if ((($Days >> $i) & 0x1) == 1) {
                if ($i == 6) {
                    $Dow[] = 0;
                } else {
                    $Dow[] = $i + 1;
                }
            }
        }
        sort($Dow);
        $this->Dow = implode(',', $Dow);
    }

    /**
     * DowToIps
     * Liefert die Eigenschaft Dow im IPS-Format.
     *
     * @return int Die Wochentage im IPS-Format.
     */
    public function DowToIps(): int
    {
        if ($this->Dow == '') {
            return 0;
        }
        $Days = explode(',', $this->Dow);
        $IpsDays = 0;
        foreach ($Days as $Day) {
            $Day--;
            if ($Day < 0) {
                $Day = 6;
            }
            $IpsDays += pow(2, $Day);
        }
        return $IpsDays;
    }

    /**
     * AddDow
     * Fügt einen Wochentag zum Wecker hinzu.
     *
     * @param int $Dow Ein Wochentag.
     */
    public function AddDow(int $Dow): void
    {
        if ($this->Dow == '') {
            $this->Dow = (string) $Dow;
            return;
        }
        $Days = explode(',', $this->Dow);
        $Key = array_search((string) $Dow, $Days);
        if ($Key !== false) {
            return;
        }
        $Days[] = (string) $Dow;
        sort($Days);
        $this->Dow = implode(',', $Days);
    }

    /**
     * DelDow
     * Löscht einen Wochentag aus dem Wecker.
     *
     * @param int $Dow Ein Wochentag.
     */
    public function DelDow(int $Dow): void
    {
        if ($this->Dow == '') {
            return;
        }
        $Days = explode(',', $this->Dow);
        $Key = array_search((string) $Dow, $Days);
        if ($Key === false) {
            return;
        }
        unset($Days[$Key]);
        sort($Days);
        $this->Dow = implode(',', $Days);
    }

    /**
     * TimeToArray
     * Liefert die Alarmzeit als Array für die Ereignisse in IPS.
     *
     * @return array
     */
    public function TimeToArray(): array
    {
        $result['Second'] = (int) gmdate('s', $this->Time);
        $result['Minute'] = (int) gmdate('i', $this->Time);
        $result['Hour'] = (int) gmdate('G', $this->Time);
        return $result;
    }

    /**
     * ArrayToTime
     * Schreibt die Alarmzeit aus dem übergeben Array in die Eigenschaft Time.
     *
     * @param array $Time
     */
    public function ArrayToTime(array $Time): void
    {
        $this->Time = ((($Time[0] * 60) + $Time[1]) * 60) + $Time[2];
    }
}

/**
 * LSA_AlarmList ist eine Klasse welche ein Array von LSA_Alarm enthält.
 */
class LSA_AlarmList
{
    /**
     * Array mit allen Items.
     *
     * @var LSA_Alarm[]
     */
    public array $Items = [];

    /**
     * __construct
     * Erzeugt eine neue LSA_AlarmList aus einem Array von LMSTaggingArray mit allen Alarmen.
     *
     * @param array $Alarms Alle Wecker oder null.
     * @return LSA_AlarmList
     */
    public function __construct(array $Alarms)
    {
        foreach ($Alarms as $Index => $AlarmData) {
            $Alarm = new LSA_Alarm($AlarmData, $Index);
            $this->Items[$Alarm->Id] = $Alarm;
        }
    }

    /**
     * __sleep
     * Liefert die Daten welche behalten werden müssen.
     *
     * @return array
     */
    public function __sleep(): array
    {
        return ['Items'];
    }

    /**
     * Add
     * Fügt einen LSA_Alarm in $Items hinzu.
     *
     * @param LSA_Alarm $Alarm Das neue Objekt.
     * @return int Der Index des Items.
     */
    public function Add(LSA_Alarm $Alarm): int
    {
        if (isset($this->Items[$Alarm->Id])) {
            return $this->Update($Alarm);
        }

        $NewIndex = count($this->Items);
        $this->Items[$Alarm->Id] = $Alarm;
        $this->Items[$Alarm->Id]->Index = $NewIndex;
        return $NewIndex;
    }

    /**
     * Update
     * Update für einen LSA_Alarm in $Items.
     *
     * @param LSA_Alarm $Alarm Das neue Objekt.
     * @return int
     */
    public function Update(LSA_Alarm $Alarm): int
    {
        $this->Items[$Alarm->Id] = $Alarm;
        return $this->Items[$Alarm->Id]->Index;
    }

    /**
     * Remove
     * Löscht einen LSA_Alarm aus $Items.
     *
     * @param string $AlarmId Der Index des zu löschenden Items.
     */
    public function Remove(string $AlarmId): void
    {
        if (!isset($this->Items[$AlarmId])) {
            return;
        }
        $RemoveIndex = $this->Items[$AlarmId]->Index;
        unset($this->Items[$AlarmId]);
        foreach ($this->Items as &$Alarm) {
            if ($Alarm->Index > $RemoveIndex) {
                $Alarm->Index--;
            }
        }
    }

    /**
     * GetById
     * Liefert einen bestimmten LSA_Alarm aus den Items anhand der Id.
     *
     * @param string $AlarmId
     * @return LSA_Alarm $Alarm
     */
    public function GetById(string $AlarmId): bool|LSA_Alarm
    {
        if (!isset($this->Items[$AlarmId])) {
            return false;
        }
        return $this->Items[$AlarmId];
    }

    /**
     * GetByIndex
     * Liefert einen bestimmten LSA_Alarm aus den Items anhand des Index.
     *
     * @param int $Index
     * @return LSA_Alarm $Alarm
     */
    public function GetByIndex(int $Index): bool|LSA_Alarm
    {
        foreach ($this->Items as $Alarm) {
            if ($Alarm->Index == $Index) {
                return $Alarm;
            }
        }
        return false;
    }
}

/**
 * SqueezeBoxAlarm Klasse für die Wecker einer SqueezeBox als Instanz in IPS.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.10
 *
 * @property int $ParentID
 * @property array $Multi_Playlist Alle Datensätze der Alarm-Playlisten.
 * @property LSA_AlarmList $Alarms Alle Wecker als Objekt.
 * @property resource|bool $Socket
 * @method bool RegisterHook(string $WebHook)
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
 *
 */
class SqueezeboxAlarm extends IPSModuleStrict
{
    use \SqueezeBox\LMSSocket,
        \SqueezeBox\LMSHTMLTable,
        \SqueezeBox\DebugHelper,
        \SqueezeboxAlarm\VariableProfileHelper,
        \SqueezeboxAlarm\BufferHelper,
        \SqueezeboxAlarm\InstanceStatus,
        \SqueezeboxAlarm\VariableHelper {
            \SqueezeboxAlarm\InstanceStatus::MessageSink as IOMessageSink;
            \SqueezeboxAlarm\InstanceStatus::RegisterParent as IORegisterParent;
            \SqueezeboxAlarm\InstanceStatus::RequestAction as IORequestAction;
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
        $this->SetReceiveDataFilter('.*"Address":"","Command":\["(alarm.*|client".*|playerpref","alarm.*|prefset","server","alarm.*)');
        $this->RegisterPropertyString(\SqueezeBox\Alarm\Property::Address, '');
        $this->RegisterPropertyBoolean(\SqueezeBox\Alarm\Property::DynamicDisplay, false);
        $this->RegisterPropertyBoolean(\SqueezeBox\Alarm\Property::ShowAdd, true);
        $this->RegisterPropertyBoolean(\SqueezeBox\Alarm\Property::ShowDelete, true);
        $this->RegisterPropertyBoolean(\SqueezeBox\Alarm\Property::ShowAlarmHTMLPlaylist, true);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString(\SqueezeBox\Alarm\Property::Table, json_encode($Style['Table']));
        $this->RegisterPropertyString(\SqueezeBox\Alarm\Property::Columns, json_encode($Style['Columns']));
        $this->RegisterPropertyString(\SqueezeBox\Alarm\Property::Rows, json_encode($Style['Rows']));
        $this->Multi_Playlist = [];
        $this->Alarms = new LSA_AlarmList([]);
        $this->ParentID = 0;
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
        if (property_exists($Data->configuration, 'showAlarmPlaylist')) {
            $Data->configuration->showAlarmHTMLPlaylist = $Data->configuration->showAlarmPlaylist;
            for ($AlarmIndex = 0; $AlarmIndex < 10; $AlarmIndex++) {
                $vid = $this->FindIDForIdent('AlarmPlaylist' . $AlarmIndex);
                if ($vid > 0) { //Migrate Statusvariable AlarmPlaylist to AlarmHTMLPlaylist
                    @IPS_SetIdent($vid, 'AlarmHTMLPlaylist' . $AlarmIndex);
                }
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
        $this->SetReceiveDataFilter('.*"Address":"","Command":\["(alarm.*|client".*|playerpref","alarm.*|prefset","server","alarm.*)');

        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        $this->Multi_Playlist = [];
        $this->ParentID = 0;
        $this->Alarms = new LSA_AlarmList([]);

        parent::ApplyChanges();

        // Adresse prüfen
        $Address = $this->ReadPropertyString(\SqueezeBox\Alarm\Property::Address);

        // Adresse als Filter setzen
        $this->SetReceiveDataFilter('.*"Address":"' . $Address . '","Command":\["(alarm.*|client".*|playerpref","alarm.*|prefset","server","alarm.*)');

        $this->SetSummary($Address);

        // Profile löschen
        $this->UnregisterProfile('LSA.Intensity');
        $this->UnregisterProfile('LSA.Timeout');
        $this->UnregisterProfile('LSA.Snooze');
        $this->UnregisterProfile('LSA.Shuffle');
        $this->UnregisterProfile('LSA.Add');
        $this->UnregisterProfile('LSA.State');
        $this->UnregisterProfile('LSA.Del.' . $this->InstanceID);

        //Status-Variablen anlegen
        $this->RegisterVariableBoolean(
            'EnableAll',
            $this->Translate('All alarms active'),
            [
                \SqueezeBox\Presentation::Type                     => VARIABLE_PRESENTATION_SWITCH,
                \SqueezeBox\Presentation\Switchable::Type          => 0,
                \SqueezeBox\Presentation\Switchable::IconFalseUsed => true,
                \SqueezeBox\Presentation\Switchable::IconTrue      => 'alarm-clock',
                \SqueezeBox\Presentation\Switchable::IconFalse     => 'xmark',
                \SqueezeBox\Presentation\Switchable::GlowColor     => 0xff0000,
                \SqueezeBox\Presentation\Switchable::GlowIntensity => 50
            ],
            1
        );
        $this->EnableAction('EnableAll');
        $this->RegisterVariableInteger(
            'DefaultVolume',
            $this->Translate('Default alarm volume'),
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
            2
        );
        $this->EnableAction('DefaultVolume');
        $this->RegisterVariableBoolean(
            'FadeIn',
            $this->Translate('Fade alarm'),
            [
                \SqueezeBox\Presentation::Type                     => VARIABLE_PRESENTATION_SWITCH,
                \SqueezeBox\Presentation\Switchable::Type          => 0,
                \SqueezeBox\Presentation\Switchable::IconFalseUsed => true,
                \SqueezeBox\Presentation\Switchable::IconTrue      => 'chart-line-up',
                \SqueezeBox\Presentation\Switchable::IconFalse     => 'xmark',
                \SqueezeBox\Presentation\Switchable::GlowColor     => 0xffff00,
                \SqueezeBox\Presentation\Switchable::GlowIntensity => 50
            ],
            3
        );
        $this->EnableAction('FadeIn');
        $this->RegisterVariableInteger(
            'Timeout',
            $this->Translate('Automatically stop'),
            [
                \SqueezeBox\Presentation::Type                 => VARIABLE_PRESENTATION_SLIDER,
                \SqueezeBox\Presentation::Icon                 => 'clock',
                \SqueezeBox\Presentation\Slider::Min           => 0,
                \SqueezeBox\Presentation\Slider::Max           => 600,
                \SqueezeBox\Presentation\Slider::Step          => 1,
                \SqueezeBox\Presentation\Slider::Digits        => 0,
                \SqueezeBox\Presentation\Slider::Type          => 5,
                \SqueezeBox\Presentation\Slider::Percentage    => false,
                \SqueezeBox\Presentation\Slider::Prefix        => '',
                \SqueezeBox\Presentation\Slider::Suffix        => $this->Translate(' sec'),
                \SqueezeBox\Presentation\Slider::IntervalsUsed => false,
                \SqueezeBox\Presentation\Slider::Intervals     => '[]',
                \SqueezeBox\Presentation\Slider::GradientType  => 0,
                \SqueezeBox\Presentation\Slider::Gradient      => '[]',
            ],
            4
        );
        $this->EnableAction('Timeout');
        $this->RegisterVariableInteger(
            'SnoozeSeconds',
            $this->Translate('Snoozetime'),
            [
                \SqueezeBox\Presentation::Type                 => VARIABLE_PRESENTATION_SLIDER,
                \SqueezeBox\Presentation::Icon                 => 'clock',
                \SqueezeBox\Presentation\Slider::Min           => 0,
                \SqueezeBox\Presentation\Slider::Max           => 1800,
                \SqueezeBox\Presentation\Slider::Step          => 1,
                \SqueezeBox\Presentation\Slider::Digits        => 0,
                \SqueezeBox\Presentation\Slider::Type          => 5,
                \SqueezeBox\Presentation\Slider::Percentage    => false,
                \SqueezeBox\Presentation\Slider::Prefix        => '',
                \SqueezeBox\Presentation\Slider::Suffix        => $this->Translate(' sec'),
                \SqueezeBox\Presentation\Slider::IntervalsUsed => false,
                \SqueezeBox\Presentation\Slider::Intervals     => '[]',
                \SqueezeBox\Presentation\Slider::GradientType  => 0,
                \SqueezeBox\Presentation\Slider::Gradient      => '[]',
            ],
            4
        );
        $this->EnableAction('SnoozeSeconds');
        if ($this->ReadPropertyBoolean(\SqueezeBox\Alarm\Property::ShowAdd)) {
            $this->RegisterVariableInteger(
                'AddAlarm',
                $this->Translate('Add alarm'),
                [
                    \SqueezeBox\Presentation::Icon         => 'alarm-plus',
                    \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_ENUMERATION,
                    \SqueezeBox\Presentation\Enum::Options => json_encode(
                        [
                            [
                                \SqueezeBox\Presentation\Enum::Value      => 0,
                                \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Add'),
                                \SqueezeBox\Presentation\Enum::IconActive => false,
                                \SqueezeBox\Presentation\Enum::Icon       => '',
                                \SqueezeBox\Presentation\Enum::Color      => -1,
                            ]
                        ]
                    )
                ],
                4
            );
            $this->EnableAction('AddAlarm');
        } else {
            $this->UnregisterVariable('AddAlarm');
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Alarm\Property::ShowDelete)) {
            $this->RefreshDeleteVariable(0);
            $this->EnableAction('DelAlarm');
        } else {
            $this->UnregisterVariable('DelAlarm');
        }
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Alarm\Property::ShowAlarmHTMLPlaylist)) {
            for ($AlarmIndex = 0; $AlarmIndex < 10; $AlarmIndex++) {
                $this->UnregisterVariable('AlarmHTMLPlaylist' . $AlarmIndex);
            }
        }
        // Wenn Kernel nicht bereit, dann warten... wenn unser IO Aktiv wird, holen wir unsere Daten :)
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyBoolean(\SqueezeBox\Alarm\Property::ShowAlarmHTMLPlaylist)) {
            $this->RegisterHook('LSAPlaylist' . $this->InstanceID);
        }
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
     * @param int $TimeStamp
     * @param int $SenderID
     * @param int $Message
     * @param array $Data
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
            case EM_CHANGEACTIVE:
                $this->SendDebug('EM_CHANGEACTIVE', $Data, 0);
                $Index = (int) substr(IPS_GetObject($SenderID)['ObjectIdent'], -1);
                $Alarms = $this->Alarms;
                $Alarm = $Alarms->GetByIndex($Index);
                if ($Alarm === false) {
                    break;
                }
                $Alarm->Enabled = $Data[0];
                $Alarms->Update($Alarm);
                $this->Alarms = $Alarms;

                /** @var LSA_Alarm $Alarm */
                $this->Send(new \SqueezeBox\LMSData(['alarm', 'update'], ['id:' . $Alarm->Id, 'enabled:' . (int) $Data[0]]));
                break;
            case EM_CHANGECYCLIC:
                $this->SendDebug('EM_CHANGECYCLIC', $Data, 0);
                $Index = (int) substr(IPS_GetObject($SenderID)['ObjectIdent'], -1);
                $Alarms = $this->Alarms;
                $Alarm = $Alarms->GetByIndex($Index);
                if ($Alarm === false) {
                    break;
                }
                $Alarm->IpsToDow($Data[2]);
                $Alarms->Update($Alarm);
                $this->Alarms = $Alarms;
                /** @var LSA_Alarm $Alarm */
                $this->Send(new \SqueezeBox\LMSData(['alarm', 'update'], ['id:' . $Alarm->Id, 'dow:' . $Alarm->Dow]));
                break;
            case EM_CHANGECYCLICTIMEFROM:
                $this->SendDebug('EM_CHANGECYCLICTIMEFROM', $Data, 0);
                $Index = (int) substr(IPS_GetObject($SenderID)['ObjectIdent'], -1);
                $Alarms = $this->Alarms;
                $Alarm = $Alarms->GetByIndex($Index);
                if ($Alarm === false) {
                    break;
                }
                $Alarm->ArrayToTime($Data);
                $Alarms->Update($Alarm);
                $this->Alarms = $Alarms;
                /** @var LSA_Alarm $Alarm  */
                $this->Send(new \SqueezeBox\LMSData(['alarm', 'update'], ['id:' . $Alarm->Id, 'time:' . $Alarm->Time]));
                break;
        }
    }

    /**
     * RequestAllState
     * IPS-Instanz-Funktion 'LSA_RequestAllState'.
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen.
     *
     * @return bool True wenn alle Abfragen erfolgreich waren, sonst false.
     */
    public function RequestAllState(): bool
    {
        if (!$this->RequestState('EnableAll')) {
            return false;
        }
        $ret = $this->RequestState('DefaultVolume');
        $ret = $ret && $this->RequestState('FadeIn');
        $ret = $ret && $this->RequestState('Timeout');
        $ret = $ret && $this->RequestState('SnoozeSeconds');
        $ret = $ret && $this->RequestState('Alarms');
        return $ret;
    }

    /**
     * RequestState
     * IPS-Instanz-Funktion 'LSA_RequestState'.
     * Fragt einen Wert der Alarme ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @param string $Ident Der Ident der abzufragenden Statusvariable.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function RequestState(string $Ident): bool
    {
        switch ($Ident) {
            case 'Alarms':
                $LMSResponse = new \SqueezeBox\LMSData('alarms', ['0', '10', 'filter:all']);
                break;
            case 'EnableAll':
                $LMSResponse = new \SqueezeBox\LMSData(['playerpref', 'alarmsEnabled'], '?');
                break;
            case 'DefaultVolume':
                $LMSResponse = new \SqueezeBox\LMSData(['playerpref', 'alarmDefaultVolume'], '?');
                break;
            case 'FadeIn':
                $LMSResponse = new \SqueezeBox\LMSData(['playerpref', 'alarmfadeseconds'], '?');
                break;
            case 'Timeout':
                $LMSResponse = new \SqueezeBox\LMSData(['playerpref', 'alarmTimeoutSeconds'], '?');
                break;
            case 'SnoozeSeconds':
                $LMSResponse = new \SqueezeBox\LMSData(['playerpref', 'alarmSnoozeSeconds'], '?');
                break;
            case 'AlarmPlaylistName0':
            case 'AlarmPlaylistName1':
            case 'AlarmPlaylistName2':
            case 'AlarmPlaylistName3':
            case 'AlarmPlaylistName4':
            case 'AlarmPlaylistName5':
            case 'AlarmPlaylistName6':
            case 'AlarmPlaylistName7':
            case 'AlarmPlaylistName8':
            case 'AlarmPlaylistName9':
            case 'AlarmRepeat0':
            case 'AlarmRepeat1':
            case 'AlarmRepeat2':
            case 'AlarmRepeat3':
            case 'AlarmRepeat4':
            case 'AlarmRepeat5':
            case 'AlarmRepeat6':
            case 'AlarmRepeat7':
            case 'AlarmRepeat8':
            case 'AlarmRepeat9':
            case 'AlarmVolume0':
            case 'AlarmVolume1':
            case 'AlarmVolume2':
            case 'AlarmVolume3':
            case 'AlarmVolume4':
            case 'AlarmVolume5':
            case 'AlarmVolume6':
            case 'AlarmVolume7':
            case 'AlarmVolume8':
            case 'AlarmVolume9':
                $LMSResponse = new \SqueezeBox\LMSData('alarms', ['0', '10', 'filter:all']);
                break;
            case 'AlarmShuffle0':
            case 'AlarmShuffle1':
            case 'AlarmShuffle2':
            case 'AlarmShuffle3':
            case 'AlarmShuffle4':
            case 'AlarmShuffle5':
            case 'AlarmShuffle6':
            case 'AlarmShuffle7':
            case 'AlarmShuffle8':
            case 'AlarmShuffle9':
            case 'AlarmState0':
            case 'AlarmState1':
            case 'AlarmState2':
            case 'AlarmState3':
            case 'AlarmState4':
            case 'AlarmState5':
            case 'AlarmState6':
            case 'AlarmState7':
            case 'AlarmState8':
            case 'AlarmState9':
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Sorry this request is unsupported by LMS.'), E_USER_NOTICE);
                restore_error_handler();
                return false;
            default:
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Ident not valid'), E_USER_NOTICE);
                restore_error_handler();
                return false;
        }
        $LMSResponse = $this->SendDirect($LMSResponse);
        if ($LMSResponse == null) {
            return false;
        }
        $LMSResponse->SliceData();
        if ((count($LMSResponse->Data) == 0) || ($LMSResponse->Data[0] == '?')) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Player not connected'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        return $this->DecodeLMSResponse($LMSResponse);
    }

    /**
     * SetAllActive
     * IPS-Instanz-Funktion 'LSA_SetAllActive'.
     * De/Aktiviert die Wecker-Funktionen des Gerätes.
     *
     * @param bool $Value De/Aktiviert die Wecker.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetAllActive(bool $Value): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['alarm', ($Value ? 'enableall' : 'disableall')]));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Command[1] == ($Value ? 'enableall' : 'disableall');
    }

    /**
     * SetDefaultVolume
     * IPS-Instanz-Funktion 'LSA_SetDefaultVolume'.
     * Setzt die Standard-Lautstärke für neue Wecker.
     *
     * @param int $Value Die neue Lautstärke
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetDefaultVolume(int $Value): bool
    {
        if (($Value < 0) || ($Value > 100)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playerpref', 'alarmDefaultVolume'], (string) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) ($LMSData->Data[0]) == (int) $Value;
    }

    /**
     * SetFadeIn
     * IPS-Instanz-Funktion 'LSA_SetFadeIn'.
     * De/Aktiviert das Einblenden der Wiedergabe.
     *
     * @param bool $Value True zum aktivieren, False zum deaktivieren.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetFadeIn(bool $Value): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playerpref', 'alarmfadeseconds'], $Value ? '1' : '0'));
        if ($LMSData === null) {
            return false;
        }
        return (int) ($LMSData->Data[0]) == (int) $Value;
    }

    /**
     * SetTimeout
     * IPS-Instanz-Funktion 'LSA_SetTimeout'.
     * Setzt die Zeit in Sekunden bis ein Wecker automatisch beendend wird.
     *
     * @param int $Value Zeit in Sekunden bis zum abschalten.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetTimeout(int $Value): bool
    {
        if ($Value < 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playerpref', 'alarmTimeoutSeconds'], (string) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) ($LMSData->Data[0]) == (int) $Value;
    }

    /**
     * SetSnoozeSeconds
     * IPS-Instanz-Funktion 'LSA_SetSnoozeSeconds'.
     * Setzt die Schlummerzeit in Sekunden.
     *
     * @param int $Value Die Schlummerzeit in Sekunden.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetSnoozeSeconds(int $Value): bool
    {
        if ($Value < 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playerpref', 'alarmSnoozeSeconds'], (string) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) ($LMSData->Data[0]) == (int) $Value;
    }

    /**
     * AlarmSnooze
     * IPS-Instanz-Funktion 'LSA_AlarmSnooze'.
     * Sendet das Schlummersignal an das Gerät und pausiert somit einen aktiven Alarm.
     *
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function AlarmSnooze(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['button'], ['snooze.single']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * AlarmStop
     * IPS-Instanz-Funktion 'LSA_AlarmStop'.
     * Beendet ein aktiven Alarm.
     *
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function AlarmStop(): bool
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['button'], ['power_off']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * AddAlarm
     * IPS-Instanz-Funktion 'LSA_AddAlarm'.
     * Fragt einen Wert der Alarme ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @return bool|int Index des Weckers, im Fehlerfall false.
     */
    public function AddAlarm(): bool|int
    {
        if (count($this->Alarms->Items) > 9) {
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['alarm', 'add'], ['enabled:1', 'time:25200', 'repeat:1', 'playlisturl:CURRENT_PLAYLIST']));
        if ($LMSData === null) {
            return false;
        }
        /** @var array $Data */
        $Data = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, ''))->DataArray();
        if (isset($Data['Id'])) {
            return count($this->Alarms->Items);
        }
        return false;
    }

    /**
     * DelAlarm
     * IPS-Instanz-Funktion 'LSA_DelAlarm'.
     * Löscht einen Wecker.
     *
     * @param int $AlarmIndex Der Index des zu löschenden Weckers.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function DelAlarm(int $AlarmIndex): bool
    {
        $Alarms = $this->Alarms;
        $Alarm = $Alarms->GetByIndex($AlarmIndex);
        if ($Alarm === false) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'AlarmIndex'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['alarm', 'delete'], ['id:' . $Alarm->Id]));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, ''))->DataArray();
        return $Data['Id'] === $Alarm->Id;
    }

    /**
     * SetPlaylist
     * IPS-Instanz-Funktion 'LSA_SetPlaylist'.
     * Setzt die Playlist bzw. die Wiedergabe für den Wecker.
     *
     * @param int    $AlarmIndex Der Index des Weckers.
     * @param string $Url        Die wiederzugebene URL.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetPlaylist(int $AlarmIndex, string $Url): bool
    {
        $Alarms = $this->Alarms;
        $Alarm = $Alarms->GetByIndex($AlarmIndex);
        if ($Alarm === false) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'AlarmIndex'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        if (($Url == '0') || ($Url == '')) {
            $Url = 'CURRENT_PLAYLIST';
        }

        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['alarm', 'update'], ['id:' . $Alarm->Id, 'playlisturl:' . (string) $Url]));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, ''))->DataArray();
        return $Data['Playlisturl'] === (string) $Url;
    }

    /**
     * SetShuffle
     * IPS-Instanz-Funktion 'LSA_SetShuffle'.
     * Setzt den Modus der zufälligen Wiedergabe des Weckers.
     *
     * @param int    $AlarmIndex Der Index des Weckers.
     * @param string $Value      Der neue Modus.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetShuffle(int $AlarmIndex, int $Value): bool
    {
        if (($Value < 0) || ($Value > 2)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s must be 0, 1 or 2.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $Alarms = $this->Alarms;
        $Alarm = $Alarms->GetByIndex($AlarmIndex);
        if ($Alarm === false) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'AlarmIndex'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['alarm', 'update'], ['id:' . $Alarm->Id, 'shufflemode:' . (int) $Value]));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, ''))->DataArray();
        return (int) ($Data['Shufflemode']) === (int) $Value;
    }

    /**
     * SetRepeat
     * IPS-Instanz-Funktion 'LSA_SetRepeat'.
     * De/Aktiviert die Wiederholung.
     *
     * @param int  $AlarmIndex Der Index des Weckers.
     * @param bool $Value      De/Aktiviert die Wiederholung.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetRepeat(int $AlarmIndex, bool $Value): bool
    {
        $Alarms = $this->Alarms;
        $Alarm = $Alarms->GetByIndex($AlarmIndex);
        if ($Alarm === false) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'AlarmIndex'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['alarm', 'update'], ['id:' . $Alarm->Id, 'repeat:' . (int) $Value]));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, ''))->DataArray();
        return (int) ($Data['Repeat']) === (int) $Value;
    }

    /**
     * SetVolume
     * IPS-Instanz-Funktion 'LSA_SetVolume'.
     * Setzt die Lautstärke des Weckers.
     *
     * @param int $AlarmIndex Der Index des Weckers.
     * @param int $Value      Die neue Lautstärke des Weckers.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetVolume(int $AlarmIndex, int $Value): bool
    {
        if (($Value < 0) || ($Value > 100)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $Alarms = $this->Alarms;
        $Alarm = $Alarms->GetByIndex($AlarmIndex);
        if ($Alarm === false) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'AlarmIndex'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['alarm', 'update'], ['id:' . $Alarm->Id, 'volume:' . (int) $Value]));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, ''))->DataArray();
        return (int) ($Data['Volume']) === (int) $Value;
    }

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
            case 'showAlarmHTMLPlaylist':
                $this->UpdateFormField('Table', 'enabled', (bool) $Value);
                $this->UpdateFormField('Columns', 'enabled', (bool) $Value);
                $this->UpdateFormField('Rows', 'enabled', (bool) $Value);
                $this->UpdateFormField('HTMLExpansionPanel', 'expanded', (bool) $Value);
                return;
            case 'EnableAll':
                $result = $this->SetAllActive((bool) $Value);
                break;
            case 'DefaultVolume':
                $result = $this->SetDefaultVolume((int) $Value);
                break;
            case 'FadeIn':
                $result = $this->SetFadeIn((bool) $Value);
                break;
            case 'AddAlarm':
                $AlarmIndex = $this->AddAlarm();
                $result = ($AlarmIndex !== false);
                break;
            case 'DelAlarm':
                $result = $this->DelAlarm((int) $Value);
                break;
            case 'Timeout':
                $result = $this->SetTimeout((int) $Value);
                break;
            case 'SnoozeSeconds':
                $result = $this->SetSnoozeSeconds((int) $Value);
                break;
            case 'AlarmShuffle0':
            case 'AlarmShuffle1':
            case 'AlarmShuffle2':
            case 'AlarmShuffle3':
            case 'AlarmShuffle4':
            case 'AlarmShuffle5':
            case 'AlarmShuffle6':
            case 'AlarmShuffle7':
            case 'AlarmShuffle8':
            case 'AlarmShuffle9':
                $Index = (int) substr($Ident, -1);
                $result = $this->SetShuffle($Index, (int) $Value);
                break;
            case 'AlarmRepeat0':
            case 'AlarmRepeat1':
            case 'AlarmRepeat2':
            case 'AlarmRepeat3':
            case 'AlarmRepeat4':
            case 'AlarmRepeat5':
            case 'AlarmRepeat6':
            case 'AlarmRepeat7':
            case 'AlarmRepeat8':
            case 'AlarmRepeat9':
                $Index = (int) substr($Ident, -1);
                $result = $this->SetRepeat($Index, (bool) $Value);
                break;
            case 'AlarmVolume0':
            case 'AlarmVolume1':
            case 'AlarmVolume2':
            case 'AlarmVolume3':
            case 'AlarmVolume4':
            case 'AlarmVolume5':
            case 'AlarmVolume6':
            case 'AlarmVolume7':
            case 'AlarmVolume8':
            case 'AlarmVolume9':
                $Index = (int) substr($Ident, -1);
                $result = $this->SetVolume($Index, (int) $Value);
                break;
            case 'AlarmState0':
            case 'AlarmState1':
            case 'AlarmState2':
            case 'AlarmState3':
            case 'AlarmState4':
            case 'AlarmState5':
            case 'AlarmState6':
            case 'AlarmState7':
            case 'AlarmState8':
            case 'AlarmState9':
                $Index = (int) substr($Ident, -1);
                if ($Value == 0) {
                    $result = $this->AlarmStop();
                } elseif ($Value == 1) {
                    $result = $this->AlarmSnooze();
                } else {
                    $result = true;
                }
                break;
            default:
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Invalid ident'), E_USER_NOTICE);
                restore_error_handler();
                return;
        }
        if ($result == false) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Error on Execute Action'), E_USER_NOTICE);
            restore_error_handler();
        }
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
        $LMSData = new \SqueezeBox\LMSData();
        $LMSData->CreateFromGenericObject($Data);
        $this->SendDebug('Receive Event ', $LMSData, 0);
        if (in_array($LMSData->Command[0], ['alarm', 'alarms', 'playerpref', 'prefset', 'client'])) {
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
            $this->LoadAlarmPlaylists();
            $this->RequestAllState();
        }
    }

    /**
     * ProcessHookData
     * Verarbeitet Daten aus dem Webhook.
     *
     * @param string $ID Die ausgewählte Alarm-Playlist als URL.
     * @global array $_GET
     */
    protected function ProcessHookData(): void
    {
        if ((!isset($_GET['ID'])) || (!isset($_GET['Type'])) || (!isset($_GET['Secret']))) {
            echo $this->Translate('Bad Request');
            return;
        }
        $AlarmIndex = substr($_GET['Type'], -1);
        $MySecret = $this->{'WebHookSecretAlarmHTMLPlaylist' . $AlarmIndex};
        $CalcSecret = base64_encode(sha1($MySecret . '0' . rawurldecode($_GET['ID']), true));

        if ($CalcSecret != rawurldecode($_GET['Secret'])) {
            echo $this->Translate('Access denied');
            return;
        }

        if (substr($_GET['Type'], 0, -1) != 'AlarmHTMLPlaylist') {
            echo $this->Translate('Bad Request');
            return;
        }

        if ($this->SetPlaylist((int) $AlarmIndex, rawurldecode($_GET['ID']))) {
            echo 'OK';
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
     * Liefert die Werkeinstellungen für die Eigenschaften Html, Table und Rows.
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
            ['index'    => 0,
                'key'   => 'Index',
                'name'  => 'Index',
                'show'  => false,
                'width' => 100,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''],
            ['index'    => 1,
                'key'   => 'Category',
                'name'  => $this->Translate('Category'),
                'show'  => true,
                'width' => 300,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''],
            ['index'    => 2,
                'key'   => 'Title',
                'name'  => $this->Translate('Title'),
                'show'  => true,
                'width' => 300,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => ''],
            ['index'    => 3,
                'key'   => 'Url',
                'name'  => 'Url',
                'show'  => false,
                'width' => 300,
                'color' => 0xffffff,
                'align' => 'center',
                'style' => '']
        ];
        $NewRowsConfig = [
            ['row'        => 'odd',
                'name'    => $this->Translate('odd'),
                'bgcolor' => 0x000000,
                'color'   => 0xffffff,
                'style'   => ''],
            ['row'        => 'even',
                'name'    => $this->Translate('even'),
                'bgcolor' => 0x080808,
                'color'   => 0xffffff,
                'style'   => ''],
            ['row'        => 'active',
                'name'    => $this->Translate('active'),
                'bgcolor' => 0x808000,
                'color'   => 0xffffff,
                'style'   => '']
        ];
        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig];
    }

    /**
     * RefreshPlaylist
     * Erzeugt eine HTML-Tabelle mit allen Playlisten für eine ~HTMLBox-Variable.
     *
     * @param int $AlarmIndex    Der Index des Alarms.
     * @param int $PlaylistIndex Der Index der aktuell gewählten Alarm-Playlist
     */
    private function RefreshPlaylist(int $AlarmIndex, int $PlaylistIndex): void
    {
        $Data = $this->Multi_Playlist;
        if (!is_array($Data)) {
            $Data = [];
        }
        // TilePlaylist -> Visu-SDK Fehlt, oder HTML-SDK nutzen?
        // HTML-Tabelle
        if ($this->ReadPropertyBoolean(\SqueezeBox\Alarm\Property::ShowAlarmHTMLPlaylist)) {
            if ($this->RegisterVariableString(
                'AlarmHTMLPlaylist' . $AlarmIndex,
                sprintf($this->Translate('Alarm %d playlist selection'), $AlarmIndex + 1),
                [
                    \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_WEB_CONTENT,
                    \SqueezeBox\Presentation\HTML::Type    => 0,
                    \SqueezeBox\Presentation\HTML::Padding => true
                ],
                (($AlarmIndex + 1) * 10) + 7
            )) {
                IPS_SetIcon($this->FindIDForIdent('AlarmHTMLPlaylist' . $AlarmIndex), 'list-music');
            }
            $HTML = $this->GetTable($Data, 'LSAPlaylist', 'AlarmHTMLPlaylist' . $AlarmIndex, 'Url', $PlaylistIndex + 1);
            $this->SetValueString('AlarmHTMLPlaylist' . $AlarmIndex, $HTML);
        }
    }

    /**
     * GetPlaylistItemFromUrl
     * Liefert einen Eintrag aus den Alarm-Playlisten anhand der URL.
     *
     * @param string $Url Die URL des zu suchenden Eintrages.
     * @return ?array Der gefundene Eintrag.
     */
    private function GetPlaylistItemFromUrl(string $Url): ?array
    {
        $Playlist = $this->Multi_Playlist;
        if (count($Playlist) == 0) {
            $this->LoadAlarmPlaylists();
            $Playlist = $this->Multi_Playlist;
        }
        if (count($Playlist) == 0) {
            return null;
        }
        if ($Url == 'CURRENT_PLAYLIST') {
            $Url = '';
        }
        foreach ($Playlist as /* $Index => */ $Item) {
            if ($Item['Url'] == $Url) {
                return $Item;
            }
        }
        return null;
    }

    /**
     * RefreshDeleteVariable
     * Aktualisiert die Variable Delete alarm mit der korrekten Anzahl der Alarme.
     *
     * @param int $Count Anzahl der Alarme.
     * @return void
     */
    private function RefreshDeleteVariable(int $Count): void
    {
        $Options = [];
        for ($index = 0; $index < $Count; $index++) {
            $Options[] = [
                \SqueezeBox\Presentation\Enum::Value           => $index,
                \SqueezeBox\Presentation\Enum::Caption         => (string) ($index + 1),
                \SqueezeBox\Presentation\Enum::IconActive      => false,
                \SqueezeBox\Presentation\Enum::Icon            => '',
                \SqueezeBox\Presentation\Enum::Color           => -1
            ];
        }
        $this->RegisterVariableInteger(
            'DelAlarm',
            $this->Translate('Delete alarm'),
            [
                \SqueezeBox\Presentation::Icon              => 'delete-right',
                \SqueezeBox\Presentation::Type              => VARIABLE_PRESENTATION_ENUMERATION,
                \SqueezeBox\Presentation\Enum::Options      => json_encode($Options)
            ],
            5
        );
    }

    /**
     * LoadAlarmPlaylists
     * Lädt alle für diesen Player gültige Alarm-Playlisten und speichert sie im Buffer.
     *
     * @return void
     */
    private function LoadAlarmPlaylists(): void
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['alarm', 'playlists'], [0, 100000]));
        if ($LMSData === null) {
            $this->Multi_Playlist = [];
            return;
        }
        $LMSData->SliceData();
        $AlarmPlaylist = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, 'category'))->DataArray();
        foreach ($AlarmPlaylist as $Index => &$Item) {
            $Item['Index'] = $Index;
        }
        $this->Multi_Playlist = $AlarmPlaylist;
    }

    /**
     * RefreshEvent
     * Erzeugt und Aktualisiert die Statusvariablen eines Alarms bei Veränderung.
     *
     * @param LSA_Alarm $Alarm Ein Alarm.
     * @return void
     */
    private function RefreshEvent(LSA_Alarm $Alarm): void
    {
        $eid = $this->FindIDForIdent('AlarmTime' . $Alarm->Index);
        if ($eid > 0) {
            $this->UnregisterMessage($eid, EM_CHANGEACTIVE);
            $this->UnregisterMessage($eid, EM_CHANGECYCLIC);
            $this->UnregisterMessage($eid, EM_CHANGECYCLICTIMEFROM);
        } else {
            $eid = IPS_CreateEvent(1);
            IPS_SetParent($eid, $this->InstanceID);
            IPS_SetIdent($eid, 'AlarmTime' . $Alarm->Index);
            IPS_SetName($eid, sprintf($this->Translate('Alarm %d alarmtime'), $Alarm->Index + 1));
            IPS_SetPosition($eid, (($Alarm->Index + 1) * 10) + 1);
            IPS_SetIcon($eid, 'Alert');
            IPS_SetEventScript($eid, $this->Translate("/*\r\nDon't change the action and target of this event!\r\nPut your code after this comment.\r\nBeware, that this event can be deleted when\r\n'Delete unused objects' is set.\r\n*/"));
            IPS_SetEventCyclic($eid, 3, 1, 127, 0, 0, 0);
        }
        $Event = IPS_GetEvent($eid);

        if ($Event['EventActive'] != $Alarm->Enabled) {
            IPS_SetEventActive($eid, $Alarm->Enabled);
        }

        $IpsDow = $Alarm->DowToIps();
        if ($Event['CyclicDateDay'] != $IpsDow) {
            IPS_SetEventCyclic($eid, 3, 1, $IpsDow, 0, 0, 0);
        }

        $IpsTime = $Alarm->TimeToArray();
        $Update = false;
        foreach ($Event['CyclicTimeFrom'] as $Key => $Value) {
            if ($Value != $IpsTime[$Key]) {
                $Update = true;
            }
        }
        if ($Update) {
            IPS_SetEventCyclicTimeFrom($eid, $IpsTime['Hour'], $IpsTime['Minute'], $IpsTime['Second']);
        }

        $this->RegisterMessage($eid, EM_CHANGEACTIVE);
        $this->RegisterMessage($eid, EM_CHANGECYCLIC);
        $this->RegisterMessage($eid, EM_CHANGECYCLICTIMEFROM);

        $PlaylistItem = $this->GetPlaylistItemFromUrl($Alarm->Url);
        if (!is_null($PlaylistItem)) {
            if ($this->RegisterVariableString(
                'AlarmPlaylistName' . $Alarm->Index,
                sprintf($this->Translate('Alarm %d playlist'), $Alarm->Index + 1),
                [],
                (($Alarm->Index + 1) * 10) + 6
            )) {
                IPS_SetIcon($this->FindIDForIdent('AlarmPlaylistName' . $Alarm->Index), 'list-music');
            }
            $this->SetValueString('AlarmPlaylistName' . $Alarm->Index, $PlaylistItem['Title']);
            $this->RefreshPlaylist($Alarm->Index, $PlaylistItem['Index']);
        }
        if ($this->RegisterVariableInteger(
            'AlarmShuffle' . $Alarm->Index,
            sprintf($this->Translate('Alarm %d playlist shuffle'), $Alarm->Index + 1),
            [
                \SqueezeBox\Presentation::Icon         => 'Shuffle',
                \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_ENUMERATION,
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
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => 'list-music',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 2,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('Album'),
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => 'album',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ]
                    ]
                )
            ],
            (($Alarm->Index + 1) * 10) + 5
        )) {
            $this->EnableAction('AlarmShuffle' . $Alarm->Index);
        }
        $this->SetValueInteger('AlarmShuffle' . $Alarm->Index, $Alarm->Shufflemode);

        // @todo Profil ~Repeat bleibt noch
        if ($this->RegisterVariableInteger(
            'AlarmRepeat' . $Alarm->Index,
            sprintf($this->Translate('Alarm %d repeat'), $Alarm->Index + 1),
            '~Repeat',
            (($Alarm->Index + 1) * 10) + 4
        )) {
            $this->EnableAction('AlarmRepeat' . $Alarm->Index);
            IPS_SetIcon($this->FindIDForIdent('AlarmRepeat' . $Alarm->Index), 'Repeat');
        }
        $this->SetValueInteger('AlarmRepeat' . $Alarm->Index, (int) $Alarm->Repeat);

        if ($this->RegisterVariableInteger(
            'AlarmVolume' . $Alarm->Index,
            sprintf($this->Translate('Alarm %d volume'), $Alarm->Index + 1),
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
            (($Alarm->Index + 1) * 10) + 3
        )) {
            $this->EnableAction('AlarmVolume' . $Alarm->Index);
        }
        $this->SetValueInteger('AlarmVolume' . $Alarm->Index, $Alarm->Volume);

        if ($this->RegisterVariableInteger(
            'AlarmState' . $Alarm->Index,
            sprintf($this->Translate('Alarm %d state'), $Alarm->Index + 1),
            [
                \SqueezeBox\Presentation::Icon         => 'alarm-clock',
                \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_ENUMERATION,
                \SqueezeBox\Presentation\Enum::Options => json_encode(
                    [
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 0,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('end'),
                            \SqueezeBox\Presentation\Enum::IconActive => true,
                            \SqueezeBox\Presentation\Enum::Icon       => 'alarm-clock',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 1,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('snooze'),
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => 'alarm-snooze',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Enum::Value      => 2,
                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('sounding'),
                            \SqueezeBox\Presentation\Enum::IconActive => false,
                            \SqueezeBox\Presentation\Enum::Icon       => 'alarm-exclamation',
                            \SqueezeBox\Presentation\Enum::Color      => -1
                        ]
                    ]
                )
            ],
            (($Alarm->Index + 1) * 10) + 2
        )) {
            $this->EnableAction('AlarmState' . $Alarm->Index);
        }
    }

    /**
     * RefreshEvents
     * Aktualisiert alle Statusvariablen bei Veränderung und löscht u.U. Statusvariablen.
     *
     * @param LSA_AlarmList $Alarms Die komplette Alarmliste.
     * @return void
     */
    private function RefreshEvents(LSA_AlarmList $Alarms): void
    {
        $Index = 0;
        foreach ($Alarms->Items as $Alarm) {
            /** @var LSA_Alarm $Alarm  */
            $Index = ($Index < $Alarm->Index) ? $Alarm->Index : $Index;
            $this->RefreshEvent($Alarm);
        }

        for ($i = $Index + 1; $i < 10; $i++) {
            $delete = $this->ReadPropertyBoolean(\SqueezeBox\Alarm\Property::DynamicDisplay);
            $eid = $this->FindIDForIdent('AlarmTime' . $i);
            if ($eid > 0) {
                $this->UnregisterMessage($eid, EM_CHANGEACTIVE);
                $this->UnregisterMessage($eid, EM_CHANGECYCLIC);
                $this->UnregisterMessage($eid, EM_CHANGECYCLICTIMEFROM);
                if ($delete) {
                    IPS_DeleteEvent($eid);
                } else {
                    IPS_SetEventCyclic($eid, 3, 1, 0, 0, 0, 0);
                    IPS_SetEventCyclicTimeFrom($eid, 0, 0, 0);
                    IPS_SetEventActive($eid, false);
                }
            }

            $vid = $this->FindIDForIdent('AlarmPlaylistName' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueString('AlarmPlaylistName' . $i, '');
                }
            }

            $vid = $this->FindIDForIdent('AlarmShuffle' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueInteger('AlarmShuffle' . $i, 0);
                }
            }

            $vid = $this->FindIDForIdent('AlarmRepeat' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueBoolean('AlarmRepeat' . $i, false);
                }
            }

            $vid = $this->FindIDForIdent('AlarmVolume' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueInteger('AlarmVolume' . $i, 0);
                }
            }

            $vid = $this->FindIDForIdent('AlarmState' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueInteger('AlarmState' . $i, 0);
                }
            }

            $vid = $this->FindIDForIdent('AlarmHTMLPlaylist' . $i);
            if ($vid > 0) {
                if ($this->ReadPropertyBoolean(\SqueezeBox\Alarm\Property::ShowAlarmHTMLPlaylist)) {
                    if ($delete) {
                        IPS_DeleteVariable($vid);
                    } else {
                        $this->SetValueString('AlarmHTMLPlaylist' . $i, '');
                    }
                } else {
                    IPS_DeleteVariable($vid);
                }
            }
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
        if ($LMSData->Data[0] == '?') {
            return false;
        }
        switch ($LMSData->Command[0]) {
            case 'alarm':
                switch ($LMSData->Command[1]) {
                    case 'enableall':
                        $this->SetValueBoolean('EnableAll', true);
                        break;
                    case 'disableall':
                        $this->SetValueBoolean('EnableAll', false);
                        break;
                    case 'defaultvolume':
                        $Data = new \SqueezeBox\LMSTaggingArray($LMSData->Data);
                        $this->SetValueInteger('DefaultVolume', $Data->DataArray()['Volume']);
                        break;
                    case 'add':
                        if (count($this->Alarms->Items) > 9) {
                            break;
                        }
                        $Data = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, ''))->DataArray();
                        $this->SendDebug('ADD', $Data, 0);
                        $Alarms = $this->Alarms;
                        $Alarm = new LSA_Alarm($Data);
                        $Alarm->Volume = $this->GetValue('DefaultVolume');
                        $Alarm->Index = $Alarms->Add($Alarm);
                        $this->Alarms = $Alarms;
                        $this->RefreshDeleteVariable(count($Alarms->Items));
                        $this->RefreshEvent($Alarm);
                        break;
                    case 'delete':
                        $Data = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, ''))->DataArray();
                        $this->SendDebug('DELETE', $Data, 0);
                        $Alarms = $this->Alarms;
                        $Alarms->Remove($Data['Id']);
                        $this->Alarms = $Alarms;
                        $AlarmIndex = count(value: $Alarms->Items);
                        $this->RefreshDeleteVariable($AlarmIndex);
                        $delete = $this->ReadPropertyBoolean(\SqueezeBox\Alarm\Property::DynamicDisplay);
                        $eid = $this->FindIDForIdent('AlarmTime' . $AlarmIndex);
                        if ($eid > 0) {
                            $this->UnregisterMessage($eid, EM_CHANGEACTIVE);
                            $this->UnregisterMessage($eid, EM_CHANGECYCLIC);
                            $this->UnregisterMessage($eid, EM_CHANGECYCLICTIMEFROM);
                            if ($delete) {
                                IPS_DeleteEvent($eid);
                            } else {
                                IPS_SetEventCyclic($eid, 3, 1, 0, 0, 0, 0);
                                IPS_SetEventCyclicTimeFrom($eid, 0, 0, 0);
                                IPS_SetEventActive($eid, false);
                            }
                        }

                        $vid = $this->FindIDForIdent('AlarmPlaylistName' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueString('AlarmPlaylistName' . $AlarmIndex, '');
                            }
                        }

                        $vid = $this->FindIDForIdent('AlarmShuffle' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueInteger('AlarmShuffle' . $AlarmIndex, 0);
                            }
                        }

                        $vid = $this->FindIDForIdent('AlarmRepeat' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueBoolean('AlarmRepeat' . $AlarmIndex, false);
                            }
                        }

                        $vid = $this->FindIDForIdent('AlarmVolume' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueInteger('AlarmVolume' . $AlarmIndex, 0);
                            }
                        }

                        $vid = $this->FindIDForIdent('AlarmState' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueInteger('AlarmState' . $AlarmIndex, 0);
                            }
                        }

                        $vid = $this->FindIDForIdent('AlarmPlaylist' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($this->ReadPropertyBoolean(\SqueezeBox\Alarm\Property::ShowAlarmHTMLPlaylist)) {
                                if ($delete) {
                                    IPS_DeleteVariable($vid);
                                } else {
                                    $this->SetValueString('AlarmPlaylist' . $AlarmIndex, '');
                                }
                            } else {
                                IPS_DeleteVariable($vid);
                            }
                        }
                        $this->RefreshEvents($Alarms);
                        break;
                    case 'update':
                        $Data = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, ''))->DataArray();
                        $this->SendDebug('UPDATE', $Data, 0);
                        $Alarms = $this->Alarms;
                        $Alarm = $Alarms->GetById($Data['Id']);
                        if ($Alarm === false) {
                            break;
                        }
                        if (array_key_exists('DowAdd', $Data)) {
                            $Alarm->AddDow($Data['DowAdd']);
                        }
                        if (array_key_exists('DowDel', $Data)) {
                            $Alarm->DelDow($Data['DowDel']);
                        }
                        if (array_key_exists('Dow', $Data)) {
                            $Alarm->Dow = $Data['Dow'];
                        }
                        if (array_key_exists('Volume', $Data)) {
                            $Alarm->Volume = $Data['Volume'];
                        }
                        if (array_key_exists('Time', $Data)) {
                            $Alarm->Time = $Data['Time'];
                        }
                        if (array_key_exists('Enabled', $Data)) {
                            $Alarm->Enabled = $Data['Enabled'];
                        }
                        if (array_key_exists('Repeat', $Data)) {
                            $Alarm->Repeat = $Data['Repeat'];
                        }
                        if (array_key_exists('Playlisturl', $Data)) {
                            if ($Data['Playlisturl'] == '0') {
                                $Data['Playlisturl'] = 'CURRENT_PLAYLIST';
                            }
                            $Alarm->Url = $Data['Playlisturl'];
                        }
                        if (array_key_exists('Shufflemode', $Data)) {
                            $Alarm->Shufflemode = $Data['Shufflemode'];
                        }
                        $this->Alarms = $Alarms;
                        $this->RefreshEvent($Alarm);
                        break;
                    case 'sound':
                    case 'snooze_end':
                        $State = 2;
                        // Refresh folgt unten
                        // No break. Add additional comment above this line if intentional
                    case 'end':
                        if (!isset($State)) {
                            $State = 0;
                        }
                        // Refresh folgt unten
                        // No break. Add additional comment above this line if intentional
                    case 'snooze':
                        if (!isset($State)) {
                            $State = 1;
                        }
                        $Alarms = $this->Alarms;
                        $Alarm = $Alarms->GetById($LMSData->Data[0]);
                        if ($Alarm === false) {
                            break;
                        }
                        if ($this->RegisterVariableInteger(
                            'AlarmState' . $Alarm->Index,
                            sprintf($this->Translate('Alarm %d state'), $Alarm->Index + 1),
                            [
                                \SqueezeBox\Presentation::Icon         => 'alarm-clock',
                                \SqueezeBox\Presentation::Type         => VARIABLE_PRESENTATION_ENUMERATION,
                                \SqueezeBox\Presentation\Enum::Options => json_encode(
                                    [
                                        [
                                            \SqueezeBox\Presentation\Enum::Value      => 0,
                                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('end'),
                                            \SqueezeBox\Presentation\Enum::IconActive => true,
                                            \SqueezeBox\Presentation\Enum::Icon       => 'alarm-clock',
                                            \SqueezeBox\Presentation\Enum::Color      => -1
                                        ],
                                        [
                                            \SqueezeBox\Presentation\Enum::Value      => 1,
                                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('snooze'),
                                            \SqueezeBox\Presentation\Enum::IconActive => false,
                                            \SqueezeBox\Presentation\Enum::Icon       => 'alarm-snooze',
                                            \SqueezeBox\Presentation\Enum::Color      => -1
                                        ],
                                        [
                                            \SqueezeBox\Presentation\Enum::Value      => 2,
                                            \SqueezeBox\Presentation\Enum::Caption    => $this->Translate('sounding'),
                                            \SqueezeBox\Presentation\Enum::IconActive => false,
                                            \SqueezeBox\Presentation\Enum::Icon       => 'alarm-exclamation',
                                            \SqueezeBox\Presentation\Enum::Color      => -1
                                        ]
                                    ]
                                )
                            ],
                            (($Alarm->Index + 1) * 10) + 2
                        )) {
                            $this->EnableAction('AlarmState' . $Alarm->Index);
                        }
                        $this->SetValueInteger('AlarmState' . $Alarm->Index, $State);
                        break;
                }
                break;
            case 'prefset':
                $LMSData->Command[1] = $LMSData->Command[2];
                unset($LMSData->Command[2]);
                // No break. Add additional comment above this line if intentional
            case 'playerpref':
                switch ($LMSData->Command[1]) {
                    case 'alarmfadeseconds':
                        $this->SetValueBoolean('FadeIn', (bool) $LMSData->Data[0]);
                        break;
                    case 'alarmDefaultVolume':
                        $this->SetValueInteger('DefaultVolume', (int) $LMSData->Data[0]);
                        break;
                    case 'alarmTimeoutSeconds':
                        $this->SetValueInteger('Timeout', (int) $LMSData->Data[0]);
                        break;
                    case 'alarmSnoozeSeconds':
                        $this->SetValueInteger('SnoozeSeconds', (int) $LMSData->Data[0]);
                        break;
                    case 'alarmsEnabled':
                        $this->SetValueBoolean('EnableAll', (bool) $LMSData->Data[0]);
                        break;
                }
                break;
            case 'alarms':
                $this->SendDebug('Decode', $LMSData, 0);
                $ReadAlarmData = (new \SqueezeBox\LMSTaggingArray($LMSData->Data))->DataArray();
                if (array_key_exists('Count', $ReadAlarmData)) {
                    $this->SendDebug('AlarmData', 'EMPTY', 0);
                    $this->Alarms = new LSA_AlarmList([]);
                } else {
                    $this->SendDebug('AlarmData', $ReadAlarmData, 0);
                    $this->Alarms = new LSA_AlarmList($ReadAlarmData);
                }
                $this->RefreshDeleteVariable(count($this->Alarms->Items));
                $this->RefreshEvents($this->Alarms);
                break;
            case 'client':
                if (($LMSData->Data[0] == 'new') || ($LMSData->Data[0] == 'reconnect')) {
                    $this->SetStatus(IS_ACTIVE);
                    $this->LoadAlarmPlaylists();
                    $this->RequestAllState();
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
     * @return ?\SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function Send(\SqueezeBox\LMSData $LMSData): ?\SqueezeBox\LMSData
    {
        if ($this->ReadPropertyString(\SqueezeBox\Alarm\Property::Address) == '') {
            return null;
        }
        try {
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
        } catch (Exception $exc) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            restore_error_handler();
            return null;
        }
        $LMSData->Address = $this->ReadPropertyString(\SqueezeBox\Alarm\Property::Address);
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
        if ($this->ReadPropertyString(\SqueezeBox\Alarm\Property::Address) == '') {
            return null;
        }
        try {
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
        } catch (Exception $ex) {
            $this->SendDebug('Receive Direct', $ex->getMessage(), 0);
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($ex->getMessage(), $ex->getCode());
            restore_error_handler();
            return null;
        }
        $LMSData->Address = $this->ReadPropertyString(\SqueezeBox\Alarm\Property::Address);
        $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $IoID = IPS_GetInstance($SplitterID)['ConnectionID'];
        $Host = IPS_GetProperty($IoID, \SqueezeBox\IO\Property::Host);
        if ($Host === '') {
            return null;
        }
        return $this->SendDirectToLMS(
            gethostbyname($Host),
            IPS_GetProperty($SplitterID, \SqueezeBox\Splitter\Property::Port),
            IPS_GetProperty($SplitterID, \SqueezeBox\Splitter\Property::Username),
            IPS_GetProperty($SplitterID, \SqueezeBox\Splitter\Property::Password),
            $LMSData
        );
    }

}
