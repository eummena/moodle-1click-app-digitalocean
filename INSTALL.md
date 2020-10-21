```bash
git clone https://git.eummena.org/eummena/dev/eummoodle/moodle-on-do-marketplace/
cd moodle-on-do-marketplace/fabric/template
# Create first a new VM with root ssh access via public keys on Digital Ocean
# Run a fabric testbuild
fab testbuild -H $VM_IP

# Run a fabric build
# fab build -H $VM_IP
```