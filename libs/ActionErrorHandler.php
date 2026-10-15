<?php

declare(strict_types=1);

namespace SqueezeBox;

/**
 * @package       Squeezebox
 * @file          ActionErrorHandler.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2026 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.20
 *
 */

/**
 * Zeigt Meldungen aus Bedienaktionen (RequestAction) und Webhooks dem Nutzer an.
 *
 * Öffentliche Funktionen melden Fehler nur per trigger_error(..., E_USER_NOTICE).
 * Während einer Bedienaktion gibt der Handler diese Meldungen per echo aus,
 * damit das Frontend sie anzeigt.
 */
trait ActionErrorHandler
{
    /** @var bool True, sobald während der aktuellen Aktion eine Meldung ausgegeben wurde. */
    private bool $ActionMessageShown = false;

    /**
     * ModulErrorHandler
     * Gibt eine Meldung per echo aus. Nur während ExecuteWithMessages aktiv.
     *
     * @param  int $errno
     * @param  string $errstr
     * @return bool
     */
    protected function ModulErrorHandler(int $errno, string $errstr): bool
    {
        if (!(error_reporting() & $errno)) {
            // Mit @ unterdrückte Meldung
            return true;
        }
        $this->SendDebug('ERROR', $errstr, 0);
        echo $errstr . PHP_EOL;
        $this->ActionMessageShown = true;
        return true;
    }

    /**
     * ExecuteWithMessages
     * Führt eine Aktion aus und gibt alle dabei gemeldeten Fehler per echo aus.
     * Liefert die Aktion false ohne eigene Meldung, wird eine allgemeine Meldung ausgegeben.
     *
     * @param  callable $Action
     * @return void
     */
    private function ExecuteWithMessages(callable $Action): void
    {
        $this->ActionMessageShown = false;
        set_error_handler([$this, 'ModulErrorHandler'], E_USER_NOTICE | E_USER_WARNING);
        try {
            if (($Action() === false) && !$this->ActionMessageShown) {
                trigger_error($this->Translate('Error on Execute Action'), E_USER_NOTICE);
            }
        } finally {
            restore_error_handler();
        }
    }
}
