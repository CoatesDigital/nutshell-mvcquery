<?php
namespace application\plugin\mvcQuery\handler
{
	use application\plugin\mvcQuery\MvcQueryException;
	
	/**
	 * @author Timothy Chandler <tim.chandler@spinifexgroup.com>
	 * @package nutshell-plugin
	 */
	class SQLite
	{
		
		protected $model = null;
		public function __construct($model)
		{
			$this->model = $model;
			
			$this->configure();				
			
			if (!empty($this->model->name) && (count($this->model->primary)>0) && !empty($this->model->columns))
			{
				if (!isset($this->model->db))
				{
					throw new MvcQueryException(MvcQueryException::CONNECTION_NOT_DEFINED);
				}
			}
			else
			{
				// TODO How should this check function?
				// throw new MvcQueryException
				// (
				// 	MvcQueryException::MODEL_MISCONFIGURED,
				// 	$this->model->name,
				// 	$this->model->primary,
				// 	$this->model->columns
				// );
			}
		}
		
		protected function configure() {
			if (!is_array($this->model->primary)){
				throw new Exception('Primary Key has to be an array.');
			}
			
			$this->model->columnNames = array_keys($this->model->columns);
			$this->model->columnNamesListStr = '`'. implode('`,`', $this->model->columnNames) . '`';
				
			// doesn't make much sense inserting an auto increment column using default settings.
			if (($this->model->primary_ai) && (count($this->model->primary)==1))
			{
				$this->model->defaultInsertColumns = array_diff($this->model->columnNames, $this->model->primary);
			} else {
				$this->model->defaultInsertColumns = &$this->model->columnNames;
			}
				
			$this->model->defaultInsertColumnsStr = '`' . implode('`,`',$this->model->defaultInsertColumns) . '`';
			$this->model->defaultInsertPlaceHolders = rtrim(str_repeat('?,',count($this->model->defaultInsertColumns)),',');
		}
		
		
		
		
		
		/**
		 * Inserts a record into the database.
		 * When $fields are defined, this is the list of fields to be used when inserting.
		 * This function returns the assigned PK when using auto increment.
		 * @param array $record
		 * @param array $fields
		 * @return int
		 */
		public function insert(Array $record, Array $fields=array())
		{
			// all fields (default) ?
			if (count($fields)==0)
			{
				// this part of the code is intended to be fast.
				$placeholders	=&$this->model->defaultInsertPlaceHolders;
				$keys			=&$this->model->defaultInsertColumnsStr;
			} else {
				$placeholders = rtrim(str_repeat('?,',count($record)),',');
				$keys         = '`' . implode('`,`',$fields) . '`';
			}
			
			$dbPrefix = $this->getDbPrefix();
			$query=
<<<SQL
INSERT INTO {$dbPrefix}`{$this->model->name}`
	({$keys})
VALUES
	({$placeholders});
SQL;
			return $this->model->db->insert($query,$record);
		}
		
		/**
		 * This function inserts a record such as ( 'key' => 'value', 'key2' => 'value2', ... );
		 * This function returns the assigned PK when using auto increment.
		 * @param array $record
		 * @return int
		 */
		public function insertAssoc(Array $record)
		{
			return $this->model->insert(array_values($record), array_keys($record));
		}
		
		/**
		 * Reads rows from the database.
		 * If $whereKeyVals isn't given, reads all rows.
		 * If $readColumns isn't given, read all columns.
		 * @param array  $whereKeyVals
		 * @param array  $readColumns
		 * @param string $additionalPartSQL
		 * @return Array
		 */
		public function read($whereKeyVals = array(), $readColumns = array(), $additionalPartSQL='', $options=null)
		{
			// is there a "where"?
			$whereKeySQL = '';
			$whereKeyValues = array();
			if (count($whereKeyVals)>0)
			{
				// filters by the given where
				$this->getWhereSQL($whereKeyVals, $whereKeySQL, $whereKeyValues);
				$whereKeySQL = " WHERE ".$whereKeySQL;
			} 
			
			// Is a joinPartSQL defined?
			$joinPartSQL = '';
			if(is_object($options))
			{
				$joinPartSQL = $options->getJoinPartSQL();
			}
			
			// are columns to be read defined?
			if (count($readColumns)>0) 
			{
				// reads only selected columns
				
				// Surround them in `ticks`?
				$readColumnsRaw = (is_object($options));
				
				if(is_object($options) && $options->getReadColumnsRaw())
				{
					$columnsSQL = implode(",\n\t", $readColumns);
				}
				else
				{
					$columnsSQL = '`' . implode("`,\n\t`", $readColumns) . '`';
				}
			} else {
				// reads all columns
				$columnsSQL = $this->model->columnNamesListStr;
			}
			
			$dbPrefix = $this->getDbPrefix();
			
			$query = 
<<<SQL
SELECT
	{$columnsSQL}
FROM
	{$dbPrefix}`{$this->model->name}`
	{$joinPartSQL}
	{$whereKeySQL}
	{$additionalPartSQL}
SQL;
			return $this->model->db->getResultFromQuery($query,$whereKeyValues);
		}
		
