<?php

declare(strict_types=1);

namespace SqueezeBox;

/**
 * @package       Squeezebox
 * @file          LMSSocket.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       4.20
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
                $Login = new \SqueezeBox\LMSData('login', [$Username, $Password]);
                $LoginData = $Login->ToRawStringForLMS();
                $this->SendDebug('Send Direct', $Login->Masked()->ToRawStringForLMS(), 0);
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
                // Bei falschen Zugangsdaten bestätigt der LMS den login-Befehl und trennt beim nächsten Befehl die Verbindung.
                $Closed = feof($this->Socket);
                fclose($this->Socket);
                $this->Socket = false;
                throw new \Exception($this->Translate($Closed ? 'Login to LMS failed. Check username and password.' : 'No answer from LMS'), E_USER_NOTICE);
            }
            $ReplyData = new \SqueezeBox\LMSResponse($answer);
            $LMSData->Data = $ReplyData->Data;
            $this->SendDebug('Response Direct', $LMSData, 0);
            return $LMSData;
        } catch (\Exception $exc) {
            $this->SendDebug('Receive Direct', $exc->getMessage(), 0);
            trigger_error($exc->getMessage(), E_USER_NOTICE);
        }
        return null;
    }

    /**
     * GetLMSConnectionData
     * Liest die Verbindungsdaten des LMS aus dem übergeordneten Splitter und dessen Client Socket.
     * Nur für Instanzen unterhalb eines Splitters (Player, Wecker).
     *
     * @return ?array Host (aufgelöst), Port (CLI), Webport, User, Password. NULL ohne Splitter, IO oder Host.
     */
    private function GetLMSConnectionData(): ?array
    {
        $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($SplitterID < 10000) {
            return null;
        }
        $IoID = IPS_GetInstance($SplitterID)['ConnectionID'];
        if ($IoID < 10000) {
            return null;
        }
        $Host = (string) IPS_GetProperty($IoID, \SqueezeBox\IO\Property::Host);
        if ($Host === '') {
            return null;
        }
        return [
            'Host'     => gethostbyname($Host),
            'Port'     => (int) IPS_GetProperty($SplitterID, \SqueezeBox\Splitter\Property::Port),
            'Webport'  => (int) IPS_GetProperty($SplitterID, \SqueezeBox\Splitter\Property::Webport),
            'User'     => (string) IPS_GetProperty($SplitterID, \SqueezeBox\Splitter\Property::Username),
            'Password' => (string) IPS_GetProperty($SplitterID, \SqueezeBox\Splitter\Property::Password)
        ];
    }

    /**
     * SendDirectViaSplitter
     * Versendet $LMSData direkt an den LMS mit den Verbindungsdaten des übergeordneten Splitters.
     *
     * @param \SqueezeBox\LMSData $LMSData Zu versendende Daten (Address bereits gesetzt).
     * @return ?\SqueezeBox\LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function SendDirectViaSplitter(\SqueezeBox\LMSData $LMSData): ?\SqueezeBox\LMSData
    {
        $Connection = $this->GetLMSConnectionData();
        if ($Connection === null) {
            return null;
        }
        return $this->SendDirectToLMS($Connection['Host'], $Connection['Port'], $Connection['User'], $Connection['Password'], $LMSData);
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
            if ($LMSData->needResponse === false) {
                return null;
            }
            // Der Splitter liefert bei einem Fehler einen leeren String und hat den Fehler bereits gemeldet.
            $result = ($answer === '') ? false : @unserialize($answer, ['allowed_classes' => [\SqueezeBox\LMSData::class]]);
            if (!($result instanceof \SqueezeBox\LMSData)) {
                $this->SendDebug('Response', 'No valid answer', 0);
                return null;
            }
            $LMSData->Data = $result->Data;
            $this->SendDebug('Response', $LMSData, 0);
            return $LMSData;
        } catch (\Exception $exc) {
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return null;
        }
    }
}

/**
 * Trait um ein Cover vom LMS zu laden. Benötigt den Trait LMSSocket (GetLMSConnectionData).
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
        $Connection = $this->GetLMSConnectionData();
        if ($Connection === null) {
            return false;
        }
        $Login = [
            'AuthUser' => $Connection['User'],
            'AuthPass' => $Connection['Password'],
            'Timeout'  => 5000
        ];
        $Host = $Connection['Host'] . ':' . $Connection['Webport'];
        if ($Player != '') {
            $Player = '?player=' . rawurlencode($Player);
            $CoverID = 'current';
        }
        $URL = 'http://' . $Host . '/music/' . $CoverID . '/' . $Size . '.png' . $Player;
        $this->SendDebug('GetCover', $URL, 0);
        return @Sys_GetURLContentEx($URL, $Login);
    }
}