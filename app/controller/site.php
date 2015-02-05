<?php

class Site {
  
public function __construct() {

}

/*
* display a list of sites
*/
public function getSiteList() {

  $f3 = \Base::instance();
  $db_file = $f3->get('db_file');
  $db_type = $f3->get('db_type');
  $db = new DB\SQL($db_type.':'.$db_file);

  $site = new DB\SQL\Mapper($db,'sites');
  
  $site->latesteventid = 'SELECT eventid FROM events WHERE events.siteid=sites.id ORDER BY date DESC LIMIT 1';
  $site->latesteventname = 'SELECT name FROM events WHERE events.siteid=sites.id ORDER BY date DESC LIMIT 1';
  $site->latesteventdate = 'SELECT date FROM events WHERE events.siteid=sites.id ORDER BY date DESC LIMIT 1';
    
  $site->load(
    array(),
    array(
      'order'=>'events DESC'
    )
  );
  
  $sitedata = array();
  $si = array();
  while (!$site->dry()) {
    $si['club'] = $site->club;
    $si['country'] = $site->country;
    $si['events'] = $site->events;
    $si['routes'] = $site->routes;
    $si['results'] = $site->results;
    $si['courses'] = $site->courses;
    $si['link'] = $site->link;
    $si['latesteventid'] = $site->latesteventid;
    $si['latesteventname'] = $site->latesteventname;
    $si['latesteventdate'] = $site->latesteventdate;
    array_push($sitedata, $si);
    $site->next();
  }
  
  $f3->set('sites', $sitedata);
  $f3->set('content','app/template/sites.htm');
  echo Template::instance()->render('/app/template/page.htm');

}

}