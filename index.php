<?php 

require_once("vendor/autoload.php");

$app = new \Slim\Slim();

$app->config('debug', true);

$app->get('/', function() {
    
	$sql = new Classdb\DB\Sql();

	$results = $sql->select("select * from tb_users");
	echo json_encode($results);

});

$app->run();

 ?>