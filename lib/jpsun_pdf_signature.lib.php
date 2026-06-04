<?php
/* Copyright (C) 2026	Pierre Ardoin	<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Helpers for JPSUN PDF signature placement.
 *
 * @file		lib/jpsun_pdf_signature.lib.php
 * @ingroup		jpsun
 */

/**
 * Return the JPSUN PRO signature layout.
 *
 * @param	float	$pagewidth		Page width
 * @param	float	$pageheight		Page height
 * @param	float|null	$leftmargin	Left margin
 * @param	float|null	$rightmargin	Right margin
 * @return	array<string,float>
 */
function jpsunPdfJpsunProSignatureLayout($pagewidth, $pageheight, $leftmargin = null, $rightmargin = null)
{
	$pagewidth = (float) $pagewidth;
	$pageheight = (float) $pageheight;
	$leftmargin = ($leftmargin === null ? (function_exists('getDolGlobalInt') ? (float) getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10) : 10.0) : (float) $leftmargin);
	$rightmargin = ($rightmargin === null ? (function_exists('getDolGlobalInt') ? (float) getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10) : 10.0) : (float) $rightmargin);

	$gap = 8.0;
	$boxw = max(70.0, ($pagewidth - $leftmargin - $rightmargin - $gap) / 2);
	$boxh = 72.0;
	$boxy = 82.0;
	if ($pageheight > 0 && $boxy + $boxh > $pageheight - 25.0) {
		$boxy = max(40.0, $pageheight - 25.0 - $boxh);
	}

	$clientx = $leftmargin + $boxw + $gap;
	$innerx = $clientx + 4.0;
	$innerw = $boxw - 8.0;
	$signaturew = min(66.0, $innerw);
	$signatureh = $signaturew / 4.0;
	$signaturey = $boxy + 45.0;

	return array(
		'provider_box_x' => $leftmargin,
		'client_box_x' => $clientx,
		'box_y' => $boxy,
		'box_w' => $boxw,
		'box_h' => $boxh,
		'client_signature_x' => $innerx,
		'client_signature_y' => $signaturey,
		'client_signature_w' => $signaturew,
		'client_signature_h' => $signatureh,
		'client_signature_label_x' => $innerx,
		'client_signature_label_y' => $signaturey + $signatureh + 1.0,
		'client_signature_label_w' => $innerw,
	);
}
