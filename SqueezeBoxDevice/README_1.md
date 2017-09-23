# SqueezeBox Player (SqueezeBoxDevice)
Ermöglich die Steuerung sowie die Darstellung der Zustände
von SqueezeBox Geräten in IPS, in Verbindung mit dem
Logitech Media Server.

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

 - Steuern und Abfragen der diversen verschiedenen Zustände und Eigenschaften.  
 - Abfragen, Laden, bearbeiten und speichern von der internen Playliste des Gerätes.  
 - Syncronisierung steuern.  
 - Fähigkeiten über das WebFront:  
    *  Modus: Play,Pause, Stop
    *  Audio: Lautstärke mit Stummschaltung, und wenn vom Gerät unterstützt, auch Tonhöhe sowie Höhen und Bass.
    *  Bedienung der 6 Preset-Tasten vom Gerät
    *  Sleeptimer: Setzen und löschen des Timer.
    *  Playlist: Trackanwahl, nächster, vorheriger Track, Widerholung und Zufallsmodus
    *  Darstellung der Daten zum aktuellen Track: Titel, Album, Interpret, Stilrichtung, Cover etc..
    *  Darstellen der Server-Playlisten sowie laden derselben auf Player.

## 2. Voraussetzungen

 - IPS 4.3 oder höher
 - Logitech Media Server (getestet ab 7.9.x)
 - kompatibler Player

## 3. Installation

 Dieses Modul ist Bestandteil der IPSSqueezeBox-Library.  
   *Über das 'Modul Control' folgende URL hinzufügen:  
    `git://github.com/Nall-chan/IPSSqueezeBox.git` 

## 4. Einrichten der Instanzen in IP-Symcon

Eine einfache Einrichtung ist über den Konfigurator [Logitech Media Server Konfigurator](../../LMSConfigurator/readme.md) möglich.  
Bei der manuellen Einrichtung ist die Instanz im Dialog 'Instanz hinzufügen' unter dem Hersteller 'Logitech' zufinden.  
![Instanz hinzufügen](imgs/add.png)  

**Konfigurationsseite:**  
![Instanz hinzufügen](imgs/conf.png)  

| Name                                   | Eigenschaft       | Typ     | Standardwert | Funktion                                                              |
| :------------------------------------: | :---------------: | :-----: | :----------: | :-------------------------------------------------------------------: |
| IP/MAC-Adresse                         | Address           | string  |              | MAC Adresse der Squeezebox [Format xx:xx:xx:xx:xx:xx ]                |
| Namen der Instanz automatisch anpassen | changeName        | boolean | false        | Instanz automatisch umbenennen wenn der Name vom Gerät sich ändert.   |
| Aktiviere Tiefenregler                 | enableBass        | boolean | true         | Statusvariablen für Tiefenregler anlegen.                             |
| Aktiviere Hochtonregler                | enableTreble      | boolean | true         | Statusvariablen für Hochtonregler anlegen.                            |
| Aktiviere Tonhöhenregler               | enablePitch       | boolean | true         | Statusvariablen für Tonhöhenregler anlegen.                           |
| Aktiviere Zufallswiedergabe            | enableRandomplay  | boolean | true         | Statusvariablen für Zufallswiedergabe anlegen.                        |
| Größe Cover                            | CoverSize         | string  | cover        | Größe vom Cover:  cover  cover150x150  cover300x300                   |
| Update Interval bei Wiedergabe         | Interval          | integer | 2            | Abstand in welchen der LMS aktuelle Daten bei der Wiedergabe liefert. |
| Playlist als HTML-Box anlegen          | showPlaylist      | boolean | true         | Aktiviert die Darstellung der Playlist als HTML-Box.                  |
| Playlist Darstellung                   | Table             | string  | Tabelle      | Style Eigenschaften der Playlist HTML-Tabelle.                        |
| Playlist Spalten                       | Columns           | string  | Tabelle      | Style Eigenschaften der Playlist Spalten.                             |
| Playlist Zeilen                        | Rows              | string  | Tabelle      | Style Eigenschaften der Playlist Zeilen.                              |


## 5. Statusvariablen und Profile

Folgende Statusvariablen werden automatisch angelegt.
**Statusvariablen allgemein:**  

