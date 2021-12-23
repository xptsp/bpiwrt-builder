#!/bin/bash
#############################################################################
# This helper script establishes all of the iptables rules required by the
# WebUI configuration for our router to operate properly.
#############################################################################
# Comments starting with "CTA:" and iptables commands from source:
#	https://javapipe.com/blog/iptables-ddos-protection/
# Comments starting with "CTB:" and iptables commands from source:
#	https://offensivesecuritygeek.wordpress.com/2014/06/24/how-to-block-port-scans-using-iptables-only/
#############################################################################
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

#############################################################################
function create_chain()
{
	if ! iptables --list-rules | grep "\-N $1" >& /dev/null; then 
		iptables -N $1
	fi
}

#############################################################################
# RELOAD => Move the new configuration file into place and reload settings:
#############################################################################
if [[ "$1" == "reload" ]]; then
	if test -f /tmp/firewall; then
		mv /tmp/router-settings /etc/default/router-settings
		chown root:root /etc/default/router-settings
	fi
fi
[[ -f /etc/default/router-settings ]] && source /etc/default/router-settings

#############################################################################
# START => Initializes the optionless base iptable firewall configuration:
#############################################################################
if [[ "$1" == "start" ]]; then
	#############################################################################
	# CTB: Flush all the iptables Rules
	#############################################################################
	iptables -F -t filter
	iptables -F -t mangle
	iptables -F -t nat

	#############################################################################
	# CTA: Set default policy to ACCEPT for input, output and forwarding:
	#############################################################################
	iptables -P INPUT ACCEPT
	iptables -P FORWARD ACCEPT
	iptables -P OUTPUT ACCEPT

	#############################################################################
	# CTB: Accept loopback input
	#############################################################################
	iptables -A INPUT -i lo -p all -j ACCEPT

	#############################################################################
	# Block user "vpn" from accessing anything other than the "lo" interface:
	#############################################################################
	iptables -A OUTPUT ! -o lo -m owner --uid-owner vpn -j DROP

	#############################################################################
	# These are global rules that will always be set!
	#############################################################################
	# Allow any related and established connections on input and forward chains:
	iptables -A INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT

	# CTA: This rule blocks all packets that are not a SYN packet and don’t
	# belong to an established TCP connection.
	iptables -t mangle -A PREROUTING -m conntrack --ctstate INVALID -j DROP

	# CTA: This blocks all packets that are new (don’t belong to an established
	# connection) and don’t use the SYN flag.  This rule is similar to the “Block
	# Invalid Packets” one, but we found that it catches some packets that the other one doesn’t.
	iptables -t mangle -A PREROUTING -p tcp ! --syn -m conntrack --ctstate NEW -j DROP

	# CTA: The above iptables rule blocks new packets (only SYN packets can be
	# new packets as per the two previous rules) that use a TCP MSS value that
	# is not common. This helps to block dumb SYN floods.
	iptables -t mangle -A PREROUTING -p tcp -m conntrack --ctstate NEW -m tcpmss ! --mss 536:65535 -j DROP

	# CTA: The above ruleset blocks packets that use bogus TCP flags, ie. TCP flags that legitimate packets wouldn’t use.
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags FIN,SYN FIN,SYN -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags SYN,RST SYN,RST -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags FIN,RST FIN,RST -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags FIN,ACK FIN -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags ACK,URG URG -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags ACK,PSH PSH -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags ALL NONE -j DROP

	# Redirect incoming port 67 to port 68:
	iptables -A INPUT -p udp -m udp --sport 67 --dport 68 -j ACCEPT

	# CTB: Dropping all invalid packets
	iptables -A INPUT -m state --state INVALID -j DROP
	iptables -A FORWARD -m state --state INVALID -j DROP
	iptables -A OUTPUT -m state --state INVALID -j DROP

	# CTB: flooding of RST packets, SMURF attack Rejection
	iptables -A INPUT -p tcp -m tcp --tcp-flags RST RST -m limit --limit 2/second --limit-burst 2 -j ACCEPT

	#############################################################################
	# Rules for input, output and forwarding for internet-facing interfaces:
	#############################################################################
	create_chain SERVICES
	create_chain MINIUPNPD
	create_chain WAN_IN
	create_chain WAN_OUT
	create_chain WAN_FORWARD
	[[ -z "${wan_ifaces[@]}" ]] && wan_ifaces=(wan)
	for IFACE in ${wan_ifaces[@]}; do 
		# CTA: Allow masquerading to the wan port:
		iptables -t nat -A POSTROUTING -o ${IFACE} -j MASQUERADE
		# Direct interface to check SERVICES chain for further rules:
		iptables -A INPUT -i ${IFACE} -j SERVICES
		# Our "intervention" for miniupnpd to work properly:
		iptables -A FORWARD -i ${IFACE} ! -o ${IFACE} -j MINIUPNPD
		# Direct interface to check WAN_IN chain for further INPUT rules:
		iptables -A INPUT -i ${IFACE} -j WAN_IN
		# Drop any connections coming from the interface:
		iptables -A INPUT -i ${IFACE} -j DROP
		# Direct interface to check WAN_OUT chain for further OUTPUT rules:
		iptables -A OUTPUT -o ${IFACE} -j WAN_OUT
		# Direct interface to check WAN_FORWARD chain for further FORWARD rules:
		iptables -A FORWARD -i ${IFACE} -j WAN_FORWARD
		# Allow related and established connections to be forwarded from the interface to other interfaces:
		iptables -A FORWARD -i ${IFACE} ! -o ${IFACE} -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT
		# Drop any connections being forwarded from the interface:
		iptables -A FORWARD -i ${IFACE} ! -o ${IFACE} -j DROP
	done

	#############################################################################
	# We need to call ourselves to complete other tasks:
	#############################################################################
	$0 reload

