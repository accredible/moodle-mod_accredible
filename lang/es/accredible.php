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
$string['brandlabel'] = 'Marca';
$string['brandglobal'] = 'Cuenta global';
$string['branddescription'] = 'Cuenta de Accredible contra la que emite esta actividad. Se preselecciona según la categoría del curso; cámbiala y recarga para elegir un grupo de esa cuenta.';
$string['brandreload'] = 'Recargar los grupos de esta marca';
$string['brandgroupmismatch'] = 'Has cambiado la marca: elige un grupo de la nueva cuenta.';
$string['brandeulabel'] = 'Marca {$a}: servidor UE (Frankfurt)';
$string['brandeuhelp'] = 'Selecciona si los datos de esta marca se alojan en la UE (Frankfurt) en lugar de en EE.UU.';
