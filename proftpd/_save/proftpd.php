<?php
/**
 * WHMCS SDK Sample Provisioning Module
 *
 * Provisioning Modules, also referred to as Product or Server Modules, allow
 * you to create modules that allow for the provisioning and management of
 * products and services in WHMCS.
 *
 * This sample file demonstrates how a provisioning module for WHMCS should be
 * structured and exercises all supported functionality.
 *
 * Provisioning Modules are stored in the /modules/servers/ directory. The
 * module name you choose must be unique, and should be all lowercase,
 * containing only letters & numbers, always starting with a letter.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "provisioningmodule" and therefore all
 * functions begin "provisioningmodule_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _ConfigOptions
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/provisioning-modules/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license https://www.whmcs.com/license/ WHMCS Eula
 */
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
// Require any libraries needed for the module to function.
// require_once __DIR__ . '/path/to/library/loader.php';
//
// Also, perform any initialization required by the service's library.
/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related abilities and
 * settings.
 *
 * @see https://developers.whmcs.com/provisioning-modules/meta-data-params/
 *
 * @return array
 */
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
/**
 * Define product configuration options.
 *
 * The values you return here define the configuration options that are
 * presented to a user when configuring a product for use with the module. These
 * values are then made available in all module function calls with the key name
 * configoptionX - with X being the index number of the field from 1 to 24.
 *
 * You can specify up to 24 parameters, with field types:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each and their possible configuration parameters are provided in
 * this sample function.
 *
 * @see https://developers.whmcs.com/provisioning-modules/config-options/
 *
 * @return array
 */