#############################################################################
# RELOAD => Setup the WebUI customizable firewall rules:
#############################################################################
elif [[ "$1" == "reload" ]]; then
	iptables -F WAN_IN
	iptables -F WAN_OUT
	iptables -F WAN_FORWARD
	$0 dmz
	$0 firewall

#############################################################################
# DMZ => Setup the DMZ server rule:
#############################################################################
# "Default DMZ Server" iptables commands from source:
#   https://www.linuxquestions.org/questions/linux-networking-3/iptables-dmz-host-490491/#post2454665
# "MAC Address Filter" iptables parameters from source:
#   https://tecadmin.net/mac-address-filtering-using-iptables/
#############################################################################
elif [[ "$1" == "dmz" ]]; then
	# Is the DMZ server enabled?  If not, exit without error:
	[[ "${enable_dmz:-"N"}" == "N" ]] && exit 0

	# Remove any DMZ-commented lines from the iptables rules list:
	iptables --list-rules | grep "\-m comment \-\-comment DMZ" | while read rule; do iptables $(echo $rule | sed "s|^-A|-D|g"); done

	# Can we locate the DMZ server?  If not, exit without error:
	server=($(arp | grep ${dmz_ip_addr:-"${dmz_mac_addr}"} 2> /dev/null))
	[[ -z "${server[0]}" ]] && exit 0
	dmz_ip_addr=${server[0]}
	iface=${server[-1]}

	# Restrict access to the DMZ based on the WebUI settings (IP range/IP mask/MAC address):
	unset params
	subnet=$(echo ${dmz_range_from} | awk 'BEGIN{FS=OFS="."} NF--')
	if [[ "${dmz_src_type}" == "range" && ! -z "${dmz_range_from}" ]]; then
		if [[ -z "${dmz_range_to}" || "${dmz_range_from/$subnet/}" == "${dmz_range_to}" ]]; then
			params="--s ${dmz_range_from}"
		else
			params="--src-range ${dmz_range_from}-${subnet}.${dmz_range_to}"
		fi
	elif [[ "${dmz_src_type}" == "mask" && ! -z "${dmz_mask_ip}"  && ! -z "${dmz_mask_bits}" ]]; then
		params="--src-range ${dmz_mask_ip}/${dmz_mask_bits}"
	fi

	# Create the iptable rule for the DMZ server:
	iptables -A WAN_FORWARD -i wan -o ${iface} ${params} -d ${dmz_ip_addr} -m comment --comment DMZ -m state --state NEW -j ACCEPT

