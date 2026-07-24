<?php

/*******************************************************
               Grid Reference Utilities
Version 3.0 - Written by Mark Wilton-Jones 1-10/4/2010
Updated 6/5/2010 to allow methods to return text
strings and add support for ITM, UTM and UPS
Updated 9/5/2010 to add dd_format
Updated 16/9/2020 to correct Helmert height handling
Updated 19/10/2020 to correct DD detection in dms_to_dd
Updated 23/12/2020 to add support for shift grids,
geoids, polynomial transformation, geodesics, trucated
grid references, floating point grid references, high
precision GPS coordinates, and more input formats
********************************************************

Please see http://www.howtocreate.co.uk/php/ for details
Please see http://www.howtocreate.co.uk/php/gridref.php for demos and instructions
Please see http://www.howtocreate.co.uk/php/gridrefapi.php for API documentation
Please see http://www.howtocreate.co.uk/jslibs/termsOfUse.html for terms and conditions of use

Provides functions to convert between different NGR formats and latitude/longitude formats.

This script contains the coefficients for OSi/LPS Polynomial Transformation. Coefficients are
available under BSD License: (c) Copyright and database rights Ordnance Survey Limited 2016,
(c) Crown copyright and and database rights Land & Property Services 2016 and/or (c) Ordnance
Survey Ireland, 2016. All rights reserved.

_______________________________________________________________________________________________*/

//use a class to keep all of the method/property clutter out of the global scope
class Grid_Ref_Utils {

	//class instantiation forcing use as a singleton - state can also be stored if needed (or can use separate instances)
	//could be done as an abstract class with static methods, but would still need instantiation to prepare UK_grid_numbers
	//(as methods/properties do not exist during initial class constructor parsing), and would restrict future flexibility
	private static $only_instance;
	public static function toolbox() {
		if( !isset( self::$only_instance ) ) {
			self::$only_instance = new Grid_Ref_Utils();
		}
		return self::$only_instance;
	}
	private function __construct() {
		$this->UK_grid_numbers = $this->grid_to_hashmap($this->UK_grid_squares);
		$this->TEXT_EUROPEAN = html_entity_decode( '&deg;', ENT_QUOTES, 'ISO-8859-1' );
		$this->TEXT_UNICODE = html_entity_decode( '&deg;', ENT_QUOTES, 'UTF-8' );
		$this->TEXT_ASIAN = chr(129).chr(139); //x818b, Shift_JIS html_entity_decode fails
		$this->TEXT_DEFAULT = html_entity_decode( '&deg;', ENT_QUOTES );
		if( !defined('PHP_FLOAT_EPSILON') ) {
			//used by geodesy functions to test for accuracy limits
			$epsilon = 1.0;
			do {
				$epsilon /= 2.0;
			} while( 1.0 + ( $epsilon / 2.0 ) != 1.0 );
			define( 'PHP_FLOAT_EPSILON', $epsilon );
		}
	}

	//define return types
	public $DATA_ARRAY = false;
	public $HTML = true;
	//will be set up when the object is constructed
	public $TEXT_EUROPEAN;
	public $TEXT_UNICODE;
	public $TEXT_ASIAN;
	public $TEXT_DEFAULT;

	//common return values
	private function generic_xy_coords( $return_type, $precise, $easting, $northing, $height = null, $additional = null ) {
		//avoid stupid -0 by adding 0
		if( $return_type ) {
			if( is_nan($easting) || is_nan($northing) ) {
				return 'OUT_OF_GRID';
			} elseif( $precise ) {
				return sprintf( '%F', $easting ) . ',' . sprintf( '%F', $northing ) . ( is_numeric($height) ? ( ',' . sprintf( '%F', $height ) ) : '' );
			} else {
				return ( round($easting) + 0 ) . ',' . ( round($northing) + 0 ) . ( is_numeric($height) ? ( ',' . ( round($height) + 0 ) ) : '' );
			}
		} else {
			$returnarray = array( $easting + 0, $northing + 0 );
			if( is_numeric($height) ) {
				$returnarray[] = $height + 0;
			}
			if( $additional !== null ) {
				$returnarray[] = $additional;
			}
			return $returnarray;
		}
	}
	private function generic_lat_long_coords( $return_type, $latitude, $longitude, $height = null ) {
		//force the longitude between -180 and 180
		$longitude = $this->wrap_around_longitude($longitude);
		if( $return_type ) {
			//sprintf to produce simple numbers instead of scientific notation
			//10 decimal places DD ~= 8 decimal places DM ~= 6 decimal places DMS
			$deg = is_string($return_type) ? $return_type : '&deg;';
			$longitude = sprintf( '%.10F', $longitude );
			if( $longitude == '-180.0000000000' ) {
				//rounding can make -179.9...9 end up as -180
				$longitude = '180.0000000000';
			}
			return sprintf( '%.10F', $latitude ) . $deg . ', ' . $longitude . $deg . ( is_numeric($height) ? ( ', ' . sprintf( '%F', $height ) ) : '' );
		} else {
			//avoid stupid -0 by adding 0
			$temparray = array( $latitude + 0, $longitude + 0 );
			if( is_numeric($height) ) {
				$temparray[] = $height + 0;
			}
			return $temparray;
		}
	}

	//common inputs
	function expand_params( $ar1, $from, $to, $ar2 ) {
		//PHP doesn't support overloaded functions/methods
		//this function makes it possible to extract an initial array, returning parameters in a standard order
		$param = 0;
		$handback = array();
		if( is_array($ar1) ) {
			foreach( $ar1 as $onecell ) {
				if( $param == $to ) {
					//ignore extra cells
					break;
				}
				$handback[$param] = $onecell;
				$param++;
			}
			//this forces attempts to use an array of one item to then continue without the next param(s)
			while( $param < $from ) {
				//prefill with null so that PHP doesn't get upset about missing cells
				$handback[$param] = null;
				$param++;
			}
		} else {
			//not using an array
			$handback[0] = $ar1;
			$param = 1;
		}
		foreach( $ar2 as $onecell ) {
			if( $param < $to && is_array($onecell) ) {
				//the first parameter that is an array must at least be at the "to" index
				//this allows "height" to be put either inside or outside an array, and still be optional
				//additional parameters in between are preserved and the calling function will continue with them
				while( $param < $to ) {
					$handback[$param] = null;
					$param++;
				}
				$param = $to;
			}
			$handback[$param] = $onecell;
			$param++;
		}
		return $handback;
	}

	//flooring to a number of significate figures
	private function floor_precision( $num, $precision ) {
		//regular floor does not support the precision param
		//usual method floor( $number * pow(10, $precision) ) / pow(10, $precision) gets floating point errors with ( 9.7, 2 )
		//string manipulation fails with 1E-15
		//this function is immune to both problems
		$rounded = round( $num, $precision );
		if( $rounded > $num ) {
			//pow works during testing for negative integer exponents, but perhaps it will fail on some systems, since JS does
			//$rounded -= pow( 10, -1 * $precision );
			//this approach always works
			$rounded -= '1E' . ( -1 * $precision );
		}
		return $rounded + 0;
	}
	//longitude must be within -180<num<=180
	private function wrap_around_longitude( $num, $negative_half = false ) {
		if( !is_numeric($num) ) {
			return $num;
		}
		if( abs($num) > 1440 ) {
			//this can end up with floating point errors much more easily than simple + or -
			//use it only in cases where the error is very large, to avoid the loops taking too long
			$num -= 360 * floor( $num / 360 );
		}
		while( ( !$negative_half && $num > 180 ) || ( $negative_half && $num >= 180 ) ) {
			$newnum = $num - 360;
			if( $newnum == $num ) {
				//avoid infinite loops with numbers high enough to need scientific notation
				return $num;
			}
			$num = $newnum;
		}
		while( ( !$negative_half && $num <= -180 ) || ( $negative_half && $num < -180 ) ) {
			$newnum = $num + 360;
			if( $newnum == $num ) {
				//avoid infinite loops with numbers high enough to need scientific notation
				return $num;
			}
			$num = $newnum;
		}
		return $num;
	}
	//azimuth must be within 0<=num<360
	private function wrap_around_azimuth($num) {
		if( !is_numeric($num) ) {
			return $num;
		}
		if( abs($num) > 1440 ) {
			//this can end up with floating point errors much more easily than simple + or -
			//use it only in cases where the error is very large, to avoid the loops taking too long
			$num -= 360 * floor( $num / 360 );
		}
		while( $num >= 360 ) {
			$newnum = $num - 360;
			if( $newnum == $num ) {
				//avoid infinite loops with numbers high enough to need scientific notation
				return $num;
			}
			$num = $newnum;
		}
		while( $num < 0 ) {
			$newnum = $num + 360;
			if( $newnum == $num ) {
				//avoid infinite loops with numbers high enough to need scientific notation
				return $num;
			}
			$num = $newnum;
		}
		return $num;
	}

	//character grids used by map systems
	private function grid_to_hashmap($grid_2d_array) {
		//make a hashmap of arrays giving the x,y values for grid letters
		$hashmap = array();
		foreach( $grid_2d_array as $grid_row_index => $grid_row_array ) {
			foreach( $grid_row_array as $grid_col_index => $grid_letter ) {
				$hashmap[$grid_letter] = array($grid_col_index,$grid_row_index);
			}
		}
		return $hashmap;
	}
	private $UK_grid_squares = array(
		//the order of grid square letters in the UK NGR system - note that in the array they start from the bottom, like grid references
		//there is no I in team
		array('V','W','X','Y','Z'),
		array('Q','R','S','T','U'),
		array('L','M','N','O','P'),
		array('F','G','H','J','K'),
		array('A','B','C','D','E')
	);
	private $UK_grid_numbers; //will be set up when the object is constructed

