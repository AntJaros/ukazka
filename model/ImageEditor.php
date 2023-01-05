<?php

namespace App\Model;

use Nette;


/**
 * Třída pro s obrázkama a fotkama
 * @package App\Model
 */
class ImageEditor
{
	use Nette\SmartObject;


	const
		IMG_WIDTH_MAX_RES = 3000,
		IMG_HEIGHT_MAX_RES = 3000;

	/** @var string */
	private $dir;


	/**
	 * ImageDirectories constructor.
	 * @param $dir
	 */
	public function __construct($dir)
	{
		$this->dir = $dir;
	}


	/**
	 * Získá cestu k adresáři ukládání obrázků.
	 */
	public function getDir(): string
	{
		return $this->dir;
	}


	/**
	 * Kontroluje maximální rozměr obrázků.
	 * @param $width
	 * @param $height
	 * @throws Nette\Utils\ImageException
	 */
	public function checkResolution($width, $height) {
		if ($width > self::IMG_WIDTH_MAX_RES || $height > self::IMG_HEIGHT_MAX_RES) {
			throw new Nette\Utils\ImageException('Velké rozměry obrázku.');
		}
	}
}