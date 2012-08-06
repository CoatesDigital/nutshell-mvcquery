<?php
namespace application\plugin\mvcQuery\handler
{
	use application\plugin\mvcQuery\handler\SQLite;
	
	class MySQL extends SQLite
	{
		
		public function showCreateTable()
		{
			$dbPrefix = $this->getDbPrefix();
			$query="SHOW CREATE TABLE {$dbPrefix}`{$this->model->name}`";
			$result = $this->model->db->getResultFromQuery($query);
			$result = $result[0];
			$result = $result['Create Table'];
			return $result;
		}
		
	}
}
?>