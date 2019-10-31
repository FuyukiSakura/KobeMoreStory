<?php
//Class Response - Build JSON structure for response data readable by AJAX
class Response{

	private $status;
	private $message;
	private $extra;

	public function __construct($status = null, $message = null, $extra = null){
		$this->status = $status;
		$this->message = $message;
		$this->extra = $extra;
	}

	public static function internalError($message = null){
		if(is_null($message)){
			return new Self("Fail", "Internal Error");
		}
		return new Self("Fail", "Internal Error: ". $message);
	}

	public static function success($message, $extra = null){
		return new Self("Success", $message, $extra);
	}

	public static function fail($message){
		return new Self("Fail", $message);
	}

	public function __toString(){
		return (string) json_encode(array('status'=>$this->status,
											'info'=>array('message'=>$this->message,
															'extra'=>$this->extra)));
	}

}
?>
