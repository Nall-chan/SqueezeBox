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

/**
 * LMSDiscovery Klasse implementiert.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.80
 *
 * @example <b>Ohne</b>
 *
 * @property array $Devices
 */
class LMSDiscovery extends IPSModuleStrict
{
    use \SqueezeBox\DebugHelper;

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        parent::ApplyChanges();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm(): string
    {
        $Devices = $this->DiscoverDevices();
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ((count($Devices) == 0) && IPS_GetOption('NATSupport')) {
            $Form['actions'][2]['visible'] = true;
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }

        $IPSDevices = $this->GetIPSInstances();

        $Values = [];

        foreach ($Devices as $IPAddress => $Device) {
            $AddValue = [
                'IPAddress'  => $IPAddress,
                'servername' => $Device['ENAME'],
                'name'       => 'Logitech Media Server (' . $Device['ENAME'] . ')',
                'version'    => $Device['VERS'],
                'instanceID' => 0
            ];
            $InstanceID = array_search($IPAddress, $IPSDevices);
            if ($InstanceID === false) {
                $InstanceID = array_search(strtolower($Device['Host']), $IPSDevices);
                if ($InstanceID !== false) {
                    $AddValue['IPAddress'] = $Device['Host'];
                }
            }
            if ($InstanceID !== false) {
                unset($IPSDevices[$InstanceID]);
                $AddValue['name'] = IPS_GetLocation($InstanceID);
                $AddValue['instanceID'] = $InstanceID;
            }

            $AddValue['create'] = [
                [
                    'moduleID'      => '{35028918-3F9C-4524-9FB4-DBAF429C6E18}',
                    'configuration' => new stdClass()
                ],
                [
                    'moduleID'      => '{96A9AB3A-2538-42C5-A130-FC34205A706A}',
                    'configuration' => [
                        'Webport' => (int) $Device['JSON']
                    ]
                ],
                [
                    'moduleID'      => '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}',
                    'configuration' => [
                        'Host' => $AddValue['IPAddress'],
                        'Port' => 9090
                    ]
                ]
            ];
            $Values[] = $AddValue;
        }

        foreach ($IPSDevices as $InstanceID => $IPAddress) {
            $Values[] = [
                'IPAddress'  => $IPAddress,
                'version'    => '',
                'servername' => '',
                'name'       => IPS_GetLocation($InstanceID),
                'instanceID' => $InstanceID
            ];
        }
        $Form['actions'][1]['values'] = $Values;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    private function GetIPSInstances(): array
    {
        $InstanceIDList = IPS_GetInstanceListByModuleID('{35028918-3F9C-4524-9FB4-DBAF429C6E18}');
        $Devices = [];
        foreach ($InstanceIDList as $InstanceID) {
            $Splitter = IPS_GetInstance($InstanceID)['ConnectionID'];
            if ($Splitter > 0) {
                $IO = IPS_GetInstance($Splitter)['ConnectionID'];
                if ($IO > 0) {
                    $Devices[$InstanceID] = strtolower(IPS_GetProperty($IO, 'Host'));
                }
            }
        }
        $this->SendDebug('IPS Devices', $Devices, 0);
        return $Devices;
    }

    private function DiscoverDevices(): array
    {
        $this->SendDebug('Discover', $this->Translate('Background discovery of Logitech Media Servers'), 0);
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            $this->SendDebug('Discover', $this->Translate('Error on create socket'), 0);
            return [];
        }
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 100000]);
        socket_bind($socket, '0.0.0.0', 0);
        $message = "\x65\x49\x50\x41\x44\x00\x4e\x41\x4d\x45\x00\x4a\x53\x4f\x4e\x00\x56\x45\x52\x53\x00\x55\x55\x49\x44\x00\x4a\x56\x49\x44\x06\x12\x34\x56\x78\x12\x34";
        $this->SendDebug('Search', $message, 1);
        if (@socket_sendto($socket, $message, strlen($message), 0, '255.255.255.255', 3483) === false) {
            return [];
        }
        usleep(100000);
        $i = 50;
        $buf = '';
        $IPAddress = '';
        $Port = 0;
        $DeviceData = [];
        while ($i) {
            $ret = @socket_recvfrom($socket, $buf, 2048, 0, $IPAddress, $Port);
            if ($ret === false) {
                break;
            }
            if ($ret === 0) {
                $i--;
                continue;
            }
            $this->SendDebug('Receive', $buf, 0);
            $Serach = ['UUID', 'ENAME', 'VERS', 'JSON'];
            foreach ($Serach as $Key) {
                $start = strpos($buf, $Key);
                if ($start !== false) {
                    $DeviceData[$IPAddress][$Key] = substr($buf, $start + strlen($Key) + 1, ord($buf[$start + strlen($Key)]));
                }
            }
            $DeviceData[$IPAddress]['Host'] = gethostbyaddr($IPAddress);
        }
        socket_close($socket);
        $this->SendDebug('Found', $DeviceData, 0);
        return $DeviceData;
    }
}

/* @} */
