<?php namespace Helpers;

use Exception;

class Token {

    private static $prefix = "$2y$08$"; // Bcryp (salt = 8)
    private static $defaultValidity = 60 * 60 * 1;
    private static $separator = "|";

    private function __construct()
    {
        $args = func_get_args();
        if(empty($args)){
            throw new Exception("one argument required");
        }
        elseif(is_array($args[0])){ 
            $this->encode($args[0]);
        }
        elseif(is_string($args[0])){ 
            $this->decode($args[0]);
        }
        else{
            throw new Exception("argument must be a string or an array");
        }
        
    }

    public array $decoded;
    public string $encoded;
    public static function create($entry) : Token
    {
        return new Token($entry);
    }

    /**
     * Vérifie la validité du token encodé ($this->decoded not null)
     * si $withDate vaut true vérifie également les date expireAt et usableAt
     */
    public function isValid(bool $withDate = true) : bool
    {
        if(!isset($this->decoded)){
            return false;
        }
        if($withDate && (isset($this->decoded['expireAt']) && $this->decoded['expireAt'] < time())){
            return false;
        }
        if($withDate && isset($this->decoded['usableAt']) && $this->decoded['usableAt'] > time()){
            return false;
        }
        return true;
    }

    /**
     * 1. Crée un token à partir d'un tableau de données
     * 2. $decoded contient les informations a stocker dans la token
     * Si les entrées createdAt, usableAt, validity et expireAt 
     * ne sont pas fournies dans $decoded, il faut les ajouter
     * 3. un token est composé d'un payload et d'une signature 
     * (séparé par un caractère remarquable qui permettra un découpage)
     * Le payload est un encodage en base 64 du tableau de données (stringifié)
     * La signature est égale au payload hashé en bcrypt (salt = 8)
     * Le token, une fois construit, doit être encodé pour pouvoir être transmis dans un url
     */
    private function encode(array $decoded = []) : void
    {
        $decoded['createdAt'] = time();
        if(!isset($decoded['usableAt'])){
            $decoded['usableAt'] = $decoded['createdAt'];
        }
        if(!isset($decoded['validity']) && !isset($decoded['expireAt'])){
            $decoded['validity'] = self::$defaultValidity;
            $decoded['expireAt'] = $decoded['usableAt'] + $decoded['validity'];
        }
        elseif(!isset($decoded['expireAt'])){
            $decoded['expireAt'] = $decoded['usableAt'] + $decoded['validity'];
        }
        elseif(!isset($payload['validity'])){
            $decoded['validity'] = $decoded['expireAt'] - $decoded['usableAt'];
        }
        $this->decoded = $decoded;
        $payload = json_encode($decoded);
        $payload = base64_encode($payload);
        $secret_key = $_ENV['config']->secret_key->secret;
        $signature = password_hash($payload. self::$separator . $secret_key, PASSWORD_BCRYPT, ['cost' => 8]);
        $encoded = str_replace(self::$prefix, "", $signature) . self::$separator . $payload;
        $this->encoded = urlencode($encoded);
    }

    /**
     * Decode un token pour obtenir le tableau de données initial
     * (faire le cheminement de la méthode encode dans l'autre sens)
     */
    private function decode(string $encoded) : void
    {
        $this->encoded = $encoded;
        $encoded = urldecode($this->encoded) ;
        $encodedSplit = explode(self::$separator, $encoded);
        if(count($encodedSplit) == 2){
            $payload = $encodedSplit[1];
            $signature = self::$prefix . $encodedSplit[0];
            $secret_key = $_ENV['config']->secret_key->secret;
            $isValid = password_verify($payload. self::$separator . $secret_key, $signature);
            if($isValid){
                $payload = base64_decode($payload);
                $decoded = json_decode($payload, true);
            }
        }
        $this->decoded = $decoded ?? null;
    }


}