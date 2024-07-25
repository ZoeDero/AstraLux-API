<?php

namespace Tools;

use Services\DatabaseService;
use Helpers\HttpRequest;
use Models\Model;
use Exception;

class Initializer
{
    public static function start(HttpRequest $request): bool
    {
        $isForce = count($request->route) > 1 && $request->route[1] == 'force';

        try {
            $arrayOfTables = self::writeTableFile($isForce);
            self::writeSchemasFiles($arrayOfTables, $isForce);

        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Génère la classe Schemas\Table (crée le fichier)
     * qui liste toutes les tables en base de données sous forme de constante
     * Renvoie la liste des tables sous forme de tableau
     * Si $isForce vaut false et que la classe existe déjà, elle n'est pas réécrite
     * Si $isForce vaut true, la classe est supprimée (si elle existe) et réécrite
     */

    private static function writeTableFile(bool $isForce = false): array
    {
        $tables = DatabaseService::getTables();
        $tableFile = "src/schemas/table.php";

        if (file_exists($tableFile) && $isForce) {
            $test = unlink($tableFile);
            if ($test == false) {
                throw new Exception("Le fichier n'a pas pu être supprimé.");
            }
        }

        if (!file_exists($tableFile)) {
            $fileContent = "<?php \r\rnamespace Schemas; \r\rclass Table { \r";
            for ($i = 0; $i < count($tables); ++$i) {
                $value = $tables[$i];
                $fileContent .= "    const " . strtoupper($value) . " = " . "'" . $value . "';\r";
            }
            $fileContent .= "}";
            $test = file_put_contents($tableFile, $fileContent);
            if ($test == false) {
                throw new Exception("Le fichier n'a pas pu être écrit.");
            }
        }

        return $tables;
    }

    /**
     * Génère une classe schema (crée le fichier) pour chaque table présente dans $tables
     * décrivant la structure de la table à l'aide de DatabaseService getSchema()
     * Si $isForce vaut false et que la classe existe déjà, elle n'est pas réécrite
     * Si $isForce vaut true, la classe est supprimée (si elle existe) et réécrite
     */

    private static function writeSchemasFiles(array $tables, bool $isForce): void
    {
        foreach ($tables as $table) {

            $className = $table;
            $schemaFile = "src/schemas/$className.php";
            $dbs = new DatabaseService();
            $schema = $dbs->getSchema($table);          
            if (file_exists($schemaFile) && $isForce) {
                $test = unlink($schemaFile);
                if ($test == false) {
                    throw new Exception("Le fichier n'a pas pu être supprimé.");
                }
            }
            if (!file_exists($schemaFile)) {
                $fileContent = "<?php \r\rnamespace Schemas; \r\rclass " . ucfirst($className) . "{\r    const COLUMNS = [\r";
                for ($i = 0; $i < count($schema); ++$i) {
                    $schemaTypesArray = $schema[$i];         
                    $typesValues = array_values($schemaTypesArray);
                    foreach($typesValues as $e){
                        if($e == "NO"){
                            array_splice($typesValues, 3, 1, "0");
                        }
                        if($e == "YES"){
                            array_splice($typesValues, 3, 1, "1");
                        }
                    }
                    $fileContent .= "        '" . $typesValues[0] . "'" . ' => ' . "['type'" . " => " . "'" . $typesValues[1] . "', " . "'" . 'nullable' . "'" . ' => ' . "'" . $typesValues[3] . "', " . "'" . 'default' . "'" . ' => ' . "'" . $typesValues[5] . "'], \r";
                }
                $fileContent .= "    ];\r}";
                $test = file_put_contents($schemaFile, $fileContent);
                if ($test == false) {
                    throw new Exception("Le fichier n'a pas pu être écrit.");
                }
            }
        }
    }
}