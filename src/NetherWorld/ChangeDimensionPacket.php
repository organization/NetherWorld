<?php

namespace NetherWorld;

use pocketmine\network\protocol\DataPacket;
use pocketmine\utils\Binary;

class ChangeDimensionPacket extends DataPacket {
	const CHANGE_DIMENSION_PACKET = 0xc1;
	const NETWORK_ID = self::CHANGE_DIMENSION_PACKET;
	public $eid;
	public $dimensionId;
	public function decode() {
		$this->dimensionId = (\PHP_INT_SIZE === 8 ? \unpack ( "N", $this->get ( 4 ) ) [1] << 32 >> 32 : \unpack ( "N", $this->get ( 4 ) ) [1]);
	}
	public function encode() {
		$this->buffer = \chr ( self::NETWORK_ID );
		$this->offset = 0;
		// $this->buffer .= \pack ( "NN", $this->eid >> 32, $this->eid & 0xFFFFFFFF );
		// $this->buffer .= Binary::writeLong ( $this->eid );
		// $this->buffer .= \chr ( $this->dimensionId );
		$this->buffer .= \pack ( "N", $this->dimensionId );
	}
}
