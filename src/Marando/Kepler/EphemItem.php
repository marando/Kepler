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

use \Marando\AstroCoord\Cartesian;
use \Marando\AstroCoord\Equat;
use \Marando\AstroDate\AstroDate;
use \Marando\Kepler\Planets\Earth;
use \Marando\Units\Time;

/**
 * @property Horiz     $altaz      Apparent altitude and azimuth of the target centr. Adjusted for lighime, gravitational deflection of light, stellar aberration, precession and nutation
 * @property string    $centerName Center body name
 * @property Geo       $centerTopo Geographic observation location (Earth only)
 * @property AstroDate $date       Date of observation
 * @property Angle     $diam       Angular width of the target body assuming full disk visibility
 * @property Distance  $distAppar  Target to observer apparent distance (as affected by light-time)
 * @property Distance  $distTrue   Target to observer true distance (not affected by light-time)
 * @property Eclip     $eclipHelio J2000.0 geometric heliocentric ecliptic longitude and latitude of target center
 * @property flost     $illum      Fraction of target illuminated by Sun as seen by observer
 * @property Time      $lightTime  One-way target center to observer light-time
 * @property float     $phase      Target-observer-Sun phase angle
 * @property Equat     $radecAppar Airless apparent right ascension and declination of the target center with respect to the Earth true equator and equinox of the date. Adjusted for light-time, gravitational deflection of light, stellar aberration, precession and nutation.
 * @property Equat     $radecJ2000 J2000.0 astrometric right ascension and declination of target center. Adjusted for light-time.
 * @property Time      $sidereal   Local apparent sidereal time
 * @property string    $targetName Target body name
 * @property Cartesian $xyzAstr    Astrometric ICRF/J2000.0 cartesian target to center position and velocity. Adjusted for light-time
 * @property Cartesian $xyzTrue    True ICRF/J2000.0 cartesian target to center position and velocity (not adjusted for light-time)
 */
class EphemItem {
  //----------------------------------------------------------------------------
  // Constructors
  //----------------------------------------------------------------------------

  /**
   * Creates a new ephemeris item
   *
   * @param AstroDate    $date      Date of observation
   * @param SolarSystObj $target    Target body
   * @param SolarSystObj $center    Center body
   * @param Cartesian    $xyzTrue   True cartesian position of target
   * @param Cartesian    $xyzAstr   Astrometric cartesian position of target
   * @param Time         $lightTime Target to center light travel time
   */
  public function __construct(AstroDate $date, SolarSystObj $target,
          SolarSystObj $center, Cartesian $xyzTrue, Cartesian $xyzAstr,
          Time $lightTime) {

    $this->date       = $date;
    $this->targetName = $target->name;
    $this->centerName = $center->name;
    $this->centerTopo = $center instanceof Earth ? $center->topo : null;
    $this->xyzTrue    = $xyzTrue;
    $this->xyzAstr    = $xyzAstr;
    $this->trueDiam   = $target->trueDiam;
  }

  // // // Static

  /**
   * Creates a new ephemeris item
   *
   * @param  AstroDate    $date      Date of observation
   * @param  SolarSystObj $target    Target body
   * @param  SolarSystObj $center    Center body
   * @param  Cartesian    $xyzTrue   True cartesian position of target
   * @param  Cartesian    $xyzAstr   Astrometric cartesian position of target
   * @param  Time         $lightTime Target to center light travel time
   * @return static
   */
  public static function create(AstroDate $date, SolarSystObj $target,
          SolarSystObj $center, Cartesian $xyzTrue, Cartesian $xyzAstr,
          Time $lightTime) {

    return new static($date, $target, $center, $xyzTrue, $xyzAstr, $lightTime);
  }

  //----------------------------------------------------------------------------
  // Properties
  //----------------------------------------------------------------------------

  /**
   * Date of observation
   * @var AstroDate
   */
  protected $date;

  /**
   * Target body name
   * @var string
   */
  protected $targetName;

  /**
   * Center body name
   * @var string
   */
  protected $centerName;

  /**
   * True cartesian position of target
   * @var Cartesian
   */
  protected $xyzTrue;

  /**
   * Astrometric cartesian position of target
   * @var Cartesian
   */
  protected $xyzAstr;

  /**
   * Target to center light travel time
   * @var Time
   */
  protected $lightTime;

  /**
   * Geographic observation location of center
   * @var Geo
   */
  protected $centerTopo;

  /**
   * True diameter of target
   * @var Distance
   */
  protected $trueDiam;

  /**
   * Property cache for this instance
   * @var array
   */
  protected $cache = [];

  public function __get($name) {
    switch ($name) {
      case 'centerName':
      case 'centerTopo':
      case 'date':
      case 'targetName':
      case 'xyzAstr':
      case 'xyzTrue':
        return $this->{$name};

      case 'altaz':
        return $this->cache($name, $this->getAltAz());

      case 'distAppar':
        return $this->cache($name, $this->radecAppar->dist->round(7));

      case 'distTrue':
        return $this->cache($name, $this->xyzTrue->r->round(7));

      case 'radecAppar':
        return $this->cache($name, $this->radecJ2000->apparent());

      case 'radecJ2000':
        return $this->cache($name, $this->xyzAstr->toEquat());
    }
  }

  //----------------------------------------------------------------------------
  // Functions
  //----------------------------------------------------------------------------

  /**
   * Returns cached properties or sets them if they have not yet been cached
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

  protected function getAltAz() {
    $radec = $this->radecAppar->copy();

    if ($this->centerTopo)
      $radec->topo = $this->centerTopo;
    else
      return 'no topographic location provided';

    return $radec->toHoriz();
  }

}
