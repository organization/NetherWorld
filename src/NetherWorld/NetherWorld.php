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

class NetherWorld extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->createHell ();
	}
	public function createHell() {
		$generator = Generator::getGenerator ( "nether" );
		$this->getServer ()->generateLevel ( "nether", null, $generator );
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