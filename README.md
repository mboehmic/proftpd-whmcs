# proftpd-whmcs
whmcs Module for proftpd

This is an addon for whmcs 
It is based on:		https://github.com/eksoverzero/whmcs-freeradius/tree/refactor

Installation
Upload the "proftpd" folder to your whmcs installation to
-> modules\servers

Server Settings:
Mandatory fields:
*				username and password (mysql username and password on the proftpd server)
*				ip-adress field is required
*			  access hash -> create on line with the name of the mysql database containing ftp users
*				not used: Hostname 

Tested on 
- Debian 11
- whmcs 8.3.2
- proftpd Version 1.3.7a on Debian 11
