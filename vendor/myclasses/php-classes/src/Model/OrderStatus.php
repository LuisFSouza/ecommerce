<?php

namespace Classesec\Model;

use \Classesec\DB\Sql;
use \Classesec\Model;

class OrderStatus extends Model{
    const EM_ABERTO = 1;
    const AGUARDANDO_PAGAMENTO = 2;
    const PAGO = 3;
    const ENTREGUE = 4;

    const ERROR = "Order-Error";
    const SUCCESS = "Order-Success";

    public static function listAll()
    {
        $sql = new Sql();

        return $sql->select("select * from tb_ordersstatus order by desStatus");
    }

    public static function setError($msg)
    {
        $_SESSION[Order::ERROR] = $msg;
    }

    public static function getError()
    {
       $msg = isset($_SESSION[Order::ERROR]) && $_SESSION[Order::ERROR] ? $_SESSION[Order::ERROR] : "";
       Order::clearMsgError();
       return $msg;
    }

    public static function clearMsgError()
    {
        $_SESSION[Order::ERROR] = null;
    }

    public static function setSuccess($msg)
    {
        $_SESSION[Order::SUCCESS] = $msg;
    }

    public static function getSuccess()
    {

       $msg = isset($_SESSION[Order::SUCCESS]) && $_SESSION[Order::SUCCESS] ? $_SESSION[Order::SUCCESS] : "";
       Order::clearSuccess();
       return $msg;
    }

    public static function clearSuccess()
    {
        $_SESSION[Order::SUCCESS] = null;
    }
}

