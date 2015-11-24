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

use \InvalidArgumentException;
use \Marando\AstroCoord\Cartesian;
use \Marando\AstroCoord\Equat;
use \Marando\AstroCoord\Frame;
use \Marando\AstroDate\AstroDate;
use \Marando\AstroDate\Epoch;
use \Marando\IAU\IAU;
use \Marando\JPLephem\DE\Reader;
use \Marando\JPLephem\DE\SSObj;
use \Marando\Kepler\JPL\SmallBodyData;
use \Marando\Units\Angle;
use \Marando\Units\Distance;
use \Marando\Units\Time;

/**
 * Represents Keplerian orbital elements
 *
 * @property string    $bodyName Optional name for the body's name
 * @property Epoch     $epoch    Epoch of orbital elements
 * @property Distance  $axis     Semi-major axis, a
 * @property float     $ecc      Eccentricity, e
 * @property Angle     $incl     Inclination, i
 * @property Angle     $meanLon  Mean longitude, L
 * @property Angle     $lonPeri  Longitude of perihelion, ϖ
 * @property Angle     $node     Longitude of the ascending node, Ω
 * @property Angle     $mAnomaly Mean anomaly, M
 * @property Angle     $eAnomaly Eccentric anomaly, E
 *
 * @property Angle     $argPeri  Argument of perihelion, ω
 * @property Distance  $axisMin  Semi-minor axis, b
 * @property Angle     $mMotion  Mean motion, n in degrees per day
 * @property AstroDate $datePeri Date of perihelion transit, Tp
 * @property Distance  $periDist Perihelion distance
 * @property Distance  $apheDist Aphelion distance
 * @property Time      $period   Orbital period
 * @property Angle     $tAnomaly True anomaly, υ
 */
class Orbitals {

  use \Marando\Units\Traits\CopyTrait;

  //----------------------------------------------------------------------------
  // Constructors
  //----------------------------------------------------------------------------

  /**
   * Kepleriam orbital elements for the planet Mercury
   * @param  AstroDate $date
   * @return static
   * @see    http://ssd.jpl.nasa.gov/?planet_pos
   */
  public static function Mercury(AstroDate $date = null) {
    $date = $date ? $date : AstroDate::now()->toTDB();

    $Me           = new static();
    $Me->bodyName = 'Mercury';
    $Me->epoch    = $date->toEpoch();
    $Me->initPlanet(1);

    return $Me;
  }

  /**
   * Kepleriam orbital elements for the planet Jupiter
   * @param  AstroDate $date
   * @return static
   * @see    http://ssd.jpl.nasa.gov/?planet_pos
   */
  public static function Jupiter(AstroDate $date = null) {
    $date = $date ? $date : AstroDate::now()->toTDB();

    $J           = new static();
    $J->bodyName = 'Jupiter';
    $J->epoch    = $date->toEpoch();
    $J->initPlanet(5);

    return $J;
  }

  /**
   *
   * @param type $name
   * @param type $bodyType
   * @return static
   */
  public static function search($name, $bodyType = null) {
    return SmallBodyData::find($name);
  }

  public static function comet(Epoch $epoch, Distance $q, $e, Angle $i,
          Angle $w, Angle $node, AstroDate $tp, $name = '') {

    // Set provided properties
    $comet           = new Orbitals();
    $comet->type     = 'comet';
    $comet->epoch    = $epoch;
    $comet->bodyName = $name;
    $comet->ecc      = $e;
    $comet->incl     = $i;
    $comet->node     = $node;
    $comet->datePeri = $tp;

    // Calculate longitude of perihelion and semi-major axis
    $comet->lonPeri = $node->copy()->add($w);
    $comet->axis    = Distance::au($q->au / (1 - $e));

    // Calculate time of since perihelion passage and mean anomaly
    $sincePeri = $epoch->jd - $tp->toJD();
    $mAnomaly  = Angle::deg($comet->mMotion->deg * $sincePeri);

    // Calculate argument of perihelion and mean longitude
    $comet->argPeri = $comet->lonPeri->copy()->subtract($comet->node);
    $comet->meanLon = $comet->argPeri->copy()->add($mAnomaly);



    return $comet;
  }

