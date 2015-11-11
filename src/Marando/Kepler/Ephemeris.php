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
 * Represents an collection of ephemeris results
 *
 * @property Horiz     $altaz         Apparent altitude and azimuth of the target centr. Adjusted for lighime, gravitational deflection of light, stellar aberration, precession and nutation
 * @property SSObj     $center        Center body
 * @property AstroDate $date          Date of observation
 * @property Angle     $diam          Angular width of the target body assuming full disk visibility
 * @property Distance  $dist          Target to observer apparent distance (as affected by light-time)
 * @property Distance  $distTrue      Target to observer true distance (not affected by light-time)
 * @property Eclip     $eclip         J2000.0 geometric heliocentric ecliptic longitude and latitude of target center
 * @property Time      $gast          Greenwich apparent sidereal time
 * @property float     $illum         Fraction of target illuminated by Sun as seen by observer
 * @property Time      $last          Local apparent sidereal time
 * @property Time      $lightTime     1-way light-time from target center to observer
 * @property Geo       $location      Geographic observation location
 * @property float     $phase         Target-observer-Sun phase angle
 * @property Equat     $radec         J2000.0 astrometric right ascension and declination of target center. Adjusted for light-time.
 * @property Equat     $radecApparent Airless apparent right ascension and declination of the target center with respect to the Earth true equator and equinox of the date. Adjusted for light-time, gravitational deflection of light, stellar aberration, precession and nutation.
 * @property SSObj     $target        Target body
 * @property Cartesian $xyz           Astrometric ICRF/J2000.0 cartesian target to center position and velocity. Adjusted for light-time
 * @property Cartesian $xyzTrue       True ICRF/J2000.0 cartesian target to center position and velocity (not adjusted for light-time)
 */
class Ephemeris implements ArrayAccess, Iterator {
  //----------------------------------------------------------------------------
  // Constructors
  //----------------------------------------------------------------------------

  /**
   * Creates a new Ephemeris instance
   */
  public function __construct() {

  }

  // // // Static

  /**
   * Creates a new ephemeris item. The provided parameters are used to
   * automatically calculate additional observational quantities
   *
   * @param  SSObj     $target    Target object
   * @param  SSObj     $center    Center object
   * @param  Cartesian $pvTrue    Geometric (true) target -> observer cartesian
   * @param  Cartesian $pvAstrom  Astrometric target -> observer cartesian
   * @param  Time      $lightTime Light-time
   * @param  Distance  $absDiam   Absolute target diameter
   * @param  AstroDate $date      Observational date and time
   * @param  Geo       $obsrv     Geographic Observation location
   * @return static
   */
  public static function item(SSObj $target, SSObj $center, Cartesian $pvTrue,
          Cartesian $pvAstrom, Time $lightTime, Distance $absDiam,
          AstroDate $date, Geo $obsrv = null) {

    // Create new instance
    $item = new static();

    // Set properties
    $item->target    = $target;
    $item->center    = $center;
    $item->pvTrue    = $pvTrue;
    $item->pvAstrom  = $pvAstrom;
    $item->lightTime = $lightTime->setUnit('min');  // Minutes preferred
    $item->absDiam   = $absDiam;
    $item->date      = $date;
    $item->obsrv     = $obsrv;

    return $item;
  }

  //----------------------------------------------------------------------------
  // Properties
  //----------------------------------------------------------------------------
  //
  // <editor-fold desc="Properties" defaultstate="collapsed">
  /**
   * Array access container
   * @var array
   */
  protected $items = [];

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
   * Target JPL DE item
   * @var SSObj
   */
  protected $target;

  /**
   * Center JPL DE item
   * @var SSObj
   */
  protected $center;

  /**
   * Observation epoch
   * @var Epoch
   */
  protected $obsEpoch;

  /**
   *
   * @var array
   */
  protected $cache = [];

  public function __get($name) {
    switch ($name) {
      case "altaz":
        $radec        = $this->radec;  // Set topo location and find horiz
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

  // </editor-fold>
  //
  //----------------------------------------------------------------------------
  // Functions
  //----------------------------------------------------------------------------

  /**
   * Returns cached properties by key name, or sets them if they have not yet
   * been cached
   *
   * @param  string $key
   * @param  mixed  $value
   * @return mixed
   */
  protected function cache($key, $value) {
    if (key_exists($key, $this->cache))
      return $this->cache[$key];           // return cached
    else
      return $this->cache[$key] = $value;  // cache, then return
  }

  /**
   * Calculates the geometric (true) heliocentric ecliptic position of this
   * instance's target
   * @return Eclip
   */
  protected function eclipHelio() {
    // Get heliocentric position & velocity vector
    $de      = (new Reader())->jde($this->date->copy()->toTDB()->jd);
    $pvhelio = $de->position($this->target, SSObj::Sun());

    // raw pv-vector -> cartesian
    $e = $this->cache('epoch', $this->date->toEpoch());
    $x = Distance::au($pvhelio[0]);
    $y = Distance::au($pvhelio[1]);
    $z = Distance::au($pvhelio[2]);
    $c = new Cartesian(Frame::ICRF(), $e, $x, $y, $z);

    // xyz -> equat -> eclip
    return $c->toEquat()->toEclip();
  }

  /**
   * Calculates the phase angle of this instance's target (angle T-S-O)
   * @return Angle
   */
  protected function phase() {
    // Target -> Sun distance
    $r = $this->eclip->dist->au;

    // Target -> Earth distance
    $Δ = $this->radecApparent->dist->au;

    // Earth -> Sun distance
    $pvh = [];
    $pvb = [];
    IAU::Epv00($this->date->jd, 0, $pvh, $pvb);
    $pvh = $pvh[0];
    $R   = sqrt($pvh[0] * $pvh[0] + $pvh[1] * $pvh[1] + $pvh[2] * $pvh[2]);

    // Calculate target's phase angle
    return Angle::rad(acos(($r ** 2 + $Δ ** 2 - $R ** 2) / (2 * $r * $Δ)));
  }

  /**
   * Calculates the illuminated disk fraction of this instance's target as seen
   * by the observer
   * @return float
   */
  protected function illum() {
    return round((1 + cos($this->phase->rad)) / 2 * 100, 3);
  }

  // // // Static

  /**
   * Calculates an angular diameter from an absolute diameter and observational
   * distance
   *
   * @param  Distance $absDiam Absolute diameter
   * @param  Distance $dist    Observational distance
   * @return Angle
   */
  protected static function diam(Distance $absDiam, Distance $dist) {
    return Angle::arcsec(206265 * $absDiam->au / $dist->au);
  }

  // // // Overrides

  /**
   * Represents this instance as a string
   * @return string
   */
  public function __toString() {
    $e   = new static();
    $str = "\n";
    foreach ($this->items as $e) {
      $y = $e->date->year;
      $m = $e->date->monthName(false);
      $d = sprintf("%02.0f", $e->date->day);
      $h = sprintf("%02.0f", $e->date->hour);
      $i = sprintf("%02.0f", $e->date->min);
      $s = sprintf("%02.0f", $e->date->sec);

      $str .= "$e->date  {$e->radecApparent}\n";
    }

    return $str;
  }

  // // // Interfaces

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
