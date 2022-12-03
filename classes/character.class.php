<?php

    class character
    {
        private $pdo;
        private $user;
        private $player;
        function __construct($pdo, array $user)
        {
            $this->pdo = $pdo;
            $this->user = $user;
            if(debug)
            {
                echo "Initialised." . PHP_EOL . __DATABASE__ . PHP_EOL;
                print_r($this->user);
            }
            $this->player = $this->checkUserExists();
            if(!$this->player)
            {
                // Perform the import here, then fetch relevant data into a var in case we're doing something.
                $this->player = $this->importFromOldDatabase();
            }
            $this->updateUsername();
            $this->lastActive();
        }

        function getTimePST()
        {
            $pst = new DateTimeZone('America/Los_Angeles');
            $time = new DateTime('', $pst);
            return $time->format('H:i:s T - l, M d, Y');
        }

        function lastActive()
        {
            $this->pdo->beginTransaction();
            $stmt = "UPDATE players SET last_active = ? WHERE uuid = ?";
            $do = $this->pdo->prepare($stmt);
            try
            {
                $do->execute([$this->getTimePST(), $this->player['uuid']]);
                $this->pdo->commit();
            }
            catch(PDOException $e)
            {
                $this->pdo->rollBack(); // Don't error if this fails, just silently roll back.
            }
        }

        function updateUsername()
        {
            if($this->player['username'] != $this->user['username'])
            {
                $this->pdo->beginTransaction();
                $stmt = "UPDATE players SET username = :usr WHERE uuid = :uuid";
                $do = $this->pdo->prepare($stmt);
                $do->bindParam(":usr", $this->user['username']);
                $do->bindParam(":uuid", $this->user['uuid']);
                try
                {
                    $do->execute();
                    $this->pdo->commit();
                }
                catch(PDOException $e)
                {
                    exit("err: Fatal Error -- Could not update altered username.");
                }
            }
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
                return false;
            }
            return $result;
        }

        function importFromOldDatabase()
        {
            $oldStarfallInventory = connectToInventory();
            $oldStarfallUsrData = connectToRptool();
            $inventories;
            $characters;
            $characterJobs;
            $personalVaults;
            $usrData;
            $factionData;
            $stmt = "SELECT * FROM users WHERE uuid = :uuid";
            try
            {
                $do = $oldStarfallUsrData->prepare($stmt);
                $do->bindParam(":uuid", $this->user['uuid']);
                $do->execute();
                $usrData = $do->fetch(PDO::FETCH_ASSOC);
                $stmt = "SELECT * FROM rp_tool_character_repository WHERE user_id = :userid AND deleted = 0";
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
                    $jobStmt = "SELECT * FROM vault_personal WHERE char_id = ?";
                    $job = $oldStarfallInventory->prepare($jobStmt);
                    $job->execute([$var['character_id']]);
                    $job = $job->fetchAll(PDO::FETCH_ASSOC);
                    if($job)
                    {
                        $personalVaults[$var['character_id']] = $job;
                    }
                    // Get faction details.
                    $jobStmt = "SELECT * FROM faction_members WHERE char_id = ?";
                    $job = $oldStarfallInventory->prepare($jobStmt);
                    $job->execute([$var['character_id']]);
                    $job = $job->fetch(PDO::FETCH_ASSOC);
                    if($job)
                    {
                        $factionData[$var['character_id']] = $job;
                    }
                }
                if(debug)
                {
                    echo PHP_EOL . "::USER DATA::" . PHP_EOL . PHP_EOL;
                    print_r($usrData);
                    echo PHP_EOL . "::CHARACTERS::" . PHP_EOL . PHP_EOL;
                    print_r($characters);
                    echo PHP_EOL . "::INVENTORIES::" . PHP_EOL . PHP_EOL;
                    if(!empty($inventories))
                    {
                        print_r($inventories);
                    }
                    if(!empty($characterJobs))
                    {
                        echo PHP_EOL . "::CHAR JOBS::" . PHP_EOL . PHP_EOL;
                        print_r($characterJobs);
                    }
                    echo PHP_EOL . "::PERSONAL VAULTS::" . PHP_EOL . PHP_EOL;
                    print_r(!empty($personalVaults) ? $personalVaults : array("NEGATIVE"));
                    if(!empty($factionData))
                    {
                        echo PHP_EOL . "::FACTION DATA::" . PHP_EOL . PHP_EOL;
                        print_r($factionData);
                    }
                }
            }
            catch(PDOException $e)
            {
                exit($e->getMessage());
            }
            // And then convert everything into a new format & populate the new database.
            $this->pdo->beginTransaction();
            try
            {
                $stmt = "INSERT INTO players (username,uuid,loaded) VALUES (?,?,0)";
                $do = $this->pdo->prepare($stmt);
                $do->execute([$this->user['username'], $this->user['uuid']]);
                $stmt = "SELECT * FROM players WHERE uuid = :uuid";
                $do = $this->pdo->prepare($stmt);
                $do->bindParam(":uuid", $this->user['uuid']);
                $do->execute();
                $playerData = $do->fetch(PDO::FETCH_ASSOC);
                if(!$playerData)
                {
                    exit("err:Fatal Error -- Player was not successfully inserted into new database.");
                }
                foreach($characters as $var)
                {
                    if(!empty($inventories) and array_key_exists($var['character_id'], $inventories))
                    {
                        $currentInv = $inventories[$var['character_id']];
                        $oldCharId = $var['character_id'];
                        $faction = '0';
                        $factionRank = '0';
                        $charJob = '0';
                        $charExp = '0';
                        $charJobLevel = '0';
                        if(!empty($factionData) and array_key_exists($var['character_id'], $factionData))
                        {
                            $faction = $factionData[$var['character_id']]['char_faction'];
                            $factionRank = $factionData[$var['character_id']]['char_faction_rank'];
                        }
                        if(!empty($characterJobs) and array_key_exists($var['character_id'], $characterJobs))
                        {
                            $charJob = $characterJobs[$var['character_id']]['job'];
                            $charExp = $characterJobs[$var['character_id']]['experience'];
                            $charJobLevel = $characterJobs[$var['character_id']]['level'];
                        }

                        $stmt = "INSERT INTO characters 
                        (owner,name,faction,description,fog_corruption,mana_corruption,dream_rot,main_crafter,m_craft_lvl,m_craft_exp)
                        VALUES
                        (?,?,?,default,?,?,?,?,?,?)
                        ";
                        $do = $this->pdo->prepare($stmt);
                        $charName = explode("=>", $var['titles'])[0];
                        if(debug)
                        {
                            echo PHP_EOL , PHP_EOL , "::INVENTORY IN LOOP ({$charName}, ID: {$oldCharId})::" , PHP_EOL , PHP_EOL;
                        }
                        $do->execute([
                            $playerData['id'],
                            $charName,
                            $faction,
                            $currentInv['fog_corruption'],
                            $currentInv['mana_corruption'],
                            $currentInv['dream_rot'],
                            $charJob,
                            $charJobLevel,
                            $charExp
                        ]);
                        // Verify insertion.
                        $stmt = "SELECT * FROM characters WHERE name = ?";
                        $do = $this->pdo->prepare($stmt);
                        $do->execute([$charName]);
                        $do = $do->fetch(PDO::FETCH_ASSOC);
                        $id = $do['id'];
                        $charName = $do['name'];

                        // Import inventory.
                        $stmt = "INSERT INTO main_inventory (char_id) VALUES (?)";
                        $do = $this->pdo->prepare($stmt);
                        $do->execute([$id]);
                        $stmt = "SELECT * FROM main_inventory WHERE char_id = ?";
                        $do = $this->pdo->prepare($stmt);
                        $do->execute([$id]);
                        $main_inventory = $do->fetch(PDO::FETCH_ASSOC);
                        if(!$main_inventory)
                        {
                            exit("err:Could not complete import. Query: " . $do->queryString);
                        }
                        $money = $currentInv['money'];
                        $tmp = array_slice($currentInv, 1, 9);
                        $currentInv = array();
                        foreach($tmp as $deets)
                        {
                            if($deets != "0")
                            {
                                $currentInv[] = $deets;
                            }
                        }
                        print_r($currentInv);
                        $bag = json_decode($main_inventory['bag'], true, 3);
                        print_r($bag);
                        $x = 1;
                        foreach($currentInv as $deets)
                        {
                            $deets = explode(":", $deets);
                            if(count($deets) < 2)
                            {
                                exit("err:Cannot import character due to inventory error." . PHP_EOL . $deets);
                            }
                            $bag[$x]['id'] = $deets[0];
                            $bag[$x]['amount'] = $deets[1];
                            ++$x;
                        }
                        $bag = json_encode($bag);
                        $stmt = "UPDATE main_inventory SET bag = ?, money = ? WHERE char_id = ?";
                        $do = $this->pdo->prepare($stmt);
                        $do->execute([$bag, $money, $id]);
                        $stmt = "SELECT bag FROM main_inventory WHERE char_id = ?";
                        $do = $this->pdo->prepare($stmt);
                        $do->execute([$id]);
                        $isMatch = $do->fetch(PDO::FETCH_ASSOC);
                        if($isMatch['bag'] !== $bag)
                        {
                            exit("err:Bag import failed at query " . $do->queryString);
                        }
                        else
                        {
                            if(debug)
                            {
                                echo PHP_EOL, PHP_EOL, "::::BAG IMPORT SUCCESS::::", PHP_EOL, PHP_EOL;
                            }
                        }
                        // Then migrate personal vault.
                        // For this, we must free memory.
                        if(debug)
                        {
                            echo PHP_EOL, $charName, " NEW ID: " , $id, PHP_EOL;
                            print_r($currentInv);
                            print_r($do);
                            print_r($main_inventory);
                            echo $bag, PHP_EOL, PHP_EOL , ":::::::::::::::::::", PHP_EOL, PHP_EOL;
                        }
                        unset($currentInv);
                        unset($main_inventory);
                        unset($bag);
                        $stmt = "INSERT INTO personal_vault (char_id,item_id,amount) VALUES (?,?,?)";
                        $do = $this->pdo->prepare($stmt);
                        if(array_key_exists($oldCharId, $personalVaults))
                        {
                            $v = $personalVaults[$oldCharId];
                            foreach($v as $deets)
                            {
                                $do->execute([$id, $deets['item_id'], $deets['amount']]);
                                if(debug)
                                {
                                    echo PHP_EOL, "INSERT {$deets['item_id']}, amount {$deets['amount']}", PHP_EOL;
                                }
                            }
                        }
                    }
                }
                if(debug)
                {
                    print_r($playerData);
                    echo PHP_EOL , PHP_EOL , "WOULD HAVE SUCCESSFULLY COMMITTED DATA", PHP_EOL, PHP_EOL;
                }
                $this->pdo->commit();
            }
            catch(PDOException $e)
            {
                $this->pdo->rollBack();
                exit("err:" . $e->getMessage());
            }
        }
    }

?>
