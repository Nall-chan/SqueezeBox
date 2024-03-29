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
 * @version       3.80
 *
 */

require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen
require_once __DIR__ . '/../libs/SqueezeBoxClass.php';  // diverse Klassen
eval('declare(strict_types=1);namespace SqueezeboxAlarm {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxAlarm {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxAlarm {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxAlarm {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxAlarm {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');

/**
 * Enthält die Daten eines Alarm.
 */
class LSA_Alarm
{
    /**
     * Id des Alarm.
     *
     * @var string
     */
    public $Id;

    /**
     * Index des Alarm.
     *
     * @var int
     */
    public $Index;

    /**
     * Tage des Alarm.
     *
     * @var string
     */
    public $Dow = '0,1,2,3,4,5,6';

    /**
     * Status des Alarm.
     *
     * @var bool
     */
    public $Enabled = true;

    /**
     * Wiederholung des Alarm.
     *
     * @var bool
     */
    public $Repeat = true;

    /**
     * Shuffle des Alarm.
     *
     * @var int
     */
    public $Shufflemode = 0;

    /**
     * Uhrzeit des Alarm.
     *
     * @var int
     */
    public $Time;

    /**
     * Lautstärke des Alarm.
     *
     * @var int
     */
    public $Volume;

    /**
     * Playlist des Alarm.
     *
     * @var string
     */
    public $Url = 'CURRENT_PLAYLIST';

    /**
     * Erzeugt aus den übergeben Daten einen neuen LSA_Alarm.
     *
     * @param array $Alarm Das Array wie es von LMSTaggingArray erzeugt wird.
     * @param int   $Index Der fortlaufende Index dieses Weckers.
     *
     * @return LSA_Alarm
     */
    public function __construct(array $Alarm = null, int $Index = null)
    {
        if (is_null($Alarm)) {
            return;
        }
        foreach ($Alarm as $Key => $Value) {
            $this->{$Key} = $Value;
        }
        $this->Index = $Index;
    }

    /**
     * Liefert die Daten welche behalten werden müssen.
     */
    public function __sleep()
    {
        return ['Id', 'Index', 'Dow', 'Enabled', 'Repeat', 'Time', 'Volume', 'Url', 'Shufflemode'];
    }

    /**
     * Schreibt die Wochentag aus dem IPS Format in die Eigenschaft Dow.
     *
     * @param int $Days Die Wochentag im IPS-Format.
     */
    public function IpsToDow(int $Days)
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
     * Liefert die Eigenschaft Dow im IPS-Format.
     *
     * @return int Die Wochentage im IPS-Format.
     */
    public function DowToIps()
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
     * Fügt einen Wochentag zum Wecker hinzu.
     *
     * @param int $Dow Ein Wochentag.
     */
    public function AddDow(int $Dow)
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
     * Löscht einen Wochentag aus dem Wecker.
     *
     * @param int $Dow Ein Wochentag.
     */
    public function DelDow(int $Dow)
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
     * Liefert die Alarmzeit als Array für die Ereignisse in IPS.
     *
     * @return array
     */
    public function TimeToArray()
    {
        $result['Second'] = (int) gmdate('s', $this->Time);
        $result['Minute'] = (int) gmdate('i', $this->Time);
        $result['Hour'] = (int) gmdate('G', $this->Time);
        return $result;
    }

    /**
     * Schreibt die Alarmzeit aus dem übergeben Array in die Eigenschaft Time.
     *
     * @param array $Time
     */
    public function ArrayToTime($Time)
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
     * @var array
     */
    public $Items = [];

    /**
     * Erzeugt eine neue LSA_AlarmList aus einem Array von LMSTaggingArray mit allen Alarmen.
     *
     * @param array $Alarms Alle Wecker oder null.
     *
     * @return LSA_AlarmList
     */
    public function __construct(array $Alarms = null)
    {
        if (is_null($Alarms)) {
            return;
        }
        foreach ($Alarms as $Index => $AlarmData) {
            $Alarm = new LSA_Alarm($AlarmData, $Index);
            $this->Items[$Alarm->Id] = $Alarm;
        }
    }