	//conversions between 12345,67890 and SS234789 grid reference formats
	public function get_UK_grid_ref( $orig_x, $orig_y = false, $figures = false, $return_type = false, $deny_bad_reference = false, $use_rounding = false, $is_irish = false ) {
		//no need to shuffle the $is_irish parameter over, since it will always be at the end
		list( $orig_x, $orig_y, $figures, $return_type, $deny_bad_reference, $use_rounding ) =
			$this->expand_params( $orig_x, 2, 2, array( $orig_y, $figures, $return_type, $deny_bad_reference, $use_rounding ) );
		if( is_bool($figures) || $figures === null ) {
			$figures = 4;
		} else {
			//enforce integer 1-25
			$figures = max( min( floor( $figures ), 25 ), 1 );
		}
		//prepare factors used for enforcing a number of grid ref figures
		$insig_figures = $figures - 5;
		if( $use_rounding ) {
			//round off unwanted detail
			$x = round( $orig_x, $insig_figures );
			$y = round( $orig_y, $insig_figures );
		} else {
			$x = $this->floor_precision( $orig_x, $insig_figures );
			$y = $this->floor_precision( $orig_y, $insig_figures );
		}
		$errorletters = 'OUT_OF_GRID';
		if( $is_irish ) {
			//the Irish grid system uses the same letter layout as the UK system, but it only has one letter, with origin at V
			$arY = floor( $y / 100000 );
			$arX = floor( $x / 100000 );
			if( $arX < 0 || $arX > 4 || $arY < 0 || $arY > 4 ) {
				//out of grid
				if( $deny_bad_reference ) {
					return false;
				}
				$letters = $errorletters;
			} else {
				$letters = $this->UK_grid_squares[ $arY ][ $arX ];
			}
		} else {
			//origin is at SV, not VV - offset it to VV instead
			$x += 1000000;
			$y += 500000;
			$ar1Y = floor( $y / 500000 );
			$ar1X = floor( $x / 500000 );
			$ar2Y = floor( ( $y % 500000 ) / 100000 );
			$ar2X = floor( ( $x % 500000 ) / 100000 );
			if( is_nan($ar1X) || is_nan($ar1Y) || is_nan($ar2X) || is_nan($ar2Y) || $ar1X < 0 || $ar1X > 4 || $ar1Y < 0 || $ar1Y > 4 ) {
				//out of grid - don't need to test $ar2Y and $ar2X since they cannot be more than 4, and ar1_ will also be less than 0 if ar2_ is
				if( $deny_bad_reference ) {
					return false;
				}
				$letters = $errorletters;
			} else {
				//first grid letter is for the 500km x 500km squares
				$letters = $this->UK_grid_squares[ $ar1Y ][ $ar1X ];
				//second grid letter is for the 100km x 100km squares
				$letters .= $this->UK_grid_squares[ $ar2Y ][ $ar2X ];
			}
		}
		//% casts to integer, so need to use floor instead
		//$x %= 100000;
		//$y %= 100000;
		//floating point errors can reappear here if using numbers after the decimal point
		//this approach also makes negative become positive remainder, but that is wanted anyway
		$x -= floor( $x / 100000 ) * 100000;
		$y -= floor( $y / 100000 ) * 100000;
		//avoid stupid -0 by adding 0
		if( $figures <= 5 ) {
			$figure_factor = pow( 10, 5 - $figures );
			$x = str_pad( ( ( $x % 100000 ) / $figure_factor ) + 0, $figures, '0', STR_PAD_LEFT );
			$y = str_pad( ( ( $y % 100000 ) / $figure_factor ) + 0, $figures, '0', STR_PAD_LEFT );
		} else {
			//pad only the left side of the decimal point
			//use sprintf to remove floating point errors introduced by -= and avoid stupid -0
			$x = explode( '.', sprintf( '%.'.$insig_figures.'F', $x ) );
			$x[0] = str_pad( $x[0], 5, '0', STR_PAD_LEFT );
			$x = implode( '.', $x );
			$y = explode( '.', sprintf( '%.'.$insig_figures.'F', $y ) );
			$y[0] = str_pad( $y[0], 5, '0', STR_PAD_LEFT );
			$y = implode( '.', $y );
		}
		if( $return_type && is_string($return_type) ) {
			return $letters . ' ' . $x . ' ' . $y;
		} elseif( $return_type ) {
			return '<var class="grid">' . $letters . '</var><var>' . $x . '</var><var>' . $y . '</var>';
		} else {
			return array( $letters, $x, $y );
		}
	}
	public function get_UK_grid_nums( $letters, $x = false, $y = false, $return_type = false, $deny_bad_reference = false, $use_rounding = false, $precise = false, $is_irish = false ) {
		//no need to shuffle the $is_irish parameter over, since it will always be at the end
		list( $letters, $x, $y, $return_type, $deny_bad_reference, $use_rounding, $precise ) =
			$this->expand_params( $letters, 3, 3, array( $x, $y, $return_type, $deny_bad_reference, $use_rounding, $precise ) );
		if( !is_string( $x ) || !is_string( $y ) ) {
			//a single string 'X[Y]12345678' or 'X[Y] 1234 5678'
			//manually move params
			$precise = $deny_bad_reference;
			$use_rounding = $return_type;
			$deny_bad_reference = $y;
			$return_type = $x;
			//split into parts
			//captured whitespace hack makes sure it fails reliably (UK/Irish grid refs must not match each other), while giving consistent matches length
			if( !preg_match( "/^\s*([A-HJ-Z])".($is_irish?"(\s*)":"([A-HJ-Z])\s*")."([\d\.]+)\s*([\d\.]*)\s*$/", strtoupper($letters), $letters ) || ( !$letters[4] && strlen($letters[3]) < 2 ) || ( !$letters[4] && strpos( $letters[3], '.' ) !== false ) ) {
				//invalid format
				if( $deny_bad_reference ) {
					return false;
				}
				//assume 0,0
				$letters = array( '', $is_irish ? 'V' : 'S', 'V', '0', '0' );
				$use_rounding = true;
			}
			if( !$letters[4] ) {
				//a single string 'X[Y]12345678', break the numbers in half
				$halflen = ceil( strlen($letters[3]) / 2 );
				$letters[4] = substr( $letters[3], $halflen );
				$letters[3] = substr( $letters[3], 0, $halflen );
			}
			$x = $letters[3];
			$y = $letters[4];
		} else {
			//st becomes ['','S','T']
			if( strlen($letters) != $is_irish ? 1 : 2 ) {
				if( $deny_bad_reference ) {
					return false;
				}
			}
			$letters = str_split(strtoupper($letters));
			array_unshift($letters,'');
		}
		$xdot = strpos( $x, '.' );
		$ydot = strpos( $y, '.' );
		if( is_numeric($x) ) {
			if( !$use_rounding ) {
				if( ( strlen($x) == 5 || $ydot !== false ) && $xdot === false ) {
					$x .= '.5';
				} else {
					$x .= '5';
				}
			}
		} else {
			if( $deny_bad_reference ) {
				return false;
			}
			$x = '0';
		}
		if( is_numeric($y) ) {
			if( !$use_rounding ) {
				if( ( strlen($y) == 5 || $xdot !== false ) && $ydot === false ) {
					$y .= '.5';
				} else {
					$y .= '5';
				}
			}
		} else {
			if( $deny_bad_reference ) {
				return false;
			}
			$y = '0';
		}
		//need 1m x 1m squares, but if either of them started with a dot, assume they are both in metres already
		if( $ydot === false && strpos( $x, '.' ) === false ) {
			$x = str_pad( $x, 5, '0' );
		}
		if( $xdot === false && strpos( $y, '.' ) === false ) {
			$y = str_pad( $y, 5, '0' );
		}
		if( $is_irish ) {
			if( isset( $this->UK_grid_numbers[$letters[1]] ) ) {
				$x += $this->UK_grid_numbers[$letters[1]][0] * 100000;
				$y += $this->UK_grid_numbers[$letters[1]][1] * 100000;
			} elseif( $deny_bad_reference ) {
				return false;
			} else {
				$x = $y = 0;
			}
		} else {
			if( isset( $this->UK_grid_numbers[$letters[1]] ) && isset( $this->UK_grid_numbers[$letters[2]] ) ) {
				//remove offset from VV to put origin back at SV
				$x += ( $this->UK_grid_numbers[$letters[1]][0] * 500000 ) + ( $this->UK_grid_numbers[$letters[2]][0] * 100000 ) - 1000000;
				$y += ( $this->UK_grid_numbers[$letters[1]][1] * 500000 ) + ( $this->UK_grid_numbers[$letters[2]][1] * 100000 ) - 500000;
			} elseif( $deny_bad_reference ) {
				return false;
			} else {
				$x = $y = 0;
			}
		}
		if( $return_type && !$use_rounding ) {
			//generic_xy_coords uses rounding
			if( $precise ) {
				$x = $this->floor_precision( $x, 6 );
				$y = $this->floor_precision( $y, 6 );
			} else {
				$x = floor($x);
				$y = floor($y);
			}
		}
		return $this->generic_xy_coords( $return_type, $precise, $x, $y );
	}
	public function get_Irish_grid_ref( $x, $y = false, $figures = false, $return_type = false, $deny_bad_reference = false, $use_rounding = false ) {
		return $this->get_UK_grid_ref( $x, $y, $figures, $return_type, $deny_bad_reference, $use_rounding, true );
	}
	public function get_Irish_grid_nums( $letters, $x = false, $y = false, $return_type = false, $deny_bad_reference = false, $use_rounding = false, $precise = false ) {
		return $this->get_UK_grid_nums( $letters, $x, $y, $return_type, $deny_bad_reference, $use_rounding, $precise, true );
	}
	public function add_grid_units( $x, $y = false, $return_type = false, $precise = false ) {
		list( $x, $y, $return_type, $precise ) = $this->expand_params( $x, 2, 2, array( $y, $return_type, $precise ) );
		if( $return_type ) {
			if( $precise ) {
				$x = sprintf( '%F', $x );
				$y = sprintf( '%F', $y );
			} else {
				//avoid stupid -0 by adding 0
				$x = round($x) + 0;
				$y = round($y) + 0;
			}
			return ( ( $x < 0 ) ? 'W ' : 'E ' ) . abs($x) . 'm, ' . ( ( $y < 0 ) ? 'S ' : 'N ' ) . abs($y) . 'm';
		} else {
			return array( abs($x), ( $x < 0 ) ? -1 : 1, abs($y), ( $y < 0 ) ? -1 : 1 );
		}
	}
	public function parse_grid_nums( $coords, $return_type = false, $deny_bad_coords = false, $strict_nums = false, $precise = false ) {
		if( is_array($coords) ) {
			//passed an array, so extract the numbers from it
			$matches = array('','',$coords[0]*$coords[1],'',$coords[2]*$coords[3]);
			$precise = $deny_bad_coords;
		} else {
			//look for two floats either side of a comma (extra captures ensure array indexes remain the same as $flexi's)
			$rigid = "/^(\s*)(-?[\d\.]+)\s*,(\s*)(-?[\d\.]+)\s*$/";
			//look for two integers either side of a comma or space, with optional leading E/W or N/S, and trailing m
			//[EW][-]<float>[m][, ][NS][-]<float>[m]
			$flexi = "/^\s*([EW]?)\s*(-?[\d\.]+)(?:\s*M)?(?:\s+|\s*,\s*)([NS]?)\s*(-?[\d\.]+)(?:\s*M)?\s*$/";
			if( !preg_match( $strict_nums ? $rigid : $flexi, strtoupper($coords), $matches ) ) {
				//invalid format
				if( $deny_bad_coords ) {
					return false;
				}
				//assume 0,0
				$matches = array( '', '', '0', '', '0' );
			}
			$matches[2] *= ( $matches[1] == 'W' ) ? -1 : 1;
			$matches[4] *= ( $matches[3] == 'S' ) ? -1 : 1;
			if( $deny_bad_coords && ( is_nan($matches[2]) || is_nan($matches[4]) ) ) {
				return false;
			}
		}
		return $this->generic_xy_coords( $return_type, $precise, $matches[2], $matches[4] );
	}

	//shifting grid coordinates between datums or by geoids using bilinear interpolation
	private $shiftcache = array();
	//shift parameter sets
	//OSTN15 1 km resolution data set
	private $shiftset_OSTN15 = array(
		'name' => 'OSTN15',
		'xspacing' => 1000,
		'yspacing' => 1000,
		'xstartsat' => 0,
		'ystartsat' => 0,
		'recordstart' => 1,
		'recordcols' => 701
	);
	public function get_shift_set($name) {
		$name = 'shiftset_'.$name;
		if( isset( $this->$name ) ) {
			return $this->$name;
		}
		return false;
	}
	public function create_shift_set( $name, $xspacing, $yspacing, $xstartsat = 0, $ystartsat = 0, $recordcols = 0, $recordstart = 0 ) {
		if( !is_string($name) || !strlen($name) || !( $xspacing * 1 ) || !( $yspacing * 1 ) ) {
			return false;
		}
		return array( 'name' => $name, 'xspacing' => $xspacing, 'yspacing' => $yspacing, 'xstartsat' => $xstartsat, 'ystartsat' => $ystartsat, 'recordstart' => $recordstart, 'recordcols' => $recordcols );
	}
	public function create_shift_record( $easting, $northing, $eastshift, $northshift, $geoidheight, $datum = null ) {
		//this does not actually get used in this format - it exists only to force the right columns to exist
		return array( 'name' => $easting.','.$northing, 'se' => $eastshift, 'sn' => $northshift, 'sg' => $geoidheight, 'datum' => $datum );
	}
	public function cache_shift_records( $name, $records ) {
		if( !is_string($name) || !strlen($name) ) {
			return false;
		}
		if( !isset($this->shiftcache[$name]) || !$this->shiftcache[$name] ) {
			//nothing has ever been cached for this shift set, create the cache
			$this->shiftcache[$name] = array();
		}
		if( !$records ) {
			return true;
		}
		//allow it to accept either a single record, or an array of records
		if( !is_array($records) || isset($records['name']) ) {
			$records = array($records);
		}
		foreach( $records as $onerecord ) {
			if( !is_array($onerecord) || !isset($onerecord['name']) || !isset($onerecord['se']) || !isset($onerecord['sn']) || !isset($onerecord['sg']) ) {
				return false;
			}
			$this->shiftcache[$name][$onerecord['name']] = array(
				'se' => $onerecord['se'],
				'sn' => $onerecord['sn'],
				'sg' => $onerecord['sg'],
				'datum' => $onerecord['datum']
			);
		}
		return true;
	}
	public function delete_shift_cache( $name = '' ) {
		if( strlen($name) ) {
			unset($this->shiftcache[$name]);
		} else {
			$this->shiftcache = array();
		}
	}
	private function get_shift_records( $name, $records, $fetch_records ) {
		if( !isset($this->shiftcache[$name]) || !$this->shiftcache[$name] ) {
			//nothing has ever been cached for this shift set, create the cache
			$this->shiftcache[$name] = array();
		}
		$unknown_records = array();
		$recordnames = array();
		foreach( $records as $onerecord ) {
			$recordnames[] = $id = $onerecord['easting'] . ',' . $onerecord['northing'];
			if( !isset($this->shiftcache[$name][$id]) ) {
				$unknown_records[] = $onerecord;
				$this->shiftcache[$name][$id] = false; //false gets detected as "set", null does not
			}
		}
		//fetch records if needed
		if( count($unknown_records) ) {
			$this->cache_shift_records( $name, call_user_func( $fetch_records, $name, $unknown_records ) );
		}
		$shifts = array();
		foreach( $recordnames as $id ) {
			$shifts[] = $this->shiftcache[$name][$id];
		}
		return $shifts;
	}
	private function get_shifts( $x, $y, $shiftset, $fetch_records ) {
		//transformation according to Transformations and OSGM15 User Guide
		//https://www.ordnancesurvey.co.uk/business-government/tools-support/os-net/for-developers
		if( !is_numeric($x) || !is_numeric($y) ) {
			//JavaScript casts to NaN, PHP casts to 0, this forces the same response
			return false;
		}
		//grid cell
		$east_index = floor( ( $x - $shiftset['xstartsat'] ) / $shiftset['xspacing'] );
		$north_index = floor( ( $y - $shiftset['ystartsat'] ) / $shiftset['yspacing'] );
		//easting and northing of cell southwest corner
		$x0 = $east_index * $shiftset['xspacing'] + $shiftset['xstartsat'];
		$y0 = $north_index * $shiftset['yspacing'] + $shiftset['ystartsat'];
		//offset within grid
		$dx = $x - $x0;
		$dy = $y - $y0;
		//retrieve shifts for the four corners of the cell
		$records = array();
		$records[0] = array( 'easting' => $x0, 'northing' => $y0 );
		//allow points to sit on the last line by setting them to the same values as each other (this is an improvement over the OS algorithm)
		if( $dx ) {
			$records[1] = array( 'easting' => $x0 + $shiftset['xspacing'], 'northing' => $y0 );
		} else {
			$records[1] = $records[0];
		}
		if( $dy ) {
			$records[2] = array( 'easting' => $records[1]['easting'], 'northing' => $y0 + $shiftset['yspacing'] );
			$records[3] = array( 'easting' => $x0, 'northing' => $records[2]['northing'] );
		} else {
			$records[2] = $records[1];
			$records[3] = $records[0];
		}
		if( $shiftset['recordcols'] ) {
			//unique, sequential record number starting from southwest eg. OSTN15
			$records[0]['record'] = $east_index + ( $north_index * $shiftset['recordcols'] ) + $shiftset['recordstart'];
			$records[1]['record'] = $dx ? ( $records[0]['record'] + 1 ) : $records[0]['record'];
			$records[3]['record'] = $dy ? ( $records[0]['record'] + $shiftset['recordcols'] ) : $records[0]['record'];
			$records[2]['record'] = $dx ? ( $records[3]['record'] + 1 ) : $records[3]['record'];
		}
		//fetch records if needed
		$shifts = $this->get_shift_records( $shiftset['name'], $records, $fetch_records );
		if( !$shifts[0] || !$shifts[1] || !$shifts[2] || !$shifts[3] ) {
			return false;
		}
		//offset values
		$t = $dx / $shiftset['xspacing'];
		$u = $dy / $shiftset['yspacing'];
		//bilinear interpolated shifts
		$se = ( ( 1 - $t ) * ( 1 - $u ) * $shifts[0]['se'] ) + ( $t * ( 1 - $u ) * $shifts[1]['se'] ) + ( $t * $u * $shifts[2]['se'] ) + ( ( 1 - $t ) * $u * $shifts[3]['se'] );
		$sn = ( ( 1 - $t ) * ( 1 - $u ) * $shifts[0]['sn'] ) + ( $t * ( 1 - $u ) * $shifts[1]['sn'] ) + ( $t * $u * $shifts[2]['sn'] ) + ( ( 1 - $t ) * $u * $shifts[3]['sn'] );
		$sg = ( ( 1 - $t ) * ( 1 - $u ) * $shifts[0]['sg'] ) + ( $t * ( 1 - $u ) * $shifts[1]['sg'] ) + ( $t * $u * $shifts[2]['sg'] ) + ( ( 1 - $t ) * $u * $shifts[3]['sg'] );
		$datums = null;
		if( isset($shifts[0]['datum']) || isset($shifts[1]['datum']) || isset($shifts[2]['datum']) || isset($shifts[3]['datum']) ) {
			$datums = array(
				isset($shifts[0]['datum']) ? $shifts[0]['datum'] : null,
				isset($shifts[1]['datum']) ? $shifts[1]['datum'] : null,
				isset($shifts[2]['datum']) ? $shifts[2]['datum'] : null,
				isset($shifts[3]['datum']) ? $shifts[3]['datum'] : null
			);
		}
		return array( $se, $sn, $sg, $datums );
	}
	public function shift_grid( $easting, $northing, $height, $shiftset = false, $fetch_records = false, $return_type = false, $deny_bad_coords = false, $precise = false ) {
		list( $easting, $northing, $height, $shiftset, $fetch_records, $return_type, $deny_bad_coords, $precise ) =
			$this->expand_params( $easting, 2, 3, array( $northing, $height, $shiftset, $fetch_records, $return_type, $deny_bad_coords, $precise ) );
		if( !$shiftset || !( $shiftset['xspacing'] * $shiftset['yspacing'] ) || !is_callable($fetch_records) ) {
			return false;
		}
		$shifts = $this->get_shifts( $easting, $northing, $shiftset, $fetch_records );
		if( !$shifts ) {
			if( $deny_bad_coords ) {
				return false;
			} else {
				return $this->generic_xy_coords( $return_type, $precise, NAN, NAN, is_numeric($height) ? NAN : null, array() );
			}
		}
		//easting, northing, height
		$easting += $shifts[0];
		$northing += $shifts[1];
		if( is_numeric($height) ) {
			$height -= $shifts[2];
		}
		return $this->generic_xy_coords( $return_type, $precise, $easting, $northing, $height, $shifts[3] );
	}
	public function reverse_shift_grid( $easting, $northing, $height, $shiftset = false, $fetch_records = false, $return_type = false, $deny_bad_coords = false, $precise = false ) {
		//transformation according to Transformations and OSGM15 User Guide
		//https://www.ordnancesurvey.co.uk/business-government/tools-support/os-net/for-developers
		list( $easting, $northing, $height, $shiftset, $fetch_records, $return_type, $deny_bad_coords, $precise ) =
			$this->expand_params( $easting, 2, 3, array( $northing, $height, $shiftset, $fetch_records, $return_type, $deny_bad_coords, $precise ) );
		if( !$shiftset || !( $shiftset['xspacing'] * $shiftset['yspacing'] ) || !is_callable($fetch_records) ) {
			return false;
		}
		$shifts = array( 0, 0 );
		$loopcount = 0;
		do {
			$oldshifts = $shifts;
			//get the shifts for the current estimated point, starting with the initial easting and northing
			//apply the new shift and try again with the shift from the new point
			$shifts = $this->get_shifts( is_numeric($easting) ? ( $easting - $shifts[0] ) : $easting, is_numeric($northing) ? ( $northing - $shifts[1] ) : $northing, $shiftset, $fetch_records );
			if( !$shifts ) {
				if( $deny_bad_coords ) {
					return false;
				} else {
					return $this->generic_xy_coords( $return_type, $precise, NAN, NAN, is_numeric($height) ? NAN : null, array() );
				}
			}
			//see if the shift changes with each iteration
			//unlikely to ever hit 100 iterations, even when taken to extremes
		} while( ( abs( $oldshifts[0] - $shifts[0] ) > 0.0001 || abs( $oldshifts[1] - $shifts[1] ) > 0.0001 ) && ++$loopcount < 100 );
		if( is_numeric($height) ) {
			$height += $shifts[2];
		}
		return $this->generic_xy_coords( $return_type, $precise, $easting - $shifts[0], $northing - $shifts[1], $height, $shifts[3] );
	}

