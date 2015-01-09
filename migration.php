<?php
require_once 'db.lib.php';
require_once 'migration.class.php';

if (empty($db_user) || empty($db_password))
	die("ERROR! You need to set the db.lib.php configs before proceeding...\n");

new Migration($connection_string, $db_user, $db_password);

