<?php
namespace application\plugin\mvcQuery
{
	/**
	 * Instantiate me, fill me, and pass me off to the QueryController.
	 * @author Dean Rather
	 */
	class MvcQueryObject
	{
		const TYPE_SELECT = 'select';
		const TYPE_INSERT = 'insert';
		const TYPE_UPDATE = 'update';
		const TYPE_DELETE = 'delete';
		
		/**
		 * The type of the query
		 * Must be one of 'select', 'insert', 'update', or 'delete'.
		 * @var String
		 */
		private $type	= null;
		
		/**
		 * The name of the table to query.
		 * Must represent a 'model' object.
		 * @var String
		 */
		private $table	= null;
		
		/**
		 * An optional key->val object representing the 'where' part of the query.
		 * keys are column names and vals are column values
		 * Any key beginning with an _underscore is treated as meta-data about the query
		 * Available meta-data are:
		 * _start
		 * _limit
		 * _order
		 * @var object
		 */
		private $where	= null;
		
		/**
		 * If true, use LIKE comparitor instead of = comparitor
		 * @var boolean
		 */
		private $loose = false;
		
		/**
		 * Array of columns to read, if empty just reads *
		 * @var array
		 */
		private $readColumns = array();
		
		/**
		 * Additional SQL to perform after the WHERE clause
		 */
		private $additionalPartSQL = '';
		
		/**
		 * If true, then the 'columns' in readColumns are not surrounded by `'s
		 */
		private $readColumnsRaw = false;
		
		
		
		public function setType($type)
		{
			$this->type = $type;
		}
		
		public function setTable($table)
		{
			$this->table = $table;
		}
		
		public function setWhere($where)
		{
			$this->where = $where;
		}
		
		public function setLoose($loose)
		{
			$this->loose = $loose;
		}
		
		public function setReadColumns($readColumns)
		{
			$this->readColumns = $readColumns;
		}
		
		public function setAdditionalPartSQL($additionalPartSQL)
		{
			$this->additionalPartSQL = $additionalPartSQL;
		}
		
		public function setReadColumnsRaw($readColumnsRaw)
		{
			$this->readColumnsRaw = $readColumnsRaw;
		}
		
		
		public function getType()
		{
			return $this->type;
		}
		
		public function getTable()
		{
			return $this->table;
		}
		
		public function getWhere()
		{
			return $this->where;
		}
		
		public function isLoose()
		{
			return $this->loose;
		}
		
		public function getReadColumns()
		{
			return $this->readColumns;
		}
		
		public function getAdditionalPartSQL()
		{
			return $this->additionalPartSQL;
		}
		
		public function getReadColumnsRaw()
		{
			return $this->readColumnsRaw;
		}
	}
}