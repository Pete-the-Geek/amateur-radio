<?
require('passwords.php');
//$handler = @mysql_connect( $host, $username, $password );
$handler = @mysqli_connect($host, $username, $password, $database, ini_get('mysqli.default_port'), '/run/mysqld/mysqld10.sock');

/* check connection */
if (mysqli_connect_errno()) {
		printf("Connect failed: %s\n", mysqli_connect_error());
		require('functions.php');
		print "<h1>Could not connect to database</h1>";
		exit;
	};

if (!mysqli_query( $handler , "SELECT NOW()" )) {
		require('functions.php');
		print "<h1>Could not access database</h1>";
		exit;
	};

function sqlfunct_query( $query_string ) {
	global $handler;
	$result = @mysqli_query( $handler, $query_string	);
	return( $result );
}

function sqlfunct_get_num_rows( $result ) {
	$num_rows = @mysqli_num_rows( $result );
	return( $num_rows );
}

function sqlfunct_fetch_array( $result ) {
	$row_array = @mysqli_fetch_array( $result );
	return( $row_array );
}

function sqlfunct_free_result( $results ) {
	@mysqli_free_result( $results );
}

function sqlfunct_close() {
	global $handler;
	@mysqli_close( $handler );
}

function sqlfunct_result( $results, $rownum, $col ) {
	$answer = @mysqli_result( $results, $rownum, $col );
	return( $answer );
}

function sqlfunct_set_read_row( $result, $rownum ) {
	@mysqli_data_seek( $result, $rownum );
}

function sqlfunct_get_pri_key() {
	global $handler;
	$answer = @mysqli_insert_id( $handler );
	return( $answer );
}

function sqlfunct_error() {
	global $handler;
	$answer = @mysqli_error( $handler );
	return( $answer );
}

function sqlfunct_errno() {
	global $handler;
	$answer = @mysqli_errno( $handler );
	return( $answer );
}

function sqlfunc_escape_string($results) {
	global $handler;
	$answer = @mysqli_real_escape_string ( $handler , $results );
	return( $answer );
}

function sqlfunct_affected_rows(){
	global $handler;
	$answer = @mysqli_affected_rows( $handler );
	return ($answer);
}
?>