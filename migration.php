<?php
require_once 'db.lib.php';
require_once 'migration.class.php';

new Migration($connection_string, $db_user, $db_password);

