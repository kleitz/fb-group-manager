<?php
error_reporting(~0);
ini_set('display_errors', 1);
define('FACEBOOK_SDK_V4_SRC_DIR', '../Facebook/');
require_once("../autoload.php");
 
// path of these files have changes
use Facebook\HttpClients\FacebookHttpable;
use Facebook\HttpClients\FacebookCurl;
use Facebook\HttpClients\FacebookCurlHttpClient;
 
use Facebook\Entities\AccessToken;
use Facebook\Entities\SignedRequest;
 
// other files remain the same
use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\FacebookRequestException;
use Facebook\FacebookOtherException;
use Facebook\FacebookAuthorizationException;
use Facebook\GraphObject;
use Facebook\GraphSessionInfo;
use Facebook\GraphUser;
session_start();
$bsHome = get_cfg_var('facebook.bshome');
$bsId = get_cfg_var('facebook.bsid');
$inBS = false;
$fbuser = null;
FacebookSession::setDefaultApplication(get_cfg_var('facebook.appid'), get_cfg_var('facebook.secret'));
$helper = new FacebookRedirectLoginHelper($bsHome);
// see if a existing session exists
if (isset($_SESSION) && isset($_SESSION['bs_fb_token'])) {
	$session = new FacebookSession($_SESSION['bs_fb_token']);
	try {
		if (!$session->validate()) {
			$session = null;
		}
	}
	catch (Exception $e) {
		$session = null;
	}
} else {
	try {
		$session = $helper->getSessionFromRedirect();
	}
	catch (FacebookRequestException $ex) {
	}
	catch (Exception $ex) {
		echo $ex->message;
	}
}
if ( !isset( $session ) || $session === null ) {
	// no session exists
	try {
		$session = $helper->getSessionFromRedirect();
	}
	catch( FacebookRequestException $ex ) {
		// When Facebook returns an error
		// handle this better in production code
		print_r( $ex );
	}
	catch( Exception $ex ) {
		// When validation fails or other local issues
		// handle this better in production code
		print_r( $ex );
	}
}
// see if we have a session
if ( isset( $session ) ) {
	// save the session
	$_SESSION['bs_fb_token'] = $session->getToken();
	// create a session using saved token or the new one we generated at login
	$session = new FacebookSession( $session->getToken() );
	
	// make the API call for user
	$request = new FacebookRequest( $session, 'GET', '/me' );
	$response = $request->execute();
	// get response
	$fbuser = $response->getGraphObject(GraphUser::className());
	
	// set vars for later interaction with db
	$fbuser_name = $fbuser->getName();
	$fbuser_id = $fbuser->getId();
	
	// make the API call for user's groups
	$request = new FacebookRequest( $session, 'GET', '/me/groups' );
	$response = $request->execute();
	
	// handle the result
	$groups = $response->getGraphObjectList();
	foreach ($groups as $group) {
		if ($group->getProperty('id') == $bsId) {
			$inBS = true;
		}
	}
	
	// set logout url
	// this url logs out out of FB entirely, as well as the #Boatsnap session.
	// if you don't do it this way, when you click "sign in" again on the #Boatsnap page, it will resume your previous session.
	$logoutUrl = $helper->getLogoutUrl( $session, $bsHome . 'logout-fb.php' );
	// this url will just destroy the current #Boatsnap session.
	//$logoutUrl = $bsHome . 'logout-fb.php'
} 
else {
	$loginUrl = $helper->getLoginUrl( array( 'email', 'user_friends', 'user_groups' ));
}
$db = new mysqli('localhost','boatsnap','boatsnap1','boatsnap');
if($db->connect_errno > 0) {
	die('Could not connect to MySQL. Please contact the Holy Ship IT Department.');
}
if(isset($fbuser_id) && $stmt = $db->prepare("SELECT user_id, snapchat_username, hidden FROM user WHERE facebook_id = ? ORDER BY date_updated DESC")) {
	$stmt->bind_param("s", $fbuser_id);
	$stmt->execute();
	$stmt->store_result();
	if($stmt->num_rows == 0) {
		if($stmt2 = $db->prepare("INSERT INTO user (date_created, date_updated, realname, facebook_id) VALUES (now(), now(), ?, ?)")) {
			$stmt2->bind_param("ss", $fbuser_name, $fbuser_id);
			$stmt2->execute();
			$stmt2->close();
		}
	}
	else {
		$stmt->bind_result($user_id, $stored_username, $hidden);
		$stmt->fetch();
	}
	$stmt->close();
}
if(isset($user_id)) {
	$stmt = $db->prepare("UPDATE user SET date_updated=now(), realname=? WHERE user_id=?");
	$stmt->bind_param("si", $fbuser_name, $fbuser_id);
	$stmt->execute();
	$stmt->close();
}
?>