  public function perihelion($i = 0) {
    $sincePeri = $this->epoch->jd - $this->datePeri->toJD();
    $nextPeri  = $this->epoch->jd + ($i * 360 / $this->mMotion->deg) - $sincePeri;

    return AstroDate::jd($nextPeri);
  }

  public static function asteroid($name) {
    // search name or number
  }

  //----------------------------------------------------------------------------
  // Properties
  //----------------------------------------------------------------------------

  /**
   * Epoch of orbital elements
   * @var Epoch
   */
  protected $epoch;

  /**
   * Name of the body
   * @var string
   */
  protected $bodyName;

  /**
   * Semi-major axis
   * @var Distance
   */
  protected $axis;

  /**
   * Eccentricity
   * @var float
   */
  protected $ecc;

  /**
   * Inclination
   * @var Angle
   */
  protected $incl;

  /**
   * Mean Longitude
   * @var Angle
   */
  protected $meanLon;

  /**
   * Longitude of perihelion
   * @var Angle
   */
  protected $lonPeri;

  /**
   * Longitude of the ascending node
   * @var Angle
   */
  protected $node;

  /**
   * JPL data for determining orbital elements for planet of this instance
   * @var array
   */
  protected $jplData;

  /**
   * Date of perihelion transit
   * @var AstroDate
   */
  protected $datePeri;
  protected $type = '';

  public function __get($name) {
    switch ($name) {
      case 'epoch':
      case 'bodyName':
      case 'axis':
      case 'ecc':
      case 'incl':
      case 'meanLon':
      case 'lonPeri':
      case 'node':
        return $this->{$name};

      case 'argPeri':
        return $this->lonPeri->copy()->subtract($this->node);

      case 'mAnomaly':
        return $this->calcMeanAnomaly();

      case 'eAnomaly':
        return $this->calcEccentricAnomaly();

      case 'axisMin':
        return $this->calcSemiMinAxis();

      case 'mMotion':
        $a = $this->axis->au;
        return Angle::deg(0.9856076686 / ($a * sqrt($a)));

      case 'periDist':
        return Distance::au($this->axis->au * (1 - $this->ecc));

      case 'apheDist':
        return Distance::au($this->axis->au * (1 + $this->ecc));

      case 'period':
        return $this->perihelion(0)->diff($this->perihelion(1))->setUnit('day');

      case 'tAnomaly':
        return $this->calcTrueAnomaly();

      default:
        throw new \Exception("Undefined property '{$name}'");
    }
  }

  public function __set($name, $value) {
    switch ($name) {
      case 'axis':
        if (!$value instanceof Distance)
          throw new InvalidArgumentException("$name must be an instance of Distance");
        $this->{$name} = $value;
        return;

      case 'ecc':
        $this->{$name} = $value;
        return;

      case 'incl':
      case 'meanLon':
      case 'lonPeri':
      case 'node':
        if (!$value instanceof Angle)
          throw new InvalidArgumentException("$name must be an instance of Angle");
        $this->{$name} = $value;
        return;

      case 'epoch':
        if (!$value instanceof Epoch)
          throw new InvalidArgumentException("$name must be an instance of Epoch");
        $this->{$name} = $value;
        return;

      case 'bodyName':
        $this->{$name} = $value;
        return;
    }
  }

  //----------------------------------------------------------------------------
  // Functions
  //----------------------------------------------------------------------------

