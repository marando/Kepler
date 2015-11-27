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

namespace Marando\Kepler\Data\JPL;

use \Marando\AstroDate\AstroDate;
use \Marando\AstroDate\Epoch;
use \Marando\Kepler\OrbitalElem;
use \Marando\Units\Angle;
use \Marando\Units\Distance;
use \Marando\Units\Time;
use \SplFileObject;

class SmallBodyDB {

  const UPDATE_INTVL_H = 6;
  const STORAGE_DIR    = 'jpl-sb-data';
  const FILES          = [
      'comet.dat'  => 'http://ssd.jpl.nasa.gov/dat/ELEMENTS.COMET',
      'asteroid_n' => 'http://ssd.jpl.nasa.gov/dat/ELEMENTS.NUMBR.gz',
      'asteroid_u' => 'http://ssd.jpl.nasa.gov/dat/ELEMENTS.UNNUM.gz',
  ];

  public function __construct() {
    $this->update();
  }

  public static function comet($name) {
    $comet = new static();
    return $comet->searchComet($name);
  }

  public static function asteroid($name) {
    // search name or number
  }

  protected function update() {
    // Check if within update hourly threshold
    $d = $this->hoursSinceUpdate();
    if ($this->hoursSinceUpdate() < static::UPDATE_INTVL_H)
      return;

    // Check if update is available
    $this->log("Checking for update...");
    if ($this->lastPublished()->diff($this->lastUpdated())->hours < 0) {
      foreach (static::FILES as $local => $remote) {
        $this->log("Downloading {$remote}");
        $this->copyfile_chunked($remote, $this->storage($local));

        if (strstr($remote, '.gz')) {
          $this->log("Extracting {$remote}");
          $this->decompress($this->storage($local), "{$local}.decomp");

          unlink($this->storage($local));
          rename("{$local}.decomp", $this->storage($local));
        }
      }

      $this->setUpdatedNow();
      $this->log("Done updating.");
    }
    else {
      $this->setUpdatedNow();
      $this->log("No update required.");
    }
  }

  protected function decompress($in, $out = null) {
    // Raising this value may increase performance
    $buffer_size   = 4096; // read 4kb at a time
    $out_file_name = $out ? $out : str_replace('.gz', '', $in);

    // Open our files (in binary mode)
    $file     = gzopen($in, 'rb');
    $out_file = fopen($out_file_name, 'wb');

    // Keep repeating until the end of the input file
    while (!gzeof($file)) {
      // Read buffer-size bytes
      // Both fwrite and gzread and binary-safe
      fwrite($out_file, gzread($file, $buffer_size));
    }
    // Files are done, close files
    fclose($out_file);
    gzclose($file);

    $out = $out_file_name;
  }

  protected function searchComet($name) {
    $file = new SplFileObject($this->storage('comet.dat'));
    $file->seek(1);

    while ($file->valid()) {
      $line  = $file->getCurrentLine();
      $cname = trim(substr($line, 0, 45));

      if (strstr(strtolower($cname), strtolower($name))) {
        // Comet found, grab JPL data
        $epoch = Epoch::jd((float)substr($line, 46, 5) + AstroDate::MJD);
        $q     = Distance::au((float)substr($line, 53, 10));
        $e     = (float)substr($line, 64, 10);
        $i     = Angle::deg((float)substr($line, 75, 9));
        $ω     = Angle::deg((float)substr($line, 85, 9));
        $Ω     = Angle::deg((float)substr($line, 95, 9));

        $tp   = substr($line, 105, 14);
        $tpY  = substr($tp, 0, 4);
        $tpM  = substr($tp, 4, 2);
        $tpD  = substr($tp, 6, 2 - 6);
        $tpDF = substr($tp, 8, strlen($tp) - 8);
        $T0   = AstroDate::parse("{$tpY}-{$tpM}-$tpD")->add(Time::days($tpDF));

        return OrbitalElem::comet($epoch, $q, $e, $i, $ω, $Ω, $T0, $cname);
      }
    }

  }

  /**
   * Logs activity, used for recording remote file updates
   * @param string $data
   */
  protected function log($data) {
    $data = date(DATE_RSS, time()) . "\t$data\n";
    file_put_contents($this->storage('.log'), $data, FILE_APPEND);
  }