| Name                   | Typ     | Ident          | Beschreibung                                              |
| :--------------------: | :-----: | :------------: | :-------------------------------------------------------: |
| Player verbunden       | boolean | Connected      | True wenn der Player mit dem Server verbunden ist         |
| Power                  | boolean | Power          | Player ein- oder ausgeschaltet                            |
| Status                 | integer | Status         | Wiedergabemodus: Play, Pause, Stop                        |
| Preset                 | integer | Preset         | Aktionsbutton für das WebFront um einen Preset auszurufen |
| Mute                   | boolean | Mute           | Stummschaltung aktiv / deaktiv                            |
| Volume                 | integer | Volume         | Lautstärke                                                |
| Bass                   | integer | Bass           | Regler für Bass                                           |
| Hochtonregler          | integer | Treble         | Regler für Hochton                                        |
| Pitch                  | integer | Pitch          | Regler für Tonhöhen                                       |
| Zufallswiedergabe      | integer | Randomplay     | Modus der Zufallswiedergabe                               |
| Mischen                | integer | Shuffle        | Aktuelle Playlist mischen                                 |
| Wiederholen            | integer | Repeat         | Aktuelle Playlist wiederholen                             |
| Playlist Anzahl Tracks | integer | Tracks         | Aktuelle Anzahl der Tracks in der Playlist                |
| Playlist               | string  | Playlistname   | Name der Playlist oder Remote-Stream, sofern vorhanden    |
| Album                  | string  | Album          | Album des Tracks der aktuellen Wiedergabe                 |
| Titel                  | string  | Title          | Titel des Tracks der aktuellen Wiedergabe                 |
| Interpret              | string  | Artist         | Interpret des Tracks der aktuellen Wiedergabe             |
| Stilrichtung           | string  | Genre          | Stilrichtung des Tracks der aktuellen Wiedergabe          |
| Dauer                  | string  | Duration       | Spielzeit des Tracks der aktuellen Wiedergabe             |
| Spielzeit              | string  | Position       | Aktuelle Postion im Track als Klartext                    |
| Position               | integer | Position2      | Aktuelle Postion im Track in Prozent                      |
| Signalstärke           | integer | Signalstrength | WLAN-Signalstärke des Players, sofern vorhanden           |
| Einschlaftimer         | integer | SleepTimer     | Gewählter Zeitraum für Einschlaftimer                     |
| Ausschalten in         | string  | SleepTimeout   | Zeit bis zum Auschalten                                   |
| Playlist               | string  | Playlist       | HTML-Box mit der Playlist des Players                     |

**Profile**:

| Name            | Typ     | verwendet von Statusvariablen |
| :-------------: | :-----: | :---------------------------: |
| LSQ.Status      | integer | Status                        |
| LSQ.Intensity   | integer | Alle 0-100 Slider             |
| LSQ.Pitch       | integer | Pitch                         |
| LSQ.Shuffle     | integer | Shuffle                       |
| LSQ.Repeat      | integer | Repeat                        |
| LSQ.Preset      | integer | Preset                        |
| LSQ.SleepTimer  | integer | SleepTimer                    |
| LSQ.Randomplay  | integer | Randomplay                    |
| LSQ.Tracklist.* | integer | Tracks                        |


## 6. WebFront

Die direkte Darstellung im WebFront ist möglich, es wird aber empfohlen mit Links zu arbeiten.  
![WebFront Beispiel](imgs/wf1.png)  

Hier ein Beispiel mit einer Splitpane und zwei Dummy-Instanzen (Playlist & Steuerung) welche Links zu den Statusvariablen und dem Cover enthalten.  
![WebFront Beispiel](imgs/wf2.png)  

## 7. PHP-Befehlsreferenz

Für alle Befehle gilt:  
Tritt ein Fehler auf, wird eine Warnung erzeugt.  
Dies gilt auch wenn ein übergebender Wert für einen Parameter nicht gültig ist, oder außerhalb seines zulässigen Bereiches liegt.  

#### 1. Steuerung:

**VERALTET**  

Alle Befehle liefern einen `boolean` als Rückgabewert.  
`true` wenn der Befehl vom Server bestätigt wurde.  
Wird der Befehl nicht bestätigt, so ist dise ein Fehler (Exception wird erzeugt).

`boolean LSQ_Power (integer $InstanzID, boolean $Value)`  
Schaltet das Gerät ein `true` oder aus `false`.  

`boolean LSQ_SelectPreset(integer $InstanzID, integer $Value)`  
Simuliert einen Tastendruck der Preset-Tasten 1-6 `$Value`.  

`boolean LSQ_Play (integer $InstanzID)`  
Startet die Wiedergabe.  

