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

namespace Marando\Kepler\Util;

use \Marando\AstroCoord\Cartesian;
use \Marando\AstroCoord\Frame;
use \Marando\AstroDate\AstroDate;
use \Marando\IAU\IAU;
use \Marando\JPLephem\DE\Reader;
use \Marando\JPLephem\DE\SSObj;
use \Marando\Units\Angle;
use \Marando\Units\Distance;

class Util {

  public static function pv2c(array $pv, AstroDate $dt) {
    $x = Distance::au($pv[0]);
    $y = Distance::au($pv[1]);
    $z = Distance::au($pv[2]);

    return $c = new Cartesian(Frame::ICRF(), $dt->toEpoch(), $x, $y, $z);
  }

  public static function pvsun(Reader $reader,          AstroDate $date) {
    $jd = $date->toTDB()->toJD();
    return static::pv2c($reader->jde($jd)->position(SSObj::Sun()), $date);
  }

  public static function trueObli(AstroDate $date) {
    $jdTT = $date->toTT()->toJD();

    IAU::Nut06a($jdTT, 0, $dpsi, $deps);
    $obli = IAU::Obl06($jdTT, 0) + $deps;

    return Angle::rad($obli);
  }

}
