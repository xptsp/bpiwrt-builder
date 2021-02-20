#!/bin/bash
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi


function check_params()
{
	if [[ -z "${1}" ]]; then
		echo "ERROR: Must specify device name to mount!"
		exit 1
	fi
	if [[ ! -e "/dev/${1}" ]]; then
		echo "ERROR: Invalid or non-existant device specified!"
		exit 1
	fi
}

function usb_mount()
{
	# Use the pmount command to mount to a directory with the volume label.
	# Failing that, mount to the device name.
	DEV=/dev/${1}
	MEDIA=$(blkid ${DEV} -o export | grep "LABEL=" | cut -d"=" -f 2)
	LABEL=${MEDIA:="${1}"}
	MEDIA=/media/"${LABEL// /_}"
	/usr/bin/pmount --umask 000 ${DEV} ${MEDIA}

	# Write Samba configuration for the device:
	cat << EOF > /etc/samba/smb.d/${LABEL}.conf
[${LABEL}]
comment=${LABEL}
path=${MEDIA}
browseable=Yes
writeable=Yes
only guest=no
create mask=0777
directory mask=0777
public=no
EOF
}

function add_shares()
{
	# Include Samba share in the configuration:
	ls /etc/samba/smb.d/* 2> /dev/null | sed -e 's/^/include = /' > /etc/samba/includes.conf

	# Reload the Samba configuration:
	smbcontrol all reload-config
}

function usb_umount()
{
	# Use the pmount command to mount to a directory with the volume label.
	# Failing that, mount to the device name.
	DEV=/dev/${1}
	/usr/bin/pumount ${DEV}
}

function remove_shares()
{
	# Write Samba configuration for the device:
	if ls /etc/samba/smb.d/*.conf >& /dev/null; then
		for conf in /etc/samba/smb.d/*.conf; do
			test -d "$(cat ${conf} | grep "path=" | cut -d"=" -f 2)" || rm ${conf}
		done
	fi
}

case "$1" in
	"start")
		check_params $2
		usb_mount $2
		add_shares
		;;
	"stop")
		check_params $2
		usb_umount $2
		remove_shares
		add_shares
		;;
	"prep")
		add_shares
		remove_shares
		;;
	*)
		echo "Syntax: usb-helper.sh [start|stop|prep]"
		;;
esac

