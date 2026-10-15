<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../libs/SqueezeBoxClass.php';
require_once __DIR__ . '/../libs/SqueezeBoxAlarmClass.php';

/**
 * Testklasse für die Traits ConvertSeconds und GetTable.
 */
class HTMLTableTester
{
    use \SqueezeBox\LMSHTMLTable;

    public int $InstanceID = 12345;
    public array $Secrets = [];
    private array $Properties = [];

    public function __construct(array $Columns, array $Rows)
    {
        $this->Properties = [
            'Table'   => json_encode([['tag' => '<table>', 'style' => ''], ['tag' => '<thead>', 'style' => ''], ['tag' => '<tbody>', 'style' => '']]),
            'Columns' => json_encode($Columns),
            'Rows'    => json_encode($Rows)
        ];
    }

    public function __set(string $Name, mixed $Value): void
    {
        $this->Secrets[$Name] = $Value;
    }

    public function Seconds(int $Time): string
    {
        return $this->ConvertSeconds($Time);
    }

    public function Table(array $Data, string $HookId, int $CurrentLine = -1): string
    {
        return $this->GetTable($Data, 'Hook', 'Type', $HookId, $CurrentLine);
    }

    protected function ReadPropertyString(string $Name): string
    {
        return $this->Properties[$Name];
    }
}

/**
 * Tests der Protokoll- und Hilfsklassen aus /libs mit Antworten eines echten LMS.
 */
class LibraryClassesTest extends TestCase
{
    private static array $Responses;

    public static function setUpBeforeClass(): void
    {
        self::$Responses = require __DIR__ . '/fixtures/LMSResponses.php';
    }

    public function testResponseFromPlayer(): void
    {
        $Response = new \SqueezeBox\LMSResponse(self::$Responses['mixerVolume']);
        $this->assertSame(\SqueezeBox\DeviceType::isMAC, $Response->Device);
        $this->assertSame('00:04:20:00:00:01', $Response->Address);
        $this->assertSame(['mixer', 'volume'], $Response->Command);
        $this->assertSame(['52'], $Response->Data);
    }

    public function testResponseFromServer(): void
    {
        $Response = new \SqueezeBox\LMSResponse(self::$Responses['syncgroups']);
        $this->assertSame(\SqueezeBox\DeviceType::isServer, $Response->Device);
        $this->assertSame(['syncgroups'], $Response->Command);
        $this->assertSame('sync_members:00:04:20:00:00:01,00:04:20:00:00:03', $Response->Data[0]);
    }

    public function testUnknownQueryIsEchoed(): void
    {
        // LMS kennt "mixer power" nicht und liefert das Fragezeichen zurück (B2).
        $Response = new \SqueezeBox\LMSResponse(self::$Responses['mixerPower']);
        $this->assertSame(['?'], $Response->Data);
    }

    /** B29 */
    public function testShoutcastWithoutSubCommand(): void
    {
        $Response = new \SqueezeBox\LMSResponse('00%3A04%3A20%3A00%3A00%3A01 shoutcast items');
        $this->assertSame(['shoutcast', 'items'], $Response->Command);
    }

    /** B21 */
    public function testTaggingDataWithoutColon(): void
    {
        $Data = new \SqueezeBox\LMSTaggingData('');
        $this->assertSame('', $Data->Name);
        $this->assertSame('', $Data->Value);
        $Data = new \SqueezeBox\LMSTaggingData('url:http://host/a');
        $this->assertSame('url', $Data->Name);
        $this->assertSame('http://host/a', $Data->Value);
    }

    public function testTaggingArrayPlayers(): void
    {
        $Response = new \SqueezeBox\LMSResponse(self::$Responses['players']);
        $Data = new \SqueezeBox\LMSData('players', ['0', '10']);
        $Data->ToRawStringForLMS();
        $Data->Data = $Response->Data;
        $Data->SliceData();
        $Players = (new \SqueezeBox\LMSTaggingArray($Data->Data, 'playerindex'))->DataArray();
        $this->assertCount(2, $Players);
        $this->assertSame('00:04:20:00:00:01', $Players[0]['Playerid']);
        $this->assertSame('baby', $Players[0]['Model']);
        $this->assertSame('Büro', $Players[1]['Name']);
    }

