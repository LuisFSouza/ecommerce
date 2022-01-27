<?php

use \Classesec\Model\User;

function formatPrice(float $vlprice)
{
    if($vlprice != null )
    {
        return number_format($vlprice, 2, ",", "." );
    }

}

function checkLogin($inadmin = true)
{
    return User::checkLogin($inadmin);
}

function getUserName()
{
    $user = User::getFromSession();
    return $user->getdesperson();
}