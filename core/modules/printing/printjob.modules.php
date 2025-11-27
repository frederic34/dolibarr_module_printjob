<?php
/*
 * Copyright (C) 2014-2025  Frédéric France      <frederic.france@free.fr>
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
 * or see https://www.gnu.org/
 */

/**
 *      \file       htdocs/core/modules/printing/printjob.modules.php
 *      \ingroup    printing
 *      \brief      File to provide printing with Microsoft Cloud Print
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/printing/modules_printing.php';

// phpcs:disable
/**
 *     Class to provide printing with PrintJob Cloud Print
 */
class printing_printjob extends PrintingDriver
{
	// phpcs:enable
	/**
	 * @var string module name
	 */
	public $name = 'printjob';

	/**
	 * @var string module description
	 */
	public $desc = 'PrintJobDesc';

	/**
	 * @var string String with name of icon for myobject. Must be the part after the 'object_' into object_myobject.png
	 */
	public $picto = 'printer';

	/**
	 * @var string active param name
	 */
	public $active = 'PRINTING_PRINTPRINTJOB';

	public $conf = [];

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string
	 */
	public $resprint;

	/**
	 * @var string[] Error codes (or messages)
	 */
	public $errors = [];

	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	public const LANGFILE = 'printjob@printjob';

	/**
	 *  Constructor
	 *
	 *  @param      DoliDB      $db      Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		$this->db = $db;
		$langs->load('printjob@printjob');

		if (!isModEnabled('printjob')) {
			$this->conf[] = [
				'varname' => 'PRINTJOB_INFO',
				'info' => $langs->transnoentitiesnoconv("WarningModuleNotActive", "OAuth"),
				'type' => 'info',
			];
		} else {
			if (1) {
				$this->conf[] = [
					'varname' => 'PRINTJOB_INFO',
					'info' => 'PrintJobAuthConfigured',
					'type' => 'info',
					'required' => false,
				];
			} else {
				$this->conf[] = [
					'varname' => 'PRINTJOB_INFO',
					'info' => 'PrintJobAuthNotConfigured',
					'type' => 'info',
					'required' => true,
				];
			}
		}
		// do not display submit button
		$this->conf[] = [
			'enabled' => 0,
			'type' => 'submit'
		];
	}

	/**
	 *  Return list of available printers
	 *
	 *  @return  int                     0 if OK, >0 if KO
	 */
	public function listAvailablePrinters()
	{
		global $langs;
		$error = 0;
		$langs->load('printing');

		$html = '<tr class="liste_titre">';
		$html .= '<td>' . $langs->trans('PRINTJOB_PRINTER_Name') . '</td>';
		$html .= '<td>' . $langs->trans('PRINTJOB_PRINTER_displayName') . '</td>';
		$html .= '<td>' . $langs->trans('PRINTJOB_PRINTER_Id') . '</td>';
		$html .= '<td>' . $langs->trans('PRINTJOB_PRINTER_OwnerName') . '</td>';
		$html .= '<td>' . $langs->trans('PRINTJOB_PRINTER_State') . '</td>';
		$html .= '<td>' . $langs->trans('PRINTJOB_PRINTER_connectionStatus') . '</td>';
		$html .= '<td>' . $langs->trans('PRINTJOB_PRINTER_Type') . '</td>';
		$html .= '<td class="center">' . $langs->trans("Select") . '</td>';
		$html .= "</tr>\n";
		$list = $this->getlistAvailablePrinters();

		foreach ($list['available'] as $printer_det) {
			$html .= '<tr class="oddeven">';
			$html .= '<td>' . $printer_det['name'] . '</td>';
			$html .= '<td>' . $printer_det['displayName'] . '</td>';
			$html .= '<td>' . $printer_det['id'] . '</td>'; // id to identify printer to use
			$html .= '<td>' . $printer_det['ownerName'] . '</td>';
			$html .= '<td>' . $printer_det['status'] . '</td>';
			$html .= '<td>' . $langs->trans('STATE_' . $printer_det['connectionStatus']) . '</td>';
			$html .= '<td>' . $langs->trans('TYPE_' . $printer_det['type']) . '</td>';
			// Defaut
			$html .= '<td class="center">';
			if (getDolGlobalString('PRINTING_PRINTJOB_DEFAULT') == $printer_det['id']) {
				$html .= img_picto($langs->trans("Default"), 'on');
			} else {
				$html .= '<a href="' . $_SERVER["PHP_SELF"] . '?action=setvalue&token=' . newToken() . '&mode=test&varname=PRINTING_PRINTJOB_DEFAULT&driver=printjob&value=' . urlencode($printer_det['id']) . '" alt="' . $langs->trans("Default") . '">';
				$html .= img_picto($langs->trans("Disabled"), 'off');
				$html .= '</a>';
			}
			$html .= '</td>';
			$html .= '</tr>' . "\n";
		}
		$this->resprint = $html;

		return $error;
	}


	/**
	 *  Return list of available printers
	 *
	 *  @return array      list of printers
	 */
	public function getlistAvailablePrinters()
	{
		global $user;
		$ret = [];

		$ret['available'] = [
			'default' => [
				'id' => 'default',
				'name' => 'default',
				'displayName' => 'default',
				'ownerName' => 'dolibarr',
				'status' => 'online',
				'connectionStatus' => 'printing',
				'type' => 'color',
			],
		];

		return $ret;
	}

