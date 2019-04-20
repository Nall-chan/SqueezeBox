<?php

/*
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          VariableHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 *
 */

/**
 * Ein Trait welcher es ermöglicht Typensicher IPS-Variablen zu beschreiben.
 */
trait VariableHelper
{
    /**
     * Setzte eine IPS-Variable vom Typ bool auf den Wert von $value.
     *
     * @param string $Ident Ident der Statusvariable.
     * @param bool   $value Neuer Wert der Statusvariable.
     *
     * @return bool true wenn der neue Wert vom alten abweicht, sonst false.
     */
    protected function SetValueBoolean($Ident, $value)
    {
        $this->SetValue($Ident, (bool) $value);
    }

    /**
     * Setzte eine IPS-Variable vom Typ float auf den Wert von $value.
     *
     * @param string $Ident Ident der Statusvariable.
     * @param float  $value Neuer Wert der Statusvariable.
     *
     * @return bool true wenn der neue Wert vom alten abweicht, sonst false.
     */
    protected function SetValueFloat($Ident, $value)
    {
        $this->SetValue($Ident, (float) $value);
    }

    /**
     * Setzte eine IPS-Variable vom Typ integer auf den Wert von $value.
     *
     * @param string $Ident Ident der Statusvariable.
     * @param int    $value Neuer Wert der Statusvariable.
     *
     * @return bool true wenn der neue Wert vom alten abweicht, sonst false.
     */
    protected function SetValueInteger($Ident, $value)
    {
        $this->SetValue($Ident, (int) $value);
    }

    /**
     * Setzte eine IPS-Variable vom Typ string auf den Wert von $value.
     *
     * @param string $Ident Ident der Statusvariable.
     * @param string $value Neuer Wert der Statusvariable.
     *
     * @return bool true wenn der neue Wert vom alten abweicht, sonst false.
     */
    protected function SetValueString($Ident, $value)
    {
        $this->SetValue($Ident, (string) $value);
    }

}

/* @} */
