# proftpd-whmcs
whmcs Module for proftpd

This is an addon for whmcs 
It is based on:		https://github.com/eksoverzero/whmcs-freeradius/tree/refactor

Installation
Upload the "proftpd" folder to your whmcs installation to
-> modules\servers

Server Settings:
Mandatory fields:
			username and password (mysql username and password on the proftpd server)
			ip-adress field is required
		  access hash -> create on line with the name of the mysql database containing ftp users
			not used: Hostname 

Product Settings:
- The proftpd Group is the "gid" od the group in the ftpgroup table. Same for the field "proftpd userid". 
- Both the group id as well as the userid should exist in /etc/passwd

I have changed the field "passwd" in table "ftpuser" to varchar(40) (before varchar(32)) to be able to insert sha1 encrypted passwords.

Tested on 
- Debian 11
- whmcs 8.3.2
- proftpd Version 1.3.7a on Debian 11