`boolean LSQ_Pause (integer $InstanzID)`  
Pausiert die Wiedergabe.  

`boolean LSQ_Stop (integer $InstanzID)`  
Stoppt die Wiedergabe.  

#### 2. Playlist:

**VERALTET**  

Wird der Befehl nicht bestätigt, so ist dise ein Fehler (Exception wird erzeugt).  
Wird ein übergebener Parameter nicht auf dem Server gefunden, so wird ebenfalls ein Fehler erzeugt.  

`string LSQ_LoadPlaylist (integer $InstanzID, string $Name)`  
Lädt die unter `$Name`übergebene Playlist.  
Die Wiedergabe wird nicht automatisch gestartet.  
Liefert den Pfad der Playlist.  

`string LSQ_ResumePlaylist(integer $InstanzID, string $Name)`  
Lädt die unter `$Name`übergebene Playlist, und springt auf den zuletzt wiedergegeben Track.  
Die Wiedergabe wird nicht automatisch gestartet.  
Liefert den Pfad der Playlist.  

`boolean LSQ_LoadTempPlaylist (integer $InstanzID)`  
Lädt eine zuvor mit LSQ_SaveTempPlaylist gespeicherte Playlist, und springt auf den zuletzt wiedergegeben Track.  
Die Wiedergabe wird nicht automatisch gestartet.  
Liefert `true`bei Erfolg.  

`boolean LSQ_LoadPlaylistByAlbumID (integer $InstanzID, integer $AlbumID)`  
Lädt eine Playlist bestehend aus der in `$AlbumID` übergeben ID eines Albums.  
Liefert `true`bei Erfolg.  

`boolean LSQ_LoadPlaylistByGenreID (integer $InstanzID, integer $GenreID)`  
Lädt eine Playlist bestehend aus der in `$GenreID` übergeben ID eines Genres.  
Liefert `true`bei Erfolg.  

`boolean LSQ_LoadPlaylistByArtistID (integer $InstanzID, integer $ArtistID)`  
Lädt eine Playlist bestehend aus der in `$ArtistID` übergeben ID eines Artist.  
Liefert `true`bei Erfolg.  

`boolean LSQ_LoadPlaylistByPlaylistID (integer $InstanzID, integer $PlaylistID)`  
Lädt eine Playlist bestehend aus der in `$PlaylistID` übergeben ID einer Playlist.  
Liefert `true`bei Erfolg.  

`array LSQ_GetPlaylistInfo(integer $InstanzID)`  
Liefert Informationen über die Playlist.  
**Hinweis:**
Funktioniert nur, wenn wirklich eine Playlist aus den vorhandnene Server-Playlisten geladen wurde.  
Und auch nur, wenn Sie manuell am Player oder per `LSQ_LoadPlaylistByPlaylistID` geladen wurde.
Playlisten welche mit ihrem Namen über `LSQ_LoadPlaylist` geladen wurden, liefern leider keine Informationen.  

**Array:**  

| Index     | Typ     | Beschreibung                          |
| :-------: | :-----: | :-----------------------------------: |
| Id        | integer | UID der Playlist in der LMS-Datenbank |
| Name      | string  | Name der Playlist                     |
| Modified  | boolean | `true` wenn Playlist verändert wurde  |
| Url       | string  | Pfad der Playlist                     |

`array LSQ_GetSongInfoOfCurrentPlaylist (integer $InstanzID)`  
Liefert Informationen über alle Songs in der Playlist.  
Mehrdimensionales Array, wobei der erste Index der Trackposition entspricht.  

**Array:**  

| Index            | Typ     | Beschreibung                        |
| :--------------: | :-----: | :---------------------------------: |
| Id               | integer | UID der Datei in der LMS-Datenbank  |
| Title            | string  | Titel                               |
| Genre            | string  | Genre                               |
| Album            | string  | Album                               |
| Artist           | string  | Interpret                           |
| Duration         | integer | Länge in Sekunden                   |
| Disc             | integer | Aktuelles Medium                    |
| Disccount        | integer | Anzahl aller Medien dieses Albums   |
| Bitrate          | string  | Bitrate in Klartext                 |
| Tracknum         | integer | Tracknummer im Album                |
| Url              | string  | Pfad der Playlist                   |
| Album_id         | integer | UID des Album in der LMS-Datenbank  |
| Artwork_track_id | string  | UID des Cover in der LMS-Datenbank  |
| Genre_id         | integer | UID des Genre in der LMS-Datenbank  |
| Artist_id        | integer | UID des Artist in der LMS-Datenbank |
| Year             | integer | Jahr des Song, soweit hinterlegt    |
| Remote_title     | string  | Titel des Stream                    |

