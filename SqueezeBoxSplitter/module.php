<?

require_once(__DIR__ . "/../SqueezeBoxClass.php");  // diverse Klassen

class LMSSplitter extends IPSModule
{

    public function __construct($InstanceID)
    {

//Never delete this line!
        parent::__construct($InstanceID);
//These lines are parsed on Symcon Startup or Instance creation
//You cannot use variables here. Just static values.
        $this->RegisterPropertyString("Host", "");
        $this->RegisterPropertyBoolean("Open", true);
        $this->RegisterPropertyInteger("Port", 9090);
        $this->RegisterPropertyInteger("Webport", 9000);
    }

    public function ApplyChanges()
    {
//Never delete this line!
        parent::ApplyChanges();
        $change = false;
        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
        $ParentID = $this->GetParent();
        if (!($ParentID === false))
        {
            if (IPS_GetProperty($ParentID, 'Host') <> $this->ReadPropertyString('Host'))
            {
                IPS_SetProperty($ParentID, 'Host', $this->ReadPropertyString('Host'));
                $change = true;
            }
            if (IPS_GetProperty($ParentID, 'Port') <> $this->ReadPropertyInteger('Port'))
            {
                IPS_SetProperty($ParentID, 'Port', $this->ReadPropertyInteger('Port'));
                $change = true;
            }
            $ParentOpen = $this->ReadPropertyBoolean('Open');
            if ($this->ReadPropertyString('Host') == '')
            {
                $ParentOpen = false;
            }
            if (IPS_GetProperty($ParentID, 'Open') <> $ParentOpen)
            {
                IPS_SetProperty($ParentID, 'Open', $ParentOpen);
                $change = true;
            }

            if ($change)
                @IPS_ApplyChanges($ParentID);
        }
        $this->RegisterVariableString("BufferIN", "BufferIN");
        $this->RegisterVariableString("BufferOUT", "BufferOUT");
        $this->RegisterVariableBoolean("WaitForResponse", "WaitForResponse");
        if ($this->ReadPropertyBoolean('Open') and $this->HasActiveParent($ParentID))
        {
            $Data = new LMSData("listen 1");
            $this->SendLMSData($Data);
        }
        IPS_LogMessage('ApplyChanges', 'Instant:' . $this->InstanceID);
        IPS_LogMessage('ApplyChanges', 'Host:' . $this->ReadPropertyString('Host'));
    }

################## PRIVATE     

    private function SendLMSData($LMSData)
    {

        if ($LMSData->needResponse)
        {
//Semaphore setzen
            if (!$this->lock("LMSData"))
            {
                throw new Exception("Can not send to LMS");
            }
            if ($LMSData->Typ == LMSData::GetData)
            {
                $WaitData = substr($LMSData->Data, 0, -2);
            }
            else
            {
                $WaitData = $LMSData->Data;
            }
            // Anfrage für die Warteschleife schreiben
            if (!$this->SetWaitForResponse($WaitData))
            {
                $this->unlock("LMSData");
                throw new Exception("Can not send to LMS");
            }
            try
            {
                $this->SendDataToParent($LMSData->Data);
            }
            catch (Exception $exc)
            {
                //  Daten in Warteschleife löschen
                $this->ResetWaitForResponse();
                $this->unlock("LMSData");
                throw $exc;
            }
            // Auf Antwort warten....
            $ret = $this->WaitForResponse();
            // SendeLock  velassen
            $this->unlock("LMSData");
            if ($ret === false) // Warteschleife lief in Timeout
            {
                //  Daten in Warteschleife löschen                
                $this->ResetWaitForResponse();
                // Fehler
                throw new Exception("No answer from LMS");
            }
            // Rückgabe ist eine Bestätigung von einem Befehl
            if ($LMSData->Typ == LMSData::SendCommand)
            {
                if ($LMSData->Data == $ret)
                    return true;
                else
                    return false;
            }
            else
            {
                // Rückgabe ist ein Wert auf eine Anfrage, abschneiden der Anfrage.
                $ret = str_replace($WaitData, "", $ret);
//                IPS_LogMessage('FOUND RESPONSE2', print_r($ret, 1));
                return $ret;
            }
        }
        else
        { // ohne Response, also ohne warten raussenden, 
            try
            {
                $this->SendDataToParent($LMSData->Data);
            }
            catch (Exception $exc)
            {
                throw $exc;
            }
        }
    }

    /*    private function encode($raw)
      {
      $array = explode(' ', $raw); // Antwortstring in Array umwandeln
      $Data = new stdClass();
      $array[0] = urldecode($array[0]);
      $Data->MAC = $this->GetMAC($array[0]); // MAC in lesbares Format umwandeln
      $Data->Payload = $array;
      return $Data;
      } */