	/**
	 *  Print selected file
	 *
	 * @param   string      $file       file
	 * @param   string      $module     module
	 * @param   string      $subdir     subdir for file
	 * @return  int                     0 if OK, >0 if KO
	 */
	public function printFile($file, $module, $subdir = '')
	{
		require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		global $conf, $user;
		$error = 0;

		$fileprint = $conf->{$module}->dir_output;
		if ($subdir != '') {
			$fileprint .= '/' . $subdir;
		}
		$fileprint .= '/' . $file;
		$mimetype = dol_mimetype($fileprint);
		// select printer uri for module order, propal,...
		$sql = "SELECT rowid, printer_id, copy FROM " . MAIN_DB_PREFIX . "printing WHERE module='" . $this->db->escape($module) . "' AND driver='printjob' AND userid=" . $user->id;
		$result = $this->db->query($sql);
		if ($result) {
			$obj = $this->db->fetch_object($result);
			if ($obj) {
				$printer_id = $obj->printer_id;
			} else {
				if (!empty($conf->global->PRINTING_PRINTJOB_DEFAULT)) {
					$printer_id = $conf->global->PRINTING_PRINTJOB_DEFAULT;
				} else {
					$this->errors[] = 'NoDefaultPrinterDefined';
					$error++;
					return $error;
				}
			}
		} else {
			dol_print_error($this->db);
		}

		$ret = $this->sendPrintToPrinter($printer_id, $file, $fileprint, $mimetype, $module);
		$this->error = 'PRINTJOB: ' . $ret['errormessage'];
		if ($ret['status'] != 1) {
			$error++;
		}
		return $error;
	}

	/**
	 *  Sends document to the printer
	 *
	 *  @param  string      $printerid      Printer id returned by PrintJob Cloud Print
	 *  @param  string      $printjobtitle  Job Title
	 *  @param  string      $filepath       File Path to be send to PrintJob Cloud Print
	 *  @param  string      $contenttype    File content type by example application/pdf, image/png
	 *  @param  string      $module         Module
	 *  @return array                       status array
	 */
	public function sendPrintToPrinter($printerid, $printjobtitle, $filepath, $contenttype, $module)
	{
		global $user, $langs;

		// Check if printer id
		if (empty($printerid)) {
			return ['status' => 0, 'errorcode' => '', 'errormessage' => 'No provided printer ID'];
		}
		$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'printjob WHERE date_creation < "'.$this->db->idate(dol_now() - 3600).'"';
		$this->db->query($sql);

		$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'printjob (printerid, filename, modulepart, date_creation, fk_user_creat, status) VALUES ("' . $this->db->escape($printerid) . '", "' . $this->db->escape($printjobtitle) . '", "' . $this->db->escape($module) . '", "' . $this->db->idate(dol_now()) . '", ' . (int) $user->id . ', 0)';
		$this->db->query($sql);

		$response = [
			'success' => 1,
			'errorCode' => '',
			'message' => $langs->trans('TheFileHasBeenAddedToThePrintQueue'),
		];
		return ['status' => $response['success'], 'errorcode' => $response['errorCode'], 'errormessage' => $response['message']];
	}


	/**
	 *  List jobs print

	 *  @param   ?string      $module     module
	 *
	 *  @return  int                     0 if OK, >0 if KO
	 */
	public function listJobs($module = null)
	{
		global $langs;

		$error = 0;
		$html = '';

		// $graph = getPrintJobClient($user);

		// Getting Jobs
		// // Send a request with api
		// try {
		// 	$response = $apiService->request(self::PRINTERS_GET_JOBS);
		// } catch (Exception $e) {
		// 	$this->errors[] = $e->getMessage();
		// 	$error++;
		// }
		// $responsedata = json_decode($response, true);
		//$html .= '<pre>'.print_r($responsedata,true).'</pre>';
		$html .= '<div class="div-table-responsive">';
		$html .= '<table width="100%" class="noborder">';
		$html .= '<tr class="liste_titre">';
		$html .= '<td>' . $langs->trans("Id") . '</td>';
		$html .= '<td>' . $langs->trans("Date") . '</td>';
		$html .= '<td>' . $langs->trans("Owner") . '</td>';
		$html .= '<td>' . $langs->trans("Printer") . '</td>';
		$html .= '<td>' . $langs->trans("Filename") . '</td>';
		$html .= '<td>' . $langs->trans("Status") . '</td>';
		$html .= '<td>' . $langs->trans("Cancel") . '</td>';
		$html .= '</tr>' . "\n";

		$jobs = $responsedata['jobs'];
		//$html .= '<pre>'.print_r($jobs['0'],true).'</pre>';
		if (is_array($jobs)) {
			foreach ($jobs as $value) {
				$html .= '<tr class="oddeven">';
				$html .= '<td>' . $value['id'] . '</td>';
				$dates = dol_print_date((int) substr($value['createTime'], 0, 10), 'dayhour');
				$html .= '<td>' . $dates . '</td>';
				$html .= '<td>' . $value['ownerId'] . '</td>';
				$html .= '<td>' . $value['printerName'] . '</td>';
				$html .= '<td>' . $value['title'] . '</td>';
				$html .= '<td>' . $value['status'] . '</td>';
				$html .= '<td>&nbsp;</td>';
				$html .= '</tr>';
			}
		} else {
			$html .= '<tr class="oddeven">';
			$html .= '<td colspan="7" class="opacitymedium">' . $langs->trans("None") . '</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		$html .= '</div>';

		$this->resprint = $html;

		return $error;
	}
}
