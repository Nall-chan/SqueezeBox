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
eval('declare(strict_types=1);namespace LyrionMusicServerConfigurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace LyrionMusicServerConfigurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');

/**
 * LyrionMusicServerConfigurator Klasse für ein SqueezeBox Konfigurator.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.10
 *
 * @property int $ParentID
 * @method bool IORequestAction(string $Ident, mixed $Value)
 * @method void IOMessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data)
 * @method int IORegisterParent()
 */
class LyrionMusicServerConfigurator extends IPSModuleStrict
{
    use \SqueezeBox\DebugHelper,
        \LyrionMusicServerConfigurator\BufferHelper,
        \LyrionMusicServerConfigurator\InstanceStatus {
            \LyrionMusicServerConfigurator\InstanceStatus::MessageSink as IOMessageSink;
            \LyrionMusicServerConfigurator\InstanceStatus::RegisterParent as IORegisterParent;
            \LyrionMusicServerConfigurator\InstanceStatus::RequestAction as IORequestAction;
        }

    /**
     * Create
     *
     * @return void
     */
    public function Create(): void
    {
        parent::Create();
        $this->SetReceiveDataFilter('.*"nothingtoreceive":.*');
        $this->ParentID = 0;
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        $this->ParentID = 0;
        $this->SetReceiveDataFilter('.*"nothingtoreceive":.*');
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        $this->RegisterParent();
        if ($this->HasActiveParent()) {
            $this->IOChangeState(IS_ACTIVE);
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
        }
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
        $this->IORequestAction($Ident, $Value);
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
        if (!$this->HasActiveParent()) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'Instance has no active parent.'
                    ]]
                ]
            ];
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        $FoundPlayers = $this->GetDeviceInfo();
        $FoundAlarms = array_filter($FoundPlayers, [$this, 'FilterAlarms']);
        $FoundBattery = array_filter($FoundPlayers, [$this, 'FilterBattery']);
        $this->SendDebug('Found Players', $FoundPlayers, 0);
        $this->SendDebug('Found Alarms', $FoundAlarms, 0);
        $this->SendDebug('Found Battery', $FoundBattery, 0);
        $InstanceIDListPlayers = $this->GetInstanceList(\SqueezeBox\GUID::Squeezebox, \SqueezeBox\Device\Property::Address);
        $this->SendDebug('IPS Players', $InstanceIDListPlayers, 0);
        $InstanceIDListAlarms = $this->GetInstanceList(\SqueezeBox\GUID::Alarm, \SqueezeBox\Alarm\Property::Address);
        $this->SendDebug('IPS Alarms', $InstanceIDListAlarms, 0);
        $InstanceIDListBattery = $this->GetInstanceList(\SqueezeBox\GUID::Battery, \SqueezeBox\Battery\Property::Address);
        $this->SendDebug('IPS Battery', $InstanceIDListBattery, 0);
        $PlayerValues = [];
        foreach ($FoundPlayers as $Address => $Device) {
            $InstanceID = array_search($Address, $InstanceIDListPlayers);
            if ($InstanceID !== false) {
                $AddValue = [
                    'instanceID' => $InstanceID,
                    'name'       => IPS_GetName($InstanceID),
                    'model'      => ucfirst($Device['model']),
                    'address'    => $Address,
                    'location'   => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true)
                ];
                unset($InstanceIDListPlayers[$InstanceID]);
            } else {
                $AddValue = [
                    'instanceID' => 0,
                    'name'       => $Device['name'],
                    'model'      => ucfirst($Device['model']),
                    'address'    => $Address,
                    'location'   => ''
                ];
            }
            $AddValue['create'] = [
                'moduleID'      => \SqueezeBox\GUID::Squeezebox,
                'configuration' => [
                    \SqueezeBox\Device\Property::Address => $Address
                ]
            ];
            $PlayerValues[] = $AddValue;
        }
        foreach ($InstanceIDListPlayers as $InstanceID => $Address) {
            $PlayerValues[] = [
                'instanceID' => $InstanceID,
                'name'       => IPS_GetName($InstanceID),
                'model'      => 'unknown',
                'address'    => $Address,
                'location'   => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true)
            ];
        }
        $AlarmValues = [];
        foreach ($FoundAlarms as $Address => $Device) {
            $InstanceID = array_search($Address, $InstanceIDListAlarms);
            if ($InstanceID !== false) {
                $AddValue = [
                    'instanceID' => $InstanceID,
                    'name'       => IPS_GetName($InstanceID),
                    'address'    => $Address,
                    'location'   => IPS_GetLocation($InstanceID)
                ];
                unset($InstanceIDListAlarms[$InstanceID]);
            } else {
                $AddValue = [
                    'instanceID' => 0,
                    'name'       => $this->Translate('Alarm') . ' ' . $Device['name'],
                    'address'    => $Address,
                    'location'   => ''
                ];
            }
            $AddValue['create'] = [
                'moduleID'      => \SqueezeBox\GUID::Alarm,
                'configuration' => [
                    \SqueezeBox\Alarm\Property::Address => $Address
                ]
            ];
            $AlarmValues[] = $AddValue;
        }
        foreach ($InstanceIDListAlarms as $InstanceID => $Address) {
            $AlarmValues[] = [
                'instanceID' => $InstanceID,
                'name'       => IPS_GetName($InstanceID),
                'address'    => $Address,
                'location'   => IPS_GetLocation($InstanceID)
            ];
        }
        $BatteryValues = [];
        foreach ($FoundBattery as $Device) {
            $InstanceID = array_search($Device['ip'], $InstanceIDListBattery);
            if ($InstanceID !== false) {
                $AddValue = [
                    'instanceID' => $InstanceID,
                    'name'       => IPS_GetName($InstanceID),
                    'address'    => $Device['ip'],
                    'location'   => IPS_GetLocation($InstanceID)
                ];
                unset($InstanceIDListBattery[$InstanceID]);
            } else {
                $AddValue = [
                    'instanceID' => 0,
                    'name'       => $this->Translate('Battery') . ' ' . $Device['name'],
                    'address'    => $Device['ip'],
                    'location'   => ''
                ];
            }
            $AddValue['create'] = [
                'moduleID'      => \SqueezeBox\GUID::Battery,
                'configuration' => [
                    \SqueezeBox\Battery\Property::Address => $Device['ip']
                ]
            ];
            $BatteryValues[] = $AddValue;
        }
        foreach ($InstanceIDListBattery as $InstanceID => $Address) {
            $BatteryValues[] = [
                'instanceID' => $InstanceID,
                'name'       => IPS_GetName($InstanceID),
                'address'    => $Address,
                'location'   => IPS_GetLocation($InstanceID)
            ];
        }

        $Form['actions'][0]['items'][0]['values'] = $PlayerValues;
        $Form['actions'][0]['items'][0]['rowCount'] = count($PlayerValues) - 1;
        $Form['actions'][1]['items'][0]['values'] = $AlarmValues;
        $Form['actions'][1]['items'][0]['rowCount'] = count($AlarmValues) - 1;
        $Form['actions'][2]['items'][0]['values'] = $BatteryValues;
        $Form['actions'][2]['items'][0]['rowCount'] = count($BatteryValues) - 1;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
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
     * IOChangeState
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     *
     * @param  int $State
     * @return void
     */
    protected function IOChangeState(int $State): void
    {
        if ($State == IS_ACTIVE) {
            // Buffer aller Player laden
        } else {
            // Buffer aller Player leeren
        }
    }

    /**
     * GetDeviceInfo
     * IPS-Instanz-Funktion 'LMC_GetDeviceInfo'.
     * Lädt die bekannten Player vom LMS.
     *
     * @return array|bool Assoziiertes Array,  false und Fehlermeldung.
     */
    private function GetDeviceInfo(): array
    {
        $Count = $this->Send(new \SqueezeBox\LMSData(['player', 'count'], '?'));
        if (($Count === false) || ($Count === null)) {
            return [];
        }
        $Players = [];
        for ($i = 0; $i < $Count->Data[0]; $i++) {
            $PlayerId = $this->Send(new \SqueezeBox\LMSData(['player', 'id'], [$i, '?']));
            if ($PlayerId === false) {
                continue;
            }
            $Id = strtolower(rawurldecode($PlayerId->Data[1]));

            $PlayerIP = $this->Send(new \SqueezeBox\LMSData(['player', 'ip'], [$i, '?']));
            if ($PlayerIP === false) {
                continue;
            }
            $Players[$Id]['ip'] = rawurldecode(explode(':', $PlayerIP->Data[1])[0]);
            $PlayerName = $this->Send(new \SqueezeBox\LMSData(['player', 'name'], [$i, '?']));
            if ($PlayerName === false) {
                continue;
            }
            $Players[$Id]['name'] = rawurldecode($PlayerName->Data[1]);
            $PlayerModel = $this->Send(new \SqueezeBox\LMSData(['player', 'model'], [$i, '?']));
            if ($PlayerModel === false) {
                continue;
            }
            $Players[$Id]['model'] = rawurldecode($PlayerModel->Data[1]);
        }
        return $Players;
    }

    /**
     * GetInstanceList
     *
     * @param  string $GUID
     * @param  string $ConfigParam
     * @return array
     */
    private function GetInstanceList(string $GUID, string $ConfigParam): array
    {
        $InstanceIDList = array_flip(array_values(array_filter(IPS_GetInstanceListByModuleID($GUID), [$this, 'FilterInstances'])));
        if ($ConfigParam != '') {
            array_walk($InstanceIDList, [$this, 'GetConfigParam'], $ConfigParam);
        }
        return $InstanceIDList;
    }

    /**
     * FilterInstances
     *
     * @param  int $InstanceID
     * @return bool
     */
    private function FilterInstances(int $InstanceID): bool
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == $this->ParentID;
    }

    /**
     * FilterBattery
     *
     * @param  array $Values
     * @return bool
     */
    private function FilterBattery(array $Values): bool
    {
        return $Values['model'] == 'baby';
    }

    /**
     * FilterAlarms
     *
     * @param  array $Values
     * @return bool
     */
    private function FilterAlarms(array $Values): bool
    {
        return !in_array($Values['model'], ['squeezelite', 'unknown', '']);
    }

    /**
     * GetConfigParam
     *
     * @param  mixed $item1
     * @param  int $InstanceID
     * @param  string $ConfigParam
     * @return void
     */
    private function GetConfigParam(mixed &$item1, int $InstanceID, string $ConfigParam): void
    {
        $item1 = IPS_GetProperty($InstanceID, $ConfigParam);
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
        try {
            $JSONData = $LMSData->ToJSONString();
            $answer = @$this->SendDataToParent($JSONData);
            if ($answer == false) {
                return null;
            }
            $result = @unserialize($answer);
            if ($result === null) {
                return null;
            }
            $LMSData->Data = $result->Data;
            return $LMSData;
        } catch (Exception $exc) {
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return null;
        }
    }
}