#############################################################################
# FIREWALL => Set the basic firewall security settings, according to WebUI:
#############################################################################
# "Filter Multicast" iptable commands from source:
# 	https://jeanwan.wordpress.com/2013/08/14/block-multicast-packets-by-using-ipfilter/
# "Drop Pings from Internet" iptable commands from source:
#   https://vitux.com/how-to-block-allow-ping-using-iptables-in-ubuntu/
#############################################################################
elif [[ "$1" == "firewall" ]]; then

	# CTB: for SMURF attack protection
	iptables -A WAN_IN -p icmp -m icmp --icmp-type address-mask-request -j DROP
	iptables -A WAN_IN -p icmp -m icmp --icmp-type timestamp-request -j DROP

	#############################################################################
	# OPTION "block_dot" => Drop outgoing DoT (DNS-over-TLS port 853) requests:
	#############################################################################
	[[ "${block_dot:-"Y"}" == "Y" ]] && iptables -A WAN_OUT -p tcp --dport 853 -j DROP

	#############################################################################
	# OPTION "block_doq" => Drop outgoing DoT (DNS-over-QUIC port 8853) requests:
	#############################################################################
	[[ "${block_doq:-"Y"}" == "Y" ]] && iptables -A WAN_OUT -p tcp --dport 8853 -j DROP

	#############################################################################
	# OPTION "drop_ping" => Disable ping response from internet
	#############################################################################
	[[ "${drop_ping:-"Y"}" == "Y" ]] && iptables -A WAN_IN -p icmp --icmp-type echo-request -j DROP

	#############################################################################
	# OPTION "drop_port_scan" => Protect against port scans:
	#############################################################################
	if [[ "${drop_port_scan:-"Y"}" == "Y" ]]; then
		# CTB: Create chain PORTSCAN and add logging to it (if requested) with your preferred prefix
		create_chain PORTSCAN
		[[ "${log_port_scan:-"N"}" == "Y" ]] && iptables -A PORTSCAN -j LOG --log-level 4 --log-prefix 'Blocked_scans '
		iptables -A PORTSCAN -j DROP

		# CTB: Create chain UDP with custom logging (if requested) 
		create_chain UDP
		[[ "${log_udp_flood:-"N"}" == "Y" ]] && iptables -A UDP -j LOG --log-level 4 --log-prefix 'UDP_FLOOD '
		iptables -A UDP -p udp -m state --state NEW -m recent --set --name UDP_FLOOD
		iptables -A UDP -j DROP

		# CTB: Accept all connections for ports from 32768 to 61000.  These are mostly
		# used in ACK and don’t have too many services hosted here.
		iptables -A WAN_IN -p tcp -m tcp --destination-port 32768:61000 -j ACCEPT

		# CTB: Anyone who previously tried to portscan or UDP flood us are locked out for an entire day.
		# Their IP’s are stored in a list called ‘PORTSCAN’:
		iptables -A WAN_IN -m recent --name PORTSCAN --rcheck --seconds 86400 -j PORTSCAN
		iptables -A WAN_IN -m recent --name UDP_FLOOD --rcheck --seconds 86400 -j PORTSCAN

		# CTB: Once the day has passed, remove them from the PORTSCAN list:
		iptables -A WAN_IN -m recent --name PORTSCAN --remove
		iptables -A WAN_IN -m recent --name UDP_FLOOD --remove

		# CTB: Anyone who does not match the above rules (open ports) is trying to access a port our sever does not
		# serve. So, as per design we consider them port scanners and we block them for an entire day
		# These rules add scanners to the PORTSCAN list, and log the attempt:
		iptables -A WAN_IN -p tcp -m tcp -m recent -m state --state NEW --name PORTSCAN --set -j PORTSCAN

		# CTB: UDP
		iptables -A WAN_IN -p udp -m state --state NEW -m recent --set --name Domainscans
		iptables -A WAN_IN -p udp -m state --state NEW -m recent --rcheck --seconds 5 --hitcount 5 --name Domainscans -j UDP
	fi

	#############################################################################
	# OPTION "drop_ident" => Block port 113 (IDENT) from the Internet
	#############################################################################
	[[ "${drop_ident:-"Y"}" == "Y" ]] && iptables -A WAN_IN -p tcp --destination-port 113 -j DROP

	#############################################################################
	# OPTION "drop_multicast" => Drop multicast packets from the Internet:
	#############################################################################
	if [[ "${drop_multicast:-"N"}" == "Y" ]]; then
		iptables -A WAN_OUT -o wan -m pkttype --pkt-type multicast -j DROP
		iptables -A WAN_IN -i wan -m pkttype --pkt-type multicast -j DROP
	fi
fi