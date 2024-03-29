#!/bin/bash
#############################################################################
# This script details the modifications done to the PiVPN installer for
# use in the BPiWRT router.  These changes allow the installer to delay the
# creation of certificates and Diffie-Hellman parameters.
#############################################################################

# Set repository head to commit "f80b0a7962d91862132c0a4abd65c1e67bd37bd7" (Dec 3rd, 2021):
cd /usr/local/src/pivpn
git reset --hard f80b0a7962d91862132c0a4abd65c1e67bd37bd7

# OPENVPN: Modify the PiVPN installer so that we can delay creation of certificates and such:
MODDED=/opt/bpi-r2-router-builder/modded_pivpn_install.sh
cp ${DIR}/auto_install/install.sh ${MODDED}
sed -i 's|setStaticIPv4(){|setStaticIPv4(){\n\treturn;|g' ${MODDED}
sed -i "/restartServices$/d" ${MODDED}
sed -i "/confLogging$/d" ${MODDED}
sed -i 's|confOVPN$|createOVPNuser|g' ${MODDED}
sed -i '/confNetwork$/d' ${MODDED}
sed -i "s|confOpenVPN(){|generateServerName(){|" ${MODDED}
sed -i "s|# Backup the openvpn folder|echo \"\$SERVER_NAME\" >> /etc/openvpn/.server_name\n}\n\nbackupOpenVPN(){\n\t# Backup  the openvpn folder|" ${MODDED}
sed -i "s|\tif \[ -f /etc/openvpn/server.conf \]; then|}\n\nconfOpenVPN(){\n\tif [ -f /etc/openvpn/server.conf ]; then|" ${MODDED}
sed -i 's|\tcd /etc/openvpn/easy-rsa|}\n\nGenerateOpenVPN() {\n\tcd  /etc/openvpn/easy-rsa|' ${MODDED}
sed -i "s|  if ! getent passwd openvpn; then|}\n\ncreateOVPNuser(){\n  if ! getent  passwd openvpn >\& /dev/null; then|" ${MODDED}
sed -i "s|  \${SUDOE} chown \"\$debianOvpnUserGroup\" /etc/openvpn/crl.pem|}\n\ncreateServerConf(){\n\t\${SUDOE}  chown \"\$debianOvpnUserGroup\" /etc/openvpn/crl.pem|" ${MODDED}
sed -i "s|whiptail --msgbox --backtitle \"Setup OpenVPN\"|echo; #whiptail --msgbox --backtitle \"Setup OpenVPN\"|g" ${MODDED}
sed -i "s|main \"\$@\"|[[ -z \"\${SKIP_MAIN}\" ]] \&\& main \"\$@\"|g" ${MODDED}
sed -i "s|\${SUDOE} install -m 644 \"\${pivpnFilesDir}\"/files/etc/openvpn/easy-rsa/pki/ffdhe\"\${pivpnENCRYPT}\".pem pki/dh\"\${pivpnENCRYPT}\".pem|curl https://2ton.com.au/getprimes/random/dhparam/\${pivpnENCRYPT} -o pki/dh\${pivpnENCRYPT}.pem|" ${MODDED}
sed -i "s|if [ \"\$USING_UFW\" -eq 0 ]; then|if [ \"\$USING_UFW\" -eq 2 ]; then|" ${MODDED}
sed -i "s|server.conf|pivpn0.conf|g" ${MODDED}
sed -i "s|pivpn0.config.txt|server_config.txt|" ${MODDED}
sed -i "s|if \[ \"\$USING_UFW\" -eq 0 \]; then|if \[ \"\$USING_UFW\" -eq 2 ]; then|" ${MODDED}

# WIREGUARD: Modify the PiVPN installer so that we can delay creation of keys and such.
# Additionally, we need to stop the installer from installing WireGuard dkms module, as they are included already. 
sed -i "s|WIREGUARD_BUILTIN=0|WIREGUARD_BUILTIN=1|" ${MODDED}
sed -i "/installWireGuard$/d" ${MODDED}
sed -i "s|^confWireGuard|backupWireGuard|" ${MODDED}
sed -i "s|^\t# Ensure that only|}\n\nConfWireGuard(){\n\t# Ensure that only|" ${MODDED}
sed -i "/confWireGuard$/d" ${MODDED}
