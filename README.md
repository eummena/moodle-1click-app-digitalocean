This repository contains the source files (scripts, files) for building the Moodle 1-click droplet image for Digital Ocean marketplace mainly via packer.

The software for preparing the droplet image is `packer`. See also [INSTALL.md](./INSTALL.md) for additional technical instructions on running packer & fab locally.


:point_right: **[Lauch now your Moodle learnign platfomr on your droplet on Digital Ocean cloud](https://marketplace.digitalocean.com/apps/moodle?refcode=93d5584474d7)**

## Moodle by Eummena :: 1-Click App for DigitalOcean MarketPlace

**[Moodle](https://moodle.org)** is the world's most popular learning management system. As the leading open source Learning Platform it is designed to provide learners, educators, and administrators with a single robust, secure and integrated solution to create personalised learning environments. This 1-Click app, maintained by **[Eummena](https://eummena.org)**, Premium Moodle Partner, will allow you to start creating your online learning site in minutes on DigitalOcean cloud!

## Getting started instructions
In addition to the Moodle Open Source Software installation, this 1-Click also:

1. Preinstalls the Certbot tool with the apache plugin.
2. Enables the UFW firewall to allow access only for SSH (port 22, rate limited), HTTP (port 80), and HTTPS (port 443).
3. Sets a MariaDB root password, protecting access outside the localhost (if you need to gain access to the root user, get the password from `/root/.root_mysql_password` file or follow [these instructions](https://www.digitalocean.com/community/tutorials/how-to-reset-your-mysql-or-mariadb-root-password)).
4. Creates the Moodle cron job for the www-data user

*For the impatient:* After you create a Moodle 1-Click Droplet, you can continue with the Moodle installation on your browser, using your Droplet’s IP address. Just fire your browser, enter the IP of your droplet and follow the instructions there. You will have to choose the MariaDB driver from the available options and enter the connection details for the database (user: `root`, for password check point 4 above).

### Preparing your Moodle site for production

In order to use your Moodle for production, you must first configure a DNS entry with the fully qualified domain name (*FQDN*) to point to the IP of your Moodle 1-Click Droplet. This way, you will have a proper URL for your Moodle site and you will also be able to enable https access (instead of the insecure http).

To proceed with these steps, you’ll need to [log into the Droplet via SSH](https://www.digitalocean.com/docs/droplets/how-to/connect-with-ssh/).

From a terminal on your local computer, connect to the Moodle 1-Click Droplet as root:

```sh
$ ssh root@your_droplet_public_ipv4
```

*Note:* If you did not add an SSH key when you created the Droplet, you’ll first be prompted to reset your root password.

Then, to automatically apply Let's Encrypt SSL and enable https access for your Moodle droplet, use the pre-installed `certbot` tool. You will be asked to enter your domain name, make sure you enter your configured FQDN (for example: `moodle.example.com`). Enter y and your email address to finish the process and make sure to allow the tool to configure Apache automatically; enter y to force HTTPS rules to be applied!

```bash
# certbot --apache
No names were found in your configuration files. Please enter in your domain 
name(s) (comma and/or space separated)  (Enter 'c' to cancel):
moodle.example.com 
...
Do you wish to force HTTPS rewrite rule for this domain? [y/N] 
y
```

After successfully completing these steps, you are now ready to proceed with Moodle installation on your browser. Just fire your browser at your FQDN and follow the instructions there. You will have to choose the MariaDB driver from the available options and enter the connection details for the database (user: `root`, for password check point 4 above).

### Operation and further support

After you have successfully installed Moodle from your browser, you can further explore the files in your droplet:

- The "web root" folder with Moodle’s code is in `/var/www/moodle-1click` and the main configuration file is `/var/www/moodle-1click/config.php` (which also includes the Moodle Database configuration details). 
- All Moodle data are stored under `/var/www/moodledata`. 
- The moodle cron job is running every one minute, and the related log files are under the folder: `/var/log/moodle_cron` (they are rotated every 30 days).

For any feedback or technical support, use the tracker on the official repo maintained by Eummena, Premium Moodle Partner: [https://github.com/eummena/moodle-1click-do/issues](https://github.com/eummena/moodle-1click-do/issues).


