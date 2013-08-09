#!/bin/sh

# Path to this script's directory
dir=$(cd `dirname $0` && pwd)

. $dir/end_selenium.sh

# download selenium
SERVER_FILE=selenium-server-standalone.jar
if [ ! -f "$dir/$SERVER_FILE" ]; then
    wget http://selenium.googlecode.com/files/selenium-server-standalone-2.33.0.jar --progress=dot -e dotbytes=5M -O "$dir/$SERVER_FILE"
fi

SELENIUM_ARGS="-Dwebdriver.enable.native.events=1"

echo "[Kdyby] Launching selenium server..."
if [ -x "$(which Xephyr)" ]; then # apt-get install xserver-xephyr fvwm
	echo "[Kdyby] launching Xephyr"

	export DISPLAY=:0
	Xephyr :1 -screen 1280x768 -ac &
	export DISPLAY=:1
	sleep 3
	fvwm &
	sleep 1
	java -jar "$dir/$SERVER_FILE" $SELENIUM_ARGS 2>&1 &

elif [ -x "$(which Xnest)" ] ; then # apt-get install xnest fvwm
	echo "[Kdyby] launching Xnest"

	export DISPLAY=:0
	Xnest :1 -display :0 -ac &
	export DISPLAY=:1
	xrandr -s 1280x768
	sleep 2
	fvwm &
	sleep 1
	java -jar "$dir/$SERVER_FILE" $SELENIUM_ARGS 2>&1 &

elif [ -x "$(which xvfb-run)" ]; then # apt-get install xvfb
	echo "[Kdyby] launching Xvfb"

	export AUTHFILE=$(tempfile -n "$dir/Xauthority")
	xvfb-run --auto-servernum --server-num=1 -e /dev/stdout --server-args="-screen 1, 1024x768x24" java -jar "$dir/$SERVER_FILE" $SELENIUM_ARGS 2>&1 &

else
	echo "[Kdyby] found nothing to cage selenium browsers in, prepare for your focus to be stolen!"

	java -jar "$dir/$SERVER_FILE" $SELENIUM_ARGS 2>&1 &
fi

echo "[Kdyby] Waiting for selenium to boot up"

wget --retry-connrefused --tries=60 --waitretry=1 --output-file=/dev/null http://127.0.0.1:4444/wd/hub/status -O /dev/null
if [ ! $? -eq 0 ]; then
	echo "[Kdyby] failed"
	. $dir/end_selenium.sh
	exit 1
else
	echo "[Kdyby] selenium server started"
	echo
fi
