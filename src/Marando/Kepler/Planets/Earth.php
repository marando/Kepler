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
use \Marando\AstroCoord\Geo;
use \Marando\AstroDate\AstroDate;
use \Marando\JPLephem\DE\SSObj;
use \Marando\Units\Distance;

class Earth extends SolarSystObj {

  protected function getSSObj() {
    return SSObj::Earth();
  }

  protected function getJPLObj() {
    return SSObj::Earth();
  }

  protected function getPhysicalDiameter() {
    return Distance::km(12756);
  }

  public static function planets(AstroDate $date = null, $string = false) {
    // if string = false return as array otherwise print below


    $date = $date ? $date : AstroDate::now();

    $planets = [
        SSObj::Sun(),
        SSObj::Mercury(),
        SSObj::Venus(),
        SSObj::Moon(),
        SSObj::Mars(),
        SSObj::Jupiter(),
        SSObj::Saturn(),
        SSObj::Uranus(),
        SSObj::Neptune(),
        SSObj::Pluto(),
    ];

    $str    = <<<STR

=================================================
               PLANETARY EPHEMERIS
           {$date}
-------------------------------------------------
Planet  | Apparent RA  | Apparent Decl | Dist, AU
--------|--------------|---------------|---------

STR;
    $jdTDB  = $date->toTDB()->toJD();
    $reader = (new \Marando\JPLephem\DE\Reader())->jde($jdTDB);
    //$reader = new \Marando\JPLephem\DE\Reader(\Marando\JPLephem\DE\DE::DE430());
    //$reader->jde($jdTDB);
    foreach ($planets as $p) {

      $pv = $reader->observe($p, SSObj::Earth());

      $x = Distance::au($pv[0]);
      $y = Distance::au($pv[1]);
      $z = Distance::au($pv[2]);
      $c = new Cartesian(Frame::ICRF(), $date->toEpoch(), $x, $y, $z);

      $eq = $c->toEquat()->apparent();

      $p   = str_pad($p, 7, ' ', STR_PAD_RIGHT);
      $fmt = 'Rh Rm Rs.Ru | +Dd Dm Ds.Du';
      $d   = sprintf('% 8.5f', $eq->dist->au);
      $str .= "{$p} | {$eq->format($fmt)} | $d\n";
    }

    $str .= str_repeat('=', 49);
    return "$str\n";
  }




}
