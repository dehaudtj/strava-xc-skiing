<!DOCTYPE html>
<html>
<head>
<title>Strava's cross-country skiing segments</title>
<meta charset="utf-8" />
<link rel="icon" type="image/x-icon" href="favicon.ico" />
<link rel="stylesheet" href="style.css" />
<link rel="stylesheet" href="spinner.css" />
</head>
<body>
<div class="spinner" id="progress">
  <div class="bounce1"></div>
  <div class="bounce2"></div>
  <div class="bounce3"></div>
</div>
<section class="container" style="height:100%; width:100%;">
  <div class="fixed-left">
  <div class="filters">
     <p>Filters</p>
     <input type="range" value="15" max="50" min="0" step"1"> 
  </div>
  <table id="table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Distance</th>
            <th>Elevation</th>
            <th>Efforts</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
  </table>
  </div>
  <div id="map-canvas"></div>
</section>
<script>

var map,
    infowindow,
    drawnpolylines,
    bounds_changed;

function Get(yourUrl, callback) {
    var Httpreq = new XMLHttpRequest(); // a new request
    Httpreq.overrideMimeType("application/json");
    Httpreq.onload = function (e) {
       if (Httpreq.readyState === 4) {
          if (Httpreq.status === 200) {
            callback(Httpreq.responseText);
          } else {
            console.error(Httpreq.statusText);
          }
       }
    };
    Httpreq.open("GET", yourUrl, true);
    Httpreq.send(null);

}

function showProgress(state) {
    var progress = document.getElementById("progress");
    if (progress == undefined) return;
    if (state) progress.style.visibility = "visible";
    else progress.style.visibility = "hidden";
}

function initialize() {
    infowindow = new google.maps.InfoWindow();
    drawnpolylines = new Map();
    bounds_changed = false; 

    map = new google.maps.Map(document.getElementById('map-canvas'), {
        center: {
            lat: 45.1910665,
            lng: 5.5506134
        },
        zoom: 13 
    });
    map.setMapTypeId('terrain');

    map.addListener('idle', function() {
       if (bounds_changed) {
          bounds_changed = false;
          load();
       }
    });
    map.addListener('dragstart', function() {
       bounds_changed = true;
    });
    map.addListener('zoom_changed', function() {
       load();
    });
    google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
       load();
    });
}

var colorMap = new Map();

function getRandomColor(id) {
  var letters = '0123456789ABCDEF';
  var color = colorMap.get(id);

  if (color == undefined) {
    color='#';
    for (var i = 0; i < 6; i++) {
      color += letters[Math.floor(Math.random() * 16)];
    }
    colorMap.set(id, color);
  }
  return color;
}

function clear() {
   var table = document.getElementById("table");
   drawnpolylines.forEach(function(value, key, map) {
      value.setMap(null);
   });
   drawnpolylines.clear();
   while(table.rows.length > 1) {
      table.deleteRow(1);
   }
}

function load() {
   showProgress(true);
   clear();
   var nelat = map.getBounds().getNorthEast().lat();
   var nelng = map.getBounds().getNorthEast().lng();
   var swlat = map.getBounds().getSouthWest().lat();
   var swlng = map.getBounds().getSouthWest().lng();

   Get("json_req.php?bounds="+nelat+","+nelng+","+swlat+","+swlng, function(json) {
      var objs = JSON.parse(json);
      for (var i = 0; i < objs.entries.length; i++) {
          // MAP   
          var encoded=objs.entries[i].map.polyline;
          var decode = google.maps.geometry.encoding.decodePath(encoded);
          var line = new google.maps.Polyline({
             path: decode,
             strokeColor: getRandomColor(objs.entries[i].id),
             strokeOpacity: 0.6,
             strokeWeight:  3
         });

         var content = "<a target=\"_blank\" href=\"https://www.strava.com/segments/"+objs.entries[i].id+"\">"+objs.entries[i].name+"</a>";

         createInfoWindow(objs.entries[i].id, line, content);

         line.setMap(map);

         // TABLE
         addLine(objs.entries[i].id, "<a target=\"_blank\" href=\"https://www.strava.com/segments/"+objs.entries[i].id+"\">"+objs.entries[i].name+"</a>", Math.round((objs.entries[i].distance/1000)*100)/100, Math.round(objs.entries[i].total_elevation_gain), objs.entries[i].effort_count);

         drawnpolylines.set(objs.entries[i].id, line);
      }
      showProgress(false);
   });
}

function addLine(id, name, dist, elev, counts)
{
	var table = document.getElementById("table");

	var line = table.insertRow(-1);
	line.onmouseover =  function(){ mouseoverLine(id, drawnpolylines.get(id), false);};
        line.onmouseout  = function() { mouseoutLine(id, drawnpolylines.get(id));};
        line.id = "tr-"+id;

	var col1 = line.insertCell(0);
	col1.innerHTML = name;

	var col2 = line.insertCell(1);
	col2.innerHTML = dist;

	var col3 = line.insertCell(2);
	col3.innerHTML = elev;

	var col4 = line.insertCell(3);
	col4.innerHTML = counts;
}

function mouseoverLine(id, line, scroll) {
   if (line == undefined) return;
   line.setOptions({
      strokeOpacity: 1.0,
      zIndex: 2,
      strokeWeight:  4
   });
   var row = document.getElementById("tr-"+id);
   if (row != undefined) {
      row.style.background = '#ffcc99';
      if (scroll) row.scrollIntoView(true);
   }
}

function mouseoutLine(id, line) {
   if (line == undefined) return;
   line.setOptions({
      strokeOpacity: 0.6,
      zIndex: 1,
      strokeWeight:  3
   });
   var row = document.getElementById("tr-"+id);
   if (row != undefined) {
      row.style.background = 'inherit';
   }
}

function createInfoWindow(id, poly,content) {
    google.maps.event.addListener(poly, 'mouseover', function(event) {
       mouseoverLine(id, poly, true);
    });

    google.maps.event.addListener(poly, 'mouseout', function(event) {
       mouseoutLine(id, poly);
    });

    google.maps.event.addListener(poly, 'click', function(event) {
        infowindow.setContent(content);
        infowindow.setPosition(event.latLng);
        infowindow.open(map);
    });
}
</script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?key=AIzaSyAbH0GjIPauLUiSZpn-xDclEa_AezdMUQA&libraries=geometry&callback=initialize"></script>
</body>
</html>
