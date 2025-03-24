<?php
/* Copyright (C) 2002-2007	Rodolphe Quiedeville		<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2018	Regis Houssin				<regis.houssin@inodbox.com>
 * Copyright (C) 2011-2020	Juanjo Menent				<jmenent@2byte.es>
 * Copyright (C) 2013		Florian Henry				<florian.henry@open-concept.pro>
 * Copyright (C) 2014-2018	Ferran Marcet				<fmarcet@2byte.es>
 * Copyright (C) 2014-2022	Charlene Benke				<charlene@patas-monkey.com>
 * Copyright (C) 2015-2016	Abbes Bahfir				<bafbes@gmail.com>
 * Copyright (C) 2018-2022	Philippe Grand				<philippe.grand@atoo-net.com>
 * Copyright (C) 2020-2024	Frédéric France				<frederic.france@free.fr>
 * Copyright (C) 2023       Benjamin Grembi				<benjamin@oarces.fr>
 * Copyright (C) 2023-2024	William Mead				<william.mead@manchenumerique.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024		Alexandre Spangaro			<alexandre@inovea-conseil.com>
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

/**
 *	\file       htdocs/fichinter/card.php
 *	\brief      Page of intervention
 *	\ingroup    ficheinter
 */

// Load Dolibarr environment
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/fichinter.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
if (isModEnabled('project')) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
}
if (isModEnabled('contract')) {
	require_once DOL_DOCUMENT_ROOT."/core/class/html.formcontract.class.php";
	require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
}
if (getDolGlobalString('FICHEINTER_ADDON') && is_readable(DOL_DOCUMENT_ROOT."/core/modules/fichinter/mod_" . getDolGlobalString('FICHEINTER_ADDON').".php")) {
	require_once DOL_DOCUMENT_ROOT."/core/modules/fichinter/mod_" . getDolGlobalString('FICHEINTER_ADDON').'.php';
}
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

// Load translation files required by the page
$langs->loadLangs(array('bills', 'companies', 'interventions', 'stocks'));

$id			= GETPOSTINT('id');
$ref		= GETPOST('ref', 'alpha');
$ref_client	= GETPOST('ref_client', 'alpha');
$socid = GETPOSTINT('socid');
$contratid = GETPOSTINT('contratid');
$action		= GETPOST('action', 'alpha');
$cancel		= GETPOST('cancel', 'alpha');
$confirm	= GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$mesg = GETPOST('msg', 'alpha');
$origin = GETPOST('origin', 'alpha');
$originid = (GETPOSTINT('originid') ? GETPOSTINT('originid') : GETPOSTINT('origin_id')); // For backward compatibility
$note_public = GETPOST('note_public', 'restricthtml');
$note_private = GETPOST('note_private', 'restricthtml');
$lineid = GETPOSTINT('line_id');

$error = 0;

