<?php

declare(strict_types=1);

namespace SqueezeBox;

require_once __DIR__ . '/TimeConvert.php';  // diverse Klassen

/**
 * @package       Squeezebox
 * @file          SqueezeBoxClass.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.00
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
 * Trait mit allen Profilen der Instanz Squeezebox-Device.
 * @method void UnregisterProfile(string $Name)
 * @method void RegisterProfileIntegerEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, int $MaxValue = -1, float $StepSize = 0)
 * @method void RegisterProfileInteger(string $Name, string $Icon, string $Prefix, string $Suffix, int $MinValue, int $MaxValue, int $StepSize)
 */
trait LSQProfile
{
    /**
     * CreateProfile
     * Erzeugt alle benötigten Profile.
     *
     * @return void
     */
    private function CreateProfile(): void
    {
        $this->UnregisterProfile('LSQ.Status');
        $this->UnregisterProfile('LSQ.Volume');
        $this->UnregisterProfile('LSQ.Intensity');
        $this->UnregisterProfile('LSQ.Repeat');
        $this->RegisterProfileInteger('LSQ.Pitch', 'Intensity', '', ' %', 80, 120, 1);
        $this->RegisterProfileIntegerEx('LSQ.Shuffle', 'Shuffle', '', '', [
            [0, $this->Translate('Off'), '', -1],
            [1, $this->Translate('Title'), '', -1],
            [2, 'Album', '', -1]
        ]);
        $this->RegisterProfileIntegerEx('LSQ.Preset', 'Execute', '', '', [
            [1, '1', '', -1],
            [2, '2', '', -1],
            [3, '3', '', -1],
            [4, '4', '', -1],
            [5, '5', '', -1],
            [6, '6', '', -1],
            [7, '7', '', -1],
            [8, '8', '', -1],
            [9, '9', '', -1],
            [10, '10', '', -1]
        ]);
        $this->RegisterProfileIntegerEx('LSQ.SleepTimer', 'Gear', '', '', [
            [0, '%d', '', -1],
            [900, '%d', '', -1],
            [1800, '%d', '', -1],
            [2700, '%d', '', -1],
            [3600, '%d', '', -1],
            [5400, '%d', '', -1]
        ]);
        $this->RegisterProfileIntegerEx('LSQ.Randomplay', 'Shuffle', '', '', [
            [0, $this->Translate('Off'), '', -1],
            [1, 'Track', '', -1],
            [2, 'Album', '', -1],
            [3, $this->Translate('Artist'), '', -1],
            [4, $this->Translate('Year'), '', -1],
        ]);
    }

    /**
     * DeleteProfile
     * Löscht alle nicht mehr benötigten Profile.
     *
     * @return void
     */
    private function DeleteProfile(): void
    {
        $this->UnregisterProfile('LSQ.Tracklist.' . $this->InstanceID);
        $this->UnregisterProfile('LSQ.Pitch');
        $this->UnregisterProfile('LSQ.Shuffle');
        $this->UnregisterProfile('LSQ.Preset');
        $this->UnregisterProfile('LSQ.SleepTimer');
    }
}

/**
 * Trait mit allen Profilen der Instanz Squeezebox-Device.
 */
trait LMSProfile
{
    /**
     * CreateProfile
     * Erzeugt alle benötigten Profile.
     *
     * @return void
     */
    private function CreateProfile(): void
    {
        $this->RegisterProfileIntegerEx('LMS.Scanner', 'Gear', '', '', [
            [0, $this->Translate('standby'), '', -1],
            [1, $this->Translate('abort'), '', -1],
            [2, $this->Translate('scan'), '', -1],
            [3, $this->Translate('only playlists'), '', -1],
            [4, $this->Translate('completely'), '', -1]
        ]);
        $this->RegisterProfileInteger('LMS.PlayerSelect.' . $this->InstanceID, 'Speaker', '', '', 0, 0, 0);
    }

    /**
     * DeleteProfile
     * Löscht alle nicht mehr benötigten Profile.
     *
     * @return void
     */
    private function DeleteProfile(): void
    {
        $this->UnregisterProfile('LMS.PlayerSelect.' . $this->InstanceID);
        $this->UnregisterProfile('LMS.Scanner');
    }
}

/**
 * Definiert eine Datensatz zum Versenden an des LMS.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.00
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
     * @param string $GUID GUID des Datenpaketes.
     * @return string Der JSON-String.
     */
    public function ToJSONString(string $GUID): string
    {
        return json_encode(['DataID'       => $GUID,
            'Address'                      => $this->Address,
            'Command'                      => $this->Command,
            'Data'                         => $this->Data,
            'needResponse'                 => $this->needResponse
        ]);
    }
}

/**
 * Klasse mit den Empfangenen Daten vom LMS.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.00
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
                        if (in_array($array[0], ['play', 'load', 'insert', 'add'])) {
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
                    case 'status':
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
     * @param string $GUID GUID welche in den JSON-String eingebunden wird.
     * @return string Der JSON-String für den Datenaustausch
     */
    public function ToJSONStringForDevice(string $GUID): string
    {
        return json_encode(['DataID'  => $GUID,
            'Address'                 => $this->Address,
            'Command'                 => $this->Command,
            'Data'                    => $this->Data
        ]);
    }
}

/**
 * Zerlegt einen getaggten Datensatz in Name und Wert.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.00
 */
