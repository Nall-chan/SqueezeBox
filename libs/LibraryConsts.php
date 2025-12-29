<?php

declare(strict_types=1);

/**
 * @package       Squeezebox
 * @file          LibraryConsts.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       4.10
 *
 */

namespace SqueezeBox
{
    class GUID
    {
        public const Splitter = '{96A9AB3A-2538-42C5-A130-FC34205A706A}';
        public const Configurator = '{35028918-3F9C-4524-9FB4-DBAF429C6E18}';
        public const Discovery = '{28AC8A6C-4E03-43BE-9C3E-B8FEF78D374C}';
        public const Squeezebox = '{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}';
        public const Alarm = '{E7423083-3502-42C8-B244-2852D0BE41D4}';
        public const Battery = '{718158BB-B247-4A71-9440-9C2FF1378752}';
        public const SendToSplitter = '{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}';
        public const SendToChild = '{CB5950B3-593C-4126-9F0F-8655A3944419}';
        public const SendToIO = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
        public const ReceiveFromIO = '{018EF6B5-AB94-40C6-AA53-46943E824ACF}';
        public const IO = '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}';
    }

    class Presentation
    {
        public const Type = 'PRESENTATION';
        public const Icon = 'ICON';
    }
}

namespace SqueezeBox\Presentation{
    class Switchable
    {
        public const IconFalseUsed = 'USE_ICON_FALSE';
        public const IconTrue = 'ICON_TRUE';
        public const IconFalse = 'ICON_FALSE';
        public const GlowColor = 'GLOW_COLOR';
        public const GlowIntensity = 'GLOW_INTENSITY';
        public const Type = 'USAGE_TYPE';
    }
    class Slider
    {
        public const Min = 'MIN';
        public const Max = 'MAX';
        public const Digits = 'DIGITS';
        public const Gradient = 'CUSTOM_GRADIENT';
        public const GradientType = 'GRADIENT_TYPE';
        public const Intervals = 'INTERVALS';
        public const IntervalsUsed = 'INTERVALS_ACTIVE';
        public const Percentage = 'PERCENTAGE';
        public const Prefix = 'PREFIX';
        public const Step = 'STEP_SIZE';
        public const Suffix = 'SUFFIX';
        public const Type = 'USAGE_TYPE';
    }
    class Enum
    {
        public const Layout = 'LAYOUT';
        public const Options = 'OPTIONS';
        public const Value = 'Value';
        public const Caption = 'Caption';
        public const IconActive = 'IconActive';
        public const Icon = 'IconValue';
        public const Color = 'Color';
    }

    class Value
    {
        public const Min = 'MIN';
        public const Max = 'MAX';
        public const Digits = 'DIGITS';
        public const Prefix = 'PREFIX';
        public const Suffix = 'SUFFIX';
        public const Type = 'USAGE_TYPE';
        public const IntervalsUsed = 'INTERVALS_ACTIVE';
        public const Intervals = 'INTERVALS';
        public const Options = 'OPTIONS';
        public const Value = 'Value';
        public const Caption = 'Caption';
        public const IconActive = 'IconActive';
        public const Icon = 'IconValue';
        public const ColorActive = 'ColorActive';
        public const Color = 'ColorValue';
    }
    class HTML
    {
        public const Type = 'HTML_TYPE';
        public const Padding = 'PADDING';
    }
}

namespace SqueezeBox\IO{
    class Property
    {
        public const Open = 'Open';
        public const Host = 'Host';
        public const Port = 'Port';
        public const UseSSL = 'UseSSL';
        public const VerifyHost = 'VerifyHost';
        public const VerifyPeer = 'VerifyPeer';
    }
}

namespace SqueezeBox\Splitter
{
    class Property
    {
        public const Username = 'User';
        public const Password = 'Password';
        public const Port = 'Port';
        public const Webport = 'Webport';
        public const ShowHTMLPlaylist = 'showHTMLPlaylist';
        public const Table = 'Table';
        public const Columns = 'Columns';
        public const Rows = 'Rows';
    }
    class Timer
    {
        public const KeepAlive = 'KeepAlive';
    }
}

namespace SqueezeBox\Device
{

    class Property
    {
        public const Address = 'Address';
        public const Interval = 'Interval';
        public const CoverSize = 'CoverSize';
        public const EnableBass = 'enableBass';
        public const EnableTreble = 'enableTreble';
        public const EnablePitch = 'enablePitch';
        public const EnableRandomPlay = 'enableRandomplay';
        public const EnableDurationText = 'enableDurationText';
        public const EnablePositionText = 'enablePositionText';
        public const EnablePreset = 'enablePreset';
        public const EnableSleepTimer = 'enableSleepTimer';
        public const ShowSleepTimeout = 'showSleepTimeout';
        public const ShowSyncMaster = 'showSyncMaster';
        public const ShowSyncControl = 'showSyncControl';
        public const ShowSignalStrength = 'showSignalstrength';
        public const ShowTilePlaylist = 'showTilePlaylist';
        public const ShowHTMLPlaylist = 'showHTMLPlaylist';
        public const ChangeName = 'changeName';
        public const Table = 'Table';
        public const Columns = 'Columns';
        public const Rows = 'Rows';
    }
}

namespace SqueezeBox\Alarm
{
    class Property
    {
        public const Address = 'Address';
        public const DynamicDisplay = 'dynamicDisplay';
        public const ShowAdd = 'showAdd';
        public const ShowDelete = 'showDelete';
        public const ShowAlarmHTMLPlaylist = 'showAlarmHTMLPlaylist';
        public const Table = 'Table';
        public const Columns = 'Columns';
        public const Rows = 'Rows';
    }
}

namespace SqueezeBox\Battery
{
    class Property
    {
        public const Active = 'Active';
        public const Address = 'Address';
        public const Interval = 'Interval';
        public const Password = 'Password';

    }
    class Timer
    {
        public const RequestState = 'RequestState';
    }
}