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
}

?>