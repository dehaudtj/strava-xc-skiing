#!/bin/bash
# All segments ID from a given activity
#wget -qO- -x --load-cookies /home/dehaudtj/prj/tmp/stravacookies.txt https://www.strava.com/activities/1304443530 | grep -o '"segment_id":[0-9]*' | sort -u | awk -F':' '{print $2}'

# All activities from segment page
#wget -qO- -x --load-cookies /home/dehaudtj/prj/tmp/stravacookies.txt https://www.strava.com/segments/14263303 | grep -o '/segment_efforts/[0-9]*'

#set -x

function store()
{
	comm -13  <(sort $db) <(echo $1 | tr ' ' '\n' | sort) >> $db
}

function getAllSeg()
{
	wget -qO- -x --load-cookies $strava_cookies $1 | grep -o '"segment_id":[0-9]*' | sort -u | awk -F':' '{print $2}'
}

function getAct()
{
	wget -qO- -x --load-cookies $strava_cookies $1 | grep -o '/segment_efforts/[0-9]*'
}

function getLocation()
{
	wget -qO- -x --load-cookies $strava_cookies $1 | grep "starting in" | sed -r 's/.*starting in ([a-zA-Z-]*).*/\1/g'
}

activity=$1
strava_base=https://www.strava.com
strava_cookies=/home/websites/jln-web.fr/strava/scripts/stravacookies.txt
default_db=segments.txt
db=$default_db

if [ -z $activity ]; then
	echo "usage: $0 <strava_activity_url>"
	exit 1
fi

#test connection
if wget -qO- -x --load-cookies $strava_cookies $activity | grep "html class.*logged.out" >/dev/null 2>&1; then
	echo "Logged out from Strava, require refresh of '$strava_cookies'"
	exit 1
fi

echo "Get segments from $activity"
segments_list=$(getAllSeg $activity)

#get location
segment=$(echo $segments_list | awk '{print $1}')
location=$(getLocation "$strava_base/segments/$segment")
if [ "$location" != "" ]; then
	db="$location.txt"
fi

touch $db

while [ "$segments_list" != "" ]
do
	segment=$(echo $segments_list | awk '{print $1}')
	segments_list=$(echo $segments_list | sed "s/$segment//g")
	if grep -q $segment $db; then
		echo "$segment already registered, continue..."
		continue
	fi

	echo "Get activities from $strava_base/segments/$segment"
	activities_list=$(getAct "$strava_base/segments/$segment")

	for act in $activities_list
	do
		echo "Get segments from $strava_base/$act"
		new_segments_list=$(getAllSeg "$strava_base/$act")
		new_segments_list=$(comm -13  <(echo $segments_list | tr ' ' '\n' | sort) <(echo $new_segments_list | tr ' ' '\n' | sort))
		new_segments_list=$(comm -13  <(sort $db) <(echo $new_segments_list | tr ' ' '\n' | sort))
		segments_list="$segments_list $new_segments_list"
	done

	store $segment

	remains=$(echo $segments_list | tr ' ' '\n' | wc -l)
	echo "Remaining segments to process: $remains"
done
