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
use \Marando\AstroCoord\Eclip;
use \Marando\AstroCoord\Equat;
use \Marando\AstroCoord\Frame;
use \Marando\AstroCoord\Geo;
use \Marando\AstroCoord\Horiz;
use \Marando\AstroDate\AstroDate;
use \Marando\AstroDate\Epoch;
use \Marando\IAU\IAU;
use \Marando\JPLephem\DE\Reader;
use \Marando\JPLephem\DE\SSObj;
use \Marando\Units\Angle;
use \Marando\Units\Distance;
use \Marando\Units\Time;

/**
 * @property Horiz     $altaz         Apparent altitude and azimuth of the
 *                                    target centr. Adjusted for light-time,
 *                                    gravitational deflection of light, stellar
 *                                    aberration, precession and nutation
 *
 * @property SSObj     $center        Center body
 *
 * @property AstroDate $date          Date of observation
 *
 * @property Angle     $diam          Angular width of the target body assuming
 *                                    full disk visibility
 *
 * @property Distance  $dist          Target to observer apparent distance (as
 *                                    affected by light-time)
 *
 * @property Distance  $distTrue      Target to observer true distance (not
 *                                    affected by light-time)
 *
 * @property Eclip     $eclip         J2000.0 geometric heliocentric ecliptic
 *                                    longitude and latitude of target center
 *
 * @property Time      $gast          Greenwich apparent sidereal time
 *
 * @property float     $illum
 *
 * @property Time      $last          Local apparent sidereal time
 *
 * @property Time      $lightTime     1-way light-time from target center to
 *                                    observer
 *
 * @property Geo       $location      Geographic observation location
 *
 * @property float     $phase
 *
 * @property Equat     $radec         J2000.0 astrometric right ascension and
 *                                    declination of target center. Adjusted
 *                                    for light-time.
 *
 * @property Equat     $radecApparent Airless apparent right ascension and
 *                                    declination of the target center with
 *                                    respect to the Earth true equator and
 *                                    equinox of the date. Adjusted for
 *                                    light-time, gravitational deflection of
 *                                    light, stellar aberration, precession
 *                                    and nutation.
 *
 * @property SSObj     $target        Target body
 *
 * @property Cartesian $xyz           Astrometric ICRF/J2000.0 cartesian target
 *                                    to center position and velocity. Adjusted
 *                                    for light-time
 *
 * @property Cartesian $xyzTrue       True ICRF/J2000.0 cartesian target to
 *                                    center position and velocity (not adjusted
 *                                    for light-time)
 */
class Ephemeris implements ArrayAccess, Iterator {

  public function __construct() {

  }

  public static function item(SSObj $target, SSObj $center, Cartesian $pvTrue,
          Cartesian $pvAstrom, Time $lightTime, Distance $absDiam,
          AstroDate $date, Geo $obsrv = null) {

    $item            = new static();
    $item->target    = $target;
    $item->center    = $center;
    $item->pvTrue    = $pvTrue;
    $item->pvAstrom  = $pvAstrom;
    $item->lightTime = $lightTime->setUnit('min');
    $item->absDiam   = $absDiam;
    $item->date      = $date;
    $item->obsrv     = $obsrv;

    return $item;
  }

  /**
   * True geometric center to target cartesian position/velocity vector
   * @var Cartesian
   */
  protected $pvTrue;

  /**
   * Astrometric center to target cartesian position/velocity vector (corrected
   * for light-time aberration
   * @var Cartesian
   */
  protected $pvAstrom;

  /**
   * Target to observer light time
   * @var Time
   */
  protected $lightTime;

  /**
   * Absolute diameter of target
   * @var Distance
   */
  protected $absDiam;

  /**
   * Date/time of the observation
   * @var AstroDate
   */
  protected $date;

  /**
   * Geographic observation location
   * @var Geo
   */
  protected $obsrv;

  /**
   *
   * @var SSObj
   */
  protected $target;

