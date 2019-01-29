<?php

/*
 * @addtogroup squeezebox
 * @{
 *
 * @package       Squeezebox
 * @file          SqueezeBoxTraits.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.0
 *
 */


/**
 * Ein Trait welcher es ermöglicht über einen Ident Variablen zu beschreiben.
 */
trait VariableHelper
{
    /**
     *  Konvertiert Sekunden in einen lesbare Zeit.
     * @param int $Time Zeit in Sekunden
     * @return string Zeit als String.
     */
    protected function ConvertSeconds(int $Time)
    {
        if ($Time > 3600) {
            return sprintf('%02d:%02d:%02d', ($Time / 3600), ($Time / 60 % 60), $Time % 60);
        } else {
            return sprintf('%02d:%02d', ($Time / 60 % 60), $Time % 60);
        }
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
        if ($id == false) {
            return false;
        }
        if (GetValueBoolean($id) <> $value) {
            $this->SetValue($Ident, $value);
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
        if ($id == false) {
            return false;
        }
        if (GetValueInteger($id) <> $value) {
            $this->SetValue($Ident, $value);
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
        if ($id == false) {
            return false;
        }
        if (GetValueString($id) <> $value) {
            $this->SetValue($Ident, $value);
            return true;
        }
        return false;
    }
}

/** @} */
