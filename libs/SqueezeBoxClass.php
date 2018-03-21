<?php

/*
 * @addtogroup squeezebox
 * @{
 *
 * @package       Squeezebox
 * @file          SqueezeBoxClass.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 *
 */

/**
 * Trait mit allen Profilen der Instanz Squeezebox-Device
 */
trait LSQProfile
{
    /**
     * Erzeugt alle benötigten Profile.
     */
    private function CreateProfile()
    {
        $this->RegisterProfileIntegerEx("LSQ.Status", "Information", "", "", array(
            array(0, "Prev", "", -1),
            array(1, "Stop", "", -1),
            array(2, "Play", "", -1),
            array(3, "Pause", "", -1),
            array(4, "Next", "", -1)
        ));
        $this->RegisterProfileInteger("LSQ.Intensity", "Intensity", "", " %", 0, 100, 1);
        $this->RegisterProfileInteger("LSQ.Pitch", "Intensity", "", " %", 80, 120, 1);
        $this->RegisterProfileIntegerEx("LSQ.Shuffle", "Shuffle", "", "", array(
            array(0, "off", "", -1),
            array(1, $this->Translate("Title"), "", -1),
            array(2, "Album", "", -1)
        ));
        $this->RegisterProfileIntegerEx("LSQ.Repeat", "Repeat", "", "", array(
            array(0, "off", "", -1),
            array(1, $this->Translate("Title"), "", -1),
            array(2, "Playlist", "", -1)
        ));
        $this->RegisterProfileIntegerEx("LSQ.Preset", "Speaker", "", "", array(
            array(1, "1", "", -1),
            array(2, "2", "", -1),
            array(3, "3", "", -1),
            array(4, "4", "", -1),
            array(5, "5", "", -1),
            array(6, "6", "", -1)
        ));
        $this->RegisterProfileIntegerEx("LSQ.SleepTimer", "Gear", "", "", array(
            array(0, "0", "", -1),
            array(900, "900", "", -1),
            array(1800, "1800", "", -1),
            array(2700, "2700", "", -1),
            array(3600, "3600", "", -1),
            array(5400, "5400", "", -1)
        ));
        $this->RegisterProfileIntegerEx("LSQ.Randomplay", "Shuffle", "", "", array(
            array(0, $this->Translate("off"), "", -1),
            array(1, "Track", "", -1),
            array(2, "Album", "", -1),
            array(3, $this->Translate("Artist"), "", -1),
            array(4, $this->Translate("Year"), "", -1),
        ));
    }

    /**
     * Löscht alle nicht mehr benötigten Profile.
     */
    private function DeleteProfile()
    {
        $this->UnregisterProfil("LSQ.Tracklist." . $this->InstanceID);
        $this->UnregisterProfil("LSQ.Status");
        $this->UnregisterProfil("LSQ.Intensity");
        $this->UnregisterProfil("LSQ.Pitch");
        $this->UnregisterProfil("LSQ.Shuffle");
        $this->UnregisterProfil("LSQ.Repeat");
        $this->UnregisterProfil("LSQ.Preset");
        $this->UnregisterProfil("LSQ.SleepTimer");
    }

    /**
     * Löscht alle nicht mehr Profile des Testmoduls.
     */
    private function DeleteOldProfile()
    {
        if (IPS_VariableProfileExists('Status.Squeezebox')) {
            IPS_DeleteVariableProfile('Status.Squeezebox');
        }
        if (IPS_VariableProfileExists('Preset.Squeezebox')) {
            IPS_DeleteVariableProfile('Preset.Squeezebox');
        }
        if (IPS_VariableProfileExists('Intensity.Squeezebox')) {
            IPS_DeleteVariableProfile('Intensity.Squeezebox');
        }
        if (IPS_VariableProfileExists('Pitch.Squeezebox')) {
            IPS_DeleteVariableProfile('Pitch.Squeezebox');
        }
        if (IPS_VariableProfileExists('Shuffle.Squeezebox')) {
            IPS_DeleteVariableProfile('Shuffle.Squeezebox');
        }
        if (IPS_VariableProfileExists('Repeat.Squeezebox')) {
            IPS_DeleteVariableProfile('Repeat.Squeezebox');
        }
        if (IPS_VariableProfileExists("Tracklist.Squeezebox." . $this->InstanceID)) {
            IPS_DeleteVariableProfile("Tracklist.Squeezebox." . $this->InstanceID);
        }
        if (IPS_VariableProfileExists('SleepTimer.Squeezebox')) {
            IPS_DeleteVariableProfile('SleepTimer.Squeezebox');
        }
    }

}

