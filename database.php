<?php
/**
 * Interfaz con la Base de Datos MS SQL SERVER
 * Utiliza el Driver de Microsoft SQL Server para PHP en Windows : http://php.net/manual/es/book.sqlsrv.php
 * 
 * @author Jorge David González Paule <jdavidpaule@gmail.com>
 */

class Database {
	var $conn;
	var $host;
	var $user;
	var $pass;
	var $db;
	var $serverName;
	
	function __construct($host, $user, $pass, $db, $serverName) {
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->db = $db;
		$this->serverName = $serverName;
		$this->conn = $this->connect($host, $user, $pass, $db, $serverName);
	}

	/**
	 * Funcion que conecta a la BBDD especificada. Si falla muestra
	 * @param string $host Dirección IP o Nombre del HOST del servidor de BBDD
	 * @param string $user Nombre de usuario.
	 * @param string $pass Password.
	 * @param string $db Nombre de la Base de Datos
	 * @param string $serverName El nombre del servidor en el que se ha establecido una conexión. Para conectar a una instancia específica,
	 *  poner una barra invertida después del nombre de servidor e indicar el nombre de la instancia (e.g. NombreServidor\sqlexpress).
	 * @return array|$conn Objeto de conexión a BBDD, si falla, se devuelve un array con información sobre el error, ver @link http://www.php.net/manual/es/function.sqlsrv-errors.php sqlsrv_errors()  
	 */
	function connect ($host, $user, $pass, $db, $serverName) {
		$connectionInfo = array( "Database"=>$db, "UID"=>$user, "PWD"=>$pass, "CharacterSet" => "UTF-8");
		$conn = sqlsrv_connect( $serverName, $connectionInfo);
	
		if($conn)
			 return $conn;
		else
			 return sqlsrv_errors();
	}
	
	/**
	 * Consulta DELETE SQL. Borra las t-uplas de una tabla que cumplan las condiciones WHERE.
	 * @param string $table Tabla de la Base de Datos.
	 * @param string $where Código SQL con las condiciones 'sin el WHERE'
	 * @return bool|stmt Recurso de consulta o FALSE si ocurre algún error. Ver @link http://www.php.net/manual/es/function.sqlsrv-query.php sqlsrv_query
	*/	
	function delete ($table, $where) {
		$sql = 'DELETE FROM '.$table.' WHERE '.$where;
		$stmt = @sqlsrv_query ($this->conn, $sql);
		
		if (! $stmt) {
			return sqlsrv_errors();
		}
		
		return $stmt;		
	}

	/**
	 * Consulta UPDATE SQL. Actualiza la columna de las t-uplas de una tabla que cumplan las condiciones WHERE.
	 * @param string $table Tabla de la Base de Datos.
	 * @param string $column Columna de la tabla a actualizar.
	 * @param string $value Nuevo valor de la columna.
	 * @param string $where Código SQL con las condiciones 'sin el WHERE'
	 * @return bool|stmt Recurso de consulta o false si falla. Ver @link http://www.php.net/manual/es/function.sqlsrv-query.php sqlsrv_query
	 */	
	function update ($table, $column, $value, $where) {
		$query = 'UPDATE '.$table.' SET '.$column.' = '.addslashes($value);
		
		if ($where != '')
			$query .= ' WHERE '.$where.';';
		else
			$query .= ';';
		
		$stmt = @sqlsrv_query ($this->conn, $query);
		
		if (!$stmt) {
			return sqlsrv_errors();
		}
		
		return $stmt;		
		
	} 
	
	/**
	 * Consulta SELECT SQL.
	 * @param string $columns Columnas de la tabla a devolver.
	 * @param string $table Tabla de la Base de Datos a consultar.
	 * @param string $where Código SQL con las condiciones 'con el WHERE'
	 * @return bool|stmt Recurso de consulta o false si falla. Ver @link http://www.php.net/manual/es/function.sqlsrv-query.php sqlsrv_query
	 */	
	function select($columns, $table, $where){
		$query = 'SELECT '.$columns.' FROM '.$table.' '.$where;
		$params = array();
		$options =  array("Scrollable"=>SQLSRV_CURSOR_STATIC);
		
		if (!$this->conn) {
			$this->conn = $this->connect($this->host, $this->user, $this->pass, $this->db, $this->serverName);
		}		
		
		$stmt = @sqlsrv_query ($this->conn, $query);
		
		if (!$stmt) {
			return sqlsrv_errors();
		}
		
		return $stmt;
	}
	
	/**
	 * Consulta INSERT SQL. En caso de error escribe en un archivo log. @see db_log($msg).
	 * @param string $table Tabla de la Base de Datos a consultar.
	 * @param array $values Array asociativo con los valores a insertar en la forma: nombre_columna => nuevo_valor
	 * @return bool|stmt|error Recurso de consulta o false si falla o la tabla no existe, Error SQL si falla la consulta. Ver @link http://www.php.net/manual/es/function.sqlsrv-query.php sqlsrv_query
	 */	
	function insert($table, $values) {

		if (!$this->table_exists($table)) {
			return false;
		}
		
		$columns = ' (';
		$val = '(';
		$total = sizeof($values)-1;
		$i = 0;		
		foreach ($values as $name=>$value) {
			if ($i < $total) {			
				$columns .= $name.',';
				$val .= $value.',';
			} else {
				$columns .= $name.')';
				$val .= $value.')';
			}
			$i++;
		}
		$sql = 'INSERT INTO '.$table.$columns.' values '.$val;

		$stmt = $this->raw($sql);
		
		return $stmt;
	}
	
