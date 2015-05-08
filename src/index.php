<?php
define('FACEBOOK_SDK_V4_SRC_DIR', '../Facebook/');
require_once("../autoload.php");



use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\FacebookRequestException;
use Facebook\FacebookAuthorizationException;
use Facebook\GraphObject;
use Facebook\FacebookPermissions;
use Facebook\FacebookPermissionException;
 
// init app with app id (APPID) and secret (SECRET)
FacebookSession::setDefaultApplication('671542646214914','3df0bd4144102eda1623babc3dcf23cc');

session_start();

// login helper with redirect_uri
$helper = new FacebookRedirectLoginHelper( 'http://localhost/fb-group-manager/src/');

try {
  $session = $helper->getSessionFromRedirect();
} catch( FacebookRequestException $ex ) {
  // When Facebook returns an error
} catch( Exception $ex ) {
  // When validation fails or other local issues
}
 
// see if we have a session
if ( isset( $session ) ) {
  // graph api request for user data
  $request = new FacebookRequest( $session, 'GET', '/me' );
  $response = $request->execute();
  // get response
  $graphObject = $response->getGraphObject();
  $userInfo = $graphObject->asArray();
   
  echo print_r($graphObject,1);
  
  echo 'Hello, ';
  echo  print_r( $userInfo['name'], 1 );
  echo '<a href="'. $helper->getLogoutUrl($session,'http://localhost/fb-group-manager/src/'). '"> Logout</a><br />';


	$request = new FacebookRequest(
	  $session,
	  'GET',
	  '/226943227358920/members'
	);
	
	$response = $request->execute();
	$graphObject = $response->getGraphObject();
	echo print_r($graphObject,1);

} else {
  // show login url
  echo '<a href="'. $helper->getLoginUrl() . '">Login</a>';
}

?>