#!/bin/bash
#
# Scripts in this directory are run during the build process.
# each script will be uploaded to /tmp on your build droplet, 
# given execute permissions and run.  The cleanup process will
# remove the scripts from your build system after they have run
# if you use the build_image task.
#

# @copyright  2020 onwards Eummena (https://eummena.org)
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
# @tracker    For any inquires or support: help@eummena.org 


# APACHE2
systemctl enable --now php7.3-fpm apache2
a2dismod -f mpm_prefork php*
a2enmod proxy proxy_fcgi mpm_event setenvif
a2enconf php7.3-fpm
a2dissite 000-default.conf
a2ensite moodle.conf
systemctl restart apache2

# MARIADB
systemctl enable --now mariadb

# MOODLE Filesystem
WWW_HOME="/var/www/moodle-1click"
MOODLEDATA="/var/www/moodledata"
mkdir $MOODLEDATA
chown -R www-data:www-data $MOODLEDATA $WWW_HOME

# UFW
ufw allow 443/tcp
ufw allow 80/tcp
ufw allow 22/tcp
ufw --force enable
