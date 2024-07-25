<?php
// 
// require "./vendor/autoload.php";

// spl_autoload_register enregisre des fonctions et les charges automatiquement;
// les fonctions : c'est charger des fichiers (require this, require that);
// Nom du namespace === nom du dossier
// On va passer une fonction qui a partir d'un nom de classe $className va importer le fichier portant le même nom dans un dossier;
// La variable className n'a pas besoin d'être fournie, c'est la fonction spl_autoloader_register() qui va chercher les classes ou fonctions en fonction du code (ex : dans index.php le fichier ArticleController.php n'est recherché par l'autoloader que lorsqu'on atteint la ligne  new ArticleController)

function loadFiles($className)
{
    $classPath = "src\\" . strtolower($className) . ".php";
    if (file_exists($classPath)) {
        require_once $classPath;
    }

    $toolsPath = strtolower($className) . ".php";
    if (file_exists($toolsPath)) {
        require_once $toolsPath;
    }

  
}

spl_autoload_register("loadFiles");