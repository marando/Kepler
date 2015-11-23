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
use \Marando\AstroDate\AstroDate;
use \Marando\AstroDate\Epoch;
use \Marando\Units\Angle;
use \Marando\Units\Distance;

/**
 * Represents Keplerian orbital elements
 *
 * @property string   $bodyName Optional name for the body's name
 * @property Epoch    $epoch    Epoch of orbital elements
 * @property Distance $axis     Semi-major axis, a
 * @property float    $ecc      Eccentricity, e
 * @property Angle    $incl     Inclination, i
 * @property Angle    $meanLon  Mean longitude, L
 * @property Angle    $lonPeri  Longitude of perihelion, ϖ
 * @property Angle    $argPeri  Argument of perihelion, ω
 * @property Angle    $node     Longitude of the ascending node, Ω
 * @property Angle    $mAnomaly Mean anomaly, M
 * @property Angle    $eAnomaly Eccentric anomaly, E
 */
class Orbitals {
  //----------------------------------------------------------------------------
  // Constructors
  //----------------------------------------------------------------------------

  /**
   * Kepleriam orbital elements for the planet Mercury
   * @param  AstroDate $date
   * @return static
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
   */
  public static function Jupiter(AstroDate $date = null) {
    $date = $date ? $date : AstroDate::now()->toTDB();

    $J           = new static();
    $J->bodyName = 'Jupiter';
    $J->epoch    = $date->toEpoch();
    $J->initPlanet(5);

    return $J;
  }

  public static function comet() {

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

  public function __get($name) {
    switch ($name) {
      case 'axis':
      case 'ecc':
      case 'incl':
      case 'meanLon':
      case 'lonPeri':
        return $this->{$name};

      case 'argPeri':
        return $this->lonPeri->copy()->subtract($this->node);

      case 'mAnomaly':
        return $this->calcMeanAnomaly();

      case 'eAnomaly':
        return $this->calcEccentricAnomaly();
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
    $M  = $this->mAnomaly->deg;
    $e  = $this->ecc;
    $ΔE = PHP_INT_MAX;

    // Iterate to find E
    $E0 = $M + $e * sin($M);
    while ($ΔE > 0) {
      $ΔM = $M - ($E0 - $e * sin($E0));
      $ΔE = $ΔM / (1 - $e * cos($E0));
      $E0 = $E0 + $ΔE;
    }

    return Angle::deg($E0);
  }

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
            'e' => [[0.20563593, 0.00001906]],
            'i' => [[7.00497902, -0.00594749]],
            'L' => [[252.25032350, 149472.67411175]],
            'ϖ' => [[77.45779628, 0.16047689]],
            'Ω' => [[48.33076593, -0.12534081]],
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
    $fmt = '% 9.5f';

    $a = sprintf($fmt, $this->axis->au);
    $e = sprintf($fmt, $this->ecc);
    $i = sprintf($fmt, $this->incl->deg);
    $L = sprintf($fmt, $this->meanLon->deg);
    $ϖ = sprintf($fmt, $this->lonPeri->deg);
    $ω = sprintf($fmt, $this->argPeri->deg);
    $Ω = sprintf($fmt, $this->node->deg);
    $M = sprintf($fmt, $this->mAnomaly->deg);
    $E = sprintf($fmt, $this->eAnomaly->deg);

    if ($this->bodyName)
      $title = "Orbital Elements of {$this->bodyName}\nEpoch {$this->epoch}";
    else
      $title = "Orbital Elements\nEpoch {$this->epoch}";

    return <<<ELEM

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
  }

}
