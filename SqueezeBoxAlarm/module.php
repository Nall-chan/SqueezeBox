<?php

require_once(__DIR__ . "/../libs/SqueezeBoxClass.php");  // diverse Klassen
require_once(__DIR__ . "/../libs/SqueezeBoxTraits.php");  // diverse Klassen

/*
 * @addtogroup squeezebox
 * @{
 *
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2017 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.02
 *
 */

/**
 * Enthält die Daten eines Alarm.
 */
class LSA_Alarm
{
    /**
     * Id des Alarm
     * @var string
     * @access public
     */
    public $Id;

    /**
     * Index des Alarm
     * @var int
     * @access public
     */
    public $Index;

    /**
     * Tage des Alarm.
     * @var string
     * @access public
     */
    public $Dow = '0,1,2,3,4,5,6';

    /**
     * Status des Alarm.
     * @var bool
     * @access public
     */
    public $Enabled = true;

    /**
     * Wiederholung des Alarm.
     * @var bool
     * @access public
     */
    public $Repeat = true;

    /**
     * Shuffle des Alarm.
     * @var int
     * @access public
     */
    public $Shufflemode = 0;

    /**
     * Uhrzeit des Alarm.
     * @var int
     * @access public
     */
    public $Time;

    /**
     * Lautstärke des Alarm.
     * @var int
     * @access public
     */
    public $Volume;

    /**
     * Playlist des Alarm.
     * @var string
     * @access public
     */
    public $Url = 'CURRENT_PLAYLIST';

    /**
     * Liefert die Daten welche behalten werden müssen.
     * @access public
     */
    public function __sleep()
    {
        return array('Id', 'Index', 'Dow', 'Enabled', 'Repeat', 'Time', 'Volume', 'Url', 'Shufflemode');
    }

    /**
     * Erzeugt aus den übergeben Daten einen neuen LSA_Alarm.
     *
     * @param array $Alarm Das Array wie es von LMSTaggingArray erzeugt wird.
     * @param int $Index Der fortlaufende Index dieses Weckers.
     * @return LSA_Alarm
     */
    public function __construct(array $Alarm = null, int $Index = null)
    {
        if (is_null($Alarm)) {
            return;
        }
        foreach ($Alarm as $Key => $Value) {
            $this->{$Key} = $Value;
        }
        $this->Index = $Index;
    }

    /**
     * Schreibt die Wochentag aus dem IPS Format in die Eigenschaft Dow.
     * @param int $Days Die Wochentag im IPS-Format.
     */
    public function IpsToDow(int $Days)
    {
        for ($i = 0; $i < 8; $i++) {
            if ((($Days >> $i) & 0x1) == 1) {
                if ($i == 6) {
                    $Dow[] = 0;
                } else {
                    $Dow[] = $i + 1;
                }
            }
        }
        sort($Dow);
        $this->Dow = implode(',', $Dow);
    }

    /**
     * Liefert die Eigenschaft Dow im IPS-Format.
     * @return int Die Wochentage im IPS-Format.
     */
    public function DowToIps()
    {
        if ($this->Dow == '') {
            return 0;
        }
        $Days = explode(',', $this->Dow);
        $IpsDays = 0;
        foreach ($Days as $Day) {
            $Day--;
            if ($Day < 0) {
                $Day = 6;
            }
            $IpsDays += pow(2, $Day);
        }
        return $IpsDays;
    }

    /**
     * Fügt einen Wochentag zum Wekcer hinzu.
     * @param int $Dow Ein Wochentag.
     */
    public function AddDow(int $Dow)
    {
        if ($this->Dow == '') {
            $this->Dow = (string) $Dow;
            return;
        }
        $Days = explode(',', $this->Dow);
        $Key = array_search((string) $Dow, $Days);
        if ($Key !== false) {
            return;
        }
        $Days[] = (string) $Dow;
        sort($Days);
        $this->Dow = implode(',', $Days);
    }

    /**
     * Löscht einen Wochentag aus dem Wecker.
     * @param int $Dow Ein Wochentag.
     */
    public function DelDow(int $Dow)
    {
        if ($this->Dow == '') {
            return;
        }
        $Days = explode(',', $this->Dow);
        $Key = array_search((string) $Dow, $Days);
        if ($Key === false) {
            return;
        }
        unset($Days[$Key]);
        sort($Days);
        $this->Dow = implode(',', $Days);
    }

    /**
     * Liefert die Alarmzeit als Array für die Ereignisse in IPS.
     * @return array
     */
    public function TimeToArray()
    {
//        $time = getdate($this->Time);
//        $result['Second'] = $time["seconds"];
//        $result['Minute'] = $time["minutes"];
//        $result['Hour'] = $time["hours"] - gettimeofday()["dsttime"];
        //$time = getdate($this->Time);
        $result['Second'] = (int) gmdate('s', $this->Time);
        $result['Minute'] = (int) gmdate('i', $this->Time);
        $result['Hour'] = (int) gmdate('G', $this->Time);
        return $result;
    }

    /**
     * Schreibt die Alarmzeit aus dem übergeben Array in die Eigenschaft Time.
     * @param array $Time
     */
    public function ArrayToTime($Time)
    {
        $this->Time = ((($Time[0] * 60) + $Time[1]) * 60) + $Time[2];
    }

}

/**
 * LSA_AlarmList ist eine Klasse welche ein Array von LSA_Alarm enthält.
 *
 */
class LSA_AlarmList
{
    /**
     * Array mit allen Items.
     * @var array
     * @access public
     */
    public $Items = array();

    /**
     * Liefert die Daten welche behalten werden müssen.
     * @access public
     */
    public function __sleep()
    {
        return array('Items');
    }

    /**
     * Erzeugt eine neue LSA_AlarmList aus einem Array von LMSTaggingArray mit allen Alarmen.
     * @param array $Alarms Alle Wecker oder null.
     * @return LSA_AlarmList
     */
    public function __construct(array $Alarms = null)
    {
        if (is_null($Alarms)) {
            return;
        }
        foreach ($Alarms as $Index => $AlarmData) {
            $Alarm = new LSA_Alarm($AlarmData, $Index);
            $this->Items[$Alarm->Id] = $Alarm;
        }
    }

    /**
     * Fügt einen LSA_Alarm in $Items hinzu.
     * @access public
     * @param LSA_Alarm $Alarm Das neue Objekt.
     * @return int Der Index des Items.
     */
    public function Add(LSA_Alarm $Alarm)
    {
        if (isset($this->Items[$Alarm->Id])) {
            return $this->Update($Alarm);
        }

        $NewIndex = count($this->Items);
        $this->Items[$Alarm->Id] = $Alarm;
        $this->Items[$Alarm->Id]->Index = $NewIndex;
        return $NewIndex;
    }

    /**
     * Update für einen LSA_Alarm in $Items.
     * @access public
     * @param LSA_Alarm $Alarm Das neue Objekt.
     */
    public function Update(LSA_Alarm $Alarm)
    {
        $this->Items[$Alarm->Id] = $Alarm;
    }

    /**
     * Löscht einen LSA_Alarm aus $Items.
     * @access public
     * @param string $AlarmId Der Index des zu löschenden Items.
     */
    public function Remove(string $AlarmId)
    {
        if (!isset($this->Items[$AlarmId])) {
            return;
        }
        $RemoveIndex = $this->Items[$AlarmId]->Index;
        unset($this->Items[$AlarmId]);
        foreach ($this->Items as &$Alarm) {
            if ($Alarm->Index > $RemoveIndex) {
                $Alarm->Index--;
            }
        }
    }

    /**
     * Liefert einen bestimmten LSA_Alarm aus den Items anhand der Id.
     * @access public
     * @param string $AlarmId
     * @return LSA_Alarm $Alarm
     */
    public function GetById(string $AlarmId)
    {
        if (!isset($this->Items[$AlarmId])) {
            return false;
        }
        return $this->Items[$AlarmId];
    }