    public function testTaggingArrayGenres(): void
    {
        $Response = new \SqueezeBox\LMSResponse(self::$Responses['genres']);
        $Genres = new \SqueezeBox\LMSTaggingArray(array_slice($Response->Data, 2));
        $Genres->Compact('Genre');
        $this->assertSame([516 => '4294967295)', 559 => 'A capella', 563 => 'Alternative'], $Genres->DataArray());
    }

    /** B38: Ids mit Ziffern oder Exponent-Schreibweise dürfen nicht verfälscht werden. */
    public function testTaggingArrayKeepsNonCanonicalNumbers(): void
    {
        $Data = (new \SqueezeBox\LMSTaggingArray(['id:1e512345', 'volume:20'], ''))->DataArray();
        $this->assertSame('1e512345', $Data['Id']);
        $Data = (new \SqueezeBox\LMSTaggingArray(['id:01234567'], ''))->DataArray();
        $this->assertSame('01234567', $Data['Id']);
        $Data = (new \SqueezeBox\LMSTaggingArray(['id:12345678'], ''))->DataArray();
        $this->assertSame(12345678, $Data['Id']);
    }

    /** B39: Eine Liste ohne Treffer liefert nur 'count:0'. */
    public function testTaggingArrayWithoutResults(): void
    {
        $Response = new \SqueezeBox\LMSResponse(self::$Responses['artistsNone']);
        $Data = new \SqueezeBox\LMSData('artists', [0, 100000, 'search:teufels kicker']);
        $Data->ToRawStringForLMS();
        $Data->Data = $Response->Data;
        $Data->SliceData();
        $this->assertSame([], (new \SqueezeBox\LMSTaggingArray($Data->Data))->DataArray());
        $Artists = new \SqueezeBox\LMSTaggingArray($Data->Data);
        $Artists->Compact('Artist');
        $this->assertSame([], $Artists->DataArray());
        // Einzelne Datensätze ohne Id bleiben erhalten.
        $this->assertSame(['Count' => 0, 'Volume' => 20], (new \SqueezeBox\LMSTaggingArray(['count:0', 'volume:20']))->DataArray());
        // Antwort von playlistcontrol: nur die Anzahl der geladenen Titel.
        $this->assertSame(['Count' => 56], (new \SqueezeBox\LMSTaggingArray(['count:56']))->DataArray());
    }

    /** B40: Bei Dateien antwortet der LMS auf playlist play/add/deleteitem mit dem lokalen Pfad statt der URL. */
    public function testIsSameSongURL(): void
    {
        $Tester = new class() {
            use \SqueezeBox\LMSSongURL;

            public static function Test(string $URL, string $Echo): bool
            {
                return self::IsSameSongURL($URL, $Echo);
            }
        };
        // Mitschnitt: URL aus LMS_GetSongsByArtist, Antwort auf 'playlist add'.
        $URL = 'file:///E:/ServerFolders/Musik/Die%20Teufelskicker/Die%20Teufels%20Kicker%20Folge%2004-%20St%FCrmer%20gesucht!/01%20Alles%20Wegen%20Olli.mp3';
        $Response = new \SqueezeBox\LMSResponse('00%3A04%3A20%3A00%3A00%3A01 playlist add E%3A%5CServerFolders%5CMusik%5CDie%20Teufelskicker%5CDie%20Teufels%20Kicker%20Folge%2004-%20St%C3%BCrmer%20gesucht!%5C01%20Alles%20Wegen%20Olli.mp3 ');
        $this->assertTrue($Tester::Test($URL, $Response->Data[0]));
        $this->assertTrue($Tester::Test('file:///E:/Musik/St%C3%BCrmer.mp3', 'E:\Musik\Stürmer.mp3'));
        $this->assertTrue($Tester::Test('file:///music/A%20B.mp3', '/music/A B.mp3'));
        $this->assertTrue($Tester::Test('https://cast.example/stream', 'https://cast.example/stream'));
        $this->assertFalse($Tester::Test($URL, 'E:\Musik\Anderer Titel.mp3'));
        $this->assertFalse($Tester::Test($URL, ''));
    }

