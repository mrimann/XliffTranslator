<?php
namespace Mrimann\XliffTranslator;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Mrimann.XliffTranslator".*
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * An interface for xliff translator services
 */
interface XliffTranslatorServiceInterface
{
	/**
	 * Returns an array of packages that are available for translation.
	 *
	 * @return array
	 */
	public function getAvailablePackages();

	/**
	 * Returns an array of files that are available for translation.
	 *
	 * @param string $packageKey package key
	 * @return array
	 */
	public function getAvailableXliffFiles($packageKey);

	/**
	 * Generates a multi-dimensional array, the matrix, containing all translations units from the
	 * source language, combined with (where existing) the translated snippets in the target
	 * language.
	 *
	 * @param string $packageKey package key
	 * @param string $fromLang source language's language key
	 * @param string $toLang target language's language key
	 * @param string $sourceName file name
	 * @return array the translation matrix
	 */
	public function generateTranslationMatrix($packageKey, $fromLang, $toLang, $sourceName = 'Main');

	/**
	 * Returns the translations matrix to be saved
	 *
	 * @param string $packageKey
	 * @param string $fromLang
	 * @param string $toLang
	 * @param array $translationUnits
	 * @param string $sourceName file name
	 *
	 * @return array
	 */
	public function getTranslationMatrixToSave($packageKey, $fromLang, $toLang, array $translationUnits, $sourceName = 'Main');


	/**
	 * Generates a multi-dimensional array, the matrix, containing all translations units to edit. This are either the
	 * target or the source tags, depending wherever the xlf file contains a translation or not.
	 *
	 * @param string $packageKey package key
	 * @param string $editLang source language's language key
	 * @param string $sourceName name of the xlf source file
	 * @return array
	 */
	public function generateEditMatrix($packageKey, $editLang, $sourceName = 'Main');

	/**
	 * Returns the matrix to be saved
	 *
	 * @param string $packageKey
	 * @param string $editLang
	 * @param array $editUnits
	 * @param string $sourceName
	 *
	 * @return array
	 */
	public function getEditMatrixToSave($packageKey, $editLang, array $editUnits, $sourceName = 'Main');

	/**
	 * Saves the new Xliff file
	 *
	 * @param string $packageKey
	 * @param string $language
	 * @param string $content
	 * @param string $sourceName file name
	 */
	public function saveXliffFile($packageKey, $language, $content, $sourceName = 'Main');

}