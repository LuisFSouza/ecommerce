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
        if(!isset($_SESSION[User::SESSION]) || !$_SESSION[User::SESSION] || !(int)$_SESSION[User::SESSION]["iduser"] > 0 || (bool)$_SESSION[User::SESSION]["inadmin"] !== $inadmin )
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

        $results = $sql->select("select * from tb_users where deslogin = :LOGIN", array(
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
        if(User::checkLogin($inadmin))
        {
            header("Location: /admin/login");
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
            ":desperson" => $this->getdesperson(),
            ":deslogin" =>$this->getdeslogin(),
            ":despassword" =>password_hash($this->getdespassword(), PASSWORD_DEFAULT, [
                "cost"=>12
            ]),
            ":desemail" =>$this->getdesemail(),
            ":nrphone" =>$this->getnrphone(),
            ":inadmin" =>$this->getinadmin()
        ));

        
        $this->setData($results[0]);
    }

    public function get($iduser)
    {
        $sql = new Sql();

        $results = $sql->select("select * from tb_users a inner join tb_persons b using(idperson) where a.iduser = :iduser", array(
            ":iduser"=>$iduser
        ));

        $this->setData($results[0]);
    }

    public function update()
    {
        $sql = new Sql();
        
        $results = $sql->select("call sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array (
            "iduser" => $this->getiduser(),
            "desperson" => $this->getdesperson(),
            "deslogin" =>$this->getdeslogin(),
            "despassword" =>$this->getdespassword(),
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

    public static function getForgot($email)
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
               
                $link = "http://www.myecommercephpcourse.com.br/admin/forgot/reset?code=$result";

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
}