    /**
     * Liefert einen bestimmten LSA_Alarm aus den Items anhand des Index.
     * @access public
     * @param int $Index
     * @return LSA_Alarm $Alarm
     */
    public function GetByIndex(int $Index)
    {
        foreach ($this->Items as $Alarm) {
            if ($Alarm->Index == $Index) {
                return $Alarm;
            }
        }
        return false;
    }

}

/**
 * SqueezeboxAlarm Klasse für die Wecker einer SqueezeBox als Instanz in IPS.
 * Erweitert IPSModule.
 *
 * @package       Squeezebox
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2017 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.0
 * @example <b>Ohne</b>
 * @property int $ParentID
 * @property array $Multi_Playlist Alle Datensätze der Alarm-Playlisten.
 * @property LSA_AlarmList $Alarms Alle Wecker als Objekt.
 */
class SqueezeboxAlarm extends IPSModule
{

    use VariableProfile,
        LMSHTMLTable,
        DebugHelper,
        BufferHelper,
        InstanceStatus,
        VariableHelper,
        Webhook {
        InstanceStatus::MessageSink as IOMessageSink;
    }
    /**
     * TCP-Socket
     * @var resource
     * @access privat
     */
    private $Socket = false;

    /**
     * Destruktor
     * schließt bei bedarf den noch offnen TCP-Socket.
     */
    public function __destruct()
    {
        if ($this->Socket) {
            fclose($this->Socket);
        }
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent("{96A9AB3A-2538-42C5-A130-FC34205A706A}");
        $this->SetReceiveDataFilter('.*(?=.*"Address":"".*)(?=(.*"Command":\["alarm.*)|(.*"Command":\["client".*)|(.*"Command":\["playerpref","alarm.*)|(.*"Command":\["prefset","server","alarm.*)).*');
        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyBoolean("dynamicDisplay", false);
        $this->RegisterPropertyBoolean("showAdd", true);
        $this->RegisterPropertyBoolean("showDelete", true);
        $this->RegisterPropertyBoolean("showAlarmPlaylist", true);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString("Table", json_encode($Style['Table']));
        $this->RegisterPropertyString("Columns", json_encode($Style['Columns']));
        $this->RegisterPropertyString("Rows", json_encode($Style['Rows']));

        $this->Multi_Playlist = array();
        $this->Alarms = new LSA_AlarmList(array());
        $this->ParentID = 0;
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook('/hook/LSAPlaylist' . $this->InstanceID);
            $this->DeleteProfile();
        }
        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        $this->SetReceiveDataFilter('.*(?=.*"Address":"".*)(?=(.*"Command":\["alarm.*)|(.*"Command":\["client".*)|(.*"Command":\["playerpref","alarm.*)|(.*"Command":\["prefset","server","alarm.*)).*');

        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        $this->Multi_Playlist = array();
        $this->ParentID = 0;
        $this->Alarms = new LSA_AlarmList(array());

        parent::ApplyChanges();

        // Addresse prüfen
        $Address = $this->ReadPropertyString('Address');
        $changeAddress = false;
        //ip Adresse:
        if (preg_match("/\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b/", $Address) !== 1) {
            // Keine IP Adresse
            if (strlen($Address) == 12) {
                $Address = strtolower(implode(":", str_split($Address, 2)));
                $changeAddress = true;
            }
            if (preg_match("/^([0-9A-Fa-f]{2}[-]){5}([0-9A-Fa-f]{2})$/", $Address) === 1) {
                $Address = strtolower(str_replace('-', ':', $Address));
                $changeAddress = true;
            }
            if ($Address <> strtolower($Address)) {
                $Address = strtolower($Address);
                $changeAddress = true;
            }
        }
        if ($changeAddress) {
            IPS_SetProperty($this->InstanceID, 'Address', $Address);
            IPS_ApplyChanges($this->InstanceID);
            return;
        }

        // Addresse als Filter setzen
        $this->SetReceiveDataFilter('.*(?=.*"Address":"' . $Address . '".*)(?=(.*"Command":\["alarm.*)|(.*"Command":\["client".*)|(.*"Command":\["playerpref","alarm.*)|(.*"Command":\["prefset","server","alarm.*)).*');
        $this->SetSummary($Address);



        // Profile anlegen
        $this->RegisterProfileInteger("LSA.Intensity", "Intensity", "", " %", 0, 100, 1);
        $this->RegisterProfileInteger("LSA.Timeout", "Clock", "", $this->Translate(" sec"), 0, 600, 1);
        $this->RegisterProfileInteger("LSA.Snooze", "Clock", "", $this->Translate(" sec"), 0, 1800, 1);
        $this->RegisterProfileIntegerEx("LSA.Shuffle", "Shuffle", "", "", array(
            array(0, "off", "", -1),
            array(1, $this->Translate("Title"), "", -1),
            array(2, "Album", "", -1)
        ));
        $this->RegisterProfileIntegerEx("LSA.Add", "Bell", "", "", array(
            array(0, $this->Translate("Add"), "", -1)
        ));
        $this->RegisterProfileIntegerEx("LSA.State", "Bell", "", "", array(
            array(0, $this->Translate("end"), "", -1),
            array(1, $this->Translate("snooze"), "", -1),
            array(2, $this->Translate("sounding"), "", -1),
        ));
        $this->RefreshDeleteProfil(0);

        //Status-Variablen anlegen
        $this->RegisterVariableBoolean("EnableAll", $this->Translate("All alarms active"), "~Switch", 1);
        $this->EnableAction("EnableAll");
        $this->RegisterVariableInteger("DefaultVolume", $this->Translate("Default alarm volume"), "LSA.Intensity", 2);
        $this->EnableAction("DefaultVolume");
        $this->RegisterVariableBoolean("FadeIn", $this->Translate("Fade alarm"), "~Switch", 3);
        $this->EnableAction("FadeIn");
        $this->RegisterVariableInteger("Timeout", $this->Translate("Automatically stop"), "LSA.Timeout", 4);
        $this->EnableAction("Timeout");
        $this->RegisterVariableInteger("SnoozeSeconds", $this->Translate("Snoozetime"), "LSA.Snooze", 4);
        $this->EnableAction("SnoozeSeconds");


        if ($this->ReadPropertyBoolean("showAdd")) {
            $this->RegisterVariableInteger("AddAlarm", $this->Translate("Add alarm"), "LSA.Add", 4);
            $this->EnableAction("AddAlarm");
        } else {
            $this->UnregisterVariable("AddAlarm");
        }

        if ($this->ReadPropertyBoolean("showDelete")) {
            $this->RegisterVariableInteger("DelAlarm", $this->Translate("Delete alarm"), "LSA.Del." . $this->InstanceID, 5);
            $this->EnableAction("DelAlarm");
        } else {
            $this->UnregisterVariable("DelAlarm");
            $this->UnregisterProfil("LSA.Del." . $this->InstanceID);
        }

        if (!$this->ReadPropertyBoolean('showAlarmPlaylist')) {
            for ($AlarmIndex = 0; $AlarmIndex < 10; $AlarmIndex++) {
                $vid = @$this->GetIDForIdent('AlarmPlaylist' . $AlarmIndex);
                if ($vid > 0) {
                    IPS_DeleteVariable($vid);
                }
            }
        }

        // Wenn Kernel nicht bereit, dann warten... wenn unser IO Aktiv wird, holen wir unsere Daten :)
        if (IPS_GetKernelRunlevel() <> KR_READY) {
            return;
        }
        if ($this->ReadPropertyBoolean('showAlarmPlaylist')) {
            $this->RegisterHook('/hook/LSAPlaylist' . $this->InstanceID);
        } else {
            $this->UnregisterHook('/hook/LSAPlaylist' . $this->InstanceID);
        }
        $this->RegisterParent();
        // Wenn Parent aktiv, dann Anmeldung an der Hardware bzw. Datenabgleich starten
        if ($this->HasActiveParent()) {
            $this->IOChangeState(IS_ACTIVE);
        }
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     *
     * @param type $TimeStamp
     * @param type $SenderID
     * @param type $Message
     * @param type $Data
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            case EM_CHANGEACTIVE:
                $this->SendDebug('EM_CHANGEACTIVE', $Data, 0);
                $Index = (int) substr(IPS_GetObject($SenderID)['ObjectIdent'], -1);
                $Alarms = $this->Alarms;
                $Alarm = $Alarms->GetByIndex($Index);
                $Alarm->Enabled = $Data[0];
                $Alarms->Update($Alarm);
                $this->Alarms = $Alarms;