/**
 * Trait mit allen Profilen der Instanz Squeezebox-Device
 */
trait LMSProfile
{
    /**
     * Erzeugt alle benötigten Profile.
     */
    private function CreateProfile()
    {
        $this->RegisterProfileIntegerEx("LMS.Scanner", "Gear", "", "", array(
            array(0, $this->Translate("standby"), "", -1),
            array(1, $this->Translate("abort"), "", -1),
            array(2, $this->Translate("scan"), "", -1),
            array(3, $this->Translate("only playlists"), "", -1),
            array(4, $this->Translate("completely"), "", -1)
        ));
        $this->RegisterProfileInteger("LMS.PlayerSelect." . $this->InstanceID, "Speaker", "", "", 0, 0, 0);
    }

    /**
     * Löscht alle nicht mehr benötigten Profile.
     */
    private function DeleteProfile()
    {
        $this->UnregisterProfil("LMS.PlayerSelect" . $this->InstanceID);
        $this->UnregisterProfil("LMS.Scanner");
    }

    /**
     * Löscht alle nicht mehr Profile des Testmoduls.
     */
    private function DeleteOldProfile()
    {
        if (IPS_VariableProfileExists('Scanner.SqueezeboxServer')) {
            IPS_DeleteVariableProfile('Scanner.SqueezeboxServer');
        }
        if (IPS_VariableProfileExists("PlayerSelect" . $this->InstanceID . ".SqueezeboxServer")) {
            IPS_DeleteVariableProfile("PlayerSelect" . $this->InstanceID . ".SqueezeboxServer");
        }
    }

}

/**
 * Definiert eine Datensatz zum Versenden an des LMS.
 *
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 * @example <b>Ohne</b>
 */
class LMSData extends stdClass
{

    use UTF8Coder;
    /**
     * Adresse des Gerätes.
     * @var string
     */
    public $Address;

    /**
     * Alle Kommandos als Array.
     * @var string|array
     */
    public $Command;

    /**
     * Alle Daten des Kommandos.
     * @var string|array
     */
    public $Data;

    /**
     * Flag ob auf Antwort gewartet werden muss.
     * @var bool
     */
    public $needResponse;

    /**
     * Anzahl der versendeten Daten.
     * @var int
     */
    private $SendValues;

    /**
     * Erzeugt ein Objekt vom Typ LMSData.
     * @param string|array $Command Kommando
     * @param string|array $Data Nutzdaten
     * @param bool $needResponse Auf Antwort warten.
     */
    public function __construct($Command = '', $Data = '', $needResponse = true)
    {
        $this->Address = '';
        if (is_array($Command)) {
            $this->Command = $Command;
        } else {
            $this->Command = array($Command);
        }
        if (is_array($Data)) {
            $this->Data = array_map('rawurlencode', $Data);
        } else {
            $this->Data = rawurlencode($Data);
        }
        $this->needResponse = $needResponse;
    }

    /**
     * Erzeugt einen String für den Datenaustausch mit einer IO-Instanz.
     * @param type $GUID Die TX-GUID
     * @return type Der JSON-String für den Datenaustausch.
     */
    public function ToJSONStringForLMS($GUID)
    {
        return json_encode(array("DataID" => $GUID, "Buffer" => utf8_encode($this->ToRawStringForLMS())));
    }

    /**
     * Erzeugt einen String für das CLI des LMS.
     * @return string CLI-Befehl für den LMS.
     */
    public function ToRawStringForLMS()
    {
        $Command = implode(' ', $this->Command);
        $this->SendValues = 0;

        if (is_array($this->Data)) {
            $Data = implode(' ', $this->Data);
            $this->SendValues = count($this->Data);
        } else {
            $Data = $this->Data;
            if (($this->Data !== null) and ( $this->Data != '%3F')) {
                $this->SendValues = 1;
            }
        }
        return trim($this->Address . ' ' . trim($Command) . ' ' . trim($Data)) . chr(0x0d);
    }

