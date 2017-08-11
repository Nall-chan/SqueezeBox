<?

require_once(__DIR__ . "/../libs/SqueezeBoxClass.php");  // diverse Klassen
require_once(__DIR__ . "/../libs/SqueezeBoxTraits.php");  // diverse Klassen
/*
 * @addtogroup squeezebox
 * @{
 *
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2017 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.0
 *
 */

/**
 * LMSConfigurator Klasse für ein SqueezeBox Konfigurator.
 * Erweitert IPSModule.
 *
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2017 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.0
 * @example <b>Ohne</b>
 */
class LMSConfigurator extends IPSModule
{

    use DebugHelper;

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent("{96A9AB3A-2538-42C5-A130-FC34205A706A}");
        $this->SetReceiveDataFilter('.*"nothingtoreceive":.*');
    }

    /**
     * Interne Funktion des SDK.
     * 
     * @access public
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
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
        $count = $this->Send(new LMSData(array('player', 'count'), '?'));
        $players = array();
        if (($count === false) or ($count === NULL))
          return $players;
        for ($i = 0; $i < $count->Data[0]; $i++)
        {
            $player = $this->Send(new LMSData(array('player', 'id'), array($i, '?')));
            if ($player === false)
                continue;
            $players[$i]['mac'] = rawurldecode($player->Data[1]);
            $player = $this->Send(new LMSData(array('player', 'ip'), array($i, '?')));
            if ($player === false)
                continue;
            $players[$i]['ip'] = rawurldecode(explode(':', $player->Data[1])[0]);
            $player = $this->Send(new LMSData(array('player', 'name'), array($i, '?')));
            if ($player === false)
                continue;
            $players[$i]['name'] = rawurldecode($player->Data[1]);
            $player = $this->Send(new LMSData(array('player', 'model'), array($i, '?')));
            if ($player === false)
                continue;
            $players[$i]['model'] = rawurldecode($player->Data[1]);
        }
        return $players;
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function GetConfigurationForm()
    {
        $FoundDevicesMAC = $FoundDevicesIP = $FoundDevicesAlarm = $this->GetDeviceInfo();
        $Total = count($FoundDevicesMAC);
        $MyParent = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $ListMAC = $ListIP = $ListAlarm = array();
        $DisconnectedMAC = $DisconnectedIP = $DisconnectedAlarm = 0;
        $NewDevicesMAC = $NewDevicesIP = $NewDevicesAlarm = 0;
        $this->SendDebug('Found', $FoundDevicesMAC, 0);
        $InstanceIDListMAC = IPS_GetInstanceListByModuleID("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
        $InstanceIDListAlarm = IPS_GetInstanceListByModuleID("{E7423083-3502-42C8-B244-2852D0BE41D4}");
        $InstanceIDListIP = IPS_GetInstanceListByModuleID("{718158BB-B247-4A71-9440-9C2FF1378752}");
        foreach ($InstanceIDListMAC as $InstanceID)
        {
            // Fremde Geräte überspringen
            if (IPS_GetInstance($InstanceID)['ConnectionID'] != $MyParent)
                continue;
            $mac = (string) IPS_GetProperty($InstanceID, 'Address');
            $Device = array(
                'instanceID' => $InstanceID,
                'mac' => $mac,
                'location' => IPS_GetLocation($InstanceID));
            $this->SendDebug('Search', $mac, 0);
            $index = array_search($mac, array_column($FoundDevicesMAC, 'mac'));
            if ($index !== false) // found
            {
                $Device['name'] = $FoundDevicesMAC[$index]['name'];
                $Device['rowColor'] = "#00ff00";
                $FoundDevicesMAC[$index]['instanceID'] = $InstanceID;
            }
            else
            {
//                $FoundDevicesMAC[$index]['instanceID'] = 0;
                $Device['name'] = 'unknown';
                $Device["rowColor"] = "#ff0000";
                $DisconnectedMAC++;
            }
            $ListMAC[] = $Device;
        }
        foreach ($FoundDevicesMAC as $Device)
        {
            if (array_key_exists('instanceID', $Device))
                continue;
            $Device = array(
                'instanceID' => 0,
                'mac' => $Device['mac'],
                'name' => $Device['name'],
                'location' => '');
            $ListMAC[] = $Device;
            $NewDevicesMAC++;
        }
        foreach ($InstanceIDListAlarm as $InstanceID)
        {
            // Fremde Geräte überspringen
            if (IPS_GetInstance($InstanceID)['ConnectionID'] != $MyParent)
                continue;
            $mac = (string) IPS_GetProperty($InstanceID, 'Address');
            $Device = array(
                'instanceID' => $InstanceID,
                'mac' => $mac,
                'location' => IPS_GetLocation($InstanceID));
            $this->SendDebug('Search', $mac, 0);
            $index = array_search($mac, array_column($FoundDevicesAlarm, 'mac'));
            if ($index !== false) // found
            {
                $Device['name'] = $FoundDevicesAlarm[$index]['name'];
                $Device['rowColor'] = "#00ff00";
                $FoundDevicesAlarm[$index]['instanceID'] = $InstanceID;
            }
            else
            {
//                $FoundDevicesMAC[$index]['instanceID'] = 0;
                $Device['name'] = 'unknown';
                $Device["rowColor"] = "#ff0000";
                $DisconnectedAlarm++;
            }
            $ListAlarm[] = $Device;
        }
        foreach ($FoundDevicesAlarm as $Device)
        {
            if (array_key_exists('instanceID', $Device))
                continue;
            $Device = array(
                'instanceID' => 0,
                'mac' => $Device['mac'],
                'name' => $Device['name'],
                'location' => '');
            $ListAlarm[] = $Device;
            $NewDevicesMAC++;
        }
        foreach ($InstanceIDListIP as $InstanceID)
        {
            $ip = (string) IPS_GetProperty($InstanceID, 'Address');
            $Device = array(
                'instanceID' => $InstanceID,
                'ip' => $ip,
                'location' => IPS_GetLocation($InstanceID));
            $this->SendDebug('Search', $ip, 0);
            $index = array_search($ip, array_column($FoundDevicesIP, 'ip'));
            if ($index !== false) // found
            {
                $Device['name'] = $FoundDevicesIP[$index]['name'];
                $Device['rowColor'] = "#00ff00";
                $FoundDevicesIP[$index]['instanceID'] = $InstanceID;
            }
            else
            {
//                $FoundDevicesIP[$index]['instanceID'] = 0;
                $Device['name'] = 'unknown';
                $Device["rowColor"] = "#ff0000";
                $DisconnectedIP++;
            }
            $ListIP[] = $Device;
        }
        foreach ($FoundDevicesIP as $Device)
        {
            if (array_key_exists('instanceID', $Device))
                continue;
            $Device = array(
                'instanceID' => 0,
                'ip' => $Device['ip'],
                'name' => $Device['name'],
                'location' => '');
            $ListIP[] = $Device;
            $NewDevicesIP++;
        }

        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        if ($Total > 0)
            $data['actions'][1]['label'] = sprintf($this->Translate("Devices found: %d"), $Total);
        if ($NewDevicesMAC > 0)
            $data['actions'][4]['label'] = sprintf($this->Translate("New devices: %d"), $NewDevicesMAC);
        if ($DisconnectedMAC > 0)
            $data['actions'][5]['label'] = sprintf($this->Translate("Disconnected devices: %d"), $DisconnectedMAC);
        $data['actions'][6]['values'] = array_merge($data['actions'][6]['values'], $ListMAC);

        if ($NewDevicesAlarm > 0)
            $data['actions'][10]['label'] = sprintf($this->Translate("New devices: %d"), $NewDevicesMAC);
        if ($DisconnectedAlarm > 0)
            $data['actions'][11]['label'] = sprintf($this->Translate("Disconnected devices: %d"), $DisconnectedMAC);
        $data['actions'][12]['values'] = array_merge($data['actions'][12]['values'], $ListAlarm);


        if ($NewDevicesIP > 0)
            $data['actions'][16]['label'] = sprintf($this->Translate("New devices: %d"), $NewDevicesIP);
        if ($DisconnectedIP > 0)
            $data['actions'][17]['label'] = sprintf($this->Translate("Disconnected devices: %d"), $DisconnectedIP);
        $data['actions'][18]['values'] = array_merge($data['actions'][18]['values'], $ListIP);

        $data['actions'][7]['onClick'] = <<<'EOT'
if (($devicesMAC['mac'] == '') or ($devicesMAC['instanceID'] > 0))
    return;
$InstanceID = IPS_CreateInstance('{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}');
if (IPS_GetInstance($InstanceID)['ConnectionID'] != IPS_GetInstance($id)['ConnectionID'])
{
    if (IPS_GetInstance($InstanceID)['ConnectionID'] > 0)
    IPS_DisconnectInstance($InstanceID);
    IPS_ConnectInstance($InstanceID, IPS_GetInstance($id)['ConnectionID']);
}
@IPS_SetProperty($InstanceID, 'Address', $devicesMAC['mac']);
@IPS_ApplyChanges($InstanceID);
IPS_SetName($InstanceID,$devicesMAC['name']);
echo 'OK';
EOT;
        $data['actions'][13]['onClick'] = <<<'EOT'
if (($devicesAlarm['mac'] == '') or ($devicesAlarm['instanceID'] > 0))
    return;
$InstanceID = IPS_CreateInstance('{E7423083-3502-42C8-B244-2852D0BE41D4}');
if (IPS_GetInstance($InstanceID)['ConnectionID'] != IPS_GetInstance($id)['ConnectionID'])
{
    if (IPS_GetInstance($InstanceID)['ConnectionID'] > 0)
    IPS_DisconnectInstance($InstanceID);
    IPS_ConnectInstance($InstanceID, IPS_GetInstance($id)['ConnectionID']);
}
@IPS_SetProperty($InstanceID, 'Address', $devicesAlarm['mac']);
@IPS_ApplyChanges($InstanceID);
IPS_SetName($InstanceID,'Alarm '.$devicesAlarm['name']);
echo 'OK';
EOT;
        $data['actions'][19]['onClick'] = <<<'EOT'
if (($devicesIP['ip'] == '') or ($devicesIP['instanceID'] > 0))
    return;
$InstanceID = IPS_CreateInstance('{718158BB-B247-4A71-9440-9C2FF1378752}');
@IPS_SetProperty($InstanceID, 'Address', $devicesIP['ip']);
@IPS_ApplyChanges($InstanceID);
IPS_SetName($InstanceID,'Battery '.$devicesIP['name']);
echo 'OK';
EOT;
        $data['actions'][0]['onClick'] = "echo '" . $this->Translate('Sorry please close and reopen configurator.') . "';";
        return json_encode($data);
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
        try
        {
            $JSONData = $LMSData->ToJSONString("{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}");
            $anwser = @$this->SendDataToParent($JSONData);
            if ($anwser === false)
                return NULL;
            $result = @unserialize($anwser);
            if ($result === NULL)
                return NULL;
            $LMSData->Data = $result->Data;
            return $LMSData;
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return NULL;
        }
    }

}

?>
