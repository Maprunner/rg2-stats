<?php
$f3 = require('./lib/f3/base.php');

$s = $f3->get('SERVER');
if (strpos($s['SERVER_NAME'], 'localhost') !== FALSE) {
  $f3->set('DEBUG', 3);
} else {
  $f3->set('DEBUG', 0);
}

$f3->config('rg2-stats.ini');
$f3->config('rg2-stats-routes.ini');

$db = new DB\SQL($f3->get('db.type').':'.$f3->get('db.file'));
$f3->set("db.instance", $db);

// get stats for footer

$f3->set('stats',$db->exec('SELECT * FROM stats ORDER BY id DESC LIMIT 2'));

$f3->run();