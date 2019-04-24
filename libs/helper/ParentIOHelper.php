<?php

/**
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          ParentIOHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 */

/**
 * Trait mit Hilfsfunktionen für den Datenaustausch.
 * @property integer $ParentID
 */
trait InstanceStatus
{
    /**
     * Interne Funktion des SDK.
     * Alle Events des Parent werden hier verarbeitet und entkoppelt über RequestAction an IOChangeState übergeben.
     * @access public
     */
    protected function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case FM_CONNECT:
            case IM_CHANGESETTINGS:
                $this->RegisterParent();
                if ($this->HasActiveParent()) {
                    $State = IS_ACTIVE;
                } else {
                    $State = IS_INACTIVE;
                }

                break;
            case FM_DISCONNECT:
                $this->RegisterParent();
                $State = IS_INACTIVE;
                break;
            case IM_CHANGESTATUS:
                $State = $Data[0];
                break;
            default:
                return;
        }
        IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',"IOChangeState",' . $State . ');');
    }

    /**
     * Interne Funktion des SDK.
     * Empfängt über RequestAction die Events vom Parent und führt IOChangeState aus.
     *
     * @access public
     * @return bool True wenn $Ident verarbeitet wurde.
     */
    protected function RequestAction($Ident, $Value)
    {
        if ($Ident != 'IOChangeState') {
            return false;
        }
        $this->IOChangeState($Value);
        return true;
    }

    /**
     * Ermittelt den Parent und verwaltet die Einträge des Parent im MessageSink
     * Ermöglicht es das Statusänderungen des Parent empfangen werden können.
     *
     * @access protected
     * @return int ID des Parent.
     */
    protected function RegisterParent()
    {
        $OldParentId = $this->ParentID;
        $ParentId = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($ParentId <> $OldParentId) {
            if ($OldParentId > 0) {
                $this->UnregisterMessage($OldParentId, IM_CHANGESTATUS);
            }
            if ($ParentId > 0) {
                $this->RegisterMessage($ParentId, IM_CHANGESTATUS);
            } else {
                $ParentId = 0;
            }
            $this->ParentID = $ParentId;
        }
        return $ParentId;
    }

    /**
     * Interne Funktion des SDK.
     * Erweitert die SDK funktion um die Prüfung ob überhaupt ein Parent verbunden ist.
     * @return bool True wenn Parent-Kette vorhanden und aktiv ist.
     */
    protected function HasActiveParent()
    {
        if ($this->ParentID > 0) {
            return parent::HasActiveParent();
        }
        return false;
    }

}

/* @} */
