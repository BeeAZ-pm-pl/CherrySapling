<?php

namespace BeeAZ\CherrySapling;

use pocketmine\block\Flowable;

class CherrySaplingBlock extends Flowable {
    private bool $ageBit = false;

    public function isAgeBit(): bool {
        return $this->ageBit;
    }

    public function setAgeBit(bool $ageBit): self {
        $this->ageBit = $ageBit;
        return $this;
    }
}