    /**
     * Liefert die Daten welche behalten werden müssen.
     */
    public function __sleep()
    {
        return ['Items'];
    }

    /**
     * Fügt einen LSA_Alarm in $Items hinzu.
     *
     * @param LSA_Alarm $Alarm Das neue Objekt.
     *
     * @return int Der Index des Items.
     */
    public function Add(LSA_Alarm $Alarm)
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
     * Update für einen LSA_Alarm in $Items.
     *
     * @param LSA_Alarm $Alarm Das neue Objekt.
     */
    public function Update(LSA_Alarm $Alarm)
    {
        $this->Items[$Alarm->Id] = $Alarm;
    }

    /**
     * Löscht einen LSA_Alarm aus $Items.
     *
     * @param string $AlarmId Der Index des zu löschenden Items.
     */
    public function Remove(string $AlarmId)
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
     * Liefert einen bestimmten LSA_Alarm aus den Items anhand der Id.
     *
     * @param string $AlarmId
     *
     * @return LSA_Alarm $Alarm
     */
    public function GetById(string $AlarmId)
    {
        if (!isset($this->Items[$AlarmId])) {
            return false;
        }
        return $this->Items[$AlarmId];
    }

    /**
     * Liefert einen bestimmten LSA_Alarm aus den Items anhand des Index.
     *
     * @param int $Index
     *
     * @return LSA_Alarm $Alarm
     */
    public function GetByIndex(int $Index)
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
 * SqueezeboxAlarm Klasse für die Wecker einer SqueezeBox als Instanz in IPS.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.80
 *
 * @example <b>Ohne</b>
 *
 * @property int $ParentID
 * @property array $Multi_Playlist Alle Datensätze der Alarm-Playlisten.
 * @property LSA_AlarmList $Alarms Alle Wecker als Objekt.
 * @property resource|false $Socket
 * @method void RegisterHook(string $WebHook)
 * @method void UnregisterHook(string $WebHook)
 * @method void SetValueBoolean(string $Ident, bool $value)
 * @method void SetValueFloat(string $Ident, float $value)
 * @method void SetValueInteger(string $Ident, int $value)
 * @method void SetValueString(string $Ident, string $value)
 * @method void RegisterProfileIntegerEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, int $MaxValue = -1, float $StepSize = 0)
 * @method void RegisterProfileInteger(string $Name, string $Icon, string $Prefix, string $Suffix, int $MinValue, int $MaxValue, int $StepSize)
 * @method void UnregisterProfile(string $Name)
 * @method string GetTable(array $Data, string $HookPrefix, string $HookType, string $HookId, int $CurrentLine = -1)
 */
class SqueezeboxAlarm extends IPSModule
{
    use \SqueezeboxAlarm\VariableProfileHelper,
        \SqueezeBox\LMSHTMLTable,
        \SqueezeBox\DebugHelper,
        \SqueezeboxAlarm\BufferHelper,
        \SqueezeboxAlarm\InstanceStatus,
        \SqueezeboxAlarm\VariableHelper,
        \SqueezeboxAlarm\WebhookHelper {
            \SqueezeboxAlarm\InstanceStatus::MessageSink as IOMessageSink;
            \SqueezeboxAlarm\InstanceStatus::RegisterParent as IORegisterParent;
            \SqueezeboxAlarm\InstanceStatus::RequestAction as IORequestAction;
        }

    private $Socket = false;