                /* @var $Alarm LSA_Alarm */
                $this->Send(new LMSData(array('alarm', 'update'), array('id:' . $Alarm->Id, 'enabled:' . (int) $Data[0])));
                break;
            case EM_CHANGECYCLIC:
                $this->SendDebug('EM_CHANGECYCLIC', $Data, 0);
                $Index = (int) substr(IPS_GetObject($SenderID)['ObjectIdent'], -1);
                $Alarms = $this->Alarms;
                $Alarm = $Alarms->GetByIndex($Index);
                $Alarm->IpsToDow($Data[2]);
                $Alarms->Update($Alarm);
                $this->Alarms = $Alarms;
                /* @var $Alarm LSA_Alarm */
                $this->Send(new LMSData(array('alarm', 'update'), array('id:' . $Alarm->Id, 'dow:' . $Alarm->Dow)));
                break;
            case EM_CHANGECYCLICTIMEFROM:
                $this->SendDebug('EM_CHANGECYCLICTIMEFROM', $Data, 0);
                $Index = (int) substr(IPS_GetObject($SenderID)['ObjectIdent'], -1);
                $Alarms = $this->Alarms;
                $Alarm = $Alarms->GetByIndex($Index);
                $Alarm->ArrayToTime($Data);
                $Alarms->Update($Alarm);
                $this->Alarms = $Alarms;
                /* @var $Alarm LSA_Alarm */
                $this->Send(new LMSData(array('alarm', 'update'), array('id:' . $Alarm->Id, 'time:' . $Alarm->Time)));
                break;
        }
    }

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        $this->RegisterParent();
        if ($this->HasActiveParent()) {
            $this->IOChangeState(IS_ACTIVE);
        }
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     * @access protected
     */
    protected function IOChangeState($State)
    {
        if ($State == IS_ACTIVE) {
            $this->LoadAlarmPlaylists();
            $this->RequestAllState();
        }
    }

    /**
     * Verarbeitet Daten aus dem Webhook.
     *
     * @access protected
     * @param string $ID Die ausgewählte Alarm-Playlist als URL.
     * @global array $_GET
     */
    protected function ProcessHookdata()
    {
        if ((!isset($_GET["ID"])) or ( !isset($_GET["Type"])) or ( !isset($_GET["Secret"]))) {
            echo $this->Translate("Bad Request");
            return;
        }
        $AlarmIndex = substr($_GET["Type"], -1);
        $MySecret = $this->{'WebHookSecretAlarmPlaylist' . $AlarmIndex};
        $CalcSecret = base64_encode(sha1($MySecret . "0" . rawurldecode($_GET["ID"]), true));

        if ($CalcSecret != rawurldecode($_GET["Secret"])) {
            echo $this->Translate("Access denied");
            return;
        }

        if (substr($_GET["Type"], 0, -1) != 'AlarmPlaylist') {
            echo $this->Translate("Bad Request");
            return;
        }

        if ($this->SetPlaylist((int) $AlarmIndex, rawurldecode($_GET["ID"]))) {
            echo "OK";
        }
    }

    ################## PRIVATE
    /**
     * Löscht die nicht mehr benötigten Profile.
     * @access private
     */
    private function DeleteProfile()
    {
        $this->UnregisterProfil("LSA.Intensity");
        $this->UnregisterProfil("LSA.Shuffle");
        $this->UnregisterProfil("LSA.Add");
        $this->UnregisterProfil("LSA.Del." . $this->InstanceID);
    }

    /**
     * Liefert die Werkeinstellungen für die Eigenschaften Html, Table und Rows.
     * @access private
     * @return array
     */
    private function GenerateHTMLStyleProperty()
    {
        $NewTableConfig = array(
            array(
                "tag"   => "<table>",
                "style" => "margin:0 auto; font-size:0.8em;"),
            array(
                "tag"   => "<thead>",
                "style" => ""),
            array(
                "tag"   => "<tbody>",
                "style" => "")
        );
        $NewColumnsConfig = array(
            array(
                "index" => 0,
                "key"   => "Index",
                "name"  => "Index",
                "show"  => false,
                "width" => 100,
                "color" => 0xffffff,
                "align" => "center",
                "style" => ""),
            array(
                "index" => 1,
                "key"   => "Category",
                "name"  => $this->Translate("Category"),
                "show"  => true,
                "width" => 300,
                "color" => 0xffffff,
                "align" => "center",
                "style" => ""),
            array(
                "index" => 2,
                "key"   => "Title",
                "name"  => $this->Translate("Title"),
                "show"  => true,
                "width" => 300,
                "color" => 0xffffff,
                "align" => "center",
                "style" => ""),
            array(
                "index" => 3,
                "key"   => "Url",
                "name"  => "Url",
                "show"  => false,
                "width" => 300,
                "color" => 0xffffff,
                "align" => "center",
                "style" => "")
        );
        $NewRowsConfig = array(
            array(
                "row"     => "odd",
                "name"    => $this->Translate("odd"),
                "bgcolor" => 0x000000,
                "color"   => 0xffffff,
                "style"   => ""),
            array(
                "row"     => "even",
                "name"    => $this->Translate("even"),
                "bgcolor" => 0x080808,
                "color"   => 0xffffff,
                "style"   => ""),
            array(
                "row"     => "active",
                "name"    => $this->Translate("active"),
                "bgcolor" => 0x808000,
                "color"   => 0xffffff,
                "style"   => "")
        );
        return array('Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig);
    }

    /**
     * Erzeugt eine HTML-Tabelle mit allen Playlisten für eine ~HTMLBox-Variable.
     * @param int $AlarmIndex Der Index des Alarms.
     * @param int $PlaylistIndex Der Index der aktuell gewählten Alarm-Playliste
     * @access private
     */
    private function RefreshPlaylist(int $AlarmIndex, int $PlaylistIndex)
    {
        if (!$this->ReadPropertyBoolean('showAlarmPlaylist')) {
            return;
        }
        $vid = @$this->GetIDForIdent('AlarmPlaylist' . $AlarmIndex);
        if ($vid === false) {
            $vid = $this->RegisterVariableString('AlarmPlaylist' . $AlarmIndex, sprintf($this->Translate('Alarm %d playlist selection'), $AlarmIndex + 1), '~HTMLBox', (($AlarmIndex + 1) * 10) + 7);
            IPS_SetIcon($vid, 'Database');
        }

        $Data = $this->Multi_Playlist;
        if (!is_array($Data)) {
            $Data = array();
        }

        $HTML = $this->GetTable($Data, 'LSAPlaylist', 'AlarmPlaylist' . $AlarmIndex, 'Url', $PlaylistIndex + 1);
        $this->SetValueString('AlarmPlaylist' . $AlarmIndex, $HTML);
    }

    /**
     * Liefert einen eintrag aus den Alarm-Playlisten anhand der URL.
     * @access private
     * @param string $Url Die URL des zu suchenden Eintrages.
     * @return array Der gefundene Eintrag.
     */
    private function GetPlaylistItemFromUrl(string $Url)
    {
        $Playlist = $this->Multi_Playlist;
        if (count($Playlist) == 0) {
            $this->LoadAlarmPlaylists();
        }
        if ($Url == 'CURRENT_PLAYLIST') {
            $Url = '';
        }
        foreach ($Playlist as /* $Index => */ $Item) {
            if ($Item['Url'] == $Url) {
                return $Item;
            }
        }
    }

    /**
     * Aktualisiert das Profil LSA.Del* mit der korrekten Anzahl der Alarme.
     * @param int $Count Anzahl der Alarme.
     */
    private function RefreshDeleteProfil(int $Count)
    {
        $Assos = array();
        for ($index = 0; $index < $Count; $index++) {
            $Assos[] = array($index, (string) ($index + 1), "", -1);
        }
        $this->RegisterProfileIntegerEx("LSA.Del." . $this->InstanceID, "Cross", "", "", $Assos);
    }

    /**
     * Lädt alle für diesen Player gültige Alarm-Playlisten und speichert sie im Buffer.
     * @access private
     */
    private function LoadAlarmPlaylists()
    {
        $LMSData = $this->SendDirect(new LMSData(array('alarm', 'playlists'), array(0, 100000)));
        if ($LMSData === null) {
            $this->Multi_Playlist = array();
            return;
        }
        $LMSData->SliceData();
        $AlarmPlaylist = (new LMSTaggingArray($LMSData->Data, 'category'))->DataArray();
        foreach ($AlarmPlaylist as $Index => &$Item) {
            $Item['Index'] = $Index;
        }
        $this->Multi_Playlist = $AlarmPlaylist;
    }

    /**
     *  Erzeugt und Aktualisiert die Statusvariablen eines Alarms bei Veränderung.
     *
     * @access private
     * @param LSA_Alarm $Alarm Ein Alarm.
     */
    private function RefreshEvent(LSA_Alarm $Alarm)
    {
        $eid = @$this->GetIDForIdent('AlarmTime' . $Alarm->Index);
        if ($eid > 0) {
            $this->UnregisterMessage($eid, EM_CHANGEACTIVE);
            $this->UnregisterMessage($eid, EM_CHANGECYCLIC);
            $this->UnregisterMessage($eid, EM_CHANGECYCLICTIMEFROM);
        } else {
            $eid = IPS_CreateEvent(1);
            IPS_SetParent($eid, $this->InstanceID);
            IPS_SetIdent($eid, 'AlarmTime' . $Alarm->Index);
            IPS_SetName($eid, sprintf($this->Translate('Alarm %d alarmtime'), $Alarm->Index + 1));
            IPS_SetPosition($eid, (($Alarm->Index + 1) * 10) + 1);
            IPS_SetIcon($eid, 'Alert');
            IPS_SetEventScript($eid, $this->Translate("/*\r\nDont change the action and target of this event!\r\nPut your code after this comment.\r\nBeware, that this event can be deletet when\r\n'Delete unused objects' is set.\r\n*/"));
            IPS_SetEventCyclic($eid, 3, 1, 127, 0, 0, 0);
        }
        $Event = IPS_GetEvent($eid);

        if ($Event['EventActive'] != $Alarm->Enabled) {
            IPS_SetEventActive($eid, $Alarm->Enabled);
        }

        $IpsDow = $Alarm->DowToIps();
        if ($Event['CyclicDateDay'] != $IpsDow) {
            IPS_SetEventCyclic($eid, 3, 1, $IpsDow, 0, 0, 0);
        }

        $IpsTime = $Alarm->TimeToArray();
        $Update = false;
        foreach ($Event['CyclicTimeFrom'] as $Key => $Value) {
            if ($Value != $IpsTime[$Key]) {
                $Update = true;
            }
        }
        if ($Update) {
            IPS_SetEventCyclicTimeFrom($eid, $IpsTime['Hour'], $IpsTime['Minute'], $IpsTime['Second']);
        }

        $this->RegisterMessage($eid, EM_CHANGEACTIVE);
        $this->RegisterMessage($eid, EM_CHANGECYCLIC);
        $this->RegisterMessage($eid, EM_CHANGECYCLICTIMEFROM);

        $PlaylistItem = $this->GetPlaylistItemFromUrl($Alarm->Url);

        $vid = @$this->GetIDForIdent('AlarmPlaylistName' . $Alarm->Index);
        if ($vid === false) {
            $vid = $this->RegisterVariableString('AlarmPlaylistName' . $Alarm->Index, sprintf($this->Translate('Alarm %d playlist'), $Alarm->Index + 1), '', (($Alarm->Index + 1) * 10) + 6);
            IPS_SetIcon($vid, 'Database');
        }
        $this->SetValueString('AlarmPlaylistName' . $Alarm->Index, $PlaylistItem['Title']);

        $this->RefreshPlaylist($Alarm->Index, $PlaylistItem['Index']);

        $vid = @$this->GetIDForIdent('AlarmShuffle' . $Alarm->Index);
        if ($vid === false) {
            $this->RegisterVariableInteger('AlarmShuffle' . $Alarm->Index, sprintf($this->Translate('Alarm %d playlist shuffle'), $Alarm->Index + 1), "LSA.Shuffle", (($Alarm->Index + 1) * 10) + 5);
            $this->EnableAction('AlarmShuffle' . $Alarm->Index);
        }
        $this->SetValueInteger('AlarmShuffle' . $Alarm->Index, $Alarm->Shufflemode);

        $vid = @$this->GetIDForIdent('AlarmRepeat' . $Alarm->Index);
        if ($vid === false) {
            $vid = $this->RegisterVariableBoolean('AlarmRepeat' . $Alarm->Index, sprintf($this->Translate('Alarm %d repeat'), $Alarm->Index + 1), '~Switch', (($Alarm->Index + 1) * 10) + 4);
            $this->EnableAction('AlarmRepeat' . $Alarm->Index);
            IPS_SetIcon($vid, 'Repeat');
        }
        $this->SetValueBoolean('AlarmRepeat' . $Alarm->Index, $Alarm->Repeat);

        $vid = @$this->GetIDForIdent('AlarmVolume' . $Alarm->Index);
        if ($vid === false) {
            $this->RegisterVariableInteger('AlarmVolume' . $Alarm->Index, sprintf($this->Translate('Alarm %d volume'), $Alarm->Index + 1), "LSA.Intensity", (($Alarm->Index + 1) * 10) + 3);
            $this->EnableAction('AlarmVolume' . $Alarm->Index);
        }
        $this->SetValueInteger('AlarmVolume' . $Alarm->Index, $Alarm->Volume);

        $vid = @$this->GetIDForIdent('AlarmState' . $Alarm->Index);
        if ($vid === false) {
            $this->RegisterVariableInteger('AlarmState' . $Alarm->Index, sprintf($this->Translate('Alarm %d state'), $Alarm->Index + 1), "LSA.State", (($Alarm->Index + 1) * 10) + 2);
            $this->EnableAction('AlarmState' . $Alarm->Index);
        }
    }

    /**
     *  Aktualisiert alle Statusvariablen bei Veränderung und löscht ggfls. Statusvariablen.
     *
     * @access private
     * @param LSA_AlarmList $Alarms Die komplette Alarmliste.
     */
    private function RefreshEvents(LSA_AlarmList $Alarms)
    {
        $Index = 0;
        foreach ($Alarms->Items as $Alarm) {
            /* @var $Alarm LSA_Alarm */
            $Index = ($Index < $Alarm->Index) ? $Alarm->Index : $Index;
            $this->RefreshEvent($Alarm);
        }

        for ($i = $Index + 1; $i < 10; $i++) {
            $delete = $this->ReadPropertyBoolean('dynamicDisplay');
            $eid = @$this->GetIDForIdent('AlarmTime' . $i);
            if ($eid > 0) {
                $this->UnregisterMessage($eid, EM_CHANGEACTIVE);
                $this->UnregisterMessage($eid, EM_CHANGECYCLIC);
                $this->UnregisterMessage($eid, EM_CHANGECYCLICTIMEFROM);
                if ($delete) {
                    IPS_DeleteEvent($eid);
                } else {
                    IPS_SetEventCyclic($eid, 3, 1, 0, 0, 0, 0);
                    IPS_SetEventCyclicTimeFrom($eid, 0, 0, 0);
                    IPS_SetEventActive($eid, false);
                }
            }

            $vid = @$this->GetIDForIdent('AlarmPlaylistName' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueString($vid, '');
                }
            }

            $vid = @$this->GetIDForIdent('AlarmShuffle' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueInteger($vid, 0);
                }
            }

            $vid = @$this->GetIDForIdent('AlarmRepeat' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueBoolean($vid, false);
                }
            }

            $vid = @$this->GetIDForIdent('AlarmVolume' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueInteger($vid, 0);
                }
            }

            $vid = @$this->GetIDForIdent('AlarmState' . $i);
            if ($vid > 0) {
                if ($delete) {
                    IPS_DeleteVariable($vid);
                } else {
                    $this->SetValueInteger($vid, 0);
                }
            }

            $vid = @$this->GetIDForIdent('AlarmPlaylist' . $i);
            if ($vid > 0) {
                if ($this->ReadPropertyBoolean('showAlarmPlaylist')) {
                    if ($delete) {
                        IPS_DeleteVariable($vid);
                    } else {
                        $this->SetValueString($vid, '');
                    }
                } else {
                    IPS_DeleteVariable($vid);
                }
            }
        }
    }

    ################## PUBLIC
    /**
     * IPS-Instanz-Funktion 'LSA_RequestAllState'.
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen.
     * @return boolean True wenn alle Abfragen erfolgreich waren, sonst false.
     */
    public function RequestAllState()
    {
        if (!$this->RequestState('EnableAll')) {
            return false;
        }
        $ret = $this->RequestState('DefaultVolume');
        $ret = $ret && $this->RequestState('FadeIn');
        $ret = $ret && $this->RequestState('Timeout');
        $ret = $ret && $this->RequestState('SnoozeSeconds');
        $ret = $ret && $this->RequestState('Alarms');
        return $ret;
    }

    /**
     * IPS-Instanz-Funktion 'LSA_RequestState'.
     * Fragt einen Wert der Alarme ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @access public
     * @param string $Ident Der Ident der abzufragenden Statusvariable.
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function RequestState(string $Ident)
    {
        switch ($Ident) {
            case 'Alarms':
                $LMSResponse = new LMSData('alarms', array('0', '10', 'filter:all'));
                break;
            case 'EnableAll':
                $LMSResponse = new LMSData(array('playerpref', 'alarmsEnabled'), '?');
                break;
            case 'DefaultVolume':
                $LMSResponse = new LMSData(array('playerpref', 'alarmDefaultVolume'), '?');
                break;
            case 'FadeIn':
                $LMSResponse = new LMSData(array('playerpref', 'alarmfadeseconds'), '?');
                break;
            case 'Timeout':
                $LMSResponse = new LMSData(array('playerpref', 'alarmTimeoutSeconds'), '?');
                break;
            case 'SnoozeSeconds':
                $LMSResponse = new LMSData(array('playerpref', 'alarmSnoozeSeconds'), '?');
                break;
            case "AlarmPlaylistName0":
            case "AlarmPlaylistName1":
            case "AlarmPlaylistName2":
            case "AlarmPlaylistName3":
            case "AlarmPlaylistName4":
            case "AlarmPlaylistName5":
            case "AlarmPlaylistName6":
            case "AlarmPlaylistName7":
            case "AlarmPlaylistName8":
            case "AlarmPlaylistName9":
            case "AlarmRepeat0":
            case "AlarmRepeat1":
            case "AlarmRepeat2":
            case "AlarmRepeat3":
            case "AlarmRepeat4":
            case "AlarmRepeat5":
            case "AlarmRepeat6":
            case "AlarmRepeat7":
            case "AlarmRepeat8":
            case "AlarmRepeat9":
            case "AlarmVolume0":
            case "AlarmVolume1":
            case "AlarmVolume2":
            case "AlarmVolume3":
            case "AlarmVolume4":
            case "AlarmVolume5":
            case "AlarmVolume6":
            case "AlarmVolume7":
            case "AlarmVolume8":
            case "AlarmVolume9":
                $LMSResponse = new LMSData('alarms', array('0', '10', 'filter:all'));
                break;
            case "AlarmShuffle0":
            case "AlarmShuffle1":
            case "AlarmShuffle2":
            case "AlarmShuffle3":
            case "AlarmShuffle4":
            case "AlarmShuffle5":
            case "AlarmShuffle6":
            case "AlarmShuffle7":
            case "AlarmShuffle8":
            case "AlarmShuffle9":
            case "AlarmState0":
            case "AlarmState1":
            case "AlarmState2":
            case "AlarmState3":
            case "AlarmState4":
            case "AlarmState5":
            case "AlarmState6":
            case "AlarmState7":
            case "AlarmState8":
            case "AlarmState9":
                trigger_error($this->Translate('Sorry this request is unsupported by LMS.'), E_USER_NOTICE);
                return false;
            default:
                trigger_error($this->Translate('Ident not valid'), E_USER_NOTICE);
                return false;
        }
        $LMSResponse = $this->SendDirect($LMSResponse);
        if ($LMSResponse == null) {
            return false;
        }
        $LMSResponse->SliceData();
        if ((count($LMSResponse->Data) == 0) or ( $LMSResponse->Data[0] == '?')) {
            trigger_error($this->Translate("Player not connected"), E_USER_NOTICE);
            return false;
        }
        return $this->DecodeLMSResponse($LMSResponse);
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetAllActive'.
     * De/Aktiviert die Wekcer-Funktionen des Gerätes
     *
     * @access public
     * @param bool $Value De/Aktiviert die Wecker.
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function SetAllActive(bool $Value)
    {
        if (!is_bool($Value)) {
            trigger_error(sprintf($this->Translate("%s must be bool."), 'Value'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('alarm', ($Value ? 'enableall' : 'disableall'))));
        if ($LMSData === null) {
            return false;
        }
        return (($LMSData->Command[1]) == ($Value ? 'enableall' : 'disableall'));
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetDefaultVolume'.
     * Setzt die Standard-Lautstärke für neue Wecker.
     *
     * @access public
     * @param int $Value Die neue Lautstärke
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function SetDefaultVolume(int $Value)
    {
        if (!is_int($Value)) {
            trigger_error(sprintf($this->Translate("%s must be integer."), 'Value'), E_USER_NOTICE);
            return false;
        }
        if (($Value < 0) || ($Value > 100)) {
            trigger_error(sprintf($this->Translate("%s out of range."), 'Value'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playerpref', 'alarmDefaultVolume'), (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return ((int) ($LMSData->Data[0]) == (int) $Value);
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetFadeIn'.
     * De/Aktiviert das Einblenden der Wiedergabe.
     *
     * @access public
     * @param bool $Value True zum aktivieren, False zum deaktivieren.
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function SetFadeIn(bool $Value)
    {
        if (!is_bool($Value)) {
            trigger_error(sprintf($this->Translate("%s must be bool."), 'Value'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playerpref', 'alarmfadeseconds'), (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return ((int) ($LMSData->Data[0]) == (int) $Value);
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetTimeout'.
     * Setzt die Zeit in Sekunden bis ein Wecker automatisch beendent wird.
     *
     * @access public
     * @param int $Value Zeit in Sekunden bis zum abschalten.
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function SetTimeout(int $Value)
    {
        if (!is_int($Value)) {
            trigger_error(sprintf($this->Translate("%s must be integer."), 'Value'), E_USER_NOTICE);
            return false;
        }
        if ($Value < 0) {
            trigger_error(sprintf($this->Translate("%s out of range."), 'Value'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playerpref', 'alarmTimeoutSeconds'), (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return ((int) ($LMSData->Data[0]) == (int) $Value);
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetSnoozeSeconds'.
     * Setzt die Schlummerzeit in Sekunden.
     *
     * @access public
     * @param int $Value Die Schlummerzeit in Sekunden.
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function SetSnoozeSeconds(int $Value)
    {
        if (!is_int($Value)) {
            trigger_error(sprintf($this->Translate("%s must be integer."), 'Value'), E_USER_NOTICE);
            return false;
        }
        if ($Value < 0) {
            trigger_error(sprintf($this->Translate("%s out of range."), 'Value'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('playerpref', 'alarmSnoozeSeconds'), (int) $Value));
        if ($LMSData === null) {
            return false;
        }
        return ((int) ($LMSData->Data[0]) == (int) $Value);
    }

    /**
     * IPS-Instanz-Funktion 'LSA_AlarmSnooze'.
     * Sendet das Schlummersignal an das Gerät und pausiert somit einen aktiven Alarm.
     *
     * @access public
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function AlarmSnooze()
    {
        $LMSData = $this->SendDirect(new LMSData(array('button'), array('snooze.single')));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * IPS-Instanz-Funktion 'LSA_AlarmStop'.
     * Beendet ein aktiven Alarm.
     *
     * @access public
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function AlarmStop()
    {
        $LMSData = $this->SendDirect(new LMSData(array('button'), array('power_off')));
        if ($LMSData === null) {
            return false;
        }
        return true;
    }

    /**
     * IPS-Instanz-Funktion 'LSA_AddAlarm'.
     * Fragt einen Wert der Alarme ab. Es ist der Ident der Statusvariable zu übergeben.
     *
     * @access public
     * @result bool|int Index des Weckers, im Fehlerfall false.
     */
    public function AddAlarm()
    {
        if (count($this->Alarms->Items) > 9) {
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('alarm', 'add'), array('enabled:1', 'time:25200', 'repeat:1', 'playlisturl:CURRENT_PLAYLIST')));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new LMSTaggingArray($LMSData->Data, ''))->DataArray();
        if (isset($Data['Id'])) {
            return count($this->Alarms->Items);
        }
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'LSA_DelAlarm'.
     * Löscht einen Wecker.
     *
     * @access public
     * @param int $AlarmIndex Der Index des zu löschenden Weckers.
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function DelAlarm(int $AlarmIndex)
    {
        if (!is_int($AlarmIndex)) {
            trigger_error(sprintf($this->Translate("%s must be integer."), 'AlarmIndex'), E_USER_NOTICE);
            return false;
        }
        $Alarms = $this->Alarms;
        $Alarm = $Alarms->GetByIndex($AlarmIndex);
        if ($Alarm === false) {
            trigger_error(sprintf($this->Translate("%s out of range."), 'AlarmIndex'), E_USER_NOTICE);
            return false;
        }

        $LMSData = $this->SendDirect(new LMSData(array('alarm', 'delete'), array('id:' . $Alarm->Id)));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new LMSTaggingArray($LMSData->Data, ''))->DataArray();
        return ($Data['Id'] === $Alarm->Id);
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetPlaylist'.
     * Setzt die Playliste bzw. die Wiedergabe für den Wecker.
     *
     * @access public
     * @param int $AlarmIndex Der Index des Weckers.
     * @param string $Url Die wiederzugebene URL.
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function SetPlaylist(int $AlarmIndex, string $Url)
    {
        if (!is_string($Url)) {
            trigger_error(sprintf($this->Translate("%s must be string."), 'Url'), E_USER_NOTICE);
            return false;
        }
        if (!is_int($AlarmIndex)) {
            trigger_error(sprintf($this->Translate("%s must be integer."), 'AlarmIndex'), E_USER_NOTICE);
            return false;
        }
        $Alarms = $this->Alarms;
        $Alarm = $Alarms->GetByIndex($AlarmIndex);
        if ($Alarm === false) {
            trigger_error(sprintf($this->Translate("%s out of range."), 'AlarmIndex'), E_USER_NOTICE);
            return false;
        }
        if (($Url == '0') or ( $Url == '')) {
            $Url = 'CURRENT_PLAYLIST';
        }

        $LMSData = $this->SendDirect(new LMSData(array('alarm', 'update'), array('id:' . $Alarm->Id, 'playlisturl:' . (string) $Url)));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new LMSTaggingArray($LMSData->Data, ''))->DataArray();
        return (($Data['Playlisturl']) === (string) $Url);
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetShuffle'.
     * Setzt den Modus der zufälligen Wiedergabe des Weckers.
     *
     * @access public
     * @param int $AlarmIndex Der Index des Weckers.
     * @param string $Value Der neue Modus.
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function SetShuffle(int $AlarmIndex, int $Value)
    {
        if (!is_int($Value)) {
            trigger_error(sprintf($this->Translate("%s must be integer."), 'Value'), E_USER_NOTICE);
            return false;
        }
        if (($Value < 0) or ( $Value > 2)) {
            trigger_error(sprintf($this->Translate("%s must be 0, 1 or 2."), 'Value'), E_USER_NOTICE);
            return false;
        }
        if (!is_int($AlarmIndex)) {
            trigger_error(sprintf($this->Translate("%s must be integer."), 'AlarmIndex'), E_USER_NOTICE);
            return false;
        }
        $Alarms = $this->Alarms;
        $Alarm = $Alarms->GetByIndex($AlarmIndex);
        if ($Alarm === false) {
            trigger_error(sprintf($this->Translate("%s out of range."), 'AlarmIndex'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('alarm', 'update'), array('id:' . $Alarm->Id, 'shufflemode:' . (int) $Value)));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new LMSTaggingArray($LMSData->Data, ''))->DataArray();
        return ((int) ($Data['Shufflemode']) === (int) $Value);
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetRepeat'.
     * De/Aktiviert die Wiederholung.
     *
     * @access public
     * @param int $AlarmIndex Der Index des Weckers.
     * @param bool $Value De/Aktiviert die Wiederholung.
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function SetRepeat(int $AlarmIndex, bool $Value)
    {
        if (!is_bool($Value)) {
            trigger_error(sprintf($this->Translate("%s must be bool."), 'Value'), E_USER_NOTICE);
            return false;
        }
        if (!is_int($AlarmIndex)) {
            trigger_error(sprintf($this->Translate("%s must be integer."), 'AlarmIndex'), E_USER_NOTICE);
            return false;
        }
        $Alarms = $this->Alarms;
        $Alarm = $Alarms->GetByIndex($AlarmIndex);
        if ($Alarm === false) {
            trigger_error(sprintf($this->Translate("%s out of range."), 'AlarmIndex'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('alarm', 'update'), array('id:' . $Alarm->Id, 'repeat:' . (int) $Value)));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new LMSTaggingArray($LMSData->Data, ''))->DataArray();
        return ((int) ($Data['Repeat']) === (int) $Value);
    }

    /**
     * IPS-Instanz-Funktion 'LSA_SetVolume'.
     * Setzt die Lautstärke des Weckers.
     *
     * @access public
     * @param int $AlarmIndex Der Index des Weckers.
     * @param int $Value Die neue Lautstärke des Weckers.
     * @result bool True wenn erfolgreich, sonst false.
     */
    public function SetVolume(int $AlarmIndex, int $Value)
    {
        if (!is_int($Value)) {
            trigger_error(sprintf($this->Translate("%s must be integer."), 'Value'), E_USER_NOTICE);
            return false;
        }
        if (($Value < 0) || ($Value > 100)) {
            trigger_error(sprintf($this->Translate("%s out of range."), 'Value'), E_USER_NOTICE);
            return false;
        }
        if (!is_int($AlarmIndex)) {
            trigger_error(sprintf($this->Translate("%s must be integer."), 'AlarmIndex'), E_USER_NOTICE);
            return false;
        }
        $Alarms = $this->Alarms;
        $Alarm = $Alarms->GetByIndex($AlarmIndex);
        if ($Alarm === false) {
            trigger_error(sprintf($this->Translate("%s out of range."), 'AlarmIndex'), E_USER_NOTICE);
            return false;
        }
        $LMSData = $this->SendDirect(new LMSData(array('alarm', 'update'), array('id:' . $Alarm->Id, 'volume:' . (int) $Value)));
        if ($LMSData === null) {
            return false;
        }
        $Data = (new LMSTaggingArray($LMSData->Data, ''))->DataArray();
        return ((int) ($Data['Volume']) === (int) $Value);
    }

    ################## ActionHandler
    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case "EnableAll":
                $result = $this->SetAllActive((bool) $Value);
                break;
            case "DefaultVolume":
                $result = $this->SetDefaultVolume((int) $Value);
                break;
            case "FadeIn":
                $result = $this->SetFadeIn((bool) $Value);
                break;
            case 'AddAlarm':
                $AlarmIndex = $this->AddAlarm();
                $result = ($AlarmIndex !== false);
                break;
            case 'DelAlarm':
                $result = $this->DelAlarm((int) $Value);
                break;
            case 'Timeout':
                $result = $this->SetTimeout((int) $Value);
                break;
            case 'SnoozeSeconds':
                $result = $this->SetSnoozeSeconds((int) $Value);
                break;
            case "AlarmShuffle0":
            case "AlarmShuffle1":
            case "AlarmShuffle2":
            case "AlarmShuffle3":
            case "AlarmShuffle4":
            case "AlarmShuffle5":
            case "AlarmShuffle6":
            case "AlarmShuffle7":
            case "AlarmShuffle8":
            case "AlarmShuffle9":
                $Index = (int) substr($Ident, -1);
                $result = $this->SetShuffle($Index, (int) $Value);
                break;
            case "AlarmRepeat0":
            case "AlarmRepeat1":
            case "AlarmRepeat2":
            case "AlarmRepeat3":
            case "AlarmRepeat4":
            case "AlarmRepeat5":
            case "AlarmRepeat6":
            case "AlarmRepeat7":
            case "AlarmRepeat8":
            case "AlarmRepeat9":
                $Index = (int) substr($Ident, -1);
                $result = $this->SetRepeat($Index, (bool) $Value);
                break;
            case "AlarmVolume0":
            case "AlarmVolume1":
            case "AlarmVolume2":
            case "AlarmVolume3":
            case "AlarmVolume4":
            case "AlarmVolume5":
            case "AlarmVolume6":
            case "AlarmVolume7":
            case "AlarmVolume8":
            case "AlarmVolume9":
                $Index = (int) substr($Ident, -1);
                $result = $this->SetVolume($Index, (int) $Value);
                break;
            case "AlarmState0":
            case "AlarmState1":
            case "AlarmState2":
            case "AlarmState3":
            case "AlarmState4":
            case "AlarmState5":
            case "AlarmState6":
            case "AlarmState7":
            case "AlarmState8":
            case "AlarmState9":
                $Index = (int) substr($Ident, -1);
                if ($Value == 0) {
                    $result = $this->AlarmStop();
                } elseif ($Value == 1) {
                    $result = $this->AlarmSnooze();
                } else {
                    $result = true;
                }
                break;
            default:
                trigger_error($this->Translate("Invalid ident"), E_USER_NOTICE);
                return;
        }
        if ($result == false) {
            trigger_error($this->Translate("Error on Execute Action"), E_USER_NOTICE);
        }
    }

    ################## Decode Data
    private function DecodeLMSResponse(LMSData $LMSData)
    {
        if ($LMSData == null) {
            return false;
        }
        if ($LMSData->Data[0] == '?') {
            return false;
        }
        switch ($LMSData->Command[0]) {
            case 'alarm':
                switch ($LMSData->Command[1]) {
                    case 'enableall':
                        $this->SetValueBoolean('EnableAll', true);
                        break;
                    case 'disableall':
                        $this->SetValueBoolean('EnableAll', false);
                        break;
                    case 'defaultvolume':
                        $Data = new LMSTaggingArray($LMSData->Data);
                        $this->SetValueInteger('DefaultVolume', $Data->DataArray()['Volume']);
                        break;
                    case 'add':
                        if (count($this->Alarms->Items) > 9) {
                            break;
                        }
                        $Data = (new LMSTaggingArray($LMSData->Data, ''))->DataArray();
                        $this->SendDebug('ADD', $Data, 0);
                        $Alarms = $this->Alarms;
                        $Alarm = new LSA_Alarm($Data);
                        $Alarm->Volume = GetValueInteger($this->GetIDForIdent('DefaultVolume'));
                        $Alarm->Index = $Alarms->Add($Alarm);
                        $this->Alarms = $Alarms;
                        $this->RefreshDeleteProfil(count($Alarms->Items));
                        $this->RefreshEvent($Alarm);
                        break;
                    case 'delete':
                        $Data = (new LMSTaggingArray($LMSData->Data, ''))->DataArray();
                        $this->SendDebug('DELETE', $Data, 0);
                        $Alarms = $this->Alarms;
                        $Alarms->Remove($Data['Id']);
                        $this->Alarms = $Alarms;
                        $AlarmIndex = count($Alarms->Items);
                        $this->RefreshDeleteProfil($AlarmIndex);
                        $delete = $this->ReadPropertyBoolean('dynamicDisplay');
                        $eid = @$this->GetIDForIdent('AlarmTime' . $AlarmIndex);
                        if ($eid > 0) {
                            $this->UnregisterMessage($eid, EM_CHANGEACTIVE);
                            $this->UnregisterMessage($eid, EM_CHANGECYCLIC);
                            $this->UnregisterMessage($eid, EM_CHANGECYCLICTIMEFROM);
                            if ($delete) {
                                IPS_DeleteEvent($eid);
                            } else {
                                IPS_SetEventCyclic($eid, 3, 1, 0, 0, 0, 0);
                                IPS_SetEventCyclicTimeFrom($eid, 0, 0, 0);
                                IPS_SetEventActive($eid, false);
                            }
                        }

                        $vid = @$this->GetIDForIdent('AlarmPlaylistName' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueString($vid, '');
                            }
                        }

                        $vid = @$this->GetIDForIdent('AlarmShuffle' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueInteger($vid, 0);
                            }
                        }

                        $vid = @$this->GetIDForIdent('AlarmRepeat' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueBoolean($vid, false);
                            }
                        }


                        $vid = @$this->GetIDForIdent('AlarmVolume' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueInteger($vid, 0);
                            }
                        }

                        $vid = @$this->GetIDForIdent('AlarmState' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($delete) {
                                IPS_DeleteVariable($vid);
                            } else {
                                $this->SetValueInteger($vid, 0);
                            }
                        }

                        $vid = @$this->GetIDForIdent('AlarmPlaylist' . $AlarmIndex);
                        if ($vid > 0) {
                            if ($this->ReadPropertyBoolean('showAlarmPlaylist')) {
                                if ($delete) {
                                    IPS_DeleteVariable($vid);
                                } else {
                                    $this->SetValueString($vid, '');
                                }
                            } else {
                                IPS_DeleteVariable($vid);
                            }
                        }


                        $this->RefreshEvents($Alarms);
                        break;
                    case 'update':
                        $Data = (new LMSTaggingArray($LMSData->Data, ''))->DataArray();
                        $this->SendDebug('UPDATE', $Data, 0);
                        $Alarms = $this->Alarms;
                        $Alarm = $Alarms->GetById($Data['Id']);
                        if ($Alarm === false) {
                            break;
                        }
                        if (array_key_exists('DowAdd', $Data)) {
                            $Alarm->AddDow($Data['DowAdd']);
                        }
                        if (array_key_exists('DowDel', $Data)) {
                            $Alarm->DelDow($Data['DowDel']);
                        }
                        if (array_key_exists('Dow', $Data)) {
                            $Alarm->Dow = $Data['Dow'];
                        }

                        if (array_key_exists('Time', $Data)) {
                            $Alarm->Time = $Data['Time'];
                        }
                        if (array_key_exists('Enabled', $Data)) {
                            $Alarm->Enabled = $Data['Enabled'];
                        }
                        if (array_key_exists('Repeat', $Data)) {
                            $Alarm->Repeat = $Data['Repeat'];
                        }
                        if (array_key_exists('Playlisturl', $Data)) {
                            if ($Data['Playlisturl'] == '0') {
                                $Data['Playlisturl'] = 'CURRENT_PLAYLIST';
                            }
                            $Alarm->Url = $Data['Playlisturl'];
                        }
                        if (array_key_exists('Shufflemode', $Data)) {
                            $Alarm->Shufflemode = $Data['Shufflemode'];
                        }
                        $this->Alarms = $Alarms;
                        $this->RefreshEvent($Alarm);
                        break;
                    case 'sound':
                    case 'snooze_end':
                        $State = 2;
                    case 'end':
                        if (!isset($State)) {
                            $State = 0;
                        }
                    case 'snooze':
                        if (!isset($State)) {
                            $State = 1;
                        }
                        $Alarms = $this->Alarms;
                        $Alarm = $Alarms->GetById($LMSData->Data[0]);
                        if ($Alarm === false) {
                            break;
                        }
                        $vid = @$this->GetIDForIdent('AlarmState' . $Alarm->Index);
                        if ($vid === false) {
                            $this->RegisterVariableInteger('AlarmState' . $Alarm->Index, sprintf($this->Translate('Alarm %d state'), $Alarm->Index + 1), "LSA.State", (($Alarm->Index + 1) * 10) + 2);
                            $this->EnableAction('AlarmState' . $Alarm->Index);
                        }

                        $this->SetValueInteger('AlarmState' . $Alarm->Index, $State);
                        break;
                }
                break;
            case 'prefset':
                $LMSData->Command[1] = $LMSData->Command[2];
                unset($LMSData->Command[2]);
            case 'playerpref':
                switch ($LMSData->Command[1]) {
                    case 'alarmfadeseconds':
                        $this->SetValueBoolean('FadeIn', (bool) $LMSData->Data[0]);
                        break;
                    case 'alarmDefaultVolume':
                        $this->SetValueInteger('DefaultVolume', (int) $LMSData->Data[0]);
                        break;
                    case 'alarmTimeoutSeconds':
                        $this->SetValueInteger('Timeout', (int) $LMSData->Data[0]);
                        break;
                    case 'alarmSnoozeSeconds':
                        $this->SetValueInteger('SnoozeSeconds', (int) $LMSData->Data[0]);
                        break;
                    case 'alarmsEnabled':
                        $this->SetValueBoolean('EnableAll', (bool) $LMSData->Data[0]);
                        break;
                }
                break;
            case 'alarms':
                $this->SendDebug('Decode', $LMSData, 0);
                $ReadAlarmData = (new LMSTaggingArray($LMSData->Data))->DataArray();
                if (array_key_exists('Count', $ReadAlarmData)) {
                    $this->SendDebug('AlarmData', 'EMPTY', 0);
                    $this->Alarms = new LSA_AlarmList(array());
                } else {
                    $this->SendDebug('AlarmData', $ReadAlarmData, 0);
                    $this->Alarms = new LSA_AlarmList($ReadAlarmData);
                }
                $this->RefreshDeleteProfil(count($this->Alarms->Items));
                $this->RefreshEvents($this->Alarms);
                break;
            case 'client':
                if (($LMSData->Data[0] == 'new') or ( $LMSData->Data[0] == 'reconnect')) {
                    $this->RequestAllState();
                }
                break;
            default:
                return false;
        }
        return true;
    }

    ################## DataPoints Ankommend von Parent-LMS-Splitter
    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ReceiveData($JSONString)
    {
        $this->SendDebug('Receive Event', $JSONString, 0);
        $Data = json_decode($JSONString);
        $LMSData = new LMSData();
        $LMSData->CreateFromGenericObject($Data);
        $this->SendDebug('Receive Event ', $LMSData, 0);
        if (in_array($LMSData->Command[0], array('alarm', 'alarms', 'playerpref', 'prefset', 'client'))) {
            $this->DecodeLMSResponse($LMSData);
        } else {
            $this->SendDebug('UNKNOW', $LMSData, 0);
        }
    }

    ################## Datenaustausch
    /**
     * Konvertiert $Data zu einem JSONString und versendet diese an den Splitter.
     *
     * @access protected
     * @param LMSData $LMSData Zu versendende Daten.
     * @return LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    private function Send(LMSData $LMSData)
    {
        try {
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }
            $LMSData->Address = $this->ReadPropertyString('Address');
            $this->SendDebug('Send', $LMSData, 0);

            $anwser = $this->SendDataToParent($LMSData->ToJSONString("{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}"));
            if ($anwser === false) {
                $this->SendDebug('Response', 'No valid answer', 0);
                return null;
            }
            $result = unserialize($anwser);
            if ($LMSData->needResponse === false) {
                return $result;
            }
            $LMSData->Data = $result->Data;
            $this->SendDebug('Response', $LMSData, 0);
            return $LMSData;
        } catch (Exception $exc) {
            trigger_error($exc->getMessage() . PHP_EOL . print_r(debug_backtrace(), true), E_USER_NOTICE);
            return null;
        }
    }

    /**
     * Konvertiert $Data zu einem String und versendet diesen direkt an den LMS.
     *
     * @access protected
     * @param LMSData $LMSData Zu versendende Daten.
     * @return LMSData Objekt mit der Antwort. NULL im Fehlerfall.
     */
    protected function SendDirect(LMSData $LMSData)
    {
        try {
            if (!$this->HasActiveParent()) {
                throw new Exception($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
            }

            $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
            $IoID = IPS_GetInstance($SplitterID)['ConnectionID'];
            $Host = IPS_GetProperty($IoID, "Host");
            if ($Host === "") {
                return null;
            }

            $LMSData->Address = $this->ReadPropertyString('Address');
            $this->SendDebug('Send Direct', $LMSData, 0);

            if (!$this->Socket) {
                $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
                $IoID = IPS_GetInstance($SplitterID)['ConnectionID'];
                $Host = IPS_GetProperty($IoID, "Host");
                if ($Host === "") {
                    return null;
                }
                $Host = gethostbyname($Host);

                $Port = IPS_GetProperty($SplitterID, 'Port');
                $User = IPS_GetProperty($SplitterID, 'User');
                $Pass = IPS_GetProperty($SplitterID, 'Password');

                $LoginData = (new LMSData('login', array($User, $Pass)))->ToRawStringForLMS();
                $this->SendDebug('Send Direct', $LoginData, 0);
                $this->Socket = @stream_socket_client("tcp://" . $Host . ":" . $Port, $errno, $errstr, 2);
                if (!$this->Socket) {
                    throw new Exception($this->Translate('No anwser from LMS'), E_USER_NOTICE);
                }
                stream_set_timeout($this->Socket, 5);
                fwrite($this->Socket, $LoginData);
                $anwserlogin = stream_get_line($this->Socket, 1024 * 1024 * 2, chr(0x0d));
                $this->SendDebug('Response Direct', $anwserlogin, 0);
                if ($anwserlogin === false) {
                    throw new Exception($this->Translate('No anwser from LMS'), E_USER_NOTICE);
                }
            }

            $Data = $LMSData->ToRawStringForLMS();
            $this->SendDebug('Send Direct', $Data, 0);
            fwrite($this->Socket, $Data);
            $anwser = stream_get_line($this->Socket, 1024 * 1024 * 2, chr(0x0d));
            $this->SendDebug('Response Direct', $anwser, 0);
            if ($anwser === false) {
                throw new Exception($this->Translate('No anwser from LMS'), E_USER_NOTICE);
            }

            $ReplyData = new LMSResponse($anwser);
            $LMSData->Data = $ReplyData->Data;
            $this->SendDebug('Response Direct', $LMSData, 0);
            return $LMSData;
        } catch (Exception $ex) {
            $this->SendDebug("Receive Direct", $ex->getMessage(), 0);
            trigger_error($ex->getMessage(), $ex->getCode());
        }
        return null;
    }

}

/** @} */