    /**
     * Liefert das Suchmuster für die SendQueue.
     * @return string Das Suchmuster.
     */
    public function GetSearchPatter()
    {
        return $this->Address . implode('', $this->Command);
    }

    /**
     * Befüllt das Objekt mit neuen Daten aus dem Datenaustausch.
     * @param stdClass $Data Das Objekt aus dem Datenaustausch.
     */
    public function CreateFromGenericObject($Data)
    {
        $this->Address = $this->DecodeUTF8($Data->Address);
        $this->Command = $this->DecodeUTF8($Data->Command);
        $this->Data = $this->DecodeUTF8($Data->Data);
        if (property_exists($Data, 'needResponse')) {
            $this->needResponse = $Data->needResponse;
        }
    }

    /**
     * Schneidet aus den Netzdaten die Anzahl der versendeten Daten ab.
     */
    public function SliceData()
    {
        $this->Data = array_slice($this->Data, $this->SendValues);
    }

    /**
     * Erzeugt ein JSON-String für den internen Datenaustausch dieses Moduls.
     * @param string $GUID GUID des Datenpaketes.
     * @return string Der JSON-String.
     */
    public function ToJSONString($GUID)
    {
        return json_encode(array("DataID"       => $GUID,
            "Address"      => $this->EncodeUTF8($this->Address),
            "Command"      => $this->EncodeUTF8($this->Command),
            "Data"         => $this->EncodeUTF8($this->Data),
            "needResponse" => $this->needResponse
        ));
    }

}

/**
 * Klasse mit den Empfangenen Daten vom LMS
 *
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 * @example <b>Ohne</b>
 */
class LMSResponse extends LMSData
{
    /**
     * Antwort ist vom LMS-Server
     *
     * @static
     */
    const isServer = 0;

    /**
     * Antwort ist von einer MAC-Adresse.
     *
     * @static
     */
    const isMAC = 1;

    /**
     * Antwort ist von einer IP-Adresse.
     *
     * @static
     */
    const isIP = 2;

    /**
     * Enthält den Type des Versenders einer Antwort
     *
     * @var int Kann ::isServer, ::isMAC oder ::isIP sein.
     */
    public $Device;

    /**
     * Zerlegt eine Antwort des LMS und erzeugt daraus ein LMSResponse-Objekt.
     *
     * @param string $RawString
     */
    public function __construct(string $RawString)
    {
        $array = explode(' ', $RawString); // Antwortstring in Array umwandeln
        if (strpos($array[0], '%3A') == 2) { //isMAC
            $this->Device = LMSResponse::isMAC;
            $this->Address = rawurldecode(array_shift($array));
        } elseif (strpos($array[0], '.')) { //isIP
            $this->Device = LMSResponse::isIP;
            $this->Address = array_shift($array);
        } else { // isServer
            $this->Device = LMSResponse::isServer;
            $this->Address = "";
        }
        $this->Command = array(array_shift($array));
        if ($this->Device == LMSResponse::isServer) {
            if (count($array) <> 0) {
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
                        if (in_array($array[0], array('rename', 'delete', 'new', 'tracks', 'edit'))) {
                            $this->Command[1] = array_shift($array);
                        }
                        break;
                }
            }
        } else {
            if (count($array) <> 0) {
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
                        if (in_array($array[0], array('play', 'load', 'insert', 'add'))) {
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
                    case 'prefset':
                    default:
                        $this->Command[1] = $this->Command[0];
                        $this->Command[0] = 'ignore';
                        break;
                }
            }
        }
        if (count($array) == 0) {
            $array[0] = "";
        }

        $this->Data = array_map('rawurldecode', $array);
    }

    /**
     * Erzeugt aus dem Objekt einen JSON-String.
     *
     * @param string $GUID GUID welche in den JSON-String eingebunden wird.
     * @return string Der JSON-String für den Datenaustausch
     */
    public function ToJSONStringForDevice(string $GUID)
    {
        return json_encode(array(
            "DataID"  => $GUID,
            "Address" => $this->EncodeUTF8($this->Address),
            "Command" => $this->EncodeUTF8($this->Command),
            "Data"    => $this->EncodeUTF8($this->Data)
        ));
    }

}

/**
 * Zerlegt einen getaggten Datensatz in Name und Wert.
 *
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 * @example <b>Ohne</b>
 */
