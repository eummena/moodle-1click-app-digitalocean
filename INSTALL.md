# Moodle 1-click app for DigitalOcean Marketplace

> @copyright  2020 onwards Eummena (https://eummena.org)
> @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
> @tracker    For any inquires or support: help@eummena.org 


To try-out the installation locally, run the following commands on your workstation:

```sh
$ git clone ...
$ cd moodle-on-do-marketplace/fabric/template
# Create first a new VM with root ssh access via public keys on DigitalOcean
# Run a fabric testbuild (VM_IP is the IP of your new VM)
$ fab testbuild -H $VM_IP

# Run a fabric build
# fab build -H $VM_IP

# PREFERRED METHOD
# Run a packer build with a new automatically provisioned droplet
packer build marketplace-image.json
```
