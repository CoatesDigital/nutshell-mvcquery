<?php
namespace application\plugin\mvcQuery
{
	use application\plugin\mvcQuery\MvcQueryException;
	use application\plugin\mvcQuery\MvcQueryObject;
	use application\plugin\mvcQuery\handler\MySQL;
	use application\plugin\mvcQuery\handler\SQLite;
	use nutshell\plugin\mvc\Model;
	use nutshell\Nutshell;
	use nutshell\core\exception\NutshellException;
	
	/**
	 * Provides functions to interface with the database
	 * @author Dean Rather
	 */
	class MvcQuery extends Model
	{
		
		/*************
		 * Constants *
		 *************/
		
		const MVC_QUERY_PLACE_HOLDER	= '_mvcquery_place_holder';
		const MVC_QUERY_VALUE			= '_mvcquery_value';
		const INSERT_DEFAULT			= 1;
		const INSERT_ASSOC				= 2;
		
		
		
		
		
		/*********************
		 * Public Attributes *
		 *********************/
		
		public $dbName		=null;	  //dbName
		public $name	 	=null;     // table name
		public $primary	   	=array();  // array with primary keys.
		public $primary_ai 	=true;     // is the pk auto increment? Only works if count($primary) == 1
		public $columns	   	=array();  // array with columns
		public $autoCreate 	=true;     // should create the table if it doesn't exist?
		public $insertType	=self::INSERT_DEFAULT;
		
		public $types		=array();
		public $columnNames	=array(); // stores column names
		
		// the following to 4 properties are intended to speed up query building.
		public $columnNamesListStr        ='';       // stores column names list separated by ','.
		public $defaultInsertColumns      =array(); // when the primary key is auto increment, the primary key isn't present as an insert column
		public $defaultInsertColumnsStr   ='';
		public $defaultInsertPlaceHolders ='';       // part of an insert statement.		
		
		
		/**
		 * Redeclare the formerly 'protected' db as public,
		 * so that when we pass ourself to a handler they can use it
		 */ 
		public $db = null;
		
		
		
		
		
		
		/**********************
		 * Private Properties *
		 **********************/
		
		/**
		 * This is one of the handlers from my handlers folder.
		 * It does the query.
		 * It is set in config / mvc / connection, who points to one of config / plugin / Db / connections.
		 * There the 'connection' config will include a 'handler', which will be used to determine which handler I load.
		 * @var MySQL|SQLite
		 */
		private $handler = null;
		


		
		
		
		
		/*******************
		 * The constructor *
		 *******************/
		
