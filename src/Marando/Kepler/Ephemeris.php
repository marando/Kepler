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

use \Marando\Units\Angle;

class Ephemeris implements \ArrayAccess, \Iterator {

  public $lightTime;
  public $dateUTC       = null;
  public $radecIRCS     = null;
  public $radecApparent = null;
  public $distApparent  = null;
  public $distTrue      = null;

  /**
   *
   * @var Angle
   */
  public $diameter = null;

  /**
   *
   * @var static
   */
  protected $items = [];

  public function offsetExists($offset) {
    return key_exists($offset, $this->items);
  }

  public function offsetGet($offset) {
    return isset($this->items[$offset]) ? $this->items[$offset] : null;
  }

  public function offsetSet($offset, $value) {
    if (is_null($offset))
      $this->items[]        = $value;
    else
      $this->items[$offset] = $value;
  }

  public function offsetUnset($offset) {
    unset($this->items[$offset]);
  }

  public function __toString() {
    $i   = new static();
    $str = '';

    foreach ($this->items as $i) {
      $str .= "\n"
              . "$i->dateUTC "
              . "$i->radecIRCS "
      //. "$i->radecApparent\t"
      //. "$i->diameter\t"
      //. "$i->lightTime";
      ;
    }

    return $str;
  }

  protected $position = 0;

  public function current() {
    return $this->items[$this->position];
  }

  public function key() {
    return $this->position;
  }

  public function next() {
    ++$this->position;
  }

  public function rewind() {
    $this->position = 0;
  }

  public function valid() {
    return isset($this->items[$this->position]);
  }

}