	//ellipsoid parameters used during grid->lat/long conversions and Helmert transformations
	private $ellipsoid_Airy_1830 = array(
		//Airy 1830 (OS)
		'a' => 6377563.396,
		'b' => 6356256.909
	);
	private $ellipsoid_Airy_1830_mod = array(
		//Airy 1830 modified (OSI)
		'a' => 6377340.189,
		'b' => 6356034.447
	);
	private $ellipsoid_GRS80 = array(
		//GRS80 (ETRS89, early versions of GPS)
		'a' => 6378137,
		'b' => 6356752.3141
	);
	private $ellipsoid_WGS84 = array(
		//WGS84 (GPS)
		'a' => 6378137,
		'b' => 6356752.31425 // https://georepository.com/ellipsoid_7030/WGS-84.html 4 November 2020
	);
	private $datumset_OSGB36 = array(
		//Airy 1830 (OS)
		'a' => 6377563.396,
		'b' => 6356256.909,
		'F0' => 0.9996012717,
		'E0' => 400000,
		'N0' => -100000,
		'Phi0' => 49,
		'Lambda0' => -2
	);
	private $datumset_OSTN15 = array(
		//OSGB36 parameters with GRS80 ellipsoid (OSTN15)
		'a' => 6378137,
		'b' => 6356752.3141,
		'F0' => 0.9996012717,
		'E0' => 400000,
		'N0' => -100000,
		'Phi0' => 49,
		'Lambda0' => -2
	);
	private $datumset_Ireland_1965 = array(
		//Airy 1830 modified (OSI)
		'a' => 6377340.189,
		'b' => 6356034.447,
		'F0' => 1.000035,
		'E0' => 200000,
		'N0' => 250000,
		'Phi0' => 53.5,
		'Lambda0' => -8
	);
	private $datumset_IRENET95 = array(
		//ITM (OSI) taken from http://en.wikipedia.org/wiki/Irish_Transverse_Mercator
		//uses GRS80, which is so close to WGS84 that there is no point in transforming with Helmert before projecting
		'a' => 6378137,
		'b' => 6356752.3141,
		'F0' => 0.999820,
		'E0' => 600000,
		'N0' => 750000,
		'Phi0' => 53.5,
		'Lambda0' => -8
	);
	//UPS (uses WGS84), taken from http://www.epsg.org/guides/ number 7 part 2 "Coordinate Conversions and Transformations including Formulas"
	//officially defined in http://earth-info.nga.mil/GandG/publications/tm8358.2/TM8358_2.pdf
	private $datumset_UPS_North = array(
		'a' => 6378137,
		'b' => 6356752.31425,
		'F0' => 0.994,
		'E0' => 2000000,
		'N0' => 2000000,
		'Phi0' => 90,
		'Lambda0' => 0
	);
	private $datumset_UPS_South = array(
		'a' => 6378137,
		'b' => 6356752.31425,
		'F0' => 0.994,
		'E0' => 2000000,
		'N0' => 2000000,
		'Phi0' => -90,
		'Lambda0' => 0
	);
	public function get_ellipsoid($name) {
		$name = 'ellipsoid_'.$name;
		if( isset( $this->$name ) ) {
			return $this->$name;
		}
		return false;
	}
	public function create_ellipsoid( $a, $b ) {
		return array('a'=>$a,'b'=>$b);
	}
	public function get_datum($name) {
		$name = 'datumset_'.$name;
		if( isset( $this->$name ) ) {
			return $this->$name;
		}
		return false;
	}
	public function create_datum( $ellip, $F0, $E0, $N0, $Phi0, $Lambda0 ) {
		if( !isset($ellip['a']) ) {
			return false;
		}
		return array('a'=>$ellip['a'],'b'=>$ellip['b'],'F0'=>$F0,'E0'=>$E0,'N0'=>$N0,'Phi0'=>$Phi0,'Lambda0'=>$Lambda0);
	}

	//conversions between 12345,67890 grid references and latitude/longitude formats using transverse mercator projection
	public $COORDS_OS_UK = 1;
	public $COORDS_OSI = 2;
	public $COORDS_GPS_UK = 3;
	public $COORDS_GPS_IRISH = 4;
	public $COORDS_GPS_ITM = 5;
	public $COORDS_GPS_IRISH_HELMERT = 6;
	public function grid_to_lat_long( $E, $N, $type = false, $return_type = false ) {
		//horribly complex conversion according to "A guide to coordinate systems in Great Britain" Annexe C:
		//http://www.ordnancesurvey.co.uk/oswebsite/gps/information/coordinatesystemsinfo/guidecontents/
		//http://www.movable-type.co.uk/scripts/latlong-gridref.html shows an alternative script for JS, which also says what some OS variables represent
		list( $E, $N, $type, $return_type ) = $this->expand_params( $E, 2, 2, array( $N, $type, $return_type ) );
		//get appropriate ellipsoid semi-major axis 'a' (metres) and semi-minor axis 'b' (metres),
		//grid scale factor on central meridian, and true origin (grid and lat-long) from Annexe A
		//extracts variables called $a,$b,$F0,$E0,$N0,$Phi0,$Lambda0
		if( is_array($type) ) {
			extract( $type, EXTR_SKIP );
		} elseif( $type == $this->COORDS_OS_UK || $type == $this->COORDS_GPS_UK ) {
			extract( $this->datumset_OSGB36, EXTR_SKIP );
		} elseif( $type == $this->COORDS_GPS_ITM ) {
			extract( $this->datumset_IRENET95, EXTR_SKIP );
		} elseif( $type == $this->COORDS_OSI || $type == $this->COORDS_GPS_IRISH || $type == $this->COORDS_GPS_IRISH_HELMERT ) {
			extract( $this->datumset_Ireland_1965, EXTR_SKIP );
		}
		if( !isset($F0) ) {
			//invalid type
			return false;
		}
		//PHP will not allow expressions in the arrays as they are defined inline as class properties, so do the conversion to radians here
		$Phi0 = deg2rad($Phi0);
		//eccentricity-squared from Annexe B B1
		//$e2 = ( ( $a * $a ) - ( $b * $b ) ) / ( $a * $a );
		$e2 = 1 - ( $b * $b ) / ( $a * $a ); //optimised
		//C1
		$n = ( $a - $b ) / ( $a + $b );
		//pre-compute values that will be re-used many times in the C3 formula
		$n2 = $n * $n;
		$n3 = pow( $n, 3 );
		$n_parts1 = ( 1 + $n + 1.25 * $n2 + 1.25 * $n3 );
		$n_parts2 = ( 3 * $n + 3 * $n2 + 2.625 * $n3 );
		$n_parts3 = ( 1.875 * $n2 + 1.875 * $n3 );
		$n_parts4 = ( 35 / 24 ) * $n3;
		//iterate to find latitude (when $N - $N0 - $M < 0.01mm)
		$Phi = $Phi0;
		$M = 0;
		$loopcount = 0;
		do {
			//C6 and C7
			$Phi += ( ( $N - $N0 - $M ) / ( $a * $F0 ) );
			//C3
			$M = $b * $F0 * (
				$n_parts1 * ( $Phi - $Phi0 ) -
				$n_parts2 * sin( $Phi - $Phi0 ) * cos( $Phi + $Phi0 ) +
				$n_parts3 * sin( 2 * ( $Phi - $Phi0 ) ) * cos( 2 * ( $Phi + $Phi0 ) ) -
				$n_parts4 * sin( 3 * ( $Phi - $Phi0 ) ) * cos( 3 * ( $Phi + $Phi0 ) )
			); //meridonal arc
			//due to number precision, it is possible to get infinite loops here for extreme cases (especially for invalid ellipsoid numbers)
			//in tests, upto 6 loops are needed for grid 25 times Earth circumference - if it reaches 100, assume it must be infinite, and break out
		} while( abs( $N - $N0 - $M ) >= 0.00001 && ++$loopcount < 100 ); //0.00001 == 0.01 mm
		//pre-compute values that will be re-used many times in the C2 and C8 formulae
		$sin_Phi = sin( $Phi );
		$sin2_Phi = $sin_Phi * $sin_Phi;
		$tan_Phi = tan( $Phi );
		$sec_Phi = 1 / cos( $Phi );
		$tan2_Phi = $tan_Phi * $tan_Phi;
		$tan4_Phi = $tan2_Phi * $tan2_Phi;
		//C2
		$Rho = $a * $F0 * ( 1 - $e2 ) * pow( 1 - $e2 * $sin2_Phi, -1.5 ); //meridional radius of curvature
		$Nu = $a * $F0 / sqrt( 1 - $e2 * $sin2_Phi ); //transverse radius of curvature
		$Eta2 = $Nu / $Rho - 1;
		//pre-compute more values that will be re-used many times in the C8 formulae
		$Nu3 = pow( $Nu, 3 );
		$Nu5 = pow( $Nu, 5 );
		//C8 parts
		$VII = $tan_Phi / ( 2 * $Rho * $Nu );
		$VIII = ( $tan_Phi / ( 24 * $Rho * $Nu3 ) ) * ( 5 + 3 * $tan2_Phi + $Eta2 - 9 * $tan2_Phi * $Eta2 );
		$IX = ( $tan_Phi / ( 720 * $Rho * $Nu5 ) ) * ( 61 + 90 * $tan2_Phi + 45 * $tan4_Phi );
		$X = $sec_Phi / $Nu;
		$XI = ( $sec_Phi / ( 6 * $Nu3 ) ) * ( ( $Nu / $Rho ) + 2 * $tan2_Phi );
		$XII = ( $sec_Phi / ( 120 * $Nu5 ) ) * ( 5 + 28 * $tan2_Phi + 24 * $tan4_Phi );
		$XIIA = ( $sec_Phi / ( 5040 * pow( $Nu, 7 ) ) ) * ( 61 + 662 * $tan2_Phi + 1320 * $tan4_Phi + 720 * pow( $tan_Phi, 6 ) );
		//C8, C9
		$Edif = $E - $E0;
		$latitude = rad2deg( $Phi - $VII * $Edif * $Edif + $VIII * pow( $Edif, 4 ) - $IX * pow( $Edif, 6 ) );
		$longitude = $Lambda0 + rad2deg( $X * $Edif - $XI * pow( $Edif, 3 ) + $XII * pow( $Edif, 5 ) - $XIIA * pow( $Edif, 7 ) );
		if( $type == $this->COORDS_GPS_UK ) {
			list( $latitude, $longitude ) = $this->Helmert_transform( $latitude, $longitude, $this->ellipsoid_Airy_1830, $this->Helmert_OSGB36_to_WGS84, $this->ellipsoid_WGS84 );
		} elseif( $type == $this->COORDS_GPS_IRISH ) {
			list( $latitude, $longitude ) = $this->polynomial_transform( $latitude, $longitude, $this->polycoeff_OSiLPS );
		} elseif( $type == $this->COORDS_GPS_IRISH_HELMERT ) {
			list( $latitude, $longitude ) = $this->Helmert_transform( $latitude, $longitude, $this->ellipsoid_Airy_1830_mod, $this->Helmert_Ireland65_to_WGS84, $this->ellipsoid_WGS84 );
		}
		return $this->generic_lat_long_coords( $return_type, $latitude, $longitude );
	}
	public function lat_long_to_grid( $latitude, $longitude, $type = false, $return_type = false, $precise = false) {
		//horribly complex conversion according to "A guide to coordinate systems in Great Britain" Annexe C:
		//http://www.ordnancesurvey.co.uk/oswebsite/gps/information/coordinatesystemsinfo/guidecontents/
		//http://www.movable-type.co.uk/scripts/latlong-gridref.html shows an alternative script for JS, which also says what some OS variables represent
		list( $latitude, $longitude, $type, $return_type, $precise ) = $this->expand_params( $latitude, 2, 2, array( $longitude, $type, $return_type, $precise ) );
		//convert back to local ellipsoid coordinates
		if( $type == $this->COORDS_GPS_UK ) {
			list( $latitude, $longitude ) = $this->Helmert_transform( $latitude, $longitude, $this->ellipsoid_WGS84, $this->Helmert_WGS84_to_OSGB36, $this->ellipsoid_Airy_1830 );
		} elseif( $type == $this->COORDS_GPS_IRISH ) {
			list( $latitude, $longitude ) = $this->reverse_polynomial_transform( $latitude, $longitude, $this->polycoeff_OSiLPS );
		} elseif( $type == $this->COORDS_GPS_IRISH_HELMERT ) {
			list( $latitude, $longitude ) = $this->Helmert_transform( $latitude, $longitude, $this->ellipsoid_WGS84, $this->Helmert_WGS84_to_Ireland65, $this->ellipsoid_Airy_1830_mod );
		}
		//get appropriate ellipsoid semi-major axis 'a' (metres) and semi-minor axis 'b' (metres),
		//grid scale factor on central meridian, and true origin (grid and lat-long) from Annexe A
		//extracts variables called $a,$b,$F0,$E0,$N0,$Phi0,$Lambda0
		if( is_array($type) ) {
			extract( $type, EXTR_SKIP );
		} elseif( $type == $this->COORDS_OS_UK || $type == $this->COORDS_GPS_UK ) {
			extract( $this->datumset_OSGB36, EXTR_SKIP );
		} elseif( $type == $this->COORDS_GPS_ITM ) {
			extract( $this->datumset_IRENET95, EXTR_SKIP );
		} elseif( $type == $this->COORDS_OSI || $type == $this->COORDS_GPS_IRISH || $type == $this->COORDS_GPS_IRISH_HELMERT ) {
			extract( $this->datumset_Ireland_1965, EXTR_SKIP );
		}
		if( !isset($F0) ) {
			//invalid type
			return false;
		}
		//PHP will not allow expressions in the arrays as they are defined inline as class properties, so do the conversion to radians here
		$Phi0 = deg2rad($Phi0);
		$Phi = deg2rad($latitude);
		$Lambda = $longitude - $Lambda0;
		//force Lambda between -180 and 180
		$Lambda = $this->wrap_around_longitude($Lambda);
		$Lambda = deg2rad($Lambda);
		//eccentricity-squared from Annexe B B1
		//$e2 = ( ( $a * $a ) - ( $b * $b ) ) / ( $a * $a );
		$e2 = 1 - ( $b * $b ) / ( $a * $a ); //optimised
		//C1
		$n = ( $a - $b ) / ( $a + $b );
		//pre-compute values that will be re-used many times in the C2, C3 and C4 formulae
		$sin_Phi = sin( $Phi );
		$sin2_Phi = $sin_Phi * $sin_Phi;
		$cos_Phi = cos( $Phi );
		$cos3_Phi = pow( $cos_Phi, 3 );
		$cos5_Phi = pow( $cos_Phi, 5 );
		$tan_Phi = tan( $Phi );
		$tan2_Phi = $tan_Phi * $tan_Phi;
		$tan4_Phi = $tan2_Phi * $tan2_Phi;
		$n2 = $n * $n;
		$n3 = pow( $n, 3 );
		//C2
		$Nu = $a * $F0 / sqrt( 1 - $e2 * $sin2_Phi ); //transverse radius of curvature
		$Rho = $a * $F0 * ( 1 - $e2 ) * pow( 1 - $e2 * $sin2_Phi, -1.5 ); //meridional radius of curvature
		$Eta2 = $Nu / $Rho - 1;
		//C3, meridonal arc
		$M = $b * $F0 * (
			( 1 + $n + 1.25 * $n2 + 1.25 * $n3 ) * ( $Phi - $Phi0 ) -
			( 3 * $n + 3 * $n2 + 2.625 * $n3 ) * sin( $Phi - $Phi0 ) * cos( $Phi + $Phi0 ) +
			( 1.875 * $n2 + 1.875 * $n3 ) * sin( 2 * ( $Phi - $Phi0 ) ) * cos( 2 * ( $Phi + $Phi0 ) ) -
			( 35 / 24 ) * $n3 * sin( 3 * ( $Phi - $Phi0 ) ) * cos( 3 * ( $Phi + $Phi0 ) )
		);
		//C4
		$I = $M + $N0;
		$II = ( $Nu / 2 ) * $sin_Phi * $cos_Phi;
		$III = ( $Nu / 24 ) * $sin_Phi * $cos3_Phi * ( 5 - $tan2_Phi + 9 * $Eta2 );
		$IIIA = ( $Nu / 720 ) * $sin_Phi * $cos5_Phi * ( 61 - 58 * $tan2_Phi + $tan4_Phi );
		$IV = $Nu * $cos_Phi;
		$V = ( $Nu / 6 ) * $cos3_Phi * ( ( $Nu / $Rho ) - $tan2_Phi );
		$VI = ( $Nu / 120 ) * $cos5_Phi * ( 5 - 18 * $tan2_Phi + $tan4_Phi + 14 * $Eta2 - 58 * $tan2_Phi * $Eta2 );
		$N = $I + $II * $Lambda * $Lambda + $III * pow( $Lambda, 4 ) + $IIIA * pow( $Lambda, 6 );
		$E = $E0 + $IV * $Lambda + $V * pow( $Lambda, 3 ) + $VI * pow( $Lambda, 5 );
		return $this->generic_xy_coords( $return_type, $precise, $E, $N );
	}

