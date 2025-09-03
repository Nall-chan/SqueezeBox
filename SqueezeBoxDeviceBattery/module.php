<?php

declare(strict_types=1);

/**
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.05
 *
 */
eval('declare(strict_types=1);namespace SqueezeboxBattery {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace SqueezeboxBattery {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');

$AutoLoader = new AutoLoaderSqueezeboxBatteryPHPSecLib('Net\SSH2');
$AutoLoader->register();

class AutoLoaderSqueezeboxBatteryPHPSecLib
{
    private $namespace;

    public function __construct($namespace = null)
    {
        $this->namespace = $namespace;
    }

    public function register()
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    public function loadClass($className)
    {
        $LibPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'phpseclib' . DIRECTORY_SEPARATOR;
        $file = $LibPath . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

/**
 * SqueezeboxBattery Klasse für die Stromversorgung einer SqueezeBox als Instanz in IPS.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.05
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 * @method void RegisterProfileIntegerEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, int $MaxValue = -1, float $StepSize = 0)
 * @method void RegisterProfileInteger(string $Name, string $Icon, string $Prefix, string $Suffix, int $MinValue, int $MaxValue, int $StepSize)
 * @method void UnregisterProfile(string $Name)
 * @method void SetValueBoolean(string $Ident, bool $value)
 * @method void SetValueFloat(string $Ident, float $value)
 * @method void SetValueInteger(string $Ident, int $value)
 * @method void SetValueString(string $Ident, string $value)
 */
class SqueezeboxBattery extends IPSModuleStrict
{
    use \SqueezeboxBattery\VariableProfileHelper;
    use \SqueezeboxBattery\VariableHelper;

    /**
     * Create
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{96A9AB3A-2538-42C5-A130-FC34205A706A}');
        $this->SetReceiveDataFilter('.*"Address":"NOTHING".*');
        $this->RegisterPropertyBoolean('Active', true);
        $this->RegisterPropertyString('Address', '');
        $this->RegisterPropertyInteger('Interval', 30);
        $this->RegisterPropertyString('Password', '1234');
        $this->RegisterTimer('RequestState', 0, 'LSQB_RequestState($_IPS[\'TARGET\']);');
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        $this->SetReceiveDataFilter('.*"Address":"NOTHING".*');

        //Never delete this line!
        parent::ApplyChanges();

        // Profile anlegen
        $this->RegisterProfileIntegerEx('LSQB.Power', 'Information', '', '', [
            [0, $this->Translate('offline'), '', -1],
            [3, $this->Translate('on main'), '', -1],
            [5, $this->Translate('on battery'), '', -1],
            [7, $this->Translate('on main and battery'), '', -1]
        ]);

        $this->RegisterProfileIntegerEx('LSQB.Charge', 'Battery', '', '', [
            [1, $this->Translate('not installed'), '', -1],
            [2, $this->Translate('standby'), '', -1],
            [3, $this->Translate('discharging'), '', -1],
            [8, $this->Translate('charging'), '', -1],
            [24, $this->Translate('load cycle wait state'), '', -1],
            [35, $this->Translate('warning'), '', -1]
        ]);

        $this->RegisterProfileInteger('LSQB.mAh', 'Intensity', '', ' mAh', 0, 0, 0);

        //Status-Variablen anlegen
        $this->RegisterVariableInteger('State', $this->Translate('State'), 'LSQB.Power', 1);
        $this->RegisterVariableFloat('SysVoltage', $this->Translate('Device voltage'), '~Volt', 2);
        $this->RegisterVariableFloat('WallVoltage', $this->Translate('Line voltage'), '~Volt', 3);
        $this->RegisterVariableInteger('ChargeState', $this->Translate('Charge state'), 'LSQB.Charge', 4);
        $this->RegisterVariableFloat('BatteryLevel', $this->Translate('Battery level'), '~Intensity.1', 5);
        $this->RegisterVariableFloat('BatteryTemperature', $this->Translate('Battery temperature'), '~Temperature', 6);
        $this->RegisterVariableFloat('BatteryVoltage', $this->Translate('Battery voltage total'), '~Volt', 7);
        $this->RegisterVariableFloat('BatteryVMon1', $this->Translate('Battery voltage 1'), '~Volt', 8);
        $this->RegisterVariableFloat('BatteryVMon2', $this->Translate('Battery voltage 2'), '~Volt', 9);
        $this->RegisterVariableInteger('BatteryCapacity', $this->Translate('Battery capacity'), 'LSQB.mAh', 10);

        // Adresse prüfen
        $Address = $this->ReadPropertyString('Address');

        if (trim($Address) == '') {
            $this->SetStatus(IS_INACTIVE);
            $this->SetTimerInterval('RequestState', 0);
            $this->SetSummary('(none)');
            return;
        }
        $this->SetSummary($Address);
        if (!$this->ReadPropertyBoolean('Active')) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetTimerInterval('RequestState', 0);
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        if ($this->ReadPropertyInteger('Interval') >= 30) {
            $this->SetStatus(IS_ACTIVE);
            $this->SetTimerInterval('RequestState', $this->ReadPropertyInteger('Interval') * 1000);
            $this->RequestState();
        } else {
            if ($this->ReadPropertyInteger('Interval') == 0) {
                $this->SetStatus(IS_INACTIVE);
            } else {
                $this->SetStatus(203);
            }
            $this->SetTimerInterval('RequestState', 0);
        }
    }

    /**
     * MessageSink
     *
     * @param  int $TimeStamp
     * @param  int $SenderID
     * @param  int $Message
     * @param  array $Data
     * @return void
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->UnregisterMessage(0, IPS_KERNELSTARTED);
                $this->ApplyChanges();
                break;
        }
    }
    //################# PUBLIC

    /**
     * RequestState
     * IPS-Instanz-Funktion 'LSQB_RequestState'.
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen.
     *
     * @return bool True wenn erfolgreich.
     */
    public function RequestState(): bool
    {
        if (!$this->ReadPropertyBoolean('Active')) {
            return false;
        }
        $Address = trim($this->ReadPropertyString('Address'));
        if ($Address == '') {
            return false;
        }
        $ssh = new \phpseclib\Net\SSH2($this->ReadPropertyString('Address'));
        try {
            $this->SendDebug('Try to connect', '', 0);
            $ssh->login('root', $this->ReadPropertyString('Password'));
        } catch (\Throwable $th) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Login failed.'), E_USER_NOTICE);
            $this->SendDebug('Login', 'ERROR', 0);
            return false;
        }
        $PowerMode = (int) $ssh->exec('cat /sys/class/i2c-adapter/i2c-1/1-0010/power_mode');
        $this->SendDebug('PowerMode', $PowerMode, 0);
        $this->SetValueInteger('State', $PowerMode);
        if ($PowerMode == 5) {
            $this->SetValueFloat('WallVoltage', 0);
        } else {
            $WallVoltage = round((int) $ssh->exec('cat /sys/class/i2c-adapter/i2c-1/1-0010/wall_voltage') / 1000, 1);
            $this->SetValueFloat('WallVoltage', $WallVoltage);
        }

        $SysVoltage = round((float) $ssh->exec('cat /sys/class/i2c-adapter/i2c-1/1-0010/sys_voltage') / 1000, 1);
        $this->SendDebug('SysVoltage', $SysVoltage, 0);
        $this->SetValueFloat('SysVoltage', $SysVoltage);
        $ChargeState = (int) $ssh->exec('cat /sys/class/i2c-adapter/i2c-1/1-0010/charger_state');
        $this->SendDebug('ChargeState', $ChargeState, 0);
        $this->SetValueInteger('ChargeState', $ChargeState);
        if ($ChargeState != 1) {
            $BatteryLevel = (int) $ssh->exec('cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_charge') / 2000;
            $this->SendDebug('BatteryLevel', $BatteryLevel, 0);
            $this->SetValueFloat('BatteryLevel', $BatteryLevel);
            $BatteryCapacity = (int) $ssh->exec('cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_capacity');
            $this->SendDebug('BatteryCapacity', $BatteryCapacity, 0);
            $this->SetValueInteger('BatteryCapacity', $BatteryCapacity);
            $BatteryTemperature = round((float) $ssh->exec('cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_temperature') / 32, 1);
            $this->SendDebug('BatteryTemperature', $BatteryTemperature, 0);
            $this->SetValueFloat('BatteryTemperature', $BatteryTemperature);
            $BatteryVoltage = round((float) $ssh->exec('cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_voltage') / 1000, 1);
            $this->SendDebug('BatteryVoltage', $BatteryVoltage, 0);
            $this->SetValueFloat('BatteryVoltage', $BatteryVoltage);
            $BatteryVMon1 = round((float) $ssh->exec('cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_vmon1_voltage') / 1000, 1);
            $this->SendDebug('BatteryVMon1', $BatteryVMon1, 0);
            $this->SetValueFloat('BatteryVMon1', $BatteryVMon1);
            $BatteryVMon2 = round((float) $ssh->exec('cat /sys/class/i2c-adapter/i2c-1/1-0010/battery_vmon2_voltage') / 1000, 1);
            $this->SendDebug('BatteryVMon2', $BatteryVMon2, 0);
            $this->SetValueFloat('BatteryVMon2', $BatteryVMon2);
        } else {
            $this->SetValueFloat('BatteryLevel', 0.0);
            $this->SetValueInteger('BatteryCapacity', 0);
            $this->SetValueFloat('BatteryTemperature', 0.0);
            $this->SetValueFloat('BatteryVoltage', 0.0);
            $this->SetValueFloat('BatteryVMon1', 0.0);
            $this->SetValueFloat('BatteryVMon2', 0.0);
        }

        $ssh->disconnect();
        $this->SendDebug('Disconnect', '', 0);
        return true;
    }

    /**
     * ModulErrorHandler
     *
     * @param  int $errno
     * @param  string $errstr
     * @return bool
     */
    protected function ModulErrorHandler(int $errno, string $errstr): bool
    {
        if (!(error_reporting() & $errno)) {
            // Dieser Fehlercode ist nicht in error_reporting enthalten
            return true;
        }
        $this->SendDebug('ERROR', $errstr, 0);
        echo $errstr . "\r\n";
        return false;
    }
}
