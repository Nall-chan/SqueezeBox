<?php

declare(strict_types=1);

/**
 * @package       Squeezebox
 * @file          SqueezeBoxAlarmClass.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.10
 *
 */

/**
 * Enthält die Daten eines Alarm.
 */
class LSA_Alarm
{
    /**
     * Id des Alarm.
     *
     * @var int|string
     */
    public int|string $Id;

    /**
     * Index des Alarm.
     *
     * @var ?int
     */
    public ?int $Index;

    /**
     * Tage des Alarm.
     *
     * @var string
     */
    public string $Dow = '0,1,2,3,4,5,6';

    /**
     * Status des Alarm.
     *
     * @var bool
     */
    public bool $Enabled = true;

    /**
     * Wiederholung des Alarm.
     *
     * @var bool
     */
    public bool $Repeat = true;

    /**
     * Shuffle des Alarm.
     *
     * @var int
     */
    public int $Shufflemode = 0;

    /**
     * Uhrzeit des Alarm.
     *
     * @var int
     */
    public int $Time;

    /**
     * Lautstärke des Alarm.
     *
     * @var int
     */
    public int $Volume;

    /**
     * Playlist des Alarm.
     *
     * @var string
     */
    public string $Url = 'CURRENT_PLAYLIST';

    /**
     * __construct
     * Erzeugt aus den übergeben Daten einen neuen LSA_Alarm.
     *
     * @param array $Alarm Das Array wie es von LMSTaggingArray erzeugt wird.
     * @param ?int   $Index Der fortlaufende Index dieses Weckers.
     * @return LSA_Alarm
     */
    public function __construct(array $Alarm, ?int $Index = null)
    {
        foreach ($Alarm as $Key => $Value) {
            $this->{$Key} = $Value;
        }
        $this->Index = $Index;
    }

    /**
     * __sleep
     * Liefert die Daten welche behalten werden müssen.
     *
     * @return array
     */
    public function __sleep(): array
    {
        return ['Id', 'Index', 'Dow', 'Enabled', 'Repeat', 'Time', 'Volume', 'Url', 'Shufflemode'];
    }

    /**
     * IpsToDow
     * Schreibt die Wochentag aus dem IPS Format in die Eigenschaft Dow.
     *
     * @param int $Days Die Wochentag im IPS-Format.
     */
    public function IpsToDow(int $Days): void
    {
        // IPS: Bit 0 = Montag ... Bit 6 = Sonntag; LMS: 0 = Sonntag, 1 = Montag ... 6 = Samstag
        $Dow = [];
        for ($i = 0; $i < 7; $i++) {
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
     * DowToIps
     * Liefert die Eigenschaft Dow im IPS-Format.
     *
     * @return int Die Wochentage im IPS-Format.
     */
    public function DowToIps(): int
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
     * AddDow
     * Fügt einen Wochentag zum Wecker hinzu.
     *
     * @param int $Dow Ein Wochentag.
     */
    public function AddDow(int $Dow): void
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
     * DelDow
     * Löscht einen Wochentag aus dem Wecker.
     *
     * @param int $Dow Ein Wochentag.
     */
    public function DelDow(int $Dow): void
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
     * TimeToArray
     * Liefert die Alarmzeit als Array für die Ereignisse in IPS.
     *
     * @return array
     */
    public function TimeToArray(): array
    {
        $result['Second'] = (int) gmdate('s', $this->Time);
        $result['Minute'] = (int) gmdate('i', $this->Time);
        $result['Hour'] = (int) gmdate('G', $this->Time);
        return $result;
    }

    /**
     * ArrayToTime
     * Schreibt die Alarmzeit aus dem übergeben Array in die Eigenschaft Time.
     *
     * @param array $Time
     */
    public function ArrayToTime(array $Time): void
    {
        $this->Time = ((($Time[0] * 60) + $Time[1]) * 60) + $Time[2];
    }
}

/**
 * LSA_AlarmList ist eine Klasse welche ein Array von LSA_Alarm enthält.
 */
class LSA_AlarmList
{
    /**
     * Array mit allen Items.
     *
     * @var LSA_Alarm[]
     */
    public array $Items = [];

    /**
     * __construct
     * Erzeugt eine neue LSA_AlarmList aus einem Array von LMSTaggingArray mit allen Alarmen.
     *
     * @param array $Alarms Alle Wecker oder null.
     * @return LSA_AlarmList
     */
    public function __construct(array $Alarms)
    {
        foreach ($Alarms as $Index => $AlarmData) {
            $Alarm = new LSA_Alarm($AlarmData, $Index);
            $this->Items[$Alarm->Id] = $Alarm;
        }
    }

    /**
     * __sleep
     * Liefert die Daten welche behalten werden müssen.
     *
     * @return array
     */
    public function __sleep(): array
    {
        return ['Items'];
    }

    /**
     * Add
     * Fügt einen LSA_Alarm in $Items hinzu.
     *
     * @param LSA_Alarm $Alarm Das neue Objekt.
     * @return int Der Index des Items.
     */
    public function Add(LSA_Alarm $Alarm): int
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
     * Update
     * Update für einen LSA_Alarm in $Items.
     *
     * @param LSA_Alarm $Alarm Das neue Objekt.
     * @return int
     */
    public function Update(LSA_Alarm $Alarm): int
    {
        $this->Items[$Alarm->Id] = $Alarm;
        return $this->Items[$Alarm->Id]->Index;
    }

    /**
     * Remove
     * Löscht einen LSA_Alarm aus $Items.
     *
     * @param string $AlarmId Der Index des zu löschenden Items.
     */
    public function Remove(string $AlarmId): void
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
     * GetById
     * Liefert einen bestimmten LSA_Alarm aus den Items anhand der Id.
     *
     * @param string $AlarmId
     * @return LSA_Alarm $Alarm
     */
    public function GetById(string $AlarmId): bool|LSA_Alarm
    {
        if (!isset($this->Items[$AlarmId])) {
            return false;
        }
        return $this->Items[$AlarmId];
    }

    /**
     * GetByIndex
     * Liefert einen bestimmten LSA_Alarm aus den Items anhand des Index.
     *
     * @param int $Index
     * @return LSA_Alarm $Alarm
     */
    public function GetByIndex(int $Index): bool|LSA_Alarm
    {
        foreach ($this->Items as $Alarm) {
            if ($Alarm->Index == $Index) {
                return $Alarm;
            }
        }
        return false;
    }
}