		/**
		 * Return a string serving as a prefix for the table name in the query. If the dbName property in the class is set to null or an empty
		 * value, then no prefix will be generated and the query will run on the currently selected database.
		 * @return string
		 */
		protected function getDbPrefix() {
			return $this->model->dbName ? '`' . $this->model->dbName . '`.' : '';
		}

		/**
		 * This method creates the where part of a query. It returns the sql string ($whereKeySQL) and their values ($whereKeyValues).
		 * @param array $whereKeyVals
		 * @param string $whereKeySQL
		 * @param array $whereKeyValues
		 * @return string
		 */
		protected function getWhereSQL($whereKeyVals, &$whereKeySQL, &$whereKeyValues)
		{
			$whereKeySQL = array();
			$whereKeyValues = array();
			
			// Quick update is possible if $whereKeyVals is numeric and PK is composed by only one column.
			if (is_numeric($whereKeyVals) && (count($this->model->primary)==1))
			{
				$whereKeySQL = " `{$this->model->primary[0]}` = ? ";
				$whereKeyValues[] = (int) $whereKeyVals;
			}
			else if (is_array($whereKeyVals)) //More specific keyval matching.
			{
				$where = array();
				foreach ($whereKeyVals as $key=>$value)
				{
					// If the val is an array, assume that the key is a comparator,
					// and the value is the actual value
					$comparator = '=';
					if(is_array($value))
					{
						reset($value);
						$comparator = key($value);
						$value = current($value);
					}
					
					switch($comparator) {
						case MvcQueryObjectData::IN:
							if(!is_array($value)) {
								$value = array($value);
							}
							$where[] = "`{$key}` {$comparator} (" . implode(',', array_fill(0, count($value), '?')) . ")";
							$whereKeyValues = array_merge($whereKeyValues, $value);
						break;

						default:
							$where[] = "`{$key}` {$comparator} ?";
							$whereKeyValues[] = $value;
						break;
					}
				}
				$whereKeySQL = implode(' AND ', $where);
			}
			else
			{
		 		throw new Exception('$whereKeyVals is invalid. Specify an array of key value pairs or a single numeric for primay key match.');
			}
		}
		
		public function update($updateKeyVals, $whereKeyVals, $additionalPartSQL='')
		{
			//Create the set portion of the query.
			$set=array();
			foreach (array_keys($updateKeyVals) as $key)
			{
				$set[] = '`' . $key . '` = ?';
			}
			$set=implode(',',$set);

			$whereKeySQL = '';
			$whereKeyValues = array();
			$this->getWhereSQL($whereKeyVals, $whereKeySQL, $whereKeyValues);
	
			$dbPrefix = $this->getDbPrefix();
			$query =
<<<SQL
UPDATE
	{$dbPrefix}`{$this->model->name}`
SET
	{$set}
WHERE
	{$whereKeySQL}
	{$additionalPartSQL}
;
SQL;
			return $this->model->db->update($query,array_merge(array_values($updateKeyVals),$whereKeyValues));
		}
		
		/**
		 * Deletes records filtering by $whereKeyVals.
		 * @param mixed $whereKeyVals
		 */
		public function delete($whereKeyVals, $options=array())
		{
			$whereKeySQL = '';
			$whereKeyValues = array();
			$this->getWhereSQL($whereKeyVals, $whereKeySQL, $whereKeyValues, $options);
			$dbPrefix = $this->getDbPrefix();
			$query=" DELETE FROM {$dbPrefix}`{$this->model->name}` WHERE {$whereKeySQL} ";
			 
			return $this->model->db->delete($query,$whereKeyValues);
		}
	
		/**
		 * This function returns TRUE if $field_name is a primary key.
 		 * @param string $field_name
		 * @return bool
		 */
		public function isPrimaryKey($field_name)
		{
			return in_array($field_name, $this->model->primary);
		}
	}
}
?>