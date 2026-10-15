<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../libs/LibraryConsts.php';
require_once __DIR__ . '/../libs/SqueezeBoxClass.php';

/**
 * Prüft Antwortzuordnung des Splitters, Darstellungs-Konstanten und Übersetzungen.
 */
class QualityTest extends TestCase
{
    /** Texte, die nicht übersetzt werden (Cover-Größen im Player-Formular). */
    private const UntranslatedPattern = '/^\d+x\d+px$/';

    /** Libs, deren Translate()-Texte ein Modul verwendet, wenn es die Datei einbindet. */
    private const Libs = ['libs/SqueezeBoxClass.php', 'libs/LMSSocket.php', 'libs/ActionErrorHandler.php', 'libs/SqueezeBoxAlarmClass.php'];

    /** Zuordnung der Konstanten-Klassen zu den Darstellungen aus tests/fixtures/presentations.json. */
    private const PresentationClasses = [
        \SqueezeBox\Presentation\Switchable::class => 'SWITCH',
        \SqueezeBox\Presentation\Slider::class     => 'SLIDER',
        \SqueezeBox\Presentation\Enum::class       => 'ENUMERATION',
        \SqueezeBox\Presentation\Value::class      => 'VALUE_PRESENTATION',
        \SqueezeBox\Presentation\HTML::class       => 'WEB_CONTENT',
        \SqueezeBox\Presentation\Duration::class   => 'DURATION',
    ];

    /** E12: Antworten gehen an die passende Anfrage, auch bei gleichem Befehl mit anderem Parameter. */
    public function testReplyQueueMatchesEcho(): void
    {
        $Queue = [
            'a' => \SqueezeBox\LMSReplyQueue::CreateEntry(new \SqueezeBox\LMSData(['player', 'id'], [0, '?'])),
            'b' => \SqueezeBox\LMSReplyQueue::CreateEntry(new \SqueezeBox\LMSData(['player', 'id'], [1, '?']))
        ];
        $this->assertSame('b', \SqueezeBox\LMSReplyQueue::FindRequest($Queue, new \SqueezeBox\LMSResponse('player id 1 00%3A04%3A20%3A2b%3A78%3A5f')));
        $this->assertSame('a', \SqueezeBox\LMSReplyQueue::FindRequest($Queue, new \SqueezeBox\LMSResponse('player id 0 00%3A04%3A20%3A2e%3A57%3Aee')));
        // Bereits beantwortete Anfragen bekommen keine zweite Antwort.
        $Queue['b']['Data'] = ['1', '00:04:20:2b:78:5f'];
        $this->assertSame('a', \SqueezeBox\LMSReplyQueue::FindRequest($Queue, new \SqueezeBox\LMSResponse('player id 1 00%3A04%3A20%3A2b%3A78%3A5f')));
    }

    /** E12: Gleiche Anfragen der Reihe nach; ein ? steht nicht im Echo; Ereignisse ohne Anfrage bleiben Ereignisse. */
    public function testReplyQueueOrderAndEvents(): void
    {
        $Queue = [
            'a' => \SqueezeBox\LMSReplyQueue::CreateEntry(new \SqueezeBox\LMSData('rescan', '?')),
            'b' => \SqueezeBox\LMSReplyQueue::CreateEntry(new \SqueezeBox\LMSData('rescan', '?'))
        ];
        $this->assertSame('', $Queue['a']['Echo']);
        $this->assertSame('a', \SqueezeBox\LMSReplyQueue::FindRequest($Queue, new \SqueezeBox\LMSResponse('rescan 0')));
        $this->assertNull(\SqueezeBox\LMSReplyQueue::FindRequest($Queue, new \SqueezeBox\LMSResponse('00%3A04%3A20%3A2e%3A57%3Aee playlist newsong Titel 3')));
        // Login: Das Passwort kommt maskiert zurück, der Benutzer stimmt überein.
        $Login = ['l' => \SqueezeBox\LMSReplyQueue::CreateEntry(new \SqueezeBox\LMSData('login', ['Micha', 'geheim']))];
        $this->assertSame('l', \SqueezeBox\LMSReplyQueue::FindRequest($Login, new \SqueezeBox\LMSResponse('login Micha ******')));
        // Ohne passendes Echo gilt wie bisher die älteste Anfrage mit gleichem Befehl.
        $Show = ['s' => \SqueezeBox\LMSReplyQueue::CreateEntry(new \SqueezeBox\LMSData('show', ['line1:Hallo Welt', 'duration:3']))];
        $this->assertSame('s', \SqueezeBox\LMSReplyQueue::FindRequest($Show, new \SqueezeBox\LMSResponse('show line1%3AHallo%2BWelt duration%3A3')));
    }

