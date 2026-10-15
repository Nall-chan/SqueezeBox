<?php

declare(strict_types=1);

namespace SqueezeBox;

require_once __DIR__ . '/LibraryConsts.php';
require_once __DIR__ . '/LMSSocket.php';
require_once __DIR__ . '/TimeConvert.php';

/**
 * @package       Squeezebox
 * @file          SqueezeBoxClass.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.10
 *
 */

/**
 * GeräteTyp
 */
enum DeviceType
{
    case isServer;
    case isMAC;
    case isIP;
}

/**
 * Definiert eine Datensatz zum Versenden an des LMS.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.10
 */
class LMSData extends \stdClass
{
    /**
     * Adresse des Gerätes.
     *
     * @var string
     */
    public string $Address;

    /**
     * Alle Kommandos als Array.
     *
     * @var string|array
     */
    public string|array $Command;

    /**
     * Alle Daten des Kommandos.
     *
     * @var string|array
     */
    public string|array $Data;

    /**
     * Flag ob auf Antwort gewartet werden muss.
     *
     * @var bool
     */
    public bool $needResponse;

    /**
     * Anzahl der versendeten Daten.
     *
     * @var int
     */
    private int $SendValues;

    /**
     * __construct
     * Erzeugt ein Objekt vom Typ LMSData.
     *
     * @param string|array $Command      Kommando
     * @param string|array $Data         Nutzdaten
     * @param bool         $needResponse Auf Antwort warten.
     */
    public function __construct($Command = '', $Data = '', $needResponse = true)
    {
        $this->Address = '';
        if (is_array($Command)) {
            $this->Command = $Command;
        } else {
            $this->Command = [$Command];
        }
        if (is_array($Data)) {
            $this->Data = array_map('rawurlencode', $Data);
        } else {
            $this->Data = rawurlencode((string) $Data);
        }
        $this->needResponse = $needResponse;
    }

