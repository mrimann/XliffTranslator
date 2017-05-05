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
     * Generates a multi-dimensional array, the matrix, containing all translations units from the
     * source language, combined with (where existing) the translated snippets in the target
     * language.
     *
     * @param string $packageKey package key
     * @param string $fromLang source language's language key
     * @param string $toLang target language's language key
     * @return array the translation matrix
     */
    public function generateTranslationMatrix($packageKey, $fromLang, $toLang);

    /**
     * Returns the translations matrix to be saved
     *
     * @param string $packageKey
     * @param string $fromLang
     * @param string $toLang
     * @param array $translationUnits
     *
     * @return array
     */
    public function getTranslationMatrixToSave($packageKey, $fromLang, $toLang, array $translationUnits);

    /**
     * Saves the new Xliff file
     *
     * @param string $packageKey
     * @param string $language
     * @param string $content
     */
    public function saveXliffFile($packageKey, $language, $content);

}