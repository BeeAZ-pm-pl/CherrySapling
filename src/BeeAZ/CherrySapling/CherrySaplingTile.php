<?php

namespace BeeAZ\CherrySapling;

use pocketmine\block\tile\Spawnable;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\StringToItemParser;
use pocketmine\world\World;
use pocketmine\math\Vector3;

class CherrySaplingTile extends Spawnable {
    private int $age = 0;

    public function __construct(World $world, Vector3 $pos) {
        parent::__construct($world, $pos);
        CherrySapling::$tiles[spl_object_id($this)] = $this;
    }

    public function close(): void {
        unset(CherrySapling::$tiles[spl_object_id($this)]);
        parent::close();
    }

    public function getAge(): int {
        return $this->age;
    }

    public function setAge(int $age): void {
        $this->age = $age;
    }

    public function readSaveData(CompoundTag $nbt): void {
        $this->age = $nbt->getInt("CherryAge", 0);
    }

    protected function writeSaveData(CompoundTag $nbt): void {
        $nbt->setInt("CherryAge", $this->age);
    }

    protected function addAdditionalSpawnData(CompoundTag $nbt): void {}

    public function tick(): void {
        $this->age++;
        if ($this->age >= mt_rand(60, 120)) {
            $this->grow();
        }
    }

    public function grow(): void {
        $world = $this->getPosition()->getWorld();
        $pos = $this->getPosition();
        
        $world->setBlock($pos, VanillaBlocks::AIR());
        
        $logItem = StringToItemParser::getInstance()->parse("cherry_log");
        $log = $logItem !== null ? $logItem->getBlock() : VanillaBlocks::OAK_LOG();
        
        $leavesItem = StringToItemParser::getInstance()->parse("cherry_leaves");
        $leaves = $leavesItem !== null ? $leavesItem->getBlock() : VanillaBlocks::OAK_LEAVES();
        
        if (method_exists($leaves, "setPersistent")) {
            $leaves = clone $leaves->setPersistent(true);
        }
        
        $trunkHeight = mt_rand(6, 9);
        $logs = [];
        $leafCenters = [];

        for ($y = 0; $y < $trunkHeight; $y++) {
            $logs[] = $pos->add(0, $y, 0);
        }
        $leafCenters[] = $pos->add(0, $trunkHeight, 0);

        $numBranches = mt_rand(3, 5);
        $dirs = [
            [1, 0], [-1, 0], [0, 1], [0, -1],
            [1, 1], [-1, 1], [1, -1], [-1, -1]
        ];
        shuffle($dirs);

        for ($i = 0; $i < $numBranches; $i++) {
            $dx = $dirs[$i][0];
            $dz = $dirs[$i][1];
            
            $branchLen = mt_rand(3, 5);
            $cx = 0;
            $cz = 0;
            $cy = mt_rand($trunkHeight - 4, $trunkHeight - 2); 
            
            for ($j = 0; $j < $branchLen; $j++) {
                $cx += $dx;
                $cz += $dz;
                if ($j % 2 === 0) $cy++;
                $logs[] = $pos->add($cx, $cy, $cz);
            }
            $leafCenters[] = $pos->add($cx, $cy, $cz);
        }

        foreach ($logs as $logPos) {
            $world->setBlock($logPos, $log);
        }

        foreach ($leafCenters as $center) {
            for ($x = -2; $x <= 2; $x++) {
                for ($y = -1; $y <= 1; $y++) {
                    for ($z = -2; $z <= 2; $z++) {
                        $dist = abs($x) + abs($y) + abs($z);
                        if ($dist <= 3) {
                            if ($dist === 3 && mt_rand(1, 3) !== 1) continue;
                            if (mt_rand(1, 100) > 90) continue;

                            $lp = $center->add($x, $y, $z);
                            if ($world->getBlock($lp)->hasSameTypeId(VanillaBlocks::AIR())) {
                                $world->setBlock($lp, $leaves);
                            }
                        }
                    }
                }
            }
        }
        
        $this->close();
    }
}