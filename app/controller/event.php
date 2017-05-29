<?php

class Event {

public function __construct() {

}

/*
* display a list of events
*/
public function getEventList($f3) {

  $db = $f3->get("db.instance");

  $event = new DB\SQL\Mapper($db,'events');

  $event->club = 'SELECT club FROM sites WHERE events.siteid=sites.id';
  $event->link = 'SELECT link FROM sites WHERE events.siteid=sites.id';
  $event->country = 'SELECT country FROM sites WHERE events.siteid=sites.id';

  $events = $event->find(array(), array('order'=>'date DESC', 'limit'=>100));

  $f3->set('events', $events);
  $f3->set('content','app/template/events.htm');
  echo Template::instance()->render('/app/template/page.htm');

}


public function getEventRSS($f3, $args) {
  $db = $f3->get("db.instance");
  $event = new DB\SQL\Mapper($db, 'events');
  $name = $event->name;
  $event->club = 'SELECT club FROM sites WHERE events.siteid=sites.id';
  $event->abbr = 'SELECT abbr FROM sites WHERE events.siteid=sites.id';
  $event->link = 'SELECT link FROM sites WHERE events.siteid=sites.id';
  $event->country = 'SELECT country FROM sites WHERE events.siteid=sites.id';
  if (isset($args['club'])) {
    $events = $event->find(array("abbr=?", $args['club']), array('order'=>'date DESC', 'limit'=>10));
  } else {
    $events = $event->find(array(), array('order'=>'date DESC', 'limit'=>10));
  }
  $f3->set('events', $events);

  echo Template::instance()->render('app/template/events.rss', 'application/rss+xml');
}

}
