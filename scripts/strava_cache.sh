#!/bin/bash

db=$1

basedir=$(cd `dirname $0` && pwd)

cache_dir="$basedir/.strava"
base_url="https://www.strava.com/api/v3/segments"
source $basedir/strava_tocken.sh
update=0

mkdir -p $cache_dir

if [ -z $db ] || [ ! -f $db ]; then
	echo "usage: $0 <db_file_with_segments_ids>"
	exit 1
fi

count=$(wc -l $db | awk '{print $1}')
i=0

for id in $(cat $db)
do
	((i++))
	echo -ne "$i/$count\033[0K\r"
	segfile=$cache_dir/$id/segment.json
	leadfile=$cache_dir/$id/leaderboard.json
	mkdir -p $cache_dir/$id
	if [ ! -e $segfile ] || [ $update -ne 0 ]; then
		curl -s -G $base_url/$id		-H "Authorization: Bearer $tocken" > $segfile
	fi

	if [ ! -e $leadfile ] || [ $update -ne 0 ]; then
		curl -s -G $base_url/$id/leaderboard	-H "Authorization: Bearer $tocken" > $leadfile
	fi
done
