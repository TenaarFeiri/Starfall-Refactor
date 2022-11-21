<?php

    // White List to ensure data sanity when using dynamic database queries.
    function whitelist(&$value, $allowed, $message)
    {
        if($value === null)
        {
            return $allowed[0];
        }
        $key = array_search($value, $allowed, true);
        if($key === false)
        {
            throw new InvalidArgumentException($message);
        }
        else
        {
            return $value;
        }
    }

?>
