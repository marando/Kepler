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
use \Marando\JPLephem\DE\Reader;
use \Marando\JPLephem\DE\SSObj;

abstract class SolarSystObj {

  //----------------------------------------------------------------------------
  // CONSTRUCTORS
  //----------------------------------------------------------------------------

  public function __construct() {

  }

  //----------------------------------------------------------------------------
  // PROPERTIES
  //----------------------------------------------------------------------------

  protected $topo;
  protected $de;
  protected $dates    = null;
  protected $dateStep = null;

  //----------------------------------------------------------------------------
  // FUNCTIONS
  //----------------------------------------------------------------------------

  public function date($date) {

  }

  public function dates(array $dates) {

  }

  public function dateRange($date1, $dateN, $step) {

  }

  public function observe(SolarSystObj $target) {
    $date = AstroDate::now();

    if ($this instanceof Planet && $target instanceof Planet) {
      // position entirely from de
      $xyzTrue;
      $xyzAstr;
      echo "\n\n" . $p = Util\Util::pv2c($this->getDE()->jde($date->toJD())->position($target->getSSObj()),
              $date);
    }
    else {
      echo "\n\n" . $p = $target->getPosition($date)->subtract($this->getPosition($date));
      echo "\n\n" . $p->toEquat();
    }
  }

  // // // Protected

  protected function getDE() {
    if ($this->de)
      return $this->de;

    return $this->de = new Reader();
  }

  // // // Abstract

  /**
   * @return SSObj SSObj representing this instance
   */
  abstract protected function getSSObj();

  /**
   * @return Cartesian Solar system barycentric position of this instance at the
   *                   provided date/time
   */
  abstract protected function getPosition(AstroDate $date);
}
