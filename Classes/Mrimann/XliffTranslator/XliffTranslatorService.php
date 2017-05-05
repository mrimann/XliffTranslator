<?php
namespace Mrimann\XliffTranslator;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Mrimann.XliffTranslator".*
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * A Service which contains the main functionality of the XliffTranslator
 *
 * @Flow\Scope("singleton")
 */
class XliffTranslatorService implements XliffTranslatorServiceInterface
{

	/**
	 * @Flow\InjectConfiguration
	 * @var array
	 */
	protected $settings;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Package\PackageManagerInterface
	 */
	protected $packageManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\I18n\Service
	 */
	protected $localizationService;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\I18n\Xliff\XliffParser
	 */
	protected $xliffParser;

	/**
	 * Returns an array of packages that are available for translation.
	 *
	 * The list includes basically every active package
	 * - minus the ones that are excluded in the configuration
	 * - minus the ones that don't have the needed translation files
	 *
	 * @return array all the available packages
	 */
	public function getAvailablePackages() {
		$allPackages = $this->packageManager->getActivePackages();

		// make sure the packages of the framework are excluded depending on our settings
		$packages = array();
		$packagesToExclude = \TYPO3\Flow\Utility\Arrays::trimExplode(',', $this->settings['packagesToExclude']);
		/** @var \TYPO3\Flow\Package\Package $package */
		foreach ($allPackages as $package) {
			if (!in_array($package->getPackageKey(), $packagesToExclude)
				&& $this->hasXliffFilesInDefaultDirectories($package)
			) {
				$packages[] = $package;
			}
		}

		return $packages;
	}

	/**
	 * Generates a multi-dimensional array, the matrix, containing all translations units from the
	 * source language, combined with (where existing) the translated snippets in the target
	 * language.
	 *
	 * For each translation unit, the status is stated in the matrix (new and untranslated, modified
	 * or already translated)
	 *
	 * @param string $packageKey package key
	 * @param string $fromLang source language's language key
	 * @param string $toLang target language's language key
	 * @return array the translation matrix
	 */
	public function generateTranslationMatrix($packageKey, $fromLang, $toLang) {
		$matrix = array();
		$fromLocale = new \TYPO3\Flow\I18n\Locale($fromLang);
		$fromItems = $this->getXliffDataAsArray($packageKey, 'Main', $fromLocale);

		$toLocale = new \TYPO3\Flow\I18n\Locale($toLang);
		$toItems = $this->getXliffDataAsArray($packageKey, 'Main', $toLocale);

		foreach ($fromItems['translationUnits'] as $transUnitId => $value) {
			$matrix[$transUnitId]['source'] = $value[0]['source'];
			$matrix[$transUnitId]['transUnitId'] = $transUnitId;

			// check if untranslated
			if (!isset($toItems['translationUnits'][$transUnitId][0]['target'])
				|| $toItems['translationUnits'][$transUnitId][0]['target'] == ''
			) {
				$matrix[$transUnitId]['target'] = '';
				$matrix[$transUnitId]['nonTranslated'] = TRUE;
				$matrix[$transUnitId]['class'] = 'nonTranslated';
			} else {
				$matrix[$transUnitId]['target'] = $toItems['translationUnits'][$transUnitId][0]['target'];
				// check if original text was modified
				if ($toItems['translationUnits'][$transUnitId][0]['source'] != $value[0]['source']) {
					$matrix[$transUnitId]['originalModified'] = TRUE;
					$matrix[$transUnitId]['class'] = 'modified';
					$matrix[$transUnitId]['originalSource'] = $toItems['translationUnits'][$transUnitId][0]['source'];
				} else {
					// translation available AND original text is still the same
					$matrix[$transUnitId]['perfetto'] = TRUE;
					$matrix[$transUnitId]['class'] = 'ok';
				}
			}
		}

		return $matrix;
	}

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
	public function getTranslationMatrixToSave($packageKey, $fromLang, $toLang, array $translationUnits) {
		$originalMatrix = $this->generateTranslationMatrix($packageKey, $fromLang, $toLang);
		$matrixToSave = array();

		foreach ($translationUnits as $translationUnit => $value) {
			if (isset($value[$toLang]) && $value[$toLang] != '') {
				$matrixToSave[$translationUnit] = $originalMatrix[$translationUnit];
				if (isset($matrixToSave[$translationUnit]['target'])) {
					$matrixToSave[$translationUnit]['oldTarget'] = $matrixToSave[$translationUnit]['target'];
				}
				$matrixToSave[$translationUnit]['target'] = $value[$toLang];
			}
		}
		return $matrixToSave;
	}

