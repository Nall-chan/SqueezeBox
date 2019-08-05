[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-3.22-blue.svg)]()
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-5.1%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-5-1-%28Stable%29-Changelog)
[![StyleCI](https://styleci.io/repos/199910754/shield?style=flat)](https://styleci.io/repos/199910754)  

# Logitech Media Server Discovery  
Sucht Logitech Media Server im Netzwerk  

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Software-Installation](#3-software-installation)
4. [Verwendung](#4-verwendung)
5. [Lizenz](#8-lizenz)

## 1. Funktionsumfang

 - Einfaches Auffinden von Logitech Media Servern im lokalen Netzwerk.  
 - Einfaches Einrichten von Konfiguratoren für gefundene Server.  

## 2. Voraussetzungen

 - IPS 5.0 oder höher
 - Logitech Media Server (getestet ab 7.9.x)

## 3. Software-Installation

 Dieses Modul ist Bestandteil der IPSSqueezeBox-Library.
   *Über das 'Modul Control' folgende URL hinzufügen:  
    `git://github.com/Nall-chan/IPSSqueezeBox.git`  

## 4. Verwendung

Nach der installation des Moduls, ist im Objektbaum unter 'Discovery Instanzen' eine Instanz [Logitech Media Server Discovery](../../LMSDiscovery/readme.md) vorhanden.  
Beim dem Öffnen der Instanz, werden alle im Netzwerk gefundenen 'Logitech Media Server' aufgelistet.  
Über das selektieren eines Servers in der Tabelle und betätigen des dazugehörigen 'Erstellen' Button, wird ein entsprechender Konfigurator in IPS angelegt.  
Mit diesem Konfigurator können dann die einzelnen Player in IPS erzeugt werden.   

## 5. Statusvariablen und Profile

Der Konfigurator besitzt keine Statusvariablen und Variablenprofile.  

## 6. WebFront

Der Konfigurator besitzt keine im WebFront darstellbaren Elemente.  

## 7. PHP-Befehlsreferenz

Der Konfigurator besitzt keine Instanz-Funktionen.  

## 8. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
