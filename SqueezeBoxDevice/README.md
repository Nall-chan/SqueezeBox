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
    1. [Allgemein](#1-allgemein)
    2. [Steuerung](#2-steuerung)
    3. [Playlist](#3-playlist)
    4. [Zufallswiedergabe](#4-zufallswiedergabe)
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
Setzt den Namen des Players auf '$Name'.  
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

---

```php
bool LSQ_Play(int $InstanzID)
bool LSQ_PlayEx(int $InstanzID, int $FadeIn)
```

---

```php
bool LSQ_Pause(int $InstanzID)
```

---

```php
bool LSQ_Stop(int $InstanzID)
```

---

```php
bool LSQ_SetVolume(int $InstanzID, int $Value)
```

---

```php
bool LSQ_SetMute(int $InstanzID, bool $Value)
```

---

```php
bool LSQ_SetPosition(int $InstanzID, int $Value)
```

---

```php
bool LSQ_SetBass(int $InstanzID, int $Value)
bool LSQ_SetTreble(int $InstanzID, int $Value)
bool LSQ_SetPitch(int $InstanzID, int $Value)
```

---

```php
bool LSQ_SetSleep(int $InstanzID, int $Seconds)
```

---

```php
bool LSQ_PreviousButton(int $InstanzID)
bool LSQ_NextButton(int $InstanzID)
```

---

```php
bool LSQ_PressButton(int $InstanzID)
```

---

```php
bool LSQ_SelectPreset(int $InstanzID, int $Value)
```

---

```php
bool LSQ_DisplayLine(int $InstanzID, string $Text, int $Duration)
bool LSQ_DisplayLineEx(int $InstanzID, string $Text, int $Duration, bool $Centered, int $Brightness)
bool LSQ_Display2Lines(int $InstanzID, string $Text1, string $Text2, int $Duration)
bool LSQ_Display2LinesEx(int $InstanzID, string $Text1, string $Text2, int $Duration, bool $Centered, int $Brightness)
```

---

```php
bool LSQ_DisplayText(int $InstanzID, string $Text1, string $Text2, int $Duration)
```

---

```php
int LSQ_GetLinesPerScreen(int $InstanzID)
```

---

```php
LSQ_GetDisplayedText(int $InstanzID)
LSQ_GetDisplayedNow(int $InstanzID)
```

### 3. Playlist

```php
LSQ_PlayUrl(int $InstanzID, string $URL)
LSQ_PlayUrlEx(int $InstanzID, string $URL, string $DisplayTitle)
```

---

```php
LSQ_PlayFavorite(int $InstanzID, string $FavoriteID)
```

---

```php
LSQ_SetShuffle(int $InstanzID, int $Value)
```

---

```php
LSQ_SetRepeat(int $InstanzID, int $Value)
```

---

```php
LSQ_GoToTrack(int $InstanzID, int $Index)
```

---

```php
LSQ_NextTrack(int $InstanzID)
LSQ_PreviousTrack(int $InstanzID)
```

---

```php
LSQ_LoadPlaylist(int $InstanzID, string $Name)
LSQ_ResumePlaylist(int $InstanzID, string $Name)
```

---

```php
LSQ_LoadPlaylistBySearch(int $InstanzID, string $Genre, string $Artist, string $Album)
```

---

```php
LSQ_LoadPlaylistByTrackTitel(int $InstanzID, string $Titel)
LSQ_LoadPlaylistByAlbumTitel(int $InstanzID, string $Titel)
LSQ_LoadPlaylistByArtistName(int $InstanzID, string $Name)
```

---

```php
LSQ_LoadPlaylistByFavoriteID(int $InstanzID, string $FavoriteID)
LSQ_LoadPlaylistByAlbumID(int $InstanzID, int $AlbumID)
LSQ_LoadPlaylistByGenreID(int $InstanzID, int $GenreID)
LSQ_LoadPlaylistByArtistID(int $InstanzID, int $ArtistID)
LSQ_LoadPlaylistByPlaylistID(int $InstanzID, int $PlaylistID)
LSQ_LoadPlaylistByFolderID(int $InstanzID, int $FolderID)
```

---

```php
LSQ_LoadPlaylistBySongIDs(int $InstanzID, string $SongIDs)
```

---

```php
LSQ_SavePlaylist(int $InstanzID, string $Name)
```

---

```php
LSQ_SaveTempPlaylist(int $InstanzID)
LSQ_LoadTempPlaylist(int $InstanzID)
```

---

```php
LSQ_AddToPlaylistByUrl(int $InstanzID, string $URL)
LSQ_AddToPlaylistByUrlEx(int $InstanzID, string $URL, string $DisplayTitle)
```

---

```php
LSQ_AddToPlaylistBySearch(int $InstanzID, string $Genre, string $Artist, string $Album)
```

---

```php
LSQ_AddToPlaylistByTrackTitel(int $InstanzID, string $Titel)
LSQ_AddToPlaylistByAlbumTitel(int $InstanzID, string $Titel)
LSQ_AddToPlaylistByArtistName(int $InstanzID, string $Name)
```

---

```php
LSQ_AddToPlaylistByFavoriteID(int $InstanzID, string $FavoriteID)
LSQ_AddToPlaylistByAlbumID(int $InstanzID, int $AlbumID)
LSQ_AddToPlaylistByGenreID(int $InstanzID, int $GenreID)
LSQ_AddToPlaylistByArtistID(int $InstanzID, int $ArtistID)
LSQ_AddToPlaylistByPlaylistID(int $InstanzID, int $PlaylistID)
LSQ_AddToPlaylistByFolderID(int $InstanzID, int $FolderID)
```

---

```php
LSQ_AddToPlaylistBySongIDs(int $InstanzID, string $SongIDs)
```

---

```php
LSQ_DeleteFromPlaylistBySearch(int $InstanzID, string $Genre, string $Artist, string $Album)
```

---

```php
LSQ_DeleteFromPlaylistByIndex(int $InstanzID, int $Position)
```

---

```php
LSQ_DeleteFromPlaylistByUrl(int $InstanzID, string $URL)
```

---

```php
LSQ_DeleteFromPlaylistByAlbumID(int $InstanzID, int $AlbumID)
LSQ_DeleteFromPlaylistByGenreID(int $InstanzID, int $GenreID)
LSQ_DeleteFromPlaylistByArtistID(int $InstanzID, int $ArtistID)
LSQ_DeleteFromPlaylistByPlaylistID(int $InstanzID, int $PlaylistID)
LSQ_DeleteFromPlaylistBySongIDs(int $InstanzID, string $SongIDs)
```

---

```php
LSQ_MoveSongInPlaylist(int $InstanzID, int $Position, int $NewPosition)
```

---

```php
LSQ_InsertInPlaylistBySearch(int $InstanzID, string $Genre, string $Artist, string $Album)
```

---

```php
LSQ_InsertInPlaylistByAlbumID(int $InstanzID, int $AlbumID)
LSQ_InsertInPlaylistByGenreID(int $InstanzID, int $GenreID)
LSQ_InsertInPlaylistByArtistID(int $InstanzID, int $ArtistID)
LSQ_InsertInPlaylistByPlaylistID(int $InstanzID, int $PlaylistID)
LSQ_InsertInPlaylistByFolderID(int $InstanzID, int $FolderID)
LSQ_InsertInPlaylistByFavoriteID(int $InstanzID, string $FavoriteID)
```

---

```php
LSQ_InsertInPlaylistBySongIDs(int $InstanzID, string $SongIDs)
```

---

```php
LSQ_PreviewPlaylistStart(int $InstanzID, string $Name)
LSQ_PreviewPlaylistStop(int $InstanzID)
```

---

```php
LSQ_ClearPlaylist(int $InstanzID)
```

---

```php
LSQ_GetPlaylistURL(int $InstanzID)
```

---

```php
LSQ_IsPlaylistModified(int $InstanzID)
```

---

```php
LSQ_GetPlaylistInfo(int $InstanzID)
```

---

```php
LSQ_AddPlaylistIndexToZappedList(int $InstanzID, int $Position)
```

---

```php
LSQ_GetSongInfoByTrackIndex(int $InstanzID, int $Index)
```

---

```php
LSQ_GetSongInfoOfCurrentPlaylist(int $InstanzID)
```

### 4. Zufallswiedergabe

```php
LSQ_StartRandomplayOfTracks(int $InstanzID)
LSQ_StartRandomplayOfAlbums(int $InstanzID)
LSQ_StartRandomplayOfArtist(int $InstanzID)
LSQ_StartRandomplayOfYear(int $InstanzID)
```

---

```php
LSQ_StopRandomplay(int $InstanzID)
```

---

```php
LSQ_RandomplaySelectAllGenre(int $InstanzID, bool $Active)
```

---

```php
LSQ_RandomplaySelectGenre(int $InstanzID, string $Genre, bool $Active)
```

## 8. Anhang

**Changlog:**  

Version 1.0:  
 - Erstes offizielles Release

## 9. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