	/**
	 * Saves the new Xliff file and backups the existing one
	 *
	 * @param string $packageKey
	 * @param string $language
	 * @param string $content
	 */
	public function saveXliffFile($packageKey, $language, $content) {
		// backup the original file before overwriting
		$this->backupXliffFile($packageKey, $language);

		// check if the file exists (or create an empty file in case these are the first translations)
		if (!is_dir(dirname($this->getFilePath($packageKey, $language)))) {
			mkdir(dirname($this->getFilePath($packageKey, $language)));
		}
		if (!is_file($this->getFilePath($packageKey, $language))) {
			touch($this->getFilePath($packageKey, $language));
		}

		// write the file
		$outputPath = $this->getFilePath($packageKey, $language);
		file_put_contents($outputPath, $content);
	}

	/**
	 * Reads a particular Xliff file and returns it's translation units as array entries
	 *
	 * @param string $packageKey package key
	 * @param string $sourceName source name (e.g. filename)
	 * @param \TYPO3\Flow\I18n\Locale the locale
	 *
	 * @return array
	 */
	protected function getXliffDataAsArray($packageKey, $sourceName, \TYPO3\Flow\I18n\Locale $locale) {
		$sourcePath = \TYPO3\Flow\Utility\Files::concatenatePaths(array('resource://' . $packageKey, 'Private/Translations'));
		list($sourcePath, $foundLocale) = $this->localizationService->getXliffFilenameAndPath($sourcePath, $sourceName, $locale);

		return $this->xliffParser->getParsedData($sourcePath);
	}

	/**
	 * Checks if a package is qualified to be shown for translations (e.g. the default
	 * directories and the file exists.
	 *
	 * The check depends on the default language from the configuration!
	 *
	 * @param \TYPO3\Flow\Package\Package $package
	 * @return boolean
	 */
	protected function hasXliffFilesInDefaultDirectories(\TYPO3\Flow\Package\Package $package) {
		$packageBasePath = $package->getResourcesPath();
		$defaultLanguage = $this->settings['defaultLanguage'];

		if (is_dir($packageBasePath . 'Private/Translations')
			&& is_dir($packageBasePath . 'Private/Translations/' . $defaultLanguage)
			&& is_file($packageBasePath . 'Private/Translations/' . $defaultLanguage . '/Main.xlf')
		) {
			return true;
		}

		return false;
	}

	/**
	 * Gets the full filesystem path to an Xliff file of a specified language within
	 * a specific package.
	 *
	 * @param string $packageKey
	 * @param string $language
	 * @return string
	 */
	protected function getFilePath($packageKey, $language) {
		return $this->packageManager->getPackage($packageKey)->getPackagePath() . 'Resources/Private/Translations/' . $language . '/Main.xlf';
	}

	/**
	 * Creates a backup file of the existing Xliff file before it gets overwritten
	 * by the new data later on.
	 *
	 * @param string $packageKey
	 * @param string $language
	 */
	protected function backupXliffFile($packageKey, $language) {
		if (is_file($this->getFilePath($packageKey, $language))) {
			copy(
				$this->getFilePath($packageKey, $language),
				$this->getFilePath($packageKey, $language) . '_backup_' . time()
			);
		}
	}
}
