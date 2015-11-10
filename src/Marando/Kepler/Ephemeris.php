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

namespace Marando\Kepler;

use \ArrayAccess;
use \Iterator;
use \Marando\AstroCoord\Cartesian;
use \Marando\AstroCoord\Equat;
use \Marando\AstroCoord\Horiz;
use \Marando\Units\Distance;
use \Marando\Units\Time;

class Ephemeris implements ArrayAccess, Iterator {

  public $dateUTC;
  public $dateUT;
  public $jdUTC;
  public $jdUT;

  public $target;
  public $center;
  /**
   * Geometric true cartesian position and velocity of the target
   * @var Cartesian
   */
  public $xyzTrue;

  /**
   * Astrometric cartesian position and velocity of the target (includes target
   * to observer light-time aberration)
   * @var Cartesian
   */
  public $xyzAstrom;

  /**
   * Geometric true target to observer distance
   * @var Distance
   */
  public $distTrue;

  /**
   * Astrometric (apparent) target to observer distance (includes target to
   * observer light-time aberration
   * @var Distance
   */
  public $dist;

  /**
   * ICRF/J2000.0 astrometric right ascension and declination of target center
   * adjusted for light-time
   * @var Equat
   */
  public $radecAstrom;

  /**
   * Airless apparent right ascension and declination of target center with
   * respect to the Earth true-equator and the meridian containing the Earth
   * true equinox of date. Adjusted for light-time, gravitational deflection of
   * light, stellar aberration, precession & nutation. Topographic if an
   * observation location was supplied.
   * @var Equat
   */
  public $radecApparent;

  /**
   * Airless apparent azimuth and elevation of target center. Adjusted for
   * light-time, the gravitational deflection of light, stellar aberration,
   * precession and nutation. Topographic if an observation location was
   * supplied.
   * @var Horiz
   */
  public $altaz;
  public $eclipAstrom;
  public $eclipApparent;

  /**
   * Local Apparent Sidereal Time. The angle measured westward in the body
   * true-equator of-date plane from the meridian containing the body-fixed
   * observer to the meridian containing the true Earth equinox (defined by
   * intersection of the true Earth equator of date with the ecliptic of date).
   * @var Time
   */
  public $sidereal;
  public $diameter;
  public $eclipHeliocentr;
  public $lightTime;
  public $galactic;
  public $solarTime;
  public $hourAngle;
  /////

  /**
   *
   * @var static
   */
  protected $items = [];

  public function offsetExists($offset) {
    return key_exists($offset, $this->items);
  }

  public function offsetGet($offset) {
    return isset($this->items[$offset]) ? $this->items[$offset] : null;
  }

  public function offsetSet($offset, $value) {
    if (is_null($offset))
      $this->items[]        = $value;
    else
      $this->items[$offset] = $value;
  }

  public function offsetUnset($offset) {
    unset($this->items[$offset]);
  }

  public function __toString() {
    $i   = new static();
    $str = '';

    foreach ($this->items as $i) {
      $str .= "\n"
              . "$i->dateUTC "
              . "$i->radecIRCS "
      //. "$i->radecApparent\t"
      //. "$i->diameter\t"
      //. "$i->lightTime";
      ;
    }

    return $str;
  }

  protected $position = 0;

  /**
   *
   * @return static
   */
  public function current() {
    return $this->items[$this->position];
  }

  public function key() {
    return $this->position;
  }

  public function next() {
    ++$this->position;
  }

  public function rewind() {
    $this->position = 0;
  }

  public function valid() {
    return isset($this->items[$this->position]);
  }

}
