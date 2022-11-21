<?php

    /*
        Main program handler for everything directly to do with character data, NOT inventories.
    */

    header('Content-type: text/plain');
    define('__ROOT__', dirname(__FILE__));
    define('__DATABASE__', dirname(dirname(__FILE__)));
    define('debug', true);
    require_once(__DATABASE__ . '/database.php');
    require_once(__ROOT__ . '/global.functions.php');
    $starfall = starfallConnect();
    if(!$starfall)
    {
        exit("err:Could not connect to the database.");
    }
    $starfall->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $starfall->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    spl_autoload_register(function ($name) {
        include __ROOT__ . '/classes/' . $name . '.class.php';
    });
    if(!debug)
    {
        $user = array(
            'username' => $headers["X-SecondLife-Owner-Name"],
            'uuid' => $headers["X-SecondLife-Owner-Key"]
        );
    }
    else
    {
        $user = array(
            'username' => $_GET['usr'],
            'uuid' => $_GET['uuid']
        );
    }
    $character = new character($starfall, $user);
?>
