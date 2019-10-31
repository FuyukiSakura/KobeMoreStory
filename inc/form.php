<?php
	/* Turn off error reporting for productive website
	This allows custom error message to be read and shown on the site by AJAX */
	error_reporting(0);

	define('RELATIVE_PATH', '../');
	require('__autoload.php');

	/* ------------- Form Validation ------------- */
	if(!isset($_POST["kobe-msg"])){
		header('HTTP/1.0 403 Forbidden');
		echo "Fobidden";
		exit;
	}

	if(!isset($_POST["kobe-agreement"])){ //Check if agreement checked
		echo Response::fail("你必須同意 使用條款");
		exit;
	}

	//Grab Input
	if($_POST["kobe-msg"] == ""){ //Check Empty Input
		echo Response::fail("你必需輸入 靠北內容");
		exit;
	}

	if($_POST["kobe-mode"] == ""){ //Check Empty Input
		echo Response::fail("你必需選擇一個發怖模式");
		exit;
	}

	$color_scheme = array("yellow", "blue", "green", "orange", "pink", "black", "warn");
	if($_POST["kobe-color"] == "" || !in_array($_POST["kobe-color"], $color_scheme)){ //Check Empty Input
		echo Response::fail("內部錯誤");
		exit;
	}

	$message = $_POST["kobe-msg"];
	$mode = ($_POST["kobe-mode"] == "text"?1:2);
	$color = $_POST["kobe-color"];
	$specialMode = ($_POST['special-image-mode'] == "on" ? 1:0) ?? 0;

	if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
  	$_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
	}
	$ip = $_SERVER["REMOTE_ADDR"]; //User IP Address
	$identifier = UUID::v4();

	//Connect to DB
	$mysqli = db_connect();
	if($mysqli == mysqli_connect_error()){
		//$response = Response::internalError();
		echo Response::internalError("DB Connect");
		exit;
	}

	/* Create MySQLi prepared statement */
	$stmt = $mysqli->stmt_init();
	if(!$stmt->prepare("INSERT INTO kobes (type, message, color_scheme, ip, identifier, special_mode) VALUES (?, ?, ?, INET_ATON(?), ?, ?)")){
		echo Response::internalError("Prepare Failed");
		exit;
	};

	if(!$stmt->bind_param("issssi", $mode, $message, $color, $ip, $identifier, $specialMode)){
		echo Response::internalError("Bind Failed");
		exit;
	};

	if(!$stmt->execute()){
		echo Response::internalError("Execute Failed");
		exit;
	};

	//Microsoft Cognitive Service
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_URL, 'https://westus.api.cognitive.microsoft.com/contentmoderator/moderate/v1.0/ProcessText/Screen/?language=zh-tw&autocorrect=false&urls=true&PII=false');
	curl_setopt($curl, CURLOPT_TIMEOUT, "30");
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: text/plain', 'Ocp-Apim-Subscription-Key: 5a22485c00534b63a5612e05101f2e98'));
	curl_setopt($curl, CURLOPT_POST, 1);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $message);
	$out = curl_exec($curl);
	if ($error = curl_error($curl)) {
		echo Response::fail('cURL error:'.$error);
		exit;
	}
	curl_close ($curl);
	
	file_put_contents("../microsoft/congnitive_results.txt", $out. "\n", FILE_APPEND);
	//Get Output
	$pms = json_decode($out,true);
	
	//Detect Mature Content and Malware
	$virusFlag = false;
	$adultFlag = false;
	$racyFlag = false;
	foreach($pms['Urls'] as $url){
		if($url['Categories']['Adult'] >= 1.0){
			$adultFlag = true;
		}
		
		if($url['Categories']['Malware'] >= 1.0){
			$virusFlag = true;
		}
	}
	
	if($virusFlag){
		echo Response::fail("你的輸入含有病毒，發怖失敗");
		exit;
	}
	
	preg_match_all('/!\[image\]\((https?:\/\/[a-zA-Z.\d\/]+)\)/', $message, $matches);
	
	foreach($matches[1] as $imgurUrl){
		$imageData['DataRepresentation'] = "URL";
		$imageData['Value'] = $imgurUrl;
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_URL, 'https://westus.api.cognitive.microsoft.com/contentmoderator/moderate/v1.0/ProcessImage/Evaluate');
		curl_setopt($curl, CURLOPT_TIMEOUT, "30");
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Ocp-Apim-Subscription-Key: 5a22485c00534b63a5612e05101f2e98'));
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($imageData));
		$out = curl_exec($curl);
		if ($error = curl_error($curl)) {
			echo Response::fail('cURL error:'.$error);
			exit;
		}
		
		file_put_contents("../microsoft/congnitive_photo_results.txt", $out. "\n", FILE_APPEND);
		curl_close ($curl);
		
		$pms = json_decode($out, true);
		if($pms['IsImageAdultClassified']){
			$adultFlag = true;
		}
		
		if($pms['IsImageRacyClassified']){
			$racyFlag = true;
		}
		//Avoid API Limitation
		sleep(1);
	}
	
	file_put_contents("../microsoft/congnitive_photo_results.txt", "\n", FILE_APPEND);
	
	if($adultFlag){
		echo Response::success("你的輸入被系統認定含有色情訊息，將改由管理員審核", ["congnitive_results"=>$out]);
		exit;
	}
	
	if(sizeof($pms['Terms']) != 0 || $racyFlag){
		echo Response::success("你的輸入被系統認定含有仇恨言論，將改由管理員審核");
		exit;
	}
	
	echo Response::success("自動發怖通過");
	exit;
?>
