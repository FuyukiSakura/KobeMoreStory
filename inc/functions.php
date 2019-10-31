<?php
####################### Database Related Functions #######################
function db_connect() {

    // Define connection as a static variable, to avoid connecting more than once
    static $connection;

    // Try and connect to the database, if a connection has not been established yet
    if(!isset($connection)) {
         // Load configuration as an array. Use the actual location of your configuration file
        $config = parse_ini_file(RELATIVE_PATH. '../configs/kobemorestory/db_config.ini');
        $connection = mysqli_connect('localhost',$config['username'],$config['password'],$config['dbname']);
    }

    // If connection was not successful, handle the error
    if($connection === false) {
        // Handle error - notify administrator, log to a file, show an error screen, etc.
        return mysqli_connect_error();
    }
    return $connection;
}

function getKobes($page = 0, $status = 0, $rows = 10,$isAdmin = false){
  //Calculate Staring Row
  $start = ($page-1) * 10;
  
  //Connect to DB
  $mysqli = db_connect();
  $stmt = $mysqli->stmt_init();
  
  //Prepare SQL Statement
  if(!$stmt->prepare("SELECT id, status, 
						IF(LENGTH(message)>15, CONCAT(LEFT(message, 15),'...'), 
						message), time, INET_NTOA(ip), identifier 
					FROM kobes WHERE status = ? ORDER BY id LIMIT ?, ?")){
    return Response::internalError("Prepared Fail");
  }

  //Bind Param
  if(!$stmt->bind_param("iii", $status, $start, $rows)){
    return Response::internalError("Bind Param Fail");
  };

  //Execute Statement
  if(!$stmt->execute()){
    return Response::internalError("Execute Fail");
  }

  //Bind Results
  if(!$stmt->bind_result($id, $type, $message, $time, $ip, $identifier)){
    return Response::internalError("Bind Results Fail");
  }

  //Fetch Results to Array
  $kobes = array();
  while($stmt->fetch()){
    $kobe = new Kobe($id, ($type == 1?"純文字":"圖片"), $message, $time, $ip, $identifier);
    array_push($kobes, $kobe);
  }

  //Close Statement
  $stmt->close();
  return $kobes;
}

function getCurrentPost(){
  //Connect to DB
  $mysqli = db_connect();
  $stmt = $mysqli->stmt_init();

  //Prepare SQL Statement
  if(!$stmt->prepare("SELECT MAX(kobe_id) FROM kobes")){
    return Response::internalError("Prepared Fail");
  }

  //Execute Statement
  if(!$stmt->execute()){
    return Response::internalError("Execute Fail");
  }

  //Bind Results
  if(!$stmt->bind_result($current_id)){
    return Response::internalError("Bind Results Fail");
  }

  $stmt->fetch();
  return $current_id;
}

function updateKobeStatus($id, $status, $access_token, $fb_id = null, $kobe_id = null){
  $mysqli = db_connect();
  $stmt = $mysqli->stmt_init();

  if(!validateAdmin($access_token)){
    return Response::fail("Not Admin");
  }

  if(!$stmt->prepare("UPDATE kobes SET status = ?, facebook_post_id = ?, kobe_id = ? WHERE id = ?")){
    return Response::fail("Prepare Failed");
  }

  if(!$stmt->bind_param("isii", $status, $fb_id, $kobe_id, $id)){
    return Response::fail("Bind Failed");
  }

  if(!$stmt->execute()){
    return Response::fail("Execute Failed");
  }

  if(!$stmt->affected_rows == 1){
    return Response::fail("Error. Affected Rows: ".$stmt->affected_rows);
  }

  return true;
}

####################### Facebook Functions #######################
function newFacebook(){
  //Static Facebook
  static $fb;

  if(!isset($fb)){
    $config = parse_ini_file(RELATIVE_PATH. '../configs/kobemorestory/fb_config.ini');

    $fb = new Facebook\Facebook([
  	 'app_id' => $config['app_id'], // Replace {app-id} with your app id
  	 'app_secret' => $config['app_secret'],
     'fileUpload' => true,
  	 'default_graph_version' => $config['default_graph_version'],
  	 ]);
  }
  return $fb;
}

function validateAdmin($access_token){
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
    return false;
  }

  if(!$stmt->bind_param("i", $fb_id)){
    return false;
  };

  //Execute Statement
  if(!$stmt->execute()){
    return false;
  }

  /* store result */
  $stmt->store_result();

  if(!$stmt->num_rows == 1){
    return false;
  }
  return true;
}

