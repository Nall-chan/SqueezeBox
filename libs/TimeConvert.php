<?php

declare(strict_types=1);

namespace SqueezeBox;

/**
 * Ein Trait welcher Sekunden in einen lesbare Zeit konvertiert.
 */
trait TimeConvert
{
    /**
     * ConvertSeconds
     *  Konvertiert Sekunden in einen lesbare Zeit.
     *
     * @param int $Time Zeit in Sekunden
     *
     * @return string Zeit als String.
     */
    protected function ConvertSeconds(int $Time): string
    {
        if ($Time > 3600) {
            return sprintf('%02d:%02d:%02d', ($Time / 3600), ($Time / 60 % 60), $Time % 60);
        } else {
            return sprintf('%02d:%02d', ($Time / 60 % 60), $Time % 60);
        }
    }
}
