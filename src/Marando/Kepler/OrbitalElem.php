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
use \Marando\AstroDate\Epoch;
use \Marando\Units\Angle;
use \Marando\Units\Distance;
use \Marando\Units\Time;

/**
 * @property Epoch     $epoch
 * @property string    $name
 * @property float     $ecc
 * @property Angle     $incl
 * @property Distance  $periDist
 * @property Distance  $apheDist
 * @property Distance  $axis
 * @property Distance  $axisMin
 * @property Time      $period
 * @property AstroDate $timePeri
 * @property Angle     $argPeri
 * @property Angle     $longPeri
 * @property Angle     $node
 * @property Angle     $meanMotion
 * @property Angle     $meanLong
 * @property Angle     $meanAnomaly
 * @property Angle     $eccAnomaly
 * @property Angle     $trueAnomaly
 * @property Time      $sincePeri Time since perihelion
 */
class OrbitalElem {

  /**
   * Eccentricity of near-parabolic lower bound
   */
  const N_PARA_LB = 0.98;

  //----------------------------------------------------------------------------
  // Constructors
  //----------------------------------------------------------------------------

  /**
   * Creates a new orbital element instance
   * @param Epoch  $epoch
   * @param string $name
   */
  private function __construct(Epoch $epoch, $name = '') {
    $this->epoch = $epoch;
    $this->name  = $name;
  }

  // // // Static

  /**
   * Creates a new orbital element instance from standard elements for a comet
   *
   * @param  Epoch     $epoch Epoch of the orbital elements
   * @param  Distance  $q     Perihelion distance
   * @param  float     $e     Eccentricity
   * @param  Angle     $i     Inclination
   * @param  Angle     $ω     Argument of perihelion
   * @param  Angle     $Ω     Longitude of the ascending node
   * @param  AstroDate $T0    Time of perihelion transit
   * @param  string    $name  Name of the comet
   * @return static
   */
  public static function comet(Epoch $epoch, Distance $q, $e, Angle $i,
          Angle $ω, Angle $Ω, AstroDate $T0, $name = '') {

    // Elliptic comet
    if ($e < static::N_PARA_LB)
      return static::initCometElliptic($epoch, $q, $e, $i, $ω, $Ω, $T0, $name);

    // Near-parabolic comet
    else if ($e < 1 && $e >= static::N_PARA_LB)
      return static::initCometNearParab($epoch, $q, $e, $i, $ω, $Ω, $T0, $name);

    // Parabolic comet
    else if ($e == 1)
      return static::initCometParab($epoch, $q, $e, $i, $ω, $Ω, $T0, $name);

    // Hyperbolic comet
    else if ($e > 1)
      return static::initCometHyperbolic($epoch, $q, $e, $i, $ω, $Ω, $T0, $name);
  }

  public static function asteroid(Epoch $epoch, Distance $a, $e, Angle $i,
          Angle $ω, Angle $Ω, Angle $M, $H, $G) {

  }

  /**
   *
   * @param Epoch    $epoch Epoch of the orbital elements
   * @param Distance $a     Semi-major axis
   * @param float    $e     Eccentricity
   * @param Angle    $i     Inclination
   * @param Angle    $ϖ     Longitude of perihelion
   * @param Angle    $Ω     Longitude of the ascending node
   * @param Angle    $L    Mean longitude
   * @param string   $name  Name of the planet
   */
  public static function planet(Epoch $epoch, Distance $a, $e, Angle $i,
          Angle $ϖ, Angle $Ω, Angle $L, $name = '') {

    $pl          = new static($epoch, $name);
    $pl->objType = 'Planet';

    $pl->axis     = $a;
    $pl->ecc      = $e;
    $pl->incl     = $i;
    $pl->longPeri = $ϖ;
    $pl->node     = $Ω;

    // Find perihelion distance, mean motion, period and argument of perihelion
    $pl->periDist   = Distance::au($a->au * (1 - $e));
    $pl->meanMotion = Angle::deg(0.9856076686 / ($a->au * sqrt($a->au)));
    $pl->period     = Time::days(360 / $pl->meanMotion->deg);
    $pl->argPeri    = $ϖ->copy()->subtract($Ω);

    // Find mean anomaly, then time of perihelion
    $M            = $L->deg - $Ω->deg - $pl->argPeri->deg;
    $pl->timePeri = AstroDate::jd($epoch->jd - ($M / $pl->meanMotion->deg));

    return $pl;
  }

