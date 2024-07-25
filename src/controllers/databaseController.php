<?php

namespace Controllers;

use Services\DatabaseService;
use Helpers\HttpRequest;
use Services\MailerService;

class DatabaseController
{

    private string $table;
    private string $pk;
    private ?string $id;
    private array $body;
    private string $action;

    public function __construct(HttpRequest $request)
    {
        $this->table = $request->route[0];
        $this->pk = "Id_" . $this->table;
        $this->id = isset($request->route[1]) ? $request->route[1] : null;

        $request_body = file_get_contents('php://input');
        $this->body = json_decode($request_body, true) ?: [];

        $this->action = $request->method;
    }

    /**
     * Retourne le résultat de la méthode ($action) exécutée
     */
    public function execute(): ?array
    {
        if ($this->action !== "POST") {
            $action = strtolower($this->action);
            $result = self::$action();
        }

        if ($this->action == "POST" && isset($this->id)) {


            if ($this->id == "product") {
                $result = $this->insertProduct($this->body);
            }

            if ($this->id == "*") {
                $result = $this->getAllWith($this->body["with"]);
            }

            if ($this->id !== "*" && $this->id !== "product") {
                $result = $this->getOneWith($this->id, $this->body["with"]);
            }
        }
        return $result;

    }

    /**
     * Action exécutée lors d'un GET
     * Retourne le résultat du selectWhere de DatabaseService
     * soit sous forme d'un tableau contenant toutes les lignes (si pas d'id)
     * soit sous forme du tableau associatif correspondant à une ligne (si id)
     */

    private function get(): ?array
    {
        $dbs = new DatabaseService($this->table);
        $datas = $dbs->selectWhere(is_null($this->id) ?: "$this->pk= ?", [$this->id]);
        return $datas;
    }

    private function put(): ?array
    {
        $dbs = new DatabaseService($this->table);
        $rows = $dbs->insertOrUpdate($this->body);
        return $rows;
    }

    private function patch()
    {
        $dbs = new DatabaseService($this->table);
        $rows = $dbs->softDelete($this->body);
        return $rows;
    }

    private function delete(): ?array
    {
        $dbs = new DatabaseService($this->table);
        $rows = $dbs->hardDelete($this->body);
        return $rows;
    }

    function sendTestMail()
    {
        $ms = new MailerService();

        $mailParams = [
            "fromAddress" => ["itstompearson.blog@gmail.com", "newsletter monblog.com"],
            "destAddresses" => ["pytompro@gmail.com"],
            "replyAddress" => ["blog@gmail.com", "information monblog.com"],
            "subject" => $this->body['subject'],
            "body" =>
            "<p>Envoyé par : " . $this->body['name'] . "</p>
                <p>Sujet : " . $this->body['subject'] . "</p>
                <p>Message : " . $this->body['message'] . "</p>
                <p>Reply to : " . $this->body['email'] . "</p>",
            "altBody" => $this->body['message']
        ];
        return $ms->send($mailParams);
    }

    function getAllWith($with)
    {
        $dbs = new DatabaseService($this->table);
        $rows = $dbs->selectWhere("is_deleted = ?", [0]);

        foreach ($rows as $row) {
            $row->with = [];

            foreach ($with as $item) {
                $dbsWith = new DatabaseService($item);
                $withRows = $dbsWith->selectWhere("is_deleted = ?", [0]);
                $valueToBind = $row->{"Id_" . $item};

                foreach ($withRows as $k) {
                    if ($k->{"Id_" . $item} == $valueToBind) {
                        $rowToFind = $dbsWith->selectWhere("Id_" . $item . " = ? AND is_deleted = ?", [$valueToBind, 0]);
                        $row->with = array_merge($row->with, $rowToFind);
                    }
                }
            }
        }

        return $rows;
    }

    function getOneWith($id, $with)
    {
        $dbs = new DatabaseService($this->table);
        $row = $dbs->selectWhere("$this->pk = ?", [$id]);
        $row[0]->with = [];

        foreach ($with as $item) {
            $dbsWith = new DatabaseService($item);
            $withRows = $dbsWith->selectWhere("is_deleted = ?", [0]);
            $valueToBind = $row[0]->{"Id_" . $item};

            foreach ($withRows as $k) {
                if ($k->{"Id_" . $item} == $valueToBind) {
                    $rowToFind = $dbsWith->selectWhere("Id_" . $item . " = ? AND is_deleted = ?", [$valueToBind, 0]);
                    $row[0]->with = array_merge($row[0]->with, $rowToFind);
                }
            }
        }

        return $row;
    }

    function insertProduct($body)
    {
        $dbs = new DatabaseService("image");
        $image = $dbs->insertOrUpdate([
            "items" => [
                [
                    "src" => $this->body["src"],
                    "alt" => $this->body["alt"]
                ]
            ]
        ]);

        if ($image) {
            $dbs = new DatabaseService("article");
            $article = $dbs->insertOrUpdate([
                "items" => [
                    [
                        "title" => $this->body["title"],
                        "content" => $this->body["content"],
                        "Id_category" => $this->body["category"],
                        "Id_image" => $image[0]->Id_image
                    ]
                ]
            ]);
            if ($article) {
                return ["result" => true];
            }
        }

        return ["result" => false];

        $bp = true;
    }



}
