#!/bin/sh

# Path to this script's directory
dir=$(cd `dirname $0` && pwd)
echo $dir

# download selenium
SERVER_FILE=selenium-server-standalone.jar
if [ ! -f "$dir/$SERVER_FILE" ]; then
    wget http://selenium.googlecode.com/files/selenium-server-standalone-2.33.0.jar --progress=dot -e dotbytes=5M -O "$dir/$SERVER_FILE"
fi

. $dir/end_selenium.sh > /dev/null 2>&1 &

echo "Launching selenium server..."
if [ -x "$(which Xephyr)" ]; then # apt-get install xserver-xephyr fvwm
	echo "launching Xephyr"

	DISPLAY=:0 Xephyr :1 -screen 1024x768 -ac &
	export DISPLAY=:1
	# xrandr -s 1024x768
	fvwm &
	sleep 2
	java -jar "$dir/$SERVER_FILE" 2>&1 &

elif [ -x "$(which Xnest)" ] ; then # apt-get install xnest fvwm
	echo "launching Xnest"

	DISPLAY=:0 Xnest :1 -display :0 -ac &
	export DISPLAY=:1
	xrandr -s 1024x768
	fvwm &
	sleep 2
	java -jar "$dir/$SERVER_FILE" 2>&1 &

elif [ -x "$(which xvfb-run)" ]; then # apt-get install xvfb
	echo "launching Xvfb"

	export AUTHFILE=$(tempfile -n "$dir/Xauthority")
	xvfb-run --auto-servernum --server-num=1 -e /dev/stdout --server-args="-screen 1, 1024x768x24" java -jar "$dir/$SERVER_FILE" 2>&1 &

else
	echo "found nothing to cage selenium browsers in, prepare for your focus to be stolen!"

	java -jar "$dir/$SERVER_FILE" 2>&1 &
fi

wget --retry-connrefused --tries=60 --waitretry=1 --output-file=/dev/null http://127.0.0.1:4444/wd/hub/status -O /dev/null
if [ ! $? -eq 0 ]; then
	echo "failed"
	. $dir/end_selenium.sh
	exit 1
else
	echo "selenium server started"
	echo
fi
