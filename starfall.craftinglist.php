<?php
    header('Content-type: text/plain');
    define('__ROOT__', dirname(__FILE__));
    define('__DATABASE__', dirname(dirname(__FILE__)));
    define('debug', false);
    if(debug) { // Error handling only if we're debugging or developing.
        // ----------------------------------------------------------------------------------------------------
        // - Display Errors
        // ----------------------------------------------------------------------------------------------------
        ini_set('display_errors', 'On');
        ini_set('html_errors', 0);
        // ----------------------------------------------------------------------------------------------------
        // - Error Reporting
        // ----------------------------------------------------------------------------------------------------
        error_reporting(-1);
    
        // ----------------------------------------------------------------------------------------------------
        // - Shutdown Handler
        // ----------------------------------------------------------------------------------------------------
        function ShutdownHandler()
        {
            if(@is_array($error = @error_get_last()))
            {
                return(@call_user_func_array('ErrorHandler', $error));
            };
    
            return(TRUE);
        };
    
        register_shutdown_function('ShutdownHandler');
    
        // ----------------------------------------------------------------------------------------------------
        // - Error Handler
        // ----------------------------------------------------------------------------------------------------
        function ErrorHandler($type, $message, $file, $line)
        {
            $_ERRORS = Array(
                0x0001 => 'E_ERROR',
                0x0002 => 'E_WARNING',
                0x0004 => 'E_PARSE',
                0x0008 => 'E_NOTICE',
                0x0010 => 'E_CORE_ERROR',
                0x0020 => 'E_CORE_WARNING',
                0x0040 => 'E_COMPILE_ERROR',
                0x0080 => 'E_COMPILE_WARNING',
                0x0100 => 'E_USER_ERROR',
                0x0200 => 'E_USER_WARNING',
                0x0400 => 'E_USER_NOTICE',
                0x0800 => 'E_STRICT',
                0x1000 => 'E_RECOVERABLE_ERROR',
                0x2000 => 'E_DEPRECATED',
                0x4000 => 'E_USER_DEPRECATED'
            );
    
            if(!@is_string($name = @array_search($type, @array_flip($_ERRORS))))
            {
                $name = 'E_UNKNOWN';
            };
    
            return(print(@sprintf("%s Error in file \xBB%s\xAB at line %d: %s\n", $name, @basename($file), $line, $message)));
        };
    
        $old_error_handler = set_error_handler("ErrorHandler");
    }
    require_once(__DATABASE__ . '/database.php');
    if(!isset($_GET['job']) or empty($_GET['job']))
    {
        exit("No job defined.");
    }
    $inventory = connectToInventory();
    if(!$inventory)
    {
        exit("No inventory defined.");
    }
    $inventory->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $inventory->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $items;
    $results = array();
    $jobDetails;
    $recipes;

    function display($id, $inv)
    {
        $jobDetails = getJobDetails($id, $inv);
        if(!$jobDetails)
        {
            exit("No job with this ID ($id) exists.");
        }
        $recipes = getCraftList($id, $inv);
        if(!$recipes)
        {
            exit("No recipes found for " . $jobDetails['name'] . ".");
        }
        $resultArr;
        foreach($recipes as $key => $data)
        {
            $resultArr[] = $data['result'];
            $recipes[$key]['ingredient_string'] = parseRecipeMats(array($data['material_one'], $data['material_two'], $data['material_three'], $data['material_four']), $inv);
        }
        $results = getItems($resultArr, $inv);
        $formatted = "FULL RECIPE LIST FOR " . $jobDetails['name'] . ":";
        foreach($recipes as $data)
        {
            $formatted = $formatted . PHP_EOL . PHP_EOL .
            "---------------------" . PHP_EOL .
            "Name: " . $data['recipe_name'] . PHP_EOL . 
            "Level: " . $data['job_level_requirement'] . PHP_EOL . 
            PHP_EOL . "Requires: " . PHP_EOL . $data['ingredient_string'] . PHP_EOL . PHP_EOL .
            "Produces: " . $results[$data['result']]['name'] . PHP_EOL . 
            PHP_EOL . 
            "Description: " . $results[$data['result']]['description'];
            ;
        }
        if(!debug)
        {
            return $formatted;
        }
        else
        {
            echo $formatted;
        }
        if(debug)
        {
            echo "::JOB::" . PHP_EOL;
            print_r($jobDetails);
            echo "::RECIPES::" . PHP_EOL;
            print_r($recipes);
            echo "::RESULTS::" . PHP_EOL;
            print_r($results);
        }
    }

    function parseRecipeMats(array $ingredients, $inv)
    {
        $ings;
        $ids;
        foreach($ingredients as $data)
        {
            if($data != "0")
            {
                $data = explode(":", $data);
                $ings[$data[0]] = array('id' => $data[0], 'amount' => $data[1]);
                $ids[] = $ings[$data[0]]['id'];
            }
        }
        $mats = getItems($ids, $inv);
        $str;
        foreach($mats as $data)
        {
            $str[] = $ings[$data['id']]['amount'] . "x " . $data['name'];
        }
        return implode(PHP_EOL, $str);
    }

    function getJobDetails($job, $inv)
    {
        $stmt = "SELECT * FROM crafting_jobs WHERE id = ?";
        $do = $inv->prepare($stmt);
        try
        {
            $do->execute([$job]);
        }
        catch(PDOException $e)
        {
            exit($e->getMessage());
        }
        return $do->fetch(PDO::FETCH_ASSOC);
    }

    function getCraftList($job, $inv)
    {
        $stmt = "SELECT * FROM crafting_recipes WHERE job_id = ? ORDER BY job_level_requirement ASC, id ASC";
        $do = $inv->prepare($stmt);
        try
        {
            $do->execute([$job]);
        }
        catch(PDOException $e)
        {
            exit($e->getMessage());
        }
        return $do->fetchAll(PDO::FETCH_ASSOC);
    }

    function getItems(array $ids, $inv)
    {
        $q;
        foreach($ids as $d)
        {
            $q[] = "?";
        }
        $q = implode(",", $q);
        $stmt = "SELECT * FROM items WHERE id IN ($q)";
        $do = $inv->prepare($stmt);
        try
        {
            $do->execute($ids);
        }
        catch(PDOException $e)
        {
            exit($e->getMessage());
        }
        $do = $do->fetchAll(PDO::FETCH_ASSOC);
        $out;
        foreach($do as $d)
        {
            $out[$d['id']] = $d;
        }
        return $out;
    }

    echo display($_GET['job'], $inventory);

?>
