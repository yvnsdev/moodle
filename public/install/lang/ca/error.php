<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Automatically generated strings for Moodle installer
 *
 * Do not edit this file manually! It contains just a subset of strings
 * needed during the very first steps of installation. This file was
 * generated automatically by export-installer.php (which is part of AMOS
 * {@link https://moodledev.io/general/projects/api/amos}) using the
 * list of strings defined in public/install/stringnames.txt file.
 *
 * @package   installer
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cannotcreatedboninstall'] = '<p>No es pot crear la base de dades.</p> <p>La base de dades especificada no existeix i l\'usuari que heu proporcionat no té permís per a crear-la.</p>
<p>L\'administrador del lloc hauria de verificar la configuració de la base de dades.</p>';
$string['cannotcreatelangdir'] = 'No s\'ha pogut crear el directori d\'idiomes';
$string['cannotcreatetempdir'] = 'No s\'ha pogut crear el directori temporal';
$string['cannotdownloadcomponents'] = 'No s\'han pogut baixar components';
$string['cannotdownloadzipfile'] = 'No s\'ha pogut baixar el fitxer ZIP';
$string['cannotfindcomponent'] = 'No s\'ha pogut trobar el component';
$string['cannotsavemd5file'] = 'No s\'ha pogut desar el fitxer md5';
$string['cannotsavezipfile'] = 'No s\'ha pogut desar el fitxer ZIP';
$string['cannotunzipfile'] = 'No s\'ha pogut descomprimir el fitxer';
$string['componentisuptodate'] = 'El component està actualitzat';
$string['dmlexceptiononinstall'] = '<p>S\'ha produït un error de la base de dades [{$a->errorcode}].<br />{$a->debuginfo}</p>';
$string['downloadedfilecheckfailed'] = 'Ha fallat la comprovació del fitxer baixat';
$string['invalidmd5'] = 'L\'md5 no és vàlid. Torneu a provar-ho';
$string['missingrequiredfield'] = 'Falta algun camp necessari';
$string['remotedownloaderror'] = '<p>No s\'ha pogut baixar el component al vostre servidor. Verifiqueu els paràmetres del servidor intermediari. Es recomana vivament l\'extensió cURL de PHP.</p>
<p>Haureu de baixar manualment el fitxer <a href="{$a->url}">{$a->url}</a>, copiar-lo a la ubicació «{$a->dest}» del vostre servidor i descomprimir-lo allà.</p>';
$string['wrongdestpath'] = 'El camí de destinació és erroni';
$string['wrongsourcebase'] = 'L\'adreça (URL) base de la font és errònia';
$string['wrongzipfilename'] = 'El nom del fitxer ZIP és erroni';