  //----------------------------------------------------------------------------
  // Properties
  //----------------------------------------------------------------------------

  /**
   * Epoch of the orbital elements
   * @var Epoch
   */
  protected $epoch;

  /**
   * Name of the body
   * @var string
   */
  protected $name;

  /**
   * Semi-major axis distance, a
   * @var Distance
   */
  protected $axis;

  /**
   * Eccentricity, e
   * @var float
   */
  protected $ecc;

  /**
   * Orbital period
   * @var Time
   */
  protected $period;

  /**
   * Inclination
   * @var Angle
   */
  protected $incl;

  /**
   * Longitude of perihelion, ϖ
   * @var Angle
   */
  protected $longPeri;

  /**
   * Time of perihelion, T0
   * @var AstroDate
   */
  protected $timePeri;

  /**
   * Argument of perihelion, ω
   * @var Angle
   */
  protected $argPeri;

  /**
   * Longitude of the ascending node
   * @var Angle
   */
  protected $node;

  /**
   * Perihelion distance
   * @var Distance
   */
  protected $periDist;

  /**
   * Mean motion per day
   * @var Angle
   */
  protected $meanMotion;
  protected $objType = '';

  public function __get($name) {
    switch ($name) {
      case 'apheDist':
        return $this->calcApheDist();

      case 'eccAnomaly':
        return $this->calcEccAnomaly();

      case 'meanAnomaly':
        return $this->calcMeanAnomaly();

      case 'meanLong':
        return $this->calcMeanLong();

      case 'sincePeri':
        return $this->calcSincePeri();

      case 'trueAnomaly':
        return $this->calcTrueAnomaly();

      case 'axisMin':
        return $this->calcAxisMin();

      case 'r':
        return $this->calcRadius();

      case 'name':
      case 'node':
      case 'incl':
      case 'argPeri':
        return $this->{$name};
    }
  }

  public function __set($name, $value) {
    switch ($name) {
      case 'epoch':
        if ($value instanceof Epoch)
          return $this->epoch = $value;
        else
          throw new \InvalidArgumentException();
    }
  }

  //----------------------------------------------------------------------------
  // Functions
  //----------------------------------------------------------------------------

  public function perihelion($num = 0) {
    $sT0 = $this->epoch->jd - $this->timePeri->toJD();
    $n   = $this->meanMotion->deg;
    $Tn  = $this->epoch->jd + ($num * 360 / $n) - $sT0;

    return is_nan($Tn) ? null : AstroDate::jd($Tn);
  }

  public function isParabolic() {
    return $this->ecc == 1;
  }

  public function isHyperbolic() {
    return $this->ecc > 1;
  }

  public function isElliptic() {
    return $this->ecc < static::N_PARA_LB;
  }

  public function isNearParab() {
    return $this->ecc >= static::N_PARA_LB && $this->ecc < 1;
  }

  // // // Protected

  protected function calcAxisMin() {
    $a = $this->axis->au;
    $e = $this->ecc;

    $b = $a * sqrt(1 - $e * $e);
    return Distance::au($b);
  }

  protected function calcSincePeri() {
    return Time::days($this->epoch->jd - $this->timePeri->toJD());
  }

  protected function calcMeanLong() {
    return $this->argPeri->copy()->add($this->meanAnomaly);
  }

