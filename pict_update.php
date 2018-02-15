<?php
if (isset($_GET['url'])) {
    $url=$_GET['url'];
    if (strpos($url, 'cloudfront.net/pictures/athletes/5988035/') !== false) {
        copy($url, "images/my_pict.png");
    }
}
?>
