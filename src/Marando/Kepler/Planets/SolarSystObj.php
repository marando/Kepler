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

use \Marando\Kepler\Ephemeris;
use \Marando\Units\Angle;
use \Exception;
use \InvalidArgumentException;
use \Marando\AstroCoord\Cartesian;
use \Marando\AstroCoord\Frame;
use \Marando\AstroCoord\Geo;
use \Marando\AstroDate\AstroDate;
use \Marando\AstroDate\TimeStandard;
use \Marando\JPLephem\DE\Reader;
use \Marando\Units\Distance;
use \Marando\Units\Time;
use \Marando\Units\Velocity;

/**
 * Represents an solar system object
 */
abstract class SolarSystObj {
  //----------------------------------------------------------------------------
  // Constructors
  //----------------------------------------------------------------------------

  /**
   * Creates a new instance with optional initial date
   * @param AstroDate $date
   */
  public function __construct(AstroDate $date = null) {
    // Set the date, and initialize a JPL DE reader to it
    $this->dates[0] = $date;
    $this->reader   = new Reader($date);
  }

  // // // Static

  /**
   * Creates a new instance with optional initial date
   * @param  AstroDate $date
   * @return static
   */
  public static function create(AstroDate $date = null) {
    return new static($date);
  }

  //----------------------------------------------------------------------------
  // Properties
  //----------------------------------------------------------------------------

  /**
   * Full list of dates for this instance
   * @var AstroDate[]
   */
  protected $dates = null;

  /**
   * Time interval step between dates of this instance, null denotes discrete
   * (non continuous) times were specified
   * @var Time
   */
  protected $dateStep = null;

  /**
   * Geographic observation location
   * @var Geo
   */
  protected $obsrv = null;

  /**
   * JPL DE file reader
   * @var Reader
   */
  protected $reader = null;

  //----------------------------------------------------------------------------
  // Functions
  //----------------------------------------------------------------------------

  /**
   * Sets a single date for the instance. The value may be either an AstroDate
   * instance (preferred), a number representing a Julian day count, or a
   * date/time string.
   *
   * @param  AstroDate|float|string $date
   * @return static
   */
  public function date($date) {
    // If array provided... parse all dates otherwise parse single date
    return $this->dates(is_array($date) ? $date : [$date]);
  }

  /**
   * Sets multiple non-continuous discrete values for the instance. Each value
   * may be either an AstroDate instance (preferred), a number representing a
   * Julian day count, or a date/time string.
   *
   * @param  AstroDate[]|float[]|string[] $dates
   * @return static
   */
  public function dates(array $dates) {
    // Nullify already set date information
    $this->dates = null;
    $this->step  = null;

    // Add each date
    foreach ($dates as $date)
      $this->dates[] = static::parseAstroDate($date);

    return $this;  // Return instance for method chaining
  }

  /**
   * Sets a range of dates for the instance. The dates are interpolated between
   * a starting and ending date using a specified time interval. Each date may
   * be either an AstroDate instance (preferred), a number representing a Julian
   * day count, or a date/time string. The time interval may be either a Time
   * instance or a string representation of the interval, ex. '1 day'.
   *
   * @param  AstroDate|float|string $date1
   * @param  AstroDate|float|string $dateN
   * @param  Time|string            $step
   *
   * @return static
   */
  public function dateRange($date1, $dateN, $step) {
    // Clear existing dates
    $this->dates = null;

    // Parse step time interval
    $this->dateStep = static::parseTime($step);

    // Parse starting and ending date and get JD
    $jd1 = static::parseAstroDate($date1)->jd;
    $jdN = static::parseAstroDate($dateN)->jd;

    // Iterate from start to end date using the step interval
    for ($jd = $jd1; $jd <= $jdN; $jd += $this->dateStep)
      $this->dates[] = AstroDate::jd($jd);  // Add each date

    return $this;  // Return instance for method chaining
  }

  /**
   * Sets an optional topographic geographic observation location for this
   * instance. Each parameter may be an angle representing latitude or
   * longitude (West -), or a string representation of the degree value with a
   * cardinal direction, ex: 45.04542 N or 34.11245 W
   *
   * @param  Angle|string $lat
   * @param  Angle|string $lon
   * @return static
   */
  public function topo($lat, $lon) {
    // Parse lat/lon and normalize to appropriate ranges
    $lat = static::parseGeoLatLon($lat)->norm(-90, 90);
    $lon = static::parseGeoLatLon($lon)->norm(-180, 180);

    // Set the observation location
    $this->obsrv = new Geo($lat, $lon);

    return $this;  // Return for method chaining
  }