    /**
     * IsValidAddress
     * Prüft die Adresse eines Players: MAC-Adresse (xx:xx:xx:xx:xx:xx) oder IPv4-Adresse.
     *
     * @param string $Address Die Adresse.
     * @return bool True, wenn das Format gültig ist.
     */
    public static function IsValidAddress(string $Address): bool
    {
        if (preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/i', $Address) === 1) {
            return true;
        }
        return filter_var($Address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Masked
     * Liefert eine Kopie für Debug-Ausgaben, in der das Passwort eines login-Befehls maskiert ist.
     *
     * @return LMSData Die Kopie.
     */
    public function Masked(): self
    {
        $Masked = clone $this;
        if (($this->Command[0] === 'login') && is_array($this->Data) && isset($this->Data[1])) {
            $Masked->Data[1] = '******';
        }
        return $Masked;
    }

    /**
     * ToJSONStringForLMS
     * Erzeugt einen String für den Datenaustausch mit einer IO-Instanz.
     *
     * @param string $GUID Die TX-GUID
     * @return string Der JSON-String für den Datenaustausch.
     */
    public function ToJSONStringForLMS(string $GUID): string
    {
        return json_encode(['DataID' => $GUID, 'Buffer' => bin2hex($this->ToRawStringForLMS())]);
    }

    /**
     * ToRawStringForLMS
     * Erzeugt einen String für das CLI des LMS.
     *
     * @return string CLI-Befehl für den LMS.
     */
    public function ToRawStringForLMS(): string
    {
        $Command = implode(' ', $this->Command);
        $this->SendValues = 0;

        if (is_array($this->Data)) {
            $Data = implode(' ', $this->Data);
            $this->SendValues = count($this->Data);
        } else {
            $Data = $this->Data;
            if (($this->Data !== null) && ($this->Data != '%3F')) {
                $this->SendValues = 1;
            }
        }
        return trim($this->Address . ' ' . trim($Command) . ' ' . trim($Data)) . chr(0x0d);
    }

    /**
     * GetSearchPatter
     * Liefert das Suchmuster für die SendQueue.
     *
     * @return string Das Suchmuster.
     */
    public function GetSearchPatter(): string
    {
        return $this->Address . implode('', $this->Command);
    }

    /**
     * CreateFromGenericObject
     * Befüllt das Objekt mit neuen Daten aus dem Datenaustausch.
     *
     * @param \stdClass $Data Das Objekt aus dem Datenaustausch.
     */
    public function CreateFromGenericObject(object $Data): void
    {
        $this->Address = $Data->Address;
        $this->Command = $Data->Command;
        $this->Data = $Data->Data;
        if (property_exists($Data, 'needResponse')) {
            $this->needResponse = $Data->needResponse;
        }
    }

    /**
     * SliceData
     * Schneidet aus den Netzdaten die Anzahl der versendeten Daten ab.
     */
    public function SliceData(): void
    {
        $this->Data = array_slice($this->Data, $this->SendValues);
    }

    /**
     * ToJSONString
     * Erzeugt ein JSON-String für den internen Datenaustausch dieses Moduls.
     *
     * @return string Der JSON-String.
     */
    public function ToJSONString(): string
    {
        return json_encode([
            'DataID'       => \SqueezeBox\GUID::SendToSplitter,
            'Address'      => $this->Address,
            'Command'      => $this->Command,
            'Data'         => $this->Data,
            'needResponse' => $this->needResponse
        ]);
    }
}

/**
 * Klasse mit den Empfangenen Daten vom LMS.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.10
 */
class LMSResponse extends LMSData
{
    /**
     * Enthält den Type des Versenders einer Antwort.
     *
     * @var DeviceType
     */
    public DeviceType $Device;

    /**
     * __construct
     * Zerlegt eine Antwort des LMS und erzeugt daraus ein LMSResponse-Objekt.
     *
     * @param string $RawString
     */
    public function __construct(string $RawString)
    {
        $array = explode(' ', $RawString); // Antwortstring in Array umwandeln
        if (strpos($array[0], '%3A') == 2) { //isMAC
            $this->Device = DeviceType::isMAC;
            $this->Address = rawurldecode(array_shift($array));
        } elseif (strpos($array[0], '.')) { //isIP
            $this->Device = DeviceType::isIP;
            $this->Address = array_shift($array);
        } else { // isServer
            $this->Device = DeviceType::isServer;
            $this->Address = '';
        }
        $this->Command = [array_shift($array)];
        if ($this->Device == DeviceType::isServer) {
            if (count($array) != 0) {
                switch ($this->Command[0]) {
                    case 'player':
                    case 'alarm':
                    case 'favorites':
                    case 'pref':
                        $this->Command[1] = array_shift($array);
                        break;
                    case 'info':
                        $this->Command[1] = array_shift($array);
                        $this->Command[2] = array_shift($array);
                        break;
                    case 'playlists':
                        if (in_array($array[0], ['rename', 'delete', 'new', 'tracks', 'edit'])) {
                            $this->Command[1] = array_shift($array);
                        }
                        break;
                }
            }
        } else {
            if (count($array) != 0) {
                switch ($this->Command[0]) {
                    case 'mixer':
                    case 'playlist':
                    case 'playerpref':
                    case 'alarm':
                    case 'lma':
                    case 'live365':
                    case 'mp3tunes':
                    case 'pandora':
                    case 'podcast':
                    case 'radiotime':
                    case 'rhapsodydirect':
                    case 'picks':
                    case 'rss':
                        $this->Command[1] = array_shift($array);
                        break;
                    case 'shoutcast':
                        $this->Command[1] = array_shift($array);
                        if (isset($array[0]) && in_array($array[0], ['play', 'load', 'insert', 'add'])) {
                            $this->Command[2] = array_shift($array);
                        }

                        break;
                    case 'prefset':
                    case 'status':
                        $this->Command[1] = array_shift($array);
                        $this->Command[2] = array_shift($array);
                        break;
                    case 'signalstrength':
                    case 'name':
                    case 'connected':
                    case 'client':
                    case 'sleep':
                    case 'button':
                    case 'power':
                    case 'play':
                    case 'stop':
                    case 'pause':
                    case 'mode':
                    case 'time':
                    case 'genre':
                    case 'artist':
                    case 'album':
                    case 'title':
                    case 'duration':
                    case 'remote':
                    case 'newmetadata':
                    case 'playlistcontrol':
                    case 'sync':
                    case 'display':
                    case 'show':
                    case 'randomplay':
                    case 'randomplaychoosegenre':
                    case 'randomplaygenreselectall':
                        break;
                    case 'displaynotify':
                    case 'menustatus':
                    default:
                        $this->Command[1] = $this->Command[0];
                        $this->Command[0] = 'ignore';
                        break;
                }
            }
        }
        if (count($array) == 0) {
            $array[0] = '';
        }

        $this->Data = array_map('rawurldecode', $array);
    }

    /**
     * ToJSONStringForDevice
     * Erzeugt aus dem Objekt einen JSON-String.
     *
     * @return string Der JSON-String für den Datenaustausch
     */
    public function ToJSONStringForDevice(): string
    {
        return json_encode([
            'DataID'  => \SqueezeBox\GUID::SendToChild,
            'Address' => $this->Address,
            'Command' => $this->Command,
            'Data'    => $this->Data
        ]);
    }
}

/**
 * Zerlegt einen getaggten Datensatz in Name und Wert.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.10
 */
class LMSTaggingData extends \stdClass
{
    /**
     * @var string Der Name des Datensatzes.
     */
    public string $Name;

    /**
     * @var string Der Inhalt des Datensatz. Leer, wenn die Zeile keinen Doppelpunkt enthält.
     */
    public string $Value = '';

    /**
     * __construct
     * Erzeugt ein LMSTaggingData-Objekt aus $TaggedDataLine.
     *
     * @param string $TaggedDataLine Die Rohdaten.
     */
    public function __construct(string $TaggedDataLine)
    {
        $Part = explode(':', $TaggedDataLine);
        $this->Name = array_shift($Part);
        if (count($Part) > 1) {
            $this->Value = implode(':', $Part);
        } else {
            if (count($Part) == 1) {
                $this->Value = array_shift($Part);
            }
        }
    }
}

/**
 * Zerlegt einen Array aus getaggten Datensätzen.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.10
 */
class LMSTaggingArray extends \stdClass
{
    /**
     * @var array Enthält alle zu dekodierenden Index mit ihren Typenumwandlungen.
     */
    public static $DataFields = [
        'Album'                   => 3, // l
        'Album_id'                => 1,
        'Artwork_track_id'        => 3, // J
        //        'Albums_count' => 1,
        'Artist'                  => 3, // a
        'Artist_id'               => 1, //s
        'Bitrate'                 => 3, // r
        'Category'                => 3,
        'Connected'               => 0,
        'Coverid'                 => 1, // i
        'Count'                   => 1,
        'Contributor_id'          => 1,
        'Contributor'             => 3,
        'Cmd'                     => 3,
        'Dow'                     => 3,
        'DowDel'                  => 1,
        'DowAdd'                  => 1,
        'Duration'                => 1, // d
        'Disc'                    => 1, // i
        'Disccount'               => 1, // q
        'Displaytype'             => 3,
        'Enabled'                 => 0,
        'Exists'                  => 0,
        'Filename'                => 3,
        'Genre'                   => 3, // g
        'Genre_id'                => 1, //p
        'Hasitems'                => 0,
        'Id'                      => 1,
        'Index'                   => 3,
        'Ip'                      => 3,
        'Icon'                    => 3,
        'Isaudio'                 => 0,
        'Model'                   => 3,
        'Modelname'               => 3,
        //        'Modified' => 0,
        'Name'                    => 3,
        'Newname'                 => 3,
        'Overwritten_playlist_id' => 1,
        'Playlisturl'             => 3,
        'Playerindex'             => 1,
        'Playerid'                => 3,
        'Playlist'                => 3,
        'Playlist_id'             => 1,
        'Repeat'                  => 0,
        //        'Remote' => 0,
        //        'Remote_title' => 3, //N
        //        'Rating' => 1, // R
        'Shufflemode'             => 1,
        'Shuffle'                 => 1,
        //        'Samplesize' => 3, // I
        //        'Singleton'=>0,
        'Type'                    => 3,
        'Time'                    => 1,
        'Title'                   => 3,
        'Track_id'                => 1,
        'Track'                   => 3,
        //        'Tracks_count' => 1,
        'Tracknum'                => 1, // t
        'Url'                     => 3, // u
        'Uuid'                    => 3,
        'Volume'                  => 1,
        'Weight'                  => 1,
        'Year'                    => 1 // Y
    ];
    /**
     * @var array Enthält die Nutzdaten
     */
    private array $DataArray = [];

    /**
     * __construct
     * Erzeugt aus einem Array mit getaggten Daten ein mehrdimensionales Array.
     *
     * @param array  $TaggedData  Das Array mit allen getaggten Zeilen.
     * @param string $UsedIdIndex Der zu verwendende Index welcher als Trenner zwischen den Objekten fungiert.
     * @param string $Filter      Ein auf alle Index anzuwendender Filter, nur Index welche den Filter am Anfang enthalten werden übernommen.
     */
    public function __construct(array $TaggedData, $UsedIdIndex = 'id', $Filter = '')
    {
        $id = -1;
        $DataArray = [];
        $UseIDs = false;
        if (count($TaggedData) > 0) {
            $UseIDs = ((new LMSTaggingData($TaggedData[0]))->Name == 'id') ? true : false;
            if ($UsedIdIndex === '') {
                $UseIDs = false;
            }
        }
        foreach ($TaggedData as $Line) {
            $Part = new LMSTaggingData($Line);
            if ($UseIDs) {
                if ($Part->Name == 'id') {
                    if (strpos($Part->Value, '.') === false) {
                        $id = (int) $Part->Value;
                    } else {
                        $id = $Part->Value;
                    }
                    continue;
                }
            } else {
                if ($Part->Name == $UsedIdIndex) {
                    $id++;
                }
            }
            if ($Filter != '') {
                if (strpos($Part->Name, $Filter) === false) {
                    continue;
                }
            }
            $Index = ucfirst($Part->Name);
            if (!array_key_exists($Index, static::$DataFields)) {
                continue;
            }

            switch (static::$DataFields[$Index]) {
                case 0:
                    $DataArray[$id][$Index] = (bool) ($Part->Value);
                    break;
                case 1:
                    // Nur echte Dezimalzahlen wandeln (Nachkommastellen werden abgeschnitten).
                    // Werte wie '1e512345' oder '01234567' sind z.B. Wecker-Ids und bleiben Strings.
                    if (preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $Part->Value)) {
                        $DataArray[$id][$Index] = (int) $Part->Value;
                    } else {
                        $DataArray[$id][$Index] = $Part->Value;
                    }
                    break;
                default:
                    // Die Werte sind bereits von LMSResponse dekodiert. URLs enthalten z.B. %20 als Teil der URL.
                    $DataArray[$id][$Index] = $Part->Value;
                    break;
            }
        }
        if (isset($DataArray[-1])) {
            if (count($DataArray) == 1) {
                // Eine Liste ohne Treffer besteht nur aus 'count:0' und ergibt ein leeres Array.
                // Andere Antworten mit nur count (z.B. playlistcontrol 'count:56') bleiben erhalten.
                $DataArray = ($DataArray[-1] === ['Count' => 0]) ? [] : $DataArray[-1];
            } else {
                unset($DataArray[-1]);
            }
        }
        $this->DataArray = $DataArray;
    }

    /**
     * DataArray
     * Liefert das Array mit allen Nutzdaten.
     *
     * @return array Das Array mit allen Nutzdaten.
     */
    public function DataArray(): array
    {
        return $this->DataArray;
    }

    /**
     * Compact
     * Bricht das Array auf einen bestimmte Index runter.
     *
     * @param string $Index Der zu verwendende Index.
     */
    public function Compact(string $Index): void
    {
        $Compacted = [];
        foreach ($this->DataArray as $Key => $Item) {
            // Einzelwerte (z.B. Count) sind keine Datensätze und entfallen.
            if (is_array($Item)) {
                $Compacted[$Key] = $Item[$Index] ?? '';
            }
        }
        $this->DataArray = $Compacted;
    }

    /**
     * Count
     * Liefert die Anzahl der Daten im DataArray.
     *
     * @return int Anzahl der Einträge in DataArray.
     */
    public function Count(): int
    {
        return count($this->DataArray);
    }
}

/**
 * Zerlegt einen Array aus getaggten Datensätzen zu SongInfos.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.10
 */
class LMSSongInfo extends \stdClass
{
    /**
     * Enthält die Zuordnung zu den Datentypen.
     *
     * @var array
     */
    public static $SongFields = [
        'Id'               => 1,
        'Title'            => 3,
        'Genre'            => 3, // g
        'Album'            => 3, // l
        'Artist'           => 3, // a
        'Duration'         => 1, // d
        'Disc'             => 1, // i
        'Disccount'        => 1, // q
        'Bitrate'          => 3, // r
        'Tracknum'         => 1, // t
        'Url'              => 3, // u
        'Remote'           => 0,
        'Rating'           => 1, // R
        'Album_id'         => 1, // e
        'Artwork_track_id' => 3, // J
        'Samplesize'       => 1, // I
        'Remote_title'     => 3, //N
        'Genre_id'         => 1, //p
        'Artist_id'        => 1, //s
        'Year'             => 1, // Y
        'Name'             => 3,
        'Modified'         => 0,
        'Playlist'         => 3
    ];

