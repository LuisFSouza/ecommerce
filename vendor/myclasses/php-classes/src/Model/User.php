<?php

namespace Classesec\Model;

use \Classesec\DB\Sql;
use \Classesec\Model;
use Exception;
use \Classesec\Mailer;

class User extends Model{
    const SESSION = "User";
    const SECRET = "cursophp7_secret";
    const SECRET_IV = "cursophp7_Secret_IV";
    const ERROR = "UserError";
    const ERROR_REGISTER = "UserErrorRegister";
    const SUCCESS = "UserSuccess";
    
    public static function getFromSession()
    {   
        $user = new User();
        
        if(isset($_SESSION[User::SESSION]) && (int)$_SESSION[User::SESSION]['iduser'] > 0)
        {
            $user->setData($_SESSION[User::SESSION]);

        }
        return $user;
    }

    public static function checkLogin($inadmin = true)
    {
        if(!isset($_SESSION[User::SESSION]) || !$_SESSION[User::SESSION] || !(int)$_SESSION[User::SESSION]["iduser"] > 0)
        {
           return false;
        }
        else
        {
            if($inadmin === true && (bool)$_SESSION[User::SESSION]['inadmin'] === true)
            {
             return true;   
            }
            else if($inadmin === false)
            {
                return true;
            }
            else{
                return false;
            }
        }
    }

    public static function login($login, $password)
    {
        $sql = new Sql();

        $results = $sql->select("select * from tb_users a inner join tb_persons b on a.idperson = b.idperson where a.deslogin = :LOGIN", array(
            ":LOGIN"=>$login
        ));
        
        
        if (count($results) === 0)
        {
            throw new \Exception("Usuário inexistente ou senha inválida");
        }
        
        $data = $results[0];
        
        if(password_verify($password, $data["despassword"])=== true)
        {
        
            $user = new User();

            $data['desperson'] = utf8_encode($data['desperson']);
            
            $user->setData($data);
        
            $_SESSION[User::SESSION] = $user->getValues();

            return $user;
        }
        else
        {
            throw new \Exception("Usuário inexistente ou senha inválida");
        }
    }

    public static function verifyLogin($inadmin = true)
    {
        if(!User::checkLogin($inadmin))
        {
            if($inadmin)
            {
                header("Location: /admin/login");
            }
            else
            {
                header("Location: /login");
            }
            exit;
        }
    }

    public static function logout()
    {
        $_SESSION[User::SESSION] = NULL;
    }

    public static function listAll()
    {
        $sql = new Sql();

        return $sql->select("select * from tb_users a inner join tb_persons b using(idperson) order by b.desperson");
    }