  /**
   * Runs the ephemeris calculations for a target body for all dates and other
   * parameters specified within this instance
   * @param  static    $obj Target body
   * @return Ephemeris      Ephemeris result
   */
  public function observe(SolarSystObj $obj) {
    // Initialize blank ephemeris
    $ephem = new Ephemeris();

    // Define target and center
    $target = $obj->getSSObj();
    $center = $this->getSSObj();

    // Target physical diameter
    $tgtPhysDiam = $obj->getPhysicalDiameter();

    // Run for each requested date
    foreach ($this->dates as $date) {
      // Get date in TDB
      $dateTDB = $date->copy()->toTDB();
      $jdeTDB  = $dateTDB->jd;

      // Set the JDE of the reader
      $de = $this->reader->jde($jdeTDB);

      // Obtain true and apparent cartesian pv-vector
      $xyz   = static::pvToCartesian($de->position($target, $center), $date);
      $xyzLT = static::pvToCartesian($de->observe($target, $center), $date);

      // Astrometric ICRF/J2000.0 RA/Decl with topographic location
      $equatICRF        = $xyzLT->toEquat();
      $equatICRF->obsrv = $this->obsrv;

      // Airless apparent RA/Decl
      $equatApparent = $equatICRF->copy()->apparent();

      // Initialize ephemeris item
      $e = new Ephemeris();

      // Set date stuff...
      $e->dateUT   = $date->copy()->toUT1();
      $e->dateUTC  = $date->copy()->toUTC();
      $e->jdUT     = $e->dateUT->jd;
      $e->jdUTC    = $e->dateUTC->jd;
      $e->sidereal = $date->gast($this->obsrv->lon);

      // Cartesian vectors
      $e->xyzTrue   = $xyz;
      $e->xyzAstrom = $xyzLT;

      // Target-Observer true and apparent distance
      $e->distTrue = $xyz->r;
      $e->dist     = $xyzLT->r;

      // RA/Decl
      $e->radecAstrom   = $equatICRF;
      $e->radecApparent = $equatApparent;

      // Horizontal (Alt/Az)
      $e->altaz = $equatApparent->toHoriz();

      // Misc...
      $e->diameter = static::diam($tgtPhysDiam, $e->dist);

      // Insert the ephemeris item
      $ephem[] = $e;
    }

    // Return the full ephemeris
    return $ephem;
  }

  // // // Protected

  protected static function parseAstroDate($date) {
    // AstroDate instance
    if ($date instanceof AstroDate)
      return $date;

    // Try parsing Julian day count
    if (is_numeric($date))
      return AstroDate::jd($date);

    // Try parsing string date representaation
    if (is_string($date))
      return AstroDate::parse($date);
  }

  protected static function parseTime($time) {
    // Time instance
    if ($time instanceof Time)
      return $time;

    // Check if string has numeric then time span, if not throw exception
    if (!preg_match('/^([0-9]*\.*[0-9]*)\s*([a-zA-Z]*)$/', $time, $tokens))
      throw new Exception("Unable to parse time duration {$time}");

    // Get the numeric and time span
    $number = $tokens[1];
    $unit   = strtolower($tokens[2]);

    // Parse the time span
    switch ($unit) {
      case 'd':
      case 'day':
      case 'days':
        return Time::days($number);

      case 'h':
      case 'hour':
      case 'hours':
        return Time::hours($number);

      case 'm':
      case 'min':
      case 'minutes':
        return Time::min($number);

      case 's':
      case 'sec':
      case 'seconds':
        return Time::sec($number);
    }
  }

  protected static function parseGeoLatLon($latlon) {
    if ($latlon instanceof Angle)
      return $latlon;

    if (is_numeric($latlon))
      return Angle::deg($latlon);

    // TODO:: Parse like '27.65424 N'
  }

  protected static function pvToCartesian($pv, $date) {
    $frame = Frame::ICRF();
    $epoch = $date->toEpoch();
    $x     = Distance::au($pv[0]);
    $y     = Distance::au($pv[1]);
    $z     = Distance::au($pv[2]);
    $vx    = Velocity::aud($pv[3]);
    $vy    = Velocity::aud($pv[4]);
    $vz    = Velocity::aud($pv[5]);

    return new Cartesian($frame, $epoch, $x, $y, $z, $vx, $vy, $vz);
  }

  protected static function diam(Distance $diam, Distance $dist) {
    return Angle::arcsec(206265 * $diam->au / $dist->au);
  }

  // // // Abstract

  abstract protected function getSSObj();

  abstract protected function getPhysicalDiameter();
}

///////////
///////////
////////