`array LSQ_GetSongInfoByTrackIndex (integer $InstanzID, integer $Index)`  
Liefert Informationen über den Song mit dem `$Index` der aktuellen Playlist.  
Wird als `$Index` 0 übergeben, so wird der aktuelle Song genutzt.  

**Array:**  

| Index            | Typ     | Beschreibung                        |
| :--------------: | :-----: | :---------------------------------: |
| Id               | integer | UID der Datei in der LMS-Datenbank  |
| Title            | string  | Titel                               |
| Genre            | string  | Genre                               |
| Album            | string  | Album                               |
| Artist           | string  | Interpret                           |
| Duration         | integer | Länge in Sekunden                   |
| Disc             | integer | Aktuelles Medium                    |
| Disccount        | integer | Anzahl aller Medien dieses Albums   |
| Bitrate          | string  | Bitrate in Klartext                 |
| Tracknum         | integer | Tracknummer im Album                |
| Url              | string  | Pfad der Playlist                   |
| Album_id         | integer | UID des Album in der LMS-Datenbank  |
| Artwork_track_id | string  | UID des Cover in der LMS-Datenbank  |
| Genre_id         | integer | UID des Genre in der LMS-Datenbank  |
| Artist_id        | integer | UID des Artist in der LMS-Datenbank |
| Year             | integer | Jahr des Song, soweit hinterlegt    |
| Remote_title     | string  | Titel des Stream                    |

Alle anderen Befehle liefern einen `boolean` als Rückgabewert.  
`true` wenn der Befehl vom Server bestätigt wurde.  
Wird der Befehl nicht bestätigt, so ist die ein Fehler (Exception wird erzeugt).  

`boolean LSQ_SavePlaylist (integer $InstanzID, string $Name)`  
Speichert eine Playlist unter den mit `$Name` übergebenen Namen.  

`boolean LSQ_SaveTempPlaylist (integer $InstanzID)`  
Speichert eine temporäre Playlist, welche beim Laden per LSQ_LoadTempPlaylist automatisch vom Server gelöscht wird.  
Eine zuvor nicht geladene temporäre Playlist wird dabei überschrieben.

`boolean LSQ_PlayTrack (integer $InstanzID, integer $Index)`  
Springt in der Playlist auf den mit `$Index` übergebe Position.  

`boolean LSQ_NextTrack (integer $InstanzID)`  
Springt in der Playlist auf den nächsten Track.  

`boolean LSQ_PreviousTrack (integer $InstanzID)`  
Springt in der Playlist auf den vorherigen Track.  

`boolean LSQ_NextButton (integer $InstanzID)`  
Simuliert einen Tastendruck auf den Vorwärts-Button des Gerätes.  

`boolean LSQ_PreviousButton (integer $InstanzID)`  
Simuliert einen Tastendruck auf den Rückwerts-Button des Gerätes.  

#### 3. Setzen von Eigenschaften:

**VERALTET**  

Alle LSQ_Set* - Befehle liefern einen `boolean` als Rückgabewert.  
`true` wenn der gleiche Wert vom Server bestätigt wurde.  
`false` wenn der bestätigte Wert abweicht.  
Wird der Befehl nicht bestätigt, so ist dies ein Fehler (Exception wird erzeugt).  

`boolean LSQ_SetBass (integer $InstanzID, integer $Value)`  
Setzt den Bass auf `$Value`. (Nur SliMP3 & SqueezeBox1 / SB1 )  

`boolean LSQ_SetMute (integer $InstanzID, boolean $Value)`  
Stummschaltung aktiv `true`oder deaktiv `false`.  

`boolean LSQ_SetName (integer $InstanzID, string $Name)`  
Setzt den Namen des Gerätes auf `$Name`.  

`boolean LSQ_SetPitch (integer $InstanzID, integer $Value)`  
Setzt den Tonhöhe auf `$Value`. (Nur SqueezeBox1 / SB1 )  

`boolean LSQ_SetPosition (integer $InstanzID, integer $Value)`  
Springt im aktuellen Track auf die Zeit in Sekunden von `$Value`.  

`boolean LSQ_SetRepeat (integer $InstanzID, integer $Value)`  
Setzt dem Modus für Wiederholungen. `$Value` kann die Werte 0 für aus,  
1 für den aktuellen Titel, oder 2 für die aktuelle Playlist enthalten.  

