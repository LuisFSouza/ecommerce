<?php

use \Classesec\Page;
use \Classesec\Model\Product;
use \Classesec\Model\Category;

$app->get('/', function() {

	$products = Product::listAll();
    
	$page = new Page();

	$page->setTpl("index", [
		"products"=>Product::checkList($products)
	]);

});

$app->get('/categories/:idcategory', function($idcategory) {

	$category = new Category();

	$category->get((int) $idcategory);

	$page = new Page();

	$page->setTpl("category", [
		'category'=>$category->getValues(),
		'products'=>Product::checkList($category->getProducts())
	]);


});
