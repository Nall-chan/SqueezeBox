<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../LMSConfigurator');
    }

    public function testValidateDiscovery(): void
    {
        $this->validateModule(__DIR__ . '/../LMSDiscovery');
    }

    public function testValidateSplitter(): void
    {
        $this->validateModule(__DIR__ . '/../LMSSplitter');
    }

    public function testValidateAlarms(): void
    {
        $this->validateModule(__DIR__ . '/../SqueezeBoxAlarm');
    }
    public function testValidateDevice(): void
    {
        $this->validateModule(__DIR__ . '/../SqueezeBoxDevice');
    }
    public function testValidateBattery(): void
    {
        $this->validateModule(__DIR__ . '/../SqueezeBoxDeviceBattery');
    }
}