<?php

declare(strict_types = 1);
/**
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          DebugHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 */

/**
 * DebugHelper ergänzt SendDebug um die Möglichkeit Array und Objekte auszugeben.
 *
 */
trait DebugHelper
{
    /**
     * Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
     *
     * @access protected
     * @param string $Message Nachricht für Data.
     * @param TXB_API_Data|mixed $Data Daten für die Ausgabe.
     * @return int $Format Ausgabeformat für Strings.
     */
    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_a($Data, 'LMSResponse')) {
            $this->SendDebug($Message . " LMSResponse->Address", $Data->Address, 0);
            $this->SendDebug($Message . " LMSResponse->Command", $Data->Command, 0);
            $this->SendDebug($Message . " LMSResponse->Data", $Data->Data, 0);
        } elseif (is_a($Data, 'LMSData')) {
            $this->SendDebug($Message . " LMSData->Address", $Data->Address, 0);
            $this->SendDebug($Message . " LMSData->Command", $Data->Command, 0);
            $this->SendDebug($Message . " LMSData->Data", $Data->Data, 0);
            $this->SendDebug($Message . " LMSData->needResponse", ($Data->needResponse ? 'true' : 'false'), 0);
        } elseif (is_array($Data)) {
            if (count($Data) > 15) {
                $this->SendDebug($Message, array_slice($Data, 0, 10), 0);
                $this->SendDebug($Message . ':CUT', '-------------CUT-----------------', 0);
                $this->SendDebug($Message, array_slice($Data, -5, null, true), 0);
            } else {
                foreach ($Data as $Key => $DebugData) {
                    $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
                }
            }
        } elseif (is_object($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . "->" . $Key, $DebugData, 0);
            }
        } elseif (is_bool($Data)) {
            parent::SendDebug($Message, ($Data ? 'TRUE' : 'FALSE'), 0);
        } else {
            if (IPS_GetKernelRunlevel() == KR_READY) {
                parent::SendDebug($Message, $Data, $Format);
            } else {
                IPS_LogMessage($this->InstanceID . ": " . $Message, $Data);
            }
        }
    }

}
