<?php

use \Marando\AstroDate\AstroDate;
use \Marando\AstroDate\Epoch;
use \Marando\Kepler\Comet;
use \Marando\Kepler\OrbitalElem;
use \Marando\Kepler\Planets\Earth;
use \Marando\Kepler\Planets\Mars;
use \Marando\Kepler\Planets\Mercury;
use \Marando\Kepler\Planets\Moon;
use \Marando\Kepler\Planets\Sun;
use \Marando\Units\Angle;
use \Marando\Units\Distance;
use \Marando\Units\Time;

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

class GenericTest extends PHPUnit_Framework_TestCase {

  public function test() {

    //$e = new Earth();
    //$e->dateRange('2015-01-01', '2016-01-01', '20 days');
    //echo $e->observe(new Mercury(), false);
    //echo $e->observe(new Mercury());
    //return;

    /**
     * loop fine tuning until K or c is acceptable
     */
// UT1 on ->toTDB() is slow
     $comet = Comet::find('C/2014 S2');

    $e = new Earth();
    $e->dates('2015-01-12', ' 2015-Sep-29');
    $e->ca(new Mars, $date, $dist);
echo "\n$date\t {$dist->au}";

    return;
    //echo "\n" . $e->date('2016-Feb-27 00:00:03.000 UTC')->observe($comet)[0]->distTrue->au;
    //echo "\n" . $e->dateRange('now', '2016-03-08', '15 days')->observe($comet);
    echo "\n\n" . Earth::closestApproach($comet, 'now', '2020-03-08', $date,
            $dist);
    echo "\n$date\t {$dist->au}";
    return;

    echo "\n\n" . Earth::closestApproach(new Moon, '2015-11-30', '2020-11-30',
            $date, $dist);
    echo "\n$date\t {$dist}";
    return;


    echo "\n\n" . AstroDate::jd(2457546.0315315);
    echo "\n" . 0.49610389062451;

    echo "\n";
    $e = new Earth();
    echo $e->date(2457546.0315315)->observe(new Mars)[0]->distTrue->au;
    return;

    echo $e->dateRange('now', '2018-11-30', '0.5 years')->observe(new Mars);

    echo "\n\n$date\t$dist\n";

    exit;

    $comet = Comet::find('C/2013 US10');
    echo $comet->orbitalElem;
    //echo $comet->orbitalElem;

    $earth = new Earth();
    //$earth->dates('2014-Dec-29', '2015-Jan-06', '2015-Jan-16', '2015-Jan-20',
    //        '2015-Jan-23');
    //$earth->dateRange(AstroDate::now('EST'), '2015-Dec-2', '1 day');
    //echo $comet->orbitalElem;
    //echo $earth->observe($comet);


    $earth->dateRange('2015-Nov-28', '2015-Dec-04', '1 day');
    echo $earth->observe($comet);




    return;

    //$earth->observe(new Mercury());
    //echo Comet::find('lovejoy')->orbitalElem->atEpoch(Epoch::J(2015.9));
    return;
    $earth = new Earth();
    $earth->dateRange(AstroDate::now('EST'), '2015-nov-29', '12 hours');
    $earth->observe(Comet::find('67P'));

    return;


    $earth = new Earth();
    $earth->date('now');
    $earth->observe(new Mercury);

    return;
    $earth = new Earth();
    $earth->dates(['2015-01-19 TT', '2015-01-23 TT']);
    $earth->observe(Comet::find('C/2014 Q2'));
    echo $o     = Comet::find('C/2014 Q2')->orbitalElem;

    $o->epoch = AstroDate::parse('2015-01-02 TT')->toEpoch();
    echo $o;


    $o->epoch = AstroDate::parse('2015-01-24 TT')->toEpoch();
    echo $o;

    //echo Comet::find('C/1980 E1')->orbitalElem;
    exit;

    $earth = new Earth();
    $earth->dateRange('2015-nov-20', '2015-nov-23', '1 day');
    $earth->observe(Comet::find('C/2015 W1'));




    return;
    $earth = new Earth();
    $earth->observe(Comet::find('C/2015 W1'));




    return;
    /*
      $epoch = Epoch::jd(2457346.500000000);
      $a     = Distance::au(0.3870979732041467);
      $e     = 0.2056309601529898;
      $i     = Angle::deg(7.004037120062203);
      $ϖ     = Angle::deg(77.4792716201);
      $Ω     = Angle::deg(4.831084721343618E+01);
      $L     = Angle::deg(233.9570580844);
      echo $m = OrbitalElem::planet($epoch, $a, $e, $i, $ϖ, $Ω, $L, 'Mercury');
     *
     */

    // Elliptic orbit
    echo $comet = Comet::find('Halley');

    // Near parabolic
    echo Comet::find('C/2014 Q2');

    // Parabolic
    echo Comet::find('C/2015 W1');

    // Hyperbolic
    echo Comet::find('C/2013 X1');

    //echo Comet::find('C/2013 US10');
    //$c->trueAnomaly;



    return;
    $epoch = AstroDate::jd(2457098.5)->toEpoch();
    $q     = Distance::au(1.290358507651428);
    $e     = .997777345958322;
    $i     = Angle::deg(80.30136442059589);
    $ω     = Angle::deg(12.39529160095434);
    $Ω     = Angle::deg(94.97528663580304);
    $T0    = AstroDate::parse('2015-01-30 TT')->add(Time::days(0.06945));

    echo OrbitalElem::comet($epoch, $q, $e, $i, $ω, $Ω, $T0);



    return;
    $earth = Earth::create(AstroDate::now());
    $earth->observe(new Mercury);

    $earth->observe(Comet::find('C/2014 Q2'));



    $comet = Comet::find('fds');
    $comet->observe(Comet::find('a'));

    $comet->observe(new Earth());
  }

}
