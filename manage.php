<?php
	session_start();
	//session_destroy();
	define('RELATIVE_PATH', './');
	require(RELATIVE_PATH. 'vendor/autoload.php');
	require(RELATIVE_PATH. 'inc/__autoload.php');

	if(!isset($_SESSION['fb_access_token'])){
		$fb = newFacebook();

		$helper = $fb->getRedirectLoginHelper();

		$permissions = ['email', 'publish_pages', 'manage_pages', 'pages_messaging']; // Optional permissions
		$loginUrl = $helper->getLoginUrl('https://kobemorestory.com/inc/fb-callback.php', $permissions);

		echo '<a href="' . htmlspecialchars($loginUrl) . '">管理員登入</a>';
		exit;
	}

	$access_token = $_SESSION['fb_access_token'];
	$fb = newFacebook();

	try {
	  // Returns a `Facebook\FacebookResponse` object
	  $response = $fb->get('/me?fields=id,name', $access_token);
	} catch(Facebook\Exceptions\FacebookResponseException $e) {
	  echo 'Graph returned an error: ' . $e->getMessage();
	  exit;
	} catch(Facebook\Exceptions\FacebookSDKException $e) {
	  echo 'Facebook SDK returned an error: ' . $e->getMessage();
	  exit;
	}

	$user = $response->getGraphUser();
	$fb_id = $user['id'];

	//Validate Admin
	$mysqli = db_connect();
	$stmt = $mysqli->stmt_init();

	//Prepare SQL Statement
	if(!$stmt->prepare("SELECT uid FROM facebook_admin WHERE fb_id = ? LIMIT 1")){
		echo Response::internalError("Prepared Fail");
		exit;
	}

	if(!$stmt->bind_param("i", $fb_id)){
		echo Response::internalError("Bind Failed");
		exit;
	};

	//Execute Statement
	if(!$stmt->execute()){
		echo Response::internalError("Execute Fail");
		exit;
	}

	/* store result */
  $stmt->store_result();

	if(!$stmt->num_rows == 1){
		echo Response::fail("You are not an admin, your ID: ".$fb_id);
		exit;
	}
?>
<!DOCTYPE html>
<html lang="zh-TW">
	<head>
		<title>靠北模物語</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>

		<!-- Bootstrap CDN -->
		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous" />
		<!-- Optional theme -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous" />
		<!-- Latest compiled and minified JavaScript -->
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>

		<!-- Golden Layout
		<script type="text/javascript" src="./library/golden-layout/goldenlayout.min.js"></script>
		<link type="text/css" rel="stylesheet" href="./library/golden-layout/goldenlayout-base.css" />
		<link type="text/css" rel="stylesheet" href="./library/golden-layout/goldenlayout-light-theme.css" /> -->

		<!-- Custom CSS and scripts -->
		<link href="./images/favicon.png" rel="shortcut icon">
		<link rel="stylesheet" href="./css/style.css" />
		<script src="./scripts/manage.js"></script>
	</head>
	<body>
		<div id="notification-area"></div>
		<div class="container-fluid" id="container">
			<div class="row">
				<div class="col-md-12 col-lg-10 col-lg-offset-1">
					<h1>Management System</h1>
					<div class="intro">
						<p><?php echo $user['name']; ?> 你好</p>
					</div>
					<ul class="nav nav-tabs">
					  <li class="active"><a data-status="0" href="#">未處理</a></li>
					  <li><a data-status="1" href="#">已審核</a></li>
					  <li><a data-status="2" href="#">封存</a></li>
					</ul>
					<ul class="pagination">
					  <li><a id="page-one" href="#">1</a></li>
					  <li><a href="#">2</a></li>
					  <li><a href="#">3</a></li>
					  <li><a href="#">4</a></li>
					  <li><a href="#">5</a></li>
					</ul>
					<div id="kobe-data">
						<table class="table table-striped">
						 <thead>
							 <tr>
								 <th>ID</th>
								 <th>Type</th>
								 <th>內容</th>
								 <th>發怖時間</th>
								 <th>IP位置</th>
								 <th>操作</th>
							 </tr>
						 </thead>
						 <tbody>
							 <tr>
								 <td colspan="6">Loading...</td>
							 </tr>
						 </tbody>
					 </table>
					</div>
					<ul class="pagination">
					  <li><a href="#">1</a></li>
					  <li><a href="#">2</a></li>
					  <li><a href="#">3</a></li>
					  <li><a href="#">4</a></li>
					  <li><a href="#">5</a></li>
					</ul>
				</div>
			</div>
		</div>
  </body>
</html>