`boolean LSQ_SetShuffle (integer $InstanzID, integer $Value)`  
Setzt dem Modus für die zufällige Wiedergabe. `$Value` kann die Werte 0 für aus,  
1 für den alle Titel in der Playlist, oder 2 für das die verschiednen Alben in der Playlist enthalten.  

`boolean LSQ_SetTreble (integer $InstanzID, integer $Value)`  
Setzt die Höhen auf `$Value`. (Nur SliMP3 & SqueezeBox1 / SB1 )  

`boolean LSQ_SetVolume (integer $InstanzID, integer $Value)`  
Setzt die Lautstärke auf `$Value`.  

`boolean LSQ_SetSleep(integer $InstanzID, integer $Seconds)`  
Aktiviert den (Ein)Schlafmodus mit der unter `$Seconds`angegeben Sekunden.  
0 deaktiviert den zuvor gesetzten Schlafmodus.  

#### 4. Lesen von Eigenschaften:

**VERALTET**  


Alle LSQ_Get* - Befehle liefern einen jeweils beschriebenen Rückgabewert.  
Antwortet das Gerät nicht auf die Anfrage, so ist dies ein Fehler und eine Exception wird erzeugt.  

`integer LSQ_GetBass (integer $InstanzID)`  
Liefert den aktuellen Wert vom Bass. (Nur SliMP3 & SqueezeBox1 / SB1 )  

`boolean LSQ_GetMute (integer $InstanzID)`  
Liefert `true` wenn Stummschaltung aktiv ist. Sonst `false`.  

`string LSQ_GetName (integer $InstanzID)`  
Liefert den aktuellen Names des Gerätes.  

`integer LSQ_GetPitch (integer $InstanzID)`  
Liefert den aktuellen Wert der eingestellten Tonhöhe. (Nur SqueezeBox1 / SB1 )  

`integer LSQ_GetPosition (integer $InstanzID)`  
Liefert die Zeit in Sekunden welche vom aktuellen Track schon gespielt wurde.  

`integer LSQ_GetRepeat (integer $InstanzID)`  
Liefert den aktuellen Modus für Wiederholungen. Es werden die Werte 0 für aus,  
1 für den aktuellen Titel, oder 2 für das aktuelle Album/Playlist gemeldet.  

`integer LSQ_GetShuffle (integer $InstanzID)`  
Liefert den aktuellen Modus für sie zufällige Wiedergabe. Es werden die Werte 0 für aus,  
1 für den aktuellen ?Titel?, oder 2 für das aktuelle Album/Playlist gemeldet.  

`integer LSQ_GetTreble (integer $InstanzID)`  
Liefert den aktuellen Wert der eingestellten Tonhöhe. (Nur SliMP3 & SqueezeBox1 / SB1 )  

`integer LSQ_GetVolume (integer $InstanzID)`  
 Liefert den aktuellen Wert der Lautstärke.  

`integer LSQ_GetSleep(integer $InstanzID)`  
Liefert die verbleibende Zeit bis zum ausschalten des Gerätes bei aktivem Schlafmodus.  
Ist der Schlafmodus nicht aktiv, wird 0 gemeldet.  


#### 5. Syncronisieren:

**VERALTET**  

Alle LSQ_Set* - Befehle liefern einen `boolean` als Rückgabewert.  
`true` wenn der gleiche Wert vom Server bestätigt wurde.  
`false` wenn der bestätigte Wert abweicht.  
Wird der Befehl nicht bestätigt, so ist dies ein Fehler (Exception wird erzeugt).  

`boolean LSQ_SetSync(integer $InstanzID, integer $SlaveInstanzID)`  
`$SlaveInstanzID` wird als Client der `$InstanzID` zugeordnet.

`boolean LSQ_SetUnSync(integer $InstanzID)`  
Löst `$InstanzID` aus der Syncronisierung von dem Master.

Alle LSQ_Get* - Befehle liefern einen jeweils beschriebenen Rückgabewert.  
Antwortet das Gerät nicht auf die Anfrage, so ist dies ein Fehler und eine Exception wird erzeugt.  

`mixed (array or boolean) LSQ_GetSync(integer $InstanzID)`  
Liefert alle InstanzIDs der mit `$InstanzID` gesyncten Geräte als Array.  
`false` wenn kein Sync aktiv ist.  

## 8. Anhang

**Changlog:**  

Version 1.0:  
 - Erstes offizielles Release

## 9. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativec
