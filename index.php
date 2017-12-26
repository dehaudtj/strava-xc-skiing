<!DOCTYPE html>
<html>
<head>
<title>Strava XC-Skiing Segments</title>
<meta charset="utf-8" />
<meta name="author" content="Julien Dehaudt">
<meta name="description" content="Strava's segments for cross-country skiing around Grenoble, RA, France">
<link rel="icon" type="image/x-icon" href="favicon.ico" />
<link rel="stylesheet" href="style.css" />
<link rel="stylesheet" href="spinner.css" />
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="https://cdn.jsdelivr.net/npm/js-cookie@2/src/js.cookie.min.js"></script>
<script>
var minSelEffort=null,
    maxSelEffort=null,
    minSelDist=null,
    maxSelDist=null,
    statsRegistered=false,
    nameFilter="",
    definedColor=new Map();

definedColor.set("rouge", "#FF0000");
definedColor.set("jaune", "#FFFF00");
definedColor.set("bleu", "#0000FF");
definedColor.set("vert", "#008000");
definedColor.set("noir", "#000000");

  $(function() {
    $("#dist-slider-range").slider({                                                                
      range: true,
      /*min: 0,
      max: 500,                                                                                        
      values: [75, 300],*/
      slide: function(event, ui) {
        $("#dist").val(ui.values[0] + " - " + ui.values[1]);
        minSelDist=ui.values[0]*1000;
        maxSelDist=ui.values[1]*1000;
      },                                                                                               
      stop: function(event, ui) {                                                                      
        load();
      }
    });
    $("#dist").val($("#dist-slider-range").slider("values", 0) + " - " + $("#dist-slider-range").slider("values", 1));                                                                               

    $("#efforts-slider-range").slider({
      range: true,
      /*min: 0,
      max: 500,
      values: [75, 300],*/
      slide: function(event, ui) {
        $("#efforts").val(ui.values[0] + " - " + ui.values[1]);
        minSelEffort=ui.values[0];
        maxSelEffort=ui.values[1];
      },
      stop: function(event, ui) {
        load();
      }
    });
    $("#efforts").val($("#efforts-slider-range").slider("values", 0) + " - " + $("#efforts-slider-range").slider("values", 1));
  } );

function setSliderValues(id, min, max, values) {
   $(id).val(values[0] + " - " + values[1]);
   $(id+"-slider-range").slider("option", "min", min);
   $(id+"-slider-range").slider("option", "max", max);
   $(id+"-slider-range").slider("option", "values", values);
}

</script>
</head>
<body>
<?php
   // check connection from local network ...
   if (substr($_SERVER['REMOTE_ADDR'],0,8) == "192.168.") {
      $visibility="";
   } else {
      $visibility="visibility: hidden;";
   }

   // Strava OAuth
   if (isset($_GET['code'])) {
	$code=$_GET['code'];
   }
   if (isset($_GET['error'])) {
	$err=$_GET['error'];
   }
?>
<div class="logged-out" id="logged-out" style="visibility: hidden;">
  <button type="button" onclick="window.location.replace('https://www.strava.com/oauth/authorize?client_id=16198&response_type=code&redirect_uri=http://strava.jln-web.fr&approval_prompt=force');"></button>
</div>
<div class="strava-powered"></div>
<div class="logged-in" id="div-user-id">
    <div id="username"></div>
    <div class="disconnect" onclick="disconnect()"></div>
</div>
<div class="spinner" id="progress">
  <div class="bounce1"></div>
  <div class="bounce2"></div>
  <div class="bounce3"></div>
</div>
<section class="container" style="height:100%; width:100%;">
  <div class="fixed-left">
  <div class="filters">
    <p>
       <label for="amount">Distance:</label>
       <input type="text" id="dist" readonly style="border:0; color:#f6931f; font-weight:bold; width=90%">
    </p> 
    <div id="dist-slider-range"></div>

    <p>
       <label for="amount">Efforts:</label>
       <input type="text" id="efforts" readonly style="border:0; color:#f6931f; font-weight:bold; width=90%">
    </p>
    <div id="efforts-slider-range"></div>
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
    drawnmarkers=[],
    bounds_changed,
    level,
    user=null;

function stravaOAuth(code, error) {
    var client_id = "16198";
    var client_secret = "43a1c90b0ac17693acb52ed02e7bbc6c83d971b3";

    if (error == "access_denied") {
        Cookies.remove("user-data");
        return;
    }

    if (code != "") {
        Post("https://www.strava.com/oauth/token",
             "client_id="+client_id+"&client_secret="+client_secret+"&code="+code,
             function(json) {
                user = JSON.parse(json);
                Cookies.set("user-data", JSON.stringify(user), { expires: 365 });
                cookieCheck();
             });
    }
}

function disconnect() {
    Post("https://www.strava.com/oauth/deauthorize",
          "access_token="+user.access_token,
             function(json) {
                var user = JSON.parse(json);
                Cookies.remove("user-data");
                cookieCheck();
             });
}

function cookieCheck() {
    user = null;
    try {
        user = JSON.parse(Cookies.get("user-data"))
    } catch (e) {
    }

    if (user==null) {
        showSignin(true);
    } else {
        if (Cookies.get("stats") == undefined) {
            Get("stats.php?content="+user.athlete.id, function(json) {});
            var inTenMinutes = new Date(new Date().getTime() + 1 * 60 * 1000);
            Cookies.set("stats", "done", { expires: inTenMinutes });
        }
        showSignin(false);
    }
}

function Post(url, params, callback) {
    var http = new XMLHttpRequest();
    //var url = "get_data.php";
    //var params = "lorem=ipsum&name=binny";
    http.open("POST", url, true);
    http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    http.onreadystatechange = function() { 
        if(http.readyState == 4 && http.status == 200) {
            callback(http.responseText);
        }
    }
    http.send(params);
}

function Get(yourUrl, callback) {
    var Httpreq = new XMLHttpRequest(); // a new request
    Httpreq.overrideMimeType("application/json");
    if (callback != null) {
       Httpreq.onload = function (e) {
          if (Httpreq.readyState === 4) {
             if (Httpreq.status === 200) {
               callback(Httpreq.responseText);
             } else {
               console.error(Httpreq.statusText);
             }
          }
       };
    }
    Httpreq.open("GET", yourUrl, callback != null);
    Httpreq.send(null);
    if (Httpreq.status === 200) {
       return Httpreq.responseText;
    } else {
       return null;
    }
}

function showSignin(state) {
    var signin = document.getElementById("logged-out");
    if (signin != undefined) {
       if (state) signin.style.visibility = "visible";
       else signin.style.visibility = "hidden";
    }

    signin = document.getElementById("div-user-id");
    var username = document.getElementById("username");
    if (signin != undefined && username != undefined) {
       if (!state) signin.style.visibility = "visible";
       else signin.style.visibility = "hidden";
       if (user != null) {
           username.innerHTML = user.athlete.firstname+" "+user.athlete.lastname;
           signin.style.backgroundImage = "url('"+user.athlete.profile_medium+"')";
       }
    }
  
}

function haversineDistance(coords1, coords2, isMiles) {
  function toRad(x) {
    return x * Math.PI / 180;
  }

  var lon1 = coords1[0];
  var lat1 = coords1[1];

  var lon2 = coords2[0];
  var lat2 = coords2[1];

  var R = 6371; // km

  var x1 = lat2 - lat1;
  var dLat = toRad(x1);
  var x2 = lon2 - lon1;
  var dLon = toRad(x2)
  var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
    Math.sin(dLon / 2) * Math.sin(dLon / 2);
  var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  var d = R * c;

  if(isMiles) d /= 1.60934;

  return d;
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
    level=13;
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
    map.addListener('zoom_changed', function(event) {
       level=map.getZoom();
       load();
    });
    google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
       load();
    });
}

var colorMap = new Map();

