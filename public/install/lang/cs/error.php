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

$string['cannotcreatedboninstall'] = '<p>Nelze vytvořit databázi.</p>
<p>Určená databáze neexistuje a uživatel nemá oprávnění k vytvoření databáze.</p>
<p>Správce stránek by měl ověřit konfiguraci databáze.</p>';
$string['cannotcreatelangdir'] = 'Nelze vytvořit adresář pro jazykové soubory';
$string['cannotcreatetempdir'] = 'Nelze vytvořit dočasný adresář';
$string['cannotdownloadcomponents'] = 'Nelze stáhnout komponenty';
$string['cannotdownloadzipfile'] = 'Nelze stáhnout soubor ZIP';
$string['cannotfindcomponent'] = 'Komponenta nenalezena';
$string['cannotsavemd5file'] = 'Nelze uložit soubor MD5';
$string['cannotsavezipfile'] = 'Nelze uložit soubor ZIP';
$string['cannotunzipfile'] = 'Nelze dekomprimovat soubor';
$string['componentisuptodate'] = 'Komponenta je aktuální';
$string['dmlexceptiononinstall'] = '<p>Došlo k chybě databáze [{$a->errorcode}].<br />{$a->debuginfo}</p>';
$string['downloadedfilecheckfailed'] = 'Selhala kontrola staženého souboru';
$string['invalidmd5'] = 'Ověření selhalo - zkuste to znovu';
$string['missingrequiredfield'] = 'Chybí některé z povinných polí';
$string['remotedownloaderror'] = '<p>Stahování komponenty na váš server selhalo. Prověřte nastavení proxy. Vřele doporučujeme PHP rozšíření cURL.</p>
<p>Nyní musíte stáhnout soubor <a href="{$a->url}">{$a->url}</a> ručně, překopírovat jej do "{$a->dest}" na vašem serveru a tam jej rozbalit.</p>';
$string['wrongdestpath'] = 'Chybné cílové umístění';
$string['wrongsourcebase'] = 'Chybné URL zdrojového serveru';
$string['wrongzipfilename'] = 'Chybné jméno souboru ZIP';