    /** F8: Jede Konstante einer Darstellungs-Klasse ist ein Parameter dieser Darstellung (auch in Gruppen und Listen). */
    public function testPresentationConstants(): void
    {
        $Presentations = json_decode(file_get_contents(__DIR__ . '/fixtures/presentations.json'), true);
        foreach (self::PresentationClasses as $Class => $Name) {
            $Known = $this->parameterNames($Presentations[$Name]);
            foreach ((new ReflectionClass($Class))->getConstants() as $Constant => $Parameter) {
                $this->assertContains($Parameter, $Known, $Class . '::' . $Constant . ' ist kein Parameter von ' . $Name);
            }
        }
        // Allgemeine Konstanten: ICON gibt es nicht bei jeder Darstellung, PRESENTATION ist die Darstellungs-GUID.
        $this->assertSame('PRESENTATION', \SqueezeBox\Presentation::Type);
        $this->assertContains(\SqueezeBox\Presentation::Icon, $this->parameterNames($Presentations['SLIDER']));
    }

    /** F8: Alle Texte aus Translate() und form.json sind in der locale.json des Moduls übersetzt. */
    public function testTranslations(): void
    {
        $Root = dirname(__DIR__);
        foreach (glob($Root . '/*/module.php') as $ModuleFile) {
            $Dir = dirname($ModuleFile);
            $Locale = json_decode(file_get_contents($Dir . '/locale.json'), true)['translations']['de'] ?? [];
            $Texts = $this->translateTexts($ModuleFile);
            $Code = file_get_contents($ModuleFile);
            foreach (self::Libs as $Lib) {
                if (str_contains($Code, basename($Lib))) {
                    $Texts += $this->translateTexts($Root . '/' . $Lib);
                }
            }
            if (file_exists($Dir . '/form.json')) {
                $this->collectFormTexts(json_decode(file_get_contents($Dir . '/form.json'), true), $Texts);
            }
            $Missing = array_filter(
                array_keys(array_diff_key($Texts, $Locale)),
                fn (string $Text): bool => preg_match(self::UntranslatedPattern, $Text) !== 1
            );
            $this->assertSame([], array_values($Missing), basename($Dir) . ': Übersetzungen fehlen');
        }
    }

    /**
     * Alle Parameternamen einer Darstellung inkl. Gruppen und Listenparameter.
     */
    private function parameterNames(array $Presentation): array
    {
        $Names = [];
        $Lists = [$Presentation['presentationParameters'] ?? []];
        foreach ($Presentation['groups'] ?? [] as $Group) {
            $Lists[] = $Group['presentationParameters'];
        }
        foreach ($Lists as $Parameters) {
            foreach ($Parameters as $Name => $Definition) {
                $Names[] = $Name;
                foreach (array_keys($Definition['parameters'] ?? []) as $Sub) {
                    $Names[] = $Sub;
                }
            }
        }
        return array_unique($Names);
    }

    /**
     * Texte aus Translate('...') einer Datei.
     */
    private function translateTexts(string $File): array
    {
        $Texts = [];
        $Code = file_get_contents($File);
        preg_match_all('/Translate\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*\)/', $Code, $Matches);
        foreach ($Matches[1] as $Text) {
            $Texts[stripslashes($Text)] = basename($File);
        }
        preg_match_all('/Translate\(\s*"((?:[^"\\\\]|\\\\.)*)"\s*\)/', $Code, $Matches);
        foreach ($Matches[1] as $Text) {
            $Texts[stripcslashes($Text)] = basename($File);
        }
        return $Texts;
    }

    /**
     * Beschriftungen aus form.json.
     */
    private function collectFormTexts(mixed $Node, array &$Texts): void
    {
        if (!is_array($Node)) {
            return;
        }
        foreach ($Node as $Key => $Value) {
            if (is_string($Value) && in_array($Key, ['caption', 'label', 'confirm'], true) && ($Value !== '')) {
                $Texts[$Value] = 'form.json';
            } elseif (is_array($Value)) {
                $this->collectFormTexts($Value, $Texts);
            }
        }
    }
}
