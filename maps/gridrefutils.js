/******************************************************
               Grid Reference Utilities
Version 3.0 - Written by Mark Wilton-Jones 6/5/2010
Updated 9/5/2010 to add ddFormat
Updated 16/9/2020 to correct Helmert height handling 
Updated 23/12/2020 to add support for shift grids,
geoids, polynomial transformation, geodesics, trucated
grid references, floating point grid references, high
precision GPS coordinates, and more input formats
*******************************************************

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

var gridRefUtilsToolbox;
(function () {

	//class instantiation forcing use as a singleton - state can also be stored if needed (or can change to use separate instances)
	function GridRefUtils() {}
	var onlyInstance;
	gridRefUtilsToolbox = function () {
		if( !onlyInstance ) {
			onlyInstance = new GridRefUtils();
		}
		return onlyInstance;
	};

	//define return types
	GridRefUtils.prototype.DATA_ARRAY = 0;
	GridRefUtils.prototype.HTML = 1;
	GridRefUtils.prototype.TEXT = 2;

	//common return values
	function genericXYCoords( returnType, precise, easting, northing, height, additional ) {
		if( returnType ) {
			if( isNaN(easting) || isNaN(northing) ) {
				return 'OUT_OF_GRID';
			} else if( precise ) {
				return prettyNumber( easting, 6 ) + ',' + prettyNumber( northing, 6 ) + ( isNumeric(height) ? ( ',' + prettyNumber( height, 6 ) ) : '' );
			} else {
				return Math.round(easting) + ',' + Math.round(northing) + ( isNumeric(height) ? ( ',' + Math.round(height) ) : '' );
			}
		} else {
			var returnarray = [ easting, northing ];
			if( isNumeric(height) ) {
				returnarray[returnarray.length] = height;
			}
			if( typeof(additional) != 'undefined' ) {
				returnarray[returnarray.length] = additional;
			}
			return returnarray;
		}
	}
	function genericLatLongCoords( returnType, latitude, longitude, height ) {
		//force the longitude between -180 and 180
		longitude = wrapAroundLongitude(longitude);
		if( returnType ) {
			//10 decimal places DD ~= 8 decimal places DM ~= 6 decimal places DMS
			longitude = prettyNumber( longitude, 10 );
			if( longitude == '-180.0000000000' ) {
				//rounding can make -179.9...9 end up as -180
				longitude = '180.0000000000';
			}
			var deg = ( returnType == GridRefUtils.prototype.TEXT ) ? '\u00B0' : '&deg;'; 
			return prettyNumber( latitude, 10 ) + deg + ', ' + longitude + deg + ( isNumeric(height) ? ( ', ' + prettyNumber( height, 6 ) ) : '' );
		} else {
			var temparray = [ latitude, longitude ];
			if( isNumeric(height) ) {
				temparray[temparray.length] = height;
			}
			return temparray;
		}
	}

	//common inputs
	function expandParams( ar1, afrom, ato, ar2 ) {
		//JavaScript doesn't support overloaded functions/methods
		//this function makes it possible to extract an initial array, returning parameters in a standard order
		var param = 0;
		var handback = [];
		if( ar1 && ar1.constructor == Array ) {
			for( var i = 0; i < ar1.length; i++ ) {
				if( param == ato ) {
					//ignore extra cells
					break;
				}
				handback[param] = ar1[i];
				param++;
			}
			//this forces attempts to use an array of one item to then continue without the next param(s)
			param = Math.max( param, afrom );
		} else {
			//not using an array
			handback[0] = ar1;
			param = 1;
		}
		for( var j = 0; j < ar2.length; j++ ) {
			if( param < ato && ar2[j] && typeof(ar2[j]) == 'object' && ar2[j].constructor != Array ) {
				//the first parameter that is an object must at least be at the "to" index
				//this allows "height" to be put either inside or outside an array, and still be optional
				//additional parameters in between are preserved and the calling function will continue with them
				param = ato;
			}
			handback[param] = ar2[j];
			param++;
		}
		return handback;
	}

	//flooring to a number of significate figures
	function floorPrecision( num, precision ) {
		//regular floor does not support the precision param
		//usual method floor( number * pow(10, precision) ) / pow(10, precision) gets floating point errors with ( 9.7, 2 )
		//string manipulation fails with 1E-15
		//this function is immune to both problems
		var rounded = round( num, precision );
		if( rounded > num ) {
			//Math.pow( 10, -1 * precision ); has floating point errors with 4 and 5, PHP does not
			rounded -= '1e' + ( -1 * precision );
		}
		return rounded;
	}

	//longitude must be within -180<num<=180
	function wrapAroundLongitude( num, negativeHalf ) {
		var newnum;
		if( !isNumeric(num) ) {
			return num;
		}
		if( Math.abs(num) > 1440 ) {
			//this can end up with floating point errors much more easily than simple + or -
			//use it only in cases where the error is very large, to avoid the loops taking too long
			num %= 360;
		}
		while( ( !negativeHalf && num > 180 ) || ( negativeHalf && num >= 180 ) ) {
			newnum = num - 360;
			if( newnum == num ) {
				//avoid infinite loops with numbers high enough to need scientific notation
				return num;
			}
			num = newnum;
		}
		while( ( !negativeHalf && num <= -180 ) || ( negativeHalf && num < -180 ) ) {
			newnum = num + 360;
			if( newnum == num ) {
				//avoid infinite loops with numbers low enough to need scientific notation
				return num;
			}
			num = newnum;
		}
		return num;
	}
	//azimuth must be within 0<=num<360
	function wrapAroundAzimuth(num) {
		var newnum;
		if( !isNumeric(num) ) {
			return num;
		}
		if( Math.abs(num) > 1440 ) {
			//this can end up with floating point errors much more easily than simple + or -
			//use it only in cases where the error is very large, to avoid the loops taking too long
			num %= 360;
		}
		while( num >= 360 ) {
			newnum = num - 360;
			if( newnum == num ) {
				//avoid infinite loops with numbers high enough to need scientific notation
				return num;
			}
			num = newnum;
		}
		while( num < 0) {
			newnum = num + 360;
			if( newnum == num ) {
				//avoid infinite loops with numbers low enough to need scientific notation
				return num;
			}
			num = newnum;
		}
		return num;
	}

	//utility functions comparable to those in PHP (some functionality is missing, as it is unused in this script)
	//like Math.round but also supports precision
	function round( num, precision ) {
		if( !precision ) { return Math.round(num); }
		//for +ve precision, try to avoid floating point errors causing results with more decimal places than precision
		if( precision > 0 && num.toFixed ) { return parseFloat(num.toFixed(precision)); }
		precision = Math.pow( 10, -precision );
		var remainder = num % precision;
		var baseNumber = num - remainder;
		var halfPrecision = precision / 2;
		if( remainder >= halfPrecision ) {
			//positive numbers
			baseNumber += precision;
		} else if( -remainder > halfPrecision ) {
			//negative numbers
			baseNumber -= precision;
		}
		return baseNumber;
	}
	//pad a string using padChr on left or right until it becomes at least minLength long
	function strPad( str, minLength, padChr, padLeft) {
		str = ( str || '' ).toString();
		while( str.length < minLength ) {
			if( padLeft ) {
				str = padChr + str;
			} else {
				str += padChr;
			}
		}
		return str;
	}
	//split a string into an array - regex match works, but is between 1 and 10 times slower depending on the browser
	function strSplit(str) {
		//return str.match(/./g); //splits string into array (g flag means matches contains each overall match instead of captures)
		var strLen = str.length, outar = ['']; //empty cell - incompatible with PHP
		for( var i = 0; i < strLen; i++ ) {
			outar[i+1] = str.charAt(i);
		}
		return outar;
	}
	//check for numbers, numeric strings or objects that can be cast to numbers
	function isNumeric(num) {
		//number objects can have any value, others must be castable to a number (but null * 1 == 0 and true * 1 == 1)
		return typeof(num) == 'number' || ( num && typeof(num) != 'boolean' && !isNaN( 1 * num ) );
	}
	//produce simple numbers instead of scientific notation, equivalent to sprintf but only for %.nF
	function prettyNumber( num, precision ) {
		//cast to number first, just in case
		return ( 1 * num ).toFixed(precision);
	}

	//character grids used by map systems
	function gridToHashmap(grid2dArray) {
		//make a hashmap of arrays giving the x,y values for grid letters
		var hashmap = {}, arRow;
		for( var i = 0; i < grid2dArray.length; i++ ) {
			arRow = grid2dArray[i];
			for( var j = 0; j < arRow.length; j++ ) {
				hashmap[arRow[j]] = [j,i];
			}
		}
		return hashmap;
	}
	var UKGridSquares = [
		//the order of grid square letters in the UK NGR system - note that in the array they start from the bottom, like grid references
		//there is no I in team
		['V','W','X','Y','Z'],
		['Q','R','S','T','U'],
		['L','M','N','O','P'],
		['F','G','H','J','K'],
		['A','B','C','D','E']
	];
	var UKGridNumbers = gridToHashmap(UKGridSquares);

	//conversions between 12345,67890 and SS234789 grid reference formats
	GridRefUtils.prototype.getUKGridRef = function ( origX, origY, figures, returnType, denyBadReference, useRounding, isIrish ) {
		//no need to shuffle the isIrish parameter over, since it will always be at the end
		var args = expandParams( origX, 2, 2, [ origY, figures, returnType, denyBadReference, useRounding ] );
		origX = args[0]; origY = args[1]; figures = args[2]; returnType = args[3]; denyBadReference = args[4]; useRounding = args[5];
		if( typeof(figures) == 'undefined' || figures === null ) {
			figures = 4;
		} else {
			//enforce integer 1-25
			figures = Math.max( Math.min( Math.floor( figures ), 25 ) || 0, 1 );
		}
		//prepare factors used for enforcing a number of grid ref figures
		var insigFigures = figures - 5, x, y;
		if( useRounding ) {
			//round off unwanted detail (default to 0 in case they pass a non-number)
			x = round( origX, insigFigures ) || 0;
			y = round( origY, insigFigures ) || 0;
		} else {
			x = floorPrecision( origX, insigFigures );
			y = floorPrecision( origY, insigFigures );
		}
		var letters, errorletters = 'OUT_OF_GRID';
		if( isIrish ) {
			//the Irish grid system uses the same letter layout as the UK system, but it only has one letter, with origin at V
			var arY = Math.floor( y / 100000 );
			var arX = Math.floor( x / 100000 );
			if( arX < 0 || arX > 4 || arY < 0 || arY > 4 ) {
				//out of grid
				if( denyBadReference ) {
					return false;
				}
				letters = errorletters;
			} else {
				letters = UKGridSquares[ arY ][ arX ];
			}
		} else {
			//origin is at SV, not VV - offset it to VV instead
			x += 1000000;
			y += 500000;
			var ar1Y = Math.floor( y / 500000 );
			var ar1X = Math.floor( x / 500000 );
			var ar2Y = Math.floor( ( y % 500000 ) / 100000 );
			var ar2X = Math.floor( ( x % 500000 ) / 100000 );
			if( isNaN(ar1X) || isNaN(ar1Y) || isNaN(ar2X) || isNaN(ar2Y) || ar1X < 0 || ar1X > 4 || ar1Y < 0 || ar1Y > 4 ) {
				//out of grid - don't need to test ar2Y and ar2X since they cannot be more than 4, and ar1_ will also be less than 0 if ar2_ is
				if( denyBadReference ) {
					return false;
				}
				letters = errorletters;
			} else {
				//first grid letter is for the 500km x 500km squares
				letters = UKGridSquares[ ar1Y ][ ar1X ];
				//second grid letter is for the 100km x 100km squares
				letters += UKGridSquares[ ar2Y ][ ar2X ];
			}
		}
		//floating point errors can reappear here if using numbers after the decimal point
		x %= 100000;
		y %= 100000;
		//fix negative grid coordinates
		if( x < 0 ) {
			x += 100000;
		}
		if( y < 0 ) {
			y += 100000;
		}
		if( figures <= 5 ) {
			var figureFactor = Math.pow( 10, 5 - figures );
			x = strPad( x / figureFactor, figures, '0', true );
			y = strPad( y / figureFactor, figures, '0', true );
		} else {
			//pad only the left side of the decimal point
			//use toFixed (if possible) to remove floating point errors introduced by %=
			x = x.toFixed ? x.toFixed(insigFigures) : x.toString();
			x = x.split(/\./);
			x[0] = strPad( x[0], 5, '0', true );
			x = x.join('.');
			y = y.toFixed ? y.toFixed(insigFigures) : y.toString();
			y = y.split(/\./);
			y[0] = strPad( y[0], 5, '0', true );
			y = y.join('.');
		}
		if( returnType == this.TEXT ) {
			return letters + ' ' + x + ' ' + y;
		} else if( returnType ) {
			return '<var class="grid">' + letters + '</var><var>' + x + '</var><var>' + y + '</var>';
		} else {
			return [ letters, x, y ];
		}
	};
	GridRefUtils.prototype.getUKGridNums = function ( letters, x, y, returnType, denyBadReference, useRounding, precise, isIrish ) {
		//no need to shuffle the isIrish parameter over, since it will always be at the end
		var args = expandParams( letters, 3, 3, [ x, y, returnType, denyBadReference, useRounding, precise ] );
		letters = args[0]; x = args[1]; y = args[2]; returnType = args[3]; denyBadReference = args[4]; useRounding = args[5]; precise = args[6];
		var halflen;
		if( typeof(x) != 'string' || typeof(y) != 'string' ) {
			//a single string 'X[Y]12345678' or 'X[Y] 1234 5678', split into parts
			//manually move params
			precise = denyBadReference;
			useRounding = returnType;
			denyBadReference = y;
			returnType = x;
			//split into parts
			//captured whitespace hack makes sure it fails reliably (UK/Irish grid refs must not match each other), while giving consistent matches length
			if( typeof(letters) != 'string' ) { letters = (letters||'').toString(); }
			letters = letters.toUpperCase().match( isIrish ? /^\s*([A-HJ-Z])(\s*)([\d\.]+)\s*([\d\.]*)\s*$/ : /^\s*([A-HJ-Z])([A-HJ-Z])\s*([\d\.]+)\s*([\d\.]*)\s*$/ )
			if( !letters || ( !letters[4] && letters[3].length < 2 ) || ( !letters[4] && letters[3].indexOf('.') != -1 ) ) {
				//invalid format
				if( denyBadReference ) {
					return false;
				}
				//assume 0,0
				letters = [ '', isIrish ? 'V' : 'S', 'V', '0', '0' ];
				useRounding = true;
			}
			if( !letters[4] ) {
				//a single string 'X[Y]12345678', break the numbers in half
				halflen = Math.ceil( letters[3].length / 2 );
				letters[4] = letters[3].substr( halflen );
				letters[3] = letters[3].substr( 0, halflen );
			}
			x = letters[3];
			y = letters[4];
		} else {
			//st becomes ['','S','T'], cast non-strings to string
			letters = letters + '';
			if( letters.length != isIrish ? 1 : 2 ) {
				if( denyBadReference ) {
					return false;
				}
			}
			letters = strSplit( ( letters || '' ).toUpperCase() );
			x += '';
			y += '';
		}
		var xdot = x.indexOf('.');
		var ydot = y.indexOf('.');
		if( isNaN( x * 1 ) ) {
			if( denyBadReference ) {
				return false;
			}
			x = '0';
		} else {
			if( !useRounding ) {
				if( ( x.length == 5 || ydot != -1 ) && xdot == -1 ) {
					x += '.5';
				} else {
					x += '5';
				}
			}
		}
		if( isNaN( y * 1 ) ) {
			if( denyBadReference ) {
				return false;
			}
			y = '0';
		} else {
			if( !useRounding ) {
				if( ( y.length == 5 || xdot != -1 ) && ydot == -1 ) {
					y += '.5';
				} else {
					y += '5';
				}
			}
		}
		var xdp = 0, ydp = 0;
		var newxdot = x.indexOf('.');
		var newydot = y.indexOf('.');
		//need 1m x 1m squares, but if either of them started with a dot, assume they are both in metres already
		if( ydot == -1 && newxdot == -1 ) {
			x = strPad( x, 5, '0' );
		}
		if( xdot == -1 && newydot == -1 ) {
			y = strPad( y, 5, '0' );
		}
		if( newxdot != -1 ) {
			xdp = x.length - ( newxdot + 1 );
		}
		if( newydot != -1 ) {
			ydp = y.length - ( newydot + 1 );
		}
		x = parseFloat( x );
		y = parseFloat( y );
		if( isIrish ) {
			if( letters[1] && UKGridNumbers[letters[1]] ) {
				x += UKGridNumbers[letters[1]][0] * 100000;
				y += UKGridNumbers[letters[1]][1] * 100000;
			} else if( denyBadReference ) {
				return false;
			} else {
				x = y = 0;
			}
		} else {
			if( letters[1] && letters[2] && UKGridNumbers[letters[1]] && UKGridNumbers[letters[2]] ) {
				//remove offset from VV to put origin back at SV
				x += ( UKGridNumbers[letters[1]][0] * 500000 ) + ( UKGridNumbers[letters[2]][0] * 100000 ) - 1000000;
				y += ( UKGridNumbers[letters[1]][1] * 500000 ) + ( UKGridNumbers[letters[2]][1] * 100000 ) - 500000;
			} else if( denyBadReference ) {
				return false;
			} else {
				x = y = 0;
			}
		}
		//the addition of myriad square offsets can mess up the numbers in JavaScript but not PHP
		//74444.555555 + 300000 = 374444.55555499997 due to floating point errors - try to fix it
		if( xdp ) {
			x = round( x, xdp );
		}
		if( ydp ) {
			y = round( y, ydp );
		}
		if( returnType && !useRounding ) {
			//genericXYCoords uses rounding
			if( precise ) {
				x = floorPrecision( x, 6 );
				y = floorPrecision( y, 6 );
			} else {
				x = Math.floor(x);
				y = Math.floor(y);
			}
		}
		return genericXYCoords( returnType, precise, x, y );
	};
	GridRefUtils.prototype.getIrishGridRef = function ( x, y, figures, returnType, denyBadReference, useRounding ) {
		return this.getUKGridRef( x, y, figures, returnType, denyBadReference, useRounding, true);
	};
	GridRefUtils.prototype.getIrishGridNums = function ( letters, x, y, returnType, denyBadReference, useRounding, precise ) {
		return this.getUKGridNums( letters, x, y, returnType, denyBadReference, useRounding, precise, true );
	};
	GridRefUtils.prototype.addGridUnits = function ( x, y, returnType, precise ) {
		var args = expandParams( x, 2, 2, [ y, returnType, precise ] );
		x = args[0]; y = args[1]; returnType = args[2]; precise = args[3];
		if( returnType ) {
			if( precise ) {
				x = prettyNumber( x, 6 );
				y = prettyNumber( y, 6 );
			} else {
				x = Math.round(x);
				y = Math.round(y);
			}
			return ( ( x < 0 ) ? 'W ' : 'E ' ) + Math.abs(x) + 'm, ' + ( ( y < 0 ) ? 'S ' : 'N ' ) + Math.abs(y) + 'm';
		} else {
			return [ Math.abs(x), ( x < 0 ) ? -1 : 1, Math.abs(y), ( y < 0 ) ? -1 : 1 ];
		}
	};
	GridRefUtils.prototype.parseGridNums = function ( coords, returnType, denyBadCoords, strictNums, precise ) {
		var matches;
		if( coords && coords.constructor == Array ) {
			//passed an array, so extract the numbers from it
			matches = ['','',(coords[0]*coords[1])||0,'','', '', (coords[2]*coords[3])||0];
			precise = denyBadCoords;
		} else {
			if( typeof(coords) != 'string' ) {
				coords = '';
			}
			//look for two floats either side of a comma (extra captures ensure array indexes remain the same as $flexi's)
			var rigid = /^(\s*)(-?[\d\.]+)(\s*)(,)(\s*)(-?[\d\.]+)\s*$/;
			//avoid using non-capturing groups for maximum compatibility
			//look for two integers either side of a comma or space, with optional leading E/W or N/S, and trailing m
			//[EW][-]<float>[m][, ][NS][-]<float>[m]
			var flexi = /^\s*([EW]?)\s*(-?[\d\.]+)(\s*M)?(\s+|\s*,\s*)([NS]?)\s*(-?[\d\.]+)(\s*M)?\s*$/;
			var matches = coords.toUpperCase().match( strictNums ? rigid : flexi );
			if( !matches ) {
				//invalid format
				if( denyBadCoords ) {
					return false;
				}
				//assume 0,0
				matches = [ '', '', '0', '', '', '', '0' ];
			}
			matches[2] *= ( matches[1] == 'W' ) ? -1 : 1;
			matches[6] *= ( matches[5] == 'S' ) ? -1 : 1;
			if( denyBadCoords && ( isNaN(matches[2]) || isNaN(matches[6]) ) ) {
				return false;
			}
		}
		return genericXYCoords( returnType, precise, matches[2], matches[6] );
	};

	//shifting grid coordinates between datums or by geoids using bilinear interpolation
	var shiftcache = {};
	//shift parameter sets
	//OSTN15 1 km resolution data set
	var shiftsets = {
		OSTN15: {
			name: 'OSTN15',
			xspacing: 1000,
			yspacing: 1000,
			xstartsat: 0,
			ystartsat: 0,
			recordstart: 1,
			recordcols: 701,
		}
	};
	GridRefUtils.prototype.getShiftSet = function (name) {
		return shiftsets[name] || false;
	}
	GridRefUtils.prototype.createShiftSet = function ( name, xspacing, yspacing, xstartsat, ystartsat, recordcols, recordstart ) {
		if( typeof('name') != 'string' || !name.length || !( xspacing * 1 ) || !( yspacing * 1 ) ) {
			return false;
		}
		return { name: name, xspacing: xspacing, yspacing: yspacing, xstartsat: xstartsat || 0, ystartsat: ystartsat || 0, recordstart: recordstart || 0, recordcols: recordcols || 0 };
	};
	GridRefUtils.prototype.createShiftRecord = function ( easting, northing, eastshift, northshift, geoidheight, datum ) {
		//this does not actually get used in this format - it exists only to force the right columns to exist
		return { name: easting + ',' + northing, se: eastshift, sn: northshift, sg: geoidheight, datum: datum };
	};
	GridRefUtils.prototype.cacheShiftRecords = function ( name, records ) {
		if( typeof('name') != 'string' || !name.length ) {
			return false;
		}
		//prevent JS native object property clashes
		name = 'c_' + name;
		if( !shiftcache[name] ) {
			//nothing has ever been cached for this geoid set, create the cache
			shiftcache[name] = {};
		}
		if( !records ) {
			return true;
		}
		//allow it to accept either a single record, or an array of records
		if( records.constructor != Array ) {
			records = [records];
		}
		for( var i = 0; i < records.length; i++ ) {
			if( typeof(records[i].name) == 'undefined' || typeof(records[i].se) == 'undefined' || typeof(records[i].sn) == 'undefined' || typeof(records[i].sg) == 'undefined' ) {
				return false;
			}
			shiftcache[name][records[i].name] = { se: records[i].se, sn: records[i].sn, sg: records[i].sg, datum: records[i].datum };
		}
		return true;
	};
	GridRefUtils.prototype.deleteShiftCache = function ( name ) {
		if( typeof(name) == 'string' && name.length ) {
			delete shiftcache[ 'c_' + name ];
		} else {
			shiftcache = {};
		}
	}
	function getShiftRecords( shiftname, records, fetchRecords, returnCallback ) {
		//prevent JS native object property clashes
		var name = 'c_' + shiftname;
		if( !shiftcache[name] ) {
			//nothing has ever been cached for this shift set, create the cache
			shiftcache[name] = {};
		}
		var unknownRecords = [];
		var recordnames = [];
		for( var i = 0; i < records.length; i++ ) {
			recordnames[i] = records[i].easting + ',' + records[i].northing;
			if( typeof(shiftcache[name][recordnames[i]]) == 'undefined' ) {
				unknownRecords[unknownRecords.length] = records[i];
				shiftcache[name][recordnames[i]] = false;
			}
		}
		//fetch records if needed
		var samethread = true;
		function asyncCode(returnedRecords) {
			if( returnedRecords ) {
				GridRefUtils.prototype.cacheShiftRecords( shiftname, returnedRecords );
			}
			var shifts = [];
			for( var j = 0; j < recordnames.length; j++ ) {
				shifts[shifts.length] = shiftcache[name][recordnames[j]];
			}
			if( returnCallback ) {
				if( samethread ) {
					//fetchRecords called asyncCode synchronously, or there was nothing to fetch - force it to become async
					setTimeout(function () { returnCallback(shifts); },0);
				} else {
					returnCallback(shifts);
				}
			} else {
				return shifts;
			}
		}
		if( returnCallback ) {
			if( unknownRecords.length ) {
				fetchRecords( shiftname, unknownRecords, asyncCode );
			} else {
				asyncCode();
			}
		} else {
			return asyncCode( unknownRecords.length ? fetchRecords( shiftname, unknownRecords ) : null );
		}
		samethread = false;
	}
	function getShifts( x, y, shiftset, fetchRecords, returnCallback ) {
		//transformation according to Transformations and OSGM15 User Guide
		//https://www.ordnancesurvey.co.uk/business-government/tools-support/os-net/for-developers
		//grid cell
		var eastIndex = Math.floor( ( x - shiftset.xstartsat ) / shiftset.xspacing );
		var northIndex = Math.floor( ( y - shiftset.ystartsat ) / shiftset.yspacing );
		//easting and northing of cell southwest corner
		var x0 = eastIndex * shiftset.xspacing + shiftset.xstartsat;
		var y0 = northIndex * shiftset.yspacing + shiftset.ystartsat;
		//offset within grid
		var dx = x - x0;
		var dy = y - y0;
		//retrieve shifts for the four corners of the cell
		var records = [];
		records[0] = { easting: x0, northing: y0 };
		//allow points to sit on the last line by setting them to the same values as each other (this is an improvement over the OS algorithm)
		if( dx ) {
			records[1] = { easting: x0 + shiftset.xspacing, northing: y0 };
		} else {
			records[1] = records[0];
		}
		if( dy ) {
			records[2] = { easting: records[1].easting, northing: y0 + shiftset.yspacing };
			records[3] = { easting: x0, northing: records[2].northing };
		} else {
			records[2] = records[1];
			records[3] = records[0];
		}
		if( shiftset.recordcols ) {
			//unique, sequential record number starting from southwest eg. OSTN15
			records[0].record = eastIndex + ( northIndex * shiftset.recordcols ) + shiftset.recordstart;
			records[1].record = dx ? ( records[0].record + 1 ) : records[0].record;
			records[3].record = dy ? ( records[0].record + shiftset.recordcols ) : records[0].record;
			records[2].record = dx ? ( records[3].record + 1 ) : records[3].record;
		}
		//fetch records if needed
		function asyncCode(shifts) {
			if( !shifts[0] || !shifts[1] || !shifts[2] || !shifts[3] ) {
				if( returnCallback ) {
					returnCallback(false);
					return;
				} else {
					return false;
				}
			}
			//offset values
			var t = dx / shiftset.xspacing;
			var u = dy / shiftset.yspacing;
			//bilinear interpolated shifts
			var se = ( ( 1 - t ) * ( 1 - u ) * shifts[0].se ) + ( t * ( 1 - u ) * shifts[1].se ) + ( t * u * shifts[2].se ) + ( ( 1 - t ) * u * shifts[3].se );
			var sn = ( ( 1 - t ) * ( 1 - u ) * shifts[0].sn ) + ( t * ( 1 - u ) * shifts[1].sn ) + ( t * u * shifts[2].sn ) + ( ( 1 - t ) * u * shifts[3].sn );
			var sg = ( ( 1 - t ) * ( 1 - u ) * shifts[0].sg ) + ( t * ( 1 - u ) * shifts[1].sg ) + ( t * u * shifts[2].sg ) + ( ( 1 - t ) * u * shifts[3].sg );
			var returnShifts = [ se, sn, sg, ( typeof(shifts[0].datum) != 'undefined' || typeof(shifts[1].datum) != 'undefined' || typeof(shifts[2].datum) != 'undefined' || typeof(shifts[3].datum) != 'undefined' ) ? [ shifts[0].datum, shifts[1].datum, shifts[2].datum, shifts[3].datum ] : window.undefined ];
			if( returnCallback ) {
				returnCallback(returnShifts);
			} else {
				return returnShifts;
			}
		}
		if( returnCallback ) {
			getShiftRecords( shiftset.name, records, fetchRecords, asyncCode );
		} else {
			return asyncCode( getShiftRecords( shiftset.name, records, fetchRecords ) );
		}
	}
	GridRefUtils.prototype.shiftGrid = function ( easting, northing, height, shiftset, fetchRecords, returnType, denyBadCoords, precise, returnCallback ) {
		var args = expandParams( easting, 2, 3, [ northing, height, shiftset, fetchRecords, returnType, denyBadCoords, precise, returnCallback ] );
		easting = args[0]; northing = args[1]; height = args[2]; shiftset = args[3]; fetchRecords = args[4]; returnType = args[5]; denyBadCoords = args[6]; precise = args[7]; returnCallback = args[8];
		if( returnCallback && typeof(returnCallback) != 'function' ) {
			return false;
		}
		if( !shiftset || !( shiftset.xspacing * shiftset.yspacing ) || typeof(fetchRecords) != 'function' ) {
			if( returnCallback ) {
				setTimeout( function () { returnCallback(false); }, 0 );
				return;
			} else {
				return false;
			}
		}
		function asyncCode(shifts) {
			if( !shifts ) {
				var errorValue = denyBadCoords ? false : genericXYCoords( returnType, precise, Number.NaN, Number.NaN, isNumeric(height) ? Number.NaN : null, [] );
				if( returnCallback ) {
					returnCallback(errorValue);
					return;
				} else {
					return errorValue;
				}
			}
			//easting, northing, height
			easting += shifts[0];
			northing += shifts[1];
			if( isNumeric(height) ) {
				height -= shifts[2];
			}
			var returnValue = genericXYCoords( returnType, precise, easting, northing, height, shifts[3] );
			if( returnCallback ) {
				returnCallback(returnValue);
			} else {
				return returnValue;
			}
		}
		if( returnCallback ) {
			getShifts( easting, northing, shiftset, fetchRecords, asyncCode );
		} else {
			return asyncCode( getShifts( easting, northing, shiftset, fetchRecords ) );
		}
	}
	GridRefUtils.prototype.reverseShiftGrid = function ( easting, northing, height, shiftset, fetchRecords, returnType, denyBadCoords, precise, returnCallback ) {
		var args = expandParams( easting, 2, 3, [ northing, height, shiftset, fetchRecords, returnType, denyBadCoords, precise, returnCallback ] );
		easting = args[0]; northing = args[1]; height = args[2]; shiftset = args[3]; fetchRecords = args[4]; returnType = args[5]; denyBadCoords = args[6]; precise = args[7]; returnCallback = args[8];
		if( returnCallback && typeof(returnCallback) != 'function' ) {
			return false;
		}
		if( !shiftset || !( shiftset.xspacing * shiftset.yspacing ) || typeof(fetchRecords) != 'function' ) {
			if( returnCallback ) {
				setTimeout( function () { returnCallback(false); }, 0 );
				return;
			} else {
				return false;
			}
		}
		var oldshifts = [ 0, 0 ];
		var loopcount = 0;
		//this uses recursion to allow asynchonous operation, instead of a do...while loop
		//can cause a stack overflow in synchronous operation but 100 loops is well below the browser stack limit, and it normally only takes <=5
		function asyncCode(shifts) {
			if( !shifts ) {
				var errorValue = denyBadCoords ? false : genericXYCoords( returnType, precise, Number.NaN, Number.NaN, isNumeric(height) ? Number.NaN : null, [] );
				if( returnCallback ) {
					returnCallback(errorValue);
					return;
				} else {
					return errorValue;
				}
			}
			//see if the shift changes with each iteration
			//unlikely to ever hit 100 iterations, even when taken to extremes
			if( ( Math.abs( oldshifts[0] - shifts[0] ) > 0.0001 || Math.abs( oldshifts[1] - shifts[1] ) > 0.0001 ) && ++loopcount < 100 ) {
				//apply the new shift and try again with the shift from the new point
				oldshifts = shifts;
				if( returnCallback ) {
					getShifts( easting - shifts[0], northing - shifts[1], shiftset, fetchRecords, asyncCode );
				} else {
					return asyncCode( getShifts( easting - shifts[0], northing - shifts[1], shiftset, fetchRecords ) );
				}
			} else {
				if( isNumeric(height) ) {
					height = height * 1 + shifts[2];
				}
				var returnValue = genericXYCoords( returnType, precise, easting - shifts[0], northing - shifts[1], height, shifts[3] );
				if( returnCallback ) {
					returnCallback(returnValue);
				} else {
					return returnValue;
				}
			}
		}
		//get the shifts for the current estimated point, starting with the initial easting and northing
		if( returnCallback ) {
			getShifts( easting, northing, shiftset, fetchRecords, asyncCode );
		} else {
			return asyncCode( getShifts( easting, northing, shiftset, fetchRecords ) );
		}
	}

	//ellipsoid parameters used during grid->lat/long conversions and Helmert transformations
	var ellipsoids = {
		Airy_1830: {
			//Airy 1830 (OS)
			a: 6377563.396,
			b: 6356256.909
		},
		Airy_1830_mod: {
			//Airy 1830 modified (OSI)
			a: 6377340.189,
			b: 6356034.447
		},
		GRS80: {
			//GRS80 (ETRS89, early versions of GPS)
			a: 6378137,
			b: 6356752.3141
		},
		WGS84: {
			//WGS84 (GPS)
			a: 6378137,
			b: 6356752.31425 // https://georepository.com/ellipsoid_7030/WGS-84.html 4 November 2020
		}
	};
	var datumsets = {
		OSGB36: {
			//Airy 1830 (OS)
			a: ellipsoids.Airy_1830.a,
			b: ellipsoids.Airy_1830.b,
			F0: 0.9996012717,
			E0: 400000,
			N0: -100000,
			Phi0: 49,
			Lambda0: -2
		},
		OSTN15: {
			//OSGB36 parameters with GRS80 ellipsoid (OSTN15)
			a: ellipsoids.GRS80.a,
			b: ellipsoids.GRS80.b,
			F0: 0.9996012717,
			E0: 400000,
			N0: -100000,
			Phi0: 49,
			Lambda0: -2
		},
		Ireland_1965: {
			//Airy 1830 modified (OSI)
			a: ellipsoids.Airy_1830_mod.a,
			b: ellipsoids.Airy_1830_mod.b,
			F0: 1.000035,
			E0: 200000,
			N0: 250000,
			Phi0: 53.5,
			Lambda0: -8
		},
		IRENET95: {
			//ITM (uses WGS84) (OSI) taken from http://en.wikipedia.org/wiki/Irish_Transverse_Mercator
			a: ellipsoids.GRS80.a,
			b: ellipsoids.GRS80.b,
			F0: 0.999820,
			E0: 600000,
			N0: 750000,
			Phi0: 53.5,
			Lambda0: 360-8
		},
		//UPS (uses WGS84), taken from http://www.epsg.org/guides/ number 7 part 2 "Coordinate Conversions and Transformations including Formulas"
		//officially defined in http://earth-info.nga.mil/GandG/publications/tm8358.2/TM8358_2.pdf
		UPS_North: {
			a: ellipsoids.WGS84.a,
			b: ellipsoids.WGS84.b,
			F0: 0.994,
			E0: 2000000,
			N0: 2000000,
			Phi0: 90,
			Lambda0: 0
		},
		UPS_South: {
			a: ellipsoids.WGS84.a,
			b: ellipsoids.WGS84.b,
			F0: 0.994,
			E0: 2000000,
			N0: 2000000,
			Phi0: -90,
			Lambda0: 0
		}
	};
	GridRefUtils.prototype.getEllipsoid = function (name) {
		return ellipsoids[name] || false;
	};
	GridRefUtils.prototype.createEllipsoid = function ( a, b ) {
		return { a: a, b: b };
	};
	GridRefUtils.prototype.getDatum = function (name) {
		return datumsets[name] || false;
	};
	GridRefUtils.prototype.createDatum = function ( ellip, F0, E0, N0, Phi0, Lambda0 ) {
		if( !ellip || typeof(ellip.a) == 'undefined' ) {
			return false;
		}
		return { a: ellip.a, b: ellip.b, F0: F0, E0: E0, N0: N0, Phi0: Phi0, Lambda0: Lambda0 };
	};

	//conversions between 12345,67890 grid references and latitude/longitude formats using transverse mercator projection
	GridRefUtils.prototype.COORDS_OS_UK = 1;
	GridRefUtils.prototype.COORDS_OSI = 2;
	GridRefUtils.prototype.COORDS_GPS_UK = 3;
	GridRefUtils.prototype.COORDS_GPS_IRISH = 4;
	GridRefUtils.prototype.COORDS_GPS_ITM = 5;
	GridRefUtils.prototype.COORDS_GPS_IRISH_HELMERT = 6;
	GridRefUtils.prototype.gridToLatLong = function ( E, N, ctype, returnType ) {
		//horribly complex conversion according to "A guide to coordinate systems in Great Britain" Annexe C:
		//http://www.ordnancesurvey.co.uk/oswebsite/gps/information/coordinatesystemsinfo/guidecontents/
		//http://www.movable-type.co.uk/scripts/latlong-gridref.html shows an alternative script for JS, which also says what some OS variables represent
		var args = expandParams( E, 2, 2, [ N, ctype, returnType ] );
		E = args[0]; N = args[1]; ctype = args[2]; returnType = args[3];
		//get appropriate ellipsoid semi-major axis 'a' (metres) and semi-minor axis 'b' (metres),
		//grid scale factor on central meridian, and true origin (grid and lat-long) from Annexe A
		var ell = {};
		if( ctype && typeof(ctype) == 'object' ) {
			ell = ctype;
		} else if( ctype == this.COORDS_OS_UK || ctype == this.COORDS_GPS_UK ) {
			ell = datumsets.OSGB36;
		} else if( ctype == this.COORDS_GPS_ITM ) {
			ell = datumsets.IRENET95;
		} else if( ctype == this.COORDS_OSI || ctype == this.COORDS_GPS_IRISH || ctype == this.COORDS_GPS_IRISH_HELMERT ) {
			ell = datumsets.Ireland_1965;
		}
		var a = ell.a, b = ell.b, F0 = ell.F0, E0 = ell.E0, N0 = ell.N0, Phi0 = ell.Phi0, Lambda0 = ell.Lambda0;
		if( typeof(F0) == 'undefined' ) {
			//invalid type
			return false;
		}
		//convert to radians
		Phi0 *= Math.PI / 180;
		//eccentricity-squared from Annexe B B1
		//e2 = ( ( a * a ) - ( b * b ) ) / ( a * a );
		var e2 = 1 - ( b * b ) / ( a * a ); //optimised
		//C1
		var n = ( a - b ) / ( a + b );
		//pre-compute values that will be re-used many times in the C3 formula
		var n2 = n * n;
		var n3 = Math.pow( n, 3 );
		var nParts1 = ( 1 + n + 1.25 * n2 + 1.25 * n3 );
		var nParts2 = ( 3 * n + 3 * n2 + 2.625 * n3 );
		var nParts3 = ( 1.875 * n2 + 1.875 * n3 );
		var nParts4 = ( 35 / 24 ) * n3;
		//iterate to find latitude (when N - N0 - M < 0.01mm)
		var Phi = Phi0;
		var M = 0;
		var loopcount = 0;
		do {
			//C6 and C7
			Phi += ( ( N - N0 - M ) / ( a * F0 ) );
			//C3
			M = b * F0 * (
				nParts1 * ( Phi - Phi0 ) -
				nParts2 * Math.sin( Phi - Phi0 ) * Math.cos( Phi + Phi0 ) +
				nParts3 * Math.sin( 2 * ( Phi - Phi0 ) ) * Math.cos( 2 * ( Phi + Phi0 ) ) -
				nParts4 * Math.sin( 3 * ( Phi - Phi0 ) ) * Math.cos( 3 * ( Phi + Phi0 ) )
			); //meridonal arc
			//due to number precision, it is possible to get infinite loops here for extreme cases (especially for invalid ellipsoid numbers)
			//in tests, upto 6 loops are needed for grid 25 times Earth circumference - if it reaches 100, assume it must be infinite, and break out
		} while( Math.abs( N - N0 - M ) >= 0.00001 && ++loopcount < 100 ); //0.00001 == 0.01 mm
		//pre-compute values that will be re-used many times in the C2 and C8 formulae
		var sinPhi = Math.sin( Phi );
		var sin2Phi = sinPhi * sinPhi;
		var tanPhi = Math.tan( Phi );
		var secPhi = 1 / Math.cos( Phi );
		var tan2Phi = tanPhi * tanPhi;
		var tan4Phi = tan2Phi * tan2Phi;
		//C2
		var Rho = a * F0 * ( 1 - e2 ) * Math.pow( 1 - e2 * sin2Phi, -1.5 ); //meridional radius of curvature
		var Nu = a * F0 / Math.sqrt( 1 - e2 * sin2Phi ); //transverse radius of curvature
		var Eta2 = Nu / Rho - 1;
		//pre-compute more values that will be re-used many times in the C8 formulae
		var Nu3 = Math.pow( Nu, 3 );
		var Nu5 = Math.pow( Nu, 5 );
		//C8 parts
		var VII = tanPhi / ( 2 * Rho * Nu );
		var VIII = ( tanPhi / ( 24 * Rho * Nu3 ) ) * ( 5 + 3 * tan2Phi + Eta2 - 9 * tan2Phi * Eta2 );
		var IX = ( tanPhi / ( 720 * Rho * Nu5 ) ) * ( 61 + 90 * tan2Phi + 45 * tan4Phi );
		var X = secPhi / Nu;
		var XI = ( secPhi / ( 6 * Nu3 ) ) * ( ( Nu / Rho ) + 2 * tan2Phi );
		var XII = ( secPhi / ( 120 * Nu5 ) ) * ( 5 + 28 * tan2Phi + 24 * tan4Phi );
		var XIIA = ( secPhi / ( 5040 * Math.pow( Nu, 7 ) ) ) * ( 61 + 662 * tan2Phi + 1320 * tan4Phi + 720 * Math.pow( tanPhi, 6 ) );
		//C8, C9
		var Edif = E - E0;
		var latitude = ( Phi - VII * Edif * Edif + VIII * Math.pow( Edif, 4 ) - IX * Math.pow( Edif, 6 ) ) * ( 180 / Math.PI );
		var longitude = Lambda0 + ( X * Edif - XI * Math.pow( Edif, 3 ) + XII * Math.pow( Edif, 5 ) - XIIA * Math.pow( Edif, 7 ) ) * ( 180 / Math.PI );
		var tmp;
		if( ctype == this.COORDS_GPS_UK ) {
			tmp = this.HelmertTransform( latitude, longitude, ellipsoids.Airy_1830, Helmerts.OSGB36_to_WGS84, ellipsoids.WGS84 );
			latitude = tmp[0];
			longitude = tmp[1];
		} else if( ctype == this.COORDS_GPS_IRISH ) {
			tmp = this.polynomialTransform( latitude, longitude, polycoeffs.OSiLPS );
			latitude = tmp[0];
			longitude = tmp[1];
		} else if( ctype == this.COORDS_GPS_IRISH_HELMERT ) {
			tmp = this.HelmertTransform( latitude, longitude, ellipsoids.Airy_1830_mod, Helmerts.Ireland65_to_WGS84, ellipsoids.WGS84 );
			latitude = tmp[0];
			longitude = tmp[1];
		}
		return genericLatLongCoords( returnType, latitude, longitude );
	};
	GridRefUtils.prototype.latLongToGrid = function ( latitude, longitude, ctype, returnType, precise ) {
		//horribly complex conversion according to "A guide to coordinate systems in Great Britain" Annexe C:
		//http://www.ordnancesurvey.co.uk/oswebsite/gps/information/coordinatesystemsinfo/guidecontents/
		//http://www.movable-type.co.uk/scripts/latlong-gridref.html shows an alternative script for JS, which also says what some OS variables represent
		var args = expandParams( latitude, 2, 2, [ longitude, ctype, returnType, precise ] );
		latitude = args[0]; longitude = args[1]; ctype = args[2]; returnType = args[3]; precise = args[4];
		//convert back to local ellipsoid coordinates
		var tmp;
		if( ctype == this.COORDS_GPS_UK ) {
			tmp = this.HelmertTransform( latitude, longitude, ellipsoids.WGS84, Helmerts.WGS84_to_OSGB36, ellipsoids.Airy_1830 );
			latitude = tmp[0];
			longitude = tmp[1];
		} else if( ctype == this.COORDS_GPS_IRISH ) {
			tmp = this.reversePolynomialTransform( latitude, longitude, polycoeffs.OSiLPS );
			latitude = tmp[0];
			longitude = tmp[1];
		} else if( ctype == this.COORDS_GPS_IRISH_HELMERT ) {
			tmp = this.HelmertTransform( latitude, longitude, ellipsoids.WGS84, Helmerts.WGS84_to_Ireland65, ellipsoids.Airy_1830_mod );
			latitude = tmp[0];
			longitude = tmp[1];
		}
		//get appropriate ellipsoid semi-major axis 'a' (metres) and semi-minor axis 'b' (metres),
		//grid scale factor on central meridian, and true origin (grid and lat-long) from Annexe A
		var ell = {};
		if( ctype && typeof(ctype) == 'object' ) {
			ell = ctype;
		} else if( ctype == this.COORDS_OS_UK || ctype == this.COORDS_GPS_UK ) {
			ell = datumsets.OSGB36;
		} else if( ctype == this.COORDS_GPS_ITM ) {
			ell = datumsets.IRENET95;
		} else if( ctype == this.COORDS_OSI || ctype == this.COORDS_GPS_IRISH || ctype == this.COORDS_GPS_IRISH_HELMERT ) {
			ell = datumsets.Ireland_1965;
		}
		var a = ell.a, b = ell.b, F0 = ell.F0, E0 = ell.E0, N0 = ell.N0, Phi0 = ell.Phi0, Lambda0 = ell.Lambda0;
		if( typeof(ell.F0) == 'undefined' ) {
			//invalid type
			return false;
		}
		//PHP will not allow expressions in the arrays as they are defined inline as class properties, so for consistency with PHP, do the conversion to radians here
		Phi0 *= Math.PI / 180;
		var Phi = latitude * Math.PI / 180;
		var Lambda = longitude - Lambda0;
		//force Lambda between -180 and 180
		Lambda = wrapAroundLongitude(Lambda);
		Lambda *= Math.PI / 180;
		//eccentricity-squared from Annexe B B1
		//e2 = ( ( a * a ) - ( b * b ) ) / ( a * a );
		var e2 = 1 - ( b * b ) / ( a * a ); //optimised
		//C1
		var n = ( a - b ) / ( a + b );
		//pre-compute values that will be re-used many times in the C2, C3 and C4 formulae
		var sinPhi = Math.sin( Phi );
		var sin2Phi = sinPhi * sinPhi;
		var cosPhi = Math.cos( Phi );
		var cos3Phi = Math.pow( cosPhi, 3 );
		var cos5Phi = Math.pow( cosPhi, 5 );
		var tanPhi = Math.tan( Phi );
		var tan2Phi = tanPhi * tanPhi;
		var tan4Phi = tan2Phi * tan2Phi;
		var n2 = n * n;
		var n3 = Math.pow( n, 3 );
		//C2
		var Nu = a * F0 / Math.sqrt( 1 - e2 * sin2Phi ); //transverse radius of curvature
		var Rho = a * F0 * ( 1 - e2 ) * Math.pow( 1 - e2 * sin2Phi, -1.5 ); //meridional radius of curvature
		var Eta2 = Nu / Rho - 1;
		//C3, meridonal arc
		var M = b * F0 * (
			( 1 + n + 1.25 * n2 + 1.25 * n3 ) * ( Phi - Phi0 ) -
			( 3 * n + 3 * n2 + 2.625 * n3 ) * Math.sin( Phi - Phi0 ) * Math.cos( Phi + Phi0 ) +
			( 1.875 * n2 + 1.875 * n3 ) * Math.sin( 2 * ( Phi - Phi0 ) ) * Math.cos( 2 * ( Phi + Phi0 ) ) -
			( 35 / 24 ) * n3 * Math.sin( 3 * ( Phi - Phi0 ) ) * Math.cos( 3 * ( Phi + Phi0 ) )
		);
		//C4
		var I = M + N0;
		var II = ( Nu / 2 ) * sinPhi * cosPhi;
		var III = ( Nu / 24 ) * sinPhi * cos3Phi * ( 5 - tan2Phi + 9 * Eta2 );
		var IIIA = ( Nu / 720 ) * sinPhi * cos5Phi * ( 61 - 58 * tan2Phi + tan4Phi );
		var IV = Nu * cosPhi;
		var V = ( Nu / 6 ) * cos3Phi * ( ( Nu / Rho ) - tan2Phi );
		var VI = ( Nu / 120 ) * cos5Phi * ( 5 - 18 * tan2Phi + tan4Phi + 14 * Eta2 - 58 * tan2Phi * Eta2 );
		var N = I + II * Lambda * Lambda + III * Math.pow( Lambda, 4 ) + IIIA * Math.pow( Lambda, 6 );
		var E = E0 + IV * Lambda + V * Math.pow( Lambda, 3 ) + VI * Math.pow( Lambda, 5 );
		return genericXYCoords( returnType, precise, E, N );
	};

	//UTM - freaky format consisting of 60 transverse mercators
	GridRefUtils.prototype.utmToLatLong = function ( zone, north, x, y, ellip, returnType, denyBadReference ) {
		var args = expandParams( zone, 4, 4, [ north, x, y, ellip, returnType, denyBadReference ] );
		zone = args[0]; north = args[1]; x = args[2]; y = args[3]; ellip = args[4]; returnType = args[5]; denyBadReference = args[6];
		if( typeof(zone) == 'string' ) {
			if( typeof(ellip) != 'object' ) {
				//if it is an object, expandParams will have already moved it over
				denyBadReference = y;
				returnType = x;
				ellip = north;
			}
			zone = zone.toUpperCase();
			var parsedZone;
			if( parsedZone = zone.match( /^\s*(0?[1-9]|[1-5][0-9]|60)\s*([C-HJ-NP-X]|NORTH|SOUTH)\s*(-?[\d\.]+)\s*[,\s]\s*(-?[\d\.]+)\s*$/ ) ) {
				//matched the shorthand 30U 1234 5678
				//[01-60]<letter><float>[, ]<float>
				//radix parameter is needed since numbers often start with a leading 0 and must not be treated as octal
				zone = parseInt( parsedZone[1], 10 );
				if( parsedZone[2].length > 1 ) {
					north = ( parsedZone[2] == 'NORTH' ) ? 1 : -1;
				} else {
					north = ( parsedZone[2] > 'M' ) ? 1 : -1;
				}
				x = parseFloat(parsedZone[3]) || 0;
				y = parseFloat(parsedZone[4]) || 0;
			} else if( parsedZone = zone.match( /^\s*(-?[\d\.]+)\s*[A-Z]*\s*[,\s]\s*(-?[\d\.]+)\s*[A-Z]*\s*[\s,]\s*ZONE\s*(0?[1-9]|[1-5][0-9]|60)\s*,?\s*([NS])/ ) ) {
				//matched the longhand 630084 mE 4833438 mN, zone 17, Northern Hemisphere
				//<float>[letters][, ]<float>[letters][, ]zone[01-60][,][NS]...
				zone = parseInt( parsedZone[3], 10 );
				north = ( parsedZone[4] == 'N' ) ? 1 : -1;
				x = parseFloat(parsedZone[1]) || 0;
				y = parseFloat(parsedZone[2]) || 0;
			} else {
				//make it reject it
				zone = 0;
			}
		}
		if( ellip && typeof(ellip) == 'object' && typeof(ellip.a) == 'undefined' ) {
			//invalid ellipsoid must be rejected early, before returning error values or constructing a datum
			return false;
		}
		if( isNaN(zone) || zone < 1 || zone > 60 || isNaN(x) || isNaN(y) ) {
			if( denyBadReference ) {
				//invalid format
				return false;
			}
			//default coordinates put it at 90,0 lat/long - use dmsToDd to get the right returnType
			return this.dmsToDd([90,0,0,1,0,0,0,0],returnType);
		}
		var ellipsoid = ( ellip && typeof(ellip) == 'object' ) ? ellip : ellipsoids.WGS84;
		ellipsoid = { a: ellipsoid.a, b: ellipsoid.b, F0: 0.9996, E0: 500000, N0: ( north < 0 ) ? 10000000 : 0, Phi0: 0, Lambda0: ( 6 * zone ) - 183 };
		return this.gridToLatLong(x,y,ellipsoid,returnType);
	};
	GridRefUtils.prototype.latLongToUtm = function ( latitude, longitude, ellip, format, returnType, denyBadCoords, precise ) {
		var args = expandParams( latitude, 2, 2, [ longitude, ellip, format, returnType, denyBadCoords, precise ] );
		latitude = args[0]; longitude = args[1]; ellip = args[2]; format = args[3]; returnType = args[4]; denyBadCoords = args[5]; precise = args[6];
		if( ellip && typeof(ellip) == 'object' && typeof(ellip.a) == 'undefined' ) {
			//invalid ellipsoid must be rejected early, before returning error values or constructing a datum
			return false;
		}
		//force the longitude between -180 and 179.99...9
		longitude = wrapAroundLongitude( longitude, true );
		var zone, zoneletter, x, y;
		if( isNaN(longitude) || isNaN(latitude) || latitude > 84 || latitude < -80 ) {
			if( denyBadCoords ) {
				//invalid format
				return false;
			}
			//default coordinates put it at ~0,0 lat/long
			if( isNaN(longitude) ) {
				longitude = 0;
			}
			if( isNaN(latitude) ) {
				latitude = 0;
			}
			if( latitude > 84 ) {
				//out of range, return appropriate polar letter, and bail out
				zoneletter = format ? 'North' : ( ( longitude < 0 ) ? 'Y' : 'Z' );
				zone = x = y = 0;
			}
			if( latitude < -80 ) {
				//out of range, return appropriate polar letter, and bail out
				zoneletter = format ? 'South' : ( ( longitude < 0 ) ? 'A' : 'B' );
				zone = x = y = 0;
			}
		}
		if( !zoneletter ) {
			//add hacks to work out if it lies in the non-standard zones
			if( latitude >= 72 && longitude >= 6 && longitude < 36 ) {
				//band X, these parts are moved around
				if( longitude < 9 ) {
					zone = 31;
				} else if( longitude < 21 ) {
					zone = 33;
				} else if( longitude < 33 ) {
					zone = 35;
				} else {
					zone = 37;
				}
			} else if( latitude >= 56 && latitude < 64 && longitude >= 3 && longitude < 6 ) {
				//band U, this part of zone 31 is moved into zone 32
				zone = 32;
			} else {
				//yay for standards!
				zone = Math.floor( longitude / 6 ) + 31;
			}
			//get the band letter
			if( format ) {
				zoneletter = ( latitude < 0 ) ? 'South' : 'North';
			} else {
				zoneletter = Math.floor( latitude / 8 ) + 77; //67 is ASCII C
				if( zoneletter > 72 ) {
					//skip I
					zoneletter++;
				}
				if( zoneletter > 78 ) {
					//skip O
					zoneletter++;
				}
				if( zoneletter > 88 ) {
					//X is as high as it goes
					zoneletter = 88;
				}
				zoneletter = String.fromCharCode(zoneletter);
			}
			//do actual transformation - happens inside "if(!zoneletter)" because this is the non-error path
			var ellipsoid = ( ellip && typeof(ellip) == 'object' ) ? ellip : ellipsoids.WGS84;
			ellipsoid = { a: ellipsoid.a, b: ellipsoid.b, F0: 0.9996, E0: 500000, N0: ( latitude < 0 ) ? 10000000 : 0, Phi0: 0, Lambda0: ( 6 * zone ) - 183 };
			var tmpcoords = this.latLongToGrid(latitude,longitude,ellipsoid)
			if( !tmpcoords ) { return false; }
			x = tmpcoords[0];
			y = tmpcoords[1];
		}
		if( returnType ) {
			if( precise ) {
				x = prettyNumber( x, 6 );
				y = prettyNumber( y, 6 );
			} else {
				x = Math.round(x);
				y = Math.round(y);
			}
			if( format ) {
				return x + 'mE, ' + y + 'mN, Zone ' + zone + ', ' + zoneletter + 'ern Hemisphere';
			}
			return zone + zoneletter + ' ' + x + ' ' + y;
		} else {
			return [ zone, ( latitude < 0 ) ? -1 : 1, x, y, zoneletter ];
		}
	};

	//basic polar stereographic pojections
	//formulae according to http://www.epsg.org/guides/ number 7 part 2 "Coordinate Conversions and Transformations including Formulas"
	GridRefUtils.prototype.polarToLatLong = function ( easting, northing, datum, returnType ) {
		var args = expandParams( easting, 2, 2, [ northing, datum, returnType ] );
		easting = args[0]; northing = args[1]; datum = args[2]; returnType = args[3];
		if( !datum ) {
			return false;
		}
		var a = datum.a, b = datum.b, k0 = datum.F0, FE = datum.E0, FN = datum.N0, Phi0 = datum.Phi0, Lambda0 = datum.Lambda0;
		if( typeof(datum.F0) == 'undefined' || ( Phi0 != 90 && Phi0 != -90 ) ) {
			//invalid type
			return false;
		}
		//eccentricity-squared
		var e2 = 1 - ( b * b ) / ( a * a ); //optimised
		var e = Math.sqrt(e2);
		var Rho = Math.sqrt( Math.pow( easting - FE, 2 ) + Math.pow( northing - FN, 2 ) );
		var t = Rho * Math.sqrt( Math.pow( 1 + e, 1 + e ) * Math.pow( 1 - e, 1 - e ) ) / ( 2 * a * k0 );
		var x;
		if( Phi0 < 0 ) {
			//south
			x = 2 * Math.atan(t) - Math.PI / 2;
		} else {
			//north
			x = Math.PI / 2 - 2 * Math.atan(t);
		}
		//pre-compute values that will be re-used many times in the Phi formula
		var e4 = e2 * e2, e6 = e4 * e2, e8 = e4 * e4;
		var Phi = x + ( e2 / 2 + 5 * e4 / 24 + e6 / 12 + 13 * e8 / 360 ) * Math.sin( 2 * x ) +
			( 7 * e4 / 48 + 29 * e6 / 240 + 811 * e8 / 11520 ) * Math.sin( 4 * x ) +
			( 7 * e6 / 120 + 81 * e8 / 1120 ) * Math.sin( 6 * x ) +
			( 4279 * e8 / 161280 ) * Math.sin( 8 * x );
		//longitude
		var Lambda;
		//formulas here are wrong in the epsg guide; atan(foo/bar) should have been atan2(foo,bar) or it is wrong for half of the grid
		if( Phi0 < 0 ) {
			//south
			Lambda = Math.atan2( easting - FE, northing - FN );
		} else {
			//north
			Lambda = Math.atan2( easting - FE, FN - northing );
		}
		var latitude = Phi * 180 / Math.PI;
		var longitude = Lambda * 180 / Math.PI + Lambda0;
		return genericLatLongCoords( returnType, latitude, longitude );
	};
	GridRefUtils.prototype.latLongToPolar = function ( latitude, longitude, datum, returnType, precise ) {
		var args = expandParams( latitude, 2, 2, [ longitude, datum, returnType, precise ] );
		latitude = args[0]; longitude = args[1]; datum = args[2]; returnType = args[3]; precise = args[4];
		if( !datum ) {
			return false;
		}
		var a = datum.a, b = datum.b, k0 = datum.F0, FE = datum.E0, FN = datum.N0, Phi0 = datum.Phi0, Lambda0 = datum.Lambda0;
		if( typeof(datum.F0) == 'undefined' || ( Phi0 != 90 && Phi0 != -90 ) ) {
			//invalid type
			return false;
		}
		var Phi = latitude * Math.PI / 180;
		var Lambda = ( longitude - Lambda0 ) * Math.PI / 180;
		//eccentricity-squared
		var e2 = 1 - ( b * b ) / ( a * a ); //optimised
		var e = Math.sqrt(e2);
		//t
		var sinPhi = Math.sin( Phi );
		var t;
		if( Phi0 < 0 ) {
			//south
			t = Math.tan( ( Math.PI / 4 ) + ( Phi / 2 ) ) / Math.pow( ( 1 + e * sinPhi ) / ( 1 - e * sinPhi ), e / 2 );
		} else {
			//north
			t = Math.tan( ( Math.PI / 4 ) - ( Phi / 2 ) ) * Math.pow( ( 1 + e * sinPhi ) / ( 1 - e * sinPhi ), e / 2 );
		}
		//Rho
		var Rho = 2 * a * k0 * t / Math.sqrt( Math.pow( 1 + e, 1 + e ) * Math.pow( 1 - e, 1 - e ) );
		var N;
		if( Phi0 < 0 ) {
			//south
			N = FN + Rho * Math.cos( Lambda );
		} else {
			//north - origin is *down*
			N = FN - Rho * Math.cos( Lambda );
		}
		var E = FE + Rho * Math.sin( Lambda );
		return genericXYCoords( returnType, precise, E, N );
	};
	GridRefUtils.prototype.upsToLatLong = function ( hemisphere, x, y, returnType, denyBadReference, minLength ) {
		var args = expandParams( hemisphere, 3, 3, [ x, y, returnType, denyBadReference, minLength ] );
		hemisphere = args[0]; x = args[1]; y = args[2]; returnType = args[3]; denyBadReference = args[4]; minLength = args[5];
		if( !isNumeric(x) || !isNumeric(y) ) {
			//a single string 'X 12345 67890', split into parts
			if( typeof(hemisphere) != 'string' ) { hemisphere = (hemisphere||'').toString(); }
			//manually move params
			minLength = returnType;
			denyBadReference = y;
			returnType = x;
			//(A|B|Y|Z|N|S|north|south)[,]<float>[, ]<float>
			hemisphere = hemisphere.match( /^\s*([ABNSYZ]|NORTH|SOUTH)\s*,?\s*(-?[\d\.]+)\s*[\s,]\s*(-?[\d\.]+)\s*$/i )
			if( !hemisphere ) {
				//invalid format, will get handled later
				x = y = null;
			} else {
				x = parseFloat(hemisphere[2]);
				y = parseFloat(hemisphere[3]);
				hemisphere = hemisphere[1];
			}
		}
		if( !isNumeric(x) || !isNumeric(y) || ( typeof(hemisphere) != 'number' && ( typeof(hemisphere) != 'string' || !hemisphere.match(/^([ABNSYZ]|NORTH|SOUTH)$/i) ) ) || minLength && ( x < 800000 || y < 800000 ) ) {
			if( denyBadReference ) {
				//invalid format
				return false;
			}
			//default coordinates put it at 0,0 lat/long - use dmsToDd to get the right returnType
			return this.dmsToDd([0,0,0,0,0,0,0,0],returnType);
		}
		if( typeof(hemisphere) == 'string' ) {
			hemisphere = hemisphere.toUpperCase();
		} else {
			hemisphere = ( hemisphere < 0 ) ? 'S' : 'N';
		}
		if( hemisphere == 'N' || hemisphere == 'NORTH' || hemisphere == 'Y' || hemisphere == 'Z' ) {
			hemisphere = datumsets.UPS_North;
		} else {
			hemisphere = datumsets.UPS_South;
		}
		return this.polarToLatLong(x,y,hemisphere,returnType);
	};
	GridRefUtils.prototype.latLongToUps = function ( latitude, longitude, format, returnType, denyBadCoords, precise ) {
		var args = expandParams( latitude, 2, 2, [ longitude, format, returnType, denyBadCoords, precise ] );
		latitude = args[0]; longitude = args[1]; format = args[2]; returnType = args[3]; denyBadCoords = args[4]; precise = args[5];
		//force the longitude between -179.99...9 and 180
		longitude = wrapAroundLongitude(longitude);
		var tmp, letter;
		if( isNaN(longitude) || isNaN(latitude) || latitude > 90 || latitude < -90 || ( latitude < 83.5 && latitude > -79.5 ) ) {
			if( denyBadCoords ) {
				//invalid format
				return false;
			}
			//default coordinates put it as 90,0 in OUT_OF_GRID zone
			tmp = [ 2000000, 2000000 ];
			letter = 'OUT_OF_GRID';
		} else {
			tmp = this.latLongToPolar( latitude, longitude, ( latitude < 0 ) ? datumsets.UPS_South : datumsets.UPS_North );
			if( latitude < 0 ) {
				if( format ) {
					letter = 'S';
				} else if( longitude < 0 ) {
					letter = 'A';
				} else {
					letter = 'B';
				}
			} else {
				if( format ) {
					letter = 'N';
				} else if( longitude < 0 ) {
					letter = 'Y';
				} else {
					letter = 'Z';
				}
			}
		}
		if( returnType ) {
			if( precise ) {
				tmp[0] = prettyNumber( tmp[0], 6 );
				tmp[1] = prettyNumber( tmp[1], 6 );
			} else {
				tmp[0] = Math.round(tmp[0]);
				tmp[1] = Math.round(tmp[1]);
			}
			return letter+' '+tmp[0]+' '+tmp[1];
		} else {
			return [ ( latitude < 0 ) ? -1 : 1, tmp[0], tmp[1], letter ];
		}
	};

	//conversions between different latitude/longitude formats
	GridRefUtils.prototype.ddToDms = function ( N, E, onlyDm, returnType ) {
		//decimal degrees (49.5,-123.5) to degrees-minutes-seconds (49°30'0"N, 123°30'0"W)
		var args = expandParams( N, 2, 2, [ E, onlyDm, returnType ] );
		N = args[0]; E = args[1]; onlyDm = args[2]; returnType = args[3];
		var NAbs = Math.abs(N);
		var EAbs = Math.abs(E);
		var degN = Math.floor(NAbs);
		var degE = Math.floor(EAbs);
		var minN, secN, minE, secE;
		if( onlyDm ) {
			minN = 60 * ( NAbs - degN );
			secN = 0;
			minE = 60 * ( EAbs - degE );
			secE = 0;
		} else {
			//the approach used here is careful to respond consistently to floating point errors for all of degrees/minutes/seconds
			//errors should not cause one to be increased while another is decreased (which could cause eg. 5 minutes 60 seconds)
			minN = 60 * NAbs;
			secN = ( minN - Math.floor( minN ) ) * 60;
			minN = Math.floor( minN % 60 );
			minE = 60 * EAbs;
			secE = ( minE - Math.floor( minE ) ) * 60;
			minE = Math.floor( minE % 60 );
		}
		if( returnType ) {
			var deg = '&deg;', quot = '&quot;';
			if( returnType == this.TEXT ) {
				deg = '\u00B0';
				quot = '"';
			}
			if( onlyDm ) {
				//careful not to round up to 60 minutes when displaying
				return degN + deg + prettyNumber( ( minN >= 59.999999995 ) ? 59.99999999 : minN, 8 ) + "'" + ( ( N < 0 ) ? 'S' : 'N' ) + ', ' +
					degE + deg + prettyNumber( ( minE >= 59.999999995 ) ? 59.99999999 : minE, 8 ) + "'" + ( ( E < 0 ) ? 'W' : 'E' );
			} else {
				//careful not to round up to 60 seconds when displaying
				return degN + deg + minN + "'" + prettyNumber( ( secN >= 59.9999995 ) ? 59.999999 : secN, 6 ) + quot + ( ( N < 0 ) ? 'S' : 'N' ) + ', ' +
					degE + deg + minE + "'" + prettyNumber( ( secE >= 59.9999995 ) ? 59.999999 : secE, 6 ) + quot + ( ( E < 0 ) ? 'W' : 'E' );
			}
		} else {
			return [ degN, minN, secN, ( N < 0 ) ? -1 : 1, degE, minE, secE, ( E < 0 ) ? -1 : 1 ];
		}
	};
	GridRefUtils.prototype.ddFormat = function( N, E, noUnits, returnType ) {
		//different formats of decimal degrees (49.5,-123.5)
		var args = expandParams( N, 2, 2, [ E, noUnits, returnType ] );
		N = args[0]; E = args[1]; noUnits = args[2]; returnType = args[3];
		if( noUnits && returnType ) {
			return genericLatLongCoords( returnType, N, E );
		}
		E = wrapAroundLongitude(E);
		//rounding needs to happen before testing for signs and letters, rounds *down* negative numbers just like toFixed
		if( returnType && E <= -179.99999999995 ) {
			E = 180;
		}
		var latMul, longMul;
		if( noUnits ) {
			latMul = longMul = 1;
		} else {
			latMul = returnType ? ( ( N < 0 ) ? 'S' : 'N' ) : ( ( N < 0 ) ? -1 : 1 );
			longMul = returnType ? ( ( E < 0 ) ? 'W' : 'E' ) : ( ( E < 0 ) ? -1 : 1 );
			N = Math.abs(N);
			E = Math.abs(E);
		}
		if( returnType ) {
			var deg = ( returnType == this.TEXT ) ? '\u00B0' : '&deg;';
			return prettyNumber( N, 10 ) + deg + latMul + ', ' + prettyNumber( E, 10 ) + deg + longMul;
		}
		return [ N, 0, 0, latMul, E, 0, 0, longMul ];
	};
	GridRefUtils.prototype.dmsToDd = function ( dms, returnType, denyBadCoords, allowUnitless ) {
		//degrees-minutes-seconds (49°30'0"N, 123°30'0"W) to decimal degrees (49.5,-123.5)
		var latlong;
		if( dms && dms.constructor == Array ) {
			//passed an array of values, which can be unshifted with gaps to get the right positions
			latlong = ['','',dms[0],'',dms[1],'',dms[2],'',dms[3],'',dms[4],'',dms[5],'',dms[6],dms[7]];
		} else {
			if( typeof(dms) != 'string' ) {
				dms = dms + '';
			}
			dms = dms.toUpperCase();
			latlong = null;
			//matches [-]<float>,[-]<float> which could also be grid coordinates, so this is kept behind an option
			if( allowUnitless && ( latlong = dms.match(/^\s*(-?)([\d\.]+)\s*,\s*(-?)([\d\.]+)\s*$/) ) ) {
				latlong = ['',latlong[1],latlong[2],'','0','','0','','',latlong[3],latlong[4],'','0','','0',''];
			}
			//matches [-]<float>[NS],[-]<float>[EW] with non-optional NSEW
			if( !latlong && ( latlong = dms.match(/^\s*(-?)([\d\.]+)\s*([NS])\s*,?\s*(-?)([\d\.]+)\s*([EW])\s*$/) ) ) {
				latlong = ['',latlong[1],latlong[2],'','0','','0','',latlong[3],latlong[4],latlong[5],'','0','','0',latlong[6]];
			}
			//simple regex ;) ... matches upto 3 sets of number[non-number] per northing/easting (allows for DMS, DM or D)
			//avoid using non-capturing groups for maximum compatibility
			//['','-','d','','m','','s','','N','-','d','','m','','s','E'];
			//note that this cannot accept HTML strings from ddToDms as it will not match &quot;
			//[-]<float><separator>[<float><separator>[<float><separator>]]([NS][,]|,)[-]<float><separator>[<float><separator>[<float><separator>]][EW]
			//Captures -, <float>, <float>, <float>, [NS], -, <float>, <float>, <float>, [EW]
			if( !latlong && !( latlong = dms ? dms.toUpperCase().match( /^\s*(-?)([\d\.]+)\D\s*(([\d\.]+)\D\s*(([\d\.]+)\D\s*)?)?(([NS])\s*,?|,)\s*(-?)([\d\.]+)\D\s*(([\d\.]+)\D\s*(([\d\.]+)\D\s*)?)?([EW]?)\s*$/ ) : null ) ) {
				//invalid format
				if( denyBadCoords ) {
					return false;
				}
				//assume 0,0
				latlong = ['','','0','','0','','0','','N','','0','','0','','0','E'];
			}
			//JS does not implicitly cast string to number when adding; operator concatenates instead
			latlong[2] = parseFloat(latlong[2]);
			latlong[4] = parseFloat(latlong[4]);
			latlong[6] = parseFloat(latlong[6]);
			latlong[10] = parseFloat(latlong[10]);
			latlong[12] = parseFloat(latlong[12]);
			latlong[14] = parseFloat(latlong[14]);
			if( denyBadCoords && ( isNaN(latlong[2]) || isNaN(latlong[10]) ) ) {
				return false;
			}
		}
		if( !latlong[4] ) { latlong[4] = 0; }
		if( !latlong[6] ) { latlong[6] = 0; }
		if( !latlong[12] ) { latlong[12] = 0; }
		if( !latlong[14] ) { latlong[14] = 0; }
		var lat = latlong[2] + latlong[4] / 60 + latlong[6] / 3600;
		if( latlong[1] ) { lat *= -1; }
		if( latlong[8] == 'S' || latlong[8] == -1 ) { lat *= -1; }
		var longt = latlong[10] + latlong[12] / 60 + latlong[14] / 3600;
		if( latlong[9] ) { longt *= -1; }
		if( latlong[15] == 'W' || latlong[15] == -1 ) { longt *= -1; }
		return genericLatLongCoords( returnType, lat, longt );
	};

	//Helmert transform parameters used during Helmert transformations
	//OSGB<->WGS84 parameters taken from "6.6 Approximate WGS84 to OSGB36/ODN transformation"
	//http://www.ordnancesurvey.co.uk/oswebsite/gps/information/coordinatesystemsinfo/guidecontents/guide6.html
	var Helmerts = {
		WGS84_to_OSGB36: {
			tx: -446.448,
			ty: 125.157,
			tz: -542.060,
			s: 20.4894,
			rx: -0.1502,
			ry: -0.2470,
			rz: -0.8421
		},
		OSGB36_to_WGS84: {
			tx: 446.448,
			ty: -125.157,
			tz: 542.060,
			s: -20.4894,
			rx: 0.1502,
			ry: 0.2470,
			rz: 0.8421
		},
		//Ireland65<->WGS84 parameters taken from http://en.wikipedia.org/wiki/Helmert_transformation
		WGS84_to_Ireland65: {
			tx: -482.53,
			ty: 130.596,
			tz: -564.557,
			s: -8.15,
			rx: 1.042,
			ry: 0.214,
			rz: 0.631
		},
		Ireland65_to_WGS84: {
			tx: 482.53,
			ty: -130.596,
			tz: 564.557,
			s: 8.15,
			rx: -1.042,
			ry: -0.214,
			rz: -0.631
		}
	};
	GridRefUtils.prototype.getTransformation = function (name) {
		return Helmerts[name] || false;
	};
	GridRefUtils.prototype.createTransformation = function ( tx, ty, tz, s, rx, ry, rz ) {
		return { tx: tx, ty: ty, tz: tz, s: s, rx: rx, ry: ry, rz: rz };
	};
	GridRefUtils.prototype.HelmertTransform = function ( N, E, H, efrom, via, eto, returnType ) {
		//conversion according to formulae listed on http://www.movable-type.co.uk/scripts/latlong-convert-coords.html
		//parts taken from http://www.ordnancesurvey.co.uk/oswebsite/gps/information/coordinatesystemsinfo/guidecontents/
		var args = expandParams( N, 2, 3, [ E, H, efrom, via, eto, returnType ] );
		N = args[0]; E = args[1]; H = args[2]; efrom = args[3]; via = args[4]; eto = args[5]; returnType = args[6];
		if( !efrom || typeof(efrom.a) == 'undefined' || !via || typeof(via.tx) == 'undefined' || !eto || typeof(eto.a) == 'undefined' ) {
			return false;
		}
		var hasHeight = isNumeric(H);
		if( !hasHeight ) {
			H = 0;
		}
		//work in radians
		N *= Math.PI / 180;
		E *= Math.PI / 180;
		//convert polar coords to cartesian
		//eccentricity-squared of source ellipsoid from Annexe B B1
		var e2 = 1 - ( efrom.b * efrom.b ) / ( efrom.a * efrom.a );
		var sinPhi = Math.sin( N );
		var cosPhi = Math.cos( N );
		//transverse radius of curvature
		var Nu = efrom.a / Math.sqrt( 1 - e2 * sinPhi * sinPhi );
		var x = ( Nu + H ) * cosPhi * Math.cos( E );
		var y = ( Nu + H ) * cosPhi * Math.sin( E );
		var z = ( ( 1 - e2 ) * Nu + H ) * sinPhi;
		//transform parameters
		var tx = via.tx, ty = via.ty, tz = via.tz, s = via.s, rx = via.rx, ry = via.ry, rz = via.rz;
		//convert seconds to radians
		rx *= Math.PI / 648000;
		ry *= Math.PI / 648000;
		rz *= Math.PI / 648000;
		//convert ppm to pp_one, and add one to avoid recalculating
		s = s / 1000000 + 1;
		//apply Helmert transform (algorithm notes incorrectly show rx instead of rz in x1 line)
		var x1 = tx + s * x - rz * y + ry * z;
		var y1 = ty + rz * x + s * y - rx * z;
		var z1 = tz - ry * x + rx * y + s * z;
		//convert cartesian coords back to polar
		//eccentricity-squared of destination ellipsoid from Annexe B B1
		e2 = 1 - ( eto.b * eto.b ) / ( eto.a * eto.a );
		var p = Math.sqrt( x1 * x1 + y1 * y1 );
		var Phi = Math.atan2( z1, p * ( 1 - e2 ) );
		var Phi1 = 2 * Math.PI;
		var accuracy = 0.000001 / eto.a; //0.01 mm accuracy, though the OS transform itself only has 4-5 metres
		var loopcount = 0;
		//due to number precision, it is possible to get infinite loops here for extreme cases (especially for invalid parameters)
		//in tests, upto 4 loops are needed - if it reaches 100, assume it must be infinite, and break out
		while( Math.abs( Phi - Phi1 ) > accuracy && loopcount++ < 100 ) {
			sinPhi = Math.sin( Phi );
			Nu = eto.a / Math.sqrt( 1 - e2 * sinPhi * sinPhi );
			Phi1 = Phi;
			Phi = Math.atan2( z1 + e2 * Nu * sinPhi, p );
		}
		var Lambda = Math.atan2( y1, x1 );
		H = ( p / Math.cos( Phi ) ) - Nu;
		//done converting - return in degrees
		var latitude = Phi * ( 180 / Math.PI );
		var longitude = Lambda * ( 180 / Math.PI );
		return genericLatLongCoords( returnType, latitude, longitude, hasHeight ? H : null );
	};

	//polynomial transforming lat-long coordinates, such as OSi/LPS (Irish) Polynomial Transformation
	var polycoeffs = {
		OSiLPS: {
			order: 3,
			positive: true,
			latm: 53.5,
			longm: -7.7,
			k0: 0.1,
			A: [
				[0.763,0.123,0.183,-0.374],
				[-4.487,-0.515,0.414,13.110],
				[0.215,-0.570,5.703,113.743],
				[-0.265,2.852,-61.678,-265.898]
			],
			B: [
				[-2.810,-4.680,0.170,2.163],
				[-0.341,-0.119,3.913,18.867],
				[1.196,4.877,-27.795,-284.294],
				[-0.887,-46.666,-95.377,-853.950]
			]
		}
	};
	GridRefUtils.prototype.getPolynomialCoefficients = function (name) {
		return polycoeffs[name] || false;
	};
	GridRefUtils.prototype.createPolynomialCoefficients = function ( order, positive, latm, longm, k0, A, B ) {
		if( !A || A.constructor != Array || !A[0] || A[0].constructor != Array || !B || B.constructor != Array || !B[0] || B[0].constructor != Array ) {
			return false;
		}
		return { order: order, positive: positive, latm: latm, longm: longm, k0: k0, A: A, B: B };
	};
	function polynomial3( latitude, longitude, latm, longm, k0, A, B ) {
		//transformation according to Transformations and OSGM15 User Guide
		//https://www.ordnancesurvey.co.uk/business-government/tools-support/os-net/for-developers
		var U = k0 * ( latitude - latm );
		var V = k0 * ( longitude - longm );
		//0 order
		var dlat = ( A[0][0] +
			//1st order
			A[1][0] * U + A[0][1] * V + A[1][1] * U * V +
			//2nd order
			A[2][0] * U*U + A[0][2] * V*V + A[2][1] * U*U * V + A[1][2] * U * V*V + A[2][2] * U*U * V*V +
			//3rd order
			A[3][0] * U*U*U + A[0][3] * V*V*V + A[3][1] * U*U*U * V + A[1][3] * U * V*V*V + A[3][2] * U*U*U * V*V + A[2][3] * U*U * V*V*V + A[3][3] * U*U*U * V*V*V
		) / 3600;
		//0 order
		var dlong = ( B[0][0] +
			//1st order
			B[1][0] * U + B[0][1] * V + B[1][1] * U * V +
			//2nd order
			B[2][0] * U*U + B[0][2] * V*V + B[2][1] * U*U * V + B[1][2] * U * V*V + B[2][2] * U*U * V*V +
			//3rd order
			B[3][0] * U*U*U + B[0][3] * V*V*V + B[3][1] * U*U*U * V + B[1][3] * U * V*V*V + B[3][2] * U*U*U * V*V + B[2][3] * U*U * V*V*V + B[3][3] * U*U*U * V*V*V
		) / 3600;
		return [ dlat, dlong ];
	}
	GridRefUtils.prototype.polynomialTransform = function ( latitude, longitude, coefficients, returnType ) {
		var args = expandParams( latitude, 2, 2, [ longitude, coefficients, returnType ] );
		latitude = args[0]; longitude = args[1]; coefficients = args[2]; returnType = args[3];
		var shifts;
		if( coefficients && coefficients.order == 3 ) {
			//currently, third order polynomial is the only supported type
			shifts = polynomial3( latitude, longitude, coefficients.latm, coefficients.longm, coefficients.k0, coefficients.A, coefficients.B );
		} else {
			return false;
		}
		return genericLatLongCoords( returnType, coefficients.positive ? ( latitude + shifts[0] ) : ( latitude - shifts[0] ), coefficients.positive ? ( longitude + shifts[1] ) : ( longitude - shifts[1] ) );
	};
	GridRefUtils.prototype.reversePolynomialTransform = function ( latitude, longitude, coefficients, returnType ) {
		var args = expandParams( latitude, 2, 2, [ longitude, coefficients, returnType ] );
		latitude = args[0]; longitude = args[1]; coefficients = args[2]; returnType = args[3];
		var slat = 0, slong = 0, difflat, difflong, latshifted = latitude, longshifted = longitude, shifts;
		var loopcount = 0;
		do {
			//get the shifts for the current estimated point, starting with the initial easting and northing
			if( coefficients && coefficients.order == 3 ) {
				//currently, third order polynomial is the only supported type
				shifts = polynomial3( latshifted, longshifted, coefficients.latm, coefficients.longm, coefficients.k0, coefficients.A, coefficients.B );
			} else {
				return false;
			}
			if( !shifts ) {
				return genericLatLongCoords( returnType, Number.NaN, Number.NaN );
			}
			difflat = slat - shifts[0];
			difflong = slong - shifts[1];
			slat = shifts[0];
			slong = shifts[1];
			//apply the new shift and try again with the shift from the new point
			latshifted = coefficients.positive ? ( latitude - slat ) : ( latitude + slat );
			longshifted = coefficients.positive ? ( longitude - slong ) : ( longitude + slong );
			//see if the shift changes with each iteration
			//unlikely to ever hit 100 iterations, even when taken to extremes
		} while( ( Math.abs( difflat ) > 1e-12 || Math.abs( difflong ) > 1e-12 ) && ++loopcount < 100 );
		return genericLatLongCoords( returnType, latshifted, longshifted );
	}

	//geodesics
	if( typeof(Number.EPSILON) == 'undefined' ) {
		//used by geodesy functions to test for accuracy limits
		var epsilon = 1;
		do {
			epsilon /= 2;
		} while( 1 + ( epsilon / 2 ) != 1 );
		Number.EPSILON = epsilon;
	}
	//Vincenty formulas with precision detection from https://www.movable-type.co.uk/scripts/latlong-vincenty.html and additional error handling
	//direct
	GridRefUtils.prototype.getGeodesicDestination = function ( latfrom, longfrom, geodeticdistance, bearingfrom, ellipsoid, returnType ) {
		var args = expandParams( latfrom, 2, 2, [ longfrom, geodeticdistance, bearingfrom, ellipsoid, returnType ] );
		latfrom = args[0]; longfrom = args[1]; geodeticdistance = args[2]; bearingfrom = args[3]; ellipsoid = args[4]; returnType = args[5];
		args = expandParams( geodeticdistance, 2, 2, [ bearingfrom, ellipsoid, returnType ] );
		geodeticdistance = args[0]; bearingfrom = args[1]; ellipsoid = args[2]; returnType = args[3];
		if( !ellipsoid || typeof(ellipsoid) != 'object' || typeof(ellipsoid.a) == 'undefined' || typeof(ellipsoid.b) == 'undefined' ) {
			return false;
		}
		var f = ( ellipsoid.a - ellipsoid.b ) / ellipsoid.a;
		longfrom = wrapAroundLongitude(longfrom);
		var lat1 = latfrom * Math.PI / 180;
		var long1 = longfrom * Math.PI / 180;
		bearingfrom *= Math.PI / 180;
		//U is called "reduced latitude", latitude on the auxiliary sphere
		var tanU1 = ( 1 - f ) * Math.tan(lat1);
		//use trig identities instead of atan then cos or sin
		var cosU1 = 1 / Math.sqrt( 1 + Math.pow( tanU1, 2 ) );
		var sinU1 = tanU1 * cosU1;
		var s1 = Math.atan2( tanU1, Math.cos(bearingfrom) );
		//a = forward azimuth of the geodesic at the equator, if it were extended that far
		var sinA = cosU1 * Math.sin(bearingfrom);
		var sin2A = Math.pow( sinA, 2 );
		var cos2A = 1 - sin2A;
		var u2 = cos2A * ( Math.pow( ellipsoid.a, 2 ) - Math.pow( ellipsoid.b, 2 ) ) / Math.pow( ellipsoid.b, 2 );
		var A = 1 + u2 * ( 4096 + u2 * ( u2 * ( 320 - 175 * u2 ) - 768 ) ) / 16384;
		var B = u2 * ( 256 + u2 * ( u2 * ( 74 - 47 * u2 ) - 128 ) ) / 1024;
		//s = angular separation between points on auxiliary sphere
		var s = geodeticdistance / ( ellipsoid.b * A ); //initial approximation
		var loopcount = 0;
		var cos2SM, cos22SM, sin2s, deltaS, sold;
		do {
			//SM = angular separation between the midpoint of the line and the equator
			cos2SM = Math.cos( 2 * s1 + s );
			cos22SM = Math.pow( cos2SM, 2 );
			sin2s = Math.sin(s);
			//difference in angular separation between auxiliary sphere and ellipsoid
			deltaS = B * sin2s * ( cos2SM + B * ( Math.cos(s) * ( 2 * Math.pow( cos2SM, 2 ) - 1 ) - B * cos2SM * ( 4 * Math.pow( sin2s, 2 ) - 3 ) * ( 4 * cos22SM - 3 ) / 6 ) / 4 );
			sold = s;
			s = geodeticdistance / ( ellipsoid.b * A ) + deltaS;
		} while( Math.abs( s - sold ) < 1E-12 && ++loopcount < 500 );
		var sins = Math.sin(s);
		var coss = Math.cos(s);
		var sinbearing = Math.sin(bearingfrom);
		var cosbearing = Math.cos(bearingfrom);
		var lat2 = Math.atan2( sinU1 * coss + cosU1 * sins * cosbearing, ( 1 - f ) * Math.sqrt( sin2A + Math.pow( sinU1 * sins - cosU1 * coss * cosbearing, 2 ) ) );
		//difference in longitude of the points on the auxiliary sphere
		var lambda = Math.atan2( sins * sinbearing, cosU1 * coss - sinU1 * sins * cosbearing );
		var C = f * cos2A * ( 4 + f * ( 4 - 3 * cos2A ) ) / 16;
		//longitudinal difference of the points on the ellipsoid
		var L = lambda - ( 1 - C) * f * sinA * ( s + C * sins * ( cos2SM + C * coss * ( 2 * cos22SM - 1 ) ) );
		var long2 = long1 + L;
		var longto = wrapAroundLongitude( long2 * ( 180 / Math.PI ) );
		var latto = lat2 * ( 180 / Math.PI );
		var bearing2 = Math.atan2( sinA, -1 * ( sinU1 * sins - cosU1 * coss * cosbearing ) );
		var bearingto = wrapAroundAzimuth( bearing2 * ( 180 / Math.PI ) );
		if( returnType ) {
			//sprintf to produce simple numbers instead of scientific notation
			bearingto = prettyNumber( bearingto, 6 );
			//bearing can be 360, after rounding
			if( bearingto == '360.000000' ) {
				bearingto = '0.000000';
			}
			var deg = ( returnType == this.TEXT ) ? '\u00B0' : '&deg;';
			return genericLatLongCoords( returnType, latto, longto ) + ' @ ' + bearingto + deg ;
		} else {
			return [ latto, longto, bearingto ];
		}
	}
	//inverse
	GridRefUtils.prototype.getGeodesic = function ( latfrom, longfrom, latto, longto, ellipsoid, returnType ) {
		var args = expandParams( latfrom, 2, 2, [ longfrom, latto, longto, ellipsoid, returnType ] );
		latfrom = args[0]; longfrom = args[1]; latto = args[2]; longto = args[3]; ellipsoid = args[4]; returnType = args[5];
		args = expandParams( latto, 2, 2, [ longto, ellipsoid, returnType ] );
		latto = args[0]; longto = args[1]; ellipsoid = args[2]; returnType = args[3];
		if( !ellipsoid || typeof(ellipsoid) != 'object' || typeof(ellipsoid.a) == 'undefined' || typeof(ellipsoid.b) == 'undefined' ) {
			return false;
		}
		var f = ( ellipsoid.a - ellipsoid.b ) / ellipsoid.a;
		longfrom = wrapAroundLongitude(longfrom);
		longto = wrapAroundLongitude(longto);
		var lat1 = latfrom * Math.PI / 180;
		var long1 = longfrom * Math.PI / 180;
		var lat2 = latto * Math.PI / 180;
		var long2 = longto * Math.PI / 180;
		var L = long2 - long1; //longitudinal difference
		//U is called "reduced latitude", latitude on the auxiliary sphere
		var tanU1 = ( 1 - f ) * Math.tan(lat1);
		var tanU2 = ( 1 - f ) * Math.tan(lat2);
		//use trig identities instead of atan then cos or sin
		var cosU1 = 1 / Math.sqrt( 1 + Math.pow( tanU1, 2 ) );
		var cosU2 = 1 / Math.sqrt( 1 + Math.pow( tanU2, 2 ) );
		var sinU1 = tanU1 * cosU1;
		var sinU2 = tanU2 * cosU2;
		var lambda = L; //difference in longitude on an auxiliary sphere, initial approximation
		//start detecting failures, not part of Vincenty formulas
		var detectedproblem = false;
		//~1 iteration for spheres, ~3-4 for spheroids
		//extreme spheroids need more; a=4b can need 80+
		//allow max 500 iterations, or bearing results flip around when resolution fails
		var maxloops = 500;
		//check if the points are somewhat far from each other, for use when handling precision errors
		//either the latitudes are more than half a circle different, so that the points are near opposite poles
		//or the latitudes are nearly opposite and longitudes are similarly different (points far apart near-ish the equator)
		var antipodal = Math.abs( lat2 - lat1 ) > Math.PI / 2 || ( Math.abs( lat1 + lat2 ) < Math.PI / 2 && Math.cos(L) <= 0 );
		var prolate = ellipsoid.b > ellipsoid.a;
		var sinS, cos2A, cos2SM, cosS, s, sinA, C, lambdaold;
		do {
			sinS = Math.sqrt( Math.pow( cosU2 * Math.sin(lambda), 2 ) + Math.pow( cosU1 * sinU2 - sinU1 * cosU2 * Math.cos(lambda), 2 ) );
			//prolate spheroids often manage to resolve in spite of having a tiny sinS
			//their antipodal distances are much harder because they do not travel over the pole, and need the full calculation
			if( Math.abs( sinS ) < Number.EPSILON && ( !prolate || !antipodal ) ) {
				//not part of Vincenty formulas
				//when the sine is smaller than the number precision, the iteration can give wildly inaccurate results, happens when:
				//* the points are almost opposite each other; measure the arc via the north pole
				//* the points are very close to each other, length becomes 0
				//* the points are the same, length is 0
				detectedproblem = true;
				cos2A = 1; //how much of the transit is via the minor axis (smaller numbers use the major axis dimensions)
				cos2SM = sinS = cosS = 0; //these will not be used
				s = antipodal ? Math.PI : 0; //distance around the spheroid
				break;
			}
			cosS = sinU1 * sinU2 + cosU1 * cosU2 * Math.cos(lambda);
			s = Math.atan2( sinS, cosS ); //angular separation between points
			//a = forward azimuth of the geodesic at the equator, if it were extended that far
			sinA = cosU1 * cosU2 * Math.sin(lambda) / sinS;
			cos2A = 1 - Math.pow( sinA, 2 );
			//SM is angular separation between the midpoint of the line and the equator
			//at an equatorial line, cos2A becomes 0, and dividing by 0 is invalid, so set cos2SM to 0 instead
			cos2SM = cos2A ? ( cosS - 2 * sinU1 * sinU2 / cos2A ) : 0;
			C = f * cos2A * ( 4 + f * ( 4 - 3 * cos2A ) ) / 16;
			lambdaold = lambda;
			lambda = L + ( 1 - C ) * f * sinA * ( s + C * sinS * ( cos2SM + C * cosS * ( -1 + 2 * Math.pow( cos2SM, 2 ) ) ) );
			//lambda > PI when it fails to resolve, but also sometimes when it works - rely only on loop counting
		} while( Math.abs( lambda - lambdaold ) > 1e-12 && --maxloops );
		detectedproblem = detectedproblem || !maxloops;
		var u2 = cos2A * ( Math.pow( ellipsoid.a, 2 ) - Math.pow( ellipsoid.b, 2 ) ) / Math.pow( ellipsoid.b, 2 );
		var A = 1 + u2 * ( 4096 + u2 * ( u2 * ( 320 - 175 * u2 ) - 768 ) ) / 16384;
		var B = u2 * ( 256 + u2 * ( u2 * ( 74 - 47 * u2 ) - 128 ) ) / 1024;
		//difference in angular separation between auxiliary sphere and ellipsoid
		var deltaS = B * sinS * ( cos2SM + B * ( cosS * ( 2 * cos2SM - 1 ) - B * cos2SM * ( 4 * Math.pow( sinS, 2 ) - 3 ) * ( 4 * cos2SM - 3 ) / 6 ) / 4 );
		var geodeticdistance = ellipsoid.b * A * ( s - deltaS );
		var problemdata = null;
		var bearingfrom, bearingto;
		if( detectedproblem ) {
			//error handling to try to get useful bearings - not part of the Vincenty formulas
			problemdata = { detectiontype: maxloops ? 'BELOW_RESOLUTION' : 'DID_NOT_CONVERGE' };
			if( antipodal ) {
				if( Math.abs( latfrom - latto ) == 180 || ( latfrom + latto == 0 && Math.abs( longto - longfrom ) == 180 ) ) {
					problemdata.separation = 'ANTIPODAL';
				} else {
					problemdata.separation = 'NEARLY_ANTIPODAL';
				}
			} else {
				problemdata.separation = 'NEARLY_IDENTICAL';
			}
			if( latfrom == latto && ( longfrom == longto || latfrom == 90 || latfrom == -90 ) ) {
				//identical points, nothing useful can be returned
				bearingfrom = bearingto = 0;
				problemdata.distanceconfidence = 1;
				problemdata.azimuthconfidence = 1;
				problemdata.separation = 'IDENTICAL';
			} else if( latfrom == 90 ) {
				//it seems to always converge when one of the points is on a pole, but these fixes are provided just in case they are needed
				//with Vincenty formulae, azimuth at 90,* is measured with 180 lined up with the longitude, like it would at 0,<longitude>
				bearingfrom = Math.PI + long1 - long2;
				//it comes down the longto meridian, so it will always be seen as 180, even when latto is -90
				bearingto = Math.PI;
				problemdata.distanceconfidence = ( latto == -90 ) ? 1 : 0.99;
				problemdata.azimuthconfidence = 1;
			} else if( latfrom == -90 ) {
				//azimuth at -90,* is measured with 0 lined up with the longitude
				bearingfrom = long2 - long1;
				bearingto = 0;
				problemdata.distanceconfidence = ( latto == 90 ) ? 1 : 0.99;
				problemdata.azimuthconfidence = 1;
			} else if( latto == 90 ) {
				//always north up their own meridian
				bearingfrom = 0;
				bearingto = long2 - long1;
				problemdata.distanceconfidence = ( latfrom == -90 ) ? 1 : 0.99;
				problemdata.azimuthconfidence = 1;
			} else if( latto == -90 ) {
				//always south
				bearingfrom = Math.PI;
				bearingto = Math.PI + long1 - long2;
				problemdata.distanceconfidence = ( latfrom == 90 ) ? 1 : 0.99;
				problemdata.azimuthconfidence = 1;
			} else if( antipodal ) {
				problemdata.distanceconfidence = ( problemdata.detectiontype == 'DID_NOT_CONVERGE' ) ? 0.6 : 0.98;
				if( prolate) {
					//prolate spheroid, the direction will be east or west
					if( Math.sin( long2 - long1 ) < 0 ) {
						bearingfrom = Math.PI / -2;
						bearingto = Math.PI / -2;
					} else {
						bearingfrom = Math.PI / 2;
						bearingto = Math.PI / 2;
					}
					problemdata.azimuthconfidence = 0.9;
				} else {
					//with antipodal points, always travel via the poles; choose the closest one if the points are not quite identical, or north otherwise
					if( lat1 + lat2 < 0 ) {
						bearingfrom = Math.PI;
						bearingto = 0;
					} else {
						bearingfrom = 0;
						bearingto = Math.PI;
					}
					problemdata.azimuthconfidence = ( Math.abs( longto - longfrom ) == 180 ) ? 1 : 0.9;
				}
				//in this case, the length is left with whatever it was
			} else {
				//points close together; below number resolution required for trig functions
				//can be calculated using basic geometry near the equator, but this is nonsense near the poles, back to being the original problem
				//this cannot be calculated in any reliable way, but this approximation is better than nothing over much of the ellipsoid
				var north = latto > latfrom;
				var south = latfrom > latto;
				var east = longto > longfrom || longfrom - longto >= 180;
				var west = longfrom > longto || longto - longfrom > 180;
				if( north && east ) {
					bearingfrom = bearingto = Math.PI / 4;
				} else if( !north && !south && east ) {
					bearingfrom = bearingto = Math.PI / 2;
				} else if( south && east ) {
					bearingfrom = bearingto = 3 * Math.PI / 4;
				} else if( south && !east && !west ) {
					bearingfrom = bearingto = Math.PI;
				} else if( south && west ) {
					bearingfrom = bearingto = 5 * Math.PI / 4;
				} else if( !north && !south && west ) {
					bearingfrom = bearingto = 3 * Math.PI / 2;
				} else if( north && west ) {
					bearingfrom = bearingto = 7 * Math.PI / 4;
				} else {
					bearingfrom = bearingto = 0;
				}
				problemdata.distanceconfidence = 0.98;
				problemdata.azimuthconfidence = 0.5 * Math.cos(lat1);
			}
		} else {
			//actual measurement
			bearingfrom = Math.atan2( cosU2 * Math.sin(lambda), ( cosU1 * sinU2 ) - ( sinU1 * cosU2 * Math.cos(lambda) ) );
			bearingto = Math.atan2( cosU1 * Math.sin(lambda), ( cosU1 * sinU2 * Math.cos(lambda) ) - ( sinU1 * cosU2 ) );
		}
		//Vincenty formula returns bearings of -180 to 180, and error handling can make numbers greater than expected bounds
		bearingfrom = wrapAroundAzimuth( bearingfrom * 180 / Math.PI );
		bearingto = wrapAroundAzimuth( bearingto * 180 / Math.PI );
		if( returnType ) {
			//produce simple numbers instead of scientific notation
			bearingfrom = prettyNumber( bearingfrom, 6 );
			bearingto = prettyNumber( bearingto, 6 );
			//bearing can be 360, after rounding
			if( bearingfrom == '360.000000' ) {
				bearingfrom = '0.000000';
			}
			if( bearingto == '360.000000' ) {
				bearingto = '0.000000';
			}
			var deg = ( returnType == this.TEXT ) ? '\u00B0' : '&deg;';
			var angle = ( returnType == this.TEXT ) ? '>' : '&gt;';
			return ( ( detectedproblem || lambda > Math.PI || !maxloops ) ? '~ ' : '' ) + prettyNumber( geodeticdistance, 6 ) + ' m, ' + bearingfrom + deg + ' =' + angle + ' ' + bearingto + deg ;
		} else {
			return [ geodeticdistance, bearingfrom, bearingto, problemdata ];
		}
	}

	//lat-long based geoid grid
	var geoidcache = {};
	var geoidgrds = {
		OSGM15_Belfast: {
			name: 'OSGM15_Belfast',
			latmin: 51.000000,
			latmax: 56.000000,
			longmin: -11.500000,
			longmax: -5.000000,
			latspacing: 0.013333,
			longspacing: 0.020000,
			latstepdp: 6,
			longstepdp: 6,
		},
		OSGM15_Malin: {
			name: 'OSGM15_Malin',
			latmin: 51.000000,
			latmax: 56.000000,
			longmin: -11.500000,
			longmax: -5.000000,
			latspacing: 0.013333,
			longspacing: 0.020000,
			latstepdp: 6,
			longstepdp: 6,
		},
		EGM96_ww15mgh: {
			name: 'EGM96_ww15mgh',
			latmin: -90.000000,
			latmax: 90.000000,
			longmin: .000000,
			longmax: 360.000000,
			latspacing: .250000,
			longspacing: .250000,
			latstepdp: 6,
			longstepdp: 6,
		}
	};
	GridRefUtils.prototype.getGeoidGrd = function (name) {
		return geoidgrds[name] || false;
	}
	GridRefUtils.prototype.createGeoidGrd = function ( name, latmin, latmax, longmin, longmax, latspacing, longspacing, latstepdp, longstepdp ) {
		if( typeof('name') != 'string' || !name.length || !( latspacing * 1 ) || !( longspacing * 1 ) || latspacing < 0 || longspacing < 0 || latmax < latmin || longmax < longmin ) {
			return false;
		}
		return { name: name, latmin: latmin, latmax: latmax, longmin: longmin, longmax: longmax, latspacing: latspacing, longspacing: longspacing, latstepdp: latstepdp, longstepdp: longstepdp };
	};
	GridRefUtils.prototype.createGeoidRecord = function ( recordindex, geoidUndulation ) {
		return { name: recordindex, height: geoidUndulation };
	};
	GridRefUtils.prototype.cacheGeoidRecords = function ( name, records ) {
		if( typeof('name') != 'string' || !name.length ) {
			return false;
		}
		//prevent JS native object property clashes
		name = 'c_' + name;
		if( !geoidcache[name] ) {
			//nothing has ever been cached for this geoid set, create the cache
			geoidcache[name] = {};
		}
		if( !records ) {
			return true;
		}
		//allow it to accept either a single record, or an array of records
		if( records.constructor != Array ) {
			records = [records];
		}
		for( var i = 0; i < records.length; i++ ) {
			if( typeof(records[i].name) == 'undefined' || typeof(records[i].height) == 'undefined' ) {
				return false;
			}
			geoidcache[name][ 'r_' + records[i].name ] = records[i].height;
		}
		return true;
	};
	GridRefUtils.prototype.deleteGeoidCache = function ( name ) {
		if( typeof(name) == 'string' && name.length ) {
			delete geoidcache[ 'c_' + name ];
		} else {
			geoidcache = {};
		}
	}
	function getGeoidRecords( geoidgrd, records, fetchRecords, returnCallback ) {
		//prevent JS native object property clashes
		var name = 'c_' + geoidgrd.name;
		if( !geoidcache[name] ) {
			//nothing has ever been cached for this geoid set, create the cache
			geoidcache[name] = {};
		}
		var unknownRecords = [];
		var recordnames = [];
		for( var i = 0; i < records.length; i++ ) {
			recordnames[i] = 'r_' + records[i].recordindex;
			if( typeof(geoidcache[name][recordnames[i]]) == 'undefined' ) {
				records[i].latitude = round( records[i].latitude, geoidgrd.latstepdp );
				records[i].longitude = round( records[i].longitude, geoidgrd.longstepdp );
				unknownRecords[unknownRecords.length] = records[i];
				geoidcache[name][recordnames[i]] = false;
			}
		}
		//fetch records if needed
		var samethread = true;
		function asyncCode(returnedRecords) {
			if( returnedRecords ) {
				GridRefUtils.prototype.cacheGeoidRecords( geoidgrd.name, returnedRecords );
			}
			var shifts = [];
			for( var j = 0; j < recordnames.length; j++ ) {
				shifts[shifts.length] = geoidcache[name][recordnames[j]];
			}
			if( returnCallback ) {
				if( samethread ) {
					//fetchRecords called asyncCode synchronously, or there was nothing to fetch - force it to become async
					setTimeout(function () { returnCallback(shifts); },0);
				} else {
					returnCallback(shifts);
				}
			} else {
				return shifts;
			}
		}
		if( returnCallback ) {
			if( unknownRecords.length ) {
				fetchRecords( geoidgrd.name, unknownRecords, asyncCode );
			} else {
				asyncCode();
			}
		} else {
			return asyncCode( unknownRecords.length ? fetchRecords( geoidgrd.name, unknownRecords ) : null );
		}
		samethread = false;
	}
	GridRefUtils.prototype.applyGeoid = function ( latitude, longitude, height, direction, geoidgrd, fetchRecords, returnType, denyBadCoords, returnCallback ) {
		var args = expandParams( latitude, 2, 3, [ longitude, height, direction, geoidgrd, fetchRecords, returnType, denyBadCoords, returnCallback ] );
		latitude = args[0]; longitude = args[1]; height = args[2]; direction = args[3]; geoidgrd = args[4]; fetchRecords = args[5]; returnType = args[6]; denyBadCoords = args[7]; returnCallback = args[8];
		if( returnCallback && typeof(returnCallback) != 'function' ) {
			return false;
		}
		if( !geoidgrd || !( geoidgrd.latspacing * geoidgrd.longspacing ) || geoidgrd.latspacing < 0 || geoidgrd.longspacing < 0 || geoidgrd.latmax < geoidgrd.latmin || geoidgrd.longmax < geoidgrd.longmin || typeof(fetchRecords) != 'function' ) {
			if( returnCallback ) {
				setTimeout( function () { returnCallback(false); }, 0 );
				return;
			} else {
				return false;
			}
		}
		longitude = wrapAroundLongitude(longitude);
		//OSI data has a rounding precision problem that causes it to miss its end point; 0.013333 is not the same as 5 / 375
		var latrange = geoidgrd.latmax - geoidgrd.latmin;
		var latspacing = latrange ? ( latrange / Math.round( latrange / geoidgrd.latspacing ) ) : geoidgrd.latspacing;
		var longrange = geoidgrd.longmax - geoidgrd.longmin;
		var longperrow = Math.round( longrange / geoidgrd.longspacing );
		var longspacing = longrange ? ( longrange / longperrow ) : geoidgrd.longspacing;
		longperrow++; //fencepost problem
		if( longitude < 0 && longitude < geoidgrd.longmin && geoidgrd.longmax > 180 ) {
			//geoid data may use -180 <= x <= 180, or it may use 0 <= x <= 360
			//this also allows them to use odd ranges like -10 to 200
			longitude += 360;
		}
		//if there is an error, this is what will be returned
		var errorValue = denyBadCoords ? false : ( returnType ? 'NaN' : Number.NaN );
		if( latitude < geoidgrd.latmin || latitude > geoidgrd.latmax || longitude < geoidgrd.longmin || longitude > geoidgrd.longmax ) {
			//the point is not within range, so this is not valid
			if( returnCallback ) {
				setTimeout( function () { returnCallback(errorValue); }, 0 );
				return;
			} else {
				return errorValue;
			}
		}
		//grid cell, latitude indexes are from the top, not the bottom
		var latIndex = Math.ceil( ( geoidgrd.latmax - latitude ) / latspacing );
		var longIndex = Math.floor( ( longitude - geoidgrd.longmin ) / longspacing );
		//lat-long of southwest corner
		var x0 = geoidgrd.longmin + longIndex * longspacing;
		var y0 = geoidgrd.latmax - latIndex * latspacing;
		//offset within grid
		var dx = longitude - x0;
		var dy = latitude - y0;
		var records = [];
		records[0] = { latitude: y0, longitude: x0, latindex: latIndex, longindex: longIndex, recordindex: longperrow * latIndex + longIndex };
		//allow points to sit on the last line by setting them to the same values as each other (otherwise it could never work on the north pole)
		if( dx ) {
			records[1] = { latitude: y0, longitude: ( longIndex + 1 ) * longspacing + geoidgrd.longmin, latindex: latIndex, longindex: longIndex + 1 };
			records[1].recordindex = longperrow * records[1].latindex + records[1].longindex;
		} else {
			records[1] = records[0];
		}
		if( dy ) {
			records[2] = { latitude: geoidgrd.latmax - ( latIndex - 1 ) * latspacing, longitude: records[1].longitude, latindex: latIndex - 1, longindex: dx ? ( longIndex + 1 ) : longIndex };
			records[3] = { latitude: records[2].latitude, longitude: records[0].longitude, latindex: latIndex - 1, longindex: longIndex };
			records[2].recordindex = longperrow * records[2].latindex + records[2].longindex;
			records[3].recordindex = longperrow * records[3].latindex + records[3].longindex;
		} else {
			records[2] = records[1];
			records[3] = records[0];
		}
		//fetch records if needed
		function asyncCode(shifts) {
			if( !isNumeric(shifts[0]) || !isNumeric(shifts[1]) || !isNumeric(shifts[2]) || !isNumeric(shifts[3]) ) {
				//this is within the grid, but data was not available
				if( returnCallback ) {
					returnCallback(errorValue);
					return;
				} else {
					return errorValue;
				}
			}
			//offset values
			var t = dx / longspacing;
			var u = dy / latspacing;
			//bilinear interpolated shifts
			var sg = ( ( 1 - t ) * ( 1 - u ) * shifts[0] ) + ( t * ( 1 - u ) * shifts[1] ) + ( t * u * shifts[2] ) + ( ( 1 - t ) * u * shifts[3] );
			height = direction ? ( height - sg ) : ( height + sg );
			//produce simple numbers instead of scientific notation
			var returnValue = returnType ? prettyNumber( height, 6 ) : height;
			if( returnCallback ) {
				returnCallback(returnValue);
			} else {
				return returnValue;
			}
		}
		if( returnCallback ) {
			getGeoidRecords( geoidgrd, records, fetchRecords, asyncCode );
		} else {
			return asyncCode( getGeoidRecords( geoidgrd, records, fetchRecords ) );
		}
	}

	//conversions between latitude/longitude and cartesian coordinates
	GridRefUtils.prototype.xyzToLatLong = function ( X, Y, Z, ellipsoid, returnType ) {
		var args = expandParams( X, 3, 3, [ Y, Z, ellipsoid, returnType ] );
		X = args[0]; Y = args[1]; Z = args[2]; ellipsoid = args[3]; returnType = args[4];
		//conversion according to "A guide to coordinate systems in Great Britain" Annexe B:
		//https://www.ordnancesurvey.co.uk/documents/resources/guide-coordinate-systems-great-britain.pdf
		if( !ellipsoid || typeof(ellipsoid) != 'object' || typeof(ellipsoid.a) == 'undefined' || typeof(ellipsoid.b) == 'undefined' ) {
			return false;
		}
		var e2 = 1 - Math.pow( ellipsoid.b, 2 ) / Math.pow( ellipsoid.a, 2 );
		var lon = Math.atan2( Y * 1, X * 1 );
		var p = Math.sqrt( Math.pow( X, 2 ) + Math.pow( Y, 2 ) );
		var lat = Math.atan2( Z, p * ( 1 - e2 ) );
		var loopcount = 0;
		var v, oldlat;
		do {
			v = ellipsoid.a / Math.sqrt( 1 - ( e2 * Math.pow( Math.sin(lat), 2 ) ) );
			//https://gssc.esa.int/navipedia/index.php/Ellipsoidal_and_Cartesian_Coordinates_Conversion alternative
			//height misses one iteration but the result is the same when diff is 0
			//height = ( p / Math.cos(lat) ) - v;
			//newlat = Math.atan2( Z, p * ( 1 - ( e2 * v / ( v + height ) ) ) );
			oldlat = lat;
			lat = Math.atan2( Z + ( e2 * v * Math.sin( lat ) ), p );
			//due to number precision, it is possible to get infinite loops here for extreme cases, when the difference is enough to roll over a digit then roll it back on the next iteration
			//in tests, upto 10 loops are needed for extremes - if it reaches 100, assume it must be infinite, and break out
		} while( Math.abs( lat - oldlat ) > 0 && ++loopcount < 100 );
		var height = ( p / Math.cos(lat) ) - v;
		return genericLatLongCoords( returnType, lat * 180 / Math.PI, lon * 180 / Math.PI, height );
	}
	GridRefUtils.prototype.latLongToXyz = function ( latitude, longitude, height, ellipsoid, returnType ) {
		var args = expandParams( latitude, 2, 3, [ longitude, height, ellipsoid, returnType ] );
		latitude = args[0]; longitude = args[1]; height = args[2]; ellipsoid = args[3]; returnType = args[4];
		if( !ellipsoid || typeof(ellipsoid) != 'object' || typeof(ellipsoid.a) == 'undefined' || typeof(ellipsoid.b) == 'undefined' ) {
			return false;
		}
		height = isNumeric(height) ? ( height * 1 ) : 0;
		latitude *= Math.PI / 180;
		longitude *= Math.PI / 180;
		var e2 = 1 - Math.pow( ellipsoid.b, 2 ) / Math.pow( ellipsoid.a, 2 );
		var v = ellipsoid.a / Math.sqrt( 1 - ( e2 * Math.pow( Math.sin(latitude), 2 ) ) );
		var X = ( v + height ) * Math.cos(latitude) * Math.cos(longitude);
		var Y = ( v + height ) * Math.cos(latitude) * Math.sin(longitude);
		var Z = ( ( ( 1 - e2 ) * v ) + height ) * Math.sin(latitude);
		if( returnType ) {
			return prettyNumber( X, 6 ) + ' ' + prettyNumber( Y, 6 ) + ' ' + prettyNumber( Z, 6 );
		} else {
			return [ X, Y, Z ];
		}
	}

})();
