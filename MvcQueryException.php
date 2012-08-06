<?php
namespace application\plugin\mvcQuery
{
	use nutshell\core\exception\NutshellException;

	/**
	 * @author Dean Rather
	 */
	class MvcQueryException extends NutshellException
	{
		/** 'type' must be one of 'select', 'insert', 'update', 'delete' */
		const INVALID_TYPE = 1;
		
		/** Queries must have a 'type' */
		const NEEDS_TYPE = 2;
		
		/** Queries must have a 'table' */
		const NEEDS_TABLE = 3;
		
		/** MvcQuery doesn't have a handler for this.. */
		const INVALID_HANDLER = 4;
		
		/** DB Connection not defined in config */
		const CONNECTION_NOT_DEFINED = 5;
		
		/** CRUD Model is misconfigured. Name, Primary Key and Columns must be defined. */
		const MODEL_MISCONFIGURED = 6;
	}
}
?>