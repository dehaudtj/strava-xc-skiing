#!/bin/bash
basedir=$(cd `dirname $0` && pwd -P)
strava_cookies=$basedir/stravacookies.txt
strava_url=https://www.strava.com/dashboard
logfile=$basedir/`basename $0`.log

echo -n "`date +%c` : " >> $logfile
if wget -qO- --save-cookies=$strava_cookies --keep-session-cookies --load-cookies=$strava_cookies $strava_url | grep -q "html class.*logged.out"; then
	echo "Logged out from Strava" >> $logfile
	exit 1
fi
echo "Logged in" >> $logfile
