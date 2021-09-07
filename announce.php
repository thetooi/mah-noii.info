<?php
/*
* Bitstorm 2 - A small and fast Bittorrent tracker
* Copyright 2011 Peter Caprioli
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

//Peer announce interval (Seconds)
define('__INTERVAL', 1800);

//Time out if peer is this late to re-announce (Seconds)
define('__TIMEOUT', 120);

//Minimum announce interval (Seconds)
//Most clients obey this, but not all
define('__INTERVAL_MIN', 60);

// By default, never encode more than this number of peers in a single request
define('__MAX_PPR', 20);

 /***********************
 ** Configuration end **
 ***********************/

//Use the correct content-type
header("Content-type: Text/Plain");

$dbh = new PDO("mysql:host=".__DB_SERVER.";dbname=".__DB_DATABASE, __DB_USERNAME, __DB_PASSWORD) or die(track('Database connection failed'));

$browser = get_browser(null, true);
$agent= isset($_SERVER['HTTP_USER_AGENT'])&&is_string($_SERVER['HTTP_USER_AGENT'])?substr($_SERVER['HTTP_USER_AGENT'], 0, 80):"N/A";
// Deny access made with a browser...
if (
    preg_match("/Mozilla/", $agent) || 
    preg_match("/Opera/", $agent) || 
    preg_match("/Links/", $agent) || 
    preg_match("/Lynx/", $agent) || 
    isset($_SERVER['HTTP_COOKIE']) || 
    isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) || 
    isset($_SERVER['HTTP_ACCEPT_CHARSET'])
    )
    die("Deny access made with a browser");


//Make sure we have something to use as a key
if (!isset($_GET['key'])) {
	$_GET['key'] = '';
}

//Inputs that are needed, do not continue without these
valdata('peer_id', true);
$peerid = $_GET['peer_id'];
valdata('port');
$port=intval($_GET['port']);
valdata('info_hash', true);
$info_hash=bin2hex($_GET['info_hash']);
//Validate key as well
valdata('key');
$key= sha1($_GET['key']);

$downloaded = isset($_GET['uploaded'])&& is_numeric($_GET['uploaded']) ? intval($_GET['uploaded']) : 0;
$uploaded = isset($_GET['uploaded'])&& is_numeric($_GET['uploaded']) ? intval($_GET['uploaded']) : 0;
$left = isset($_GET['left'])&& is_numeric($_GET['uploaded']) ? intval($_GET['left']) : 0;


//Do we have a valid client port?
if (!ctype_digit($_GET['port']) || $_GET['port'] < 1 || $_GET['port'] > 65535) {
	die(track('Invalid client port'));
}

//Hack to get comatibility with trackon
if ($_GET['port'] == 999 && substr($_GET['peer_id'], 0, 10) == '-TO0001-XX') {
	die("d8:completei0e10:incompletei0e8:intervali600e12:min intervali60e5:peersld2:ip12:72.14.194.184:port3:999ed2:ip11:72.14.194.14:port3:999ed2:ip12:72.14.194.654:port3:999eee");
}
$user_agent= isset($_SERVER['HTTP_USER_AGENT'])&&is_string($_SERVER['HTTP_USER_AGENT'])?substr($_SERVER['HTTP_USER_AGENT'], 0, 80):"N/A";
$ipadress=is_string($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:die("Weird ip adress");

$insert_peer=$dbh->prepare('INSERT INTO `peer` (`peer_id`, `user_agent`, `ip_address`, `key`, `port`, `last_updated`) '
	. "VALUES (:peerid, :user_agent, INET_ATON(:ipaddress), :key, :port, UTC_TIMESTAMP()) "
	. 'ON DUPLICATE KEY UPDATE `peer_id`=VALUES(`peer_id`), `user_agent` = VALUES(`user_agent`), `ip_address` = VALUES(`ip_address`), `key`=VALUES(`key`), `port` = VALUES(`port`), `last_updated` = UTC_TIMESTAMP(), `id` = LAST_INSERT_ID(`peer`.`id`)');
$insert_peer->execute(['peerid'=>$peerid,'user_agent'=>$user_agent,'ipaddress'=>$ipadress,'key'=>$key,'port'=>$port]);

$pk_peer = $dbh->lastInsertId();

$insert_torrent=$dbh->prepare("INSERT INTO `torrent` (`hash`) VALUES (:infohash) "
 	. "ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)"); // ON DUPLICATE KEY UPDATE is just to make mysql_insert_id work

$insert_torrent->execute(['infohash'=>$info_hash]);
$pk_torrent = $dbh->lastInsertId();

$params=['pkpeer'=>$pk_peer,'uploaded'=>$uploaded,'downloaded'=>$downloaded,'left'=>$left,'infohash'=>$info_hash];
$state = 'state';
$attempt = 'attempt';
if (isset($_GET['event'])){
	$state = ":state";
        $params['state']=$_GET['event'];
	$attempt = 'LAST_INSERT_ID(peer_torrent.id)';
}
$insert_peer_torrent=$dbh->prepare('INSERT INTO peer_torrent (peer_id, torrent_id, uploaded, downloaded, `left`, attempt, `last_updated`) '
	. 'SELECT :pkpeer, `torrent`.`id`, :uploaded, :downloaded, :left, 0, UTC_TIMESTAMP() '
	. 'FROM `torrent` '
	. "WHERE `torrent`.`hash` = :infohash "
	. 'ON DUPLICATE KEY UPDATE `uploaded` = VALUES(`uploaded`), `downloaded` = VALUES(`downloaded`), `left` = VALUES(`left`), ' 
	. 'state=' . $state . ', attempt=' . $attempt . ', ' 
	. 'last_updated = UTC_TIMESTAMP() ');
$insert_peer_torrent->execute($params);

$pk_peer_torrent = $dbh->lastInsertId();

$numwant = __MAX_PPR; //Can be modified by client

//Set number of peers to return
if (isset($_GET['numwant']) && ctype_digit($_GET['numwant']) && $_GET['numwant'] <= __MAX_PPR && $_GET['numwant'] >= 0) {
	$numwant = (int)$_GET['numwant'];
}

$select_peer_torrent=$dbh->prepare('SELECT INET_NTOA(peer.ip_address), peer.port, peer.peer_id '
	. 'FROM peer_torrent '
	. 'JOIN peer ON peer.id = peer_torrent.peer_id '
	. "WHERE peer_torrent.torrent_id = :pktorrent AND peer_torrent.state != 'stopped' "
	. 'AND peer_torrent.last_updated >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . (__INTERVAL + __TIMEOUT) . ' SECOND) '
	. 'AND peer.id != :pkpeer '
	. 'ORDER BY RAND() '
	. 'LIMIT ' . $numwant);