	//UTM - freaky format consisting of 60 transverse mercators
	public function utm_to_lat_long( $zone, $north = false, $x = false, $y = false, $ellip = false, $return_type = false, $deny_bad_reference = false ) {
		list( $zone, $north, $x, $y, $ellip, $return_type, $deny_bad_reference ) =
			$this->expand_params( $zone, 4, 4, array( $north, $x, $y, $ellip, $return_type, $deny_bad_reference ) );
		if( is_string( $zone ) ) {
			//manually move params
			if( !is_array($ellip) ) {
				//if it is an array, expand_params will have already moved it over
				$deny_bad_reference = $y;
				$return_type = $x;
				$ellip = $north;
			}
			$zone = strtoupper($zone);
			if( preg_match( "/^\s*(0?[1-9]|[1-5][0-9]|60)\s*([C-HJ-NP-X]|NORTH|SOUTH)\s*(-?[\d\.]+)\s*[,\s]\s*(-?[\d\.]+)\s*$/", $zone, $parsed_zone ) ) {
				//matched the shorthand 30U 1234 5678
				//[01-60](<letter>|north|south)<float>[, ]<float>
				//radix parameter is needed since numbers often start with a leading 0 and must not be treated as octal
				$zone = $parsed_zone[1] * 1;
				if( strlen( $parsed_zone[2] ) > 1 ) {
					$north = ( $parsed_zone[2] == 'NORTH' ) ? 1 : -1;
				} else {
					$north = ( $parsed_zone[2] > 'M' ) ? 1 : -1;
				}
				$x = $parsed_zone[3] * 1;
				$y = $parsed_zone[4] * 1;
			} else if( preg_match( "/^\s*(-?[\d\.]+)\s*[A-Z]*\s*[,\s]\s*(-?[\d\.]+)\s*[A-Z]*\s*[\s,]\s*ZONE\s*(0?[1-9]|[1-5][0-9]|60)\s*,?\s*([NS])/", $zone, $parsed_zone ) ) {
				//matched the longhand 630084 mE 4833438 mN, zone 17, Northern Hemisphere
				//<float>[letters][, ]<float>[letters][, ]zone[01-60][,][NS]...
				$zone = $parsed_zone[3] * 1;
				$north = ( $parsed_zone[4] == 'N' ) ? 1 : -1;
				$x = $parsed_zone[1] * 1;
				$y = $parsed_zone[2] * 1;
			} else {
				//make it reject it
				$zone = 0;
			}
		}
		if( is_array($ellip) && !isset($ellip['a']) ) {
			//invalid ellipsoid must be rejected early, before returning error values or constructing a datum
			return false;
		}
		if( !is_numeric($zone) || $zone < 1 || $zone > 60 || !is_numeric($x) || !is_numeric($y) ) {
			if( $deny_bad_reference ) {
				//invalid format
				return false;
			}
			//default coordinates put it at 90,0 lat/long - use dms_to_dd to get the right return_type
			return $this->dms_to_dd(array(90,0,0,1,0,0,0,0),$return_type);
		}
		$ellipsoid = is_array($ellip) ? $ellip : $this->ellipsoid_WGS84;
		$ellipsoid = array( 'a' => $ellipsoid['a'], 'b' => $ellipsoid['b'], 'F0' => 0.9996, 'E0' => 500000, 'N0' => ( $north < 0 ) ? 10000000 : 0, 'Phi0' => 0, 'Lambda0' => ( 6 * $zone ) - 183 );
		return $this->grid_to_lat_long($x,$y,$ellipsoid,$return_type);
	}
	public function lat_long_to_utm( $latitude, $longitude = false, $ellip = false, $format = false, $return_type = false, $deny_bad_coords = false, $precise = false ) {
		list( $latitude, $longitude, $ellip, $format, $return_type, $deny_bad_coords, $precise ) =
			$this->expand_params( $latitude, 2, 2, array( $longitude, $ellip, $format, $return_type, $deny_bad_coords, $precise ) );
		if( is_array($ellip) && !isset($ellip['a']) ) {
			//invalid ellipsoid must be rejected early, before returning error values or constructing a datum
			return false;
		}
		//force the longitude between -180 and 179.99...9
		$longitude = $this->wrap_around_longitude( $longitude, true );
		if( !is_numeric($longitude) || !is_numeric($latitude) || $latitude > 84 || $latitude < -80 ) {
			if( $deny_bad_coords ) {
				//invalid format
				return false;
			}
			//default coordinates put it at ~0,0 lat/long
			if( !is_numeric($longitude) ) {
				$longitude = 0;
			}
			if( !is_numeric($latitude) ) {
				$latitude = 0;
			}
			if( $latitude > 84 ) {
				//out of range, return appropriate polar letter, and bail out
				$zoneletter = $format ? 'North' : ( ( $longitude < 0 ) ? 'Y' : 'Z' );
				$zone = $x = $y = 0;
			}
			if( $latitude < -80 ) {
				//out of range, return appropriate polar letter, and bail out
				$zoneletter = $format ? 'South' : ( ( $longitude < 0 ) ? 'A' : 'B' );
				$zone = $x = $y = 0;
			}
		}
		if( !isset($zoneletter) || !$zoneletter ) {
			//add hacks to work out if it lies in the non-standard zones
			if( $latitude >= 72 && $longitude >= 6 && $longitude < 36 ) {
				//band X, these parts are moved around
				if( $longitude < 9 ) {
					$zone = 31;
				} else if( $longitude < 21 ) {
					$zone = 33;
				} else if( $longitude < 33 ) {
					$zone = 35;
				} else {
					$zone = 37;
				}
			} else if( $latitude >= 56 && $latitude < 64 && $longitude >= 3 && $longitude < 6 ) {
				//band U, this part of zone 31 is moved into zone 32
				$zone = 32;
			} else {
				//yay for standards!
				$zone = floor( $longitude / 6 ) + 31;
			}
			//get the band letter
			if( $format ) {
				$zoneletter = ( $latitude < 0 ) ? 'South' : 'North';
			} else {
				$zoneletter = floor( $latitude / 8 ) + 77; //67 is ASCII C
				if( $zoneletter > 72 ) {
					//skip I
					$zoneletter++;
				}
				if( $zoneletter > 78 ) {
					//skip O
					$zoneletter++;
				}
				if( $zoneletter > 88 ) {
					//X is as high as it goes
					$zoneletter = 88;
				}
				$zoneletter = chr($zoneletter);
			}
			//do actual transformation - happens inside "if(!$zoneletter)" because this is the non-error path
			$ellipsoid = is_array($ellip) ? $ellip : $this->ellipsoid_WGS84;
			$ellipsoid = array( 'a' => $ellipsoid['a'], 'b' => $ellipsoid['b'], 'F0' => 0.9996, 'E0' => 500000, 'N0' => ( $latitude < 0 ) ? 10000000 : 0, 'Phi0' => 0, 'Lambda0' => ( 6 * $zone ) - 183 );
			$tmpcoords = $this->lat_long_to_grid($latitude,$longitude,$ellipsoid);
			if( !$tmpcoords ) { return false; }
			$x = $tmpcoords[0];
			$y = $tmpcoords[1];
		}
		if( $return_type ) {
			if( $precise ) {
				$x = sprintf( '%F', $x );
				$y = sprintf( '%F', $y );
			} else {
				//avoid stupid -0 by adding 0
				$x = round($x) + 0;
				$y = round($y) + 0;
			}
			if( $format ) {
				return $x . 'mE, ' . $y . 'mN, Zone ' . $zone . ', ' . $zoneletter . 'ern Hemisphere';
			}
			return $zone . $zoneletter . ' ' . $x . ' ' . $y;
		} else {
			return array( $zone, ( $latitude < 0 ) ? -1 : 1, $x+0, $y+0, $zoneletter );
		}
	}