abstract
        class __SolarSystObj {

  //
  // Constructors
  //

  public function __construct(AstroDate $date = null) {
    $this->dates[0] = $date;
    $this->reader   = new Reader($date);
  }

  // // // Static

  public static function create(AstroDate $dt = null) {

  }

  //
  // Properties
  //

  protected $dates = null;
  protected $step  = null;
  protected $topo  = null;
  protected $ephem = null;

  /**
   *
   * @var Reader
   */
  protected $reader = null;

  public function __get($name) {
    switch ($name) {
      case 'id':
        return $this->getJPLObj();
    }
  }

  //
  // Functions
  //

  public function date(AstroDate $dt) {
    $this->dates = null;
    $this->dates = [$dt];
    $this->step  = null;
  }

  public function dates(array $dt) {
    foreach ($dt as $d)
      if ($d instanceof AstroDate == false)
        throw new InvalidArgumentException('All $dt items must be of AstroDate type');

    $this->dates = null;
    $this->dates = $dt;
    $this->step  = null;
  }

  public function dateRange(AstroDate $dt1, AstroDate $dt2, Time $step) {
    $this->dates = null;
    $this->dates = [$dt1, $dt2];
    $this->step  = $step;
  }

  public function topo(Geo $topo) {
    $this->topo = $topo;
  }

  public function observe(SolarSystObj $obj) {
    $ephem = [];

    if ($this->step) {
      $jd1  = $this->dates[0]->copy()->toTDB()->jd;
      $jd2  = $this->dates[1]->copy()->toTDB()->jd;
      $step = $this->step->days;

      for ($jd = $jd1; $jd < $jd2; $jd += $step) {
        $pv = $this->reader->jde($jd)->observe($obj->id, $this->id, $lt);

        $frame = Frame::ICRF();
        $epoch = AstroDate::jd($jd, TimeStandard::TDB())->toEpoch();
        $x     = Distance::au($pv[0]);
        $y     = Distance::au($pv[1]);
        $z     = Distance::au($pv[2]);
        $vx    = Velocity::aud($pv[3]);
        $vy    = Velocity::aud($pv[4]);
        $vz    = Velocity::aud($pv[5]);

        $c         = new Cartesian($frame, $epoch, $x, $y, $z, $vx, $vy, $vz);
        $eq        = $c->toEquat();
        $eq->obsrv = $this->topo;
        $ephem[]   = $eq;
      }
    }
    else {

      foreach ($this->dates as $date) {
        $pv = $this->reader->jde($date->jd)->observe($obj->id, $this->id);


        $frame = Frame::ICRF();
        $epoch = $date->toEpoch();
        $x     = Distance::au($pv[0]);
        $y     = Distance::au($pv[1]);
        $z     = Distance::au($pv[2]);
        $vx    = Velocity::aud($pv[3]);
        $vy    = Velocity::aud($pv[4]);
        $vz    = Velocity::aud($pv[5]);

        $c         = new Cartesian($frame, $epoch, $x, $y, $z, $vx, $vy, $vz);
        $eq        = $c->toEquat();
        $eq->obsrv = $this->topo;
        $ephem[]   = $eq;
      }
    }

    $this->ephem = $ephem;
    return $ephem;
  }

  public function position(SolarSystObj $obj = null) {
    $ephem = [];

    if ($this->step) {
      if ($obj == null) {
        $jd1  = $this->dates[0]->copy()->toTDB()->jd;
        $jd2  = $this->dates[1]->copy()->toTDB()->jd;
        $step = $this->step->days;

        for ($jd = $jd1; $jd < $jd2; $jd += $step) {
          $pv = $this->reader->jde($jd)->position($this->id);

          $frame = Frame::ICRF();
          $epoch = AstroDate::jd($jd, TimeStandard::TDB())->toEpoch();
          $x     = Distance::au($pv[0]);
          $y     = Distance::au($pv[1]);
          $z     = Distance::au($pv[2]);
          $vx    = Velocity::aud($pv[3]);
          $vy    = Velocity::aud($pv[4]);
          $vz    = Velocity::aud($pv[5]);

          $ephem[] = new Cartesian($frame, $epoch, $x, $y, $z, $vx, $vy, $vz);
        }
      }
    }
    else {
      if ($obj == null) {
        foreach ($this->dates as $date) {
          $ephem[] = $this->reader->jde($date->jd)->position($this->id);
        }
      }
    }

    return $ephem;



    /*
      if ($obj == null) {
      if ($this->step) {
      $jd1  = $this->dates[0]->jd;
      $jd2  = $this->dates[1]->jd;
      $step = $this->step->days;

      $ephem = [];
      for ($jd = $jd1; $jd < $jd2; $jd += $step) {
      $ephem[] = $this->reader->jde($jd)->position();
      }
      }
      }
     *
     */
  }

  public function ephem() {

  }

  public function diam(SolarSystObj $obj) {

    if ($this->ephem == null)
      $this->observe($obj);

    $ephem = $this->ephem;

    $diams = [];
    foreach ($ephem as $eph) {
      $d       = $obj->getPhysicalDiameter()->au;
      $D       = $eph->apparent()->dist->au;
      $δ       = 206265 * $d / $D;
      $diams[] = \Marando\Units\Angle::arcsec($δ);

      /*
        $d       = $obj->getPhysicalDiameter()->au;
        $D       = $this->observe($obj)[0]->dist->au;
        $δ       = 206265 * $d / $D;
        $diams[] = \Marando\Units\Angle::arcsec($δ);
       *
       */
    }

    return $diams;
  }

  protected function listDates() {
    $dates = [];

    if ($this->step) {
      $jd1  = $this->dates[0]->copy()->toUTC()->jd;
      $jd2  = $this->dates[1]->copy()->toUTC()->jd;
      $step = $this->step->days;

      for ($jd = $jd1; $jd < $jd2; $jd += $step)
        $dates[] = AstroDate::jd($jd);
    }
    else {
      $dates[] = $this->dates;
    }

    return $dates;
  }

  // // // Abstract

  abstract protected function getJPLObj();

  protected
          function getPhysicalDiameter() {
    return Distance::mi(8000);
  }

}
