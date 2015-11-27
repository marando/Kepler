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

namespace Marando\Kepler\Util;

use \Marando\AstroCoord\Cartesian;
use \Marando\AstroCoord\Frame;
use \Marando\AstroDate\AstroDate;
use \Marando\IAU\IAU;
use \Marando\JPLephem\DE\Reader;
use \Marando\JPLephem\DE\SSObj;
use \Marando\Units\Angle;
use \Marando\Units\Distance;
use \Marando\Units\Time;
use \SebastianBergmann\RecursionContext\Exception;

class Util {

  public static function pv2c(array $pv, AstroDate $dt) {
    $x = Distance::au($pv[0]);
    $y = Distance::au($pv[1]);
    $z = Distance::au($pv[2]);

    return $c = new Cartesian(Frame::ICRF(), $dt->toEpoch(), $x, $y, $z);
  }

  public static function xyzsun(Reader $reader, AstroDate $date) {
    $jd = $date->toTDB()->toJD();
    return static::pv2c($reader->jde($jd)->position(SSObj::Sun()), $date);
  }

  public static function trueObli(AstroDate $date) {
    $jdTT = $date->toTT()->toJD();

    IAU::Nut06a($jdTT, 0, $dpsi, $deps);
    $obli = IAU::Obl06($jdTT, 0) + $deps;

    return Angle::rad($obli);
  }

  public static function parseAstroDate($date) {
    if (strtolower($date) == 'now')
      return AstroDate::now ();

    // AstroDate instance
    if ($date instanceof AstroDate)
      return $date;

    // Try parsing Julian day count
    if (is_numeric($date))
      return AstroDate::jd($date);

    // Try parsing string date representaation
    if (is_string($date))
      return AstroDate::parse($date);

    throw new Exception("Unable to parse date {$date}");
  }

  public static function parseTime($time) {
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

}