class LMSTaggingData extends stdClass
{
    /**
     * @access public
     * @var string Der Name des Datensatzes.
     */
    public $Name;

    /**
     * @access public
     * @var string Der Inhalt des Datensatzen.
     */
    public $Value;

    /**
     * Erzeugt ein LMSTaggingData-Objekt aus $TaggedDataLine.
     *
     * @access public
     * @param string $TaggedDataLine Die Rohdaten.
     */
    public function __construct($TaggedDataLine)
    {
        $Part = explode(':', $TaggedDataLine); //
        $this->Name = array_shift($Part);
        if (count($Part) > 1) {
            $this->Value = implode(':', $Part);
        } else {
            $this->Value = array_shift($Part);
        }
        if (is_int($this->Value)) {
            $this->Value = (int) $this->Value;
        } else {
            $this->Value = (string) $this->Value;
        }
    }

}

/**
 * Zerlegt einen Array aus getaggten Datensätzen.
 *
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 * @example <b>Ohne</b>
 */
class LMSTaggingArray extends stdClass
{
    /**
     * @var array Enthält die Nutzdaten
     */
    private $DataArray;

    /**
     *
     * @var array Enthält alle zu dekodierenen Index mit ihren Typenumwandlungen.
     */
    public static $DataFields = array(
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
    );

