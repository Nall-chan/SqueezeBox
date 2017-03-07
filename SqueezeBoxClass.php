<?

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

if (@constant('IPS_BASE') == null) //Nur wenn Konstanten noch nicht bekannt sind.
{
// --- BASE MESSAGE
    define('IPS_BASE', 10000);                             //Base Message
    define('IPS_KERNELSHUTDOWN', IPS_BASE + 1);            //Pre Shutdown Message, Runlevel UNINIT Follows
    define('IPS_KERNELSTARTED', IPS_BASE + 2);             //Post Ready Message
// --- KERNEL
    define('IPS_KERNELMESSAGE', IPS_BASE + 100);           //Kernel Message
    define('KR_CREATE', IPS_KERNELMESSAGE + 1);            //Kernel is beeing created
    define('KR_INIT', IPS_KERNELMESSAGE + 2);              //Kernel Components are beeing initialised, Modules loaded, Settings read
    define('KR_READY', IPS_KERNELMESSAGE + 3);             //Kernel is ready and running
    define('KR_UNINIT', IPS_KERNELMESSAGE + 4);            //Got Shutdown Message, unloading all stuff
    define('KR_SHUTDOWN', IPS_KERNELMESSAGE + 5);          //Uninit Complete, Destroying Kernel Inteface
// --- KERNEL LOGMESSAGE
    define('IPS_LOGMESSAGE', IPS_BASE + 200);              //Logmessage Message
    define('KL_MESSAGE', IPS_LOGMESSAGE + 1);              //Normal Message                      | FG: Black | BG: White  | STLYE : NONE
    define('KL_SUCCESS', IPS_LOGMESSAGE + 2);              //Success Message                     | FG: Black | BG: Green  | STYLE : NONE
    define('KL_NOTIFY', IPS_LOGMESSAGE + 3);               //Notiy about Changes                 | FG: Black | BG: Blue   | STLYE : NONE
    define('KL_WARNING', IPS_LOGMESSAGE + 4);              //Warnings                            | FG: Black | BG: Yellow | STLYE : NONE
    define('KL_ERROR', IPS_LOGMESSAGE + 5);                //Error Message                       | FG: Black | BG: Red    | STLYE : BOLD
    define('KL_DEBUG', IPS_LOGMESSAGE + 6);                //Debug Informations + Script Results | FG: Grey  | BG: White  | STLYE : NONE
    define('KL_CUSTOM', IPS_LOGMESSAGE + 7);               //User Message                        | FG: Black | BG: White  | STLYE : NONE
// --- MODULE LOADER
    define('IPS_MODULEMESSAGE', IPS_BASE + 300);           //ModuleLoader Message
    define('ML_LOAD', IPS_MODULEMESSAGE + 1);              //Module loaded
    define('ML_UNLOAD', IPS_MODULEMESSAGE + 2);            //Module unloaded
// --- OBJECT MANAGER
    define('IPS_OBJECTMESSAGE', IPS_BASE + 400);
    define('OM_REGISTER', IPS_OBJECTMESSAGE + 1);          //Object was registered
    define('OM_UNREGISTER', IPS_OBJECTMESSAGE + 2);        //Object was unregistered
    define('OM_CHANGEPARENT', IPS_OBJECTMESSAGE + 3);      //Parent was Changed
    define('OM_CHANGENAME', IPS_OBJECTMESSAGE + 4);        //Name was Changed
    define('OM_CHANGEINFO', IPS_OBJECTMESSAGE + 5);        //Info was Changed
    define('OM_CHANGETYPE', IPS_OBJECTMESSAGE + 6);        //Type was Changed
    define('OM_CHANGESUMMARY', IPS_OBJECTMESSAGE + 7);     //Summary was Changed
    define('OM_CHANGEPOSITION', IPS_OBJECTMESSAGE + 8);    //Position was Changed
    define('OM_CHANGEREADONLY', IPS_OBJECTMESSAGE + 9);    //ReadOnly was Changed
    define('OM_CHANGEHIDDEN', IPS_OBJECTMESSAGE + 10);     //Hidden was Changed
    define('OM_CHANGEICON', IPS_OBJECTMESSAGE + 11);       //Icon was Changed
    define('OM_CHILDADDED', IPS_OBJECTMESSAGE + 12);       //Child for Object was added
    define('OM_CHILDREMOVED', IPS_OBJECTMESSAGE + 13);     //Child for Object was removed
    define('OM_CHANGEIDENT', IPS_OBJECTMESSAGE + 14);      //Ident was Changed
// --- INSTANCE MANAGER
    define('IPS_INSTANCEMESSAGE', IPS_BASE + 500);         //Instance Manager Message
    define('IM_CREATE', IPS_INSTANCEMESSAGE + 1);          //Instance created
    define('IM_DELETE', IPS_INSTANCEMESSAGE + 2);          //Instance deleted
    define('IM_CONNECT', IPS_INSTANCEMESSAGE + 3);         //Instance connectged
    define('IM_DISCONNECT', IPS_INSTANCEMESSAGE + 4);      //Instance disconncted
    define('IM_CHANGESTATUS', IPS_INSTANCEMESSAGE + 5);    //Status was Changed
    define('IM_CHANGESETTINGS', IPS_INSTANCEMESSAGE + 6);  //Settings were Changed
    define('IM_CHANGESEARCH', IPS_INSTANCEMESSAGE + 7);    //Searching was started/stopped
    define('IM_SEARCHUPDATE', IPS_INSTANCEMESSAGE + 8);    //Searching found new results
    define('IM_SEARCHPROGRESS', IPS_INSTANCEMESSAGE + 9);  //Searching progress in %
    define('IM_SEARCHCOMPLETE', IPS_INSTANCEMESSAGE + 10); //Searching is complete
// --- VARIABLE MANAGER
    define('IPS_VARIABLEMESSAGE', IPS_BASE + 600);              //Variable Manager Message
    define('VM_CREATE', IPS_VARIABLEMESSAGE + 1);               //Variable Created
    define('VM_DELETE', IPS_VARIABLEMESSAGE + 2);               //Variable Deleted
    define('VM_UPDATE', IPS_VARIABLEMESSAGE + 3);               //On Variable Update
    define('VM_CHANGEPROFILENAME', IPS_VARIABLEMESSAGE + 4);    //On Profile Name Change
    define('VM_CHANGEPROFILEACTION', IPS_VARIABLEMESSAGE + 5);  //On Profile Action Change
// --- SCRIPT MANAGER
    define('IPS_SCRIPTMESSAGE', IPS_BASE + 700);           //Script Manager Message
    define('SM_CREATE', IPS_SCRIPTMESSAGE + 1);            //On Script Create
    define('SM_DELETE', IPS_SCRIPTMESSAGE + 2);            //On Script Delete
    define('SM_CHANGEFILE', IPS_SCRIPTMESSAGE + 3);        //On Script File changed
    define('SM_BROKEN', IPS_SCRIPTMESSAGE + 4);            //Script Broken Status changed
// --- EVENT MANAGER
    define('IPS_EVENTMESSAGE', IPS_BASE + 800);             //Event Scripter Message
    define('EM_CREATE', IPS_EVENTMESSAGE + 1);             //On Event Create
    define('EM_DELETE', IPS_EVENTMESSAGE + 2);             //On Event Delete
    define('EM_UPDATE', IPS_EVENTMESSAGE + 3);
    define('EM_CHANGEACTIVE', IPS_EVENTMESSAGE + 4);
    define('EM_CHANGELIMIT', IPS_EVENTMESSAGE + 5);
    define('EM_CHANGESCRIPT', IPS_EVENTMESSAGE + 6);
    define('EM_CHANGETRIGGER', IPS_EVENTMESSAGE + 7);
    define('EM_CHANGETRIGGERVALUE', IPS_EVENTMESSAGE + 8);
    define('EM_CHANGETRIGGEREXECUTION', IPS_EVENTMESSAGE + 9);
    define('EM_CHANGECYCLIC', IPS_EVENTMESSAGE + 10);
    define('EM_CHANGECYCLICDATEFROM', IPS_EVENTMESSAGE + 11);
    define('EM_CHANGECYCLICDATETO', IPS_EVENTMESSAGE + 12);
    define('EM_CHANGECYCLICTIMEFROM', IPS_EVENTMESSAGE + 13);
    define('EM_CHANGECYCLICTIMETO', IPS_EVENTMESSAGE + 14);
// --- MEDIA MANAGER
    define('IPS_MEDIAMESSAGE', IPS_BASE + 900);           //Media Manager Message
    define('MM_CREATE', IPS_MEDIAMESSAGE + 1);             //On Media Create
    define('MM_DELETE', IPS_MEDIAMESSAGE + 2);             //On Media Delete
    define('MM_CHANGEFILE', IPS_MEDIAMESSAGE + 3);         //On Media File changed
    define('MM_AVAILABLE', IPS_MEDIAMESSAGE + 4);          //Media Available Status changed
    define('MM_UPDATE', IPS_MEDIAMESSAGE + 5);
// --- LINK MANAGER
    define('IPS_LINKMESSAGE', IPS_BASE + 1000);           //Link Manager Message
    define('LM_CREATE', IPS_LINKMESSAGE + 1);             //On Link Create
    define('LM_DELETE', IPS_LINKMESSAGE + 2);             //On Link Delete
    define('LM_CHANGETARGET', IPS_LINKMESSAGE + 3);       //On Link TargetID change
// --- DATA HANDLER
    define('IPS_DATAMESSAGE', IPS_BASE + 1100);             //Data Handler Message
    define('DM_CONNECT', IPS_DATAMESSAGE + 1);             //On Instance Connect
    define('DM_DISCONNECT', IPS_DATAMESSAGE + 2);          //On Instance Disconnect
// --- SCRIPT ENGINE
    define('IPS_ENGINEMESSAGE', IPS_BASE + 1200);           //Script Engine Message
    define('SE_UPDATE', IPS_ENGINEMESSAGE + 1);             //On Library Refresh
    define('SE_EXECUTE', IPS_ENGINEMESSAGE + 2);            //On Script Finished execution
    define('SE_RUNNING', IPS_ENGINEMESSAGE + 3);            //On Script Started execution
// --- PROFILE POOL
    define('IPS_PROFILEMESSAGE', IPS_BASE + 1300);
    define('PM_CREATE', IPS_PROFILEMESSAGE + 1);
    define('PM_DELETE', IPS_PROFILEMESSAGE + 2);
    define('PM_CHANGETEXT', IPS_PROFILEMESSAGE + 3);
    define('PM_CHANGEVALUES', IPS_PROFILEMESSAGE + 4);
    define('PM_CHANGEDIGITS', IPS_PROFILEMESSAGE + 5);
    define('PM_CHANGEICON', IPS_PROFILEMESSAGE + 6);
    define('PM_ASSOCIATIONADDED', IPS_PROFILEMESSAGE + 7);
    define('PM_ASSOCIATIONREMOVED', IPS_PROFILEMESSAGE + 8);
    define('PM_ASSOCIATIONCHANGED', IPS_PROFILEMESSAGE + 9);
// --- TIMER POOL
    define('IPS_TIMERMESSAGE', IPS_BASE + 1400);            //Timer Pool Message
    define('TM_REGISTER', IPS_TIMERMESSAGE + 1);
    define('TM_UNREGISTER', IPS_TIMERMESSAGE + 2);
    define('TM_SETINTERVAL', IPS_TIMERMESSAGE + 3);
    define('TM_UPDATE', IPS_TIMERMESSAGE + 4);
    define('TM_RUNNING', IPS_TIMERMESSAGE + 5);
// --- STATUS CODES
    define('IS_SBASE', 100);
    define('IS_CREATING', IS_SBASE + 1); //module is being created
    define('IS_ACTIVE', IS_SBASE + 2); //module created and running
    define('IS_DELETING', IS_SBASE + 3); //module us being deleted
    define('IS_INACTIVE', IS_SBASE + 4); //module is not beeing used
// --- ERROR CODES
    define('IS_EBASE', 200);          //default errorcode
    define('IS_NOTCREATED', IS_EBASE + 1); //instance could not be created
// --- Search Handling
    define('FOUND_UNKNOWN', 0);     //Undefined value
    define('FOUND_NEW', 1);         //Device is new and not configured yet
    define('FOUND_OLD', 2);         //Device is already configues (InstanceID should be set)
    define('FOUND_CURRENT', 3);     //Device is already configues (InstanceID is from the current/searching Instance)
    define('FOUND_UNSUPPORTED', 4); //Device is not supported by Module

    define('vtBoolean', 0);
    define('vtInteger', 1);
    define('vtFloat', 2);
    define('vtString', 3);
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

    public $Address;
    public $Command;
    public $Data;
    public $needResponse;
    private $SendValues;

    public function __construct($Command = '', $Data = '', $needResponse = true)
    {
        $this->Address = '';
        if (is_array($Command))
            $this->Command = $Command;
        else
            $this->Command = array($Command);
        if (is_array($Data))
            $this->Data = array_map('rawurlencode', $Data);
        else
            $this->Data = rawurlencode($Data);
        $this->needResponse = $needResponse;
    }

    public function ToJSONStringForLMS($GUID)
    {
        $this->SendValues = count($this->Data);
        return json_encode(Array("DataID" => $GUID, "Buffer" => utf8_encode($this->ToRawStringForLMS())));
    }

    public function ToRawStringForLMS()
    {
        $Command = implode(' ', $this->Command);
        $this->SendValues = count($this->Data);

        if (is_array($this->Data))
            $Data = implode(' ', $this->Data);
        else
            $Data = $this->Data;
        return trim($this->Address . ' ' . trim($Command) . ' ' . trim($Data)) . chr(0x0d);
    }

    public function GetSearchPatter()
    {
        return $this->Address . implode('', $this->Command);
    }

    public function CreateFromGenericObject($Data)
    {
        $this->Address = $this->DecodeUTF8($Data->Address);
        $this->Command = $this->DecodeUTF8($Data->Command);
        $this->Data = $this->DecodeUTF8($Data->Data);
        if (property_exists($Data, 'needResponse'))
            $this->needResponse = $Data->needResponse;
    }

    public function SliceData()
    {
        $this->Data = array_slice($this->Data, $this->SendValues);
    }

    public function ToJSONString($GUID)
    {
        return json_encode(Array("DataID" => $GUID,
            "Address" => $this->EncodeUTF8($this->Address),
            "Command" => $this->EncodeUTF8($this->Command),
            "Data" => $this->EncodeUTF8($this->Data),
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
        if (strpos($array[0], '%3A') == 2) //isMAC
        {
            $this->Device = LMSResponse::isMAC;
            $this->Address = rawurldecode(array_shift($array));
        }
        elseif (strpos($array[0], '.')) //isIP
        {
            $this->Device = LMSResponse::isIP;
            $this->Address = array_shift($array);
        }
        else // isServer
        {
            $this->Device = LMSResponse::isServer;
            $this->Address = "";
        }
        $this->Command = array(array_shift($array));
        if ($this->Device == LMSResponse::isServer)
        {
            if (count($array) <> 0)
            {
                switch ($this->Command[0])
                {
                    case 'player':
                        $this->Command[1] = array_shift($array);
                        if ($this->Command[1] == "name")
                            $this->Command[2] = array_shift($array);
                        break;
                    case 'songinfo':
                    case 'info':
                        $this->Command[1] = array_shift($array);
                        $this->Command[2] = array_shift($array);
                        break;
                    case 'playlists':
                        if (in_array($array[0], array('rename', 'delete', 'new', 'tracks', 'edit')))
                            $this->Command[1] = array_shift($array);
                        break;
                    case 'alarm':
                    case 'favorites':
                        $this->Command[1] = array_shift($array);
                        break;
                }
            }
        }
        else
        {
            if (count($array) <> 0)
            {
                switch ($this->Command[0])
                {

//                    case 'alarm':
                    case 'mixer':
                    case 'playlist':
                        $this->Command[1] = array_shift($array);
                        break;
//                    case 'prefset':
                    case 'status':
                        $this->Command[1] = array_shift($array);
                        $this->Command[2] = array_shift($array);
                        break;
//                    case 'prefset':
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
//                    case 'ignore':
                        break;

                    case 'displaynotify':
                    case 'menustatus':
                    case 'prefset':

                    case 'alarm':
                    default:
                        $this->Command[1] = $this->Command[0];
                        $this->Command[0] = 'ignore';
                        break;

//                        $this->Command[1] = $this->Command[0];
//                        $this->Command[0] = false;
                }
            }
        }
        if (count($array) == 0)
            $array[0] = "";

        //parent::__construct($Command, $array);
        $this->Data = array_map('rawurldecode', $array);
//        $this->Data = $array;
    }

    /**
     * Erzeugt aus dem Objekt einen JSON-String.
     * 
     * @param string $GUID GUID welche in den JSON-String eingebunden wird.
     * @return string Der JSON-String für den Datenaustausch
     */
    public function ToJSONStringForDevice(string $GUID)
    {
        return json_encode(Array(
            "DataID" => $GUID,
            "Address" => $this->EncodeUTF8($this->Address),
            "Command" => $this->EncodeUTF8($this->Command),
            "Data" => $this->EncodeUTF8($this->Data)
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
        //$Part = explode('%3A', $TaggedDataLine); //        
        $Part = explode(':', $TaggedDataLine); //                
//        $this->Name = rawurldecode(array_shift($Part));
        $this->Name = array_shift($Part);
        if (count($Part) > 1)
            $this->Value = implode(':', $Part);
//            $this->Value = implode('%3A', $Part);
        else
            $this->Value = array_shift($Part);
        if (is_numeric($this->Value))
            $this->Value = (int) $this->Value;
        else
            $this->Value = (string) $this->Value;
//            $this->Value = rawurldecode($this->Value);        
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
    static $DataFields = array(
        'Id' => 1,
//        'Title' => 3,
        'Genre' => 3, // g
        'Album' => 3, // l
        'Artist' => 3, // a
//        'Duration' => 1, // d
        'Category' => 3,
        'Coverid' => 1, // i
        'Count' => 1,
//        'Disc' => 1, // i
//        'Disccount' => 1, // q
//        'Bitrate' => 3, // r
        'Filename' => 3,
//        'Tracknum' => 1, // t
        'Playerindex' => 3,
        'Playerid' => 3,
        'Uuid' => 3,
        'Ip' => 3,
        'Model' => 3,
        'Connected' => 0,
        'Url' => 3, // u
//        'Remote' => 0,
//        'Rating' => 1, // R
//        'Album_id' => 1, // e
//        'Artwork_track_id' => 3, // J
//        'Samplesize' => 3, // I
//        'Remote_title' => 3, //N 
//        'Genre_id' => 1, //p
//        'Artist_id' => 1, //s
        'Year' => 1, // Y
        'Name' => 3,
        'Type' => 3,
        'Title' => 3,
//        'Singleton'=>0,
        'Isaudio' => 0,
        'Hasitems' => 1,
//        'Modified' => 0,
//        'Playlist' => 3
    );

    /**
     * Erzeugt aus einem Array mit getaggten Daten ein mehrdimensionales Array.
     * 
     * @access public
     * @param array $TaggedData Das Array mit allen getaggten Zeilen.
     * @param string $UsedIdIndex Der zu verwendene Index welcher als Trenner zwischen den Objekten fungiert.
     */
    public function __construct(array $TaggedData, $UsedIdIndex = 'id')
    {
        $id = -1;
        $DataArray = array();
        if (count($TaggedData) > 0)
        {
            $UseIDs = ((new LMSTaggingData($TaggedData[0]))->Name == 'id') ? true : false;
        }
        foreach ($TaggedData as $Line)
        {
            $Part = new LMSTaggingData($Line);
            if ($UseIDs)
            {
                if ($Part->Name == 'id')
                {
                    $id = $Part->Value;
                    continue;
                }
            }
            else
            {
                if ($Part->Name == $UsedIdIndex)
                    $id++;
            }
            $Index = ucfirst($Part->Name);
            if (!array_key_exists($Index, static::$DataFields))
                continue;


            if (static::$DataFields[$Index] == 0)
                $DataArray[$id][$Index] = (bool) ($Part->Value);
            elseif (static::$DataFields[$Index] == 1)
            {
                if (is_numeric($Part->Value))
                    $DataArray[$id][$Index] = (int) $Part->Value;
                else
                    $DataArray[$id][$Index] = rawurldecode($Part->Value);
            }
            else
                $DataArray[$id][$Index] = rawurldecode($Part->Value);
        }
        if (isset($DataArray[-1]))
        {
            if (count($DataArray) == 1)
            {
                $DataArray = $DataArray[-1];
            }
            else
            {
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

class LMSSongInfo extends stdClass
{

    private $SongArray;
    private $Duration;
    static $SongFields = array(
        'Id' => 1,
        'Title' => 3,
        'Genre' => 3, // g
        'Album' => 3, // l
        'Artist' => 3, // a
        'Duration' => 1, // d
        'Disc' => 1, // i
        'Disccount' => 1, // q
        'Bitrate' => 3, // r
        'Tracknum' => 1, // t
        'Url' => 3, // u
        'Remote' => 0,
        'Rating' => 1, // R
        'Album_id' => 1, // e
        'Artwork_track_id' => 3, // J
        'Samplesize' => 3, // I
        'Remote_title' => 3, //N 
        'Genre_id' => 1, //p
        'Artist_id' => 1, //s
        'Year' => 1, // Y
        'Name' => 3,
        'Modified' => 0,
        'Playlist' => 3
    );

    public function __construct(array $TaggedData)
    {
        $id = -1;
        $Songs = array();
        $Duration = 0;
        $Playlist = ((new LMSTaggingData($TaggedData[0]))->Name == 'playlist index') ? true : false;
        foreach ($TaggedData as $Line)
        {
            $Part = new LMSTaggingData($Line);
            if ($Playlist)
            {
                if ($Part->Name == 'playlist index')
                {
                    $id = (int) $Part->Value;
                    continue;
                }
            }
            else
            {
                if ($Part->Name == 'id')
                    $id++;
            }
            $Index = ucfirst($Part->Name);
            if (!array_key_exists($Index, static::$SongFields))
                continue;


            if ($Part->Name == 'duration')
                $Duration = +intval($Part->Value);
            if (static::$SongFields[$Index] == 0)
                $Songs[$id][$Index] = (bool) ($Part->Value);
            elseif (static::$SongFields[$Index] == 1)
                $Songs[$id][$Index] = intval($Part->Value);
            else
                $Songs[$id][$Index] = rawurldecode($Part->Value);
        }
        if ((count($Songs) <> 1 ) and isset($Songs[-1]))
            unset($Songs[-1]);
        $this->SongArray = $Songs;
        $this->Duration = $Duration;
    }

    public function GetSong()
    {
        return array_shift($this->SongArray);
    }

    public function GetAllSongs()
    {
        return $this->SongArray;
    }

    public function GetTotalDuration()
    {
        return $this->Duration;
    }

    public function CountAllSongs()
    {
        return count($this->SongArray);
    }

}

trait LMSSongURL
{

    protected function GetValidSongURL(&$SongURL)
    {
        if (!is_string($SongURL))
        {
            trigger_error("URL must be string.", E_USER_NOTICE);
            return false;
        }
        $valid = strpos($SongURL, 'file:///');
        if ($valid === false)
        {
            $SongURL = 'file:///' . $SongURL;
        }
        elseif ($valid > 0)
        {
            trigger_error("URL not valid.", E_USER_NOTICE);
            return false;
        }
        $SongURL = str_replace('\\', '/', $SongURL);
        return true;
    }

}

trait LMSHTMLTable
{

    /**
     * Liefert den Header der HTML-Tabelle.
     * 
     * @access private
     * @param array $Config Die Kofiguration der Tabelle
     * @return string HTML-String
     */
    protected function GetTableHeader($Config)
    {
        $html = "";
        // Button Styles erzeugen
        if (isset($Config['Button']))
        {
            $html .= "<style>" . PHP_EOL;
            foreach ($Config['Button'] as $Class => $Button)
            {
                $html .= '.' . $Class . ' {' . $Button . '}' . PHP_EOL;
            }
            $html .= "</style>" . PHP_EOL;
        }
        // Kopf der Tabelle erzeugen
        $html .= '<table style="' . $Config['Style']['T'] . '">' . PHP_EOL;
        // JS Rückkanal erzeugen
        $html .= '<script type="text/javascript">
function xhrGet' . $this->InstanceID . '(o)
{
    var HTTP = new XMLHttpRequest();
    HTTP.open(\'GET\',o.url,true);
    HTTP.send();
    HTTP.addEventListener(\'load\', function(event)
    {
        if (HTTP.status >= 200 && HTTP.status < 300)
        {
            if (HTTP.responseText != \'OK\')
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
if (notify.childElementCount == 0)
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
})
}

</script>';
        $html .= '<colgroup>' . PHP_EOL;
        foreach ($Config['Spalten'] as $Index => $Value)
        {
            $html .= '<col width="' . $Config['Breite'][$Index] . '" />' . PHP_EOL;
        }
        $html .= '</colgroup>' . PHP_EOL;
        $html .= '<thead style="' . $Config['Style']['H'] . '">' . PHP_EOL;
        $html .= '<tr style="' . $Config['Style']['HR'] . '">';
        foreach ($Config['Spalten'] as $Index => $Value)
        {
            $html .= '<th style="' . $Config['Style']['HF' . $Index] . '">' . $Value . '</th>';
        }
        $html .= '</tr>' . PHP_EOL;
        $html .= '</thead>' . PHP_EOL;
        $html .= '<tbody style="' . $Config['Style']['B'] . '">' . PHP_EOL;
        return $html;
    }

    /**
     * Liefert den Footer der HTML-Tabelle.
     * 
     * @access private
     * @return string HTML-String
     */
    protected function GetTableFooter()
    {
        $html = '</tbody>' . PHP_EOL;
        $html .= '</table>' . PHP_EOL;
        return $html;
    }

}

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
        if (is_string($item))
            $item = utf8_decode($item);
        else if (is_object($item))
        {
            foreach ($item as $property => $value)
            {
                $item->{$property} = $this->DecodeUTF8($value);
            }
        }
        else if (is_array($item))
        {
            foreach ($item as $property => $value)
            {
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
        if (is_string($item))
            $item = utf8_encode($item);
        else if (is_object($item))
        {
            foreach ($item as $property => $value)
            {
                $item->{$property} = $this->EncodeUTF8($value);
            }
        }
        else if (is_array($item))
        {
            foreach ($item as $property => $value)
            {
                $item[$property] = $this->EncodeUTF8($value);
            }
        }
        return $item;
    }

}

trait LMSCover
{

    protected function GetCover(int $SplitterID, string $CoverID, string $Size, string $Player)
    {
        $Host = IPS_GetProperty($SplitterID, 'Host') . ":" . IPS_GetProperty($SplitterID, 'Webport');
        if ($Player <> "")
        {
            $Player = "?player=" . rawurlencode($Player);
            $CoverID = "current";
        }
        return @Sys_GetURLContent("http://" . $Host . "/music/" . $CoverID . "/" . $Size . ".png" . $Player);
    }

}

trait VariableHelper
{
    ################## VARIABLEN - protected

    protected function ConvertSeconds(int $Time)
    {
        if ($Time > 3600)
            return @date("H:i:s", $Time);
        else
            return @date("i:s", $Time);
    }

    /**
     * Setzte eine IPS-Variable vom Typ bool auf den Wert von $value
     *
     * @access protected
     * @param string $Ident Ident der Statusvariable.
     * @param bool $value Neuer Wert der Statusvariable.
     * @return bool true wenn der neue Wert vom alten abweicht, sonst false.
     */
    protected function SetValueBoolean($Ident, $value)
    {
        $id = @$this->GetIDForIdent($Ident);
        if ($id == false)
            return false;
        if (GetValueBoolean($id) <> $value)
        {
            SetValueBoolean($id, $value);
            return true;
        }
        return false;
    }

    /**
     * Setzte eine IPS-Variable vom Typ integer auf den Wert von $value.
     *
     * @access protected
     * @param string $Ident Ident der Statusvariable.
     * @param int $value Neuer Wert der Statusvariable.
     * @return bool true wenn der neue Wert vom alten abweicht, sonst false.
     */
    protected function SetValueInteger($Ident, $value)
    {
        $id = @$this->GetIDForIdent($Ident);
        if ($id == false)
            return false;
        if (GetValueInteger($id) <> $value)
        {
            SetValueInteger($id, $value);
            return true;
        }
        return false;
    }

    /**
     * Setzte eine IPS-Variable vom Typ string auf den Wert von $value.
     *
     * @access protected
     * @param string $Ident Ident der Statusvariable.
     * @param string $value Neuer Wert der Statusvariable.
     * @return bool true wenn der neue Wert vom alten abweicht, sonst false.
     */
    protected function SetValueString($Ident, $value)
    {
        $id = @$this->GetIDForIdent($Ident);
        if ($id == false)
            return false;
        if (GetValueString($id) <> $value)
        {
            SetValueString($id, $value);
            return true;
        }
        return false;
    }

}

/**
 * Trait mit Hilfsfunktionen für Variablenprofile.
 */
trait Profile
{

    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ integer mit Assoziationen
     *
     * @access protected
     * @param string $Name Name des Profils.
     * @param string $Icon Name des Icon.
     * @param string $Prefix Prefix für die Darstellung.
     * @param string $Suffix Suffix für die Darstellung.
     * @param array $Associations Assoziationen der Werte als Array.
     */
    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (sizeof($Associations) === 0)
        {
            $MinValue = 0;
            $MaxValue = 0;
        }
        else
        {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations) - 1][0];
        }

        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        $old = IPS_GetVariableProfile($Name)["Associations"];
        $OldValues = array_column($old, 'Value');
        foreach ($Associations as $Association)
        {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
            $OldKey = array_search($Association[0], $OldValues);
            if (!($OldKey === false ))
                unset($OldValues[$OldKey]);
        }
        foreach ($OldValues as $OldKey => $OldValue)
        {
            IPS_SetVariableProfileAssociation($Name, $OldValue, '', '', 0);
        }
    }

    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ integer
     *
     * @access protected
     * @param string $Name Name des Profils.
     * @param string $Icon Name des Icon.
     * @param string $Prefix Prefix für die Darstellung.
     * @param string $Suffix Suffix für die Darstellung.
     * @param int $MinValue Minimaler Wert.
     * @param int $MaxValue Maximaler wert.
     * @param int $StepSize Schrittweite
     */
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {

        if (!IPS_VariableProfileExists($Name))
        {
            IPS_CreateVariableProfile($Name, 1);
        }
        else
        {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1)
                throw new Exception("Variable profile type does not match for profile " . $Name, E_USER_NOTICE);
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    /**
     * Löscht ein VariablenProfil.
     *
     * @access protected
     * @param string $Name Name des Profils.
     */
    protected function UnregisterProfile($Name)
    {
        if (IPS_VariableProfileExists($Name))
            IPS_DeleteVariableProfile($Name);
    }

}

/**
 * Trait mit Hilfsfunktionen für den Datenaustausch.
 */
trait InstanceStatus
{

    /**
     * Liefert den Parent der Instanz.
     * 
     * @return int|bool InstanzID des Parent, false wenn kein Parent vorhanden.
     */
    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    /**
     * Ermittelt den Parent und verwaltet die Einträge des Parent im MessageSink
     * Ermöglicht es das Statusänderungen des Parent empfangen werden können.
     * 
     * @access private
     */
    protected function GetParentData()
    {
        $OldParentId = $this->GetBuffer('Parent');
        $ParentId = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($OldParentId > 0)
            $this->UnregisterMessage($OldParentId, IM_CHANGESTATUS);
        if ($ParentId > 0)
        {
            $this->RegisterMessage($ParentId, IM_CHANGESTATUS);
            $this->SetBuffer('Parent', $ParentId);
        }
        else
            $this->SetBuffer('Parent', 0);
    }

    /**
     * Prüft den Parent auf vorhandensein und Status.
     * 
     * @return bool True wenn Parent vorhanden und in Status 102, sonst false.
     */
    protected function HasActiveParent()
    {
        $ParentID = $this->GetParent();
        if ($ParentID !== false)
        {
            if (IPS_GetInstance($ParentID)['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }

    /**
     * Setzt den Status dieser Instanz auf den übergebenen Status.
     * Prüft vorher noch ob sich dieser vom aktuellen Status unterscheidet.
     * 
     * @access protected
     * @param int $InstanceStatus
     */
    protected function SetStatus($InstanceStatus)
    {
        if ($InstanceStatus <> IPS_GetInstance($this->InstanceID)['InstanceStatus'])
            parent::SetStatus($InstanceStatus);
    }

}

################## SEMAPHOREN Helper  - protected  

/**
 * Biete Funktionen um Thread-Safe auf Objekte zuzugrifen.
 */
trait Semaphore
{

    /**
     * Versucht eine Semaphore zu setzen und wiederholt dies bei Misserfolg bis zu 100 mal.
     * @param string $ident Ein String der den Lock bezeichnet.
     * @return boolean TRUE bei Erfolg, FALSE bei Misserfolg.
     */
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

    /**
     * Löscht eine Semaphore.
     * @param string $ident Ein String der den Lock bezeichnet.
     */
    private function unlock($ident)
    {
        IPS_SemaphoreLeave("LMS_" . (string) $this->InstanceID . (string) $ident);
    }

}

################## WEBHOOK Helper  - protected  

trait Webhook
{

    /**
     * Erstellt einen WebHook, wenn nicht schon vorhanden.
     *
     * @access protected
     * @param string $WebHook URI des WebHook.
     */
    protected function RegisterHook($WebHook)
    {
        $ids = IPS_GetInstanceListByModuleID("{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}");
        if (sizeof($ids) > 0)
        {
            $hooks = json_decode(IPS_GetProperty($ids[0], "Hooks"), true);
            $found = false;
            foreach ($hooks as $index => $hook)
            {
                if ($hook['Hook'] == $WebHook)
                {
                    if ($hook['TargetID'] == $this->InstanceID)
                        return;
                    $hooks[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found)
            {
                $hooks[] = Array("Hook" => $WebHook, $this->InstanceID);
            }
            IPS_SetProperty($ids[0], "Hooks", json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }

    /**
     * Löscht einen WebHook, wenn vorhanden.
     *
     * @access protected
     * @param string $WebHook URI des WebHook.
     */
    protected function UnregisterHook($WebHook)
    {
        $ids = IPS_GetInstanceListByModuleID("{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}");
        if (sizeof($ids) > 0)
        {
            $hooks = json_decode(IPS_GetProperty($ids[0], "Hooks"), true);
            $found = false;
            foreach ($hooks as $index => $hook)
            {
                if ($hook['Hook'] == $WebHook)
                {
                    $found = $index;
                    break;
                }
            }

            if ($found !== false)
            {
                array_splice($hooks, $index, 1);
                IPS_SetProperty($ids[0], "Hooks", json_encode($hooks));
                IPS_ApplyChanges($ids[0]);
            }
        }
    }

}

################## DEBUG - protected

/**
 * DebugHelper ergänzt SendDebug um die Möglichkeit Array und Objekte auszugeben.
 * 
 */
trait DebugHelper
{

    /**
     * Formatiert eine DebugAusgabe und gibt sie an IPS weiter.
     *
     * @access protected
     * @param string $Message Nachrichten-Feld.
     * @param string|array|LMSData|LMSResponse $Data Daten-Feld.
     * @param int $Format Ausgabe in Klartext(0) oder Hex(1)
     */
    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_a($Data, 'LMSResponse'))
        {
            $this->SendDebug($Message . " LMSResponse:Address", $Data->Address, 0);
            $this->SendDebug($Message . " LMSResponse:Command", $Data->Command, 0);
            $this->SendDebug($Message . " LMSResponse:Data", $Data->Data, 0);
        }
        else if (is_a($Data, 'LMSData'))
        {
            $this->SendDebug($Message . " LMSData:Address", $Data->Address, 0);
            $this->SendDebug($Message . " LMSData:Command", $Data->Command, 0);
            $this->SendDebug($Message . " LMSData:Data", $Data->Data, 0);
            $this->SendDebug($Message . " LMSData:needResponse", ($Data->needResponse ? 'true' : 'false'), 0);
        }
        elseif (is_array($Data))
        {
            if (count($Data) > 25)
            {
                $this->SendDebug($Message, array_slice($Data, 0, 20), 0);
                $this->SendDebug($Message . ':CUT', '-------------CUT-----------------', 0);
                $this->SendDebug($Message, array_slice($Data, -5, null, true), 0);
            }
            else
            {
                foreach ($Data as $Key => $DebugData)
                {
                    $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
                }
            }
        }
        else if (is_object($Data))
        {
            foreach ($Data as $Key => $DebugData)
            {
                $this->SendDebug($Message . "->" . $Key, $DebugData, 0);
            }
        }
        else
        {
            parent::SendDebug($Message, $Data, $Format);
        }
    }

}

// Klasse mit Daten zum SENDEN von ein Device an den LMS-Splitter
class LSQData extends stdClass
{

    use UTF8Coder;

    public $Address; //DeviceID
    public $Command;
    public $Value;
    public $needResponse;

    public function __construct($Command, $Value, $needResponse = true)
    {
        if (is_array($Command))
            $this->Command = $Command;
        else
            $this->Command = array($Command);

        $this->Value = $Value;
        $this->needResponse = $needResponse;
    }

    public function ToJSONStringForSplitter($GUID)
    {
        return json_encode(Array("DataID" => $GUID,
            "Address" => $this->EncodeUTF8($this->Address),
            "Command" => $this->EncodeUTF8($this->Command),
            "Value" => $this->EncodeUTF8($this->Value),
            "needResponse" => $this->needResponse
        ));
    }

    public function ToRawStringForLMS()
    {
        $Command = implode(' ', $this->Command);
        if (is_array($this->Value))
            $Data = implode(' ', $this->Value);
        else
            $Data = $this->Value;
        return trim($this->Address . ' ' . trim($Command) . ' ' . trim($Data)) . chr(0x0d);
    }

}

class LSQButton extends stdClass
{

    const power = 'power';
    const voldown = 'voldown';
    const volup = 'volup';
    const preset_1 = 'preset_1.single';
    const preset_2 = 'preset_2.single';
    const preset_3 = 'preset_3.single';
    const preset_4 = 'preset_4.single';
    const preset_5 = 'preset_5.single';
    const preset_6 = 'preset_6.single';
    const jump_rew = 'jump_rew';
    const jump_fwd = 'jump_fwd';

}

// Klasse mit einem Teil der Empfangenen Daten von einem LSQResponse
class LSQEvent extends stdClass
{

    public $Command;
    public $Value;
    public $isResponse = false;

    public function __construct($Command, $Value, $isResponse)
    {
        $this->Command = $Command;
        $this->Value = $Value;
        $this->isResponse = $isResponse;
    }

}

class LSQTaggingData extends LSQEvent
{

    public function __construct($Data, $isResponse)
    {
        $Part = explode('%3A', $Data); //        
        $Command = rawurldecode(array_shift($Part));
        if (!(strpos($Command, chr(0x20)) === false))
        {
            $Command = explode(chr(0x20), $Command);
        }
        if (count($Part) > 1)
        {
            $Value = implode('%3A', $Part);
        }
        else
        {
            $Value = array_shift($Part);
        }
        parent::__construct($Command, $Value, $isResponse);
        //return new LSQEvent($Command, $Value, $isResponse);
    }

}

//OLD
class LSMSongInfo extends stdClass
{

    private $SongArray;
    private $Duration;

    public function __construct($TaggedDataLine)
    {
        $id = -1;
        $Songs = array();
        $Duration = 0;
        $SongFields = array(
            'Id' => 1,
            'Title' => 3,
            'Genre' => 3, // g
            'Album' => 3, // l
            'Artist' => 3, // a
            'Duration' => 1, // d
            'Disc' => 1, // i
            'Disccount' => 1, // q
            'Bitrate' => 3, // r
            'Tracknum' => 1, // t
            'Url' => 3, // u
            'Remote' => 0,
            'Rating' => 1, // R
            'Album_id' => 1, // e
            'Artwork_track_id' => 3, // J
            'Samplesize' => 3, // I
            'Remote_title' => 3, //N 
            'Genre_id' => 1, //p
            'Artist_id' => 1, //s
            'Year' => 1, // Y
            'Name' => 3,
            'Modified' => 0,
            'Playlist' => 3
        );
        if (!is_array($TaggedDataLine))
            $TaggedDataLine = explode(' ', $TaggedDataLine);
        foreach ($TaggedDataLine as $Line)
        {

//            $LSQPart = $this->decodeLSQTag gingData($Line, false);
            $LSQPart = new LSQTaggingData($Line, false);
//LMSTaggingData
            if (is_array($LSQPart->Command) and ( $LSQPart->Command[0] == LSQResponse::playlist) and ( $LSQPart->Command[1] == LSQResponse::index))
            {
                $id = (int) $LSQPart->Value;
//                IPS_LogMessage('LMSSongInfo','ROW: '.$Key.' ID: '.$id);                
                continue;
            }
            if (is_array($LSQPart->Command))
                continue;

            if ($LSQPart->Command == LSQResponse::id)
            {
                $id++;
            }

            if ($LSQPart->Command == LSQResponse::duration)
                $Duration = +intval($LSQPart->Value);
            $Index = ucfirst($LSQPart->Command);
            if (array_key_exists($Index, $SongFields))
            {
                if ($SongFields[$Index] == 0)
                    $Songs[$id][$Index] = (bool) ($LSQPart->Value);

                elseif ($SongFields[$Index] == 1)
                    $Songs[$id][$Index] = intval($LSQPart->Value);
                else
                    $Songs[$id][$Index] = /* utf8_decode( */rawurldecode(rawurldecode($LSQPart->Value))/* ) */;
            }
        }
        if ((count($Songs) <> 1 ) and isset($Songs[-1]))
        {
            if (isset($Songs[-1]))
                unset($Songs[-1]);
        }
        $this->SongArray = $Songs;
        $this->Duration = $Duration;
    }

    public function GetSong()
    {
        return array_shift($this->SongArray);
    }

    public function GetAllSongs()
    {
        return $this->SongArray;
    }

    public function GetTotalDuration()
    {
        /*       if ($this->Duration > 3600)
          return @date('Hi:s', $this->Duration - 3600);
          else
          return @date('i:s', $this->Duration); */
        return $this->Duration;
    }

    public function CountAllSongs()
    {
        return count($this->SongArray);
    }

}

// Klasse mit den Empfangenen Daten vom LMS-Splitter
class LSQResponse extends stdClass
{

    //commands
    const listen = 'listen';
    const signalstrength = 'signalstrength';
    const name = 'name';
    const player_name = 'player_name';
    const player_connected = 'player_connected';
    const player_ip = 'player_ip';
    const open = 'open';
    const time = 'time';
    const playlist_tracks = 'playlist_tracks';
    const playlist_cur_index = 'playlist_cur_index';
    const currentSong = 'currentSong';
    const waitingToPlay = 'waitingToPlay';
    const jump = 'jump';
    const can_seek = 'can_seek';
    const remote = 'remote';
    const newmetadata = 'newmetadata';
    const remoteMeta = 'remoteMeta';
    const displaynotify = 'displaynotify';
    const id = 'id';
    const rate = 'rate';
    const seq_no = 'seq_no';
    const playlist_timestamp = 'playlist_timestamp';
    const title = 'title';
    const current_title = 'current_title';
    const index = 'index';
    const connected = 'connected';
    const sleep = 'sleep';
    const will_sleep_in = 'will_sleep_in';
    const sync = 'sync';
    const mode = 'mode';
    const power = 'power';
    const album = 'album';
    const artist = 'artist';
    const duration = 'duration';
    const genre = 'genre';
    const play = 'play';
    const stop = 'stop';
    const pause = 'pause';
    const mixer = 'mixer';
    const show = 'show';
    const display = 'display';
    const linesperscreen = 'linesperscreen';
    const displaynow = 'displaynow';
    const playerpref = 'playerpref';
    const button = 'button';
    const irenable = 'irenable';
    const connect = 'connect';
    const status = 'status';
    const prefset = 'prefset';
    const playlist = 'playlist';
    const playlists = 'playlists';
    const playlistcontrol = 'playlistcontrol';
    const client = 'client';
    //mixer
    const volume = 'volume';
    const muting = 'muting';
    const bass = 'bass';
    const treble = 'treble';
    const pitch = 'pitch';
    //playlist oder prefset
    const repeat = 'repeat';
    const shuffle = 'shuffle';
    const newsong = 'newsong';
    const tracks = 'tracks';
    const loadtracks = 'loadtracks';
    const load_done = 'load_done';
    const playlist_name = 'playlist_name';
    const playlist_modified = 'playlist_modified';
    const playlist_id = 'playlist_id';

    /*
      connected ?
      play
      stop
      pause
      mode ?
      time
      genre ?
      artist ?
      album ?
      title ?
      duration ?
      remote ?
      current_title ?
      path ?
      playlist play
      playlist add
      playlist insert
      playlist deleteitem
      playlist move
      playlist delete
      playlist preview
      playlist resume
      playlist save
      playlist loadalbum
      playlist addalbum
      playlist loadtracks
      playlist addtracks
      playlist insertalbum
      playlist deletealbum
      playlist clear
      playlist zap
      playlist name ?
      playlist url ?
      playlist modified ?
      playlist playlistsinfo
      playlist index
      playlist genre ?
      playlist artist ?
      playlist album ?
      playlist title ?
      playlist path ?
      playlist remote ?
      playlist duration ?
      playlist tracks ?
      playlist shuffle
      playlist repeat
      playlistcontrol
     */

    public $Address;
    public $Command;
    public $Value;
    public $isResponse = false;

//    private $Modus = Array(2 => LSQResponse::stop, 3 => LSQResponse::play, 4 => LSQResponse::pause);

    public function __construct($Data) // LMS->Data
    {
        $this->Address = $Data->Address;
        foreach ($Data->Data as $Key => $Value)
        {

            $Data->Data[$Key] = utf8_decode($Value);
        }
        array_unshift($Data->Data, utf8_decode($Data->Command[0]));
        switch ($Data->Data[0])
        {
            // 0 = Command 1 = Value
            case LSQResponse::signalstrength:
            case LSQResponse::name:
            case LSQResponse::connected:
            case LSQResponse::sleep:
            case LSQResponse::listen:
            case LSQResponse::sync:
            case LSQResponse::power:
            case LSQResponse::linesperscreen:
            case LSQResponse::irenable:
            case LSQResponse::connect:
            case LSQResponse::play:
            case LSQResponse::pause:
            case LSQResponse::stop:
            case LSQResponse::mode:
            case LSQResponse::client:
            case LSQResponse::album:
            case LSQResponse::artist:
            case LSQResponse::genre:
            case LSQResponse::duration:
            case LSQResponse::time:
            case LSQResponse::newmetadata:
            case LSQResponse::title:
                $this->Command = array_shift($Data->Data);
                if (isset($Data->Data[0]))
                    $this->Value = array_shift($Data->Data);
                break;
            // 0 = Command 1=multiValue
            case LSQResponse::status:
                $this->Command[0] = array_shift($Data->Data);
                $this->Command[1] = array_shift($Data->Data);
                $this->Command[2] = array_shift($Data->Data);
//                $this->Command[3] =array_shift($Data->Data);                
                $this->Value = array_values($Data->Data);
                break;
            case LSQResponse::playlistcontrol:
                $this->Command = array_shift($Data->Data);
                $this->Value = $Data->Data;
                break;
//        LSQResponse::show,
//        LSQResponse::display,
//        LSQResponse::displaynow,
//        LSQResponse::playerpref
            // 1 = Command 2 = Value             
            case LSQResponse::button:
            case LSQResponse::mixer:
            case LSQResponse::playlist:
            case LSQResponse::playlists:
                $this->Command[0] = array_shift($Data->Data);
                $this->Command[1] = array_shift($Data->Data);
                if (isset($Data->Data[1]))
                {
                    $this->Value = $Data->Data;

//                    $this->Value[0] =  array_shift($Data->Data);
//                    $this->Value[1] =  array_shift($Data->Data);
                }
                elseif (isset($Data->Data[0]))
                    $this->Value = array_shift($Data->Data);
                break;

            // 2 = Command 3 = Value             
            case LSQResponse::prefset:
                $this->Command[0] = array_shift($Data->Data);
                $this->Command[1] = array_shift($Data->Data);
                $this->Command[2] = array_shift($Data->Data);
                if (isset($Data->Data[0]))
                    $this->Value = array_shift($Data->Data);
                break;
            default:

                $this->Command = array_shift($Data->Data);
                if (isset($Data->Data[0]))
                    $this->Value = array_shift($Data->Data);
                break;
            case 'displaynotif
                    y': //ignorieren
            case 'menustatus': //ignorieren                
                $this->Command = false;
                break;
        }
    }

    // Liefert den Aktuellen Zustand (play,pause,stop) als integer für die Status-Variable
    /*    public function GetModus()
      {

      return (int) array_keys($this->Modus, $this->Value, true);
      } */
}

if (!function_exists("array_column"))
{

    function array_column($array, $column_name)
    {

        return array_map(function($element) use($column_name)
        {
            return $element[$column_name];
        }, $array
        );
    }

}
/** @} */