    /** B40: Antworten werden genau einmal dekodiert; URLs behalten ihre eigene Kodierung. */
    public function testTaggingArrayDecodesOnce(): void
    {
        $Response = new \SqueezeBox\LMSResponse(self::$Responses['mediafolder']);
        $Folders = (new \SqueezeBox\LMSTaggingArray(array_slice($Response->Data, 3)))->DataArray();
        $this->assertSame('Bartimäus', $Folders[241888]['Filename']);
        $this->assertSame('file:///E:/ServerFolders/Musik/Bartim%E4us', $Folders[241888]['Url']);
        $this->assertSame('file:///E:/ServerFolders/Musik/Dietmar%20Simon', $Folders[241889]['Url']);
        $this->assertNotFalse(json_encode($Folders));

        $Response = new \SqueezeBox\LMSResponse(self::$Responses['songinfo']);
        $Song = (new \SqueezeBox\LMSSongInfo(array_slice($Response->Data, 3)))->GetSong();
        $this->assertSame('file:///E:/Musik/01%20Fu%DFballerpech.mp3', $Song['Url']);
        $this->assertSame('Fußballerpech', $Song['Title']);
        $this->assertNotFalse(json_encode($Song));
    }

    public function testSongInfoPlaylist(): void
    {
        $Response = new \SqueezeBox\LMSResponse(self::$Responses['playlistTracks']);
        $SongInfo = new \SqueezeBox\LMSSongInfo(array_slice($Response->Data, 5));
        $this->assertSame(3, $SongInfo->CountAllSongs());
        $this->assertSame(62 + 89 + 159, $SongInfo->GetTotalDuration());
        $this->assertSame('PKK', $SongInfo->GetAllSongs()[1]['Title']);
    }

    public function testSongInfoStatus(): void
    {
        $Response = new \SqueezeBox\LMSResponse(self::$Responses['status']);
        $SongInfo = new \SqueezeBox\LMSSongInfo(array_slice($Response->Data, 3));
        $Song = $SongInfo->GetSong();
        $this->assertSame('Realize', $Song['Title']);
        $this->assertSame(245, $Song['Duration']);
    }

    /** B19 */
    public function testSongInfoEmpty(): void
    {
        $SongInfo = new \SqueezeBox\LMSSongInfo([]);
        $this->assertSame(0, $SongInfo->CountAllSongs());
        $this->assertSame([], $SongInfo->GetSong());
        $SongInfo = new \SqueezeBox\LMSSongInfo(['']);
        $this->assertSame([], $SongInfo->GetSong());
    }

    /** B25 */
    public function testConvertSeconds(): void
    {
        $Tester = new HTMLTableTester([], []);
        $this->assertSame('00:00', $Tester->Seconds(0));
        $this->assertSame('00:59', $Tester->Seconds(59));
        $this->assertSame('59:59', $Tester->Seconds(3599));
        $this->assertSame('01:00:00', $Tester->Seconds(3600));
        $this->assertSame('01:01:01', $Tester->Seconds(3661));
    }

    /** B20 */
    public function testHTMLTable(): void
    {
        $Columns = [
            ['index' => 0, 'key' => 'Play', 'name' => '', 'show' => true, 'width' => 50, 'color' => -1, 'align' => 'center', 'style' => ''],
            ['index' => 1, 'key' => 'Title', 'name' => 'Title', 'show' => true, 'width' => 250, 'color' => -1, 'align' => 'center', 'style' => '']
        ];
        $Rows = [
            ['row' => 'odd', 'name' => 'odd', 'bgcolor' => -1, 'color' => -1, 'style' => 'ODDSTYLE'],
            ['row' => 'even', 'name' => 'even', 'bgcolor' => -1, 'color' => -1, 'style' => 'EVENSTYLE'],
            ['row' => 'active', 'name' => 'active', 'bgcolor' => -1, 'color' => -1, 'style' => 'ACTIVESTYLE']
        ];
        $Tester = new HTMLTableTester($Columns, $Rows);
        $HTML = $Tester->Table([
            ['Title' => '<b>A & B\'s</b>', 'Url' => 'file:///E:/Musik/A%20B.mp3'],
            ['Title' => 'Zweiter', 'Url' => 'CURRENT_PLAYLIST']
        ], 'Url', 2);
        // eine Kopfzeile und zwei Datenzeilen
        $this->assertSame(3, substr_count($HTML, '</tr>'));
        $this->assertStringContainsString('&lt;b&gt;A &amp; B&#039;s&lt;/b&gt;', $HTML);
        $this->assertStringNotContainsString('<b>A', $HTML);
        // Zeilenstil gehört in das tr-Element
        $this->assertMatchesRegularExpression('/<tr style="[^"]*EVENSTYLE[^"]*"/', $HTML);
        $this->assertMatchesRegularExpression('/<tr style="[^"]*ACTIVESTYLE[^"]*"/', $HTML);
        // ID wird einmal kodiert übertragen
        $this->assertStringContainsString('ID=' . rawurlencode('file:///E:/Musik/A%20B.mp3') . '&', $HTML);
        // Secret passt zur unveränderten ID
        $Secret = $Tester->Secrets['WebHookSecretType'];
        $LineSecret = rawurlencode(base64_encode(sha1($Secret . '0' . 'file:///E:/Musik/A%20B.mp3', true)));
        $this->assertStringContainsString('Secret=' . $LineSecret, $HTML);
    }

