<?

class LMSConfigurator extends IPSModule
{

    /**
     * IPS-Instanz-Funktion 'LMC_CreateAllPlayer'.
     * @access public
     * @result array Alle IPS-IDs der neu erstellen Player-Instanzen.
     */
    public function CreateAllPlayer()
    {
        $players = $this->GetNumberOfPlayers();
        if ($players === false)
            return false;
        $DevicesIDs = IPS_GetInstanceListByModuleID("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
        $CreatedPlayers = array();
        foreach ($DevicesIDs as $Device)
        {
            $KnownDevices[] = IPS_GetProperty($Device, 'Address');
        }
        for ($i = 0; $i < $players; $i++)
        {
            $player = $this->Send(new LMSData(array('player', 'id', $i), '?'));
            if ($player === false)
                continue;
            $playermac = rawurldecode($player);

            if (in_array($playermac, $KnownDevices))
                continue;
            $NewDevice = IPS_CreateInstance("{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}");
            $playerName = $this->Send(new LMSData(array('player', 'name', $i), '?'));
            IPS_SetName($NewDevice, $playerName);
            if (IPS_GetInstance($NewDevice)['ConnectionID'] <> $this->InstanceID)
            {
                @IPS_DisconnectInstance($NewDevice);
                IPS_ConnectInstance($NewDevice, $this->InstanceID);
            }
            IPS_SetProperty($NewDevice, 'Address', $playermac);
            IPS_ApplyChanges($NewDevice);
            $CreatedPlayers[] = $NewDevice;
        }
        return $CreatedPlayers;
    }
    
    /* // Für das Anlegen der Battery
     *  player ip <playerindex|playerid> ?

The "player ip ?" query returns the IP address (along with port number) of the specified player.

Example:

    Request: "player ip 0 ?<LF>" or "0 player ip ?"
    Response: "player ip 0 192.168.1.22:3483<LF>"


     */
}
?>