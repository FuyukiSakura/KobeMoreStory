<?php
	spl_autoload_register(function ($class_name) {
		$path = RELATIVE_PATH. 'class/'. $class_name .'.class.php';
		if(file_exists($path)){
			include_once $path;
		}
	});
?>
