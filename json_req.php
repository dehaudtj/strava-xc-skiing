<?php
   header('Content-type:application/json;charset=utf-8');
   define("DBPATH", "db/.strava/");
   $bounds=NULL;
   $coordinates=NULL;
   // bounds=${nelat},${nelng},${swlat},${swlng}
   if (isset($_GET['bounds'])) {
      $bounds=$_GET['bounds'];
      $coordinates=explode(",", $bounds);
      if (count($coordinates) != 4) {
         $coordinates=NULL;
      }
   }

   $allfolders = scandir(DBPATH);
   $segfolders = array_diff($allfolders, array('.', '..'));
   $json="{ \"entries\": [";
   $count=count($segfolders);
   $first = true;
   for($i=0; $i<$count; $i++) {
      $segfolder=$segfolders[$i];
      $segdesc=DBPATH.$segfolder."/segment.json";
      if(is_file($segdesc) && filesize($segdesc)) {
         $json_content=file_get_contents($segdesc);
         if ($coordinates != NULL) {
            $obj=json_decode($json_content);
            if (!($obj->{'start_latitude'} > $coordinates[2] && $obj->{'start_latitude'} < $coordinates[0] && $obj->{'start_longitude'} > $coordinates[3] && $obj->{'start_longitude'} < $coordinates[1])) {
               continue;
            }
         }
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
