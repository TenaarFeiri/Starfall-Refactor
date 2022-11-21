<?php

    class character
    {
        private $pdo;
        private $user;
        function __construct($pdo, array $user)
        {
            $this->pdo = $pdo;
            $this->user = $user;
            if(debug)
            {
                echo "Initialised." . PHP_EOL . __DATABASE__ . PHP_EOL;
                print_r($this->user);
            }
            $this->checkUserExists();
        }

        function checkUserExists()
        {
            // Verify the existence of the user. If they do not exist in the system, import character data from other DBs.
            $stmt = "SELECT * FROM players WHERE uuid = :uuid";
            $do = $this->pdo->prepare($stmt);
            $do->bindParam(":uuid", $this->user['uuid']);
            try
            {
                $do->execute();
                $result = $do->fetch(PDO::FETCH_ASSOC);
            }
            catch(PDOException $e)
            {
                exit($e->getMessage());
            }
            if(!$result)
            {
                // Perform the import here.
                $this->importFromOldDatabase();
            }
        }

        function importFromOldDatabase()
        {
            require_once(__DATABASE__ . '/database.php');
            $oldStarfallInventory = connectToInventory();
            $oldStarfallUsrData = connectToRptool();
            $inventories = array();
            $characters = array();
            $characterJobs = array();
            $personalVaults = array();
            $usrData = array();
            $stmt = "SELECT * FROM users WHERE uuid = :uuid";
            try
            {
                $do = $oldStarfallUsrData->prepare($stmt);
                $do->bindParam(":uuid", $this->user['uuid']);
                $do->execute();
                $usrData = $do->fetch(PDO::FETCH_ASSOC);
                $stmt = "SELECT * FROM rp_tool_character_repository WHERE user_id = :userid";
                $do = $oldStarfallUsrData->prepare($stmt);
                $do->bindParam(":userid", $usrData['id']);
                $do->execute();
                $characters = $do->fetchAll(PDO::FETCH_ASSOC);
                $stmt = "SELECT * FROM character_inventory WHERE char_id = ?";
                $do = $oldStarfallInventory->prepare($stmt);
                foreach($characters as $var)
                {
                    $do->execute([$var['character_id']]);
                    $result = $do->fetch(PDO::FETCH_ASSOC);
                    if($result)
                    {
                        $inventories[$var['character_id']] = $result;
                    }
                    $jobStmt = "SELECT * FROM crafters WHERE char_id = ?";
                    $job = $oldStarfallInventory->prepare($jobStmt);
                    $job->execute([$var['character_id']]);
                    $job = $job->fetch(PDO::FETCH_ASSOC);
                    if($job)
                    {
                        $characterJobs[$var['character_id']] = $job;
                    }
                    // Code to grab personal vaults here.
                }
                if(debug)
                {
                    print_r($usrData);
                    print_r($characters);
                    print_r($inventories);
                    if($characterJobs)
                    {
                        print_r($characterJobs);
                    }
                }
            }
            catch(PDOException $e)
            {
                exit($e->getMessage());
            }
        }
    }

?>
