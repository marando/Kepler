<?php

namespace Marando\AstroCoord;

use \Marando\AstroDate\AstroDate;
use \Marando\AstroDate\TimeScale;
use \Marando\AstroDate\TimeStandard;
use \Marando\IAU\IAU;
use \Marando\IAU\iauASTROM;
use \Marando\JPLephem\DE\Reader;
use \Marando\JPLephem\DE\SSObj;
use \Marando\Kepler\Orbitals;
use \Marando\Kepler\Planets\Earth;
use \Marando\Kepler\Planets\Jupiter;
use \Marando\Kepler\Planets\Mars;
use \Marando\Kepler\Planets\Mercury;
use \Marando\Kepler\Planets\Moon;
use \Marando\Kepler\Planets\Neptune;
use \Marando\Kepler\Planets\Pluto;
use \Marando\Kepler\Planets\Saturn;
use \Marando\Kepler\Planets\Sun;
use \Marando\Kepler\Planets\Uranus;
use \Marando\Kepler\Planets\Venus;
use \Marando\Units\Angle;
use \Marando\Units\Distance;
use \Marando\Units\Time;
use \Marando\Units\Velocity;
use \PHPUnit_Framework_TestCase;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2015-11-03 at 01:14:00.
 */
class GenericTest extends PHPUnit_Framework_TestCase {

  public function testOrbitals() {

    //$elem = Mercury::orbitals(AstroDate::parse('1500-Nov-08 23:20:34'));

    //echo Mercury::orbitals();
    //echo $j = Orbitals::Jupiter(AstroDate::jd(2451545, TimeScale::TT()));

    echo Orbitals::planet(new Jupiter);

    //Mercury::orbitals(AstroDate::create(2900, 1, 1));
  }