		/**
		 * Parent constructor sets up the DB connection, then we choose which Handler to use for generating the actual query.
		 * The handler will set it's 'model' value to this model, so that it has access to the DB connection and so that
		 * the functions here can handle the request
		 */
		public function __construct()
		{
			parent::__construct();
			$config			= Nutshell::getInstance()->config;
			$connectionName	= $config->plugin->Mvc->connection;
			$handlerName	= $config->plugin->Db->connections->$connectionName->handler;
			
			if(strtolower($handlerName) == 'mysql')
			{
				$this->handler = new MySQL($this);
			}
			elseif(strtolower($handlerName) == 'sqlite')
			{
				$this->handler = new SQLite($this);
			}
			else
			{
				throw new MvcQueryException(MvcQueryException::INVALID_HANDLER, $connectionName, $handlerName);
			}
		}
		
		
		
		
		
		
		/********************
		 * The big function *
		 ********************/
		
		
		/**
		 * Pass me a object representing a Query.
		 * It must have a 'table' value.
		 * It must have a 'type' value which is one of: 'select' 'update' 'delete'.
		 * Optionally, it may have any of: 'where', etc.
		 * @throws MvcQueryException
		 */
		public function query(MvcQueryObject $queryObject)
		{
			$this->checkQueryData($queryObject);
			$model = $queryObject->getModel();
			
			
			// Parse the 'where' part to get the 'where' and 'additionalPartSQL' arguments
			$vals = array();
			$keys = array();
			$where = array();
			$aggregate = false;
			$aggregateVal = false;
			$debug = false;
			$additionalPartSQL = $queryObject->getAdditionalPartSQL();
			$data = $queryObject->getWhere();
			if(!$data) $data = array();
			$limit=array('offset'=>null,'limit'=>null);
			$sort=array('by'=>1,'dir'=>null);
			foreach($data as $key => $val)
			{
				if($key[0] == '_') // It's some meta data
				{
					if($key == "_offset" && is_numeric($val))
					{
						$limit['offset']=$val;
					}
					
					if($key == "_limit" && is_numeric($val))
					{
						$limit['limit']=$val;
					}
					
					if($key == "_sortBy" && is_string($val))
					{
						$sort['by']=str_replace("'", "`", $this->db->quote($val));
					}
					if($key == "_sortBy" && is_array($val))
					{
						$sort['by'] = array();
						foreach($val as $col)
						{
							if($col) $sort['by'][] = str_replace("'", "`", $this->db->quote($col));
						}
						$sort['by'] = implode(', ', $sort['by']);
						if(!$sort['by']) $sort['by']=1;
					}
					
					if($key == "_sortDir" && is_string($val))
					{
						$sort['dir']=str_replace("'", "", $this->db->quote($val));
					}
					
					if($key == "_count" && $val)
					{
						$aggregate = 'count';
					}
					
					if($key == "_min" && $val)
					{
						$aggregate = 'min';
						$aggregateVal = $val;
					}
					
					if($key == "_max" && $val)
					{
						$aggregate = 'max';
						$aggregateVal = $val;
					}
					
					if($key == "_avg" && $val)
					{
						$aggregate = 'avg';
						$aggregateVal = $val;
					}
					
					if($key == '_debug' && $val)
					{
						$debug = true;
					}
					
				}
				else
				{
					$keys[] = $key;
					$vals[] = $val;
					$where[$key] = $val;
				}
			}
			
			
			if (!is_null($sort['by']))
			{
				if (!is_null($sort['dir']))
				{
					$additionalPartSQL.=' ORDER BY '.$sort['by'].' '.$sort['dir'];
				}
				else
				{
					$additionalPartSQL.=' ORDER BY '.$sort['by'];
				}
			}
			
			if (!is_null($limit['limit']))
			{
				if (!is_null($limit['offset']))
				{
					$additionalPartSQL.=' LIMIT '.$limit['offset'].','.$limit['limit'];
				}
				else
				{
					$additionalPartSQL.=' LIMIT '.$limit['limit'];
				}
			}
			
			// prepare the readColumns argument
			if($aggregate)
			{
				$readColumns = array();
				switch($aggregate)
				{
					case 'count':
						$readColumns[] = "COUNT(1) as '_count'";
						break;
					case 'min':
						$readColumns[] = "MIN({$aggregateVal}) as '_min'";
						break;
					case 'max':
						$readColumns[] = "MAX({$aggregateVal}) as '_max'";
						break;
					case 'avg':
						$readColumns[] = "AVG({$aggregateVal}) as '_avg'";
						break;
				}
			}
			else
			{
				$readColumns = $queryObject->getReadColumns();
			}
			
			if($queryObject->getType() == 'select')
			{
				$return = $model->read($where, $readColumns, $additionalPartSQL, $queryObject);
			}
			elseif($queryObject->getType() == 'insert')
			{
				switch($model->insertType)
				{
					case self::INSERT_ASSOC:
						$return = $model->insertAssoc($where);
					break;

					case self::INSERT_DEFAULT:
						// fall through
					default:
						$return = $model->insert($vals, $keys);
					break;
				}
			}
			elseif($queryObject->getType() == 'update')
			{
				$return = $model->update($where, array('id' => $where['id']));
			}
			elseif($queryObject->getType() == 'delete')
			{
				$return = $model->delete($where, $queryObject);
			}
			else
			{
				throw new MvcQueryException(MvcQueryException::INVALID_TYPE, " [".$queryObject->getType()."] is invalid.");
			}
			
			if($debug && Nutshell::getInstance()->config->application->mode=='development')
			{
				$return = array($this->db->getLastQueryObject());
			}
			
			return $return;
		}
		
		
		
		
		
