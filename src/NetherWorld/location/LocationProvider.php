<?php

namespace NetherWorld\location;

use pocketmine\Server;
use pocketmine\Player;
use NetherWorld\NetherWorld;

class LocationProvider {
	
	/**
	 *
	 * @var LocationProvider
	 */
	private static $instance = null;
	/**
	 *
	 * @var NetherWorld
	 */
	private $plugin;
	/**
	 *
	 * @var LocationLoader
	 */
	private $loader;
	/**
	 *
	 * @var Server
	 */
	private $server;
	/**
	 *
	 * @var LocationProvider DB
	 */
	private $db;
	public function __construct(NetherWorld $plugin) {
		if (self::$instance == null)
			self::$instance = $this;
		
		$this->plugin = $plugin;
		$this->loader = $plugin->getLocationLoader ();
		$this->server = Server::getInstance ();
	}
	public function loadLocation($userName) {
		return $this->loader->loadLocation ( $userName );
	}
	public function unloadLocation($userName = null) {
		return $this->loader->unloadLocation ( $userName );
	}
	public function getLocation(Player $player) {
		return $this->loader->getLocation ( $player );
	}
	public function getLocationToName($name) {
		return $this->loader->getLocationToName ( $name );
	}
	public static function getInstance() {
		return static::$instance;
	}
}

?>