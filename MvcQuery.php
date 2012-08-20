<?php
namespace application\plugin\mvcQuery
{
	use application\plugin\mvcQuery\MvcQueryException;
	use application\plugin\mvcQuery\MvcQueryObject;
	use application\plugin\mvcQuery\handler\MySQL;
	use application\plugin\mvcQuery\handler\SQLite;
	use nutshell\plugin\mvc\Model;
	use nutshell\Nutshell;
	
	/**
	 * Provides functions to interface with the database
	 * @author Dean Rather
	 */
	class MvcQuery extends Model
	{

		const MVC_QUERY_PLACE_HOLDER = '_mvcquery_place_holder';

		const MVC_QUERY_VALUE = '_mvcquery_value';

		const INSERT_DEFAULT = 1;

		const INSERT_ASSOC = 2;
		
		public $dbName		=null;	  //dbName
		public $name	 	=null;     // table name
		public $primary	   	=array();  // array with primary keys.
		public $primary_ai 	=true;     // is the pk auto increment? Only works if count($primary) == 1
		public $columns	   	=array();  // array with columns
		public $autoCreate 	=true;     // should create the table if it doesn't exist?
		
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
		
		
		/**
		 * This is one of the handlers from my handlers folder.
		 * It does the query.
		 * It is set in config / mvc / connection, who points to one of config / plugin / Db / connections.
		 * There the 'connection' config will include a 'handler', which will be used to determine which handler I load.
		 * @var MySQL|SQLite
		 */
		private $handler = null;
		
		public $insertType = self::INSERT_DEFAULT;

		/**
		 * Parent constructor sets up the DB connection, then we choose which Handler to use for generating the actual query.
		 * The handler will set it's 'model' value to this model, so that it has access to the DB connection and so that
		 * the functions here can handle the request
		 */
		public function __construct()
		{
			parent::__construct();
			require_once(__DIR__._DS_.'MvcQueryObject.php');
			require_once(__DIR__._DS_.'MvcQueryObjectData.php');
			require_once(__DIR__._DS_.'MvcQueryException.php');
			require_once(__DIR__._DS_.'handler'._DS_.'SQLite.php');
			require_once(__DIR__._DS_.'handler'._DS_.'MySQL.php');
		
			$config = Nutshell::getInstance()->config;
			$connectionName = $config->plugin->Mvc->connection;
			$handlerName = $config->plugin->Db->connections->$connectionName->handler;
			
			if($handlerName == 'mysql')
			{
				$this->handler = new MySQL($this);
			}
			elseif($handlerName == 'sqlite')
			{
				$this->handler = new SQLite($this);
			}
			else
			{
				MvcQueryException(MvcQueryException::INVALID_HANDLER, $connectionName, $handlerName);
			}
		}
		
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
			$additionalPartSQL = $queryObject->getAdditionalPartSQL();
			$data = $queryObject->getWhere();
			if(!$data) $data = array();
			$limit=array('offset'=>null,'limit'=>null);
			$sort=array('by'=>null,'dir'=>null);
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
					
					if($key == "_sortDir" && is_string($val))
					{
						$sort['dir']=str_replace("'", "", $this->db->quote($val));
					}
					
					if($key == "_count" && $val)
					{
						$aggregate = 'count';
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
			
			return $return;
		}
		
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
	}
}
