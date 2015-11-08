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

use \Marando\AstroCoord\Cartesian;
use \Marando\AstroCoord\Frame;
use \Marando\AstroDate\AstroDate;
use \Marando\JPLephem\DE\DE;
use \Marando\JPLephem\DE\Reader;
use \Marando\Units\Distance;
use \Marando\Units\Velocity;

abstract class SolarSystObj {

  /**
   *
   * @var AstroDate
   */
  protected $date;

  public static function at(AstroDate $date) {
    $obj       = new static();
    $obj->date = $date;

    return $obj;
  }

  public function position(SolarSystObj $target = null) {
    $de    = new Reader(DE::DE421());
    $jdTDB = $this->date->toTDB()->jd;

    $pv = [];
    if ($target)
      $pv = $de->jde($jdTDB)->position($target->id, $this->id);
    else
      $pv = $de->jde($jdTDB)->position($this->id);

    $frame = Frame::ICRF();
    $epoch = $this->date->toEpoch();
    $x     = Distance::au($pv[0]);
    $y     = Distance::au($pv[1]);
    $z     = Distance::au($pv[2]);
    $vx    = Velocity::aud($pv[3]);
    $vy    = Velocity::aud($pv[4]);
    $vz    = Velocity::aud($pv[5]);

    return new Cartesian($frame, $epoch, $x, $y, $z, $vx, $vy, $vz);
  }

  public function observe(SolarSystObj $target) {
    $de    = new Reader(DE::DE421());
    $jdTDB = $this->date->toTDB()->jd;

    $pv = $de->jde($jdTDB)->observe($target->id, $this->id);

    $frame = Frame::ICRF();
    $epoch = $this->date->toEpoch();
    $x     = Distance::au($pv[0]);
    $y     = Distance::au($pv[1]);
    $z     = Distance::au($pv[2]);
    $vx    = Velocity::aud($pv[3]);
    $vy    = Velocity::aud($pv[4]);
    $vz    = Velocity::aud($pv[5]);

    $c = new Cartesian($frame, $epoch, $x, $y, $z, $vx, $vy, $vz);
    return $c->toEquat();
  }

  public function __get($name) {
    switch ($name) {
      case 'id':
        return $this->getJPLObj();
    }
  }

  abstract protected function getJPLObj();
}
