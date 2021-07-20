[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-3.62-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-5.3%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/SqueezeBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/SqueezeBox/actions) [![Run Tests](https://github.com/Nall-chan/SqueezeBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/SqueezeBox/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](../README.md#6-spenden)  
# Squeezebox Battery  <!-- omit in toc -->
Daten zur Stromversorgung und des Akkus in IPS einbinden.  

## Dokumentation  <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
- [6. WebFront](#6-webfront)
- [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)
- [8. Lizenzen](#8-lizenzen)

## 1. Funktionsumfang

 - Auslesen und darstellen von Werten der Stromversorgung und des Akkumulators.  

## 2. Voraussetzungen

 - IPS 5.3 oder höher
 - kompatibler Player mit aktivierten SSH-Zugang  

## 3. Software-Installation

  Dieses Modul ist Bestandteil der [SqueezeBox-Library](../README.md#3-software-installation).  

## 4. Einrichten der Instanzen in IP-Symcon

Eine einfache Einrichtung ist über den Konfigurator [Logitech Media Server Konfigurator](../LMSConfigurator/README.md) möglich.  
Bei der manuellen Einrichtung ist die Instanz im Dialog `Instanz hinzufügen` unter dem Hersteller `Logitech` zu finden.  
![Instanz hinzufügen](imgs/add1.png)  

**Konfigurationsseite:**  
![Instanz hinzufügen](imgs/conf1.png)  

| Name       | Eigenschaft | Typ     | Standardwert | Funktion                               |
| :--------: | :---------: | :-----: | :----------: | :------------------------------------: |
| Host       | Address     | string  |              | IP-Adresse / Hostname der Squeezebox   |
| Passwort   | Password    | string  | 1234         | Passwort für den SSH-Zugang            |
| Intervall  | Interval    | integer | 30           | Abfrageintervall                       |


## 5. Statusvariablen und Profile

Folgende Statusvariablen werden automatisch angelegt.
**Statusvariablen:**  

| Name               | Typ     | Ident              | Beschreibung                     |
| :----------------: | :-----: | :----------------: | :------------------------------: |
| Status             | integer | State              | Status der Stromversorgung       |
| Gerätespannung     | float   | SysVoltage         | Interne Gerätespannung           |
| Netzspannung       | float   | WallVoltage        | Spannung vom externen Anschluss  |
| Ladestatus         | integer | ChargeState        | Aktueller Betriebsmodus des Akku |
| Akkuladekapazität  | float   | BatteryLevel       | in Prozent                       |
| Akkutemperatur     | float   | BatteryTemperature | in °C                            |
| Akkuspannung Summe | float   | BatteryVoltage     | in Volt                          |
| Akkuspannung 1     | float   | BatteryVMon1       | in Volt                          |
| Akkuspannung 2     | float   | BatteryVMon2       | in Volt                          |
| Akkukapazität      | integer | BatteryCapacity    | in mAh                           |

**Profile**:

| Name        | Typ     | verwendet von Statusvariablen |
| :---------: | :-----: | :---------------------------: |
| LSQB.Power  | integer | Status                        |
| LSQB.Charge | integer | Ladestatus                    |
| LSQB.mAh    | integer | Akkukapazität                 |


## 6. WebFront

Die direkte Darstellung der Instanz im WebFront:  
![WebFront Beispiel](imgs/wf.png)  

## 7. PHP-Befehlsreferenz

```php
bool LSQB_RequestState(int $InstanzID)
```
Aktuellen Status aus dem Gerät auslesen.  
Es wird `true` zurückgeben wenn die Abfrage erfolgreich war,  
oder `false` im Fehlerfall.  


## 8. Lizenzen

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  

  phpseclib from Jim Wigginton <terrafrost@php.net>  
   [MIT License](http://www.opensource.org/licenses/mit-license.html)  
   Link: [http://phpseclib.sourceforge.net](http://phpseclib.sourceforge.net)  
 