    /**
     * Ein Array das alle Songs enthält.
     *
     * @var array
     */
    private array $SongArray = [];

    /**
     * Die gesamte Spielzeit in Sekunden.
     *
     * @var int
     */
    private int $Duration = 0;

    /**
     * __construct
     * Erzeugt aus einem Array mit getaggten Daten ein LMSSongInfo Objekt.
     *
     * @param array $TaggedData
     */
    public function __construct(array $TaggedData)
    {
        $id = -1;
        $Songs = [];
        $Duration = 0;
        $Playlist = (count($TaggedData) > 0) && ((new LMSTaggingData((string) $TaggedData[0]))->Name == 'playlist index');
        foreach ($TaggedData as $Line) {
            $Part = new LMSTaggingData($Line);
            if ($Playlist) {
                if ($Part->Name == 'playlist index') {
                    $id = (int) $Part->Value;
                    continue;
                }
            } else {
                if ($Part->Name == 'id') {
                    $id++;
                    $Songs[$id]['Artist'] = '';
                    $Songs[$id]['Title'] = '';
                    $Songs[$id]['Duration'] = 0;
                }
            }
            $Index = ucfirst($Part->Name);
            if (!array_key_exists($Index, static::$SongFields)) {
                continue;
            }

            if ($Part->Name == 'duration') {
                $Duration += (int) $Part->Value;
            }
            if (static::$SongFields[$Index] == 0) {
                $Songs[$id][$Index] = (bool) ($Part->Value);
            } elseif (static::$SongFields[$Index] == 1) {
                $Songs[$id][$Index] = intval($Part->Value);
            } else {
                // Die Werte sind bereits von LMSResponse dekodiert.
                $Songs[$id][$Index] = $Part->Value;
                if ($Index == 'Remote_title') {
                    $Songs[$id]['Title'] = $Songs[$id]['Remote_title'];
                }
            }
        }
        if (isset($Songs[-1])) {
            unset($Songs[-1]);
        }
        $this->SongArray = $Songs;
        $this->Duration = $Duration;
    }