//PDF
$hidedetails = (GETPOSTINT('hidedetails') ? GETPOSTINT('hidedetails') : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS') ? 1 : 0));
$hidedesc = (GETPOSTINT('hidedesc') ? GETPOSTINT('hidedesc') : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DESC') ? 1 : 0));
$hideref = (GETPOSTINT('hideref') ? GETPOSTINT('hideref') : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_REF') ? 1 : 0));

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('interventioncard', 'globalcard'));

$object = new Fichinter($db);
$extrafields = new ExtraFields($db);
$objectsrc = null;

$extrafields->fetch_name_optionals_label($object->table_element);

// Load object
if ($id > 0 || !empty($ref)) {
	$ret = $object->fetch($id, $ref);
	if ($ret > 0) {
		$ret = $object->fetch_thirdparty();
	}
	if ($ret < 0) {
		dol_print_error(null, $object->error);
	}
}

// Security check
if ($user->socid) {
	$socid = $user->socid;
}
$result = restrictedArea($user, 'ficheinter', $id, 'fichinter');

$permissionnote = $user->hasRight('ficheinter', 'creer'); // Used by the include of actions_setnotes.inc.php
$permissiondellink = $user->hasRight('ficheinter', 'creer'); // Used by the include of actions_dellink.inc.php
$permissiontodelete = (($object->statut == Fichinter::STATUS_DRAFT && $user->hasRight('ficheinter', 'creer')) || $user->hasRight('ficheinter', 'supprimer'));

$usercancreate = $user->hasRight('ficheinter', 'creer');


/*
 * Actions
 */

$parameters = array('socid' => $socid);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$backurlforlist = DOL_URL_ROOT.'/fichinter/list.php';

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = DOL_URL_ROOT.'/fichinter/card.php?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
			}
		}
	}

	if ($cancel) {
		if (!empty($backtopageforcancel)) {
			header("Location: ".$backtopageforcancel);
			exit;
		} elseif (!empty($backtopage)) {
			header("Location: ".$backtopage);
			exit;
		}
		$action = '';
	}

	include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php'; // Must be include, not include_once

	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php'; // Must be include, not include_once

	// Action clone object
	if ($action == 'confirm_clone' && $confirm == 'yes' && $user->hasRight('ficheinter', 'creer')) {
		if (1 == 0 && !GETPOST('clone_content') && !GETPOST('clone_receivers')) {
			setEventMessages($langs->trans("NoCloneOptionsSpecified"), null, 'errors');
		} else {
			if ($object->id > 0) {
				// Because createFromClone modifies the object, we must clone it so that we can restore it later
				$orig = clone $object;

				$result = $object->createFromClone($user, $socid);
				if ($result > 0) {
					header("Location: ".$_SERVER['PHP_SELF'].'?id='.$result);
					exit;
				} else {
					setEventMessages($object->error, $object->errors, 'errors');
					$object = $orig;
					$action = '';
				}
			}
		}
	}

	if ($action == 'confirm_validate' && $confirm == 'yes' && $user->hasRight('ficheinter', 'creer')) {
		$result = $object->setValid($user);

		if ($result >= 0) {
			if (!getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {
				// Define output language
				$outputlangs = $langs;
				$newlang = '';
				if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
					$newlang = GETPOST('lang_id', 'aZ09');
				}
				if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {
					$newlang = $object->thirdparty->default_lang;
				}
				if (!empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}
				$result = fichinter_create($db, $object, (!GETPOST('model', 'alpha')) ? $object->model_pdf : GETPOST('model', 'alpha'), $outputlangs);
			}

			header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
			exit;
		} else {
			$mesg = $object->error;
		}
	} elseif ($action == 'confirm_modify' && $confirm == 'yes' && $user->hasRight('ficheinter', 'creer')) {
		$result = $object->setDraft($user);
		if ($result >= 0) {
			if (!getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {
				// Define output language
				$outputlangs = $langs;
				$newlang = '';
				if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
					$newlang = GETPOST('lang_id', 'aZ09');
				}
				if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {
					$newlang = $object->thirdparty->default_lang;
				}
				if (!empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}
				$result = fichinter_create($db, $object, (!GETPOST('model', 'alpha')) ? $object->model_pdf : GETPOST('model', 'alpha'), $outputlangs);
			}

			header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		} else {
			$mesg = $object->error;
		}
	} elseif ($action == 'confirm_done' && $confirm == 'yes' && $user->hasRight('ficheinter', 'creer')) {
		$result = $object->setClose($user);

		if ($result >= 0) {
			if (!getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {
				// Define output language
				$outputlangs = $langs;
				$newlang = '';
				if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
					$newlang = GETPOST('lang_id', 'aZ09');
				}
				if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {
					$newlang = $object->thirdparty->default_lang;
				}
				if (!empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}
				$result = fichinter_create($db, $object, (!GETPOST('model', 'alpha')) ? $object->model_pdf : GETPOST('model', 'alpha'), $outputlangs);
			}

			header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
			exit;
		} else {
			$mesg = $object->error;
		}
	} elseif ($action == 'add' && $user->hasRight('ficheinter', 'creer')) {
		$selectedLines = GETPOST('toselect', 'array');
		$object->socid = $socid;
		$object->duration = GETPOSTINT('duration');
		$object->fk_project = GETPOSTINT('projectid');
		$object->fk_contrat = GETPOSTINT('contratid');
		$object->author = $user->id;
		$object->description = GETPOST('description', 'restricthtml');
		$object->ref = $ref;
		$object->ref_client = $ref_client;
		$object->model_pdf = GETPOST('model', 'alpha');
		$object->note_private = GETPOST('note_private', 'restricthtml');
		$object->note_public = GETPOST('note_public', 'restricthtml');

		if ($object->socid > 0) {
			// If creation from another object of another module (Example: origin=propal, originid=1)
			if (!empty($origin) && !empty($originid)) {
				// Parse element/subelement (ex: project_task)
				$regs = array();
				$element = $subelement = GETPOST('origin', 'alphanohtml');
				if (preg_match('/^([^_]+)_([^_]+)/i', GETPOST('origin', 'alphanohtml'), $regs)) {
					$element = $regs[1];
					$subelement = $regs[2];
				}
		
				// For compatibility
				if ($element == 'order') {
					$element = $subelement = 'commande';
				}
				if ($element == 'propal') {
					$element = 'comm/propal';
					$subelement = 'propal';
				}
				if ($element == 'contract') {
					$element = $subelement = 'contrat';
				}
		
				$object->origin = $origin;
				$object->origin_id = $originid;
		
				// Possibility to add external linked objects with hooks
				$object->linked_objects[$object->origin] = $object->origin_id;
				if (GETPOSTISARRAY('other_linked_objects')) {
					$object->linked_objects = array_merge($object->linked_objects, GETPOST('other_linked_objects', 'array:int'));
				}
		
				// Extrafields
				// Fill array 'array_options' with data from add form
				$ret = $extrafields->setOptionalsFromPost(null, $object);
				if ($ret < 0) {
					$error++;
					$action = 'create';
				}
				//$array_options = $extrafields->getOptionalsFromPost($object->table_element);
		
				//$object->array_options = $array_options;
		
				$id = $object->create($user);
		
				if ($id > 0) {
					dol_include_once('/'.$element.'/class/'.$subelement.'.class.php');
		
					$classname = ucfirst($subelement);
					$srcobject = new $classname($db);
		
					dol_syslog("Try to find source object origin=".$object->origin." originid=".$object->origin_id." to add lines");
					$result = $srcobject->fetch($object->origin_id);
					if ($result > 0) {
						$srcobject->fetch_thirdparty();
						$lines = $srcobject->lines;
						if (empty($lines) && method_exists($srcobject, 'fetch_lines')) {
							$srcobject->fetch_lines();
							$lines = $srcobject->lines;
						}
		
						if (is_array($lines)) {
							$num = count($lines);
		
							for ($i = 0; $i < $num; $i++) {
								if (!in_array($lines[$i]->id, $selectedLines)) {
									continue; // Skip unselected lines
								}
		
								$product_type = ($lines[$i]->product_type ? $lines[$i]->product_type : Product::TYPE_PRODUCT);
		
								if ($prod->duration_value && $conf->global->FICHINTER_USE_SERVICE_DURATION) {
									switch ($prod->duration_unit) {
										default:
										case 'h':
											$mult = 3600;
											break;
										case 'd':
											$mult = 3600 * 24;
											break;
										case 'w':
											$mult = 3600 * 24 * 7;
											break;
										case 'm':
											$mult = (int) 3600 * 24 * (365 / 12); // Average month duration
											break;
										case 'y':
											$mult = 3600 * 24 * 365;
											break;
									}
									$duration = $prod->duration_value * $mult * $lines[$i]->qty;
								}
		
								$desc = $lines[$i]->product_ref;
								$desc .= ' - ';
								$desc .= $label;
								$desc .= '<br>';
							}
							// Common part (predefined or free line)
							$desc .= dol_htmlentitiesbr($lines[$i]->desc);
							$desc .= '<br>';
							$desc .= ' ('.$langs->trans('Quantity').': '.$lines[$i]->qty.')';
		
							$timearray = dol_getdate(dol_now());
							$date_intervention = dol_mktime(0, 0, 0, $timearray['mon'], $timearray['mday'], $timearray['year']);
		
							if ($product_type == Product::TYPE_PRODUCT) {
								$duration = 0;
							}
		
							$predef = '';
		
							// Extrafields
							$extrafields->fetch_name_optionals_label($object->table_element_line);
							$array_options = $extrafields->getOptionalsFromPost($object->table_element_line, $predef);
		
							$result = $object->addline(
								$user,
								$id,
								$desc,
								$date_intervention,
								$duration,
								$array_options
							);
		
							if ($result < 0) {
								$error++;
								break;
							}
						}
					} else {
						$langs->load("errors");
						setEventMessages($srcobject->error, $srcobject->errors, 'errors');
						$action = 'create';
						$error++;
					}
				} else {
					$langs->load("errors");
					setEventMessages($object->error, $object->errors, 'errors');
					$action = 'create';
					$error++;
				}
			} else {
				// Fill array 'array_options' with data from add form
				$ret = $extrafields->setOptionalsFromPost(null, $object);
				if ($ret < 0) {
					$error++;
					$action = 'create';
				}
		
				if (!$error) {
					// Extrafields
					$array_options = $extrafields->getOptionalsFromPost($object->table_element);
		
					$object->array_options = $array_options;
		
					$result = $object->create($user);
					if ($result > 0) {
						$id = $result; // Force raffraichissement sur fiche venant d'etre cree
					} else {
						$langs->load("errors");
						setEventMessages($object->error, $object->errors, 'errors');
						$action = 'create';
						$error++;
					}
				}
			}
		} else {
			$mesg = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ThirdParty"));
			$action = 'create';
			$error++;
		}
		
		if ($action == 'update' && $user->hasRight('ficheinter', 'creer')) {
			$object->socid = $socid;
			$object->fk_project = GETPOSTINT('projectid');
			$object->fk_contrat = GETPOSTINT('contratid');
			$object->author = $user->id;
			$object->description = GETPOST('description', 'restricthtml');
			$object->ref = $ref;
			$object->ref_client = $ref_client;
		
			$result = $object->update($user);
			if ($result < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
			}
		} elseif ($action == 'classin' && $user->hasRight('ficheinter', 'creer')) {
			// Set into a project
			$result = $object->setProject(GETPOSTINT('projectid'));
			if ($result < 0) {
				dol_print_error($db, $object->error);
			}
		}												
} elseif ($action == 'setcontract' && $user->hasRight('contrat', 'creer')) {														
// Set into a contract													
$result = $object->set_contrat($user, GETPOSTINT('contratid'));													
if ($result < 0) {													
	dol_print_error($db, $object->error);												
}													
} elseif ($action == 'setref_client' && $user->hasRight('ficheinter', 'creer')) {														
// Positionne ref client													
$result = $object->setRefClient($user, GETPOST('ref_client', 'alpha'));													
if ($result < 0) {													
	setEventMessages($object->error, $object->errors, 'errors');												
}													
} elseif ($action == 'confirm_delete' && $confirm == 'yes' && $user->hasRight('ficheinter', 'supprimer')) {														
$result = $object->delete($user);													
if ($result < 0) {													
	setEventMessages($object->error, $object->errors, 'errors');												
}													
													
header('Location: '.DOL_URL_ROOT.'/fichinter/list.php?leftmenu=ficheinter&restore_lastsearch_values=1');													
exit;													
} elseif ($action == 'setdescription' && $user->hasRight('ficheinter', 'creer')) {														
$result = $object->set_description($user, GETPOST('description'));													
if ($result < 0) {													
	dol_print_error($db, $object->error);												
}													
} elseif ($action == "addline" && $user->hasRight('ficheinter', 'creer')) {														
// Add line													
if (!GETPOST('np_desc', 'restricthtml') && !getDolGlobalString('FICHINTER_EMPTY_LINE_DESC')) {													
	$mesg = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Description"));												
	$error++;												
}													
if (!getDolGlobalString('FICHINTER_WITHOUT_DURATION') && !GETPOSTINT('durationhour') && !GETPOSTINT('durationmin')) {													
	$mesg = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Duration"));												
	$error++;												
}													
if (!getDolGlobalString('FICHINTER_WITHOUT_DURATION') && GETPOSTINT('durationhour') >= 24 && GETPOSTINT('durationmin') > 0) {													
	$mesg = $langs->trans("ErrorValueTooHigh");												
	$error++;												
}													
if (!$error) {													
	$db->begin();												
													
	$desc = GETPOST('np_desc', 'restricthtml');												
	$date_intervention = dol_mktime(GETPOSTINT('dihour'), GETPOSTINT('dimin'), 0, GETPOSTINT('dimonth'), GETPOSTINT('diday'), GETPOSTINT('diyear'));												
	$duration = !getDolGlobalString('FICHINTER_WITHOUT_DURATION') ? convertTime2Seconds(GETPOSTINT('durationhour'), GETPOSTINT('durationmin')) : 0;												
													
	// Extrafields												
	$extrafields->fetch_name_optionals_label($object->table_element_line);												
	$array_options = $extrafields->getOptionalsFromPost($object->table_element_line);												
													
	$result = $object->addline(												
		$user,											
		$id,											
		$desc,											
		$date_intervention,											
		$duration,											
		$array_options											
	);												
													
	// Define output language												
	$outputlangs = $langs;												
	$newlang = '';												
	if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {												
		$newlang = GETPOST('lang_id', 'aZ09');											
	}												
	if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {												
		$newlang = $object->thirdparty->default_lang;											
	}												
	if (!empty($newlang)) {												
		$outputlangs = new Translate("", $conf);											
		$outputlangs->setDefaultLang($newlang);											
	}												
													
	if ($result >= 0) {												
		$db->commit();											
													
		if (!getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {											
			fichinter_create($db, $object, $object->model_pdf, $outputlangs);										
		}											
		header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);											
		exit;											
	} else {												
		$mesg = $object->error;											
		$db->rollback();											
	}												
}													
} elseif ($action == 'classifybilled' && $user->hasRight('ficheinter', 'creer')) {														
// Classify Billed													
$result = $object->setStatut(Fichinter::STATUS_BILLED);													
if ($result > 0) {													
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);												
	exit;												
} else {													
	setEventMessages($object->error, $object->errors, 'errors');												
}													
} elseif ($action == 'classifyunbilled' && $user->hasRight('ficheinter', 'creer')) {														
// Classify unbilled													
$result = $object->setStatut(Fichinter::STATUS_VALIDATED);													
if ($result > 0) {													
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);												
	exit;												
} else {													
	$mesg = $object->error;												
}													
} elseif ($action == 'confirm_reopen' && $user->hasRight('ficheinter', 'creer')) {														
// Reopen													
$result = $object->setStatut(Fichinter::STATUS_VALIDATED);													
if ($result > 0) {													
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);												
	exit;												
} else {													
	$mesg = $object->error;												
}													
} elseif ($action == 'updateline' && $user->hasRight('ficheinter', 'creer') && GETPOST('save', 'alpha')) {														
// Mise a jour d'une ligne d'intervention													
$objectline = new FichinterLigne($db);													
if ($objectline->fetch($lineid) <= 0) {													
	dol_print_error($db);												
	exit;												
}													
													
if ($object->fetch($objectline->fk_fichinter) <= 0) {													
	dol_print_error($db);												
	exit;												
}													
$object->fetch_thirdparty();													
													
$desc = GETPOST('np_desc', 'restricthtml');													
$date_inter = dol_mktime(GETPOSTINT('dihour'), GETPOSTINT('dimin'), 0, GETPOSTINT('dimonth'), GETPOSTINT('diday'), GETPOSTINT('diyear'));													
$duration = convertTime2Seconds(GETPOSTINT('durationhour'), GETPOSTINT('durationmin'));													
													
$objectline->date = $date_inter;													
$objectline->desc = $desc;													
$objectline->duration = $duration;													
													
// Extrafields													
$extrafields->fetch_name_optionals_label($object->table_element_line);													
$array_options = $extrafields->getOptionalsFromPost($object->table_element_line);													
if (is_array($array_options)) {													
	$objectline->array_options = array_merge($objectline->array_options, $array_options);												
}													
													
$result = $objectline->update($user);													
if ($result < 0) {													
	dol_print_error($db);												
	exit;												
}													
													
// Define output language													
$outputlangs = $langs;													
$newlang = '';													
if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {													
	$newlang = GETPOST('lang_id', 'aZ09');												
}													
if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {													
	$newlang = $object->thirdparty->default_lang;												
}													
if (!empty($newlang)) {													
	$outputlangs = new Translate("", $conf);												
	$outputlangs->setDefaultLang($newlang);												
}													
if (!getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {													
	fichinter_create($db, $object, $object->model_pdf, $outputlangs);												
}													
													
header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);													
exit;													
} elseif ($action == 'confirm_deleteline' && $confirm == 'yes' && $user->hasRight('ficheinter', 'creer')) {														
// Supprime une ligne d'intervention AVEC confirmation													
$objectline = new FichinterLigne($db);													
if ($objectline->fetch($lineid) <= 0) {													
	dol_print_error($db);												
	exit;												
}													
$result = $objectline->deleteLine($user);													
													
if ($object->fetch($objectline->fk_fichinter) <= 0) {													
	dol_print_error($db);												
	exit;												
}													
													
// Define output language													
$outputlangs = $langs;													
$newlang = '';													
if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {													
	$newlang = GETPOST('lang_id', 'aZ09');												
}													
if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {													
	$newlang = $object->thirdparty->default_lang;												
}													
if (!empty($newlang)) {													
	$outputlangs = new Translate("", $conf);												
	$outputlangs->setDefaultLang($newlang);												
}													
if (!getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {													
	fichinter_create($db, $object, $object->model_pdf, $outputlangs);												
}													
} elseif ($action == 'up' && $user->hasRight('ficheinter', 'creer')) {														
// Set position of lines													
$object->line_up($lineid);													
													
// Define output language													
$outputlangs = $langs;													
$newlang = '';													
if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {													
	$newlang = GETPOST('lang_id', 'aZ09');												
}													
if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {													
	$newlang = $object->thirdparty->default_lang;												
}													
if (!empty($newlang)) {													
	$outputlangs = new Translate("", $conf);												
	$outputlangs->setDefaultLang($newlang);												
}													
if (!getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {													
	fichinter_create($db, $object, $object->model_pdf, $outputlangs);												
}													
													
header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id.'#'.$lineid);													
exit;													
} elseif ($action == 'down' && $user->hasRight('ficheinter', 'creer')) {														
$object->line_down($lineid);													
													
// Define output language													
$outputlangs = $langs;													
$newlang = '';													
if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {													
	$newlang = GETPOST('lang_id', 'aZ09');												
}													
if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang)) {													
	$newlang = $object->thirdparty->default_lang;												
}													
if (!empty($newlang)) {													
	$outputlangs = new Translate("", $conf);												
	$outputlangs->setDefaultLang($newlang);												
}													
if (!getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {													
	fichinter_create($db, $object, $object->model_pdf, $outputlangs);												
}													
													
header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id.'#'.$lineid);													
exit;													
}														
													
