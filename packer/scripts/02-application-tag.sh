#!/bin/bash

# @copyright  2020 onwards Eummena (https://eummena.org)
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
# @tracker    For any inquires or support: help@eummena.org 


################################
## PART: Write the application tag
##
## vi: syntax=sh expandtab ts=4

build_date=$(date +%Y-%m-%d)
distro="$(lsb_release -s  -i)"
distro_release="$(lsb_release -s  -r)"
distro_codename="$(lsb_release -s -c)"
distro_arch="$(uname -m)"

cat >> /var/lib/digitalocean/application.info <<EOM
application_name="${application_name}"
build_date="${build_date}"
distro="${distro}"
distro_release="${distro_release}"
distro_codename="${distro_codename}"
distro_arch="${distro_arch}"
application_version="${application_version}"
EOM
