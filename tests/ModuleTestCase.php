<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * Basis für Tests, welche Instanzen im Stub-Kernel erzeugen.
 */
abstract class ModuleTestCase extends TestCase
{
    public const SplitterID = '{96A9AB3A-2538-42C5-A130-FC34205A706A}';
    public const ConfiguratorID = '{35028918-3F9C-4524-9FB4-DBAF429C6E18}';
    public const DiscoveryID = '{28AC8A6C-4E03-43BE-9C3E-B8FEF78D374C}';
    public const DeviceID = '{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}';
    public const AlarmID = '{E7423083-3502-42C8-B244-2852D0BE41D4}';
    public const BatteryID = '{718158BB-B247-4A71-9440-9C2FF1378752}';
    public const SendToChild = '{CB5950B3-593C-4126-9F0F-8655A3944419}';

    /** Testadresse eines Players (anonymisiert). */
    public const PlayerAddress = '00:04:20:00:00:01';

    /** @var string[] Während des Tests aufgelaufene Meldungen per trigger_error. */
    protected array $Errors = [];

    /** @var string[] Während des Tests aufgelaufene PHP-Fehler (Warnings, Notices, Deprecations). */
    protected array $PhpErrors = [];

    /** @var string Ausgaben (echo) der Instanz beim letzten call(). */
    protected string $Output = '';

    public function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/IOStubs/library.json');
        IPS\ActionPool::loadActions(__DIR__ . '/../actions');
        foreach (['~PlaybackPreviousNext' => VARIABLETYPE_INTEGER, '~Repeat' => VARIABLETYPE_INTEGER, '~Song' => VARIABLETYPE_STRING, '~Artist' => VARIABLETYPE_STRING, '~Playlist' => VARIABLETYPE_STRING, '~Progress' => VARIABLETYPE_FLOAT] as $Profile => $Type) {
            if (!IPS_VariableProfileExists($Profile)) {
                IPS_CreateVariableProfile($Profile, $Type);
            }
        }
        $this->Errors = [];
        $this->PhpErrors = [];
        // Die php.ini des Entwicklungsrechners blendet E_USER_NOTICE aus.
        error_reporting(E_ALL);
        set_error_handler(function (int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
        {
            // Mit @ unterdrückte Meldungen ignorieren
            if (!(error_reporting() & $errno)) {
                return true;
            }
            if ($errno & (E_USER_NOTICE | E_USER_WARNING | E_USER_ERROR)) {
                $this->Errors[] = $errstr;
            } else {
                $this->PhpErrors[] = $errstr . ' (' . basename(dirname($errfile)) . '/' . basename($errfile) . ':' . $errline . ')';
            }
            return true;
        });
        parent::setUp();
    }

    public function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    /**
     * Erzeugt eine Instanz und setzt die übergebenen Eigenschaften.
     *
     * @param string $ModuleID GUID des Moduls.
     * @param array  $Config   Eigenschaften.
     * @return int Die InstanzID.
     */
    protected function createInstance(string $ModuleID, array $Config = []): int
    {
        $iid = IPS_CreateInstance($ModuleID);
        if (count($Config)) {
            IPS_SetConfiguration($iid, json_encode($Config));
            IPS_ApplyChanges($iid);
        }
        return $iid;
    }

    /**
     * Übergibt eine LMS-Antwortzeile (wie vom Splitter zerlegt) an eine Instanz.
     *
     * @param int    $InstanceID Ziel.
     * @param string $RawLine    Eine Zeile so wie sie vom LMS kommt (ohne Zeilenende).
     */
    protected function receive(int $InstanceID, string $RawLine): void
    {
        $Response = new \SqueezeBox\LMSResponse($RawLine);
        $this->callReceiveData($InstanceID, $Response->ToJSONStringForDevice());
    }

    /**
     * Übergibt eine Antwort so, wie sie SendDirect nach SliceData() an DecodeLMSResponse weiterreicht:
     * Der Befehl stammt aus der Anfrage, die Daten aus der Antwort (ohne Echo der Parameter).
     *
     * @param int    $InstanceID Ziel.
     * @param array  $Command    Befehl der Anfrage.
     * @param string $RawLine    Antwortzeile ohne Echo der Parameter.
     */
    protected function receiveAnswer(int $InstanceID, array $Command, string $RawLine): void
    {
        $Response = new \SqueezeBox\LMSResponse($RawLine);
        $this->callReceiveData($InstanceID, json_encode([
            'DataID'  => self::SendToChild,
            'Address' => $Response->Address,
            'Command' => $Command,
            'Data'    => $Response->Data
        ]));
    }

    /**
     * Ruft ReceiveData der Instanz direkt auf (ohne Datenfluss-Filter).
     */
    protected function callReceiveData(int $InstanceID, string $JSONString): void
    {
        $this->call($InstanceID, 'ReceiveData', $JSONString);
    }

    /**
     * Ruft eine öffentliche Methode der Instanz auf und schneidet Ausgaben mit.
     *
     * @return mixed Rückgabewert der Methode.
     */
    protected function call(int $InstanceID, string $Method, mixed ...$Params): mixed
    {
        ob_start();
        try {
            return IPS\InstanceManager::getInstanceInterface($InstanceID)->$Method(...$Params);
        } finally {
            $this->Output = (string) ob_get_clean();
        }
    }

    /**
     * Prüft, dass keine PHP-Fehler (keine Modulmeldungen) aufgetreten sind.
     */
    protected function assertNoPhpErrors(): void
    {
        $this->assertSame([], $this->PhpErrors, 'PHP-Fehler aufgetreten');
    }

    /**
     * Ident → Wert aller Statusvariablen einer Instanz.
     */
    protected function values(int $InstanceID): array
    {
        $Result = [];
        foreach (IPS_GetChildrenIDs($InstanceID) as $ChildID) {
            $Object = IPS_GetObject($ChildID);
            if ($Object['ObjectType'] == OBJECTTYPE_VARIABLE) {
                $Result[$Object['ObjectIdent']] = GetValue($ChildID);
            }
        }
        return $Result;
    }
}
