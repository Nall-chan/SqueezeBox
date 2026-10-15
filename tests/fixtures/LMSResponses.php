<?php

declare(strict_types=1);

/**
 * Antworten eines Lyrion Music Server 9.1.1, mitgeschnitten am 15.10.2026.
 * Anonymisiert: MAC-Adressen, Namen der Player, IP-Adressen, UUIDs und Seriennummern ersetzt, Listen gekürzt.
 * Konstruiert (nicht mitgeschnitten): 'syncgroupsNone', 'alarmsNone' und in 'alarms' der vierte Wecker
 * (rein numerische Id, keine Wochentage), 'statusStream' (Radio-Stream ohne Dauer).
 * 'artistsNone' und 'mediafolder' sind mitgeschnitten, 'mediafolder' auf zwei Einträge gekürzt.
 */
return [
    'players'        => 'players 0 10 count%3A2 playerindex%3A0 playerid%3A00%3A04%3A20%3A00%3A00%3A01 uuid%3A00000000000000000000000000000001 ip%3A192.0.2.81%3A42532 name%3APlayer%201 seq_no%3A2 model%3Ababy modelname%3ASqueezebox%20Radio power%3A1 isplaying%3A0 displaytype%3Anone isplayer%3A1 canpoweroff%3A1 connected%3A1 firmware%3A9.0.1-r17084 playerindex%3A1 playerid%3Acc%3Acc%3A00%3A00%3A00%3A02 uuid%3A ip%3A192.0.2.250%3A61354 name%3AB%C3%BCro seq_no%3A0 model%3Asqueezelite modelname%3ACastBridge power%3A1 isplaying%3A0 displaytype%3Anone isplayer%3A1 canpoweroff%3A1 connected%3A1 firmware%3A0',
    'syncgroups'     => 'syncgroups sync_members%3A00%3A04%3A20%3A00%3A00%3A01%2C00%3A04%3A20%3A00%3A00%3A03 sync_member_names%3APlayer%201%2CPlayer%203',
    'syncgroupsNone' => 'syncgroups',
    'genres'         => 'genres 0 3 id%3A516 genre%3A4294967295) favorites_url%3Adb%3Agenre.name%3D4294967295) id%3A559 genre%3AA%20capella favorites_url%3Adb%3Agenre.name%3DA%2520capella id%3A563 genre%3AAlternative favorites_url%3Adb%3Agenre.name%3DAlternative count%3A81',
    'playlistTracks' => 'playlists tracks 0 3 playlist_id%3A241861 tags%3Ad playlist%20index%3A0 id%3A240928 title%3AMutter%20aller%20Sketche duration%3A62.587 playlist%20index%3A1 id%3A240929 title%3APKK duration%3A89.418 playlist%20index%3A2 id%3A240930 title%3ABruder%20vor%20Luder duration%3A159.608 count%3A153',
    'songinfo'       => 'songinfo 0 100 track_id%3A220030 tags%3AgladiqrRtueJINpsy id%3A220030 title%3AFu%C3%9Fballerpech genre%3AH%C3%B6rspiel album%3ADie%20Teufelskicker%20Folge%2001%3A%20Moritz%20macht%20das%20Spiel! artist%3ADie%20Teufelskicker duration%3A314.009 bitrate%3A320kb%2Fs%20CBR tracknum%3A1 url%3Afile%3A%2F%2F%2FE%3A%2FMusik%2F01%2520Fu%25DFballerpech.mp3 album_id%3A12230 artwork_track_id%3A6478fae1 genre_id%3A521 artist_id%3A14736 year%3A2005',
    'mixerVolume'    => '00%3A04%3A20%3A00%3A00%3A01 mixer volume 52',
    'mixerPower'     => '00%3A04%3A20%3A00%3A00%3A01 mixer power %3F',
    'syncPlayer'     => '00%3A04%3A20%3A00%3A00%3A01 sync 00%3A04%3A20%3A00%3A00%3A03',
    'playlistsinfo'  => '00%3A04%3A20%3A00%3A00%3A01 playlist playlistsinfo',
    'status'         => '00%3A04%3A20%3A00%3A00%3A01 status - 1 tags%3AgladiqrRtueJINpsy player_name%3APlayer%201 player_connected%3A1 player_ip%3A192.0.2.81%3A42532 power%3A1 signalstrength%3A88 mode%3Apause remote%3A1 current_title%3ARealize time%3A61.2079959869385 rate%3A1 duration%3A245 sync_master%3A00%3A04%3A20%3A00%3A00%3A01 sync_slaves%3A00%3A04%3A20%3A00%3A00%3A03 mixer%20volume%3A52 playlist%20repeat%3A0 playlist%20shuffle%3A1 playlist%20mode%3Aoff seq_no%3A2 playlist_cur_index%3A11 playlist_timestamp%3A1789468862.9259 playlist_tracks%3A50 randomplay%3A0 digital_volume_control%3A1 use_volume_control%3A1 playlist%20index%3A11 id%3A-200610120 title%3ARealize album%3A%20%7C%20YouTube%20Music artist%3A%E9%88%B4%E6%9C%A8%E3%81%93%E3%81%AE%E3%81%BF duration%3A245 bitrate%3A192k%20CBR url%3Aytm%3A%2F%2FO0vtg52wtjo year%3A0',
    // Wie vom Modul nach SliceData() verarbeitet: ohne Echo der Parameter "0 10 filter:all".
    'alarms'         => '00%3A04%3A20%3A00%3A00%3A01 alarms fade%3A0 count%3A4 id%3A4fcd6ade dow%3A1%2C2%2C3%2C4%2C5 enabled%3A1 repeat%3A1 shufflemode%3A2 time%3A57780 volume%3A44 url%3Afile%3A%2F%2F%2FE%3A%2FMusik%2FPlaylist%2FAKB.m3u id%3Aba83a018 dow%3A0%2C1%2C2%2C3%2C4%2C5%2C6 enabled%3A1 repeat%3A1 shufflemode%3A0 time%3A25200 volume%3A20 url%3ACURRENT_PLAYLIST id%3A5cd4aa32 dow%3A0%2C1%2C2%2C3%2C4%2C5%2C6 enabled%3A0 repeat%3A1 shufflemode%3A0 time%3A25200 volume%3A20 url%3ACURRENT_PLAYLIST id%3A12345678 dow%3A enabled%3A1 repeat%3A0 shufflemode%3A0 time%3A25200 volume%3A20 url%3ACURRENT_PLAYLIST',
    'alarmsNone'     => '00%3A04%3A20%3A00%3A00%3A01 alarms fade%3A0 count%3A0',
    'artistsNone'    => 'artists 0 100000 search%3Ateufels%20kicker count%3A0',
    'mediafolder'    => 'mediafolder 0 100000 tags%3Auc id%3A241888 filename%3ABartim%C3%A4us type%3Afolder coverid%3A url%3Afile%3A%2F%2F%2FE%3A%2FServerFolders%2FMusik%2FBartim%25E4us id%3A241889 filename%3ADietmar%20Simon type%3Afolder coverid%3A url%3Afile%3A%2F%2F%2FE%3A%2FServerFolders%2FMusik%2FDietmar%2520Simon count%3A2',
    'statusStream'   => '00%3A04%3A20%3A00%3A00%3A01 status - 1 tags%3AgladiqrRtueJINpsy player_name%3APlayer%201 player_connected%3A1 power%3A1 mode%3Aplay remote%3A1 current_title%3ARadio%20Test time%3A125.5 rate%3A1 playlist_cur_index%3A0 playlist_tracks%3A1 playlist%20index%3A0 id%3A-1 title%3ARadio%20Test url%3Ahttp%3A%2F%2Fradio.example%2Fstream.mp3',
    'alarmPlaylists' => 'alarm playlists 0 5 category%3AAktuelle%20Wiedergabeliste title%3AAktuelle%20Wiedergabeliste%20verwenden url%3A singleton%3A1 category%3AFavoriten title%3AWenn%20sie2 url%3Afile%3A%2F%2F%2FE%3A%2FMusik%2FMax%20Giesinger%2F01-05-%20Wenn%20sie%20tanzt.mp3 singleton%3A0 category%3AFavoriten title%3AAnime%20Nexus url%3Ahttp%3A%2F%2Fopml.radiotime.com%2FTune.ashx%3Fid%3Ds108601%26formats%3Daac%2Cogg%2Cmp3%26partnerId%3D16%26serial%3D0 singleton%3A0',
];
