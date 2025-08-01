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

$string['admindirname'] = 'Directorio Admin';
$string['availablelangs'] = 'Lista de idiomas dispoñíbeis';
$string['chooselanguagehead'] = 'Escolla un idioma';
$string['chooselanguagesub'] = 'Escolla un idioma para o proceso de instalación. Este idioma empregarase tamén como idioma predeterminado do sitio, malia que pode cambiarse máis adiante.';
$string['clialreadyconfigured'] = 'O ficheiro de configuración config.php xa existe. Use admin/cli/install_database.php para instalar Moodle para este sitio.';
$string['clialreadyinstalled'] = 'O ficheiro de configuración config.php xa existe. Use admin/cli/install_database.php para actualizar Moodle para este sitio.';
$string['cliinstallheader'] = 'Programa de instalación de Moodle en liña de ordes {$a}';
$string['clitablesexist'] = 'Xa existen táboas de base de datos, a instalación CLI non pode continuar.';
$string['databasehost'] = 'Enderezo/máquina da base de datos';
$string['databasename'] = 'Nome de base de datos';
$string['databasetypehead'] = 'Escolla o controlador da base de datos';
$string['dataroot'] = 'Directorio de datos';
$string['datarootpermission'] = 'Permisos dos directorios de datos';
$string['dbprefix'] = 'Prefixo das táboas';
$string['dirroot'] = 'Directorio de Moodle';
$string['environmenthead'] = 'Comprobando o seu contorno…';
$string['environmentsub2'] = 'Cada versión de Moodle ten algún requisito mínimo da versión de PHP e un número obrigatorio de extensións de PHP.
Antes de cada instalación ou actualización faise unha comprobación completa do contorno. Póñase en contacto co administrador do servidor se non sabe como instalar a nova versión ou activar as extensións PHP.';
$string['errorsinenvironment'] = 'A comprobación do contorno no foi satisfactoria!';
$string['installation'] = 'Instalación';
$string['langdownloaderror'] = 'Non foi posíbel descargar o idioma «{$a}». O proceso de instalación continuará en inglés.';
$string['paths'] = 'Rutas';
$string['pathserrcreatedataroot'] = 'O instalador non pode crear o directorio de datos ({$a->dataroot}).';
$string['pathshead'] = 'Confirme as rutas';
$string['pathsrodataroot'] = 'O directorio dataroot non ten permisos de escritura.';
$string['pathsroparentdataroot'] = 'O directorio principal ({$a->parent}) non ten permisos de escritura. O instalador non pode crear o directorio de datos ({$a->dataroot}).';
$string['pathssubadmindir'] = 'Moi poucos enderezos web empregan /admin como URL especial para
permitirlle acceder a un panel de control ou semellante. Desafortunadamente, isto entra en conflito coa localización estándar das páxinas de administración de Moodle. Vostede pode corrixir isto
renomeando o directorio admin na súa instalación, e poñendo aquí ese novo nome.  Por exemplo: <em>moodleadmin</em>. Iso corrixirá as ligazóns admin en Moodle.';
$string['pathssubdataroot'] = '<p>Un directorio onde Moodle almacenará todo o contido de ficheiros enviados polos usuarios.</p>
<p>Este directorio debería ser lexíbel e escribíbel polo usuario do servidor web (normalmente «www-data«» «ninguén» ou «apache»).</p>
<p> Non debe ser accesíbel directamente na web. </p>
<p> Se o directorio non existe actualmente, o proceso de instalación tentará crealo. </p>';
$string['pathssubdirroot'] = 'Ruta completa do directorio de instalación de Moodle.';
$string['pathssubwwwroot'] = '<p>O enderezo completo onde se accederá a Moodle, é dicir, o enderezo que os usuarios introducirán na barra de enderezos do seu navegador para acceder a Moodle.</p>
<p>Non é posíbel acceder a Moodle con varias direccións. Se o seu sitio é accesíbel a través de varios enderezos, escolla o máis sinxelo e configure unha redirección permanente para cada un dos outros enderezos.</p>
<p Se o seu sitio é accesíbel tanto dende a Internet como dende unha rede interna (ás veces chamada Intranet), entón use o enderezo público aquí.</p>
<p>Se o enderezo actual non é correcto, cambie o URL na barra de enderezos do seu navegador e reinicie a instalación.</p>';
$string['pathsunsecuredataroot'] = 'A localización de dataroot non é segura';
$string['pathswrongadmindir'] = 'Non existe o directorio Admin';
$string['phpextension'] = 'Extensión PHP {$a}';
$string['phpversion'] = 'Versión PHP';
$string['welcomep10'] = '{$a->installername} ({$a->installerversion})';
$string['welcomep20'] = 'Se esta a ver esta páxina é porque puido instalar correctamente e
    executar o paquete <strong>{$a->packname} {$a->packversion}</strong> no seu computador. Parabéns!';
$string['welcomep30'] = 'Esta versión de <strong>{$a->installername}</strong> inclúe as aplicacións
    para crear un contorno no que <strong>Moodle</strong> funcione, nomeadamente:';
$string['welcomep40'] = 'O paquete inclúe tamen <strong>Moodle {$a->moodlerelease} ({$a->moodleversion})</strong>.';
$string['welcomep50'] = 'O uso de todas as aplicacións deste paquete está rexido polas súas respectivas licenzas. O paquete completo <strong>{$a->installername}</strong> é de <a href="https://www.opensource.org/docs/definition_plain.html">código aberto</a> e distribúese baixo a licenza <a href="https://www.gnu.org/copyleft/gpl.html">GPL</a>.';
$string['welcomep60'] = 'As páxinas seguintes guiarano a través de algúns sinxelos pasos para configurar
    e axustar <strong>Moodle</strong> no seu computador. Pode empregar os axustes predeterminados
    ou, opcionalmente, modificalos para que se axusten ás súas necesidades.';
$string['welcomep70'] = 'Prema no botón «Seguinte» para continuar coa configuración de <strong>Moodle</strong>.';
$string['wwwroot'] = 'Enderezo web';
