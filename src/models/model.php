<?php

namespace Models;

use Services\DatabaseService;

class Model
{

    public string $table;
    public string $pk;
    public array $schema;

    public function __construct(string $table, array $json)
    {
        $this->table = $table;
        $this->pk = 'Id_' . $this->table;
        $this->schema = self::getSchema($table);

        if (!isset($json[$this->pk])) {
            $json[$this->pk] = self::nextGuid();
        }

        foreach ($this->schema as $k => $v) {

            if (isset($json[$k])) {
                $this->$k = $json[$k];
            } elseif ($this->schema[$k]['nullable'] == 1 && $this->schema[$k]['default'] == '') {
                $this->$k = null;
            }
            else {
                $this->$k = $this->schema[$k]['default'];
            }
        }
    }
    
    public static function getSchema(string $table): array
    {
        $schemaName = "Schemas\\" . ucfirst($table);
        file_exists($schemaName ?: null);
        return $schemaName::COLUMNS;
    }

    private function nextGuid(int $length = 16): string
    {
        $time = microtime(true) * 10000;
        $guid = base_convert($time, 10, 32);
        while (strlen($guid) < $length) {
            $random = base_convert(random_int(0, 10), 10, 32);
            $guid .= $random;
        }
        return $guid;
    }

    public function data(): array
    {
        $data = (array) clone $this;
        foreach ($data as $key => $v) {
            if (!isset($this->schema[$key])) {
                unset($data[$key]);
            }
        }
        return $data;
    }
}
