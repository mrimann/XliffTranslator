<?php
namespace Mrimann\XliffTranslator\Controller;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Mrimann.XliffTranslator".*
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Standard controller for the Mrimann.XliffTranslator package
 *
 * @Flow\Scope("singleton")
 */
class StandardController extends \TYPO3\Flow\Mvc\Controller\ActionController {

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
	 * Index action
	 *
	 * @return void
	 */
	public function indexAction() {
		$packages = $this->packageManager->getActivePackages();
		$this->view->assign('packages', $packages);
	}

	/**
	 * Renders the form to translate a specific Xliff file from language A to language B
	 *
	 * @param string $packageKey
	 * @param string $fromLang
	 * @param string $toLang
	 */
	public function translateAction($packageKey = '', $fromLang = '', $toLang = '') {
		$this->view->assign('packageKey', $packageKey);
		$this->view->assign('languages', explode(',', $this->settings['availableLanguages']));
		$this->view->assign('fromLang', $fromLang);
		$this->view->assign('toLang', $toLang);

		// check if we're ready for the actual translation
		if ($fromLang != '' && $toLang != '') {
			$this->view->assign('readyForTranslating', TRUE);
			$this->view->assign('translationMatrix', $this->generateTranslationMatrix($packageKey, $fromLang, $toLang));
		}
	}

	protected function generateTranslationMatrix($packageKey, $fromLang, $toLang) {
		$fromLocale = new \TYPO3\Flow\I18n\Locale($fromLang);
		$fromItems = $this->getModel($packageKey, 'Main', $fromLocale);

		$toLocale = new \TYPO3\Flow\I18n\Locale($toLang);
		$toItems = $this->getModel($packageKey, 'Main', $toLocale);

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


	protected function getModel($packageKey, $sourceName, \TYPO3\Flow\I18n\Locale $locale) {
		$sourcePath = \TYPO3\Flow\Utility\Files::concatenatePaths(array('resource://' . $packageKey, 'Private/Translations'));
		list($sourcePath, $foundLocale) = $this->localizationService->getXliffFilenameAndPath($sourcePath, $sourceName, $locale);

		$result = $this->xliffParser->getParsedData($sourcePath);
		return $result;
		return new \TYPO3\Flow\I18n\Xliff\XliffModel($sourcePath, $foundLocale);
	}

	/**
	 * Saves the translations from the translation form
	 *
	 * @param string $packageKey
	 * @param string $fromLang
	 * @param string $toLang
	 * @param array $translationUnits
	 */
	public function saveTranslationsAction($packageKey, $fromLang, $toLang, array $translationUnits) {
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

		// Create the Xliff file to be written to disk later on
		$xliffView = new \TYPO3\Fluid\View\TemplateView();
		$path = 'resource://Mrimann.XliffTranslator/Private/Templates/Standard/Xliff.xlf';

		$xliffView->setControllerContext($this->getControllerContext());
		$xliffView->setTemplatePathAndFilename($path);
 		$xliffView->assign('matrixToSave', $matrixToSave);

		// backup the original file before overwriting
		$this->backupXliffFile($packageKey, $toLang);

		// write the file
		$outputPath = $this->getFilePath($packageKey, $toLang);
		fopen($outputPath, 'w');
		file_put_contents($outputPath, $xliffView->render());

		die();
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
		copy(
			$this->getFilePath($packageKey, $language),
			$this->getFilePath($packageKey, $language) . '_backup_' . time()
		);
	}
}

?>