	//basic polar stereographic pojections
	//formulae according to http://www.epsg.org/guides/ number 7 part 2 "Coordinate Conversions and Transformations including Formulas"
	public function polar_to_lat_long( $easting, $northing, $datum = false, $return_type = false ) {
		list( $easting, $northing, $datum, $return_type ) = $this->expand_params( $easting, 2, 2, array( $northing, $datum, $return_type ) );
		if( !$datum ) {
			return false;
		}
		if( !isset($datum['F0']) || ( $datum['Phi0'] != 90 && $datum['Phi0'] != -90 ) ) {
			//invalid type
			return false;
		}
		$a = $datum['a'];
		$b = $datum['b'];
		$k0 = $datum['F0'];
		$FE = $datum['E0'];
		$FN = $datum['N0'];
		$Phi0 = $datum['Phi0'];
		$Lambda0 = $datum['Lambda0'];
		//eccentricity-squared
		$e2 = 1 - ( $b * $b ) / ( $a * $a ); //optimised
		$e = sqrt($e2);
		$Rho = sqrt( pow( $easting - $FE, 2 ) + pow( $northing - $FN, 2 ) );
		$t = $Rho * sqrt( pow( 1 + $e, 1 + $e ) * pow( 1 - $e, 1 - $e ) ) / ( 2 * $a * $k0 );
		if( $Phi0 < 0 ) {
			//south
			$x = 2 * atan($t) - M_PI / 2;
		} else {
			//north
			$x = M_PI / 2 - 2 * atan($t);
		}
		//pre-compute values that will be re-used many times in the Phi formula
		$e4 = $e2 * $e2;
		$e6 = $e4 * $e2;
		$e8 = $e4 * $e4;
		$Phi = $x + ( $e2 / 2 + 5 * $e4 / 24 + $e6 / 12 + 13 * $e8 / 360 ) * sin( 2 * $x ) +
			( 7 * $e4 / 48 + 29 * $e6 / 240 + 811 * $e8 / 11520 ) * sin( 4 * $x ) +
			( 7 * $e6 / 120 + 81 * $e8 / 1120 ) * sin( 6 * $x ) +
			( 4279 * $e8 / 161280 ) * sin( 8 * $x );
		//longitude
		//formulas here are wrong in the epsg guide; atan(foo/bar) should have been atan2(foo,bar) or it is wrong for half of the grid
		if( $Phi0 < 0 ) {
			//south
			$Lambda = $Lambda0 + atan2( $easting - $FE, $northing - $FN );
		} else {
			//north
			$Lambda = $Lambda0 + atan2( $easting - $FE, $FN - $northing );
		}
		$latitude = rad2deg($Phi);
		$longitude = rad2deg($Lambda) + $Lambda0;
		return $this->generic_lat_long_coords( $return_type, $latitude, $longitude );
	}
	public function lat_long_to_polar( $latitude, $longitude, $datum = false, $return_type = false, $precise = false ) {
		list( $latitude, $longitude, $datum, $return_type, $precise ) = $this->expand_params( $latitude, 2, 2, array( $longitude, $datum, $return_type, $precise ) );
		if( !$datum ) {
			return false;
		}
		if( !isset($datum['F0']) || ( $datum['Phi0'] != 90 && $datum['Phi0'] != -90 ) ) {
			//invalid type
			return false;
		}
		$a = $datum['a'];
		$b = $datum['b'];
		$k0 = $datum['F0'];
		$FE = $datum['E0'];
		$FN = $datum['N0'];
		$Lambda0 = $datum['Lambda0'];
		$Phi = deg2rad($latitude);
		$Lambda = deg2rad( $longitude - $Lambda0 );
		//eccentricity-squared
		$e2 = 1 - ( $b * $b ) / ( $a * $a ); //optimised
		$e = sqrt($e2);
		//t
		$sinPhi = sin( $Phi );
		if( $latitude < 0 ) {
			//south
			$t = tan( ( M_PI / 4 ) + ( $Phi / 2 ) ) / pow( ( 1 + $e * $sinPhi ) / ( 1 - $e * $sinPhi ), $e / 2 );
		} else {
			//north
			$t = tan( ( M_PI / 4 ) - ( $Phi / 2 ) ) * pow( ( 1 + $e * $sinPhi ) / ( 1 - $e * $sinPhi ), $e / 2 );
		}
		//Rho
		$Rho = 2 * $a * $k0 * $t / sqrt( pow( 1 + $e, 1 + $e ) * pow( 1 - $e, 1 - $e ) );
		if( $latitude < 0 ) {
			//south
			$N = $FN + $Rho * cos( $Lambda );
		} else {
			//north - origin is *down*
			$N = $FN - $Rho * cos( $Lambda );
		}
		$E = $FE + $Rho * sin( $Lambda );
		return $this->generic_xy_coords( $return_type, $precise, $E, $N );
	}
	public function ups_to_lat_long( $hemisphere, $x = false, $y = false, $return_type = false, $deny_bad_reference = false, $min_length = false ) {
		list( $hemisphere, $x, $y, $return_type, $deny_bad_reference, $min_length ) =
			$this->expand_params( $hemisphere, 3, 3, array( $x, $y, $return_type, $deny_bad_reference, $min_length ) );
		if( !is_numeric($x) || !is_numeric($y) ) {
			//a single string 'X 12345 67890', split into parts
			//manually move params
			$min_length = $return_type;
			$deny_bad_reference = $y;
			$return_type = $x;
			//(A|B|Y|Z|N|S|north|south)[,]<float>[, ]<float>
			if( !preg_match( "/^\s*([ABNSYZ]|NORTH|SOUTH)\s*,?\s*(-?[\d\.]+)\s*[\s,]\s*(-?[\d\.]+)\s*$/i", $hemisphere, $hemisphere ) ) {
				//invalid format, will get handled later
				$x = $y = null;
			} else {
				$x = $hemisphere[2];
				$y = $hemisphere[3];
				$hemisphere = $hemisphere[1];
			}
		}
		if( !is_numeric($x) || !is_numeric($y) || ( !is_numeric($hemisphere) && ( !is_string($hemisphere) || !preg_match( "/^([ABNSYZ]|NORTH|SOUTH)$/i", $hemisphere ) ) ) || ( $min_length && ( $x < 800000 || $y < 800000 ) ) ) {
			if( $deny_bad_reference ) {
				//invalid format
				return false;
			}
			//default coordinates put it at 0,0 lat/long - use dms_to_dd to get the right return_type
			return $this->dms_to_dd(array(0,0,0,0,0,0,0,0),$return_type);
		}
		if( is_string($hemisphere) ) {
			$hemisphere = strtoupper($hemisphere);
		} else {
			$hemisphere = ( $hemisphere < 0 ) ? 'S' : 'N';
		}
		if( $hemisphere == 'N' || $hemisphere == 'NORTH' || $hemisphere == 'Y' || $hemisphere == 'Z' ) {
			$hemisphere = $this->datumset_UPS_North;
		} else {
			$hemisphere = $this->datumset_UPS_South;
		}
		return $this->polar_to_lat_long($x*1,$y*1,$hemisphere,$return_type);
	}
	public function lat_long_to_ups( $latitude, $longitude = false, $format = false, $return_type = false, $deny_bad_coords = false, $precise = false ) {
		list( $latitude, $longitude, $format, $return_type, $deny_bad_coords, $precise ) =
			$this->expand_params( $latitude, 2, 2, array( $longitude, $format, $return_type, $deny_bad_coords, $precise ) );
		//force the longitude between -179.99...9 and 180
		$longitude = $this->wrap_around_longitude($longitude);
		if( !is_numeric($longitude) || !is_numeric($latitude) || $latitude > 90 || $latitude < -90 || ( $latitude < 83.5 && $latitude > -79.5 ) ) {
			if( $deny_bad_coords ) {
				//invalid format
				return false;
			}
			//default coordinates put it as 90,0 in OUT_OF_GRID zone
			$tmp = array( 2000000, 2000000 );
			$letter = 'OUT_OF_GRID';
		} else {
			$tmp = $this->lat_long_to_polar( $latitude, $longitude, ( $latitude < 0 ) ? $this->datumset_UPS_South : $this->datumset_UPS_North );
			if( $latitude < 0 ) {
				if( $format ) {
					$letter = 'S';
				} else if( $longitude < 0 ) {
					$letter = 'A';
				} else {
					$letter = 'B';
				}
			} else {
				if( $format ) {
					$letter = 'N';
				} else if( $longitude < 0 ) {
					$letter = 'Y';
				} else {
					$letter = 'Z';
				}
			}
		}
		if( $return_type ) {
			if( $precise ) {
				$tmp[0] = sprintf( '%F', $tmp[0] );
				$tmp[1] = sprintf( '%F', $tmp[1] );
			} else {
				//avoid stupid -0 by adding 0
				$tmp[0] = round($tmp[0]) + 0;
				$tmp[1] = round($tmp[1]) + 0;
			}
			return $letter.' '.$tmp[0].' '.$tmp[1];
		} else {
			return array( ( $latitude < 0 ) ? -1 : 1, $tmp[0]+0, $tmp[1]+0, $letter );
		}
	}

	//conversions between different latitude/longitude formats
	public function dd_to_dms( $N, $E = false, $only_dm = false, $return_type = false ) {
		//decimal degrees (49.5,-123.5) to degrees-minutes-seconds (49°30'0"N, 123°30'0"W)
		list( $N, $E, $only_dm, $return_type ) = $this->expand_params( $N, 2, 2, array( $E, $only_dm, $return_type ) );
		$N_abs = abs($N);
		$E_abs = abs($E);
		$degN = floor($N_abs);
		$degE = floor($E_abs);
		if( $only_dm ) {
			$minN = 60 * ( $N_abs - $degN );
			$secN = 0;
			$minE = 60 * ( $E_abs - $degE );
			$secE = 0;
		} else {
			//the approach used here is careful to respond consistently to floating point errors for all of degrees/minutes/seconds
			//errors should not cause one to be increased while another is decreased (which could cause eg. 5 minutes 60 seconds)
			$minN = 60 * $N_abs;
			$secN = ( $minN - floor( $minN ) ) * 60;
			$minN %= 60;
			$minE = 60 * $E_abs;
			$secE = ( $minE - floor( $minE ) ) * 60;
			$minE %= 60;
		}
		//avoid stupid -0 by adding 0
		$degN += 0;
		$minN += 0;
		$secN += 0;
		$degE += 0;
		$minE += 0;
		$secE += 0;
		if( $return_type ) {
			//sprintf to produce simple numbers instead of scientific notation (also reduces accuracy to 6 decimal places)
			$deg = is_string($return_type) ? $return_type : '&deg;';
			$quot = is_string($return_type) ? '"' : '&quot;';
			if( $only_dm ) {
				//careful not to round up to 60 minutes when displaying
				return
					$degN . $deg . sprintf( '%.8F', ( $minN >= 59.999999995 ) ? 59.99999999 : $minN ) . "'" . ( ( $N < 0 ) ? 'S' : 'N' ) . ', ' .
					$degE . $deg . sprintf( '%.8F', ( $minE >= 59.999999995 ) ? 59.99999999 : $minE ) . "'" . ( ( $E < 0 ) ? 'W' : 'E' );
			} else {
				//careful not to round up to 60 seconds when displaying
				return
					$degN . $deg . $minN . "'" . sprintf( '%F', ( $secN >= 59.9999995 ) ? 59.999999 : $secN ) . $quot . ( ( $N < 0 ) ? 'S' : 'N' ) . ', ' .
					$degE . $deg . $minE . "'" . sprintf( '%F', ( $secE >= 59.9999995 ) ? 59.999999 : $secE ) . $quot . ( ( $E < 0 ) ? 'W' : 'E' );
			}
		} else {
			return array( $degN, $minN, $secN, ( $N < 0 ) ? -1 : 1, $degE, $minE, $secE, ( $E < 0 ) ? -1 : 1 );
		}
	}
	public function dd_format( $N, $E = false, $no_units = false, $return_type = false ) {
		//different formats of decimal degrees (49.5,-123.5)
		list( $N, $E, $no_units, $return_type ) = $this->expand_params( $N, 2, 2, array( $E, $no_units, $return_type ) );
		if( $no_units && $return_type ) {
			return $this->generic_lat_long_coords( $return_type, $N, $E );
		}
		$E = $this->wrap_around_longitude($E);
		//rounding needs to happen before testing for signs and letters, rounds *down* negative numbers just like sprintf
		if( $return_type && $E <= -179.99999999995 ) {
			$E = 180;
		}
		if( $no_units ) {
			$lat_mul = $long_mul = 1;
		} else {
			$lat_mul = $return_type ? ( ( $N < 0 ) ? 'S' : 'N' ) : ( ( $N < 0 ) ? -1 : 1 );
			$long_mul = $return_type ? ( ( $E < 0 ) ? 'W' : 'E' ) : ( ( $E < 0 ) ? -1 : 1 );
			$N = abs($N);
			$E = abs($E);
		}
		if( $return_type ) {
			$deg = is_string($return_type) ? $return_type : '&deg;';
			return sprintf( '%.10F', $N ) . $deg . $lat_mul . ', ' . sprintf( '%.10F', $E ) . $deg . $long_mul;
		}
		return array( $N, 0, 0, $lat_mul, $E, 0, 0, $long_mul );
	}
	public function dms_to_dd( $dms, $return_type = false, $deny_bad_coords = false, $allow_unitless = false ) {
		//degrees-minutes-seconds (49°30'0"N, 123°30'0"W) to decimal degrees (49.5,-123.5)
		if( is_array( $dms ) ) {
			//passed an array of values, which can be unshifted once to get the right positions
			$latlong = $dms;
			array_unshift($latlong,'','');
			array_splice($latlong,6,0,'');
		} else {
			$latlong = null;
			//matches [-]<float>,[-]<float> which could also be grid coordinates, so this is kept behind an option
			if( $allow_unitless && preg_match("/^\s*(-?)([\d\.]+)\s*,\s*(-?)([\d\.]+)\s*$/", strtoupper($dms), $latlong ) ) {
				$latlong = array('',$latlong[1],$latlong[2],'0','0','',$latlong[3],$latlong[4],'0','0','');
			}
			//matches [-]<float>[NS],[-]<float>[EW] with non-optional NSEW
			if( !$latlong && preg_match("/^\s*(-?)([\d\.]+)\s*([NS])\s*,?\s*(-?)([\d\.]+)\s*([EW])\s*$/", strtoupper($dms), $latlong ) ) {
				$latlong = array('',$latlong[1],$latlong[2],'0','0',$latlong[3],$latlong[4],$latlong[5],'0','0',$latlong[6]);
			}
			//simple regex ;) ... matches upto 3 sets of number[non-number] per northing/easting (allows for DMS, DM or D)
			//uses \D+ to avoid encoding conflicts with ° character which can become multibyte (u flag just fails to match)
			//smart-quote versions of ' and " are also multi-byte characters
			//it must not treat NSEW as if they are part of the degree symbol with decimal degrees, or other separators
			//note that this cannot accept HTML strings from dd_to_dms as it will not match &quot;
			//[-]<float><separator>[<float><separator>[<float><separator>]]([NS][,]|,)[-]<float><separator>[<float><separator>[<float><separator>]][EW]
			//Captures -, <float>, <float>, <float>, [NS], -, <float>, <float>, <float>, [EW]
			if( !$latlong && !preg_match( "/^\s*(-?)([\d\.]+)\D+?\s*(?:([\d\.]+)\D+?\s*(?:([\d\.]+)\D+?\s*)?)?(?:([NS])\s*,?|,)\s*(-?)([\d\.]+)\D+?\s*(?:([\d\.]+)\D+?\s*(?:([\d\.]+)\D+?\s*)?)?([EW]?)\s*$/", strtoupper($dms), $latlong ) ) {
				//invalid format
				if( $deny_bad_coords ) {
					return false;
				}
				//assume 0,0
				$latlong = array('','','0','0','0','N','','0','0','0','E');
			}
			if( $deny_bad_coords && ( is_nan($latlong[2]) || is_nan($latlong[7]) ) ) {
				return false;
			}
		}
		if( !$latlong[3] ) { $latlong[3] = 0; }
		if( !$latlong[4] ) { $latlong[4] = 0; }
		if( !$latlong[8] ) { $latlong[8] = 0; }
		if( !$latlong[9] ) { $latlong[9] = 0; }
		$lat = $latlong[2] + $latlong[3] / 60 + $latlong[4] / 3600;
		if( $latlong[1] ) { $lat *= -1; }
		if( $latlong[5] == 'S' || $latlong[5] == -1 ) { $lat *= -1; }
		$long = $latlong[7] + $latlong[8] / 60 + $latlong[9] / 3600;
		if( $latlong[6] ) { $long *= -1; }
		if( $latlong[10] == 'W' || $latlong[10] == -1 ) { $long *= -1; }
		return $this->generic_lat_long_coords( $return_type, $lat, $long );
	}

