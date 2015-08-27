# IPSSqueezeBox
Ermöglich die Steuerung sowie die Darstellung der Zustände
von SqueezeBox Geräten in IPS, in Verbindung mit dem
Logitech Media Server.

## Doku:

### Funktionsreferenz LMSSplitter / Logitech Media Server:
Für alle Befehle gilt: Tritt ein Fehler auf, wird eine Exception geworfen.
Dies gilt auch wenn ein Parameter nicht gültig ist, oder z.B. ein Index nicht in der Datenbank gefunden wurde.  

#### Server:

`string LMS_GetVersion(integer $InstanzID)`  
Liefert die aktuell installierte Version des Logitech Media-Server.

`boolean LMS_Rescan(integer $InstanzID)`  
Startet einen schnellen Rescan der Library.
Liefert `true` wenn Rescan gestartet wurde.

`boolean LMS_GetRescanProgress(integer $InstanzID)`  
Prüft ob aktuell ein Rescan läuft `true`, sonst `false`.

#### Datenbank:

`array LMS_GetLibaryInfo (integer $InstanzID)`  
Liefert Informationen über die Datenbank des LMS.  

**Array:**  

| Index   | Typ     | Beschreibung                |
| :-----: | :-----: | :-------------------------: |
| Genres  | integer | Anzahl verschiedener Genres |
| Artists | integer | Anzahl der Interpreten      |
| Albums  | integer | Anzahl der Alben            |
| Songs   | integer | Anzahl aller Titel          |

`array LMS_GetSongInfoByFileID (integer $InstanzID, integer $FileID)`  
Liefert Informationen über eine in `$FileID` übergebene Datei.  

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
  
`array LMS_GetSongInfoByFileURL (integer $InstanzID, string $FileURL)`  

Liefert Informationen über eine in `$FileURL` übergebene Datei.  

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


#### Player:

`integer LMS_GetNumberOfPlayers(integer $InstanzID)`  
Fragt die aktuelle Anzahl aller bekannten Player vom Server ab.
**Hinweis:**
    Der Server 'vergisst' Player welche komplett vom Stromnetz/Netzwerk getrennt wurden.
    Somit muss die Anzahl nicht mit allen registrieren Geräten übereinstimmen.

`array LMS_CreateAllPlayer(integer $InstanzID)`  
    Erzeugt und konfiguriert alle noch nicht in IPS vorhandenen SqueezeBox-Devices,   
    welche der Server aktuell kennt.  
    Das Array enthält alle erzeugten Instanzen.  

`array LMS_GetPlayerInfo(integer $InstanzID, integer $Index)`  
Liefert Infomationen über ein Gerät.  

**Array:**  

| Index       | Typ     | Beschreibung                                      |
| :---------: | :-----: | :-----------------------------------------------: |
| Playerindex | integer | Forlaufender Index                                |
| Playerid    | string  | MAC oder IP-Adresse                               |
| Uuid        | string  | 32-stellige eindeutige Kennung                    |
| Ip          | string  | IP-Adresse und Port                               |
| Name        | string  | Name des Gerätes                                  |
| Model       | string  | Model des Gerätes                                 |
| Isplayer    | integer | 1 wenn Gerät der SqueezeBox-Familie               |
| Displaytype | string  | Typ vom verbauten Display                         |
| Canpoweroff | integer | 1 wenn Gerät Standby unterstützt                  |
| Connected   | integer | 1 wenn Gerät aktuell mit dem Server verbunden ist |

### Funktionsreferenz LSQDevice / SqueezeboxDevice:
Für alle Befehle gilt: Tritt ein Fehler auf, wird eine Exception geworfen.

#### Steuerung:
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

#### Playlist:
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

#### Setzen von Eigenschaften:

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

#### Lesen von Eigenschaften:

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


#### Syncronisieren:

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

### Funktionsreferenz SqueezeboxBattery:

**Wichtig**:  
Getestet nur mit der SqueezeBox Radio und UE (mit SqueezeBox Firmware)!  
Damit der Status des Akku und Ladeteiles von der SqueezeBox abgefragt werden kann,
ist es notwenig den SSH-Zugang auf den Geräten zu aktivieren.

`boolean LSQB_RequestState(integer $InstanzID)`  
Startet eine Statusabfrage der Instanz.  
Bei Erfolg wird `true` zurück gegeben, sonst `false`.  

### Konfiguration:

#### LMSSplitter:

| Eigenschaft | Typ     | Standardwert | Funktion                           |
| :---------: | :-----: | :----------: | :--------------------------------: |
| Open        | boolean | true         | Verbindung zum LMS aktiv / deaktiv |
| Host        | string  |              | Adresse des LMS                    |
| Port        | integer | 9090         | CLI-Port des LMS                   |
| Webport     | integer | 9000         | Port des LMS-Webserver             |


#### LSQDevice:  

| Eigenschaft | Typ     | Standardwert | Funktion                                                              |
| :---------: | :-----: | :----------: | :-------------------------------------------------------------------: |
| Address     | string  |              | MAC [inkl. : ] bei SqueezeBox-Geräten IP-Adresse bei Anderen          |
| CoverSize   | string  | cover        | Größe vom Cover:  cover  cover150x150  cover300x300                   |
| Interval    | integer | 2            | Abstand in welchen der LMS aktuelle Daten bei der Wiedergabe liefert. |

#### SqueezeboxBattery:  

| Eigenschaft | Typ     | Standardwert | Funktion                                                                |
| :---------: | :-----: | :----------: | :---------------------------------------------------------------------: |
| Address     | string  |              | IP-Adresse der SqueezeBox                                               |
| Password    | string  | 1234         | root-Passwort der SqueezeBox. Standard-Passwort ist 1234                |
| Interval    | integer | 30           | Abstand in welchen der Status abgefragt werden soll (<30 nicht möglich) |


### GUIDs und Datenaustausch:

#### LMSSplitter:

GUID: `{96A9AB3A-2538-42C5-A130-FC34205A706A}`  

**Datenempfang vom LSQDevice:**  
Interface-GUI:`{EDDCCB34-E194-434D-93AD-FFDF1B56EF38}`  
Objekt vom Typ `LSQData`  

| Eigenschaft  | Typ     | Funktion                            |
| :----------: | :-----: | :---------------------------------: |
| Address      | string  | Empfänger Adresse                   |
| Command      | mixed   | String oder Array mit den Commandos |
| Value        | mixed   | String oder Array mit den Werten    |
| needResponse | boolean | true wenn Gerät antworten muss      |


#### LSQDevice:  

GUID: `{118189F9-DC7E-4DF4-80E1-9A4DF0882DD7}`  

**Datenempfang vom LMSSplitter:**  
Interface-GUI:`{CB5950B3-593C-4126-9F0F-8655A3944419}`  
Objekt vom Typ `LMSResponse`  

| Eigenschaft | Typ     | Funktion                                                              |
| :---------: | :-----: | :-------------------------------------------------------------------: |
| Device      | enum    | 1 = Gerät mit MAC-Adresse, 2 = Gerät mit IP-Adresse                   |
| MAC         | string  | Absender MAC-Adresse                                                  |
| IP          | string  | Absender IP-Adresse                                                   |
| Data        | array   | Array mit allen empfangenen Roh-Daten vom Device                      |

#### SqueezeboxBattery:

GUID: `{718158BB-B247-4A71-9440-9C2FF1378752}`  
