# IPSSqueezeBox
Ermöglich die Steuerung und Darstellung der Zustände
von SqueezeBox Geräten in IPS, in Verbindung mit dem
Logitech Media Server.

## Doku:

### Funktionsreferenz LMSSplitter / Logitech Media Server:
Für alle Befehle gilt: Tritt ein Fehler auf, wird eine Exception geworfen.

#### Datenbank:

**array LMS_GetLibaryInfo (integer $InstanzID)**  

| Index   | Typ     | Beschreibung                |
| :-----: | :-----: | :-------------------------: |
| Genres  | integer | Anzahl verschiedener Genres |
| Artists | integer | Anzahl der Interpreten      |
| Albums  | integer | Anzahl der Alben            |
| Songs   | integer | Anzahl aller Titel          |


*mehr fehlt noch...*

### Funktionsreferenz LSQDevice / SqueezeboxDevice:
Für alle Befehle gilt: Tritt ein Fehler auf, wird eine Exception geworfen.

#### Steuerung:
Alle Befehle liefern einen boolean als Rückgabewert.
true wenn der Befehl vom Server bestätigt wurde.
Wird der Befehl nicht bestätigt, so ist die ein Fehler (Exception wird erzeugt).

**boolean LSQ_Power (integer $InstanzID, boolean $Value)  
boolean LSQ_SelectPreset(integer $InstanzID, integer $Value)  
boolean LSQ_Play (integer $InstanzID)  
boolean LSQ_Pause (integer $InstanzID)  
boolean LSQ_Stop (integer $InstanzID)**

#### Playlist:

**string LSQ_LoadPlaylist (integer $InstanzID, string $Name)  
array LSQ_GetSongInfoOfCurrentPlaylist (integer $InstanzID)  
array LSQ_GetSongInfoByTrackIndex (integer $InstanzID, integer $Index)**  
Index kann 0 für aktueller Titel oder Index der Playlist sein.  

| Index     | Typ     | Beschreibung                       |
| :-------: | :-----: | :--------------------------------: |
| Duration  | integer | Länge in Sekunden                  |
| Id        | integer | UID der Datei in der LMS-Datenbank |
| Title     | string  | Titel                              |
| Genre     | string  | Genre                              |
| Album     | string  | Album                              |
| Artist    | string  | Interpret                          |
| Disc      | integer | Aktuelles Medium                   |
| Disccount | integer | Anzahl aller Medien dieses Albums  |
| Bitrate   | string  | Bitrate in Klartext                |
Alle anderen Befehle liefern einen boolean als Rückgabewert.
true wenn der Befehl vom Server bestätigt wurde.
Wird der Befehl nicht bestätigt, so ist die ein Fehler (Exception wird erzeugt).

**boolean LSQ_SavePlaylist (integer $InstanzID, string $Name)  
boolean LSQ_PlayTrack (integer $InstanzID, integer $Value)  
boolean LSQ_NextTrack (integer $InstanzID)  
boolean LSQ_PreviousTrack (integer $InstanzID)  
boolean LSQ_NextButton (integer $InstanzID)  
boolean LSQ_PreviousButton (integer $InstanzID)**  


#### Setzen von Eigenschaften:

Alle LSQ_Set* - Befehle liefern einen boolean als Rückgabewert.
true wenn der gleiche Wert vom Server bestätigt wurde.
false wenn der bestätigte Wert abweicht.
Wird der Befehl nicht bestätigt, so ist die ein Fehler (Exception wird erzeugt).

**boolean LSQ_SetBass (integer $InstanzID, integer $Value)  
boolean LSQ_SetMute (integer $InstanzID, boolean $Value)  
boolean LSQ_SetName (integer $InstanzID, string $Name)  
boolean LSQ_SetPitch (integer $InstanzID, integer $Value)  
boolean LSQ_SetPosition (integer $InstanzID, integer $Value)  
boolean LSQ_SetRepeat (integer $InstanzID, integer $Value)  
boolean LSQ_SetShuffle (integer $InstanzID, integer $Value)  
boolean LSQ_SetTreble (integer $InstanzID, integer $Value)  
boolean LSQ_SetVolume (integer $InstanzID, integer $Value)  
boolean LSQ_SetSleep(integer $InstanzID, integer $Seconds)**  

#### Lesen von Eigenschaften:

Alle LSQ_Set* - Befehle liefern einen boolean als Rückgabewert.
true wenn der gleiche Wert vom Server bestätigt wurde.
false wenn der bestätigte Wert abweicht.

**integer LSQ_GetBass (integer $InstanzID)  
boolean LSQ_GetMute (integer $InstanzID)  
string LSQ_GetName (integer $InstanzID)  
integer LSQ_GetPitch (integer $InstanzID)  
integer LSQ_GetPosition (integer $InstanzID)  
integer LSQ_GetRepeat (integer $InstanzID)  
integer LSQ_GetShuffle (integer $InstanzID)  
integer LSQ_GetTreble (integer $InstanzID)  
integer LSQ_GetVolume (integer $InstanzID)  
integer LSQ_GetSleep(integer $InstanzID)**  


#### Syncronisieren:

**boolean LSQ_SetSync(integer $InstanzID, integer $SlaveInstanzID)**  
SlaveInstanzID wird als Client dem InstanzID zugeordnet.

**boolean LSQ_SetUnSync(integer $InstanzID)**  
Löst $InstanzID aus der Syncronisierung von dem Master.

**mixed (array or boolean) LSQ_GetSync(integer $InstanzID)**  
Liefert alle InstanzIDs der mit $InstanzID gesyncten Geräte als Array.
false wenn kein Sync aktiv ist.

