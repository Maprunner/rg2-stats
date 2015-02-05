<?php

class Event {
  
public function __construct() {

}

/*
* display a list of events
*/
public function getEventList() {

  $f3 = \Base::instance();
  $db_file = $f3->get('db_file');
  $db_type = $f3->get('db_type');
  $db = new DB\SQL($db_type.':'.$db_file);

  $event = new DB\SQL\Mapper($db,'events');
  
  $event->club = 'SELECT club FROM sites WHERE events.siteid=sites.id';
  $event->link = 'SELECT link FROM sites WHERE events.siteid=sites.id';
  $event->country = 'SELECT country FROM sites WHERE events.siteid=sites.id';
       
  $event->load(
    array(),
    array(
        'order'=>'date DESC',
        'limit'=>100
    )
  );
  
  $eventdata = array();
  $ev = array();
  while (!$event->dry()) {
    $ev['date'] = $event->date;
    $ev['name'] = $event->name;
    $ev['country'] = $event->country;
    $ev['eventid'] = $event->eventid;
    $ev['link'] = $event->link;
    $ev['results'] = $event->results;
    $ev['courses'] = $event->courses;
    $ev['routes'] = $event->routes;
    $ev['club'] = $event->club;
    array_push($eventdata, $ev);
    $event->next();
  }
  
  $f3->set('events', $eventdata);
  $f3->set('content','app/template/events.htm');
  echo Template::instance()->render('/app/template/page.htm');

}

}