  public function toEquat(AstroDate $date = null, &$lt = null) {
    $dt = $date ? $date : $this->epoch->toDate();

    $ε = IAU::Obl06($dt->toTT()->toJD(), 0);
    $Ω = $this->node->rad;
    $i = $this->incl->rad;
    $q = $this->periDist->au;
    $ω = $this->argPeri->rad;

    // Astronomical Algorithms (J. Meeus), p.228
    $F = cos($Ω);
    $G = sin($Ω) * cos($ε);
    $H = sin($Ω) * sin($ε);
    $P = -sin($Ω) * cos($i);
    $Q = cos($Ω) * cos($i) * cos($ε) - sin($i) * sin($ε);
    $R = cos($Ω) * cos($i) * sin($ε) + sin($i) * cos($ε);

    $A = atan2($F, $P);
    $B = atan2($G, $Q);
    $C = atan2($H, $R);
    $a = sqrt($F * $F + $P * $P);
    $b = sqrt($G * $G + $Q * $Q);
    $c = sqrt($H * $H + $R * $R);

    if ($this->type == 'comet') {

      $τT = $dt->toJD() - $this->datePeri->toJD();
      $W  = (0.03649116245 / ($q * sqrt($q))) * $τT;

      // find s
      $G = $W / 2;
      $Y = pow($G + sqrt($G * $G + 1), 1 / 3);
      $s = $Y - 1 / $Y;

      // Find true anomaly and radius vector (not affected for light time)
      $ν = 2 * atan($s);
      $r = $q * (1 + $s * $s);

      // Position of comet
      $x = $r * $a * sin($A + $ω + $ν);
      $y = $r * $b * sin($B + $ω + $ν);
      $z = $r * $c * sin($C + $ω + $ν);

      // Position of sun
      $de    = (new Reader())->jde($dt->toJD());
      $pvSun = $de->position(SSObj::Sun(), SSObj::Earth());

      // Find Geocentric RA/Decl and distance
      $ξ = $pvSun[0] + $x;
      $η = $pvSun[1] + $y;
      $ζ = $pvSun[2] + $z;
      $α = Angle::atan2($η, $ξ)->norm()->toTime();
      $Δ = Distance::au(sqrt($ξ * $ξ + $η * $η + $ζ * $ζ));
      $δ = Angle::rad($ζ / $Δ->au);

      return new Equat(Frame::ICRF(), $dt->toEpoch(), $α, $δ, $Δ);
    }
  }

  public function toEclip($date = null, $lt = null) {
    //$ε = Angle::rad(IAU::Obl06($date->toTT()->toJD(), 0));
    //return $this->toEquat($date, $lt)->toEclip($ε);
  }

  // // // Protected

  /**
   * Initializes this instance with the orbital elements of a planet
   * @param  int       $i Planet number, 1=Mercury, 9=Pluto
   * @throws Exception    Occurs if the date is out of range
   */
  protected function initPlanet($i) {
    // Date values
    $dataJPL = static::dataJPL($i);
    $year    = $this->epoch->toDate()->year;
    $T       = ($this->epoch->jd - 2451545.0) / Epoch::DaysJulianYear;

    // Check date and determine year class
    if ($year <= 2050 && $year >= 1800)
      $yearClass = 0;
    else if ($year <= 3000 && $year >= -3000)
      $yearClass = 1;
    else
      throw new Exception("Orbital elements can only be calculated for dates"
      . "between 3000 BC and 3000 AD");

    // Calculate each orbital element
    $elem = [];
    foreach ($dataJPL[0] as $el => $data) {
      $elem[$el] = $data[$yearClass][0] + $data[$yearClass][1] * $T;
    }

    // Populate data
    $this->jplData = $dataJPL;
    $this->axis    = Distance::au($elem['a']);
    $this->ecc     = $elem['e'];
    $this->incl    = Angle::deg($elem['i']);
    $this->meanLon = Angle::deg($elem['L'])->norm();
    $this->lonPeri = Angle::deg($elem['ϖ']);
    $this->node    = Angle::deg($elem['Ω']);

    return;
  }

