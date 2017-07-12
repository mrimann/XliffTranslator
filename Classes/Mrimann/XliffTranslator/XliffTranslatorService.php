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
	 * @Flow\InjectConfiguration(package="Mrimann.XliffTranslator")
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
	 * @var \TYPO3\Flow\Cache\CacheManager
	 */
	protected $cacheManager;

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
	 * Returns an array of files that are available for translation.
	 *
	 * @param string $packageKey package key
	 * @return array
	 */
	public function getAvailableXliffFiles($packageKey) {
		$defaultLanguage = $this->settings['defaultLanguage'];
		$resourcePath = $this->packageManager->getPackage($packageKey)->getResourcesPath();

		return glob($resourcePath . 'Private/Translations/' . $defaultLanguage . '/*.xlf');
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
	 * @param string $sourceName name of the xlf source file
	 * @return array the translation matrix
	 */
	public function generateTranslationMatrix($packageKey, $fromLang, $toLang, $sourceName = 'Main') {
		$matrix = array();
		$fromLocale = new \TYPO3\Flow\I18n\Locale($fromLang);
		$fromItems = $this->getXliffDataAsArray($packageKey, $sourceName, $fromLocale);

		$toLocale = new \TYPO3\Flow\I18n\Locale($toLang);
		$toItems = $this->getXliffDataAsArray($packageKey, $sourceName, $toLocale);

		$fromSourceLocale = $fromItems['sourceLocale'];
		$toSourceLocale = $toItems['sourceLocale'];
		foreach ($fromItems['translationUnits'] as $transUnitId => $value) {
			if ($fromSourceLocale->getLanguage() === $fromLang) {
				$fromValue = $value[0]['source'];
			} else {
				$fromValue = $value[0]['target'];
			}
			if ($toSourceLocale->getLanguage() === $toLang) {
				$toValue = isset($toItems['translationUnits'][$transUnitId][0]['source']) ?  $toItems['translationUnits'][$transUnitId][0]['source'] : '';
			} else {
				$toValue = isset($toItems['translationUnits'][$transUnitId][0]['target']) ?  $toItems['translationUnits'][$transUnitId][0]['target'] : '';
			}
			$this->normalizeOutputString($fromValue);
			$this->normalizeOutputString($toValue);

			$matrix[$transUnitId]['source'] = $fromValue;
			$matrix[$transUnitId]['transUnitId'] = $transUnitId;

			// check if untranslated
			if ($toValue == '') {
				$matrix[$transUnitId]['target'] = '';
				$matrix[$transUnitId]['nonTranslated'] = TRUE;
				$matrix[$transUnitId]['class'] = 'nonTranslated';
			} else {
				$matrix[$transUnitId]['target'] = $toValue;
				// check if original text was modified
				if ($toSourceLocale->getLanguage() !== $toLang && $toItems['translationUnits'][$transUnitId][0]['source'] != $fromValue) {
					$matrix[$transUnitId]['originalModified'] = TRUE;
					$matrix[$transUnitId]['class'] = 'modified';
					$matrix[$transUnitId]['originalSource'] = $this->normalizeOutputString($toItems['translationUnits'][$transUnitId][0]['source']);
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
	 * @param string $sourceName
	 *
	 * @return array
	 */
	public function getTranslationMatrixToSave($packageKey, $fromLang, $toLang, array $translationUnits, $sourceName = 'Main') {
		$originalMatrix = $this->generateTranslationMatrix($packageKey, $fromLang, $toLang, $sourceName);
		$matrixToSave = array();

		foreach ($translationUnits as $translationUnit => $value) {
			if (isset($value[$toLang]) && $value[$toLang] != '') {
				$matrixToSave[$translationUnit] = $originalMatrix[$translationUnit];
				if (isset($matrixToSave[$translationUnit]['target'])) {
					$matrixToSave[$translationUnit]['oldTarget'] = $this->normalizeInputString($matrixToSave[$translationUnit]['target']);
				}
				$matrixToSave[$translationUnit]['target'] = $value[$toLang];
				$this->normalizeInputString($matrixToSave[$translationUnit]['source']);
				$this->normalizeInputString($matrixToSave[$translationUnit]['target']);
			}
		}
		return $matrixToSave;
	}

	/**
	 * Generates a multi-dimensional array, the matrix, containing all translations units to edit. This are either the
	 * target or the source tags, depending wherever the xlf file contains a translation or not.
	 *
	 * @param string $packageKey package key
	 * @param string $editLang source language's language key
	 * @param string $sourceName name of the xlf source file
	 * @return array
	 */
	public function generateEditMatrix($packageKey, $editLang, $sourceName = 'Main') {
		$matrix = array();
		$locale = new \TYPO3\Flow\I18n\Locale($editLang);
		$items = $this->getXliffDataAsArray($packageKey, $sourceName, $locale);

		$sourceLang = $items['sourceLocale']->getLanguage();
		foreach ($items['translationUnits'] as $unitId => $value) {
			if ($sourceLang !== $editLang) {
				$matrix[$unitId]['target'] = $this->normalizeOutputString($value[0]['target']);
			}
			$matrix[$unitId]['source'] = $this->normalizeOutputString($value[0]['source']);
			$matrix[$unitId]['transUnitId'] = $unitId;
		}

		return array($sourceLang, $matrix);
	}

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
	public function getEditMatrixToSave($packageKey, $editLang, array $editUnits, $sourceName = 'Main') {
		list($sourceLang, $originalMatrix) = $this->generateEditMatrix($packageKey, $editLang, $sourceName);
		$matrixToSave = array();
		$editTag = ($sourceLang === $editLang) ? 'source' : 'target';
		foreach ($editUnits as $editUnit => $value) {
			if (isset($value[$editLang])) {
				$matrixToSave[$editUnit] = $originalMatrix[$editUnit];
				if (isset($matrixToSave[$editUnit][$editTag])) {
					$matrixToSave[$editUnit]['old' . ucfirst($editTag)] = $this->normalizeInputString($matrixToSave[$editUnit][$editTag]);
				}
				$matrixToSave[$editUnit][$editTag] = $value[$editLang];
			}
			$this->normalizeInputString($matrixToSave[$editUnit]['source']);
			if (isset($matrixToSave[$editUnit]['target'])) {
				$this->normalizeInputString($matrixToSave[$editUnit]['target']);
			}
		}

		return array($sourceLang, $matrixToSave);
	}

	/**
	 * Render and save the Xliff file
	 *
	 * @param string $packageKey
	 * @param string $sourceLang
	 * @param string $targetLang
	 * @param array $matrixToSave
	 * @param \TYPO3\Flow\Mvc\Controller\ControllerContext $context
	 * @param string $sourceName
	 */
	public function saveXliff($packageKey, $sourceLang, $targetLang, array $matrixToSave, \TYPO3\Flow\Mvc\Controller\ControllerContext $context, $sourceName = 'Main') {
		$xliffView = new \TYPO3\Fluid\View\TemplateView();
		$path = 'resource://Mrimann.XliffTranslator/Private/Templates/Standard/Xliff.xlf';

		$xliffView->setControllerContext($context);
		$xliffView->setTemplatePathAndFilename($path);

		$xliffView->assign('sourceLang', $sourceLang);
		$xliffView->assign('targetLang', $targetLang);
		$xliffView->assign('matrixToSave', $matrixToSave);
		$xliffContent = $xliffView->render();

		$this->backupXliffFile($packageKey, $targetLang, $sourceName);
		$this->saveXliffFile($packageKey, $targetLang, $xliffContent, $sourceName);

		$this->cacheManager->getCache('Flow_I18n_XmlModelCache')->flush();
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
			&& !empty($this->getAvailableXliffFiles($package->getPackageKey()))
		) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Gets the full filesystem path to an Xliff file of a specified language within
	 * a specific package.
	 *
	 * @param string $packageKey
	 * @param string $language
	 * @param string $sourceName
	 * @return string
	 */
	protected function getFilePath($packageKey, $language, $sourceName) {
		return \TYPO3\Flow\Utility\Files::concatenatePaths(array($this->packageManager->getPackage($packageKey)->getResourcesPath(), 'Private/Translations/', $language, $sourceName . '.xlf'));
	}

	/**
	 * Creates a backup file of the existing Xliff file before it gets overwritten
	 * by the new data later on.
	 *
	 * @param string $packageKey
	 * @param string $language
	 * @param string $sourceName
	 */
	protected function backupXliffFile($packageKey, $language, $sourceName) {
		$path = $this->getFilePath($packageKey, $language, $sourceName);
		if (is_file($path)) {
			copy($path, $path . '_backup_' . time());
		}
	}

	/**
	 * Saves the new Xliff file and backups the existing one
	 *
	 * @param string $packageKey
	 * @param string $language
	 * @param string $content
	 * @param string $sourceName
	 */
	protected function saveXliffFile($packageKey, $language, $content, $sourceName = 'Main') {
		$outputPath = $this->getFilePath($packageKey, $language, $sourceName);

		// check if the file exists (or create an empty file in case these are the first translations)
		if (!is_dir(dirname($outputPath))) {
			mkdir(dirname($outputPath), 0777, TRUE);
		}
		if (!is_file($outputPath)) {
			touch($outputPath);
		}

		// write the file
		file_put_contents($outputPath, $content);
	}

	/**
	 * Normalizes the input string to be saved:
	 * Adds CDATA tag for strings containing HTML
	 * Converts special chars
	 *
	 * @param string $string
	 * @return string
	 */
	protected function normalizeInputString(&$string) {
		if($string !== strip_tags($string)) {
			$string = '<![CDATA[' . $string . ']]>';
		} else {
			$string = htmlspecialchars($string);
		}
		return $string;
	}

	/**
	 * Normalizes the output string:
	 * Removes xliff formatting tabs and spaces
	 *
	 * @param string $string
	 * @return string
	 */
	protected function normalizeOutputString(&$string) {
		$string = trim(preg_replace('/\t+| {2,}/', '', $string));
		return $string;
	}
}