    /**
     * Destruktor
     * schließt bei Bedarf den noch offenen TCP-Socket.
     */
    public function __destruct()
    {
        if ($this->Socket) {
            fclose($this->Socket);
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{96A9AB3A-2538-42C5-A130-FC34205A706A}');
        $this->SetReceiveDataFilter('.*"Address":"","Command":\["(alarm.*|client".*|playerpref","alarm.*|prefset","server","alarm.*)');
        $this->RegisterPropertyString('Address', '');
        $this->RegisterPropertyBoolean('dynamicDisplay', false);
        $this->RegisterPropertyBoolean('showAdd', true);
        $this->RegisterPropertyBoolean('showDelete', true);
        $this->RegisterPropertyBoolean('showAlarmPlaylist', true);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString('Table', json_encode($Style['Table']));
        $this->RegisterPropertyString('Columns', json_encode($Style['Columns']));
        $this->RegisterPropertyString('Rows', json_encode($Style['Rows']));

        $this->Multi_Playlist = [];
        $this->Alarms = new LSA_AlarmList([]);
        $this->ParentID = 0;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook('/hook/LSAPlaylist' . $this->InstanceID);
            $this->DeleteProfile();
        }
        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
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
        $Address = $this->ReadPropertyString('Address');
        $changeAddress = false;
        //ip Adresse:
        if (preg_match('/\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b/', $Address) !== 1) {
            // Keine IP Adresse
            if (strlen($Address) == 12) {
                $Address = strtolower(implode(':', str_split($Address, 2)));
                $changeAddress = true;
            }
            if (preg_match('/^([0-9A-Fa-f]{2}[-]){5}([0-9A-Fa-f]{2})$/', $Address) === 1) {
                $Address = strtolower(str_replace('-', ':', $Address));
                $changeAddress = true;
            }
            if ($Address != strtolower($Address)) {
                $Address = strtolower($Address);
                $changeAddress = true;
            }
        }
        if ($changeAddress) {
            IPS_SetProperty($this->InstanceID, 'Address', $Address);
            IPS_ApplyChanges($this->InstanceID);
            return;
        }

        // Adresse als Filter setzen
        $this->SetReceiveDataFilter('.*"Address":"' . $Address . '","Command":\["(alarm.*|client".*|playerpref","alarm.*|prefset","server","alarm.*)');

        $this->SetSummary($Address);

        // Profile anlegen
        $this->RegisterProfileInteger('LSA.Intensity', 'Intensity', '', ' %', 0, 100, 1);
        $this->RegisterProfileInteger('LSA.Timeout', 'Clock', '', $this->Translate(' sec'), 0, 600, 1);
        $this->RegisterProfileInteger('LSA.Snooze', 'Clock', '', $this->Translate(' sec'), 0, 1800, 1);
        $this->RegisterProfileIntegerEx('LSA.Shuffle', 'Shuffle', '', '', [
            [0, $this->Translate('Off'), '', -1],
            [1, $this->Translate('Title'), '', -1],
            [2, 'Album', '', -1]
        ]);
        $this->RegisterProfileIntegerEx('LSA.Add', 'Bell', '', '', [
            [0, $this->Translate('Add'), '', -1]
        ]);
        $this->RegisterProfileIntegerEx('LSA.State', 'Bell', '', '', [
            [0, $this->Translate('end'), '', -1],
            [1, $this->Translate('snooze'), '', -1],
            [2, $this->Translate('sounding'), '', -1]
        ]);
        $this->RefreshDeleteProfil(0);

        //Status-Variablen anlegen
        $this->RegisterVariableBoolean('EnableAll', $this->Translate('All alarms active'), '~Switch', 1);
        $this->EnableAction('EnableAll');
        $this->RegisterVariableInteger('DefaultVolume', $this->Translate('Default alarm volume'), 'LSA.Intensity', 2);
        $this->EnableAction('DefaultVolume');
        $this->RegisterVariableBoolean('FadeIn', $this->Translate('Fade alarm'), '~Switch', 3);
        $this->EnableAction('FadeIn');
        $this->RegisterVariableInteger('Timeout', $this->Translate('Automatically stop'), 'LSA.Timeout', 4);
        $this->EnableAction('Timeout');
        $this->RegisterVariableInteger('SnoozeSeconds', $this->Translate('Snoozetime'), 'LSA.Snooze', 4);
        $this->EnableAction('SnoozeSeconds');

        if ($this->ReadPropertyBoolean('showAdd')) {
            $this->RegisterVariableInteger('AddAlarm', $this->Translate('Add alarm'), 'LSA.Add', 4);
            $this->EnableAction('AddAlarm');
        } else {
            $this->UnregisterVariable('AddAlarm');
        }

        if ($this->ReadPropertyBoolean('showDelete')) {
            $this->RegisterVariableInteger('DelAlarm', $this->Translate('Delete alarm'), 'LSA.Del.' . $this->InstanceID, 5);
            $this->EnableAction('DelAlarm');
        } else {
            $this->UnregisterVariable('DelAlarm');
            $this->UnregisterProfile('LSA.Del.' . $this->InstanceID);
        }

        if (!$this->ReadPropertyBoolean('showAlarmPlaylist')) {
            for ($AlarmIndex = 0; $AlarmIndex < 10; $AlarmIndex++) {
                $vid = @$this->GetIDForIdent('AlarmPlaylist' . $AlarmIndex);
                if ($vid > 0) {
                    IPS_DeleteVariable($vid);
                }
            }
        }

        // Wenn Kernel nicht bereit, dann warten... wenn unser IO Aktiv wird, holen wir unsere Daten :)
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyBoolean('showAlarmPlaylist')) {
            $this->RegisterHook('/hook/LSAPlaylist' . $this->InstanceID);
        } else {
            $this->UnregisterHook('/hook/LSAPlaylist' . $this->InstanceID);
        }
        $this->RegisterParent();
        // Wenn Parent aktiv, dann Anmeldung an der Hardware bzw. Datenabgleich starten
        if ($this->HasActiveParent()) {
            $this->IOChangeState(IS_ACTIVE);
        }
    }

    /**
     * Interne Funktion des SDK.
     *
     *
     * @param int $TimeStamp
     * @param int $SenderID
     * @param int $Message
     * @param array $Data
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
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

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'LSA_RequestAllState'.
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen.
     *
     * @return bool True wenn alle Abfragen erfolgreich waren, sonst false.
     */
    public function RequestAllState()
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
     * IPS-Instanz-Funktion 'LSA_RequestState'.
     * Fragt einen Wert der Alarme ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @param string $Ident Der Ident der abzufragenden Statusvariable.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function RequestState(string $Ident)
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
     * IPS-Instanz-Funktion 'LSA_SetAllActive'.
     * De/Aktiviert die Wecker-Funktionen des Gerätes.
     *
     * @param bool $Value De/Aktiviert die Wecker.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetAllActive(bool $Value)
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['alarm', ($Value ? 'enableall' : 'disableall')]));
        if ($LMSData === null) {
            return false;
        }
        return $LMSData->Command[1] == ($Value ? 'enableall' : 'disableall');
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetDefaultVolume'.
     * Setzt die Standard-Lautstärke für neue Wecker.
     *
     * @param int $Value Die neue Lautstärke
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetDefaultVolume(int $Value)
    {
        if (($Value < 0) || ($Value > 100)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playerpref', 'alarmDefaultVolume'], (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) ($LMSData->Data[0]) == (int) $Value;
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetFadeIn'.
     * De/Aktiviert das Einblenden der Wiedergabe.
     *
     * @param bool $Value True zum aktivieren, False zum deaktivieren.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetFadeIn(bool $Value)
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playerpref', 'alarmfadeseconds'], (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) ($LMSData->Data[0]) == (int) $Value;
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetTimeout'.
     * Setzt die Zeit in Sekunden bis ein Wecker automatisch beendend wird.
     *
     * @param int $Value Zeit in Sekunden bis zum abschalten.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetTimeout(int $Value)
    {
        if ($Value < 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playerpref', 'alarmTimeoutSeconds'], (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) ($LMSData->Data[0]) == (int) $Value;
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetSnoozeSeconds'.
     * Setzt die Schlummerzeit in Sekunden.
     *
     * @param int $Value Die Schlummerzeit in Sekunden.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetSnoozeSeconds(int $Value)
    {
        if ($Value < 0) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['playerpref', 'alarmSnoozeSeconds'], (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return (int) ($LMSData->Data[0]) == (int) $Value;
    }

    /**
     * IPS-Instanz-Funktion 'LSA_AlarmSnooze'.
     * Sendet das Schlummersignal an das Gerät und pausiert somit einen aktiven Alarm.
     *
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function AlarmSnooze()
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['button'], ['snooze.single']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * IPS-Instanz-Funktion 'LSA_AlarmStop'.
     * Beendet ein aktiven Alarm.
     *
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function AlarmStop()
    {
        $LMSData = $this->SendDirect(new \SqueezeBox\LMSData(['button'], ['power_off']));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * IPS-Instanz-Funktion 'LSA_AddAlarm'.
     * Fragt einen Wert der Alarme ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @return bool|int Index des Weckers, im Fehlerfall false.
     */
    public function AddAlarm()
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
     * IPS-Instanz-Funktion 'LSA_DelAlarm'.
     * Löscht einen Wecker.
     *
     * @param int $AlarmIndex Der Index des zu löschenden Weckers.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function DelAlarm(int $AlarmIndex)
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
     * IPS-Instanz-Funktion 'LSA_SetPlaylist'.
     * Setzt die Playlist bzw. die Wiedergabe für den Wecker.
     *
     * @param int    $AlarmIndex Der Index des Weckers.
     * @param string $Url        Die wiederzugebene URL.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetPlaylist(int $AlarmIndex, string $Url)
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
     * IPS-Instanz-Funktion 'LSA_SetShuffle'.
     * Setzt den Modus der zufälligen Wiedergabe des Weckers.
     *
     * @param int    $AlarmIndex Der Index des Weckers.
     * @param string $Value      Der neue Modus.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetShuffle(int $AlarmIndex, int $Value)
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
     * IPS-Instanz-Funktion 'LSA_SetRepeat'.
     * De/Aktiviert die Wiederholung.
     *
     * @param int  $AlarmIndex Der Index des Weckers.
     * @param bool $Value      De/Aktiviert die Wiederholung.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetRepeat(int $AlarmIndex, bool $Value)
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
     * IPS-Instanz-Funktion 'LSA_SetVolume'.
     * Setzt die Lautstärke des Weckers.
     *
     * @param int $AlarmIndex Der Index des Weckers.
     * @param int $Value      Die neue Lautstärke des Weckers.
     * @return bool True wenn erfolgreich, sonst false.
     */
    public function SetVolume(int $AlarmIndex, int $Value)
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

    //################# ActionHandler

    /**
     * Interne Funktion des SDK.
     */
    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return;
        }
        switch ($Ident) {
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

    //################# DataPoints Ankommend von Parent-LMS-Splitter

    /**
     * Interne Funktion des SDK.
     */
    public function ReceiveData($JSONString)
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
        if ($State == IS_ACTIVE) {
            $this->LoadAlarmPlaylists();
            $this->RequestAllState();
        }
    }

    /**
     * Verarbeitet Daten aus dem Webhook.
     *
     * @param string $ID Die ausgewählte Alarm-Playlist als URL.
     *
     * @global array $_GET
     */
    protected function ProcessHookdata()
    {
        if ((!isset($_GET['ID'])) || (!isset($_GET['Type'])) || (!isset($_GET['Secret']))) {
            echo $this->Translate('Bad Request');
            return;
        }
        $AlarmIndex = substr($_GET['Type'], -1);
        $MySecret = $this->{'WebHookSecretAlarmPlaylist' . $AlarmIndex};
        $CalcSecret = base64_encode(sha1($MySecret . '0' . rawurldecode($_GET['ID']), true));

        if ($CalcSecret != rawurldecode($_GET['Secret'])) {
            echo $this->Translate('Access denied');
            return;
        }

        if (substr($_GET['Type'], 0, -1) != 'AlarmPlaylist') {
            echo $this->Translate('Bad Request');
            return;
        }

        if ($this->SetPlaylist((int) $AlarmIndex, rawurldecode($_GET['ID']))) {
            echo 'OK';
        }
    }

    /**
     * Konvertiert $Data zu einem String und versendet diesen direkt an den LMS.
     *
     * @param \SqueezeBox\LMSData $LMSData Zu versendende Daten.
     *
     * @return \SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    protected function SendDirect(\SqueezeBox\LMSData $LMSData)
    {
        try {
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }

            $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
            $IoID = IPS_GetInstance($SplitterID)['ConnectionID'];
            $Host = IPS_GetProperty($IoID, 'Host');
            if ($Host === '') {
                return null;
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
        } catch (Exception $ex) {
            $this->SendDebug('Receive Direct', $ex->getMessage(), 0);
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($ex->getMessage(), $ex->getCode());
            restore_error_handler();
        }
        return null;
    }

    protected function ModulErrorHandler($errno, $errstr)
    {
        if (!(error_reporting() & $errno)) {
            // Dieser Fehlercode ist nicht in error_reporting enthalten
            return true;
        }
        $this->SendDebug('ERROR', $errstr, 0);
        echo $errstr . "\r\n";
        return false;
    }

    //################# PRIVATE

    /**
     * Löscht die nicht mehr benötigten Profile.
     */
    private function DeleteProfile()
    {
        $this->UnregisterProfile('LSA.Intensity');
        $this->UnregisterProfile('LSA.Shuffle');
        $this->UnregisterProfile('LSA.Add');
        $this->UnregisterProfile('LSA.Del.' . $this->InstanceID);
    }

    /**
     * Liefert die Werkeinstellungen für die Eigenschaften Html, Table und Rows.
     *
     * @return array
     */
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
     * Erzeugt eine HTML-Tabelle mit allen Playlisten für eine ~HTMLBox-Variable.
     *
     * @param int $AlarmIndex    Der Index des Alarms.
     * @param int $PlaylistIndex Der Index der aktuell gewählten Alarm-Playlist
     */
    private function RefreshPlaylist(int $AlarmIndex, int $PlaylistIndex)
    {
        if (!$this->ReadPropertyBoolean('showAlarmPlaylist')) {
            return;
        }
        $vid = @$this->GetIDForIdent('AlarmPlaylist' . $AlarmIndex);
        if ($vid === false) {
            $vid = $this->RegisterVariableString('AlarmPlaylist' . $AlarmIndex, sprintf($this->Translate('Alarm %d playlist selection'), $AlarmIndex + 1), '~HTMLBox', (($AlarmIndex + 1) * 10) + 7);
            IPS_SetIcon($vid, 'Database');
        }

        $Data = $this->Multi_Playlist;
        if (!is_array($Data)) {
            $Data = [];
        }

        $HTML = $this->GetTable($Data, 'LSAPlaylist', 'AlarmPlaylist' . $AlarmIndex, 'Url', $PlaylistIndex + 1);
        $this->SetValueString('AlarmPlaylist' . $AlarmIndex, $HTML);
    }

    /**
     * Liefert einen eintrag aus den Alarm-Playlisten anhand der URL.
     *
     * @param string $Url Die URL des zu suchenden Eintrages.
     *
     * @return array Der gefundene Eintrag.
     */
    private function GetPlaylistItemFromUrl(string $Url)
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
     * Aktualisiert das Profil LSA.Del* mit der korrekten Anzahl der Alarme.
     *
     * @param int $Count Anzahl der Alarme.
     */
    private function RefreshDeleteProfil(int $Count)
    {
        $Assoziations = [];
        for ($index = 0; $index < $Count; $index++) {
            $Assoziations[] = [$index, (string) ($index + 1), '', -1];
        }
        $this->RegisterProfileIntegerEx('LSA.Del.' . $this->InstanceID, 'Cross', '', '', $Assoziations);
    }

    /**
     * Lädt alle für diesen Player gültige Alarm-Playlisten und speichert sie im Buffer.
     */
    private function LoadAlarmPlaylists()
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
     *  Erzeugt und Aktualisiert die Statusvariablen eines Alarms bei Veränderung.
     *
     * @param LSA_Alarm $Alarm Ein Alarm.
     */
    private function RefreshEvent(LSA_Alarm $Alarm)
    {
        $eid = @$this->GetIDForIdent('AlarmTime' . $Alarm->Index);
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
            $vid = @$this->GetIDForIdent('AlarmPlaylistName' . $Alarm->Index);
            if ($vid === false) {
                $vid = $this->RegisterVariableString('AlarmPlaylistName' . $Alarm->Index, sprintf($this->Translate('Alarm %d playlist'), $Alarm->Index + 1), '', (($Alarm->Index + 1) * 10) + 6);
                IPS_SetIcon($vid, 'Database');
            }
            $this->SetValueString('AlarmPlaylistName' . $Alarm->Index, $PlaylistItem['Title']);

            $this->RefreshPlaylist($Alarm->Index, $PlaylistItem['Index']);
        }
        if ($this->RegisterVariableInteger('AlarmShuffle' . $Alarm->Index, sprintf($this->Translate('Alarm %d playlist shuffle'), $Alarm->Index + 1), 'LSA.Shuffle', (($Alarm->Index + 1) * 10) + 5)) {
            $this->EnableAction('AlarmShuffle' . $Alarm->Index);
        }
        $this->SetValueInteger('AlarmShuffle' . $Alarm->Index, $Alarm->Shufflemode);

        $vid = @$this->GetIDForIdent('AlarmRepeat' . $Alarm->Index);
        if ($vid === false) {
            $vid = $this->RegisterVariableBoolean('AlarmRepeat' . $Alarm->Index, sprintf($this->Translate('Alarm %d repeat'), $Alarm->Index + 1), '~Switch', (($Alarm->Index + 1) * 10) + 4);
            $this->EnableAction('AlarmRepeat' . $Alarm->Index);
            IPS_SetIcon($vid, 'Repeat');
        }
        $this->SetValueInteger('AlarmRepeat' . $Alarm->Index, (int) $Alarm->Repeat);

        $vid = @$this->GetIDForIdent('AlarmVolume' . $Alarm->Index);
        if ($vid === false) {
            $this->RegisterVariableInteger('AlarmVolume' . $Alarm->Index, sprintf($this->Translate('Alarm %d volume'), $Alarm->Index + 1), 'LSA.Intensity', (($Alarm->Index + 1) * 10) + 3);
            $this->EnableAction('AlarmVolume' . $Alarm->Index);
        }
        $this->SetValueInteger('AlarmVolume' . $Alarm->Index, $Alarm->Volume);

        $vid = @$this->GetIDForIdent('AlarmState' . $Alarm->Index);
        if ($vid === false) {
            $this->RegisterVariableInteger('AlarmState' . $Alarm->Index, sprintf($this->Translate('Alarm %d state'), $Alarm->Index + 1), 'LSA.State', (($Alarm->Index + 1) * 10) + 2);
            $this->EnableAction('AlarmState' . $Alarm->Index);
        }
    }

    /**
     *  Aktualisiert alle Statusvariablen bei Veränderung und löscht u.U. Statusvariablen.
     *
     * @param LSA_AlarmList $Alarms Die komplette Alarmliste.
     */
    private function RefreshEvents(LSA_AlarmList $Alarms)
    {
        $Index = 0;
        foreach ($Alarms->Items as $Alarm) {
            /** @var LSA_Alarm $Alarm  */
            $Index = ($Index < $Alarm->Index) ? $Alarm->Index : $Index;
            $this->RefreshEvent($Alarm);
        }

        for ($i = $Index + 1; $i < 10; $i++) {
            $delete = $this->ReadPropertyBoolean('dynamicDisplay');
            $eid = @$this->GetIDForIdent('AlarmTime' . $i);
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

            $vid = @$this->GetIDForIdent('AlarmPlaylistName' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueString('AlarmPlaylistName' . $i, '');
                }
            }

            $vid = @$this->GetIDForIdent('AlarmShuffle' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueInteger('AlarmShuffle' . $i, 0);
                }
            }

            $vid = @$this->GetIDForIdent('AlarmRepeat' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueBoolean('AlarmRepeat' . $i, false);
                }
            }

            $vid = @$this->GetIDForIdent('AlarmVolume' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueInteger('AlarmVolume' . $i, 0);
                }
            }

            $vid = @$this->GetIDForIdent('AlarmState' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueInteger('AlarmState' . $i, 0);
                }
            }

            $vid = @$this->GetIDForIdent('AlarmPlaylist' . $i);
            if ($vid > 0) {
                if ($this->ReadPropertyBoolean('showAlarmPlaylist')) {
                    if ($delete) {
                        IPS_DeleteVariable($vid);
                    } else {
                        $this->SetValueString('AlarmPlaylist' . $i, '');
                    }
                } else {
                    IPS_DeleteVariable($vid);
                }
            }
        }
    }

    //################# Decode Data
    private function DecodeLMSResponse(\SqueezeBox\LMSData $LMSData)
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
                        $this->RefreshDeleteProfil(count($Alarms->Items));
                        $this->RefreshEvent($Alarm);
                        break;
                    case 'delete':
                        $Data = (new \SqueezeBox\LMSTaggingArray($LMSData->Data, ''))->DataArray();
                        $this->SendDebug('DELETE', $Data, 0);
                        $Alarms = $this->Alarms;
                        $Alarms->Remove($Data['Id']);
                        $this->Alarms = $Alarms;
                        $AlarmIndex = count($Alarms->Items);
                        $this->RefreshDeleteProfil($AlarmIndex);
                        $delete = $this->ReadPropertyBoolean('dynamicDisplay');
                        $eid = @$this->GetIDForIdent('AlarmTime' . $AlarmIndex);
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

                        $vid = @$this->GetIDForIdent('AlarmPlaylistName' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueString('AlarmPlaylistName' . $AlarmIndex, '');
                            }
                        }

                        $vid = @$this->GetIDForIdent('AlarmShuffle' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueInteger('AlarmShuffle' . $AlarmIndex, 0);
                            }
                        }

                        $vid = @$this->GetIDForIdent('AlarmRepeat' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueBoolean('AlarmRepeat' . $AlarmIndex, false);
                            }
                        }

                        $vid = @$this->GetIDForIdent('AlarmVolume' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueInteger('AlarmVolume' . $AlarmIndex, 0);
                            }
                        }

                        $vid = @$this->GetIDForIdent('AlarmState' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueInteger('AlarmState' . $AlarmIndex, 0);
                            }
                        }

                        $vid = @$this->GetIDForIdent('AlarmPlaylist' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($this->ReadPropertyBoolean('showAlarmPlaylist')) {
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
                        // FIXME: No break. Please add proper comment if intentional
                        // No break. Add additional comment above this line if intentional
                    case 'end':
                        if (!isset($State)) {
                            $State = 0;
                        }
                        // FIXME: No break. Please add proper comment if intentional
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
                        $vid = @$this->GetIDForIdent('AlarmState' . $Alarm->Index);
                        if ($vid === false) {
                            $this->RegisterVariableInteger('AlarmState' . $Alarm->Index, sprintf($this->Translate('Alarm %d state'), $Alarm->Index + 1), 'LSA.State', (($Alarm->Index + 1) * 10) + 2);
                            $this->EnableAction('AlarmState' . $Alarm->Index);
                        }

                        $this->SetValueInteger('AlarmState' . $Alarm->Index, $State);
                        break;
                }
                break;
            case 'prefset':
                $LMSData->Command[1] = $LMSData->Command[2];
                unset($LMSData->Command[2]);
                // FIXME: No break. Please add proper comment if intentional
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
                $this->RefreshDeleteProfil(count($this->Alarms->Items));
                $this->RefreshEvents($this->Alarms);
                break;
            case 'client':
                if (($LMSData->Data[0] == 'new') || ($LMSData->Data[0] == 'reconnect')) {
                    $this->RequestAllState();
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
     * @param \SqueezeBox\LMSData $LMSData Zu versendende Daten.
     *
     * @return \SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function Send(\SqueezeBox\LMSData $LMSData)
    {
        try {
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
