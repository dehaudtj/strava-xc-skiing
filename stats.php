<?php
define(FILE, "stats.log");

if (isset($_GET['content'])) {
   $content=$_GET['content'];
   if (strpos($content, '5988035') == false) {
      file_put_contents(FILE, time().";".$_SERVER['REMOTE_ADDR'].";".$content."\n", FILE_APPEND | LOCK_EX);
   }
}
?>