$select_peer_torrent->execute(['pktorrent'=>$pk_torrent,'pkpeer'=>$pk_peer ]);
$reply = array(); //To be encoded and sent to the client

while ($r=$select_peer_torrent->fetch(PDO::FETCH_NUM)) { //Runs for every client with the same infohash
	$reply[] = array($r[0], $r[1], $r[2]); //ip, port, peerid
}

$select_peers=$dbh->prepare('SELECT IFNULL(SUM(peer_torrent.left > 0), 0) AS leech, IFNULL(SUM(peer_torrent.left = 0), 0) AS seed '
	. 'FROM peer_torrent '
	. "WHERE peer_torrent.torrent_id = :pktorrent AND peer_torrent.state != 'stopped' "
	. 'AND peer_torrent.last_updated >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . (__INTERVAL + __TIMEOUT) . ' SECOND) '
	. 'GROUP BY `peer_torrent`.`torrent_id`');
$select_peers->execute(['pktorrent'=>$pk_torrent]);
$seeders = 0;
$leechers = 0;

if ($r = $select_peers->fetch(PDO::FETCH_NUM))
{
	$seeders = $r[1];
	$leechers = $r[0];
}

die(track($reply, $seeders[0], $leechers[0]));

//Bencoding function, returns a bencoded dictionary
//You may go ahead and enter custom keys in the dictionary in
//this function if you'd like.
function track($list, $c=0, $i=0) {
	if (is_string($list)) { //Did we get a string? Return an error to the client
		return 'd14:failure reason'.strlen($list).':'.$list.'e';
	}
	$p = ''; //Peer directory
	foreach($list as $d) { //Runs for each client
		$pid = '';
		if (!isset($_GET['no_peer_id'])) { //Send out peer_ids in the reply
			$real_id = hex2bin($d[2]);
			$pid = '7:peer id'.strlen($real_id).':'.$real_id;
		}
		$p .= 'd2:ip'.strlen($d[0]).':'.$d[0].$pid.'4:porti'.$d[1].'ee';
	}
	//Add some other paramters in the dictionary and merge with peer list
	$r = 'd8:intervali'.__INTERVAL.'e12:min intervali'.__INTERVAL_MIN.'e8:completei'.$c.'e10:incompletei'.$i.'e5:peersl'.$p.'ee';
	return $r;
}

//Do some input validation
function valdata($g, $fixed_size=false) {
	if (!isset($_GET[$g])) {
		die(track('Invalid request, missing data'));
	}
	if (!is_string($_GET[$g])) {
		die(track('Invalid request, unknown data type'));
	}
	if ($fixed_size && strlen($_GET[$g]) != 20) {
		die(track('Invalid request, length on fixed argument not correct'));
	}
	if (strlen($_GET[$g]) > 80) { //128 chars should really be enough
		die(track('Request too long'));
	}
}
