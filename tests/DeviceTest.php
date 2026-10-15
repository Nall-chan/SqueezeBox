<?php

declare(strict_types=1);

include_once __DIR__ . '/ModuleTestCase.php';

/**
 * Tests der Player-Instanz ohne LMS: Events über ReceiveData, Parameterprüfungen der Instanzfunktionen.
 */
class DeviceTest extends ModuleTestCase
{
    private static array $Responses;
    private int $iid;

    public static function setUpBeforeClass(): void
    {
        self::$Responses = require __DIR__ . '/fixtures/LMSResponses.php';
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->iid = $this->createInstance(self::DeviceID, ['Address' => self::PlayerAddress, 'enablePositionText' => true, 'enableDurationText' => true]);
        $this->Errors = [];
        $this->PhpErrors = [];
    }

    public function testVolumeEvent(): void
    {
        $this->receive($this->iid, self::$Responses['mixerVolume']);
        $this->assertSame(52, $this->values($this->iid)['Volume']);
        $this->assertNoPhpErrors();
    }

    /** B3 */
    public function testPlaylistClear(): void
    {
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 duration 245');
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 time 61');
        $this->assertSame(61, $this->values($this->iid)['Position']);
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 playlist clear');
        $Values = $this->values($this->iid);
        $this->assertSame(0, $Values['Position']);
        $this->assertSame(0, $Values['Duration']);
        $this->assertSame('0:00', $Values['PositionText']);
        $this->assertSame('0:00', $Values['DurationText']);
        $this->assertNoPhpErrors();
        $this->assertSame([], $this->Errors);
    }

    /** B34: Streams haben keine Position, Position2 ist dann nicht bedienbar. */
    public function testStreamHasNoPosition(): void
    {
        $Position2 = IPS_GetObjectIDByIdent('Position2', $this->iid);
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 remote 0');
        $this->assertSame(0, IPS_GetVariable($Position2)['VariableAction'], 'seekable, aber ohne Dauer');
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 duration 245');
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 time 61');
        $this->assertSame(61, $this->values($this->iid)['Position']);
        $this->assertNotSame(0, IPS_GetVariable($Position2)['VariableAction'], 'seekable mit Dauer');

        $this->receive($this->iid, self::$Responses['statusStream']);
        $Values = $this->values($this->iid);
        $this->assertSame(0, $Values['Duration']);
        $this->assertSame(0, $Values['Position']);
        $this->assertSame('00:00', $Values['PositionText']);
        $this->assertSame(0.0, $Values['Position2']);
        $this->assertSame(0, IPS_GetVariable($Position2)['VariableAction']);

        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 time 30');
        $this->assertSame(0, $this->values($this->iid)['Position']);
        $this->assertNoPhpErrors();
    }

    /** B34: Im Zustand Stop fehlt duration auch bei normalen Titeln (Abo-Status ohne Tags); die Dauer bleibt erhalten. */
    public function testStoppedStatusKeepsDuration(): void
    {
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 duration 280');
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 status - 1 subscribe%3A0 player_name%3APlayer%201 player_connected%3A1 mode%3Astop remote%3A1 time%3A0 playlist_tracks%3A50 playlist%20index%3A11 id%3A-200739232 title%3AIkenai%20Borderline');
        $this->assertSame(280, $this->values($this->iid)['Duration']);
        $this->assertNoPhpErrors();
    }

    /** B34: In der Statusantwort steht time vor duration; die Position darf nicht mit der alten Dauer verworfen werden. */
    public function testStatusTimeBeforeDuration(): void
    {
        $this->receive($this->iid, self::$Responses['status']);
        $Values = $this->values($this->iid);
        $this->assertSame(245, $Values['Duration']);
        $this->assertSame(61, $Values['Position']);
        $this->assertNoPhpErrors();
    }

