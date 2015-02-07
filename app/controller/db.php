<?php

class Db {
  
public function __construct() {

}

/*
* update database events
* read through list of known sites, poll the API and update our records
* intended to be run as a cron job once a night
*/
public function updateDatabase($f3) {
  if (php_sapi_name() !='cli') {
    echo 'Attempt to run db update from browser. This must be run as a cron job.';
    exit;
  }
  $logger = new Log(date('Y-m-d-H-i-s').'-db-update.log');
  $db = $f3->get("db.instance");
  
  // this speeds up UPDATEs from 120ms to 1ms
  $db->exec('PRAGMA synchronous=OFF');
  $site = new DB\SQL\Mapper($db,'sites');
  $site->load(array(), array('order'=>'id ASC'));

  $routeupdate = new DB\SQL\Mapper($db,'routes');
  
  // used to record which sites failed to return valid information on this pass
  $invalidsites = array();

  // read through each site
  while (!$site->dry()) {
    try {
      $validsite = true;
      $web = new Web;
      // get stats if API supports them
      $request = $web->request($site->link.'/rg2api.php?type=stats');
      if (strpos($request['body'], 'Request not recognised') !== false) {
        // else make do with event list from old installations
        $request = $web->request($site->link.'/rg2api.php?type=events');
      }
      $data = json_decode($request['body']);
      if ($data == NULL) {
        $validsite = false;
      }
    }
    //catch exception
        catch(Exception $e) {
          $validsite = false;
          $logger->write('Error reading site '.$site->link);
    }
    $count = 0;
    if ($validsite) {
      // read through each event in returned list
      foreach ($data->data as $event) {
        $dbrow = new DB\SQL\Mapper($db,'events');
        // try to get existing record for this event
        $dbrow->load(array('siteid=? AND hasheventid=?', $site->id, $event->id));
        $dbrow->id == NULL ? $newrecord = true : $newrecord = false;
        // update it or set up a new record
        $dbrow->siteid = $site->id;
        $dbrow->hasheventid = $event->id;
        $dbrow->name = $f3->decode($event->name);
        // old API didn't set results etc so need to check first
        if (array_key_exists('results', $event)) {
          $dbrow->results = $event->results;
        } else {
          $dbrow->results = 0;
        }
        if (array_key_exists('courses', $event)) {
          $dbrow->courses = $event->courses;
        } else {
          $dbrow->courses = 0;
        }
        $newroutes = false;
        if (array_key_exists('routes', $event)) {
          // check if new routes have been added since last poll
          if (!$newrecord) {
            if (($dbrow->routes) < ($event->routes)) {
              $newroutes = true;
            } 
          }
          $dbrow->routes = $event->routes;
        } else {
          $dbrow->routes = 0;
        }
        $dbrow->date = $event->date;
        $dbrow->updatetime = date('Y-m-d H:i:s');
        // update or create record
        $dbrow->save();
        $count++;
        // add new route record if necessary
        if (($newrecord) || ($newroutes)) {
          $routeupdate->eventid = $dbrow->id;
          $routeupdate->name = $dbrow->name;
          $routeupdate->siteid = $dbrow->siteid;
          $routeupdate->hasheventid = $dbrow->hasheventid;
          $routeupdate->routes = $dbrow->routes;
          $routeupdate->updatetime = date('Y-m-d H:i:s');
          $routeupdate->save();
          $routeupdate->reset();
        }
      }

      $logger->write($count.' records found for site '.$site->link);
    } else {
      array_push($invalidsites, $site->id);
      $logger->write('Invalid response for site '.$site->link);
    }
    $site->next();
  }

  // remove any deleted events
  // no good way to do this, so here is a not very bad way
  // based on testing if we have updated the event record after a succesful pass for a given site
  // if not then event wasn't in list returned by API so has been deleted
  $event = new DB\SQL\Mapper($db,'events');
  $event->load(array(), array('order'=>'siteid ASC, id ASC'));
  // allow a margin of 30 minutes: we can always catch it on the next update
  $cutofftime = time() - 1800;
  while (!$event->dry()) {
    // don't delete things if we didn't get an API response
    if (!in_array($event->siteid, $invalidsites)) {
      if (strtotime($event->updatetime) < $cutofftime) {
        // wasn't updated this pass so it must have been deleted
        // can't use $event->erase() since it resets the pointer so next() doesn't work as expected
        $db->exec('DELETE FROM events WHERE id='.$event->id);
        $logger->write('Deleted event '.$event->id.' from site '.$event->siteid);
      }
    }
    $event->next();
  }  
  
  // update totals in sites table
  $site = new DB\SQL\Mapper($db,'sites');
  $site->load(array(), array('order'=>'id ASC'));
  while (!$site->dry()) {
    $res = $db->exec('SELECT COUNT(siteid) as e,SUM(results) AS r, SUM(courses) as c, SUM(routes) as ro FROM events WHERE siteid='.$site->id);
    $site->events = $res[0]['e'];
    $site->results = $res[0]['r'];
    $site->courses = $res[0]['c'];
    $site->routes = $res[0]['ro'];
    if (!in_array($site->id, $invalidsites)) {
      $site->lastcheck = date('Y-m-d H:i:s');
    }
    $site->save();
    $site->next();
  }

  // add new stats record
  $stats = new DB\SQL\Mapper($db,'stats');
  $res = $db->exec('SELECT COUNT(id) as s, SUM(events) AS e, SUM(results) AS r, SUM(courses) as c, SUM(routes) as ro FROM sites');
  $stats->sites = $res[0]['s'];
  $stats->events = $res[0]['e'];
  $stats->results = $res[0]['r'];
  $stats->courses = $res[0]['c'];
  $stats->routes = $res[0]['ro'];
  $stats->updated = date('Y-m-d H:i:s');
  $stats->save();
}

}