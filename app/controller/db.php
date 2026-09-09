<?php
define('RG_INPUT_ENCODING', 'UTF-8');
define('RG2_ENCODING', 'UTF-8');
define('EVENT_LOCKED_INDICATOR', '_');
define('GPS_RESULT_OFFSET', 50000);

class Db {
  
public function __construct() {

}

/*
* update database events
* read through list of known sites, poll the API and update our records
* intended to be run as a cron job once a night
*/
public function updateDatabase($f3) {
  // if (php_sapi_name() !='cli') {
  //   echo 'Attempt to run db update from browser. This must be run as a cron job.';
  //   exit;
  // }
  $logger = new Log(date('Y-m-d-H-i-s').'-db-update.log');
  $db = $f3->get("db.instance");
  
  // this speeds up UPDATEs from 120ms to 1ms
  $db->exec('PRAGMA synchronous=OFF');
  $site = new DB\SQL\Mapper($db,'sites');
  $site->load(array(), array('order'=>'id ASC'));

  $routeupdate = new DB\SQL\Mapper($db,'routes');
  
  // used to record which sites failed to return valid information on this pass
  $invalidsites = array();
  $options = array(
	'header' => [
    'Cache-Control: no-cache',
		'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'
	  ]
  );
  // read through each site
  while (!$site->dry() && count($invalidsites) < 2) {
    try {
      $validsite = true;
      // $web = new Web;
      // get stats if API supports them
      // $request = $web->request($site->link.'/rg2api.php?type=stats', $options);
      // $logger->write($site->link.'/rg2api.php?type=stats');
      // foreach ($request['headers'] as $header) {
      //   $logger->write($header);
      // }
      // if (strpos($request['body'], 'Request not recognised') !== false) {
      //   // else make do with event list from old installations
      //   $request = $web->request($site->link.'/rg2api.php?type=events');
      // }
      $uk = strpos($site->link, "routegadget.co.uk");
      if ($uk == false) {
        $validsite = false;
      } else {
        $first = strpos($site->link, ".");
        $second = strpos($site->link, ".", $first + 1);
        $siteName = substr($site->link, $first + 1, $second - $first - 1);
        if ("clok" === $siteName) {
          $kartat = "../sites/" . $siteName . "/gadget/kartat/";
        } else {
          $kartat = "../sites/" . $siteName . "/kartat/";
        }
        $logger->write($kartat);
        $data = json_decode(self::getAllEvents($kartat, true));
        if ($data == NULL) {
          $logger->write('No data found in response for site '.$site->link);
          $validsite = false;
        }
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
      foreach ($data as $event) {
        $dbrow = new DB\SQL\Mapper($db,'events');
        // try to get existing record for this event
        $dbrow->load(array('siteid=? AND hasheventid=?', $site->id, $event->id));
        $dbrow->id == NULL ? $newrecord = true : $newrecord = false;
        // update it or set up a new record
        $dbrow->siteid = $site->id;
        $dbrow->hasheventid = $event->id;
        $dbrow->name = $f3->decode($event->name);
        // old API didn't set results etc so need to check first
        if (property_exists($event, 'results')) {
          $dbrow->results = $event->results;
        } else {
          $dbrow->results = 0;
        }
        if (property_exists($event, 'courses')) {
          $dbrow->courses = $event->courses;
        } else {
          $dbrow->courses = 0;
        }
        $newroutes = false;
        if (property_exists($event, 'routes')) {
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

        // georeferenced events return A, B, C, D, E and F from world file
        if (property_exists($event, 'A')) {
          $dbrow->georef = 1;
        } else {
          $dbrow->georef = 0;
        }
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
        //$db->exec('DELETE FROM events WHERE id='.$event->id);
        //$logger->write('Deleted event '.$event->id.' from site '.$event->siteid);
      }
    }
    $event->next();
  }  
  
  // update totals in sites table
  $site = new DB\SQL\Mapper($db,'sites');
  $site->load(array(), array('order'=>'id ASC'));
  $logger->write('Starting totals update');
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
  $logger->write('Adding stats record');
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

  private function getAllEvents($kartatDirectory, $includeStats)
  {
    $output = [];
    $referenced = 0;
    $maps = [];
    if (($handle = @fopen($kartatDirectory . "kartat.txt", "r")) !== false) {
      while (($data = fgetcsv($handle, 0, "|")) !== false) {
        if (count($data) == 14) {
          list($A, $B, $C, $D, $E, $F) = self::generateWorldFile($data);
          // make sure it worked OK
          if ($E != 0 && $F != 0) {
            $maps[$referenced]["mapid"] = intval($data[0]);
            $maps[$referenced]["A"] = $A;
            $maps[$referenced]["D"] = $D;
            $maps[$referenced]["B"] = $B;
            $maps[$referenced]["E"] = $E;
            $maps[$referenced]["C"] = $C;
            $maps[$referenced]["F"] = $F;
            $referenced++;
          }
        }
      }
      fclose($handle);
    }

    $row = 0;
    if (($handle = @fopen($kartatDirectory . "kisat.txt", "r")) !== false) {
      while (($data = fgetcsv($handle, 0, "|")) !== false) {
        $detail = [];
        $fields = count($data);
        if ($fields < 7) {
          // not enough fields to be a valid line
          continue;
        }
        $detail["id"] = intval($data[0]);
        $detail["mapid"] = intval($data[1]);
        if (file_exists($kartatDirectory . $detail["mapid"] . ".gif")) {
          $detail["suffix"] = "gif";
        }
        for ($i = 0; $i < $referenced; $i++) {
          if ($detail["mapid"] == $maps[$i]["mapid"]) {
            $detail["A"] = $maps[$i]["A"];
            $detail["B"] = $maps[$i]["B"];
            $detail["C"] = $maps[$i]["C"];
            $detail["D"] = $maps[$i]["D"];
            $detail["E"] = $maps[$i]["E"];
            $detail["F"] = $maps[$i]["F"];
          }
        }

        $detail["format"] = intval($data[2]);
        // Issue #11: found a stray &#39; in a SUFFOC file
        $name = self::encode_rg_input($data[3]);
        $detail["name"] = str_replace("&#39;", "'", $name);
        // and stray &amp;#39; in a CHIG file
        $detail["name"] = str_replace("&amp;#39;", "'", $name);
        $detail["date"] = $data[4];
        if ($fields > 5) {
          $detail["club"] = self::encode_rg_input($data[5]);
        } else {
          $detail["club"] = "";
        }
        if ($fields > 6) {
          $detail["type"] = $data[6];
        } else {
          $detail["type"] = "X";
        }
        $detail["locked"] = false;
        if ($fields > 7) {
          // Trailing character indicates event is locked: remove before displaying comments.
          $detail["comment"] = rtrim(
            self::formatComments($data[7]),
            EVENT_LOCKED_INDICATOR
          );
          if (substr($data[7], -1) == EVENT_LOCKED_INDICATOR) {
            $detail["locked"] = true;
          }
        } else {
          $detail["comment"] = "";
        }
        if ($includeStats) {
          // avoid reading big results file into memory: has been seen to cause trouble
          if (
            ($resulthandle = @fopen(
              $kartatDirectory . "kilpailijat_" . $detail["id"] . ".txt",
              "r"
            )) !== false
          ) {
            $count = 0;
            while (($data = fgetcsv($resulthandle, 0, "|")) !== false) {
              // don't double-count GPS results
              if (count($data) > 0) {
                if ($data[0] < GPS_RESULT_OFFSET) {
                  $count++;
                } else {
                  continue;
                }
              }
            }
            fclose($resulthandle);
            $detail["results"] = $count;
          } else {
            $detail["results"] = 0;
          }
          if (
            file_exists($kartatDirectory . "sarjat_" . $detail["id"] . ".txt")
          ) {
            $detail["courses"] = count(
              file($kartatDirectory . "sarjat_" . $detail["id"] . ".txt")
            );
          } else {
            $detail["courses"] = 0;
          }
          if (
            file_exists(
              $kartatDirectory . "kommentit_" . $detail["id"] . ".txt"
            )
          ) {
            $detail["routes"] = count(
              file($kartatDirectory . "kommentit_" . $detail["id"] . ".txt")
            );
          } else {
            $detail["routes"] = 0;
          }
        }

        $output[$row] = $detail;
        $row++;
      }
      fclose($handle);
    }
    usort($output, self::class . "::sortEventsByDate");
    return json_encode($output);
  }


  private function sortEventsByDate($a, $b)
  {
    return strcmp($a["date"], $b["date"]);
  }

   private function generateWorldFile($data)
  {
    // takes three georeferenced points in a kartat row and converts to World File format
    for ($i = 0; $i < 3; $i++) {
      $x[$i] = intval($data[2 + ($i * 4)]);
      $lon[$i] = floatval($data[3 + ($i * 4)]);
      $y[$i] = intval($data[4 + ($i * 4)]);
      $lat[$i] = floatval($data[5 + ($i * 4)]);
      //utils::rg2log($data[0].", ".$lat[$i].", ".$lon[$i].", ".$x[$i].", ".$y[$i]);
    }
    // assumes various things about the three points
    // works for RG2, may not work for the original, but we can live with that
    // ideally we would have saved the world file rather than three points
    if (($x[0] !== 0) || ($y[0] !== 0) || ($y[2] !== 0) || ($x[2] === 0)) {
      return array(0, 0, 0, 0, 0, 0);
    }

    // X = Ax + By + C, Y = Dx + Ey + F
    // C = X - Ax - By, where x and y are 0
    $C = $lon[0];
    // F = Y - Dx - Ey, where X and Y are 0
    $F = $lat[0];
    // A = (X - By - C) / x where y = 0
    $A = ($lon[2] - $C) / $x[2];
    // B = (X - Ax - C) / y
    $B = ($lon[1] - ($A * $x[1]) - $C) / $y[1];
    // D = (Y - Ey - F) / x where y = 0
    $D = ($lat[2] - $F) / $x[2];
    // E = (Y - Dx - F) / y
    $E = ($lat[1] - ($D * $x[1]) -  $F) / $y[1];

    return array($A, $B, $C, $D, $E, $F);
  }

    public static function encode_rg_input($input_str)
    {
        $encoded = '';
        if (RG_INPUT_ENCODING != RG2_ENCODING) {
            //
            $encoded = @iconv(RG_INPUT_ENCODING, RG2_ENCODING . '//TRANSLIT//IGNORE', $input_str);
        } else {
            // this removes any non-RG2_ENCODING characters that are stored locally, normally by an original Routegadget installation
            $encoded = mb_convert_encoding($input_str, RG2_ENCODING, RG2_ENCODING);
        }
        if (!$encoded) {
            //
            $encoded = "";
        } else {
            // ENT_COMPAT is just a default flag: ENT_SUBSTITUTE is PHP 5.4.0+
            $encoded = htmlentities($encoded, ENT_COMPAT, RG2_ENCODING);
        }
        return $encoded;
    }

    public  function formatComments($inputComments)
    {
        // replace carriage return and line break codes
        $comments = self::encode_rg_input($inputComments);
        // RG1 uses #cr##nl# and #nl# to allow saving to text file
        $comments = str_replace("#cr##nl#", "\n", $comments);
        $comments = str_replace("#nl#", "\n", $comments);
        $comments = str_replace("<br>", "\n", $comments);
        return $comments;
    }
}