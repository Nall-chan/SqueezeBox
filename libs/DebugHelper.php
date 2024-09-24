<?php

declare(strict_types=1);
/**
 * @addtogroup squeezebox
 * @{
 *
 * @file          DebugHelper.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.8
 */

namespace SqueezeBox;

/**
 * DebugHelper ergänzt SendDebug um die Möglichkeit Array und Objekte auszugeben.
 */
trait DebugHelper
{
    /**
     * SendDebug
     * Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
     *
     * @param string $Message Nachricht für Data.
     * @param mixed  $Data    Daten für die Ausgabe.
     * @param int $Format Ausgabeformat für Strings.
     * @return bool
     */
    protected function SendDebug(string $Message, mixed $Data, int $Format): bool
    {
        if (is_a($Data, '\\SqueezeBox\\LMSResponse')) {
            $this->SendDebug($Message . ' LMSResponse->Address', $Data->Address, 0);
            $this->SendDebug($Message . ' LMSResponse->Command', $Data->Command, 0);
            $this->SendDebug($Message . ' LMSResponse->Data', $Data->Data, 0);
        } elseif (is_a($Data, '\\SqueezeBox\\LMSData')) {
            $this->SendDebug($Message . ' LMSData->Address', $Data->Address, 0);
            $this->SendDebug($Message . ' LMSData->Command', $Data->Command, 0);
            $this->SendDebug($Message . ' LMSData->Data', $Data->Data, 0);
            $this->SendDebug($Message . ' LMSData->needResponse', ($Data->needResponse ? 'true' : 'false'), 0);
        } elseif (is_array($Data)) {
            if (count($Data) == 0) {
                $this->SendDebug($Message, '[EMPTY]', 0);
            } elseif (count($Data) > 25) {
                $this->SendDebug($Message, array_slice($Data, 0, 20), 0);
                $this->SendDebug($Message . ':CUT', '-------------CUT-----------------', 0);
                $this->SendDebug($Message, array_slice($Data, -5, null, true), 0);
            } else {
                foreach ($Data as $Key => $DebugData) {
                    $this->SendDebug($Message . ':' . $Key, $DebugData, 0);
                }
            }
        } elseif (is_object($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . '->' . $Key, $DebugData, 0);
            }
        } elseif (is_bool($Data)) {
            parent::SendDebug($Message, ($Data ? 'TRUE' : 'FALSE'), 0);
        } else {
            if (IPS_GetKernelRunlevel() == KR_READY) {
                parent::SendDebug($Message, (string) $Data, $Format);
            } else {
                $this->LogMessage($Message . ':' . (string) $Data, KL_DEBUG);
            }
        }
        return true;
    }
}
