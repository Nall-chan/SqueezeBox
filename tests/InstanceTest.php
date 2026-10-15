<?php

declare(strict_types=1);

include_once __DIR__ . '/ModuleTestCase.php';

/**
 * Anlegen der Instanzen aller Module im Stub-Kernel.
 */
class InstanceTest extends ModuleTestCase
{
    /**
     * Splitter und Batterie registrieren Timer; die Stubs verlangen dafür getTime() im Modul.
     * Diese Module werden daher nur über ihre Lib-Klassen getestet.
     */
    public function testCreateModules(): void
    {
        foreach ([self::DiscoveryID, self::ConfiguratorID, self::DeviceID, self::AlarmID] as $ModuleID) {
            $iid = $this->createInstance($ModuleID);
            $this->assertTrue(IPS_InstanceExists($iid), $ModuleID);
        }
        $this->assertSame([], $this->Errors);
    }

    public function testDeviceWithAddress(): void
    {
        $iid = $this->createInstance(self::DeviceID, ['Address' => self::PlayerAddress]);
        $this->assertArrayHasKey('Volume', $this->values($iid));
        $this->assertSame(IS_INACTIVE, IPS_GetInstance($iid)['InstanceStatus']);
    }

    public function testAlarmWithAddress(): void
    {
        $iid = $this->createInstance(self::AlarmID, ['Address' => self::PlayerAddress]);
        $this->assertArrayHasKey('EnableAll', $this->values($iid));
    }
}
