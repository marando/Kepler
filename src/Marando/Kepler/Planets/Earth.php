<?php

/*
 * Copyright (C) 2015 ashley
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

namespace Marando\Kepler\Planets;

use \Marando\AstroCoord\Geo;
use \Marando\AstroDate\AstroDate;
use \Marando\JPLephem\DE\SSObj;

class Earth extends SolarSystObj {

  protected $location;

  public static function topo(AstroDate $date, Geo $geo) {
    $topo           = new static();
    $topo->date     = $date;
    $topo->location = $geo;

    return $topo;
  }

  public function observe(SolarSystObj $target) {
    $pos       = parent::observe($target);
    $pos->topo = $this->topo;

    return $pos;
  }

  /*
    public function apparent(SolarSystObj $target) {
    if ($this->location)
    $app = parent::observe($target)->apparent($this->location);
    else
    $app = parent::observe($target)->apparent();

    return $app;
    }
   *
   */

  protected function getJPLObj() {
    return SSObj::Earth();
  }

}
