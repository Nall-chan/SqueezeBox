<?php

declare(strict_types=1);

/*
 * @addtogroup squeezebox
 * @{
 *
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.1
 *
 */
require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen
require_once __DIR__ . '/../libs/SqueezeBoxClass.php';  // diverse Klassen
eval('declare(strict_types=1);namespace squeezebox {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace squeezebox {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');

/**
 * LMSConfigurator Klasse für ein SqueezeBox Konfigurator.
 * Erweitert IPSModule.
 *
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.0
 * @example <b>Ohne</b>
 */
class LMSConfigurator extends IPSModule
{

    use \squeezebox\DebugHelper,
        \squeezebox\BufferHelper,
        \squeezebox\InstanceStatus {
        \squeezebox\InstanceStatus::MessageSink as IOMessageSink;
        \squeezebox\InstanceStatus::RegisterParent as IORegisterParent;
        \squeezebox\InstanceStatus::RequestAction as IORequestAction;
    }
    /**
     * Interne Funktion des SDK.
     *
     * @access public
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
     *
     * @access public
     */
    public function ApplyChanges()
    {
        $this->ParentID = 0;
        $this->SetReceiveDataFilter('.*"nothingtoreceive":.*');
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() <> KR_READY) {
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
     * @access public
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

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        $this->RegisterParent();
        /* if ($this->HasActiveParent()) {
          $this->IOChangeState(IS_ACTIVE);
          } */
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        return false;
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
     * @access protected
     */
    protected function IOChangeState($State)
    {
        $this->LogMessage(__METHOD__, KL_DEBUG);
        if ($State == IS_ACTIVE) {
            // Gerätebuffer laden
        } else {
            // Gerätebuffer leeren
        }
    }

    /**
     * IPS-Instanz-Funktion 'LMC_GetDeviceInfo'.
     * Lädt die bekannten Player vom LMS
     *
     * @access private
     * @result array|bool Assoziertes Array,  false und Fehlermeldung.
     */
    private function GetDeviceInfo()
    {
        $count = $this->Send(new LMSData(['player', 'count'], '?'));
        if (($count === false) or ( $count === null)) {
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
        return ($Values['model'] == 'baby');
    }

    private function GetConfigParam(&$item1, $InstanceID, $ConfigParam)
    {
        $item1 = IPS_GetProperty($InstanceID, $ConfigParam);
    }

    private function GetConfiguratorArray(string $GUID, string $ConfigParamName)
    {
        $ModuleName = IPS_GetModule($GUID)['Aliases'][0];
        $InstancesDevices = $this->GetInstanceList($GUID, $ConfigParamName);
        $this->SendDebug($ModuleName, $InstancesDevices, 0);
        $Devices = [];
        $Device = [];
        if ($ConfigParamName != '') {
            $InstanceID = array_search($index, $InstancesDevices);
            $Device['line'] = $index;
        } else {
            $InstanceID = array_search(0, $InstancesDevices);
        }
        if ($InstanceID === false) {
            $Device['instanceID'] = 0;
            $Device['name'] = $ModuleName;
        } else {
            unset($InstancesDevices[$InstanceID]);
            $Device['instanceID'] = $InstanceID;
            $Device['name'] = IPS_GetLocation($InstanceID);
        }
        $Create = [
            'moduleID'      => $GUID,
            'configuration' => new stdClass()
        ];
        if ($ConfigParamName != '') {
            $Create['configuration'] = [
                $ConfigParamName => $index
            ];
        }
        $Device['create'] = array_merge([$Create], $ParentCreate);
        $Devices[] = $Device;

        if ($ConfigParamName !== '') {
            foreach ($InstancesDevices as $InstanceID => $Line) {
                $Devices[] = [
                    'instanceID' => $InstanceID,
                    'type'       => $ModuleName,
                    'line'       => $Line,
                    'name'       => IPS_GetLocation($InstanceID)
                ];
            }
        } else {
            foreach ($InstancesDevices as $InstanceID => $Line) {
                $Devices[] = [
                    'instanceID' => $InstanceID,
                    'type'       => $ModuleName,
                    'name'       => IPS_GetLocation($InstanceID)
                ];
            }
        }
        return $Devices;
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
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
                'model'      => 'unknow',
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
        foreach ($InstanceIDListPlayers as $InstanceID => $Address) {
            $AlarmValues[] = [
                'instanceID' => $InstanceID,
                'name'       => IPS_GetName($InstanceID),
                'address'    => $Address,
                'location'   => IPS_GetLocation($InstanceID)
            ];
        }
        $BatteryValues = [];
        foreach ($FoundBattery as $Address => $Device) {
            $InstanceID = array_search($Address, $InstanceIDListBattery);
            if ($InstanceID !== false) {
                $AddValue = [
                    'instanceID' => $InstanceID,
                    'name'       => IPS_GetName($InstanceID),
                    'address'    => $Address,
                    'location'   => IPS_GetLocation($InstanceID)
                ];
                unset($InstanceIDListBattery[$InstanceID]);
            } else {
                $AddValue = [
                    'instanceID' => 0,
                    'name'       => $this->Translate('Battery') . ' ' . $Device['name'],
                    'address'    => $Address,
                    'location'   => ''
                ];
            }
            $Create = [
                'moduleID'      => '{718158BB-B247-4A71-9440-9C2FF1378752}',
                'configuration' => ['Address' => $Address]
            ];

            $AddValue['create'] = array_merge([$Create], $ParentCreate);
            $BatteryValues[] = $AddValue;
        }
        foreach ($InstanceIDListPlayers as $InstanceID => $Address) {
            $BatteryValues[] = [
                'instanceID' => $InstanceID,
                'name'       => IPS_GetName($InstanceID),
                'address'    => $Address,
                'location'   => IPS_GetLocation($InstanceID)
            ];
        }

        $Form['actions'][0]['items'][0]['values'] = $PlayerValues;
        $Form['actions'][0]['items'][0]['rowCount'] = count($PlayerValues) + 1;
        $Form['actions'][1]['items'][0]['values'] = $AlarmValues;
        $Form['actions'][1]['items'][0]['rowCount'] = count($AlarmValues) + 1;
        $Form['actions'][2]['items'][0]['values'] = $BatteryValues;
        $Form['actions'][2]['items'][0]['rowCount'] = count($BatteryValues) + 1;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    /**
     * Konvertiert $Data zu einem JSONString und versendet diese an den Splitter.
     *
     * @access protected
     * @param LMSData $LMSData Zu versendende Daten.
     * @return LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function Send(LMSData $LMSData)
    {
        try {
            $JSONData = $LMSData->ToJSONString('{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}');
            $anwser = @$this->SendDataToParent($JSONData);
            if ($anwser === false) {
                return null;
            }
            $result = @unserialize($anwser);
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