  protected function lastUpdated() {
    $file = $this->storage('.updated');

    if (!file_exists($file))
      file_put_contents($file, 0);

    $jd = (file_get_contents($file) / 86400.0) + 2440587.5;
    return AstroDate::jd($jd);
  }

  /**
   * Saves to disk the last remote file update timestamp using the current time
   */
  protected function setUpdatedNow() {
    file_put_contents($this->storage('.updated'), time());
  }

  protected function lastPublished() {
    $f = file_get_contents('http://ssd.jpl.nasa.gov/?sb_elem');
    $p = '/.*The files above were updated on \n*\s*([0-9]{4})-'
            . '([a-zA-Z]{3})-([0-9]{1,2})\s([0-9]{2}):([0-9]{2})\s-([0-9]{4})/';

    if (preg_match($p, $f, $dt)) {
      $y   = $dt[1];
      $m   = $dt[2];
      $d   = $dt[3];
      $h   = $dt[4];
      $min = $dt[5];
      $utc = $dt[6];

      return AstroDate::parse("$y-$m-$d  $h:$min UT-$utc");
    }

    return null;
  }

  /**
   * Returns a file from local storage, and creates the directory in the event
   * that it does not exist
   *
   * @param  string $file Filename
   * @return string       Full relative path to the file
   */
  protected function storage($file = null) {
    $folder = static::STORAGE_DIR;
    $path   = __DIR__ . "/../../../../../$folder";

    if (!file_exists($path))
      mkdir($path);

    if (!file_exists("$path/.gitignore"))
      file_put_contents("$path/.gitignore", '*');

    return $file ? "$path/$file" : "$path";
  }

  /**
   * Copy remote file over HTTP one small chunk at a time.
   *
   * @param $infile The full URL to the remote file
   * @param $outfile The path where to save the file
   */
  function copyfile_chunked($infile, $outfile) {
    $chunksize = 10 * (1024 * 1024); // 10 Megs

    /**
     * parse_url breaks a part a URL into it's parts, i.e. host, path,
     * query string, etc.
     */
    $parts    = parse_url($infile);
    $i_handle = fsockopen($parts['host'], 80, $errstr, $errcode, 5);
    $o_handle = fopen($outfile, 'wb');

    if ($i_handle == false || $o_handle == false) {
      return false;
    }

    if (!empty($parts['query'])) {
      $parts['path'] .= '?' . $parts['query'];
    }

    /**
     * Send the request to the server for the file
     */
    $request = "GET {$parts['path']} HTTP/1.1\r\n";
    $request .= "Host: {$parts['host']}\r\n";
    $request .= "User-Agent: Mozilla/5.0\r\n";
    $request .= "Keep-Alive: 115\r\n";
    $request .= "Connection: keep-alive\r\n\r\n";
    fwrite($i_handle, $request);

    /**
     * Now read the headers from the remote server. We'll need
     * to get the content length.
     */
    $headers = array();
    while (!feof($i_handle)) {
      $line      = fgets($i_handle);
      if ($line == "\r\n")
        break;
      $headers[] = $line;
    }

    /**
     * Look for the Content-Length header, and get the size
     * of the remote file.
     */
    $length = 0;
    foreach ($headers as $header) {
      if (stripos($header, 'Content-Length:') === 0) {
        $length = (int)str_replace('Content-Length: ', '', $header);
        break;
      }
    }



    /**
     * Start reading in the remote file, and writing it to the
     * local file one chunk at a time.
     */
    $cnt = 0;
    while (!feof($i_handle)) {
      $buf   = '';
      $buf   = fread($i_handle, $chunksize);
      $bytes = fwrite($o_handle, $buf);
      if ($bytes == false) {
        return false;
      }
      $cnt += $bytes;

      /**
       * We're done reading when we've reached the conent length
       */
      if ($cnt >= $length)
        break;
    }

    fclose($i_handle);
    fclose($o_handle);
    return $cnt;
  }

  /**
   * Returns the number of hours since last updating the local data
   * @return float
   */
  protected function hoursSinceUpdate() {
    return $this->lastUpdated()->diff(AstroDate::now())->hours;
  }

}