    /**
     * GetSong
     * Liefert den ersten Datensatz der Songs.
     *
     * @return array Der Datensatz des Songs, ein leeres Array wenn keine Songs vorhanden sind.
     */
    public function GetSong(): array
    {
        return array_shift($this->SongArray) ?? [];
    }

    /**
     * GetAllSongs
     * Liefert alle Datensätze der Songs.
     *
     * @return array Alle Datensätze.
     */
    public function GetAllSongs(): array
    {
        return $this->SongArray;
    }

    /**
     * GetTotalDuration
     * Liefert die Laufzeit alle Songs.
     *
     * @return int Die Laufzeit in Sekunden.
     */
    public function GetTotalDuration(): int
    {
        return $this->Duration;
    }

    /**
     * CountAllSongs
     * Liefert die Anzahl alles Songs.
     *
     * @return int Die Anzahl der Songs.
     */
    public function CountAllSongs(): int
    {
        return count($this->SongArray);
    }
}

/**
 * Prüft und korrigiert eine URL.
 */
trait LMSSongURL
{
    /**
     * GetValidSongURL
     * Prüft und korrigiert eine URL.
     *
     * @param string $SongURL
     * @return bool True wenn URL valid ist, sonst false
     */
    protected function GetValidSongURL(string &$SongURL): bool
    {
        $valid = strpos($SongURL, '://');
        if ($valid === false) {
            trigger_error(sprintf($this->Translate('%s not valid.'), 'URL'), E_USER_NOTICE);
            return false;
        }
        $SongURL = str_replace('\\', '/', $SongURL);
        return true;
    }