function getRandomColor(id, name) {
  var letters = '0123456789ABCDEF';
  var color = colorMap.get(id);

  if (color == undefined) {
    // defined
    definedColor.forEach(function(value, key, map) {
       if (name.toLowerCase().indexOf(key) != -1) {
           color = value;
       }
    });

    // random
    if (color == undefined) {
       color='#';
       for (var i = 0; i < 6; i++) {
         color += letters[Math.floor(Math.random() * 16)];
       }
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
   while(table.rows.length > 1) {
      table.deleteRow(1);
   }
   drawnmarkers.forEach(function(elem) {
        elem.setMap(null);
   });
}

function getMarker(latlng, km) {
    var m = null;

    drawnmarkers.forEach(function(elem) {
        if (haversineDistance(latlng, [elem.getPosition().lat(),elem.getPosition().lng()]) <= km) {
            m = elem;
        }
    });

    if (m == null) {
        var json = Get("https://maps.googleapis.com/maps/api/geocode/json?latlng="+latlng[0]+","+latlng[1]+"&key=AIzaSyAwkCujJLlo56P0sDRHH6phn67zkAjPvEo", null);
        if (json != null) {
           var objs = JSON.parse(json);
           var city;
           objs.results[0].address_components.forEach(function (elem) {
              if (elem.types.includes("locality")) {
                 city = elem.short_name;
              }
           });
           m = new google.maps.Marker({
                 position: new google.maps.LatLng(latlng[0], latlng[1]),
                 title:city
               });
           drawnmarkers.push(m);
        }
    }
    return m;
}

function load() {
   cookieCheck();
   showProgress(true);
   clear();
   var nelat = map.getBounds().getNorthEast().lat();
   var nelng = map.getBounds().getNorthEast().lng();
   var swlat = map.getBounds().getSouthWest().lat();
   var swlng = map.getBounds().getSouthWest().lng();

   Get("json_req.php?minEffort="+minSelEffort+
                   "&maxEffort="+maxSelEffort+
                   "&minDist="+minSelDist+
                   "&maxDist="+maxSelDist+
                   "&name="+nameFilter+
                   "&bounds="+nelat+","+nelng+","+swlat+","+swlng, function(json) {
      var minEffort = Number.MAX_SAFE_INTEGER,
          maxEffort = Number.MIN_SAFE_INTEGER,
          minDist = Number.MAX_SAFE_INTEGER,
          maxDist = Number.MIN_SAFE_INTEGER;
      var objs = JSON.parse(json);
      for (var i = 0; i < objs.entries.length; i++) {
          // MARKERS 
          if (level <= 10) {
               marker = getMarker(objs.entries[i].start_latlng, 9);
               marker.setMap(map);
               continue;
          }

          // LINES
          var encoded=objs.entries[i].map.polyline;
          var decode = google.maps.geometry.encoding.decodePath(encoded);

          var line = new google.maps.Polyline({
             path: decode,
             strokeColor: getRandomColor(objs.entries[i].id, objs.entries[i].name),
             strokeOpacity: 0.6,
             strokeWeight:  3
         });

         var content = "<a target=\"_blank\" href=\"https://www.strava.com/segments/"+objs.entries[i].id+"\">"+objs.entries[i].name+"</a>";

         createInfoWindow(objs.entries[i].id, line, content);

         line.setMap(map);

         if (drawnpolylines.get(objs.entries[i].id) != null)
              drawnpolylines.get(objs.entries[i].id).setMap(null);
         drawnpolylines.set(objs.entries[i].id, line);

         // TABLE
         addLine(objs.entries[i].id, "<a target=\"_blank\" href=\"https://www.strava.com/segments/"+objs.entries[i].id+"\">"+objs.entries[i].name+"</a>", Math.round((objs.entries[i].distance/1000)*100)/100, Math.round(objs.entries[i].total_elevation_gain), objs.entries[i].effort_count);

         // SLIDERS
         if (objs.entries[i].effort_count < minEffort) {
             minEffort = objs.entries[i].effort_count;
         }
         if (objs.entries[i].effort_count > maxEffort) {
             maxEffort = objs.entries[i].effort_count;
         }
         if (objs.entries[i].distance < minDist) {
             minDist = objs.entries[i].distance;
         }
         if (objs.entries[i].distance > maxDist) {
             maxDist = objs.entries[i].distance;
         }

      }
      if (minSelEffort == null && maxSelEffort == null && minEffort != Number.MAX_SAFE_INTEGER && maxEffort != Number.MIN_SAFE_INTEGER)
	      setSliderValues("#efforts", 0, 10000, [ minEffort, maxEffort]);
      if (minSelDist == null && maxSelDist == null && minDist != Number.MAX_SAFE_INTEGER && maxDist != Number.MIN_SAFE_INTEGER)
	      setSliderValues("#dist", 0, 100, [ Math.round(minDist/1000), Math.round(maxDist/1000)]);

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

var code=<?php echo json_encode($code);?>;
var err=<?php echo json_encode($err);?>;

if (code != null || err != null) {
    stravaOAuth(code, err);
}

</script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?key=AIzaSyAbH0GjIPauLUiSZpn-xDclEa_AezdMUQA&libraries=geometry&callback=initialize"></script>
</body>
</html>