  protected function calcRadius() {
    $q = $this->periDist->au;
    $e = $this->ecc;
    $ν = $this->trueAnomaly->rad;

    return Distance::au($q * (1 + $e) / (1 + $e * cos($ν)));
  }

  protected function calcMeanAnomaly() {
    if ($this->isElliptic()) {
      $n   = $this->meanMotion->deg;
      $sT0 = $this->sincePeri->days;

      return Angle::deg($n * $sT0);
    }

    // Parabolic orbits
    else if ($this->isParabolic()) {
      // Mean anomaly does not exist for parabolic, it is zero
      return Angle::deg(0);
    }

    // Hyperbolic orbits
    else if ($this->isHyperbolic()) {
      $e = $this->ecc;
      $ν = $this->trueAnomaly->rad;
      $E = $this->eccAnomaly->rad;
      return Angle::rad($e * sinh($E) - $E);
    }
    else {
      $E = $this->eccAnomaly->rad;
      $e = $this->ecc;
      return Angle::rad($E - $e * sin($E));
    }
  }

  protected function calcEccAnomaly() {
    // Elliptic orbits
    if ($this->isElliptic()) {
      $M  = $this->meanAnomaly->rad;
      $e  = $this->ecc;
      $ΔE = PHP_INT_MAX;

      // Iterate to find E
      $E0 = $M + $e * sin($M);
      while ($ΔE > 0) {
        $ΔM = $M - ($E0 - $e * sin($E0));
        $ΔE = $ΔM / (1 - $e * cos($E0));
        $E0 = $E0 + $ΔE;
      }

      return Angle::deg($E0)->norm();
    }
    // Parabolic orbits
    else if ($this->isParabolic()) {
      // Eccentric anomaly does not exist for parabolic, it is zero
      return Angle::deg(0);
    }
    // Hyperbolic orbits
    else if ($this->isHyperbolic()) {
      $e = $this->ecc;
      $ν = $this->trueAnomaly->rad;

      return Angle::rad(acosh(($e + cos($ν)) / (1 + $e * cos($ν))));
    }
    // All other obits
    else {
      $e = $this->ecc;
      $ν = $this->trueAnomaly->rad;

      return Angle::rad(acos(($e + cos($ν)) / (1 + $e * cos($ν))));
    }
  }

  protected function calcApheDist() {
    return Distance::au($this->axis->au * (1 + $this->ecc));
  }

  protected function calcTrueAnomaly() {
    if ($this->isElliptic()) {
      $E = $this->eccAnomaly->deg;
      $e = $this->ecc;

      $cosν = (cos($E) - $e) / (1 - $e * cos($E));
      return Angle::rad(acos($cosν));
    }
    else {
      // For parabolic, near parabolic, and hyperbolic
      $q = $this->periDist->au;
      $e = $this->ecc;
      $t = $this->sincePeri->days;

      static::landgraf($q, $e, $t, $ν, $r);
      return $ν;
    }
  }