		/**********************************
		 * Handler Pass-through functions *
		 **********************************/
		
		
		public function update($updateKeyVals,$whereKeyVals, $additionalPartSQL='')
		{
			return $this->handler->update($updateKeyVals,$whereKeyVals, $additionalPartSQL);
		}
	
		public function read($whereKeyVals = array(), $readColumns = array(), $additionalPartSQL='', $mvcQueryObject=null)
		{
			return $this->handler->read($whereKeyVals, $readColumns, $additionalPartSQL, $mvcQueryObject);
		}
		
		public function insert($record, $fields=array())
		{
			return $this->handler->insert($record, $fields);
		}

		public function insertAssoc($record)
		{
			return $this->handler->insertAssoc($record);
		}
		
		public function delete($whereKeyVals, $mvcQueryObject=null)
		{
			return $this->handler->delete($whereKeyVals, $mvcQueryObject);
		}
		
		
		
		/*********************
		 * Utility Functions *
		 *********************/
		
		public function getTableName()
		{
			$tableName = $this->name;
			return $tableName;
		}
		
		public function getModel($modelName)
		{
			$parts = explode('/', $modelName);
			$model = $this->model;
			foreach($parts as $part)
			{
				$model = $model->$part;
			}
			
			return $model;
		}
		
		public function tableExists($tableName)
		{
			$dbName	= $this->getDBName();
			$query	= "SHOW TABLES FROM {$dbName} LIKE '{$tableName}'";
			$result	= $this->db->getResultFromQuery($query);
			return (sizeof($result));
		}
		
		public function getDBName()
		{
			$config = Nutshell::getInstance()->config;
			$connectionName = $config->plugin->Mvc->connection;
			return $config->plugin->Db->connections->{$connectionName}->database;
		}
		
		public function showCreateTable()
		{
			// todo, check that this handler can do that!
			return $this->handler->showCreateTable();
		}
		
		private function checkQueryData($queryObject)
		{
			if(!$queryObject->getModel()) throw new MvcQueryException(MvcQueryException::NEEDS_TABLE);
			if(!$queryObject->getType()) throw new MvcQueryException(MvcQueryException::NEEDS_TYPE);
		}

		public function toArray()
		{
			$array = array();
			foreach($this->columnNames as $columnName)
			{
				$array[$columnName] = $this->$columnName;
			}
			return $array;
		}

		public function save()
		{
			$record = $this->toArray();
			$existing = $this->read(array('id'=>$record['id']));
			if(sizeof($existing))
			{
				$existing = $existing[0];
				$where = array('id'=>$existing['id']);
				$this->update($record, $where);
			}
			else
			{
				$id = $this->insertAssoc($record);
				$this->id = $id;
			}
		}
		
		public static function executeSQLDump($file)
		{
			$config = Nutshell::getInstance()->config;
			$dbConfig = $config->plugin->Db->connections->{$config->plugin->Mvc->connection};
			$passwordSegment = $dbConfig->password == '' ? '' : '-p' . $dbConfig->password;

			$command = sprintf
			(
				"mysql -u %s %s %s < \"%s\"",
				$dbConfig->username,
				$passwordSegment,
				$dbConfig->database,
				$file
			);
			
			
			exec($command, $output, $return);
			if($return !== 0)
			{
				throw new NutshellException('Executing command failed', "command:",$command, "output:",$output, "return:",$return);
			}
		}
	}
}
