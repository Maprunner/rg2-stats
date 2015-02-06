<?php

$f3 = require('./lib/f3/base.php');

$f3->config('rg2-stats.ini');

// get stats for footer
$db_file = $f3->get('db_file');
$db_type = $f3->get('db_type');
$db = new DB\SQL($db_type.':'.$db_file);
$f3->set('stats',$db->exec('SELECT * FROM stats ORDER BY id DESC LIMIT 1'));

$f3->route('GET /', 'Event->getEventList');

$f3->route('GET /events', 'Event->getEventList');
$f3->route('GET /events/rss/@club', 'Event->getEventRSS');
$f3->route('GET /events/rss', 'Event->getEventRSS');
$f3->route('GET /sites', 'Site->getSiteList');
$f3->route('GET /db', 'Db->updateDatabase');
$f3->run();