  protected function formatStr() {
    $fmt  = '% 14.8f';
    $objT = $this->objType ? "{$this->objType} " : '';
    $name = "{$objT}{$this->name}\n";
    $na   = '     -';

    $type;
    if ($this->isElliptic())
      $type = 'Elliptic';
    else if ($this->isNearParab())
      $type = 'Elliptic, near-parabolic';
    else if ($this->isParabolic())
      $type = 'Parabolic';
    else if ($this->isHyperbolic())
      $type = 'Hyperbolic';

    $e = sprintf($fmt, $this->ecc);
    $i = sprintf($fmt, $this->incl->deg);
    $q = sprintf($fmt, $this->periDist->au);
    $Q = $e < 1 ? sprintf("$fmt AU", $this->apheDist->au) : $na;
    $a = $e < 1 ? sprintf("$fmt AU", $this->axis->au) : $na;
    $b = $e < 1 ? sprintf("$fmt AU", $this->axisMin->au) : $na;
    $ω = sprintf($fmt, $this->argPeri->deg);
    $ϖ = sprintf($fmt, $this->longPeri->deg);
    $Ω = sprintf($fmt, $this->node->deg);
    $n = $e < 1 ? sprintf("{$fmt}°/day", $this->meanMotion->deg) : $na;
    $L = sprintf($fmt, $this->meanLong->deg);
    $M = $e < 1 ? sprintf("{$fmt}°", $this->meanAnomaly->deg) : $na;
    $E = $e < 1 ? sprintf("{$fmt}°", $this->eccAnomaly->deg) : $na;
    $ν = sprintf($fmt, $this->trueAnomaly->deg);
    $r = sprintf("{$fmt} AU", $this->r->au);

    $T = $na;
    if ($e < 1) {
      if ($this->period->days > 365.25)
        $T = sprintf("$fmt years", $this->period->days / 365.25);
      else
        $T = sprintf("$fmt days", $this->period->days);
    }

    @$T0 = $this->timePeri;
    @$T0 = sprintf('% 5d', $T0->year) . $T0->format('-M-d H:i:s.u');

    $T1 = $na;
    if ($e < 1) {
      $T1 = $this->perihelion(1);
      $T1 = sprintf('% 5d', $T1->year) . $T1->format('-M-d H:i:s.u');
    }

    $str = <<<STR

====================================================
{$name}Epoch {$this->epoch}
----------------------------------------------------
Orbital type            | {$type}
Orbital eccentricity    | {$e}
Orbital inclination     | {$i}°
Perihelion distance     | {$q} AU
Aphelion distance       | {$Q}
Semi-major axis         | {$a}
Semi-minor axis         | {$b}
Orbital period          | {$T}
Time of perihelion      | {$T0}
Next perihelion         | {$T1}
Argument of perihelion  | {$ω}°
Longitude of perihelion | {$ϖ}°
Longitude of asc. node  | {$Ω}°
Mean motion             | {$n}
Mean longitude          | {$L}°
Mean anomaly            | {$M}
Eccentric anomaly       | {$E}
True anomaly            | {$ν}°
Radius at epoch         | {$r}
====================================================

STR;

    return $str;
  }

  protected static function landgraf($q, $e, $t, &$ν, &$r) {
    $κ  = 0.01720209895;
    $q1 = $κ * sqrt((1 + $e) / $q) / (2 * $q);
    $g  = (1 - $e) / (1 + $e);

    if ($t == 0) {
      $ν = Angle::deg(0);
      $r = Distance::au($q);
      return;
    }

    $d1 = 10000.0;
    $d  = 1e-9;
    $q2 = $q1 * $t;
    $s  = 2.0 / (3 * abs($q2));
    $s  = 2.0 / tan(2 * atan(pow(tan(atan($s) / 2), 1 / 3)));

    if ($t < 0)
      $s = -$s;

    if ($e != 1) {
      $l = 0;
      while (true) {
        $s0 = $s;
        $z  = 1.0;
        $y  = $s * $s;
        $g1 = -$y * $s;
        $q3 = $q2 + 2 * $g * $s * $y / 3;
        while (true) {
          $z += 1;
          $g1 = -$g1 * $g * $y;
          $z1 = ($z - ($z + 1) * $g) / (2 * $z + 1);
          $f  = $z1 * $g1;
          $q3 += $f;
          if ($z > 50 || abs($f) > $d1) {
            throw new \Exception("no convergence");
          }
          if (abs($f) <= $d) {
            break;
          }
        }
        $l++;
        if ($l > 50) {
          throw new \Exception("no convergence {$l}");
        }
        while (true) {
          $s1 = $s;
          $s  = (2 * $s * $s * $s / 3 + $q3) / ($s * $s + 1);
          if (abs($s - $s1) <= $d) {
            break;
          }
        }
        if (abs($s - $s0) <= $d) {
          break;
        }
      }
    }

    $ν = Angle::rad(2 * atan($s))->norm();
    $r = Distance::au($q * (1 + $e) / (1 + $e * cos($ν->rad)));

    return;
  }