	/**
	 * Consulta en crudo SQL. En caso de error escribe en un archivo log. @see db_log($msg).
	 * @param string $query Código SQL a ejecutar
	 * @return bool|stmt Recurso de consulta o false si falla o la tabla no existe. Ver @link http://www.php.net/manual/es/function.sqlsrv-query.php sqlsrv_query
	 */	
	function raw($query){
		if (!$this->conn) {
			$this->conn = $this->connect($this->host, $this->user, $this->pass, $this->db, $this->serverName);
		}
		$stmt = @sqlsrv_query ($this->conn, $query);
		if (!$stmt) {
			return sqlsrv_errors();
		}
		return $stmt;
	}	
	
	/**
	 * Comprueba si la consulta ha devuelto alguna fila como resultado.
	 * @param stmt $stmt Recurso devuelto por una consulta exitosa. @see raw($query) @see insert($table, $values) @see select($columns, $table, $where)
	 * @see update ($table, $column, $value, $where) @see delete ($table, $where)
	 * @return bool True si hay filas en la consulta. Ver @link http://www.php.net/manual/es/function.sqlsrv-has-rows.php sqlsrv_has_rows
	 */	
	function has_rows($stmt) {
		return @sqlsrv_has_rows($stmt);
	}
	
	/**
	 * Libera un recurso devuelto por una consulta exitosa. @see raw($query) @see insert($table, $values) @see select($columns, $table, $where)
	 * @see update ($table, $column, $value, $where) @see delete ($table, $where)
	 * @param stmt $stmt Recurso devuelto por una consulta exitosa.
	 */	
	function free_stmt($stmt){
		@sqlsrv_free_stmt($stmt);
	}
	
	/**
	 * Devuelve la siguiente fila de la consulta en un array
	 * @param stmt $stmt Recurso devuelto por una consulta exitosa. @see raw($query) @see insert($table, $values) @see select($columns, $table, $where)
	 * @see update ($table, $column, $value, $where) @see delete ($table, $where)
	 * @return array|bool|NULL Fila de la BBDD, NULL si no hay filas, False si ocurre un error. Ver @link http://www.php.net/manual/es/function.sqlsrv-fetch-array.php sqlsrv_fetch_array
	 */	
	function fetch_array($stmt) {
		return @sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
	}
	
	/**
	 * Escribe un mensaje de error en el archivo de los 'db_log.txt' junto a la fecha actual.
	 * @param string $msg Mensaje a escribir.
	 */	
	function db_log($msg){
		/*
		$f = fopen ("db_log.txt", "a+");
		fwrite($f, "[".date("d/m/Y H:i:s")."] - ".$msg);
		fclose($f);
		*/
	}
	
	/**
	 * Cierrra la conexión con el servidor de BBDD.
	 * @return Devuelve TRUE en caso de éxito o FALSE en caso de error. Ver @link http://www.php.net/manual/es/function.sqlsrv-close.php sqlsrv_close
	 */	
	function close() {
		return @sqlsrv_close( $this->conn );
	}

	
	/**
	 * Devuelve la cantidad de filas de una consulta.
	 * @param stmt $stmt Recurso devuelto por una consulta exitosa. @see raw($query) @see insert($table, $values) @see select($columns, $table, $where)
	 * @return int|bool Número de filas o FALSE si ocurre un error. Ver @link http://www.php.net/manual/es/function.sqlsrv-num-rows.php
	 */	
	function num_rows ($stmt) {
		return @sqlsrv_num_rows($stmt);
	}
	
	
	/**
	 * Comprueba si una tabla existe en la Base de Datos.
	 * @param string $table Nombre de la tabla.
	 * @return True si existe la tabla, False si no existe.
	 */	
	function table_exists($table) {
		$stmt = $this->raw("SELECT OBJECT_ID('".$table."', 'U')");
		$result = $this->fetch_array($stmt);
		$this->free_stmt($stmt);
		
		if ($result[""])
			return true;
		else
			return false;
	}

	/**
	 * Crear una nueva tabla en la Base de Datos.
	 * @param string $name Nombre de la tabla.
	 * @return bool|stmt|errors False si ya existía la tabla o hay errores. Recurso de consulta si se creó exitosamente.
	 */	
	function create_table($name, $columns) {
		
		if ($this->table_exists($name))
			return false;

		$sql = 'CREATE TABLE '.$name.' ( ';
		$total = sizeof($columns)-1;
		$i = 0;
		foreach ($columns as $nombre=>$tipo) {
			if ($i < $total)
				$sql .= $nombre.' '.$tipo.', ';
			else
				$sql .= $nombre.' '.$tipo;
			$i++;
		}
		$sql .= ' )';
		
		$stmt = $this->raw($sql);

		return $stmt;
	}
}


?>