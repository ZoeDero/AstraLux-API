<?php

namespace Controllers;

use Services\DatabaseService;
use Helpers\HttpRequest;
use Helpers\Token;
use Services\MailerService;

class AuthController
{

    public function __construct(HttpRequest $request)
    {
        $this->controller = $request->route[0];
        $this->function = isset($request->route[1]) ? $request->route[1] : null;

        $request_body = file_get_contents('php://input');
        $this->body = json_decode($request_body, true) ?: [];

        $this->action = $request->method;
    }

    public function execute()
    {

        $function = $this->function;
        $result = self::$function();
        return $result;
    }

    public function login()
    {
        $dbs = new DatabaseService('app_user');
        $email = filter_var($this->body['mail'], FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ["result" => false];
        }

        $user = $dbs->selectWhere("mail = ? AND is_deleted = ?", [$email, 0]);
        $prefix = $_ENV['config']->hash->prefix;

        if (count($user) == 1 && password_verify($this->body['password'], $prefix . $user[0]->password)) {

            $dbs = new DatabaseService("role");
            $role = $dbs->selectWhere("Id_role = ? AND is_deleted = ?", [$user[0]->Id_role, 0]);

            $tokenFromDataArray = Token::create(['mail' => $user[0]->mail, 'password' => $user[0]->password]);
            $encoded = $tokenFromDataArray->encoded;

            return ["result" => true, "role" => $role[0]->weight, "id" => $user[0]->Id_app_user, "token" => $encoded];
        }

        return ["result" => false];
    }

    public function check()
    {
        $headers = apache_request_headers();
        if (isset($headers["Authorization"])) {
            $token = $headers["Authorization"];
        }

        if (isset($token) && !empty($token)) {

            $tokenFromEncodedString = Token::create($token);
            $decoded = $tokenFromEncodedString->decoded;
            $test = $tokenFromEncodedString->isValid();

            if ($test == true) {
                $dbs = new DatabaseService("app_user");
                $user = $dbs->selectWhere("mail = ? AND is_deleted = ?", [$decoded["mail"], 0]);

                $dbs = new DatabaseService("role");
                $role = $dbs->selectWhere("Id_role = ? AND is_deleted = ?", [$user[0]->Id_role, 0]);

                return ["result" => true, "role" => $role[0]->weight, "id" => $user[0]->Id_app_user];
            }

            return ["result" => false];
        }

        return ["result" => false];
    }

    public function register()
    {
        $dbs = new DatabaseService("app_user");
        $user = $dbs->selectWhere("mail = ? AND is_deleted = ?", [$this->body['mail'], 0]);

        if (count($user) > 0) {
            return ['result' => false, 'message' => 'email ' . $this->body['mail'] . ' already used'];
        }

        $dbs = new DatabaseService("account");
        $account = $dbs->selectWhere("pseudo = ? AND is_deleted = ?", [$this->body['pseudo'], 0]);
        if (count($account) > 0) {
            return ['result' => false, 'message' => 'pseudo ' . $this->body['pseudo'] . ' already used'];
        }

        $tokenFromDataArray = Token::create(['pseudo' => $this->body['pseudo'], 'mail' => $this->body['mail']]);
        $token = $tokenFromDataArray->encoded;

        $href = "http://localhost:3000/account/validate/$token";

        $ms = new MailerService();
        $mailParams = [
            "fromAddress" => ["register@monblog.com", "nouveau compte monblog.com"],
            "destAddresses" => [$this->body['mail']],
            "replyAddress" => ["noreply@monblog.com", "No Reply"],
            "subject" => "Créer votre compte nomblog.com",
            "body" => 'Click to validate the account creation <br>
                    <a href="' . $href . '">Valider</a> ',
            "altBody" => "Go to $href to validate the account creation"
        ];

        $sent = $ms->send($mailParams);

        return ['result' => $sent['result'], 'message' => $sent['result'] ?
            "Vérifier votre boîte mail et confirmer la création de votre compte sur monblog.com" :
            "Une erreur est survenue, veuiller recommencer l'inscription"];
    }

    public function validate()
    {

        $token = $this->body['token'] ?? "";

        if (isset($token) && !empty($token)) {

            $tokenFromEncodedString = Token::create($token);
            $decoded = $tokenFromEncodedString->decoded;
            $test = $tokenFromEncodedString->isValid();

            if ($test == true) {
                return ['result' => true, "pseudo" => $decoded['pseudo'], "mail" => $decoded['mail']];
            }

            return ['result' => false];
        }

        return ['result' => false];
    }

    public function create()
    {
        $dbs = new DatabaseService("role");
        $role = $dbs->selectWhere("weight = ? AND is_deleted = ?", [1, 0]);
        $password = password_hash($this->body["pass"], PASSWORD_ARGON2ID, [
            'memory_cost' => 1024,
            'time_cost' => 2,
            'threads' => 2
        ]);
        $prefix = $_ENV['config']->hash->prefix;
        $password = str_replace($prefix, "", $password);
        $dbs = new DatabaseService("account");
        $account = $dbs->insertOrUpdate(["items" => [
                [
                    "pseudo" => $this->body["data"]["pseudo"]
                ]]
        ]);
        if ($account) {
            $dbs = new DatabaseService("app_user");
            $user = $dbs->insertOrUpdate(["items" => [
                    [
                        "mail" => $this->body["data"]["mail"],
                        "password" => $password,
                        "Id_role" => $role[0]->Id_role,
                        "Id_account" => $account[0]->Id_account
                    ]]
            ]);
            if ($user) {
                return ["result" => true];
            }
        }
        return ["result" => false];
    }
}
