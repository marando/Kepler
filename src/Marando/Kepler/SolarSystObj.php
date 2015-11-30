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
use \Marando\AstroCoord\Equat;
use \Marando\AstroCoord\Geo;
use \Marando\AstroDate\AstroDate;
use \Marando\Interp\Interp3;
use \Marando\Interp\Interp5;
use \Marando\Interp\Lagrange;
use \Marando\JPLephem\DE\Reader;
use \Marando\JPLephem\DE\SSObj;
use \Marando\Kepler\Util\Util;
use \Marando\Units\Distance;
use \Marando\Units\Time;

/**
 * @property OrbitalElem $orbitalElem Description
 * @property string $name
 * @property Distance $trueDiam
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

      case 'trueDiam':
        return $this->getTrueDiam();

      case 'name':
        return $this->getName();
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
    return $this->dates($date);
  }

  /**
   * Sets multiple discrete values for the instance. Each value may be either an
   * AstroDate instance (preferred), a number representing a Julian day count,
   * or a date/time string.
   *
   * @param  AstroDate[]|float[]|string[] $dates
   * @return static
   */
  public function dates(...$dates) {
    $dateArray = [];

    foreach ($dates as $d)
      $dateArray[] = Util::parseAstroDate($d);



    usort($dateArray,
            function($a, $b) {
      return strcmp($a->toJD(), $b->toJD());
    });

    $this->dates    = $dateArray;
    $this->dateStep = null;

    return $this;
  }

  public function dateRange($date1, $dateN, $step) {
    $dt1 = Util::parseAstroDate($date1);
    $jd1 = $dt1->toJD();
    $jdN = Util::parseAstroDate($dateN)->toJD();
    $tz  = $date1 instanceof AstroDate ? $date1->timezone : 'UTC';

    $this->dates    = null;
    $this->dateStep = Util::parseTime($step);

    for ($jd = $jd1; $jd <= $jdN; $jd += $this->dateStep->days) {
      $date = AstroDate::jd($jd);

      // Fix to use original timezone
      if ($dt1->timezone) {
        $date->setTimezone($tz);
        $offset = Time::hours($dt1->timezone->offset($jd));
        $date->sub($offset);
      }

      $this->dates[] = $date;
    }

    return $this;
  }

  /**
   *
   * @param SolarSystObj $target
   * @return EphemItem
   * @throws Exception
   */
  public function observe(SolarSystObj $target, $interp = false) {
    if (!$this->dates)
      throw new Exception('No dates');

    if ($interp) {

    }

    $ephem = new Ephemeris();
    foreach ($this->dates as $dt) {
      $this->calcXYZ($dt->copy(), $target, $this, $xyzTrue, $xyzAstr, $lt);
      $ephem[] = new EphemItem($dt, $target, $this, $xyzTrue, $xyzAstr, $lt);
    }

    return $ephem;
  }

  // // // Protected

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

    $jdTDB = $dt->toTDB()->toJD();

    // Planet -> Planet
    if ($target instanceof Planet && $center instanceof Planet) {
      $de     = Util::de()->jde($jdTDB);
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
      $τ0 = 0.0057755183 * $xyzTrue->r->au;
      $Δτ = PHP_INT_MAX;
      while ($Δτ > 0) {
        $dt0       = AstroDate::jd($jdTDB - $τ0);
        $xyzTarget = $target->getPosition($dt0);
        $xyzCenter = $center->getPosition($dt0);
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

  public function ca(SolarSystObj $obj, AstroDate &$date = null,
          Distance &$dist = null) {

    // Find min and max date of this instance
    $jd1 = $this->dates[0]->toJD();
    $jdN = $this->dates[0]->toJD();
    foreach ($this->dates as $d) {
      $jdd = $d->toJD();
      $jdN = $d->toJD() > $jdN ? $jdd : $jdN;
      $jd1 = $d->toJD() < $jd1 ? $jdd : $jd1;
    }

    $n  = 5;
    $ΔK = 1e-9;
    $x  = 0;
    $y  = 0;
    $r  = [];

    for ($i = 0; $i < 18; $i++) {
      $step = ($jdN - $jd1) / $n;
      $r    = [];

      for ($jd = $jd1; $jd < $jdN; $jd += $step) {
        if ($this instanceof Planet && $obj instanceof Planet) {
          $de  = Util::de()->jde($jd);
          $pv  = $de->position($obj->getSSObj(), $this->getSSObj());
          $r[] = sqrt($pv[0] * $pv[0] + $pv[1] * $pv[1] + $pv[2] * $pv[2]);
        }

        if (count($r) == $n)
          break;
      }

      if ($i == 0)
        $minx = $jd1 + array_search(min($r), $r) * $step;

      $i5 = Interp5::init($jd1, $jdN, $r);
      $i5->x($jd1 + ($jdN - $jd1) / 2, $y´, $K);
      $i5->extremum($x´, $y´);

      if ($x´ && $y´ && $x´ != 0 && $y´ != 0) {
        $x = $x´;
        $y = $y´;
      }

      if ((abs($K) < abs($ΔK) && $x´ != 0 && $y´ != 0)) {
        $de = Util::de()->jde($x´);
        $pv = $de->position($obj->getSSObj(), $this->getSSObj());
        $r  = sqrt($pv[0] * $pv[0] + $pv[1] * $pv[1] + $pv[2] * $pv[2]);

        $dist = Distance::au($r)->round(7);
        $date = AstroDate::jd($x);
        return;
      }
      else {
        $mr = array_search(min($r), $r);
        if ($mr < 1)
          $mr = 0;
        if ($mr > 4)
          $mr = 4;

        $jm  = $jd1 + $mr * $step;
        $jd1 = $jm - ($step * 1);
        $jdN = $jm + ($step * 1);
      }
    }

    $de = Util::de()->jde($minx);
    $pv = $de->position($obj->getSSObj(), $this->getSSObj());
    $r  = sqrt($pv[0] * $pv[0] + $pv[1] * $pv[1] + $pv[2] * $pv[2]);

    $dist = Distance::au($r)->round(7);
    $date = AstroDate::jd($minx);

    return;
  }

  public static function closestApproach(SolarSystObj $obj, $date1, $date2,
          AstroDate &$date = null, Distance &$dist = null) {

    $n          = 5;
    //$diffThresh = 1e-4;
    $diffThresh = 1e-9;
    $c          = PHP_INT_MAX;

    $jd1  = Util::parseAstroDate($date1)->toJD();
    $jdN  = Util::parseAstroDate($date2)->toJD();
    $targ = new static();

    $xe = 0;
    $ye = 0;

    for ($i = 0; $i < 15; $i++) {
      $r = [];

      $step = ($jdN - $jd1) / $n;
      for ($jd = $jd1; $jd < $jdN; $jd += $step) {
        if (count($r) == $n)
          break;

        $ri  = $targ->date((float)$jd)->observe($obj)[0]->distTrue->au;
        $r[] = $ri;
        echo "\n$jd\t$ri";
      }
      echo "\n";

      $i5 = Interp5::init($jd1, $jdN, $r);
      $i5->x($jd1 + ($jdN - $jd1) / 2, $y, $K);

      $i5->extremum($x, $y);
      //print_r([$x, $y]);
      //echo "\n". $K;
      //echo "\n". AstroDate::jd($x);
      //echo "\n". Distance::au($y)."\n\n";



      if ($x && $y && $x != 0 && $y != 0) {
        $xe = $x;
        $ye = $y;

        print_r([$jd1, $jdN, $jdN - $jd1, $K]);
        //print_r([$i, $xe, $ye, $K]);
      }

      if (abs($K) < abs($diffThresh) && $xe != 0 && $ye != 0) {
        $dist = $targ->date((float)$xe)->observe($obj)[0]->distTrue->au;
        $dist = Distance::au($dist)->round(7);
        $date = AstroDate::jd($xe);

        //$date = AstroDate::jd($xe);
        //$dist = Distance::au($ye)->round(7);
        echo "\n" . $i;
        return;
      }

      $ind = array_search(min($r), $r);
      if ($ind < 1)
        $ind = 0;
      if ($ind > 4)
        $ind = 4;

      $jm  = $jd1 + $ind * $step;
      $jd1 = $jm - ($step * 1);
      $jdN = $jm + ($step * 1);

      echo "\n\n$jm\t\t$jd1\t $jdN\n\n";
    }


    return;





    exit;


    goto test;

    $jd1  = Util::parseAstroDate($date1)->toJD();
    $jdN  = Util::parseAstroDate($date2)->toJD();
    $targ = new static();

    $n  = 0;
    $n  = 5;
    $st = ($jdN - $jd1) / $n;
    while ($n < 5) {
      $table = [];

      echo "\n";
      for ($jd = $jd1; $jd < $jdN; $jd += $st) {
        $dist    = $targ->date((float)$jd)->observe($obj)[0]->distTrue->au;
        $table[] = [$jd, $dist];
        echo "\n$jd\t$dist";
      }


      $min  = PHP_INT_MAX;
      $minI = -1;
      foreach ($table as $t) {
        if ($t[1] < $min) {
          $min = $t[1];
          $minI++;
        }
      }

      if ($minI == 0)
        $minI+=1;
      if ($minI >= $n - 1)
        $minI-=1;

      $jd1 = $table[$minI - 1][0];
      $jdN = $table[$minI + 1][0];

      if ($jdN < $jd1) {
        $tmp = $jd1;
        $jd1 = $jdN;
        $jdN = $tmp;
      }

      $st = ($jdN - $jd1) / $n;
      $n++;
    }

    echo "\n" . $date = AstroDate::jd($table[$minI][0]);
    echo "\n" . $dist = Distance::au($table[$minI][1]);

    echo "\n\n";




    test:

    $jd1  = Util::parseAstroDate($date1)->toJD();
    $jdN  = Util::parseAstroDate($date2)->toJD();
    $targ = new static();

    $r  = [];
    $n  = 5;
    $st = ($jdN - $jd1) / $n;
    for ($jd = $jd1; $jd < $jdN; $jd += $st) {
      if (count($r) == 5)
        break;

      $dist = $targ->date((float)$jd)->observe($obj)[0]->distTrue->au;
      $r[]  = $dist;
    }
    print_r($r);

    $i5 = Interp5::init($jd1, $jdN, $r);

    $jdMin = PHP_INT_MAX;
    $rMin  = PHP_INT_MAX;
    for ($n = -1; $n < 1; $n += 0.001) {
      $i5->n($n, $x, $y, $K);
      if ($y < $rMin) {
        $jdMin = $x;
        $rMin  = $y;
      }
    }

    echo "\n\n$jdMin\t$rMin\t$K";
    echo "\n" . AstroDate::jd($jdMin);

    echo "\n\n\n";
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

  /**
   * @return Distance True diameter of the body
   */
  abstract protected function getTrueDiam();

  abstract protected function getName();
}
