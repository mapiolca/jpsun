<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Proposal cover metadata and safe document ownership. No session dependency. */
class JpsunProposalCover
{
	public const MODEL = 'jpsunwithoutpicturescover';
	public const COLORS = array('JPSUN_COVER_ACCENT' => '#007D96', 'JPSUN_COVER_TEXT' => '#071C2C', 'JPSUN_COVER_BACKGROUND' => '#EAF3F5');
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';

	/** @param DoliDB $db Database */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/** @return bool Native model activation is per entity. */
	public function isEnabled(int $entity): bool
	{
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."document_model WHERE type = 'propal' AND nom = '".self::MODEL."' AND entity = ".$entity;
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = 'JpsunCoverReadError';
			return false;
		}
		$enabled = $this->db->num_rows($res) > 0;
		$this->db->free($res);
		return $enabled;
	}

	/** @return bool Required to re-encode all accepted images, including mirrored EXIF. */
	public static function canUpload(): bool
	{
		return extension_loaded('gd') && function_exists('exif_read_data') && class_exists('finfo');
	}

	/**
	 * Read stored cover facts for a shared proposal, independently of viewer field definitions.
	 * Native fetch_optionals() only loads definitions in the authenticated entity (v20+).
	 * Caller must already have checked native proposal access. No computed field is evaluated.
	 * @param Propal $object Authorized proposal
	 * @return bool
	 */
	public function loadOwnerValues($object): bool
	{
		global $conf;
		if ((int) $object->entity === (int) $conf->entity) {
			return true;
		}
		if ((int) $object->id <= 0 || (int) $object->entity <= 0) {
			$this->error = 'JpsunCoverReadError';
			return false;
		}
		$keys = array('jpsun_cover_image', 'jpsun_cover_caption', 'powerplantpv_consumption_type', 'powerplantpv_peak_power', 'powerplantpv_storage_capacity');
		$sql = "SELECT name FROM ".MAIN_DB_PREFIX."extrafields WHERE elementtype = 'propal' AND entity IN (0, ".((int) $object->entity).")";
		$sql .= " AND name IN ('".implode("','", $keys)."')";
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = 'JpsunCoverReadError';
			return false;
		}
		$columns = array();
		while (is_object($field = $this->db->fetch_object($res))) {
			if (in_array($field->name, $keys, true)) {
				$columns[$field->name] = 'e.'.$this->db->sanitize($field->name);
			}
		}
		$this->db->free($res);
		foreach ($keys as $key) {
			unset($object->array_options['options_'.$key]);
		}
		if (!$columns) {
			return true;
		}
		$sql = 'SELECT '.implode(', ', $columns).' FROM '.MAIN_DB_PREFIX.'propal p';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'propal_extrafields e ON e.fk_object = p.rowid';
		$sql .= ' WHERE p.rowid = '.((int) $object->id).' AND p.entity = '.((int) $object->entity);
		$res = $this->db->query($sql);
		$row = $res ? $this->db->fetch_object($res) : false;
		if ($res) {
			$this->db->free($res);
		}
		if (!is_object($row)) {
			$this->error = 'JpsunCoverReadError';
			return false;
		}
		foreach ($columns as $key => $column) {
			$object->array_options['options_'.$key] = $row->{$key};
		}
		return true;
	}

	/**
	 * @param Propal $object Loaded, authorized proposal
	 * @return string|false
	 */
	public function directory($object)
	{
		global $conf;
		$entity = (int) $object->entity;
		if ((int) $object->id <= 0 || $entity <= 0 || empty($conf->propal->multidir_output[$entity]) || !is_string($conf->propal->multidir_output[$entity])) {
			$this->error = 'JpsunCoverDirectoryError';
			return false;
		}
		$base = getMultidirOutput($object, 'propal', 1);
		if (!is_string($base) || $base === '' || strpos($base, 'error-') === 0) {
			$this->error = 'JpsunCoverDirectoryError';
			return false;
		}
		// Resolve every existing ancestor: a symlink must never leave the owner directory.
		$root = realpath($conf->propal->multidir_output[$entity]);
		$ref = dol_sanitizeFileName($object->ref);
		if (!$root || $ref === '' || $ref === '.' || $ref === '..' || strpbrk($ref, '/\\') !== false) {
			$this->error = 'JpsunCoverDirectoryError';
			return false;
		}
		$dir = rtrim($base, '/\\');
		if (!self::inside($dir, $root)) {
			$this->error = 'JpsunCoverDirectoryError';
			return false;
		}
		foreach (array($dir, $dir.'/jpsun-cover') as $path) {
			if (is_link($path) || (file_exists($path) && (!is_dir($path) || !self::inside((string) realpath($path), $root)))) {
				$this->error = 'JpsunCoverDirectoryError';
				return false;
			}
		}
		return $dir.'/jpsun-cover';
	}

	private static function inside(string $path, string $root): bool
	{
		return strpos(str_replace('\\', '/', $path).'/', rtrim(str_replace('\\', '/', $root), '/').'/') === 0;
	}

	/**
	 * @param Propal $object Authorized proposal
	 * @return string|false Empty means no illustration.
	 */
	public function image($object)
	{
		$name = (string) ($object->array_options['options_jpsun_cover_image'] ?? '');
		if ($name === '') {
			return '';
		}
		if (!preg_match('/^[a-f0-9]{32}\.png$/D', $name)) {
			$this->error = 'JpsunCoverDirectoryError';
			return false;
		}
		$dir = $this->directory($object);
		$path = $dir ? $dir.'/'.$name : '';
		if (!$dir || is_link($path) || !is_file($path) || !self::inside((string) realpath($path), $dir)) {
			$this->error = 'JpsunCoverImageMissing';
			return false;
		}
		return $path;
	}

	/**
	 * Re-encode pixels only. Caller handles authorization, upload provenance and persistence.
	 * EXIF flips are not covered by the native orientation helper in the v20 baseline.
	 * @return bool Success
	 */
	public function cleanImage(string $source, string $destination): bool
	{
		if (!self::canUpload()) {
			$this->error = 'JpsunCoverImageLibraries';
			return false;
		}
		$size = @getimagesize($source);
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime = @$finfo->file($source);
		if (!$size || !in_array($mime, array('image/jpeg', 'image/png'), true) || $mime !== $size['mime'] || $size[0] <= 0 || $size[1] <= 0 || $size[0] * $size[1] > 24000000) {
			$this->error = 'JpsunCoverInvalidImage';
			return false;
		}
		// Decode + rotation can hold several true-color buffers at once. Refuse before
		// allocating them rather than losing the request to an uncatchable memory error.
		$memory = trim((string) ini_get('memory_limit'));
		if (preg_match('/^(\d+)\s*([KMG]?)$/iD', $memory, $limit)) {
			$units = array('' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824);
			$available = (float) $limit[1] * $units[strtoupper($limit[2])] - memory_get_usage(true);
			if ($size[0] * $size[1] * 12 + 16777216 > $available) {
				$this->error = 'JpsunCoverInvalidImage';
				return false;
			}
		}
		require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
		$exif = $mime === 'image/jpeg' ? @exif_read_data($source) : false;
		$orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
		$img = in_array($orientation, array(3, 6, 8), true)
			? correctExifImageOrientation($source, false)
			: ($mime === 'image/jpeg' ? @imagecreatefromjpeg($source) : @imagecreatefrompng($source));
		if (!$img) {
			$this->error = 'JpsunCoverInvalidImage';
			return false;
		}

		if (in_array($orientation, array(2, 4, 5, 7), true)) {
			imageflip($img, in_array($orientation, array(4, 5), true) ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
		}
		$angle = array(5 => 270, 7 => 270); // Mirrored quarter-turns absent from the v20 helper.
		if (isset($angle[$orientation])) {
			$rotated = imagerotate($img, $angle[$orientation], 0);
			imagedestroy($img);
			$img = $rotated;
		}
		if (!$img) {
			$this->error = 'JpsunCoverInvalidImage';
			return false;
		}
		imagesavealpha($img, true);
		$ok = @imagepng($img, $destination, 6);
		imagedestroy($img);
		if (!$ok) {
			$this->error = 'JpsunCoverWriteError';
		}
		return $ok;
	}

	/** @return array{x:float,y:float,width:float,height:float} Fixed-height centered image. */
	public static function placement(int $width, int $height): array
	{
		$w = 81.5 * $width / max(1, $height);
		return array('x' => 15.0 + (180.0 - $w) / 2, 'y' => 88.5, 'width' => $w, 'height' => 81.5);
	}

	/**
	 * Persist only the two cover fields, with a native row lock and optimistic image revision.
	 * @param Propal $object Authorized proposal
	 * @param User $actor Authorized actor
	 * @param array{error:int,tmp_name:string,size:int}|null $upload PHP upload (never trusts client name)
	 * @return bool
	 */
	public function save($object, $actor, ?array $upload, string $caption, string $revision, bool $remove): bool
	{
		global $conf;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		if (!$actor->hasRight('propal', 'creer') || mb_strlen($caption) > 255
			|| ((int) $object->entity !== (int) $conf->entity && isModEnabled('multicompany') && !$actor->hasRight('multicompany', 'propal', 'write'))) {
			$this->error = 'JpsunCoverUnavailable';
			return false;
		}
		$dir = $this->directory($object);
		if (!$dir || dol_mkdir($dir) < 0) {
			$this->error = 'JpsunCoverDirectoryError';
			return false;
		}
		$newName = '';
		if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
			$limit = min(10 * 1024 * 1024, (int) getDolGlobalInt('MAIN_UPLOAD_DOC', 10000) * 1024);
			if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] <= 0 || $upload['size'] > $limit || !is_uploaded_file($upload['tmp_name'])) {
				$this->error = 'JpsunCoverInvalidImage';
				return false;
			}
			$newName = bin2hex(random_bytes(16)).'.png';
			if (!$this->cleanImage($upload['tmp_name'], $dir.'/'.$newName)) {
				if (is_file($dir.'/'.$newName)) {
					dol_delete_file($dir.'/'.$newName);
				}
				return false;
			}
		}
		if ($this->db->begin() <= 0) {
			$this->error = 'JpsunCoverWriteError';
			if ($newName !== '') {
				dol_delete_file($dir.'/'.$newName);
			}
			return false;
		}
		$res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'propal WHERE rowid = '.((int) $object->id).' AND entity = '.((int) $object->entity).' FOR UPDATE');
		$ok = $res && $this->db->num_rows($res) === 1 && $object->fetch_optionals() >= 0 && $this->loadOwnerValues($object);
		$oldName = (string) ($object->array_options['options_jpsun_cover_image'] ?? '');
		if ($ok && !hash_equals(hash('sha256', $oldName.'\0'.($object->array_options['options_jpsun_cover_caption'] ?? '')), $revision)) {
			$this->error = 'JpsunCoverConcurrentChange';
			$ok = false;
		}
		if ($res) {
			$this->db->free($res);
		}
		$target = $remove ? '' : ($newName !== '' ? $newName : $oldName);
		if ($ok) {
			$object->array_options['options_jpsun_cover_image'] = $target;
			$object->array_options['options_jpsun_cover_caption'] = $remove ? '' : $caption;
			$ok = $object->updateExtraField('jpsun_cover_image', null, $actor) > 0 && $object->updateExtraField('jpsun_cover_caption', null, $actor) > 0;
		}
		if (!$ok || $this->db->commit() <= 0) {
			$this->db->rollback();
			if ($newName !== '') {
				dol_delete_file($dir.'/'.$newName);
			}
			$this->error = $this->error ?: 'JpsunCoverWriteError';
			return false;
		}
		if ($oldName !== $target && preg_match('/^[a-f0-9]{32}\.png$/D', $oldName) && !is_link($dir.'/'.$oldName)) {
			if (is_file($dir.'/'.$oldName) && !dol_delete_file($dir.'/'.$oldName)) {
				dol_syslog(__METHOD__.' old illustration cleanup failed for proposal '.((int) $object->id), LOG_WARNING);
			}
		}
		return true;
	}
}
