<?php

declare(strict_types=1);

namespace SqueezeBox;

/**
 * @package       Squeezebox
 * @file          LMSSocket.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.10
 *
 */

/**
 * @property resource|bool $Socket
 */
trait LMSSocket
{
    /**
     * __destruct
     * schließt bei Bedarf den noch offenen TCP-Socket.
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->Socket) {
            fclose($this->Socket);
        }
    }

    /**
     * SendDirectToLMS
     * Konvertiert $Data zu einem String und versendet diesen direkt an den LMS.
     *
     * @param  string $Host
     * @param  int $Port
     * @param  string $Username
     * @param  string $Password
     * @param \SqueezeBox\LMSData $LMSData Zu versendende Daten.
     * @return ?\SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function SendDirectToLMS(string $Host, int $Port, string $Username, string $Password, \SqueezeBox\LMSData $LMSData): ?\SqueezeBox\LMSData
    {
        try {
            $this->SendDebug('Send Direct', $LMSData, 0);
            if (!$this->Socket) {
                if ($Host === '') {
                    return null;
                }
                $LoginData = (new \SqueezeBox\LMSData('login', [$Username, $Password]))->ToRawStringForLMS();
                $this->SendDebug('Send Direct', $LoginData, 0);
                $this->Socket = @stream_socket_client('tcp://' . $Host . ':' . $Port, $errno, $errstr, 2);
                if (!$this->Socket) {
                    throw new \Exception($this->Translate('No answer from LMS'), E_USER_NOTICE);
                }
                stream_set_timeout($this->Socket, 5);
                fwrite($this->Socket, $LoginData);
                $LoginResult = stream_get_line($this->Socket, 1024 * 1024 * 2, chr(0x0d));
                $this->SendDebug('Response Direct', $LoginResult, 0);
                if ($LoginResult === false) {
                    throw new \Exception($this->Translate('No answer from LMS'), E_USER_NOTICE);
                }
            }
            $Data = $LMSData->ToRawStringForLMS();
            $this->SendDebug('Send Direct', $Data, 0);
            fwrite($this->Socket, $Data);
            $answer = stream_get_line($this->Socket, 1024 * 1024 * 2, chr(0x0d));
            $this->SendDebug('Response Direct', $answer, 0);
            if ($answer === false) {
                throw new \Exception($this->Translate('No answer from LMS'), E_USER_NOTICE);
            }
            $ReplyData = new \SqueezeBox\LMSResponse($answer);
            $LMSData->Data = $ReplyData->Data;
            $this->SendDebug('Response Direct', $LMSData, 0);
            return $LMSData;
        } catch (\Exception $exc) {
            $this->SendDebug('Receive Direct', $exc->getMessage(), 0);
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            restore_error_handler();
        }
        return null;
    }

    /**
     * SendToSplitter
     * Konvertiert $Data zu einem JSONString und versendet diese an den Splitter.
     *
     * @param \SqueezeBox\LMSData $LMSData Zu versendende Daten.
     * @return \SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function SendToSplitter(\SqueezeBox\LMSData $LMSData): ?\SqueezeBox\LMSData
    {
        try {
            $this->SendDebug('Send', $LMSData, 0);
            $answer = $this->SendDataToParent($LMSData->ToJSONString());
            if ($answer === false) {
                $this->SendDebug('Response', 'No valid answer', 0);
                return null;
            }
            $result = unserialize($answer);
            if ($LMSData->needResponse === false) {
                return null;
            }
            $LMSData->Data = $result->Data;
            $this->SendDebug('Response', $LMSData, 0);
            return $LMSData;
        } catch (\Exception $exc) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            restore_error_handler();
            return null;
        }
    }
}

/**
 * Trait um ein Cover vom LMS zu laden.
 */
trait LMSCover
{
    /**
     * GetCover
     * Liefert die Rohdaten eines Covers, welches vom LMS geladen wurde.
     *
     * @param string $CoverID Die ID des Covers.
     * @param string $Size    Die Größe des Covers in Pixel.
     * @param string $Player  Die Player-MAC.
     * @return bool|string Die Rohdaten des Covers, oder false im Fehlerfall.
     */
    private function GetCover(string $CoverID, string $Size, string $Player): bool|string
    {
        $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $IoID = IPS_GetInstance($SplitterID)['ConnectionID'];
        $Hostname = IPS_GetProperty($IoID, 'Host');
        $Webport = IPS_GetProperty($SplitterID, 'Webport');
        $Login = [
            'AuthUser' => IPS_GetProperty($SplitterID, 'User'),
            'AuthPass' => IPS_GetProperty($SplitterID, 'Password'),
            'Timeout'  => 5000
        ];

        if ($Hostname === '') {
            return false;
        }
        $Host = gethostbyname($Hostname);
        $Host .= ':' . $Webport;
        if ($Player != '') {
            $Player = '?player=' . rawurlencode($Player);
            $CoverID = 'current';
        }
        $URL = 'http://' . $Host . '/music/' . $CoverID . '/' . $Size . '.png' . $Player;
        $this->SendDebug('GetCover', $URL, 0);
        return @Sys_GetURLContentEx($URL, $Login);
    }
}