    /*    private function GetMAC($mac)
      {
      return $this->MAC = strtolower(str_replace(array("-", ":"), "", $mac));
      } */

    private function lock($ident)
    {
        for ($i = 0; $i < 100; $i++)
        {
            if (IPS_SemaphoreEnter("LMS_" . (string) $this->InstanceID . (string) $ident, 1))
            {
                return true;
            }
            else
            {
                IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    private function unlock($ident)
    {
        IPS_SemaphoreLeave("LMS_" . (string) $this->InstanceID . (string) $ident);
    }

    private function SetWaitForResponse($Data)
    {
        if ($this->lock('BufferOut'))
        {
            $buffer = $this->GetIDForIdent('BufferOUT');
            $WaitForResponse = $this->GetIDForIdent('WaitForResponse');
            SetValueString($buffer, $Data);
            SetValueBoolean($WaitForResponse, true);
            $this->unlock('BufferOut');
            return true;
        }
        return false;
    }

    private function WaitForResponse()
    {
        $Event = $this->GetIDForIdent('WaitForResponse');
        for ($i = 0; $i < 500; $i++)
        {
            if (GetValueBoolean($Event))
                IPS_Sleep(10);
            else
            {
                if ($this->lock('BufferOut'))
                {
                    $buffer = $this->GetIDForIdent('BufferOUT');
                    $ret = GetValueString($buffer);
                    SetValueString($buffer, "");
                    $this->unlock('BufferOut');
                    return $ret;
                }
                return false;
            }
        }
        return false;
    }

    private function ResetWaitForResponse()
    {
        if ($this->lock('BufferOut'))
        {
            $buffer = $this->GetIDForIdent('BufferOUT');
            $WaitForResponse = $this->GetIDForIdent('WaitForResponse');
            SetValueString($buffer, '');
            SetValueBoolean($WaitForResponse, false);
            $this->unlock('BufferOut');
            return true;
        }
        return false;
    }

    private function WriteResponse($Array)
    {
        $Event = $this->GetIDForIdent('WaitForResponse');
        if (!GetValueBoolean($Event))
            return false;
        $buffer = $this->GetIDForIdent('BufferOUT');
//        $Data[0] = urldecode($Data[0]);
        $Data = implode(" ", $Array);
        if (!(strpos($Data, GetValueString($buffer)) === false))
        {
            if ($this->lock('BufferOut'))
            {
                $Event = $this->GetIDForIdent('WaitForResponse');
                SetValueString($buffer, $Data);
                SetValueBoolean($Event, false);
                $this->unlock('BufferOut');
                return true;
            }
            return 'Error on write ResponseBuffer';
        }
        return false;
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function SendEx($Text)
    {
//        return $this->SendDataToParent($Text);
    }

    public function Rescan()
    {
        $ret = $this->SendLMSData(new LMSData('rescan', LMSData::SendCommand));
        IPS_LogMessage('LMS Rescan', print_r($ret, 1));
        return $ret;
    }

    public function Test1()
    {
        return "Test";
    }

    public function Test2()
    {
        return json_encode(array(1, 5, 7.9, 'footo' => 2, 'foo' => 'bar'));
    }

    public function CreateAllPlayer()
    {
        /*        $players = $this->SendDataToParent('player count ?');

          for ($i = 0; $i < $players; $i++)
          {
          $player = $this->SendDataToParent('player id ' . $i . ' ?');
          $playerName = $this->SendDataToParent('player name ' . $i . ' ?');
          // Daten zerlegen und Childs anlegen/prüfen
          IPS_LogMessage('PLAYER ID' . $i, print_r($player, 1));
          IPS_LogMessage('PLAYER NAME' . $i, print_r($playerName, 1));
          } */
    }

    public function GetPlayerInfo($Value)
    {
        $ret = $this->SendLMSData(new LMSData('players ' . $Value . ' 1', LMSData::GetData));
        IPS_LogMessage('LMS GetPlayerInfo', print_r($ret, 1));
        return $ret;
    }

    public function GetLibaryInfo()
    {
        $gernes = $this->SendLMSData(new LMSData('info total genres ?', LMSData::GetData));
        $artists = $this->SendLMSData(new LMSData('info total artists ?', LMSData::GetData));
        $albums = $this->SendLMSData(new LMSData('info total albums ?', LMSData::GetData));
        $songs = $this->SendLMSData(new LMSData('info total songs ?', LMSData::GetData));
        $ret = array('Geners' => $gernes, 'Artists' => $artists, 'Albums' => $albums, 'Songs' => $songs);
        IPS_LogMessage('LMS GetLibaryInfo', print_r($ret, 1));
        return $ret;
    }

    public function GetVersion()
    {
        $ret = $this->SendLMSData(new LMSData('version ?', LMSData::GetData));
        IPS_LogMessage('LMS GetVersion', print_r($ret, 1));
        return $ret;
    }

################## DataPoints

    public function ForwardData($JSONString)
    {
//EDD ankommend von Device
//
        $Data = json_decode($JSONString);
//        IPS_LogMessage("IOSplitter FRWD MAC", $data->MAC);
//        IPS_LogMessage("IOSplitter FRWD Payload", $data->Payload);
        if (is_array($Data->LSQ->Command))
            $Data->LSQ->Command = implode(' ', $Data->LSQ->Command);
        $LMSData = new LMSData($Data->LSQ->Address . ' ' . $Data->LSQ->Command . ' ' . $Data->LSQ->Value, LMSData::SendCommand, false);
// Daten annehmen und mit MAC codieren. Senden an Parent
//weiter zu IO  mit Warteschlange 
//
//
        $ret = $this->SendLMSData($LMSData);
        return $ret;
    }

    public function ReceiveData($JSONString)
    // 018EF6B5-AB94-40C6-AA53-46943E824ACF ankommend von ClientSocket-IO            
    {
        $data = json_decode($JSONString);
        //IPS_LogMessage("IOSplitter RECV", utf8_decode($data->Buffer));
        $bufferID = $this->GetIDForIdent("BufferIN");

        if (!$this->lock("bufferin"))
        {
            throw new Exception("ReceiveBuffer is locked");
        }
        $head = GetValueString($bufferID);
        SetValueString($bufferID, '');
        $packet = explode(chr(0x0d), $head . $data->Buffer);
        $tail = array_pop($packet);
        SetValueString($bufferID, $tail);
        $this->unlock("bufferin");
        foreach ($packet as $part)
        {
            $Data = new LMSResponse($part);
//            IPS_LogMessage("IOSplitter PART", print_r($Data, 1));
            if ($Data->Device == LMSResponse::isServer)
            {
                $isResponse = $this->WriteResponse($Data->Data);
                if ($isResponse === true)
                {
//                    IPS_LogMessage("IOSplitter isResonse", "TRUE");
                    // wird von Anfrage-Thread bearbeitet, für uns ist hier schluß
                    continue; // unn�tig, nur damit kein leerer Zweig ist :)
                }
                elseif ($isResponse === false)
                { //Info Daten von Server verarbeiten
                    // TODO
                }
                else
                {
                    $ret = new Exception($isResponse);
                }
            }
            /*            elseif ($Data->Device == LMSResponse::isMAC)
              {
              $ret = $this->SendDataToChildren(json_encode(Array("DataID" => "{CB5950B3-593C-4126-9F0F-8655A3944419}", "LMS" => $Data)));
              }
              elseif ($Data->Device == LMSResponse::isIP)
              {
              $ret = $this->SendDataToChildren(json_encode(Array("DataID" => "{CB5950B3-593C-4126-9F0F-8655A3944419}", "LMS" => $Data)));
              } */
            else
            {
                try
                {
                    $this->SendDataToChildren(json_encode(Array("DataID" => "{CB5950B3-593C-4126-9F0F-8655A3944419}", "LMS" => $Data)));
                }
                catch (Exception $exc)
                {
                    $ret = new Exception($exc);
                }
            }
        }
        if (isset($ret))
            throw $ret; // Fehler gesetzt, jetzt werfen
        return true;
    }

    protected function SendDataToParent($Data)
    {
        //Semaphore setzen
        if (!$this->lock("ToParent"))
        {
            throw new Exception("Can not send to LMS");
        }
        // Daten senden
        try
        {
            $ret = IPS_SendDataToParent($this->InstanceID, json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $Data . chr(0x0d))));
        }
        catch (Exception $exc)
        {
            // Senden fehlgeschlagen
            $this->unlock("ToParent");
            throw new Exception("LMS not reachable");
        }
        $this->unlock("ToParent");
        return $ret;
    }

    protected function SendDataToChildren($Data)
    {
        return IPS_SendDataToChildren($this->InstanceID, $Data);
    }

################## DUMMYS / WOARKAROUNDS - protected

    protected function GetParent()
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //          
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    protected function HasActiveParent($ParentID)
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //
        if ($ParentID > 0)
        {
            $parent = IPS_GetInstance($ParentID);
            if ($parent['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }

    /*
      protected function SetStatus($data)
      {
      IPS_LogMessage(__CLASS__, __FUNCTION__); //
      }

      protected function RegisterTimer($data, $cata)
      {
      IPS_LogMessage(__CLASS__, __FUNCTION__); //
      }

      protected function SetTimerInterval($data, $cata)
      {
      IPS_LogMessage(__CLASS__, __FUNCTION__); //
      }

      protected function LogMessage($data, $cata)
      {

      }

      protected function SetSummary($data)
      {
      IPS_LogMessage(__CLASS__, __FUNCTION__ . "Data:" . $data); //
      }
     */
}

?>