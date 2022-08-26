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
eval('declare(strict_types=1);namespace LMSConfigurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace LMSConfigurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');

/**
 * LMSConfigurator Klasse für ein SqueezeBox Konfigurator.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.70
 *
 * @example <b>Ohne</b>
 */
class LMSConfigurator extends IPSModule
{
    use \squeezebox\DebugHelper,
        \LMSConfigurator\BufferHelper,
        \LMSConfigurator\InstanceStatus {
        \LMSConfigurator\InstanceStatus::MessageSink as IOMessageSink;
        \LMSConfigurator\InstanceStatus::RegisterParent as IORegisterParent;
        \LMSConfigurator\InstanceStatus::RequestAction as IORequestAction;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{96A9AB3A-2538-42C5-A130-FC34205A706A}');
        $this->SetReceiveDataFilter('.*"nothingtoreceive":.*');
        $this->ParentID = 0;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
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
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        return false;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

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
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
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
            // Buffer aller Player laden
        } else {
            // Buffer aller Player leeren
        }
    }

    /**
     * IPS-Instanz-Funktion 'LMC_GetDeviceInfo'.
     * Lädt die bekannten Player vom LMS.
     *
     * @result array|bool Assoziiertes Array,  false und Fehlermeldung.
     */
    private function GetDeviceInfo()
    {
        $count = $this->Send(new LMSData(['player', 'count'], '?'));
        if (($count === false) || ($count === null)) {
            return [];
        }
        $players = [];
        for ($i = 0; $i < $count->Data[0]; $i++) {
            $playerid = $this->Send(new LMSData(['player', 'id'], [$i, '?']));
            if ($playerid === false) {
                continue;
            }
            $id = strtolower(rawurldecode($playerid->Data[1]));

            $playerip = $this->Send(new LMSData(['player', 'ip'], [$i, '?']));
            if ($playerip === false) {
                continue;
            }
            $players[$id]['ip'] = rawurldecode(explode(':', $playerip->Data[1])[0]);
            $playername = $this->Send(new LMSData(['player', 'name'], [$i, '?']));
            if ($playername === false) {
                continue;
            }
            $players[$id]['name'] = rawurldecode($playername->Data[1]);
            $playermodel = $this->Send(new LMSData(['player', 'model'], [$i, '?']));
            if ($playermodel === false) {
                continue;
            }
            $players[$id]['model'] = rawurldecode($playermodel->Data[1]);
        }
        return $players;
    }

    private function GetInstanceList(string $GUID, string $ConfigParam)
    {
        $InstanceIDList = array_flip(array_values(array_filter(IPS_GetInstanceListByModuleID($GUID), [$this, 'FilterInstances'])));
        if ($ConfigParam != '') {
            array_walk($InstanceIDList, [$this, 'GetConfigParam'], $ConfigParam);
        }
        return $InstanceIDList;
    }

    private function FilterInstances(int $InstanceID)
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == $this->ParentID;
    }

    private function FilterBattery($Values)
    {
        return $Values['model'] == 'baby';
    }

    private function GetConfigParam(&$item1, $InstanceID, $ConfigParam)
    {
        $item1 = IPS_GetProperty($InstanceID, $ConfigParam);
    }

    /**
     * Konvertiert $Data zu einem JSONString und versendet diese an den Splitter.
     *
     * @param LMSData $LMSData Zu versendende Daten.
     *
     * @return LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function Send(LMSData $LMSData)
    {
        try {
            $JSONData = $LMSData->ToJSONString('{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}');
            $answer = @$this->SendDataToParent($JSONData);
            if ($answer === false) {
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
