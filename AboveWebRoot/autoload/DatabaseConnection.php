<?php
class DatabaseConnection
{
  static function connect()
  {
    return new DB\SQL(
  	"mysql:host=localhost;port=3306;dbname=iriaedin_DANC",
  	"iriaedin_iriaedin",
  	"#Song#20040304"
    );
  }}

?>
