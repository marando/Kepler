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

abstract class SolarSystObj {

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

  public function diam(SolarSystObj $obj) {
    $d = $obj->getPhysicalDiameter()->au;
    $D = $this->observe($obj)[0]->dist->au;
    $δ = 206265 * $d / $D;

    return \Marando\Units\Angle::arcsec($δ);
  }

  // // // Abstract

  abstract protected function getJPLObj();

  protected function getPhysicalDiameter() {
    return Distance::mi(8000);
  }

}
