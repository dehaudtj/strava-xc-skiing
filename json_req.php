<?php
   header('Content-type:application/json;charset=utf-8');
   define("DBPATH", "db/.strava/");

   $bounds=NULL;
   $coordinates=NULL;
   $minEffort=NULL;
   $maxEffort=NULL;
   $minDist=NULL;
   $maxDist=NULL;
   // bounds=${nelat},${nelng},${swlat},${swlng}
   if (isset($_GET['bounds'])) {
      $bounds=$_GET['bounds'];
      $coordinates=explode(",", $bounds);
      if (count($coordinates) != 4) {
         $coordinates=NULL;
      }
   }

   if (isset($_GET['minEffort'])) {
	$minEffort=(int)$_GET['minEffort'];
   }
   if (isset($_GET['maxEffort'])) {
        $maxEffort=(int)$_GET['maxEffort'];
   }
   if (isset($_GET['minDist'])) {
        $minDist=(int)$_GET['minDist'];
   }
   if (isset($_GET['maxDist'])) {
        $maxDist=(int)$_GET['maxDist'];
   }

   $allfolders = scandir(DBPATH);
   $segfolders = array_diff($allfolders, array('.', '..'));
   $json="{ \"entries\": [";
   $count=count($segfolders);
   $first = true;
   for($i=0; $i<$count; $i++) {
      $segfolder=$segfolders[$i];
      $segdesc=DBPATH.$segfolder."/segment.json";
      $hide=DBPATH.$segfolder."/hide";
      if(!is_file($hide) && is_file($segdesc) && filesize($segdesc)) {
         $json_content=file_get_contents($segdesc);
         $obj=json_decode($json_content);

         if ($coordinates != NULL) {
            if (!($obj->{'start_latitude'} > $coordinates[2] && $obj->{'start_latitude'} < $coordinates[0] && $obj->{'start_longitude'} > $coordinates[3] && $obj->{'start_longitude'} < $coordinates[1])) {
               continue;
            }
         }

         if ($minEffort != NULL && $obj->{'effort_count'} < $minEffort) continue;
         if ($maxEffort != NULL && $obj->{'effort_count'} > $maxEffort) continue;
         if ($minDist != NULL && $obj->{'distance'} < $minDist) continue;
         if ($maxDist != NULL && $obj->{'distance'} > $maxDist) continue;


         if (!$first) {
            $json = $json.",\n";
         } else {
            $first = false;
         }
         $json = $json.$json_content;
      }
   }
   $json = $json."]}";
   
   echo $json;
?>
