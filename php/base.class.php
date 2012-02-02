<?php

class base {
  public $config;

  function __autoload($class)
  {
    require_once("$class.class.php");
  }

  function __construct($config)
  {
    $this->config = $config;
  }

  function timestamp($format = "Y-m-d H:i:s")
  {
    return date($format);
  }

  function error($arg, $log = false)
  {
    if($log)
      {
	$trace = debug_backtrace();
	$this->log("=====================\n");
	$this->log("ERROR: " . $arg . "\n");
	$this->log(implode('\n',$trace));
	$this->log("=====================\n");
      }
    die($arg);
  }

  function log($msg, $logfile = "log.txt")
  {
    if(!($fh = fopen($logfile, "at")))
      die("<strong>ERROR</strong>: Could not open $logfile for logging.");
    $fwrite($fh, $msg."\n");
    @fclose($fh);
  }

  function display()
  {
    echo " == " . get_class($this) . "<br />";
    foreach($this as $key => $value)
      if(is_object($value) && (is_subclass_of($value,"base") || get_class($value) == "base"))
	{
	  echo "$key...<br>";
	  $value->display();
	}
      else 
	if(!is_object($value))
	  if(is_array($value))
	    {
	      echo "$key => ";
	      print_r($value);
	      echo "<br />";
	    }
	  else
	    echo "$key => $value<br />";
    echo " /// " . get_class($this) . "<br />";;
  }
}

?>