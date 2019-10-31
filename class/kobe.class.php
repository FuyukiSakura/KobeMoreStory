<?php
############# Kobe Object #############
class Kobe implements JsonSerializable{

  private $id;
  private $type;
  private $message;
  private $time;
  private $ip;
  private $identifier;

  public function __construct($id, $type, $message, $time, $ip, $identifier){
    $this->id = $id;
    $this->type = $type;
    $this->message = $message;
    $this->time = $time;
    $this->ip = $ip;
    $this->identifier = $identifier;
  }

  public function getArray(){
    return array('id'=>$this->id, 'id'=>$this->id, 'type'=>$this->type,
      'message'=>$this->message, 'time'=>$this->time, 'ip'=>$this->ip, 'identifier'=>$this->identifier);
  }

  public function __toString(){
    return (string) json_encode(getArray());
  }

  public function jsonSerialize(){
        return get_object_vars($this);
  }
}
?>
