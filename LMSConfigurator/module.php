<?php

declare(strict_types=1);

/**
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.05
 *
 */
require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen
require_once __DIR__ . '/../libs/SqueezeBoxClass.php';  // diverse Klassen
eval('declare(strict_types=1);namespace LMSConfigurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace LMSConfigurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');

/**
 * LMSConfigurator Klasse für ein SqueezeBox Konfigurator.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.05
 *
 * @property int $ParentID
 */
class LMSConfigurator extends IPSModuleStrict
{
    use \SqueezeBox\DebugHelper,
        \LMSConfigurator\BufferHelper,
        \LMSConfigurator\InstanceStatus {
            \LMSConfigurator\InstanceStatus::MessageSink as IOMessageSink;
            \LMSConfigurator\InstanceStatus::RegisterParent as IORegisterParent;
            \LMSConfigurator\InstanceStatus::RequestAction as IORequestAction;
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
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);

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
        $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $IO = IPS_GetInstance($Splitter)['ConnectionID'];
        $ParentCreate = [
            [
                'moduleID'      => '{96A9AB3A-2538-42C5-A130-FC34205A706A}',
                'configuration' => [
                    'User'     => IPS_GetProperty($Splitter, 'User'),
                    'Password' => IPS_GetProperty($Splitter, 'Password'),
                    'Port'     => IPS_GetProperty($Splitter, 'Port'),
                    'Webport'  => IPS_GetProperty($Splitter, 'Webport')
                ]
            ],
            [
                'moduleID'      => '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}',
                'configuration' => [
                    'Host' => IPS_GetProperty($IO, 'Host'),
                    'Port' => (int) IPS_GetProperty($IO, 'Port')
                ]
            ]
        ];

        $FoundPlayers = $FoundBattery = $FoundAlarms = $this->GetDeviceInfo();
        $FoundBattery = array_filter($FoundBattery, [$this, 'FilterBattery']);
        $this->SendDebug('Found Players', $FoundPlayers, 0);
        $this->SendDebug('Found Battery', $FoundBattery, 0);
        $InstanceIDListPlayers = $this->GetInstanceList('{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}', 'Address');
        $this->SendDebug('IPS Players', $InstanceIDListPlayers, 0);
        $InstanceIDListAlarms = $this->GetInstanceList('{E7423083-3502-42C8-B244-2852D0BE41D4}', 'Address');
        $this->SendDebug('IPS Alarms', $InstanceIDListAlarms, 0);
        $InstanceIDListBattery = $this->GetInstanceList('{718158BB-B247-4A71-9440-9C2FF1378752}', 'Address');
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
            $Create = [
                'moduleID'      => '{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}',
                'configuration' => ['Address' => $Address]
            ];

            $AddValue['create'] = array_merge([$Create], $ParentCreate);
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
            $Create = [
                'moduleID'      => '{E7423083-3502-42C8-B244-2852D0BE41D4}',
                'configuration' => ['Address' => $Address]
            ];

            $AddValue['create'] = array_merge([$Create], $ParentCreate);
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
            $Create = [
                'moduleID'      => '{718158BB-B247-4A71-9440-9C2FF1378752}',
                'configuration' => ['Address' => $Device['ip']]
            ];

            $AddValue['create'] = array_merge([$Create], $ParentCreate);
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
        $count = $this->Send(new \SqueezeBox\LMSData(['player', 'count'], '?'));
        if (($count === false) || ($count === null)) {
            return [];
        }
        $players = [];
        for ($i = 0; $i < $count->Data[0]; $i++) {
            $playerid = $this->Send(new \SqueezeBox\LMSData(['player', 'id'], [$i, '?']));
            if ($playerid === false) {
                continue;
            }
            $id = strtolower(rawurldecode($playerid->Data[1]));

            $playerip = $this->Send(new \SqueezeBox\LMSData(['player', 'ip'], [$i, '?']));
            if ($playerip === false) {
                continue;
            }
            $players[$id]['ip'] = rawurldecode(explode(':', $playerip->Data[1])[0]);
            $playername = $this->Send(new \SqueezeBox\LMSData(['player', 'name'], [$i, '?']));
            if ($playername === false) {
                continue;
            }
            $players[$id]['name'] = rawurldecode($playername->Data[1]);
            $playermodel = $this->Send(new \SqueezeBox\LMSData(['player', 'model'], [$i, '?']));
            if ($playermodel === false) {
                continue;
            }
            $players[$id]['model'] = rawurldecode($playermodel->Data[1]);
        }
        return $players;
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
     * @return null|\SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function Send(\SqueezeBox\LMSData $LMSData): null|\SqueezeBox\LMSData
    {
        try {
            $JSONData = $LMSData->ToJSONString('{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}');
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
