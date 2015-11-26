<?php

/*
 * Copyright (C) 2015 Ashley Marando
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Marando\Kepler;

use \Marando\AstroDate\AstroDate;
use \Marando\AstroDate\Epoch;
use \Marando\JPLephem\DE\SSObj;
use \Marando\Kepler\Data\JPL\SmallBodyDB;
use \Marando\Kepler\Util\Util;

class Comet extends SmallBody {

  /**
   *
   * @var OrbitalElem
   */
  protected $orbitals;

  public function __construct() {

  }

  public static function find($name) {
    $comet           = new static();
    $comet->orbitals = SmallBodyDB::comet($name);
    return $comet;
  }

  protected function getPosition(AstroDate $date) {
    $this->orbitals->epoch = $date->toEpoch();

    $ε = Util::trueObli($date)->rad;
    $Ω = $this->orbitals->node->rad;
    $i = $this->orbitals->incl->rad;
    $r = $this->orbitals->r->au;
    $ω = $this->orbitals->argPeri->rad;
    $ν = $this->orbitals->trueAnomaly->rad;

    $F = cos($Ω);
    $G = sin($Ω) * cos($ε);
    $H = sin($Ω) * sin($ε);
    $P = -sin($Ω) * cos($i);
    $Q = cos($Ω) * cos($i) * cos($ε) - sin($i) * sin($ε);
    $R = cos($Ω) * cos($i) * sin($ε) + sin($i) * cos($ε);

    $A = atan2($F, $P);
    $B = atan2($G, $Q);
    $C = atan2($H, $R);
    $a = sqrt($F * $F + $P * $P);
    $b = sqrt($G * $G + $Q * $Q);
    $c = sqrt($H * $H + $R * $R);

    $x = $r * $a * sin($A + $ω + $ν);
    $y = $r * $b * sin($B + $ω + $ν);
    $z = $r * $c * sin($C + $ω + $ν);

    $this->orbitals;
    $comet = Util::pv2c([$x, $y, $z], $date);
    $sun   = Util::pvsun($this->getDE(), $date);

    // Return solar system barycentric position of comet
    return $sun->add($comet);
  }

  protected function getSSObj() {
    return null;
  }

  public function __toString() {
    return $this->orbitals->name;
  }

}
