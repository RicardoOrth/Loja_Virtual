<?php
/**
 * Bootstrap da aplicação.
 * Centraliza a conexão e o carregamento automático (autoload) das
 * entidades (models/) e dos DAOs (dao/), evitando require_once espalhados.
 *
 * Basta cada página fazer:  require_once "config/bootstrap.php";
 */

require_once __DIR__ . "/database.php";
require_once __DIR__ . "/constants.php";

// Autoload simples: procura a classe em models/ e depois em dao/
spl_autoload_register(function ($classe) {
    $caminhos = [
        __DIR__ . "/../models/" . $classe . ".php",
        __DIR__ . "/../dao/"    . $classe . ".php",
    ];
    foreach ($caminhos as $arquivo) {
        if (file_exists($arquivo)) {
            require_once $arquivo;
            return;
        }
    }
});

/** Atalho para obter a conexão PDO. */
function getDB() {
    return (new Database())->getConnection();
}
