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

$string['cannotcreatedboninstall'] = '<p>Nie można utworzyć bazy danych.</p> <p>Określona baza danych nie istnieje, a dany użytkownik nie ma uprawnień do tworzenia bazy danych.</p> <p>Administrator strony powinien sprawdzić konfigurację bazy danych.</p>';
$string['cannotcreatelangdir'] = 'Nie można utworzyć katalogu lang/';
$string['cannotcreatetempdir'] = 'Nie można utworzyć katalogu tymczasowego';
$string['cannotdownloadcomponents'] = 'Nie można pobrać składowych (komponentów)';
$string['cannotdownloadzipfile'] = 'Nie można pobrać pliku ZIP';
$string['cannotfindcomponent'] = 'Nie można znaleźć komponentu';
$string['cannotsavemd5file'] = 'Nie można zapisać pliku md5';
$string['cannotsavezipfile'] = 'Nie można zapisać pliku ZIP';
$string['cannotunzipfile'] = 'Nie można rozpakować (unzip) pliku';
$string['componentisuptodate'] = 'Komponent jest aktualny';
$string['dmlexceptiononinstall'] = '<p> Wystąpił błąd bazy danych [{$a->errorcode}]. <br /> {$a->debuginfo} </p>';
$string['downloadedfilecheckfailed'] = 'Sprawdzenie pobranego pliku zakończyło się niepowodzeniem';
$string['invalidmd5'] = 'Niewłaściwy md5';
$string['missingrequiredfield'] = 'Brak wymaganego pola';
$string['remotedownloaderror'] = 'Pobieranie składnika na serwer nie powiodło się. Sprawdź ustawienia proxy. Rozszerzenie PHP cURL jest bardzo zalecane. <br /><br />Musisz pobrać następująćy plik <a href="{$a->url}">{$a->url}</a> ręcznie, skopiować go do lokalizacji "{$a->dest}" i rozpakować poleceniem unzip.';
$string['wrongdestpath'] = 'Błędna ścieżka docelowa';
$string['wrongsourcebase'] = 'Błędne źródło bazy URL';
$string['wrongzipfilename'] = 'Błędna nazwa pliku ZIP';
