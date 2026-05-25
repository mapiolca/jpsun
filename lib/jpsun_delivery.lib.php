<?php
/* Copyright (C) 2026	Pierre Ardoin		<developpeur@lesmetiersdubatiment.fr>
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
 * Get the native Dolibarr availability/delivery delay id from a proposal-like object.
 *
 * @param	object	$object	Proposal object
 * @return	int				Availability id, 0 if empty
 */
function jpsunGetPropalAvailabilityId($object)
{
	foreach (array('availability_id', 'fk_availability') as $property) {
		if (isset($object->{$property}) && (int) $object->{$property} > 0) {
			return (int) $object->{$property};
		}
	}

	return 0;
}

/**
 * Get the native Dolibarr delivery date from a proposal-like object.
 *
 * @param	object	$object	Proposal object
 * @return	int				Delivery date timestamp, 0 if empty
 */
function jpsunGetPropalDeliveryDate($object)
{
	if (!isset($object->delivery_date) || empty($object->delivery_date)) {
		return 0;
	}

	if (is_numeric($object->delivery_date)) {
		return (int) $object->delivery_date;
	}

	$timestamp = strtotime((string) $object->delivery_date);
	return ($timestamp !== false && $timestamp > 0 ? $timestamp : 0);
}

/**
 * Load and parse a Dolibarr availability delay into a duration spec.
 *
 * @param	DoliDB		$db					Database handler
 * @param	int			$availabilityId		Availability id
 * @param	Translate	$langs				Language handler
 * @return	array{result:int,quantity?:int,unit?:string,raw?:string,code?:string,label?:string,error?:string}
 */
function jpsunGetAvailabilityDurationSpec($db, $availabilityId, $langs = null)
{
	$availabilityId = (int) $availabilityId;
	if ($availabilityId <= 0) {
		return array('result' => 0, 'error' => 'Missing availability id');
	}

	$sql = "SELECT rowid, code, label";
	$sql .= " FROM ".MAIN_DB_PREFIX."c_availability";
	$sql .= " WHERE rowid = ".$availabilityId;
	$resql = $db->query($sql);
	if (!$resql) {
		return array('result' => -1, 'error' => $db->lasterror());
	}

	$obj = $db->fetch_object($resql);
	if (!$obj) {
		return array('result' => 0, 'error' => 'Availability not found');
	}

	if (strtoupper(trim((string) $obj->code)) === 'AV_NOW') {
		return array(
			'result' => 1,
			'quantity' => 0,
			'unit' => 'day',
			'mode' => 'from_signature',
			'raw' => 'AV_NOW',
			'code' => (string) $obj->code,
			'label' => (string) $obj->label
		);
	}

	$candidates = array();
	foreach (array($obj->code, $obj->label) as $candidate) {
		$candidate = trim((string) $candidate);
		if ($candidate !== '') {
			$candidates[] = $candidate;
		}
	}

	if (is_object($langs)) {
		foreach (array($obj->code, $obj->label) as $candidate) {
			$candidate = trim((string) $candidate);
			if ($candidate === '') {
				continue;
			}
			$translated = $langs->transnoentitiesnoconv($candidate);
			if ($translated !== $candidate) {
				$candidates[] = $translated;
			}
			$availabilityTranslated = $langs->transnoentitiesnoconv('AvailabilityType'.$candidate);
			if ($availabilityTranslated !== 'AvailabilityType'.$candidate) {
				$candidates[] = $availabilityTranslated;
			}
		}
	}

	$candidates = array_values(array_unique($candidates));
	foreach ($candidates as $candidate) {
		$duration = jpsunParseAvailabilityDuration($candidate);
		if ($duration['result'] > 0) {
			$duration['raw'] = $candidate;
			$duration['code'] = (string) $obj->code;
			$duration['label'] = (string) $obj->label;
			return $duration;
		}
	}

	return array(
		'result' => -2,
		'code' => (string) $obj->code,
		'label' => (string) $obj->label,
		'error' => 'Unable to parse availability duration'
	);
}

/**
 * Parse a human readable availability duration.
 *
 * @param	string	$value	Duration text, for example "3 mois", "12 semaines", "10 days" or "4weeks"
 * @return	array{result:int,quantity?:int,unit?:string}
 */
