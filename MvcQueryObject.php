<?php
namespace application\plugin\mvcQuery
{
	/**
	 * Instantiate me, fill me, and pass me off to the MvcQuery->query.
	 * @author Dean Rather
	 */
	class MvcQueryObject
	{
		const TYPE_SELECT = 'select';
		const TYPE_INSERT = 'insert';
		const TYPE_UPDATE = 'update';
		const TYPE_DELETE = 'delete';
		
		
		public function __construct($data=null)
		{
			if(is_object($data))
			{
				// Inject Myself
				foreach($data as $key => $val)
				{
					$this->$key = $val;
				}
			}
		}
		
		/**
		 * The type of the query
		 * Must be one of 'select', 'insert', 'update', or 'delete'.
		 * @var String
		 */
		private $type = null;
		
		public function setType($type)
		{
			$this->type = $type;
		}
		
		public function getType()
		{
			return $this->type;
		}
		
		
		/**
		 * The model to query.
		 * Must be a 'model' object.
		 * @var Model
		 */
		private $model = null;
		
		public function getModel()
		{
		    return $this->model;
		}
		
		public function setModel($model)
		{
		    $this->model = $model;
		}
		
		/**
		 * An optional key->val object representing the 'where' part of the query.
		 * keys are column names and vals are column values
		 * Any key beginning with an _underscore is treated as meta-data about the query
		 * See MvcQueryObjectData
		 * // TODO Rename to "data"
		 * @var object
		 */
		private $where = null;
		
		public function setWhere($where)
		{
			$this->where = $where;
		}
		
		public function addWhere($where)
		{
			$catenator = $this->getWhere() ? "\nAND" : "\nWHERE";
			$this->setWhere($this->getWhere() . $catenator . $where);
		}
		
		public function getWhere()
		{
			return $this->where;
		}
		
		
		private $data = null;
		
		public function setData($data)
		{
			$this->data = $data;
		}
		
		public function getData()
		{
			return $this->data;
		}
		
		
		
		/**
		 * Array of columns to read, if empty just reads *
		 * @var array
		 */
		private $readColumns = array();
		
		public function setReadColumns($readColumns)
		{
			$this->readColumns = $readColumns;
		}
		
		public function addreadColumns($readColumns)
		{
			$this->setReadColumns(array_merge($this->getReadColumns(), $readColumns));
		}
		
		public function getReadColumns()
		{
			return $this->readColumns;
		}
		
		
		/**
		 * Additional SQL to perform after the WHERE clause
		 */
		private $additionalPartSQL = '';
		
		public function setAdditionalPartSQL($additionalPartSQL)
		{
			$this->additionalPartSQL = $additionalPartSQL;
		}
		
		public function addAdditionalPartSQL($additionalPartSQL)
		{
			$this->setAdditionalPartSQL($this->getAdditionalPartSQL()."\n".$additionalPartSQL);
		}
		
		public function getAdditionalPartSQL()
		{
			return $this->additionalPartSQL;
		}
		
		
		/**
		 * Additional SQL to perform before the WHERE clause
		 */
		private $joinPartSQL = '';
		
		public function setJoinPartSQL($joinPartSQL)
		{
			$this->joinPartSQL = $joinPartSQL;
		}
		
		public function addJoinPartSQL($joinPartSQL)
		{
			$this->setJoinPartSQL($this->getJoinPartSQL()."\n".$joinPartSQL);
		}
		
		public function getJoinPartSQL()
		{
			return $this->joinPartSQL;
		}
		
		
		/**
		 * If true, then the 'columns' in readColumns are not surrounded by `'s
		 */
		private $readColumnsRaw = false;
		
		public function setReadColumnsRaw($readColumnsRaw)
		{
			$this->readColumnsRaw = $readColumnsRaw;
		}
		
		public function getReadColumnsRaw()
		{
			return $this->readColumnsRaw;
		}
		
		
		/**
		 * Additional SQL to perform after the WHERE clause.
		 * Either "WHERE" or "AND" will be appended to this
		 * Todo: remove
		 */
		private $additionalWhereSQL = '';
		
		public function getAdditionalWhereSQL()
		{
		    return $this->additionalWhereSQL;
		}
		
		public function setAdditionalWhereSQL($additionalWhereSQL)
		{
		    $this->additionalWhereSQL = $additionalWhereSQL;
		}
		
	}
}