	//Helmert transform parameters used during Helmert transformations
	//OSGB<->WGS84 parameters taken from "6.6 Approximate WGS84 to OSGB36/ODN transformation"
	//http://www.ordnancesurvey.co.uk/oswebsite/gps/information/coordinatesystemsinfo/guidecontents/guide6.html
	private $Helmert_WGS84_to_OSGB36 = array(
		'tx' => -446.448,
		'ty' => 125.157,
		'tz' => -542.060,
		's' => 20.4894,
		'rx' => -0.1502,
		'ry' => -0.2470,
		'rz' => -0.8421
	);
	private $Helmert_OSGB36_to_WGS84 = array(
		'tx' => 446.448,
		'ty' => -125.157,
		'tz' => 542.060,
		's' => -20.4894,
		'rx' => 0.1502,
		'ry' => 0.2470,
		'rz' => 0.8421
	);
	//Ireland65<->WGS84 parameters taken from http://en.wikipedia.org/wiki/Helmert_transformation
	private $Helmert_WGS84_to_Ireland65 = array(
		'tx' => -482.53,
		'ty' => 130.596,
		'tz' => -564.557,
		's' => -8.15,
		'rx' => 1.042,
		'ry' => 0.214,
		'rz' => 0.631
	);
	private $Helmert_Ireland65_to_WGS84 = array(
		'tx' => 482.53,
		'ty' => -130.596,
		'tz' => 564.557,
		's' => 8.15,
		'rx' => -1.042,
		'ry' => -0.214,
		'rz' => -0.631
	);
	public function get_transformation($name) {
		$name = 'Helmert_'.$name;
		if( isset( $this->$name ) ) {
			return $this->$name;
		}
		return false;
	}
	public function create_transformation( $tx, $ty, $tz, $s, $rx, $ry, $rz ) {
		return array('tx'=>$tx,'ty'=>$ty,'tz'=>$tz,'s'=>$s,'rx'=>$rx,'ry'=>$ry,'rz'=>$rz);
	}
	public function Helmert_transform( $N, $E, $H, $from, $via = false, $to = false, $return_type = false ) {
		//conversion according to formulae listed on http://www.movable-type.co.uk/scripts/latlong-convert-coords.html
		//parts taken from http://www.ordnancesurvey.co.uk/oswebsite/gps/information/coordinatesystemsinfo/guidecontents/
		list( $N, $E, $H, $from, $via, $to, $return_type ) = $this->expand_params( $N, 2, 3, array( $E, $H, $from, $via, $to, $return_type ) );
		if( !$from || !isset($from['a']) || !$via || !isset($via['tx']) || !$to || !isset($to['a']) ) {
			return false;
		}
		$has_height = is_numeric($H);
		if( !$has_height ) {
			$H = 0;
		}
		//work in radians
		$N = deg2rad($N);
		$E = deg2rad($E);
		//convert polar coords to cartesian
		//eccentricity-squared of source ellipsoid from Annexe B B1
		$e2 = 1 - ( $from['b'] * $from['b'] ) / ( $from['a'] * $from['a'] );
		$sin_Phi = sin( $N );
		$cos_Phi = cos( $N );
		//transverse radius of curvature
		$Nu = $from['a'] / sqrt( 1 - $e2 * $sin_Phi * $sin_Phi );
		$x = ( $Nu + $H ) * $cos_Phi * cos( $E );
		$y = ( $Nu + $H ) * $cos_Phi * sin( $E );
		$z = ( ( 1 - $e2 ) * $Nu + $H ) * $sin_Phi;
		//extracts variables called $tx,$ty,$tz,$s,$rx,$ry,$rz
		extract( $via, EXTR_SKIP );
		//convert seconds to radians
		$rx *= M_PI / 648000;
		$ry *= M_PI / 648000;
		$rz *= M_PI / 648000;
		//convert ppm to pp_one, and add one to avoid recalculating
		$s = $s / 1000000 + 1;
		//apply Helmert transform (algorithm notes incorrectly show rx instead of rz in $x1 line)
		$x1 = $tx + $s * $x - $rz * $y + $ry * $z;
		$y1 = $ty + $rz * $x + $s * $y - $rx * $z;
		$z1 = $tz - $ry * $x + $rx * $y + $s * $z;
		//convert cartesian coords back to polar
		//eccentricity-squared of destination ellipsoid from Annexe B B1
		$e2 = 1 - ( $to['b'] * $to['b'] ) / ( $to['a'] * $to['a'] );
		$p = sqrt( $x1 * $x1 + $y1 * $y1 );
		$Phi = atan2( $z1, $p * ( 1 - $e2 ) );
		$Phi1 = 2 * M_PI;
		$accuracy = 0.000001 / $to['a']; //0.01 mm accuracy, though the OS transform itself only has 4-5 metres
		$loopcount = 0;
		//due to number precision, it is possible to get infinite loops here for extreme cases (especially for invalid parameters)
		//in tests, upto 4 loops are needed - if it reaches 100, assume it must be infinite, and break out
		while( abs( $Phi - $Phi1 ) > $accuracy && $loopcount++ < 100 ) {
			$sin_Phi = sin( $Phi );
			$Nu = $to['a'] / sqrt( 1 - $e2 * $sin_Phi * $sin_Phi );
			$Phi1 = $Phi;
			$Phi = atan2( $z1 + $e2 * $Nu * $sin_Phi, $p );
		}
		$Lambda = atan2( $y1, $x1 );
		$H = ( $p / cos( $Phi ) ) - $Nu;
		//done converting - return in degrees
		$latitude = rad2deg($Phi);
		$longitude = rad2deg($Lambda);
		return $this->generic_lat_long_coords( $return_type, $latitude, $longitude, $has_height ? $H : null );
	}

	//polynomial transforming lat-long coordinates, such as OSi/LPS (Irish) Polynomial Transformation
	private $polycoeff_OSiLPS = array(
		'order' => 3,
		'positive' => true,
		'latm' => 53.5,
		'longm' => -7.7,
		'k0' => 0.1,
		'A' => array(
			array(0.763,0.123,0.183,-0.374),
			array(-4.487,-0.515,0.414,13.110),
			array(0.215,-0.570,5.703,113.743),
			array(-0.265,2.852,-61.678,-265.898)
		),
		'B' => array(
			array(-2.810,-4.680,0.170,2.163),
			array(-0.341,-0.119,3.913,18.867),
			array(1.196,4.877,-27.795,-284.294),
			array(-0.887,-46.666,-95.377,-853.950)
		)
	);
	public function get_polynomial_coefficients($name) {
		$name = 'polycoeff_'.$name;
		if( isset( $this->$name ) ) {
			return $this->$name;
		}
		return false;
	}
	public function create_polynomial_coefficients( $order, $positive, $latm, $longm, $k0, $A, $B ) {
		if( !isset($A) || !is_array($A) || !isset($A[0]) || !is_array($A[0]) || !isset($B) || !is_array($B) || !isset($B[0]) || !is_array($B[0]) ) {
			return false;
		}
		return array( 'order' => $order, 'positive' => $positive, 'latm' => $latm, 'longm' => $longm, 'k0' => $k0, 'A' => $A, 'B' => $B );
	}
	private function polynomial3( $latitude, $longitude, $latm, $longm, $k0, $A, $B ) {
		//transformation according to Transformations and OSGM15 User Guide
		//https://www.ordnancesurvey.co.uk/business-government/tools-support/os-net/for-developers
		$U = $k0 * ( $latitude - $latm );
		$V = $k0 * ( $longitude - $longm );
		//0 order
		$dlat = ( $A[0][0] +
			//1st order
			$A[1][0] * $U + $A[0][1] * $V + $A[1][1] * $U * $V +
			//2nd order
			$A[2][0] * $U*$U + $A[0][2] * $V*$V + $A[2][1] * $U*$U * $V + $A[1][2] * $U * $V*$V + $A[2][2] * $U*$U * $V*$V +
			//3rd order
			$A[3][0] * $U*$U*$U + $A[0][3] * $V*$V*$V + $A[3][1] * $U*$U*$U * $V + $A[1][3] * $U * $V*$V*$V + $A[3][2] * $U*$U*$U * $V*$V + $A[2][3] * $U*$U * $V*$V*$V + $A[3][3] * $U*$U*$U * $V*$V*$V
		) / 3600;
		//0 order
		$dlong = ( $B[0][0] +
			//1st order
			$B[1][0] * $U + $B[0][1] * $V + $B[1][1] * $U * $V +
			//2nd order
			$B[2][0] * $U*$U + $B[0][2] * $V*$V + $B[2][1] * $U*$U * $V + $B[1][2] * $U * $V*$V + $B[2][2] * $U*$U * $V*$V +
			//3rd order
			$B[3][0] * $U*$U*$U + $B[0][3] * $V*$V*$V + $B[3][1] * $U*$U*$U * $V + $B[1][3] * $U * $V*$V*$V + $B[3][2] * $U*$U*$U * $V*$V + $B[2][3] * $U*$U * $V*$V*$V + $B[3][3] * $U*$U*$U * $V*$V*$V
		) / 3600;
		return array( $dlat, $dlong );
	}
	public function polynomial_transform( $latitude, $longitude, $coefficients = false, $return_type = false ) {
		list( $latitude, $longitude, $coefficients, $return_type ) = $this->expand_params( $latitude, 2, 2, array( $longitude, $coefficients, $return_type ) );
		if( is_array($coefficients) && $coefficients['order'] == 3 ) {
			//currently, third order polynomial is the only supported type
			$shifts = $this->polynomial3( $latitude, $longitude, $coefficients['latm'], $coefficients['longm'], $coefficients['k0'], $coefficients['A'], $coefficients['B'] );
		} else {
			return false;
		}
		return $this->generic_lat_long_coords( $return_type, $coefficients['positive'] ? ( $latitude + $shifts[0] ) : ( $latitude - $shifts[0] ), $coefficients['positive'] ? ( $longitude + $shifts[1] ) : ( $longitude - $shifts[1] ) );
	}
	public function reverse_polynomial_transform( $latitude, $longitude, $coefficients = false, $return_type = false ) {
		list( $latitude, $longitude, $coefficients, $return_type ) = $this->expand_params( $latitude, 2, 2, array( $longitude, $coefficients, $return_type ) );
		$slat = $slong = 0;
		$latshifted = $latitude;
		$longshifted = $longitude;
		$loopcount = 0;
		do {
			//get the shifts for the current estimated point, starting with the initial easting and northing
			if( is_array($coefficients) && isset($coefficients['order']) && $coefficients['order'] == 3 ) {
				//currently, third order polynomial is the only supported type
				$shifts = $this->polynomial3( $latshifted, $longshifted, $coefficients['latm'], $coefficients['longm'], $coefficients['k0'], $coefficients['A'], $coefficients['B'] );
			} else {
				return false;
			}
			if( !$shifts ) {
				return $this->generic_lat_long_coords( $return_type, NAN, NAN );
			}
			$difflat = $slat - $shifts[0];
			$difflong = $slong - $shifts[1];
			$slat = $shifts[0];
			$slong = $shifts[1];
			//apply the new shift and try again with the shift from the new point
			$latshifted = $coefficients['positive'] ? ( $latitude - $slat ) : ( $latitude + $slat );
			$longshifted = $coefficients['positive'] ? ( $longitude - $slong ) : ( $longitude + $slong );
			//see if the shift changes with each iteration
			//unlikely to ever hit 100 iterations, even when taken to extremes
		} while( ( abs( $difflat ) > 1E-12 || abs( $difflong ) > 1E-12 ) && ++$loopcount < 100 );
		return $this->generic_lat_long_coords( $return_type, $latshifted, $longshifted );
	}