// Actions when printing a doc from card														
include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';														
													
// Actions to send emails														
$triggersendname = 'FICHINTER_SENTBYMAIL';														
$autocopy = 'MAIN_MAIL_AUTOCOPY_FICHINTER_TO';														
$trackid = 'int'.$object->id;														
include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';														
													
// Actions to build doc														
$upload_dir = $conf->ficheinter->dir_output;														
$permissiontoadd = $user->hasRight('ficheinter', 'creer');														
include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';														
													
if ($action == 'update_extras' && $user->hasRight('ficheinter', 'creer')) {														
$object->oldcopy = dol_clone($object, 2);													
													
// Fill array 'array_options' with data from update form													
$ret = $extrafields->setOptionalsFromPost(null, $object, GETPOST('attribute', 'restricthtml'));													
if ($ret < 0) {													
	$error++;												
}													
													
if (!$error) {													
	// Actions on extra fields												
	$result = $object->insertExtraFields('INTERVENTION_MODIFY');												
	if ($result < 0) {												
		setEventMessages($object->error, $object->errors, 'errors');											
		$error++;											
	}												
}													
													
if ($error) {													
	$action = 'edit_extras';												
}													
}														
													
if (getDolGlobalString('MAIN_DISABLE_CONTACTS_TAB') && $user->hasRight('ficheinter', 'creer')) {														
if ($action == 'addcontact') {													
	if ($result > 0 && $id > 0) {												
		$contactid = (GETPOSTINT('userid') ? GETPOSTINT('userid') : GETPOSTINT('contactid'));											
		$typeid = (GETPOST('typecontact') ? GETPOST('typecontact') : GETPOST('type'));											
		$result = $object->add_contact($contactid, $typeid, GETPOST("source", 'aZ09'));											
	}												
													
	if ($result >= 0) {												
		header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);											
		exit;											
	} else {												
		if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS') {											
			$langs->load("errors");										
			$mesg = $langs->trans("ErrorThisContactIsAlreadyDefinedAsThisType");										
		} else {											
			$mesg = $object->error;										
		}											
	}												
} elseif ($action == 'swapstatut') {													
	// bascule du statut d'un contact												
	$result = $object->swapContactStatus(GETPOSTINT('ligne'));												
} elseif ($action == 'deletecontact') {													
	// Efface un contact												
	$result = $object->delete_contact(GETPOSTINT('lineid'));												
													
	if ($result >= 0) {												
		header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);											
		exit;											
	} else {												
		dol_print_error($db);											
	}												
}													
}														
}														
													
													
/*														
* View														
*/														
													