####################### Generate Image #######################
function generateImage($text, $colorScheme, $type, $newYear = false){


  define('MAX_WIDTH', 680);
  $font = '../font/MSMHei-Bold.ttf';
  $font_size = 30;
  $angle = 0;
  
  if($newYear == 'true'){
	  define('MIN_WIDTH', 200);
	  define('MIN_HEIGHT', 600);
	  $font = '../font/hkgyokk.ttf';
	  $font_size = 72;
  }else{
	  define('MIN_WIDTH', 480);
	  define('MIN_HEIGHT', 290);
  }
  
  $string = "";
  $tmp_string = "";
  

  //split the string
  //build new string word for word
  //check everytime you add a word if string still fits
  //otherwise, remove last word, post current string and start fresh on a new line
  $words = mbStringToArray($text);

  for($i = 0; $i < count($words); $i++) {
	  $tmp_string .= $words[$i];
	  //Check for new line
	  if($words[$i] == "\n"){
		$string .= $tmp_string;
		$tmp_string = "";
		$i++;
		$tmp_string .= $words[$i];
	  }

	  //check size of string
	  $dim = imagettfbbox($font_size, $angle, $font, $tmp_string);
	  $text_width = $dim[2]-$dim[0];

	  if(!($text_width+30 < MAX_WIDTH)) {
		$string .= mb_substr($tmp_string,0,-1)."\n";
		$i--;
		$tmp_string = "";
	  }
  }
  $string .= $tmp_string; //Add last bit

  // Get Bounding Box Size
  $text_box = imagettfbbox($font_size,$angle,$font,$string);

  // Get your Text Width and Height
  $text_width = $text_box[2]-$text_box[0];
  $text_height = $text_box[1]-$text_box[7];
  //Initialize - Default Black
  //$bg = imagecolorallocate($im, 0, 0, 0);
  //$textcolor = imagecolorallocate($im, 230, 230, 230);
  
  $height = MIN_HEIGHT;
  $width = MIN_WIDTH;

  if($text_height+80 > MIN_HEIGHT){
    $height = $text_height+80;
  }

  if($text_width+30 > MIN_WIDTH){
    $width = $text_width+30;
  }

  $im = imagecreate($width, $height);

  //Change Color Scheme On Select
  switch($colorScheme){
    case "yellow":
      $bg = imagecolorallocate($im, 255, 201, 14);
      $textcolor = imagecolorallocate($im, 81, 77, 77);
      break;
    case "blue":
      $bg = imagecolorallocate($im, 0, 162, 232);
      $textcolor = imagecolorallocate($im, 80, 77, 78);
      break;
    case "green":
      $bg = imagecolorallocate($im, 181, 230, 29);
      $textcolor = imagecolorallocate($im, 65, 62, 61);
      break;
    case "orange":
      $bg = imagecolorallocate($im, 255, 127, 39);
      $textcolor = imagecolorallocate($im, 81, 77, 77);
      break;
    case "pink":
      $bg = imagecolorallocate($im, 255, 174, 201);
      $textcolor = imagecolorallocate($im, 255, 255, 255);
      break;
    case "black":
      $bg = imagecolorallocate($im, 0, 0, 0);
      $textcolor = imagecolorallocate($im, 230, 230, 230);
      break;
	case "warn":
      $bg = imagecolorallocate($im, 0, 0, 0);
      $textcolor = imagecolorallocate($im, 255, 0, 16);
      break;
  }

  // Get image Width and Height
  $image_width = imagesx($im);
  $image_height = imagesy($im);

  // Calculate coordinates of the text
  $x = ($image_width/2) - ($text_width/2);
  $y = ($image_height/2) - ($text_height/2);

  // Write the string at the top left

  imagettftext($im, 18, 0, $image_width-150, $image_height-25, $textcolor, '../font/MSMHei-Bold.ttf', '靠北模物語');
  imagettftext($im, $font_size, 0, $x, $y+15, $textcolor, $font, $string);

  return $im;
}

function write_multiline_text($image, $font_size, $color, $font, $text, $start_x, $start_y, $max_width) {

}

function mbStringToArray ($string) {
    $strlen = mb_strlen($string);
    while ($strlen) {
        $array[] = mb_substr($string,0,1,"UTF-8");
        $string = mb_substr($string,1,$strlen,"UTF-8");
        $strlen = mb_strlen($string);
    }
    return $array;
}

####################### Utilities #######################
function generateRandomID() {
		$charset = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
		$base = strlen($charset);
		$result = '';

		$now = explode(' ', microtime())[1];
		while ($now >= $base){
			$i = $now % $base;
			$result = $charset[$i] . $result;
			$now /= $base;
		}
  		return substr($result, -5);
	}
?>
