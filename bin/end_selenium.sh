#!/bin/sh

kill `ps -ef|grep -i selenium-server-standalone| grep -v grep| awk '{print $2}'` > /dev/null 2>&1
killall Xvfb > /dev/null 2>&1
killall Xephyr > /dev/null 2>&1
killall Xnest > /dev/null 2>&1
killall fvwm > /dev/null 2>&1
