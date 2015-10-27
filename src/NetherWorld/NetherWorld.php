<?php

namespace NetherWorld;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\level\generator\Generator;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\Player;
use pocketmine\math\Math;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerKickEvent;
use NetherWorld\location\LocationLoader;
use NetherWorld\location\LocationProvider;
use pocketmine\network\protocol\StartGamePacket;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;

class NetherWorld extends PluginBase implements Listener {
	/**
	 *
	 * @var LocationLoader
	 */
	private $locationLoader;
	/**
	 *
	 * @var LocationProvider
	 */
	private $locationProvider;
	public function onEnable() {
		$this->locationLoader = new LocationLoader ( $this );
		$this->locationProvider = new LocationProvider ( $this );
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new MoveCheckTask ( $this ), 60 );
		$this->createHell ();
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function getLocationLoader() {
		return $this->locationLoader;
	}
	public function getLocationProvider() {
		return $this->locationProvider;
	}
	public function onPlayerQuitEvent(PlayerQuitEvent $event) {
		$this->locationLoader->unloadLocation ( $event->getPlayer ()->getName () );
	}
	public function onPlayerKickEvent(PlayerKickEvent $event) {
		$this->locationLoader->unloadLocation ( $event->getPlayer ()->getName () );
	}
	public function createHell() {
		$generator = Generator::getGenerator ( "nether" );
		$bool = $this->getServer ()->generateLevel ( "nether", null, $generator );
		
		if (! $this->getServer ()->getLevelByName ( "nether" ) instanceof Level)
			$this->getServer ()->loadLevel ( "nether" );
		
		if (! $bool) {
			$level = $this->getServer ()->getLevelByName ( "nether" );
			if ($level instanceof Level) {
				$spawn = $level->getSafeSpawn ();
				$level->generateChunk ( $spawn->x, $spawn->z );
				
				$x = $spawn->x;
				$y = $spawn->y;
				$z = $spawn->z;
				
				$hellDoorData = $this->locationProvider->getLocationToName ( "@hellLocation" );
				$hellDoorData->savePosition ( new Position ( $x, $y + 2, $z, $level ) );
				
				$z -= 2;
				
				// DOOR CREATE
				$doorLength = 6;
				$doorHeight = 10;
				$portalBlock = new Block ( 90 );
				$vector = new Vector3 ( $x, $y, $z );
				
				$centerX = $x + ($doorLength / 2);
				
				for($dx = 0; $dx <= ($doorLength - 1); $dx ++) {
					$level->setBlock ( $vector->setComponents ( $centerX + $dx, $y, $z ), $portalBlock );
					$level->setBlock ( $vector->setComponents ( $centerX + $dx, $y + $doorHeight, $z ), $portalBlock );
				}
				for($dy = 0; $dy <= ($doorHeight - 1); $dy ++) {
					$level->setBlock ( $vector->setComponents ( $centerX - ($doorLength / 2), $y + $dy, $z ), $portalBlock );
					$level->setBlock ( $vector->setComponents ( $centerX + ($doorLength / 2), $y + $dy, $z ), $portalBlock );
				}
				
				$startX = $centerX - ($doorLength / 2);
				$startY = $y;
				$startZ = $z;
				$endX = $centerX + ($doorLength / 2);
				$endY = $y + $doorHeight;
				$endZ = $z;
				
				if ($startX > $endX) {
					$backup = $endX;
					$endX = $startX;
					$startX = $backup;
				}
				if ($startY > $endY) {
					$backup = $endY;
					$endY = $startY;
					$startY = $backup;
				}
				if ($startZ > $endZ) {
					$backup = $endZ;
					$endZ = $startZ;
					$startZ = $backup;
				}
				
				$startY ++;
				$endY = $endY - 2;
				
				if ($startZ == $endZ) {
					$startX ++;
					$endX --;
				} else {
					$startZ ++;
					$endZ --;
				}
				
				for($x = $startX; $x <= $endX; $x ++)
					for($y = $startY; $y <= $endY; $y ++)
						for($z = $startZ; $z <= $endZ; $z ++)
							$level->setBlock ( $vector->setComponents ( $x, $y, $z ), $portalBlock );
			}
		}
	}
	public function onPlayerInteractEvent(PlayerInteractEvent $event) {
		if ($event->getItem ()->getId () == Item::FLINT_AND_STEEL and $event->getFace () == 1) {
			if ($event->getBlock () instanceof Block and $event->getBlock ()->getId () == Block::OBSIDIAN) {
				$twoPos = $this->canActivate ( $event->getBlock ()->getLevel (), $event->getBlock () );
				if ($twoPos !== false) {
					$this->setPortal ( $event->getBlock ()->getLevel (), $twoPos );
					$event->setCancelled ();
				}
			}
		}
	}
	public function checkInsidePortal() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			if ($this->isInsidePortal ( $player )) {
				if ($player->getLevel ()->getName () != "nether") {
					// MOVE TO HELL
					$locationData = $this->locationProvider->getLocation ( $player );
					$pos = $player->getPosition ();
					$pos = new Position ( $pos->x + 2, $pos->y + 2, $pos->z + 2, $pos->getLevel () );
					$locationData->savePosition ( $pos, $player->pitch, $player->yaw );
					
					$hellDoorData = $this->locationProvider->getLocationToName ( "@hellLocation" );
					$player->teleport ( $hellDoorData->getPosition (), 0, 0 );
					
					$pk = new ChangeDimensionPacket ();
					$pk->eid = 0;
					$pk->dimensionId = 1;
					// $this->sendChangeDimension ( $player, 1 );
					$player->dataPacket ( $pk );
					
					$player->chunk = null;
					$player->checkNetwork ();
					// $this->sendChangeDimension ( $player, - 1 );
				} else {
					// OR MOVE TO NORMAL WORLD
					$locationData = $this->locationProvider->getLocation ( $player );
					$player->teleport ( $locationData->getPosition (), $locationData->getYaw (), $locationData->getPitch () );
				}
			}
		}
	}
	public function onPlayerJoinEvent(PlayerJoinEvent $event) {
		// $pk = new ChangeDimensionPacket ();
		// $pk->eid = 0;
		// $pk->dimensionId = - 1;
		// $event->getPlayer ()->dataPacket ( $pk );
		// $this->sendChangeDimension ( $event->getPlayer (), 1 );
	}
	public function checkSend(DataPacketSendEvent $event) {
		// if ($event->getPacket () instanceof StartGamePacket) {
		// $event->getPacket ()->dimension = 1;
		// }
	}
	public function checkReceive(DataPacketReceiveEvent $event) {
		// echo "pid: " . $event->getPacket ()->pid () . " 0x" . dechex ( $event->getPacket ()->pid () ) . "\n";
	}
	public function sendChangeDimension(Player $player, $dimension = 0) {
		echo "sendChangeDimension\n";
		$pk = new StartGamePacket ();
		$pk->seed = - 1;
		$pk->dimension = $dimension;
		$pk->x = $player->x;
		$pk->y = $player->y;
		$pk->z = $player->z;
		$spawnPosition = $player->getSpawn ();
		$pk->spawnX = ( int ) $spawnPosition->x;
		$pk->spawnY = ( int ) $spawnPosition->y;
		$pk->spawnZ = ( int ) $spawnPosition->z;
		$pk->generator = 1; // 0 old, 1 infinite, 2 flat
		$pk->gamemode = $player->gamemode & 0x01;
		$pk->eid = 0;
		$player->dataPacket ( $pk );
		$player->sendSettings ();
	}
	public function isInsidePortal(Player $player) {
		$block = $player->getLevel ()->getBlock ( $player->temporalVector->setComponents ( Math::floorFloat ( $player->x ), Math::floorFloat ( $y = ($player->y + $player->getEyeHeight ()) ), Math::floorFloat ( $player->z ) ) );
		return $block->getId () == 90;
	}
	public function onBlockBreakEvent(BlockBreakEvent $event) {
		if ($event->getBlock ()->getId () == Block::OBSIDIAN or $event->getBlock ()->getId () == 90)
			$this->breakHellDoor ( $event->getBlock () );
	}
	public function breakHellDoor(Block $block) {
		$result = $this->getHellDoorBlocks ( $block, [ 
				"nestingDepth" => 0 
		] );
		$nestingDepth = 0;
		foreach ( $result as $pos => $bool ) {
			$nestingDepth ++;
			if ($nestingDepth >= 20)
				break;
			$pos = explode ( ":", $pos );
			if (isset ( $pos [2] ))
				$block->getLevel ()->setBlock ( new Vector3 ( $pos [0], $pos [1], $pos [2] ), Block::get ( Block::AIR ) );
		}
	}
	public function getHellDoorBlocks(Block $block, $data) {
		$data ["nestingDepth"] ++;
		if ($data ["nestingDepth"] >= 20)
			return $data;
		$sides = [ 
				Vector3::SIDE_EAST,
				Vector3::SIDE_WEST,
				Vector3::SIDE_NORTH,
				Vector3::SIDE_SOUTH,
				Vector3::SIDE_UP,
				Vector3::SIDE_DOWN 
		];
		$blockPos = "{$block->x}:{$block->y}:{$block->z}";
		if (! isset ( $data [$blockPos] ))
			$data [$blockPos] = true;
		foreach ( $sides as $side ) {
			if ($data ["nestingDepth"] >= 20)
				break;
			$sideBlock = $block->getSide ( $side );
			$sideBlockPos = "{$sideBlock->x}:{$sideBlock->y}:{$sideBlock->z}";
			if (isset ( $data [$sideBlockPos] ))
				continue;
			$id = $sideBlock->getId ();
			if ($id == 90) {
				$data [$sideBlockPos] = true;
				$returns = $this->getHellDoorBlocks ( $sideBlock, $data );
				if ($returns ["nestingDepth"] >= 20)
					break;
				foreach ( $returns as $returnPos => $bool )
					if (! isset ( $data [$returnPos] ))
						$data [$returnPos] = true;
			}
		}
		return $data;
	}
	public function canActivate(Level $level, Position $pos) {
		// +-x y +-z
		$minLength = 4;
		$minHeight = 5;
		$loopLimit = 27;
		
		$x = $pos->x;
		$y = $pos->y;
		$z = $pos->z;
		
		// INITIAL
		$nowLoop = 0;
		$firstPos = null;
		$secondPos = null;
		$height = 0;
		$initailSuccess = false;
		// DOWN LENGTH CHECK
		for(;;) {
			$x --;
			if ($nowLoop >= $loopLimit)
				break;
			if ($level->getBlockIdAt ( $x, $y, $z ) != Block::OBSIDIAN)
				break;
			if ($level->getBlockIdAt ( $x, $y + 1, $z ) != Block::AIR)
				break;
			if ($level->getBlockIdAt ( $x - 1, $y + 1, $z ) == Block::OBSIDIAN)
				$initailSuccess = true;
			$nowLoop ++;
		}
		if (! $initailSuccess) {
			$nowLoop = 0;
			
			$x = $pos->x;
			$y = $pos->y;
			$z = $pos->z;
			
			for(;;) {
				$z --;
				if ($nowLoop >= $loopLimit)
					break;
				if ($level->getBlockIdAt ( $x, $y, $z ) != Block::OBSIDIAN)
					break;
				if ($level->getBlockIdAt ( $x, $y + 1, $z ) != Block::AIR)
					break;
				if ($level->getBlockIdAt ( $x, $y + 1, $z - 1 ) == Block::OBSIDIAN)
					$initailSuccess = true;
				$nowLoop ++;
			}
			if (! $initailSuccess)
				return false;
			
			$firstPos = new Vector3 ( $x, $y, $z );
			$z += $nowLoop;
			for(;;) {
				$z ++;
				if ($nowLoop >= $loopLimit)
					break;
				if ($level->getBlockIdAt ( $x, $y, $z ) != Block::OBSIDIAN)
					break;
				if ($level->getBlockIdAt ( $x, $y + 1, $z ) != Block::AIR)
					break;
				if ($level->getBlockIdAt ( $x, $y + 1, $z + 1 ) == Block::OBSIDIAN)
					$initailSuccess = true;
				$nowLoop ++;
			}
			if (! $initailSuccess)
				return false;
			$secondPos = new Vector3 ( $x, $y, $z );
		} else {
			if ($secondPos == null) {
				$firstPos = new Vector3 ( $x, $y, $z );
				$x += $nowLoop;
				
				for(;;) {
					$x ++;
					if ($nowLoop >= $loopLimit)
						break;
					if ($level->getBlockIdAt ( $x, $y, $z ) != Block::OBSIDIAN)
						break;
					if ($level->getBlockIdAt ( $x, $y + 1, $z ) != Block::AIR)
						break;
					if ($level->getBlockIdAt ( $x + 1, $y + 1, $z ) == Block::OBSIDIAN)
						$initailSuccess = true;
					$nowLoop ++;
				}
				
				if (! $initailSuccess)
					return false;
				$secondPos = new Vector3 ( $x, $y, $z );
			}
		}
		// HEIGHT CHECK
		if ($firstPos->x == $secondPos->x) {
			// z 축으로 배치됨
			$diff = abs ( $firstPos->z - $secondPos->z );
			if ($diff < $minLength)
				return false;
			$fheight = 0;
			$fx = $firstPos->x;
			$fy = $firstPos->y;
			$fz = $firstPos->z;
			
			for(;;) {
				if ($level->getBlockIdAt ( $fx, $fy + $fheight, $fz ) != Block::OBSIDIAN)
					break;
				if ($fheight > $loopLimit)
					return false;
				$fheight ++;
			}
			$height = $fy + $fheight;
			$sheight = 0;
			$sx = $secondPos->x;
			$sy = $secondPos->y;
			$sz = $secondPos->z;
			
			for(;;) {
				$sheight ++;
				if ($level->getBlockIdAt ( $sx, $sy + $sheight, $sz ) != Block::OBSIDIAN)
					break;
				if ($sheight > $loopLimit)
					return false;
			}
			if ($fheight !== $sheight)
				return false;
		} else {
			// x 축으로 배치됨
			$diff = abs ( $firstPos->x - $secondPos->x );
			if ($diff < $minLength)
				return false;
			$fheight = 0;
			$fx = $firstPos->x - 1;
			$fy = $firstPos->y;
			$fz = $firstPos->z;
			
			for(;;) {
				$fheight ++;
				if ($level->getBlockIdAt ( $fx, $fy + $fheight, $fz ) != Block::OBSIDIAN)
					break;
				if ($fheight > $loopLimit)
					return false;
			}
			$sheight = 0;
			$sx = $secondPos->x + 1;
			$sy = $secondPos->y;
			$sz = $secondPos->z;
			
			for(;;) {
				$sheight ++;
				if ($level->getBlockIdAt ( $sx, $sy + $sheight, $sz ) != Block::OBSIDIAN)
					break;
				if ($sheight > $loopLimit)
					return false;
			}
			if ($fheight !== $sheight)
				return false;
			$height = $fheight;
		}
		
		if ($height < $minHeight) {
			return false;
		}
		
		$returnFirstPos = clone $firstPos;
		$returnSecondPos = clone $secondPos->setComponents ( $secondPos->x, $height, $secondPos->z );
		
		$x = $secondPos->x;
		$y = $secondPos->y;
		$z = $secondPos->z;
		$firstPos = null;
		$secondPos = null;
		$initailSuccess = false;
		
		// UP LENGTH CHECK
		for(;;) {
			$x --;
			if ($nowLoop >= $loopLimit)
				break;
			if ($level->getBlockIdAt ( $x, $y, $z ) != Block::OBSIDIAN)
				break;
			if ($level->getBlockIdAt ( $x, $y + 1, $z ) != Block::AIR)
				break;
			if ($level->getBlockIdAt ( $x - 1, $y + 1, $z ) == Block::OBSIDIAN)
				$initailSuccess = true;
			$nowLoop ++;
		}
		
		if (! $initailSuccess) {
			$nowLoop = 0;
			
			$x = $pos->x;
			$y = $pos->y;
			$z = $pos->z;
			
			for(;;) {
				$z --;
				if ($nowLoop >= $loopLimit)
					break;
				if ($level->getBlockIdAt ( $x, $y, $z ) != Block::OBSIDIAN)
					break;
				if ($level->getBlockIdAt ( $x, $y + 1, $z ) != Block::AIR)
					break;
				if ($level->getBlockIdAt ( $x, $y + 1, $z - 1 ) == Block::OBSIDIAN)
					$initailSuccess = true;
				$nowLoop ++;
			}
			if (! $initailSuccess)
				return false;
			$firstPos = new Vector3 ( $x, $y, $z );
			$z += $nowLoop;
			
			for(;;) {
				$z ++;
				if ($nowLoop >= $loopLimit)
					break;
				if ($level->getBlockIdAt ( $x, $y, $z ) != Block::OBSIDIAN)
					break;
				if ($level->getBlockIdAt ( $x, $y + 1, $z ) != Block::AIR)
					break;
				if ($level->getBlockIdAt ( $x, $y + 1, $z + 1 ) == Block::OBSIDIAN)
					$initailSuccess = true;
				$nowLoop ++;
			}
			if (! $initailSuccess)
				return false;
			$secondPos = new Vector3 ( $x, $y, $z );
		} else {
			if ($secondPos == null) {
				$firstPos = new Vector3 ( $x, $y, $z );
				$x += $nowLoop;
				
				for(;;) {
					$x ++;
					if ($nowLoop >= $loopLimit)
						break;
					if ($level->getBlockIdAt ( $x, $y, $z ) != Block::OBSIDIAN)
						break;
					if ($level->getBlockIdAt ( $x, $y + 1, $z ) != Block::AIR)
						break;
					if ($level->getBlockIdAt ( $x + 1, $y + 1, $z ) == Block::OBSIDIAN)
						$initailSuccess = true;
					$nowLoop ++;
				}
				if (! $initailSuccess)
					return false;
				$secondPos = new Vector3 ( $x, $y, $z );
			}
		}
		return [ 
				$returnFirstPos,
				$returnSecondPos 
		];
	}
	public function setPortal(Level $level, $twoPos) {
		if (! $twoPos [0] instanceof Vector3)
			return;
		if (! $twoPos [1] instanceof Vector3)
			return;
		
		$startX = $twoPos [0]->x;
		$startY = $twoPos [0]->y;
		$startZ = $twoPos [0]->z;
		$endX = $twoPos [1]->x;
		$endY = $twoPos [1]->y;
		$endZ = $twoPos [1]->z;
		
		if ($startX > $endX) {
			$backup = $endX;
			$endX = $startX;
			$startX = $backup;
		}
		if ($startY > $endY) {
			$backup = $endY;
			$endY = $startY;
			$startY = $backup;
		}
		if ($startZ > $endZ) {
			$backup = $endZ;
			$endZ = $startZ;
			$startZ = $backup;
		}
		$startY ++;
		$endY = $endY - 2;
		
		if ($startZ == $endZ) {
			$startX ++;
			$endX --;
		} else {
			$startZ ++;
			$endZ --;
		}
		
		$portalBlock = new Block ( 90 );
		$vector = new Vector3 ( 0, 0, 0 );
		
		for($x = $startX; $x <= $endX; $x ++)
			for($y = $startY; $y <= $endY; $y ++)
				for($z = $startZ; $z <= $endZ; $z ++)
					$level->setBlock ( $vector->setComponents ( $x, $y, $z ), $portalBlock );
	}
}

?>