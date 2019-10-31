<?php
  require('functions.php');
  // Create a 1280*30 image
  $text = ($_POST['text'] == "" ? '你今天要靠北什麼？':urldecode($_POST['text']));
  $color = $_POST['color'];
  $type = 'jpeg';

  $img = generateImage($text, $color, $type, $newYear);

  // Output the image
  //header('Content-type: image/png');
  ob_start();
  imagejpeg($img);
  $image_data = ob_get_contents();
  ob_end_clean();
  imagedestroy($img);
  //echo base64_encode($image_data);

  $base64 = 'data:image/' . $type . ';base64,' . base64_encode($image_data);
  echo $base64;

  /* $base64 = 'data:image/' . $type . ';base64,' . base64_encode($im);
  echo $base64; */
?>