<!doctype html>
<html xmlns:fb="http://www.facebook.com/2008/fbml">
	<head>
		<title>Shipfam.com #Boatsnap</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
    	<link href="css/bootstrap.min.css" rel="stylesheet">
		<link href="css/stickyfooter.css" rel="stylesheet">
		<link href="css/bootstrap-switch.css" rel="stylesheet">
		<link href="css/bootstrap-tour.min.css" rel="stylesheet">
		<style>
#users td {
	padding-right: 10px;
}
		</style>
	</head>

<body>
	<div class="container">
		<div class="page-header">
			<h2>Shipfam.com #Boatsnap</h2>
			<p>
			<?php if(!$inBS): ?>
				<br>
			<?php endif ?>
			You are 
			<?php if($fbuser): ?>
				<?php if($inBS): ?>
					<i><?=$fbuser_name?></i> on the Facebook <a class="btn btn-default btn-xs" href="<?=$logoutUrl?>">Logout</a>
				<?php else: ?>
					<b>NOT</b> authorized to use this application <a class="btn btn-default btn-xs" href="<?=$logoutUrl?>">Logout</a>
				<?php endif ?>
			<?php else: ?>
				<i>not connected to</i> the Facebook
			<?php endif ?>
			<?php if($inBS): ?>
				and not logged into Snapchat.
			<?php endif ?>
			</p>
		</div>
			<?php if($inBS): ?>
				<div class="alert alert-warning">You are not logged into Snapchat. List is read-only. <a href="index.php">Click Here</a> to login and enable one-click adding.</div>
			<?php endif ?>
	</div>

	<div class="container">

		<?php if (!$fbuser): ?>
			<p><a href="<?=$loginUrl?>"><img src="img/connect-fb.png" /></a></p>
		<?php else: ?>
		<?php if($inBS): ?>
<div class="table-responsive">
	<table class="table-hover" id="users">
		<tbody>
<?php
################ BUILD TABLE ###################################
$sql = <<<SQL
	SELECT *
	  FROM user
	  WHERE snapchat_username IS NOT NULL
	    AND hidden = 0
	  ORDER BY date_updated DESC
SQL;
if(!$result = $db->query($sql)) {
	die('Unable to query database. Please contact Holy Ship IT Department.');
}
while($row = $result->fetch_assoc()) {
	?>
			<tr>
				<td><a href="//facebook.com/<?=$row['facebook_id']?>" target="_blank"><img src="//graph.facebook.com/<?=$row['facebook_id']?>/picture" /></a></td>
				<td><?=$row['realname']?></td>
				<td><?=$row['snapchat_username']?></td>
			</tr>
	<?php
}
?>
		</tbody>
	</table>
</div>
			<?php endif ?>
			<?php endif ?>
	<?php if($fbuser && !$inBS): ?>
	<i>You can't snap with us</i>
	<?php endif ?>
	</div>

    <div id="footer">
    	<div class="container">
    		<p class="text-muted">Created by <a href="https://philihp.com">Philihp Busby</a> and <a href="https://www.facebook.com/portugalc/">Chris Portugal</a> to make your life easier.</p>
    	</div>
    </div>
	<script src="js/jquery.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script src="js/bootstrap-switch.js"></script>
	<script src="js/bootstrap-tour.min.js"></script>
 <script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
  ga('create', 'UA-47237641-1', 'shipfam.com');
  ga('send', 'pageview');
</script>	
  </body>
</html>
<?php
$db->close();
?>