class LMSTaggingData extends \stdClass
{
    /**
     * @var string Der Name des Datensatzes.
     */
    public string $Name;

    /**
     * @var string Der Inhalt des Datensatz.
     */
    public string $Value;

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
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.00
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
                        $id = rawurldecode($Part->Value);
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
                    if (is_numeric($Part->Value)) {
                        $DataArray[$id][$Index] = (int) $Part->Value;
                    } else {
                        $DataArray[$id][$Index] = (string) rawurldecode((string) $Part->Value);
                    }
                    break;
                default:
                    $DataArray[$id][$Index] = rawurldecode((string) $Part->Value);
                    break;
            }
        }
        if (isset($DataArray[-1])) {
            if (count($DataArray) == 1) {
                $DataArray = $DataArray[-1];
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
        array_walk($this->DataArray, [$this, 'FlatDataArray'], $Index);
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

    /**
     * FlatDataArray
     * Callback-Funktion für Compact.
     *
     * @param mixed $Item Das aktuelle Item.
     * @param mixed Der übergeben Index von Item.
     * @param string $Index Der Index aus Item der hochkopiert wird.
     */
    protected function FlatDataArray(&$Item, $Key, $Index): void
    {
        $Item = $Item[$Index];
    }
}

/**
 * Zerlegt einen Array aus getaggten Datensätzen zu SongInfos.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.00
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
        $Playlist = ((new LMSTaggingData($TaggedData[0]))->Name == 'playlist index') ? true : false;
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
                $Duration = +intval($Part->Value);
            }
            if (static::$SongFields[$Index] == 0) {
                $Songs[$id][$Index] = (bool) ($Part->Value);
            } elseif (static::$SongFields[$Index] == 1) {
                $Songs[$id][$Index] = intval($Part->Value);
            } else {
                if (is_string($Part->Value)) {
                    $Songs[$id][$Index] = rawurldecode($Part->Value);
                    if ($Index == 'Remote_title') {
                        $Songs[$id]['Title'] = $Songs[$id]['Remote_title'];
                    }
                } else {
                    $Songs[$id][$Index] = $Part->Value;
                }
            }
        }
        if ((count($Songs) != 1) && isset($Songs[-1])) {
            unset($Songs[-1]);
        }
        $this->SongArray = $Songs;
        $this->Duration = $Duration;
    }

    /**
     * GetSong
     * Liefert den ersten Datensatz der Songs.
     *
     * @return array Der Datensatz des Songs.
     */
    public function GetSong(): array
    {
        return array_shift($this->SongArray);
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
        if (!is_string($SongURL)) {
            trigger_error(sprintf($this->Translate('%s must be string.'), 'URL'), E_USER_NOTICE);
            return false;
        }
        $valid = strpos($SongURL, '://');
        if ($valid === false) {
            trigger_error(sprintf($this->Translate('%s not valid.'), 'URL'), E_USER_NOTICE);
            return false;
        }
        $SongURL = str_replace('\\', '/', $SongURL);
        return true;
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
                    $Line['Duration'] = $this->ConvertSeconds($Line['Duration']);
                } else {
                    $Line['Duration'] = '---';
                }

                $Line['Play'] = ($Line['Position'] == $CurrentLine ? '<div class="iconMediumSpinner ipsIconArrowRight" style="width: 100%; background-position: center center;"></div>' : '');

                $LineSecret = rawurlencode(base64_encode(sha1($NewSecret . '0' . $Line[$HookId], true)));
                $LineIndex = ($Line['Position'] == $CurrentLine ? 'active' : ($pos % 2 ? 'odd' : 'even'));
                $TrStyle = [];
                if ($Config_Rows_BgColor[$LineIndex] >= 0) {
                    $TrStyle[] = 'background-color:#' . substr('000000' . dechex($Config_Rows_BgColor[$LineIndex]), -6);
                }
                if ($Config_Rows_Color[$LineIndex] >= 0) {
                    $TrStyle[] = 'color:#' . substr('000000' . dechex($Config_Rows_Color[$LineIndex]), -6);
                }
                $TdStyle[] = $Config_Rows_Style[$LineIndex];
                $HTMLData .= '<tr style="' . implode(';', $TrStyle) . ';" onclick="eval(document.getElementById(\'script' . $this->InstanceID . '\').innerHTML.toString()); window.xhrGet' . $this->InstanceID . '({ url: \'hook/' . $HookPrefix . $this->InstanceID . '?Type=' . $HookType . '&ID=' . ($HookId == 'Url' ? rawurlencode($Line[$HookId]) : $Line[$HookId]) . '&Secret=' . $LineSecret . '\' });">';

                $td = [];
                foreach ($Config_Columns as $Column) {
                    if ($Column['show'] !== true) {
                        continue;
                    }
                    if (!array_key_exists($Column['key'], $Line)) {
                        $Line[$Column['key']] = '';
                    }
                    $TdStyle = [];
                    $TdStyle[] = 'text-align:' . $Column['align'];
                    $TdStyle[] = $Column['style'];

                    $td[$Column['index']] = '<td style="' . implode(';', $TdStyle) . ';">' . (string) $Line[$Column['key']] . '</td>';
                }
                ksort($td);
                $HTMLData .= implode('', $td) . '</tr>';
                $HTMLData .= '</tr>' . PHP_EOL;
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
    protected function GetCover(string $CoverID, string $Size, string $Player): bool|string
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
