<?php

namespace NetherWorld\location;

use pocketmine\Player;
use pocketmine\Server;
use NetherWorld\NetherWorld;
use NetherWorld\task\AutoUnloadTask;

class LocationLoader {
	private static $instance = null;
	/**
	 *
	 * @var Users prefix data
	 */
	private $users = [ ];
	/**
	 *
	 * @var NetherWorld
	 */
	private $plugin;
	/**
	 *
	 * @var Server
	 */
	private $server;
	public function __construct(NetherWorld $plugin) {
		if (self::$instance == null)
			self::$instance = $this;
		
		$this->server = Server::getInstance ();
		$this->plugin = $plugin;
		
		$this->server->getScheduler ()->scheduleRepeatingTask ( new AutoUnloadTask ( $this ), 12000 );
	}
	/**
	 * Create a default setting
	 *
	 * @param string $userName        	
	 */
	public function loadLocation($userName) {
		$userName = strtolower ( $userName );
		$alpha = substr ( $userName, 0, 1 );
		
		if (isset ( $this->users [$userName] ))
			return $this->users [$userName];
		
		if (! file_exists ( $this->plugin->getDataFolder () . "player/" ))
			@mkdir ( $this->plugin->getDataFolder () . "player/" );
		
		return $this->users [$userName] = new LocationData ( $userName, $this->plugin->getDataFolder () . "player/" );
	}
	public function unloadLocation($userName = null) {
		if ($userName === null) {
			foreach ( $this->users as $userName => $locationData ) {
				if ($this->users [$userName] instanceof LocationData)
					$this->users [$userName]->save ( true );
				unset ( $this->users [$userName] );
			}
			return true;
		}
		
		$userName = strtolower ( $userName );
		if (! isset ( $this->users [$userName] ))
			return false;
		if ($this->users [$userName] instanceof LocationData) {
			$this->users [$userName]->save ( true );
		}
		unset ( $this->users [$userName] );
		return true;
	}
	/**
	 * Get Location Data
	 *
	 * @param Player $player        	
	 * @return LocationData
	 */
	public function getLocation(Player $player) {
		$userName = strtolower ( $player->getName () );
		if (! isset ( $this->users [$userName] ))
			$this->loadLocation ( $userName );
		return $this->users [$userName];
	}
	/**
	 * Get Location Data
	 *
	 * @param string $player        	
	 * @return LocationData
	 */
	public function getLocationToName($name) {
		$userName = strtolower ( $name );
		if (! isset ( $this->users [$userName] ))
			$this->loadLocation ( $userName );
		return $this->users [$userName];
	}
	public function save($async = false) {
		foreach ( $this->users as $userName => $locationData )
			if ($locationData instanceof LocationData)
				$locationData->save ( $async );
	}
	/**
	 *
	 * @return AreaLoader
	 */
	public static function getInstance() {
		return static::$instance;
	}
}

?>