$form = new Form($db);														
$formfile = new FormFile($db);														
if (isModEnabled('contract')) {														
$formcontract = new FormContract($db);														
													
													
$formproject = new FormProjets($db);														
													
													
													
$help_url = 'EN:Module_Interventions';														
													
llxHeader('', $langs->trans("Intervention"), $help_url);														
													
if ($action == 'create') {														
// Create new intervention														
													
$soc = new Societe($db);														
													
print load_fiche_titre($langs->trans("AddIntervention"), '', 'intervention');														
													
if ($error > 0) {														
dol_htmloutput_errors($mesg);													
} else {														
dol_htmloutput_mesg($mesg);													
}														
													
if ($socid) {														
$res = $soc->fetch($socid);													
}														
													
if (GETPOST('origin', 'alphanohtml') && GETPOSTINT('originid')) {														
// Parse element/subelement (ex: project_task)													
$regs = array();													
$element = $subelement = GETPOST('origin', 'alphanohtml');													
if (preg_match('/^([^_]+)_([^_]+)/i', GETPOST('origin', 'alphanohtml'), $regs)) {													
	$element = $regs[1];												
	$subelement = $regs[2];												
}													
													
if ($element == 'project') {													
	$projectid = GETPOSTINT('originid');												
} else {													
	// For compatibility												
	if ($element == 'order' || $element == 'commande') {												
		$element = $subelement = 'commande';											
	}												
	if ($element == 'propal') {												
		$element = 'comm/propal';											
		$subelement = 'propal';											
	}												
	if ($element == 'contract') {												
		$element = $subelement = 'contrat';											
	}												
													
	dol_include_once('/'.$element.'/class/'.$subelement.'.class.php');												
													
	$classname = ucfirst($subelement);												
	$objectsrc = new $classname($db);												
	$objectsrc->fetch(GETPOST('originid'));												
	if (empty($objectsrc->lines) && method_exists($objectsrc, 'fetch_lines')) {												
		$objectsrc->fetch_lines();											
		$lines = $objectsrc->lines;											
	}												
	$objectsrc->fetch_thirdparty();												
													
	$projectid = (!empty($objectsrc->fk_project) ? $objectsrc->fk_project : '');												
													
	$soc = $objectsrc->thirdparty;												
													
	$note_private = (!empty($objectsrc->note) ? $objectsrc->note : (!empty($objectsrc->note_private) ? $objectsrc->note_private : GETPOST('note_private', 'restricthtml')));												
	$note_public = (!empty($objectsrc->note_public) ? $objectsrc->note_public : GETPOST('note_public', 'restricthtml'));												
													
	// Replicate extrafields												
	$objectsrc->fetch_optionals();												
	$object->array_options = $objectsrc->array_options;												
													
	// Object source contacts list												
	$srccontactslist = $objectsrc->liste_contact(-1, 'external', 1);												
}													
} else {														
$projectid = GETPOSTINT('projectid');													
}														
													
if (!$conf->global->FICHEINTER_ADDON) {														
dol_print_error($db, $langs->trans("Error")." ".$langs->trans("Error_FICHEINTER_ADDON_NotDefined"));													
exit;													
}														
													
$object->date = dol_now();														
													
$obj = getDolGlobalString('FICHEINTER_ADDON');														
$obj = "mod_".$obj;														
													
//$modFicheinter = new $obj;														
//$numpr = $modFicheinter->getNextValue($soc, $object);														
													
if ($socid > 0) {														
$soc = new Societe($db);													
$soc->fetch($socid);													
													
print '<form name="fichinter" action="'.$_SERVER['PHP_SELF'].'" method="POST">';													
print '<input type="hidden" name="token" value="'.newToken().'">';													
print '<input type="hidden" name="socid" value='.$soc->id.'>';													
print '<input type="hidden" name="action" value="add">';													
print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';													
													
print dol_get_fiche_head('');													
													
print '<table class="border centpercent">';													
													
print '<tr><td class="fieldrequired titlefieldcreate">'.$langs->trans("ThirdParty").'</td><td>'.$soc->getNomUrl(1).'</td></tr>';													
													
// Ref													
print '<tr><td class="fieldrequired">'.$langs->trans('Ref').'</td><td>'.$langs->trans("Draft").'</td></tr>';													
													
// Ref customer													
print '<tr class="field_ref_client"><td class="titlefieldcreate">'.$langs->trans('RefCustomer').'</td><td class="valuefieldcreate">';													
print '<input type="text" name="ref_client" value="'.GETPOST('ref_client').'"></td>';													
print '</tr>';													
													
// Description (must be a textarea and not html must be allowed (used in list view)													
print '<tr><td class="tdtop">'.$langs->trans("Description").'</td>';													
print '<td>';													
print '<textarea name="description" class="quatrevingtpercent" rows="'.ROWS_3.'">'.GETPOST('description').'</textarea>';													
print '</td></tr>';													
													
// Project													
if (isModEnabled('project')) {													
	$formproject = new FormProjets($db);												
													
	$langs->load("project");												
													
	print '<tr><td>'.$langs->trans("Project").'</td><td>';												
	/* Fix: If a project must be linked to any companies (suppliers or not), project must be not be set as limited to customer but must be not linked to any particular thirdparty												
	if ($societe->fournisseur==1)												
		$numprojet=select_projects(-1, GETPOST("projectid", 'int'), 'projectid');											
	else												
		$numprojet=select_projects($societe->id, GETPOST("projectid", 'int'), 'projectid');											
		*/											
	$numprojet = $formproject->select_projects($soc->id, $projectid, 'projectid');												
	if ($numprojet == 0) {												
		print ' &nbsp; <a href="'.DOL_URL_ROOT.'/projet/card.php?socid='.$soc->id.'&action=create"><span class="fa fa-plus-circle valignmiddle paddingleft" title="'.$langs->trans("AddProject").'"></span></a>';											
	}												
	print '</td></tr>';												
}													
													
// Contract													
if (isModEnabled('contract')) {													
	$langs->load("contracts");												
	print '<tr><td>'.$langs->trans("Contract").'</td><td>';												
	$numcontrat = $formcontract->select_contract($soc->id, GETPOSTINT('contratid'), 'contratid', 0, 1, 1);												
	if ($numcontrat == 0) {												
		print ' &nbsp; <a href="'.DOL_URL_ROOT.'/contrat/card.php?socid='.$soc->id.'&action=create"><span class="fa fa-plus-circle valignmiddle paddingleft" title="'.$langs->trans("AddContract").'"></span></a>';											
	}												
	print '</td></tr>';												
}													
													
// Model													
print '<tr>';													
print '<td>'.$langs->trans("DefaultModel").'</td>';													
print '<td>';													
$liste = ModelePDFFicheinter::liste_modeles($db);													
print $form->selectarray('model', $liste, $conf->global->FICHEINTER_ADDON_PDF);													
print "</td></tr>";													
													
// Public note													
print '<tr>';													
print '<td class="tdtop">'.$langs->trans('NotePublic').'</td>';													
print '<td>';													
$doleditor = new DolEditor('note_public', $note_public, '', 80, 'dolibarr_notes', 'In', 0, false, !getDolGlobalString('FCKEDITOR_ENABLE_NOTE_PUBLIC') ? 0 : 1, ROWS_3, '90%');													
print $doleditor->Create(1);													
//print '<textarea name="note_public" cols="80" rows="'.ROWS_3.'">'.$note_public.'</textarea>';													
print '</td></tr>';													
													
// Private note													
if (empty($user->socid)) {													
	print '<tr>';												
	print '<td class="tdtop">'.$langs->trans('NotePrivate').'</td>';												
	print '<td>';												
	$doleditor = new DolEditor('note_private', $note_private, '', 80, 'dolibarr_notes', 'In', 0, false, !getDolGlobalString('FCKEDITOR_ENABLE_NOTE_PRIVATE') ? 0 : 1, ROWS_3, '90%');												
	print $doleditor->Create(1);												
	//print '<textarea name="note_private" cols="80" rows="'.ROWS_3.'">'.$note_private.'</textarea>';												
	print '</td></tr>';												
}													
													
// Other attributes													
$parameters = array();													
$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action); // Note that $action and $object may have been modified by hook													
print $hookmanager->resPrint;													
if (empty($reshook)) {													
	print $object->showOptionals($extrafields, 'create');												
}													
													
// Show link to origin object													
if (!empty($origin) && !empty($originid) && is_object($objectsrc)) {													
	$newclassname = $classname;												
	if ($newclassname == 'Propal') {												
		$langs->load('propal');											
		$newclassname = 'CommercialProposal';											
	}												
	print '<tr><td>'.$langs->trans($newclassname).'</td><td colspan="2">'.$objectsrc->getNomUrl(1).'</td></tr>';												
													
	// Amount												
	/* Hide amount because we only copy services so amount may differ than source												
	print '<tr><td>' . $langs->trans('AmountHT') . '</td><td>' . price($objectsrc->total_ht) . '</td></tr>';												
	print '<tr><td>' . $langs->trans('AmountVAT') . '</td><td>' . price($objectsrc->total_tva) . "</td></tr>";												
	if ($mysoc->localtax1_assuj == "1" || $objectsrc->total_localtax1 != 0) 		// Localtax1 RE										
	{												
		print '<tr><td>' . $langs->transcountry("AmountLT1", $mysoc->country_code) . '</td><td>' . price($objectsrc->total_localtax1) . "</td></tr>";											
	}												
													
	if ($mysoc->localtax2_assuj == "1" || $objectsrc->total_localtax2 != 0) 		// Localtax2 IRPF										
	{												
		print '<tr><td>' . $langs->transcountry("AmountLT2", $mysoc->country_code) . '</td><td>' . price($objectsrc->total_localtax2) . "</td></tr>";											
	}												
													
	print '<tr><td>' . $langs->trans('AmountTTC') . '</td><td>' . price($objectsrc->total_ttc) . "</td></tr>";												
													
	if (isModEnabled("multicurrency"))												
	{												
		print '<tr><td>' . $langs->trans('MulticurrencyAmountHT') . '</td><td>' . price($objectsrc->multicurrency_total_ht) . '</td></tr>';											
		print '<tr><td>' . $langs->trans('MulticurrencyAmountVAT') . '</td><td>' . price($objectsrc->multicurrency_total_tva) . "</td></tr>";											
		print '<tr><td>' . $langs->trans('MulticurrencyAmountTTC') . '</td><td>' . price($objectsrc->multicurrency_total_ttc) . "</td></tr>";											
	}												
	*/												
}													
													
print '</table>';													
													
if (is_object($objectsrc)) {													
	print '<input type="hidden" name="origin"         value="'.$objectsrc->element.'">';												
	print '<input type="hidden" name="originid"       value="'.$objectsrc->id.'">';												
} elseif ($origin == 'project' && !empty($projectid)) {													
	print '<input type="hidden" name="projectid" value="'.$projectid.'">';												
}													
													
print dol_get_fiche_end();													
													
print $form->buttonsSaveCancel("CreateDraftIntervention");													
													
// Show origin lines													
if (!empty($origin) && !empty($originid) && is_object($objectsrc) && !getDolGlobalInt('FICHINTER_DISABLE_DETAILS')) {													
	$title = $langs->trans('Services');												
	print load_fiche_titre($title);												
													
	print '<div class="div-table-responsive-no-min">';												
	print '<table class="noborder centpercent">';												
													
	$objectsrc->printOriginLinesList(!getDolGlobalString('FICHINTER_PRINT_PRODUCTS') ? 'services' : ''); // Show only service, except if option FICHINTER_PRINT_PRODUCTS is on												
													
	print '</table>';												
	print '</div>';												
}													
													
print '</form>';													
} else {														
	print '<form name="fichinter" action="'.$_SERVER['PHP_SELF'].'" method="POST">';													
	print '<input type="hidden" name="token" value="'.newToken().'">';													
	print '<input type="hidden" name="action" value="create">';		// We go back to create action											
	print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';													
													
	print dol_get_fiche_head('');													
													
	if (is_object($objectsrc)) {													
		print '<input type="hidden" name="origin" value="'.$objectsrc->element.'">';												
		print '<input type="hidden" name="originid" value="'.$objectsrc->id.'">';												
	} elseif ($origin == 'project' && !empty($projectid)) {													
		print '<input type="hidden" name="projectid" value="'.$projectid.'">';												
	}													
	print '<table class="border centpercent">';													
	print '<tr><td class="fieldrequired">'.$langs->trans("ThirdParty").'</td><td>';													
	print $form->select_company('', 'socid', '', 'SelectThirdParty', 1, 0, null, 0, 'minwidth300');													
	print ' <a href="'.DOL_URL_ROOT.'/societe/card.php?action=create&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=create').'"><span class="fa fa-plus-circle valignmiddle paddingleft" title="'.$langs->trans("AddThirdParty").'"></span></a>';													
	print '</td></tr>';													
	print '</table>';													
													
	print dol_get_fiche_end();													
													
	print $form->buttonsSaveCancel("CreateDraftIntervention");													
													
	print '</form>';													
} elseif ($id > 0 || !empty($ref)) {														
	// View mode														
													
	$object->fetch($id, $ref);														
	$object->fetch_thirdparty();														
													
	$soc = new Societe($db);														
	$soc->fetch($object->socid);														
													
	if ($error > 0) {														
		dol_htmloutput_errors($mesg);												
	} else {														
		dol_htmloutput_mesg($mesg);												
	}														
													
	$head = fichinter_prepare_head($object);														
													
	print dol_get_fiche_head($head, 'card', $langs->trans("InterventionCard"), -1, 'intervention');														
													
	$formconfirm = '';														
													
	// Confirm deletion of intervention														
	if ($action == 'delete') {														
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteIntervention'), $langs->trans('ConfirmDeleteIntervention'), 'confirm_delete', '', 0, 1);													
	}														
													
	// Confirm validation														
	if ($action == 'validate') {														
		// Verify if the object's number os temporary													
		$ref = substr($object->ref, 1, 4);													
		if ($ref == 'PROV') {													
			$numref = $object->getNextNumRef($soc);												
			if (empty($numref)) {												
				$error++;											
				setEventMessages($object->error, $object->errors, 'errors');											
			}												
		} else {													
			$numref = $object->ref;												
		}													
		$text = $langs->trans('ConfirmValidateIntervention', $numref);													
		if (isModEnabled('notification')) {													
			require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';												
			$notify = new Notify($db);												
			$text .= '<br>';												
			$text .= $notify->confirmMessage('FICHINTER_VALIDATE', $object->socid, $object);												
		}													
													
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ValidateIntervention'), $text, 'confirm_validate', '', 1, 1);													
	}														
													
	// Confirm done														
	if ($action == 'classifydone') {														
		$text = $langs->trans('ConfirmCloseIntervention');													
		if (isModEnabled('notification')) {													
			require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';												
			$notify = new Notify($db);												
			$text .= '<br>';												
			$text .= $notify->confirmMessage('FICHINTER_CLOSE', $object->socid, $object);												
		}													
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('CloseIntervention'), $text, 'confirm_done', '', 0, 1);													
	}														
													
	// Confirm back to draft														
	if ($action == 'modify') {														
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ModifyIntervention'), $langs->trans('ConfirmModifyIntervention'), 'confirm_modify', '', 0, 1);													
	}														
													
	// Confirm back to open														
	if ($action == 'reopen') {														
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ReOpen'), $langs->trans('ConfirmReopenIntervention', $object->ref), 'confirm_reopen', '', 0, 1);													
	}														
													
	// Confirm deletion of line														
	if ($action == 'ask_deleteline') {														
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&line_id='.$lineid, $langs->trans('DeleteInterventionLine'), $langs->trans('ConfirmDeleteInterventionLine'), 'confirm_deleteline', '', 0, 1);													
	}														
													
	// Clone confirmation														
	if ($action == 'clone') {														
		// Create an array for form													
		$formquestion = array(													
			array('type' => 'other', 'name' => 'socid', 'label' => $langs->trans("SelectThirdParty"), 'value' => $form->select_company(GETPOSTINT('socid'), 'socid', '', '', 0, 0, null, 0, 'minwidth200')),												
		);													
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneIntervention', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);													
	}														
													
	if (!$formconfirm) {														
		$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);													
		$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook													
		if (empty($reshook)) {													
			$formconfirm .= $hookmanager->resPrint;												
		} elseif ($reshook > 0) {													
			$formconfirm = $hookmanager->resPrint;												
		}													
	}														
													
	// Print form confirm														
	print $formconfirm;														
													
													
	// Intervention card														
	$linkback = '<a href="'.DOL_URL_ROOT.'/fichinter/list.php?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';														
													
													
	$morehtmlref = '<div class="refidno">';														
	// Ref customer														
	$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, $user->hasRight('ficheinter', 'creer'), 'string', '', 0, 1);														
	$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, $user->hasRight('ficheinter', 'creer'), 'string', '', null, null, '', 1);														
	// Thirdparty														
	$morehtmlref .= '<br>'.$object->thirdparty->getNomUrl(1, 'customer');														
	// Project														
	if (isModEnabled('project')) {														
		$langs->load("projects");													
		$morehtmlref .= '<br>';													
		if ($usercancreate) {													
			$morehtmlref .= img_picto($langs->trans("Project"), 'project', 'class="pictofixedwidth"');												
			if ($action != 'classify') {												
				$morehtmlref .= '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=classify&token='.newToken().'&id='.$object->id.'">'.img_edit($langs->transnoentitiesnoconv('SetProject')).'</a> ';											
			}												
			$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, $object->socid, $object->fk_project, ($action == 'classify' ? 'projectid' : 'none'), 0, 0, 0, 1, '', 'maxwidth300');												
		} else {													
			if (!empty($object->fk_project)) {												
				$proj = new Project($db);											
				$proj->fetch($object->fk_project);											
				$morehtmlref .= $proj->getNomUrl(1);											
				if ($proj->title) {											
					$morehtmlref .= '<span class="opacitymedium"> - '.dol_escape_htmltag($proj->title).'</span>';										
				}											
			}												
		}
	}
}