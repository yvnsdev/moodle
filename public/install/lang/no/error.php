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

$string['cannotcreatedboninstall'] = '<p>Kan ikke opprette databasen.</p>
<p>Den angitte databasen eksisterer ikke og oppgitt bruker har ikke rettigheter til å opprette databasen.</p>
<p>Portaladministratoren må derfor verifisere databaseoppsettet.</p>';
$string['cannotcreatelangdir'] = 'Kan ikke opprette mappen \'lang\'.';
$string['cannotcreatetempdir'] = 'Kan ikke opprette mappen \'temp';
$string['cannotdownloadcomponents'] = 'Kan ikke laste ned komponentene.';
$string['cannotdownloadzipfile'] = 'Kan ikke laste ned ZIP-fil.';
$string['cannotfindcomponent'] = 'Kan ikke finne komponenten.';
$string['cannotsavemd5file'] = 'Kan ikke lagre md5-fil.';
$string['cannotsavezipfile'] = 'Kan ikke lagre ZIP-fil.';
$string['cannotunzipfile'] = 'Kan ikke pakke opp filen.';
$string['componentisuptodate'] = 'Komponenten er oppdatert';
$string['dmlexceptiononinstall'] = '<p>Det oppstod en databasefeil [{$a->errorcode}].<br />{$a->debuginfo}</p>';
$string['downloadedfilecheckfailed'] = 'Sjekk av nedlastet fil mislykkes.';
$string['invalidmd5'] = 'Ugyldig md5, prøv igjen';
$string['missingrequiredfield'] = 'Noen påkrevde felt mangler';
$string['remotedownloaderror'] = '<p>Mislykkes i å laste ned komponenten til din server, vennligst sjekk proxy-innstillingene. PHP cURL tillegget er sterkt anbefalt. </p>
<p>Du må laste ned <a href="{$a->url}">{$a->url}</a> filen manuelt, kopiere den til "{$a->dest}" på serveren din og pakke den ut der.</p>';
$string['wrongdestpath'] = 'Gal målmappe';
$string['wrongsourcebase'] = 'Feil kilde URL base';
$string['wrongzipfilename'] = 'Galt ZIP-filnavn.';