  protected static function initCometElliptic(Epoch $epoch, Distance $q, $e,
          Angle $i, Angle $ω, Angle $Ω, AstroDate $T0, $name = '') {

    $comet          = new static($epoch, $name);
    $comet->objType = 'Comet';

    $comet->periDist = $q;
    $comet->ecc      = $e;
    $comet->incl     = $i;
    $comet->argPeri  = $ω;
    $comet->node     = $Ω;
    $comet->timePeri = $T0;

    $a   = $q->au / (1 - $e);
    $Q   = $a * (1 + $e);
    $n   = 0.9856076686 / ($a * sqrt($a));
    $sT0 = $epoch->jd - $T0->toJD();
    $T   = (360 / $n);

    $comet->axis       = Distance::au($a);
    $comet->apheDist   = Distance::au($Q);
    $comet->meanMotion = Angle::deg($n);
    $comet->period     = Time::days($T);
    $comet->longPeri   = $Ω->copy()->add($ω);

    return $comet;
  }

  protected static function initCometNearParab(Epoch $epoch, Distance $q, $e,
          Angle $i, Angle $ω, Angle $Ω, AstroDate $T0, $name = '') {

    $comet          = new static($epoch, $name);
    $comet->objType = 'Comet';

    $comet->periDist = $q;
    $comet->ecc      = $e;
    $comet->incl     = $i;
    $comet->argPeri  = $ω;
    $comet->node     = $Ω;
    $comet->timePeri = $T0;

    $a   = $q->au / (1 - $e);
    $Q   = $a * (1 + $e);
    $n   = 0.9856076686 / ($a * sqrt($a));
    $sT0 = $epoch->jd - $T0->toJD();
    $T   = (360 / $n);

    $comet->axis       = Distance::au($a);
    $comet->apheDist   = Distance::au($Q);
    $comet->meanMotion = Angle::deg($n);
    $comet->period     = Time::days($T);
    $comet->longPeri   = $Ω->copy()->add($ω);

    return $comet;
  }

  protected static function initCometParab(Epoch $epoch, Distance $q, $e,
          Angle $i, Angle $ω, Angle $Ω, AstroDate $T0, $name = '') {

    $comet          = new static($epoch, $name);
    $comet->objType = 'Comet';

    $comet->periDist = $q;
    $comet->ecc      = $e;
    $comet->incl     = $i;
    $comet->argPeri  = $ω;
    $comet->node     = $Ω;
    $comet->timePeri = $T0;
    $comet->longPeri = $Ω->copy()->add($ω);

    return $comet;
  }

  protected static function initCometHyperbolic(Epoch $epoch, Distance $q, $e,
          Angle $i, Angle $ω, Angle $Ω, AstroDate $T0, $name = '') {

    $comet          = new static($epoch, $name);
    $comet->objType = 'Comet';

    $comet->periDist = $q;
    $comet->ecc      = $e;
    $comet->incl     = $i;
    $comet->argPeri  = $ω;
    $comet->node     = $Ω;
    $comet->timePeri = $T0;

    $a   = $q->au / (1 - $e);
    $Q   = $a * (1 + $e);
    $n   = 0.9856076686 / ($a * sqrt($a));
    $sT0 = $epoch->jd - $T0->toJD();
    $T   = (360 / $n);

    $comet->axis       = Distance::au($a);
    $comet->apheDist   = Distance::au($Q);
    $comet->meanMotion = Angle::deg($n);
    $comet->period     = Time::days($T);
    $comet->longPeri   = $Ω->copy()->add($ω);

    return $comet;
  }

  // // // Overrides

  public function __toString() {
    return $this->formatStr();
  }

}
