<?php

    /*
        Main program handler for everything directly to do with character data, NOT inventories.
    */

    header('Content-type: text/plain');
    define('__ROOT__', dirname(__FILE__));
    define('__DATABASE__', dirname(dirname(__FILE__)));
    define('debug', false);
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
    $headers = apache_request_headers();
    if(!isset($headers['X-Secondlife-Owner-Key']) or empty($headers['X-Secondlife-Owner-Key']))
    {
        exit("err:No owner key defined.");
    }
    $user = array(
        'username' => $headers["X-Secondlife-Owner-Name"],
        'uuid' => $headers["X-Secondlife-Owner-Key"]
    );
    $character = new character($starfall, $user);
    $var = $_POST; // Put the POST data in the new array.
    if(isset($var['boot']))
    {
        // Perform the boot function.
        echo $character->boot();
        echo "test";
    }
?>