  public function test() {

    return;



    // Mercury | 15 51 49.711 | -20 48 16.085 |  1.44960
    // Mercury | 15 51 49.711 | -20 48 16.085 |  1.44960

    echo Earth::planets(AstroDate::parse('2015-Nov-21 00:00:00 TT'));
    return;

    $e   = new Earth();
    $now = AstroDate::now('EST');
    echo "\n" . $now->sidereal('a', Angle::deg(-82));

    //echo $e->planetary($now);
//exit;


    $now = AstroDate::now('EST');
    $e->dateRange($now, $now->copy()->add(Time::days(1)), '4 hours');


    //$e->dateRange('1990-nov-01', '2040-nov-01', '365.25 days');
    //$e->dateRange(AstroDate::now(), AstroDate::now()->add(Time::days(365 * 2)),'5 days');
    $e->topo('27.9681 N', '82.4764 W');

    echo $ephem = $e->observe(new Mars);
    return;

/////add thing to show solar time or like:
////////Day / Twilights / astronom / civil etc...
    // echo $ephem;
    //return;
    $startB = microtime(true);
    foreach ($ephem as $e)
      echo "\n"
      //. $ephem->eclip."\t"
      . $e->date->setTimezone('est')->format('Y-m-d h:i:s A T') . "\t"
      //. "$e->target\t"
      //. "{$e->date->year}-{$e->date->monthName(false)}-{$e->date->day}\t"
      . "$e->altaz\t"
      . $e->dist->setUnit('km') . "\t"
      //. "$e->radecApparent\t"
      // . "{$e->dist->au}\t"
      //. "{$e->radecApparent->format(Equat::FORMAT_DEFAULT_NF)}\t"
//. "$e->radec\t"
      //. "$e->phase\t"
      //. "$e->illum\t"
      //. "$e->diam\t"
//. "$e->center\t"
      //. "$e->location\t"
      //. "$e->radecApparent"
      ;
    //. "{$e->dateUTC->year}-{$e->dateUTC->month}-{$e->dateUTC->day}\t"
    //. "$e->altaz\t"
    //. "{$e->diameter->arcsec}";

    echo "\nephem calc->" . (microtime(true) - $startB);
    return;
    foreach ($ephem as $e) {
      echo "\n\n";
      echo "\n" . $e->radecAstrom;
      echo "\n" . $e->radecApparent;
      echo "\n" . $e->eclipAstrom->lon->deg;
      echo "\n" . $e->eclipAstrom->lat->deg;
      echo "\n" . $e->dateUTC;
      echo "\n" . $e->distTrue;
      echo "\n" . $e->dist;
      echo "\n" . $e->altaz;
    }








    return;
    $e     = new Earth();
    $date1 = AstroDate::now();
    $date2 = $date1->copy()->add(Time::days(365 * 2));
    $e->dateRange($date1, $date2, Time::days(60));




    // retruns ephemeris object has ALL the suff
    /* $ephem = $e->ephem();
      foreach ($ephem as $e) {
      /**
     * RA/Dec ICRF/J2000.0 astrom
     * RA/Dec Apparent
     * Alt/Az Topographic
     * Angular Diameter
     * True distance
     * Apparent distance
     * light travel time
     * cartesian
     * true cartesian
     * ...
     */
    // example
    //echo "\n$e->date\t$e->radec\t$e->horiz\t$e->diam"
    // }
    // therefore should $e->observe($obj) and such
    // just return the first date? yes maybe?
    // or maybe it shouldnt even exist?
    // OR........... ->observe() should be the ephemeris function?
    // I mean if you want ->position ->observe etc.. kinda redundant
    // and you can still get it from Ephem obj
    // or you can just use DEReader






    $pv   = $e->observe(new Mars);
    $diam = $e->diam(new Mars);

    for ($i = 0; $i < count($pv); $i++)
      echo "\n{$pv[$i]->apparent()}\t$diam[$i]";



    return;
    $e = new Earth();
    //$date = AstroDate::parse('2019-Jul-02 7:26:00', TimeStandard::TT());
    //$e->date($date);
    $e->date(AstroDate::now());
    $e->topo(Geo::deg(27, -82));

    $planets = [
        new Sun,
        new Moon,
        new Mercury,
        new Venus,
        new Mars,
        new Jupiter,
        new Saturn,
        new Uranus,
        new Neptune,
        new Pluto
    ];

    foreach ($planets as $p) {
      $eq = $e->observe($p)[0];

      //echo "\n$p->id\t{$eq->apparent()}\t$eq->dist";

      $h = $e->observe($p)[0]->toHoriz();
      echo "\n$p->id\t$h Dist $eq->dist\t" . $e->diam($p);
    }
//cache stuff. set raw results and clear when date chsnges so assumed valid if there
    return;

    $e = new Earth();
    $e->date(AstroDate::now());
    $e->topo(Geo::deg(27, -82));
    echo "\n\n";
    echo "\n" . AstroDate::now();
    echo "\n" . $e->observe(new Sun)[0];
    echo "\n" . $e->observe(new Sun)[0]->apparent();
    echo "\n" . $e->observe(new Sun)[0]->toHoriz();


    return;



    $e = new Earth();
    //$e->dates([AstroDate::now(), AstroDate::now()]);

    $dt1  = AstroDate::now();
    $dt2  = $dt1->copy()->add(Time::days(1));
    $step = Time::hours(1);
    $e->dateRange($dt1, $dt2, $step);

    $start = microtime(true);
    $pv    = $e->observe(new Mercury);

    foreach ($pv as $p)
      echo "\n{$p->obsEpoch->toDate()->jd}\t" . $p->toHoriz();
    echo "\nSec> " . $time_elapsed_secs = microtime(true) - $start . "\n";

    echo "\n\n";

    return;
    $r = new Reader();

    $start = microtime(true);
    for ($jd = 2457335.4168467; $jd < 2457420.4168467; $jd += 1) {

      $pv1 = $r->jde($jd)->observe(SSObj::Mercury(), SSObj::Earth());

      $frame = Frame::ICRF();
      $epoch = AstroDate::jd($jd, TimeStandard::TDB())->toEpoch();
      $x     = Distance::au($pv1[0]);
      $y     = Distance::au($pv1[1]);
      $z     = Distance::au($pv1[2]);
      $vx    = Velocity::aud($pv1[3]);
      $vy    = Velocity::aud($pv1[4]);
      $vz    = Velocity::aud($pv1[5]);
      $c     = new Cartesian($frame, $epoch, $x, $y, $z, $vx, $vy, $vz);
      echo "\n{$jd}\t" . $c->toEquat();
    }
    echo "\nSec> " . $time_elapsed_secs = microtime(true) - $start . "\n";

    return;

    ///////
    ///////
    //////////////

    $jd1  = AstroDate::now()->jd;
    $jd2  = AstroDate::now()->add(Time::days(1))->jd;
    $step = 0.05;
    // todo cache objects to make this more efficient, date array maybe?
    for ($jd = $jd1; $jd < $jd2; $jd += $step) {
      $dt  = AstroDate::jd($jd);
      $geo = Geo::deg(28, -82);

      $radec = Earth::topo($dt, $geo)->observe(new Uranus)->apparent();
      $altaz = Earth::topo($dt, $geo)->observe(new Uranus)->toHoriz();
      echo "\n{$dt}\t{$radec->ra->toAngle()->deg}";
    }




    return;



    $date = AstroDate::parse('2015-Mar-20 00:00:00.000');

    echo "\n\n" . Earth::at($date)->position();
    echo "\n\n" . Earth::at($date)->position(new Mercury);
    echo "\n\n" . Earth::at($date)->observe(new Mercury);
    echo "\n" . Earth::at($date)->observe(new Mercury)->apparent();
    echo "\n" . Earth::at($date)->observe(new Mercury)->apparent()->toHoriz();
    echo "\n" . Earth::at($date)->observe(new Mercury)->toHoriz();

    $geo = Geo::deg(27, -82);
    echo "\n\n" . Earth::topo($date, $geo)->observe(new Mercury);
    echo "\n" . Earth::topo($date, $geo)->observe(new Mercury)->apparent();
    echo "\n" . Earth::topo($date, $geo)->observe(new Mercury)->apparent()->toHoriz();
    echo "\n" . Earth::topo($date, $geo)->observe(new Mercury)->toHoriz();



    return;
    /*


      $jd1  = AstroDate::parse('2015-11-01')->jd;
      $jd2  = AstroDate::parse('2015-11-02')->jd;
      $step = 0.1;

      for ($jd = $jd1; $jd < $jd2; $jd += $step) {
      $date = AstroDate::jd($jd);
      $eq = Earth::at($date)->observe(new Uranus)->toHoriz();

      echo "\n$date->hour\t{$eq->alt->deg}\t{$eq->az->deg}";
      //echo "\n$date->year\t" . Earth::at($date)->observe(new Mercury);
      }

     */



    $date = AstroDate::parse('2015-Mar-20 22:00:00');





    $jd1  = AstroDate::parse('1900-01-01')->jd;
    $jd2  = AstroDate::parse('2100-01-01')->jd;
    $step = 3652.5;

    for ($jd = $jd1; $jd < $jd2; $jd += $step) {
      $date = AstroDate::jd($jd);
      $e    = Earth::at($date)->apparent(new Moon);
      $e1   = Earth::topo($date, Geo::deg(27, -82))->apparent(new Moon);
      echo "\n\t{$e->ra->toAngle()->deg}\t{$e->dec->deg}";
      echo "\n\t{$e1->ra->toAngle()->deg}\t{$e->dec->deg}\n";
    }




    return;
    echo "\n\n" . $date . "\n";

    $earth = Earth::at($date);
    //echo "\n" . $earth->position(new Pluto);
    echo "\n" . $earth->observe(new Pluto);
    echo "\n" . $earth->apparent(new Pluto);

    $tampa = Earth::topo($date, Geo::deg(27, -82));
    //echo "\n" . $tampa->position(new Pluto);
    echo "\n" . $tampa->observe(new Pluto);
    echo "\n" . $tampa->apparent(new Pluto);











    return;
    // Geocentric
    $p2 = Earth::at($date)->observe(new Pluto);

    // Topographic
    $tampa = Earth::topo($date, Geo::deg(27, -82));
    $p1    = $tampa->observe(new Pluto);

    echo "\n$p1\n$p2\n";

    // Geocentric
    $p2 = Earth::at($date)->observe(new Pluto)->apparent();

    // Topographic
    $tampa = Earth::topo($date, Geo::deg(27, -82));
    $p1    = $tampa->observe(new Pluto)->apparent();

    echo "\n$p1\n$p2\n";


    // Geocentric
    $p2 = Earth::at($date)->position(new Pluto);

    // Topographic
    $tampa = Earth::topo($date, Geo::deg(27, -82));
    $p1    = $tampa->position(new Pluto);

    echo "\n$p1\n$p2\n";







    return;
    echo "\n" . Earth::at($date)->position();
    echo "\n\n" . Earth::at($date)->position(new Mercury);
    echo "\n\n" . Earth::at($date)->observe(new Mercury);
    echo "\n\n" . Earth::at($date)->observe(new Mercury);
    echo "\n\n" . Earth::at($date)->observe(new Mercury)->toHoriz(Geo::deg(27,
                    -82));

    // $tampa = Earth::topo(Geo::deg(27, -82));
    //echo $p     = $tampa->observe(new Pluto);
    //$tampa = Earth::at($date)




    return;
    $tampa = Earth::at($date)->topo(Geo::deg(27, -82));






    $centr = Earth::at($date);
    $tampa = Earth::at($date)->topo($geo);
  }

}
