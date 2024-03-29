#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the 
# SSH service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################

# Regenerate the missing SSH keys if missing:
if ! test -e /etc/ssh/ssh_host_rsa_key; then
	ssh-keygen -N "" -t rsa -f /etc/ssh/ssh_host_rsa_key
	ssh-keygen -N "" -t ed25519 -f /etc/ssh/ssh_host_ed25519_key
	ssh-keygen -N "" -t ecdsa -f /etc/ssh/ssh_host_ecdsa_key
fi

# Generate a "id_rsa.pub" file if it doesn't exist already:
test -f /root/.ssh/id_rsa || ssh-keygen -t rsa -f /root/.ssh/id_rsa -N ""

# Turn the blue light on on the side opposite the network ports:
echo 1 > /sys/class/leds/bpi-r2:pio:blue/brightness

exit 0
