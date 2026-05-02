<?php

namespace BeeAZ\CherrySapling;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\TileFactory;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\StringToItemParser;
use pocketmine\item\ItemTypeIds;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\particle\BoneMealParticle;
use pocketmine\world\particle\HappyVillagerParticle;

class CherrySapling extends PluginBase implements Listener {
    public static array $tiles = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        $cherry_sapling = new CherrySaplingBlock(new BlockIdentifier(BlockTypeIds::CHERRY_SAPLING, CherrySaplingTile::class), 'Cherry Sapling', new BlockTypeInfo(BlockBreakInfo::instant()));
        
        TileFactory::getInstance()->register(CherrySaplingTile::class, ['Cherry Sapling']);
        RuntimeBlockStateRegistry::getInstance()->register($cherry_sapling);
        StringToItemParser::getInstance()->registerBlock('Cherry_Sapling', fn () => clone $cherry_sapling);
        
        GlobalBlockStateHandlers::getDeserializer()->map(
            BlockTypeNames::CHERRY_SAPLING,
            fn (BlockStateReader $reader): CherrySaplingBlock => (clone $cherry_sapling)
                ->setAgeBit($reader->readBool(BlockStateNames::AGE_BIT))
        );
        
        GlobalBlockStateHandlers::getSerializer()->map(
            $cherry_sapling,
            fn (CherrySaplingBlock $cherry_sapling) => BlockStateWriter::create(BlockTypeNames::CHERRY_SAPLING)
                ->writeBool(BlockStateNames::AGE_BIT, $cherry_sapling->isAgeBit())
        );
        
        GlobalItemDataHandlers::getDeserializer()->map(BlockTypeNames::CHERRY_SAPLING, fn () => $cherry_sapling->asItem());
        GlobalItemDataHandlers::getSerializer()->map($cherry_sapling->asItem(), fn () => new SavedItemData(BlockTypeNames::CHERRY_SAPLING));
        CreativeInventory::getInstance()->add($cherry_sapling->asItem());

        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getLoadedChunks() as $chunkHash => $chunk) {
                foreach ($chunk->getTiles() as $tile) {
                    if ($tile instanceof CherrySaplingTile) {
                        self::$tiles[spl_object_id($tile)] = $tile;
                    }
                }
            }
        }

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            foreach (self::$tiles as $id => $tile) {
                if ($tile->isClosed()) {
                    unset(self::$tiles[$id]);
                    continue;
                }
                $tile->tick();
            }
        }), 20);
    }

    public function onChunkLoad(ChunkLoadEvent $event): void {
        foreach ($event->getChunk()->getTiles() as $tile) {
            if ($tile instanceof CherrySaplingTile) {
                self::$tiles[spl_object_id($tile)] = $tile;
            }
        }
    }

    public function onPlace(BlockPlaceEvent $event) {
        if ($event->isCancelled()) return;
        $blockAgainst = $event->getBlockAgainst();
        $blockTransaction = $event->getTransaction()->getBlocks();
        $block = null;
        foreach ($blockTransaction as [$x, $y, $z, $b]) {
            $block = $b;
        }
        if ($block !== null && $block->getTypeId() === BlockTypeIds::CHERRY_SAPLING) {
            if ($blockAgainst->getTypeId() == BlockTypeIds::DIRT || $blockAgainst->getTypeId() == BlockTypeIds::GRASS) {
                if ($tile = $block->getPosition()->getWorld()->getTile($block->getPosition())) {
                    $block->getPosition()->getWorld()->removeTile($tile);
                }
                $tile = new CherrySaplingTile($block->getPosition()->getWorld(), $block->getPosition());
                $tile->setAge(1);
                $block->getPosition()->getWorld()->addTile($tile);
            } else {
                $event->cancel();
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $action = $event->getAction();
        if ($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            $item = $event->getItem();
            $block = $event->getBlock();
            if ($block !== null && $block->getTypeId() === BlockTypeIds::CHERRY_SAPLING) {
                if ($item->getTypeId() === ItemTypeIds::BONE_MEAL) {
                    $tile = $block->getPosition()->getWorld()->getTile($block->getPosition());
                    if ($tile instanceof CherrySaplingTile) {
                        $event->cancel();
                        $item->pop();
                        $event->getPlayer()->getInventory()->setItemInHand($item);
                        $block->getPosition()->getWorld()->addParticle($block->getPosition()->add(0.5, 0.5, 0.5), new HappyVillagerParticle());
                        $tile->setAge($tile->getAge() + mt_rand(15, 30));
                        if ($tile->getAge() >= 60) {
                            $tile->grow();
                        }
                    }
                }
            }
        }
    }
}