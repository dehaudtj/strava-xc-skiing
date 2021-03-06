#!/bin/bash

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
	# get page count, or nothing if only 1 page
	# wget -qO- -x --load-cookies=$strava_cookies $1 | grep -Eo "[^_]page=[0-9]*" | awk -F'=' '{print $2}' | sort -n | tail -1
	# remain to loop over pages instead
	# for page in 1..$page_count
	# do
	#   wget -qO- -x --load-cookies $strava_cookies $1/leaderboard?page=$page | grep -o '/segment_efforts/[0-9]*'
	# done
	# TO BE TESTED
	# LIMIT TO MAX COUNT OF PAGES ?!
	wget -qO- -x --load-cookies $strava_cookies $1 | grep -o '/segment_efforts/[0-9]*'
}

function getLocation()
{
	wget -qO- -x --load-cookies $strava_cookies $1 | grep "starting in" | sed -r 's/.*starting in ([a-zA-Z-]*).*/\1/g'
}

basedir=$(cd `dirname $0` && pwd)
activity=$1
strava_base=https://www.strava.com
strava_cookies=$basedir/stravacookies.txt
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