  /**
   * Calculates the M, the mean anomaly for this instance
   * @return Angle
   */
  protected function calcMeanAnomaly() {
    // Required values
    $y = $this->epoch->toDate()->year;
    $L = $this->meanLon->deg;
    $ϖ = $this->argPeri->deg;

    // Initial computation of M
    $M = $L - $ϖ;

    // If year is outside of 1800-2050, add extra JPL terms
    if ($y <= 2050 && $y >= 1800) {
      // Check if extra terms of Jupiter to Pluto exist for this instance
      if ($this->jplData[1] != null) {
        // Time variable
        $T = ($this->epoch->jd - 2451545.0) / Epoch::DaysJulianYear;

        // Extra terms
        $b = $this->jplData[1]['b'];
        $c = $this->jplData[1]['c'];
        $s = $this->jplData[1]['s'];
        $f = $this->jplData[1]['f'];

        // Additions to M based on terms (see JPL documentation)
        $M += $b * ($T * $T) + $c * cos($f * $T) + $s * sin($f * $T);
      }
    }

    // Normalize angle
    return Angle::deg($M)->norm(-180, 180);
  }

  /**
   * Finds the eccentric anomaly of this instance via iteration
   * @return Angle
   */
  protected function calcEccentricAnomaly() {
    $M  = $this->mAnomaly->rad;
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

  /**
   * Calculates the semi-minor axis of this instance
   * @return Distance
   */
  protected function calcSemiMinAxis() {
    $a = $this->axis->au;
    $e = $this->ecc;

    $b = $a * sqrt(1 - $e * $e);
    return Distance::au($b);
  }

  protected function calcTrueAnomaly() {
    $E = $this->eAnomaly->deg;
    $e = $this->ecc;

    $cosv = (cos($E) - $e) / (1 - $e * cos($E));
    return Angle::rad(acos($cosv));
  }

  // // // Static

  /**
   * Gets the JPL terms and rates for determining the orbital elements of a
   * provided planet
   *
   * @param  int   $planet Planet number, 1=Mercury, 9=Pluto
   * @return array
   */
  protected static function dataJPL($planet) {
    $data = [
        // Mercury
        1 => [
            //      1800-2050                 -3000 to 3000
            'a' => [[0.38709927, 0.00000037], [0.38709843, 0.00000000]],
            'e' => [[0.20563593, 0.00001906], [0.20563661, 0.00002123]],
            'i' => [[7.00497902, -0.00594749], [7.00559432, -0.00590158]],
            'L' => [[252.25032350, 149472.67411175], [252.25166724, 149472.67486623]],
            'ϖ' => [[77.45779628, 0.16047689], [77.45771895, 0.15940013]],
            'Ω' => [[48.33076593, -0.12534081], [48.33961819, -0.12214182]],
        ],
        // Jupiter
        5 => [
            //      1800-2050                  -3000 to 3000
            'a' => [[5.20288700, -0.00011607], [ 5.20248019, -0.00002864]],
            'e' => [[0.04838624, -0.00013253], [ 0.04853590, 0.00018026]],
            'i' => [[1.30439695, -0.00183714], [ 1.29861416, -0.00322699]],
            'L' => [[34.39644051, 3034.74612775], [ 34.33479152, 3034.90371757]],
            'ϖ' => [[14.72847983, 0.21252668], [ 14.27495244, 0.18199196]],
            'Ω' => [[100.47390909, 0.20469106], [ 100.29282654, 0.13024619]],
        ],
    ];

    // Additional terms required for computation of M
    $terms = [
        1 => 0,
        2 => 0,
        3 => 0,
        4 => 0,
        // Jupiter
        5 => [
            'b' => -0.00012452,
            'c' => 0.06064060,
            's' => -0.35635438,
            'f' => 38.35125000,
        ],
        // Saturn
        6 => [
            'b' => 0.00025899,
            'c' => -0.13434469,
            's' => 0.87320147,
            'f' => 38.35125000,
        ],
        // Uranus
        7 => [
            'b' => 0.00058331,
            'c' => -0.97731848,
            's' => 0.17689245,
            'f' => 7.67025000,
        ],
        // Neptune
        8 => [
            'b' => -0.00041348,
            'c' => 0.68346318,
            's' => -0.10162547,
            'f' => 7.67025000,
        ],
        // Pluto
        9 => [
            'b' => -0.01262724,
            'c' => 0,
            's' => 0,
            'f' => 0,
        ],
    ];

    // Return array of terms
    return [$data[$planet], $terms[$planet]];
  }

  // // // Overrides

  /**
   * Represents this instance as a string
   * @return string
   */
  public function __toString() {
    if ($this->type == 'comet')
      return $this->formatComet();

    // sprintf format
    $fmt = '%+ 10.5f';

    // Format elements
    $a = sprintf($fmt, $this->axis->au);
    $e = sprintf($fmt, $this->ecc);
    $i = sprintf($fmt, $this->incl->deg);
    $L = sprintf($fmt, $this->meanLon->deg);
    $ϖ = sprintf($fmt, $this->lonPeri->deg);
    $ω = sprintf($fmt, $this->argPeri->deg);
    $Ω = sprintf($fmt, $this->node->deg);
    $M = sprintf($fmt, $this->mAnomaly->deg);
    $E = sprintf($fmt, $this->eAnomaly->deg);

    // Format title
    if ($this->bodyName)
      $title = "{$this->bodyName}\nEpoch {$this->epoch} (JD {$this->epoch->jd})";
    else
      $title = "Orbital Elements\nEpoch {$this->epoch} (JD {$this->epoch->jd})";

    // Generate string
    $str = <<<ELEM
{$title}
 * * *
a = {$a} AU
e = {$e}
i = {$i}°
L = {$L}°
ϖ = {$ϖ}°
ω = {$ω}°
Ω = {$Ω}°
M = {$M}°
E = {$E}°
ELEM;

    return "\n$str\n";
  }

  protected function formatComet() {
    // sprintf format
    $fmt = '% 14.8f';

    // Format elements
    $a  = sprintf($fmt, $this->axis->au);
    $e  = sprintf($fmt, $this->ecc);
    $i  = sprintf($fmt, $this->incl->deg);
    $L  = sprintf($fmt, $this->meanLon->deg);
    $ϖ  = sprintf($fmt, $this->lonPeri->deg);
    $ω  = sprintf($fmt, $this->argPeri->deg);
    $Ω  = sprintf($fmt, $this->node->deg);
    $M  = sprintf($fmt, $this->mAnomaly->deg);
    $E  = sprintf($fmt, $this->eAnomaly->deg);
    $q  = sprintf($fmt, $this->periDist->au);
    $ap = sprintf($fmt, $this->apheDist->au);
    $T  = sprintf($fmt, $this->period->days / Epoch::DaysJulianYear);
    $n  = sprintf($fmt, $this->mMotion->deg);

    echo "v = " . $this->tAnomaly->deg;

    // Generate string
    $str = <<<ELEM
====================================================
Comet {$this->bodyName}
Epoch {$this->epoch}
----------------------------------------------------
Orbital Eccentricity    | {$e}
Orbital Inclination     | {$i}°
Perihelion distance     | {$q} AU
Aphelion distance       | {$ap} AU
Semi-major axis         | {$a} AU
Orbital period          | {$T} years
Perihelion Transit      | {$this->datePeri->format('Y-M-d H:i:s.u')}
Next Perihelion Transit | {$this->perihelion(1)->format('Y-M-d H:i:s.u')}
Argument of Perihelion  | {$ω}°
Ascending node          | {$Ω}°
Mean anomaly            | {$M}°
Mean motion             | {$n}°/day
====================================================
ELEM;

    return "\n$str\n";
  }

}
