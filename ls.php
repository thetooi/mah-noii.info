<?php 
/*
 * Bitstorm 2 - A small and fast Bittorrent tracker
 * Copyright 2011 Inpun LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*************************
 ** Configuration start **
 *************************/

require 'constants.php';

/***********************
 ** Configuration end **
 ***********************/

//Use the correct content-type
header("Content-type: text/html");
?>
<!DOCTYPE html><html> <head> <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /> <title>OpenBitTorrent - List Hash Torrent</title> <link rel="stylesheet" type="text/css" href="styles.css" /> <link rel="apple-touch-icon" sizes="57x57" href="/apple-icon-57x57.png"> <link rel="apple-touch-icon" sizes="60x60" href="/apple-icon-60x60.png"> <link rel="apple-touch-icon" sizes="72x72" href="/apple-icon-72x72.png"> <link rel="apple-touch-icon" sizes="76x76" href="/apple-icon-76x76.png"> <link rel="apple-touch-icon" sizes="114x114" href="/apple-icon-114x114.png"> <link rel="apple-touch-icon" sizes="120x120" href="/apple-icon-120x120.png"> <link rel="apple-touch-icon" sizes="144x144" href="/apple-icon-144x144.png"> <link rel="apple-touch-icon" sizes="152x152" href="/apple-icon-152x152.png"> <link rel="apple-touch-icon" sizes="180x180" href="/apple-icon-180x180.png"> <link rel="icon" type="image/png" sizes="192x192" href="/android-icon-192x192.png"> <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png"> <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png"> <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png"> <link rel="manifest" href="/manifest.json"> <meta name="msapplication-TileColor" content="#ffffff"> <meta name="msapplication-TileImage" content="/ms-icon-144x144.png"> <meta name="theme-color" content="#ffffff"> <style type="text/css">body, input, textarea { font-family: "Fira Sans","Source Sans Pro",Helvetica,Arial,sans-serif; font-weight: 400;}table.db-table { border-right: 1px solid #ccc; border-bottom: 1px solid #ccc; margin: 0 auto;}table.db-table th {padding: 5px;border-left: 1px solid #ccc;border-top: 1px solid #ccc;}table.db-table td {padding: 5px;border-left: 1px solid #ccc;border-top: 1px solid #ccc;}</style> </head> <body> <div class="form-wrapper cf"> <h1>OpenBitTorrent - List 100 Hash Torrent</h1>
<?php

$dbh = new PDO("mysql:host=".__DB_SERVER.";dbname=".__DB_DATABASE, __DB_USERNAME, __DB_PASSWORD) or die(track('Database connection failed'));
$torrents_tracked = $dbh->query('SELECT hash '
		. 'FROM (SELECT torrent_id, uploaded, downloaded, MAX(attempt) FROM peer_torrent '
		. 'GROUP BY torrent_id, peer_id) as X JOIN torrent ON X.torrent_id = torrent.id '
		. 'GROUP BY torrent_id LIMIT 100');
$pees_tracked = $dbh->query('SELECT hash, COUNT(left = 0) as seeders, COUNT(left > 0) as leechers '
		. 'FROM (SELECT torrent_id, uploaded, downloaded, MAX(attempt) FROM peer_torrent '
		. 'GROUP BY torrent_id, peer_id) as X JOIN torrent ON X.torrent_id = torrent.id '
		. 'GROUP BY torrent_id LIMIT 100');
$rowcount = $torrents_tracked->rowCount();
if( $rowcount > 0) {
	echo '<table cellpadding="0" cellspacing="0" class="db-table">';
	echo '<tr><th>Hash</th><th>Seeders</th><th>Leechers</th></tr>';
	while($r = $pees_tracked->fetch(PDO::FETCH_NUM)) {
		echo '<tr>';
		echo '<td><a href="magnet:?xt=urn:btih:',strtoupper($r[0]),'&dn=',strtoupper($r[0]),'&tr=https%3A%2F%2Fmah-noii.info%2Fannounce" target="_blank"><img src="magnet.png" width="10px">',strtoupper($r[0]),'</a></td>';
		echo '<td>',formatBytes($r[1]),'</td>';
		echo '<td>',formatBytes($r[2]),'</td>';
		echo '</tr>';
	}
	echo '</table>';
}
function formatBytes($size) {
	if ($size==0) {
		return "0";
	}
	$suffixes = array ('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
	$base = log($size, 1024);
	$s = pow(1024, $base - floor($base));
	$precision = max(0, 1-floor(log($s, 10)));
	
	return sprintf("%.".$precision."f%s", $s, $suffixes[floor($base)]);
}
?>
</div> <div class="byline"> <p>Powered by <a target="_blank" href="https://toui.cc">เดอะทุย</a></p> </div> </body></html>