function proftpd_ConfigOptions(){
    return array(
        'proftpd Group' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'ProFtpd group name',
        ),
        'Usage Limit' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '0',
            'Description' => 'Usage limit in bytes. Use 0 or leave blank to disable',
        ),
        'Rate Limit' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '0',
            'Description' => 'Rate limit. Use 0 or leave blank to disable',
        ),
        'Session Limit' => array(
            'Type' => 'text',
            'Size' => '5',
            'Default' => '0',
            'Description' => 'Session limit as a number. Use 0 or leave blank to disable',
        )
    );
}
/**
 * Provision a new instance of a product/service.
 *
 * Attempt to provision a new instance of a given product/service. This is
 * called any time provisioning is requested inside of WHMCS. Depending upon the
 * configuration, this can be any of:
 * * When a new order is placed
 * * When an invoice for a new order is paid
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function proftpd_CreateAccount(array $params){
    try {
        $email = $params['clientsdetails']['email'];
        $firstname = $params['clientsdetails']['firstname'];
        $lastname = $params['clientsdetails']['lastname'];

        $username = $params['username'];
        $password = $params['password'];

        $groupname = $params['configoption1'];
        $rate_limit = $params['configoption3'];
        $session_limit = $params['configoption4'];

        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];

        if (!$username) {
            $username = proftpd_username($email);

            update_query(
                'tblhosting',
                array(
                    'username' => $username
                ),
                array(
                    'id' => $params['serviceid']
                )
            );
        }

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

        if ($data[0]) {
            proftpd_WHMCSReconnect();
            return 'UserID Already Exists';
        }

        $query = "INSERT INTO ftpuser (userid, passwd, uid, gid, homedir, shell) VALUES ('$username', '$password', '2001', '2001', '/home/ftproot', '/sbin/nologin')";
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
/**
 * Suspend an instance of a product/service.
 *
 * Called when a suspension is requested. This is invoked automatically by WHMCS
 * when a product becomes overdue on payment or can be called manually by admin
 * user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function proftpd_SuspendAccount(array $params){
    try {
        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];

		$username = $params['username'];
        $password = $params['password'];

        $proftpdsql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "SELECT COUNT(*) FROM ftpuser WHERE userid='$username'";
        $result = mysqli_query( $proftpdsql, $query);

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

        $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username' AND attribute='Expiration'";
        $result = mysqli_query($proftpdsql, $query);

        if (!$result) {
            $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
            proftpd_WHMCSReconnect();
            return 'ProFtpd Database Query Error: ' . $proftpderror;
        }

        $data = mysqli_fetch_array($result);
        $count = $data[0];

        if (!$count) {
            $query = "INSERT INTO radcheck (username,attribute,value,op) VALUES ('$username','Expiration','".date("d F Y")."',':=')";
        } else {
            $query = "UPDATE radcheck SET value='".date("d F Y")."' WHERE username='$username' AND attribute='Expiration'";
        }

        $result = mysqli_query( $proftpdsql, $query);

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
/**
 * Un-suspend instance of a product/service.
 *
 * Called when an un-suspension is requested. This is invoked
 * automatically upon payment of an overdue invoice for a product, or
 * can be called manually by admin user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function proftpd_UnsuspendAccount(array $params){
    try {
        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];
		 
		$username = $params['username'];
        $password = $params['password'];

        $proftpdsql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username' AND attribute='Expiration'";
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
            return 'User Not Currently Suspended';
        }

        $query = "DELETE FROM radcheck WHERE username='$username' AND attribute='Expiration'";
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
/**
 * Terminate instance of a product/service.
 *
 * Called when a termination is requested. This can be invoked automatically for
 * overdue products if enabled, or requested manually by an admin user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function proftpd_TerminateAccount(array $params){
    try {
        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];

		$username = $params['username'];
        $password = $params['password'];

        $proftpdsql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

 
  
        $query = "DELETE FROM ftpuser WHERE userid='$username'";
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
/**
 * Change the password for an instance of a product/service.
 *
 * Called when a password change is requested. This can occur either due to a
 * client requesting it via the client area or an admin requesting it from the
 * admin side.
 *
 * This option is only available to client end users when the product is in an
 * active status.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function proftpd_ChangePassword(array $params){
    try {
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

        $query = "UPDATE tpuser SET passwd='$password' WHERE userid='$username' ";
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
/**
 * Upgrade or downgrade an instance of a product/service.
 *
 * Called to apply any change in product assignment or parameters. It
 * is called to provision upgrade or downgrade orders, as well as being
 * able to be invoked manually by an admin user.
 *
 * This same function is called for upgrades and downgrades of both
 * products and configurable options.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function proftpd_ChangePackage(array $params)
{
    try {
        $rate_limit = $params['configoption3'];
        $session_limit = $params['configoption4'];

        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];
	
		 
		$username = $params['username'];
        $password = $params['password'];

        $proftpdsql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "SELECT COUNT(*) FROM radusergroup WHERE username='$username'";
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

        $query = "UPDATE radusergroup SET groupname='$groupname' WHERE username='$username'";
        $result = mysqli_query($proftpdsql, $query);

        if (!$result) {
            $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
            proftpd_WHMCSReconnect();
            return 'ProFtpd Database Query Error: ' . $proftpderror;
        }

        foreach ($params["configoptions"] as $key => $value) {
            if ($key == 'Rate Limit') {
                $rate_limit = $value;
            }

            if ($key == 'Session Limit') {
                $session_limit = $value;
            }
        }

        if ($rate_limit) {
            $query = "UPDATE radreply SET value='$rate_limit' WHERE username='$username' AND attribute='Mikrotik-Rate-Limit'";
            $result = mysqli_query($proftpdsql, $query);

            if (!$result) {
                $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
                proftpd_WHMCSReconnect();
                return 'ProFtpd Database Query Error: ' . $proftpderror;
            }
        }

        if ($session_limit) {
            $query = "UPDATE radcheck SET value='$session_limit' WHERE username='$username' AND attribute='Simultaneous-Use'";
            $result = mysqli_query($proftpdsql, $query);

            if (!$result) {
                $proftpderror = mysqli_error($GLOBALS["___mysqli_ston"]);
                proftpd_WHMCSReconnect();
                return 'ProFtpd Database Query Error: ' . $proftpderror;
            }
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


function proftpd_username($email){
  global $CONFIG;
  $emaillen = strlen($email);
  $result = select_query(
    "tblhosting",
    "COUNT(*)",
    array(
      "username" => $email
    )
  );
  $data = mysql_fetch_array($result);
  $username_exists = $data[0];
  $suffix = 0;
  while( $username_exists > 0 ){
    $suffix++;
    $email = substr( $email, 0, $emaillen ) . $suffix;
    $result = select_query(
      "tblhosting",
      "COUNT(*)",
      array(
        "username" => $email
      )
    );
    $data = mysql_fetch_array($result);
    $username_exists = $data[0];
  }
  return $email;
}

