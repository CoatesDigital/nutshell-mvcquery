<?php
namespace application\plugin\mvcQuery
{
	/**
	 * An optional key->val object representing the 'where' part of the query.
	 * to be passed into MvcQueryObject as the "Where" part.
	 * 
	 * Should be renamed from 'where', as in the case of a get it represents where,
	 * but in the case of a set it represents the object's data
	 * 
	 * keys are column names and vals are column values
	 * Any key beginning with an _underscore is treated as meta-data about the query
	 * Available meta-data are: (not implemented yet)
	 * _start
	 * _limit
	 * _order
	 * 
	 * If the val of a key/val pair is an array, the key of this is the comparator, and the val is the val
	 * 
	 * @var object
	 */
	class MvcQueryObjectData
	{
		const EQUALS		= '=';
		const LIKE			= 'LIKE';
		const GREATER_THAN	= '>';
		const LESS_THAN		= '<';
	}
}