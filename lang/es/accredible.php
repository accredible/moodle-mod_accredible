<?php
// This file is part of the Accredible Certificate module for Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish language strings for the accredible module.
 *
 * Only the multi-brand settings are translated here. Every other string keeps
 * falling back to the standard Spanish language pack, or to English when the
 * pack does not cover it.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.LangFilesOrdering.IncorrectOrder

$string['brandsheading'] = 'Marcas';
$string['brandsheadinghelp'] = 'Configura una cuenta de Accredible por marca. Las actividades seleccionan una marca y se emiten contra esa cuenta. Deja un slot vacío para desactivarlo; las actividades sin marca usan el código API de arriba.';
$string['brandnamelabel'] = 'Marca {$a}: nombre';
$string['brandnamehelp'] = 'Nombre mostrado al seleccionar una marca en la actividad. Déjalo vacío para desactivar este slot.';
$string['brandapikeylabel'] = 'Marca {$a}: código API';
$string['brandapikeyhelp'] = 'Código API de la cuenta de Accredible de esta marca.';
$string['groupnoselection'] = 'Ningún curso seleccionado';
$string['groupsearchplaceholder'] = 'Buscar curso…';
$string['groupsearchhelp'] = 'Solo se listan los grupos de la marca seleccionada ({$a} disponibles). Escribe parte del nombre para filtrarlos.';
$string['brandlabel'] = 'Marca';
$string['brandchoose'] = 'Elige una marca';
$string['branddescription'] = 'Cuenta de Accredible contra la que emite esta actividad. Se preselecciona según la categoría del curso; cámbiala y recarga para elegir un grupo de esa cuenta.';
$string['brandreload'] = 'Recargar los grupos de esta marca';
$string['brandgroupmismatch'] = 'Has cambiado la marca: elige un grupo de la nueva cuenta.';
$string['brandrequired'] = 'Elige una marca: la actividad emite contra su cuenta de Accredible.';
$string['brandunresolved'] = 'La categoría de este curso no indica ninguna marca, así que no se ha podido preseleccionar. Elige una y recarga para ver sus grupos.';
$string['brandmissing'] = 'No se ha indicado ninguna marca, y no existe una cuenta de Accredible por defecto.';
$string['brandunknown'] = 'La marca «{$a}» no está configurada en los ajustes del plugin.';
$string['brandwithoutkey'] = 'La marca «{$a}» no tiene código API configurado.';
$string['nobrandsconfigured'] = 'No hay ninguna marca de Accredible configurada. Añade al menos una en los ajustes del plugin antes de crear esta actividad.';
$string['brandeulabel'] = 'Marca {$a}: servidor UE (Frankfurt)';
$string['brandeuhelp'] = 'Selecciona si los datos de esta marca se alojan en la UE (Frankfurt) en lugar de en EE.UU.';