	//geodesics
	//Vincenty formulas with precision detection from https://www.movable-type.co.uk/scripts/latlong-vincenty.html and additional error handling
	//direct
	public function get_geodesic_destination( $latfrom, $longfrom, $geodeticdistance, $bearingfrom = false, $ellipsoid = false, $return_type = false ) {
		list( $latfrom, $longfrom, $geodeticdistance, $bearingfrom, $ellipsoid, $return_type ) =
			$this->expand_params( $latfrom, 2, 2, array( $longfrom, $geodeticdistance, $bearingfrom, $ellipsoid, $return_type ) );
		list( $geodeticdistance, $bearingfrom, $ellipsoid, $return_type ) = $this->expand_params( $geodeticdistance, 2, 2, array( $bearingfrom, $ellipsoid, $return_type ) );
		if( !is_array($ellipsoid) || !isset($ellipsoid['a']) || !isset($ellipsoid['b']) ) {
			return false;
		}
		$f = ( $ellipsoid['a'] - $ellipsoid['b'] ) / $ellipsoid['a'];
		$longfrom = $this->wrap_around_longitude($longfrom);
		$lat1 = deg2rad($latfrom);
		$long1 = deg2rad($longfrom);
		$bearingfrom = deg2rad($bearingfrom);
		//U is called "reduced latitude", latitude on the auxiliary sphere
		$tanU1 = ( 1 - $f ) * tan($lat1);
		//use trig identities instead of atan then cos or sin
		$cosU1 = 1 / sqrt( 1 + pow( $tanU1, 2 ) );
		$sinU1 = $tanU1 * $cosU1;
		$s1 = atan2( $tanU1, cos($bearingfrom) );
		//a = forward azimuth of the geodesic at the equator, if it were extended that far
		$sinA = $cosU1 * sin($bearingfrom);
		$sin2A = pow( $sinA, 2 );
		$cos2A = 1 - $sin2A;
		$u2 = $cos2A * ( pow( $ellipsoid['a'], 2 ) - pow( $ellipsoid['b'], 2 ) ) / pow( $ellipsoid['b'], 2 );
		$A = 1 + $u2 * ( 4096 + $u2 * ( $u2 * ( 320 - 175 * $u2 ) - 768 ) ) / 16384;
		$B = $u2 * ( 256 + $u2 * ( $u2 * ( 74 - 47 * $u2 ) - 128 ) ) / 1024;
		//s = angular separation between points on auxiliary sphere
		$s = $geodeticdistance / ( $ellipsoid['b'] * $A ); //initial approximation
		$loopcount = 0;
		do {
			//SM = angular separation between the midpoint of the line and the equator
			$cos2SM = cos( 2 * $s1 + $s );
			$cos22SM = pow( $cos2SM, 2 );
			$sin2s = sin($s);
			//difference in angular separation between auxiliary sphere and ellipsoid
			$deltaS = $B * $sin2s * ( $cos2SM + $B * ( cos($s) * ( 2 * pow( $cos2SM, 2 ) - 1 ) - $B * $cos2SM * ( 4 * pow( $sin2s, 2 ) - 3 ) * ( 4 * $cos22SM - 3 ) / 6 ) / 4 );
			$sold = $s;
			$s = $geodeticdistance / ( $ellipsoid['b'] * $A ) + $deltaS;
		} while( abs( $s - $sold ) < 1E-12 && ++$loopcount < 500 );
		$sins = sin($s);
		$coss = cos($s);
		$sinbearing = sin($bearingfrom);
		$cosbearing = cos($bearingfrom);
		$lat2 = atan2( $sinU1 * $coss + $cosU1 * $sins * $cosbearing, ( 1 - $f ) * sqrt( $sin2A + pow( $sinU1 * $sins - $cosU1 * $coss * $cosbearing, 2 ) ) );
		//difference in longitude of the points on the auxiliary sphere
		$lambda = atan2( $sins * $sinbearing, $cosU1 * $coss - $sinU1 * $sins * $cosbearing );
		$C = $f * $cos2A * ( 4 + $f * ( 4 - 3 * $cos2A ) ) / 16;
		//longitudinal difference of the points on the ellipsoid
		$L = $lambda - ( 1 - $C) * $f * $sinA * ( $s + $C * $sins * ( $cos2SM + $C * $coss * ( 2 * $cos22SM - 1 ) ) );
		$long2 = $long1 + $L;
		$longto = $this->wrap_around_longitude(rad2deg($long2));
		$latto = rad2deg($lat2);
		$bearing2 = atan2( $sinA, -1 * ( $sinU1 * $sins - $cosU1 * $coss * $cosbearing ) );
		$bearingto = $this->wrap_around_azimuth(rad2deg($bearing2));
		if( $return_type ) {
			//sprintf to produce simple numbers instead of scientific notation
			$bearingto = sprintf( '%F', $bearingto );
			//bearing can be 360, after rounding
			if( $bearingto == '360.000000' ) {
				$bearingto = '0.000000';
			}
			$deg = is_string($return_type) ? $return_type : '&deg;';
			return $this->generic_lat_long_coords( $return_type, $latto, $longto ) . ' @ ' . $bearingto . $deg ;
		} else {
			//avoid stupid -0 by adding 0
			return array( $latto + 0, $longto + 0, $bearingto + 0 );
		}
	}
	//inverse
	public function get_geodesic( $latfrom, $longfrom, $latto, $longto = false, $ellipsoid = false, $return_type = false ) {
		list( $latfrom, $longfrom, $latto, $longto, $ellipsoid, $return_type ) =
			$this->expand_params( $latfrom, 2, 2, array( $longfrom, $latto, $longto, $ellipsoid, $return_type ) );
		list( $latto, $longto, $ellipsoid, $return_type ) = $this->expand_params( $latto, 2, 2, array( $longto, $ellipsoid, $return_type ) );
		if( !is_array($ellipsoid) || !isset($ellipsoid['a']) || !isset($ellipsoid['b']) ) {
			return false;
		}
		$f = ( $ellipsoid['a'] - $ellipsoid['b'] ) / $ellipsoid['a'];
		$longfrom = $this->wrap_around_longitude($longfrom);
		$longto = $this->wrap_around_longitude($longto);
		$lat1 = deg2rad($latfrom);
		$long1 = deg2rad($longfrom);
		$lat2 = deg2rad($latto);
		$long2 = deg2rad($longto);
		$L = $long2 - $long1; //longitudinal difference
		//U is called "reduced latitude", latitude on the auxiliary sphere
		$tanU1 = ( 1 - $f ) * tan($lat1);
		$tanU2 = ( 1 - $f ) * tan($lat2);
		//use trig identities instead of atan then cos or sin
		$cosU1 = 1 / sqrt( 1 + pow( $tanU1, 2 ) );
		$cosU2 = 1 / sqrt( 1 + pow( $tanU2, 2 ) );
		$sinU1 = $tanU1 * $cosU1;
		$sinU2 = $tanU2 * $cosU2;
		$lambda = $L; //difference in longitude on an auxiliary sphere, initial approximation
		//start detecting failures, not part of Vincenty formulas
		$detectedproblem = false;
		//~1 iteration for spheres, ~3-4 for spheroids
		//extreme spheroids need more; a=4b can need 80+
		//allow max 500 iterations, or bearing results flip around when resolution fails
		$maxloops = 500;
		//check if the points are somewhat far from each other, for use when handling precision errors
		//either the latitudes are more than half a circle different, so that the points are near opposite poles
		//or the latitudes are nearly opposite and longitudes are similarly different (points far apart near-ish the equator)
		$antipodal = abs( $lat2 - $lat1 ) > M_PI / 2 || ( abs( $lat1 + $lat2 ) < M_PI / 2 && cos($L) <= 0 );
		$prolate = $ellipsoid['b'] > $ellipsoid['a'];
		do {
			$sinS = sqrt( pow( $cosU2 * sin($lambda), 2 ) + pow( $cosU1 * $sinU2 - $sinU1 * $cosU2 * cos($lambda), 2 ) );
			//prolate spheroids often manage to resolve in spite of having a tiny sinS
			//their antipodal distances are much harder because they do not travel over the pole, and need the full calculation
			if( abs( $sinS ) < PHP_FLOAT_EPSILON && ( !$prolate || !$antipodal ) ) {
				//not part of Vincenty formulas
				//when the sine is smaller than the number precision, the iteration can give wildly inaccurate results, happens when:
				//* the points are almost opposite each other; measure the arc via the north pole
				//* the points are very close to each other, length becomes 0
				//* the points are the same, length is 0
				$detectedproblem = true;
				$cos2A = 1; //how much of the transit is via the minor axis (smaller numbers use the major axis dimensions)
				$cos2SM = $sinS = $cosS = 0; //these will not be used
				$s = $antipodal ? M_PI : 0; //distance around the spheroid
				break;
			}
			$cosS = $sinU1 * $sinU2 + $cosU1 * $cosU2 * cos($lambda);
			$s = atan2( $sinS, $cosS ); //angular separation between points
			//a = forward azimuth of the geodesic at the equator, if it were extended that far
			$sinA = $cosU1 * $cosU2 * sin($lambda) / $sinS;
			$cos2A = 1 - pow( $sinA, 2 );
			//SM is angular separation between the midpoint of the line and the equator
			//at an equatorial line, cos2A becomes 0, and dividing by 0 is invalid, so set cos2SM to 0 instead
			$cos2SM = $cos2A ? ( $cosS - 2 * $sinU1 * $sinU2 / $cos2A ) : 0;
			$C = $f * $cos2A * ( 4 + $f * ( 4 - 3 * $cos2A ) ) / 16;
			$lambdaold = $lambda;
			$lambda = $L + ( 1 - $C ) * $f * $sinA * ( $s + $C * $sinS * ( $cos2SM + $C * $cosS * ( -1 + 2 * pow( $cos2SM, 2 ) ) ) );
			//lambda > PI when it fails to resolve, but also sometimes when it works - rely only on loop counting
		} while( abs( $lambda - $lambdaold ) > 1E-12 && --$maxloops );
		$detectedproblem = $detectedproblem || !$maxloops;
		$u2 = $cos2A * ( pow( $ellipsoid['a'], 2 ) - pow( $ellipsoid['b'], 2 ) ) / pow( $ellipsoid['b'], 2 );
		$A = 1 + $u2 * ( 4096 + $u2 * ( $u2 * ( 320 - 175 * $u2 ) - 768 ) ) / 16384;
		$B = $u2 * ( 256 + $u2 * ( $u2 * ( 74 - 47 * $u2 ) - 128 ) ) / 1024;
		//difference in angular separation between auxiliary sphere and ellipsoid
		$deltaS = $B * $sinS * ( $cos2SM + $B * ( $cosS * ( 2 * $cos2SM - 1 ) - $B * $cos2SM * ( 4 * pow( $sinS, 2 ) - 3 ) * ( 4 * $cos2SM - 3 ) / 6 ) / 4 );
		$geodeticdistance = $ellipsoid['b'] * $A * ( $s - $deltaS );
		$problemdata = null;
		if( $detectedproblem ) {
			//error handling to try to get useful bearings - not part of the Vincenty formulas
			$problemdata = array( 'detectiontype' => $maxloops ? 'BELOW_RESOLUTION' : 'DID_NOT_CONVERGE' );
			if( $antipodal ) {
				if( abs( $latfrom - $latto ) == 180 || ( $latfrom + $latto == 0 && abs( $longto - $longfrom ) == 180 ) ) {
					$problemdata['separation'] = 'ANTIPODAL';
				} else {
					$problemdata['separation'] = 'NEARLY_ANTIPODAL';
				}
			} else {
				$problemdata['separation'] = 'NEARLY_IDENTICAL';
			}
			if( $latfrom == $latto && ( $longfrom == $longto || $latfrom == 90 || $latfrom == -90 ) ) {
				//identical points, nothing useful can be returned
				$bearingfrom = $bearingto = 0;
				$problemdata['distanceconfidence'] = 1;
				$problemdata['azimuthconfidence'] = 1;
				$problemdata['separation'] = 'IDENTICAL';
			} elseif( $latfrom == 90 ) {
				//it seems to always converge when one of the points is on a pole, but these fixes are provided just in case they are needed
				//with Vincenty formulae, azimuth at 90,* is measured with 180 lined up with the longitude, like it would at 0,<longitude>
				$bearingfrom = M_PI + $long1 - $long2;
				//it comes down the longto meridian, so it will always be seen as 180, even when latto is -90
				$bearingto = M_PI;
				$problemdata['distanceconfidence'] = ( $latto == -90 ) ? 1 : 0.99;
				$problemdata['azimuthconfidence'] = 1;
			} elseif( $latfrom == -90 ) {
				//azimuth at -90,* is measured with 0 lined up with the longitude
				$bearingfrom = $long2 - $long1;
				$bearingto = 0;
				$problemdata['distanceconfidence'] = ( $latto == 90 ) ? 1 : 0.99;
				$problemdata['azimuthconfidence'] = 1;
			} elseif( $latto == 90 ) {
				//always north up their own meridian
				$bearingfrom = 0;
				$bearingto = $long2 - $long1;
				$problemdata['distanceconfidence'] = ( $latfrom == -90 ) ? 1 : 0.99;
				$problemdata['azimuthconfidence'] = 1;
			} elseif( $latto == -90 ) {
				//always south
				$bearingfrom = M_PI;
				$bearingto = M_PI + $long1 - $long2;
				$problemdata['distanceconfidence'] = ( $latfrom == 90 ) ? 1 : 0.99;
				$problemdata['azimuthconfidence'] = 1;
			} elseif( $antipodal ) {
				$problemdata['distanceconfidence'] = ( $problemdata['detectiontype'] == 'DID_NOT_CONVERGE' ) ? 0.6 : 0.98;
				if( $prolate ) {
					//prolate spheroid, the direction will be east or west
					if( sin( $long2 - $long1 ) < 0 ) {
						$bearingfrom = M_PI / -2;
						$bearingto = M_PI / -2;
					} else {
						$bearingfrom = M_PI / 2;
						$bearingto = M_PI / 2;
					}
					$problemdata['azimuthconfidence'] = 0.9;
				} else {
					//with antipodal points, always travel via the poles; choose the closest one if the points are not quite identical, or north otherwise
					if( $lat1 + $lat2 < 0 ) {
						$bearingfrom = M_PI;
						$bearingto = 0;
					} else {
						$bearingfrom = 0;
						$bearingto = M_PI;
					}
					$problemdata['azimuthconfidence'] = ( abs( $longto - $longfrom ) == 180 ) ? 1 : 0.9;
				}
				//in this case, the length is left with whatever it was
			} else {
				//points close together; below number resolution required for trig functions
				//can be calculated using basic geometry near the equator, but this is nonsense near the poles, back to being the original problem
				//this cannot be calculated in any reliable way, but this approximation is better than nothing over much of the ellipsoid
				$north = $latto > $latfrom;
				$south = $latfrom > $latto;
				$east = $longto > $longfrom || $longfrom - $longto >= 180;
				$west = $longfrom > $longto || $longto - $longfrom > 180;
				if( $north && $east ) {
					$bearingfrom = $bearingto = M_PI / 4;
				} elseif( !$north && !$south && $east ) {
					$bearingfrom = $bearingto = M_PI / 2;
				} elseif( $south && $east ) {
					$bearingfrom = $bearingto = 3 * M_PI / 4;
				} elseif( $south && !$east && !$west ) {
					$bearingfrom = $bearingto = M_PI;
				} elseif( $south && $west ) {
					$bearingfrom = $bearingto = 5 * M_PI / 4;
				} elseif( !$north && !$south && $west ) {
					$bearingfrom = $bearingto = 3 * M_PI / 2;
				} elseif( $north && $west ) {
					$bearingfrom = $bearingto = 7 * M_PI / 4;
				} else {
					$bearingfrom = $bearingto = 0;
				}
				$problemdata['distanceconfidence'] = 0.98;
				$problemdata['azimuthconfidence'] = 0.5 * cos($lat1);
			}
		} else {
			//actual measurement
			$bearingfrom = atan2( $cosU2 * sin($lambda), ( $cosU1 * $sinU2 ) - ( $sinU1 * $cosU2 * cos($lambda) ) );
			$bearingto = atan2( $cosU1 * sin($lambda), ( $cosU1 * $sinU2 * cos($lambda) ) - ( $sinU1 * $cosU2 ) );
		}
		//Vincenty formula returns bearings of -180 to 180, and error handling can make numbers greater than expected bounds
		$bearingfrom = $this->wrap_around_azimuth(rad2deg($bearingfrom));
		$bearingto = $this->wrap_around_azimuth(rad2deg($bearingto));
		if( $return_type ) {
			//sprintf to produce simple numbers instead of scientific notation
			$bearingfrom = sprintf( '%F', $bearingfrom );
			$bearingto = sprintf( '%F', $bearingto );
			//bearing can be 360, after rounding
			if( $bearingfrom == '360.000000' ) {
				$bearingfrom = '0.000000';
			}
			if( $bearingto == '360.000000' ) {
				$bearingto = '0.000000';
			}
			$deg = is_string($return_type) ? $return_type : '&deg;';
			$angle = is_string($return_type) ? '>' : '&gt;';
			return ( ( $detectedproblem || $lambda > M_PI || !$maxloops ) ? '~ ' : '' ) . sprintf( '%F', $geodeticdistance ) . ' m, ' . $bearingfrom . $deg . ' =' . $angle . ' ' . $bearingto . $deg ;
		} else {
			//avoid stupid -0 by adding 0
			return array( $geodeticdistance, $bearingfrom + 0, $bearingto + 0, $problemdata );
		}
	}
	
