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

use \ArrayAccess;
use \Countable;
use \InvalidArgumentException;
use \Iterator;
use \Marando\AstroDate\AstroDate;

class Ephemeris implements ArrayAccess, Countable, Iterator {
  //----------------------------------------------------------------------------
  // Properties
  //----------------------------------------------------------------------------

  /**
   * ArrayAccess item container
   * @var EphemItem[]
   */
  protected $items = [];

  /**
   * Iterator position
   * @var int
   */
  protected $position = 0;

  //----------------------------------------------------------------------------
  // Functions
  //----------------------------------------------------------------------------

  public function __toString() {
    $topo   = $this->items[0]->centerTopo ? "({$this->items[0]->centerTopo})" : '';
    $header = <<<HEADER

=========================================================================
Target: {$this->items[0]->targetName}
Center: {$this->items[0]->centerName} {$topo}
-------------------------------------------------------------------------
Date/Time                | Apparent RA  | Apparent Decl | Distance
-------------------------------------------------------------------------
HEADER;

    $body = '';
    foreach ($this->items as $item) {
      $date = $item->date->format('Y-M-d h:i:s T');
      $ra   = $item->radecAppar->format('Rh Rm Rs.Ru');
      $dec  = $item->radecAppar->format('+Dd Dm Ds.Du');
      $dist = $item->distAppar;

      if ($dist->au < 0.05)
        $dist->setUnit('km')->round(0);

      $body .= "\n" . "{$date} | {$ra} | {$dec} | {$dist}";
    }

    return "{$header}{$body}\n" . str_repeat('=', 73);
  }

  // // // Interface

  public function count() {
    return count($this->items);
  }

  public function current() {
    return $this->items[$this->position];
  }

  public function key() {
    return $this->position;
  }

  public function next() {
    ++$this->position;
  }

  public function offsetExists($offset) {
    return key_exists($offset, $this->items);
  }

  public function offsetGet($offset) {
    return isset($this->items[$offset]) ? $this->items[$offset] : null;
  }

  public function offsetSet($offset, $value) {
    if ($value instanceof EphemItem)
      if (is_null($offset))
        $this->items[]        = $value;
      else
        $this->items[$offset] = $value;
    else
      throw new InvalidArgumentException("Item must be EphemItem instance");
  }

  public function offsetUnset($offset) {
    unset($this->items[$offset]);
  }

  public function rewind() {
    $this->position = 0;
  }

  public function valid() {
    return isset($this->items[$this->position]);
  }

}
