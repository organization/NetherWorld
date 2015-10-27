<?php

namespace NetherWorld\task;

use pocketmine\scheduler\Task;
use NetherWorld\location\LocationLoader;

class AutoUnloadTask extends Task {
	protected $owner;
	public function __construct(LocationLoader $owner) {
		$this->owner = $owner;
	}
	public function onRun($currentTick) {
		$this->owner->unloadLocation();
	}
}
?>