    /** B32: Ungültige Adresse ergibt Status 202, leere Adresse bleibt inaktiv. */
    public function testInvalidAddress(): void
    {
        $Invalid = $this->createInstance(self::DeviceID, ['Address' => '00:04:20:00:00']);
        $this->assertSame(202, IPS_GetInstance($Invalid)['InstanceStatus']);
        $Empty = $this->createInstance(self::DeviceID, ['Address' => '']);
        $this->assertNotSame(202, IPS_GetInstance($Empty)['InstanceStatus']);
        $this->assertNotSame(202, IPS_GetInstance($this->iid)['InstanceStatus']);
        $this->assertNoPhpErrors();
    }

    /** B45: Player mit IP-Adresse haben keine Variable Power. */
    public function testIPPlayerWithoutPower(): void
    {
        $IPPlayer = $this->createInstance(self::DeviceID, ['Address' => '192.168.201.99']);
        $this->assertFalse(@IPS_GetObjectIDByIdent('Power', $IPPlayer));
        IPS_ApplyChanges($IPPlayer);
        $this->assertNoPhpErrors();
    }

    /** B44: Ohne playlist_name in der Statusmeldung (z.B. Stream per URL) wird der Name geleert. */
    public function testPlaylistNameCleared(): void
    {
        $this->receive($this->iid, str_replace('playlist_cur_index', 'playlist_name%3AMeine%20Liste playlist_cur_index', self::$Responses['status']));
        $this->assertSame('Meine Liste', $this->values($this->iid)['Playlistname']);
        $this->receive($this->iid, self::$Responses['statusStream']);
        $this->assertSame('', $this->values($this->iid)['Playlistname']);
        $this->assertNoPhpErrors();
    }

    /** B43: Laden, Fortsetzen und Verschieben laden die Playlist neu, auch wenn die Anzahl gleich bleibt (auch im Abo). */
    public function testPlaylistEventsRefresh(): void
    {
        $this->receive($this->iid, self::$Responses['status']);
        // Ohne LMS schlägt das Laden fehl und meldet 'Error on read playlist'; daran ist der Versuch erkennbar.
        foreach (['play http%3A%2F%2Fradio.example%2Fstream.mp3', 'load_done', 'resume tempplaylist_000420000001 wipePlaylist%3A1 noplay%3A0', 'move 1 2', 'playtracks listRef ARRAY(0x1) %20 %20 index%3A0'] as $Event) {
            $this->Errors = [];
            $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 playlist ' . $Event);
            $this->assertContains('Error on read playlist', $this->Errors, $Event);
        }
        $this->assertNoPhpErrors();
    }

    /** E8: Ohne erzeugte HTML-Playlist ist das Secret leer; ein damit berechneter Hash wird abgewiesen. */
    public function testWebhookWithoutSecret(): void
    {
        $_GET = ['ID' => '1', 'Type' => 'Track', 'Secret' => base64_encode(sha1('' . '0' . '1', true))];
        $Hook = new ReflectionMethod(IPS\InstanceManager::getInstanceInterface($this->iid), 'ProcessHookData');
        ob_start();
        $Hook->invoke(IPS\InstanceManager::getInstanceInterface($this->iid));
        $Output = (string) ob_get_clean();
        $_GET = [];
        $this->assertSame('Access denied', $Output);
        $this->assertNoPhpErrors();
    }

    /** B13 */
    public function testSearchWithoutGenre(): void
    {
        foreach (['LoadPlaylistBySearch', 'AddToPlaylistBySearch', 'InsertInPlaylistBySearch', 'DeleteFromPlaylistBySearch'] as $Function) {
            $this->Errors = [];
            $this->call($this->iid, $Function, '', 'Queen', '');
            $this->assertNotContains('One search pattern is required', $this->Errors, $Function);
            $this->call($this->iid, $Function, '', '', '');
            $this->assertContains('One search pattern is required', $this->Errors, $Function);
            // E1: Öffentliche Funktionen geben keine Meldung per echo aus.
            $this->assertSame('', $this->Output, $Function);
        }
        $this->assertNoPhpErrors();
    }

