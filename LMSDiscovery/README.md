[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.00-blue.svg)]()
[![Version](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-4.3%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-4-3-%28Stable%29-Changelog)

# Squeezebox Konfigurator  
Vereinfacht das Anlegen von verschiedenen SqueezeBox-Instanzen.  

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Software-Installation](#3-software-installation) 
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz) 
8. [Anhang](#8-anhang)  
9. [Lizenz](#9-lizenz)

## 1. Funktionsumfang

 - Auslesen und darstellen aller im LMS und IPS bekannten Geräte und Instanzen.  
 - Einfaches Anlegen von neuen Instanzen in IPS.  

## 2. Voraussetzungen

 - IPS 4.3 oder höher
 - Logitech Media Server (getestet ab 7.9.x)
 - kompatibler Player

## 3. Software-Installation

 Dieses Modul ist Bestandteil der IPSSqueezeBox-Library.
   *Über das 'Modul Control' folgende URL hinzufügen:  
    `git://github.com/Nall-chan/IPSSqueezeBox.git`  

## 4. Einrichten der Instanzen in IP-Symcon

Das Modul ist im Dialog 'Instanz hinzufügen' unter dem Hersteller 'Logitech' zufinden.  
![Instanz hinzufügen](imgs/add1.png)  

Alternativ ist es auch in der Liste alle Konfiguratoren aufgeführt.  
![Instanz hinzufügen](imgs/add2.png)  

Es wird automatisch eine LMSSplitter Instanz erzeugt, wenn noch keine vorhanden ist.  
Werden in dem sich öffnenden Konfigurationsformular keine Geräte angezeigt, so ist zuerst der Splitter korrekt zu konfigurieren.  
Dieser kann über die Schaltfläche 'Gateway konfigurieren' erreicht werden.  
Details dazu sind der Dokumentation des Splitters zu entnehmen.

Ist der Splitter korrekt verbunden, wird beim öffnen des Konfigurator folgendender Dialog angezeigt.  
![Konfigurator](imgs/conf.png)  

Über das selektieren eines Eintrages in der Tabelle uns benötigen des dazugehörigen 'Erstellen' Button,  
können alle Instanzen in IPS angelegt werden.  

## 5. Statusvariablen und Profile

Der Konfigurator besitzt keine Statusvariablen und Variablenprofile.  

## 6. WebFront

Der Konfigurator besitzt keine im WebFront darstellbaren Elemente.  

## 7. PHP-Befehlsreferenz

Der Konfigurator besitzt keine Instanz-Funktionen.  

## 8. Anhang

**Changlog:**  

Version 2.0:  
 - Komplett überarbeitete Version für IPS 4.3 und höher  

Version 1.0:  
 - Erstes offizielles Release  

## 9. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