    /** B8 */
    public function testAlarmDow(): void
    {
        $Alarm = new LSA_Alarm(['Id' => 'ba83a018', 'Time' => 25200, 'Volume' => 20]);
        $Alarm->IpsToDow(127);
        $this->assertSame('0,1,2,3,4,5,6', $Alarm->Dow);
        $this->assertSame(127, $Alarm->DowToIps());
        $Alarm->IpsToDow(0b0000001);
        $this->assertSame('1', $Alarm->Dow);
        $Alarm->IpsToDow(0b1000000);
        $this->assertSame('0', $Alarm->Dow);
        $Alarm->IpsToDow(0);
        $this->assertSame('', $Alarm->Dow);
        $this->assertSame(0, $Alarm->DowToIps());
        $this->assertSame(['Second' => 0, 'Minute' => 0, 'Hour' => 7], $Alarm->TimeToArray());
        $Alarm->ArrayToTime([6, 30, 15]);
        $this->assertSame(23415, $Alarm->Time);
    }

    public function testAlarmList(): void
    {
        $Response = new \SqueezeBox\LMSResponse(self::$Responses['alarms']);
        $Data = (new \SqueezeBox\LMSTaggingArray($Response->Data))->DataArray();
        $List = new LSA_AlarmList($Data);
        $this->assertCount(4, $List->Items);
        $this->assertSame(1, $List->GetById('ba83a018')->Index);
        $this->assertSame('ba83a018', $List->GetByIndex(1)->Id);
        $this->assertSame('', $List->GetByIndex(3)->Dow);
        $this->assertNotFalse($List->GetById('12345678'));
        $List->Remove('ba83a018');
        $this->assertCount(3, $List->Items);
        $this->assertSame('5cd4aa32', $List->GetByIndex(1)->Id);
    }

    /** B32 */
    public function testIsValidAddress(): void
    {
        $this->assertTrue(\SqueezeBox\LMSData::IsValidAddress('00:04:20:2e:57:ee'));
        $this->assertTrue(\SqueezeBox\LMSData::IsValidAddress('00:04:20:2E:57:EE'));
        $this->assertTrue(\SqueezeBox\LMSData::IsValidAddress('192.168.201.20'));
        $this->assertFalse(\SqueezeBox\LMSData::IsValidAddress('00:04:20:2e:57'));
        $this->assertFalse(\SqueezeBox\LMSData::IsValidAddress('00-04-20-2e-57-ee'));
        $this->assertFalse(\SqueezeBox\LMSData::IsValidAddress('192.168.201.300'));
        $this->assertFalse(\SqueezeBox\LMSData::IsValidAddress('Micha'));
    }

    /** E7: Das Passwort eines login-Befehls erscheint nicht im Debug. */
    public function testLoginMasked(): void
    {
        $Login = new \SqueezeBox\LMSData('login', ['Micha', 'geheim 1']);
        $this->assertSame("login Micha ******\r", $Login->Masked()->ToRawStringForLMS());
        $this->assertSame("login Micha geheim%201\r", $Login->ToRawStringForLMS());
        $Other = new \SqueezeBox\LMSData(['playlist', 'play'], ['geheim']);
        $this->assertSame($Other->ToRawStringForLMS(), $Other->Masked()->ToRawStringForLMS());
    }
}