    /** B23 */
    public function testRelativeValuesEmpty(): void
    {
        foreach (['SetVolumeEx', 'SetBassEx', 'SetTrebleEx', 'SetPitchEx'] as $Function) {
            $this->assertFalse($this->call($this->iid, $Function, ''), $Function);
        }
        $this->assertNoPhpErrors();
    }

    /** B16 */
    public function testSyncWithoutConnection(): void
    {
        $this->assertFalse($this->call($this->iid, 'GetSync'));
        $this->assertFalse($this->call($this->iid, 'SetUnSync'));
        $Second = $this->createInstance(self::DeviceID, ['Address' => '00:04:20:00:00:02']);
        $this->assertFalse($this->call($this->iid, 'SetSync', $Second));
        $this->assertNoPhpErrors();
    }

    /** B24 */
    public function testRequestActionInvalidValues(): void
    {
        $this->call($this->iid, 'RequestAction', 'Status', 9);
        $this->call($this->iid, 'RequestAction', 'Randomplay', 9);
        $this->call($this->iid, 'RequestAction', 'TilePlaylist', 'kein JSON');
        $this->assertNoPhpErrors();
    }

    /** B28, B19 */
    public function testSongInfoWithoutConnection(): void
    {
        $this->assertFalse($this->call($this->iid, 'GetSongInfoByTrackIndex', 1));
        $this->assertFalse($this->call($this->iid, 'GetPlaylistURL'));
        $this->assertNoPhpErrors();
    }

    /** E1/E3: RequestAction zeigt die Meldung der Funktion genau einmal per echo an, ohne zusätzliche Notice. */
    public function testRequestActionShowsMessageOnce(): void
    {
        $this->Errors = [];
        $this->call($this->iid, 'RequestAction', 'Status', 9);
        $this->assertSame('Value invalid.' . PHP_EOL, $this->Output);
        $this->assertSame([], $this->Errors);
        // Ohne verbundenen Player meldet Play() 'Player not connected'; die allgemeine Meldung entfällt dann.
        $this->call($this->iid, 'RequestAction', 'Volume', 20);
        $this->assertStringNotContainsString('Error on Execute Action', $this->Output);
        $this->assertNotSame('', $this->Output);
        $this->assertSame([], $this->Errors);
        $this->assertNoPhpErrors();
    }

    /** E6: Tritt der Player einer Sync-Gruppe bei, liest er seinen Zustand neu; eine leere syncgroupid löst nichts aus. */
    public function testSyncGroupIdRequestsState(): void
    {
        $this->Errors = [];
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 prefset server syncgroupid');
        $this->assertSame([], $this->Errors);
        // Ohne LMS schlägt die erste Abfrage fehl; daran ist der Versuch erkennbar.
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 prefset server syncgroupid 294891357');
        $this->assertNotSame([], $this->Errors);
        $this->assertNoPhpErrors();
    }
    /** E13: newsong lädt das Cover höchstens einmal; bei neuem Index übernimmt VM_UPDATE von Index. */
    public function testNewSongLoadsCoverOnce(): void
    {
        $this->receive($this->iid, self::$Responses['status']);
        $this->assertSame(12, $this->values($this->iid)['Index']);
        $Covers = fn (): int => count(array_filter(IPS\DebugServer::getDebugMessages($this->iid), fn (array $Debug): bool => $Debug['Message'] == 'Refresh Cover'));
        $Before = $Covers();
        // Gleicher Index (z.B. neue Playlist ab demselben Titel): genau ein Cover.
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 playlist newsong Realize 11');
        $this->assertSame($Before + 1, $Covers());
        // Neuer Index: nur der Index wird geschrieben, Cover und Playlist folgen über VM_UPDATE.
        $this->receive($this->iid, '00%3A04%3A20%3A00%3A00%3A01 playlist newsong Next 12');
        $this->assertSame(13, $this->values($this->iid)['Index']);
        $this->assertSame($Before + 1, $Covers());
        $this->assertNoPhpErrors();
    }
}
