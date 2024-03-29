#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the
# docker service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################

# If a partition isn't mounted for docker image storage, try and mount a partition
# with the label "DOCKER" at "/var/lib/docker":
if ! mount | grep " /var/lib/docker " >& /dev/null; then
	eval `blkid --match-token LABEL=DOCKER --output export`
	[[ ! -z "${DEVNAME}" ]] && mount -o noatime ${DEVNAME} /var/lib/docker
fi

# Make some sub-directories in the docker directories:
mkdir -p /var/lib/docker/{bin,data}

# Change the default IP address that containers are bound to:
test -f /etc/default/docker && source /etc/default/docker
NEW_IP=$(cat /etc/network/interfaces.d/${CONTAINER_IFACE:-"br0"} 2>&1 | grep "address" | head -1 | awk '{print $2}')
FILE=/etc/docker/daemon.json
if test -f ${FILE}; then
	if [[ ! -z "${NEW_IP}" ]]; then
		OLD_IP=$(cat ${FILE} | grep '"ip"' | awk '{print $2}' | cut -d\" -f 2)
		[[ "${NEW_IP}" != "${OLD_IP}" ]] && sed -i "s|\"ip\":.*|\"ip\": \"${NEW_IP}\",|g" ${FILE}
	fi
fi

# Add some iptable firewall rules to help docker communicate correctly through 
# the nftables firewall:
iptables -N DOCKER-USER >& /dev/null
if ! iptables --list-rules | grep -q 0xd0cca5e; then 
	iptables -I DOCKER-USER 1 -j MARK --set-mark 0xd0cca5e
	iptables -I DOCKER-USER 2 -m physdev --physdev-is-bridged -j MARK --set-mark 0x10ca1
	iptables -A FORWARD -m mark --mark 0xd0cca5e -j MARK --set-mark 0
fi

# Return error code 0 to caller:
exit 0
