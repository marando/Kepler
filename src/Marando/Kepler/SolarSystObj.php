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

use \Exception;
use \Marando\AstroCoord\Cartesian;
use \Marando\AstroCoord\Geo;
use \Marando\AstroDate\AstroDate;
use \Marando\JPLephem\DE\Reader;
use \Marando\JPLephem\DE\SSObj;
use \Marando\Kepler\Util\Util;
use \Marando\Units\Time;

/**
 * @property OrbitalElem $orbitalElem Description
 */
abstract class SolarSystObj {

  //----------------------------------------------------------------------------
  // CONSTRUCTORS
  //----------------------------------------------------------------------------

  public function __construct() {

  }

  //----------------------------------------------------------------------------
  // PROPERTIES
  //----------------------------------------------------------------------------

  /**
   * JPL DE reader
   * @var Reader
   */
  protected $de;

  /**
   * Full array of dates for this instance
   * @var AstroDate[]
   */
  protected $dates = null;

  /**
   * Time interval between dates of this instance
   * @var Time
   */
  protected $dateStep = null;

  /**
   * Earth topographic observation location
   * @var Geo
   */
  protected $topo = null;

  public function __get($name) {
    switch ($name) {
      case 'orbitalElem':
        return $this->getOrbitals();
    }
  }

  //----------------------------------------------------------------------------
  // FUNCTIONS
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
    return $this->dates(is_array($date) ? $date : [$date]);
  }

  /**
   * Sets multiple discrete values for the instance. Each value may be either an
   * AstroDate instance (preferred), a number representing a Julian day count,
   * or a date/time string.
   *
   * @param  AstroDate[]|float[]|string[] $dates
   * @return static
   */
  public function dates(array $dates) {
    $dateArray = [];

    foreach ($dates as $d)
      $dateArray[] = Util::parseAstroDate($d);

    $this->dates    = $dateArray;
    $this->dateStep = null;

    return $this;
  }

  public function dateRange($date1, $dateN, $step) {
    $jd1 = Util::parseAstroDate($date1)->toJD();
    $jdN = Util::parseAstroDate($dateN)->toJD();
    $tz  = $date1 instanceof AstroDate ? $date1->timezone : 'UTC';

    $this->dates    = null;
    $this->dateStep = Util::parseTime($step);

    for ($jd = $jd1; $jd <= $jdN; $jd += $this->dateStep->days)
      $this->dates[] = AstroDate::jd($jd)->setTimezone($tz);

    return $this;
  }

  public function observe(SolarSystObj $target) {
    if (!$this->dates)
      throw new Exception('No dates');

    foreach ($this->dates as $dt) {
      $this->calcXYZ($dt, $target, $this, $xyzTrue, $xyzAstr, $lightTime);

      $tName = $target->getSSObj() ? : $target->orbitalElem->name;
      $cName = $this->getSSObj() ? : $this->orbitalElem->name;


      echo "\n\n" . $tName;
      echo "\n\n" . $cName;
      echo "\n\n" . $xyzTrue->r;
      echo "\n\n" . $xyzAstr;
      echo "\n\n" . $lightTime;
    }
  }

  // // // Protected

  protected function getDE() {
    if ($this->de)
      return $this->de;

    return $this->de = new Reader();
  }

  /**
   * Finds the true position and astrometric (light-time correctred) position
   * and light-time for a target solar system body with respect to a defined
   * center
   *
   * @param AstroDate    $dt        Date of observation
   * @param SolarSystObj $target    Target body
   * @param SolarSystObj $center    Center body
   * @param Cartesian    $xyzTrue   True cartesian position
   * @param Cartesian    $xyzAstr   Astrometric cartesian position
   * @param Time         $lightTime Light-time interval
   */
  protected function calcXYZ(AstroDate $dt, SolarSystObj $target,
          SolarSystObj $center, &$xyzTrue, &$xyzAstr, &$lightTime) {

    // Planet -> Planet
    if ($target instanceof Planet && $center instanceof Planet) {
      $jdTDB  = $dt->toTDB()->toJD();
      $de     = $this->getDE()->jde($jdTDB);
      $target = $target->getSSObj();
      $center = $center->getSSObj();

      $xyzTrue = Util::pv2c($de->position($target, $center), $dt);
      $xyzAstr = Util::pv2c($de->observe($target, $center, $lightTime), $dt);
    }

    // One body is not a planet
    else {
      $xyzTarget = $target->getPosition($dt);
      $xyzCenter = $center->getPosition($dt);
      $xyzTrue   = $xyzTarget->subtract($xyzCenter);

      // Find astrometric position & light-time via iteration
      $τ0 = 0;
      $Δτ = PHP_INT_MAX;
      while ($Δτ > 0) {
        $xyzTarget = $target->getPosition($dt);
        $xyzCenter = $center->getPosition($dt);
        $xyzAstr   = $xyzTarget->subtract($xyzCenter);

        $τ  = 0.0057755183 * $xyzTrue->r->au;
        $Δτ = $τ - $τ0;
        $τ0 = $τ;
      }

      $lightTime = Time::days($τ);
    }

    $lightTime->setUnit('min');
  }

  protected static function orbitalXYZ($dt, $t, $c, &$xyzTrue, &$xyzAstr, &$lt) {
    $xyzTarget = $t->getPosition($dt);
    $xyzCenter = $c->getPosition($dt);
    $xyzTrue   = $xyzTarget->subtract($xyzCenter);

    // Find astrometric position & light-time via iteration
    $τ0 = 0;
    $Δτ = PHP_INT_MAX;
    while ($Δτ > 0) {
      $xyzTarget = $t->getPosition($dt);
      $xyzCenter = $c->getPosition($dt);
      $xyzAstr   = $xyzTarget->subtract($xyzCenter);

      $τ  = 0.0057755183 * $xyzTrue->r->au;
      $Δτ = $τ - $τ0;
      $τ0 = $τ;
    }

    $lt = Time::days($τ)->setUnit('min');
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

  abstract protected function getOrbitals();
}
