<?php
/* Copyright (C) 2015		Jean-François Ferry		<jfefe@aternatik.fr>
 * Copyright (C) 2024-2025  Frédéric France			<frederic.france@free.fr>
 * Copyright (C) ---Replace with your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

dol_include_once('/printjob/class/printjob.class.php');



/**
 * \file    htdocs/modulebuilder/template/class/api_printjob.class.php
 * \ingroup printjob
 * \brief   File for API management of myobject.
 */

/**
 * API class for printjob myobject
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class PrintJobApi extends DolibarrApi
{
	/**
	 * @var PrintJob {@type PrintJob}
	 */
	/*
	 * @var mixed TODO: set type
	 */
	public $printjob;

	/**
	 * Constructor
	 *
	 * @url     GET /
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
		$this->printjob = new PrintJob($this->db);
	}


	/* BEGIN MODULEBUILDER API PRINTJOB */
	/**
	 * Get properties of a printjob object
	 *
	 * Return an array with printjob information
	 *
	 * @param	int		$id				ID of printjob
	 * @return  Object					Object with cleaned properties
	 * @phan-return	PrintJob			Object with cleaned properties
	 * @phpstan-return	PrintJob			Object with cleaned properties
	 *
	 * @phan-return  PrintJob
	 *
	 * @url	GET printjob/{id}
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function get($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('printing', 'read')) {
			throw new RestException(403);
		}

		$result = $this->printjob->fetch($id);
		if (!$result) {
			throw new RestException(404, 'PrintJob not found');
		}

		return $this->_cleanObjectDatas($this->printjob);
	}


	/**
	 * List printjobs
	 *
	 * Get a list of printjobs
	 *
	 * @param 	string		   $sortfield			Sort field
	 * @param 	string		   $sortorder			Sort order
	 * @param 	int			   $limit				Limit for list
	 * @param 	int			   $page				Page number
	 * @param 	string         $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @param 	string		   $properties			Restrict the data returned to these properties. Ignored if empty. Comma separated list of properties names
	 * @return  array                               Array of PrintJob objects
	 * @phan-return array<int,PrintJob>
	 * @phpstan-return array<int,PrintJob>
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 503 System error
	 *
	 * @url	GET /printjobs/
	 */
	public function index($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '', $properties = '')
	{
		global $hookmanager;

		$obj_ret = array();
		$tmpobject = new PrintJob($this->db);

		if (!DolibarrApiAccess::$user->hasRight('printing', 'read')) {
			throw new RestException(403);
		}

		$sql = "SELECT t.rowid";
		$sql .= " FROM ".$this->db->prefix().$tmpobject->table_element." AS t";
		if (!empty($tmpobject->ismultientitymanaged) && (int) $tmpobject->ismultientitymanaged == 1) {
			$sql .= " WHERE t.entity IN (".getEntity($tmpobject->element).")";
		} else {
			$sql .= " WHERE 1 = 1";
		}
		if ($sqlfilters) {
			$errormessage = '';
			$sql .= forgeSQLFromUniversalSearchCriteria($sqlfilters, $errormessage);
			if ($errormessage) {
				throw new RestException(400, 'Error when validating parameter sqlfilters -> '.$errormessage);
			}
		}

		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		$result = $this->db->query($sql);
		$i = 0;
		if ($result) {
			$num = $this->db->num_rows($result);
			$min = min($num, ($limit <= 0 ? $num : $limit));
			while ($i < $min) {
				$obj = $this->db->fetch_object($result);
				$tmp_object = new PrintJob($this->db);
				if ($tmp_object->fetch($obj->rowid)) {
					$obj_ret[] = $this->_filterObjectProperties($this->_cleanObjectDatas($tmp_object), $properties);
				}
				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieving printjob list: '.$this->db->lasterror());
		}

		return $obj_ret;
	}

	/**
	 * Update printjob
	 *
	 * @param 	int   		$id             Id of printjob to update
	 * @param 	int 		$status   		Data
	 * @phan-param ?array<string,mixed>	$request_data
	 * @phpstan-param ?array<string,mixed>	$request_data
	 * @return 	Object						Object after update
	 * @phan-return PrintJob
	 * @phpstan-return PrintJob
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 500 System error
	 *
	 * @url	PUT printjobs/{id}
	 */
	public function put($id, $status)
	{
		if (!DolibarrApiAccess::$user->hasRight('printing', 'read')) {
			throw new RestException(403);
		}

		$result = $this->printjob->fetch($id);
		if (!$result) {
			throw new RestException(404, 'PrintJob not found');
		}


		$this->printjob->status = (int) $status;

		// Clean data
		// $this->printjob->abc = sanitizeVal($this->printjob->abc, 'alphanohtml');

		if ($this->printjob->update(DolibarrApiAccess::$user, 0) > 0) {
			return $this->get($id);
		} else {
			throw new RestException(500, $this->printjob->error);
		}
	}

	/**
	 * Delete printjob
	 *
	 * @param   int     $id   PrintJob ID
	 * @return  array
	 * @phan-return array<string,array{code:int,message:string}>
	 * @phpstan-return array<string,array{code:int,message:string}>
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 * @throws RestException 409 Nothing to do
	 * @throws RestException 500 System error
	 *
	 * @url	DELETE printjobs/{id}
	 */
	public function delete($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('printjob', 'printjob', 'delete')) {
			throw new RestException(403);
		}
		if (!DolibarrApi::_checkAccessToResource('printjob', $id, 'printjob_printjob')) {
			throw new RestException(403, 'Access to instance id='.$this->printjob->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}

		$result = $this->printjob->fetch($id);
		if (!$result) {
			throw new RestException(404, 'PrintJob not found');
		}

		if ($this->printjob->delete(DolibarrApiAccess::$user) == 0) {
			throw new RestException(409, 'Error when deleting PrintJob : '.$this->printjob->error);
		} elseif ($this->printjob->delete(DolibarrApiAccess::$user) < 0) {
			throw new RestException(500, 'Error when deleting PrintJob : '.$this->printjob->error);
		}

		return array(
			'success' => array(
				'code' => 200,
				'message' => 'PrintJob deleted'
			)
		);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Clean sensitive object data fields
	 * @phpstan-template T
	 *
	 * @param   Object  $object     Object to clean
	 * @return  Object              Object with cleaned properties
	 *
	 * @phpstan-param T $object
	 * @phpstan-return T
	 */
	protected function _cleanObjectDatas($object)
	{
		// phpcs:enable
		$object = parent::_cleanObjectDatas($object);

		unset($object->rowid);
		unset($object->canvas);
		unset($object->db);
		unset($object->import_key);
		unset($object->warehouse_id);

		return $object;
	}
}
