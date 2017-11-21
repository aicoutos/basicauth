<?php
/**
* Basic
* Micro framework em PHP
*/

namespace Basic;

use Medoo\Medoo;

/**
 * Classe Auth
 */
class Auth
{
    private $db;

    /**
     * Seta a variável $db
     * @param array $db Dados SQL
     */
    public function __construct($db=null)
    {
        if(is_null($db)){
            die("db not found");
        }else{
            $this->db = new Medoo([
                // required
                'database_type' => 'mysql',
                'database_name' => $db['db_name'],
                'server' => $db['db_server'],
                'username' => $db['db_user'],
                'password' => $db['db_password'],
                // [optional]
                'charset' => 'utf8',
                'port' => 3306
            ]);
        }
    }
    /**
    * Verifica se o usuário está autenticado
    * @return mixed Retorna os dados dele caso esteja ou retorna false
    */
    public function isAuth()
    {
        if (!isset($_COOKIE['id'])) {
            return false;
        }
        if (!isset($_COOKIE['token'])) {
            return false;
        }
        $where['AND']=[
            'id'=>@$_COOKIE['id'],
            'token'=>@$_COOKIE['token']
        ];
        $user=$this->db->get("users", '*', $where);
        if (isset($user['token_expiration'])) {
            if ($user['token_expiration']>time()) {
                return $user;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    /**
    * Faz o logout do usuaŕio
    * @return bool Retorna true ou false
    */
    public function logout()
    {
        $user=$this->isAuth();
        setcookie("token", "", time()-3600, '/');
        setcookie("id", "", time()-3600, '/');
        if ($user) {
            $data=[
                'token_expiration'=>time()-3600
            ];
            $this->db->update("users", $data, ['id'=>$user['id']]);
        }
        return true;
    }
    /**
    * Autentica o usuário baseado nas variáveis $_POST
    * @return mixed Dados do usuário ou mensagens de erro
    */
    public function signin()
    {
        $this->logout();
        $email=@$_POST['email'];
        $password=@$_POST['password'];
        $error=false;
        $where=[
            'email'=>$email
        ];
        $user=$this->db->get("users", '*', $where);
        if (!$user) {
            $error[]='invalid_email';
        }
        if (password_verify($password, $user['password'])) {
            $id=$user['id'];
            $min=60;
            $hora=60*$min;
            $dia=24*$hora;
            $ano=365*$dia;
            $limit=time()+(2*$ano);
            $token=bin2hex(openssl_random_pseudo_bytes(32));
            $data=[
                'token'=>$token,
                'token_expiration'=>$limit
            ];
            $this->db->update("users", $data, ['id'=>$id]);
            setcookie("id", $id, $limit, '/');
            setcookie("token", $token, $limit, '/');
            return $this->db->get("users", "*", ['id'=>$id]);
        } else {
            $error[]='invalid_password';
        }
        if ($error) {
            return ['error'=>$error];
        }
    }
    /**
    * Cadastra de usuário baseado nas variáveis $_POST e no parâmetro $user
    * @param  boolean $user Dados do usuário
    * @return mixed         Faz o signin criando o token de autenticação
    */
    public function signup($user=false)
    {
        $this->logout();
        $user['created_at']=time();
        if ($user===false) {
            $user=[
                'name'=>@$_POST['name'],
                'email'=>@$_POST['email'],
                'password'=>@$_POST['password']
            ];
        }
        $user['name']=trim($user['name']);
        $user['name']=strtolower($user['name']);
        $user['name']=ucfirst($user['name']);
        $user['name']=preg_replace('/\s+/', ' ', $user['name']);
        $error=false;
        if (preg_match('/^[a-z0-9 .\-]+$/i', $user['name']) && strlen($user['name'])>=3) {
            if (filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                if (strlen($user['password'])>=8) {
                    $user['password']=password_hash($user['password'], PASSWORD_DEFAULT);
                    if ($this->db->get("users", '*', ['email'=>$user['email']])) {
                        $error[]='invalid_email';
                    } else {
                        $data=[
                            'email'=>$user['email'],
                            'name'=>$user['name'],
                            'password'=>$user['password']
                        ];
                        if (isset($user['type'])) {
                            $user['type']=trim(strtolower($user['type']));
                            if (
                                $user['type']=='admin' ||
                                $user['type']=='super' ||
                                $user['type']=='user'
                            ) {
                                $data['type']=$user['type'];
                            } else {
                                $data['type']='user';
                            }
                        }
                        $this->db->insert("users", $data);
                        $id=$this->db->id();
                        if (is_numeric($id) && $id<>0) {
                            if (isset($_POST['email']) && isset($_POST['password'])) {
                                $this->signin();
                            } else {
                                return $id;
                            }
                        } else {
                            return false;
                        }
                    }
                } else {
                    $error[]='invalid_password';
                }
            } else {
                $error[]='invalid_email';
            }
        } else {
            $error[]='invalid_name';
        }
        if ($error) {
            return ['error'=>$error];
        }
    }
}
