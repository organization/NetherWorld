<?php

namespace NetherWorld;

use pocketmine\scheduler\PluginTask;

class MoveCheckTask extends PluginTask {
	public function __construct($owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		$this->owner->checkInsidePortal ();
	}
}

?>