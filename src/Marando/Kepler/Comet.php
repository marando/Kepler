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
use \Marando\Kepler\Data\JPL\SmallBodyDB;
use \Marando\Kepler\Util\Util;

class Comet extends SmallBody {

  public function __construct(Epoch $epoch, $q, $e, $i, $w, $node, $Tp, $name) {
    // store all of this not as orbital just in instance
  }

  public static function find($name) {
    // for testing... instead this shoud store the comet's orbitals
    $comet = SmallBodyDB::comet($name);
    return $comet;
  }

  protected function getPosition(AstroDate $date) {
    // get stored info and find position based on eccentricity

    return Util::pv2c([1, 2, 3], $date);
  }

  protected function getSSObj() {
    return null;
  }

}
