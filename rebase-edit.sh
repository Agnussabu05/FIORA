#!/bin/sh
# This script will be used to automatically edit the rebase todo list
sed -i '1s/pick/edit/' "$1"
