<?php

namespace NetherWorld\location;

use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\Server;
use pocketmine\level\Level;

class LocationData {
	private $userName;
	private $dataFolder;
	private $data;
	private $nowSpecialPrefix = null;
	private $specialPrefixList = [ ];
	public function __construct($userName, $dataFolder) {
		$userName = strtolower ( $userName );
		
		$this->userName = $userName;
		$this->dataFolder = $dataFolder . substr ( $userName, 0, 1 ) . "/";
		
		if (! file_exists ( $this->dataFolder ))
			@mkdir ( $this->dataFolder );
		
		$this->load ();
	}
	public function load() {
		$this->data = (new Config ( $this->dataFolder . $this->userName . ".json", Config::JSON, [ 
				"x" => 0,
				"y" => 0,
				"z" => 0,
				"pitch" => 0,
				"yaw" => 0,
				"levelName" => null 
		] ))->getAll ();
	}
	public function save($async = false) {
		$data = new Config ( $this->dataFolder . $this->userName . ".json", Config::JSON );
		$data->setAll ( $this->data );
		$data->save ( $async );
	}
	public function savePosition(Position $pos, $pitch = 0, $yaw = 0) {
		$this->data ["x"] = $pos->x;
		$this->data ["y"] = $pos->y;
		$this->data ["z"] = $pos->z;
		$this->data ["pitch"] = $pitch;
		$this->data ["yaw"] = $yaw;
		$this->data ["levelName"] = $pos->getLevel ()->getFolderName ();
	}
	public function getPosition() {
		$level = Server::getInstance ()->getLevelByName ( $this->data ["levelName"] );
		
		if (! $level instanceof Level)
			return Server::getInstance ()->getDefaultLevel ()->getSafeSpawn ();
		
		return new Position ( $this->data ["x"], $this->data ["y"], $this->data ["z"], $level );
	}
	public function getPitch() {
		return $this->data ["pitch"];
	}
	public function getYaw() {
		return $this->data ["yaw"];
	}
}

?>