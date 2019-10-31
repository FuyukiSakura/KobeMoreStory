<?php
  /* Turn off error reporting for productive website
  This allows custom error message to be read and shown on the site by AJAX */
  error_reporting(0);

  define('RELATIVE_PATH', '../');
  require(RELATIVE_PATH. "inc/__autoload.php");

  if(!isset($_POST['action'])){
    header('HTTP/1.0 403 Forbidden');
		echo "Fobidden";
		exit;
  }

  session_start();
  $action = $_POST['action'];
  $access_token = $_SESSION['fb_access_token']; //Facebook Token
  $is_admin = validateAdmin($access_token); //Check if Facebook Admin

  if(!$is_admin){
    header('HTTP/1.0 403 Forbidden');
		echo "Fobidden";
		exit;
  }

  //Get Kobes
  if($action == "getKobes"){
	$page = $_POST['page'] ?? 1;
	$status = $_POST['kobeStatus'] ?? 0;
	
	$kobes = getKobes($page, $status);
    if($kobes instanceof Response){
      echo $kobes;
      exit;
    }

	/* ---------- Count Total ---------- */
    $mysqli = db_connect();
    $stmt = $mysqli->stmt_init();
    //Prepare SQL Statement
    if(!$stmt->prepare("SELECT count(id) FROM kobes WHERE status = ?")){
      echo Response::internalError("Prepared Fail");
      exit;
    }
	
	if(!$stmt->bind_param("i", $status)){
      echo Response::internalError("Bind Param Fail");
      exit;
    }

    //Execute Statement
    if(!$stmt->execute()){
      echo Response::internalError("Execute Fail");
      exit;
    }

    //Bind Results
    if(!$stmt->bind_result($count)){
      echo Response::internalError("Bind Results Fail");
      exit;
    }

    $stmt->fetch();

    echo Response::success("Get kobes successfully", array('kobes'=>$kobes, 'countKobes'=>$count));
    exit;

  //Archieve Post
  }elseif($action == "archieve"){
    if(!isset($_POST['kid'])){
      echo Response::fail("Bad Request");
      exit;
    }

    $id = $_POST['kid'];
    $result = updateKobeStatus($id, 2, $access_token);
    if($result instanceof Response){
      echo $result;
      exit;
    }
    echo Response::success("Comment Archieved");
    exit;

  //Approve Post
  }elseif($action == "post"){
    if(!isset($_POST['kid'])){
      echo Response::fail("Bad Request");
      exit;
    }

    $id = $_POST['kid'];
    $mysqli = db_connect();
    $stmt = $mysqli->stmt_init();

    $kobe_id = getCurrentPost() + 1;

    //Prepare SQL Statement
    if(!$stmt->prepare("SELECT message, color_scheme, type, special_mode FROM kobes WHERE id = ? AND status = 0 LIMIT 1")){
      echo Response::internalError("Prepared Fail");
      exit;
    }

    if(!$stmt->bind_param('i', $id)){
      echo Response::internalError("Bind Param Fail");
      exit;
    }

    //Execute Statement
    if(!$stmt->execute()){
      echo Response::internalError("Execute Fail");
      exit;
    }

    //Bind Results
    if(!$stmt->bind_result($kobe_msg, $color, $type, $specialMode)){
      echo Response::internalError("Bind Results Fail");
      exit;
    }
	
	//Check if post archieved or posted
	$stmt->store_result();
	if($stmt->num_rows == 0){
	  echo Response::internalError("Cannot Post archieved post or repeatly");
	  $stmt->close();
      exit;
	}
	
    $stmt->fetch();
    $stmt->close();

    /* --------------------- Post to Facebook --------------------- */
    $fb_config = parse_ini_file(RELATIVE_PATH. '../configs/kobemorestory/fb_config.ini');
    $fb = newFacebook();

    //Default Post to Feed
    $request_page = '/'.$fb_config['page_id'].'/feed';

	$graphNode = [];
    if($type == 1){
		
		//Load All Imgur Uploads
		preg_match_all('/!\[image\]\((https?:\/\/[a-zA-Z.\d\/]+)\)/', $kobe_msg, $matches);
	  
		foreach($matches[0] as $imgurInput){
			$kobe_msg = str_replace($imgurInput, "", $kobe_msg);
		}
		
		$graphImages = [];
		foreach($matches[1] as $imgurUrl){
			try{
				$response = $fb->post('/me/photos', 
					[
					'url'=>$imgurUrl,
					'published'=>'false',
					'caption'=>'#靠北模物語'.$kobe_id
					],
					$fb_config['page_access_token']);
				$graphNode = $response->getGraphNode();
				$graphImages[] = $graphNode['id'];
			} catch(Facebook\Exceptions\FacebookResponseException $e) {
				echo Response::fail('Graph returned an error: ' . $e->getMessage());
				exit;
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
				echo Response::fail('Facebook SDK returned an error: ' . $e->getMessage());
				exit;
			}
			
			$graphNode = $response->getGraphNode();
			$graphImages[] = $graphNode['id'];
		}
		
		for($i=0;$i<sizeof($graphImages);$i++){
			$postVar["attached_media[".$i."]"] = '{"media_fbid":"'. $graphImages[$i] .'"}';
		}
		
		//Set Message
		$postVar["message"] = "#靠北模物語".$kobe_id."\n".$kobe_msg;
		
		//Check if URL Exist in Message
		$reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
		if(preg_match($reg_exUrl, $kobe_msg, $url)){
			$postVar["link"] = $url[0];
		}
		
		//Post to Facebook
		try{
			$response = $fb->post('/me/feed', $postVar, $fb_config['page_access_token']);
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
		  echo Response::fail('Graph returned an error: ' . $e->getMessage());
		  exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
		  echo Response::fail('Facebook SDK returned an error: ' . $e->getMessage());
		  exit;
		}
		
		//Prepare Graph for Database Insert
		$graphNode = $response->getGraphNode();
    }elseif($type == 2){
		$params = array(
		  "access_token" => $fb_config['page_access_token'], // see: https://developers.facebook.com/docs/facebook-login/access-tokens/
		);
		
		$img = generateImage($kobe_msg, $color, "png", ($specialMode == 1?'true':'false'));

		########### IMGUR ###########
		//Output Image
		ob_start();
		imagepng($img);
		$image_data = ob_get_contents();
		ob_end_clean();
		imagedestroy($img);

		//Imgur Settings
		$client_id="f8a093cb367933a";
		$pvars   = array('image' => base64_encode($image_data));
		$timeout = 30;

		$base64 = 'data:image/' . $type . ';base64,' . base64_encode($image_data);
		//$param['source'] = new CURLFile(base64_encode($image_data), 'image/png');
		//$fb->fileToUpload(base64_encode($image_data));

		//Upload to Imgur
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_URL, 'https://api.imgur.com/3/image.json');
		curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Client-ID ' . $client_id));
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $pvars);
		$out = curl_exec($curl);
		if ($error = curl_error($curl)) {
			echo Response::fail('cURL error:'.$error);
			exit;
		}
		curl_close ($curl);

		//Get Output
		$pms = json_decode($out,true);
		$url=$pms['data']['link'];
		if($url!=""){
			$params["url"] = $url;
		}else{
			echo Response::fail("Imgur throwed a fail: ". $pms['data']['error']);
			exit;
		}

		########### Facebook ###########
		$params["message"] = "#靠北模物語".$kobe_id;
		$request_page = '/'.$fb_config['page_id'].'/photos';

		//Prepare SQL Statement
		$stmt = $mysqli->stmt_init();
		if(!$stmt->prepare("UPDATE kobes SET imgur_url = ? WHERE id = ?")){
			echo Response::internalError("Prepared Fail");
			exit;
		}

		if(!$stmt->bind_param('si', $url, $id)){
			echo Response::internalError("Bind Param Fail");
			exit;
		}

		//Execute Statement
		if(!$stmt->execute()){
			echo Response::internalError("Execute Fail");
			exit;
		}
		
		try {
			//Documentation Method
			$response = $fb->post('/me/photos', $params, $fb_config['page_access_token']);
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			echo Response::fail('Graph returned an error: ' . $e->getMessage());
			exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			echo Response::fail('Facebook SDK returned an error: ' . $e->getMessage());
			exit;
		}
		$graphNode = $response->getGraphNode();
	}
	
    /* handle the result */
    //Update Database
    $id = $_POST['kid'];
    $data_result = updateKobeStatus($id, 1, $access_token, $graphNode['id'], $kobe_id);
    if($data_result instanceof Response){
      echo $data_result;
    }

    echo Response::success("Posted Successfully");
    exit;
  }else{
    header('HTTP/1.0 403 Forbidden');
		echo "Fobidden";
		exit;
  }
?>
