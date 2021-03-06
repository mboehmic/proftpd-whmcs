<?php

/**
* Description: 	whmcs modul to change proftpd's mysql database
* Company:		Biteno GmbH
* Web:          https://www.biteno.com 
* Author:		Matthias Boehmichen
* Date:			30.12.2021
*
* Based on:		https://github.com/eksoverzero/whmcs-freeradius/tree/refactor
*
* Settings:		Mandatory fields:
*				username and password (mysql username and password)
*				ip-adress field is required
*			    access hash -> name of the mysql database containing ftp users
*				not used: Hostname 
* 
* Changelog:	29.12.2021 initial Version with service creation, termination, package change and password reset
*				30.12.2021 removed old custom config fields ; added "proftpd Userid" as second field 
*				groupid and userid must exist both in mysql and /etc/passwd resp. /etc/group on the target server
*				tested on ProFTPD Version 1.3.7a / Debian 11 Bullseye with whmcs 8.3.2
* 
**/
 
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
 
function proftpd_MetaData(){
    return array(
        'DisplayName' => 'ProFtpd',
        'APIVersion' => '1.1', // Use API Version 1.1
        'RequiresServer' => true, // Set true if module requires a server to work
        'DefaultNonSSLPort' => '1111', // Default Non-SSL Connection Port
        'DefaultSSLPort' => '1112', // Default SSL Connection Port
        'ServiceSingleSignOnLabel' => 'Login to Panel as User',
        'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
}
 
function proftpd_ConfigOptions(){
    return array(
        'proftpd Group' => array(
            'Type' => 'text',
            'Size' => '5',
            'Default' => '',
            'Description' => 'ProFtpd group id - must not be empty',
        ),
        'proftpd Userid' => array(
            'Type' => 'text',
            'Size' => '5',
            'Default' => '0',
            'Description' => 'ProFtpd userid - must not be empty',
        )
        
    );
}
 
function proftpd_CreateAccount(array $params){
    try {
        $proftpd_email = $params['clientsdetails']['email'];
		
		$proftpd_group = $params['configoption1'];
        $proftpd_userid = $params['configoption2'];        
        $proftpd_username = $params['username'];
        $proftpd_password = $params['password'];
		
		$sha1_user_password = "{sha1}".base64_encode(pack("H*", sha1($proftpd_password)));

        $proftpd_sqlhost = $params['serverip'];
        $proftpd_sqldbname = $params['serveraccesshash'];
        $proftpd_sqlusername = $params['serverusername'];
        $proftpd_sqlpassword = $params['serverpassword'];

        if (!$proftpd_username) {
            $proftpd_username = proftpd_username($proftpd_email);

            update_query(
                'tblhosting',
                array(
                    'username' => $proftpd_username
                ),
                array(
                    'id' => $params['serviceid']
                )
            );
        }

        $proftpdsql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($proftpd_sqlhost,  $proftpd_sqlusername,  $proftpd_sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $proftpd_sqldbname);

        $query = "SELECT COUNT(*) FROM ftpuser WHERE userid='$proftpd_username'";
        $result = mysqli_query($proftpdsql, $query);

        if (!$result) {
            $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
            proftpd_WHMCSReconnect();
            return 'ProFtpd Database Query Error: ' . $proftpderror;
        }

        $data = mysqli_fetch_array($result);

        if ($data[0]) {
            proftpd_WHMCSReconnect();
            return 'UserID Already Exists';
        }

        $query = "INSERT INTO ftpuser (userid, passwd, uid, gid, homedir, shell) VALUES ('$proftpd_username', '$sha1_user_password', '$proftpd_userid', '$proftpd_group', '/home/ftproot', '/sbin/nologin')";
        $result = mysqli_query($proftpdsql, $query);

        if (!$result) {
            $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
            proftpd_WHMCSReconnect();
            return 'ProFtpd Database Query Error: ' . $proftpderror;
        }
             

        proftpd_WHMCSReconnect();
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'ProFtpd',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }

    return 'success';
}


function proftpd_ChangePassword(array $params){
    try {
        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];
 
        $proftpd_username = $params['username'];
        $proftpd_password = $params['password'];
		
		$sha1_user_password = "{sha1}".base64_encode(pack("H*", sha1($proftpd_password)));

        $proftpdsql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "SELECT COUNT(*) FROM ftpuser WHERE userid='$proftpd_username'";
        $result = mysqli_query($proftpdsql, $query);

        if (!$result) {
            $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
            proftpd_WHMCSReconnect();
            return 'ProFtpd Database Query Error: ' . $proftpderror;
        }

        $data = mysqli_fetch_array($result);
        $count = $data[0];

        if (!$count) {
            proftpd_WHMCSReconnect();
            return 'User Not Found';
        }

        $query = "UPDATE ftpuser SET passwd='$sha1_user_password' WHERE userid='$proftpd_username' ";
        $result = mysqli_query($proftpdsql, $query);

        if (!$result) {
            $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
            proftpd_WHMCSReconnect();
            return 'ProFtpd Database Query Error: ' . $proftpderror;
        }

        proftpd_WHMCSReconnect();
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'ProFtpd',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}

function proftpd_ChangePackage(array $params)
{
    try {
		$proftpd_group = $params['configoption1'];
		$proftpd_userid = $params['configoption2'];
		
        
        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];
	
		 
		$username = $params['username'];
        $password = $params['password'];

        $proftpdsql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "SELECT COUNT(*) FROM ftpuser WHERE userid='$username'";
        $result = mysqli_query($proftpdsql, $query);

        if (!$result) {
            $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
            proftpd_WHMCSReconnect();
            return 'ProFtpd Database Query Error: ' . $proftpderror;
        }

        $data = mysqli_fetch_array($result);
        $count = $data[0];

        if (!$count) {
            proftpd_WHMCSReconnect();
            return 'User Not Found';
        }

        $query = "UPDATE ftpuser SET gid='$proftpd_group', uid='$proftpd_userid' WHERE userid='$username'";
        $result = mysqli_query($proftpdsql, $query);

        if (!$result) {
            $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
            proftpd_WHMCSReconnect();
            return 'ProFtpd Database Query Error: ' . $proftpderror;
        }


        proftpd_WHMCSReconnect();
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'ProFtpd',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }

    return 'success';
}