  /**
   *
   * @var SSObj
   */
  protected $center;

  /**
   *
   * @var Epoch
   */
  protected $epoch;

  /**
   *
   * @var array
   */
  protected $cache = [];

  public function __get($name) {
    switch ($name) {
      case "altaz":
        $radec        = $this->radec;
        $radec->obsrv = $this->obsrv;
        return $this->cache($name, $radec->toHoriz());

      case 'center':
        return $this->cache($name, $this->center);

      case 'date':
        return $this->cache($name, $this->date);

      case 'diam':
        return $this->cache($name, static::diam($this->absDiam, $this->dist));

      case 'dist':
        return $this->cache($name, $this->pvAstrom->r);

      case 'distTrue':
        return $this->cache($name, $this->pvAstrom->r);

      case 'eclip':
        return $this->cache($name, $this->eclipHelio());

      case 'gast':
        return $this->cache($name, $this->date->gast());

      case 'illum':
        return $this->cache($name, $this->illum());

      case 'last':
        return $this->cache($name, $this->date->gast($this->location->lon));

      case 'lightTime':
        return $this->cache($name, $this->lightTime);

      case 'location':
        return $this->cache($name, $this->obsrv);

      case 'phase':
        return $this->cache($name, $this->phase());

      case 'radec':
        return $this->cache($name, $this->pvAstrom->toEquat());

      case 'radecApparent':
        return $this->cache($name, $this->pvAstrom->toEquat()->apparent());

      case 'target':
        return $this->target;

      case 'xyz':
        return $this->cache($name, $this->pvAstrom);

      case 'xyzTrue':
        return $this->cache($name, $this->pvTrue);
    }
  }

  // Cache values on retrieval for speed
  protected function cache($key, $value) {
    if (key_exists($key, $this->cache))
      return $this->cache[$key];
    else
      return $this->cache[$key] = $value;
  }

  protected static function diam(Distance $absDiam, Distance $dist) {
    return Angle::arcsec(206265 * $absDiam->au / $dist->au);
  }

  protected function eclipHelio() {
    $de      = (new Reader())->jde($this->date->copy()->toTDB()->jd);
    $pvhelio = $de->position($this->target, SSObj::Sun());

    $e = $this->cache('epoch', $this->date->toEpoch());
    $x = Distance::au($pvhelio[0]);
    $y = Distance::au($pvhelio[1]);
    $z = Distance::au($pvhelio[2]);
    $c = new Cartesian(Frame::ICRF(), $e, $x, $y, $z);

    return $c->toEquat()->toEclip();
  }

  protected function phase() {
    // Target -> Sun distance
    $r = $this->eclip->dist->au;

    // Target -> Earth
    $Δ = $this->radecApparent->dist->au;

    // Earth -> Sun distance (R)
    $s   = microtime(true);
    $pvh = [];
    $pvb = [];
    IAU::Epv00($this->date->jd, 0, $pvh, $pvb);
    $pvh = $pvh[0];
    $R   = sqrt($pvh[0] * $pvh[0] + $pvh[1] * $pvh[1] + $pvh[2] * $pvh[2]);

    // Calculate target phase angle
    return Angle::rad(acos(($r ** 2 + $Δ ** 2 - $R ** 2) / (2 * $r * $Δ)));
  }

  protected function illum() {
    return round((1 + cos($this->phase->rad)) / 2 * 100, 3);
  }

  public function __toString() {
    $i   = new static();
    $str = "\n";

    foreach ($this->items as $i) {
      $y = $i->date->year;
      $m = $i->date->monthName(false);
      $d = sprintf("%02.0f", $i->date->day);
      $str .= "$y-$m-$d\t"
              . "{$i->radec}\n";
    }

    return $str;
  }

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

class __Ephemeris implements ArrayAccess, Iterator {

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
              . "JD $i->jdUT UT\t"
              . "$i->radecAstrom"
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
