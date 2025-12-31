<?php

declare(strict_types=1);

/**
 * @package       Squeezebox
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.10
 *
 */
require_once __DIR__ . '/../libs/LibraryConsts.php';
require_once __DIR__ . '/../libs/DebugHelper.php';
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
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       4.10
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 * @method void UnregisterProfile(string $Name)
 * @method void SetValueBoolean(string $Ident, bool $value)
 * @method void SetValueFloat(string $Ident, float $value)
 * @method void SetValueInteger(string $Ident, int $value)
 * @method void SetValueString(string $Ident, string $value)
 * @method bool IORequestAction(string $Ident, mixed $Value)
 * @method void IOMessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data)
 * @method int IORegisterParent()
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
        $this->SetReceiveDataFilter('.*"Address":"NOTHING".*');
        $this->RegisterPropertyBoolean(\SqueezeBox\Battery\Property::Active, true);
        $this->RegisterPropertyString(\SqueezeBox\Battery\Property::Address, '');
        $this->RegisterPropertyInteger(\SqueezeBox\Battery\Property::Interval, 30);
        $this->RegisterPropertyString(\SqueezeBox\Battery\Property::Password, '1234');
        $this->RegisterTimer(\SqueezeBox\Battery\Timer::RequestState, 0, 'LSQB_RequestState($_IPS[\'TARGET\']);');
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
        //Status-Variablen anlegen
        $this->RegisterVariableInteger(
            'State',
            $this->Translate('State'),
            [
                \SqueezeBox\Presentation::Icon                => 'plug',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Digits        => 0,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => '',
                \SqueezeBox\Presentation\Value::IntervalsUsed => true,
                \SqueezeBox\Presentation\Value::Intervals     => json_encode([
                    [
                        \SqueezeBox\Presentation\Value::IntervalMinValue => 0,
                        \SqueezeBox\Presentation\Value::IntervalMaxValue => 2,
                        \SqueezeBox\Presentation\Value::ConstantActive   => true,
                        \SqueezeBox\Presentation\Value::ConstantValue    => $this->Translate('offline'),
                        \SqueezeBox\Presentation\Value::ConversionFactor => 1,
                        \SqueezeBox\Presentation\Value::IconActive       => true,
                        \SqueezeBox\Presentation\Value::Icon             => 'plug-circle-minus',
                        \SqueezeBox\Presentation\Value::PrefixActive     => false,
                        \SqueezeBox\Presentation\Value::PrefixValue      => '',
                        \SqueezeBox\Presentation\Value::SuffixActive     => false,
                        \SqueezeBox\Presentation\Value::SuffixValue      => '',
                        \SqueezeBox\Presentation\Value::DigitsActive     => false,
                        \SqueezeBox\Presentation\Value::DigitsValue      => 0,
                        \SqueezeBox\Presentation\Value::ColorActive      => false,
                        \SqueezeBox\Presentation\Value::Color            => -1
                    ],
                    [
                        \SqueezeBox\Presentation\Value::IntervalMinValue => 3,
                        \SqueezeBox\Presentation\Value::IntervalMaxValue => 4,
                        \SqueezeBox\Presentation\Value::ConstantActive   => true,
                        \SqueezeBox\Presentation\Value::ConstantValue    => $this->Translate('on main'),
                        \SqueezeBox\Presentation\Value::ConversionFactor => 1,
                        \SqueezeBox\Presentation\Value::IconActive       => true,
                        \SqueezeBox\Presentation\Value::Icon             => 'plug-circle-bolt',
                        \SqueezeBox\Presentation\Value::PrefixActive     => false,
                        \SqueezeBox\Presentation\Value::PrefixValue      => '',
                        \SqueezeBox\Presentation\Value::SuffixActive     => false,
                        \SqueezeBox\Presentation\Value::SuffixValue      => '',
                        \SqueezeBox\Presentation\Value::DigitsActive     => false,
                        \SqueezeBox\Presentation\Value::DigitsValue      => 0,
                        \SqueezeBox\Presentation\Value::ColorActive      => false,
                        \SqueezeBox\Presentation\Value::Color            => -1
                    ],
                    [
                        \SqueezeBox\Presentation\Value::IntervalMinValue => 5,
                        \SqueezeBox\Presentation\Value::IntervalMaxValue => 6,
                        \SqueezeBox\Presentation\Value::ConstantActive   => true,
                        \SqueezeBox\Presentation\Value::ConstantValue    => $this->Translate('on battery'),
                        \SqueezeBox\Presentation\Value::ConversionFactor => 1,
                        \SqueezeBox\Presentation\Value::IconActive       => true,
                        \SqueezeBox\Presentation\Value::Icon             => 'battery-full',
                        \SqueezeBox\Presentation\Value::PrefixActive     => false,
                        \SqueezeBox\Presentation\Value::PrefixValue      => '',
                        \SqueezeBox\Presentation\Value::SuffixActive     => false,
                        \SqueezeBox\Presentation\Value::SuffixValue      => '',
                        \SqueezeBox\Presentation\Value::DigitsActive     => false,
                        \SqueezeBox\Presentation\Value::DigitsValue      => 0,
                        \SqueezeBox\Presentation\Value::ColorActive      => false,
                        \SqueezeBox\Presentation\Value::Color            => -1
                    ],
                    [
                        \SqueezeBox\Presentation\Value::IntervalMinValue => 7,
                        \SqueezeBox\Presentation\Value::IntervalMaxValue => 7,
                        \SqueezeBox\Presentation\Value::ConstantActive   => true,
                        \SqueezeBox\Presentation\Value::ConstantValue    => $this->Translate('on main and battery'),
                        \SqueezeBox\Presentation\Value::ConversionFactor => 1,
                        \SqueezeBox\Presentation\Value::IconActive       => true,
                        \SqueezeBox\Presentation\Value::Icon             => 'charging-station',
                        \SqueezeBox\Presentation\Value::PrefixActive     => false,
                        \SqueezeBox\Presentation\Value::PrefixValue      => '',
                        \SqueezeBox\Presentation\Value::SuffixActive     => false,
                        \SqueezeBox\Presentation\Value::SuffixValue      => '',
                        \SqueezeBox\Presentation\Value::DigitsActive     => false,
                        \SqueezeBox\Presentation\Value::DigitsValue      => 0,
                        \SqueezeBox\Presentation\Value::ColorActive      => false,
                        \SqueezeBox\Presentation\Value::Color            => -1
                    ]
                ])
            ],
            1
        );
        $this->RegisterVariableFloat(
            'SysVoltage',
            $this->Translate('Device voltage'),
            [
                \SqueezeBox\Presentation::Icon                => 'Electricity',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Min           => 0,
                \SqueezeBox\Presentation\Value::Max           => 0,
                \SqueezeBox\Presentation\Value::Digits        => 1,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => ' V',
                \SqueezeBox\Presentation\Value::IntervalsUsed => false,
                \SqueezeBox\Presentation\Value::Intervals     => '[]',
                \SqueezeBox\Presentation\Value::Percentage    => false,
                \SqueezeBox\Presentation\Value::Type          => 0
            ],
            2
        );
        $this->RegisterVariableFloat(
            'WallVoltage',
            $this->Translate('Line voltage'),
            [
                \SqueezeBox\Presentation::Icon                => 'Electricity',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Min           => 0,
                \SqueezeBox\Presentation\Value::Max           => 0,
                \SqueezeBox\Presentation\Value::Digits        => 1,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => ' V',
                \SqueezeBox\Presentation\Value::IntervalsUsed => false,
                \SqueezeBox\Presentation\Value::Intervals     => '[]',
                \SqueezeBox\Presentation\Value::Percentage    => false,
                \SqueezeBox\Presentation\Value::Type          => 0
            ],
            3
        );
        $this->RegisterVariableInteger(
            'ChargeState',
            $this->Translate('Charge state'),
            [
                \SqueezeBox\Presentation::Icon                => 'Battery',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Min           => 1,
                \SqueezeBox\Presentation\Value::Max           => 35,
                \SqueezeBox\Presentation\Value::Digits        => 0,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => '',
                \SqueezeBox\Presentation\Value::Percentage    => false,
                \SqueezeBox\Presentation\Value::Type          => 0,
                \SqueezeBox\Presentation\Value::IntervalsUsed => true,
                \SqueezeBox\Presentation\Value::Intervals     => json_encode(
                    [
                        [
                            \SqueezeBox\Presentation\Value::IntervalMinValue => 1,
                            \SqueezeBox\Presentation\Value::IntervalMaxValue => 1,
                            \SqueezeBox\Presentation\Value::ConstantActive   => true,
                            \SqueezeBox\Presentation\Value::ConstantValue    => $this->Translate('not installed'),
                            \SqueezeBox\Presentation\Value::ConversionFactor => 1,
                            \SqueezeBox\Presentation\Value::IconActive       => true,
                            \SqueezeBox\Presentation\Value::Icon             => 'xmark',
                            \SqueezeBox\Presentation\Value::PrefixActive     => false,
                            \SqueezeBox\Presentation\Value::PrefixValue      => '',
                            \SqueezeBox\Presentation\Value::SuffixActive     => false,
                            \SqueezeBox\Presentation\Value::SuffixValue      => '',
                            \SqueezeBox\Presentation\Value::DigitsActive     => false,
                            \SqueezeBox\Presentation\Value::DigitsValue      => 0,
                            \SqueezeBox\Presentation\Value::ColorActive      => false,
                            \SqueezeBox\Presentation\Value::Color            => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Value::IntervalMinValue => 2,
                            \SqueezeBox\Presentation\Value::IntervalMaxValue => 2,
                            \SqueezeBox\Presentation\Value::ConstantActive   => true,
                            \SqueezeBox\Presentation\Value::ConstantValue    => $this->Translate('standby'),
                            \SqueezeBox\Presentation\Value::ConversionFactor => 1,
                            \SqueezeBox\Presentation\Value::IconActive       => true,
                            \SqueezeBox\Presentation\Value::Icon             => 'battery-full',
                            \SqueezeBox\Presentation\Value::PrefixActive     => false,
                            \SqueezeBox\Presentation\Value::PrefixValue      => '',
                            \SqueezeBox\Presentation\Value::SuffixActive     => false,
                            \SqueezeBox\Presentation\Value::SuffixValue      => '',
                            \SqueezeBox\Presentation\Value::DigitsActive     => false,
                            \SqueezeBox\Presentation\Value::DigitsValue      => 0,
                            \SqueezeBox\Presentation\Value::ColorActive      => false,
                            \SqueezeBox\Presentation\Value::Color            => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Value::IntervalMinValue => 3,
                            \SqueezeBox\Presentation\Value::IntervalMaxValue => 7,
                            \SqueezeBox\Presentation\Value::ConstantActive   => true,
                            \SqueezeBox\Presentation\Value::ConstantValue    => $this->Translate('discharging'),
                            \SqueezeBox\Presentation\Value::ConversionFactor => 1,
                            \SqueezeBox\Presentation\Value::IconActive       => true,
                            \SqueezeBox\Presentation\Value::Icon             => 'battery-half',
                            \SqueezeBox\Presentation\Value::PrefixActive     => false,
                            \SqueezeBox\Presentation\Value::PrefixValue      => '',
                            \SqueezeBox\Presentation\Value::SuffixActive     => false,
                            \SqueezeBox\Presentation\Value::SuffixValue      => '',
                            \SqueezeBox\Presentation\Value::DigitsActive     => false,
                            \SqueezeBox\Presentation\Value::DigitsValue      => 0,
                            \SqueezeBox\Presentation\Value::ColorActive      => false,
                            \SqueezeBox\Presentation\Value::Color            => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Value::IntervalMinValue => 8,
                            \SqueezeBox\Presentation\Value::IntervalMaxValue => 24,
                            \SqueezeBox\Presentation\Value::ConstantActive   => true,
                            \SqueezeBox\Presentation\Value::ConstantValue    => $this->Translate('charging'),
                            \SqueezeBox\Presentation\Value::ConversionFactor => 1,
                            \SqueezeBox\Presentation\Value::IconActive       => true,
                            \SqueezeBox\Presentation\Value::Icon             => 'charging-station',
                            \SqueezeBox\Presentation\Value::PrefixActive     => false,
                            \SqueezeBox\Presentation\Value::PrefixValue      => '',
                            \SqueezeBox\Presentation\Value::SuffixActive     => false,
                            \SqueezeBox\Presentation\Value::SuffixValue      => '',
                            \SqueezeBox\Presentation\Value::DigitsActive     => false,
                            \SqueezeBox\Presentation\Value::DigitsValue      => 0,
                            \SqueezeBox\Presentation\Value::ColorActive      => false,
                            \SqueezeBox\Presentation\Value::Color            => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Value::IntervalMinValue => 25,
                            \SqueezeBox\Presentation\Value::IntervalMaxValue => 34,
                            \SqueezeBox\Presentation\Value::ConstantActive   => true,
                            \SqueezeBox\Presentation\Value::ConstantValue    => $this->Translate('load cycle wait state'),
                            \SqueezeBox\Presentation\Value::ConversionFactor => 1,
                            \SqueezeBox\Presentation\Value::IconActive       => true,
                            \SqueezeBox\Presentation\Value::Icon             => 'battery-full',
                            \SqueezeBox\Presentation\Value::PrefixActive     => false,
                            \SqueezeBox\Presentation\Value::PrefixValue      => '',
                            \SqueezeBox\Presentation\Value::SuffixActive     => false,
                            \SqueezeBox\Presentation\Value::SuffixValue      => '',
                            \SqueezeBox\Presentation\Value::DigitsActive     => false,
                            \SqueezeBox\Presentation\Value::DigitsValue      => 0,
                            \SqueezeBox\Presentation\Value::ColorActive      => false,
                            \SqueezeBox\Presentation\Value::Color            => -1
                        ],
                        [
                            \SqueezeBox\Presentation\Value::IntervalMinValue => 35,
                            \SqueezeBox\Presentation\Value::IntervalMaxValue => 35,
                            \SqueezeBox\Presentation\Value::ConstantActive   => true,
                            \SqueezeBox\Presentation\Value::ConstantValue    => $this->Translate('warning'),
                            \SqueezeBox\Presentation\Value::ConversionFactor => 1,
                            \SqueezeBox\Presentation\Value::IconActive       => true,
                            \SqueezeBox\Presentation\Value::Icon             => 'battery-empty',
                            \SqueezeBox\Presentation\Value::PrefixActive     => false,
                            \SqueezeBox\Presentation\Value::PrefixValue      => '',
                            \SqueezeBox\Presentation\Value::SuffixActive     => false,
                            \SqueezeBox\Presentation\Value::SuffixValue      => '',
                            \SqueezeBox\Presentation\Value::DigitsActive     => false,
                            \SqueezeBox\Presentation\Value::DigitsValue      => 0,
                            \SqueezeBox\Presentation\Value::ColorActive      => false,
                            \SqueezeBox\Presentation\Value::Color            => -1
                        ]
                    ]
                )
            ],
            4
        );
        $this->RegisterVariableFloat(
            'BatteryLevel',
            $this->Translate('Battery level'),
            [
                \SqueezeBox\Presentation::Icon                => 'Battery',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Min           => 0,
                \SqueezeBox\Presentation\Value::Max           => 1,
                \SqueezeBox\Presentation\Value::Digits        => 0,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => ' %',
                \SqueezeBox\Presentation\Value::IntervalsUsed => false,
                \SqueezeBox\Presentation\Value::Intervals     => '[]',
                \SqueezeBox\Presentation\Value::Percentage    => true,
                \SqueezeBox\Presentation\Value::Type          => 0
            ],
            5
        );
        $this->RegisterVariableFloat(
            'BatteryTemperature',
            $this->Translate('Battery temperature'),
            [
                \SqueezeBox\Presentation::Icon                => 'Temperature',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Min           => -30,
                \SqueezeBox\Presentation\Value::Max           => 70,
                \SqueezeBox\Presentation\Value::Digits        => 1,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => ' °C',
                \SqueezeBox\Presentation\Value::IntervalsUsed => false,
                \SqueezeBox\Presentation\Value::Intervals     => '[]',
                \SqueezeBox\Presentation\Value::Percentage    => false,
                \SqueezeBox\Presentation\Value::Type          => 1
            ],
            6
        );
        $this->RegisterVariableFloat(
            'BatteryVoltage',
            $this->Translate('Battery voltage total'),
            [
                \SqueezeBox\Presentation::Icon                => 'Electricity',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Min           => 0,
                \SqueezeBox\Presentation\Value::Max           => 0,
                \SqueezeBox\Presentation\Value::Digits        => 1,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => ' V',
                \SqueezeBox\Presentation\Value::IntervalsUsed => false,
                \SqueezeBox\Presentation\Value::Intervals     => '[]',
                \SqueezeBox\Presentation\Value::Percentage    => false,
                \SqueezeBox\Presentation\Value::Type          => 0
            ],
            7
        );
        $this->RegisterVariableFloat(
            'BatteryVMon1',
            $this->Translate('Battery voltage 1'),
            [
                \SqueezeBox\Presentation::Icon                => 'Electricity',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Min           => 0,
                \SqueezeBox\Presentation\Value::Max           => 0,
                \SqueezeBox\Presentation\Value::Digits        => 1,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => ' V',
                \SqueezeBox\Presentation\Value::IntervalsUsed => false,
                \SqueezeBox\Presentation\Value::Intervals     => '[]',
                \SqueezeBox\Presentation\Value::Percentage    => false,
                \SqueezeBox\Presentation\Value::Type          => 0
            ],
            8
        );
        $this->RegisterVariableFloat(
            'BatteryVMon2',
            $this->Translate('Battery voltage 2'),
            [
                \SqueezeBox\Presentation::Icon                => 'Electricity',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Min           => 0,
                \SqueezeBox\Presentation\Value::Max           => 0,
                \SqueezeBox\Presentation\Value::Digits        => 1,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => ' V',
                \SqueezeBox\Presentation\Value::IntervalsUsed => false,
                \SqueezeBox\Presentation\Value::Intervals     => '[]',
                \SqueezeBox\Presentation\Value::Percentage    => false,
                \SqueezeBox\Presentation\Value::Type          => 0
            ],
            9
        );
        $this->RegisterVariableInteger(
            'BatteryCapacity',
            $this->Translate('Battery capacity'),
            [
                \SqueezeBox\Presentation::Icon                => 'Intensity',
                \SqueezeBox\Presentation::Type                => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                \SqueezeBox\Presentation\Value::Min           => 0,
                \SqueezeBox\Presentation\Value::Max           => 0,
                \SqueezeBox\Presentation\Value::Digits        => 0,
                \SqueezeBox\Presentation\Value::Prefix        => '',
                \SqueezeBox\Presentation\Value::Suffix        => ' mAh',
                \SqueezeBox\Presentation\Value::IntervalsUsed => false,
                \SqueezeBox\Presentation\Value::Intervals     => '[]',
                \SqueezeBox\Presentation\Value::Percentage    => false,
                \SqueezeBox\Presentation\Value::Type          => 0
            ],
            10
        );
        // Profile entfernen
        $this->UnregisterProfile('LSQB.Power');
        $this->UnregisterProfile('LSQB.mAh');
        $this->UnregisterProfile('LSQB.Charge');
        // Adresse prüfen
        $Address = $this->ReadPropertyString(\SqueezeBox\Battery\Property::Address);
        if (trim($Address) == '') {
            $this->SetStatus(IS_INACTIVE);
            $this->SetTimerInterval(\SqueezeBox\Battery\Timer::RequestState, 0);
            $this->SetSummary('(none)');
            return;
        }
        $this->SetSummary($Address);
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Battery\Property::Active)) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetTimerInterval(\SqueezeBox\Battery\Timer::RequestState, 0);
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        if ($this->ReadPropertyInteger(\SqueezeBox\Battery\Property::Interval) >= 30) {
            $this->SetStatus(IS_ACTIVE);
            $this->SetTimerInterval(\SqueezeBox\Battery\Timer::RequestState, $this->ReadPropertyInteger(\SqueezeBox\Battery\Property::Interval) * 1000);
            $this->RequestState();
        } else {
            if ($this->ReadPropertyInteger(\SqueezeBox\Battery\Property::Interval) == 0) {
                $this->SetStatus(IS_INACTIVE);
            } else {
                $this->SetStatus(203);
            }
            $this->SetTimerInterval(\SqueezeBox\Battery\Timer::RequestState, 0);
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

    /**
     * RequestState
     * IPS-Instanz-Funktion 'LSQB_RequestState'.
     * Aktuellen Status des Devices ermitteln und, wenn verbunden, abfragen.
     *
     * @return bool True wenn erfolgreich.
     */
    public function RequestState(): bool
    {
        if (!$this->ReadPropertyBoolean(\SqueezeBox\Battery\Property::Active)) {
            return false;
        }
        $Address = trim($this->ReadPropertyString(\SqueezeBox\Battery\Property::Address));
        if ($Address == '') {
            return false;
        }
        $ssh = new \phpseclib\Net\SSH2($this->ReadPropertyString(\SqueezeBox\Battery\Property::Address));
        try {
            $this->SendDebug('Try to connect', '', 0);
            $ssh->login('root', $this->ReadPropertyString(\SqueezeBox\Battery\Property::Password));
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