function jpsunParseAvailabilityDuration($value)
{
	$value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8');
	$value = strtolower(trim($value));
	$value = str_replace(array('_', '-'), ' ', $value);
	$value = preg_replace('/\s+/', ' ', $value);

	if (!preg_match('/([0-9]+)\s*([a-z]+)/', $value, $matches)) {
		return array('result' => 0);
	}

	$quantity = (int) $matches[1];
	$unitToken = $matches[2];
	if ($quantity <= 0) {
		return array('result' => 0);
	}

	$unit = '';
	if ($unitToken === 'd' || $unitToken === 'j' || strpos($unitToken, 'day') === 0 || strpos($unitToken, 'jour') === 0 || strpos($unitToken, 'giorn') === 0 || strpos($unitToken, 'dia') === 0 || strpos($unitToken, 'tag') === 0) {
		$unit = 'day';
	} elseif ($unitToken === 'w' || strpos($unitToken, 'week') === 0 || strpos($unitToken, 'semaine') === 0 || strpos($unitToken, 'seman') === 0 || strpos($unitToken, 'settiman') === 0 || strpos($unitToken, 'woch') === 0) {
		$unit = 'week';
	} elseif ($unitToken === 'm' || strpos($unitToken, 'month') === 0 || strpos($unitToken, 'mois') === 0 || strpos($unitToken, 'mes') === 0 || strpos($unitToken, 'monat') === 0) {
		$unit = 'month';
	} elseif ($unitToken === 'y' || $unitToken === 'an' || $unitToken === 'ans' || strpos($unitToken, 'year') === 0 || strpos($unitToken, 'anne') === 0 || strpos($unitToken, 'anno') === 0 || strpos($unitToken, 'jahr') === 0) {
		$unit = 'year';
	}

	if ($unit === '') {
		return array('result' => 0);
	}

	return array(
		'result' => 1,
		'quantity' => $quantity,
		'unit' => $unit
	);
}

/**
 * Add a parsed availability duration to a base date.
 *
 * @param	int		$baseTimestamp	Base timestamp
 * @param	array	$durationSpec	Duration spec from jpsunGetAvailabilityDurationSpec()
 * @return	int						Result date at local midnight
 */
function jpsunAddAvailabilityDurationToDate($baseTimestamp, $durationSpec)
{
	$quantity = (int) ($durationSpec['quantity'] ?? 0);
	$unit = (string) ($durationSpec['unit'] ?? '');
	if ($quantity < 0 || !in_array($unit, array('day', 'week', 'month', 'year'), true)) {
		return 0;
	}

	$base = dol_getdate($baseTimestamp > 0 ? $baseTimestamp : dol_now());
	$date = new DateTimeImmutable(sprintf('%04d-%02d-%02d 12:00:00', $base['year'], $base['mon'], $base['mday']));
	$modifyUnit = array(
		'day' => 'days',
		'week' => 'weeks',
		'month' => 'months',
		'year' => 'years'
	);
	if ($quantity > 0) {
		$date = $date->modify('+'.$quantity.' '.$modifyUnit[$unit]);
	}

	return dol_mktime(0, 0, 0, (int) $date->format('n'), (int) $date->format('j'), (int) $date->format('Y'));
}

/**
 * Build a business-day project window around a delivery date.
 *
 * @param	int	$deliveryTimestamp	Delivery timestamp
 * @param	int	$workdays			Number of business days in the window
 * @return	array{date_start:int,date_end:int,pivot:int}
 */
function jpsunBuildBusinessDayWindowAroundDate($deliveryTimestamp, $workdays)
{
	$workdays = max(1, (int) $workdays);
	$pivot = jpsunMoveToNextBusinessDay($deliveryTimestamp);
	$daysBefore = (int) floor($workdays / 2);
	$daysAfter = $workdays - $daysBefore - 1;

	return array(
		'date_start' => jpsunShiftBusinessDays($pivot, -$daysBefore),
		'date_end' => jpsunShiftBusinessDays($pivot, $daysAfter),
		'pivot' => $pivot,
		'calendar_source' => jpsunGetBusinessCalendarSource()
	);
}

/**
 * Build a forward business-day project window from a signature date.
 *
 * @param	int	$signatureTimestamp	Signature timestamp
 * @param	int	$workdays			Number of business days in the window
 * @return	array{date_start:int,date_end:int,pivot:int}
 */
function jpsunBuildBusinessDayWindowFromDate($signatureTimestamp, $workdays)
{
	$workdays = max(1, (int) $workdays);
	$pivot = jpsunMoveToNextBusinessDay($signatureTimestamp);

	return array(
		'date_start' => $pivot,
		'date_end' => jpsunShiftBusinessDays($pivot, $workdays - 1),
		'pivot' => $pivot,
		'calendar_source' => jpsunGetBusinessCalendarSource()
	);
}

/**
 * Move a timestamp to the next business day if it falls on a non-working day.
 *
 * @param	int	$timestamp	Timestamp
 * @return	int				Business day timestamp at local midnight
 */
function jpsunMoveToNextBusinessDay($timestamp)
{
	$timestamp = jpsunNormalizeDateTimestamp($timestamp);
	while (!jpsunIsBusinessDay($timestamp)) {
		$timestamp = jpsunShiftCalendarDays($timestamp, 1);
	}

	return $timestamp;
}

/**
 * Shift a timestamp by a number of business days.
 *
 * @param	int	$timestamp	Timestamp
 * @param	int	$offset		Business day offset
 * @return	int				Shifted timestamp at local midnight
 */
function jpsunShiftBusinessDays($timestamp, $offset)
{
	$timestamp = jpsunNormalizeDateTimestamp($timestamp);
	$offset = (int) $offset;
	$step = ($offset < 0 ? -1 : 1);
	$remaining = abs($offset);

	while ($remaining > 0) {
		$timestamp = jpsunShiftCalendarDays($timestamp, $step);
		if (jpsunIsBusinessDay($timestamp)) {
			$remaining--;
		}
	}

	return $timestamp;
}