    public function save()
    {
        $sql = new Sql();
        
        $results = $sql->select("call sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array (
            ":desperson" => utf8_decode($this->getdesperson()),
            ":deslogin" =>$this->getdeslogin(),
            ":despassword" =>User::getPasswordHash($this->getdespassword()),
            ":desemail" =>$this->getdesemail(),
            ":nrphone" =>$this->getnrphone(),
            ":inadmin" =>$this->getinadmin()
        ));

        
        $this->setData($results[0]);
    }

    public function get($iduser)
    {
        $sql = new Sql();

        $results = $sql->select("select * from tb_users a inner join tb_persons b using(idperson) where a.iduser = :iduser", [
            ":iduser"=>$iduser
        ]);

        $results[0]['desperson'] = utf8_encode($results[0]['desperson']);

        $this->setData($results[0]);
    }

    public function update($passwordHash = true)
    {
        $sql = new Sql();

        if($passwordHash)
        {
            $password = User::getPasswordHash($this->getdespassword());
        }
        else
        {
            $password = $this->getdespassword();
        }
        
        $results = $sql->select("call sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array (
            "iduser" => $this->getiduser(),
            "desperson" => utf8_decode($this->getdesperson()),
            "deslogin" =>$this->getdeslogin(),
            "despassword" => $password,
            "desemail" =>$this->getdesemail(),
            "nrphone" =>$this->getnrphone(),
            "inadmin" =>$this->getinadmin()
        ));

        
        $this->setData($results[0]);
    }

    public function delete()
    {
        $sql = new Sql();
        
        $sql->query("call sp_users_delete(:iduser)" , array(
            ":iduser"=>$this->getiduser()
        ));
    }

    public static function getForgot($email, $inadmin = true)
    {
        $sql = new Sql();
        
        $results = $sql->select("select * from tb_persons a inner join tb_users b using(idperson) where a.desemail = :email" , array(
            ":email"=>$email
        ));

        if(count($results) === 0)
        {
            throw new \Exception("Não foi possivel recuperar a senha");
        }
        else
        {
            $data = $results[0];

            $results2 = $sql->select("call sp_userspasswordsrecoveries_create(:iduser, :desip)", array(
                ":iduser"=>$data["iduser"],
                ":desip"=>$_SERVER["REMOTE_ADDR"]
            ));

            if(count($results2) === 0)
            {
                throw new \Exception("Não foi possivel recuperar a senha");
            }
            else
            {
                $dataRecovery = $results2[0];

                $code = openssl_encrypt($dataRecovery['idrecovery'], 'AES-128-CBC', pack("a16", User::SECRET), 0, pack("a16", User::SECRET_IV));
                $result = base64_encode($code);
                
                if($inadmin === true)
                {
                    $link = "http://www.myecommercephpcourse.com.br/admin/forgot/reset?code=$result";
                }
                else
                {
                    $link = "http://www.myecommercephpcourse.com.br/forgot/reset?code=$result";
                }
               

                $mailer = new Mailer($data['desemail'], $data['desperson'], "Redefinir senha da UFF Store", "forgot", array(
                    "name"=>$data['desperson'],
                    "link"=>$link
                )); 

                $mailer->send();

                return $data;
            }
        }
    }

    public static function validForgotDecrypt($code)
    {
        $result = base64_decode($code);

		$idrecovery = openssl_decrypt($result, 'AES-128-CBC', pack("a16", User::SECRET), 0, pack("a16", User::SECRET_IV));

        $sql = new Sql();
        
        $results = $sql->select("select * from tb_userspasswordsrecoveries a inner join tb_users b using(iduser) inner join tb_persons c using(idperson) where a.idrecovery = :idrecovery and a.dtrecovery is null and date_add(a.dtregister, interval 1 hour) >= now()", array(
            ":idrecovery"=>$idrecovery
        ));
        if (count($results) === 0)
        {
            throw new \Exception("Não foi possível recuperar a senha.");
        }
        else
        {
            return $results[0];
        }
    }

    public static function setForgotUsed($idrecovery)
    {
        $sql = new Sql();
        
        $sql->query("update tb_userspasswordsrecoveries set dtrecovery = now() where idrecovery = :idrecovery" , array(
            ":idrecovery"=>$idrecovery
        ));
    }

    public function setPassword($password)
    {
        $sql = new Sql();
        
        $sql->query("update tb_users set despassword = :password where iduser = :iduser" , array(
            ":password"=>$password,
            ":iduser"=>$this->getiduser()
        ));
    }

    public static function setMsgError($msg)
    {
        $_SESSION[User::ERROR] = $msg;
    }

    public static function getMsgError()
    {
       $msg = isset($_SESSION[User::ERROR]) && $_SESSION[User::ERROR] ? $_SESSION[User::ERROR] : "";
       User::clearMsgError();
       return $msg;
    }

    public static function clearMsgError()
    {
        $_SESSION[User::ERROR] = null;
    }

    public static function getPasswordHash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT, [
            'cost'=>12
        ]);
    }

    public static function setErrorRegister($msg)
    {
        $_SESSION[User::ERROR_REGISTER] = $msg;
    }

    public static function getErrorRegister()
    {

       $msg = isset($_SESSION[User::ERROR_REGISTER]) && $_SESSION[User::ERROR_REGISTER] ? $_SESSION[User::ERROR_REGISTER] : "";
       User::clearErrorRegister();
       return $msg;
    }

    public static function clearErrorRegister()
    {
        $_SESSION[User::ERROR_REGISTER] = null;
    }

    public static function checkLoginExists($login)
    {
        $sql = new Sql();
        
        $results = $sql->select("select * from tb_users where deslogin = :deslogin", [
            ':deslogin'=>$login
        ]);

        return (count($results) > 0);
    }

    public static function setSuccess($msg)
    {
        $_SESSION[User::SUCCESS] = $msg;
    }

    public static function getSuccess()
    {

       $msg = isset($_SESSION[User::SUCCESS]) && $_SESSION[User::SUCCESS] ? $_SESSION[User::SUCCESS] : "";
       User::clearSuccess();
       return $msg;
    }

    public static function clearSuccess()
    {
        $_SESSION[User::SUCCESS] = null;
    }

}