    /**
     * Erzeugt aus einem Array mit getaggten Daten ein mehrdimensionales Array.
     *
     * @access public
     * @param array $TaggedData Das Array mit allen getaggten Zeilen.
     * @param string $UsedIdIndex Der zu verwendene Index welcher als Trenner zwischen den Objekten fungiert.
     * @param string $Filter Ein auf alle Index anzuwendender Filter, nur Index welche den Filter am Anfang enthalten werden übernommen.
     */
    public function __construct(array $TaggedData, $UsedIdIndex = 'id', $Filter = '')
    {
        $id = -1;
        $DataArray = array();
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
                    if (is_int($Part->Value)) {
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


            if (static::$DataFields[$Index] == 0) {
                $DataArray[$id][$Index] = (bool) ($Part->Value);
            } elseif (static::$DataFields[$Index] == 1) {
                if (is_int($Part->Value)) {
                    $DataArray[$id][$Index] = (int) $Part->Value;
                } else {
                    $DataArray[$id][$Index] = (string) rawurldecode($Part->Value);
                }
            } else {
                $DataArray[$id][$Index] = rawurldecode($Part->Value);
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
     * Liefert das Array mit allen Nutzdaten
     *
     * @access public
     * @return array Das Array mit allen Nutzdaten.
     */
    public function DataArray()
    {
        return $this->DataArray;
    }

    /**
     * Bricht das Array auf einen bestimmte Index runter.
     *
     * @access public
     * @param string $Index Der zu verwendende Index.
     */
    public function Compact(string $Index)
    {
        array_walk($this->DataArray, array($this, 'FlatDataArray'), $Index);
    }

    /**
     * Callback-Funktion für Compact
     *
     * @access protected
     * @param mixed $Item Das aktuelle Item.
     * @param mixed Der übergeben Index von Item.
     * @param string $Index Der Index aus Item der hochkopiert wird.
     */
    protected function FlatDataArray(&$Item, $Key, $Index)
    {
        $Item = $Item[$Index];
    }

    /**
     * Liefert die Anzahl der Daten im DataArray.
     *
     * @access public
     * @return int Anzahl der Einträge in DataArray.
     */
    public function Count()
    {
        return count($this->DataArray);
    }

}

/**
 * Zerlegt einen Array aus getaggten Datensätzen zu SongInfos
 *
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 * @example <b>Ohne</b>
 */
class LMSSongInfo extends stdClass
{
    /**
     * Ein Array das alle Songs enthält
     * @var array
     */
    private $SongArray = array();

    /**
     * Die gesamte Spielzeit in Sekunden.
     * @var int
     */
    private $Duration = 0;

    /**
     * Enthält die Zuordnung zu den Datentypen.
     * @var array
     */
    public static $SongFields = array(
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
        'Samplesize'       => 3, // I
        'Remote_title'     => 3, //N
        'Genre_id'         => 1, //p
        'Artist_id'        => 1, //s
        'Year'             => 1, // Y
        'Name'             => 3,
        'Modified'         => 0,
        'Playlist'         => 3
    );

    /**
     * Erzeugt aus einem Array mit getaggten Daten ein LMSSongInfo Objekt.
     * @param array $TaggedData
     */
    public function __construct(array $TaggedData)
    {
        $id = -1;
        $Songs = array();
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
                $Songs[$id][$Index] = rawurldecode($Part->Value);
            }
        }
        if ((count($Songs) <> 1) and isset($Songs[-1])) {
            unset($Songs[-1]);
        }
        $this->SongArray = $Songs;
        $this->Duration = $Duration;
    }

    /**
     * Liefert den ersten Datensatz der Songs.
     * @return array Der Datensatz des Songs.
     */
    public function GetSong()
    {
        return array_shift($this->SongArray);
    }

    /**
     * Liefert alle Datensätze der Songs.
     * @return array Alle Datensätze.
     */
    public function GetAllSongs()
    {
        return $this->SongArray;
    }

    /**
     * Liefert die Laufzeit alle Songs.
     * @return int Die Laufzeit in Sekunden.
     */
    public function GetTotalDuration()
    {
        return $this->Duration;
    }

    /**
     * Liefert die Anzahl alles Songs.
     * @return int Die Anzahl der Songs.
     */
    public function CountAllSongs()
    {
        return count($this->SongArray);
    }

}

/**
 * Prüft und korrigiert eine URL
 */
trait LMSSongURL
{
    /**
     * Prüft und korrigiert eine URL
     * @param type $SongURL
     * @return boolean True wenn URL valid ist, sonst false
     */
    protected function GetValidSongURL(&$SongURL)
    {
        if (!is_string($SongURL)) {
            trigger_error(sprintf($this->Translate("%s must be string."), "URL"), E_USER_NOTICE);
            return false;
        }
        $valid = strpos($SongURL, '://');
        if ($valid === false) {
            trigger_error(sprintf($this->Translate("%s not valid."), "URL"), E_USER_NOTICE);
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
    /**
     * Konvertiert die alte Playlist-Config.
     * @return boolean True wenn alte Config konvertiert wurde, sonst false.
     */
    protected function ConvertPlaylistConfig()
    {
        $ID = $this->ReadPropertyInteger('Playlistconfig');
        if ($ID == 0) {
            return false;
        }

        IPS_SetName($ID, IPS_GetName($ID) . ' (Old used by:' . $this->InstanceID . ')');
        $Style = $this->GenerateHTMLStyleProperty();
        IPS_SetProperty($this->InstanceID, 'Table', json_encode($Style['Table']));
        IPS_SetProperty($this->InstanceID, 'Columns', json_encode($Style['Columns']));
        IPS_SetProperty($this->InstanceID, 'Rows', json_encode($Style['Rows']));
        IPS_SetProperty($this->InstanceID, 'Playlistconfig', 0);
        IPS_ApplyChanges($this->InstanceID);
        return true;
    }

    /**
     * Liefert den Header der HTML-Tabelle.
     *
     * @access private
     * @param array $Config Die Kofiguration der Tabelle
     * @return string HTML-String
     */
    protected function GetTableHeader($Config_Table, $Config_Columns)
    {
        $table = "";
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
        $colgroup = array();
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
        $th = array();
        foreach ($Config_Columns as $Column) {
            if ($Column['show'] !== true) {
                continue;
            }
            $ThStyle = array();
            if ($Column['color'] >= 0) {
                $ThStyle[] = 'color:#' . substr("000000" . dechex($Column['color']), -6);
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
     * Liefert den Inhalt der HTML-Box für ein Tabelle.
     * @param array $Data Die Nutzdaten der Tabelle.
     * @param string $HookPrefix Der Prefix des Webhook.
     * @param string $HookType Ein String welcher als Parameter Type im Webhook übergeben wird.
     * @param string $HookId Der Index aus dem Array $Data welcher die Nutzdaten (Parameter ID) des Webhook enthält.
     * @param int $CurrentLine Die Aktuelle Zeile welche als Aktiv erzeugt werden soll.
     * @return string Der HTML-String.
     */
    protected function GetTable($Data, $HookPrefix, $HookType, $HookId, $CurrentLine = -1)
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

                $LineSecret = rawurlencode(base64_encode(sha1($NewSecret . "0" . $Line[$HookId], true)));
                $LineIndex = ($Line['Position'] == $CurrentLine ? 'active' : ($pos % 2 ? 'odd' : 'even'));
                $TrStyle = array();
                if ($Config_Rows_BgColor[$LineIndex] >= 0) {
                    $TrStyle[] = 'background-color:#' . substr("000000" . dechex($Config_Rows_BgColor[$LineIndex]), -6);
                }
                if ($Config_Rows_Color[$LineIndex] >= 0) {
                    $TrStyle[] = 'color:#' . substr("000000" . dechex($Config_Rows_Color[$LineIndex]), -6);
                }
                $TdStyle[] = $Config_Rows_Style[$LineIndex];
                $HTMLData .= '<tr style="' . implode(';', $TrStyle) . ';" onclick="eval(document.getElementById(\'script' . $this->InstanceID . '\').innerHTML.toString()); window.xhrGet' . $this->InstanceID . '({ url: \'hook/' . $HookPrefix . $this->InstanceID . '?Type=' . $HookType . '&ID=' . ($HookId == 'Url' ? rawurlencode($Line[$HookId]) : $Line[$HookId]) . '&Secret=' . $LineSecret . '\' });">';

                $td = array();
                foreach ($Config_Columns as $Column) {
                    if ($Column['show'] !== true) {
                        continue;
                    }
                    if (!array_key_exists($Column['key'], $Line)) {
                        $Line[$Column['key']] = '';
                    }
                    $TdStyle = array();
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
     * @access private
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
     * Liefert die Rohdaten eines Covers, welches vom LMS geladen wurde.
     * @param string $CoverID Die ID des Covers.
     * @param string $Size Die Größe des Covers in Pixel.
     * @param string $Player Die Player-MAC.
     * @return boolean|string Die Rohdaten des Covers, oder false im Fehlerfall.
     */
    protected function GetCover(string $CoverID, string $Size, string $Player)
    {
        $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $IoID = IPS_GetInstance($SplitterID)['ConnectionID'];
        $Hostname = IPS_GetProperty($IoID, "Host");
        $Webport = IPS_GetProperty($SplitterID, 'Webport');
        $Login = array(
            "AuthUser" => IPS_GetProperty($SplitterID, 'User'),
            "AuthPass" => IPS_GetProperty($SplitterID, 'Password'),
            "Timeout"  => 5000
        );

        if ($Hostname === "") {
            return false;
        }
        $Host = gethostbyname($Hostname);
        $Host .= ":" . $Webport;
        if ($Player <> "") {
            $Player = "?player=" . rawurlencode($Player);
            $CoverID = "current";
        }
        $URL = "http://" . $Host . "/music/" . $CoverID . "/" . $Size . ".png" . $Player;
        $this->SendDebug('GetCover', $URL, 0);
        return @Sys_GetURLContentEx($URL, $Login);
    }

}

/**
 * Trait für die Umwandlung von Objekten von und nach UTF8.
 */
trait UTF8Coder
{
    /**
     * Führt eine UTF8-Dekodierung für einen String oder ein Objekt durch (rekursiv)
     *
     * @access protected
     * @param string|object $item Zu dekodierene Daten.
     * @return string|object Dekodierte Daten.
     */
    protected function DecodeUTF8($item)
    {
        if (is_string($item)) {
            $item = utf8_decode($item);
        } elseif (is_object($item)) {
            foreach ($item as $property => $value) {
                $item->{$property} = $this->DecodeUTF8($value);
            }
        } elseif (is_array($item)) {
            foreach ($item as $property => $value) {
                $item[$property] = $this->DecodeUTF8($value);
            }
        }

        return $item;
    }

    /**
     * Führt eine UTF8-Enkodierung für einen String oder ein Objekt durch (rekursiv)
     *
     * @access protected
     * @param string|object $item Zu Enkodierene Daten.
     * @return string|object Enkodierte Daten.
     */
    protected function EncodeUTF8($item)
    {
        if (is_string($item)) {
            $item = utf8_encode($item);
        } elseif (is_object($item)) {
            foreach ($item as $property => $value) {
                $item->{$property} = $this->EncodeUTF8($value);
            }
        } elseif (is_array($item)) {
            foreach ($item as $property => $value) {
                $item[$property] = $this->EncodeUTF8($value);
            }
        }
        return $item;
    }

}

/** @} */
