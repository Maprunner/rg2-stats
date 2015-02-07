<?php

class Site {
  
public function __construct() {

}

/*
* display a list of sites
*/
public function getSiteList($f3) {

  $db = $f3->get("db.instance");
  $site = new DB\SQL\Mapper($db,'sites');
  
  $site->latesthasheventid = 'SELECT hasheventid FROM events WHERE events.siteid=sites.id ORDER BY date DESC LIMIT 1';
  $site->latesteventname = 'SELECT name FROM events WHERE events.siteid=sites.id ORDER BY date DESC LIMIT 1';
  $site->latesteventdate = 'SELECT date FROM events WHERE events.siteid=sites.id ORDER BY date DESC LIMIT 1';
    
  $sites = $site->find();

  $f3->set('sites', $sites);
  $f3->set('content','app/template/sites.htm');

  echo Template::instance()->render('/app/template/page.htm');

}

}