function proftpd_TerminateAccount(array $params){
    try {
        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];

       $proftpd_username = $params['username'];
        $proftpd_password = $params['password'];
		

        $proftpdsql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

 
  
        $query = "DELETE FROM ftpuser WHERE userid='$proftpd_username'";
        $result = mysqli_query($proftpdsql, $query);

        if (!$result) {
            $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
            proftpd_WHMCSReconnect();
            return 'ProFtpd Database Query Error: ' . $proftpderror;
        }

        proftpd_WHMCSReconnect();
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'ProFtpd',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}

function proftpd_WHMCSReconnect() {
    require( ROOTDIR . "/configuration.php" );

    $whmcsmysql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($db_host,  $db_username,  $db_password));
    mysqli_select_db($GLOBALS["___mysqli_ston"], $db_name);
}


function proftpd_username($proftpd_email){
  global $CONFIG;
  $proftpd_emaillen = strlen($proftpd_email);
  $result = select_query(
    "tblhosting",
    "COUNT(*)",
    array(
      "username" => $proftpd_email
    )
  );
  $data = mysql_fetch_array($result);
  $proftpd_username_exists = $data[0];
  $suffix = 0;
  while( $proftpd_username_exists > 0 ){
    $suffix++;
    $proftpd_email = substr( $proftpd_email, 0, $proftpd_emaillen ) . $suffix;
    $result = select_query(
      "tblhosting",
      "COUNT(*)",
      array(
        "username" => $proftpd_email
      )
    );
    $data = mysql_fetch_array($result);
    $proftpd_username_exists = $data[0];
  }
  return $proftpd_email;
}

