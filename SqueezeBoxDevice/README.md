[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-3.70-blue.svg)](https://community.symcon.de/t/modul-squeezebox-release/46937)
[![Version](https://img.shields.io/badge/Symcon%20Version-6.1%20%3E-green.svg)](https://www.symcon.de/service/dokumentation/installation/migrationen/v60-v61-q1-2022/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/SqueezeBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/SqueezeBox/actions) [![Run Tests](https://github.com/Nall-chan/SqueezeBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/SqueezeBox/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](../README.md#6-spenden)  
# SqueezeBox Player (SqueezeBoxDevice)  <!-- omit in toc -->  

Ermöglicht die Steuerung sowie die Darstellung der Zustände
von SqueezeBox Geräten in IPS, in Verbindung mit dem
Logitech Media Server.  

## Dokumentation  <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
- [6. WebFront](#6-webfront)
- [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)
  - [1. Allgemein](#1-allgemein)
  - [2. Steuerung](#2-steuerung)
  - [3. Playlist](#3-playlist)
  - [4. Zufallswiedergabe](#4-zufallswiedergabe)
  - [5. Synchronisieren](#5-synchronisieren)
- [8. Aktionen](#8-aktionen)
- [9. Anhang](#9-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

 - Steuern und Abfragen der diversen verschiedenen Zustände und Eigenschaften.  
 - Abfragen, Laden, bearbeiten und speichern von der internen  des Gerätes.  
 - Synchronisierung steuern.  
 - Fähigkeiten über das WebFront:  
    *  Modus: Play,Pause, Stop, Gruppierung 
    *  Audio: Lautstärke mit Stummschaltung, und wenn vom Gerät unterstützt, auch Tonhöhe sowie Höhen und Bass.
    *  Bedienung der 6 Preset-Tasten vom Gerät.
    *  Sleeptimer: Setzen und löschen des Timer.
    *  Playlist: Trackanwahl, nächster, vorheriger Track, Wiederholung und Zufallsmodus
    *  Darstellung der Daten zum aktuellen Track: Titel, Album, Interpret, Stilrichtung, Cover etc..
    *  Darstellen der aktuellen Playlist als Tabelle sowie auswahl eines Tracks.  

## 2. Voraussetzungen

 - IP-Symcon ab Version 6.1
 - Logitech Media Server (getestet ab 7.9.x)
 - kompatibler Player

## 3. Software-Installation  

 Dieses Modul ist Bestandteil der [SqueezeBox-Library](../README.md#3-software-installation).   

## 4. Einrichten der Instanzen in IP-Symcon

Eine einfache Einrichtung ist über den Konfigurator [Logitech Media Server Konfigurator](../LMSConfigurator/README.md) möglich.  
Bei der manuellen Einrichtung ist die Instanz im Dialog `Instanz hinzufügen` unter dem Hersteller `Logitech` zu finden.  
![Instanz hinzufügen](imgs/add1.png)  

**Konfigurationsseite:**  
![Instanz hinzufügen](imgs/conf1.png)  
![Instanz hinzufügen](imgs/conf2.png)  

|                  Name                  |    Eigenschaft    |   Typ   | Standardwert |                               Funktion                                |
| :------------------------------------: | :---------------: | :-----: | :----------: | :-------------------------------------------------------------------: |
|             IP/MAC-Adresse             |      Address      | string  |              |        MAC Adresse der Squeezebox [Format xx:xx:xx:xx:xx:xx ]         |
| Namen der Instanz automatisch anpassen |    changeName     | boolean |    false     |  Instanz automatisch umbenennen wenn der Name vom Gerät sich ändert.  |
|         Aktiviere Tiefenregler         |    enableBass     | boolean |     true     |               Statusvariablen für Tiefenregler anlegen.               |
|        Aktiviere Hochtonregler         |   enableTreble    | boolean |     true     |              Statusvariablen für Hochtonregler anlegen.               |
|        Aktiviere Tonhöhenregler        |    enablePitch    | boolean |     true     |              Statusvariablen für Tonhöhenregler anlegen.              |
|      Aktiviere Zufallswiedergabe       | enableRandomplay  | boolean |     true     |            Statusvariablen für Zufallswiedergabe anlegen.             |
|      Aktiviere Dauer in Sekunden       | enableRawDuration | boolean |    false     |             Statusvariable für Dauer in Sekunden anlegen.             |
|    Aktiviere Spielzeit in Sekunden     | enableRawPosition | integer |    false     |  Statusvariable für aktuelle Position im Track in Sekunden anlegen.   |
|          Zeige Sync-Master an          |  showSyncMaster   | boolean |     true     |           Statusvariablen für Master einer Gruppe anlegen.            |
|      Zeige Steuerung für Sync an       |  showSyncControl  | boolean |     true     |             Statusvariablen für Synchronisierung anlegen.             |
|              Größe Cover               |     CoverSize     | string  |    cover     |          Größe vom Cover:  cover  cover150x150  cover300x300          |
|     Update Interval bei Wiedergabe     |     Interval      | integer |      2       | Abstand in welchen der LMS aktuelle Daten bei der Wiedergabe liefert. |
|     Playlist als HTML-Box anlegen      |   showPlaylist    | boolean |     true     |         Aktiviert die Darstellung der Playlist als HTML-Box.          |
|          Playlist Darstellung          |       Table       | string  |   Tabelle    |            Style Eigenschaften der Playlist HTML-Tabelle.             |
|            Playlist Spalten            |      Columns      | string  |   Tabelle    |               Style Eigenschaften der Playlist Spalten.               |
|            Playlist Zeilen             |       Rows        | string  |   Tabelle    |               Style Eigenschaften der Playlist Zeilen.                |


## 5. Statusvariablen und Profile

Folgende Statusvariablen werden automatisch angelegt.  
**Statusvariablen allgemein:**  

|          Name          |   Typ   |     Ident      |                        Beschreibung                        |
| :--------------------: | :-----: | :------------: | :--------------------------------------------------------: |
|         Power          | boolean |     Power      |               Player ein- oder ausgeschaltet               |
|         Status         | integer |     Status     |             Wiedergabemodus: Play, Pause, Stop             |
|         Preset         | integer |     Preset     | Aktionsbutton für das WebFront um einen Preset auszurufen  |
|          Mute          | boolean |      Mute      |              Stummschaltung aktiv / desaktiv               |
|         Volume         | integer |     Volume     |                         Lautstärke                         |
|          Bass          | integer |      Bass      |                      Regler für Bass                       |
|     Hochtonregler      | integer |     Treble     |                     Regler für Hochton                     |
|         Pitch          | integer |     Pitch      |                    Regler für Tonhöhen                     |
|   Zufallswiedergabe    | integer |   Randomplay   |                Modus der Zufallswiedergabe                 |
|         Master         | boolean |     Master     | true wenn der Player der Master einer Synchronisierung ist |
|    Synchronisieren     | integer |      Sync      |    Bedienung für die Synchronisierung aus dem WebFront     |
|        Mischen         | integer |    Shuffle     |                 Aktuelle Playlist mischen                  |
|      Wiederholen       | integer |     Repeat     |               Aktuelle Playlist wiederholen                |
| Playlist Anzahl Tracks | integer |     Tracks     |         Aktuelle Anzahl der Tracks in der Playlist         |
|        Playlist        | string  |  Playlistname  |   Name der Playlist oder Remote-Stream, sofern vorhanden   |
|         Album          | string  |     Album      |         Album des Tracks der aktuellen Wiedergabe          |
|         Titel          | string  |     Title      |         Titel des Tracks der aktuellen Wiedergabe          |
|       Interpret        | string  |     Artist     |       Interpret des Tracks der aktuellen Wiedergabe        |
|      Stilrichtung      | string  |     Genre      |      Stilrichtung des Tracks der aktuellen Wiedergabe      |
|         Dauer          | string  |    Duration    |       Spielzeit des Tracks der aktuellen Wiedergabe        |
|   Dauer in Sekunden    | integer |  DurationRaw   | Spielzeit des Tracks der aktuellen Wiedergabe in Sekunden  |
|       Spielzeit        | string  |    Position    |           Aktuelle Postion im Track als Klartext           |
|        Position        | integer |   Position2    |            Aktuelle Postion im Track in Prozent            |
| Spielzeit in Sekunden  | integer |  PositionRaw   |           Aktuelle Position im Track in Sekunden           |
|      Signalstärke      | integer | Signalstrength |      WLAN-Signalstärke des Players, sofern vorhanden       |
|     Einschlaftimer     | integer |   SleepTimer   |           Gewählter Zeitraum für Einschlaftimer            |
|     Ausschalten in     | string  |  SleepTimeout  |                  Zeit bis zum Ausschalten                  |
|        Playlist        | string  |    Playlist    |           HTML-Box mit der Playlist des Players            |

**Profile**:

|             Name             |   Typ   | verwendet von Statusvariablen |
| :--------------------------: | :-----: | :---------------------------: |
|          LSQ.Status          | integer |            Status             |
|        LSQ.Intensity         | integer |       Alle 0-100 Slider       |
|          LSQ.Pitch           | integer |             Pitch             |
|         LSQ.Shuffle          | integer |            Shuffle            |
|          LSQ.Repeat          | integer |            Repeat             |
|          LSQ.Preset          | integer |            Preset             |
|        LSQ.SleepTimer        | integer |          SleepTimer           |
|   LSQ.Sync.\<InstanzeID\>    | integer |             Sync              |
|        LSQ.Randomplay        | integer |          Randomplay           |
| LSQ.Tracklist.\<InstanzeID\> | integer |            Tracks             |

## 6. WebFront

Die direkte Darstellung im WebFront ist möglich, es wird aber empfohlen mit Links zu arbeiten.  
![WebFront Beispiel](imgs/wf1.png)  

Hier ein Beispiel mit einer SplitPane und zwei Dummy-Instanzen (Playlist & Steuerung) welche Links zu den Statusvariablen und dem Cover enthalten.  
![WebFront Beispiel](imgs/wf2.png)  

## 7. PHP-Befehlsreferenz

Für alle Befehle gilt:  
Tritt ein Fehler auf, wird eine Warnung erzeugt.  
Dies gilt auch wenn ein übergebender Wert für einen Parameter nicht gültig ist, oder außerhalb seines zulässigen Bereiches liegt.  

### 1. Allgemein

```php
string LSQ_GetName(int $InstanzID)
```
Liefert den Namen des Players.  
Im Fehlerfall wird `false` zurückgegeben.  

---

```php
bool LSQ_SetName(int $InstanzID, string $Name)
```
Setzt den Namen des Players auf `$Name`.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_RequestState(int $InstanzID, string $Ident)
```
Fordert den Wert einer Statusvariable an.  
Es ist der Ident der Statusvariable zu übergeben.  
Es wird `true` zurückgeben wenn der Befehl vom Server bestätigt wurde,  
oder `false` im Fehlerfall.  


### 2. Steuerung

```php
bool LSQ_Power(int $InstanzID, bool $Value)
```
Schaltet das Gerät ein `true` oder aus `false`.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_Play(int $InstanzID)
bool LSQ_PlayEx(int $InstanzID, int $FadeIn)
```
Startet die Wiedergabe.  
Mit LSQ_PlayEx kann ein `$FadeIn` in Sekunden übergeben werden.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_Pause(int $InstanzID)
```
Pausiert die Wiedergabe.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_Stop(int $InstanzID)
```
Stoppt die Wiedergabe.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_SetVolume(int $InstanzID, int $Value)
```
Setzt die Lautstärke auf `$Value`.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_SetMute(int $InstanzID, bool $Value)
```
Stummschaltung aktiv `true`oder desaktiv `false`.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_SetPosition(int $InstanzID, int $Value)
```
Springt im aktuellen Track auf die Zeit in Sekunden von `$Value`.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_SetBass(int $InstanzID, int $Value)
bool LSQ_SetTreble(int $InstanzID, int $Value)
bool LSQ_SetPitch(int $InstanzID, int $Value)
```
Setzt den Bass, die Höhen oder Tonhöhen auf `$Value`. (Pitch nur bei SliMP3 & SqueezeBox1 / SB1 )  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_SetSleep(int $InstanzID, int $Seconds)
```
Aktiviert den (Ein)Schlafmodus mit der unter `$Seconds`angegeben Sekunden.  
0 deaktiviert den zuvor gesetzten Schlafmodus.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_PreviousButton(int $InstanzID)
bool LSQ_NextButton(int $InstanzID)
```
Simuliert einen Tastendruck auf den Vorwärts bzw. Rückwerts-Button des Gerätes.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_PressButton(int $InstanzID)
```
Simuliert einen Tastendruck der TODO  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_SelectPreset(int $InstanzID, int $Value)
```
Simuliert einen Tastendruck der Preset-Tasten 1-6 `$Value`.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_DisplayLine(int $InstanzID, string $Text, int $Duration)
bool LSQ_DisplayLineEx(int $InstanzID, string $Text, int $Duration, bool $Centered, int $Brightness)
bool LSQ_Display2Lines(int $InstanzID, string $Text1, string $Text2, int $Duration)
bool LSQ_Display2LinesEx(int $InstanzID, string $Text1, string $Text2, int $Duration, bool $Centered, int $Brightness)
```
TODO  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_DisplayText(int $InstanzID, string $Text1, string $Text2, int $Duration)
```
TODO  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
int LSQ_GetLinesPerScreen(int $InstanzID)
```
TODO  

---

```php
LSQ_GetDisplayedText(int $InstanzID)
LSQ_GetDisplayedNow(int $InstanzID)
```
TODO  

### 3. Playlist

```php
LSQ_PlayUrl(int $InstanzID, string $URL)
LSQ_PlayUrlEx(int $InstanzID, string $URL, string $DisplayTitle)
LSQ_PlayUrlSpecial(int $InstanzID, string $URL)
LSQ_PlayUrlSpecialEx(int $InstanzID, string $URL, string $DisplayTitle)
```

---

```php
bool LSQ_PlayFavorite(int $InstanzID, string $FavoriteID)
```
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_SetShuffle(int $InstanzID, int $Value)
```
Setzt dem Modus für die zufällige Wiedergabe. `$Value` kann die Werte 0 für aus,  
1 für den alle Titel in der Playlist, oder 2 für das die verschiedenen Alben in der Playlist enthalten.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_SetRepeat(int $InstanzID, int $Value)
```
Setzt dem Modus für Wiederholungen. `$Value` kann die Werte 0 für aus,  
1 für den aktuellen Titel, oder 2 für die aktuelle Playlist enthalten.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_GoToTrack(int $InstanzID, int $Index)
```
Springt in der Playlist auf den mit `$Index` übergebe Position.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_NextTrack(int $InstanzID)
bool LSQ_PreviousTrack(int $InstanzID)
```
Springt in der Playlist auf den vorherigen bzw. nächsten Track.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
string LSQ_LoadPlaylist(int $InstanzID, string $Name)
```
Lädt die unter `$Name` übergebene Playlist.  
Die Wiedergabe wird nicht automatisch gestartet.  
Liefert den Pfad der Playlist.  

---

```php
string LSQ_ResumePlaylist(int $InstanzID, string $Name)
```
Lädt die unter `$Name` übergebene Playlist, und springt auf den zuletzt wiedergegeben Track.  
Die Wiedergabe wird nicht automatisch gestartet.  
Liefert den Pfad der Playlist.  

---

```php
LSQ_LoadPlaylistBySearch(int $InstanzID, string $Genre, string $Artist, string $Album)
```
TODO  

---

```php
LSQ_LoadPlaylistByTrackTitel(int $InstanzID, string $Titel)
LSQ_LoadPlaylistByAlbumTitel(int $InstanzID, string $Titel)
LSQ_LoadPlaylistByArtistName(int $InstanzID, string $Name)
```
TODO  

---

```php
bool LSQ_LoadPlaylistByFavoriteID(int $InstanzID, string $FavoriteID)
bool LSQ_LoadPlaylistByAlbumID(int $InstanzID, int $AlbumID)
bool LSQ_LoadPlaylistByGenreID(int $InstanzID, int $GenreID)
bool LSQ_LoadPlaylistByArtistID(int $InstanzID, int $ArtistID)
bool LSQ_LoadPlaylistByPlaylistID(int $InstanzID, int $PlaylistID)
bool LSQ_LoadPlaylistByFolderID(int $InstanzID, int $FolderID)
```
Lädt eine Playlist bestehend aus der inm zweiten Parameter übergebene ID.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
LSQ_LoadPlaylistBySongIDs(int $InstanzID, string $SongIDs)
```
TODO  

---

```php
bool LSQ_SavePlaylist(int $InstanzID, string $Name)
```
Speichert eine Playlist unter den mit `$Name` übergebenen Namen.   
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_SaveTempPlaylist(int $InstanzID)
```
Speichert eine temporäre Playlist, welche beim Laden per LSQ_LoadTempPlaylist automatisch vom Server gelöscht wird.  
Eine zuvor nicht geladene temporäre Playlist wird dabei überschrieben.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
bool LSQ_LoadTempPlaylist(int $InstanzID)
```
Lädt eine zuvor mit LSQ_SaveTempPlaylist gespeicherte Playlist, und springt auf den zuletzt wiedergegeben Track.  
Die Wiedergabe wird nicht automatisch gestartet.  
Liefert `true` bei Erfolg, sonst `false`.  

---

```php
LSQ_AddToPlaylistByUrl(int $InstanzID, string $URL)
LSQ_AddToPlaylistByUrlEx(int $InstanzID, string $URL, string $DisplayTitle)
```
TODO  

---

```php
LSQ_AddToPlaylistBySearch(int $InstanzID, string $Genre, string $Artist, string $Album)
```
TODO  

---

```php
LSQ_AddToPlaylistByTrackTitel(int $InstanzID, string $Titel)
LSQ_AddToPlaylistByAlbumTitel(int $InstanzID, string $Titel)
LSQ_AddToPlaylistByArtistName(int $InstanzID, string $Name)
```
TODO  

---

```php
LSQ_AddToPlaylistByFavoriteID(int $InstanzID, string $FavoriteID)
LSQ_AddToPlaylistByAlbumID(int $InstanzID, int $AlbumID)
LSQ_AddToPlaylistByGenreID(int $InstanzID, int $GenreID)
LSQ_AddToPlaylistByArtistID(int $InstanzID, int $ArtistID)
LSQ_AddToPlaylistByPlaylistID(int $InstanzID, int $PlaylistID)
LSQ_AddToPlaylistByFolderID(int $InstanzID, int $FolderID)
```
TODO  

---

```php
LSQ_AddToPlaylistBySongIDs(int $InstanzID, string $SongIDs)
```
TODO  

---

```php
LSQ_DeleteFromPlaylistBySearch(int $InstanzID, string $Genre, string $Artist, string $Album)
```
TODO  

---

```php
LSQ_DeleteFromPlaylistByIndex(int $InstanzID, int $Position)
```
TODO  

---

```php
LSQ_DeleteFromPlaylistByUrl(int $InstanzID, string $URL)
```
TODO  

---

```php
LSQ_DeleteFromPlaylistByAlbumID(int $InstanzID, int $AlbumID)
LSQ_DeleteFromPlaylistByGenreID(int $InstanzID, int $GenreID)
LSQ_DeleteFromPlaylistByArtistID(int $InstanzID, int $ArtistID)
LSQ_DeleteFromPlaylistByPlaylistID(int $InstanzID, int $PlaylistID)
LSQ_DeleteFromPlaylistBySongIDs(int $InstanzID, string $SongIDs)
```
TODO  

---

```php
LSQ_MoveSongInPlaylist(int $InstanzID, int $Position, int $NewPosition)
```
TODO  

---

```php
LSQ_InsertInPlaylistBySearch(int $InstanzID, string $Genre, string $Artist, string $Album)
```
TODO  

---

```php
LSQ_InsertInPlaylistByAlbumID(int $InstanzID, int $AlbumID)
LSQ_InsertInPlaylistByGenreID(int $InstanzID, int $GenreID)
LSQ_InsertInPlaylistByArtistID(int $InstanzID, int $ArtistID)
LSQ_InsertInPlaylistByPlaylistID(int $InstanzID, int $PlaylistID)
LSQ_InsertInPlaylistByFolderID(int $InstanzID, int $FolderID)
LSQ_InsertInPlaylistByFavoriteID(int $InstanzID, string $FavoriteID)
```
TODO  

---

```php
LSQ_InsertInPlaylistBySongIDs(int $InstanzID, string $SongIDs)
```
TODO  

---

```php
LSQ_PreviewPlaylistStart(int $InstanzID, string $Name)
LSQ_PreviewPlaylistStop(int $InstanzID)
```
TODO  

---

```php
LSQ_ClearPlaylist(int $InstanzID)
```
TODO  

---

```php
LSQ_GetPlaylistURL(int $InstanzID)
```
TODO  

---

```php
LSQ_IsPlaylistModified(int $InstanzID)
```
TODO  

---

```php
LSQ_AddPlaylistIndexToZappedList(int $InstanzID, int $Position)
```
TODO  

---

```php
array LSQ_GetPlaylistInfo(int $InstanzID)
```
Liefert Informationen über die Playlist.  
**Hinweis:**
Funktioniert nur, wenn die Playlist aus den vorhandenen Server-Playlisten geladen wurde.  
Und auch nur, wenn Sie manuell am Player oder per `LSQ_LoadPlaylistByPlaylistID` geladen wurde.
Playlisten welche mit ihrem Namen über `LSQ_LoadPlaylist` geladen wurden, liefern leider keine Informationen.  

**Array:**  

|  Index   |   Typ   |             Beschreibung              |
| :------: | :-----: | :-----------------------------------: |
|    Id    | integer | UID der Playlist in der LMS-Datenbank |
|   Name   | string  |           Name der Playlist           |
| Modified | boolean | `true` wenn Playlist verändert wurde  |
|   Url    | string  |           Pfad der Playlist           |


---

```php
array LSQ_GetSongInfoByTrackIndex(int $InstanzID, int $Index)
```
Liefert Informationen über den Song mit dem `$Index` der aktuellen Playlist.  
Wird als `$Index` 0 übergeben, so wird der aktuelle Song genutzt.  

**Array:**  

|      Index       |   Typ   |            Beschreibung             |
| :--------------: | :-----: | :---------------------------------: |
|        Id        | integer | UID der Datei in der LMS-Datenbank  |
|      Title       | string  |                Titel                |
|      Genre       | string  |                Genre                |
|      Album       | string  |                Album                |
|      Artist      | string  |              Interpret              |
|     Duration     | integer |          Länge in Sekunden          |
|       Disc       | integer |          Aktuelles Medium           |
|    Disccount     | integer |  Anzahl aller Medien dieses Albums  |
|     Bitrate      | string  |         Bitrate in Klartext         |
|     Tracknum     | integer |        Tracknummer im Album         |
|       Url        | string  |          Pfad der Playlist          |
|     Album_id     | integer | UID des Album in der LMS-Datenbank  |
| Artwork_track_id | string  | UID des Cover in der LMS-Datenbank  |
|     Genre_id     | integer | UID des Genre in der LMS-Datenbank  |
|    Artist_id     | integer | UID des Artist in der LMS-Datenbank |
|       Year       | integer |  Jahr des Song, soweit hinterlegt   |
|   Remote_title   | string  |          Titel des Stream           |

---

```php
array LSQ_GetSongInfoOfCurrentPlaylist(int $InstanzID)
```
Liefert Informationen über alle Songs in der Playlist.  
Mehrdimensionales Array, wobei der erste Index der Position in der Playlist entspricht.  

**Array:**  

|      Index       |   Typ   |            Beschreibung             |
| :--------------: | :-----: | :---------------------------------: |
|        Id        | integer | UID der Datei in der LMS-Datenbank  |
|      Title       | string  |                Titel                |
|      Genre       | string  |                Genre                |
|      Album       | string  |                Album                |
|      Artist      | string  |              Interpret              |
|     Duration     | integer |          Länge in Sekunden          |
|       Disc       | integer |          Aktuelles Medium           |
|    Disccount     | integer |  Anzahl aller Medien dieses Albums  |
|     Bitrate      | string  |         Bitrate in Klartext         |
|     Tracknum     | integer |        Tracknummer im Album         |
|       Url        | string  |          Pfad der Playlist          |
|     Album_id     | integer | UID des Album in der LMS-Datenbank  |
| Artwork_track_id | string  | UID des Cover in der LMS-Datenbank  |
|     Genre_id     | integer | UID des Genre in der LMS-Datenbank  |
|    Artist_id     | integer | UID des Artist in der LMS-Datenbank |
|       Year       | integer |  Jahr des Song, soweit hinterlegt   |
|   Remote_title   | string  |          Titel des Stream           |


### 4. Zufallswiedergabe

```php
LSQ_StartRandomplayOfTracks(int $InstanzID)
LSQ_StartRandomplayOfAlbums(int $InstanzID)
LSQ_StartRandomplayOfArtist(int $InstanzID)
LSQ_StartRandomplayOfYear(int $InstanzID)
```
TODO  

---

```php
LSQ_StopRandomplay(int $InstanzID)
```
TODO  

---

```php
LSQ_RandomplaySelectAllGenre(int $InstanzID, bool $Active)
```
TODO  

---

```php
LSQ_RandomplaySelectGenre(int $InstanzID, string $Genre, bool $Active)
```
TODO  

### 5. Synchronisieren  

```php
bool LSQ_SetSync(int $InstanzID, int $InstanzIDofMaster)
```
Synchronisiert die `$InstanzeID` mit der Playerintanz welche in `InstanzIDofMaster` übergeben wurde.  
Liefert `true` bei Erfolg, sonst `false`.  

---  

```php
bool LSQ_SetUnSync(int $InstanzID)
```
Beendet eine Synchronisierung der `$InstanzeID`.  
Liefert `true` bei Erfolg, sonst `false`.  

---  

```php
array LSQ_GetSync(int $InstanzID)
```
Liefert ein Array über alle InstanzIDs welche mit dieser `$InstanzID` synchronisiert sind.  

---  

## 8. Aktionen

// TODO

## 9. Anhang

### 1. Changelog

[Changelog der Library](../README.md#3-changelog)

### 2. Spenden

Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

  PayPal:  
<a href="https://www.paypal.com/donate?hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>  

  Wunschliste:  
<a href="https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share" target="_blank"><img src="https://upload.wikimedia.org/wikipedia/commons/4/4a/Amazon_icon.svg" border="0" width="100"/></a>  

## 10. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