    /**
     * IsSameSongURL
     * Vergleicht eine gesendete URL mit dem Echo des LMS.
     * Bei Dateien antwortet der LMS mit dem lokalen Pfad (z.B. E:\Musik\Die Ärzte\Lied.mp3) statt mit der URL
     * (file:///E:/Musik/Die%20%C4rzte/Lied.mp3); Pfade in der URL können Latin-1 kodiert sein.
     *
     * @param string $SongURL Die gesendete URL.
     * @param string $Echo    Die Antwort des LMS.
     * @return bool True, wenn das Echo zur URL passt.
     */
    protected static function IsSameSongURL(string $SongURL, string $Echo): bool
    {
        $Echo = str_replace('\\', '/', $Echo);
        if ($Echo === '') {
            return false;
        }
        if (strpos($SongURL, $Echo) !== false) {
            return true;
        }
        if (stripos($SongURL, 'file://') !== 0) {
            return false;
        }
        $Path = rawurldecode(substr($SongURL, 7));
        if (!mb_check_encoding($Path, 'UTF-8')) {
            $Path = mb_convert_encoding($Path, 'UTF-8', 'ISO-8859-1');
        }
        return str_ends_with($Path, $Echo);
    }
}

/**
 * @property string $WebHookSecret
 */
trait LMSHTMLTable
{
    use TimeConvert;

    /**
     * GetTableHeader
     * Liefert den Header der HTML-Tabelle.
     *
     * @param array $Config_Table Die Konfiguration der Tabelle
     * @param array $Config_Columns Die Konfiguration der Tabelle
     * @return string HTML-String
     */
    protected function GetTableHeader(array $Config_Table, array $Config_Columns): string
    {
        $table = '';
        // Kopf der Tabelle erzeugen
        $table .= '<table style="' . $Config_Table['<table>'] . '">' . PHP_EOL;
        // JS Rückkanal erzeugen
        $table .= '<script type="text/javascript" id="script' . $this->InstanceID . '">
function xhrGet' . $this->InstanceID . '(o)
{
    var HTTP = new XMLHttpRequest();
    HTTP.open(\'GET\',o.url,true);
    HTTP.send();
    HTTP.addEventListener(\'load\', function()
    {
        if (HTTP.status >= 200 && HTTP.status < 300)
        {
            if (HTTP.responseText !== \'OK\')
                sendError' . $this->InstanceID . '(HTTP.responseText);
        } else {
            sendError' . $this->InstanceID . '(HTTP.statusText);
        }
    });
}

function sendError' . $this->InstanceID . '(data)
{
var notify = document.getElementsByClassName("ipsNotifications")[0];
var newDiv = document.createElement("div");
newDiv.innerHTML =\'<div style="height:auto; visibility: hidden; overflow: hidden; transition: height 500ms ease-in 0s" class="ipsNotification"><div class="spacer"></div><div class="message icon error" onclick="document.getElementsByClassName(\\\'ipsNotifications\\\')[0].removeChild(this.parentNode);"><div class="ipsIconClose"></div><div class="content"><div class="title">Fehler</div><div class="text">\' + data + \'</div></div></div></div>\';
if (notify.childElementCount === 0)
	var thisDiv = notify.appendChild(newDiv.firstChild);
else
	var thisDiv = notify.insertBefore(newDiv.firstChild,notify.childNodes[0]);
var newheight = window.getComputedStyle(thisDiv, null)["height"];
thisDiv.style.height = "0px";
thisDiv.style.visibility = "visible";
function sleep (time) {
  return new Promise((resolve) => setTimeout(resolve, time));
}
sleep(10).then(() => {
	thisDiv.style.height = newheight;
});
}

</script>';
        $table .= '<colgroup>' . PHP_EOL;
        $colgroup = [];
        foreach ($Config_Columns as $Column) {
            if ($Column['show'] !== true) {
                continue;
            }
            $colgroup[$Column['index']] = '<col width="' . $Column['width'] . 'em" />' . PHP_EOL;
        }
        ksort($colgroup);
        $table .= implode('', $colgroup) . '</colgroup>' . PHP_EOL;
        $table .= '<thead style="' . $Config_Table['<thead>'] . '">' . PHP_EOL;
        $table .= '<tr>';
        $th = [];
        foreach ($Config_Columns as $Column) {
            if ($Column['show'] !== true) {
                continue;
            }
            $ThStyle = [];
            if ($Column['color'] >= 0) {
                $ThStyle[] = 'color:#' . substr('000000' . dechex($Column['color']), -6);
            }
            $ThStyle[] = 'text-align:' . $Column['align'];
            $ThStyle[] = $Column['style'];
            $th[$Column['index']] = '<th style="' . implode(';', $ThStyle) . ';">' . $Column['name'] . '</th>';
        }
        ksort($th);
        $table .= implode('', $th) . '</tr>' . PHP_EOL;
        $table .= '</thead>' . PHP_EOL;
        $table .= '<tbody style="' . $Config_Table['<tbody>'] . '">' . PHP_EOL;
        return $table;
    }

    /**
     * GetTable
     * Liefert den Inhalt der HTML-Box für ein Tabelle.
     *
     * @param array  $Data        Die Nutzdaten der Tabelle.
     * @param string $HookPrefix  Der Prefix des Webhook.
     * @param string $HookType    Ein String welcher als Parameter Type im Webhook übergeben wird.
     * @param string $HookId      Der Index aus dem Array $Data welcher die Nutzdaten (Parameter ID) des Webhook enthält.
     * @param int    $CurrentLine Die Aktuelle Zeile welche als Aktiv erzeugt werden soll.
     * @return string Der HTML-String.
     */
    protected function GetTable(array $Data, string $HookPrefix, string $HookType, string $HookId, int $CurrentLine = -1): string
    {
        $Config_Table = array_column(json_decode($this->ReadPropertyString('Table'), true), 'style', 'tag');
        $Config_Columns = json_decode($this->ReadPropertyString('Columns'), true);
        $Config_Rows = json_decode($this->ReadPropertyString('Rows'), true);
        $Config_Rows_BgColor = array_column($Config_Rows, 'bgcolor', 'row');
        $Config_Rows_Color = array_column($Config_Rows, 'color', 'row');
        $Config_Rows_Style = array_column($Config_Rows, 'style', 'row');

        $NewSecret = base64_encode(openssl_random_pseudo_bytes(12));
        $this->{'WebHookSecret' . $HookType} = $NewSecret;

        $HTMLData = $this->GetTableHeader($Config_Table, $Config_Columns);
        $pos = 0;
        if (count($Data) > 0) {
            foreach ($Data as $Line) {
                $Line['Position'] = $pos + 1;

                if (array_key_exists('Duration', $Line)) {
                    $Line['Duration'] = $this->ConvertSeconds((int) $Line['Duration']);
                } else {
                    $Line['Duration'] = '---';
                }

                $HookValue = (string) ($Line[$HookId] ?? '');
                $LineSecret = rawurlencode(base64_encode(sha1($NewSecret . '0' . $HookValue, true)));
                $LineIndex = ($Line['Position'] == $CurrentLine ? 'active' : ($pos % 2 ? 'odd' : 'even'));
                $TrStyle = [];
                if (($Config_Rows_BgColor[$LineIndex] ?? -1) >= 0) {
                    $TrStyle[] = 'background-color:#' . substr('000000' . dechex($Config_Rows_BgColor[$LineIndex]), -6);
                }
                if (($Config_Rows_Color[$LineIndex] ?? -1) >= 0) {
                    $TrStyle[] = 'color:#' . substr('000000' . dechex($Config_Rows_Color[$LineIndex]), -6);
                }
                if (($Config_Rows_Style[$LineIndex] ?? '') != '') {
                    $TrStyle[] = $Config_Rows_Style[$LineIndex];
                }
                // Die ID wird einmal URL-kodiert übertragen; der Webhook erhält sie über $_GET wieder dekodiert.
                $HTMLData .= '<tr style="' . htmlspecialchars(implode(';', $TrStyle)) . ';" onclick="eval(document.getElementById(\'script' . $this->InstanceID . '\').innerHTML.toString()); window.xhrGet' . $this->InstanceID . '({ url: \'hook/' . $HookPrefix . $this->InstanceID . '?Type=' . rawurlencode($HookType) . '&ID=' . rawurlencode($HookValue) . '&Secret=' . $LineSecret . '\' });">';

                $td = [];
                foreach ($Config_Columns as $Column) {
                    if ($Column['show'] !== true) {
                        continue;
                    }
                    if ($Column['key'] == 'Play') {
                        $Content = ($Line['Position'] == $CurrentLine ? '<div class="iconMediumSpinner ipsIconArrowRight" style="width: 100%; background-position: center center;"></div>' : '');
                    } else {
                        $Content = htmlspecialchars((string) ($Line[$Column['key']] ?? ''), ENT_QUOTES);
                    }
                    $TdStyle = [];
                    $TdStyle[] = 'text-align:' . $Column['align'];
                    $TdStyle[] = $Column['style'];

                    $td[$Column['index']] = '<td style="' . htmlspecialchars(implode(';', $TdStyle)) . ';">' . $Content . '</td>';
                }
                ksort($td);
                $HTMLData .= implode('', $td) . '</tr>' . PHP_EOL;
                $pos++;
            }
        }
        $HTMLData .= $this->GetTableFooter();
        return $HTMLData;
    }

    /**
     * Liefert den Footer der HTML-Tabelle.
     *
     * @return string HTML-String
     */
    protected function GetTableFooter()
    {
        $table = '</tbody>' . PHP_EOL;
        $table .= '</table>' . PHP_EOL;
        return $table;
    }
}