	//lat-long based geoid grid
	private $geoidcache = array();
	private $geoidgrd_OSGM15_Belfast = array(
		'name' => 'OSGM15_Belfast',
		'latmin' => 51.000000,
		'latmax' => 56.000000,
		'longmin' => -11.500000,
		'longmax' => -5.000000,
		'latspacing' => 0.013333,
		'longspacing' => 0.020000,
		'latstepdp' => 6,
		'longstepdp' => 6,
	);
	private $geoidgrd_OSGM15_Malin = array(
		'name' => 'OSGM15_Malin',
		'latmin' => 51.000000,
		'latmax' => 56.000000,
		'longmin' => -11.500000,
		'longmax' => -5.000000,
		'latspacing' => 0.013333,
		'longspacing' => 0.020000,
		'latstepdp' => 6,
		'longstepdp' => 6,
	);
	private $geoidgrd_EGM96_ww15mgh = array(
		'name' => 'EGM96_ww15mgh',
		'latmin' => -90.000000,
		'latmax' => 90.000000,
		'longmin' => .000000,
		'longmax' => 360.000000,
		'latspacing' => .250000,
		'longspacing' => .250000,
		'latstepdp' => 6,
		'longstepdp' => 6,
	);
	public function get_geoid_grd($name) {
		$name = 'geoidgrd_'.$name;
		if( isset( $this->$name ) ) {
			return $this->$name;
		}
		return false;
	}
	public function create_geoid_grd( $name, $latmin, $latmax, $longmin, $longmax, $latspacing, $longspacing, $latstepdp, $longstepdp ) {
		if( !is_string($name) || !strlen($name) || !( $latspacing * 1 ) || !( $longspacing * 1 ) || $latspacing < 0 || $longspacing < 0 || $latmax < $latmin || $longmax < $longmin ) {
			return false;
		}
		return array(
			'name' => $name,
			'latmin' => $latmin,
			'latmax' => $latmax,
			'longmin' => $longmin,
			'longmax' => $longmax,
			'latspacing' => $latspacing,
			'longspacing' => $longspacing,
			'latstepdp' => $latstepdp,
			'longstepdp' => $longstepdp
		);
	}
	public function create_geoid_record( $recordindex, $geoid_undulation ) {
		//this does not actually get used in this format - it exists only to force the right columns to exist
		return array( 'name' => "r_$recordindex", 'height' => $geoid_undulation );
	}
	public function cache_geoid_records( $name, $records ) {
		if( !is_string($name) || !strlen($name) ) {
			return false;
		}
		if( !isset($this->geoidcache[$name]) || !$this->geoidcache[$name] ) {
			//nothing has ever been cached for this geoid set, create the cache
			$this->geoidcache[$name] = array();
		}
		if( !$records ) {
			return true;
		}
		//allow it to accept either a single record, or an array of records
		if( !is_array($records) || isset($records['name']) ) {
			$records = array($records);
		}
		foreach( $records as $onerecord ) {
			if( !is_array($onerecord) || !isset($onerecord['name']) || !isset($onerecord['height']) ) {
				return false;
			}
			$this->geoidcache[$name][$onerecord['name']] = $onerecord['height'];
		}
		return true;
	}
	public function delete_geoid_cache( $name = '' ) {
		if( strlen($name) ) {
			unset($this->geoidcache[$name]);
		} else {
			$this->geoidcache = array();
		}
	}
	private function get_geoid_records( $geoidgrd, $records, $fetch_records ) {
		if( !isset($this->geoidcache[$geoidgrd['name']]) || !$this->geoidcache[$geoidgrd['name']] ) {
			//nothing has ever been cached for this geoid set, create the cache
			$this->geoidcache[$geoidgrd['name']] = array();
		}
		$unknown_records = array();
		$recordnames = array();
		foreach( $records as $onerecord ) {
			$recordnames[] = $id = 'r_'.$onerecord['recordindex'];
			if( !isset($this->geoidcache[$geoidgrd['name']][$id]) ) {
				$onerecord['latitude'] = round( $onerecord['latitude'], $geoidgrd['latstepdp'] );
				$onerecord['longitude'] = round( $onerecord['longitude'], $geoidgrd['longstepdp'] );
				$unknown_records[] = $onerecord;
				$this->geoidcache[$geoidgrd['name']][$id] = false; //false gets detected as "set", null does not
			}
		}
		if( count($unknown_records) ) {
			$this->cache_geoid_records( $geoidgrd['name'], call_user_func( $fetch_records, $geoidgrd['name'], $unknown_records ) );
		}
		$shifts = array();
		foreach( $recordnames as $id ) {
			$shifts[] = $this->geoidcache[$geoidgrd['name']][$id];
		}
		return $shifts;
	}
	public function apply_geoid( $latitude, $longitude, $height, $direction, $geoidgrd, $fetch_records = false, $return_type = false, $deny_bad_coords = false ) {
		list( $latitude, $longitude, $height, $direction, $geoidgrd, $fetch_records, $return_type, $deny_bad_coords ) =
			$this->expand_params( $latitude, 2, 3, array( $longitude, $height, $direction, $geoidgrd, $fetch_records, $return_type, $deny_bad_coords ) );
		if( !$geoidgrd || !( $geoidgrd['latspacing'] * $geoidgrd['longspacing'] ) || $geoidgrd['latspacing'] < 0 || $geoidgrd['longspacing'] < 0 || $geoidgrd['latmax'] < $geoidgrd['latmin'] || $geoidgrd['longmax'] < $geoidgrd['longmin'] || !is_callable($fetch_records) ) {
			return false;
		}
		$longitude = $this->wrap_around_longitude($longitude);
		//OSI data has a rounding precision problem that causes it to miss its end point; 0.013333 is not the same as 5 / 375
		$latrange = $geoidgrd['latmax'] - $geoidgrd['latmin'];
		$latspacing = $latrange ? ( $latrange / round( $latrange / $geoidgrd['latspacing'] ) ) : $geoidgrd['latspacing'];
		$longrange = $geoidgrd['longmax'] - $geoidgrd['longmin'];
		$longperrow = round( $longrange / $geoidgrd['longspacing'] );
		$longspacing = $longrange ? ( $longrange / $longperrow ) : $geoidgrd['longspacing'];
		$longperrow++; //fencepost problem
		if( $longitude < 0 && $longitude < $geoidgrd['longmin'] && $geoidgrd['longmax'] > 180 ) {
			//geoid data may use -180 <= x <= 180, or it may use 0 <= x <= 360
			//this also allows them to use odd ranges like -10 to 200
			$longitude += 360;
		}
		if( $latitude < $geoidgrd['latmin'] || $latitude > $geoidgrd['latmax'] || $longitude < $geoidgrd['longmin'] || $longitude > $geoidgrd['longmax'] ) {
			//the point is not within range, so this is not valid
			if( $deny_bad_coords ) {
				return false;
			} else {
				return $return_type ? 'NaN' : NAN;
			}
		}
		//grid cell, latitude indexes are from the top, not the bottom
		$lat_index = ceil( ( $geoidgrd['latmax'] - $latitude ) / $latspacing );
		$long_index = floor( ( $longitude - $geoidgrd['longmin'] ) / $longspacing );
		//lat-long of southwest corner
		$x0 = $geoidgrd['longmin'] + $long_index * $longspacing;
		$y0 = $geoidgrd['latmax'] - $lat_index * $latspacing;
		//offset within grid
		$dx = $longitude - $x0;
		$dy = $latitude - $y0;
		$records = array();
		$records[0] = array( 'latitude' => $y0, 'longitude' => $x0, 'latindex' => $lat_index, 'longindex' => $long_index, 'recordindex' => $longperrow * $lat_index + $long_index );
		//allow points to sit on the last line by setting them to the same values as each other (otherwise it could never work on the north pole)
		if( $dx ) {
			$records[1] = array( 'latitude' => $y0, 'longitude' => ( $long_index + 1 ) * $longspacing + $geoidgrd['longmin'], 'latindex' => $lat_index, 'longindex' => $long_index + 1 );
			$records[1]['recordindex'] = $longperrow * $records[1]['latindex'] + $records[1]['longindex'];
		} else {
			$records[1] = $records[0];
		}
		if( $dy ) {
			$records[2] = array( 'latitude' => $geoidgrd['latmax'] - ( $lat_index - 1 ) * $latspacing, 'longitude' => $records[1]['longitude'], 'latindex' => $lat_index - 1, 'longindex' => $dx ? ( $long_index + 1 ) : $long_index );
			$records[3] = array( 'latitude' => $records[2]['latitude'], 'longitude' => $records[0]['longitude'], 'latindex' => $lat_index - 1, 'longindex' => $long_index );
			$records[2]['recordindex'] = $longperrow * $records[2]['latindex'] + $records[2]['longindex'];
			$records[3]['recordindex'] = $longperrow * $records[3]['latindex'] + $records[3]['longindex'];
		} else {
			$records[2] = $records[1];
			$records[3] = $records[0];
		}
		//fetch records if needed
		$shifts = $this->get_geoid_records( $geoidgrd, $records, $fetch_records );
		if( !is_numeric($shifts[0]) || !is_numeric($shifts[1]) || !is_numeric($shifts[2]) || !is_numeric($shifts[3]) ) {
			//this is within the grid, but data was not available
			if( $deny_bad_coords ) {
				return false;
			} else {
				return $return_type ? 'NaN' : NAN;
			}
		}
		//offset values
		$t = $dx / $longspacing;
		$u = $dy / $latspacing;
		//bilinear interpolated shifts
		$sg = ( ( 1 - $t ) * ( 1 - $u ) * $shifts[0] ) + ( $t * ( 1 - $u ) * $shifts[1] ) + ( $t * $u * $shifts[2] ) + ( ( 1 - $t ) * $u * $shifts[3] );
		$height = $direction ? ( $height - $sg ) : ( $height + $sg );
		if( $return_type ) {
			//sprintf to produce simple numbers instead of scientific notation
			return sprintf( '%F', $height );
		} else {
			return $height;
		}
	}

	//conversions between latitude/longitude and cartesian coordinates
	function xyz_to_lat_long( $X, $Y, $Z = false, $ellipsoid = false, $return_type = false) {
		list( $X, $Y, $Z, $ellipsoid, $return_type ) = $this->expand_params( $X, 3, 3, array( $Y, $Z, $ellipsoid, $return_type ) );
		//conversion according to "A guide to coordinate systems in Great Britain" Annexe B:
		//https://www.ordnancesurvey.co.uk/documents/resources/guide-coordinate-systems-great-britain.pdf
		if( !is_array($ellipsoid) || !isset($ellipsoid['a']) || !isset($ellipsoid['b']) ) {
			return false;
		}
		$e2 = 1 - pow( $ellipsoid['b'], 2 ) / pow( $ellipsoid['a'], 2 );
		$lon = atan2( $Y * 1, $X * 1 );
		$p = sqrt( pow( $X, 2 ) + pow( $Y, 2 ) );
		$lat = atan2( $Z, $p * ( 1 - $e2 ) );
		$loopcount = 0;
		do {
			$v = $ellipsoid['a'] / sqrt( 1 - ( $e2 * pow( sin($lat), 2 ) ) );
			//https://gssc.esa.int/navipedia/index.php/Ellipsoidal_and_Cartesian_Coordinates_Conversion alternative
			//height misses one iteration but the result is the same when diff is 0
			//$height = ( $p / cos($lat) ) - $v;
			//$lat = atan2( $Z, $p * ( 1 - ( $e2 * $v / ( $v + $height ) ) ) );
			$oldlat = $lat;
			$lat = atan2( $Z + ( $e2 * $v * sin( $lat ) ), $p );
			//due to number precision, it is possible to get infinite loops here for extreme cases, when the difference is enough to roll over a digit then roll it back on the next iteration
			//in tests, upto 10 loops are needed for extremes - if it reaches 100, assume it must be infinite, and break out
		} while( abs( $lat - $oldlat ) > 0 && ++$loopcount < 100 );
		$height = ( $p / cos($lat) ) - $v;
		return $this->generic_lat_long_coords( $return_type, rad2deg($lat), rad2deg($lon), $height );
	}
	public function lat_long_to_xyz( $latitude, $longitude, $height = false, $ellipsoid = false, $return_type = false ) {
		list( $latitude, $longitude, $height, $ellipsoid, $return_type ) = $this->expand_params( $latitude, 2, 3, array( $longitude, $height, $ellipsoid, $return_type ) );
		if( !is_array($ellipsoid) || !isset($ellipsoid['a']) || !isset($ellipsoid['b']) ) {
			return false;
		}
		$height = is_numeric($height) ? $height : 0;
		$latitude = deg2rad($latitude);
		$longitude = deg2rad($longitude);
		$e2 = 1 - pow( $ellipsoid['b'], 2 ) / pow( $ellipsoid['a'], 2 );
		$v = $ellipsoid['a'] / sqrt( 1 - ( $e2 * pow( sin($latitude), 2 ) ) );
		$X = ( $v + $height ) * cos($latitude) * cos($longitude);
		$Y = ( $v + $height ) * cos($latitude) * sin($longitude);
		$Z = ( ( ( 1 - $e2 ) * $v ) + $height ) * sin($latitude);
		if( $return_type ) {
			return sprintf( '%F', $X ) . ' ' . sprintf( '%F', $Y ) . ' ' . sprintf( '%F', $Z );
		} else {
			return array( $X, $Y, $Z );
		}
	}

}

?>