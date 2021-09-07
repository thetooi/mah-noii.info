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

//Time out if peer (Seconds)
define('__TIMEOUT', 3600);

 /***********************
 ** Configuration end **
 ***********************/
   
/* Prepare Db */
$dbh = new PDO("mysql:host=".__DB_SERVER.";dbname=".__DB_DATABASE, __DB_USERNAME, __DB_PASSWORD) or die(track('Database connection failed'));

   
/* Remove Old Peers */
   $sql1 = 'DELETE FROM `peer` WHERE UNIX_TIMESTAMP(`last_updated`) < '. (time() - __TIMEOUT);
   $dbh -> query( $sql1 );
   
/* Remove Dead Peers */
   $sql2 = 'DELETE FROM `peer_torrent` WHERE UNIX_TIMESTAMP(`last_updated`) < '. (time() - __TIMEOUT);
   $dbh -> query( $sql2 );