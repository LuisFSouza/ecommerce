<?php

use \Classesec\Page;

$app->get('/', function() {
    
	$page = new Page();

	$page->setTpl("index");

});