/**
 * Check if a timestamp is a business day according to Dolibarr configuration.
 *
 * @param	int	$timestamp	Timestamp
 * @return	bool			True for business days
 */
function jpsunIsBusinessDay($timestamp)
{
	$weekday = (int) date('N', $timestamp);
	$calendarSource = jpsunGetBusinessCalendarSource();

	if ($calendarSource === 'opening_hours') {
		return (trim(getDolGlobalString(jpsunGetOpeningHoursConstantForWeekday($weekday))) !== '');
	}

	if ($calendarSource === 'holiday_non_working_days') {
		return !getDolGlobalInt(jpsunGetNonWorkingDayConstantForWeekday($weekday), jpsunGetDefaultNonWorkingDayValue($weekday));
	}

	return ($weekday >= 1 && $weekday <= 5);
}

/**
 * Get the active source for business-day calculation.
 *
 * @return	string	Source code
 */
function jpsunGetBusinessCalendarSource()
{
	if (jpsunHasOpeningHoursConfiguration()) {
		return 'opening_hours';
	}

	if (function_exists('isModEnabled') && isModEnabled('holiday') && jpsunHolidayCalendarHasBusinessDay()) {
		return 'holiday_non_working_days';
	}

	return 'default_weekdays';
}

/**
 * Check if at least one opening-hours constant is configured.
 *
 * @return	bool	True if opening hours are configured
 */
function jpsunHasOpeningHoursConfiguration()
{
	for ($weekday = 1; $weekday <= 7; $weekday++) {
		if (trim(getDolGlobalString(jpsunGetOpeningHoursConstantForWeekday($weekday))) !== '') {
			return true;
		}
	}

	return false;
}

/**
 * Check if the holiday non-working day configuration leaves at least one business day.
 *
 * @return	bool	True if at least one business day exists
 */
function jpsunHolidayCalendarHasBusinessDay()
{
	for ($weekday = 1; $weekday <= 7; $weekday++) {
		if (!getDolGlobalInt(jpsunGetNonWorkingDayConstantForWeekday($weekday), jpsunGetDefaultNonWorkingDayValue($weekday))) {
			return true;
		}
	}

	return false;
}

/**
 * Get the opening-hours constant name for a weekday.
 *
 * @param	int	$weekday	ISO weekday number, Monday=1
 * @return	string			Global constant name
 */
function jpsunGetOpeningHoursConstantForWeekday($weekday)
{
	$suffixes = array(
		1 => 'MONDAY',
		2 => 'TUESDAY',
		3 => 'WEDNESDAY',
		4 => 'THURSDAY',
		5 => 'FRIDAY',
		6 => 'SATURDAY',
		7 => 'SUNDAY'
	);

	$weekday = max(1, min(7, (int) $weekday));
	return 'MAIN_INFO_OPENINGHOURS_'.$suffixes[$weekday];
}

/**
 * Get the non-working day constant name for a weekday.
 *
 * @param	int	$weekday	ISO weekday number, Monday=1
 * @return	string			Global constant name
 */
function jpsunGetNonWorkingDayConstantForWeekday($weekday)
{
	$suffixes = array(
		1 => 'MONDAY',
		2 => 'TUESDAY',
		3 => 'WEDNESDAY',
		4 => 'THURSDAY',
		5 => 'FRIDAY',
		6 => 'SATURDAY',
		7 => 'SUNDAY'
	);

	$weekday = max(1, min(7, (int) $weekday));
	return 'MAIN_NON_WORKING_DAYS_INCLUDE_'.$suffixes[$weekday];
}

/**
 * Get the default non-working value for a weekday.
 *
 * @param	int	$weekday	ISO weekday number, Monday=1
 * @return	int				1 for non-working, 0 for working
 */
function jpsunGetDefaultNonWorkingDayValue($weekday)
{
	$weekday = (int) $weekday;
	return ($weekday === 6 || $weekday === 7 ? 1 : 0);
}

/**
 * Shift a local date by calendar days.
 *
 * @param	int	$timestamp	Timestamp
 * @param	int	$days		Number of days
 * @return	int				Shifted timestamp at local midnight
 */
function jpsunShiftCalendarDays($timestamp, $days)
{
	$parts = dol_getdate($timestamp);
	$date = new DateTimeImmutable(sprintf('%04d-%02d-%02d 12:00:00', $parts['year'], $parts['mon'], $parts['mday']));
	$date = $date->modify(($days >= 0 ? '+' : '').((int) $days).' days');

	return dol_mktime(0, 0, 0, (int) $date->format('n'), (int) $date->format('j'), (int) $date->format('Y'));
}

/**
 * Normalize a timestamp to local midnight.
 *
 * @param	int	$timestamp	Timestamp
 * @return	int				Local midnight timestamp
 */
function jpsunNormalizeDateTimestamp($timestamp)
{
	$parts = dol_getdate($timestamp > 0 ? $timestamp : dol_now());
	return dol_mktime(0, 0, 0, $parts['mon'], $parts['mday'], $parts['year']);
}
