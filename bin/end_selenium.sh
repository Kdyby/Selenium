#!/bin/sh

kill `ps -ef|grep -i selenium| grep -v grep| awk '{print $2}'`
killall Xvfb
killall Xephyr
killall Xnest
killall fvwm
